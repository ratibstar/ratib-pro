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

    /** @param array<string, mixed> $plan */
    public static function marketingName(array $plan): string
    {
        $slug = trim((string) ($plan['slug'] ?? ''));
        if ($slug !== '') {
            $key = 'plan_' . $slug . '_name';
            $label = __($key);
            if ($label !== $key) {
                return $label;
            }
        }
        return (string) ($plan['name'] ?? '');
    }

    /** @param array<string, mixed> $plan */
    public static function marketingDescription(array $plan): string
    {
        $slug = trim((string) ($plan['slug'] ?? ''));
        if ($slug !== '') {
            $key = 'plan_' . $slug . '_desc';
            $label = __($key);
            if ($label !== $key) {
                return $label;
            }
        }
        return (string) ($plan['description'] ?? '');
    }

    /** @param array<string, mixed> $plan */
    public static function marketingPrice(array $plan): string
    {
        $monthly = $plan['price_monthly'] ?? $plan['price'] ?? 0;
        return number_format((float) $monthly, 0, '.', ',');
    }
}
