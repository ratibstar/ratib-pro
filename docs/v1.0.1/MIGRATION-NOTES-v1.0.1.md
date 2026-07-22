# RATEB ERP v1.0.1 — Migration Notes

**Version:** 1.0.1 (maintenance)  
**Schema migrations required:** **None**

---

## Overview

v1.0.1 is a **code-only maintenance release**. No SQL migrations, no data migration scripts, and no production database changes are part of this release.

---

## Pre-deploy checklist

| Step | Action |
|------|--------|
| 1 | Confirm certified backup exists (`erp-admin_rateb-erp-20260627-024200.sql.gz`) |
| 2 | Merge `release/v1.0.1` → `main` only with explicit approval |
| 3 | GitHub Actions deploy runs automatically on `main` push |
| 4 | Post-deploy: verify `ratib-erp-build.txt` shows `rateb-erp-v1.0.1-maintenance-20260627` |
| 5 | Post-deploy: test company portal logout → ERP login page |
| 6 | Post-deploy: next backup run — `php bin/erp-restore.php --verify` should PASS |

---

## Configuration changes

| Setting | v1.0.0 | v1.0.1 |
|---------|--------|--------|
| `RATEB_APP_VERSION` | 1.0.0 | 1.0.1 |
| Build marker file | `rateb-erp-ga-security-20260626` | `rateb-erp-v1.0.1-maintenance-20260627` |

No `.env` changes required for standard deploy.

---

## Operator notes

- **`config/test-control-db.php`:** Now CLI-only. If previously accessed via HTTP for diagnostics, use SSH + env vars instead.
- **Archive plan:** Document moves are planned in `docs/archive/ARCHIVE-PLAN.md` — not executed in v1.0.1 code drop.

---

## Rollback

1. Redeploy commit/tag `v1.0.0` (`e64c37b3`)
2. Or restore database from GA-certified backup if needed
3. Re-run health + enterprise cert probes

No down-migration scripts exist — rollback is redeploy-only.

---

*No migrations in v1.0.1.*
