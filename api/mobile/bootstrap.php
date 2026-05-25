<?php
/**
 * Mobile portal API bootstrap — CORS, JSON helpers, signed bearer tokens.
 */
declare(strict_types=1);

ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!defined('SYSTEM_ENDPOINT')) {
    define('SYSTEM_ENDPOINT', true);
}

function rateb_mobile_json(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function rateb_mobile_token_secret(): string
{
    $secret = getenv('MOBILE_AUTH_SECRET');
    if ($secret !== false && $secret !== '') {
        return (string) $secret;
    }
    if (defined('MOBILE_AUTH_SECRET') && MOBILE_AUTH_SECRET !== '') {
        return (string) MOBILE_AUTH_SECRET;
    }
    return 'rateb-mobile-change-me-in-production';
}

function rateb_mobile_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function rateb_mobile_b64url_decode(string $data): string
{
    $pad = strlen($data) % 4;
    if ($pad > 0) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return (string) base64_decode(strtr($data, '-_', '+/'), true);
}

function rateb_mobile_issue_token(array $claims): string
{
    $header = rateb_mobile_b64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
    $payload = rateb_mobile_b64url_encode(json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    $sig = rateb_mobile_b64url_encode(
        hash_hmac('sha256', $header . '.' . $payload, rateb_mobile_token_secret(), true)
    );
    return $header . '.' . $payload . '.' . $sig;
}

function rateb_mobile_validate_token(?string $token): ?array
{
    if ($token === null || $token === '') {
        return null;
    }
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }
    [$header, $payload, $sig] = $parts;
    $expected = rateb_mobile_b64url_encode(
        hash_hmac('sha256', $header . '.' . $payload, rateb_mobile_token_secret(), true)
    );
    if (!hash_equals($expected, $sig)) {
        return null;
    }
    $json = rateb_mobile_b64url_decode($payload);
    $claims = json_decode($json, true);
    if (!is_array($claims)) {
        return null;
    }
    $exp = (int) ($claims['exp'] ?? 0);
    if ($exp > 0 && $exp < time()) {
        return null;
    }
    return $claims;
}

function rateb_mobile_bearer_token(): ?string
{
    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        return trim($m[1]);
    }
    return null;
}

/**
 * Map backend account metadata to mobile portal role.
 *
 * @return 'worker'|'company'|'agency'
 */
function rateb_mobile_map_portal_role(string $accountType, ?string $roleName = null): string
{
    if ($accountType === 'partner') {
        return 'agency';
    }
    $rn = strtolower(trim((string) $roleName));
    if ($rn !== '') {
        if (str_contains($rn, 'agency') || str_contains($rn, 'partner') || str_contains($rn, 'recruit')) {
            return 'agency';
        }
        if (str_contains($rn, 'worker') || str_contains($rn, 'employee') || str_contains($rn, 'labour') || str_contains($rn, 'labor')) {
            return 'worker';
        }
        if (str_contains($rn, 'company') || str_contains($rn, 'employer') || str_contains($rn, 'client')) {
            return 'company';
        }
    }
    return 'company';
}

function rateb_mobile_build_token_claims(
    string $accountType,
    int $subjectId,
    string $portalRole,
    ?int $countryId = null,
    ?int $agencyId = null
): array {
    return [
        'sub' => $subjectId,
        'typ' => $accountType,
        'role' => $portalRole,
        'country_id' => $countryId,
        'agency_id' => $agencyId,
        'iat' => time(),
        'exp' => time() + (86400 * 7),
    ];
}
