<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/salaries.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/salaries.php`.
 */
if (isset($_GET['control']) && (string)$_GET['control'] === '1') {
    session_name('ratib_control');
}
require_once __DIR__ . '/hr-api-bootstrap.inc.php';
// Disable error display to prevent HTML output in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set headers to prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Load response.php - try root Utils first, then api/utils
$responsePath = __DIR__ . '/../../Utils/response.php';
if (!file_exists($responsePath)) {
    $responsePath = __DIR__ . '/../../api/utils/response.php';
}
if (!file_exists($responsePath)) {
    error_log('ERROR: response.php not found. Tried: ' . __DIR__ . '/../../Utils/response.php and ' . __DIR__ . '/../../api/utils/response.php');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server configuration error: response.php not found']);
    exit;
}
require_once $responsePath;

require_once __DIR__ . '/hr-connection.php';

try {
    $conn = hr_api_get_connection();

    $action = $_GET['action'] ?? 'list';
    $id = $_GET['id'] ?? null;

    switch ($action) {
        case 'list':
            // Check if table exists (PDO MySQL: do not rely on rowCount() for SHOW TABLES)
            $tableCheck = $conn->query("SHOW TABLES LIKE 'salaries'");
            $salariesTableExists = ($tableCheck !== false && $tableCheck->fetch(PDO::FETCH_NUM) !== false);
            if (!$salariesTableExists) {
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
            $month = isset($_GET['month']) ? trim($_GET['month']) : '';
            $status = isset($_GET['status']) ? trim($_GET['status']) : '';
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            
            // Validate month format if provided
            if (!empty($month) && !preg_match('/^\d{4}-\d{2}$/', $month)) {
                sendResponse(['success' => false, 'message' => 'Invalid month format. Use YYYY-MM'], 400);
            }
            
            // Validate search length
            if (strlen($search) > 255) {
                sendResponse(['success' => false, 'message' => 'Search term too long'], 400);
            }
            
            $offset = ($page - 1) * $limit;
            
            $whereConditions = [];
            $params = [];
            
            if (!empty($month)) {
                $whereConditions[] = "salary_month = :month";
                $params[':month'] = $month;
            }
            
            if (!empty($status)) {
                $whereConditions[] = "status = :status";
                $params[':status'] = $status;
            }
            
            if (!empty($search)) {
                $searchPattern = '%' . $search . '%';
                $whereConditions[] = "(record_id LIKE :search1 OR employee_name LIKE :search2 OR salary_month LIKE :search3)";
                $params[':search1'] = $params[':search2'] = $params[':search3'] = $searchPattern;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM salaries $whereClause";
            $countStmt = $conn->prepare($countQuery);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get salaries
            $lim = (int) $limit;
            $off = (int) $offset;
            $query = "SELECT * FROM salaries $whereClause ORDER BY id DESC LIMIT {$lim} OFFSET {$off}";
            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $salaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendResponse([
                'success' => true,
                'data' => $salaries,
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
                sendResponse(['success' => false, 'message' => 'Invalid input data'], 400);
            }
            
            // Validate required fields
            $requiredFields = ['employee_id', 'salary_month', 'working_days', 'basic_salary'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    sendResponse(['success' => false, 'message' => "Field '$field' is required"], 400);
                }
            }
            
            // Get employee name
            $empQuery = "SELECT name FROM employees WHERE id = :id";
            $empStmt = $conn->prepare($empQuery);
            $empStmt->bindParam(':id', $input['employee_id'], PDO::PARAM_INT);
            $empStmt->execute();
            $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
            
            
            if (!$employee) {
                sendResponse(['success' => false, 'message' => 'Employee not found'], 404);
            }
            
            // Generate record ID with PA0001 format
            require_once __DIR__ . '/../core/formatted-id-helper.php';
            try {
                $recordId = generateHRSalaryId($conn);
            } catch (Exception $e) {
                error_log("Error generating HR salary ID: " . $e->getMessage());
                // Fallback to simple ID generation (PA prefix for Payroll)
                $recordId = 'PA' . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
            }
            
            // Validate numeric inputs
            $basicSalary = floatval($input['basic_salary']);
            if ($basicSalary < 0) {
                sendResponse(['success' => false, 'message' => 'Basic salary cannot be negative'], 400);
            }
            if ($basicSalary > 1000000) {
                sendResponse(['success' => false, 'message' => 'Basic salary exceeds maximum limit'], 400);
            }
            
            $workingDays = intval($input['working_days']);
            if ($workingDays < 1 || $workingDays > 31) {
                sendResponse(['success' => false, 'message' => 'Working days must be between 1 and 31'], 400);
            }
            
            // Sanitize numeric inputs
            $housingAllowance = empty($input['housing_allowance']) ? 0 : floatval($input['housing_allowance']);
            if ($housingAllowance < 0) {
                sendResponse(['success' => false, 'message' => 'Housing allowance cannot be negative'], 400);
            }
            
            $transportation = empty($input['transportation']) ? 0 : floatval($input['transportation']);
            if ($transportation < 0) {
                sendResponse(['success' => false, 'message' => 'Transportation cannot be negative'], 400);
            }
            
            $overtimeHours = empty($input['overtime_hours']) ? 0 : floatval($input['overtime_hours']);
            if ($overtimeHours < 0) {
                sendResponse(['success' => false, 'message' => 'Overtime hours cannot be negative'], 400);
            }
            if ($overtimeHours > 200) {
                sendResponse(['success' => false, 'message' => 'Overtime hours exceeds maximum (200)'], 400);
            }
            
            $overtimeRate = empty($input['overtime_rate']) ? 0 : floatval($input['overtime_rate']);
            if ($overtimeRate < 0) {
                sendResponse(['success' => false, 'message' => 'Overtime rate cannot be negative'], 400);
            }
            
            $bonus = empty($input['bonus']) ? 0 : floatval($input['bonus']);
            if ($bonus < 0) {
                sendResponse(['success' => false, 'message' => 'Bonus cannot be negative'], 400);
            }
            
            $insurance = empty($input['insurance']) ? 0 : floatval($input['insurance']);
            if ($insurance < 0) {
                sendResponse(['success' => false, 'message' => 'Insurance cannot be negative'], 400);
            }
            
            $taxPercentage = empty($input['tax_percentage']) ? 0 : floatval($input['tax_percentage']);
            if ($taxPercentage < 0 || $taxPercentage > 100) {
                sendResponse(['success' => false, 'message' => 'Tax percentage must be between 0 and 100'], 400);
            }
            
            $otherDeductions = empty($input['other_deductions']) ? 0 : floatval($input['other_deductions']);
            if ($otherDeductions < 0) {
                sendResponse(['success' => false, 'message' => 'Other deductions cannot be negative'], 400);
            }
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}$/', $input['salary_month'])) {
                sendResponse(['success' => false, 'message' => 'Invalid salary month format. Use YYYY-MM'], 400);
            }
            
            $status = $input['status'] ?? 'pending';
            
            // Calculate overtime pay
            $overtimePay = $overtimeHours * $overtimeRate;
            
            // Calculate totals
            $totalEarnings = $basicSalary + $housingAllowance + $transportation + $bonus + $overtimePay;
            
            // Calculate tax amount (tax percentage of total earnings)
            $taxAmount = ($taxPercentage / 100) * $totalEarnings;
            
            // Calculate total deductions (insurance + tax + other deductions)
            $totalDeductions = $insurance + $taxAmount + $otherDeductions;
            
            // Calculate net salary
            $netSalary = $totalEarnings - $totalDeductions;
            
            // Validate net salary is not negative (can be 0)
            if ($netSalary < 0) {
                sendResponse(['success' => false, 'message' => 'Net salary cannot be negative. Check deductions.'], 400);
            }
            
            // Start transaction
            $conn->beginTransaction();
            
            try {
                // Get currency from input or default to SAR
            $currency = !empty($input['currency']) ? strtoupper(trim($input['currency'])) : 'SAR';
            
            // Check if currency column exists
            $columnsQuery = "SHOW COLUMNS FROM salaries LIKE 'currency'";
            $columnsStmt = $conn->query($columnsQuery);
            $hasCurrencyColumn = ($columnsStmt !== false && $columnsStmt->fetch(PDO::FETCH_NUM) !== false);
            
            // Build INSERT query dynamically based on whether currency column exists
            if ($hasCurrencyColumn) {
                $query = "INSERT INTO salaries (
                    record_id, employee_id, employee_name, currency, salary_month, working_days, basic_salary,
                    housing_allowance, transportation, overtime_hours, overtime_rate, bonus,
                    insurance, tax_percentage, other_deductions, total_earnings, total_deductions,
                    net_salary, status, created_at, updated_at
                ) VALUES (
                    :record_id, :employee_id, :employee_name, :currency, :salary_month, :working_days, :basic_salary,
                    :housing_allowance, :transportation, :overtime_hours, :overtime_rate, :bonus,
                    :insurance, :tax_percentage, :other_deductions, :total_earnings, :total_deductions,
                    :net_salary, :status, NOW(), NOW()
                )";
            } else {
                $query = "INSERT INTO salaries (
                    record_id, employee_id, employee_name, salary_month, working_days, basic_salary,
                    housing_allowance, transportation, overtime_hours, overtime_rate, bonus,
                    insurance, tax_percentage, other_deductions, total_earnings, total_deductions,
                    net_salary, status, created_at, updated_at
                ) VALUES (
                    :record_id, :employee_id, :employee_name, :salary_month, :working_days, :basic_salary,
                    :housing_allowance, :transportation, :overtime_hours, :overtime_rate, :bonus,
                    :insurance, :tax_percentage, :other_deductions, :total_earnings, :total_deductions,
                    :net_salary, :status, NOW(), NOW()
                )";
            }
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':record_id', $recordId);
            $stmt->bindParam(':employee_id', $input['employee_id'], PDO::PARAM_INT);
            $stmt->bindParam(':employee_name', $employee['name']);
            if ($hasCurrencyColumn) {
                $stmt->bindParam(':currency', $currency);
            }
            $stmt->bindParam(':salary_month', $input['salary_month']);
            $stmt->bindParam(':working_days', $input['working_days'], PDO::PARAM_INT);
            $stmt->bindParam(':basic_salary', $basicSalary);
            $stmt->bindParam(':housing_allowance', $housingAllowance);
            $stmt->bindParam(':transportation', $transportation);
            $stmt->bindParam(':overtime_hours', $overtimeHours);
            $stmt->bindParam(':overtime_rate', $overtimeRate);
            $stmt->bindParam(':bonus', $bonus);
            $stmt->bindParam(':insurance', $insurance);
            $stmt->bindParam(':tax_percentage', $taxPercentage);
            $stmt->bindParam(':other_deductions', $otherDeductions);
            
            $stmt->bindParam(':total_earnings', $totalEarnings);
            $stmt->bindParam(':total_deductions', $totalDeductions);
            $stmt->bindParam(':net_salary', $netSalary);
            $stmt->bindParam(':status', $status);
            
            $result = $stmt->execute();
            
            if ($result) {
                $insertId = $conn->lastInsertId();
                
                // Get created salary for history
                $stmt = $conn->prepare("SELECT * FROM salaries WHERE id = ?");
                $stmt->execute([$insertId]);
                $newSalary = $stmt->fetch(PDO::FETCH_ASSOC);
                
                    // Log history
                    if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                        require_once __DIR__ . '/../core/global-history-helper.php';
                        @logGlobalHistory('salaries', $insertId, 'create', 'hr', null, $newSalary);
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    
                    sendResponse([
                        'success' => true,
                        'message' => 'Salary record created successfully',
                        'data' => ['id' => $insertId]
                    ]);
                } else {
                    $conn->rollBack();
                    sendResponse(['success' => false, 'message' => 'Failed to create salary record'], 500);
                }
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                error_log("HR Salary add transaction error: " . $e->getMessage());
                sendResponse(['success' => false, 'message' => 'Failed to create salary record: ' . $e->getMessage()], 500);
            }
            break;
            
        case 'get':
            if (!$id) {
                sendResponse(['success' => false, 'message' => 'Salary ID is required'], 400);
            }
            
            $query = "SELECT * FROM salaries WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $salary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($salary) {
                sendResponse([
                    'success' => true,
                    'data' => $salary
                ]);
            } else {
                sendResponse(['success' => false, 'message' => 'Salary record not found'], 404);
            }
            break;
            
        case 'update':
            // checkApiPermission('hr_edit');
            
            if (!$id) {
                sendResponse(['success' => false, 'message' => 'Salary ID is required'], 400);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                sendResponse(['success' => false, 'message' => 'Invalid input data'], 400);
            }
            
            // Get old data for history
            $stmt = $conn->prepare("SELECT * FROM salaries WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $oldSalary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldSalary) {
                sendResponse(['success' => false, 'message' => 'Salary record not found'], 404);
            }
            
            // Check if currency column exists
            $columnsQuery = "SHOW COLUMNS FROM salaries LIKE 'currency'";
            $columnsStmt = $conn->query($columnsQuery);
            $hasCurrencyColumn = ($columnsStmt !== false && $columnsStmt->fetch(PDO::FETCH_NUM) !== false);
            
            // Get current values for calculation (merge with input to get all current values)
            $currentSalary = $oldSalary;
            $basicSalary = isset($input['basic_salary']) ? floatval($input['basic_salary']) : floatval($currentSalary['basic_salary']);
            $housingAllowance = isset($input['housing_allowance']) ? (empty($input['housing_allowance']) ? 0 : floatval($input['housing_allowance'])) : floatval($currentSalary['housing_allowance'] ?? 0);
            $transportation = isset($input['transportation']) ? (empty($input['transportation']) ? 0 : floatval($input['transportation'])) : floatval($currentSalary['transportation'] ?? 0);
            $overtimeHours = isset($input['overtime_hours']) ? (empty($input['overtime_hours']) ? 0 : floatval($input['overtime_hours'])) : floatval($currentSalary['overtime_hours'] ?? 0);
            $overtimeRate = isset($input['overtime_rate']) ? (empty($input['overtime_rate']) ? 0 : floatval($input['overtime_rate'])) : floatval($currentSalary['overtime_rate'] ?? 0);
            $bonus = isset($input['bonus']) ? (empty($input['bonus']) ? 0 : floatval($input['bonus'])) : floatval($currentSalary['bonus'] ?? 0);
            $insurance = isset($input['insurance']) ? (empty($input['insurance']) ? 0 : floatval($input['insurance'])) : floatval($currentSalary['insurance'] ?? 0);
            $taxPercentage = isset($input['tax_percentage']) ? (empty($input['tax_percentage']) ? 0 : floatval($input['tax_percentage'])) : floatval($currentSalary['tax_percentage'] ?? 0);
            $otherDeductions = isset($input['other_deductions']) ? (empty($input['other_deductions']) ? 0 : floatval($input['other_deductions'])) : floatval($currentSalary['other_deductions'] ?? 0);
            
            // Calculate overtime pay
            $overtimePay = $overtimeHours * $overtimeRate;
            
            // Recalculate totals
            $totalEarnings = $basicSalary + $housingAllowance + $transportation + $bonus + $overtimePay;
            
            // Calculate tax amount (tax percentage of total earnings)
            $taxAmount = ($taxPercentage / 100) * $totalEarnings;
            
            // Calculate total deductions (insurance + tax + other deductions)
            $totalDeductions = $insurance + $taxAmount + $otherDeductions;
            
            // Calculate net salary
            $netSalary = $totalEarnings - $totalDeductions;
            
            $updateFields = [];
            $params = [':id' => $id];
            
            $allowedFields = ['working_days', 'basic_salary', 'housing_allowance', 'transportation', 'overtime_hours', 'overtime_rate', 'bonus', 'insurance', 'tax_percentage', 'other_deductions', 'status'];
            if ($hasCurrencyColumn) {
                $allowedFields[] = 'currency';
            }
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "$field = :$field";
                    if ($field === 'currency') {
                        $params[":$field"] = strtoupper(trim($input[$field]));
                    } else {
                        $params[":$field"] = $input[$field];
                    }
                }
            }
            
            // Always recalculate and update totals
            $updateFields[] = "total_earnings = :total_earnings";
            $updateFields[] = "total_deductions = :total_deductions";
            $updateFields[] = "net_salary = :net_salary";
            $params[':total_earnings'] = $totalEarnings;
            $params[':total_deductions'] = $totalDeductions;
            $params[':net_salary'] = $netSalary;
            
            if (empty($updateFields)) {
                sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
            }
            
            $updateFields[] = "updated_at = NOW()";
            
            $query = "UPDATE salaries SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            
            // Get updated salary for history
            $stmt = $conn->prepare("SELECT * FROM salaries WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $updatedSalary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Log history
            if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('salaries', $id, 'update', 'hr', $oldSalary, $updatedSalary);
            }
            
            sendResponse(['success' => true, 'message' => 'Salary updated successfully']);
            break;
            
        case 'delete':
            // checkApiPermission('hr_delete');
            
            if (!$id) {
                sendResponse(['success' => false, 'message' => 'Salary ID is required'], 400);
            }
            
            // Get deleted data for history (before deletion)
            $stmt = $conn->prepare("SELECT * FROM salaries WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $deletedSalary = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$deletedSalary) {
                sendResponse(['success' => false, 'message' => 'Salary record not found'], 404);
            }
            
            $query = "DELETE FROM salaries WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Log history
            if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('salaries', $id, 'delete', 'hr', $deletedSalary, null);
            }
            
            sendResponse(['success' => true, 'message' => 'Salary record deleted successfully']);
            break;
            
        case 'bulk-update':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['ids']) || !is_array($input['ids']) || empty($input['ids'])) {
                sendResponse(['success' => false, 'message' => 'Invalid IDs provided'], 400);
            }
            
            if (!isset($input['status'])) {
                sendResponse(['success' => false, 'message' => 'Status is required'], 400);
            }
            
            // Validate status value
            $allowedStatuses = ['pending', 'approved', 'paid', 'cancelled'];
            if (!in_array($input['status'], $allowedStatuses)) {
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
                sendResponse(['success' => false, 'message' => 'No valid IDs provided'], 400);
            }
            
            // Limit bulk operations to prevent abuse
            if (count($validIds) > 100) {
                sendResponse(['success' => false, 'message' => 'Too many IDs (max 100)'], 400);
            }
            
            $placeholders = implode(',', array_fill(0, count($validIds), '?'));
            $query = "UPDATE salaries SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            
            $stmt = $conn->prepare($query);
            $stmt->execute(array_merge([$input['status']], $validIds));
            
            sendResponse([
                'success' => true,
                'message' => count($validIds) . ' salary record(s) updated successfully'
            ]);
            break;
            
        case 'bulk-delete':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['ids']) || !is_array($input['ids']) || empty($input['ids'])) {
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
                sendResponse(['success' => false, 'message' => 'No valid IDs provided'], 400);
            }
            
            // Limit bulk operations to prevent abuse
            if (count($validIds) > 100) {
                sendResponse(['success' => false, 'message' => 'Too many IDs (max 100)'], 400);
            }
            
            $placeholders = implode(',', array_fill(0, count($validIds), '?'));
            $query = "DELETE FROM salaries WHERE id IN ($placeholders)";
            
            $stmt = $conn->prepare($query);
            $stmt->execute($validIds);
            
            sendResponse([
                'success' => true,
                'message' => count($validIds) . ' salary record(s) deleted successfully'
            ]);
            break;
            
        default:
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("HR Salaries API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return proper JSON error response
    sendResponse(['success' => false, 'message' => 'An error occurred while processing your request'], 500);
} catch (Error $e) {
    // Handle PHP fatal errors
    error_log("HR Salaries API Fatal Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    sendResponse(['success' => false, 'message' => 'A system error occurred'], 500);
}
?>
