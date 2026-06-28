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
    $badge = function_exists('rateb_ops_nav_pending_badge') ? rateb_ops_nav_pending_badge($resourcePath) : 0;
    echo '<a href="' . rateb_url($route) . '" class="rateb-nav-link' . $active . '">';
    echo '<i class="fas ' . $icon . '"></i><span>' . __($labelKey) . '</span>';
    if ($badge > 0) {
        echo '<span class="rateb-nav-badge rateb-nav-badge--pending" title="' . Rateb\App\Core\View::escape(__('approvals_oversight')) . '">' . $badge . '</span>';
    }
    echo '</a>';
};

$renderNavGroup = static function (
    string $title,
    string $groupIcon,
    bool $hasActive,
    callable $renderBody,
    int $sectionBadge = 0,
    string $badgeClass = ''
): void {
    $openClass = $hasActive ? ' is-open' : '';
    echo '<div class="rateb-nav-group' . $openClass . '" data-nav-group>';
    echo '<button type="button" class="rateb-nav-group-toggle" aria-expanded="' . ($hasActive ? 'true' : 'false') . '" data-nav-group-toggle>';
    echo '<i class="fas ' . Rateb\App\Core\View::escape($groupIcon) . ' rateb-nav-group-icon"></i>';
    echo '<span class="rateb-nav-group-label">' . Rateb\App\Core\View::escape($title) . '</span>';
    if ($sectionBadge > 0) {
        $cls = 'rateb-nav-badge rateb-nav-group-badge';
        if ($badgeClass !== '') {
            $cls .= ' ' . Rateb\App\Core\View::escape($badgeClass);
        }
        echo '<span class="' . $cls . '" title="' . Rateb\App\Core\View::escape(__('approvals_oversight')) . '">' . $sectionBadge . '</span>';
    }
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
    $sectionBadge = 0;
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
        if (function_exists('rateb_ops_nav_pending_badge')) {
            $sectionBadge += rateb_ops_nav_pending_badge($path);
        }
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
    }, $sectionBadge, 'rateb-nav-badge--pending');
};

$adminSection = static function (
    string $title,
    array $items,
    string $groupIcon = 'fa-folder-open',
    int $sectionBadge = 0,
    array $linkBadges = [],
    string $badgeClass = ''
) use ($navActive, $renderNavGroup): void {
    $renderAdminLink = static function (array $link) use ($navActive, $linkBadges, $badgeClass): void {
        $active = $navActive($link[0]) ? ' active' : '';
        $badge = (int) ($linkBadges[$link[0]] ?? 0);
        echo '<a href="' . rateb_url($link[0]) . '" class="rateb-nav-link' . $active . '">';
        echo '<i class="fas ' . $link[2] . '"></i><span>' . __($link[1]) . '</span>';
        if ($badge > 0) {
            $cls = 'rateb-nav-badge';
            if ($badgeClass !== '') {
                $cls .= ' ' . $badgeClass;
            }
            echo '<span class="' . Rateb\App\Core\View::escape($cls) . '" title="' . Rateb\App\Core\View::escape(__('approvals_oversight')) . '">' . $badge . '</span>';
        }
        echo '</a>';
    };

    $renderAdminSubGroup = static function (array $subGroup) use ($navActive, $renderAdminLink): void {
        $subOpen = false;
        foreach ($subGroup['links'] as $link) {
            if ($navActive($link[0])) {
                $subOpen = true;
                break;
            }
        }
        $openClass = $subOpen ? ' is-open' : '';
        echo '<div class="rateb-nav-subgroup' . $openClass . '" data-nav-group>';
        echo '<button type="button" class="rateb-nav-subgroup-toggle" data-nav-group-toggle aria-expanded="' . ($subOpen ? 'true' : 'false') . '">';
        echo '<i class="fas ' . Rateb\App\Core\View::escape($subGroup['icon']) . '"></i>';
        echo '<span>' . Rateb\App\Core\View::escape($subGroup['label']) . '</span>';
        echo '<i class="fas fa-chevron-down rateb-nav-subgroup-chevron" aria-hidden="true"></i>';
        echo '</button><div class="rateb-nav-subgroup-body">';
        foreach ($subGroup['links'] as $link) {
            $renderAdminLink($link);
        }
        echo '</div></div>';
    };

    $visibleItems = [];
    $hasActive = false;
    foreach ($items as $item) {
        if (($item['type'] ?? 'link') === 'subgroup') {
            $subLinks = [];
            foreach ($item['links'] ?? [] as $link) {
                $module = $link[4] ?? '';
                if (!rateb_nav_can($link[3], $module)) {
                    continue;
                }
                $subLinks[] = $link;
                if ($navActive($link[0])) {
                    $hasActive = true;
                }
            }
            if ($subLinks === []) {
                continue;
            }
            $visibleItems[] = [
                'type' => 'subgroup',
                'label' => (string) ($item['label'] ?? ''),
                'icon' => (string) ($item['icon'] ?? 'fa-folder'),
                'links' => $subLinks,
            ];
            continue;
        }
        $link = $item['link'] ?? $item;
        $module = $link[4] ?? '';
        if (!rateb_nav_can($link[3], $module)) {
            continue;
        }
        if ($navActive($link[0])) {
            $hasActive = true;
        }
        $visibleItems[] = ['type' => 'link', 'link' => $link];
    }

    if ($visibleItems === []) {
        return;
    }

    $renderNavGroup($title, $groupIcon, $hasActive, static function () use ($visibleItems, $renderAdminLink, $renderAdminSubGroup): void {
        foreach ($visibleItems as $item) {
            if ($item['type'] === 'subgroup') {
                $renderAdminSubGroup($item);
                continue;
            }
            $renderAdminLink($item['link']);
        }
    }, $sectionBadge, $badgeClass);
};
