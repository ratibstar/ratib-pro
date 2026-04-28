# Full review: what to upload and what to delete on the server

Use this list so the server matches your current project: **upload** the control panel folder and the new redirect, then **delete** the old control-panel paths that used to live in the Ratib Pro root.

---

## Option: Zip then upload (recommended)

You can zip the control panel and upload **one file** instead of 74.

**1. Create the zip (on your PC)**  
- Open your project folder: `ratibprogram`.  
- Select these **two** items: **`control.php`** (file) and **`control-panel`** (folder).  
- Right‑click → **Send to → Compressed (zipped) folder**.  
- Name it e.g. `control-panel-upload.zip`.

**2. Upload**  
- Upload `control-panel-upload.zip` to your **site root** on the server (same place as `index.php`).

**3. Unzip on the server**  
- Unzip the file **in the site root** so that after unzip you have:
  - `control.php` next to `index.php`
  - a folder `control-panel/` with all files inside  
- If your unzip creates a wrapper folder (e.g. `control-panel-upload/`), move `control.php` and `control-panel` from inside it to the site root, then delete the empty wrapper folder.

**4. Delete from server**  
- Still do **Part 2** below: delete the old control paths from the server so they don’t conflict.

---

## Part 1 — UPLOAD (74 items: 1 at root + 73 in control-panel)

Upload these from your local `ratibprogram` to the server in the **same relative paths**.  
Check off each line as you upload.

### At project root (1 file)

| # | Path | Description |
|---|------|-------------|
| 1 | `control.php` | Redirects to `/control-panel/`. Upload this so opening control.php goes to the control panel folder. |

### Inside `control-panel/` (77 files)

Upload the **entire** `control-panel` folder. Below is the full file list so you can verify nothing is missing.

#### Root of control-panel (4)

| # | Path |
|---|------|
| 2 | `control-panel/index.php` |
| 3 | `control-panel/README.md` |
| 4 | `control-panel/DEEPER_CHECK.md` |
| 5 | `control-panel/UPLOAD_CHECKLIST.md` |

#### control-panel/config (1)

| # | Path |
|---|------|
| 6 | `control-panel/config/env.php` |

#### control-panel/core (4)

| # | Path |
|---|------|
| 7 | `control-panel/core/Auth.php` |
| 8 | `control-panel/core/BaseModel.php` |
| 9 | `control-panel/core/bootstrap.php` |
| 10 | `control-panel/core/Database.php` |

#### control-panel/includes (6)

| # | Path |
|---|------|
| 11 | `control-panel/includes/config.php` |
| 12 | `control-panel/includes/control-permissions.php` |
| 13 | `control-panel/includes/control_pending_reg_alert.php` |
| 14 | `control-panel/includes/control/back-button.php` |
| 15 | `control-panel/includes/control/layout-wrapper.php` |
| 16 | `control-panel/includes/control/sidebar.php` |

#### control-panel/api/control (18)

| # | Path |
|---|------|
| 17 | `control-panel/api/control/accounting.php` |
| 18 | `control-panel/api/control/admins-api.php` |
| 19 | `control-panel/api/control/agencies.php` |
| 20 | `control-panel/api/control/agency-db-helper.php` |
| 21 | `control-panel/api/control/countries.php` |
| 22 | `control-panel/api/control/country-users-api.php` |
| 23 | `control-panel/api/control/get-control-countries-for-users.php` |
| 24 | `control-panel/api/control/get-countries-with-login.php` |
| 25 | `control-panel/api/control/get-current-user-permissions.php` |
| 26 | `control-panel/api/control/get-users-per-country.php` |
| 27 | `control-panel/api/control/get_permissions_groups.php` |
| 28 | `control-panel/api/control/permissions-check.php` |
| 29 | `control-panel/api/control/registration-requests.php` |
| 30 | `control-panel/api/control/save_user_permissions.php` |
| 31 | `control-panel/api/control/settings-api.php` |
| 32 | `control-panel/api/control/support-chats.php` |
| 33 | `control-panel/api/control/user_permissions.php` |

#### control-panel/css (5)

| # | Path |
|---|------|
| 34 | `control-panel/css/login.css` |
| 35 | `control-panel/css/nav.css` |
| 36 | `control-panel/css/system-settings.css` |
| 37 | `control-panel/css/control-pending-reg-alert.css` |
| 38 | `control-panel/css/control/system.css` |

#### control-panel/js (8)

| # | Path |
|---|------|
| 39 | `control-panel/js/login.js` |
| 40 | `control-panel/js/modern-forms.js` |
| 41 | `control-panel/js/permissions.js` |
| 42 | `control-panel/js/system-settings.js` |
| 43 | `control-panel/js/countries-cities.js` |
| 44 | `control-panel/js/control-pending-reg-alert.js` |
| 45 | `control-panel/js/control/system.js` |
| 46 | `control-panel/js/utils/header-config.js` |

#### control-panel/pages (11)

| # | Path |
|---|------|
| 47 | `control-panel/pages/login.php` |
| 48 | `control-panel/pages/logout.php` |
| 49 | `control-panel/pages/home.php` |
| 50 | `control-panel/pages/select-country.php` |
| 51 | `control-panel/pages/select-agency.php` |
| 52 | `control-panel/pages/system-settings.php` |
| 53 | `control-panel/pages/control-agencies.php` |
| 54 | `control-panel/pages/control-registration-requests.php` |
| 55 | `control-panel/pages/control-support-chats.php` |
| 56 | `control-panel/pages/dashboard-accounting.php` |
| 57 | `control-panel/pages/dashboard-hr.php` |

#### control-panel/pages/control (16)

| # | Path |
|---|------|
| 58 | `control-panel/pages/control/index.php` |
| 59 | `control-panel/pages/control/dashboard.php` |
| 60 | `control-panel/pages/control/countries.php` |
| 61 | `control-panel/pages/control/agencies.php` |
| 62 | `control-panel/pages/control/admins.php` |
| 63 | `control-panel/pages/control/country-users.php` |
| 64 | `control-panel/pages/control/accounting.php` |
| 65 | `control-panel/pages/control/hr.php` |
| 66 | `control-panel/pages/control/registration-requests.php` |
| 67 | `control-panel/pages/control/support-chats.php` |
| 68 | `control-panel/pages/control/system-settings.php` |
| 69 | `control-panel/pages/control/control-panel-settings.php` |
| 70 | `control-panel/pages/control/control-panel-users.php` |
| 71 | `control-panel/pages/control/ratib-pro-users.php` |
| 72 | `control-panel/pages/control/super-admin-tenants.php` |
| 73 | `control-panel/pages/control/README.md` |

**Total to upload: 74 files** (1 at root + 73 in control-panel). If your server already has an old `control-panel` folder, overwrite it with this full set. (If you counted 78 before, that may include empty folders or assets.)

---

## Part 2 — DELETE from server (old control panel paths)

These paths **no longer exist** in the Ratib Pro project because the control panel was moved into `control-panel/`. Delete them on the server so the server matches your cleaned project. Remove files first, then empty folders.

### Complete list of all files to delete (copy-paste reference)

```
own-program.php
pages/control-agencies.php
pages/control-registration-requests.php
pages/control-support-chats.php
pages/select-country.php
pages/select-agency.php
includes/control-config.php
includes/control-sidebar.php
includes/control-permissions.php
includes/control_pending_reg_alert.php
pages/control/accounting.php
pages/control/hr.php
pages/control/admins.php
pages/control/countries.php
pages/control/control-panel-settings.php
pages/control/super-admin-tenants.php
pages/control/ratib-pro-users.php
pages/control/country-users.php
pages/control/control-panel-users.php
pages/control/system-settings.php
pages/control/support-chats.php
pages/control/registration-requests.php
pages/control/agencies.php
pages/control/dashboard.php
pages/control/index.php
pages/control/README.md
api/control/settings-api.php
api/control/get_permissions_groups.php
api/control/get-control-countries-for-users.php
api/control/agency-db-helper.php
api/control/get-users-per-country.php
api/control/country-users-api.php
api/control/get-countries-with-login.php
api/control/registration-requests.php
api/control/user_permissions.php
api/control/save_user_permissions.php
api/control/permissions-check.php
api/control/get-current-user-permissions.php
api/control/accounting.php
api/control/countries.php
api/control/agencies.php
api/control/support-chats.php
api/control/admins-api.php
includes/control/back-button.php
includes/control/sidebar.php
includes/control/layout-wrapper.php
```

**Optional (delete if present):** `config/env/control_ratib_sa.php`, any `config/env/control*.php`, any `config/control_*.sql`, and folders `css/control/`, `js/control/` at site root.

**Order:** Delete the files above first, then remove the empty folders: `pages/control/`, `api/control/`, `includes/control/`.

---

### Files to delete (if they exist)

| # | Server path | Note |
|---|-------------|------|
| 1 | `own-program.php` | Old control entry; not used anymore. |
| 2 | `pages/control-agencies.php` | Now only in `control-panel/pages/`. |
| 3 | `pages/control-registration-requests.php` | Now only in `control-panel/pages/`. |
| 4 | `pages/control-support-chats.php` | Now only in `control-panel/pages/`. |
| 5 | `pages/select-country.php` | Now only in `control-panel/pages/`. |
| 6 | `pages/select-agency.php` | Now only in `control-panel/pages/`. |
| 7 | `includes/control-config.php` | Control config; removed from Ratib Pro. |
| 8 | `includes/control-sidebar.php` | Control sidebar; removed from Ratib Pro. |
| 9 | `includes/control-permissions.php` | Now only in `control-panel/includes/`. |
| 10 | `includes/control_pending_reg_alert.php` | Now only in `control-panel/includes/`. |

### Folders to delete (and everything inside)

Delete the **entire** folder. After deleting files inside, remove the empty folder.

| # | Server path | Contents to remove |
|---|-------------|--------------------|
| 11 | `pages/control/` | All PHP files (dashboard, countries, agencies, admins, accounting, hr, registration-requests, support-chats, system-settings, etc.). |
| 12 | `api/control/` | All control API PHP files. |
| 13 | `includes/control/` | layout-wrapper.php, sidebar.php, back-button.php, etc. |

### Optional (if you had these on the server)

| # | Server path | When to delete |
|---|-------------|----------------|
| 14 | `config/env/control_ratib_sa.php` | If you had a control-specific env file. |
| 15 | `config/env/control*.php` | Any other `config/env/control*.php` files. |
| 16 | `config/control_*.sql` | Any control SQL scripts in `config/`. |
| 17 | `css/control/` | Only if you had control-only CSS at **root** (not inside control-panel). |
| 18 | `js/control/` | Only if you had control-only JS at **root** (not inside control-panel). |

---

## Part 3 — Updated Ratib Pro files (upload these to the server)

These files were **modified** to remove control panel logic from the main app. Upload them from your local `ratibprogram` to the server (same paths) so the server has the cleaned Ratib Pro version.

### Complete list (all paths relative to site root)

```
config/env/load.php
includes/config.php
index.php
core/bootstrap.php
pages/login.php
pages/dashboard.php
pages/logout.php
pages/check-login.php
pages/system-settings.php
pages/dashboard-hr.php
pages/dashboard-accounting.php
includes/header.php
includes/permissions.php
includes/TenantLoader.php
core/Auth.php
api/get-current-user-permissions.php
api/settings/settings-api.php
api/settings/get_permissions_groups.php
api/permissions/save_user_permissions.php
```

**Total: 19 files.** Upload each to the same path on the server (e.g. `includes/config.php` → server `includes/config.php`).

---

## Summary

- **Upload:** `control.php` + the full `control-panel/` folder so the control panel runs from `control-panel/`.
- **Delete:** The old control paths listed in Part 2 so the server no longer has control panel code mixed in the Ratib Pro root.
- **Upload (updated):** The 19 Ratib Pro files in Part 3 so the server has the version with control panel logic removed.

After this, the control panel is only under `control-panel/`, and opening `control.php` will redirect to it.
