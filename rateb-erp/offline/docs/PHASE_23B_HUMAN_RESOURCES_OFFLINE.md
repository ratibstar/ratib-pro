# Phase 23B — Enterprise Human Resources Offline (Tier-1 drafts)

**Status:** Implemented (additive Tier-1 offline)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**SDK:** **14.2.0** (additive enterprise methods on `RatebOffline.hr()`)  
**IndexedDB:** **DB_VERSION=2** — **NOT bumped**

## Executive Summary

Phase 23B adds Enterprise HRMS offline drafts on shared queue module `hr`, additive to Phase 4 attendance/leave. Client enqueues via `RatebOffline.hr()` → Offline Queue → ReplayEngine → `HumanResourcesOfflineReplayService` → Phase 23A services only → `rateb_hrm_*`. Phase 4 `HrOfflineReplayService` remains for attendance/leave. All new flags default OFF. Rejected on the enterprise path: delete, attendance, leave approval, payroll, salary, payments, government, binary upload, email, SMS, notifications, approvals.

## Repository Audit

Phase 23A services confirmed present and used as sole replay targets:

| Service | Role |
|---------|------|
| `EmployeeProfileService` | employee.create / employee.update |
| `DepartmentService` | department.create |
| `PositionService` | position.create |
| `OrganizationService` | organization.create (unit / location by payload) |
| `TrainingService` | training.create |
| `PerformanceReviewService` | performance.create |
| `GoalService` | goal.create |
| `CompetencyService` | competency.create |
| `PromotionService` | promotion.create |
| `TransferService` | transfer.create |
| `HrmAssignmentService` | assignment.create (not Recruitment `AssignmentService`) |
| `EmployeeTimelineService` | note.create |
| `EmployeeCommentService` | comment.create |
| `HumanResourcesWorkflowService` | workflow.transition |
| `EmployeeDocumentMetaService` | document meta only (no binary upload) |

No SQL bypass. No duplicated validation/workflow.

## Files Created

- `offline/server/Services/HumanResourcesOfflineReplayService.php`
- `offline/server/Services/HumanResourcesOfflineTenantGuard.php`
- `offline/server/Services/HumanResourcesOfflineMasterDataDirectoryService.php`
- `offline/client/adapters/hr-adapter.js` (extended; Phase 4 APIs preserved)
- `offline/tests/HumanResourcesOfflinePhase23bTest.php`
- `offline/tests/run-humanresources-offline-tests.php`
- `offline/docs/PHASE_23B_HUMAN_RESOURCES_OFFLINE.md`

## Files Modified

- `offline/config/feature-flags.php`
- `offline/config/modules.php`
- `offline/config/entity-manifest.php`
- `offline/config/master-data-entities.php`
- `offline/config/ops-page-allowlist.php`
- `offline/server/Services/OfflineFeatureFlagService.php`
- `offline/server/Services/OfflineReplayEngine.php`
- `offline/server/Services/OfflineQueueService.php`
- `offline/server/Services/OfflineConflictResolverService.php`
- `offline/server/Services/OfflineAuthorizationService.php` (hr ability already present; no redesign)
- `offline/server/Services/OfflineBackgroundSync.php` (`hr_enterprise_enabled`)
- `offline/server/Services/OfflineCursorService.php`
- `offline/server/Services/ErpOfflineMasterDataPolicy.php`
- `offline/client/core/sdk.js`
- `offline/client/adapters/ops-forms-adapter.js`
- `offline/scripts/build-rateb-offline-bundle.php`
- `public/assets/offline/rateb-offline.js`
- `public/assets/offline/rateb-offline.min.js`
- `tests/hr/HrPhase23ATest.php`

## Architecture

```
RatebOffline.hr()
  → Offline Queue (module = hr, frozen schema)
  → OfflineReplayEngine (dispatch by action)
      ├─ enterprise actions → HumanResourcesOfflineReplayService → Phase 23A → rateb_hrm_*
      └─ attendance / leave → HrOfflineReplayService (Phase 4) — unchanged
```

Additive Tier-1 only. No Foundation / SDK / IndexedDB redesign.

## Replay

- Enterprise actions only → `HumanResourcesOfflineReplayService`
- Delegation exclusively to Phase 23A services listed in Repository Audit
- Idempotency via existing `[offline:key]` in notes
- Never bypass services; never write SQL directly
- `str_starts_with($action, 'hr.')` attendance/leave stays on Phase 4 path

## Queue

- `module = hr` (shared with Phase 4; frozen schema)
- Supported: `employee.create|update`, `department.create`, `position.create`, `organization.create`, `training.create`, `performance.create`, `goal.create`, `competency.create`, `promotion.create`, `transfer.create`, `assignment.create`, `workflow.transition`, `comment.create`, `note.create`
- Rejected (enterprise path): delete, attendance, leave approval, payroll, salary, payments, government, binary upload, email, SMS, notifications, approvals

## Feature Flags

Default **OFF**. All require `offline.enabled`.

| Flag | Env |
|------|-----|
| `offline.hr` | `RATEB_OFFLINE_HR` |
| `offline.hr.employee` | `RATEB_OFFLINE_HR_EMPLOYEE` |
| `offline.hr.training` | `RATEB_OFFLINE_HR_TRAINING` |
| `offline.hr.performance` | `RATEB_OFFLINE_HR_PERFORMANCE` |
| `offline.hr.workflow` | `RATEB_OFFLINE_HR_WORKFLOW` |
| `offline.hr.masterdata` | `RATEB_OFFLINE_HR_MASTERDATA` |

Phase 4 `offline.hr.attendance` remains separate and unchanged.

Subflag gates: employee/dept/position/org → `.employee`; training → `.training`; performance/goal/competency → `.performance`; workflow → `.workflow`; promotion/transfer/assignment/comment/note → parent `offline.hr` only.

## Security

- `HumanResourcesOfflineTenantGuard` — company + branch isolation
- Server-authoritative replay; offline cache UI-only
- Existing Auth + RBAC unchanged
- Idempotency via `[offline:key]`
- No security redesign

## Performance

Reuses existing cursor engine, replay engine, queue, master data, background sync. No `DB_VERSION` bump. No SDK version bump. No IndexedDB schema changes.

## Regression

Verified green (or noted):

| Suite | Result |
|-------|--------|
| Phase 23B HR offline | **26/26 PASS** |
| Phase 23A HR online | **GATE CLEAR 0/13** |
| Phase 22B Manufacturing | **26/26 PASS** |
| Phase 21B Procurement Enterprise | **26/26 PASS** |
| Phase 20B Approval | **26/26 PASS** |
| Phase 19B Assets | **26/26 PASS** |
| Phase 18B Projects | **26/26 PASS** |
| Phase 17B CRM | **26/26 PASS** |
| Phase 16B Accounting | **27/27 PASS** |
| Enterprise Baseline v1.2 | **GATE CLEAR 0/10** |
| Phase 4 HR offline | **30/30 PASS** (deny assertion uses non-ability `payroll`; accounting allowed since 16B) |

SDK **14.2.0**, IndexedDB **DB_VERSION 2**, Offline Foundation **v1.1**, Baseline **v1.2** unchanged.

## Tests

```bash
php offline/tests/run-humanresources-offline-tests.php
php tests/hr/run-hr-phase23a-tests.php
php offline/tests/run-manufacturing-offline-tests.php
php offline/tests/run-enterprise-baseline-v12-tests.php
```

Phase 23B gate: **26/26 PASS** — flags, replay, queue, conflict, tenant, authorization, SDK, bundle, adapters, master data, ops hooks, background sync, foundation markers.

## Production Readiness

1. Confirm migration `189_hr_platform_enterprise.sql` applied online (Phase 23A).
2. Rebuild bundle: `php offline/scripts/build-rateb-offline-bundle.php`.
3. Pilot: enable `offline.enabled` + `offline.hr` (+ needed sub-flags). All remain **OFF by default**.
4. Phase 4 attendance continues on `offline.hr.attendance` only — do not require `offline.hr` for attendance.
5. No placeholders / TODOs. No redesign. Enterprise Baseline v1.2 freeze maintained.
