<?php
declare(strict_types=1);

/**
 * Phase AA.3 fresh-process before/after bench.
 * Usage: php phase-aa3-fresh-bench.php
 */
$root = dirname(__DIR__, 2);
$php = PHP_BINARY;
$worker = __DIR__ . '/_aa3_fresh_worker.php';

$paths = [
    '/admin',
    '/login',
    '/logout',
    '/admin/executive-dashboard',
    '/admin/users',
    '/admin/ops/inventory',
    '/api/v1',
    '/site',
    '/admin/cms',
    '/admin/ops/pos/register',
    '/totally-unknown-xyz',
];

$results = [];
foreach ($paths as $path) {
    foreach (['all', 'selective'] as $mode) {
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($worker) . ' '
            . escapeshellarg($mode) . ' ' . escapeshellarg($path);
        $json = shell_exec($cmd);
        $decoded = is_string($json) ? json_decode(trim($json), true) : null;
        $results[$path][$mode] = is_array($decoded) ? $decoded : ['error' => 'worker failed', 'raw' => $json];
    }
}

$out = [
    'phase' => 'AA.3',
    'measured_at' => gmdate('c'),
    'acceptance' => [
        'admin_routes_lt_150' => (($results['/admin']['selective']['route_count'] ?? 999) < 150),
        'admin_match' => ($results['/admin']['selective']['has_match'] ?? false),
        'admin_handler' => $results['/admin']['selective']['matched_handler'] ?? null,
    ],
    'summary' => [],
    'paths' => $results,
];

foreach ($results as $path => $modes) {
    $a = $modes['all'] ?? [];
    $s = $modes['selective'] ?? [];
    $out['summary'][$path] = [
        'aa1_style_all_routes' => $a['route_count'] ?? null,
        'aa3_selective_routes' => $s['route_count'] ?? null,
        'reduction' => isset($a['route_count'], $s['route_count'])
            ? ($a['route_count'] - $s['route_count']) : null,
        'all_reg_ms' => $a['registration_ms'] ?? null,
        'selective_reg_ms' => $s['registration_ms'] ?? null,
        'modules' => $s['modules'] ?? null,
        'mode' => $s['mode'] ?? null,
        'has_match' => $s['has_match'] ?? null,
        'matched_handler' => $s['matched_handler'] ?? null,
        'memory_peak_selective' => $s['memory_peak_bytes'] ?? null,
    ];
}

$dir = __DIR__ . '/reports';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$file = $dir . '/phase-aa3-fresh-bench.json';
file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo json_encode([
    'acceptance' => $out['acceptance'],
    'summary' => $out['summary'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wrote $file\n";
