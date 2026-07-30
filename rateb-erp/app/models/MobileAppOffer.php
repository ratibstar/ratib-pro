<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Per-company mobile promotional offer. */
final class MobileAppOffer extends Model
{
    protected string $table = 'rateb_mobile_app_offers';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id',
        'title_ar',
        'title_en',
        'body_ar',
        'body_en',
        'image_path',
        'discount_label',
        'starts_at',
        'ends_at',
        'sort_order',
        'is_active',
    ];
}
