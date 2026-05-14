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
    /**
     * @return array<string,array{label:string,href:string}>
     */
    function control_client_platform_links(): array
    {
        $root = control_client_platform_public_root();
        $agencyId = (int) ($_GET['agency_id'] ?? ($_SESSION['control_agency_id'] ?? 0));
        $query = ['control' => '1'];
        if ($agencyId > 0) {
            $query['agency_id'] = (string) $agencyId;
        }
        $qs = http_build_query($query);

        return [
            'hub' => [
                'label' => 'Client Hub',
                'href' => $root . '/pages/client/dashboard.php?' . $qs,
            ],
            'services' => [
                'label' => 'Services',
                'href' => $root . '/pages/client/services.php?' . $qs,
            ],
            'domains' => [
                'label' => 'Domains',
                'href' => $root . '/pages/client/domains.php?' . $qs,
            ],
            'orders' => [
                'label' => 'Orders',
                'href' => $root . '/pages/client/orders.php?' . $qs,
            ],
            'billing' => [
                'label' => 'Billing',
                'href' => $root . '/pages/client/billing.php?' . $qs,
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
