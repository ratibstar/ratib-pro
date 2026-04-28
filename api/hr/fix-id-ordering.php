<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/fix-id-ordering.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/fix-id-ordering.php`.
 */
ob_start();
// Disable error display to prevent HTML output in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

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

// Load Database class
if (!file_exists(__DIR__ . '/../core/Database.php')) {
    error_log('ERROR: Database.php not found at ' . __DIR__ . '/../core/Database.php');
    ob_clean();
    sendResponse([
        'success' => false,
        'message' => 'Server configuration error: Database.php not found'
    ], 500);
}
require_once __DIR__ . '/../core/Database.php';

// Load permission helper if it exists
if (file_exists(__DIR__ . '/../core/api-permission-helper.php')) {
    require_once __DIR__ . '/../core/api-permission-helper.php';
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $action = $_GET['action'] ?? 'list';
    $id = $_GET['id'] ?? null;

    switch ($action) {
        case 'list':
            // Check if table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'employees'");
            if (!$tableCheck || $tableCheck->rowCount() == 0) {
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
            
            if (function_exists('enforceApiPermission')) {
                enforceApiPermission('employees', 'list');
            }
            
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 5;
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            $department = $_GET['department'] ?? '';
            
            $offset = ($page - 1) * $limit;
            
            $whereConditions = [];
            $params = [];
            
            if (!empty($search)) {
                $whereConditions[] = "(name LIKE :search OR email LIKE :search OR employee_id LIKE :search)";
                $params[':search'] = "%$search%";
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
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get employees
            $query = "SELECT * FROM employees $whereClause ORDER BY id DESC LIMIT :limit OFFSET :offset";
            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            
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
            enforceApiPermission('employees', 'get');
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
            enforceApiPermission('employees', 'add');
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                sendResponse(['success' => false, 'message' => 'Invalid input data'], 400);
            }
            
            // Validate required fields
            $requiredFields = ['name', 'email', 'phone', 'country', 'city', 'address', 'department', 'position', 'join_date'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    sendResponse(['success' => false, 'message' => "Field '$field' is required"], 400);
                }
            }
            
            // Check if email already exists
            $checkQuery = "SELECT COUNT(*) as count FROM employees WHERE email = :email";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bindParam(':email', $input['email']);
            $checkStmt->execute();
            if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                sendResponse(['success' => false, 'message' => 'Email already exists'], 400);
            }
            
            // Generate employee ID with EM0001 format
            require_once __DIR__ . '/../core/formatted-id-helper.php';
            try {
                $employeeId = generateHREmployeeId($conn);
            } catch (Exception $e) {
                error_log("Error generating HR employee ID: " . $e->getMessage());
                // Fallback to simple ID generation
                $employeeId = 'EM' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            }
            
            $query = "INSERT INTO employees (
                employee_id, name, email, phone, birthdate, department, position, join_date, 
                country, city, address, status, type, basic_salary, created_at, updated_at
            ) VALUES (
                :employee_id, :name, :email, :phone, :birthdate, :department, :position, :join_date,
                :country, :city, :address, :status, :type, :basic_salary, NOW(), NOW()
            )";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':employee_id', $employeeId);
            $stmt->bindParam(':name', $input['name']);
            $stmt->bindParam(':email', $input['email']);
            $stmt->bindParam(':phone', $input['phone']);
            $stmt->bindParam(':birthdate', $input['birthdate']);
            $stmt->bindParam(':department', $input['department']);
            $stmt->bindParam(':position', $input['position']);
            $stmt->bindParam(':join_date', $input['join_date']);
            $stmt->bindParam(':country', $input['country']);
            $stmt->bindParam(':city', $input['city']);
            $stmt->bindParam(':address', $input['address']);
            $status = $input['status'] ?? 'Active';
            $type = $input['type'] ?? 'Full-time';
            $basicSalary = $input['basic_salary'] ?? 0;
            
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':basic_salary', $basicSalary);
            $stmt->execute();
            
            $newEmployeeId = $conn->lastInsertId();
            
            // Get created employee for history
            $stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
            $stmt->execute([$newEmployeeId]);
            $newEmployee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Log history
            if (file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('employees', $newEmployeeId, 'create', 'hr', null, $newEmployee);
            }
            
            ob_clean();
            sendResponse([
                'success' => true,
                'message' => 'Employee added successfully',
                'data' => ['id' => $newEmployeeId, 'employee_id' => $employeeId]
            ]);
            break;
            
        case 'update':
        case 'edit':
            enforceApiPermission('employees', 'update');
            
            if (!$id) {
                sendResponse(['success' => false, 'message' => 'Employee ID is required'], 400);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                sendResponse(['success' => false, 'message' => 'Invalid input data'], 400);
            }
            
            // Get old data for history
            $stmt = $conn->prepare("SELECT * FROM employees WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $oldEmployee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldEmployee) {
                sendResponse(['success' => false, 'message' => 'Employee not found'], 404);
            }
            
            $updateFields = [];
            $params = [':id' => $id];
            
            $allowedFields = ['name', 'email', 'phone', 'birthdate', 'department', 'position', 'country', 'city', 'address', 'status', 'type', 'basic_salary'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $input[$field];
                }
            }
            
            if (empty($updateFields)) {
                sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
            }
            
            $updateFields[] = "updated_at = NOW()";
            
            $query = "UPDATE employees SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            
            // Get updated employee for history
            $stmt = $conn->prepare("SELECT * FROM employees WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $updatedEmployee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Log history
            if (file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('employees', $id, 'update', 'hr', $oldEmployee, $updatedEmployee);
            }
            
            ob_clean();
            sendResponse(['success' => true, 'message' => 'Employee updated successfully']);
            break;
            
        case 'delete':
            enforceApiPermission('employees', 'delete');
            
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
            
            // Log history
            if (file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('employees', $id, 'delete', 'hr', $deletedEmployee, null);
            }
            
            ob_clean();
            sendResponse(['success' => true, 'message' => 'Employee deleted successfully']);
            break;
            
        default:
            ob_clean();
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
            break;
    }
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("HR Employee API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return proper JSON error response
    ob_clean();
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
