<?php
use Rateb\App\Services\FormLookupService;

/** @var array<string, mixed>|null $entry */
/** @var array<int, array<string, mixed>> $lines */
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
$isEdit = !empty($entry);
$action = $isEdit
    ? rateb_app_url('journal-entries/' . (int) $entry['id'])
    : rateb_app_url('journal-entries');
$rows = $lines;
if (empty($rows)) {
    $rows = [['account_id' => '', 'cost_center_id' => '', 'debit' => '', 'credit' => '', 'memo' => '']];
}
$headerFields = FormLookupService::journalEntryHeaderFormFields();
$lookupSvc = new FormLookupService();
$lookups = $lookupSvc->forFields(array_merge($headerFields, [
    ['lookup' => 'chart_of_accounts'],
    ['lookup' => 'cost_centers'],
    ['lookup' => 'branches'],
]));
$coaOptions = $lookups['chart_of_accounts'] ?? [];
$ccOptions = $lookups['cost_centers'] ?? [];
?>
<form method="post" action="<?php echo $action; ?>" class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <div class="mb-4">
            <?php Rateb\App\Core\View::partial('accounting-form', [
                'formFields' => $headerFields,
                'item' => $entry,
                'lookups' => $lookups,
            ]); ?>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0"><?php echo __('journal_lines'); ?></h6>
            <button type="button" class="btn btn-sm btn-outline-primary" data-journal-lines-add>
                <i class="fas fa-plus"></i> <?php echo __('add_line'); ?>
            </button>
        </div>
        <div class="table-responsive">
            <table class="table rateb-table" data-journal-lines-table>
                <thead>
                <tr>
                    <th><?php echo __('account'); ?></th>
                    <th><?php echo __('cost_center'); ?></th>
                    <th class="text-end" style="width:120px"><?php echo __('debit'); ?></th>
                    <th class="text-end" style="width:120px"><?php echo __('credit'); ?></th>
                    <th><?php echo __('memo'); ?></th>
                    <th class="text-end rateb-accounting-actions-col"><?php echo __('actions'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $line) { ?>
                <tr data-journal-lines-row>
                    <td>
                        <select name="line_account_id[]" class="form-select form-select-sm" required>
                            <option value=""><?php echo __('select_account'); ?></option>
                            <?php foreach ($coaOptions as $opt) {
                                $sel = (int) ($line['account_id'] ?? 0) === (int) $opt['value'] ? ' selected' : '';
                                ?>
                            <option value="<?php echo (int) $opt['value']; ?>"<?php echo $sel; ?>><?php echo Rateb\App\Core\View::escape($opt['label']); ?></option>
                            <?php } ?>
                        </select>
                    </td>
                    <td>
                        <select name="line_cost_center_id[]" class="form-select form-select-sm">
                            <option value=""><?php echo __('optional'); ?></option>
                            <?php foreach ($ccOptions as $opt) {
                                $ccSel = (int) ($line['cost_center_id'] ?? 0) === (int) $opt['value'] ? ' selected' : '';
                                ?>
                            <option value="<?php echo (int) $opt['value']; ?>"<?php echo $ccSel; ?>><?php echo Rateb\App\Core\View::escape($opt['label']); ?></option>
                            <?php } ?>
                        </select>
                    </td>
                    <td><input type="number" step="0.01" min="0" name="line_debit[]" class="form-control form-control-sm text-end"
                               value="<?php echo Rateb\App\Core\View::escape((string) ($line['debit'] ?? '')); ?>"></td>
                    <td><input type="number" step="0.01" min="0" name="line_credit[]" class="form-control form-control-sm text-end"
                               value="<?php echo Rateb\App\Core\View::escape((string) ($line['credit'] ?? '')); ?>"></td>
                    <td><input type="text" name="line_memo[]" class="form-control form-control-sm"
                               value="<?php echo Rateb\App\Core\View::escape((string) ($line['memo'] ?? '')); ?>"></td>
                    <td class="text-end rateb-actions-cell">
                        <button type="button" class="btn btn-sm btn-outline-danger" data-journal-lines-remove title="<?php echo __('delete'); ?>">
                            <i class="fas fa-times" aria-hidden="true"></i><span class="rateb-btn-label"><?php echo __('delete'); ?></span>
                        </button>
                    </td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="journal-balance-bar mt-3" data-journal-balance
             data-msg-unbalanced="<?php echo Rateb\App\Core\View::escape(__('journal_not_balanced')); ?>"
             data-msg-hint="<?php echo Rateb\App\Core\View::escape(__('journal_balance_hint')); ?>">
            <div class="d-flex flex-wrap gap-3 align-items-center mb-2">
                <span><?php echo __('debit'); ?>: <strong data-journal-total-debit>0.00</strong></span>
                <span><?php echo __('credit'); ?>: <strong data-journal-total-credit>0.00</strong></span>
                <span><?php echo __('difference'); ?>: <strong data-journal-diff>0.00</strong></span>
            </div>
            <div class="alert alert-warning mb-0" data-journal-unbalanced-alert role="alert" hidden>
                <?php echo __('journal_balance_hint'); ?>
            </div>
            <p class="text-muted small mb-0" data-journal-balance-ok><?php echo __('journal_balance_hint'); ?></p>
        </div>
    </div>
    <div class="rateb-card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
        <a href="<?php echo rateb_app_url('journal-entries'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
    </div>
</form>
<script src="<?php echo htmlspecialchars(rateb_asset('js/journal-lines.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
