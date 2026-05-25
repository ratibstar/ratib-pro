<?php
/**
 * Mobile portal logout — clears server session; client should discard bearer token.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../core/ratib_api_session.inc.php';
ratib_api_pick_session_name();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../core/Auth.php';

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
        rateb_mobile_json(['success' => false, 'message' => 'POST required'], 405);
    }

    $claims = rateb_mobile_validate_token(rateb_mobile_bearer_token());

    if (($claims['typ'] ?? '') === 'partner') {
        if (function_exists('ratib_partner_portal_clear')) {
            ratib_partner_portal_clear();
        }
    } else {
        Auth::logout();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    rateb_mobile_json(['success' => true, 'message' => 'Signed out']);
} catch (Throwable $e) {
    rateb_mobile_json(['success' => true, 'message' => 'Signed out']);
}
