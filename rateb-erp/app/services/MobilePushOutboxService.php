<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Enqueue push delivery jobs from in-app notifications (feature-flagged).
 * Does not send; does not alter email/SMS.
 */
final class MobilePushOutboxService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    private MobilePushOutboxStoreInterface $store;

    public function __construct(?MobilePushOutboxStoreInterface $store = null)
    {
        $this->store = $store ?? new MobilePushOutboxDbStore();
    }

    /**
     * Create outbox rows for a notification (idempotent per notification+client_app+user).
     *
     * @param array<string,mixed> $data Extra payload (entity ids, type, etc.)
     * @return list<int> inserted outbox ids (empty if disabled or duplicates)
     */
    public function enqueueFromNotification(
        int $notificationId,
        int $companyId,
        int $userId,
        string $title,
        string $body,
        array $data = [],
        ?array $clientApps = null
    ): array {
        if (!MobilePushConfig::outboxEnabled()) {
            return [];
        }
        if ($notificationId < 1 || $companyId < 1) {
            return [];
        }

        $apps = $clientApps ?? MobilePushConfig::targetClientApps();
        $ids = [];
        $json = $data === [] ? null : json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = null;
        }

        foreach ($apps as $app) {
            if (!in_array($app, MobileDeviceRegistryService::CLIENT_APPS, true)) {
                continue;
            }
            $id = $this->store->insertIgnoreDuplicate([
                'company_id' => $companyId,
                'user_id' => max(0, $userId),
                'client_app' => $app,
                'notification_id' => $notificationId,
                'title' => mb_substr($title, 0, 255),
                'body' => $body,
                'data_json' => $json,
                'status' => self::STATUS_PENDING,
            ]);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function store(): MobilePushOutboxStoreInterface
    {
        return $this->store;
    }
}
