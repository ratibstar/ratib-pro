<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Per-company mobile app content page (about / terms / privacy / faq / …). */
final class MobileAppContent extends Model
{
    protected string $table = 'rateb_mobile_app_contents';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id',
        'slug',
        'title_ar',
        'title_en',
        'body_ar',
        'body_en',
        'sort_order',
        'is_active',
    ];
}
