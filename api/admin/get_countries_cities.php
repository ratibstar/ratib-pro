<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/get_countries_cities.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/get_countries_cities.php`.
 */
// API endpoint to get countries and cities from recruitment_countries table
// This replaces hardcoded countries-cities.js data
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');

// Session name must be set in config/env/load.php (ratib_control cookie / ?control=1) BEFORE session_start().
// Calling session_start() here first locks PHP to the default session name → empty $_SESSION → 401 for SSO users.
require_once dirname(__DIR__, 2) . '/includes/config.php';

header('Content-Type: application/json');

$isAppLogged = function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user();
$isControlLogged = !empty($_SESSION['control_logged_in']);
$isControlBridge = function_exists('ratib_control_pro_bridge') && ratib_control_pro_bridge();
if (!$isAppLogged && !$isControlLogged && !$isControlBridge) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

/**
 * When recruitment_countries has a country but no city rows, return common cities (Gulf recruitment).
 */
function ratib_fallback_cities_for_country(string $country): array
{
    $key = strtolower(trim($country));
    static $map = [
        'ethiopia' => ['Addis Ababa', 'Dire Dawa', 'Mekelle', 'Bahir Dar', 'Hawassa', 'Dessie', 'Jimma', 'Gondar'],
        'philippines' => ['Manila', 'Quezon City', 'Davao City', 'Cebu City', 'Zamboanga City', 'Antipolo', 'Pasig', 'Taguig'],
        'india' => ['Mumbai', 'Delhi', 'Bengaluru', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad'],
        'bangladesh' => ['Dhaka', 'Chittagong', 'Khulna', 'Rajshahi', 'Sylhet', 'Rangpur', 'Barisal'],
        'nepal' => ['Kathmandu', 'Pokhara', 'Lalitpur', 'Bharatpur', 'Biratnagar', 'Birgunj'],
        'sri lanka' => ['Colombo', 'Dehiwala', 'Moratuwa', 'Negombo', 'Kandy', 'Jaffna', 'Galle'],
        'indonesia' => ['Jakarta', 'Surabaya', 'Bandung', 'Medan', 'Semarang', 'Makassar', 'Palembang'],
        'pakistan' => ['Karachi', 'Lahore', 'Islamabad', 'Rawalpindi', 'Faisalabad', 'Multan', 'Peshawar'],
        'kenya' => ['Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret'],
        'uganda' => ['Kampala', 'Entebbe', 'Gulu', 'Jinja', 'Mbale'],
        'ghana' => ['Accra', 'Kumasi', 'Tamale', 'Takoradi', 'Cape Coast'],
        'nigeria' => ['Lagos', 'Abuja', 'Kano', 'Ibadan', 'Port Harcourt', 'Benin City'],
        'vietnam' => ['Ho Chi Minh City', 'Hanoi', 'Da Nang', 'Hai Phong', 'Can Tho'],
        'thailand' => ['Bangkok', 'Chiang Mai', 'Pattaya', 'Phuket', 'Hat Yai'],
        'myanmar' => ['Yangon', 'Mandalay', 'Naypyidaw', 'Mawlamyine'],
        'egypt' => ['Cairo', 'Alexandria', 'Giza', 'Shubra El Kheima', 'Port Said'],
        'jordan' => ['Amman', 'Zarqa', 'Irbid', 'Russeifa', 'Aqaba'],
        'lebanon' => ['Beirut', 'Tripoli', 'Sidon', 'Tyre', 'Zahle'],
        'yemen' => ["Sana'a", 'Aden', 'Taiz', 'Hudaydah', 'Ibb'],
        'sudan' => ['Khartoum', 'Omdurman', 'Port Sudan', 'Kassala'],
        'tunisia' => ['Tunis', 'Sfax', 'Sousse', 'Kairouan'],
        'morocco' => ['Casablanca', 'Rabat', 'Fes', 'Marrakesh', 'Tangier'],
        'south africa' => ['Johannesburg', 'Cape Town', 'Durban', 'Pretoria', 'Port Elizabeth'],
        'saudi arabia' => ['Riyadh', 'Jeddah', 'Mecca', 'Medina', 'Dammam', 'Khobar'],
        'united arab emirates' => ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah'],
        'kuwait' => ['Kuwait City', 'Hawalli', 'Salmiya', 'Farwaniya'],
        'qatar' => ['Doha', 'Al Rayyan', 'Al Wakrah', 'Umm Salal'],
        'bahrain' => ['Manama', 'Riffa', 'Muharraq', 'Hamad Town'],
        'oman' => ['Muscat', 'Salalah', 'Sohar', 'Nizwa'],
    ];
    if (isset($map[$key])) {
        return $map[$key];
    }
    foreach ($map as $name => $cities) {
        if (strtolower($name) === $key) {
            return $cities;
        }
    }
    return [];
}

try {
    // Check if database connection exists
    if (!isset($conn) || $conn === null) {
        throw new Exception("Database connection not available");
    }
    
    $action = $_GET['action'] ?? 'all';
    $requestedCountry = isset($_GET['country']) ? urldecode($_GET['country']) : null;
    $debug = isset($_GET['debug']) && $_GET['debug'] === '1';
    
    // Debug: Check what's actually in the database
    if ($debug) {
        $debugQuery = "SELECT country_name, city, status, LOWER(TRIM(status)) as normalized_status 
                       FROM recruitment_countries 
                       ORDER BY country_name ASC, city ASC";
        $debugResult = $conn->query($debugQuery);
        $debugData = [];
        if ($debugResult) {
            while ($debugRow = $debugResult->fetch_assoc()) {
                $debugData[] = $debugRow;
            }
        }
        error_log("DEBUG - All records in recruitment_countries: " . json_encode($debugData));
    }
    
    // Get all countries and their cities from recruitment_countries table
    // Use LOWER() to make status comparison case-insensitive (handles 'active', 'ACTIVE', 'Active', etc.)
    // Also handle NULL or empty status as 'active' (default behavior)
    $query = "SELECT DISTINCT country_name, city, id, country_code, currency, flag_emoji, status 
              FROM recruitment_countries 
              WHERE (LOWER(TRIM(COALESCE(status, ''))) = 'active' OR status IS NULL OR TRIM(status) = '')
              ORDER BY country_name ASC, city ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Database error: " . ($conn->error ?? 'Unknown error'));
    }
    
    // Debug: Log how many rows were found
    if ($debug) {
        $rowCount = $result->num_rows;
        error_log("DEBUG - Found $rowCount rows with status='active'");
    }
    
    $countriesData = [];
    $countriesCities = [];
    $countryNamesSeen = []; // Track unique country names
    
    while ($row = $result->fetch_assoc()) {
        $countryName = trim($row['country_name'] ?? '');
        $city = !empty($row['city']) ? trim($row['city']) : null;
        $countryCode = trim($row['country_code'] ?? '');
        
        // Map common country codes to correct country names
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
        
        // Map common typos/misspellings to correct country names
        $typoToCountry = [
            'bangladish' => 'Bangladesh',
            'banglades' => 'Bangladesh',
            'bangladsh' => 'Bangladesh',
            'bangladesh' => 'Bangladesh', // normalize case
        ];
        
        // Normalize country name: handle empty, typos, and case
        $normalizedCountryName = '';
        
        if (!empty($countryName)) {
            $lowerCountryName = strtolower(trim($countryName));
            // Check for typos first
            if (isset($typoToCountry[$lowerCountryName])) {
                $normalizedCountryName = $typoToCountry[$lowerCountryName];
            } else {
                // Use the original name but normalize case
                $normalizedCountryName = ucwords(strtolower($countryName));
            }
        }
        
        // If still empty or if we have a code that suggests a different name, infer from code
        if (empty($normalizedCountryName) && !empty($countryCode)) {
            $inferredName = $codeToCountry[strtoupper($countryCode)] ?? '';
            if (!empty($inferredName)) {
                $normalizedCountryName = $inferredName;
            }
        }
        
        // If we still don't have a country name, skip this record
        if (empty($normalizedCountryName)) {
            if ($debug) {
                error_log("WARNING: Record ID {$row['id']} has empty/unrecognized country_name. Original: '{$countryName}', City: {$city}, Code: {$countryCode}");
            }
            continue; // Skip records without a valid country name
        }
        
        // Use normalized country name
        $countryName = $normalizedCountryName;
        
        // Track that we've seen this country (even if it has no cities)
        if (!in_array($countryName, $countryNamesSeen)) {
            $countryNamesSeen[] = $countryName;
        }
        
        // Build countriesData array with full country info
        if (!isset($countriesData[$countryName])) {
            $countriesData[$countryName] = [
                'id' => $row['id'],
                'name' => $countryName,
                'code' => $row['country_code'],
                'currency' => $row['currency'],
                'flag_emoji' => $row['flag_emoji'],
                'cities' => []
            ];
        }
        
        // Add city if it exists and is not already in the array
        if (!empty($city) && !in_array($city, $countriesData[$countryName]['cities'])) {
            $countriesData[$countryName]['cities'][] = $city;
        }
        
        // Build legacy format for backward compatibility
        if (!isset($countriesCities[$countryName])) {
            $countriesCities[$countryName] = [];
        }
        if (!empty($city) && !in_array($city, $countriesCities[$countryName])) {
            $countriesCities[$countryName][] = $city;
        }
    }
    
    // Ensure all countries are included in countriesCities even if they have no cities
    foreach ($countryNamesSeen as $countryName) {
        if (!isset($countriesCities[$countryName])) {
            $countriesCities[$countryName] = [];
        }
    }
    
    // Sort cities within each country
    foreach ($countriesData as &$country) {
        sort($country['cities']);
    }
    foreach ($countriesCities as &$cities) {
        sort($cities);
    }
    
    // Return based on action
    if ($action === 'countries') {
        // Return only country list
        $countries = array_keys($countriesCities);
        // Remove duplicates and sort (case-insensitive)
        $countries = array_unique($countries);
        sort($countries);
        
        // Log for debugging (only if debug parameter is set)
        if ($debug) {
            error_log("Countries API - Found " . count($countries) . " countries: " . implode(', ', $countries));
            error_log("Countries API - Full countriesCities array: " . json_encode($countriesCities));
        }
        
        $response = [
            'success' => true,
            'countries' => $countries,
            'data' => array_values($countriesData),
            'count' => count($countries)
        ];
        
        // Add debug info if requested
        if ($debug) {
            $response['debug'] = [
                'total_records_found' => $result->num_rows ?? 0,
                'unique_countries' => count($countries),
                'countries_list' => $countries,
                'countries_with_cities' => $countriesCities
            ];
        }
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } elseif ($action === 'cities' && $requestedCountry) {
        // Return cities for a specific country
        // Try exact match first, then case-insensitive match
        $cities = $countriesCities[$requestedCountry] ?? [];
        
        // If no exact match, try case-insensitive match
        if (empty($cities)) {
            foreach ($countriesCities as $countryName => $countryCities) {
                if (strtolower(trim($countryName)) === strtolower(trim($requestedCountry))) {
                    $cities = $countryCities;
                    break;
                }
            }
        }
        
        if (count($cities) === 0 && $requestedCountry) {
            $cities = ratib_fallback_cities_for_country($requestedCountry);
        }
        
        // If no cities found, return empty array (not an error)
        echo json_encode([
            'success' => true,
            'country' => $requestedCountry,
            'cities' => $cities,
            'message' => count($cities) === 0 ? 'No cities found for this country. Add cities via System Settings.' : null
        ]);
    } else {
        // Return all data (default)
        $allCountries = array_keys($countriesCities);
        $allCountries = array_unique($allCountries);
        sort($allCountries);
        
        echo json_encode([
            'success' => true,
            'countriesCities' => $countriesCities,
            'countriesData' => array_values($countriesData),
            'countries' => $allCountries,
            'count' => count($allCountries) // Add count for debugging
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving countries/cities: ' . $e->getMessage()
    ]);
}
?>
