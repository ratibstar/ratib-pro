<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/cars.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/cars.php`.
 */
if (isset($_GET['control']) && (string)$_GET['control'] === '1') {
    session_name('ratib_control');
}
require_once __DIR__ . '/hr-api-bootstrap.inc.php';
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
            $tableCheck = $conn->query("SHOW TABLES LIKE 'cars'");
            $carsTableExists = ($tableCheck !== false && $tableCheck->fetch(PDO::FETCH_NUM) !== false);
            if (!$carsTableExists) {
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
                sendResponse(['success' => false, 'message' => 'Search term too long'], 400);
            }
            
            $offset = ($page - 1) * $limit;
            
            $whereConditions = [];
            $params = [];
            
            if (!empty($status)) {
                $whereConditions[] = "c.status = :status";
                $params[':status'] = $status;
            }
            
            if (!empty($search)) {
                $searchPattern = '%' . $search . '%';
                $whereConditions[] = "(c.vehicle_number LIKE :search1 OR c.vehicle_model LIKE :search2 OR c.driver_name LIKE :search3 OR c.record_id LIKE :search4 OR COALESCE(e.name, '') LIKE :search5)";
                $params[':search1'] = $params[':search2'] = $params[':search3'] = $params[':search4'] = $params[':search5'] = $searchPattern;
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            $joinClause = (!empty($search)) ? 'LEFT JOIN employees e ON c.driver_id = e.id' : '';
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM cars c $joinClause $whereClause";
            $countStmt = $conn->prepare($countQuery);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get cars with driver names (JOIN always needed for driver_name in SELECT)
            $lim = (int) $limit;
            $off = (int) $offset;
            $query = "SELECT c.*, COALESCE(e.name, c.driver_name, 'N/A') as driver_name 
                      FROM cars c 
                      LEFT JOIN employees e ON c.driver_id = e.id 
                      $whereClause 
                      ORDER BY c.id DESC LIMIT {$lim} OFFSET {$off}";
            $stmt = $conn->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendResponse([
                'success' => true,
                'data' => $cars,
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
            $requiredFields = ['vehicle_number', 'vehicle_model'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    sendResponse(['success' => false, 'message' => "Field '$field' is required"], 400);
                }
            }
            
            // Validate string lengths
            if (strlen($input['vehicle_number']) > 50) {
                sendResponse(['success' => false, 'message' => 'Vehicle number too long (max 50 characters)'], 400);
            }
            if (strlen($input['vehicle_model']) > 100) {
                sendResponse(['success' => false, 'message' => 'Vehicle model too long (max 100 characters)'], 400);
            }
            if (isset($input['notes']) && strlen($input['notes']) > 1000) {
                sendResponse(['success' => false, 'message' => 'Notes too long (max 1000 characters)'], 400);
            }
            
            // Validate date formats if provided
            if (!empty($input['registration_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['registration_date'])) {
                sendResponse(['success' => false, 'message' => 'Invalid registration date format. Use YYYY-MM-DD'], 400);
            }
            if (!empty($input['insurance_expiry']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['insurance_expiry'])) {
                sendResponse(['success' => false, 'message' => 'Invalid insurance expiry format. Use YYYY-MM-DD'], 400);
            }
            if (!empty($input['maintenance_due_date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['maintenance_due_date'])) {
                sendResponse(['success' => false, 'message' => 'Invalid maintenance due date format. Use YYYY-MM-DD'], 400);
            }
            
            // Check if vehicle number already exists
            $checkQuery = "SELECT COUNT(*) as count FROM cars WHERE vehicle_number = :vehicle_number";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bindParam(':vehicle_number', $input['vehicle_number']);
            $checkStmt->execute();
            if ($checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                sendResponse(['success' => false, 'message' => 'Vehicle number already exists'], 400);
            }
            
            // Generate record ID with VE0001 format
            require_once __DIR__ . '/../core/formatted-id-helper.php';
            try {
                $recordId = generateHRVehicleId($conn);
            } catch (Exception $e) {
                error_log("Error generating HR vehicle ID: " . $e->getMessage());
                // Fallback to simple ID generation (VE prefix for Vehicles)
                $recordId = 'VE' . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
            }
            
            $query = "INSERT INTO cars (
                record_id, vehicle_number, vehicle_model, driver_id, driver_name, status,
                registration_date, insurance_expiry, maintenance_due_date, notes,
                created_at, updated_at
            ) VALUES (
                :record_id, :vehicle_number, :vehicle_model, :driver_id, :driver_name, :status,
                :registration_date, :insurance_expiry, :maintenance_due_date, :notes,
                NOW(), NOW()
            )";
            
            // Get driver name if driver_id is provided
            $driverName = null;
            if (!empty($input['driver_id'])) {
                $driverQuery = "SELECT name FROM employees WHERE id = :driver_id";
                $driverStmt = $conn->prepare($driverQuery);
                $driverStmt->bindParam(':driver_id', $input['driver_id'], PDO::PARAM_INT);
                $driverStmt->execute();
                $driver = $driverStmt->fetch(PDO::FETCH_ASSOC);
                $driverName = $driver ? $driver['name'] : null;
            }
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':record_id', $recordId);
            $vehicleNumber = $input['vehicle_number'];
            $vehicleModel = $input['vehicle_model'];
            $driverId = $input['driver_id'] ?? null;
            $status = $input['status'] ?? 'available';
            $registrationDate = $input['registration_date'] ?? null;
            $insuranceExpiry = $input['insurance_expiry'] ?? null;
            $maintenanceDueDate = $input['maintenance_due_date'] ?? null;
            $notes = $input['notes'] ?? null;
            
            $stmt->bindParam(':vehicle_number', $vehicleNumber);
            $stmt->bindParam(':vehicle_model', $vehicleModel);
            $stmt->bindParam(':driver_id', $driverId);
            $stmt->bindParam(':driver_name', $driverName);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':registration_date', $registrationDate);
            $stmt->bindParam(':insurance_expiry', $insuranceExpiry);
            $stmt->bindParam(':maintenance_due_date', $maintenanceDueDate);
            $stmt->bindParam(':notes', $notes);
            $stmt->execute();
            
            $newCarId = $conn->lastInsertId();
            
            // Get created car for history
            $stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
            $stmt->execute([$newCarId]);
            $newCar = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Log history
            if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('cars', $newCarId, 'create', 'hr', null, $newCar);
            }
            
            sendResponse([
                'success' => true,
                'message' => 'Vehicle added successfully',
                'data' => ['id' => $newCarId]
            ]);
            break;
            
        case 'update':
            // checkApiPermission('hr_edit');
            
            if (!$id) {
                sendResponse(['success' => false, 'message' => 'Vehicle ID is required'], 400);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                sendResponse(['success' => false, 'message' => 'Invalid input data'], 400);
            }
            
            // Get old data for history
            $stmt = $conn->prepare("SELECT * FROM cars WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $oldCar = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldCar) {
                sendResponse(['success' => false, 'message' => 'Vehicle not found'], 404);
            }
            
            $updateFields = [];
            $params = [':id' => $id];
            
            $allowedFields = ['vehicle_number', 'vehicle_model', 'driver_id', 'driver_name', 'status', 'registration_date', 'registration_expiry', 'insurance_expiry', 'maintenance_due_date', 'maintenance_expiry', 'notes'];
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
            
            $query = "UPDATE cars SET " . implode(', ', $updateFields) . " WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            
            // Get updated car for history
            $stmt = $conn->prepare("SELECT * FROM cars WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $updatedCar = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Log history
            if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('cars', $id, 'update', 'hr', $oldCar, $updatedCar);
            }
            
            sendResponse(['success' => true, 'message' => 'Vehicle updated successfully']);
            break;
            
        case 'get':
            // checkApiPermission('hr_view');
            
            if (!$id) {
                sendResponse(['success' => false, 'message' => 'Vehicle ID is required'], 400);
            }
            
            $query = "SELECT c.*, 
                      c.registration_date as registration_expiry,
                      c.maintenance_due_date as maintenance_expiry,
                      COALESCE(e.name, c.driver_name, 'N/A') as driver_name 
                      FROM cars c 
                      LEFT JOIN employees e ON c.driver_id = e.id 
                      WHERE c.id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($vehicle) {
                sendResponse([
                    'success' => true,
                    'data' => $vehicle
                ]);
            } else {
                sendResponse(['success' => false, 'message' => 'Vehicle not found'], 404);
            }
            break;
            
        case 'delete':
            // checkApiPermission('hr_delete');
            
            if (!$id) {
                sendResponse(['success' => false, 'message' => 'Vehicle ID is required'], 400);
            }
            
            // Get deleted data for history (before deletion)
            $stmt = $conn->prepare("SELECT * FROM cars WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $deletedCar = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$deletedCar) {
                sendResponse(['success' => false, 'message' => 'Vehicle not found'], 404);
            }
            
            $query = "DELETE FROM cars WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Log history
            if (hr_api_writes_ratib_artifacts() && file_exists(__DIR__ . '/../core/global-history-helper.php')) {
                require_once __DIR__ . '/../core/global-history-helper.php';
                @logGlobalHistory('cars', $id, 'delete', 'hr', $deletedCar, null);
            }
            
            sendResponse(['success' => true, 'message' => 'Vehicle deleted successfully']);
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
            $allowedStatuses = ['Active', 'Inactive', 'Maintenance', 'Retired'];
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
            $query = "UPDATE cars SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
            
            $stmt = $conn->prepare($query);
            $stmt->execute(array_merge([$input['status']], $validIds));
            
            sendResponse([
                'success' => true,
                'message' => count($validIds) . ' vehicle(s) updated successfully'
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
            $query = "DELETE FROM cars WHERE id IN ($placeholders)";
            
            $stmt = $conn->prepare($query);
            $stmt->execute($validIds);
            
            sendResponse([
                'success' => true,
                'message' => count($validIds) . ' vehicle(s) deleted successfully'
            ]);
            break;
            
        default:
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    error_log("HR Cars API Error: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'A system error occurred'], 500);
} catch (Error $e) {
    error_log("HR Cars API Fatal Error: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'A system error occurred'], 500);
}
?>
