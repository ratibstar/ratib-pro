<?php

declare(strict_types=1);

/**
 * Export offline/config/ops-page-allowlist.php → public JSON for the ERP Service Worker.
 * Source of truth remains the PHP allowlist; SW must not hardcode paths.
 *
 * Run: php offline/scripts/sync-ops-page-allowlist.php
 */

$root = dirname(__DIR__, 2);
$src = $root . '/offline/config/ops-page-allowlist.php';
$dest = $root . '/public/assets/offline/ops-page-allowlist.json';

if (!is_file($src)) {
    fwrite(STDERR, "MISSING {$src}\n");
    exit(1);
}

$cfg = require $src;
if (!is_array($cfg)) {
    fwrite(STDERR, "INVALID allowlist\n");
    exit(1);
}

$paths = array_values(array_filter(array_map(
    static fn ($p): string => trim((string) $p, "/ \t\n\r"),
    $cfg['paths'] ?? []
), static fn (string $p): bool => $p !== ''));

$payload = [
    'version' => 1,
    'generated_at' => gmdate('c'),
    'source' => 'offline/config/ops-page-allowlist.php',
    'paths' => $paths,
];

$dir = dirname($dest);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    fwrite(STDERR, "Cannot create {$dir}\n");
    exit(1);
}

$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false) {
    fwrite(STDERR, "JSON encode failed\n");
    exit(1);
}

file_put_contents($dest, $json . "\n");
echo 'Wrote ' . count($paths) . " paths → {$dest}\n";
