<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Marketing;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\CmsService;
use Rateb\App\Website\Career\CareerApplicationService;
use Rateb\App\Website\Career\CareerPortalAuthService;
use Rateb\App\Website\Career\CareerSeoService;
use Rateb\App\Website\TenantMediaService;
use Rateb\App\Website\WebsiteContext;

/**
 * Phase WEBSITE-06 — Candidate portal (register, login, track, save jobs).
 */
final class CareerCandidateController extends Controller
{
    private function ensureWebsite(): bool
    {
        if (!class_exists(WebsiteContext::class) || WebsiteContext::current() === null) {
            $this->notFound();
            return false;
        }

        return true;
    }

    public function showRegister(): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        $auth = new CareerPortalAuthService();
        if ($auth->isLoggedIn()) {
            Response::redirect(rateb_url('site/candidate'));
            return;
        }
        $this->renderCandidate('marketing/candidate/register', __('register') ?: 'Register', 'register');
    }

    public function register(): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('site/candidate/register'));
            return;
        }
        $result = (new CareerPortalAuthService())->register($_POST);
        if (!($result['ok'] ?? false)) {
            SessionManager::flash('error', (string) ($result['error'] ?? 'register_failed'));
            Response::redirect(rateb_url('site/candidate/register'));
            return;
        }
        SessionManager::flash('success', __('register_ok') ?: 'Account created');
        Response::redirect(rateb_url('site/candidate'));
    }

    public function showLogin(): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        $auth = new CareerPortalAuthService();
        if ($auth->isLoggedIn()) {
            Response::redirect(rateb_url('site/candidate'));
            return;
        }
        $this->renderCandidate('marketing/candidate/login', __('login') ?: 'Login', 'login');
    }

    public function login(): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('site/candidate/login'));
            return;
        }
        $result = (new CareerPortalAuthService())->login(
            (string) $this->input('email', ''),
            (string) $this->input('password', '')
        );
        if (!($result['ok'] ?? false)) {
            SessionManager::flash('error', __('invalid_credentials') ?: 'Invalid credentials');
            Response::redirect(rateb_url('site/candidate/login'));
            return;
        }
        Response::redirect(rateb_url('site/candidate'));
    }

    public function logout(): void
    {
        (new CareerPortalAuthService())->logout();
        SessionManager::flash('success', __('logout_ok') ?: 'Logged out');
        Response::redirect(rateb_url('site/careers'));
    }

    public function dashboard(): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        $auth = new CareerPortalAuthService();
        $user = $auth->currentUser();
        if ($user === null) {
            Response::redirect(rateb_url('site/candidate/login'));
            return;
        }
        $apps = new CareerApplicationService();
        $this->renderCandidate('marketing/candidate/dashboard', __('portal_dashboard') ?: 'Dashboard', 'dashboard', [
            'portalUser' => $user,
            'applications' => $apps->applicationsForUser((int) $user['id']),
            'savedJobs' => $apps->savedJobsForUser((int) $user['id']),
        ]);
    }

    public function applications(): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        $auth = new CareerPortalAuthService();
        $user = $auth->currentUser();
        if ($user === null) {
            Response::redirect(rateb_url('site/candidate/login'));
            return;
        }
        $this->renderCandidate('marketing/candidate/applications', __('applications') ?: 'Applications', 'applications', [
            'portalUser' => $user,
            'applications' => (new CareerApplicationService())->applicationsForUser((int) $user['id']),
        ]);
    }

    public function withdraw(int $id): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('site/candidate/applications'));
            return;
        }
        $auth = new CareerPortalAuthService();
        $user = $auth->currentUser();
        if ($user === null) {
            Response::redirect(rateb_url('site/candidate/login'));
            return;
        }
        $ok = (new CareerApplicationService())->withdraw($id, (int) $user['id']);
        SessionManager::flash($ok ? 'success' : 'error', $ok ? __('application_withdrawn') : __('invalid_request'));
        Response::redirect(rateb_url('site/candidate/applications'));
    }

    public function savedJobs(): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        $auth = new CareerPortalAuthService();
        $user = $auth->currentUser();
        if ($user === null) {
            Response::redirect(rateb_url('site/candidate/login'));
            return;
        }
        $this->renderCandidate('marketing/candidate/saved', __('saved_jobs') ?: 'Saved Jobs', 'saved', [
            'portalUser' => $user,
            'savedJobs' => (new CareerApplicationService())->savedJobsForUser((int) $user['id']),
        ]);
    }

    public function saveJob(int $careerId): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('site/careers'));
            return;
        }
        $auth = new CareerPortalAuthService();
        $user = $auth->currentUser();
        if ($user === null) {
            Response::redirect(rateb_url('site/candidate/login'));
            return;
        }
        (new CareerApplicationService())->saveJob((int) $user['id'], $careerId);
        Response::redirect(rateb_url('site/candidate/saved'));
    }

    public function unsaveJob(int $careerId): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        if (!$this->validateCsrf()) {
            Response::redirect(rateb_url('site/candidate/saved'));
            return;
        }
        $auth = new CareerPortalAuthService();
        $user = $auth->currentUser();
        if ($user === null) {
            Response::redirect(rateb_url('site/candidate/login'));
            return;
        }
        (new CareerApplicationService())->unsaveJob((int) $user['id'], $careerId);
        Response::redirect(rateb_url('site/candidate/saved'));
    }

    public function profile(): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        $auth = new CareerPortalAuthService();
        $user = $auth->currentUser();
        if ($user === null) {
            Response::redirect(rateb_url('site/candidate/login'));
            return;
        }
        $this->renderCandidate('marketing/candidate/profile', __('profile') ?: 'Profile', 'profile', [
            'portalUser' => $user,
        ]);
    }

    public function updateProfile(): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('site/candidate/profile'));
            return;
        }
        $auth = new CareerPortalAuthService();
        $user = $auth->currentUser();
        if ($user === null) {
            Response::redirect(rateb_url('site/candidate/login'));
            return;
        }
        try {
            $auth->updateProfile((int) $user['id'], $_POST);
            if (isset($_FILES['resume']) && is_array($_FILES['resume']) && !empty($_FILES['resume']['tmp_name'])) {
                $upload = (new TenantMediaService())->upload($_FILES['resume']);
                if (($upload['ok'] ?? false) === true) {
                    $auth->updateResume((int) $user['id'], (int) $upload['id'], (string) $upload['path']);
                }
            }
            SessionManager::flash('success', __('portal_profile_saved') ?: 'Profile saved');
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        Response::redirect(rateb_url('site/candidate/profile'));
    }

    /** @param array<string, mixed> $extra */
    private function renderCandidate(string $view, string $title, string $section, array $extra = []): void
    {
        $cms = new CmsService();
        $seo = new CareerSeoService();
        $this->view($view, array_merge([
            'title' => $title,
            'meta' => $seo->portalMeta($section, $title),
            'menuItems' => $cms->menuItems(),
            'theme' => $cms->theme(),
            'analytics' => $cms->analytics(),
            'csrf' => Csrf::token(),
            'isCareerPage' => true,
            'candidateSection' => $section,
        ], $extra), 'marketing-careers');
    }
}
