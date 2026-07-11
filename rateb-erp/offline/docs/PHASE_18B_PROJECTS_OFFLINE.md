# Phase 18B — Projects Offline (Tier-1 Drafts)

**Status:** CLEAR — additive Tier-1 module on Enterprise Offline Foundation v1.1  
**SDK:** remains **14.2.0** (backward compatible; Projects flags + adapter additive)

## Executive Summary

Projects Offline wraps Phase 18A online `Project*` services only. Queue contract, IndexedDB v2, ReplayEngine architecture, SW, auth, and RBAC are unchanged aside from additive `module = projects` branches. All Projects feature flags default **OFF** and require `offline.enabled`.

**Not supported offline:** delete, payments, approvals, email/SMS send, attachment upload, government APIs.

## Replay Flow

```
Client adapter enqueue (module=projects)
  → OfflineQueue (frozen fields)
  → OfflineReplayEngine (additive Projects branch)
  → ProjectOfflineReplayService
  → Phase 18A Project* domain services
  → Database
```

## Queue Mapping

| Action | Sub-flag |
|--------|----------|
| `project.create` / `project.update` / `milestone.create` / `phase.create` / `comment.create` / `assignment.create` / `issue.create` / `risk.create` / `budget.create` / `activity.create` | `offline.projects` (parent) |
| `task.create` / `task.update` | `offline.projects.tasks` |
| `workflow.transition` | `offline.projects.workflow` |
| `timesheet.create` | `offline.projects.timesheets` |
| Master-data pull | `offline.projects.masterdata` |

**Module:** `projects` — queue field names unchanged.

## Feature Flags (default OFF)

- `offline.projects` → `RATEB_OFFLINE_PROJECTS`
- `offline.projects.tasks` → `RATEB_OFFLINE_PROJECTS_TASKS`
- `offline.projects.workflow` → `RATEB_OFFLINE_PROJECTS_WORKFLOW`
- `offline.projects.timesheets` → `RATEB_OFFLINE_PROJECTS_TIMESHEETS`
- `offline.projects.masterdata` → `RATEB_OFFLINE_PROJECTS_MASTERDATA`

## Tests

```bash
php offline/scripts/build-rateb-offline-bundle.php
php offline/tests/run-projects-offline-tests.php
```

## Production / Pilot readiness

- **Production:** flags OFF — safe to deploy code.
- **Pilot:** enable `offline.enabled` + `offline.projects` (+ sub-flags) after migration 184.
