<?php declare(strict_types=1);
$types = [
    'sales' => __('sales_by_branch'),
    'profit' => __('profit_by_branch'),
    'expenses' => __('expenses_by_branch'),
    'inventory' => __('inventory_by_branch'),
    'employees' => __('employees_by_branch'),
];
$type = (string) ($type ?? 'sales');
?>
<div class="rateb-page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h4 mb-0"><?php echo Rateb\App\Core\View::escape($title ?? __('branch_reports')); ?></h1>
    <div class="btn-group btn-group-sm">
        <?php foreach ($types as $key => $label) { ?>
        <a class="btn btn-outline-secondary <?php echo $type === $key ? 'active' : ''; ?>" href="<?php echo rateb_url(rateb_app_route('branch-dashboard/reports?type=' . rawurlencode($key))); ?>"><?php echo Rateb\App\Core\View::escape($label); ?></a>
        <?php } ?>
    </div>
</div>

<div class="table-responsive rateb-card">
    <table class="table table-sm mb-0">
        <thead><tr><th><?php echo __('branch'); ?></th><th><?php echo __('code'); ?></th><th><?php echo __('value'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($rows ?? [] as $row) {
            $val = $row['metric'] ?? ($row['sales_total'] ?? 0);
        ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($row['code'] ?? '')); ?></td>
                <td><?php echo is_numeric($val) && !str_contains($type, 'employees') ? number_format((float) $val, 2) : (int) $val; ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div>
