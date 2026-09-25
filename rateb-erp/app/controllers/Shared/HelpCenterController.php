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
        $q = trim((string) $this->input('q', ''));
        $this->view('help/index', array_merge($this->searchChrome($q), [
            'title' => __('help_center'),
            'modules' => $this->repo->modulesForUser(),
            'faqs' => $this->repo->faqs(),
            'canManage' => $this->repo->gate()->canManageContent(),
        ], $this->erpAppPublished()), 'main');
    }

    /**
     * Offers + content pages the platform published for the ERP app (this company + all companies).
     *
     * @return array{appOffers:list<array<string,mixed>>,appPages:list<array<string,string>>}
     */
    private function erpAppPublished(): array
    {
        try {
            $cid = (int) (\Rateb\App\Core\TenantContext::companyId() ?? 0);
            $ops = new \Rateb\App\Services\AgentAppsOpsService();

            return [
                'appOffers' => $ops->publishedOffers(max(0, $cid), 'erp'),
                'appPages' => $ops->publishedContent(max(0, $cid), 'erp'),
            ];
        } catch (\Throwable $e) {
            error_log('HelpCenterController::erpAppPublished: ' . $e->getMessage());

            return ['appOffers' => [], 'appPages' => []];
        }
    }

    public function module(string $slug): void
    {
        $module = $this->repo->module($slug);
        if ($module === null) {
            $this->notFound();

            return;
        }
        $q = trim((string) $this->input('q', ''));
        $this->view('help/module', array_merge($this->searchChrome($q), [
            'title' => (string) ($module['title'] ?? __('help_center')),
            'module' => $module,
            'articles' => $this->repo->articlesForModule($slug),
            'faqs' => $this->repo->faqs($slug),
        ]), 'main');
    }

    public function article(string $slug): void
    {
        $article = $this->repo->article($slug);
        if ($article === null) {
            $this->notFound();

            return;
        }
        $q = trim((string) $this->input('q', ''));
        $this->view('help/article', array_merge($this->searchChrome($q), [
            'title' => (string) ($article['title'] ?? __('help_center')),
            'article' => $article,
        ]), 'main');
    }

    /**
     * @return array<string,mixed>
     */
    private function searchChrome(string $q): array
    {
        return [
            'searchIndex' => $this->repo->searchIndex(),
            'searchQuery' => $q,
            'searchHits' => $q !== '' ? $this->search->search($q, 20) : [],
            'helpHomeUrl' => rateb_url('admin/help'),
        ];
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
