<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\Company;
use Rateb\App\Models\Permission;
use Rateb\App\Models\Role;
use Rateb\App\Models\User;
use Throwable;

/**
 * Demo-host only: ensure a normal company user can see locked CRM checkout.
 * Never creates invoices, never calls payments, never activates CRM.
 */
final class ModuleAddonDemoPreviewService
{
    public const DEMO_EMAIL = 'addon-preview@admin.rateb.sa';
    public const DEMO_NAME = 'Module Add-on Preview';
    public const ROLE_SLUG = 'addon-preview-crm';

    /** @var list<string> */
    private const ROLE_PERMISSIONS = ['dashboard.view', 'crm.view'];

    /**
     * @param list<string> $modules
     * @return list<string>
     */
    public static function modulesWithoutSlug(array $modules, string $slug): array
    {
        $slug = strtolower(trim($slug));
        $kept = [];
        foreach ($modules as $mod) {
            $mod = strtolower(trim((string) $mod));
            if ($mod === '' || $mod === $slug) {
                continue;
            }
            if (!in_array($mod, $kept, true)) {
                $kept[] = $mod;
            }
        }
        foreach (['dashboard', 'notifications'] as $implied) {
            if (!in_array($implied, $kept, true)) {
                $kept[] = $implied;
            }
        }

        return $kept;
    }

    /**
     * @return array{ok:bool,code:string,email?:string,user_id?:int,company_id?:int,company_name?:string,created?:bool,crm_stripped?:bool,user_limit_bumped?:bool}
     */
    public function ensureDemoUser(string $plainPassword): array
    {
        $addons = new ModuleAddonService();
        if (!$addons->previewDemoHostAllowed()) {
            return ['ok' => false, 'code' => 'not_demo_host'];
        }
        if (!$addons->isEnabled()) {
            return ['ok' => false, 'code' => 'disabled'];
        }
        $plainPassword = (string) $plainPassword;
        if (strlen($plainPassword) < 8) {
            return ['ok' => false, 'code' => 'weak_password'];
        }

        $companyId = DedicatedTenantPolicy::primaryCompanyId();
        if ($companyId < 1) {
            return ['ok' => false, 'code' => 'no_company'];
        }
        $company = (new Company())->find($companyId);
        if (!is_array($company)) {
            return ['ok' => false, 'code' => 'no_company'];
        }

        $limits = (new PlanLimitService())->getLimits($companyId);
        $current = is_array($limits['modules'] ?? null) ? $limits['modules'] : [];
        $hadCrm = in_array('crm', array_map('strval', $current), true);
        $withoutCrm = self::modulesWithoutSlug($current, 'crm');
        if (!(new Company())->updateModules($companyId, $withoutCrm)) {
            return ['ok' => false, 'code' => 'modules_write_failed'];
        }
        PlanLimitService::forgetCompanyLimits($companyId);
        if (function_exists('rateb_ops_company_request_state')) {
            $state = &rateb_ops_company_request_state();
            unset($state['rows'][$companyId], $state['exists'][$companyId]);
        }

        $roleId = $this->ensurePreviewRole($companyId);
        if ($roleId < 1) {
            return ['ok' => false, 'code' => 'role_failed'];
        }

        $users = new User();
        $existing = $users->findByEmail(self::DEMO_EMAIL);
        $created = false;
        $limitBumped = false;
        if ($existing === null) {
            if (!(new PlanLimitService())->canAddUser($companyId)) {
                $nextLimit = max(1, (int) ($company['user_limit'] ?? 0), (int) ($limits['user_limit'] ?? 0)) + 1;
                (new Company())->update($companyId, ['user_limit' => $nextLimit]);
                PlanLimitService::forgetCompanyLimits($companyId);
                if (function_exists('rateb_ops_company_request_state')) {
                    $state = &rateb_ops_company_request_state();
                    unset($state['rows'][$companyId], $state['exists'][$companyId]);
                }
                $limitBumped = true;
            }
            $row = [
                'company_id' => $companyId,
                'name' => self::DEMO_NAME,
                'email' => self::DEMO_EMAIL,
                'is_super_admin' => 0,
                'status' => 'active',
                'locale' => 'ar',
            ];
            $users->applyPassword($row, $plainPassword);
            $userId = (int) $users->create($row);
            $created = $userId > 0;
        } else {
            $userId = (int) ($existing['id'] ?? 0);
            $row = [
                'company_id' => $companyId,
                'name' => self::DEMO_NAME,
                'email' => self::DEMO_EMAIL,
                'is_super_admin' => 0,
                'status' => 'active',
                'locale' => 'ar',
            ];
            $users->applyPassword($row, $plainPassword);
            $users->update($userId, $row);
        }
        if ($userId < 1) {
            return ['ok' => false, 'code' => 'user_failed'];
        }

        (new AuthorizationService())->syncUserRoles($userId, [$roleId]);
        try {
            (new AccountLockoutService())->clearLock($userId);
        } catch (Throwable $e) {
            // Preview login must not fail because lockout table is missing.
        }

        (new AuditService())->log('create', 'module_addon_demo_preview', $userId, [
            'email' => self::DEMO_EMAIL,
            'company_id' => $companyId,
            'crm_stripped' => $hadCrm,
            'created' => $created,
        ]);

        return [
            'ok' => true,
            'code' => 'ok',
            'email' => self::DEMO_EMAIL,
            'user_id' => $userId,
            'company_id' => $companyId,
            'company_name' => (string) ($company['name'] ?? ''),
            'created' => $created,
            'crm_stripped' => $hadCrm,
            'user_limit_bumped' => $limitBumped,
        ];
    }

    private function ensurePreviewRole(int $companyId): int
    {
        $authz = new AuthorizationService();
        $existing = $authz->findRoleBySlug(self::ROLE_SLUG, $companyId);
        $roleId = (int) ($existing['id'] ?? 0);
        if ($roleId < 1) {
            $roleId = (int) (new Role())->create([
                'company_id' => $companyId,
                'name' => 'Add-on preview (CRM view)',
                'slug' => self::ROLE_SLUG,
                'description' => 'Demo-only role: dashboard + CRM view for locked checkout preview',
                'is_system' => 0,
            ]);
        }
        if ($roleId < 1) {
            return 0;
        }

        $db = Database::connection();
        $insert = $db->prepare(
            'INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id) VALUES (:rid, :pid)'
        );
        foreach (self::ROLE_PERMISSIONS as $slug) {
            $perm = (new Permission())->queryOne(
                'SELECT id FROM rateb_permissions WHERE slug = :slug LIMIT 1',
                ['slug' => $slug]
            );
            $pid = (int) ($perm['id'] ?? 0);
            if ($pid < 1) {
                return 0;
            }
            $insert->execute(['rid' => $roleId, 'pid' => $pid]);
        }

        return $roleId;
    }
}
