<?php
declare(strict_types=1);

/**
 * Unified ERP sidebar — platform oversight for super admin, company operations for tenant users.
 */

$addonNavContext = static function (): array {
    static $ctx = null;
    if (is_array($ctx)) {
        return $ctx;
    }
    $ctx = ['on' => false, 'company_id' => 0, 'addons' => null, 'limits' => null];
    if (function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
        return $ctx;
    }
    $flag = \Rateb\App\Services\ModuleAddonService::FLAG_NAME;
    $env = getenv($flag);
    if ($env === false || $env === '') {
        $env = $_ENV[$flag] ?? '';
    }
    if ($env === '' && defined($flag)) {
        $env = (string) constant($flag);
    }
    if (!in_array(strtolower(trim((string) $env)), ['1', 'true', 'yes', 'on'], true)) {
        return $ctx;
    }
    $companyId = function_exists('rateb_nav_tenant_company_id_for_gate')
        ? rateb_nav_tenant_company_id_for_gate()
        : (int) ($_SESSION['rateb_company_id'] ?? 0);
    if ($companyId < 1) {
        return $ctx;
    }
    $ctx['on'] = true;
    $ctx['company_id'] = $companyId;
    $ctx['addons'] = new \Rateb\App\Services\ModuleAddonService();
    $ctx['limits'] = new \Rateb\App\Services\PlanLimitService();

    return $ctx;
};

$isLockedPurchasableModule = static function (string $slug, string $permission) use ($addonNavContext): bool {
    $ctx = $addonNavContext();
    if (!$ctx['on'] || $ctx['company_id'] < 1) {
        return false;
    }
    $slug = strtolower(trim($slug));
    if ($slug === '' || $permission === '' || !rateb_can($permission)) {
        return false;
    }
    // Company-permissions catalog modules: unchecked = hide (never purchase-lock).
    // Demo/addon locks remain a separate board for commercial upsell of add-on-only slugs.
    if (class_exists(\Rateb\App\Services\PlanLimitService::class)) {
        $entitlementCatalog = \Rateb\App\Services\PlanLimitService::moduleCatalog();
        if (is_array($entitlementCatalog) && array_key_exists($slug, $entitlementCatalog)) {
            return false;
        }
    }
    if (!$ctx['addons']->isPurchasable($slug)) {
        return false;
    }

    return !$ctx['limits']->companyHasModule($ctx['company_id'], $slug);
};

$addonLockedHint = static function (): string {
    $locale = function_exists('rateb_locale') ? strtolower((string) rateb_locale()) : '';

    return str_starts_with($locale, 'ar') ? 'متاح للشراء' : 'Available to purchase';
};

$addonLockedRendered = [];

$opsLink = static function (
    string $resourcePath,
    string $labelKey,
    string $icon,
    string $module = '',
    string $perm = '',
    ?string $labelOverride = null
) use ($navActive, $isLockedPurchasableModule, $addonLockedHint, &$addonLockedRendered): void {
    $entity = rateb_entity_perms($resourcePath);
    $permission = $perm !== '' ? $perm : $entity['view'];
    $module = $module !== '' ? $module : $entity['module'];
    $locked = false;
    if ($permission === '' && $module === '') {
        if (!rateb_is_super_admin() && (int) ($_SESSION['rateb_company_id'] ?? 0) < 1) {
            return;
        }
    } elseif (!rateb_nav_can($permission, $module)) {
        if (!$isLockedPurchasableModule($module, $permission)) {
            return;
        }
        $locked = true;
    }
    if ($locked) {
        if (isset($addonLockedRendered[$module])) {
            return;
        }
        $addonLockedRendered[$module] = true;
        $billingRoute = 'admin/billing/modules/' . $module;
        $href = rateb_url($billingRoute);
        $active = $navActive($billingRoute) ? ' active' : '';
        $label = ($labelOverride !== null && $labelOverride !== '') ? $labelOverride : __($labelKey);
        $hint = $addonLockedHint();
        $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        echo '<a href="' . $esc($href) . '" data-rateb-href="' . $esc($href) . '" data-rateb-full-nav="1" class="rateb-nav-link rateb-nav-link--locked' . $active . '" title="' . $esc($hint) . '" aria-label="' . $esc($label . ' — ' . $hint) . '">';
        echo '<i class="fas ' . $icon . '"></i>';
        echo '<span class="rateb-nav-locked-text"><span class="rateb-nav-locked-label">' . $esc($label) . '</span><span class="rateb-nav-locked-hint">' . $esc($hint) . '</span></span>';
        echo '<i class="fas fa-lock" aria-hidden="true"></i>';
        echo '</a>';
        return;
    }
    $route = rateb_app_route($resourcePath);
    $active = $navActive($route) ? ' active' : '';
    $badge = function_exists('rateb_ops_nav_pending_badge') ? rateb_ops_nav_pending_badge($resourcePath) : 0;
    // Selling register/biometric only — hard URL + full document load (pos-shell).
    // Never emit admin/pos/pos (broken) — always admin/ops/pos/register.
    $isPosFullNav = $resourcePath === 'pos'
        || $resourcePath === 'pos/register'
        || str_starts_with($resourcePath, 'pos/register/')
        || $resourcePath === 'pos/biometric'
        || str_starts_with($resourcePath, 'pos/biometric/');
    $href = $isPosFullNav
        ? rateb_url_with_ops_company('admin/ops/pos/register')
        : rateb_app_url($resourcePath);
    if ($isPosFullNav && function_exists('rateb_url_set_query_param')) {
        $href = rateb_url_set_query_param($href, 'rateb_live', '1');
    }
    if ($isPosFullNav) {
        echo '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" data-rateb-href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" data-rateb-full-nav="1" data-pos-open-register="1" class="rateb-nav-link' . $active . '">';
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
    string $groupIcon = 'fa-folder-open',
    bool $eager = false
) use ($opsLink, $navActive, $renderNavGroup, $isLockedPurchasableModule): void {
    $hasActive = false;
    $sectionBadge = 0;
    $unlocked = [];
    $lockedFirst = null;
    foreach ($links as $link) {
        [$path, $labelKey, $icon, $module, $perm] = array_pad($link, 5, '');
        $entity = rateb_entity_perms($path);
        $permission = $perm !== '' ? $perm : $entity['view'];
        $module = $module !== '' ? $module : $entity['module'];
        $can = false;
        if ($permission === '' && $module === '') {
            $can = rateb_is_super_admin() || (int) ($_SESSION['rateb_company_id'] ?? 0) > 0;
        } else {
            $can = rateb_nav_can($permission, $module);
        }
        if ($can) {
            $unlocked[] = $link;
            if (function_exists('rateb_ops_nav_pending_badge')) {
                $sectionBadge += rateb_ops_nav_pending_badge($path);
            }
            if ($navActive(rateb_app_route($path))) {
                $hasActive = true;
            }
            continue;
        }
        if (!$isLockedPurchasableModule($module, $permission)) {
            continue;
        }
        if ($lockedFirst === null) {
            $lockedFirst = [$path, $labelKey, $groupIcon !== '' ? $groupIcon : $icon, $module, $permission];
        }
        if ($navActive('admin/billing/modules/' . $module)) {
            $hasActive = true;
        }
    }
    if ($unlocked === []) {
        if ($lockedFirst === null) {
            return;
        }
        // Lock-only section: one top-level locked link (visible lock, one click → checkout).
        $opsLink($lockedFirst[0], $lockedFirst[1], $lockedFirst[2], $lockedFirst[3], $lockedFirst[4], $title);
        return;
    }
    $renderNavGroup($title, $groupIcon, $hasActive || $eager, static function () use ($links, $opsLink): void {
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
