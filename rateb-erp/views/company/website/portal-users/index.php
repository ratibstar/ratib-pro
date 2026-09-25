<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $users */
/** @var int $listLimit */
/** @var bool|null $subscriptionOk */
/** @var bool $canManage */
/** @var string $csrf */

use Rateb\App\Controllers\Company\WebsitePortalUsersController as PortalUsers;
use Rateb\App\Website\Portal\PortalUserAdminService as PortalAdmin;

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$t = [PortalUsers::class, 'label'];
$typeLabels = [
    'customer' => $t('Customer', 'عميل'),
    'employer' => $t('Employer', 'صاحب عمل'),
    'partner' => $t('Partner', 'شريك'),
];
$stateBadges = [
    PortalAdmin::STATE_APPROVED => ['bg-success', $t('Approved', 'معتمد')],
    PortalAdmin::STATE_NOT_APPROVED => ['bg-secondary', $t('Not approved', 'غير معتمد')],
    PortalAdmin::STATE_SUSPENDED => ['bg-danger', $t('Suspended', 'موقوف')],
    PortalAdmin::STATE_PENDING => ['bg-warning text-dark', $t('Pending', 'قيد الانتظار')],
];
$postUrl = static fn (int $id, string $action): string => rateb_url(rateb_app_route('website/portal-users/' . $id . '/' . $action));
$users = $users ?? [];
?>
<link rel="stylesheet" href="<?php echo $e(rateb_asset('css/website-builder.css')); ?>">
<div class="container-fluid py-3 wb-admin">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?php echo $e($title ?? $t('Portal Users', 'مستخدمو البوابة')); ?></h1>
        <?php if (!empty($canManage)) { ?>
            <a class="btn btn-primary" href="<?php echo $e(rateb_url(rateb_app_route('website/portal-users/create'))); ?>"><?php echo $e($t('New portal user', 'مستخدم بوابة جديد')); ?></a>
        <?php } ?>
    </div>

    <?php if ($subscriptionOk === false) { ?>
        <div class="alert alert-warning"><?php echo $e($t(
            'This company has no valid subscription. Customer Portal app login will be refused even for approved accounts.',
            'لا يوجد اشتراك صالح لهذه الشركة. سيُرفض دخول تطبيق بوابة العملاء حتى للحسابات المعتمدة.'
        )); ?></div>
    <?php } ?>

    <p class="text-muted small"><?php echo $e($t(
        'App access must be approved per account. Accounts are never deleted; suspend them instead.',
        'يجب اعتماد وصول التطبيق لكل حساب على حدة. لا يتم حذف الحسابات؛ استخدم التعليق بدلاً من ذلك.'
    )); ?></p>

    <?php if ($users === []) { ?>
        <div class="alert alert-info"><?php echo $e($t('No portal users yet.', 'لا يوجد مستخدمو بوابة بعد.')); ?></div>
    <?php } else { ?>
    <div class="table-responsive">
    <table class="table table-striped align-middle">
        <thead>
        <tr>
            <th>#</th>
            <th><?php echo $e($t('Name', 'الاسم')); ?></th>
            <th><?php echo $e($t('Email', 'البريد')); ?></th>
            <th><?php echo $e($t('Type', 'النوع')); ?></th>
            <th><?php echo $e($t('Account', 'الحساب')); ?></th>
            <th><?php echo $e($t('App access', 'وصول التطبيق')); ?></th>
            <th><?php echo $e($t('Active app sessions', 'جلسات التطبيق النشطة')); ?></th>
            <th><?php echo $e($t('Last app use', 'آخر استخدام للتطبيق')); ?></th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u) {
            $id = (int) $u['id'];
            $state = (string) $u['app_access_state'];
            [$badgeClass, $badgeText] = $stateBadges[$state] ?? ['bg-secondary', $state];
            $approved = trim((string) ($u['app_access_approved_at'] ?? '')) !== '';
            $active = (string) ($u['status'] ?? '') === 'active';
            ?>
            <tr>
                <td><?php echo $id; ?></td>
                <td><?php echo $e($u['full_name'] ?? ''); ?><?php if (!empty($u['organization_name'])) { ?><div class="small text-muted"><?php echo $e($u['organization_name']); ?></div><?php } ?></td>
                <td><?php echo $e($u['email'] ?? ''); ?></td>
                <td><?php echo $e($typeLabels[(string) ($u['portal_type'] ?? '')] ?? ($u['portal_type'] ?? '')); ?></td>
                <td><?php echo $e($active ? $t('Active', 'نشط') : ((string) ($u['status'] ?? '') === 'suspended' ? $t('Suspended', 'موقوف') : $t('Pending', 'قيد الانتظار'))); ?></td>
                <td>
                    <span class="badge <?php echo $e($badgeClass); ?>"><?php echo $e($badgeText); ?></span>
                    <?php if ($approved) { ?><div class="small text-muted"><?php echo $e($u['app_access_approved_at']); ?></div><?php } ?>
                </td>
                <td><?php echo (int) ($u['active_sessions'] ?? 0); ?></td>
                <td class="small"><?php echo $e($u['sessions_last_used_at'] ?? '—'); ?></td>
                <td class="text-nowrap">
                    <?php if (!empty($canManage)) { ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo $e(rateb_url(rateb_app_route('website/portal-users/' . $id . '/edit'))); ?>"><?php echo $e(__('edit')); ?></a>
                        <?php if ($approved) { ?>
                            <form method="post" action="<?php echo $e($postUrl($id, 'app-access/revoke')); ?>" class="d-inline"><input type="hidden" name="_csrf" value="<?php echo $e($csrf); ?>"><button type="submit" class="btn btn-sm btn-outline-warning"><?php echo $e($t('Revoke App Access', 'إلغاء وصول التطبيق')); ?></button></form>
                        <?php } else { ?>
                            <form method="post" action="<?php echo $e($postUrl($id, 'app-access/approve')); ?>" class="d-inline"><input type="hidden" name="_csrf" value="<?php echo $e($csrf); ?>"><button type="submit" class="btn btn-sm btn-outline-success"><?php echo $e($t('Approve', 'اعتماد')); ?></button></form>
                        <?php } ?>
                        <form method="post" action="<?php echo $e($postUrl($id, 'status')); ?>" class="d-inline"><input type="hidden" name="_csrf" value="<?php echo $e($csrf); ?>"><input type="hidden" name="status" value="<?php echo $active ? 'suspended' : 'active'; ?>"><button type="submit" class="btn btn-sm <?php echo $active ? 'btn-outline-danger' : 'btn-outline-success'; ?>"><?php echo $e($active ? $t('Suspend', 'تعليق') : $t('Activate', 'تفعيل')); ?></button></form>
                        <?php if ((int) ($u['active_sessions'] ?? 0) > 0) { ?>
                            <form method="post" action="<?php echo $e($postUrl($id, 'sessions/revoke')); ?>" class="d-inline"><input type="hidden" name="_csrf" value="<?php echo $e($csrf); ?>"><button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo $e($t('Revoke Sessions', 'إنهاء الجلسات')); ?></button></form>
                        <?php } ?>
                    <?php } ?>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
    </div>
    <?php if (count($users) >= (int) $listLimit) { ?>
        <p class="small text-muted"><?php echo $e($t('Showing the newest ', 'يتم عرض أحدث ') . (int) $listLimit); ?></p>
    <?php } ?>
    <?php } ?>
</div>
