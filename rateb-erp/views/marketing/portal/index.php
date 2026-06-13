<?php
/** @var array<string, mixed> $portal */
$user = $portal['user'] ?? [];
$company = $portal['company'] ?? [];
$limits = $portal['limits'] ?? [];
$metrics = $portal['metrics'] ?? [];
$subscription = $portal['subscription'] ?? null;
$modules = $portal['modules'] ?? [];
$quickLinks = $portal['quickLinks'] ?? [];
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
        <div class="card border-0 shadow-sm rateb-portal-welcome mb-4">
            <div class="card-body p-4">
                <p class="text-muted small mb-1"><?php echo __('portal_welcome'); ?></p>
                <h1 class="h3 mb-1"><?php echo Rateb\App\Core\View::escape($companyName !== '' ? $companyName : $userName); ?></h1>
                <p class="text-muted mb-0"><?php echo Rateb\App\Core\View::escape(__('portal_welcome_user', ['name' => $userName])); ?></p>
                <p class="small mt-2 mb-0 opacity-75"><?php echo __('portal_customer_only_hint'); ?></p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card h-100 shadow-sm rateb-portal-stat-card">
                    <div class="card-body text-center">
                        <div class="rateb-portal-stat-num"><?php echo (int) ($metrics['purchase_requests'] ?? 0); ?></div>
                        <div class="small text-muted"><?php echo __('purchase_requests'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100 shadow-sm rateb-portal-stat-card">
                    <div class="card-body text-center">
                        <div class="rateb-portal-stat-num"><?php echo (int) ($metrics['purchase_orders'] ?? 0); ?></div>
                        <div class="small text-muted"><?php echo __('purchase_orders'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100 shadow-sm rateb-portal-stat-card">
                    <div class="card-body text-center">
                        <div class="rateb-portal-stat-num"><?php echo number_format((float) ($metrics['inventory_value'] ?? 0), 0); ?></div>
                        <div class="small text-muted"><?php echo __('inventory_value'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card h-100 shadow-sm rateb-portal-stat-card">
                    <div class="card-body text-center">
                        <div class="rateb-portal-stat-num"><?php echo (int) ($metrics['suppliers'] ?? 0); ?></div>
                        <div class="small text-muted"><?php echo __('suppliers'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <span class="fw-semibold"><i class="fas fa-bolt text-primary ms-2"></i><?php echo __('portal_quick_links'); ?></span>
                        <?php if ($unreadNotifications > 0) { ?>
                        <a href="<?php echo rateb_url('site/portal/notifications'); ?>" class="badge text-bg-danger text-decoration-none">
                            <?php echo $unreadNotifications; ?> <?php echo __('notifications'); ?>
                        </a>
                        <?php } ?>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <?php foreach ($quickLinks as $link) { ?>
                            <div class="col-sm-6">
                                <a href="<?php echo Rateb\App\Core\View::escape((string) ($link['url'] ?? '#')); ?>" class="rateb-portal-action-btn">
                                    <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($link['icon'] ?? 'fa-link')); ?> ms-2"></i>
                                    <?php echo Rateb\App\Core\View::escape((string) ($link['label'] ?? '')); ?>
                                    <?php if (!empty($link['badge'])) { ?>
                                    <span class="badge text-bg-danger ms-1"><?php echo (int) $link['badge']; ?></span>
                                    <?php } ?>
                                </a>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-transparent fw-semibold">
                        <i class="fas fa-puzzle-piece text-primary ms-2"></i><?php echo __('portal_plan_modules'); ?>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small"><?php echo __('portal_plan_modules_hint'); ?></p>
                        <?php if ($modules === []) { ?>
                        <p class="text-muted mb-0"><?php echo __('portal_no_modules'); ?></p>
                        <?php } else { ?>
                        <div class="row g-3">
                            <?php foreach ($modules as $mod) { ?>
                            <div class="col-md-6">
                                <div class="card h-100 rateb-portal-module-card">
                                    <div class="card-body">
                                        <div class="fw-semibold rateb-portal-module-title mb-2">
                                            <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($mod['icon'] ?? 'fa-cube')); ?> text-primary ms-2"></i>
                                            <?php echo Rateb\App\Core\View::escape((string) ($mod['label'] ?? '')); ?>
                                        </div>
                                        <?php if (!empty($mod['subs'])) { ?>
                                        <ul class="rateb-portal-module-subs mb-0">
                                            <?php foreach ($mod['subs'] as $subLabel) { ?>
                                            <li><?php echo Rateb\App\Core\View::escape((string) $subLabel); ?></li>
                                            <?php } ?>
                                        </ul>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-transparent fw-semibold">
                        <i class="fas fa-id-card text-primary ms-2"></i><?php echo __('portal_account'); ?>
                    </div>
                    <div class="card-body">
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
                        <a href="<?php echo rateb_url('site/portal/profile'); ?>" class="btn btn-outline-primary btn-sm w-100"><?php echo __('portal_edit_profile'); ?></a>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-transparent fw-semibold">
                        <i class="fas fa-crown text-warning ms-2"></i><?php echo __('plan_limits'); ?>
                    </div>
                    <div class="card-body">
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
                        <a href="<?php echo rateb_url('site/pricing'); ?>" class="btn btn-outline-primary btn-sm w-100"><?php echo __('cms_view_plans'); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
