<?php
declare(strict_types=1);

if (!defined('RATIB_ENV_NO_SESSION')) {
    define('RATIB_ENV_NO_SESSION', true);
}

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err === null || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'RATEB ERP fatal: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line'];
});

try {
    $ratebRootHint = realpath(dirname(__FILE__, 2));
    if ($ratebRootHint === false) {
        $ratebRootHint = dirname(__FILE__, 2);
    }

    require_once dirname(__FILE__, 2) . '/app/Core/Bootstrap.php';

    Rateb\App\Core\Bootstrap::init($ratebRootHint);

    Rateb\App\Core\Auth::bootstrapFromSession();

    $router = new Rateb\App\Core\Router();

    require RATEB_ROOT . '/routes/web.php';
    require RATEB_ROOT . '/routes/marketing.php';
    require RATEB_ROOT . '/routes/cms.php';
    require RATEB_ROOT . '/routes/company.php';
    require RATEB_ROOT . '/routes/api.php';

    require_once RATEB_ROOT . '/app/helpers/Request.php';

    $path = \Rateb\App\Helpers\Request::resolvePath();
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (Throwable $e) {
    error_log('RATEB ERP bootstrap error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'RATEB ERP error: ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
    }
    $debug = (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1');
    $cpMode = defined('RATEB_CP_ENTRY') && RATEB_CP_ENTRY;
    $directPublic = !$cpMode;
    if ($directPublic) {
        return;
    }
    if ($debug || $cpMode) {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        $msg = $e->getMessage();
        $isDbAccess = strpos($msg, '1044') !== false || strpos($msg, '1049') !== false || strpos($msg, 'Access denied') !== false;
        $dbName = function_exists('control_rateb_erp_db_name') ? control_rateb_erp_db_name() : 'outratib_rateb-erp';
        $dbUser = defined('RATEB_DB_USER') ? (string) RATEB_DB_USER : (defined('DB_USER') ? (string) DB_USER : 'outratib_out');
        $assetBase = defined('RATEB_CP_ASSETS_URL') ? (string) RATEB_CP_ASSETS_URL : '/rateb-erp/public/assets';
        echo '<!DOCTYPE html><html lang="ar" dir="rtl" data-theme="light" data-bs-theme="light"><head><meta charset="UTF-8">';
        echo '<link href="' . htmlspecialchars($assetBase . '/css/variables.css', ENT_QUOTES, 'UTF-8') . '" rel="stylesheet">';
        echo '<link href="' . htmlspecialchars($assetBase . '/css/light.css', ENT_QUOTES, 'UTF-8') . '" rel="stylesheet">';
        echo '<style>body{font-family:Tajawal,system-ui,sans-serif;background:#cce0f5;color:#1a3354;margin:0;padding:2rem}</style>';
        echo '</head><body class="rateb-app">';
        echo '<div style="max-width:760px;margin:0 auto;padding:1rem 1.25rem;text-align:right;">';
        echo '<h1 style="font-size:1.35rem;">خطأ نظام رتب ERP</h1>';
        echo '<pre style="background:#e3effc;border:1px solid #b8cfe8;border-radius:8px;padding:1rem;overflow:auto;direction:ltr;text-align:left;">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</pre>';
        if ($isDbAccess) {
            echo '<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:1rem;margin:1rem 0;">';
            echo '<strong>صلاحيات قاعدة البيانات</strong><br>';
            echo 'المستخدم <code style="direction:ltr">' . htmlspecialchars($dbUser, ENT_QUOTES, 'UTF-8') . '</code> ';
            echo 'لا يملك صلاحية على قاعدة <code style="direction:ltr">outratib_rateb-erp</code> (وليس outratib-rateb-erp).<br><br>';
            echo '<strong>في cPanel:</strong><br>1. MySQL® Databases → تأكد أن القاعدة <b>outratib_rateb-erp</b> موجودة<br>';
            echo '2. Add User To Database → <code>outratib_out</code> + <code>outratib_rateb-erp</code><br>';
            echo '3. ALL PRIVILEGES → Make Changes<br><br>';
            echo '4. إن لم ينجح: أنشئ مستخدم MySQL جديد <code>outratib_erp</code> واربطه بالقاعدة فقط، ثم ضع في ملف <code>.env</code> على السيرفر:<br>';
            echo '<code style="direction:ltr;display:block;margin-top:0.5rem">RATEB_ERP_DB_USER=outratib_erp<br>RATEB_ERP_DB_PASS=...</code>';
            echo '</div>';
        }
        if ($cpMode && function_exists('control_rateb_erp_migrate_page_url')) {
            echo '<p><a href="' . htmlspecialchars(control_rateb_erp_migrate_page_url(), ENT_QUOTES, 'UTF-8') . '">فتح إعداد قاعدة بيانات ERP</a></p>';
        }
        echo '</div></body></html>';
    } else {
        echo 'RATEB ERP is temporarily unavailable. Please check server logs or run migrations.';
    }
}
