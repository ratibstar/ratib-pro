<?php
/**
 * Download a worker document file only if shared with the partner portal agency (or staff with view).
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
require_once __DIR__ . '/PartnerAgencyWorkerDocSharesController.php';

$shareId = (int) ($_GET['share_id'] ?? 0);
if ($shareId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Bad request';
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratibEnsureGlobalPartnershipsSchema($conn);
    $ctl = new PartnerAgencyWorkerDocSharesController($conn);

    $partnerAgencyId = 0;

    if (function_exists('ratib_partner_portal_session_is_valid') && ratib_partner_portal_session_is_valid()) {
        $partnerAgencyId = ratib_partner_portal_agency_id();
    } else {
        enforceApiPermission('partnerships', 'view');
        $stmt = $conn->prepare(
            'SELECT partner_agency_id FROM partner_agency_worker_document_shares WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$shareId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Not found';
            exit;
        }
        $partnerAgencyId = (int) ($row['partner_agency_id'] ?? 0);
    }

    if ($partnerAgencyId <= 0) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Forbidden';
        exit;
    }

    $resolved = $ctl->resolveShareForDownload($shareId, $partnerAgencyId);
    if (!$resolved) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Not found';
        exit;
    }

    $wid = (int) ($resolved['worker_id'] ?? 0);
    $dt = (string) ($resolved['document_type'] ?? '');
    $fn = (string) ($resolved['filename'] ?? '');
    $baseDir = realpath(__DIR__ . '/../../uploads/workers/' . $wid . '/documents/' . $dt);
    if ($baseDir === false) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'File not available';
        exit;
    }
    $path = $baseDir . DIRECTORY_SEPARATOR . $fn;
    $real = realpath($path);
    if ($real === false || !is_file($real) || strpos($real, $baseDir) !== 0) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'File not found';
        exit;
    }

    $mime = 'application/octet-stream';
    if (function_exists('mime_content_type')) {
        $mime = (string) @mime_content_type($real);
        if ($mime === '') {
            $mime = 'application/octet-stream';
        }
    }

    $downloadName = $fn;
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($real));
    header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
    header('Cache-Control: private, no-store');
    readfile($real);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Error';
    exit;
}
