# RATEB — Forensic + Runtime Truth Model

**المستودع:** `ratebprogram` فقط (لا يوجد مجلد `ratebsrar`)  
**التاريخ:** 2026-05-24  
**المنهج:** قراءة `.htaccess`، نقاط الدخول، `require/include`، وفحص أنماط الجلسة/الصلاحيات داخل الملفات — **بدون افتراضات تشغيلية خارج الكود**.

---

## 1. خريطة المسارات الفعلية (ACTIVE ROUTES)

### 1.1 آلية التوجيه العامة

| الطبقة | الملف | السلوك |
|--------|------|--------|
| Apache rewrite | `.htaccess` | قواعد صريحة لـ marketing/trust/login؛ ثم `DirectoryIndex public/index.php index.php` |
| Front controller | `public/index.php` | Designed، `/` → `index.php`، `/profile` → `pages/about.php`، fallback 404 |
| Root index | `index.php` | `includes/config.php` → إن `rateb_program_session_is_valid_user()` → dashboard وإلا marketing home |
| API | `/api/**/*.php` | **ملف مباشر** (لا router مركزي legacy)؛ استثناء `public/worker-platform.php` + `api/v1/*/index.php` |

**ملاحظة runtime:** أي ملف PHP موجود تحت `api/` أو `pages/` يمكن استدعاؤه مباشرة إذا كان على الخادم، حتى بدون قاعدة في `.htaccess`.

---

### 1.2 مسارات `.htaccess` → صفحات (مصدر: `.htaccess` سطر 12–90)

| URL (canonical) | الملف | Auth (من الكود) |
|-----------------|------|-----------------|
| `/home` | `pages/home.php` | **Public** — `require config.php` فقط؛ لا فحص `logged_in` في أول 30 سطر |
| `/profile/` | `pages/about.php` | **Public** |
| `/security-compliance/` | `pages/security-compliance.php` | **Public** |
| `/architecture/` | `pages/architecture.php` | **Public** |
| `/procurement-legal/` | `pages/procurement-legal.php` | **Public** |
| `/enterprise-trust/` | `pages/enterprise-trust.php` | **Public** |
| `/government-workforce-operations/` | `pages/government-workforce-operations.php` | **Public** |
| `/enterprise-pack/` | `pages/enterprise-pack.php` | **Public** |
| `/{country}/login` | `pages/login.php?country_slug=` | **Public** — يضبط `$_SESSION` عند POST ناجح (سطر ~1098+) |
| `/login/scan` | `pages/login-scan.php` | **Public** — يتطلب `token` pair صالح للcookie `rateb_pair` |
| `/{country}/login/scan` | `pages/login-scan.php` | **Public** (نفس الملف) |
| `/{country}/workforce/scan` | `pages/login-scan.php?mode=checkin` | **Public** |
| `/login/badge` | `pages/login-badge.php` | **Public** — landing لـ badge URL |
| `/workforce/badge` | `pages/workforce-badge.php` | **Auth** — `$_SESSION['logged_in']` (grep `pages/workforce-badge.php:12`) |
| `/{country}/` (catch-all) | `pages/dashboard.php?country_slug=` | **Auth** — `logged_in` + `user_id` (`pages/dashboard.php:17`) |
| `/{country}/pages/{page}` | `pages/{page}?country_slug=` | يعتمد على الصفحة |
| `/pages/{name}` (extensionless) | `pages/{name}.php` إن وُجد | يعتمد على الصفحة |
| `/control-panel/pages/*` | `control-panel/pages/*.php` | **Control session** — `control-panel/index.php:10` `control_logged_in` |
| `/admin/tenants` | `admin/tenants.php` | `.htaccess:102` — فحص داخل الملف (admin tree) |
| `/Designed/*` | `public/index.php` → Designed bootstrap | **منفصل** — `includes/designed_bootstrap.php` |

---

### 1.3 صفحات agency — Auth (من grep `pages/*.php`)

| الصفحة | الشرط | الملف:سطر تقريبي |
|--------|--------|------------------|
| `dashboard.php` | `user_id` + `logged_in` | `:17` |
| `Worker.php` | `user_id` + `logged_in` | `:17` |
| `agent.php`, `subagent.php`, `hr.php`, `accounting.php`, `reports.php`, `visa.php` | نفس النمط | `:11–17` |
| `system-settings.php` | `permission_middleware.php` + `logged_in` | `:7, :15` |
| `user-login-barcode.php`, `workforce-badge.php` | `logged_in` | `:11–12` |
| `help-center.php` | `logged_in` | `:8` |
| `contact.php` | `logged_in` | `:12` |
| `partner-portal*.php` (عدة) | `rateb_program_session_is_valid_user()` | مثال `partner-agencies.php:10` |
| `pages/client/*` | `pages/client/_auth.inc.php` → `rateb_client_dashboard_require_access()` | `:23` |
| `login.php`, `login-scan.php`, `login-badge.php` | **Public** (مع منطق pair/token) | — |
| `home.php`, `about.php`, trust pages | **Public** | — |
| `test-config.php` | **Public** + `display_errors=1` | `:11–14` |
| `rateb-purge-cache.php` | **Key** `rateb-deploy-sync-2026` | `:8–13` |
| `rateb-sync-from-github.php` | **Key** + `run=1` | `:11–16` |

---

### 1.4 API — نقاط الدخول الرئيسية

**النمط العام:** `https://{host}/api/{path}.php`

| Endpoint | الملف | الدالة/الإجراء | Auth |
|----------|------|----------------|------|
| `POST /api/login-barcode-pair.php` | `api/login-barcode-pair.php` | `action`: `create`/`poll`/`submit` → `rateb_barcode_pair_*()` | **Public** — `SYSTEM_ENDPOINT`; لا session (`:11–12`) |
| `POST /api/qr-login.php` | `api/qr-login.php` | `validate`/`submit`/`validate_pin`/`trusted_*`/`issue`/`revoke` | **Public** لـ validate/trusted؛ **Auth** لـ `metrics` (`:236`) و `issue`/`revoke` (`:250`) |
| `POST /api/workers/core/create.php` | `api/workers/core/create.php` | `enforceApiPermission('workers','create')` | **Auth + RBAC** `:38` |
| `GET /api/workers/get.php` | `api/workers/get.php` | `enforceApiPermission('workers','get')` | **Auth + RBAC** `:36` |
| `* /api/accounting/journal-entries.php` | `api/accounting/journal-entries.php` | session ثم `enforceApiPermission('journal-entries', …)` | **Auth + RBAC** `:64–80` |
| `POST /api/worker-tracking/update-location.php` | `api/worker-tracking/update-location.php` | `tracking_json()` pipeline | **ليس session agency** — `tenant_id` + `worker_id`؛ token اختياري/إلزامي حسب `TRACKING_REQUIRE_TOKEN` env (`:47–51`, `:135–138`) |
| `POST /api/registration-request.php` | `api/registration-request.php` | تعليق صريح: "No authentication required" | **Public** `:7–8` |
| `GET /api/ngenius-health.php` | `api/ngenius-health.php` | health JSON | **Public** — `SYSTEM_ENDPOINT` `:30` |
| `GET /api/v1/index.php` | `api/v1/index.php` | قائمة endpoints JSON | **Public** (لا فحص في الملف) |
| `* /api/diagnostics/tenant-isolation-self-test.php` | `api/diagnostics/tenant-isolation-self-test.php` | self-test | **Auth** app أو control session `:13–20` |
| `GET /api/hr/debug-query.php` | `api/hr/debug-query.php` | SELECT employees | **لا auth في أول 50 سطر** — **Public إذا reachable** |
| `GET /api/admin/clear_all_data.php` | `api/admin/clear_all_data.php` | `action=clear_all_data` | **Admin** `role_id === 1` `:26–29` |

**إحصاء grep (363 ملف PHP تحت `api/`):**
- ~**72** ملفاً يستدعي `enforceApiPermission` صراحة
- ~**92** ملفاً يحتوي أنماط auth أوسع (session / control / role)
- **الباقي** قد يعتمد على `includes/config.php` فقط أو **بدون gate واضح** — يتطلب مراجعة ملف-بملف قبل production hardening

---

### 1.5 Worker Platform (مسار منفصل)

| URL (relative to script) | الملف | Route key | Auth |
|--------------------------|------|-----------|------|
| `public/worker-platform.php` | `public/worker-platform.php` | من `routes/worker_platform.php` | `AccessMiddleware` + permission keys مثل `workers.create`, `tracking.move` (`routes/worker_platform.php:23–38`) |

**Routes المعرّفة في `routes/worker_platform.php`:**
- `POST /workers`
- `POST /tracking/move`
- `GET /workflows/{id}/timeline`
- `GET /metrics/system-health`
- `GET /metrics/workflow-stats`
- `GET /metrics/failure-rates`
- `GET /system/health`

---

### 1.6 Control Panel API

**Base:** `/control-panel/api/control/*.php` (42 ملفاً observed)

| مثال | الملف |
|------|------|
| Agencies | `control-panel/api/control/agencies.php` |
| Worker tracking | `control-panel/api/control/worker-tracking.php` |
| SSE | `control-panel/api/control/events-stream.php` |
| Tenant isolation test | `control-panel/api/control/tenant-isolation-self-test.php` |

**Entry gate للوحة:** `control-panel/index.php` → `$_SESSION['control_logged_in']`.

---

## 2. خريطة الاعتمادات (DEPENDENCY GRAPH)

### 2.1 Hub مركزي: `includes/config.php`

```
includes/config.php
├── config/env/load.php          → getenv / host profile
├── config/env/rateb_sa.php  → DB_*, SINGLE_URL_MODE, CONTROL_PANEL_DB_NAME
├── admin/core/EventBus.php      (optional)
├── $GLOBALS['conn'] = mysqli    → tenant/agency DB (:1199–1207, :1383, :1407)
└── session + agency switching     → comments :43–50
```

**من يستدعيه:** `pages/*.php`، معظم `api/*`، `control-panel/includes/config.php` (نسخة منفصلة).

---

### 2.2 سلسلة API legacy (workers مثال)

```
pages/Worker.php (UI)
  → js/worker/* (fetch)
    → api/workers/get.php
        → api/core/Database.php (PDO)
        → api/core/api-permission-helper.php
            → includes/config.php          [session, $GLOBALS['conn']]
            → includes/permission_middleware.php
            → includes/permissions.php     [hasPermission()]
            → api/core/module-permissions.php
        → DB: workers table via PDO
```

---

### 2.3 QR Login chain

```
pages/login.php + js/login.js
  → POST api/login-barcode-pair.php (create/poll)
      → includes/rateb-barcode-login-pair.php
          → temp files sys_get_temp_dir()/rateb_barcode_pairs OR DB login_barcode_pairs
  → pages/login-scan.php (phone)
      → cookie rateb_pair
      → js/login-scan.js → POST api/qr-login.php (validate)
          → includes/config.php
          → includes/rateb-qr-login.php
              → includes/rateb-user-login-barcode.php
              → includes/rateb-barcode-login-auth.php
          → includes/rateb-qr-workforce-identity.php
          → rateb_qr_login_authenticate_payload() → users table (mysqli $GLOBALS['conn'] or resolved)
          → rateb_barcode_pair_approve() → desktop poll in js/login.js
  → DB: users (qr_login_token cols), qr_login_audit (sql/migrations/20260522_qr_login_enterprise.sql)
```

---

### 2.4 GPS tracking chain

```
mobile-app/assets/js/app.js (fetch)
  → POST api/worker-tracking/update-location.php
      → includes/config.php [TENANT_REQUIRED, TenantExecutionContext]
      → api/core/Database.php → PDO app tenant
      → getControlDB() → control PDO
      → admin/core/EventBus.php, GeofenceEngine.php, …
      → tables: worker_tracking_devices, location storage (via ratebEnsureWorkerTrackingSchema)
```

**مصدر tenant:** `TenantExecutionContext::getTenantId()` أو `HTTP_X_TENANT_ID` أو payload `tenant_id` (`update-location.php:99–110`).

---

### 2.5 Infrastructure marketplace

```
control-panel/pages/control/infrastructure.php
  → api/infrastructure-marketplace/*.php
      → modules/infrastructure-marketplace/bootstrap.php
          → Rateb\InfrastructureMarketplace\* autoload
      → Workers/InfrastructureProvisioningWorker.php (CLI/cron documented in Docs/)
```

---

### 2.6 Client dashboard module

```
pages/client/dashboard.php
  → pages/client/_common-start.inc.php
      → pages/client/_auth.inc.php
          → includes/config.php
          → modules/client-dashboard/bootstrap.php
              → rateb_client_dashboard_require_access()
  → api/client-dashboard/snapshot.php (via JS — 5 files under api/client-dashboard/)
```

---

### 2.7 `$GLOBALS` — استخدامات مرصودة

| Key | يُضبط في | يُقرأ في |
|-----|----------|----------|
| `$GLOBALS['conn']` | `includes/config.php:1205+` | `permissions.php`, QR auth, login pages, diagnostics |
| `$GLOBALS['control_conn']` | `control-panel/includes/config.php:103` | control-panel APIs |
| `$GLOBALS['rateb_public_nav_on_marketing_home']` | `pages/home.php:10` | nav includes |
| `$GLOBALS['worker_platform_event_dispatcher']` | `public/worker-platform.php:23` | app listeners |

---

## 3. الملفات غير المستخدمة / Dead Code (أدلة من الكود)

### 3.1 مجلدات فارغة (0 ملفات)

| المسار | ملاحظة |
|--------|--------|
| `workflow-orchestration/` | **فارغ** — orchestration موجود في `app/`, `api/workers/workflow-engine.php` |
| `hr/` (root) | **فارغ** — HR الفعلي في `pages/hr.php`, `api/hr/` |
| `examples/` | **فارغ** |
| `.well-known/` | **فارغ** |
| `api/hr_advances/`, `api/hr_salaries/`, `api/roles/`, … | **مجلدات API بلا ملفات** (counts=0 في scan) |

### 3.2 Blade views بدون مراجع PHP

| الملف | دليل عدم الاستخدام |
|------|---------------------|
| `resources/views/dashboard/index.blade.php` | **0** matches لـ `resources/views` في `*.php` (grep) |
| `resources/views/layouts/app.blade.php` |同上 |
| `resources/views/dashboard/admin.blade.php` |同上 |

**الاستنتاج من الكود:** مسار Blade **غير موصول** بأي entry point PHP في المستودع.

### 3.3 مسارات workflow متعددة (تداخل — ليس dead لكن redundant)

| الملف | مراجع |
|------|--------|
| `api/workers/workflow-engine.php` | `require` من `api/workers/core/create.php:31` |
| `api/workers/worker-onboarding.php` | existence test في `tests/Integration/EndpointsTest.php:21` |
| `api/workflows/worker-onboarding.php` |同上 |
| `includes/worker_onboarding_workflow.php` |同上 |
| `app/Workflows/WorkerOnboardingWorkflow.php` | `routes/worker_platform.php` layer |
| `public/workflows/worker-onboarding/index.php` |同上 |

### 3.4 APIs/debug بدون routing خاص — **reachable مباشرة**

| الملف | الخطر |
|------|--------|
| `api/hr/debug-query.php` | debug JSON |
| `api/accounting/test-apis.php` | اسم test |
| `api/accounting/test-entity-accounts.php` | اسم test |
| `api/support-chat-test.php` | اسم test |

### 3.5 `archive/` (45 ملف)

- موجود في الشجرة؛ **مستبعد** من deploy script (workspace rule) — **ليس entry production** في CI.

### 3.6 `Designed/` (127 ملف)

- **entry منفصل** عبر `public/index.php` + `Designed/public/` — **ليس جزءاً** من fast-deploy paths الرئيسية.

---

## 4. نموذج تدفق البيانات الحقيقي (DATA FLOW)

### 4.1 Worker lifecycle

```
[UI] pages/Worker.php (auth: session)
  ↓ POST (JS)
[API] api/workers/core/create.php
  → enforceApiPermission('workers','create')
  → api/workers/workflow-engine.php
      → rateb_workflow_ensure_schema(PDO)  → tables workflow_definitions, workflow_stages, …
      → stage keys: identity, passport, police, medical, … (workflow-engine.php:9–22)
  → PDO Database::getInstance() → INSERT workers (tenant DB)
  ↓
[API] api/workers/update-status.php / update-documents.php / bulk-*.php
  → enforceApiPermission on many paths
  → workers table + documents sub-API (api/workers/documents/*)
  ↓
[Optional] api/workers/worker-tracking-onboarding.php
  → links worker to tracking devices (control DB)
```

**مصدر البيانات:** tenant mysqli/PDO عبر `includes/config.php` → `$GLOBALS['conn']` / `Database` singleton.

---

### 4.2 QR login flow (runtime)

```
1. Desktop: pages/login.php + js/login.js
   POST api/login-barcode-pair.php {action:create}
   → rateb_barcode_pair_create() [includes/rateb-barcode-login-pair.php]
   → returns token → QR on screen

2. Phone: GET /login/scan?token=… → pages/login-scan.php
   → rateb_barcode_pair_read(token)
   → setcookie('rateb_pair', token, 600s)

3. Phone scan badge:
   POST api/qr-login.php {action:validate, qr_payload, pair_token}
   → rateb_qr_login_authenticate_payload() [includes/rateb-qr-login.php]
   → reads users.qr_login_token / RATEBLOGIN: prefix
   → rateb_qr_login_audit() → qr_login_audit table
   → rateb_barcode_pair_approve() [pair.php]
   → optional rateb_qr_login_apply_session() if no pair

4. Desktop: js/login.js polls api/login-barcode-pair.php {action:poll}
   → status approved → redirect with barcode_pair query
   → pages/login.php consumes pair → session (rateb-barcode-login-auth.php)
```

**Session keys (من login.php grep):** `logged_in`, `user_id`, `agency_id`, …

---

### 4.3 Finance / ledger flow

```
[UI] pages/accounting.php (session logged_in)
  ↓ js/accounting/*
[API] api/accounting/journal-entries.php
  → includes/config.php [session]
  → enforceApiPermission journal-entries view/create/update/delete
  → api/accounting/core/erp-posting-controls.php
  → api/accounting/core/audit-trail-helper.php
  → mysqli/PDO queries on journal + ledger tables (legacy schema in tenant DB)

[Parallel SaaS schema — migrations not necessarily wired to all UI paths]
database/migrations/2025_02_17_* → ledger_journals, ledger_entries, wallets, …
database/SCHEMA.md documents ER for app/Modules/Ledger
```

**ملاحظة runtime truth:** **مساران** — accounting API legacy ضخم (`api/accounting/` 67 ملف) + migrations `database/migrations/` لـ app modules؛ **لا يوجد في الكود دليل أن كل UI يستخدم migrations الجديدة فقط**.

---

### 4.4 GPS tracking flow

```
[mobile-app] assets/js/app.js → fetch POST
[API] api/worker-tracking/update-location.php
  Input: worker_id, lat/lng, optional is_offline_batch + locations[]
  Context: tenant_id (header/payload/TenantExecutionContext)
  Auth: NOT $_SESSION — device_id + api_token if TRACKING_REQUIRE_TOKEN env true
  Control DB: worker_tracking_devices register/validate
  App DB: worker existence check on workers.id
  Side effects: EventBus + GeofenceEngine + threat engines (admin/core/*)
  Output: JSON tracking_json()

Documented in: docs/worker-tracking-mobile-contract.md
Cached in SW: mobile-app/sw.js:35 (/api/worker-tracking/)
```

---

## 5. نموذج الأمان (SECURITY EXPOSURE MAP)

### 5.1 تصنيف المستويات

| المستوى | المعنى |
|---------|--------|
| **P0** | Public + sensitive data or destructive |
| **P1** | Public + auth bypass surface / weak shared secret |
| **P2** | Auth required but broad (any logged user) |
| **P3** | RBAC via enforceApiPermission |
| **P4** | Admin role_id=1 or control_logged_in |

### 5.2 جدول نقاط حرجة (ملف → تصنيف)

| Endpoint / Page | ملف | تصنيف | خطورة |
|-----------------|------|--------|--------|
| QR validate | `api/qr-login.php` action validate | **Public** | P1 — by design؛ rate limit in `rateb-qr-login.php` |
| Pair create/poll | `api/login-barcode-pair.php` | **Public** | P2 |
| Registration | `api/registration-request.php` | **Public** | P2 — comment rate limit by IP |
| N-Genius health | `api/ngenius-health.php` | **Public** | P2 |
| HR debug | `api/hr/debug-query.php` | **Public** (no gate in header) | **P0** |
| Test config | `pages/test-config.php` | **Public** + errors | **P0** |
| Clear all data | `api/admin/clear_all_data.php` | **Admin** | P0 if session compromised |
| Purge cache | `pages/rateb-purge-cache.php` | **Shared key** | P1 |
| Sync github | `pages/rateb-sync-from-github.php` | **Shared key** | P1 |
| Worker tracking POST | `api/worker-tracking/update-location.php` | **Token/tenant** not session | P1–P2 |
| Journal entries | `api/accounting/journal-entries.php` | **RBAC** | P3 |
| Worker create | `api/workers/core/create.php` | **RBAC** | P3 |
| Control panel | `control-panel/*` | **control_logged_in** | P4 |
| Diagnostics tenant test | `api/diagnostics/tenant-isolation-self-test.php` | **Logged user** | P2 |
| Worker platform | `public/worker-platform.php` | **AccessMiddleware** | P3 |

### 5.3 أسرار في git (ملف → pattern)

| ملف | سطر | المحتوى |
|-----|-----|---------|
| `config/env/rateb_sa.php` | 22 | fallback `DB_PASS` literal |
| `config/env/default.php` | 16 | `define('DB_PASS', '…')` |
| `config/env/bangladesh_rateb_sa.php` | 15 | same pattern |

### 5.4 Session handling (ملفات)

| Mechanism | File |
|-----------|------|
| Agency session | `pages/login.php` sets `$_SESSION['logged_in']` |
| API session pick | `api/core/rateb_api_session.inc.php` |
| Regenerate ID | `core/Auth.php:116`, `control-panel/core/Auth.php:36` |
| QR apply session | `rateb_qr_login_apply_session()` in `includes/rateb-qr-login.php` |
| Control SSO | `includes/config.php` (control=1&agency_id=) referenced in login.php:11 |

---

## 6. النتيجة النهائية

### 6.1 ما هو النظام فعلياً (من الكود فقط)

Monorepo PHP/MySQL يجمع:

1. **واجهة agency** (`pages/` + `js/`) مع session `logged_in`
2. **~363 API script** تحت `api/` — **one-file-one-endpoint**
3. **Control panel** منفصل (`control-panel/` + `control_logged_in`)
4. **طبقة app/** (`public/worker-platform.php`) — JSON router صغir with middleware
5. **Module packages:** `client-dashboard`, `infrastructure-marketplace`
6. **Mobile PWA** → `api/worker-tracking/*`
7. **QR auth subsystem** — public validate APIs + pair files
8. **Designed/** — تطبيق PHP منفصل داخل نفس repo
9. **Payment folders:** `tap-payments/`, `paypal-checkout/`, `includes/ngenius.php`

**ليس:** framework موحّد واحد — **legacy procedural + app layer + modules** coexist.

---

### 6.2 ما يبدو production-active (أدلة في الكود)

| Area | Evidence |
|------|----------|
| Login + dashboard | `.htaccess` country routes + session checks in `dashboard.php` |
| Workers API | `enforceApiPermission` widespread in workers/accounting |
| QR login | Full file chain + SQL migrations + `.htaccess` routes |
| Deploy | `.github/workflows/deploy.yml` + `scripts/github-cpanel-fileman-deploy-core.py` |
| Worker tracking | Mobile contract doc + 5 API files + EventBus integration |
| Control panel | 201 files + gated index |
| Infra marketplace | 203 files + SQL migrations + runbook markdown |

---

### 6.3 ما يبدو experimental / incomplete / risky

| Item | Evidence |
|------|----------|
| Empty `workflow-orchestration/` | 0 files |
| Unused Blade `resources/views/` | no PHP requires |
| Dual ledger paths | `api/accounting/` vs `database/migrations/` |
| 7 workflow entry files | listed §3.3 — no single owner |
| ~271 API files without `enforceApiPermission` string | 363 total − ~92 with auth markers |
| Debug/test endpoints in tree | §3.4 |
| `tests/` — 8 files; integration = file existence only | `tests/Integration/EndpointsTest.php` |
| `coreai/agent/README.md` placeholder | agent folder |
| Public ops pages with static key | `rateb-deploy-sync-2026` |

---

### 6.4 Runtime Truth — جملة واحدة

**الكود ي describe منصة تشغيل workforce multi-tenant تعمل primarily عبر PHP pages + direct API scripts على `$GLOBALS['conn']`، مع subsystems منفصلة (control panel, worker-platform JSON, QR public APIs, GPS tenant-token auth) — وليس runtime موحّداً تحت router واحد.**

---

*Read-only audit. لا تعديلات على المستودع.*
