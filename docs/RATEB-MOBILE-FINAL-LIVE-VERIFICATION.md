# RATEB Mobile — Final Live Production Verification

**Date:** 25 May 2026 (live probes)  
**API base:** `https://out.ratib.sa/api/mobile`  
**Method:** Live HTTP probes + code audit + `flutter build web --release`  
**Scope:** Verification only — no features, no refactors

---

## Executive summary

| Item | Result |
|------|--------|
| **Launch readiness score** | **42 / 100** |
| **Verdict** | **NO-GO** |
| **Primary blocker** | `MOBILE_AUTH_SECRET` **not loaded** on production — JWT paths still return **503 `config_error`** |

Hardening **is deployed** (503 on JWT validate). Secret deployment **is not effective yet** — any request that invokes `rateb_mobile_token_secret()` with a non-empty Bearer token fails.

---

## Phase 1 — ENV / secret verification

### Code audit

| Check | Result |
|-------|--------|
| Default production secret removed | **PASS** — not in codebase |
| Dev-only fallback | **PASS** — `rateb-mobile-dev-only-not-for-production`, localhost only |
| Fail-closed on `*.ratib.sa` without secret | **PASS** — returns 503 |
| Secret never in JSON responses | **PASS** |
| `.env` bridge allowlist includes `MOBILE_AUTH_SECRET` | **PASS** in repo (`config/env/load.php`) — **must be deployed to server** |

### Live probes — invalid Bearer `aaa.bbb.ccc`

| Endpoint | HTTP | Code | Expected | Result |
|----------|------|------|----------|--------|
| `profile.php` | **503** | `config_error` | 401 | **FAIL** |
| `worker-dashboard.php` | **503** | `config_error` | 401 | **FAIL** |
| `company-workers.php` | **503** | `config_error` | 401 | **FAIL** |
| `company-requests.php` | **503** | `config_error` | 401 | **FAIL** |
| `agency-pipeline.php` | **503** | `config_error` | 401 | **FAIL** |
| `agency-assignments.php` | **503** | `config_error` | 401 | **FAIL** |
| `worker-tasks.php` | **503** | `config_error` | 401 | **FAIL** |

### Live probes — no Authorization header

| Endpoint | HTTP | Result |
|----------|------|--------|
| `profile.php` | **401** Unauthorized | **PASS** (no secret call — empty token) |
| `worker-dashboard.php` | **401** `unauthorized` | **PASS** |

**Interpretation:** Missing header skips secret load; **any non-empty Bearer** triggers secret → **503**. Confirms secret is **still absent** server-side.

### Health / CORS

| Probe | Result |
|-------|--------|
| `GET health.php` | **200** PASS |
| `OPTIONS health.php` | **204**, `Access-Control-Allow-Origin: *` PASS |

### Phase 1 overall: **FAIL**

---

## Phase 2 — Auth flow verification

### Live results

| Test | HTTP | Body code | Expected | Result |
|------|------|-----------|----------|--------|
| Invalid password login | **401** | `invalid_credentials` | 401 | **PASS** |
| Valid login | *not run* | — | 200 + token | **BLOCKED** — would 503 on `issue_token` until secret loads |
| Logout | *client-only* | — | session cleared | **PASS** (code audit) |
| Session restore | *client-only* | — | JWT from SharedPreferences | **PASS** (code audit) |

### Exact verification commands

```bash
# A) Secret loaded? (MUST be 401, not 503)
curl -sS -w "\nHTTP %{http_code}\n" \
  -H "Authorization: Bearer aaa.bbb.ccc" \
  https://out.ratib.sa/api/mobile/profile.php

# B) Invalid login
curl -sS -w "\nHTTP %{http_code}\n" -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"wrong","password":"wrong"}' \
  https://out.ratib.sa/api/mobile/login.php

# C) Valid login (replace credentials)
curl -sS -w "\nHTTP %{http_code}\n" -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"PILOT_USER","password":"PILOT_PASS"}' \
  https://out.ratib.sa/api/mobile/login.php

# D) Profile with token
curl -sS -w "\nHTTP %{http_code}\n" \
  -H "Authorization: Bearer TOKEN_HERE" \
  https://out.ratib.sa/api/mobile/profile.php

# E) Logout (optional server-side)
curl -sS -X POST \
  -H "Authorization: Bearer TOKEN_HERE" \
  https://out.ratib.sa/api/mobile/logout.php
```

### Expected after secret fix

| Step | HTTP | Notes |
|------|------|-------|
| A | **401** | Not 503 |
| B | **401** | `invalid_credentials` |
| C | **200** | `"token":"eyJ..."`, `"role":"company|worker|agency"` |
| D | **200** | `"success":true,"data":{...}` |
| E | **200** | Best-effort; client always clears storage |

### Phase 2 overall: **PARTIAL FAIL** (login success path blocked)

---

## Phase 3 — QR flow verification

### Live results

| Test | HTTP | Code | Expected | Result |
|------|------|------|----------|--------|
| Empty payload | **400** | `invalid` | 400 | **PASS** |
| Unrecognized format | **401** | `invalid_format` | 401 | **PASS** (before secret) |
| Bad signature `RATEBMOBQR:abc.def` | **503** | `config_error` | 401 `invalid_signature` | **FAIL** — secret required for sig verify |
| `qr-generate.php` no auth | **401** | Unauthorized | 401 | **PASS** |
| Expired QR | *not run* | — | 401 `expired` | **BLOCKED** — needs valid signed QR |
| Reused nonce | *not run* | — | 401 `nonce_reused` | **BLOCKED** — needs generate + double login |

### QR generate + validate (after secret fix)

```bash
# 1) Login to get token
TOKEN=$(curl -sS -X POST -H "Content-Type: application/json" \
  -d '{"email":"USER","password":"PASS"}' \
  https://out.ratib.sa/api/mobile/login.php | jq -r .token)

# 2) Generate QR
curl -sS -X POST -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}' \
  https://out.ratib.sa/api/mobile/qr-generate.php

# 3) QR login (single use)
curl -sS -X POST -H "Content-Type: application/json" \
  -d '{"qr_payload":"RATEBMOBQR:..."}' \
  https://out.ratib.sa/api/mobile/qr-login.php

# 4) Replay same payload — expect nonce_reused
```

### Phase 3 overall: **PARTIAL FAIL** (signature path blocked by 503)

---

## Phase 4 — Tenant isolation verification

### Code audit (deployed logic in repo)

| Route | Scoping | Result |
|-------|---------|--------|
| `company-workers.php` | JWT `country_id` → `workers.country_id` or `agents.tenant_id` | **PASS** (code) |
| `company-requests.php` | `cases.country_id` / tenant join via workers/agents | **PASS** (code) |
| Worker routes | `rateb_mobile_resolve_worker()` + tenant filter | **PASS** (code) |
| Agency routes | `rateb_mobile_resolve_agency_id()` + `workersByAgency($id)` | **PASS** (code) |

### Live verification

**BLOCKED** — cannot obtain JWT while secret returns 503 on token issue/validate with Bearer.

### Empty-data causes (not leaks)

| Cause | Symptom | Risk |
|-------|---------|------|
| `users.country_id = 0` | Empty company dashboard | Low — fail-safe |
| No workers for tenant | Zero counts | Low — data issue |
| Worker email not linked to `workers` row | Worker tasks show link prompt | Low |
| Missing `country_id`/`tenant_id` columns | **503 config_error** from tenant helper | Medium |

### SQL spot-checks (phpMyAdmin)

```sql
SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND ((TABLE_NAME='workers' AND COLUMN_NAME IN ('country_id','agent_id'))
    OR (TABLE_NAME='users' AND COLUMN_NAME='country_id')
    OR (TABLE_NAME='agents' AND COLUMN_NAME IN ('tenant_id','country_id'))
    OR (TABLE_NAME='cases' AND COLUMN_NAME IN ('country_id','tenant_id')));

SELECT COUNT(*) FROM users WHERE country_id IS NULL OR country_id = 0;
SELECT COUNT(*) FROM workers WHERE status != 'deleted';
```

### Remaining risks

- Cross-tenant negative test **not executed** (no valid tokens)
- Admin users default to **company** portal role
- Separate DB per country (Option A) may make column filters redundant but not harmful

### Phase 4 overall: **PASS** (code) / **NOT LIVE-VERIFIED**

---

## Phase 5 — Flutter app verification

| Check | Result |
|-------|--------|
| `flutter build web --release` | **PASS** (~40s) |
| Router / `StatefulShellRoute` | **PASS** (code audit) |
| Tabs via `PortalShell.goBranch` | **PASS** (code audit) |
| Login screen | **PASS** (code audit) |
| Offline banner (Stack overlay) | **PASS** (code audit) |
| Stale cache + retry | **PASS** (code audit) |
| Logout → `AppRouter.login` | **PASS** (code audit) |
| `kDebugMode` error detail only | **PASS** — release uses generic messages |
| Uncaught exception handlers | `FlutterError.onError` logs — **PASS** |
| API spam | Cache + background refresh — **PASS** (code audit) |

**Live app against production:** Login success → dashboard API calls will **503** until backend secret fixed.

### Phase 5 overall: **PASS** (build + code) / **FAIL** (live E2E against prod)

---

## Phase 6 — Pilot readiness

| Area | Score | Status |
|------|-------|--------|
| Production ENV / secret | **15 / 100** | **FAIL** — 503 on JWT paths |
| Auth (password) | **40 / 100** | Invalid login OK; success path blocked |
| QR | **35 / 100** | Pre-signature OK; sign/verify blocked |
| Tenant isolation | **75 / 100** | Code OK; live unverified |
| Flutter release (web) | **90 / 100** | Build passes |
| Android readiness | **50 / 100** | Manifest OK; AAB build failed (network) |
| Pilot stability | **25 / 100** | No authenticated E2E on prod |

### Final launch readiness score: **42 / 100**

### GO / NO-GO verdict: **NO-GO**

Pilot cannot start until Phase 1 probe returns **401** (not 503) on invalid Bearer.

---

## Remaining blockers (ordered)

| # | Blocker | Action |
|---|---------|--------|
| 1 | Secret not loaded | Add `MOBILE_AUTH_SECRET=...` to server `.env` (project root) |
| 2 | Env bridge | Deploy `config/env/load.php` with allowlist entry |
| 3 | PHP reload | Reload PHP-FPM / LiteSpeed on cPanel |
| 4 | Verify | `curl profile.php -H "Authorization: Bearer x.y.z"` → **401** |
| 5 | Valid login smoke | POST login → 200 + token |
| 6 | Pilot user `country_id` | SQL spot-check / assign if zero |
| 7 | Android AAB | Retry `flutter build appbundle` on stable network |

---

## Safe pilot user count

| Stage | Users | When |
|-------|-------|------|
| **Now** | **0** | NO-GO |
| After secret verified | **5** | 2 company, 2 worker, 1 agency |
| Week 2 | **+10 max (15 total)** | After 72h clean web pilot |

---

## Recommended rollout sequence

1. **Fix secret on server** + deploy `load.php` + reload PHP  
2. Re-run Phase 1 curl (503 → 401)  
3. Valid login + dashboard curl with real pilot account  
4. **Web pilot** — 5 internal users, 72 hours  
5. Tenant SQL spot-checks on pilot accounts  
6. Android internal track (after AAB + keystore)  
7. Expand to 15 users max  

---

## Immediate ops checklist (copy/paste)

```bash
# After setting MOBILE_AUTH_SECRET in .env and deploying load.php:

# MUST PASS before any pilot user:
curl -sS -o /dev/null -w "%{http_code}\n" \
  -H "Authorization: Bearer test.token.here" \
  https://out.ratib.sa/api/mobile/profile.php
# Expected: 401

curl -sS -X POST -H "Content-Type: application/json" \
  -d '{"email":"REAL_USER","password":"REAL_PASS"}' \
  https://out.ratib.sa/api/mobile/login.php
# Expected: 200 with token field
```

---

*Final live verification complete. Production secret deployment is **not yet effective** as of probe time.*
