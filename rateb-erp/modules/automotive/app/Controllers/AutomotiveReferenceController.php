<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Controllers;

use Rateb\App\Automotive\Services\AutomotiveReferenceService;
use Rateb\App\Core\SessionManager;

final class AutomotiveReferenceController extends AutomotiveBaseController
{
    public function index(): void
    {
        $this->bootstrapAutomotive();
        $this->guardView();
        $service = new AutomotiveReferenceService();
        $ready = $this->tablesReady($service);
        $this->automotiveView('reference/index', [
            'title' => __('auto_epc_reference'),
            'ready' => $ready,
            'markets' => $ready ? $service->markets() : [],
            'organizations' => $ready ? $service->organizations() : [],
            'brands' => $ready ? $service->vehicleBrands() : [],
            'can_manage' => $this->canManage(),
        ]);
    }

    public function storeMarket(): void
    {
        $this->mutate(function (AutomotiveReferenceService $service): void {
            $parent = (int) $this->input('parent_market_id', 0);
            $service->createMarket(
                (string) $this->input('code', ''),
                (string) $this->input('name_en', ''),
                (string) $this->input('name_ar', ''),
                $parent > 0 ? $parent : null
            );
        });
    }

    public function storeOrganization(): void
    {
        $this->mutate(function (AutomotiveReferenceService $service): void {
            $parent = (int) $this->input('parent_org_id', 0);
            $service->createOrganization(
                (string) $this->input('org_role', ''),
                (int) $this->input('home_market_id', 0),
                (string) $this->input('name_en', ''),
                (string) $this->input('name_ar', ''),
                $parent > 0 ? $parent : null
            );
        });
    }

    public function storeBrand(): void
    {
        $this->mutate(function (AutomotiveReferenceService $service): void {
            $service->createVehicleBrand(
                (int) $this->input('vehicle_mfr_org_id', 0),
                (string) $this->input('name_en', ''),
                (string) $this->input('name_ar', '')
            );
        });
    }

    private function mutate(callable $action): void
    {
        $this->bootstrapAutomotive();
        $this->guardManage();
        $back = rateb_url(rateb_app_route('automotive/reference'));
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect($back);
        }
        try {
            $action(new AutomotiveReferenceService());
            SessionManager::flash('success', __('auto_epc_saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect($back);
    }

    private function tablesReady(AutomotiveReferenceService $service): bool
    {
        try {
            $service->markets();
            return true;
        } catch (\Throwable) {
            return false;
        }
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
