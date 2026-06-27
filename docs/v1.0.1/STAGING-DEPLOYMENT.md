# RATIB ERP v1.0.1 — Staging Deployment

**Date:** 2026-06-27  
**Environment:** `dev.rateb.sa`  
**Database:** `admin_rateb_dev` (only — production `admin_rateb-erp` not used)  
**Branch deployed:** `release/v1.0.1` @ `1db3e427`  
**Production:** **UNCHANGED** (`rateb.sa`)

---

## Summary

v1.0.1 maintenance code was deployed to the **staging subdomain** `dev.rateb.sa` with ERP database **`admin_rateb_dev`**. Production was not modified, merged, or deployed.

---

## Deployment Steps Executed

| Step | Action | Result |
|------|--------|--------|
| 1 | Branch verified | `release/v1.0.1` |
| 2 | Bootstrap `dev.rateb.sa` from production file tree (read-only copy) | ✅ |
| 3 | Staging `.env` — `RATEB_ERP_DB_NAME=admin_rateb_dev` | ✅ |
| 4 | Staging credentials — `RATEB_ERP_DB_USER=admin_rateb_dev` | ✅ |
| 5 | Host profile — `config/env/dev_rateb_sa.php` | ✅ |
| 6 | Deploy v1.0.1 runtime files (SCP) | ✅ |
| 7 | Cache clear (`rateb-erp/storage/cache/`) | ✅ |
| 8 | Migrations | **Skipped** — none in v1.0.1 |
| 9 | Verification script | ✅ PASS |

---

## Files Deployed (v1.0.1)

| File | Change |
|------|--------|
| `rateb-erp/app/services/DeploymentReadinessService.php` | Backup verifier fix |
| `rateb-erp/app/controllers/Marketing/CustomerPortalController.php` | Portal logout redirect |
| `rateb-erp/config/app.php` | Version 1.0.1 |
| `rateb-erp/public/ratib-erp-build.txt` | Build marker |
| `config/test-control-db.php` | CLI-only diagnostic |
| `config/env/dev_rateb_sa.php` | **Staging host profile (new on server)** |

---

## Staging Configuration

| Setting | Value |
|---------|-------|
| Site URL | `https://dev.rateb.sa` |
| ERP DB | `admin_rateb_dev` |
| ERP DB user | `admin_rateb_dev` |
| `RATEB_ENV` | `staging` |
| Production DB | **Not connected** |

**Server paths:**

- Web root: `/home/admin/domains/dev.rateb.sa/public_html`
- ERP: `/home/admin/domains/dev.rateb.sa/public_html/rateb-erp`

---

## Build Marker

```
rateb-erp-v1.0.1-maintenance-20260627
```

`RATEB_APP_VERSION=1.0.1`

---

## Backup Verification (Staging)

| Item | Value |
|------|-------|
| Backup file | `erp-admin_rateb_dev-20260627-151837.sql.gz` |
| Size | 68,069 bytes |
| `erp-restore.php --verify` | **PASS** |
| Verifier | v1.0.1 (256KB MariaDB-safe scan) |

---

## Migrations

**None required.** v1.0.1 is code-only. Staging DB already at migration `135_phase6_interbranch_execution.sql`.

---

## Production Safety

| Check | Status |
|-------|--------|
| `rateb.sa` code modified | ❌ NO |
| `admin_rateb-erp` used | ❌ NO |
| Merge to `main` | ❌ NO |
| Push to `main` | ❌ NO |
| Production deploy | ❌ NO |

Bootstrap copied files **from** production **to** dev (read-only on source). Production runtime unchanged.

---

## Rollback (Staging Only)

Redeploy previous build marker on dev or re-run bootstrap from production v1.0.0 tree. No DB rollback required.

---

*Staging deployment — v1.0.1 maintenance release.*
