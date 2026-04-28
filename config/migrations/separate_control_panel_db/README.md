# Separate Control Panel Database

Moves all `control_*` tables from Ratib Pro DB (`outratib_out`) to a dedicated `control_panel_db`.

## Steps

### 1. Create database and tables (phpMyAdmin or MySQL CLI)

```bash
mysql -u outratib_out -p < 01_create_database.sql
mysql -u outratib_out -p < 02_create_tables.sql
```

Or run both in phpMyAdmin (create DB first, then run 02_create_tables.sql in `control_panel_db`).

### 2. Migrate data

```bash
php 03_migrate_data.php
```

Or via browser (must be logged in to Control Panel):
`https://out.ratib.sa/config/migrations/separate_control_panel_db/03_migrate_data.php`

### 3. Update configuration

Add to `config/env/control_ratib_sa.php` (or set env vars):

```php
define('CONTROL_PANEL_DB_NAME', 'control_panel_db');
```

The app will use `control_panel_db` for `control_conn` when this constant is defined.

### 4. (Optional) Drop control tables from outratib_out

After verifying Control Panel works with the new DB:

```sql
-- Run in outratib_out - only after confirming migration succeeded
DROP TABLE IF EXISTS control_bank_reconciliations, control_entry_approvals, control_electronic_invoices,
  control_disbursement_vouchers, control_receipts, control_expenses, control_journal_entry_lines,
  control_journal_entries, control_support_payments, control_bank_guarantees, control_cost_centers,
  control_chart_accounts, control_accounting_transactions, control_support_chat_messages,
  control_support_chats, control_registration_requests, control_admin_permissions,
  control_admins, control_agencies, control_countries;
```

## Environment variables

| Variable | Default | Description |
|----------|---------|-------------|
| `CONTROL_PANEL_DB_NAME` | `control_panel_db` | Control Panel database name |
| `RATIB_DB_NAME` | `outratib_out` | Source Ratib Pro database |
| `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_PORT` | (from env) | MySQL credentials |
