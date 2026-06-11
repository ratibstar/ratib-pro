<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Services\ContractWorkflowService;
use Rateb\App\Services\ErpAnalyticsService;
use Rateb\App\Services\InventoryWorkflowService;

final class ExecutiveDashboardController extends Controller
{
    public function index(): void
    {
        $this->view('admin/executive-dashboard/index', [
            'title' => __('executive_dashboard'),
            'data' => (new ErpAnalyticsService())->executiveDashboard(),
            'expiring_contracts' => (new ContractWorkflowService())->expiringContracts(60),
            'expiring_inventory' => (new InventoryWorkflowService())->expiringItems(30),
            'csrf' => Csrf::token(),
        ], 'main');
    }
}
