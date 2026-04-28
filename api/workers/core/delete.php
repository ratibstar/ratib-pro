<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/core/delete.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/core/delete.php`.
 */
// EN: Buffer output to prevent accidental non-JSON fragments in API response.
// AR: تخزين المخرجات مؤقتاً لمنع أي أجزاء غير JSON من الظهور في الاستجابة.
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../../Utils/response.php';

// EN: Soft-delete workflow with audit snapshot capture before status mutation.
// AR: مسار حذف منطقي مع حفظ لقطة سجل التدقيق قبل تغيير الحالة.
try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['ids']) || !is_array($data['ids'])) {
        throw new Exception('Worker IDs are required');
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();

    // EN: Sanitize incoming IDs to safe integer list before SQL placeholders.
    // AR: تنقية المعرفات الواردة إلى قائمة أرقام صحيحة قبل بناء الاستعلام.
    // Convert to integers and validate
    $ids = array_map('intval', $data['ids']);
    $ids = array_filter($ids);
    
    if (empty($ids)) {
        throw new Exception('No valid worker IDs provided');
    }
    
    // EN: Preload affected rows for history trail and post-action reporting.
    // AR: جلب الصفوف المتأثرة مسبقاً لأغراض السجل والتقارير بعد التنفيذ.
    // Get deleted data for history (before deletion)
    $deletedWorkers = [];
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $conn->prepare("SELECT * FROM workers WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $deletedWorkers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // EN: Mark records as deleted (soft delete) instead of physical removal.
    // AR: تعليم السجلات كمحذوفة (حذف منطقي) بدلاً من الإزالة الفعلية.
    // Soft delete workers
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    $sql = "UPDATE workers SET status = 'deleted' WHERE id IN ($placeholders)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($ids);

    $affected = $stmt->rowCount();
    
    // Log history for each deleted worker
    $helperPath = __DIR__ . '/../../core/global-history-helper.php';
    if (file_exists($helperPath)) {
        require_once $helperPath;
        if (function_exists('logGlobalHistory')) {
            foreach ($deletedWorkers as $deletedWorker) {
                error_log("🔍 Attempting to log worker delete history: ID=" . $deletedWorker['id']);
                $result = logGlobalHistory('workers', $deletedWorker['id'], 'delete', 'workers', $deletedWorker, null);
                if ($result) {
                    error_log("✅ Worker delete history logged: ID=" . $deletedWorker['id']);
                } else {
                    error_log("❌ Failed to log worker delete history: ID=" . $deletedWorker['id']);
                }
            }
        } else {
            error_log("❌ logGlobalHistory function not found after require");
        }
    } else {
        error_log("❌ History helper not found at: $helperPath");
    }

    ob_clean();
    sendResponse([
        'success' => true,
        'message' => "$affected worker(s) deleted successfully",
        'data' => [
            'deleted_count' => $affected,
            'deleted_ids' => $ids
        ]
    ]);

} catch (Exception $e) {
    error_log("Worker delete error: " . $e->getMessage());
    ob_clean();
    sendResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
} catch (Throwable $e) {
    error_log("Worker delete fatal error: " . $e->getMessage());
    ob_clean();
    sendResponse([
        'success' => false,
        'message' => 'Fatal error: ' . $e->getMessage()
    ], 500);
} 