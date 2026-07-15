<?php
declare(strict_types=1);

/**
 * Phase WEBSITE-06 — Career Portal & ATS Integration verification gate.
 */
$root = dirname(__DIR__, 2);
$fail = 0;
$check = static function (string $name, bool $ok) use (&$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ": $name\n";
    if (!$ok) {
        $fail++;
    }
};

$files = [
    'migrations/198_website_career_portal.sql',
    'app/Website/Career/CareerJobService.php',
    'app/Website/Career/CareerApplicationService.php',
    'app/Website/Career/CareerPortalAuthService.php',
    'app/Website/Career/CareerSeoService.php',
    'app/Website/Career/CareerNotificationService.php',
    'app/Website/Career/CareerBlockRenderer.php',
    'app/controllers/Marketing/CareerPortalController.php',
    'app/controllers/Marketing/CareerCandidateController.php',
    'app/Core/Middleware/CareerPortalAuthMiddleware.php',
    'views/layouts/marketing-careers.php',
    'views/marketing/careers/index.php',
    'views/marketing/careers/job.php',
    'views/marketing/careers/apply.php',
    'views/marketing/candidate/dashboard.php',
    'public/assets/css/website-careers.css',
    'public/assets/js/website-careers.js',
];
foreach ($files as $rel) {
    $check('exists ' . $rel, is_file($root . '/' . $rel));
}

$mig = (string) file_get_contents($root . '/migrations/198_website_career_portal.sql');
foreach ([
    'rateb_cms_careers',
    'rateb_website_career_portal_users',
    'rateb_website_career_applications',
    'rateb_website_career_saved_jobs',
    'recruitment_candidate_id',
    'website.careers.view',
    'website.careers.manage',
] as $needle) {
    $check('migration has ' . $needle, str_contains($mig, $needle));
}

$appSrc = (string) file_get_contents($root . '/app/Website/Career/CareerApplicationService.php');
$check('apply uses CandidateService', str_contains($appSrc, 'CandidateService'));
$check('apply source website_career', str_contains($appSrc, 'website_career'));
$check('no duplicate job table', !str_contains($mig, 'rateb_recruitment_jobs') && !str_contains($mig, 'CREATE TABLE IF NOT EXISTS rateb_cms_jobs'));

$jobSrc = (string) file_get_contents($root . '/app/Website/Career/CareerJobService.php');
$check('jobs read rateb_cms_careers', str_contains($jobSrc, 'rateb_cms_careers'));
$check('jobs cached', str_contains($jobSrc, '$cache'));

$seoSrc = (string) file_get_contents($root . '/app/Website/Career/CareerSeoService.php');
$check('JobPosting schema', str_contains($seoSrc, 'JobPosting'));
$check('sitemap career paths', str_contains($seoSrc, 'site/careers/job/'));

$routes = (string) file_get_contents($root . '/routes/modules/marketing.php');
$check('careers index route', str_contains($routes, '/site/careers') && str_contains($routes, 'CareerPortalController'));
$check('candidate portal routes', str_contains($routes, '/site/candidate') && str_contains($routes, 'CareerCandidateController'));
$check('career routes before catch-all', strpos($routes, "get('/site/careers'") < strpos($routes, "get('/site/{slug}'"));

$registry = (string) file_get_contents($root . '/app/Website/WebsiteBlockRegistry.php');
foreach (['jobs', 'featured_jobs', 'job_categories', 'job_search', 'cta_apply', 'recruiter_team'] as $block) {
    $check('block registry ' . $block, str_contains($registry, "'" . $block . "'"));
}

$renderer = (string) file_get_contents($root . '/app/Website/WebsiteBlockRenderer.php');
$check('renderer delegates career blocks', str_contains($renderer, 'CareerBlockRenderer'));

$layout = (string) file_get_contents($root . '/views/layouts/marketing-careers.php');
$check('no inline script body in careers layout', !preg_match('/<script(?![^>]*(?:\bsrc=|type="application\/ld\+json"))[^>]*>/i', $layout));
$check('external careers css', str_contains($layout, 'website-careers.css'));
$check('external careers js', str_contains($layout, 'website-careers.js'));

$mw = (string) file_get_contents($root . '/routes/middleware-helpers.php');
$check('career portal middleware helper', str_contains($mw, 'rateb_career_portal_mw'));

$boot = (string) file_get_contents($root . '/app/Core/Bootstrap.php');
$check('website boot loads CareerJobService', preg_match('/function initWebsite[\s\S]*CareerJobService\.php/', $boot) === 1);
$check('website boot omits POS', !preg_match('/function initWebsite[\s\S]*PosModule::init/', $boot));
$check('website boot omits Offline', !preg_match('/function initWebsite[\s\S]*OfflineModule::init/', $boot));

$tenantSeo = (string) file_get_contents($root . '/app/Website/TenantSeoService.php');
$check('tenant sitemap merges careers', str_contains($tenantSeo, 'CareerSeoService'));

$phpFiles = [
    'app/Website/Career/CareerJobService.php',
    'app/Website/Career/CareerApplicationService.php',
    'app/Website/Career/CareerPortalAuthService.php',
    'app/Website/Career/CareerSeoService.php',
    'app/Website/Career/CareerNotificationService.php',
    'app/Website/Career/CareerBlockRenderer.php',
    'app/controllers/Marketing/CareerPortalController.php',
    'app/controllers/Marketing/CareerCandidateController.php',
];
foreach ($phpFiles as $rel) {
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($root . '/' . $rel) . ' 2>&1', $out, $code);
    $check('php -l ' . $rel, $code === 0);
}

require $root . '/app/Website/WebsiteKernel.php';
$check('careers is public path', \Rateb\App\Website\WebsiteKernel::isPublicPath('/site/careers'));
$check('candidate login is public path', \Rateb\App\Website\WebsiteKernel::isPublicPath('/site/candidate/login'));

echo "\nGATE: " . ($fail === 0 ? 'CLEAR' : "BLOCKED ($fail failures)") . "\n";
exit($fail === 0 ? 0 : 1);
