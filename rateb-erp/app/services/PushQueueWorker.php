<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Processes rateb_mobile_push_outbox — pending → processing → sent|failed.
 * Retry-safe; ignores revoked devices; never logs full push tokens.
 */
final class PushQueueWorker
{
    public const MAX_ATTEMPTS = 5;

    private MobilePushOutboxStoreInterface $store;
    private MobilePushDeliveryService $delivery;

    public function __construct(
        ?MobilePushOutboxStoreInterface $store = null,
        ?MobilePushDeliveryService $delivery = null
    ) {
        $this->store = $store ?? new MobilePushOutboxDbStore();
        $this->delivery = $delivery ?? new MobilePushDeliveryService();
    }

    public function processPending(int $limit = 50): int
    {
        $jobs = $this->store->claimPending($limit);
        $processed = 0;
        foreach ($jobs as $job) {
            $this->processOne($job);
            $processed++;
        }

        return $processed;
    }

    /**
     * @param array<string,mixed> $job
     */
    public function processOne(array $job): void
    {
        $id = (int) ($job['id'] ?? 0);
        if ($id < 1) {
            return;
        }

        $attempts = (int) ($job['attempts'] ?? 1);
        $result = $this->delivery->deliverJob($job);

        if ($result['ok']) {
            $this->store->update($id, [
                'status' => MobilePushOutboxService::STATUS_SENT,
                'sent_at' => date('Y-m-d H:i:s'),
                'last_error' => $result['error'] !== '' && $result['sent'] === 0
                    ? mb_substr((string) $result['error'], 0, 512)
                    : null,
            ]);
            return;
        }

        $err = mb_substr((string) ($result['error'] ?? 'push_failed'), 0, 512);
        $err = $this->scrubTokenLeak($err);

        if ($attempts >= self::MAX_ATTEMPTS) {
            $this->store->update($id, [
                'status' => MobilePushOutboxService::STATUS_FAILED,
                'last_error' => $err,
            ]);
            return;
        }

        // Retry-safe: return to pending for another claim cycle.
        $this->store->update($id, [
            'status' => MobilePushOutboxService::STATUS_PENDING,
            'last_error' => $err,
        ]);
    }

    private function scrubTokenLeak(string $message): string
    {
        // Strip long hex/base64-looking runs that may be tokens.
        $scrubbed = preg_replace('/[A-Za-z0-9_\-:]{32,}/', '***', $message);

        return is_string($scrubbed) ? $scrubbed : 'push_failed';
    }
}
