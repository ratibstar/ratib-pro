# Enterprise Multi-Tenant Deployment Checklist

## Pre-Deployment

- [ ] **Backup database** (full dump)
- [ ] **Backup codebase** (git commit or zip)
- [ ] Test on staging first

## Step 1: Database Migration

```bash
# 1. Backup
mysqldump -u user -p outratib_out > backup_$(date +%Y%m%d).sql

# 2. Run schema (run each block separately if errors)
mysql -u user -p outratib_out < config/migrations/enterprise_multi_tenant_001_schema.sql

# 3. If countries exists, add subscription columns
mysql -u user -p outratib_out < config/migrations/enterprise_multi_tenant_002_extend_countries.sql
```

- [ ] Verify `countries` table has 12 rows
- [ ] Verify `users` has `tenant_role` column
- [ ] Assign first super_admin: `UPDATE users SET tenant_role='super_admin', country_id=NULL WHERE user_id=1;`

## Step 2: DNS / Subdomains

Create A or CNAME records for:
- sa.out.ratib.sa
- ae.out.ratib.sa
- eg.out.ratib.sa
- bd.out.ratib.sa
- (etc. for all 12 countries)

- [ ] Verify each subdomain resolves to server IP

## Step 3: cPanel / Server

- [ ] Add subdomains in cPanel (or wildcard *.out.ratib.sa)
- [ ] Ensure Document Root points to same folder as main site
- [ ] SSL: Enable for each subdomain or use wildcard cert

## Step 4: Environment

Add to `.env` or set in cPanel / server:

```
MULTI_TENANT_SUBDOMAIN_ENABLED=0
TENANT_ALLOW_ROOT_DOMAIN=1
TENANT_BASE_DOMAIN=out.ratib.sa
```

- Start with `MULTI_TENANT_SUBDOMAIN_ENABLED=0` to test without breaking current system
- When ready: set to `1`
- When subdomains live: set `TENANT_ALLOW_ROOT_DOMAIN=0` to block root domain

## Step 5: Code Deploy

- [ ] Upload `/core` folder
- [ ] Upload updated `includes/config.php`
- [ ] Upload updated `pages/login.php`
- [ ] Upload `admin/` folder
- [ ] Set file permissions (644 for PHP)

## Step 6: Enable Multi-Tenant

In `includes/config.php`:
```php
define('MULTI_TENANT_SUBDOMAIN_ENABLED', true);
```

- [ ] Test sa.out.ratib.sa login
- [ ] Test super_admin at /admin/
- [ ] Test country_admin restricted to own tenant

## Step 7: Security Hardening

- [ ] Regenerate session ID on login (already in Auth)
- [ ] HTTPOnly cookies (already in load.php)
- [ ] Secure cookies for HTTPS
- [ ] Add CSRF tokens to forms (future)
- [ ] Block direct access to /core/*.php (deny in .htaccess if needed)

## Rollback

If issues occur:
1. Set `MULTI_TENANT_SUBDOMAIN_ENABLED` to `false`
2. Restore database from backup if needed
3. Revert code changes

## Future: Per-Tenant Database

When moving a country to its own DB:
1. Add `db_host`, `db_name`, etc. to `countries` table
2. Update `Database::getConnection($tenantId)` to switch connection
3. Migrate that tenant's data to new DB
