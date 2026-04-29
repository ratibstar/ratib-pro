<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/core/create.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/core/create.php`.
 */
// EN: Start buffered output to guarantee clean JSON responses on all code paths.
// AR: بدء التخزين المؤقت للمخرجات لضمان استجابات JSON نظيفة في كل المسارات.
// Start output buffering
ob_start();

// Disable error display but keep logging
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../core/Database.php';
// Load response.php - try root Utils first, then api/utils
$responsePath = __DIR__ . '/../../../Utils/response.php';
if (!file_exists($responsePath)) {
    $responsePath = __DIR__ . '/../../utils/response.php';
}
if (!file_exists($responsePath)) {
    error_log('ERROR: response.php not found. Tried: ' . __DIR__ . '/../../../Utils/response.php and ' . __DIR__ . '/../../utils/response.php');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server configuration error']);
    exit;
}
require_once $responsePath;
require_once __DIR__ . '/../../core/api-permission-helper.php';
require_once __DIR__ . '/../indonesia-compliance-helper.php';
require_once __DIR__ . '/../workflow-engine.php';
require_once __DIR__ . '/country-profile-enforcement.php';

// EN: Permission gate: only users with worker-create access can proceed.
// AR: بوابة صلاحيات: يسمح فقط لمن يملك صلاحية إنشاء العمال بالمتابعة.
// Enforce permission for creating workers
try {
    enforceApiPermission('workers', 'create');
} catch (Exception $e) {
    error_log('Permission check error: ' . $e->getMessage());
    ob_clean();
    if (!function_exists('sendResponse')) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Permission check failed: ' . $e->getMessage()]);
        exit;
    }
    sendResponse(['success' => false, 'message' => 'Permission check failed: ' . $e->getMessage()], 500);
}

// EN: Local JSON responder fallback used when shared response helper is unavailable.
// AR: دالة محلية بديلة لإرجاع JSON عند عدم توفر مساعد الاستجابات المشترك.
// Function to send JSON response
function sendJsonResponse($data, $statusCode = 200) {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    // Set headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    http_response_code($statusCode);
    
    
    // Send response
    echo json_encode($data);
    exit;
}

try {
    // Get JSON data from request
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        throw new Exception('Invalid input data - JSON decode failed');
    }

    // EN: Minimal required-field validation before any database interaction.
    // AR: تحقق أساسي من الحقول المطلوبة قبل أي تفاعل مع قاعدة البيانات.
    // Required fields check (only essential fields)
    $required = ['full_name', 'agent_id', 'gender'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("$field is required");
        }
    }
    
    // Set default nationality if not provided
    if (empty($data['nationality'])) {
        $data['nationality'] = 'Not specified';
    }

    // Enforce country profile requirements on backend (not UI only).
    ratib_enforce_country_requirements($data, null);

    $isIndonesiaWorker = ratib_worker_is_indonesia_payload([
        'country' => (string)($data['country'] ?? ''),
        'nationality' => (string)($data['nationality'] ?? ''),
        'language' => (string)($data['language'] ?? ''),
    ]);

    // Lifecycle-specific validation removed to restore previous Ratib Pro flow.

    // Database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratib_indonesia_compliance_ensure_schema($conn);
    ratib_worker_lifecycle_ensure_schema($conn, $data);
    ratib_workflow_ensure_schema($conn);
    
    // EN: Schema compatibility guard: extend status enum dynamically if deployment is outdated.
    // AR: حماية توافق المخطط: توسيع ENUM للحالة ديناميكياً إذا كانت البنية قديمة.
    // Check and update status column ENUM if needed to include 'inactive' and 'suspended'
    // This ensures the database accepts all status values we're trying to save
    try {
        $checkEnum = $conn->query("SHOW COLUMNS FROM workers WHERE Field = 'status'");
        $statusColumn = $checkEnum->fetch(PDO::FETCH_ASSOC);
        if ($statusColumn && isset($statusColumn['Type'])) {
            $enumType = strtolower($statusColumn['Type']);
            // Check if 'inactive' and 'suspended' are in the ENUM
            if (stripos($enumType, 'inactive') === false || stripos($enumType, 'suspended') === false) {
                // Alter the ENUM to include the new values - preserve existing values and add new ones
                $alterQuery = "ALTER TABLE workers MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'deployed', 'returned', 'inactive', 'suspended', 'active') DEFAULT 'pending'";
                $conn->exec($alterQuery);
            }
        }
    } catch (Exception $e) {
        // Continue anyway - might be VARCHAR instead of ENUM
    }
    
    try {
        // EN: Transaction boundary to keep worker creation atomic and rollback-safe.
        // AR: بداية المعاملة لضمان إنشاء العامل بشكل ذري مع إمكانية التراجع الآمن.
        // Start transaction
        $conn->beginTransaction();
        
        // Check if workers table exists and get its structure
        $stmt = $conn->query("DESCRIBE workers");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        ratib_indonesia_compliance_ensure_schema($conn);
        $stmt = $conn->query("DESCRIBE workers");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Generate worker ID - handle case where table might be empty
        $stmt = $conn->query("SELECT COALESCE(MAX(CAST(SUBSTRING(formatted_id, 2) AS UNSIGNED)), 0) + 1 as next_id FROM workers WHERE formatted_id REGEXP '^W[0-9]+$'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextId = $result ? $result['next_id'] : 1;
        
        // EN: Normalize frontend field names into canonical database column names.
        // AR: توحيد أسماء الحقول القادمة من الواجهة إلى أسماء الأعمدة المعتمدة في قاعدة البيانات.
        // Map frontend field names to database column names
        $mappedData = $data;
        if (isset($data['full_name'])) {
            $mappedData['worker_name'] = $data['full_name'];
            unset($mappedData['full_name']);
        }
        if (isset($data['phone'])) {
            $mappedData['contact_number'] = $data['phone'];
            unset($mappedData['phone']);
        }
        
        // Handle empty values - convert empty strings to NULL for nullable fields
        $nullableFields = [
            'subagent_id', 'passport_number', 'identity_number', 'police_number', 'medical_number',
            'visa_number', 'ticket_number', 'training_certificate_number', 'approval_reference_id',
            'medical_center_name', 'training_center', 'contract_signed_number', 'insurance_number', 'exit_permit_number',
            'identity_date', 'passport_date', 'police_date', 'medical_date', 'visa_date', 'ticket_date',
            'training_certificate_date', 'passport_expiry_date', 'personal_photo_url', 'biometric_id',
            'demand_letter_id', 'salary', 'working_hours', 'contract_duration', 'vacation_days',
            'accommodation_details', 'food_details', 'transport_details', 'insurance_details',
            'medical_check_date', 'training_notes', 'government_registration_number', 'worker_card_number',
            'exit_clearance_status', 'work_permit_number', 'flight_ticket_number', 'travel_date',
            'insurance_policy_number', 'country_compliance_primary_file', 'country_compliance_secondary_file',
            'contract_deployment_primary_file', 'contract_deployment_secondary_file', 'contract_deployment_verification_file',
            'current_stage', 'stage_completed', 'workflow_version_id'
        ];
        foreach ($nullableFields as $field) {
            if (isset($mappedData[$field]) && $mappedData[$field] === '') {
                $mappedData[$field] = null;
            }
        }

        // Set default document statuses if not provided and column exists
        $documentStatusFields = [
            'identity_status', 'passport_status', 'police_status', 'medical_status', 'visa_status',
            'ticket_status', 'training_certificate_status', 'contract_signed_status', 'insurance_status',
            'exit_permit_status', 'country_compliance_primary_status', 'country_compliance_secondary_status',
            'contract_deployment_primary_status', 'contract_deployment_secondary_status', 'contract_deployment_verification_status'
        ];
        foreach ($documentStatusFields as $field) {
            if (in_array($field, $columns)) {
                if (!isset($mappedData[$field]) || $mappedData[$field] === '') {
                    $mappedData[$field] = 'pending';
                }
            }
        }
        
        // Add system fields
        $mappedData['formatted_id'] = 'W' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
        if (empty($mappedData['status_stage'])) {
            $mappedData['status_stage'] = 'registered';
        }
        if (empty($mappedData['status_stage_updated_at'])) {
            $mappedData['status_stage_updated_at'] = date('Y-m-d H:i:s');
        }
        if (empty($mappedData['training_status'])) {
            $mappedData['training_status'] = 'not_started';
        }
        if (empty($mappedData['language_level'])) {
            $mappedData['language_level'] = 'basic';
        }
        if (empty($mappedData['gov_approval_status'])) {
            $mappedData['gov_approval_status'] = 'pending';
        }

        // Dynamic workflow engine (country-configured).
        $actorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        ratib_workflow_apply_on_save($conn, $mappedData, null, $actorId);
        
        // Handle status - use form value or default to 'pending'
        if (isset($data['status']) && !empty($data['status'])) {
            // Map form status to database status (same mapping as update)
            $statusMap = [
                'active' => 'approved',  // Form 'active' -> DB 'approved'
                'inactive' => 'inactive',
                'pending' => 'pending',
                'suspended' => 'suspended'
            ];
            $formStatus = strtolower(trim($data['status']));
            $mappedData['status'] = $statusMap[$formStatus] ?? $formStatus ?? 'pending';
        } else {
            $mappedData['status'] = 'pending';
        }
        
        // Filter out fields that don't exist in the database
        $filteredData = [];
        foreach ($mappedData as $key => $value) {
            if (in_array($key, $columns)) {
                $filteredData[$key] = $value;
            }
        }
        
        // Build SQL
        $fields = array_keys($filteredData);
        $placeholders = array_map(function($field) { return ":$field"; }, $fields);
        
        $sql = "INSERT INTO workers (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        // Insert worker
        $stmt = $conn->prepare($sql);
        foreach ($filteredData as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $result = $stmt->execute();
        
        if (!$result) {
            throw new Exception('Failed to insert worker into database');
        }
        
        $workerId = $conn->lastInsertId();

        if (!empty($mappedData['_workflow_transition_needed'])) {
            ratib_workflow_log_stage_transition(
                $conn,
                (int)$workerId,
                (string)($mappedData['_workflow_stage_from'] ?? null),
                (string)($mappedData['_workflow_stage_to'] ?? null),
                $actorId,
                isset($mappedData['_workflow_resolved_id']) ? (int)$mappedData['_workflow_resolved_id'] : null
            );
        }
        
        // Get the newly created worker with agent and subagent info
        $query = "
            SELECT w.*,
                   w.country,
                   a.agent_name,
                   a.formatted_id as agent_formatted_id,
                   s.subagent_name,
                   s.formatted_id as subagent_formatted_id
            FROM workers w
            LEFT JOIN agents a ON w.agent_id = a.id
            LEFT JOIN subagents s ON w.subagent_id = s.id
            WHERE w.id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$workerId]);
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Commit transaction
        $conn->commit();
        
        // EN: Post-create accounting hook to ensure worker has a ledger/account mapping.
        // AR: ربط محاسبي بعد الإنشاء لضمان وجود حساب أستاذ/دليل مرتبط بالعامل.
        // Auto-create GL account in system accounts for this worker
        $workerName = $worker['full_name'] ?? $worker['worker_name'] ?? $filteredData['full_name'] ?? $filteredData['worker_name'] ?? '';
        if ($workerName) {
            require_once __DIR__ . '/../../accounting/entity-account-helper.php';
            if (function_exists('ensureEntityAccount')) {
                ensureEntityAccount($conn, 'worker', $workerId, $workerName);
            }
        }
        
        // Log history
        $helperPath = __DIR__ . '/../../core/global-history-helper.php';
        if (file_exists($helperPath)) {
            require_once $helperPath;
            if (function_exists('logGlobalHistory')) {
                logGlobalHistory('workers', $workerId, 'create', 'workers', null, $worker);
            }
        }
        
        // Send success response
        ob_clean();
        sendResponse([
            'success' => true,
            'message' => 'Worker created successfully',
            'data' => $worker
        ]);

    } catch (Exception $e) {
        // Rollback on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

} catch (CountryProfileValidationException $e) {
    error_log('Worker create country profile validation: ' . $e->getMessage());
    ob_clean();
    if (function_exists('sendResponse')) {
        sendResponse([
            'success' => false,
            'message' => $e->getMessage()
        ], 422);
    } else {
        http_response_code(422);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
} catch (Exception $e) {
    error_log('Error in create.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    ob_clean();
    if (function_exists('sendResponse')) {
        sendResponse([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
} catch (Throwable $e) {
    error_log('Fatal error in create.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    ob_clean();
    if (function_exists('sendResponse')) {
        sendResponse([
            'success' => false,
            'message' => 'Fatal error: ' . $e->getMessage()
        ], 500);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $e->getMessage()
        ]);
        exit;
    }
}