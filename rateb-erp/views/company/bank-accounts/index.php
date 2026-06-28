<?php
$bulkEnabled = $bulkEnabled ?? false;
$actionsEnabled = $actionsEnabled ?? false;
$showActionsCol = true;
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <p class="text-muted small mb-0"><?php echo __('bank_accounts_help'); ?></p>
    <?php if ($createEnabled ?? false) { ?>
    <a href="<?php echo rateb_app_url('bank-accounts/create'); ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> <?php echo __('new_bank_account'); ?>
    </a>
    <?php } ?>
</div>
<div class="rateb-card">
    <?php if ($bulkEnabled && !empty($items)) { ?>
    <div class="rateb-bulk-bar d-none" data-rateb-bulk-bar>
        <span class="rateb-bulk-count" data-rateb-bulk-count data-label="<?php echo Rateb\App\Core\View::escape(__('bulk_selected')); ?>">0</span>
        <form method="post" action="<?php echo rateb_app_url('bank-accounts/bulk-delete'); ?>" class="d-inline" data-rateb-bulk-form="delete"
              data-rateb-bulk-confirm="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_deactivate')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="button" class="btn btn-warning btn-sm" data-rateb-bulk-delete-btn><i class="fas fa-ban"></i> <?php echo __('bulk_deactivate'); ?></button>
        </form>
    </div>
    <?php } ?>
    <div class="rateb-card-header"><?php echo __('bank_accounts'); ?></div>
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0" data-rateb-bulk-table="<?php echo $bulkEnabled ? '1' : '0'; ?>">
            <thead>
            <tr>
                <th><?php echo __('name'); ?></th>
                <th><?php echo __('bank_name'); ?></th>
                <th><?php echo __('account_number'); ?></th>
                <th><?php echo __('code'); ?></th>
                <th class="text-end"><?php echo __('book_balance'); ?></th>
                <?php if ($showActionsCol) { ?>
                <th class="rateb-th-actions text-end">
                    <span class="rateb-actions-head">
                        <?php if ($bulkEnabled && !empty($items)) { ?>
                        <input type="checkbox" class="form-check-input" data-rateb-select-all title="<?php echo Rateb\App\Core\View::escape(__('select_all')); ?>">
                        <?php } ?>
                        <span><?php echo __('actions'); ?></span>
                    </span>
                </th>
                <?php } ?>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($items)) { ?>
            <tr><td colspan="<?php echo 5 + ($showActionsCol ? 1 : 0); ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else { foreach ($items as $row) { ?>
            <tr>
                <td>
                    <?php echo Rateb\App\Core\View::escape($row['name']); ?>
                    <?php if (!empty($row['is_default'])) { ?>
                    <span class="badge bg-primary ms-1"><?php echo __('default'); ?></span>
                    <?php } ?>
                </td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($row['bank_name'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($row['account_number'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($row['account_code'] ?? ''); ?></td>
                <td class="text-end"><?php echo number_format((float) ($row['book_balance'] ?? 0), 2); ?></td>
                <?php if ($showActionsCol) { ?>
                <td class="rateb-actions-cell text-nowrap text-end">
                    <div class="rateb-actions justify-content-end">
                    <?php if ($bulkEnabled) { ?>
                    <input type="checkbox" class="form-check-input rateb-row-check rateb-actions-select" value="<?php echo (int) $row['id']; ?>" data-rateb-row-check>
                    <?php } ?>
                    <a href="<?php echo rateb_app_url('bank-accounts/' . (int) $row['id']); ?>" class="btn btn-sm btn-outline-info" title="<?php echo __('view'); ?>"><i class="fas fa-eye"></i></a>
                    <?php if ($actionsEnabled) { ?>
                    <a href="<?php echo rateb_app_url('bank-accounts/' . (int) $row['id'] . '/edit'); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo __('edit'); ?>"><i class="fas fa-edit"></i></a>
                    <form method="post" action="<?php echo rateb_app_url('bank-accounts/' . (int) $row['id'] . '/delete'); ?>" class="d-inline"
                          data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_deactivate')); ?>">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo __('delete'); ?>"><i class="fas fa-ban"></i></button>
                    </form>
                    <?php } ?>
                    </div>
                </td>
                <?php } ?>
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
