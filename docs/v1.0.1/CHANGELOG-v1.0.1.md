# RATIB ERP v1.0.1 — Changelog

All notable changes for the v1.0.1 maintenance release.

---

## [1.0.1] — 2026-06-27

### Fixed

- **Backup verification:** `DeploymentReadinessService::verifyBackupFile()` scans up to 256KB decompressed content; detects MariaDB/MySQL dump headers, `CREATE TABLE`, and `INSERT INTO` — fixes false `not_sql_dump` on MariaDB 10.11 preamble.
- **Portal logout:** `CustomerPortalController::logout()` redirects to `rateb_url('login')` instead of marketing home; session destruction and remember-me revocation unchanged via `Auth::logout()`.

### Security

- **`config/test-control-db.php`:** Removed hardcoded database password; CLI-only; requires `CONTROL_DB_*` or `DB_*` environment variables.

### Changed

- **`RATEB_APP_VERSION`:** `1.0.0` → `1.0.1`
- **`RATEB_ASSET_BUILD`:** `20260627-v1.0.1-maintenance`
- **`rateb-erp/public/ratib-erp-build.txt`:** `rateb-erp-v1.0.1-maintenance-20260627`

### Repository / Ops (no runtime impact until merge)

- `.gitignore` — `__pycache__`, QA temp outputs, backup artifacts
- `docs/archive/ARCHIVE-PLAN.md` — planned doc/script archival
- `.github/workflow-drafts/` — inactive PR/backup/rollback/tag workflows
- Phase 1–3 documentation under `docs/v1.0.1/`

### Unchanged

- GA documents in `rateb-erp/docs/GA/` (canonical closeout)
- Database migrations
- ERP feature modules

---

## [1.0.0] — 2026-06-27

General Availability — see `rateb-erp/docs/GA/CHANGELOG-v1.0.md`.

---

*Changelog format follows Keep a Changelog principles for v1.0.1 scope.*
