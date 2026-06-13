<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\RateLimiter;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\User;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\LoginActivityService;

/** Single sign-in — routes users to admin or company by role. */
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

        if (!RateLimiter::attempt('erp_login_' . md5($email), 5, 300)) {
            SessionManager::flash('error', __('too_many_attempts'));
            Response::redirect(rateb_url('login'));
        }

        $user = Auth::attemptAuto($email, $password);
        (new LoginActivityService())->record($user ? (int) $user['id'] : null, $email, $user !== null);

        if (!$user) {
            SessionManager::flash('error', __('invalid_credentials'));
            Response::redirect($next !== '' ? rateb_url('login?next=' . rawurlencode($next)) : rateb_url('login'));
        }

        if (!empty($user['locale']) && in_array($user['locale'], RATEB_SUPPORTED_LOCALES, true)) {
            $_SESSION['rateb_locale'] = $user['locale'];
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
