<?php
/**
 * EN: Handles API endpoint/business logic in `api/agents/create.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/agents/create.php`.
 */
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../core/api-permission-helper.php';
enforceApiPermission('agents', 'create');

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ApiResponse.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid JSON data received');
    }
    
    // Validate required fields
    $required = ['full_name', 'email', 'phone'];
    if (isset($data['username']) || isset($data['password'])) {
        $required = array_merge($required, ['username', 'password']);
    }
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("$field is required");
        }
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }
    
    // Check if email already exists
    $db = Database::getInstance();
    $existingAgent = $db->queryOne("SELECT id FROM agents WHERE email = ?", [$data['email']]);
    if ($existingAgent) {
        throw new Exception("Email already exists");
    }
    
    // Start transaction for user + agent creation
    $conn = $db->getConnection();
    if (method_exists($conn, 'beginTransaction')) {
        $conn->beginTransaction();
    }
    
    try {
        $user_id = null;
        
        // Create user account if username and password provided
        if (!empty($data['username']) && !empty($data['password'])) {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'agent')");
            $stmt->execute([$data['username'], $hashedPassword, $data['email']]);
            $user_id = $conn->lastInsertId();
        }
        
        // Create the agent
        $agent = $db->createAgent($data);
        
        // Log activity if user_id is available
        if ($user_id && isset($_SESSION['user_id'])) {
            $description = "Created new agent: " . $data['full_name'];
            $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, 'create_agent', ?)");
            $stmt->execute([$_SESSION['user_id'], $description]);
        }
        
        if (method_exists($conn, 'commit')) {
            $conn->commit();
        }
    
        // Auto-create GL account in system accounts for this agent
        $agentId = $agent['id'] ?? null;
        $agentName = $agent['agent_name'] ?? $agent['full_name'] ?? $data['full_name'] ?? '';
        if ($agentId && $agentName) {
            require_once __DIR__ . '/../accounting/entity-account-helper.php';
            if (function_exists('ensureEntityAccount')) {
                ensureEntityAccount($conn, 'agent', $agentId, $agentName);
            }
        }
    
        ob_clean();
        echo ApiResponse::success($agent, 'Agent created successfully');
        exit;
        
    } catch (Exception $e) {
        if (method_exists($conn, 'rollBack')) {
            $conn->rollBack();
        }
        throw $e;
    }
    
} catch (Exception $e) {
    ob_clean();
    echo ApiResponse::error($e->getMessage());
    exit;
} catch (Error $e) {
    ob_clean();
    echo ApiResponse::error("An unexpected error occurred");
    exit;
} catch (Throwable $e) {
    ob_clean();
    echo ApiResponse::error("An unexpected error occurred");
    exit;
}