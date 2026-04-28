<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/core/get-simple.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/core/get-simple.php`.
 */
// EN: Lightweight diagnostic endpoint uses direct config constants for quick access.
// AR: نقطة نهاية خفيفة للتشخيص تستخدم ثوابت الإعداد مباشرة للوصول السريع.
require_once __DIR__ . '/../../../includes/config.php';

header('Content-Type: application/json');

// EN: Minimal read path: fetch first page sample + total count.
// AR: مسار قراءة مبسط: جلب عينة الصفحة الأولى مع العدد الكلي.
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // EN: Pull limited worker sample for quick client-side smoke checks.
    // AR: جلب عينة محدودة من العمال لفحوصات سريعة على الواجهة.
    // Simple query to get workers
    $stmt = $pdo->query("SELECT * FROM workers LIMIT 10");
    $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM workers");
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'workers' => $workers,
            'pagination' => [
                'page' => 1,
                'limit' => 10,
                'total' => (int)$total,
                'total_pages' => ceil($total / 10)
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 