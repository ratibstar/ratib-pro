<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Company;
use Rateb\App\Models\Plan;
use Rateb\App\Models\User;

final class PlanLimitService
{
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

    /** @return array<string, string> */
    public static function moduleCatalog(): array
    {
        return [
            'procurement' => 'procurement',
            'inventory' => 'inventory',
            'suppliers' => 'suppliers',
            'assets' => 'assets',
            'contracts' => 'contracts',
            'tenders' => 'tenders',
            'reports' => 'reports',
            'medical_devices' => 'medical_devices',
            'accounting' => 'accounting',
            'documents' => 'documents',
            'workflows' => 'workflows',
            'hr' => 'human_resources',
        ];
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

        return [
            'user_limit' => $userLimit,
            'storage_limit_mb' => $storageMb,
            'branch_limit' => $this->branchLimitForCompany($company, $plan),
            'modules' => $modules,
            'plan_name' => $planName,
        ];
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
        $companyModules = $this->decodeModules($company['modules'] ?? null);
        $modules = $companyModules;

        if ($modules === [] && $plan) {
            $modules = $this->decodeModules($plan['modules'] ?? null);
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

        return $modules;
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

    public function hasValidSubscription(int $companyId): bool
    {
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
