-- RATEB ERP accounting COA tree: group headers + parent links (idempotent via app on index)
SET NAMES utf8mb4;

-- No schema change; parent_id already exists on rateb_chart_of_accounts.
-- Company COA hierarchy is seeded/linked by AccountingService::ensureDefaultAccounts().
