<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\LocalQrRenderer;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\GuestMenu\Services\GuestMenuCatalogService;
use Rateb\App\GuestMenu\Services\GuestMenuOrderService;
use Rateb\App\GuestMenu\Services\GuestMenuSettingsService;
use Rateb\App\GuestMenu\Support\GuestMenuView;

/** Public guest menu — no authentication. */
final class GuestMenuPublicController extends Controller
{
    public function menu(string $slug): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'HEAD') {
            if (!headers_sent()) {
                http_response_code(200);
                header('Content-Type: text/html; charset=UTF-8');
            }
            exit;
        }

        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('X-Rateb-Guest-Menu: 1');
        }

        $settings = (new GuestMenuSettingsService())->getEnabledByPublicSlug($slug);
        if ($settings === null) {
            $this->notFound();

            return;
        }

        $rtl = function_exists('rateb_is_rtl') ? rateb_is_rtl() : true;
        $title = $rtl
            ? trim((string) ($settings['title_ar'] ?? ''))
            : trim((string) ($settings['title_en'] ?? ''));
        if ($title === '') {
            $title = (string) ($settings['company_name'] ?? 'Menu');
        }

        $companyId = (int) $settings['company_id'];
        $catalogService = new GuestMenuCatalogService();
        // Resolve from DB settings (+ auto-detect/persist when still "all").
        $catalogPack = $catalogService->resolveCatalogPackForCompany(
            $companyId,
            (string) ($settings['catalog_pack'] ?? 'all')
        );
        $settings['catalog_pack'] = $catalogPack;
        $catalog = $catalogService->browse(
            $companyId,
            isset($settings['branch_id']) ? (int) $settings['branch_id'] : null,
            null,
            1,
            $rtl,
            $catalogPack,
        );

        GuestMenuView::render('public/menu', [
            'title' => $title,
            'settings' => $settings,
            'catalog' => $catalog,
            'rtl' => $rtl,
            'apiUrl' => $this->catalogApiUrl((string) $settings['public_slug']),
            'orderApiUrl' => $this->orderApiUrl((string) $settings['public_slug']),
            'orderMode' => (string) ($settings['mode'] ?? 'browse') === 'order',
        ], 'public');
    }

    public function catalogApi(string $slug): void
    {
        $settings = (new GuestMenuSettingsService())->getEnabledByPublicSlug($slug);
        if ($settings === null) {
            Response::json(['ok' => false, 'error' => 'not_found'], 404);

            return;
        }

        $rtl = isset($_GET['lang']) && $_GET['lang'] === 'en' ? false : (function_exists('rateb_is_rtl') ? rateb_is_rtl() : true);
        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== ''
            ? (int) $_GET['category_id']
            : null;
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $companyId = (int) $settings['company_id'];
        $catalogService = new GuestMenuCatalogService();
        $catalogPack = $catalogService->resolveCatalogPackForCompany(
            $companyId,
            (string) ($settings['catalog_pack'] ?? 'all')
        );

        $catalog = $catalogService->browse(
            $companyId,
            isset($settings['branch_id']) ? (int) $settings['branch_id'] : null,
            $categoryId,
            $page,
            $rtl,
            $catalogPack,
        );

        Response::json([
            'ok' => true,
            'data' => $catalog,
        ]);
    }

    public function submitOrder(string $slug): void
    {
        $settings = (new GuestMenuSettingsService())->getEnabledByPublicSlug($slug);
        if ($settings === null) {
            Response::json(['ok' => false, 'error' => 'not_found'], 404);

            return;
        }

        $raw = file_get_contents('php://input');
        $payload = is_string($raw) && $raw !== '' ? json_decode($raw, true) : $_POST;
        if (!is_array($payload)) {
            Response::json(['ok' => false, 'error' => 'invalid_json'], 400);

            return;
        }

        $result = (new GuestMenuOrderService())->submit($settings, $payload);
        if (!$result['ok']) {
            Response::json(['ok' => false, 'error' => (string) ($result['message'] ?? 'rejected')], 422);

            return;
        }

        Response::json([
            'ok' => true,
            'order_id' => (int) ($result['order_id'] ?? 0),
            'order_no' => (string) ($result['order_no'] ?? ''),
        ]);
    }

    public function qrPng(string $slug): void
    {
        $settings = (new GuestMenuSettingsService())->getEnabledByPublicSlug($slug);
        if ($settings === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Not found';
            exit;
        }

        $url = (new GuestMenuSettingsService())->publicMenuUrl((string) $settings['public_slug']);
        try {
            $png = LocalQrRenderer::png($url, 320);
        } catch (\Throwable $e) {
            $svg = LocalQrRenderer::svg($url, 320);
            if ($svg !== '') {
                header('Content-Type: image/svg+xml');
                header('Cache-Control: public, max-age=3600');
                echo $svg;
                exit;
            }
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'QR unavailable';
            exit;
        }

        if ($png === '') {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'QR unavailable';
            exit;
        }

        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=3600');
        echo $png;
        exit;
    }

    private function orderApiUrl(string $slug): string
    {
        return function_exists('rateb_public_url')
            ? rateb_public_url('m/' . rawurlencode($slug) . '/api/order')
            : '/m/' . rawurlencode($slug) . '/api/order';
    }

    private function catalogApiUrl(string $slug): string
    {
        $base = function_exists('rateb_public_url')
            ? rateb_public_url('m/' . rawurlencode($slug) . '/api/catalog')
            : '/m/' . rawurlencode($slug) . '/api/catalog';

        return $base;
    }
}
