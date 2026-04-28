<?php
/**
 * EN: Handles API endpoint/business logic in `api/settings/get_permissions_groups.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/settings/get_permissions_groups.php`.
 */
// EN: Start buffered mode to keep output strictly JSON and suppress noise.
// AR: تشغيل التخزين المؤقت للمخرجات لضمان استجابات JSON فقط ومنع أي تسريبات.
// Start output buffering to prevent any output before JSON
if (!ob_get_level()) {
    ob_start();
}

// Disable error display but keep error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// EN: Bootstrap session/config early for tenant-aware permission context.
// AR: تهيئة الجلسة والإعدادات مبكراً لضمان سياق صلاحيات مرتبط بالمستأجر.
// Load config first for session and DB
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/permissions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

// EN: Database loader prefers core singleton API then falls back to legacy config class.
// AR: محمل قاعدة البيانات يفضل نسخة النواة (singleton) ثم يرجع للمسار القديم عند الحاجة.
// Try to require Database class - load core Database first (has getInstance), then fallback
try {
    $coreDatabasePath = __DIR__ . '/../core/Database.php';
    $configDatabasePath = __DIR__ . '/../../config/database.php';
    
    // Load core Database class FIRST (has getInstance method) before config/database.php
    if (file_exists($coreDatabasePath)) {
        require_once $coreDatabasePath;
        error_log("get_permissions_groups: Core Database class loaded successfully");
    }
    
    // Load config/database.php only if Database class doesn't have getInstance (fallback)
    if (!class_exists('Database') || !method_exists('Database', 'getInstance')) {
        if (file_exists($configDatabasePath)) {
            require_once $configDatabasePath;
            error_log("get_permissions_groups: Database config loaded successfully (fallback)");
        } else {
            throw new Exception('Database config file not found at: ' . $configDatabasePath);
        }
    }
    
    if (!class_exists('Database')) {
        throw new Exception('Database class not found after loading both core and config files');
    }
} catch (Exception $e) {
    ob_clean();
    error_log("get_permissions_groups: Database class loading error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database configuration error: ' . $e->getMessage()
    ]);
    ob_end_flush();
    exit;
} catch (Throwable $e) {
    ob_clean();
    error_log("get_permissions_groups: Database class loading fatal error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database configuration fatal error: ' . $e->getMessage()
    ]);
    ob_end_flush();
    exit;
}

// EN: Fatal shutdown handler returns structured JSON for unrecoverable runtime crashes.
// AR: معالج إنهاء للأخطاء الحرجة يعيد JSON منظم عند الأعطال غير القابلة للاستمرار.
// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        exit;
    }
});

// EN: Resolve caller scope, load effective permission set, and build grouped response payload.
// AR: تحديد نطاق المستدعي، تحميل الصلاحيات الفعالة، ثم بناء الاستجابة المجمعة.
try {
    $isAppUser = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] >= 1
        && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    if (!$isAppUser) {
        ob_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Admin not logged in']);
        ob_end_flush();
        exit;
    }
    
    // Check if Database class exists
    if (!class_exists('Database')) {
        throw new Exception('Database class not found. Please check config/database.php');
    }
    
    // Create database connection with error handling
    // Try getInstance() if available (core Database), otherwise use new Database()
    try {
        if (method_exists('Database', 'getInstance')) {
            $db = Database::getInstance();
        } else {
            $db = new Database();
        }
        $conn = $db->getConnection(); // PDO connection
        
        if (!$conn) {
            throw new Exception('Failed to get database connection');
        }
    } catch (Exception $dbError) {
        error_log("Database connection error in get_permissions_groups.php: " . $dbError->getMessage());
        throw new Exception('Database connection failed: ' . $dbError->getMessage());
    }
    
    // Same mysqli as save_user_permissions.php: $GLOBALS['conn'] after includes/config.php (agency-aware in SINGLE_URL_MODE).
    // Do NOT override with a second connection using country_id LIMIT 1 — that picked the wrong agency when several share a country.
    $appConn = (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) ? $GLOBALS['conn'] : null;
    $countryConn = null;
    $sessionCountryId = isset($_SESSION['country_id']) ? (int)$_SESSION['country_id'] : 0;
    $sessionAgencyId = isset($_SESSION['agency_id']) ? (int)$_SESSION['agency_id'] : 0;
    $getAgencyId = isset($_GET['agency_id']) ? (int)$_GET['agency_id'] : 0;
    $sessionLoggedIn = !empty($_SESSION['logged_in']);
    if ((!$appConn || !($appConn instanceof mysqli)) && defined('SINGLE_URL_MODE') && SINGLE_URL_MODE && $sessionLoggedIn && ($sessionAgencyId > 0 || $sessionCountryId > 0 || $getAgencyId > 0)) {
        $lookupConn = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
        if ($lookupConn instanceof mysqli) {
            $chk = @$lookupConn->query("SHOW TABLES LIKE 'control_agencies'");
            if ($chk && $chk->num_rows > 0) {
                $susp = function_exists('ratib_control_agency_active_fragment')
                    ? ratib_control_agency_active_fragment($lookupConn, 'a')
                    : '1=1';
                $row = null;
                if ($sessionAgencyId > 0) {
                    $stA = @$lookupConn->prepare("SELECT db_host, db_port, db_user, db_pass, db_name FROM control_agencies a WHERE a.id = ? AND a.is_active = 1 AND {$susp} LIMIT 1");
                    if ($stA) {
                        $stA->bind_param('i', $sessionAgencyId);
                        $stA->execute();
                        $rA = $stA->get_result();
                        if ($rA && $rA->num_rows > 0) {
                            $row = $rA->fetch_assoc();
                        }
                        $stA->close();
                    }
                }
                if ($row === null && $getAgencyId > 0) {
                    $stG = @$lookupConn->prepare("SELECT db_host, db_port, db_user, db_pass, db_name, country_id FROM control_agencies a WHERE a.id = ? AND a.is_active = 1 AND {$susp} LIMIT 1");
                    if ($stG) {
                        $stG->bind_param('i', $getAgencyId);
                        $stG->execute();
                        $rG = $stG->get_result();
                        if ($rG && $rG->num_rows > 0) {
                            $try = $rG->fetch_assoc();
                            $cid = (int)($try['country_id'] ?? 0);
                            if ($sessionCountryId <= 0 || $cid === $sessionCountryId || (isset($_SESSION['role_id']) && (int)$_SESSION['role_id'] === 1)) {
                                unset($try['country_id']);
                                $row = $try;
                            }
                        }
                        $stG->close();
                    }
                }
                if ($row === null && $sessionCountryId > 0) {
                    $stC = @$lookupConn->prepare("SELECT db_host, db_port, db_user, db_pass, db_name FROM control_agencies a WHERE a.country_id = ? AND a.is_active = 1 AND {$susp} ORDER BY a.id ASC LIMIT 1");
                    if ($stC) {
                        $stC->bind_param('i', $sessionCountryId);
                        $stC->execute();
                        $rC = $stC->get_result();
                        if ($rC && $rC->num_rows > 0) {
                            $row = $rC->fetch_assoc();
                        }
                        $stC->close();
                    }
                }
                if ($row !== null) {
                    try {
                        $port = (int)($row['db_port'] ?? 3306);
                        $countryConn = new mysqli(
                            $row['db_host'],
                            $row['db_user'],
                            $row['db_pass'],
                            $row['db_name'],
                            $port
                        );
                        $countryConn->set_charset('utf8mb4');
                    } catch (Throwable $e) {
                        error_log('get_permissions_groups: tenant DB connect failed: ' . $e->getMessage());
                        $countryConn = null;
                    }
                }
            }
        }
    }
    $permReadConn = ($appConn instanceof mysqli) ? $appConn : $countryConn;
    
    // Parse permissions from DB (JSON column may come as string or already-decoded array in some drivers)
    $parse_json_permissions = function ($raw) {
        if ($raw === null || $raw === '') return [];
        if (is_array($raw)) return array_map('strval', $raw);
        if (is_string($raw)) {
            $dec = json_decode($raw, true);
            return is_array($dec) ? array_map('strval', $dec) : [];
        }
        return [];
    };
    
    // Get current user's permissions to filter what they can assign
    // CRITICAL: Admins (role_id = 1) and Control Panel users ALWAYS see ALL permissions for assignment
    $currentUserPermissions = [];
    $currentUserIsAdmin = false;
    $hasUserSpecificPermissions = false;
    
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
        $currentUserIsAdmin = true;
        $currentUserPermissions = ['*']; // Special marker to show ALL permissions
        error_log("Permission filtering: User {$_SESSION['user_id']} is ADMIN (role_id=1) - showing ALL permissions including new ones for assignment");
    } elseif (isset($_SESSION['user_id'])) {
        // For non-admin users, check their actual permissions (use permReadConn = country DB when available)
        try {
            if ($permReadConn instanceof mysqli) {
                $chk = @$permReadConn->query("SHOW COLUMNS FROM users LIKE 'permissions'");
                $columnExists = $chk && $chk->num_rows > 0;
                if ($columnExists) {
                    $pkCur = ratib_users_primary_key_column($permReadConn);
                    $stmt = $permReadConn->prepare("SELECT permissions FROM users WHERE `{$pkCur}` = ?");
                    $stmt->bind_param('i', $_SESSION['user_id']);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $user = $res ? $res->fetch_assoc() : null;
                    if ($user && isset($user['permissions']) && $user['permissions'] !== null) {
                        $hasUserSpecificPermissions = true;
                        $currentUserPermissions = json_decode($user['permissions'], true) ?: [];
                        error_log("Permission filtering: User {$_SESSION['user_id']} has user-specific permissions: " . count($currentUserPermissions) . " permissions");
                    }
                }
                if (!$hasUserSpecificPermissions && isset($_SESSION['role_id'])) {
                    $roleStmt = $permReadConn->prepare("SELECT permissions FROM roles WHERE role_id = ?");
                    $rid = (int)$_SESSION['role_id'];
                    $roleStmt->bind_param('i', $rid);
                    $roleStmt->execute();
                    $rres = $roleStmt->get_result();
                    $role = $rres ? $rres->fetch_assoc() : null;
                    if ($role && isset($role['permissions']) && $role['permissions'] !== '' && $role['permissions'] !== null) {
                        $currentUserPermissions = json_decode($role['permissions'], true) ?: [];
                        error_log("Permission filtering: User {$_SESSION['user_id']} has role permissions: " . count($currentUserPermissions) . " permissions");
                    }
                }
            } else {
                $checkStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'permissions'");
                $columnExists = $checkStmt->rowCount() > 0;
                if ($columnExists) {
                    $pkCur = ratib_users_primary_key_column($conn);
                    $stmt = $conn->prepare("SELECT permissions FROM users WHERE `{$pkCur}` = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user && isset($user['permissions']) && $user['permissions'] !== null) {
                        $hasUserSpecificPermissions = true;
                        $currentUserPermissions = json_decode($user['permissions'], true) ?: [];
                        error_log("Permission filtering: User {$_SESSION['user_id']} has user-specific permissions: " . count($currentUserPermissions) . " permissions");
                    }
                }
                if (!$hasUserSpecificPermissions && isset($_SESSION['role_id'])) {
                    $roleStmt = $conn->prepare("SELECT permissions FROM roles WHERE role_id = ?");
                    $roleStmt->execute([$_SESSION['role_id']]);
                    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
                    if ($role && isset($role['permissions']) && !empty($role['permissions'])) {
                        $currentUserPermissions = json_decode($role['permissions'], true) ?: [];
                        error_log("Permission filtering: User {$_SESSION['user_id']} has role permissions: " . count($currentUserPermissions) . " permissions");
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error getting current user permissions: " . $e->getMessage());
            $currentUserPermissions = [];
        } catch (Throwable $e) {
            error_log("Error getting current user permissions: " . $e->getMessage());
            $currentUserPermissions = [];
        }
    }
    
    // Get role_id or user_id from query parameter
    $roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
    
    // Validate user_id if provided
    if ($userId !== null && $userId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user ID'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Validate role_id if provided
    if ($roleId !== null && $roleId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid role ID'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    // Master control-panel vocabulary (countries, global agencies, etc.) — only when NOT inside a program/agency workspace.
    // SSO often keeps control_logged_in set while you manage users on System Settings (?agency_id=…); that must still use
    // this program's permission list (workers, partner agencies, accounting, …), not the short master-control list.
    $agencyProgramContext = !empty($_SESSION['logged_in'])
        || (int) ($_SESSION['agency_id'] ?? 0) > 0
        || (int) ($_GET['agency_id'] ?? 0) > 0;
    $isControl = !empty($_SESSION['control_logged_in']) && !$agencyProgramContext;
    
    if ($isControl) {
        // Control Panel - granular permission groups
        $permissionGroups = [
        [
            'id' => 'control_core',
            'name' => 'Control Panel - Core',
            'count' => 8,
            'permissions' => [
                ['id' => 'control_dashboard', 'name' => 'View Dashboard'],
                ['id' => 'control_select_country', 'name' => 'Select Country (all countries)'],
                ['id' => 'hide_dashboard_countries_card', 'name' => 'Show Dashboard "Active Countries" card'],
                ['id' => 'hide_dashboard_agencies_card', 'name' => 'Show Dashboard "Total Agencies" card'],
                ['id' => 'hide_dashboard_pending_requests_card', 'name' => 'Show Dashboard "Pending Requests" card'],
                ['id' => 'hide_dashboard_accounting_card', 'name' => 'Show Dashboard "Accounting" card'],
                ['id' => 'hide_dashboard_quick_actions', 'name' => 'Show Dashboard "Quick Actions" section'],
                ['id' => 'hide_dashboard_recent_requests', 'name' => 'Show Dashboard "Recent Requests" section']
            ]
        ],
        [
            'id' => 'control_countries',
            'name' => 'Countries Management',
            'count' => 5,
            'permissions' => [
                ['id' => 'control_countries', 'name' => 'Full Access (all below)'],
                ['id' => 'view_control_countries', 'name' => 'View Countries'],
                ['id' => 'add_control_country', 'name' => 'Add Country'],
                ['id' => 'edit_control_country', 'name' => 'Edit Country'],
                ['id' => 'delete_control_country', 'name' => 'Delete Country']
            ]
        ],
        [
            'id' => 'control_agencies',
            'name' => 'Agencies Management',
            'count' => 6,
            'permissions' => [
                ['id' => 'control_agencies', 'name' => 'Full Access (all below)'],
                ['id' => 'view_control_agencies', 'name' => 'View Agencies'],
                ['id' => 'open_control_agency', 'name' => 'Open Agency'],
                ['id' => 'add_control_agency', 'name' => 'Add Agency'],
                ['id' => 'edit_control_agency', 'name' => 'Edit Agency'],
                ['id' => 'delete_control_agency', 'name' => 'Delete Agency']
            ]
        ],
        [
            'id' => 'control_registration',
            'name' => 'Registration Requests',
            'count' => 7,
            'permissions' => [
                ['id' => 'control_registration_requests', 'name' => 'Full Access (all below)'],
                ['id' => 'view_control_registration', 'name' => 'View Requests (country-scoped)'],
                ['id' => 'view_all_control_registration', 'name' => 'View requests from all countries'],
                ['id' => 'edit_control_registration', 'name' => 'Edit Request'],
                ['id' => 'approve_control_registration', 'name' => 'Approve Request'],
                ['id' => 'reject_control_registration', 'name' => 'Reject Request'],
                ['id' => 'delete_control_registration', 'name' => 'Delete Request']
            ]
        ],
        [
            'id' => 'control_support',
            'name' => 'Support Chats',
            'count' => 3,
            'permissions' => [
                ['id' => 'control_support_chats', 'name' => 'Full Access (all below)'],
                ['id' => 'view_control_support', 'name' => 'View Chats'],
                ['id' => 'reply_control_support', 'name' => 'Reply to Chats']
            ]
        ],
        [
            'id' => 'control_designed_site',
            'name' => 'Designed Site (navbar link)',
            'count' => 2,
            'permissions' => [
                ['id' => 'control_designed_site', 'name' => 'Full access (show link)'],
                ['id' => 'view_control_designed_site', 'name' => 'View / open Designed site']
            ]
        ],
        [
            'id' => 'control_accounting',
            'name' => 'Accounting',
            'count' => 3,
            'permissions' => [
                ['id' => 'control_accounting', 'name' => 'Full Access (all below)'],
                ['id' => 'view_control_accounting', 'name' => 'View Accounting'],
                ['id' => 'manage_control_accounting', 'name' => 'Manage Accounting']
            ]
        ],
        [
            'id' => 'control_hr',
            'name' => 'HR Management',
            'count' => 3,
            'permissions' => [
                ['id' => 'control_hr', 'name' => 'Full Access (all below)'],
                ['id' => 'view_control_hr', 'name' => 'View HR'],
                ['id' => 'manage_control_hr', 'name' => 'Manage HR']
            ]
        ],
        [
            'id' => 'control_government',
            'name' => 'Government Control',
            'count' => 4,
            'permissions' => [
                ['id' => 'gov_admin', 'name' => 'Government admin (full module)'],
                ['id' => 'control_government', 'name' => 'Full Access (all below)'],
                ['id' => 'view_control_government', 'name' => 'View Government Control'],
                ['id' => 'manage_control_government', 'name' => 'Manage inspections / violations / lists']
            ]
        ],
        [
            'id' => 'control_admins',
            'name' => 'Control Admins',
            'count' => 5,
            'permissions' => [
                ['id' => 'control_admins', 'name' => 'Full Access (all below)'],
                ['id' => 'view_control_admins', 'name' => 'View Admins'],
                ['id' => 'add_control_admin', 'name' => 'Add Admin'],
                ['id' => 'edit_control_admin', 'name' => 'Edit Admin'],
                ['id' => 'delete_control_admin', 'name' => 'Delete Admin']
            ]
        ],
        [
            'id' => 'control_system',
            'name' => 'System Settings',
            'count' => 5,
            'permissions' => [
                ['id' => 'control_system_settings', 'name' => 'Full Access (all below)'],
                ['id' => 'view_control_system_settings', 'name' => 'View Settings'],
                ['id' => 'edit_control_system_settings', 'name' => 'Edit Settings'],
                ['id' => 'manage_control_users', 'name' => 'Manage Users'],
                ['id' => 'manage_control_roles', 'name' => 'Manage Roles']
            ]
        ]
    ];
    } else {
        // Ratib Pro (agency/country app) - full permission set (includes Partner Agencies group; counts drift — do not rely on comment for exact totals)
        $permissionGroups = [
            ['id' => 'system_management', 'name' => 'System Management', 'count' => 11, 'permissions' => [
                ['id' => 'view_dashboard', 'name' => 'View Dashboard'],
                ['id' => 'manage_branches', 'name' => 'Manage Branches'],
                ['id' => 'view_operations_log', 'name' => 'View Operations Log'],
                ['id' => 'manage_system_settings', 'name' => 'Manage System Settings'],
                ['id' => 'manage_settings', 'name' => 'Manage Settings (Users, Office Manager, Visa, etc.)'],
                ['id' => 'manage_recruitment_countries', 'name' => 'Manage Recruitment Countries'],
                ['id' => 'manage_job_categories', 'name' => 'Manage Job Categories'],
                ['id' => 'manage_recruitment_settings', 'name' => 'Manage Recruitment Settings (Age, Appearance, Status, Arrival, etc.)'],
                ['id' => 'manage_backup', 'name' => 'Manage Backup'],
                ['id' => 'initialize_database', 'name' => 'Initialize Database'],
                ['id' => 'site_settings', 'name' => 'Site Settings']
            ]],
            ['id' => 'user_management', 'name' => 'User Management', 'count' => 5, 'permissions' => [
                ['id' => 'view_users', 'name' => 'View Users'],
                ['id' => 'add_user', 'name' => 'Add User'],
                ['id' => 'edit_user', 'name' => 'Edit User'],
                ['id' => 'change_user_status', 'name' => 'Change User Status'],
                ['id' => 'delete_user', 'name' => 'Delete User']
            ]],
            ['id' => 'role_management', 'name' => 'Role Management', 'count' => 5, 'permissions' => [
                ['id' => 'view_roles', 'name' => 'View Roles'],
                ['id' => 'add_role', 'name' => 'Add Role'],
                ['id' => 'edit_role', 'name' => 'Edit Role'],
                ['id' => 'manage_role_permissions', 'name' => 'Manage Role Permissions'],
                ['id' => 'delete_role', 'name' => 'Delete Role']
            ]],
            ['id' => 'access_management', 'name' => 'Access Management', 'count' => 4, 'permissions' => [
                ['id' => 'view_permissions', 'name' => 'View Permissions'],
                ['id' => 'assign_permissions', 'name' => 'Assign Permissions'],
                ['id' => 'revoke_permissions', 'name' => 'Revoke Permissions'],
                ['id' => 'audit_access_logs', 'name' => 'Audit Access Logs']
            ]],
            ['id' => 'agent_management', 'name' => 'Agent Management', 'count' => 5, 'permissions' => [
                ['id' => 'view_agents', 'name' => 'View Agents'],
                ['id' => 'add_agent', 'name' => 'Add Agent'],
                ['id' => 'edit_agent', 'name' => 'Edit Agent'],
                ['id' => 'delete_agent', 'name' => 'Delete Agent'],
                ['id' => 'export_agents', 'name' => 'Export Agents']
            ]],
            ['id' => 'subagent_management', 'name' => 'SubAgent Management', 'count' => 5, 'permissions' => [
                ['id' => 'view_subagents', 'name' => 'View SubAgents'],
                ['id' => 'add_subagent', 'name' => 'Add SubAgent'],
                ['id' => 'edit_subagent', 'name' => 'Edit SubAgent'],
                ['id' => 'delete_subagent', 'name' => 'Delete SubAgent'],
                ['id' => 'export_subagents', 'name' => 'Export SubAgents']
            ]],
            ['id' => 'worker_management', 'name' => 'Worker Management', 'count' => 9, 'permissions' => [
                ['id' => 'view_workers', 'name' => 'View Workers'],
                ['id' => 'view_worker', 'name' => 'View Worker (single)'],
                ['id' => 'add_worker', 'name' => 'Add Worker'],
                ['id' => 'edit_worker', 'name' => 'Edit Worker'],
                ['id' => 'delete_worker', 'name' => 'Delete Worker'],
                ['id' => 'view_worker_documents', 'name' => 'View Worker Documents'],
                ['id' => 'manage_musaned', 'name' => 'Manage Musaned'],
                ['id' => 'bulk_edit_workers', 'name' => 'Bulk Edit Workers'],
                ['id' => 'export_workers', 'name' => 'Export Workers']
            ]],
            ['id' => 'partner_agencies', 'name' => '🌍 Partner Agencies', 'count' => 4, 'permissions' => [
                ['id' => 'view_partner_agencies', 'name' => 'View Partner Agencies'],
                ['id' => 'add_partner_agency', 'name' => 'Add Partner Agency'],
                ['id' => 'edit_partner_agency', 'name' => 'Edit Partner Agency'],
                ['id' => 'delete_partner_agency', 'name' => 'Delete Partner Agency'],
            ]],
            ['id' => 'case_management', 'name' => 'Case Management', 'count' => 5, 'permissions' => [
                ['id' => 'view_cases', 'name' => 'View Cases'],
                ['id' => 'add_case', 'name' => 'Add Case'],
                ['id' => 'edit_case', 'name' => 'Edit Case'],
                ['id' => 'delete_case', 'name' => 'Delete Case'],
                ['id' => 'export_cases', 'name' => 'Export Cases']
            ]],
            ['id' => 'accounting_chart', 'name' => 'Accounting - Chart of Accounts', 'count' => 5, 'permissions' => [
                ['id' => 'view_chart_accounts', 'name' => 'View Chart of Accounts'],
                ['id' => 'add_account', 'name' => 'Add Account'],
                ['id' => 'edit_account', 'name' => 'Edit Account'],
                ['id' => 'delete_account', 'name' => 'Delete Account'],
                ['id' => 'import_chart_accounts', 'name' => 'Import Chart of Accounts']
            ]],
            ['id' => 'accounting_journal', 'name' => 'Accounting - Journal Entries', 'count' => 6, 'permissions' => [
                ['id' => 'view_journal_entries', 'name' => 'View Journal Entries'],
                ['id' => 'add_journal_entry', 'name' => 'Add Journal Entry'],
                ['id' => 'edit_journal_entry', 'name' => 'Edit Journal Entry'],
                ['id' => 'approve_journal_entry', 'name' => 'Approve Journal Entry'],
                ['id' => 'delete_journal_entry', 'name' => 'Delete Journal Entry'],
                ['id' => 'print_journal_entry', 'name' => 'Print Journal Entry']
            ]],
            ['id' => 'accounting_receipt', 'name' => 'Accounting - Receipt Vouchers', 'count' => 6, 'permissions' => [
                ['id' => 'view_receipt_vouchers', 'name' => 'View Receipt Vouchers'],
                ['id' => 'add_receipt_voucher', 'name' => 'Add Receipt Voucher'],
                ['id' => 'edit_receipt_voucher', 'name' => 'Edit Receipt Voucher'],
                ['id' => 'approve_receipt_voucher', 'name' => 'Approve Receipt Voucher'],
                ['id' => 'delete_receipt_voucher', 'name' => 'Delete Receipt Voucher'],
                ['id' => 'print_receipt_voucher', 'name' => 'Print Receipt Voucher']
            ]],
            ['id' => 'accounting_payment', 'name' => 'Accounting - Payment Vouchers', 'count' => 6, 'permissions' => [
                ['id' => 'view_payment_vouchers', 'name' => 'View Payment Vouchers'],
                ['id' => 'add_payment_voucher', 'name' => 'Add Payment Voucher'],
                ['id' => 'edit_payment_voucher', 'name' => 'Edit Payment Voucher'],
                ['id' => 'approve_payment_voucher', 'name' => 'Approve Payment Voucher'],
                ['id' => 'delete_payment_voucher', 'name' => 'Delete Payment Voucher'],
                ['id' => 'print_payment_voucher', 'name' => 'Print Payment Voucher']
            ]],
            ['id' => 'accounting_receivable', 'name' => 'Accounting - Accounts Receivable', 'count' => 5, 'permissions' => [
                ['id' => 'view_receivables', 'name' => 'View Receivables'],
                ['id' => 'add_receivable', 'name' => 'Add Receivable'],
                ['id' => 'edit_receivable', 'name' => 'Edit Receivable'],
                ['id' => 'approve_receivable', 'name' => 'Approve Receivable'],
                ['id' => 'delete_receivable', 'name' => 'Delete Receivable']
            ]],
            ['id' => 'accounting_payable', 'name' => 'Accounting - Accounts Payable', 'count' => 5, 'permissions' => [
                ['id' => 'view_payables', 'name' => 'View Payables'],
                ['id' => 'add_payable', 'name' => 'Add Payable'],
                ['id' => 'edit_payable', 'name' => 'Edit Payable'],
                ['id' => 'approve_payable', 'name' => 'Approve Payable'],
                ['id' => 'delete_payable', 'name' => 'Delete Payable']
            ]],
            ['id' => 'accounting_banking', 'name' => 'Accounting - Banking', 'count' => 8, 'permissions' => [
                ['id' => 'view_bank_accounts', 'name' => 'View Bank Accounts'],
                ['id' => 'add_bank_account', 'name' => 'Add Bank Account'],
                ['id' => 'edit_bank_account', 'name' => 'Edit Bank Account'],
                ['id' => 'bank_reconciliation', 'name' => 'Bank Reconciliation'],
                ['id' => 'view_bank_transactions', 'name' => 'View Bank Transactions'],
                ['id' => 'add_bank_transaction', 'name' => 'Add Bank Transaction'],
                ['id' => 'edit_bank_transaction', 'name' => 'Edit Bank Transaction'],
                ['id' => 'delete_bank_transaction', 'name' => 'Delete Bank Transaction']
            ]],
            ['id' => 'accounting_expenses', 'name' => 'Accounting - Expenses', 'count' => 5, 'permissions' => [
                ['id' => 'view_expenses', 'name' => 'View Expenses'],
                ['id' => 'add_expense', 'name' => 'Add Expense'],
                ['id' => 'edit_expense', 'name' => 'Edit Expense'],
                ['id' => 'approve_expense', 'name' => 'Approve Expense'],
                ['id' => 'delete_expense', 'name' => 'Delete Expense']
            ]],
            ['id' => 'accounting_customers', 'name' => 'Accounting - Customers', 'count' => 4, 'permissions' => [
                ['id' => 'view_customers', 'name' => 'View Customers'],
                ['id' => 'add_customer', 'name' => 'Add Customer'],
                ['id' => 'edit_customer', 'name' => 'Edit Customer'],
                ['id' => 'delete_customer', 'name' => 'Delete Customer']
            ]],
            ['id' => 'accounting_vendors', 'name' => 'Accounting - Vendors', 'count' => 4, 'permissions' => [
                ['id' => 'view_vendors', 'name' => 'View Vendors'],
                ['id' => 'add_vendor', 'name' => 'Add Vendor'],
                ['id' => 'edit_vendor', 'name' => 'Edit Vendor'],
                ['id' => 'delete_vendor', 'name' => 'Delete Vendor']
            ]],
            ['id' => 'accounting_reports', 'name' => 'Accounting - Reports', 'count' => 8, 'permissions' => [
                ['id' => 'view_trial_balance', 'name' => 'View Trial Balance'],
                ['id' => 'view_income_statement', 'name' => 'View Income Statement'],
                ['id' => 'view_balance_sheet', 'name' => 'View Balance Sheet'],
                ['id' => 'view_account_statement', 'name' => 'View Account Statement'],
                ['id' => 'view_receipt_reports', 'name' => 'View Receipt Reports'],
                ['id' => 'view_payment_reports', 'name' => 'View Payment Reports'],
                ['id' => 'export_reports_excel', 'name' => 'Export Reports to Excel'],
                ['id' => 'export_reports_pdf', 'name' => 'Export Reports to PDF']
            ]],
            ['id' => 'accounting_opening_closing', 'name' => 'Accounting - Opening & Closing Entries', 'count' => 12, 'permissions' => [
                ['id' => 'view_opening_entries', 'name' => 'View Opening Entries'],
                ['id' => 'add_opening_entry', 'name' => 'Add Opening Entry'],
                ['id' => 'edit_opening_entry', 'name' => 'Edit Opening Entry'],
                ['id' => 'approve_opening_entry', 'name' => 'Approve Opening Entry'],
                ['id' => 'delete_opening_entry', 'name' => 'Delete Opening Entry'],
                ['id' => 'print_opening_entry', 'name' => 'Print Opening Entry'],
                ['id' => 'view_closing_entries', 'name' => 'View Closing Entries'],
                ['id' => 'add_closing_entry', 'name' => 'Add Closing Entry'],
                ['id' => 'approve_closing_entry', 'name' => 'Approve Closing Entry'],
                ['id' => 'print_closing_entry', 'name' => 'Print Closing Entry'],
                ['id' => 'view_balance_sheet_after_closing', 'name' => 'View Balance Sheet After Closing'],
                ['id' => 'view_profit_loss_after_closing', 'name' => 'View Profit & Loss After Closing']
            ]],
            ['id' => 'accounting_budget', 'name' => 'Accounting - Budget Management', 'count' => 5, 'permissions' => [
                ['id' => 'view_budgets', 'name' => 'View Budgets'],
                ['id' => 'add_budget', 'name' => 'Add Budget'],
                ['id' => 'edit_budget', 'name' => 'Edit Budget'],
                ['id' => 'delete_budget', 'name' => 'Delete Budget'],
                ['id' => 'view_budget_reports', 'name' => 'View Budget Reports']
            ]],
            ['id' => 'accounting_settings', 'name' => 'Accounting - Settings', 'count' => 4, 'permissions' => [
                ['id' => 'view_accounting_settings', 'name' => 'View Accounting Settings'],
                ['id' => 'edit_accounting_settings', 'name' => 'Edit Accounting Settings'],
                ['id' => 'manage_financial_years', 'name' => 'Manage Financial Years'],
                ['id' => 'manage_transaction_types', 'name' => 'Manage Transaction Types']
            ]],
            ['id' => 'system_history', 'name' => 'System History', 'count' => 5, 'permissions' => [
                ['id' => 'view_system_history', 'name' => 'View System History'],
                ['id' => 'view_module_history', 'name' => 'View Module History'],
                ['id' => 'export_history', 'name' => 'Export History'],
                ['id' => 'filter_history', 'name' => 'Filter History'],
                ['id' => 'search_history', 'name' => 'Search History']
            ]],
            ['id' => 'hr_management', 'name' => 'HR Management', 'count' => 10, 'permissions' => [
                ['id' => 'view_hr_dashboard', 'name' => 'View HR Dashboard'],
                ['id' => 'view_employees', 'name' => 'View Employees'],
                ['id' => 'add_employee', 'name' => 'Add Employee'],
                ['id' => 'edit_employee', 'name' => 'Edit Employee'],
                ['id' => 'delete_employee', 'name' => 'Delete Employee'],
                ['id' => 'manage_attendance', 'name' => 'Manage Attendance'],
                ['id' => 'manage_leaves', 'name' => 'Manage Leaves'],
                ['id' => 'manage_salaries', 'name' => 'Manage Salaries'],
                ['id' => 'manage_departments', 'name' => 'Manage Departments'],
                ['id' => 'manage_positions', 'name' => 'Manage Positions']
            ]],
            ['id' => 'reports', 'name' => 'Reports', 'count' => 6, 'permissions' => [
                ['id' => 'view_reports', 'name' => 'View Reports'],
                ['id' => 'view_recruitment_report', 'name' => 'View Recruitment Report'],
                ['id' => 'view_client_report', 'name' => 'View Client Report'],
                ['id' => 'view_worker_report', 'name' => 'View Worker Report'],
                ['id' => 'export_reports', 'name' => 'Export Reports'],
                ['id' => 'print_reports', 'name' => 'Print Reports']
            ]],
            ['id' => 'communications', 'name' => 'Communications', 'count' => 6, 'permissions' => [
                ['id' => 'communication_view', 'name' => 'View Communications (page access)'],
                ['id' => 'view_messages', 'name' => 'View Messages'],
                ['id' => 'send_message', 'name' => 'Send Message'],
                ['id' => 'view_received_messages', 'name' => 'View Received Messages'],
                ['id' => 'reply_to_message', 'name' => 'Reply to Message'],
                ['id' => 'delete_message', 'name' => 'Delete Message']
            ]],
            ['id' => 'notifications', 'name' => 'Notifications', 'count' => 4, 'permissions' => [
                ['id' => 'view_notifications', 'name' => 'View Notifications'],
                ['id' => 'create_notification', 'name' => 'Create Notification'],
                ['id' => 'manage_notifications', 'name' => 'Manage Notifications'],
                ['id' => 'delete_notification', 'name' => 'Delete Notification']
            ]],
            ['id' => 'visa_management', 'name' => 'Visa Management', 'count' => 5, 'permissions' => [
                ['id' => 'view_visas', 'name' => 'View Visas'],
                ['id' => 'add_visa', 'name' => 'Add Visa'],
                ['id' => 'edit_visa', 'name' => 'Edit Visa'],
                ['id' => 'delete_visa', 'name' => 'Delete Visa'],
                ['id' => 'export_visas', 'name' => 'Export Visas']
            ]],
            ['id' => 'contact_management', 'name' => 'Contact Management', 'count' => 8, 'permissions' => [
                ['id' => 'view_contacts', 'name' => 'View Contacts'],
                ['id' => 'add_contact', 'name' => 'Add Contact'],
                ['id' => 'edit_contact', 'name' => 'Edit Contact'],
                ['id' => 'delete_contact', 'name' => 'Delete Contact'],
                ['id' => 'view_contact_details', 'name' => 'View Contact Details'],
                ['id' => 'manage_contact_communications', 'name' => 'Manage Contact Communications'],
                ['id' => 'export_contacts', 'name' => 'Export Contacts'],
                ['id' => 'import_contacts', 'name' => 'Import Contacts']
            ]]
        ];
    }

    // Dynamic: Country Access - one permission per country (country_{slug}) [Control Panel only]
    if ($isControl) {
        try {
            $chk = $conn->query("SHOW TABLES LIKE 'control_countries'");
            if ($chk && $chk->rowCount() > 0) {
                $stmt = $conn->query("SELECT id, name, slug FROM control_countries WHERE is_active = 1 ORDER BY sort_order ASC, name ASC");
                if ($stmt) {
                    $countryPerms = [];
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $countryPerms[] = ['id' => 'country_' . $row['slug'], 'name' => $row['name']];
                    }
                    if (!empty($countryPerms)) {
                        array_splice($permissionGroups, 1, 0, [[
                            'id' => 'control_country_access',
                            'name' => 'Country Access',
                            'count' => count($countryPerms),
                            'permissions' => $countryPerms
                        ]]);
                    }
                }
            }
        } catch (Throwable $e) { /* ignore */ }
    }

    // Get role permissions if role_id is provided (use permReadConn = country DB when available)
    $rolePermissions = [];
    if ($roleId) {
        if ($permReadConn instanceof mysqli) {
            $stmt = $permReadConn->prepare("SELECT permissions FROM roles WHERE role_id = ?");
            $stmt->bind_param('i', $roleId);
            $stmt->execute();
            $res = $stmt->get_result();
            $role = $res ? $res->fetch_assoc() : null;
            if ($role && !empty($role['permissions'])) {
                $rolePermissions = json_decode($role['permissions'], true) ?: [];
            }
        } else {
            $stmt = $conn->prepare("SELECT permissions FROM roles WHERE role_id = ?");
            $stmt->execute([$roleId]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($role && $role['permissions']) {
                $rolePermissions = json_decode($role['permissions'], true) ?: [];
            }
        }
    }
    
    // Get user permissions if user_id is provided (use permReadConn = country DB when available)
    $userPermissions = [];
    /** @var list<string> Role JSON for the edited user — used only to paint green/red when users.permissions is empty (use role only). */
    $editedUserRolePermissionIds = [];
    if ($userId) {
        try {
            if ($permReadConn instanceof mysqli) {
                $chk = @$permReadConn->query("SHOW COLUMNS FROM users LIKE 'permissions'");
                $columnExists = $chk && $chk->num_rows > 0;
                if ($columnExists) {
                    // Use same PK as save script (user_id or id)
                    $userPk = 'user_id';
                    $pkChk = @$permReadConn->query("SHOW COLUMNS FROM users LIKE 'user_id'");
                    if (!$pkChk || $pkChk->num_rows === 0) {
                        $pkChk = @$permReadConn->query("SHOW COLUMNS FROM users LIKE 'id'");
                        if ($pkChk && $pkChk->num_rows > 0) {
                            $userPk = 'id';
                        }
                    }
                    $stmt = $permReadConn->prepare("SELECT role_id, permissions FROM users WHERE {$userPk} = ?");
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $user = $res ? $res->fetch_assoc() : null;
                    $stmt->close();
                    if ($user) {
                        $userPermissions = $parse_json_permissions($user['permissions'] ?? null);
                    }
                    $rid = $user ? (int) ($user['role_id'] ?? 0) : 0;
                    if ($rid > 0) {
                        $stR = @$permReadConn->prepare('SELECT permissions FROM roles WHERE role_id = ? LIMIT 1');
                        if ($stR) {
                            $stR->bind_param('i', $rid);
                            $stR->execute();
                            $rRes = $stR->get_result();
                            $roleRow = $rRes ? $rRes->fetch_assoc() : null;
                            $stR->close();
                            if ($roleRow && !empty($roleRow['permissions'])) {
                                $dec = json_decode($roleRow['permissions'], true);
                                $editedUserRolePermissionIds = is_array($dec) ? array_map('strval', $dec) : [];
                            }
                        }
                    }
                }
            } else {
                $checkStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'permissions'");
                $columnExists = $checkStmt->rowCount() > 0;
                if ($columnExists) {
                    $pkEdit = ratib_users_primary_key_column($conn);
                    $stmt = $conn->prepare("SELECT role_id, permissions FROM users WHERE `{$pkEdit}` = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        $userPermissions = $parse_json_permissions($user['permissions'] ?? null);
                    }
                    $rid = $user ? (int) ($user['role_id'] ?? 0) : 0;
                    if ($rid > 0) {
                        $stR = $conn->prepare('SELECT permissions FROM roles WHERE role_id = ? LIMIT 1');
                        $stR->execute([$rid]);
                        $roleRow = $stR->fetch(PDO::FETCH_ASSOC);
                        if ($roleRow && !empty($roleRow['permissions'])) {
                            $dec = json_decode($roleRow['permissions'], true);
                            $editedUserRolePermissionIds = is_array($dec) ? array_map('strval', $dec) : [];
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Permissions column check failed: " . $e->getMessage());
        } catch (Throwable $e) {
            error_log("Permissions column check failed: " . $e->getMessage());
        }
    }
    
    $readFromFallbackTenant = ($countryConn instanceof mysqli) && !($appConn instanceof mysqli);
    $debugDbName = null;
    if ($permReadConn instanceof mysqli) {
        $dbRes = @$permReadConn->query('SELECT DATABASE()');
        $debugDbName = $dbRes && ($row = $dbRes->fetch_row()) ? $row[0] : null;
    }
    // Close only the extra tenant connection we opened when $GLOBALS['conn'] was missing (never close $appConn).
    if ($countryConn instanceof mysqli) {
        $countryConn->close();
        $countryConn = null;
    }
    
    // Filter permissions: Only show permissions that current user has (unless admin)
    // CRITICAL: Admins (role_id = 1) ALWAYS see ALL permissions for assignment, including new ones
    $shouldShowAllPermissions = false;
    if ($currentUserIsAdmin || (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1)) {
        // Admin always sees all permissions
        $shouldShowAllPermissions = true;
        $currentUserIsAdmin = true;
    } elseif (is_array($currentUserPermissions) && in_array('*', $currentUserPermissions, true)) {
        $shouldShowAllPermissions = true;
    }
    
    if ($shouldShowAllPermissions) {
        // True admin - show all permissions (including newly added ones)
        foreach ($permissionGroups as &$group) {
            // Recalculate count from actual permissions array
            $group['count'] = count($group['permissions']);
        }
        unset($group); // Clean up reference variable
        error_log("Permission filtering: User {$_SESSION['user_id']} is ADMIN - showing all permissions (including new ones).");
    } else {
        // Filter each group to only include permissions the current user has
        $originalGroupCount = count($permissionGroups);
        $originalPermissionCount = 0;
        foreach ($permissionGroups as $g) {
            $originalPermissionCount += count($g['permissions']);
        }
        
        foreach ($permissionGroups as &$group) {
            // Filter permissions array to only include what current user has
            $group['permissions'] = array_filter($group['permissions'], function($perm) use ($currentUserPermissions) {
                return in_array($perm['id'], $currentUserPermissions, true);
            });
            // Re-index array after filtering
            $group['permissions'] = array_values($group['permissions']);
            // Update count
            $group['count'] = count($group['permissions']);
        }
        unset($group); // Clean up reference variable
        
        // Remove groups that have no permissions left
        $permissionGroups = array_filter($permissionGroups, function($group) {
            return count($group['permissions']) > 0;
        });
        // Re-index array
        $permissionGroups = array_values($permissionGroups);
        
        $filteredPermissionCount = 0;
        foreach ($permissionGroups as $g) {
            $filteredPermissionCount += count($g['permissions']);
        }
        
        error_log("Permission filtering: User {$_SESSION['user_id']} has " . count($currentUserPermissions) . " permissions. Filtered from {$originalPermissionCount} to {$filteredPermissionCount} permissions, from {$originalGroupCount} to " . count($permissionGroups) . " groups.");
    }
    
    // Mark which permissions are granted (green in UI) vs not (red).
    // For user edit: if users.permissions is empty, effective access follows the user's role — show that in granted flags.
    // Response user_permissions stays the stored JSON only (save/unsaved logic unchanged).
    $storedUserPerms = is_array($userPermissions) ? array_map('strval', $userPermissions) : [];
    if ($userId) {
        $permissionsToCheckForGranted = $storedUserPerms;
        if (!in_array('*', $permissionsToCheckForGranted, true) && count($permissionsToCheckForGranted) === 0) {
            $permissionsToCheckForGranted = $editedUserRolePermissionIds;
        }
    } else {
        $permissionsToCheckForGranted = is_array($rolePermissions) ? array_map('strval', $rolePermissions) : [];
    }
    $hasFullAccess = in_array('*', $permissionsToCheckForGranted, true);

    foreach ($permissionGroups as &$group) {
        foreach ($group['permissions'] as &$permission) {
            $permId = isset($permission['id']) ? (string) $permission['id'] : '';
            $permission['granted'] = $hasFullAccess || ($permId !== '' && in_array($permId, $permissionsToCheckForGranted, true));
        }
        unset($permission);
    }
    unset($group);
    
    $response = [
        'success' => true,
        'groups' => $permissionGroups
    ];
    
    if ($roleId) {
        $response['role_permissions'] = $rolePermissions;
    }
    
    if ($userId) {
        $response['user_permissions'] = $userPermissions;
        // Optional debug: add ?debug=1 to see which connection/DB was used for user permissions (helps if load shows wrong state)
        if (!empty($_GET['debug'])) {
            $response['debug'] = [
                'connection' => $permReadConn instanceof mysqli ? ($readFromFallbackTenant ? 'fallback_tenant_mysqli' : 'app_mysqli') : 'pdo',
                'user_permissions_count' => count($userPermissions),
                'db_name' => $debugDbName,
            ];
        }
    }
    if ($isControl) {
        $response['source'] = 'control';
        $response['db_name'] = defined('DB_NAME') ? DB_NAME : null;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    // Clear any output that might have been sent
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    error_log("Error in get_permissions_groups.php: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    // Handle fatal errors
    if (ob_get_level() > 0) {
        ob_clean();
    }
    http_response_code(500);
    error_log("Fatal error in get_permissions_groups.php: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Fatal Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

// End output buffering and send output
if (ob_get_level() > 0) {
    ob_end_flush();
}
