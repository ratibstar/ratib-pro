-- PX6 — dedicated POS sale completion permission.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
(
    'POS Complete Sale',
    'إتمام بيع نقطة البيع',
    'pos.sale.complete',
    'pos',
    'Complete checkout and commit POS sale effects',
    'إتمام الدفع وتثبيت آثار عملية البيع'
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    name_ar = VALUES(name_ar),
    description = VALUES(description),
    description_ar = VALUES(description_ar);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug = 'pos.sale.complete'
WHERE r.slug IN (
    'pos_cashier',
    'pos_supervisor',
    'pos_manager',
    'branch_user',
    'branch_manager',
    'super-admin',
    'company-full-access'
);
