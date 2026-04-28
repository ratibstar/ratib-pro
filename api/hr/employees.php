<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/employees.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/employees.php`.
 */
// Use control session when ?control=1 so control panel requests are authenticated
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

if (!function_exists('hr_normalize_email_string')) {
    /**
     * Trim and remove whitespace from email (avoid /u PCRE on hosts where unicode regex breaks).
     */
    function hr_normalize_email_string($email)
    {
        if ($email === null || !is_string($email)) {
            return '';
        }
        $email = trim($email);
        $stripped = preg_replace('/\s+/', '', $email);
        return ($stripped === null) ? '' : (string) $stripped;
    }
}

try {
    hr_api_require_control_panel_auth();
    $conn = hr_api_get_connection();

    $action = $_GET['action'] ?? 'list';
    $id = $_GET['id'] ?? null;

    switch ($action) {
        case 'list':
            hr_api_enforce_employees_permission('list');
            // Check if table exists (PDO MySQL: do not rely on rowCount() for SHOW TABLES)
            $tableCheck = $conn->query("SHOW TABLES LIKE 'employees'");
            $employeesTableExists = ($tableCheck !== false && $tableCheck->fetch(PDO::FETCH_NUM) !== false);
            if (!$employeesTableExists) {
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
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $status = isset($_GET['status']) ? trim($_GET['status']) : '';
            $department = isset($_GET['department']) ? trim($_GET['department']) : '';
            
            // Validate search length
            if (strlen($search) > 255) {
                sendResponse(['success' => false, 'message' => 'Search term too long'], 400);
            }
            
            $offset = ($page - 1) * $limit;
            
            $whereConditions = [];
            $params = [];
            
            if (!empty($search)) {
                $searchPattern = '%' . trim($search) . '%';
                $whereConditions[] = "(name LIKE :search1 OR email LIKE :search2 OR employee_id LIKE :search3)";
                $params[':search1'] = $params[':search2'] = $params[':search3'] = $searchPattern;
            }
            
            if (!empty($status)) {
                $whereConditions[] = "status = :status";
                $params[':status'] = $status;
            }
            
            if (!empty($department)) {
                $whereConditions[] = "department = :department";
                $params[':department'] = $department;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM employees $whereClause";
            $countStmt = $conn->prepare($countQuery);
            $countStmt->execute($params);
            $countRow = $countStmt->fetch(PDO::FETCH_ASSOC);
            $total = (int)($countRow['total'] ?? 0);
            
            // Get employees (integer LIMIT/OFFSET — native PDO MySQL often rejects bound LIMIT)
            $lim = (int) $limit;
            $off = (int) $offset;
            $query = "SELECT * FROM employees $whereClause ORDER BY id DESC LIMIT {$lim} OFFSET {$off}";
            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ob_clean();
            sendResponse([
                'success' => true,
                'data' => $employees,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            break;
            
        case 'get':
            hr_api_enforce_employees_permission('get');
            if (!$id) {
                sendResponse(['success' => false, 'message' => 'Employee ID is required'], 400);
            }
            
            $query = "SELECT * FROM employees WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$employee) {
                sendResponse(['success' => false, 'message' => 'Employee not found'], 404);
            }
            
            ob_clean();
            sendResponse(['success' => true, 'data' => $employee]);
            break;
            
        case 'add':
            hr_api_enforce_employees_permission('add');
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                sendResponse(['success' => false, 'message' => 'Invalid input data'], 400);
            }
            
            if (isset($input['email'])) {
                $input['email'] = hr_normalize_email_string($input['email']);
            }
            
            // Validate required fields - check if columns exist before requiring them
            $columnsQuery = "SHOW COLUMNS FROM employees";
            $columnsStmt = $conn->query($columnsQuery);
            $existingColumns = [];
            if ($columnsStmt) {
                while ($row = $columnsStmt->fetch(PDO::FETCH_ASSOC)) {
                    $existingColumns[] = $row['Field'];
                }
            }
            
            // Base required fields (always required)
            $requiredFields = ['name', 'email', 'phone', 'address', 'join_date'];
            
            // Add optional required fields only if columns exist
            if (in_array('country', $existingColumns)) {
                $requiredFields[] = 'country';
            }
            if (in_array('city', $existingColumns)) {
                $requiredFields[] = 'city';
            }
            if (in_array('department', $existingColumns)) {
                $requiredFields[] = 'department';
            }
            if (in_array('position', $existingColumns)) {
                $requiredFields[] = 'position';
            }
            
            foreach ($requiredFields as $field) {
                if (!isset($input[$field]) || (is_string($input[$field]) && trim($input[$field]) === '')) {
                    sendResponse(['success' => false, 'message' => "Field '$field' is required"], 400);
                }
            }
            
            // Validate email format (same rules as PHP; addresses like "x@y" without a dot in the domain are rejected)
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                sendResponse([
                    'success' => false,
                    'message' => 'Invalid email format. Use a full address such as name@company.com (the part after @ must include a domain with a dot).',
                ], 400);
            }
            
            // Validate string lengths
            if (strlen($input['name']) > 255) {
                sendResponse(['success' => false, 'message' => 'Name too long (max 255 characters)'], 400);
            }
            if (strlen($input['email']) > 255) {
                sendResponse(['success' => false, 'message' => 'Email too long (max 255 characters)'], 400);
            }
            if (isset($input['phone']) && strlen($input['phone']) > 50) {
                sendResponse(['success' => false, 'message' => 'Phone too long (max 50 characters)'], 400);
            }
            
            // Check if email already exists
            $checkQuery = "SELECT COUNT(*) as count FROM employees WHERE email = :email";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bindParam(':email', $input['email']);
            $checkStmt->execute();
            if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                sendResponse(['success' => false, 'message' => 'Email already exists'], 400);
            }
            
            // Start transaction for atomic operation
            $conn->beginTransaction();
            
            try {
                // Generate employee ID with EM0001 format
                require_once __DIR__ . '/../core/formatted-id-helper.php';
                try {
                    $employeeId = generateHREmployeeId($conn);
                } catch (Exception $e) {
                    error_log("Error generating HR employee ID: " . $e->getMessage());
                    // Fallback to simple ID generation
                    $employeeId = 'EM' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                }
                
                // $existingColumns is already set from validation above, reuse it
                // Build dynamic INSERT query based on existing columns
            $insertFields = ['employee_id', 'name', 'email', 'phone', 'birthdate', 'join_date', 'address', 'status', 'type', 'basic_salary', 'created_at', 'updated_at'];
            $insertValues = [':employee_id', ':name', ':email', ':phone', ':birthdate', ':join_date', ':address', ':status', ':type', ':basic_salary', 'NOW()', 'NOW()'];
            
            // Add department if column exists
            if (in_array('department', $existingColumns)) {
                $birthdateIndex = array_search('birthdate', $insertFields);
                array_splice($insertFields, $birthdateIndex + 1, 0, 'department');
                array_splice($insertValues, $birthdateIndex + 1, 0, ':department');
            }
            
            // Add position if column exists
            if (in_array('position', $existingColumns)) {
                $deptIndex = array_search('department', $insertFields);
                if ($deptIndex !== false) {
                    array_splice($insertFields, $deptIndex + 1, 0, 'position');
                    array_splice($insertValues, $deptIndex + 1, 0, ':position');
                } else {
                    $birthdateIndex = array_search('birthdate', $insertFields);
                    array_splice($insertFields, $birthdateIndex + 1, 0, 'position');
                    array_splice($insertValues, $birthdateIndex + 1, 0, ':position');
                }
            }
            
            // Add country and city if columns exist
            if (in_array('country', $existingColumns)) {
                // Insert country after address
                $addressIndex = array_search('address', $insertFields);
                array_splice($insertFields, $addressIndex + 1, 0, 'country');
                array_splice($insertValues, $addressIndex + 1, 0, ':country');
            }
            if (in_array('city', $existingColumns)) {
                // Insert city after country (or after address if country doesn't exist)
                $countryIndex = array_search('country', $insertFields);
                if ($countryIndex !== false) {
                    array_splice($insertFields, $countryIndex + 1, 0, 'city');
                    array_splice($insertValues, $countryIndex + 1, 0, ':city');
                } else {
                    $addressIndex = array_search('address', $insertFields);
                    array_splice($insertFields, $addressIndex + 1, 0, 'city');
                    array_splice($insertValues, $addressIndex + 1, 0, ':city');
                }
            }
            
            $query = "INSERT INTO employees (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertValues) . ")";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':employee_id', $employeeId);
            $stmt->bindParam(':name', $input['name']);
            $stmt->bindParam(':email', $input['email']);
            $stmt->bindParam(':phone', $input['phone']);
            $stmt->bindParam(':birthdate', $input['birthdate']);
            $stmt->bindParam(':join_date', $input['join_date']);
            $stmt->bindParam(':address', $input['address']);
            
            // Only bind department and position if columns exist
            if (in_array('department', $existingColumns)) {
                $stmt->bindParam(':department', $input['department']);
            }
            if (in_array('position', $existingColumns)) {
                $stmt->bindParam(':position', $input['position']);
            }
            
            // Only bind country and city if columns exist
            if (in_array('country', $existingColumns)) {
                $stmt->bindParam(':country', $input['country']);
            }
            if (in_array('city', $existingColumns)) {
                $stmt->bindParam(':city', $input['city']);
            }
            $status = $input['status'] ?? 'Active';
            $type = $input['type'] ?? 'Full-time';
            $basicSalary = $input['basic_salary'] ?? 0;
            
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':basic_salary', $basicSalary);
            $stmt->execute();
            
            $newEmployeeId = $conn->lastInsertId();
            
            // Auto-create GL account (Ratib Pro HR only — not control-panel HR DB)
            if (hr_api_writes_ratib_artifacts()) {
                try {
                    require_once __DIR__ . '/../accounting/entity-account-helper.php';
                    $employeeName = $input['name'] ?? '';
                    if ($employeeName && function_exists('ensureEntityAccount')) {
                        $dbConn = isset($conn) ? $conn : ($GLOBALS['conn'] ?? null);
                        if ($dbConn) {
                            ensureEntityAccount($dbConn, 'hr', $newEmployeeId, $employeeName);
                        }
                    }
                } catch (Throwable $e) {
                    error_log("HR Employee add: ensureEntityAccount failed (non-fatal): " . $e->getMessage());
                }
            }
            
            // Get created employee for history
            $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
            $stmt->execute([$newEmployeeId]);
            $newEmployee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('employees', $newEmployeeId, 'create', 'hr', null, $newEmployee);
            }
            
            // Commit transaction
            $conn->commit();
            
            ob_clean();
            sendResponse([
                'success' => true,
                'message' => 'Employee added successfully',
                'data' => ['id' => $newEmployeeId, 'employee_id' => $employeeId]
            ]);
            
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                error_log("HR Employee add transaction error: " . $e->getMessage());
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Failed to add employee: ' . $e->getMessage()], 500);
            }
            break;
            
        case 'update':
        case 'edit':
            hr_api_enforce_employees_permission('update');
            
            // Get ID from URL parameter or request body
            if (!$id) {
                $input = json_decode(file_get_contents('php://input'), true);
                $id = $input['id'] ?? null;
            }
            
            if (!$id) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Employee ID is required'], 400);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Invalid input data'], 400);
            }
            
            if (isset($input['email'])) {
                $input['email'] = hr_normalize_email_string($input['email']);
            }
            
            // Get old data for history
            $stmt = $conn->prepare("SELECT * FROM employees WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $oldEmployee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldEmployee) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Employee not found'], 404);
            }
            
            $updateFields = [];
            $params = [':id' => $id];
            
            // First, check which columns actually exist in the table
            $columnsQuery = "SHOW COLUMNS FROM employees";
            $columnsStmt = $conn->query($columnsQuery);
            $existingColumns = [];
            if ($columnsStmt) {
                while ($row = $columnsStmt->fetch(PDO::FETCH_ASSOC)) {
                    $existingColumns[] = $row['Field'];
                }
            }
            
            // Only include fields that exist in the database
            $allowedFields = ['name', 'email', 'phone', 'birthdate', 'department', 'position', 'join_date', 'country', 'city', 'address', 'status', 'type', 'basic_salary'];
            foreach ($allowedFields as $field) {
                // Only add field if it exists in the table and is provided in input
                if (in_array($field, $existingColumns) && isset($input[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $input[$field];
                }
            }
            
            if (empty($updateFields)) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
            }
            
            if (isset($params[':email']) && is_string($params[':email']) && $params[':email'] !== ''
                && !filter_var($params[':email'], FILTER_VALIDATE_EMAIL)) {
                ob_clean();
                sendResponse([
                    'success' => false,
                    'message' => 'Invalid email format. Use a full address such as name@company.com (the part after @ must include a domain with a dot).',
                ], 400);
            }
            
            $updateFields[] = "updated_at = NOW()";
            
            try {
                $query = "UPDATE employees SET " . implode(', ', $updateFields) . " WHERE id = :id";
                $stmt = $conn->prepare($query);
                
                if (!$stmt) {
                    ob_clean();
                    sendResponse(['success' => false, 'message' => 'Database error: Failed to prepare update query'], 500);
                }
                
                $result = $stmt->execute($params);
                
                if (!$result) {
                    $errorInfo = $stmt->errorInfo();
                    ob_clean();
                    sendResponse(['success' => false, 'message' => 'Database error: ' . ($errorInfo[2] ?? 'Unknown error')], 500);
                }
            } catch (PDOException $e) {
                error_log("Employee update error: " . $e->getMessage());
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
            }
            
            // Get updated employee for history
            $stmt = $conn->prepare("SELECT * FROM employees WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $updatedEmployee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('employees', $id, 'update', 'hr', $oldEmployee, $updatedEmployee);
            }
            
            ob_clean();
            sendResponse(['success' => true, 'message' => 'Employee updated successfully']);
            break;
            
        case 'delete':
            hr_api_enforce_employees_permission('delete');
            
            if (!$id) {
                sendResponse(['success' => false, 'message' => 'Employee ID is required'], 400);
            }
            
            // Get deleted data for history (before deletion)
            $stmt = $conn->prepare("SELECT * FROM employees WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $deletedEmployee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$deletedEmployee) {
                sendResponse(['success' => false, 'message' => 'Employee not found'], 404);
            }
            
            $query = "DELETE FROM employees WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('employees', $id, 'delete', 'hr', $deletedEmployee, null);
            }
            
            ob_clean();
            sendResponse(['success' => true, 'message' => 'Employee deleted successfully']);
            break;
            
        case 'bulk-update':
            hr_api_enforce_employees_permission('update');
            
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
            $allowedStatuses = ['Active', 'Inactive', 'On Leave', 'Terminated'];
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
            $query = "UPDATE employees SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            
            $stmt = $conn->prepare($query);
            $stmt->execute(array_merge([$input['status']], $validIds));
            
            ob_clean();
            sendResponse([
                'success' => true,
                'message' => count($validIds) . ' employee(s) updated successfully'
            ]);
            break;
            
        case 'bulk-delete':
            hr_api_enforce_employees_permission('delete');
            
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
            $query = "DELETE FROM employees WHERE id IN ($placeholders)";
            
            $stmt = $conn->prepare($query);
            $stmt->execute($validIds);
            
            ob_clean();
            sendResponse([
                'success' => true,
                'message' => count($validIds) . ' employee(s) deleted successfully'
            ]);
            break;
            
        default:
            ob_clean();
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
            break;
    }
    
} catch (Exception $e) {
    error_log("HR Employee API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    ob_clean();
    $msg = $e->getMessage();
    if (strpos($msg, 'Authentication required') !== false) {
        sendResponse(['success' => false, 'message' => $msg], 401);
    }
    if (strpos($msg, 'Missing required permission') !== false || strpos($msg, 'Module not configured') !== false || strpos($msg, 'Action not configured') !== false) {
        sendResponse(['success' => false, 'message' => $msg], 403);
    }
    if (strpos($msg, 'Cannot connect to control HR database') !== false || strpos($msg, 'CONTROL_PANEL_DB_NAME') !== false || strpos($msg, 'isolation requires') !== false || strpos($msg, 'SQLSTATE') !== false || strpos($msg, 'Access denied for user') !== false) {
        sendResponse([
            'success' => false,
            'message' => (defined('DEBUG_MODE') && DEBUG_MODE) ? $msg : 'Control HR database is unavailable. Create the control panel DB, run control_panel_hr_tables.sql on it, ensure CONTROL_PANEL_DB_NAME is set, and grant MySQL access (see CONTROL_PANEL_DB_USER in hr-connection.php).',
        ], 503);
    }
    sendResponse(['success' => false, 'message' => 'An error occurred while processing your request'], 500);
} catch (Error $e) {
    // Handle PHP fatal errors
    error_log("HR Employee API Fatal Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    ob_clean();
    sendResponse(['success' => false, 'message' => 'A system error occurred'], 500);
} catch (Throwable $e) {
    error_log("HR Employee API Throwable Error: " . $e->getMessage());
    ob_clean();
    sendResponse(['success' => false, 'message' => 'A system error occurred'], 500);
}
?>
