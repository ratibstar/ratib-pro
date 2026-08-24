<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Route module loader — Phase AA.3 dashboard-minimal bootstrap.
 * GET /admin loads auth + dashboard only. Unknown paths → loadAll() (full set).
 */
final class RouteModuleLoader
{
    /** @var list<string> */
    private static array $lastLoadedIds = [];

    /** @var list<string> */
    private static array $lastLoadedFiles = [];

    /** @var 'all'|'selective'|'fallback_all' */
    private static string $lastMode = 'all';

    /**
     * Require every module in routes/manifest.php (full table).
     *
     * @return list<string> Loaded module ids
     */
    public static function loadAll(Router $router): array
    {
        self::$lastMode = 'all';

        return self::loadIds($router, null);
    }

    /**
     * Load modules for this request path. Unknown paths → full set.
     * Selective miss fallback is handled by the caller (index.php).
     *
     * @return list<string>
     */
    public static function loadForPath(Router $router, string $path): array
    {
        $selected = self::selectModuleIds($path);
        if ($selected === null) {
            self::$lastMode = 'all';

            return self::loadIds($router, null);
        }
        self::$lastMode = 'selective';

        return self::loadIds($router, $selected);
    }

    public static function markFallbackAll(): void
    {
        self::$lastMode = 'fallback_all';
    }

    /**
     * @param list<string>|null $onlyIds null = all modules in manifest order
     * @return list<string>
     */
    private static function loadIds(Router $router, ?array $onlyIds): array
    {
        self::$lastLoadedIds = [];
        self::$lastLoadedFiles = [];

        if (!defined('RATEB_ROOT')) {
            throw new \RuntimeException('RATEB_ROOT must be defined before RouteModuleLoader');
        }

        $manifestPath = RATEB_ROOT . '/routes/manifest.php';
        if (!is_file($manifestPath)) {
            throw new \RuntimeException('Route manifest missing: ' . $manifestPath);
        }

        /** @var list<array{id:string,file:string,optional?:bool}> $modules */
        $modules = require $manifestPath;
        if (!is_array($modules)) {
            throw new \RuntimeException('Route manifest must return a list of modules');
        }

        $allow = $onlyIds === null ? null : array_fill_keys($onlyIds, true);

        foreach ($modules as $module) {
            $id = (string) ($module['id'] ?? '');
            $rel = (string) ($module['file'] ?? '');
            $optional = !empty($module['optional']);
            if ($id === '' || $rel === '') {
                continue;
            }
            if ($allow !== null && !isset($allow[$id])) {
                continue;
            }
            $filePath = RATEB_ROOT . '/' . ltrim(str_replace('\\', '/', $rel), '/');
            if (!is_file($filePath)) {
                if ($optional) {
                    continue;
                }
                throw new \RuntimeException('Required route module file missing: ' . $filePath);
            }
            require $filePath;
            self::$lastLoadedIds[] = $id;
            self::$lastLoadedFiles[] = $rel;
        }

        return self::$lastLoadedIds;
    }

    /**
     * @return list<string>|null null ⇒ unknown path ⇒ caller should loadAll
     */
    public static function selectModuleIds(string $path): ?array
    {
        $path = '/' . trim(str_replace('\\', '/', $path), '/');
        if ($path !== '/') {
            $path = rtrim($path, '/') ?: '/';
        }

        $want = [];

        // Phase WEBSITE-02 — public website kernel: marketing (+ auth for locale/portal) only.
        if (defined('RATEB_WEBSITE_KERNEL') && RATEB_WEBSITE_KERNEL) {
            $want = ['auth' => true, 'marketing' => true];

            return self::orderWant($want);
        }

        // Dashboard minimal set — Phase AA.3 acceptance target.
        if (self::isDashboardPath($path)) {
            $want = ['auth' => true, 'dashboard' => true];

            return self::orderWant($want);
        }

        // In-app Help Center (all authenticated ERP users).
        if ($path === '/admin/help' || str_starts_with($path, '/admin/help/')) {
            $want = ['auth' => true, 'help' => true];

            return self::orderWant($want);
        }

        if (self::isAuthPath($path)) {
            $want = ['auth' => true];

            return self::orderWant($want);
        }

        if (str_starts_with($path, '/site')) {
            $want = ['auth' => true, 'marketing' => true];

            return self::orderWant($want);
        }

        // Auth owns barcode/qr login under /api/*; REST API module otherwise.
        if ($path === '/api/login-barcode-pair' || $path === '/api/qr-login') {
            $want = ['auth' => true];

            return self::orderWant($want);
        }
        if (str_starts_with($path, '/api/') || $path === '/api') {
            $want = ['auth' => true, 'api' => true, 'payment' => true];

            return self::orderWant($want);
        }

        if (str_starts_with($path, '/admin/cms')) {
            $want = ['auth' => true, 'cms' => true];

            return self::orderWant($want);
        }

        if (self::isPosUiPath($path)) {
            $want = ['auth' => true, 'pos' => true, 'pos_v2' => true];

            return self::orderWant($want);
        }

        if (str_starts_with($path, '/m/') || $path === '/m') {
            $want = ['auth' => true, 'guest_menu' => true];

            return self::orderWant($want);
        }

        if (str_starts_with($path, '/admin/subscription') || $path === '/admin/support') {
            $want = ['auth' => true, 'subscription' => true];

            return self::orderWant($want);
        }

        if (self::isPlatformPath($path)) {
            $want = ['auth' => true, 'platform' => true, 'payment' => true];

            return self::orderWant($want);
        }

        if (self::isOpsPath($path)) {
            $want = ['auth' => true, 'ops' => true];
            if (str_contains($path, '/guest-menu')) {
                $want['guest_menu'] = true;
            }
            if (str_contains($path, '/logistics')) {
                $want['logistics'] = true;
            }

            return self::orderWant($want);
        }

        // Unknown — AA.1 full load.
        return null;
    }

    private static function isDashboardPath(string $path): bool
    {
        return $path === '/admin'
            || $path === '/admin/executive-dashboard'
            || $path === '/admin/api/module-metrics'
            || $path === '/admin/api/support-ticket-alerts'
            || str_starts_with($path, '/admin/api/support-ticket-alerts/')
            || $path === '/admin/api/dashboard-charts';
    }

    private static function isAuthPath(string $path): bool
    {
        if (
            $path === '/'
            || $path === '/rateb-erp'
            || $path === '/favicon.ico'
            || $path === '/logout'
            || str_starts_with($path, '/login')
            || str_starts_with($path, '/password')
            || str_starts_with($path, '/locale')
            || str_starts_with($path, '/documents')
            || str_starts_with($path, '/barcode')
            || str_starts_with($path, '/scan')
        ) {
            return true;
        }

        return $path === '/admin/login' || $path === '/admin/logout';
    }

    private static function isPlatformPath(string $path): bool
    {
        if (!str_starts_with($path, '/admin/')) {
            return false;
        }
        // Ops namespace never uses platform module alone.
        if (str_starts_with($path, '/admin/ops/') || $path === '/admin/ops') {
            return false;
        }

        static $prefixes = [
            '/admin/companies',
            '/admin/agency-updates',
            '/admin/access-control',
            '/admin/accounting',
            '/admin/chart-of-accounts',
            '/admin/coa-tree',
            '/admin/journal-entries',
            '/admin/users',
            '/admin/roles',
            '/admin/permissions',
            '/admin/email-templates',
            '/admin/sms-templates',
            '/admin/support-tickets',
            '/admin/subscriptions',
            '/admin/plans',
            '/admin/payments',
            '/admin/payment-gateways',
            '/admin/invoices',
            '/admin/audit-logs',
            '/admin/accounting-control',
            '/admin/login-activity',
            '/admin/queue-monitor',
            '/admin/automation-health',
            '/admin/api/mail-dns-check',
            '/admin/settings',
            '/admin/mobile-apps',
            '/admin/agent-apps',
            '/admin/hr-mobile',
            '/admin/tools',
            '/admin/reports',
            '/admin/procurement',
            '/admin/inventory',
            '/admin/rfq',
            '/admin/stock-movements',
            '/admin/supplier-evaluations',
            '/admin/medical-devices',
            '/admin/notifications',
            '/admin/suppliers',
            '/admin/assets',
            '/admin/contracts',
            '/admin/workflows',
            '/admin/oversight',
        ];

        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private static function isOpsPath(string $path): bool
    {
        if ($path === '/admin/ops' || str_starts_with($path, '/admin/ops/')) {
            return true;
        }
        // Legacy /company/* redirects live in ops module.
        if (str_starts_with($path, '/company') || str_starts_with($path, '/accounting')) {
            return true;
        }
        // Tenant ERP roots that rateb_app_route keeps under /admin/{root} (not /admin/ops).
        if (!str_starts_with($path, '/admin/')) {
            return false;
        }
        static $opsRoots = [
            'hr', 'recruitment', 'crm', 'projects', 'eam', 'approvals', 'eproc', 'mfg',
            'hrm', 'payroll', 'qms', 'dms', 'bi', 'analytics', 'branch-dashboard',
            'branch-financial', 'branch-transfers', 'branches', 'profile', 'warehouses',
            'warehouse-transfers', 'product-categories', 'purchase-requests', 'purchase-orders',
            'quotations', 'tenders', 'inventory-batches', 'inventory-audits', 'inventory-forecast',
            'supplier-comms', 'supplier-classifications', 'supplier-kpi', 'contract-renewals',
            'asset-maintenance', 'asset-assignments', 'asset-depreciation', 'device-maintenance',
            'device-spare-parts', 'device-warranty', 'cost-centers', 'cash-vouchers',
            'fiscal-periods', 'bank-accounts', 'website',
        ];
        $rest = substr($path, strlen('/admin/'));
        $root = explode('/', $rest, 2)[0];

        return $root !== '' && in_array($root, $opsRoots, true);
    }

    private static function isPosUiPath(string $path): bool
    {
        if ($path === '/pos' || str_starts_with($path, '/pos/')) {
            return true;
        }
        if (preg_match('#^/admin/(?:ops/)?pos(?:/|$)#', $path) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param array<string,bool> $want
     * @return list<string>
     */
    private static function orderWant(array $want): array
    {
        $ordered = [];
        $manifest = require RATEB_ROOT . '/routes/manifest.php';
        foreach ($manifest as $module) {
            $id = (string) ($module['id'] ?? '');
            if ($id !== '' && isset($want[$id])) {
                $ordered[] = $id;
            }
        }

        return $ordered;
    }

    /** @return list<string> */
    public static function lastLoadedIds(): array
    {
        return self::$lastLoadedIds;
    }

    /** @return list<string> */
    public static function lastLoadedFiles(): array
    {
        return self::$lastLoadedFiles;
    }

    public static function lastMode(): string
    {
        return self::$lastMode;
    }
}
