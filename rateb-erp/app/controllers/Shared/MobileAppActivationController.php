<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Controller;
use Rateb\App\Core\IpRateLimiter;
use Rateb\App\Core\Response;
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
                Response::redirect(rateb_url('m/activate/' . MobileAppActivationService::format($code)));
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
        $erpBase = $apks->erpBaseUrlForCompany($company);
        $branded = new MobileAppBrandedService($apks);
        $names = $branded->names($company);
        $hrConfig = (new MobileAppConfigService())->findByCompanyId((int) $company['id']);
        $erpHost = (string) (parse_url($erpBase, PHP_URL_HOST) ?? '');
        $hrPublished = $apks->publishedBuild('hr');
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
            'unifiedHrApk' => is_array($hrPublished) ? (string) ($hrPublished['url'] ?? '') : '',
            'useUnifiedHr' => !$apks->companyHasBrandedBuild('hr', $company),
        ]), 'auth');
    }

    private function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
