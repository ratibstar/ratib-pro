<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Controllers\Admin;

use Rateb\PlatformCatalog\Application\Policies\PolicyGuardInterface;
use Rateb\PlatformCatalog\Application\Services\RetailArabicRepairService;
use Rateb\PlatformCatalog\Application\Support\AdminLocale;
use Rateb\PlatformCatalog\Application\Support\AdminNavigation;
use Rateb\PlatformCatalog\Core\View;

final class AdminPageController
{
    /** @var array<string, string> */
    private const PAGES = [
        'dashboard' => 'platform/admin/dashboard',
        'products' => 'platform/admin/products',
        'categories' => 'platform/admin/categories',
        'brands' => 'platform/admin/brands',
        'suppliers' => 'platform/admin/suppliers',
        'families' => 'platform/admin/families',
        'attributes' => 'platform/admin/attributes',
        'collections' => 'platform/admin/collections',
        'channels' => 'platform/admin/channels',
        'pricing' => 'platform/admin/pricing',
        'media' => 'platform/admin/media',
        'import_export' => 'platform/admin/import-export',
        'search' => 'platform/admin/search',
        'change_requests' => 'platform/admin/change-requests',
        'workflow' => 'platform/admin/workflow',
        'seo' => 'platform/admin/seo',
        'versions' => 'platform/admin/versions',
        'duplicates' => 'platform/admin/duplicates',
        'saved_filters' => 'platform/admin/saved-filters',
        'erp_sync' => 'platform/admin/erp-sync',
        'webhooks' => 'platform/admin/webhooks',
        'queue' => 'platform/admin/queue',
        'audit_logs' => 'platform/admin/audit-logs',
        'health' => 'platform/admin/health',
        'settings' => 'platform/admin/settings',
    ];

    public function __construct(
        private readonly PolicyGuardInterface $guard,
        private readonly AdminNavigation $navigation
    ) {
    }

    public function dashboard(): void
    {
        $this->render('dashboard');
    }

    public function products(): void
    {
        $repairNotice = null;
        $forceRepair = isset($_GET['repair_arabic']) && (string) $_GET['repair_arabic'] === '1';
        if ($this->guard->isAuthenticated()) {
            try {
                $result = (new RetailArabicRepairService())->repairIfNeeded($forceRepair);
                if ($result['repaired'] && ($result['message'] ?? '') === 'fixed') {
                    $repairNotice = $forceRepair
                        ? 'تم إصلاح الأسماء العربية. حدّث القائمة.'
                        : 'تم إصلاح الأسماء العربية التالفة تلقائياً. اضغط «تحديث».';
                } elseif ($result['repaired'] && ($result['message'] ?? '') === 'partial') {
                    $repairNotice = 'تم محاولة إصلاح الأسماء العربية وما زال بعضها تالفاً — أعد الضغط على «إصلاح النصوص العربية».';
                } elseif ($forceRepair) {
                    $repairNotice = 'لا توجد أسماء تالفة تحتاج إصلاحاً.';
                }
            } catch (\Throwable $e) {
                $repairNotice = 'تعذّر إصلاح الأسماء: ' . $e->getMessage();
            }
        }

        $this->render('products', ['repairNotice' => $repairNotice]);
    }

    public function categories(): void
    {
        $this->render('categories');
    }

    public function brands(): void
    {
        $this->render('brands');
    }

    public function suppliers(): void
    {
        $this->render('suppliers');
    }

    public function families(): void
    {
        $this->render('families');
    }

    public function attributes(): void
    {
        $this->render('attributes');
    }

    public function collections(): void
    {
        $this->render('collections');
    }

    public function channels(): void
    {
        $this->render('channels');
    }

    public function pricing(): void
    {
        $this->render('pricing');
    }

    public function media(): void
    {
        $this->render('media');
    }

    public function importExport(): void
    {
        $this->render('import_export');
    }

    public function search(): void
    {
        $this->render('search');
    }

    public function changeRequests(): void
    {
        $this->render('change_requests');
    }

    public function workflow(): void
    {
        $this->render('workflow');
    }

    public function seo(): void
    {
        $this->render('seo');
    }

    public function versions(): void
    {
        $this->render('versions');
    }

    public function duplicates(): void
    {
        $this->render('duplicates');
    }

    public function savedFilters(): void
    {
        $this->render('saved_filters');
    }

    public function erpSync(): void
    {
        $this->render('erp_sync');
    }

    public function webhooks(): void
    {
        $this->render('webhooks');
    }

    public function queue(): void
    {
        $this->render('queue');
    }

    public function auditLogs(): void
    {
        $this->render('audit_logs');
    }

    public function health(): void
    {
        $this->render('health');
    }

    public function settings(): void
    {
        $this->render('settings');
    }

    /** @param array<string, mixed> $extra */
    private function render(string $pageKey, array $extra = []): void
    {
        if (!$this->guard->isAuthenticated()) {
            catalog_maybe_redirect_erp_sso();
        }

        $locale = AdminLocale::resolve();
        $authenticated = $this->guard->isAuthenticated();
        $permissions = $this->navigation->currentPermissions();

        if ($authenticated && !$this->navigation->canAccessPage($pageKey)) {
            http_response_code(403);
            View::render('platform/admin/forbidden', [
                'title' => catalog__('admin_forbidden', $locale),
                'locale' => $locale,
                'dir' => AdminLocale::dir($locale),
                'pageKey' => 'forbidden',
                'navItems' => $this->navigation->visibleItems($locale),
                'permissions' => $permissions,
                'authenticated' => true,
            ], 'admin');

            return;
        }

        $view = self::PAGES[$pageKey] ?? 'platform/admin/dashboard';

        View::render($view, array_merge([
            'title' => catalog__('nav_' . $pageKey, $locale) . ' — ' . catalog__('admin_panel', $locale),
            'locale' => $locale,
            'dir' => AdminLocale::dir($locale),
            'pageKey' => $pageKey,
            'navItems' => $this->navigation->visibleItems($locale),
            'permissions' => $permissions,
            'authenticated' => $authenticated,
            'release' => defined('RATEB_PLATFORM_CATALOG_RELEASE') ? (string) RATEB_PLATFORM_CATALOG_RELEASE : '',
            'architecture' => defined('RATEB_PLATFORM_CATALOG_VERSION') ? (string) RATEB_PLATFORM_CATALOG_VERSION : '1.3.1',
        ], $extra), 'admin');
    }
}
