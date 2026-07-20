<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Services\Push\ApnsPushProviderInterface;
use Rateb\App\Services\Push\FcmPushProviderInterface;
use Rateb\App\Services\Push\PushSendResult;
use Rateb\App\Services\Push\StubApnsPushProvider;
use Rateb\App\Services\Push\StubFcmPushProvider;

/**
 * Resolves active devices and sends via provider abstraction.
 * Not invoked from NotificationService directly — used by PushQueueWorker.
 */
final class MobilePushDeliveryService
{
    private MobileDeviceService $devices;
    private FcmPushProviderInterface $fcm;
    private ApnsPushProviderInterface $apns;

    public function __construct(
        ?MobileDeviceService $devices = null,
        ?FcmPushProviderInterface $fcm = null,
        ?ApnsPushProviderInterface $apns = null
    ) {
        $this->devices = $devices ?? new MobileDeviceService();
        $this->fcm = $fcm ?? new StubFcmPushProvider(MobilePushConfig::fcmConfigured());
        $this->apns = $apns ?? new StubApnsPushProvider(MobilePushConfig::apnsConfigured());
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function resolveDevices(int $companyId, int $userId, string $clientApp): array
    {
        if ($companyId < 1) {
            return [];
        }
        if ($userId > 0) {
            return $this->devices->findActivePushDevices($companyId, $userId, $clientApp);
        }

        return $this->devices->findActivePushDevicesForCompany($companyId, $clientApp);
    }

    /**
     * Deliver one outbox job to all matching active (non-revoked) devices.
     *
     * @param array<string,mixed> $job
     * @return array{ok:bool,sent:int,failed:int,skipped:int,error:string}
     */
    public function deliverJob(array $job): array
    {
        $companyId = (int) ($job['company_id'] ?? 0);
        $userId = (int) ($job['user_id'] ?? 0);
        $clientApp = (string) ($job['client_app'] ?? '');
        $title = (string) ($job['title'] ?? '');
        $body = (string) ($job['body'] ?? '');
        $data = [];
        if (!empty($job['data_json']) && is_string($job['data_json'])) {
            $decoded = json_decode($job['data_json'], true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $devices = $this->resolveDevices($companyId, $userId, $clientApp);
        if ($devices === []) {
            return [
                'ok' => true,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'error' => 'no_active_devices',
            ];
        }

        $payload = [
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'notification_id' => $job['notification_id'] ?? null,
        ];

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $lastError = '';

        foreach ($devices as $device) {
            if ((string) ($device['status'] ?? '') === MobileDeviceRegistryService::STATUS_REVOKED) {
                $skipped++;
                continue;
            }
            $token = trim((string) ($device['push_token'] ?? ''));
            if ($token === '') {
                $skipped++;
                continue;
            }

            $provider = strtolower((string) ($device['push_provider'] ?? 'fcm'));
            $result = $this->sendWithProvider($provider, $token, $payload);
            if ($result->ok) {
                $sent++;
                continue;
            }
            if ($result->invalidToken) {
                $this->devices->clearInvalidPushToken($device);
                $skipped++;
                $lastError = $result->code;
                continue;
            }
            $failed++;
            $lastError = $result->code !== '' ? $result->code : $result->message;
            // Never include full token in error text.
            $lastError = str_replace($token, MobilePushConfig::redactToken($token), $lastError);
        }

        $ok = $failed === 0;
        return [
            'ok' => $ok,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'error' => $lastError,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function sendWithProvider(string $provider, string $token, array $payload): PushSendResult
    {
        if ($provider === 'apns') {
            return $this->apns->send($token, $payload);
        }
        if ($provider === 'none') {
            return PushSendResult::failure('no_provider', 'Device has no push provider');
        }

        return $this->fcm->send($token, $payload);
    }
}
