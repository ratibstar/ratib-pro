<?php
declare(strict_types=1);

namespace Rateb\App\Website\Career;

use Rateb\App\Services\CmsService;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-06 — Read/search jobs from rateb_cms_careers (ATS source, tenant-scoped).
 */
final class CareerJobService
{
    private TenantWebsiteRepository $repo;

    /** @var array<string, mixed> */
    private static array $cache = [];

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return array{items: list<array<string,mixed>>, total: int, page: int, per_page: int} */
    public function search(string $query = '', ?string $category = null, int $page = 1, int $perPage = 12): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;
        $cacheKey = 'search:' . md5($query . '|' . ($category ?? '') . '|' . $page . '|' . $perPage . '|' . $this->repo->companyId());
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        [$where, $params] = $this->repo->companyWhere();
        $where .= " AND status = 'open'";
        if ($category !== null && $category !== '') {
            $where .= ' AND category_slug = :cat';
            $params['cat'] = $category;
        }
        if ($query !== '') {
            $where .= ' AND (title_en LIKE :q OR title_ar LIKE :q OR department_en LIKE :q OR department_ar LIKE :q'
                . ' OR location_en LIKE :q OR location_ar LIKE :q OR description_en LIKE :q OR description_ar LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }

        $totalRow = $this->repo->fetchOne("SELECT COUNT(*) AS c FROM rateb_cms_careers WHERE {$where}", $params);
        $total = (int) ($totalRow['c'] ?? 0);
        $items = $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_careers WHERE {$where}
             ORDER BY featured DESC, COALESCE(published_at, '1970-01-01') DESC, id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $result = ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
        self::$cache[$cacheKey] = $result;

        return $result;
    }

    /** @return list<array<string, mixed>> */
    public function featured(int $limit = 6): array
    {
        $limit = max(1, min(20, $limit));
        $key = 'featured:' . $limit . ':' . $this->repo->companyId();
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        [$where, $params] = $this->repo->companyWhere();
        $rows = $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_careers WHERE {$where} AND status = 'open' AND featured = 1
             ORDER BY COALESCE(published_at, '1970-01-01') DESC, id DESC LIMIT {$limit}",
            $params
        );
        self::$cache[$key] = $rows;

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function latest(int $limit = 8): array
    {
        $limit = max(1, min(30, $limit));
        $key = 'latest:' . $limit . ':' . $this->repo->companyId();
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        [$where, $params] = $this->repo->companyWhere();
        $rows = $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_careers WHERE {$where} AND status = 'open'
             ORDER BY COALESCE(published_at, '1970-01-01') DESC, id DESC LIMIT {$limit}",
            $params
        );
        self::$cache[$key] = $rows;

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function categories(): array
    {
        $key = 'categories:' . $this->repo->companyId();
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        [$where, $params] = $this->repo->companyWhere();
        $rows = $this->repo->fetchAll(
            "SELECT category_slug AS slug,
                    COUNT(*) AS job_count,
                    MAX(department_en) AS label_en,
                    MAX(department_ar) AS label_ar
             FROM rateb_cms_careers
             WHERE {$where} AND status = 'open' AND category_slug IS NOT NULL AND category_slug <> ''
             GROUP BY category_slug
             ORDER BY job_count DESC, category_slug ASC",
            $params
        );
        self::$cache[$key] = $rows;

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }
        [$where, $params] = $this->repo->companyWhere();
        $params['slug'] = $slug;

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_cms_careers WHERE {$where} AND slug = :slug LIMIT 1",
            $params
        );
    }

    /** @return list<array<string, mixed>> */
    public function related(int $careerId, ?string $categorySlug, int $limit = 4): array
    {
        $limit = max(1, min(10, $limit));
        [$where, $params] = $this->repo->companyWhere();
        $params['id'] = $careerId;
        $where .= " AND status = 'open' AND id <> :id";
        if ($categorySlug !== null && $categorySlug !== '') {
            $where .= ' AND category_slug = :cat';
            $params['cat'] = $categorySlug;
        }

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_careers WHERE {$where}
             ORDER BY featured DESC, id DESC LIMIT {$limit}",
            $params
        );
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    public static function jobTitle(array $job): string
    {
        return CmsService::pickLocale($job, 'title');
    }

    public static function jobUrl(array $job): string
    {
        $slug = trim((string) ($job['slug'] ?? ''));

        return rateb_url('site/careers/job/' . rawurlencode($slug !== '' ? $slug : (string) ($job['id'] ?? '')));
    }
}
