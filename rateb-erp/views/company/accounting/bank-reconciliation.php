<?php
$data = $data ?? ['accounts' => [], 'total_cash' => 0, 'petty_cash' => 0];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
?>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('total_cash'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($data['total_cash'] ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('petty_cash'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($data['petty_cash'] ?? 0), 2); ?> <small>SAR</small></div>
        </div>
    </div>
</div>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('bank_reconciliation'); ?></div>
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0">
            <thead>
            <tr>
                <th><?php echo __('name'); ?></th>
                <th><?php echo __('bank_name'); ?></th>
                <th><?php echo __('account_number'); ?></th>
                <th class="text-end"><?php echo __('opening_balance'); ?></th>
                <th class="text-end"><?php echo __('book_balance'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($data['accounts'])) { ?>
            <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_bank_accounts'); ?></td></tr>
            <?php } else { foreach ($data['accounts'] as $row) { ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape($row['name']); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($row['bank_name'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($row['account_number'] ?? '')); ?></td>
                <td class="text-end"><?php echo number_format((float) ($row['opening_balance'] ?? 0), 2); ?></td>
                <td class="text-end"><?php echo number_format((float) ($row['book_balance'] ?? 0), 2); ?></td>
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
