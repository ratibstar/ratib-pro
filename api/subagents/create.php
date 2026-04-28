<?php
/**
 * EN: Handles API endpoint/business logic in `api/subagents/create.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/subagents/create.php`.
 */
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('subagents', 'create');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ApiResponse.php';

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        ob_clean();
        echo ApiResponse::error('Invalid JSON input');
        exit;
    }
    
    // Validate required fields
    $required = ['full_name', 'email', 'phone'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            ob_clean();
            echo ApiResponse::error("$field is required");
            exit;
        }
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        ob_clean();
        echo ApiResponse::error('Invalid email format');
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM subagents WHERE email = ? AND status != 'deleted'");
    $stmt->execute([$data['email']]);
    $existingSubagent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingSubagent) {
        ob_clean();
        echo ApiResponse::error('Email already exists');
        exit;
    }
    
    // Prepare data for insertion
    $subagent_name = $data['full_name'];
    $email = $data['email'];
    $contact_number = $data['phone'];
    $city = $data['city'] ?? '';
    $address = $data['address'] ?? '';
    $agent_id = isset($data['agent_id']) ? (int)$data['agent_id'] : null;
    $status = isset($data['status']) ? strtolower($data['status']) : 'active';
    $status = ($status === 'active' || $status === 'inactive') ? $status : 'active';
    
    // Insert the subagent
    $sql = "INSERT INTO subagents (
        subagent_name, email, contact_number, city, address, agent_id, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $subagent_name,
        $email,
        $contact_number,
        $city,
        $address,
        $agent_id,
        $status
    ]);
    
    $newId = $conn->lastInsertId();
    
    // Auto-create GL account for this subagent in system accounts (helper supports PDO and mysqli)
    require_once __DIR__ . '/../accounting/entity-account-helper.php';
    if (function_exists('ensureEntityAccount') && $subagent_name) {
        ensureEntityAccount($conn, 'subagent', $newId, $subagent_name);
    }
    
    // Fetch the created subagent with agent name
    $query = "SELECT s.*, a.agent_name FROM subagents s LEFT JOIN agents a ON s.agent_id = a.id WHERE s.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$newId]);
    $subagent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$subagent) {
        ob_clean();
        echo ApiResponse::error('Failed to retrieve created subagent');
        exit;
    }
    
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
    
    // Log history if helper exists
    if (file_exists(__DIR__ . '/../core/global-history-helper.php')) {
        require_once __DIR__ . '/../core/global-history-helper.php';
        @logGlobalHistory('subagents', $newId, 'create', 'subagents', null, $subagent);
    }
    
    ob_clean();
    echo ApiResponse::success($transformed, 'Subagent created successfully');
    exit;
    
} catch (Exception $e) {
    error_log("Subagents create error: " . $e->getMessage());
    error_log("Subagents create stack trace: " . $e->getTraceAsString());
    ob_clean();
    echo ApiResponse::error($e->getMessage());
    exit;
} catch (Error $e) {
    error_log("Subagents create fatal error: " . $e->getMessage());
    error_log("Subagents create fatal stack trace: " . $e->getTraceAsString());
    ob_clean();
    echo ApiResponse::error("Fatal error: " . $e->getMessage());
    exit;
}

