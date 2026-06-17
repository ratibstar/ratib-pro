<?php
declare(strict_types=1);

/** @var string $hrActive */
$hrActive = (string) ($hrActive ?? '');
$menu = require RATEB_ROOT . '/config/hr-menu.php';
$currentRoute = function_exists('rateb_normalize_erp_route')
    ? rateb_normalize_erp_route(rateb_current_erp_route())
    : '';

$routeMatches = static function (string $route) use ($currentRoute): bool {
    if ($route === '' || $currentRoute === '') {
        return false;
    }
    $target = rateb_app_route($route);
    if ($currentRoute === $target) {
        return true;
    }
    if ($route === 'hr/leaves' && str_starts_with($currentRoute, rateb_app_route('hr/leaves'))) {
        return !str_starts_with($currentRoute, rateb_app_route('hr/leave-types'));
    }
    return str_starts_with($currentRoute, $target . '/');
};

$groupOpen = static function (array $item) use ($hrActive, $routeMatches): bool {
    if (!empty($item['children'])) {
        foreach ($item['children'] as $child) {
            if (($child['id'] ?? '') === $hrActive || $routeMatches((string) ($child['route'] ?? ''))) {
                return true;
            }
        }
    }
    return false;
};

$renderLink = static function (array $item, bool $isChild = false) use ($hrActive, $routeMatches): void {
    $id = (string) ($item['id'] ?? '');
    $route = (string) ($item['route'] ?? '');
    $active = $id === $hrActive || $routeMatches($route);
    $url = $route !== '' ? rateb_url_with_ops_company(rateb_app_route($route)) : '#';
    $class = 'rateb-hr-tree-link' . ($isChild ? ' is-child' : '') . ($active ? ' is-active' : '');
    ?>
<a href="<?php echo Rateb\App\Core\View::escape($url); ?>" class="<?php echo $class; ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
    <span class="rateb-hr-tree-icon"><i class="fas <?php echo Rateb\App\Core\View::escape((string) ($item['icon'] ?? 'fa-circle')); ?>"></i></span>
    <span class="rateb-hr-tree-label"><?php echo Rateb\App\Core\View::escape(__((string) ($item['label'] ?? $id))); ?></span>
    <?php if (!empty($item['stub'])) { ?>
    <span class="rateb-hr-tree-soon badge bg-secondary"><?php echo __('soon'); ?></span>
    <?php } ?>
</a>
<?php
};
?>
<aside class="rateb-hr-tree" aria-label="<?php echo Rateb\App\Core\View::escape(__('human_resources')); ?>">
    <div class="rateb-hr-tree-brand">
        <i class="fas fa-users-gear"></i>
        <span><?php echo __('human_resources'); ?></span>
    </div>
    <nav class="rateb-hr-tree-nav">
        <?php foreach ($menu as $item) {
            if (!empty($item['children'])) {
                $open = $groupOpen($item);
                ?>
        <div class="rateb-hr-tree-group<?php echo $open ? ' is-open' : ''; ?>" data-hr-tree-group>
            <button type="button" class="rateb-hr-tree-group-toggle" data-hr-tree-toggle aria-expanded="<?php echo $open ? 'true' : 'false'; ?>">
                <span class="rateb-hr-tree-icon"><i class="fas <?php echo Rateb\App\Core\View::escape((string) ($item['icon'] ?? 'fa-folder')); ?>"></i></span>
                <span class="rateb-hr-tree-label"><?php echo Rateb\App\Core\View::escape(__((string) ($item['label'] ?? ''))); ?></span>
                <i class="fas fa-chevron-down rateb-hr-tree-chevron" aria-hidden="true"></i>
            </button>
            <div class="rateb-hr-tree-children">
                <?php foreach ($item['children'] as $child) {
                    $renderLink($child, true);
                } ?>
            </div>
        </div>
                <?php
            } else {
                $renderLink($item, false);
            }
        } ?>
    </nav>
</aside>
