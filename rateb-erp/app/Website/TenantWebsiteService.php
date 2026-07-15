<?php
declare(strict_types=1);

namespace Rateb\App\Website;

use Rateb\App\Core\Response;

/**
 * Phase WEBSITE-03 — Tenant website facade (pages, blogs, careers, forms data).
 * Reuses MarketingController via CmsService delegation — no duplicate controllers.
 */
final class TenantWebsiteService
{
    private TenantWebsiteRepository $repo;
    private TenantThemeService $theme;
    private TenantSeoService $seo;
    private TenantMenuService $menus;
    private TenantBlockService $blocks;
    private TenantMediaService $media;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
        $this->theme = new TenantThemeService($this->repo);
        $this->seo = new TenantSeoService($this->repo);
        $this->menus = new TenantMenuService($this->repo);
        $this->blocks = new TenantBlockService($this->repo);
        $this->media = new TenantMediaService($this->repo);
    }

    public function repository(): TenantWebsiteRepository
    {
        return $this->repo;
    }

    public function themeService(): TenantThemeService
    {
        return $this->theme;
    }

    public function seoService(): TenantSeoService
    {
        return $this->seo;
    }

    public function menuService(): TenantMenuService
    {
        return $this->menus;
    }

    public function blockService(): TenantBlockService
    {
        return $this->blocks;
    }

    public function mediaService(): TenantMediaService
    {
        return $this->media;
    }

    /** @return array<string, mixed>|null */
    public function pageBySlug(string $slug): ?array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['s'] = $slug;
        $params['st'] = 'published';

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_cms_pages WHERE {$where} AND slug = :s AND status = :st LIMIT 1",
            $params
        );
    }

    /** @return array<string, mixed> */
    public function theme(): array
    {
        return $this->theme->theme();
    }

    /** @return list<array<string, mixed>> */
    public function menuItems(string $menuSlug = 'main'): array
    {
        return $this->menus->menuItems($menuSlug);
    }

    /** @return array<string, string> */
    public function metaTags(string $pageSlug, string $defaultTitle): array
    {
        return $this->seo->metaTags($pageSlug, $defaultTitle);
    }

    /** @return array<string, array{section:array<string,mixed>,blocks:list<array<string,mixed>>}> */
    public function pageContent(string $pageSlug): array
    {
        return $this->blocks->pageContent($pageSlug);
    }

    /** @return list<array<string, mixed>> */
    public function publishedArticles(int $limit = 50): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $sql = "SELECT * FROM rateb_cms_blog_articles WHERE {$where} AND status = 'published'
             AND (published_at IS NULL OR published_at <= NOW())
             ORDER BY published_at DESC, id DESC LIMIT " . max(1, min(200, $limit));

        return $this->repo->fetchAll($sql, $params);
    }

    /** @return array<string, mixed>|null */
    public function articleBySlug(string $slug): ?array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['s'] = $slug;

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_cms_blog_articles WHERE {$where} AND slug = :s
             AND status = 'published' AND (published_at IS NULL OR published_at <= NOW()) LIMIT 1",
            $params
        );
    }

    /** @return list<array<string, mixed>> */
    public function activeSlides(): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $now = date('Y-m-d H:i:s');
        $params['now'] = $now;
        $params['now2'] = $now;

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_slides WHERE {$where} AND is_active = 1
             AND (starts_at IS NULL OR starts_at <= :now)
             AND (ends_at IS NULL OR ends_at >= :now2)
             ORDER BY sort_order ASC",
            $params
        );
    }

    /** @return list<array<string, mixed>> */
    public function activeFaqs(int $limit = 50): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $sql = "SELECT * FROM rateb_cms_faqs WHERE {$where} AND is_active = 1
             ORDER BY sort_order ASC LIMIT " . max(1, min(100, $limit));

        return $this->repo->fetchAll($sql, $params);
    }

    /** @return list<array<string, mixed>> */
    public function approvedTestimonials(int $limit = 50): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['s'] = 'approved';
        $sql = "SELECT * FROM rateb_cms_testimonials WHERE {$where} AND status = :s
             ORDER BY sort_order ASC LIMIT " . max(1, min(100, $limit));

        return $this->repo->fetchAll($sql, $params);
    }

    /** @return list<array<string, mixed>> */
    public function publishedServices(): array
    {
        [$where, $params] = $this->repo->companyWhere();

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_services WHERE {$where} AND status = 'published' ORDER BY sort_order ASC, id ASC",
            $params
        );
    }

    /** @return list<array<string, mixed>> */
    public function openCareers(): array
    {
        [$where, $params] = $this->repo->companyWhere();

        return $this->repo->fetchAll(
            "SELECT * FROM rateb_cms_careers WHERE {$where} AND status = 'open' ORDER BY id DESC",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function about(): ?array
    {
        [$where, $params] = $this->repo->companyWhere();

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_cms_about WHERE {$where} ORDER BY id ASC LIMIT 1",
            $params
        );
    }

    /** @return array<string, mixed>|null */
    public function contactSettings(): ?array
    {
        return $this->theme->contact();
    }

    /** @return array<string, mixed>|null */
    public function analytics(): ?array
    {
        [$where, $params] = $this->repo->companyWhere();

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_cms_analytics WHERE {$where} ORDER BY id ASC LIMIT 1",
            $params
        );
    }

    public function robotsContent(): string
    {
        return $this->seo->robotsContent();
    }

    /** @return list<string> */
    public function sitemapPaths(): array
    {
        return $this->seo->sitemapPaths();
    }

    public function trackPageView(string $pageSlug): void
    {
        try {
            $today = date('Y-m-d');
            if ($this->repo->scoped()) {
                $existing = $this->repo->fetchOne(
                    'SELECT id, page_views FROM rateb_cms_visitors WHERE company_id = :cid AND visit_date = :d LIMIT 1',
                    ['cid' => $this->repo->companyId(), 'd' => $today]
                );
                if ($existing) {
                    $this->repo->execute(
                        'UPDATE rateb_cms_visitors SET page_views = page_views + 1 WHERE id = :id AND company_id = :cid',
                        ['id' => (int) $existing['id'], 'cid' => $this->repo->companyId()]
                    );
                } else {
                    $this->repo->execute(
                        'INSERT INTO rateb_cms_visitors (company_id, visit_date, page_views, unique_visitors)
                         VALUES (:cid, :d, 1, 1)',
                        ['cid' => $this->repo->companyId(), 'd' => $today]
                    );
                }
            } else {
                $this->repo->execute(
                    'INSERT INTO rateb_cms_visitors (visit_date, page_views, unique_visitors)
                     VALUES (:d, 1, 1)
                     ON DUPLICATE KEY UPDATE page_views = page_views + 1',
                    ['d' => $today]
                );
            }
        } catch (\Throwable $e) {
            error_log('Tenant website visitor track: ' . $e->getMessage());
        }
        unset($pageSlug);
    }

    public function applyRedirectIfAny(string $path): void
    {
        $candidates = $this->redirectCandidates($path);
        [$where, $params] = $this->repo->companyWhere();
        foreach ($candidates as $candidate) {
            $params['p'] = $candidate;
            $row = $this->repo->fetchOne(
                "SELECT * FROM rateb_cms_redirects WHERE {$where} AND from_path = :p AND is_active = 1 LIMIT 1",
                $params
            );
            if ($row === null) {
                continue;
            }
            $to = trim((string) ($row['to_path'] ?? ''));
            if ($to === '') {
                return;
            }
            $code = (int) ($row['status_code'] ?? 301);
            if (!in_array($code, [301, 302, 307, 308], true)) {
                $code = 301;
            }
            if (preg_match('#^https?://#i', $to) === 1) {
                Response::redirect($to, $code);
            }
            Response::redirect(rateb_url(ltrim($to, '/')), $code);
        }
    }

    /** @return list<string> */
    private function redirectCandidates(string $path): array
    {
        $path = '/' . trim($path, '/');
        $out = [];
        $add = static function (string $p) use (&$out): void {
            $p = trim($p);
            if ($p === '') {
                return;
            }
            if ($p[0] !== '/') {
                $p = '/' . $p;
            }
            $out[$p] = $p;
            $out[ltrim($p, '/')] = ltrim($p, '/');
        };
        $add($path);
        if (str_starts_with($path, '/site/')) {
            $add(substr($path, 5));
        } elseif ($path === '/site') {
            $add('/');
            $add('site');
        }

        return array_values($out);
    }
}
