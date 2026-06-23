<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\SaaS;

use Ratib\ContactCenter\App\Application\Services\Billing\SubscriptionService;
use Ratib\ContactCenter\App\Application\Services\RccAuditService;
use Ratib\ContactCenter\App\Core\Database;
use Ratib\ContactCenter\App\Core\Events\EventBus;
use Ratib\ContactCenter\App\Core\Events\EventType;

/** Automated tenant provisioning — creates tenant, admin user, default queue, trial subscription. */
final class TenantProvisioningService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions = new SubscriptionService(),
        private readonly RccAuditService $audit = new RccAuditService()
    ) {
    }

    /** @param array<string, mixed> $data */
    public function provision(array $data, ?int $actorUserId): array
    {
        $code = (string) ($data['code'] ?? ('tenant-' . bin2hex(random_bytes(4))));
        $name = (string) ($data['name'] ?? $code);
        $pdo = Database::connection();
        $pdo->prepare(
            'INSERT INTO rcc_tenants (code, name, name_ar, locale, billing_email, parent_tenant_id, reseller_id, status)
             VALUES (:code, :name, :name_ar, :locale, :email, :parent, :reseller, \'active\')'
        )->execute([
            'code' => $code,
            'name' => $name,
            'name_ar' => $data['name_ar'] ?? null,
            'locale' => (string) ($data['locale'] ?? 'ar'),
            'email' => $data['billing_email'] ?? ($data['admin_email'] ?? null),
            'parent' => $data['parent_tenant_id'] ?? null,
            'reseller' => $data['reseller_id'] ?? null,
        ]);
        $tenantId = (int) $pdo->lastInsertId();
        $adminEmail = (string) ($data['admin_email'] ?? ('admin@' . $code . '.local'));
        $passwordHash = password_hash((string) ($data['admin_password'] ?? bin2hex(random_bytes(8))), PASSWORD_DEFAULT);
        $pdo->prepare(
            'INSERT INTO rcc_users (tenant_id, email, password_hash, full_name, locale) VALUES (:tid, :email, :hash, :name, :locale)'
        )->execute([
            'tid' => $tenantId,
            'email' => $adminEmail,
            'hash' => $passwordHash,
            'name' => (string) ($data['admin_name'] ?? 'Administrator'),
            'locale' => (string) ($data['locale'] ?? 'ar'),
        ]);
        $userId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT IGNORE INTO rcc_user_roles (user_id, role_id, tenant_id) VALUES (:uid, 1, :tid)')
            ->execute(['uid' => $userId, 'tid' => $tenantId]);
        $pdo->prepare(
            "INSERT INTO rcc_queues (tenant_id, code, name, name_ar, sla_target_seconds, status, strategy)
             VALUES (:tid, 'default', 'Default Queue', 'الطابور الافتراضي', 60, 'active', 'rrmemory')"
        )->execute(['tid' => $tenantId]);
        $planId = (int) ($data['plan_id'] ?? 1);
        $this->subscriptions->subscribe($tenantId, $planId, $actorUserId, (int) ($data['trial_days'] ?? 14));
        $this->audit->log($tenantId, 'provisioning.tenant.created', $actorUserId, 'tenant', $tenantId, ['code' => $code]);
        EventBus::instance()->emit([
            'type' => EventType::TENANT_PROVISIONED,
            'tenant_id' => $tenantId,
            'payload' => ['code' => $code, 'admin_user_id' => $userId],
        ]);
        return [
            'tenant_id' => $tenantId,
            'code' => $code,
            'admin_user_id' => $userId,
            'admin_email' => $adminEmail,
        ];
    }
}
