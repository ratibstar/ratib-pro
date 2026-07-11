# Phase 21A — Enterprise Procurement & Supplier Collaboration Platform (ONLINE)

**Status:** Implemented (ONLINE foundation layer)  
**Baseline:** Enterprise Baseline **v1.2** — **NOT modified**  
**Offline Foundation:** **v1.1** — **NOT modified**  
**Offline:** Do NOT implement Offline EPROC. Existing Procurement Offline (PR/PO/RFQ drafts) untouched.  
**Migration:** `migrations/187_procurement_enterprise_platform.sql`

## Executive Summary

Phase 21A adds a **tenant-scoped Enterprise Procurement Platform (EPROC)** on additive `rateb_eproc_*` tables for supplier collaboration, qualification, scorecards, tenders, contracts, portal invites, calendar, and spend snapshots. It does **not** replace legacy PR/PO/RFQ/GRN (`ProcurementService`, `/purchase-requests`, `/purchase-orders`, `/rfq`, `/suppliers`). UI routes live under `/eproc/*` guarded by `rateb_erp_mw('procurement', …)`.

## Repository Audit (pre-21A)

| Area | Status |
|------|--------|
| Legacy PR / PO / RFQ / GRN / suppliers | **Preserved** (untouched) |
| `ProcurementService` + Offline Procurement | **Preserved** |
| Enterprise supplier portal / scorecards / SLA / qualification / eproc tenders | **Missing → created** (`rateb_eproc_*`) |
| Offline EPROC | Deferred to future phase |
| Offline Foundation / Queue / Replay / SDK | **Frozen — not modified** |

## Architecture

```
Controllers (thin, company/eproc)
  → Domain services (Supplier*, EnterpriseTender*, …)
  → ProcurementWorkflowService ONLY for workflow_status
  → Models (Eproc* → rateb_eproc_*)
  → Database
```

Legacy ops continue via `ProcurementService`. EAP integration uses `ProcurementApprovalLinkService` (optional `ApprovalRequestService`). Document binaries ONLINE ONLY (meta table only). Spend summary reuses `ErpAnalyticsService::procurementDashboard` when available.

## Workflow

**Only via `ProcurementWorkflowService`:**

- Supplier profile: `draft → qualified → active → suspended → blacklisted → archived`
- Tender: `draft → published → bidding → evaluation → awarded → closed → cancelled → archived`
- Contract: `draft → negotiation → active → expired → renewed → terminated → archived`
- Qualification: `draft → submitted → under_review → approved → rejected → archived`
- Collaboration: `open → in_progress → resolved → closed → archived`

## Offline readiness (for later)

| Operation | Service | Replay-ready | Notes |
|-----------|---------|--------------|-------|
| Category / profile / contact CRUD | `SupplierCategoryService`, `SupplierProfileService`, … | YES | Draft/metadata |
| Workflow transition | `ProcurementWorkflowService` | YES | Must call service |
| Scorecard / SLA / risk / qualification | domain services | YES | Tenant-scoped |
| Tender / bid / comparison / contract | `EnterpriseTenderService`, … | YES | |
| Calendar / comment / assignment / tag | services | YES | |
| Portal invite create | `SupplierPortalService` | PARTIAL | Meta yes; email send **NO** |
| Approval link meta | `ProcurementApprovalLinkService` | PARTIAL | Meta yes; EAP notify **NO** |
| Document meta | `SupplierDocumentMetaService` | PARTIAL | Meta yes; binary **NO** |
| Binary upload / email / SMS / payments | — | **NO** | **ONLINE ONLY** |
| Legacy PR/PO receive / stock / GL post | `ProcurementService` | N/A | Existing offline module; out of EPROC scope |

## RBAC

| Slug | Role |
|------|------|
| `procurement.view` | view EPROC |
| `procurement.create` | create |
| `procurement.update` | update |
| `procurement.submit` | workflow transitions |
| `procurement.supplier` | supplier collaboration |
| `procurement.tender` | tenders / bids |
| `procurement.contract` | contracts |
| `procurement.portal` | portal invites |
| `procurement.admin` | admin |
| `procurement.manage` | all (implies above + oversight) |

## Files Created

- `migrations/187_procurement_enterprise_platform.sql`
- `app/models/EprocModels.php`
- `app/services/ProcurementEnterpriseSupport.php`
- `app/services/ProcurementWorkflowService.php`
- `app/services/ProcurementTimelineService.php`
- `app/services/ProcurementEnterpriseDomainServices.php`
- `app/controllers/Company/ProcurementEnterpriseControllers.php`
- `views/company/eproc/**`
- `tests/procurement/*`
- `docs/PHASE_21A_PROCUREMENT_ENTERPRISE.md`

## Files Modified (additive)

- `routes/company.php`, `app/Core/Bootstrap.php`
- `config/permissions-system.php`, `config/entity-permissions.php`
- `config/permission-labels-{en,ar}.php`, `config/lang/{en,ar}.php`
- `views/partials/sidebar-ops-nav.php`

## Tests

```bash
php tests/procurement/run-procurement-phase21a-tests.php
```

## Production readiness

1. Run migration `187_procurement_enterprise_platform.sql`
2. Ensure plan module `procurement` is enabled
3. Grant `procurement.view` / `procurement.manage` (seeded to `company-full-access` / `super-admin`)
4. Use `/eproc` for the enterprise platform; legacy `/purchase-*` remains operational
5. Future offline EPROC must default flags OFF — Baseline untouched

## Success criteria

- ONLINE EPROC domain complete and multi-tenant
- Workflow only through `ProcurementWorkflowService`
- Legacy procurement + Offline Foundation unchanged
- Gate tests CLEAR
