<?php
/**
 * EN: Handles API endpoint/business logic in `api/workers/core/update.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/workers/core/update.php`.
 */
// EN: Buffer output to keep API responses strictly JSON.
// AR: تخزين المخرجات مؤقتاً لضمان أن الاستجابة تكون JSON فقط.
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json');

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
require_once __DIR__ . '/../indonesia-compliance-helper.php';
require_once __DIR__ . '/../workflow-engine.php';

// EN: Main update workflow with validation, normalization, persistence, and history logging.
// AR: مسار التحديث الرئيسي: تحقق، توحيد بيانات، حفظ، وتسجيل تاريخ التغيير.
try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    

    if (!$id) {
        throw new Exception('Worker ID is required');
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();
    ratib_indonesia_compliance_ensure_schema($conn);
    ratib_workflow_ensure_schema($conn);
    $describeStmt = $conn->query("DESCRIBE workers");
    $workerColumns = $describeStmt->fetchAll(PDO::FETCH_COLUMN);
    $workerColumnLookup = array_fill_keys($workerColumns, true);
    
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
        // Continue anyway - might be VARCHAR instead of ENUM, or table structure is different
    }

    // EN: Load current worker snapshot first for existence check and audit diff.
    // AR: تحميل الحالة الحالية للعامل أولاً للتحقق من الوجود وحساب فرق التغييرات.
    // Check if worker exists and get old data for history
    $stmt = $conn->prepare("SELECT * FROM workers WHERE id = ? AND status != 'deleted'");
    $stmt->execute([$id]);
    $oldWorker = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$oldWorker) {
        throw new Exception('Worker not found');
    }

    $mergedWorkerContext = array_merge($oldWorker, [
        'country' => (string)($data['country'] ?? ($oldWorker['country'] ?? '')),
        'nationality' => (string)($data['nationality'] ?? ($oldWorker['nationality'] ?? '')),
        'language' => (string)($data['language'] ?? ($oldWorker['language'] ?? '')),
    ]);
    $isIndonesiaWorker = ratib_worker_is_indonesia_payload($mergedWorkerContext);
    ratib_worker_lifecycle_ensure_schema($conn, $mergedWorkerContext);
    $actorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    ratib_workflow_apply_on_save($conn, $data, $oldWorker, $actorId);

    // Lifecycle-specific validation removed to restore previous Ratib Pro flow.

    // Stage progression is enforced by workflow-engine.php for all countries.

    // EN: Uniqueness guards for critical identity documents.
    // AR: حماية التفرد لوثائق الهوية الأساسية.
    // Check for duplicate passport/identity numbers
    if (!empty($data['passport_number'])) {
        $stmt = $conn->prepare("SELECT id FROM workers WHERE passport_number = ? AND id != ?");
        $stmt->execute([$data['passport_number'], $id]);
        if ($stmt->fetch()) {
            throw new Exception('Passport number already exists');
        }
    }

    if (!empty($data['identity_number'])) {
        $stmt = $conn->prepare("SELECT id FROM workers WHERE identity_number = ? AND id != ?");
        $stmt->execute([$data['identity_number'], $id]);
        if ($stmt->fetch()) {
            throw new Exception('Identity number already exists');
        }
    }

    // EN: Dynamic field update allows partial edits from frontend forms.
    // AR: التحديث الديناميكي للحقول يسمح بتعديلات جزئية من نماذج الواجهة.
    // Build update query dynamically
    $updateFields = [];
    $params = [];
    
    $allowedFields = [
        'worker_name', 'agent_id', 'subagent_id', 'nationality', 'gender',
        'identity_number', 'identity_date', 'passport_number', 'passport_date', 
        'police_number', 'police_date', 'medical_number', 'medical_date',
        'visa_number', 'visa_date', 'ticket_number', 'ticket_date',
        'training_certificate_number', 'training_certificate_date',
        'identity_status', 'passport_status',
        'police_status', 'medical_status', 'visa_status', 'ticket_status', 'training_certificate_status', 
        'contract_signed_status', 'insurance_status', 'exit_permit_status',
        'contract_signed_number', 'insurance_number', 'exit_permit_number',
        'status_stage', 'training_status', 'training_center', 'language_level',
        'medical_center_name', 'gov_approval_status', 'approval_reference_id',
        'emergency_name', 'emergency_relation', 'emergency_phone', 'emergency_address', 
        'age', 'marital_status', 'email', 'contact_number', 'country', 'city', 'address', 
        'language', 'status', 'local_experience', 'abroad_experience', 'qualification', 
        'skills', 'job_title', 'date_of_birth', 'birth_date', 'passport_expiry', 
        'arrival_date', 'departure_date', 'place_of_birth', 'passport_expiry_date',
        'personal_photo_url', 'education_level', 'work_experience', 'is_identity_verified',
        'biometric_id', 'demand_letter_id', 'salary', 'working_hours', 'contract_duration',
        'vacation_days', 'accommodation_details', 'food_details', 'transport_details',
        'insurance_details', 'medical_check_date', 'predeparture_training_completed',
        'training_notes', 'government_registration_number', 'worker_card_number',
        'exit_clearance_status', 'work_permit_number', 'contract_verified',
        'flight_ticket_number', 'travel_date', 'insurance_policy_number',
        'country_compliance_primary_status', 'country_compliance_primary_file',
        'country_compliance_secondary_status', 'country_compliance_secondary_file',
        'contract_deployment_primary_status', 'contract_deployment_primary_file',
        'contract_deployment_secondary_status', 'contract_deployment_secondary_file',
        'contract_deployment_verification_status', 'contract_deployment_verification_file',
        'workflow_id', 'workflow_version_id', 'current_stage', 'stage_completed'
    ];
    $allowedFields = array_values(array_filter($allowedFields, function ($field) use ($workerColumnLookup) {
        return isset($workerColumnLookup[$field]);
    }));

    foreach ($allowedFields as $field) {
        // Map frontend field names to database field names
        $frontendField = $field === 'worker_name' ? 'full_name' : 
                        ($field === 'contact_number' ? 'phone' : $field);
        
        if (array_key_exists($frontendField, $data)) {
            $updateFields[] = "$field = ?";
            
            // Handle empty values for nullable fields
            $value = $data[$frontendField];
            
            // Special handling for job_title - ensure it's stored as comma-separated string
            if ($field === 'job_title') {
                if (is_array($value)) {
                    $value = implode(',', array_filter($value));
                } else if (empty($value)) {
                    $value = null;
                }
                error_log("Job title value being saved: " . $value);
            }
            
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
                'stage_completed', 'workflow_version_id'
            ];
            
            $dateFields = ['date_of_birth', 'birth_date', 'passport_expiry', 'arrival_date', 'departure_date', 'medical_date', 'training_certificate_date', 'passport_expiry_date', 'medical_check_date', 'travel_date'];
            
            // Special handling for status field - never set to null, always use the provided value or default to 'pending'
            if ($field === 'status') {
                if ($value === '' || $value === null) {
                    $params[] = 'pending';
                } else {
                    $params[] = $value;
                }
            } else if (in_array($field, $nullableFields) && $value === '') {
                $params[] = null;
            } else if (in_array($field, $dateFields) && ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00')) {
                $params[] = null;
            } else {
                $params[] = $value;
            }
        } else if ($field === 'status' && isset($workerColumnLookup['status'])) {
            // If status field is missing from data, add it with default value
            $updateFields[] = "status = ?";
            $params[] = 'pending';
        }
    }

    // EN: Always stamp modification time for reliable audit trails.
    // AR: تحديث وقت التعديل دائماً لضمان تتبع دقيق في سجلات المراجعة.
    // Always update the updated_at timestamp
    if (isset($workerColumnLookup['updated_at'])) {
        $updateFields[] = "updated_at = NOW()";
    }
    if (array_key_exists('status_stage', $data) && isset($workerColumnLookup['status_stage_updated_at'])) {
        $updateFields[] = "status_stage_updated_at = NOW()";
    }

    if (empty($updateFields)) {
        throw new Exception('No valid fields to update');
    }

    // Add id to params
    $params[] = $id;

    // Update worker
    $sql = "UPDATE workers SET " . implode(', ', $updateFields) . " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    // EN: Reload enriched worker row for immediate UI refresh payload.
    // AR: إعادة جلب سجل العامل مع البيانات المرتبطة لإرسال نتيجة جاهزة للواجهة.
    // Get updated worker data
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
    $stmt->execute([$id]);
    $worker = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log history
    $helperPath = __DIR__ . '/../../core/global-history-helper.php';
    if (file_exists($helperPath)) {
        require_once $helperPath;
        if (function_exists('logGlobalHistory')) {
            logGlobalHistory('workers', $id, 'update', 'workers', $oldWorker, $worker);
        }
    }

    ob_clean();
    sendResponse([
        'success' => true,
        'message' => 'Worker updated successfully',
        'data' => $worker
    ]);

} catch (Exception $e) {
    error_log("Worker update error: " . $e->getMessage());
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
    error_log("Worker update fatal error: " . $e->getMessage());
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
