<?php
$report = $report ?? ['accounts' => []];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
Rateb\App\Core\View::partial('accounting-reports-back');
$accounts = $report['accounts'] ?? [];
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

<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo __('partners_subsidiary_ledger'); ?></div>
    <div class="rateb-card-body">
        <p class="text-muted small mb-0"><?php echo __('partners_subsidiary_ledger_help'); ?></p>
    </div>
</div>

<?php if ($accounts === []) { ?>
<div class="rateb-card">
    <div class="rateb-card-body text-center text-muted py-5"><?php echo __('no_records'); ?></div>
</div>
<?php } else { foreach ($accounts as $block) {
    $acct = $block['account'] ?? [];
    $name = rateb_locale() === 'ar' && !empty($acct['name_ar']) ? $acct['name_ar'] : ($acct['name'] ?? '');
    ?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header d-flex flex-wrap justify-content-between gap-2">
        <span class="rateb-ar-text"><?php echo Rateb\App\Core\View::escape(($acct['code'] ?? '') . ' — ' . $name); ?></span>
        <span class="small text-muted"><?php echo __('closing_balance'); ?>: <?php echo number_format((float) ($block['closing'] ?? 0), 2); ?></span>
    </div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0 table-sm">
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
                    <td class="text-end"><?php echo number_format((float) ($block['opening'] ?? 0), 2); ?></td>
                </tr>
                <?php foreach ($block['lines'] ?? [] as $row) {
                    $desc = rateb_locale() === 'ar' && !empty($row['description_ar']) ? $row['description_ar'] : ($row['description'] ?? '');
                    ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($row['entry_no'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::formatDate($row['entry_date'] ?? ''); ?></td>
                    <td class="rateb-ar-text"><?php echo Rateb\App\Core\View::escape($desc); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($row['debit'] ?? 0), 2); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($row['credit'] ?? 0), 2); ?></td>
                    <td class="text-end"><?php echo number_format((float) ($row['balance'] ?? 0), 2); ?></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } } ?>
