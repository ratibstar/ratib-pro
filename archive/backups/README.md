# Backups Directory

This directory is for storing database backups.

## Current Status

All old backup SQL files have been removed. The main database file is now:
- `database/init.sql` - Complete database initialization file

## Creating New Backups

When you need to create a backup:

1. **Via phpMyAdmin:**
   - Select your database
   - Click "Export" tab
   - Choose "Quick" or "Custom" method
   - Click "Go" to download

2. **Via Command Line:**
   ```bash
   mysqldump -u username -p ratibprogram > backups/ratibprogram_backup_YYYY-MM-DD.sql
   ```

3. **Naming Convention:**
   - Use format: `ratibprogram_backup_YYYY-MM-DD_HH-MM-SS.sql`
   - Example: `ratibprogram_backup_2025-12-22_14-30-00.sql`

## Backup Retention

- Keep only recent backups (last 2-3 backups)
- Remove backups older than 30 days
- Store important backups in a separate location

## Notes

- The main database file (`database/init.sql`) should always be kept up to date
- Regular backups are recommended before major changes
- Test backup restoration periodically

