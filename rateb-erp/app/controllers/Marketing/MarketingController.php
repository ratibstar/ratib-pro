<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Marketing;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Models\CmsBlogArticle;
use Rateb\App\Models\CmsCareer;
use Rateb\App\Models\CmsHelpArticle;
use Rateb\App\Models\CmsKbArticle;
use Rateb\App\Models\CmsLead;
use Rateb\App\Models\CmsNewsletterSubscriber;
use Rateb\App\Services\CmsLeadNotificationService;
use Rateb\App\Models\CmsPartner;
use Rateb\App\Models\CmsService as CmsServiceItem;
use Rateb\App\Models\CmsSystemStatus;
use Rateb\App\Services\CmsArticleTagService;
use Rateb\App\Services\CmsService;

final class MarketingController extends Controller
{
    private CmsService $cms;

    public function __construct()
    {
        $this->cms = new CmsService();
    }

    public function home(): void
    {
        if (isset($_GET['open']) && trim((string) $_GET['open']) === 'register') {
            $helper = dirname(__DIR__, 4) . '/includes/rateb-public-base-url.php';
            if (is_file($helper)) {
                require_once $helper;
            }
            $plan = (string) ($_GET['plan'] ?? 'professional');
            $years = isset($_GET['years']) ? (int) $_GET['years'] : 1;
            $url = function_exists('rateb_marketing_register_url')
                ? rateb_marketing_register_url($plan, $years)
                : (rateb_site_origin() . '/site/pricing?register=1&plan=professional#pricing');
            Response::redirect($url);
            return;
        }
        $this->renderPage('home', 'home');
    }

    public function page(string $slug): void
    {
        $allowed = [
            'features', 'solutions', 'industries', 'pricing', 'request-demo', 'contact',
            'about', 'faq', 'blog', 'services', 'reviews', 'partners', 'careers',
            'privacy', 'terms', 'cookies', 'system-status', 'help-center', 'knowledge-base',
        ];
        // WEBSITE-04 — unlimited CMS pages for tenant builders (slug whitelist only for platform defaults).
        if (!in_array($slug, $allowed, true)) {
            $page = $this->cms->pageBySlug($slug);
            if ($page === null || (string) ($page['status'] ?? '') === 'draft') {
                $this->notFound();
                return;
            }
        }
        $this->renderPage($slug, $slug);
    }

    /** Phase WEBSITE-04 — signed preview token (WebsiteKernel stack only). */
    public function preview(string $token): void
    {
        $this->sendMarketingNoCacheHeaders();
        if (!class_exists(\Rateb\App\Website\WebsiteContext::class)
            || \Rateb\App\Website\WebsiteContext::current() === null) {
            $this->notFound();
            return;
        }
        $resolved = (new \Rateb\App\Website\WebsiteVersionService())->resolvePreviewToken($token);
        if ($resolved === null) {
            $this->notFound();
            return;
        }
        $page = $resolved['page'];
        $slug = (string) ($page['slug'] ?? 'home');
        $meta = $this->cms->metaTags($slug, CmsService::pickLocale($page, 'title') . ' (Preview)');
        $builderHtml = (new \Rateb\App\Website\WebsiteBlockRenderer())->renderPage($slug, true);
        $this->view('marketing/builder', [
            'page' => $page,
            'builderHtml' => $builderHtml,
            'isPreview' => true,
            'meta' => $meta,
            'title' => CmsService::pickLocale($page, 'title') . ' (Preview)',
            'menuItems' => $this->cms->menuItems(),
            'footerMenu' => $this->cms->menuItems('footer'),
            'theme' => $this->cms->theme(),
            'analytics' => [],
            'csrf' => Csrf::token(),
            'footerColumns' => $this->cms->footerColumns(),
        ], 'marketing');
    }

    public function blogArticle(string $slug): void
    {
        $stmt = null;
        if (class_exists(\Rateb\App\Website\WebsiteContext::class)
            && \Rateb\App\Website\WebsiteContext::current() !== null) {
            $stmt = (new \Rateb\App\Website\TenantWebsiteService())->articleBySlug($slug);
        }
        if ($stmt === null) {
            $stmt = (new CmsBlogArticle())->findBySlug($slug);
        }
        if ($stmt === null) {
            $this->notFound();
            return;
        }
        $ctx = class_exists(\Rateb\App\Website\WebsiteContext::class)
            ? \Rateb\App\Website\WebsiteContext::current()
            : null;
        if ($ctx !== null && $ctx->isolationEnabled()
            && (int) ($stmt['company_id'] ?? -1) !== $ctx->companyId()) {
            $this->notFound();
            return;
        }
        $this->cms->trackPageView('blog/' . $slug);
        $title = CmsService::pickLocale($stmt, 'title');
        $meta = $this->cms->metaTags('blog', $title);
        $this->view('marketing/blog-article', [
            'article' => $stmt,
            'articleTags' => (new CmsArticleTagService())->tagsForArticle((int) ($stmt['id'] ?? 0)),
            'meta' => $meta,
            'title' => $title,
            'menuItems' => $this->cms->menuItems(),
            'theme' => $this->cms->theme(),
            'analytics' => $this->cms->analytics(),
            'csrf' => Csrf::token(),
        ], 'marketing');
    }

    public function sitemap(): void
    {
        $pages = $this->cms->tenantSitemapPaths();
        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($pages as $p) {
            $loc = rateb_url($p);
            echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc></url>' . "\n";
        }
        echo '</urlset>';
        exit;
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=UTF-8');
        echo $this->cms->robotsContent();
        exit;
    }

    /** Phase WEBSITE-04 — theme tokens as external stylesheet (no inline CSS). */
    public function themeCss(): void
    {
        header('Content-Type: text/css; charset=UTF-8');
        header('Cache-Control: public, max-age=60');
        if (class_exists(\Rateb\App\Website\WebsiteContext::class)
            && \Rateb\App\Website\WebsiteContext::current() !== null) {
            echo (new \Rateb\App\Website\WebsiteThemeEditorService())->cssVariables();
            echo "\n";
        }
        exit;
    }

    private function sendMarketingNoCacheHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private');
        header('Pragma: no-cache');
        header('X-LiteSpeed-Cache-Control: no-cache', false);
    }

    private function renderPage(string $slug, string $template): void
    {
        $this->sendMarketingNoCacheHeaders();
        $page = $this->cms->pageBySlug($slug);
        $viewFile = RATEB_VIEWS_PATH . '/marketing/' . $template . '.php';
        if ($page === null && $slug !== 'home' && !is_file($viewFile)) {
            $this->notFound();
            return;
        }
        $this->cms->trackPageView($slug);
        $defaultTitle = $page ? CmsService::pickLocale($page, 'title') : $this->defaultPageTitle($slug);
        $meta = $this->cms->metaTags($slug, $defaultTitle);
        $data = [
            'page' => $page,
            'content' => $this->cms->pageContent($slug),
            'meta' => $meta,
            'title' => $defaultTitle,
            'menuItems' => $this->cms->menuItems(),
            'footerMenu' => $this->cms->menuItems('footer'),
            'theme' => $this->cms->theme(),
            'analytics' => $this->cms->analytics(),
            'csrf' => Csrf::token(),
            'slides' => $this->cms->activeSlides(),
            'testimonials' => $this->cms->approvedTestimonials(9),
            'articles' => $this->cms->publishedArticles(),
            'faqs' => $this->cms->activeFaqs(8),
            'plans' => $this->cms->publishedPlans(),
            'about' => $this->cms->about(),
            'contact' => $this->cms->contactSettings(),
            'offices' => $this->cms->offices(),
            'footerColumns' => $this->cms->footerColumns(),
        ];
        $data = array_merge($data, $this->pageExtras($slug));
        $tpl = $page ? (string) ($page['template'] ?? $slug) : $slug;
        if ($tpl === 'form' && $slug === 'request-demo') {
            $tpl = 'request-demo';
        }
        if ($tpl === 'default') {
            $tpl = $slug;
        }
        // WEBSITE-04 — builder template uses shared WebsiteBlockRenderer (no duplicated marketing partials).
        if ($tpl === 'builder' || ($page && (string) ($page['template'] ?? '') === 'builder')) {
            $data['builderHtml'] = (new \Rateb\App\Website\WebsiteBlockRenderer())->renderPage($slug);
            $data['isPreview'] = false;
            $this->view('marketing/builder', $data, 'marketing');
            return;
        }
        $viewFile = RATEB_VIEWS_PATH . '/marketing/' . $tpl . '.php';
        if (!is_file($viewFile)) {
            // Fallback: if page has sections, render via builder pipeline.
            $content = $data['content'] ?? [];
            if (is_array($content) && $content !== []) {
                $data['builderHtml'] = (new \Rateb\App\Website\WebsiteBlockRenderer())->renderPage($slug);
                $data['isPreview'] = false;
                $this->view('marketing/builder', $data, 'marketing');
                return;
            }
            $tpl = $slug;
        }
        $this->view('marketing/' . $tpl, $data, 'marketing');
    }

    /** @return array<string, mixed> */
    private function pageExtras(string $slug): array
    {
        switch ($slug) {
            case 'faq':
                return ['allFaqs' => $this->cms->activeFaqs(200)];
            case 'blog':
                return ['allArticles' => $this->cms->queryPublishedArticles(50)];
            case 'services':
                return ['allServices' => (new CmsServiceItem())->all(100, 0)];
            case 'reviews':
                return ['allTestimonials' => $this->cms->approvedTestimonialsAll(50)];
            case 'partners':
                return ['allPartners' => (new CmsPartner())->all(50, 0)];
            case 'careers':
                return ['allCareers' => (new CmsCareer())->all(50, 0)];
            case 'system-status':
                return ['statusItems' => (new CmsSystemStatus())->all(20, 0)];
            case 'help-center':
                return ['helpArticles' => (new CmsHelpArticle())->all(100, 0)];
            case 'knowledge-base':
                return ['kbArticles' => (new CmsKbArticle())->all(100, 0)];
            default:
                return [];
        }
    }

    private function defaultPageTitle(string $slug): string
    {
        $titles = [
            'features' => __('cms_explore_features'),
            'pricing' => __('cms_pricing_preview'),
            'industries' => 'القطاعات',
            'about' => __('cms_about'),
            'contact' => __('cms_contact'),
            'faq' => 'الأسئلة الشائعة',
            'request-demo' => __('cms_request_demo'),
            'services' => __('cms_services'),
            'reviews' => 'آراء العملاء',
            'partners' => 'الشركاء',
            'careers' => 'الوظائف',
            'privacy' => 'الخصوصية',
            'terms' => 'الشروط',
            'cookies' => 'ملفات الارتباط',
            'system-status' => 'حالة النظام',
            'help-center' => 'مركز المساعدة',
            'knowledge-base' => 'قاعدة المعرفة',
        ];
        return $titles[$slug] ?? __('cms_home');
    }

    /** Must stay protected — matches Controller::notFound() (PHP visibility fatal if private). */
    protected function notFound(): void
    {
        http_response_code(404);
        $this->sendMarketingNoCacheHeaders();
        $this->view('marketing/404', [
            'title' => __('cms_not_found'),
            'menuItems' => $this->cms->menuItems(),
            'theme' => $this->cms->theme(),
        ], 'marketing');
    }
}

final class MarketingFormsController extends Controller
{
    public function contact(): void
    {
        $this->handleLead('contact');
    }

    public function demo(): void
    {
        $this->handleLead('demo');
    }

    public function quote(): void
    {
        $this->handleLead('quote');
    }

    public function newsletter(): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('csrf_invalid'));
            $this->redirect(rateb_url('site'));
            return;
        }
        $email = trim((string) $this->input('email', ''));
        $name = trim((string) $this->input('name', ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            SessionManager::flash('error', __('cms_invalid_email'));
            $this->redirect(rateb_url('site'));
            return;
        }
        $model = new CmsNewsletterSubscriber();
        $existing = $model->findByEmail($email);
        if ($existing === null) {
            $model->create([
                'email' => $email,
                'name' => $name,
                'segment' => 'general',
                'status' => 'active',
            ]);
        }
        SessionManager::flash('success', __('cms_newsletter_ok'));
        $this->redirect(rateb_url('site'));
    }

    /** Phase WEBSITE-04 — visual form submit → CRM. */
    public function websiteForm(string $slug): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('csrf_invalid'));
            $this->redirect(rateb_url('site'));
            return;
        }
        if (!class_exists(\Rateb\App\Website\WebsiteContext::class)
            || \Rateb\App\Website\WebsiteContext::current() === null) {
            SessionManager::flash('error', __('cms_form_required'));
            $this->redirect(rateb_url('site'));
            return;
        }
        $fields = $_POST['fields'] ?? [];
        if (!is_array($fields)) {
            $fields = [];
        }
        $result = (new \Rateb\App\Website\WebsiteFormService())->submit(
            $slug,
            $fields,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        );
        if (empty($result['ok'])) {
            SessionManager::flash('error', (string) ($result['message'] ?? __('cms_form_required')));
        } else {
            SessionManager::flash('success', __('cms_lead_ok') ?: __('saved_ok'));
        }
        $this->redirect(rateb_url('site'));
    }

    private function handleLead(string $type): void
    {
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('csrf_invalid'));
            $this->redirect(rateb_url('site/' . ($type === 'demo' ? 'request-demo' : 'contact')));
            return;
        }
        $name = trim((string) $this->input('name', ''));
        $email = trim((string) $this->input('email', ''));
        $phone = trim((string) $this->input('phone', ''));
        $company = trim((string) $this->input('company', ''));
        $message = trim((string) $this->input('message', ''));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            SessionManager::flash('error', __('cms_form_required'));
            $this->redirect(rateb_url('site/' . ($type === 'demo' ? 'request-demo' : 'contact')));
            return;
        }
        $model = new CmsLead();
        try {
            $leadData = [
                'lead_type' => $type,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'company' => $company,
                'message' => $message,
                'status' => 'new',
                'source_page' => $type,
                'ip_address' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            ];
            if (class_exists(\Rateb\App\Website\WebsiteContext::class)
                && \Rateb\App\Website\WebsiteContext::current() !== null
                && \Rateb\App\Website\WebsiteContext::current()->isolationEnabled()) {
                $leadData['company_id'] = \Rateb\App\Website\WebsiteContext::current()->companyId();
            }
            $leadId = $model->create($leadData);
        } catch (\Throwable $e) {
            error_log('CMS lead save: ' . $e->getMessage());
            SessionManager::flash('error', __('cms_lead_save_failed'));
            $this->redirect(rateb_url('site/' . ($type === 'demo' ? 'request-demo' : 'contact')));
            return;
        }
        $leadRow = [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company' => $company,
            'message' => $message,
        ];
        try {
            $notifier = new CmsLeadNotificationService();
            $notifier->notifyStaff($leadId, $type, $leadRow);
            $notifier->notifyCustomer($type, $leadRow);
        } catch (\Throwable $e) {
            error_log('CMS lead email: ' . $e->getMessage());
        }
        SessionManager::flash('success', __('cms_lead_ok'));
        $this->redirect(rateb_url('site/' . ($type === 'demo' ? 'request-demo' : 'contact')));
    }
}

final class MarketingMediaController extends Controller
{
    public function serve(string $file): void
    {
        $file = basename($file);
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
            http_response_code(404);
            exit;
        }

        if (class_exists(\Rateb\App\Website\WebsiteContext::class)
            && \Rateb\App\Website\WebsiteContext::current() !== null) {
            $media = new \Rateb\App\Website\TenantMediaService();
            $row = $media->findByBasename($file);
            $found = $row ? $media->absolutePathForRow($row) : null;
            if ($found === null || !is_file($found)) {
                http_response_code(404);
                exit;
            }
        } else {
            $base = RATEB_STORAGE_PATH . '/cms-media';
            $found = null;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $path) {
                if ($path->getFilename() === $file) {
                    $found = $path->getPathname();
                    break;
                }
            }
            if ($found === null || !is_file($found)) {
                http_response_code(404);
                exit;
            }
        }

        $mime = mime_content_type($found) ?: 'application/octet-stream';
        if (str_contains(strtolower($mime), 'svg')) {
            \Rateb\App\Core\SecurityHeaders::sendRestrictedMediaHeaders($mime);
            readfile($found);
            exit;
        }
        \Rateb\App\Core\SecurityHeaders::send();
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($found));
        readfile($found);
        exit;
    }
}
