<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/advances.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/advances.php`.
 */
if (isset($_GET['control']) && (string)$_GET['control'] === '1') {
    session_name('ratib_control');
}
require_once __DIR__ . '/hr-api-bootstrap.inc.php';
// Disable caching for this API endpoint
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
ob_start();
// Disable error display to prevent HTML output in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load response.php - try root Utils first, then api/utils
$responsePath = __DIR__ . '/../../Utils/response.php';
if (!file_exists($responsePath)) {
    $responsePath = __DIR__ . '/../../api/utils/response.php';
}
if (!file_exists($responsePath)) {
    error_log('ERROR: response.php not found. Tried: ' . __DIR__ . '/../../Utils/response.php and ' . __DIR__ . '/../../api/utils/response.php');
    // Simple response function if Utils/response.php doesn't exist
    if (!function_exists('sendResponse')) {
        function sendResponse($data, $statusCode = 200) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }
    }
} else {
    require_once $responsePath;
}

require_once __DIR__ . '/hr-connection.php';

try {
    $conn = hr_api_get_connection();

    $action = $_GET['action'] ?? 'list';
    $id = $_GET['id'] ?? null;

    switch ($action) {
        case 'list':
            // Check if table exists (PDO MySQL: do not rely on rowCount() for SHOW TABLES)
            $tableCheck = $conn->query("SHOW TABLES LIKE 'advances'");
            $advancesTableExists = ($tableCheck !== false && $tableCheck->fetch(PDO::FETCH_NUM) !== false);
            if (!$advancesTableExists) {
                ob_clean();
                sendResponse([
                    'success' => true,
                    'data' => [],
                    'pagination' => [
                        'total' => 0,
                        'page' => 1,
                        'limit' => 5,
                        'pages' => 0
                    ]
                ]);
                break;
            }
            
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 5; // Max 100 per page
            $status = isset($_GET['status']) ? trim($_GET['status']) : '';
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            
            // Validate search length
            if (strlen($search) > 255) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Search term too long'], 400);
            }
            
            $offset = ($page - 1) * $limit;
            
            $whereConditions = [];
            $params = [];
            
            if (!empty($status)) {
                $whereConditions[] = "a.status = :status";
                $params[':status'] = $status;
            }
            if (!empty($search)) {
                $searchPattern = '%' . $search . '%';
                $whereConditions[] = "(a.employee_name LIKE :search1 OR a.record_id LIKE :search2 OR a.purpose LIKE :search3)";
                $params[':search1'] = $params[':search2'] = $params[':search3'] = $searchPattern;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM advances a $whereClause";
            $countStmt = $conn->prepare($countQuery);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get advances
            $lim = (int) $limit;
            $off = (int) $offset;
            $query = "SELECT a.* FROM advances a $whereClause ORDER BY a.id DESC LIMIT {$lim} OFFSET {$off}";
            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $advances = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            sendResponse([
                'success' => true,
                'data' => $advances,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'add':
            // checkApiPermission('hr_add');
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Invalid input data'], 400);
            }
            
            // Validate required fields
            $requiredFields = ['employee_id', 'request_date', 'amount', 'repayment_date', 'purpose'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    ob_clean();
                    sendResponse(['success' => false, 'message' => "Field '$field' is required"], 400);
                }
            }
            
            // Validate numeric fields
            $amount = floatval($input['amount']);
            if ($amount <= 0) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Amount must be greater than 0'], 400);
            }
            if ($amount > 1000000) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Amount exceeds maximum limit'], 400);
            }
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['request_date'])) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Invalid request date format. Use YYYY-MM-DD'], 400);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['repayment_date'])) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Invalid repayment date format. Use YYYY-MM-DD'], 400);
            }
            
            // Validate date logic
            if (strtotime($input['repayment_date']) < strtotime($input['request_date'])) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Repayment date cannot be before request date'], 400);
            }
            
            // Validate string lengths
            if (strlen($input['purpose']) > 500) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Purpose too long (max 500 characters)'], 400);
            }
            
            // Get employee name
            $empQuery = "SELECT name FROM employees WHERE id = :id";
            $empStmt = $conn->prepare($empQuery);
            $empStmt->bindParam(':id', $input['employee_id'], PDO::PARAM_INT);
            $empStmt->execute();
            $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$employee) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Employee not found'], 404);
            }
            
            // Generate record ID with AD0001 format
            require_once __DIR__ . '/../core/formatted-id-helper.php';
            try {
                $recordId = generateHRAdvanceId($conn);
            } catch (Exception $e) {
                error_log("Error generating HR advance ID: " . $e->getMessage());
                // Fallback to simple ID generation (AD prefix for Advances)
                $recordId = 'AD' . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
            }
            
            $query = "INSERT INTO advances (
                record_id, employee_id, employee_name, request_date, amount, repayment_date, 
                purpose, status, created_at, updated_at
            ) VALUES (
                :record_id, :employee_id, :employee_name, :request_date, :amount, :repayment_date,
                :purpose, :status, NOW(), NOW()
            )";
            
            $stmt = $conn->prepare($query);
            $status = $input['status'] ?? 'pending';
            
            $stmt->bindParam(':record_id', $recordId);
            $stmt->bindParam(':employee_id', $input['employee_id'], PDO::PARAM_INT);
            $stmt->bindParam(':employee_name', $employee['name']);
            $stmt->bindParam(':request_date', $input['request_date']);
            $stmt->bindParam(':amount', $input['amount']);
            $stmt->bindParam(':repayment_date', $input['repayment_date']);
            $stmt->bindParam(':purpose', $input['purpose']);
            $stmt->bindParam(':status', $status);
            
            // Start transaction for atomic operation
            $conn->beginTransaction();
            
            try {
                $stmt->execute();
                
                $newAdvanceId = $conn->lastInsertId();
                
                // Get created advance for history
                $stmt = $conn->prepare("SELECT * FROM advances WHERE id = ?");
                $stmt->execute([$newAdvanceId]);
                $newAdvance = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Log history
                if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                    require_once __DIR__ . '/../core/global-history-helper.php';
                    @logGlobalHistory('advances', $newAdvanceId, 'create', 'hr', null, $newAdvance);
                }
                
                // Commit transaction
                $conn->commit();
                
                ob_clean();
                sendResponse([
                    'success' => true,
                    'message' => 'Advance request submitted successfully',
                    'data' => ['id' => $newAdvanceId]
                ]);
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                error_log("HR Advance add transaction error: " . $e->getMessage());
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Failed to submit advance: ' . $e->getMessage()], 500);
            }
            break;
            
        case 'get':
            if (!$id) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Advance ID is required'], 400);
            }
            
            $query = "SELECT * FROM advances WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $advance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($advance) {
                ob_clean();
                sendResponse([
                    'success' => true,
                    'data' => $advance
                ]);
            } else {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Advance record not found'], 404);
            }
            break;
            
        case 'update':
            // checkApiPermission('hr_edit');
            
            if (!$id) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Advance ID is required'], 400);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Invalid input data'], 400);
            }
            
            // Get old data for history
            $stmt = $conn->prepare("SELECT * FROM advances WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $oldAdvance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldAdvance) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Advance not found'], 404);
            }
            
            $updateFields = [];
            $params = [':id' => $id];
            
            $allowedFields = ['employee_id', 'amount', 'repayment_date', 'purpose', 'status', 'approved_by'];
            $employeeIdUpdated = false;
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $input[$field];
                    if ($field === 'employee_id') {
                        $employeeIdUpdated = true;
                    }
                }
            }
            
            // If employee_id was updated, fetch and update employee_name
            if ($employeeIdUpdated && isset($input['employee_id'])) {
                $empQuery = "SELECT name FROM employees WHERE id = :id";
                $empStmt = $conn->prepare($empQuery);
                $empStmt->bindParam(':id', $input['employee_id'], PDO::PARAM_INT);
                $empStmt->execute();
                $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($employee) {
                    $updateFields[] = "employee_name = :employee_name";
                    $params[':employee_name'] = $employee['name'];
                }
            }
            
            if (empty($updateFields)) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
            }
            
            $updateFields[] = "updated_at = NOW()";
            
            $query = "UPDATE advances SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            
            // Get updated advance for history
            $stmt = $conn->prepare("SELECT * FROM advances WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $updatedAdvance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Log history
            if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('advances', $id, 'update', 'hr', $oldAdvance, $updatedAdvance);
            }
            
            ob_clean();
            sendResponse(['success' => true, 'message' => 'Advance updated successfully']);
            break;
            
        case 'delete':
            // checkApiPermission('hr_delete');
            
            if (!$id) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Advance ID is required'], 400);
            }
            
            // Get deleted data for history (before deletion)
            $stmt = $conn->prepare("SELECT * FROM advances WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $deletedAdvance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$deletedAdvance) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Advance not found'], 404);
            }
            
            $query = "DELETE FROM advances WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Log history
            if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('advances', $id, 'delete', 'hr', $deletedAdvance, null);
            }
            
            ob_clean();
            sendResponse(['success' => true, 'message' => 'Advance deleted successfully']);
            break;
            
        case 'bulk-update':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['ids']) || !is_array($input['ids']) || empty($input['ids'])) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Invalid IDs provided'], 400);
            }
            
            if (!isset($input['status'])) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Status is required'], 400);
            }
            
            // Validate status value
            $allowedStatuses = ['pending', 'approved', 'rejected', 'paid'];
            if (!in_array($input['status'], $allowedStatuses)) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Invalid status value'], 400);
            }
            
            // Validate and sanitize IDs - must be integers
            $validIds = [];
            foreach ($input['ids'] as $id) {
                $id = intval($id);
                if ($id > 0) {
                    $validIds[] = $id;
                }
            }
            
            if (empty($validIds)) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'No valid IDs provided'], 400);
            }
            
            // Limit bulk operations to prevent abuse
            if (count($validIds) > 100) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Too many IDs (max 100)'], 400);
            }
            
            $placeholders = implode(',', array_fill(0, count($validIds), '?'));
            $query = "UPDATE advances SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            
            $stmt = $conn->prepare($query);
            $stmt->execute(array_merge([$input['status']], $validIds));
            
            ob_clean();
            sendResponse([
                'success' => true,
                'message' => count($validIds) . ' advance(s) updated successfully'
            ]);
            break;
            
        case 'bulk-delete':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['ids']) || !is_array($input['ids']) || empty($input['ids'])) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Invalid IDs provided'], 400);
            }
            
            // Validate and sanitize IDs - must be integers
            $validIds = [];
            foreach ($input['ids'] as $id) {
                $id = intval($id);
                if ($id > 0) {
                    $validIds[] = $id;
                }
            }
            
            if (empty($validIds)) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'No valid IDs provided'], 400);
            }
            
            // Limit bulk operations to prevent abuse
            if (count($validIds) > 100) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Too many IDs (max 100)'], 400);
            }
            
            $placeholders = implode(',', array_fill(0, count($validIds), '?'));
            $query = "DELETE FROM advances WHERE id IN ($placeholders)";
            
            $stmt = $conn->prepare($query);
            $stmt->execute($validIds);
            
            ob_clean();
            sendResponse([
                'success' => true,
                'message' => count($validIds) . ' advance(s) deleted successfully'
            ]);
            break;
            
        default:
            ob_clean();
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("HR Advances API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return proper JSON error response
    ob_clean();
    sendResponse(['success' => false, 'message' => 'An error occurred while processing your request'], 500);
} catch (Error $e) {
    // Handle PHP fatal errors
    error_log("HR Advances API Fatal Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    ob_clean();
    sendResponse(['success' => false, 'message' => 'A system error occurred'], 500);
} catch (Throwable $e) {
    error_log("HR Advances API Throwable Error: " . $e->getMessage());
    ob_clean();
    sendResponse(['success' => false, 'message' => 'A system error occurred'], 500);
}
?>
