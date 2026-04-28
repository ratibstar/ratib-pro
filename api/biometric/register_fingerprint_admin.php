<?php
/**
 * EN: Handles API endpoint/business logic in `api/biometric/register_fingerprint_admin.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/biometric/register_fingerprint_admin.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    // Handle FormData input
    $userId = isset($_POST['userId']) ? (int) $_POST['userId'] : null;
    $username = $_POST['username'] ?? null;
    $fingerprintData = $_POST['fingerprintData'] ?? null;
    
    // Check if admin is logged in (app admin or control panel admin)
    $isAppAdmin = function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user();
    $isControlAdmin = !empty($_SESSION['control_logged_in']);
    if (!$isAppAdmin && !$isControlAdmin) {
        echo json_encode(['success' => false, 'message' => 'Admin not logged in']);
        exit;
    }
    
    if (!$userId || !$fingerprintData) {
        echo json_encode(['success' => false, 'message' => 'User and fingerprint data required']);
        exit;
    }
    
    // Verify the user exists
    $stmt = $conn->prepare("SELECT user_id, username, status FROM users WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $userRow = $result->fetch_assoc();
    
    if (strcasecmp($userRow['status'], 'active') !== 0) {
        echo json_encode(['success' => false, 'message' => 'User account is not active']);
        exit;
    }
    
    // If username was not provided (or mismatched), fall back to DB value
    if (empty($username) || strcasecmp($username, $userRow['username']) !== 0) {
        $username = $userRow['username'];
    }
    
    $conn->begin_transaction();
    try {
        // Clean up any legacy empty templates
        $conn->query("DELETE FROM fingerprint_templates WHERE template_data IS NULL OR template_data = ''");
        
        // Ensure only one template row per user
        $deleteTpl = $conn->prepare("DELETE FROM fingerprint_templates WHERE user_id = ?");
        if ($deleteTpl) {
            $deleteTpl->bind_param("i", $userId);
            $deleteTpl->execute();
            $deleteTpl->close();
        }
        
        // Store fingerprint template in database (THIS IS THE ONLY REQUIRED OPERATION)
        $stmt = $conn->prepare("
            INSERT INTO fingerprint_templates (user_id, template_data) 
            VALUES (?, ?)
        ");
        
        $stmt->bind_param("is", $userId, $fingerprintData);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to store fingerprint template: ' . $stmt->error);
        }
        
        $templateId = $stmt->insert_id;
        $stmt->close();
        
        // Verify the template was saved correctly
        $verifyStmt = $conn->prepare("SELECT template_data FROM fingerprint_templates WHERE id = ? AND template_data IS NOT NULL AND template_data <> ''");
        $verifyStmt->bind_param("i", $templateId);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        
        if ($verifyResult->num_rows === 0) {
            throw new Exception('Fingerprint template was not saved correctly');
        }
        $verifyStmt->close();
        
        // OPTIONAL: Try to store in biometric_credentials table (completely optional, failures are ignored)
        // We'll use a simple approach: only insert if we can verify the exact columns exist
        try {
            $tablesResult = $conn->query("SHOW TABLES LIKE 'biometric_credentials'");
            if ($tablesResult && $tablesResult->num_rows > 0) {
            // Get actual columns from the table
            $columnsResult = $conn->query("SHOW COLUMNS FROM biometric_credentials");
            $actualColumns = [];
            if ($columnsResult) {
                while ($row = $columnsResult->fetch_assoc()) {
                    $actualColumns[] = strtolower($row['Field']);
                }
                $columnsResult->free();
            }
            
            // Only build insert if we have the minimum required columns
            if (in_array('user_id', $actualColumns) && in_array('credential_data', $actualColumns)) {
                $credentialData = json_encode([
                    'fingerprintData' => $fingerprintData,
                    'deviceInfo' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'registrationTime' => date('Y-m-d H:i:s'),
                    'registeredBy' => $_SESSION['user_id']
                ]);
                
                // Build safe insert with only columns that exist
                $insertColumns = ['user_id', 'credential_data'];
                $insertValues = [$userId, $credentialData];
                $insertTypes = 'is';
                
                // Add optional columns only if they exist
                if (in_array('credential_type', $actualColumns)) {
                    $insertColumns[] = 'credential_type';
                    $insertValues[] = 'fingerprint';
                    $insertTypes .= 's';
                }
                if (in_array('device_id', $actualColumns)) {
                    $insertColumns[] = 'device_id';
                    $insertValues[] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                    $insertTypes .= 's';
                }
                if (in_array('is_active', $actualColumns)) {
                    $insertColumns[] = 'is_active';
                    $insertValues[] = 1;
                    $insertTypes .= 'i';
                }
                
                // Delete old credentials first
                $deleteCred = $conn->prepare("DELETE FROM biometric_credentials WHERE user_id = ?");
                if ($deleteCred) {
                    $deleteCred->bind_param("i", $userId);
                    $deleteCred->execute();
                    $deleteCred->close();
                }
                
                // Build and execute insert
                $columnList = '`' . implode('`, `', $insertColumns) . '`';
                $placeholders = str_repeat('?,', count($insertColumns) - 1) . '?';
                $insertSql = "INSERT INTO biometric_credentials ($columnList) VALUES ($placeholders)";
                
                $credStmt = $conn->prepare($insertSql);
                if ($credStmt) {
                    $credStmt->bind_param($insertTypes, ...$insertValues);
                    $credStmt->execute();
                    $credStmt->close();
                }
            }
            }
        } catch (Exception $e) {
            // Ignore errors in optional biometric_credentials insert
            error_log("Note: Could not insert into biometric_credentials: " . $e->getMessage());
        }
        
        // OPTIONAL: Try to log the registration (completely optional, failures are ignored)
        try {
            $logTablesResult = $conn->query("SHOW TABLES LIKE 'biometric_logs'");
            if ($logTablesResult && $logTablesResult->num_rows > 0) {
            // Get actual columns from the table
            $logColumnsResult = $conn->query("SHOW COLUMNS FROM biometric_logs");
            $actualLogColumns = [];
            if ($logColumnsResult) {
                while ($row = $logColumnsResult->fetch_assoc()) {
                    $actualLogColumns[] = strtolower($row['Field']);
                }
                $logColumnsResult->free();
            }
            
            // Build log insert with only columns that exist
            $logInsertColumns = [];
            $logInsertValues = [];
            $logInsertTypes = '';
            
            if (in_array('user_id', $actualLogColumns)) {
                $logInsertColumns[] = 'user_id';
                $logInsertValues[] = $userId;
                $logInsertTypes .= 'i';
            }
            
            // Check for credential_type or biometric_type
            if (in_array('credential_type', $actualLogColumns)) {
                $logInsertColumns[] = 'credential_type';
                $logInsertValues[] = 'fingerprint';
                $logInsertTypes .= 's';
            } elseif (in_array('biometric_type', $actualLogColumns)) {
                $logInsertColumns[] = 'biometric_type';
                $logInsertValues[] = 'fingerprint';
                $logInsertTypes .= 's';
            }
            
            // Check for authentication_result or success
            if (in_array('authentication_result', $actualLogColumns)) {
                $logInsertColumns[] = 'authentication_result';
                $logInsertValues[] = 'success';
                $logInsertTypes .= 's';
            } elseif (in_array('success', $actualLogColumns)) {
                $logInsertColumns[] = 'success';
                $logInsertValues[] = 1;
                $logInsertTypes .= 'i';
            }
            
            if (in_array('action_type', $actualLogColumns)) {
                $logInsertColumns[] = 'action_type';
                $logInsertValues[] = 'registration';
                $logInsertTypes .= 's';
            }
            
            if (in_array('confidence_score', $actualLogColumns)) {
                $logInsertColumns[] = 'confidence_score';
                $logInsertValues[] = 1.0;
                $logInsertTypes .= 'd';
            }
            
            if (in_array('device_info', $actualLogColumns)) {
                $logInsertColumns[] = 'device_info';
                $logInsertValues[] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $logInsertTypes .= 's';
            }
            
            if (in_array('ip_address', $actualLogColumns)) {
                $logInsertColumns[] = 'ip_address';
                $logInsertValues[] = $_SERVER['REMOTE_ADDR'] ?? '';
                $logInsertTypes .= 's';
            }
            
            if (in_array('user_agent', $actualLogColumns)) {
                $logInsertColumns[] = 'user_agent';
                $logInsertValues[] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $logInsertTypes .= 's';
            }
            
            // Only insert if we have at least one column
            if (!empty($logInsertColumns)) {
                $logColumnList = '`' . implode('`, `', $logInsertColumns) . '`';
                $logPlaceholders = str_repeat('?,', count($logInsertColumns) - 1) . '?';
                $logInsertSql = "INSERT INTO biometric_logs ($logColumnList) VALUES ($logPlaceholders)";
                
                $logStmt = $conn->prepare($logInsertSql);
                if ($logStmt) {
                    $logStmt->bind_param($logInsertTypes, ...$logInsertValues);
                    $logStmt->execute();
                    $logStmt->close();
                }
            }
            }
        } catch (Exception $e) {
            // Ignore errors in optional biometric_logs insert
            error_log("Note: Could not insert into biometric_logs: " . $e->getMessage());
        }
        
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Fingerprint biometric registered successfully for ' . $username,
            'templateId' => $templateId,
            'userId' => $userId,
            'username' => $username
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
