<?php declare(strict_types=1); ?>
<div class="rateb-page-header mb-3">
    <h1 class="h4 mb-0"><?php echo Rateb\App\Core\View::escape($title ?? __('branch_comparison')); ?></h1>
</div>

<form method="get" class="rateb-card mb-3">
    <div class="rateb-card-body row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label"><?php echo __('branch_a'); ?></label>
            <select name="branch_a" class="form-select">
                <option value=""><?php echo __('select'); ?></option>
                <?php foreach ($branches ?? [] as $b) { ?>
                <option value="<?php echo (int) $b['id']; ?>" <?php echo ((int) ($branchA ?? 0) === (int) $b['id']) ? 'selected' : ''; ?>><?php echo Rateb\App\Core\View::escape((string) ($b['name'] ?? '')); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label"><?php echo __('branch_b'); ?></label>
            <select name="branch_b" class="form-select">
                <option value=""><?php echo __('select'); ?></option>
                <?php foreach ($branches ?? [] as $b) { ?>
                <option value="<?php echo (int) $b['id']; ?>" <?php echo ((int) ($branchB ?? 0) === (int) $b['id']) ? 'selected' : ''; ?>><?php echo Rateb\App\Core\View::escape((string) ($b['name'] ?? '')); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary"><?php echo __('compare'); ?></button>
        </div>
    </div>
</form>

<?php if (!empty($comparison)) {
    $a = $comparison['branch_a'] ?? [];
    $b = $comparison['branch_b'] ?? [];
    $metrics = ['sales_total', 'purchases_total', 'expenses_total', 'profit_total', 'employees_count', 'inventory_value'];
?>
<div class="row g-3">
    <div class="col-md-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape((string) ($a['name'] ?? '')); ?></div>
            <div class="rateb-card-body small">
                <?php foreach ($metrics as $m) { ?>
                <div class="d-flex justify-content-between"><span><?php echo __($m); ?></span><strong><?php echo is_float($a[$m] ?? 0) || str_contains($m, 'total') || str_contains($m, 'value') ? number_format((float) ($a[$m] ?? 0), 2) : (int) ($a[$m] ?? 0); ?></strong></div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape((string) ($b['name'] ?? '')); ?></div>
            <div class="rateb-card-body small">
                <?php foreach ($metrics as $m) { ?>
                <div class="d-flex justify-content-between"><span><?php echo __($m); ?></span><strong><?php echo is_float($b[$m] ?? 0) || str_contains($m, 'total') || str_contains($m, 'value') ? number_format((float) ($b[$m] ?? 0), 2) : (int) ($b[$m] ?? 0); ?></strong></div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<canvas id="branchCompareChart" class="mt-4" height="120"></canvas>
<script>
(function () {
    var a = <?php echo json_encode(array_map('floatval', array_intersect_key($a, array_flip(['sales_total','purchases_total','expenses_total','profit_total']))), JSON_UNESCAPED_UNICODE); ?>;
    var b = <?php echo json_encode(array_map('floatval', array_intersect_key($b, array_flip(['sales_total','purchases_total','expenses_total','profit_total']))), JSON_UNESCAPED_UNICODE); ?>;
    var el = document.getElementById('branchCompareChart');
    if (!el || typeof Chart === 'undefined') return;
    new Chart(el, {
        type: 'bar',
        data: {
            labels: ['<?php echo __('sales_total'); ?>','<?php echo __('purchases_total'); ?>','<?php echo __('expenses_total'); ?>','<?php echo __('profit_total'); ?>'],
            datasets: [
                { label: <?php echo json_encode((string) ($a['name'] ?? 'A')); ?>, data: [a.sales_total||0,a.purchases_total||0,a.expenses_total||0,a.profit_total||0] },
                { label: <?php echo json_encode((string) ($b['name'] ?? 'B')); ?>, data: [b.sales_total||0,b.purchases_total||0,b.expenses_total||0,b.profit_total||0] }
            ]
        }
    });
})();
</script>
<?php } ?>
