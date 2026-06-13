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
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $pairToken = isset($_GET['barcode_pair'])
                ? preg_replace('/[^a-f0-9]/', '', strtolower((string) $_GET['barcode_pair']))
                : '';
            if (strlen($pairToken) === 32) {
                $user = (new \Rateb\App\Services\BarcodeLoginService())->pairConsumeForLogin($pairToken);
                if ($user && Auth::loginUser($user)) {
                    if (!empty($user['locale']) && in_array($user['locale'], RATEB_SUPPORTED_LOCALES, true)) {
                        $_SESSION['rateb_locale'] = $user['locale'];
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

        $this->view('shared/auth/login', [
            'title' => __('login'),
            'csrf' => Csrf::token(),
            'next' => $this->safeNextUrl((string) ($_GET['next'] ?? '')),
        ], 'auth');
    }

    public function login(): void
    {
        $next = $this->safeNextUrl((string) $this->input('next', ''));
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('login'));
        }

        $email = trim((string) $this->input('email', ''));
        $password = (string) $this->input('password', '');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (!RateLimiter::attempt('erp_login_' . md5($email), 5, 300)
            || !IpRateLimiter::attempt('erp_login_ip_' . md5($ip), 20, 900)) {
            SessionManager::flash('error', __('too_many_attempts'));
            Response::redirect(rateb_url('login'));
        }

        $userModel = new User();
        $preUser = $userModel->findByEmail($email);
        $lockout = new AccountLockoutService();
        if ($lockout->isLocked($preUser)) {
            (new LoginActivityService())->record($preUser ? (int) $preUser['id'] : null, $email, false);
            SessionManager::flash('error', __('account_locked'));
            Response::redirect(rateb_url('login'));
        }

        $user = Auth::attemptAuto($email, $password);
        (new LoginActivityService())->record($user ? (int) $user['id'] : null, $email, $user !== null);

        if (!$user) {
            $lockout->recordFailure($email);
            SessionManager::flash('error', __('invalid_credentials'));
            Response::redirect($next !== '' ? rateb_url('login?next=' . rawurlencode($next)) : rateb_url('login'));
        }

        $lockout->clearLock((int) $user['id']);

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
        if (!empty($user['locale']) && in_array($user['locale'], RATEB_SUPPORTED_LOCALES, true)) {
            $_SESSION['rateb_locale'] = $user['locale'];
        }
        if ($this->input('remember')) {
            (new RememberMeService())->issue((int) $user['id']);
        }
        (new User())->updateLastLogin((int) $user['id']);
        (new AuditService())->log('login', 'user', (int) $user['id']);
        Response::redirect($next !== '' ? $next : rateb_url(Auth::homePath()));
    }

    private function safeNextUrl(string $next): string
    {
        $next = trim($next);
        if ($next === '' || strpos($next, '..') !== false) {
            return '';
        }
        if (preg_match('#^https?://#i', $next)) {
            $appBase = rateb_public_url('');
            return strpos($next, $appBase) === 0 ? $next : '';
        }
        return rateb_public_url(ltrim($next, '/'));
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect(rateb_url('login'));
    }
}
