<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/**
 * Shared mobile device registry (ESS / Manager / future clients).
 * Not POS/offline devices. No auth secrets.
 */
final class MobileDevice extends Model
{
    protected string $table = 'rateb_mobile_devices';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id',
        'user_id',
        'client_app',
        'platform',
        'device_id',
        'push_token',
        'app_version',
        'last_seen_at',
        'status',
    ];
}
