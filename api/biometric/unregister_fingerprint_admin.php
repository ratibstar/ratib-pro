<?php
/**
 * EN: Handles API endpoint/business logic in `api/biometric/unregister_fingerprint_admin.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/biometric/unregister_fingerprint_admin.php`.
 */
require_once '../../includes/config.php';

header('Content-Type: application/json');

try {
    // Handle FormData or JSON input
    $userId = isset($_POST['userId']) ? (int) $_POST['userId'] : (isset($_GET['userId']) ? (int) $_GET['userId'] : null);
    $username = $_POST['username'] ?? $_GET['username'] ?? null;
    
    // Check if admin is logged in (app admin or control panel admin)
    $isAppAdmin = function_exists('ratib_program_session_is_valid_user') && ratib_program_session_is_valid_user();
    $isControlAdmin = !empty($_SESSION['control_logged_in']);
    if (!$isAppAdmin && !$isControlAdmin) {
        echo json_encode(['success' => false, 'message' => 'Admin not logged in']);
        exit;
    }
    
    if (!$userId) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        exit;
    }
    
    // Verify the user exists (admin can unregister for any user, even inactive)
    $stmt = $conn->prepare("SELECT user_id, username FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    $user = $result->fetch_assoc();
    $actualUsername = $user['username'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete fingerprint template
        $deleteTpl = $conn->prepare("DELETE FROM fingerprint_templates WHERE user_id = ?");
        $deleteTpl->bind_param("i", $userId);
        $deleteTpl->execute();
        $templatesDeleted = $deleteTpl->affected_rows;
        
        // Delete WebAuthn credentials (this is what the status query checks)
        $webauthnDeleted = 0;
        $tablesResult = $conn->query("SHOW TABLES LIKE 'webauthn_credentials'");
        if ($tablesResult && $tablesResult->num_rows > 0) {
            $deleteWebAuthn = $conn->prepare("DELETE FROM webauthn_credentials WHERE user_id = ?");
            if ($deleteWebAuthn) {
                $deleteWebAuthn->bind_param("i", $userId);
                $deleteWebAuthn->execute();
                $webauthnDeleted = $deleteWebAuthn->affected_rows;
                $deleteWebAuthn->close();
            }
        }
        
        // Delete biometric credentials (if table exists)
        $credentialsDeleted = 0;
        $tablesResult = $conn->query("SHOW TABLES LIKE 'biometric_credentials'");
        if ($tablesResult && $tablesResult->num_rows > 0) {
            // Check if credential_type column exists
            $hasCredentialType = false;
            $columnsResult = $conn->query("SHOW COLUMNS FROM biometric_credentials");
            if ($columnsResult) {
                while ($col = $columnsResult->fetch_assoc()) {
                    if (strcasecmp($col['Field'], 'credential_type') === 0) {
                        $hasCredentialType = true;
                        break;
                    }
                }
                $columnsResult->free();
            }
            
            // Build delete query based on available columns
            if ($hasCredentialType) {
                $deleteCred = $conn->prepare("DELETE FROM biometric_credentials WHERE user_id = ? AND credential_type = 'fingerprint'");
            } else {
                $deleteCred = $conn->prepare("DELETE FROM biometric_credentials WHERE user_id = ?");
            }
            if ($deleteCred) {
                $deleteCred->bind_param("i", $userId);
                $deleteCred->execute();
                $credentialsDeleted = $deleteCred->affected_rows;
                $deleteCred->close();
            }
        }
        
        // Log the unregistration (completely optional, failures are ignored)
        try {
            $tablesResult = $conn->query("SHOW TABLES LIKE 'biometric_logs'");
            if ($tablesResult && $tablesResult->num_rows > 0) {
                // Get actual columns from the table
                $columnsResult = $conn->query("SHOW COLUMNS FROM biometric_logs");
                $actualColumns = [];
                if ($columnsResult) {
                    while ($col = $columnsResult->fetch_assoc()) {
                        $actualColumns[] = strtolower($col['Field']);
                    }
                    $columnsResult->free();
                }
                
                // Build insert with only columns that exist
                $logInsertColumns = [];
                $logInsertValues = [];
                $logInsertTypes = '';
                
                if (in_array('user_id', $actualColumns)) {
                    $logInsertColumns[] = 'user_id';
                    $logInsertValues[] = $userId;
                    $logInsertTypes .= 'i';
                }
                
                // Check for credential_type or biometric_type
                if (in_array('credential_type', $actualColumns)) {
                    $logInsertColumns[] = 'credential_type';
                    $logInsertValues[] = 'fingerprint';
                    $logInsertTypes .= 's';
                } elseif (in_array('biometric_type', $actualColumns)) {
                    $logInsertColumns[] = 'biometric_type';
                    $logInsertValues[] = 'fingerprint';
                    $logInsertTypes .= 's';
                }
                
                // Check for authentication_result or success
                if (in_array('authentication_result', $actualColumns)) {
                    $logInsertColumns[] = 'authentication_result';
                    $logInsertValues[] = 'unregistered';
                    $logInsertTypes .= 's';
                } elseif (in_array('success', $actualColumns)) {
                    $logInsertColumns[] = 'success';
                    $logInsertValues[] = 0;
                    $logInsertTypes .= 'i';
                }
                
                if (in_array('action_type', $actualColumns)) {
                    $logInsertColumns[] = 'action_type';
                    $logInsertValues[] = 'unregistration';
                    $logInsertTypes .= 's';
                }
                
                if (in_array('device_info', $actualColumns)) {
                    $logInsertColumns[] = 'device_info';
                    $logInsertValues[] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $logInsertTypes .= 's';
                }
                
                if (in_array('ip_address', $actualColumns)) {
                    $logInsertColumns[] = 'ip_address';
                    $logInsertValues[] = $_SERVER['REMOTE_ADDR'] ?? '';
                    $logInsertTypes .= 's';
                }
                
                if (in_array('user_agent', $actualColumns)) {
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
            // Ignore logging errors - unregistration should still work
            error_log("Note: Could not log fingerprint unregistration: " . $e->getMessage());
        }
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Fingerprint unregistered successfully for ' . $actualUsername,
            'userId' => $userId,
            'username' => $actualUsername,
            'templatesDeleted' => $templatesDeleted,
            'webauthnDeleted' => $webauthnDeleted,
            'credentialsDeleted' => $credentialsDeleted
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

