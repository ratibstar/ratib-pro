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

    /** Curated marketing bullets: DB limits first, then lang features (no raw module list). */
    /** @param array<string, mixed> $plan
     * @return list<string>
     */
    public static function marketingDisplayFeatures(array $plan): array
    {
        $features = self::marketingFeatures($plan);
        $limits = self::marketingLimitsSummary($plan);
        if ($limits !== '') {
            array_unshift($features, $limits);
        }

        return $features;
    }

    /** @param array<string, mixed> $plan
     * @return list<string>
     */
    public static function marketingModuleHighlights(array $plan, int $max = 8): array
    {
        $decoded = json_decode((string) ($plan['modules'] ?? ''), true);
        if (!is_array($decoded) || $decoded === []) {
            $slug = trim((string) ($plan['slug'] ?? ''));
            if ($slug !== '') {
                $decoded = \Rateb\App\Services\PlanLimitService::modulesForSlug($slug);
            }
        }
        if ($decoded === []) {
            return [];
        }
        $catalog = \Rateb\App\Services\PlanLimitService::moduleCatalog();
        $lines = [];
        foreach ($decoded as $mod) {
            $key = (string) $mod;
            if ($key === '') {
                continue;
            }
            $labelKey = (string) ($catalog[$key] ?? $key);
            $lines[] = __($labelKey);
            if (count($lines) >= $max) {
                break;
            }
        }

        return $lines;
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
            $parts[] = self::marketingUsersPhrase($users);
        }
        if ($branches > 0) {
            $parts[] = self::marketingBranchesPhrase($branches);
        }

        return implode(' · ', $parts);
    }

    private static function marketingUsersPhrase(int $users): string
    {
        if (function_exists('rateb_locale') && rateb_locale() === 'ar') {
            if ($users === 1) {
                return 'مستخدم واحد';
            }
            if ($users === 2) {
                return 'مستخدمان';
            }
            if ($users >= 3 && $users <= 10) {
                return 'حتى ' . $users . ' مستخدمين';
            }
            if ($users === 100) {
                return 'حتى مائة مستخدم';
            }
            if ($users > 10) {
                return 'حتى ' . $users . ' مستخدمًا';
            }

            return 'حتى ' . $users . ' مستخدم';
        }

        return $users === 1
            ? __('plan_up_to_users', ['n' => '1'])
            : __('plan_up_to_users', ['n' => (string) $users]);
    }

    private static function marketingBranchesPhrase(int $branches): string
    {
        if (function_exists('rateb_locale') && rateb_locale() === 'ar') {
            if ($branches === 1) {
                return 'فرع واحد';
            }
            if ($branches === 2) {
                return 'فرعان';
            }
            if ($branches >= 3 && $branches <= 10) {
                return $branches . ' فروع';
            }

            return $branches . ' فرعاً';
        }

        return __('plan_up_to_branches', ['n' => (string) $branches]);
    }
}
