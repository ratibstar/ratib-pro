<?php
declare(strict_types=1);

namespace Rateb\App\Subscription\Admin;

use Rateb\App\Core\Database;
use Rateb\App\Services\NotificationService;
use Rateb\App\Subscription\SubscriptionStatus;

/**
 * Fan-out subscription lifecycle alerts to platform super-admins
 * so ops can follow any tenant that enters warning / grace / suspension.
 */
final class SubscriptionAdminNotifier
{
    public const TRIGGER = 'subscription_engine_alert';

    /**
     * Companies currently in an alert window (≤14 days, grace, suspended).
     *
     * @return list<array<string, mixed>>
     */
    public function listAlertWindowTenants(string $todayYmd, int $withinDays = 14): array
    {
        $withinDays = max(1, min(90, $withinDays));
        try {
            $pdo = Database::connection();
            $sql = 'SELECT e.company_id,
                           c.name AS company_name,
                           e.subscription_end,
                           e.current_status,
                           e.suspended_at,
                           DATEDIFF(e.subscription_end, :today) AS days_remaining
                    FROM rateb_subscription_engine e
                    LEFT JOIN rateb_companies c ON c.id = e.company_id
                    WHERE e.current_status = \'SUSPENDED\'
                       OR e.suspended_at IS NOT NULL
                       OR e.current_status IN (\'GRACE\', \'SUSPENSION_PENDING\')
                       OR e.subscription_end <= DATE_ADD(:today2, INTERVAL ' . $withinDays . ' DAY)
                    ORDER BY e.subscription_end ASC, e.company_id ASC
                    LIMIT 200';
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['today' => $todayYmd, 'today2' => $todayYmd]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            error_log('RATEB SubscriptionAdminNotifier::listAlertWindowTenants: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Create in-app notifications for all active super-admins (once per company/day).
     *
     * @return array{companies:int,notifications:int,items:list<array<string,mixed>>}
     */
    public function fanOutToPlatformAdmins(?string $todayYmd = null): array
    {
        $today = $todayYmd ?? gmdate('Y-m-d');
        $rows = $this->listAlertWindowTenants($today, SubscriptionAdminViewModel::EXPIRING_SOON_DAYS);
        $items = $this->mapItems($rows);

        if ($rows === [] || !class_exists(NotificationService::class)) {
            return ['companies' => 0, 'notifications' => 0, 'items' => []];
        }

        $adminIds = $this->superAdminUserIds();
        if ($adminIds === []) {
            return ['companies' => count($rows), 'notifications' => 0, 'items' => $items];
        }

        $notifier = new NotificationService();
        $notifCount = 0;

        foreach ($rows as $row) {
            $companyId = (int) ($row['company_id'] ?? 0);
            if ($companyId < 1) {
                continue;
            }
            $name = trim((string) ($row['company_name'] ?? ''));
            if ($name === '') {
                $name = '#' . $companyId;
            }
            $days = (int) ($row['days_remaining'] ?? 0);
            $status = strtoupper((string) ($row['current_status'] ?? SubscriptionStatus::ACTIVE));
            $kind = self::classify($row, $days, $status);
            $title = function_exists('__')
                ? (string) __('subscription_admin_alert_title')
                : 'Subscription alert';
            $message = $this->messageFor($kind, $name, $days, $companyId);
            $type = ($kind === 'suspended' || $kind === 'grace') ? 'danger' : 'warning';

            foreach ($adminIds as $uid) {
                if ($this->alreadyNotifiedToday($uid, $companyId, $today)) {
                    continue;
                }
                try {
                    $notifier->notifyUser(
                        $uid,
                        $companyId,
                        $title,
                        $message,
                        $type,
                        self::TRIGGER,
                        'company',
                        $companyId
                    );
                    $notifCount++;
                } catch (\Throwable $e) {
                    error_log('RATEB SubscriptionAdminNotifier notify: ' . $e->getMessage());
                }
            }
        }

        return [
            'companies' => count($rows),
            'notifications' => $notifCount,
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function classify(array $row, int $days, string $status): string
    {
        $suspendedAt = trim((string) ($row['suspended_at'] ?? ''));
        if ($status === SubscriptionStatus::SUSPENDED || $suspendedAt !== '') {
            return 'suspended';
        }
        if ($status === SubscriptionStatus::GRACE
            || $status === SubscriptionStatus::SUSPENSION_PENDING
            || $days < 0) {
            return 'grace';
        }
        if ($days <= 3) {
            return 'critical';
        }
        return 'warning';
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function mapItems(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $companyId = (int) ($row['company_id'] ?? 0);
            $name = trim((string) ($row['company_name'] ?? ''));
            $days = (int) ($row['days_remaining'] ?? 0);
            $status = strtoupper((string) ($row['current_status'] ?? ''));
            $out[] = [
                'company_id' => $companyId,
                'company_name' => $name !== '' ? $name : ('#' . $companyId),
                'days_remaining' => $days,
                'status' => $status,
                'kind' => self::classify($row, $days, $status),
                'expiry' => substr((string) ($row['subscription_end'] ?? ''), 0, 10),
            ];
        }
        return $out;
    }

    private function messageFor(string $kind, string $companyName, int $days, int $companyId): string
    {
        $t = static function (string $key, array $vars, string $fallback) use ($companyName, $days, $companyId): string {
            if (function_exists('__')) {
                $out = (string) __($key, $vars);
                if ($out !== '' && $out !== $key) {
                    return $out;
                }
            }
            return strtr($fallback, [
                ':company' => $companyName,
                ':id' => (string) $companyId,
                ':days' => (string) max(0, $days),
            ]);
        };

        return match ($kind) {
            'suspended' => $t(
                'subscription_admin_alert_suspended',
                ['company' => $companyName, 'id' => $companyId],
                'Company :company (#:id) is suspended — follow up required.'
            ),
            'grace' => $t(
                'subscription_admin_alert_grace',
                ['company' => $companyName, 'id' => $companyId],
                'Company :company (#:id) is in grace / pending suspension — follow up required.'
            ),
            'critical' => $t(
                'subscription_admin_alert_critical',
                ['company' => $companyName, 'id' => $companyId, 'days' => max(0, $days)],
                'Company :company (#:id) expires in :days day(s) — critical.'
            ),
            default => $t(
                'subscription_admin_alert_warning',
                ['company' => $companyName, 'id' => $companyId, 'days' => max(0, $days)],
                'Company :company (#:id) expires in :days day(s) — renewal window.'
            ),
        };
    }

    private function alreadyNotifiedToday(int $userId, int $companyId, string $todayYmd): bool
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare(
                'SELECT id FROM rateb_notifications
                 WHERE user_id = :uid
                   AND trigger_type = :trigger
                   AND entity_type = \'company\'
                   AND entity_id = :cid
                   AND DATE(created_at) = :today
                 LIMIT 1'
            );
            $stmt->execute([
                'uid' => $userId,
                'trigger' => self::TRIGGER,
                'cid' => $companyId,
                'today' => $todayYmd,
            ]);
            return (int) $stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** @return list<int> */
    private function superAdminUserIds(): array
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query(
                "SELECT id FROM rateb_users WHERE is_super_admin = 1 AND status = 'active' ORDER BY id ASC LIMIT 50"
            );
            if ($stmt === false) {
                return [];
            }
            $ids = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
            return $ids;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
