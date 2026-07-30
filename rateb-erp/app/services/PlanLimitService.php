<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Company;
use Rateb\App\Models\Plan;
use Rateb\App\Models\User;

final class PlanLimitService
{
    /** @var array<int, array<string, mixed>> */
    private static array $limitsCache = [];

    /** @return array<string, array<string, mixed>> */
    public static function tierDefinitions(): array
    {
        static $tiers = null;
        if ($tiers !== null) {
            return $tiers;
        }
        $file = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/config/plan-tiers.php';
        $tiers = is_file($file) ? require $file : [];

        return is_array($tiers) ? $tiers : [];
    }

    /** @return array<string, mixed>|null */
    public static function tierForSlug(string $slug): ?array
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return null;
        }
        $tier = self::tierDefinitions()[$slug] ?? null;

        return is_array($tier) ? $tier : null;
    }

    /** @return list<string> */
    public static function modulesForSlug(string $slug): array
    {
        $tier = self::tierForSlug($slug);
        if ($tier === null) {
            return self::defaultModules();
        }
        $mods = $tier['modules'] ?? [];
        if (!is_array($mods) || $mods === []) {
            return self::defaultModules();
        }

        return array_values(array_filter(array_map('strval', $mods)));
    }

    /** @return array<string, string> module key => lang label key */
    public static function moduleCatalog(): array
    {
        static $catalog = null;
        if ($catalog !== null) {
            return $catalog;
        }

        $file = (defined('RATEB_ROOT') ? RATEB_ROOT : '') . '/config/permissions-system.php';
        $cfg = is_file($file) ? require $file : [];
        $modules = is_array($cfg['company_modules'] ?? null) ? $cfg['company_modules'] : [];
        $labels = is_array($cfg['tenant_module_labels'] ?? null) ? $cfg['tenant_module_labels'] : [];

        $catalog = [];
        foreach ($modules as $mod) {
            $key = trim((string) $mod);
            if ($key === '') {
                continue;
            }
            $catalog[$key] = trim((string) ($labels[$key] ?? $key));
        }

        return $catalog;
    }

    /** @param list<string> $modules @return list<string> */
    public static function filterKnownModules(array $modules): array
    {
        $known = array_keys(self::moduleCatalog());

        return array_values(array_filter(
            array_map('strval', $modules),
            static fn(string $module): bool => $module !== '' && in_array($module, $known, true)
        ));
    }

    public function getCompanyRow(int $companyId): ?array
    {
        return (new Company())->find($companyId);
    }

    /** @return list<string> */
    public static function defaultModules(): array
    {
        return ['procurement', 'inventory', 'suppliers'];
    }

    /** @return array{user_limit:int,storage_limit_mb:int,modules:array<int,string>,plan_name:?string} */
    public function getLimits(int $companyId): array
    {
        if (isset(self::$limitsCache[$companyId])) {
            return self::$limitsCache[$companyId];
        }

        $company = $this->getCompanyRow($companyId);
        if (!$company) {
            return ['user_limit' => 0, 'storage_limit_mb' => 0, 'modules' => [], 'plan_name' => null];
        }

        $plan = null;
        if (!empty($company['plan_id'])) {
            $plan = (new Plan())->find((int) $company['plan_id']);
        }

        $modules = $this->resolveModules($company, $plan);
        $planName = $plan ? (string) $plan['name'] : null;

        $userLimit = (int) ($company['user_limit'] ?? 0);
        if ($userLimit < 1 && $plan) {
            $userLimit = (int) ($plan['max_users'] ?? 10);
        }
        if ($userLimit < 1) {
            $userLimit = 10;
        }

        $storageMb = (int) ($company['storage_limit_mb'] ?? 0);
        if ($storageMb < 1 && $plan) {
            $storageMb = (int) ($plan['max_storage_mb'] ?? 1024);
        }
        if ($storageMb < 1) {
            $storageMb = 1024;
        }

        self::$limitsCache[$companyId] = [
            'user_limit' => $userLimit,
            'storage_limit_mb' => $storageMb,
            'branch_limit' => $this->branchLimitForCompany($company, $plan),
            'modules' => $modules,
            'plan_name' => $planName,
        ];

        return self::$limitsCache[$companyId];
    }

    public static function forgetCompanyLimits(?int $companyId = null): void
    {
        if ($companyId === null) {
            self::$limitsCache = [];
            return;
        }
        unset(self::$limitsCache[$companyId]);
    }

    private function branchLimitForCompany(array $company, ?array $plan): int
    {
        $limit = (int) ($company['branch_limit'] ?? 0);
        if ($limit > 0) {
            return $limit;
        }
        if ($plan) {
            $planLimit = (int) ($plan['max_branches'] ?? 0);
            if ($planLimit > 0) {
                return $planLimit;
            }
        }
        return 10;
    }

    /** @param array<string,mixed> $company @param array<string,mixed>|null $plan */
    private function resolveModules(array $company, ?array $plan): array
    {
        // Dedicated agency ERP: Control Panel package (erp_plan_slug) is the entitlement source.
        if (DedicatedTenantPolicy::isDedicated()) {
            $controlSlug = $this->resolveControlPlanSlug();
            if ($controlSlug !== '') {
                $tier = self::modulesForSlug($controlSlug);
                if ($tier !== []) {
                    $this->maybePersistDedicatedPlanFromControl($company, $controlSlug, $tier);

                    return self::applyLegacyImpliedModules($tier, $company);
                }
            }
            // Fallback: trust plan_id slug even when control lookup is unavailable on the agency vhost.
            if ($plan) {
                $planSlug = strtolower(trim((string) ($plan['slug'] ?? '')));
                $tier = $planSlug !== '' ? self::modulesForSlug($planSlug) : [];
                if ($tier !== []) {
                    return self::applyLegacyImpliedModules($tier, $company);
                }
            }
        }

        $companyModules = $this->decodeModules($company['modules'] ?? null);
        $modules = $companyModules;

        if ($plan) {
            $planSlug = strtolower(trim((string) ($plan['slug'] ?? '')));
            $tierModules = $planSlug !== '' ? self::modulesForSlug($planSlug) : [];
            if ($modules === [] && $tierModules !== []) {
                $modules = $tierModules;
            } elseif ($modules === []) {
                $modules = $this->decodeModules($plan['modules'] ?? null);
            } elseif ($tierModules !== [] && DedicatedTenantPolicy::isDedicated()) {
                // Stale company.modules (e.g. starter) must not override plan_id entitlements.
                $modules = $tierModules;
            }
        }

        if ($modules === []) {
            $starter = (new Plan())->queryOne(
                "SELECT modules FROM rateb_plans WHERE slug = 'starter' AND is_active = 1 LIMIT 1"
            );
            if ($starter) {
                $modules = $this->decodeModules($starter['modules'] ?? null);
            }
        }

        if ($modules === []) {
            $modules = self::defaultModules();
        }

        return self::applyLegacyImpliedModules($modules, $company);
    }

    private function resolveControlPlanSlug(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $cached = '';
        try {
            $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
            if ($host !== '' && str_contains($host, ':')) {
                $host = explode(':', $host, 2)[0];
            }
            if ($host === '' || !function_exists('rateb_lookup_agency_by_host')) {
                return $cached;
            }
            $agency = rateb_lookup_agency_by_host($host);
            if (!is_array($agency)) {
                return $cached;
            }
            $slug = strtolower(trim((string) ($agency['erp_plan_slug'] ?? '')));
            if (in_array($slug, ['starter', 'professional', 'enterprise'], true)) {
                $cached = $slug;
            }
        } catch (\Throwable $e) {
            $cached = '';
        }

        return $cached;
    }

    /** @param array<string,mixed> $company @param list<string> $tierModules */
    private function maybePersistDedicatedPlanFromControl(array $company, string $planSlug, array $tierModules): void
    {
        static $attempted = false;
        if ($attempted) {
            return;
        }
        $attempted = true;
        $companyId = (int) ($company['id'] ?? 0);
        if ($companyId < 1 || $tierModules === []) {
            return;
        }
        $current = $this->decodeModules($company['modules'] ?? null);
        $missing = array_values(array_diff($tierModules, $current));
        if ($missing === []) {
            return;
        }
        try {
            (new DedicatedCompanySeedService())->applyPlanSlug($companyId, $planSlug);
        } catch (\Throwable $e) {
            error_log('RATEB dedicated plan persist: ' . $e->getMessage());
        }
    }

    /** @param list<string> $modules @param array<string,mixed> $company @return list<string> */
    private static function applyLegacyImpliedModules(array $modules, array $company): array
    {
        $explicit = self::decodeModulesStatic($company['modules'] ?? null);
        if ($explicit === []) {
            return $modules;
        }
        $extendedKeys = ['dashboard', 'pos', 'branches', 'notifications'];
        foreach ($extendedKeys as $key) {
            if (in_array($key, $explicit, true)) {
                return $modules;
            }
        }
        foreach (['dashboard', 'notifications'] as $implied) {
            if (!in_array($implied, $modules, true)) {
                $modules[] = $implied;
            }
        }

        return $modules;
    }

    /** @return list<string> */
    private static function decodeModulesStatic($raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
    }

    public function companyHasModule(int $companyId, string $module): bool
    {
        $limits = $this->getLimits($companyId);
        return in_array($module, $limits['modules'], true);
    }

    public function companyAccessAllowed(int $companyId): bool
    {
        $company = $this->getCompanyRow($companyId);
        if (!$company) {
            return false;
        }
        $status = (string) ($company['status'] ?? '');
        if ($status === 'suspended') {
            return false;
        }
        if ($status !== 'active') {
            return false;
        }
        return $this->hasValidSubscription($companyId);
    }

    /** ESS / mobile API bearer: company row must exist (no SaaS subscription gate). */
    public function apiBearerCompanyAllowed(int $companyId): bool
    {
        return $companyId > 0 && $this->getCompanyRow($companyId) !== null;
    }

    /** First company row usable for ESS when user tenant is missing/invalid. */
    public function essFallbackCompanyId(): int
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $primary = DedicatedTenantPolicy::primaryCompanyId();
        if ($primary > 0 && $this->getCompanyRow($primary) !== null) {
            $cached = $primary;
            return $cached;
        }

        try {
            $row = (new Company())->queryOne('SELECT id FROM rateb_companies ORDER BY id ASC LIMIT 1');
            $cached = is_array($row) ? (int) ($row['id'] ?? 0) : 0;
        } catch (\Throwable $e) {
            $cached = 0;
        }

        return $cached;
    }

    /**
     * Resolve tenant for mobile token minting.
     * Prefer employee-bound company, then user company, then ESS fallback.
     *
     * @param array<string, mixed> $user
     */
    public function resolveEssApiCompanyId(array $user): int
    {
        $userId = (int) ($user['id'] ?? 0);
        $email = strtolower(trim((string) ($user['email'] ?? '')));

        if ($userId > 0) {
            $boundCompany = $this->essEmployeeCompanyId(
                'SELECT company_id FROM rateb_employees WHERE user_id = :uid ORDER BY id ASC LIMIT 1',
                ['uid' => $userId]
            );
            if ($boundCompany > 0) {
                return $boundCompany;
            }
        }

        if ($email !== '') {
            $emailCompany = $this->essEmployeeCompanyId(
                'SELECT company_id FROM rateb_employees
                 WHERE LOWER(TRIM(email)) = :em
                 ORDER BY id ASC LIMIT 1',
                ['em' => $email]
            );
            if ($emailCompany > 0) {
                return $emailCompany;
            }
        }

        $companyId = (int) ($user['company_id'] ?? 0);
        if ($companyId > 0 && $this->getCompanyRow($companyId) !== null) {
            return $companyId;
        }

        return $this->essFallbackCompanyId();
    }

    /** Unscoped employee company lookup (branch filters must not block ESS login). */
    private function essEmployeeCompanyId(string $sql, array $params): int
    {
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();
            $companyId = is_array($row) ? (int) ($row['company_id'] ?? 0) : 0;
            if ($companyId > 0 && $this->getCompanyRow($companyId) !== null) {
                return $companyId;
            }
        } catch (\Throwable $e) {
            // continue
        }

        return 0;
    }

    public function hasValidSubscription(int $companyId): bool
    {
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            return true;
        }
        if (function_exists('rateb_erp_is_dedicated_deployment') && rateb_erp_is_dedicated_deployment()) {
            return true;
        }

        $sub = (new \Rateb\App\Models\Subscription())->queryOne(
            'SELECT status, ends_at FROM rateb_subscriptions WHERE company_id = :cid ORDER BY id DESC LIMIT 1',
            ['cid' => $companyId]
        );
        if (!$sub) {
            return false;
        }
        $st = (string) ($sub['status'] ?? '');
        if (in_array($st, ['active', 'trial'], true)) {
            $ends = (string) ($sub['ends_at'] ?? '');
            if ($ends !== '' && strtotime($ends) < strtotime('today')) {
                return false;
            }
            return true;
        }
        return false;
    }

    public function storageUsedBytes(int $companyId): int
    {
        $row = (new Company())->queryOne(
            'SELECT COALESCE(SUM(file_size), 0) AS total FROM rateb_documents WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        return (int) ($row['total'] ?? 0);
    }

    public function canUploadBytes(int $companyId, int $bytes): bool
    {
        $limits = $this->getLimits($companyId);
        $maxBytes = (int) $limits['storage_limit_mb'] * 1024 * 1024;
        if ($maxBytes < 1) {
            return true;
        }
        return ($this->storageUsedBytes($companyId) + $bytes) <= $maxBytes;
    }

    public function canAddUser(int $companyId): bool
    {
        $limits = $this->getLimits($companyId);
        $count = (new User())->count(['company_id' => $companyId]);
        return $count < $limits['user_limit'];
    }

    public function assertCanAddUser(int $companyId): void
    {
        if ($companyId > 0 && !$this->canAddUser($companyId)) {
            throw new \RuntimeException(__('user_limit_reached'));
        }
    }

    public function syncFromPlan(int $companyId, int $planId): void
    {
        $plan = (new Plan())->find($planId);
        if (!$plan) {
            return;
        }

        (new Company())->update($companyId, [
            'plan_id' => $planId,
            'user_limit' => (int) ($plan['max_users'] ?? 10),
            'storage_limit_mb' => (int) ($plan['max_storage_mb'] ?? 1024),
            'modules' => $plan['modules'] ?? '[]',
        ]);
    }

    /** @return array<int, string> */
    private function decodeModules($raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw)));
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded))) : [];
    }
}
