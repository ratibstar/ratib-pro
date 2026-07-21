<?php
declare(strict_types=1);

namespace Rateb\App\Services;

/**
 * Unified Mobile Device Registry — ERP-owned, shared by all mobile clients.
 * Never stores passwords or authentication secrets.
 * Push delivery handles only — no FCM/APNs send in this service (Phase I.2+).
 */
final class MobileDeviceRegistryService
{
    public const CLIENT_APPS = [
        'ess',
        'manager',
        'workforce',
        'supervisor',
        'ceo',
    ];

    public const PLATFORMS = ['android', 'ios', 'other'];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_REVOKED = 'revoked';

    private MobileDeviceService $devices;
    private MobileDeviceStoreInterface $store;

    public function __construct(
        ?MobileDeviceStoreInterface $store = null,
        ?MobileDeviceService $devices = null
    ) {
        $this->store = $store ?? new MobileDeviceDbStore();
        $this->devices = $devices ?? new MobileDeviceService($this->store);
    }

    /**
     * Upsert device for authenticated user/company.
     *
     * @param array<string, mixed> $input
     * @return array{status:int,body:array<string,mixed>}
     */
    public function register(int $userId, int $companyId, array $input): array
    {
        if ($userId < 1 || $companyId < 1) {
            return $this->fail(401, 'unauthorized', 'Unauthorized');
        }

        unset($input['user_id'], $input['company_id'], $input['userId'], $input['companyId']);

        $clientApp = $this->devices->normalizeClientApp($input['client_app'] ?? null);
        if ($clientApp === null) {
            return $this->fail(422, 'validation_error', 'Invalid client_app');
        }
        $deviceId = $this->devices->normalizeDeviceId($input['device_id'] ?? null);
        if ($deviceId === null) {
            return $this->fail(422, 'validation_error', 'Invalid device_id');
        }
        $platform = $this->devices->normalizePlatform($input['platform'] ?? null);
        $appVersion = $this->devices->nullableVersion($input['app_version'] ?? null);
        $pushPatch = $this->devices->pushFieldsForRegister($input);

        try {
            MobileDeviceSchemaBootstrap::ensure();

            $existing = $this->store->findByIdentity($companyId, $clientApp, $deviceId);
            if ($existing !== null) {
                $ownerId = (int) ($existing['user_id'] ?? 0);
                if ($ownerId !== $userId) {
                    return $this->fail(403, 'forbidden', 'Device belongs to another user');
                }
                $patch = array_merge([
                    'platform' => $platform,
                    'app_version' => $appVersion,
                    'last_seen_at' => date('Y-m-d H:i:s'),
                    'status' => self::STATUS_ACTIVE,
                ], $pushPatch);
                // Intentionally omit push_token when absent from request — preserve existing.
                $this->store->update((int) $existing['id'], $patch);
                $row = $this->store->findByIdForUser($companyId, $userId, (int) $existing['id']);
            } else {
                $create = array_merge([
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'client_app' => $clientApp,
                    'platform' => $platform,
                    'device_id' => $deviceId,
                    'push_token' => $pushPatch['push_token'] ?? null,
                    'push_provider' => $pushPatch['push_provider'] ?? 'none',
                    'locale' => $pushPatch['locale'] ?? null,
                    'app_version' => $appVersion,
                    'last_seen_at' => date('Y-m-d H:i:s'),
                    'status' => self::STATUS_ACTIVE,
                ], $pushPatch);
                $id = $this->store->insert($create);
                $row = $this->store->findByIdForUser($companyId, $userId, (int) $id);
            }

            if ($row === null) {
                return $this->fail(500, 'register_failed', 'Device register failed');
            }

            return $this->ok(['device' => $this->devices->dto($row)]);
        } catch (\Throwable $e) {
            return $this->schemaFail($e);
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array{status:int,body:array<string,mixed>}
     */
    public function heartbeat(int $userId, int $companyId, array $input): array
    {
        if ($userId < 1 || $companyId < 1) {
            return $this->fail(401, 'unauthorized', 'Unauthorized');
        }

        unset($input['user_id'], $input['company_id'], $input['userId'], $input['companyId']);

        $clientApp = $this->devices->normalizeClientApp($input['client_app'] ?? null);
        $deviceId = $this->devices->normalizeDeviceId($input['device_id'] ?? null);
        if ($clientApp === null || $deviceId === null) {
            return $this->fail(422, 'validation_error', 'Invalid client_app or device_id');
        }

        try {
            MobileDeviceSchemaBootstrap::ensure();

            $row = $this->store->findByIdentity($companyId, $clientApp, $deviceId);
            if ($row === null || (int) ($row['user_id'] ?? 0) !== $userId) {
                return $this->fail(404, 'not_found', 'Device not found');
            }
            if ((string) ($row['status'] ?? '') === self::STATUS_REVOKED) {
                return $this->fail(403, 'device_revoked', 'Device has been revoked');
            }

            $patch = [
                'last_seen_at' => date('Y-m-d H:i:s'),
                'status' => self::STATUS_ACTIVE,
            ];
            // Only update token when a non-empty value is provided — never null wipe via heartbeat.
            if (array_key_exists('push_token', $input)) {
                $tok = $this->devices->nullableToken($input['push_token']);
                if ($tok !== null) {
                    $patch['push_token'] = $tok;
                }
            }
            if (array_key_exists('push_provider', $input)) {
                $p = $this->devices->normalizeProvider($input['push_provider']);
                if ($p !== null) {
                    $patch['push_provider'] = $p;
                }
            }
            if (array_key_exists('locale', $input)) {
                $patch['locale'] = $this->devices->nullableLocale($input['locale']);
            }
            if (array_key_exists('app_version', $input)) {
                $patch['app_version'] = $this->devices->nullableVersion($input['app_version']);
            }
            if (array_key_exists('platform', $input)) {
                $patch['platform'] = $this->devices->normalizePlatform($input['platform']);
            }

            $this->store->update((int) $row['id'], $patch);
            $fresh = $this->store->findByIdForUser($companyId, $userId, (int) $row['id']);

            return $this->ok(['device' => $this->devices->dto($fresh ?? $row)]);
        } catch (\Throwable $e) {
            return $this->schemaFail($e);
        }
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    public function revoke(int $userId, int $companyId, int $devicePk): array
    {
        return $this->devices->revoke($userId, $companyId, $devicePk);
    }

    /**
     * @param array<string,mixed> $input
     * @return array{status:int,body:array<string,mixed>}
     */
    public function updatePushToken(int $userId, int $companyId, array $input): array
    {
        try {
            MobileDeviceSchemaBootstrap::ensure();
        } catch (\Throwable $e) {
            return $this->schemaFail($e);
        }

        return $this->devices->updatePushToken($userId, $companyId, $input);
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
    private function schemaFail(\Throwable $e): array
    {
        if (DatabaseErrorService::isSchemaIssue($e)) {
            return $this->fail(503, 'schema_outdated', DatabaseErrorService::userMessage($e));
        }

        return $this->fail(500, 'register_failed', 'Device register failed');
    }

    /**
     * @param array<string,mixed> $data
     * @return array{status:int,body:array<string,mixed>}
     */
    private function ok(array $data): array
    {
        return [
            'status' => 200,
            'body' => ['success' => true, 'data' => $data],
        ];
    }

    /**
     * @return array{status:int,body:array<string,mixed>}
     */
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
