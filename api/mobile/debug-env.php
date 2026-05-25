<?php
/**
 * TEMPORARY — DELETE after MOBILE_AUTH_SECRET is verified on production.
 *
 * Internal diagnostics for mobile env loading. Never returns secret values.
 *
 * Access: localhost only, OR client IP listed in .env MOBILE_ENV_DIAG_IP (comma-separated).
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/env.inc.php';

/**
 * @return list<string>
 */
function rateb_mobile_debug_allowed_ips(): array
{
    $ips = ['127.0.0.1', '::1'];

    $raw = getenv('MOBILE_ENV_DIAG_IP');
    if ($raw === false || trim((string) $raw) === '') {
        foreach (rateb_mobile_env_candidate_paths() as $path) {
            $fromFile = rateb_mobile_parse_dotenv_key($path, 'MOBILE_ENV_DIAG_IP');
            if ($fromFile !== null && trim($fromFile) !== '') {
                $raw = $fromFile;
                break;
            }
        }
    }

    if ($raw !== false && trim((string) $raw) !== '') {
        foreach (explode(',', (string) $raw) as $part) {
            $part = trim($part);
            if ($part !== '' && !in_array($part, $ips, true)) {
                $ips[] = $part;
            }
        }
    }

    return $ips;
}

function rateb_mobile_debug_client_allowed(): bool
{
    $client = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return $client !== '' && in_array($client, rateb_mobile_debug_allowed_ips(), true);
}

if (!rateb_mobile_debug_client_allowed()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'not_found'], JSON_UNESCAPED_UNICODE);
    exit;
}

rateb_mobile_bootstrap_env();

$host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
$productionMode = $host !== ''
    && !str_contains($host, 'localhost')
    && !str_contains($host, '127.0.0.1')
    && str_contains($host, 'ratib.sa');

$secret = getenv('MOBILE_AUTH_SECRET');
if (($secret === false || trim((string) $secret) === '') && defined('MOBILE_AUTH_SECRET')) {
    $secret = MOBILE_AUTH_SECRET;
}
$loaded = is_string($secret) && trim($secret) !== '';
$secretLength = $loaded ? strlen(trim((string) $secret)) : 0;

echo json_encode([
    'env_exists' => !empty($GLOBALS['rateb_mobile_env_file_found']),
    'mobile_auth_secret_loaded' => $loaded,
    'secret_length' => $secretLength,
    'env_path' => (string) ($GLOBALS['rateb_mobile_env_file_used'] ?? ''),
    'production_mode' => $productionMode,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
