# Phase 15A — Enterprise Recruitment Platform (ONLINE FOUNDATION)

**Status:** Implemented (ONLINE only)  
**Offline:** NO offline code — Foundation v1.1 untouched  
**Migration:** `migrations/181_recruitment_platform.sql`

## Purpose

Reusable domain services for recruitment so future Phase 15 Offline can call these services without duplicating business logic.

## Offline Replay Compatibility Matrix

| Operation | Reusable Service | Offline Replay Compatible |
|-----------|------------------|---------------------------|
| Create candidate | `CandidateService::create` | YES |
| Update candidate | `CandidateService::update` | YES |
| Soft-delete candidate | `CandidateService::softDelete` | YES |
| Add experience / education / note | `CandidateService::*` | YES |
| Workflow transition | `RecruitmentWorkflowService::transition` | YES |
| Create/update/delete visa | `VisaService` | YES |
| Create/update/delete medical | `MedicalService` | YES |
| Create/update/delete contract | `RecruitmentContractService` | YES |
| Create/update/delete interview | `InterviewService` | YES |
| Passport create | `PassportService` | YES |
| Agency CRUD | `RecruitmentAgencyService` | YES |
| Assign / revoke recruiter | `AssignmentService` | YES |
| Timeline / activity | `RecruitmentTimelineService` | YES |
| Attachment **upload** | `RecruitmentDocumentMetaService` → `DocumentService` | NO (ONLINE multipart only) |
| Attachment metadata list | `RecruitmentDocumentMetaService::listFor` | YES (read) |
| Search / list | `CandidateService::list` | YES (read / master-data later) |

## Explicitly out of scope (15A)

- Offline queue / replay / SDK / SW / IndexedDB
- Binary attachment sync offline
- Approvals engine redesign
- Payroll / accounting posting

## Run tests

```bash
php tests/recruitment/run-recruitment-phase15a-tests.php
```

## Enable module

1. Run migration `181_recruitment_platform.sql`
2. Ensure plan includes `recruitment` (professional / enterprise tiers updated)
3. Grant `recruitment.manage` (seeded for company-full-access / super-admin)
