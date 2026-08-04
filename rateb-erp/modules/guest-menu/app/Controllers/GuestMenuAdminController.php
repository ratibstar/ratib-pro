<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Database;
use Rateb\App\Core\LocalQrRenderer;
use Rateb\App\Core\SessionManager;
use Rateb\App\GuestMenu\Services\GuestMenuCatalogSeedService;
use Rateb\App\GuestMenu\Services\GuestMenuCatalogService;
use Rateb\App\GuestMenu\Services\GuestMenuMenuRepairService;
use Rateb\App\GuestMenu\Services\GuestMenuOrderService;
use Rateb\App\GuestMenu\Services\GuestMenuPlatformCatalogSeedRunner;
use Rateb\App\GuestMenu\Services\GuestMenuPlatformImportService;
use Rateb\App\GuestMenu\Services\GuestMenuSettingsService;
use Rateb\App\Services\BranchService;
use Rateb\App\GuestMenu\Support\GuestMenuView;
use PDO;

/** Admin settings for guest QR menu. */
final class GuestMenuAdminController extends Controller
{
    public function index(): void
    {
        $this->guardView();
        $companyId = $this->companyId();
        $settingsService = new GuestMenuSettingsService();

        try {
            $settings = $settingsService->ensureForCompany($companyId);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'rateb_guest_menu_settings') && str_contains($msg, '1146')) {
                SessionManager::flash('error', __('guest_menu_schema_missing'));
            } else {
                SessionManager::flash('error', $msg);
            }
            $settings = [
                'company_id' => $companyId,
                'is_enabled' => 0,
                'public_slug' => '',
                'mode' => 'browse',
            ];
        }

        $publicUrl = $settingsService->publicMenuUrl((string) ($settings['public_slug'] ?? ''));
        $slug = (string) ($settings['public_slug'] ?? '');
        $qrPreviewSrc = (!empty($settings['is_enabled']) && $slug !== '')
            ? $settingsService->qrPreviewSrc($slug)
            : '';
        $menuBranchId = isset($settings['branch_id']) ? (int) $settings['branch_id'] : null;
        if ($menuBranchId !== null && $menuBranchId < 1) {
            $menuBranchId = null;
        }
        $catalogStats = (new GuestMenuCatalogService())->statsForCompany($companyId, $menuBranchId);
        $inventoryUrl = rateb_app_url('inventory');
        GuestMenuView::render('admin/settings', [
            'title' => __('guest_menu_settings'),
            'settings' => $settings,
            'publicUrl' => $publicUrl,
            'qrPreviewSrc' => $qrPreviewSrc,
            'qrDownloadUrl' => rateb_app_url('guest-menu/qr.png?download=1'),
            'catalogStats' => $catalogStats,
            'inventoryUrl' => $inventoryUrl,
            'platformCatalogUrl' => function_exists('rateb_platform_catalog_entry_url')
                ? rateb_platform_catalog_entry_url()
                : (function_exists('rateb_platform_catalog_admin_url') ? rateb_platform_catalog_admin_url() : ''),
            'platformCatalogEnabled' => function_exists('rateb_platform_catalog_nav_enabled')
                && rateb_platform_catalog_nav_enabled(),
            'catalogPacks' => \Rateb\App\GuestMenu\Services\PlatformRetailCatalogSeedData::industryPacks(),
            'branches' => $this->branchesForCompany($companyId),
            'csrf' => Csrf::token(),
        ]);
    }

    public function orders(): void
    {
        $this->guardView();
        $companyId = $this->companyId();
        $orders = (new GuestMenuOrderService())->listForCompany($companyId, 100);
        GuestMenuView::render('admin/orders', [
            'title' => __('guest_menu_orders_title'),
            'orders' => $orders,
            'settingsUrl' => rateb_app_url('guest-menu'),
            'csrf' => Csrf::token(),
        ]);
    }

    public function orderStatus(int $orderId): void
    {
        $this->guardManage();
        if (!$this->requireCsrfOrStay()) {
            return;
        }
        $status = (string) $this->input('status', 'pending');
        $ok = (new GuestMenuOrderService())->updateStatus($this->companyId(), $orderId, $status);
        SessionManager::flash($ok ? 'success' : 'error', $ok ? __('guest_menu_order_updated') : __('guest_menu_order_update_failed'));
        $this->redirect(rateb_app_url('guest-menu/orders'));
    }

    public function importCatalog(): void
    {
        $this->guardManage();
        if (!$this->requireCsrfOrStay()) {
            return;
        }

        $companyId = $this->companyId();
        $pack = trim((string) ($_POST['catalog_pack'] ?? 'all'));
        $replace = !empty($_POST['replace_imported']);
        if ($replace) {
            $deleted = (new GuestMenuPlatformImportService())->deleteImportedForCompany($companyId);
            if ($deleted > 0) {
                SessionManager::flash('success', __('guest_menu_delete_imported_done', [
                    'count' => (string) $deleted,
                ]));
            }
        }

        // Seed-authoritative repair first (rewrites ?? from PHP UTF-8), then pack import.
        $repair = (new GuestMenuMenuRepairService())->repairCompany($companyId, $pack);
        if (!$repair['ok']) {
            SessionManager::flash(
                'error',
                __('guest_menu_menu_repair_failed') . ': ' . (string) ($repair['message'] ?? '')
            );
            $this->redirectGuestMenu();

            return;
        }

        $result = (new GuestMenuPlatformImportService())->importToCompany($companyId, 300, $pack);
        if (!$result['ok']) {
            SessionManager::flash('error', __('guest_menu_import_failed') . ': ' . (string) ($result['message'] ?? ''));
        } else {
            $msg = __('guest_menu_menu_repair_done', [
                'repaired' => (string) ($repair['repaired'] ?? 0),
                'imported' => (string) (($result['imported'] ?? 0) + (int) ($repair['imported'] ?? 0)),
            ]);
            $msg .= ' — ' . __('guest_menu_import_done', [
                'imported' => (string) ($result['imported'] ?? 0),
                'skipped' => (string) ($result['skipped'] ?? 0),
                'updated' => (string) ($result['updated'] ?? 0),
            ]);
            SessionManager::flash('success', $msg);
        }
        $this->redirectGuestMenu();
    }

    /** One-shot: إصلاح أسماء المنيو الآن — seed + rewrite all RC-* from UTF-8 PHP. */
    public function repairMenuNames(): void
    {
        $this->guardManage();
        if (!$this->requireCsrfOrStay()) {
            return;
        }
        $companyId = $this->companyId();
        $pack = trim((string) ($_POST['catalog_pack'] ?? 'all'));
        $result = (new GuestMenuMenuRepairService())->repairCompany($companyId, $pack);
        if (!$result['ok']) {
            SessionManager::flash(
                'error',
                __('guest_menu_menu_repair_failed') . ': ' . (string) ($result['message'] ?? '')
            );
        } else {
            SessionManager::flash('success', __('guest_menu_menu_repair_done', [
                'repaired' => (string) ($result['repaired'] ?? 0),
                'imported' => (string) ($result['imported'] ?? 0),
            ]));
        }
        $this->redirectGuestMenu();
    }

    public function deleteImportedCatalog(): void
    {
        $this->guardManage();
        if (!$this->requireCsrfOrStay()) {
            return;
        }
        $deleted = (new GuestMenuPlatformImportService())->deleteImportedForCompany($this->companyId());
        SessionManager::flash('success', __('guest_menu_delete_imported_done', [
            'count' => (string) $deleted,
        ]));
        $this->redirectGuestMenu();
    }

    public function exportCatalog(): void
    {
        $this->guardView();
        $companyId = $this->companyId();
        if ($companyId < 1) {
            SessionManager::flash('error', __('select_company_ops'));
            $this->redirectGuestMenu();

            return;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT i.sku, i.item_name,
                    COALESCE(NULLIF(c.name_ar, \'\'), NULLIF(c.name, \'\'), NULLIF(i.category, \'\'), \'\') AS category_name,
                    i.unit_cost AS price
             FROM rateb_inventory i
             LEFT JOIN rateb_product_categories c ON c.id = i.category_id
             WHERE i.company_id = :cid
               AND (i.status IS NULL OR i.status = \'active\')
             ORDER BY category_name ASC, i.item_name ASC'
        );
        $stmt->execute(['cid' => $companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $filename = 'guest-menu-inventory-c' . $companyId . '-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        if ($out === false) {
            http_response_code(500);
            exit;
        }
        fputcsv($out, ['sku', 'name', 'category', 'price']);
        foreach ($rows as $row) {
            fputcsv($out, [
                (string) ($row['sku'] ?? ''),
                (string) ($row['item_name'] ?? ''),
                (string) ($row['category_name'] ?? ''),
                number_format((float) ($row['price'] ?? 0), 2, '.', ''),
            ]);
        }
        fclose($out);
        exit;
    }

    public function seedPlatformCatalog(): void
    {
        $this->guardManage();
        if (!$this->requireCsrfOrStay()) {
            return;
        }
        if (!function_exists('rateb_is_super_admin') || !rateb_is_super_admin()) {
            SessionManager::flash('error', __('guest_menu_platform_seed_forbidden'));
            $this->redirectGuestMenu();

            return;
        }
        $result = (new GuestMenuPlatformCatalogSeedRunner())->run();
        if (!$result['ok']) {
            SessionManager::flash(
                'error',
                __('guest_menu_platform_seed_failed') . ': ' . (string) ($result['message'] ?? '')
            );
        } else {
            SessionManager::flash('success', __('guest_menu_platform_seed_done', [
                'count' => (string) ($result['product_count'] ?? 0),
            ]));
        }
        $this->redirectGuestMenu();
    }

    public function seedDemo(): void
    {
        $this->guardManage();
        if (!$this->requireCsrfOrStay()) {
            return;
        }
        $result = (new GuestMenuCatalogSeedService())->seedDemoForCompany($this->companyId());
        if (!$result['ok']) {
            SessionManager::flash('error', __('guest_menu_seed_failed') . ': ' . (string) ($result['message'] ?? ''));
        } else {
            SessionManager::flash('success', __('guest_menu_seed_done', [
                'count' => (string) ($result['created'] ?? 0),
            ]));
        }
        $this->redirectGuestMenu();
    }

    /**
     * CSRF failure must NEVER destroy the session or force logout — regenerate token, flash, stay.
     * (Logout on Import was caused by SessionManager clearing alternate cookies mid-session.)
     */
    private function requireCsrfOrStay(): bool
    {
        if ($this->validateCsrf()) {
            return true;
        }
        Csrf::regenerate();
        SessionManager::flash('error', __('csrf_invalid'));
        $this->redirectGuestMenu();

        return false;
    }

    private function redirectGuestMenu(): void
    {
        // Single company_id only — never append a second ?company_id=.
        $url = rateb_app_url('guest-menu');
        $cid = 0;
        if (function_exists('rateb_resolve_ops_company_id')) {
            $cid = (int) rateb_resolve_ops_company_id();
        }
        if ($cid > 0 && function_exists('rateb_url_set_query_param')) {
            $url = rateb_url_set_query_param($url, 'company_id', (string) $cid);
        }
        $this->redirect($url);
    }

    /** @return list<array<string, mixed>> */
    private function branchesForCompany(int $companyId): array
    {
        if ($companyId < 1 || !class_exists(BranchService::class)) {
            return [];
        }
        try {
            return (new BranchService())->listForCompany($companyId);
        } catch (\Throwable) {
            return [];
        }
    }

    public function save(): void
    {
        $this->guardManage();
        if (!$this->requireCsrfOrStay()) {
            return;
        }

        $companyId = $this->companyId();
        $settingsService = new GuestMenuSettingsService();

        try {
            $settingsService->save($companyId, [
                'is_enabled' => $this->input('is_enabled'),
                'public_slug' => $this->input('public_slug'),
                'mode' => $this->input('mode', 'browse'),
                'branch_id' => $this->input('branch_id'),
                'title_ar' => $this->input('title_ar'),
                'title_en' => $this->input('title_en'),
                'welcome_message' => $this->input('welcome_message'),
            ]);
            SessionManager::flash('success', __('guest_menu_saved'));
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage() === 'slug_taken'
                ? __('guest_menu_public_slug') . ' — taken'
                : __('guest_menu_public_slug') . ' — invalid';
            SessionManager::flash('error', $msg);
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }

        $this->redirectGuestMenu();
    }

    public function qrPng(): void
    {
        $this->guardView();
        $companyId = $this->companyId();
        $settings = (new GuestMenuSettingsService())->ensureForCompany($companyId);
        $slug = (string) ($settings['public_slug'] ?? '');
        if ($slug === '') {
            http_response_code(404);
            exit;
        }

        $url = (new GuestMenuSettingsService())->publicMenuUrl($slug);
        $download = (string) ($_GET['download'] ?? '') === '1';
        try {
            $png = LocalQrRenderer::png($url, 400);
        } catch (\Throwable $e) {
            error_log('GuestMenuAdminController QR: ' . $e->getMessage());
            $svg = LocalQrRenderer::svg($url, 400);
            if ($svg !== '') {
                header('Content-Type: image/svg+xml');
                header('Cache-Control: no-store, no-cache, must-revalidate');
                header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="guest-menu-qr.svg"');
                echo $svg;
                exit;
            }
            http_response_code(500);
            exit;
        }

        if ($png === '') {
            http_response_code(500);
            exit;
        }

        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="guest-menu-qr.png"');
        header('Content-Length: ' . (string) strlen($png));
        echo $png;
        exit;
    }

    private function companyId(): int
    {
        if (function_exists('rateb_require_ops_company')) {
            return rateb_require_ops_company();
        }

        return (int) (SessionManager::get('rateb_company_id') ?? 0);
    }

    private function guardView(): void
    {
        if (function_exists('rateb_can_view_entity') && rateb_can_view_entity('guest-menu')) {
            return;
        }
        SessionManager::flash('error', __('access_denied'));
        $this->redirect(rateb_app_url('pos/dashboard'));
    }

    private function guardManage(): void
    {
        if (function_exists('rateb_can_manage_entity') && rateb_can_manage_entity('guest-menu')) {
            return;
        }
        SessionManager::flash('error', __('access_denied'));
        $this->redirect(rateb_app_url('guest-menu'));
    }
}
