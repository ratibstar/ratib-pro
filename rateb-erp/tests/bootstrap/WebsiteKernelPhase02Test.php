<?php
declare(strict_types=1);

/**
 * Phase WEBSITE-02 — Website Kernel path / module isolation smoke (CLI).
 */
$root = dirname(__DIR__, 2);
require $root . '/app/Website/WebsiteKernel.php';

use Rateb\App\Website\WebsiteKernel;

$fail = 0;
$check = static function (string $name, bool $ok) use (&$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ": $name\n";
    if (!$ok) {
        $fail++;
    }
};

$check('public /', WebsiteKernel::isPublicPath('/'));
$check('public /site/about', WebsiteKernel::isPublicPath('/site/about'));
$check('public /locale/en', WebsiteKernel::isPublicPath('/locale/en'));
$check('ERP /admin blocked', !WebsiteKernel::isPublicPath('/admin'));
$check('ERP /admin/hr blocked', !WebsiteKernel::isPublicPath('/admin/hr'));
$check('ERP /api blocked', !WebsiteKernel::isPublicPath('/api/v1/x'));
$check('ERP /pos blocked', !WebsiteKernel::isPublicPath('/pos'));
$check('ERP /login blocked', !WebsiteKernel::isPublicPath('/login'));

if (!defined('RATEB_WEBSITE_KERNEL')) {
    define('RATEB_WEBSITE_KERNEL', true);
}
require $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

$ids = \Rateb\App\Core\RouteModuleLoader::selectModuleIds('/site/contact');
$check('kernel modules auth+marketing', $ids === ['auth', 'marketing']);

echo $fail === 0 ? "GATE: CLEAR\n" : "GATE: FAIL $fail\n";
exit($fail === 0 ? 0 : 1);
