<?php
/**
 * Client Dashboard Platform — modular bootstrap (paths + access helpers).
 */
declare(strict_types=1);

if (!defined('RATIB_CLIENT_DASHBOARD_ROOT')) {
    define('RATIB_CLIENT_DASHBOARD_ROOT', __DIR__);
}

if (!function_exists('ratib_client_dashboard_asset_url')) {
    function ratib_client_dashboard_asset_url(string $relativeWithinAssets): string
    {
        $relativeWithinAssets = ltrim($relativeWithinAssets, '/');
        $disk = RATIB_CLIENT_DASHBOARD_ROOT . '/Assets/' . $relativeWithinAssets;
        $mtime = @filemtime($disk);
        $v = ($mtime !== false) ? $mtime : time();
        $base = asset('modules/client-dashboard/Assets/' . $relativeWithinAssets);
        return $base . '?v=' . (int) $v;
    }
}

if (!function_exists('ratib_client_dashboard_page_url')) {
    function ratib_client_dashboard_page_url(string $page): string
    {
        return pageUrl('client/' . ltrim($page, '/'));
    }
}

if (!function_exists('ratib_client_dashboard_can_access')) {
    function ratib_client_dashboard_can_access(): bool
    {
        if (!function_exists('ratib_program_session_is_valid_user') || !ratib_program_session_is_valid_user()) {
            return false;
        }
        if (!function_exists('hasPermission')) {
            return false;
        }
        if (hasPermission('view_client_dashboard')) {
            return true;
        }
        return hasPermission('view_dashboard');
    }
}

if (!function_exists('ratib_client_dashboard_require_access')) {
    function ratib_client_dashboard_require_access(): void
    {
        if (ratib_client_dashboard_can_access()) {
            return;
        }
        if (!function_exists('ratib_program_session_is_valid_user') || !ratib_program_session_is_valid_user()) {
            $qs = [];
            if (!empty($_GET['country_slug'])) {
                $qs[] = 'country_slug=' . rawurlencode((string) $_GET['country_slug']);
            }
            if (isset($_GET['control'], $_GET['agency_id']) && (string) $_GET['control'] === '1' && ctype_digit((string) $_GET['agency_id'])) {
                $qs[] = 'control=1';
                $qs[] = 'agency_id=' . (int) $_GET['agency_id'];
            }
            $suffix = !empty($qs) ? ('?' . implode('&', $qs)) : '';
            header('Location: ' . pageUrl('login.php') . $suffix);
            exit;
        }
        header('Location: ' . pageUrl('profile.php'));
        exit;
    }
}

if (!function_exists('ratib_client_dashboard_api_deny')) {
    function ratib_client_dashboard_api_deny(): void
    {
        if (!headers_sent()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo '{"ok":false,"message":"unauthorized"}';
        exit;
    }
}

if (!function_exists('ratib_client_dashboard_api_require_access')) {
    function ratib_client_dashboard_api_require_access(): void
    {
        if (!ratib_client_dashboard_can_access()) {
            ratib_client_dashboard_api_deny();
        }
    }
}

if (!function_exists('ratib_client_dashboard_marketplace_href')) {
    function ratib_client_dashboard_marketplace_href(): string
    {
        return ratib_nav_url('client/domains.php', 'catalog=1');
    }
}

if (!function_exists('ratib_client_dashboard_nav_sections')) {
    /**
     * @return list<array{key:string,label:string,icon:string,href:string,children?:array<int,array{key:string,label:string,href:string}>}>
     */
    function ratib_client_dashboard_nav_sections(): array
    {
        $u = static function (string $p): string {
            return htmlspecialchars(ratib_nav_url('client/' . $p), ENT_QUOTES, 'UTF-8');
        };

        return [
            [
                'key' => 'home',
                'label' => 'Dashboard',
                'icon' => 'fa-gauge-high',
                'href' => $u('dashboard.php'),
            ],
            [
                'key' => 'orders',
                'label' => 'Orders',
                'icon' => 'fa-bag-shopping',
                'href' => $u('orders.php'),
            ],
            [
                'key' => 'services',
                'label' => 'Services',
                'icon' => 'fa-server',
                'href' => $u('services.php'),
            ],
            [
                'key' => 'domains',
                'label' => 'Domains',
                'icon' => 'fa-globe',
                'href' => $u('domains.php'),
            ],
            [
                'key' => 'billing',
                'label' => 'Billing',
                'icon' => 'fa-file-invoice-dollar',
                'href' => $u('billing.php'),
            ],
            [
                'key' => 'security',
                'label' => 'Security',
                'icon' => 'fa-shield-halved',
                'href' => $u('security.php'),
            ],
            [
                'key' => 'support',
                'label' => 'Support',
                'icon' => 'fa-life-ring',
                'href' => $u('support.php'),
            ],
            [
                'key' => 'notifications',
                'label' => 'Notifications',
                'icon' => 'fa-bell',
                'href' => $u('notifications-center.php'),
            ],
            [
                'key' => 'subs',
                'label' => 'Plans',
                'icon' => 'fa-rectangle-list',
                'href' => $u('subscriptions.php'),
            ],
            [
                'key' => 'settings',
                'label' => 'Account & Team',
                'icon' => 'fa-user-gear',
                'href' => $u('settings.php'),
            ],
        ];
    }
}
