# RATIB ERP — HR Phase G0 Approval Matrix Architecture Audit

**Status:** COMPLETE (evidence only — no implementation)  
**Date:** 2026-08-14  
**Base commit:** `17839695` (Phase F PASS)  
**Rule:** Discover from code. Do not invent modules. Do not implement until this audit is accepted.

---

## 0. Scope lock (from Phase G charter)

| In scope | Out of scope |
|----------|--------------|
| Configurable approval **stages** for Leave / Requests / Permission | ApprovalEngine2 / new workflow product |
| `ApprovalOversightService` as **execution authority** | Payroll workflow rewrite |
| Matrix config with **safe fallback** when unset | Accounting / GL changes |
| Tenant isolation + RBAC preserved | Decisions / Expenses queues (absent — Phase F deferred) |
| Multi-stage **only where architecture can support safely** | Destructive migrations / history wipe |
| Exit: leave + certificate/request use matrix | Unblocking company `POST …/approve` |

**Execution order (charter):** Audit → findings → plan → implementation → tests → regression → certification.  
**This document = Audit + findings + recommended plan only.**

---

## 1. Current approval stack (discovered)

Three distinct approval-related layers exist today. They are **not** interchangeable.

| Layer | Tables / service | Multi-stage? | Wired to HR leave / permission / request? | Role today |
|-------|------------------|--------------|---------------------------------------------|------------|
| **A. Oversight (canonical HR decide)** | Domain tables + `ApprovalOversightService` | **No** — single shot `pending→approved\|rejected` | **Yes** (`hr_leave`, `hr_permission`, `hr_request`) | Platform SA decide; Phase F inbox reads this |
| **B. Legacy Workflow** | `rateb_approval_workflows` / `_steps` / `_instances` / `_actions` + `WorkflowService` | **Yes** (step counter) | Entity types **registered**; submit/sync **not** wired for HR domain creates | Used for PR/PO (+ generic instances); HR sync incomplete |
| **C. EAP (Phase 20A)** | `rateb_eap_*` + `ApprovalWorkflowService` / `ApprovalDomainServices` | **Yes** (stages/chains) | **No** link from leave/permission/employee_request rows | Separate company `/approvals/*` product |

**Architecture lock for Phase G:** Do **not** make EAP or a fourth engine the HR execution path. Keep **A** as the decide path. Matrix must **configure** A, not replace it.

```text
Create leave / permission / request (domain SoT)
        ↓ status = pending (unchanged)
ApprovalOversightService::listPending / process   ← execution authority
        ↓ (today) one approve → HrService / setHrStatus / setEmployeeRequestStatus
Phase F: HrApprovalInboxService (read-only aggregate)
```

---

## 2. Module map (HR in-scope)

| Module | Domain table | Pending status | Oversight source | Final approve path today | Audit | Notification | Company approve route |
|--------|--------------|----------------|------------------|--------------------------|-------|--------------|------------------------|
| **Leave** | `rateb_leave_requests` | `pending` | `hr_leave` | `HrService::approveLeave` / `rejectLeave` | Oversight `AuditService` on decide; leave methods update row | ESS: `notifyPendingSubmission('hr_leave')` | **Blocked** `$blockCompanyApprovalAction` |
| **Permission** | `rateb_hr_permission_requests` | `pending` | `hr_permission` | `ApprovalOversightService::setHrStatus` | Same | ESS create: **no** notify found | **Blocked** |
| **Employee request** (incl. certificate types) | `rateb_hr_employee_requests` | `pending` | `hr_request` | `setEmployeeRequestStatus` | Same | Company/ESS create: **no** systematic notify | **Blocked** |
| **Payroll** | `rateb_payroll_periods` | `draft` | `hr_payroll` | `approvePayroll` | Phase B/D | — | **Blocked** (out of Phase G matrix) |
| **Decisions** | — | — | — | — | — | — | Deferred (absent) |
| **HR Expenses** | — | — | — | — | — | — | Deferred (absent) |

### 2.1 Domain status enums (SoT — do not replace)

- Leave / permission / request: `pending | approved | rejected | cancelled`
- **No** `current_stage` / matrix columns on these tables today
- Payroll remains `draft → approved → posted` (Phase G must not touch)

### 2.2 Request types (certificate exit criterion)

Lookup `employee_request_types` (`FormLookupService`):

- `salary_certificate`
- `end_of_service`
- `experience_letter`
- `other`

ESS Phase C submit currently restricts to `inquiry` / `complaint` only — certificate types are **company CRUD** (and future ESS extension), still stored in `rateb_hr_employee_requests.request_type`.

Roadmap also mentions careful extension (`transfer`, `resignation`, …) — **not required for G exit**; treat as later additive types if matrix keys are generic.

---

## 3. Oversight execution details (Layer A)

**File:** `rateb-erp/app/services/ApprovalOversightService.php`

| Concern | Behavior |
|---------|----------|
| Pending discovery | `status_column` + `status_value` per source (`pending` / payroll `draft`) |
| Company isolation | `company_id` filter; non–super-admin detail/process scoped |
| Decide entry | `process($sourceKey, $recordId, $companyId, $action)` |
| Idempotency | UPDATE … `WHERE status = pending` (or leave service checks); second approve fails |
| Reject | Allowed for leave/permission/request; **disabled** for `hr_payroll` |
| Undo | Resets leave/permission/request to `pending`; payroll undo disabled |
| RBAC (platform UI) | Admin oversight controllers; SA bulk decide gated |
| Company UI | Phase F inbox **view-only**; company approve routes blocked |

**Gap vs multi-stage:** One successful `approve` always finalizes the domain row. There is **no** intermediate stage state in oversight or in HR tables.

---

## 4. Legacy Workflow (Layer B) — multi-stage exists, HR incomplete

**Files:** `WorkflowService.php`, `WorkflowSubmissionService.php`

| Capability | Status for HR |
|------------|---------------|
| Entity types include `hr_leave`, `hr_permission_request`, `hr_employee_request`, `hr_payroll` | Registered in `entityTypeOptions()` |
| Auto-submit on create | **No** — `WorkflowSubmissionService` only hooks purchase_request / purchase_order status handlers |
| Multi-step advance | Yes on `rateb_approval_instances.current_step` |
| Sync domain status on final approve/reject | **`syncEntityStatus` only handles purchase_request / purchase_order** — HR rows would stay `pending` even if instance became `approved` |
| Company workflow approve routes | **Blocked** (same `$blockCompanyApprovalAction`) |
| Oversight | `workflow_instance` source → `approveAsOversight` / `rejectAsOversight` |

**Risk if Phase G naively “just submit HR into WorkflowService”:**

1. Dual pending: domain `pending` **and** workflow instance pending (double inbox noise).  
2. Final workflow approve would **not** update leave/permission/request without new sync code.  
3. Would blur “Oversight as HR execution authority” into two decide surfaces for the same business object.

**Verdict:** Layer B is **not** a safe drop-in matrix for HR without substantial wiring and SoT clarification. Prefer **not** to revive it as the Phase G primary path unless a later ADR explicitly chooses it.

---

## 5. EAP (Layer C) — parallel product, not HR SoT

**Migration:** `186_approval_platform_enterprise.sql`  
**Docs:** `rateb-erp/docs/PHASE_20A_APPROVAL_ONLINE.md`

- Templates / stages / rules / chains / `rateb_eap_requests` with `related_module` / `related_type` / `related_id`
- Own workflow: `draft → submitted → pending → approved|rejected` via `ApprovalWorkflowService`
- Explicitly **does not replace** `ApprovalOversightService` / legacy workflows
- No discovered code path that creates EAP requests from `rateb_leave_requests` / permission / employee_request creates

**Verdict for Phase G:** Reusing EAP as a **full second engine** for HR would violate “no ApprovalEngine2 / no second workflow product” and split SoT. Optional later: read-only reference of stage naming patterns — **not** migrate HR decide into EAP in Phase G.

---

## 6. Company controllers vs routes (security note)

`HrLeavesController` / permission / employee request controllers still contain `approve` / `reject` methods that mutate status if invoked.

Routes in `routes/modules/ops.php` map those POSTs to `$blockCompanyApprovalAction` (redirect/block).

Phase G must **keep routes blocked**. Matrix must not re-enable company-side bypass of oversight.

---

## 7. Phase F interaction

| Asset | Implication for G |
|-------|-------------------|
| `HrApprovalInboxService` | Aggregates `hr_leave` / `hr_permission` / `hr_request` / `hr_payroll` while domain status is pending/draft |
| Inbox allowed_action | `view_only_in_company_inbox` |
| If intermediate stages keep domain `pending` | Items correctly remain in inbox until **final** approve |
| If intermediate statuses invent new domain enums | Risk of falling out of oversight `status_value=pending` filter — **avoid** unless oversight sources updated carefully |

---

## 8. Findings summary

| ID | Finding | Severity | Phase G impact |
|----|---------|----------|----------------|
| G-F1 | HR approve is **single-shot** via Oversight → domain service | Info | Matrix must wrap decide, not replace domain SoT |
| G-F2 | No matrix / stage columns on leave / permission / request tables | Gap | Need **additive** config (+ optional progress) tables |
| G-F3 | Legacy Workflow multi-stage is **incomplete for HR** (no submit, no sync) | High if reused blindly | Do not use as silent default |
| G-F4 | EAP is a parallel approval product | High if reused as HR decide | Out of Phase G execution path |
| G-F5 | Certificate types exist on `request_type`; ESS does not create them today | Medium | Matrix key = `hr_request` + `request_type`; company create is enough for exit |
| G-F6 | Permission / request create often lack `notifyPendingSubmission` | Low–Med | Optional harden in G or follow-up; not exit blocker |
| G-F7 | Company approve methods exist but routes blocked | Security | Keep blocked |
| G-F8 | Payroll / Accounting / Decisions / Expenses | Out of scope | Do not extend matrix to payroll in G |
| G-F9 | Fallback when no matrix | Required | Current single-shot Oversight behavior must remain default |

---

## 9. Safe multi-stage support assessment

| Approach | Safe for Phase G? | Notes |
|----------|-------------------|-------|
| **G-Safe:** Additive HR matrix config + stage progress; Oversight `process` advances stages; **final** stage calls existing `approveLeave` / `setHrStatus` / `setEmployeeRequestStatus`; domain stays `pending` until final | **Yes** | Matches charter; preserves Phase F inbox; fallback = no config → today’s path |
| Wire HR into Legacy WorkflowService + fix sync + suppress dual pending | Risky / large | Possible later ADR; not recommended as first G path |
| Route HR through EAP requests | **No** for G | Second product / SoT split |
| New ApprovalEngine2 | **Forbidden** | Charter |
| Unlock company approve for stage actors | **No** unless explicit security ADR | Conflicts with Phase B/F governance |

**Conclusion:** Multi-stage is supportable **only** as an **optional overlay** on Oversight decide for leave / permission / request, with domain status remaining the SoT for “still pending.”

---

## 10. Recommended implementation plan (not executed)

### 10.1 Additive schema (illustrative — names final in implementation ADR)

Additive only (`CREATE TABLE IF NOT EXISTS`), company-scoped:

1. **Matrix definition** — e.g. `rateb_hr_approval_matrices`  
   - `company_id`, `source_key` (`hr_leave` \| `hr_permission` \| `hr_request`), optional `request_type` (NULL = all / leave N/A), `is_active`, soft metadata  
2. **Stages** — e.g. `rateb_hr_approval_matrix_stages`  
   - `matrix_id`, `sort_order`, `code`, `name`, optional `approver_role` / notes  
3. **Progress** — e.g. `rateb_hr_approval_stage_progress`  
   - `company_id`, `source_key`, `record_id`, `matrix_id`, `current_stage_order`, `status` (`in_progress` \| `completed` \| `rejected`), audit fields  

No ALTER that changes meaning of existing `status` enums. No destructive backfill required: absent progress ⇒ fallback single-shot.

### 10.2 Service (new, thin — not a second engine)

Suggested: `HrApprovalMatrixService` (name TBD)

Responsibilities:

- Resolve matrix for `(company_id, source_key, request_type?)`  
- If **none** → return “passthrough” (current Oversight behavior)  
- If present → on Oversight approve: record stage action; if not last stage, **do not** call domain final approve; if last stage, call existing finalizers  
- On reject at any stage → existing reject paths  
- Enforce `company_id` on all reads/writes  
- Idempotent stage advances (unique progress per record)

**Must not:** own pending discovery; replace `ApprovalOversightService`; touch payroll/accounting.

### 10.3 Hook point

Prefer a **single** interception inside `ApprovalOversightService::processOnce` for `hr_leave` / `hr_permission` / `hr_request` **before** final domain mutate — or a dedicated helper called from those three branches only.

Do **not** fork decide into company controllers.

### 10.4 Exit criterion mapping

| Exit item | Plan |
|-----------|------|
| Leave uses matrix config | Seed/config path for `source_key=hr_leave`; tests with 2+ stages |
| Certificate/request uses matrix | Matrix row for `hr_request` + `request_type=salary_certificate` (and/or experience_letter); final approve still `setEmployeeRequestStatus` |
| Fallback | No matrix / inactive → identical to pre-G single-shot |
| Tenant + RBAC | Progress and matrix CRUD scoped by company; decide remains oversight authority |
| Permission | Include in matrix map (charter) even if exit text emphasizes leave + certificate |

### 10.5 Explicit non-goals in implementation

- No payroll matrix  
- No Decisions/Expenses invention  
- No EAP migration of HR rows  
- No company approve unlock  
- No rewrite of `HrService::approveLeave` business rules beyond being called on **final** stage  
- No Phase H leave-depth features  

### 10.6 Tests (when implementation starts)

- No matrix → leave approve still finalizes in one Oversight approve  
- Matrix 2 stages → first approve keeps `pending` + advances progress; second finalizes  
- Reject at stage 1 → rejected; no finalize  
- Wrong `company_id` cannot advance  
- Certificate type matrix does not apply to unrelated `request_type`  
- Phase B–F + ESS leave regressions remain CLEAR  
- Company approve routes still blocked  

### 10.7 Docs after implementation (not now)

- `HR-PHASE-G-APPROVAL-MATRIX-CERTIFICATION.md`  
- Update roadmap Phase G → COMPLETE  
- Do **not** mark Phase H complete  

---

## 11. Risks & open decisions (for acceptance before code)

| # | Decision needed | Recommendation |
|---|-----------------|----------------|
| D1 | Where do stage actors approve? | Keep **platform Oversight** as only decide surface in G (company inbox stays read-only). Role-based stage actor UI = later phase if needed. |
| D2 | Who configures matrices? | Company HR admin CRUD under Admin ops **or** SA-only seed in G1 — pick smallest surface that meets exit tests. |
| D3 | Intermediate display | Keep domain `pending`; expose stage label via progress join in inbox/oversight detail (optional UX). |
| D4 | Permission in exit | Include in matrix capability; certify leave + `salary_certificate` as mandatory exit. |
| D5 | Legacy Workflow / EAP | Document as **non-execution** for HR in G; no dual-write. |

---

## 12. Audit gate

```text
[x] Leave / permission / request SoT and statuses mapped
[x] ApprovalOversightService confirmed as HR decide authority
[x] Legacy Workflow multi-stage assessed (unsafe as silent default)
[x] EAP assessed (parallel product — not HR SoT for G)
[x] Phase F inbox compatibility assessed
[x] Certificate request_type discovered
[x] Payroll / Accounting / Decisions / Expenses excluded
[x] Safe multi-stage approach identified (overlay + fallback)
[x] Implementation plan documented — NOT executed
[ ] Implementation (blocked until audit acceptance)
```

**STOP.** Await acceptance of findings / plan before any code, migration, or certification docs.
