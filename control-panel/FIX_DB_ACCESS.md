# Fix "Access denied" for control panel database

If you still get **Access denied for user 'outratib_out'@'localhost' to database 'outratib_control_panel_db'** after adding the user in cPanel, use a **dedicate MySQL user** for the control panel.

## Step 1: Create a new MySQL user (cPanel)

1. cPanel → **MySQL® Databases**.
2. Under **Add New User**:
   - **Username:** e.g. `cpanel_cp` (cPanel may add a prefix like `outratib_`, so the full name becomes `outratib_cpanel_cp`).
   - **Password:** choose a strong password and note it.
3. Click **Create User**.

## Step 2: Add this user to the control panel database

1. In the same page, find **Add User To Database**.
2. **User:** select the user you just created (e.g. `outratib_cpanel_cp`).
3. **Database:** select **outratib_control_panel_db**.
4. Click **Add**.
5. On the privileges page, tick **ALL PRIVILEGES**, then **Make Changes**.

## Step 3: Set credentials in env on the server

1. On the server, edit **control-panel/config/env.php**.
2. Set the control panel DB user and password. Either change the defaults or set them via the helper at the top (the `$e()` function reads from environment variables first).

   Use the **full MySQL username** (with cPanel prefix if shown in phpMyAdmin or in the MySQL user list). For example:

   ```php
   define('DB_USER', $e('CONTROL_DB_USER', 'outratib_cpanel_cp'));   // full username as in cPanel
   define('DB_PASS', $e('CONTROL_DB_PASS', 'YourNewPasswordHere'));
   ```

   Keep:

   ```php
   define('CONTROL_PANEL_DB_NAME', $e('CONTROL_PANEL_DB_NAME', 'outratib_control_panel_db'));
   ```

3. Save the file.

## Step 4: Run create_admin.php again

Open: **https://out.ratib.sa/control-panel/create_admin.php**

Then log in with username **admin**, password **password**, and delete the helper files (create_admin.php, check_db.php, this FIX_DB_ACCESS.md) from the server.
