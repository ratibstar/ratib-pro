<?php
declare(strict_types=1);

namespace Rateb\App\Website;

use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\AuditService;

/**
 * Phase WEBSITE-04 — Draft / publish / preview / rollback / schedule.
 */
final class WebsiteVersionService
{
    private TenantWebsiteRepository $repo;
    private WebsiteBuilderService $builder;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
        $this->builder = new WebsiteBuilderService($this->repo);
    }

    /** @return list<array<string, mixed>> */
    public function versionsForPage(int $pageId): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['pid'] = $pageId;

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_website_page_versions WHERE {$where} AND page_id = :pid ORDER BY version_no DESC",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function findVersion(int $id): ?array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['id'] = $id;
        $row = $this->repo->fetchOne(
            "SELECT * FROM rateb_website_page_versions WHERE {$where} AND id = :id LIMIT 1",
            $params
        );
        $this->repo->assertRowCompany($row, 'website_version');

        return $row;
    }

    public function saveDraft(int $pageId, ?string $label = null, ?array $seo = null): int
    {
        $page = $this->builder->pageById($pageId);
        if ($page === null) {
            throw new \RuntimeException('Page not found');
        }
        $snapshot = [
            'page' => $page,
            'tree' => $this->builder->builderTree((string) $page['slug']),
        ];
        $cid = $this->repo->companyId();
        $max = $this->repo->fetchOne(
            'SELECT COALESCE(MAX(version_no), 0) AS m FROM rateb_website_page_versions WHERE company_id = :cid AND page_id = :pid',
            ['cid' => $cid, 'pid' => $pageId]
        );
        $ver = ((int) ($max['m'] ?? 0)) + 1;
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO rateb_website_page_versions
             (company_id, page_id, version_no, status, label, snapshot_json, seo_json, created_by)
             VALUES (:company_id, :page_id, :version_no, \'draft\', :label, :snapshot_json, :seo_json, :created_by)'
        );
        $stmt->execute([
            'company_id' => $cid,
            'page_id' => $pageId,
            'version_no' => $ver,
            'label' => $label,
            'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'seo_json' => $seo !== null ? json_encode($seo, JSON_UNESCAPED_UNICODE) : null,
            'created_by' => (int) (SessionManager::get('rateb_user_id') ?? 0) ?: null,
        ]);
        $id = (int) $db->lastInsertId();
        (new AuditService())->log('website_version_draft', 'website_version', $id, [
            'page_id' => $pageId,
            'company_id' => $cid,
        ]);

        return $id;
    }

    public function publish(int $pageId, ?int $versionId = null): int
    {
        if ($versionId === null) {
            $versionId = $this->saveDraft($pageId, 'Publish snapshot');
        }
        $version = $this->findVersion($versionId);
        if ($version === null || (int) $version['page_id'] !== $pageId) {
            throw new \RuntimeException('Version not found');
        }
        $this->applySnapshot($version);
        $cid = $this->repo->companyId();
        $this->repo->execute(
            "UPDATE rateb_website_page_versions SET status = 'archived' WHERE company_id = :cid AND page_id = :pid AND status = 'published'",
            ['cid' => $cid, 'pid' => $pageId]
        );
        $this->repo->execute(
            "UPDATE rateb_website_page_versions SET status = 'published', published_at = NOW(), scheduled_at = NULL WHERE id = :id AND company_id = :cid",
            ['id' => $versionId, 'cid' => $cid]
        );
        $this->repo->execute(
            "UPDATE rateb_cms_pages SET status = 'published', published_at = NOW() WHERE id = :id AND company_id = :cid",
            ['id' => $pageId, 'cid' => $cid]
        );
        (new AuditService())->log('website_publish', 'cms_page', $pageId, [
            'version_id' => $versionId,
            'company_id' => $cid,
        ]);

        return $versionId;
    }

    public function schedule(int $pageId, string $when, ?int $versionId = null): int
    {
        if ($versionId === null) {
            $versionId = $this->saveDraft($pageId, 'Scheduled');
        }
        $version = $this->findVersion($versionId);
        if ($version === null || (int) $version['page_id'] !== $pageId) {
            throw new \RuntimeException('Version not found');
        }
        $ts = strtotime($when);
        if ($ts === false) {
            throw new \InvalidArgumentException('Invalid schedule time');
        }
        $cid = $this->repo->companyId();
        $this->repo->execute(
            "UPDATE rateb_website_page_versions SET status = 'scheduled', scheduled_at = :s WHERE id = :id AND company_id = :cid",
            ['s' => date('Y-m-d H:i:s', $ts), 'id' => $versionId, 'cid' => $cid]
        );
        $this->repo->execute(
            "UPDATE rateb_cms_pages SET status = 'scheduled', published_at = :s WHERE id = :id AND company_id = :cid",
            ['s' => date('Y-m-d H:i:s', $ts), 'id' => $pageId, 'cid' => $cid]
        );

        return $versionId;
    }

    public function rollback(int $pageId, int $versionId): void
    {
        $version = $this->findVersion($versionId);
        if ($version === null || (int) $version['page_id'] !== $pageId) {
            throw new \RuntimeException('Version not found');
        }
        $this->applySnapshot($version);
        $this->publish($pageId, $this->saveDraft($pageId, 'Rollback to v' . (int) $version['version_no']));
    }

    public function createPreviewToken(int $pageId, ?int $versionId = null, int $ttlSeconds = 3600): string
    {
        $page = $this->builder->pageById($pageId);
        if ($page === null) {
            throw new \RuntimeException('Page not found');
        }
        $token = bin2hex(random_bytes(32));
        $this->repo->execute(
            'INSERT INTO rateb_website_preview_tokens (company_id, page_id, version_id, token, expires_at, created_by)
             VALUES (:cid, :pid, :vid, :token, :exp, :uid)',
            [
                'cid' => $this->repo->companyId(),
                'pid' => $pageId,
                'vid' => $versionId,
                'token' => $token,
                'exp' => date('Y-m-d H:i:s', time() + max(300, $ttlSeconds)),
                'uid' => (int) (SessionManager::get('rateb_user_id') ?? 0) ?: null,
            ]
        );

        return $token;
    }

    /** @return array{page:array<string,mixed>,version:?array<string,mixed>}|null */
    public function resolvePreviewToken(string $token): ?array
    {
        $row = $this->repo->fetchOne(
            'SELECT * FROM rateb_website_preview_tokens WHERE token = :t AND expires_at > NOW() LIMIT 1',
            ['t' => $token]
        );
        if ($row === null) {
            return null;
        }
        // Enforce same company as current WebsiteContext.
        if ((int) $row['company_id'] !== $this->repo->companyId()) {
            return null;
        }
        $page = $this->builder->pageById((int) $row['page_id']);
        if ($page === null) {
            return null;
        }
        $version = null;
        if (!empty($row['version_id'])) {
            $version = $this->findVersion((int) $row['version_id']);
        }

        return ['page' => $page, 'version' => $version];
    }

    /** Process due scheduled publishes (call from cron/public soft tick). */
    public function processScheduled(int $limit = 20): int
    {
        $cid = $this->repo->companyId();
        $rows = $this->repo->fetchAll(
            "SELECT * FROM rateb_website_page_versions
             WHERE company_id = :cid AND status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()
             ORDER BY scheduled_at ASC LIMIT " . max(1, min(100, $limit)),
            ['cid' => $cid]
        );
        $n = 0;
        foreach ($rows as $row) {
            try {
                $this->publish((int) $row['page_id'], (int) $row['id']);
                $n++;
            } catch (\Throwable $e) {
                error_log('WebsiteVersionService schedule: ' . $e->getMessage());
            }
        }

        return $n;
    }

    /** @param array<string, mixed> $version */
    private function applySnapshot(array $version): void
    {
        $raw = (string) ($version['snapshot_json'] ?? '');
        $snap = json_decode($raw, true);
        if (!is_array($snap) || !isset($snap['page'], $snap['tree']) || !is_array($snap['tree'])) {
            throw new \RuntimeException('Invalid version snapshot');
        }
        $page = $snap['page'];
        $slug = (string) ($page['slug'] ?? '');
        if ($slug === '') {
            throw new \RuntimeException('Snapshot page missing slug');
        }
        $cid = $this->repo->companyId();
        // Replace live tree for this page slug.
        $existing = $this->builder->sectionsForSlug($slug);
        foreach ($existing as $sec) {
            $this->builder->deleteSection((int) $sec['id']);
        }
        $db = Database::connection();
        foreach ($snap['tree'] as $pack) {
            if (!is_array($pack)) {
                continue;
            }
            $section = $pack['section'] ?? [];
            $blocks = $pack['blocks'] ?? [];
            if (!is_array($section)) {
                continue;
            }
            $stmt = $db->prepare(
                'INSERT INTO rateb_cms_sections (company_id, page_slug, section_key, title_en, title_ar, body_en, body_ar, settings_json, sort_order, is_active)
                 VALUES (:company_id, :page_slug, :section_key, :title_en, :title_ar, :body_en, :body_ar, :settings_json, :sort_order, :is_active)'
            );
            $stmt->execute([
                'company_id' => $cid,
                'page_slug' => $slug,
                'section_key' => (string) ($section['section_key'] ?? 'section'),
                'title_en' => (string) ($section['title_en'] ?? ''),
                'title_ar' => (string) ($section['title_ar'] ?? ''),
                'body_en' => (string) ($section['body_en'] ?? ''),
                'body_ar' => (string) ($section['body_ar'] ?? ''),
                'settings_json' => is_array($section['settings_json'] ?? null)
                    ? json_encode($section['settings_json'], JSON_UNESCAPED_UNICODE)
                    : ($section['settings_json'] ?? null),
                'sort_order' => (int) ($section['sort_order'] ?? 0),
                'is_active' => (int) ($section['is_active'] ?? 1),
            ]);
            $sectionId = (int) $db->lastInsertId();
            if (!is_array($blocks)) {
                continue;
            }
            $bstmt = $db->prepare(
                'INSERT INTO rateb_cms_blocks
                 (company_id, section_id, block_type, title_en, title_ar, content_en, content_ar, icon, image_path, link_url, settings_json, sort_order, is_active)
                 VALUES (:company_id, :section_id, :block_type, :title_en, :title_ar, :content_en, :content_ar, :icon, :image_path, :link_url, :settings_json, :sort_order, :is_active)'
            );
            foreach ($blocks as $block) {
                if (!is_array($block)) {
                    continue;
                }
                $settings = $block['settings_json'] ?? null;
                if (is_array($settings)) {
                    $settings = json_encode($settings, JSON_UNESCAPED_UNICODE);
                }
                $bstmt->execute([
                    'company_id' => $cid,
                    'section_id' => $sectionId,
                    'block_type' => (string) ($block['block_type'] ?? 'text'),
                    'title_en' => (string) ($block['title_en'] ?? ''),
                    'title_ar' => (string) ($block['title_ar'] ?? ''),
                    'content_en' => (string) ($block['content_en'] ?? ''),
                    'content_ar' => (string) ($block['content_ar'] ?? ''),
                    'icon' => $block['icon'] ?? null,
                    'image_path' => $block['image_path'] ?? null,
                    'link_url' => $block['link_url'] ?? null,
                    'settings_json' => $settings,
                    'sort_order' => (int) ($block['sort_order'] ?? 0),
                    'is_active' => (int) ($block['is_active'] ?? 1),
                ]);
            }
        }
        if (!empty($version['seo_json'])) {
            $seo = json_decode((string) $version['seo_json'], true);
            if (is_array($seo)) {
                (new WebsiteSeoEditorService($this->repo))->saveForSlug($slug, $seo);
            }
        }
    }
}
