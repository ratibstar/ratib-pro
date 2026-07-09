-- POS Design B — extended permission slugs (inventory adjust, supervisor approve, card payment)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('POS Inventory Adjust', 'تعديل مخزون نقطة البيع', 'pos.inventory.adjust', 'pos', 'Adjust stock from POS register', 'تعديل المخزون من شاشة البيع'),
('POS Supervisor Approve', 'اعتماد المشرف نقطة البيع', 'pos.supervisor.approve', 'pos', 'Supervisor biometric approval for sensitive POS actions', 'اعتماد بصمة المشرف للعمليات الحساسة'),
('POS Record Card Payment', 'تسجيل دفع البطاقة', 'pos.payment.record', 'pos', 'Record card and non-cash payments at POS', 'تسجيل دفع البطاقة والمدفوعات غير النقدية')
ON DUPLICATE KEY UPDATE name = VALUES(name), name_ar = VALUES(name_ar), description = VALUES(description);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug IN (
    'pos.inventory.adjust', 'pos.supervisor.approve', 'pos.payment.record'
)
WHERE r.slug IN ('pos_supervisor', 'pos_manager', 'super-admin', 'company-full-access');

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
INNER JOIN rateb_permissions p ON p.slug = 'pos.payment.record'
WHERE r.slug = 'pos_cashier';
