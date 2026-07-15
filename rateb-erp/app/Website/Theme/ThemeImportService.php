<?php
declare(strict_types=1);

namespace Rateb\App\Website\Theme;

use Rateb\App\Core\Database;
use Rateb\App\Services\AuditService;
use Rateb\App\Website\TenantWebsiteRepository;

/** Phase WEBSITE-05 — Import portable theme package into tenant (company_id stamped). */
final class ThemeImportService
{
    private TenantWebsiteRepository $repo;
    private ThemeValidator $validator;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
        $this->validator = new ThemeValidator();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok:bool,installed_id?:int,errors?:list<string>,warnings?:list<string>}
     */
    public function import(array $payload): array
    {
        $check = $this->validator->validateImportPayload($payload);
        if (!$check['ok']) {
            return ['ok' => false, 'errors' => $check['errors'], 'warnings' => $check['warnings']];
        }
        $manifest = ThemeManifest::fromArray($payload['manifest']);
        $cid = $this->repo->companyId();
        $key = 'import-' . $manifest->slug() . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
        $db = Database::connection();
        $db->prepare(
            'INSERT INTO rateb_website_theme_installed
             (company_id, package_id, package_slug, install_key, name_en, name_ar, source, status, package_version)
             VALUES (:cid, NULL, :pslug, :ikey, :name_en, :name_ar, \'import\', \'installed\', :ver)'
        )->execute([
            'cid' => $cid,
            'pslug' => $manifest->slug(),
            'ikey' => $key,
            'name_en' => $manifest->nameEn(),
            'name_ar' => $manifest->nameAr(),
            'ver' => $manifest->version(),
        ]);
        $installedId = (int) $db->lastInsertId();

        // Store imported tokens as override base when package may not exist on disk.
        $override = is_array($payload['override'] ?? null) ? $payload['override'] : [];
        if (!isset($override['tokens']) || !is_array($override['tokens'])) {
            $override['tokens'] = $manifest->tokens();
        } else {
            $override['tokens'] = array_replace_recursive($manifest->tokens(), $override['tokens']);
        }
        (new ThemeOverrideService($this->repo))->save($installedId, $override);

        $destRoot = rtrim((string) RATEB_STORAGE_PATH, '/\\') . '/cms-media/' . $cid . '/themes/' . $installedId;
        if (!is_dir($destRoot)) {
            mkdir($destRoot, 0755, true);
        }
        $assets = $payload['assets'] ?? [];
        if (is_array($assets)) {
            foreach ($assets as $asset) {
                if (!is_array($asset)) {
                    continue;
                }
                $b64 = (string) ($asset['data_base64'] ?? '');
                $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', (string) ($asset['filename'] ?? 'asset.bin')) ?: 'asset.bin';
                if ($b64 === '') {
                    continue;
                }
                $bin = base64_decode($b64, true);
                if ($bin === false) {
                    continue;
                }
                $dest = $destRoot . '/' . $filename;
                file_put_contents($dest, $bin);
                $publicRel = 'storage/cms-media/' . $cid . '/themes/' . $installedId . '/' . $filename;
                $this->repo->execute(
                    'INSERT INTO rateb_website_theme_assets (company_id, installed_id, asset_key, asset_type, file_path, checksum)
                     VALUES (:cid, :iid, :akey, :atype, :path, :sum)',
                    [
                        'cid' => $cid,
                        'iid' => $installedId,
                        'akey' => (string) ($asset['key'] ?? pathinfo($filename, PATHINFO_FILENAME)),
                        'atype' => (string) ($asset['type'] ?? 'image'),
                        'path' => $publicRel,
                        'sum' => hash('sha256', $bin),
                    ]
                );
            }
        }

        (new AuditService())->log('website_theme_import', 'website_theme', $installedId, ['company_id' => $cid]);

        return ['ok' => true, 'installed_id' => $installedId, 'warnings' => $check['warnings']];
    }
}
