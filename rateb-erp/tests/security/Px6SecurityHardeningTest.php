<?php
declare(strict_types=1);

final class Px6SecurityHardeningTest
{
    /** @var list<array{name:string,passed:bool,detail:string}> */
    private array $results = [];
    private string $root;

    public function __construct()
    {
        $this->root = dirname(__DIR__, 3);
    }

    /** @return list<array{name:string,passed:bool,detail:string}> */
    public function run(): array
    {
        $this->testDangerousEndpointsRemoved();
        $this->testDedicatedCheckoutPermission();
        $this->testPosInventoryBoundary();
        $this->testHrAccountingDecoupled();
        $this->testHrMutationSecurity();
        $this->testTenantIsolationDefaultsAndWrites();
        $this->testAccountingMigrationGuard();
        $this->testDestructiveAdminGuard();
        $this->testPosSyncAndApprovalTenantGuards();
        $this->testAuthoritativeTaxPricing();

        return $this->results;
    }

    private function testDangerousEndpointsRemoved(): void
    {
        $paths = [
            'config/setup-admin.php',
            'pages/setup-admin.php',
            'api/hr/fix-employees-only.php',
            'api/hr/debug-query.php',
            'api/hr/fix-all-prefixes.php',
            'api/hr/fix-id-ordering.php',
            'api/hr/fix-helper-prefixes.php',
            'api/hr/populate-existing-ids.php',
            'api/settings/reset-tables.php',
            'api/admin/add_bangladesh_countries.php',
            'api/accounting/setup-followup-messages.php',
            'api/help-center/seed-bengali-translations.php',
            'api/support-chat-test.php',
            'api/reports/test-connection.php',
            'api/help-center/test-categories.php',
            'api/accounting/test-apis.php',
            'api/accounting/test-entity-accounts.php',
            'config/drop_and_prepare_country.php',
            'config/drop_and_prepare_bangladesh.php',
            'config/run_option_a_setup.php',
            'config/clear_all_country_users.php',
            'config/migrations/reset_admin_password.php',
            'config/migrations/grant_admin_full_access.php',
            'config/migrations/run_multi_tenant_migration.php',
            'config/migrations/clear_control_admins_keep_admin.php',
            'config/migrations/check_rateb_pro_login.php',
            'pages/rateb-copy-from-repo.php',
            'pages/rateb-cms-rebrand-apply.php',
            'pages/check-login.php',
            'pages/rateb-reset-country-test-admin.php',
            'pages/rateb-fix-status.php',
            'pages/rateb-fix-perms.php',
            'pages/rateb-perms-check.php',
            'pages/rateb-sync-from-github.php',
            'pages/rateb-profile-deploy.php',
            'pages/rateb-purge-cache.php',
            'pages/rateb-mysql-probe.php',
            'pages/rateb-test-domain-probe.php',
            'pages/rateb-check-all-country-dbs.php',
            'pages/rateb-cms-db-check.php',
            'pages/rateb-enterprise-brand-audit.php',
            'pages/rateb-boot-trace.php',
            'pages/rateb-build-check.php',
            'pages/rateb-chrome-bust.php',
            'pages/rateb-live-chrome-check.php',
            'pages/rateb-nav-health.php',
            'pages/rateb-url-audit.php',
            'pages/rateb-rebrand-status.php',
            'pages/rateb-which-page.php',
            'pages/rateb-bootstrap-test-domain.php',
            'pages/test-config.php',
            'pages/test-error.php',
            'pages/tenant-test.php',
            'pages/deploy-root.php',
            'rateb-profile-check.php',
            'designed-status.php',
            'control-panel/fix-500.php',
        ];
        foreach ($paths as $path) {
            $this->record('removed endpoint: ' . $path, !is_file($this->root . '/' . $path), 'must not be deployable');
        }
        $remainingFixes = glob($this->root . '/api/hr/fix*.php') ?: [];
        $this->record('no HTTP HR fix endpoints', $remainingFixes === [], implode(', ', $remainingFixes));

        $migrate = $this->read('config/migrations/separate_control_panel_db/03_migrate_data.php');
        $this->record(
            'control DB migrate is CLI-only',
            str_contains($migrate, "PHP_SAPI !== 'cli'") && !str_contains($migrate, "9s%BpMr1]dfb"),
            'must reject HTTP and require env credentials'
        );
        $home = $this->read('pages/home.php');
        $this->record(
            'home has no hardcoded deploy sync key',
            !str_contains($home, 'rateb-deploy-sync-2026'),
            'shared deploy key must not be embeddable in marketing home'
        );
    }

    private function testDedicatedCheckoutPermission(): void
    {
        $v1 = $this->read('rateb-erp/modules/pos/app/Controllers/PosRegisterApiController.php');
        $v2 = $this->read('rateb-erp/modules/pos/app/Services/V2/Checkout/PosV2CheckoutAccessValidator.php');
        $method = $this->methodBody($v1, 'public function checkout');
        $complete = $this->methodBody($v2, 'public function assertCanComplete');
        $this->record(
            'V1 checkout requires pos.sale.complete',
            str_contains($method, "guardPosPermission('pos.sale.complete'"),
            'view/register permission must not complete sales'
        );
        $this->record(
            'V2 checkout rejects register-only permission',
            str_contains($complete, 'pos.sale.complete') && !str_contains($complete, 'pos.register'),
            'dedicated completion permission only'
        );
        $this->record(
            'checkout permission migration exists',
            is_file($this->root . '/rateb-erp/migrations/202_pos_checkout_permission.sql'),
            'permission must be deployable'
        );
    }

    private function testPosInventoryBoundary(): void
    {
        $dir = $this->root . '/rateb-erp/modules/pos/app';
        $violations = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (preg_match('/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+rateb_inventory/i', $source)) {
                $violations[] = $file->getPathname();
            }
        }
        $workflow = $this->read('rateb-erp/app/services/InventoryWorkflowService.php');
        $this->record('POS has no direct Inventory mutations', $violations === [], implode(', ', $violations));
        $this->record(
            'Inventory publishes serial transition API',
            str_contains($workflow, 'public function transitionSerialStatusInTransaction'),
            'Inventory remains sole mutation owner'
        );
    }

    private function testHrAccountingDecoupled(): void
    {
        $employees = $this->read('api/hr/employees.php');
        $this->record(
            'HR has no Accounting implementation coupling',
            !str_contains($employees, 'entity-account-helper.php') && !str_contains($employees, 'ensureEntityAccount('),
            'Accounting lifecycle is not an HR side effect'
        );
    }

    private function testHrMutationSecurity(): void
    {
        $guard = $this->read('api/hr/hr-connection.php');
        $employees = $this->read('api/hr/employees.php');
        $client = $this->read('js/hr.js');
        $this->record(
            'HR employee mutations are POST-only with CSRF',
            str_contains($employees, 'hr_api_require_employee_mutation_security($action)')
                && str_contains($guard, "!== 'POST'")
                && str_contains($guard, 'hash_equals($stored, $token)'),
            'GET, PUT and DELETE cannot mutate employee data'
        );
        $this->record(
            'HR client sends session CSRF token',
            str_contains($client, 'hrMutationHeaders')
                && str_contains($client, "'X-CSRF-Token'")
                && !preg_match("/employees\\.php\\?action=(?:update|delete)[^\\n]*\\n\\s*method:\\s*'(?:PUT|DELETE)'/", $client),
            'all employee mutations use the protected POST contract'
        );
    }

    private function testTenantIsolationDefaultsAndWrites(): void
    {
        $config = $this->read('includes/config.php');
        $loader = $this->read('includes/TenantLoader.php');
        $model = $this->read('rateb-erp/app/Core/Model.php');
        $this->record(
            'country tenant isolation enabled by default',
            str_contains($config, "define('MULTI_TENANT_ENABLED', true)")
                && str_contains($config, "require_once __DIR__ . '/bootstrap_multi_tenant.php'"),
            'bootstrap must run on every configured request'
        );
        $this->record(
            'tenant resolver keeps apex public context explicit',
            str_contains($loader, "'www.rateb.sa'")
                && str_contains($loader, "define('COUNTRY_ID', 0)")
                && str_contains($loader, 'Approved apex/development hosts'),
            'only explicit global hosts may continue without a country tenant'
        );
        $this->record(
            'model create rejects tenant mismatch',
            str_contains($model, 'Tenant mismatch for tenant-scoped create.'),
            'caller company_id cannot override TenantContext'
        );
        $this->record(
            'model update keeps tenant immutable',
            str_contains($model, 'Tenant mismatch for tenant-scoped update.')
                && str_contains($model, 'unset($data[$this->tenantColumn])'),
            'generic writes cannot reassign company ownership'
        );
    }

    private function testAccountingMigrationGuard(): void
    {
        $guard = $this->read('api/accounting/core/accounting-endpoint-guard.php');
        $this->record(
            'accounting migrations are POST-only',
            str_contains($guard, "!== 'POST'") && str_contains($guard, "header('Allow: POST')"),
            'GET cannot mutate accounting'
        );
        $this->record(
            'accounting migrations require CSRF',
            str_contains($guard, 'Invalid or missing CSRF token') && str_contains($guard, 'hash_equals'),
            'missing tokens fail closed'
        );
    }

    private function testDestructiveAdminGuard(): void
    {
        $source = $this->read('api/admin/clear_all_data.php');
        $this->record(
            'destructive admin reset is POST-only with CSRF',
            str_contains($source, "!== 'POST'")
                && str_contains($source, 'Invalid or missing CSRF token')
                && str_contains($source, 'hash_equals'),
            'authenticated GET requests must not delete data'
        );
    }

    private function testPosSyncAndApprovalTenantGuards(): void
    {
        $api = $this->read('rateb-erp/modules/pos/app/Controllers/PosApiController.php');
        $approval = $this->read('rateb-erp/modules/pos/app/Services/PosSupervisorApprovalService.php');
        $this->record(
            'POS sync checkout requires completion permission',
            str_contains($api, "'checkout' => 'pos.sale.complete'")
                && str_contains($api, "'complete_sale' => 'pos.sale.complete'")
                && str_contains($api, "\$scope['user_id'] = \$this->userId()"),
            'offline payload cannot bypass checkout permission or forge actor'
        );
        $this->record(
            'POS approvals are company scoped',
            substr_count($approval, 'company_id = :cid') >= 3,
            'grant, consume and request updates bind tenant'
        );
        $this->record(
            'POS approval token consumption is atomic',
            str_contains($approval, 'consumed_at IS NULL AND expires_at >= NOW()')
                && str_contains($approval, '$consume->rowCount() !== 1'),
            'a single-use token cannot succeed concurrently'
        );
    }

    private function testAuthoritativeTaxPricing(): void
    {
        $controller = $this->read('rateb-erp/modules/pos/app/Controllers/PosRegisterApiController.php');
        $replay = $this->read('rateb-erp/modules/pos/app/Services/PosOfflineReplayService.php');
        $js = $this->read('rateb-erp/public/assets/pos/js/pos-register-checkout.js');
        $this->record(
            'online pricing uses tenant tax settings',
            substr_count($controller, 'PosTaxSettingsService') >= 3
                && !str_contains($controller, "\$payload['tax_rate']"),
            'controller must not trust client tax rate'
        );
        $this->record(
            'offline replay uses authoritative tax settings',
            str_contains($replay, 'PosTaxSettingsService'),
            'queued tax values are not authoritative'
        );
        $this->record(
            'local pricing includes line discounts',
            str_contains($js, 'lineDiscountTotal') && str_contains($js, 'discount_percent'),
            'offline preview mirrors server pricing order'
        );
    }

    private function read(string $relative): string
    {
        $content = file_get_contents($this->root . '/' . $relative);
        if ($content === false) {
            throw new RuntimeException('Cannot read ' . $relative);
        }

        return $content;
    }

    private function methodBody(string $source, string $needle): string
    {
        $start = strpos($source, $needle);
        if ($start === false) {
            return '';
        }
        $next = strpos($source, "\n    public function ", $start + strlen($needle));

        return substr($source, $start, $next === false ? null : $next - $start);
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
    }
}
