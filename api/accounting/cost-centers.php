<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/cost-centers.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/cost-centers.php`.
 */
require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check permissions - use accounts permission as fallback
$method = $_SERVER['REQUEST_METHOD'];
$MODULE_PERMISSIONS = require __DIR__ . '/../core/module-permissions.php';
if (isset($MODULE_PERMISSIONS['cost_centers'])) {
    if ($method === 'GET') {
        enforceApiPermission('cost_centers', 'view');
    } elseif ($method === 'POST') {
        enforceApiPermission('cost_centers', 'create');
    } elseif ($method === 'PUT') {
        enforceApiPermission('cost_centers', 'update');
    } elseif ($method === 'DELETE') {
        enforceApiPermission('cost_centers', 'delete');
    }
} else {
    // Fallback to accounts permission if cost_centers doesn't exist
    if ($method === 'GET') {
        enforceApiPermission('accounts', 'view');
    } elseif ($method === 'POST') {
        enforceApiPermission('accounts', 'create');
    } elseif ($method === 'PUT') {
        enforceApiPermission('accounts', 'update');
    } elseif ($method === 'DELETE') {
        enforceApiPermission('accounts', 'delete');
    }
}

try {
    // Check if cost_centers table exists, create if not
    $tableCheck = $conn->query("SHOW TABLES LIKE 'cost_centers'");
    if ($tableCheck->num_rows === 0) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS cost_centers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_by INT,
                INDEX idx_code (code),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    if ($method === 'GET') {
        $id = isset($_GET['id']) ? intval($_GET['id']) : null;
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        
        if ($id) {
            // Get single cost center
            $stmt = $conn->prepare("SELECT * FROM cost_centers WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                echo json_encode([
                    'success' => true,
                    'cost_center' => [
                        'id' => intval($row['id']),
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'description' => $row['description'],
                        'status' => $row['status'],
                        'linked_journal_entries' => 0,
                        'linked_approvals' => 0,
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ]
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Cost center not found']);
            }
        } else {
            // Get all cost centers
            $query = "SELECT * FROM cost_centers WHERE 1=1";
            $params = [];
            $types = '';
            
            if ($status) {
                $query .= " AND status = ?";
                $params[] = $status;
                $types .= 's';
            }
            
            $query .= " ORDER BY id DESC, created_at DESC";
            
            if (!empty($params)) {
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
            } else {
                $result = $conn->query($query);
            }
            
            $costCenters = [];
            while ($row = $result->fetch_assoc()) {
                $costCenterId = intval($row['id']);
                
                // Count linked journal entries
                $journalCount = 0;
                $journalCheck = $conn->query("SHOW COLUMNS FROM journal_entry_lines LIKE 'cost_center_id'");
                if ($journalCheck && $journalCheck->num_rows > 0) {
                    $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM journal_entry_lines WHERE cost_center_id = ?");
                    $countStmt->bind_param('i', $costCenterId);
                    $countStmt->execute();
                    $countResult = $countStmt->get_result();
                    if ($countRow = $countResult->fetch_assoc()) {
                        $journalCount = intval($countRow['count']);
                    }
                }
                
                // Count linked entry approvals
                $approvalCount = 0;
                $approvalCheck = $conn->query("SHOW COLUMNS FROM entry_approval LIKE 'cost_center_id'");
                if ($approvalCheck && $approvalCheck->num_rows > 0) {
                    $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM entry_approval WHERE cost_center_id = ?");
                    $countStmt->bind_param('i', $costCenterId);
                    $countStmt->execute();
                    $countResult = $countStmt->get_result();
                    if ($countRow = $countResult->fetch_assoc()) {
                        $approvalCount = intval($countRow['count']);
                    }
                }
                
                $costCenters[] = [
                    'id' => $costCenterId,
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'status' => $row['status'],
                    'linked_journal_entries' => $journalCount,
                    'linked_approvals' => $approvalCount,
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'cost_centers' => $costCenters,
                'count' => count($costCenters)
            ]);
        }
    } elseif ($method === 'POST') {
        // Create new cost center
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }
        
        $code = trim($data['code'] ?? '');
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $status = $data['status'] ?? 'active';
        $createdBy = $_SESSION['user_id'] ?? null;
        
        if (empty($code) || empty($name)) {
            throw new Exception('Code and name are required');
        }
        
        // Check if code already exists
        $checkStmt = $conn->prepare("SELECT id FROM cost_centers WHERE code = ?");
        $checkStmt->bind_param('s', $code);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            throw new Exception('Cost center code already exists');
        }
        
        $stmt = $conn->prepare("
            INSERT INTO cost_centers (code, name, description, status, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('ssssi', $code, $name, $description, $status, $createdBy);
        $stmt->execute();
        $costCenterId = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Cost center created successfully',
            'cost_center_id' => $costCenterId
        ]);
    } elseif ($method === 'PUT') {
        // Update cost center
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($id <= 0) {
            throw new Exception('Cost center ID is required');
        }
        
        $code = trim($data['code'] ?? '');
        $name = trim($data['name'] ?? '');
        $description = trim($data['description'] ?? '');
        $status = $data['status'] ?? 'active';
        
        if (empty($code) || empty($name)) {
            throw new Exception('Code and name are required');
        }
        
        // Check if code already exists for another cost center
        $checkStmt = $conn->prepare("SELECT id FROM cost_centers WHERE code = ? AND id != ?");
        $checkStmt->bind_param('si', $code, $id);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            throw new Exception('Cost center code already exists');
        }
        
        $stmt = $conn->prepare("
            UPDATE cost_centers 
            SET code = ?, name = ?, description = ?, status = ?
            WHERE id = ?
        ");
        $stmt->bind_param('ssssi', $code, $name, $description, $status, $id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cost center updated successfully'
        ]);
    } elseif ($method === 'DELETE') {
        // Delete cost center
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id <= 0) {
            throw new Exception('Cost center ID is required');
        }
        
        $stmt = $conn->prepare("DELETE FROM cost_centers WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cost center deleted successfully'
        ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

