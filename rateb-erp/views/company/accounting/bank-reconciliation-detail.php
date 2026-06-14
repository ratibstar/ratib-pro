<?php
$d = $data ?? [];
$bank = $d['bank'] ?? [];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
?>
<div class="mb-3">
    <a href="<?php echo rateb_app_url('accounting/bank-reconciliation'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-right"></i> <?php echo __('bank_reconciliation'); ?>
    </a>
</div>
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('book_balance'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($d['book_balance'] ?? 0), 2); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('statement_balance'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($d['statement_balance'] ?? 0), 2); ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('difference'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) ($d['difference'] ?? 0), 2); ?></div>
        </div>
    </div>
</div>
<?php if (!($canManage ?? false)) { ?>
<p class="text-muted small mb-2"><i class="fas fa-lock me-1"></i><?php echo __('accounting_perm_bank_import_hint'); ?></p>
<?php } ?>
<?php if ($canManage ?? false) { ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo __('import_bank_statement'); ?></div>
    <div class="rateb-card-body">
        <p class="text-muted small"><?php echo __('bank_statement_csv_help'); ?></p>
        <form method="post" action="<?php echo rateb_app_url('accounting/bank-reconciliation/' . (int) $bankId . '/import'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <textarea name="statement_csv" class="form-control font-monospace" rows="6" placeholder="date,description,amount,reference"></textarea>
            <button type="submit" class="btn btn-primary mt-2"><i class="fas fa-file-import"></i> <?php echo __('import'); ?></button>
        </form>
    </div>
</div>
<?php } ?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('book_transactions'); ?></div>
            <div class="rateb-card-body p-0">
                <table class="table rateb-table mb-0 table-sm">
                    <thead><tr><th><?php echo __('evaluation_date'); ?></th><th><?php echo __('entry_no'); ?></th><th class="text-end"><?php echo __('amount'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach (($d['book_lines'] ?? []) as $line) {
                        $amt = (float) ($line['debit'] ?? 0) - (float) ($line['credit'] ?? 0);
                        ?>
                    <tr>
                        <td><?php echo Rateb\App\Core\View::escape($line['entry_date']); ?></td>
                        <td><?php echo Rateb\App\Core\View::escape($line['entry_no']); ?></td>
                        <td class="text-end"><?php echo number_format($amt, 2); ?></td>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header d-flex justify-content-between align-items-center">
                <span><?php echo __('bank_statement_lines'); ?></span>
            </div>
            <?php if (($bulkEnabled ?? false) && !empty($d['statement_lines'])) { ?>
            <div class="rateb-bulk-bar d-none" data-rateb-bulk-bar>
                <span class="rateb-bulk-count" data-rateb-bulk-count data-label="<?php echo Rateb\App\Core\View::escape(__('bulk_selected')); ?>">0</span>
                <form method="post" action="<?php echo rateb_app_url('accounting/bank-reconciliation/bulk-delete-lines'); ?>" class="d-inline" data-rateb-bulk-form="delete"
                      data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_delete')); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <input type="hidden" name="bank_account_id" value="<?php echo (int) $bankId; ?>">
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> <?php echo __('bulk_delete'); ?></button>
                </form>
            </div>
            <?php } ?>
            <div class="rateb-card-body p-0">
                <table class="table rateb-table mb-0 table-sm" data-rateb-bulk-table="<?php echo ($bulkEnabled ?? false) ? '1' : '0'; ?>">
                    <thead>
                    <tr>
                        <?php if ($bulkEnabled ?? false) { ?>
                        <th class="rateb-bulk-th"><input type="checkbox" class="form-check-input" data-rateb-select-all></th>
                        <?php } ?>
                        <th><?php echo __('evaluation_date'); ?></th>
                        <th><?php echo __('description'); ?></th>
                        <th class="text-end"><?php echo __('amount'); ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($d['statement_lines'] ?? []) as $line) { ?>
                    <tr class="<?php echo !empty($line['is_reconciled']) ? 'table-success' : ''; ?>">
                        <?php if ($bulkEnabled ?? false) { ?>
                        <td class="rateb-bulk-td">
                            <?php if (empty($line['is_reconciled'])) { ?>
                            <input type="checkbox" class="form-check-input" data-rateb-row-check value="<?php echo (int) $line['id']; ?>">
                            <?php } ?>
                        </td>
                        <?php } ?>
                        <td><?php echo Rateb\App\Core\View::escape($line['line_date']); ?></td>
                        <td><?php echo Rateb\App\Core\View::escape($line['description']); ?></td>
                        <td class="text-end"><?php echo number_format((float) ($line['amount'] ?? 0), 2); ?></td>
                        <td class="text-nowrap">
                            <?php if (($canManage ?? false) && empty($line['is_reconciled'])) { ?>
                            <form method="post" action="<?php echo rateb_app_url('accounting/bank-reconciliation/lines/' . (int) $line['id'] . '/reconcile'); ?>" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                <input type="hidden" name="bank_account_id" value="<?php echo (int) $bankId; ?>">
                                <button type="submit" class="btn btn-xs btn-outline-success btn-sm"><?php echo __('mark_reconciled'); ?></button>
                            </form>
                            <form method="post" action="<?php echo rateb_app_url('accounting/bank-reconciliation/lines/' . (int) $line['id'] . '/delete'); ?>" class="d-inline"
                                  onsubmit="return confirm('<?php echo __('bulk_confirm_delete'); ?>');">
                                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                <input type="hidden" name="bank_account_id" value="<?php echo (int) $bankId; ?>">
                                <button type="submit" class="btn btn-xs btn-outline-danger btn-sm"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php } elseif (!empty($line['is_reconciled'])) { ?>
                            <span class="badge bg-success"><?php echo __('reconciled'); ?></span>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
