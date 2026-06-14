<?php
$report = $report ?? ['revenue' => 0, 'expenses' => 0, 'net' => 0, 'lines' => []];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
Rateb\App\Core\View::partial('accounting-reports-back');
$exportQs = http_build_query(array_filter(['from' => $from ?? '', 'to' => $to ?? '']));
Rateb\App\Core\View::partial('export-toolbar', ['exportRoute' => rateb_app_url('accounting/export/profit-loss') . ($exportQs ? '?' . $exportQs : '')]);
?>
<form method="get" class="rateb-card mb-3">
    <div class="rateb-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><?php echo __('date_from'); ?></label>
                <input type="date" name="from" class="form-control" value="<?php echo Rateb\App\Core\View::escape($from ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('date_to'); ?></label>
                <input type="date" name="to" class="form-control" value="<?php echo Rateb\App\Core\View::escape($to ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><?php echo __('filter'); ?></button>
            </div>
        </div>
    </div>
</form>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('revenue'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) $report['revenue'], 2); ?> <small>SAR</small></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('expenses'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) $report['expenses'], 2); ?> <small>SAR</small></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('net_income'); ?></div>
            <div class="rateb-stat-value"><?php echo number_format((float) $report['net'], 2); ?> <small>SAR</small></div>
        </div>
    </div>
</div>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('profit_loss'); ?></div>
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0">
            <thead>
            <tr>
                <th><?php echo __('code'); ?></th>
                <th><?php echo __('name'); ?></th>
                <th><?php echo __('account_type'); ?></th>
                <th class="text-end"><?php echo __('amount'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($report['lines'])) { ?>
            <tr><td colspan="4" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else { foreach ($report['lines'] as $line) {
                $name = rateb_locale() === 'ar' && !empty($line['name_ar']) ? $line['name_ar'] : $line['name'];
                $dr = (float) ($line['total_debit'] ?? 0);
                $cr = (float) ($line['total_credit'] ?? 0);
                $amt = ($line['account_type'] ?? '') === 'revenue' ? $cr - $dr : $dr - $cr;
                ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape($line['code']); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($name); ?></td>
                <td><?php echo __((string) ($line['account_type'] ?? '')); ?></td>
                <td class="text-end"><?php echo number_format($amt, 2); ?></td>
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
