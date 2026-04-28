<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/banks.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/banks.php`.
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
    enforceApiPermission('bank-accounts', 'view');
} elseif ($method === 'POST') {
    enforceApiPermission('bank-accounts', 'create');
} elseif ($method === 'PUT') {
    enforceApiPermission('bank-accounts', 'update');
} elseif ($method === 'DELETE') {
    enforceApiPermission('bank-accounts', 'delete');
}

try {
    // Check if table exists, create if not
    $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_banks'");
    if ($tableCheck->num_rows === 0) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS accounting_banks (
                id INT PRIMARY KEY AUTO_INCREMENT,
                bank_name VARCHAR(100) NOT NULL,
                account_name VARCHAR(100) NOT NULL,
                account_number VARCHAR(50),
                account_type VARCHAR(50) DEFAULT 'Checking',
                opening_balance DECIMAL(15,2) DEFAULT 0.00,
                current_balance DECIMAL(15,2) DEFAULT 0.00,
                is_active BOOLEAN DEFAULT TRUE,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    // Handle different HTTP methods
    if ($method === 'GET') {
        $bankId = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if ($bankId) {
            // Get single bank account
            $stmt = $conn->prepare("
                SELECT 
                    id,
                    bank_name,
                    account_name,
                    account_number,
                    account_type,
                    opening_balance,
                    current_balance,
                    is_active,
                    created_at,
                    updated_at
                FROM accounting_banks
                WHERE id = ?
            ");
            $stmt->bind_param('i', $bankId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                echo json_encode([
                    'success' => true,
                    'bank' => [
                        'id' => intval($row['id']),
                        'bank_name' => $row['bank_name'],
                        'account_name' => $row['account_name'],
                        'account_number' => $row['account_number'],
                        'account_type' => $row['account_type'],
                        'opening_balance' => floatval($row['opening_balance']),
                        'current_balance' => floatval($row['current_balance']),
                        'is_active' => boolval($row['is_active']),
                        'created_at' => $row['created_at'],
                        'updated_at' => $row['updated_at']
                    ]
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Bank account not found'
                ]);
            }
        } else {
            // Get all bank accounts (both active and inactive) - newest first
            $stmt = $conn->prepare("
                SELECT 
                    id,
                    bank_name,
                    account_name,
                    account_number,
                    account_type,
                    opening_balance,
                    current_balance,
                    is_active,
                    created_at
                FROM accounting_banks
                ORDER BY created_at DESC, id DESC
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $banks = [];
            while ($row = $result->fetch_assoc()) {
                $banks[] = [
                    'id' => intval($row['id']),
                    'bank_name' => $row['bank_name'],
                    'account_name' => $row['account_name'],
                    'account_number' => $row['account_number'],
                    'account_type' => $row['account_type'],
                    'opening_balance' => floatval($row['opening_balance']),
                    'current_balance' => floatval($row['current_balance']),
                    'is_active' => boolval($row['is_active'])
                ];
            }
            
            echo json_encode([
                'success' => true,
                'banks' => $banks
            ]);
        }
    } elseif ($method === 'POST') {
        // Create new bank account
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            throw new Exception('Invalid request data');
        }
        
        $bankName = $data['bank_name'] ?? '';
        $accountName = $data['account_name'] ?? '';
        $accountNumber = $data['account_number'] ?? '';
        $accountType = $data['account_type'] ?? 'Checking';
        $openingBalance = floatval($data['opening_balance'] ?? 0);
        
        if (empty($bankName) || empty($accountName)) {
            throw new Exception('Bank name and account name are required');
        }
        
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                INSERT INTO accounting_banks 
                (bank_name, account_name, account_number, account_type, opening_balance, current_balance, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 1, ?)
            ");
            $userId = $_SESSION['user_id'];
            $stmt->bind_param('ssssddi', $bankName, $accountName, $accountNumber, $accountType, $openingBalance, $openingBalance, $userId);
            $stmt->execute();
            $bankId = $conn->insert_id;
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Bank account created successfully',
                'bank_id' => $bankId
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } elseif ($method === 'PUT') {
        // Update bank account
        $bankId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($bankId <= 0) {
            throw new Exception('Bank account ID is required');
        }
        
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                UPDATE accounting_banks
                SET bank_name = ?, account_name = ?, account_number = ?, account_type = ?, is_active = ?
                WHERE id = ?
            ");
            $bankName = $data['bank_name'] ?? '';
            $accountName = $data['account_name'] ?? '';
            $accountNumber = $data['account_number'] ?? '';
            $accountType = $data['account_type'] ?? 'Checking';
            $isActive = isset($data['is_active']) ? intval($data['is_active']) : 1;
            $stmt->bind_param('ssssii', $bankName, $accountName, $accountNumber, $accountType, $isActive, $bankId);
            $stmt->execute();
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Bank account updated successfully'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } elseif ($method === 'DELETE') {
        // Delete bank account (hard delete - permanently remove from database)
        $bankId = isset($_GET['id']) ? intval($_GET['id']) : 0;
        if ($bankId <= 0) {
            throw new Exception('Bank account ID is required');
        }
        
        $conn->begin_transaction();
        try {
            // Check if account exists
            $checkStmt = $conn->prepare("SELECT id FROM accounting_banks WHERE id = ?");
            $checkStmt->bind_param('i', $bankId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Bank account not found');
            }
            
            // Permanently delete the bank account
            $stmt = $conn->prepare("DELETE FROM accounting_banks WHERE id = ?");
            $stmt->bind_param('i', $bankId);
            $stmt->execute();
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Bank account deleted successfully'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
