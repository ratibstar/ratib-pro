<?php
declare(strict_types=1);

namespace Rateb\App\Subscription\Admin;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\NotificationService;
use Rateb\App\Subscription\SubscriptionStatus;

/**
 * Fan-out subscription lifecycle alerts to platform super-admins
 * so ops can follow any tenant that enters warning / grace / suspension.
 */
final class SubscriptionAdminNotifier
{
    public const TRIGGER = 'subscription_engine_alert';
    private const SESSION_FANOUT_KEY_PREFIX = 'rateb_sub_admin_fanout_';

    /**
     * Companies currently in an alert window (≤14 days, grace, suspended).
     * Does not include ancient past expiry rows that are still ACTIVE.
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
                       OR (
                            e.subscription_end >= :today_from
                            AND e.subscription_end <= DATE_ADD(:today2, INTERVAL ' . $withinDays . ' DAY)
                          )
                    ORDER BY e.subscription_end ASC, e.company_id ASC
                    LIMIT 200';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'today' => $todayYmd,
                'today_from' => $todayYmd,
                'today2' => $todayYmd,
            ]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            error_log('RATEB SubscriptionAdminNotifier::listAlertWindowTenants: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Ops panel data only — no notification writes.
     *
     * @return array{companies:int,notifications:int,items:list<array<string,mixed>>}
     */
    public function alertWindowSummary(?string $todayYmd = null): array
    {
        $today = $todayYmd ?? gmdate('Y-m-d');
        $rows = $this->listAlertWindowTenants($today, SubscriptionAdminViewModel::EXPIRING_SOON_DAYS);
        return [
            'companies' => count($rows),
            'notifications' => 0,
            'items' => $this->mapItems($rows),
        ];
    }

    /**
     * Create in-app notifications for super-admins (once per company/day).
     * When $respectSessionThrottle is true, skips the write path if already
     * attempted this browser session today (panel list still available via summary).
     *
     * @return array{companies:int,notifications:int,items:list<array<string,mixed>>,skipped:bool}
     */
    public function fanOutToPlatformAdmins(?string $todayYmd = null, bool $respectSessionThrottle = true): array
    {
        $today = $todayYmd ?? gmdate('Y-m-d');
        $rows = $this->listAlertWindowTenants($today, SubscriptionAdminViewModel::EXPIRING_SOON_DAYS);
        $items = $this->mapItems($rows);
        $base = [
            'companies' => count($rows),
            'notifications' => 0,
            'items' => $items,
            'skipped' => false,
        ];

        if ($rows === []) {
            return $base;
        }

        $sessionKey = self::SESSION_FANOUT_KEY_PREFIX . $today;
        if ($respectSessionThrottle && class_exists(SessionManager::class) && SessionManager::get($sessionKey)) {
            $base['skipped'] = true;
            return $base;
        }

        if (!class_exists(NotificationService::class)) {
            return $base;
        }

        $adminIds = $this->superAdminUserIds();
        if ($adminIds === []) {
            if ($respectSessionThrottle && class_exists(SessionManager::class)) {
                SessionManager::set($sessionKey, 1);
            }
            return $base;
        }

        $companyIds = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['company_id'] ?? 0);
            if ($cid > 0) {
                $companyIds[] = $cid;
            }
        }
        $already = $this->alreadyNotifiedSet($adminIds, $companyIds, $today);

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
                $pairKey = $uid . ':' . $companyId;
                if (isset($already[$pairKey])) {
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
                    $already[$pairKey] = true;
                    $notifCount++;
                } catch (\Throwable $e) {
                    error_log('RATEB SubscriptionAdminNotifier notify: ' . $e->getMessage());
                }
            }
        }

        if ($respectSessionThrottle && class_exists(SessionManager::class)) {
            SessionManager::set($sessionKey, 1);
        }

        $base['notifications'] = $notifCount;
        return $base;
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

    /**
     * One query for all admin×company pairs already notified today.
     *
     * @param list<int> $adminIds
     * @param list<int> $companyIds
     * @return array<string, true> keys "userId:companyId"
     */
    private function alreadyNotifiedSet(array $adminIds, array $companyIds, string $todayYmd): array
    {
        $out = [];
        if ($adminIds === [] || $companyIds === []) {
            return $out;
        }
        try {
            $pdo = Database::connection();
            $adminPlace = implode(',', array_fill(0, count($adminIds), '?'));
            $coPlace = implode(',', array_fill(0, count($companyIds), '?'));
            $dayStart = $todayYmd . ' 00:00:00';
            $dayEnd = gmdate('Y-m-d', (strtotime($todayYmd . ' +1 day') ?: time())) . ' 00:00:00';
            $sql = 'SELECT user_id, entity_id
                    FROM rateb_notifications
                    WHERE trigger_type = ?
                      AND entity_type = \'company\'
                      AND user_id IN (' . $adminPlace . ')
                      AND entity_id IN (' . $coPlace . ')
                      AND created_at >= ?
                      AND created_at < ?
                    LIMIT 5000';
            $params = array_merge([self::TRIGGER], $adminIds, $companyIds, [$dayStart, $dayEnd]);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $uid = (int) ($row['user_id'] ?? 0);
                $cid = (int) ($row['entity_id'] ?? 0);
                if ($uid > 0 && $cid > 0) {
                    $out[$uid . ':' . $cid] = true;
                }
            }
        } catch (\Throwable $e) {
            // Fall through — may create duplicates same day; safer than blocking page.
        }
        return $out;
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
