<?php
declare(strict_types=1);

define('RATEB_ROOT', str_replace('\\', '/', realpath(dirname(__DIR__)) ?: dirname(__DIR__)));

try {
    require_once RATEB_ROOT . '/app/Core/Bootstrap.php';

    Rateb\App\Core\Bootstrap::init(RATEB_ROOT);

    Rateb\App\Core\Auth::bootstrapFromSession();

    $router = new Rateb\App\Core\Router();

    require RATEB_ROOT . '/routes/web.php';
    require RATEB_ROOT . '/routes/company.php';
    require RATEB_ROOT . '/routes/api.php';

    require_once RATEB_ROOT . '/app/helpers/Request.php';

    $path = \Rateb\App\Helpers\Request::resolvePath();
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $path);
} catch (Throwable $e) {
    error_log('RATEB ERP bootstrap error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    $debug = (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1');
    $cpMode = defined('RATEB_CP_ENTRY') && RATEB_CP_ENTRY;
    if ($debug || $cpMode) {
        $msg = $e->getMessage();
        $isDbAccess = strpos($msg, '1044') !== false || strpos($msg, '1049') !== false || strpos($msg, 'Access denied') !== false;
        $dbName = function_exists('control_rateb_erp_db_name') ? control_rateb_erp_db_name() : 'outratib_rateb-erp';
        $dbUser = defined('RATEB_DB_USER') ? (string) RATEB_DB_USER : (defined('DB_USER') ? (string) DB_USER : 'outratib_out');
        echo '<div style="font-family:Tajawal,system-ui,sans-serif;max-width:760px;margin:2rem auto;padding:1rem 1.25rem;direction:rtl;text-align:right;">';
        echo '<h1 style="font-size:1.35rem;">خطأ RATEB ERP</h1>';
        echo '<pre style="background:#f5f5f5;padding:1rem;overflow:auto;direction:ltr;text-align:left;">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</pre>';
        if ($isDbAccess) {
            echo '<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:1rem;margin:1rem 0;">';
            echo '<strong>صلاحيات قاعدة البيانات</strong><br>';
            echo 'المستخدم <code style="direction:ltr">' . htmlspecialchars($dbUser, ENT_QUOTES, 'UTF-8') . '</code> ';
            echo 'لا يملك صلاحية على قاعدة <code style="direction:ltr">' . htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') . '</code>.<br><br>';
            echo '<strong>في cPanel:</strong> MySQL® Databases → Add User To Database → اختر المستخدم والقاعدة → ALL PRIVILEGES → Make Changes.';
            echo '</div>';
        }
        if ($cpMode && function_exists('control_rateb_erp_migrate_page_url')) {
            echo '<p><a href="' . htmlspecialchars(control_rateb_erp_migrate_page_url(), ENT_QUOTES, 'UTF-8') . '">فتح إعداد قاعدة بيانات ERP</a></p>';
        }
        echo '</div>';
    } else {
        echo 'RATEB ERP is temporarily unavailable. Please check server logs or run migrations.';
    }
}
