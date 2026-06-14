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
                    <th style="width:50px"></th>
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
                    <td><button type="button" class="btn btn-sm btn-outline-danger" data-journal-lines-remove><i class="fas fa-times"></i></button></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small mb-0"><?php echo __('journal_balance_hint'); ?></p>
    </div>
    <div class="rateb-card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
        <a href="<?php echo rateb_app_url('journal-entries'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
    </div>
</form>
<script>
(function () {
    var table = document.querySelector('[data-journal-lines-table]');
    if (!table) return;
    var addBtn = document.querySelector('[data-journal-lines-add]');
    var tbody = table.querySelector('tbody');
    addBtn && addBtn.addEventListener('click', function () {
        var row = tbody.querySelector('[data-journal-lines-row]');
        if (!row) return;
        tbody.appendChild(row.cloneNode(true));
        var last = tbody.lastElementChild;
        last.querySelectorAll('input').forEach(function (el) { el.value = ''; });
        last.querySelectorAll('select').forEach(function (el) { el.selectedIndex = 0; });
    });
    tbody.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-journal-lines-remove]');
        if (!btn) return;
        if (tbody.querySelectorAll('[data-journal-lines-row]').length <= 1) return;
        btn.closest('tr').remove();
    });
})();
</script>
