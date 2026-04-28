# Single URL Mode – All Countries Use Same Login Link

## Overview

When **Single URL Mode** is enabled, all countries use the same login URL:

**https://out.ratib.sa/pages/login.php**

Users select their country from the dropdown, enter credentials, and the system connects to that country’s database. After login, all requests use the selected country’s DB.

## How It Works

1. **Login page** – Uses `outratib_out` (control DB) to load the country list from `control_countries`.
2. **Login POST** – User selects country → system looks up DB in `control_agencies` by `country_id` → connects to that country’s DB → validates credentials.
3. **After login** – Session stores `country_id` and `logged_in`. On each request, config switches `$conn` to that country’s DB.

## Configuration

- **`config/env/out_ratib_sa.php`** – Sets `SINGLE_URL_MODE = true` and `SITE_URL = https://out.ratib.sa`.
- **`includes/config.php`** – Session-based DB switching when `country_id` and `logged_in` are in session.
- **`pages/login.php`** – Connects to the selected country’s DB before validating credentials.

## Requirements

1. **`outratib_out`** must have:
   - `control_countries` – All countries (Bangladesh, Ethiopia, Indonesia, etc.).
   - `control_agencies` – One row per country with `country_id`, `db_host`, `db_port`, `db_user`, `db_pass`, `db_name`.

2. **Run Option A migrations** on `outratib_out`:
   - `config/migrations/option_a_02_update_control_agencies_all.sql` – Maps each `country_id` to its DB.

3. **Country DBs** – Each country DB (e.g. `outratib_bangladesh`, `outratib_ethiopia`) must exist and have the app schema.

## Control Panel – Ratib Pro Users

The control panel (e.g. `?control=1`) uses `outratib_out` and can manage users across all countries. The “Ratib Pro Users” feature will list users from all country DBs – you can use the Ratib Pro Users page (sidebar) to manage users per country.

## Subdomains vs Single URL

- **Subdomains** (e.g. `bangladesh.out.ratib.sa`) – Still work via `agency_resolver`; each subdomain uses its own DB.
- **Single URL** (`out.ratib.sa`) – Uses country dropdown and session-based DB switching.

## For developers – different country DBs

We use **different databases per country**. Any code that reads or writes users, roles, permissions, agents, workers, or other app data must use the **current country’s connection**:

- **Pages / includes:** Use `$GLOBALS['conn']` (mysqli) — config.php sets it to the country DB when `country_id` is in session.
- **API scripts:** Ensure `config.php` (or the loader that sets `$GLOBALS['agency_db']`) is loaded first; then `Database::getInstance()->getConnection()` (PDO) will use the same country DB. For APIs that only use mysqli, use `$GLOBALS['conn']` when available so save and load use the same DB.
- **Control panel** (e.g. `?control=1`) may use the main/control DB for cross-country operations; Ratib Pro app logic must always use the country DB for that session.
