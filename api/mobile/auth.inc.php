<?php
/**
 * Shared mobile JWT auth helpers for data endpoints.
 */
declare(strict_types=1);

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/tenant.inc.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/../../includes/config.php';

/**
 * @return array<string, mixed>
 */
function rateb_mobile_require_auth(?string $requiredRole = null): array
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        rateb_mobile_json(['success' => false, 'message' => 'GET required'], 405);
    }

    $claims = rateb_mobile_validate_token(rateb_mobile_bearer_token());
    if ($claims === null) {
        rateb_mobile_json(['success' => false, 'message' => 'Unauthorized', 'code' => 'unauthorized'], 401);
    }

    if ($requiredRole !== null && ($claims['role'] ?? '') !== $requiredRole) {
        rateb_mobile_json(['success' => false, 'message' => 'Forbidden', 'code' => 'forbidden'], 403);
    }

    return $claims;
}

function rateb_mobile_pdo(): PDO
{
    $pdo = Database::getInstance()->getConnection();
    ratebEnsureGlobalPartnershipsSchema($pdo);

    return $pdo;
}

/**
 * Resolve a workers row for a staff JWT (match by user email).
 *
 * @param array<string, mixed> $claims
 * @return array<string, mixed>|null
 */
function rateb_mobile_resolve_worker(PDO $pdo, array $claims): ?array
{
    if (($claims['typ'] ?? '') !== 'staff') {
        return null;
    }

    $userId = (int) ($claims['sub'] ?? 0);
    if ($userId <= 0) {
        return null;
    }

    $userStmt = $pdo->prepare('SELECT email, username FROM users WHERE user_id = ? LIMIT 1');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        return null;
    }

    $email = trim((string) ($user['email'] ?? ''));
    if ($email !== '') {
        $scopeWhere = ["w.status != 'deleted'"];
        $scopeParams = [];
        rateb_mobile_apply_worker_tenant_scope($pdo, $claims, 'w', $scopeWhere, $scopeParams);
        $scopeSql = implode(' AND ', $scopeWhere);

        $stmt = $pdo->prepare(
            "SELECT w.id, w.worker_name, w.email, w.status, w.passport_number, w.contact_number
             FROM workers w
             WHERE LOWER(TRIM(w.email)) = LOWER(?)
             AND {$scopeSql}
             ORDER BY w.id DESC
             LIMIT 1"
        );
        $stmt->execute(array_merge([$email], $scopeParams));
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($worker) {
            return $worker;
        }
    }

    $username = trim((string) ($user['username'] ?? ''));
    if ($username !== '') {
        $scopeWhere = ["w.status != 'deleted'"];
        $scopeParams = [];
        rateb_mobile_apply_worker_tenant_scope($pdo, $claims, 'w', $scopeWhere, $scopeParams);
        $scopeSql = implode(' AND ', $scopeWhere);

        $stmt = $pdo->prepare(
            "SELECT w.id, w.worker_name, w.email, w.status, w.passport_number, w.contact_number
             FROM workers w
             WHERE w.worker_name LIKE ?
             AND {$scopeSql}
             ORDER BY w.id DESC
             LIMIT 1"
        );
        $stmt->execute(array_merge(['%' . $username . '%'], $scopeParams));
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($worker) {
            return $worker;
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $claims
 * @return array<string, mixed>
 */
function rateb_mobile_staff_profile(PDO $pdo, array $claims): array
{
    $userId = (int) ($claims['sub'] ?? 0);
    $portalRole = (string) ($claims['role'] ?? 'company');

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
        rateb_mobile_json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $countryName = null;
    $countryId = $user['country_id'] ?? null;
    if ($countryId) {
        $countryStmt = $pdo->prepare('SELECT country_name FROM recruitment_countries WHERE id = ? LIMIT 1');
        $countryStmt->execute([(int) $countryId]);
        $countryRow = $countryStmt->fetch(PDO::FETCH_ASSOC);
        $countryName = $countryRow['country_name'] ?? null;
    }

    return [
        'user_id' => (int) $user['user_id'],
        'username' => (string) ($user['username'] ?? ''),
        'email' => (string) ($user['email'] ?? ''),
        'phone' => (string) ($user['phone'] ?? ''),
        'role' => $portalRole,
        'role_name' => (string) ($user['role_name'] ?? ''),
        'account_type' => 'staff',
        'country_id' => $countryId !== null ? (int) $countryId : null,
        'country_name' => $countryName,
        'status' => (string) ($user['status'] ?? 'active'),
    ];
}

function rateb_mobile_relative_time(?string $datetime): string
{
    if ($datetime === null || trim($datetime) === '') {
        return 'Recently';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return (string) $datetime;
    }
    $diff = time() - $ts;
    if ($diff < 3600) {
        return max(1, (int) floor($diff / 60)) . ' min ago';
    }
    if ($diff < 86400) {
        return max(1, (int) floor($diff / 3600)) . ' hours ago';
    }
    if ($diff < 604800) {
        return max(1, (int) floor($diff / 86400)) . ' days ago';
    }

    return date('M j, Y', $ts);
}

function rateb_mobile_humanize_status(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === '') {
        return 'Unknown';
    }

    return ucwords(str_replace(['_', '-'], ' ', $status));
}
