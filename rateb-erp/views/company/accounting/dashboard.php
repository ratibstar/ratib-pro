<?php
$trial = $trial ?? [];
$summary = $summary ?? [];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
if (rateb_is_super_admin() || rateb_can('access.manage')) {
    Rateb\App\Core\View::partial('accounting-permissions-note');
}
?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('invoices_paid'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($summary['invoices_paid_total'] ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('payments'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($summary['payments_total'] ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('procurement'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($summary['procurement_received'] ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
</div>
<div class="d-flex flex-wrap gap-2 mb-3">
    <?php if ($canPost ?? rateb_can_post_entity('accounting')) { ?>
    <form method="post" action="<?php echo rateb_app_url('accounting/sync'); ?>" class="d-inline">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> <?php echo __('accounting_sync'); ?></button>
    </form>
    <?php } ?>
    <a href="<?php echo rateb_url_with_ops_company(rateb_app_route('accounting/reports')); ?>" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-chart-pie"></i> <?php echo __('view_all_reports'); ?>
    </a>
    <?php if (rateb_can_export_entity('accounting')) { ?>
    <a href="<?php echo rateb_url_with_ops_company('accounting/export/trial-balance'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-download"></i> <?php echo __('export_trial_balance'); ?>
    </a>
    <a href="<?php echo rateb_url_with_ops_company('accounting/export/journals'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-download"></i> <?php echo __('export_journals'); ?>
    </a>
    <?php } ?>
</div>
<p class="text-muted small mb-3"><?php echo __('accounting_auto_post_help'); ?></p>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('trial_balance'); ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('code'); ?></th>
                    <th><?php echo __('name'); ?></th>
                    <th><?php echo __('account_type'); ?></th>
                    <th class="text-end"><?php echo __('debit'); ?></th>
                    <th class="text-end"><?php echo __('credit'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($trial)) { ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($trial as $row) {
                    $name = rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : $row['name'];
                    ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($row['code']); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($name); ?></td>
                    <td><?php echo __((string) ($row['account_type'] ?? '')); ?></td>
                    <td class="text-end"><?php echo number_format((float) $row['total_debit'], 2); ?></td>
                    <td class="text-end"><?php echo number_format((float) $row['total_credit'], 2); ?></td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
