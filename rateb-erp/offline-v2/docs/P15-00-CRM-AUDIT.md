# P15-00 — Phase 15 CRM Audit (Enterprise Report)

**Status:** COMPLETE (evidence only)  
**Architecture Freeze:** AF 2.1 + AF 2.1.1 ACTIVE  
**Implementation:** NONE — STOP after audit  
**Scope:** ONLINE ERP CRM (`rateb-erp/` Phase 17A) only — NOT Offline V1, NOT Offline V2 BM, NOT CMS leads, NOT Contact Center CRM

---

## Executive verdict

Online CRM is a **Phase 17A tenant-scoped foundation**: leads, CRM accounts (`rateb_crm_companies`), contacts, opportunities, pipelines, activities/meetings/calls/tasks, campaigns, timeline, and assignments — domain services + soft-delete tables from migration `183`.

It is **MVC + session auth**, not a JSON CRM API. Integrations are thin: optional `customer_id` → `rateb_customers`, website/portal lead intake. **No** Sales order, Accounting GL, or Inventory ownership coupling.

Offline V2 has **no CRM BusinessModule** yet. Offline V1 (17B) wraps the same online services (flags OFF) — frozen reference only; do **not** lift V1 adapters into V2.

Offline V2 CRM BM must:

- Own **CRM documents only** (`crm.*` entities).
- Depend on **identity** (mandatory); optional **sales** / **accounting** only if justified via published APIs.
- **Never** own inventory, accounting, procurement, or sales state.
- Consume other modules only via `module.identity.*` / `module.sales.*` / `module.accounting.*` / `module.inventory.*` (read-only if required).

---

## 1. Leads

| Item | Evidence |
|------|----------|
| Tables | `rateb_crm_leads`, `rateb_crm_lead_sources`, `rateb_crm_status_history` |
| Model | `CrmLead`, `CrmLeadSource`, `CrmStatusHistory` — `app/models/CrmModels.php` |
| Service | `LeadService` — `list`, `find`, `create`, `update`, `softDelete`, `boardCounts` — `app/services/CrmDomainServices.php` |
| Workflow | **Only** `CrmWorkflowService::transition()` may change `workflow_status` (`LeadService::update` unsets it) |
| Controller / views | `CrmLeadsController` — `views/company/crm/leads/{index,board,form,show}.php` |
| Routes | `GET/POST …/crm/leads*`, `…/transition`, `…/assign`, `…/notes` — `routes/modules/ops.php` |

Fields (concept): `lead_no`, title, contact fields, `crm_company_id`, `contact_id`, `customer_id`, `source_id`, `owner_user_id`, `workflow_status`, `estimated_value`, `priority`, soft `status`/`deleted_at`.

**Distinct:** CMS `rateb_cms_leads` — not CRM.

---

## 2. Opportunities

| Item | Evidence |
|------|----------|
| Table | `rateb_crm_opportunities` |
| Service | `OpportunityService` — `list`, `find`, `create`, `update`, `moveStage` |
| Status | `workflow_status` default `open`; `moveStage` sets `won`/`lost` from stage `is_won`/`is_lost` |
| Routes | `GET/POST …/crm/opportunities`, `POST …/crm/opportunities/{id}/move-stage` |

**Gap:** no dedicated opportunity show/edit/delete routes; `update()` can patch `workflow_status` directly (unlike leads).

---

## 3. Accounts (CRM companies)

| Item | Evidence |
|------|----------|
| Table | `rateb_crm_companies` (optional `customer_id`) |
| Service | `CrmCompanyService` — `list`, `find`, `create` |
| Routes / views | `GET/POST …/crm/companies` — `views/company/crm/companies/index.php` |

UI “Companies” = CRM accounts — **not** tenant `rateb_companies`.

---

## 4. Contacts

| Item | Evidence |
|------|----------|
| Table | `rateb_crm_contacts` |
| Service | `ContactService` — `list`, `create` |
| Links | `crm_company_id`, optional `customer_id` |
| Routes | `GET/POST …/crm/contacts` |

No edit/delete UI/routes in 17A.

---

## 5. Activities

| Item | Evidence |
|------|----------|
| Table | `rateb_crm_activities` |
| Types | ENUM `note` \| `follow_up` \| `other` |
| Statuses | `open` \| `done` \| `cancelled` |
| Service | `ActivityService` — `list`, `create` — `app/services/CrmActivityServices.php` |

Polymorphic `related_type`/`related_id` + FK-style `lead_id` / `opportunity_id`. **No** dedicated `/crm/activities` route (used on customer profile list).

---

## 6. Tasks

| Item | Evidence |
|------|----------|
| Table | `rateb_crm_tasks` |
| Service | `TaskService` — `list`, `create`, `complete` |
| Fields | `due_at`, `priority`, `reminder_at` (stored; **no notifier**) |
| Routes | `GET/POST …/crm/tasks`, `POST …/crm/tasks/{id}/complete` |

---

## 7. Calendar

**Not implemented as a CRM calendar.**

- Meetings: `rateb_crm_meetings` + `MeetingService` (`starts_at`/`ends_at`) — list/create at `…/crm/meetings`.
- Calls: `rateb_crm_calls` + `CallService` — service exists; **no** dedicated UI route in ops map.
- No `/crm/calendar`, ICS, or calendar sync.
- Other ERP calendars (MFG, EAM, eproc, website appointments) are **out of scope**.

---

## 8. Pipeline

| Item | Evidence |
|------|----------|
| Tables | `rateb_crm_pipelines`, `rateb_crm_pipeline_stages` |
| Service | `PipelineService` — `listPipelines`, `defaultPipeline`, `stagesFor`, `createPipeline`, `board` |
| Default stages | `qualification` (10%), `proposal` (40%), `negotiation` (70%), `won` (100%, is_won), `lost` (is_lost) |
| Routes | `GET/POST …/crm/pipeline`, move-stage on opportunities |

Lead “board” is **workflow_status kanban** (`LeadService::boardCounts`) — separate from opportunity pipeline board.

---

## 9. Campaigns

| Item | Evidence |
|------|----------|
| Table | `rateb_crm_campaigns` |
| Types | `email` \| `call` \| `event` \| `social` \| `other` |
| Statuses | `draft` \| `active` \| `paused` \| `completed` \| `cancelled` |
| Service | `CampaignService` — `list`, `create` |
| Routes | `GET/POST …/crm/campaigns` |

**Distinct:** CMS newsletter campaigns. No send engine / member list in 17A.

---

## 10. Follow-up

**No first-class Follow-up entity.**

- CRM: `rateb_crm_activities.activity_type = 'follow_up'` via `ActivityService::create`.
- Supplier follow-up (`SupplierCommService` / `rateb_supplier_comms`) is **not** CRM.

---

## 11. Customer timeline

| Item | Evidence |
|------|----------|
| Table | `rateb_crm_timeline` (append-only) |
| Service | `CrmTimelineService` — `record`, `listForLead`, `listForCustomer`, `listRecent` |
| UI | Lead show + dashboard recent; `GET …/crm/customers/{id}` → `CrmCustomerProfileController` |

Event types include: `lead_created`, `lead_updated`, `workflow`, `opportunity_*`, `note`, `activity`, `task`, `task_done`, `assignment`, meeting/call creates.

**Gap:** profile timeline by `customer_id` does not fully join `rateb_customers` master; activity list on that page is not strictly customer-filtered.

---

## 12. Permissions

Seeded in `migrations/183_crm_platform_enterprise.sql` → `rateb_permissions`:

| Slug | Intent |
|------|--------|
| `crm.view` | view |
| `crm.create` | create |
| `crm.update` | update |
| `crm.delete` | soft-delete |
| `crm.assign` | assignments |
| `crm.pipeline` | pipelines/stages |
| `crm.activities` | meetings/calls/tasks |
| `crm.campaign` | campaigns |
| `crm.admin` | admin bundle |
| `crm.manage` | all |

Config: `config/permissions-system.php`, `permission-labels-*.php`, `module-permissions.php` (`crm` → `crm.manage`), `entity-permissions.php`.  
Route gate: `rateb_erp_mw('crm', '<perm>', 'crm')`.

---

## 13. APIs

**No dedicated online REST CRM API** under `api/`.

Surface is **HTML form POST** on company app routes (`rateb_app_route('crm/…')`).

Inbound service integrations:

- Website forms → `LeadService::create` (`crm_lead_id` on submissions)
- Portal requests → `LeadService::create`

Offline V1 delta paths (not Online CRM API): `/api/v1/offline/delta/crm_*_directory`.

Contact Center API (`ratib-contact-center/public/api/v1/crm.php`) — **out of ERP CRM scope**.

---

## 14. Database

**Primary migration:** `migrations/183_crm_platform_enterprise.sql`

| Table |
|-------|
| `rateb_crm_lead_sources` |
| `rateb_crm_tags` |
| `rateb_crm_pipelines` |
| `rateb_crm_pipeline_stages` |
| `rateb_crm_companies` |
| `rateb_crm_contacts` |
| `rateb_crm_leads` |
| `rateb_crm_opportunities` |
| `rateb_crm_campaigns` |
| `rateb_crm_activities` |
| `rateb_crm_meetings` |
| `rateb_crm_calls` |
| `rateb_crm_tasks` |
| `rateb_crm_notes` |
| `rateb_crm_timeline` |
| `rateb_crm_assignments` |
| `rateb_crm_entity_tags` |
| `rateb_crm_status_history` |

Website bridge columns (not CRM-owned): `crm_enabled` / `crm_lead_id` on website forms, submissions, portal requests.

---

## 15. Services

| File | Classes |
|------|---------|
| `app/services/CrmSupport.php` | Tenant, UUID, numbering (`LD-`/`OP-`), `assertLead` |
| `app/services/CrmWorkflowService.php` | Lead status machine + history + audit |
| `app/services/CrmTimelineService.php` | Timeline append/list |
| `app/services/CrmDomainServices.php` | Lead, Opportunity, Pipeline, Company, Contact, Campaign, Note, Assignment, LeadSource, Tag |
| `app/services/CrmActivityServices.php` | Activity, Meeting, Call, Task |
| `app/controllers/Company/CrmControllers.php` | Thin MVC controllers |
| `app/models/CrmModels.php` | All `Crm*` models |

Tests: `tests/crm/CrmPhase17ATest.php`, `tests/crm/run-crm-phase17a-tests.php`.  
Doc: `docs/PHASE_17A_CRM_ONLINE.md`.

---

## 16. Workflow

**Lead machine** (`CrmWorkflowService`):

```
new → contacted → qualified → proposal → won|lost → archived
```

Allowed edges:

| From | To |
|------|-----|
| `new` | contacted, qualified, archived |
| `contacted` | qualified, proposal, lost, archived |
| `qualified` | proposal, won, lost, archived |
| `proposal` | won, lost, qualified, archived |
| `won` | archived |
| `lost` | archived, new |
| `archived` | ∅ |

Side effects: update lead; insert `rateb_crm_status_history`; timeline `workflow`; optional `AuditService::log('crm.workflow', …)`; archive sets `status=archived`.

**Opportunity:** stage board + soft `workflow_status` `open|won|lost` (not the lead state machine).

---

## 17. Reports

**None** for CRM (no funnel, conversion, campaign ROI, owner workload reports).

Dashboard = recent leads + board counts + recent timeline (`CrmDashboardController`).

---

## 18. Notifications

**None for CRM.**

- `task.reminder_at` stored, unused.
- `CmsLeadNotificationService` = CMS leads only.
- Supplier follow-up reminders = not CRM.

---

## 19. Integrations

| System | Behavior |
|--------|----------|
| **Identity / tenant** | `TenantContext::companyId()`, session user/branch; RBAC `crm.*` |
| **Accounting / customers** | Optional nullable `customer_id` on companies/contacts/leads/opps/activities; **no** AR/GL posting |
| **Sales** | **No** quote/order/invoice bridge from opportunities |
| **Inventory** | **None** |
| **Website / portal** | Forms & portal → `LeadService::create` |
| **CMS** | Explicitly separate (`rateb_cms_leads`) |
| **Contact Center** | Separate product (`rcc_*`) — parallel CRM |
| **Audit** | `AuditService` on workflow |

### Offline V2 published APIs relevant to optional deps

| Module | Relevant published APIs (read/link only for CRM) |
|--------|--------------------------------------------------|
| Identity | `session`, `claims`, `rbac`, `unlock` |
| Sales | `upsertCustomer`, `listSalesOrders`, quote/order lifecycle (optional link on won) |
| Accounting | reports/posting — **CRM must not post GL**; optional customer link only |
| Inventory | read-only if ever needed — CRM must never mutate stock |

---

## 20. Sync boundaries

### Online → Offline V1 (Phase 17B) — exists, flags default OFF

Doc: `offline/docs/PHASE_17B_CRM_OFFLINE.md`

| Piece | Path |
|-------|------|
| Replay | `offline/server/Services/CrmOfflineReplayService.php` |
| Guard | `CrmOfflineTenantGuard.php` |
| Master data | `CrmOfflineMasterDataDirectoryService.php` |
| Client | `offline/client/adapters/crm-adapter.js` |
| Flags | `offline.crm`, `.leads`, `.workflow`, `.activities`, `.masterdata` |

Queue actions (V1): `lead.create/update`, `workflow.transition`, `opportunity.create`, meeting/call/task/note/assignment/campaign/contact/company.create.

**Not offline (V1):** delete, payments, approvals, email/SMS, attachments, gov APIs.

### Offline V2 implication

| May sync | Must NOT sync |
|----------|---------------|
| Lead create/update drafts | Passwords / tokens / session |
| Workflow transitions + status history | Binary attachments |
| Opportunity create / stage moves | Mass email send results |
| Notes / tasks / meetings / calls / assignments | Computed dashboards / board counts as SoT |
| Campaign create metadata | Inventory balances / GL balances |
| Directory: sources, stages, tags, CRM accounts | CMS leads / RCC CRM rows |
| Timeline append events | Credential material |

---

## Full route map (Online)

| Method | Path | Perm |
|--------|------|------|
| GET | `crm` | module `crm` |
| GET | `crm/leads`, `crm/leads/board` | view |
| GET/POST | `crm/leads/create`, `crm/leads` | `crm.create` |
| GET | `crm/leads/{id}` | view |
| GET/POST | `crm/leads/{id}/edit`, `{id}` | `crm.update` |
| POST | `crm/leads/{id}/delete` | `crm.delete` |
| POST | `crm/leads/{id}/transition` | `crm.update` |
| POST | `crm/leads/{id}/assign` | `crm.assign` |
| POST | `crm/leads/{id}/notes` | `crm.update` |
| GET/POST | `crm/pipeline` | `crm.pipeline` |
| POST | `crm/opportunities/{id}/move-stage` | `crm.pipeline` |
| GET/POST | `crm/opportunities` (+ create) | view / `crm.create` |
| GET/POST | `crm/meetings` | `crm.activities` |
| GET/POST | `crm/tasks`, `…/complete` | `crm.activities` |
| GET/POST | `crm/campaigns` | `crm.campaign` |
| GET/POST | `crm/contacts` | view / create |
| GET/POST | `crm/companies` | view / create |
| GET | `crm/customers/{id}` | view |

**Missing UI despite services:** calls UI, activities CRUD UI, lead sources/tags admin, opportunity update/delete, calendar.

---

## 23–26. Required BusinessModule surface (for future impl — not this phase)

### Suggested entity prefix

`crm.*` — e.g. `crm.lead`, `crm.account`, `crm.contact`, `crm.opportunity`, `crm.pipeline`, `crm.stage`, `crm.campaign`, `crm.activity`, `crm.meeting`, `crm.call`, `crm.task`, `crm.note`, `crm.assignment`, `crm.timeline`, `crm.status_history`, `crm.tag`, `crm.lead_source`

### Suggested published APIs (`module.crm.*`)

| API | Purpose |
|-----|---------|
| `upsertLead` / `listLeads` / `getLead` | Lead CRUD drafts |
| `transitionLead` | Sole workflow writer |
| `upsertAccount` / `upsertContact` | CRM account + contact |
| `createOpportunity` / `moveOpportunityStage` | Pipeline |
| `createTask` / `completeTask` | Tasks |
| `createMeeting` / `createCall` / `createActivity` | Activities |
| `createCampaign` | Campaign metadata |
| `assignOwner` | Assignments |
| `addNote` | Notes |
| `listTimeline` | Timeline query |
| `listPipelines` / `upsertPipeline` | Pipeline directory |
| `getDiagnostics` / `runSelfTest` | Health |

### Suggested DTOs

- `LeadDraftDTO` — title, contacts, source, estimated_value, priority, customer_link?
- `WorkflowTransitionDTO` — lead_id, to_status, reason, client_idempotency_key
- `OpportunityDTO` — pipeline_id, stage_id, amount, currency, lead_id?
- `ActivityDTO` / `TaskDTO` / `MeetingDTO` — related_type/id, due_at, assignee
- `TimelineEventDTO` — entity refs, event_type, payload_json
- `CustomerLinkDTO` — optional sales/accounting customer id (link only)

### Suggested events

| Event | When |
|-------|------|
| `crm:ready` | Module activate |
| `crm:lead_created` | Lead create |
| `crm:lead_transitioned` | WorkflowPort success |
| `crm:opportunity_stage_changed` | Pipeline move |
| `crm:task_completed` | Task complete |
| `crm:timeline_recorded` | Timeline append |

### Suggested permissions (mirror online)

`crm.view` · `crm.create` · `crm.update` · `crm.delete` · `crm.assign` · `crm.pipeline` · `crm.activities` · `crm.campaign` · `crm.manage`

---

## Reusable components

- Entity vocabulary: Lead, Account (CRM company), Contact, Opportunity, Pipeline/Stage, Campaign, Activity/Meeting/Call/Task, Note, Assignment, Tag, Timeline, StatusHistory  
- Lead status machine + **sole transition authority**  
- Pipeline stages with probability / won-lost flags  
- Soft-delete + `public_uuid` + company/branch scoping  
- Timeline as append-only projection of mutations  
- Optional link to customer master (`customer_id`) without owning AR  
- Permission matrix `crm.*`  
- Numbering concepts `LD-*` / `OP-*`  
- Offline-replayable draft ops listed in 17A doc  

---

## Non-reusable components

- PHP `Model` + `CrmSupport` Session/TenantContext  
- Controllers / views / CSRF form posts  
- `rateb_erp_mw` / Bootstrap require lists  
- Direct SQL string queries in services  
- Website/portal PHP bridges  
- Offline V1 queue/adapter/flags/SDK  
- Contact Center `rcc_*` CRM  
- CMS leads / newsletter  
- Hardcoded UI boards as sync SoT  

---

## Risks

| ID | Severity | Risk |
|----|----------|------|
| R1 | High | Dual CRM worlds: ERP `rateb_crm_*` vs RCC `rcc_*` vs CMS leads — identity confusion |
| R2 | High | `customer_id` optional without CustomerLinkPort / lead→customer conversion |
| R3 | High | No Sales/Accounting ports — won opportunity does not create quote/invoice/AR |
| R4 | High | Opportunity `workflow_status` mutable outside stage machine |
| R5 | Medium | No CRM calendar / reminders / notifications |
| R6 | Medium | Incomplete CRUD (calls, tags, sources, opp edit, activities UI) |
| R7 | Medium | Ops sidebar may exclude CRM — discoverability / test drift |
| R8 | Medium | Customer profile timeline not fully joined/filtered to customer master |
| R9 | Medium | Multi-class god files (`CrmDomainServices.php`) — hard to port as clean BM ports |
| R10 | Medium | Lead numbering via count-style generation — race / non-idempotent under offline replay |
| R11 | Low | Campaigns are metadata-only (no execution channel) |
| R12 | Info | Offline V1 wraps online services — V2 must not inherit V1 queue coupling |

---

## Missing abstractions

1. **CrmWritePort** — create/update lead, note, task, meeting, opportunity (idempotent client keys)  
2. **CrmWorkflowPort** — sole lead transition authority  
3. **PipelinePort** — stages + move opportunity  
4. **TimelinePort** — append + query by lead/customer  
5. **CustomerLinkPort** — resolve/link sales/accounting customer without owning financials  
6. **DirectoryPort** — sources, stages, tags, CRM accounts (read)  
7. **Domain events** — `crm:lead_transitioned`, `crm:opportunity_stage_changed` for Sales consumers  
8. **NotificationPort** — task due / follow-up (optional)  
9. Clear rule: CRM never writes inventory or GL  
10. Optional Sales bridge: on `won` emit event → Sales may create quotation (CRM does not call Accounting)

---

## Recommended CRM BusinessModule implementation plan

1. **Charter** CRM BM — docs only; mandatory dep `identity >= 1.0.0`; optional `sales` only if CustomerLink / won→quote is in scope; do **not** depend on inventory for ownership.  
2. Local `crm.*` entity storage — never `inv.*` / `acct.*` / `sales.*` / `proc.*` / `identity.*` SQL.  
3. Implement **CrmWorkflowPort** + **TimelinePort** first.  
4. Implement Lead / Account / Contact / Opportunity / Pipeline / Task / Meeting / Campaign drafts.  
5. Auth/RBAC via `module.identity.*` only.  
6. Optional: subscribe/publish events toward Sales on `won` — never mutate sales/accounting stores directly.  
7. Inventory: **no writes**; read-only only if a future justified use exists.  
8. Sync: CRM business events only.  
9. Self-tests + host wiring + evidence pack.  
10. **STOP** before next ERP module.

---

## Adjacent systems (exclude from Online ERP CRM BM)

| System | Location |
|--------|----------|
| Offline V1 CRM | `rateb-erp/offline/**` + `PHASE_17B_CRM_OFFLINE.md` |
| Offline V2 | No CRM BM yet |
| CMS leads | `rateb_cms_leads`, admin CMS routes |
| Contact Center CRM | `ratib-contact-center/**` |
| Supplier follow-up | `SupplierCommService` |

---

## Architecture conflict check

No Platform or existing BusinessModule modification is required for this **audit**.  
If a future implementation requires changing Identity / Inventory / Procurement / Sales / Accounting / Platform — **STOP** and raise Architecture Conflict.

---

## Phase boundary

**Phase 15 CRM Audit: COMPLETE**  
**Do NOT implement CRM BusinessModule in this phase.**  
**STOP.**
