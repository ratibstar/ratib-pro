<?php
declare(strict_types=1);

namespace Rateb\App\Website\Theme;

use Rateb\App\Core\Database;
use Rateb\App\Website\TenantWebsiteRepository;

/** Phase WEBSITE-05 — Export installed theme as portable JSON package. */
final class ThemeExportService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /**
     * @return array<string, mixed>
     */
    public function exportInstalled(int $installedId): array
    {
        $catalog = new ThemeCatalogService($this->repo);
        $row = $catalog->findInstalled($installedId);
        if ($row === null) {
            throw new \RuntimeException('Installed theme not found');
        }
        $slug = (string) ($row['package_slug'] ?? '');
        $manifestArr = [];
        try {
            $pkg = ThemePackage::load($slug);
            $manifestArr = $pkg->manifest()->toArray();
        } catch (\Throwable $e) {
            $manifestArr = [
                'slug' => $slug,
                'name_en' => (string) ($row['name_en'] ?? $slug),
                'name_ar' => (string) ($row['name_ar'] ?? ''),
                'version' => (string) ($row['package_version'] ?? '1.0.0'),
                'engine' => ThemeManifest::ENGINE,
                'engine_min' => ThemeManifest::ENGINE_MIN,
                'tokens' => [],
            ];
        }
        $override = (new ThemeOverrideService($this->repo))->get($installedId);
        // Bake overrides into export tokens so import restores customization as new base.
        if (isset($override['tokens']) && is_array($override['tokens'])) {
            $manifestArr['tokens'] = array_replace_recursive($manifestArr['tokens'] ?? [], $override['tokens']);
        }

        $cid = $this->repo->companyId();
        $assetsOut = [];
        $assets = $this->repo->fetchAll(
            'SELECT * FROM rateb_website_theme_assets WHERE company_id = :cid AND installed_id = :iid',
            ['cid' => $cid, 'iid' => $installedId]
        );
        foreach ($assets as $asset) {
            $rel = (string) ($asset['file_path'] ?? '');
            $abs = $this->absoluteFromStorage($rel);
            $b64 = '';
            if ($abs !== null && is_file($abs)) {
                $bin = file_get_contents($abs);
                $b64 = $bin !== false ? base64_encode($bin) : '';
            }
            $assetsOut[] = [
                'key' => (string) $asset['asset_key'],
                'type' => (string) $asset['asset_type'],
                'filename' => basename($rel),
                'data_base64' => $b64,
            ];
        }

        (new ThemeBackupService($this->repo))->backup($installedId, 'Export snapshot', 'export');

        return [
            'format' => 'rateb-theme-package-v1',
            'exported_at' => date('c'),
            'company_id' => $cid,
            'manifest' => $manifestArr,
            'override' => $override,
            'assets' => $assetsOut,
            'demo' => $manifestArr['demo'] ?? [],
        ];
    }

    private function absoluteFromStorage(string $relative): ?string
    {
        $rel = str_replace('\\', '/', ltrim($relative, '/'));
        if (str_starts_with($rel, 'storage/')) {
            $rel = substr($rel, strlen('storage/'));
        }
        if ($rel === '' || str_contains($rel, '..')) {
            return null;
        }
        $full = rtrim((string) RATEB_STORAGE_PATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

        return is_file($full) ? $full : null;
    }
}
