# RATIB ERP — HR Phase T Production Readiness

**Commit:** `a248e1d0`  
**Deploy:** [success](https://github.com/ratibstar/ratib-pro/actions/runs/31868520686)  
**Scope:** Enterprise hardening of Phases B–S. No new business engines.

This document is the production-facing inventory for HR go-live. It does **not** enable live GOSI/WPS, rewrite payroll, or start Phase U.

---

## Migrations 247–257

Apply **in order**. All are additive. **No DROP.**

| # | File | Phase | Purpose |
|---|------|-------|---------|
| 247 | `247_hr_phase_b_ess_user_company_index.sql` | B | ESS `(user_id, company_id)` index |
| 248 | `248_hr_phase_g_approval_matrix.sql` | G | Approval matrices / stages / progress |
| 249 | `249_hr_phase_h2_leave_integrity.sql` | H2 | Leave integrity (additive) |
| 250 | `250_hr_phase_k_hirebridge_contracts.sql` | K | `rateb_hr_employment_contracts` |
| 251 | `251_hr_phase_l_letters.sql` | L | Letters |
| 252 | `252_hr_phase_m_decisions.sql` | M | `rateb_hr_decisions` |
| 253 | `253_hr_phase_o_succession.sql` | O | Critical positions / succession |
| 254 | `254_hr_phase_p_saudi_foundation.sql` | P | Saudi employment fields + local audit |
| 255 | `255_hr_phase_q_ops_automation.sql` | Q | Ops reminder ledger + settings |
| 256 | `256_hr_phase_r_saudi_compliance.sql` | R | GOSI period lines + WPS export tables (`external_sent` default 0) |
| 257 | `257_hr_phase_s_workforce_intelligence.sql` | S | Workforce plan targets |

**No migration 258.** Phase T added no schema.

Runtime inventory: `HrEnterpriseReadinessService::MIGRATION_INVENTORY` / `snapshot()`.

---

## Cron / jobs

Entry: `php rateb-erp/bin/erp-cron.php` → `CronService::runAll()`.

Recommended schedule: every **5–15 minutes**.

| Job key | Service | Notes |
|---------|---------|--------|
| `hr_employment_contract_status` | `HrEmploymentContractService::processExpiryStatus` | Status transitions only |
| `hr_employment_contract_alerts` | `HrEmploymentContractService::processExpiryAlerts` | Notifications |
| `hr_ops_automation` | `HrOpsAutomationService::runAll` | Milestone reminders; unique ledger claims |

Failed run recovery: next cron re-attempts **unclaimed** `period_key` rows only. Duplicate claims return false (no double notify / no domain approve or payroll post).

---

## Feature flags

| Flag | Default | Production rule |
|------|---------|-----------------|
| `HR_PAYROLL_ACCOUNTING_ENABLED` | **OFF** | Enable only after chart codes are mapped |
| `gosi_wps_external_send` | **OFF** (hardcoded) | Must remain OFF. No curl / bank / Mudad connector |

Payroll accounting optional codes (only if flag ON):

- `HR_PAYROLL_EXPENSE_ACCOUNT_CODE`
- `HR_PAYROLL_PAYABLE_ACCOUNT_CODE`
- `HR_PAYROLL_DEDUCTION_ACCOUNT_CODE`

---

## Required configuration

1. Session tenant resolution (`rateb_resolve_ops_company_id` / `TenantContext`) — never client `company_id`.
2. RBAC helpers (`rateb_can_view_entity` / `rateb_can_manage_entity`) so 360 salary is not deny-all.
3. Admin ERP URL only: `/public/admin/*`.
4. Cron as above.
5. Migrations 247–257 applied on the production schema.

---

## Saudi connectors state

| Control | Production value |
|---------|------------------|
| External send enabled | **false** |
| `external_sent` on new GOSI/WPS rows | **0** |
| HTTP / curl / GOSI_API / Mudad | **absent** |
| UI copy | `hr_saudi_no_external_send` |

Readiness % and exception counts are **local diagnostics**. They are not filings.

---

## Required indexes

| Index / unique | Source |
|----------------|--------|
| `idx_employees_user_company (user_id, company_id)` | 247 |
| `uq_hr_ops_reminder (company_id, reminder_type, entity_type, entity_id, period_key)` | 255 |
| GOSI period unique `(company, year, month, employee)` | 256 |
| Company-scoped keys on HR matrices / contracts / Saudi fields | 248, 250, 254 |

---

## Deployment prerequisites

1. Fast deploy of this commit (GitHub Actions Fileman) — `rateb-erp/` is in the managed tree.
2. Apply 247–257 if any table from `HrEnterpriseReadinessService::snapshot()` is missing.
3. Confirm `php bin/erp-cron.php` is scheduled.
4. Confirm payroll accounting flag is OFF unless charts are mapped.
5. Confirm GOSI/WPS send remains OFF (`external_send_enabled` false).
6. Do **not** ship a second ERP frontend (`public/v2`).

---

## Production blockers

Raised dynamically by `HrEnterpriseReadinessService::productionBlockers()`:

- Incomplete HR migrations 247–257 (missing files/tables)
- Any environment that would set GOSI/WPS `external_send_enabled` true (**forbidden**)

Operational blockers **not** auto-fixed:

- Orphan attendance/leave/payroll/contract/salary rows (read-only CC widget; **no auto-merge/delete**)
- FK constraints deferred until orphan counts are zero
- Employee FK / manager hierarchy still not introduced (Phase P soft-link only)

---

## Hotspots (performance)

Bounded only — no N+1 Employee 360 loops:

- Command Center (`LIST_LIMIT` / `SEARCH_LIMIT` + compact integrity COUNTs)
- Employee 360 (`loadTab` lazy)
- Approval Inbox (`listPending` cap 50–500)
- Workforce analytics / intelligence (`REPORT_LIMIT` / `LIST_LIMIT`)
- Saudi compliance (company profiles LIMIT; 360 uses single-employee `LIMIT 1`)
- ESS APIs (resolver company + user; never client employee_id)

---

## Explicitly out of scope

- Phase U
- Live GOSI / WPS transmission
- Payroll or accounting engine rewrite
- Approval Engine rewrite
- Flutter rewrite
- Manager FK
- New business features
