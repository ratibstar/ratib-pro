<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/core/update-musaned-status.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/core/update-musaned-status.php`.
 */
// EN: Musaned status endpoint requires dedicated workers.musaned permission.
// AR: نقطة تحديث حالة مساند تتطلب صلاحية workers.musaned بشكل صريح.
require_once __DIR__ . '/../../core/api-permission-helper.php';
enforceApiPermission('workers', 'musaned');

require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/auth.php';

header('Content-Type: application/json');

// EN: Validate status payload, update worker status fields, then audit the change.
// AR: التحقق من بيانات الحالة، تحديث حقول العامل، ثم تسجيل التغيير في السجل.
try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // EN: Strict input validation to prevent arbitrary column/value updates.
    // AR: تحقق صارم من المدخلات لمنع تعديل أعمدة/قيم غير مسموح بها.
    // Validate input
    if (empty($data['id'])) {
        throw new Exception("Worker ID is required");
    }
    
    if (empty($data['status_name']) || !preg_match('/_status$/', $data['status_name'])) {
        throw new Exception("Invalid status name");
    }
    
    if (empty($data['status_value']) || !in_array($data['status_value'], ['done', 'not_done', 'issues', 'canceled'])) {
        throw new Exception("Invalid status value");
    }
    
    // EN: Load previous worker snapshot for history diff logging.
    // AR: جلب حالة العامل السابقة لاستخدامها في سجل الفروقات.
    // Get old data for history (before update)
    $fetchSql = "SELECT * FROM workers WHERE id = ?";
    $fetchStmt = $conn->prepare($fetchSql);
    $fetchStmt->bind_param('i', $data['id']);
    $fetchStmt->execute();
    $result = $fetchStmt->get_result();
    $oldWorker = $result->fetch_assoc();
    
    if (!$oldWorker) {
        throw new Exception('Worker not found');
    }
    
    // Get issues field name
    $issues_field = str_replace('_status', '_issues', $data['status_name']);
    
    // EN: Update status field and optional issues companion field atomically.
    // AR: تحديث حقل الحالة وحقل الملاحظات المرتبط به بشكل متسق.
    // Prepare SQL based on whether issues are provided
    if ($data['status_value'] === 'issues' && isset($data['issues'])) {
        $sql = "UPDATE workers SET {$data['status_name']} = ?, $issues_field = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssi', $data['status_value'], $data['issues'], $data['id']);
    } else {
        $sql = "UPDATE workers SET {$data['status_name']} = ?, $issues_field = NULL WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('si', $data['status_value'], $data['id']);
    }
    
    if ($stmt->execute()) {
        // Get updated worker data
        $sql = "SELECT w.*, 
                a.full_name as agent_name, 
                s.full_name as subagent_name 
                FROM workers w 
                LEFT JOIN agents a ON w.agent_id = a.agent_id 
                LEFT JOIN subagents s ON w.subagent_id = s.subagent_id 
                WHERE w.id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $data['id']);
        $stmt->execute();
        $newWorker = $stmt->get_result()->fetch_assoc();
        
        // Log history - convert MySQLi arrays to proper format
        $helperPath = __DIR__ . '/../../core/global-history-helper.php';
        if (file_exists($helperPath) && $oldWorker && $newWorker) {
            // Database class should already be loaded from config.php, but ensure it's available
            if (!class_exists('Database')) {
                error_log("Musaned Status Update: Database class not found, trying to load config");
            }
            
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                // Convert MySQLi associative arrays to regular arrays
                $oldData = array();
                $newData = array();
                foreach ($oldWorker as $key => $value) {
                    if (!is_numeric($key)) {
                        $oldData[$key] = $value;
                    }
                }
                foreach ($newWorker as $key => $value) {
                    if (!is_numeric($key)) {
                        $newData[$key] = $value;
                    }
                }
                
                // Log history with error handling
                $logResult = @logGlobalHistory('workers', $data['id'], 'update', 'workers', $oldData, $newData);
                if (!$logResult) {
                    error_log("Musaned Status Update: Failed to log history for worker ID: " . $data['id']);
                } else {
                    error_log("Musaned Status Update: Successfully logged history for worker ID: " . $data['id']);
                }
            } else {
                error_log("Musaned Status Update: logGlobalHistory function not found");
            }
        } else {
            error_log("Musaned Status Update: Helper path exists: " . (file_exists($helperPath) ? 'yes' : 'no') . ", oldWorker: " . ($oldWorker ? 'yes' : 'no') . ", newWorker: " . ($newWorker ? 'yes' : 'no'));
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => $newWorker
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