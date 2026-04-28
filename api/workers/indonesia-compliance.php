<?php
/**
 * EN: API facade for Indonesia/BP2MI worker compliance validation and stage updates.
 * AR: واجهة API لطبقة امتثال إندونيسيا/BP2MI للتحقق وتحديث مراحل العامل.
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('workers', 'update');
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/indonesia-compliance-helper.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    ratib_indonesia_compliance_ensure_schema($pdo);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $input = $method === 'POST'
        ? (json_decode(file_get_contents('php://input'), true) ?: $_POST)
        : $_GET;

    $action = (string)($input['action'] ?? 'validate');
    $workerId = (int)($input['worker_id'] ?? $input['id'] ?? 0);
    if ($workerId <= 0) {
        throw new Exception('worker_id is required');
    }

    $stmt = $pdo->prepare("SELECT * FROM workers WHERE id = ? AND COALESCE(status, '') != 'deleted' LIMIT 1");
    $stmt->execute([$workerId]);
    $worker = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$worker) {
        throw new Exception('Worker not found');
    }

    if ($action === 'update_stage') {
        $targetStage = (string)($input['status_stage'] ?? '');
        $check = ratib_indonesia_can_move_to_stage($pdo, $worker, $targetStage);
        if (!$check['allowed']) {
            sendResponse([
                'success' => false,
                'message' => 'Worker is not ready for this Indonesia compliance stage.',
                'data' => [
                    'ready' => false,
                    'missing_items' => $check['missing_items'],
                ],
            ], 422);
        }

        $upd = $pdo->prepare('UPDATE workers SET status_stage = ?, status_stage_updated_at = NOW(), updated_at = NOW() WHERE id = ?');
        $upd->execute([$targetStage, $workerId]);
        $worker['status_stage'] = $targetStage;
        $worker['status_stage_updated_at'] = date('Y-m-d H:i:s');
    }

    $validation = validateWorkerForDeployment($workerId, $pdo);
    $documents = ratib_indonesia_document_statuses($pdo, $workerId, $worker);

    sendResponse([
        'success' => true,
        'data' => [
            'worker_id' => $workerId,
            'is_indonesia_worker' => ratib_indonesia_is_worker($worker),
            'status_stage' => $worker['status_stage'] ?? 'registered',
            'stages' => ratib_indonesia_worker_stages(),
            'documents' => $documents,
            'validation' => $validation,
        ],
    ]);
} catch (Exception $e) {
    sendResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}

