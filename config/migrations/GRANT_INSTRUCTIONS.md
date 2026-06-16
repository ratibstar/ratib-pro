# Grant Database Access — DirectAdmin / rateb.sa

Fix **"Access denied for user 'admin_rateb'@'localhost' to database 'admin_xxx'"** on country login and dashboards (Kenya, Uganda, Bangladesh, etc.).

---

## Quick fix (choose one)

### Option A — DirectAdmin UI (recommended — use when phpMyAdmin GRANT gives #1044)

**phpMyAdmin cannot GRANT** if you are logged in as `admin__XXXX...` (limited user). Use DirectAdmin instead.

Full steps: **`control-panel/DIRECTADMIN_GRANT_STEPS.md`**

Short version:

1. **DirectAdmin** `https://167.233.71.107:2222` → login **`admin`**
2. **Account Manager** → **MySQL Management**
3. **Add User to Database** — repeat 12 times:
   - User: **`admin_rateb`**
   - Database: each country DB (see table below)
   - Privileges: **ALL**
4. Verify: **https://rateb.sa/config/check_country_db_access.php?control=1**

### Option B — SQL via SSH only (not phpMyAdmin limited user)

```bash
ssh admin@167.233.71.107
mysql -u admin -p
```

Then paste **`control-panel/GRANT_COUNTRY_DBS_ADMIN_RATEB.sql`** and `FLUSH PRIVILEGES;`.

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
