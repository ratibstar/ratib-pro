<?php
/**
 * EN: Handles API endpoint/business logic in `api/hr/fix-employees-only.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/hr/fix-employees-only.php`.
 */
/**
 * Fix Employees ID ordering only
 * Run this: https://out.ratib.sa/api/hr/fix-employees-only.php
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/Database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // Get all employees ordered by id DESC (newest first)
        $stmt = $conn->query("SELECT id FROM employees ORDER BY id DESC");
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total = count($employees);
        
        // Step 1: Set all to unique temporary values
        foreach ($employees as $index => $emp) {
            $tempId = 'TEMP_' . $emp['id'] . '_' . time() . '_' . $index;
            $updateStmt = $conn->prepare("UPDATE employees SET employee_id = ? WHERE id = ?");
            $updateStmt->execute([$tempId, $emp['id']]);
        }
        
        // Step 2: Now assign correct IDs (newest gets highest number)
        $updated = 0;
        foreach ($employees as $index => $emp) {
            $counter = $total - $index; // If total=7, index 0 gets 7, index 1 gets 6, etc.
            $newId = 'EM' . str_pad($counter, 4, '0', STR_PAD_LEFT);
            $updateStmt = $conn->prepare("UPDATE employees SET employee_id = ? WHERE id = ?");
            $updateStmt->execute([$newId, $emp['id']]);
            $updated++;
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Updated $updated employees. Newest (id=$employees[0][id]) now has EM" . str_pad($total, 4, '0', STR_PAD_LEFT),
            'expected_order' => 'EM' . str_pad($total, 4, '0', STR_PAD_LEFT) . ', EM' . str_pad($total-1, 4, '0', STR_PAD_LEFT) . ', ... (newest to oldest)'
        ], JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
