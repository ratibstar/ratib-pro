<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/musaned/update.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/musaned/update.php`.
 */
/**
 * Musaned bulk status update — uses the same PDO country DB as workers/core/get.php
 * (Database + $GLOBALS['agency_db']), not a separate mysqli(DB_*).
 */
header('Content-Type: application/json');

error_reporting(0);
ini_set('display_errors', 0);

// POST-only: mirror bridge params so includes/config.php control SSO sees them like a GET page load
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!isset($_GET['control']) && isset($_POST['_control']) && (string) $_POST['_control'] === '1') {
        $_GET['control'] = '1';
    }
    if (!isset($_GET['agency_id']) && isset($_POST['_agency_id'])) {
        $aid = (int) $_POST['_agency_id'];
        if ($aid > 0) {
            $_GET['agency_id'] = (string) $aid;
        }
    }
}

require_once __DIR__ . '/../../core/ratib_api_session.inc.php';
ratib_api_pick_session_name();

$configPath = __DIR__ . '/../../../includes/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Configuration not found']);
    exit;
}
require_once $configPath;

require_once __DIR__ . '/../../core/Database.php';

try {
    $workerId = isset($_POST['worker_id']) ? (int) $_POST['worker_id'] : 0;
    if ($workerId <= 0) {
        throw new Exception('Worker ID is required');
    }

    $db = Database::getInstance();
    $pdo = $db->getConnection();
    if (!$pdo) {
        throw new Exception('Database connection not available');
    }

    $chk = $pdo->prepare('SELECT id FROM workers WHERE id = ? LIMIT 1');
    $chk->execute([$workerId]);
    if (!$chk->fetchColumn()) {
        throw new Exception('Worker not found');
    }

    $statusFields = [
        'musaned_status', 'contract_status', 'embassy_status', 'epro_approval_status',
        'epro_approved_status', 'fmol_approval_status', 'fmol_approved_status',
        'saudi_embassy_status', 'visa_issued_status', 'arrived_ksa_status',
        'rejected_status', 'canceled_status', 'visa_canceled_status',
    ];
    $issuesFields = [
        'musaned_issues', 'contract_issues', 'embassy_issues', 'epro_approval_issues',
        'epro_approved_issues', 'fmol_approval_issues', 'fmol_approved_issues',
        'saudi_embassy_issues', 'visa_issued_issues', 'arrived_ksa_issues',
        'rejected_issues', 'canceled_issues', 'visa_canceled_issues',
    ];

    $setParts = [];
    $params = [];

    foreach ($statusFields as $field) {
        if (!isset($_POST[$field])) {
            continue;
        }
        $value = (string) $_POST[$field];
        if (!in_array($value, ['done', 'not_done', 'issues', 'canceled', 'pending'], true)) {
            $value = 'pending';
        }
        $setParts[] = '`' . $field . '` = ?';
        $params[] = $value;
    }

    foreach ($issuesFields as $field) {
        if (!array_key_exists($field, $_POST)) {
            continue;
        }
        $setParts[] = '`' . $field . '` = ?';
        $params[] = $_POST[$field];
    }

    if ($setParts === []) {
        throw new Exception('No status fields to update');
    }

    $fetchOld = $pdo->prepare('SELECT * FROM workers WHERE id = ?');
    $fetchOld->execute([$workerId]);
    $oldWorker = $fetchOld->fetch(PDO::FETCH_ASSOC);
    if (!$oldWorker) {
        throw new Exception('Worker not found');
    }

    $params[] = $workerId;
    $sql = 'UPDATE workers SET ' . implode(', ', $setParts) . ' WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $fetchNew = $pdo->prepare('SELECT * FROM workers WHERE id = ?');
    $fetchNew->execute([$workerId]);
    $newWorker = $fetchNew->fetch(PDO::FETCH_ASSOC);

    $helperPath = __DIR__ . '/../../core/global-history-helper.php';
    if (is_file($helperPath) && $oldWorker && $newWorker) {
        require_once $helperPath;
        if (function_exists('logGlobalHistory')) {
            @logGlobalHistory('workers', $workerId, 'update', 'workers', $oldWorker, $newWorker);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Musaned statuses updated successfully',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
}
