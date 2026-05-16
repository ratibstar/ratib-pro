<?php
declare(strict_types=1);

/** Proxy broken legacy URL → standalone global-ai-run (build global-ai-v7). */
$target = dirname(__DIR__, 3) . '/api/workers/global-ai-run.php';
if (!is_file($target)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Upload api/workers/global-ai-run.php to the server (build global-ai-v7).',
        'build' => 'global-ai-v7',
    ]);
    exit;
}
require $target;
