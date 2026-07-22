# RATEB ERP v1.0.1 — Security Changes

**Version:** 1.0.1  
**Date:** 2026-06-27

---

## Summary

v1.0.1 resolves **Phase 2 MEDIUM** finding SEC-M01 and hardens diagnostic tooling. No changes to production authentication logic except portal logout redirect (UX only — session still destroyed securely).

---

## Resolved

### SEC-M01 — Hardcoded credentials in test utility

| Before | After |
|--------|-------|
| `config/test-control-db.php` contained plaintext `$db_pass` | Credentials from `CONTROL_DB_*` / `DB_*` env vars only |
| Web-accessible diagnostic | **CLI-only** — HTTP returns 403 |

**Production ERP auth:** Unchanged. `Auth::logout()` still revokes remember-me tokens and destroys session.

---

## Portal logout (L-01)

| Item | Behavior |
|------|----------|
| Session | Destroyed via `SessionManager::destroy()` |
| Remember-me | Revoked via `RememberMeService::revokeAllForUser()` |
| Audit | `logout` event logged |
| CSRF | Unchanged — logout is GET route, no state mutation before redirect |
| Redirect | `rateb_url('login')` → `/rateb-erp/public/login` on production |

---

## Unchanged security posture

- Health probe token gate
- CSP / HSTS / API rate limiting
- Migrate token gitignored
- No secrets in deploy workflow YAML

---

## Remaining (not in v1.0.1 code scope)

| ID | Item | Plan |
|----|------|------|
| SEC-L01 | Passwords in archive markdown | Redact in archive pass |
| SEC-L02–L05 | Recovery script default passwords | Review in v1.0.2 housekeeping |
| — | Secret scan in CI | Activate `workflow-drafts/pr-validation.yml` when approved |

---

## Risk assessment

| Severity | Count after v1.0.1 |
|----------|-------------------|
| Critical | 0 |
| High | 0 |
| Medium | 0 (SEC-M01 resolved) |
| Low | 5 (documented in KNOWN-ISSUES) |

---

*Security changes — maintenance release v1.0.1.*
