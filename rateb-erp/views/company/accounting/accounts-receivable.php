<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('ar_open_total'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($totalOpen ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('ar_paid_total'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($totalPaid ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
</div>
<p class="text-muted small mb-3"><?php echo __('ar_subscription_help'); ?></p>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('accounts_receivable'); ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('invoice_no'); ?></th>
                    <th><?php echo __('issued_at'); ?></th>
                    <th><?php echo __('due_date'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th class="text-end"><?php echo __('total'); ?></th>
                    <th><?php echo __('journal_entry'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)) { ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($rows as $row) { ?>
                <tr>
                    <td>
                        <?php if (rateb_is_super_admin() && !empty($row['id'])) { ?>
                        <a href="<?php echo rateb_url('admin/invoices/' . (int) $row['id'] . '/edit'); ?>">
                            <?php echo Rateb\App\Core\View::escape($row['invoice_no']); ?>
                        </a>
                        <?php } else { ?>
                        <?php echo Rateb\App\Core\View::escape($row['invoice_no']); ?>
                        <?php } ?>
                    </td>
                    <td><?php echo Rateb\App\Core\View::escape($row['issued_at']); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['due_date'] ?? '—'); ?></td>
                    <td><?php echo __((string) ($row['status'] ?? '')); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($row['total_amount'] ?? 0), 2); ?></td>
                    <td>
                        <?php if (!empty($row['journal_id'])) { ?>
                        <a href="<?php echo rateb_app_url('journal-entries/' . (int) $row['journal_id']); ?>">
                            <?php echo Rateb\App\Core\View::escape($row['entry_no']); ?>
                        </a>
                        <?php } else { ?>
                        <span class="text-muted"><?php echo __('not_posted'); ?></span>
                        <?php } ?>
                    </td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
