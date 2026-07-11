# Phase 26A — Enterprise Document Management Platform (ONLINE)

**Status:** Implemented (ONLINE foundation layer)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**Offline:** Do NOT implement Offline DMS here — deferred to Phase **26B**.  
**Migration:** `migrations/192_document_management_platform.sql`

## Executive Summary

Phase 26A adds a **tenant-scoped Enterprise Document Management System (DMS/ECM)** on additive `rateb_dms_*` tables for repositories, folders, documents, versions, metadata, checkouts, shares, permissions, retention policies/jobs, legal holds, categories, tags, document links/relations, approvals meta, signature requests/events, audit logs, timeline, comments, favorites, recent items, search index, and status history.

It does **not** replace the legacy attachment layer (`rateb_documents` / `DocumentService` / `DocumentsController`). Soft links only (`legacy_document_id`). UI routes live under `/dms/*` guarded by `rateb_erp_mw('documents', …)`.

## Repository Audit (pre-26A)

| Area | Status | Action |
|------|--------|--------|
| `DocumentService` | Exists — entity upload pipeline | **Never modify** |
| `DocumentsController` / `documents` route | Exists — legacy list/upload | **Never modify** |
| `rateb_documents` / `rateb_entity_attachments` | Exist | **Never ALTER** |
| Module-specific `*DocumentMetaService` | Exist (HR, assets, QMS, etc.) | Unchanged; DMS is separate |
| `rateb_dms_*` / `/dms/*` | Absent pre-26A | **Greenfield** additive namespace |
| Offline Foundation / Queue / Replay / SDK / IndexedDB | Frozen | **Not modified** |

## Architecture

```
Controllers (thin, company/dms)
  → Domain services (DmsRepository*, DmsDocument*, DmsShare*, …)
  → DocumentWorkflowService ONLY for workflow_status
    → Models (Dms* → rateb_dms_*)
      → Database
```

## Workflow

**Only via `DocumentWorkflowService`:**

`draft → checked_in → review → approved → published → archived`

## Security

- Tenant + branch scoped rows
- `public_uuid` on every table
- Optimistic locking via `version`
- CSRF on mutating forms
- Soft delete (`deleted_at`)
- Permissions: `documents.view`, `documents.create`, `documents.update`, `documents.share`, `documents.download`, `documents.retention`, `documents.admin`, `documents.manage`

## Migration

`192_document_management_platform.sql` — 25 additive `CREATE TABLE IF NOT EXISTS rateb_dms_*` + permission seed (`ON DUPLICATE KEY UPDATE`) + role grants for `company-full-access` / `super-admin`. No `ALTER` of `rateb_documents` or attachment tables.

## Offline readiness (for later Phase 26B)

| Operation | Service | Replay-ready | Notes |
|-----------|---------|--------------|-------|
| Repository / folder create | domain services | YES | Master data |
| Document create | `DmsDocumentService` | YES | Starts `draft` |
| Workflow transition | `DocumentWorkflowService` | YES | Must call service |
| Share / retention / legal hold | domain services | YES | |
| Search index update | `DmsSearchService` | YES | Metadata only |
| Legacy `DocumentService` upload | Attachments | NO in 26A | Soft-link only |
| Offline adapter / replay | N/A | Deferred 26B | Not shipped in 26A |

## Performance

- Paginated lists (`LIMIT`/`OFFSET` + `COUNT`)
- Search index table with title index column
- Timeline append-only indexes on `(company_id, created_at)` and entity keys
- Company-scoped queries with `deleted_at IS NULL`
- Workflow status indexes on documents
- Minimal joins (search uses single inner join to documents)

## Regression

- Legacy `documents` route and `DocumentService` unchanged
- Offline Foundation markers (`DB_VERSION = 2`, SDK `14.2.0`) intact
- No modifications to Queue, Replay, Service Worker, RBAC core, or authentication

## Tests

```bash
php tests/documents/run-document-management-phase26a-tests.php
```

Target: **13/13 PASS**

## Production Readiness

- Additive migration with idempotent `CREATE TABLE IF NOT EXISTS`
- Permission seeds with `ON DUPLICATE KEY UPDATE`
- Thin controllers delegating to domain services
- Workflow authority centralized in `DocumentWorkflowService`
- EN/AR translations and sidebar navigation wired
- Ready for migration apply on production after deploy
