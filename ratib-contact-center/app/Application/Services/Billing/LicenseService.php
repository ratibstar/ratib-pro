<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Billing;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class LicenseService
{
    public function __construct(private readonly RccAuditService $audit = new RccAuditService())
    {
    }

    public function issue(int $tenantId, int $seats, ?int $planId, ?int $userId, ?int $daysValid = 365): array
    {
        $key = 'RCC-' . strtoupper(bin2hex(random_bytes(8)));
        $expires = $daysValid !== null ? date('Y-m-d H:i:s', strtotime('+' . $daysValid . ' days')) : null;
        Database::connection()->prepare(
            'INSERT INTO rcc_licenses (tenant_id, license_key, plan_id, seats, expires_at) VALUES (:tid, :key, :pid, :seats, :exp)'
        )->execute(['tid' => $tenantId, 'key' => $key, 'pid' => $planId, 'seats' => $seats, 'exp' => $expires]);
        $id = (int) Database::connection()->lastInsertId();
        $this->audit->log($tenantId, 'license.issued', $userId, 'license', $id);
        EventBus::instance()->emit([
            'type' => EventType::LICENSE_UPDATED,
            'tenant_id' => $tenantId,
            'payload' => ['license_id' => $id],
        ]);
        return $this->find($tenantId, $id) ?? [];
    }

    public function validate(int $tenantId, string $licenseKey): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT id FROM rcc_licenses WHERE tenant_id = :tid AND license_key = :key AND status = 'active'
             AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1"
        );
        $stmt->execute(['tid' => $tenantId, 'key' => $licenseKey]);
        return $stmt->fetchColumn() !== false;
    }

    /** @return list<array<string, mixed>> */
    public function list(int $tenantId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_licenses WHERE tenant_id = :tid ORDER BY issued_at DESC');
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll() ?: [];
    }

    public function find(int $tenantId, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_licenses WHERE tenant_id = :tid AND id = :id');
        $stmt->execute(['tid' => $tenantId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
