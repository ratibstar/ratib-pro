# CRM Architecture Map (Phase 10)

Official UI: Admin only (`/public/admin/*`). No `public/v2` CRM frontend.

## Layers

```
Views (company/crm/*)
  → Controllers (CrmControllers.php)
    → Domain / Intelligence / Ops Services
      → Models (CrmModels.php + Customer)
        → MySQL rateb_crm_* / rateb_customers CRM columns
```

## Core domains

| Domain | Key services |
|--------|----------------|
| Sales flow | LeadService, OpportunityService, PipelineService, CrmQuotation* |
| Activities | ActivityService, Task/Call/Meeting services |
| Intelligence | CrmOpportunityIntelligence*, CrmIntelligenceLayer*, CrmPredictiveRules* |
| RevOps | CrmRevOpsCommandCenter*, CrmEnterpriseForecast*, CrmRevenueIntelligence* |
| Governance | CrmGovernance*, CrmDataQuality*, CrmWorkflowGovernance*, CrmDuplicateMerge* |
| Automation | CrmAutomationService, CrmRevOpsAutomationService (+ Safety), RulesEngine |
| Reporting | CrmReport*, CrmReportingCenter*, CrmUnifiedSearch* |

## Hardening notes (Phase 10)

- Quality/governance GET paths use snapshots / issue counts (scan on POST).
- Automation: cooldown + run lock + notify budget via `CrmAutomationSafetyService`.
- RevOps `runAll(false)` does **not** nest legacy automation by default.
- Customer 360 is read-only unless `?refresh=1`.
- Pipeline board capped at 500 opportunities.
