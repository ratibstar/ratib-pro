<?php
declare(strict_types=1);

namespace Rateb\App\Website;

use Rateb\App\Core\Database;

/** Phase WEBSITE-04 — Per-page SEO upsert (meta/canonical/OG/Twitter/robots/schema). */
final class WebsiteSeoEditorService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveForSlug(string $slug, array $data): void
    {
        $cid = $this->repo->companyId();
        [$where, $params] = $this->repo->companyWhere();
        $params['s'] = $slug;
        $existing = $this->repo->fetchOne(
            "SELECT id FROM rateb_cms_seo WHERE {$where} AND page_slug = :s LIMIT 1",
            $params
        );
        $payload = [
            'page_slug' => $slug,
            'meta_title_en' => (string) ($data['meta_title_en'] ?? $data['title'] ?? ''),
            'meta_title_ar' => (string) ($data['meta_title_ar'] ?? ''),
            'meta_description_en' => (string) ($data['meta_description_en'] ?? $data['description'] ?? ''),
            'meta_description_ar' => (string) ($data['meta_description_ar'] ?? ''),
            'og_title_en' => (string) ($data['og_title_en'] ?? $data['og_title'] ?? ''),
            'og_title_ar' => (string) ($data['og_title_ar'] ?? ''),
            'og_description_en' => (string) ($data['og_description_en'] ?? $data['og_description'] ?? ''),
            'og_description_ar' => (string) ($data['og_description_ar'] ?? ''),
            'og_image' => (string) ($data['og_image'] ?? ''),
            'canonical_url' => (string) ($data['canonical_url'] ?? $data['canonical'] ?? ''),
            'twitter_card' => (string) ($data['twitter_card'] ?? 'summary_large_image'),
            'robots' => (string) ($data['robots'] ?? 'index,follow'),
            'schema_json' => is_array($data['schema_json'] ?? null)
                ? json_encode($data['schema_json'], JSON_UNESCAPED_UNICODE)
                : ($data['schema_json'] ?? $data['schema'] ?? null),
        ];
        $db = Database::connection();
        // Detect optional columns (schema_json / robots may vary by migration history).
        $cols = $this->seoColumns();
        if ($existing) {
            $sets = [];
            $bind = ['id' => (int) $existing['id'], 'cid' => $cid];
            foreach ($payload as $k => $v) {
                if ($k === 'page_slug' || !isset($cols[$k])) {
                    continue;
                }
                $sets[] = "{$k} = :{$k}";
                $bind[$k] = $v;
            }
            if ($sets !== []) {
                $db->prepare('UPDATE rateb_cms_seo SET ' . implode(', ', $sets) . ' WHERE id = :id AND company_id = :cid')
                    ->execute($bind);
            }

            return;
        }
        $fields = ['company_id', 'page_slug'];
        $placeholders = [':company_id', ':page_slug'];
        $bind = ['company_id' => $cid, 'page_slug' => $slug];
        foreach ($payload as $k => $v) {
            if ($k === 'page_slug' || !isset($cols[$k])) {
                continue;
            }
            $fields[] = $k;
            $placeholders[] = ':' . $k;
            $bind[$k] = $v;
        }
        $db->prepare(
            'INSERT INTO rateb_cms_seo (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')'
        )->execute($bind);
    }

    /** @return array<string, true> */
    private function seoColumns(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        $cache = [];
        try {
            $stmt = Database::connection()->query('SHOW COLUMNS FROM rateb_cms_seo');
            $rows = $stmt ? $stmt->fetchAll() : [];
            foreach ($rows as $row) {
                $name = (string) ($row['Field'] ?? '');
                if ($name !== '') {
                    $cache[$name] = true;
                }
            }
        } catch (\Throwable $e) {
            $cache = [
                'meta_title_en' => true,
                'meta_title_ar' => true,
                'meta_description_en' => true,
                'meta_description_ar' => true,
                'og_title_en' => true,
                'og_title_ar' => true,
                'og_description_en' => true,
                'og_description_ar' => true,
                'og_image' => true,
                'canonical_url' => true,
                'twitter_card' => true,
            ];
        }

        return $cache;
    }
}
