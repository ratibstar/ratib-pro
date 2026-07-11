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
        'meta_json',
        'last_seen_at',
        'status',
        'activated_by',
        'activated_at',
        'approved_by',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_REVOKED = 'revoked';

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
