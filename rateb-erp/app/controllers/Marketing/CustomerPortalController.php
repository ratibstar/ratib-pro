<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Marketing;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\User;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\CmsService;
use Rateb\App\Services\CustomerPortalService;

final class CustomerPortalController extends Controller
{
    public function index(): void
    {
        $this->renderPortal('marketing/portal/index', __('portal_dashboard'), 'home', [
            'portal' => (new CustomerPortalService())->snapshot(),
        ]);
    }

    public function profile(): void
    {
        $user = Auth::user();
        $this->renderPortal('marketing/portal/profile', __('profile'), 'profile', [
            'user' => $user,
        ]);
    }

    public function updateProfile(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('site/portal/profile'));
        }
        $user = Auth::user();
        if (!$user) {
            Response::redirect(rateb_url('site/login'));
        }
        $name = trim((string) $this->input('name', ''));
        $phone = trim((string) $this->input('phone', ''));
        $password = (string) $this->input('password', '');
        $passwordConfirm = (string) $this->input('password_confirm', '');

        if ($name === '') {
            SessionManager::flash('error', __('cms_form_required'));
            Response::redirect(rateb_url('site/portal/profile'));
        }
        if ($password !== '' && strlen($password) < 8) {
            SessionManager::flash('error', __('cms_password_min'));
            Response::redirect(rateb_url('site/portal/profile'));
        }
        if ($password !== '' && $password !== $passwordConfirm) {
            SessionManager::flash('error', __('cms_password_mismatch'));
            Response::redirect(rateb_url('site/portal/profile'));
        }

        $data = ['name' => $name, 'phone' => $phone];
        $locale = trim((string) $this->input('locale', ''));
        if ($locale !== '' && in_array($locale, RATEB_SUPPORTED_LOCALES, true)) {
            $data['locale'] = $locale;
            $_SESSION['rateb_locale'] = $locale;
        }
        if ($password !== '') {
            $data['password'] = password_hash($password, PASSWORD_DEFAULT);
        }

        (new User())->update((int) $user['id'], $data);
        (new AuditService())->log('update', 'user', (int) $user['id'], ['context' => 'customer_portal']);
        SessionManager::flash('success', __('portal_profile_saved'));
        Response::redirect(rateb_url('site/portal/profile'));
    }

    public function notifications(): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::redirect(rateb_url('site/login'));
        }
        $companyId = (int) SessionManager::get('rateb_company_id', 0);
        $items = (new CustomerPortalService())->notifications($companyId, (int) $user['id']);
        $this->renderPortal('marketing/portal/notifications', __('notifications'), 'notifications', [
            'notifications' => $items,
        ]);
    }

    public function logout(): void
    {
        Auth::logout();
        SessionManager::flash('success', __('logout_ok'));
        Response::redirect(rateb_url('site'));
    }

    /** @param array<string, mixed> $extra */
    private function renderPortal(string $view, string $title, string $section, array $extra = []): void
    {
        $cms = new CmsService();
        $this->view($view, array_merge([
            'title' => $title,
            'meta' => $cms->metaTags('portal', $title),
            'menuItems' => $cms->menuItems(),
            'theme' => $cms->theme(),
            'analytics' => $cms->analytics(),
            'csrf' => Csrf::token(),
            'isPortalPage' => true,
            'portalSection' => $section,
        ], $extra), 'marketing');
    }
}
