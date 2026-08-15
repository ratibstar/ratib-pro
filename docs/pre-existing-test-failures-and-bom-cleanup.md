# Pre-existing Test Failures & BOM Cleanup

> **Status:** classification only — **no code is fixed in this task**. This is a triage
> report opened as a follow-up to the dev-environment setup PR (#4). All items below were
> verified as **PRE-EXISTING on `origin/main`** (reproduced on a clean `main` worktree),
> i.e. they are independent of PR #4 (which added only `AGENTS.md` + `composer.lock`).

## How this was measured

- Ran the full project test battery: **96 test runners** (worker-platform `TestRunner`,
  POS V2 master, CRM master certification, all `rateb-erp/tests/**/run-*.php`, 33
  `rateb-erp/offline/tests/run-*.php`, and `rateb-platform-catalog/tests/run.php`).
- Result: **66 PASS / 30 FAIL**. Gating CI suite (POS V2) = 16/16 PASS; CRM master =
  PASS; worker-platform = 15/15 PASS.
- The 30 failing suites fail **identically** on a clean `origin/main` checkout.
- DB used: local MariaDB (`admin_rateb-erp`), migrations fully applied (265 SQL files,
  series to `257_*`).

## Priority legend

| Priority | Meaning |
|---|---|
| P1 | Fatal error (suite aborts) — fix first |
| P2 | Multiple failing assertions in a module suite |
| P3 | Low-impact / convention / host-dependent assertion |
| P4 | Working-as-designed / informational (no fix needed) |

## Failure groups (30 suites)

### Group C — Fatal: tenant-scoped create mismatch (P1) — 2 suites
Runtime fatal `RuntimeException: Tenant mismatch for tenant-scoped create.`
(`app/Core/Model.php:331`) via `OfflineQueueService::enqueueBatch()` in the test's seeded
tenant context.
- `rateb-erp/offline/tests/run-hr-offline-tests.php`
- `rateb-erp/offline/tests/run-inventory-offline-tests.php`

### Group D — Test references a missing source file (P1) — 1 suite
`MoyasarGatewayTest::bootstrap()` requires `rateb-erp/app/Services/Logger.php`, which does
**not exist** in the repo → `require_once` fatal. The core payment tests in the same
runner **pass** ("ALL PAYMENT TESTS PASSED") before the fatal. Likely a wrong include
path or a missing/renamed test helper.
- `rateb-erp/tests/Payment/run-payment-gateway-tests.php`

### Group A — Route / sidebar / translation registration assertions (P2) — 13 suites
Assertions such as `routes registered under <module>`, `sidebar + EN/AR translations`, and
`offline modules registry ...` fail. These assert the HTTP route/sidebar registries which
are populated differently under a CLI run (no HTTP host / platform-oversight context) than
under a web request. Consistent `1–3 / ~13` failures per suite.
- `rateb-erp/tests/accounting/run-accounting-phase16a-tests.php` (3/12)
- `rateb-erp/tests/approval/run-approval-phase20a-tests.php` (1/13)
- `rateb-erp/tests/assets/run-assets-phase19a-tests.php` (2/13)
- `rateb-erp/tests/bi/run-business-intelligence-phase27a-tests.php` (3/13)
- `rateb-erp/tests/crm/run-crm-phase17a-tests.php` (1/12)
- `rateb-erp/tests/documents/run-document-management-phase26a-tests.php` (2/14)
- `rateb-erp/tests/hr/run-hr-phase23a-tests.php` (2/13)
- `rateb-erp/tests/manufacturing/run-manufacturing-phase22a-tests.php` (2/13)
- `rateb-erp/tests/payroll/run-payroll-phase24a-tests.php` (2/13)
- `rateb-erp/tests/procurement/run-procurement-phase21a-tests.php` (2/13)
- `rateb-erp/tests/projects/run-projects-phase18a-tests.php` (1/13)
- `rateb-erp/tests/quality/run-quality-phase25a-tests.php` (2/13)
- `rateb-erp/tests/recruitment/run-recruitment-phase15a-tests.php` (2/11)

### Group B — Offline shell / SW / identity / RBAC / master-data gates (P2) — 10 suites
Assertions on the browser offline shell, service-worker coexistence (POS vs ERP SW),
feature-flag gating, admin offline-devices page, migration-file presence, and RBAC policy.
- `rateb-erp/offline/tests/run-enterprise-baseline-v12-tests.php` (1/10)
- `rateb-erp/offline/tests/run-erp-offline-identity-tests.php` (1/18)
- `rateb-erp/offline/tests/run-erp-offline-identity-p2-tests.php` (1/24)
- `rateb-erp/offline/tests/run-erp-offline-master-data-tests.php` (1/18)
- `rateb-erp/offline/tests/run-erp-offline-phase131-tests.php` (4/13)
- `rateb-erp/offline/tests/run-erp-offline-phase14-tests.php` (3/14)
- `rateb-erp/offline/tests/run-erp-offline-rbac-tests.php` (1/18)
- `rateb-erp/offline/tests/run-erp-shell-offline-tests.php` (4/18)
- `rateb-erp/offline/tests/run-offline-foundation-tests.php` (1/26)
- `rateb-erp/offline/tests/run-offline-monitoring-tests.php` (2/18)

### Group E — Code convention / security-shape assertions (P3) — 2 suites
- `rateb-erp/tests/hr/run-ess-phase-c-hardening-tests.php` — `ESS employee request APIs avoid SELECT *`
- `rateb-erp/tests/hr/run-hr-phase-c-security-tests.php` — `CrudController exposes afterSuccessfulStore/Update hooks`

### Group F — Host-dependent unit test (P3) — 1 suite
`rateb-platform-catalog/tests/run.php`: `ErpSessionFileReader decodes ERP session file`
depends on `$_SERVER['HTTP_HOST']` (unset under CLI), so `resolvePlatformUserId()` returns
null. 173 pass / 1 fail / 21 skip. Not part of gating CI.

### Group G — Staging soak safety refusal (P4 — working as designed) — 1 suite
`rateb-erp/offline/tests/run-phase462-staging-soak.php` intentionally **refuses to run
against a non-staging DB** (`FAIL [Critical]: Refuse non-staging DB: admin_rateb-erp`).
This is a safety guard, not a defect. Run only on the staging host/DB it is designed for.

## Summary counts

| Group | Cause | Priority | Suites |
|---|---|---|---|
| C | Fatal: tenant-scoped create mismatch | P1 | 2 |
| D | Test requires missing `app/Services/Logger.php` | P1 | 1 |
| A | Route/sidebar/translation registration (CLI context) | P2 | 13 |
| B | Offline shell/SW/identity/RBAC/master-data gates | P2 | 10 |
| E | Code convention / security-shape assertions | P3 | 2 |
| F | Host-dependent (`HTTP_HOST`) catalog unit test | P3 | 1 |
| G | Staging soak safety refusal (by design) | P4 | 1 |
| | **Total** | | **30** |

## BOM Cleanup (separate concern) — 14 files

These view templates begin with a UTF-8 BOM (`EF BB BF`) placed **before**
`declare(strict_types=1)`, which makes `php -l` fail (`strict_types declaration must be the
very first statement`). Fix = strip the leading BOM (no logic change). All are under
`rateb-erp/views/company/bi/`:

- `rateb-erp/views/company/bi/alerts/index.php`
- `rateb-erp/views/company/bi/analytics/index.php`
- `rateb-erp/views/company/bi/datasets/index.php`
- `rateb-erp/views/company/bi/exports/index.php`
- `rateb-erp/views/company/bi/forecasts/index.php`
- `rateb-erp/views/company/bi/kpis/index.php`
- `rateb-erp/views/company/bi/kpis/show.php`
- `rateb-erp/views/company/bi/reports/index.php`
- `rateb-erp/views/company/bi/reports/show.php`
- `rateb-erp/views/company/bi/schedules/index.php`
- `rateb-erp/views/company/bi/scopes/index.php`
- `rateb-erp/views/company/bi/timeline/index.php`
- `rateb-erp/views/company/bi/trends/index.php`
- `rateb-erp/views/company/bi/widgets/index.php`

## Suggested remediation order (future work — not done here)

1. **P1 Group D** — restore/point the correct `Logger` include in `MoyasarGatewayTest` (test-only, trivial).
2. **P1 Group C** — set the correct tenant context in the offline HR/inventory queue tests (or the queue create call).
3. **BOM (14 files)** — strip the leading BOM so `php -l` passes (mechanical, no behavior change).
4. **P2 Groups A/B** — decide whether these route/sidebar/offline-shell assertions should run under a web/bootstrapped context or be adjusted for CLI; align the test harness accordingly.
5. **P3 Groups E/F** — address the convention assertions and make the catalog test set `HTTP_HOST` in its bootstrap.
6. **P4 Group G** — leave as-is; document that it runs on staging only.
