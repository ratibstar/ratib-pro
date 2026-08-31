<?php
declare(strict_types=1);

namespace Rateb\App\Services\Help;

/**
 * Context-aware Help suggestions based on current ERP route.
 */
final class HelpContextService
{
    private HelpCenterRepository $repo;

    public function __construct(?HelpCenterRepository $repo = null)
    {
        $this->repo = $repo ?? new HelpCenterRepository();
    }

    /**
     * @return array{module:?array<string,mixed>,suggestions:list<array<string,mixed>>,faqs:list<array<string,mixed>>}
     */
    public function forRoute(string $erpRoute): array
    {
        $route = trim(str_replace('\\', '/', $erpRoute), '/');
        $moduleSlug = $this->resolveModuleSlug($route);
        $module = $moduleSlug !== null ? $this->repo->module($moduleSlug) : null;
        $suggestions = [];
        if ($moduleSlug !== null) {
            $suggestions = array_slice($this->repo->articlesForModule($moduleSlug), 0, 6);
        }
        if ($suggestions === []) {
            // Fallback: popular beginner articles across visible modules.
            foreach ($this->repo->modulesForUser() as $mod) {
                foreach ($this->repo->articlesForModule((string) $mod['slug']) as $article) {
                    if (($article['difficulty'] ?? '') === 'beginner') {
                        $suggestions[] = $article;
                    }
                    if (count($suggestions) >= 5) {
                        break 2;
                    }
                }
            }
        }

        return [
            'module' => $module,
            'suggestions' => $suggestions,
            'faqs' => $this->repo->faqs($moduleSlug),
        ];
    }

    public function resolveModuleSlug(string $route): ?string
    {
        $route = mb_strtolower(trim(str_replace('\\', '/', $route), '/'));
        if ($route === '' || $route === 'admin') {
            return 'dashboard';
        }

        $candidates = [];
        foreach (HelpContentBuilder::modules() as $module) {
            $slug = (string) ($module['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            if (!$this->repo->gate()->canSeeCatalogModule($module)) {
                continue;
            }
            $hints = array_map('strval', $module['route_hints'] ?? []);
            foreach ($hints as $hint) {
                $hint = mb_strtolower(trim(str_replace('\\', '/', $hint), '/'));
                if ($hint === '' || $hint === 'admin') {
                    continue;
                }
                if ($this->routeMatchesHint($route, $hint)) {
                    $candidates[] = ['slug' => $slug, 'len' => mb_strlen($hint)];
                }
            }
        }

        if ($candidates === []) {
            return null;
        }
        usort($candidates, static fn (array $a, array $b): int => $b['len'] <=> $a['len']);

        return (string) $candidates[0]['slug'];
    }

    private function routeMatchesHint(string $route, string $hint): bool
    {
        if ($route === $hint) {
            return true;
        }
        // Segment-aware: ".../purchase-orders" or ".../purchase-orders/..."
        if (preg_match('#(?:^|/)' . preg_quote($hint, '#') . '(?:/|$)#', $route) === 1) {
            return true;
        }
        // Allow trailing slash variants already normalized.
        return str_ends_with($route, '/' . $hint);
    }
}
