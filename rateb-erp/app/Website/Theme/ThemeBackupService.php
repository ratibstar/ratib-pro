<?php
declare(strict_types=1);

namespace Rateb\App\Website\Theme;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Website\TenantWebsiteRepository;

/** Phase WEBSITE-05 — Backup / restore points for installed themes. */
final class ThemeBackupService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    public function backup(int $installedId, string $label = 'Backup', string $kind = 'backup'): int
    {
        $catalog = new ThemeCatalogService($this->repo);
        $row = $catalog->findInstalled($installedId);
        if ($row === null) {
            throw new \RuntimeException('Installed theme not found');
        }
        $cid = $this->repo->companyId();
        $override = (new ThemeOverrideService($this->repo))->get($installedId);
        $assets = $this->repo->fetchAll(
            'SELECT asset_key, asset_type, file_path, checksum FROM rateb_website_theme_assets WHERE company_id = :cid AND installed_id = :iid',
            ['cid' => $cid, 'iid' => $installedId]
        );
        $snapshot = [
            'installed' => $row,
            'override' => $override,
            'assets' => $assets,
        ];
        $max = $this->repo->fetchOne(
            'SELECT COALESCE(MAX(version_no), 0) AS m FROM rateb_website_theme_versions WHERE company_id = :cid AND installed_id = :iid',
            ['cid' => $cid, 'iid' => $installedId]
        );
        $ver = ((int) ($max['m'] ?? 0)) + 1;
        $db = Database::connection();
        $db->prepare(
            'INSERT INTO rateb_website_theme_versions
             (company_id, installed_id, version_no, label, kind, snapshot_json, created_by)
             VALUES (:cid, :iid, :ver, :label, :kind, :snap, :uid)'
        )->execute([
            'cid' => $cid,
            'iid' => $installedId,
            'ver' => $ver,
            'label' => $label,
            'kind' => in_array($kind, ['backup', 'restore_point', 'export'], true) ? $kind : 'backup',
            'snap' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'uid' => (int) (SessionManager::get('rateb_user_id') ?? 0) ?: null,
        ]);

        return (int) $db->lastInsertId();
    }

    public function restore(int $versionId): void
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['id'] = $versionId;
        $row = $this->repo->fetchOne(
            "SELECT * FROM rateb_website_theme_versions WHERE {$where} AND id = :id LIMIT 1",
            $params
        );
        $this->repo->assertRowCompany($row, 'theme_version');
        if ($row === null) {
            throw new \RuntimeException('Backup not found');
        }
        $snap = json_decode((string) ($row['snapshot_json'] ?? ''), true);
        if (!is_array($snap)) {
            throw new \RuntimeException('Invalid backup snapshot');
        }
        $installedId = (int) $row['installed_id'];
        $override = is_array($snap['override'] ?? null) ? $snap['override'] : [];
        (new ThemeOverrideService($this->repo))->save($installedId, $override);
        $installed = (new ThemeCatalogService($this->repo))->findInstalled($installedId);
        if ($installed && in_array(($installed['status'] ?? ''), ['active', 'preview'], true)) {
            (new ThemeInstaller($this->repo))->activate($installedId);
        }
    }

    /** @return list<array<string, mixed>> */
    public function listFor(int $installedId): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['iid'] = $installedId;

        return $this->repo->fetchAll(
            "SELECT id, version_no, label, kind, created_at FROM rateb_website_theme_versions
             WHERE {$where} AND installed_id = :iid ORDER BY version_no DESC",
            $params
        );
    }
}
