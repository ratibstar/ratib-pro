# Option A — Run All (Automated)

Everything is set up. Follow these steps:

---

## Before You Start

1. **Re-import** the SQL file into the small databases (ethiopia, indonesia, kenya, etc.) so they have full data.
2. **Grant access**: In cPanel → MySQL Databases, add user `outratib_out` to **all** country databases with ALL PRIVILEGES.

---

## Run the Automated Script

### Option 1: From browser

1. Upload the project to your server (if not already).
2. Open: **https://out.ratib.sa/config/run_option_a_setup.php**

   (If `config` is not web-accessible, use Option 2.)

3. The script will:
   - Run `DELETE FROM control_agencies WHERE country_id != X` in each country database
   - Run `UPDATE control_agencies` in the main DB to point each country to its own database

### Option 2: From command line (SSH)

```bash
cd /path/to/ratibprogram
php config/run_option_a_setup.php
```

---

## If the Script Fails

Run the SQL manually:

### Step 1: DELETE (in each country database)

In phpMyAdmin, for each database run the matching line from:

`config/migrations/option_a_01_delete_per_country.sql`

### Step 2: UPDATE (in outratib_out)

Run the full contents of:

`config/migrations/option_a_02_update_control_agencies_all.sql`

---

## After Running

1. Test each country’s login URL.
2. Delete or protect `config/run_option_a_setup.php` so it cannot be run by others.
