<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Models;

use Rateb\App\Core\Model;

final class OfflineIdentityNonce extends Model
{
    protected string $table = 'rateb_offline_identity_nonces';

    protected bool $tenantScoped = true;

    protected array $fillable = [
        'company_id',
        'device_id',
        'jti',
        'identity_version',
        'status',
        'issued_at',
        'expires_at',
        'invalidated_at',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INVALID = 'invalid';

    /** @return array<string, mixed>|null */
    public function findActiveJti(int $companyId, string $jti): ?array
    {
        $jti = trim($jti);
        if ($companyId < 1 || $jti === '') {
            return null;
        }

        return $this->queryOne(
            'SELECT * FROM rateb_offline_identity_nonces
             WHERE company_id = :cid AND jti = :jti AND status = :st
             LIMIT 1',
            [
                'cid' => $companyId,
                'jti' => $jti,
                'st' => self::STATUS_ACTIVE,
            ]
        );
    }

    public function invalidateDevice(int $companyId, string $deviceId): int
    {
        $deviceId = trim($deviceId);
        if ($companyId < 1 || $deviceId === '') {
            return 0;
        }

        $rows = $this->query(
            'SELECT id FROM rateb_offline_identity_nonces
             WHERE company_id = :cid AND device_id = :did AND status = :st',
            [
                'cid' => $companyId,
                'did' => $deviceId,
                'st' => self::STATUS_ACTIVE,
            ]
        );
        $n = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            if ($this->update($id, [
                'status' => self::STATUS_INVALID,
                'invalidated_at' => $now,
            ])) {
                $n++;
            }
        }

        return $n;
    }

    public function invalidateJti(int $companyId, string $jti): bool
    {
        $jti = trim($jti);
        if ($companyId < 1 || $jti === '') {
            return false;
        }

        $row = $this->queryOne(
            'SELECT id FROM rateb_offline_identity_nonces
             WHERE company_id = :cid AND jti = :jti
             LIMIT 1',
            ['cid' => $companyId, 'jti' => $jti]
        );
        if ($row === null) {
            return false;
        }
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            return false;
        }

        return $this->update($id, [
            'status' => self::STATUS_INVALID,
            'invalidated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
