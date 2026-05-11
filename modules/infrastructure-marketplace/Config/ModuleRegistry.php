<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Config;

/**
 * Declarative route and admin nav metadata. Wire into app routers/menus when enabling the module.
 */
final class ModuleRegistry
{
    /**
     * Logical HTTP routes (for future front controller or manual includes).
     *
     * @return list<array{method:string,pattern:string,handler:string,auth:string}>
     */
    public static function httpRoutes(): array
    {
        return [
            [
                'method' => 'GET',
                'pattern' => '/api/infrastructure-marketplace/health.php',
                'handler' => 'Ratib\\InfrastructureMarketplace\\Controllers\\HealthController::handle',
                'auth' => 'optional',
            ],
        ];
    }

    /**
     * Control panel / admin menu hints (copy into sidebar or load dynamically).
     *
     * @return list<array{label:string,href:string,permission_hint:string}>
     */
    public static function controlPanelNavHints(): array
    {
        return [
            [
                'label' => 'Infrastructure marketplace',
                'href' => '/api/infrastructure-marketplace/health',
                'permission_hint' => 'control_system_settings or custom infra permission (define when activating)',
            ],
        ];
    }
}
