<?php
declare(strict_types=1);

/**
 * Static guards for Super Admin / agency access-control hardening.
 */
$root = dirname(__DIR__);

function acfix_assert(bool $ok, string $msg): void
{
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}

$users = (string) file_get_contents($root . '/app/controllers/Admin/AdminControllers.php');
$access = (string) file_get_contents($root . '/app/controllers/Admin/AccountingControllers.php');
$authz = (string) file_get_contents($root . '/app/services/AuthorizationService.php');
$app = (string) file_get_contents($root . '/config/app.php');
$auth = (string) file_get_contents($root . '/app/Core/Auth.php');

acfix_assert(str_contains($users, 'Non-SA actors can never set is_super_admin'), 'users collectData blocks SA escalation');
acfix_assert(str_contains($users, 'Only platform Super Admin may see/edit the SA flag'), 'SA checkbox hidden for non-SA');
acfix_assert(str_contains($users, 'Never allow non-SA to manage Super Admin accounts'), 'userInScope blocks SA targets for non-SA');
acfix_assert(str_contains($users, 'rateb_app_route(\'access-control\')'), 'staff home uses ops access-control route');
acfix_assert(str_contains($users, 'createEnabled && $rbac[\'scope\'] === \'company\' && $companyId > 0'), 'roles create requires real company id');
acfix_assert(str_contains($users, 'filterAssignablePermissionIds'), 'roles save filters permission ids');
acfix_assert(str_contains($users, 'assertTenantRoleWrite'), 'roles create guards tenant write');
acfix_assert(str_contains($access, 'assertRbacReadable'), 'access control gates platform scope');
acfix_assert(str_contains($authz, 'function filterAssignablePermissionIds'), 'authz exposes permission filter');
acfix_assert(str_contains($authz, 'assignablePermissionIdSet'), 'authz builds assignable permission set');
acfix_assert(str_contains($app, 'never arbitrary tenants'), 'ops company adopt restricted for non-SA');
acfix_assert(!str_contains($app, 'REMOVED_BLOCK_MARKER'), 'ops company cleanup has no leftover marker');
acfix_assert(str_contains($auth, 'rateb_agency_access_perms_synced_v3'), 'login clears synced_v3 agency key');
acfix_assert(str_contains($auth, 'rateb_saas_tenant_access_perms_synced_v3'), 'login clears synced_v3 saas key');

echo "\nAccess-control hardening tests: all passed\n";
