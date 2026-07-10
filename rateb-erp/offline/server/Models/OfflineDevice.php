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
    ];
}
