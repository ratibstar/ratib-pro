<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Models;

use Rateb\App\Core\Model;

final class OfflineSyncQueueItem extends Model
{
    protected string $table = 'rateb_offline_sync_queue';

    protected bool $tenantScoped = true;

    protected array $fillable = [
        'company_id',
        'branch_id',
        'device_id',
        'user_id',
        'module',
        'action',
        'idempotency_key',
        'payload',
        'status',
        'version',
        'retry_count',
        'last_error',
        'synced_at',
    ];
}
