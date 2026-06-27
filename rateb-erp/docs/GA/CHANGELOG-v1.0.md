# RATIB ERP v1.0 — Changelog

**Release:** 1.0.0  
**GA date:** 2026-06-27  
**Production:** https://rateb.sa

This changelog summarizes everything completed during the RATIB ERP v1.0 GA certification project.

---

## Security

- GA-SEC-C01: Secured `erp-health.php` — token gate, no anonymous privilege escalation
- GA-SEC-H01: Document barcode tenant isolation and auth gate
- GA-SEC-H02/H03: CMS XSS and SVG upload hardening
- GA-SEC-H04: API rate limiting (`ApiRateLimiter`)
- GA-SEC-H05: Security headers — CSP, HSTS, X-Frame-Options, X-Content-Type-Options
- `HealthProbeAuth` — unified probe authentication
- Production security cert: **0 Critical, 0 High, 0 Medium**

---

## Companies

- Multi-company admin shell operational
- Company create/activate flows validated on production
- `BillingService::ensureInitialSubscription()` — auto-provision subscription on company activation (Blocker B fix)
- Company portal login validated (Tests 92–94)

---

## Branches

- Branch management at `/admin/ops/branches`
- `BranchAccessService` and `rateb_user_branches` tenant isolation
- Inter-branch transfer service and `rateb_branch_transfers` table
- HQ and branch manager roles defined
- Phase 6 inter-branch execution (migration 135)

---

## Users

- Super-admin accounts preserved: `admin@rateb.sa`, `ahmedashrafabdalmonem77@gmail.com`
- Login activity monitoring at `/admin/login-activity`
- Remember-me cookie support validated
- Restricted user isolation verified (Issue 3 regression)

---

## Roles

- RBAC tables preserved in reset dry-run: `rateb_roles`, `rateb_role_permissions`
- Roles admin at `/admin/roles` — HTTP 200 validated
- HQ and branch manager role definitions in enterprise suite

---

## Permissions

- `rateb_permissions` preserved in reset dry-run
- Permissions admin at `/admin/permissions` — HTTP 200 validated
- Entity and module permission maps configured

---

## Subscriptions

- Plans and subscriptions admin modules operational
- Auto-provision trial subscription on company activation
- `PlanLimitService::hasValidSubscription()` gate for portal access
- Subscription regression validated (subscription id 6, trial status)

---

## Billing

- Invoices, payments, plans admin routes validated
- Billing read-only QA Tests 23–26 PASS
- N-Genius integration lives outside ERP core (main site / config)

---

## CRM

- Customers module at `/admin/customers` — HTTP 200 validated
- Customer-facing portal at `/site/portal`

---

## HR

- HR modules at `/admin/hr/*` (corrected from `/admin/ops/hr/*` in QA runner)
- Departments, employees, payroll structures, attendance validated
- HR QA Tests 57–62 PASS

---

## Accounting

- Chart of accounts, journal entries, fiscal periods operational
- Inter-branch GL accounts 1350/2150 seeded
- `BranchFinancialReportingService`, `ConsolidationEliminationService` validated
- Trial balance and elimination symmetry PASS in enterprise suite

---

## Procurement

- RFQ, purchase orders, supplier evaluation modules validated
- Approval workflows and instances operational
- Procurement QA Tests 37–42, 43–56 PASS

---

## Inventory

- Warehouses, inventory batches, stock movements validated
- Inventory audits and low-stock alert hooks in cron
- Warehouse transfers table ready

---

## Reports

- Reports at `/admin/reports` — HTTP 200 validated
- Executive dashboard at `/admin/executive-dashboard`
- KPI at `/admin/ops/reports/kpi`
- Branch financial reporting services available

---

## Notifications

- Notifications admin at `/admin/notifications` — HTTP 200 validated
- `rateb_notification_queue` with retry support
- Email and SMS template admin modules

---

## Automation

- `AutomationControllers.php` loaded in Bootstrap (Blocker A fix)
- Automation health at `/admin/automation-health` — HTTP 200
- Queue monitor at `/admin/queue-monitor` — HTTP 200
- Cron health tracking via `rateb_cron_health`
- `erp-cron.php` every 15 minutes

---

## Monitoring

- Login activity, queue monitor, automation health — all HTTP 200
- Audit logs at `/admin/audit-logs`
- Enterprise infrastructure suite validates monitoring scripts

---

## API

- API v1 at `/rateb-erp/public/api/v1` — HTTP 200 validated
- `ApiBranchGuardService` branch-scoped token validation
- API rate limiting applied
- `rateb_api_tokens` with `branch_id` column

---

## Portal

- Company portal at `/site/portal` — login validated
- Password reset flow validated (Issue 2 regression)
- Logout redirects to marketing home (LOW observation — session cleared)

---

## Health

- `erp-health.php` returns `{"status":"ok"}` — HTTP 200
- No session impersonation paths in health probe
- Ping endpoint: PHP 8.3.31 confirmed

---

## Backup

- `bin/erp-backup.php` — nightly cron at 02:00
- Production backup certified: `erp-admin_rateb-erp-20260627-024200.sql.gz`
- Files archive: `erp-files-20260627-024201.tar.gz` (33 MB)
- Retention policy via `AutomationSettings::backupRetentionDays()`

---

## Restore

- `bin/erp-restore.php` — restore and `--verify` modes
- Restore drill: 143 tables, 1 second, enterprise 31/31 PASS
- `--verify` false negative on MariaDB 10.11 documented (LOW)

---

## QA

- Enterprise QA Tests 1–100 complete
- Tests 23–100: 76 PASS, 1 BLOCKED (tenant scope), 0 FAIL
- Regression Issues 14–17: PASS (monitoring, subscription, portal, restricted user)
- Tests 18–22: PASS (queue, automation, reports, executive dashboard, settings)
- Zero Critical, Zero High, Zero Medium defects at GA closeout

---

## Safe QA v2

- Manifest-only cleanup — no production mutation outside QA sessions
- QA-prefix objects only
- Manifest sessions: `SAFE-QA-20260627-023048`, `SAFE-QA-20260627-023740`
- Zero orphan QA objects verified via resolver

---

## Manifest Resolver

- `bin/QaManifestResolver.php` — DB lookup for QA-prefixed objects
- `public/qa-manifest-resolve.php` — HTTP resolver API (token-gated)
- `scripts/qa-manifest/SafeQaManifest.psm1` — PowerShell manifest module
- `scripts/qa-manifest/cleanup-from-manifest.ps1` — ordered manifest-only delete

---

## Enterprise validation

- Enterprise test suite: **31/31 PASS** on live production DB
- Suites: branch_isolation, financial, transfers, api_security, infrastructure
- Probe: `erp-security-cert.php?enterprise=1`

---

## Infrastructure & deployment

- Build marker: `rateb-erp-ga-security-20260626`
- Migrations through 135 applied on production
- GitHub Actions fast deploy configured
- Production reset script (`reset-production.php`) — dry-run validated, **not executed**

---

## Documentation delivered at GA

- `FINAL-GA-CERTIFICATE.md`
- `FINAL-RISK-REGISTER.md`
- `PRODUCTION-HANDOVER.md`
- `FINAL-SIGNOFF.md`
- `go-live-final-report.md`
- `go-live-backup-restore-evidence-20260627.json`
- `enterprise-qa-certification-final.md`

---

*RATIB ERP v1.0.0 — General Availability release changelog.*
