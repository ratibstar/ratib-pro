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
$storageUsedMb = (float) ($portal['storageUsedMb'] ?? 0);
$trialDaysLeft = $portal['trialDaysLeft'] ?? null;
$unreadNotifications = (int) ($portal['unreadNotifications'] ?? 0);
$companyName = (string) ($company['name'] ?? '');
$userName = (string) ($user['name'] ?? '');
?>
<section class="rateb-mkt-portal-hero">
    <div class="container">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <p class="rateb-mkt-portal-eyebrow mb-1"><?php echo __('portal_welcome'); ?></p>
                <h1 class="rateb-mkt-portal-title mb-2"><?php echo Rateb\App\Core\View::escape($companyName !== '' ? $companyName : $userName); ?></h1>
                <p class="rateb-mkt-portal-sub mb-0"><?php echo Rateb\App\Core\View::escape(__('portal_welcome_user', ['name' => $userName])); ?></p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a href="<?php echo rateb_url('admin'); ?>" class="btn btn-light btn-lg rateb-mkt-portal-erp-btn">
                    <i class="fas fa-grid-2 me-2"></i><?php echo __('portal_open_erp'); ?>
                </a>
            </div>
        </div>
    </div>
</section>

<section class="rateb-mkt-section rateb-mkt-portal-body">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="rateb-mkt-portal-card">
                    <h2 class="h5 mb-3"><i class="fas fa-id-card text-primary me-2"></i><?php echo __('portal_account'); ?></h2>
                    <dl class="rateb-mkt-portal-dl mb-0">
                        <dt><?php echo __('name'); ?></dt>
                        <dd><?php echo Rateb\App\Core\View::escape($userName); ?></dd>
                        <dt><?php echo __('login_email'); ?></dt>
                        <dd><?php echo Rateb\App\Core\View::escape((string) ($user['email'] ?? '')); ?></dd>
                        <dt><?php echo __('cms_company_name'); ?></dt>
                        <dd><?php echo Rateb\App\Core\View::escape($companyName); ?></dd>
                        <?php if (!empty($company['phone'])) { ?>
                        <dt><?php echo __('phone'); ?></dt>
                        <dd><?php echo Rateb\App\Core\View::escape((string) $company['phone']); ?></dd>
                        <?php } ?>
                    </dl>
                </div>

                <div class="rateb-mkt-portal-card mt-4">
                    <h2 class="h5 mb-3"><i class="fas fa-crown text-warning me-2"></i><?php echo __('plan_limits'); ?></h2>
                    <dl class="rateb-mkt-portal-dl mb-3">
                        <dt><?php echo __('current_plan'); ?></dt>
                        <dd><?php echo Rateb\App\Core\View::escape((string) ($subscription['plan_display'] ?? $limits['plan_name'] ?? '—')); ?></dd>
                        <?php if ($subscription) { ?>
                        <dt><?php echo __('status'); ?></dt>
                        <dd>
                            <span class="badge bg-<?php echo ($subscription['status'] ?? '') === 'trial' ? 'info' : 'success'; ?>">
                                <?php echo Rateb\App\Core\View::escape((string) ($subscription['status'] ?? '')); ?>
                            </span>
                        </dd>
                        <?php if ($trialDaysLeft !== null) { ?>
                        <dt><?php echo __('portal_trial_days'); ?></dt>
                        <dd><?php echo (int) $trialDaysLeft; ?> <?php echo __('portal_days'); ?></dd>
                        <?php } ?>
                        <?php if (!empty($subscription['ends_at'])) { ?>
                        <dt><?php echo __('end_date'); ?></dt>
                        <dd><?php echo Rateb\App\Core\View::escape((string) $subscription['ends_at']); ?></dd>
                        <?php } ?>
                        <?php } ?>
                        <dt><?php echo __('user_limit'); ?></dt>
                        <dd><?php echo $userCount; ?> / <?php echo (int) ($limits['user_limit'] ?? 0); ?></dd>
                        <dt><?php echo __('storage_limit_mb'); ?></dt>
                        <dd><?php echo $storageUsedMb; ?> / <?php echo (int) ($limits['storage_limit_mb'] ?? 0); ?> MB</dd>
                    </dl>
                    <a href="<?php echo rateb_url('site/pricing'); ?>" class="btn btn-outline-primary btn-sm"><?php echo __('cms_view_plans'); ?></a>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="rateb-mkt-portal-stat">
                            <div class="rateb-mkt-portal-stat-value"><?php echo (int) ($metrics['purchase_requests'] ?? 0); ?></div>
                            <div class="rateb-mkt-portal-stat-label"><?php echo __('purchase_requests'); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="rateb-mkt-portal-stat">
                            <div class="rateb-mkt-portal-stat-value"><?php echo (int) ($metrics['purchase_orders'] ?? 0); ?></div>
                            <div class="rateb-mkt-portal-stat-label"><?php echo __('purchase_orders'); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="rateb-mkt-portal-stat">
                            <div class="rateb-mkt-portal-stat-value"><?php echo number_format((float) ($metrics['inventory_value'] ?? 0), 0); ?></div>
                            <div class="rateb-mkt-portal-stat-label"><?php echo __('inventory_value'); ?></div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="rateb-mkt-portal-stat">
                            <div class="rateb-mkt-portal-stat-value"><?php echo (int) ($metrics['suppliers'] ?? 0); ?></div>
                            <div class="rateb-mkt-portal-stat-label"><?php echo __('suppliers'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="rateb-mkt-portal-card mb-4">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <h2 class="h5 mb-0"><i class="fas fa-bolt text-primary me-2"></i><?php echo __('portal_quick_links'); ?></h2>
                        <?php if ($unreadNotifications > 0) { ?>
                        <span class="badge bg-danger"><?php echo $unreadNotifications; ?> <?php echo __('notifications'); ?></span>
                        <?php } ?>
                    </div>
                    <div class="row g-2">
                        <?php foreach ($quickLinks as $link) { ?>
                        <div class="col-md-6">
                            <a href="<?php echo Rateb\App\Core\View::escape((string) ($link['url'] ?? '#')); ?>" class="rateb-mkt-portal-quick-link">
                                <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($link['icon'] ?? 'fa-link')); ?>"></i>
                                <span><?php echo Rateb\App\Core\View::escape((string) ($link['label'] ?? '')); ?></span>
                                <i class="fas fa-arrow-left rateb-mkt-portal-quick-arrow"></i>
                            </a>
                        </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="rateb-mkt-portal-card">
                    <h2 class="h5 mb-3"><i class="fas fa-puzzle-piece text-primary me-2"></i><?php echo __('portal_modules'); ?></h2>
                    <?php if ($modules === []) { ?>
                    <p class="text-muted mb-0"><?php echo __('portal_no_modules'); ?></p>
                    <?php } else { ?>
                    <div class="row g-3">
                        <?php foreach ($modules as $mod) { ?>
                        <div class="col-md-6">
                            <div class="rateb-mkt-portal-module">
                                <a href="<?php echo Rateb\App\Core\View::escape((string) ($mod['url'] ?? '#')); ?>" class="rateb-mkt-portal-module-head">
                                    <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($mod['icon'] ?? 'fa-cube')); ?>"></i>
                                    <strong><?php echo Rateb\App\Core\View::escape((string) ($mod['label'] ?? '')); ?></strong>
                                </a>
                                <?php if (!empty($mod['subs'])) { ?>
                                <ul class="rateb-mkt-portal-module-subs mb-0">
                                    <?php foreach ($mod['subs'] as $sub) { ?>
                                    <li><a href="<?php echo Rateb\App\Core\View::escape((string) ($sub['url'] ?? '#')); ?>"><?php echo Rateb\App\Core\View::escape((string) ($sub['label'] ?? '')); ?></a></li>
                                    <?php } ?>
                                </ul>
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</section>
