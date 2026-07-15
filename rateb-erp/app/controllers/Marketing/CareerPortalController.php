<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Marketing;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Response;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\CmsService;
use Rateb\App\Website\Career\CareerApplicationService;
use Rateb\App\Website\Career\CareerJobService;
use Rateb\App\Website\Career\CareerPortalAuthService;
use Rateb\App\Website\Career\CareerSeoService;
use Rateb\App\Website\WebsiteContext;

/**
 * Phase WEBSITE-06 — Public career portal (jobs from rateb_cms_careers / ATS).
 */
final class CareerPortalController extends Controller
{
    private function ensureWebsite(): bool
    {
        if (!class_exists(WebsiteContext::class) || WebsiteContext::current() === null) {
            $this->notFound();
            return false;
        }

        return true;
    }

    public function index(): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        $jobs = new CareerJobService();
        $seo = new CareerSeoService();
        $this->renderCareer('marketing/careers/index', __('careers') ?: 'Careers', [
            'featuredJobs' => $jobs->featured(6),
            'latestJobs' => $jobs->latest(8),
            'categories' => $jobs->categories(),
            'meta' => $seo->portalMeta('home', __('careers') ?: 'Careers'),
        ]);
    }

    public function search(): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $jobs = new CareerJobService();
        $result = $jobs->search($q, null, $page, 12);
        $seo = new CareerSeoService();
        $this->renderCareer('marketing/careers/search', __('job_search') ?: 'Job Search', [
            'query' => $q,
            'result' => $result,
            'categories' => $jobs->categories(),
            'meta' => $seo->portalMeta('search', __('job_search') ?: 'Job Search'),
        ]);
    }

    public function category(string $slug): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        $jobs = new CareerJobService();
        $result = $jobs->search('', $slug, 1, 24);
        if ($result['total'] < 1) {
            $this->notFound();
            return;
        }
        $label = $slug;
        foreach ($jobs->categories() as $cat) {
            if ((string) ($cat['slug'] ?? '') === $slug) {
                $label = CmsService::pickLocale($cat, 'label');
                break;
            }
        }
        $seo = new CareerSeoService();
        $this->renderCareer('marketing/careers/category', $label, [
            'categorySlug' => $slug,
            'categoryLabel' => $label,
            'result' => $result,
            'meta' => $seo->portalMeta('category', $label),
        ]);
    }

    public function job(string $slug): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        $jobs = new CareerJobService();
        $job = $jobs->findBySlug($slug);
        if ($job === null || (string) ($job['status'] ?? '') !== 'open') {
            $this->notFound();
            return;
        }
        $seo = new CareerSeoService();
        $auth = new CareerPortalAuthService();
        $this->renderCareer('marketing/careers/job', CareerJobService::jobTitle($job), [
            'job' => $job,
            'relatedJobs' => $jobs->related((int) $job['id'], (string) ($job['category_slug'] ?? ''), 4),
            'portalUser' => $auth->currentUser(),
            'meta' => $seo->jobMeta($job),
        ]);
    }

    public function applyForm(string $slug): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        $jobs = new CareerJobService();
        $job = $jobs->findBySlug($slug);
        if ($job === null || (string) ($job['status'] ?? '') !== 'open') {
            $this->notFound();
            return;
        }
        $auth = new CareerPortalAuthService();
        $seo = new CareerSeoService();
        $this->renderCareer('marketing/careers/apply', __('apply_online') ?: 'Apply Online', [
            'job' => $job,
            'portalUser' => $auth->currentUser(),
            'meta' => $seo->jobMeta($job),
        ]);
    }

    public function apply(string $slug): void
    {
        if (!$this->ensureWebsite()) {
            return;
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            Response::redirect(rateb_url('site/careers/job/' . rawurlencode($slug) . '/apply'));
            return;
        }
        $resume = isset($_FILES['resume']) && is_array($_FILES['resume']) ? $_FILES['resume'] : null;
        $result = (new CareerApplicationService())->submit($slug, $_POST, $resume);
        if (!($result['ok'] ?? false)) {
            SessionManager::flash('error', (string) ($result['error'] ?? 'application_failed'));
            Response::redirect(rateb_url('site/careers/job/' . rawurlencode($slug) . '/apply'));
            return;
        }
        SessionManager::flash('success', __('application_submitted') ?: 'Application submitted successfully');
        Response::redirect(rateb_url('site/candidate'));
    }

    /** @param array<string, mixed> $extra */
    private function renderCareer(string $view, string $title, array $extra = []): void
    {
        $cms = new CmsService();
        $this->view($view, array_merge([
            'title' => $title,
            'menuItems' => $cms->menuItems(),
            'footerMenu' => $cms->menuItems('footer'),
            'theme' => $cms->theme(),
            'analytics' => $cms->analytics(),
            'csrf' => Csrf::token(),
            'isCareerPage' => true,
        ], $extra), 'marketing-careers');
    }
}
