# Phase 18A — Enterprise Projects Platform (ONLINE FOUNDATION)

**Status:** Implemented (ONLINE foundation layer)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline:** Do NOT implement Offline. No Queue / Replay / SDK / SW / IDB changes.  
**Migration:** `migrations/184_projects_platform_enterprise.sql`

## Executive Summary

Phase 18A adds a **tenant-scoped Enterprise Projects** module (projects, phases, milestones, tasks/subtasks, kanban/gantt/calendar, issues, risks, timesheets, resources, budget/costs, comments, timeline). It is distinct from CRM tasks (`rateb_crm_tasks`) and HR attendance. Services are prefixed `Project*` to avoid collisions with CRM `TaskService` / Recruitment `AssignmentService`.

## Repository Audit (pre-18A)

| Area | Status |
|------|--------|
| Standalone Projects module | **Missing → created** |
| CRM tasks / boards | **Not overloaded** |
| Recruitment assignments | **Not overloaded** |
| HR attendance | **Not used as timesheets** |
| Offline Projects | Deferred to **Phase 18B** |

## Architecture

```
Controllers (thin)
  → Domain services (Project*, ProjectWorkflowService only for status)
  → Models (rateb_project_* / rateb_projects)
  → Database
```

**Only `ProjectWorkflowService` may change project/task `workflow_status`.**

## Workflow

**Project:** `draft → planned → active → on_hold → completed|cancelled → archived`  
**Task:** `new → assigned → in_progress → review → done|cancelled`

## Offline readiness (for 18B later)

| Operation | Service | Offline Replay Compatible |
|-----------|---------|---------------------------|
| Project create/update draft | `ProjectService` | YES |
| Project/task workflow | `ProjectWorkflowService` | YES |
| Task / comment / activity create | Project* services | YES |
| Timesheet draft create | `ProjectTimesheetService` | YES (draft only) |
| Budget posting / approvals / payments | — | **NO** |
| Binary attachment upload | — | **NO** |

## RBAC

| Slug | Role |
|------|------|
| `projects.view` | view |
| `projects.create` | create |
| `projects.update` | update |
| `projects.delete` | soft-delete |
| `projects.assign` | assignments |
| `projects.tasks` | tasks/kanban/gantt |
| `projects.timesheets` | timesheets |
| `projects.budget` | budgets/costs |
| `projects.reports` | reports |
| `projects.admin` | admin |
| `projects.manage` | all |

## Files Created

- `migrations/184_projects_platform_enterprise.sql`
- `app/models/ProjectModels.php`
- `app/services/ProjectSupport.php`
- `app/services/ProjectWorkflowService.php`
- `app/services/ProjectTimelineService.php`
- `app/services/ProjectDomainServices.php`
- `app/services/ProjectActivityServices.php`
- `app/controllers/Company/ProjectControllers.php`
- `views/company/projects/**`
- `tests/projects/*`
- `docs/PHASE_18A_PROJECTS_ONLINE.md`

## Files Modified (additive)

- `routes/company.php`, `app/Core/Bootstrap.php`
- `config/permissions-system.php`, `config/entity-permissions.php`
- `config/permission-labels-{en,ar}.php`, `config/lang/{en,ar}.php`
- `views/partials/sidebar-ops-nav.php`

## Tests

```bash
php tests/projects/run-projects-phase18a-tests.php
```

## Production readiness

1. Run migration `184_projects_platform_enterprise.sql`
2. Enable plan module `projects` for the tenant
3. Grant `projects.*` permissions (seeded to `company-full-access` / `super-admin`)
4. Phase 18B may wrap these services — Offline flags must default OFF — Baseline untouched

## Success criteria

- ✔ Domain services own business rules  
- ✔ No Offline / Queue / Replay / SDK / Baseline changes  
- ✔ Distinct from CRM tasks / Recruitment assignments  
- ✔ Future 18B can call these services directly  
