<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\SaaS;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class ResellerService
{
    public function __construct(private readonly RccAuditService $audit = new RccAuditService())
    {
    }

    /** @param array<string, mixed> $data */
    public function register(int $tenantId, array $data, ?int $userId): array
    {
        $code = (string) ($data['code'] ?? ('RSL-' . strtoupper(bin2hex(random_bytes(3)))));
        Database::connection()->prepare(
            'INSERT INTO rcc_resellers (tenant_id, code, name, name_ar, contact_email, commission_rate, revenue_share_pct)
             VALUES (:tid, :code, :name, :name_ar, :email, :comm, :share)'
        )->execute([
            'tid' => $tenantId,
            'code' => $code,
            'name' => (string) ($data['name'] ?? 'Reseller'),
            'name_ar' => $data['name_ar'] ?? null,
            'email' => (string) ($data['contact_email'] ?? ''),
            'comm' => (float) ($data['commission_rate'] ?? 15),
            'share' => (float) ($data['revenue_share_pct'] ?? 20),
        ]);
        $id = (int) Database::connection()->lastInsertId();
        $this->audit->log($tenantId, 'reseller.registered', $userId, 'reseller', $id);
        EventBus::instance()->emit(['type' => EventType::RESELLER_UPDATED, 'tenant_id' => $tenantId, 'payload' => ['reseller_id' => $id]]);
        return $this->findByTenant($tenantId) ?? [];
    }

    public function findByTenant(int $tenantId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_resellers WHERE tenant_id = :tid LIMIT 1');
        $stmt->execute(['tid' => $tenantId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listSubTenants(int $resellerId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT t.* FROM rcc_tenants t WHERE t.reseller_id = :rid ORDER BY t.id ASC'
        );
        $stmt->execute(['rid' => $resellerId]);
        return $stmt->fetchAll() ?: [];
    }

    public function recordCommission(int $resellerId, int $subTenantId, float $amount, string $currency, ?int $invoiceId, ?int $paymentId): void
    {
        $reseller = Database::connection()->prepare('SELECT commission_rate FROM rcc_resellers WHERE id = :id');
        $reseller->execute(['id' => $resellerId]);
        $rate = (float) ($reseller->fetchColumn() ?: 0);
        $commission = round($amount * ($rate / 100), 2);
        Database::connection()->prepare(
            'INSERT INTO rcc_reseller_commissions (reseller_id, sub_tenant_id, invoice_id, payment_id, commission_amount, currency, period_month)
             VALUES (:rid, :sub, :inv, :pay, :amt, :cur, :month)'
        )->execute([
            'rid' => $resellerId,
            'sub' => $subTenantId,
            'inv' => $invoiceId,
            'pay' => $paymentId,
            'amt' => $commission,
            'cur' => $currency,
            'month' => date('Y-m'),
        ]);
        EventBus::instance()->emit([
            'type' => EventType::RESELLER_COMMISSION_RECORDED,
            'tenant_id' => $subTenantId,
            'payload' => ['reseller_id' => $resellerId, 'commission' => $commission],
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function commissions(int $resellerId, ?string $month = null): array
    {
        $month = $month ?? date('Y-m');
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rcc_reseller_commissions WHERE reseller_id = :rid AND period_month = :month ORDER BY created_at DESC'
        );
        $stmt->execute(['rid' => $resellerId, 'month' => $month]);
        return $stmt->fetchAll() ?: [];
    }
}
