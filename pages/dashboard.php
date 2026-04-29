<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/dashboard.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/dashboard.php`.
 */
require_once '../includes/config.php';
require_once '../includes/permissions.php';
require_once '../api/core/ensure-global-partnerships-schema.php';
require_once '../api/partnerships/PartnerAgencyController.php';
require_once '../api/partnerships/DeploymentController.php';
// Check if user is logged in (must be a real `users` row: positive user_id)
if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] < 1
    || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $ssoParts = [];
    $countrySlug = isset($_GET['country_slug']) ? trim((string)$_GET['country_slug']) : '';
    if ($countrySlug !== '') {
        $ssoParts[] = 'country_slug=' . rawurlencode($countrySlug);
    }
    if (isset($_GET['control']) && (string) $_GET['control'] === '1'
        && isset($_GET['agency_id']) && ctype_digit((string) $_GET['agency_id'])) {
        $ssoParts[] = 'control=1';
        $ssoParts[] = 'agency_id=' . (int) $_GET['agency_id'];
    }
    $ssoQs = !empty($ssoParts) ? ('?' . implode('&', $ssoParts)) : '';
    header('Location: ' . pageUrl('login.php') . $ssoQs);
    exit;
}

// Check if user has permission to view dashboard
if (!hasPermission('view_dashboard')) {
    // Logged-in users without dashboard permission should not be treated as logged out.
    header('Location: ' . pageUrl('profile.php'));
    exit;
}

// Ensure database connection is available
if (!isset($conn) || $conn === null) {
    // Try to get connection from globals
    if (isset($GLOBALS['conn']) && $GLOBALS['conn'] !== null) {
        $conn = $GLOBALS['conn'];
    } else {
        // Create new connection
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            $conn->set_charset("utf8mb4");
            $GLOBALS['conn'] = $conn;
        } catch (Exception $e) {
            error_log("Dashboard - Failed to create database connection: " . $e->getMessage());
            die("Database connection failed. Please contact administrator.");
        }
    }
}
$pageTitle = "Dashboard";
$pageCss = [
    asset('css/dashboard.css'),
    asset('css/system-settings.css') . "?v=" . time()
];
$modernFormsVersion = file_exists(__DIR__ . '/../js/modern-forms.js')
    ? filemtime(__DIR__ . '/../js/modern-forms.js')
    : time();
$countriesCitiesVersion = file_exists(__DIR__ . '/../js/countries-cities.js')
    ? filemtime(__DIR__ . '/../js/countries-cities.js')
    : time();

$systemSettingsVersion = file_exists(__DIR__ . '/../js/system-settings.js')
    ? filemtime(__DIR__ . '/../js/system-settings.js')
    : time();
$unifiedHistoryVersion = file_exists(__DIR__ . '/../js/unified-history.js')
    ? filemtime(__DIR__ . '/../js/unified-history.js')
    : time();
$pageJs = [
    "https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js",
    asset('js/countries-cities.js') . "?v=" . $countriesCitiesVersion,
    asset('js/modern-forms.js') . "?v=" . $modernFormsVersion,
    asset('js/system-settings.js') . "?v=" . $systemSettingsVersion,
    asset('js/unified-history.js') . "?v=" . $unifiedHistoryVersion,
    asset('js/dashboard.js') . "?v=" . time()
];

// Country / agency context (for control and normal logins)
$currentCountryName = $_SESSION['country_name'] ?? null;
if (!$currentCountryName) {
    $currentCountryName = defined('TENANT_NAME') ? TENANT_NAME : null;
}
$currentAgencyName = isset($_SESSION['agency_name']) ? trim((string)$_SESSION['agency_name']) : null;
if ($currentAgencyName === '') $currentAgencyName = null;
// Re-fetch agency from DB: prefer session agency_id (specific agency), else first agency for country
$sessionCountryId = isset($_SESSION['country_id']) ? (int)$_SESSION['country_id'] : 0;
$sessionAgencyId = isset($_SESSION['agency_id']) ? (int)$_SESSION['agency_id'] : 0;
if (($sessionCountryId > 0 || $sessionAgencyId > 0) && isset($conn) && $conn instanceof mysqli) {
    $ctrlConn = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
    foreach ([$ctrlConn, $conn] as $tryConn) {
        if (!$tryConn || !($tryConn instanceof mysqli)) continue;
        try {
            $chk = @$tryConn->query("SHOW TABLES LIKE 'control_agencies'");
            if (!$chk || $chk->num_rows === 0) continue;
            // Prefer specific agency_id from session so we show the exact agency user logged into
            if ($sessionAgencyId > 0) {
                $susp = ratib_control_agency_active_fragment($tryConn, null);
                $stmt = $tryConn->prepare("SELECT name, country_id FROM control_agencies WHERE id = ? AND is_active = 1 AND {$susp} LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("i", $sessionAgencyId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && $res->num_rows > 0 && ($row = $res->fetch_assoc())) {
                        $currentAgencyName = trim($row['name'] ?? '') ?: null;
                        $stmt->close();
                        break;
                    }
                    $stmt->close();
                }
            }
            if ($currentAgencyName !== null && $currentAgencyName !== '') break;
            // Fallback: first agency for current country
            if ($sessionCountryId > 0) {
                $susp = ratib_control_agency_active_fragment($tryConn, null);
                $stmt = $tryConn->prepare("SELECT name FROM control_agencies WHERE country_id = ? AND is_active = 1 AND {$susp} ORDER BY id ASC LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("i", $sessionCountryId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    if ($res && $res->num_rows > 0 && ($row = $res->fetch_assoc())) {
                        $currentAgencyName = trim($row['name'] ?? '') ?: null;
                        $stmt->close();
                        break;
                    }
                    $stmt->close();
                }
            }
        } catch (Throwable $e) { /* try next conn */ }
    }
}

// Only load and display system stats, agent stats, subagent stats, worker stats, case stats, and recent activities.
// Initialize default values
$agentStats = ['total' => 0, 'active' => 0, 'inactive' => 0];
$subAgentStats = ['total' => 0, 'active' => 0, 'inactive' => 0];
$workerStats = ['total' => 0, 'active' => 0, 'inactive' => 0];
$caseStats = ['total' => 0, 'open' => 0, 'in_progress' => 0, 'pending' => 0, 'resolved' => 0, 'closed' => 0, 'urgent' => 0];
$hrStats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'terminated' => 0];
$reportsStats = ['total' => 0, 'today' => 0, 'this_month' => 0];
$contactStats = ['total' => 0, 'active' => 0, 'inactive' => 0];
$visaStats = ['total' => 0, 'active' => 0, 'inactive' => 0];
$notificationStats = ['new' => 0, 'total' => 0];
$accountingStats = ['invoices' => 0, 'bills' => 0, 'transactions' => 0, 'customers' => 0, 'vendors' => 0];
$settingsStats = ['total' => 0, 'active' => 0];
$activities = null;
$notificationCount = 0;
$partnershipStats = ['total_agencies' => 0, 'active_agencies' => 0, 'countries_count' => 0];
$deploymentStats = ['total_abroad' => 0, 'active_count' => 0, 'returned_count' => 0, 'issue_count' => 0];
try {
    // Get Agents stats - handle NULL values with COALESCE
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'agents'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $result = $conn->query("
            SELECT 
                COUNT(*) as total,
                    COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as active,
                    COALESCE(SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END), 0) as inactive
            FROM agents
            ");
            if ($result) {
                $agentStats = $result->fetch_assoc();
                // Ensure all values are integers
                $agentStats['total'] = (int)($agentStats['total'] ?? 0);
                $agentStats['active'] = (int)($agentStats['active'] ?? 0);
                $agentStats['inactive'] = (int)($agentStats['inactive'] ?? 0);
            }
        }
    } catch (Exception $e) {
        error_log("Dashboard - Agents stats error: " . $e->getMessage());
    }

    // Get SubAgents stats - handle NULL values with COALESCE
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'subagents'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $result = $conn->query("
            SELECT 
                COUNT(*) as total,
                    COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as active,
                    COALESCE(SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END), 0) as inactive
            FROM subagents
            ");
            if ($result) {
                $subAgentStats = $result->fetch_assoc();
                // Ensure all values are integers
                $subAgentStats['total'] = (int)($subAgentStats['total'] ?? 0);
                $subAgentStats['active'] = (int)($subAgentStats['active'] ?? 0);
                $subAgentStats['inactive'] = (int)($subAgentStats['inactive'] ?? 0);
            }
        }
    } catch (Exception $e) {
        error_log("Dashboard - SubAgents stats error: " . $e->getMessage());
    }

    // Get Workers stats - EXCLUDE deleted workers and handle NULL values
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'workers'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $result = $conn->query("
            SELECT 
                COUNT(*) as total,
                    COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) as active,
                    COALESCE(SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END), 0) as inactive
            FROM workers
                WHERE status != 'deleted'
            ");
            if ($result) {
                $workerStats = $result->fetch_assoc();
                // Ensure all values are integers
                $workerStats['total'] = (int)($workerStats['total'] ?? 0);
                $workerStats['active'] = (int)($workerStats['active'] ?? 0);
                $workerStats['inactive'] = (int)($workerStats['inactive'] ?? 0);
            }
        }
    } catch (Exception $e) {
        error_log("Dashboard - Workers stats error: " . $e->getMessage());
    }

    // Get Cases stats - handle NULL values with COALESCE
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'cases'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $result = $conn->query("
            SELECT 
                COUNT(*) as total,
                    COALESCE(SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END), 0) as open,
                    COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) as in_progress,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) as pending,
                    COALESCE(SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END), 0) as resolved,
                    COALESCE(SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END), 0) as closed,
                    COALESCE(SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END), 0) as urgent
            FROM cases
            ");
            if ($result) {
                $caseStats = $result->fetch_assoc();
                // Ensure all values are integers
                $caseStats['total'] = (int)($caseStats['total'] ?? 0);
                $caseStats['open'] = (int)($caseStats['open'] ?? 0);
                $caseStats['in_progress'] = (int)($caseStats['in_progress'] ?? 0);
                $caseStats['pending'] = (int)($caseStats['pending'] ?? 0);
                $caseStats['resolved'] = (int)($caseStats['resolved'] ?? 0);
                $caseStats['closed'] = (int)($caseStats['closed'] ?? 0);
                $caseStats['urgent'] = (int)($caseStats['urgent'] ?? 0);
            }
        }
    } catch (Exception $e) {
        error_log("Dashboard - Cases stats error: " . $e->getMessage());
    }

    // Partnership + deployments stats
    try {
        $pdo = null;
        if (class_exists('Database') && method_exists('Database', 'getInstance')) {
            $pdo = Database::getInstance()->getConnection();
        }
        if ($pdo instanceof PDO) {
            ratibEnsureGlobalPartnershipsSchema($pdo);
            $partnershipController = new PartnerAgencyController($pdo);
            $deploymentController = new DeploymentController($pdo);
            $partnershipStats = $partnershipController->stats();
            $deploymentStats = $deploymentController->stats();
        }
    } catch (Throwable $e) {
        error_log("Dashboard - Partnerships stats error: " . $e->getMessage());
    }

    // Get recent activities - check table exists first, then query
    $activities = null;
    try {
        // Check if activity_logs table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'activity_logs'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $activities = $conn->query("
                SELECT al.*, COALESCE(u.username, 'System') as username 
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.user_id
                ORDER BY al.created_at DESC LIMIT 5
            ");
            // If query failed, set to null
            if (!$activities) {
                $activities = null;
            }
        }
    } catch (Exception $e) {
        error_log("Dashboard - Activities error: " . $e->getMessage());
        $activities = null;
    }
    
    // Get HR stats - handle NULL values and check table existence
    try {
        $total = 0;
        $active = 0;
        $inactive = 0;
        $terminated = 0;
        
        // Check if employees table exists first
        $tableCheck = $conn->query("SHOW TABLES LIKE 'employees'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            // Try to count from employees table
            try {
                $result = $conn->query("SELECT COUNT(*) as count FROM employees");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $total = (int)($row['count'] ?? 0);
                }
            } catch (Exception $e) {
                error_log("Dashboard - HR employees total error: " . $e->getMessage());
            }
            
            // Count active employees
            try {
                $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'Active'");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $active = (int)($row['count'] ?? 0);
                }
            } catch (Exception $e) {
                error_log("Dashboard - HR active employees error: " . $e->getMessage());
            }
            
            // Count inactive employees
            try {
                $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'Inactive'");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $inactive = (int)($row['count'] ?? 0);
                }
            } catch (Exception $e) {
                error_log("Dashboard - HR inactive employees error: " . $e->getMessage());
            }
            
            // Count terminated employees
            try {
                $result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'Terminated'");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $terminated = (int)($row['count'] ?? 0);
                }
            } catch (Exception $e) {
                error_log("Dashboard - HR terminated employees error: " . $e->getMessage());
            }
        }
        
        $hrStats = [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'terminated' => $terminated
        ];
    } catch (Exception $e) {
        error_log("Dashboard - HR stats error: " . $e->getMessage());
    }

    // Get Accounting stats - handle NULL values and table existence
    try {
        // Check table existence first, then calculate
        $invoices = 0;
        $bills = 0;
        $transactions = 0;
        $customers = 0;
        $vendors = 0;
        
        // Count invoices
        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_invoices'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as count FROM accounting_invoices");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $invoices = (int)($row['count'] ?? 0);
                }
            }
        } catch (Exception $e) {
            error_log("Dashboard - Accounting invoices error: " . $e->getMessage());
        }
        
        // Count bills
        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_bills'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as count FROM accounting_bills");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $bills = (int)($row['count'] ?? 0);
                }
            }
        } catch (Exception $e) {
            error_log("Dashboard - Accounting bills error: " . $e->getMessage());
        }
        
        // Count transactions
        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as count FROM financial_transactions");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $transactions = (int)($row['count'] ?? 0);
                }
            }
        } catch (Exception $e) {
            error_log("Dashboard - Accounting transactions error: " . $e->getMessage());
        }
        
        // Count customers
        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_customers'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as count FROM accounting_customers WHERE is_active = 1");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $customers = (int)($row['count'] ?? 0);
                }
            }
        } catch (Exception $e) {
            error_log("Dashboard - Accounting customers error: " . $e->getMessage());
        }
        
        // Count vendors
        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'accounting_vendors'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as count FROM accounting_vendors WHERE is_active = 1");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $vendors = (int)($row['count'] ?? 0);
                }
            }
        } catch (Exception $e) {
            error_log("Dashboard - Accounting vendors error: " . $e->getMessage());
        }
        
        $accountingStats = [
            'invoices' => $invoices,
            'bills' => $bills,
            'transactions' => $transactions,
            'customers' => $customers,
            'vendors' => $vendors
        ];
    } catch (Exception $e) {
        error_log("Dashboard - Accounting stats error: " . $e->getMessage());
    }

    // Get Reports stats - count ALL activity/log tables (activity_logs, system_events, case_activities, global_history)
    try {
        $activityTotal = 0;
        $systemTotal = 0;
        $caseActivityTotal = 0;
        $globalHistoryTotal = 0;
        $activityToday = 0;
        $systemToday = 0;
        $caseActivityToday = 0;
        $globalHistoryToday = 0;
        $activityMonth = 0;
        $systemMonth = 0;
        $caseActivityMonth = 0;
        $globalHistoryMonth = 0;
        
        // Check if activity_logs table exists and count
        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'activity_logs'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as count FROM activity_logs");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $activityTotal = (int)($row['count'] ?? 0);
                }
                
                $result = $conn->query("SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $activityToday = (int)($row['count'] ?? 0);
                }
                
                $result = $conn->query("SELECT COUNT(*) as count FROM activity_logs WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $activityMonth = (int)($row['count'] ?? 0);
                }
            }
        } catch (Exception $e) {
            error_log("Dashboard - Activity logs error: " . $e->getMessage());
        }
        
        // system_events observability count (optimized: single query for all counts)
        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'system_events'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                // Single query to get all counts at once (more efficient)
                $result = $conn->query("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as month
                    FROM system_events
                    WHERE event_type LIKE 'CONTROL_%'
                       OR event_type LIKE 'QUERY_%'
                       OR event_type = 'ADMIN_AUDIT'
                ");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $systemTotal = (int)($row['total'] ?? 0);
                    $systemToday = (int)($row['today'] ?? 0);
                    $systemMonth = (int)($row['month'] ?? 0);
                }
            }
        } catch (Exception $e) {
            error_log("Dashboard - System events error: " . $e->getMessage());
        }
        
        // Check if case_activities table exists and count
        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'case_activities'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as count FROM case_activities");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $caseActivityTotal = (int)($row['count'] ?? 0);
                }
                
                $result = $conn->query("SELECT COUNT(*) as count FROM case_activities WHERE DATE(created_at) = CURDATE()");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $caseActivityToday = (int)($row['count'] ?? 0);
                }
                
                $result = $conn->query("SELECT COUNT(*) as count FROM case_activities WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $caseActivityMonth = (int)($row['count'] ?? 0);
                }
            }
        } catch (Exception $e) {
            error_log("Dashboard - Case activities error: " . $e->getMessage());
        }
        
        // Check if global_history table exists and count (optimized: single query for all counts)
        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'global_history'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                // Single query to get all counts at once (more efficient)
                $result = $conn->query("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
                        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as month
                    FROM global_history
                ");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $globalHistoryTotal = (int)($row['total'] ?? 0);
                    $globalHistoryToday = (int)($row['today'] ?? 0);
                    $globalHistoryMonth = (int)($row['month'] ?? 0);
                }
            }
        } catch (Exception $e) {
            error_log("Dashboard - Global history error: " . $e->getMessage());
        }
        
        $reportsStats = [
            'total' => $activityTotal + $systemTotal + $caseActivityTotal + $globalHistoryTotal,
            'today' => $activityToday + $systemToday + $caseActivityToday + $globalHistoryToday,
            'this_month' => $activityMonth + $systemMonth + $caseActivityMonth + $globalHistoryMonth
        ];
    } catch (Exception $e) {
        error_log("Dashboard - Reports stats error: " . $e->getMessage());
    }

    // Get Contact stats - handle NULL values with COALESCE
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'contacts'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $result = $conn->query("
                SELECT 
                    COUNT(*) as total,
                    COALESCE(SUM(CASE WHEN status = 'active' AND is_deleted = 0 THEN 1 ELSE 0 END), 0) as active,
                    COALESCE(SUM(CASE WHEN status = 'inactive' AND is_deleted = 0 THEN 1 ELSE 0 END), 0) as inactive
                FROM contacts
                WHERE is_deleted = 0
            ");
            if ($result) {
                $contactStats = $result->fetch_assoc();
                $contactStats['total'] = (int)($contactStats['total'] ?? 0);
                $contactStats['active'] = (int)($contactStats['active'] ?? 0);
                $contactStats['inactive'] = (int)($contactStats['inactive'] ?? 0);
            }
        }
    } catch (Exception $e) {
        error_log("Dashboard - Contact stats error: " . $e->getMessage());
    }
    
    // Get Visa Management stats - handle NULL values with COALESCE
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'visa_types'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $result = $conn->query("
                SELECT 
                    COUNT(*) as total,
                    COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) as active,
                    COALESCE(SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END), 0) as inactive
                FROM visa_types
            ");
            if ($result) {
                $visaStats = $result->fetch_assoc();
                $visaStats['total'] = (int)($visaStats['total'] ?? 0);
                $visaStats['active'] = (int)($visaStats['active'] ?? 0);
                $visaStats['inactive'] = (int)($visaStats['inactive'] ?? 0);
            }
        }
    } catch (Exception $e) {
        error_log("Dashboard - Visa stats error: " . $e->getMessage());
    }

    // Get Settings stats - count all settings tables individually
    try {
        $total = 0;
        $active = 0;
        
        $settingsTables = [
            'visa_types',
            'recruitment_countries',
            'job_categories',
            'age_specifications',
            'appearance_specifications',
            'status_specifications',
            'request_statuses',
            'arrival_agencies',
            'arrival_stations',
            'worker_statuses',
            'hr_settings',
            'office_manager_data'
        ];
        
        foreach ($settingsTables as $table) {
            try {
                $count = 0;
                // Count total
                $result = $conn->query("SELECT COUNT(*) as count FROM `{$table}`");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $count = (int)($row['count'] ?? 0);
                    $total += $count;
                }
                
                // Count active (only for tables with is_active column)
                if (in_array($table, ['visa_types', 'recruitment_countries', 'job_categories', 'age_specifications', 
                                      'appearance_specifications', 'status_specifications', 'request_statuses', 
                                      'arrival_agencies', 'arrival_stations', 'worker_statuses'])) {
                    $result = $conn->query("SELECT COUNT(*) as count FROM `{$table}` WHERE is_active = 1");
                    if ($result) {
                        $row = $result->fetch_assoc();
                        $activeCount = (int)($row['count'] ?? 0);
                        $active += $activeCount;
                    }
                } else {
                    // For tables without is_active, count all as active
                    $active += $count;
                }
            } catch (Exception $e) {
                error_log("Dashboard - Settings table {$table} error: " . $e->getMessage());
            }
        }
        
        $settingsStats = [
            'total' => $total,
            'active' => $active
        ];
    } catch (Exception $e) {
        error_log("Dashboard - Settings stats error: " . $e->getMessage());
    }

    // Get notification stats - use ONLY contact_notifications table (main notifications table)
    try {
        $total = 0;
        $new = 0;
        
        // Check for contact_notifications table (main notifications table)
        try {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'contact_notifications'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                // Count total notifications - ONLY from contact_notifications
                $result = $conn->query("SELECT COUNT(*) as count FROM contact_notifications");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $total = (int)($row['count'] ?? 0);
                }
                
                // Count unread notifications (status != 'read' means unread, including pending, sent, failed)
                $result = $conn->query("SELECT COUNT(*) as count FROM contact_notifications WHERE status != 'read'");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $new = (int)($row['count'] ?? 0);
                }
            }
        } catch (Exception $e) {
            error_log("Dashboard - contact_notifications error: " . $e->getMessage());
        }
        
        // Do NOT add from notifications table - only use contact_notifications
        // This ensures we show the correct count (39) from the notifications page
        
        $notificationStats = [
            'total' => $total,
            'new' => $new
        ];
        $notificationCount = $new;
    } catch (Exception $e) {
        error_log("Dashboard - Notification stats error: " . $e->getMessage());
        $notificationCount = 0;
    }
} catch (Exception $e) {
    error_log("Dashboard - General error: " . $e->getMessage());
}

// Get current user's full name from database
$userFullName = '';
try {
    if (isset($_SESSION['user_id']) && isset($conn)) {
        $userId = intval($_SESSION['user_id']);
        $stmt = $conn->prepare("
            SELECT 
                COALESCE(full_name, name, CONCAT(first_name, ' ', last_name), username) as display_name
            FROM users 
            WHERE user_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $userData = $result->fetch_assoc();
                $userFullName = trim($userData['display_name'] ?? '');
            }
            $stmt->close();
        }
    }
} catch (Exception $e) {
    error_log("Dashboard - Error fetching user full name: " . $e->getMessage());
    // Fallback to username if available
    $userFullName = $_SESSION['username'] ?? '';
}

include '../includes/header.php';
?>

        <div class="dashboard-content">
            <?php if (!empty($currentCountryName) || !empty($currentAgencyName)): ?>
            <div class="dashboard-context-bar">
                <span class="dashboard-context-label">Country:</span>
                <span class="dashboard-context-value"><?php echo htmlspecialchars($currentCountryName ?: '—'); ?></span>
                <span class="dashboard-context-sep" aria-hidden="true"></span>
                <span class="dashboard-context-label">Agency:</span>
                <span class="dashboard-context-value"><?php echo htmlspecialchars(($currentAgencyName !== null && $currentAgencyName !== '') ? $currentAgencyName : '—'); ?></span>
            </div>
            <?php endif; ?>
            <div class="header-bar">
                <div class="logo-section">
                    <div class="ratib-dashboard-banner">RATIB — Recruitment Automation &amp; Tracking Intelligence Base</div>
                    <div class="flashing-text" id="flashingText">Welcome Back</div>
                </div>
                <div class="header-bar-right">
                    <?php if (!empty($userFullName)): ?>
                    <div class="user-name-display">
                        <span class="user-full-name"><?php echo htmlspecialchars($userFullName); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="main-content">
                <div class="system-grid" role="navigation" aria-label="System modules">
            <!-- Agents Card -->
            <div class="system-card" data-href="<?php echo htmlspecialchars(ratib_nav_url('agent.php'), ENT_QUOTES, 'UTF-8'); ?>" data-permission="view_agents" tabindex="0" role="button" aria-label="Agents - Total: <?php echo $agentStats['total'] ?? 0; ?>">
                <h2>👥 Agents</h2>
                <div class="status-info">
                    <p class="count">Total: <span id="totalAgents"><?php echo $agentStats['total'] ?? 0; ?></span></p>
                    <p class="count">Active: <span id="activeAgents"><?php echo $agentStats['active'] ?? 0; ?></span></p>
                    <p class="count">Inactive: <span id="inactiveAgents"><?php echo $agentStats['inactive'] ?? 0; ?></span></p>
                    <p class="status">Status: 
                        <span class="status-indicator <?php echo ($agentStats['active'] ?? 0) > 0 ? 'active' : 'inactive'; ?>">
                            <?php echo ($agentStats['active'] ?? 0) > 0 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- SubAgents Card -->
            <div class="system-card" data-href="<?php echo htmlspecialchars(ratib_nav_url('subagent.php'), ENT_QUOTES, 'UTF-8'); ?>" data-permission="view_subagents" tabindex="0" role="button" aria-label="SubAgents - Total: <?php echo $subAgentStats['total'] ?? 0; ?>">
                <h2>👤 SubAgents</h2>
                <div class="status-info">
                    <p class="count">Total: <span id="totalSubAgents"><?php echo $subAgentStats['total'] ?? 0; ?></span></p>
                    <p class="count">Active: <span id="activeSubAgents"><?php echo $subAgentStats['active'] ?? 0; ?></span></p>
                    <p class="count">Inactive: <span id="inactiveSubAgents"><?php echo $subAgentStats['inactive'] ?? 0; ?></span></p>
                    <p class="status">Status: 
                        <span class="status-indicator <?php echo ($subAgentStats['active'] ?? 0) > 0 ? 'active' : 'inactive'; ?>">
                            <?php echo ($subAgentStats['active'] ?? 0) > 0 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Workers Card -->
            <div class="system-card" data-href="<?php echo htmlspecialchars(ratib_nav_url('Worker.php'), ENT_QUOTES, 'UTF-8'); ?>" data-permission="view_workers" tabindex="0" role="button" aria-label="Workers - Total: <?php echo $workerStats['total'] ?? 0; ?>">
                <h2>👷 Workers</h2>
                <div class="status-info">
                    <p class="count">Total: <span id="totalWorkers"><?php echo $workerStats['total'] ?? 0; ?></span></p>
                    <p class="count">Active: <span id="activeWorkers"><?php echo $workerStats['active'] ?? 0; ?></span></p>
                    <p class="count">Inactive: <span id="inactiveWorkers"><?php echo $workerStats['inactive'] ?? 0; ?></span></p>
                    <p class="status">Status: 
                        <span class="status-indicator <?php echo ($workerStats['active'] ?? 0) > 0 ? 'active' : 'inactive'; ?>">
                            <?php echo ($workerStats['active'] ?? 0) > 0 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </p>
                </div>
            </div>

            <div class="system-card" data-href="<?php echo htmlspecialchars(ratib_nav_url('partner-agencies.php'), ENT_QUOTES, 'UTF-8'); ?>" data-permission="view_partner_agencies,view_workers" tabindex="0" role="button" aria-label="Partnerships - Total agencies: <?php echo $partnershipStats['total_agencies'] ?? 0; ?>">
                <h2>🌍 Partnerships</h2>
                <div class="status-info">
                    <p class="count">Total Agencies: <span><?php echo $partnershipStats['total_agencies'] ?? 0; ?></span></p>
                    <p class="count">Active: <span><?php echo $partnershipStats['active_agencies'] ?? 0; ?></span></p>
                    <p class="count">Countries: <span><?php echo $partnershipStats['countries_count'] ?? 0; ?></span></p>
                </div>
            </div>

            <div class="system-card" data-href="<?php echo htmlspecialchars(ratib_nav_url('partner-agencies.php'), ENT_QUOTES, 'UTF-8'); ?>" data-permission="view_partner_agencies,view_workers" tabindex="0" role="button" aria-label="Deployed workers - Total abroad: <?php echo $deploymentStats['total_abroad'] ?? 0; ?>">
                <h2>✈️ Deployed Workers</h2>
                <div class="status-info">
                    <p class="count">Total Abroad: <span><?php echo $deploymentStats['total_abroad'] ?? 0; ?></span></p>
                    <p class="count">Active: <span><?php echo $deploymentStats['active_count'] ?? 0; ?></span></p>
                    <p class="count">Returned: <span><?php echo $deploymentStats['returned_count'] ?? 0; ?></span></p>
                    <p class="count">Issues: <span><?php echo $deploymentStats['issue_count'] ?? 0; ?></span></p>
                </div>
            </div>

            <!-- Cases Card -->
            <div class="system-card" data-href="<?php echo htmlspecialchars(ratib_nav_url('cases/cases-table.php'), ENT_QUOTES, 'UTF-8'); ?>" data-permission="view_cases" tabindex="0" role="button" aria-label="Cases - Total: <?php echo $caseStats['total'] ?? 0; ?>">
                <h2>📋 Cases</h2>
                <div class="status-info">
                    <p class="count">Total: <span id="totalCases"><?php echo $caseStats['total'] ?? 0; ?></span></p>
                    <p class="count">Open: <span id="openCases"><?php echo $caseStats['open'] ?? 0; ?></span></p>
                    <p class="count">Urgent: <span id="urgentCases"><?php echo $caseStats['urgent'] ?? 0; ?></span></p>
                    <p class="count">Resolved: <span id="resolvedCases"><?php echo $caseStats['resolved'] ?? 0; ?></span></p>
                    <p class="status">Status: 
                        <span class="status-indicator <?php echo ($caseStats['total'] ?? 0) > 0 ? 'active' : 'inactive'; ?>">
                            <?php echo ($caseStats['total'] ?? 0) > 0 ? 'Active' : 'No Cases'; ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- HR System Card -->
            <div class="system-card" data-href="<?php echo htmlspecialchars(ratib_nav_url('hr.php'), ENT_QUOTES, 'UTF-8'); ?>" data-permission="view_hr_dashboard" tabindex="0" role="button" aria-label="HR System - Total: <?php echo $hrStats['total'] ?? 0; ?>">
                <h2>👔 HR System</h2>
                <div class="status-info">
                    <p class="count">Total: <span id="totalHR"><?php echo $hrStats['total'] ?? 0; ?></span></p>
                    <p class="count">Active: <span id="activeHR"><?php echo $hrStats['active'] ?? 0; ?></span></p>
                    <p class="count">Inactive: <span id="inactiveHR"><?php echo $hrStats['inactive'] ?? 0; ?></span></p>
                    <p class="status">Status: 
                        <span class="status-indicator <?php echo ($hrStats['active'] ?? 0) > 0 ? 'active' : 'inactive'; ?>">
                            <?php echo ($hrStats['active'] ?? 0) > 0 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Accounting Card -->
            <div class="system-card" data-href="<?php echo htmlspecialchars(ratib_nav_url('accounting.php'), ENT_QUOTES, 'UTF-8'); ?>" data-permission="view_chart_accounts" tabindex="0" role="button" aria-label="Professional Accounting System">
                <h2>💰 Accounting</h2>
                <div class="status-info">
                    <p class="count">Invoices: <span id="totalInvoices"><?php echo $accountingStats['invoices'] ?? 0; ?></span></p>
                    <p class="count">Bills: <span id="totalBills"><?php echo $accountingStats['bills'] ?? 0; ?></span></p>
                    <p class="count">Transactions: <span id="totalTransactions"><?php echo $accountingStats['transactions'] ?? 0; ?></span></p>
                    <p class="count">Customers: <span id="totalCustomers"><?php echo $accountingStats['customers'] ?? 0; ?></span></p>
                    <p class="status">Status: 
                        <span class="status-indicator <?php echo ($accountingStats['transactions'] ?? 0) > 0 ? 'active' : 'inactive'; ?>">
                            <?php echo ($accountingStats['transactions'] ?? 0) > 0 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Reports Card -->
            <div class="system-card" data-href="<?php echo htmlspecialchars(ratib_nav_url('reports.php'), ENT_QUOTES, 'UTF-8'); ?>" data-permission="view_reports" tabindex="0" role="button" aria-label="Reports - Total: <?php echo $reportsStats['total'] ?? 0; ?>">
                <h2>📈 Reports</h2>
                <div class="status-info">
                    <p class="count">Total: <span id="totalReports"><?php echo $reportsStats['total'] ?? 0; ?></span></p>
                    <p class="count">Today: <span id="todayReports"><?php echo $reportsStats['today'] ?? 0; ?></span></p>
                    <p class="count">This Month: <span id="monthReports"><?php echo $reportsStats['this_month'] ?? 0; ?></span></p>
                    <p class="status">Status: 
                        <span class="status-indicator <?php echo ($reportsStats['total'] ?? 0) > 0 ? 'active' : 'inactive'; ?>">
                            <?php echo ($reportsStats['total'] ?? 0) > 0 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Contact Card -->
            <div class="system-card" data-href="<?php echo htmlspecialchars(ratib_nav_url('contact.php'), ENT_QUOTES, 'UTF-8'); ?>" data-permission="view_contacts" tabindex="0" role="button" aria-label="Contact - Total: <?php echo $contactStats['total'] ?? 0; ?>">
                <h2>📞 Contact</h2>
                <div class="status-info">
                    <p class="count">Total: <span id="totalContacts"><?php echo $contactStats['total'] ?? 0; ?></span></p>
                    <p class="count">Active: <span id="activeContacts"><?php echo $contactStats['active'] ?? 0; ?></span></p>
                    <p class="count">Inactive: <span id="inactiveContacts"><?php echo $contactStats['inactive'] ?? 0; ?></span></p>
                    <p class="status">Status: 
                        <span class="status-indicator <?php echo ($contactStats['active'] ?? 0) > 0 ? 'active' : 'inactive'; ?>">
                            <?php echo ($contactStats['active'] ?? 0) > 0 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </p>
                </div>
            </div>

            <?php if (false): /* control panel removed */ ?>
            <!-- Settings Card - Control Panel Only -->
            <div class="system-card" data-href="<?php echo htmlspecialchars(ratib_nav_url('system-settings.php'), ENT_QUOTES, 'UTF-8'); ?>" tabindex="0" role="button" aria-label="Settings - Total: <?php echo $settingsStats['total'] ?? 0; ?>">
                <h2>⚙️ Settings</h2>
                <div class="status-info">
                    <p class="count">Total: <span id="totalSettings"><?php echo $settingsStats['total'] ?? 0; ?></span></p>
                    <p class="count">Active: <span id="activeSettings"><?php echo $settingsStats['active'] ?? 0; ?></span></p>
                    <p class="status">Status: 
                        <span class="status-indicator <?php echo ($settingsStats['active'] ?? 0) > 0 ? 'active' : 'inactive'; ?>">
                            <?php echo ($settingsStats['active'] ?? 0) > 0 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Logout Card -->
            <!-- Removed as per edit hint -->

            <?php if (false): /* control panel removed */ ?>
            <!-- Visa Management Card - Control Panel Only -->
            <div class="system-card" data-href="<?php echo htmlspecialchars(ratib_nav_url('system-settings.php'), ENT_QUOTES, 'UTF-8'); ?>" tabindex="0" role="button" aria-label="Visa Management - Total: <?php echo $visaStats['total'] ?? 0; ?>">
                <h2>🛂 Visa Management</h2>
                <div class="status-info">
                    <p class="count">Total: <span id="totalVisas"><?php echo $visaStats['total'] ?? 0; ?></span></p>
                    <p class="count">Active: <span id="activeVisas"><?php echo $visaStats['active'] ?? 0; ?></span></p>
                    <p class="count">Inactive: <span id="inactiveVisas"><?php echo $visaStats['inactive'] ?? 0; ?></span></p>
                    <p class="status">Status: 
                        <span class="status-indicator <?php echo ($visaStats['active'] ?? 0) > 0 ? 'active' : 'inactive'; ?>">
                            <?php echo ($visaStats['active'] ?? 0) > 0 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notifications Card -->
            <div class="system-card" data-href="<?php echo htmlspecialchars(ratib_nav_url('notifications.php'), ENT_QUOTES, 'UTF-8'); ?>" data-permission="view_notifications" tabindex="0" role="button" aria-label="Notifications - New: <?php echo $notificationStats['new'] ?? 0; ?>">
                <h2>🔔 Notifications</h2>
                <div class="status-info">
                    <p class="count">Total: <span id="totalNotifications"><?php echo $notificationStats['total'] ?? 0; ?></span></p>
                    <p class="count">New: <span id="newNotifications"><?php echo $notificationStats['new'] ?? 0; ?></span></p>
                    <p class="status">Status: 
                        <span class="status-indicator <?php echo ($notificationStats['new'] ?? 0) > 0 ? 'active' : 'neutral'; ?>">
                            <?php echo ($notificationStats['new'] ?? 0) > 0 ? 'New Alerts' : 'No Alerts'; ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- User Profile Card -->
            <div class="system-card" data-action="open-profile-modal" data-user-id="<?php echo intval($_SESSION['user_id'] ?? 0); ?>" tabindex="0" role="button" aria-label="My Profile - <?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?>">
                <h2>👤 My Profile</h2>
                <div class="status-info">
                    <p class="user-info">User: <?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?></p>
                    <p class="user-role">Role: <?php echo ucfirst($_SESSION['role'] ?? 'User'); ?></p>
                    <?php if (!empty($_SESSION['role_description'])): ?>
                        <p class="role-desc"><?php echo htmlspecialchars($_SESSION['role_description']); ?></p>
                    <?php endif; ?>
                    <p class="last-login">Last Login: <span id="lastLoginTime">Loading...</span></p>
                </div>
            </div>
        </div>

        <!-- Modern Charts Section -->
        <div class="charts-section" role="region" aria-label="Dashboard Analytics">
            <div class="charts-section-header">
                <h2>📊 Analytics & Insights</h2>
                <div class="charts-actions">
                    <button class="btn-chart-action" id="refreshChartsBtn" title="Refresh Charts">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn-chart-action" id="exportAllChartsBtn" title="Export All Charts">
                        <i class="fas fa-download"></i> Export All
                    </button>
                </div>
            </div>
            <div class="charts-grid">
                <!-- System Overview Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-bar"></i> System Overview</h3>
                        <div class="chart-actions">
                            <button class="chart-action-btn" data-chart="systemOverviewChart" data-action="fullscreen" title="Fullscreen">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button class="chart-action-btn" data-chart="systemOverviewChart" data-action="export" title="Export Chart">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="chart-action-btn" data-chart="systemOverviewChart" data-action="print" title="Print Chart">
                                <i class="fas fa-print"></i>
                            </button>
                            <button class="chart-action-btn" data-chart="systemOverviewChart" data-action="refresh" title="Refresh Chart">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="systemOverviewChart" aria-label="System Overview Bar Chart showing total and active counts for Agents, SubAgents, Workers, HR, and Contacts" role="img"></canvas>
                    </div>
                </div>

                <!-- Status Distribution Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-pie"></i> Status Distribution</h3>
                        <div class="chart-actions">
                            <button class="chart-action-btn" data-chart="statusDistributionChart" data-action="fullscreen" title="Fullscreen">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button class="chart-action-btn" data-chart="statusDistributionChart" data-action="export" title="Export Chart">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="chart-action-btn" data-chart="statusDistributionChart" data-action="print" title="Print Chart">
                                <i class="fas fa-print"></i>
                            </button>
                            <button class="chart-action-btn" data-chart="statusDistributionChart" data-action="refresh" title="Refresh Chart">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusDistributionChart" aria-label="Status Distribution Doughnut Chart showing active and inactive status breakdown" role="img"></canvas>
                    </div>
                </div>

                <!-- Cases Status Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-clipboard-list"></i> Cases Breakdown</h3>
                        <div class="chart-actions">
                            <button class="chart-action-btn" data-chart="casesChart" data-action="fullscreen" title="Fullscreen">
                                <i class="fas fa-expand"></i>
                            </button>
                            <button class="chart-action-btn" data-chart="casesChart" data-action="export" title="Export Chart">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="chart-action-btn" data-chart="casesChart" data-action="print" title="Print Chart">
                                <i class="fas fa-print"></i>
                            </button>
                            <button class="chart-action-btn" data-chart="casesChart" data-action="refresh" title="Refresh Chart">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="casesChart" aria-label="Cases Breakdown Pie Chart showing open, urgent, and resolved cases" role="img"></canvas>
                    </div>
                </div>

                <!-- Activity Trends Chart -->
                <div class="chart-card chart-card-full">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-line"></i> Activity Trends</h3>
                        <div class="chart-header-controls">
                            <div class="chart-date-range">
                                <select id="activityTrendsRange" class="chart-range-select">
                                    <option value="7">Last 7 Days</option>
                                    <option value="14">Last 14 Days</option>
                                    <option value="30" selected>Last 30 Days</option>
                                    <option value="90">Last 90 Days</option>
                                </select>
                            </div>
                            <div class="chart-actions">
                                <button class="chart-action-btn" data-chart="activityTrendsChart" data-action="fullscreen" title="Fullscreen">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button class="chart-action-btn" data-chart="activityTrendsChart" data-action="export" title="Export Chart">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button class="chart-action-btn" data-chart="activityTrendsChart" data-action="print" title="Print Chart">
                                    <i class="fas fa-print"></i>
                                </button>
                                <button class="chart-action-btn" data-chart="activityTrendsChart" data-action="refresh" title="Refresh Chart">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="activityTrendsChart" aria-label="Activity Trends Line Chart showing activities and reports over the last 7 days" role="img"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="activities-section" role="region" aria-label="Recent Activities">
            <h2>📜 Recent Activities</h2>
            <div class="activity-list">
                <?php if ($activities && $activities->num_rows > 0): ?>
                    <?php while ($activity = $activities->fetch_assoc()): ?>
                        <div class="activity-item" role="article" tabindex="0">
                            <div class="activity-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="activity-details">
                                <p><?php echo htmlspecialchars($activity['description'] ?? 'Activity'); ?></p>
                                <small>By <?php echo htmlspecialchars($activity['username'] ?? 'System'); ?> - <?php 
                                    $date = $activity['created_at'] ?? null;
                                    echo $date ? date('M d, Y H:i', strtotime($date)) : 'Date not available';
                                ?></small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="activity-details">
                            <p>No recent activities found</p>
                            <small>Activities will appear here as you use the system</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Profile Modal (Same design as System Settings Users Table) -->
<div id="mainModal" class="modern-modal modal-hidden">
    <div class="modern-modal-content">
        <div class="modern-modal-header">
            <h2 class="modern-modal-title" id="modalTitle">👤 My Profile</h2>
            <button class="modal-close" data-action="close-modal" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="modalBody">
            <!-- Profile content will be loaded here -->
        </div>
    </div>
</div>

<!-- Form Popup Modal (for Edit Form) -->
<div id="formPopupModal" class="modern-modal modal-hidden">
    <div class="modern-modal-content form-popup-content">
        <div class="modern-modal-header">
            <h2 class="modern-modal-title" id="formPopupTitle">Edit Profile</h2>
            <button class="modal-close" data-action="close-form">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="formPopupBody">
            <!-- Form content will be loaded here by ModernForms -->
        </div>
    </div>
</div>

<!-- Fingerprint Registration Modal -->
<div id="fingerprintRegistrationModal" class="modern-modal modal-hidden">
    <div class="modern-modal-content fingerprint-modal-content">
        <div class="modern-modal-header">
            <h2 class="modern-modal-title">
                <i class="fas fa-fingerprint"></i>
                <span id="fingerprintModalTitle">Register Fingerprint</span>
            </h2>
            <button class="modal-close" data-action="close-fingerprint-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="fingerprint-registration-body">
            <div class="fingerprint-info-section fingerprint-user-info">
                <h3 class="fingerprint-section-title">
                    <i class="fas fa-user"></i> User Information
                </h3>
                <div class="fingerprint-info-grid">
                    <strong>Username:</strong>
                    <span id="fingerprintRegUsername">-</span>
                    <strong>User ID:</strong>
                    <span id="fingerprintRegUserId">-</span>
                </div>
            </div>
            
            <div class="fingerprint-info-section fingerprint-prerequisites">
                <h3 class="fingerprint-section-title fingerprint-section-title-warning">
                    <i class="fas fa-exclamation-triangle"></i> Prerequisites
                </h3>
                <p class="fingerprint-text">
                    <strong>1. Set up Windows Password first</strong> (Required for Windows Hello)
                </p>
                <p class="fingerprint-text">
                    <strong>2. Set up Windows Hello Fingerprint</strong> in Windows Settings > Accounts > Sign-in options
                </p>
            </div>
            
            <div class="fingerprint-info-section fingerprint-instructions">
                <h3 class="fingerprint-section-title fingerprint-section-title-success">
                    <i class="fas fa-info-circle"></i> How to Register
                </h3>
                <ol class="fingerprint-steps-list">
                    <li>Click <strong>"Register Fingerprint"</strong> button below</li>
                    <li><strong>Place your finger</strong> on the scanner when prompted</li>
                    <li>Wait for the scan to complete</li>
                    <li>Your fingerprint will be saved automatically</li>
                </ol>
            </div>
            
            <div id="fingerprintRegistrationStatus" class="fingerprint-status-hidden"></div>
        </div>
        
        <div class="fingerprint-actions">
            <button class="modern-btn modern-btn-secondary fingerprint-action-btn" data-action="close-fingerprint-modal">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button id="fingerprintRegisterBtn" class="modern-btn modern-btn-primary fingerprint-action-btn" data-action="execute-fingerprint-registration">
                <i class="fas fa-fingerprint"></i> Register Fingerprint
            </button>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>