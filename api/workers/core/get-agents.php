<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/core/get-agents.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/core/get-agents.php`.
 */
// EN: Agent list endpoint reuses shared config/auth context.
// AR: نقطة جلب الوكلاء تعتمد على تهيئة الإعدادات والمصادقة المشتركة.
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// EN: Fetch active agents for worker form selectors.
// AR: جلب الوكلاء النشطين لاستخدامهم في قوائم اختيار نموذج العامل.
try {
    // EN: Query returns minimal identification fields for lightweight dropdown payload.
    // AR: الاستعلام يرجع حقول تعريف أساسية فقط لتقليل حجم البيانات في القوائم.
    $stmt = $conn->prepare("
        SELECT agent_id, formatted_id, full_name 
        FROM agents 
        WHERE status = 'active'
        ORDER BY full_name
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    $agents = [];
    
    while ($row = $result->fetch_assoc()) {
        $agents[] = $row;
    }

    echo json_encode([
        'success' => true,
        'agents' => $agents
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close(); 