# RATEB Mobile — Production Unblock Guide

**Production API:** `https://rateb.sa/api/mobile`  
**Current blocker:** `503 config_error` on JWT paths — `MOBILE_AUTH_SECRET` not available to PHP  
**Stack detected:** **nginx** in front (typical **cPanel** + PHP-FPM / LiteSpeed backend)

---

## Task 1 — Production secret validation (code audit)

### JWT signing / verification

| Path | File | Secret source |
|------|------|---------------|
| Issue JWT (login, QR success) | `api/mobile/bootstrap.php` → `rateb_mobile_issue_token()` | `rateb_mobile_token_secret()` |
| Verify JWT (all data routes) | `api/mobile/bootstrap.php` → `rateb_mobile_validate_token()` | same |
| Profile | `api/mobile/profile.php` | via `validate_token` |
| Data routes | `api/mobile/auth.inc.php` → `rateb_mobile_require_auth()` | via `validate_token` |

### QR signing / verification

| Path | File | Secret source |
|------|------|---------------|
| Generate QR | `api/mobile/qr.inc.php` → `rateb_mobile_qr_sign()` | `rateb_mobile_token_secret()` |
| Verify QR | `api/mobile/qr.inc.php` → `rateb_mobile_qr_verify_sig()` | same |
| QR login | `api/mobile/qr-login.php` | verify then `rateb_mobile_issue_token()` |

### Audit results — PASS

| Check | Status |
|-------|--------|
| Default production secret `rateb-mobile-change-me-in-production` | **Removed** |
| Dev-only fallback | `rateb-mobile-dev-only-not-for-production` — **only when NOT `*.rateb.sa`** |
| Production fail-closed | `rateb_mobile_config_error()` → **503**, logs CRITICAL, **never exposes secret** |
| Invalid password login | **401** without calling `issue_token` (misleading health signal) |
| Any Bearer / JWT operation on `rateb.sa` without secret | **503 `config_error`** (expected today) |

### Root cause (ops + code)

1. **`MOBILE_AUTH_SECRET` not set** on server `.env`, **and/or**
2. **Bridge loader gap (fixed in repo):** `config/env/load.php` did not allowlist `MOBILE_AUTH_SECRET` — `.env` lines were **silently ignored**.  
   **Deploy `config/env/load.php` after setting `.env`.**

Secret resolution order (after `includes/config.php` loads):

1. `getenv('MOBILE_AUTH_SECRET')` — from `.env` bridge or cPanel PHP env  
2. `define('MOBILE_AUTH_SECRET')` — from `config/env/rateb_sa.php`  
3. Dev fallback (localhost only)  
4. Production → **503**

---

### Exact `.env` line required

Add to **project root** `.env` on server (same directory as `includes/`, `api/`, `config/`):

```env
MOBILE_AUTH_SECRET=<your-secret-here>
```

**Generate secret (recommended):**

```bash
openssl rand -base64 48
```

Example (do **not** use this value — generate your own):

```env
MOBILE_AUTH_SECRET=K7xP2mN9vQ4wR8tY6uI0oL3sD5fG1hJ2kZ8xC7vB4nM9pQ6wE3rT0yU5iO2a
```

### Recommended secret length

| Property | Value |
|----------|--------|
| Minimum | **32 bytes** random (256-bit) |
| Recommended | **48 bytes** base64 (~64 chars) |
| Encoding | Single line, no quotes required (quotes stripped if matched) |
| Rotation | If old default secret was ever used in prod, rotate and invalidate all mobile sessions |

---

### Safe reload instructions (cPanel / nginx / PHP-FPM)

**After editing `.env` on server:**

1. **Save** `.env` outside web-readable path if possible (project root above `public_html` is typical on cPanel).
2. **Deploy** updated `config/env/load.php` (includes `MOBILE_AUTH_SECRET` in allowlist).
3. **Reload PHP** (pick what matches your host):

| Method | Steps |
|--------|--------|
| **cPanel → PHP-FPM** | MultiPHP Manager → no reload needed usually; use step 4 |
| **cPanel → LiteSpeed** | Restart LiteSpeed Web Server (WHM/cPanel) |
| **PHP-FPM pool** | `sudo systemctl reload php-fpm` (or `php82-php-fpm`) |
| **Apache mod_php** | `sudo systemctl reload httpd` |
| **No shell access** | cPanel → **Select PHP Version** → Save (forces pool refresh) OR wait 1–2 min |

4. **Verify** with curl below (503 → 401 on profile with bad token).

**Do not** commit `.env` to git.

---

### Verification curl commands

```bash
# 1) Health — always 200 (no secret)
curl -sS https://rateb.sa/api/mobile/health.php

# 2) BEFORE secret — expect 503 on JWT validate path
curl -sS -o /dev/null -w "%{http_code}\n" \
  -H "Authorization: Bearer aaa.bbb.ccc" \
  https://rateb.sa/api/mobile/profile.php
# Expected NOW: 503

# 3) AFTER secret — expect 401 (not 503)
curl -sS -w "\nHTTP %{http_code}\n" \
  -H "Authorization: Bearer aaa.bbb.ccc" \
  https://rateb.sa/api/mobile/profile.php
# Expected AFTER FIX: {"success":false,"message":"Unauthorized"} + HTTP 401

# 4) Invalid login — always 401 (does NOT prove secret works)
curl -sS -w "\nHTTP %{http_code}\n" -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"probe","password":"wrong"}' \
  https://rateb.sa/api/mobile/login.php

# 5) Valid login — proves secret + auth end-to-end
curl -sS -w "\nHTTP %{http_code}\n" -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"YOUR_USER","password":"YOUR_PASS"}' \
  https://rateb.sa/api/mobile/login.php
# Expected: HTTP 200, "success":true, "token":"eyJ..."

# 6) Authenticated profile
TOKEN="<paste token from step 5>"
curl -sS -w "\nHTTP %{http_code}\n" \
  -H "Authorization: Bearer $TOKEN" \
  https://rateb.sa/api/mobile/profile.php
# Expected: HTTP 200, "success":true, "data":{...}

# 7) Company workers (company role token)
curl -sS -w "\nHTTP %{http_code}\n" \
  -H "Authorization: Bearer $TOKEN" \
  https://rateb.sa/api/mobile/company-workers.php
# Expected: HTTP 200 JSON (may be empty roster)
```

---

## Task 2 — Server ENV detection

### Identified stack

| Component | Evidence |
|-----------|----------|
| **Edge** | `Server: nginx` on `rateb.sa` |
| **Hosting** | cPanel deploy scripts in repo (`scripts/github-cpanel-fileman-deploy-core.py`, `cpanel-deploy-sync.sh`) |
| **PHP** | PHP 8.x compatible; PHP-FPM or LiteSpeed common on cPanel |
| **Env loading** | `config/env/load.php` → `rateb_env_load_bridge_dotenv()` reads project-root `.env` |
| **Host profile** | `config/env/rateb_sa.php` for `rateb.sa` |

### Load order for mobile login

```
cors.php → bootstrap.php (functions) → includes/config.php
  → config/env/load.php (.env bridge)
  → config/env/rateb_sa.php (define MOBILE_AUTH_SECRET if getenv set)
→ Auth::login → rateb_mobile_issue_token() → rateb_mobile_token_secret()
```

### Fallback troubleshooting if env still missing

| Symptom | Cause | Fix |
|---------|-------|-----|
| Still **503** after `.env` edit | `load.php` not deployed with `MOBILE_AUTH_SECRET` allowlist | Deploy `config/env/load.php` |
| Still **503** | `.env` in wrong path | Place at project root (parent of `config/`), also check `public_html/.env` duplicate |
| Still **503** | Typo / spaces | Key must be exactly `MOBILE_AUTH_SECRET` |
| Still **503** | PHP opcache / FPM stale | Reload PHP-FPM / LiteSpeed (see above) |
| Still **503** | Empty value | `MOBILE_AUTH_SECRET=` with no value counts as missing |
| **503** only on some routes | Partial deploy | Redeploy all `api/mobile/*.php` + `config/env/` |

**cPanel alternative (no `.env` bridge):**

- **Software → MultiPHP INI Editor → Environment Variables** (if available), OR  
- WHM **PHP-FPM pool** `env[MOBILE_AUTH_SECRET]=...`  
- Then reload PHP — `getenv()` works without `.env` file

**Never** put the secret in git-tracked PHP files.

---

## Task 3 — Tenant data audit

Mobile isolation uses JWT `country_id` / agency `sub` — see `api/mobile/tenant.inc.php`.

Run on **`admin_out`** (or active tenant DB) in phpMyAdmin:

### Schema checks

```sql
-- Which tenant columns exist?
SELECT TABLE_NAME, COLUMN_NAME
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    (TABLE_NAME = 'workers' AND COLUMN_NAME IN ('country_id', 'agent_id'))
    OR (TABLE_NAME = 'users' AND COLUMN_NAME = 'country_id')
    OR (TABLE_NAME = 'agents' AND COLUMN_NAME IN ('tenant_id', 'country_id'))
    OR (TABLE_NAME = 'cases' AND COLUMN_NAME IN ('country_id', 'tenant_id'))
  )
ORDER BY TABLE_NAME, COLUMN_NAME;
```

### NULL / zero population

```sql
-- Users without tenant (pilot accounts will see EMPTY company data — not a leak)
SELECT COUNT(*) AS users_missing_country
FROM users
WHERE country_id IS NULL OR country_id = 0;

SELECT user_id, username, email, country_id
FROM users
WHERE country_id IS NULL OR country_id = 0
ORDER BY user_id
LIMIT 20;

-- Workers without country (if column exists)
SELECT COUNT(*) AS workers_missing_country
FROM workers
WHERE country_id IS NULL OR country_id = 0;

-- Agents without tenant (if tenant_id exists)
SELECT COUNT(*) AS agents_missing_tenant
FROM agents
WHERE tenant_id IS NULL OR tenant_id = 0;

-- Cases without country (if column exists)
SELECT COUNT(*) AS cases_missing_country
FROM cases
WHERE country_id IS NULL OR country_id = 0;
```

### Cross-tenant risk probe

```sql
-- Workers visible to two different country tenants (HIGH RISK if > 0)
SELECT w.id, w.worker_name, w.country_id, a.tenant_id, a.agent_name
FROM workers w
LEFT JOIN agents a ON a.id = w.agent_id
WHERE w.status != 'deleted'
  AND (
    (w.country_id IS NOT NULL AND w.country_id > 0 AND a.tenant_id IS NOT NULL AND a.tenant_id > 0 AND w.country_id != a.tenant_id)
  )
LIMIT 50;
```

### Dashboard empty vs leak

| Scenario | API behavior | Risk |
|----------|--------------|------|
| User `country_id = 0` | Empty lists (`1=0` scope) | **Low** — fail-safe |
| Workers exist but wrong tenant | Filtered out | **Low** — correct isolation |
| No `country_id`/`tenant_id` columns | **503 config_error** from mobile API | **Medium** — blocks pilot until schema fixed |
| Global unscoped query | **Fixed in code** — was pre-hardening issue | Deploy tenant.inc.php |

### Safe UPDATE examples (only if pilot users lack country)

**Preview first:**

```sql
SELECT user_id, username, country_id FROM users WHERE username IN ('pilot_company_1', 'admin');
```

**Assign sending country (example id = 2 — replace with real `recruitment_countries.id`):**

```sql
UPDATE users
SET country_id = 2
WHERE user_id IN (/* pilot user ids */)
  AND (country_id IS NULL OR country_id = 0);
```

**Do not bulk-update workers/agents without DBA review.**

---

## Task 4 — Live auth verification plan

### After `MOBILE_AUTH_SECRET` is configured

| Step | Command / action | Expected |
|------|------------------|----------|
| A | `curl profile.php` + bad Bearer | **401** Unauthorized, NOT 503 |
| B | POST login wrong password | **401** `invalid_credentials` |
| C | POST login valid user | **200** + `"token":"eyJ..."` + `"role"` |
| D | GET profile.php + token | **200** + user data |
| E | GET company-workers.php (company role) | **200** JSON roster |
| F | GET worker-dashboard.php (worker role) | **200** or **403** if wrong role |
| G | App login in browser | Dashboard loads, tabs work |
| H | Logout + refresh | Returns to login |
| I | QR invalid payload | **401** `invalid_format` / `invalid_signature` |
| J | QR valid (from qr-generate) | **200** + token (single-use) |

### Troubleshooting matrix

| HTTP | Code | Meaning | Action |
|------|------|---------|--------|
| 503 | `config_error` | Secret still missing | Fix `.env`, deploy `load.php`, reload PHP |
| 401 | `invalid_credentials` | Bad password | Expected for wrong login |
| 401 | `unauthorized` | Bad/expired JWT | Re-login |
| 403 | `forbidden` | Wrong portal role | Use correct role account |
| 200 | empty `workers:[]` | Tenant scope or empty DB | Run Task 3 SQL |
| 200 | data present | **GO** for that route | Continue pilot |

---

## Task 5 — Go / No-Go checklist

### P0 — before ANY pilot user

- [ ] Generate `MOBILE_AUTH_SECRET` (48+ bytes random)
- [ ] Add to production `.env` at project root
- [ ] Deploy `config/env/load.php` (MOBILE_AUTH_SECRET allowlist)
- [ ] Reload PHP-FPM / LiteSpeed
- [ ] `profile.php` + bad Bearer → **401** (not 503)
- [ ] Valid login returns JWT **200**
- [ ] Authenticated `company-workers.php` → **200**
- [ ] Assign `country_id` to pilot user accounts
- [ ] Confirm tenant columns exist (Task 3 SQL)

### P1 — before Android internal track

- [ ] Web pilot 5 users × 72 hours incident-free
- [ ] `flutter build appbundle` succeeds
- [ ] Upload keystore + `key.properties` configured
- [ ] Play Console internal testing track
- [ ] QR camera test on physical Android device
- [ ] Privacy policy URL in Play listing

### P2 — optional polish

- [ ] Brand launcher icon / splash
- [ ] Cross-tenant negative test (two country accounts)
- [ ] Rotate JWT secret schedule documented
- [ ] Monitoring alert on `503 config_error` rate

---

## Current status: **NO-GO**

**Single action to unblock:** set `MOBILE_AUTH_SECRET` + deploy `config/env/load.php` + reload PHP → verify **503 → 401** on profile probe.

---

*Operational guide — no app features changed except `MOBILE_AUTH_SECRET` env bridge allowlist.*
