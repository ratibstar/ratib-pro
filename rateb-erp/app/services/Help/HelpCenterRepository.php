<?php
declare(strict_types=1);

namespace Rateb\App\Services\Help;

/**
 * Read API for Help Center content (file catalog first; DB overlay ready for admin CMS).
 */
final class HelpCenterRepository
{
    private HelpPermissionGate $gate;

    public function __construct(?HelpPermissionGate $gate = null)
    {
        $this->gate = $gate ?? new HelpPermissionGate();
    }

    public function gate(): HelpPermissionGate
    {
        return $this->gate;
    }

    /** @return list<array<string,mixed>> */
    public function modulesForUser(): array
    {
        $locale = function_exists('rateb_locale') ? rateb_locale() : 'ar';
        $out = [];
        $counts = $this->articleCountsByModule();
        foreach (HelpContentBuilder::modules() as $module) {
            $gate = isset($module['module_gate']) ? (string) $module['module_gate'] : null;
            if (!$this->gate->canSeeModule($gate !== '' ? $gate : null)) {
                continue;
            }
            $audience = (string) ($module['audience'] ?? 'all');
            if (!$this->gate->canSeeAudience($audience)) {
                continue;
            }
            $slug = (string) ($module['slug'] ?? '');
            $out[] = [
                'slug' => $slug,
                'icon' => (string) ($module['icon'] ?? 'fa-circle-question'),
                'accent' => (string) ($module['accent'] ?? 'sky'),
                'title' => $locale === 'en'
                    ? (string) ($module['title_en'] ?? $module['title_ar'] ?? $slug)
                    : (string) ($module['title_ar'] ?? $module['title_en'] ?? $slug),
                'description' => $locale === 'en'
                    ? (string) ($module['desc_en'] ?? $module['desc_ar'] ?? '')
                    : (string) ($module['desc_ar'] ?? $module['desc_en'] ?? ''),
                'article_count' => (int) ($counts[$slug] ?? 0),
                'audience' => $audience,
            ];
        }

        return $out;
    }

    /** @return array<string,mixed>|null */
    public function module(string $slug): ?array
    {
        $raw = HelpContentBuilder::modulesBySlug()[$slug] ?? null;
        if ($raw === null) {
            return null;
        }
        $gate = isset($raw['module_gate']) ? (string) $raw['module_gate'] : null;
        if (!$this->gate->canSeeModule($gate !== '' ? $gate : null)) {
            return null;
        }
        if (!$this->gate->canSeeAudience((string) ($raw['audience'] ?? 'all'))) {
            return null;
        }
        $locale = function_exists('rateb_locale') ? rateb_locale() : 'ar';
        $bp = HelpContentBuilder::blueprints()[$slug] ?? [];

        return [
            'slug' => $slug,
            'icon' => (string) ($raw['icon'] ?? 'fa-circle-question'),
            'accent' => (string) ($raw['accent'] ?? 'sky'),
            'title' => $locale === 'en'
                ? (string) ($raw['title_en'] ?? $raw['title_ar'] ?? $slug)
                : (string) ($raw['title_ar'] ?? $raw['title_en'] ?? $slug),
            'description' => $locale === 'en'
                ? (string) ($raw['desc_en'] ?? $raw['desc_ar'] ?? '')
                : (string) ($raw['desc_ar'] ?? $raw['desc_en'] ?? ''),
            'overview' => $locale === 'en'
                ? (string) ($bp['overview_en'] ?? $bp['overview_ar'] ?? '')
                : (string) ($bp['overview_ar'] ?? $bp['overview_en'] ?? ''),
            'start_here' => $locale === 'en'
                ? array_values(array_map('strval', $bp['start_en'] ?? []))
                : array_values(array_map('strval', $bp['start_ar'] ?? [])),
            'flow' => $locale === 'en'
                ? array_values(array_map('strval', $bp['flow_en'] ?? []))
                : array_values(array_map('strval', $bp['flow_ar'] ?? [])),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function articlesForModule(string $moduleSlug): array
    {
        $out = [];
        foreach (HelpContentBuilder::articles() as $article) {
            if ((string) ($article['module'] ?? '') !== $moduleSlug) {
                continue;
            }
            if ((string) ($article['status'] ?? '') !== 'published') {
                continue;
            }
            if (!$this->gate->canSeeAudience((string) ($article['audience'] ?? 'all'))) {
                continue;
            }
            $out[] = $this->presentArticleCard($article);
        }

        return $out;
    }

    /** @return array<string,mixed>|null */
    public function article(string $slug): ?array
    {
        foreach (HelpContentBuilder::articles() as $article) {
            if ((string) ($article['slug'] ?? '') !== $slug) {
                continue;
            }
            if ((string) ($article['status'] ?? '') !== 'published') {
                return null;
            }
            if (!$this->gate->canSeeAudience((string) ($article['audience'] ?? 'all'))) {
                return null;
            }
            $moduleSlug = (string) ($article['module'] ?? '');
            $module = $this->module($moduleSlug);
            if ($module === null && $moduleSlug !== '') {
                // Module soft-hidden — still allow article if audience ok and no hard gate fail.
                $rawMod = HelpContentBuilder::modulesBySlug()[$moduleSlug] ?? null;
                if ($rawMod !== null) {
                    $gate = isset($rawMod['module_gate']) ? (string) $rawMod['module_gate'] : null;
                    if (!$this->gate->canSeeModule($gate !== '' ? $gate : null)) {
                        return null;
                    }
                }
            }

            return $this->presentArticleFull($article, $module);
        }

        return null;
    }

    /**
     * Compact search index for client-side instant search.
     *
     * @return list<array<string,mixed>>
     */
    public function searchIndex(): array
    {
        $locale = function_exists('rateb_locale') ? rateb_locale() : 'ar';
        $modules = HelpContentBuilder::modulesBySlug();
        $out = [];
        foreach (HelpContentBuilder::articles() as $article) {
            if ((string) ($article['status'] ?? '') !== 'published') {
                continue;
            }
            if (!$this->gate->canSeeAudience((string) ($article['audience'] ?? 'all'))) {
                continue;
            }
            $moduleSlug = (string) ($article['module'] ?? '');
            $mod = $modules[$moduleSlug] ?? null;
            if ($mod !== null) {
                $gate = isset($mod['module_gate']) ? (string) $mod['module_gate'] : null;
                if (!$this->gate->canSeeModule($gate !== '' ? $gate : null)) {
                    continue;
                }
            }
            $out[] = [
                'slug' => (string) ($article['slug'] ?? ''),
                'module' => $moduleSlug,
                'title' => $locale === 'en'
                    ? (string) ($article['title_en'] ?? $article['title_ar'] ?? '')
                    : (string) ($article['title_ar'] ?? $article['title_en'] ?? ''),
                'summary' => $locale === 'en'
                    ? (string) ($article['summary_en'] ?? $article['summary_ar'] ?? '')
                    : (string) ($article['summary_ar'] ?? $article['summary_en'] ?? ''),
                'module_title' => $mod === null
                    ? $moduleSlug
                    : ($locale === 'en'
                        ? (string) ($mod['title_en'] ?? $mod['title_ar'] ?? $moduleSlug)
                        : (string) ($mod['title_ar'] ?? $mod['title_en'] ?? $moduleSlug)),
                'keywords' => array_values(array_map('strval', $article['keywords'] ?? [])),
                'difficulty' => (string) ($article['difficulty'] ?? 'beginner'),
                'minutes' => (int) ($article['minutes'] ?? 3),
                'icon' => (string) ($article['icon'] ?? 'fa-circle-question'),
                'type' => 'article',
            ];
        }
        foreach ($this->modulesForUser() as $module) {
            $out[] = [
                'slug' => (string) $module['slug'],
                'module' => (string) $module['slug'],
                'title' => (string) $module['title'],
                'summary' => (string) $module['description'],
                'module_title' => (string) $module['title'],
                'keywords' => [(string) $module['title'], (string) $module['slug']],
                'difficulty' => '',
                'minutes' => 0,
                'icon' => (string) $module['icon'],
                'type' => 'module',
            ];
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    public function faqs(?string $moduleSlug = null): array
    {
        $locale = function_exists('rateb_locale') ? rateb_locale() : 'ar';
        $out = [];
        foreach (HelpContentBuilder::faqs() as $faq) {
            $mod = $faq['module'] ?? null;
            if ($moduleSlug !== null && $mod !== null && (string) $mod !== $moduleSlug) {
                continue;
            }
            if ($moduleSlug !== null && $mod === null) {
                // include global FAQs on module pages too
            }
            $out[] = [
                'id' => (string) ($faq['id'] ?? ''),
                'module' => $mod,
                'question' => $locale === 'en'
                    ? (string) ($faq['q_en'] ?? $faq['q_ar'] ?? '')
                    : (string) ($faq['q_ar'] ?? $faq['q_en'] ?? ''),
                'answer' => $locale === 'en'
                    ? (string) ($faq['a_en'] ?? $faq['a_ar'] ?? '')
                    : (string) ($faq['a_ar'] ?? $faq['a_en'] ?? ''),
            ];
        }

        return $out;
    }

    /** @return array<string,int> */
    private function articleCountsByModule(): array
    {
        $counts = [];
        foreach (HelpContentBuilder::articles() as $article) {
            if ((string) ($article['status'] ?? '') !== 'published') {
                continue;
            }
            if (!$this->gate->canSeeAudience((string) ($article['audience'] ?? 'all'))) {
                continue;
            }
            $m = (string) ($article['module'] ?? '');
            if ($m === '') {
                continue;
            }
            $counts[$m] = ($counts[$m] ?? 0) + 1;
        }

        return $counts;
    }

    /** @param array<string,mixed> $article @return array<string,mixed> */
    private function presentArticleCard(array $article): array
    {
        $locale = function_exists('rateb_locale') ? rateb_locale() : 'ar';
        $slug = (string) ($article['slug'] ?? '');
        $module = (string) ($article['module'] ?? '');

        return [
            'slug' => $slug,
            'module' => $module,
            'accent' => $this->resolveArticleAccent($module, $slug),
            'title' => $locale === 'en'
                ? (string) ($article['title_en'] ?? $article['title_ar'] ?? '')
                : (string) ($article['title_ar'] ?? $article['title_en'] ?? ''),
            'summary' => $locale === 'en'
                ? (string) ($article['summary_en'] ?? $article['summary_ar'] ?? '')
                : (string) ($article['summary_ar'] ?? $article['summary_en'] ?? ''),
            'difficulty' => (string) ($article['difficulty'] ?? 'beginner'),
            'minutes' => (int) ($article['minutes'] ?? 3),
            'icon' => (string) ($article['icon'] ?? 'fa-circle-question'),
        ];
    }

    /**
     * Soft modern color mix: module accent as base + per-article offset for a varied grid.
     */
    private function resolveArticleAccent(string $moduleSlug, string $articleSlug): string
    {
        static $palette = [
            'sky', 'teal', 'amber', 'blue', 'emerald', 'orange', 'pink',
            'cyan', 'violet', 'indigo', 'purple', 'green', 'rose', 'fuchsia', 'yellow',
        ];
        $moduleAccent = (string) (HelpContentBuilder::modulesBySlug()[$moduleSlug]['accent'] ?? 'sky');
        $base = array_search($moduleAccent, $palette, true);
        if ($base === false) {
            $base = 0;
        }
        $offset = abs((int) crc32($articleSlug !== '' ? $articleSlug : $moduleSlug)) % count($palette);

        return $palette[($base + $offset) % count($palette)];
    }

    /**
     * @param array<string,mixed> $article
     * @param array<string,mixed>|null $module
     * @return array<string,mixed>
     */
    private function presentArticleFull(array $article, ?array $module): array
    {
        $locale = function_exists('rateb_locale') ? rateb_locale() : 'ar';
        $sections = is_array($article['sections'] ?? null) ? $article['sections'] : [];
        $pick = static function (array $node) use ($locale): mixed {
            if ($locale === 'en') {
                return $node['en'] ?? $node['ar'] ?? null;
            }

            return $node['ar'] ?? $node['en'] ?? null;
        };

        $related = [];
        foreach (array_map('strval', $article['related'] ?? []) as $relSlug) {
            foreach (HelpContentBuilder::articles() as $other) {
                if ((string) ($other['slug'] ?? '') === $relSlug
                    && (string) ($other['status'] ?? '') === 'published'
                    && $this->gate->canSeeAudience((string) ($other['audience'] ?? 'all'))
                ) {
                    $related[] = $this->presentArticleCard($other);
                    break;
                }
            }
        }

        $siblings = $this->articlesForModule((string) ($article['module'] ?? ''));
        $prev = null;
        $next = null;
        $slug = (string) ($article['slug'] ?? '');
        foreach ($siblings as $i => $sib) {
            if ((string) ($sib['slug'] ?? '') === $slug) {
                $prev = $siblings[$i - 1] ?? null;
                $next = $siblings[$i + 1] ?? null;
                break;
            }
        }

        $moduleSlug = (string) ($article['module'] ?? '');

        return [
            'slug' => $slug,
            'module' => $moduleSlug,
            'accent' => $this->resolveArticleAccent($moduleSlug, $slug),
            'module_meta' => $module,
            'title' => $locale === 'en'
                ? (string) ($article['title_en'] ?? $article['title_ar'] ?? '')
                : (string) ($article['title_ar'] ?? $article['title_en'] ?? ''),
            'summary' => $locale === 'en'
                ? (string) ($article['summary_en'] ?? $article['summary_ar'] ?? '')
                : (string) ($article['summary_ar'] ?? $article['summary_en'] ?? ''),
            'difficulty' => (string) ($article['difficulty'] ?? 'beginner'),
            'minutes' => (int) ($article['minutes'] ?? 3),
            'icon' => (string) ($article['icon'] ?? 'fa-circle-question'),
            'what' => (string) $pick(is_array($sections['what'] ?? null) ? $sections['what'] : []),
            'when' => (string) $pick(is_array($sections['when'] ?? null) ? $sections['when'] : []),
            'steps' => array_values(array_map('strval', (array) $pick(is_array($sections['steps'] ?? null) ? $sections['steps'] : []))),
            'example' => (string) $pick(is_array($sections['example'] ?? null) ? $sections['example'] : []),
            'tips' => array_values(array_map('strval', (array) $pick(is_array($sections['tips'] ?? null) ? $sections['tips'] : []))),
            'mistakes' => array_values(array_map('strval', (array) $pick(is_array($sections['mistakes'] ?? null) ? $sections['mistakes'] : []))),
            'related' => $related,
            'prev' => $prev,
            'next' => $next,
        ];
    }
}
