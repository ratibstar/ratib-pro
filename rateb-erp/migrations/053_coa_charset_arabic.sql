-- RATEB ERP — utf8mb4 for chart of accounts (fixes ? in Arabic account names)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

ALTER TABLE rateb_chart_of_accounts CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE rateb_journal_entries CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE rateb_journal_lines CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
