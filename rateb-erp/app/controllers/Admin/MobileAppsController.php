<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\Company;
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
        $rows = $svc->listCompaniesWithConfig();
        foreach ($rows as &$row) {
            $row['features'] = $svc->decodeFeatures($row['enabled_features'] ?? null);
            $row['mobile_active'] = (string) ($row['mobile_status'] ?? '') === MobileAppConfigService::STATUS_ACTIVE;
        }
        unset($row);

        $this->view('admin/mobile-apps/index', [
            'title' => __('mobile_apps_title'),
            'rows' => $rows,
            'csrf' => Csrf::token(),
            'canManage' => $this->canManage(),
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
        $row = $svc->findByCompanyId($companyId);
        $features = $svc->decodeFeatures($row['enabled_features'] ?? null);

        $this->view('admin/mobile-apps/edit', [
            'title' => __('mobile_apps_edit'),
            'company' => $company,
            'config' => $row,
            'features' => $features,
            'featureKeys' => MobileAppConfigService::FEATURE_KEYS,
            'csrf' => Csrf::token(),
            'canManage' => $this->canManage(),
        ], 'main');
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

        $status = isset($_POST['status']) && (string) $_POST['status'] === 'active'
            ? MobileAppConfigService::STATUS_ACTIVE
            : MobileAppConfigService::STATUS_INACTIVE;

        $svc = new MobileAppConfigService();
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
}
