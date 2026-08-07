<?php
declare(strict_types=1);

namespace Rateb\App\Marketplace\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\TenantContext;
use Rateb\App\Marketplace\Support\MarketplaceView;

abstract class MarketplaceBaseController extends Controller
{
    protected function bootstrapMarketplace(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
    }

    protected function companyId(): int
    {
        return (int) (TenantContext::companyId() ?? 0);
    }

    protected function guardView(string $resource = 'marketplace'): void
    {
        if (function_exists('rateb_can_view_entity') && !rateb_can_view_entity($resource)) {
            if (function_exists('rateb_can') && (rateb_can('marketplace.view') || rateb_can('marketplace.manage'))) {
                return;
            }
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }

    protected function marketplaceView(string $view, array $data = []): void
    {
        MarketplaceView::render($view, $data, 'main');
    }
}
