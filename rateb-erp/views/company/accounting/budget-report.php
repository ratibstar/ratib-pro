<?php
$report = $report ?? ['lines' => [], 'totals' => ['budget' => 0, 'actual' => 0, 'variance' => 0]];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
?>
<form method="get" class="rateb-card mb-3">
    <div class="rateb-card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><?php echo __('fiscal_year'); ?></label>
                <input type="number" name="year" class="form-control" value="<?php echo (int) ($year ?? date('Y')); ?>" min="2000" max="2100">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><?php echo __('filter'); ?></button>
            </div>
        </div>
    </div>
</form>
<?php if ($canManage ?? false) { ?>
<form method="post" action="<?php echo rateb_app_url('accounting/budget-report'); ?>" class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo __('set_budget'); ?></div>
    <div class="rateb-card-body">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <input type="hidden" name="fiscal_year" value="<?php echo (int) ($year ?? date('Y')); ?>">
        <div class="row g-2 mb-2">
            <div class="col-md-6">
                <select name="budget_account_id[]" class="form-select">
                    <option value=""><?php echo __('select_account'); ?></option>
                    <?php foreach ($accounts as $acc) {
                        $label = $acc['code'] . ' — ' . (rateb_locale() === 'ar' && !empty($acc['name_ar']) ? $acc['name_ar'] : $acc['name']);
                        ?>
                    <option value="<?php echo (int) $acc['id']; ?>"><?php echo Rateb\App\Core\View::escape($label); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-4">
                <input type="number" step="0.01" min="0" name="budget_amount[]" class="form-control" placeholder="<?php echo __('amount'); ?>">
            </div>
        </div>
        <p class="text-muted small"><?php echo __('budget_entry_hint'); ?></p>
        <button type="submit" class="btn btn-sm btn-outline-primary"><?php echo __('save_budget'); ?></button>
    </div>
</form>
<?php } ?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('budget_vs_actual'); ?></div>
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0">
            <thead>
            <tr>
                <th><?php echo __('code'); ?></th>
                <th><?php echo __('name'); ?></th>
                <th class="text-end"><?php echo __('budget'); ?></th>
                <th class="text-end"><?php echo __('actual'); ?></th>
                <th class="text-end"><?php echo __('variance'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($report['lines'])) { ?>
            <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else { foreach ($report['lines'] as $row) {
                $name = rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : $row['name'];
                ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape($row['code']); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($name); ?></td>
                <td class="text-end"><?php echo number_format((float) ($row['budget_amount'] ?? 0), 2); ?></td>
                <td class="text-end"><?php echo number_format((float) ($row['actual_amount'] ?? 0), 2); ?></td>
                <td class="text-end"><?php echo number_format((float) ($row['variance'] ?? 0), 2); ?></td>
            </tr>
            <?php } } ?>
            </tbody>
            <?php if (!empty($report['lines'])) { ?>
            <tfoot>
            <tr class="fw-bold">
                <td colspan="2"><?php echo __('total'); ?></td>
                <td class="text-end"><?php echo number_format((float) ($report['totals']['budget'] ?? 0), 2); ?></td>
                <td class="text-end"><?php echo number_format((float) ($report['totals']['actual'] ?? 0), 2); ?></td>
                <td class="text-end"><?php echo number_format((float) ($report['totals']['variance'] ?? 0), 2); ?></td>
            </tr>
            </tfoot>
            <?php } ?>
        </table>
    </div>
</div>
