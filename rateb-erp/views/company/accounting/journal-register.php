<?php
$rows = $rows ?? [];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
Rateb\App\Core\View::partial('accounting-reports-back');
$exportQs = http_build_query(array_filter(['from' => $from ?? '', 'to' => $to ?? '']));
if (rateb_can_export_entity('accounting')) {
    Rateb\App\Core\View::partial('export-toolbar', [
        'exportRoute' => rateb_app_url('accounting/export/journals') . ($exportQs ? '?' . $exportQs : ''),
    ]);
}
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
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('journal_register'); ?></div>
    <div class="rateb-card-body p-0" data-rateb-server-search="0">
        <?php Rateb\App\Core\View::partial('table-search', ['mode' => 'client', 'placeholder' => __('search_table')]); ?>
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('entry_no'); ?></th>
                    <th><?php echo __('evaluation_date'); ?></th>
                    <th><?php echo __('description'); ?></th>
                    <th><?php echo __('code'); ?></th>
                    <th><?php echo __('name'); ?></th>
                    <th class="text-end"><?php echo __('debit'); ?></th>
                    <th class="text-end"><?php echo __('credit'); ?></th>
                    <th><?php echo __('status'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)) { ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($rows as $row) {
                    $desc = rateb_locale() === 'ar' && !empty($row['description_ar']) ? $row['description_ar'] : ($row['description'] ?? '');
                    $name = rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : ($row['name'] ?? '');
                    ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($row['entry_no'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::formatDate($row['entry_date'] ?? ''); ?></td>
                    <td class="rateb-ar-text"><?php echo Rateb\App\Core\View::escape($desc); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['code'] ?? ''); ?></td>
                    <td class="rateb-ar-text"><?php echo Rateb\App\Core\View::escape($name); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($row['debit'] ?? 0), 2); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($row['credit'] ?? 0), 2); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape(__((string) ($row['status'] ?? ''))); ?></td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
