<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Periodic execution layer: ask which subscription notifications should be
 * generated today, then write history records only.
 *
 * Loads subscription engine rows in batches — no HR, users, permissions,
 * or financial modules.
 */
final class NotificationScheduler
{
    public const DEFAULT_BATCH_SIZE = 100;

    private SubscriptionEngineStore $repository;
    private SubscriptionEngine $subscriptionEngine;
    private NotificationEngine $notificationEngine;
    private NotificationGenerator $generator;

    public function __construct(
        ?SubscriptionEngineStore $repository = null,
        ?SubscriptionEngine $subscriptionEngine = null,
        ?NotificationEngine $notificationEngine = null,
        ?NotificationGenerator $generator = null
    ) {
        $this->repository = $repository ?? new SubscriptionRepository();
        $this->subscriptionEngine = $subscriptionEngine ?? new SubscriptionEngine($this->repository);
        $this->notificationEngine = $notificationEngine ?? new NotificationEngine();
        $this->generator = $generator ?? new NotificationGenerator();
    }

    /**
     * @param array{
     *   today?: string,
     *   batch_size?: int,
     *   dry_run?: bool,
     *   max_batches?: int|null
     * } $options
     * @return array{
     *   today: string,
     *   dry_run: bool,
     *   scanned: int,
     *   eligible: int,
     *   inserted: int,
     *   skipped: int,
     *   declined: int,
     *   batches: int,
     *   errors: list<string>,
     *   elapsed_seconds: float
     * }
     */
    public function run(array $options = []): array
    {
        $started = microtime(true);
        $today = isset($options['today']) && is_string($options['today']) && $options['today'] !== ''
            ? substr($options['today'], 0, 10)
            : gmdate('Y-m-d');
        $batchSize = isset($options['batch_size']) ? (int) $options['batch_size'] : self::DEFAULT_BATCH_SIZE;
        $batchSize = max(1, min(500, $batchSize));
        $dryRun = !empty($options['dry_run']);
        $maxBatches = array_key_exists('max_batches', $options) && $options['max_batches'] !== null
            ? max(1, (int) $options['max_batches'])
            : null;

        $scanned = 0;
        $eligible = 0;
        $inserted = 0;
        $skipped = 0;
        $declined = 0;
        $batches = 0;
        $errors = [];
        $afterId = 0;

        while (true) {
            if ($maxBatches !== null && $batches >= $maxBatches) {
                break;
            }

            try {
                $rows = $this->repository->listEngineRowsAfterId($afterId, $batchSize);
            } catch (\Throwable $e) {
                $msg = 'batch_load_failed after_id=' . $afterId . ': ' . $e->getMessage();
                $errors[] = $msg;
                error_log('RATEB NotificationScheduler: ' . $msg);
                break;
            }

            if ($rows === []) {
                break;
            }

            $batches++;
            foreach ($rows as $row) {
                $rowId = (int) ($row['id'] ?? 0);
                if ($rowId > $afterId) {
                    $afterId = $rowId;
                }

                $companyId = (int) ($row['company_id'] ?? 0);
                $scanned++;

                try {
                    $context = $this->subscriptionEngine->contextFromRow($row, $today);
                    $decision = $this->notificationEngine->evaluate($context, $today);

                    if (!$decision->shouldGenerate()) {
                        $declined++;
                        continue;
                    }

                    $eligible++;
                    if ($dryRun) {
                        $skipped++;
                        continue;
                    }

                    $id = $this->generator->generate($decision);
                    if ($id > 0) {
                        $inserted++;
                        error_log(sprintf(
                            'RATEB NotificationScheduler: generated id=%d company=%d type=%s trigger=%s',
                            $id,
                            $companyId,
                            (string) $decision->notificationType(),
                            (string) $decision->triggerDay()
                        ));
                    } else {
                        // Duplicate or write failure — safe retry outcome.
                        $skipped++;
                    }
                } catch (\Throwable $e) {
                    $msg = 'company_id=' . $companyId . ': ' . $e->getMessage();
                    $errors[] = $msg;
                    error_log('RATEB NotificationScheduler tenant error: ' . $msg);
                }
            }

            if (count($rows) < $batchSize) {
                break;
            }
        }

        $stats = [
            'today' => $today,
            'dry_run' => $dryRun,
            'scanned' => $scanned,
            'eligible' => $eligible,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'declined' => $declined,
            'batches' => $batches,
            'errors' => $errors,
            'elapsed_seconds' => round(microtime(true) - $started, 4),
        ];

        // Platform ops: fan-out in-app alerts to super-admins for any tenant in window.
        if (!$dryRun) {
            try {
                if (class_exists(\Rateb\App\Subscription\Admin\SubscriptionAdminNotifier::class)) {
                    $fan = (new \Rateb\App\Subscription\Admin\SubscriptionAdminNotifier())
                        ->fanOutToPlatformAdmins($today);
                    $stats['admin_fanout_companies'] = (int) ($fan['companies'] ?? 0);
                    $stats['admin_fanout_notifications'] = (int) ($fan['notifications'] ?? 0);
                }
            } catch (\Throwable $e) {
                $stats['admin_fanout_error'] = $e->getMessage();
                error_log('RATEB NotificationScheduler admin fan-out: ' . $e->getMessage());
            }
        }

        error_log('RATEB NotificationScheduler finished: ' . json_encode($stats));

        return $stats;
    }
}
