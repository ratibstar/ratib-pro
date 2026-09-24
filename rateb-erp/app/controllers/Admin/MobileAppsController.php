<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\Company;
use Rateb\App\Services\HrMobileApkService;
use Rateb\App\Services\MobileAppConfigService;

/**
 * Platform Mobile Apps Management — tenant enablement + white-label config.
 * Keeps HR Mobile Console (launcher) separate under /admin/hr-mobile.
 */
final class MobileAppsController extends Controller
{
    public function index(): void
    {
        if (!$this->canView()) {
            http_response_code(403);
            echo '403';
            return;
        }

        $svc = new MobileAppConfigService();
        $apkSvc = new HrMobileApkService();
        $showApk = $this->canToggleEnable();
        $rows = $svc->listCompaniesWithConfig();
        foreach ($rows as &$row) {
            $row['features'] = $svc->decodeFeatures($row['enabled_features'] ?? null);
            $row['mobile_active'] = (string) ($row['mobile_status'] ?? '') === MobileAppConfigService::STATUS_ACTIVE;
            if ($showApk) {
                $companyRow = ['id' => (int) $row['company_id']] + $row;
                $row['erp_base_url'] = $apkSvc->erpBaseUrlForCompany($companyRow);
                $row['apk'] = $apkSvc->meta((int) $row['company_id']);
            }
        }
        unset($row);

        $this->view('admin/mobile-apps/index', [
            'title' => __('mobile_apps_title'),
            'rows' => $rows,
            'csrf' => Csrf::token(),
            'canManage' => $this->canManage(),
            'canToggleEnable' => $showApk,
            'consoleUrl' => rateb_url('admin/hr-mobile'),
            'consoleAccessible' => function_exists('rateb_hr_mobile_console_accessible')
                && rateb_hr_mobile_console_accessible(),
        ], 'main');
    }

    public function edit(array $params = []): void
    {
        if (!$this->canView()) {
            http_response_code(403);
            echo '403';
            return;
        }

        $companyId = (int) ($params['id'] ?? 0);
        $company = (new Company())->find($companyId);
        if (!$company) {
            SessionManager::flash('error', __('not_found'));
            Response::redirect(rateb_url('admin/mobile-apps'));
            return;
        }

        $svc = new MobileAppConfigService();
        $features = $svc->enableSalaryFeaturesForHrCompany($companyId);
        $row = $svc->findByCompanyId($companyId);
        $apkSvc = new HrMobileApkService();
        $apk = $this->canToggleEnable() ? $apkSvc->meta($companyId) : null;
        $apkUrl = $apk !== null ? $apkSvc->downloadUrl($apk) : '';

        $this->view('admin/mobile-apps/edit', [
            'title' => __('mobile_apps_edit'),
            'company' => $company,
            'config' => $row,
            'features' => $features,
            'featureKeys' => MobileAppConfigService::FEATURE_KEYS,
            'csrf' => Csrf::token(),
            'canManage' => $this->canManage(),
            'canToggleEnable' => $this->canToggleEnable(),
            'apk' => $apk,
            'apkUrl' => $apkUrl,
            'apkQr' => $apkUrl !== '' ? $apkSvc->qrImageUrl($apkUrl) : '',
            'erpBaseUrl' => $apkSvc->erpBaseUrlForCompany($company),
            'buildCommand' => $apkSvc->buildCommand($company),
            'apkChunkBytes' => HrMobileApkService::CHUNK_BYTES,
            'apkMaxBytes' => HrMobileApkService::MAX_BYTES,
        ], 'main');
    }

    /** Quick enable/disable from the companies list (platform super-admin only). */
    public function toggle(array $params = []): void
    {
        if (!$this->canToggleEnable()) {
            http_response_code(403);
            echo '403';
            return;
        }
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/mobile-apps'));
            return;
        }
        $companyId = (int) ($params['id'] ?? 0);
        $svc = new MobileAppConfigService();
        $existing = $svc->findByCompanyId($companyId);
        $status = (string) $this->input('status', '') === MobileAppConfigService::STATUS_ACTIVE
            ? MobileAppConfigService::STATUS_ACTIVE
            : MobileAppConfigService::STATUS_INACTIVE;
        $result = $svc->upsertForCompany($companyId, [
            'app_name' => (string) ($existing['app_name'] ?? ''),
            'logo_path' => $existing['logo_path'] ?? null,
            'icon_path' => $existing['icon_path'] ?? null,
            'splash_path' => $existing['splash_path'] ?? null,
            'theme_color' => (string) ($existing['theme_color'] ?? '#0D6EFD'),
            'status' => $status,
            'enabled_features' => is_array($existing)
                ? $svc->decodeFeatures($existing['enabled_features'] ?? null)
                : MobileAppConfigService::defaultFeatures(),
        ]);
        if ($result['ok']) {
            $msg = $status === MobileAppConfigService::STATUS_ACTIVE
                ? __('mobile_apps_enabled_flash')
                : __('mobile_apps_disabled_flash');
        } else {
            $msg = __('mobile_apps_save_failed');
        }
        SessionManager::flash($result['ok'] ? 'success' : 'error', $msg);
        $back = (string) $this->input('back', '') === 'edit'
            ? 'admin/mobile-apps/' . $companyId
            : 'admin/mobile-apps';
        Response::redirect(rateb_url($back));
    }

    /** Chunked upload so large APKs pass hosts with small upload_max_filesize. */
    public function uploadApkChunk(array $params = []): void
    {
        if (!$this->canToggleEnable()) {
            Response::json(['ok' => false, 'message' => '403'], 403);
            return;
        }
        if (!$this->validateCsrf()) {
            Response::json(['ok' => false, 'message' => __('csrf_invalid')], 419);
            return;
        }
        $file = $_FILES['chunk'] ?? null;
        if (!is_array($file)) {
            Response::json(['ok' => false, 'message' => __('mobile_apps_apk_upload_too_large')], 422);
            return;
        }
        $result = (new HrMobileApkService())->storeChunk(
            (int) ($params['id'] ?? 0),
            strtolower((string) $this->input('upload_id', '')),
            (int) $this->input('index', -1),
            (int) $this->input('total', 0),
            $file,
            (string) $this->input('original_name', 'app.apk'),
            (int) (\Rateb\App\Core\Auth::user()['id'] ?? 0)
        );
        if ($result['ok'] && $result['done']) {
            SessionManager::flash('success', $result['message']);
        }
        Response::json(['ok' => $result['ok'], 'done' => $result['done'], 'message' => $result['message']], $result['ok'] ? 200 : 422);
    }

    public function deleteApk(array $params = []): void
    {
        if (!$this->canToggleEnable()) {
            http_response_code(403);
            echo '403';
            return;
        }
        $companyId = (int) ($params['id'] ?? 0);
        if ($this->validateCsrf()) {
            (new HrMobileApkService())->remove($companyId);
            SessionManager::flash('success', __('mobile_apps_apk_deleted'));
        }
        Response::redirect(rateb_url('admin/mobile-apps/' . $companyId));
    }

    /** Public employee download (no login) — only while the company app is enabled. */
    public function downloadApk(array $params = []): void
    {
        $apkSvc = new HrMobileApkService();
        $found = $apkSvc->findByToken((string) ($params['token'] ?? ''));
        $config = $found !== null ? (new MobileAppConfigService())->findByCompanyId($found['company_id']) : null;
        if ($found === null || !is_file($found['path'])
            || !is_array($config) || (string) ($config['status'] ?? '') !== MobileAppConfigService::STATUS_ACTIVE) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo __('mobile_apps_apk_unavailable');
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $company = (new Company())->find($found['company_id']) ?? ['id' => $found['company_id']];
        $name = 'rateb-hr-' . $apkSvc->companySlug($company) . '.apk';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(200);
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . (string) filesize($found['path']));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        readfile($found['path']);
    }

    public function save(array $params = []): void
    {
        if (!$this->canManage()) {
            http_response_code(403);
            echo '403';
            return;
        }
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/mobile-apps'));
            return;
        }

        $companyId = (int) ($params['id'] ?? 0);
        $postedFeatures = $_POST['features'] ?? [];
        if (!is_array($postedFeatures)) {
            $postedFeatures = [];
        }
        $features = [];
        foreach (MobileAppConfigService::FEATURE_KEYS as $key) {
            $features[$key] = isset($postedFeatures[$key]) && (string) $postedFeatures[$key] === '1';
        }

        $svc = new MobileAppConfigService();
        // Agency users must not enable/disable the mobile app — only platform/super-admin.
        if ($this->canToggleEnable()) {
            $status = isset($_POST['status']) && (string) $_POST['status'] === 'active'
                ? MobileAppConfigService::STATUS_ACTIVE
                : MobileAppConfigService::STATUS_INACTIVE;
        } else {
            $existing = $svc->findByCompanyId($companyId);
            $status = is_array($existing) && (string) ($existing['status'] ?? '') === MobileAppConfigService::STATUS_ACTIVE
                ? MobileAppConfigService::STATUS_ACTIVE
                : MobileAppConfigService::STATUS_INACTIVE;
        }

        $result = $svc->upsertForCompany($companyId, [
            'app_name' => (string) $this->input('app_name', ''),
            'logo_path' => (string) $this->input('logo_path', ''),
            'icon_path' => (string) $this->input('icon_path', ''),
            'splash_path' => (string) $this->input('splash_path', ''),
            'theme_color' => (string) $this->input('theme_color', '#0D6EFD'),
            'status' => $status,
            'enabled_features' => $features,
        ]);

        SessionManager::flash(
            $result['ok'] ? 'success' : 'error',
            $result['ok'] ? __('mobile_apps_saved') : __('mobile_apps_save_failed')
        );
        Response::redirect(rateb_url('admin/mobile-apps/' . $companyId));
    }

    private function canView(): bool
    {
        if (function_exists('rateb_can') && rateb_can('mobile_apps.view')) {
            return true;
        }
        if (function_exists('rateb_nav_can') && rateb_nav_can('mobile_apps.view')) {
            return true;
        }
        // Fallback for Super Admin before role_permissions migrate lands.
        return function_exists('rateb_is_super_admin') && rateb_is_super_admin()
            && function_exists('rateb_can') && rateb_can('settings.manage');
    }

    private function canManage(): bool
    {
        if (function_exists('rateb_can') && rateb_can('mobile_apps.manage')) {
            return true;
        }
        if (function_exists('rateb_nav_can') && rateb_nav_can('mobile_apps.manage')) {
            return true;
        }

        return function_exists('rateb_is_super_admin') && rateb_is_super_admin()
            && function_exists('rateb_can') && rateb_can('settings.manage');
    }

    /**
     * Enable/disable mobile app is platform SaaS ops only.
     * Never show on dedicated agency hosts (وكلاء), and on rateb.sa only for super-admin.
     */
    private function canToggleEnable(): bool
    {
        // Agency dedicated ERP (e.g. admin.rateb.sa) — agents must never toggle enablement.
        if (function_exists('rateb_erp_is_dedicated_deployment') && rateb_erp_is_dedicated_deployment()) {
            return false;
        }
        // rateb.sa is "platform host" for everyone — do NOT use rateb_is_platform_oversight_host()
        // (that is true for the whole domain and was leaking the toggle to company admins).
        return function_exists('rateb_is_super_admin') && rateb_is_super_admin();
    }
}
