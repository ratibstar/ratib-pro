<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Controller;
use Rateb\App\Core\IpRateLimiter;
use Rateb\App\Core\Response;
use Rateb\App\Core\View;
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
                Response::redirect(
                    rateb_url('m/activate/' . MobileAppActivationService::format($code) . '?setup=1')
                );
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
            $apks->reconcileCompanySlotPolicy('hr', $company);
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
        $hrPublished = $apks->publishedBuild('hr');
        $unifiedDl = $this->unifiedHrDownloadMeta($hrPublished);
        $useUnifiedHr = !$apks->companyHasBrandedBuild('hr', $company);
        $wantsQrHandoff = $useUnifiedHr
            && isset($_GET['setup'])
            && (string) $_GET['setup'] === '1'
            && $this->isAndroidClient();
        if ($wantsQrHandoff) {
            $intent = $svc->mobileAppIntentUrl($code);
            if ($intent !== '') {
                $this->renderAndroidHandoff(
                    $intent,
                    (string) ($unifiedDl['url'] ?? ''),
                    $formatted,
                    $names[rateb_locale() === 'ar' ? 'ar' : 'en']
                );
                return;
            }
        }
        if (str_contains($requestUri, '/m/activate/')) {
            $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
            if ($query === '' || !preg_match('/(?:^|&)setup=1(?:&|$)/', $query)) {
                $query = $query === '' ? 'setup=1' : $query . '&setup=1';
            }
            Response::redirect(rateb_url('app-activate/' . $formatted) . ($query !== '' ? '?' . $query : ''));
            return;
        }
        $this->view('shared/app-activate', array_merge($data, [
            'company' => [
                'id' => (int) ($company['id'] ?? 0),
                'name' => $names[rateb_locale() === 'ar' ? 'ar' : 'en'],
                'logo' => $branded->iconUrl($company, $hrConfig, $erpBase),
            ],
            'code' => MobileAppActivationService::format($code),
            'erpHost' => $erpHost,
            'apps' => $svc->enabledApps($company),
            'adminUrl' => $apks->isEnabled('erp', $company) ? $erpBase . '/admin' : '',
            'unifiedHrApk' => (string) ($unifiedDl['url'] ?? ''),
            'unifiedHrDl' => $unifiedDl,
            'useUnifiedHr' => $useUnifiedHr,
        ]), 'auth');
    }

    private function isAndroidClient(): bool
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (preg_match('/Android|okhttp|wv\)/i', $ua)) {
            return true;
        }
        $ch = strtolower((string) ($_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? ''));
        return $ch === '?1';
    }

    private function renderAndroidHandoff(string $intent, string $apkUrl, string $code, string $companyName): void
    {
        header('Cache-Control: no-store');
        View::render('shared/app-activate-android-handoff', [
            'intent' => $intent,
            'apkUrl' => $apkUrl,
            'code' => $code,
            'companyName' => $companyName,
        ], null);
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

    /**
     * @param array<string, mixed>|null $pub
     *
     * @return array{url:string, size_mb:float, version_code:int, package:string}
     */
    private function unifiedHrDownloadMeta(?array $pub): array
    {
        // Static file (always deployed); unified-hr.apk is an Apache alias in downloads/.htaccess.
        $url = is_array($pub) && ($pub['url'] ?? '') !== ''
            ? (string) $pub['url']
            : rateb_public_url('downloads/' . MobileAppApkService::PUBLISHED_FILES['hr']);
        $size = is_array($pub) ? (int) ($pub['size'] ?? 0) : 0;
        $vc = is_array($pub) ? (int) ($pub['version_code'] ?? 0) : 0;

        $sha = is_array($pub) ? (string) ($pub['sha256'] ?? '') : '';

        $canonical = rateb_public_url('downloads/unified-hr.apk');
        if ($canonical !== '' && $vc > 0) {
            $canonical .= (str_contains($canonical, '?') ? '&' : '?') . 'v=' . $vc;
        }

        return [
            'url' => $canonical !== '' ? $canonical : $url,
            'size_mb' => $size > 0 ? round($size / 1048576, 1) : 0.0,
            'version_code' => $vc,
            'package' => 'sa.rateb.hr.mobile',
            'sha256' => $sha,
            'sha256_short' => $sha !== '' ? substr($sha, 0, 12) : '',
        ];
    }

    private function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
