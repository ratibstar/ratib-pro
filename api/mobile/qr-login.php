<?php
/**
 * Mobile QR login — validate signed workforce QR → JWT session.
 *
 * POST JSON: { "qr_payload": "RATEBMOBQR:..." }
 */
declare(strict_types=1);

require_once __DIR__ . '/cors.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/qr.inc.php';

require_once __DIR__ . '/../core/ratib_api_session.inc.php';
require_once __DIR__ . '/../../includes/config.php';

ratib_api_pick_session_name();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        rateb_mobile_json(['success' => false, 'message' => 'POST required'], 405);
    }

    $input = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $payload = trim((string) ($input['qr_payload'] ?? $input['payload'] ?? $input['barcode'] ?? ''));

    if ($payload === '') {
        rateb_mobile_json(['success' => false, 'message' => 'QR payload is required.', 'code' => 'invalid'], 400);
    }

    $pdo = Database::getInstance()->getConnection();
    ratibEnsureGlobalPartnershipsSchema($pdo);

    // Legacy workforce badges (RATIBLOGIN:…)
    if (str_starts_with($payload, 'RATIBLOGIN:')) {
        $legacy = rateb_mobile_qr_try_legacy_badge($payload);
        if (!empty($legacy['success'])) {
            rateb_mobile_json($legacy);
        }
        rateb_mobile_json([
            'success' => false,
            'message' => (string) ($legacy['message'] ?? 'Invalid badge.'),
            'code' => (string) ($legacy['code'] ?? 'invalid'),
        ], 401);
    }

    $verified = rateb_mobile_qr_verify_payload($payload);
    if (empty($verified['ok']) || empty($verified['data'])) {
        rateb_mobile_json([
            'success' => false,
            'message' => (string) ($verified['message'] ?? 'Invalid QR code.'),
            'code' => (string) ($verified['code'] ?? 'invalid'),
        ], 401);
    }

    $data = $verified['data'];
    $subjectId = (int) ($data['sub'] ?? 0);
    $accountType = (string) ($data['typ'] ?? 'staff');
    $nonce = (string) ($data['nonce'] ?? '');
    $exp = (int) ($data['exp'] ?? 0);

    $nonceResult = rateb_mobile_qr_consume_nonce($pdo, $nonce, $subjectId, $accountType, $exp);
    if (empty($nonceResult['ok'])) {
        rateb_mobile_json([
            'success' => false,
            'message' => (string) ($nonceResult['message'] ?? 'QR already used.'),
            'code' => (string) ($nonceResult['code'] ?? 'nonce_reused'),
        ], 401);
    }

    if ($accountType === 'partner') {
        $result = rateb_mobile_qr_issue_partner_jwt($pdo, $subjectId);
    } else {
        $result = rateb_mobile_qr_issue_staff_jwt($pdo, $subjectId);
    }

    if (empty($result['success'])) {
        rateb_mobile_json([
            'success' => false,
            'message' => (string) ($result['message'] ?? 'Login failed.'),
            'code' => (string) ($result['code'] ?? 'invalid'),
        ], 401);
    }

    rateb_mobile_json($result);
} catch (Throwable $e) {
    rateb_mobile_json(['success' => false, 'message' => 'QR login failed'], 500);
}
