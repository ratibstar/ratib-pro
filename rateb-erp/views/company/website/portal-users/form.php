<?php
declare(strict_types=1);
/** @var array<string,mixed>|null $user */
/** @var array<string,string> $old */
/** @var list<string> $types */
/** @var bool|null $subscriptionOk */
/** @var string $csrf */

use Rateb\App\Controllers\Company\WebsitePortalUsersController as PortalUsers;
use Rateb\App\Website\Portal\PortalUserAdminService as PortalAdmin;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$t = [PortalUsers::class, 'label'];
$old = $old ?? [];
$isEdit = is_array($user ?? null);
$id = $isEdit ? (int) $user['id'] : 0;
$val = static fn (string $field): string => (string) ($old[$field] ?? ($user[$field] ?? ''));
$typeLabels = [
    'customer' => $t('Customer', 'عميل'),
    'employer' => $t('Employer', 'صاحب عمل'),
    'partner' => $t('Partner', 'شريك'),
];
$route = static fn (string $sub): string => rateb_url(rateb_app_route('website/portal-users' . $sub));
$action = $isEdit ? $route('/' . $id) : $route('');
$selectedType = $val('portal_type') !== '' ? $val('portal_type') : 'customer';
?>
<link rel="stylesheet" href="<?php echo $e(rateb_asset('css/website-builder.css')); ?>">
<div class="container-fluid py-3 wb-admin">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?php echo $e($title ?? ''); ?></h1>
        <a class="btn btn-outline-secondary" href="<?php echo $e($route('')); ?>"><?php echo $e($t('Back to list', 'العودة للقائمة')); ?></a>
    </div>

    <?php if ($subscriptionOk === false) { ?>
        <div class="alert alert-warning"><?php echo $e($t(
            'This company has no valid subscription. Customer Portal app login will be refused even for approved accounts.',
            'لا يوجد اشتراك صالح لهذه الشركة. سيُرفض دخول تطبيق بوابة العملاء حتى للحسابات المعتمدة.'
        )); ?></div>
    <?php } ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header"><?php echo $e($t('Account details', 'بيانات الحساب')); ?></div>
                <div class="card-body">
                    <form method="post" action="<?php echo $e($action); ?>" autocomplete="off">
                        <input type="hidden" name="_csrf" value="<?php echo $e($csrf); ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="pu_type"><?php echo $e($t('Portal type', 'نوع البوابة')); ?></label>
                                <select class="form-select" id="pu_type" name="portal_type" required>
                                    <?php foreach ($types as $type) { ?>
                                        <option value="<?php echo $e($type); ?>"<?php echo $selectedType === $type ? ' selected' : ''; ?>><?php echo $e($typeLabels[$type] ?? $type); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="pu_email"><?php echo $e($t('Email', 'البريد الإلكتروني')); ?></label>
                                <input class="form-control" type="email" id="pu_email" name="email" maxlength="190" required value="<?php echo $e($val('email')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="pu_name"><?php echo $e($t('Full name', 'الاسم الكامل')); ?></label>
                                <input class="form-control" id="pu_name" name="full_name" maxlength="190" required value="<?php echo $e($val('full_name')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="pu_phone"><?php echo $e($t('Phone', 'الهاتف')); ?></label>
                                <input class="form-control" id="pu_phone" name="phone" maxlength="40" value="<?php echo $e($val('phone')); ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label" for="pu_org"><?php echo $e($t('Organization', 'الجهة')); ?></label>
                                <input class="form-control" id="pu_org" name="organization_name" maxlength="190" value="<?php echo $e($val('organization_name')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="pu_crm"><?php echo $e($t('CRM company ID (optional)', 'رقم شركة CRM (اختياري)')); ?></label>
                                <input class="form-control" id="pu_crm" name="crm_company_id" inputmode="numeric" pattern="[0-9]*" value="<?php echo $e($val('crm_company_id')); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="pu_cust"><?php echo $e($t('ERP customer ID (optional)', 'رقم العميل في ERP (اختياري)')); ?></label>
                                <input class="form-control" id="pu_cust" name="erp_customer_id" inputmode="numeric" pattern="[0-9]*" value="<?php echo $e($val('erp_customer_id')); ?>">
                            </div>
                            <?php if (!$isEdit) { ?>
                                <div class="col-md-6">
                                    <label class="form-label" for="pu_pass"><?php echo $e($t('Password', 'كلمة المرور')); ?></label>
                                    <input class="form-control" type="password" id="pu_pass" name="password" minlength="<?php echo PortalAdmin::MIN_PASSWORD_LENGTH; ?>" autocomplete="new-password" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="pu_pass2"><?php echo $e($t('Confirm password', 'تأكيد كلمة المرور')); ?></label>
                                    <input class="form-control" type="password" id="pu_pass2" name="password_confirmation" minlength="<?php echo PortalAdmin::MIN_PASSWORD_LENGTH; ?>" autocomplete="new-password" required>
                                </div>
                            <?php } ?>
                        </div>
                        <?php if ($isEdit) { ?>
                            <p class="small text-muted mt-3 mb-0"><?php echo $e($t(
                                'Changing the email or portal type signs out all app sessions for this account.',
                                'تغيير البريد أو نوع البوابة ينهي جميع جلسات التطبيق لهذا الحساب.'
                            )); ?></p>
                        <?php } else { ?>
                            <p class="small text-muted mt-3 mb-0"><?php echo $e($t(
                                'New accounts are active with app access not approved.',
                                'الحساب الجديد يكون نشطاً ووصول التطبيق غير معتمد.'
                            )); ?></p>
                        <?php } ?>
                        <button type="submit" class="btn btn-primary mt-3"><?php echo $e($isEdit ? $t('Save', 'حفظ') : $t('Create', 'إنشاء')); ?></button>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($isEdit) {
            $state = (string) ($user['app_access_state'] ?? '');
            $approved = trim((string) ($user['app_access_approved_at'] ?? '')) !== '';
            $active = (string) ($user['status'] ?? '') === 'active';
            $stateText = [
                PortalAdmin::STATE_APPROVED => $t('Approved', 'معتمد'),
                PortalAdmin::STATE_NOT_APPROVED => $t('Not approved', 'غير معتمد'),
                PortalAdmin::STATE_SUSPENDED => $t('Suspended', 'موقوف'),
                PortalAdmin::STATE_PENDING => $t('Pending', 'قيد الانتظار'),
            ][$state] ?? $state;
            ?>
        <div class="col-lg-5">
            <div class="card mb-4">
                <div class="card-header"><?php echo $e($t('Access', 'الوصول')); ?></div>
                <div class="card-body">
                    <dl class="row mb-3">
                        <dt class="col-6"><?php echo $e($t('Account', 'الحساب')); ?></dt>
                        <dd class="col-6"><?php echo $e($active ? $t('Active', 'نشط') : ((string) $user['status'] === 'suspended' ? $t('Suspended', 'موقوف') : $t('Pending', 'قيد الانتظار'))); ?></dd>
                        <dt class="col-6"><?php echo $e($t('App access', 'وصول التطبيق')); ?></dt>
                        <dd class="col-6"><?php echo $e($stateText); ?></dd>
                        <dt class="col-6"><?php echo $e($t('Approved at', 'تاريخ الاعتماد')); ?></dt>
                        <dd class="col-6"><?php echo $e($approved ? $user['app_access_approved_at'] : '—'); ?></dd>
                        <dt class="col-6"><?php echo $e($t('Active app sessions', 'جلسات التطبيق النشطة')); ?></dt>
                        <dd class="col-6"><?php echo (int) ($user['active_sessions'] ?? 0); ?></dd>
                        <dt class="col-6"><?php echo $e($t('Last app use', 'آخر استخدام للتطبيق')); ?></dt>
                        <dd class="col-6"><?php echo $e($user['sessions_last_used_at'] ?? '—'); ?></dd>
                        <dt class="col-6"><?php echo $e($t('Last web login', 'آخر دخول للموقع')); ?></dt>
                        <dd class="col-6"><?php echo $e($user['last_login_at'] ?? '—'); ?></dd>
                    </dl>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($approved) { ?>
                            <form method="post" action="<?php echo $e($route('/' . $id . '/app-access/revoke')); ?>"><input type="hidden" name="_csrf" value="<?php echo $e($csrf); ?>"><button type="submit" class="btn btn-outline-warning"><?php echo $e($t('Revoke App Access', 'إلغاء وصول التطبيق')); ?></button></form>
                        <?php } else { ?>
                            <form method="post" action="<?php echo $e($route('/' . $id . '/app-access/approve')); ?>"><input type="hidden" name="_csrf" value="<?php echo $e($csrf); ?>"><button type="submit" class="btn btn-success"><?php echo $e($t('Approve', 'اعتماد')); ?></button></form>
                        <?php } ?>
                        <form method="post" action="<?php echo $e($route('/' . $id . '/status')); ?>"><input type="hidden" name="_csrf" value="<?php echo $e($csrf); ?>"><input type="hidden" name="status" value="<?php echo $active ? 'suspended' : 'active'; ?>"><button type="submit" class="btn <?php echo $active ? 'btn-outline-danger' : 'btn-outline-success'; ?>"><?php echo $e($active ? $t('Suspend', 'تعليق') : $t('Activate', 'تفعيل')); ?></button></form>
                        <form method="post" action="<?php echo $e($route('/' . $id . '/sessions/revoke')); ?>"><input type="hidden" name="_csrf" value="<?php echo $e($csrf); ?>"><button type="submit" class="btn btn-outline-secondary"><?php echo $e($t('Revoke Sessions', 'إنهاء الجلسات')); ?></button></form>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><?php echo $e($t('Change Password', 'تغيير كلمة المرور')); ?></div>
                <div class="card-body">
                    <form method="post" action="<?php echo $e($route('/' . $id . '/password')); ?>" autocomplete="off">
                        <input type="hidden" name="_csrf" value="<?php echo $e($csrf); ?>">
                        <div class="mb-3">
                            <label class="form-label" for="pu_newpass"><?php echo $e($t('New password', 'كلمة المرور الجديدة')); ?></label>
                            <input class="form-control" type="password" id="pu_newpass" name="password" minlength="<?php echo PortalAdmin::MIN_PASSWORD_LENGTH; ?>" autocomplete="new-password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="pu_newpass2"><?php echo $e($t('Confirm password', 'تأكيد كلمة المرور')); ?></label>
                            <input class="form-control" type="password" id="pu_newpass2" name="password_confirmation" minlength="<?php echo PortalAdmin::MIN_PASSWORD_LENGTH; ?>" autocomplete="new-password" required>
                        </div>
                        <p class="small text-muted"><?php echo $e($t('All app sessions will be signed out.', 'سيتم إنهاء جميع جلسات التطبيق.')); ?></p>
                        <button type="submit" class="btn btn-primary"><?php echo $e($t('Change Password', 'تغيير كلمة المرور')); ?></button>
                    </form>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
