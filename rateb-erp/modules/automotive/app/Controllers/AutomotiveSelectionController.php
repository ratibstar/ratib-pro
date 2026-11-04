<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Controllers;

use Rateb\App\Automotive\Services\AutomotiveGate;
use Rateb\App\Automotive\Services\AutomotiveSelectionService;
use Rateb\App\Core\SessionManager;

final class AutomotiveSelectionController extends AutomotiveBaseController
{
    public function index(): void
    {
        $this->bootstrapAutomotive();
        (new AutomotiveGate())->assert('automotive.selection.view');
        $rows = (new AutomotiveSelectionService())->selections();
        $this->automotiveView('selection/index', [
            'title' => __('auto_epc_selection'),
            'rows' => $rows,
            'can_manage' => function_exists('rateb_can') && rateb_can('automotive.selection.manage'),
            'can_link' => function_exists('rateb_can') && rateb_can('automotive.inventory_link.manage'),
            'can_map' => function_exists('rateb_can') && rateb_can('automotive.supplier_map.manage'),
        ]);
    }

    public function store(): void
    {
        $this->mutate(function (AutomotiveSelectionService $service): void {
            $service->select((string) $this->input('entity_type', ''), (int) $this->input('entity_id', 0));
        });
    }

    public function remove(): void
    {
        $this->mutate(function (AutomotiveSelectionService $service): void {
            $service->remove((int) $this->input('selection_id', 0));
        });
    }

    public function link(): void
    {
        $this->mutate(function (AutomotiveSelectionService $service): void {
            $service->linkInventory((int) $this->input('global_part_id', 0), (int) $this->input('inventory_id', 0));
        }, 'automotive.inventory_link.manage');
    }

    public function mapSupplier(): void
    {
        $this->mutate(function (AutomotiveSelectionService $service): void {
            $service->mapSupplier(
                (int) $this->input('global_part_id', 0),
                (int) $this->input('supplier_id', 0),
                (string) $this->input('supplier_number', '')
            );
        }, 'automotive.supplier_map.manage');
    }

    private function mutate(callable $action, string $permission = 'automotive.selection.manage'): void
    {
        $this->bootstrapAutomotive();
        try {
            (new AutomotiveGate())->assert($permission);
        } catch (\Throwable) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url(rateb_app_route('automotive/selection')));
        }
        try {
            $action(new AutomotiveSelectionService());
            SessionManager::flash('success', __('auto_epc_saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('automotive/selection')));
    }
}
