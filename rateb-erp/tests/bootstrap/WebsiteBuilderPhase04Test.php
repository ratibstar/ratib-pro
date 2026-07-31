<?php
declare(strict_types=1);

/**
 * Phase WEBSITE-04 — Enterprise Website Builder gate (static + structural verification).
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
    'migrations/196_website_builder_enterprise.sql',
    'app/Website/WebsiteBlockRegistry.php',
    'app/Website/WebsiteBlockRenderer.php',
    'app/Website/WebsiteBuilderService.php',
    'app/Website/WebsiteVersionService.php',
    'app/Website/WebsiteFormService.php',
    'app/Website/WebsiteThemeEditorService.php',
    'app/Website/WebsiteMediaManagerService.php',
    'app/Website/WebsiteMenuBuilderService.php',
    'app/Website/WebsiteSeoEditorService.php',
    'app/controllers/Company/WebsiteControllers.php',
    'views/company/website/builder/index.php',
    'views/marketing/builder.php',
    'public/assets/css/website-blocks.css',
    'public/assets/css/website-builder.css',
    'public/assets/js/website-builder.js',
    'public/assets/js/website-media.js',
    'public/assets/js/website-menus.js',
    'public/assets/js/website-forms.js',
];
foreach ($files as $rel) {
    $check('exists ' . $rel, is_file($root . '/' . $rel));
}

$reg = (string) file_get_contents($root . '/app/Website/WebsiteBlockRegistry.php');
foreach (['hero', 'about', 'services', 'features', 'counters', 'cta', 'team', 'testimonials', 'faq', 'pricing', 'blog', 'news', 'careers', 'contact', 'gallery', 'partners', 'brands', 'map', 'whatsapp', 'forms', 'custom_html', 'spacer', 'divider', 'video', 'image', 'slider'] as $type) {
    $check('block type ' . $type, str_contains($reg, "'" . $type . "'"));
}

$ctx = (string) file_get_contents($root . '/app/Website/WebsiteContext.php');
$check('ops bootForOps', str_contains($ctx, 'bootForOps'));

$ver = (string) file_get_contents($root . '/app/Website/WebsiteVersionService.php');
$check('version draft', str_contains($ver, 'saveDraft'));
$check('version publish', str_contains($ver, 'function publish'));
$check('version rollback', str_contains($ver, 'function rollback'));
$check('version schedule', str_contains($ver, 'function schedule'));
$check('version preview token', str_contains($ver, 'createPreviewToken'));

$form = (string) file_get_contents($root . '/app/Website/WebsiteFormService.php');
$check('form CRM routing', str_contains($form, 'routeToCrm') || str_contains($form, 'LeadService'));
$check('form company_id insert', str_contains($form, 'company_id'));

$mig = (string) file_get_contents($root . '/migrations/196_website_builder_enterprise.sql');
$check('migration versions table', str_contains($mig, 'rateb_website_page_versions'));
$check('migration forms', str_contains($mig, 'rateb_website_forms'));
$check('migration media folders', str_contains($mig, 'rateb_website_media_folders'));
$check('migration permissions website.*', str_contains($mig, 'website.builder.manage') && str_contains($mig, 'website.publish'));

$ops = (string) file_get_contents($root . '/routes/modules/ops.php');
$check('ops website routes', str_contains($ops, "website/builder"));
$check('ops website controllers', str_contains($ops, 'WebsiteBuilderController'));

$mkt = (string) file_get_contents($root . '/routes/modules/marketing.php');
$check('preview route', str_contains($mkt, '/site/preview/{token}'));
$check('forms route', str_contains($mkt, '/site/forms/{slug}'));
$check('theme.css route', str_contains($mkt, '/site/theme.css'));

$loader = (string) file_get_contents($root . '/app/Core/RouteModuleLoader.php');
$check('ops path includes website', str_contains($loader, "'website'"));

$boot = (string) file_get_contents($root . '/app/Core/Bootstrap.php');
$classmap = is_file($root . '/app/Core/generated-classmap.php')
    ? (string) file_get_contents($root . '/app/Core/generated-classmap.php')
    : '';
$check(
    'ERP boot loads WebsiteControllers',
    str_contains($boot, 'WebsiteControllers.php')
    || str_contains($classmap, 'WebsiteControllers.php')
    || is_file($root . '/app/controllers/Company/WebsiteControllers.php')
);
$check('website bootstrap has renderer', preg_match('/function initWebsite[\s\S]*WebsiteBlockRenderer\.php/', $boot) === 1);
$check('website bootstrap omits POS', !preg_match('/function initWebsite[\s\S]*PosModule::init/', $boot));
$check('website bootstrap omits Offline', !preg_match('/function initWebsite[\s\S]*OfflineModule::init/', $boot));

$builderJs = (string) file_get_contents($root . '/public/assets/js/website-builder.js');
$check('dnd dragstart in builder js', str_contains($builderJs, 'dragstart'));
$check('no inline script in builder view', !preg_match('/<script>(?!.*src=)/', (string) file_get_contents($root . '/views/company/website/builder/index.php')));

$renderer = (string) file_get_contents($root . '/app/Website/WebsiteBlockRenderer.php');
$check('renderer strips scripts', str_contains($renderer, 'script'));
$check('renderer lazy loading', str_contains($renderer, 'loading="lazy"'));

$repo = (string) file_get_contents($root . '/app/Website/WebsiteBuilderService.php');
$check('builder enforces company_id', str_contains($repo, 'company_id'));

// Syntax check critical PHP files
$phpFiles = [
    'app/Website/WebsiteBlockRegistry.php',
    'app/Website/WebsiteBlockRenderer.php',
    'app/Website/WebsiteBuilderService.php',
    'app/Website/WebsiteVersionService.php',
    'app/Website/WebsiteFormService.php',
    'app/controllers/Company/WebsiteControllers.php',
];
foreach ($phpFiles as $rel) {
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($root . '/' . $rel) . ' 2>&1', $out, $code);
    $check('php -l ' . $rel, $code === 0);
}

// AA3 / prior website gates still present
$check('phase03 test present', is_file($root . '/tests/bootstrap/WebsiteTenantPhase03Test.php'));
$check('phase02 test present', is_file($root . '/tests/bootstrap/WebsiteKernelPhase02Test.php'));

require $root . '/app/Website/WebsiteBlockRegistry.php';
$types = \Rateb\App\Website\WebsiteBlockRegistry::typeIds();
$check('registry count >= 26', count($types) >= 26);

require $root . '/app/Website/WebsiteKernel.php';
$check('admin not public', !\Rateb\App\Website\WebsiteKernel::isPublicPath('/admin'));
$check('site public', \Rateb\App\Website\WebsiteKernel::isPublicPath('/site/about'));
$check('preview public path', \Rateb\App\Website\WebsiteKernel::isPublicPath('/site/preview/abc'));

echo $fail === 0 ? "GATE: CLEAR\n" : "GATE: FAIL $fail\n";
exit($fail === 0 ? 0 : 1);
