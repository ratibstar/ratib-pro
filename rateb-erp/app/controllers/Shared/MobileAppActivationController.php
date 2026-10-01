<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Controller;
use Rateb\App\Core\IpRateLimiter;
use Rateb\App\Core\Response;
use Rateb\App\Core\View;
use Rateb\App\Models\Company;
use Rateb\App\Services\MobileAppActivationService;
use Rateb\App\Services\MobileAppApkService;
use Rateb\App\Services\MobileAppBrandedService;
use Rateb\App\Services\MobileAppConfigService;

/**
 * Public company activation for the shared mobile apps (no login).
 * The code is shared by Super Admin with the company; it maps to the company's server only.
 */
final class MobileAppActivationController extends Controller
{
    private const API_LIMIT = 30;
    private const PAGE_LIMIT = 60;
    private const WINDOW_SECONDS = 600;

    /** GET /api/v1/mobile/activation?code=…&app=hr|erp|customer */
    public function api(): void
    {
        header('Cache-Control: no-store');
        if (!IpRateLimiter::attempt('mobile_activation_api:' . $this->ip(), self::API_LIMIT, self::WINDOW_SECONDS)) {
            Response::json(['success' => false, 'code' => 'rate_limited', 'message' => __('mobile_activation_rate_limited')], 429);
            return;
        }
        $code = MobileAppActivationService::normalize((string) ($_GET['code'] ?? ''));
        if ($code === '') {
            Response::json(['success' => false, 'code' => 'invalid_code', 'message' => __('mobile_activation_invalid')], 404);
            return;
        }
        $result = (new MobileAppActivationService())->resolve($code, (string) ($_GET['app'] ?? 'hr'));
        Response::json($result['body'], $result['status']);
    }

    /** GET /app-activate (?code=… redirects to the company page) */
    public function form(): void
    {
        $raw = (string) ($_GET['code'] ?? '');
        if ($raw !== '') {
            $code = MobileAppActivationService::normalize($raw);
            if ($code !== '') {
                Response::redirect(rateb_url('app-activate/' . MobileAppActivationService::format($code)));
                return;
            }
        }
        $this->view('shared/app-activate', [
            'title' => __('mobile_activation_title'),
            'company' => null,
            'error' => $raw !== '' ? __('mobile_activation_invalid') : null,
        ], 'auth');
    }

    /** GET /app-activate/{code}: company name, enabled apps with download links, code to enter. */
    public function show(array $params = []): void
    {
        header('Cache-Control: no-store');
        $data = ['title' => __('mobile_activation_title'), 'company' => null, 'error' => null];
        if (!IpRateLimiter::attempt('mobile_activation_page:' . $this->ip(), self::PAGE_LIMIT, self::WINDOW_SECONDS)) {
            http_response_code(429);
            $data['error'] = __('mobile_activation_rate_limited');
            $this->view('shared/app-activate', $data, 'auth');
            return;
        }
        $svc = new MobileAppActivationService();
        $apks = new MobileAppApkService();
        $code = MobileAppActivationService::normalize((string) ($params['code'] ?? ''));
        $company = $code !== '' ? $svc->findCompanyByCode($code) : null;
        if (is_array($company)) {
            if ($apks->hrIsPerCompanyDedicated()) {
                (new MobileAppBrandedService($apks))->ensureDedicatedHrRequested((int) ($company['id'] ?? 0));
                $refreshed = (new Company())->find((int) ($company['id'] ?? 0));
                if (is_array($refreshed)) {
                    $company = $refreshed;
                }
            }
            $apks->reconcileCompanySlotPolicy('hr', $company);
            $this->ensureCompanionMobileApps($apks, (int) ($company['id'] ?? 0));
            $refreshed = (new Company())->find((int) ($company['id'] ?? 0));
            if (is_array($refreshed)) {
                $company = $refreshed;
            }
        }
        if ($company === null || (string) ($company['status'] ?? 'active') !== 'active') {
            http_response_code(404);
            $data['error'] = __('mobile_activation_invalid');
            $this->view('shared/app-activate', $data, 'auth');
            return;
        }
        $formatted = MobileAppActivationService::format($code);
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $erpBase = $apks->erpBaseUrlForCompany($company);
        $branded = new MobileAppBrandedService($apks);
        $names = $branded->names($company);
        $hrConfig = (new MobileAppConfigService())->findByCompanyId((int) $company['id']);
        $erpHost = (string) (parse_url($erpBase, PHP_URL_HOST) ?? '');
        if (str_contains($requestUri, '/m/activate/')) {
            // QR scanned but App Links did not hand off: open the HR app directly; the tiles page is the fallback.
            $isAndroid = (bool) preg_match('/Android/i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
            Response::redirect($isAndroid && $apks->isEnabled('hr', $company)
                ? $svc->mobileAppOpenIntent('hr', $code, $company, $svc->appOpenUrl($code, 'hr', true))
                : $svc->appOpenUrl($code, 'hr', true));
            return;
        }
        $apps = $svc->enabledApps($company);
        foreach ($apps as $i => $row) {
            $apps[$i]['tile_href'] = $svc->appOpenUrl($code, (string) ($row['app'] ?? 'hr'));
        }
        $this->view('shared/app-activate', array_merge($data, [
            'company' => [
                'id' => (int) ($company['id'] ?? 0),
                'name' => $names[rateb_locale() === 'ar' ? 'ar' : 'en'],
                'logo' => $branded->iconUrl($company, $hrConfig, $erpBase),
            ],
            'code' => MobileAppActivationService::format($code),
            'erpHost' => $erpHost,
            'apps' => $apps,
            'adminUrl' => $apks->isEnabled('erp', $company)
                ? $erpBase . '/login?company_code=' . rawurlencode($formatted)
                : '',
        ]), 'auth');
    }

    /** Legacy tile links (/app-activate/{code}/go/{app}) → /open-app/{code}/{app}. */
    public function launch(array $params = []): void
    {
        $code = MobileAppActivationService::normalize((string) ($params['code'] ?? ''));
        $app = (string) ($params['app'] ?? '');
        if ($code === '' || !in_array($app, MobileAppApkService::APPS, true)) {
            Response::redirect(rateb_url('app-activate'));
            return;
        }
        Response::redirect((new MobileAppActivationService())->appOpenUrl($code, $app));
    }

    /**
     * GET /open-app/{code}/{app}: one app per QR/link. Android gets a server redirect to the app's
     * intent (a redirect keeps the tap's user gesture, unlike JS after load); the install/open page
     * (?page=1) is the intent fallback when the app is not installed, and the page for other devices.
     */
    public function openApp(array $params = []): void
    {
        header('Cache-Control: no-store');
        $code = MobileAppActivationService::normalize((string) ($params['code'] ?? ''));
        $app = (string) ($params['app'] ?? '');
        $svc = new MobileAppActivationService();
        $company = $code !== '' && in_array($app, MobileAppApkService::APPS, true)
            ? $svc->findCompanyByCode($code)
            : null;
        $row = null;
        if (is_array($company) && (string) ($company['status'] ?? 'active') === 'active') {
            foreach ($svc->enabledApps($company) as $candidate) {
                if (($candidate['app'] ?? '') === $app) {
                    $row = $candidate;
                    break;
                }
            }
        }
        if ($row === null) {
            http_response_code(404);
            $this->view('shared/app-activate', [
                'title' => __('mobile_activation_title'),
                'company' => null,
                'error' => __('mobile_activation_invalid'),
            ], 'auth');
            return;
        }
        $isAndroid = (bool) preg_match('/Android/i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($isAndroid && !isset($_GET['page'])) {
            Response::redirect($svc->mobileAppOpenIntent($app, $code, $company, $svc->appOpenUrl($code, $app, true)));
            return;
        }
        $apks = new MobileAppApkService();
        $erpBase = $apks->erpBaseUrlForCompany($company);
        $branded = new MobileAppBrandedService($apks);
        $hrConfig = (new MobileAppConfigService())->findByCompanyId((int) $company['id']);
        $this->view('shared/app-open', [
            'title' => __('mobile_app_short_' . $app),
            'app' => $app,
            'appLabel' => __('mobile_app_short_' . $app),
            'companyName' => $branded->names($company)[rateb_locale() === 'ar' ? 'ar' : 'en'],
            'companyLogo' => $branded->iconUrl($company, $hrConfig, $erpBase),
            'openUrl' => $isAndroid ? $svc->appOpenUrl($code, $app) : '',
            'apkUrl' => (string) ($row['url'] ?? ''),
            'activationCode' => MobileAppActivationService::format($code),
        ], 'auth');
    }

    /** When HR is on, show ERP + Customer on the public activation page (shared platform APKs). */
    private function ensureCompanionMobileApps(MobileAppApkService $apks, int $companyId): void
    {
        if ($companyId < 1) {
            return;
        }
        $company = (new Company())->find($companyId);
        if (!is_array($company) || !$apks->isEnabled('hr', $company)) {
            return;
        }
        foreach (['erp', 'customer'] as $app) {
            if (!$apks->isEnabled($app, $company)) {
                $apks->setEnabledInSettings($app, $companyId, true);
            }
        }
    }

    /** GET /downloads/unified-hr.apk — canonical unified HR only (never a company branded slot). */
    public function downloadUnifiedHrApk(): void
    {
        $pub = (new MobileAppApkService())->publishedBuild('hr');
        if ($pub === null || !is_file($pub['path'])) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo __('mobile_apps_apk_unavailable');
            return;
        }
        $vc = (int) ($pub['version_code'] ?? 0);
        $name = 'rateb-hr-unified-sa.rateb.hr.mobile'
            . ($vc > 0 ? '-b' . $vc : '') . '.apk';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . (string) filesize($pub['path']));
        header('X-Rateb-Apk-Package: sa.rateb.hr.mobile');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($pub['path']);
    }

    /** GET /downloads/company/{key} or {key}.apk — serves published branded builds (when static file is missing). */
    public function downloadCompanyBrandedApk(array $params = []): void
    {
        $raw = trim((string) ($params['key'] ?? ''));
        if (str_ends_with(strtolower($raw), '.apk')) {
            $raw = substr($raw, 0, -4);
        }
        if (!MobileAppBrandedService::validKey($raw)) {
            $this->renderBrandedApkUnavailable(404);
            return;
        }
        $branded = new MobileAppBrandedService();
        $pub = $branded->published($raw);
        if ($pub === null || !is_file($pub['path'])) {
            $this->renderBrandedApkUnavailable(404);
            return;
        }
        $pkg = (string) ($pub['package'] ?? '');
        $vc = (int) ($pub['version_code'] ?? 0);
        $name = 'rateb-hr-' . $raw . ($vc > 0 ? '-b' . $vc : '') . '.apk';
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . (string) filesize($pub['path']));
        if ($pkg !== '') {
            header('X-Rateb-Apk-Package: ' . $pkg);
        }
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($pub['path']);
    }

    private function renderBrandedApkUnavailable(int $status): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        $title = function_exists('__') ? __('mobile_apps_apk_not_built_title') : 'APK not available';
        $body = function_exists('__') ? __('mobile_apps_apk_not_built_body') : 'This company app has not been built yet.';
        $dir = rateb_locale() === 'ar' ? 'rtl' : 'ltr';
        $lang = rateb_locale() === 'ar' ? 'ar' : 'en';
        echo '<!DOCTYPE html><html lang="' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '" dir="' . $dir . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
            . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title></head><body style="font-family:system-ui,sans-serif;padding:1.5rem;line-height:1.6">'
            . '<h1 style="font-size:1.1rem">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p>'
            . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
    }

    private function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
