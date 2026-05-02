<?php
/**
 * EN: Handles API endpoint/business logic in `api/partnerships/partner-agencies.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/partnerships/partner-agencies.php`.
 */
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/PartnerAgencyController.php';

function partnershipsJson($payload, int $status = 200): void
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
    enforceApiPermission('partnerships', $method === 'GET' ? 'view' : ($method === 'POST' ? 'create' : ($method === 'DELETE' ? 'delete' : 'update')));

    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratibEnsureGlobalPartnershipsSchema($conn);
    $controller = new PartnerAgencyController($conn);

    if ($method === 'GET') {
        // Most specific first: workers list must not be shadowed by loose `isset($_GET['stats'])`
        // (which is true for ?stats= or ?stats with no value on some stacks).
        if (isset($_GET['workers']) && (string) ($_GET['workers'] ?? '') === '1') {
            $partnerId = (int) ($_GET['partner_agency_id'] ?? 0);
            if ($partnerId <= 0) {
                throw new InvalidArgumentException('partner_agency_id is required');
            }
            partnershipsJson(['success' => true, 'data' => $controller->workersByAgency($partnerId)]);
        }
        if (isset($_GET['stats']) && (string) ($_GET['stats'] ?? '') === '1') {
            partnershipsJson(['success' => true, 'data' => $controller->stats()]);
        }
        $detailId = (int) ($_GET['id'] ?? 0);
        if ($detailId > 0) {
            try {
                partnershipsJson(['success' => true, 'data' => $controller->show($detailId)]);
            } catch (RuntimeException $e) {
                if (stripos($e->getMessage(), 'not found') !== false) {
                    partnershipsJson(['success' => false, 'message' => $e->getMessage()], 404);
                }
                throw $e;
            }
        }
        partnershipsJson(['success' => true, 'data' => $controller->index()]);
    }

    if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $created = $controller->create($payload);
        partnershipsJson(['success' => true, 'message' => 'Agency created successfully', 'data' => $created], 201);
    }

    if ($method === 'PUT') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            throw new InvalidArgumentException('Agency id is required');
        }
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $updated = $controller->update($id, $payload);
        partnershipsJson(['success' => true, 'message' => 'Agency updated successfully', 'data' => $updated]);
    }

    if ($method === 'PATCH') {
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $ids = $payload['ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $status = (string) ($payload['status'] ?? '');
        $updated = $controller->bulkSetStatus($ids, $status);
        $msg = $updated > 0
            ? "Updated status for {$updated} agenc" . ($updated === 1 ? 'y' : 'ies')
            : 'No matching agencies were updated';
        partnershipsJson(['success' => true, 'message' => $msg, 'data' => ['updated' => $updated]]);
    }

    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            throw new InvalidArgumentException('Agency id is required');
        }
        $controller->delete($id);
        partnershipsJson(['success' => true, 'message' => 'Agency deleted successfully']);
    }

    partnershipsJson(['success' => false, 'message' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    partnershipsJson(['success' => false, 'message' => $e->getMessage()], 500);
}

