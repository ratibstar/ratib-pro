<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('ap_open_total'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($totalOpen ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('ap_posted_total'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($totalPosted ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
</div>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('accounts_payable'); ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('order_no'); ?></th>
                    <th><?php echo __('supplier'); ?></th>
                    <th><?php echo __('order_date'); ?></th>
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
                        <a href="<?php echo rateb_app_url('purchase-orders/' . (int) $row['id']); ?>">
                            <?php echo Rateb\App\Core\View::escape($row['order_no']); ?>
                        </a>
                    </td>
                    <td><?php echo Rateb\App\Core\View::escape($row['supplier_name'] ?? '—'); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['order_date']); ?></td>
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
