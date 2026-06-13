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
        <div class="rateb-portal-welcome mb-4">
            <div class="rateb-portal-welcome-inner">
                <h1 class="h3 mb-1"><?php echo Rateb\App\Core\View::escape($companyName !== '' ? $companyName : $userName); ?></h1>
                <p class="mb-0 opacity-90"><?php echo Rateb\App\Core\View::escape(__('portal_welcome_user', ['name' => $userName])); ?></p>
            </div>
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
                <div class="rateb-portal-panel h-100">
                    <div class="rateb-portal-panel-head fw-semibold">
                        <i class="fas fa-id-card text-primary ms-2"></i><?php echo __('portal_account'); ?>
                    </div>
                    <div class="rateb-portal-panel-body">
                        <dl class="rateb-portal-meta mb-3">
                            <dt><?php echo __('name'); ?></dt>
                            <dd><?php echo Rateb\App\Core\View::escape($userName); ?></dd>
                            <dt><?php echo __('login_email'); ?></dt>
                            <dd class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($user['email'] ?? '')); ?></dd>
                            <dt><?php echo __('cms_company_name'); ?></dt>
                            <dd><?php echo Rateb\App\Core\View::escape($companyName); ?></dd>
                            <?php if (!empty($company['phone'])) { ?>
                            <dt><?php echo __('phone'); ?></dt>
                            <dd class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) $company['phone']); ?></dd>
                            <?php } ?>
                        </dl>
                        <a href="<?php echo rateb_url('site/portal/profile'); ?>" class="btn btn-outline-primary btn-sm"><?php echo __('portal_edit_profile'); ?></a>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="rateb-portal-panel h-100">
                    <div class="rateb-portal-panel-head fw-semibold">
                        <i class="fas fa-crown text-warning ms-2"></i><?php echo __('plan_limits'); ?>
                    </div>
                    <div class="rateb-portal-panel-body">
                        <dl class="rateb-portal-meta mb-3">
                            <dt><?php echo __('current_plan'); ?></dt>
                            <dd><?php echo Rateb\App\Core\View::escape((string) ($subscription['plan_display'] ?? $limits['plan_name'] ?? '—')); ?></dd>
                            <?php if ($subscription) { ?>
                            <dt><?php echo __('status'); ?></dt>
                            <dd>
                                <span class="badge text-bg-<?php echo $subStatus === 'trial' ? 'info' : 'success'; ?>">
                                    <?php echo Rateb\App\Core\View::escape($statusLabel); ?>
                                </span>
                            </dd>
                            <?php if ($trialDaysLeft !== null) { ?>
                            <dt><?php echo __('portal_trial_days'); ?></dt>
                            <dd><?php echo __('portal_days_left', ['days' => (string) (int) $trialDaysLeft]); ?></dd>
                            <?php } ?>
                            <?php if (!empty($subscription['ends_at'])) { ?>
                            <dt><?php echo __('end_date'); ?></dt>
                            <dd class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) $subscription['ends_at']); ?></dd>
                            <?php } ?>
                            <?php } ?>
                            <dt><?php echo __('user_limit'); ?></dt>
                            <dd><?php echo __('portal_users_usage', ['used' => (string) $userCount, 'max' => (string) $userLimit]); ?></dd>
                            <dt><?php echo __('storage_limit_mb'); ?></dt>
                            <dd><?php echo __('portal_storage_usage', ['used' => (string) $storageUsedMb, 'max' => (string) $storageLimitMb]); ?></dd>
                        </dl>
                        <a href="<?php echo rateb_url('site/pricing'); ?>" class="btn btn-outline-primary btn-sm"><?php echo __('cms_view_plans'); ?></a>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($modules !== []) { ?>
        <div class="rateb-portal-panel mt-4">
            <div class="rateb-portal-panel-head fw-semibold">
                <i class="fas fa-puzzle-piece text-primary ms-2"></i><?php echo __('portal_plan_modules'); ?>
            </div>
            <div class="rateb-portal-panel-body">
                <p class="text-muted small mb-3"><?php echo __('portal_plan_modules_hint'); ?></p>
                <div class="row g-2">
                    <?php foreach ($modules as $mod) { ?>
                    <div class="col-md-4 col-sm-6">
                        <div class="rateb-portal-module-chip">
                            <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($mod['icon'] ?? 'fa-cube')); ?> ms-1"></i>
                            <?php echo Rateb\App\Core\View::escape((string) ($mod['label'] ?? '')); ?>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
