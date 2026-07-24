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
    $href = rateb_app_url($resourcePath);
    // Selling register/biometric only — full document load (pos-shell).
    // Other POS admin pages use Admin main layout and soft-nav like Inventory/HR.
    $isPosFullNav = $resourcePath === 'pos'
        || $resourcePath === 'pos/register'
        || str_starts_with($resourcePath, 'pos/register/')
        || $resourcePath === 'pos/biometric'
        || str_starts_with($resourcePath, 'pos/biometric/');
    if ($isPosFullNav) {
        echo '<a href="' . $href . '" data-rateb-href="' . $href . '" data-rateb-full-nav="1" class="rateb-nav-link' . $active . '">';
    } else {
        echo '<a href="' . $href . '" data-rateb-href="' . $href . '" class="rateb-nav-link' . $active . '" onclick="return false;">';
    }
    echo '<i class="fas ' . $icon . '"></i><span>' . __($labelKey) . '</span>';
    if ($badge > 0) {
        echo '<span class="rateb-nav-badge rateb-nav-badge--pending" title="' . Rateb\App\Core\View::escape(__('ops_nav_pending_hint')) . '">' . $badge . '</span>';
    }
    echo '</a>';
};

$renderNavGroup = static function (
    string $title,
    string $groupIcon,
    bool $hasActive,
    callable $renderBody,
    int $sectionBadge = 0,
    string $badgeClass = '',
    string $badgeTitleKey = 'approvals_oversight'
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
        echo '<span class="' . $cls . '" title="' . Rateb\App\Core\View::escape(__($badgeTitleKey)) . '">' . $sectionBadge . '</span>';
    }
    echo '<i class="fas fa-chevron-down rateb-nav-group-chevron" aria-hidden="true"></i>';
    echo '</button>';
    echo '<div class="rateb-nav-group-body">';
    /* PERF-P3: collapsed groups keep markup in <template> until first open (smaller first DOM). */
    if ($hasActive) {
        $renderBody();
    } else {
        echo '<template data-rateb-nav-lazy>';
        $renderBody();
        echo '</template>';
    }
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
    }, $sectionBadge, 'rateb-nav-badge--pending', 'ops_nav_pending_hint');
};

$adminSection = static function (
    string $title,
    array $items,
    string $groupIcon = 'fa-folder-open',
    int $sectionBadge = 0,
    array $linkBadges = [],
    string $badgeClass = '',
    string $linkBadgeClass = '',
    string $badgeTitleKey = 'approvals_oversight'
) use ($navActive, $renderNavGroup): void {
    $renderAdminLink = static function (array $link) use ($navActive, $linkBadges, $badgeClass, $linkBadgeClass, $badgeTitleKey): void {
        $active = $navActive($link[0]) ? ' active' : '';
        $badge = (int) ($linkBadges[$link[0]] ?? 0);
        $linkClass = $linkBadgeClass !== '' ? $linkBadgeClass : $badgeClass;
        echo '<a href="' . rateb_url($link[0]) . '" data-rateb-href="' . rateb_url($link[0]) . '" class="rateb-nav-link' . $active . '" onclick="return false;">';
        echo '<i class="fas ' . $link[2] . '"></i><span>' . __($link[1]) . '</span>';
        if ($badge > 0) {
            $cls = 'rateb-nav-badge';
            if ($linkClass !== '') {
                $cls .= ' ' . $linkClass;
            }
            echo '<span class="' . Rateb\App\Core\View::escape($cls) . '" title="' . Rateb\App\Core\View::escape(__($badgeTitleKey)) . '">' . $badge . '</span>';
        }
        echo '</a>';
    };

    $renderAdminSubGroup = static function (array $subGroup) use ($navActive, $renderAdminLink, $linkBadges): void {
        $subOpen = false;
        $subBadge = 0;
        foreach ($subGroup['links'] as $link) {
            if ($navActive($link[0])) {
                $subOpen = true;
            }
            $subBadge += (int) ($linkBadges[$link[0]] ?? 0);
        }
        $openClass = $subOpen ? ' is-open' : '';
        echo '<div class="rateb-nav-subgroup' . $openClass . '" data-nav-group>';
        echo '<button type="button" class="rateb-nav-subgroup-toggle" data-nav-group-toggle aria-expanded="' . ($subOpen ? 'true' : 'false') . '">';
        echo '<i class="fas ' . Rateb\App\Core\View::escape($subGroup['icon']) . '"></i>';
        echo '<span>' . Rateb\App\Core\View::escape($subGroup['label']) . '</span>';
        if ($subBadge > 0) {
            echo '<span class="rateb-nav-badge rateb-nav-badge--pending" title="' . Rateb\App\Core\View::escape(__('ops_nav_pending_hint')) . '">' . $subBadge . '</span>';
        }
        echo '<i class="fas fa-chevron-down rateb-nav-subgroup-chevron" aria-hidden="true"></i>';
        echo '</button><div class="rateb-nav-subgroup-body">';
        if ($subOpen) {
            foreach ($subGroup['links'] as $link) {
                $renderAdminLink($link);
            }
        } else {
            echo '<template data-rateb-nav-lazy>';
            foreach ($subGroup['links'] as $link) {
                $renderAdminLink($link);
            }
            echo '</template>';
        }
        echo '</div></div>';
    };

    $visibleItems = [];
    $hasActive = false;
    foreach ($items as $item) {
        if (($item['type'] ?? 'link') === 'subgroup') {
            $gate = $item['gate'] ?? null;
            if (is_array($gate) && count($gate) >= 1) {
                $gatePerm = (string) ($gate[0] ?? '');
                $gateModule = (string) ($gate[1] ?? '');
                if ($gatePerm !== '' && !rateb_nav_can($gatePerm, $gateModule)) {
                    continue;
                }
            }
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
    }, $sectionBadge, $badgeClass, $badgeTitleKey);
};
