-- Backfill COA parent group accounts and parent_id links (per company + platform template)
SET NAMES utf8mb4;

-- Platform template (company_id IS NULL): insert missing group headers
INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
SELECT NULL, '1000', 'Assets', 'الأصول', 'asset', NULL, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rateb_chart_of_accounts WHERE company_id IS NULL AND code = '1000');
INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
SELECT NULL, '2000', 'Liabilities', 'الخصوم', 'liability', NULL, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rateb_chart_of_accounts WHERE company_id IS NULL AND code = '2000');
INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
SELECT NULL, '3000', 'Equity', 'حقوق الملكية', 'equity', NULL, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rateb_chart_of_accounts WHERE company_id IS NULL AND code = '3000');
INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
SELECT NULL, '4000', 'Revenue', 'الإيرادات', 'revenue', NULL, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rateb_chart_of_accounts WHERE company_id IS NULL AND code = '4000');
INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
SELECT NULL, '5000', 'Expenses', 'المصروفات', 'expense', NULL, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM rateb_chart_of_accounts WHERE company_id IS NULL AND code = '5000');

-- Per company: insert missing group headers
INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
SELECT c.id, '1000', 'Assets', 'الأصول', 'asset', NULL, 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_chart_of_accounts a WHERE a.company_id = c.id AND a.code = '1000');

INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
SELECT c.id, '2000', 'Liabilities', 'الخصوم', 'liability', NULL, 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_chart_of_accounts a WHERE a.company_id = c.id AND a.code = '2000');

INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
SELECT c.id, '3000', 'Equity', 'حقوق الملكية', 'equity', NULL, 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_chart_of_accounts a WHERE a.company_id = c.id AND a.code = '3000');

INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
SELECT c.id, '4000', 'Revenue', 'الإيرادات', 'revenue', NULL, 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_chart_of_accounts a WHERE a.company_id = c.id AND a.code = '4000');

INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, parent_id, is_active)
SELECT c.id, '5000', 'Expenses', 'المصروفات', 'expense', NULL, 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_chart_of_accounts a WHERE a.company_id = c.id AND a.code = '5000');

-- Link children → parents (platform template)
UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id IS NULL AND parent.code = '1000'
SET child.parent_id = parent.id
WHERE child.company_id IS NULL AND child.code IN ('1100','1200','1210','1300');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id IS NULL AND parent.code = '2000'
SET child.parent_id = parent.id
WHERE child.company_id IS NULL AND child.code IN ('2100','2200');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id IS NULL AND parent.code = '3000'
SET child.parent_id = parent.id
WHERE child.company_id IS NULL AND child.code IN ('3100');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id IS NULL AND parent.code = '4000'
SET child.parent_id = parent.id
WHERE child.company_id IS NULL AND child.code IN ('4100');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id IS NULL AND parent.code = '5000'
SET child.parent_id = parent.id
WHERE child.company_id IS NULL AND child.code IN ('5100','5200');

-- Link children → parents (each company)
UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id = child.company_id AND parent.code = '1000'
SET child.parent_id = parent.id
WHERE child.company_id IS NOT NULL AND child.code IN ('1100','1200','1210','1300');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id = child.company_id AND parent.code = '2000'
SET child.parent_id = parent.id
WHERE child.company_id IS NOT NULL AND child.code IN ('2100','2200');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id = child.company_id AND parent.code = '3000'
SET child.parent_id = parent.id
WHERE child.company_id IS NOT NULL AND child.code IN ('3100');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id = child.company_id AND parent.code = '4000'
SET child.parent_id = parent.id
WHERE child.company_id IS NOT NULL AND child.code IN ('4100');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id = child.company_id AND parent.code = '5000'
SET child.parent_id = parent.id
WHERE child.company_id IS NOT NULL AND child.code IN ('5100','5200');
