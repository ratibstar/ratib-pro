<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

echo "step=start\n";
try {
    require dirname(__DIR__) . '/config/bootstrap.php';
    echo "step=bootstrap\n";
    require_once dirname(__DIR__) . '/app/plans.php';
    echo "step=plans\n";
    require_once dirname(__DIR__) . '/app/notify/mail.php';
    echo "step=mail\n";
    require dirname(__DIR__) . '/app/nav.php';
    echo "step=nav\n";
    echo 'has_footer=' . (function_exists('rateb_site_footer') ? 'yes' : 'no') . "\n";
    echo 'has_inbox=' . (function_exists('rateb_support_inbox') ? 'yes' : 'no') . "\n";
    echo 'inbox=' . rateb_support_inbox() . "\n";
    echo 'ui=' . ui_language() . "\n";
    ob_start();
    rateb_site_header('/contact', false, true);
    $header = ob_get_clean();
    echo 'header_bytes=' . strlen($header) . "\n";
    ob_start();
    rateb_site_footer('/contact');
    $footer = ob_get_clean();
    echo 'footer_bytes=' . strlen($footer) . "\n";
    echo "step=ok\n";
} catch (Throwable $e) {
    echo 'error=' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
