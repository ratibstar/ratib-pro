# Country-Isolated Login Setup

**Goal:** Each country has its own login page. Users from Country A cannot log in at Country B's URL. No conflicts between countries.

---

## How It Works

1. **Each country = unique URL**  
   - Bangladesh: `https://bangladesh.out.ratib.sa/pages/login.php`  
   - Main (Saudi): `https://out.ratib.sa/pages/login.php`

2. **URL determines country**  
   `SITE_URL` is matched against `control_agencies.site_url` to get `control_countries.id`.

3. **Login checks country**  
   - User must have `users.country_id` = agency's `control_countries.id`  
   - Or be super_admin (`country_id` NULL)

4. **Session is per host**  
   Sessions are not shared across subdomains, so each country has its own session.

---

## Setup Steps

### 1. Run the migration

In phpMyAdmin on your **agency database** (e.g. `outratib_out`):

```sql
-- From config/migrations/country_isolated_login_001_users_control_country.sql
ALTER TABLE users ADD COLUMN country_id INT UNSIGNED NULL DEFAULT NULL;
UPDATE users SET country_id = 1 WHERE country_id IS NULL OR country_id = 0;
UPDATE users SET country_id = NULL WHERE role_id = 1;
```

Adjust `country_id = 1` if your Bangladesh country has a different `control_countries.id`.  
Assign Main users to `country_id = 2` (or your Main country id).

### 2. Ensure `control_agencies` has correct `site_url`

Each agency must have a unique `site_url` that matches the host:

| Country   | site_url                      | Host                    |
|-----------|-------------------------------|-------------------------|
| Bangladesh| https://bangladesh.out.ratib.sa | bangladesh.out.ratib.sa |
| Main      | https://out.ratib.sa          | out.ratib.sa            |

### 3. Assign users to countries

- Bangladesh users: `UPDATE users SET country_id = 1 WHERE ...`
- Main users: `UPDATE users SET country_id = 2 WHERE ...`
- Super admin: `country_id = NULL` (can log in from any country)

### 4. DNS / env files

- `bangladesh.out.ratib.sa` → env file `config/env/bangladesh_out_ratib_sa.php` or `agency_resolver`
- `out.ratib.sa` → env file or `agency_resolver` from `control_agencies`

---

## Option: Separate DB per country (no shared DB)

If each country has its own database, no extra logic is needed. Different DB = different users.  
Ensure each agency in `control_agencies` has its own `db_name` and `site_url`.

---

## Option: Subdomain multi-tenant (single shared DB)

For `sa.out.ratib.sa`, `bd.out.ratib.sa`, etc. with one shared DB:

1. Set `MULTI_TENANT_SUBDOMAIN_ENABLED = true` in config
2. Run `enterprise_multi_tenant_001_schema.sql` (creates `countries` table)
3. `TenantResolver` sets `TENANT_ID` from subdomain
4. `Auth::login` restricts by `users.country_id` = `TENANT_ID`

---

## Troubleshooting

| Issue | Fix |
|-------|-----|
| "Access denied. This account is not valid for this country" | User's `country_id` does not match the agency's country. Update `users.country_id` or use the correct login URL. |
| No country resolved | Check `control_agencies.site_url` matches `SITE_URL` (from env or `agency_resolver`). |
| Super admin can't log in | Ensure `users.country_id IS NULL` and `tenant_role = 'super_admin'` (or `role_id = 1`). |
