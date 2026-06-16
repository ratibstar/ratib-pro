<?php
declare(strict_types=1);

if (!function_exists('control_client_platform_public_root')) {
    function control_client_platform_public_root(): string
    {
        if (!function_exists('control_rateb_pro_public_base_url')) {
            require_once __DIR__ . '/request-url.php';
        }

        $root = function_exists('control_rateb_pro_public_base_url')
            ? rtrim((string) control_rateb_pro_public_base_url(), '/')
            : '';

        if ($root === '' && defined('SITE_URL') && (string) SITE_URL !== '') {
            $root = rtrim((string) SITE_URL, '/');
        }

        if ($root === '' && isset($_SERVER['HTTP_HOST'])) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $root = $scheme . '://' . $_SERVER['HTTP_HOST'];
        }

        return rtrim($root, '/');
    }
}

if (!function_exists('control_panel_resolved_base_path')) {
    function control_panel_resolved_base_path(): string
    {
        if (function_exists('getBaseUrl')) {
            $base = rtrim((string) getBaseUrl(), '/');
            if ($base !== '' && $base !== '/') {
                return $base;
            }
        }
        if (!empty($_SERVER['SCRIPT_NAME']) && strpos((string) $_SERVER['SCRIPT_NAME'], '/control-panel/') !== false) {
            return '/control-panel';
        }

        return '/control-panel';
    }
}

if (!function_exists('control_panel_client_wrapper_href')) {
    function control_panel_client_wrapper_href(string $pagePath, array $query = []): string
    {
        $page = function_exists('rateb_clean_page_segment')
            ? rateb_clean_page_segment($pagePath)
            : ltrim(str_replace('\\', '/', $pagePath), '/');
        $query['control'] = '1';

        return control_panel_resolved_base_path() . '/pages/' . $page . '?' . http_build_query($query);
    }
}

if (!function_exists('control_client_platform_wrapper_url')) {
    function control_client_platform_wrapper_url(string $section, string $extraQuery = ''): string
    {
        $section = strtolower(trim($section));
        $map = [
            'hub' => 'control/client-hub.php',
            'dashboard' => 'control/client-hub.php',
            'services' => 'control/client-services.php',
            'domains' => 'control/client-domains.php',
            'orders' => 'control/client-orders.php',
            'billing' => 'control/client-billing.php',
            'security' => 'control/client-security.php',
            'support' => 'control/client-support.php',
            'notifications' => 'control/client-notifications.php',
            'subscriptions' => 'control/client-subscriptions.php',
            'settings' => 'control/client-settings.php',
        ];
        $targetPath = $map[$section] ?? 'control/client-hub.php';

        $query = [];
        $agencyId = (int) ($_GET['agency_id'] ?? ($_SESSION['control_agency_id'] ?? 0));
        if ($agencyId > 0) {
            $query['agency_id'] = (string) $agencyId;
        }

        $extraQuery = ltrim(trim($extraQuery), '?&');
        if ($extraQuery !== '') {
            $parsedExtra = [];
            parse_str($extraQuery, $parsedExtra);
            foreach ($parsedExtra as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $query[(string) $key] = $value;
            }
        }

        return control_panel_client_wrapper_href($targetPath, $query);
    }
}

if (!function_exists('control_client_platform_links')) {
    /**
     * @return array<string,array{label:string,href:string}>
     */
    function control_client_platform_links(): array
    {
        return [
            'hub' => [
                'label' => 'Client Hub',
                'href' => control_client_platform_wrapper_url('hub'),
            ],
            'services' => [
                'label' => 'Services',
                'href' => control_client_platform_wrapper_url('services'),
            ],
            'domains' => [
                'label' => 'Domains',
                'href' => control_client_platform_wrapper_url('domains', 'catalog=1'),
            ],
            'orders' => [
                'label' => 'Orders',
                'href' => control_client_platform_wrapper_url('orders'),
            ],
            'billing' => [
                'label' => 'Billing',
                'href' => control_client_platform_wrapper_url('billing'),
            ],
        ];
    }
}

if (!function_exists('control_client_platform_active_key')) {
    /**
     * Resolve the active Client Platform section from the current control-panel wrapper script.
     */
    function control_client_platform_active_key(): ?string
    {
        $script = strtolower(basename((string) ($_SERVER['PHP_SELF'] ?? ''), '.php'));
        $map = [
            'client-hub' => 'hub',
            'client-services' => 'services',
            'client-domains' => 'domains',
            'client-orders' => 'orders',
            'client-billing' => 'billing',
            'client-security' => 'security',
            'client-support' => 'support',
            'client-notifications' => 'notifications',
            'client-subscriptions' => 'subscriptions',
            'client-settings' => 'settings',
        ];

        return $map[$script] ?? null;
    }
}

if (!function_exists('control_client_platform_section_to_key')) {
    function control_client_platform_section_to_key(string $section): ?string
    {
        $section = strtolower(trim($section));
        $map = [
            'home' => 'hub',
            'dashboard' => 'hub',
            'hub' => 'hub',
            'services' => 'services',
            'domains' => 'domains',
            'orders' => 'orders',
            'billing' => 'billing',
            'security' => 'security',
            'support' => 'support',
            'notifications' => 'notifications',
            'subs' => 'subscriptions',
            'subscriptions' => 'subscriptions',
            'settings' => 'settings',
        ];

        return $map[$section] ?? null;
    }
}

if (!function_exists('control_render_client_platform_tabs')) {
    function control_render_client_platform_tabs(?string $activeKey = null): string
    {
        if ($activeKey === null || $activeKey === '') {
            $activeKey = control_client_platform_active_key();
        }

        $links = control_client_platform_links();

        ob_start();
        ?>
        <nav class="client-platform-tabs" aria-label="Client Platform links" data-permission="control_dashboard">
            <span class="client-platform-tabs__label">
                <i class="fas fa-table-cells-large" aria-hidden="true"></i>
                <span>Client Platform</span>
            </span>
            <?php foreach ($links as $key => $item): ?>
                <?php
                $isActive = ($activeKey !== null && $activeKey === $key);
                $linkClass = 'client-platform-tabs__link' . ($isActive ? ' is-active' : '');
                ?>
                <a class="<?php echo htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $isActive ? ' aria-current="page"' : ''; ?>>
                    <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
        return (string) ob_get_clean();
    }
}
