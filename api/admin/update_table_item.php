<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/update_table_item.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/update_table_item.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}
if (!isset($_SESSION['role_id']) || (int)$_SESSION['role_id'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['table']) || !isset($input['id']) || !isset($input['data'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input data'
    ]);
    exit;
}

    $table = $input['table'];
    $id = $input['id'];
    $data = $input['data'];
    
    try {
        // Validate table name to prevent SQL injection
        $allowed_tables = [
            'office_manager', 'visa_types', 'recruitment_countries', 'job_categories',
            'age_specifications', 'appearance_specifications', 'status_specifications',
            'request_statuses', 'arrival_agencies', 'arrival_stations', 'worker_statuses',
            'system_config'
        ];
        
        if (!in_array($table, $allowed_tables)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid table name'
            ]);
            exit;
        }
        
        // Map form field names to database column names (same as add_table_item.php)
        $fieldMapping = [
            'recruitment_countries' => [
                'name' => 'country_name',
                'code' => 'country_code',
                'description' => 'country_description'
            ],
            'job_categories' => [
                'name' => 'category_name',
                'description' => 'category_description'
            ],
            'visa_types' => [
                'name' => 'visa_name',
                'description' => 'visa_description'
            ],
            'age_specifications' => [
                'name' => 'age_range',
                'description' => 'age_description'
            ],
            'status_specifications' => [
                'name' => 'status_name',
                'description' => 'status_description'
            ],
            'appearance_specifications' => [
                'name' => 'appearance_name',
                'description' => 'appearance_description'
            ],
            'arrival_agencies' => [
                'name' => 'agency_name',
                'description' => 'agency_description'
            ],
            'arrival_stations' => [
                'name' => 'station_name',
                'description' => 'station_description'
            ]
        ];
        
        // Apply field mapping if exists for this table
        if (isset($fieldMapping[$table])) {
            $mappedData = [];
            foreach ($data as $key => $value) {
                // Use mapped column name if exists, otherwise use original key
                $dbColumn = $fieldMapping[$table][$key] ?? $key;
                $mappedData[$dbColumn] = $value;
            }
            $data = $mappedData;
        }
        
        // Normalize country names for recruitment_countries table
        if ($table === 'recruitment_countries' && isset($data['country_name'])) {
            $countryName = trim($data['country_name']);
            $countryCode = trim($data['country_code'] ?? '');
            
            // Map common typos/misspellings to correct country names
            $typoToCountry = [
                'bangladish' => 'Bangladesh',
                'banglades' => 'Bangladesh',
                'bangladsh' => 'Bangladesh',
            ];
            
            // Map country codes to correct names
            $codeToCountry = [
                'BD' => 'Bangladesh',
                'BGD' => 'Bangladesh',
                'SA' => 'Saudi Arabia',
                'SAU' => 'Saudi Arabia',
                'AE' => 'United Arab Emirates',
                'UAE' => 'United Arab Emirates',
                'KW' => 'Kuwait',
                'KWT' => 'Kuwait',
                'QA' => 'Qatar',
                'QAT' => 'Qatar',
                'BH' => 'Bahrain',
                'BHR' => 'Bahrain',
                'OM' => 'Oman',
                'OMN' => 'Oman',
                'EG' => 'Egypt',
                'EGY' => 'Egypt',
                'JO' => 'Jordan',
                'JOR' => 'Jordan',
                'LB' => 'Lebanon',
                'LBN' => 'Lebanon',
                'US' => 'United States',
                'USA' => 'United States',
                'GB' => 'United Kingdom',
                'GBR' => 'United Kingdom',
                'IN' => 'India',
                'IND' => 'India',
                'PK' => 'Pakistan',
                'PAK' => 'Pakistan',
                'PH' => 'Philippines',
                'PHL' => 'Philippines',
                'ID' => 'Indonesia',
                'IDN' => 'Indonesia',
                'MY' => 'Malaysia',
                'MYS' => 'Malaysia',
                'TH' => 'Thailand',
                'THA' => 'Thailand',
                'VN' => 'Vietnam',
                'VNM' => 'Vietnam',
                'SG' => 'Singapore',
                'SGP' => 'Singapore',
                'LK' => 'Sri Lanka',
                'LKA' => 'Sri Lanka',
            ];
            
            // Normalize country name
            if (!empty($countryName)) {
                $lowerCountryName = strtolower($countryName);
                // Check for typos first
                if (isset($typoToCountry[$lowerCountryName])) {
                    $data['country_name'] = $typoToCountry[$lowerCountryName];
                } elseif (!empty($countryCode) && isset($codeToCountry[strtoupper($countryCode)])) {
                    // If code suggests a different name, use the code-based name
                    $codeBasedName = $codeToCountry[strtoupper($countryCode)];
                    // Only override if the current name is clearly wrong (typo)
                    if (strpos($lowerCountryName, strtolower($codeBasedName)) === false) {
                        $data['country_name'] = $codeBasedName;
                    } else {
                        // Normalize case
                        $data['country_name'] = ucwords(strtolower($countryName));
                    }
                } else {
                    // Normalize case
                    $data['country_name'] = ucwords(strtolower($countryName));
                }
            } elseif (!empty($countryCode) && isset($codeToCountry[strtoupper($countryCode)])) {
                // If country_name is empty but we have a code, infer from code
                $data['country_name'] = $codeToCountry[strtoupper($countryCode)];
            }
        }
        
        // Build UPDATE query
        $set_clauses = [];
        foreach ($data as $key => $value) {
            $set_clauses[] = "$key = '" . $conn->real_escape_string($value) . "'";
        }
        $set_clause = implode(', ', $set_clauses);
        
        $query = "UPDATE $table SET $set_clause WHERE id = " . intval($id);
    
    if ($conn->query($query)) {
        echo json_encode([
            'success' => true,
            'message' => 'Item updated successfully'
        ]);
    } else {
        throw new Exception("Error executing query: " . $conn->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating item: ' . $e->getMessage()
    ]);
}
?> 