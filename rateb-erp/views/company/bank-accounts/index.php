<?php
$bulkEnabled = $bulkEnabled ?? false;
$actionsEnabled = $actionsEnabled ?? false;
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
              data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_deactivate')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-ban"></i> <?php echo __('bulk_deactivate'); ?></button>
        </form>
    </div>
    <?php } ?>
    <div class="rateb-card-header"><?php echo __('bank_accounts'); ?></div>
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0" data-rateb-bulk-table="<?php echo $bulkEnabled ? '1' : '0'; ?>">
            <thead>
            <tr>
                <?php if ($bulkEnabled) { ?>
                <th class="rateb-bulk-th"><input type="checkbox" class="form-check-input" data-rateb-select-all></th>
                <?php } ?>
                <th><?php echo __('name'); ?></th>
                <th><?php echo __('bank_name'); ?></th>
                <th><?php echo __('account_number'); ?></th>
                <th><?php echo __('code'); ?></th>
                <th class="text-end"><?php echo __('book_balance'); ?></th>
                <?php if ($actionsEnabled) { ?><th></th><?php } ?>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($items)) { ?>
            <tr><td colspan="<?php echo ($bulkEnabled ? 1 : 0) + ($actionsEnabled ? 1 : 0) + 5; ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else { foreach ($items as $row) { ?>
            <tr>
                <?php if ($bulkEnabled) { ?>
                <td class="rateb-bulk-td">
                    <input type="checkbox" class="form-check-input" data-rateb-row-check value="<?php echo (int) $row['id']; ?>">
                </td>
                <?php } ?>
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
                <?php if ($actionsEnabled) { ?>
                <td class="text-nowrap">
                    <a href="<?php echo rateb_app_url('bank-accounts/' . (int) $row['id'] . '/edit'); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                    <form method="post" action="<?php echo rateb_app_url('bank-accounts/' . (int) $row['id'] . '/delete'); ?>" class="d-inline"
                          onsubmit="return confirm('<?php echo __('bulk_confirm_deactivate'); ?>');">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-ban"></i></button>
                    </form>
                </td>
                <?php } ?>
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
