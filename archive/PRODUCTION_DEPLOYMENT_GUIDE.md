# 🚀 Production Deployment Guide
## Ratib Program - https://out.ratib.sa/

---

## ✅ Files to Upload to Production Server

### Required Folders (Upload ALL):
- ✅ `api/` - API endpoints
- ✅ `pages/` - Page files
- ✅ `includes/` - Include files
- ✅ `css/` - Stylesheets
- ✅ `js/` - JavaScript files
- ✅ `uploads/` - Upload directory (ensure writable permissions)
- ✅ `logs/` - Log files directory (ensure writable permissions)
- ✅ `vendor/` - Composer dependencies
- ✅ `config/` - Configuration files
- ✅ `assets/` - Assets directory
- ✅ `hr/` - HR module files

### Required Files (Upload ALL):
- ✅ `index.php` - Entry point
- ✅ `.htaccess` - Apache configuration
- ✅ `api/.htaccess` - API security
- ✅ `composer.json` - Dependencies

---

## ❌ Files/Folders to EXCLUDE (DO NOT Upload):

- ❌ `archive/` - **ENTIRE FOLDER** (contains non-critical files)
- ❌ `move-to-archive.ps1` - PowerShell script (not needed)
- ❌ Any `.md` files in root (all moved to archive/)
- ❌ `PRODUCTION_DEPLOYMENT_GUIDE.md` - This file (for reference only)

---

## 📋 Pre-Deployment Checklist

### 1. Database Setup:
- [ ] Import `archive/database/init.sql` via phpMyAdmin
- [ ] Verify all tables are created
- [ ] Check database credentials in `includes/config.php`

### 2. File Permissions:
- [ ] Set `uploads/` to 755 or 775 (writable)
- [ ] Set `logs/` to 755 or 775 (writable)
- [ ] Set PHP files to 644 (readable)
- [ ] Set `.htaccess` files to 644 (readable)

### 3. Configuration:
- [ ] Verify `includes/config.php` has production credentials
- [ ] Verify `BASE_URL` is set to `''` (empty for root)
- [ ] Verify `SITE_URL` is set to `https://out.ratib.sa`
- [ ] Verify `PRODUCTION_MODE` is `true`

### 4. Security:
- [ ] Verify `.htaccess` files are uploaded
- [ ] Verify HTTPS redirect works
- [ ] Verify security headers are set
- [ ] Test login functionality

### 5. Testing:
- [ ] Test all modules
- [ ] Test file uploads
- [ ] Test API endpoints
- [ ] Monitor error logs

---

## 🔧 Quick Deployment Steps

1. **Run the archive script** (if not already done):
   ```powershell
   .\move-to-archive.ps1
   ```

2. **Upload files** (excluding archive folder):
   - Use FTP/SFTP client
   - Upload all folders EXCEPT `archive/`
   - Upload all files EXCEPT `.md` files and scripts

3. **Set permissions**:
   ```bash
   chmod 755 uploads/
   chmod 755 logs/
   chmod 644 *.php
   chmod 644 .htaccess
   ```

4. **Import database**:
   - Access phpMyAdmin
   - Import `archive/database/init.sql`

5. **Verify configuration**:
   - Check `includes/config.php`
   - Test login
   - Test all modules

---

## 📝 Post-Deployment

- Monitor `logs/php-errors.log` for errors
- Test all functionality
- Verify HTTPS redirect works
- Check file uploads work correctly

---

**Status**: ✅ Ready for Production Deployment
