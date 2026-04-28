# Multi-Tenant Architecture Guide

Single codebase, single database, 12 countries with complete data isolation. Designed for future scalability (separate DB per country).

---

## 1. Database Structure

### Countries Table
```sql
countries (id, name, code, domain, status, sort_order, created_at, updated_at)
```
- **code**: Subdomain (sa, ae, eg)
- **domain**: Full host (sa.ratib.sa)
- **status**: active | inactive

### Business Tables
All main tables include:
```sql
country_id INT UNSIGNED NOT NULL
KEY idx_country_id (country_id)
FOREIGN KEY (country_id) REFERENCES countries(id)
```

### Migration
Run `config/migrations/multi_tenant_001_countries.sql` in phpMyAdmin.
- Creates `countries` table
- Adds `country_id` to users, roles, activity_logs, system_config
- Existing rows get `country_id = 1` (adjust per your data)

---

## 2. Tenant Detection (Subdomain)

| Host              | Country Code |
|-------------------|--------------|
| sa.ratib.sa       | sa           |
| ae.ratib.sa       | ae           |
| bangladesh.out.ratib.sa | bd   |

**TenantLoader.php** extracts subdomain from `$_SERVER['HTTP_HOST']` and:
1. Matches `domain` column, or
2. Uses first subdomain as `code`
3. Defines `COUNTRY_ID`, `COUNTRY_CODE`, `COUNTRY_NAME`
4. Stops with 404 if country not found

---

## 3. Integration (Enable Multi-Tenant)

### Step 1: Add to `includes/config.php` (after DB connection)

```php
// After: $conn = $GLOBALS['conn'];

define('MULTI_TENANT_ENABLED', true);  // Set to true when ready
require_once __DIR__ . '/TenantLoader.php';
TenantLoader::init();
TenantLoader::validateSession();
```

### Step 2: Bootstrap Order
```
load.php (env) → config.php (DB + TenantLoader) → your pages
```

---

## 4. Data Isolation

### All Queries MUST Filter by country_id

```php
require_once __DIR__ . '/helpers/CountryFilter.php';

// SELECT
$where = CountryFilter::where('users', 'status = ?');
$sql = "SELECT * FROM users WHERE $where";

// INSERT - country_id added automatically
$params = CountryFilter::insertParams(
    ['username', 'email', 'password'],
    [$username, $email, $hash],
    ['s','s','s']
);
$sql = "INSERT INTO users (" . implode(',', $params['columns']) . ") VALUES (" . $params['placeholders'] . ")";
$stmt->bind_param($params['types'], ...$params['values']);

// UPDATE / DELETE - always include CountryFilter::where()
$where = CountryFilter::where('users', 'id = ?');
$sql = "UPDATE users SET name = ? WHERE $where";
```

---

## 5. Security

| Rule | Implementation |
|------|-----------------|
| No manual country switch | Session stores `country_id`; TenantLoader validates on each request |
| Validate every request | Call `TenantLoader::validateSession()` in config |
| All queries filtered | Use `CountryFilter::where()` or `insertParams()` |
| Control panel bypass | `IS_CONTROL_PANEL` → `COUNTRY_ID = 0` (no filter) |

---

## 6. Folder Structure (Recommended)

```
ratibprogram/
├── config/
│   ├── env/                    # Per-host config (existing)
│   └── migrations/             # SQL migrations
│       └── multi_tenant_001_countries.sql
├── includes/
│   ├── config.php              # Main config + TenantLoader
│   ├── TenantLoader.php        # Subdomain → country
│   └── helpers/
│       ├── CountryFilter.php   # Query filter helper
│       └── SecureQueryExample.php
├── api/                        # All APIs use CountryFilter
├── pages/
└── docs/
    └── MULTI_TENANT_ARCHITECTURE.md
```

---

## 7. Future Scalability (Separate DB per Country)

When moving one country to its own hosting:

1. **Database**: Create new DB, export that country's data
2. **Config**: Add env file `config/env/sa.ratib.sa.php` with that country's DB_*
3. **Code**: No changes. TenantLoader + CountryFilter work the same
4. **Connection**: `load.php` → env defines DB_* → `config.php` uses it

The abstraction (COUNTRY_ID, CountryFilter) stays the same. Only the connection config changes.

---

## 8. Best Practices

- **Backup** before running migration
- **Run ALTERs one by one** – skip tables that don't exist
- **Test with MULTI_TENANT_ENABLED = false** first – verify no breakage
- **Add country_id to new tables** from day one
- **Use prepared statements** – never concatenate user input
- **Control panel** (IS_CONTROL_PANEL) does NOT use TenantLoader – it manages all countries

---

## 9. Checklist

- [ ] Run `multi_tenant_001_countries.sql`
- [ ] Add `country_id` to all business tables
- [ ] Set `MULTI_TENANT_ENABLED = true` in config
- [ ] Include TenantLoader in config.php
- [ ] Update all SELECT/INSERT/UPDATE/DELETE to use CountryFilter
- [ ] Test each subdomain (sa, ae, bd, etc.)
- [ ] Verify session stores country_id
- [ ] Verify no cross-country data leakage
