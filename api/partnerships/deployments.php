<?php
/**
 * EN: Handles API endpoint/business logic in `api/partnerships/deployments.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/partnerships/deployments.php`.
 */
ob_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
require_once __DIR__ . '/../core/ensure-global-partnerships-schema.php';
require_once __DIR__ . '/../core/ensure-government-labor-schema.php';
require_once __DIR__ . '/DeploymentController.php';

function deploymentsJson($payload, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function deploymentsCsv(array $rows): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="deployments-export.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Worker', 'Country', 'Agency', 'Status', 'Job Title', 'Salary', 'Contract Start', 'Contract End']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['worker_name'] ?? '',
            $row['country'] ?? '',
            $row['partner_agency_name'] ?? '',
            $row['status'] ?? '',
            $row['job_title'] ?? '',
            $row['salary'] ?? '',
            $row['contract_start'] ?? '',
            $row['contract_end'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $deploymentsPerm = 'view';
    if ($method === 'POST') {
        $deploymentsPerm = 'create';
    } elseif ($method === 'PUT') {
        $deploymentsPerm = 'update';
    } elseif ($method === 'DELETE') {
        $deploymentsPerm = 'delete';
    }
    enforceApiPermission('deployments', $deploymentsPerm);

    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratibEnsureGlobalPartnershipsSchema($conn);
    ratibEnsureGovernmentLaborSchema($conn);
    $controller = new DeploymentController($conn);

    if ($method === 'GET') {
        if (isset($_GET['stats'])) {
            deploymentsJson(['success' => true, 'data' => $controller->stats()]);
        }

        $rows = $controller->index([
            'country' => $_GET['country'] ?? '',
            'search' => $_GET['search'] ?? '',
            'worker_id' => (int) ($_GET['worker_id'] ?? 0),
            'status' => $_GET['status'] ?? '',
            'active_abroad' => (!empty($_GET['active_abroad']) && (string) $_GET['active_abroad'] === '1'),
            'expiring_within_days' => (int) ($_GET['expiring_within_days'] ?? 0),
        ]);
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            deploymentsCsv($rows);
        }
        deploymentsJson(['success' => true, 'data' => $rows]);
    }

    if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $created = $controller->create($payload);
        deploymentsJson(['success' => true, 'message' => 'Deployment created successfully', 'data' => $created], 201);
    }

    if ($method === 'PUT') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            throw new InvalidArgumentException('Deployment id is required');
        }
        $payload = json_decode(file_get_contents('php://input'), true) ?: [];
        $updated = $controller->updateStatus($id, (string) ($payload['status'] ?? ''));
        deploymentsJson(['success' => true, 'message' => 'Deployment status updated', 'data' => $updated]);
    }

    if ($method === 'DELETE') {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            throw new InvalidArgumentException('Deployment id is required');
        }
        $controller->delete($id);
        deploymentsJson(['success' => true, 'message' => 'Deployment deleted successfully']);
    }

    deploymentsJson(['success' => false, 'message' => 'Method not allowed'], 405);
} catch (Throwable $e) {
    deploymentsJson(['success' => false, 'message' => $e->getMessage()], 500);
}

