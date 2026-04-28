# Ratib Program – Entire System Check Report

**Date:** February 11, 2026  
**Scope:** Full codebase – structure, security, consistency, APIs, frontend, config, docs.

---

## 1. Executive Summary

| Area              | Status   | Notes                                      |
|-------------------|----------|--------------------------------------------|
| Project structure | ✅ Good  | Clear `api/`, `pages/`, `includes/`, `js/`, `css/` |
| Entry point       | ✅ Good  | `index.php` → login or dashboard           |
| Authentication    | ✅ Good  | Session + `password_verify`, prepared stmts |
| Permissions       | ✅ Good  | Middleware used across many APIs           |
| Documentation     | ✅ Good  | `COMPLETE_PROGRAM_DOCUMENTATION.md` etc.   |
| Config & secrets  | ⚠️ Fix   | Credentials in repo; duplicate config file |
| Error handling    | ⚠️ Fix   | `includes/error_handler.php` is empty      |
| CORS              | ⚠️ Review| `Access-Control-Allow-Origin: *` in API    |
| SQL safety        | ⚠️ Review| Some dynamic table names (whitelist needed)|
| Linter            | ✅ OK    | No errors on sampled files                 |

---

## 2. Project Structure

- **Root:** `index.php`, docs (e.g. `COMPLETE_PROGRAM_DOCUMENTATION.md`, `USER_TRAINING_GUIDE.md`).
- **includes:** `config.php`, `header.php`, `footer.php`, `permissions.php`, `permission_middleware.php`, overlays, modals.  
  - `sidebar.php` is **empty** (navigation is in `header.php` → `main-nav`).
  - `error_handler.php` is **empty** (see below).
- **config:** `database.php` – **duplicate** of `includes/config.php` (same DB credentials and helpers). Only `pages/visa.php` uses it; rest use `includes/config.php`.
- **pages:** 27 PHP pages (dashboard, login, Worker, agent, subagent, accounting, hr, reports, help-center, settings, etc.).
- **api:** 260+ PHP files in logical folders (admin, agents, workers, accounting, hr, reports, help-center, etc.).
- **js:** 77+ JS files (modern-forms.js, navigation, module-specific scripts).
- **css:** Themed by module (dashboard, accounting, hr, help-center, etc.).

---

## 3. Configuration & Security

### 3.1 Credentials in repository

- **Issue:** `includes/config.php` and `config/database.php` both contain:
  - `DB_USER`, `DB_PASS`, `DB_NAME`, `DB_HOST`, `DB_PORT`
  - Same credentials duplicated.
- **Recommendation:**
  - Move credentials to environment variables or a `.env` file (outside web root or in `.gitignore`).
  - Load them in one place (e.g. `includes/config.php`) and do not commit `.env` or any file with real passwords.

### 3.2 Duplicate config

- **Issue:** `config/database.php` is almost a copy of `includes/config.php`. `pages/visa.php` uses `../config/database.php` then later `../includes/config.php`.
- **Recommendation:** Use only `includes/config.php` everywhere and remove dependency on `config/database.php` (see fixes below).

### 3.3 Session & PHP settings

- **Good:** `config.php` sets:
  - `session.cookie_httponly`, `session.use_only_cookies`
  - `session.cookie_secure` when HTTPS is on
  - `date_default_timezone_set('Asia/Riyadh')`
  - `display_errors = 0`, `log_errors = 1`, production-oriented settings

### 3.4 API protection

- **Good:** `api/.htaccess` denies direct access to `config.php`, `database.php`, `.env`.
- **CORS:** `Access-Control-Allow-Origin: *` – acceptable for same-domain only; if API is used from other domains, restrict origin.

---

## 4. Authentication & Authorization

### 4.1 Login (`pages/login.php`)

- Uses **prepared statement** for username lookup.
- Uses **password_verify()** for password.
- Sets `$_SESSION['user_id']`, `username`, `role_id`, `logged_in`, `user_permissions`, `user_specific_permissions`.
- Status check for `active` users.

### 4.2 Page protection

- **Dashboard** and other main pages check:
  - `$_SESSION['user_id']` and `$_SESSION['logged_in']`
  - `hasPermission('view_dashboard')` (or relevant permission).
- Redirect to `pageUrl('login.php')` when not logged in or not allowed.

### 4.3 API permission usage

- Many API files use `checkApiPermission()` or `checkPermission(..., true)` (from `permission_middleware.php`).
- Dozens of endpoints use permission checks; a few endpoints may be intentionally public (e.g. login, help content).  
- **Recommendation:** Audit any API that does not call `checkPermission` / `checkApiPermission` and confirm they are either public by design or protected elsewhere.

---

## 5. Database & SQL Safety

### 5.1 Good practices

- **Workers:** `api/workers/core/*` use prepared statements (`prepare`, `bind_param`, `execute`).
- **Login:** Prepared statement + `password_verify`.
- **Header:** Company name loaded with prepared statement.

### 5.2 Dynamic table / identifier usage

- **api/reports/reports.php:** `$conn->query("SHOW COLUMNS FROM {$tableName}")`, `"SHOW TABLES LIKE '{$table}'"`, `"DELETE FROM {$table}"`, etc.  
  - Ensure `$tableName` / `$table` are **whitelisted** (e.g. from a fixed list of allowed tables), not raw user input.
- **api/admin/clear_all_data.php:** Similar pattern with `$table`.
- **api/settings/settings-api.php:** `"SHOW TABLES LIKE '{$tableName}'"`, `"SHOW COLUMNS FROM \`{$tableName}\`"` – same whitelist requirement.
- **api/accounting/core/ReceiptPaymentVoucherManager.php:** PDO with table/column names in SQL – ensure these are from a controlled list.
- **api/accounting/accounts.php:** Mix of `real_escape_string` and dynamic identifiers – prefer whitelisting table/column names.

**Recommendation:** For any query that uses variable table or column names, allow only values from a predefined list (e.g. array of allowed table names) and never interpolate user input directly.

---

## 6. Frontend & Assets

- **Header:** Loads Bootstrap, Font Awesome, Select2, nav.css, chat-widget.css; page-specific CSS via `$pageCss`.
- **Navigation:** `js/navigation.js` – desktop hover expand, mobile toggle, overlay; permission-based nav links via `data-permission`.
- **Helpers:** `pageUrl()`, `asset()`, `apiUrl()` used consistently in pages.
- **Linter:** No issues reported on `js/modern-forms.js`, `pages/dashboard.php`, `api/workers/core/get.php`.

---

## 7. Issues to Fix

### 7.1 Empty `includes/error_handler.php`

- File is effectively empty; no centralized error handling.
- **Recommendation:** Implement a minimal handler (e.g. set `set_exception_handler` / `set_error_handler` to log and return a generic message in production). If you want, we can add a minimal implementation.

### 7.2 Unify config (remove `config/database.php`)

- **Recommendation:** In `pages/visa.php`, remove `require_once '../config/database.php'` and use only `require_once __DIR__ . '/../includes/config.php'` at the top (and ensure session is started by config). Then you can deprecate or delete `config/database.php` to avoid duplicate credentials and logic.

### 7.3 Credentials and CORS

- Move DB credentials to environment and restrict CORS if the API is not meant to be public (as above).

---

## 8. What’s Working Well

- Clear separation of API, pages, includes, and assets.
- Single entry point and consistent use of `config`, `pageUrl`, `asset`, `apiUrl`.
- Login and core flows use prepared statements and password hashing.
- Permission system integrated in many APIs and in nav.
- Rich documentation (e.g. `COMPLETE_PROGRAM_DOCUMENTATION.md`, training guides).
- Session and PHP settings tuned for production.
- No linter errors on the files checked.

---

## 9. Recommended Next Steps

1. **Immediate:** Unify config (visa.php → `includes/config.php` only; then remove or deprecate `config/database.php`).
2. **Immediate:** Add a minimal error handler in `includes/error_handler.php` and include it from `config.php` if desired.
3. **Short term:** Move DB credentials to environment variables and document in README or deployment docs.
4. **Short term:** Audit APIs that don’t use `checkPermission` / `checkApiPermission`.
5. **Short term:** Review all dynamic table/column names in SQL and enforce whitelists.
6. **Optional:** Restrict CORS in `api/.htaccess` to your actual front-end origin(s).

If you want, the next step can be applying the immediate fixes (visa.php + error_handler.php) in the codebase.
