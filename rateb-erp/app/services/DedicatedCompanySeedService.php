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
        $email = self::DEFAULT_EMAIL;
        $contactName = self::DEFAULT_LOGIN;
        $password = self::newInitialPassword();

        $plan = $this->resolvePlan($planSlug);
        $planId = (int) $plan['id'];
        $modules = $plan['modules'] ?? json_encode(PlanLimitService::defaultModules(), JSON_UNESCAPED_UNICODE);

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $companyId = $this->resolveDedicatedCompanyId($companyName, $email, $plan, $modules);
            $this->ensureDedicatedSubscription($companyId, $plan);
            $userId = $this->ensureDedicatedAdminUser($companyId, $email, $contactName, $password);

            $this->assignCompanyFullAccessRole($userId, $companyId);

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

    /**
     * Ensure the default tenant admin (username admin, email admin@local, password 123456).
     *
     * @return array{company_id:int,user_id:int,admin_username:string,admin_email:string,admin_password:string}
     */
    public function ensureStandardAdmin(int $companyId = 0): array
    {
        if ($companyId < 1) {
            $row = (new Company())->queryOne('SELECT id FROM rateb_companies ORDER BY id ASC LIMIT 1');
            $companyId = (int) ($row['id'] ?? 0);
        }
        if ($companyId < 1) {
            throw new \RuntimeException('No company found for standard admin.');
        }

        $userModel = new User();
        $candidate = $userModel->findByEmail(self::DEFAULT_EMAIL);
        if ($candidate === null) {
            $candidate = $userModel->queryOne(
                "SELECT * FROM rateb_users
                 WHERE company_id = :cid
                   AND COALESCE(is_super_admin, 0) = 0
                   AND (
                     email LIKE 'admin+%'
                     OR LOWER(name) = 'admin'
                   )
                 ORDER BY id ASC
                 LIMIT 1",
                ['cid' => $companyId]
            );
        }
        if ($candidate === null) {
            $candidate = $userModel->queryOne(
                'SELECT * FROM rateb_users
                 WHERE company_id = :cid AND COALESCE(is_super_admin, 0) = 0
                 ORDER BY id ASC
                 LIMIT 1',
                ['cid' => $companyId]
            );
        }

        $initialPassword = self::newInitialPassword();
        $hash = password_hash($initialPassword, PASSWORD_DEFAULT);
        if ($candidate !== null) {
            $userId = (int) ($candidate['id'] ?? 0);
            $userModel->query(
                'DELETE ur FROM rateb_user_roles ur
                 INNER JOIN rateb_users u ON u.id = ur.user_id
                 WHERE u.email = :email AND COALESCE(u.is_super_admin, 0) = 0 AND u.id <> :uid',
                ['email' => self::DEFAULT_EMAIL, 'uid' => $userId]
            );
            $userModel->query(
                'DELETE FROM rateb_users
                 WHERE email = :email AND COALESCE(is_super_admin, 0) = 0 AND id <> :uid',
                ['email' => self::DEFAULT_EMAIL, 'uid' => $userId]
            );
            $userModel->query(
                'DELETE ur FROM rateb_user_roles ur
                 INNER JOIN rateb_users u ON u.id = ur.user_id
                 WHERE u.company_id = :cid AND COALESCE(u.is_super_admin, 0) = 0 AND u.id <> :uid',
                ['cid' => $companyId, 'uid' => $userId]
            );
            $userModel->query(
                'DELETE FROM rateb_users
                 WHERE company_id = :cid AND COALESCE(is_super_admin, 0) = 0 AND id <> :uid',
                ['cid' => $companyId, 'uid' => $userId]
            );
            $userModel->update($userId, [
                'company_id' => $companyId,
                'name' => self::DEFAULT_LOGIN,
                'email' => self::DEFAULT_EMAIL,
                'password' => $hash,
                'status' => 'active',
                'is_super_admin' => 0,
            ]);
        } else {
            $userId = $this->ensureDedicatedAdminUser(
                $companyId,
                self::DEFAULT_EMAIL,
                self::DEFAULT_LOGIN,
                $initialPassword
            );
        }

        $this->assignCompanyFullAccessRole($userId, $companyId);

        (new BarcodeLoginService())->ensureUserBarcode($userId);

        return [
            'company_id' => $companyId,
            'user_id' => $userId,
            'admin_username' => self::DEFAULT_LOGIN,
            'admin_email' => self::DEFAULT_EMAIL,
            'admin_password' => $initialPassword,
        ];
    }

    private static function newInitialPassword(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Wipe business data then attach existing tenant users to a fresh company shell.
     * Password hashes and login identities are never changed.
     *
     * @return array{company_id:int,users_preserved:int,credentials_unchanged:bool}
     */
    public function rebuildShellPreserveLogins(
        string $companyName,
        string $planSlug = 'professional',
        bool $forceSingleTenant = false
    ): array {
        $plan = $this->resolvePlan($planSlug);
        $planId = (int) $plan['id'];
        $modules = $plan['modules'] ?? json_encode(PlanLimitService::defaultModules(), JSON_UNESCAPED_UNICODE);

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $companyId = $this->resolveDedicatedCompanyId(
                $companyName,
                self::DEFAULT_EMAIL,
                $plan,
                $modules,
                $forceSingleTenant
            );
            $this->ensureDedicatedSubscription($companyId, $plan);
            (new BranchService())->ensureMainBranch($companyId);
            (new WarehouseService())->dedupeMainWarehouses($companyId);
            (new WarehouseService())->ensureDefaultWarehouse($companyId);
            (new WarehouseService())->dedupeMainWarehouses($companyId);
            $this->dedupeDefaultCategory($companyId);

            $userModel = new User();
            $tenantUsers = $userModel->query(
                'SELECT id, email, name FROM rateb_users WHERE is_super_admin = 0 OR is_super_admin IS NULL ORDER BY id ASC'
            );
            $auth = new AuthorizationService();
            $auth->ensureCompanyRoles($companyId);
            $role = $auth->findRoleBySlug('company-full-access', $companyId);
            $roleId = $role ? (int) $role['id'] : 0;
            $barcode = new BarcodeLoginService();

            foreach ($tenantUsers as $user) {
                $userId = (int) ($user['id'] ?? 0);
                if ($userId < 1) {
                    continue;
                }
                $userModel->update($userId, [
                    'company_id' => $companyId,
                    'status' => 'active',
                ]);
                if ($roleId > 0) {
                    $hasRole = $userModel->queryOne(
                        'SELECT 1 FROM rateb_user_roles WHERE user_id = :uid AND role_id = :rid LIMIT 1',
                        ['uid' => $userId, 'rid' => $roleId]
                    );
                    if (!$hasRole) {
                        $auth->assignRole($userId, $roleId);
                    }
                }
                $barcode->ensureUserBarcode($userId);
            }

            $db->commit();

            return [
                'company_id' => $companyId,
                'users_preserved' => count($tenantUsers),
                'credentials_unchanged' => true,
            ];
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

  /** @param array<string, mixed> $plan */
    private function resolveDedicatedCompanyId(
        string $companyName,
        string $email,
        array $plan,
        string $modules,
        bool $forceSingleTenant = false
    ): int {
        $companyModel = new Company();
        $rows = $companyModel->query('SELECT id, slug FROM rateb_companies ORDER BY id ASC');

        if ($rows === []) {
            return (int) $companyModel->create($this->companyPayload($companyName, $email, $plan, $modules));
        }

        $singleTenant = $forceSingleTenant || DedicatedTenantPolicy::isDedicated();
        if (!$singleTenant) {
            DedicatedTenantPolicy::assertCanCreateCompany();

            return (int) $companyModel->create($this->companyPayload($companyName, $email, $plan, $modules));
        }

        $keepId = (int) $rows[0]['id'];
        foreach (array_slice($rows, 1) as $extra) {
            $this->removeCompanyTenant((int) $extra['id']);
        }

        $companyModel->update($keepId, $this->companyPayload(
            $companyName,
            $email,
            $plan,
            $modules,
            $this->uniqueCompanySlug($companyName, $keepId)
        ));

        return $keepId;
    }

    /** @param array<string, mixed> $plan
     *  @return array<string, mixed>
     */
    private function companyPayload(
        string $companyName,
        string $email,
        array $plan,
        string $modules,
        ?string $slug = null
    ): array {
        $planId = (int) $plan['id'];

        return [
            'name' => $companyName,
            'slug' => $slug ?? $this->uniqueCompanySlug($companyName),
            'email' => $email,
            'phone' => '',
            'status' => 'active',
            'plan_id' => $planId,
            'user_limit' => (int) ($plan['max_users'] ?? 25),
            'storage_limit_mb' => (int) ($plan['max_storage_mb'] ?? 2048),
            'modules' => $modules,
        ];
    }

    /** @param array<string, mixed> $plan */
    private function ensureDedicatedSubscription(int $companyId, array $plan): void
    {
        $planId = (int) $plan['id'];
        $subscription = new Subscription();
        $existing = $subscription->queryOne(
            'SELECT id FROM rateb_subscriptions WHERE company_id = :cid ORDER BY id ASC LIMIT 1',
            ['cid' => $companyId]
        );
        $payload = [
            'company_id' => $companyId,
            'plan_id' => $planId,
            'status' => 'active',
            'billing_cycle' => 'yearly',
            'amount' => (float) ($plan['price_yearly'] ?? $plan['price_monthly'] ?? 0),
            'starts_at' => date('Y-m-d'),
            'ends_at' => date('Y-m-d', strtotime('+1 year')),
            'auto_renew' => 1,
        ];
        if ($existing) {
            $subscription->update((int) $existing['id'], $payload);
            return;
        }
        $subscription->create($payload);
    }

    /** Ensure company status + subscription so company users can sign in on agency/dedicated ERP. */
    public function ensureCompanyLoginReady(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }

        $companyModel = new Company();
        $company = $companyModel->find($companyId);
        if (!$company) {
            return;
        }

        if ((string) ($company['status'] ?? '') !== 'active') {
            $companyModel->update($companyId, ['status' => 'active']);
        }

        $planId = (int) ($company['plan_id'] ?? 0);
        $plan = null;
        if ($planId > 0) {
            $plan = (new Plan())->find($planId);
        }
        if (!is_array($plan)) {
            $plan = $this->resolvePlan('professional');
            if ($planId < 1) {
                $companyModel->update($companyId, ['plan_id' => (int) $plan['id']]);
            }
        }

        $this->ensureDedicatedSubscription($companyId, $plan);
    }

    private function ensureDedicatedAdminUser(
        int $companyId,
        string $email,
        string $contactName,
        string $password
    ): int {
        $userModel = new User();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $existing = $userModel->findByEmail($email);

        if ($existing && (int) ($existing['company_id'] ?? 0) === $companyId) {
            $userModel->update((int) $existing['id'], [
                'name' => $contactName,
                'password' => $hash,
                'status' => 'active',
                'is_super_admin' => 0,
            ]);

            return (int) $existing['id'];
        }

        $userModel->query(
            'DELETE ur FROM rateb_user_roles ur
             INNER JOIN rateb_users u ON u.id = ur.user_id
             WHERE u.company_id = :cid AND u.is_super_admin = 0 AND u.email <> :email',
            ['cid' => $companyId, 'email' => $email]
        );
        $userModel->query(
            'DELETE FROM rateb_users WHERE company_id = :cid AND is_super_admin = 0 AND email <> :email',
            ['cid' => $companyId, 'email' => $email]
        );

        return (int) $userModel->create([
            'company_id' => $companyId,
            'name' => $contactName,
            'email' => $email,
            'password' => $hash,
            'phone' => '',
            'is_super_admin' => 0,
            'status' => 'active',
            'locale' => 'ar',
        ]);
    }

    private function removeCompanyTenant(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        $db = Database::connection();
        $userModel = new User();
        $userModel->query(
            'DELETE ur FROM rateb_user_roles ur
             INNER JOIN rateb_users u ON u.id = ur.user_id
             WHERE u.company_id = :cid',
            ['cid' => $companyId]
        );
        $userModel->query('DELETE FROM rateb_users WHERE company_id = :cid', ['cid' => $companyId]);
        (new Subscription())->query('DELETE FROM rateb_subscriptions WHERE company_id = :cid', ['cid' => $companyId]);
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        $db->prepare('DELETE FROM rateb_branches WHERE company_id = ?')->execute([$companyId]);
        $db->prepare('DELETE FROM rateb_companies WHERE id = ?')->execute([$companyId]);
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    /** @return array<string, mixed> */
    private function resolvePlan(string $planSlug): array
    {
        $slug = strtolower(trim($planSlug) !== '' ? trim($planSlug) : 'professional');
        $plan = (new Plan())->queryOne(
            'SELECT * FROM rateb_plans WHERE slug = :slug AND is_active = 1 LIMIT 1',
            ['slug' => $slug]
        );
        if ($plan === null) {
            $plan = (new Plan())->queryOne(
                'SELECT * FROM rateb_plans WHERE slug = :slug LIMIT 1',
                ['slug' => $slug]
            );
        }
        if ($plan === null && in_array($slug, ['starter', 'professional', 'enterprise'], true)) {
            // Ensure canonical marketing tiers exist, then retry (agency DBs can lag).
            try {
                (new MigrationService())->repairMarketingPlansCanonicalIfNeeded(Database::connection());
            } catch (\Throwable $e) {
                // continue to fallback below
            }
            $plan = (new Plan())->queryOne(
                'SELECT * FROM rateb_plans WHERE slug = :slug LIMIT 1',
                ['slug' => $slug]
            );
        }
        if ($plan === null) {
            throw new \RuntimeException('ERP plan not found: ' . $slug);
        }

        // Always prefer config/plan-tiers.php module bundles over stale DB JSON.
        $tierModules = PlanLimitService::modulesForSlug($slug);
        if ($tierModules !== []) {
            $plan['modules'] = json_encode($tierModules, JSON_UNESCAPED_UNICODE);
            $tier = PlanLimitService::tierForSlug($slug);
            if (is_array($tier)) {
                if (isset($tier['max_users'])) {
                    $plan['max_users'] = (int) $tier['max_users'];
                }
                if (isset($tier['max_storage_mb'])) {
                    $plan['max_storage_mb'] = (int) $tier['max_storage_mb'];
                }
                if (isset($tier['max_branches'])) {
                    $plan['max_branches'] = (int) $tier['max_branches'];
                }
            }
        }

        return $plan;
    }

    /**
     * Apply a SaaS plan slug onto an existing dedicated company (plan_id, modules, limits, subscription).
     *
     * @return array{company_id:int,plan_slug:string,plan_id:int,modules:list<string>}
     */
    public function applyPlanSlug(int $companyId, string $planSlug): array
    {
        if ($companyId < 1) {
            throw new \InvalidArgumentException('company id is required');
        }
        $plan = $this->resolvePlan($planSlug);
        $planId = (int) ($plan['id'] ?? 0);
        if ($planId < 1) {
            throw new \RuntimeException('ERP plan id missing for slug: ' . $planSlug);
        }
        $modulesJson = (string) ($plan['modules'] ?? '[]');
        $modules = PlanLimitService::filterKnownModules(
            json_decode($modulesJson, true) ?: PlanLimitService::modulesForSlug($planSlug)
        );
        if ($modules === []) {
            $modules = PlanLimitService::modulesForSlug($planSlug);
        }
        foreach (['dashboard', 'notifications'] as $implied) {
            if (!in_array($implied, $modules, true)) {
                $modules[] = $implied;
            }
        }
        $modules = array_values(array_unique($modules));
        $modulesJson = json_encode($modules, JSON_UNESCAPED_UNICODE);
        if ($modulesJson === false) {
            throw new \RuntimeException('Failed to encode company modules');
        }

        $companyModel = new Company();
        $company = $companyModel->find($companyId);
        if (!$company) {
            throw new \RuntimeException('Company not found: #' . $companyId);
        }
        $companyModel->update($companyId, [
            'plan_id' => $planId,
            'user_limit' => (int) ($plan['max_users'] ?? 25),
            'storage_limit_mb' => (int) ($plan['max_storage_mb'] ?? 2048),
            'modules' => $modulesJson,
            'status' => 'active',
        ]);
        if (function_exists('rateb_ops_company_request_state')) {
            $state = &rateb_ops_company_request_state();
            unset($state['rows'][$companyId], $state['exists'][$companyId]);
        }
        $this->ensureDedicatedSubscription($companyId, $plan);
        PlanLimitService::forgetCompanyLimits($companyId);

        return [
            'company_id' => $companyId,
            'plan_slug' => strtolower(trim($planSlug)),
            'plan_id' => $planId,
            'modules' => $modules,
        ];
    }

    private function dedupeDefaultWarehouse(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        try {
            $rows = (new \Rateb\App\Models\Warehouse())->query(
                'SELECT id FROM rateb_warehouses WHERE company_id = :cid AND code = :code ORDER BY id ASC',
                ['cid' => $companyId, 'code' => WarehouseService::MAIN_CODE]
            );
            if (count($rows) <= 1) {
                return;
            }
            $keepId = (int) ($rows[0]['id'] ?? 0);
            foreach (array_slice($rows, 1) as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0 && $id !== $keepId) {
                    (new \Rateb\App\Models\Warehouse())->delete($id);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function dedupeDefaultCategory(int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        try {
            $rows = (new \Rateb\App\Models\ProductCategory())->query(
                'SELECT id FROM rateb_product_categories WHERE company_id = :cid AND code = :code ORDER BY id ASC',
                ['cid' => $companyId, 'code' => 'GEN']
            );
            if (count($rows) <= 1) {
                return;
            }
            $keepId = (int) ($rows[0]['id'] ?? 0);
            foreach (array_slice($rows, 1) as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0 && $id !== $keepId) {
                    (new \Rateb\App\Models\ProductCategory())->delete($id);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    private function uniqueCompanySlug(string $name, ?int $exceptId = null): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/', '-', $name), '-'));
        if ($slug === '' || strlen($slug) < 2) {
            $slug = 'company';
        }
        $companyModel = new Company();
        $base = $slug;
        $i = 0;
        while (true) {
            $existing = $companyModel->findBySlug($slug);
            if ($existing === null || ($exceptId !== null && (int) $existing['id'] === $exceptId)) {
                break;
            }
            $i++;
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    private function assignCompanyFullAccessRole(int $userId, int $companyId): void
    {
        if ($userId < 1 || $companyId < 1) {
            return;
        }
        $authz = new AuthorizationService();
        $authz->ensureCompanyRoles($companyId);
        $role = $authz->findRoleBySlug('company-full-access', $companyId);
        if (!$role) {
            return;
        }
        $roleId = (int) $role['id'];
        $hasRole = (new User())->queryOne(
            'SELECT 1 FROM rateb_user_roles WHERE user_id = :uid AND role_id = :rid LIMIT 1',
            ['uid' => $userId, 'rid' => $roleId]
        );
        if (!$hasRole) {
            $authz->assignRole($userId, $roleId);
        }
    }
}
