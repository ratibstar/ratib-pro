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
        'max_users', 'max_storage_mb', 'max_branches', 'modules', 'is_active',
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

    /** @param array<string, mixed> $plan */
    public static function marketingYearlyPrice(array $plan): string
    {
        $yearly = $plan['price_yearly'] ?? null;
        if ($yearly !== null && (float) $yearly > 0) {
            return number_format((float) $yearly, 0, '.', ',');
        }
        $monthly = (float) ($plan['price_monthly'] ?? $plan['price'] ?? 0);
        return number_format($monthly * 12, 0, '.', ',');
    }

    /** @param array<string, mixed> $plan
     * @return list<string>
     */
    public static function marketingFeatures(array $plan): array
    {
        $slug = trim((string) ($plan['slug'] ?? ''));
        if ($slug === '') {
            return [];
        }
        $key = 'plan_' . $slug . '_features';
        $raw = __($key);
        if ($raw === $key || trim($raw) === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return $out;
    }

    /** @param array<string, mixed> $plan */
    public static function marketingLimitsSummary(array $plan): string
    {
        $users = (int) ($plan['max_users'] ?? 0);
        $branches = (int) ($plan['max_branches'] ?? 0);
        if ($users < 1 && $branches < 1) {
            return '';
        }
        $parts = [];
        if ($users > 0) {
            $parts[] = __('plan_up_to_users', ['n' => (string) $users]);
        }
        if ($branches > 0) {
            $parts[] = __('plan_up_to_branches', ['n' => (string) $branches]);
        }

        return implode(' · ', $parts);
    }
}
