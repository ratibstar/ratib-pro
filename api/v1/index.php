<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'success' => true,
    'version' => 'v1',
    'endpoints' => [
        '/api/v1/workers?id={worker_id}',
        '/api/v1/tracking',
        '/api/v1/workflows?id={workflow_id}',
        '/api/v1/workflows (POST)',
        '/api/v1/alerts?limit=100',
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
