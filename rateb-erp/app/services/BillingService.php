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

    /** @return array<int, array{id:int,label:string,company_id:int,amount:string}> */
    public function subscriptionOptions(?int $companyId = null, ?int $includeId = null): array
    {
        $sql = 'SELECT s.id, s.company_id, s.amount, s.billing_cycle, c.name AS company_name, p.name AS plan_name, s.status
                FROM rateb_subscriptions s
                INNER JOIN rateb_companies c ON c.id = s.company_id
                INNER JOIN rateb_plans p ON p.id = s.plan_id
                WHERE (s.status IN (\'active\', \'trial\')';
        $params = [];
        if ($includeId !== null && $includeId > 0) {
            $sql .= ' OR s.id = :inc';
            $params['inc'] = $includeId;
        }
        $sql .= ')';
        if ($companyId !== null && $companyId > 0) {
            $sql .= ' AND s.company_id = :cid';
            $params['cid'] = $companyId;
        }
        $sql .= ' ORDER BY s.id DESC LIMIT 200';
        $rows = (new Subscription())->query($sql, $params);
        $out = [];
        foreach ($rows as $row) {
            $cycle = (string) ($row['billing_cycle'] ?? 'monthly');
            $cycleLabel = $cycle === 'yearly' ? __('billing_cycle_yearly') : __('billing_cycle_monthly');
            $out[] = [
                'id' => (int) $row['id'],
                'company_id' => (int) $row['company_id'],
                'amount' => (string) ($row['amount'] ?? '0'),
                'label' => ($row['plan_name'] ?? '') . ' — ' . $cycleLabel,
            ];
        }
        return $out;
    }

    /** @return array<string, mixed>|null */
    public function activeSubscriptionForCompany(int $companyId): ?array
    {
        if ($companyId < 1) {
            return null;
        }
        $row = (new Subscription())->queryOne(
            'SELECT s.id, s.company_id, s.amount, s.billing_cycle, p.name AS plan_name
             FROM rateb_subscriptions s
             INNER JOIN rateb_plans p ON p.id = s.plan_id
             WHERE s.company_id = :cid AND s.status IN (\'active\', \'trial\')
             ORDER BY s.id DESC LIMIT 1',
            ['cid' => $companyId]
        );
        if (!$row) {
            return null;
        }
        $terms = ((string) ($row['billing_cycle'] ?? 'monthly')) === 'yearly' ? 30 : 15;
        return [
            'id' => (int) $row['id'],
            'amount' => (float) ($row['amount'] ?? 0),
            'payment_terms_days' => $terms,
            'label' => (string) ($row['plan_name'] ?? ''),
        ];
    }

    public function invoiceNoExists(string $invoiceNo, ?int $excludeId = null): bool
    {
        $invoiceNo = trim($invoiceNo);
        if ($invoiceNo === '') {
            return false;
        }
        $sql = 'SELECT id FROM rateb_invoices WHERE invoice_no = :no';
        $params = ['no' => $invoiceNo];
        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $row = (new \Rateb\App\Models\Invoice())->queryOne($sql, $params);
        return $row !== null;
    }

    public function nextInvoiceNo(): string
    {
        $year = date('Y');
        $prefix = 'INV-' . $year . '-';
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT invoice_no FROM rateb_invoices WHERE invoice_no LIKE :pfx ORDER BY id DESC LIMIT 1');
        $stmt->execute(['pfx' => $prefix . '%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $n = 1;
        if ($row && !empty($row['invoice_no'])) {
            $suffix = substr((string) $row['invoice_no'], strlen($prefix));
            $n = max(1, (int) $suffix + 1);
        }
        $candidate = $prefix . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
        while ($this->invoiceNoExists($candidate)) {
            $n++;
            $candidate = $prefix . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
        }
        return $candidate;
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
