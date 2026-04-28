# Country Data Isolation Guide

**Goal:** Each country uses its own data. Bangladesh sees only Bangladesh data. Main (Saudi) sees only Main data. No conflicts.

---

## Two Ways to Achieve This

### Option A: Separate Database Per Country (Recommended – simplest)

Each country has its own database. Different DB = different data. No code changes needed.

| Country   | Database     | URL                    |
|-----------|--------------|-------------------------|
| Bangladesh| outratib_bd  | bangladesh.out.ratib.sa |
| Main      | outratib_out | out.ratib.sa            |

**Steps:**

1. **Create a new database in cPanel** for Bangladesh (e.g. `outratib_bd`).

2. **Copy structure and data** from `outratib_out`:
   - In phpMyAdmin: Export `outratib_out` → Import into `outratib_bd`
   - Or: Export structure only, then copy only Bangladesh rows (where `country_id = 1`)

3. **Update `control_agencies`** in phpMyAdmin:
   ```sql
   UPDATE control_agencies 
   SET db_name = 'outratib_bd', db_user = 'your_bd_user', db_pass = 'your_bd_pass' 
   WHERE country_id = 1 AND slug = 'bangladesh';
   ```

4. **Create DB user** in cPanel for `outratib_bd` and use those credentials in the UPDATE above.

5. **Result:** When users visit `bangladesh.out.ratib.sa`, they use `outratib_bd`. When they visit `out.ratib.sa`, they use `outratib_out`. Complete isolation.

---

### Option B: Shared Database with country_id Filtering

One database, all tables have `country_id`. Every query filters by `$_SESSION['country_id']`.

**Requirements:**
- `country_id` on all business tables (agents, workers, cases, subagents, etc.)
- All APIs and pages filter by `country_id`
- CountryFilter/BaseModel used everywhere

**Current state:** Login already sets `$_SESSION['country_id']`. Some tables may have `country_id`. Many APIs do NOT filter by country yet.

**Effort:** High – requires updating many API files and queries.

---

## Recommendation

Use **Option A (separate database per country)**. It gives:

- Full data isolation
- No risk of cross-country data leaks
- No code changes
- Simple to maintain

---

## Quick Setup for Option A

### 1. Create Bangladesh database

In cPanel → MySQL Databases:
- Create database: `outratib_bd`
- Create user and assign to `outratib_bd` with ALL PRIVILEGES

### 2. Copy data

In phpMyAdmin:
- Select `outratib_out` → Export (structure + data)
- Select `outratib_bd` → Import

Or, to copy only Bangladesh data:
```sql
-- Run in outratib_out, export result
-- Then run in outratib_bd
-- (Tables need country_id - agents, workers, etc.)
```

### 3. Update control_agencies

```sql
UPDATE control_agencies 
SET db_name = 'outratib_bd', 
    db_user = 'outratib_bd', 
    db_pass = 'your_password_here' 
WHERE country_id = 1;
```

### 4. Test

- Visit `bangladesh.out.ratib.sa` → should use Bangladesh DB
- Visit `out.ratib.sa` → should use Main DB
- Data is isolated per country
