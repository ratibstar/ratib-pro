# Archive Folder
## Non-Critical Files for Production

This folder contains files that are not needed for production deployment.

## ⚠️ IMPORTANT: DO NOT UPLOAD THIS FOLDER TO PRODUCTION SERVER

---

## 📁 Contents

### Documentation Files (.md)
- All markdown documentation files (39+ files)
- Setup guides
- Deployment checklists
- System documentation
- Video scripts
- User guides

### Development Folders
- `docs/` - Documentation files
- `tests/` - Test files
- `setup/` - Setup scripts
- `errors/` - Error logs folder
- `exports/` - Export files folder
- `php/` - PHP utilities folder
- `Forms/` - Forms folder
- `dashboard/` - Dashboard folder
- `cron/` - Cron jobs folder
- `path-api/` - Duplicate path folder
- `path-pages/` - Duplicate path folder

### Database & Backups
- `database/` - Database initialization files and migrations
- `backups/` - Backup files and README

### Other Non-Critical Files
- `Utils/` - Utility files (can be moved if not used)

---

## ✅ Production Files (Keep These)

The following folders/files MUST be kept for production:

### Critical Folders:
- `api/` - API endpoints (REQUIRED)
- `pages/` - Page files (REQUIRED)
- `includes/` - Include files (REQUIRED)
- `css/` - Stylesheets (REQUIRED)
- `js/` - JavaScript files (REQUIRED)
- `uploads/` - Upload directory (REQUIRED)
- `logs/` - Log files directory (REQUIRED)
- `vendor/` - Composer dependencies (REQUIRED)
- `config/` - Configuration files (REQUIRED)
- `assets/` - Assets directory (REQUIRED)
- `hr/` - HR module files (REQUIRED)

### Critical Files:
- `index.php` - Entry point (REQUIRED)
- `.htaccess` - Apache configuration (REQUIRED)
- `api/.htaccess` - API security (REQUIRED)
- `composer.json` - Dependencies (REQUIRED)

---

## 🚀 Deployment Instructions

### When Uploading to Production Server:

1. **Upload ALL files EXCEPT the `archive/` folder**
2. **Keep these folders:**
   - api/
   - pages/
   - includes/
   - css/
   - js/
   - uploads/
   - logs/
   - vendor/
   - config/
   - assets/
   - hr/

3. **Keep these files:**
   - index.php
   - .htaccess
   - api/.htaccess
   - composer.json

4. **DO NOT upload:**
   - archive/ (this entire folder)
   - Any .md files (they're all in archive/)
   - move-to-archive.ps1 (this script)

---

## 📝 Notes

- These files are kept for reference but are not required for the production system to function
- Documentation files can be accessed locally if needed
- Database files (`database/init.sql`) should be imported separately via phpMyAdmin
- Backup files are kept for reference only

---

**Last Updated**: 2025-01-20
