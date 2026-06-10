<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class Plan extends Model
{
    protected string $table = 'rateb_plans';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'name', 'slug', 'description', 'price_monthly', 'price_yearly',
        'max_users', 'max_storage_mb', 'modules', 'is_active',
    ];

    public function getActive(): array
    {
        return $this->query('SELECT * FROM rateb_plans WHERE is_active = 1 ORDER BY price_monthly ASC');
    }
}
