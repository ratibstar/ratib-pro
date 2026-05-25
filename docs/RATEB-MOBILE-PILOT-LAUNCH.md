# RATEB Mobile — Pilot Launch Hardening Report

**Date:** 25 May 2026  
**Scope:** Critical pilot-launch items only (no new features)

---

## 1. Fix summary

| # | Area | Change |
|---|------|--------|
| 1 | **JWT/QR secret** | `bootstrap.php` — production hosts (`*.ratib.sa`) **fail closed** with HTTP 503 `config_error` if `MOBILE_AUTH_SECRET` missing; dev localhost keeps non-production fallback only |
| 2 | **Secret logging** | `error_log('CRITICAL: MOBILE_AUTH_SECRET is not configured…')` — secret value never logged or returned |
| 3 | **Env wiring** | `config/env/out_ratib_sa.php` — loads `MOBILE_AUTH_SECRET` from `.env` into `define()` when set |
| 4 | **Tenant isolation** | New `api/mobile/tenant.inc.php` — country/agency scope from JWT claims only |
| 5 | **Company workers** | `company-workers.php` — tenant-scoped via `workers.country_id` or `agents.tenant_id` / `agents.country_id` |
| 6 | **Company requests** | `company-requests.php` — cases scoped by `cases.country_id`, `cases.tenant_id`, or worker/agent tenant join |
| 7 | **Worker resolve** | `auth.inc.php` — worker lookup by email/name includes same tenant filter |
| 8 | **Agency APIs** | `agency-pipeline.php`, `agency-assignments.php` — use `rateb_mobile_resolve_agency_id()` from JWT |
| 9 | **Resilience** | `resilient_loader.dart` — no retry on 401/403 (prior QA fix retained) |
| 10 | **Logout/nav** | `portal_shell.dart` — logout → `AppRouter.login`; cache cleared on logout/401 |
| 11 | **Session restore** | `auth_provider.dart` — bootstrap validates token via profile; clears stale sessions |
| 12 | **Android manifest** | App label **RATEB**, `INTERNET` + `CAMERA` permissions |
| 13 | **Android release** | `build.gradle.kts` — release signing from `key.properties` when present; debug fallback for local builds |
| 14 | **Signing template** | `android/key.properties.example` |

---

## 2. Security findings

### Resolved this pass

| Finding | Severity | Resolution |
|---------|----------|------------|
| Default JWT secret in production | **Critical** | Production returns 503; no default secret on `*.ratib.sa` |
| Company workers global read | **High** | Tenant scope enforced from JWT/user `country_id` |
| Company cases global read | **High** | Tenant scope on cases/workers/agents |
| Worker match by email cross-tenant | **Medium** | Tenant filter on `rateb_mobile_resolve_worker()` |
| Agency ID from unvalidated claim | **Low** | Centralized `rateb_mobile_resolve_agency_id()` |

### Still open (launch blockers / risks)

| Finding | Severity | Action required |
|---------|----------|-----------------|
| **`MOBILE_AUTH_SECRET` not set on server** | **Critical** | Add to production `.env` before pilot; rotate if old default was ever used |
| Staff with `country_id = 0` | **Medium** | Returns **empty** worker/case lists (fail-safe); assign country to pilot users |
| No DB column for tenant on workers/agents | **Medium** | Deploy returns 503 `config_error` — verify schema has `workers.country_id` or `agents.tenant_id` |
| QR legacy `RATIBLOGIN:` bridge | **Low** | PIN badges rejected on mobile; legacy path still exists server-side |
| CORS `Access-Control-Allow-Origin: *` | **Low** | Acceptable for mobile API; review if cookie-based auth added later |
| Android release uses debug signing | **High** (Play Store) | Create upload keystore + `key.properties` before Play upload |

---

## 3. Android pilot checklist

### Configuration (done in repo)

- [x] App name: **RATEB** (`strings.xml` + manifest)
- [x] Permissions: `INTERNET`, `CAMERA` (QR)
- [x] Launcher icons: default Flutter mipmap placeholders (replace before public marketing)
- [x] Splash: `launch_background.xml` (default Flutter)
- [x] Release Gradle config + `key.properties.example`
- [x] API URL: `--dart-define=RATEB_API_BASE_URL=https://out.ratib.sa/api`

### Before Play Console upload

- [ ] Generate upload keystore:
  ```bash
  keytool -genkey -v -keystore rateb-upload.jks -keyalg RSA -keysize 2048 -validity 10000 -alias rateb
  ```
- [ ] Copy `android/key.properties.example` → `android/key.properties` (gitignored)
- [ ] Store keystore backup securely (loss = cannot update app)
- [ ] Build AAB:
  ```powershell
  cd rateb_mobile
  flutter build appbundle --dart-define=RATEB_API_BASE_URL=https://out.ratib.sa/api
  ```
- [ ] Output: `build/app/outputs/bundle/release/app-release.aab`

### Play Console

- [ ] Create app entry (package `com.ratib.rateb_mobile`)
- [ ] Upload AAB to **Internal testing** track first
- [ ] Complete Data safety form (camera, network, account data)
- [ ] Add privacy policy URL (required)
- [ ] Invite pilot testers via email list
- [ ] Test: install → login → all 3 role flows → QR → logout → reinstall (session)

### Release testing (manual)

- [ ] Password login / invalid password message
- [ ] QR login + expired/reused QR errors
- [ ] Tab navigation all roles
- [ ] Offline banner + cached data
- [ ] Session expiry → login with “Session expired”

---

## 4. Remaining launch blockers

| Priority | Blocker | Owner |
|----------|---------|-------|
| **P0** | Set `MOBILE_AUTH_SECRET` on production server | DevOps |
| **P0** | Deploy `api/mobile/bootstrap.php`, `tenant.inc.php`, scoped endpoints | Deploy |
| **P0** | Confirm DB has tenant column (`workers.country_id` or `agents.tenant_id`) | DBA |
| **P1** | Pilot users must have valid `country_id` for company data | Admin |
| **P1** | Android upload keystore + signed AAB for Play internal track | Mobile |
| **P2** | Replace default launcher icon with RATEB brand asset | Design |
| **P2** | Privacy policy page linked in Play Console | Legal/Ops |

---

## 5. Deploy files (must ship)

```
api/mobile/bootstrap.php
api/mobile/tenant.inc.php          ← NEW
api/mobile/auth.inc.php
api/mobile/company-workers.php
api/mobile/company-requests.php
api/mobile/agency-pipeline.php
api/mobile/agency-assignments.php
config/env/out_ratib_sa.php
```

Flutter: rebuild and distribute AAB/APK after pull.

---

## 6. Production secret setup

Add to server `.env` (project root, not committed):

```
MOBILE_AUTH_SECRET=<minimum-32-char-random-string>
```

Verify after deploy:

```bash
# Should still work (no secret needed)
curl https://out.ratib.sa/api/mobile/health.php

# Should return 503 config_error if secret missing (after bootstrap deploy)
curl -X POST https://out.ratib.sa/api/mobile/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test","password":"test"}'
```

After secret is set, login returns 401 for bad credentials (not 503).

---

*Pilot hardening pass complete. No new product features added.*
