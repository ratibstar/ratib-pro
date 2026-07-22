# Documentation Archive Plan — v1.0.1

**Status:** PLAN ONLY — do not execute until approved.  
**Goal:** Reduce operator confusion without modifying final GA closeout documents.

---

## Keep untouched (canonical GA)

These files in `rateb-erp/docs/GA/` **must not move or edit**:

- `FINAL-GA-CERTIFICATE.md`
- `FINAL-RISK-REGISTER.md`
- `PRODUCTION-HANDOVER.md`
- `CHANGELOG-v1.0.md`
- `FINAL-SIGNOFF.md`
- `go-live-final-report.md`
- `go-live-backup-restore-evidence-20260627.json`

---

## Target: `docs/archive/ga/`

| Source | Reason |
|--------|--------|
| `rateb-erp/docs/GA/enterprise-ga-final-certification.md` | Superseded — says GA BLOCKED |
| `rateb-erp/docs/GA/ga-certification.md` | Superseded |
| `rateb-erp/docs/GA/RATEB-ERP-v1.0-FINAL-GO-LIVE-CERTIFICATION-REPORT.md` | Partial run superseded |
| `rateb-erp/docs/GA/go-live-operational-cert-20260627-023758.json` | Blocked backup probe |
| `rateb-erp/docs/GA/enterprise-validation-report.md` | Duplicate |
| `rateb-erp/docs/GA/enterprise-validation-final.md` | Duplicate |
| `rateb-erp/docs/GA/dr-validation.md` | Duplicate |
| `rateb-erp/docs/GA/dr-final.md` | Duplicate |
| `rateb-erp/docs/GA/performance-report.md` | Duplicate |
| `rateb-erp/docs/GA/performance-final.md` | Duplicate |
| `rateb-erp/docs/GA/accounting-validation.md` | Stale BLOCKED |
| `rateb-erp/docs/GA/accounting-final.md` | Stale BLOCKED |

---

## Target: `docs/archive/qa/`

| Source | Reason |
|--------|--------|
| `scripts/qa-manifest/sessions/SAFE-QA-20260627011943.json` | Iteration session |
| `scripts/qa-manifest/sessions/SAFE-QA-20260627-020834.json` | Iteration session |
| `scripts/qa-manifest/sessions/SAFE-QA-20260627-021015.json` | Iteration session |
| `scripts/qa-manifest/sessions/SAFE-QA-20260627-021102.json` | Iteration session |
| `scripts/qa-manifest/sessions/SAFE-QA-PROBE-ROLE-33.json` | Probe only |

**Keep in place:** `QA-CERT-20260627023047.json`, `SAFE-QA-20260627-023048`, `SAFE-QA-20260627-023740`, `regression-output.json` (latest evidence).

---

## Target: `docs/archive/` (root noise)

| Source | Reason |
|--------|--------|
| `HR_ULTRA_DEEP_AUDIT_REPORT.md` | Pre-GA audit |
| Root-level `*_AUDIT*.md` / `*_REPORT*.md` | Historical |

---

## Target: `scripts/archive/go-live-20260627/`

| Source | Reason |
|--------|--------|
| `scripts/qa-go-live-*.sh` | One-time ops probes |
| `scripts/qa-go-live-operational-cert.ps1` | One-time cert runner |

---

## Execution checklist (when approved)

1. Create directories: `docs/archive/ga/`, `docs/archive/qa/`, `scripts/archive/go-live-20260627/`
2. `git mv` files per tables above
3. Add README in each archive folder explaining supersession date
4. Do **not** change canonical GA files
5. Commit on `release/v1.0.1` only

---

*Archive plan — Phase 3 v1.0.1. No files moved in this phase.*
