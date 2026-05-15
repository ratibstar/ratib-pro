<?php
/**
 * Client Dashboard Platform — modular bootstrap (paths + access helpers).
 */
declare(strict_types=1);

if (!defined('RATIB_CLIENT_DASHBOARD_ROOT')) {
    define('RATIB_CLIENT_DASHBOARD_ROOT', __DIR__);
}

if (!function_exists('ratib_client_dashboard_page_url')) {
    function ratib_client_dashboard_page_url(string $page): string
    {
        return pageUrl('client/' . ltrim($page, '/'));
    }
}

if (!function_exists('ratib_client_dashboard_is_control_wrapper_active')) {
    function ratib_client_dashboard_is_control_wrapper_active(): bool
    {
        return defined('RATIB_CLIENT_CONTROL_WRAPPER_ACTIVE') && RATIB_CLIENT_CONTROL_WRAPPER_ACTIVE;
    }
}

if (!function_exists('ratib_client_dashboard_public_site_base_url')) {
    /**
     * Prefix for URLs to site-root `/modules/...` and other assets.
     *
     * When Client Hub renders inside `/control-panel/...`, BASE_URL/`getBaseUrl()` often point at the
     * control subpath — those requests 404 because static/module files remain at the public app root.
     */
    function ratib_client_dashboard_public_site_base_url(): string
    {
        if (!ratib_client_dashboard_is_control_wrapper_active()) {
            return rtrim((string) getBaseUrl(), '/');
        }

        if (function_exists('control_ratib_pro_public_base_url')) {
            $pub = rtrim((string) control_ratib_pro_public_base_url(), '/');
            if ($pub !== '') {
                return $pub;
            }
        }

        if (defined('SITE_URL') && SITE_URL !== '') {
            return rtrim((string) SITE_URL, '/');
        }

        return rtrim((string) getBaseUrl(), '/');
    }
}

if (!function_exists('ratib_client_dashboard_asset_url')) {
    function ratib_client_dashboard_asset_url(string $relativeWithinAssets): string
    {
        $relativeWithinAssets = ltrim($relativeWithinAssets, '/');
        $disk = RATIB_CLIENT_DASHBOARD_ROOT . '/Assets/' . $relativeWithinAssets;
        $mtime = @filemtime($disk);
        $v = ($mtime !== false) ? $mtime : time();
        $root = ratib_client_dashboard_public_site_base_url();
        return $root . '/modules/client-dashboard/Assets/' . $relativeWithinAssets . '?v=' . (int) $v;
    }
}

if (!function_exists('ratib_client_dashboard_has_control_context')) {
    function ratib_client_dashboard_has_control_context(): bool
    {
        if (empty($_SESSION['control_logged_in'])) {
            return false;
        }

        $agencyId = 0;
        if (isset($_GET['agency_id']) && ctype_digit((string) $_GET['agency_id'])) {
            $agencyId = (int) $_GET['agency_id'];
        }
        if ($agencyId <= 0) {
            $agencyId = (int) ($_SESSION['control_agency_id'] ?? 0);
        }

        return $agencyId > 0;
    }
}

if (!function_exists('ratib_client_dashboard_context_url')) {
    function ratib_client_dashboard_context_url(string $page, string $extraQuery = ''): string
    {
        $page = ltrim($page, '/');
        if (!ratib_client_dashboard_is_control_wrapper_active()) {
            return ratib_nav_url('client/' . $page, $extraQuery);
        }

        $map = [
            'dashboard.php' => 'hub',
            'orders.php' => 'orders',
            'services.php' => 'services',
            'domains.php' => 'domains',
            'billing.php' => 'billing',
            'security.php' => 'security',
            'support.php' => 'support',
            'notifications-center.php' => 'notifications',
            'subscriptions.php' => 'subscriptions',
            'settings.php' => 'settings',
        ];
        $section = $map[$page] ?? 'hub';

        $controlNavFile = dirname(__DIR__, 2) . '/control-panel/includes/control/client-platform-nav.php';
        if (is_file($controlNavFile) && !function_exists('control_client_platform_wrapper_url')) {
            require_once $controlNavFile;
        }

        if (function_exists('control_client_platform_wrapper_url')) {
            return control_client_platform_wrapper_url($section, $extraQuery);
        }

        return ratib_nav_url('client/' . $page, $extraQuery);
    }
}

if (!function_exists('ratib_client_dashboard_can_access')) {
    function ratib_client_dashboard_can_access(): bool
    {
        if (ratib_client_dashboard_has_control_context()) {
            return true;
        }
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
        return ratib_client_dashboard_context_url('domains.php', 'catalog=1');
    }
}

if (!function_exists('ratib_client_dashboard_nav_sections')) {
    /**
     * @return list<array{key:string,label:string,icon:string,href:string,children?:array<int,array{key:string,label:string,href:string}>}>
     */
    function ratib_client_dashboard_nav_sections(): array
    {
        $u = static function (string $p): string {
            return htmlspecialchars(ratib_client_dashboard_context_url($p), ENT_QUOTES, 'UTF-8');
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
