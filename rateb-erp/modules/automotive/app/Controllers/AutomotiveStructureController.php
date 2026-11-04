<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Controllers;

use Rateb\App\Automotive\Services\AutomotiveGate;
use Rateb\App\Automotive\Services\AutomotiveGlobalCatalogService;
use Rateb\App\Core\SessionManager;

final class AutomotiveStructureController extends AutomotiveBaseController
{
    public function index(): void
    {
        $this->bootstrapAutomotive();
        (new AutomotiveGate())->assert('automotive.reference.view');
        $catalog = new AutomotiveGlobalCatalogService();
        $this->automotiveView('structure/index', [
            'title' => __('auto_epc_structure'),
            'models' => $catalog->models(),
            'generations' => $catalog->generations(),
            'engines' => $catalog->engines(),
            'transmissions' => $catalog->transmissions(),
            'diagrams' => $catalog->diagrams(),
            'can_manage' => function_exists('rateb_can') && rateb_can('automotive.reference.manage'),
        ]);
    }

    public function storeModel(): void
    {
        $this->mutate(function (AutomotiveGlobalCatalogService $catalog): void {
            $catalog->createModel((int) $this->input('vehicle_brand_id', 0), (string) $this->input('name_en', ''), (string) $this->input('name_ar', ''));
        });
    }

    public function storeGeneration(): void
    {
        $this->mutate(function (AutomotiveGlobalCatalogService $catalog): void {
            $catalog->createGeneration(
                (int) $this->input('model_id', 0),
                (int) $this->input('market_id', 0),
                (string) $this->input('name_en', ''),
                (string) $this->input('name_ar', ''),
                (int) $this->input('year_from', 0),
                (int) $this->input('year_to', 0)
            );
        });
    }

    public function storeEngine(): void
    {
        $this->mutate(function (AutomotiveGlobalCatalogService $catalog): void {
            $catalog->createEngine((string) $this->input('code', ''), (string) $this->input('name_en', ''), (string) $this->input('name_ar', ''));
        });
    }

    public function storeTransmission(): void
    {
        $this->mutate(function (AutomotiveGlobalCatalogService $catalog): void {
            $catalog->createTransmission((string) $this->input('code', ''), (string) $this->input('name_en', ''), (string) $this->input('name_ar', ''));
        });
    }

    public function storeDiagram(): void
    {
        $this->mutate(function (AutomotiveGlobalCatalogService $catalog): void {
            $catalog->createDiagram(
                (string) $this->input('system_code', ''),
                (string) $this->input('assembly_code', ''),
                (int) $this->input('version_no', 0),
                (string) $this->input('svg_text', '')
            );
        });
    }

    private function mutate(callable $action): void
    {
        $this->bootstrapAutomotive();
        try {
            (new AutomotiveGate())->assert('automotive.reference.manage');
        } catch (\Throwable) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url(rateb_app_route('automotive/structure')));
        }
        try {
            $action(new AutomotiveGlobalCatalogService());
            SessionManager::flash('success', __('auto_epc_saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('automotive/structure')));
    }
}
