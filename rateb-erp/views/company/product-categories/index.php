<?php
$categoryTree = $categoryTree ?? [];
$mostUsed = $mostUsed ?? [];
?>
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('category_tree'); ?></div>
            <div class="rateb-card-body">
                <?php if ($categoryTree === []) { ?>
                <p class="text-muted mb-0 small"><?php echo __('no_records'); ?></p>
                <?php } else {
                    $svc = new \Rateb\App\Services\ProductCategoryService();
                    $renderTree = static function (array $nodes, int $depth = 0) use (&$renderTree, $svc): void {
                        echo '<ul class="list-unstyled mb-0' . ($depth > 0 ? ' ms-3' : '') . '">';
                        foreach ($nodes as $node) {
                            $icon = trim((string) ($node['icon'] ?? ''));
                            $catId = (int) ($node['id'] ?? 0);
                            $imgUrl = $svc->imageUrl($catId, $node['image_path'] ?? null);
                            echo '<li class="py-1 d-flex align-items-center gap-2">';
                            if ($imgUrl !== '') {
                                echo '<button type="button" class="btn p-0 border-0 bg-transparent flex-shrink-0" data-rateb-image-preview="' . Rateb\App\Core\View::escape($imgUrl) . '" title="' . Rateb\App\Core\View::escape(__('view_image')) . '">';
                                echo '<img src="' . Rateb\App\Core\View::escape($imgUrl) . '" alt="" class="rounded border" style="width:28px;height:28px;object-fit:cover;cursor:zoom-in;">';
                                echo '</button>';
                            } elseif ($icon !== '') {
                                echo '<i class="fas ' . Rateb\App\Core\View::escape($icon) . '"></i>';
                            }
                            echo '<span>' . Rateb\App\Core\View::escape((string) ($node['label'] ?? ''));
                            if (empty($node['is_active'])) {
                                echo ' <span class="badge bg-secondary">' . __('inactive') . '</span>';
                            }
                            if (empty($node['is_visible'])) {
                                echo ' <span class="badge bg-warning text-dark">' . __('category_hidden') . '</span>';
                            }
                            echo '</span></li>';
                            if (!empty($node['children'])) {
                                $renderTree($node['children'], $depth + 1);
                            }
                        }
                        echo '</ul>';
                    };
                    $renderTree($categoryTree);
                } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="rateb-card h-100">
            <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><?php echo __('most_used_categories'); ?></span>
                <div class="d-flex gap-2">
                    <a href="<?php echo rateb_url($reportRoute ?? rateb_app_url('product-categories/report')); ?>" class="btn btn-outline-primary btn-sm"><?php echo __('category_products_report'); ?></a>
                    <?php Rateb\App\Core\View::partial('export-toolbar', ['exportRoute' => $exportRoute ?? '', 'exportEnabled' => $exportEnabled ?? true, 'inline' => true]); ?>
                </div>
            </div>
            <div class="rateb-card-body p-0">
                <table class="table rateb-table mb-0 table-sm">
                    <thead><tr><th><?php echo __('name'); ?></th><th><?php echo __('product_count'); ?></th></tr></thead>
                    <tbody>
                    <?php if ($mostUsed === []) { ?>
                    <tr><td colspan="2" class="text-center text-muted py-3"><?php echo __('no_records'); ?></td></tr>
                    <?php } else {
                        foreach ($mostUsed as $row) {
                            $name = rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : ($row['name'] ?? '');
                            ?>
                    <tr>
                        <td><?php echo Rateb\App\Core\View::escape((string) $name); ?></td>
                        <td><?php echo (int) ($row['product_count'] ?? 0); ?></td>
                    </tr>
                    <?php }
                    } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php
$copyEnabled = $actionsEnabled ?? true;
Rateb\App\Core\View::partial('crud-index', get_defined_vars());
if ($copyEnabled && !empty($items)) { ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var base = <?php echo json_encode(rateb_url($routePrefix ?? '')); ?>;
    document.querySelectorAll('[data-rateb-bulk-table] tbody tr').forEach(function (tr) {
        var actions = tr.querySelector('.rateb-actions');
        if (!actions) return;
        var edit = actions.querySelector('a[href*="/edit"]');
        if (!edit) return;
        var m = edit.getAttribute('href').match(/\/(\d+)\/edit/);
        if (!m) return;
        var copy = document.createElement('a');
        copy.className = 'btn btn-sm btn-outline-secondary';
        copy.href = base + '/' + m[1] + '/copy';
        copy.title = <?php echo json_encode(__('copy_category')); ?>;
        copy.innerHTML = '<i class="fas fa-copy"></i>';
        actions.prepend(copy);
    });
});
</script>
<?php } ?>
