# CRM Permissions Matrix (Phase 10)

| Slug | Purpose | Typical grant |
|------|---------|---------------|
| crm.view / create / update / delete / assign | Core CRM | company roles |
| crm.pipeline* / crm.activities* / crm.campaign | Ops | sales |
| crm.reports.view / crm.export.manage | Reports + CSV | managers |
| crm.forecast.* / crm.revenue.* | Forecast / revenue intel | RevOps |
| crm.governance.view / manage | Data quality + settings | admins |
| crm.workflow.governance | Stage rules | admins |
| crm.revops.view | Command center read | managers |
| **crm.revops.run** | Run RevOps automation (write) | admins |
| crm.cockpit.view / crm.insights.view | Executive read | exec |
| **crm.insights.manage** | Dismiss/persist insights | admins |
| crm.search.view | Unified search | sales |
| crm.reporting.center | Saved dashboards / schedules | managers |
| crm.predictive.manage | Predictive rules | admins |
| crm.merge.manage | Duplicate merge | admins |
| crm.intelligence.advanced | Intelligence layer | managers |
| crm.admin / crm.manage | Full CRM | company-full-access |

Seeded to `company-full-access` + `super-admin` via migrations 233–239.
