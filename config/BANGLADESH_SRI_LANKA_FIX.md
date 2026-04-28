# Bangladesh & Sri Lanka – Issues and Fixes

## Bangladesh

### Issues found
1. **Typo in database name**: Some files used `outratib_bangladish` (wrong) instead of `outratib_bangladesh` (correct).
2. **`control_agencies`**: May point to the wrong database name.
3. **Access denied**: The `outratib_out` user may not have access to `outratib_bangladesh`.

### Fixes applied in code
- `drop_and_prepare_bangladesh.php` now uses `outratib_bangladesh`.
- APIs try fallbacks: `outratib_bangladish` → `outratib_bangladesh` → `outratib_out` (main DB).

### What you should do
1. **Run in `outratib_out` (phpMyAdmin):**
   ```sql
   UPDATE control_agencies SET db_name = 'outratib_bangladesh' WHERE db_name = 'outratib_bangladish';
   ```

2. **In cPanel → MySQL Databases:**
   - Create `outratib_bangladesh` if it does not exist.
   - Add user `outratib_out` to `outratib_bangladesh` with ALL PRIVILEGES.

3. **If the database was created as `outratib_bangladish`:** Either rename it to `outratib_bangladesh` in cPanel, or keep `control_agencies` pointing to `outratib_bangladish` (the code will still try it as a fallback).

---

## Sri Lanka

### Issues found
1. **Duplicate slugs**: `control_countries` can have both `sri_lanka` and `sri-lanka`, causing Sri Lanka to appear twice.
2. **Access denied**: Same as Bangladesh – `outratib_out` may not have access to `outratib_sri_lanka`.

### Fixes applied in code
- API deduplicates countries by slug (Sri Lanka appears only once).
- APIs use main DB (`outratib_out`) as fallback when `outratib_sri_lanka` connection fails.

### What you should do
1. **Remove duplicate Sri Lanka (run in `outratib_out`):**
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
   - Create `outratib_sri_lanka` if it does not exist.
   - Add user `outratib_out` to `outratib_sri_lanka` with ALL PRIVILEGES.

---

## Quick checklist

| Step | Bangladesh | Sri Lanka |
|------|------------|-----------|
| Database exists? | `outratib_bangladesh` | `outratib_sri_lanka` |
| User has access? | Add `outratib_out` in cPanel | Same |
| `control_agencies.db_name` | `outratib_bangladesh` | `outratib_sri_lanka` |
| Fix typo in DB | Run `fix_bangladesh_db_name.sql` | N/A |
| Remove duplicate | N/A | Run SQL above |
