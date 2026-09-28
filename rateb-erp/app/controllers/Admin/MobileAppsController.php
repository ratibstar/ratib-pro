<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\Company;
use Rateb\App\Services\MobileAppActivationService;
use Rateb\App\Services\MobileAppApkService;
use Rateb\App\Services\MobileAppBrandedService;
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
        $platformUpdate = $platform ? $apkSvc->platformUpdateStatus($app) : null;
        $rows = (new MobileAppConfigService())->listCompaniesWithConfig();
        $branded = new MobileAppBrandedService($apkSvc);
        $activationSvc = new MobileAppActivationService($apkSvc);
        foreach ($rows as &$row) {
            $company = ['id' => (int) $row['company_id']] + $row;
            $row['mobile_active'] = $apkSvc->isEnabled($app, $company, ['status' => $row['mobile_status'] ?? '']);
            if ($platform) {
                $apkSvc->reconcileCompanySlotPolicy($app, $company);
                $branded->sync($app, $company, $this->currentUserId());
                $row['server'] = $apkSvc->serverForCompany($app, $company);
                $row['apk'] = $apkSvc->meta($apkSvc->slotKey($app, (int) $row['company_id']));
                $row['uses_shared'] = $row['apk'] === null && $apkSvc->sharedFallback($app, $company) !== null;
                $row['needs_app_update'] = $apkSvc->companyNeedsAppUpdate($app, $company);
                $storedCode = $activationSvc->codeFor($company);
                if ($storedCode === '' && !empty($row['mobile_active'])) {
                    $storedCode = $activationSvc->ensureCode((int) $row['company_id']);
                }
                $row['activation_code'] = $storedCode !== ''
                    ? MobileAppActivationService::format($storedCode)
                    : '';
            }
        }
        unset($row);
        $activationDuplicates = $platform ? $activationSvc->findDuplicateCodes() : [];

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
            'platformUpdate' => $platformUpdate,
            'activationDuplicates' => $activationDuplicates,
        ], 'main');
    }

    /** App updates hub: newest published build → shared build → company apps (platform super-admin). */
    public function updates(): void
    {
        if (!$this->canToggleEnable()) {
            http_response_code(403);
            echo '403';
            return;
        }

        $app = MobileAppApkService::normalizeApp((string) ($_GET['app'] ?? 'hr'));
        $apkSvc = new MobileAppApkService();
        $syncState = $apkSvc->syncSharedFromPublished($app, $this->currentUserId());
        $platformUpdate = $apkSvc->platformUpdateStatus($app);
        $sharedUrl = $apkSvc->downloadUrlForToken($apkSvc->ensureToken($app));

        $rows = [];
        $branded = new MobileAppBrandedService($apkSvc);
        foreach ((new MobileAppConfigService())->listCompaniesWithConfig() as $row) {
            $company = ['id' => (int) $row['company_id']] + $row;
            $apkSvc->reconcileCompanySlotPolicy($app, $company);
            $branded->sync($app, $company, $this->currentUserId());
            $rows[] = [
                'company_id' => (int) $row['company_id'],
                'company_name' => (string) ($row['company_name'] ?? ''),
                'active' => $apkSvc->isEnabled($app, $company, ['status' => $row['mobile_status'] ?? '']),
                'server' => $apkSvc->serverForCompany($app, $company),
                'state' => $apkSvc->companyUpdateState($app, $company),
            ];
        }

        $this->view('admin/mobile-apps/updates', [
            'title' => __('mobile_apps_updates_title'),
            'app' => $app,
            'appInfo' => $apkSvc->appInfo($app),
            'published' => $apkSvc->publishedBuild($app),
            'syncState' => $syncState,
            'sharedApk' => $apkSvc->meta($app),
            'sharedUrl' => $sharedUrl,
            'sharedQr' => $apkSvc->qrImageUrl($sharedUrl),
            'rows' => $rows,
            'csrf' => Csrf::token(),
            'apkChunkBytes' => MobileAppApkService::CHUNK_BYTES,
            'apkMaxBytes' => MobileAppApkService::MAX_BYTES,
            'platformUpdate' => $platformUpdate,
        ], 'main');
    }

    /** One click: adopt published shared APK + link companies on stale own builds. */
    public function applyPlatformUpdates(array $params = []): void
    {
        $app = $this->platformAppParam($params);
        $back = (string) $this->input('back', '');
        if ($back === '') {
            $back = rateb_url('admin/mobile-apps') . '?app=' . rawurlencode($app !== '' ? $app : 'hr');
        }
        if (!$this->canToggleEnable()) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect($back);
            return;
        }
        if ($app === '' || !$this->validateCsrf()) {
            SessionManager::flash('error', __('csrf_invalid'));
            Response::redirect($back);
            return;
        }
        $apkSvc = new MobileAppApkService();
        $before = $apkSvc->platformUpdateStatus($app);
        if ($apkSvc->sharedDiffersFromPublished($app)) {
            $apkSvc->syncSharedFromPublished($app, $this->currentUserId(), true);
        }
        $linked = 0;
        foreach ($apkSvc->outdatedCompanyIds($app) as $companyId) {
            $company = (new Company())->find($companyId);
            if (!is_array($company)) {
                continue;
            }
            if ($apkSvc->linkCompanyToShared($app, $company) === 'linked') {
                $linked++;
            }
        }
        $after = $apkSvc->platformUpdateStatus($app);
        if (!$before['pending']) {
            SessionManager::flash('success', __('mobile_platform_update_none'));
        } elseif (!$after['pending']) {
            SessionManager::flash(
                'success',
                sprintf(
                    __('mobile_platform_update_done'),
                    $before['version'] !== '' ? $before['version'] : (string) $before['version_code'],
                    $linked
                )
            );
        } else {
            SessionManager::flash('warning', __('mobile_platform_update_partial'));
        }
        Response::redirect($back);
    }

    /** Update one company to the latest platform unified APK (sync publish → shared, then link company). */
    public function updateCompanyApp(array $params = []): void
    {
        $companyId = (int) ($params['id'] ?? 0);
        $app = MobileAppApkService::normalizeApp((string) $this->input('app', 'hr'));
        $back = (string) $this->input('back', '');
        if ($back === '') {
            $back = rateb_url('admin/mobile-apps/' . $companyId) . ($app === 'hr' ? '' : '?app=' . $app) . '#mobile-distribution';
        }
        if (!$this->canToggleEnable()) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect($back);
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('csrf_invalid'));
            Response::redirect($back);
            return;
        }
        $company = (new Company())->find($companyId);
        if (!is_array($company)) {
            SessionManager::flash('error', __('not_found'));
            Response::redirect($back);
            return;
        }
        $apkSvc = new MobileAppApkService();
        if ($apkSvc->companyUpdateState($app, $company) === 'own_branded') {
            SessionManager::flash('error', __('mobile_company_update_branded'));
            Response::redirect($back);
            return;
        }
        if ($apkSvc->sharedDiffersFromPublished($app)) {
            $apkSvc->syncSharedFromPublished($app, $this->currentUserId(), true);
        }
        $link = $apkSvc->linkCompanyToShared($app, $company);
        $still = $apkSvc->companyNeedsAppUpdate($app, $company);
        $msgKey = match ($link) {
            'linked' => 'mobile_company_update_done',
            'already_shared' => $still ? 'mobile_company_update_partial' : 'mobile_company_update_already',
            'no_shared' => 'mobile_use_shared_no_platform_apk',
            'branded' => 'mobile_company_update_branded',
            default => 'mobile_apps_save_failed',
        };
        SessionManager::flash(
            $still && $link !== 'linked' ? 'warning' : ($link === 'no_shared' || $link === 'branded' ? 'error' : 'success'),
            __($msgKey)
        );
        Response::redirect($back);
    }

    /** Replace the shared build with the newest published build (overrides a manual shared upload). */
    public function applyPublished(array $params = []): void
    {
        $app = $this->platformAppParam($params);
        $back = 'admin/mobile-apps/updates?app=' . rawurlencode($app !== '' ? $app : 'hr');
        if (!$this->canToggleEnable()) {
            http_response_code(403);
            echo '403';
            return;
        }
        if ($app === '' || !$this->validateCsrf()) {
            Response::redirect(rateb_url($back));
            return;
        }
        $state = (new MobileAppApkService())->syncSharedFromPublished($app, $this->currentUserId(), true);
        SessionManager::flash(
            in_array($state, ['updated', 'current'], true) ? 'success' : 'error',
            __('mobile_apps_updates_apply_' . $state)
        );
        Response::redirect(rateb_url($back));
    }

    /** Link selected platform-server companies to the shared build so they get this and future updates. */
    public function pushUpdates(array $params = []): void
    {
        $app = $this->platformAppParam($params);
        $back = 'admin/mobile-apps/updates?app=' . rawurlencode($app !== '' ? $app : 'hr');
        if (!$this->canToggleEnable()) {
            http_response_code(403);
            echo '403';
            return;
        }
        if ($app === '' || !$this->validateCsrf()) {
            Response::redirect(rateb_url($back));
            return;
        }
        $ids = $_POST['company_ids'] ?? [];
        $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0))) : [];
        if ($ids === []) {
            SessionManager::flash('error', __('mobile_apps_updates_push_none_selected'));
            Response::redirect(rateb_url($back));
            return;
        }

        $apkSvc = new MobileAppApkService();
        $model = new Company();
        $counts = ['linked' => 0, 'already_shared' => 0, 'branded' => 0, 'no_shared' => 0];
        foreach ($ids as $id) {
            $company = $model->find($id);
            if (!is_array($company)) {
                continue;
            }
            $counts[$apkSvc->linkCompanyToShared($app, $company)]++;
        }
        if ($counts['no_shared'] > 0) {
            SessionManager::flash('error', __('mobile_apps_updates_apply_none'));
        } else {
            SessionManager::flash('success', sprintf(
                __('mobile_apps_updates_push_done'),
                $counts['linked'],
                $counts['already_shared']
            ));
        }
        Response::redirect(rateb_url($back));
    }

    /** Request or cancel the company-branded build of one app (platform super-admin only). */
    public function branded(array $params = []): void
    {
        $companyId = (int) ($params['id'] ?? 0);
        $app = MobileAppApkService::normalizeApp((string) $this->input('app', 'hr'));
        $back = rateb_url('admin/mobile-apps/' . $companyId) . ($app === 'hr' ? '' : '?app=' . $app);
        if (!$this->canToggleEnable()) {
            http_response_code(403);
            echo '403';
            return;
        }
        if ($this->validateCsrf()) {
            $on = (string) $this->input('requested', '') === '1';
            $brandedSvc = new MobileAppBrandedService();
            $ok = $brandedSvc->setRequested($app, $companyId, $on);
            $msgKey = $ok ? ($on ? 'mobile_branded_requested' : 'mobile_branded_cancelled') : 'mobile_apps_save_failed';
            if ($ok && !$on) {
                $company = (new Company())->find($companyId);
                if (is_array($company)) {
                    $link = (new MobileAppApkService())->linkCompanyToShared($app, $company);
                    if ($link === 'linked') {
                        $msgKey = 'mobile_use_shared_done';
                    }
                }
            }
            SessionManager::flash($ok ? 'success' : 'error', __($msgKey));
        }
        Response::redirect($back . '#rateb-app-apk-card');
    }

    /** Point company at the platform shared APK and cancel optional separate branded APK mode. */
    public function useShared(array $params = []): void
    {
        $companyId = (int) ($params['id'] ?? 0);
        $app = MobileAppApkService::normalizeApp((string) $this->input('app', 'hr'));
        $back = rateb_url('admin/mobile-apps/' . $companyId) . ($app === 'hr' ? '' : '?app=' . $app) . '#rateb-app-apk-card';
        if (!$this->canToggleEnable()) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect($back);
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('csrf_invalid'));
            Response::redirect($back);
            return;
        }
        $company = (new Company())->find($companyId);
        if (!is_array($company)) {
            SessionManager::flash('error', __('not_found'));
            Response::redirect($back);
            return;
        }
        $brandedSvc = new MobileAppBrandedService();
        $brandedSvc->setRequested($app, $companyId, false);
        $link = (new MobileAppApkService())->linkCompanyToShared($app, $company);
        $msgKey = match ($link) {
            'linked' => 'mobile_use_shared_done',
            'already_shared' => 'mobile_use_shared_already',
            'no_shared' => 'mobile_use_shared_no_platform_apk',
            'branded' => 'mobile_use_shared_branded_apk',
            default => 'mobile_apps_save_failed',
        };
        SessionManager::flash($link === 'linked' || $link === 'already_shared' ? 'success' : 'error', __($msgKey));
        Response::redirect($back);
    }

    /** Company name shown on the download page and inside the apps (Arabic / English). */
    public function names(array $params = []): void
    {
        $companyId = (int) ($params['id'] ?? 0);
        $app = MobileAppApkService::normalizeApp((string) $this->input('app', 'hr'));
        $back = rateb_url('admin/mobile-apps/' . $companyId) . ($app === 'hr' ? '' : '?app=' . $app);
        if (!$this->canToggleEnable()) {
            http_response_code(403);
            echo '403';
            return;
        }
        if ($this->validateCsrf()) {
            $branded = new MobileAppBrandedService();
            $ok = $branded->setNames(
                $companyId,
                (string) $this->input('name_ar', ''),
                (string) $this->input('name_en', '')
            );
            if ($ok && $branded->keyFor($app, (array) ((new Company())->find($companyId) ?? [])) !== '') {
                $branded->refreshSpecSnapshot($app, $companyId);
            }
            SessionManager::flash($ok ? 'success' : 'error', __($ok ? 'mobile_names_saved' : 'mobile_apps_save_failed'));
        }
        Response::redirect($back);
    }

    /** Queue of company-branded builds (platform super-admin). */
    public function brandedQueue(): void
    {
        if (!$this->canToggleEnable()) {
            http_response_code(403);
            echo '403';
            return;
        }
        $app = MobileAppApkService::normalizeApp((string) ($_GET['app'] ?? 'hr'));
        $branded = new MobileAppBrandedService();
        $this->view('admin/mobile-apps/branded-queue', [
            'title' => __('mobile_branded_queue_title'),
            'app' => $app,
            'rows' => $branded->listBuildQueue($app, false),
            'actionable' => $branded->listBuildQueue($app, true),
            'platformVersions' => $branded->sharedVersionCodes(),
            'dispatchEnabled' => trim((string) (getenv('RATEB_GITHUB_DISPATCH_TOKEN') ?: '')) !== '',
            'apiConfigured' => rateb_mobile_build_secret() !== '',
            'csrf' => Csrf::token(),
        ], 'main');
    }

    /** Queue one company branded build and optionally trigger GitHub Actions. */
    public function brandedBuild(array $params = []): void
    {
        $companyId = (int) ($params['id'] ?? 0);
        $app = MobileAppApkService::normalizeApp((string) $this->input('app', 'hr'));
        $back = (string) $this->input('back', '');
        if ($back === '') {
            $back = rateb_url('admin/mobile-apps/' . $companyId) . ($app === 'hr' ? '' : '?app=' . $app);
        }
        if (!str_contains($back, '#')) {
            $back .= '#rateb-app-apk-card';
        }
        if (!$this->canToggleEnable()) {
            SessionManager::flash('error', __('access_denied'));
            Response::redirect($back);
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('csrf_invalid'));
            Response::redirect($back);
            return;
        }
        $branded = new MobileAppBrandedService();
        $ok = $branded->queueBuild($app, $companyId);
        $dispatched = $ok && rateb_github_dispatch_mobile_build($app);
        SessionManager::flash(
            $ok ? 'success' : 'error',
            __($ok ? ($dispatched ? 'mobile_branded_build_dispatched' : 'mobile_branded_build_queued') : 'mobile_branded_build_failed')
        );
        Response::redirect($back);
    }

    /** Queue every pending/outdated branded build for one app type. */
    public function brandedBuildAll(array $params = []): void
    {
        $app = $this->platformAppParam($params);
        $back = rateb_url('admin/mobile-apps/branded-queue') . '?app=' . rawurlencode($app !== '' ? $app : 'hr');
        if (!$this->canToggleEnable() || !$this->validateCsrf()) {
            Response::redirect($back);
            return;
        }
        $branded = new MobileAppBrandedService();
        $n = 0;
        foreach ($branded->listBuildQueue($app !== '' ? $app : null, true) as $row) {
            if ($branded->queueBuild($row['app'], (int) $row['company_id'])) {
                $n++;
            }
        }
        $dispatched = $n > 0 && rateb_github_dispatch_mobile_build($app !== '' ? $app : 'all');
        SessionManager::flash(
            $n > 0 ? 'success' : 'info',
            __($n > 0 ? ($dispatched ? 'mobile_branded_build_all_dispatched' : 'mobile_branded_build_all_queued') : 'mobile_branded_build_none')
        );
        Response::redirect($back);
    }

    /** New activation code for the company; the old code stops working. */
    public function regenerateActivationCode(array $params = []): void
    {
        $companyId = (int) ($params['id'] ?? 0);
        $app = MobileAppApkService::normalizeApp((string) $this->input('app', 'hr'));
        $back = rateb_url('admin/mobile-apps/' . $companyId) . ($app === 'hr' ? '' : '?app=' . $app);
        if (!$this->canToggleEnable()) {
            http_response_code(403);
            echo '403';
            return;
        }
        if ($this->validateCsrf()) {
            $ok = (new MobileAppActivationService())->regenerate($companyId) !== '';
            SessionManager::flash($ok ? 'success' : 'error', __($ok ? 'mobile_activation_regenerated' : 'mobile_apps_save_failed'));
        }
        Response::redirect($back);
    }

    private function currentUserId(): int
    {
        return (int) (\Rateb\App\Core\Auth::user()['id'] ?? 0);
    }

    public function edit(array $params = []): void
    {
        if (!$this->canView()) {
            http_response_code(403);
            echo '403';
            return;
        }

        $companyId = (int) ($params['id'] ?? 0);
        $company = $this->ownsCompany($companyId) ? (new Company())->find($companyId) : null;
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
            $data['platformUpdate'] = (new MobileAppApkService())->platformUpdateStatus($app);
        } else {
            $data['share'] = $this->companyShare($company);
        }

        $this->view($app === 'hr' ? 'admin/mobile-apps/edit' : 'admin/mobile-apps/company-app', $data, 'main');
    }

    /**
     * Read-only sharing info for the company's own admins: activation code + download links.
     * Codes are issued on the platform only; agency hosts have their own DB, so never mint one there.
     *
     * @return array{code:string, activationUrl:string, activationQr:string, apps:list<array{app:string, url:string, qr:string}>}
     */
    private function companyShare(array $company): array
    {
        $apkSvc = new MobileAppApkService();
        $activation = new MobileAppActivationService($apkSvc);
        $agencyHost = function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host();
        $code = $agencyHost ? $activation->codeFor($company) : $activation->ensureCode((int) $company['id']);
        $activationUrl = $code !== '' ? $activation->publicActivationUrl($code) : '';
        $apps = [];
        foreach (MobileAppApkService::APPS as $app) {
            if (!$apkSvc->isEnabled($app, $company)) {
                continue;
            }
            $url = $agencyHost
                ? rateb_platform_oversight_public_url('downloads/' . MobileAppApkService::PUBLISHED_FILES[$app])
                : $apkSvc->downloadUrlForToken($apkSvc->ensureToken($apkSvc->slotKey($app, (int) $company['id'])));
            $apps[] = ['app' => $app, 'url' => $url, 'qr' => $apkSvc->qrImageUrl($url, 160)];
        }

        return [
            'code' => MobileAppActivationService::format($code),
            'activationUrl' => $activationUrl,
            'activationQr' => $code !== '' ? $apkSvc->qrDataUri($activation->qrActivationPayload($code), 160) : '',
            'activationQrPayload' => $code !== '' ? $activation->qrActivationPayload($code) : '',
            'apps' => $apps,
        ];
    }

    /** Platform super-admin sees every company; company users only their own. */
    private function ownsCompany(int $companyId): bool
    {
        if ($companyId < 1) {
            return false;
        }
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            return true;
        }

        return (int) (\Rateb\App\Core\TenantContext::companyId() ?? 0) === $companyId;
    }

    /** @return array<string, mixed> */
    private function companyAppCard(string $app, array $company, ?array $hrConfig): array
    {
        $apkSvc = new MobileAppApkService();
        $branded = new MobileAppBrandedService($apkSvc);
        $cid = (int) $company['id'];
        $key = $apkSvc->slotKey($app, $cid);
        $server = $apkSvc->serverForCompany($app, $company);
        $apkSvc->reconcileCompanySlotPolicy($app, $company);
        $branded->sync($app, $company, $this->currentUserId());
        $apk = $apkSvc->meta($key);
        $url = $apkSvc->downloadUrlForToken($apkSvc->ensureToken($key));
        $activation = new MobileAppActivationService($apkSvc);
        $code = $activation->ensureCode($cid);
        if ($code !== '' && $activation->findCompanyByCode($code) === null) {
            $code = $activation->regenerate($cid);
        }
        $activationUrl = $code !== '' ? $activation->activationUrl($code) : '';
        $brandedKey = $branded->keyFor($app, $company);
        $pub = $brandedKey !== '' ? $branded->published($brandedKey) : null;
        $targetBuild = $branded->sharedVersionCodes()[$app] ?? 0;
        $needsBuild = $brandedKey !== ''
            && ($pub === null || ($targetBuild > 0 && (int) ($pub['version_code'] ?? 0) < $targetBuild));
        $sharedApk = $apk === null ? $apkSvc->sharedFallback($app, $company) : null;
        $servedMeta = $apk ?? $sharedApk;
        $servedBuildLabel = '';
        if (is_array($servedMeta)) {
            $vc = (int) ($servedMeta['version_code'] ?? 0);
            $ver = trim((string) ($servedMeta['version'] ?? ''));
            $servedBuildLabel = $ver !== '' ? $ver . ($vc > 0 ? ' (build ' . $vc . ')' : '') : ($vc > 0 ? 'build ' . $vc : '');
        }
        $distribution = 'missing';
        if ($brandedKey !== '') {
            $distribution = 'branded';
        } elseif ($apk !== null) {
            $distribution = $apkSvc->isBranded($apk) ? 'branded' : 'own_upload';
        } elseif ($sharedApk !== null) {
            $distribution = 'shared';
        }
        $identityLogo = $branded->iconUrl($company, $hrConfig, $server);
        $companyUpdateState = $apkSvc->companyUpdateState($app, $company);

        return [
            'distribution' => $distribution,
            'companyUpdateState' => $companyUpdateState,
            'needsAppUpdate' => $apkSvc->companyNeedsAppUpdate($app, $company),
            'companyName' => (string) ($company['name'] ?? ''),
            'servedBuildLabel' => $servedBuildLabel,
            'identityLogo' => $identityLogo,
            'showHrBrandingLink' => $app === 'hr',
            'branded' => [
                'requested' => $brandedKey !== '',
                'needs_build' => $needsBuild,
                'built' => $apkSvc->isBranded($apk),
                'version' => $apkSvc->isBranded($apk) ? (string) ($apk['version'] ?? '') : '',
                'package' => $branded->packageFor($app, $cid),
                'name' => $branded->displayName($app, $company, $hrConfig),
                'icon' => $branded->iconUrl($company, $hrConfig, $server),
                'command' => $branded->buildCommand($app, $company, $hrConfig, MobileAppActivationService::format($code)),
                'queued' => $this->brandedQueued($company, $app),
            ],
            'names' => $branded->names($company),
            'dispatchEnabled' => trim((string) (getenv('RATEB_GITHUB_DISPATCH_TOKEN') ?: '')) !== '',
            'activationCode' => MobileAppActivationService::format($code),
            'activationUrl' => $activationUrl,
            'activationQr' => $code !== '' ? $apkSvc->qrDataUri($activation->qrActivationPayload($code), 180) : '',
            'activationQrPayload' => $code !== '' ? $activation->qrActivationPayload($code) : '',
            'needsCode' => $apkSvc->needsActivationCode($app, $company),
            'app' => $app,
            'cid' => $cid,
            'active' => $apkSvc->isEnabled($app, $company, $hrConfig ?? ['status' => '']),
            'server' => $server,
            'buildDir' => $apkSvc->appInfo($app)['build_dir'],
            'buildCommand' => $apkSvc->buildCommand($app, $server, $apkSvc->companySlug($company)),
            'apk' => $apk,
            'sharedApk' => $sharedApk,
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
        $backInput = (string) $this->input('back', '');
        $back = match ($backInput) {
            'edit' => 'admin/mobile-apps/' . $companyId,
            'settings' => 'admin/agent-apps/settings',
            default => 'admin/mobile-apps',
        };
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
        if (is_array($company)) {
            $apkSvc->reconcileCompanySlotPolicy($found['app'], $company);
            $policyPath = $apkSvc->activationApkPath($found['app'], $company);
            if ($policyPath !== null && is_file($policyPath)) {
                $found['path'] = $policyPath;
            }
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
        if (!$this->ownsCompany($companyId)) {
            http_response_code(403);
            echo '403';
            return;
        }
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

    private function brandedQueued(array $company, string $app): bool
    {
        $settings = json_decode((string) ($company['settings'] ?? ''), true);

        return is_array($settings) && isset($settings[MobileAppBrandedService::QUEUE_KEY][MobileAppApkService::normalizeApp($app)]);
    }
}
