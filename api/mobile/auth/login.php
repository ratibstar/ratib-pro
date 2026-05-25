<?php
/**
 * Mobile portal login — email/username + password → bearer token + portal role.
 *
 * POST JSON: { "email": "...", "password": "...", "country_id": 0, "agency_id": 0 }
 * Response:  { "success": true, "token": "...", "role": "worker|company|agency", ... }
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_once __DIR__ . '/../core/ratib_api_session.inc.php';
ratib_api_pick_session_name();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        rateb_mobile_json(['success' => false, 'message' => 'POST required'], 405);
    }

    $input = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $login = trim((string) ($input['email'] ?? $input['username'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $countryId = (int) ($input['country_id'] ?? 0);
    $agencyId = (int) ($input['agency_id'] ?? 0);

    if ($login === '' || $password === '') {
        rateb_mobile_json(['success' => false, 'message' => 'Email and password are required'], 400);
    }

    // Optional tenant hint for SINGLE_URL_MODE before staff credential check.
    if ($countryId > 0) {
        $_SESSION['country_id'] = $countryId;
    }
    if ($agencyId > 0) {
        $_SESSION['agency_id'] = $agencyId;
    }

    // 1) Partner portal (recruitment agency accounts)
    $pdo = Database::getInstance()->getConnection();
    ratibEnsureGlobalPartnershipsSchema($pdo);

    $isNumericId = ctype_digit($login);
    if ($isNumericId) {
        $partnerStmt = $pdo->prepare(
            'SELECT id, name, email, portal_password_hash FROM partner_agencies
             WHERE portal_enabled = 1 AND id = ? LIMIT 1'
        );
        $partnerStmt->execute([(int) $login]);
    } else {
        $partnerStmt = $pdo->prepare(
            'SELECT id, name, email, portal_password_hash FROM partner_agencies
             WHERE portal_enabled = 1
               AND (
                    (email IS NOT NULL AND TRIM(email) <> \'\' AND LOWER(TRIM(email)) = LOWER(?))
                    OR LOWER(TRIM(name)) = LOWER(?)
               )
             LIMIT 5'
        );
        $partnerStmt->execute([$login, $login]);
    }

    $partnerMatches = $partnerStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($partnerMatches as $candidate) {
        $hash = (string) ($candidate['portal_password_hash'] ?? '');
        if ($hash !== '' && password_verify($password, $hash)) {
            session_regenerate_id(true);
            if (function_exists('ratib_partner_portal_clear')) {
                ratib_partner_portal_clear();
            }
            $_SESSION['partner_portal_logged_in'] = true;
            $_SESSION['partner_portal_agency_id'] = (int) ($candidate['id'] ?? 0);

            $portalRole = 'agency';
            $claims = rateb_mobile_build_token_claims(
                'partner',
                (int) $candidate['id'],
                $portalRole,
                $countryId > 0 ? $countryId : null,
                (int) $candidate['id']
            );
            $token = rateb_mobile_issue_token($claims);

            rateb_mobile_json([
                'success' => true,
                'token' => $token,
                'role' => $portalRole,
                'user_id' => (int) $candidate['id'],
                'username' => (string) ($candidate['name'] ?? ''),
                'email' => (string) ($candidate['email'] ?? ''),
                'display_name' => (string) ($candidate['name'] ?? ''),
            ]);
        }
    }

    // 2) Staff / employer accounts (users table)
    $authResult = Auth::login($login, $password);
    if (!$authResult['success']) {
        rateb_mobile_json([
            'success' => false,
            'message' => (string) ($authResult['error'] ?? 'Invalid username or password'),
            'code' => 'invalid_credentials',
        ], 401);
    }

    $user = $authResult['user'] ?? [];
    $userId = (int) ($user['user_id'] ?? 0);
    $roleName = null;

    if ($userId > 0) {
        $roleStmt = $pdo->prepare(
            'SELECT r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = ? LIMIT 1'
        );
        $roleStmt->execute([$userId]);
        $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
        $roleName = $roleRow['role_name'] ?? null;
    }

    $portalRole = rateb_mobile_map_portal_role('staff', $roleName);
    $claims = rateb_mobile_build_token_claims(
        'staff',
        $userId,
        $portalRole,
        isset($_SESSION['country_id']) ? (int) $_SESSION['country_id'] : null,
        isset($_SESSION['agency_id']) ? (int) $_SESSION['agency_id'] : null
    );
    $token = rateb_mobile_issue_token($claims);

    rateb_mobile_json([
        'success' => true,
        'token' => $token,
        'role' => $portalRole,
        'user_id' => $userId,
        'username' => (string) ($user['username'] ?? $login),
        'email' => (string) ($user['email'] ?? $login),
        'display_name' => (string) ($user['username'] ?? $login),
    ]);
} catch (Throwable $e) {
    rateb_mobile_json(['success' => false, 'message' => 'Login failed'], 500);
}
