<?php
declare(strict_types=1);

/**
 * Safe runtime smoke checks for country profiles + tracking endpoints.
 *
 * Default mode is READ-ONLY (GET endpoints only).
 * Write-mode tests are disabled unless --allow-write is provided.
 *
 * Usage:
 *   php tools/runtime-smoke-country-tracking.php --base="http://localhost/ratibprogram/control-panel/api/control"
 *   php tools/runtime-smoke-country-tracking.php --base="https://example.com/control-panel/api/control" --allow-write
 */

function argValue(string $name, array $argv): ?string
{
    foreach ($argv as $a) {
        if (strpos($a, $name . '=') === 0) return substr($a, strlen($name) + 1);
    }
    return null;
}

function hasFlag(string $flag, array $argv): bool
{
    return in_array($flag, $argv, true);
}

function req(string $url, string $method = 'GET', ?array $json = null): array
{
    global $cookieHeader;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if (!empty($cookieHeader)) $headers[] = 'Cookie: ' . $cookieHeader;
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($json !== null) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err !== '') {
            return ['ok' => false, 'status' => 0, 'error' => $err, 'json' => null, 'raw' => null];
        }
        $decoded = json_decode((string) $body, true);
        return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'error' => null, 'json' => $decoded, 'raw' => $body];
    }

    $headers = "Accept: application/json\r\n";
    if (!empty($cookieHeader)) $headers .= "Cookie: {$cookieHeader}\r\n";
    $content = null;
    if ($json !== null) {
        $headers .= "Content-Type: application/json\r\n";
        $content = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $content,
            'timeout' => 15,
            'ignore_errors' => true,
        ]
    ]);
    $body = @file_get_contents($url, false, $context);
    $status = 0;
    if (!empty($http_response_header) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    if ($body === false) {
        return ['ok' => false, 'status' => $status, 'error' => 'HTTP request failed', 'json' => null, 'raw' => null];
    }
    $decoded = json_decode((string) $body, true);
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'error' => null, 'json' => $decoded, 'raw' => $body];
}

$base = argValue('--base', $argv) ?: '';
$cookieHeader = argValue('--cookie', $argv) ?: '';
$allowWrite = hasFlag('--allow-write', $argv);
$fails = [];
$oks = [];

if ($base === '') {
    fwrite(STDERR, "Missing --base URL\n");
    exit(2);
}
$base = rtrim($base, '/');

// Read-only checks
$r1 = req($base . '/country-profiles.php?action=preview');
if (!$r1['ok']) $fails[] = "preview failed HTTP " . $r1['status'];
else $oks[] = "preview ok";

$r2 = req($base . '/country-profiles.php?action=export');
if (!$r2['ok']) $fails[] = "export failed HTTP " . $r2['status'];
else $oks[] = "export ok";

$r3 = req($base . '/worker-tracking.php?action=latest&limit=5&q=test');
if (!$r3['ok']) $fails[] = "tracking latest failed HTTP " . $r3['status'];
else $oks[] = "tracking latest search ok";

$r4 = req($base . '/government.php?action=inspections&limit=5&q=test');
if (!$r4['ok']) $fails[] = "government inspections failed HTTP " . $r4['status'];
else $oks[] = "government inspections search ok";

// Optional write checks (disabled by default)
if ($allowWrite) {
    $invalidPayload = [
        'country_slug' => 'default',
        'labels' => ['government' => 'Gov', 'badKey' => 'x'],
        'requirements' => ['full_name', 'not_real_field']
    ];
    $rw = req($base . '/country-profiles.php', 'POST', $invalidPayload);
    if ($rw['status'] !== 422) {
        $fails[] = "whitelist validation expected 422, got " . $rw['status'];
    } else {
        $oks[] = "whitelist validation returns 422";
    }
}

echo "Runtime smoke (" . ($allowWrite ? 'read+write' : 'read-only') . "): " . count($oks) . " ok, " . count($fails) . " failed\n";
foreach ($oks as $x) echo "[OK] {$x}\n";
foreach ($fails as $x) echo "[FAIL] {$x}\n";
exit(count($fails) > 0 ? 1 : 0);

