<?php
/**
 * Download CV file (staff with view permission, or partner portal for own agency).
 */
require_once __DIR__ . '/../core/ratib_api_session.inc.php';
ratib_api_pick_session_name();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/PartnerAgencyCvsController.php';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo 'Bad request';
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();
ratibEnsureGlobalPartnershipsSchema($conn);

$cvs = new PartnerAgencyCvsController($conn);
try {
    $row = $cvs->findById($id);
} catch (Throwable $e) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$authorized = false;
if (function_exists('ratib_partner_portal_session_is_valid') && ratib_partner_portal_session_is_valid()) {
    $authorized = ratib_partner_portal_agency_id() === (int) ($row['partner_agency_id'] ?? 0);
} else {
    try {
        enforceApiPermission('partnerships', 'view');
        $authorized = true;
    } catch (Throwable $e) {
        $authorized = false;
    }
}

if (!$authorized) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$path = $cvs->absoluteFilePath($row);
if ($path === null) {
    http_response_code(404);
    echo 'File missing';
    exit;
}

$downloadName = (string) ($row['original_filename'] ?? 'document');
$mime = (string) ($row['mime_type'] ?? '');
if ($mime === '' || $mime === 'application/octet-stream') {
    $mime = 'application/octet-stream';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
header('Cache-Control: private, max-age=0');
readfile($path);
exit;
