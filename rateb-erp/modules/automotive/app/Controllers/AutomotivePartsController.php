<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Controllers;

use Rateb\App\Automotive\Services\AutomotiveCatalogService;
use Rateb\App\Core\SessionManager;

final class AutomotivePartsController extends AutomotiveBaseController
{
    public function index(): void
    {
        $this->bootstrapAutomotive();
        $this->guardView();
        $companyId = $this->companyId();
        $catalog = new AutomotiveCatalogService();
        $this->automotiveView('parts/index', [
            'title' => __('auto_parts'),
            'parts' => $companyId > 0 ? $catalog->listParts($companyId) : [],
            'inventory_choices' => $companyId > 0 ? $catalog->inventoryChoices($companyId) : [],
            'can_manage' => $this->canManage(),
        ]);
    }

    public function store(): void
    {
        $this->bootstrapAutomotive();
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url(rateb_app_route('automotive/parts')));
        }
        try {
            $id = (new AutomotiveCatalogService())->createPart(
                $this->companyId(),
                (int) $this->input('inventory_id', 0),
                (string) $this->input('manufacturer', ''),
                (string) $this->input('brand', ''),
                (string) $this->input('oem_number', ''),
                (string) $this->input('status', 'active')
            );
            SessionManager::flash('success', __('auto_part_saved'));
            $this->redirect(rateb_url(rateb_app_route('automotive/parts/' . $id)));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('automotive/parts')));
        }
    }

    public function show(array $params): void
    {
        $this->bootstrapAutomotive();
        $this->guardView();
        $companyId = $this->companyId();
        try {
            $part = (new AutomotiveCatalogService())->partDetail($companyId, (int) ($params['id'] ?? 0));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url(rateb_app_route('automotive/parts')));
        }
        $catalog = new AutomotiveCatalogService();
        $this->automotiveView('parts/show', [
            'title' => __('auto_part'),
            'part' => $part,
            'vehicles' => $catalog->listVehicles($companyId),
            'inventory_choices' => $catalog->inventoryExcept($companyId, (int) ($part['inventory_id'] ?? 0)),
            'can_manage' => $this->canManage(),
        ]);
    }

    public function storeNumber(array $params): void
    {
        $this->mutateChild($params, function (AutomotiveCatalogService $catalog, int $companyId, int $partId): void {
            $catalog->addNumber($companyId, $partId, (string) $this->input('code', ''), (string) $this->input('code_type', ''));
        });
    }

    public function storeBarcode(array $params): void
    {
        $this->mutateChild($params, function (AutomotiveCatalogService $catalog, int $companyId, int $partId): void {
            $catalog->addBarcode($companyId, $partId, (string) $this->input('code', ''));
        });
    }

    public function storeFitment(array $params): void
    {
        $this->mutateChild($params, function (AutomotiveCatalogService $catalog, int $companyId, int $partId): void {
            $catalog->addFitment($companyId, $partId, (int) $this->input('vehicle_id', 0));
        });
    }

    public function storeInterchange(array $params): void
    {
        $this->mutateChild($params, function (AutomotiveCatalogService $catalog, int $companyId, int $partId): void {
            $catalog->addInterchange($companyId, $partId, (int) $this->input('alternate_inventory_id', 0));
        });
    }

    private function mutateChild(array $params, callable $action): void
    {
        $this->bootstrapAutomotive();
        $this->guardManage();
        $partId = (int) ($params['id'] ?? 0);
        $back = rateb_url(rateb_app_route('automotive/parts/' . $partId));
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect($back);
        }
        try {
            $action(new AutomotiveCatalogService(), $this->companyId(), $partId);
            SessionManager::flash('success', __('auto_part_saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect($back);
    }

    private function canManage(): bool
    {
        return function_exists('rateb_can_manage_entity') && rateb_can_manage_entity('automotive');
    }

    private function guardManage(): void
    {
        if ($this->canManage()) {
            return;
        }
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}
