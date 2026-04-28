<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/control-center-assets.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/control-center-assets.php`.
 */
declare(strict_types=1);

$asset = (string) ($_GET['file'] ?? '');
$map = [
    'css' => [
        'paths' => [
            __DIR__ . '/assets/css/control-center.css',
        ],
        'type' => 'text/css; charset=UTF-8',
    ],
    'js' => [
        'paths' => [
            __DIR__ . '/assets/js/control-center.js',
        ],
        'type' => 'application/javascript; charset=UTF-8',
    ],
];

if (!isset($map[$asset])) {
    http_response_code(404);
    exit('Asset not found');
}

$assetPath = '';
foreach ($map[$asset]['paths'] as $candidatePath) {
    if (file_exists($candidatePath)) {
        $assetPath = $candidatePath;
        break;
    }
}

if ($assetPath === '') {
    http_response_code(404);
    exit('Asset missing');
}

header('Content-Type: ' . $map[$asset]['type']);
header('Cache-Control: public, max-age=300');
readfile($assetPath);
exit;

