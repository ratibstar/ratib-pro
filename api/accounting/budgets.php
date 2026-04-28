<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/budgets.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/budgets.php`.
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

$method = $_SERVER['REQUEST_METHOD'];

// Check permissions
if ($method === 'GET') {
    enforceApiPermission('accounts', 'view');
} elseif ($method === 'POST' || $method === 'PUT') {
    enforceApiPermission('accounts', 'edit');
} elseif ($method === 'DELETE') {
    enforceApiPermission('accounts', 'delete');
}

try {
    // Check if budgets table exists, create if not
    $tableCheck = $conn->query("SHOW TABLES LIKE 'budgets'");
    if ($tableCheck->num_rows === 0) {
        $conn->query("
            CREATE TABLE IF NOT EXISTS budgets (
                id INT PRIMARY KEY AUTO_INCREMENT,
                budget_name VARCHAR(255) NOT NULL,
                period_type ENUM('Monthly', 'Quarterly', 'Yearly') NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                status ENUM('Draft', 'Active', 'Closed') DEFAULT 'Draft',
                notes TEXT,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    if ($method === 'GET') {
        $budgetId = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if ($budgetId) {
            // Get single budget with line items
            $stmt = $conn->prepare("SELECT * FROM budgets WHERE id = ?");
            $stmt->bind_param('i', $budgetId);
            $stmt->execute();
            $result = $stmt->get_result();
            $budget = $result->fetch_assoc();
            
            if ($budget) {
                // Get line items
                $stmt = $conn->prepare("
                    SELECT bli.*, fa.account_code, fa.account_name, fa.account_type
                    FROM budget_line_items bli
                    LEFT JOIN financial_accounts fa ON bli.account_id = fa.id
                    WHERE bli.budget_id = ?
                    ORDER BY fa.account_code
                ");
                $stmt->bind_param('i', $budgetId);
                $stmt->execute();
                $lineItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                
                $budget['line_items'] = $lineItems;
                
                echo json_encode([
                    'success' => true,
                    'budget' => $budget
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Budget not found'
                ]);
            }
        } else {
            // Get all budgets
            $stmt = $conn->prepare("SELECT * FROM budgets ORDER BY start_date DESC, created_at DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            
            $budgets = [];
            while ($row = $result->fetch_assoc()) {
                // Get total budgeted amount
                $stmt2 = $conn->prepare("SELECT COALESCE(SUM(budgeted_amount), 0) as total FROM budget_line_items WHERE budget_id = ?");
                $stmt2->bind_param('i', $row['id']);
                $stmt2->execute();
                $totalResult = $stmt2->get_result()->fetch_assoc();
                $row['total_budgeted'] = floatval($totalResult['total'] ?? 0);
                
                $budgets[] = $row;
            }
            
            echo json_encode([
                'success' => true,
                'budgets' => $budgets
            ]);
        }
    } elseif ($method === 'POST') {
        // Create new budget
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['budget_name']) || !isset($data['start_date']) || !isset($data['end_date'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Budget name, start date, and end date are required'
            ]);
            exit;
        }
        
        $budgetName = $data['budget_name'];
        $periodType = $data['period_type'] ?? 'Monthly';
        $startDate = $data['start_date'];
        $endDate = $data['end_date'];
        $status = $data['status'] ?? 'Draft';
        $notes = $data['notes'] ?? null;
        $userId = $_SESSION['user_id'];
        
        $conn->begin_transaction();
        
        try {
            // Insert budget
            $stmt = $conn->prepare("
                INSERT INTO budgets (budget_name, period_type, start_date, end_date, status, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('ssssssi', $budgetName, $periodType, $startDate, $endDate, $status, $notes, $userId);
            $stmt->execute();
            $budgetId = $conn->insert_id;
            
            // Insert line items if provided
            if (isset($data['line_items']) && is_array($data['line_items'])) {
                $stmt = $conn->prepare("
                    INSERT INTO budget_line_items (budget_id, account_id, budgeted_amount, notes)
                    VALUES (?, ?, ?, ?)
                ");
                
                foreach ($data['line_items'] as $item) {
                    if (isset($item['account_id']) && isset($item['budgeted_amount'])) {
                        $accountId = intval($item['account_id']);
                        $budgetedAmount = floatval($item['budgeted_amount']);
                        $itemNotes = $item['notes'] ?? null;
                        $stmt->bind_param('iids', $budgetId, $accountId, $budgetedAmount, $itemNotes);
                        $stmt->execute();
                    }
                }
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Budget created successfully',
                'budget_id' => $budgetId
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } elseif ($method === 'PUT') {
        // Update budget
        $budgetId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$budgetId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Budget ID is required']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        $conn->begin_transaction();
        
        try {
            // Update budget
            $updateFields = [];
            $params = [];
            $types = '';
            
            if (isset($data['budget_name'])) {
                $updateFields[] = "budget_name = ?";
                $params[] = $data['budget_name'];
                $types .= 's';
            }
            if (isset($data['period_type'])) {
                $updateFields[] = "period_type = ?";
                $params[] = $data['period_type'];
                $types .= 's';
            }
            if (isset($data['start_date'])) {
                $updateFields[] = "start_date = ?";
                $params[] = $data['start_date'];
                $types .= 's';
            }
            if (isset($data['end_date'])) {
                $updateFields[] = "end_date = ?";
                $params[] = $data['end_date'];
                $types .= 's';
            }
            if (isset($data['status'])) {
                $updateFields[] = "status = ?";
                $params[] = $data['status'];
                $types .= 's';
            }
            if (isset($data['notes'])) {
                $updateFields[] = "notes = ?";
                $params[] = $data['notes'];
                $types .= 's';
            }
            
            if (!empty($updateFields)) {
                $params[] = $budgetId;
                $types .= 'i';
                $query = "UPDATE budgets SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
            }
            
            // Update line items if provided
            if (isset($data['line_items']) && is_array($data['line_items'])) {
                // Delete existing line items
                $stmt = $conn->prepare("DELETE FROM budget_line_items WHERE budget_id = ?");
                $stmt->bind_param('i', $budgetId);
                $stmt->execute();
                
                // Insert new line items
                $stmt = $conn->prepare("
                    INSERT INTO budget_line_items (budget_id, account_id, budgeted_amount, notes)
                    VALUES (?, ?, ?, ?)
                ");
                
                foreach ($data['line_items'] as $item) {
                    if (isset($item['account_id']) && isset($item['budgeted_amount'])) {
                        $accountId = intval($item['account_id']);
                        $budgetedAmount = floatval($item['budgeted_amount']);
                        $itemNotes = $item['notes'] ?? null;
                        $stmt->bind_param('iids', $budgetId, $accountId, $budgetedAmount, $itemNotes);
                        $stmt->execute();
                    }
                }
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Budget updated successfully'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    } elseif ($method === 'DELETE') {
        $budgetId = isset($_GET['id']) ? intval($_GET['id']) : null;
        
        if (!$budgetId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Budget ID is required']);
            exit;
        }
        
        $conn->begin_transaction();
        
        try {
            // Delete line items first
            $stmt = $conn->prepare("DELETE FROM budget_line_items WHERE budget_id = ?");
            $stmt->bind_param('i', $budgetId);
            $stmt->execute();
            
            // Delete budget
            $stmt = $conn->prepare("DELETE FROM budgets WHERE id = ?");
            $stmt->bind_param('i', $budgetId);
            $stmt->execute();
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Budget deleted successfully'
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }
    
} catch (Exception $e) {
    error_log('Budgets API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

