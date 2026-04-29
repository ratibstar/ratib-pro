<?php
declare(strict_types=1);

if (!class_exists('CountryProfileValidationException')) {
    class CountryProfileValidationException extends Exception {}
}

if (!function_exists('ratib_country_profile_allowed_requirement_fields')) {
    function ratib_country_profile_allowed_requirement_fields(): array
    {
        return [
            'full_name', 'gender', 'agent_id',
            'identity', 'password',
            'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number',
            'training_certificate_number', 'contract_signed_number', 'insurance_number', 'exit_permit_number', 'approval_reference_id',
            'government_registration_number', 'work_permit_number', 'insurance_policy_number',
            'salary', 'contract_duration', 'flight_ticket_number', 'predeparture_training_completed', 'contract_verified'
        ];
    }
}

if (!function_exists('ratib_country_profile_defaults')) {
    function ratib_country_profile_defaults(): array
    {
        return [
            'indonesia' => [
                'labels' => ['government' => 'Government Approval', 'workPermit' => 'Exit Permit', 'contract' => 'Signed Contract', 'travel' => 'Travel Readiness'],
                'requirements' => ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'training_certificate_number', 'contract_signed_number', 'insurance_number', 'exit_permit_number', 'approval_reference_id'],
            ],
            'bangladesh' => [
                'labels' => ['government' => 'BMET Registration', 'workPermit' => 'Work Permit', 'contract' => 'Overseas Contract', 'travel' => 'Travel Clearance'],
                'requirements' => ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'government_registration_number', 'work_permit_number', 'insurance_policy_number', 'salary', 'contract_duration', 'flight_ticket_number', 'predeparture_training_completed', 'contract_verified'],
            ],
            'sri_lanka' => [
                'labels' => ['government' => 'SLBFE Registration', 'workPermit' => 'Work Permit', 'contract' => 'Employment Contract', 'travel' => 'Departure Clearance'],
                'requirements' => ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'government_registration_number', 'work_permit_number', 'insurance_policy_number', 'salary', 'contract_duration', 'flight_ticket_number', 'predeparture_training_completed', 'contract_verified'],
            ],
            'kenya' => [
                'labels' => ['government' => 'NITA Registration', 'workPermit' => 'Work Permit', 'contract' => 'Employment Contract', 'travel' => 'Travel Clearance'],
                'requirements' => ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'government_registration_number', 'work_permit_number', 'insurance_policy_number', 'salary', 'contract_duration', 'flight_ticket_number', 'predeparture_training_completed', 'contract_verified'],
            ],
            'default' => [
                'labels' => ['government' => 'Government Registration', 'workPermit' => 'Work Permit', 'contract' => 'Contract', 'travel' => 'Travel & Departure'],
                'requirements' => ['full_name', 'gender', 'agent_id', 'identity_number', 'passport_number', 'police_number', 'medical_number', 'visa_number', 'ticket_number', 'government_registration_number', 'work_permit_number'],
            ],
        ];
    }
}

if (!function_exists('ratib_country_profile_registry_rows')) {
    /**
     * Active rows from control_countries (same registry as Country Profiles UI).
     *
     * @return list<array{slug: string, name_lower: string}>
     */
    function ratib_country_profile_registry_rows(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        $ctrl = $GLOBALS['control_conn'] ?? null;
        if (!($ctrl instanceof mysqli)) {
            return $cache;
        }
        $chk = @$ctrl->query("SHOW TABLES LIKE 'control_countries'");
        if (!$chk || $chk->num_rows === 0) {
            return $cache;
        }
        $hasActive = $ctrl->query("SHOW COLUMNS FROM control_countries LIKE 'is_active'");
        $hasSort = $ctrl->query("SHOW COLUMNS FROM control_countries LIKE 'sort_order'");
        $where = ($hasActive && $hasActive->num_rows > 0) ? 'WHERE is_active = 1' : '';
        $orderBy = ($hasSort && $hasSort->num_rows > 0) ? 'sort_order ASC, name ASC' : 'name ASC';
        $sql = "SELECT LOWER(TRIM(slug)) AS slug, name FROM control_countries {$where} ORDER BY {$orderBy}";
        $q = @$ctrl->query($sql);
        if ($q) {
            while ($row = $q->fetch_assoc()) {
                $slug = strtolower(trim((string) ($row['slug'] ?? '')));
                if ($slug === '') {
                    continue;
                }
                $cache[] = [
                    'slug' => $slug,
                    'name_lower' => strtolower(trim((string) ($row['name'] ?? ''))),
                ];
            }
        }

        return $cache;
    }
}

if (!function_exists('ratib_country_profile_detect_slug')) {
    function ratib_country_profile_detect_slug(array $source): string
    {
        $countryName = strtolower(trim((string) ($source['country'] ?? $source['nationality'] ?? $_SESSION['country_name'] ?? (defined('COUNTRY_NAME') ? COUNTRY_NAME : ''))));
        $countryCode = strtolower(trim((string) ($source['country_code'] ?? $_SESSION['country_code'] ?? (defined('COUNTRY_CODE') ? COUNTRY_CODE : ''))));
        $combined = trim($countryName . ' ' . $countryCode);

        foreach (ratib_country_profile_registry_rows() as $row) {
            $slug = $row['slug'];
            $nm = $row['name_lower'];
            if ($countryName !== '') {
                if ($countryName === $slug) {
                    return $slug;
                }
                if ($nm !== '' && $countryName === $nm) {
                    return $slug;
                }
            }
        }

        if (strpos($countryName, 'indonesia') !== false || preg_match('/\bidn?\b/', $combined)) return 'indonesia';
        if (strpos($countryName, 'bangladesh') !== false || preg_match('/\bbd\b/', $combined)) return 'bangladesh';
        if (strpos($countryName, 'sri lanka') !== false || strpos($countryName, 'srilanka') !== false || preg_match('/\blk\b/', $combined)) return 'sri_lanka';
        if (strpos($countryName, 'kenya') !== false || preg_match('/\bke\b/', $combined)) return 'kenya';
        return 'default';
    }
}

if (!function_exists('ratib_country_profile_load_custom')) {
    function ratib_country_profile_load_custom(string $slug): ?array
    {
        $ctrl = $GLOBALS['control_conn'] ?? null;
        if (!($ctrl instanceof mysqli) || $slug === '') return null;
        $chk = $ctrl->query("SHOW TABLES LIKE 'control_country_profiles'");
        if (!$chk || $chk->num_rows === 0) return null;
        $st = $ctrl->prepare("SELECT labels_json, requirements_json FROM control_country_profiles WHERE country_slug = ? LIMIT 1");
        if (!$st) return null;
        $st->bind_param('s', $slug);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) return null;
        return [
            'labels' => json_decode((string) ($row['labels_json'] ?? '{}'), true) ?: [],
            'requirements' => json_decode((string) ($row['requirements_json'] ?? '[]'), true) ?: [],
        ];
    }
}

if (!function_exists('ratib_country_profile_effective')) {
    function ratib_country_profile_effective(string $slug): array
    {
        $defaults = ratib_country_profile_defaults();
        $base = $defaults[$slug] ?? $defaults['default'];
        $custom = ratib_country_profile_load_custom($slug);
        if (is_array($custom) && !empty($custom['labels']) && is_array($custom['labels'])) {
            $base['labels'] = $custom['labels'];
        }
        if (is_array($custom) && !empty($custom['requirements']) && is_array($custom['requirements'])) {
            $base['requirements'] = array_values(array_unique(array_filter(array_map('strval', $custom['requirements']))));
        }
        return $base;
    }
}

if (!function_exists('ratib_country_profile_value_for_field')) {
    function ratib_country_profile_value_for_field(string $field, array $payload, ?array $existing): mixed
    {
        if (array_key_exists($field, $payload)) return $payload[$field];
        if ($field === 'full_name') {
            if (array_key_exists('worker_name', $payload)) return $payload['worker_name'];
            if (is_array($existing)) return $existing['worker_name'] ?? $existing['full_name'] ?? null;
        }
        if ($field === 'agent_id') {
            if (array_key_exists('agent_id', $payload)) return $payload['agent_id'];
            if (is_array($existing)) return $existing['agent_id'] ?? null;
        }
        if ($field === 'identity') {
            return ratib_country_profile_value_for_field('identity_number', $payload, $existing);
        }
        return is_array($existing) ? ($existing[$field] ?? null) : null;
    }
}

if (!function_exists('ratib_country_profile_is_missing')) {
    function ratib_country_profile_is_missing(mixed $value): bool
    {
        if ($value === null) return true;
        if (is_string($value)) return trim($value) === '';
        if (is_array($value)) return count($value) === 0;
        return false;
    }
}

if (!function_exists('ratib_enforce_country_requirements')) {
    function ratib_enforce_country_requirements(array $payload, ?array $existing = null): void
    {
        $slug = ratib_country_profile_detect_slug(array_merge($existing ?: [], $payload));
        $effective = ratib_country_profile_effective($slug);
        $required = array_values(array_intersect(
            ratib_country_profile_allowed_requirement_fields(),
            array_map('strval', (array) ($effective['requirements'] ?? []))
        ));
        $missing = [];
        foreach ($required as $field) {
            if ($field === 'password') {
                continue;
            }
            $val = ratib_country_profile_value_for_field($field, $payload, $existing);
            if (ratib_country_profile_is_missing($val)) $missing[] = $field;
        }
        if (!empty($missing)) {
            throw new CountryProfileValidationException('Missing required fields for country profile [' . $slug . ']: ' . implode(', ', $missing));
        }
    }
}

