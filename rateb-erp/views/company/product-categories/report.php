<?php
/** @var array<int, array<string, mixed>> $rows */
$rows = $rows ?? [];
?>
<div class="mb-3">
    <a href="<?php echo rateb_url($routePrefix ?? rateb_app_route('product-categories')); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('back'); ?></a>
    <?php Rateb\App\Core\View::partial('export-toolbar', [
        'exportRoute' => $exportRoute ?? '',
        'exportEnabled' => $exportEnabled ?? true,
        'inline' => true,
    ]); ?>
</div>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('category_products_report'); ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('category_code'); ?></th>
                    <th><?php echo __('name'); ?></th>
                    <th><?php echo __('product_count'); ?></th>
                    <th><?php echo __('stock_value'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []) { ?>
                <tr><td colspan="4" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else {
                    foreach ($rows as $row) { ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['category_code'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['category_name'] ?? '')); ?></td>
                    <td><?php echo (int) ($row['product_count'] ?? 0); ?></td>
                    <td class="rateb-ltr-num"><?php echo number_format((float) ($row['stock_value'] ?? 0), 2); ?></td>
                </tr>
                <?php }
                } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
