<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Marketing;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\IpRateLimiter;
use Rateb\App\Core\RateLimiter;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\CmsLead;
use Rateb\App\Models\Plan;
use Rateb\App\Models\User;
use Rateb\App\Services\AccountLockoutService;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\CmsService;
use Rateb\App\Services\CustomerRegistrationService;
use Rateb\App\Services\LoginActivityService;
use Rateb\App\Services\RememberMeService;
use Rateb\App\Services\TwoFactorService;

final class MarketingAuthController extends Controller
{
    public function showLogin(): void
    {
        if (SessionManager::get('_rateb_2fa_user_id')) {
            $this->renderAuth('marketing/auth/two-factor', __('two_factor_verify'), [
                'csrf' => Csrf::token(),
            ]);
            return;
        }

        $this->renderAuth('marketing/auth/login', __('cms_customer_login'), [
            'csrf' => Csrf::token(),
            'next' => $this->safeNextUrl((string) ($_GET['next'] ?? '')),
        ]);
    }

    public function login(): void
    {
        $next = $this->safeNextUrl((string) $this->input('next', ''));
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('site/login'));
        }

        $email = trim((string) $this->input('email', ''));
        $password = (string) $this->input('password', '');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        if (!RateLimiter::attempt('erp_customer_login_' . md5($email), 5, 300)
            || !IpRateLimiter::attempt('erp_customer_login_ip_' . md5($ip), 20, 900)) {
            SessionManager::flash('error', __('too_many_attempts'));
            Response::redirect(rateb_url('site/login'));
        }

        $userModel = new User();
        $preUser = $userModel->findByEmail($email);
        $lockout = new AccountLockoutService();
        if ($lockout->isLocked($preUser)) {
            (new LoginActivityService())->record($preUser ? (int) $preUser['id'] : null, $email, false);
            SessionManager::flash('error', __('account_locked'));
            Response::redirect(rateb_url('site/login'));
        }

        $user = Auth::attempt($email, $password, 'company');
        (new LoginActivityService())->record($user ? (int) $user['id'] : null, $email, $user !== null);

        if (!$user) {
            $lockout->recordFailure($email);
            if ($preUser && (int) ($preUser['is_super_admin'] ?? 0) === 1) {
                SessionManager::flash('error', __('cms_admin_use_staff_login'));
            } else {
                SessionManager::flash('error', __('invalid_credentials'));
            }
            $redirect = $next !== '' ? rateb_url('site/login?next=' . rawurlencode($next)) : rateb_url('site/login');
            Response::redirect($redirect);
        }

        $lockout->clearLock((int) $user['id']);

        if ((new TwoFactorService())->needsVerification($user)) {
            SessionManager::forget('rateb_user_id');
            SessionManager::forget('rateb_company_id');
            SessionManager::forget('rateb_is_super_admin');
            SessionManager::forget('rateb_portal');
            SessionManager::set('_rateb_2fa_user_id', (int) $user['id']);
            SessionManager::set('_rateb_2fa_next', $next);
            Response::redirect(rateb_url('site/login'));
        }

        $this->finishLogin($user, $next);
    }

    public function verifyTwoFactor(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('site/login'));
        }
        $userId = (int) SessionManager::get('_rateb_2fa_user_id', 0);
        $user = (new User())->find($userId);
        if (!$user || (int) ($user['is_super_admin'] ?? 0) === 1) {
            Response::redirect(rateb_url('site/login'));
        }
        $code = (string) $this->input('code', '');
        if (!(new TwoFactorService())->verifyLogin($user, $code)) {
            (new LoginActivityService())->record($userId, (string) $user['email'], false);
            SessionManager::flash('error', __('two_factor_invalid'));
            Response::redirect(rateb_url('site/login'));
        }
        Auth::loginUser($user);
        SessionManager::forget('_rateb_2fa_user_id');
        $next = (string) SessionManager::get('_rateb_2fa_next', '');
        SessionManager::forget('_rateb_2fa_next');
        $this->finishLogin($user, $next);
    }

    public function showRegister(): void
    {
        $planSlug = $this->resolveRegisterPlanSlug((string) ($_GET['plan'] ?? ''));
        $selectedPlan = $this->loadRegisterPlan($planSlug);
        $this->renderAuth('marketing/auth/register', __('cms_register'), [
            'csrf' => Csrf::token(),
            'selectedPlan' => $selectedPlan,
            'planSlug' => $selectedPlan ? (string) ($selectedPlan['slug'] ?? '') : '',
        ]);
    }

    public function register(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('site/register'));
        }

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!RateLimiter::attempt('erp_customer_register_ip_' . md5($ip), 5, 3600)) {
            SessionManager::flash('error', __('too_many_attempts'));
            Response::redirect(rateb_url('site/register'));
        }

        $companyName = trim((string) $this->input('company_name', ''));
        $contactName = trim((string) $this->input('name', ''));
        $email = trim((string) $this->input('email', ''));
        $phone = trim((string) $this->input('phone', ''));
        $password = (string) $this->input('password', '');
        $passwordConfirm = (string) $this->input('password_confirm', '');

        if ($companyName === '' || $contactName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            SessionManager::flash('error', __('cms_form_required'));
            Response::redirect(rateb_url('site/register'));
        }
        if (strlen($password) < 8) {
            SessionManager::flash('error', __('cms_password_min'));
            Response::redirect(rateb_url('site/register'));
        }
        if ($password !== $passwordConfirm) {
            SessionManager::flash('error', __('cms_password_mismatch'));
            Response::redirect(rateb_url('site/register'));
        }

        $planSlug = $this->resolveRegisterPlanSlug((string) $this->input('plan_slug', ''));

        try {
            $result = (new CustomerRegistrationService())->register(
                $companyName,
                $contactName,
                $email,
                $password,
                $phone,
                $planSlug
            );
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            Response::redirect(rateb_url('site/register'));
        }

        $user = (new User())->find($result['user_id']);
        if (!$user) {
            SessionManager::flash('error', __('cms_register_unavailable'));
            Response::redirect(rateb_url('site/register'));
        }

        (new CmsLead())->create([
            'lead_type' => 'contact',
            'name' => $contactName,
            'email' => $email,
            'phone' => $phone,
            'company' => $companyName,
            'message' => 'Self-registration',
            'status' => 'won',
            'source_page' => 'register',
            'ip_address' => $ip,
        ]);

        Auth::loginUser($user);
        (new User())->updateLastLogin((int) $user['id']);
        (new AuditService())->log('register', 'company', $result['company_id'], [
            'user_id' => $result['user_id'],
            'email' => $email,
        ]);
        SessionManager::flash('success', __('cms_register_ok'));
        Response::redirect(rateb_url(Auth::homePath()));
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
        (new AuditService())->log('login', 'user', (int) $user['id'], ['portal' => 'company']);
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

    /** @param array<string, mixed> $extra */
    private function renderAuth(string $view, string $title, array $extra = []): void
    {
        $cms = new CmsService();
        $this->view($view, array_merge([
            'title' => $title,
            'meta' => $cms->metaTags('auth', $title),
            'menuItems' => $cms->menuItems(),
            'theme' => $cms->theme(),
            'analytics' => $cms->analytics(),
        ], $extra), 'marketing-auth');
    }

    private function resolveRegisterPlanSlug(string $raw): string
    {
        $slug = strtolower(trim($raw));
        return in_array($slug, ['starter', 'professional', 'enterprise'], true) ? $slug : '';
    }

    /** @return array<string, mixed>|null */
    private function loadRegisterPlan(string $planSlug): ?array
    {
        if ($planSlug === '') {
            return null;
        }
        $plan = (new Plan())->queryOne(
            'SELECT * FROM rateb_plans WHERE slug = :slug AND is_active = 1 LIMIT 1',
            ['slug' => $planSlug]
        );

        return $plan ?: null;
    }
}
