<?php
declare(strict_types=1);

/**
 * Tracking onboarding for Ratib Pro country sites (/philippines, /pages/Worker.php, etc.).
 * Uses program session (view_workers) — not control_logged_in only.
 */
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/../../control-panel/includes/control-permissions.php';

try {
    enforceApiPermission('workers', 'get');
} catch (Throwable $authError) {
    $hasControlAccess = !empty($_SESSION['control_logged_in'])
        && (
            hasControlPermission(CONTROL_PERM_GOVERNMENT)
            || hasControlPermission('manage_control_government')
            || hasControlPermission('gov_admin')
            || hasControlPermission(CONTROL_PERM_ADMINS)
        );
    if (!$hasControlAccess) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

define('RATIB_TRACKING_PROGRAM_AUTH', true);
require_once dirname(__DIR__, 2) . '/control-panel/api/control/worker-tracking-onboarding.php';
