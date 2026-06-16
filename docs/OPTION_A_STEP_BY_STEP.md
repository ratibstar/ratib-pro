# Option A: Separate Database Per Country — Step by Step

Do **one country at a time**. This guide uses **Bangladesh** as the first example.

---

## Step 1: Create the new database in cPanel

1. Log in to **cPanel**.
2. Open **MySQL® Databases**.
3. Under **Create New Database**:
   - Name: `admin_bd` (or `youruser_admin_bd` if cPanel adds a prefix)
   - Click **Create Database**.
4. Under **Add New User** (if needed):
   - Username: `admin_bd` (or `youruser_admin_bd`)
   - Password: create a strong password and save it
   - Click **Create User**.
5. Under **Add User To Database**:
   - Select the new user and database
   - Click **Add**
   - Check **ALL PRIVILEGES**
   - Click **Make Changes**

---

## Step 2: Export the current database

1. Open **phpMyAdmin**.
2. Select the database `admin_out` (or your current main DB).
3. Click **Export**.
4. Method: **Quick**.
5. Format: **SQL**.
6. Click **Go** and save the `.sql` file.

---

## Step 3: Import into the new database

1. In phpMyAdmin, select the new database `admin_bd`.
2. Click **Import**.
3. Choose the `.sql` file from Step 2.
4. Click **Go**.
5. Wait until you see “Import has been successfully finished”.

---

## Step 4: Update control_agencies for Bangladesh

1. In phpMyAdmin, select the database that contains `control_agencies` (usually `admin_out`).
2. Click **SQL**.
3. Run this (replace `YOUR_BD_PASSWORD` with the real password):

```sql
UPDATE control_agencies 
SET db_name = 'admin_bd', 
    db_user = 'admin_bd', 
    db_pass = 'YOUR_BD_PASSWORD' 
WHERE country_id = 1 AND slug = 'bangladesh';
```

4. If cPanel uses a prefix (e.g. `admin_`), use the full names:

```sql
UPDATE control_agencies 
SET db_name = 'admin_admin_bd', 
    db_user = 'admin_admin_bd', 
    db_pass = 'YOUR_BD_PASSWORD' 
WHERE country_id = 1 AND slug = 'bangladesh';
```

5. Click **Go**.

---

## Step 5: Test Bangladesh

1. Open: `https://bangladesh.rateb.sa/pages/login.php`
2. Log in with a Bangladesh user.
3. Check that:
   - Login works
   - Dashboard loads
   - Agents, workers, cases, etc. show only Bangladesh data

---

## Step 6: Repeat for the next country (e.g. Main/Saudi)

When you’re ready for another country:

1. Create a new database (e.g. `admin_main`).
2. Create user and grant privileges.
3. Export `admin_out` and import into the new DB.
4. Update `control_agencies`:

```sql
UPDATE control_agencies 
SET db_name = 'admin_main', 
    db_user = 'admin_main', 
    db_pass = 'YOUR_MAIN_PASSWORD' 
WHERE country_id = 2 AND slug = 'main';
```

5. Test at `https://rateb.sa/pages/login.php`.

---

## Important notes

| Item | Note |
|------|------|
| **DB names** | Use the exact names shown in cPanel (with prefix if any). |
| **Passwords** | Store them safely; you’ll need them in `control_agencies`. |
| **First country** | Start with Bangladesh; keep Main on `admin_out` until you move it. |
| **Backup** | Export `admin_out` before any changes. |

---

## Checklist for one country

- [ ] New database created
- [ ] New user created and granted privileges
- [ ] Data exported from main DB
- [ ] Data imported into new DB
- [ ] `control_agencies` updated with new `db_name`, `db_user`, `db_pass`
- [ ] Login tested
- [ ] Data verified (only that country’s data)
