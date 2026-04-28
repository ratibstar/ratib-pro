# Ratib Control Panel (Standalone)

Standalone control panel extracted from Ratib Pro. Manages countries, agencies, registration requests, support chats, accounting, HR, and control admins.

## Structure

```
ratib-control-panel/
├── index.php              # Entry point → redirects to login or dashboard
├── config/
│   └── env.php            # Environment (DB, SITE_URL, etc.)
├── includes/
│   ├── config.php         # Main config, DB connection, session
│   ├── control-permissions.php
│   ├── control_pending_reg_alert.php
│   └── control/
│       ├── layout-wrapper.php
│       ├── sidebar.php
│       └── back-button.php
├── pages/
│   ├── login.php          # Control panel login (control_admins only)
│   ├── logout.php
│   ├── select-country.php
│   ├── select-agency.php
│   ├── control-agencies.php  # Embedded agencies table
│   └── control/           # All control panel pages
├── api/
│   └── control/           # All control panel APIs
├── css/
│   ├── control/system.css
│   └── login.css
├── js/
│   ├── control/system.js
│   ├── permissions.js
│   └── control-pending-reg-alert.js
└── core/                  # Database, Auth, BaseModel
```

## Setup

1. **Database**: Use the same `control_panel_db` (or `outratib_control_panel_db`) as Ratib Pro. Run migrations from `ratibprogram/config/migrations/separate_control_panel_db/` if needed.

2. **Environment**: Edit `config/env.php` or set env vars:
   - `CONTROL_PANEL_DB_NAME` – control database name
   - `CONTROL_DB_HOST`, `CONTROL_DB_USER`, `CONTROL_DB_PASS`
   - `CONTROL_SITE_URL` – e.g. `https://control.ratib.sa`
   - `CONTROL_BASE_URL` – base path if in subfolder (e.g. `/control-panel`)

3. **Web server**: Point document root to this folder, or use a subfolder and set `BASE_URL` accordingly.

4. **RATIB_PRO_URL** (optional): In `config/env.php`, define `RATIB_PRO_URL` for the "My Own Pro" sidebar link to open Ratib Pro with `?control=1&own=1`.

## URLs (no ?control=1 needed – always control mode)

- Login: `/pages/login.php`
- Dashboard: `/pages/control/dashboard.php`
- Countries: `/pages/control/countries.php`
- Agencies: `/pages/control/agencies.php`
- Registration: `/pages/control/registration-requests.php`
- API: `/api/control/*`
