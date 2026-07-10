<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Models;

use Rateb\App\Core\Model;

final class OfflineEntityCursor extends Model
{
    protected string $table = 'rateb_offline_entity_cursors';

    protected bool $tenantScoped = true;

    protected array $fillable = [
        'company_id',
        'branch_id',
        'entity_type',
        'cursor_token',
        'updated_at',
    ];
}
