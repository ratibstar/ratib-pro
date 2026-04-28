<?php
/**
 * EN: Handles API endpoint/business logic in `api/visa-applications.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/visa-applications.php`.
 */
// Disable error display to prevent HTML output in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

try {
    require_once '../config/database.php';
    require_once '../Utils/response.php';
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    // Test endpoint
    if ($action === 'test') {
        sendResponse([
            'success' => true,
            'message' => 'Visa applications API is working',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    switch ($method) {
        case 'GET':
            handleGet($conn, $action);
            break;
        case 'POST':
            handlePost($conn, $action);
            break;
        case 'PUT':
            handlePut($conn, $action);
            break;
        case 'DELETE':
            handleDelete($conn, $action);
            break;
        default:
            sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    
} catch (Exception $e) {
    error_log("Visa applications API error: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function handleGet($conn, $action) {
    switch ($action) {
        case 'list':
            getVisaApplications($conn);
            break;
        case 'stats':
            getVisaStats($conn);
            break;
        case 'pending':
            getPendingApplications($conn);
            break;
        default:
            getVisaApplications($conn);
    }
}

function handlePost($conn, $action) {
    switch ($action) {
        case 'add':
            addVisaApplication($conn);
            break;
        case 'bulk_approve':
            bulkApproveApplications($conn);
            break;
        case 'bulk_reject':
            bulkRejectApplications($conn);
            break;
        default:
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
}

function handlePut($conn, $action) {
    switch ($action) {
        case 'update':
            updateVisaApplication($conn);
            break;
        case 'approve':
            approveApplication($conn);
            break;
        case 'reject':
            rejectApplication($conn);
            break;
        default:
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
}

function handleDelete($conn, $action) {
    switch ($action) {
        case 'delete':
            deleteVisaApplication($conn);
            break;
        default:
            sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
}

function getVisaApplications($conn) {
    try {
        $query = "SELECT w.*, vt.visa_name, jc.category_name, a.agent_name, s.subagent_name 
                  FROM workers w 
                  LEFT JOIN visa_types vt ON w.visa_type_id = vt.id 
                  LEFT JOIN job_categories jc ON w.job_category_id = jc.id
                  LEFT JOIN agents a ON w.agent_id = a.id
                  LEFT JOIN subagents s ON w.subagent_id = s.id
                  ORDER BY w.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no applications exist, add some sample data
        if (empty($applications)) {
            addSampleData($conn);
            // Fetch again after adding sample data
            $stmt->execute();
            $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        sendResponse([
            'success' => true,
            'applications' => $applications
        ]);
    } catch (Exception $e) {
        error_log("Error fetching applications: " . $e->getMessage());
        sendResponse([
            'success' => false,
            'message' => 'Error fetching applications: ' . $e->getMessage()
        ], 500);
    }
}

function addSampleData($conn) {
    try {
        // Insert sample visa applications
        $sampleData = [
            [
                'worker_name' => 'John Doe',
                'passport_number' => 'A1234567',
                'nationality' => 'American',
                'date_of_birth' => '1990-01-15',
                'gender' => 'male',
                'visa_type_id' => 1,
                'status' => 'pending',
                'contact_number' => '+1234567890',
                'email' => 'john.doe@email.com',
                'address' => '123 Main St, New York, USA'
            ],
            [
                'worker_name' => 'Jane Smith',
                'passport_number' => 'B9876543',
                'nationality' => 'British',
                'date_of_birth' => '1985-05-20',
                'gender' => 'female',
                'visa_type_id' => 2,
                'status' => 'approved',
                'contact_number' => '+44123456789',
                'email' => 'jane.smith@email.com',
                'address' => '456 Oak Ave, London, UK'
            ],
            [
                'worker_name' => 'Ahmed Ali',
                'passport_number' => 'C5555555',
                'nationality' => 'Egyptian',
                'date_of_birth' => '1988-12-10',
                'gender' => 'male',
                'visa_type_id' => 3,
                'status' => 'rejected',
                'contact_number' => '+20123456789',
                'email' => 'ahmed.ali@email.com',
                'address' => '789 Palm St, Cairo, Egypt'
            ]
        ];
        
        $query = "INSERT INTO workers (worker_name, passport_number, nationality, date_of_birth, gender, 
                  visa_type_id, status, contact_number, email, address) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        
        foreach ($sampleData as $data) {
            $stmt->execute([
                $data['worker_name'],
                $data['passport_number'],
                $data['nationality'],
                $data['date_of_birth'],
                $data['gender'],
                $data['visa_type_id'],
                $data['status'],
                $data['contact_number'],
                $data['email'],
                $data['address']
            ]);
        }
        
        error_log("Sample data added successfully");
    } catch (Exception $e) {
        error_log("Error adding sample data: " . $e->getMessage());
    }
}

function getVisaStats($conn) {
    $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
              FROM workers";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    sendResponse([
        'success' => true,
        'stats' => $stats
    ]);
}

function getPendingApplications($conn) {
    $query = "SELECT w.*, vt.visa_name, jc.category_name, a.agent_name, s.subagent_name 
              FROM workers w 
              LEFT JOIN visa_types vt ON w.visa_type_id = vt.id 
              LEFT JOIN job_categories jc ON w.job_category_id = jc.id
              LEFT JOIN agents a ON w.agent_id = a.id
              LEFT JOIN subagents s ON w.subagent_id = s.id
              WHERE w.status = 'pending'
              ORDER BY w.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse([
        'success' => true,
        'applications' => $applications
    ]);
}

function addVisaApplication($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required_fields = ['worker_name', 'passport_number', 'nationality', 'visa_type_id'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            sendResponse(['success' => false, 'message' => "Field $field is required"], 400);
        }
    }
    
    $query = "INSERT INTO workers (worker_name, passport_number, nationality, date_of_birth, gender, 
              contact_number, email, address, agent_id, subagent_id, visa_type_id, job_category_id, 
              salary, status, arrival_date, departure_date) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $result = $stmt->execute([
        $data['worker_name'],
        $data['passport_number'],
        $data['nationality'],
        $data['date_of_birth'] ?? null,
        $data['gender'] ?? null,
        $data['contact_number'] ?? null,
        $data['email'] ?? null,
        $data['address'] ?? null,
        $data['agent_id'] ?? null,
        $data['subagent_id'] ?? null,
        $data['visa_type_id'],
        $data['job_category_id'] ?? null,
        $data['salary'] ?? null,
        $data['status'] ?? 'pending',
        $data['arrival_date'] ?? null,
        $data['departure_date'] ?? null
    ]);
    
    if ($result) {
        $workerId = $conn->lastInsertId();
        $workerName = $data['worker_name'] ?? '';
        if ($workerId && $workerName) {
            $helperPath = __DIR__ . '/accounting/entity-account-helper.php';
            if (file_exists($helperPath)) {
                require_once $helperPath;
                if (function_exists('ensureEntityAccount')) {
                    ensureEntityAccount($conn, 'worker', $workerId, $workerName);
                }
            }
        }
        sendResponse([
            'success' => true,
            'message' => 'Visa application added successfully',
            'id' => $workerId
        ]);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to add visa application'], 500);
    }
}

function bulkApproveApplications($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['application_ids']) || !is_array($data['application_ids'])) {
        sendResponse(['success' => false, 'message' => 'Application IDs are required'], 400);
    }
    
    $ids = implode(',', array_map('intval', $data['application_ids']));
    $approval_date = $data['approval_date'] ?? date('Y-m-d');
    $approval_notes = $data['approval_notes'] ?? '';
    
    $query = "UPDATE workers SET status = 'approved', updated_at = NOW() WHERE id IN ($ids)";
    $stmt = $conn->prepare($query);
    $result = $stmt->execute();
    
    if ($result) {
        sendResponse([
            'success' => true,
            'message' => count($data['application_ids']) . ' applications approved successfully'
        ]);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to approve applications'], 500);
    }
}

function bulkRejectApplications($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['application_ids']) || !is_array($data['application_ids'])) {
        sendResponse(['success' => false, 'message' => 'Application IDs are required'], 400);
    }
    
    $ids = implode(',', array_map('intval', $data['application_ids']));
    $rejection_date = $data['rejection_date'] ?? date('Y-m-d');
    $rejection_reason = $data['rejection_reason'] ?? '';
    $rejection_notes = $data['rejection_notes'] ?? '';
    
    $query = "UPDATE workers SET status = 'rejected', updated_at = NOW() WHERE id IN ($ids)";
    $stmt = $conn->prepare($query);
    $result = $stmt->execute();
    
    if ($result) {
        sendResponse([
            'success' => true,
            'message' => count($data['application_ids']) . ' applications rejected successfully'
        ]);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to reject applications'], 500);
    }
}

function updateVisaApplication($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Application ID is required'], 400);
    }
    
    $fields = [];
    $values = [];
    
    $allowed_fields = ['worker_name', 'passport_number', 'nationality', 'date_of_birth', 'gender', 
                      'contact_number', 'email', 'address', 'agent_id', 'subagent_id', 'visa_type_id', 
                      'job_category_id', 'salary', 'status', 'arrival_date', 'departure_date'];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }
    
    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }
    
    $values[] = $data['id'];
    $query = "UPDATE workers SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $result = $stmt->execute($values);
    
    if ($result) {
        sendResponse(['success' => true, 'message' => 'Application updated successfully']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to update application'], 500);
    }
}

function deleteVisaApplication($conn) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        sendResponse(['success' => false, 'message' => 'Application ID is required'], 400);
    }
    
    $query = "DELETE FROM workers WHERE id = ?";
    $stmt = $conn->prepare($query);
    $result = $stmt->execute([$id]);
    
    if ($result) {
        sendResponse(['success' => true, 'message' => 'Application deleted successfully']);
    } else {
        sendResponse(['success' => false, 'message' => 'Failed to delete application'], 500);
    }
}
?>
