<?php
/**
 * Enterprise JSON-LD for public marketing pages (RATEB).
 */
declare(strict_types=1);

require_once __DIR__ . '/rateb-public-cms.php';
require_once __DIR__ . '/rateb-enterprise-terminology.php';

if (!function_exists('rateb_enterprise_schema_organization')) {
    /**
     * @return array<string, mixed>
     */
    function rateb_enterprise_schema_organization(string $baseUrl): array
    {
        $url = rtrim($baseUrl, '/') . '/';

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => 'RATEB',
            'alternateName' => 'Recruitment Automation & Telemetry Enterprise Base',
            'url' => $url,
            'description' => rateb_enterprise_brand_meta_description(),
            'email' => 'info@rateb.sa',
            'areaServed' => 'Worldwide',
            'knowsAbout' => [
                'Enterprise Workforce Program Infrastructure',
                'Workforce tracking',
                'Recruitment Orchestration',
                'Multi-tenant operations platform',
            ],
        ];
    }
}

if (!function_exists('rateb_enterprise_schema_software_application')) {
    /**
     * @return array<string, mixed>
     */
    function rateb_enterprise_schema_software_application(string $baseUrl): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'SoftwareApplication',
            'name' => 'RATEB Platform',
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem' => 'Web',
            'description' => rateb_brand_category() . ' — ' . rateb_brand_expansion(),
            'url' => rtrim($baseUrl, '/') . '/',
            'provider' => [
                '@type' => 'Organization',
                'name' => 'RATEB',
            ],
            'featureList' => [
                'Dashboard',
                'Tracking',
                'GPS Tracking',
                'Reports',
                'Notifications',
                'System Settings',
                'Immutable workflow history',
                'RBAC and tenant isolation',
            ],
        ];
    }
}

if (!function_exists('rateb_enterprise_schema_breadcrumb')) {
    /**
     * @param list<array{0:string,1:string}> $crumbs name, url
     *
     * @return array<string, mixed>
     */
    function rateb_enterprise_schema_breadcrumb(array $crumbs): array
    {
        $items = [];
        foreach ($crumbs as $i => $c) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $c[0],
                'item' => $c[1],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }
}

if (!function_exists('rateb_enterprise_schema_emit')) {
    /**
     * @param list<array<string, mixed>> $graphs
     */
    function rateb_enterprise_schema_emit(array $graphs): void
    {
        if ($graphs === []) {
            return;
        }
        $payload = count($graphs) === 1 ? $graphs[0] : ['@context' => 'https://schema.org', '@graph' => $graphs];
        echo '<script type="application/ld+json">';
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo '</script>' . "\n";
    }
}
