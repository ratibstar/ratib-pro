# RATEB ERP v1.0.1 — Staging Certification

**Date:** 2026-06-27  
**Certification type:** Staging / UAT readiness  
**Environment:** `dev.rateb.sa`  
**Database:** `admin_rateb_dev`  
**Release:** v1.0.1 maintenance (`release/v1.0.1`)

---

## Certification Statement

RATEB ERP **v1.0.1 maintenance** is **certified for staging user acceptance** on `dev.rateb.sa` with database **`admin_rateb_dev`**.

Production (`rateb.sa` / `admin_rateb-erp`) was **not modified** by this deployment.

---

## Certification Criteria

| Criterion | Required | Actual | Status |
|-----------|----------|--------|--------|
| Deploy to dev only | Yes | Yes | ✅ |
| DB = admin_rateb_dev only | Yes | Yes | ✅ |
| No production DB access | Yes | Confirmed | ✅ |
| v1.0.1 build marker | Yes | Yes | ✅ |
| Health endpoint OK | Yes | Yes | ✅ |
| ERP login reachable | Yes | Yes | ✅ |
| Portal logout → ERP login | Yes | Yes | ✅ |
| Backup verify PASS | Yes | Yes | ✅ |
| No new migrations | Yes | None run | ✅ |
| Core modules smoke OK | Yes | PASS (1 route WARN) | ✅ |

---

## Release Scope Validated on Staging

| Change | Validated |
|--------|-----------|
| MariaDB backup verifier (L-02) | ✅ PASS on staging backup |
| Portal logout redirect (L-01) | ✅ 302 → `/rateb-erp/public/login` |
| Version 1.0.1 / build marker (L-03) | ✅ |
| Security: test-control-db hardening | ✅ Deployed |
| ERP modules regression | ✅ No regressions observed |

---

## Risk Assessment (Staging)

| Severity | Count |
|----------|-------|
| Critical | 0 |
| High | 0 |
| Medium | 0 |
| Low | 2 (branch route URL, partial smoke coverage) |

**Deployment risk:** **LOW**

---

## Production Readiness (Staging Perspective)

| Question | Answer |
|----------|--------|
| Ready for UAT on dev? | **YES** |
| Ready for production merge? | **Pending operator approval** (separate from staging) |
| Blocking issues? | **None** |

---

## Recommended Next Steps

1. **User acceptance testing** on `https://dev.rateb.sa`
2. Operator approval for merge `release/v1.0.1` → `main` (when ready)
3. Production deploy only after explicit merge approval
4. Post-production: verify build marker, logout, backup `--verify` on production

---

## Sign-Off

| Role | Status |
|------|--------|
| Staging deployment | ✅ Complete |
| Staging certification | ✅ **APPROVED FOR UAT** |
| Production go-live | ⏸ Await operator approval |

---

*Staging certification — v1.0.1 maintenance release.*
