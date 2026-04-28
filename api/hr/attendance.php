<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/attendance.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/attendance.php`.
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

function toWesternNumerals($str) {
    if ($str === null || $str === '') return $str;
    $str = (string) $str;
    $arabic = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    for ($i = 0; $i <= 9; $i++) {
        $str = str_replace($arabic[$i], (string)$i, $str);
        $str = str_replace($persian[$i], (string)$i, $str);
    }
    return $str;
}

try {
    $conn = hr_api_get_connection();

    $action = $_GET['action'] ?? 'list';
    $id = $_GET['id'] ?? null;

    switch ($action) {
        case 'list':
            // Check if table exists (PDO MySQL: do not rely on rowCount() for SHOW TABLES)
            $tableCheck = $conn->query("SHOW TABLES LIKE 'attendance'");
            $attendanceTableExists = ($tableCheck !== false && $tableCheck->fetch(PDO::FETCH_NUM) !== false);
            if (!$attendanceTableExists) {
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
            $date = isset($_GET['date']) ? trim($_GET['date']) : '';
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $status = isset($_GET['status']) ? trim($_GET['status']) : '';
            
            // Validate date format if provided
            if (!empty($date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD'], 400);
            }
            
            // Validate search length
            if (strlen($search) > 255) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Search term too long'], 400);
            }
            
            $offset = ($page - 1) * $limit;
            
            $whereConditions = [];
            $params = [];
            
            if (!empty($date)) {
                $whereConditions[] = "a.date = :date";
                $params[':date'] = $date;
            }
            if (!empty($status)) {
                $whereConditions[] = "a.status = :status";
                $params[':status'] = $status;
            }
            if (!empty($search)) {
                $searchPattern = '%' . $search . '%';
                $whereConditions[] = "(a.employee_name LIKE :search1 OR a.record_id LIKE :search2 OR COALESCE(e.name, '') LIKE :search3)";
                $params[':search1'] = $params[':search2'] = $params[':search3'] = $searchPattern;
            }
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            $lim = (int) $limit;
            $off = (int) $offset;
            $query = "SELECT a.*, COALESCE(e.name, a.employee_name, 'N/A') as employee_name, e.employee_id 
                     FROM attendance a 
                     LEFT JOIN employees e ON a.employee_id = e.id 
                     $whereClause
                     ORDER BY a.id DESC 
                     LIMIT {$lim} OFFSET {$off}";
            
            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countJoin = (!empty($search)) ? 'LEFT JOIN employees e ON a.employee_id = e.id' : '';
            $countQuery = "SELECT COUNT(*) as total FROM attendance a $countJoin $whereClause";
            $countStmt = $conn->prepare($countQuery);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            ob_clean();
            sendResponse([
                'success' => true,
                'data' => $attendance,
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
            $requiredFields = ['employee_id', 'date', 'status'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    ob_clean();
                    sendResponse(['success' => false, 'message' => "Field '$field' is required"], 400);
                }
            }
            
            // Validate date format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['date'])) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD'], 400);
            }
            
            // Validate time format if provided
            if (!empty($input['check_in_time']) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $input['check_in_time'])) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Invalid check-in time format. Use HH:MM or HH:MM:SS'], 400);
            }
            if (!empty($input['check_out_time']) && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $input['check_out_time'])) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Invalid check-out time format. Use HH:MM or HH:MM:SS'], 400);
            }
            
            // Validate time logic
            if (!empty($input['check_in_time']) && !empty($input['check_out_time'])) {
                $checkIn = strtotime($input['date'] . ' ' . $input['check_in_time']);
                $checkOut = strtotime($input['date'] . ' ' . $input['check_out_time']);
                if ($checkOut < $checkIn) {
                    ob_clean();
                    sendResponse(['success' => false, 'message' => 'Check-out time cannot be before check-in time'], 400);
                }
            }
            
            // Validate status
            $validStatuses = ['present', 'absent', 'late', 'leave', 'half_day'];
            if (!in_array($input['status'], $validStatuses)) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Invalid status. Must be one of: ' . implode(', ', $validStatuses)], 400);
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
            
            // Generate record ID with AT0001 format
            require_once __DIR__ . '/../core/formatted-id-helper.php';
            try {
                $recordId = generateHRAttendanceId($conn);
            } catch (Exception $e) {
                error_log("Error generating HR attendance ID: " . $e->getMessage());
                // Fallback to simple ID generation (AT prefix for Attendance)
                $recordId = 'AT' . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
            }
            
            $query = "INSERT INTO attendance (
                record_id, employee_id, employee_name, date, check_in_time, check_out_time, 
                status, notes, created_at, updated_at
            ) VALUES (
                :record_id, :employee_id, :employee_name, :date, :check_in_time, :check_out_time,
                :status, :notes, NOW(), NOW()
            )";
            
            $stmt = $conn->prepare($query);
            $checkIn = isset($input['check_in_time']) ? toWesternNumerals(preg_replace('/^-/', '', $input['check_in_time'])) : '';
            $checkOut = isset($input['check_out_time']) ? toWesternNumerals(preg_replace('/^-/', '', $input['check_out_time'])) : '';
            $dateVal = isset($input['date']) ? toWesternNumerals($input['date']) : '';
            $stmt->bindParam(':record_id', $recordId);
            $stmt->bindParam(':employee_id', $input['employee_id'], PDO::PARAM_INT);
            $stmt->bindParam(':employee_name', $employee['name']);
            $stmt->bindValue(':date', $dateVal);
            $stmt->bindValue(':check_in_time', $checkIn);
            $stmt->bindValue(':check_out_time', $checkOut);
            $stmt->bindParam(':status', $input['status']);
            $stmt->bindParam(':notes', $input['notes']);
            
            // Start transaction for atomic operation
            $conn->beginTransaction();
            
            try {
                $stmt->execute();
                
                $newAttendanceId = $conn->lastInsertId();
                
                // Get created attendance for history
                $stmt = $conn->prepare("SELECT * FROM attendance WHERE id = ?");
                $stmt->execute([$newAttendanceId]);
                $newAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Log history
                if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                    require_once __DIR__ . '/../core/global-history-helper.php';
                    @logGlobalHistory('attendance', $newAttendanceId, 'create', 'hr', null, $newAttendance);
                }
                
                // Commit transaction
                $conn->commit();
                
                ob_clean();
                sendResponse([
                    'success' => true,
                    'message' => 'Attendance recorded successfully',
                    'data' => ['id' => $newAttendanceId]
                ]);
                
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                error_log("HR Attendance add transaction error: " . $e->getMessage());
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Failed to record attendance: ' . $e->getMessage()], 500);
            }
            break;
            
        case 'get':
            if (!$id) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Attendance ID is required'], 400);
            }
            
            $query = "SELECT a.*, e.name as employee_name, e.employee_id as employee_formatted_id 
                     FROM attendance a 
                     LEFT JOIN employees e ON a.employee_id = e.id 
                     WHERE a.id = :id";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($attendance) {
                ob_clean();
                sendResponse([
                    'success' => true,
                    'data' => $attendance
                ]);
            } else {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Attendance record not found'], 404);
            }
            break;
            
        case 'update':
            // checkApiPermission('hr_edit');
            
            if (!$id) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Attendance ID is required'], 400);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Invalid input data'], 400);
            }
            
            // Get old data for history
            $stmt = $conn->prepare("SELECT * FROM attendance WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $oldAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldAttendance) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Attendance record not found'], 404);
            }
            
            $updateFields = [];
            $params = [':id' => $id];
            
            $allowedFields = ['employee_id', 'check_in_time', 'check_out_time', 'status', 'notes'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateFields[] = "$field = :$field";
                    $val = $input[$field];
                    if ($field === 'check_in_time' || $field === 'check_out_time') {
                        $val = toWesternNumerals(preg_replace('/^-/', '', (string)$val));
                    }
                    $params[":$field"] = $val;
                }
            }
            
            if (empty($updateFields)) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
            }
            
            $updateFields[] = "updated_at = NOW()";
            
            $query = "UPDATE attendance SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            
            // Get updated attendance for history
            $stmt = $conn->prepare("SELECT * FROM attendance WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $updatedAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Log history
            if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('attendance', $id, 'update', 'hr', $oldAttendance, $updatedAttendance);
            }
            
            ob_clean();
            sendResponse(['success' => true, 'message' => 'Attendance updated successfully']);
            break;
            
        case 'delete':
            // checkApiPermission('hr_delete');
            
            if (!$id) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Attendance ID is required'], 400);
            }
            
            // Get deleted data for history (before deletion)
            $stmt = $conn->prepare("SELECT * FROM attendance WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $deletedAttendance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$deletedAttendance) {
                ob_clean();
                sendResponse(['success' => false, 'message' => 'Attendance record not found'], 404);
            }
            
            $query = "DELETE FROM attendance WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Log history
            if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('attendance', $id, 'delete', 'hr', $deletedAttendance, null);
            }
            
            ob_clean();
            sendResponse(['success' => true, 'message' => 'Attendance record deleted successfully']);
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
            $allowedStatuses = ['present', 'absent', 'late', 'leave', 'half_day'];
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
            $query = "UPDATE attendance SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            
            $stmt = $conn->prepare($query);
            $stmt->execute(array_merge([$input['status']], $validIds));
            
            ob_clean();
            sendResponse([
                'success' => true,
                'message' => count($validIds) . ' attendance record(s) updated successfully'
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
            $query = "DELETE FROM attendance WHERE id IN ($placeholders)";
            
            $stmt = $conn->prepare($query);
            $stmt->execute($validIds);
            
            ob_clean();
            sendResponse([
                'success' => true,
                'message' => count($validIds) . ' attendance record(s) deleted successfully'
            ]);
            break;
            
        default:
            ob_clean();
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    // Log the error for debugging
    error_log("HR Attendance API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return proper JSON error response
    ob_clean();
    sendResponse(['success' => false, 'message' => 'An error occurred while processing your request'], 500);
} catch (Error $e) {
    // Handle PHP fatal errors
    error_log("HR Attendance API Fatal Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    ob_clean();
    sendResponse(['success' => false, 'message' => 'A system error occurred'], 500);
} catch (Throwable $e) {
    error_log("HR Attendance API Throwable Error: " . $e->getMessage());
    ob_clean();
    sendResponse(['success' => false, 'message' => 'A system error occurred'], 500);
}
?>
