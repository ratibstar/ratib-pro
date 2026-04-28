<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/permissions.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/permissions.php`.
 */
/**
 * Permissions System for Ratibprogram
 * This file contains all permission-related functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure config.php is loaded for database connection if not already loaded
if (!defined('DB_HOST')) {
    $configPath = __DIR__ . '/config.php';
    if (file_exists($configPath)) {
        require_once $configPath;
    }
}

// Ensure database connection exists
if (!isset($GLOBALS['conn']) || $GLOBALS['conn'] === null) {
    // Try to create connection if config constants are available
    if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $GLOBALS['conn'] = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, defined('DB_PORT') ? DB_PORT : 3306);
            $GLOBALS['conn']->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log("Failed to create database connection in permissions.php: " . $e->getMessage());
            $GLOBALS['conn'] = null;
        }
    }
}

if (!function_exists('ratib_users_primary_key_column')) {
    /**
     * Some tenant DBs use `users.id` instead of `users.user_id`. save_user_permissions.php already
     * resolves the PK; permission checks must use the same column or user-specific JSON is never read
     * and role_id=1 is treated as full admin (all permissions).
     *
     * @param mysqli|PDO $conn
     * @return 'user_id'|'id'
     */
    function ratib_users_primary_key_column($conn): string
    {
        if ($conn instanceof mysqli) {
            static $cache = [];
            $oid = spl_object_hash($conn);
            if (isset($cache[$oid])) {
                return $cache[$oid];
            }
            $pk = 'user_id';
            $chk = @$conn->query("SHOW COLUMNS FROM users LIKE 'user_id'");
            if ($chk && $chk->num_rows > 0) {
                $cache[$oid] = $pk;
                return $pk;
            }
            $chk2 = @$conn->query("SHOW COLUMNS FROM users LIKE 'id'");
            if ($chk2 && $chk2->num_rows > 0) {
                $pk = 'id';
            }
            $cache[$oid] = $pk;
            return $pk;
        }
        if ($conn instanceof PDO) {
            static $cachePdo = [];
            $oid = spl_object_hash($conn);
            if (isset($cachePdo[$oid])) {
                return $cachePdo[$oid];
            }
            $pk = 'user_id';
            try {
                $r = $conn->query("SHOW COLUMNS FROM users LIKE 'user_id'");
                if ($r && $r->rowCount() > 0) {
                    $cachePdo[$oid] = $pk;
                    return $pk;
                }
                $r2 = $conn->query("SHOW COLUMNS FROM users LIKE 'id'");
                if ($r2 && $r2->rowCount() > 0) {
                    $pk = 'id';
                }
            } catch (Throwable $e) {
                /* ignore */
            }
            $cachePdo[$oid] = $pk;
            return $pk;
        }
        return 'user_id';
    }
}

/**
 * Check if user has a specific permission
 * @param string $permission The permission ID to check (e.g., 'view_users', 'add_agent')
 * @return bool True if user has permission, false otherwise
 */
function hasPermission($permission) {
    // Check if user is logged in (must match a real `users` row)
    if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] < 1
        || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        error_log("Permission check: User not logged in");
        return false;
    }
    if (isset($_SESSION['username']) && is_string($_SESSION['username'])
        && strncmp($_SESSION['username'], 'Control:', 8) === 0) {
        return false;
    }
    
    // Get global connection
    global $conn;
    
    // Ensure connection is available
    if (!isset($conn) || $conn === null || !is_object($conn) || !($conn instanceof mysqli)) {
        // Try to initialize connection if constants are available
        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
            try {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                $conn = new mysqli(
                    DB_HOST, 
                    DB_USER, 
                    DB_PASS, 
                    DB_NAME, 
                    defined('DB_PORT') ? DB_PORT : 3306
                );
                $conn->set_charset("utf8mb4");
            } catch (Exception $e) {
                error_log("Permission check: Failed to create database connection: " . $e->getMessage());
                return false;
            }
        } else {
            error_log("Permission check: Database connection not available and constants not defined");
            return false;
        }
    }
    
    try {
        // FIRST: Check user-specific permissions (they override role permissions AND admin status)
        // This works even if role_id is NULL
        $hasUserSpecificPermissions = false;
        try {
            $checkStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'permissions'");
            $columnExists = $checkStmt->num_rows > 0;
            
            if ($columnExists) {
                $pk = ratib_users_primary_key_column($conn);
                $stmt = $conn->prepare("SELECT permissions FROM users WHERE `{$pk}` = ?");
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    $stmt->close();
                    
                    // Check if user has custom permissions set (not NULL and not empty array)
                    if (isset($user['permissions']) && $user['permissions'] !== null && trim($user['permissions']) !== '' && trim($user['permissions']) !== '[]') {
                        $userPermissions = json_decode($user['permissions'], true);
                        if (is_array($userPermissions) && count($userPermissions) > 0) {
                            $hasUserSpecificPermissions = true;
                            // Check if permission exists in user-specific permissions
                            $hasPermission = in_array($permission, $userPermissions, true);
                            error_log("Permission check: User {$_SESSION['user_id']} has user-specific permissions: " . json_encode($userPermissions) . ", checking '{$permission}' = " . ($hasPermission ? 'GRANTED' : 'DENIED'));
                            return $hasPermission;
                        }
                    }
                    // No explicit user-specific permissions:
                    // continue to admin/role checks below (do not deny early).
                } else {
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            error_log("Permissions column check failed: " . $e->getMessage());
        }
        
        // SECOND: If no user-specific permissions, check admin status
        // Admin (role_id = 1) has all permissions ONLY if no user-specific permissions are set
        if (!$hasUserSpecificPermissions && isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
            error_log("Permission check: User {$_SESSION['user_id']} is ADMIN (role_id=1) with no user-specific permissions - GRANTING ALL");
    return true;
}
        
        // THIRD: If no user-specific permissions and role_id exists, check role permissions
        if (isset($_SESSION['role_id']) && !empty($_SESSION['role_id'])) {
            $stmt = $conn->prepare("SELECT permissions FROM roles WHERE role_id = ?");
            $stmt->bind_param("i", $_SESSION['role_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $role = $result->fetch_assoc();
                $stmt->close();
                
                // If no permissions stored, deny access
                if (!empty($role['permissions'])) {
                    // Decode JSON permissions array
                    $permissions = json_decode($role['permissions'], true);
                    
                    // If JSON decode succeeded and is an array, check permission
                    if (is_array($permissions)) {
                        $hasPermission = in_array($permission, $permissions, true);
                        error_log("Permission check: User {$_SESSION['user_id']} role_id={$_SESSION['role_id']} has role permissions: " . json_encode($permissions) . ", checking '{$permission}' = " . ($hasPermission ? 'GRANTED' : 'DENIED'));
                        return $hasPermission;
                    }
                }
            } else {
                $stmt->close();
            }
        }
        
        // If user has no role_id and no user-specific permissions, deny access
        error_log("Permission check: User {$_SESSION['user_id']} has no role_id and no user-specific permissions - denying '{$permission}'");
        return false;
        
    } catch (Exception $e) {
        error_log("Error checking permission: " . $e->getMessage() . " | Stack: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Get user permissions from database (from role)
 * @return array Array of permission IDs that the user has
 */
function getUserPermissions() {
    global $conn;
    
    // Ensure connection is available
    if (!isset($conn) || $conn === null || !is_object($conn) || !($conn instanceof mysqli)) {
        // Try to initialize connection if constants are available
        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
            try {
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                $conn = new mysqli(
                    DB_HOST, 
                    DB_USER, 
                    DB_PASS, 
                    DB_NAME, 
                    defined('DB_PORT') ? DB_PORT : 3306
                );
                $conn->set_charset("utf8mb4");
            } catch (Exception $e) {
                error_log("getUserPermissions: Failed to create database connection: " . $e->getMessage());
                return [];
            }
        } else {
            error_log("getUserPermissions: Database connection not available and constants not defined");
            return [];
        }
    }
    
    if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] < 1) {
        return [];
    }
    
    try {
        // FIRST: Check if user has specific permissions (they override everything including admin)
        $hasUserSpecificPermissions = false;
        try {
            $checkStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'permissions'");
            $columnExists = $checkStmt->num_rows > 0;
            
            if ($columnExists) {
                $pk = ratib_users_primary_key_column($conn);
                $stmt = $conn->prepare("SELECT permissions FROM users WHERE `{$pk}` = ?");
                $stmt->bind_param("i", $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    $stmt->close();
                    
                    // If user has custom permissions set (not NULL and not empty array), use those
                    $raw = isset($user['permissions']) ? trim($user['permissions']) : null;
                    if ($raw !== null && $raw !== '' && $raw !== '[]') {
                        $userPermissions = json_decode($user['permissions'], true);
                        if (is_array($userPermissions) && count($userPermissions) > 0) {
                            $hasUserSpecificPermissions = true;
                            return $userPermissions;
                        }
                    }
                    // NULL or empty []: fall through so admin gets ['*'], others get [] below
                } else {
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            error_log("Error checking user-specific permissions: " . $e->getMessage());
        }
        
        // SECOND: If no user-specific permissions, check admin status
        // Admin (role_id = 1) has all permissions ONLY if no user-specific permissions are set
        if (!$hasUserSpecificPermissions && isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
            return ['*']; // Special marker for admin
        }
        
        // THIRD: For regular users, DO NOT use role permissions for UI visibility
        // Users see NOTHING unless explicitly granted user-specific permissions
        // Role permissions are NOT used for UI - only user-specific permissions matter
        // This ensures new users see nothing by default
        
        // No permissions found - return empty array (user sees nothing)
        return [];
        
    } catch (Exception $e) {
        error_log("Error getting user permissions: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if user has any of the specified permissions
 * @param array $permissions Array of permission IDs to check
 * @return bool True if user has at least one permission
 */
function hasAnyPermission($permissions) {
    if (!is_array($permissions) || empty($permissions)) {
        return false;
    }
    
    foreach ($permissions as $permission) {
        if (hasPermission($permission)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Check if user has all of the specified permissions
 * @param array $permissions Array of permission IDs to check
 * @return bool True if user has all permissions
 */
function hasAllPermissions($permissions) {
    if (!is_array($permissions) || empty($permissions)) {
        return false;
    }
    
    foreach ($permissions as $permission) {
        if (!hasPermission($permission)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Check if current user is admin
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    return (int)($_SESSION['user_id'] ?? 0) > 0
        && isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1;
}

/**
 * Get user role name
 * @return string Role name
 */
function getUserRole() {
    global $conn;
    
    if (!isset($_SESSION['role_id'])) {
        return 'Guest';
    }
    
    try {
        $stmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
        $stmt->bind_param("i", $_SESSION['role_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            return $row['role_name'];
        }
        
        return 'Unknown';
    } catch (Exception $e) {
        error_log("Error getting user role: " . $e->getMessage());
        return 'Unknown';
    }
}

/**
 * Check permission or show unauthorized message
 * @param string $permission The permission required
 */
function checkPermissionOrShowUnauthorized($permission) {
    if (!hasPermission($permission)) {
        http_response_code(403);
        require_once __DIR__ . '/final_overlay.php';
        showFinalOverlay($permission);
        exit;
    }
}

/**
 * Log user activity
 * @param string $action The action performed
 * @param string $description Description of the action
 */
function logActivity($action, $description = '') {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt->bind_param("issss", 
            $_SESSION['user_id'], 
            $action, 
            $description, 
            $ip, 
            $userAgent
        );
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}
