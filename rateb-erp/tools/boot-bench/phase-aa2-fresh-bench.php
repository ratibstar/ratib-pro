<?php
declare(strict_types=1);

/**
 * Fresh-process registration timing for AA.1 vs AA.2 (avoids same-process skew).
 * Usage: php phase-aa2-fresh-bench.php
 */
$root = dirname(__DIR__, 2);
$php = PHP_BINARY;
$runner = __DIR__ . '/_aa2_fresh_worker.php';

$paths = [
    '/admin',
    '/login',
    '/logout',
    '/api/v1',
    '/site',
    '/admin/cms',
    '/admin/ops/pos/register',
    '/totally-unknown-xyz',
];

$results = [];
foreach ($paths as $path) {
    foreach (['all', 'selective'] as $mode) {
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($runner) . ' '
            . escapeshellarg($mode) . ' ' . escapeshellarg($path);
        $json = shell_exec($cmd);
        $decoded = is_string($json) ? json_decode(trim($json), true) : null;
        if (!is_array($decoded)) {
            $results[$path][$mode] = ['error' => 'worker failed', 'raw' => $json];
            continue;
        }
        $results[$path][$mode] = $decoded;
    }
}

$out = [
    'measured_at' => gmdate('c'),
    'paths' => $results,
    'summary' => [],
];
foreach ($results as $path => $modes) {
    $a = $modes['all'] ?? [];
    $s = $modes['selective'] ?? [];
    $out['summary'][$path] = [
        'aa1_routes' => $a['route_count'] ?? null,
        'aa2_routes' => $s['route_count'] ?? null,
        'reduction' => isset($a['route_count'], $s['route_count'])
            ? ($a['route_count'] - $s['route_count']) : null,
        'aa1_reg_ms' => $a['registration_ms'] ?? null,
        'aa2_reg_ms' => $s['registration_ms'] ?? null,
        'aa2_modules' => $s['modules'] ?? null,
        'aa2_mode' => $s['mode'] ?? null,
        'has_match' => $s['has_match'] ?? null,
        'mem_peak_aa2' => $s['memory_peak_bytes'] ?? null,
    ];
}

$dir = __DIR__ . '/reports';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
$file = $dir . '/phase-aa2-fresh-bench.json';
file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo json_encode($out['summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wrote $file\n";
