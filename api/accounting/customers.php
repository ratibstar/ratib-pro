<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/customers.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/customers.php`.
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
    enforceApiPermission('receivables', 'view');
} elseif ($method === 'POST') {
    enforceApiPermission('receivables', 'create');
} elseif ($method === 'PUT') {
    enforceApiPermission('receivables', 'update');
} elseif ($method === 'DELETE') {
    enforceApiPermission('receivables', 'delete');
}

try {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_customers'");
    if ($tableCheck->num_rows === 0) {
        // Create table if it doesn't exist
        $createTable = "
            CREATE TABLE IF NOT EXISTS accounting_customers (
                id INT PRIMARY KEY AUTO_INCREMENT,
                customer_name VARCHAR(100) NOT NULL,
                contact_person VARCHAR(100),
                email VARCHAR(100),
                phone VARCHAR(20),
                address TEXT,
                credit_limit DECIMAL(15,2) DEFAULT 0.00,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_active (is_active),
                INDEX idx_name (customer_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        if (!$conn->query($createTable)) {
            throw new Exception('Failed to create accounting_customers table: ' . $conn->error);
        }
    }
    
    if ($method === 'GET') {
        $customerId = isset($_GET['id']) ? intval($_GET['id']) : null;
        $isActive = isset($_GET['is_active']) ? intval($_GET['is_active']) : null;
        
        if ($customerId) {
            // Get single customer
            $stmt = $conn->prepare("SELECT * FROM accounting_customers WHERE id = ?");
            $stmt->bind_param('i', $customerId);
            $stmt->execute();
            $result = $stmt->get_result();
            $customer = $result->fetch_assoc();
            
            if ($customer) {
                echo json_encode([
                    'success' => true,
                    'customer' => $customer
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Customer not found'
                ]);
            }
        } else {
            // Get all customers
            $query = "SELECT * FROM accounting_customers";
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
            
            $query .= " ORDER BY customer_name ASC";
            
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            
            $customers = [];
            while ($row = $result->fetch_assoc()) {
                $customers[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'customers' => $customers
            ]);
        }
    } elseif ($method === 'POST') {
        // Create new customer
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            throw new Exception('Invalid request data');
        }
        
        $customerName = $data['customer_name'] ?? '';
        $contactPerson = $data['contact_person'] ?? null;
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;
        $address = $data['address'] ?? null;
        $creditLimit = floatval($data['credit_limit'] ?? 0);
        $isActive = isset($data['is_active']) ? intval($data['is_active']) : 1;
        
        if (empty($customerName)) {
            throw new Exception('Customer name is required');
        }
        
        $stmt = $conn->prepare("
            INSERT INTO accounting_customers 
            (customer_name, contact_person, email, phone, address, credit_limit, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssssdi', $customerName, $contactPerson, $email, $phone, $address, $creditLimit, $isActive);
        $stmt->execute();
        $customerId = $conn->insert_id;
        
        // Get created customer for history
        $fetchStmt = $conn->prepare("SELECT * FROM accounting_customers WHERE id = ?");
        $fetchStmt->bind_param('i', $customerId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $newCustomer = $result->fetch_assoc();
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $newCustomer) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('accounting_customers', $customerId, 'create', 'accounting', null, $newCustomer);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Customer created successfully',
            'customer_id' => $customerId
        ]);
    } elseif ($method === 'PUT') {
        // Update customer
        $customerId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($customerId <= 0) {
            throw new Exception('Customer ID is required');
        }
        
        // Get old data for history
        $fetchStmt = $conn->prepare("SELECT * FROM accounting_customers WHERE id = ?");
        $fetchStmt->bind_param('i', $customerId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $oldCustomer = $result->fetch_assoc();
        
        if (!$oldCustomer) {
            throw new Exception('Customer not found');
        }
        
        $customerName = $data['customer_name'] ?? '';
        $contactPerson = $data['contact_person'] ?? null;
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;
        $address = $data['address'] ?? null;
        $creditLimit = floatval($data['credit_limit'] ?? 0);
        $isActive = isset($data['is_active']) ? intval($data['is_active']) : 1;
        
        if (empty($customerName)) {
            throw new Exception('Customer name is required');
        }
        
        $stmt = $conn->prepare("
            UPDATE accounting_customers 
            SET customer_name = ?, contact_person = ?, email = ?, phone = ?, address = ?, credit_limit = ?, is_active = ?
            WHERE id = ?
        ");
        $stmt->bind_param('sssssdii', $customerName, $contactPerson, $email, $phone, $address, $creditLimit, $isActive, $customerId);
        $stmt->execute();
        
        // Get updated customer for history
        $fetchStmt = $conn->prepare("SELECT * FROM accounting_customers WHERE id = ?");
        $fetchStmt->bind_param('i', $customerId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $updatedCustomer = $result->fetch_assoc();
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $oldCustomer && $updatedCustomer) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('accounting_customers', $customerId, 'update', 'accounting', $oldCustomer, $updatedCustomer);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Customer updated successfully'
        ]);
    } elseif ($method === 'DELETE') {
        // Delete customer (soft delete by setting is_active = 0)
        $customerId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($customerId <= 0) {
            throw new Exception('Customer ID is required');
        }
        
        // Get old data for history (before deletion)
        $fetchStmt = $conn->prepare("SELECT * FROM accounting_customers WHERE id = ?");
        $fetchStmt->bind_param('i', $customerId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $oldCustomer = $result->fetch_assoc();
        
        if (!$oldCustomer) {
            throw new Exception('Customer not found');
        }
        
        $stmt = $conn->prepare("UPDATE accounting_customers SET is_active = 0 WHERE id = ?");
        $stmt->bind_param('i', $customerId);
        $stmt->execute();
        
        // Get updated customer for history (after soft delete)
        $fetchStmt = $conn->prepare("SELECT * FROM accounting_customers WHERE id = ?");
        $fetchStmt->bind_param('i', $customerId);
        $fetchStmt->execute();
        $result = $fetchStmt->get_result();
        $deletedCustomer = $result->fetch_assoc();
        
        // Log history
        $helperPath = __DIR__ . '/../core/global-history-helper.php';
        if (file_exists($helperPath) && $oldCustomer && $deletedCustomer) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('accounting_customers', $customerId, 'update', 'accounting', $oldCustomer, $deletedCustomer);
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Customer deleted successfully'
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

