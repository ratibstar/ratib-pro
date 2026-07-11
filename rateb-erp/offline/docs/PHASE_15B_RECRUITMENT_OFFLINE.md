# Phase 15B — Recruitment Offline (Tier-1)

**Status:** CLEAR — additive Tier-1 module on Enterprise Offline Foundation v1.1  
**SDK:** remains **14.2.0** (backward compatible; recruitment flags + adapter additive)

## Executive Summary

Recruitment Offline wraps Phase 15A online services only. Queue contract, IndexedDB v2, ReplayEngine architecture, SW, auth, and RBAC are unchanged aside from additive `module = recruitment` branches. All recruitment feature flags default **OFF** and require `offline.enabled`.

## Repository Audit (15A)

| Area | Location | Offline use |
|------|----------|-------------|
| Migration 181 | `migrations/181_recruitment_platform.sql` | Domain tables only |
| Services | `CandidateService`, `RecruitmentWorkflowService`, `AssignmentService`, `VisaService`, `MedicalService`, `PassportService`, `RecruitmentContractService`, `InterviewService`, `RecruitmentAgencyService` | Replay delegates to these |
| Controllers / routes / views | `app/` + `views/company/recruitment/` | Ops allowlist browse + form hooks |
| Permissions | `recruitment.*` | Server auth unchanged |
| Tests | `tests/recruitment/` | Online; offline tests under `offline/tests/` |

## Replay Flow

```
Client adapter enqueue (module=recruitment)
  → OfflineQueue (frozen fields)
  → OfflineReplayEngine (additive recruitment branch)
  → RecruitmentOfflineReplayService
  → Phase 15A domain services
  → Database
```

## Queue Mapping

| Action | Sub-flag |
|--------|----------|
| `candidate.create` / `candidate.update` / `note.create` | `offline.recruitment.candidates` |
| `workflow.transition` | `offline.recruitment.workflow` |
| `assignment.create` | `offline.recruitment.assignment` |
| `interview.create`, `visa.create`, `medical.create`, `passport.create`/`passport.update`, `contract.create` | `offline.recruitment` |

## Feature Flags (default OFF)

- `offline.recruitment` → `RATEB_OFFLINE_RECRUITMENT`
- `offline.recruitment.candidates` → `RATEB_OFFLINE_RECRUITMENT_CANDIDATES`
- `offline.recruitment.workflow` → `RATEB_OFFLINE_RECRUITMENT_WORKFLOW`
- `offline.recruitment.assignment` → `RATEB_OFFLINE_RECRUITMENT_ASSIGNMENT`

## Files Created

- `offline/server/Services/RecruitmentOfflineReplayService.php`
- `offline/server/Services/RecruitmentOfflineTenantGuard.php`
- `offline/server/Services/RecruitmentOfflineAgencyDirectoryService.php`
- `offline/server/Services/RecruitmentOfflineSkillDirectoryService.php`
- `offline/server/Services/RecruitmentOfflineLanguageDirectoryService.php`
- `offline/client/adapters/recruitment-adapter.js`
- `offline/tests/RecruitmentOfflinePhase15bTest.php`
- `offline/tests/run-recruitment-offline-tests.php`
- `offline/scripts/build-rateb-offline-bundle.php`
- `offline/docs/PHASE_15B_RECRUITMENT_OFFLINE.md`

## Files Modified (additive)

- Flags, queue, replay engine, conflict resolver, authz, modules, entity-manifest, master-data, cursor, ops allowlist, ops-forms, SDK, background sync, public SDK bundle

## Not supported (by design)

Government submission, payments, approvals, binary attachment upload.

## Remaining risks

- Passport online API is create-only; `passport.update` maps to create (metadata draft).
- Countries directory not implemented (no dedicated countries table); nationality remains free-text.
- Skills/languages delta uses id cursor (no `updated_at`).

## Production / Pilot readiness

- **Production:** flags OFF — safe to deploy code.
- **Pilot:** enable `offline.enabled` + `offline.recruitment` (+ sub-flags as needed) on a single tenant after migration 181.

## Tests

```bash
php offline/tests/run-recruitment-offline-tests.php
```

Target: **25/25 PASS**. Existing Inv/HR/Proc/14.2 suites remain GREEN.
