<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/user_permissions.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/user_permissions.php`.
 */
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Admin-only endpoint
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Database connection using config constants
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Create permissions tables if they don't exist
try {
    // Create permissions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        category VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create user_permissions table
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        permission_id INT NOT NULL,
        granted BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_permission (user_id, permission_id)
    )");
    
    // Insert default permissions if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM permissions");
    if ($stmt->fetchColumn() == 0) {
        $defaultPermissions = [
            ['users_view', 'View Users', 'User Management'],
            ['users_create', 'Create Users', 'User Management'],
            ['users_edit', 'Edit Users', 'User Management'],
            ['users_delete', 'Delete Users', 'User Management'],
            ['settings_view', 'View Settings', 'System Settings'],
            ['settings_edit', 'Edit Settings', 'System Settings'],
            ['reports_view', 'View Reports', 'Reports'],
            ['reports_export', 'Export Reports', 'Reports'],
            ['data_view', 'View Data', 'Data Management'],
            ['data_edit', 'Edit Data', 'Data Management'],
            ['data_delete', 'Delete Data', 'Data Management']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO permissions (name, description, category) VALUES (?, ?, ?)");
        foreach ($defaultPermissions as $permission) {
            $stmt->execute($permission);
        }
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Table creation failed: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // Get user permissions
        $userId = $_GET['user_id'] ?? null;
        
        if ($userId) {
            try {
                $stmt = $pdo->prepare("
                    SELECT p.*, up.granted 
                    FROM permissions p 
                    LEFT JOIN user_permissions up ON p.id = up.permission_id AND up.user_id = ?
                    ORDER BY p.category, p.name
                ");
                $stmt->execute([$userId]);
                $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'permissions' => $permissions]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch permissions: ' . $e->getMessage()]);
            }
        } else {
            // Get all permissions
            try {
                $stmt = $pdo->query("SELECT * FROM permissions ORDER BY category, name");
                $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'permissions' => $permissions]);
            } catch(PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch permissions: ' . $e->getMessage()]);
            }
        }
        break;
        
    case 'POST':
        // Save user permissions
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['user_id']) || !isset($data['permissions'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required data']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Delete existing permissions for this user
            $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
            $stmt->execute([$data['user_id']]);
            
            // Insert new permissions
            $stmt = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_id, granted) VALUES (?, ?, ?)");
            
            foreach ($data['permissions'] as $permissionName => $granted) {
                // Get permission ID
                $permStmt = $pdo->prepare("SELECT id FROM permissions WHERE name = ?");
                $permStmt->execute([$permissionName]);
                $permissionId = $permStmt->fetchColumn();
                
                if ($permissionId && $granted) {
                    $stmt->execute([$data['user_id'], $permissionId, true]);
                }
            }
            
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Permissions saved successfully']);
        } catch(PDOException $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to save permissions: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}
?>
