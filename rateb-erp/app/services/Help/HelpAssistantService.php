<?php
declare(strict_types=1);

namespace Rateb\App\Services\Help;

/**
 * Conversational Help Assistant — ranks Help Center articles with ERP context and builds safe answers.
 * Does not invent ERP features; answers only from Help Center knowledge.
 */
final class HelpAssistantService
{
    private HelpCenterRepository $repo;
    private HelpSearchService $search;
    private HelpContextService $context;
    private HelpAnalyticsService $analytics;

    public function __construct(
        ?HelpCenterRepository $repo = null,
        ?HelpSearchService $search = null,
        ?HelpContextService $context = null,
        ?HelpAnalyticsService $analytics = null
    ) {
        $this->repo = $repo ?? new HelpCenterRepository();
        $this->search = $search ?? new HelpSearchService($this->repo);
        $this->context = $context ?? new HelpContextService($this->repo);
        $this->analytics = $analytics ?? new HelpAnalyticsService();
    }

    /**
     * @return array<string,mixed>
     */
    public function ask(string $question, array $ctx = []): array
    {
        $locale = $this->normalizeLocale((string) ($ctx['locale'] ?? (function_exists('rateb_locale') ? rateb_locale() : 'ar')));
        $route = trim((string) ($ctx['route'] ?? ''), '/');
        $question = $this->sanitizeQuestion($question);
        $moduleSlug = $this->context->resolveModuleSlug($route);

        if ($question === '') {
            return $this->payload($locale, [
                'type' => 'clarification',
                'answer' => $locale === 'en'
                    ? 'Please type a short question about RATIB ERP.'
                    : 'اكتب سؤالاً قصيراً عن نظام رتب ERP.',
                'has_answer' => false,
            ], $moduleSlug, $route, $question);
        }

        $hits = $this->search->search($question, 12);
        $hits = array_values(array_filter($hits, static fn (array $h): bool => ($h['type'] ?? '') === 'article'));
        $hits = $this->boostByContext($hits, $moduleSlug, $question);

        if ($hits === []) {
            $this->analytics->track([
                'event_type' => 'unanswered',
                'locale' => $locale,
                'module_slug' => $moduleSlug,
                'route_hint' => $route,
                'query_text' => $question,
                'has_answer' => false,
            ]);

            return $this->payload($locale, [
                'type' => 'fallback',
                'answer' => $locale === 'en'
                    ? 'I could not find a matching guide in the Help Center yet. Try different keywords, open the full Help Center, or contact support.'
                    : 'لم أجد شرحاً مطابقاً في مركز المساعدة حالياً. جرّب كلمات أخرى، أو افتح مركز المساعدة، أو تواصل مع الدعم.',
                'has_answer' => false,
                'support_url' => $this->supportUrl(),
                'help_home_url' => rateb_url('admin/help'),
            ], $moduleSlug, $route, $question);
        }

        // Ambiguity: top two close scores across different modules.
        if (count($hits) >= 2) {
            $a = $hits[0];
            $b = $hits[1];
            $scoreA = (int) ($a['_rank'] ?? 0);
            $scoreB = (int) ($b['_rank'] ?? 0);
            if ($scoreA > 0 && abs($scoreA - $scoreB) <= 3
                && (string) ($a['module'] ?? '') !== (string) ($b['module'] ?? '')
            ) {
                return $this->payload($locale, [
                    'type' => 'clarification',
                    'answer' => $locale === 'en'
                        ? 'Your question may relate to more than one module. Which one do you mean?'
                        : 'سؤالك قد يتعلق بأكثر من وحدة. أيّها تقصد؟',
                    'has_answer' => true,
                    'options' => [
                        [
                            'label' => (string) ($a['module_title'] ?? $a['module'] ?? ''),
                            'article_slug' => (string) ($a['slug'] ?? ''),
                            'module' => (string) ($a['module'] ?? ''),
                        ],
                        [
                            'label' => (string) ($b['module_title'] ?? $b['module'] ?? ''),
                            'article_slug' => (string) ($b['slug'] ?? ''),
                            'module' => (string) ($b['module'] ?? ''),
                        ],
                    ],
                ], $moduleSlug, $route, $question);
            }
        }

        $top = $hits[0];
        $article = $this->repo->article((string) ($top['slug'] ?? ''));
        if ($article === null) {
            $this->analytics->track([
                'event_type' => 'unanswered',
                'locale' => $locale,
                'module_slug' => $moduleSlug,
                'route_hint' => $route,
                'query_text' => $question,
                'has_answer' => false,
            ]);

            return $this->payload($locale, [
                'type' => 'fallback',
                'answer' => $locale === 'en'
                    ? 'The matching guide is not available for your role right now.'
                    : 'الشرح المطابق غير متاح لصلاحيتك حالياً.',
                'has_answer' => false,
                'support_url' => $this->supportUrl(),
            ], $moduleSlug, $route, $question);
        }

        $answer = $this->composeAnswer($article, $locale);
        $related = array_slice(is_array($article['related'] ?? null) ? $article['related'] : [], 0, 4);
        $articleUrl = rateb_url('admin/help/article/' . rawurlencode((string) $article['slug']));

        $this->analytics->track([
            'event_type' => 'ask',
            'locale' => $locale,
            'module_slug' => (string) ($article['module'] ?? $moduleSlug ?? ''),
            'route_hint' => $route,
            'query_text' => $question,
            'article_slug' => (string) ($article['slug'] ?? ''),
            'has_answer' => true,
        ]);

        return $this->payload($locale, [
            'type' => 'answer',
            'answer' => $answer,
            'has_answer' => true,
            'module' => is_array($article['module_meta'] ?? null) ? $article['module_meta'] : null,
            'article' => [
                'slug' => (string) ($article['slug'] ?? ''),
                'title' => (string) ($article['title'] ?? ''),
                'summary' => (string) ($article['summary'] ?? ''),
                'module' => (string) ($article['module'] ?? ''),
                'minutes' => (int) ($article['minutes'] ?? 3),
                'difficulty' => (string) ($article['difficulty'] ?? 'beginner'),
                'icon' => (string) ($article['icon'] ?? 'fa-circle-question'),
                'url' => $articleUrl,
            ],
            'steps' => array_slice(array_values(array_map('strval', $article['steps'] ?? [])), 0, 5),
            'related' => array_map(static function (array $r): array {
                return [
                    'slug' => (string) ($r['slug'] ?? ''),
                    'title' => (string) ($r['title'] ?? ''),
                    'url' => rateb_url('admin/help/article/' . rawurlencode((string) ($r['slug'] ?? ''))),
                    'accent' => (string) ($r['accent'] ?? 'sky'),
                ];
            }, $related),
            'open_label' => $locale === 'en' ? 'Open full guide' : 'فتح الشرح الكامل',
            'support_url' => $this->supportUrl(),
        ], $moduleSlug, $route, $question);
    }

    /** @return array<string,mixed> */
    public function bootstrap(array $ctx = []): array
    {
        $locale = $this->normalizeLocale((string) ($ctx['locale'] ?? (function_exists('rateb_locale') ? rateb_locale() : 'ar')));
        $route = trim((string) ($ctx['route'] ?? ''), '/');
        $ctxData = $this->context->forRoute($route);
        $quick = $locale === 'en' ? $this->quickEn() : $this->quickAr();

        return [
            'ok' => true,
            'locale' => $locale,
            'welcome' => $locale === 'en'
                ? "Hello 👋 I'm RATIB Assistant. Ask me anything about RATIB ERP."
                : 'مرحباً 👋 أنا مساعد رتب. اسألني عن أي وظيفة أو شاشة في نظام رتب ERP.',
            'title' => $locale === 'en' ? 'RATIB AI Assistant' : 'مساعد رتب الذكي',
            'subtitle' => $locale === 'en' ? 'How can I help you?' : 'كيف يمكنني مساعدتك؟',
            'quick' => $quick,
            'context' => [
                'module' => $ctxData['module'],
                'suggestions' => array_slice($ctxData['suggestions'], 0, 5),
                'route' => $route,
                'company_id' => function_exists('rateb_resolve_ops_company_id') ? (int) rateb_resolve_ops_company_id() : 0,
                'branch_id' => function_exists('rateb_active_branch_filter_id') ? (int) rateb_active_branch_filter_id() : 0,
                'role' => $this->repo->gate()->audienceLevel(),
            ],
            'help_home_url' => rateb_url('admin/help'),
            'support_url' => $this->supportUrl(),
            'typing' => $locale === 'en' ? 'Typing...' : 'يكتب الآن...',
        ];
    }

    /**
     * @param list<array<string,mixed>> $hits
     * @return list<array<string,mixed>>
     */
    private function boostByContext(array $hits, ?string $moduleSlug, string $question): array
    {
        $out = [];
        foreach ($hits as $i => $hit) {
            $score = 100 - $i;
            if ($moduleSlug !== null && (string) ($hit['module'] ?? '') === $moduleSlug) {
                $score += 25;
            }
            // Prefer beginner guides for "how do I" style questions.
            if (preg_match('/كيف|how\s+do|how\s+to|أضيف|انشئ|أنشئ|create|add/ui', $question) === 1
                && (string) ($hit['difficulty'] ?? '') === 'beginner'
            ) {
                $score += 5;
            }
            $hit['_rank'] = $score;
            $out[] = $hit;
        }
        usort($out, static fn (array $a, array $b): int => ((int) ($b['_rank'] ?? 0)) <=> ((int) ($a['_rank'] ?? 0)));

        return $out;
    }

    /** @param array<string,mixed> $article */
    private function composeAnswer(array $article, string $locale): string
    {
        $title = (string) ($article['title'] ?? '');
        $moduleTitle = is_array($article['module_meta'] ?? null)
            ? (string) ($article['module_meta']['title'] ?? $article['module'] ?? '')
            : (string) ($article['module'] ?? '');
        $what = trim((string) ($article['what'] ?? ''));
        $steps = array_values(array_map('strval', $article['steps'] ?? []));
        $steps = array_slice($steps, 0, 4);

        if ($locale === 'en') {
            $lines = [];
            $lines[] = 'Module: ' . ($moduleTitle !== '' ? $moduleTitle : 'ERP');
            $lines[] = 'Guide: ' . $title;
            if ($what !== '') {
                $lines[] = $what;
            }
            if ($steps !== []) {
                $lines[] = 'Steps:';
                foreach ($steps as $i => $step) {
                    $lines[] = ($i + 1) . '. ' . $step;
                }
            }

            return implode("\n", $lines);
        }

        $lines = [];
        $lines[] = 'الوحدة: ' . ($moduleTitle !== '' ? $moduleTitle : 'ERP');
        $lines[] = 'الشرح: ' . $title;
        if ($what !== '') {
            $lines[] = $what;
        }
        if ($steps !== []) {
            $lines[] = 'الخطوات:';
            foreach ($steps as $i => $step) {
                $lines[] = ($i + 1) . '. ' . $step;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function payload(string $locale, array $data, ?string $moduleSlug, string $route, string $question): array
    {
        return array_merge([
            'ok' => true,
            'locale' => $locale,
            'question' => $question,
            'context_module' => $moduleSlug,
            'route' => $route,
            'ts' => date('c'),
        ], $data);
    }

    private function sanitizeQuestion(string $q): string
    {
        $q = strip_tags($q);
        $q = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $q) ?? $q;
        $q = trim(preg_replace('/\s+/u', ' ', $q) ?? $q);
        if (mb_strlen($q) > 400) {
            $q = mb_substr($q, 0, 400);
        }

        return $q;
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(substr($locale, 0, 2));

        return $locale === 'en' ? 'en' : 'ar';
    }

    /** Public contact page — not the internal ERP support-tickets module. */
    private function supportUrl(): string
    {
        $override = trim((string) (getenv('RATEB_HELP_SUPPORT_URL') ?: ''));
        if ($override !== '') {
            return function_exists('rateb_external_url')
                ? rateb_external_url($override)
                : $override;
        }

        return rateb_url('site/contact');
    }

    /** @return list<string> */
    private function quickAr(): array
    {
        return [
            'كيف أضيف مستخدم؟',
            'كيف أنشئ طلب شراء؟',
            'كيف أضيف صنف؟',
            'كيف أعمل جرد للمخزون؟',
            'كيف أصدر فاتورة؟',
            'كيف أستخدم نقطة البيع؟',
            'كيف أوافق على طلب؟',
            'كيف أستخدم الحسابات؟',
        ];
    }

    /** @return list<string> */
    private function quickEn(): array
    {
        return [
            'How do I add a user?',
            'How do I create a purchase request?',
            'How do I add a product?',
            'How do I perform inventory counting?',
            'How do I create an invoice?',
            'How do I use POS?',
            'How do I approve a request?',
            'How do I use accounting?',
        ];
    }
}
