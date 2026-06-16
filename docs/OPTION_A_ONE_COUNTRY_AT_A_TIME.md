# Option A — One Country at a Time

Start with **Bangladesh** only. When it works, repeat for the next country.

---

## Bangladesh — Step by Step

### Step 1: Create the database (if not done)

1. Log in to **cPanel**
2. Open **MySQL® Databases**
3. Create database: `admin_bangladish`
4. Create user (or use existing `admin_out`)
5. Add user to database with **ALL PRIVILEGES**

---

### Step 2: Export from main database

1. Open **phpMyAdmin**
2. Click **admin_out** in the left sidebar
3. Click **Export** tab
4. Method: **Quick**
5. Format: **SQL**
6. Click **Go** → save the `.sql` file

---

### Step 3: Import into Bangladesh database

1. In phpMyAdmin, click **admin_bangladish** in the left sidebar
2. Click **Import** tab
3. Choose the `.sql` file from Step 2
4. Click **Go**
5. Wait for "Import has been successfully finished"

---

### Step 4: Keep only Bangladesh in control_agencies

1. Still in phpMyAdmin, make sure **admin_bangladish** is selected (left sidebar)
2. Click **SQL** tab
3. Paste this and click **Go**:

```sql
DELETE FROM control_agencies WHERE country_id != 1;
```

---

### Step 5: Update main database to use Bangladesh DB

1. Click **admin_out** in the left sidebar
2. Click **SQL** tab
3. Paste this and click **Go** (use your real password if different):

```sql
UPDATE control_agencies SET db_name = 'admin_bangladish', db_user = 'admin_out', db_pass = '9s%BpMr1]dfb' WHERE country_id = 1;
```

---

### Step 6: Test

1. Open: `https://bangladesh.rateb.sa/pages/login.php`
2. Log in with a Bangladesh user
3. Check that the dashboard and data load correctly

---

## Done with Bangladesh

When Bangladesh works, repeat the same steps for the next country (e.g. Ethiopia):

- Use database: **admin_ethiopia**
- Use: `DELETE FROM control_agencies WHERE country_id != 2;`
- Use: `UPDATE control_agencies SET db_name = 'admin_ethiopia', ... WHERE country_id = 2;`

---

## Quick reference — country_id for each country

| Country   | country_id | Database          |
|-----------|------------|-------------------|
| Bangladesh| 1          | admin_bangladish |
| Ethiopia  | 2          | admin_ethiopia |
| Indonesia | 3          | admin_indonesia |
| Kenya     | 4          | admin_kenya    |
| Nepal     | 5          | admin_nepal    |
| Nigeria   | 6          | admin_nigeria  |
| Philippines | 7        | admin_philippines |
| Rwanda    | 8          | admin_rwanda   |
| Sri Lanka | 9          | admin_sri_lanka |
| Thailand  | 10         | admin_thailand |
| Uganda    | 11         | admin_uganda   |
