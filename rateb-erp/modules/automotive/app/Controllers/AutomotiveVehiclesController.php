<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Controllers;

use Rateb\App\Automotive\Services\AutomotiveCatalogService;
use Rateb\App\Core\SessionManager;

final class AutomotiveVehiclesController extends AutomotiveBaseController
{
    public function index(): void
    {
        $this->bootstrapAutomotive();
        $this->guardView();
        $companyId = $this->companyId();
        $this->automotiveView('vehicles/index', [
            'title' => __('auto_vehicles'),
            'vehicles' => $companyId > 0 ? (new AutomotiveCatalogService())->listVehicles($companyId) : [],
            'can_manage' => function_exists('rateb_can_manage_entity') && rateb_can_manage_entity('automotive'),
        ]);
    }

    public function store(): void
    {
        $this->bootstrapAutomotive();
        if (!function_exists('rateb_can_manage_entity') || !rateb_can_manage_entity('automotive')) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url(rateb_app_route('automotive/vehicles')));
        }
        try {
            (new AutomotiveCatalogService())->createVehicle(
                $this->companyId(),
                (string) $this->input('make', ''),
                (string) $this->input('model', ''),
                (int) $this->input('model_year', 0),
                (string) $this->input('engine', ''),
                (string) $this->input('trim', '')
            );
            SessionManager::flash('success', __('auto_vehicle_saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('automotive/vehicles')));
    }
}
