# Full Review: Entity Accounts (Agent, SubAgent, Worker, HR)

**Date:** 2026-02-02  
**Scope:** Titles for Agent / SubAgent / Worker / HR, individual account numbers in the accounting system, and removal of Entity Accounts from the browser.

---

## 1. What Was Implemented

### 1.1 Titles in the receipt voucher dropdown
- **Cash / Bank Account** and **Account Collected From** show four section headers:
  - **─── Agents ───**
  - **─── SubAgents ───**
  - **─── Workers ───**
  - **─── HR ───**
- Under each: either **individual names with account numbers** (e.g. `4301 Ahmed (Income)`) or **— No accounts assigned yet —** if none exist.

### 1.2 Auto-connect existing data
- When the receipt voucher (or any call) loads accounts with **`ensure_entity_accounts=1`**, the API:
  - Reads all rows from **agents**, **subagents**, **workers**, **employees** (HR).
  - For each person **without** a row in `financial_accounts` with their `entity_type` and `entity_id`, it **creates** one:
    - **Account code:** 43xx (agents), 44xx (subagents), 45xx (workers), 46xx (HR).
    - **Account name:** person’s name from the table.
  - Uses DB ENUM values: **Income** / **Expense**, **Credit** / **Debit** (GAAP migration).

### 1.3 Entity Accounts removed from the browser
- **Entity Accounts** nav item and tab have been removed from `pages/accounting.php`.
- All related JS (loadEntityAccounts, assignEntityAccount, entity-accounts tab handling) has been removed from `js/accounting/professional.js`.

### 1.4 Database
- **GAAP migration** (`api/accounting/transactions/run-gaap-migration-safe.php`) adds to `financial_accounts`:
  - `entity_type` (VARCHAR 50, NULL)
  - `entity_id` (INT, NULL)
  - Index `idx_entity_type_id`
- Existing code uses these columns; no separate “Entity Accounts” table.

---

## 2. Why You Might Still See “No accounts assigned yet”

1. **GAAP migration not run**  
   If `financial_accounts` does not have `entity_type` and `entity_id`, the ensure logic does not run.  
   **Fix:** Run the GAAP migration once (the one that updates `financial_accounts`).  
   Example URL (adjust to your app):  
   `https://out.ratib.sa/api/accounting/transactions/run-gaap-migration-safe.php`  
   (Must be logged in.)

2. **ENUM mismatch (fixed in code)**  
   The ensure block was inserting `REVENUE` / `EXPENSE` and `CREDIT` / `DEBIT`, while the GAAP migration defines `Income`, `Expense`, `Debit`, `Credit`. That could cause inserts to fail.  
   **Fix (already applied):** Ensure block now uses `Income`, `Expense`, `Credit`, `Debit`.

3. **Empty entity tables**  
   If **agents**, **subagents**, **workers**, or **employees** have no rows (or only test rows filtered out), no accounts are created for that type.  
   **Check:** Confirm there is real data in those tables.

4. **Different table/column names**  
   Ensure logic expects:
   - agents.agent_name (or full_name / name)
   - subagents.subagent_name (or full_name / name)
   - workers.worker_name (or full_name / name)
   - employees.name (or employee_name / full_name)  
   If your schema differs, the SELECT may return no rows.

---

## 3. Current File Touchpoints

| File | Role |
|------|------|
| `api/accounting/accounts.php` | GET: `ensure_entity_accounts=1` creates missing entity accounts; returns accounts with `entity_type` / `entity_id`. POST: can create account with `entity_type` + `entity_id`. |
| `api/accounting/entities.php` | `?format=sections` returns Agent/SubAgent/Worker/HR sections with account info (used only if you re-add an Entity Accounts view). |
| `api/accounting/transactions/run-gaap-migration-safe.php` | Adds `entity_type`, `entity_id`, and index to `financial_accounts`. |
| `js/accounting/professional.js` | Receipt voucher calls `accounts.php?is_active=1&ensure_entity_accounts=1`; dropdown groups by entity type and shows the four titles. |
| `pages/accounting.php` | No Entity Accounts nav or tab. |

---

## 4. What You Should See After Fix

1. Run the GAAP migration once (if not already done).
2. Open **Add Receipt Voucher** or **Edit Receipt Voucher** and open the **Cash / Bank Account** or **Account Collected From** dropdown.
3. You should see:
   - **Agents** → list of agent names with codes like 4301, 4302, …
   - **SubAgents** → 4401, 4402, …
   - **Workers** → 4501, 4502, …
   - **HR** → 4601, 4602, …
   - **General Ledger Accounts** → all other GL accounts.

If you still see “No accounts assigned yet” under a title, check that:
- The GAAP migration has been run.
- The corresponding table (agents / subagents / workers / employees) has rows and the name column used in the ensure logic exists and has data.

---

## 5. “Last updated” CSS

- The extra block of CSS that was added for the Entity Accounts tab has been removed from `css/accounting/professional.css`.  
- No “last updated” or Entity-Accounts-specific CSS remains in that file.
