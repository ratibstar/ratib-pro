<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/debug-query.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/debug-query.php`.
 */
/**
 * Debug script to check what query is actually being executed
 * Run this: https://out.ratib.sa/api/hr/debug-query.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/Database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $results = [];
    
    // Test Employees query
    $query = "SELECT * FROM employees ORDER BY id DESC LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results['employees'] = [
        'query' => $query,
        'count' => count($employees),
        'ids' => array_column($employees, 'id'),
        'employee_ids' => array_column($employees, 'employee_id'),
        'first_record' => $employees[0] ?? null,
        'last_record' => $employees[count($employees)-1] ?? null,
        'is_descending' => count($employees) > 1 ? ($employees[0]['id'] > $employees[count($employees)-1]['id']) : 'N/A'
    ];
    
    // Test Attendance query
    $query = "SELECT a.* FROM attendance a ORDER BY a.id DESC LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $results['attendance'] = [
        'query' => $query,
        'count' => count($attendance),
        'ids' => array_column($attendance, 'id'),
        'record_ids' => array_column($attendance, 'record_id'),
        'first_record' => $attendance[0] ?? null,
        'last_record' => $attendance[count($attendance)-1] ?? null,
        'is_descending' => count($attendance) > 1 ? ($attendance[0]['id'] > $attendance[count($attendance)-1]['id']) : 'N/A'
    ];
    
    // Check actual file content
    $employeesFile = __DIR__ . '/employees.php';
    $fileContent = file_get_contents($employeesFile);
    $hasOrderByDesc = strpos($fileContent, 'ORDER BY id DESC') !== false || strpos($fileContent, 'ORDER BY a.id DESC') !== false;
    
    $results['file_check'] = [
        'employees_file_exists' => file_exists($employeesFile),
        'has_order_by_desc' => $hasOrderByDesc,
        'file_path' => $employeesFile
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Query debug results',
        'note' => 'is_descending should be true (newest first)',
        'results' => $results
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
