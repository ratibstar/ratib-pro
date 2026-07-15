<?php
declare(strict_types=1);

/**
 * Phase WEBSITE-03 — Tenant isolation smoke (no DB required for core gates).
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
    'app/Website/WebsiteContext.php',
    'app/Website/TenantWebsiteRepository.php',
    'app/Website/TenantThemeService.php',
    'app/Website/TenantSeoService.php',
    'app/Website/TenantMenuService.php',
    'app/Website/TenantBlockService.php',
    'app/Website/TenantMediaService.php',
    'app/Website/TenantWebsiteService.php',
    'migrations/195_website_tenant_cms_isolation.sql',
];
foreach ($files as $rel) {
    $check('exists ' . $rel, is_file($root . '/' . $rel));
}

$repoSrc = (string) file_get_contents($root . '/app/Website/TenantWebsiteRepository.php');
$check('repository scopes company_id', str_contains($repoSrc, 'website_company_id'));
$check('repository asserts cross-tenant deny', str_contains($repoSrc, 'Cross-tenant'));

$mediaSrc = (string) file_get_contents($root . '/app/Website/TenantMediaService.php');
$check('media path includes company_id', str_contains($mediaSrc, '/cms-media/\' . $companyId'));

$cmsSrc = (string) file_get_contents($root . '/app/services/CmsService.php');
$check('CmsService delegates to TenantWebsiteService', str_contains($cmsSrc, 'TenantWebsiteService'));

$kernelSrc = (string) file_get_contents($root . '/app/Website/WebsiteKernel.php');
$check('kernel boots WebsiteContext', str_contains($kernelSrc, 'WebsiteContext::bootFromRequest'));

$bootSrc = (string) file_get_contents($root . '/app/Core/Bootstrap.php');
$check('website bootstrap omits POS', !str_contains($bootSrc, 'PosModule::init') || !preg_match('/function initWebsite[\s\S]*PosModule::init/', $bootSrc));
$check('website bootstrap omits Offline', !preg_match('/function initWebsite[\s\S]*OfflineModule::init/', $bootSrc));

$mig = (string) file_get_contents($root . '/migrations/195_website_tenant_cms_isolation.sql');
$check('migration covers pages', str_contains($mig, 'rateb_cms_pages'));
$check('migration covers media', str_contains($mig, 'rateb_cms_media'));
$check('migration covers theme', str_contains($mig, 'rateb_cms_theme'));
$check('migration covers seo', str_contains($mig, 'rateb_cms_seo'));

require $root . '/app/Website/WebsiteKernel.php';
$check('admin not public path', !\Rateb\App\Website\WebsiteKernel::isPublicPath('/admin'));
$check('site is public path', \Rateb\App\Website\WebsiteKernel::isPublicPath('/site/about'));

echo $fail === 0 ? "GATE: CLEAR\n" : "GATE: FAIL $fail\n";
exit($fail === 0 ? 0 : 1);
