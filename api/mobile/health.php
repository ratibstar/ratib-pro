<?php
/**
 * Mobile API health check — no auth, no DB. Use to verify deploy + CORS.
 */
declare(strict_types=1);

require_once __DIR__ . '/cors.php';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'message' => 'RATEB mobile API reachable',
    'ts' => time(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
