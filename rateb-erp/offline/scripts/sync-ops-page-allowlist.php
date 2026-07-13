<?php

declare(strict_types=1);

/**
 * Export offline/config/ops-page-allowlist.php → public JSON for the ERP Service Worker.
 * Canonical routes are resolved ONLY via rateb_app_route() — never hardcoded /admin/ops/.
 *
 * JSON shape (v2):
 * {
 *   "version": 2,
 *   "paths": ["purchase-requests", "hr/attendance", ...],
 *   "routes": {
 *     "purchase-requests": "admin/ops/purchase-requests",
 *     "hr/attendance": "admin/hr/attendance"
 *   }
 * }
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

require_once $root . '/config/app.php';

if (!function_exists('rateb_app_route')) {
    fwrite(STDERR, "rateb_app_route() unavailable\n");
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

$routes = [];
foreach ($paths as $logical) {
    $canonical = trim((string) rateb_app_route($logical), "/ \t\n\r");
    if ($canonical === '') {
        fwrite(STDERR, "INVALID ROUTE (empty) for logical path: {$logical}\n");
        continue;
    }
    $routes[$logical] = $canonical;
    // SaaS cloud (rateb.sa) often serves access-control under admin/ops; CLI host may differ.
    $rootSeg = explode('/', $logical)[0] ?? '';
    $opsConflict = [
        'access-control', 'users', 'roles', 'permissions',
        'audit-logs', 'support-tickets', 'email-templates', 'sms-templates',
    ];
    if (in_array($rootSeg, $opsConflict, true)) {
        $opsForm = 'admin/ops/' . $logical;
        $adminForm = 'admin/' . $logical;
        if ($canonical === $adminForm) {
            $routes[$logical . '@ops'] = $opsForm;
        } elseif ($canonical === $opsForm) {
            $routes[$logical . '@admin'] = $adminForm;
        }
    }
}

$payload = [
    'version' => 2,
    'generated_at' => gmdate('c'),
    'source' => 'offline/config/ops-page-allowlist.php + rateb_app_route()',
    'paths' => $paths,
    'routes' => $routes,
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
echo 'Wrote ' . count($paths) . ' paths / ' . count($routes) . " routes → {$dest}\n";
echo 'Sample hr/attendance → ' . ($routes['hr/attendance'] ?? 'MISSING') . "\n";
echo 'Sample purchase-requests → ' . ($routes['purchase-requests'] ?? 'MISSING') . "\n";
