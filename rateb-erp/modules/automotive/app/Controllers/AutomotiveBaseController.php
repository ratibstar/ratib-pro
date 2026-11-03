<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Controllers;

use Rateb\App\Automotive\Support\AutomotiveView;
use Rateb\App\Core\Controller;
use Rateb\App\Core\TenantContext;

abstract class AutomotiveBaseController extends Controller
{
    protected function bootstrapAutomotive(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
    }

    protected function companyId(): int
    {
        return (int) (TenantContext::companyId() ?? 0);
    }

    protected function guardView(string $resource = 'automotive'): void
    {
        if (function_exists('rateb_can_view_entity') && !rateb_can_view_entity($resource)) {
            if (function_exists('rateb_can') && (rateb_can('automotive.view') || rateb_can('automotive.manage'))) {
                return;
            }
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }

    protected function automotiveView(string $view, array $data = []): void
    {
        AutomotiveView::render($view, $data, 'main');
    }
}
