# Bangladesh & Sri Lanka – Issues and Fixes

## Bangladesh

### Issues found
1. **Typo in database name**: Some files used `admin_bangladish` (wrong) instead of `admin_bangladesh` (correct).
2. **`control_agencies`**: May point to the wrong database name.
3. **Access denied**: The `admin_out` user may not have access to `admin_bangladesh`.

### Fixes applied in code
- `drop_and_prepare_bangladesh.php` now uses `admin_bangladesh`.
- APIs try fallbacks: `admin_bangladish` → `admin_bangladesh` → `admin_out` (main DB).

### What you should do
1. **Run in `admin_out` (phpMyAdmin):**
   ```sql
   UPDATE control_agencies SET db_name = 'admin_bangladesh' WHERE db_name = 'admin_bangladish';
   ```

2. **In cPanel → MySQL Databases:**
   - Create `admin_bangladesh` if it does not exist.
   - Add user `admin_out` to `admin_bangladesh` with ALL PRIVILEGES.

3. **If the database was created as `admin_bangladish`:** Either rename it to `admin_bangladesh` in cPanel, or keep `control_agencies` pointing to `admin_bangladish` (the code will still try it as a fallback).

---

## Sri Lanka

### Issues found
1. **Duplicate slugs**: `control_countries` can have both `sri_lanka` and `sri-lanka`, causing Sri Lanka to appear twice.
2. **Access denied**: Same as Bangladesh – `admin_out` may not have access to `admin_sri_lanka`.

### Fixes applied in code
- API deduplicates countries by slug (Sri Lanka appears only once).
- APIs use main DB (`admin_out`) as fallback when `admin_sri_lanka` connection fails.

### What you should do
1. **Remove duplicate Sri Lanka (run in `admin_out`):**
   ```sql
   -- Step 1: Get the ID of sri-lanka (the duplicate)
   -- SELECT id FROM control_countries WHERE slug = 'sri-lanka';
   -- Step 2: Update agencies pointing to that ID to use sri_lanka's ID instead
   -- UPDATE control_agencies SET country_id = (SELECT id FROM control_countries WHERE slug = 'sri_lanka' LIMIT 1) WHERE country_id = <sri-lanka-id>;
   -- Step 3: Delete the duplicate
   DELETE FROM control_countries WHERE slug = 'sri-lanka';
   ```
   (If you get a foreign key error, update control_agencies first in phpMyAdmin.)

2. **In cPanel → MySQL Databases:**
   - Create `admin_sri_lanka` if it does not exist.
   - Add user `admin_out` to `admin_sri_lanka` with ALL PRIVILEGES.

---

## Quick checklist

| Step | Bangladesh | Sri Lanka |
|------|------------|-----------|
| Database exists? | `admin_bangladesh` | `admin_sri_lanka` |
| User has access? | Add `admin_out` in cPanel | Same |
| `control_agencies.db_name` | `admin_bangladesh` | `admin_sri_lanka` |
| Fix typo in DB | Run `fix_bangladesh_db_name.sql` | N/A |
| Remove duplicate | N/A | Run SQL above |
