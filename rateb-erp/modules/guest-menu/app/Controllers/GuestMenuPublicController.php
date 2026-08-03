<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\LocalQrRenderer;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\GuestMenu\Services\GuestMenuCatalogService;
use Rateb\App\GuestMenu\Services\GuestMenuSettingsService;
use Rateb\App\GuestMenu\Support\GuestMenuView;

/** Public guest menu — no authentication. */
final class GuestMenuPublicController extends Controller
{
    public function menu(string $slug): void
    {
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

        $catalog = (new GuestMenuCatalogService())->browse(
            (int) $settings['company_id'],
            isset($settings['branch_id']) ? (int) $settings['branch_id'] : null,
            null,
            1,
            $rtl,
        );

        GuestMenuView::render('public/menu', [
            'title' => $title,
            'settings' => $settings,
            'catalog' => $catalog,
            'rtl' => $rtl,
            'apiUrl' => $this->catalogApiUrl((string) $settings['public_slug']),
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

        $catalog = (new GuestMenuCatalogService())->browse(
            (int) $settings['company_id'],
            isset($settings['branch_id']) ? (int) $settings['branch_id'] : null,
            $categoryId,
            $page,
            $rtl,
        );

        Response::json([
            'ok' => true,
            'data' => $catalog,
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

    private function catalogApiUrl(string $slug): string
    {
        $base = function_exists('rateb_public_url')
            ? rateb_public_url('m/' . rawurlencode($slug) . '/api/catalog')
            : '/m/' . rawurlencode($slug) . '/api/catalog';

        return $base;
    }
}
