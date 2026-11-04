<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Controllers;

use Rateb\App\Automotive\Services\AutomotiveGate;
use Rateb\App\Automotive\Services\AutomotiveOpenCatalogService;
use Rateb\App\Automotive\Services\AutomotiveSelectionService;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\Logger;

final class AutomotiveOpenCatalogController extends AutomotiveBaseController
{
    public function __construct(private ?AutomotiveSelectionService $selections = null)
    {
    }

    public function index(): void
    {
        $this->guardView();
        $service = new AutomotiveOpenCatalogService();
        $this->automotiveView('open-catalog/index', [
            'title' => __('auto_open_catalog'),
            'release' => $service->release(),
            'kinds' => $service->kinds(),
        ]);
    }

    public function types(): void
    {
        $this->guardView();
        $this->automotiveView('open-catalog/types', [
            'title' => __('auto_open_types'),
            'kinds' => (new AutomotiveOpenCatalogService())->kinds(),
        ]);
    }

    public function makes(): void
    {
        $this->guardView();
        $page = $this->page();
        $result = (new AutomotiveOpenCatalogService())->makes(
            (string) $this->input('kind', ''),
            (string) $this->input('q', ''),
            $page
        );
        $this->automotiveView('open-catalog/makes', [
            'title' => __('auto_open_makes'),
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'kind' => (string) $this->input('kind', ''),
            'q' => (string) $this->input('q', ''),
        ]);
    }

    public function models(): void
    {
        $this->guardView();
        $result = (new AutomotiveOpenCatalogService())->models(
            (string) $this->input('kind', ''),
            (string) $this->input('make', ''),
            (string) $this->input('market', ''),
            (string) $this->input('q', ''),
            $this->page()
        );
        $this->automotiveView('open-catalog/models', [
            'title' => __('auto_open_models'),
            'rows' => $result['rows'],
            'total' => $result['total'],
            'page' => $result['page'],
            'kind' => (string) $this->input('kind', ''),
            'make' => (string) $this->input('make', ''),
            'market' => (string) $this->input('market', ''),
            'q' => (string) $this->input('q', ''),
        ]);
    }

    public function show(int $id): void
    {
        $this->guardView();
        $detail = (new AutomotiveOpenCatalogService())->detail($id);
        $this->automotiveView('open-catalog/show', [
            'title' => (string) $detail['model']['name_en'],
            'detail' => $detail,
            'can_select' => function_exists('rateb_can') && rateb_can('automotive.selection.manage'),
        ]);
    }

    public function select(): void
    {
        $this->bootstrapAutomotive();
        try {
            (new AutomotiveGate())->assert('automotive.selection.manage');
        } catch (\Throwable) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url(rateb_app_route('automotive/open-catalog')));
        }
        try {
            ($this->selections ?? new AutomotiveSelectionService())->select('open_model', (int) $this->input('model_id', 0));
            SessionManager::flash('success', __('auto_epc_saved'));
        } catch (\InvalidArgumentException $e) {
            Logger::warning('open catalog selection rejected', ['class' => $e::class]);
            SessionManager::flash('error', __('auto_open_select_failed'));
        } catch (\PDOException $e) {
            $duplicate = (string) $e->getCode() === '23000';
            Logger::error('open catalog selection failed', ['class' => $e::class, 'code' => (string) $e->getCode()]);
            SessionManager::flash('error', __($duplicate ? 'auto_open_select_duplicate' : 'auto_open_select_failed'));
        } catch (\RuntimeException $e) {
            Logger::error('open catalog selection failed', ['class' => $e::class]);
            SessionManager::flash('error', __('auto_open_select_failed'));
        }
        $this->redirect(rateb_url(rateb_app_route('automotive/open-catalog')));
    }

    private function page(): int
    {
        return max(1, (int) $this->input('page', 1));
    }
}
