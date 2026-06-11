<?php
$trial = $trial ?? [];
$summary = $summary ?? [];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'admin']);
?>
<div class="row g-3 mb-4">
    <div class="col-md-4 col-lg-2">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('invoices_paid'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($summary['invoices_paid_total'] ?? 0), 2); ?> <small>SAR</small></div>
            <div class="rateb-stat-meta"><?php echo (int) ($summary['invoices_paid_count'] ?? 0); ?> <?php echo __('invoices'); ?></div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('invoices_open'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($summary['invoices_open_total'] ?? 0), 2); ?> <small>SAR</small></div>
            <div class="rateb-stat-meta"><?php echo (int) ($summary['invoices_open_count'] ?? 0); ?> <?php echo __('invoices'); ?></div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('payments'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($summary['payments_total'] ?? 0), 2); ?> <small>SAR</small></div>
            <div class="rateb-stat-meta"><?php echo (int) ($summary['payments_count'] ?? 0); ?> <?php echo __('records'); ?></div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('procurement'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($summary['procurement_received'] ?? 0), 2); ?> <small>SAR</small></div>
            <div class="rateb-stat-meta"><?php echo __('purchase_orders'); ?></div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('journal_entries'); ?></div>
            <div class="rateb-stat-value"><?php echo (int) ($summary['journal_posted'] ?? 0); ?></div>
            <div class="rateb-stat-meta"><?php echo __('posted'); ?></div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('chart_of_accounts'); ?></div>
            <div class="rateb-stat-value"><?php echo (int) ($summary['accounts_active'] ?? 0); ?></div>
            <div class="rateb-stat-meta"><?php echo __('active'); ?></div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    <form method="post" action="<?php echo rateb_url('admin/accounting/sync'); ?>" class="d-inline">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sync"></i> <?php echo __('accounting_sync'); ?></button>
    </form>
    <a href="<?php echo rateb_url('admin/chart-of-accounts/create'); ?>" class="btn btn-outline-primary btn-sm"><i class="fas fa-plus"></i> <?php echo __('add_account'); ?></a>
    <a href="<?php echo rateb_url('admin/invoices/create'); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-file-invoice"></i> <?php echo __('new_invoice'); ?></a>
    <a href="<?php echo rateb_url('admin/payments/create'); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-money-bill"></i> <?php echo __('new_payment'); ?></a>
</div>
<p class="text-muted small mb-3"><?php echo __('accounting_sync_help'); ?></p>

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
