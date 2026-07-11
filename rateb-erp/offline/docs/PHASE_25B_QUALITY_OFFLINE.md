# Phase 25B — Enterprise Quality Management Offline

**Status:** Implemented (Tier-1 drafts, flags default OFF)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**SDK:** **14.2.0** (banner note only; contracts unchanged)  
**IndexedDB:** **DB_VERSION = 2** — **NOT bumped**  
**Online prerequisite:** Phase **25A** (`rateb_qms_*`, `Quality*Service`, `QualityWorkflowService`)

## Executive Summary

Phase 25B adds additive Offline support for Enterprise QMS. Client queues via `RatebOffline.quality()` → Offline Queue `module=quality` → `OfflineReplayEngine` → `QualityOfflineReplayService` → **Phase 25A services only** → `rateb_qms_*`.

No duplicated business logic. No SQL from Replay. No SDK/IDB/Queue schema redesign. All feature flags **OFF** by default.

## Repository Audit (pre-25B)

| Target | Status |
|--------|--------|
| Migration 191 / `rateb_qms_*` | Present |
| `QualityWorkflowService` | Present |
| `QualityInspectionService` (+ `update` for offline drafts) | Present |
| `QualityChecklistService`, `QualityAuditService`, `QualityDefectService`, `QualityNonconformityService` | Present |
| `QmsCorrectiveActionService` / `QmsPreventiveActionService` (25A names) | Present |
| `SupplierQualityService`, `QualityComplaintService`, `QualityCommentService`, `QualityTimelineService` | Present |
| `QualityAssignmentService` | Added additively in 25A domain for replay target |
| Architecture redesign | **Not required** |

## Architecture

```
RatebOffline.quality()
  → Offline Queue (module = quality)
    → OfflineReplayEngine
      → QualityOfflineReplayService
        → Phase 25A services ONLY
          → rateb_qms_*
```

## Replay

Delegates only to:

- `QualityInspectionService`, `QualityChecklistService`, `QualityAuditService`
- `QualityDefectService`, `QualityNonconformityService`
- `QmsCorrectiveActionService`, `QmsPreventiveActionService`
- `SupplierQualityService`, `QualityComplaintService`
- `QualityAssignmentService`, `QualityCommentService`
- `QualityWorkflowService` (early statuses only offline)
- `QualityTimelineService` (`note.create`)

Rejected: delete, attachments, binary, notifications, email/SMS, payments, government, approvals, inventory/GL posting.

Offline workflow limited to early statuses (inspection/audit: planned/scheduled/archived; CAPA: draft/assigned/archived).

## Queue

**module = `quality`**

Supported: `inspection.create|update`, `checklist.create`, `audit.create`, `defect.create`, `nonconformity.create`, `corrective_action.create`, `preventive_action.create`, `supplier_quality.create`, `complaint.create`, `assignment.create`, `comment.create`, `workflow.transition`, `note.create`.

## Feature Flags (default OFF)

| Flag | Env |
|------|-----|
| `offline.quality` | `RATEB_OFFLINE_QUALITY` |
| `offline.quality.inspections` | `RATEB_OFFLINE_QUALITY_INSPECTIONS` |
| `offline.quality.audit` | `RATEB_OFFLINE_QUALITY_AUDIT` |
| `offline.quality.workflow` | `RATEB_OFFLINE_QUALITY_WORKFLOW` |
| `offline.quality.masterdata` | `RATEB_OFFLINE_QUALITY_MASTERDATA` |

Requires `offline.enabled`.

## Security

Tenant + branch guards (`QualityOfflineTenantGuard`), existing Auth/RBAC, server-authoritative replay, idempotency via notes markers, offline cache UI-only.

## Performance

Reuses Replay Engine, Queue, Cursor, Master Data, Background Sync. No new object stores / IDB version bump / SDK contract changes.

## Regression

SDK **14.2.0**, DB_VERSION **2**, Payroll 24B, Manufacturing, Assets, Procurement, HR, Accounting, CRM, Projects, POS — additive only. Hard-reject list fixed to `payments` only so flag-gated payroll/quality enqueue can work when enabled.

## Tests

```bash
php offline/tests/run-quality-offline-tests.php
```

Target: **26/26 PASS** / GATE CLEAR.

Also: `php tests/quality/run-quality-phase25a-tests.php` (coupling asserts 25B files exist).

## Production Readiness

1. Ensure Phase 25A migration 191 applied  
2. Keep all `RATEB_OFFLINE_QUALITY*` unset (OFF) until pilot  
3. Rebuild bundle: `php offline/scripts/build-rateb-offline-bundle.php`  
4. Enable flags gradually: `offline.enabled` → `offline.quality` → subflags  
5. Soft-links only — MFG `QualityCheckService` / EAM inspections unchanged
