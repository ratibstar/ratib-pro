<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Marketplace;

use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

final class MarketplaceService
{
    public function __construct(private readonly RccAuditService $audit = new RccAuditService())
    {
    }

    /** @return list<array<string, mixed>> */
    public function catalog(?string $category = null): array
    {
        $sql = 'SELECT * FROM rcc_marketplace_addons WHERE is_published = 1';
        $params = [];
        if ($category !== null && $category !== '') {
            $sql .= ' AND category = :cat';
            $params['cat'] = $category;
        }
        $sql .= ' ORDER BY sort_order ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function subscribe(int $tenantId, int $addonId, ?int $userId, ?array $config = null): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_marketplace_addons WHERE id = :id AND is_published = 1');
        $stmt->execute(['id' => $addonId]);
        if (!$stmt->fetch()) {
            throw new \InvalidArgumentException('Add-on not found');
        }
        Database::connection()->prepare(
            'INSERT INTO rcc_tenant_addons (tenant_id, addon_id, status, config_json) VALUES (:tid, :aid, \'active\', :cfg)
             ON DUPLICATE KEY UPDATE status = \'active\', config_json = VALUES(config_json), cancelled_at = NULL'
        )->execute([
            'tid' => $tenantId,
            'aid' => $addonId,
            'cfg' => $config !== null ? json_encode($config, JSON_UNESCAPED_UNICODE) : null,
        ]);
        $this->audit->log($tenantId, 'marketplace.subscribe', $userId, 'addon', $addonId);
        EventBus::instance()->emit([
            'type' => EventType::MARKETPLACE_SUBSCRIBED,
            'tenant_id' => $tenantId,
            'payload' => ['addon_id' => $addonId],
        ]);
        return $this->tenantAddons($tenantId);
    }

    public function unsubscribe(int $tenantId, int $addonId, ?int $userId): void
    {
        Database::connection()->prepare(
            "UPDATE rcc_tenant_addons SET status = 'cancelled', cancelled_at = NOW() WHERE tenant_id = :tid AND addon_id = :aid"
        )->execute(['tid' => $tenantId, 'aid' => $addonId]);
        $this->audit->log($tenantId, 'marketplace.unsubscribe', $userId, 'addon', $addonId);
        EventBus::instance()->emit([
            'type' => EventType::MARKETPLACE_UNSUBSCRIBED,
            'tenant_id' => $tenantId,
            'payload' => ['addon_id' => $addonId],
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function tenantAddons(int $tenantId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT ta.*, a.code, a.name, a.name_ar, a.category, a.price_amount, a.currency
             FROM rcc_tenant_addons ta
             INNER JOIN rcc_marketplace_addons a ON a.id = ta.addon_id
             WHERE ta.tenant_id = :tid AND ta.status = \'active\''
        );
        $stmt->execute(['tid' => $tenantId]);
        return $stmt->fetchAll() ?: [];
    }
}
