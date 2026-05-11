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
            [
                'method' => 'GET',
                'pattern' => '/api/infrastructure-marketplace/dashboard.php',
                'handler' => 'module endpoint file: api/infrastructure-marketplace/dashboard.php',
                'auth' => 'optional',
            ],
            [
                'method' => 'GET',
                'pattern' => '/api/infrastructure-marketplace/catalog.php',
                'handler' => 'module endpoint file: api/infrastructure-marketplace/catalog.php',
                'auth' => 'optional',
            ],
            [
                'method' => 'POST',
                'pattern' => '/api/infrastructure-marketplace/order.php',
                'handler' => 'module endpoint file: api/infrastructure-marketplace/order.php',
                'auth' => 'optional',
            ],
            [
                'method' => 'POST',
                'pattern' => '/api/infrastructure-marketplace/lifecycle-action.php',
                'handler' => 'module endpoint file: api/infrastructure-marketplace/lifecycle-action.php',
                'auth' => 'optional',
            ],
            [
                'method' => 'GET',
                'pattern' => '/api/infrastructure-marketplace/providers.php',
                'handler' => 'module endpoint file: api/infrastructure-marketplace/providers.php',
                'auth' => 'optional',
            ],
            [
                'method' => 'POST',
                'pattern' => '/api/infrastructure-marketplace/provider-activation.php',
                'handler' => 'module endpoint file: api/infrastructure-marketplace/provider-activation.php',
                'auth' => 'optional',
            ],
            [
                'method' => 'GET',
                'pattern' => '/api/infrastructure-marketplace/ops-queue.php',
                'handler' => 'module endpoint file: api/infrastructure-marketplace/ops-queue.php',
                'auth' => 'optional',
            ],
            [
                'method' => 'POST',
                'pattern' => '/api/infrastructure-marketplace/ops-retry-job.php',
                'handler' => 'module endpoint file: api/infrastructure-marketplace/ops-retry-job.php',
                'auth' => 'optional',
            ],
            [
                'method' => 'GET',
                'pattern' => '/api/infrastructure-marketplace/ops-job-trace.php',
                'handler' => 'module endpoint file: api/infrastructure-marketplace/ops-job-trace.php',
                'auth' => 'optional',
            ],
            [
                'method' => 'POST',
                'pattern' => '/api/infrastructure-marketplace/ops-replay-job.php',
                'handler' => 'module endpoint file: api/infrastructure-marketplace/ops-replay-job.php',
                'auth' => 'optional',
            ],
            [
                'method' => 'GET',
                'pattern' => '/api/infrastructure-marketplace/ops-billing-sync.php',
                'handler' => 'module endpoint file: api/infrastructure-marketplace/ops-billing-sync.php',
                'auth' => 'optional',
            ],
            [
                'method' => 'GET',
                'pattern' => '/api/infrastructure-marketplace/domain-search.php',
                'handler' => 'module endpoint file: api/infrastructure-marketplace/domain-search.php',
                'auth' => 'optional',
            ],
            [
                'method' => 'GET',
                'pattern' => '/modules/infrastructure-marketplace/Views/admin/dashboard.php',
                'handler' => 'module view file',
                'auth' => 'optional',
            ],
            [
                'method' => 'GET',
                'pattern' => '/modules/infrastructure-marketplace/Views/admin/providers.php',
                'handler' => 'module view file',
                'auth' => 'optional',
            ],
            [
                'method' => 'GET',
                'pattern' => '/modules/infrastructure-marketplace/Views/marketplace/index.php',
                'handler' => 'module view file',
                'auth' => 'optional',
            ],
            [
                'method' => 'GET',
                'pattern' => '/modules/infrastructure-marketplace/Views/client/services.php',
                'handler' => 'module view file',
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
                'href' => '/api/infrastructure-marketplace/health.php',
                'permission_hint' => 'control_system_settings or custom infra permission (define when activating)',
            ],
            [
                'label' => 'Infrastructure dashboard',
                'href' => '/modules/infrastructure-marketplace/Views/admin/dashboard.php',
                'permission_hint' => 'control_system_settings',
            ],
            [
                'label' => 'Infrastructure providers',
                'href' => '/modules/infrastructure-marketplace/Views/admin/providers.php',
                'permission_hint' => 'control_system_settings',
            ],
            [
                'label' => 'Infrastructure marketplace',
                'href' => '/modules/infrastructure-marketplace/Views/marketplace/index.php',
                'permission_hint' => 'control_system_settings or agency user when published',
            ],
            [
                'label' => 'Infrastructure client services',
                'href' => '/modules/infrastructure-marketplace/Views/client/services.php',
                'permission_hint' => 'tenant scoped authenticated user',
            ],
        ];
    }
}
