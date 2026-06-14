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
        $updated += $this->repairChartOfAccounts($pdo);
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
                "SELECT name_ar AS v FROM rateb_chart_of_accounts WHERE code = '1220' LIMIT 1",
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

    private function repairChartOfAccounts(PDO $pdo): int
    {
        $labels = $this->chartOfAccountLabels();
        $updated = 0;
        $stmt = $pdo->prepare(
            'UPDATE rateb_chart_of_accounts SET name = :en, name_ar = :ar WHERE code = :code'
        );
        foreach ($labels as $code => [$nameEn, $nameAr]) {
            $stmt->execute(['en' => $nameEn, 'ar' => $nameAr, 'code' => $code]);
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
            'UPDATE rateb_warehouses SET name = :n WHERE name LIKE :bad',
            ['n' => 'المستودع الرئيسي', 'bad' => '%?%']
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
    private function chartOfAccountLabels(): array
    {
        $file = (defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2)) . '/config/coa-labels-ar.php';
        $labels = is_file($file) ? require $file : [];
        return is_array($labels) ? $labels : [];
    }

    /** @return array<string, array{0: string, 1: string}> */
    private function permissionLabels(): array
    {
        $file = (defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2)) . '/config/permission-labels-ar.php';
        $labels = is_file($file) ? require $file : [];
        return is_array($labels) ? $labels : [];
    }
}
