# SQL Files Organization

## Main Database File

**`init.sql`** - Complete database initialization file
- Contains all tables, data, indexes, and constraints
- Includes DROP TABLE IF EXISTS statements for safe re-import
- This is the ONLY file needed for fresh database setup
- Source: `c:\Users\hp\Desktop\ratibprogram.sql`

## Files to Keep (Essential)

- `init.sql` - Main database initialization (complete)
- `notifications.sql` - Notification system setup (if separate)

## Files Removed (Test/Check/Backup Files)

The following test, check, and backup SQL files have been removed as they are not needed for production:

### Backup Files (Removed):
- `backups/ratibprogram_backup_2025-08-16_10-58-21.sql`
- `backups/ratibprogram_backup_2025-08-16_10-58-27.sql`
- `backups/ratibprogram_backup_2025-08-24_07-58-01.sql`
- `backups/ratibprogram_backup_2025-08-24_11-57-48.sql`
- `backups/ratibprogram_backup_2025-08-26_20-56-43.sql`
- `backups/ratibprogram_backup_2025-08-26_22-18-31.sql`

### Test Files:

### Test Files:
- `test-restricted-permissions.sql`
- `simple-table-check.sql`
- `quick-check-accounts.sql`
- `FINAL_CHECK_SIMPLE.sql`
- `FINAL_ACCOUNTING_CHECK.sql`

### Check/Verify Files:
- `check-missing-accounting-tables.sql`
- `check-user-permissions.sql`
- `verify-accounting-setup.sql`
- `verify-all-accounting-tables.sql`
- `verify-new-accounting-tables.sql`
- `final-accounting-database-check.sql`

### Migration Files (No longer needed):
- `accounting-migrate-existing-tables.sql`
- `create-new-accounting-tables.sql`
- `create-new-accounting-tables-safe.sql`
- `fix-new-accounting-tables.sql`
- `accounting-schema.sql` (merged into init.sql)
- `accounting-complete.sql` (merged into init.sql)
- `accounting-initial-data.sql` (merged into init.sql)
- `accounting-initial-data-safe.sql` (merged into init.sql)

### Setup Files (One-time use, no longer needed):
- `setup-user-permissions.sql`
- `add-user-permissions-column.sql`
- `add-password-plain-column.sql`
- `create-webauthn-table.sql` (if not in init.sql)
- `update_hr_documents.sql` (if not in init.sql)
- `list-all-users.sql`

## How to Use

### For Fresh Installation:
1. Create database: `CREATE DATABASE ratibprogram;`
2. Import `init.sql` via phpMyAdmin or command line
3. Update `includes/config.php` with database credentials

### For Remote Server:
1. Import `init.sql` via phpMyAdmin
2. Update database credentials in config files
3. Ensure all tables are created successfully

## Notes

- All triggers are commented out (use PHP helper functions instead)
- All tables include DROP TABLE IF EXISTS for safe re-import
- The complete database structure is in `init.sql`
- Test, check, and old backup files have been removed
- Only `init.sql` is needed for database initialization
- Create new backups as needed (see `backups/README.md`)

