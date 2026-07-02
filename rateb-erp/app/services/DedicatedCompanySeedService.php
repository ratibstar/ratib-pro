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
        $email = self::DEFAULT_EMAIL;
        $contactName = self::DEFAULT_LOGIN;
        $password = self::DEFAULT_PASSWORD;

        $plan = $this->resolvePlan($planSlug);
        $planId = (int) $plan['id'];
        $modules = $plan['modules'] ?? json_encode(PlanLimitService::defaultModules(), JSON_UNESCAPED_UNICODE);

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $companyId = $this->resolveDedicatedCompanyId($companyName, $email, $plan, $modules);
            $this->ensureDedicatedSubscription($companyId, $plan);
            $userId = $this->ensureDedicatedAdminUser($companyId, $email, $contactName, $password);

            $roleRow = (new User())->queryOne(
                "SELECT id FROM rateb_roles WHERE slug = 'company-full-access' LIMIT 1"
            );
            if ($roleRow) {
                $hasRole = (new User())->queryOne(
                    'SELECT 1 FROM rateb_user_roles WHERE user_id = :uid AND role_id = :rid LIMIT 1',
                    ['uid' => $userId, 'rid' => (int) $roleRow['id']]
                );
                if (!$hasRole) {
                    (new AuthorizationService())->assignRole($userId, (int) $roleRow['id']);
                }
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

        $userId = $this->ensureDedicatedAdminUser(
            $companyId,
            self::DEFAULT_EMAIL,
            self::DEFAULT_LOGIN,
            self::DEFAULT_PASSWORD
        );

        $roleRow = (new User())->queryOne(
            "SELECT id FROM rateb_roles WHERE slug = 'company-full-access' LIMIT 1"
        );
        if ($roleRow) {
            $hasRole = (new User())->queryOne(
                'SELECT 1 FROM rateb_user_roles WHERE user_id = :uid AND role_id = :rid LIMIT 1',
                ['uid' => $userId, 'rid' => (int) $roleRow['id']]
            );
            if (!$hasRole) {
                (new AuthorizationService())->assignRole($userId, (int) $roleRow['id']);
            }
        }

        (new BarcodeLoginService())->ensureUserBarcode($userId);

        return [
            'company_id' => $companyId,
            'user_id' => $userId,
            'admin_username' => self::DEFAULT_LOGIN,
            'admin_email' => self::DEFAULT_EMAIL,
            'admin_password' => self::DEFAULT_PASSWORD,
        ];
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
            $roleRow = $userModel->queryOne(
                "SELECT id FROM rateb_roles WHERE slug = 'company-full-access' LIMIT 1"
            );
            $roleId = $roleRow ? (int) $roleRow['id'] : 0;
            $auth = new AuthorizationService();
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
}
