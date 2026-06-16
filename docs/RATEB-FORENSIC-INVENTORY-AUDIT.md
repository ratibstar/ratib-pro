# RATEB / ratebprogram — Forensic Repository Inventory Audit

**Date:** 2026-05-24  
**Method:** Read-only filesystem scan and file content inspection  
**Scope requested:** `ratebprogram` / `ratebsrar`  
**Scope observed:** `c:\Users\انا\Desktop\ratebprogram` only — **no folder named `ratebsrar` exists** on disk (Desktop search returned 0 matches).

**Rules applied:** No code modified. Statements below cite observed paths, file counts, or verbatim patterns from files.

---

## 1. REAL FOLDER TREE OVERVIEW

### 1.1 All top-level directories (file counts via recursive scan)

| Folder | Files | Observed role (from files inside) |
|--------|------:|-----------------------------------|
| `node_modules/` | 2014 | NPM dependencies (not application source) |
| `api/` | 378 | PHP JSON/HTTP endpoints grouped by domain subfolders |
| `modules/` | 255 | Two product modules: `client-dashboard/`, `infrastructure-marketplace/` |
| `control-panel/` | 201 | Separate admin console: pages, includes, `api/control/` |
| `app/` | 159 | Namespaced PHP (`App\`): Services, Workflows, Modules, Core |
| `pages/` | 139 | PHP page scripts (agency UI, public pages, ops utilities) |
| `js/` | 136 | Front-end JavaScript |
| `Designed/` | 127 | Separate PHP app (`Designed/app/`, `views/`, `public/`, own `database/`) |
| `includes/` | 103 | Shared PHP bootstrap, auth, CMS, payments, QR login |
| `logs/` | 89 | Log files on disk |
| `config/` | 76 | `env/`, `migrations/`, `database.php`, payment config |
| `android/` | 75 | Capacitor Android project + native tracking service |
| `vendor/` | 72 | Composer PHP dependencies |
| `admin/` | 70 | Platform control center (`admin/core/*Engine.php`, dashboards) |
| `css/` | 66 | Stylesheets including `rateb-enterprise-tokens.css` |
| `archive/` | 45 | Old markdown checklists (not referenced in deploy deny list as code) |
| `public/` | 44 | Front controller, webhooks, SSE, build marker, worker-platform entry |
| `database/` | 37 | PHP migration classes + `SCHEMA.md` + SQL fragments |
| `ios/` | 34 | Capacitor iOS / Xcode project |
| `coreai/` | 30 | Standalone AI UI (`coreai/index.php`, `php/ai.php`, `js/app.js`) |
| `docs/` | 27 | Markdown documentation |
| `tap-payments/` | 24 | Standalone Tap payment pages (`pay.php`, `webhook.php`, etc.) |
| `assets/` | 23 | Static images/diagrams |
| `scripts/` | 19 | Deploy Python/shell, health-check, QR provision script |
| `paypal-checkout/` | 15 | Standalone PayPal REST checkout |
| `core/` | 12 | `Auth.php`, `Database.php`, tenant resolver |
| `mobile-app/` | 9 | PWA worker tracker (`assets/js/app.js`, `sw.js`) |
| `tests/` | 8 | Custom test runner + 6 test definition files |
| `sql/` | 6 | SQL migration files (e.g. `sql/migrations/20260522_qr_login_enterprise.sql`) |
| `tools/` | 6 | Smoke/demo PHP scripts |
| `resources/` | 5 | Sparse Blade views |
| `uploads/` | 5 | Uploaded media |
| `storage/` | 3 | Storage placeholders |
| `.vscode/` | 2 | Editor config |
| `.cursor/` | 2 | Cursor rules |
| `bootstrap/` | 1 | Single file |
| `Utils/` | 1 | Single file |
| `.github/` | 1 | `workflows/deploy.yml` |
| `cache/` | 1 | Single file |
| `routes/` | 1 | `worker_platform.php` |
| `profile/` | 1 | Single file (rewrite targets `pages/about.php`) |
| **`workflow-orchestration/`** | **0** | **Empty directory** |
| **`hr/`** | **0** | **Empty directory** |
| **`examples/`** | **0** | **Empty directory** |
| **`.well-known/`** | **0** | **Empty directory** |

**Root files (observed):** `index.php`, `.htaccess`, `favicon.php`, `control.php`, many `*.md` audit reports (~148 markdown files repo-wide per glob).

### 1.2 Largest application modules (excluding `node_modules/`, `vendor/`, `logs/`)

1. `api/` — 378 files; largest subfolder `api/accounting/` (67 files)
2. `modules/infrastructure-marketplace/` — 203 files
3. `control-panel/` — 201 files
4. `app/` — 159 files
5. `pages/` — 139 files

---

## 2. MODULE-BY-MODULE BREAKDOWN

### 2.1 `api/` (378 files)

**Subfolders by file count (observed):**

| Subfolder | Files | Example files |
|-----------|------:|-----------------|
| `accounting/` | 67 | `transactions.php`, `dashboard-data.php`, `entity-transactions.php` |
| `workers/` | 50 | `get-single.php`, `workflow-engine.php`, `worker-onboarding.php`, `bulk-*.php` |
| `admin/` | 38 | `clear_all_data.php`, `backup_system.php`, `add_user.php` |
| `core/` | 23 | `TenantDatabaseManager.php`, `api-permission-helper.php`, `Database.php` |
| `partnerships/` | 21 | `partner-portal-auth.php`, `partner-agency-account-statement.php` |
| `infrastructure-marketplace/` | 18 | `catalog.php`, `ops-queue.php`, `health.php` |
| `hr/` | 16 | `debug-query.php`, employee-related endpoints |
| `agents/` | 16 | `create.php`, `bulk-deactivate.php` |
| `help-center/` | 11 | `tutorials.php`, `categories.php` |
| `v1/` | 6 | `index.php`, `workers/index.php`, `tracking/index.php`, `workflows/index.php`, `alerts/index.php` |

**Root-level API files (22 observed):** includes `qr-login.php`, `login-barcode-pair.php`, `create-order.php`, `ngenius-health.php`, `global-ai-run.php`, `support-chat-*.php`, `visa-applications*.php`.

**Entry pattern:** Each `*.php` file is typically invoked directly via URL (e.g. `/api/accounting/transactions.php`); no single front controller for legacy `api/`.

**Dependencies (visible in requires):**
- Most endpoints: `require_once '../../includes/config.php'` or `api/core/api-permission-helper.php`
- Permission map: `api/core/module-permissions.php`
- Session bootstrap: `api/core/rateb_api_session.inc.php`

---

### 2.2 `app/` (159 files)

**Subfolders (file counts):**

| Folder | Files | Contents (observed) |
|--------|------:|----------------------|
| `Modules/` | 70 | Agency, Subscription, Payment, Ledger, Wallet, Commission, Settlement, Reporting, Admin |
| `Core/` | 21 | `Application.php`, `WorkflowEngine.php`, `Autoloader.php`, `Database.php` |
| `Services/` | 18 | `WorkerService`, `TrackingService`, `AuthorizationService`, `WebhookService`, etc. |
| `Repositories/` | 12 | Data access for app layer |
| `Listeners/` | 7 | Workflow/webhook/violation listeners |
| `Events/` | 7 | `WorkerCreated`, `ViolationDetected`, workflow lifecycle events |
| `Workflows/` | 6 | `WorkerOnboardingWorkflow.php` |
| `Controllers/` | 5 | HTTP controllers |
| `Middleware/` | 3 | `AccessMiddleware.php` |

**Entry point:** `public/worker-platform.php` loads `app/Core/Autoloader.php`, `config/worker_tracking.php`, `routes/worker_platform.php`; returns JSON.

**Module README:** `app/Modules/README.md` documents module list.

---

### 2.3 `modules/client-dashboard/` (52 files)

**Example structure:**
- `bootstrap.php` — defines `RATEB_CLIENT_DASHBOARD_ROOT`, URL helpers
- `Adapters/` — Billing, Domains, Hosting, Infrastructure, Orders
- `Actions/` — Renew, Upgrade, Suspend, RetryPayment
- `Policy/PolicyEngine.php`, `Orchestration/SnapshotOrchestrator.php`
- `Assets/js/` — client UI scripts

**UI entry:** `pages/client/*.php` (18 files) with `_auth.inc.php`, `_common-start.inc.php`  
**API entry:** `api/client-dashboard/` (5 files): `snapshot.php`, `orders.php`, `activities.php`, etc.  
**Control panel embed:** `control-panel/pages/control/client-*.php`, `client-hub.php`

---

### 2.4 `modules/infrastructure-marketplace/` (203 files)

**Example structure:**
- `bootstrap.php` — autoload `Rateb\InfrastructureMarketplace\` namespace
- `Provisioning/ProvisioningOrchestrator.php`, `Workers/InfrastructureProvisioningWorker.php`
- `Registrars/Adapters/NamecheapRegistrarAdapter.php`
- `Hosting/Adapters/CpanelWhmAdapter.php`
- `Migrations/*.sql` (e.g. `001_foundation.sql`)
- `Docs/` — runbooks (`RUNBOOK_EMERGENCY_DISABLE.md`, `RUNBOOK_WORKER_QUEUE_RECOVERY.md`, etc.)
- `Views/marketplace/index.php`, `Views/admin/dashboard.php`
- `Assets/js/infrastructure-marketplace.js`

**API entry:** `api/infrastructure-marketplace/` (18 files)  
**Control panel:** `control-panel/pages/control/infrastructure.php`

---

### 2.5 `control-panel/` (201 files)

**Entry:** `control-panel/index.php` — requires `includes/config.php`; redirects to `control/dashboard.php` if `$_SESSION['control_logged_in']` set, else `login.php`.

**Pages (55 PHP files under `control-panel/pages/`):** includes `control/agencies.php`, `control/countries.php`, `control/registration-requests.php`, `control/support-chats.php`, `control/government.php`, `control/tracking-map.php`, `control/infrastructure.php`, `control/soc-dashboard.php`.

**API (42 files under `control-panel/api/`):** includes `control/agencies.php`, `control/worker-tracking.php`, `control/government.php`, `control/events-stream.php`, `diagnostics/tenant-isolation-self-test.php`.

**Config:** `control-panel/includes/config.php` — opens `$GLOBALS['control_conn']` to `CONTROL_PANEL_DB_NAME`; can open per-agency mysqli from `control_agencies` row fields (`db_host`, `db_user`, `db_pass`, `db_name`).

---

### 2.6 `pages/` (139 files)

**Groups observed by filename:**

| Group | Files (examples) |
|-------|------------------|
| Agency ops | `dashboard.php`, `Worker.php`, `agent.php`, `subagent.php`, `accounting.php`, `hr.php`, `reports.php`, `visa.php`, `system-settings.php` |
| Auth / QR | `login.php`, `login-scan.php`, `login-badge.php`, `workforce-badge.php`, `user-login-barcode.php` |
| Partner | `partner-portal.php`, `partner-portal-login.php`, `partner-agencies.php`, `partner-cvs-control.php` |
| Public / trust | `home.php`, `about.php`, `enterprise-trust.php`, `security-compliance.php`, `architecture.php`, `procurement-legal.php`, `government-workforce-operations.php`, `enterprise-pack.php` |
| Client | `client/dashboard.php`, `client/marketplace.php`, … (18 files) |
| Ops utilities | `rateb-sync-from-github.php`, `rateb-purge-cache.php`, `rateb-fix-perms.php`, `test-config.php`, `test-error.php`, `deploy-root.php`, `tenant-test.php` |

**Routing:** `.htaccess` maps clean URLs to `pages/*.php` (e.g. `/home` → `pages/home.php`, `/{country}/login` → `pages/login.php?country_slug=…`).

---

### 2.7 `includes/` (103 PHP files)

**Functional groups (by filename prefix/path):**

| Group | Example files |
|-------|---------------|
| Config bootstrap | `config.php` (loads `config/env/load.php`, sets `$GLOBALS['conn']`) |
| Permissions | `permissions.php`, `permission_middleware.php`, `modal_permissions.php` |
| QR / barcode login | `rateb-qr-login.php`, `rateb-barcode-login-pair.php`, `rateb-barcode-login-auth.php`, `rateb-qr-workforce-identity.php`, `rateb-user-login-barcode.php` |
| Public nav/CMS | `rateb-home-public-nav-bootstrap.php`, `site-content.php`, `rateb-public-cms.php` |
| Payments | `ngenius.php`, `payment_api_bootstrap.php` |
| Multi-tenant | `bootstrap_multi_tenant.php`, `TenantLoader.php`, `control_lookup_conn.php` |
| Partner portal | `partner-portal-header.php`, `partner-portal-nav.php` |

---

### 2.8 `admin/` (70 files)

**Example files:** `control-center.php`, `tenants.php`, `create-tenant.php`, `government.php`, `government-tracking.php`, `debug-dashboard.php`, `events-stream.php`.

**Core engines (`admin/core/`):** `EventBus.php`, `GeofenceEngine.php`, `AntiSpoofEngine.php`, `WorkerThreatFusionEngine.php`, `BackupService.php`, `ControlCenterAccess.php`.

**`.htaccess` routes:** `/admin/tenants/` → `admin/tenants.php`, `/admin/government/` → `admin/government.php`.

---

### 2.9 `Designed/` (127 files) — separate tree

**Structure:** `Designed/app/` (40 files), `views/` (28), `storage/` (19), `public/` (16), `database/` (6), `routes/web.php`.

**Bridge to main repo:** `public/index.php` requires `includes/designed_bootstrap.php`; `pages/designed-launcher.php` exists in main `pages/`.

**Deploy note:** `.github/workflows/deploy.yml` and deploy scripts explicitly exclude `Designed/` from auto-upload (observed in workspace rules and deploy script comments).

---

### 2.10 Payment folders

| Folder | Entry files |
|--------|-------------|
| `tap-payments/` | `index.php`, `pay.php`, `verify.php`, `webhook.php`, `success.php`, `failed.php` |
| `paypal-checkout/` | `index.php`, `create-order.php`, `capture-order.php`, `webhook.php` |
| Main app N-Genius | `includes/ngenius.php`, `api/create-order.php`, `api/ngenius-health.php`, `config/ngenius.secrets.example.php` |

---

### 2.11 Mobile

| Folder | Role |
|--------|------|
| `mobile-app/` | PWA: `assets/js/app.js` (worker tracking UI), `sw.js` (caches `/api/worker-tracking/` requests) |
| `android/` | Capacitor wrapper; native `TrackingForegroundService` |
| `ios/` | Capacitor Xcode project |

**Documented API contract:** `docs/worker-tracking-mobile-contract.md` lists `POST /api/worker-tracking/update-location.php`, `sos.php`, `GET history.php`.

---

### 2.12 `coreai/` (30 files)

**Entry:** `coreai/index.php`  
**Backend:** `coreai/php/ai.php`, `plan.php`, `execute.php`, `project_graph.php`, `memory.php`  
**Integration hook:** `includes/global_ai_run.php`, `app/UI/GlobalAIButton.php` (referenced in prior tree listing)

---

## 3. FEATURE MAP (EVIDENCE-BASED)

### 3.1 Password / session login

| Item | Path |
|------|------|
| Login page | `pages/login.php` — queries `control_countries` / agency picklist; uses `$GLOBALS['conn']` |
| Config bootstrap | `includes/config.php` — comment lines 43–50 describe per-country DB via `$GLOBALS['conn']` |
| Session validity helper | referenced in root `index.php`: `rateb_program_session_is_valid_user()` |
| Session regenerate | `core/Auth.php` line 116 area: `session_regenerate_id(true)` on login |
| Control panel login | `control-panel/pages/login.php` sets `control_logged_in` session (used in `control-panel/index.php`) |

**Flow (from `pages/login.php` + `includes/config.php`):** HTTP → `.htaccess` rewrite → `pages/login.php` → `includes/config.php` → mysqli `$GLOBALS['conn']` → POST auth against `users` table (inferred from login page DB usage; table name appears in `core/Auth.php` column query).

---

### 3.2 QR / barcode login

| Item | Path |
|------|------|
| Documentation | `docs/QR-BARCODE-LOGIN.md` |
| Desktop login UI | `pages/login.php` + `js/login.js` |
| Pair API | `api/login-barcode-pair.php` |
| Validate/issue API | `api/qr-login.php` |
| Pair logic | `includes/rateb-barcode-login-pair.php` |
| Token/audit logic | `includes/rateb-qr-login.php` |
| Workforce identity | `includes/rateb-qr-workforce-identity.php` |
| Session completion | `includes/rateb-barcode-login-auth.php` |
| Phone scan page | `pages/login-scan.php` + `js/login-scan.js` + `css/qr-scan.css` |
| Badge landing | `pages/login-badge.php` |
| Printable badge | `pages/user-login-barcode.php` |
| Workforce badge | `pages/workforce-badge.php` |
| SQL schema | `sql/migrations/20260522_qr_login_enterprise.sql` — alters `users`, creates `qr_login_audit` |
| SQL workforce | `sql/migrations/20260523_qr_workforce_identity.sql` (file exists per repo; adds trusted devices/challenges) |
| `.htaccess` routes | lines 74–80: `/login/scan`, `/login/badge`, `/{country}/workforce/scan`, etc. |

**JS calls (from `js/login.js`):** `POST /api/login-barcode-pair.php`, `POST /api/qr-login.php`.

**Data flow (from `docs/QR-BARCODE-LOGIN.md` + file names):** Desktop `js/login.js` creates pair token → phone opens `login-scan.php` → scans badge → `api/qr-login.php` validate → pair approved → desktop session via `rateb-barcode-login-auth.php` / `?barcode_pair=` on login page.

---

### 3.3 WebAuthn / biometric

| Item | Path |
|------|------|
| WebAuthn API | `api/webauthn/` (6 files): `register_start.php`, `authenticate_finish_auto.php`, etc. |
| Biometric API | `api/biometric/` (6 files): fingerprint/face register and authenticate |

---

### 3.4 API systems

**Legacy structure:** One PHP file per endpoint under `api/{domain}/{action}.php`.

**Versioned surface:** `api/v1/index.php` returns JSON listing:
- `/api/v1/workers?id={worker_id}`
- `/api/v1/tracking`
- `/api/v1/workflows`
- `/api/v1/alerts?limit=100`

**Modern JSON router:** `public/worker-platform.php` — route keys like `GET /system/health`, workflow timeline regex.

**Permission enforcement (observed in `api/core/api-permission-helper.php`):**
- Function `enforceApiPermission($module, $action)`
- Bypass if `$_SESSION['control_logged_in']`
- Requires `$_SESSION['user_id']`, `$_SESSION['logged_in'] === true`
- Admin bypass `role_id == 1`
- Maps to `api/core/module-permissions.php`

**Example guarded endpoints:** `api/accounting/transactions.php`, `dashboard-data.php`, `budgets.php` call `enforceApiPermission(...)`.

---

### 3.5 Telemetry / GPS

| Item | Path |
|------|------|
| API endpoints | `api/worker-tracking/update-location.php`, `latest.php`, `history.php`, `archive.php`, `sos.php` |
| Mobile contract | `docs/worker-tracking-mobile-contract.md` |
| Mobile client | `mobile-app/assets/js/app.js`, `mobile-app/sw.js` line 35: `/api/worker-tracking/` |
| Control panel map | `control-panel/pages/control/tracking-map.php`, `tracking-health.php` |
| Onboarding API | `api/workers/worker-tracking-onboarding.php`, `control-panel/api/control/worker-tracking-onboarding.php` |
| DB migration | `database/migrations/2026_04_25_000021_worker_tracking_core.php` |
| Admin engines | `admin/core/GeofenceEngine.php`, `AntiSpoofEngine.php`, `WorkerBehaviorAnalyzer.php` |

**Data flow (from mobile contract doc):** Mobile POST JSON to `update-location.php` with `worker_id`, lat/lng; offline batch via `is_offline_batch` + `locations[]`.

---

### 3.6 Finance / ledger

| Item | Path |
|------|------|
| Legacy accounting API | `api/accounting/` (67 files) |
| UI pages | `pages/accounting.php`, `pages/dashboard-accounting.php`, `pages/account/*` |
| JS | `js/accounting/` (multiple `professional.*.js` files) |
| App modules | `app/Modules/Ledger/`, `Payment/`, `Wallet/`, `Commission/`, `Settlement/` |
| Schema doc | `database/SCHEMA.md` — tables: `ledger_accounts`, `ledger_journals`, `ledger_entries`, `wallets`, `transactions`, `commissions`, `settlements` |
| PHP migrations | `database/migrations/2025_02_17_000001` through `000016` |

---

### 3.7 Marketplace / infrastructure

| Item | Path |
|------|------|
| Module | `modules/infrastructure-marketplace/` (203 files) |
| API | `api/infrastructure-marketplace/` (18 files) |
| Client UI | `pages/client/marketplace.php`, `pages/client/infrastructure.php` |
| Control admin | `control-panel/pages/control/infrastructure.php` |

---

### 3.8 Admin / control panel

| Item | Path |
|------|------|
| Control panel | `control-panel/` — see §2.5 |
| Platform admin | `admin/` — tenants, event bus, gov tracking |
| Admin API | `api/admin/` (38 files) |
| Permissions file | `control-panel/includes/control-permissions.php` (20+ permission keys referenced in prior grep) |

---

### 3.9 Workers / agents / HR / partnerships

| Domain | Primary paths |
|--------|---------------|
| Workers | `pages/Worker.php`, `api/workers/`, `js/worker/` |
| Agents | `pages/agent.php`, `api/agents/` |
| Subagents | `pages/subagent.php`, `api/subagents/` |
| HR | `pages/hr.php`, `api/hr/` (includes `debug-query.php`) |
| Partnerships | `pages/partner-portal*.php`, `api/partnerships/` |
| Government labor | `includes/government-labor.php`, `api/core/ensure-government-labor-schema.php`, `database/migrations/2026_04_25_000020_government_labor_tables.php` (tables: `gov_worker_tracking`, `gov_violations`, `gov_blacklist`, `gov_inspections`) |

---

### 3.10 Workflow orchestration (multiple locations — observed files)

| Path |
|------|
| `app/Workflows/WorkerOnboardingWorkflow.php` |
| `app/Core/WorkflowEngine.php` |
| `api/workers/workflow-engine.php` |
| `api/workers/worker-onboarding.php` |
| `api/workflows/worker-onboarding.php` |
| `includes/worker_onboarding_workflow.php` |
| `public/workflows/worker-onboarding/index.php` |

**Note:** Folder `workflow-orchestration/` exists at repo root with **0 files**.

---

### 3.11 Help center

| Item | Path |
|------|------|
| Page | `pages/help-center.php` |
| API | `api/help-center/` (11 files) |
| JS | `js/help-center/` |
| CSS | `css/help-center/help-center-enterprise.css` |

---

### 3.12 Global AI

| Item | Path |
|------|------|
| API | `api/global-ai-run.php`, `api/workers/global-ai-run.php` |
| Include | `includes/global_ai_run.php`, `includes/rateb_global_ai_workflow_core.php` |
| Workspace | `coreai/` |

---

## 4. ARCHITECTURE ANALYSIS (FACTUAL ONLY)

### 4.1 Request flow patterns observed

**Pattern A — Legacy page:**
```
Browser → .htaccess rewrite → pages/{name}.php → includes/config.php → $GLOBALS['conn'] (mysqli) → HTML output
```

**Pattern B — Legacy API:**
```
Client → /api/{domain}/{file}.php → includes/config.php and/or api-permission-helper.php → mysqli/PDO → JSON
```

**Pattern C — App worker platform:**
```
Client → public/worker-platform.php → app/Core/Autoloader → routes/worker_platform.php → App\Controllers/Services → JSON
```

**Pattern D — Public front controller:**
```
Browser → .htaccess → public/index.php → (Designed branch OR dirname index.php)
```

**Pattern E — Control panel:**
```
Browser → /control-panel/... → control-panel/includes/config.php → control_conn + optional agency mysqli
```

### 4.2 Multi-tenant structure (code evidence)

| Mechanism | File evidence |
|-----------|---------------|
| Per-request agency DB | `includes/config.php` comments 43–50; `$GLOBALS['conn']` |
| Agency resolver from URL | `config/env/agency_resolver.php` — reads `control_agencies` columns `db_host`, `db_user`, `db_pass`, `db_name` |
| Control DB name | `CONTROL_PANEL_DB_NAME` default `admin_control_panel_db` in `config/env/rateb_sa.php` line 24 |
| PDO tenant manager | `api/core/TenantDatabaseManager.php` — reads tenant creds from control DB table `tenants`; throws if tenant switch mid-request |
| Single URL mode | `define('SINGLE_URL_MODE', true)` in `config/env/rateb_sa.php` line 27 |
| Country slug routing | `.htaccess` line 84: `/{country}/` → `pages/dashboard.php?country_slug=$1` |
| Tenant self-test | `api/tenants/self-test.php`, `control-panel/api/control/tenant-isolation-self-test.php` |

### 4.3 Legacy vs new separation (exact folders)

| Layer | Folders |
|-------|---------|
| **Legacy procedural** | `api/` (except `v1/`), `pages/`, `includes/`, `js/`, most of `control-panel/` |
| **Namespaced app layer** | `app/`, `routes/worker_platform.php`, `public/worker-platform.php` |
| **Module packages** | `modules/client-dashboard/`, `modules/infrastructure-marketplace/` |
| **Separate product** | `Designed/` |
| **Shared core** | `core/Auth.php`, `core/Database.php`, `admin/core/EventBus.php` |

Both layers share `includes/config.php` and MySQL; no evidence of full migration of legacy endpoints into `app/`.

---

## 5. SECURITY REVIEW (CODE OBSERVED ONLY)

### 5.1 Hardcoded / fallback credentials (file references)

| File | Observed content |
|------|------------------|
| `config/env/rateb_sa.php` line 22 | `define('DB_PASS', ... ?: '9s%BpMr1]dfb');` |
| `config/env/default.php` line 16 | `define('DB_PASS', '9s%BpMr1]dfb');` |
| `config/env/bangladesh_rateb_sa.php` line 15 | `define('DB_PASS', '9s%BpMr1]dfb');` |
| `config/env/rateb_sa.php` lines 21–23 | Default `DB_USER` `admin_out`, `DB_NAME` `admin_out` |
| `config/env/ngenius.secrets.php` | Keys present with empty string values (`NGENIUS_API_KEY`, `NGENIUS_API_SECRET`) |

Env override path exists via `getenv('DB_PASS')` in `rateb_sa.php` before fallback.

### 5.2 Auth checks (where enforced)

| Location | Mechanism |
|----------|-----------|
| `includes/permission_middleware.php` | `checkPermission()` — session `user_id`, `logged_in`, `hasPermission()` |
| `api/core/api-permission-helper.php` | `enforceApiPermission()` — session + module map |
| `control-panel/index.php` | `$_SESSION['control_logged_in']` |
| `api/admin/clear_all_data.php` lines 20–29 | Requires session + `role_id === 1` |
| `core/Auth.php` | `Auth::login()` with tenant isolation comments |

### 5.3 API protection patterns

- Session-based for legacy API (`enforceApiPermission`, `checkPermission`)
- Control panel session bypass in `enforceApiPermission` when `control_logged_in` set
- `api/qr-login.php` — public JSON endpoint with `SYSTEM_ENDPOINT` define; uses `rateb-qr-login.php` validation (rate limits referenced in docs, not re-verified line-by-line here)

### 5.4 Exposed debug / dev / ops endpoints (file paths)

| File | Observation |
|------|-------------|
| `pages/test-config.php` | `display_errors=1`; prints `DB_HOST`; comment says "DELETE after fixing" |
| `pages/test-error.php` | Exists in `pages/` listing |
| `api/hr/debug-query.php` | Runs `SELECT * FROM employees` without visible auth check in first 50 lines |
| `api/accounting/test-apis.php` | Filename indicates test surface |
| `api/support-chat-test.php` | Root API file |
| `api/admin/clear_all_data.php` | Destructive; gated by admin session |
| `admin/debug-dashboard.php` | Exists; `OBSERVABILITY_DASHBOARD_ENABLED` true in `rateb_sa.php` |
| `admin/dev/event-load-test.php`, `validate-observability.php` | Under `admin/dev/` |
| `pages/rateb-sync-from-github.php` | Gated by `?run=1&key=rateb-deploy-sync-2026` (lines 11–16) |
| `pages/rateb-purge-cache.php` | Gated by same key string (lines 8–13) |
| `pages/rateb-fix-perms.php` | Exists (ops utility) |
| `pages/rateb-copy-from-repo.php` | Referenced by sync redirect |
| `pages/deploy-root.php`, `tenant-test.php` | Exist in pages listing |

### 5.5 Session handling

- `api/core/rateb_api_session.inc.php` + `rateb_api_pick_session_name()` loaded before `session_start()` in permission helper
- `session_regenerate_id(true)` in `core/Auth.php` and `control-panel/core/Auth.php` (grep hits)
- Partner portal: `pages/partner-portal.php` line 25 `session_regenerate_id(true)`

---

## 6. DATABASE LAYER

### 6.1 Connection definitions

| File | Role |
|------|------|
| `config/env/load.php` | Loads host-specific env; lists env vars `DB_HOST`, `DB_PASS`, `CONTROL_DB_PASS`, etc. |
| `config/env/rateb_sa.php` | Defines `DB_*`, `CONTROL_PANEL_DB_NAME`, `SINGLE_URL_MODE` |
| `config/database.php` | Sets `$GLOBALS['conn'] = new mysqli(...)` |
| `includes/config.php` | Main bootstrap; documents `$GLOBALS['conn']` as tenant connection |
| `api/core/Database.php` | PDO singleton used by some APIs |
| `api/core/TenantDatabaseManager.php` | PDO per tenant from control DB |
| `control-panel/includes/config.php` | `$GLOBALS['control_conn']` + agency connection from `control_agencies` |

### 6.2 Multi-DB handling (evidence)

- Comments in `includes/config.php` state separate database per country/agency
- `control-panel/api/control/agency-db-helper.php` opens mysqli with agency row credentials
- `config/migrations/separate_control_panel_db/` — README + `03_migrate_data.php` for splitting control DB
- `TenantDatabaseManager.php` line 9: credentials from control DB table `tenants`

### 6.3 Schema hints from code/migrations

**From `database/SCHEMA.md` + migrations:** `countries`, `agencies`, `customers`, `subscription_plans`, `subscriptions`, `wallets`, `transactions`, `commissions`, `ledger_accounts`, `ledger_journals`, `ledger_entries`, `settlements`.

**From `2026_04_25_000020_government_labor_tables.php`:** `gov_worker_tracking`, `gov_violations`, `gov_blacklist`, `gov_inspections`.

**From `sql/migrations/20260522_qr_login_enterprise.sql`:** `users` columns `login_barcode`, `qr_login_token`, …; table `qr_login_audit`.

**From `pages/login.php`:** queries table `control_countries` on control connection.

**From `config/env/agency_resolver.php`:** table `control_agencies` with `db_host`, `db_user`, `db_pass`, `db_name`, `site_url`.

### 6.4 ORM vs raw SQL

| Pattern | Where |
|---------|--------|
| Raw mysqli | Widespread: `includes/config.php`, `control-panel/includes/config.php`, many `api/` files |
| PDO wrapper | `api/core/Database.php`, `core/Database.php`, `TenantDatabaseManager.php` |
| Laravel-style migrations | `database/migrations/*.php` use `Illuminate\Database\Migrations\Migration` |
| Inline SQL in PHP | Most legacy APIs and pages |
| Query classes | `api/core/queries/` — `WorkerQueries.php`, `AgentQueries.php`, etc. |

---

## 7. TEST COVERAGE

### 7.1 Exact test files

| File | What it tests (from file content) |
|------|-----------------------------------|
| `tests/TestRunner.php` | Runs all `*Test.php`; skips Unit/Workflow on PHP < 8 |
| `tests/bootstrap.php` | Test bootstrap |
| `tests/Unit/AuthorizationServiceTest.php` | `AuthorizationService::can()` edge cases |
| `tests/Unit/AlertServiceTest.php` | Alert service |
| `tests/Unit/IdempotencyServiceTest.php` | Idempotency service |
| `tests/Unit/WorkflowServiceTest.php` | Workflow service |
| `tests/Workflow/WorkflowEngineTest.php` | Workflow engine |
| `tests/Integration/EndpointsTest.php` | **File existence only** for v1 APIs and workflow onboarding paths; worker-platform health route string in `public/worker-platform.php` |

### 7.2 Untested areas (by absence of test files)

No test files found under paths for:
- `api/accounting/` (67 files)
- `api/workers/` (50 files)
- `api/admin/` (38 files)
- `pages/` (139 files)
- `control-panel/` (201 files)
- `modules/infrastructure-marketplace/` (203 files)
- QR login flows
- Payment integrations

**Android/iOS:** `android/app/src/test/` and `androidTest/` contain template example tests only (Capacitor default structure observed in tree listing).

---

## 8. ENTRY POINT MAP

### 8.1 Public / site root

| Entry | Path |
|-------|------|
| Root redirect | `index.php` → marketing home or country dashboard if session valid |
| Apache rewrite hub | `.htaccess` → `pages/*`, `public/index.php`, static assets |
| Public front controller | `public/index.php` |
| Build marker | `public/rateb-build.txt` |
| Webhooks | `public/webhooks/dispatch.php` |
| Worker platform JSON | `public/worker-platform.php` |
| Workflow public | `public/workflows/worker-onboarding/index.php` |

### 8.2 Admin entry points

| Entry | Path |
|-------|------|
| Control panel | `control-panel/index.php` → `control/dashboard.php` |
| Platform admin | `admin/index.php`, `admin/dashboard.php`, `admin/control-center.php` |
| Tenant admin | `admin/tenants.php`, `admin/create-tenant.php` (.htaccess routes) |

### 8.3 API entry points

| Type | Path |
|------|------|
| Per-file legacy | `/api/{folder}/{script}.php` (~378 files) |
| v1 index | `api/v1/index.php` |
| v1 resources | `api/v1/workers/index.php`, `tracking/index.php`, `workflows/index.php`, `alerts/index.php` |
| Control panel API | `/control-panel/api/control/*.php` (42 files) |
| Admin API | `/api/admin/*.php` |

### 8.4 Mobile integration

| Entry | Integration |
|-------|-------------|
| `mobile-app/index.html` | Redirect to `index.php` |
| `mobile-app/assets/js/app.js` | Tracking UI; uses `fetch()` (line 1819 area) |
| `mobile-app/sw.js` | Service worker; special handling for `/api/worker-tracking/` |
| `android/`, `ios/` | Capacitor shells bundling mobile-app assets |
| Contract doc | `docs/worker-tracking-mobile-contract.md` → `api/worker-tracking/*.php` |

### 8.5 Designed (separate)

| Entry | Path |
|-------|------|
| Launcher | `pages/designed-launcher.php` |
| Bootstrap | `includes/designed_bootstrap.php` |
| App public root | `Designed/public/` (16 files) |

---

## 9. REAL RISKS (FILE-TIED ONLY)

| Risk | Evidence |
|------|----------|
| DB password in git-tracked env fallbacks | `config/env/rateb_sa.php:22`, `default.php:16`, `bangladesh_rateb_sa.php:15` |
| Shared static ops key | `rateb-deploy-sync-2026` in `pages/rateb-sync-from-github.php:11`, `pages/rateb-purge-cache.php:9` |
| Diagnostic page exposes config | `pages/test-config.php` prints `DB_HOST`, enables `display_errors` |
| HR debug API may leak employee rows | `api/hr/debug-query.php` returns employee records as JSON; no auth in first 50 lines |
| Destructive admin API exists | `api/admin/clear_all_data.php` — deletes business data when `confirm=1` and admin session |
| Observability dashboard enabled on prod host config | `config/env/rateb_sa.php:33` `OBSERVABILITY_DASHBOARD_ENABLED=true` + `admin/debug-dashboard.php` |
| Empty placeholder API dirs | `api/hr_advances/`, `api/roles/` etc. have 0 files — may confuse routing expectations |
| Dual workflow implementations | Seven distinct workflow-related files listed in §3.10 — no single owner file |
| SQL migrations not in deploy auto-path | Deploy script comments (workspace rules) list `sql/` outside fast-upload prefixes — schema drift risk if ops miss manual run |
| `logs/` directory (89 files) | Operational data on disk in repo workspace |

---

## 10. FINAL EXECUTIVE SUMMARY

### 10.1 What the system actually is (from files)

A **PHP/MySQL monorepo** containing:

1. **Agency workforce management** — pages + `api/workers/` + agents/subagents/partnerships  
2. **Accounting subsystem** — large `api/accounting/` + `js/accounting/`  
3. **Control panel** — separate console with own API tree and DB connection logic  
4. **Infrastructure marketplace module** — self-contained namespace under `modules/infrastructure-marketplace/`  
5. **Client dashboard module** — `modules/client-dashboard/` + `pages/client/`  
6. **Partial modern layer** — `app/` + `public/worker-platform.php`  
7. **Mobile worker tracking PWA** — `mobile-app/` + Capacitor `android/`/`ios/`  
8. **QR/barcode login subsystem** — dedicated includes, APIs, pages, SQL migrations, docs  
9. **Separate Designed storefront** — `Designed/` tree with own MVC layout  
10. **Payment integrations** — N-Genius in main includes; `tap-payments/` and `paypal-checkout/` as standalone folders  

Primary production host configuration file observed: `config/env/rateb_sa.php` (`SITE_URL` `https://rateb.sa`).

### 10.2 Production-ready vs partial (file-based)

| Area | Status | Basis |
|------|--------|-------|
| Legacy pages + API | Present in large volume | 378 API files, 139 pages, deploy workflow exists |
| Control panel | Complete tree | 201 files, entry gate on session |
| Infra marketplace | Extensive | 203 files + SQL migrations + runbook markdown in `Docs/` |
| QR login | Implemented | 10+ dedicated files + SQL + `.htaccess` routes |
| Worker tracking API | Implemented | 5 API files + mobile contract doc |
| `app/` worker platform | Partial | Separate entry; legacy parallel paths remain |
| Automated tests | Minimal | 8 test files; integration tests check file existence only |
| `workflow-orchestration/` | Empty | 0 files |
| `Designed/` | Separate | Own deploy doc; excluded from main deploy scripts |

### 10.3 Incomplete or experimental (observed)

| Item | Evidence |
|------|----------|
| Empty root folders | `workflow-orchestration/`, `hr/`, `examples/`, `.well-known/` |
| Test/debug endpoints in tree | `test-config.php`, `debug-query.php`, `test-apis.php`, `admin/dev/*` |
| Global AI | `api/global-ai-run.php`, `coreai/` — isolated; `coreai/agent/README.md` exists |
| Multiple onboarding workflow entry files | Listed §3.10 without unified directory |
| PHP 7.4 compat shims | `includes/config.php` line 35–36 compatibility mode log |

---

**End of forensic inventory.**  
**No files were modified during this audit.**
