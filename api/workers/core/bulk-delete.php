<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/core/bulk-delete.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/core/bulk-delete.php`.
 */
// EN: Bulk-delete endpoint bootstraps shared config + auth context.
// AR: نقطة الحذف الجماعي تعتمد على تهيئة الإعدادات والمصادقة المشتركة.
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// EN: Bulk deletion flow: validate IDs, snapshot rows, delete, then audit.
// AR: مسار الحذف الجماعي: تحقق المعرفات، حفظ لقطة السجلات، حذف، ثم تسجيل التدقيق.
try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // EN: Ensure request contains array of worker IDs.
    // AR: التحقق من وجود مصفوفة معرفات العمال في الطلب.
    // Validate input
    if (empty($data['ids']) || !is_array($data['ids'])) {
        throw new Exception("Worker IDs array is required");
    }
    
    // Convert worker IDs array to integers
    $workerIds = array_map('intval', $data['ids']);
    $workerIds = array_filter($workerIds);
    
    if (empty($workerIds)) {
        throw new Exception('No valid worker IDs provided');
    }
    
    // EN: Capture pre-delete row state for history completeness.
    // AR: حفظ حالة السجلات قبل الحذف لضمان اكتمال سجل التاريخ.
    // Get old data for history (before deletion)
    $placeholders = str_repeat('?,', count($workerIds) - 1) . '?';
    $fetchSql = "SELECT * FROM workers WHERE id IN ($placeholders)";
    $fetchStmt = $conn->prepare($fetchSql);
    $types = str_repeat('i', count($workerIds));
    $fetchStmt->bind_param($types, ...$workerIds);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    $deletedWorkers = [];
    while ($row = $result->fetch_assoc()) {
        $deletedWorkers[] = $row;
    }
    
    // EN: Execute physical row deletion for selected workers.
    // AR: تنفيذ حذف فعلي لصفوف العمال المحددين.
    // Delete workers
    $sql = "DELETE FROM workers WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$workerIds);
    
    if ($stmt->execute()) {
        // Log history for each deleted worker
        $helperPath = __DIR__ . '/../../core/global-history-helper.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                foreach ($deletedWorkers as $deletedWorker) {
                    @logGlobalHistory('workers', $deletedWorker['id'], 'delete', 'workers', $deletedWorker, null);
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Workers deleted successfully',
            'count' => $stmt->affected_rows
        ]);
    } else {
        throw new Exception($stmt->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close(); 