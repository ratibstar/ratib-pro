# RATIB ERP — HR Product & UX Gap Audit

**Status:** COMPLETE (evidence only — no implementation)  
**Date:** 2026-08-14  
**Scope:** Read-only product/UX audit of live Admin HR (`/admin/hr/*`), Recruitment, parallel HRM/Payroll surfaces, ESS API, and Flutter mobile (`ratib_hr_mobile`).  
**Rule:** Discover what end users can actually do. Do **not** change code, DB, or other docs in this phase.

**Governance context (already shipped):** Phases B–H security/governance PASS; Phase H2 leave integrity PASS (`docs/hr/HR-PHASE-H2-LEAVE-CERTIFICATION.md`).  
**Product thesis:** Backend hardening outpaced visible HR product evolution. This audit separates **capability that exists** from **capability users can use**.

---

## 1. Executive Summary

RATIB’s production HR product that company users see is the **`hr/*` sidebar** (`config/hr-menu.php`). It is a **functional CRUD ops suite** for employees, attendance, leaves, loans, payroll periods, documents metadata, requests, fleet, and a **read-only** approvals inbox.

What users **do not** get (despite enterprise expectations and some parallel backends):

1. A **360° employee master** (profile is a flat card; YTD attendance computed but not shown).
2. A **unified employee timeline**.
3. **Employment contracts** (sidebar “Contracts” is supplier/commercial, not HR employment).
4. **Letter/PDF generation** (request types exist; no templates/print engine).
5. **Company/manager decide path** (inbox view-only; approve routes blocked → platform oversight only).
6. **Hire → employee** bridge from Recruitment.
7. **Org chart / reporting lines** in the live employee model.
8. **Disciplinary / decisions / succession / expenses** Admin UIs.
9. **Enterprise reports & analytics** beyond two monthly tables.
10. ESS/mobile **parity** (no certificate requests, no PDF payslips, no manager approvals, no leave-type catalog API).

**Overall product maturity (weighted):** ~**2.3 / 5** — basic-to-functional ops HR, not yet an enterprise people product.

**Highest leverage next work:** user-visible Employee Master 360 + manager/company actionable approvals + recruitment hire bridge + letter PDFs — not another governance-only phase.

---

## 2. Current HR Architecture (what users actually touch)

| Surface | URL / entry | Audience | Product role |
|---------|-------------|----------|--------------|
| **Ops HR (canonical product)** | `/admin/hr/*` via sidebar | Company HR | Day-to-day HR |
| **Approval Inbox** | `/admin/hr/approvals-inbox` | Company (read-only) | See pending; cannot decide |
| **Platform Oversight** | `/admin/oversight/approvals` | Super admin | Actual approve/reject |
| **Recruitment** | `/admin/recruitment/*` | HR/recruiters | Candidate pipeline (no hire bridge) |
| **Payroll platform** | `/admin/payroll/*` | Separate sidebar | Parallel thin stack vs `hr/payroll` |
| **HRMS Phase 23A** | `/admin/hrm/*` | **No sidebar** | Thin CRUD stubs; dual employee profiles |
| **ESS API** | `/api/v1/hr/*` | Linked employees | Mobile backend |
| **Flutter app** | `ratib_hr_mobile` | Employees | ESS client |
| **`public/v2` HR BM** | Archive / migration only | — | **Not production ERP UI** (architecture lock) |

**Canonical employee SoT for product + ESS:** `rateb_employees` (`id` + `company_id`).  
**Parallel:** `rateb_hrm_employee_profiles` (weak `legacy_employee_id` link; not in HR sidebar).

### Navigation users see (`hr-menu.php`)

Overview · Pending actions · Employees · Departments · Job titles · Holidays · Attendance (workplaces, permissions, daily, bulk, “monthly”→reports) · Leaves (requests, types, balances, report) · Loans · Payroll (periods, components, structure) · Documents · Employee requests · Fleet.

**Not in HR nav:** Letters module, Decisions, Violations, Efficiency, Succession, Employment contracts, Org chart, HR Settings, HRM performance/training, ESS web portal.

---

## 3. User Journey Results

### 3.1 Employee journey

| # | Step | Status | Evidence / gap |
|---|------|--------|----------------|
| 1 | Employee is created | **EXISTING** | Admin `hr/employees` CRUD |
| 2 | Profile completed | **PARTIAL** | Basic identity fields; no photo, dependents, bank, manager |
| 3 | Contract exists | **MISSING** | No employment contract module |
| 4 | Salary exists | **EXISTING** | `salary_base` + payroll structure components |
| 5 | Employee sees dashboard | **PARTIAL** | ESS/Flutter home; Admin has no employee self-service portal |
| 6 | Requests leave | **EXISTING** | ESS apply + Admin create; H2 overlap/balance guards |
| 7 | Sees balance | **EXISTING** | ESS balances + Admin balances page |
| 8 | Tracks approval | **PARTIAL** | Status on request; **no stage / next approver** UX |
| 9 | Receives approval/rejection | **PARTIAL** | Leave outcome notification; matrix intermediate stages must not false-notify (H2) |
| 10 | Downloads letters | **MISSING** | No PDF letter engine; ESS coerces certificate types → inquiry |
| 11 | Sees attendance | **PARTIAL** | ESS history (default ~30d); no monthly “explain my month” UX |
| 12 | Sees payslip | **PARTIAL** | ESS JSON + `.txt` file; Admin print HTML; **not PDF** |

### 3.2 HR Admin journey

| # | Step | Status | Evidence / gap |
|---|------|--------|----------------|
| 1–2 | Create/edit employee | **EXISTING** | Form + list search `?q=` |
| 3–4 | Assign department / position | **EXISTING** | `department_id` + `job_title` string/CRUD titles |
| 5 | Assign manager | **MISSING** | No manager on `rateb_employees`; HRM field not on form |
| 6 | Create contract | **MISSING** | Employment; supplier contracts are unrelated |
| 7 | Set salary | **EXISTING** | `salary_base` + structure |
| 8 | Manage leave types | **EXISTING** | `hr/leave-types` |
| 9 | Manage balances | **PARTIAL** | Read-only balances table; no manual adjust UI |
| 10 | Review attendance | **PARTIAL** | Daily CRUD + bulk; weak filters/calendar |
| 11 | Process violations | **MISSING** | Schema `rateb_hrm_disciplinary_actions` — **no Admin UI** |
| 12–14 | Generate / review / approve-post payroll | **PARTIAL** | Generate + show + payslip print; company **cannot** approve (oversight) |
| 15 | Generate reports | **PARTIAL** | Two reports only (monthly attendance/payroll + leave report) |

### 3.3 Manager / Oversight journey

| # | Step | Status | Evidence / gap |
|---|------|--------|----------------|
| 1 | See pending actions | **PARTIAL** | Company inbox = **all company** pending, not “my team” |
| 2–4 | Open / understand / employee info | **PARTIAL** | View link to record; no rich request detail + employee 360 |
| 5 | Approve/reject | **MISSING** (company/manager) | Routes blocked; Flutter Approvals = placeholder |
| 6–8 | Stage / previous decisions / next approver | **PARTIAL** | Matrix exists backend; **UI does not explain stages** to company users |
| 9 | Receive notification | **PARTIAL** | Oversight pending notify; **not** line-manager notify |

**Who can decide HR today:** platform super admin on Oversight. Company HR and managers mostly **watch and wait**.

---

## 4. Employee Master Gaps

**Page:** `views/company/hr/employees/show.php` via `HrEmployeesController::show` → `HrService::employeeProfile`.

| Concern | Status |
|---------|--------|
| Identity (code, email, phone, hire, status) | **Existing** |
| Employment / department name | **Partial** (dept on form/list; show omits clear dept label) |
| Position / job title | **Existing** |
| Contract | **Missing** |
| Salary | **Existing** (`salary_base` always shown — permission UX gap) |
| Attendance | **Missing in UI** (`attendance_ytd` returned by service, **not rendered**) |
| Leave balance / recent leaves | **Existing** |
| Leave history (full) | **Partial** (recent only) |
| Requests / letters / violations / payroll / documents / audit | **Missing** on profile |
| Tabs / timeline | **Missing** |

**Verdict:** One page **cannot** answer “Who is this employee?” beyond identity + leave snapshot. **P1 product gap.**

---

## 5. Leave Gaps (UX only — H2 logic not in scope)

| Capability | Admin | ESS/Mobile |
|------------|-------|------------|
| Available / used / entitled | Balances page | Balances API |
| Pending / approved / rejected history | List statuses | List/show |
| Paid / unpaid visible | Type flag in types CRUD; weak on request UX | Weak |
| Approval stage / approver | **Missing** | **Missing** |
| Calendar / upcoming leave | **Missing** | **Missing** |
| Leave types catalog for apply | Admin CRUD | **Missing API** (`leave_type_id` required without list) |
| Cancel | Admin + ESS (H2) | Existing |

**Calendar-day semantics** intentionally preserved (H2). Working-day/holiday calc deferred — document as product expectation gap, not a bug.

---

## 6. Attendance Gaps

| Capability | Status |
|------------|--------|
| Daily attendance CRUD | **Existing** |
| Bulk entry | **Existing** |
| Monthly “report” | **Partial** (`hr/reports` — summary tables, not timesheet calendar) |
| Late / early / OT / exemptions / sanctions | **Missing** as first-class UX |
| Work periods / shifts | **Missing** (workplaces = geo radius CRUD only) |
| Proof / selfie / GPS punch | **Missing** on ESS punch |
| Employee “what happened this month?” | **Missing** |

---

## 7. Payroll Gaps

| Capability | Admin `hr/payroll` | ESS |
|------------|--------------------|-----|
| Period draft → approved → posted | **Existing** (decide via oversight) | N/A |
| Basic / allowances / deductions / net | **Existing** on lines + print | Partial DTO |
| Loans in generate | **Existing** (batch) | Notes may include |
| Absence deduction | **Existing** | Opaque in ESS |
| Unpaid leave deduction (H2) | **Existing** in generate notes | Opaque |
| Posted ≠ GL ≠ bank | **Documented** (Phase D) | Must stay clear in UX |
| Accounting status | Flag OFF; not a payroll UI claim | — |
| PDF payslip | **Missing** (HTML print / `.txt`) | `.txt` only |
| Employee Admin self-service | **Missing** | API only |

---

## 8. Contracts Gaps

| Type | Status |
|------|--------|
| Supplier / commercial `contracts` | Unrelated to HR employment |
| Recruitment candidate contracts | Exists on candidate; **does not create employee** |
| Employment contract lifecycle (create, expiry, renewal, salary, position, docs, alerts) | **Missing** |

**Shallow / absent enterprise capabilities:** probation, EOS, renewals calendar, expiry notifications, GOSI linkage, employee contract register.

---

## 9. Recruitment Gaps

| Step | Status |
|------|--------|
| Vacancy / job requisition | **Missing** (candidates without formal openings) |
| Candidate + pipeline | **Existing** |
| Interview / medical / visa / documents | **Existing** (child records) |
| Offer / contract on candidate | **Partial** |
| Hiring → `rateb_employees` | **MISSING — hard break** |

**HireBridge** remains the critical break between Recruitment and Employee Master.

---

## 10. Organization Gaps

| Capability | Status |
|------------|--------|
| Departments CRUD | **Existing** (`hr/departments`) |
| Job titles CRUD | **Existing** |
| Positions / grades UI | **Partial** (HRM tables/UI thin, not in HR nav) |
| Managers / reporting lines on live employee | **Missing** |
| Visual org chart | **Missing** |
| Searchable org connected to employees | **Partial** (dept filter weak) |

**Do not invent manager approval hierarchy** — but product still needs a **reporting-line field** for UX and “my team” views without becoming an auth authority.

---

## 11. Letters Gaps

| Type | Request enum | Generate PDF | Download | Audit |
|------|--------------|--------------|----------|-------|
| Salary certificate | Admin yes / ESS no | **Missing** | **Missing** | Request row only |
| Experience letter | Admin yes / ESS no | **Missing** | **Missing** | Request row only |
| End of service | Admin yes | **Missing** | **Missing** | Request row only |
| Employment certificate | Via types/other | **Missing** | **Missing** | — |

**Verdict:** Letters are **request tickets**, not an HR letters product.

---

## 12. Reports Gaps

### Existing

1. `hr/reports` — monthly attendance + payroll summary (+ CSV).
2. `hr/reports/leaves` — leave report (+ export).
3. HRM reports — workflow board counts / timeline only (not in HR sidebar).

### Missing enterprise reports

Headcount · Turnover · Attendance exceptions · Absence analysis · Leave utilization/calendar · Payroll/salary analysis · Contracts expiry · Violations · Recruitment funnel · Department analysis · Manager team reports · ESS-facing summaries.

---

## 13. ESS / Mobile Gaps

| Area | Status |
|------|--------|
| Dashboard / profile / attendance / leave / notifications | **Existing–Partial** |
| Requests (inquiry/complaint) | **Existing** |
| Letters / certificates | **Missing** |
| Payslip PDF | **Missing** |
| Document upload | **Missing** |
| Leave types list API | **Missing** |
| Manager approvals | **Missing** (placeholder) |
| GPS / workplace punch | **Missing** |
| Web ESS portal in Admin | **Missing** |
| Gap vs Web HR | Certificates, decide path, rich profile, reports |

---

## 14. Navigation Gaps

| Issue | Detail |
|-------|--------|
| Dual payroll UIs | `hr/payroll` vs `payroll/*` — confusing |
| Dual employee masters | `hr/employees` vs `hrm/employees` (hidden) |
| “Contracts” label | Commercial, not employment |
| “Monthly attendance” menu | Points to general reports |
| Orphan `hrm/*` | Routes exist, **no HR sidebar** |
| Missing nav for real needs | Letters, contracts (employment), settings, violations, org chart |
| Dead links inside `hr-menu.php` | **None found** (routes/views present) |

---

## 15. Notification Gaps

| Event | Today |
|-------|-------|
| Leave submit → oversight | **Existing** |
| Leave final approve/reject/cancel → employee | **Existing** (H2) |
| Matrix intermediate stage | Must not false-final (architecture) — **UX still opaque** |
| Permission / request / letter / payroll published | **Weak / missing** employee notify |
| Contract expiry / attendance exception | **Missing** |
| Line manager notify | **Missing** |

No second notification engine required — coverage and targeting gaps only.

---

## 16. Performance Gaps (observe only)

| Area | Risk |
|------|------|
| Leave balances / employee lists | Generic CRUD; large tenants may lack strong pagination UX |
| Dashboard | Light aggregates — OK for now |
| Approval inbox | Aggregates multiple sources — watch growth |
| Reports | Full-month scans — acceptable at small scale |
| `employeeProfile` | Computes `attendance_ytd` then **discards in view** (wasted work) |
| ESS | Generally bounded; leave-types missing causes client workarounds |

No optimization in this audit.

---

## 17. Security UI Gaps (not redoing Phases B–H)

| Issue | Severity |
|-------|----------|
| Company users see **all** pending HR items, not team-scoped | P2 (over-exposure / noise) |
| Employee show always displays **salary_base** if page reachable | P2 (permission UX) |
| ESS unbound employee → 404 (correct) | OK |
| Tenant isolation on ESS/Admin | Phases B–C — assume held |
| Documents download authorization | Rely on existing services — spot-check in future UX phase |
| Manager cannot decide (by design today) | Product gap, not bypass |

---

## 18. Product Maturity Score

| Module | Score (0–5) | Rationale |
|--------|-------------|-----------|
| Employee Master | **2** | CRUD + thin show |
| Contracts (employment) | **0** | Missing |
| Recruitment | **3** | Pipeline OK; hire break |
| Organization | **2** | Depts/titles only |
| Attendance | **2** | CRUD/bulk; no engine UX |
| Leaves | **3** | Functional + H2 integrity; weak calendar/stage UX |
| Requests | **2** | CRUD tickets |
| Letters | **1** | Types without generation |
| Decisions | **0** | Deferred / absent |
| Payroll | **3** | Ops generate/print solid; decide/GL/PDF gaps |
| Expenses | **0** | Not in HR |
| Violations | **1** | Schema only |
| Allowances | **2** | Via components only |
| Efficiency / Performance | **1** | Hidden HRM stubs |
| Succession | **0** | Missing |
| ESS | **3** | Core mobile flows work |
| Dashboard | **2** | Light cards |
| Reports | **2** | Two reports |
| Notifications | **2** | Leave-centric |
| Settings | **1** | No HR settings UI |

**Average ≈ 1.7–2.3** depending on weighting toward live `hr/*` modules (~2.3).

---

## 19. P0 / P1 / P2 / P3 Priorities

### P0 — Broken / unusable for expected roles

1. **Company/manager cannot complete approve/reject** for leave/request/payroll in-product (inbox is watch-only).
2. **Recruitment “deployed” does not create an employee** — hiring journey dead-ends.
3. **ESS cannot request salary/experience certificates** (types coerced to inquiry) while Admin labels imply letters exist.
4. **Employment contracts absent** while “Contracts” nav implies HR coverage (misleading product).

### P1 — Major missing business capability

1. **Employee Master 360** (tabs: attendance, leave, payroll, documents, requests, loans, audit).
2. **Unified employee timeline**.
3. **Letter PDF generate + download + audit**.
4. **HireBridge** (candidate → `rateb_employees`, idempotent).
5. **Leave/request status UX** (stage, actor, history) without new approval engine.
6. **ESS leave-types catalog + payslip PDF**.
7. **Balance adjust / carry-forward** product UI (beyond H2 integrity).
8. **Disciplinary / decisions** Admin surfaces (or explicitly hide schema promises).

### P2 — Important UX / product gaps

1. HR dashboard depth (on leave today, late today, contracts expiring, utilization).
2. Leave calendar + upcoming leave.
3. Attendance monthly employee self-explain view.
4. Org chart / manager field (reporting line, not auth hierarchy).
5. Documents unified center (files + metadata + expiry alerts).
6. Navigation cleanup (hrm orphan, dual payroll, contracts labeling).
7. Notification coverage (payslip, request status, expiry).
8. Salary visibility permission on employee show.
9. Flutter manager Approvals beyond placeholder.

### P3 — Enhancements

1. Succession, advanced performance, analytics tiles.
2. Working-day / holiday leave calc (deferred from H2).
3. Half-day AM/PM.
4. GPS/geofence punch, biometric import.
5. GOSI/WPS connectors (flagged, certified later).
6. Polished charts, kanban recruitment, vacancy module.

---

## 20. Recommended next phases (user-visible value first)

> These **product phases** prioritize what users see. They may **reorder** the existing enterprise roadmap (which next lists Attendance engine as Phase I). Do **not** start implementation in this audit.

### Recommended Phase I — Employee Master 360 (Admin)

**Goal:** One employee page answers “Who is this person?”  
Tabs/sections: identity, employment, salary/structure, attendance summary (use existing `attendance_ytd`), leave balances/history, requests, documents, loans, payroll slips, audit trail.  
**Exit:** Show page is the HR home for an employee; no new employee table.

### Recommended Phase J — Actionable Approvals UX (Company)

**Goal:** Company HR/authorized roles can **understand and act** on pending leave/permission/request/payroll without inventing ApprovalEngine2.  
Respect Oversight + Matrix; surface stage/history; keep tenant rules.  
**Exit:** Inbox is not watch-only for authorized company actors (product decision required vs current block).

### Recommended Phase K — HireBridge + Employment Contracts (additive)

**Goal:** Recruitment deployed → employee; employment contract register with dates/expiry alerts.  
**Exit:** One happy-path hire; contract list on employee profile.

### Recommended Phase L — Letters & Documents Center

**Goal:** Salary/experience PDF generation + ESS download; unify document UX.  
**Exit:** Request → approve → PDF → employee download audited.

### Recommended Phase M — ESS/Mobile Parity Pack

**Goal:** Leave-types API, certificate requests, PDF payslip, manager pending (if Phase J allows), richer dashboard.  
**Exit:** Flutter journeys match Admin letter/leave/payslip promises.

### Recommended Phase N — Attendance Experience (then engine)

**Goal:** Monthly attendance explanation UX first; then raw punch → daily → exceptions (aligns with prior roadmap “Phase I” attendance engine once UX shell exists).

### Recommended Phase O — Dashboard, Reports, Notifications pack

**Goal:** Visible KPIs + enterprise report set + notification coverage — without a second notify engine.

### Explicitly defer

- Another security-only audit unless a P0 security regression appears.
- `public/v2` HR UI revival (architecture lock).
- ApprovalEngine2 / EAP / Legacy Workflow for HR.
- Rewriting payroll formula / confusing posted with GL/bank.

---

## Appendix A — HR Dashboard widget matrix

| Widget | Status |
|--------|--------|
| Total employees | **Existing** |
| Active employees | **Existing** |
| Present today | **Existing** |
| Absent today | **Existing** |
| Pending leaves | **Existing** (links inbox) |
| Draft payrolls | **Existing** (links inbox) |
| Pending approvals total | **Partial** (banner) |
| New employees | **Missing** |
| Employees on leave today | **Missing** |
| Late today | **Missing** |
| Contracts expiring | **Missing** |
| Leave utilization | **Missing** |
| Attendance summary charts | **Missing** |
| Payroll status board | **Missing** |

---

## Appendix B — Files inspected (representative)

- `rateb-erp/config/hr-menu.php`
- `rateb-erp/views/partials/sidebar-hr-nav.php`
- `rateb-erp/views/company/hr/dashboard.php`
- `rateb-erp/views/company/hr/employees/show.php`, `index.php`, `form.php`
- `rateb-erp/views/company/hr/leaves/*`, `attendance/*`, `payroll/*`, `approvals/inbox.php`, `reports.php`, `reports-leaves.php`, `requests/*`, `documents/*`
- `rateb-erp/routes/modules/ops.php`, `api.php`
- `rateb-erp/app/controllers/Company/HrControllers.php`, `HrExtendedControllers.php`, `HumanResourcesControllers.php`, `RecruitmentControllers.php`
- `rateb-erp/app/services/HrService.php`, `HrEss*`, `HrApprovalInboxService.php`, `ApprovalOversightService.php`, `NotificationService.php`, `RecruitmentWorkflowService.php`
- `ratib_hr_mobile/lib/features/**` (tabs, approvals placeholder)
- `docs/hr/HR-ENTERPRISE-ROADMAP.md`, H2 certification (context only)

---

## Appendix C — Definition of audit done

```text
[x] Real HR product surfaces mapped
[x] Employee / Admin / Manager journeys classified
[x] Employee master / timeline / dashboard audited
[x] Leave / attendance / payroll UX gaps listed (no H2 logic change)
[x] Contracts / recruitment / org / letters / reports / ESS / nav / search / docs / notifications / settings
[x] Performance + UI security notes
[x] Maturity scores
[x] P0–P3 + recommended product phases
[x] No code / DB / migration / deploy
```

**STOP — audit only. Do not start Phase I implementation from this document until explicitly authorized.**
