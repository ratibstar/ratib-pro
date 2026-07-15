<?php
declare(strict_types=1);

namespace Rateb\App\Website\Theme;

use Rateb\App\Website\TenantWebsiteRepository;
use Rateb\App\Website\WebsiteThemeEditorService;

/**
 * Phase WEBSITE-05 — Inheritance: Base Theme → Agency Override → Runtime.
 * Does not duplicate package files; merges tokens only.
 */
final class ThemeResolver
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /**
     * @return array{tokens:array<string,mixed>,logo_path:string,favicon_path:string,installed_id:?int,package_slug:?string,source:string}
     */
    public function resolveRuntime(?bool $preferPreview = null): array
    {
        $preferPreview = $preferPreview ?? $this->isPreviewRequest();
        $catalog = new ThemeCatalogService($this->repo);
        $installed = null;
        if ($preferPreview) {
            $installed = $catalog->previewInstalled() ?: $catalog->activeInstalled();
        } else {
            $installed = $catalog->activeInstalled();
        }
        if ($installed === null) {
            $defaults = (new WebsiteThemeEditorService($this->repo))->defaultTokens();
            $theme = (new \Rateb\App\Website\TenantThemeService($this->repo))->theme();
            $raw = $theme['tokens_json'] ?? null;
            $tokens = $defaults;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $tokens = array_replace_recursive($defaults, $decoded);
                }
            }

            return [
                'tokens' => $tokens,
                'logo_path' => (string) ($theme['logo_path'] ?? ''),
                'favicon_path' => (string) ($theme['favicon_path'] ?? ''),
                'installed_id' => null,
                'package_slug' => null,
                'source' => 'legacy',
            ];
        }

        return $this->resolveForInstalled((int) $installed['id']);
    }

    /**
     * @return array{tokens:array<string,mixed>,logo_path:string,favicon_path:string,installed_id:int,package_slug:string,source:string}
     */
    public function resolveForInstalled(int $installedId): array
    {
        $cid = $this->repo->companyId();
        $cacheKey = $cid . ':' . $installedId;
        if (isset(self::$cache[$cacheKey])) {
            /** @var array{tokens:array<string,mixed>,logo_path:string,favicon_path:string,installed_id:int,package_slug:string,source:string} */
            return self::$cache[$cacheKey];
        }

        $catalog = new ThemeCatalogService($this->repo);
        $row = $catalog->findInstalled($installedId);
        if ($row === null) {
            throw new \RuntimeException('Installed theme not found');
        }

        $defaults = (new WebsiteThemeEditorService($this->repo))->defaultTokens();
        $baseTokens = $defaults;
        $packageSlug = (string) ($row['package_slug'] ?? '');
        try {
            $pkg = ThemePackage::load($packageSlug);
            $baseTokens = array_replace_recursive($defaults, $pkg->manifest()->tokens());
        } catch (\Throwable $e) {
            // Imported/custom without disk package: rely on overrides only.
        }
        $override = (new ThemeOverrideService($this->repo))->get($installedId);
        $overrideTokens = is_array($override['tokens'] ?? null) ? $override['tokens'] : [];
        // Allow flat override keys for convenience.
        foreach (['colors', 'typography', 'buttons', 'cards', 'radius', 'shadows', 'icons', 'header', 'footer', 'layout', 'direction'] as $k) {
            if (isset($override[$k]) && is_array($override[$k])) {
                $overrideTokens[$k] = array_replace_recursive($overrideTokens[$k] ?? [], $override[$k]);
            } elseif (isset($override[$k]) && $k === 'direction') {
                $overrideTokens['direction'] = $override[$k];
            }
        }
        $tokens = array_replace_recursive($baseTokens, $overrideTokens);

        $resolved = [
            'tokens' => $tokens,
            'logo_path' => (string) ($override['logo_path'] ?? ''),
            'favicon_path' => (string) ($override['favicon_path'] ?? ''),
            'installed_id' => $installedId,
            'package_slug' => $packageSlug,
            'source' => (string) ($row['source'] ?? 'marketplace'),
        ];
        self::$cache[$cacheKey] = $resolved;

        return $resolved;
    }

    private function isPreviewRequest(): bool
    {
        $path = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (str_contains($path, '/site/preview/')) {
            return true;
        }

        return isset($_GET['theme_preview']) && (string) $_GET['theme_preview'] === '1';
    }
}
