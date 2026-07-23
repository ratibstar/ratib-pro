<?php
declare(strict_types=1);

namespace Rateb\App\Subscription;

/**
 * Future cron / CLI entry for subscription notification generation.
 *
 * Safe to run multiple times per day: dedupe in history table + evaluate().
 * Does not install cron, send messages, or change access.
 */
final class SubscriptionNotificationJob
{
    private NotificationScheduler $scheduler;

    public function __construct(?NotificationScheduler $scheduler = null)
    {
        $this->scheduler = $scheduler ?? new NotificationScheduler();
    }

    /**
     * @param array{
     *   today?: string,
     *   batch_size?: int,
     *   dry_run?: bool,
     *   max_batches?: int|null
     * } $options
     * @return array<string, mixed>
     */
    public function execute(array $options = []): array
    {
        return $this->scheduler->run($options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function run(array $options = []): array
    {
        return (new self())->execute($options);
    }
}
