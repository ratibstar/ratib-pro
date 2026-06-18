<?php
/**
 * Mobile workforce QR identity — signed payloads, nonce replay protection.
 */
declare(strict_types=1);

const RATEB_MOBQR_PREFIX = 'RATEBMOBQR:';
const RATEB_MOBQR_VERSION = 1;
const RATEB_MOBQR_DEFAULT_TTL = 600;
const RATEB_MOBQR_MAX_TTL = 86400;

function rateb_mobile_qr_b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function rateb_mobile_qr_b64url_decode(string $data): string
{
    $pad = strlen($data) % 4;
    if ($pad > 0) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return (string) base64_decode(strtr($data, '-_', '+/'), true);
}

function rateb_mobile_qr_sign(string $bodyB64): string
{
    $secret = rateb_mobile_token_secret();
    return rateb_mobile_qr_b64url_encode(
        hash_hmac('sha256', RATEB_MOBQR_PREFIX . $bodyB64, $secret, true)
    );
}

function rateb_mobile_qr_verify_sig(string $bodyB64, string $sigB64): bool
{
    $expected = rateb_mobile_qr_sign($bodyB64);
    return hash_equals($expected, $sigB64);
}

function rateb_mobile_qr_ensure_nonce_table(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS mobile_qr_used_nonces (
            nonce_hash CHAR(64) NOT NULL PRIMARY KEY,
            subject_id INT NOT NULL,
            account_type VARCHAR(16) NOT NULL DEFAULT \'staff\',
            consumed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            INDEX idx_mobile_qr_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function rateb_mobile_qr_purge_expired_nonces(PDO $pdo): void
{
    try {
        $pdo->exec('DELETE FROM mobile_qr_used_nonces WHERE expires_at < NOW()');
    } catch (Throwable $e) {
        // Best-effort cleanup.
    }
}

function rateb_mobile_qr_nonce_hash(string $nonce): string
{
    return hash('sha256', $nonce);
}

/**
 * @return array{ok:bool,message?:string,code?:string}
 */
function rateb_mobile_qr_consume_nonce(PDO $pdo, string $nonce, int $subjectId, string $accountType, int $exp): array
{
    rateb_mobile_qr_ensure_nonce_table($pdo);
    rateb_mobile_qr_purge_expired_nonces($pdo);

    $hash = rateb_mobile_qr_nonce_hash($nonce);
    $check = $pdo->prepare('SELECT nonce_hash FROM mobile_qr_used_nonces WHERE nonce_hash = ? LIMIT 1');
    $check->execute([$hash]);
    if ($check->fetch(PDO::FETCH_ASSOC)) {
        return ['ok' => false, 'message' => 'QR code already used.', 'code' => 'nonce_reused'];
    }

    $expDt = date('Y-m-d H:i:s', $exp);
    $insert = $pdo->prepare(
        'INSERT INTO mobile_qr_used_nonces (nonce_hash, subject_id, account_type, expires_at)
         VALUES (?, ?, ?, ?)'
    );
    try {
        $insert->execute([$hash, $subjectId, $accountType, $expDt]);
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'QR code already used.', 'code' => 'nonce_reused'];
    }

    return ['ok' => true];
}

/**
 * @return array{ok:bool,payload?:string,expires_at?:int,message?:string}
 */
function rateb_mobile_qr_build_payload(
    int $subjectId,
    string $accountType = 'staff',
    int $ttlSeconds = RATEB_MOBQR_DEFAULT_TTL
): array {
    if ($subjectId <= 0) {
        return ['ok' => false, 'message' => 'Invalid subject.'];
    }

    $ttlSeconds = max(60, min(RATEB_MOBQR_MAX_TTL, $ttlSeconds));
    $exp = time() + $ttlSeconds;
    $nonce = bin2hex(random_bytes(16));

    $inner = [
        'v' => RATEB_MOBQR_VERSION,
        'sub' => $subjectId,
        'typ' => $accountType,
        'nonce' => $nonce,
        'exp' => $exp,
    ];

    $bodyB64 = rateb_mobile_qr_b64url_encode(
        json_encode($inner, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
    $sigB64 = rateb_mobile_qr_sign($bodyB64);
    $payload = RATEB_MOBQR_PREFIX . $bodyB64 . '.' . $sigB64;

    return [
        'ok' => true,
        'payload' => $payload,
        'expires_at' => $exp,
    ];
}

/**
 * @return array{ok:bool,data?:array<string,mixed>,message?:string,code?:string}
 */
function rateb_mobile_qr_verify_payload(string $rawPayload): array
{
    $rawPayload = trim($rawPayload);
    if ($rawPayload === '') {
        return ['ok' => false, 'message' => 'Empty QR payload.', 'code' => 'invalid'];
    }

    if (!str_starts_with($rawPayload, RATEB_MOBQR_PREFIX)) {
        return ['ok' => false, 'message' => 'Unrecognized QR format.', 'code' => 'invalid_format'];
    }

    $rest = substr($rawPayload, strlen(RATEB_MOBQR_PREFIX));
    $dot = strrpos($rest, '.');
    if ($dot === false) {
        return ['ok' => false, 'message' => 'Malformed QR payload.', 'code' => 'invalid'];
    }

    $bodyB64 = substr($rest, 0, $dot);
    $sigB64 = substr($rest, $dot + 1);
    if ($bodyB64 === '' || $sigB64 === '') {
        return ['ok' => false, 'message' => 'Malformed QR payload.', 'code' => 'invalid'];
    }

    if (!rateb_mobile_qr_verify_sig($bodyB64, $sigB64)) {
        return ['ok' => false, 'message' => 'Invalid QR signature.', 'code' => 'invalid_signature'];
    }

    $json = rateb_mobile_qr_b64url_decode($bodyB64);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return ['ok' => false, 'message' => 'Invalid QR data.', 'code' => 'invalid'];
    }

    $version = (int) ($data['v'] ?? 0);
    if ($version !== RATEB_MOBQR_VERSION) {
        return ['ok' => false, 'message' => 'Unsupported QR version.', 'code' => 'invalid_version'];
    }

    $exp = (int) ($data['exp'] ?? 0);
    if ($exp <= 0 || $exp < time()) {
        return ['ok' => false, 'message' => 'QR code has expired.', 'code' => 'expired'];
    }

    $subjectId = (int) ($data['sub'] ?? 0);
    $accountType = (string) ($data['typ'] ?? 'staff');
    $nonce = (string) ($data['nonce'] ?? '');
    if ($subjectId <= 0 || $nonce === '' || strlen($nonce) < 16) {
        return ['ok' => false, 'message' => 'Invalid QR credentials.', 'code' => 'invalid'];
    }

    return [
        'ok' => true,
        'data' => [
            'sub' => $subjectId,
            'typ' => $accountType,
            'nonce' => $nonce,
            'exp' => $exp,
        ],
    ];
}

/**
 * Issue mobile JWT after QR validation for staff user.
 *
 * @return array{success:bool,token?:string,role?:string,user_id?:int,username?:string,email?:string,user?:array<string,mixed>,message?:string,code?:string}
 */
function rateb_mobile_qr_issue_staff_jwt(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT u.user_id, u.username, u.email, u.phone, u.status, u.country_id, r.role_name
         FROM users u
         LEFT JOIN roles r ON u.role_id = r.role_id
         WHERE u.user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found.', 'code' => 'not_found'];
    }

    if (strtolower((string) ($user['status'] ?? 'active')) === 'inactive') {
        return ['success' => false, 'message' => 'Account is inactive.', 'code' => 'inactive'];
    }

    $roleName = (string) ($user['role_name'] ?? '');
    $portalRole = rateb_mobile_resolve_staff_portal_role($pdo, $userId, $roleName);
    $countryId = isset($user['country_id']) ? (int) $user['country_id'] : null;

    $claims = rateb_mobile_build_token_claims(
        'staff',
        $userId,
        $portalRole,
        $countryId > 0 ? $countryId : null,
        null
    );
    $token = rateb_mobile_issue_token($claims);

    return [
        'success' => true,
        'token' => $token,
        'role' => $portalRole,
        'user_id' => $userId,
        'username' => (string) ($user['username'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'display_name' => (string) ($user['username'] ?? ''),
        'user' => [
            'user_id' => $userId,
            'username' => (string) ($user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'phone' => (string) ($user['phone'] ?? ''),
            'role' => $portalRole,
            'status' => (string) ($user['status'] ?? 'active'),
        ],
    ];
}

/**
 * @return array{success:bool,token?:string,role?:string,user_id?:int,username?:string,email?:string,user?:array<string,mixed>,message?:string,code?:string}
 */
function rateb_mobile_qr_issue_partner_jwt(PDO $pdo, int $agencyId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, email FROM partner_agencies WHERE id = ? AND portal_enabled = 1 LIMIT 1'
    );
    $stmt->execute([$agencyId]);
    $agency = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$agency) {
        return ['success' => false, 'message' => 'Agency not found.', 'code' => 'not_found'];
    }

    $portalRole = 'agency';
    $claims = rateb_mobile_build_token_claims(
        'partner',
        $agencyId,
        $portalRole,
        null,
        $agencyId
    );
    $token = rateb_mobile_issue_token($claims);

    return [
        'success' => true,
        'token' => $token,
        'role' => $portalRole,
        'user_id' => $agencyId,
        'username' => (string) ($agency['name'] ?? ''),
        'email' => (string) ($agency['email'] ?? ''),
        'display_name' => (string) ($agency['name'] ?? ''),
        'user' => [
            'user_id' => $agencyId,
            'username' => (string) ($agency['name'] ?? ''),
            'email' => (string) ($agency['email'] ?? ''),
            'role' => $portalRole,
            'account_type' => 'partner',
            'status' => 'active',
        ],
    ];
}

/**
 * Bridge legacy RATEBLOGIN badges to mobile JWT (no PIN flow on mobile).
 *
 * @return array{success:bool,token?:string,role?:string,user_id?:int,username?:string,email?:string,user?:array<string,mixed>,message?:string,code?:string}
 */
function rateb_mobile_qr_try_legacy_badge(string $payload): array
{
    if (!str_starts_with($payload, 'RATEBLOGIN:')) {
        return ['success' => false, 'message' => 'Not a legacy badge.', 'code' => 'skip'];
    }

    require_once __DIR__ . '/../../includes/config.php';
    require_once __DIR__ . '/../../includes/rateb-qr-login.php';
    require_once __DIR__ . '/../../includes/rateb-qr-workforce-identity.php';

    $auth = rateb_qr_login_authenticate_payload($payload, ['skip_pin' => true, 'trust_device' => false], null);
    if (!empty($auth['needs_pin'])) {
        return [
            'success' => false,
            'message' => 'This badge requires a PIN. Use password login on mobile.',
            'code' => 'needs_pin',
        ];
    }
    if (empty($auth['ok']) || empty($auth['session']['user_id'])) {
        return [
            'success' => false,
            'message' => (string) ($auth['message'] ?? 'Badge not recognized.'),
            'code' => (string) ($auth['code'] ?? 'invalid'),
        ];
    }

    $userId = (int) $auth['session']['user_id'];
    require_once __DIR__ . '/../core/Database.php';
    $pdo = Database::getInstance()->getConnection();

    return rateb_mobile_qr_issue_staff_jwt($pdo, $userId);
}
