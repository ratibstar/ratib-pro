# RATIB ERP v1.0.1 — Phase 3 Maintenance Report

**Date:** 2026-06-27  
**Branch:** `release/v1.0.1`  
**Baseline:** v1.0.0 @ `e64c37b3274040ebc480865c01c247324f288cfb`  
**Status:** ✅ **PHASE 3 COMPLETE — AWAITING APPROVAL**

---

## Executive Summary

Phase 3 prepared RATIB ERP **v1.0.1** as the first maintenance release after GA. All nine work items were completed on the development branch only. No merge, push, deploy, migration, or production database access was performed.

| Task | Status |
|------|--------|
| 1 — Backup verifier fix | ✅ Done |
| 2 — Portal logout UX | ✅ Done |
| 3 — Build version increment | ✅ Done |
| 4 — Archive plan (no moves) | ✅ Plan only |
| 5 — Security hardening (MEDIUM) | ✅ Done + verified |
| 6 — Git hygiene (.gitignore) | ✅ Done |
| 7 — CI workflow drafts (inactive) | ✅ Done |
| 8 — Release documentation | ✅ Done |
| 9 — Local verification | ✅ Done |

**Recommendation:** Approve merge of `release/v1.0.1` → `main` when ready; run post-deploy logout and backup `--verify` checks. Do **not** proceed to Phase 4 until explicit approval.

---

## Files Changed

### Modified (6)

| File | Change |
|------|--------|
| `rateb-erp/app/services/DeploymentReadinessService.php` | 256KB gzip scan; MariaDB/MySQL dump detection |
| `rateb-erp/app/controllers/Marketing/CustomerPortalController.php` | Logout redirect → `rateb_url('login')` |
| `rateb-erp/config/app.php` | `RATEB_APP_VERSION` 1.0.1; build string updated |
| `rateb-erp/public/ratib-erp-build.txt` | `rateb-erp-v1.0.1-maintenance-20260627` |
| `config/test-control-db.php` | Env-based creds; CLI-only (HTTP 403) |
| `.gitignore` | `__pycache__`, QA temps, backup artifacts |

**Diff summary:** 6 files, +107 / −30 lines (uncommitted).

### Added (new paths)

| Path | Purpose |
|------|---------|
| `docs/v1.0.1/RELEASE-NOTES-v1.0.1.md` | Release notes |
| `docs/v1.0.1/CHANGELOG-v1.0.1.md` | Changelog |
| `docs/v1.0.1/MIGRATION-NOTES-v1.0.1.md` | Upgrade/rollback (no schema) |
| `docs/v1.0.1/SECURITY-CHANGES-v1.0.1.md` | Security delta |
| `docs/v1.0.1/KNOWN-ISSUES-v1.0.1.md` | Open/closed issues |
| `docs/v1.0.1/PHASE3-MAINTENANCE-REPORT.md` | This report |
| `docs/archive/ARCHIVE-PLAN.md` | Planned doc archival |
| `.github/workflow-drafts/*.yml` | Inactive CI drafts (`if: false`) |

### Untouched (per constraints)

- `rateb-erp/docs/GA/*` — canonical GA closeout frozen
- Production database and migrations
- `main` branch and remote

---

## Security Improvements

| ID | Finding | Resolution |
|----|---------|------------|
| **SEC-M01** | Hardcoded password in `config/test-control-db.php` | Removed; uses `CONTROL_DB_*` / `DB_*` env vars; CLI-only |
| **SEC-M02** | Live passwords in tracked country env | **Verified:** `config/env/*.php` use `getenv()` with empty fallback — no literals |

**Portal logout (L-01):** Only redirect target changed. `Auth::logout()` still revokes remember-me, logs audit event, and destroys session.

**Production auth unchanged:** Login, CSRF on POST login, middleware gates — no modifications.

**Remaining LOW (documented, not in v1.0.1 scope):** SEC-L01–L05 (archive markdown, recovery script defaults).

---

## Repository Improvements

- **`docs/archive/ARCHIVE-PLAN.md`** — Lists obsolete GA/QA docs to move later; canonical GA files explicitly excluded.
- **No files moved** — execution deferred until approval.
- **Release docs** — New `docs/v1.0.1/` set; GA docs not modified.

---

## Git Improvements

`.gitignore` additions:

- `__pycache__/`, `*.pyc`
- Temporary QA outputs and manifests
- Generated report patterns
- `rateb-erp/storage/backups/*.sql.gz` (with `.gitkeep` exception)

**Note:** Previously tracked `scripts/__pycache__/*.pyc` remain in git history/index until a separate cleanup commit is approved.

---

## CI Improvements

Draft workflows in `.github/workflow-drafts/` ( **not** in `.github/workflows/` ):

| Draft | Purpose |
|-------|---------|
| `pr-validation.yml` | PHP lint + secret scan placeholder |
| `backup-verify.yml` | Post-backup `--verify` hook |
| `rollback-checklist.yml` | Manual rollback checklist job |
| `tag-validation.yml` | Tag format / semver check |

All jobs use `if: false` — **zero CI impact** until copied and enabled.

---

## Backup Verifier Results

**Issue:** MariaDB 10.11 dump preamble exceeded 512-byte `gzread` window → false `not_sql_dump`.

**Fix:** Scan up to **262,144 bytes** decompressed via `readGzipSample()`; `isSqlDumpSample()` checks:

- MariaDB / MySQL dump headers (`MariaDB dump`, `MySQL dump`, `-- Host:`)
- `CREATE TABLE` / `CREATE DATABASE`
- `INSERT INTO` (optional when header + CREATE present)
- `DROP TABLE` (schema-only edge case)

### Local test results

| Sample | Result |
|--------|--------|
| MariaDB 10.11-style (40-line preamble before CREATE) | ✅ PASS |
| MySQL 8.0 dump header + CREATE + INSERT | ✅ PASS |
| Non-SQL gzip content | ✅ Rejected |
| Backward compatibility (valid dumps with standard headers) | ✅ Maintained |

---

## Logout Verification

| Check | Result |
|-------|--------|
| Redirect target | `rateb_url('login')` → `/rateb-erp/public/login` on production |
| Session destruction | ✅ via `SessionManager::destroy()` in `Auth::logout()` |
| Remember-me cleanup | ✅ `RememberMeService::revokeAllForUser()` |
| Audit logging | ✅ `logout` event |
| Auth logic changes | None beyond redirect |

**Routing:** `rateb_url()` delegates to `rateb_public_url()` — consistent with existing login flows across middleware and controllers.

---

## Task 9 — Local Validation

| Check | Result |
|-------|--------|
| PHP syntax (changed `.php` files) | ✅ No errors |
| Backup verifier (MariaDB/MySQL samples) | ✅ PASS |
| Logout redirect code review | ✅ PASS |
| Git branch | ✅ `release/v1.0.1` |
| Migrations run | ❌ None (by design) |
| Production endpoints / DB writes | ❌ None (by design) |
| GA docs modified | ❌ None |

---

## Risk Matrix

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Backup verifier regression on old dumps | Low | Medium | 256KB scan; header + CREATE required |
| Logout redirect breaks deep links | Low | Low | Standard ERP login URL used elsewhere |
| test-control-db CLI break for ops | Low | Low | Document env vars in file header |
| Accidental draft workflow activation | Low | Medium | Drafts outside `workflows/`; `if: false` |
| Untracked `.pyc` noise persists | Medium | Low | `.gitignore` + future cleanup commit |

**Overall regression impact:** **LOW** — maintenance-only, no schema or feature changes.

---

## Regression Impact

| Area | Impact |
|------|--------|
| ERP modules / accounting / inventory | None |
| Database schema | None |
| API / mobile auth | None |
| Deploy pipeline | None until merge to `main` |
| GA certification evidence | None (docs frozen) |

---

## Remaining Issues

See `docs/v1.0.1/KNOWN-ISSUES-v1.0.1.md`. Summary:

- Archive execution pending approval (`L-ARCH-01`, `L-ARCH-02`)
- LOW recovery-script default passwords (SEC-L02–L05)
- CI-01 auto-migrations on `main` deploy (process, not v1.0.1 code)
- CI drafts inactive until approved

---

## Recommendation

1. **Review** uncommitted changes on `release/v1.0.1`.
2. **Commit** when approved (not done in Phase 3 per stop rules).
3. **Merge to `main`** only with explicit go-ahead — triggers production deploy.
4. **Post-deploy:** Confirm build marker, portal logout, next backup `--verify`.
5. **Optional follow-up:** Execute `ARCHIVE-PLAN.md`; remove tracked `__pycache__`; enable CI drafts.

---

## STOP — Phase 3 Complete

- ❌ Do NOT merge into `main`
- ❌ Do NOT deploy
- ❌ Do NOT push
- ❌ Do NOT run migrations
- ❌ Do NOT begin Phase 4

**Await approval before continuing.**

---

*Phase 3 — Maintenance Release v1.0.1 — Development branch only.*
