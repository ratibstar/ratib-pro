<?php

declare(strict_types=1);

/**
 * Phase I.2 Mobile Push Delivery Engine gates.
 *
 * Run: php tests/hr/run-ess-phase-i2-push-delivery-tests.php
 */
final class EssPhaseI2PushDeliveryTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testMigrationAndConfigPlaceholders();
        $this->testNotificationCreatesOutboxWhenEnabled();
        $this->testOutboxDisabledByDefault();
        $this->testTenantUserClientAppIsolation();
        $this->testRevokedDeviceExcluded();
        $this->testFailedPushRetry();
        $this->testIdempotentWorkerAndEnqueue();
        $this->testNoTokenLeakage();
        $this->testEmailSmsUnchanged();
        $this->testProvidersNotInNotificationService();

        return $this->results;
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
        echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . ($detail !== '' ? ' — ' . $detail : '') . PHP_EOL;
    }

    private function enableOutbox(): void
    {
        putenv('RATEB_MOBILE_PUSH_OUTBOX_ENABLED=1');
        $_ENV['RATEB_MOBILE_PUSH_OUTBOX_ENABLED'] = '1';
        putenv('RATEB_MOBILE_PUSH_CLIENT_APPS=ess,manager');
        $_ENV['RATEB_MOBILE_PUSH_CLIENT_APPS'] = 'ess,manager';
        \Rateb\App\Services\MobilePushConfig::resetFileCache();
    }

    private function disableOutbox(): void
    {
        putenv('RATEB_MOBILE_PUSH_OUTBOX_ENABLED=0');
        $_ENV['RATEB_MOBILE_PUSH_OUTBOX_ENABLED'] = '0';
        \Rateb\App\Services\MobilePushConfig::resetFileCache();
    }

    private function testMigrationAndConfigPlaceholders(): void
    {
        $mig = (string) file_get_contents(RATEB_ROOT . '/migrations/208_mobile_push_outbox.sql');
        $ex = (string) file_get_contents(RATEB_ROOT . '/config/mobile-push.example.php');
        $ok = str_contains($mig, 'rateb_mobile_push_outbox')
            && str_contains($mig, 'uq_push_outbox_delivery')
            && str_contains($mig, 'notification_id')
            && str_contains($ex, 'RATEB_MOBILE_PUSH_FCM_PROJECT_ID')
            && str_contains($ex, 'RATEB_MOBILE_PUSH_APNS_KEY_ID')
            && !str_contains($ex, 'BEGIN PRIVATE KEY');
        $this->record('Migration + config placeholders present (no secrets)', $ok);
    }

    private function testNotificationCreatesOutboxWhenEnabled(): void
    {
        $this->enableOutbox();
        $outbox = new ArrayMobilePushOutboxStore();
        $svc = new \Rateb\App\Services\MobilePushOutboxService($outbox);
        $ids = $svc->enqueueFromNotification(
            501,
            100,
            10,
            'Hello',
            'Body',
            ['type' => 'info']
        );
        $ok = count($ids) === 2
            && $outbox->countByNotification(501) === 2
            && isset($outbox->rows[$ids[0]])
            && ($outbox->rows[$ids[0]]['status'] ?? '') === 'pending'
            && ($outbox->rows[$ids[0]]['company_id'] ?? 0) === 100
            && ($outbox->rows[$ids[0]]['user_id'] ?? -1) === 10;
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/NotificationService.php');
        $ok = $ok && str_contains($src, 'enqueueMobilePush')
            && str_contains($src, 'MobilePushOutboxService');
        $this->record('Notification path creates push outbox when enabled', $ok);
        $this->disableOutbox();
    }

    private function testOutboxDisabledByDefault(): void
    {
        $this->disableOutbox();
        $outbox = new ArrayMobilePushOutboxStore();
        $svc = new \Rateb\App\Services\MobilePushOutboxService($outbox);
        $ids = $svc->enqueueFromNotification(502, 100, 10, 'T', 'B');
        $this->record('Outbox disabled by default (feature flag)', $ids === [] && $outbox->rows === []);
    }

    private function testTenantUserClientAppIsolation(): void
    {
        $devices = new ArrayMobileDeviceStore();
        $reg = new \Rateb\App\Services\MobileDeviceRegistryService($devices);
        $reg->register(10, 100, [
            'client_app' => 'ess',
            'device_id' => 'a',
            'platform' => 'android',
            'push_token' => 'tok-ess-100',
            'push_provider' => 'fcm',
        ]);
        $reg->register(10, 200, [
            'client_app' => 'ess',
            'device_id' => 'a',
            'platform' => 'android',
            'push_token' => 'tok-ess-200',
            'push_provider' => 'fcm',
        ]);
        $reg->register(11, 100, [
            'client_app' => 'ess',
            'device_id' => 'b',
            'platform' => 'android',
            'push_token' => 'tok-other-user',
            'push_provider' => 'fcm',
        ]);
        $reg->register(10, 100, [
            'client_app' => 'manager',
            'device_id' => 'c',
            'platform' => 'android',
            'push_token' => 'tok-mgr',
            'push_provider' => 'fcm',
        ]);

        $fcm = new RecordingFcmPushProvider();
        $delivery = new \Rateb\App\Services\MobilePushDeliveryService(
            new \Rateb\App\Services\MobileDeviceService($devices),
            $fcm
        );
        $rEss = $delivery->deliverJob([
            'company_id' => 100,
            'user_id' => 10,
            'client_app' => 'ess',
            'title' => 't',
            'body' => 'b',
        ]);
        $tokens = array_column($fcm->sent, 'token');
        $ok = ($rEss['sent'] ?? 0) === 1
            && $tokens === ['tok-ess-100'];
        $this->record('Tenant/user/client_app targeting isolation', $ok);
    }

    private function testRevokedDeviceExcluded(): void
    {
        $devices = new ArrayMobileDeviceStore();
        $reg = new \Rateb\App\Services\MobileDeviceRegistryService($devices);
        $r = $reg->register(10, 100, [
            'client_app' => 'ess',
            'device_id' => 'rev',
            'platform' => 'android',
            'push_token' => 'tok-revoked',
            'push_provider' => 'fcm',
        ]);
        $id = (int) ($r['body']['data']['device']['id'] ?? 0);
        $reg->revoke(10, 100, $id);
        $fcm = new RecordingFcmPushProvider();
        $delivery = new \Rateb\App\Services\MobilePushDeliveryService(
            new \Rateb\App\Services\MobileDeviceService($devices),
            $fcm
        );
        $res = $delivery->deliverJob([
            'company_id' => 100,
            'user_id' => 10,
            'client_app' => 'ess',
            'title' => 't',
            'body' => 'b',
        ]);
        $ok = ($res['ok'] ?? false) === true
            && ($res['sent'] ?? -1) === 0
            && $fcm->sent === [];
        $this->record('Revoked device excluded from delivery', $ok);
    }

    private function testFailedPushRetry(): void
    {
        $devices = new ArrayMobileDeviceStore();
        $reg = new \Rateb\App\Services\MobileDeviceRegistryService($devices);
        $reg->register(10, 100, [
            'client_app' => 'ess',
            'device_id' => 'retry',
            'platform' => 'android',
            'push_token' => 'tok-retry',
            'push_provider' => 'fcm',
        ]);
        $outbox = new ArrayMobilePushOutboxStore();
        $this->enableOutbox();
        (new \Rateb\App\Services\MobilePushOutboxService($outbox))->enqueueFromNotification(
            900,
            100,
            10,
            'Retry',
            'Body',
            [],
            ['ess']
        );
        $fcm = new RecordingFcmPushProvider();
        $fcm->failUntilAttempt = 1;
        $worker = new \Rateb\App\Services\PushQueueWorker(
            $outbox,
            new \Rateb\App\Services\MobilePushDeliveryService(
                new \Rateb\App\Services\MobileDeviceService($devices),
                $fcm
            )
        );
        $worker->processPending(10);
        $row = array_values($outbox->rows)[0];
        $okFirst = ($row['status'] ?? '') === 'pending' && (int) ($row['attempts'] ?? 0) === 1;
        $fcm->failUntilAttempt = 0;
        $worker->processPending(10);
        $row = array_values($outbox->rows)[0];
        $ok = $okFirst && ($row['status'] ?? '') === 'sent';
        $this->record('Failed push retries then succeeds', $ok);
        $this->disableOutbox();
    }

    private function testIdempotentWorkerAndEnqueue(): void
    {
        $this->enableOutbox();
        $outbox = new ArrayMobilePushOutboxStore();
        $svc = new \Rateb\App\Services\MobilePushOutboxService($outbox);
        $a = $svc->enqueueFromNotification(777, 100, 10, 'A', 'B', [], ['ess']);
        $b = $svc->enqueueFromNotification(777, 100, 10, 'A', 'B', [], ['ess']);
        $okEnqueue = count($a) === 1 && $b === [] && $outbox->countByNotification(777) === 1;

        $devices = new ArrayMobileDeviceStore();
        (new \Rateb\App\Services\MobileDeviceRegistryService($devices))->register(10, 100, [
            'client_app' => 'ess',
            'device_id' => 'idem',
            'platform' => 'android',
            'push_token' => 'tok-idem',
            'push_provider' => 'fcm',
        ]);
        $fcm = new RecordingFcmPushProvider();
        $worker = new \Rateb\App\Services\PushQueueWorker(
            $outbox,
            new \Rateb\App\Services\MobilePushDeliveryService(
                new \Rateb\App\Services\MobileDeviceService($devices),
                $fcm
            )
        );
        $n1 = $worker->processPending(10);
        $n2 = $worker->processPending(10);
        $row = array_values($outbox->rows)[0];
        $ok = $okEnqueue
            && $n1 === 1
            && $n2 === 0
            && ($row['status'] ?? '') === 'sent'
            && count($fcm->sent) === 1;
        $this->record('Idempotent enqueue + worker claim', $ok);
        $this->disableOutbox();
    }

    private function testNoTokenLeakage(): void
    {
        $secret = 'super-secret-push-token-value-abcdefghijklmnopqrstuvwxyz';
        $redacted = \Rateb\App\Services\MobilePushConfig::redactToken($secret);
        $devices = new ArrayMobileDeviceStore();
        (new \Rateb\App\Services\MobileDeviceRegistryService($devices))->register(10, 100, [
            'client_app' => 'ess',
            'device_id' => 'leak',
            'platform' => 'android',
            'push_token' => $secret,
            'push_provider' => 'fcm',
        ]);
        $outbox = new ArrayMobilePushOutboxStore();
        $this->enableOutbox();
        (new \Rateb\App\Services\MobilePushOutboxService($outbox))->enqueueFromNotification(
            801,
            100,
            10,
            'T',
            'B',
            [],
            ['ess']
        );
        $fcm = new RecordingFcmPushProvider();
        $fcm->failNext = true;
        $worker = new \Rateb\App\Services\PushQueueWorker(
            $outbox,
            new \Rateb\App\Services\MobilePushDeliveryService(
                new \Rateb\App\Services\MobileDeviceService($devices),
                $fcm
            )
        );
        $worker->processPending(5);
        $row = array_values($outbox->rows)[0];
        $err = (string) ($row['last_error'] ?? '');
        $dump = json_encode($outbox->rows);
        $ok = !str_contains($redacted, $secret)
            && !str_contains($err, $secret)
            && !str_contains((string) $dump, $secret)
            && str_contains($workerSrc = (string) file_get_contents(RATEB_ROOT . '/app/services/PushQueueWorker.php'), 'scrubTokenLeak');
        $this->record('No full push token leakage in errors/outbox', $ok);
        $this->disableOutbox();
    }

    private function testEmailSmsUnchanged(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/NotificationService.php');
        $queue = (string) file_get_contents(RATEB_ROOT . '/app/services/QueueWorkerService.php');
        $ok = str_contains($src, 'function queueEmail')
            && str_contains($src, 'function sendSms')
            && str_contains($src, "'ch' => 'email'")
            && str_contains($src, "'ch' => 'sms'")
            && str_contains($queue, "channel === 'email'")
            && str_contains($queue, "channel === 'sms'")
            && !str_contains($queue, 'PushQueueWorker')
            && !str_contains($src, 'FcmPushProvider');
        $this->record('Existing email/SMS paths unchanged', $ok);
    }

    private function testProvidersNotInNotificationService(): void
    {
        $src = (string) file_get_contents(RATEB_ROOT . '/app/services/NotificationService.php');
        $delivery = (string) file_get_contents(RATEB_ROOT . '/app/services/MobilePushDeliveryService.php');
        $ok = !str_contains($src, 'FcmPush')
            && !str_contains($src, 'ApnsPush')
            && !str_contains($src, 'PushQueueWorker')
            && str_contains($delivery, 'FcmPushProviderInterface')
            && str_contains($delivery, 'ApnsPushProviderInterface')
            && is_file(RATEB_ROOT . '/app/services/Push/FcmPushProviderInterface.php')
            && is_file(RATEB_ROOT . '/app/services/Push/ApnsPushProviderInterface.php');
        $this->record('Providers abstracted outside NotificationService', $ok);
    }
}
