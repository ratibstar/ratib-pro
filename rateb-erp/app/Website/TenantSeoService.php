<?php
declare(strict_types=1);

namespace Rateb\App\Website;

use Rateb\App\Services\CmsService;

/**
 * Phase WEBSITE-03 — Per-tenant SEO (meta, OG, Twitter, schema, sitemap, robots).
 */
final class TenantSeoService
{
    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return array<string, mixed>|null */
    public function seoRow(string $pageSlug): ?array
    {
        [$where, $params] = $this->repo->companyWhere();
        $params['s'] = $pageSlug;

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_cms_seo WHERE {$where} AND page_slug = :s LIMIT 1",
            $params
        );
    }

    /** @return array<string, string> */
    public function metaTags(string $pageSlug, string $defaultTitle): array
    {
        $seo = $this->seoRow($pageSlug);
        $titleKey = CmsService::localeField('meta_title');
        $descKey = CmsService::localeField('meta_description');
        $ogTitleKey = CmsService::localeField('og_title');
        $ogDescKey = CmsService::localeField('og_description');

        $title = $seo ? trim((string) ($seo[$titleKey] ?? '')) : '';
        if ($title === '') {
            $title = $defaultTitle;
        }
        $description = $seo ? trim((string) ($seo[$descKey] ?? '')) : '';
        $ogTitle = $seo ? trim((string) ($seo[$ogTitleKey] ?? '')) : $title;
        $ogDesc = $seo ? trim((string) ($seo[$ogDescKey] ?? '')) : $description;
        $ogImage = $seo ? (string) ($seo['og_image'] ?? '') : '';
        $canonical = $seo ? (string) ($seo['canonical_url'] ?? '') : '';
        if ($canonical === '') {
            $canonical = rateb_url('site/' . ($pageSlug === 'home' ? '' : $pageSlug));
        }

        $meta = [
            'title' => $title,
            'description' => $description,
            'og_title' => $ogTitle,
            'og_description' => $ogDesc,
            'og_image' => $ogImage,
            'canonical' => $canonical,
            'twitter_card' => $seo ? (string) ($seo['twitter_card'] ?? 'summary_large_image') : 'summary_large_image',
            'schema_json' => $this->organizationSchemaJson($title, $description, $canonical),
        ];

        return $meta;
    }

    public function robotsContent(): string
    {
        [$where, $params] = $this->repo->companyWhere();
        $row = $this->repo->fetchOne(
            "SELECT content FROM rateb_cms_robots WHERE {$where} ORDER BY id ASC LIMIT 1",
            $params
        );
        if ($row) {
            return (string) $row['content'];
        }
        $origin = rateb_site_origin();

        return "User-agent: *\nAllow: /\nSitemap: {$origin}/site/sitemap.xml\n";
    }

    /** @return list<string> */
    public function sitemapPaths(): array
    {
        [$where, $params] = $this->repo->companyWhere();
        $pages = $this->repo->fetchAll(
            "SELECT slug FROM rateb_cms_pages WHERE {$where} AND status = 'published' ORDER BY sort_order ASC, id ASC",
            $params
        );
        $paths = ['site'];
        foreach ($pages as $page) {
            $slug = trim((string) ($page['slug'] ?? ''));
            if ($slug === '' || $slug === 'home') {
                continue;
            }
            $paths[] = 'site/' . $slug;
        }
        $articles = $this->repo->fetchAll(
            "SELECT slug FROM rateb_cms_blog_articles WHERE {$where} AND status = 'published'
             AND (published_at IS NULL OR published_at <= NOW()) ORDER BY published_at DESC LIMIT 200",
            $params
        );
        foreach ($articles as $article) {
            $slug = trim((string) ($article['slug'] ?? ''));
            if ($slug !== '') {
                $paths[] = 'site/blog/' . $slug;
            }
        }

        return array_values(array_unique($paths));
    }

    private function organizationSchemaJson(string $title, string $description, string $canonical): string
    {
        $theme = (new TenantThemeService($this->repo))->theme();
        $contact = (new TenantThemeService($this->repo))->contact();
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $title,
            'url' => $canonical !== '' ? $canonical : rateb_site_origin(),
            'description' => $description,
        ];
        $logo = trim((string) ($theme['logo_path'] ?? ''));
        if ($logo !== '') {
            $data['logo'] = $logo;
        }
        $phone = trim((string) ($contact['phone'] ?? ''));
        if ($phone !== '') {
            $data['telephone'] = $phone;
        }
        $email = trim((string) ($contact['email'] ?? ''));
        if ($email !== '') {
            $data['email'] = $email;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);

        return is_string($json) ? $json : '{}';
    }
}
