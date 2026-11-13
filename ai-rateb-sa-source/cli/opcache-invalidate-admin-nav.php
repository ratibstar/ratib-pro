<?php
declare(strict_types=1);

$root = dirname(__DIR__);
foreach (['admin/auth.php', 'admin/bootstrap.php'] as $rel) {
    $path = $root . '/' . $rel;
    if (is_file($path) && function_exists('opcache_invalidate')) {
        opcache_invalidate($path, true);
    }
}
header('Content-Type: text/plain; charset=utf-8');
echo "ok\n";
