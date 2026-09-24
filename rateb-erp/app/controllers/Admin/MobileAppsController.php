<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\Company;
use Rateb\App\Services\MobileAppApkService;
use Rateb\App\Services\MobileAppConfigService;

/**
 * Platform Mobile Apps Management — the three RATEB apps (HR / ERP / Customer) handled the same way:
 * companies list per app, enable per company, per-company signed APK + public link/QR, shared build.
 * HR also keeps its tenant white-label config. HR Mobile Console (launcher) stays under /admin/hr-mobile.
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

        $platform = $this->canToggleEnable();
        $app = $platform ? MobileAppApkService::normalizeApp((string) ($_GET['app'] ?? 'hr')) : 'hr';
        $apkSvc = new MobileAppApkService();
        $rows = (new MobileAppConfigService())->listCompaniesWithConfig();
        foreach ($rows as &$row) {
            $company = ['id' => (int) $row['company_id']] + $row;
            $row['mobile_active'] = $apkSvc->isEnabled($app, $company, ['status' => $row['mobile_status'] ?? '']);
            if ($platform) {
                $row['server'] = $apkSvc->serverForCompany($app, $company);
                $row['apk'] = $apkSvc->meta($apkSvc->slotKey($app, (int) $row['company_id']));
                $row['uses_shared'] = $row['apk'] === null && $apkSvc->sharedFallback($app, $company) !== null;
            }
        }
        unset($row);

        $shared = null;
        if ($platform) {
            $sharedToken = $apkSvc->ensureToken($app);
            $sharedUrl = $apkSvc->downloadUrlForToken($sharedToken);
            $sharedServer = $apkSvc->platformServer($app);
            $shared = [
                'server' => $sharedServer,
                'build_command' => $apkSvc->buildCommand($app, $sharedServer, 'platform'),
                'apk' => $apkSvc->meta($app),
                'url' => $sharedUrl,
                'qr' => $apkSvc->qrImageUrl($sharedUrl),
            ];
        }

        $this->view('admin/mobile-apps/index', [
            'title' => __('mobile_apps_title'),
            'app' => $app,
            'appInfo' => $apkSvc->appInfo($app),
            'rows' => $rows,
            'shared' => $shared,
            'csrf' => Csrf::token(),
            'canManage' => $this->canManage(),
            'canToggleEnable' => $platform,
            'apkChunkBytes' => MobileAppApkService::CHUNK_BYTES,
            'apkMaxBytes' => MobileAppApkService::MAX_BYTES,
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

        $platform = $this->canToggleEnable();
        $app = $platform ? MobileAppApkService::normalizeApp((string) ($_GET['app'] ?? 'hr')) : 'hr';
        $svc = new MobileAppConfigService();
        $features = $app === 'hr' ? $svc->enableSalaryFeaturesForHrCompany($companyId) : [];
        $row = $svc->findByCompanyId($companyId);

        $data = [
            'title' => __('mobile_apps_edit'),
            'app' => $app,
            'company' => $company,
            'config' => $row,
            'features' => $features,
            'featureKeys' => MobileAppConfigService::FEATURE_KEYS,
            'csrf' => Csrf::token(),
            'canManage' => $this->canManage(),
            'canToggleEnable' => $platform,
        ];
        if ($platform) {
            $data['appCard'] = $this->companyAppCard($app, $company, $row);
        }

        $this->view($app === 'hr' ? 'admin/mobile-apps/edit' : 'admin/mobile-apps/company-app', $data, 'main');
    }

    /** @return array<string, mixed> */
    private function companyAppCard(string $app, array $company, ?array $hrConfig): array
    {
        $apkSvc = new MobileAppApkService();
        $cid = (int) $company['id'];
        $key = $apkSvc->slotKey($app, $cid);
        $server = $apkSvc->serverForCompany($app, $company);
        $apk = $apkSvc->meta($key);
        $url = $apkSvc->downloadUrlForToken($apkSvc->ensureToken($key));

        return [
            'app' => $app,
            'cid' => $cid,
            'active' => $apkSvc->isEnabled($app, $company, $hrConfig ?? ['status' => '']),
            'server' => $server,
            'buildDir' => $apkSvc->appInfo($app)['build_dir'],
            'buildCommand' => $apkSvc->buildCommand($app, $server, $apkSvc->companySlug($company)),
            'apk' => $apk,
            'sharedApk' => $apk === null ? $apkSvc->sharedFallback($app, $company) : null,
            'url' => $url,
            'qr' => $apkSvc->qrImageUrl($url),
            'chunkBytes' => MobileAppApkService::CHUNK_BYTES,
            'maxBytes' => MobileAppApkService::MAX_BYTES,
        ];
    }

    /** Enable/disable one app for one company (platform super-admin only). */
    public function toggle(array $params = []): void
    {
        if (!$this->canToggleEnable()) {
            http_response_code(403);
            echo '403';
            return;
        }
        $companyId = (int) ($params['id'] ?? 0);
        $app = MobileAppApkService::normalizeApp((string) $this->input('app', 'hr'));
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('admin/mobile-apps') . '?app=' . $app);
            return;
        }
        $enable = (string) $this->input('status', '') === MobileAppConfigService::STATUS_ACTIVE;
        $ok = $app === 'hr'
            ? $this->setHrStatus($companyId, $enable)
            : (new MobileAppApkService())->setEnabledInSettings($app, $companyId, $enable);
        if ($ok) {
            SessionManager::flash('success', $enable ? __('mobile_apps_enabled_flash') : __('mobile_apps_disabled_flash'));
        } else {
            SessionManager::flash('error', __('mobile_apps_save_failed'));
        }
        $back = (string) $this->input('back', '') === 'edit'
            ? 'admin/mobile-apps/' . $companyId
            : 'admin/mobile-apps';
        Response::redirect(rateb_url($back) . '?app=' . $app);
    }

    private function setHrStatus(int $companyId, bool $enable): bool
    {
        $svc = new MobileAppConfigService();
        $existing = $svc->findByCompanyId($companyId);
        $result = $svc->upsertForCompany($companyId, [
            'app_name' => (string) ($existing['app_name'] ?? ''),
            'logo_path' => $existing['logo_path'] ?? null,
            'icon_path' => $existing['icon_path'] ?? null,
            'splash_path' => $existing['splash_path'] ?? null,
            'theme_color' => (string) ($existing['theme_color'] ?? '#0D6EFD'),
            'status' => $enable ? MobileAppConfigService::STATUS_ACTIVE : MobileAppConfigService::STATUS_INACTIVE,
            'enabled_features' => is_array($existing)
                ? $svc->decodeFeatures($existing['enabled_features'] ?? null)
                : MobileAppConfigService::defaultFeatures(),
        ]);

        return $result['ok'];
    }

    public function uploadApkChunk(array $params = []): void
    {
        $app = MobileAppApkService::normalizeApp((string) $this->input('app', 'hr'));
        $this->receiveApkChunk((new MobileAppApkService())->slotKey($app, (int) ($params['id'] ?? 0)));
    }

    public function uploadPlatformApkChunk(array $params = []): void
    {
        $this->receiveApkChunk($this->platformAppParam($params));
    }

    public function deleteApk(array $params = []): void
    {
        $companyId = (int) ($params['id'] ?? 0);
        $app = MobileAppApkService::normalizeApp((string) $this->input('app', 'hr'));
        $this->removeApk((new MobileAppApkService())->slotKey($app, $companyId), 'admin/mobile-apps/' . $companyId . '?app=' . $app);
    }

    public function deletePlatformApk(array $params = []): void
    {
        $app = $this->platformAppParam($params);
        $this->removeApk($app, 'admin/mobile-apps?app=' . rawurlencode($app));
    }

    private function platformAppParam(array $params): string
    {
        $app = strtolower((string) ($params['app'] ?? ''));

        return in_array($app, MobileAppApkService::APPS, true) ? $app : '';
    }

    /** Chunked upload so large APKs pass hosts with small upload_max_filesize. */
    private function receiveApkChunk(string $key): void
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
        $result = (new MobileAppApkService())->storeChunk(
            $key,
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

    private function removeApk(string $key, string $back): void
    {
        if (!$this->canToggleEnable()) {
            http_response_code(403);
            echo '403';
            return;
        }
        if ($this->validateCsrf()) {
            (new MobileAppApkService())->remove($key);
            SessionManager::flash('success', __('mobile_apps_apk_deleted'));
        }
        Response::redirect(rateb_url($back));
    }

    /**
     * Public download (no login). A company link works only while that app is enabled for the company;
     * shared-build links work whenever a shared APK is uploaded.
     */
    public function downloadApk(array $params = []): void
    {
        $apkSvc = new MobileAppApkService();
        $found = $apkSvc->resolveToken((string) ($params['token'] ?? ''));
        $company = null;
        $available = $found !== null && is_file($found['path']);
        if ($available && $found['company_id'] > 0) {
            $company = (new Company())->find($found['company_id']);
            $available = is_array($company) && $apkSvc->isEnabled($found['app'], $company);
        }
        if (!$available) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo __('mobile_apps_apk_unavailable');
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $name = $apkSvc->appInfo($found['app'])['file_prefix']
            . (is_array($company) ? '-' . $apkSvc->companySlug($company) : '') . '.apk';
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
