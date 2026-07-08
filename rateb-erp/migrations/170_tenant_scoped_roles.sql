-- RATEB ERP — Phase 2: tenant-scoped roles (per-company role rows + composite unique key)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Drop global slug uniqueness so the same slug can exist once per company.
ALTER TABLE rateb_roles DROP FOREIGN KEY fk_roles_company;
ALTER TABLE rateb_roles DROP INDEX uq_roles_slug;

-- Clone company-full-access per company from the global template (if any).
INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT c.id, 'Company Full Access', 'company-full-access', 'Default ERP access for company portal users', 1
FROM rateb_companies c
WHERE NOT EXISTS (
    SELECT 1 FROM rateb_roles r WHERE r.company_id = c.id AND r.slug = 'company-full-access'
);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r_new.id, rp.permission_id
FROM rateb_roles r_new
INNER JOIN rateb_roles r_tpl ON r_tpl.slug = 'company-full-access' AND r_tpl.company_id IS NULL
INNER JOIN rateb_role_permissions rp ON rp.role_id = r_tpl.id
WHERE r_new.slug = 'company-full-access' AND r_new.company_id IS NOT NULL;

-- Operational tenant roles (idempotent per company).
INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT c.id, 'Procurement Manager', 'procurement-manager', 'Manage purchase requests, orders, and RFQ', 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_roles r WHERE r.company_id = c.id AND r.slug = 'procurement-manager');

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT c.id, 'Inventory Manager', 'inventory-manager', 'Manage inventory, warehouses, and stock', 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_roles r WHERE r.company_id = c.id AND r.slug = 'inventory-manager');

INSERT INTO rateb_roles (company_id, name, slug, description, is_system)
SELECT c.id, 'HR Manager', 'hr-manager', 'Manage employees, attendance, and payroll', 1
FROM rateb_companies c
WHERE NOT EXISTS (SELECT 1 FROM rateb_roles r WHERE r.company_id = c.id AND r.slug = 'hr-manager');

-- Point company users at their company-local role rows (not global NULL-company templates).
UPDATE rateb_user_roles ur
INNER JOIN rateb_users u ON u.id = ur.user_id AND u.company_id > 0 AND COALESCE(u.is_super_admin, 0) = 0
INNER JOIN rateb_roles r_old ON r_old.id = ur.role_id AND r_old.company_id IS NULL
INNER JOIN rateb_roles r_new ON r_new.company_id = u.company_id AND r_new.slug = r_old.slug
SET ur.role_id = r_new.id
WHERE r_old.slug IN (
    'company-full-access', 'hq_admin', 'hq_manager', 'branch_manager', 'branch_user',
    'procurement-manager', 'inventory-manager', 'hr-manager',
    'pos_cashier', 'pos_supervisor', 'pos_manager'
);

-- Remove global tenant template rows (platform roles keep company_id NULL).
DELETE rp FROM rateb_role_permissions rp
INNER JOIN rateb_roles r ON r.id = rp.role_id
WHERE r.company_id IS NULL
  AND r.slug IN (
    'company-full-access', 'hq_admin', 'hq_manager', 'branch_manager', 'branch_user',
    'procurement-manager', 'inventory-manager', 'hr-manager',
    'pos_cashier', 'pos_supervisor', 'pos_manager'
);

DELETE ur FROM rateb_user_roles ur
INNER JOIN rateb_roles r ON r.id = ur.role_id
WHERE r.company_id IS NULL
  AND r.slug IN (
    'company-full-access', 'hq_admin', 'hq_manager', 'branch_manager', 'branch_user',
    'procurement-manager', 'inventory-manager', 'hr-manager',
    'pos_cashier', 'pos_supervisor', 'pos_manager'
);

DELETE FROM rateb_roles
WHERE company_id IS NULL
  AND slug IN (
    'company-full-access', 'hq_admin', 'hq_manager', 'branch_manager', 'branch_user',
    'procurement-manager', 'inventory-manager', 'hr-manager',
    'pos_cashier', 'pos_supervisor', 'pos_manager'
);

ALTER TABLE rateb_roles ADD UNIQUE KEY uq_roles_company_slug (company_id, slug);
ALTER TABLE rateb_roles ADD INDEX idx_roles_company (company_id);
ALTER TABLE rateb_roles ADD CONSTRAINT fk_roles_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE;
