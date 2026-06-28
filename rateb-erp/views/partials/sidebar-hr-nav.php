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
    if ($resourcePath === 'hr/reports') {
        return $erpRoute === $route;
    }
    if ($resourcePath === 'hr/reports/leaves') {
        return $erpRoute === rateb_app_route('hr/reports/leaves');
    }
    if ($resourcePath === 'hr/leaves/balances') {
        return $erpRoute === rateb_app_route('hr/leaves/balances');
    }
    if ($resourcePath === 'hr/leaves') {
        $leaveTypes = rateb_app_route('hr/leave-types');
        $balances = rateb_app_route('hr/leaves/balances');
        if ($erpRoute === $balances || str_starts_with($erpRoute, $balances . '/')) {
            return false;
        }
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
    $badge = function_exists('rateb_ops_nav_pending_badge') ? rateb_ops_nav_pending_badge($route) : 0;
    echo '<a href="' . Rateb\App\Core\View::escape($url) . '" class="rateb-nav-link' . $active . '">';
    echo '<i class="fas ' . Rateb\App\Core\View::escape((string) ($item['icon'] ?? 'fa-circle')) . '"></i>';
    echo '<span>' . Rateb\App\Core\View::escape(__((string) ($item['label'] ?? ''))) . '</span>';
    if ($badge > 0) {
        echo '<span class="rateb-nav-badge rateb-nav-badge--pending" title="' . Rateb\App\Core\View::escape(__('approvals_oversight')) . '">' . $badge . '</span>';
    }
    echo '</a>';
};

$renderHrSubgroup = static function (array $item) use ($hrRouteActive, $renderHrLink): void {
    $open = false;
    $subBadge = 0;
    foreach ($item['children'] ?? [] as $child) {
        if ($hrRouteActive((string) ($child['route'] ?? ''))) {
            $open = true;
        }
        if (function_exists('rateb_ops_nav_pending_badge')) {
            $subBadge += rateb_ops_nav_pending_badge((string) ($child['route'] ?? ''));
        }
    }
    $openClass = $open ? ' is-open' : '';
    echo '<div class="rateb-nav-subgroup' . $openClass . '" data-nav-group>';
    echo '<button type="button" class="rateb-nav-subgroup-toggle" data-nav-group-toggle aria-expanded="' . ($open ? 'true' : 'false') . '">';
    echo '<i class="fas ' . Rateb\App\Core\View::escape((string) ($item['icon'] ?? 'fa-folder')) . '"></i>';
    echo '<span>' . Rateb\App\Core\View::escape(__((string) ($item['label'] ?? ''))) . '</span>';
    if ($subBadge > 0) {
        echo '<span class="rateb-nav-badge rateb-nav-badge--pending" title="' . Rateb\App\Core\View::escape(__('approvals_oversight')) . '">' . $subBadge . '</span>';
    }
    echo '<i class="fas fa-chevron-down rateb-nav-subgroup-chevron" aria-hidden="true"></i>';
    echo '</button><div class="rateb-nav-subgroup-body">';
    foreach ($item['children'] ?? [] as $child) {
        $renderHrLink($child);
    }
    echo '</div></div>';
};

$hrSectionBadge = 0;
foreach ($menu as $hrItem) {
    if (!empty($hrItem['children'])) {
        foreach ($hrItem['children'] as $child) {
            if (function_exists('rateb_ops_nav_pending_badge')) {
                $hrSectionBadge += rateb_ops_nav_pending_badge((string) ($child['route'] ?? ''));
            }
        }
    } elseif (function_exists('rateb_ops_nav_pending_badge')) {
        $hrSectionBadge += rateb_ops_nav_pending_badge((string) ($hrItem['route'] ?? ''));
    }
}

$renderNavGroup(__('human_resources'), 'fa-users-gear', $hrSectionActive, static function () use ($menu, $renderHrLink, $renderHrSubgroup): void {
    foreach ($menu as $item) {
        if (!empty($item['children'])) {
            $renderHrSubgroup($item);
        } else {
            $renderHrLink($item);
        }
    }
}, $hrSectionBadge, 'rateb-nav-badge--pending');
