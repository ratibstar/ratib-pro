<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\IpRateLimiter;
use Rateb\App\Core\RateLimiter;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\User;
use Rateb\App\Services\AccountLockoutService;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\LoginActivityService;
use Rateb\App\Services\RememberMeService;
use Rateb\App\Services\TwoFactorService;

final class LoginController extends Controller
{
    public function showLogin(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
        if (Auth::check()) {
            Response::redirect(rateb_url(Auth::homePath()));
            return;
        }

        // Recover from duplicate session cookies (path=/ vs /rateb-erp/public) that break CSRF.
        // Purge ALL path variants, then destroy+start a clean session and issue CSRF for this page.
        $err = (string) ($_GET['err'] ?? '');
        if ($err === 'csrf' || $err === 'session') {
            SessionManager::purgeAllAuthCookies();
            SessionManager::destroy();
            // destroy() already starts a fresh session — pin its cookie, do NOT purge again.
            SessionManager::reissueCanonicalSessionCookie();
            // Issue a fresh CSRF token into the new session so the first login attempt works.
            Csrf::token();
        }

        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            IpRateLimiter::reset('erp_login_ip_' . md5($ip));
        }

        (new AccountLockoutService())->unlockExpired();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $pairToken = isset($_GET['barcode_pair'])
                ? preg_replace('/[^a-f0-9]/', '', strtolower((string) $_GET['barcode_pair']))
                : '';
            if (strlen($pairToken) === 32) {
                $user = (new \Rateb\App\Services\BarcodeLoginService())->pairConsumeForLogin($pairToken);
                if ($user && Auth::loginUser($user)) {
                    if (!empty($user['locale']) && in_array($user['locale'], RATEB_SUPPORTED_LOCALES, true)) {
                        $_SESSION['rateb_locale'] = $user['locale'];
                        if (function_exists('rateb_set_locale_cookie')) {
                            rateb_set_locale_cookie((string) $user['locale']);
                        }
                    }
                    (new User())->updateLastLogin((int) $user['id']);
                    (new AuditService())->log('login', 'user', (int) $user['id'], ['method' => 'barcode_pair']);
                    Response::redirect(rateb_url(Auth::homePath()));
                }
            }
        }

        if (SessionManager::get('_rateb_2fa_user_id')) {
            $this->view('shared/auth/two-factor', [
                'title' => __('two_factor_verify'),
                'csrf' => Csrf::token(),
            ], 'auth');
            return;
        }

        $branchPortal = $this->resolveBranchPortalFromRequest();

        if (Auth::check() && $branchPortal) {
            $branchId = (int) ($branchPortal['id'] ?? 0);
            if ($branchId > 0) {
                $user = Auth::user();
                $companyId = (int) ($user['company_id'] ?? 0);
                $branchSvc = new \Rateb\App\Services\BranchService();
                $isSuper = (bool) SessionManager::get('rateb_is_super_admin');
                if ($isSuper || $branchSvc->userMayUsePortalBranch((int) $user['id'], $branchId, $companyId)) {
                    if ($isSuper) {
                        $cid = (int) ($branchPortal['company_id'] ?? 0);
                        if ($cid > 0) {
                            SessionManager::set('rateb_ops_company_id', $cid);
                            TenantContext::setCompanyId($cid);
                        }
                    }
                    SessionManager::set('rateb_portal_branch_id', $branchId);
                    \Rateb\App\Core\BranchContext::reset();
                    Response::redirect(rateb_url(Auth::homePath()));
                }
            }
        }

        $this->view('shared/auth/login', [
            'title' => __('login'),
            'csrf' => Csrf::token(),
            'next' => $this->safeNextUrl((string) ($_GET['next'] ?? '')),
            'branchPortal' => $branchPortal,
            'loginError' => $this->resolveLoginErrorFromRequest(),
            'agencyLoginHint' => $this->agencyLoginHint(),
        ], 'auth');
    }

    private function loginRedirect(string $errCode = ''): void
    {
        $url = function_exists('rateb_list_url')
            ? rateb_list_url('login', $errCode !== '' ? ['err' => $errCode] : [])
            : rateb_url('login');
        Response::redirect($url);
    }

    private function resolveLoginErrorFromRequest(): string
    {
        $map = [
            'credentials' => __('invalid_credentials'),
            'csrf' => __('invalid_request'),
            'locked' => __('account_locked'),
            'rate' => (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host())
                ? __('too_many_attempts_agency')
                : __('too_many_attempts'),
            'session' => __('login_session_expired'),
            'db' => __('db_error_title'),
            'inactive' => __('login_user_inactive'),
            'no_company' => __('login_no_company'),
            'company_inactive' => __('login_company_inactive'),
            'access' => __('company_access_denied'),
        ];
        $code = strtolower(trim((string) ($_GET['err'] ?? '')));

        return $map[$code] ?? '';
    }

    private function agencyLoginHint(): string
    {
        if (!function_exists('rateb_is_agency_erp_host') || !rateb_is_agency_erp_host()) {
            return '';
        }

        return __('agency_erp_login_hint');
    }

    /** @return array{email_max:int,email_decay:int,ip_max:int,ip_decay:int,ip_enabled:bool} */
    private function loginRatePolicy(): array
    {
        if (function_exists('rateb_erp_login_rate_policy')) {
            return rateb_erp_login_rate_policy();
        }

        return [
            'email_max' => 5,
            'email_decay' => 300,
            'ip_max' => 20,
            'ip_decay' => 900,
            'ip_enabled' => true,
        ];
    }

    /** @return array<string, mixed>|null */
    private function resolveBranchPortalFromRequest(): ?array
    {
        $branchSvc = new \Rateb\App\Services\BranchService();
        $branchId = $branchSvc->resolvePortalBranchIdFromRequest();
        if ($branchId < 1) {
            SessionManager::forget('_rateb_login_branch_id');
            return null;
        }
        $branch = $branchSvc->findActiveForPortal($branchId);
        if (!$branch) {
            SessionManager::flash('error', __('branch_portal_invalid'));
            SessionManager::forget('_rateb_login_branch_id');
            return null;
        }
        SessionManager::set('_rateb_login_branch_id', $branchId);
        return $branch;
    }

    public function login(): void
    {
        $next = $this->safeNextUrl((string) $this->input('next', ''));
        try {
            if (!$this->validateCsrf()) {
                $this->loginRedirect('csrf');
            }

            $email = trim((string) $this->input('email', ''));
            $password = (string) $this->input('password', '');
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $emailKey = 'erp_login_' . md5($email);
            $ipKey = 'erp_login_ip_' . md5($ip);
            $ratePolicy = $this->loginRatePolicy();
            $emailMax = (int) ($ratePolicy['email_max'] ?? 5);
            $emailDecay = (int) ($ratePolicy['email_decay'] ?? 300);
            $ipMax = (int) ($ratePolicy['ip_max'] ?? 20);
            $ipDecay = (int) ($ratePolicy['ip_decay'] ?? 900);
            $ipEnabled = !empty($ratePolicy['ip_enabled']);

            if (RateLimiter::isLimited($emailKey, $emailMax)
                || ($ipEnabled && IpRateLimiter::isLimited($ipKey, $ipMax))) {
                $this->loginRedirect('rate');
            }

            $userModel = new User();
            $preUser = $userModel->findByLogin($email);
            $lockout = new AccountLockoutService();
            if ($lockout->isLocked($preUser)) {
                (new LoginActivityService())->record($preUser ? (int) $preUser['id'] : null, $email, false);
                $this->loginRedirect('locked');
            }

            $branchId = (int) SessionManager::get('_rateb_login_branch_id', 0);
            if ($branchId < 1) {
                $branchId = (new \Rateb\App\Services\BranchService())->resolvePortalBranchIdFromRequest();
                if ($branchId > 0) {
                    SessionManager::set('_rateb_login_branch_id', $branchId);
                }
            }

            $user = Auth::attemptAuto($email, $password);
            (new LoginActivityService())->record($user ? (int) $user['id'] : null, $email, $user !== null);

            if (!$user) {
                RateLimiter::attempt($emailKey, $emailMax, $emailDecay);
                if ($ipEnabled) {
                    IpRateLimiter::attempt($ipKey, $ipMax, $ipDecay);
                }
                $lockout->recordFailure($email);
                $errCode = Auth::consumeLoginFailureReason() ?? 'credentials';
                $redirect = function_exists('rateb_list_url')
                    ? rateb_list_url('login', ['err' => $errCode])
                    : rateb_url('login');
                if ($next !== '') {
                    $redirect = (function_exists('rateb_url_query') ? rateb_url_query($redirect, ['next' => $next]) : $redirect);
                }
                Response::redirect($redirect);
            }

            $lockout->clearLock((int) $user['id']);
            RateLimiter::reset($emailKey);
            if ($ipEnabled) {
                IpRateLimiter::reset($ipKey);
            }

            if ((new TwoFactorService())->needsVerification($user)) {
                SessionManager::forget('rateb_user_id');
                SessionManager::forget('rateb_company_id');
                SessionManager::forget('rateb_is_super_admin');
                SessionManager::forget('rateb_portal');
                SessionManager::set('_rateb_2fa_user_id', (int) $user['id']);
                SessionManager::set('_rateb_2fa_next', $next);
                Response::redirect(rateb_url('login'));
            }

            $this->finishLogin($user, $next);
        } catch (\Throwable $e) {
            error_log('RATEB login: ' . $e->getMessage());
            $this->loginRedirect('db');
        }
    }

    public function verifyTwoFactor(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('login'));
        }
        $userId = (int) SessionManager::get('_rateb_2fa_user_id', 0);
        $user = (new User())->find($userId);
        if (!$user) {
            Response::redirect(rateb_url('login'));
        }
        $code = (string) $this->input('code', '');
        if (!(new TwoFactorService())->verifyLogin($user, $code)) {
            (new LoginActivityService())->record($userId, (string) $user['email'], false);
            SessionManager::flash('error', __('two_factor_invalid'));
            Response::redirect(rateb_url('login'));
        }
        Auth::loginUser($user);
        SessionManager::forget('_rateb_2fa_user_id');
        $next = (string) SessionManager::get('_rateb_2fa_next', '');
        SessionManager::forget('_rateb_2fa_next');
        $this->finishLogin($user, $next);
    }

    /** @param array<string, mixed> $user */
    private function finishLogin(array $user, string $next): void
    {
        $branchId = (int) SessionManager::get('_rateb_login_branch_id', 0);
        if ($branchId > 0) {
            $companyId = (int) ($user['company_id'] ?? 0);
            $branchSvc = new \Rateb\App\Services\BranchService();
            $isSuper = (bool) SessionManager::get('rateb_is_super_admin');
            if (!$branchSvc->userMayUsePortalBranch((int) $user['id'], $branchId, $companyId)) {
                Auth::logout();
                SessionManager::flash('error', __('branch_portal_denied'));
                Response::redirect(rateb_branch_portal_url($branchId));
            }
            if ($isSuper) {
                $branch = $branchSvc->findActiveForPortal($branchId);
                $cid = (int) ($branch['company_id'] ?? 0);
                if ($cid > 0) {
                    SessionManager::set('rateb_ops_company_id', $cid);
                    TenantContext::setCompanyId($cid);
                }
            }
            SessionManager::set('rateb_portal_branch_id', $branchId);
            SessionManager::forget('_rateb_login_branch_id');
        }

        if (!empty($user['locale']) && in_array($user['locale'], RATEB_SUPPORTED_LOCALES, true)) {
            $_SESSION['rateb_locale'] = $user['locale'];
            if (function_exists('rateb_set_locale_cookie')) {
                rateb_set_locale_cookie((string) $user['locale']);
            }
        }
        if ($this->input('remember')) {
            (new RememberMeService())->issue((int) $user['id']);
        }
        (new User())->updateLastLogin((int) $user['id']);
        (new AuditService())->log('login', 'user', (int) $user['id']);
        Csrf::clearCookie();
        Response::redirect(Auth::resolvePostLoginUrl($next, $user));
    }

    private function safeNextUrl(string $next): string
    {
        $next = trim($next);
        if ($next === '' || strpos($next, '..') !== false) {
            return '';
        }
        if (preg_match('#^https?://#i', $next)) {
            $appBase = rateb_public_url('');
            if (strpos($next, $appBase) === 0) {
                // ok — ERP app URL
            } elseif (str_contains($next, '/rateb-platform-catalog/')) {
                $host = strtolower((string) parse_url($next, PHP_URL_HOST));
                $reqHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
                if ($host !== '' && $host === $reqHost) {
                    // ok — catalog return on same host
                } else {
                    $next = '';
                }
            } else {
                $next = '';
            }
        } else {
            $next = rateb_public_url(ltrim($next, '/'));
        }
        if ($next !== ''
            && function_exists('rateb_erp_is_dedicated_deployment')
            && rateb_erp_is_dedicated_deployment()
            && Auth::urlIsCustomerPortal($next)) {
            return '';
        }

        return $next;
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect(rateb_url('login'));
    }
}
