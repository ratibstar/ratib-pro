<?php
declare(strict_types=1);
/**
 * Temporary boot diagnostic — remove after cloud 500 is resolved.
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$root = dirname(__DIR__);
$out = [];
$step = 'start';

try {
    $step = 'initMinimal';
    define('RATEB_ENV_NO_SESSION', true);
    require_once $root . '/app/Core/Bootstrap.php';
    \Rateb\App\Core\Bootstrap::initMinimal($root);
    $out[] = 'initMinimal=ok mode=' . \Rateb\App\Core\HybridRuntime::mode();

    $step = 'initFull';
    // Full init needs session; use a throwaway session name
    if (session_status() === PHP_SESSION_NONE) {
        session_name('rateb_diag');
    }
    // Re-enter full boot path pieces that login uses
    require_once $root . '/app/Core/Database.php';
    $pdo = \Rateb\App\Core\Database::connection();
    $out[] = 'db=ok';

    $step = 'marketingHome';
    if (class_exists(\Rateb\App\Controllers\Marketing\MarketingController::class)) {
        $out[] = 'MarketingController=loaded';
    } else {
        require_once $root . '/app/controllers/Marketing/MarketingController.php';
        $out[] = 'MarketingController=required';
    }

    $step = 'loginController';
    if (!class_exists(\Rateb\App\Controllers\Shared\LoginController::class)) {
        // rely on autoload
    }
    $out[] = 'LoginController=' . (class_exists(\Rateb\App\Controllers\Shared\LoginController::class) ? 'ok' : 'missing');

    $step = 'authLayout';
    $locale = function_exists('rateb_locale') ? rateb_locale() : 'ar';
    $out[] = 'locale=' . $locale;
    $out[] = 'rtl=' . (function_exists('rateb_is_rtl') && rateb_is_rtl() ? '1' : '0');
    $out[] = 'bs=' . rateb_bootstrap_css();
    $out[] = 'fa=' . rateb_fontawesome_css();

    $step = 'renderLoginView';
    ob_start();
    $title = 'diag';
    $pageContent = '<p>diag</p>';
    include $root . '/views/layouts/auth.php';
    $html = ob_get_clean();
    $out[] = 'authLayoutLen=' . strlen($html);
    $out[] = 'authHasVendor=' . (str_contains($html, 'vendor/bootstrap') ? '1' : '0');

    $step = 'renderMarketingLayout';
    ob_start();
    $meta = ['title' => 'diag'];
    $theme = [];
    $analytics = [];
    $pageContent = '<p>diag</p>';
    include $root . '/views/layouts/marketing.php';
    $html2 = ob_get_clean();
    $out[] = 'marketingLayoutLen=' . strlen($html2);
    $out[] = 'mktHasHeroClass=' . (str_contains($html2, 'rateb-marketing') ? '1' : '0');

    $step = 'done';
    $out[] = 'ALL_OK';
} catch (Throwable $e) {
    $out[] = 'FAIL_STEP=' . $step;
    $out[] = 'FAIL ' . $e->getMessage();
    $out[] = 'at ' . $e->getFile() . ':' . $e->getLine();
    $out[] = substr($e->getTraceAsString(), 0, 1500);
}

echo implode("\n", $out) . "\n";
