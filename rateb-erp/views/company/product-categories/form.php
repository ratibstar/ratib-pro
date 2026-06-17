<?php
$breadcrumbs = $breadcrumbs ?? [];
$categoryTree = $categoryTree ?? [];
?>
<?php if ($breadcrumbs !== []) { ?>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?php echo rateb_url($routePrefix ?? rateb_app_route('product-categories')); ?>"><?php echo __('product_categories'); ?></a></li>
        <?php foreach ($breadcrumbs as $i => $crumb) {
            $isLast = $i === count($breadcrumbs) - 1;
            if ($isLast) { ?>
        <li class="breadcrumb-item active" aria-current="page"><?php echo Rateb\App\Core\View::escape((string) ($crumb['label'] ?? '')); ?></li>
            <?php } else { ?>
        <li class="breadcrumb-item"><?php echo Rateb\App\Core\View::escape((string) ($crumb['label'] ?? '')); ?></li>
            <?php }
        } ?>
    </ol>
</nav>
<?php } ?>
<div class="row g-3">
    <div class="col-lg-8">
        <?php Rateb\App\Core\View::partial('crud-form', get_defined_vars()); ?>
    </div>
    <div class="col-lg-4">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('category_tree'); ?></div>
            <div class="rateb-card-body small">
                <?php if ($categoryTree === []) { ?>
                <p class="text-muted mb-0"><?php echo __('no_records'); ?></p>
                <?php } else {
                    $renderTree = static function (array $nodes, int $depth = 0) use (&$renderTree): void {
                        echo '<ul class="list-unstyled mb-0' . ($depth > 0 ? ' ms-3' : '') . '">';
                        foreach ($nodes as $node) {
                            echo '<li class="py-1">' . Rateb\App\Core\View::escape((string) ($node['label'] ?? '')) . '</li>';
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
        <p class="text-muted small mt-2 mb-0"><?php echo __('category_icon_hint'); ?></p>
        <p class="text-muted small mb-0"><?php echo __('category_image_hint'); ?></p>
    </div>
</div>
