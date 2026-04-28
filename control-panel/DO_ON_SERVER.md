# Do this on the server (one-time)

## 1. Upload these files

Upload from your PC to the server (same paths):

- `control-panel/config/env.php` (SITE_URL = https://out.ratib.sa, rest ready)
- `control-panel/api/control/get-users-per-country.php` (countries show even when no agencies yet)

## 2. MySQL: give the app access to the control panel database

In **cPanel → MySQL® Databases**:

- Under **Add User To Database**, select user **outratib_out** and database **outratib_control_panel_db**, then **Add**.
- On the next screen, tick **ALL PRIVILEGES** and click **Make Changes**.

If that user still gets "Access denied":

- Create a **new MySQL user** (e.g. username `cpanel_cp`, strong password).
- Add that user to **outratib_control_panel_db** with **ALL PRIVILEGES**.
- Edit **control-panel/config/env.php** on the server and set:
  - `DB_USER` = that new user’s full name (e.g. `outratib_cpanel_cp`)
  - `DB_PASS` = that user’s password

## 3. Optional: remove helper files from the server

For security, delete these from the server if you no longer need them:

- `control-panel/check_db.php`
- `control-panel/create_admin.php`
- `control-panel/INSERT_ADMIN_IN_PHPMYADMIN.sql` (optional; keep locally if you like)
- `control-panel/INSERT_COUNTRIES_IN_PHPMYADMIN.sql` (optional; keep locally if you like)

## 4. Test

- Open **https://out.ratib.sa/control-panel/** and log in.
- Open **Manage Countries** — you should see the 12 countries (if you ran the INSERT in phpMyAdmin).
- Open **Country Users** — choose a country or see “No agencies configured” and add agencies in **Manage Agencies** if needed.

Done. The code side is ready; steps 1 and 2 on the server are required for the panel to work.
