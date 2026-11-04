-- Register Automotive EPC permissions. Does not change existing role assignments.

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
