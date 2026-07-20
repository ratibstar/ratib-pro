<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Internal mobile device helpers — push targeting + token/revoke handling.
 *
 * ERP-owned. Shared by ESS / Manager / future client_app values.
 * Never stores passwords, JWTs, or session secrets.
 * Does not send push (I.2+). Does not use rateb_offline_devices.
 */
final class MobileDeviceService
{
    public const PUSH_PROVIDERS = ['none', 'fcm', 'apns'];

    private MobileDeviceStoreInterface $store;

    public function __construct(?MobileDeviceStoreInterface $store = null)
    {
        $this->store = $store ?? new MobileDeviceDbStore();
    }

    /**
     * Active devices with delivery handles for fan-out (future push worker).
     *
     * @return list<array<string,mixed>>
     */
    public function findActivePushDevices(int $companyId, int $userId, string $clientApp): array
    {
        if ($companyId < 1 || $userId < 1) {
            return [];
        }
        $app = $this->normalizeClientApp($clientApp);
        if ($app === null) {
            return [];
        }

        return $this->store->listActiveWithPush($companyId, $userId, $app);
    }

    /**
     * Upsert push delivery handle for authenticated user's device.
     * company_id / user_id from auth only — never from body.
     *
     * @param array<string,mixed> $input
     * @return array{status:int,body:array<string,mixed>}
     */
    public function updatePushToken(int $userId, int $companyId, array $input): array
    {
        if ($userId < 1 || $companyId < 1) {
            return $this->fail(401, 'unauthorized', 'Unauthorized');
        }

        // Ignore any client-supplied tenant/user identity.
        unset($input['user_id'], $input['company_id'], $input['userId'], $input['companyId']);

        $clientApp = $this->normalizeClientApp($input['client_app'] ?? null);
        $deviceId = $this->normalizeDeviceId($input['device_id'] ?? null);
        if ($clientApp === null || $deviceId === null) {
            return $this->fail(422, 'validation_error', 'Invalid client_app or device_id');
        }

        $token = $this->nullableToken($input['push_token'] ?? null);
        if ($token === null) {
            return $this->fail(422, 'validation_error', 'Invalid push_token');
        }

        $provider = $this->normalizeProvider($input['push_provider'] ?? null);
        if ($provider === null) {
            return $this->fail(422, 'validation_error', 'Invalid push_provider');
        }
        $locale = $this->nullableLocale($input['locale'] ?? null);

        $row = $this->store->findByIdentity($companyId, $clientApp, $deviceId);
        if ($row === null || (int) ($row['user_id'] ?? 0) !== $userId) {
            return $this->fail(404, 'not_found', 'Device not found');
        }
        if ((string) ($row['status'] ?? '') === MobileDeviceRegistryService::STATUS_REVOKED) {
            return $this->fail(403, 'device_revoked', 'Device has been revoked');
        }
        if ((int) ($row['company_id'] ?? 0) !== $companyId) {
            return $this->fail(403, 'forbidden', 'Tenant mismatch');
        }

        $patch = [
            'push_token' => $token,
            'push_provider' => $provider,
            'last_seen_at' => date('Y-m-d H:i:s'),
            'status' => MobileDeviceRegistryService::STATUS_ACTIVE,
        ];
        if ($locale !== null || array_key_exists('locale', $input)) {
            $patch['locale'] = $locale;
        }
        if (array_key_exists('app_version', $input)) {
            $patch['app_version'] = $this->nullableVersion($input['app_version']);
        }
        if (array_key_exists('platform', $input)) {
            $patch['platform'] = $this->normalizePlatform($input['platform']);
        }

        $this->store->update((int) $row['id'], $patch);
        $fresh = $this->store->findByIdForUser($companyId, $userId, (int) $row['id']);

        return $this->ok(['device' => $this->dto($fresh ?? $row)]);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function revoke(int $userId, int $companyId, int $devicePk): array
    {
        if ($userId < 1 || $companyId < 1) {
            return $this->fail(401, 'unauthorized', 'Unauthorized');
        }
        if ($devicePk < 1) {
            return $this->fail(422, 'validation_error', 'Invalid device id');
        }
        $row = $this->store->findByIdForUser($companyId, $userId, $devicePk);
        if ($row === null) {
            return $this->fail(404, 'not_found', 'Device not found');
        }

        $this->store->update($devicePk, [
            'status' => MobileDeviceRegistryService::STATUS_REVOKED,
            'push_token' => null,
            'push_provider' => 'none',
            'last_seen_at' => date('Y-m-d H:i:s'),
        ]);
        $fresh = $this->store->findByIdForUser($companyId, $userId, $devicePk);

        return $this->ok(['device' => $this->dto($fresh ?? $row)]);
    }

    /**
     * Build register/update fields for push columns without nulling an existing token.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed> Patch fragment (may be empty)
     */
    public function pushFieldsForRegister(array $input): array
    {
        $patch = [];
        if (!array_key_exists('push_token', $input)) {
            // Preserve existing token — never overwrite with NULL.
        } else {
            $tok = $this->nullableToken($input['push_token']);
            if ($tok !== null) {
                $patch['push_token'] = $tok;
            }
            // Empty / null push_token in body → preserve (do not clear).
        }
        if (array_key_exists('push_provider', $input)) {
            $p = $this->normalizeProvider($input['push_provider']);
            if ($p !== null) {
                $patch['push_provider'] = $p;
            }
        } elseif (isset($patch['push_token'])) {
            $patch['push_provider'] = $this->normalizeProvider($input['push_provider'] ?? 'fcm') ?? 'fcm';
        }
        if (array_key_exists('locale', $input)) {
            $patch['locale'] = $this->nullableLocale($input['locale']);
        }

        return $patch;
    }

    /** @return array<string,mixed> */
    public function dto(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'client_app' => (string) ($row['client_app'] ?? ''),
            'platform' => (string) ($row['platform'] ?? ''),
            'device_id' => (string) ($row['device_id'] ?? ''),
            'push_provider' => (string) ($row['push_provider'] ?? 'none'),
            'locale' => isset($row['locale']) && $row['locale'] !== '' ? (string) $row['locale'] : null,
            'app_version' => isset($row['app_version']) ? (string) $row['app_version'] : null,
            'status' => (string) ($row['status'] ?? ''),
            'last_seen_at' => isset($row['last_seen_at']) ? (string) $row['last_seen_at'] : null,
        ];
    }

    public function normalizeClientApp(mixed $raw): ?string
    {
        $v = strtolower(trim((string) ($raw ?? '')));

        return in_array($v, MobileDeviceRegistryService::CLIENT_APPS, true) ? $v : null;
    }

    public function normalizeDeviceId(mixed $raw): ?string
    {
        $v = trim((string) ($raw ?? ''));
        if ($v === '' || strlen($v) > 64) {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9._\-]+$/', $v)) {
            return null;
        }

        return $v;
    }

    public function normalizePlatform(mixed $raw): string
    {
        $v = strtolower(trim((string) ($raw ?? 'other')));

        return in_array($v, MobileDeviceRegistryService::PLATFORMS, true) ? $v : 'other';
    }

    public function normalizeProvider(mixed $raw): ?string
    {
        $v = strtolower(trim((string) ($raw ?? 'none')));
        if ($v === '') {
            $v = 'none';
        }

        return in_array($v, self::PUSH_PROVIDERS, true) ? $v : null;
    }

    public function nullableToken(mixed $raw): ?string
    {
        $v = trim((string) ($raw ?? ''));
        if ($v === '') {
            return null;
        }

        return mb_substr($v, 0, 512);
    }

    public function nullableLocale(mixed $raw): ?string
    {
        $v = trim((string) ($raw ?? ''));
        if ($v === '') {
            return null;
        }

        return mb_substr($v, 0, 16);
    }

    public function nullableVersion(mixed $raw): ?string
    {
        $v = trim((string) ($raw ?? ''));
        if ($v === '') {
            return null;
        }

        return mb_substr($v, 0, 64);
    }

    /** @param array<string,mixed> $data @return array{status:int,body:array<string,mixed>} */
    private function ok(array $data): array
    {
        return [
            'status' => 200,
            'body' => ['success' => true, 'data' => $data],
        ];
    }

    /** @return array{status:int,body:array<string,mixed>} */
    private function fail(int $status, string $code, string $message): array
    {
        return [
            'status' => $status,
            'body' => [
                'success' => false,
                'code' => $code,
                'message' => $message,
            ],
        ];
    }

}
