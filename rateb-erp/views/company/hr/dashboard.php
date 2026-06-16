<?php
$stats = $stats ?? ['employees' => 0, 'active' => 0, 'present_today' => 0, 'absent_today' => 0, 'pending_leaves' => 0, 'draft_payrolls' => 0];
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'overview']);
?>
<div class="row g-3 mb-4">
    <div class="col-md-4 col-lg-2">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('hr_employees'); ?></div>
            <div class="rateb-stat-value"><?php echo (int) ($stats['employees'] ?? 0); ?></div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('hr_active_employees'); ?></div>
            <div class="rateb-stat-value"><?php echo (int) ($stats['active'] ?? 0); ?></div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('hr_present_today'); ?></div>
            <div class="rateb-stat-value"><?php echo (int) ($stats['present_today'] ?? 0); ?></div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('hr_absent_today'); ?></div>
            <div class="rateb-stat-value"><?php echo (int) ($stats['absent_today'] ?? 0); ?></div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('hr_pending_leaves'); ?></div>
            <div class="rateb-stat-value"><?php echo (int) ($stats['pending_leaves'] ?? 0); ?></div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('hr_draft_payrolls'); ?></div>
            <div class="rateb-stat-value"><?php echo (int) ($stats['draft_payrolls'] ?? 0); ?></div>
        </div>
    </div>
</div>
<p class="text-muted mb-4"><?php echo __('hr_intro'); ?></p>
<div class="d-flex flex-wrap gap-2">
    <a href="<?php echo rateb_app_url('hr/employees'); ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-id-badge"></i> <?php echo __('hr_employees'); ?>
    </a>
    <a href="<?php echo rateb_app_url('hr/departments'); ?>" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-sitemap"></i> <?php echo __('hr_departments'); ?>
    </a>
    <a href="<?php echo rateb_app_url('hr/attendance'); ?>" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-clock"></i> <?php echo __('hr_attendance'); ?>
    </a>
    <a href="<?php echo rateb_app_url('hr/leaves'); ?>" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-calendar-minus"></i> <?php echo __('hr_leaves'); ?>
    </a>
    <a href="<?php echo rateb_app_url('hr/payroll'); ?>" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-money-check-dollar"></i> <?php echo __('hr_payroll'); ?>
    </a>
</div>
