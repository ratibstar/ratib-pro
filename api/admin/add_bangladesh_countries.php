<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/add_bangladesh_countries.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/add_bangladesh_countries.php`.
 */
/**
 * Script to add Bangladesh country and its major cities to recruitment_countries table
 * Run this script once to populate Bangladesh data
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

try {
    // Check if database connection exists
    if (!isset($conn) || $conn === null) {
        throw new Exception("Database connection not available");
    }
    
    // Bangladesh country information
    $countryName = 'Bangladesh';
    $countryCode = 'BGD';  // ISO 3166-1 alpha-3 code (matches the country mapping in modern-forms.js)
    $currency = 'BDT';
    $flagEmoji = '🇧🇩';
    $status = 'active';
    
    // Major cities in Bangladesh
    $cities = [
        'Dhaka',           // Capital
        'Chittagong',      // Port city
        'Khulna',          // Industrial city
        'Rajshahi',        // Administrative city
        'Sylhet',          // Commercial city
        'Comilla',         // District city
        'Barisal',         // Port city
        'Rangpur',         // Northern city
        'Mymensingh',      // Administrative city
        'Jessore',         // District city
        'Narayanganj',     // Industrial city
        'Gazipur',         // Industrial city
        'Cox\'s Bazar',    // Tourist city
        'Bogra',           // District city
        'Dinajpur'         // District city
    ];
    
    $inserted = 0;
    $skipped = 0;
    $errors = [];
    
    // Prepare insert statement
    $insertStmt = $conn->prepare("INSERT INTO recruitment_countries 
        (country_name, country_code, currency, flag_emoji, city, status) 
        VALUES (?, ?, ?, ?, ?, ?)");
    
    if (!$insertStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Prepare check statement
    $checkStmt = $conn->prepare("SELECT id FROM recruitment_countries 
        WHERE country_name = ? AND city = ?");
    
    if (!$checkStmt) {
        throw new Exception("Prepare check statement failed: " . $conn->error);
    }
    
    // Insert each city as a separate entry
    foreach ($cities as $city) {
        // Check if this country-city combination already exists
        $checkStmt->bind_param("ss", $countryName, $city);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows > 0) {
            $skipped++;
            $result->close();
            continue;
        }
        $result->close();
        
        // Insert new entry
        $insertStmt->bind_param("ssssss", $countryName, $countryCode, $currency, $flagEmoji, $city, $status);
        
        if ($insertStmt->execute()) {
            $inserted++;
        } else {
            $errors[] = "Failed to insert {$city}: " . $insertStmt->error;
        }
    }
    
    $insertStmt->close();
    $checkStmt->close();
    
    // Return results
    echo json_encode([
        'success' => true,
        'message' => "Bangladesh data added successfully",
        'data' => [
            'country' => $countryName,
            'country_code' => $countryCode,
            'currency' => $currency,
            'flag_emoji' => $flagEmoji,
            'cities_inserted' => $inserted,
            'cities_skipped' => $skipped,
            'total_cities' => count($cities),
            'errors' => $errors
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error adding Bangladesh data: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
