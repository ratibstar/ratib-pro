<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\CustomerPortal\PdoCustomerPortalStore;
use Rateb\App\Website\Portal\PortalUserAdminService;
use Rateb\App\Website\WebsiteContext;

/**
 * Website module — company-scoped admin for website / Customer Portal accounts.
 * Route middleware enforces website.portal.view (GET list) and website.portal.manage (everything else).
 */
final class WebsitePortalUsersController extends Controller
{
    private const BASE = 'website/portal-users';
    private const OLD_INPUT = 'portal_user_old';
    private const FORM_FIELDS = ['portal_type', 'email', 'full_name', 'phone', 'organization_name', 'crm_company_id', 'erp_customer_id'];

    public function index(): void
    {
        $service = $this->service();
        $this->view('company/website/portal-users/index', [
            'title' => self::label('Portal Users', 'مستخدمو البوابة'),
            'users' => $service->list(),
            'listLimit' => PortalUserAdminService::LIST_LIMIT,
            'subscriptionOk' => $this->subscriptionOk($service->companyId()),
            'canManage' => function_exists('rateb_can_manage_entity') && rateb_can_manage_entity('website-portal-users'),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function create(): void
    {
        $service = $this->service();
        $this->view('company/website/portal-users/form', [
            'title' => self::label('New portal user', 'مستخدم بوابة جديد'),
            'user' => null,
            'old' => $this->oldInput(),
            'types' => PortalUserAdminService::types(),
            'subscriptionOk' => $this->subscriptionOk($service->companyId()),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function store(): void
    {
        $this->guardPost(self::BASE . '/create');
        $result = $this->service()->create($_POST);
        if (!$result['ok']) {
            $this->failWithInput($result['errors'] ?? [], self::BASE . '/create');
        }
        SessionManager::flash('success', self::label(
            'Portal user created. App access is not approved yet.',
            'تم إنشاء مستخدم البوابة. وصول التطبيق غير معتمد بعد.'
        ));
        $this->redirect(rateb_url(rateb_app_route(self::BASE . '/' . (int) $result['id'] . '/edit')));
    }

    public function edit(array $params): void
    {
        $service = $this->service();
        $user = $service->find((int) ($params['id'] ?? 0));
        if ($user === null) {
            $this->missing();
        }
        $this->view('company/website/portal-users/form', [
            'title' => self::label('Edit portal user', 'تعديل مستخدم البوابة'),
            'user' => $user,
            'old' => $this->oldInput(),
            'types' => PortalUserAdminService::types(),
            'subscriptionOk' => $this->subscriptionOk($service->companyId()),
            'csrf' => Csrf::token(),
        ], 'main');
    }

    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $this->guardPost($this->editRoute($id));
        $result = $this->service()->update($id, $_POST);
        if (!$result['ok']) {
            $this->failWithInput($result['errors'] ?? [], $this->editRoute($id));
        }
        $message = self::label('Saved.', 'تم الحفظ.');
        if (($result['sessions_revoked'] ?? 0) > 0) {
            $message .= ' ' . self::label('App sessions were signed out.', 'تم إنهاء جلسات التطبيق.');
        }
        $this->done($message, $id);
    }

    public function password(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $this->guardPost($this->editRoute($id));
        $result = $this->service()->setPassword(
            $id,
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password_confirmation'] ?? '')
        );
        if (!$result['ok']) {
            $this->fail($result['errors'] ?? [], $this->editRoute($id));
        }
        $this->done(self::label('Password changed. App sessions were signed out.', 'تم تغيير كلمة المرور وإنهاء جلسات التطبيق.'), $id);
    }

    public function status(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $this->guardPost($this->editRoute($id));
        $target = (string) ($_POST['status'] ?? '');
        $service = $this->service();
        if ($target === 'active') {
            $result = $service->activate($id);
            $message = self::label('Account activated. App access is unchanged.', 'تم تفعيل الحساب. حالة وصول التطبيق لم تتغير.');
        } elseif ($target === 'suspended') {
            $result = $service->suspend($id);
            $message = self::label('Account suspended. App sessions were signed out.', 'تم تعليق الحساب وإنهاء جلسات التطبيق.');
        } else {
            $result = ['ok' => false, 'errors' => ['status' => 'invalid_status']];
            $message = '';
        }
        if (!$result['ok']) {
            $this->fail($result['errors'] ?? [], $this->editRoute($id));
        }
        $this->done($message, $id);
    }

    public function approveAppAccess(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $this->guardPost($this->editRoute($id));
        $result = $this->service()->approveAppAccess($id);
        if (!$result['ok']) {
            $this->fail($result['errors'] ?? [], $this->editRoute($id));
        }
        $this->done(self::label('App access approved.', 'تم اعتماد وصول التطبيق.'), $id);
    }

    public function revokeAppAccess(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $this->guardPost($this->editRoute($id));
        $result = $this->service()->revokeAppAccess($id);
        if (!$result['ok']) {
            $this->fail($result['errors'] ?? [], $this->editRoute($id));
        }
        $this->done(self::label('App access revoked and app sessions signed out.', 'تم إلغاء وصول التطبيق وإنهاء جلساته.'), $id);
    }

    public function revokeSessions(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $this->guardPost($this->editRoute($id));
        $result = $this->service()->revokeSessions($id);
        if (!$result['ok']) {
            $this->fail($result['errors'] ?? [], $this->editRoute($id));
        }
        $this->done(
            self::label('App sessions signed out: ', 'تم إنهاء جلسات التطبيق: ') . (int) ($result['sessions_revoked'] ?? 0),
            $id
        );
    }

    public static function label(string $en, string $ar): string
    {
        return function_exists('rateb_locale') && rateb_locale() === 'ar' ? $ar : $en;
    }

    private function service(): PortalUserAdminService
    {
        try {
            $ctx = WebsiteContext::bootForOps();
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', self::label('Select a company first.', 'اختر شركة أولاً.'));
            $this->redirect(rateb_url('admin'));
        }

        return new PortalUserAdminService($ctx->companyId());
    }

    /** Every POST handler calls this before touching the service. */
    private function guardPost(string $backRoute): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url(rateb_app_route($backRoute)));
        }
    }

    private function editRoute(int $id): string
    {
        return $id > 0 ? self::BASE . '/' . $id . '/edit' : self::BASE;
    }

    private function done(string $message, int $id): void
    {
        SessionManager::flash('success', $message);
        $this->redirect(rateb_url(rateb_app_route($this->editRoute($id))));
    }

    /** @param array<string, string> $errors */
    private function fail(array $errors, string $backRoute): void
    {
        if (($errors['account'] ?? '') === 'not_found') {
            $this->missing();
        }
        SessionManager::flash('error', self::errorMessage($errors));
        $this->redirect(rateb_url(rateb_app_route($backRoute)));
    }

    /** @param array<string, string> $errors */
    private function failWithInput(array $errors, string $backRoute): void
    {
        $old = [];
        foreach (self::FORM_FIELDS as $field) {
            if (isset($_POST[$field]) && is_scalar($_POST[$field])) {
                $old[$field] = mb_substr((string) $_POST[$field], 0, 190);
            }
        }
        SessionManager::flash(self::OLD_INPUT, $old);
        $this->fail($errors, $backRoute);
    }

    private function missing(): void
    {
        SessionManager::flash('error', __('not_found'));
        $this->redirect(rateb_url(rateb_app_route(self::BASE)));
    }

    /** @return array<string, string> */
    private function oldInput(): array
    {
        $old = SessionManager::flash(self::OLD_INPUT);

        return is_array($old) ? $old : [];
    }

    private function subscriptionOk(int $companyId): ?bool
    {
        try {
            return (new PdoCustomerPortalStore())->hasValidSubscription($companyId, time());
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @param array<string, string> $errors */
    private static function errorMessage(array $errors): string
    {
        $messages = [
            'invalid_portal_type' => ['Choose a valid portal type.', 'اختر نوع بوابة صحيحاً.'],
            'invalid_email' => ['Enter a valid email address.', 'أدخل بريداً إلكترونياً صحيحاً.'],
            'email_taken' => ['This email is already used for this portal type in this company.', 'هذا البريد مستخدم لنفس نوع البوابة في هذه الشركة.'],
            'full_name_required' => ['Full name is required.', 'الاسم الكامل مطلوب.'],
            'too_long' => ['A field is too long.', 'أحد الحقول أطول من المسموح.'],
            'invalid_link' => ['The linked CRM company or customer was not found in this company.', 'شركة CRM أو العميل المرتبط غير موجود في هذه الشركة.'],
            'password_min' => ['Password must be at least 8 characters.', 'كلمة المرور يجب ألا تقل عن 8 أحرف.'],
            'password_max' => ['Password is too long.', 'كلمة المرور طويلة جداً.'],
            'password_mismatch' => ['Password confirmation does not match.', 'تأكيد كلمة المرور غير مطابق.'],
            'invalid_status' => ['Invalid status.', 'حالة غير صالحة.'],
        ];
        $out = [];
        foreach (array_unique(array_values($errors)) as $code) {
            $pair = $messages[$code] ?? ['Invalid request.', 'طلب غير صالح.'];
            $out[] = self::label($pair[0], $pair[1]);
        }

        return implode(' ', $out);
    }
}
