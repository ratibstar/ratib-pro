<?php
declare(strict_types=1);

namespace Rateb\App\Website\Career;

use Rateb\App\Services\CmsService;
use Rateb\App\Website\TenantSeoService;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-06 — Job SEO: canonical, meta, OpenGraph, Schema.org JobPosting, sitemap paths.
 */
final class CareerSeoService
{
    private TenantWebsiteRepository $repo;
    private CareerJobService $jobs;

    public function __construct(?TenantWebsiteRepository $repo = null, ?CareerJobService $jobs = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
        $this->jobs = $jobs ?? new CareerJobService($this->repo);
    }

    /** @return array<string, string> */
    public function portalMeta(string $pageKey, string $defaultTitle): array
    {
        $seo = new TenantSeoService($this->repo);
        $meta = $seo->metaTags('careers', $defaultTitle);
        $meta['canonical'] = rateb_url('site/careers');

        return $meta;
    }

    /** @return array<string, string> */
    public function jobMeta(array $job): array
    {
        $title = CareerJobService::jobTitle($job);
        $descKey = CmsService::localeField('meta_description');
        $titleKey = CmsService::localeField('meta_title');
        $description = trim((string) ($job[$descKey] ?? ''));
        if ($description === '') {
            $description = mb_substr(strip_tags(CmsService::pickLocale($job, 'description')), 0, 300);
        }
        $metaTitle = trim((string) ($job[$titleKey] ?? ''));
        if ($metaTitle === '') {
            $metaTitle = $title;
        }
        $canonical = trim((string) ($job['canonical_url'] ?? ''));
        if ($canonical === '') {
            $canonical = CareerJobService::jobUrl($job);
        }
        $ogImage = trim((string) ($job['og_image'] ?? ''));

        return [
            'title' => $metaTitle,
            'description' => $description,
            'og_title' => $metaTitle,
            'og_description' => $description,
            'og_image' => $ogImage,
            'canonical' => $canonical,
            'twitter_card' => 'summary_large_image',
            'robots' => 'index,follow',
            'schema_json' => $this->jobPostingSchema($job, $canonical, $description),
        ];
    }

    /** @return list<string> */
    public function sitemapPaths(): array
    {
        $paths = ['site/careers', 'site/careers/search'];
        [$where, $params] = $this->repo->companyWhere();
        $jobs = $this->repo->fetchAll(
            "SELECT slug FROM rateb_cms_careers WHERE {$where} AND status = 'open' ORDER BY id DESC LIMIT 500",
            $params
        );
        foreach ($jobs as $job) {
            $slug = trim((string) ($job['slug'] ?? ''));
            if ($slug !== '') {
                $paths[] = 'site/careers/job/' . $slug;
            }
        }
        foreach ($this->jobs->categories() as $cat) {
            $slug = trim((string) ($cat['slug'] ?? ''));
            if ($slug !== '') {
                $paths[] = 'site/careers/category/' . $slug;
            }
        }

        return array_values(array_unique($paths));
    }

    private function jobPostingSchema(array $job, string $canonical, string $description): string
    {
        $title = CareerJobService::jobTitle($job);
        $location = CmsService::pickLocale($job, 'location');
        $city = CmsService::pickLocale($job, 'city');
        if ($city !== '') {
            $location = $city . ($location !== '' ? ', ' . $location : '');
        }
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'JobPosting',
            'title' => $title,
            'description' => $description,
            'datePosted' => (string) ($job['published_at'] ?? date('Y-m-d')),
            'validThrough' => date('Y-m-d', strtotime('+90 days')),
            'employmentType' => strtoupper((string) ($job['employment_type'] ?? 'FULL_TIME')),
            'hiringOrganization' => [
                '@type' => 'Organization',
                'name' => rateb_site_origin(),
                'sameAs' => rateb_site_origin(),
            ],
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => 'RATEB Career ID',
                'value' => (string) ($job['id'] ?? ''),
            ],
            'url' => $canonical,
        ];
        if ($location !== '') {
            $data['jobLocation'] = [
                '@type' => 'Place',
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => $location,
                    'addressCountry' => strtoupper((string) ($job['country_code'] ?? 'SA')),
                ],
            ];
        }
        $salaryMin = $job['salary_min'] ?? null;
        $salaryMax = $job['salary_max'] ?? null;
        if ($salaryMin !== null || $salaryMax !== null) {
            $data['baseSalary'] = [
                '@type' => 'MonetaryAmount',
                'currency' => (string) ($job['salary_currency'] ?? 'SAR'),
                'value' => [
                    '@type' => 'QuantitativeValue',
                    'minValue' => $salaryMin,
                    'maxValue' => $salaryMax,
                    'unitText' => 'MONTH',
                ],
            ];
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);

        return is_string($json) ? $json : '{}';
    }
}
