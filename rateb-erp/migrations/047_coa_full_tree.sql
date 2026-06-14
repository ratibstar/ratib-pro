-- Standard full COA tree: seed missing accounts + link parents (per company + platform)
SET NAMES utf8mb4;

-- Helper: insert account if missing (platform template company_id IS NULL)
INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, is_active)
SELECT NULL, v.code, v.name, v.name_ar, v.account_type, 1
FROM (
    SELECT '1150' AS code, 'Bank Accounts' AS name, 'الحسابات البنكية' AS name_ar, 'asset' AS account_type UNION ALL
    SELECT '1220', 'Advances to Suppliers', 'سلف موردين', 'asset' UNION ALL
    SELECT '1400', 'Prepaid Expenses', 'مصروفات مقدمة', 'asset' UNION ALL
    SELECT '1500', 'Fixed Assets', 'الأصول الثابتة', 'asset' UNION ALL
    SELECT '1510', 'Equipment', 'معدات', 'asset' UNION ALL
    SELECT '1520', 'Vehicles', 'مركبات', 'asset' UNION ALL
    SELECT '1530', 'Buildings', 'مباني', 'asset' UNION ALL
    SELECT '1590', 'Accumulated Depreciation', 'مجمع الإهلاك', 'asset' UNION ALL
    SELECT '2110', 'Customer Advances', 'دفعات مقدمة من العملاء', 'liability' UNION ALL
    SELECT '2300', 'Accrued Expenses', 'مصروفات مستحقة', 'liability' UNION ALL
    SELECT '2400', 'Salaries Payable', 'رواتب مستحقة', 'liability' UNION ALL
    SELECT '2500', 'Short-term Loans', 'قروض قصيرة الأجل', 'liability' UNION ALL
    SELECT '3200', 'Share Capital', 'رأس المال', 'equity' UNION ALL
    SELECT '3300', 'Current Year Profit/Loss', 'أرباح/خسائر العام الحالي', 'equity' UNION ALL
    SELECT '4200', 'Service Revenue', 'إيرادات الخدمات', 'revenue' UNION ALL
    SELECT '4300', 'Other Income', 'إيرادات أخرى', 'revenue' UNION ALL
    SELECT '4900', 'Sales Returns & Allowances', 'مردودات ومسموحات المبيعات', 'revenue' UNION ALL
    SELECT '5300', 'Salaries & Wages', 'الرواتب والأجور', 'expense' UNION ALL
    SELECT '5400', 'Rent Expense', 'مصروف الإيجار', 'expense' UNION ALL
    SELECT '5500', 'Utilities', 'مرافق (كهرباء، ماء، …)', 'expense' UNION ALL
    SELECT '5600', 'Depreciation Expense', 'مصروف الإهلاك', 'expense' UNION ALL
    SELECT '5700', 'Bank & Finance Charges', 'عمولات بنكية ومالية', 'expense' UNION ALL
    SELECT '5800', 'General & Administrative', 'مصروفات عمومية وإدارية', 'expense' UNION ALL
    SELECT '5900', 'Marketing & Sales', 'مصروفات تسويق ومبيعات', 'expense'
) v
WHERE NOT EXISTS (
    SELECT 1 FROM rateb_chart_of_accounts a WHERE a.company_id IS NULL AND a.code = v.code
);

-- Per company: same accounts
INSERT INTO rateb_chart_of_accounts (company_id, code, name, name_ar, account_type, is_active)
SELECT c.id, v.code, v.name, v.name_ar, v.account_type, 1
FROM rateb_companies c
CROSS JOIN (
    SELECT '1150' AS code, 'Bank Accounts' AS name, 'الحسابات البنكية' AS name_ar, 'asset' AS account_type UNION ALL
    SELECT '1220', 'Advances to Suppliers', 'سلف موردين', 'asset' UNION ALL
    SELECT '1400', 'Prepaid Expenses', 'مصروفات مقدمة', 'asset' UNION ALL
    SELECT '1500', 'Fixed Assets', 'الأصول الثابتة', 'asset' UNION ALL
    SELECT '1510', 'Equipment', 'معدات', 'asset' UNION ALL
    SELECT '1520', 'Vehicles', 'مركبات', 'asset' UNION ALL
    SELECT '1530', 'Buildings', 'مباني', 'asset' UNION ALL
    SELECT '1590', 'Accumulated Depreciation', 'مجمع الإهلاك', 'asset' UNION ALL
    SELECT '2110', 'Customer Advances', 'دفعات مقدمة من العملاء', 'liability' UNION ALL
    SELECT '2300', 'Accrued Expenses', 'مصروفات مستحقة', 'liability' UNION ALL
    SELECT '2400', 'Salaries Payable', 'رواتب مستحقة', 'liability' UNION ALL
    SELECT '2500', 'Short-term Loans', 'قروض قصيرة الأجل', 'liability' UNION ALL
    SELECT '3200', 'Share Capital', 'رأس المال', 'equity' UNION ALL
    SELECT '3300', 'Current Year Profit/Loss', 'أرباح/خسائر العام الحالي', 'equity' UNION ALL
    SELECT '4200', 'Service Revenue', 'إيرادات الخدمات', 'revenue' UNION ALL
    SELECT '4300', 'Other Income', 'إيرادات أخرى', 'revenue' UNION ALL
    SELECT '4900', 'Sales Returns & Allowances', 'مردودات ومسموحات المبيعات', 'revenue' UNION ALL
    SELECT '5300', 'Salaries & Wages', 'الرواتب والأجور', 'expense' UNION ALL
    SELECT '5400', 'Rent Expense', 'مصروف الإيجار', 'expense' UNION ALL
    SELECT '5500', 'Utilities', 'مرافق (كهرباء، ماء، …)', 'expense' UNION ALL
    SELECT '5600', 'Depreciation Expense', 'مصروف الإهلاك', 'expense' UNION ALL
    SELECT '5700', 'Bank & Finance Charges', 'عمولات بنكية ومالية', 'expense' UNION ALL
    SELECT '5800', 'General & Administrative', 'مصروفات عمومية وإدارية', 'expense' UNION ALL
    SELECT '5900', 'Marketing & Sales', 'مصروفات تسويق ومبيعات', 'expense'
) v
WHERE NOT EXISTS (
    SELECT 1 FROM rateb_chart_of_accounts a WHERE a.company_id = c.id AND a.code = v.code
);

-- Link parents (platform) — assets under 1000
UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id IS NULL AND parent.code = '1000'
SET child.parent_id = parent.id
WHERE child.company_id IS NULL AND child.code IN ('1100','1150','1200','1210','1220','1300','1400','1500');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id IS NULL AND parent.code = '1500'
SET child.parent_id = parent.id
WHERE child.company_id IS NULL AND child.code IN ('1510','1520','1530','1590');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id IS NULL AND parent.code = '1150'
SET child.parent_id = parent.id
WHERE child.company_id IS NULL AND child.code REGEXP '^111[0-9]$';

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id IS NULL AND parent.code = '2000'
SET child.parent_id = parent.id
WHERE child.company_id IS NULL AND child.code IN ('2100','2110','2200','2300','2400','2500');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id IS NULL AND parent.code = '3000'
SET child.parent_id = parent.id
WHERE child.company_id IS NULL AND child.code IN ('3100','3200','3300');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id IS NULL AND parent.code = '4000'
SET child.parent_id = parent.id
WHERE child.company_id IS NULL AND child.code IN ('4100','4200','4300','4900');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id IS NULL AND parent.code = '5000'
SET child.parent_id = parent.id
WHERE child.company_id IS NULL AND child.code IN ('5100','5200','5300','5400','5500','5600','5700','5800','5900');

-- Link parents (each company)
UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id = child.company_id AND parent.code = '1000'
SET child.parent_id = parent.id
WHERE child.company_id IS NOT NULL AND child.code IN ('1100','1150','1200','1210','1220','1300','1400','1500');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id = child.company_id AND parent.code = '1500'
SET child.parent_id = parent.id
WHERE child.company_id IS NOT NULL AND child.code IN ('1510','1520','1530','1590');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id = child.company_id AND parent.code = '1150'
SET child.parent_id = parent.id
WHERE child.company_id IS NOT NULL AND child.code REGEXP '^111[0-9]$';

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id = child.company_id AND parent.code = '2000'
SET child.parent_id = parent.id
WHERE child.company_id IS NOT NULL AND child.code IN ('2100','2110','2200','2300','2400','2500');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id = child.company_id AND parent.code = '3000'
SET child.parent_id = parent.id
WHERE child.company_id IS NOT NULL AND child.code IN ('3100','3200','3300');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id = child.company_id AND parent.code = '4000'
SET child.parent_id = parent.id
WHERE child.company_id IS NOT NULL AND child.code IN ('4100','4200','4300','4900');

UPDATE rateb_chart_of_accounts child
JOIN rateb_chart_of_accounts parent ON parent.company_id = child.company_id AND parent.code = '5000'
SET child.parent_id = parent.id
WHERE child.company_id IS NOT NULL AND child.code IN ('5100','5200','5300','5400','5500','5600','5700','5800','5900');
