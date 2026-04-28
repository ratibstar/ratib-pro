<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/get_users.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/get_users.php`.
 */
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

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

// Check if users table exists and get its structure
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        // Create users table if it doesn't exist
        $sql = "CREATE TABLE users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role_id INT DEFAULT 1,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);
        
        // Insert sample users
        $sampleUsers = [
            ['admin', 'admin@ratibprogram.com', password_hash('admin123', PASSWORD_DEFAULT), 1],
            ['manager', 'manager@ratibprogram.com', password_hash('manager123', PASSWORD_DEFAULT), 2],
            ['editor', 'editor@ratibprogram.com', password_hash('editor123', PASSWORD_DEFAULT), 3],
            ['viewer', 'viewer@ratibprogram.com', password_hash('viewer123', PASSWORD_DEFAULT), 4],
            ['operator', 'operator@ratibprogram.com', password_hash('operator123', PASSWORD_DEFAULT), 5]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role_id) VALUES (?, ?, ?, ?)");
        foreach ($sampleUsers as $user) {
            $stmt->execute($user);
        }
    } else {
        // Table exists, check and add missing columns safely
        $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        
        // Check if there's already an auto-increment column
        $autoIncrementColumn = null;
        $stmt = $pdo->query("SHOW COLUMNS FROM users");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (strpos($row['Extra'], 'auto_increment') !== false) {
                $autoIncrementColumn = $row['Field'];
                break;
            }
        }
        
        // Add missing columns if they don't exist (but don't add duplicate auto-increment)
        if (!in_array('username', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN username VARCHAR(50) NOT NULL UNIQUE");
        }
        if (!in_array('email', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(100) NOT NULL UNIQUE");
        }
        if (!in_array('password', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN password VARCHAR(255) NOT NULL");
        }
        if (!in_array('role_id', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN role_id INT DEFAULT 1");
        }
        if (!in_array('status', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive') DEFAULT 'active'");
        }
        if (!in_array('created_at', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }
        if (!in_array('updated_at', $columns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
        
        // If no auto-increment column exists, add one
        if (!$autoIncrementColumn) {
            if (!in_array('user_id', $columns)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN user_id INT AUTO_INCREMENT PRIMARY KEY FIRST");
            }
        }
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Table setup failed: ' . $e->getMessage()]);
    exit;
}

// Handle different HTTP methods
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // Get all users
        try {
            // Get the actual column names from the table
            $stmt = $pdo->query("SHOW COLUMNS FROM users");
            $tableColumns = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tableColumns[] = $row['Field'];
            }
            
            // Build SELECT query with available columns
            $selectColumns = [];
            $requiredColumns = ['user_id', 'username', 'email', 'role_id', 'status', 'created_at'];
            
            foreach ($requiredColumns as $col) {
                if (in_array($col, $tableColumns)) {
                    $selectColumns[] = $col;
                }
            }
            
            if (empty($selectColumns)) {
                $selectColumns = ['*']; // Fallback to all columns
            }
            
            $sql = "SELECT " . implode(', ', $selectColumns) . " FROM users ORDER BY created_at DESC";
            $stmt = $pdo->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'users' => $users, 'available_columns' => $tableColumns]);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch users: ' . $e->getMessage()]);
        }
        break;
        
    case 'POST':
        // Create new user
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);




            exit;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role_id, status) VALUES (?, ?, ?, ?, ?)");
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $roleId = $data['role_id'] ?? 1;
            $status = $data['status'] ?? 'active';
            
            $stmt->execute([$data['username'], $data['email'], $hashedPassword, $roleId, $status]);
            $userId = $pdo->lastInsertId();
            
            // Auto-create GL account in system accounts when user has Accounting role (non-fatal)
            try {
                $roleStmt = $pdo->prepare("SELECT role_name FROM roles WHERE role_id = ? LIMIT 1");
                if ($roleStmt && $roleStmt->execute([$roleId])) {
                    $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
                    if ($roleRow && strtolower(trim($roleRow['role_name'] ?? '')) === 'accounting') {
                        $helperPath = __DIR__ . '/../accounting/entity-account-helper.php';
                        if (file_exists($helperPath)) {
                            require_once $helperPath;
                            if (function_exists('ensureEntityAccount')) {
                                ensureEntityAccount($pdo, 'accounting', $userId, $data['username'] ?? '');
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                error_log("get_users: ensureEntityAccount for Accounting role failed (user created): " . $e->getMessage());
            }
            
            echo json_encode(['success' => true, 'message' => 'User created successfully', 'user_id' => $userId]);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . $e->getMessage()]);
        }
        break;
        
    case 'PUT':
        // Update user
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing user ID']);
            exit;
        }
        
        try {
            $updates = [];
            $params = [];
            
            if (isset($data['username'])) {
                $updates[] = "username = ?";
                $params[] = $data['username'];
            }
            if (isset($data['email'])) {
                $updates[] = "email = ?";
                $params[] = $data['email'];
            }
            if (isset($data['role_id'])) {
                $updates[] = "role_id = ?";
                $params[] = $data['role_id'];
            }
            if (isset($data['status'])) {
                $updates[] = "status = ?";
                $params[] = $data['status'];
            }
            
            if (empty($updates)) {
                echo json_encode(['success' => false, 'message' => 'No fields to update']);
                exit;
            }
            
            $params[] = $data['user_id'];
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to update user: ' . $e->getMessage()]);
        }
        break;
        
    case 'DELETE':
        // Delete user(s)
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['user_ids'])) {
            echo json_encode(['success' => false, 'message' => 'Missing user IDs']);
            exit;
        }
        
        try {
            $userIds = is_array($data['user_ids']) ? $data['user_ids'] : [$data['user_ids']];
            $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
            $sql = "DELETE FROM users WHERE user_id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($userIds);
            
            echo json_encode(['success' => true, 'message' => 'User(s) deleted successfully']);
        } catch(PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to delete user(s): ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
}
?>
