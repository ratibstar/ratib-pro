-- Register Automotive EPC permissions. Does not change existing role assignments.
--
-- Rollback procedure. Do not run it as part of this migration.
-- 293 does not create tables and has no foreign keys to 292.
-- Roll 293 back independently. If both migrations are removed, roll 293 back first
-- only so operators are not left with labels for tables that still exist; the
-- permission rows themselves do not reference 292.
-- Execution order:
-- 1. Take an approved backup of rateb_permissions and rateb_role_permissions.
-- 2. Review the SELECT below. It returns only rows that still have the original
--    English and Arabic names from this file and are not assigned to any role.
-- 3. Delete only those reviewed ids. Skip every permission that was assigned in
--    rateb_role_permissions, or whose name or name_ar was edited after this migration.
--
-- SELECT p.id, p.slug
-- FROM rateb_permissions p
-- WHERE p.slug IN (
--     'automotive.reference.view', 'automotive.reference.manage', 'automotive.reference.approve',
--     'automotive.reference.import', 'automotive.reference.retire', 'automotive.reference.audit',
--     'automotive.selection.view', 'automotive.selection.manage',
--     'automotive.inventory_link.view', 'automotive.inventory_link.manage',
--     'automotive.supplier_map.view', 'automotive.supplier_map.manage'
-- )
-- AND (p.slug = 'automotive.reference.view' AND p.name = 'View global automotive reference' AND p.name_ar = 'عرض المرجع العالمي للسيارات'
--   OR p.slug = 'automotive.reference.manage' AND p.name = 'Manage global automotive reference' AND p.name_ar = 'إدارة المرجع العالمي للسيارات'
--   OR p.slug = 'automotive.reference.approve' AND p.name = 'Approve global automotive reference' AND p.name_ar = 'اعتماد المرجع العالمي للسيارات'
--   OR p.slug = 'automotive.reference.import' AND p.name = 'Import global automotive reference' AND p.name_ar = 'استيراد المرجع العالمي للسيارات'
--   OR p.slug = 'automotive.reference.retire' AND p.name = 'Retire global automotive reference' AND p.name_ar = 'إيقاف المرجع العالمي للسيارات'
--   OR p.slug = 'automotive.reference.audit' AND p.name = 'Audit global automotive reference' AND p.name_ar = 'تدقيق المرجع العالمي للسيارات'
--   OR p.slug = 'automotive.selection.view' AND p.name = 'View company automotive selections' AND p.name_ar = 'عرض اختيارات السيارات للشركة'
--   OR p.slug = 'automotive.selection.manage' AND p.name = 'Manage company automotive selections' AND p.name_ar = 'إدارة اختيارات السيارات للشركة'
--   OR p.slug = 'automotive.inventory_link.view' AND p.name = 'View automotive inventory links' AND p.name_ar = 'عرض ربط مخزون السيارات'
--   OR p.slug = 'automotive.inventory_link.manage' AND p.name = 'Manage automotive inventory links' AND p.name_ar = 'إدارة ربط مخزون السيارات'
--   OR p.slug = 'automotive.supplier_map.view' AND p.name = 'View automotive supplier maps' AND p.name_ar = 'عرض خرائط موردي السيارات'
--   OR p.slug = 'automotive.supplier_map.manage' AND p.name = 'Manage automotive supplier maps' AND p.name_ar = 'إدارة خرائط موردي السيارات')
-- AND NOT EXISTS (
--     SELECT 1 FROM rateb_role_permissions rp WHERE rp.permission_id = p.id
-- );
-- Then, after that result is confirmed:
-- DELETE p FROM rateb_permissions p
-- INNER JOIN (the same filters) ...
-- Do not use a slug-only DELETE.

SET NAMES utf8mb4;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View global automotive reference', 'عرض المرجع العالمي للسيارات', 'automotive.reference.view', 'automotive', 'View global automotive reference records', 'عرض سجلات المرجع العالمي للسيارات'),
('Manage global automotive reference', 'إدارة المرجع العالمي للسيارات', 'automotive.reference.manage', 'automotive', 'Create and edit global automotive reference records', 'إنشاء وتعديل سجلات المرجع العالمي للسيارات'),
('Approve global automotive reference', 'اعتماد المرجع العالمي للسيارات', 'automotive.reference.approve', 'automotive', 'Approve global automotive reference records', 'اعتماد سجلات المرجع العالمي للسيارات'),
('Import global automotive reference', 'استيراد المرجع العالمي للسيارات', 'automotive.reference.import', 'automotive', 'Preview and import licensed automotive datasets', 'معاينة واستيراد مجموعات بيانات السيارات المرخصة'),
('Retire global automotive reference', 'إيقاف المرجع العالمي للسيارات', 'automotive.reference.retire', 'automotive', 'Retire global automotive reference records', 'إيقاف سجلات المرجع العالمي للسيارات'),
('Audit global automotive reference', 'تدقيق المرجع العالمي للسيارات', 'automotive.reference.audit', 'automotive', 'View global automotive reference audit history', 'عرض سجل تدقيق المرجع العالمي للسيارات'),
('View company automotive selections', 'عرض اختيارات السيارات للشركة', 'automotive.selection.view', 'automotive', 'View this company automotive selections', 'عرض اختيارات السيارات لهذه الشركة'),
('Manage company automotive selections', 'إدارة اختيارات السيارات للشركة', 'automotive.selection.manage', 'automotive', 'Manage this company automotive selections', 'إدارة اختيارات السيارات لهذه الشركة'),
('View automotive inventory links', 'عرض ربط مخزون السيارات', 'automotive.inventory_link.view', 'automotive', 'View links from global parts to company inventory', 'عرض ربط القطع العالمية بمخزون الشركة'),
('Manage automotive inventory links', 'إدارة ربط مخزون السيارات', 'automotive.inventory_link.manage', 'automotive', 'Link global parts to existing company inventory', 'ربط القطع العالمية بمخزون الشركة الحالي'),
('View automotive supplier maps', 'عرض خرائط موردي السيارات', 'automotive.supplier_map.view', 'automotive', 'View supplier part-number maps', 'عرض خرائط أرقام الموردين'),
('Manage automotive supplier maps', 'إدارة خرائط موردي السيارات', 'automotive.supplier_map.manage', 'automotive', 'Map supplier part numbers to global parts', 'ربط أرقام الموردين بالقطع العالمية')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    name_ar = VALUES(name_ar),
    module = VALUES(module),
    description = VALUES(description),
    description_ar = VALUES(description_ar);
