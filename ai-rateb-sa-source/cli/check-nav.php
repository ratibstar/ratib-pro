<?php
declare(strict_types=1);

$path = dirname(__DIR__) . '/app/nav.php';
echo 'path=' . $path . PHP_EOL;
echo 'bytes=' . filesize($path) . PHP_EOL;
echo 'mtime=' . date('c', filemtime($path)) . PHP_EOL;
echo 'has_footer_text=' . (str_contains((string) file_get_contents($path), 'function rateb_site_footer') ? 'yes' : 'no') . PHP_EOL;
require $path;
echo 'function_exists=' . (function_exists('rateb_site_footer') ? 'yes' : 'no') . PHP_EOL;
if (function_exists('opcache_get_status')) {
    $st = opcache_get_status(false);
    echo 'opcache_enabled=' . (!empty($st['opcache_enabled']) ? 'yes' : 'no') . PHP_EOL;
}
if (function_exists('opcache_invalidate')) {
    opcache_invalidate($path, true);
    echo "invalidated\n";
}
