<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Services\Help\HelpAnalyticsService;
use Rateb\App\Services\Help\HelpAssistantService;

/**
 * Help Assistant chat API (authenticated, CSRF-protected mutations).
 */
final class HelpAssistantController extends Controller
{
    private HelpAssistantService $assistant;
    private HelpAnalyticsService $analytics;

    public function __construct()
    {
        $this->assistant = new HelpAssistantService();
        $this->analytics = new HelpAnalyticsService();
    }

    public function bootstrap(): void
    {
        $this->json($this->assistant->bootstrap($this->readContext()));
    }

    public function ask(): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['ok' => false, 'error' => 'csrf'], 419);

            return;
        }
        $question = trim((string) $this->input('question', ''));
        $ctx = $this->readContext();
        $isQuick = (string) $this->input('source', '') === 'quick';
        if ($isQuick) {
            $this->analytics->track([
                'event_type' => 'quick',
                'locale' => (string) ($ctx['locale'] ?? 'ar'),
                'module_slug' => '',
                'route_hint' => (string) ($ctx['route'] ?? ''),
                'query_text' => $question,
                'has_answer' => true,
            ]);
        }
        $this->json($this->assistant->ask($question, $ctx));
    }

    public function track(): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['ok' => false, 'error' => 'csrf'], 419);

            return;
        }
        $type = (string) $this->input('event_type', 'open_article');
        $this->analytics->track([
            'event_type' => $type,
            'locale' => (string) $this->input('locale', 'ar'),
            'module_slug' => (string) $this->input('module_slug', ''),
            'route_hint' => (string) $this->input('route', ''),
            'query_text' => (string) $this->input('query_text', ''),
            'article_slug' => (string) $this->input('article_slug', ''),
            'has_answer' => true,
        ]);
        $this->json(['ok' => true]);
    }

    /** @return array{locale:string,route:string} */
    private function readContext(): array
    {
        $locale = strtolower(substr((string) $this->input('locale', function_exists('rateb_locale') ? rateb_locale() : 'ar'), 0, 2));
        if ($locale !== 'en') {
            $locale = 'ar';
        }
        $route = trim(str_replace('\\', '/', (string) $this->input('route', '')), '/');
        if ($route === '' && function_exists('rateb_current_erp_route')) {
            $route = (string) rateb_current_erp_route();
        }

        return ['locale' => $locale, 'route' => $route];
    }
}
