# Phase 17B — CRM Offline (Tier-1 Drafts)

**Status:** CLEAR — additive Tier-1 module on Enterprise Offline Foundation v1.1  
**SDK:** remains **14.2.0** (backward compatible; CRM flags + adapter additive)

## Executive Summary

CRM Offline wraps Phase 17A online services only. Queue contract, IndexedDB v2, ReplayEngine architecture, SW, auth, and RBAC are unchanged aside from additive `module = crm` branches. All CRM feature flags default **OFF** and require `offline.enabled`.

**Not supported offline:** delete, payments, approvals, email/SMS send, attachment upload, government APIs.

## Repository Audit (17A)

| Area | Location | Offline use |
|------|----------|-------------|
| Migration 183 | `migrations/183_crm_platform_enterprise.sql` | Domain tables |
| Services | `LeadService`, `CrmWorkflowService`, `OpportunityService`, `PipelineService`, `MeetingService`, `CallService`, `TaskService`, `CampaignService`, `ActivityService`, `CrmAssignmentService`, `CrmTimelineService`, `ContactService`, `CrmCompanyService`, `CrmNoteService` | Replay delegates write drafts; directories read-only |
| Controllers / routes / views | `routes/company.php` + `views/company/crm/` | Ops allowlist + form hooks |
| Permissions | `crm.*` | Server auth unchanged |
| Tests | `tests/crm/` | Online; offline under `offline/tests/` |

## Replay Flow

```
Client adapter enqueue (module=crm)
  → OfflineQueue (frozen fields)
  → OfflineReplayEngine (additive CRM branch)
  → CrmOfflineReplayService
  → Phase 17A domain services
  → Database
```

## Queue Mapping

| Action | Sub-flag |
|--------|----------|
| `lead.create` / `lead.update` / `note.create` / `contact.create` / `company.create` | `offline.crm.leads` |
| `workflow.transition` | `offline.crm.workflow` |
| `meeting.create` / `call.create` / `task.create` | `offline.crm.activities` |
| `opportunity.create` / `assignment.create` / `campaign.create` | `offline.crm` (parent) |
| Master-data pull | `offline.crm.masterdata` |

**Module:** `crm` — queue field names unchanged.

## Feature Flags (default OFF)

- `offline.crm` → `RATEB_OFFLINE_CRM`
- `offline.crm.leads` → `RATEB_OFFLINE_CRM_LEADS`
- `offline.crm.workflow` → `RATEB_OFFLINE_CRM_WORKFLOW`
- `offline.crm.activities` → `RATEB_OFFLINE_CRM_ACTIVITIES`
- `offline.crm.masterdata` → `RATEB_OFFLINE_CRM_MASTERDATA`

## Files Created

- `offline/server/Services/CrmOfflineReplayService.php`
- `offline/server/Services/CrmOfflineTenantGuard.php`
- `offline/server/Services/CrmOfflineMasterDataDirectoryService.php`
- `offline/client/adapters/crm-adapter.js`
- `offline/tests/CrmOfflinePhase17bTest.php`
- `offline/tests/run-crm-offline-tests.php`
- `offline/docs/PHASE_17B_CRM_OFFLINE.md`

## Files Modified (additive)

- Flags, queue, replay engine, conflict resolver, authz, modules, entity-manifest, master-data, cursor, ops allowlist, ops-forms, SDK, background sync, build script, public SDK bundle
- `tests/crm/CrmPhase17ATest.php` — online services remain free of offline queue coupling

## Tests

```bash
php offline/scripts/build-rateb-offline-bundle.php
php offline/tests/run-crm-offline-tests.php
```

## Production / Pilot readiness

- **Production:** flags OFF — safe to deploy code.
- **Pilot:** enable `offline.enabled` + `offline.crm` (+ sub-flags) after migration 183.
