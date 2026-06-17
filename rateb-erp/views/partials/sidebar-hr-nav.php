<?php
declare(strict_types=1);

/**
 * Human Resources — nested sidebar section (tree inside main nav).
 */
$entity = rateb_entity_perms('hr');
if (!rateb_nav_can($entity['view'], 'hr')) {
    return;
}

$menu = require RATEB_ROOT . '/config/hr-menu.php';
$erpRoute = rateb_current_erp_route();

$hrRouteActive = static function (string $resourcePath) use ($erpRoute, $navActive): bool {
    $route = rateb_app_route($resourcePath);
    if ($resourcePath === 'hr') {
        return $erpRoute === $route;
    }
    if ($resourcePath === 'hr/leaves') {
        $leaveTypes = rateb_app_route('hr/leave-types');
        if ($erpRoute === $route || str_starts_with($erpRoute, $route . '/')) {
            return !str_starts_with($erpRoute, $leaveTypes);
        }
        return false;
    }
    return $navActive($route);
};

$hrSectionActive = $navActive(rateb_app_route('hr'));

$renderHrLink = static function (array $item) use ($hrRouteActive): void {
    $route = (string) ($item['route'] ?? '');
    if ($route === '') {
        return;
    }
    $active = $hrRouteActive($route) ? ' active' : '';
    $url = rateb_url_with_ops_company(rateb_app_route($route));
    echo '<a href="' . Rateb\App\Core\View::escape($url) . '" class="rateb-nav-link' . $active . '">';
    echo '<i class="fas ' . Rateb\App\Core\View::escape((string) ($item['icon'] ?? 'fa-circle')) . '"></i>';
    echo '<span>' . Rateb\App\Core\View::escape(__((string) ($item['label'] ?? ''))) . '</span>';
    if (!empty($item['stub'])) {
        echo '<span class="rateb-nav-soon">' . __('soon') . '</span>';
    }
    echo '</a>';
};

$renderHrSubgroup = static function (array $item) use ($hrRouteActive, $renderHrLink): void {
    $open = false;
    foreach ($item['children'] ?? [] as $child) {
        if ($hrRouteActive((string) ($child['route'] ?? ''))) {
            $open = true;
            break;
        }
    }
    $openClass = $open ? ' is-open' : '';
    echo '<div class="rateb-nav-subgroup' . $openClass . '" data-nav-group>';
    echo '<button type="button" class="rateb-nav-subgroup-toggle" data-nav-group-toggle aria-expanded="' . ($open ? 'true' : 'false') . '">';
    echo '<i class="fas ' . Rateb\App\Core\View::escape((string) ($item['icon'] ?? 'fa-folder')) . '"></i>';
    echo '<span>' . Rateb\App\Core\View::escape(__((string) ($item['label'] ?? ''))) . '</span>';
    echo '<i class="fas fa-chevron-down rateb-nav-subgroup-chevron" aria-hidden="true"></i>';
    echo '</button><div class="rateb-nav-subgroup-body">';
    foreach ($item['children'] ?? [] as $child) {
        $renderHrLink($child);
    }
    echo '</div></div>';
};

$renderNavGroup(__('human_resources'), 'fa-users-gear', $hrSectionActive, static function () use ($menu, $renderHrLink, $renderHrSubgroup): void {
    foreach ($menu as $item) {
        if (!empty($item['children'])) {
            $renderHrSubgroup($item);
        } else {
            $renderHrLink($item);
        }
    }
});
