<?php
/** @var array<string, mixed> $portal */
$user = $portal['user'] ?? [];
$company = $portal['company'] ?? [];
$limits = $portal['limits'] ?? [];
$subscription = $portal['subscription'] ?? null;
$modules = $portal['modules'] ?? [];
$userCount = (int) ($portal['userCount'] ?? 0);
$userLimit = (int) ($limits['user_limit'] ?? 0);
$storageUsedMb = (float) ($portal['storageUsedMb'] ?? 0);
$storageLimitMb = (int) ($limits['storage_limit_mb'] ?? 0);
$trialDaysLeft = $portal['trialDaysLeft'] ?? null;
$unreadNotifications = (int) ($portal['unreadNotifications'] ?? 0);
$companyName = (string) ($company['name'] ?? '');
$userName = (string) ($user['name'] ?? '');
$subStatus = (string) ($subscription['status'] ?? '');
$statusLabel = $subStatus === 'trial' ? __('portal_status_trial') : ($subStatus === 'active' ? __('portal_status_active') : $subStatus);
?>
<div class="rateb-portal-page">
    <div class="container py-4">
        <div class="rateb-portal-hero">
            <h1><?php echo Rateb\App\Core\View::escape($companyName !== '' ? $companyName : $userName); ?></h1>
            <p><?php echo Rateb\App\Core\View::escape(__('portal_welcome_user', ['name' => $userName])); ?></p>
        </div>

        <?php if ($unreadNotifications > 0) { ?>
        <div class="rateb-portal-alert mb-4">
            <i class="fas fa-bell ms-2"></i>
            <a href="<?php echo rateb_url('site/portal/notifications'); ?>" class="text-decoration-none">
                <?php echo __('portal_unread_count', ['count' => (string) $unreadNotifications]); ?>
            </a>
        </div>
        <?php } ?>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="rateb-portal-card">
                    <div class="rateb-portal-card-head">
                        <i class="fas fa-id-card text-primary ms-2"></i><?php echo __('portal_account'); ?>
                    </div>
                    <div class="rateb-portal-card-body">
                        <div class="rateb-portal-kv">
                            <div class="rateb-portal-kv-row">
                                <span class="rateb-portal-kv-label"><?php echo __('name'); ?></span>
                                <span class="rateb-portal-kv-value"><?php echo Rateb\App\Core\View::escape($userName); ?></span>
                            </div>
                            <div class="rateb-portal-kv-row">
                                <span class="rateb-portal-kv-label"><?php echo __('login_email'); ?></span>
                                <span class="rateb-portal-kv-value rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($user['email'] ?? '')); ?></span>
                            </div>
                            <div class="rateb-portal-kv-row">
                                <span class="rateb-portal-kv-label"><?php echo __('cms_company_name'); ?></span>
                                <span class="rateb-portal-kv-value"><?php echo Rateb\App\Core\View::escape($companyName); ?></span>
                            </div>
                            <?php if (!empty($company['phone'])) { ?>
                            <div class="rateb-portal-kv-row">
                                <span class="rateb-portal-kv-label"><?php echo __('phone'); ?></span>
                                <span class="rateb-portal-kv-value rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) $company['phone']); ?></span>
                            </div>
                            <?php } ?>
                        </div>
                        <a href="<?php echo rateb_url('site/portal/profile'); ?>" class="btn btn-outline-primary btn-sm mt-3"><?php echo __('portal_edit_profile'); ?></a>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="rateb-portal-card">
                    <div class="rateb-portal-card-head">
                        <i class="fas fa-crown text-warning ms-2"></i><?php echo __('plan_limits'); ?>
                    </div>
                    <div class="rateb-portal-card-body">
                        <div class="rateb-portal-kv">
                            <div class="rateb-portal-kv-row">
                                <span class="rateb-portal-kv-label"><?php echo __('current_plan'); ?></span>
                                <span class="rateb-portal-kv-value"><?php echo Rateb\App\Core\View::escape((string) ($subscription['plan_display'] ?? $limits['plan_name'] ?? '—')); ?></span>
                            </div>
                            <?php if ($subscription) { ?>
                            <div class="rateb-portal-kv-row">
                                <span class="rateb-portal-kv-label"><?php echo __('status'); ?></span>
                                <span class="rateb-portal-kv-value">
                                    <span class="badge text-bg-<?php echo $subStatus === 'trial' ? 'info' : 'success'; ?>"><?php echo Rateb\App\Core\View::escape($statusLabel); ?></span>
                                </span>
                            </div>
                            <?php if ($trialDaysLeft !== null) { ?>
                            <div class="rateb-portal-kv-row">
                                <span class="rateb-portal-kv-label"><?php echo __('portal_trial_days'); ?></span>
                                <span class="rateb-portal-kv-value"><?php echo __('portal_days_left', ['days' => (string) (int) $trialDaysLeft]); ?></span>
                            </div>
                            <?php } ?>
                            <?php if (!empty($subscription['ends_at'])) { ?>
                            <div class="rateb-portal-kv-row">
                                <span class="rateb-portal-kv-label"><?php echo __('end_date'); ?></span>
                                <span class="rateb-portal-kv-value rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) $subscription['ends_at']); ?></span>
                            </div>
                            <?php } ?>
                            <?php } ?>
                            <div class="rateb-portal-kv-row">
                                <span class="rateb-portal-kv-label"><?php echo __('user_limit'); ?></span>
                                <span class="rateb-portal-kv-value"><?php echo __('portal_users_usage', ['used' => (string) $userCount, 'max' => (string) $userLimit]); ?></span>
                            </div>
                            <div class="rateb-portal-kv-row">
                                <span class="rateb-portal-kv-label"><?php echo __('storage_limit_mb'); ?></span>
                                <span class="rateb-portal-kv-value"><?php echo __('portal_storage_usage', ['used' => (string) $storageUsedMb, 'max' => (string) $storageLimitMb]); ?></span>
                            </div>
                        </div>
                        <a href="<?php echo rateb_url('site/pricing'); ?>" class="btn btn-outline-primary btn-sm mt-3"><?php echo __('cms_view_plans'); ?></a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($modules !== []) { ?>
        <div class="rateb-portal-card mt-4">
            <div class="rateb-portal-card-head">
                <i class="fas fa-puzzle-piece text-primary ms-2"></i><?php echo __('portal_plan_modules'); ?>
            </div>
            <div class="rateb-portal-card-body">
                <p class="text-muted small mb-3"><?php echo __('portal_plan_modules_hint'); ?></p>
                <div class="rateb-portal-modules">
                    <?php foreach ($modules as $mod) { ?>
                    <span class="rateb-portal-module-pill">
                        <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($mod['icon'] ?? 'fa-cube')); ?>"></i>
                        <?php echo Rateb\App\Core\View::escape((string) ($mod['label'] ?? '')); ?>
                    </span>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
