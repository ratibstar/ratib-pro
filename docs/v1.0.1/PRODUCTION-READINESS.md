# RATEB ERP v1.0.1 — Production Readiness

**Release:** v1.0.1 (maintenance)  
**Branch:** `release/v1.0.1` → `main`  
**Date:** 2026-06-27  
**Baseline:** v1.0.0 @ `e64c37b3274040ebc480865c01c247324f288cfb`

---

## Readiness Verdict

| Field | Value |
|-------|-------|
| **Production ready** | ✅ YES — after merge approval |
| **Release type** | Maintenance patch |
| **Schema changes** | None |
| **Downtime expected** | None |
| **Deployment risk** | LOW |

---

## What Ships in v1.0.1

| Change | Production impact |
|--------|-------------------|
| MariaDB backup verifier fix | Ops accuracy — `--verify` no longer false-negative on long preamble |
| Portal logout redirect | Users land on ERP login after company portal logout |
| Build marker / version | Ops verification; asset cache bust |
| `test-control-db.php` hardening | HTTP 403; no hardcoded password |
| `.gitignore` patterns | Dev/repo hygiene only |
| Release documentation | No runtime impact |

---

## What Does NOT Ship

| Item | Status |
|------|--------|
| New ERP features | ❌ None |
| Database migrations | ❌ None |
| Billing / HR / CRM / Inventory changes | ❌ None |
| API changes | ❌ None |
| CI workflow activation | ❌ None |
| `.github/workflow-drafts/` | ❌ Not in branch |

---

## Pre-Merge State

| Item | Status |
|------|--------|
| GA certification (v1.0.0) | ✅ Valid baseline |
| Certified backup | `erp-admin_rateb-erp-20260627-024200.sql.gz` |
| Production DB | Unchanged until merge + deploy |
| `origin/main` | Still v1.0.0 @ `e64c37b3` |

---

## Post-Merge Deploy Expectations

| Step | Expected outcome |
|------|------------------|
| GitHub Actions `deploy.yml` runs | Automatic on `main` push |
| Fast-deploy uploads changed ERP files | Service, controller, config, build marker |
| Migrations step | No new migrations in this release |
| Downtime | None |
| Duration | ~3–8 minutes (existing pipeline) |

---

## Post-Deploy Verification

| # | Check | Expected |
|---|-------|----------|
| 1 | Build marker | `rateb-erp-v1.0.1-maintenance-20260627` |
| 2 | Portal logout | `/site/portal/logout` → ERP login |
| 3 | Backup verify | `php bin/erp-restore.php --verify` PASS on MariaDB dump |
| 4 | ERP health | Existing health probe PASS |
| 5 | Enterprise cert | Re-run if operator requires formal sign-off |

---

## Rollback Plan

| Scenario | Action | DB rollback |
|----------|--------|-------------|
| Post-deploy issue | Redeploy v1.0.0 @ `e64c37b3` | Not required |
| Data corruption (unlikely) | Restore certified backup | Only if needed |

**Rollback time:** ~3–8 minutes (redeploy)

---

## Risk Summary

| Category | Level |
|----------|-------|
| Critical | 0 |
| High | 0 |
| Medium | 0 |
| Low | 3 (deploy-on-merge, cache bust, logout UX) |
| **Overall** | **LOW** |

---

## Backward Compatibility

| Check | Result |
|-------|--------|
| v1.0.0 users upgrade safely | ✅ YES |
| Breaking changes | **0** |
| New required env vars (ERP) | **0** |
| API contract changes | **0** |

---

## Operator Decision

| Question | Answer |
|----------|--------|
| Ready for merge? | **YES** — pending explicit approval |
| Ready for production after merge? | **YES** |
| Blockers? | **None** |

---

## Recommendation

**READY FOR MERGE** — `release/v1.0.1` → `main`

Merge will trigger production deploy. Confirm operator approval before executing merge.

**STOP** — Await operator approval.

---

*Production readiness — v1.0.1 maintenance release.*
