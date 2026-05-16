<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
$marker = __DIR__ . '/ratib-build.txt';
echo is_file($marker) ? trim((string) file_get_contents($marker)) : 'marker-missing';
