<?php
declare(strict_types=1);

namespace Rateb\App\GuestMenu\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\LocalQrRenderer;
use Rateb\App\Core\SessionManager;
use Rateb\App\GuestMenu\Services\GuestMenuSettingsService;
use Rateb\App\GuestMenu\Support\GuestMenuView;

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
        GuestMenuView::render('admin/settings', [
            'title' => __('guest_menu_settings'),
            'settings' => $settings,
            'publicUrl' => $publicUrl,
            'qrPreviewSrc' => $qrPreviewSrc,
            'qrDownloadUrl' => rateb_app_url('guest-menu/qr.png?download=1'),
            'csrf' => Csrf::token(),
        ]);
    }

    public function save(): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('csrf_invalid'));
            $this->redirect(rateb_app_url('guest-menu'));

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

        $this->redirect(rateb_app_url('guest-menu'));
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
