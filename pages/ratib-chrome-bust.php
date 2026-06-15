<?php
/**
 * Chrome / cache diagnostic (reachable under /pages/ when root file is missing).
 * https://rateb.sa/pages/ratib-chrome-bust.php
 */
declare(strict_types=1);

$rootScript = dirname(__DIR__) . '/ratib-chrome-bust.php';
if (is_file($rootScript)) {
    require $rootScript;
    return;
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo "ratib-chrome-bust\n";
echo "MISSING root ratib-chrome-bust.php — upload it to public_html/\n";
