<?php
/**
 * Dynamic worker workflow engine (country-configured, no hardcoded branching).
 */

if (!function_exists('ratib_workflow_stage_keys')) {
    function ratib_workflow_stage_keys(): array
    {
        return [
            'identity',
            'passport',
            'police',
            'medical',
            'technical_training',
            'contract',
            'government',
            'work_permit',
            'visa',
            'predeparture_training',
            'ticket',
            'travel',
        ];
    }
}

if (!function_exists('ratib_workflow_ensure_schema')) {
    function ratib_workflow_ensure_schema(PDO $pdo): void
    {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS workflow_definitions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                workflow_key VARCHAR(80) NOT NULL UNIQUE,
                country_name VARCHAR(120) NULL,
                country_code VARCHAR(12) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                enforce_stage_order TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_workflow_country_code (country_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS workflow_stages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                workflow_id INT NOT NULL,
                stage_key VARCHAR(80) NOT NULL,
                stage_order INT NOT NULL,
                stage_label VARCHAR(120) NOT NULL,
                stage_description TEXT NULL,
                required_fields_json TEXT NULL,
                fields_config_json TEXT NULL,
                stage_weight DECIMAL(8,3) NOT NULL DEFAULT 1.000,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_workflow_stage (workflow_id, stage_key),
                UNIQUE KEY uniq_workflow_order (workflow_id, stage_order),
                INDEX idx_workflow_stages_workflow (workflow_id),
                CONSTRAINT fk_workflow_stages_definition FOREIGN KEY (workflow_id) REFERENCES workflow_definitions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS workflow_rules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                workflow_id INT NOT NULL,
                stage_key VARCHAR(80) NOT NULL,
                rule_type VARCHAR(60) NOT NULL,
                rule_config_json TEXT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_workflow_rules_workflow_stage (workflow_id, stage_key),
                CONSTRAINT fk_workflow_rules_definition FOREIGN KEY (workflow_id) REFERENCES workflow_definitions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS workflow_versions (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                workflow_id INT NOT NULL,
                version_number INT NOT NULL,
                config_hash VARCHAR(64) NOT NULL,
                snapshot_json LONGTEXT NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 0,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_workflow_version_num (workflow_id, version_number),
                UNIQUE KEY uniq_workflow_version_hash (workflow_id, config_hash),
                INDEX idx_workflow_versions_active (workflow_id, is_active),
                CONSTRAINT fk_workflow_versions_definition FOREIGN KEY (workflow_id) REFERENCES workflow_definitions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS workflow_templates (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                template_name VARCHAR(120) NOT NULL UNIQUE,
                base_country_group VARCHAR(120) NULL,
                template_json LONGTEXT NOT NULL,
                created_by INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS worker_stage_logs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                worker_id INT NOT NULL,
                stage_from VARCHAR(80) NULL,
                stage_to VARCHAR(80) NULL,
                changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                changed_by INT NULL,
                workflow_id INT NULL,
                INDEX idx_worker_stage_logs_worker (worker_id),
                INDEX idx_worker_stage_logs_changed_at (changed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Backward-compatible additive ALTERs for already-existing tables.
        try { $pdo->exec("ALTER TABLE workflow_definitions ADD COLUMN country_code VARCHAR(12) NULL"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE workflow_definitions ADD UNIQUE KEY uniq_workflow_country_code (country_code)"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE workflow_stages ADD COLUMN stage_description TEXT NULL"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE workflow_stages ADD COLUMN fields_config_json TEXT NULL"); } catch (Throwable $e) {}
        try { $pdo->exec("ALTER TABLE workflow_stages ADD COLUMN stage_weight DECIMAL(8,3) NOT NULL DEFAULT 1.000"); } catch (Throwable $e) {}

        // Add worker linkage fields if missing.
        $columns = $pdo->query("DESCRIBE workers")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $workerAdd = [
            'workflow_id' => 'INT NULL',
            'workflow_version_id' => 'BIGINT NULL',
            'current_stage' => "VARCHAR(80) NULL",
            'stage_completed' => 'TEXT NULL',
        ];
        foreach ($workerAdd as $col => $def) {
            if (!in_array($col, $columns, true)) {
                try {
                    $pdo->exec("ALTER TABLE workers ADD COLUMN {$col} {$def}");
                } catch (Throwable $e) {
                    error_log("workflow-engine add worker column {$col} failed: " . $e->getMessage());
                }
            }
        }

        ratib_workflow_seed_default($pdo);
        ratib_workflow_ensure_version_snapshot($pdo, (int)$pdo->query("SELECT id FROM workflow_definitions WHERE workflow_key = 'default_global' LIMIT 1")->fetchColumn(), null);
    }
}

if (!function_exists('ratib_workflow_seed_default')) {
    function ratib_workflow_seed_default(PDO $pdo): void
    {
        // Ensure default workflow shell exists; rules are DB-driven and not hardcoded here.
        $pdo->prepare(
            "INSERT INTO workflow_definitions (workflow_key, country_name, country_code, is_active, enforce_stage_order)
             VALUES ('default_global', '*', 'DEFAULT', 1, 1)
             ON DUPLICATE KEY UPDATE is_active = VALUES(is_active), country_code = VALUES(country_code)"
        )->execute();

        $workflowId = (int)$pdo->query("SELECT id FROM workflow_definitions WHERE workflow_key = 'default_global' LIMIT 1")->fetchColumn();
        if ($workflowId <= 0) {
            return;
        }

        $stages = ratib_workflow_stage_keys();
        foreach ($stages as $index => $stageKey) {
            $required = ratib_workflow_default_required_fields($stageKey);
            $fieldsConfig = [];
            foreach ($required as $fieldKey) {
                $fieldsConfig[$fieldKey] = ['required' => true];
            }
            $stmt = $pdo->prepare(
                "INSERT INTO workflow_stages (workflow_id, stage_key, stage_order, stage_label, stage_description, required_fields_json, fields_config_json, stage_weight)
                 VALUES (?, ?, ?, ?, NULL, ?, ?, 1.000)
                 ON DUPLICATE KEY UPDATE
                     stage_order = VALUES(stage_order),
                     stage_label = VALUES(stage_label),
                     required_fields_json = COALESCE(workflow_stages.required_fields_json, VALUES(required_fields_json)),
                     fields_config_json = COALESCE(workflow_stages.fields_config_json, VALUES(fields_config_json))"
            );
            $stmt->execute([
                $workflowId,
                $stageKey,
                $index + 1,
                ucwords(str_replace('_', ' ', $stageKey)),
                json_encode($required, JSON_UNESCAPED_UNICODE),
                json_encode($fieldsConfig, JSON_UNESCAPED_UNICODE)
            ]);
        }

        // Label normalization migration (presentation only, no stage_key change).
        $norm = $pdo->prepare(
            "UPDATE workflow_stages
             SET stage_label = CASE
                 WHEN stage_key = 'contract' THEN 'Contract'
                 WHEN stage_key = 'travel' THEN 'Travel & Departure'
                 WHEN stage_key = 'predeparture_training' THEN 'Pre-Departure Training'
                 WHEN stage_key = 'government' THEN 'Government Registration'
                 WHEN stage_key = 'work_permit' THEN 'Work Permit'
                 ELSE stage_label
             END
             WHERE workflow_id = ?"
        );
        $norm->execute([$workflowId]);
    }
}

if (!function_exists('ratib_workflow_default_required_fields')) {
    function ratib_workflow_default_required_fields(string $stageKey): array
    {
        $map = [
            'identity' => ['identity_number', 'identity_date'],
            'passport' => ['passport_number', 'passport_date'],
            'police' => ['police_number', 'police_date'],
            'medical' => ['medical_number', 'medical_date'],
            'technical_training' => ['training_certificate_number', 'training_certificate_date'],
            'contract' => ['contract_duration'],
            'government' => ['government_registration_number'],
            'work_permit' => ['work_permit_number'],
            'visa' => ['visa_number', 'visa_date'],
            'predeparture_training' => ['predeparture_training_completed'],
            'ticket' => ['ticket_number', 'ticket_date'],
            'travel' => ['travel_date'],
        ];
        return $map[$stageKey] ?? [];
    }
}

if (!function_exists('ratib_workflow_resolve_definition')) {
    function ratib_workflow_resolve_definition(PDO $pdo, ?string $countryName): array
    {
        $country = trim((string)$countryName);
        if ($country !== '') {
            $stmt = $pdo->prepare("SELECT * FROM workflow_definitions WHERE is_active = 1 AND UPPER(country_code) = UPPER(?) LIMIT 1");
            $stmt->execute([$country]);
            $byCode = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($byCode) return $byCode;

            $stmt = $pdo->prepare("SELECT * FROM workflow_definitions WHERE is_active = 1 AND LOWER(country_name) = LOWER(?) LIMIT 1");
            $stmt->execute([$country]);
            $byName = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($byName) return $byName;
        }

        $default = $pdo->query("SELECT * FROM workflow_definitions WHERE is_active = 1 AND (workflow_key = 'default_global' OR country_code = 'DEFAULT') LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($default) {
            return $default;
        }
        throw new Exception('No active workflow definition available');
    }
}

if (!function_exists('ratib_workflow_get_stage_map')) {
    function ratib_workflow_get_stage_map(PDO $pdo, int $workflowId): array
    {
        $stmt = $pdo->prepare("SELECT stage_key, stage_order, stage_label, stage_description, required_fields_json, fields_config_json, stage_weight FROM workflow_stages WHERE workflow_id = ? ORDER BY stage_order ASC");
        $stmt->execute([$workflowId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $required = json_decode((string)($row['required_fields_json'] ?? '[]'), true) ?: [];
            $fieldsConfig = json_decode((string)($row['fields_config_json'] ?? '[]'), true);
            if (!is_array($fieldsConfig)) $fieldsConfig = [];
            $out[] = [
                'stage_key' => (string)$row['stage_key'],
                'stage_order' => (int)$row['stage_order'],
                'stage_label' => (string)($row['stage_label'] ?? ''),
                'stage_description' => (string)($row['stage_description'] ?? ''),
                'required_fields' => $required,
                'fields_config' => $fieldsConfig,
                'stage_weight' => (float)($row['stage_weight'] ?? 1),
            ];
        }
        return $out;
    }
}

if (!function_exists('ratib_workflow_get_rules')) {
    function ratib_workflow_get_rules(PDO $pdo, int $workflowId, string $stageKey): array
    {
        $stmt = $pdo->prepare("SELECT rule_type, rule_config_json FROM workflow_rules WHERE workflow_id = ? AND stage_key = ?");
        $stmt->execute([$workflowId, $stageKey]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static function ($r) {
            return [
                'rule_type' => (string)$r['rule_type'],
                'config' => json_decode((string)$r['rule_config_json'], true) ?: [],
            ];
        }, $rules);
    }
}

if (!function_exists('ratib_workflow_collect_snapshot')) {
    function ratib_workflow_collect_snapshot(PDO $pdo, int $workflowId): array
    {
        $defStmt = $pdo->prepare("SELECT * FROM workflow_definitions WHERE id = ? LIMIT 1");
        $defStmt->execute([$workflowId]);
        $definition = $defStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $stagesStmt = $pdo->prepare("SELECT * FROM workflow_stages WHERE workflow_id = ? ORDER BY stage_order ASC");
        $stagesStmt->execute([$workflowId]);
        $stages = $stagesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $rulesStmt = $pdo->prepare("SELECT * FROM workflow_rules WHERE workflow_id = ? ORDER BY stage_key ASC, id ASC");
        $rulesStmt->execute([$workflowId]);
        $rules = $rulesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'definition' => $definition,
            'stages' => $stages,
            'rules' => $rules,
        ];
    }
}

if (!function_exists('ratib_workflow_validate_definition')) {
    function ratib_workflow_validate_definition(PDO $pdo, int $workflowId): array
    {
        $errors = [];
        $stages = ratib_workflow_get_stage_map($pdo, $workflowId);
        if (empty($stages)) {
            return ['valid' => false, 'errors' => ['missing_stages']];
        }

        $stageKeys = array_column($stages, 'stage_key');
        foreach ($stageKeys as $stageKey) {
            if (trim((string)$stageKey) === '') {
                $errors[] = 'missing_stage_key';
            }
        }
        $dupes = array_keys(array_filter(array_count_values($stageKeys), static function ($count) { return $count > 1; }));
        foreach ($dupes as $dupKey) {
            $errors[] = 'duplicate_stage_key_' . $dupKey;
        }
        $orders = array_column($stages, 'stage_order');
        sort($orders);
        $expected = range(1, count($orders));
        if ($orders !== $expected) {
            $errors[] = 'stage_order_inconsistent';
        }

        foreach ($stages as $stage) {
            if (!isset($stage['fields_config']) || !is_array($stage['fields_config'])) {
                $errors[] = 'missing_fields_config_json_' . $stage['stage_key'];
                continue;
            }
            $requiredFields = is_array($stage['required_fields'] ?? null) ? $stage['required_fields'] : [];
            foreach ($requiredFields as $fieldKey) {
                if (!array_key_exists($fieldKey, $stage['fields_config'])) {
                    $errors[] = 'required_field_not_in_fields_config_' . $stage['stage_key'] . '_' . $fieldKey;
                }
            }
        }
        $knownFields = [];
        foreach ($stages as $stage) {
            $cfg = is_array($stage['fields_config'] ?? null) ? $stage['fields_config'] : [];
            foreach (array_keys($cfg) as $fieldName) {
                $knownFields[(string)$fieldName] = true;
            }
        }

        $rulesStmt = $pdo->prepare("SELECT stage_key, rule_type, rule_config_json FROM workflow_rules WHERE workflow_id = ?");
        $rulesStmt->execute([$workflowId]);
        $rules = $rulesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $supportedRuleTypes = ['require_stage_completed', 'require_field_value', 'require_many', 'block_if', 'auto_set_status', 'compute_status'];

        foreach ($rules as $rule) {
            $stageKey = (string)($rule['stage_key'] ?? '');
            if (!in_array($stageKey, $stageKeys, true)) {
                $errors[] = 'rule_stage_missing_' . $stageKey;
            }
            $decoded = json_decode((string)($rule['rule_config_json'] ?? ''), true);
            if (!is_array($decoded)) {
                $errors[] = 'invalid_rule_config_json_' . $stageKey;
                continue;
            }
            $type = strtolower(trim((string)($decoded['type'] ?? $rule['rule_type'] ?? '')));
            if (!in_array($type, $supportedRuleTypes, true)) {
                $errors[] = 'unsupported_rule_type_' . $type;
                continue;
            }

            if ($type === 'require_stage_completed') {
                $dep = (string)($decoded['stage'] ?? '');
                if ($dep === '' || !in_array($dep, $stageKeys, true)) {
                    $errors[] = 'missing_stage_dependency_' . $stageKey;
                }
            }

            if ($type === 'require_many') {
                $conds = $decoded['conditions'] ?? null;
                if (!is_array($conds) || empty($conds)) {
                    $errors[] = 'invalid_require_many_' . $stageKey;
                } else {
                    foreach ($conds as $cond) {
                        $field = (string)($cond['field'] ?? '');
                        if ($field !== '' && empty($knownFields[$field])) {
                            $errors[] = 'broken_rule_field_reference_' . $stageKey . '_' . $field;
                        }
                    }
                }
            }
            if (in_array($type, ['require_field_value', 'block_if', 'compute_status', 'auto_set_status'], true)) {
                $field = (string)($decoded['field'] ?? '');
                if ($field !== '' && empty($knownFields[$field])) {
                    $errors[] = 'broken_rule_field_reference_' . $stageKey . '_' . $field;
                }
            }
        }

        return ['valid' => empty($errors), 'errors' => array_values(array_unique($errors))];
    }
}

if (!function_exists('validate_workflow_definition')) {
    function validate_workflow_definition(PDO $pdo, int $workflowId): array
    {
        return ratib_workflow_validate_definition($pdo, $workflowId);
    }
}

if (!function_exists('ratib_workflow_ensure_version_snapshot')) {
    function ratib_workflow_ensure_version_snapshot(PDO $pdo, int $workflowId, ?int $createdBy): ?int
    {
        if ($workflowId <= 0) return null;
        $snapshot = ratib_workflow_collect_snapshot($pdo, $workflowId);
        $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
        $hash = hash('sha256', (string)$snapshotJson);

        $existingStmt = $pdo->prepare("SELECT id FROM workflow_versions WHERE workflow_id = ? AND config_hash = ? LIMIT 1");
        $existingStmt->execute([$workflowId, $hash]);
        $existingId = $existingStmt->fetchColumn();
        if ($existingId) {
            $pdo->prepare("UPDATE workflow_versions SET is_active = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE workflow_id = ?")->execute([(int)$existingId, $workflowId]);
            return (int)$existingId;
        }

        $nextNumStmt = $pdo->prepare("SELECT COALESCE(MAX(version_number), 0) + 1 FROM workflow_versions WHERE workflow_id = ?");
        $nextNumStmt->execute([$workflowId]);
        $nextNum = (int)$nextNumStmt->fetchColumn();

        $insert = $pdo->prepare(
            "INSERT INTO workflow_versions (workflow_id, version_number, config_hash, snapshot_json, is_active, created_by)
             VALUES (?, ?, ?, ?, 1, ?)"
        );
        $insert->execute([$workflowId, $nextNum, $hash, $snapshotJson, $createdBy]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE workflow_versions SET is_active = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE workflow_id = ?")->execute([$newId, $workflowId]);
        return $newId;
    }
}

if (!function_exists('ratib_workflow_activate_version')) {
    function ratib_workflow_activate_version(PDO $pdo, int $workflowId, int $versionId): bool
    {
        if ($workflowId <= 0 || $versionId <= 0) return false;
        $exists = $pdo->prepare("SELECT id FROM workflow_versions WHERE workflow_id = ? AND id = ? LIMIT 1");
        $exists->execute([$workflowId, $versionId]);
        if (!$exists->fetchColumn()) return false;
        $pdo->prepare("UPDATE workflow_versions SET is_active = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE workflow_id = ?")->execute([$versionId, $workflowId]);
        return true;
    }
}

if (!function_exists('ratib_workflow_clone_template')) {
    function ratib_workflow_clone_template(PDO $pdo, int $templateId, string $workflowKey, string $countryCode, ?int $createdBy = null): int
    {
        $stmt = $pdo->prepare("SELECT template_json, base_country_group FROM workflow_templates WHERE id = ? LIMIT 1");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$template) throw new Exception('Template not found');
        $templateJson = json_decode((string)$template['template_json'], true);
        if (!is_array($templateJson)) throw new Exception('Template JSON invalid');

        $ins = $pdo->prepare(
            "INSERT INTO workflow_definitions (workflow_key, country_name, country_code, is_active, enforce_stage_order)
             VALUES (?, ?, ?, 1, ?)"
        );
        $ins->execute([$workflowKey, (string)($template['base_country_group'] ?? $countryCode), $countryCode, (int)($templateJson['enforce_stage_order'] ?? 1)]);
        $workflowId = (int)$pdo->lastInsertId();

        $stages = is_array($templateJson['stages'] ?? null) ? $templateJson['stages'] : [];
        $rules = is_array($templateJson['rules'] ?? null) ? $templateJson['rules'] : [];

        $insStage = $pdo->prepare(
            "INSERT INTO workflow_stages (workflow_id, stage_key, stage_order, stage_label, required_fields_json, fields_config_json, stage_weight)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($stages as $stage) {
            $insStage->execute([
                $workflowId,
                (string)($stage['stage_key'] ?? ''),
                (int)($stage['stage_order'] ?? 0),
                (string)($stage['stage_label'] ?? ''),
                json_encode($stage['required_fields_json'] ?? [], JSON_UNESCAPED_UNICODE),
                json_encode($stage['fields_config_json'] ?? [], JSON_UNESCAPED_UNICODE),
                (float)($stage['stage_weight'] ?? 1.0),
            ]);
        }

        $insRule = $pdo->prepare(
            "INSERT INTO workflow_rules (workflow_id, stage_key, rule_type, rule_config_json)
             VALUES (?, ?, ?, ?)"
        );
        foreach ($rules as $rule) {
            $insRule->execute([
                $workflowId,
                (string)($rule['stage_key'] ?? ''),
                (string)($rule['rule_type'] ?? ''),
                json_encode($rule['rule_config_json'] ?? $rule['rule_config'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);
        }

        ratib_workflow_ensure_version_snapshot($pdo, $workflowId, $createdBy);
        return $workflowId;
    }
}

if (!function_exists('ratib_workflow_context_from_payload')) {
    function ratib_workflow_context_from_payload(array $payload, ?array $existingWorker = null): array
    {
        $merged = array_merge($existingWorker ?? [], $payload);
        $jobType = '';
        if (!empty($merged['job_type'])) {
            $jobType = (string)$merged['job_type'];
        } elseif (!empty($merged['job_title'])) {
            $jobType = is_array($merged['job_title']) ? implode(',', $merged['job_title']) : (string)$merged['job_title'];
        }
        $country = (string)($merged['country'] ?? '');
        $nationality = (string)($merged['nationality'] ?? '');
        $employerCountry = (string)($merged['employer_country'] ?? $merged['destination_country'] ?? '');
        $riskLevel = (string)($merged['risk_level'] ?? '');

        if ($riskLevel === '') {
            $riskScore = 0.25;
            if ($country !== '' && $employerCountry !== '' && strtolower($country) !== strtolower($employerCountry)) {
                $riskScore += 0.35;
            }
            if ($nationality === '') $riskScore += 0.2;
            if ($jobType === '') $riskScore += 0.2;
            $riskLevel = $riskScore >= 0.7 ? 'high' : ($riskScore >= 0.4 ? 'medium' : 'low');
        }

        return [
            'country' => $country,
            'nationality' => $nationality,
            'job_type' => $jobType,
            'employer_country' => $employerCountry,
            'risk_level' => $riskLevel,
        ];
    }
}

if (!function_exists('ratib_workflow_resolve_definition_selector')) {
    function ratib_workflow_resolve_definition_selector(PDO $pdo, $selector): ?array
    {
        if (is_numeric($selector)) {
            $stmt = $pdo->prepare("SELECT * FROM workflow_definitions WHERE is_active = 1 AND id = ? LIMIT 1");
            $stmt->execute([(int)$selector]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        if (is_string($selector) && trim($selector) !== '') {
            $text = trim($selector);
            $stmt = $pdo->prepare("SELECT * FROM workflow_definitions WHERE is_active = 1 AND workflow_key = ? LIMIT 1");
            $stmt->execute([$text]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) return $row;

            $stmt = $pdo->prepare("SELECT * FROM workflow_definitions WHERE is_active = 1 AND UPPER(country_code) = UPPER(?) LIMIT 1");
            $stmt->execute([$text]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        }

        if (is_array($selector)) {
            if (!empty($selector['id'])) {
                $row = ratib_workflow_resolve_definition_selector($pdo, (int)$selector['id']);
                if ($row) return $row;
            }
            if (!empty($selector['workflow_key'])) {
                $row = ratib_workflow_resolve_definition_selector($pdo, (string)$selector['workflow_key']);
                if ($row) return $row;
            }
            if (!empty($selector['country_code'])) {
                $row = ratib_workflow_resolve_definition_selector($pdo, (string)$selector['country_code']);
                if ($row) return $row;
            }
        }

        return null;
    }
}

if (!function_exists('ratib_workflow_recommend_template')) {
    /**
     * Intelligent template recommendation layer (additive, non-breaking).
     * Returns: recommended_template_id, confidence_score, adjusted_rules, explanation, workflow_definition_id.
     */
    function ratib_workflow_recommend_template(PDO $pdo, array $context): array
    {
        $country = strtolower(trim((string)($context['country'] ?? '')));
        $nationality = strtolower(trim((string)($context['nationality'] ?? '')));
        $jobType = strtolower(trim((string)($context['job_type'] ?? '')));
        $employerCountry = strtolower(trim((string)($context['employer_country'] ?? '')));
        $riskLevel = strtolower(trim((string)($context['risk_level'] ?? 'medium')));

        $templatesStmt = $pdo->query("SELECT id, template_name, base_country_group, template_json FROM workflow_templates ORDER BY id ASC");
        $templates = $templatesStmt ? ($templatesStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

        $best = [
            'recommended_template_id' => null,
            'confidence_score' => 0.0,
            'adjusted_rules' => [],
            'explanation' => ['No matching template found; using default workflow.'],
            'workflow_definition_id' => null,
        ];

        foreach ($templates as $template) {
            $templateJson = json_decode((string)($template['template_json'] ?? ''), true);
            if (!is_array($templateJson)) {
                continue;
            }

            $score = 0.2;
            $explanation = [];
            $adjustedRules = is_array($templateJson['rule_overrides'] ?? null) ? $templateJson['rule_overrides'] : [];

            $baseGroup = strtolower(trim((string)($template['base_country_group'] ?? '')));
            if ($baseGroup !== '' && ($baseGroup === $country || $baseGroup === $employerCountry)) {
                $score += 0.25;
                $explanation[] = 'Country group matches template base.';
            }

            $countryMods = is_array($templateJson['country_modifiers'] ?? null) ? $templateJson['country_modifiers'] : [];
            if ($country !== '' && isset($countryMods[$country]) && is_array($countryMods[$country])) {
                $score += 0.3;
                $explanation[] = 'Country modifier rules applied.';
                if (is_array($countryMods[$country]['rule_overrides'] ?? null)) {
                    $adjustedRules = array_merge($adjustedRules, $countryMods[$country]['rule_overrides']);
                }
            }

            $jobMods = is_array($templateJson['job_type_modifiers'] ?? null) ? $templateJson['job_type_modifiers'] : [];
            if ($jobType !== '' && isset($jobMods[$jobType]) && is_array($jobMods[$jobType])) {
                $score += 0.25;
                $explanation[] = 'Job-type modifier rules applied.';
                if (is_array($jobMods[$jobType]['rule_overrides'] ?? null)) {
                    $adjustedRules = array_merge($adjustedRules, $jobMods[$jobType]['rule_overrides']);
                }
            }

            $match = is_array($templateJson['match'] ?? null) ? $templateJson['match'] : [];
            $nationalities = is_array($match['nationalities'] ?? null) ? array_map('strtolower', $match['nationalities']) : [];
            if ($nationality !== '' && !empty($nationalities) && in_array($nationality, $nationalities, true)) {
                $score += 0.1;
                $explanation[] = 'Nationality matched template preferences.';
            }

            $riskProfiles = is_array($templateJson['risk_profiles'] ?? null) ? $templateJson['risk_profiles'] : [];
            if ($riskLevel !== '' && isset($riskProfiles[$riskLevel]) && is_array($riskProfiles[$riskLevel])) {
                $score += 0.1;
                $explanation[] = 'Risk-level profile adjustments applied.';
                if (is_array($riskProfiles[$riskLevel]['rule_overrides'] ?? null)) {
                    $adjustedRules = array_merge($adjustedRules, $riskProfiles[$riskLevel]['rule_overrides']);
                }
            }

            $baseWorkflowSelector = $templateJson['base_workflow'] ?? null;
            $resolvedWorkflow = ratib_workflow_resolve_definition_selector($pdo, $baseWorkflowSelector);

            if ($resolvedWorkflow) {
                $score += 0.05;
            } else {
                $score -= 0.25;
                $explanation[] = 'Base workflow selector could not be resolved.';
            }

            $score = max(0.0, min(1.0, $score));
            if ($score > $best['confidence_score']) {
                $best = [
                    'recommended_template_id' => (int)$template['id'],
                    'confidence_score' => $score,
                    'adjusted_rules' => $adjustedRules,
                    'explanation' => !empty($explanation) ? $explanation : ['Template matched with baseline confidence.'],
                    'workflow_definition_id' => $resolvedWorkflow ? (int)$resolvedWorkflow['id'] : null,
                ];
            }
        }

        return $best;
    }
}

if (!function_exists('ratib_workflow_to_bool_map')) {
    function ratib_workflow_to_bool_map($raw): array
    {
        if (is_array($raw)) {
            $arr = $raw;
        } else {
            $arr = json_decode((string)$raw, true);
            if (!is_array($arr)) {
                $arr = [];
            }
        }
        $out = [];
        foreach ($arr as $k => $v) {
            $out[(string)$k] = filter_var($v, FILTER_VALIDATE_BOOLEAN) || $v === 1 || $v === '1';
        }
        return $out;
    }
}

if (!function_exists('ratib_workflow_is_value_allowed')) {
    function ratib_workflow_is_value_allowed($value, array $allowed): bool
    {
        $normalized = strtolower(trim((string)$value));
        foreach ($allowed as $a) {
            if ($normalized === strtolower(trim((string)$a))) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('ratib_workflow_validate_stage_rules')) {
    function ratib_workflow_validate_stage_rules(PDO $pdo, int $workflowId, string $stageKey, array $workerLike, array $stageCompleted): array
    {
        $missing = [];
        $rules = ratib_workflow_get_rules($pdo, $workflowId, $stageKey);
        foreach ($rules as $rule) {
            $cfg = $rule['config'];
            $type = strtolower(trim((string)($cfg['type'] ?? $rule['rule_type'])));
            $cfg = $rule['config'];
            if ($type === 'require_stage_completed') {
                $stage = (string)($cfg['stage'] ?? '');
                if ($stage === '' || empty($stageCompleted[$stage])) {
                    $missing[] = "stage_{$stage}_not_completed";
                }
                continue;
            }
            if ($type === 'require_field_value') {
                $field = (string)($cfg['field'] ?? '');
                $allowed = is_array($cfg['allowed'] ?? null) ? $cfg['allowed'] : [];
                if ($field === '' || !ratib_workflow_is_value_allowed($workerLike[$field] ?? '', $allowed)) {
                    $missing[] = "field_{$field}_invalid";
                }
                continue;
            }
            if ($type === 'require_many') {
                $conditions = is_array($cfg['conditions'] ?? null) ? $cfg['conditions'] : [];
                foreach ($conditions as $cond) {
                    $field = (string)($cond['field'] ?? '');
                    $allowed = is_array($cond['allowed'] ?? null) ? $cond['allowed'] : [];
                    if ($field === '' || !ratib_workflow_is_value_allowed($workerLike[$field] ?? '', $allowed)) {
                        $missing[] = "field_{$field}_invalid";
                    }
                }
                continue;
            }
            if ($type === 'block_if') {
                $field = (string)($cfg['field'] ?? '');
                $blocked = is_array($cfg['blocked'] ?? null) ? $cfg['blocked'] : [];
                if ($field !== '' && ratib_workflow_is_value_allowed($workerLike[$field] ?? '', $blocked)) {
                    $missing[] = "field_{$field}_blocked";
                }
                continue;
            }
        }
        return array_values(array_unique($missing));
    }
}

if (!function_exists('ratib_workflow_validate_field_type')) {
    function ratib_workflow_validate_field_type($value, string $type): bool
    {
        $type = strtolower(trim($type));
        $str = trim((string)$value);
        if ($type === '' || $type === 'string') return true;
        if ($type === 'numeric') return is_numeric($str);
        if ($type === 'boolean') return in_array(strtolower($str), ['1', '0', 'true', 'false', 'yes', 'no'], true);
        if ($type === 'date') return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $str);
        if ($type === 'date_future') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) return false;
            return strtotime($str) !== false && strtotime($str) >= strtotime(date('Y-m-d'));
        }
        return true;
    }
}

if (!function_exists('ratib_workflow_validate_stage_fields_config')) {
    function ratib_workflow_validate_stage_fields_config(array $stageDef, array $workerLike): array
    {
        $errors = [];
        $fieldsConfig = is_array($stageDef['fields_config'] ?? null) ? $stageDef['fields_config'] : [];
        foreach ($fieldsConfig as $fieldKey => $cfg) {
            $cfg = is_array($cfg) ? $cfg : [];
            $value = $workerLike[$fieldKey] ?? null;
            $required = !empty($cfg['required']);
            if ($required && ($value === null || $value === '')) {
                $errors[] = "field_{$fieldKey}_required";
                continue;
            }
            if ($value !== null && $value !== '' && !empty($cfg['type']) && !ratib_workflow_validate_field_type($value, (string)$cfg['type'])) {
                $errors[] = "field_{$fieldKey}_invalid_type";
            }
        }
        return array_values(array_unique($errors));
    }
}

if (!function_exists('ratib_workflow_compute_document_statuses')) {
    function ratib_workflow_compute_document_statuses(PDO $pdo, int $workflowId, array &$payload, array $workerLike): void
    {
        $docStatusMap = [
            'passport_status' => ['number_field' => 'passport_number'],
            'medical_status' => ['number_field' => 'medical_number'],
            'visa_status' => ['number_field' => 'visa_number'],
            'ticket_status' => ['number_field' => 'ticket_number'],
        ];

        // compute_status and auto_set_status rules can override defaults per stage.
        $stageRulesStmt = $pdo->prepare("SELECT stage_key FROM workflow_stages WHERE workflow_id = ?");
        $stageRulesStmt->execute([$workflowId]);
        $stageKeys = $stageRulesStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($stageKeys as $stageKey) {
            $rules = ratib_workflow_get_rules($pdo, $workflowId, (string)$stageKey);
            foreach ($rules as $rule) {
                $ruleType = strtolower(trim((string)$rule['rule_type']));
                $cfg = $rule['config'];
                if (!in_array($ruleType, ['compute_status', 'auto_set_status'], true)) continue;
                $target = (string)($cfg['target_status_field'] ?? '');
                if ($target === '') continue;
                $field = (string)($cfg['field'] ?? '');
                $allowed = is_array($cfg['allowed'] ?? null) ? $cfg['allowed'] : [];
                if ($field !== '' && !empty($allowed) && ratib_workflow_is_value_allowed($workerLike[$field] ?? '', $allowed)) {
                    $payload[$target] = (string)($cfg['on_match'] ?? 'ok');
                } else {
                    $payload[$target] = (string)($cfg['on_fail'] ?? ($payload[$target] ?? 'pending'));
                }
            }
        }

        foreach ($docStatusMap as $statusField => $meta) {
            $field = $meta['number_field'];
            $value = trim((string)($workerLike[$field] ?? ''));
            if ($value === '') {
                $payload[$statusField] = 'pending';
            } else {
                $payload[$statusField] = ratib_workflow_validate_field_type($value, 'string') ? 'ok' : 'not_ok';
            }
        }
    }
}

if (!function_exists('ratib_workflow_log_stage_transition')) {
    function ratib_workflow_log_stage_transition(PDO $pdo, int $workerId, ?string $from, ?string $to, ?int $changedBy, ?int $workflowId): void
    {
        if ($workerId <= 0) return;
        if ((string)$from === (string)$to) return;
        $stmt = $pdo->prepare(
            "INSERT INTO worker_stage_logs (worker_id, stage_from, stage_to, changed_by, workflow_id)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$workerId, $from, $to, $changedBy, $workflowId]);
    }
}

if (!function_exists('ratib_workflow_apply_on_save')) {
    /**
     * Validates and mutates payload with workflow_id/current_stage/stage_completed.
     */
    function ratib_workflow_apply_on_save(PDO $pdo, array &$payload, ?array $existingWorker = null, ?int $changedBy = null): void
    {
        ratib_workflow_ensure_schema($pdo);

        $country = (string)($payload['country'] ?? ($existingWorker['country'] ?? ''));
        $preferredWorkflowId = isset($payload['workflow_id']) ? (int)$payload['workflow_id'] : 0;
        $context = ratib_workflow_context_from_payload($payload, $existingWorker);
        $recommendation = ratib_workflow_recommend_template($pdo, $context);
        if ($preferredWorkflowId <= 0 && !empty($recommendation['workflow_definition_id'])) {
            $preferredWorkflowId = (int)$recommendation['workflow_definition_id'];
        }
        $workflow = null;
        $workflowId = 0;
        $workflowValidation = ['valid' => false, 'errors' => ['workflow_not_loaded']];
        try {
            if ($preferredWorkflowId > 0) {
                $workflow = ratib_workflow_resolve_definition_selector($pdo, $preferredWorkflowId);
            }
            if (!$workflow) {
                $workflow = ratib_workflow_resolve_definition($pdo, $country);
            }
            $workflowId = (int)$workflow['id'];
            $workflowValidation = ratib_workflow_validate_definition($pdo, $workflowId);
        } catch (Throwable $e) {
            error_log('Workflow resolve failed, falling back to default safe workflow: ' . $e->getMessage());
        }

        if ($workflowId <= 0 || !$workflowValidation['valid']) {
            if (!empty($workflowValidation['errors'])) {
                error_log('Workflow validation failed for country [' . $country . ']: ' . implode(', ', $workflowValidation['errors']));
            }
            $workflow = ratib_workflow_resolve_definition($pdo, 'DEFAULT');
            $workflowId = (int)$workflow['id'];
            $workflowValidation = ratib_workflow_validate_definition($pdo, $workflowId);
            if (!$workflowValidation['valid']) {
                error_log('Default safe workflow is invalid: ' . implode(', ', $workflowValidation['errors']));
            }
        }

        $activeVersionId = ratib_workflow_ensure_version_snapshot($pdo, $workflowId, $changedBy);
        $enforce = (int)($workflow['enforce_stage_order'] ?? 1) === 1;
        $stages = ratib_workflow_get_stage_map($pdo, $workflowId);
        if (empty($stages)) {
            throw new Exception('Workflow has no stages configured');
        }

        $stageKeys = array_column($stages, 'stage_key');
        $firstStage = $stageKeys[0];
        $existingStageCompleted = ratib_workflow_to_bool_map($existingWorker['stage_completed'] ?? []);
        $incomingStageCompleted = ratib_workflow_to_bool_map($payload['stage_completed'] ?? []);
        $stageCompleted = array_merge($existingStageCompleted, $incomingStageCompleted);

        $currentStage = (string)($payload['current_stage'] ?? ($existingWorker['current_stage'] ?? $firstStage));
        if (!in_array($currentStage, $stageKeys, true)) {
            $currentStage = $firstStage;
        }

        $workerLike = array_merge($existingWorker ?? [], $payload);
        ratib_workflow_compute_document_statuses($pdo, $workflowId, $payload, $workerLike);
        $workerLike = array_merge($workerLike, $payload);

        $currentStageDef = null;
        foreach ($stages as $stg) {
            if ($stg['stage_key'] === $currentStage) {
                $currentStageDef = $stg;
                break;
            }
        }
        if (!$currentStageDef) {
            throw new Exception('Current stage definition missing in workflow');
        }

        $fieldErrors = ratib_workflow_validate_stage_fields_config($currentStageDef, $workerLike);
        if (!empty($fieldErrors)) {
            throw new Exception('Workflow field validation failed: ' . implode(', ', $fieldErrors));
        }

        if ($enforce) {
            $blocked = ratib_workflow_validate_stage_rules($pdo, $workflowId, $currentStage, $workerLike, $stageCompleted);
            if (!empty($blocked)) {
                throw new Exception('Workflow stage requirements not met: ' . implode(', ', $blocked));
            }
            $requiredFields = $currentStageDef['required_fields'] ?? [];
            foreach ($requiredFields as $field) {
                $val = $payload[$field] ?? ($existingWorker[$field] ?? null);
                if ($val === null || $val === '') {
                    throw new Exception("Current stage requires field: {$field}");
                }
            }
        }

        $previousStage = (string)($existingWorker['current_stage'] ?? $currentStage);
        $stageCompleted[$currentStage] = true;
        $currentIndex = array_search($currentStage, $stageKeys, true);
        $nextStage = $stageKeys[min($currentIndex + 1, count($stageKeys) - 1)];
        $payload['workflow_id'] = $workflowId;
        $payload['workflow_version_id'] = $activeVersionId;
        $payload['current_stage'] = $nextStage;
        $payload['stage_completed'] = json_encode($stageCompleted, JSON_UNESCAPED_UNICODE);
        $payload['_workflow_recommendation'] = $recommendation;
        $payload['_workflow_stage_from'] = $previousStage;
        $payload['_workflow_stage_to'] = $nextStage;
        $payload['_workflow_transition_needed'] = ($previousStage !== $nextStage);
        $payload['_workflow_resolved_id'] = $workflowId;

        if (!empty($existingWorker['id'])) {
            ratib_workflow_log_stage_transition($pdo, (int)$existingWorker['id'], $previousStage, $nextStage, $changedBy, $workflowId);
        }
    }
}

