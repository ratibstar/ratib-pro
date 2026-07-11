<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Models;

use Rateb\App\Core\Model;

final class OfflineIdentityAudit extends Model
{
    protected string $table = 'rateb_offline_identity_audit';

    protected bool $tenantScoped = true;

    protected array $fillable = [
        'company_id',
        'branch_id',
        'user_id',
        'device_id',
        'event_type',
        'detail_json',
    ];
}
