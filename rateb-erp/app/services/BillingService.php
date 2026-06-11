<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Company;
use Rateb\App\Models\Plan;
use Rateb\App\Models\Subscription;
use PDO;

final class BillingService
{
    public static function normalizeCompanyId($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = (int) $value;
        return $id > 0 ? $id : null;
    }

    public function companyExists(int $companyId): bool
    {
        $row = (new Company())->queryOne('SELECT id FROM rateb_companies WHERE id = :id LIMIT 1', ['id' => $companyId]);
        return $row !== null;
    }

    public function planExists(int $planId): bool
    {
        $row = (new Plan())->queryOne('SELECT id FROM rateb_plans WHERE id = :id LIMIT 1', ['id' => $planId]);
        return $row !== null;
    }

    public function subscriptionBelongsToCompany(?int $subscriptionId, int $companyId): bool
    {
        if ($subscriptionId === null || $subscriptionId < 1) {
            return true;
        }
        $row = (new Subscription())->queryOne(
            'SELECT id FROM rateb_subscriptions WHERE id = :sid AND company_id = :cid LIMIT 1',
            ['sid' => $subscriptionId, 'cid' => $companyId]
        );
        return $row !== null;
    }

    /** @return array<int, array{id:int,name:string}> */
    public function companyOptions(): array
    {
        return (new Company())->query('SELECT id, name FROM rateb_companies ORDER BY name ASC LIMIT 500');
    }

    /** @return array<int, array{id:int,name:string}> */
    public function planOptions(): array
    {
        return (new Plan())->query('SELECT id, name FROM rateb_plans WHERE is_active = 1 ORDER BY price_monthly ASC');
    }

    /** @return array<int, array{id:int,label:string}> */
    public function subscriptionOptions(?int $companyId = null): array
    {
        $sql = 'SELECT s.id, s.company_id, c.name AS company_name, p.name AS plan_name
                FROM rateb_subscriptions s
                INNER JOIN rateb_companies c ON c.id = s.company_id
                INNER JOIN rateb_plans p ON p.id = s.plan_id';
        $params = [];
        if ($companyId !== null && $companyId > 0) {
            $sql .= ' WHERE s.company_id = :cid';
            $params['cid'] = $companyId;
        }
        $sql .= ' ORDER BY s.id DESC LIMIT 200';
        $rows = (new Subscription())->query($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row['id'],
                'label' => '#' . $row['id'] . ' — ' . ($row['company_name'] ?? '') . ' / ' . ($row['plan_name'] ?? ''),
            ];
        }
        return $out;
    }

    public function nextInvoiceNo(): string
    {
        $pdo = Database::connection();
        $row = $pdo->query('SELECT MAX(id) AS m FROM rateb_invoices')->fetch();
        $n = (int) ($row['m'] ?? 0) + 1;
        return 'INV-' . date('Y') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    public function ensureBillingReady(): void
    {
        (new AccountingService())->ensureDefaultAccounts(null);
        $pdo = Database::connection();
        $count = (int) $pdo->query('SELECT COUNT(*) AS c FROM rateb_companies')->fetch()['c'];
        if ($count > 0) {
            return;
        }
        $plan = (new Plan())->queryOne('SELECT id FROM rateb_plans WHERE is_active = 1 ORDER BY id ASC LIMIT 1');
        $planId = $plan ? (int) $plan['id'] : null;
        $stmt = $pdo->prepare(
            "INSERT INTO rateb_companies (name, slug, email, status, plan_id, user_limit, storage_limit_mb)
             VALUES ('شركة تجريبية', 'demo-company', 'demo@rateb.sa', 'active', :pid, 10, 1024)"
        );
        $stmt->execute(['pid' => $planId]);
    }
}
