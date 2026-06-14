<?php
/** Cash vouchers — all statuses (read-only list) */
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2 text-primary"></i><?php echo __('cash_vouchers'); ?></h5>
    <?php if ($canManage ?? false) { ?>
    <a href="<?php echo rateb_app_url('cash-vouchers/create'); ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> <?php echo __('new_cash_voucher'); ?>
    </a>
    <?php } ?>
</div>

<div class="rateb-card">
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('voucher_no'); ?></th>
                    <th><?php echo __('voucher_type'); ?></th>
                    <th><?php echo __('evaluation_date'); ?></th>
                    <th><?php echo __('party_name'); ?></th>
                    <th class="text-end"><?php echo __('amount'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)) { ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($items as $row) {
                    $st = (string) ($row['status'] ?? '');
                    $displayStatus = $st === 'draft' ? 'pending' : ($st === 'posted' ? 'approved' : $st);
                    ?>
                <tr>
                    <td class="fw-semibold"><?php echo Rateb\App\Core\View::escape($row['voucher_no']); ?></td>
                    <td><?php echo __((string) ($row['voucher_type'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['voucher_date']); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['party_name'] ?? '—'); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($row['amount'] ?? 0), 2); ?></td>
                    <td><?php echo __($displayStatus); ?></td>
                    <td class="text-end">
                        <a href="<?php echo rateb_app_url('cash-vouchers/' . (int) $row['id']); ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i> <?php echo __('view'); ?>
                        </a>
                    </td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
