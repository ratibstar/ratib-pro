# RATIB ERP — HR Phase R Saudi Compliance Certification

**Status:** COMPLETE  
**Date:** 2026-08-15  
**Base:** Phase Q Operations Automation (`00d73f93`)  
**Commit:** `50ad4cf4`  
**Deploy:** [success](https://github.com/ratibstar/ratib-pro/actions/runs/31847528991)

---

## Objective

Prepare RATIB for Saudi HR compliance (GOSI / WPS) with local eligibility, contribution model, validation, readiness exports, and Command Center visibility — **without any external transmission**.

---

## Delivered

| Area | Implementation |
|------|----------------|
| Migration | `256_hr_phase_r_saudi_compliance.sql` — extend `rateb_hr_saudi_employment_fields`; add `rateb_hr_gosi_period_lines`, `rateb_hr_wps_export_batches`, `rateb_hr_wps_export_lines` (`external_sent` default 0) |
| Service | `HrSaudiComplianceService` — profiles, IBAN mod-97, GOSI rates/base, WPS batch build, reports, readiness % |
| Foundation | Reuses Phase P `HrSaudiComplianceFoundationService` + audit channel |
| Admin UI | `/admin/hr/saudi-compliance` + reports/export |
| Command Center | Saudi readiness %, missing data, GOSI/WPS exceptions |
| Payroll SoT | Reads `rateb_payroll_periods` / `rateb_payroll_lines` only — no calc rewrite |
| Contracts | Phase K `rateb_hr_employment_contracts` for dates/salary mismatch |
| Employee SoT | `rateb_employees` |

**Explicit non-goals met:** no GOSI send, no WPS/Mudad send, no payroll redesign, no Approval Engine, no Flutter, no manager FK, no Phase S.

---

## External policy

- `external_sent = 0` on all new rows  
- `external_send_enabled = false` in readiness summary  
- No curl / HTTP connector / bank API in Phase R services or controllers  

---

## Tests

| Suite | Result |
|-------|--------|
| `run-hr-phase-r-tests.php` | **CLEAR** |
| Phase B–Q regressions | **CLEAR** |

---

## Remaining risks

- Migration `256` must be applied before GOSI/WPS build actions work.  
- GOSI rates are a readiness model (9.75%/11.75% Saudi; 2% non-Saudi employer) — not a live filing connector.  
- WPS builds require an existing payroll period with lines.  
- Contribution base uses employee salary + optional housing/transport/other fields when present.

**Exit:** Saudi HR readiness foundation is local-only and visible on Command Center. **Met.**
