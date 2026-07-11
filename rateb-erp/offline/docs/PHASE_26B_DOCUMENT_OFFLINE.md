# Phase 26B — Enterprise Document Management Offline

**Status:** Core files implemented (Tier-1 drafts, flags default OFF)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**SDK:** **14.2.0** (banner note only; contracts unchanged)  
**IndexedDB:** **DB_VERSION = 2** — **NOT bumped**  
**Online prerequisite:** Phase **26A** (`rateb_dms_*`, `Dms*Service`, `DocumentWorkflowService`)

## Executive Summary

Phase 26B adds additive Offline support for Enterprise DMS. Client queues via `RatebOffline.documents()` → Offline Queue `module=documents` → `OfflineReplayEngine` → `DocumentOfflineReplayService` → **Phase 26A services only** → `rateb_dms_*`.

No duplicated business logic. No SQL from Replay. No SDK/IDB/Queue schema redesign. All feature flags **OFF** by default.

## Architecture

```
RatebOffline.documents()
  → Offline Queue (module = documents)
    → OfflineReplayEngine
      → DocumentOfflineReplayService
        → Phase 26A services ONLY
          → rateb_dms_*
```

## Replay

Delegates only to:

- `DmsRepositoryService`, `DmsFolderService`, `DmsDocumentService`
- `DmsVersionService`, `DmsCheckoutService`, `DmsShareService`
- `DmsPermissionService` (`grant`), `DmsCommentService`
- `DocumentWorkflowService` (early statuses only offline)
- `DocumentTimelineService` (`note.create`)

Rejected: delete, upload, attachments, binary, notifications, email/SMS, payments, signature, publish, approve, download.

Offline workflow limited to: `draft`, `checked_in`, `review`, `archived` (never `approved` / `published`).

## Queue

**module = `documents`**

Supported: `repository.create|update`, `folder.create|update`, `document.create|update`, `version.create`, `checkout.create`, `share.create`, `permission.create`, `comment.create`, `workflow.transition`, `note.create`.

## Feature Flags (default OFF)

| Flag | Env |
|------|-----|
| `offline.documents` | `RATEB_OFFLINE_DOCUMENTS` |
| `offline.documents.repositories` | `RATEB_OFFLINE_DOCUMENTS_REPOSITORIES` |
| `offline.documents.workflow` | `RATEB_OFFLINE_DOCUMENTS_WORKFLOW` |
| `offline.documents.masterdata` | `RATEB_OFFLINE_DOCUMENTS_MASTERDATA` |

Requires `offline.enabled`.

## Security

Tenant + branch guards (`DocumentOfflineTenantGuard`), existing Auth/RBAC, server-authoritative replay, idempotency via notes markers (`[offline:key]`), offline cache UI-only.

## Tests

```bash
php offline/tests/run-document-offline-tests.php
```

Target: **~26 PASS** / GATE CLEAR (after parent wires flags / registry / bundle).
