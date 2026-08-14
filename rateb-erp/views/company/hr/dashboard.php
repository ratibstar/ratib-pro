<?php
$stats = $stats ?? ['employees' => 0, 'active' => 0, 'present_today' => 0, 'absent_today' => 0, 'pending_leaves' => 0, 'draft_payrolls' => 0];
$inboxCounts = $inboxCounts ?? ['total' => 0];
$companyId = (int) ($companyId ?? 0);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'overview']);
?>
<?php if ($companyId < 1 && function_exists('rateb_is_super_admin') && rateb_is_super_admin()) { ?>
<div class="alert alert-warning mb-3">
    <i class="fas fa-building me-1"></i> <?php echo __('hr_select_company_hint'); ?>
</div>
<?php } ?>
<?php if ((int) ($inboxCounts['total'] ?? 0) > 0) { ?>
<div class="alert alert-warning mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span>
        <i class="fas fa-inbox me-1"></i>
        <?php echo __('hr_pending_actions'); ?>:
        <strong><?php echo (int) $inboxCounts['total']; ?></strong>
    </span>
    <a href="<?php echo rateb_url(rateb_app_route('hr/approvals-inbox')); ?>" class="btn btn-sm btn-warning">
        <?php echo __('hr_open_approval_inbox'); ?>
    </a>
</div>
<?php } ?>
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
        <a href="<?php echo rateb_url(rateb_app_route('hr/approvals-inbox')); ?>?type=leave" class="text-decoration-none text-reset">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('hr_pending_leaves'); ?></div>
            <div class="rateb-stat-value"><?php echo (int) ($stats['pending_leaves'] ?? 0); ?></div>
        </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-2">
        <a href="<?php echo rateb_url(rateb_app_route('hr/approvals-inbox')); ?>?type=payroll" class="text-decoration-none text-reset">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('hr_draft_payrolls'); ?></div>
            <div class="rateb-stat-value"><?php echo (int) ($stats['draft_payrolls'] ?? 0); ?></div>
        </div>
        </a>
    </div>
</div>
<p class="text-muted small mb-0"><?php echo __('hr_intro'); ?></p>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
