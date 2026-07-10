<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Models;

use Rateb\App\Core\Model;

final class OfflineSyncConflict extends Model
{
    protected string $table = 'rateb_offline_sync_conflicts';

    protected bool $tenantScoped = true;

    protected array $fillable = [
        'company_id',
        'queue_id',
        'idempotency_key',
        'reason',
        'client_payload',
        'server_payload',
        'status',
        'resolved_by',
        'resolved_at',
    ];
}
