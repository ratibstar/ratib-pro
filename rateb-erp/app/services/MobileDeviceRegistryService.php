<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\MobileDevice;

/**
 * Unified Mobile Device Registry — ERP-owned, shared by all mobile clients.
 * Never stores passwords or authentication secrets.
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

        $clientApp = $this->normalizeClientApp($input['client_app'] ?? null);
        if ($clientApp === null) {
            return $this->fail(422, 'validation_error', 'Invalid client_app');
        }
        $deviceId = $this->normalizeDeviceId($input['device_id'] ?? null);
        if ($deviceId === null) {
            return $this->fail(422, 'validation_error', 'Invalid device_id');
        }
        $platform = $this->normalizePlatform($input['platform'] ?? null);
        $pushToken = $this->nullableToken($input['push_token'] ?? null);
        $appVersion = $this->nullableVersion($input['app_version'] ?? null);

        $existing = $this->findByIdentity($companyId, $clientApp, $deviceId);
        if ($existing !== null) {
            $ownerId = (int) ($existing['user_id'] ?? 0);
            if ($ownerId !== $userId) {
                return $this->fail(403, 'forbidden', 'Device belongs to another user');
            }
            if ((string) ($existing['status'] ?? '') === self::STATUS_REVOKED) {
                // Re-register after revoke: reactivate for same owner.
            }
            (new MobileDevice())->update((int) $existing['id'], [
                'platform' => $platform,
                'push_token' => $pushToken,
                'app_version' => $appVersion,
                'last_seen_at' => date('Y-m-d H:i:s'),
                'status' => self::STATUS_ACTIVE,
            ]);
            $row = $this->findByIdForUser($companyId, $userId, (int) $existing['id']);
        } else {
            $id = (new MobileDevice())->create([
                'company_id' => $companyId,
                'user_id' => $userId,
                'client_app' => $clientApp,
                'platform' => $platform,
                'device_id' => $deviceId,
                'push_token' => $pushToken,
                'app_version' => $appVersion,
                'last_seen_at' => date('Y-m-d H:i:s'),
                'status' => self::STATUS_ACTIVE,
            ]);
            $row = $this->findByIdForUser($companyId, $userId, (int) $id);
        }

        if ($row === null) {
            return $this->fail(500, 'register_failed', 'Device register failed');
        }

        return $this->ok(['device' => $this->dto($row)]);
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
        $clientApp = $this->normalizeClientApp($input['client_app'] ?? null);
        $deviceId = $this->normalizeDeviceId($input['device_id'] ?? null);
        if ($clientApp === null || $deviceId === null) {
            return $this->fail(422, 'validation_error', 'Invalid client_app or device_id');
        }

        $row = $this->findByIdentity($companyId, $clientApp, $deviceId);
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
        if (array_key_exists('push_token', $input)) {
            $patch['push_token'] = $this->nullableToken($input['push_token']);
        }
        if (array_key_exists('app_version', $input)) {
            $patch['app_version'] = $this->nullableVersion($input['app_version']);
        }
        if (array_key_exists('platform', $input)) {
            $patch['platform'] = $this->normalizePlatform($input['platform']);
        }

        (new MobileDevice())->update((int) $row['id'], $patch);
        $fresh = $this->findByIdForUser($companyId, $userId, (int) $row['id']);

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
        $row = $this->findByIdForUser($companyId, $userId, $devicePk);
        if ($row === null) {
            return $this->fail(404, 'not_found', 'Device not found');
        }

        (new MobileDevice())->update($devicePk, [
            'status' => self::STATUS_REVOKED,
            'push_token' => null,
            'last_seen_at' => date('Y-m-d H:i:s'),
        ]);
        $fresh = $this->findByIdForUser($companyId, $userId, $devicePk);

        return $this->ok(['device' => $this->dto($fresh ?? $row)]);
    }

    /** @return array<string,mixed>|null */
    private function findByIdentity(int $companyId, string $clientApp, string $deviceId): ?array
    {
        return (new MobileDevice())->queryOne(
            'SELECT id, company_id, user_id, client_app, platform, device_id, push_token,
                    app_version, last_seen_at, status, created_at, updated_at
             FROM rateb_mobile_devices
             WHERE company_id = :cid AND client_app = :app AND device_id = :did
             LIMIT 1',
            ['cid' => $companyId, 'app' => $clientApp, 'did' => $deviceId]
        );
    }

    /** @return array<string,mixed>|null */
    private function findByIdForUser(int $companyId, int $userId, int $id): ?array
    {
        return (new MobileDevice())->queryOne(
            'SELECT id, company_id, user_id, client_app, platform, device_id, push_token,
                    app_version, last_seen_at, status, created_at, updated_at
             FROM rateb_mobile_devices
             WHERE company_id = :cid AND user_id = :uid AND id = :id
             LIMIT 1',
            ['cid' => $companyId, 'uid' => $userId, 'id' => $id]
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function dto(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'client_app' => (string) ($row['client_app'] ?? ''),
            'platform' => (string) ($row['platform'] ?? ''),
            'device_id' => (string) ($row['device_id'] ?? ''),
            'app_version' => isset($row['app_version']) ? (string) $row['app_version'] : null,
            'status' => (string) ($row['status'] ?? ''),
            'last_seen_at' => isset($row['last_seen_at']) ? (string) $row['last_seen_at'] : null,
        ];
    }

    private function normalizeClientApp(mixed $raw): ?string
    {
        $v = strtolower(trim((string) ($raw ?? '')));
        return in_array($v, self::CLIENT_APPS, true) ? $v : null;
    }

    private function normalizeDeviceId(mixed $raw): ?string
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

    private function normalizePlatform(mixed $raw): string
    {
        $v = strtolower(trim((string) ($raw ?? 'other')));
        return in_array($v, self::PLATFORMS, true) ? $v : 'other';
    }

    private function nullableToken(mixed $raw): ?string
    {
        $v = trim((string) ($raw ?? ''));
        if ($v === '') {
            return null;
        }

        return mb_substr($v, 0, 512);
    }

    private function nullableVersion(mixed $raw): ?string
    {
        $v = trim((string) ($raw ?? ''));
        if ($v === '') {
            return null;
        }

        return mb_substr($v, 0, 64);
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
