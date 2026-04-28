<?php
/**
 * EN: Handles API endpoint/business logic in `api/visa-applications-simple.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/visa-applications-simple.php`.
 */
require_once __DIR__ . '/../includes/config.php';

// Simple visa applications API without external dependencies
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

// Simple response function
function sendResponse($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

// Function definitions
function getVisaApplications($pdo) {
    try {
        $query = "SELECT w.*, vt.visa_name, jc.category_name, a.agent_name, s.subagent_name 
                  FROM workers w 
                  LEFT JOIN visa_types vt ON w.visa_type_id = vt.id 
                  LEFT JOIN job_categories jc ON w.job_category_id = jc.id
                  LEFT JOIN agents a ON w.agent_id = a.id
                  LEFT JOIN subagents s ON w.subagent_id = s.id
                  ORDER BY w.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no applications exist, add some sample data
        if (empty($applications)) {
            addSampleData($pdo);
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

function addSampleData($pdo) {
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
        
        $stmt = $pdo->prepare($query);
        
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

function getVisaStats($pdo) {
    try {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                  FROM workers";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        sendResponse([
            'success' => true,
            'stats' => $stats
        ]);
    } catch (Exception $e) {
        error_log("Error fetching stats: " . $e->getMessage());
        sendResponse([
            'success' => false,
            'message' => 'Error fetching stats: ' . $e->getMessage()
        ], 500);
    }
}

function addVisaApplication($pdo) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            sendResponse(['success' => false, 'message' => 'Invalid JSON data'], 400);
        }
        
        $required_fields = ['worker_name', 'passport_number', 'nationality'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                sendResponse(['success' => false, 'message' => "Field $field is required"], 400);
            }
        }
        
        $query = "INSERT INTO workers (worker_name, passport_number, nationality, date_of_birth, gender, 
                  contact_number, email, address, agent_id, visa_type_id, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($query);
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
            $data['visa_type_id'] ?? 1,
            $data['status'] ?? 'pending'
        ]);
        
        if ($result) {
            $workerId = $pdo->lastInsertId();
            $workerName = $data['worker_name'] ?? '';
            if ($workerId && $workerName) {
                $helperPath = __DIR__ . '/accounting/entity-account-helper.php';
                if (file_exists($helperPath)) {
                    require_once $helperPath;
                    if (function_exists('ensureEntityAccount')) {
                        ensureEntityAccount($pdo, 'worker', $workerId, $workerName);
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
    } catch (Exception $e) {
        error_log("Error adding application: " . $e->getMessage());
        sendResponse(['success' => false, 'message' => 'Error adding application: ' . $e->getMessage()], 500);
    }
}

function bulkApproveApplications($pdo) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['application_ids']) || !is_array($data['application_ids'])) {
            sendResponse(['success' => false, 'message' => 'Application IDs are required'], 400);
        }
        
        $ids = implode(',', array_map('intval', $data['application_ids']));
        
        $query = "UPDATE workers SET status = 'approved', updated_at = NOW() WHERE id IN ($ids)";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute();
        
        if ($result) {
            sendResponse([
                'success' => true,
                'message' => count($data['application_ids']) . ' applications approved successfully'
            ]);
        } else {
            sendResponse(['success' => false, 'message' => 'Failed to approve applications'], 500);
        }
    } catch (Exception $e) {
        error_log("Error bulk approving: " . $e->getMessage());
        sendResponse(['success' => false, 'message' => 'Error bulk approving: ' . $e->getMessage()], 500);
    }
}

function bulkRejectApplications($pdo) {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['application_ids']) || !is_array($data['application_ids'])) {
            sendResponse(['success' => false, 'message' => 'Application IDs are required'], 400);
        }
        
        $ids = implode(',', array_map('intval', $data['application_ids']));
        
        $query = "UPDATE workers SET status = 'rejected', updated_at = NOW() WHERE id IN ($ids)";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute();
        
        if ($result) {
            sendResponse([
                'success' => true,
                'message' => count($data['application_ids']) . ' applications rejected successfully'
            ]);
        } else {
            sendResponse(['success' => false, 'message' => 'Failed to reject applications'], 500);
        }
    } catch (Exception $e) {
        error_log("Error bulk rejecting: " . $e->getMessage());
        sendResponse(['success' => false, 'message' => 'Error bulk rejecting: ' . $e->getMessage()], 500);
    }
}

function deleteVisaApplication($pdo) {
    try {
        $id = $_GET['id'] ?? null;
        
        if (!$id) {
            sendResponse(['success' => false, 'message' => 'Application ID is required'], 400);
        }
        
        $query = "DELETE FROM workers WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $result = $stmt->execute([$id]);
        
        if ($result) {
            sendResponse(['success' => true, 'message' => 'Application deleted successfully']);
        } else {
            sendResponse(['success' => false, 'message' => 'Failed to delete application'], 500);
        }
    } catch (Exception $e) {
        error_log("Error deleting application: " . $e->getMessage());
        sendResponse(['success' => false, 'message' => 'Error deleting application: ' . $e->getMessage()], 500);
    }
}

// Main execution
try {
    // Database connection using config constants
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        // Try to create database if it doesn't exist (only for localhost)
        if (DB_HOST === 'localhost') {
            try {
                $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
                $pdo->exec("USE " . DB_NAME);
            
                // Create workers table if it doesn't exist
                $createTable = "CREATE TABLE IF NOT EXISTS workers (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    worker_name VARCHAR(100) NOT NULL,
                    passport_number VARCHAR(50) UNIQUE,
                    nationality VARCHAR(50),
                    date_of_birth DATE,
                    gender ENUM('male', 'female'),
                    contact_number VARCHAR(20),
                    email VARCHAR(100),
                    address TEXT,
                    agent_id INT,
                    subagent_id INT,
                    visa_type_id INT,
                    job_category_id INT,
                    salary DECIMAL(10,2),
                    status ENUM('pending', 'approved', 'rejected', 'deployed', 'returned') DEFAULT 'pending',
                    arrival_date DATE,
                    departure_date DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )";
                $pdo->exec($createTable);
            
            } catch (PDOException $e2) {
                error_log("Database creation failed: " . $e2->getMessage());
                sendResponse([
                    'success' => false,
                    'message' => 'Database connection failed: ' . $e2->getMessage()
                ], 500);
            }
        }
    }
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    // Test endpoint
    if ($action === 'test') {
        sendResponse([
            'success' => true,
            'message' => 'Visa applications API is working',
            'timestamp' => date('Y-m-d H:i:s'),
            'database' => DB_NAME,
            'host' => DB_HOST
        ]);
    }
    
    // Debug endpoint
    if ($action === 'debug') {
        sendResponse([
            'success' => true,
            'message' => 'Debug information',
            'database_connected' => true,
            'database_name' => DB_NAME,
            'host' => DB_HOST,
            'method' => $method,
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    switch ($method) {
        case 'GET':
            if ($action === 'list') {
                getVisaApplications($pdo);
            } elseif ($action === 'stats') {
                getVisaStats($pdo);
            } else {
                getVisaApplications($pdo);
            }
            break;
        case 'POST':
            if ($action === 'add') {
                addVisaApplication($pdo);
            } elseif ($action === 'bulk_approve') {
                bulkApproveApplications($pdo);
            } elseif ($action === 'bulk_reject') {
                bulkRejectApplications($pdo);
            } else {
                sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
            }
            break;
        case 'DELETE':
            if ($action === 'delete') {
                deleteVisaApplication($pdo);
            } else {
                sendResponse(['success' => false, 'message' => 'Invalid action'], 400);
            }
            break;
        default:
            sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
    
} catch (Exception $e) {
    error_log("Visa applications API error: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => $e->getMessage()], 500);
} catch (Error $e) {
    error_log("Visa applications API PHP error: " . $e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal server error'], 500);
}
