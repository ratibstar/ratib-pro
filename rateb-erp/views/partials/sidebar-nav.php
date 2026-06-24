<?php
declare(strict_types=1);

/**
 * Unified ERP sidebar — platform oversight for super admin, company operations for tenant users.
 */
$opsLink = static function (
    string $resourcePath,
    string $labelKey,
    string $icon,
    string $module = '',
    string $perm = ''
) use ($navActive): void {
    $entity = rateb_entity_perms($resourcePath);
    $permission = $perm !== '' ? $perm : $entity['view'];
    $module = $module !== '' ? $module : $entity['module'];
    if ($permission === '' && $module === '') {
        if (!rateb_is_super_admin() && (int) ($_SESSION['rateb_company_id'] ?? 0) < 1) {
            return;
        }
    } elseif (!rateb_nav_can($permission, $module)) {
        return;
    }
    $route = rateb_app_route($resourcePath);
    $active = $navActive($route) ? ' active' : '';
    echo '<a href="' . rateb_url($route) . '" class="rateb-nav-link' . $active . '">';
    echo '<i class="fas ' . $icon . '"></i><span>' . __($labelKey) . '</span></a>';
};

$renderNavGroup = static function (
    string $title,
    string $groupIcon,
    bool $hasActive,
    callable $renderBody
): void {
    $openClass = $hasActive ? ' is-open' : '';
    echo '<div class="rateb-nav-group' . $openClass . '" data-nav-group>';
    echo '<button type="button" class="rateb-nav-group-toggle" aria-expanded="' . ($hasActive ? 'true' : 'false') . '" data-nav-group-toggle>';
    echo '<i class="fas ' . Rateb\App\Core\View::escape($groupIcon) . ' rateb-nav-group-icon"></i>';
    echo '<span class="rateb-nav-group-label">' . Rateb\App\Core\View::escape($title) . '</span>';
    echo '<i class="fas fa-chevron-down rateb-nav-group-chevron" aria-hidden="true"></i>';
    echo '</button>';
    echo '<div class="rateb-nav-group-body">';
    $renderBody();
    echo '</div></div>';
};

$opsSection = static function (
    string $title,
    array $links,
    string $groupIcon = 'fa-folder-open'
) use ($opsLink, $navActive, $renderNavGroup): void {
    $hasActive = false;
    $hasVisible = false;
    foreach ($links as $link) {
        [$path, , , $module, $perm] = array_pad($link, 5, '');
        $entity = rateb_entity_perms($path);
        $permission = $perm !== '' ? $perm : $entity['view'];
        $module = $module !== '' ? $module : $entity['module'];
        $can = false;
        if ($permission === '' && $module === '') {
            $can = rateb_is_super_admin() || (int) ($_SESSION['rateb_company_id'] ?? 0) > 0;
        } else {
            $can = rateb_nav_can($permission, $module);
        }
        if (!$can) {
            continue;
        }
        $hasVisible = true;
        if ($navActive(rateb_app_route($path))) {
            $hasActive = true;
        }
    }
    if (!$hasVisible) {
        return;
    }
    $renderNavGroup($title, $groupIcon, $hasActive, static function () use ($links, $opsLink): void {
        foreach ($links as $link) {
            $opsLink($link[0], $link[1], $link[2], $link[3] ?? '', $link[4] ?? '');
        }
    });
};

$adminSection = static function (
    string $title,
    array $links,
    string $groupIcon = 'fa-folder-open'
) use ($navActive, $renderNavGroup): void {
    $visibleLinks = [];
    foreach ($links as $link) {
        if (!rateb_nav_can($link[3])) {
            continue;
        }
        $visibleLinks[] = $link;
    }
    if ($visibleLinks === []) {
        return;
    }
    $hasActive = false;
    foreach ($visibleLinks as $link) {
        if ($navActive($link[0])) {
            $hasActive = true;
            break;
        }
    }
    $renderNavGroup($title, $groupIcon, $hasActive, static function () use ($visibleLinks, $navActive): void {
        foreach ($visibleLinks as $link) {
            $active = $navActive($link[0]) ? ' active' : '';
            $badge = (isset($link[4]) && is_numeric($link[4])) ? (int) $link[4] : 0;
            echo '<a href="' . rateb_url($link[0]) . '" class="rateb-nav-link' . $active . '">';
            echo '<i class="fas ' . $link[2] . '"></i><span>' . __($link[1]) . '</span>';
            if ($badge > 0) {
                echo '<span class="rateb-nav-badge">' . $badge . '</span>';
            }
            echo '</a>';
        }
    });
};
