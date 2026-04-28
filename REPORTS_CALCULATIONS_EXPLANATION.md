# Reports Dashboard Calculations - Data Source Explanation

---

## The three top cards: Total Reports, Today, This Month

### What you see

| Card          | Value shown | Description text              |
|---------------|-------------|-------------------------------|
| **Total Reports** | 13        | All activity logs combined    |
| **Today**         | 13        | Reports generated today      |
| **This Month**    | 13        | Reports this month           |

### Where the **numbers** (e.g. 13) come from

They are **sums of row counts** from these 4 database tables:

```
Total Reports = count(activity_logs) + count(system_logs) + count(case_activities) + count(global_history)
Today         = same 4 tables, but only rows where DATE(created_at) = today
This Month    = same 4 tables, but only rows where MONTH(created_at) = current month
```

- **Data source**: Tables `activity_logs`, `system_logs`, `case_activities`, `global_history` (each with a `created_at` column for today/month filters).
- **Fetched by**: API `api/reports/reports.php?action=get_log_stats` (function `getLogStats()`), or if that fails, direct DB queries in `pages/reports.php` (fallback block).
- **Variables used in the page**: `$totalReports`, `$todayReports`, `$monthReports` in `pages/reports.php`.

In your case, **all 13** come from **`global_history`** (the other three tables have 0 rows), so:
- Total = 0+0+0+13 = **13**
- Today = 0+0+0+13 = **13** (all 13 rows have `created_at` today)
- This Month = 0+0+0+13 = **13** (all 13 rows are in the current month)

### Where the **labels and descriptions** come from

- **Card titles**: "Total Reports", "Today", "This Month" — hardcoded in the HTML in `pages/reports.php`.
- **Descriptions**: "All activity logs combined", "Reports generated today", "Reports this month" — same file, in the `reports-status-card-desc` divs.

**File**: `pages/reports.php`  
**Approx. lines**: 227–256 (status cards section).

---

## Where the (breakdown) Numbers Come From

The Reports Dashboard calculations come from **counting records in 4 database tables**:

### 1. **activity_logs** Table
- **Source**: Created when users perform actions
- **Logged by**: `logActivity()` function in `includes/permissions.php`
- **Queries**:
  ```sql
  SELECT COUNT(*) FROM activity_logs                                    -- Total
  SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE() -- Today
  SELECT COUNT(*) FROM activity_logs WHERE MONTH(created_at) = MONTH(CURDATE()) -- This Month
  ```

### 2. **system_logs** Table
- **Source**: System events and operations
- **Queries**:
  ```sql
  SELECT COUNT(*) FROM system_logs                                    -- Total
  SELECT COUNT(*) FROM system_logs WHERE DATE(created_at) = CURDATE() -- Today
  SELECT COUNT(*) FROM system_logs WHERE MONTH(created_at) = MONTH(CURDATE()) -- This Month
  ```

### 3. **case_activities** Table
- **Source**: Case-related activities
- **Queries**:
  ```sql
  SELECT COUNT(*) FROM case_activities                                    -- Total
  SELECT COUNT(*) FROM case_activities WHERE DATE(created_at) = CURDATE() -- Today
  SELECT COUNT(*) FROM case_activities WHERE MONTH(created_at) = MONTH(CURDATE()) -- This Month
  ```

### 4. **global_history** Table
- **Source**: All CRUD operations across the entire system
- **Logged by**: `logGlobalHistory()` function in `api/core/global-history-helper.php`
- **Queries**:
  ```sql
  SELECT COUNT(*) FROM global_history                                    -- Total
  SELECT COUNT(*) FROM global_history WHERE DATE(created_at) = CURDATE() -- Today
  SELECT COUNT(*) FROM global_history WHERE MONTH(created_at) = MONTH(CURDATE()) -- This Month
  ```

## How Totals Are Calculated

The dashboard cards show:

### **Total Reports** Card
```
Total Reports = activity_logs.total + system_logs.total + case_activities.total + global_history.total
```

### **Today** Card
```
Today = activity_logs.today + system_logs.today + case_activities.today + global_history.today
```

### **This Month** Card
```
This Month = activity_logs.month + system_logs.month + case_activities.month + global_history.month
```

## Code Flow

1. **Page Load** (`pages/reports.php`):
   - First tries to get data from API: `api/reports/reports.php?action=get_log_stats`
   - If API fails, falls back to direct database queries

2. **API Endpoint** (`api/reports/reports.php`):
   - Function `getLogStats()` queries all 4 tables
   - Returns JSON: `{success: true, data: {activity_logs: {...}, system_logs: {...}, ...}}`

3. **Display**:
   - Values are displayed in the status cards on the dashboard
   - Each card shows the count from its respective table

## Why Some Cards Show Zero

If a card shows **0**, it means:
- The table exists but has no records, OR
- The table doesn't exist, OR
- The `created_at` column doesn't exist in that table

## Files Involved

- **Display**: `pages/reports.php` (lines 17-188)
- **API**: `api/reports/reports.php` (function `getLogStats()` starting at line 2191)
- **Logging Functions**:
  - `includes/permissions.php` - `logActivity()` function
  - `api/core/global-history-helper.php` - `logGlobalHistory()` function

## Current Status (Based on Your Dashboard)

- **Total Reports**: 13 (all from `global_history` table)
- **Today**: 13 (all from `global_history` table)
- **This Month**: 13 (all from `global_history` table)
- **Activity Logs**: 0 (table empty or doesn't exist)
- **System Logs**: 0 (table empty or doesn't exist)
- **Case Activities**: 0 (table empty or doesn't exist)
- **Global History**: 13 (has data!)

This means your system is currently only logging to the `global_history` table, and the other 3 tables are empty or not being used.

---

## Revenue, Commission, and Payroll Amounts (Agents / SubAgents / Workers)

### Where they come from

- **Agents → Revenue**: `financial_transactions` table, where `entity_type = 'agent'` and `transaction_type` is income. Sum of the amount column.
- **SubAgents → Commission**: `financial_transactions` table, where `entity_type = 'subagent'` and `transaction_type` is income. Sum of the amount column.
- **Workers → Payroll**: `financial_transactions` table, where `entity_type = 'worker'` and `transaction_type` is expense; if no data, then sum of `salary`/`basic_salary` from `workers` table.

**Code**: `api/reports/reports.php` — `getAgentsStats()`, `getSubAgentsStats()`, `getWorkersStats()`.

### Change made (no accounting yet)

Previously, when there was **no** data in `financial_transactions`, the code used **hardcoded fallbacks**:

- Agents Revenue: `activeAgents × 2500` (e.g. $2,500 for 1 agent)
- SubAgents Commission: `active × 1500` (e.g. **$1,500** for 1 subagent)
- Workers Payroll: `active × 1000`

Those fallbacks have been **removed**. If you have not used the accounting module:

- **Revenue** (Agents) and **Commission** (SubAgents) will show **$0.00** until you have real income entries in `financial_transactions` (or equivalent).
- **Payroll** (Workers) will show **$0.00** until you have expense entries or salary data in `workers`.

So the **$1,500.00 Commission** you saw was from the old SubAgent fallback (1 active subagent × $1,500). It was not from accounting; it is now removed so the dashboard shows real data only.
