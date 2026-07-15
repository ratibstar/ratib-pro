<?php
declare(strict_types=1);

namespace Rateb\App\Website;

/** Phase WEBSITE-03 — Tenant menus / footer. */
final class TenantMenuService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return list<array<string, mixed>> */
    public function menuItems(string $menuSlug = 'main'): array
    {
        [$where, $params] = $this->repo->companyWhere('m');
        $params['slug'] = $menuSlug;
        $rows = $this->repo->fetchAll(
            "SELECT i.* FROM rateb_cms_menu_items i
             INNER JOIN rateb_cms_menus m ON m.id = i.menu_id AND m.company_id = i.company_id
             WHERE {$where} AND m.slug = :slug AND i.is_active = 1
             ORDER BY i.sort_order ASC, i.id ASC",
            $params
        );
        if ($rows === [] && $this->repo->scoped()) {
            // Legacy rows may lack menu company join match — fall back to item company only.
            [$w2, $p2] = $this->repo->companyWhere('i');
            $p2['slug'] = $menuSlug;
            $rows = $this->repo->fetchAll(
                "SELECT i.* FROM rateb_cms_menu_items i
                 INNER JOIN rateb_cms_menus m ON m.id = i.menu_id
                 WHERE {$w2} AND m.slug = :slug AND i.is_active = 1
                 ORDER BY i.sort_order ASC, i.id ASC",
                $p2
            );
        }
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $key = (string) ($row['url'] ?? '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function footerColumns(): array
    {
        [$where, $params] = $this->repo->companyWhere();

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_footer_columns WHERE {$where} ORDER BY sort_order ASC, id ASC",
            $params
        );
    }
}
