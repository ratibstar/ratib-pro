<?php
declare(strict_types=1);

namespace Rateb\App\Website;

use Rateb\App\Core\Database;

/** Phase WEBSITE-04 — Visual nested menu + header/footer shell editors. */
final class WebsiteMenuBuilderService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return list<array<string, mixed>> */
    public function menus(): array
    {
        [$where, $params] = $this->repo->companyWhere();

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_menus WHERE {$where} ORDER BY location ASC, id ASC",
            $params
        );
    }

    /** @return list<array<string, mixed>> */
    public function items(int $menuId): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['mid'] = $menuId;

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_menu_items WHERE {$where} AND menu_id = :mid ORDER BY sort_order ASC, id ASC",
            $params
        );
    }

    /**
     * Nested tree for visual editor.
     * @return list<array<string, mixed>>
     */
    public function tree(int $menuId): array
    {
        $items = $this->items($menuId);
        $byParent = [];
        foreach ($items as $item) {
            $pid = $item['parent_id'] !== null ? (int) $item['parent_id'] : 0;
            $byParent[$pid][] = $item;
        }
        $build = static function (int $parent) use (&$build, $byParent): array {
            $out = [];
            foreach ($byParent[$parent] ?? [] as $row) {
                $row['children'] = $build((int) $row['id']);
                $out[] = $row;
            }

            return $out;
        };

        return $build(0);
    }

    /** @param array<string, mixed> $data */
    public function saveMenu(array $data, ?int $id = null): int
    {
        $cid = $this->repo->companyId();
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', strtolower(trim((string) ($data['slug'] ?? 'main')))) ?: 'main';
        $db = Database::connection();
        if ($id !== null && $id > 0) {
            $db->prepare(
                'UPDATE rateb_cms_menus SET slug=:slug, name_en=:name_en, name_ar=:name_ar, location=:location
                 WHERE id=:id AND company_id=:cid'
            )->execute([
                'slug' => $slug,
                'name_en' => (string) ($data['name_en'] ?? $slug),
                'name_ar' => (string) ($data['name_ar'] ?? ''),
                'location' => (string) ($data['location'] ?? 'header'),
                'id' => $id,
                'cid' => $cid,
            ]);

            return $id;
        }
        $db->prepare(
            'INSERT INTO rateb_cms_menus (company_id, slug, name_en, name_ar, location)
             VALUES (:cid, :slug, :name_en, :name_ar, :location)'
        )->execute([
            'cid' => $cid,
            'slug' => $slug,
            'name_en' => (string) ($data['name_en'] ?? $slug),
            'name_ar' => (string) ($data['name_ar'] ?? ''),
            'location' => (string) ($data['location'] ?? 'header'),
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * Replace all items from flat editor payload.
     * @param list<array<string, mixed>> $items
     */
    public function replaceItems(int $menuId, array $items): void
    {
        $cid = $this->repo->companyId();
        [$where, $params] = $this->repo->companyWhere();
        $params['id'] = $menuId;
        $menu = $this->repo->fetchOne("SELECT * FROM rateb_cms_menus WHERE {$where} AND id = :id LIMIT 1", $params);
        $this->repo->assertRowCompany($menu, 'cms_menu');
        if ($menu === null) {
            throw new \RuntimeException('Menu not found');
        }
        $this->repo->execute(
            'DELETE FROM rateb_cms_menu_items WHERE menu_id = :mid AND company_id = :cid',
            ['mid' => $menuId, 'cid' => $cid]
        );
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO rateb_cms_menu_items
             (company_id, menu_id, parent_id, label_en, label_ar, url, sort_order, is_active)
             VALUES (:company_id, :menu_id, :parent_id, :label_en, :label_ar, :url, :sort_order, :is_active)'
        );
        $idMap = [];
        foreach (array_values($items) as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $tempKey = (string) ($item['_key'] ?? ('n' . $i));
            $parentTemp = $item['parent_key'] ?? null;
            $parentId = null;
            if ($parentTemp !== null && $parentTemp !== '' && isset($idMap[(string) $parentTemp])) {
                $parentId = $idMap[(string) $parentTemp];
            } elseif (!empty($item['parent_id'])) {
                $parentId = (int) $item['parent_id'];
            }
            $stmt->execute([
                'company_id' => $cid,
                'menu_id' => $menuId,
                'parent_id' => $parentId,
                'label_en' => (string) ($item['label_en'] ?? ''),
                'label_ar' => (string) ($item['label_ar'] ?? ''),
                'url' => (string) ($item['url'] ?? '#'),
                'sort_order' => (int) ($item['sort_order'] ?? $i),
                'is_active' => !isset($item['is_active']) || !empty($item['is_active']) ? 1 : 0,
            ]);
            $idMap[$tempKey] = (int) $db->lastInsertId();
        }
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

    /**
     * @param list<array<string, mixed>> $columns
     */
    public function saveFooterColumns(array $columns): void
    {
        $cid = $this->repo->companyId();
        $this->repo->execute('DELETE FROM rateb_cms_footer_columns WHERE company_id = :cid', ['cid' => $cid]);
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO rateb_cms_footer_columns (company_id, title_en, title_ar, links_json, sort_order)
             VALUES (:company_id, :title_en, :title_ar, :links_json, :sort_order)'
        );
        foreach (array_values($columns) as $i => $col) {
            if (!is_array($col)) {
                continue;
            }
            $links = $col['links'] ?? $col['links_json'] ?? [];
            if (is_array($links)) {
                $links = json_encode($links, JSON_UNESCAPED_UNICODE);
            }
            $stmt->execute([
                'company_id' => $cid,
                'title_en' => (string) ($col['title_en'] ?? ''),
                'title_ar' => (string) ($col['title_ar'] ?? ''),
                'links_json' => $links,
                'sort_order' => (int) ($col['sort_order'] ?? $i),
            ]);
        }
    }
}
