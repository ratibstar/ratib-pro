<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Company;
use Rateb\App\Models\Plan;
use Rateb\App\Models\Subscription;
use Rateb\App\Models\User;

final class DedicatedCompanySeedService
{
  /** Initial company admin for dedicated ERP (change after first login). */
    public const DEFAULT_LOGIN = 'admin';
    public const DEFAULT_EMAIL = 'admin@local';
    public const DEFAULT_PASSWORD = '123456';

    /**
     * Seed exactly one company + admin user for a dedicated ERP database.
     *
     * @return array{company_id:int,user_id:int,admin_email:string,admin_password:string,admin_username:string}
     */
    public function seed(
        string $companyName,
        string $adminEmail,
        string $planSlug = 'professional',
        string $adminName = ''
    ): array {
        DedicatedTenantPolicy::assertCanCreateCompany();

        $email = self::DEFAULT_EMAIL;
        $contactName = self::DEFAULT_LOGIN;
        $password = self::DEFAULT_PASSWORD;

        $plan = $this->resolvePlan($planSlug);
        $planId = (int) $plan['id'];
        $modules = $plan['modules'] ?? json_encode(PlanLimitService::defaultModules(), JSON_UNESCAPED_UNICODE);

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $companyId = (new Company())->create([
                'name' => $companyName,
                'slug' => $this->uniqueCompanySlug($companyName),
                'email' => $email,
                'phone' => '',
                'status' => 'active',
                'plan_id' => $planId,
                'user_limit' => (int) ($plan['max_users'] ?? 25),
                'branch_limit' => (int) ($plan['max_branches'] ?? 5),
                'storage_limit_mb' => (int) ($plan['max_storage_mb'] ?? 2048),
                'modules' => $modules,
            ]);

            (new Subscription())->create([
                'company_id' => $companyId,
                'plan_id' => $planId,
                'status' => 'active',
                'billing_cycle' => 'yearly',
                'amount' => (float) ($plan['price_yearly'] ?? $plan['price_monthly'] ?? 0),
                'starts_at' => date('Y-m-d'),
                'ends_at' => date('Y-m-d', strtotime('+1 year')),
                'auto_renew' => 1,
            ]);

            $userId = (new User())->create([
                'company_id' => $companyId,
                'name' => $contactName,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'phone' => '',
                'is_super_admin' => 0,
                'status' => 'active',
                'locale' => 'ar',
            ]);

            $roleRow = (new User())->queryOne(
                "SELECT id FROM rateb_roles WHERE slug = 'company-full-access' LIMIT 1"
            );
            if ($roleRow) {
                (new AuthorizationService())->assignRole($userId, (int) $roleRow['id']);
            }

            (new BarcodeLoginService())->ensureUserBarcode($userId);
            (new BranchService())->ensureMainBranch($companyId);

            $db->commit();

            return [
                'company_id' => $companyId,
                'user_id' => $userId,
                'admin_username' => self::DEFAULT_LOGIN,
                'admin_email' => self::DEFAULT_LOGIN,
                'admin_password' => $password,
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /** @return array<string, mixed> */
    private function resolvePlan(string $planSlug): array
    {
        $slug = trim($planSlug) !== '' ? trim($planSlug) : 'professional';
        $plan = (new Plan())->queryOne(
            'SELECT * FROM rateb_plans WHERE slug = :slug AND is_active = 1 LIMIT 1',
            ['slug' => $slug]
        );
        if ($plan !== null) {
            return $plan;
        }
        $fallback = (new Plan())->queryOne(
            'SELECT * FROM rateb_plans WHERE is_active = 1 ORDER BY price_monthly ASC LIMIT 1'
        );
        if ($fallback === null) {
            throw new \RuntimeException('No active ERP plan found for dedicated seed.');
        }

        return $fallback;
    }

    private function uniqueCompanySlug(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/', '-', $name), '-'));
        if ($slug === '' || strlen($slug) < 2) {
            $slug = 'company';
        }
        $companyModel = new Company();
        $base = $slug;
        $i = 0;
        while ($companyModel->findBySlug($slug) !== null) {
            $i++;
            $slug = $base . '-' . $i;
        }

        return $slug;
    }
}
