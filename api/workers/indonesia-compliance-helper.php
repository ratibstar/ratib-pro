<?php
/**
 * EN: Indonesia/BP2MI compliance layer for workers (schema, stage validation, alerts).
 * AR: طبقة امتثال إندونيسيا/BP2MI للعمال (المخطط، التحقق من المراحل، والتنبيهات).
 */

if (!function_exists('ratib_indonesia_worker_stages')) {
    function ratib_indonesia_worker_stages(): array
    {
        return [
            'registered',
            'document_ready',
            'medical_passed',
            'training_completed',
            'contract_signed',
            'govt_approved',
            'visa_issued',
            'ready_to_depart',
            'deployed',
        ];
    }
}

if (!function_exists('ratib_indonesia_required_documents')) {
    function ratib_indonesia_required_documents(): array
    {
        return [
            'passport' => 'Passport',
            'contract_signed' => 'Signed Contract',
            'medical_certificate' => 'Medical Certificate',
            'insurance' => 'Insurance',
            'training_certificate' => 'Training Certificate',
            'visa' => 'Visa',
            'exit_permit' => 'Exit Permit',
        ];
    }
}

if (!function_exists('ratib_indonesia_compliance_ensure_schema')) {
    function ratib_indonesia_compliance_ensure_schema(PDO $pdo): void
    {
        $columns = [];
        try {
            $stmt = $pdo->query('DESCRIBE workers');
            $columns = $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
        } catch (Throwable $e) {
            error_log('Indonesia compliance schema read failed: ' . $e->getMessage());
            return;
        }

        $columnDefs = [
            'status_stage' => "VARCHAR(40) DEFAULT 'registered'",
            'status_stage_updated_at' => 'DATETIME NULL',
            'medical_center_name' => 'VARCHAR(255) NULL',
            'training_status' => "VARCHAR(30) DEFAULT 'not_started'",
            'training_center' => 'VARCHAR(255) NULL',
            'language_level' => "VARCHAR(30) DEFAULT 'basic'",
            'gov_approval_status' => "VARCHAR(30) DEFAULT 'pending'",
            'approval_reference_id' => 'VARCHAR(100) NULL',
            'insurance_status' => "VARCHAR(20) DEFAULT 'pending'",
            'insurance_number' => 'VARCHAR(100) NULL',
            'insurance_file' => 'VARCHAR(255) NULL',
            'contract_signed_status' => "VARCHAR(20) DEFAULT 'pending'",
            'contract_signed_number' => 'VARCHAR(100) NULL',
            'contract_signed_file' => 'VARCHAR(255) NULL',
            'exit_permit_status' => "VARCHAR(20) DEFAULT 'pending'",
            'exit_permit_number' => 'VARCHAR(100) NULL',
            'exit_permit_file' => 'VARCHAR(255) NULL',
        ];

        foreach ($columnDefs as $column => $definition) {
            if (in_array($column, $columns, true)) {
                continue;
            }
            try {
                $pdo->exec("ALTER TABLE workers ADD COLUMN {$column} {$definition}");
                $columns[] = $column;
            } catch (Throwable $e) {
                error_log("Indonesia compliance add column {$column} failed: " . $e->getMessage());
            }
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS worker_indonesia_documents (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    worker_id INT NOT NULL,
                    document_type VARCHAR(60) NOT NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    file_path VARCHAR(255) NULL,
                    verified_by INT NULL,
                    verified_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_worker_indonesia_document (worker_id, document_type),
                    INDEX idx_worker_indonesia_documents_worker (worker_id),
                    INDEX idx_worker_indonesia_documents_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            error_log('Indonesia compliance documents table failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('ratib_indonesia_is_worker')) {
    function ratib_indonesia_is_worker(array $worker): bool
    {
        $haystack = strtolower(implode(' ', [
            (string)($worker['country'] ?? ''),
            (string)($worker['nationality'] ?? ''),
            (string)($worker['language'] ?? ''),
        ]));
        return strpos($haystack, 'indonesia') !== false
            || strpos($haystack, 'indonesian') !== false
            || preg_match('/\bidn?\b/', $haystack) === 1;
    }
}

if (!function_exists('ratib_worker_is_indonesia_payload')) {
    /**
     * True when a worker payload/snapshot belongs to Indonesia context.
     * We use country-aware exclusion so lifecycle extensions never apply to Indonesia workers.
     */
    function ratib_worker_is_indonesia_payload(array $workerLike): bool
    {
        return ratib_indonesia_is_worker($workerLike);
    }
}

if (!function_exists('ratib_worker_lifecycle_ensure_schema')) {
    /**
     * Ensure non-Indonesia lifecycle columns exist on workers table.
     * This intentionally skips Indonesia context to preserve existing Indonesia flow.
     */
    function ratib_worker_lifecycle_ensure_schema(PDO $pdo, array $workerLike = []): void
    {
        if (ratib_worker_is_indonesia_payload($workerLike)) {
            return;
        }

        $columns = [];
        try {
            $stmt = $pdo->query('DESCRIBE workers');
            $columns = $stmt ? ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: []) : [];
        } catch (Throwable $e) {
            error_log('Worker lifecycle schema read failed: ' . $e->getMessage());
            return;
        }

        $columnDefs = [
            // Updated: keep lifecycle schema complete for non-Indonesia workers only.
            'passport_number' => 'VARCHAR(120) NULL',
            'address' => 'VARCHAR(255) NULL',
            'marital_status' => 'VARCHAR(30) NULL',
            'job_title' => 'TEXT NULL',
            'passport_expiry_date' => 'DATE NULL',
            'personal_photo_url' => 'VARCHAR(255) NULL',
            'education_level' => 'VARCHAR(120) NULL',
            'work_experience' => 'TEXT NULL',
            'is_identity_verified' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'biometric_id' => 'VARCHAR(120) NULL',
            'demand_letter_id' => 'VARCHAR(120) NULL',
            'salary' => 'DECIMAL(10,2) NULL',
            'working_hours' => 'VARCHAR(60) NULL',
            'contract_duration' => 'VARCHAR(120) NULL',
            'vacation_days' => 'INT NULL',
            'accommodation_details' => 'TEXT NULL',
            'food_details' => 'TEXT NULL',
            'transport_details' => 'TEXT NULL',
            'insurance_details' => 'TEXT NULL',
            'medical_status' => "VARCHAR(20) DEFAULT 'pending'",
            'medical_check_date' => 'DATE NULL',
            'medical_center_name' => 'VARCHAR(255) NULL',
            'predeparture_training_completed' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'training_notes' => 'TEXT NULL',
            'government_registration_number' => 'VARCHAR(120) NULL',
            'worker_card_number' => 'VARCHAR(120) NULL',
            'country_compliance_primary_status' => "VARCHAR(20) DEFAULT 'pending'",
            'country_compliance_primary_file' => 'VARCHAR(255) NULL',
            'country_compliance_secondary_status' => "VARCHAR(20) DEFAULT 'pending'",
            'country_compliance_secondary_file' => 'VARCHAR(255) NULL',
            'exit_clearance_status' => "VARCHAR(30) DEFAULT 'pending'",
            'visa_status' => "VARCHAR(30) DEFAULT 'pending'",
            'work_permit_number' => 'VARCHAR(120) NULL',
            'contract_verified' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'flight_ticket_number' => 'VARCHAR(120) NULL',
            'travel_date' => 'DATE NULL',
            'insurance_policy_number' => 'VARCHAR(120) NULL',
            'contract_deployment_primary_status' => "VARCHAR(20) DEFAULT 'pending'",
            'contract_deployment_primary_file' => 'VARCHAR(255) NULL',
            'contract_deployment_secondary_status' => "VARCHAR(20) DEFAULT 'pending'",
            'contract_deployment_secondary_file' => 'VARCHAR(255) NULL',
            'contract_deployment_verification_status' => "VARCHAR(20) DEFAULT 'pending'",
            'contract_deployment_verification_file' => 'VARCHAR(255) NULL',
        ];

        foreach ($columnDefs as $column => $definition) {
            if (in_array($column, $columns, true)) {
                continue;
            }
            try {
                $pdo->exec("ALTER TABLE workers ADD COLUMN {$column} {$definition}");
                $columns[] = $column;
            } catch (Throwable $e) {
                error_log("Worker lifecycle add column {$column} failed: " . $e->getMessage());
            }
        }
    }
}

if (!function_exists('ratib_indonesia_status_approved')) {
    function ratib_indonesia_status_approved($value): bool
    {
        return in_array(strtolower(trim((string)$value)), ['approved', 'ok', 'passed', 'completed', 'issued', 'signed'], true);
    }
}

if (!function_exists('ratib_indonesia_status_rejected')) {
    function ratib_indonesia_status_rejected($value): bool
    {
        return in_array(strtolower(trim((string)$value)), ['rejected', 'not_ok', 'failed'], true);
    }
}

if (!function_exists('ratib_indonesia_document_statuses')) {
    function ratib_indonesia_document_statuses(PDO $pdo, int $workerId, array $worker): array
    {
        $docs = [];
        foreach (ratib_indonesia_required_documents() as $type => $label) {
            $docs[$type] = [
                'label' => $label,
                'status' => 'pending',
                'file_path' => null,
                'verified_by' => null,
                'verified_at' => null,
            ];
        }

        $fieldMap = [
            'passport' => ['status' => 'passport_status', 'file' => 'passport_file'],
            'medical_certificate' => ['status' => 'medical_status', 'file' => 'medical_file'],
            'training_certificate' => ['status' => 'training_certificate_status', 'file' => 'training_certificate_file'],
            'visa' => ['status' => 'visa_status', 'file' => 'visa_file'],
            'contract_signed' => ['status' => 'contract_signed_status', 'file' => 'contract_signed_file'],
            'insurance' => ['status' => 'insurance_status', 'file' => 'insurance_file'],
            'exit_permit' => ['status' => 'exit_permit_status', 'file' => 'exit_permit_file'],
        ];
        foreach ($fieldMap as $type => $fields) {
            if (isset($worker[$fields['status']])) {
                $docs[$type]['status'] = (string)$worker[$fields['status']];
            }
            if (!empty($worker[$fields['file']])) {
                $docs[$type]['file_path'] = (string)$worker[$fields['file']];
            }
        }

        try {
            $stmt = $pdo->prepare('SELECT document_type, status, file_path, verified_by, verified_at FROM worker_indonesia_documents WHERE worker_id = ?');
            $stmt->execute([$workerId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $type = (string)($row['document_type'] ?? '');
                if (!isset($docs[$type])) {
                    continue;
                }
                $docs[$type]['status'] = (string)($row['status'] ?? $docs[$type]['status']);
                $docs[$type]['file_path'] = $row['file_path'] ?? $docs[$type]['file_path'];
                $docs[$type]['verified_by'] = $row['verified_by'] ?? null;
                $docs[$type]['verified_at'] = $row['verified_at'] ?? null;
            }
        } catch (Throwable $e) {
            error_log('Indonesia compliance document read failed: ' . $e->getMessage());
        }

        return $docs;
    }
}

if (!function_exists('validateWorkerForDeployment')) {
    function validateWorkerForDeployment($workerId, ?PDO $pdo = null): array
    {
        if (!$pdo) {
            require_once __DIR__ . '/../core/Database.php';
            $pdo = Database::getInstance()->getConnection();
        }
        ratib_indonesia_compliance_ensure_schema($pdo);

        $stmt = $pdo->prepare("SELECT * FROM workers WHERE id = ? AND COALESCE(status, '') != 'deleted' LIMIT 1");
        $stmt->execute([(int)$workerId]);
        $worker = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$worker) {
            return ['ready' => false, 'not_ready' => true, 'missing_items' => ['worker_not_found'], 'alerts' => []];
        }

        $missing = [];
        $alerts = ratib_indonesia_compliance_alerts($pdo, $worker);
        $docs = ratib_indonesia_document_statuses($pdo, (int)$workerId, $worker);
        foreach ($docs as $type => $doc) {
            if (!ratib_indonesia_status_approved($doc['status']) || empty($doc['file_path'])) {
                $missing[] = 'document_' . $type;
            }
        }

        $medicalStatus = strtolower(trim((string)($worker['medical_status'] ?? 'pending')));
        if ($medicalStatus === 'failed' || $medicalStatus === 'not_ok') {
            $missing[] = 'medical_failed';
        } elseif (!in_array($medicalStatus, ['passed', 'ok', 'approved'], true)) {
            $missing[] = 'medical_not_passed';
        }

        if (strtolower(trim((string)($worker['training_status'] ?? 'not_started'))) !== 'completed') {
            $missing[] = 'training_not_completed';
        }
        if (!ratib_indonesia_status_approved($worker['contract_signed_status'] ?? null)) {
            $missing[] = 'contract_not_signed';
        }
        if (!ratib_indonesia_status_approved($worker['visa_status'] ?? null)) {
            $missing[] = 'visa_not_issued';
        }
        if (strtolower(trim((string)($worker['gov_approval_status'] ?? 'pending'))) !== 'approved') {
            $missing[] = 'government_not_approved';
        }

        return [
            'ready' => empty($missing),
            'not_ready' => !empty($missing),
            'missing_items' => array_values(array_unique($missing)),
            'alerts' => $alerts,
        ];
    }
}

if (!function_exists('ratib_indonesia_can_move_to_stage')) {
    function ratib_indonesia_can_move_to_stage(PDO $pdo, array $worker, string $targetStage): array
    {
        $stages = ratib_indonesia_worker_stages();
        if (!in_array($targetStage, $stages, true)) {
            return ['allowed' => false, 'missing_items' => ['invalid_stage']];
        }
        if (!ratib_indonesia_is_worker($worker)) {
            return ['allowed' => true, 'missing_items' => []];
        }
        if (ratib_indonesia_status_rejected($worker['medical_status'] ?? null)) {
            return ['allowed' => false, 'missing_items' => ['medical_failed']];
        }

        $targetIndex = array_search($targetStage, $stages, true);
        $missing = [];
        $validation = validateWorkerForDeployment((int)$worker['id'], $pdo);

        if ($targetIndex >= array_search('medical_passed', $stages, true)
            && in_array('medical_not_passed', $validation['missing_items'], true)) {
            $missing[] = 'medical_not_passed';
        }
        if ($targetIndex >= array_search('training_completed', $stages, true)
            && in_array('training_not_completed', $validation['missing_items'], true)) {
            $missing[] = 'training_not_completed';
        }
        if ($targetIndex >= array_search('contract_signed', $stages, true)
            && in_array('contract_not_signed', $validation['missing_items'], true)) {
            $missing[] = 'contract_not_signed';
        }
        if ($targetIndex >= array_search('govt_approved', $stages, true)
            && in_array('government_not_approved', $validation['missing_items'], true)) {
            $missing[] = 'government_not_approved';
        }
        if ($targetIndex >= array_search('visa_issued', $stages, true)
            && in_array('visa_not_issued', $validation['missing_items'], true)) {
            $missing[] = 'visa_not_issued';
        }
        if ($targetIndex >= array_search('ready_to_depart', $stages, true)) {
            $missing = array_merge($missing, $validation['missing_items']);
        }

        return ['allowed' => empty($missing), 'missing_items' => array_values(array_unique($missing))];
    }
}

if (!function_exists('ratib_indonesia_compliance_alerts')) {
    function ratib_indonesia_compliance_alerts(PDO $pdo, array $worker, int $stuckDays = 7): array
    {
        $alerts = [];
        $workerId = (int)($worker['id'] ?? 0);
        if ($workerId <= 0) {
            return $alerts;
        }

        $docs = ratib_indonesia_document_statuses($pdo, $workerId, $worker);
        foreach ($docs as $type => $doc) {
            if (!ratib_indonesia_status_approved($doc['status']) || empty($doc['file_path'])) {
                $alerts[] = ['type' => 'document_missing', 'document_type' => $type];
            }
        }
        if (ratib_indonesia_status_rejected($worker['medical_status'] ?? null)) {
            $alerts[] = ['type' => 'medical_failed'];
        }
        $stageUpdatedAt = trim((string)($worker['status_stage_updated_at'] ?? ''));
        if ($stageUpdatedAt !== '') {
            $ageSeconds = time() - strtotime($stageUpdatedAt);
            if ($ageSeconds > ($stuckDays * 86400)) {
                $alerts[] = [
                    'type' => 'stage_stuck',
                    'stage' => (string)($worker['status_stage'] ?? 'registered'),
                    'days' => (int)floor($ageSeconds / 86400),
                ];
            }
        }

        return $alerts;
    }
}

