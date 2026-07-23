<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

use Rateb\App\Core\Database;

/**
 * Mirror subscription engine lifecycle state into a linked agency ERP DB.
 * Platform edits alone do not affect test.rateb.sa (separate database).
 */
final class SubscriptionAgencyMirror
{
    /**
     * Upsert engine row on the linked agency DB for this platform company.
     *
     * @param array{
     *   subscription_start?:string,
     *   subscription_end:string,
     *   current_status?:string,
     *   grace_period_days?:int,
     *   suspended_at?:string|null
     * } $state
     */
    public static function mirrorToLinkedAgency(int $platformCompanyId, array $state): bool
    {
        if ($platformCompanyId < 1) {
            return false;
        }
        $end = substr(trim((string) ($state['subscription_end'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            return false;
        }

        if (!class_exists(\Rateb\App\Services\AgencyErpMigrationService::class)) {
            return false;
        }

        try {
            $agencySvc = new \Rateb\App\Services\AgencyErpMigrationService();
            $agency = $agencySvc->findLinkedAgencyForPlatformCompany($platformCompanyId);
            if ($agency === null) {
                return false;
            }
            $cfg = $agencySvc->agencyDatabaseConfig($agency);
            if (trim((string) ($cfg['db'] ?? '')) === '') {
                return false;
            }

            Database::useConnectionOverride([
                'db' => (string) $cfg['db'],
                'host' => (string) ($cfg['host'] ?? 'localhost'),
                'port' => (int) ($cfg['port'] ?? 3306),
                'user' => (string) ($cfg['user'] ?? ''),
                'pass' => (string) ($cfg['pass'] ?? ''),
            ]);
            try {
                $pdo = Database::connection();
                // Dedicated agency DBs typically have one tenant company (not platform id).
                $agencyCompanyId = (int) $pdo->query(
                    'SELECT id FROM rateb_companies ORDER BY id ASC LIMIT 1'
                )->fetchColumn();
                if ($agencyCompanyId < 1) {
                    return false;
                }

                $start = substr(trim((string) ($state['subscription_start'] ?? '')), 0, 10);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
                    $start = $end;
                }
                $status = strtoupper(trim((string) ($state['current_status'] ?? SubscriptionStatus::ACTIVE)));
                if (!SubscriptionStatus::isKnown($status)) {
                    $status = SubscriptionStatus::ACTIVE;
                }
                $graceDays = max(0, min(90, (int) ($state['grace_period_days'] ?? 7)));
                $suspendedAt = $state['suspended_at'] ?? null;
                if ($status === SubscriptionStatus::SUSPENDED && ($suspendedAt === null || $suspendedAt === '')) {
                    $suspendedAt = gmdate('Y-m-d H:i:s');
                }
                if ($status !== SubscriptionStatus::SUSPENDED) {
                    $suspendedAt = null;
                }

                $exists = $pdo->prepare(
                    'SELECT id FROM rateb_subscription_engine WHERE company_id = :cid LIMIT 1'
                );
                $exists->execute(['cid' => $agencyCompanyId]);
                $engineId = (int) $exists->fetchColumn();

                if ($engineId > 0) {
                    try {
                        $upd = $pdo->prepare(
                            'UPDATE rateb_subscription_engine
                             SET subscription_start = :start,
                                 subscription_end = :end,
                                 current_status = :status,
                                 grace_period_days = :grace,
                                 suspended_at = :suspended_at,
                                 grace_started_at = NULL,
                                 grace_end_at = NULL,
                                 updated_at = NOW()
                             WHERE company_id = :cid'
                        );
                        $upd->execute([
                            'start' => $start,
                            'end' => $end,
                            'status' => $status,
                            'grace' => $graceDays,
                            'suspended_at' => $suspendedAt,
                            'cid' => $agencyCompanyId,
                        ]);
                    } catch (\Throwable $colEx) {
                        $upd = $pdo->prepare(
                            'UPDATE rateb_subscription_engine
                             SET subscription_start = :start,
                                 subscription_end = :end,
                                 current_status = :status,
                                 grace_period_days = :grace,
                                 suspended_at = :suspended_at,
                                 updated_at = NOW()
                             WHERE company_id = :cid'
                        );
                        $upd->execute([
                            'start' => $start,
                            'end' => $end,
                            'status' => $status,
                            'grace' => $graceDays,
                            'suspended_at' => $suspendedAt,
                            'cid' => $agencyCompanyId,
                        ]);
                    }
                } else {
                    try {
                        $ins = $pdo->prepare(
                            'INSERT INTO rateb_subscription_engine
                                (company_id, subscription_start, subscription_end, grace_period_days,
                                 current_status, suspended_at, created_at)
                             VALUES
                                (:cid, :start, :end, :grace, :status, :suspended_at, NOW())'
                        );
                        $ins->execute([
                            'cid' => $agencyCompanyId,
                            'start' => $start,
                            'end' => $end,
                            'grace' => $graceDays,
                            'status' => $status,
                            'suspended_at' => $suspendedAt,
                        ]);
                    } catch (\Throwable $e) {
                        error_log('RATEB SubscriptionAgencyMirror insert: ' . $e->getMessage());
                        return false;
                    }
                }

                error_log(sprintf(
                    'RATEB subscription mirrored to agency db=%s agency_company_id=%d end=%s status=%s',
                    (string) $cfg['db'],
                    $agencyCompanyId,
                    $end,
                    $status
                ));
                return true;
            } finally {
                Database::clearConnectionOverride();
            }
        } catch (\Throwable $e) {
            error_log('RATEB SubscriptionAgencyMirror: ' . $e->getMessage());
            return false;
        }
    }
}
