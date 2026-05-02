<?php
/**
 * List / add / remove worker document shares for a partner agency (staff).
 * GET returns shares + workers from deployments for the picker.
 */
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/PartnerAgencyWorkerDocSharesController.php';

function workerSharesJson(array $payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratibEnsureGlobalPartnershipsSchema($conn);
    $ctl = new PartnerAgencyWorkerDocSharesController($conn);

    $partnerAgencyId = (int) ($_GET['partner_agency_id'] ?? $_POST['partner_agency_id'] ?? 0);

    if ($method === 'GET') {
        enforceApiPermission('partnerships', 'view');
        if ($partnerAgencyId <= 0) {
            throw new InvalidArgumentException('partner_agency_id is required');
        }
        $body = [
            'success' => true,
            'data' => [
                'shares' => $ctl->listSharesWithDetails($partnerAgencyId),
                'deployment_workers' => $ctl->listDeploymentWorkers($partnerAgencyId),
                'document_types' => PartnerAgencyWorkerDocSharesController::allowedDocumentTypes(),
                'document_labels' => (static function (): array {
                    $out = [];
                    foreach (PartnerAgencyWorkerDocSharesController::allowedDocumentTypes() as $t) {
                        $out[$t] = PartnerAgencyWorkerDocSharesController::documentTypeLabel($t);
                    }

                    return $out;
                })(),
            ],
        ];
        workerSharesJson($body);
    }

    if ($method === 'POST') {
        enforceApiPermission('partnerships', 'update');
        $raw = (string) file_get_contents('php://input');
        $json = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($json)) {
            $json = [];
        }
        $partnerAgencyId = (int) ($json['partner_agency_id'] ?? $partnerAgencyId);
        $workerId = (int) ($json['worker_id'] ?? 0);
        $documentType = (string) ($json['document_type'] ?? '');
        if ($partnerAgencyId <= 0 || $workerId <= 0 || $documentType === '') {
            throw new InvalidArgumentException('partner_agency_id, worker_id, and document_type are required');
        }
        $created = $ctl->addShare($partnerAgencyId, $workerId, $documentType);
        workerSharesJson(['success' => true, 'message' => 'Share added', 'data' => $created], 201);
    }

    if ($method === 'DELETE') {
        enforceApiPermission('partnerships', 'delete');
        $shareId = (int) ($_GET['id'] ?? 0);
        $partnerAgencyId = (int) ($_GET['partner_agency_id'] ?? 0);
        if ($shareId <= 0 || $partnerAgencyId <= 0) {
            throw new InvalidArgumentException('id and partner_agency_id are required');
        }
        $ctl->deleteShare($shareId, $partnerAgencyId);
        workerSharesJson(['success' => true, 'message' => 'Share removed']);
    }

    workerSharesJson(['success' => false, 'message' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    workerSharesJson(['success' => false, 'message' => $e->getMessage()], 500);
}
