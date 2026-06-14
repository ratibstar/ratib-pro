<?php
$bulkManage = ($canManage ?? false);
$bulkApprove = ($canApprove ?? false);
$bulkAny = $bulkManage || $bulkApprove;
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h5>
    <?php if ($canManage ?? false) { ?>
    <a href="<?php echo rateb_app_url('cash-vouchers/create'); ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> <?php echo __('new_cash_voucher'); ?>
    </a>
    <?php } ?>
</div>
<div class="rateb-card">
    <?php if ($bulkAny && !empty($items)) { ?>
    <div class="rateb-bulk-bar d-none" data-rateb-bulk-bar>
        <span class="rateb-bulk-count" data-rateb-bulk-count data-label="<?php echo Rateb\App\Core\View::escape(__('bulk_selected')); ?>">0</span>
        <?php if ($bulkManage) { ?>
        <form method="post" action="<?php echo rateb_app_url('cash-vouchers/bulk-delete'); ?>" class="d-inline" data-rateb-bulk-form="delete"
              data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_delete_drafts')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> <?php echo __('bulk_delete_drafts'); ?></button>
        </form>
        <?php } ?>
        <?php if ($bulkApprove) { ?>
        <form method="post" action="<?php echo rateb_app_url('cash-vouchers/bulk-approve'); ?>" class="d-inline" data-rateb-bulk-form="approve">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> <?php echo __('bulk_approve'); ?></button>
        </form>
        <?php } ?>
    </div>
    <?php } ?>
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0" data-rateb-bulk-table="<?php echo $bulkAny ? '1' : '0'; ?>">
            <thead>
            <tr>
                <?php if ($bulkAny) { ?>
                <th class="rateb-bulk-th"><input type="checkbox" class="form-check-input" data-rateb-select-all></th>
                <?php } ?>
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
            <tr><td colspan="<?php echo $bulkAny ? 8 : 7; ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else { foreach ($items as $row) {
                $st = (string) ($row['status'] ?? '');
                $isDraft = $st === 'draft';
                ?>
            <tr>
                <?php if ($bulkAny) { ?>
                <td class="rateb-bulk-td">
                    <?php if ($isDraft) { ?>
                    <input type="checkbox" class="form-check-input" data-rateb-row-check value="<?php echo (int) $row['id']; ?>">
                    <?php } ?>
                </td>
                <?php } ?>
                <td><?php echo Rateb\App\Core\View::escape($row['voucher_no']); ?></td>
                <td><?php echo __((string) ($row['voucher_type'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($row['voucher_date']); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($row['party_name'] ?? '—'); ?></td>
                <td class="text-end"><?php echo number_format((float) ($row['amount'] ?? 0), 2); ?></td>
                <td><span class="badge bg-<?php echo $st === 'posted' ? 'success' : ($st === 'void' ? 'secondary' : 'warning'); ?>"><?php echo __($st); ?></span></td>
                <td class="text-nowrap">
                    <a href="<?php echo rateb_app_url('cash-vouchers/' . (int) $row['id']); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                    <?php if (($canManage ?? false) && $isDraft) { ?>
                    <a href="<?php echo rateb_app_url('cash-vouchers/' . (int) $row['id'] . '/edit'); ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-edit"></i></a>
                    <?php } ?>
                </td>
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
