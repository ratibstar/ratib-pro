<?php
/**
 * Workers with every allowed document file uploaded ("full CV"), plus deployment partner ids.
 */
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/PartnerAgencyWorkerDocSharesController.php';

function cvsReadyJson(array $payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        cvsReadyJson(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    enforceApiPermission('partnerships', 'view');
    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratibEnsureGlobalPartnershipsSchema($conn);
    $ctl = new PartnerAgencyWorkerDocSharesController($conn);
    cvsReadyJson(['success' => true, 'data' => ['workers' => $ctl->listFullReadyWorkers()]]);
} catch (Throwable $e) {
    cvsReadyJson(['success' => false, 'message' => $e->getMessage()], 500);
}
