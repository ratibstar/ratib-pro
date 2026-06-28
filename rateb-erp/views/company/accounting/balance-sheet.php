<?php
$report = $report ?? ['assets' => 0, 'liabilities' => 0, 'equity' => 0, 'lines' => []];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
Rateb\App\Core\View::partial('accounting-reports-back');
$exportQs = http_build_query(array_filter(['as_of' => $asOf ?? '']));
Rateb\App\Core\View::partial('export-toolbar', ['exportRoute' => rateb_app_url('accounting/export/balance-sheet') . ($exportQs ? '?' . $exportQs : '')]);
?>
<form method="get" class="rateb-card mb-3">
    <div class="rateb-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><?php echo __('as_of_date'); ?></label>
                <input type="date" name="as_of" class="form-control" value="<?php echo Rateb\App\Core\View::escape($asOf ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><?php echo __('filter'); ?></button>
            </div>
        </div>
    </div>
</form>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('balance_sheet'); ?></div>
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0">
            <thead>
            <tr>
                <th><?php echo __('code'); ?></th>
                <th><?php echo __('name'); ?></th>
                <th><?php echo __('account_type'); ?></th>
                <th class="text-end"><?php echo __('balance'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($report['lines'])) { ?>
            <tr><td colspan="4" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else { foreach ($report['lines'] as $line) {
                $name = rateb_locale() === 'ar' && !empty($line['name_ar']) ? $line['name_ar'] : $line['name'];
                $dr = (float) ($line['total_debit'] ?? 0);
                $cr = (float) ($line['total_credit'] ?? 0);
                $type = (string) ($line['account_type'] ?? '');
                $bal = $type === 'asset' ? $dr - $cr : ($type === 'liability' || $type === 'equity' ? $cr - $dr : 0);
                if (abs($bal) < 0.005) {
                    continue;
                }
                ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape($line['code']); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($name); ?></td>
                <td><?php echo __($type); ?></td>
                <td class="text-end"><?php echo number_format($bal, 2); ?></td>
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
