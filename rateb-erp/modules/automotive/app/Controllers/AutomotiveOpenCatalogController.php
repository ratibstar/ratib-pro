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
        $loaded = $this->openRelease();
        $this->automotiveView('open-catalog/index', [
            'title' => __('auto_open_catalog'),
            'release' => $loaded['release'] ?? [],
            'kinds' => $loaded ? $loaded['service']->kinds() : [],
            'missing_release' => $loaded === null,
        ]);
    }

    public function types(): void
    {
        $this->guardView();
        $loaded = $this->openRelease();
        $this->automotiveView('open-catalog/types', [
            'title' => __('auto_open_types'),
            'kinds' => $loaded ? $loaded['service']->kinds() : [],
            'missing_release' => $loaded === null,
        ]);
    }

    public function makes(): void
    {
        $this->guardView();
        $loaded = $this->openRelease();
        if ($loaded === null) {
            $this->emptyListing('open-catalog/makes', __('auto_open_makes'));
            return;
        }
        $page = $this->page();
        $result = $loaded['service']->makes(
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
        $loaded = $this->openRelease();
        if ($loaded === null) {
            $this->emptyListing('open-catalog/models', __('auto_open_models'));
            return;
        }
        $result = $loaded['service']->models(
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
        if ($this->openRelease() === null) {
            $this->redirect(rateb_url(rateb_app_route('automotive/open-catalog')));
        }
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

    /** @return array{service:AutomotiveOpenCatalogService,release:array<string,mixed>}|null */
    private function openRelease(): ?array
    {
        $service = new AutomotiveOpenCatalogService();
        try {
            return ['service' => $service, 'release' => $service->release()];
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'open_release_missing') {
                throw $e;
            }
            return null;
        }
    }

    private function emptyListing(string $view, string $title): void
    {
        $this->automotiveView($view, [
            'title' => $title,
            'rows' => [],
            'total' => 0,
            'page' => 1,
            'missing_release' => true,
            'kind' => '',
            'make' => '',
            'market' => '',
            'q' => '',
        ]);
    }
}
