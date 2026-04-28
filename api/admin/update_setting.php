<?php
/**
 * EN: Handles API endpoint/business logic in `api/admin/update_setting.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/admin/update_setting.php`.
 */
require_once '../../includes/config.php';
header('Content-Type: application/json; charset=utf-8');

// Session is already started in config.php, so we don't need to start it again

// Error reporting (Production: log only, don't display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_POST['action'])) {
    error_log("Missing action parameter");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing action parameter']);
    exit;
}

$action = $_POST['action'];
error_log("Action requested: " . $action);

try {
    switch ($action) {
        case 'update_office_manager':
            // Update office manager data
            $companyName = $_POST['company_name'] ?? '';
            $contactPerson = $_POST['contact_person'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $email = $_POST['email'] ?? '';
            $address = $_POST['address'] ?? '';
            $licenseNumber = $_POST['license_number'] ?? '';
            $expiryDate = $_POST['expiry_date'] ?? '';
            
            error_log("Updating office manager data");
            
            $stmt = $conn->prepare("UPDATE office_manager_data SET 
                company_name = ?, contact_person = ?, phone = ?, email = ?, 
                address = ?, license_number = ?, expiry_date = ? 
                WHERE id = 1");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssssss", $companyName, $contactPerson, $phone, $email, $address, $licenseNumber, $expiryDate);
            
            if ($stmt->execute()) {
                error_log("Office manager data updated successfully");
                echo json_encode(['success' => true, 'message' => 'Office manager data updated successfully']);
            } else {
                throw new Exception('Failed to update office manager data: ' . $stmt->error);
            }
            break;
            
        case 'add_visa_type':
            // Add new visa type
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $durationDays = $_POST['duration_days'] ?? 0;
            $fee = $_POST['fee'] ?? 0;
            $requirements = $_POST['requirements'] ?? '';
            
            if (empty($name)) {
                throw new Exception('Visa name is required');
            }
            
            error_log("Adding visa type: " . $name);
            
            $stmt = $conn->prepare("INSERT INTO visa_types (name, description, duration_days, fee, requirements, status) VALUES (?, ?, ?, ?, ?, 'active')");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssids", $name, $description, $durationDays, $fee, $requirements);
            
            if ($stmt->execute()) {
                error_log("Visa type added successfully");
                echo json_encode(['success' => true, 'message' => 'Visa type added successfully']);
            } else {
                throw new Exception('Failed to add visa type: ' . $stmt->error);
            }
            break;
            
        case 'delete_visa_type':
            // Delete visa type
            $visaId = $_POST['visa_id'] ?? 0;
            
            if (!$visaId) {
                throw new Exception('Visa ID is required');
            }
            
            error_log("Deleting visa type ID: " . $visaId);
            
            $stmt = $conn->prepare("DELETE FROM visa_types WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $visaId);
            
            if ($stmt->execute()) {
                error_log("Visa type deleted successfully");
                echo json_encode(['success' => true, 'message' => 'Visa type deleted successfully']);
            } else {
                throw new Exception('Failed to delete visa type: ' . $stmt->error);
            }
            break;
            
        case 'update_system_config':
            // Update system configuration
            $systemSettings = [];
            foreach ($_POST as $key => $value) {
                if ($key !== 'action' && !empty($value)) {
                    $systemSettings[$key] = $value;
                }
            }
            
            if (empty($systemSettings)) {
                throw new Exception('No settings to update');
            }
            
            error_log("Updating system config: " . json_encode($systemSettings));
            
            $success = true;
            foreach ($systemSettings as $key => $value) {
                $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("sss", $key, $value, $value);
                
                if (!$stmt->execute()) {
                    $success = false;
                    throw new Exception('Failed to update setting ' . $key . ': ' . $stmt->error);
                }
            }
            
            if ($success) {
                error_log("System configuration updated successfully");
                echo json_encode(['success' => true, 'message' => 'System configuration updated successfully']);
            } else {
                throw new Exception('Failed to update system configuration');
            }
            break;
            
        case 'add_experience_level':
            // Add new experience level
            $name = $_POST['name'] ?? '';
            $minYears = $_POST['min_years'] ?? 0;
            $maxYears = $_POST['max_years'] ?? 0;
            $description = $_POST['description'] ?? '';
            
            if (empty($name)) {
                throw new Exception('Experience level name is required');
            }
            
            error_log("Adding experience level: " . $name);
            
            $stmt = $conn->prepare("INSERT INTO experience_levels (name, min_years, max_years, description) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("siis", $name, $minYears, $maxYears, $description);
            
            if ($stmt->execute()) {
                error_log("Experience level added successfully");
                echo json_encode(['success' => true, 'message' => 'Experience level added successfully']);
            } else {
                throw new Exception('Failed to add experience level: ' . $stmt->error);
            }
            break;
            
        case 'update_experience_level':
            // Update experience level
            $levelId = $_POST['item_id'] ?? 0;
            $levelName = $_POST['level_name'] ?? '';
            $minYears = $_POST['min_years'] ?? 0;
            $maxYears = $_POST['max_years'] ?? 999;
            $description = $_POST['level_description'] ?? '';
            
            if (!$levelId || empty($levelName)) {
                throw new Exception('Experience level ID and name are required');
            }
            
            error_log("Updating experience level ID: " . $levelId);
            
            $stmt = $conn->prepare("UPDATE experience_levels SET level_name = ?, min_years = ?, max_years = ?, level_description = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("siisi", $levelName, $minYears, $maxYears, $description, $levelId);
            
            if ($stmt->execute()) {
                error_log("Experience level updated successfully");
                echo json_encode(['success' => true, 'message' => 'Experience level updated successfully']);
            } else {
                throw new Exception('Failed to update experience level: ' . $stmt->error);
            }
            break;
            
        case 'delete_experience_level':
            // Delete experience level
            $levelId = $_POST['level_id'] ?? 0;
            
            if (!$levelId) {
                throw new Exception('Experience level ID is required');
            }
            
            error_log("Deleting experience level ID: " . $levelId);
            
            $stmt = $conn->prepare("DELETE FROM experience_levels WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $levelId);
            
            if ($stmt->execute()) {
                error_log("Experience level deleted successfully");
                echo json_encode(['success' => true, 'message' => 'Experience level deleted successfully']);
            } else {
                throw new Exception('Failed to delete experience level: ' . $stmt->error);
            }
            break;
            
        case 'add_recruitment_profession':
            // Add new recruitment profession
            $professionName = $_POST['profession_name'] ?? '';
            $category = $_POST['category'] ?? '';
            $description = $_POST['description'] ?? '';
            $salaryRange = $_POST['salary_range'] ?? '';
            
            if (empty($professionName)) {
                throw new Exception('Profession name is required');
            }
            
            error_log("Adding recruitment profession: " . $professionName);
            
            $stmt = $conn->prepare("INSERT INTO recruitment_professions (profession_name, category, description, salary_range) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssss", $professionName, $category, $description, $salaryRange);
            
            if ($stmt->execute()) {
                error_log("Recruitment profession added successfully");
                echo json_encode(['success' => true, 'message' => 'Recruitment profession added successfully']);
            } else {
                throw new Exception('Failed to add recruitment profession: ' . $stmt->error);
            }
            break;
            
        case 'add_recruitment_country':
            // Add new recruitment country
            $countryName = $_POST['country_name'] ?? '';
            $countryDescription = $_POST['country_description'] ?? '';
            $countryCode = $_POST['country_code'] ?? '';
            
            if (empty($countryName)) {
                throw new Exception('Country name is required');
            }
            
            error_log("Adding recruitment country: " . $countryName);
            
            $stmt = $conn->prepare("INSERT INTO recruitment_countries (country_name, country_description, country_code, is_active) VALUES (?, ?, ?, 1)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sss", $countryName, $countryDescription, $countryCode);
            
            if ($stmt->execute()) {
                error_log("Recruitment country added successfully");
                echo json_encode(['success' => true, 'message' => 'Recruitment country added successfully']);
            } else {
                throw new Exception('Failed to add recruitment country: ' . $stmt->error);
            }
            break;
            
        case 'add_job_category':
            // Add new job category
            $categoryName = $_POST['category_name'] ?? '';
            $categoryDescription = $_POST['category_description'] ?? '';
            $salaryRange = $_POST['salary_range'] ?? '';
            $requirements = $_POST['requirements'] ?? '';
            
            if (empty($categoryName)) {
                throw new Exception('Category name is required');
            }
            
            error_log("Adding job category: " . $categoryName);
            
            $stmt = $conn->prepare("INSERT INTO job_categories (category_name, category_description, salary_range, requirements, is_active) VALUES (?, ?, ?, ?, 1)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssss", $categoryName, $categoryDescription, $salaryRange, $requirements);
            
            if ($stmt->execute()) {
                error_log("Job category added successfully");
                echo json_encode(['success' => true, 'message' => 'Job category added successfully']);
            } else {
                throw new Exception('Failed to add job category: ' . $stmt->error);
            }
            break;
            
        case 'add_age_specification':
            // Add new age specification
            $ageRange = $_POST['age_range'] ?? '';
            $ageDescription = $_POST['age_description'] ?? '';
            $minAge = $_POST['min_age'] ?? 0;
            $maxAge = $_POST['max_age'] ?? 100;
            
            if (empty($ageRange)) {
                throw new Exception('Age range is required');
            }
            
            error_log("Adding age specification: " . $ageRange);
            
            $stmt = $conn->prepare("INSERT INTO age_specifications (age_range, age_description, min_age, max_age, is_active) VALUES (?, ?, ?, ?, 1)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssii", $ageRange, $ageDescription, $minAge, $maxAge);
            
            if ($stmt->execute()) {
                error_log("Age specification added successfully");
                echo json_encode(['success' => true, 'message' => 'Age specification added successfully']);
            } else {
                throw new Exception('Failed to add age specification: ' . $stmt->error);
            }
            break;
            
        case 'add_appearance_specification':
            // Add new appearance specification
            $specificationName = $_POST['specification_name'] ?? '';
            $description = $_POST['description'] ?? '';
            $category = $_POST['category'] ?? '';
            
            if (empty($specificationName)) {
                throw new Exception('Specification name is required');
            }
            
            error_log("Adding appearance specification: " . $specificationName);
            
            $stmt = $conn->prepare("INSERT INTO appearance_specifications (specification_name, description, category, status) VALUES (?, ?, ?, 'active')");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sss", $specificationName, $description, $category);
            
            if ($stmt->execute()) {
                error_log("Appearance specification added successfully");
                echo json_encode(['success' => true, 'message' => 'Appearance specification added successfully']);
            } else {
                throw new Exception('Failed to add appearance specification: ' . $stmt->error);
            }
            break;
            
        case 'add_status_specification':
            // Add new status specification
            $statusName = $_POST['status_name'] ?? '';
            $statusDescription = $_POST['status_description'] ?? '';
            $colorCode = $_POST['color_code'] ?? '#007bff';
            
            if (empty($statusName)) {
                throw new Exception('Status name is required');
            }
            
            error_log("Adding status specification: " . $statusName);
            
            $stmt = $conn->prepare("INSERT INTO status_specifications (status_name, status_description, color_code, is_active) VALUES (?, ?, ?, 1)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sss", $statusName, $statusDescription, $colorCode);
            
            if ($stmt->execute()) {
                error_log("Status specification added successfully");
                echo json_encode(['success' => true, 'message' => 'Status specification added successfully']);
            } else {
                throw new Exception('Failed to add status specification: ' . $stmt->error);
            }
            break;
            
        case 'add_arrival_station':
            // Add new arrival station
            $stationName = $_POST['station_name'] ?? '';
            $stationDescription = $_POST['station_description'] ?? '';
            $location = $_POST['location'] ?? '';
            
            if (empty($stationName)) {
                throw new Exception('Station name is required');
            }
            
            error_log("Adding arrival station: " . $stationName);
            
            $stmt = $conn->prepare("INSERT INTO arrival_stations (station_name, station_description, location, is_active) VALUES (?, ?, ?, 1)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sss", $stationName, $stationDescription, $location);
            
            if ($stmt->execute()) {
                error_log("Arrival station added successfully");
                echo json_encode(['success' => true, 'message' => 'Arrival station added successfully']);
            } else {
                throw new Exception('Failed to add arrival station: ' . $stmt->error);
            }
            break;
            
        case 'add_arrival_agency':
            // Add new arrival agency
            $agencyName = $_POST['agency_name'] ?? '';
            $agencyDescription = $_POST['agency_description'] ?? '';
            $contactInfo = $_POST['contact_info'] ?? '';
            
            if (empty($agencyName)) {
                throw new Exception('Agency name is required');
            }
            
            error_log("Adding arrival agency: " . $agencyName);
            
            $stmt = $conn->prepare("INSERT INTO arrival_agencies (agency_name, agency_description, contact_info, is_active) VALUES (?, ?, ?, 1)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sss", $agencyName, $agencyDescription, $contactInfo);
            
            if ($stmt->execute()) {
                error_log("Arrival agency added successfully");
                echo json_encode(['success' => true, 'message' => 'Arrival agency added successfully']);
            } else {
                throw new Exception('Failed to add arrival agency: ' . $stmt->error);
            }
            break;
            
        case 'add_request_status':
            // Add new request status
            $statusName = $_POST['status_name'] ?? '';
            $statusDescription = $_POST['status_description'] ?? '';
            $colorCode = $_POST['color_code'] ?? '#007bff';
            
            if (empty($statusName)) {
                throw new Exception('Status name is required');
            }
            
            error_log("Adding request status: " . $statusName);
            
            $stmt = $conn->prepare("INSERT INTO request_statuses (status_name, status_description, color_code, is_active) VALUES (?, ?, ?, 1)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sss", $statusName, $statusDescription, $colorCode);
            
            if ($stmt->execute()) {
                error_log("Request status added successfully");
                echo json_encode(['success' => true, 'message' => 'Request status added successfully']);
            } else {
                throw new Exception('Failed to add request status: ' . $stmt->error);
            }
            break;
            
        case 'add_worker_status':
            // Add new worker status
            $statusName = $_POST['status_name'] ?? '';
            $statusDescription = $_POST['status_description'] ?? '';
            $colorCode = $_POST['color_code'] ?? '#007bff';
            
            if (empty($statusName)) {
                throw new Exception('Status name is required');
            }
            
            error_log("Adding worker status: " . $statusName);
            
            $stmt = $conn->prepare("INSERT INTO worker_statuses (status_name, status_description, color_code, is_active) VALUES (?, ?, ?, 1)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sss", $statusName, $statusDescription, $colorCode);
            
            if ($stmt->execute()) {
                error_log("Worker status added successfully");
                echo json_encode(['success' => true, 'message' => 'Worker status added successfully']);
            } else {
                throw new Exception('Failed to add worker status: ' . $stmt->error);
            }
            break;
            
        case 'update_recruitment_profession':
            // Update recruitment profession
            $professionId = $_POST['item_id'] ?? 0;
            $professionName = $_POST['profession_name'] ?? '';
            $category = $_POST['category'] ?? '';
            $description = $_POST['profession_description'] ?? '';
            $salaryRange = $_POST['salary_range'] ?? '';
            
            if (!$professionId || empty($professionName)) {
                throw new Exception('Profession ID and name are required');
            }
            
            error_log("Updating recruitment profession ID: " . $professionId);
            
            $stmt = $conn->prepare("UPDATE recruitment_professions SET profession_name = ?, category = ?, profession_description = ?, salary_range = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssssi", $professionName, $category, $description, $salaryRange, $professionId);
            
            if ($stmt->execute()) {
                error_log("Recruitment profession updated successfully");
                echo json_encode(['success' => true, 'message' => 'Recruitment profession updated successfully']);
            } else {
                throw new Exception('Failed to update recruitment profession: ' . $stmt->error);
            }
            break;
            
        case 'delete_recruitment_profession':
            // Delete recruitment profession
            $professionId = $_POST['profession_id'] ?? 0;
            
            if (!$professionId) {
                throw new Exception('Profession ID is required');
            }
            
            error_log("Deleting recruitment profession ID: " . $professionId);
            
            $stmt = $conn->prepare("DELETE FROM recruitment_professions WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $professionId);
            
            if ($stmt->execute()) {
                error_log("Recruitment profession deleted successfully");
                echo json_encode(['success' => true, 'message' => 'Recruitment profession deleted successfully']);
            } else {
                throw new Exception('Failed to delete recruitment profession: ' . $stmt->error);
            }
            break;
            
        case 'update_visa_type':
            // Update visa type
            $visaId = $_POST['item_id'] ?? 0;
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $durationDays = $_POST['duration_days'] ?? 0;
            $fee = $_POST['fee'] ?? 0;
            $requirements = $_POST['requirements'] ?? '';
            $status = $_POST['status'] ?? 'active';
            
            if (!$visaId || empty($name)) {
                throw new Exception('Visa ID and name are required');
            }
            
            error_log("Updating visa type ID: " . $visaId);
            
            $stmt = $conn->prepare("UPDATE visa_types SET name = ?, description = ?, duration_days = ?, fee = ?, requirements = ?, status = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssidssi", $name, $description, $durationDays, $fee, $requirements, $status, $visaId);
            
            if ($stmt->execute()) {
                error_log("Visa type updated successfully");
                echo json_encode(['success' => true, 'message' => 'Visa type updated successfully']);
            } else {
                throw new Exception('Failed to update visa type: ' . $stmt->error);
            }
            break;
            
        case 'update_recruitment_country':
            // Update recruitment country
            $countryId = $_POST['item_id'] ?? 0;
            $countryName = $_POST['country_name'] ?? '';
            $countryDescription = $_POST['country_description'] ?? '';
            $countryCode = $_POST['country_code'] ?? '';
            $isActive = $_POST['is_active'] ?? 1;
            
            if (!$countryId || empty($countryName)) {
                throw new Exception('Country ID and name are required');
            }
            
            error_log("Updating recruitment country ID: " . $countryId);
            
            $stmt = $conn->prepare("UPDATE recruitment_countries SET country_name = ?, country_description = ?, country_code = ?, is_active = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssii", $countryName, $countryDescription, $countryCode, $isActive, $countryId);
            
            if ($stmt->execute()) {
                error_log("Recruitment country updated successfully");
                echo json_encode(['success' => true, 'message' => 'Recruitment country updated successfully']);
            } else {
                throw new Exception('Failed to update recruitment country: ' . $stmt->error);
            }
            break;
            
        case 'delete_recruitment_country':
            // Delete recruitment country
            $countryId = $_POST['country_id'] ?? 0;
            
            if (!$countryId) {
                throw new Exception('Country ID is required');
            }
            
            error_log("Deleting recruitment country ID: " . $countryId);
            
            $stmt = $conn->prepare("DELETE FROM recruitment_countries WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $countryId);
            
            if ($stmt->execute()) {
                error_log("Recruitment country deleted successfully");
                echo json_encode(['success' => true, 'message' => 'Recruitment country deleted successfully']);
            } else {
                throw new Exception('Failed to delete recruitment country: ' . $stmt->error);
            }
            break;
            
        case 'update_job_category':
            // Update job category
            $categoryId = $_POST['item_id'] ?? 0;
            $categoryName = $_POST['category_name'] ?? '';
            $categoryDescription = $_POST['category_description'] ?? '';
            $salaryRange = $_POST['salary_range'] ?? '';
            $requirements = $_POST['requirements'] ?? '';
            $isActive = $_POST['is_active'] ?? 1;
            
            if (!$categoryId || empty($categoryName)) {
                throw new Exception('Category ID and name are required');
            }
            
            error_log("Updating job category ID: " . $categoryId);
            
            $stmt = $conn->prepare("UPDATE job_categories SET category_name = ?, category_description = ?, salary_range = ?, requirements = ?, is_active = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssssii", $categoryName, $categoryDescription, $salaryRange, $requirements, $isActive, $categoryId);
            
            if ($stmt->execute()) {
                error_log("Job category updated successfully");
                echo json_encode(['success' => true, 'message' => 'Job category updated successfully']);
            } else {
                throw new Exception('Failed to update job category: ' . $stmt->error);
            }
            break;
            
        case 'delete_job_category':
            // Delete job category
            $categoryId = $_POST['category_id'] ?? 0;
            
            if (!$categoryId) {
                throw new Exception('Category ID is required');
            }
            
            error_log("Deleting job category ID: " . $categoryId);
            
            $stmt = $conn->prepare("DELETE FROM job_categories WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $categoryId);
            
            if ($stmt->execute()) {
                error_log("Job category deleted successfully");
                echo json_encode(['success' => true, 'message' => 'Job category deleted successfully']);
            } else {
                throw new Exception('Failed to delete job category: ' . $stmt->error);
            }
            break;
            
        case 'update_age_specification':
            // Update age specification
            $ageId = $_POST['item_id'] ?? 0;
            $ageRange = $_POST['age_range'] ?? '';
            $ageDescription = $_POST['age_description'] ?? '';
            $minAge = $_POST['min_age'] ?? 0;
            $maxAge = $_POST['max_age'] ?? 100;
            $isActive = $_POST['is_active'] ?? 1;
            
            if (!$ageId || empty($ageRange)) {
                throw new Exception('Age ID and range are required');
            }
            
            error_log("Updating age specification ID: " . $ageId);
            
            $stmt = $conn->prepare("UPDATE age_specifications SET age_range = ?, age_description = ?, min_age = ?, max_age = ?, is_active = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssiiii", $ageRange, $ageDescription, $minAge, $maxAge, $isActive, $ageId);
            
            if ($stmt->execute()) {
                error_log("Age specification updated successfully");
                echo json_encode(['success' => true, 'message' => 'Age specification updated successfully']);
            } else {
                throw new Exception('Failed to update age specification: ' . $stmt->error);
            }
            break;
            
        case 'delete_age_specification':
            // Delete age specification
            $ageId = $_POST['age_id'] ?? 0;
            
            if (!$ageId) {
                throw new Exception('Age ID is required');
            }
            
            error_log("Deleting age specification ID: " . $ageId);
            
            $stmt = $conn->prepare("DELETE FROM age_specifications WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $ageId);
            
            if ($stmt->execute()) {
                error_log("Age specification deleted successfully");
                echo json_encode(['success' => true, 'message' => 'Age specification deleted successfully']);
            } else {
                throw new Exception('Failed to delete age specification: ' . $stmt->error);
            }
            break;
            
        case 'update_appearance_specification':
            // Update appearance specification
            $specId = $_POST['item_id'] ?? 0;
            $specificationName = $_POST['specification_name'] ?? '';
            $description = $_POST['description'] ?? '';
            $category = $_POST['category'] ?? '';
            $status = $_POST['status'] ?? 'active';
            
            if (!$specId || empty($specificationName)) {
                throw new Exception('Specification ID and name are required');
            }
            
            error_log("Updating appearance specification ID: " . $specId);
            
            $stmt = $conn->prepare("UPDATE appearance_specifications SET specification_name = ?, description = ?, category = ?, status = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("ssssi", $specificationName, $description, $category, $status, $specId);
            
            if ($stmt->execute()) {
                error_log("Appearance specification updated successfully");
                echo json_encode(['success' => true, 'message' => 'Appearance specification updated successfully']);
            } else {
                throw new Exception('Failed to update appearance specification: ' . $stmt->error);
            }
            break;
            
        case 'delete_appearance_specification':
            // Delete appearance specification
            $specId = $_POST['spec_id'] ?? 0;
            
            if (!$specId) {
                throw new Exception('Specification ID is required');
            }
            
            error_log("Deleting appearance specification ID: " . $specId);
            
            $stmt = $conn->prepare("DELETE FROM appearance_specifications WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $specId);
            
            if ($stmt->execute()) {
                error_log("Appearance specification deleted successfully");
                echo json_encode(['success' => true, 'message' => 'Appearance specification deleted successfully']);
            } else {
                throw new Exception('Failed to delete appearance specification: ' . $stmt->error);
            }
            break;
            
        case 'update_status_specification':
            // Update status specification
            $statusId = $_POST['item_id'] ?? 0;
            $statusName = $_POST['status_name'] ?? '';
            $statusDescription = $_POST['status_description'] ?? '';
            $colorCode = $_POST['color_code'] ?? '#007bff';
            $isActive = $_POST['is_active'] ?? 1;
            
            if (!$statusId || empty($statusName)) {
                throw new Exception('Status ID and name are required');
            }
            
            error_log("Updating status specification ID: " . $statusId);
            
            $stmt = $conn->prepare("UPDATE status_specifications SET status_name = ?, status_description = ?, color_code = ?, is_active = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssii", $statusName, $statusDescription, $colorCode, $isActive, $statusId);
            
            if ($stmt->execute()) {
                error_log("Status specification updated successfully");
                echo json_encode(['success' => true, 'message' => 'Status specification updated successfully']);
            } else {
                throw new Exception('Failed to update status specification: ' . $stmt->error);
            }
            break;
            
        case 'delete_status_specification':
            // Delete status specification
            $statusId = $_POST['status_id'] ?? 0;
            
            if (!$statusId) {
                throw new Exception('Status ID is required');
            }
            
            error_log("Deleting status specification ID: " . $statusId);
            
            $stmt = $conn->prepare("DELETE FROM status_specifications WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $statusId);
            
            if ($stmt->execute()) {
                error_log("Status specification deleted successfully");
                echo json_encode(['success' => true, 'message' => 'Status specification deleted successfully']);
            } else {
                throw new Exception('Failed to delete status specification: ' . $stmt->error);
            }
            break;
            
        case 'update_arrival_station':
            // Update arrival station
            $stationId = $_POST['item_id'] ?? 0;
            $stationName = $_POST['station_name'] ?? '';
            $stationDescription = $_POST['station_description'] ?? '';
            $location = $_POST['location'] ?? '';
            $isActive = $_POST['is_active'] ?? 1;
            
            if (!$stationId || empty($stationName)) {
                throw new Exception('Station ID and name are required');
            }
            
            error_log("Updating arrival station ID: " . $stationId);
            
            $stmt = $conn->prepare("UPDATE arrival_stations SET station_name = ?, station_description = ?, location = ?, is_active = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssii", $stationName, $stationDescription, $location, $isActive, $stationId);
            
            if ($stmt->execute()) {
                error_log("Arrival station updated successfully");
                echo json_encode(['success' => true, 'message' => 'Arrival station updated successfully']);
            } else {
                throw new Exception('Failed to update arrival station: ' . $stmt->error);
            }
            break;
            
        case 'delete_arrival_station':
            // Delete arrival station
            $stationId = $_POST['station_id'] ?? 0;
            
            if (!$stationId) {
                throw new Exception('Station ID is required');
            }
            
            error_log("Deleting arrival station ID: " . $stationId);
            
            $stmt = $conn->prepare("DELETE FROM arrival_stations WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $stationId);
            
            if ($stmt->execute()) {
                error_log("Arrival station deleted successfully");
                echo json_encode(['success' => true, 'message' => 'Arrival station deleted successfully']);
            } else {
                throw new Exception('Failed to delete arrival station: ' . $stmt->error);
            }
            break;
            
        case 'update_arrival_agency':
            // Update arrival agency
            $agencyId = $_POST['item_id'] ?? 0;
            $agencyName = $_POST['agency_name'] ?? '';
            $agencyDescription = $_POST['agency_description'] ?? '';
            $contactInfo = $_POST['contact_info'] ?? '';
            $isActive = $_POST['is_active'] ?? 1;
            
            if (!$agencyId || empty($agencyName)) {
                throw new Exception('Agency ID and name are required');
            }
            
            error_log("Updating arrival agency ID: " . $agencyId);
            
            $stmt = $conn->prepare("UPDATE arrival_agencies SET agency_name = ?, agency_description = ?, contact_info = ?, is_active = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssii", $agencyName, $agencyDescription, $contactInfo, $isActive, $agencyId);
            
            if ($stmt->execute()) {
                error_log("Arrival agency updated successfully");
                echo json_encode(['success' => true, 'message' => 'Arrival agency updated successfully']);
            } else {
                throw new Exception('Failed to update arrival agency: ' . $stmt->error);
            }
            break;
            
        case 'delete_arrival_agency':
            // Delete arrival agency
            $agencyId = $_POST['agency_id'] ?? 0;
            
            if (!$agencyId) {
                throw new Exception('Agency ID is required');
            }
            
            error_log("Deleting arrival agency ID: " . $agencyId);
            
            $stmt = $conn->prepare("DELETE FROM arrival_agencies WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $agencyId);
            
            if ($stmt->execute()) {
                error_log("Arrival agency deleted successfully");
                echo json_encode(['success' => true, 'message' => 'Arrival agency deleted successfully']);
            } else {
                throw new Exception('Failed to delete arrival agency: ' . $stmt->error);
            }
            break;
            
        case 'update_request_status':
            // Update request status
            $statusId = $_POST['item_id'] ?? 0;
            $statusName = $_POST['status_name'] ?? '';
            $statusDescription = $_POST['status_description'] ?? '';
            $colorCode = $_POST['color_code'] ?? '#007bff';
            $isActive = $_POST['is_active'] ?? 1;
            
            if (!$statusId || empty($statusName)) {
                throw new Exception('Status ID and name are required');
            }
            
            error_log("Updating request status ID: " . $statusId);
            
            $stmt = $conn->prepare("UPDATE request_statuses SET status_name = ?, status_description = ?, color_code = ?, is_active = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssii", $statusName, $statusDescription, $colorCode, $isActive, $statusId);
            
            if ($stmt->execute()) {
                error_log("Request status updated successfully");
                echo json_encode(['success' => true, 'message' => 'Request status updated successfully']);
            } else {
                throw new Exception('Failed to update request status: ' . $stmt->error);
            }
            break;
            
        case 'delete_request_status':
            // Delete request status
            $statusId = $_POST['status_id'] ?? 0;
            
            if (!$statusId) {
                throw new Exception('Status ID is required');
            }
            
            error_log("Deleting request status ID: " . $statusId);
            
            $stmt = $conn->prepare("DELETE FROM request_statuses WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $statusId);
            
            if ($stmt->execute()) {
                error_log("Request status deleted successfully");
                echo json_encode(['success' => true, 'message' => 'Request status deleted successfully']);
            } else {
                throw new Exception('Failed to delete request status: ' . $stmt->error);
            }
            break;
            
        case 'update_worker_status':
            // Update worker status
            $statusId = $_POST['item_id'] ?? 0;
            $statusName = $_POST['status_name'] ?? '';
            $statusDescription = $_POST['status_description'] ?? '';
            $colorCode = $_POST['color_code'] ?? '#007bff';
            $isActive = $_POST['is_active'] ?? 1;
            
            if (!$statusId || empty($statusName)) {
                throw new Exception('Status ID and name are required');
            }
            
            error_log("Updating worker status ID: " . $statusId);
            
            $stmt = $conn->prepare("UPDATE worker_statuses SET status_name = ?, status_description = ?, color_code = ?, is_active = ? WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("sssii", $statusName, $statusDescription, $colorCode, $isActive, $statusId);
            
            if ($stmt->execute()) {
                error_log("Worker status updated successfully");
                echo json_encode(['success' => true, 'message' => 'Worker status updated successfully']);
            } else {
                throw new Exception('Failed to update worker status: ' . $stmt->error);
            }
            break;
            
        case 'delete_worker_status':
            // Delete worker status
            $statusId = $_POST['status_id'] ?? 0;
            
            if (!$statusId) {
                throw new Exception('Status ID is required');
            }
            
            error_log("Deleting worker status ID: " . $statusId);
            
            $stmt = $conn->prepare("DELETE FROM worker_statuses WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $statusId);
            
            if ($stmt->execute()) {
                error_log("Worker status deleted successfully");
                echo json_encode(['success' => true, 'message' => 'Worker status deleted successfully']);
            } else {
                throw new Exception('Failed to delete worker status: ' . $stmt->error);
            }
            break;
            
        default:
            throw new Exception('Invalid action: ' . $action);
    }

} catch (Exception $e) {
    error_log("Update setting error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?> 