<?php
declare(strict_types=1);

namespace Rateb\App\Website\Theme;

use Rateb\App\Website\TenantWebsiteRepository;

/** Phase WEBSITE-05 — Marketplace facade for company controllers. */
final class ThemeMarketplaceService
{
    private TenantWebsiteRepository $repo;
    private ThemeCatalogService $catalog;
    private ThemeInstaller $installer;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
        $this->catalog = new ThemeCatalogService($this->repo);
        $this->installer = new ThemeInstaller($this->repo);
    }

    /** @return array{available:list<array<string,mixed>>,installed:list<array<string,mixed>>,active:?array<string,mixed>,preview:?array<string,mixed>} */
    public function dashboard(): array
    {
        $this->catalog->syncCatalogFromDisk();

        return [
            'available' => $this->catalog->availablePackages(),
            'installed' => $this->catalog->installed(),
            'active' => $this->catalog->activeInstalled(),
            'preview' => $this->catalog->previewInstalled(),
        ];
    }

    /** @return array{ok:bool,installed_id?:int,errors?:list<string>,warnings?:list<string>} */
    public function install(string $slug): array
    {
        return $this->installer->install($slug);
    }

    public function activate(int $id): void
    {
        $this->installer->activate($id);
    }

    public function preview(int $id): void
    {
        $this->installer->preview($id);
    }

    public function clearPreview(): void
    {
        $this->installer->clearPreview();
    }

    public function duplicate(int $id, ?string $name = null): int
    {
        return $this->installer->duplicate($id, $name);
    }

    public function reset(int $id): void
    {
        (new ThemeBackupService($this->repo))->backup($id, 'Before reset', 'restore_point');
        $this->installer->reset($id);
    }

    public function delete(int $id): void
    {
        $this->installer->delete($id);
    }

    /** @return array<string, mixed> */
    public function export(int $id): array
    {
        return (new ThemeExportService($this->repo))->exportInstalled($id);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok:bool,installed_id?:int,errors?:list<string>,warnings?:list<string>}
     */
    public function import(array $payload): array
    {
        return (new ThemeImportService($this->repo))->import($payload);
    }

    /** @return array{ok:bool,message:string,stats:array<string,int>} */
    public function importDemo(int $id): array
    {
        return (new ThemeDemoImportService($this->repo))->importForInstalled($id);
    }

    /**
     * @param array<string, mixed> $override
     */
    public function saveOverride(int $id, array $override): void
    {
        (new ThemeBackupService($this->repo))->backup($id, 'Before customize', 'restore_point');
        (new ThemeOverrideService($this->repo))->save($id, $override);
        $row = $this->catalog->findInstalled($id);
        if ($row && ($row['status'] ?? '') === 'active') {
            $this->installer->activate($id);
        } elseif ($row && ($row['status'] ?? '') === 'preview') {
            $this->installer->preview($id);
        }
    }

    /** @return array<string, mixed> */
    public function getOverride(int $id): array
    {
        return (new ThemeOverrideService($this->repo))->get($id);
    }

    public function backup(int $id, string $label = 'Backup'): int
    {
        return (new ThemeBackupService($this->repo))->backup($id, $label);
    }

    public function restore(int $versionId): void
    {
        (new ThemeBackupService($this->repo))->restore($versionId);
    }

    /** @return list<array<string, mixed>> */
    public function backups(int $id): array
    {
        return (new ThemeBackupService($this->repo))->listFor($id);
    }

    public function validatePackage(string $slug): array
    {
        $pkg = ThemePackage::load($slug);

        return (new ThemeValidator())->validatePackage($pkg);
    }
}
