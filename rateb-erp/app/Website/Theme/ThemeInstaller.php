<?php
declare(strict_types=1);

namespace Rateb\App\Website\Theme;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\AuditService;
use Rateb\App\Website\TenantThemeService;
use Rateb\App\Website\TenantWebsiteRepository;
use Rateb\App\Website\WebsiteThemeEditorService;

/** Phase WEBSITE-05 — Install / activate / preview / duplicate / delete / reset. */
final class ThemeInstaller
{
    private TenantWebsiteRepository $repo;
    private ThemeCatalogService $catalog;
    private ThemeValidator $validator;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
        $this->catalog = new ThemeCatalogService($this->repo);
        $this->validator = new ThemeValidator();
    }

    /**
     * @return array{ok:bool,installed_id?:int,errors?:list<string>,warnings?:list<string>}
     */
    public function install(string $packageSlug, ?string $installKey = null): array
    {
        $pkg = ThemePackage::load($packageSlug);
        $result = $this->validator->validatePackage($pkg);
        if (!$result['ok']) {
            return ['ok' => false, 'errors' => $result['errors'], 'warnings' => $result['warnings']];
        }
        $m = $pkg->manifest();
        $cid = $this->repo->companyId();
        $key = $installKey ?: ($m->slug() . '-' . substr(bin2hex(random_bytes(4)), 0, 8));
        $key = preg_replace('/[^a-z0-9\-]+/', '-', strtolower($key)) ?: $m->slug();

        $existing = $this->repo->fetchOne(
            'SELECT id FROM rateb_website_theme_installed WHERE company_id = :cid AND install_key = :k LIMIT 1',
            ['cid' => $cid, 'k' => $key]
        );
        if ($existing) {
            return ['ok' => true, 'installed_id' => (int) $existing['id'], 'warnings' => ['Already installed']];
        }

        $packageRow = $this->catalog->packageBySlug($m->slug());
        $db = Database::connection();
        $db->prepare(
            'INSERT INTO rateb_website_theme_installed
             (company_id, package_id, package_slug, install_key, name_en, name_ar, source, status, package_version)
             VALUES (:cid, :pid, :pslug, :ikey, :name_en, :name_ar, \'marketplace\', \'installed\', :ver)'
        )->execute([
            'cid' => $cid,
            'pid' => $packageRow ? (int) ($packageRow['id'] ?? 0) ?: null : null,
            'pslug' => $m->slug(),
            'ikey' => $key,
            'name_en' => $m->nameEn(),
            'name_ar' => $m->nameAr(),
            'ver' => $m->version(),
        ]);
        $installedId = (int) $db->lastInsertId();

        $this->copyPackageAssets($pkg, $installedId);
        $this->ensureOverrideRow($installedId, []);

        (new AuditService())->log('website_theme_install', 'website_theme', $installedId, [
            'company_id' => $cid,
            'package' => $m->slug(),
        ]);

        return ['ok' => true, 'installed_id' => $installedId, 'warnings' => $result['warnings']];
    }

    public function activate(int $installedId): void
    {
        $row = $this->catalog->findInstalled($installedId);
        if ($row === null) {
            throw new \RuntimeException('Installed theme not found');
        }
        $cid = $this->repo->companyId();
        $this->repo->execute(
            "UPDATE rateb_website_theme_installed SET status = 'installed' WHERE company_id = :cid AND status IN ('active','preview')",
            ['cid' => $cid]
        );
        $this->repo->execute(
            "UPDATE rateb_website_theme_installed SET status = 'active', activated_at = NOW() WHERE id = :id AND company_id = :cid",
            ['id' => $installedId, 'cid' => $cid]
        );
        $this->applyResolvedToCmsTheme($installedId, false);
        (new AuditService())->log('website_theme_activate', 'website_theme', $installedId, ['company_id' => $cid]);
    }

    public function preview(int $installedId): void
    {
        $row = $this->catalog->findInstalled($installedId);
        if ($row === null) {
            throw new \RuntimeException('Installed theme not found');
        }
        $cid = $this->repo->companyId();
        $this->repo->execute(
            "UPDATE rateb_website_theme_installed SET status = 'installed' WHERE company_id = :cid AND status = 'preview'",
            ['cid' => $cid]
        );
        // Keep current active; mark this as preview.
        if (($row['status'] ?? '') !== 'active') {
            $this->repo->execute(
                "UPDATE rateb_website_theme_installed SET status = 'preview' WHERE id = :id AND company_id = :cid",
                ['id' => $installedId, 'cid' => $cid]
            );
        }
        $this->applyResolvedToCmsTheme($installedId, true);
    }

    public function clearPreview(): void
    {
        $cid = $this->repo->companyId();
        $this->repo->execute(
            "UPDATE rateb_website_theme_installed SET status = 'installed' WHERE company_id = :cid AND status = 'preview'",
            ['cid' => $cid]
        );
        $active = $this->catalog->activeInstalled();
        if ($active) {
            $this->applyResolvedToCmsTheme((int) $active['id'], false);
        } else {
            $this->clearThemePointers();
            TenantThemeService::clearCache();
        }
    }

    public function duplicate(int $installedId, ?string $newName = null): int
    {
        $row = $this->catalog->findInstalled($installedId);
        if ($row === null) {
            throw new \RuntimeException('Installed theme not found');
        }
        $cid = $this->repo->companyId();
        $key = ($row['package_slug'] ?? 'theme') . '-copy-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $db = Database::connection();
        $db->prepare(
            'INSERT INTO rateb_website_theme_installed
             (company_id, package_id, package_slug, install_key, name_en, name_ar, source, status, package_version, parent_installed_id)
             VALUES (:cid, :pid, :pslug, :ikey, :name_en, :name_ar, \'duplicate\', \'installed\', :ver, :parent)'
        )->execute([
            'cid' => $cid,
            'pid' => $row['package_id'] ?? null,
            'pslug' => (string) $row['package_slug'],
            'ikey' => $key,
            'name_en' => $newName ?: ((string) ($row['name_en'] ?? 'Theme') . ' Copy'),
            'name_ar' => (string) ($row['name_ar'] ?? ''),
            'ver' => (string) ($row['package_version'] ?? '1.0.0'),
            'parent' => $installedId,
        ]);
        $newId = (int) $db->lastInsertId();

        // Copy overrides only (package remains shared — no full theme duplication).
        $override = (new ThemeOverrideService($this->repo))->get($installedId);
        $this->ensureOverrideRow($newId, $override);

        $assets = $this->repo->fetchAll(
            'SELECT * FROM rateb_website_theme_assets WHERE company_id = :cid AND installed_id = :iid',
            ['cid' => $cid, 'iid' => $installedId]
        );
        foreach ($assets as $asset) {
            $this->repo->execute(
                'INSERT INTO rateb_website_theme_assets (company_id, installed_id, asset_key, asset_type, file_path, checksum)
                 VALUES (:cid, :iid, :akey, :atype, :path, :sum)',
                [
                    'cid' => $cid,
                    'iid' => $newId,
                    'akey' => (string) $asset['asset_key'],
                    'atype' => (string) $asset['asset_type'],
                    'path' => (string) $asset['file_path'],
                    'sum' => $asset['checksum'] ?? null,
                ]
            );
        }

        return $newId;
    }

    public function reset(int $installedId): void
    {
        $row = $this->catalog->findInstalled($installedId);
        if ($row === null) {
            throw new \RuntimeException('Installed theme not found');
        }
        (new ThemeOverrideService($this->repo))->save($installedId, []);
        if (($row['status'] ?? '') === 'active' || ($row['status'] ?? '') === 'preview') {
            $this->applyResolvedToCmsTheme($installedId, ($row['status'] ?? '') === 'preview');
        }
    }

    public function delete(int $installedId): void
    {
        $row = $this->catalog->findInstalled($installedId);
        if ($row === null) {
            return;
        }
        if (($row['status'] ?? '') === 'active') {
            throw new \RuntimeException('Cannot delete active theme — activate another first');
        }
        $cid = $this->repo->companyId();
        $this->repo->execute(
            'DELETE FROM rateb_website_theme_overrides WHERE company_id = :cid AND installed_id = :id',
            ['cid' => $cid, 'id' => $installedId]
        );
        $this->repo->execute(
            'DELETE FROM rateb_website_theme_assets WHERE company_id = :cid AND installed_id = :id',
            ['cid' => $cid, 'id' => $installedId]
        );
        $this->repo->execute(
            'DELETE FROM rateb_website_theme_versions WHERE company_id = :cid AND installed_id = :id',
            ['cid' => $cid, 'id' => $installedId]
        );
        $this->repo->execute(
            'DELETE FROM rateb_website_theme_installed WHERE company_id = :cid AND id = :id',
            ['cid' => $cid, 'id' => $installedId]
        );
    }

    private function applyResolvedToCmsTheme(int $installedId, bool $preview): void
    {
        $resolved = (new ThemeResolver($this->repo))->resolveForInstalled($installedId);
        $editor = new WebsiteThemeEditorService($this->repo);
        $editor->save($resolved['tokens'], [
            'logo_path' => (string) ($resolved['logo_path'] ?? ''),
            'favicon_path' => (string) ($resolved['favicon_path'] ?? ''),
            'primary_color' => (string) ($resolved['tokens']['colors']['primary'] ?? ''),
            'secondary_color' => (string) ($resolved['tokens']['colors']['secondary'] ?? ''),
            'font_family' => (string) ($resolved['tokens']['typography']['font_family'] ?? ''),
        ]);
        $cid = $this->repo->companyId();
        $col = $preview ? 'preview_installed_id' : 'active_installed_id';
        try {
            if ($preview) {
                $this->repo->execute(
                    "UPDATE rateb_cms_theme SET preview_installed_id = :iid WHERE company_id = :cid",
                    ['iid' => $installedId, 'cid' => $cid]
                );
            } else {
                $this->repo->execute(
                    "UPDATE rateb_cms_theme SET active_installed_id = :iid, preview_installed_id = NULL WHERE company_id = :cid",
                    ['iid' => $installedId, 'cid' => $cid]
                );
            }
        } catch (\Throwable $e) {
            // Columns may be missing until migration 197.
            error_log('ThemeInstaller pointer: ' . $e->getMessage());
        }
        TenantThemeService::clearCache();
        ThemeResolver::clearCache();
    }

    private function clearThemePointers(): void
    {
        $cid = $this->repo->companyId();
        try {
            $this->repo->execute(
                'UPDATE rateb_cms_theme SET preview_installed_id = NULL WHERE company_id = :cid',
                ['cid' => $cid]
            );
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /** @param array<string, mixed> $override */
    private function ensureOverrideRow(int $installedId, array $override): void
    {
        (new ThemeOverrideService($this->repo))->save($installedId, $override);
    }

    private function copyPackageAssets(ThemePackage $pkg, int $installedId): void
    {
        $cid = $this->repo->companyId();
        $destRoot = rtrim((string) RATEB_STORAGE_PATH, '/\\') . '/cms-media/' . $cid . '/themes/' . $installedId;
        if (!is_dir($destRoot) && !mkdir($destRoot, 0755, true) && !is_dir($destRoot)) {
            return;
        }
        foreach ($pkg->manifest()->assets() as $asset) {
            $rel = (string) ($asset['path'] ?? '');
            $key = (string) ($asset['key'] ?? pathinfo($rel, PATHINFO_FILENAME));
            $src = $pkg->assetAbsolute($rel);
            if ($src === null) {
                continue;
            }
            $name = basename($src);
            $dest = $destRoot . '/' . $name;
            if (!@copy($src, $dest)) {
                continue;
            }
            $publicRel = 'storage/cms-media/' . $cid . '/themes/' . $installedId . '/' . $name;
            $this->repo->execute(
                'INSERT INTO rateb_website_theme_assets (company_id, installed_id, asset_key, asset_type, file_path, checksum)
                 VALUES (:cid, :iid, :akey, :atype, :path, :sum)
                 ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), checksum = VALUES(checksum)',
                [
                    'cid' => $cid,
                    'iid' => $installedId,
                    'akey' => $key,
                    'atype' => (string) ($asset['type'] ?? 'image'),
                    'path' => $publicRel,
                    'sum' => hash_file('sha256', $dest) ?: null,
                ]
            );
        }
    }
}
