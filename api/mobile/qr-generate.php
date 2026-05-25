<?php
/**
 * Generate signed mobile workforce QR for a user (staff admin / authenticated portal).
 *
 * POST JSON: { "user_id": 123, "ttl_seconds": 600, "account_type": "staff" }
 * Requires: Authorization: Bearer {valid JWT}
 */
declare(strict_types=1);

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/qr.inc.php';

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/../../includes/config.php';

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        rateb_mobile_json(['success' => false, 'message' => 'POST required'], 405);
    }

    $claims = rateb_mobile_validate_token(rateb_mobile_bearer_token());
    if ($claims === null) {
        rateb_mobile_json(['success' => false, 'message' => 'Unauthorized'], 401);
    }

    $input = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $userId = (int) ($input['user_id'] ?? $input['subject_id'] ?? 0);
    $ttl = (int) ($input['ttl_seconds'] ?? RATEB_MOBQR_DEFAULT_TTL);
    $accountType = strtolower(trim((string) ($input['account_type'] ?? $claims['typ'] ?? 'staff')));

    if ($userId <= 0) {
        rateb_mobile_json(['success' => false, 'message' => 'user_id is required'], 400);
    }

    $callerRole = (string) ($claims['role'] ?? '');
    if ($callerRole === 'worker' && (int) ($claims['sub'] ?? 0) !== $userId) {
        rateb_mobile_json(['success' => false, 'message' => 'Forbidden'], 403);
    }

    $pdo = Database::getInstance()->getConnection();
    ratibEnsureGlobalPartnershipsSchema($pdo);

    if ($accountType === 'partner') {
        $check = $pdo->prepare(
            'SELECT id FROM partner_agencies WHERE id = ? AND portal_enabled = 1 LIMIT 1'
        );
        $check->execute([$userId]);
        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            rateb_mobile_json(['success' => false, 'message' => 'Agency not found'], 404);
        }
    } else {
        $check = $pdo->prepare('SELECT user_id FROM users WHERE user_id = ? LIMIT 1');
        $check->execute([$userId]);
        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            rateb_mobile_json(['success' => false, 'message' => 'User not found'], 404);
        }
        $accountType = 'staff';
    }

    $built = rateb_mobile_qr_build_payload($userId, $accountType, $ttl);
    if (empty($built['ok']) || empty($built['payload'])) {
        rateb_mobile_json([
            'success' => false,
            'message' => (string) ($built['message'] ?? 'Could not generate QR'),
        ], 500);
    }

    rateb_mobile_json([
        'success' => true,
        'data' => [
            'qr_payload' => (string) $built['payload'],
            'expires_at' => (int) ($built['expires_at'] ?? 0),
            'expires_in' => max(0, (int) ($built['expires_at'] ?? 0) - time()),
            'user_id' => $userId,
            'account_type' => $accountType,
        ],
    ]);
} catch (Throwable $e) {
    rateb_mobile_json(['success' => false, 'message' => 'QR generation failed'], 500);
}
