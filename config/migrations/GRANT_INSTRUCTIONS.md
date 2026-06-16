# Grant Database Access — cPanel Instructions

Fix **"Access denied for user 'admin'@'localhost' to database 'admin_xxx'"** on the Country Users page.

---

## Step-by-Step (cPanel)

1. Log in to **cPanel**
2. Open **MySQL® Databases**
3. Scroll to **Add User To Database**
4. **User:** Select `admin` (or `admin_out` if your app uses that)
5. **Database:** Select each country database below, one at a time
6. Click **Add**
7. On the privileges screen, check **ALL PRIVILEGES** → **Make Changes**
8. Repeat steps 4–7 for every database in the list

---

## Databases to Add

Add your DB user to **all** of these:

| # | Database Name |
|---|---------------|
| 1 | admin_bangladesh |
| 2 | admin_ethiopia |
| 3 | admin_indonesia |
| 4 | admin_kenya |
| 5 | admin_nepal |
| 6 | admin_nigeria |
| 7 | admin_philippines |
| 8 | admin_rwanda |
| 9 | admin_sri_lanka |
| 10 | admin_thailand |
| 11 | admin_uganda |

---

## Which User to Use?

- If the error shows **`admin`** → add `admin` to each database
- If the error shows **`admin_out`** → add `admin_out` to each database
- If unsure → add **both** users to each database

---

## Verify

After adding the user to all databases:

1. Open: **https://rateb.sa/config/check_country_db_access.php?control=1**
2. All 11 databases should show ✓ OK
3. Reload the Country Users page — the "Access denied" error should be gone

---

## Why phpMyAdmin Fails

Running `GRANT` in phpMyAdmin while logged in as `admin` fails because that user does not have `GRANT` privileges. Use the cPanel **MySQL Databases** interface instead; it uses the correct permissions.
