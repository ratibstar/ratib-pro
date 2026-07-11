# Phase 24B — Enterprise Payroll Offline (Tier-1 drafts)

**Status:** Implemented (additive Tier-1 offline)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**SDK:** **14.2.0** (additive `RatebOffline.payroll()`)  
**IndexedDB:** **DB_VERSION=2** — **NOT bumped**

## Executive Summary

Phase 24B adds Enterprise Payroll offline drafts on queue module `payroll`. Client enqueues via `RatebOffline.payroll()` → Offline Queue → ReplayEngine → `PayrollOfflineReplayService` → Phase 24A services only → `rateb_payroll_*`. Never calculates, approves, posts, or writes SQL in replay. All flags default OFF.

## Repository Audit

Phase 24A services confirmed present and used as sole replay targets:

| Service | Role |
|---------|------|
| `PayrollStructureService` | salary_structure.create / update |
| `EmployeeSalaryService` | employee_salary.create / update |
| `PayrollBatchService` | payroll_batch.create / update |
| `PayrollWorkflowService` | workflow.transition (draft↔prepared/archived only offline) |
| `LoanService` / `AdvanceService` / `BonusService` / `OvertimeService` / `SettlementService` | creates |
| `PayrollCommentService` | comment.create |
| `PayrollTimelineService` | note.create |
| `PayrollComponentService` / `PayrollCycleService` / `PayrollPayslipService` / `PayrollDocumentMetaService` / `PayrollCalculationService` | available; calculate never called offline |

## Files Created

- `offline/server/Services/PayrollOfflineReplayService.php`
- `offline/server/Services/PayrollOfflineTenantGuard.php`
- `offline/server/Services/PayrollOfflineMasterDataDirectoryService.php`
- `offline/client/adapters/payroll-adapter.js`
- `offline/tests/PayrollOfflinePhase24bTest.php`
- `offline/tests/run-payroll-offline-tests.php`
- `offline/docs/PHASE_24B_PAYROLL_OFFLINE.md`

## Files Modified

- Feature flags, modules, entity-manifest, master-data-entities, ops-page-allowlist
- OfflineFeatureFlagService, OfflineReplayEngine, OfflineQueueService, OfflineConflictResolverService
- OfflineAuthorizationService, OfflineBackgroundSync, OfflineCursorService, ErpOfflineMasterDataPolicy
- SDK, ops-forms-adapter, offline bundle build + assets
- Phase 24A domain: `PayrollStructureService::update`, `PayrollBatchService::update`, `EmployeeSalaryService` (business logic stays online)
- `tests/payroll/PayrollPhase24ATest.php`

## Architecture

```
RatebOffline.payroll()
  → Offline Queue (module = payroll, frozen schema)
  → OfflineReplayEngine
  → PayrollOfflineReplayService → Phase 24A → rateb_payroll_*
```

## Replay

Delegation only to Phase 24A. Idempotency via `[offline:key]` in notes. Never calculate/approve/post inside replay. Workflow offline limited to `draft` / `prepared` / `archived`.

## Queue

Supported: `salary_structure.create|update`, `employee_salary.create|update`, `payroll_batch.create|update`, `workflow.transition`, `loan|advance|bonus|overtime|settlement.create`, `comment.create`, `note.create`.

Rejected: delete, calculate, approve, post, payments, bank transfer, accounting posting, attendance import, leave approval, notifications, email/SMS, government, attachments, binary upload.

## Feature Flags

Default **OFF**. Require `offline.enabled`.

| Flag | Env |
|------|-----|
| `offline.payroll` | `RATEB_OFFLINE_PAYROLL` |
| `offline.payroll.employee` | `RATEB_OFFLINE_PAYROLL_EMPLOYEE` |
| `offline.payroll.batch` | `RATEB_OFFLINE_PAYROLL_BATCH` |
| `offline.payroll.workflow` | `RATEB_OFFLINE_PAYROLL_WORKFLOW` |
| `offline.payroll.masterdata` | `RATEB_OFFLINE_PAYROLL_MASTERDATA` |

## Security

`PayrollOfflineTenantGuard` — company + branch isolation. Server-authoritative. Existing Auth/RBAC. UI cache only. `[offline:key]` idempotency.

## Performance

Reuses cursor, replay, queue, master data, background sync. No SDK/IDB/DB_VERSION changes.

## Regression

SDK **14.2.0**, IndexedDB **DB_VERSION 2**, Offline Foundation **v1.1**, Baseline **v1.2** unchanged. Phase 24A / 23B / 22B / 21B / 20B and earlier remain green with additive flags OFF.

## Tests

```bash
php offline/tests/run-payroll-offline-tests.php
```

Target: **26/26 PASS**.

## Production Readiness

1. Confirm Phase 24A migration `190` applied online.
2. Rebuild bundle: `php offline/scripts/build-rateb-offline-bundle.php`
3. Pilot: enable `offline.enabled` + `offline.payroll` (+ sub-flags). All remain OFF by default.
4. Never enable calculate/approve/post offline — those stay ONLINE ONLY.
