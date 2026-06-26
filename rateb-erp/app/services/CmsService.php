<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\HtmlSanitizer;
use Rateb\App\Core\Response;
use Rateb\App\Models\CmsAbout;
use Rateb\App\Models\CmsAnalytics;
use Rateb\App\Models\CmsBlock;
use Rateb\App\Models\CmsBlogArticle;
use Rateb\App\Models\CmsContactSettings;
use Rateb\App\Models\CmsFaq;
use Rateb\App\Models\CmsMenu;
use Rateb\App\Models\CmsMenuItem;
use Rateb\App\Models\CmsPage;
use Rateb\App\Models\CmsPartner;
use Rateb\App\Models\CmsRobots;
use Rateb\App\Models\CmsSeo;
use Rateb\App\Models\CmsSection;
use Rateb\App\Models\CmsSlide;
use Rateb\App\Models\CmsTestimonial;
use Rateb\App\Models\CmsTheme;
use Rateb\App\Models\CmsVisitor;
use Rateb\App\Models\Plan;

final class CmsService
{
    /** @var array<string, mixed>|null */
    private static ?array $themeCache = null;

    public static function localeField(string $base): string
    {
        return rateb_locale() === 'ar' ? $base . '_ar' : $base . '_en';
    }

    /** @param array<string, mixed> $row */
    public static function pickLocale(array $row, string $base): string
    {
        $field = self::localeField($base);
        $fallback = $base . '_en';
        $val = trim((string) ($row[$field] ?? ''));
        if ($val !== '') {
            return $val;
        }
        return (string) ($row[$fallback] ?? '');
    }

    public static function sanitizeHtml(string $html): string
    {
        return HtmlSanitizer::sanitizeRichHtml($html);
    }

    /** @return array<int, array<string, mixed>> */
    public function footerColumns(): array
    {
        $stmt = Database::connection()->query(
            'SELECT * FROM rateb_cms_footer_columns ORDER BY sort_order ASC, id ASC'
        );
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    /** @return array<int, array<string, mixed>> */
    public function offices(): array
    {
        $stmt = Database::connection()->query(
            'SELECT * FROM rateb_cms_offices ORDER BY sort_order ASC, id ASC'
        );
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    public function trackPageView(string $pageSlug): void
    {
        try {
            $db = Database::connection();
            $today = date('Y-m-d');
            $stmt = $db->prepare(
                'INSERT INTO rateb_cms_visitors (visit_date, page_views, unique_visitors)
                 VALUES (:d, 1, 1)
                 ON DUPLICATE KEY UPDATE page_views = page_views + 1'
            );
            $stmt->execute(['d' => $today]);
        } catch (\Throwable $e) {
            error_log('CMS visitor track: ' . $e->getMessage());
        }
    }

    public function pageBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rateb_cms_pages WHERE slug = :s AND status = :st LIMIT 1');
        $stmt->execute(['s' => $slug, 'st' => 'published']);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function sectionsForPage(string $pageSlug): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_cms_sections WHERE page_slug = :p AND is_active = 1 ORDER BY sort_order ASC'
        );
        $stmt->execute(['p' => $pageSlug]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function blocksForSection(int $sectionId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_cms_blocks WHERE section_id = :id AND is_active = 1 ORDER BY sort_order ASC'
        );
        $stmt->execute(['id' => $sectionId]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    public function pageContent(string $pageSlug): array
    {
        $sections = $this->sectionsForPage($pageSlug);
        $out = [];
        foreach ($sections as $section) {
            $key = (string) $section['section_key'];
            $out[$key] = [
                'section' => $section,
                'blocks' => $this->blocksForSection((int) $section['id']),
            ];
        }
        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    public function menuItems(string $menuSlug = 'main'): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT i.* FROM rateb_cms_menu_items i
             JOIN rateb_cms_menus m ON m.id = i.menu_id
             WHERE m.slug = :slug AND i.is_active = 1
             ORDER BY i.sort_order ASC, i.id ASC'
        );
        $stmt->execute(['slug' => $menuSlug]);
        $rows = $stmt->fetchAll() ?: [];
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

    /** @return array<string, mixed>|null */
    public function seoForPage(string $pageSlug): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rateb_cms_seo WHERE page_slug = :s LIMIT 1');
        $stmt->execute(['s' => $pageSlug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, string> */
    public function metaTags(string $pageSlug, string $defaultTitle): array
    {
        $seo = $this->seoForPage($pageSlug);
        $titleKey = self::localeField('meta_title');
        $descKey = self::localeField('meta_description');
        $ogTitleKey = self::localeField('og_title');
        $ogDescKey = self::localeField('og_description');

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

        return [
            'title' => $title,
            'description' => $description,
            'og_title' => $ogTitle,
            'og_description' => $ogDesc,
            'og_image' => $ogImage,
            'canonical' => $canonical,
            'twitter_card' => $seo ? (string) ($seo['twitter_card'] ?? 'summary_large_image') : 'summary_large_image',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function activeSlides(): array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_cms_slides WHERE is_active = 1
             AND (starts_at IS NULL OR starts_at <= :now)
             AND (ends_at IS NULL OR ends_at >= :now2)
             ORDER BY sort_order ASC'
        );
        $stmt->execute(['now' => $now, 'now2' => $now]);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function approvedTestimonials(int $limit = 6): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_cms_testimonials WHERE status = :s ORDER BY sort_order ASC LIMIT :lim'
        );
        $stmt->bindValue(':s', 'approved');
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function publishedArticles(int $limit = 3): array
    {
        return $this->queryPublishedArticles($limit);
    }

    /** @return array<int, array<string, mixed>> */
    public function queryPublishedArticles(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM rateb_cms_blog_articles WHERE status = 'published'
             AND (published_at IS NULL OR published_at <= NOW())
             ORDER BY published_at DESC, id DESC LIMIT :lim"
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function activeFaqs(int $limit = 5): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_cms_faqs WHERE is_active = 1 ORDER BY sort_order ASC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function approvedTestimonialsAll(int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM rateb_cms_testimonials WHERE status = :s ORDER BY sort_order ASC LIMIT :lim'
        );
        $stmt->bindValue(':s', 'approved');
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function publishedPlans(): array
    {
        try {
            return (new Plan())->getActive();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @return array<string, mixed>|null */
    public function about(): ?array
    {
        $stmt = Database::connection()->query('SELECT * FROM rateb_cms_about ORDER BY id ASC LIMIT 1');
        $row = $stmt ? $stmt->fetch() : false;
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function contactSettings(): ?array
    {
        $stmt = Database::connection()->query('SELECT * FROM rateb_cms_contact_settings ORDER BY id ASC LIMIT 1');
        $row = $stmt ? $stmt->fetch() : false;
        return $row ?: null;
    }

    /** @return array<string, mixed> */
    public function theme(): array
    {
        if (self::$themeCache !== null) {
            return self::$themeCache;
        }
        $stmt = Database::connection()->query('SELECT * FROM rateb_cms_theme ORDER BY id ASC LIMIT 1');
        $row = $stmt ? $stmt->fetch() : false;
        self::$themeCache = $row ?: [
            'primary_color' => '#1a5fb4',
            'secondary_color' => '#3584e4',
            'font_family' => 'Tajawal',
        ];
        return self::$themeCache;
    }

    /** @return array<string, mixed>|null */
    public function analytics(): ?array
    {
        $stmt = Database::connection()->query('SELECT * FROM rateb_cms_analytics ORDER BY id ASC LIMIT 1');
        $row = $stmt ? $stmt->fetch() : false;
        return $row ?: null;
    }

    public function robotsContent(): string
    {
        $stmt = Database::connection()->query('SELECT content FROM rateb_cms_robots ORDER BY id ASC LIMIT 1');
        $row = $stmt ? $stmt->fetch() : false;
        return $row ? (string) $row['content'] : "User-agent: *\nAllow: /";
    }

    /** @return array<string, int> */
    public function dashboardStats(): array
    {
        $db = Database::connection();
        $stats = [
            'visitors_today' => 0,
            'leads_total' => 0,
            'leads_new' => 0,
            'contact_requests' => 0,
            'demo_requests' => 0,
            'newsletter' => 0,
            'blog_published' => 0,
        ];
        try {
            $stmt = $db->query("SELECT page_views FROM rateb_cms_visitors WHERE visit_date = CURDATE() LIMIT 1");
            $row = $stmt ? $stmt->fetch() : false;
            $stats['visitors_today'] = $row ? (int) $row['page_views'] : 0;

            $stmt = $db->query('SELECT COUNT(*) c FROM rateb_cms_leads');
            $stats['leads_total'] = (int) ($stmt->fetch()['c'] ?? 0);

            $stmt = $db->query("SELECT COUNT(*) c FROM rateb_cms_leads WHERE status = 'new'");
            $stats['leads_new'] = (int) ($stmt->fetch()['c'] ?? 0);

            $stmt = $db->query("SELECT COUNT(*) c FROM rateb_cms_leads WHERE lead_type = 'contact'");
            $stats['contact_requests'] = (int) ($stmt->fetch()['c'] ?? 0);

            $stmt = $db->query("SELECT COUNT(*) c FROM rateb_cms_leads WHERE lead_type = 'demo'");
            $stats['demo_requests'] = (int) ($stmt->fetch()['c'] ?? 0);

            $stmt = $db->query("SELECT COUNT(*) c FROM rateb_cms_newsletter_subscribers WHERE status = 'active'");
            $stats['newsletter'] = (int) ($stmt->fetch()['c'] ?? 0);

            $stmt = $db->query("SELECT COUNT(*) c FROM rateb_cms_blog_articles WHERE status = 'published'");
            $stats['blog_published'] = (int) ($stmt->fetch()['c'] ?? 0);
        } catch (\Throwable $e) {
            error_log('CMS dashboard stats: ' . $e->getMessage());
        }
        return $stats;
    }

    public function checkRedirect(string $path): ?array
    {
        foreach ($this->redirectPathCandidates($path) as $candidate) {
            $stmt = Database::connection()->prepare(
                'SELECT * FROM rateb_cms_redirects WHERE from_path = :p AND is_active = 1 LIMIT 1'
            );
            $stmt->execute(['p' => $candidate]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }
        return null;
    }

    public function applyRedirectIfAny(string $path): void
    {
        $row = $this->checkRedirect($path);
        if ($row === null) {
            return;
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

    /** @return array<int, string> */
    private function redirectPathCandidates(string $path): array
    {
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            $path = '/';
        }
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
        if (strpos($path, '/site/') === 0) {
            $add(substr($path, 5));
        } elseif ($path === '/site') {
            $add('/');
            $add('site');
        }
        return array_values($out);
    }
}
