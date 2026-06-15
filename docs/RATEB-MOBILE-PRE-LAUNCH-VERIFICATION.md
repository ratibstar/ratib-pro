# RATEB Mobile — Final Pre-Launch Verification

**Date:** 25 May 2026  
**Production API:** `https://rateb.sa/api/mobile`  
**Method:** Live HTTP probes, static code audit, release build checks  
**Scope:** Launch-readiness only — no new features

---

## Launch readiness score: **58 / 100 — NOT READY**

| Area | Score | Status |
|------|-------|--------|
| Production ENV / secrets | **25 / 100** | **BLOCKED** — `MOBILE_AUTH_SECRET` missing on live server |
| Tenant isolation (code) | **85 / 100** | Hardening deployed; DB population unverified |
| Flutter app (release) | **88 / 100** | `flutter build web --release` passes |
| Android release | **55 / 100** | Config OK; AAB build failed (Gradle network) |
| Pilot flows (live) | **35 / 100** | Auth/dashboard/QR **cannot succeed** until secret set |

**Verdict:** Do **not** start external pilot until `MOBILE_AUTH_SECRET` is set on production and verified with a successful login.

---

## 1. Production ENV verification

### Code audit (repo) — PASS

| Check | Result |
|-------|--------|
| Default production secret `rateb-mobile-change-me-in-production` | **Removed** — not in codebase |
| Dev-only fallback | `rateb-mobile-dev-only-not-for-production` — **localhost only** |
| Production fail-closed | `rateb_mobile_is_production()` + `rateb_mobile_config_error()` → 503 |
| Secret in API responses | **Never exposed** |
| `config/env/rateb_sa.php` | Loads `MOBILE_AUTH_SECRET` from `.env` when present |

### Live production (`rateb.sa`) — FAIL

| Endpoint | Test | HTTP | Body |
|----------|------|------|------|
| `health.php` | GET | 200 | OK — no auth/secret needed |
| `login.php` | POST invalid password | **401** | `invalid_credentials` — **does not prove secret is set** (token never issued) |
| `profile.php` | GET Bearer `aaa.bbb.ccc` | **503** | `config_error` |
| `worker-dashboard.php` | GET Bearer (invalid) | **503** | `config_error` |
| `company-workers.php` | GET Bearer (invalid) | **503** | `config_error` |

**Interpretation:** Hardened `bootstrap.php` **is deployed**. Any JWT validate/issue path returns **503** because **`MOBILE_AUTH_SECRET` is not configured** on the server.

**Misconfiguration detection:** 503 appears only on real JWT paths — not on health or invalid-password login. This is correct behavior, but **invalid login 401 is misleading** for ops monitoring.

### Required action (P0)

Add to production `.env` (project root on server):

```
MOBILE_AUTH_SECRET=<32+ character cryptographically random string>
```

Verify after deploy:

```bash
# Should return 401 Unauthorized (not 503)
curl -H "Authorization: Bearer aaa.bbb.ccc" \
  https://rateb.sa/api/mobile/profile.php
```

Then test real login from the app.

---

## 2. Tenant data verification

### Code (deployed logic) — PASS with caveats

| Endpoint | Scoping mechanism |
|----------|-------------------|
| `company-workers.php` | JWT `country_id` → `workers.country_id` OR `agents.tenant_id` / `agents.country_id` |
| `company-requests.php` | `cases.country_id` / `cases.tenant_id` OR worker/agent tenant join |
| `worker-dashboard.php`, `worker-tasks.php` | Own worker via email + tenant filter |
| `agency-pipeline.php`, `agency-assignments.php` | JWT partner `sub` = agency id only |

Staff with **`country_id = 0`** → empty lists (`1=0` scope), not cross-tenant leak.

### Live DB — CANNOT VERIFY (no DB access)

Per `docs/COUNTRY_DATA_ISOLATION_GUIDE.md`, full isolation may be **separate DB per country** (Option A) rather than shared `country_id` columns.

### Warnings (must confirm before pilot)

| Warning | Risk | Action |
|---------|------|--------|
| **`workers.country_id` may be missing or NULL** | Company dashboard shows zeros; tenant filter may fall back to `agents.tenant_id` or 503 | DBA: `SHOW COLUMNS FROM workers LIKE 'country_id'` + `SELECT COUNT(*) FROM workers WHERE country_id IS NULL OR country_id = 0` |
| **`agents.tenant_id` may be missing or NULL** | Same | DBA: `SHOW COLUMNS FROM agents LIKE 'tenant_id'` + null count |
| **Pilot users with `users.country_id = 0`** | Empty dashboard (fail-safe, not a leak) | Assign country to all pilot accounts |
| **`admin` mapped to company role** | Admin sees company portal, not worker/agency | Use role-appropriate pilot accounts |
| **Cross-tenant leak test not run** | Cannot test without valid JWT + two tenant accounts | After secret fix: login two country users, compare worker IDs |

---

## 3. Android release verification

| Check | Result |
|-------|--------|
| `INTERNET` permission | Present in `AndroidManifest.xml` |
| `CAMERA` permission | Present (QR on native) |
| App name | **RATEB** (`strings.xml`) |
| Launcher icon | Default Flutter mipmap — **valid placeholder**, not branded |
| Splash | White `launch_background.xml` — **valid placeholder** |
| Release signing config | `key.properties` + Gradle — ready when keystore exists |
| `flutter build appbundle` | **FAILED** — Gradle wrapper download `SocketException` (network) |
| `flutter build web --release` | **PASS** |
| Debug-only code | `kDebugMode` used only for verbose error text — release shows generic messages |
| QR in release | `mobile_scanner` + camera permission; manual paste fallback on web |

**Android blocker:** Retry AAB build on stable network; create upload keystore before Play internal track.

---

## 4. Pilot flow simulation

| Flow | Code path | Live production today |
|------|-----------|------------------------|
| Password login | Auth → JWT → router | **FAIL on success** — `issue_token` → 503 |
| Dashboard load | Bearer GET endpoints | **FAIL** — 503 on all authenticated routes |
| QR login | `qr-login.php` → JWT | **FAIL** for valid signed QR — needs secret |
| Logout | Clear session + cache + `/login` | OK (client-only) |
| Offline mode | `NetworkMonitor` + cache + banner | OK (client-only; needs prior successful load) |
| Token expiry | 401 → `handleUnauthorized` | OK in code; untested live without valid tokens |
| Tab navigation | `StatefulShellRoute` | OK (client-only) |
| Crashes | No blocking compile errors | Web release build passes |

**No blocking Flutter crashes identified in code.** Production API misconfiguration blocks all authenticated flows.

---

## 5. Remaining blockers

| Priority | Blocker | Est. effort |
|----------|---------|-------------|
| **P0** | Set `MOBILE_AUTH_SECRET` on production `.env` | 15 min |
| **P0** | Verify login + profile return 200/401 (not 503) | 5 min |
| **P0** | Confirm tenant columns populated OR accept empty pilot data | 1–2 hrs DBA |
| **P1** | Assign `country_id` to all pilot user accounts | 30 min |
| **P1** | Android AAB build + Play internal testing track | 2–4 hrs |
| **P2** | Brand launcher icon / splash | Design |
| **P2** | Privacy policy URL for Play Console | Ops |

---

## 6. Recommended first pilot rollout strategy

### Phase 0 — Unblock (Day 0, same day)

1. Set `MOBILE_AUTH_SECRET` on server; reload PHP/env.
2. Smoke test: login → company dashboard → workers tab → logout.
3. Confirm pilot users have `country_id` set and workers exist for that tenant.

### Phase 1 — Web internal pilot (Days 1–3)

- **Channel:** `flutter build web` hosted on `rateb.sa` subdomain or internal URL.
- **Users:** 5 internal staff (see below).
- **Goal:** Auth, tabs, live data, QR paste login, session refresh.
- **Rollback:** Disable mobile URL; secret rotation if compromise suspected.

### Phase 2 — Android internal track (Days 4–7)

- Build signed AAB after keystore + network fix.
- Play Console **Internal testing** only (not production track).
- Same 5 users + 2 Android devices for QR camera test.

### Phase 3 — Controlled expansion (Week 2+)

- Add 10–15 users if Phase 1–2 clean.
- One sending-country tenant only (avoid multi-country until DB isolation confirmed).

---

## 7. Safe number of initial pilot users

| Phase | Users | Roles | Rationale |
|-------|-------|-------|-----------|
| **Phase 1 (web)** | **5** | 2 company, 2 worker, 1 agency | Small blast radius; covers all portals |
| **Phase 2 (Android)** | **Same 5** | Same | No new users until native QR verified |
| **Phase 3** | **+10 max (15 total)** | Mixed | Only after 72h without P0/P1 incidents |

**Do not exceed 15 users** until tenant column population and cross-tenant negative test are documented.

Suggested pilot accounts:

- 1× company manager (known `country_id`, workers in DB)
- 1× company admin (same tenant)
- 2× workers linked by email to worker records
- 1× partner agency with portal enabled

---

## 8. Quick go/no-go checklist

- [ ] `MOBILE_AUTH_SECRET` set on production
- [ ] `curl profile.php` with bad token → **401**, not 503
- [ ] Real login works in app
- [ ] Company dashboard shows non-zero OR confirmed empty DB (not config error)
- [ ] Two-tenant negative test (optional but recommended)
- [ ] Web release build deployed
- [ ] Android AAB on internal track (if mobile native required)

**Current go/no-go: NO-GO** until first two items pass.

---

*Final pre-launch verification complete. No code changes in this pass.*
