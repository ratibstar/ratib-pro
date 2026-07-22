# RATEB ERP v1.0.1 — Release Notes

**Release date:** 2026-06-27  
**Type:** Maintenance release (first patch after GA v1.0.0)  
**Branch:** `release/v1.0.1`  
**Production baseline:** v1.0.0 @ `e64c37b3`

---

## Summary

RATEB ERP v1.0.1 is the first maintenance release after General Availability. It addresses operational observations from GA closeout: backup verification false negatives on MariaDB 10.11 dumps, company portal logout redirect UX, build/version metadata, repository security hygiene, and prepared (inactive) CI workflow drafts.

**No new ERP features. No database schema changes. No migrations.**

---

## Resolved in v1.0.1

| ID | Area | Fix |
|----|------|-----|
| L-02 | Backup | Robust SQL dump verification (256KB scan, MariaDB/MySQL headers) |
| L-01 | Portal UX | Logout redirects to ERP login (`/rateb-erp/public/login`) |
| L-03 | Build | Version `1.0.1`, build marker `rateb-erp-v1.0.1-maintenance-20260627` |
| SEC-M01 | Security | `config/test-control-db.php` — CLI-only, env-based credentials |

---

## Not changed

- ERP business logic (except logout redirect target)
- Database schema and migrations
- GA certification documents (`rateb-erp/docs/GA/*` canonical files)
- Production data

---

## Upgrade path

1. Merge `release/v1.0.1` → `main` (triggers deploy — **after approval only**)
2. Confirm build marker on server
3. Verify portal logout and backup `--verify` on next cron backup

See `MIGRATION-NOTES-v1.0.1.md` and `SECURITY-CHANGES-v1.0.1.md`.

---

## Rollback

Redeploy v1.0.0 tag/commit `e64c37b3` or restore from certified backup `erp-admin_rateb-erp-20260627-024200.sql.gz`.

---

*Maintenance release — documentation only until merge approved.*
