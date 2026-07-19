<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/**
 * Tenant mobile white-label config (platform-managed).
 * Table is company-scoped; platform admin writes with explicit company_id.
 */
final class MobileAppConfig extends Model
{
    protected string $table = 'rateb_mobile_app_configs';
    /** Platform admin lists all tenants; isolation enforced in service/API by company_id. */
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id',
        'app_name',
        'logo_path',
        'icon_path',
        'splash_path',
        'theme_color',
        'status',
        'enabled_features',
    ];
}
