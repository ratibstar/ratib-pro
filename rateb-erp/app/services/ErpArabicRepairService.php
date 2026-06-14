<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use Rateb\App\Core\Database;

/**
 * Repairs Arabic text in core ERP tables when migrations ran with wrong charset
 * (name_ar shows as ?????? or م becomes ?).
 */
final class ErpArabicRepairService
{
    /** @return array{updated: int, permissions_sample: string} */
    public function repair(): array
    {
        $pdo = Database::connection();
        $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

        $updated = 0;
        $updated += $this->repairPermissions($pdo);
        $updated += $this->repairDemoOperational($pdo);

        $sample = '';
        $stmt = $pdo->query("SELECT name_ar FROM rateb_permissions WHERE slug = 'dashboard.view' LIMIT 1");
        if ($stmt !== false) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            $sample = is_array($row) ? (string) ($row['name_ar'] ?? '') : '';
        }

        return ['updated' => $updated, 'permissions_sample' => $sample];
    }

    public function needsRepair(): bool
    {
        try {
            $pdo = Database::connection();
            $checks = [
                "SELECT name_ar AS v FROM rateb_permissions WHERE slug = 'dashboard.view' LIMIT 1",
                "SELECT name_ar AS v FROM rateb_permissions WHERE slug = 'inventory.manage' LIMIT 1",
                "SELECT name AS v FROM rateb_warehouses WHERE code = 'WH-MAIN' LIMIT 1",
            ];
            foreach ($checks as $sql) {
                $stmt = $pdo->query($sql);
                if ($stmt === false) {
                    continue;
                }
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $stmt->closeCursor();
                if (!is_array($row)) {
                    continue;
                }
                $value = (string) ($row['v'] ?? '');
                if ($value === '' || strpos($value, '?') !== false || preg_match('/^\?+$/', $value) === 1) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function repairPermissions(PDO $pdo): int
    {
        $updated = 0;
        foreach ($this->permissionLabels() as $slug => [$nameAr, $descAr]) {
            $stmt = $pdo->prepare(
                'UPDATE rateb_permissions SET name_ar = :n, description_ar = :d WHERE slug = :s'
            );
            $stmt->execute(['n' => $nameAr, 'd' => $descAr, 's' => $slug]);
            $updated += $stmt->rowCount();
        }
        return $updated;
    }

    private function repairDemoOperational(PDO $pdo): int
    {
        $updated = 0;

        /** @param array<string, string> $params */
        $patch = static function (string $sql, array $params) use ($pdo, &$updated): void {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $updated += $stmt->rowCount();
        };

        $patch(
            'UPDATE rateb_warehouses SET name = :n, location = :l WHERE code = :c',
            ['n' => 'المستودع الرئيسي', 'l' => 'الرياض', 'c' => 'WH-MAIN']
        );

        $patch(
            'UPDATE rateb_inventory SET item_name = :n, category = :cat WHERE sku = :s',
            ['n' => 'قفازات طبية — Large', 'cat' => 'مستهلكات', 's' => 'GLV-L']
        );
        $patch(
            'UPDATE rateb_inventory SET item_name = :n, category = :cat WHERE sku = :s',
            ['n' => 'محلول تعقيم', 'cat' => 'مستهلكات', 's' => 'DSF-500']
        );

        $patch(
            'UPDATE rateb_suppliers SET name = :n WHERE code = :c',
            ['n' => 'مورد تجريبي — المعدات الطبية', 'c' => 'SUP-DEMO']
        );

        $patch(
            'UPDATE rateb_purchase_requests SET title = :t, department = :d, notes = :notes WHERE request_no = :no',
            [
                't' => 'طلب شراء مستهلكات طبية',
                'd' => 'المشتريات',
                'notes' => 'بيانات تجريبية للعرض في لوحة الإدارة',
                'no' => 'PR-00001',
            ]
        );

        $patch(
            'UPDATE rateb_purchase_orders SET notes = :n WHERE order_no = :no',
            ['n' => 'أمر شراء تجريبي', 'no' => 'PO-00001']
        );

        $patch(
            'UPDATE rateb_stock_movements SET notes = :n WHERE reference_type = :r AND movement_type = :m',
            ['n' => 'استلام أولي — تجريبي', 'r' => 'demo_seed', 'm' => 'in']
        );
        $patch(
            'UPDATE rateb_stock_movements SET notes = :n WHERE reference_type = :r AND movement_type = :m AND notes LIKE :bad',
            ['n' => 'صرف تجريبي', 'r' => 'demo_seed', 'm' => 'out', 'bad' => '%?%']
        );

        return $updated;
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function permissionLabels(): array
    {
        return [
            'dashboard.view' => ['عرض لوحة التحكم', 'الوصول إلى لوحة التحكم'],
            'companies.manage' => ['إدارة الشركات', 'إدارة كاملة للشركات'],
            'companies.view' => ['عرض الشركات', 'عرض قائمة الشركات'],
            'subscriptions.manage' => ['إدارة الاشتراكات', 'إدارة اشتراكات الشركات'],
            'plans.manage' => ['إدارة الباقات', 'إدارة باقات الاشتراك'],
            'users.manage' => ['إدارة المستخدمين', 'إدارة مستخدمي المنصة'],
            'roles.manage' => ['إدارة الأدوار', 'إدارة أدوار المستخدمين'],
            'permissions.manage' => ['إدارة الصلاحيات', 'إدارة صلاحيات النظام'],
            'procurement.manage' => ['إدارة المشتريات', 'إدارة عمليات الشراء'],
            'inventory.manage' => ['إدارة المخزون', 'إدارة المخزون والمستودعات'],
            'suppliers.manage' => ['إدارة الموردين', 'إدارة سجل الموردين'],
            'assets.manage' => ['إدارة الأصول', 'إدارة الأصول الثابتة'],
            'contracts.manage' => ['إدارة العقود', 'إدارة العقود'],
            'tenders.manage' => ['إدارة المناقصات', 'إدارة المناقصات'],
            'reports.view' => ['عرض التقارير', 'عرض تقارير المنصة'],
            'settings.manage' => ['إدارة الإعدادات', 'إدارة إعدادات النظام'],
            'evaluations.manage' => ['إدارة تقييم الموردين', 'إنشاء وإدارة تقييمات الموردين'],
            'evaluations.view' => ['عرض تقييم الموردين', 'عرض سجلات تقييم الموردين'],
            'company_plans.manage' => ['إدارة باقات الشركات', 'تعديل حدود الباقة والوحدات للشركات'],
            'access.manage' => ['إدارة التحكم بالوصول', 'التحكم الكامل بالمستخدمين والأدوار والصلاحيات'],
            'accounting.view' => ['عرض الحسابات', 'عرض دليل الحسابات والقيود'],
            'accounting.manage' => ['إدارة الحسابات', 'إدارة دليل الحسابات والقيود اليومية'],
            'accounting.post' => ['ترحيل القيود', 'ترحيل وإلغاء القيود المحاسبية'],
            'accounting.approve' => ['اعتماد القيود والسندات', 'اعتماد وترحيل القيود اليومية وسندات الصرف والقبض'],
            'billing.manage' => ['إدارة الفوترة', 'إدارة الفواتير والمدفوعات'],
            'stock_movements.view' => ['عرض حركات المخزون', 'عرض سجل حركات المخزون'],
            'stock_movements.manage' => ['إدارة حركات المخزون', 'إنشاء حركات المخزون'],
            'categories.view' => ['عرض تصنيفات المنتجات', 'عرض تصنيفات المنتجات'],
            'categories.manage' => ['إدارة تصنيفات المنتجات', 'إدارة تصنيفات المنتجات'],
            'inventory_audit.view' => ['عرض جرد المخزون', 'عرض عمليات الجرد'],
            'inventory_audit.manage' => ['إدارة جرد المخزون', 'إدارة عمليات الجرد'],
            'documents.view' => ['عرض المستندات', 'عرض المستندات المرفوعة'],
            'documents.manage' => ['إدارة المستندات', 'رفع وإدارة المستندات'],
            'workflows.view' => ['عرض سير الموافقات', 'عرض سير الموافقات'],
            'workflows.manage' => ['إدارة سير الموافقات', 'إعداد سير الموافقات'],
            'workflows.approve' => ['اعتماد الطلبات', 'اعتماد أو رفض الطلبات'],
            'reports.export' => ['تصدير التقارير', 'تصدير PDF Excel CSV'],
            'supplier_comms.view' => ['عرض تواصل الموردين', 'عرض سجل التواصل'],
            'supplier_comms.manage' => ['إدارة تواصل الموردين', 'تسجيل التواصل مع الموردين'],
            'contract_renewals.view' => ['عرض تجديد العقود', 'عرض تجديدات العقود'],
            'contract_renewals.manage' => ['إدارة تجديد العقود', 'إدارة تجديدات العقود'],
            'asset_maintenance.view' => ['عرض صيانة الأصول', 'عرض صيانة الأصول'],
            'asset_maintenance.manage' => ['إدارة صيانة الأصول', 'إدارة صيانة الأصول'],
            'device_service.view' => ['عرض خدمة الأجهزة', 'عرض سجل خدمة الأجهزة'],
            'device_service.manage' => ['إدارة خدمة الأجهزة', 'إدارة سجل خدمة الأجهزة'],
            'procurement.analytics' => ['عرض تحليلات المشتريات', 'عرض مؤشرات المشتريات'],
            'notifications.manage' => ['إدارة الإشعارات', 'إدارة مركز الإشعارات'],
            'inventory_batches.view' => ['عرض دفعات المخزون', 'عرض تتبع دفعات المخزون'],
            'inventory_batches.manage' => ['إدارة دفعات المخزون', 'إنشاء وإدارة دفعات المخزون'],
            'supplier_classifications.view' => ['عرض تصنيف الموردين', 'عرض تصنيفات الموردين'],
            'supplier_classifications.manage' => ['إدارة تصنيف الموردين', 'إدارة تصنيفات الموردين'],
            'supplier_kpi.view' => ['عرض مؤشرات الموردين', 'عرض لوحة مؤشرات الموردين'],
            'asset_assignments.view' => ['عرض تعيين الأصول', 'عرض تعيين الأصول'],
            'asset_assignments.manage' => ['إدارة تعيين الأصول', 'إدارة تعيين الأصول'],
            'asset_depreciation.view' => ['عرض إهلاك الأصول', 'عرض سجل إهلاك الأصول'],
            'asset_depreciation.manage' => ['إدارة إهلاك الأصول', 'تسجيل إهلاك الأصول'],
            'device_warranty.view' => ['عرض ضمان الأجهزة', 'عرض تتبع ضمان الأجهزة'],
            'device_spare_parts.manage' => ['إدارة قطع غيار الأجهزة', 'إدارة مخزون قطع غيار الأجهزة'],
            'executive.dashboard.view' => ['عرض لوحة الإدارة التنفيذية', 'لوحة إدارية تنفيذية عبر الشركات'],
            'reports.kpi.view' => ['عرض تقارير مؤشرات الشركة', 'لوحة مؤشرات الشركة والتصدير'],
            'reports.cost_analysis.view' => ['عرض تقارير تحليل التكلفة', 'تقارير تحليل التكلفة'],
            'reports.inventory_valuation.view' => ['عرض تقارير تقييم المخزون', 'تقارير تقييم المخزون'],
            'warehouse_transfers.view' => ['عرض تحويلات المستودعات', 'عرض طلبات التحويل بين المستودعات'],
            'warehouse_transfers.manage' => ['إدارة تحويلات المستودعات', 'إنشاء واعتماد تحويلات المستودعات'],
            'inventory_forecast.view' => ['عرض توقعات المخزون', 'عرض توقعات إعادة الطلب واستهلاك المخزون'],
            'cms.view' => ['عرض نظام المحتوى', 'عرض نظام المحتوى'],
            'cms.manage' => ['إدارة نظام المحتوى', 'إدارة نظام المحتوى'],
            'cms.leads' => ['إدارة العملاء المحتملين', 'إدارة العملاء المحتملين'],
            'cms.seo' => ['إدارة تحسين محركات البحث', 'إدارة تحسين محركات البحث'],
            'cms.media' => ['إدارة مكتبة الوسائط', 'إدارة مكتبة الوسائط'],
        ];
    }
}
