<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Company;
use Rateb\App\Models\Plan;
use Rateb\App\Models\User;

final class PlanLimitService
{
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
        ];
    }

    public function getCompanyRow(int $companyId): ?array
    {
        return (new Company())->find($companyId);
    }

    /** @return array{user_limit:int,storage_limit_mb:int,modules:array<int,string>,plan_name:?string} */
    public function getLimits(int $companyId): array
    {
        $company = $this->getCompanyRow($companyId);
        if (!$company) {
            return ['user_limit' => 0, 'storage_limit_mb' => 0, 'modules' => [], 'plan_name' => null];
        }

        $modules = $this->decodeModules($company['modules'] ?? null);
        $planName = null;

        if (!empty($company['plan_id'])) {
            $plan = (new Plan())->find((int) $company['plan_id']);
            if ($plan) {
                $planName = (string) $plan['name'];
                if ($modules === []) {
                    $modules = $this->decodeModules($plan['modules'] ?? null);
                }
                if ((int) ($company['user_limit'] ?? 0) < 1) {
                    $company['user_limit'] = (int) ($plan['max_users'] ?? 10);
                }
                if ((int) ($company['storage_limit_mb'] ?? 0) < 1) {
                    $company['storage_limit_mb'] = (int) ($plan['max_storage_mb'] ?? 1024);
                }
            }
        }

        return [
            'user_limit' => (int) ($company['user_limit'] ?? 10),
            'storage_limit_mb' => (int) ($company['storage_limit_mb'] ?? 1024),
            'modules' => $modules,
            'plan_name' => $planName,
        ];
    }

    public function companyHasModule(int $companyId, string $module): bool
    {
        $limits = $this->getLimits($companyId);
        return in_array($module, $limits['modules'], true);
    }

    public function canAddUser(int $companyId): bool
    {
        $limits = $this->getLimits($companyId);
        $count = (new User())->count(['company_id' => $companyId]);
        return $count < $limits['user_limit'];
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
