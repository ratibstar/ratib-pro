<?php
declare(strict_types=1);

/**
 * Phase AA.1 — identity gate: legacy require list vs RouteModuleLoader route tables must match.
 * Spawns two processes so each builds a clean route table with the same normalizer.
 */
$root = dirname(__DIR__, 2);
$php = PHP_BINARY;
$script = $root . '/tools/boot-bench/phase-aa1-route-signature.php';

$run = static function (string $mode) use ($php, $script): array {
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($mode);
    exec($cmd . ' 2>&1', $out, $code);
    if ($code !== 0) {
        throw new RuntimeException("signature $mode failed ($code): " . implode("\n", $out));
    }
    $hashFile = dirname($script) . '/reports/phase-aa1-' . $mode . '-routes.sha256';
    $jsonFile = dirname($script) . '/reports/phase-aa1-' . $mode . '-latest.json';
    return [
        'sha256' => trim((string) file_get_contents($hashFile)),
        'meta' => json_decode((string) file_get_contents($jsonFile), true),
        'output' => implode("\n", $out),
    ];
};

$legacy = $run('legacy');
$loader = $run('loader');

$ok = $legacy['sha256'] === $loader['sha256']
    && ($legacy['meta']['route_count'] ?? null) === ($loader['meta']['route_count'] ?? null)
    && ($legacy['meta']['loaded_files'] ?? null) === ($loader['meta']['loaded_files'] ?? null)
    && ($legacy['meta']['admin_matches'] ?? null) === ($loader['meta']['admin_matches'] ?? null);

if (!$ok) {
    fwrite(STDERR, "FAIL Phase AA.1 identity\n");
    fwrite(STDERR, 'legacy_sha=' . $legacy['sha256'] . "\n");
    fwrite(STDERR, 'loader_sha=' . $loader['sha256'] . "\n");
    fwrite(STDERR, 'legacy_count=' . ($legacy['meta']['route_count'] ?? '?') . "\n");
    fwrite(STDERR, 'loader_count=' . ($loader['meta']['route_count'] ?? '?') . "\n");
    exit(1);
}

echo "PASS Phase AA.1 RouteModuleLoader identity\n";
echo 'route_count=' . $loader['meta']['route_count'] . "\n";
echo 'sha256=' . $loader['sha256'] . "\n";
echo 'modules=' . implode(',', $loader['meta']['loaded_modules'] ?? []) . "\n";
echo 'files=' . implode(',', $loader['meta']['loaded_files'] ?? []) . "\n";
exit(0);
