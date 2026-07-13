<?php
declare(strict_types=1);

/**
 * Phase D.4 — Zero-touch status writer (infrastructure only).
 * Probes cloud reachability + local SQLite + sync pending; writes status.json.
 * Does not change HybridRuntime / HybridSyncEngine.
 *
 * php bin/hybrid-zero-touch-status.php
 * php bin/hybrid-zero-touch-status.php --loop --interval=3
 */

$root = dirname(__DIR__);
define('RATEB_ENV_NO_SESSION', true);
define('RATEB_ROOT', $root);
require_once $root . '/app/Core/Bootstrap.php';
\Rateb\App\Core\Bootstrap::initMinimal($root);

use Rateb\App\Core\BranchDiagnostics;
use Rateb\App\Core\Database;
use Rateb\App\Core\HybridRuntime;
use Rateb\App\Core\HybridSyncConfig;
use Rateb\App\Core\HybridSyncEngine;

$serve = $root . '/storage/branch/serve.env';
if (is_readable($serve)) {
    foreach (file($serve, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        putenv(trim($k) . '=' . trim($v));
        $_ENV[trim($k)] = trim($v);
    }
    HybridRuntime::reset();
}

$loop = in_array('--loop', $argv, true);
$interval = 3;
foreach ($argv as $a) {
    if (str_starts_with($a, '--interval=')) {
        $interval = max(2, min(10, (int) substr($a, 11)));
    }
}

$appEnv = $root . '/storage/branch/appliance.env';
$cloudUrl = 'https://rateb.sa';
$localUrl = 'http://127.0.0.1:8088/';
if (is_readable($appEnv)) {
    foreach (file($appEnv, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $k = trim($k);
        $v = trim($v);
        if ($k === 'RATEB_BRANCH_HTTP_URL') {
            $localUrl = $v;
        }
        if ($k === 'RATEB_CLOUD_URL' && $v !== '') {
            $cloudUrl = $v;
        }
    }
}

function d4_probe_https(string $url, int $timeoutSec = 3): array
{
    $ok = false;
    $detail = 'curl_missing';
    $latencyMs = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeoutSec,
            CURLOPT_TIMEOUT => $timeoutSec,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_USERAGENT => 'RATIB-ZeroTouch/D4',
        ]);
        $t0 = microtime(true);
        curl_exec($ch);
        $latencyMs = (int) round((microtime(true) - $t0) * 1000);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $ok = $code >= 200 && $code < 500;
        $detail = $ok ? "http={$code} latency_ms={$latencyMs}" : "http={$code} err={$err}";
    } else {
        $t0 = microtime(true);
        $ctx = stream_context_create(['http' => ['timeout' => $timeoutSec], 'ssl' => ['verify_peer' => true]]);
        $headers = @get_headers($url, false, $ctx);
        $latencyMs = (int) round((microtime(true) - $t0) * 1000);
        $ok = is_array($headers) && isset($headers[0]) && preg_match('/HTTP\/\d/i', $headers[0]);
        $detail = $ok ? 'headers_ok latency_ms=' . $latencyMs : 'unreachable';
    }

    return ['ok' => $ok, 'detail' => $detail, 'latency_ms' => $latencyMs];
}

function d4_dns(string $host): bool
{
    if ($host === '') {
        return false;
    }
    $ips = @gethostbynamel($host);

    return is_array($ips) && $ips !== [];
}

function d4_write_status(string $root, array $payload): void
{
    $dir = $root . '/storage/branch';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $path = $dir . '/status.json';
    $tmp = $path . '.tmp';
    file_put_contents($tmp, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    @rename($tmp, $path);
}

function d4_snapshot(string $root, string $localUrl, string $cloudUrl): array
{
    $host = parse_url($cloudUrl, PHP_URL_HOST) ?: 'rateb.sa';
    $dnsOk = d4_dns($host);
    $https = d4_probe_https($cloudUrl, 3);
    $api = d4_probe_https(rtrim($cloudUrl, '/') . '/api/health', 3);
    if (!$api['ok']) {
        $api = d4_probe_https(rtrim($cloudUrl, '/') . '/', 3);
    }

    $sqliteOk = HybridRuntime::shouldUseSqlite() && is_file(HybridRuntime::sqlitePath());
    $pending = 0;
    $lastSync = null;
    $syncOnline = false;
    if ($sqliteOk) {
        try {
            $pdo = Database::connection();
            if (HybridSyncConfig::enabled()) {
                $st = (new HybridSyncEngine())->status($pdo);
                $pending = (int) ($st['outbox']['pending'] ?? 0);
                $syncOnline = !empty($st['online']);
                $lastSync = $st['last_success_at'] ?? ($st['last_run_at'] ?? null);
            }
        } catch (\Throwable $e) {
            $sqliteOk = is_file(HybridRuntime::sqlitePath());
        }
    }

    $cloudReachable = $dnsOk && ($https['ok'] || $api['ok'] || $syncOnline);
    // Branch appliance always serves local SQLite UX; online = cloud reachable for sync/portal.
    $state = 'offline';
    $label = 'OFFLINE';
    $emoji = "\u{1F534}"; // red
    if (!$sqliteOk && !$cloudReachable) {
        $state = 'starting';
        $label = 'STARTING';
        $emoji = "\u{1F535}"; // blue
    } elseif ($cloudReachable && $pending > 0) {
        $state = 'syncing';
        $label = 'SYNCING';
        $emoji = "\u{1F7E1}"; // yellow
    } elseif ($cloudReachable) {
        $state = 'online';
        $label = 'ONLINE';
        $emoji = "\u{1F7E2}"; // green
    }

    // Customer-facing URL: always local launcher URL (seamless offline). Cloud portal separate.
    $openUrl = $localUrl;

    return [
        'phase' => 'D.4',
        'updated_at' => gmdate('c'),
        'state' => $state,
        'label' => $label,
        'emoji' => $emoji,
        'display' => trim($emoji . ' ' . $label),
        'local_url' => $localUrl,
        'cloud_url' => $cloudUrl,
        'open_url' => $openUrl,
        'cloud_connected' => $cloudReachable,
        'sqlite_connected' => $sqliteOk,
        'dns_ok' => $dnsOk,
        'https_ok' => $https['ok'],
        'api_ok' => $api['ok'],
        'latency_ms' => $https['latency_ms'] ?? $api['latency_ms'],
        'pending_records' => $pending,
        'last_sync' => $lastSync,
        'runtime' => 'branch_sqlite',
        'sync_engine' => 'HybridSyncEngine',
        'probes' => [
            'dns' => $dnsOk,
            'https' => $https,
            'api' => $api,
        ],
    ];
}

do {
    try {
        $payload = d4_snapshot($root, $localUrl, $cloudUrl);
        // Enrich with diagnostics health string when cheap
        try {
            $diag = (new BranchDiagnostics())->run();
            $payload['health'] = $diag['health'] ?? 'unknown';
            $payload['diagnostics_ok'] = !empty($diag['ok']);
        } catch (\Throwable $e) {
            $payload['health'] = 'n/a';
        }
        d4_write_status($root, $payload);
        if (!$loop) {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            exit(0);
        }
    } catch (\Throwable $e) {
        d4_write_status($root, [
            'phase' => 'D.4',
            'updated_at' => gmdate('c'),
            'state' => 'maintenance',
            'label' => 'MAINTENANCE',
            'emoji' => "\u{26AA}",
            'display' => "\u{26AA} MAINTENANCE",
            'error' => $e->getMessage(),
            'open_url' => $localUrl,
        ]);
        if (!$loop) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(1);
        }
    }
    sleep($interval);
} while ($loop);
