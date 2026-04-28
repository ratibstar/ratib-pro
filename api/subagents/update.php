<?php
/**
 * EN: Handles API endpoint/business logic in `api/subagents/update.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/subagents/update.php`.
 */
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('subagents', 'update');

try {
    $corePath = __DIR__ . '/../core/';
    require_once $corePath . 'Database.php';
    require_once $corePath . 'ApiResponse.php';

    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if (!$id) {
        ob_clean();
        echo ApiResponse::error('Subagent ID is required');
        exit;
    }
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        ob_clean();
        echo ApiResponse::error('Invalid JSON input');
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM subagents WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$id]);
    $oldSubagent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldSubagent) {
        ob_clean();
        echo ApiResponse::error('Subagent not found');
        exit;
    }
    
    $updateFields = [];
    $params = [];
    
    if (isset($data['full_name'])) {
        $updateFields[] = "subagent_name = ?";
        $params[] = $data['full_name'];
    }
    if (isset($data['email'])) {
        $updateFields[] = "email = ?";
        $params[] = $data['email'];
    }
    if (isset($data['phone'])) {
        $updateFields[] = "contact_number = ?";
        $params[] = $data['phone'];
    }
    if (isset($data['city'])) {
        $updateFields[] = "city = ?";
        $params[] = $data['city'];
    }
    if (isset($data['address'])) {
        $updateFields[] = "address = ?";
        $params[] = $data['address'];
    }
    if (isset($data['agent_id'])) {
        $updateFields[] = "agent_id = ?";
        $params[] = (int)$data['agent_id'];
    }
    if (isset($data['status'])) {
        $status = strtolower($data['status']);
        $status = ($status === 'active' || $status === 'inactive') ? $status : 'active';
        $updateFields[] = "status = ?";
        $params[] = $status;
    }
    
    if (empty($updateFields)) {
        ob_clean();
        echo ApiResponse::error('No fields to update');
        exit;
    }
    
    $updateFields[] = "updated_at = CURRENT_TIMESTAMP";
    $params[] = $id;
    
    $sql = "UPDATE subagents SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    $query = "SELECT s.*, a.agent_name FROM subagents s LEFT JOIN agents a ON s.agent_id = a.id WHERE s.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$id]);
    $subagent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $transformed = [
        'subagent_id' => $subagent['id'],
        'formatted_id' => 'S' . str_pad($subagent['id'], 4, '0', STR_PAD_LEFT),
        'full_name' => $subagent['subagent_name'],
        'email' => $subagent['email'],
        'phone' => $subagent['contact_number'],
        'city' => $subagent['city'] ?? null,
        'address' => $subagent['address'] ?? null,
        'agent_id' => $subagent['agent_id'],
        'agent_name' => $subagent['agent_name'] ?? null,
        'status' => $subagent['status'],
        'created_at' => $subagent['created_at'],
        'updated_at' => $subagent['updated_at']
    ];
    
    if (file_exists(__DIR__ . '/../core/global-history-helper.php')) {
        require_once __DIR__ . '/../core/global-history-helper.php';
        @logGlobalHistory('subagents', $id, 'update', 'subagents', $oldSubagent, $subagent);
    }
    
    ob_clean();
    echo ApiResponse::success($transformed, 'Subagent updated successfully');
    exit;
    
} catch (Exception $e) {
    error_log("Subagents update error: " . $e->getMessage());
    error_log("Subagents update stack trace: " . $e->getTraceAsString());
    ob_clean();
    echo ApiResponse::error($e->getMessage());
    exit;
} catch (Error $e) {
    error_log("Subagents update fatal error: " . $e->getMessage());
    error_log("Subagents update fatal stack trace: " . $e->getTraceAsString());
    ob_clean();
    echo ApiResponse::error("Fatal error: " . $e->getMessage());
    exit;
}

