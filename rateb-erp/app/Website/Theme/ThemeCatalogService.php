<?php
declare(strict_types=1);

namespace Rateb\App\Website\Theme;

use Rateb\App\Core\Database;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-05 — Catalog sync + installed theme queries (company_id scoped).
 */
final class ThemeCatalogService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** Sync disk packages into DB catalog (idempotent). */
    public function syncCatalogFromDisk(): int
    {
        $n = 0;
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO rateb_website_theme_packages
             (slug, name_en, name_ar, version, engine_min, package_path, manifest_json, preview_image, is_system, is_published)
             VALUES (:slug, :name_en, :name_ar, :version, :engine_min, :package_path, :manifest_json, :preview_image, 1, 1)
             ON DUPLICATE KEY UPDATE name_en=VALUES(name_en), name_ar=VALUES(name_ar), version=VALUES(version),
             engine_min=VALUES(engine_min), package_path=VALUES(package_path), manifest_json=VALUES(manifest_json),
             preview_image=VALUES(preview_image), is_published=1'
        );
        foreach (ThemePackage::discoverSlugs() as $slug) {
            try {
                $pkg = ThemePackage::load($slug);
                $m = $pkg->manifest();
                $preview = (string) ($m->toArray()['preview_image'] ?? '');
                $stmt->execute([
                    'slug' => $m->slug(),
                    'name_en' => $m->nameEn(),
                    'name_ar' => $m->nameAr(),
                    'version' => $m->version(),
                    'engine_min' => $m->engineMin(),
                    'package_path' => 'themes/' . $slug,
                    'manifest_json' => $m->toJson(),
                    'preview_image' => $preview !== '' ? $preview : null,
                ]);
                $n++;
            } catch (\Throwable $e) {
                error_log('ThemeCatalog sync ' . $slug . ': ' . $e->getMessage());
            }
        }

        return $n;
    }

    /** @return list<array<string, mixed>> */
    public function availablePackages(): array
    {
        $this->ensureCatalog();
        try {
            return $this->repo->fetchAll(
                'SELECT * FROM rateb_website_theme_packages WHERE is_published = 1 ORDER BY name_en ASC'
            );
        } catch (\Throwable $e) {
            return $this->availableFromDisk();
        }
    }

    /** @return list<array<string, mixed>> */
    private function availableFromDisk(): array
    {
        $out = [];
        foreach (ThemePackage::discoverSlugs() as $slug) {
            try {
                $pkg = ThemePackage::load($slug);
                $m = $pkg->manifest();
                $out[] = [
                    'id' => 0,
                    'slug' => $m->slug(),
                    'name_en' => $m->nameEn(),
                    'name_ar' => $m->nameAr(),
                    'version' => $m->version(),
                    'package_path' => 'themes/' . $slug,
                    'preview_image' => $m->toArray()['preview_image'] ?? null,
                    'is_system' => 1,
                ];
            } catch (\Throwable $e) {
                // skip
            }
        }

        return $out;
    }

    private function ensureCatalog(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        try {
            $count = $this->repo->fetchOne('SELECT COUNT(*) AS c FROM rateb_website_theme_packages');
            if ((int) ($count['c'] ?? 0) < 1) {
                $this->syncCatalogFromDisk();
            }
        } catch (\Throwable $e) {
            // Migration may not have run yet; disk fallback handles available list.
        }
    }

    /** @return array<string, mixed>|null */
    public function packageBySlug(string $slug): ?array
    {
        $this->ensureCatalog();
        try {
            return $this->repo->fetchOne(
                'SELECT * FROM rateb_website_theme_packages WHERE slug = :s AND is_published = 1 LIMIT 1',
                ['s' => $slug]
            );
        } catch (\Throwable $e) {
            foreach ($this->availableFromDisk() as $row) {
                if (($row['slug'] ?? '') === $slug) {
                    return $row;
                }
            }

            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    public function installed(): array
    {
        [$where, $params] = $this->repo->companyWhere();

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_website_theme_installed WHERE {$where} ORDER BY installed_at DESC",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function findInstalled(int $id): ?array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['id'] = $id;
        $row = $this->repo->fetchOne(
            "SELECT * FROM rateb_website_theme_installed WHERE {$where} AND id = :id LIMIT 1",
            $params
        );
        $this->repo->assertRowCompany($row, 'theme_installed');

        return $row;
    }

    /** @return array<string, mixed>|null */
    public function activeInstalled(): ?array
    {
        [$where, $params] = $this->repo->companyWhere();

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_website_theme_installed WHERE {$where} AND status = 'active' ORDER BY activated_at DESC LIMIT 1",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function previewInstalled(): ?array
    {
        [$where, $params] = $this->repo->companyWhere();

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_website_theme_installed WHERE {$where} AND status = 'preview' ORDER BY id DESC LIMIT 1",
            $params
        );
    }
}
