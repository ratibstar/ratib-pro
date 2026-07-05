-- RATEB ERP — POS role bundles + branch role POS permissions
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT c.id, 'POS Cashier', 'pos_cashier', 'POS register and shift open', 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_roles r WHERE r.company_id = c.id AND r.slug = 'pos_cashier');

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT c.id, 'POS Supervisor', 'pos_supervisor', 'POS supervisor — shifts, returns, reports', 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_roles r WHERE r.company_id = c.id AND r.slug = 'pos_supervisor');

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT c.id, 'POS Manager', 'pos_manager', 'POS manager — terminals, settings, Z report', 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_roles r WHERE r.company_id = c.id AND r.slug = 'pos_manager');

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug IN (
    'pos.view', 'pos.register', 'pos.shift.open'
)
WHERE r.slug = 'pos_cashier';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug IN (
    'pos.view', 'pos.register', 'pos.shift.open', 'pos.shift.close',
    'pos.discount.manage', 'pos.returns.manage', 'pos.reports.view', 'pos.cash_drawer.manage',
    'pos.orders.view'
)
WHERE r.slug = 'pos_supervisor';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug IN (
    'pos.view', 'pos.manage', 'pos.register', 'pos.terminal.manage',
    'pos.shift.open', 'pos.shift.close', 'pos.cash_drawer.manage',
    'pos.orders.view', 'pos.settings.manage', 'pos.sync.manage',
    'pos.reports.view', 'pos.reports.z'
)
WHERE r.slug = 'pos_manager';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug IN (
    'pos.view', 'pos.register', 'pos.shift.open', 'pos.shift.close',
    'pos.orders.view', 'pos.reports.view', 'pos.cash_drawer.manage', 'pos.terminal.manage'
)
WHERE r.slug = 'branch_manager';

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug IN (
    'pos.view', 'pos.register', 'pos.shift.open', 'pos.orders.view'
)
WHERE r.slug = 'branch_user';
