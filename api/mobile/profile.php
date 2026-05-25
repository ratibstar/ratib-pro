<?php
/**
 * Mobile portal profile — requires Authorization: Bearer {token}.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../core/ratib_api_session.inc.php';
ratib_api_pick_session_name();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        rateb_mobile_json(['success' => false, 'message' => 'GET required'], 405);
    }

    $claims = rateb_mobile_validate_token(rateb_mobile_bearer_token());
    if ($claims === null) {
        rateb_mobile_json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $accountType = (string) ($claims['typ'] ?? '');
    $subjectId = (int) ($claims['sub'] ?? 0);
    $portalRole = (string) ($claims['role'] ?? 'company');

    if ($accountType === 'partner' && $subjectId > 0) {
        $pdo = Database::getInstance()->getConnection();
        ratibEnsureGlobalPartnershipsSchema($pdo);
        $stmt = $pdo->prepare(
            'SELECT id, name, email FROM partner_agencies WHERE id = ? AND portal_enabled = 1 LIMIT 1'
        );
        $stmt->execute([$subjectId]);
        $agency = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$agency) {
            rateb_mobile_json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        rateb_mobile_json([
            'success' => true,
            'data' => [
                'user_id' => (int) $agency['id'],
                'username' => (string) ($agency['name'] ?? ''),
                'email' => (string) ($agency['email'] ?? ''),
                'role' => $portalRole,
                'account_type' => 'partner',
                'status' => 'active',
            ],
        ]);
    }

    if ($accountType === 'staff' && $subjectId > 0) {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare(
            'SELECT u.user_id, u.username, u.email, u.phone, u.status, u.country_id, r.role_name
             FROM users u
             LEFT JOIN roles r ON u.role_id = r.role_id
             WHERE u.user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$subjectId]);
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

        rateb_mobile_json([
            'success' => true,
            'data' => [
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
            ],
        ]);
    }

    rateb_mobile_json(['success' => false, 'message' => 'Unauthorized'], 401);
} catch (Throwable $e) {
    rateb_mobile_json(['success' => false, 'message' => 'Profile unavailable'], 500);
}
