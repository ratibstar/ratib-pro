<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\MobileDevice;

/**
 * DB access for rateb_mobile_devices (phone apps only — not POS/offline).
 */
final class MobileDeviceDbStore implements MobileDeviceStoreInterface
{
    private const COLS = 'id, company_id, user_id, client_app, platform, device_id, push_token,
                    push_provider, locale, app_version, last_seen_at, status, created_at, updated_at';

    /** @return array<string,mixed>|null */
    public function findByIdentity(int $companyId, string $clientApp, string $deviceId): ?array
    {
        return (new MobileDevice())->queryOne(
            'SELECT ' . self::COLS . '
             FROM rateb_mobile_devices
             WHERE company_id = :cid AND client_app = :app AND device_id = :did
             LIMIT 1',
            ['cid' => $companyId, 'app' => $clientApp, 'did' => $deviceId]
        );
    }

    /** @return array<string,mixed>|null */
    public function findByIdForUser(int $companyId, int $userId, int $id): ?array
    {
        return (new MobileDevice())->queryOne(
            'SELECT ' . self::COLS . '
             FROM rateb_mobile_devices
             WHERE company_id = :cid AND user_id = :uid AND id = :id
             LIMIT 1',
            ['cid' => $companyId, 'uid' => $userId, 'id' => $id]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listActiveWithPush(int $companyId, int $userId, string $clientApp): array
    {
        $rows = (new MobileDevice())->query(
            'SELECT ' . self::COLS . '
             FROM rateb_mobile_devices
             WHERE company_id = :cid
               AND user_id = :uid
               AND client_app = :app
               AND status = :st
               AND push_token IS NOT NULL
               AND push_token <> \'\'
             ORDER BY id ASC',
            [
                'cid' => $companyId,
                'uid' => $userId,
                'app' => $clientApp,
                'st' => MobileDeviceRegistryService::STATUS_ACTIVE,
            ]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listActiveWithPushForCompany(int $companyId, string $clientApp): array
    {
        $rows = (new MobileDevice())->query(
            'SELECT ' . self::COLS . '
             FROM rateb_mobile_devices
             WHERE company_id = :cid
               AND client_app = :app
               AND status = :st
               AND push_token IS NOT NULL
               AND push_token <> \'\'
             ORDER BY id ASC',
            [
                'cid' => $companyId,
                'app' => $clientApp,
                'st' => MobileDeviceRegistryService::STATUS_ACTIVE,
            ]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @param array<string,mixed> $data */
    public function insert(array $data): int
    {
        return (int) (new MobileDevice())->create($data);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        (new MobileDevice())->update($id, $data);
    }
}
