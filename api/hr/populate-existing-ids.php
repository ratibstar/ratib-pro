<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/populate-existing-ids.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/populate-existing-ids.php`.
 */
/**
 * Populate Existing Records with EM0001 Format IDs
 * 
 * This script populates existing records in HR tables with EM0001 format IDs
 * if they don't already have them.
 * 
 * Run this script once after adding the record_id columns.
 * Access via: /api/hr/populate-existing-ids.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

header('Content-Type: application/json');

try {
    require_once __DIR__ . '/../core/Database.php';
    require_once __DIR__ . '/../core/formatted-id-helper.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to load required files: ' . $e->getMessage()]);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    $results = [];
    
    // 1. Populate employees table (update employee_id to EM format if not already)
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM employees WHERE employee_id IS NULL OR employee_id NOT REGEXP '^EM[0-9]+$'");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($count > 0) {
            $stmt = $conn->query("SELECT id FROM employees WHERE employee_id IS NULL OR employee_id NOT REGEXP '^EM[0-9]+$' ORDER BY id DESC");
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updated = 0;
            foreach ($employees as $emp) {
                try {
                    $newId = generateHREmployeeId($conn);
                    $updateStmt = $conn->prepare("UPDATE employees SET employee_id = ? WHERE id = ?");
                    $updateStmt->execute([$newId, $emp['id']]);
                    $updated++;
                } catch (Exception $e) {
                    error_log("Error updating employee {$emp['id']}: " . $e->getMessage());
                }
            }
            $results['employees'] = "Updated $updated employees";
        } else {
            $results['employees'] = "All employees already have EM format IDs";
        }
    } catch (Exception $e) {
        $results['employees'] = "Error: " . $e->getMessage();
    }
    
    // 2. Populate attendance table
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM attendance WHERE record_id IS NULL OR record_id NOT REGEXP '^AT[0-9]+$'");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($count > 0) {
            $stmt = $conn->query("SELECT id FROM attendance WHERE record_id IS NULL OR record_id NOT REGEXP '^AT[0-9]+$' ORDER BY id DESC");
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updated = 0;
            foreach ($records as $record) {
                try {
                    $newId = generateHRAttendanceId($conn);
                    $updateStmt = $conn->prepare("UPDATE attendance SET record_id = ? WHERE id = ?");
                    $updateStmt->execute([$newId, $record['id']]);
                    $updated++;
                } catch (Exception $e) {
                    error_log("Error updating attendance {$record['id']}: " . $e->getMessage());
                }
            }
            $results['attendance'] = "Updated $updated attendance records";
        } else {
            $results['attendance'] = "All attendance records already have AT format IDs";
        }
    } catch (Exception $e) {
        $results['attendance'] = "Error: " . $e->getMessage();
    }
    
    // 3. Populate advances table
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM advances WHERE record_id IS NULL OR record_id NOT REGEXP '^AD[0-9]+$'");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($count > 0) {
            $stmt = $conn->query("SELECT id FROM advances WHERE record_id IS NULL OR record_id NOT REGEXP '^AD[0-9]+$' ORDER BY id DESC");
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updated = 0;
            foreach ($records as $record) {
                try {
                    $newId = generateHRAdvanceId($conn);
                    $updateStmt = $conn->prepare("UPDATE advances SET record_id = ? WHERE id = ?");
                    $updateStmt->execute([$newId, $record['id']]);
                    $updated++;
                } catch (Exception $e) {
                    error_log("Error updating advance {$record['id']}: " . $e->getMessage());
                }
            }
            $results['advances'] = "Updated $updated advance records";
        } else {
            $results['advances'] = "All advance records already have AD format IDs";
        }
    } catch (Exception $e) {
        $results['advances'] = "Error: " . $e->getMessage();
    }
    
    // 4. Populate salaries table
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM salaries WHERE record_id IS NULL OR record_id NOT REGEXP '^PA[0-9]+$'");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($count > 0) {
            $stmt = $conn->query("SELECT id FROM salaries WHERE record_id IS NULL OR record_id NOT REGEXP '^PA[0-9]+$' ORDER BY id DESC");
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updated = 0;
            foreach ($records as $record) {
                try {
                    $newId = generateHRSalaryId($conn);
                    $updateStmt = $conn->prepare("UPDATE salaries SET record_id = ? WHERE id = ?");
                    $updateStmt->execute([$newId, $record['id']]);
                    $updated++;
                } catch (Exception $e) {
                    error_log("Error updating salary {$record['id']}: " . $e->getMessage());
                }
            }
            $results['salaries'] = "Updated $updated salary records";
        } else {
            $results['salaries'] = "All salary records already have PA format IDs";
        }
    } catch (Exception $e) {
        $results['salaries'] = "Error: " . $e->getMessage();
    }
    
    // 5. Populate hr_documents table
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM hr_documents WHERE record_id IS NULL OR record_id NOT REGEXP '^DO[0-9]+$'");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($count > 0) {
            $stmt = $conn->query("SELECT id FROM hr_documents WHERE record_id IS NULL OR record_id NOT REGEXP '^DO[0-9]+$' ORDER BY id DESC");
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updated = 0;
            foreach ($records as $record) {
                try {
                    $newId = generateHRDocumentId($conn);
                    $updateStmt = $conn->prepare("UPDATE hr_documents SET record_id = ? WHERE id = ?");
                    $updateStmt->execute([$newId, $record['id']]);
                    $updated++;
                } catch (Exception $e) {
                    error_log("Error updating document {$record['id']}: " . $e->getMessage());
                }
            }
            $results['documents'] = "Updated $updated document records";
        } else {
            $results['documents'] = "All document records already have DO format IDs";
        }
    } catch (Exception $e) {
        $results['documents'] = "Error: " . $e->getMessage();
    }
    
    // 6. Populate cars table
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM cars WHERE record_id IS NULL OR record_id NOT REGEXP '^VE[0-9]+$'");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        if ($count > 0) {
            $stmt = $conn->query("SELECT id FROM cars WHERE record_id IS NULL OR record_id NOT REGEXP '^VE[0-9]+$' ORDER BY id DESC");
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $updated = 0;
            foreach ($records as $record) {
                try {
                    $newId = generateHRVehicleId($conn);
                    $updateStmt = $conn->prepare("UPDATE cars SET record_id = ? WHERE id = ?");
                    $updateStmt->execute([$newId, $record['id']]);
                    $updated++;
                } catch (Exception $e) {
                    error_log("Error updating vehicle {$record['id']}: " . $e->getMessage());
                }
            }
            $results['vehicles'] = "Updated $updated vehicle records";
        } else {
            $results['vehicles'] = "All vehicle records already have VE format IDs";
        }
    } catch (Exception $e) {
        $results['vehicles'] = "Error: " . $e->getMessage();
    }
    
    // Output results
    echo json_encode([
        'success' => true,
        'message' => 'ID population completed',
        'results' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
