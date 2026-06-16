# DirectAdmin — grant admin_rateb to all country DBs

## Why phpMyAdmin GRANT failed

Error **#1044** with user like `admin__1VpRNUtMnyE47bduY...` means phpMyAdmin logged you in as a **limited per-database user**, not the account owner. That user **cannot** run `GRANT`.

**Do not** paste `GRANT_COUNTRY_DBS_ADMIN_RATEB.sql` in that phpMyAdmin session.

---

## Fix in DirectAdmin (use this)

1. Open **DirectAdmin** (not phpMyAdmin):  
   `https://167.233.71.107:2222`  
   Login: Linux user **`admin`**

2. Go to **Account Manager** → **MySQL Management**  
   (older skins: **MySQL Databases**)

3. Scroll to **“Add User to Database”** (or **“Grant access”**)

4. For **each** database below, repeat:
   - **User:** `admin_rateb`
   - **Database:** pick one country DB
   - **Privileges:** **ALL** / **ALL PRIVILEGES**
   - Click **Add** / **Save**

| # | Database |
|---|----------|
| 1 | admin_bangladesh |
| 2 | admin_ethiopia |
| 3 | admin_genia |
| 4 | admin_indonesia |
| 5 | admin_kenya |
| 6 | admin_nepal |
| 7 | admin_nigeria |
| 8 | admin_philippines |
| 9 | admin_rwanda |
| 10 | admin_sri_lanka |
| 11 | admin_thailand |
| 12 | admin_uganda |

5. When done, open in browser:  
   **https://rateb.sa/config/check_country_db_access.php?control=1**  
   All 12 should show **OK**.

6. SSH (optional) reset logins:
   ```bash
   cd /home/admin/domains/rateb.sa/public_html
   php pages/rateb-reset-country-test-admin.php
   ```

---

## Alternative — SSH + mysql as account owner

Only if DirectAdmin UI is missing “Add user to database”:

```bash
ssh admin@167.233.71.107
mysql -u admin -p
```

Then paste the `GRANT` lines from `GRANT_COUNTRY_DBS_ADMIN_RATEB.sql` and `FLUSH PRIVILEGES;`.

If `mysql -u admin` fails, use the MySQL password from DirectAdmin → **MySQL Management** → **Access Details** for user `admin` (account owner DB user, not `admin_rateb`).

---

## Check one country in phpMyAdmin (after grants)

1. Open phpMyAdmin from DirectAdmin.
2. In the **left sidebar**, **click** `admin_bangladesh` (must be **bold/highlighted**).
3. Top of page must say **Database: admin_bangladesh** (not `admin_control_panel_db`).
4. SQL tab → run **only** these 4 lines:

```sql
SHOW TABLES LIKE 'users';
SELECT COUNT(*) AS user_count FROM users;
SELECT user_id, username, email FROM users WHERE username = 'admin' LIMIT 1;
SELECT username FROM users ORDER BY user_id LIMIT 10;
```

Repeat: click `admin_kenya` in sidebar → run same 4 lines, etc.

If you still see `Tables_in_admin_control_panel_db`, you did **not** switch database in the sidebar.
