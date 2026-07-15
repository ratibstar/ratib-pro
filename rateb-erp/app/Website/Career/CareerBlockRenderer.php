<?php
declare(strict_types=1);

namespace Rateb\App\Website\Career;

use Rateb\App\Core\View;
use Rateb\App\Services\CmsService;

/**
 * Phase WEBSITE-06 — Builder career blocks (single render path; used by WebsiteBlockRenderer).
 */
final class CareerBlockRenderer
{
    private CareerJobService $jobs;

    public function __construct(?CareerJobService $jobs = null)
    {
        $this->jobs = $jobs ?? new CareerJobService();
    }

    /** @param array<string, mixed> $settings */
    public function renderJobs(string $cls, string $title, array $settings): string
    {
        $limit = max(1, min(20, (int) ($settings['limit'] ?? 6)));
        $items = $this->jobs->latest($limit);
        $html = '<div class="' . $cls . ' wb-career-jobs">';
        if ($title !== '') {
            $html .= '<h3 class="wb-block__title">' . View::escape($title) . '</h3>';
        }
        foreach ($items as $job) {
            $html .= $this->jobRow($job);
        }
        $html .= '<a class="wb-block__link" href="' . View::escape(rateb_url('site/careers')) . '">' . View::escape(__('view_all_jobs') ?: 'View all') . '</a>';
        $html .= '</div>';

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function renderFeatured(string $cls, string $title, array $settings): string
    {
        $limit = max(1, min(12, (int) ($settings['limit'] ?? 4)));
        $items = $this->jobs->featured($limit);
        $html = '<div class="' . $cls . ' wb-career-jobs">';
        if ($title !== '') {
            $html .= '<h3 class="wb-block__title">' . View::escape($title) . '</h3>';
        }
        foreach ($items as $job) {
            $html .= $this->jobRow($job, true);
        }
        $html .= '</div>';

        return $html;
    }

    public function renderCategories(string $cls, string $title): string
    {
        $cats = $this->jobs->categories();
        $html = '<div class="' . $cls . '">';
        if ($title !== '') {
            $html .= '<h3 class="wb-block__title">' . View::escape($title) . '</h3>';
        }
        $html .= '<ul class="wb-block__list">';
        foreach ($cats as $cat) {
            $slug = (string) ($cat['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $label = CmsService::pickLocale($cat, 'label');
            $url = rateb_url('site/careers/category/' . rawurlencode($slug));
            $html .= '<li><a href="' . View::escape($url) . '">' . View::escape($label)
                . ' <span>(' . (int) ($cat['job_count'] ?? 0) . ')</span></a></li>';
        }
        $html .= '</ul></div>';

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function renderSearchWidget(string $cls, string $title, array $settings): string
    {
        $ph = rateb_locale() === 'ar'
            ? (string) ($settings['placeholder_ar'] ?? 'ابحث عن وظيفة…')
            : (string) ($settings['placeholder_en'] ?? 'Search jobs…');
        $action = rateb_url('site/careers/search');
        $html = '<div class="' . $cls . ' wb-career-search-widget">';
        if ($title !== '') {
            $html .= '<h3 class="wb-block__title">' . View::escape($title) . '</h3>';
        }
        $html .= '<form method="get" action="' . View::escape($action) . '">';
        $html .= '<input type="search" name="q" placeholder="' . View::escape($ph) . '">';
        $html .= '<button type="submit" class="wb-btn wb-btn--primary">' . View::escape(__('search') ?: 'Search') . '</button>';
        $html .= '</form></div>';

        return $html;
    }

    /** @param array<string, mixed> $settings */
    public function renderCtaApply(string $cls, string $title, string $content, array $settings): string
    {
        $ctaEn = (string) ($settings['cta_label_en'] ?? 'Apply now');
        $ctaAr = (string) ($settings['cta_label_ar'] ?? 'قدّم الآن');
        $cta = rateb_locale() === 'ar' ? $ctaAr : $ctaEn;
        $url = (string) ($settings['cta_url'] ?? rateb_url('site/careers'));
        if ($url !== '' && $url[0] === '/') {
            $url = rateb_url(ltrim($url, '/'));
        }

        return '<div class="' . $cls . ' wb-career-cta">'
            . ($title !== '' ? '<h3>' . View::escape($title) . '</h3>' : '')
            . ($content !== '' ? '<p>' . View::escape($content) . '</p>' : '')
            . '<a class="wb-btn wb-btn--primary" href="' . View::escape($url) . '">' . View::escape($cta) . '</a>'
            . '</div>';
    }

    /** @param array<string, mixed> $settings */
    public function renderRecruiterTeam(string $cls, string $title, array $settings): string
    {
        $members = $settings['members'] ?? [];
        if (!is_array($members)) {
            $members = [];
        }
        $html = '<div class="' . $cls . '">';
        if ($title !== '') {
            $html .= '<h3 class="wb-block__title">' . View::escape($title) . '</h3>';
        }
        $html .= '<ul class="wb-block__list">';
        foreach ($members as $m) {
            if (!is_array($m)) {
                continue;
            }
            $name = rateb_locale() === 'ar'
                ? (string) ($m['name_ar'] ?? $m['name_en'] ?? '')
                : (string) ($m['name_en'] ?? $m['name_ar'] ?? '');
            $role = rateb_locale() === 'ar'
                ? (string) ($m['role_ar'] ?? $m['role_en'] ?? '')
                : (string) ($m['role_en'] ?? $m['role_ar'] ?? '');
            $html .= '<li><strong>' . View::escape($name) . '</strong>';
            if ($role !== '') {
                $html .= ' — ' . View::escape($role);
            }
            $html .= '</li>';
        }
        $html .= '</ul></div>';

        return $html;
    }

    /** @param array<string, mixed> $job */
    private function jobRow(array $job, bool $featured = false): string
    {
        $url = CareerJobService::jobUrl($job);
        $html = '<article class="wb-career-job">';
        $html .= '<h4><a href="' . View::escape($url) . '">' . View::escape(CareerJobService::jobTitle($job)) . '</a></h4>';
        $html .= '<p>' . View::escape(CmsService::pickLocale($job, 'department'));
        $loc = CmsService::pickLocale($job, 'location');
        if ($loc !== '') {
            $html .= ' — ' . View::escape($loc);
        }
        $html .= '</p>';
        if ($featured && !empty($job['featured'])) {
            $html .= '<span class="rateb-career-card__badge">' . View::escape(__('featured') ?: 'Featured') . '</span>';
        }
        $html .= '</article>';

        return $html;
    }
}
