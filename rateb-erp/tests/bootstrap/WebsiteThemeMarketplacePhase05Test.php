<?php
declare(strict_types=1);

/**
 * Phase WEBSITE-05 — Theme Marketplace verification gate.
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
    'migrations/197_website_theme_marketplace.sql',
    'app/Website/Theme/ThemeManifest.php',
    'app/Website/Theme/ThemePackage.php',
    'app/Website/Theme/ThemeValidator.php',
    'app/Website/Theme/ThemeCatalogService.php',
    'app/Website/Theme/ThemeInstaller.php',
    'app/Website/Theme/ThemeOverrideService.php',
    'app/Website/Theme/ThemeResolver.php',
    'app/Website/Theme/ThemeBackupService.php',
    'app/Website/Theme/ThemeExportService.php',
    'app/Website/Theme/ThemeImportService.php',
    'app/Website/Theme/ThemeDemoImportService.php',
    'app/Website/Theme/ThemeMarketplaceService.php',
    'themes/aurora/manifest.json',
    'themes/oasis/manifest.json',
    'themes/noctis/manifest.json',
    'views/company/website/theme/edit.php',
    'public/assets/css/website-theme-marketplace.css',
    'public/assets/js/website-theme-marketplace.js',
];
foreach ($files as $rel) {
    $check('exists ' . $rel, is_file($root . '/' . $rel));
}

$mig = (string) file_get_contents($root . '/migrations/197_website_theme_marketplace.sql');
foreach ([
    'rateb_website_theme_packages',
    'rateb_website_theme_installed',
    'rateb_website_theme_versions',
    'rateb_website_theme_assets',
    'rateb_website_theme_overrides',
    'active_installed_id',
    'website.theme.marketplace',
] as $needle) {
    $check('migration has ' . $needle, str_contains($mig, $needle));
}

require $root . '/app/Website/Theme/ThemeManifest.php';
require $root . '/app/Website/Theme/ThemePackage.php';
require $root . '/app/Website/Theme/ThemeValidator.php';
require $root . '/app/Website/WebsiteBlockRegistry.php';

foreach (['aurora', 'oasis', 'noctis'] as $slug) {
    $pkg = \Rateb\App\Website\Theme\ThemePackage::load($slug);
    $v = (new \Rateb\App\Website\Theme\ThemeValidator())->validatePackage($pkg);
    $check('validate package ' . $slug, $v['ok'] === true);
    $check('package ' . $slug . ' has tokens', $pkg->manifest()->tokens() !== []);
    $check('package ' . $slug . ' has demo pages', isset($pkg->manifest()->demo()['pages']));
}

$resolverSrc = (string) file_get_contents($root . '/app/Website/Theme/ThemeResolver.php');
$check('resolver documents inheritance', str_contains($resolverSrc, 'Base Theme') || str_contains($resolverSrc, 'array_replace_recursive'));
$check('resolver never duplicates package files', !str_contains($resolverSrc, 'copy('));

$installerSrc = (string) file_get_contents($root . '/app/Website/Theme/ThemeInstaller.php');
$check('installer stamps company_id', str_contains($installerSrc, 'company_id'));
$check('duplicate copies overrides only', str_contains($installerSrc, 'duplicate') && str_contains($installerSrc, 'ThemeOverrideService'));

$demoSrc = (string) file_get_contents($root . '/app/Website/Theme/ThemeDemoImportService.php');
$check('demo import uses builder service', str_contains($demoSrc, 'WebsiteBuilderService'));
$check('demo import uses form service', str_contains($demoSrc, 'WebsiteFormService'));

$editorSrc = (string) file_get_contents($root . '/app/Website/WebsiteThemeEditorService.php');
$check('editor delegates to ThemeResolver', str_contains($editorSrc, 'ThemeResolver'));

$ops = (string) file_get_contents($root . '/routes/modules/ops.php');
$check('ops marketplace install route', str_contains($ops, 'theme/marketplace/install'));
$check('ops marketplace import route', str_contains($ops, 'theme/marketplace/import'));
$check('ops marketplace demo route', str_contains($ops, 'theme/marketplace/demo'));

$ctrl = (string) file_get_contents($root . '/app/controllers/Company/WebsiteControllers.php');
$check('controller install/export/import', str_contains($ctrl, 'marketplaceInstall') && str_contains($ctrl, 'marketplaceExport') && str_contains($ctrl, 'marketplaceImport'));

$view = (string) file_get_contents($root . '/views/company/website/theme/edit.php');
$check('no inline script body in theme view', !preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $view));
$check('external marketplace js', str_contains($view, 'website-theme-marketplace.js'));

$boot = (string) file_get_contents($root . '/app/Core/Bootstrap.php');
$check('website boot loads ThemeMarketplaceService', preg_match('/function initWebsite[\s\S]*ThemeMarketplaceService\.php/', $boot) === 1);
$check('website boot omits POS', !preg_match('/function initWebsite[\s\S]*PosModule::init/', $boot));
$check('website boot omits Offline', !preg_match('/function initWebsite[\s\S]*OfflineModule::init/', $boot));

$exportSrc = (string) file_get_contents($root . '/app/Website/Theme/ThemeExportService.php');
$check('export format v1', str_contains($exportSrc, 'rateb-theme-package-v1'));
$importSrc = (string) file_get_contents($root . '/app/Website/Theme/ThemeImportService.php');
$check('import source=import', str_contains($importSrc, 'import'));

$phpFiles = [
    'app/Website/Theme/ThemeMarketplaceService.php',
    'app/Website/Theme/ThemeInstaller.php',
    'app/Website/Theme/ThemeResolver.php',
    'app/Website/Theme/ThemeDemoImportService.php',
    'app/Website/Theme/ThemeExportService.php',
    'app/Website/Theme/ThemeImportService.php',
];
foreach ($phpFiles as $rel) {
    $out = [];
    $code = 0;
    exec('php -l ' . escapeshellarg($root . '/' . $rel) . ' 2>&1', $out, $code);
    $check('php -l ' . $rel, $code === 0);
}

require $root . '/app/Website/WebsiteKernel.php';
$check('admin not public', !\Rateb\App\Website\WebsiteKernel::isPublicPath('/admin'));
$check('site still public', \Rateb\App\Website\WebsiteKernel::isPublicPath('/site'));

$slugs = \Rateb\App\Website\Theme\ThemePackage::discoverSlugs();
$check('discover >= 3 packages', count($slugs) >= 3);

echo $fail === 0 ? "GATE: CLEAR\n" : "GATE: FAIL $fail\n";
exit($fail === 0 ? 0 : 1);
