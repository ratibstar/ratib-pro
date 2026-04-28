# Deeper check report – Ratib Control Panel (standalone)

This document summarizes a deeper check of flows, APIs, sessions, DB usage, and edge cases.

---

## 1. Session and auth

- **Session name:** `ratib_control` (set in `includes/config.php`). No conflict with Ratib Pro.
- **Session keys:** `control_logged_in`, `control_user_id`, `control_username`, `control_full_name`, `control_permissions`, `control_agency_id`, `control_country_id`, etc. Refreshed from `control_admin_permissions` on each request when logged in.
- **Login:** `pages/login.php` uses POST to self; no `login.js` (no webauthn). Redirect after login: `pageUrl('control/dashboard.php')`.
- **All control APIs** under `api/control/` require `$_SESSION['control_logged_in']` (or `IS_CONTROL_PANEL` + same). `get-current-user-permissions.php` returns permissions when logged in, `success: false` when not.
- **Redirect when not logged in:** Pages redirect to `pageUrl('login.php')`. Config redirects to `pageUrl('select-country.php')` when logged in but no agency/country and not on login/select/logout/dashboard/api.

---

## 2. API base and JS

- **Layout wrapper** sets `#control-config` with `data-api-base` = full base + `/api/control`, and `#app-config` with `data-base-url`, `data-api-base` (base + `/api`), and `data-control-api-path` (base + `/api/control`). Script initializes `APP_CONFIG.baseUrl`, `APP_CONFIG.apiBase`, `APP_CONFIG.controlApiPath`, `BASE_PATH`.
- **Pages that set API base:** All main control pages set `$apiBase = $baseUrl . '/api/control'` and pass it into JS (e.g. `API_BASE`, `apiBase`). Registration-requests and support-chats use the same pattern; dashboard/country-users/super-admin-tenants use `#control-config` or inline `apiBase`.
- **permissions.js:** Uses `APP_CONFIG.baseUrl` + `/api/control/get-current-user-permissions.php` when `data-control="1"`. Correct for standalone.
- **control-pending-reg-alert.js:** `getApiBase()` builds `origin + pathBase + '/api/control'` from `location.pathname`; correct for subfolder installs.
- **system.js:** Uses `#control-config` → `dataset.apiBase` or fallback `/api/control` for `registration-requests.php`. Correct.
- **Fix applied:** HR and Accounting stub pages now include `#control-config` and `data-control-api-path` so shared JS gets the control API base consistently.

---

## 3. Database

- **Connections:** `config.php` sets `$GLOBALS['control_conn']` to the control DB (`CONTROL_PANEL_DB_NAME`). On agency switch (`?agency_id=...`), `$GLOBALS['conn']` is switched to the selected agency DB; otherwise `$GLOBALS['conn'] = $GLOBALS['control_conn']`.
- **APIs:** Control-only APIs use `$ctrl = $GLOBALS['control_conn']`. Agency-scoped APIs (e.g. `country-users-api.php`) require `agency_id`, then connect to that agency’s DB; they do not use `$GLOBALS['conn']` for the main list.
- **get-users-per-country.php:** Uses `$ctrl` only; connects per-agency inside the loop. No use of default `$conn` for user listing.
- **Tables:** All APIs that need tables run `SHOW TABLES LIKE '...'` (and sometimes `SHOW COLUMNS`) and return JSON errors when missing. Schema in `ratibprogram/config/migrations/separate_control_panel_db/02_create_tables.sql` includes `control_registration_requests.updated_at` and `control_agencies.country_id`, `base_url`, etc., consistent with API usage.

---

## 4. Security (SQL and input)

- **APIs:** User-controlled values (ids, search, status, etc.) are either cast to int, validated against whitelists (e.g. status in `['open','closed']`, action in `['approve','reject']`), or used in prepared statements. `accounting.php` uses `$MODULES[$module]` for table name (whitelist); no raw `$_GET` table name in SQL.
- **Escaping:** Where dynamic SQL is used (e.g. status in support-chats, registration-requests), values are passed through `real_escape_string` after validation. Prepared statements used for most writes and parameterized queries.

---

## 5. Flows

- **Login → Dashboard:** Login posts to self, sets session, redirects to `pageUrl('control/dashboard.php')`.
- **Select country/agency:** `select-country.php` → `select-agency.php` → `?agency_id=...` handled in config → redirect to `pageUrl('control/dashboard.php')`. Sidebar “Select Country” → `pageUrl('select-country.php')`.
- **Menu links:** All point to `pageUrl('control/...')` or `pageUrl('select-country.php')`, `pageUrl('logout.php')`, `pageUrl('system-settings.php')`. No `?control=1` in internal navigation (only where linking out to Ratib Pro, e.g. RATIB_PRO_URL).
- **Logout:** `pageUrl('logout.php')` then redirect to `pageUrl('login.php')?message=logged_out`.

---

## 6. Edge cases

- **No agency selected:** Config redirects to `pageUrl('select-country.php')` when appropriate (excluding login, select, dashboard, api, logout).
- **country-users:** Requires permission; API requires `agency_id` and returns an error if missing. Page uses country select then agency/users; no silent wrong-DB use.
- **Empty countries/agencies:** APIs return `list: []` / `countries: []`; pages handle empty state.
- **RATIB_PRO_URL not set:** Sidebar “My Own Pro” and stub pages (home, HR, accounting, system-settings) can show stub message or `#`; documented in README/UPLOAD_CHECKLIST.
- **Login page:** Does not load `login.js`; no webauthn. No `/api/control/webauthn/` endpoints in standalone (intentional).

---

## 7. Files and includes

- Every `require_once`/`include` in the control panel project points to an existing file.
- `asset()` and `pageUrl()` used consistently; no leftover Ratib Pro–only paths in critical paths.
- **IS_CONTROL_PANEL:** Defined in `config/env.php` and in several API scripts; all control APIs that check it are consistent.

---

## 8. Fixes applied in this pass

1. **HR and Accounting pages:** Added `#control-config` with `data-api-base="<?php echo ... ?>/api/control"` and extended `#app-config` with `data-control-api-path` and `APP_CONFIG.controlApiPath` so any shared JS (e.g. system.js, permissions) gets the correct control API base on these stubs.

---

## 9. Optional follow-ups

- Copy full `api/settings/` from Ratib Pro if full system settings are needed (settings-api is currently a stub).
- Ensure `logs/` exists and is writable for `config.php` error_log.
- Run DB migrations (`01_create_database.sql`, `02_create_tables.sql`) if the control DB is new.
- Set `RATIB_PRO_URL` in `config/env.php` if you want “My Own Pro” and stub links to open Ratib Pro.

---

## 10. Deeeep check (second pass)

### Core / bootstrap
- **bootstrap.php** loads `Database.php`, `Auth.php`, `BaseModel.php`. Control panel does **not** call `Database::` or `Auth::` or `BaseModel` in any page/API; it uses mysqli `$control_conn` everywhere. Core classes are present for compatibility; `env.php` sets `DB_NAME` = `CONTROL_PANEL_DB_NAME` so `Database::getConnection()` would point at the control DB if ever used.
- **control-permissions.php:** `getAllowedCountryIds($ctrl)` returns `null` (no restriction), `[]` (no access), or array of ids. All APIs that use it handle `null` and `[]` correctly. `requireControlPermission($p1, $p2, ...)` grants if **any** permission matches.

### API input handling
- **JSON body:** All APIs use `json_decode(file_get_contents('php://input'), true) ?: $_POST` or similar. When body is malformed, `json_decode` returns `null`; code then uses `$_POST` or validates `$input` and returns a clear error (e.g. "Missing user_id or permissions"). No unchecked null dereference.
- **user_permissions.php / save_user_permissions.php:** Explicit checks for `isset($input['user_id'])`, `isset($input['permissions'])`, and type checks on permissions array.

### XSS / JS embedding
- **API_BASE in JS:** Four pages embedded `$apiBase` with `addslashes()`, which is brittle for backslashes or quotes in URLs. **Fixed:** Replaced with `json_encode($apiBase)` in:
  - `pages/control-registration-requests.php`
  - `pages/control/admins.php`
  - `pages/control/countries.php`
  - `pages/control-agencies.php`
- **Echo in pages:** User/DB-derived output in pages uses `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` or safe constructs (e.g. `(int)$r['id']`). Registration-requests table uses `htmlspecialchars` for agency_name, contact_email, etc.

### Base path / REQUEST_URI edge case
- When `REQUEST_URI` is exactly `/` (root), `preg_replace('#/pages/[^?]*.*$#', '', '/')` does not match, so the base path became `/`. That produced a double slash in URLs: `https://host//api/control`. **Fixed:** Use `rtrim(..., '/')` on the base path in:
  - `includes/control_pending_reg_alert.php`
  - `includes/control/layout-wrapper.php` (both `$apiBase` and `$fullBase` computation).

### Pending registration alert
- **Included only on:** control-agencies, control-registration-requests, control-support-chats, select-country. **Not** in `endControlLayout()`, so dashboard and other layout pages do not show the popup. To show it on all layout pages, add `require_once` for `control_pending_reg_alert.php` in `endControlLayout()` before `</body>`.
- **control-pending-reg-alert.js** derives API base from `location.pathname`; it does not use the PHP `$controlAlertApiBase` variable (that variable is computed but not output into the page).

### Agency DB helper
- **agency-db-helper.php:** `getAgencyDbConnection($agency, $countryId)` returns `null` on failure. Callers (country-users-api, get-users-per-country) check for null and return JSON errors. Alternate DB name fallbacks (e.g. Bangladesh/Sri Lanka) are present; one alternate uses `'sri Lanka'` (space) for a possible typo in DB name.

### Fixes applied in this (deeeep) pass
1. **API_BASE:** Use `json_encode($apiBase)` instead of `addslashes($apiBase)` in the four pages above.
2. **Base path:** Use `rtrim(preg_replace(...), '/')` in control_pending_reg_alert and layout-wrapper to avoid `//` when `REQUEST_URI` is `/`.

---

*Report generated after deeper check of flows, APIs, sessions, DB, and JS. Second pass: core usage, input handling, XSS/embedding, base path edge case, pending alert scope.*
