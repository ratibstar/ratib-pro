<?php
/**
 * Share all worker document files (full CV slots) to one partner for selected workers.
 */
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/PartnerAgencyWorkerDocSharesController.php';

function cvsSendJson(array $payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        cvsSendJson(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    enforceApiPermission('partnerships', 'update');
    $raw = (string) file_get_contents('php://input');
    $json = $raw !== '' ? json_decode($raw, true) : [];
    if (!is_array($json)) {
        $json = [];
    }
    $partnerAgencyId = (int) ($json['partner_agency_id'] ?? 0);
    $workerIds = $json['worker_ids'] ?? [];
    if (!is_array($workerIds)) {
        $workerIds = [];
    }
    $workerIds = array_map('intval', $workerIds);
    $workerIds = array_values(array_filter($workerIds, static fn ($id) => $id > 0));
    if ($partnerAgencyId <= 0 || $workerIds === []) {
        throw new InvalidArgumentException('partner_agency_id and non-empty worker_ids are required');
    }
    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratibEnsureGlobalPartnershipsSchema($conn);
    $ctl = new PartnerAgencyWorkerDocSharesController($conn);
    $result = $ctl->addAllFileSharesForWorkersToPartner($partnerAgencyId, $workerIds);
    cvsSendJson(['success' => true, 'message' => 'Shares updated', 'data' => $result]);
} catch (InvalidArgumentException $e) {
    cvsSendJson(['success' => false, 'message' => $e->getMessage()], 400);
} catch (Throwable $e) {
    cvsSendJson(['success' => false, 'message' => $e->getMessage()], 500);
}
