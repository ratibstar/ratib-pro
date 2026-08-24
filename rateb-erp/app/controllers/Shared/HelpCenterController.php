<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Controller;
use Rateb\App\Services\Help\HelpCenterRepository;
use Rateb\App\Services\Help\HelpContextService;
use Rateb\App\Services\Help\HelpSearchService;

final class HelpCenterController extends Controller
{
    private HelpCenterRepository $repo;
    private HelpSearchService $search;
    private HelpContextService $context;

    public function __construct()
    {
        $this->repo = new HelpCenterRepository();
        $this->search = new HelpSearchService($this->repo);
        $this->context = new HelpContextService($this->repo);
    }

    public function index(): void
    {
        $this->view('help/index', [
            'title' => __('help_center'),
            'modules' => $this->repo->modulesForUser(),
            'searchIndex' => $this->repo->searchIndex(),
            'faqs' => $this->repo->faqs(),
            'canManage' => $this->repo->gate()->canManageContent(),
            'helpHomeUrl' => rateb_url('admin/help'),
        ], 'main');
    }

    public function module(string $slug): void
    {
        $module = $this->repo->module($slug);
        if ($module === null) {
            $this->notFound();

            return;
        }
        $this->view('help/module', [
            'title' => (string) ($module['title'] ?? __('help_center')),
            'module' => $module,
            'articles' => $this->repo->articlesForModule($slug),
            'faqs' => $this->repo->faqs($slug),
            'helpHomeUrl' => rateb_url('admin/help'),
        ], 'main');
    }

    public function article(string $slug): void
    {
        $article = $this->repo->article($slug);
        if ($article === null) {
            $this->notFound();

            return;
        }
        $this->view('help/article', [
            'title' => (string) ($article['title'] ?? __('help_center')),
            'article' => $article,
            'helpHomeUrl' => rateb_url('admin/help'),
        ], 'main');
    }

    public function searchApi(): void
    {
        $q = trim((string) $this->input('q', ''));
        $limit = max(1, min(40, (int) $this->input('limit', 20)));
        $this->json([
            'ok' => true,
            'q' => $q,
            'results' => $this->search->search($q, $limit),
        ]);
    }

    public function contextApi(): void
    {
        $path = trim((string) $this->input('path', ''));
        if ($path === '' && function_exists('rateb_current_erp_route')) {
            $path = (string) rateb_current_erp_route();
        }
        $payload = $this->context->forRoute($path);
        $this->json([
            'ok' => true,
            'path' => $path,
            'module' => $payload['module'],
            'suggestions' => $payload['suggestions'],
            'faqs' => $payload['faqs'],
            'helpHomeUrl' => rateb_url('admin/help'),
        ]);
    }

    public function indexJson(): void
    {
        $this->json([
            'ok' => true,
            'index' => $this->repo->searchIndex(),
        ]);
    }
}
