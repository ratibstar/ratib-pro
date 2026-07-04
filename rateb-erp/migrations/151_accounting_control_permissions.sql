-- Phase 6: Enterprise Accounting Control Center permissions
INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('Accounting Control Dashboard', 'لوحة مركز التحكم المحاسبي', 'accounting.dashboard', 'accounting', 'View enterprise accounting control dashboard', 'عرض لوحة مركز التحكم المحاسبي'),
('Event Store Monitor', 'مراقبة مخزن الأحداث', 'accounting.events', 'accounting', 'View accounting event store', 'عرض مخزن أحداث المحاسبة'),
('Replay Center', 'مركز إعادة المعالجة', 'accounting.replay', 'accounting', 'Replay accounting events', 'إعادة معالجة أحداث المحاسبة'),
('Accounting Audit Center', 'مركز التدقيق المحاسبي', 'accounting.audit', 'accounting', 'View accounting audit logs and evidence', 'عرض سجلات التدقيق المحاسبي'),
('Projection Dashboard', 'لوحة التوقعات', 'accounting.projections', 'accounting', 'View financial projection snapshots', 'عرض لقطات التوقعات المالية'),
('Consolidation Dashboard', 'لوحة التوحيد', 'accounting.consolidation', 'accounting', 'View consolidated financial snapshots', 'عرض اللقطات المالية الموحدة'),
('Drift Detection', 'كشف الانحراف', 'accounting.drift', 'accounting', 'View drift detection reports', 'عرض تقارير انحراف المحاسبة'),
('Reconciliation Center', 'مركز التسوية', 'accounting.reconciliation', 'accounting', 'View and execute reconciliation', 'عرض وتنفيذ التسوية المحاسبية'),
('Financial Integrity', 'النزاهة المالية', 'accounting.integrity', 'accounting', 'View integrity and evidence packs', 'عرض النزاهة المالية وحزم الأدلة'),
('Accounting System Health', 'صحة النظام المحاسبي', 'accounting.system_health', 'accounting', 'View accounting pipeline health', 'عرض صحة خط المحاسبة')
ON DUPLICATE KEY UPDATE name = VALUES(name), module = VALUES(module);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN (
    'accounting.dashboard', 'accounting.events', 'accounting.replay', 'accounting.audit',
    'accounting.projections', 'accounting.consolidation', 'accounting.drift',
    'accounting.reconciliation', 'accounting.integrity', 'accounting.system_health'
)
WHERE r.slug IN ('company-full-access', 'hq_admin');
