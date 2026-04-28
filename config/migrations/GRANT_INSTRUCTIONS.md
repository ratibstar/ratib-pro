# Grant Database Access — cPanel Instructions

Fix **"Access denied for user 'outratib'@'localhost' to database 'outratib_xxx'"** on the Country Users page.

---

## Step-by-Step (cPanel)

1. Log in to **cPanel**
2. Open **MySQL® Databases**
3. Scroll to **Add User To Database**
4. **User:** Select `outratib` (or `outratib_out` if your app uses that)
5. **Database:** Select each country database below, one at a time
6. Click **Add**
7. On the privileges screen, check **ALL PRIVILEGES** → **Make Changes**
8. Repeat steps 4–7 for every database in the list

---

## Databases to Add

Add your DB user to **all** of these:

| # | Database Name |
|---|---------------|
| 1 | outratib_bangladesh |
| 2 | outratib_ethiopia |
| 3 | outratib_indonesia |
| 4 | outratib_kenya |
| 5 | outratib_nepal |
| 6 | outratib_nigeria |
| 7 | outratib_philippines |
| 8 | outratib_rwanda |
| 9 | outratib_sri_lanka |
| 10 | outratib_thailand |
| 11 | outratib_uganda |

---

## Which User to Use?

- If the error shows **`outratib`** → add `outratib` to each database
- If the error shows **`outratib_out`** → add `outratib_out` to each database
- If unsure → add **both** users to each database

---

## Verify

After adding the user to all databases:

1. Open: **https://out.ratib.sa/config/check_country_db_access.php?control=1**
2. All 11 databases should show ✓ OK
3. Reload the Country Users page — the "Access denied" error should be gone

---

## Why phpMyAdmin Fails

Running `GRANT` in phpMyAdmin while logged in as `outratib` fails because that user does not have `GRANT` privileges. Use the cPanel **MySQL Databases** interface instead; it uses the correct permissions.
