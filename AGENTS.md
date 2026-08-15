# AGENTS.md

## Cursor Cloud specific instructions

This is a large PHP + MySQL monorepo (the RATEB platform). The most self-contained,
fully-runnable product is **RATEB ERP** (`rateb-erp/`). The main RATEB Pro app (repo
root) is a complex multi-tenant system that resolves per-country databases from
`control_agencies`; it is not needed to demonstrate the environment and is not set up
here beyond its shared DB. Standard commands live in `rateb-erp/README.md` and
`.github/workflows/pos-v2-tests.yml`; this section only covers non-obvious dev caveats.

### Runtime that is already provisioned in the VM snapshot
- System packages: PHP 8.3 CLI (+ `pdo_mysql`, `pdo_sqlite`, `mbstring`, `gd`, `curl`,
  `xml`, `bcmath`, `intl`), Composer, MariaDB 10.11, Node 22.
- MySQL user `rateb` / password `rateb` (ALL PRIVILEGES) and databases:
  `admin_rateb`, `admin_rateb-erp`, `admin_control_panel_db`, `admin_call-center`,
  `admin_rateb_platform_catalog`. RATEB ERP migrations are already applied to
  `admin_rateb-erp`.
- A gitignored `/workspace/.env` holds local dev DB credentials and host settings.
  If it is missing, recreate it from `.env.example` using the `rateb`/`rateb` creds
  above, plus `RATEB_MAIN_PLATFORM_HOSTS=127.0.0.1,localhost` (see gotchas below).

### Starting services (not done by the update script)
- Start the DB each boot: `sudo service mariadb start`.
- Serve RATEB ERP (front controller, docroot = `rateb-erp/public`). The PHP built-in
  server is single-threaded, so set `PHP_CLI_SERVER_WORKERS`, and pass the platform-host
  env so redirects/URLs stay on localhost:
  ```bash
  cat > /tmp/erp-router.php <<'PHP'
  <?php
  $docroot = $_SERVER['DOCUMENT_ROOT'] ?: '/workspace/rateb-erp/public';
  $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
  if ($path !== '/' && is_file($docroot . $path)) { return false; }
  require $docroot . '/index.php';
  PHP
  RATEB_MAIN_PLATFORM_HOSTS=127.0.0.1,localhost \
  RATEB_PLATFORM_ERP_PUBLIC_BASE=http://127.0.0.1:8080 \
  PHP_CLI_SERVER_WORKERS=6 \
  php -S 127.0.0.1:8080 -t /workspace/rateb-erp/public /tmp/erp-router.php
  ```
  Super Admin login: `admin@rateb.sa` / `123456`. API token:
  `POST /api/v1/auth/token {"email","password"}`.
- To re-run ERP migrations: `cd rateb-erp && RATEB_ERP_DB_NAME=admin_rateb-erp \
  RATEB_ERP_DB_USER=rateb RATEB_ERP_DB_PASS=rateb DB_HOST=127.0.0.1 DB_USER=rateb \
  DB_PASS=rateb php migrations/run.php`.

### Gotchas (important, non-obvious)
- **Browser GUI testing against localhost redirects to production.** The ERP ships a
  client-side "instant nav"/offline shell that rewrites document navigations to the
  absolute production origin `https://rateb.sa/rateb-erp/public/...`. A real browser
  (Chrome/computer-use/Playwright) will therefore jump to rateb.sa after login even
  though the local server is correct. For reliable LOCAL verification: use `curl`/the
  REST API, or render with **JavaScript disabled** (all pages are server-rendered PHP,
  so the HTML/UI is complete without JS). `RATEB_MAIN_PLATFORM_HOSTS=127.0.0.1` fixes
  the *server-side* post-login redirect but does not stop the client-side JS jump.
- The `.env` dotenv bridge only injects DB_/control keys into `getenv()`. Non-DB env
  vars (e.g. `RATEB_MAIN_PLATFORM_HOSTS`) must be exported to the PHP process directly
  (as in the serve command above), not just placed in `.env`.
- ERP `/admin/companies` create is intentionally disabled in this build (defers to the
  Control Panel). Use `/admin/users/create` (or accounting/plans) to exercise CRUD.

### Lint / test / build
- No dedicated linter is configured; lint = PHP syntax (`php -l`). A repo-wide pass is
  clean except 14 pre-existing BI view templates under `rateb-erp/views/company/bi/**`
  that have a UTF-8 BOM before `declare(strict_types=1)` (pre-existing, not env-related).
- Worker-platform tests: `DB_HOST=127.0.0.1 DB_DATABASE=admin_rateb DB_USERNAME=rateb \
  DB_PASSWORD=rateb php tests/TestRunner.php`.
- POS V2 (gating CI suite): `cd rateb-erp && RATEB_ERP_DB_NAME=admin_rateb-erp \
  RATEB_ERP_DB_USER=rateb RATEB_ERP_DB_PASS=rateb DB_HOST=127.0.0.1 DB_USER=rateb \
  DB_PASS=rateb POS_V2_INTEGRATION_SEED=1 php modules/pos/tests/run-all-pos-v2-tests.php`.
- Platform catalog: `php rateb-platform-catalog/tests/run.php` (1 pre-existing failure,
  `ErpSessionFileReader decodes ERP session file`, depends on `$_SERVER['HTTP_HOST']`
  and fails under CLI; not part of gating CI).
- This is a PHP app — there is no build step for development.
