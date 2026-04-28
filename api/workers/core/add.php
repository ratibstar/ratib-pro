<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/core/add.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/core/add.php`.
 */
// EN: Legacy create endpoint wired to shared config/auth bootstrap.
// AR: نقطة إنشاء قديمة مرتبطة بتهيئة الإعدادات والمصادقة المشتركة.
require_once '../../includes/config.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// EN: Worker creation flow with required-field checks, duplicate guards, and post-create hooks.
// AR: مسار إنشاء العامل مع تحقق الحقول المطلوبة، وفحوصات التكرار، وخطافات ما بعد الإنشاء.
try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // EN: Validate minimum identity profile before insert.
    // AR: التحقق من الحد الأدنى لبيانات الهوية قبل الإدخال.
    // Validate required fields
    $required = ['full_name', 'identity_number', 'passport_number', 'nationality'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // EN: Prevent duplicate national identity values.
    // AR: منع تكرار رقم الهوية الوطنية.
    // Check for duplicate identity number
    $stmt = $conn->prepare("SELECT id FROM workers WHERE identity_number = ?");
    $stmt->bind_param('s', $data['identity_number']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("Identity number already exists");
    }
    
    // Check for duplicate passport number
    $stmt = $conn->prepare("SELECT id FROM workers WHERE passport_number = ?");
    $stmt->bind_param('s', $data['passport_number']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("Passport number already exists");
    }
    
    // EN: Insert primary worker record and retain generated ID for enrichment.
    // AR: إدراج سجل العامل الأساسي والاحتفاظ بالمعرف الناتج لاستكمال البيانات.
    // Insert worker
    $sql = "INSERT INTO workers (
        full_name, identity_number, passport_number, nationality,
        language, phone, email, address,
        agent_id, subagent_id,
        police_number, medical_number, visa_number, ticket_number,
        emergency_name, emergency_relation, emergency_phone, emergency_address
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssssssssiissssssss',
        $data['full_name'],
        $data['identity_number'],
        $data['passport_number'],
        $data['nationality'],
        $data['language'],
        $data['phone'],
        $data['email'],
        $data['address'],
        $data['agent_id'],
        $data['subagent_id'],
        $data['police_number'],
        $data['medical_number'],
        $data['visa_number'],
        $data['ticket_number'],
        $data['emergency_name'],
        $data['emergency_relation'],
        $data['emergency_phone'],
        $data['emergency_address']
    );
    
    if ($stmt->execute()) {
        $id = $conn->insert_id;
        
        // Get the newly created worker with formatted_id
        $sql = "SELECT w.*, 
                a.full_name as agent_name, 
                s.full_name as subagent_name 
                FROM workers w 
                LEFT JOIN agents a ON w.agent_id = a.agent_id 
                LEFT JOIN subagents s ON w.subagent_id = s.subagent_id 
                WHERE w.id = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $worker = $stmt->get_result()->fetch_assoc();
        
        // EN: Append create audit event to global history stream.
        // AR: إضافة حدث إنشاء إلى سجل التاريخ العام.
        // Log history
        $helperPath = __DIR__ . '/../../core/global-history-helper.php';
        if (file_exists($helperPath) && $worker) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                @logGlobalHistory('workers', $id, 'create', 'workers', null, $worker);
            }
        }
        
        // EN: Ensure accounting entity account exists for the newly created worker.
        // AR: ضمان إنشاء الحساب المحاسبي المرتبط بالعامل الجديد.
        // Auto-create GL account for this worker
        require_once __DIR__ . '/../../accounting/entity-account-helper.php';
        $workerName = $worker['worker_name'] ?? $worker['full_name'] ?? $data['full_name'] ?? '';
        if ($workerName && function_exists('ensureEntityAccount')) {
            ensureEntityAccount($conn, 'worker', $id, $workerName);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Worker added successfully',
            'data' => $worker
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