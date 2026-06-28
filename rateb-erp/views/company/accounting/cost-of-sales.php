<?php
$report = $report ?? ['total' => 0, 'accounts' => [], 'entries' => []];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
Rateb\App\Core\View::partial('accounting-reports-back');
?>
<form method="get" class="rateb-card mb-3">
    <div class="rateb-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><?php echo __('date_from'); ?></label>
                <input type="date" name="from" class="form-control" dir="ltr" lang="en" value="<?php echo Rateb\App\Core\View::escape($from ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('date_to'); ?></label>
                <input type="date" name="to" class="form-control" dir="ltr" lang="en" value="<?php echo Rateb\App\Core\View::escape($to ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><?php echo __('filter'); ?></button>
            </div>
        </div>
    </div>
</form>
<div class="rateb-card mb-4">
    <div class="rateb-card-header"><?php echo __('cost_of_sales_accounts'); ?></div>
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
            <?php if (empty($report['accounts'])) { ?>
            <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else { foreach ($report['accounts'] as $row) {
                $name = rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : $row['name'];
                $dr = (float) ($row['total_debit'] ?? 0);
                $cr = (float) ($row['total_credit'] ?? 0);
                ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape($row['code']); ?></td>
                <td class="rateb-ar-text"><?php echo Rateb\App\Core\View::escape($name); ?></td>
                <td class="text-end"><?php echo number_format($dr, 2); ?></td>
                <td class="text-end"><?php echo number_format($cr, 2); ?></td>
                <td class="text-end"><?php echo number_format($dr - $cr, 2); ?></td>
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('cost_of_sales_entries'); ?></div>
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0">
            <thead>
            <tr>
                <th><?php echo __('entry_no'); ?></th>
                <th><?php echo __('evaluation_date'); ?></th>
                <th><?php echo __('description'); ?></th>
                <th><?php echo __('code'); ?></th>
                <th class="text-end"><?php echo __('debit'); ?></th>
                <th class="text-end"><?php echo __('credit'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($report['entries'])) { ?>
            <tr><td colspan="6" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else { foreach ($report['entries'] as $row) {
                $desc = rateb_locale() === 'ar' && !empty($row['description_ar']) ? $row['description_ar'] : ($row['description'] ?? '');
                $acct = rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : ($row['name'] ?? '');
                ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape($row['entry_no'] ?? ''); ?></td>
                <td><?php echo Rateb\App\Core\View::formatDate($row['entry_date'] ?? ''); ?></td>
                <td class="rateb-ar-text"><?php echo Rateb\App\Core\View::escape($desc); ?></td>
                <td><?php echo Rateb\App\Core\View::escape(($row['code'] ?? '') . ' — ' . $acct); ?></td>
                <td class="text-end"><?php echo number_format((float) ($row['debit'] ?? 0), 2); ?></td>
                <td class="text-end"><?php echo number_format((float) ($row['credit'] ?? 0), 2); ?></td>
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
