<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Models;

use Rateb\App\Core\Model;

final class OfflineDevice extends Model
{
    protected string $table = 'rateb_offline_devices';

    protected bool $tenantScoped = true;

    protected array $fillable = [
        'company_id',
        'branch_id',
        'device_id',
        'user_id',
        'label',
        'fingerprint',
        'nickname',
        'meta_json',
        'last_seen_at',
        'last_online_at',
        'last_replay_at',
        'last_unlock_at',
        'last_logout_at',
        'status',
        'trust_status',
        'identity_expires_at',
        'identity_version',
        'identity_jti',
        'force_logout_at',
        'vault_integrity',
        'activated_by',
        'activated_at',
        'approved_by',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_TRUSTED = 'trusted';
    public const STATUS_LOST = 'lost';
    public const STATUS_DISABLED = 'disabled';

    public const TRUST_TRUSTED = 'trusted';
    public const TRUST_REVOKED = 'revoked';
    public const TRUST_LOST = 'lost';
    public const TRUST_DISABLED = 'disabled';

    /** @return array<string, mixed>|null */
    public function findByDeviceId(int $companyId, string $deviceId): ?array
    {
        $deviceId = trim($deviceId);
        if ($companyId < 1 || $deviceId === '') {
            return null;
        }

        return $this->queryOne(
            'SELECT * FROM rateb_offline_devices
             WHERE company_id = :cid AND device_id = :did
             LIMIT 1',
            ['cid' => $companyId, 'did' => $deviceId]
        );
    }
}
