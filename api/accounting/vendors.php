<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/vendors.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/vendors.php`.
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

// Check permissions based on method
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    enforceApiPermission('payables', 'view');
} elseif ($method === 'POST') {
    enforceApiPermission('payables', 'create');
} elseif ($method === 'PUT') {
    enforceApiPermission('payables', 'update');
} elseif ($method === 'DELETE') {
    enforceApiPermission('payables', 'delete');
}

try {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_vendors'");
    if ($tableCheck->num_rows === 0) {
        // Create table if it doesn't exist
        $createTable = "
            CREATE TABLE IF NOT EXISTS accounting_vendors (
                id INT PRIMARY KEY AUTO_INCREMENT,
                vendor_name VARCHAR(100) NOT NULL,
                contact_person VARCHAR(100),
                email VARCHAR(100),
                phone VARCHAR(20),
                address TEXT,
                payment_terms INT DEFAULT 30,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_active (is_active),
                INDEX idx_name (vendor_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        if (!$conn->query($createTable)) {
            throw new Exception('Failed to create accounting_vendors table: ' . $conn->error);
        }
    }
    
    if ($method === 'GET') {
        $vendorId = isset($_GET['id']) ? intval($_GET['id']) : null;
        $isActive = isset($_GET['is_active']) ? intval($_GET['is_active']) : null;
        
        if ($vendorId) {
            // Get single vendor
            $stmt = $conn->prepare("SELECT * FROM accounting_vendors WHERE id = ?");
            $stmt->bind_param('i', $vendorId);
            $stmt->execute();
            $result = $stmt->get_result();
            $vendor = $result->fetch_assoc();
            
            if ($vendor) {
                echo json_encode([
                    'success' => true,
                    'vendor' => $vendor
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Vendor not found'
                ]);
            }
        } else {
            // Get all vendors
            $query = "SELECT * FROM accounting_vendors";
            $conditions = [];
            $params = [];
            $types = '';
            
            if ($isActive !== null) {
                $conditions[] = "is_active = ?";
                $params[] = $isActive;
                $types .= 'i';
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(' AND ', $conditions);
            }
            
            $query .= " ORDER BY vendor_name ASC";
            
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $vendors = [];
            while ($row = $result->fetch_assoc()) {
                $vendors[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'vendors' => $vendors
            ]);
        }
    } elseif ($method === 'POST') {
        // Create new vendor
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            throw new Exception('Invalid request data');
        }
        
        $vendorName = $data['vendor_name'] ?? '';
        $contactPerson = $data['contact_person'] ?? null;
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;
        $address = $data['address'] ?? null;
        $paymentTerms = intval($data['payment_terms'] ?? 30);
        $isActive = isset($data['is_active']) ? intval($data['is_active']) : 1;
        
        if (empty($vendorName)) {
            throw new Exception('Vendor name is required');
        }
        
        $stmt = $conn->prepare("
            INSERT INTO accounting_vendors 
            (vendor_name, contact_person, email, phone, address, payment_terms, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssssii', $vendorName, $contactPerson, $email, $phone, $address, $paymentTerms, $isActive);
        $stmt->execute();
        $vendorId = $conn->insert_id;
        
        // Get created vendor for history
        $fetchStmt = $conn->prepare("SELECT * FROM accounting_vendors WHERE id = ?");
        $fetchStmt->bind_param('i', $vendorId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $newVendor = $result->fetch_assoc();
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $newVendor) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('accounting_vendors', $vendorId, 'create', 'accounting', null, $newVendor);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Vendor created successfully',
            'vendor_id' => $vendorId
        ]);
    } elseif ($method === 'PUT') {
        // Update vendor
        $vendorId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($vendorId <= 0) {
            throw new Exception('Vendor ID is required');
        }
        
        // Get old data for history
        $fetchStmt = $conn->prepare("SELECT * FROM accounting_vendors WHERE id = ?");
        $fetchStmt->bind_param('i', $vendorId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $oldVendor = $result->fetch_assoc();
        
        if (!$oldVendor) {
            throw new Exception('Vendor not found');
        }
        
        $vendorName = $data['vendor_name'] ?? '';
        $contactPerson = $data['contact_person'] ?? null;
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;
        $address = $data['address'] ?? null;
        $paymentTerms = intval($data['payment_terms'] ?? 30);
        $isActive = isset($data['is_active']) ? intval($data['is_active']) : 1;
        
        if (empty($vendorName)) {
            throw new Exception('Vendor name is required');
        }
        
        $stmt = $conn->prepare("
            UPDATE accounting_vendors 
            SET vendor_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, payment_terms = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->bind_param('sssssiii', $vendorName, $contactPerson, $email, $phone, $address, $paymentTerms, $isActive, $vendorId);
        $stmt->execute();
        
        // Get updated vendor for history
        $fetchStmt = $conn->prepare("SELECT * FROM accounting_vendors WHERE id = ?");
        $fetchStmt->bind_param('i', $vendorId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $updatedVendor = $result->fetch_assoc();
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $oldVendor && $updatedVendor) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('accounting_vendors', $vendorId, 'update', 'accounting', $oldVendor, $updatedVendor);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Vendor updated successfully'
        ]);
    } elseif ($method === 'DELETE') {
        // Delete vendor (soft delete by setting is_active = 0)
        $vendorId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($vendorId <= 0) {
            throw new Exception('Vendor ID is required');
        }
        
        // Get old data for history (before deletion)
        $fetchStmt = $conn->prepare("SELECT * FROM accounting_vendors WHERE id = ?");
        $fetchStmt->bind_param('i', $vendorId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $oldVendor = $result->fetch_assoc();
        
        if (!$oldVendor) {
            throw new Exception('Vendor not found');
        }
        
        $stmt = $conn->prepare("UPDATE accounting_vendors SET is_active = 0 WHERE id = ?");
        $stmt->bind_param('i', $vendorId);
        $stmt->execute();
        
        // Get updated vendor for history (after soft delete)
        $fetchStmt = $conn->prepare("SELECT * FROM accounting_vendors WHERE id = ?");
        $fetchStmt->bind_param('i', $vendorId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $deletedVendor = $result->fetch_assoc();
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $oldVendor && $deletedVendor) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('accounting_vendors', $vendorId, 'update', 'accounting', $oldVendor, $deletedVendor);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Vendor deleted successfully'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

