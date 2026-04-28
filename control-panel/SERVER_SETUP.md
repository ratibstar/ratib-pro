# Control panel – server setup (out.ratib.sa)

## 1. control.php not found

**Problem:** `https://out.ratib.sa/control.php` shows "File not found".

**Fix:** Upload the file **`control.php`** to the **site root** on the server (the same folder where `index.php` is, usually `public_html` or `httpdocs`).

- In cPanel → File Manager → go to the site root.
- Upload the `control.php` file from your project (root of `ratibprogram`).
- So you have: `index.php`, `control.php`, `control-panel/`, etc. in the same folder.

---

## 2. "Control panel database unavailable" (can't login)

**Problem:** Login page shows "Control panel database unavailable." and login fails.

**Cause:** The control panel uses its **own database**. That database must exist on the server and the credentials in `control-panel/config/env.php` must be correct.

### Option A – Create the control panel database (recommended)

1. **Create the database**
   - cPanel → **MySQL® Databases**.
   - Create a new database, e.g. `outratib_control_panel_db` (or use the name your host allows, e.g. `cpaneluser_controlpanel`).

2. **Assign the MySQL user**
   - In the same MySQL Databases page, add the **same MySQL user** that Ratib Pro uses (e.g. `outratib_out`) to this new database with **ALL PRIVILEGES**.

3. **Create tables**
   - cPanel → **phpMyAdmin** (or MySQL Remote).
   - Select the new control panel database.
   - Run the SQL from:
     - `config/migrations/separate_control_panel_db/01_create_database.sql` (only the `CREATE DATABASE` part if the DB was created in cPanel; otherwise run as-is).
     - `config/migrations/separate_control_panel_db/02_create_tables.sql`  
   - If your database name is different (e.g. with a prefix), change the first line of `02_create_tables.sql` from `USE outratib_control_panel_db;` to `USE your_actual_db_name;`.

4. **Create an admin user**
   - In the control panel database, ensure table `control_admins` has at least one user. Example (set your own password):
   ```sql
   INSERT INTO control_admins (username, password, full_name, is_active)
   VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 1);
   ```
   - The hash above is for password `password`. To use a different password, generate a new hash in PHP: `echo password_hash('your_password', PASSWORD_DEFAULT);`

5. **Set credentials in env**
   - Edit **`control-panel/config/env.php`** on the server (or set environment variables).
   - Set:
     - `DB_HOST` – usually `localhost`
     - `DB_USER` – your MySQL username (e.g. `outratib_out`)
     - `DB_PASS` – your MySQL password
     - `CONTROL_PANEL_DB_NAME` or `DB_NAME` – the **exact** name of the control panel database you created (e.g. `outratib_control_panel_db` or `cpaneluser_controlpanel`).

### Option B – Use the same database as Ratib Pro

If the **main Ratib Pro database** already has the control panel tables (`control_admins`, `control_countries`, `control_agencies`, etc.):

- In **`control-panel/config/env.php`** on the server, set **`CONTROL_PANEL_DB_NAME`** (or **`CONTROL_DB_NAME`**) to the **same database name** as Ratib Pro (e.g. `outratib_out`).
- Keep **DB_HOST**, **DB_USER**, **DB_PASS** the same as the main app.
- Then the control panel will use that one database and the existing control_* tables.

---

## 3. After setup

- Open **https://out.ratib.sa/control.php** – it should redirect to the control panel.
- Or open **https://out.ratib.sa/control-panel/**.
- Log in with a user from the `control_admins` table (e.g. the admin you created in step 4 above).
