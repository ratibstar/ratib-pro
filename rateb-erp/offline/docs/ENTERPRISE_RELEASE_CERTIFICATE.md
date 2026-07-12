# Enterprise Release Certificate — RATEB ERP Offline

**Certificate ID:** `RATEB-ERP-OFFLINE-CERT-2026-07-12`  
**Release version:** `erp-offline-v1.0.0`  
**Issued:** 2026-07-12  
**Issuer:** Production release closure audit (repository + live rateb.sa)

---

## Statement

This certifies that **RATEB ERP Offline** release **`erp-offline-v1.0.0`** has completed production validation on **https://rateb.sa** with evidence-based PASS results for the full certification checklist (steps 1–18), and that the production tree for critical offline artifacts has been verified **byte-identical** to repository commit **`2c6b0c3275b5a675210d595cabcef987714419a5`** (plus this closure documentation commit).

---

## Production readiness

| Criterion | Status |
|-----------|--------|
| Production readiness | **READY** for enterprise maintenance and incremental upgrades |
| Functional change in closure | **None** (documentation, tag, sync of line-ending drift only) |
| Validation status | **PASS — 18/18** |
| Service Worker controlling | `pos-sw.js` |
| Offline identity + PIN after logout | Verified (`keep_vault`) |
| Offline draft → IndexedDB → MySQL replay | Verified (PR + lines, inventory movement, attendance) |

---

## Validation evidence location

| Location | Contents |
|----------|----------|
| `C:\Users\Public\pw-rateb-validate\evidence2\report.json` | Full step report + allowlist stats |
| `C:\Users\Public\pw-rateb-validate\evidence2\14e-mysql.json` | MySQL PR / movement / attendance proof |
| `C:\Users\Public\pw-rateb-validate\evidence2\09b-offline-*.png` | Offline same-UI screenshots |
| `C:\Users\Public\pw-rateb-validate\evidence2\17e-pin.json` / `.png` | PIN unlock after logout |
| `rateb-erp/offline/docs/RELEASE_NOTES.md` | Features, fixes, limitations |
| `rateb-erp/offline/docs/DEPLOYMENT_MANIFEST.md` | Versions, migrations, hashes |

---

## Rollback reference

| Item | Value |
|------|-------|
| Safe rollback commit | `96ad925b187244ff986b777aa587644147d91f88` |
| Subject | `deploy-20260712-072115` |
| Procedure | Redeploy `rateb-erp` tree from that commit (or `git checkout` + fast deploy of offline/public paths); bump SW caches if clients retain `v14` assets; users may need hard refresh / SW update |

---

## Sign-off

| Field | Value |
|-------|-------|
| Release version | `erp-offline-v1.0.0` |
| Git tag | `erp-offline-v1.0.0` |
| Production readiness | **READY** |
| Validation status | **PASS** |
| Evidence location | `C:\Users\Public\pw-rateb-validate\evidence2\` |
| Rollback reference | `96ad925b187244ff986b777aa587644147d91f88` |

**Certificate status: ISSUED**
