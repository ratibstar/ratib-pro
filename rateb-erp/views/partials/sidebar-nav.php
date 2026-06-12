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

$opsSection = static function (
    string $title,
    array $links
) use ($opsLink): void {
    $visible = false;
    foreach ($links as $link) {
        [$path, , , $module, $perm] = array_pad($link, 5, '');
        $entity = rateb_entity_perms($path);
        $permission = $perm !== '' ? $perm : $entity['view'];
        $module = $module !== '' ? $module : $entity['module'];
        if ($permission === '' && $module === '') {
            $visible = true;
            break;
        }
        if (rateb_nav_can($permission, $module)) {
            $visible = true;
            break;
        }
    }
    if (!$visible) {
        return;
    }
    echo '<div class="rateb-nav-section">' . Rateb\App\Core\View::escape($title) . '</div>';
    foreach ($links as $link) {
        $opsLink($link[0], $link[1], $link[2], $link[3] ?? '', $link[4] ?? '');
    }
};
