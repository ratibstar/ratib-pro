# P18-00 — Phase 18 Projects Audit (Enterprise Report)

**Status:** COMPLETE (evidence only)  
**Architecture Freeze:** AF 2.1 + AF 2.1.1 ACTIVE  
**Implementation:** NONE — STOP after audit  
**Scope:** ONLINE ERP Projects (`rateb-erp/` Phase 18A `/projects/*`) — **NOT** Offline V1 as SoT, **NOT** Offline V2 BM (no Projects BusinessModule yet)

---

## Executive verdict

Online Projects is a **Phase 18A tenant-scoped foundation**:

1. **Enterprise Projects** (`/projects/*`) — `rateb_project_*` projects, phases, milestones, tasks/subtasks, kanban/gantt/calendar views, issues, risks, timesheets, resources, budget/cost **meta**, comments, assignments, tags, timeline, status history.
2. **Distinct from CRM tasks** (`rateb_crm_tasks`) and **HR attendance** — services prefixed `Project*` to avoid `TaskService` / `AssignmentService` collisions.
3. **Soft links only** to customers (`customer_id`), cost centers (`cost_center_id`), and user directory (`owner_user_id`, `assignee_user_id`, `user_id`) — **no inventory posting, no GL, no sales/procurement document ownership**.
4. **MVC + session RBAC**, not a JSON Projects API. Offline V2 has **no Projects BusinessModule**. Offline V1 Phase 18B wraps online `Project*` services (flags OFF) — frozen reference only.

Offline V2 Projects BM must:

- Own **project documents only** (`proj.*` entities).
- Depend on **identity** (mandatory); optional **crm** / **sales** / **procurement** / **accounting** / **inventory** via published APIs only.
- **Never** own authentication, inventory balances, accounting journals, procurement/sales docs, CRM stores, HR records, or manufacturing documents.
- Treat budget/cost/timesheet/resource rows as **local meta** unless future ports explicitly bridge to accounting/HR/inventory.

---

## 1. Architecture

| Layer | Evidence |
|-------|----------|
| Pattern | Thin controllers → `Project*` domain services → `ProjectModels` → MySQL |
| Workflow authority | **Only** `ProjectWorkflowService` may change `workflow_status` on projects/tasks (`ProjectService::update` / `ProjectTaskService::update` unset `workflow_status`) |
| Timeline | Append-only `ProjectTimelineService::record()` → `rateb_project_timeline` |
| Tenant | `ProjectSupport::requireCompanyId()` via `TenantContext`; branch/user from session |
| Docs | `docs/PHASE_18A_PROJECTS_ONLINE.md` |
| Tests | `tests/projects/ProjectsPhase18ATest.php` |
| Offline V1 | `offline/docs/PHASE_18B_PROJECTS_OFFLINE.md` — queue replay to same services; flags default OFF |

```
Controllers (Company\Project*)
  → ProjectService / ProjectTaskService / … / ProjectWorkflowService
  → app/models/ProjectModels.php
  → rateb_project_* tables
```

**Not present:** REST/JSON API module, domain event bus, cross-module write ports, attachment binary store.

---

## 2. Database schema

**Migration:** `migrations/184_projects_platform_enterprise.sql`

| Table | Purpose |
|-------|---------|
| `rateb_project_roles` | Member role catalog |
| `rateb_project_tags` | Tag catalog |
| `rateb_projects` | Project header (`project_no`, workflow, dates, `customer_id`, `cost_center_id`, `budget_amount`, `version`) |
| `rateb_project_members` | Project membership |
| `rateb_project_phases` | WBS phases |
| `rateb_project_milestones` | Milestones (optional `phase_id`) |
| `rateb_project_tasks` | Tasks/subtasks (`parent_task_id`, kanban/gantt fields) |
| `rateb_project_activities` | Activity log entries |
| `rateb_project_timeline` | Append-only timeline (`meta_json`) |
| `rateb_project_issues` | Issue tracker |
| `rateb_project_risks` | Risk register |
| `rateb_project_timesheets` | Time entries (`status`: draft/submitted/approved/rejected) |
| `rateb_project_resources` | Resource allocation (`user`/`equipment`/`material`/`other`) |
| `rateb_project_budgets` | Budget lines (`status`: draft/approved/locked) |
| `rateb_project_costs` | Cost lines (`status`: recorded/void) — **local ledger, not GL** |
| `rateb_project_comments` | Text comments |
| `rateb_project_assignments` | Generic assignments (`related_type` project/task) |
| `rateb_project_entity_tags` | Tag links |
| `rateb_project_status_history` | Workflow audit trail |

**Cross-module soft refs (no FK enforcement in services):**

- `rateb_projects.customer_id` → sales/CRM customer directory (int only)
- `rateb_projects.cost_center_id` → accounting cost center (int only)
- `rateb_mfg_production_orders.project_id` → manufacturing PO (peer soft link; MFG owns PO)

**Distinct tables (do not conflate):**

- `rateb_crm_tasks` — CRM activities
- HR attendance / leave — not project timesheets

---

## 3. Domain services

| Service | Responsibility |
|---------|----------------|
| `ProjectSupport` | UUID, tenant, numbering (`PRJ-YYYY-#####`, `T-####`), find/assert |
| `ProjectService` | Project CRUD, board counts |
| `ProjectTaskService` | Task CRUD, kanban/gantt row helpers |
| `ProjectPhaseService` | Phase list/create |
| `ProjectMilestoneService` | Milestone list/create |
| `ProjectIssueService` | Issue list/create |
| `ProjectRiskService` | Risk list/create |
| `ProjectBudgetService` | Budget + **local** cost record/list |
| `ProjectResourceService` | Resource allocation meta |
| `ProjectCommentService` | Comments |
| `ProjectAssignmentService` | Assign project/task; syncs `ProjectMember` / `assignee_user_id` |
| `ProjectTagService` | Tag create (no attach route in 18A HTTP) |
| `ProjectWorkflowService` | Sole project/task `workflow_status` transitions + status history |
| `ProjectTimelineService` | Append-only timeline |
| `ProjectActivityService` | Structured activities |
| `ProjectTimesheetService` | **Draft-only** timesheet create |

**Files:** `app/services/ProjectDomainServices.php`, `ProjectActivityServices.php`, `ProjectWorkflowService.php`, `ProjectTimelineService.php`, `ProjectSupport.php`

---

## 4. Workflows

### Project workflow

`draft → planned → active → on_hold → completed|cancelled → archived`

| From | Allowed to |
|------|------------|
| draft | planned, cancelled, archived |
| planned | active, on_hold, cancelled, archived |
| active | on_hold, completed, cancelled |
| on_hold | active, cancelled, archived |
| completed | archived |
| cancelled | archived, draft |
| archived | (terminal) |

### Task workflow

`new → assigned → in_progress → review → done|cancelled`

| From | Allowed to |
|------|------------|
| new | assigned, in_progress, cancelled |
| assigned | in_progress, cancelled |
| in_progress | review, done, cancelled |
| review | in_progress, done, cancelled |
| done | (terminal) |
| cancelled | new |

**Gaps:**

- Timesheet `submitted` / `approved` / `rejected` — **enum only**; no `ProjectTimesheetWorkflowService`
- Budget `approved` / `locked` — **enum only**; no approve/lock service
- Issue/risk/milestone/phase — status fields but no dedicated workflow machines
- Milestone `achieved` — no transition service (create-only HTTP)

---

## 5. Permissions (RBAC)

**Module:** `projects` — `config/module-permissions.php` → `projects.manage`

| Slug | Role |
|------|------|
| `projects.view` | View projects and related records |
| `projects.create` | Create projects |
| `projects.update` | Update projects |
| `projects.delete` | Soft-delete |
| `projects.assign` | Members / owners |
| `projects.tasks` | Tasks, kanban, gantt, calendar |
| `projects.timesheets` | Timesheets |
| `projects.budget` | Budgets and costs |
| `projects.reports` | Reports board |
| `projects.admin` | Full admin subset |
| `projects.manage` | Implies all above — `config/permissions-system.php` |

**Entity map:** `config/entity-permissions.php` — `projects`, `project-tasks`

**Middleware:** `rateb_erp_mw('projects', '<perm>', 'projects')` on `routes/modules/ops.php`

---

## 6. HTTP routes / APIs

**Surface:** Server-rendered MVC + CSRF form POST — **not** a public JSON API.

| Route pattern | Controller | Permission |
|---------------|------------|------------|
| `GET /projects` | `ProjectsDashboardController` | `projects.view` |
| `GET/POST /projects/list`, create, show, edit, update, delete, transition, assign, comments | `ProjectsController` | mixed |
| `GET/POST /projects/tasks`, kanban, gantt, calendar, `{id}/transition` | `ProjectTasksController` | `projects.tasks` |
| `GET/POST /projects/milestones` | `ProjectMilestonesController` | view / update |
| `GET/POST /projects/issues`, `/risks` | `ProjectIssuesController`, `ProjectRisksController` | view / update |
| `GET/POST /projects/timesheets` | `ProjectTimesheetsController` | `projects.timesheets` |
| `GET/POST /projects/resources` | `ProjectResourcesController` | view / update |
| `GET/POST /projects/budget`, `/budget/costs` | `ProjectBudgetController` | `projects.budget` |
| `GET/POST /projects/timeline`, `/timeline/activities` | `ProjectTimelineController` | view / update |
| `GET /projects/reports` | `ProjectReportsController` | `projects.reports` |

**Missing HTTP routes (service exists, UI/replay only):**

- `ProjectPhaseService` — no `/projects/phases` route (Offline 18B `phase.create` only)
- `ProjectTagService` / entity tags — no tag management routes
- Timesheet approve/submit — no route
- Budget approve/lock — no route
- Task/project delete endpoints partial (project soft-delete yes; limited child deletes)

**Views:** `views/company/projects/**` (16 PHP templates incl. dashboard, kanban, gantt, calendar, budget, reports)

---

## 7. Ownership boundaries

| Domain | Online Projects behavior | Projects BM must |
|--------|--------------------------|------------------|
| Authentication | Session `SessionManager` / `TenantContext` | Use `module.identity.*` only; never store credentials |
| Inventory | `resource_type=material` is label/meta only; **no stock movement** | Never own balances; optional `module.inventory.*` read for item refs |
| Accounting | `cost_center_id` soft int; `rateb_project_costs` local; **no journal post** | Own cost/budget **meta**; optional accounting port for future GL bridge — never own GL |
| Procurement | No PO/PR ownership | Optional events only via `module.procurement.*` |
| Sales | `customer_id` soft int only | Optional customer link via `module.sales.*` or CRM account port |
| CRM | Separate `rateb_crm_tasks`; optional same `customer_id` namespace | Never own CRM leads/opportunities; optional `module.crm.*` link |
| HR | Project timesheets ≠ HR attendance/leave | Never own HR records; optional directory via identity; do not duplicate payroll |
| Manufacturing | MFG PO may reference `project_id` | Never own MFG orders/BOM; optional read of MFG project link |

**Verdict:** Online stack respects **document ownership** inside `rateb_project_*`. Cross-module fields are **soft links without enforced ports** — dual-truth risk if V2 BM copies IDs without API validation.

---

## 8. Dependencies (Offline V2 recommendation)

### Mandatory

- `module.identity.*` — sealed identity, RBAC, unlock gate

### Optional (published APIs only)

| Module | Use case |
|--------|----------|
| `module.crm.*` | Link project to CRM account/contact/opportunity (read + soft ref) |
| `module.sales.*` | Resolve `customer_id`, future billing milestone bridge |
| `module.procurement.*` | Material requisition / subcontract PO signals (events only) |
| `module.accounting.*` | Cost center validation; optional cost accrual **events** — never post GL from Projects |
| `module.inventory.*` | Read item catalog for `resource_type=material` — never post stock |

### Forbidden ownership

Authentication · inventory balances · accounting journals · procurement documents · sales documents · CRM stores · HR records · manufacturing documents

### Dependency graph

```
identity (mandatory)
    ↑
proj BM
    ├──→ crm (optional): account/contact soft refs
    ├──→ sales (optional): customer ref
    ├──→ accounting (optional): cost center + cost sync events
    ├──→ procurement (optional): requisition signals
    └──→ inventory (optional): item read for material resources

Peers (soft refs only): mfg production_order.project_id

FORBID owning: GL, stock, PO/SO docs, CRM/HR/MFG stores, auth
```

---

## 9. Reusable concepts (for Offline V2 BM)

- Additive `rateb_project_*` schema as **conceptual model** for `proj.*` entities
- Sole workflow authority pattern (`ProjectWorkflowService`)
- Soft-delete + `public_uuid` + company/branch + optimistic `version` on project/task
- Append-only timeline + status_history
- Permission matrix `projects.*`
- Distinction from CRM tasks / HR attendance / MFG work orders
- Budget/cost/timesheet as **local meta** until explicit cross-module ports
- Kanban/gantt/calendar as **projections** over task workflow_status (not separate SoT)

---

## 10. Non-reusable code

- PHP `Model` + `SessionManager` / `TenantContext` / `rateb_erp_mw`
- Controllers, CSRF form posts, server-rendered views
- Direct SQL in domain services
- Offline V1 queue (`ProjectOfflineReplayService`), adapters, feature flags, IndexedDB — **do not lift as V2 SoT**
- `ProjectSupport::userId()` tied to PHP session — V2 uses identity claims
- Reports page = in-memory board counts only

---

## 11. Risks

| ID | Severity | Risk |
|----|----------|------|
| R1 | High | `customer_id` / `cost_center_id` stored without cross-module validation — orphan refs |
| R2 | High | Budget/cost **meta** can be mistaken for accounting SoT — no GL bridge |
| R3 | High | Timesheet `approved` status exists but **no approval workflow** — HR payroll confusion |
| R4 | Medium | Dual assignment paths (`ProjectMember` + `ProjectAssignment` + direct `assignee_user_id`) |
| R5 | Medium | Phases/tags lack HTTP CRUD — incomplete online surface |
| R6 | Medium | `resource_type=material` implies inventory but **no item_id** column |
| R7 | Medium | No attachments — comments only; binary docs out of scope |
| R8 | Medium | No REST API — V2 must design `module.proj.*` contract from services, not routes |
| R9 | Info | Offline V1 18B wraps online — V2 must not inherit V1 queue architecture |
| R10 | Info | Offline V2 has **no** Projects BM yet |

---

## 12. Missing abstractions

1. **ProjectWorkflowPort** — sole transition for project/task status  
2. **TaskPort** — CRUD + subtask tree + kanban projection  
3. **WbsPort** — phases + milestones  
4. **TimesheetPort** — draft create + future submit/approve (HR/accounting bridge optional)  
5. **BudgetLedgerPort** — budget/cost meta only  
6. **ResourcePort** — allocation meta; optional inventory item ref  
7. **IssueRiskPort** — issues/risks lifecycle  
8. **TimelinePort** — append-only events  
9. **CustomerLinkPort** — validate CRM/sales customer refs via APIs  
10. **CostCenterLinkPort** — validate accounting cost center via API  
11. **DirectoryPort** — user display via identity (never HR SoT)  
12. Clear rule: Projects never writes inventory qty / GL / sales / procurement / CRM / HR / MFG  

---

## 13. Suggested Offline V2 Projects BM (future — not in this phase)

**Module id:** `proj` · **Entities:** `proj.project`, `proj.phase`, `proj.milestone`, `proj.task`, `proj.issue`, `proj.risk`, `proj.timesheet`, `proj.resource`, `proj.budget`, `proj.cost`, `proj.comment`, `proj.assignment`, `proj.timeline`, `proj.status_history`

**Suggested services (`module.proj.*`):** `upsertProject` · `transitionProject` · `upsertTask` · `transitionTask` · `upsertPhase` · `upsertMilestone` · `createIssue` · `createRisk` · `createTimesheet` · `upsertResource` · `createBudget` · `recordCost` · `assign` · `recordTimeline` · `listTimeline` · `probeOptionalPeers` · `getDiagnostics` · `runSelfTest`

**Suggested events:** `proj:ready` · `proj:project_transitioned` · `proj:task_transitioned` · `proj:timesheet_logged` · `proj:cost_recorded` · `proj:issue_created` · `proj:risk_created`

**Suggested permissions:** `projects.view|create|update|delete|assign|tasks|timesheets|budget|reports|admin|manage` (mirror online slugs in identity RBAC snapshot)

---

## 14. Architecture conflict check

No Platform or existing BusinessModule modification is required for this **audit**.  
If future implementation requires changing Platform / Identity / Inventory / Procurement / Sales / Accounting / CRM / HR / Manufacturing — **STOP** and raise Architecture Conflict.

---

## Phase boundary

**Phase 18 Projects Audit: COMPLETE**  
**Do NOT implement Projects BusinessModule in this phase.**  
**STOP.**  
Wait for Architecture Board approval before creating the Projects BusinessModule.
