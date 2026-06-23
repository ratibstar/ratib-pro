<?php
$report = $report ?? ['account' => null, 'lines' => [], 'opening' => 0, 'closing' => 0];
$accounts = $accounts ?? [];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
Rateb\App\Core\View::partial('accounting-reports-back');
$account = $report['account'] ?? null;
?>
<form method="get" class="rateb-card mb-3">
    <div class="rateb-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label"><?php echo __('account'); ?></label>
                <select name="account_id" class="form-select" required>
                    <option value=""><?php echo __('select'); ?></option>
                    <?php foreach ($accounts as $acct) {
                        $label = rateb_locale() === 'ar' && !empty($acct['name_ar']) ? $acct['name_ar'] : ($acct['name'] ?? '');
                        $sel = (int) ($accountId ?? 0) === (int) ($acct['id'] ?? 0) ? ' selected' : '';
                        ?>
                    <option value="<?php echo (int) ($acct['id'] ?? 0); ?>"<?php echo $sel; ?>>
                        <?php echo Rateb\App\Core\View::escape(($acct['code'] ?? '') . ' — ' . $label); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('date_from'); ?></label>
                <input type="date" name="from" class="form-control" dir="ltr" lang="en" value="<?php echo Rateb\App\Core\View::escape($from ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('date_to'); ?></label>
                <input type="date" name="to" class="form-control" dir="ltr" lang="en" value="<?php echo Rateb\App\Core\View::escape($to ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><?php echo __('filter'); ?></button>
            </div>
        </div>
    </div>
</form>

<?php if (!$account) { ?>
<div class="rateb-card">
    <div class="rateb-card-body text-center text-muted py-5"><?php echo __('account_statement_select_account'); ?></div>
</div>
<?php } else {
    $name = rateb_locale() === 'ar' && !empty($account['name_ar']) ? $account['name_ar'] : ($account['name'] ?? '');
    ?>
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="rateb-card"><div class="rateb-card-body"><div class="rateb-stat-label"><?php echo __('account'); ?></div><div class="rateb-stat-value rateb-ar-text"><?php echo Rateb\App\Core\View::escape(($account['code'] ?? '') . ' ' . $name); ?></div></div></div></div>
    <div class="col-md-3"><div class="rateb-card"><div class="rateb-card-body"><div class="rateb-stat-label"><?php echo __('opening_balance'); ?></div><div class="rateb-stat-value"><?php echo number_format((float) ($report['opening'] ?? 0), 2); ?></div></div></div></div>
    <div class="col-md-3"><div class="rateb-card"><div class="rateb-card-body"><div class="rateb-stat-label"><?php echo __('closing_balance'); ?></div><div class="rateb-stat-value"><?php echo number_format((float) ($report['closing'] ?? 0), 2); ?></div></div></div></div>
    <div class="col-md-3"><div class="rateb-card"><div class="rateb-card-body"><div class="rateb-stat-label"><?php echo __('movement'); ?></div><div class="rateb-stat-value small"><?php echo number_format((float) ($report['total_debit'] ?? 0), 2); ?> / <?php echo number_format((float) ($report['total_credit'] ?? 0), 2); ?></div></div></div></div>
</div>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('general_account_statement'); ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('entry_no'); ?></th>
                    <th><?php echo __('evaluation_date'); ?></th>
                    <th><?php echo __('description'); ?></th>
                    <th class="text-end"><?php echo __('debit'); ?></th>
                    <th class="text-end"><?php echo __('credit'); ?></th>
                    <th class="text-end"><?php echo __('balance'); ?></th>
                </tr>
                </thead>
                <tbody>
                <tr class="table-light">
                    <td colspan="3"><?php echo __('opening_balance'); ?></td>
                    <td class="text-end">—</td>
                    <td class="text-end">—</td>
                    <td class="text-end"><?php echo number_format((float) ($report['opening'] ?? 0), 2); ?></td>
                </tr>
                <?php if (empty($report['lines'])) { ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($report['lines'] as $row) {
                    $desc = rateb_locale() === 'ar' && !empty($row['description_ar']) ? $row['description_ar'] : ($row['description'] ?? '');
                    ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($row['entry_no'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['entry_date'] ?? ''); ?></td>
                    <td class="rateb-ar-text"><?php echo Rateb\App\Core\View::escape($desc); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($row['debit'] ?? 0), 2); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($row['credit'] ?? 0), 2); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($row['balance'] ?? 0), 2); ?></td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>
