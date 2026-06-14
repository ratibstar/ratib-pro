<?php
$report = $report ?? ['lines' => []];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
Rateb\App\Core\View::partial('accounting-reports-back');
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
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('cost_center_report'); ?></div>
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0">
            <thead>
            <tr>
                <th><?php echo __('code'); ?></th>
                <th><?php echo __('name'); ?></th>
                <th class="text-end"><?php echo __('debit'); ?></th>
                <th class="text-end"><?php echo __('credit'); ?></th>
                <th class="text-end"><?php echo __('net'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($report['lines'])) { ?>
            <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else { foreach ($report['lines'] as $row) {
                $name = rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : $row['name'];
                $dr = (float) ($row['total_debit'] ?? 0);
                $cr = (float) ($row['total_credit'] ?? 0);
                ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape($row['code']); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($name); ?></td>
                <td class="text-end"><?php echo number_format($dr, 2); ?></td>
                <td class="text-end"><?php echo number_format($cr, 2); ?></td>
                <td class="text-end"><?php echo number_format($dr - $cr, 2); ?></td>
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
