<?php
declare(strict_types=1);

namespace Rateb\App\Website\Theme;

use Rateb\App\Core\Database;
use Rateb\App\Website\TenantWebsiteRepository;

/** Phase WEBSITE-05 — Agency overrides (never mutate package). */
final class ThemeOverrideService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return array<string, mixed> */
    public function get(int $installedId): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['iid'] = $installedId;
        $row = $this->repo->fetchOne(
            "SELECT * FROM rateb_website_theme_overrides WHERE {$where} AND installed_id = :iid LIMIT 1",
            $params
        );
        if ($row === null) {
            return [];
        }
        $decoded = json_decode((string) ($row['override_json'] ?? ''), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<string, mixed> $override */
    public function save(int $installedId, array $override): void
    {
        $cid = $this->repo->companyId();
        $json = json_encode($override, JSON_UNESCAPED_UNICODE);
        $db = Database::connection();
        $existing = $this->repo->fetchOne(
            'SELECT id FROM rateb_website_theme_overrides WHERE company_id = :cid AND installed_id = :iid LIMIT 1',
            ['cid' => $cid, 'iid' => $installedId]
        );
        if ($existing) {
            $db->prepare(
                'UPDATE rateb_website_theme_overrides SET override_json = :j WHERE id = :id AND company_id = :cid'
            )->execute(['j' => $json, 'id' => (int) $existing['id'], 'cid' => $cid]);
        } else {
            $db->prepare(
                'INSERT INTO rateb_website_theme_overrides (company_id, installed_id, override_json) VALUES (:cid, :iid, :j)'
            )->execute(['cid' => $cid, 'iid' => $installedId, 'j' => $json]);
        }
        ThemeResolver::clearCache();
    }
}
