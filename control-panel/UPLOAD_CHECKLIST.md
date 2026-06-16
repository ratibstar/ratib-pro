# Pre-upload checklist (verified)

## Fixed before upload

- **API**
  - `api/control/get_permissions_groups.php` – now self-contained (no dependency on `api/settings/get_permissions_groups.php`).
  - `api/control/settings-api.php` – returns a JSON stub so no 500 when system settings API is called (full API requires copying `api/settings/` from Rateb Pro if needed).

- **Missing pages**
  - `pages/control-registration-requests.php` – copied from Rateb Pro (embedded table view).
  - `pages/control-support-chats.php` – copied from Rateb Pro (embedded support chats).
  - `pages/system-settings.php` – stub that explains system settings are in Rateb Pro and optionally links to `RATEB_PRO_URL`.

- **Wrong URLs**
  - Login redirects use `pageUrl('login.php')` (no `?control=1`).
  - Dashboard redirect uses `pageUrl('control/dashboard.php')`.
  - `control-agencies.php` nav links point to `control/countries.php`, `control/agencies.php`, etc. (no `control-countries.php`).
  - Registration and support iframe URLs use `?embedded=1` only.

- **Stub assets (avoid 404)**
  - `css/nav.css`, `css/system-settings.css`
  - `js/utils/header-config.js`, `js/countries-cities.js`, `js/modern-forms.js`, `js/system-settings.js`

## Optional after upload

1. **Full system settings**  
   Copy from Rateb Pro: `api/settings/settings-api.php` and any files it includes into `api/settings/`. Then remove the stub in `api/control/settings-api.php` and include the real logic.

2. **HR / Accounting iframes**  
   Stub pages `pages/dashboard-hr.php` and `pages/dashboard-accounting.php` are included; they show a message and link to Rateb Pro. For full HR/Accounting UI, copy the real pages from Rateb Pro.

3. **RATEB_PRO_URL**  
   In `config/env.php`, define `RATEB_PRO_URL` (e.g. `https://rateb.sa`) so “My Own Pro” and “Open Rateb Pro” links work.

4. **Database**  
   Use the same control DB as Rateb Pro (e.g. run `config/migrations/separate_control_panel_db/` from Rateb Pro if needed).

5. **logs/**  
   Ensure `logs/` exists and is writable (or change `error_log` in `includes/config.php`).

## File layout (quick check)

- `index.php` (entry)
- `config/env.php`
- `includes/config.php`, `control-permissions.php`, `control/`, `control_pending_reg_alert.php`
- `pages/login.php`, `logout.php`, `select-country.php`, `select-agency.php`, `system-settings.php`, `home.php`, `control-agencies.php`, `control-registration-requests.php`, `control-support-chats.php`, `dashboard-hr.php`, `dashboard-accounting.php`
- `pages/control/*.php` (dashboard, countries, agencies, admins, etc.)
- `api/control/*.php`
- `core/*.php`
- `css/` (control/, login.css, nav.css, system-settings.css, control-pending-reg-alert.css)
- `js/` (control/, permissions.js, login.js, utils/, countries-cities.js, modern-forms.js, system-settings.js, control-pending-reg-alert.js)
- `logs/` (writable)
- `assets/` (optional; for logo)
