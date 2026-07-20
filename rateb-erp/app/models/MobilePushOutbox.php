<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Push delivery outbox — not notification content. */
final class MobilePushOutbox extends Model
{
    protected string $table = 'rateb_mobile_push_outbox';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id',
        'user_id',
        'client_app',
        'notification_id',
        'title',
        'body',
        'data_json',
        'status',
        'attempts',
        'last_error',
        'sent_at',
    ];
}
