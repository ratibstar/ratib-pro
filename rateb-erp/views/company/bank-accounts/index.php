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
    <div class="rateb-card-header"><?php echo __('bank_accounts'); ?></div>
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0">
            <thead>
            <tr>
                <th><?php echo __('name'); ?></th>
                <th><?php echo __('bank_name'); ?></th>
                <th><?php echo __('account_number'); ?></th>
                <th><?php echo __('code'); ?></th>
                <th class="text-end"><?php echo __('book_balance'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($items)) { ?>
            <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
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
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
