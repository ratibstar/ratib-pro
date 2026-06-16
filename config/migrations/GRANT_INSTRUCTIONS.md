# Grant Database Access — DirectAdmin / rateb.sa

Fix **"Access denied for user 'admin_rateb'@'localhost' to database 'admin_xxx'"** on country login and dashboards (Kenya, Uganda, Bangladesh, etc.).

---

## Quick fix (choose one)

### Option A — DirectAdmin UI (recommended)

1. Log in to **DirectAdmin** → **User Level** → **MySQL Management**
2. Open user **`admin_rateb`**
3. Assign **ALL PRIVILEGES** on every country database:

| Database |
|----------|
| admin_bangladesh |
| admin_ethiopia |
| admin_genia |
| admin_indonesia |
| admin_kenya |
| admin_nepal |
| admin_nigeria |
| admin_philippines |
| admin_rwanda |
| admin_sri_lanka |
| admin_thailand |
| admin_uganda |

4. Save

### Option B — SQL (phpMyAdmin as MySQL admin, not admin_rateb)

Run **`control-panel/GRANT_COUNTRY_DBS_ADMIN_RATEB.sql`** in phpMyAdmin while logged in as the DirectAdmin MySQL **admin** user (or root).

---

## Verify

1. Open: **https://rateb.sa/config/check_country_db_access.php?control=1**  
   All databases should show ✓ OK.

2. Reset test logins (after grants work):
   ```bash
   php pages/rateb-reset-country-test-admin.php
   ```

3. Full audit:
   ```bash
   php pages/rateb-check-all-country-dbs.php
   ```

4. Test login: **https://rateb.sa/bangladesh/login** → `admin` / `123456`

---

## phpMyAdmin tip (section B checks)

When checking a country DB manually, **click the database in the left sidebar** (`admin_bangladesh`, etc.) **before** running queries.  
If you paste `USE admin_bangladesh` with other SQL, phpMyAdmin may stay on `admin_control_panel_db` — that is why you saw `users` with 0 rows (control panel DB, not Bangladesh).

Use **`control-panel/CHECK_ONE_COUNTRY_DB.sql`** per database.

---

## Why all countries fail the same way

Every agency in `control_agencies` uses **`db_user = admin_rateb`**.  
That MySQL user can reach `admin_rateb` and `admin_control_panel_db`, but **was never granted** the 12 per-country databases after the cPanel → DirectAdmin move.

One GRANT fix unlocks **all** countries at once.
