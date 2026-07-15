<?php
declare(strict_types=1);

namespace Rateb\App\Website;

use Rateb\App\Core\Database;
use Rateb\App\Services\AuditService;

/**
 * Phase WEBSITE-04 — Tenant page/section/block mutations + library (company_id enforced).
 */
final class WebsiteBuilderService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    public function companyId(): int
    {
        return $this->repo->companyId();
    }

    /** @return list<array<string, mixed>> */
    public function pages(): array
    {
        [$where, $params] = $this->repo->companyWhere();

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_pages WHERE {$where} ORDER BY sort_order ASC, id ASC",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function pageBySlug(string $slug): ?array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['s'] = $slug;
        $row = $this->repo->fetchOne(
            "SELECT * FROM rateb_cms_pages WHERE {$where} AND slug = :s LIMIT 1",
            $params
        );
        $this->repo->assertRowCompany($row, 'cms_page');

        return $row;
    }

    /** @return array<string, mixed>|null */
    public function pageById(int $id): ?array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['id'] = $id;
        $row = $this->repo->fetchOne(
            "SELECT * FROM rateb_cms_pages WHERE {$where} AND id = :id LIMIT 1",
            $params
        );
        $this->repo->assertRowCompany($row, 'cms_page');

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function savePage(array $data, ?int $id = null): int
    {
        $cid = $this->repo->companyId();
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', strtolower(trim((string) ($data['slug'] ?? '')))) ?: 'page';
        $payload = [
            'slug' => $slug,
            'title_en' => trim((string) ($data['title_en'] ?? $slug)),
            'title_ar' => trim((string) ($data['title_ar'] ?? '')),
            'content_en' => (string) ($data['content_en'] ?? ''),
            'content_ar' => (string) ($data['content_ar'] ?? ''),
            'template' => trim((string) ($data['template'] ?? 'builder')) ?: 'builder',
            'status' => in_array(($data['status'] ?? 'draft'), ['draft', 'published', 'scheduled'], true)
                ? (string) $data['status'] : 'draft',
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
        $db = Database::connection();
        if ($id !== null && $id > 0) {
            $existing = $this->pageById($id);
            if ($existing === null) {
                throw new \RuntimeException('Page not found');
            }
            $stmt = $db->prepare(
                'UPDATE rateb_cms_pages SET slug=:slug, title_en=:title_en, title_ar=:title_ar,
                 content_en=:content_en, content_ar=:content_ar, template=:template, status=:status,
                 sort_order=:sort_order WHERE id=:id AND company_id=:company_id'
            );
            $payload['id'] = $id;
            $payload['company_id'] = $cid;
            $stmt->execute($payload);
            (new AuditService())->log('website_page_update', 'cms_page', $id, ['company_id' => $cid]);

            return $id;
        }
        $stmt = $db->prepare(
            'INSERT INTO rateb_cms_pages (company_id, slug, title_en, title_ar, content_en, content_ar, template, status, sort_order)
             VALUES (:company_id, :slug, :title_en, :title_ar, :content_en, :content_ar, :template, :status, :sort_order)'
        );
        $payload['company_id'] = $cid;
        $stmt->execute($payload);
        $newId = (int) $db->lastInsertId();
        (new AuditService())->log('website_page_create', 'cms_page', $newId, ['company_id' => $cid]);

        return $newId;
    }

    public function deletePage(int $id): void
    {
        $page = $this->pageById($id);
        if ($page === null) {
            throw new \RuntimeException('Page not found');
        }
        $slug = (string) $page['slug'];
        $cid = $this->repo->companyId();
        $sections = $this->sectionsForSlug($slug);
        foreach ($sections as $section) {
            $this->deleteSection((int) $section['id']);
        }
        $this->repo->execute(
            'DELETE FROM rateb_cms_pages WHERE id = :id AND company_id = :cid',
            ['id' => $id, 'cid' => $cid]
        );
    }

    /** @return list<array<string, mixed>> */
    public function sectionsForSlug(string $slug): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['p'] = $slug;

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_sections WHERE {$where} AND page_slug = :p ORDER BY sort_order ASC, id ASC",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function sectionById(int $id): ?array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['id'] = $id;
        $row = $this->repo->fetchOne(
            "SELECT * FROM rateb_cms_sections WHERE {$where} AND id = :id LIMIT 1",
            $params
        );
        $this->repo->assertRowCompany($row, 'cms_section');

        return $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addSection(string $pageSlug, array $data = []): int
    {
        $page = $this->pageBySlug($pageSlug);
        if ($page === null) {
            throw new \RuntimeException('Page not found');
        }
        $cid = $this->repo->companyId();
        $key = preg_replace('/[^a-z0-9_\-]+/', '_', strtolower(trim((string) ($data['section_key'] ?? 'section_' . time())))) ?: ('section_' . time());
        $max = $this->repo->fetchOne(
            'SELECT COALESCE(MAX(sort_order), -1) AS m FROM rateb_cms_sections WHERE company_id = :cid AND page_slug = :p',
            ['cid' => $cid, 'p' => $pageSlug]
        );
        $order = ((int) ($max['m'] ?? -1)) + 1;
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO rateb_cms_sections (company_id, page_slug, section_key, title_en, title_ar, body_en, body_ar, settings_json, sort_order, is_active)
             VALUES (:company_id, :page_slug, :section_key, :title_en, :title_ar, :body_en, :body_ar, :settings_json, :sort_order, 1)'
        );
        $stmt->execute([
            'company_id' => $cid,
            'page_slug' => $pageSlug,
            'section_key' => $key,
            'title_en' => (string) ($data['title_en'] ?? ''),
            'title_ar' => (string) ($data['title_ar'] ?? ''),
            'body_en' => (string) ($data['body_en'] ?? ''),
            'body_ar' => (string) ($data['body_ar'] ?? ''),
            'settings_json' => isset($data['settings']) ? json_encode($data['settings'], JSON_UNESCAPED_UNICODE) : null,
            'sort_order' => $order,
        ]);

        return (int) $db->lastInsertId();
    }

    public function deleteSection(int $id): void
    {
        $section = $this->sectionById($id);
        if ($section === null) {
            return;
        }
        $cid = $this->repo->companyId();
        $this->repo->execute(
            'DELETE FROM rateb_cms_blocks WHERE section_id = :sid AND company_id = :cid',
            ['sid' => $id, 'cid' => $cid]
        );
        $this->repo->execute(
            'DELETE FROM rateb_cms_sections WHERE id = :id AND company_id = :cid',
            ['id' => $id, 'cid' => $cid]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addBlock(int $sectionId, string $type, array $data = []): int
    {
        if (!WebsiteBlockRegistry::isValid($type)) {
            throw new \InvalidArgumentException('Unknown block type');
        }
        $section = $this->sectionById($sectionId);
        if ($section === null) {
            throw new \RuntimeException('Section not found');
        }
        $defaults = WebsiteBlockRegistry::defaults($type);
        $cid = $this->repo->companyId();
        $max = $this->repo->fetchOne(
            'SELECT COALESCE(MAX(sort_order), -1) AS m FROM rateb_cms_blocks WHERE company_id = :cid AND section_id = :sid',
            ['cid' => $cid, 'sid' => $sectionId]
        );
        $settings = $data['settings'] ?? $defaults['settings'];
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO rateb_cms_blocks
             (company_id, section_id, block_type, title_en, title_ar, content_en, content_ar, icon, image_path, link_url, settings_json, sort_order, is_active)
             VALUES (:company_id, :section_id, :block_type, :title_en, :title_ar, :content_en, :content_ar, :icon, :image_path, :link_url, :settings_json, :sort_order, 1)'
        );
        $stmt->execute([
            'company_id' => $cid,
            'section_id' => $sectionId,
            'block_type' => $type,
            'title_en' => (string) ($data['title_en'] ?? $defaults['title_en']),
            'title_ar' => (string) ($data['title_ar'] ?? $defaults['title_ar']),
            'content_en' => (string) ($data['content_en'] ?? $defaults['content_en']),
            'content_ar' => (string) ($data['content_ar'] ?? $defaults['content_ar']),
            'icon' => $data['icon'] ?? null,
            'image_path' => $data['image_path'] ?? null,
            'link_url' => $data['link_url'] ?? null,
            'settings_json' => json_encode(is_array($settings) ? $settings : [], JSON_UNESCAPED_UNICODE),
            'sort_order' => ((int) ($max['m'] ?? -1)) + 1,
        ]);

        return (int) $db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function updateBlock(int $id, array $data): void
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['id'] = $id;
        $row = $this->repo->fetchOne(
            "SELECT * FROM rateb_cms_blocks WHERE {$where} AND id = :id LIMIT 1",
            $params
        );
        $this->repo->assertRowCompany($row, 'cms_block');
        if ($row === null) {
            throw new \RuntimeException('Block not found');
        }
        $settings = $data['settings'] ?? null;
        if (is_array($settings)) {
            $settings = json_encode($settings, JSON_UNESCAPED_UNICODE);
        } elseif (!array_key_exists('settings_json', $data)) {
            $settings = $row['settings_json'] ?? null;
        } else {
            $settings = $data['settings_json'];
        }
        $this->repo->execute(
            'UPDATE rateb_cms_blocks SET title_en=:title_en, title_ar=:title_ar, content_en=:content_en, content_ar=:content_ar,
             icon=:icon, image_path=:image_path, link_url=:link_url, settings_json=:settings_json, is_active=:is_active
             WHERE id=:id AND company_id=:cid',
            [
                'title_en' => (string) ($data['title_en'] ?? $row['title_en']),
                'title_ar' => (string) ($data['title_ar'] ?? $row['title_ar']),
                'content_en' => (string) ($data['content_en'] ?? $row['content_en']),
                'content_ar' => (string) ($data['content_ar'] ?? $row['content_ar']),
                'icon' => $data['icon'] ?? $row['icon'],
                'image_path' => $data['image_path'] ?? $row['image_path'],
                'link_url' => $data['link_url'] ?? $row['link_url'],
                'settings_json' => $settings,
                'is_active' => isset($data['is_active']) ? (!empty($data['is_active']) ? 1 : 0) : (int) $row['is_active'],
                'id' => $id,
                'cid' => $this->repo->companyId(),
            ]
        );
    }

    public function deleteBlock(int $id): void
    {
        $this->repo->execute(
            'DELETE FROM rateb_cms_blocks WHERE id = :id AND company_id = :cid',
            ['id' => $id, 'cid' => $this->repo->companyId()]
        );
    }

    /**
     * @param list<int> $sectionIds
     * @param array<int, list<int>> $blocksBySection sectionId => block ids in order
     */
    public function reorder(array $sectionIds, array $blocksBySection = []): void
    {
        $cid = $this->repo->companyId();
        foreach (array_values($sectionIds) as $order => $sid) {
            $this->repo->execute(
                'UPDATE rateb_cms_sections SET sort_order = :o WHERE id = :id AND company_id = :cid',
                ['o' => (int) $order, 'id' => (int) $sid, 'cid' => $cid]
            );
        }
        foreach ($blocksBySection as $sectionId => $blockIds) {
            if (!is_array($blockIds)) {
                continue;
            }
            foreach (array_values($blockIds) as $order => $bid) {
                $this->repo->execute(
                    'UPDATE rateb_cms_blocks SET sort_order = :o, section_id = :sid WHERE id = :id AND company_id = :cid',
                    [
                        'o' => (int) $order,
                        'sid' => (int) $sectionId,
                        'id' => (int) $bid,
                        'cid' => $cid,
                    ]
                );
            }
        }
    }

    /** @return array{section:array<string,mixed>,blocks:list<array<string,mixed>>}[] keyed list */
    public function builderTree(string $pageSlug): array
    {
        $tree = [];
        foreach ($this->sectionsForSlug($pageSlug) as $section) {
            $sid = (int) $section['id'];
            [$where, $params] = $this->repo->companyWhere();
            $params['sid'] = $sid;
            $blocks = $this->repo->fetchAll(
                "SELECT * FROM rateb_cms_blocks WHERE {$where} AND section_id = :sid ORDER BY sort_order ASC, id ASC",
                $params
            );
            $tree[] = ['section' => $section, 'blocks' => $blocks];
        }

        return $tree;
    }

    /** @return list<array<string, mixed>> */
    public function library(): array
    {
        [$where, $params] = $this->repo->companyWhere();

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_website_block_library WHERE {$where} AND is_active = 1 ORDER BY name_en ASC",
            $params
        );
    }

    /** @param array<string, mixed> $data */
    public function saveLibraryItem(array $data, ?int $id = null): int
    {
        $cid = $this->repo->companyId();
        $type = (string) ($data['block_type'] ?? 'text');
        if (!WebsiteBlockRegistry::isValid($type)) {
            throw new \InvalidArgumentException('Unknown block type');
        }
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', strtolower(trim((string) ($data['slug'] ?? $type)))) ?: $type;
        $payloadJson = $data['payload'] ?? $data['payload_json'] ?? [];
        if (is_array($payloadJson)) {
            $payloadJson = json_encode($payloadJson, JSON_UNESCAPED_UNICODE);
        }
        $db = Database::connection();
        if ($id !== null && $id > 0) {
            $db->prepare(
                'UPDATE rateb_website_block_library SET slug=:slug, block_type=:block_type, name_en=:name_en, name_ar=:name_ar,
                 payload_json=:payload_json, is_active=:is_active WHERE id=:id AND company_id=:company_id'
            )->execute([
                'slug' => $slug,
                'block_type' => $type,
                'name_en' => (string) ($data['name_en'] ?? $slug),
                'name_ar' => (string) ($data['name_ar'] ?? ''),
                'payload_json' => $payloadJson,
                'is_active' => !isset($data['is_active']) || !empty($data['is_active']) ? 1 : 0,
                'id' => $id,
                'company_id' => $cid,
            ]);

            return $id;
        }
        $db->prepare(
            'INSERT INTO rateb_website_block_library (company_id, slug, block_type, name_en, name_ar, payload_json, is_active)
             VALUES (:company_id, :slug, :block_type, :name_en, :name_ar, :payload_json, 1)'
        )->execute([
            'company_id' => $cid,
            'slug' => $slug,
            'block_type' => $type,
            'name_en' => (string) ($data['name_en'] ?? $slug),
            'name_ar' => (string) ($data['name_ar'] ?? ''),
            'payload_json' => $payloadJson,
        ]);

        return (int) $db->lastInsertId();
    }
}
