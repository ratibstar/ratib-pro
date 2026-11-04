<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Controllers;

use Rateb\App\Automotive\Services\AutomotiveGate;
use Rateb\App\Automotive\Services\AutomotiveGlobalCatalogService;
use Rateb\App\Automotive\Services\AutomotiveImportService;
use Rateb\App\Core\SessionManager;

final class AutomotiveCatalogController extends AutomotiveBaseController
{
    public function index(): void
    {
        $this->bootstrapAutomotive();
        $this->guardView();
        $term = trim((string) $this->input('q', ''));
        $market = trim((string) $this->input('market', ''));
        $rows = [];
        if ($term !== '') {
            try {
                $rows = (new AutomotiveGlobalCatalogService())->search($term, $market);
            } catch (\Throwable) {
                $rows = [];
            }
        }
        $this->automotiveView('catalog/index', [
            'title' => __('auto_epc_catalog'),
            'rows' => $rows,
            'q' => $term,
            'market' => $market,
        ]);
    }

    public function storePart(): void
    {
        $this->mutate(function (): void {
            (new AutomotiveGlobalCatalogService())->createPart(
                (string) $this->input('name_en', ''),
                (string) $this->input('name_ar', '')
            );
        }, 'automotive.reference.manage');
    }

    public function retirePart(): void
    {
        $this->mutate(function (): void {
            (new AutomotiveGlobalCatalogService())->retirePart((int) $this->input('part_id', 0));
        }, 'automotive.reference.retire');
    }

    public function previewImport(): void
    {
        $this->mutate(function (): void {
            (new AutomotiveImportService())->preview((int) $this->input('source_id', 0), (string) $this->input('csv', ''));
        }, 'automotive.reference.import');
    }

    public function approveImport(): void
    {
        $this->mutate(function (): void {
            (new AutomotiveImportService())->approve((int) $this->input('batch_id', 0));
        }, 'automotive.reference.approve');
    }

    private function mutate(callable $action, string $permission): void
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
            $this->redirect(rateb_url(rateb_app_route('automotive/catalog')));
        }
        try {
            $action();
            SessionManager::flash('success', __('auto_epc_saved'));
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_url(rateb_app_route('automotive/catalog')));
    }
}
