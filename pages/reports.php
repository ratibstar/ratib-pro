<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/reports.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/reports.php`.
 */
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/permissions.php');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// Check if user has permission to view reports
if (!hasPermission('view_reports')) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// Get total reports count from API with database fallback
$totalReports = 0;
$todayReports = 0;
$monthReports = 0;

$activityTotal = 0;
$systemTotal = 0;
$caseActivityTotal = 0;
$activityToday = 0;
$systemToday = 0;
$caseActivityToday = 0;
$activityMonth = 0;
$systemMonth = 0;
$caseActivityMonth = 0;
$globalHistoryTotal = 0;
$globalHistoryToday = 0;
$globalHistoryMonth = 0;

$statsLoaded = false;
    
// Fetch log stats from API
try {
    $apiUrl = apiUrl('reports/reports.php') . '?action=get_log_stats';
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data && $data['success'] && isset($data['data'])) {
            $stats = $data['data'];
            
            $activityTotal = $stats['activity_logs']['total'] ?? 0;
            $activityToday = $stats['activity_logs']['today'] ?? 0;
            $activityMonth = $stats['activity_logs']['month'] ?? 0;
            
            $systemTotal = $stats['system_events']['total'] ?? 0;
            $systemToday = $stats['system_events']['today'] ?? 0;
            $systemMonth = $stats['system_events']['month'] ?? 0;
            
            $caseActivityTotal = $stats['case_activities']['total'] ?? 0;
            $caseActivityToday = $stats['case_activities']['today'] ?? 0;
            $caseActivityMonth = $stats['case_activities']['month'] ?? 0;
            
            $globalHistoryTotal = $stats['global_history']['total'] ?? 0;
            $globalHistoryToday = $stats['global_history']['today'] ?? 0;
            $globalHistoryMonth = $stats['global_history']['month'] ?? 0;
            
            $totalReports = $activityTotal + $systemTotal + $caseActivityTotal + $globalHistoryTotal;
            $todayReports = $activityToday + $systemToday + $caseActivityToday + $globalHistoryToday;
            $monthReports = $activityMonth + $systemMonth + $caseActivityMonth + $globalHistoryMonth;
            
            $statsLoaded = true;
        } else {
            error_log("Reports page - API returned invalid data. HTTP: $httpCode, Response: " . substr($response, 0, 200));
        }
    } else {
        error_log("Reports page - API call failed. HTTP: $httpCode, Error: $curlError");
    }
} catch (Exception $e) {
    error_log("Reports page - API error: " . $e->getMessage());
}

// Fallback: Query database directly if API call failed
if (!$statsLoaded) {
    try {
        require_once(__DIR__ . '/../includes/config.php');
        
        // Helper function to get stats for a table
        function getTableStats($conn, $tableName) {
            $stats = ['total' => 0, 'today' => 0, 'month' => 0];
            $tableCheck = $conn->query("SHOW TABLES LIKE '$tableName'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                // Total count
                $result = $conn->query("SELECT COUNT(*) as count FROM $tableName");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['total'] = (int)($row['count'] ?? 0);
                }
                
                // Today count
                $result = $conn->query("SELECT COUNT(*) as count FROM $tableName WHERE DATE(created_at) = CURDATE()");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['today'] = (int)($row['count'] ?? 0);
                }
                
                // Month count
                $result = $conn->query("SELECT COUNT(*) as count FROM $tableName WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['month'] = (int)($row['count'] ?? 0);
                }
            }
            return $stats;
        }

        function getSystemEventStats($conn) {
            $stats = ['total' => 0, 'today' => 0, 'month' => 0];
            $tableCheck = $conn->query("SHOW TABLES LIKE 'system_events'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $where = "(event_type LIKE 'CONTROL_%' OR event_type LIKE 'QUERY_%' OR event_type = 'ADMIN_AUDIT')";
                $result = $conn->query("SELECT COUNT(*) as count FROM system_events WHERE {$where}");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['total'] = (int)($row['count'] ?? 0);
                }
                $result = $conn->query("SELECT COUNT(*) as count FROM system_events WHERE {$where} AND DATE(created_at) = CURDATE()");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['today'] = (int)($row['count'] ?? 0);
                }
                $result = $conn->query("SELECT COUNT(*) as count FROM system_events WHERE {$where} AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['month'] = (int)($row['count'] ?? 0);
                }
            }
            return $stats;
        }
        
        // Get stats for each table
        $activityStats = getTableStats($conn, 'activity_logs');
        $activityTotal = $activityStats['total'];
        $activityToday = $activityStats['today'];
        $activityMonth = $activityStats['month'];
        
        $systemStats = getSystemEventStats($conn);
        $systemTotal = $systemStats['total'];
        $systemToday = $systemStats['today'];
        $systemMonth = $systemStats['month'];
        
        $caseActivityStats = getTableStats($conn, 'case_activities');
        $caseActivityTotal = $caseActivityStats['total'];
        $caseActivityToday = $caseActivityStats['today'];
        $caseActivityMonth = $caseActivityStats['month'];
        
        $globalHistoryStats = getTableStats($conn, 'global_history');
        $globalHistoryTotal = $globalHistoryStats['total'];
        $globalHistoryToday = $globalHistoryStats['today'];
        $globalHistoryMonth = $globalHistoryStats['month'];
        
        // Calculate totals
        $totalReports = $activityTotal + $systemTotal + $caseActivityTotal + $globalHistoryTotal;
        $todayReports = $activityToday + $systemToday + $caseActivityToday + $globalHistoryToday;
        $monthReports = $activityMonth + $systemMonth + $caseActivityMonth + $globalHistoryMonth;
        
    } catch (Exception $e) {
        error_log("Reports page - Database fallback error: " . $e->getMessage());
    }
}

$pageTitle = "Reports Management";
$pageCss = [
    asset('css/reports.css'),
    "https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css"
];
$pageJs = [
    "https://cdn.jsdelivr.net/npm/moment/moment.min.js",
    "https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js",
    "https://cdn.jsdelivr.net/npm/chart.js",
    asset('js/reports.js')
];

require_once(__DIR__ . '/../includes/header.php');
?>

<div class="reports-container">
    <!-- Reports Header -->
    <div class="reports-header">
        <h2><i class="fas fa-chart-line"></i> Reports Dashboard</h2>
        <div class="report-actions">
            <button class="btn-refresh" data-action="refresh-reports">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button class="btn-export" data-action="export-report">
                <i class="fas fa-file-export"></i> Export
            </button>
            <button class="btn-print" data-action="print-report">
                <i class="fas fa-print"></i> Print
            </button>
            <button class="btn-individual" data-action="go-to-individual-reports">
                <i class="fas fa-user-chart"></i> Individual Reports
            </button>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="status-cards-grid">
        <!-- Total Reports Card -->
        <div class="reports-status-card reports-status-card-purple">
            <div class="reports-status-card-header">
                <h3>Total Reports</h3>
                <i class="fas fa-chart-bar reports-status-card-icon"></i>
            </div>
            <div class="reports-status-card-value"><?php echo number_format($totalReports); ?></div>
            <div class="reports-status-card-desc">All activity logs combined</div>
        </div>

        <!-- Today Reports Card -->
        <div class="reports-status-card reports-status-card-pink">
            <div class="reports-status-card-header">
                <h3>Today</h3>
                <i class="fas fa-calendar-day reports-status-card-icon"></i>
            </div>
            <div class="reports-status-card-value"><?php echo number_format($todayReports); ?></div>
            <div class="reports-status-card-desc">Reports generated today</div>
        </div>

        <!-- This Month Reports Card -->
        <div class="reports-status-card reports-status-card-blue">
            <div class="reports-status-card-header">
                <h3>This Month</h3>
                <i class="fas fa-calendar-alt reports-status-card-icon"></i>
            </div>
            <div class="reports-status-card-value"><?php echo number_format($monthReports); ?></div>
            <div class="reports-status-card-desc">Reports this month</div>
        </div>

        <!-- Status Card -->
        <div class="reports-status-card <?php echo ($totalReports > 0) ? 'reports-status-card-green' : 'reports-status-card-grey'; ?>">
            <div class="reports-status-card-header">
                <h3>Status</h3>
                <i class="fas fa-<?php echo ($totalReports > 0) ? 'check-circle' : 'times-circle'; ?> reports-status-card-icon"></i>
            </div>
            <div class="reports-status-card-value reports-status-card-value-small">
                <?php echo ($totalReports > 0) ? 'Active' : 'Inactive'; ?>
            </div>
            <div class="reports-status-card-desc">
                <?php echo ($totalReports > 0) ? 'Reports system is active' : 'No reports available'; ?>
            </div>
        </div>
    </div>

    <!-- Breakdown Cards -->
    <div class="breakdown-cards-grid">
        <div class="reports-breakdown-card">
            <div class="reports-breakdown-card-header">
                <i class="fas fa-history reports-breakdown-card-icon reports-breakdown-card-icon-activity"></i>
                <h4>Activity Logs</h4>
            </div>
            <div class="reports-breakdown-card-value"><?php echo number_format($activityTotal); ?></div>
            <div class="reports-breakdown-card-today">Today: <?php echo number_format($activityToday); ?></div>
        </div>

        <div class="reports-breakdown-card">
            <div class="reports-breakdown-card-header">
                <i class="fas fa-cog reports-breakdown-card-icon reports-breakdown-card-icon-system"></i>
                <h4>System Logs</h4>
            </div>
            <div class="reports-breakdown-card-value"><?php echo number_format($systemTotal); ?></div>
            <div class="reports-breakdown-card-today">Today: <?php echo number_format($systemToday); ?></div>
        </div>

        <div class="reports-breakdown-card">
            <div class="reports-breakdown-card-header">
                <i class="fas fa-briefcase reports-breakdown-card-icon reports-breakdown-card-icon-case"></i>
                <h4>Case Activities</h4>
            </div>
            <div class="reports-breakdown-card-value"><?php echo number_format($caseActivityTotal); ?></div>
            <div class="reports-breakdown-card-today">Today: <?php echo number_format($caseActivityToday); ?></div>
        </div>

        <div class="reports-breakdown-card">
            <div class="reports-breakdown-card-header">
                <i class="fas fa-globe reports-breakdown-card-icon reports-breakdown-card-icon-global"></i>
                <h4>Global History</h4>
            </div>
            <div class="reports-breakdown-card-value"><?php echo number_format($globalHistoryTotal); ?></div>
            <div class="reports-breakdown-card-today">Today: <?php echo number_format($globalHistoryToday); ?></div>
        </div>
    </div>

    <!-- Report Categories -->
    <div class="report-categories">
        <div class="category-card active" data-category="agents" data-action="switch-category">
            <i class="fas fa-user-tie"></i>
            <span>Agents</span>
        </div>
        <div class="category-card" data-category="subagents" data-action="switch-category">
            <i class="fas fa-users"></i>
            <span>SubAgents</span>
        </div>
        <div class="category-card" data-category="workers" data-action="switch-category">
            <i class="fas fa-hard-hat"></i>
            <span>Workers</span>
        </div>
        <div class="category-card" data-category="cases" data-action="switch-category">
            <i class="fas fa-briefcase"></i>
            <span>Cases</span>
        </div>
        <div class="category-card" data-category="hr" data-action="switch-category">
            <i class="fas fa-users-cog"></i>
            <span>HR</span>
        </div>
        <div class="category-card" data-category="financial" data-action="switch-category">
            <i class="fas fa-dollar-sign"></i>
            <span>Financial</span>
        </div>
    </div>

    <!-- Quick Stats - Analytics Cards at Top -->
    <div class="quick-stats">
        <!-- Stats will be populated based on category -->
    </div>

    <!-- Filters Section - LTR and English only, no Arabic -->
    <div class="report-filters" dir="ltr" lang="en">
        <div class="filter-row">
            <div class="filter-group">
                <label>Date Range</label>
                <input type="text" id="dateRangePicker" class="date-range-picker" autocomplete="off">
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select id="statusFilter">
                    <option value="all">All Status</option>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Sort By</label>
                <select id="sortBy">
                    <option value="date">Date</option>
                    <option value="name">Name</option>
                    <option value="amount">Amount</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-section">
        <div class="chart-row">
            <div class="chart-container">
                <h3>Performance Overview</h3>
                <canvas id="performanceChart"></canvas>
            </div>
            <div class="chart-container">
                <h3>Revenue Analysis</h3>
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Detailed Reports -->
    <div class="detailed-reports">
        <div class="report-tabs">
            <button class="tab-btn active" data-tab="summary">Summary</button>
            <button class="tab-btn" data-tab="details">Detailed View</button>
        </div>

        <div class="report-content">
            <!-- Summary Tab Content -->
            <div class="tab-content active" id="summary">
                <div class="table-container">
                    <table class="reports-table">
                        <thead>
                            <!-- Dynamic headers based on category -->
                        </thead>
                        <tbody>
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Detailed View Tab Content -->
            <div class="tab-content" id="details">
                <div class="table-container">
                    <table class="reports-table">
                        <thead>
                            <!-- Dynamic headers based on category -->
                        </thead>
                        <tbody>
                            <!-- Dynamic content -->
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- Category-specific templates -->
<template id="agentsStats">
    <div class="stat-card">
        <i class="fas fa-user-tie"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">Total Agents</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-user-check"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">Active Agents</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-user-plus"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">New This Month</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-dollar-sign"></i>
        <div class="stat-info">
            <span class="stat-value">$0</span>
            <span class="stat-label">Revenue</span>
        </div>
    </div>
</template>

<template id="subagentsStats">
    <div class="stat-card">
        <i class="fas fa-users"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">Total SubAgents</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-user-check"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">Active SubAgents</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-user-plus"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">New This Month</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-dollar-sign"></i>
        <div class="stat-info">
            <span class="stat-value">$0</span>
            <span class="stat-label">Commission</span>
        </div>
    </div>
</template>

<template id="workersStats">
    <div class="stat-card">
        <i class="fas fa-hard-hat"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">Total Workers</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-user-check"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">Active Workers</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-user-plus"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">New This Month</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-dollar-sign"></i>
        <div class="stat-info">
            <span class="stat-value">$0</span>
            <span class="stat-label">Payroll</span>
        </div>
    </div>
</template>

<template id="casesStats">
    <div class="stat-card">
        <i class="fas fa-briefcase"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">Total Cases</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-briefcase"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">Active Cases</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-plus"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">New This Month</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-dollar-sign"></i>
        <div class="stat-info">
            <span class="stat-value">$0</span>
            <span class="stat-label">Revenue</span>
        </div>
    </div>
</template>

<template id="hrStats">
    <div class="stat-card">
        <i class="fas fa-users-cog"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">Total Employees</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-user-check"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">Active Employees</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-user-plus"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">New This Month</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-dollar-sign"></i>
        <div class="stat-info">
            <span class="stat-value">$0</span>
            <span class="stat-label">Payroll</span>
        </div>
    </div>
</template>

<template id="financialStats">
    <div class="stat-card">
        <i class="fas fa-exchange-alt"></i>
        <div class="stat-info">
            <span class="stat-value">0</span>
            <span class="stat-label">Total Transactions</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-arrow-up"></i>
        <div class="stat-info">
            <span class="stat-value">$0</span>
            <span class="stat-label">Total Revenue</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-arrow-down"></i>
        <div class="stat-info">
            <span class="stat-value">$0</span>
            <span class="stat-label">Total Expenses</span>
        </div>
    </div>
    <div class="stat-card">
        <i class="fas fa-dollar-sign"></i>
        <div class="stat-info">
            <span class="stat-value">$0</span>
            <span class="stat-label">Net Profit</span>
        </div>
    </div>
</template>


<?php require_once(__DIR__ . '/../includes/footer.php'); ?>
