<?php
declare(strict_types=1);

if (!function_exists('control_client_platform_public_root')) {
    function control_client_platform_public_root(): string
    {
        if (!function_exists('control_ratib_pro_public_base_url')) {
            require_once __DIR__ . '/request-url.php';
        }

        $root = function_exists('control_ratib_pro_public_base_url')
            ? rtrim((string) control_ratib_pro_public_base_url(), '/')
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

if (!function_exists('control_client_platform_links')) {
    function control_client_platform_wrapper_url(string $section, string $extraQuery = ''): string
    {
        $section = strtolower(trim($section));
        $map = [
            'hub' => 'dashboard',
            'dashboard' => 'dashboard',
            'services' => 'services',
            'domains' => 'domains',
            'orders' => 'orders',
            'billing' => 'billing',
            'security' => 'security',
            'support' => 'support',
            'notifications' => 'notifications',
            'subscriptions' => 'subscriptions',
            'settings' => 'settings',
        ];
        $resolvedSection = $map[$section] ?? 'dashboard';

        $baseUrl = function_exists('control_panel_page_with_control')
            ? control_panel_page_with_control('control/client-platform.php')
            : (pageUrl('control/client-platform.php') . '?control=1');

        $query = ['section' => $resolvedSection];
        $agencyId = (int) ($_GET['agency_id'] ?? ($_SESSION['control_agency_id'] ?? 0));
        if ($agencyId > 0) {
            $query['agency_id'] = (string) $agencyId;
        }

        $extraQuery = ltrim(trim($extraQuery), '?&');
        if ($extraQuery !== '') {
            $extra = [];
            parse_str($extraQuery, $extra);
            foreach ($extra as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                $query[(string) $key] = $value;
            }
        }

        return $baseUrl . '&' . http_build_query($query);
    }

    /**
     * @return array<string,array{label:string,href:string}>
     */
    function control_client_platform_links(): array
    {
        return [
            'hub' => [
                'label' => 'Client Hub',
                'href' => control_client_platform_wrapper_url('dashboard'),
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

if (!function_exists('control_render_client_platform_tabs')) {
    function control_render_client_platform_tabs(): string
    {
        $links = control_client_platform_links();

        ob_start();
        ?>
        <nav class="client-platform-tabs" aria-label="Client Platform links" data-permission="control_dashboard">
            <span class="client-platform-tabs__label">
                <i class="fas fa-table-cells-large" aria-hidden="true"></i>
                <span>Client Platform</span>
            </span>
            <?php foreach ($links as $item): ?>
                <a class="client-platform-tabs__link" href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
        return (string) ob_get_clean();
    }
}
