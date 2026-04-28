<?php
/**
 * EN: Handles API endpoint/business logic in `api/reports/reports.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/reports/reports.php`.
 */
/**
 * Reports API Endpoint
 * Handles all report-related data requests
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../core/ratib_api_session.inc.php';
ratib_api_pick_session_name();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_category_data':
            getCategoryData();
            break;
        case 'get_stats':
            getStats();
            break;
        case 'get_log_stats':
            getLogStats();
            break;
        case 'get_chart_data':
            getChartData();
            break;
        case 'get_table_data':
            getTableData();
            break;
        case 'export_data':
            exportData();
            break;
        case 'debug_table':
            debugTable();
            break;
        case 'clear_calculations':
            clearCalculations();
            break;
        case 'debug_global_history':
            debugGlobalHistory();
            break;
        case 'clear_reports_history':
            clearReportsHistory();
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function getCategoryData() {
    $category = $_GET['category'] ?? 'agents';
    $filters = getFilters();
    $debug = isset($_GET['debug']) && $_GET['debug'] === '1';
    
    error_log("=== getCategoryData DEBUG ===");
    error_log("Category: " . $category);
    error_log("Filters: " . json_encode($filters));
    
    $stats = getCategoryStats($category, $filters);
    error_log("Stats count: " . count($stats));
    
    $tableData = getCategoryTableData($category, $filters);
    error_log("TableData count: " . count($tableData));
    error_log("TableData sample: " . json_encode(array_slice($tableData, 0, 2)));
    
    // If tableData is empty but stats show data exists, log a warning
    if (empty($tableData) && !empty($stats)) {
        error_log("WARNING: tableData is empty but stats show data exists!");
        error_log("This suggests the tableData query is failing or filtering everything out.");
    }
    
    $data = [
        'stats' => $stats,
        'charts' => getCategoryCharts($category, $filters),
        'tableData' => $tableData,
        'analytics' => generateAnalyticsData($stats, $category)
    ];
    
    error_log("Final data structure - stats: " . count($data['stats']) . ", tableData: " . count($data['tableData']));
    
    $response = ['success' => true, 'data' => $data];
    
    // Add debug info if requested
    if ($debug) {
        $response['debug'] = [
            'category' => $category,
            'filters' => $filters,
            'tableDataCount' => count($tableData),
            'statsCount' => count($stats)
        ];
    }
    
    echo json_encode($response);
}

function generateAnalyticsData($stats, $category) {
    $analytics = [];
    
    if (empty($stats)) {
        return [];
    }
    
    // Convert stats to analytics format
    foreach ($stats as $stat) {
        $analytics[] = [
            'title' => $stat['label'] ?? 'Metric',
            'value' => $stat['value'] ?? 'N/A',
            'trend' => '',
            'trend_class' => ''
        ];
    }
    
    return $analytics;
}

function getStats() {
    $category = $_GET['category'] ?? 'agents';
    $filters = getFilters();
    
    $stats = getCategoryStats($category, $filters);
    echo json_encode(['success' => true, 'data' => $stats]);
}

function getChartData() {
    $category = $_GET['category'] ?? 'agents';
    $chartType = $_GET['chart_type'] ?? 'performance';
    $filters = getFilters();
    
    $chartData = getCategoryCharts($category, $filters);
    echo json_encode(['success' => true, 'data' => $chartData[$chartType] ?? []]);
}

function getTableData() {
    $category = $_GET['category'] ?? 'agents';
    $filters = getFilters();
    
    $tableData = getCategoryTableData($category, $filters);
    echo json_encode(['success' => true, 'data' => $tableData]);
}

function exportData() {
    $category = $_GET['category'] ?? 'agents';
    $format = $_GET['format'] ?? 'csv';
    $filters = getFilters();
    
    $data = getCategoryTableData($category, $filters);
    
    if ($format === 'csv') {
        exportCSV($data, $category);
    } else {
        echo json_encode(['success' => true, 'data' => $data]);
    }
}

function getFilters() {
    return [
        'dateRange' => [
            'start' => $_GET['start_date'] ?? null,
            'end' => $_GET['end_date'] ?? null
        ],
        'status' => $_GET['status'] ?? 'all',
        'sortBy' => $_GET['sort_by'] ?? 'date'
    ];
}

/**
 * Get the amount column for financial_transactions (total_amount or amount)
 */
function getFinancialAmountColumn($conn) {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
    if (!$tableCheck || $tableCheck->num_rows === 0) return null;
    $cols = $conn->query("SHOW COLUMNS FROM financial_transactions");
    if (!$cols) return null;
    $fields = [];
    while ($col = $cols->fetch_assoc()) $fields[] = $col['Field'];
    if (in_array('total_amount', $fields)) return 'total_amount';
    if (in_array('amount', $fields)) return 'amount';
    return null;
}

/**
 * Get the date column for financial_transactions (transaction_date or created_at)
 */
function getFinancialDateColumn($conn) {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
    if (!$tableCheck || $tableCheck->num_rows === 0) return 'created_at';
    $cols = $conn->query("SHOW COLUMNS FROM financial_transactions");
    if (!$cols) return 'created_at';
    $fields = [];
    while ($col = $cols->fetch_assoc()) $fields[] = $col['Field'];
    return in_array('transaction_date', $fields) ? 'transaction_date' : 'created_at';
}

/**
 * Build date filter for financial_transactions (use transaction_date/created_at, not entity columns)
 */
function buildFinancialDateFilter($conn, $filters) {
    if (empty($filters['dateRange']['start']) || empty($filters['dateRange']['end'])) return '';
    $dateCol = getFinancialDateColumn($conn);
    $start = $conn->real_escape_string($filters['dateRange']['start']);
    $end = $conn->real_escape_string($filters['dateRange']['end']);
    return " AND {$dateCol} BETWEEN '{$start}' AND '{$end}'";
}

/**
 * Transaction type check - case insensitive (Income/income/INCOME)
 */
function getIncomeTypeCondition() {
    return "LOWER(transaction_type) = 'income'";
}
function getExpenseTypeCondition() {
    return "LOWER(transaction_type) = 'expense'";
}

function getCategoryStats($category, $filters) {
    global $conn;
    
    switch ($category) {
        case 'agents':
            return getAgentsStats($filters);
        case 'subagents':
            return getSubAgentsStats($filters);
        case 'workers':
            return getWorkersStats($filters);
        case 'cases':
            return getCasesStats($filters);
        case 'hr':
            return getHRStats($filters);
        case 'financial':
            return getFinancialStats($filters);
        default:
            return [];
    }
}

function getAgentsStats($filters) {
    global $conn;
    
    try {
        $whereConditions = buildWhereClause($filters, 'agents');
        $whereClause = !empty($whereConditions) ? " AND " . $whereConditions : "";
        
        // Total agents
        $query = "SELECT COUNT(*) as count FROM agents WHERE status IN ('active', 'inactive', 'pending')" . $whereClause;
        $result = $conn->query($query);
        $totalAgents = $result ? (int)$result->fetch_assoc()['count'] : 0;
        
        // Active agents
        $query = "SELECT COUNT(*) as count FROM agents WHERE status = 'active'" . $whereClause;
        $result = $conn->query($query);
        $activeAgents = $result ? (int)$result->fetch_assoc()['count'] : 0;
        
        // New this month
        $query = "SELECT COUNT(*) as count FROM agents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status IN ('active', 'inactive', 'pending')";
        $result = $conn->query($query);
        $newThisMonth = $result ? (int)$result->fetch_assoc()['count'] : 0;
        
        // Revenue - from financial_transactions (use correct amount column, case-insensitive type)
        $revenue = 0;
        $amountCol = getFinancialAmountColumn($conn);
        if ($amountCol) {
            $dateFilter = buildFinancialDateFilter($conn, $filters);
            $incomeCond = getIncomeTypeCondition();
            $query = "SELECT SUM(COALESCE({$amountCol}, 0)) as total FROM financial_transactions 
                     WHERE entity_type = 'agent' AND {$incomeCond}" . $dateFilter;
            $result = $conn->query($query);
            if ($result) {
                $row = $result->fetch_assoc();
                $revenue = (float)($row['total'] ?? 0);
            }
        }
        // No fallback estimate: show $0 when accounting not used

        return [
            [
                'label' => 'Total Agents',
                'value' => $totalAgents,
                'icon' => 'fas fa-user-tie',
                'color' => '#667eea'
            ],
            [
                'label' => 'Active Agents',
                'value' => $activeAgents,
                'icon' => 'fas fa-user-check',
                'color' => '#4CAF50'
            ],
            [
                'label' => 'New This Month',
                'value' => $newThisMonth,
                'icon' => 'fas fa-user-plus',
                'color' => '#FF9800'
            ],
            [
                'label' => 'Revenue',
                'value' => '$' . number_format($revenue, 2),
                'icon' => 'fas fa-dollar-sign',
                'color' => '#2196F3'
            ]
        ];
    } catch (Exception $e) {
        error_log("Error getting agents stats: " . $e->getMessage());
        return getDefaultStats('Agents');
    }
}

function getSubAgentsStats($filters) {
    global $conn;
    
    try {
        $whereClause = buildWhereClause($filters, 'subagents');
        
        $query = "SELECT COUNT(*) as count FROM subagents WHERE status IN ('active', 'inactive', 'pending')" . $whereClause;
        $result = $conn->query($query);
        $total = $result ? (int)$result->fetch_assoc()['count'] : 0;
        
        $query = "SELECT COUNT(*) as count FROM subagents WHERE status = 'active'" . $whereClause;
        $result = $conn->query($query);
        $active = $result ? (int)$result->fetch_assoc()['count'] : 0;
        
        $query = "SELECT COUNT(*) as count FROM subagents WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = $conn->query($query);
        $newThisMonth = $result ? (int)$result->fetch_assoc()['count'] : 0;
        
        $revenue = 0;
        $amountCol = getFinancialAmountColumn($conn);
        if ($amountCol) {
            $dateFilter = buildFinancialDateFilter($conn, $filters);
            $incomeCond = getIncomeTypeCondition();
            $query = "SELECT SUM(COALESCE({$amountCol}, 0)) as total FROM financial_transactions 
                     WHERE entity_type = 'subagent' AND {$incomeCond}" . $dateFilter;
            $result = $conn->query($query);
            if ($result) {
                $row = $result->fetch_assoc();
                $revenue = (float)($row['total'] ?? 0);
            }
        }
        // No fallback estimate: show $0 when accounting not used

        return [
            ['label' => 'Total SubAgents', 'value' => $total, 'icon' => 'fas fa-users', 'color' => '#667eea'],
            ['label' => 'Active SubAgents', 'value' => $active, 'icon' => 'fas fa-user-check', 'color' => '#4CAF50'],
            ['label' => 'New This Month', 'value' => $newThisMonth, 'icon' => 'fas fa-user-plus', 'color' => '#FF9800'],
            ['label' => 'Commission', 'value' => '$' . number_format($revenue, 2), 'icon' => 'fas fa-dollar-sign', 'color' => '#2196F3']
        ];
    } catch (Exception $e) {
        return getDefaultStats('SubAgents');
    }
}

function getWorkersStats($filters) {
    global $conn;
    
    try {
        // Check if workers table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'workers'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return [
                ['label' => 'Total Workers', 'value' => 0, 'icon' => 'fas fa-hard-hat', 'color' => '#667eea'],
                ['label' => 'Active Workers', 'value' => 0, 'icon' => 'fas fa-user-check', 'color' => '#4CAF50'],
                ['label' => 'New This Month', 'value' => 0, 'icon' => 'fas fa-user-plus', 'color' => '#FF9800'],
                ['label' => 'Payroll', 'value' => '$0.00', 'icon' => 'fas fa-dollar-sign', 'color' => '#2196F3']
            ];
        }
        
        // Get column names to detect correct columns
        $cols = $conn->query("SHOW COLUMNS FROM workers");
        $availableColumns = [];
        if ($cols) {
            while ($col = $cols->fetch_assoc()) {
                $availableColumns[] = $col['Field'];
            }
        }
        
        // Detect date column
        $dateColumn = in_array('hire_date', $availableColumns) ? 'hire_date' : 
                     (in_array('created_at', $availableColumns) ? 'created_at' : 'created_at');
        
        // Build WHERE clause properly
        $whereConditions = buildWhereClause($filters, 'workers');
        $whereClause = !empty($whereConditions) ? " AND " . $whereConditions : "";
        
        // Total workers - check all status values (case insensitive)
        $query = "SELECT COUNT(*) as count FROM workers WHERE LOWER(status) IN ('active', 'inactive', 'pending', 'suspended', 'approved', 'rejected', 'deployed', 'returned')" . $whereClause;
        $result = $conn->query($query);
        $total = $result ? (int)$result->fetch_assoc()['count'] : 0;
        
        // Active workers - check for case-insensitive active status
        $query = "SELECT COUNT(*) as count FROM workers WHERE LOWER(status) IN ('active', 'approved', 'deployed')" . $whereClause;
        $result = $conn->query($query);
        $active = $result ? (int)$result->fetch_assoc()['count'] : 0;
        
        // New this month - use detected date column (don't filter by status, only by date range if provided)
        $dateRangeFilter = "";
        if (!empty($filters['dateRange']['start']) && !empty($filters['dateRange']['end'])) {
            $start = $conn->real_escape_string($filters['dateRange']['start']);
            $end = $conn->real_escape_string($filters['dateRange']['end']);
            $dateRangeFilter = " AND {$dateColumn} BETWEEN '{$start}' AND '{$end}'";
        }
        $query = "SELECT COUNT(*) as count FROM workers WHERE {$dateColumn} >= DATE_SUB(NOW(), INTERVAL 30 DAY)" . $dateRangeFilter;
        error_log("Workers New This Month query: " . $query);
        $result = $conn->query($query);
        if (!$result) {
            error_log("Workers New This Month query error: " . $conn->error);
            $newThisMonth = 0;
        } else {
            $newThisMonth = (int)$result->fetch_assoc()['count'];
        }
        error_log("Workers New This Month result: " . $newThisMonth);
        
        $payroll = 0;
        $amountCol = getFinancialAmountColumn($conn);
        if ($amountCol) {
            $dateFilter = buildFinancialDateFilter($conn, $filters);
            $expenseCond = getExpenseTypeCondition();
            $query = "SELECT SUM(COALESCE({$amountCol}, 0)) as total FROM financial_transactions 
                     WHERE entity_type = 'worker' AND {$expenseCond}" . $dateFilter;
            $result = $conn->query($query);
            if ($result) {
                $row = $result->fetch_assoc();
                $payroll = (float)($row['total'] ?? 0);
            }
        }
        if ($payroll == 0) {
            $salaryCol = in_array('salary', $availableColumns) ? 'salary' : (in_array('basic_salary', $availableColumns) ? 'basic_salary' : null);
            if ($salaryCol) {
                $query = "SELECT SUM(COALESCE({$salaryCol}, 0)) as total FROM workers WHERE LOWER(status) IN ('active', 'approved', 'deployed')" . $whereClause;
                $result = $conn->query($query);
                if ($result) {
                    $row = $result->fetch_assoc();
                    $payroll = (float)($row['total'] ?? 0);
                }
            }
            // No fallback estimate: show $0 when accounting/payroll not used
        }

        return [
            ['label' => 'Total Workers', 'value' => $total, 'icon' => 'fas fa-hard-hat', 'color' => '#667eea'],
            ['label' => 'Active Workers', 'value' => $active, 'icon' => 'fas fa-user-check', 'color' => '#4CAF50'],
            ['label' => 'New This Month', 'value' => $newThisMonth, 'icon' => 'fas fa-user-plus', 'color' => '#FF9800'],
            ['label' => 'Payroll', 'value' => '$' . number_format($payroll, 2), 'icon' => 'fas fa-dollar-sign', 'color' => '#2196F3']
        ];
    } catch (Exception $e) {
        return getDefaultStats('Workers');
    }
}

function getCasesStats($filters) {
    global $conn;
    
    try {
        // Check if cases table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'cases'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return [
                ['label' => 'Total Cases', 'value' => 0, 'icon' => 'fas fa-briefcase', 'color' => '#667eea'],
                ['label' => 'Active Cases', 'value' => 0, 'icon' => 'fas fa-briefcase', 'color' => '#4CAF50'],
                ['label' => 'New This Month', 'value' => 0, 'icon' => 'fas fa-plus', 'color' => '#FF9800'],
                ['label' => 'Revenue', 'value' => '$0.00', 'icon' => 'fas fa-dollar-sign', 'color' => '#2196F3']
            ];
        }
        
        // Build WHERE clause properly
        $whereConditions = buildWhereClause($filters, 'cases');
        $whereClause = !empty($whereConditions) ? " WHERE " . $whereConditions : "";
        
        // Total cases
        $query = "SELECT COUNT(*) as count FROM cases" . $whereClause;
        $result = $conn->query($query);
        $total = $result ? (int)$result->fetch_assoc()['count'] : 0;
        
        // Active cases - check for case-insensitive active/open status
        $activeWhere = !empty($whereConditions) ? " AND " . $whereConditions : "";
        $query = "SELECT COUNT(*) as count FROM cases WHERE LOWER(status) IN ('active', 'open', 'in_progress')" . $activeWhere;
        $result = $conn->query($query);
        $active = $result ? (int)$result->fetch_assoc()['count'] : 0;
        
        // New this month - don't filter by status, only by date range if provided
        $dateRangeFilter = "";
        if (!empty($filters['dateRange']['start']) && !empty($filters['dateRange']['end'])) {
            $start = $conn->real_escape_string($filters['dateRange']['start']);
            $end = $conn->real_escape_string($filters['dateRange']['end']);
            $dateRangeFilter = " AND created_at BETWEEN '{$start}' AND '{$end}'";
        }
        $query = "SELECT COUNT(*) as count FROM cases WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" . $dateRangeFilter;
        error_log("Cases New This Month query: " . $query);
        $result = $conn->query($query);
        if (!$result) {
            error_log("Cases New This Month query error: " . $conn->error);
            $newThisMonth = 0;
        } else {
            $newThisMonth = (int)$result->fetch_assoc()['count'];
        }
        error_log("Cases New This Month result: " . $newThisMonth);
        
        $revenue = 0;
        try {
            // Get column names to detect amount column
            $cols = $conn->query("SHOW COLUMNS FROM cases");
            $availableColumns = [];
            if ($cols) {
                while ($col = $cols->fetch_assoc()) {
                    $availableColumns[] = $col['Field'];
                }
            }
            
            $amountColumn = in_array('total_amount', $availableColumns) ? 'total_amount' : 
                           (in_array('amount', $availableColumns) ? 'amount' : null);
            
            if ($amountColumn) {
                $revenueWhere = !empty($whereConditions) ? " AND " . $whereConditions : "";
                $query = "SELECT SUM({$amountColumn}) as total FROM cases WHERE LOWER(status) IN ('completed', 'closed')" . $revenueWhere;
                $result = $conn->query($query);
                if ($result) {
                    $row = $result->fetch_assoc();
                    $revenue = (float)($row['total'] ?? 0);
                }
            }
        } catch (Exception $e) {
            error_log("Cases revenue query error: " . $e->getMessage());
            $revenue = 0;
        }

        return [
            ['label' => 'Total Cases', 'value' => $total, 'icon' => 'fas fa-briefcase', 'color' => '#667eea'],
            ['label' => 'Active Cases', 'value' => $active, 'icon' => 'fas fa-briefcase', 'color' => '#4CAF50'],
            ['label' => 'New This Month', 'value' => $newThisMonth, 'icon' => 'fas fa-plus', 'color' => '#FF9800'],
            ['label' => 'Revenue', 'value' => '$' . number_format($revenue, 2), 'icon' => 'fas fa-dollar-sign', 'color' => '#2196F3']
        ];
    } catch (Exception $e) {
        return getDefaultStats('Cases');
    }
}

function getHRStats($filters) {
    global $conn;
    
    try {
        // Check for both possible table names - employees is the actual table
        $tableName = null;
        $check1 = $conn->query("SHOW TABLES LIKE 'employees'");
        if ($check1 && $check1->num_rows > 0) {
            $tableName = 'employees';
        } else {
            $check2 = $conn->query("SHOW TABLES LIKE 'hr_employees'");
            if ($check2 && $check2->num_rows > 0) {
                $tableName = 'hr_employees';
            }
        }
        
        if (!$tableName) {
            return [
                ['label' => 'Total Employees', 'value' => 0, 'icon' => 'fas fa-users-cog', 'color' => '#667eea'],
                ['label' => 'Active Employees', 'value' => 0, 'icon' => 'fas fa-user-check', 'color' => '#4CAF50'],
                ['label' => 'New This Month', 'value' => 0, 'icon' => 'fas fa-user-plus', 'color' => '#FF9800'],
                ['label' => 'Payroll', 'value' => '$0.00', 'icon' => 'fas fa-dollar-sign', 'color' => '#2196F3']
            ];
        }
        
        // Get column names to detect correct column names
        $cols = $conn->query("SHOW COLUMNS FROM {$tableName}");
        $availableColumns = [];
        if ($cols) {
            while ($col = $cols->fetch_assoc()) {
                $availableColumns[] = $col['Field'];
            }
        }
        
        // Detect date column
        $dateColumn = in_array('join_date', $availableColumns) ? 'join_date' : 
                     (in_array('hire_date', $availableColumns) ? 'hire_date' : 'created_at');
        
        // Detect salary column
        $salaryColumn = in_array('basic_salary', $availableColumns) ? 'basic_salary' : 
                       (in_array('salary', $availableColumns) ? 'salary' : null);
        
        // Build WHERE clause
        $whereConditions = buildWhereClause($filters, 'hr_employees');
        $whereClause = !empty($whereConditions) ? " WHERE " . $whereConditions : "";
        
        // Total employees
        $query = "SELECT COUNT(*) as count FROM {$tableName}" . $whereClause;
        $result = $conn->query($query);
        $total = $result ? (int)$result->fetch_assoc()['count'] : 0;
        
        // Active employees - check for case-insensitive active status
        $activeWhere = !empty($whereConditions) ? " AND " . $whereConditions : "";
        $query = "SELECT COUNT(*) as count FROM {$tableName} WHERE LOWER(status) IN ('active', 'actif')" . $activeWhere;
        $result = $conn->query($query);
        $active = $result ? (int)$result->fetch_assoc()['count'] : 0;
        
        // New this month - use detected date column (don't filter by status, only by date range if provided)
        $dateRangeFilter = "";
        if (!empty($filters['dateRange']['start']) && !empty($filters['dateRange']['end'])) {
            $start = $conn->real_escape_string($filters['dateRange']['start']);
            $end = $conn->real_escape_string($filters['dateRange']['end']);
            $dateRangeFilter = " AND {$dateColumn} BETWEEN '{$start}' AND '{$end}'";
        }
        $query = "SELECT COUNT(*) as count FROM {$tableName} WHERE {$dateColumn} >= DATE_SUB(NOW(), INTERVAL 30 DAY)" . $dateRangeFilter;
        error_log("HR New This Month query: " . $query);
        $result = $conn->query($query);
        if (!$result) {
            error_log("HR New This Month query error: " . $conn->error);
            $newThisMonth = 0;
        } else {
            $newThisMonth = (int)$result->fetch_assoc()['count'];
        }
        error_log("HR New This Month result: " . $newThisMonth);
        
        // Payroll - try salaries table (actual paid) first, else employees basic_salary
        $payroll = 0;
        $salariesCheck = $conn->query("SHOW TABLES LIKE 'salaries'");
        if ($salariesCheck && $salariesCheck->num_rows > 0) {
            $sCols = $conn->query("SHOW COLUMNS FROM salaries");
            $sFields = [];
            if ($sCols) { while ($c = $sCols->fetch_assoc()) $sFields[] = $c['Field']; }
            if (in_array('net_salary', $sFields)) {
                $currMonth = date('Y-m');
                $query = "SELECT SUM(COALESCE(net_salary, 0)) as total FROM salaries WHERE salary_month = '{$currMonth}'";
                $result = $conn->query($query);
                if ($result) {
                    $row = $result->fetch_assoc();
                    $payroll = (float)($row['total'] ?? 0);
                }
                if ($payroll == 0) {
                    $query = "SELECT SUM(COALESCE(net_salary, 0)) as total FROM salaries WHERE salary_month >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 3 MONTH), '%Y-%m')";
                    $result = $conn->query($query);
                    if ($result) {
                        $row = $result->fetch_assoc();
                        $payroll = (float)($row['total'] ?? 0);
                    }
                }
            }
        }
        // Payroll = salaries table only. No fallback to employees.basic_salary so cleared = $0

        return [
            ['label' => 'Total Employees', 'value' => $total, 'icon' => 'fas fa-users-cog', 'color' => '#667eea'],
            ['label' => 'Active Employees', 'value' => $active, 'icon' => 'fas fa-user-check', 'color' => '#4CAF50'],
            ['label' => 'New This Month', 'value' => $newThisMonth, 'icon' => 'fas fa-user-plus', 'color' => '#FF9800'],
            ['label' => 'Payroll', 'value' => '$' . number_format($payroll, 2), 'icon' => 'fas fa-dollar-sign', 'color' => '#2196F3']
        ];
    } catch (Exception $e) {
        error_log("Error getting HR stats: " . $e->getMessage());
        return getDefaultStats('HR');
    }
}

function getFinancialStats($filters) {
    global $conn;
    
    try {
        // Check if table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return [
                ['label' => 'Total Transactions', 'value' => 0, 'icon' => 'fas fa-exchange-alt', 'color' => '#667eea'],
                ['label' => 'Total Revenue', 'value' => '$0.00', 'icon' => 'fas fa-arrow-up', 'color' => '#4CAF50'],
                ['label' => 'Total Expenses', 'value' => '$0.00', 'icon' => 'fas fa-arrow-down', 'color' => '#f44336'],
                ['label' => 'Net Profit', 'value' => '$0.00', 'icon' => 'fas fa-dollar-sign', 'color' => '#4CAF50']
            ];
        }
        
        // Get column names to detect correct amount column
        $cols = $conn->query("SHOW COLUMNS FROM financial_transactions");
        $availableColumns = [];
        if ($cols) {
            while ($col = $cols->fetch_assoc()) {
                $availableColumns[] = $col['Field'];
            }
        }
        
        // Detect amount column - use total_amount if available, otherwise amount
        $amountColumn = in_array('total_amount', $availableColumns) ? 'total_amount' : 
                       (in_array('amount', $availableColumns) ? 'amount' : null);
        
        if (!$amountColumn) {
            return [
                ['label' => 'Total Transactions', 'value' => 0, 'icon' => 'fas fa-exchange-alt', 'color' => '#667eea'],
                ['label' => 'Total Revenue', 'value' => '$0.00', 'icon' => 'fas fa-arrow-up', 'color' => '#4CAF50'],
                ['label' => 'Total Expenses', 'value' => '$0.00', 'icon' => 'fas fa-arrow-down', 'color' => '#f44336'],
                ['label' => 'Net Profit', 'value' => '$0.00', 'icon' => 'fas fa-dollar-sign', 'color' => '#4CAF50']
            ];
        }
        
        // Build WHERE clause
        $whereConditions = buildWhereClause($filters, 'financial_transactions');
        $whereClause = !empty($whereConditions) ? " WHERE " . $whereConditions : "";
        
        // Total transactions
        $query = "SELECT COUNT(*) as count FROM financial_transactions" . $whereClause;
        $result = $conn->query($query);
        $totalTransactions = $result ? (int)$result->fetch_assoc()['count'] : 0;
        
        // Total Revenue - check for both 'Income' and 'income' transaction types
        $revenueWhere = " WHERE transaction_type IN ('Income', 'income', 'INCOME')";
        $revenueFilter = !empty($whereConditions) ? " AND " . $whereConditions : "";
        $query = "SELECT SUM({$amountColumn}) as total FROM financial_transactions" . $revenueWhere . $revenueFilter;
        $result = $conn->query($query);
        $totalRevenue = $result ? (float)($result->fetch_assoc()['total'] ?? 0) : 0;
        
        // Total Expenses - check for both 'Expense' and 'expense' transaction types
        $expenseWhere = " WHERE transaction_type IN ('Expense', 'expense', 'EXPENSE')";
        $expenseFilter = !empty($whereConditions) ? " AND " . $whereConditions : "";
        $query = "SELECT SUM({$amountColumn}) as total FROM financial_transactions" . $expenseWhere . $expenseFilter;
        $result = $conn->query($query);
        $totalExpenses = $result ? (float)($result->fetch_assoc()['total'] ?? 0) : 0;
        
        $netProfit = $totalRevenue - $totalExpenses;

        return [
            ['label' => 'Total Transactions', 'value' => $totalTransactions, 'icon' => 'fas fa-exchange-alt', 'color' => '#667eea'],
            ['label' => 'Total Revenue', 'value' => '$' . number_format($totalRevenue, 2), 'icon' => 'fas fa-arrow-up', 'color' => '#4CAF50'],
            ['label' => 'Total Expenses', 'value' => '$' . number_format($totalExpenses, 2), 'icon' => 'fas fa-arrow-down', 'color' => '#f44336'],
            ['label' => 'Net Profit', 'value' => '$' . number_format($netProfit, 2), 'icon' => 'fas fa-dollar-sign', 'color' => $netProfit >= 0 ? '#4CAF50' : '#f44336']
        ];
    } catch (Exception $e) {
        error_log("Error getting Financial stats: " . $e->getMessage());
        return getDefaultStats('Financial');
    }
}

function getDefaultStats($category) {
    return [
        ['label' => "Total $category", 'value' => 0, 'icon' => 'fas fa-chart-bar', 'color' => '#667eea'],
        ['label' => "Active $category", 'value' => 0, 'icon' => 'fas fa-check', 'color' => '#4CAF50'],
        ['label' => 'New This Month', 'value' => 0, 'icon' => 'fas fa-plus', 'color' => '#FF9800'],
        ['label' => 'Revenue', 'value' => '$0.00', 'icon' => 'fas fa-dollar-sign', 'color' => '#2196F3']
    ];
}

function getCategoryCharts($category, $filters) {
    switch ($category) {
        case 'agents':
            return getAgentsCharts($filters);
        case 'subagents':
            return getSubAgentsCharts($filters);
        case 'workers':
            return getWorkersCharts($filters);
        case 'cases':
            return getCasesCharts($filters);
        case 'hr':
            return getHRCharts($filters);
        case 'financial':
            return getFinancialCharts($filters);
        default:
            return ['performance' => [], 'revenue' => []];
    }
}

function getAgentsCharts($filters) {
    global $conn;
    
    try {
        // Performance chart data (last 6 months)
        $query = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count
            FROM agents 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ";
        $result = $conn->query($query);
        
        $performanceLabels = [];
        $performanceValues = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $performanceLabels[] = date('M', strtotime($row['month'] . '-01'));
                $performanceValues[] = (int)$row['count'];
            }
        }
        
        // Revenue chart data
        $revenueLabels = [];
        $revenueValues = [];
        $amountCol = getFinancialAmountColumn($conn);
        $dateCol = $amountCol ? getFinancialDateColumn($conn) : 'created_at';
        if ($amountCol) {
            $incomeCond = getIncomeTypeCondition();
            $query = "
                SELECT 
                    DATE_FORMAT({$dateCol}, '%Y-%m') as month,
                    SUM(COALESCE({$amountCol}, 0)) as total
                FROM financial_transactions
                WHERE entity_type = 'agent' AND {$incomeCond}
                AND {$dateCol} >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT({$dateCol}, '%Y-%m')
                ORDER BY month
            ";
            $result = @$conn->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $revenueLabels[] = date('M', strtotime($row['month'] . '-01'));
                    $revenueValues[] = (float)($row['total'] ?? 0);
                }
            }
        }
        if (empty($revenueLabels)) {
            $revenueLabels = $performanceLabels;
            $revenueValues = array_fill(0, count($revenueLabels), 0);
        }

        return [
            'performance' => [
                'type' => 'line',
                'data' => [
                    'labels' => $performanceLabels,
                    'datasets' => [[
                        'label' => 'Agent Performance',
                        'data' => $performanceValues,
                        'borderColor' => '#667eea',
                        'backgroundColor' => 'rgba(102, 126, 234, 0.1)',
                        'tension' => 0.4
                    ]]
                ]
            ],
            'revenue' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $revenueLabels,
                    'datasets' => [[
                        'label' => 'Revenue',
                        'data' => $revenueValues,
                        'backgroundColor' => 'rgba(33, 150, 243, 0.8)',
                        'borderColor' => '#2196F3'
                    ]]
                ]
            ]
        ];
    } catch (Exception $e) {
        return getDefaultCharts();
    }
}

function getSubAgentsCharts($filters) {
    return getAgentsCharts($filters); // Similar structure
}

function getWorkersCharts($filters) {
    return getAgentsCharts($filters); // Similar structure
}

function getCasesCharts($filters) {
    return getAgentsCharts($filters); // Similar structure
}

function getHRCharts($filters) {
    return getAgentsCharts($filters); // Similar structure
}

function getFinancialCharts($filters) {
    global $conn;
    
    try {
        $amountCol = getFinancialAmountColumn($conn);
        $dateCol = getFinancialDateColumn($conn);
        if (!$amountCol) return getDefaultCharts();
        $query = "
            SELECT 
                DATE_FORMAT({$dateCol}, '%Y-%m') as month,
                SUM(CASE WHEN LOWER(transaction_type) = 'income' THEN COALESCE({$amountCol}, 0) ELSE 0 END) as revenue,
                SUM(CASE WHEN LOWER(transaction_type) = 'expense' THEN COALESCE({$amountCol}, 0) ELSE 0 END) as expenses
            FROM financial_transactions
            WHERE {$dateCol} >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT({$dateCol}, '%Y-%m')
            ORDER BY month
        ";
        $result = $conn->query($query);
        
        $labels = [];
        $revenueValues = [];
        $expenseValues = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $labels[] = date('M', strtotime($row['month'] . '-01'));
                $revenueValues[] = (float)($row['revenue'] ?? 0);
                $expenseValues[] = (float)($row['expenses'] ?? 0);
            }
        }
        
        return [
            'performance' => [
                'type' => 'line',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Revenue',
                            'data' => $revenueValues,
                            'borderColor' => '#4CAF50',
                            'backgroundColor' => 'rgba(76, 175, 80, 0.1)',
                            'tension' => 0.4
                        ],
                        [
                            'label' => 'Expenses',
                            'data' => $expenseValues,
                            'borderColor' => '#f44336',
                            'backgroundColor' => 'rgba(244, 67, 54, 0.1)',
                            'tension' => 0.4
                        ]
                    ]
                ]
            ],
            'revenue' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [
                        [
                            'label' => 'Revenue',
                            'data' => $revenueValues,
                            'backgroundColor' => 'rgba(76, 175, 80, 0.8)',
                            'borderColor' => '#4CAF50'
                        ],
                        [
                            'label' => 'Expenses',
                            'data' => $expenseValues,
                            'backgroundColor' => 'rgba(244, 67, 54, 0.8)',
                            'borderColor' => '#f44336'
                        ]
                    ]
                ]
            ]
        ];
    } catch (Exception $e) {
        return getDefaultCharts();
    }
}

function getDefaultCharts() {
    $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
    return [
        'performance' => [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Performance',
                    'data' => array_fill(0, 6, 0),
                    'borderColor' => '#667eea',
                    'backgroundColor' => 'rgba(102, 126, 234, 0.1)',
                    'tension' => 0.4
                ]]
            ]
        ],
        'revenue' => [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Revenue',
                    'data' => array_fill(0, 6, 0),
                    'backgroundColor' => 'rgba(33, 150, 243, 0.8)',
                    'borderColor' => '#2196F3'
                ]]
            ]
        ]
    ];
}

function getCategoryTableData($category, $filters) {
    switch ($category) {
        case 'agents':
            return getAgentsTableData($filters);
        case 'subagents':
            return getSubAgentsTableData($filters);
        case 'workers':
            return getWorkersTableData($filters);
        case 'cases':
            return getCasesTableData($filters);
        case 'hr':
            return getHRTableData($filters);
        case 'financial':
            return getFinancialTableData($filters);
        default:
            return [];
    }
}

function getAgentsTableData($filters) {
    global $conn;
    
    error_log("=== getAgentsTableData DEBUG START ===");
    error_log("Filters received: " . json_encode($filters));
    
    try {
        // First, check if table exists and has data
        $tableCheck = $conn->query("SELECT COUNT(*) as total FROM agents");
        if ($tableCheck) {
            $countRow = $tableCheck->fetch_assoc();
            $totalInTable = (int)($countRow['total'] ?? 0);
            error_log("Total agents in table (before filters): " . $totalInTable);
            
            // If table is empty, return early
            if ($totalInTable === 0) {
                error_log("Table is empty - returning empty array");
                return [];
            }
        } else {
            error_log("ERROR: Could not query agents table: " . $conn->error);
            return [];
        }
        
        // Test: Try the absolute simplest query possible
        $simpleTest = $conn->query("SELECT * FROM agents LIMIT 1");
        if ($simpleTest && $simpleTest->num_rows > 0) {
            $testRow = $simpleTest->fetch_assoc();
            error_log("Simple test query SUCCESS - table has data");
            error_log("Sample row keys: " . implode(", ", array_keys($testRow)));
        } else {
            error_log("ERROR: Simple test query failed or returned 0 rows");
            error_log("MySQL error: " . ($conn->error ?: "None"));
            return [];
        }
        
        // Get all column names
        $columnsResult = $conn->query("SHOW COLUMNS FROM agents");
        $availableColumns = [];
        if ($columnsResult) {
            while ($col = $columnsResult->fetch_assoc()) {
                $availableColumns[] = $col['Field'];
            }
            error_log("Available columns: " . implode(", ", $availableColumns));
        }
        
        // Detect which name column exists
        $nameColumn = null;
        $nameColumnsToTry = ['agent_name', 'name', 'full_name', 'formatted_id'];
        foreach ($nameColumnsToTry as $col) {
            if (in_array($col, $availableColumns)) {
                $nameColumn = $col;
                break;
            }
        }
        
        // If no name column found, use a fallback expression
        if (!$nameColumn) {
            $nameExpression = "CONCAT('Agent ', id)";
            error_log("No name column found, using fallback: CONCAT('Agent ', id)");
        } else {
            $nameExpression = $nameColumn;
            error_log("Using name column: " . $nameColumn);
        }
        
        $whereConditions = buildWhereClause($filters, 'agents');
        
        error_log("WHERE conditions: " . ($whereConditions ?: "NONE"));
        
        // Build ORDER BY clause - fix column references for aliases
        $sortBy = $filters['sortBy'] ?? 'date';
        $orderByClause = '';
        if ($sortBy === 'name') {
            // Use the actual column expression, not the alias (MySQL can have issues with aliases in ORDER BY)
            $orderByClause = "ORDER BY {$nameExpression} DESC";
        } else {
            // For date sorting, use the actual column name (created_at)
            $orderByClause = "ORDER BY created_at DESC";
        }
        
        // Build actual query - start simple and add complexity
        $query = "SELECT 
                    id,
                    {$nameExpression} as name,
                    COALESCE(status, 'unknown') as status,
                    COALESCE(created_at, NOW()) as joinDate
                  FROM agents";
        
        // Add WHERE clause if needed
        if (!empty($whereConditions)) {
            $query .= " WHERE " . $whereConditions;
        }
        
        // Add ORDER BY clause
        $query .= " " . $orderByClause . " LIMIT 50";
        
        error_log("Final query: " . $query);
        
        $result = $conn->query($query);
        $data = [];
        
        if (!$result) {
            $error = $conn->error;
            error_log("Query failed: " . $error);
            error_log("MySQL error code: " . $conn->errno);
            
            // Try query without ORDER BY (ORDER BY might be the issue)
            $queryNoOrder = "SELECT id, {$nameExpression} as name, status, created_at as joinDate FROM agents";
            if (!empty($whereConditions)) {
                $queryNoOrder .= " WHERE " . $whereConditions;
            }
            $queryNoOrder .= " LIMIT 50";
            error_log("Trying query without ORDER BY: " . $queryNoOrder);
            $resultNoOrder = $conn->query($queryNoOrder);
            if ($resultNoOrder && $resultNoOrder->num_rows > 0) {
                error_log("Query without ORDER BY worked - returned " . $resultNoOrder->num_rows . " rows");
                $result = $resultNoOrder;
            } else {
                // Try query without WHERE clause (WHERE might be filtering everything)
                $queryNoWhere = "SELECT id, {$nameExpression} as name, status, created_at as joinDate FROM agents";
                $queryNoWhere .= " " . $orderByClause . " LIMIT 50";
                error_log("Trying query without WHERE: " . $queryNoWhere);
                $resultNoWhere = $conn->query($queryNoWhere);
                if ($resultNoWhere && $resultNoWhere->num_rows > 0) {
                    error_log("Query without WHERE worked - returned " . $resultNoWhere->num_rows . " rows");
                    $result = $resultNoWhere;
                } else {
                    // Try simplest possible query
                    $query2 = "SELECT id, {$nameExpression} as name, status, created_at as joinDate FROM agents LIMIT 50";
                    error_log("Trying fallback query: " . $query2);
                    $result2 = $conn->query($query2);
                    if ($result2 && $result2->num_rows > 0) {
                        error_log("Fallback query worked - returned " . $result2->num_rows . " rows");
                        $result = $result2;
                    } else {
                        error_log("Fallback query also failed: " . ($conn->error ?: "No error but 0 rows"));
                        // Check if table has data
                        $countQuery = "SELECT COUNT(*) as total FROM agents";
                        $countResult = $conn->query($countQuery);
                        if ($countResult) {
                            $countRow = $countResult->fetch_assoc();
                            $total = (int)($countRow['total'] ?? 0);
                            error_log("Total agents in table: " . $total);
                            if ($total === 0) {
                                error_log("Table is empty - returning empty array");
                                return [];
                            } else {
                                error_log("WARNING: Table has {$total} agents but query returned 0 rows!");
                                // Try absolute simplest query
                                $simplestQuery = "SELECT id, id as name, 'unknown' as status, NOW() as joinDate FROM agents LIMIT 50";
                                error_log("Trying absolute simplest query: " . $simplestQuery);
                                $simplestResult = $conn->query($simplestQuery);
                                if ($simplestResult && $simplestResult->num_rows > 0) {
                                    error_log("Simplest query worked - returned " . $simplestResult->num_rows . " rows");
                                    $result = $simplestResult;
                                } else {
                                    return [];
                                }
                            }
                        } else {
                            return [];
                        }
                    }
                }
            }
        }
        
        error_log("Query returned " . ($result ? $result->num_rows : 0) . " rows");
        
        if (!$result) {
            error_log("ERROR: Result is null/false - query failed");
            return [];
        }
        
        if ($result->num_rows === 0) {
            error_log("WARNING: Query succeeded but returned 0 rows");
            error_log("This might mean WHERE clause filtered everything out");
            // Try one more time with absolutely no filters
            $finalTestQuery = "SELECT id, {$nameExpression} as name, status, created_at as joinDate FROM agents LIMIT 50";
            error_log("Final test query (no filters at all): " . $finalTestQuery);
            $finalTestResult = $conn->query($finalTestQuery);
            if ($finalTestResult && $finalTestResult->num_rows > 0) {
                error_log("Final test query worked! Using this result instead.");
                $result = $finalTestResult;
            } else {
                error_log("Final test query also returned 0 rows or failed");
                return [];
            }
        }
        
        error_log("About to process " . $result->num_rows . " rows");
        $rowCount = 0;
        
        // Reset result pointer to beginning
        $result->data_seek(0);
        
        while ($row = $result->fetch_assoc()) {
            $rowCount++;
            if ($rowCount <= 2) {
                error_log("Processing row #{$rowCount}: " . json_encode($row));
            }
            
            // Ensure we have valid data before processing
            if (!isset($row['id'])) {
                error_log("WARNING: Row missing id field, skipping: " . json_encode($row));
                continue;
            }
            
            // Calculate revenue from financial transactions
            $revenue = 0;
            try {
                // Check if financial_transactions table exists first
                $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
                if ($tableCheck && $tableCheck->num_rows > 0) {
                    // Check what columns exist
                    $colsCheck = $conn->query("SHOW COLUMNS FROM financial_transactions");
                    $hasEntityType = false;
                    $hasEntityId = false;
                    $amountCol = null;
                    if ($colsCheck) {
                        while ($col = $colsCheck->fetch_assoc()) {
                            if ($col['Field'] === 'entity_type') $hasEntityType = true;
                            if ($col['Field'] === 'entity_id') $hasEntityId = true;
                            if (in_array($col['Field'], ['total_amount', 'amount']) && !$amountCol) {
                                $amountCol = $col['Field'];
                            }
                        }
                    }
                    
                    if ($hasEntityType && $hasEntityId && $amountCol) {
                        $incomeCond = getIncomeTypeCondition();
                        $revenueQuery = "SELECT SUM(COALESCE({$amountCol}, 0)) as total 
                                        FROM financial_transactions 
                                        WHERE entity_type = 'agent' AND entity_id = " . (int)$row['id'] . " AND {$incomeCond}";
                        $revenueResult = $conn->query($revenueQuery);
                        if ($revenueResult) {
                            $revenueRow = $revenueResult->fetch_assoc();
                            $revenue = (float)($revenueRow['total'] ?? 0);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Revenue query error for agent " . $row['id'] . ": " . $e->getMessage());
                $revenue = 0;
            }
            
            // Calculate performance based on status
            $status = strtolower($row['status'] ?? 'unknown');
            $performance = '0%';
            if ($status === 'active') {
                $performance = '100%';
            } else if ($status === 'inactive') {
                $performance = '0%';
            } else {
                $performance = '50%';
            }
            
            $dataRow = [
                'id' => $row['id'],
                'name' => $row['name'] ?? 'N/A',
                'status' => $row['status'] ?? 'unknown',
                'revenue' => '$' . number_format($revenue, 2),
                'performance' => $performance,
                'joinDate' => date('Y-m-d', strtotime($row['joinDate'] ?? 'now'))
            ];
            
            if ($rowCount <= 2) {
                error_log("Formatted data row #{$rowCount}: " . json_encode($dataRow));
            }
            
            $data[] = $dataRow;
        }
        
        error_log("=== getAgentsTableData DEBUG END ===");
        error_log("Returning " . count($data) . " agents");
        if (count($data) > 0) {
            error_log("First agent data: " . json_encode($data[0]));
        }
        
        return $data;
    } catch (Exception $e) {
        error_log("Error getting agents table data: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return [];
    }
}

function getSubAgentsTableData($filters) {
    global $conn;
    
    error_log("=== getSubAgentsTableData DEBUG START ===");
    error_log("Filters received: " . json_encode($filters));
    
    try {
        // Check if subagents table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'subagents'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            error_log("Subagents table does not exist");
            return [];
        }
        
        // Get column names
        $columnsResult = $conn->query("SHOW COLUMNS FROM subagents");
        $availableColumns = [];
        if ($columnsResult) {
            while ($col = $columnsResult->fetch_assoc()) {
                $availableColumns[] = $col['Field'];
            }
            error_log("Available columns in subagents: " . implode(", ", $availableColumns));
        }
        
        // Detect name column
        $nameColumn = null;
        $nameColumnsToTry = ['subagent_name', 'name', 'full_name'];
        foreach ($nameColumnsToTry as $col) {
            if (in_array($col, $availableColumns)) {
                $nameColumn = $col;
                break;
            }
        }
        $nameExpression = $nameColumn ?: "CONCAT('SubAgent ', id)";
        
        $whereConditions = buildWhereClause($filters, 'subagents');
        $orderBy = getOrderBy($filters, 'subagents');
        
        // Build query with proper WHERE clause
        $query = "SELECT 
                    id,
                    {$nameExpression} as name,
                    COALESCE(status, 'unknown') as status,
                    COALESCE(created_at, NOW()) as joinDate
                  FROM subagents";
        
        if (!empty($whereConditions)) {
            $query .= " WHERE " . $whereConditions;
        }
        
        $query .= " " . $orderBy . " LIMIT 50";
        
        error_log("SubAgents query: " . $query);
        
        $result = $conn->query($query);
        $data = [];
        
        if (!$result) {
            error_log("SubAgents query failed: " . $conn->error);
            error_log("MySQL error code: " . $conn->errno);
            return [];
        }
        
        error_log("SubAgents query returned " . $result->num_rows . " rows");
        
        if ($result->num_rows === 0) {
            error_log("No subagents found in database");
            return [];
        }
        
        while ($row = $result->fetch_assoc()) {
            // Calculate revenue from financial transactions
            $revenue = 0;
            try {
                // Check if financial_transactions table exists first
                $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
                if ($tableCheck && $tableCheck->num_rows > 0) {
                    // Check what columns exist
                    $colsCheck = $conn->query("SHOW COLUMNS FROM financial_transactions");
                    $hasEntityType = false;
                    $hasEntityId = false;
                    $amountCol = null;
                    if ($colsCheck) {
                        while ($col = $colsCheck->fetch_assoc()) {
                            if ($col['Field'] === 'entity_type') $hasEntityType = true;
                            if ($col['Field'] === 'entity_id') $hasEntityId = true;
                            if (in_array($col['Field'], ['total_amount', 'amount']) && !$amountCol) {
                                $amountCol = $col['Field'];
                            }
                        }
                    }
                    
                    if ($hasEntityType && $hasEntityId && $amountCol) {
                        $incomeCond = getIncomeTypeCondition();
                        $revenueQuery = "SELECT SUM(COALESCE({$amountCol}, 0)) as total 
                                        FROM financial_transactions 
                                        WHERE entity_type = 'subagent' AND entity_id = " . (int)$row['id'] . " AND {$incomeCond}";
                        $revenueResult = $conn->query($revenueQuery);
                        if ($revenueResult) {
                            $revenueRow = $revenueResult->fetch_assoc();
                            $revenue = (float)($revenueRow['total'] ?? 0);
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Revenue query error for subagent " . $row['id'] . ": " . $e->getMessage());
                $revenue = 0;
            }
            
            // Calculate performance based on status
            $status = strtolower($row['status'] ?? 'unknown');
            $performance = '0%';
            if ($status === 'active') {
                $performance = '100%';
            } else if ($status === 'inactive') {
                $performance = '0%';
            } else {
                $performance = '50%';
            }
            
            $data[] = [
                'id' => $row['id'],
                'name' => $row['name'] ?? 'N/A',
                'status' => $row['status'] ?? 'unknown',
                'revenue' => '$' . number_format($revenue, 2),
                'performance' => $performance,
                'joinDate' => date('Y-m-d', strtotime($row['joinDate'] ?? 'now'))
            ];
        }
        
        error_log("Returning " . count($data) . " subagents");
        if (count($data) > 0) {
            error_log("First subagent: " . json_encode($data[0]));
        }
        return $data;
    } catch (Exception $e) {
        error_log("Error getting subagents table data: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return [];
    }
}

function getWorkersTableData($filters) {
    global $conn;
    
    error_log("=== getWorkersTableData DEBUG START ===");
    error_log("Filters received: " . json_encode($filters));
    
    try {
        // Check if workers table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'workers'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            error_log("Workers table does not exist");
            return [];
        }
        
        // Get column names
        $columnsResult = $conn->query("SHOW COLUMNS FROM workers");
        $availableColumns = [];
        if ($columnsResult) {
            while ($col = $columnsResult->fetch_assoc()) {
                $availableColumns[] = $col['Field'];
            }
            error_log("Available columns in workers: " . implode(", ", $availableColumns));
        }
        
        // Detect name column
        $nameColumn = null;
        $nameColumnsToTry = ['worker_name', 'name', 'full_name'];
        foreach ($nameColumnsToTry as $col) {
            if (in_array($col, $availableColumns)) {
                $nameColumn = $col;
                break;
            }
        }
        $nameExpression = $nameColumn ?: "CONCAT('Worker ', id)";
        
        // Detect date column
        $dateColumn = in_array('hire_date', $availableColumns) ? 'hire_date' : 
                     (in_array('created_at', $availableColumns) ? 'created_at' : 'NOW()');
        
        // Detect salary column
        $salaryColumn = in_array('salary', $availableColumns) ? 'salary' : 
                       (in_array('basic_salary', $availableColumns) ? 'basic_salary' : null);
        $salaryExpression = $salaryColumn ? "COALESCE({$salaryColumn}, 0)" : "0";
        
        $whereConditions = buildWhereClause($filters, 'workers');
        
        // Build ORDER BY clause directly using detected columns
        $sortBy = $filters['sortBy'] ?? 'date';
        $orderByClause = '';
        if ($sortBy === 'name') {
            $orderByClause = "ORDER BY {$nameExpression} DESC";
        } else {
            $orderByClause = "ORDER BY {$dateColumn} DESC";
        }
        
        // Build query with proper WHERE clause - include salary
        $query = "SELECT 
                    id,
                    {$nameExpression} as name,
                    COALESCE(status, 'unknown') as status,
                    {$salaryExpression} as salary,
                    COALESCE({$dateColumn}, NOW()) as joinDate
                  FROM workers";
        
        if (!empty($whereConditions)) {
            $query .= " WHERE " . $whereConditions;
        }
        
        $query .= " " . $orderByClause . " LIMIT 50";
        
        error_log("Workers query: " . $query);
        
        $result = $conn->query($query);
        $data = [];
        
        if (!$result) {
            error_log("Workers query failed: " . $conn->error);
            error_log("MySQL error code: " . $conn->errno);
            return [];
        }
        
        error_log("Workers query returned " . $result->num_rows . " rows");
        
        if ($result->num_rows === 0) {
            error_log("No workers found in database");
            return [];
        }
        
        while ($row = $result->fetch_assoc()) {
            $salary = (float)($row['salary'] ?? 0);
            $status = strtolower($row['status'] ?? 'unknown');
            
            // Calculate performance based on status
            $performance = '0%';
            if ($status === 'approved' || $status === 'active' || $status === 'deployed') {
                $performance = '100%';
            } else if ($status === 'pending') {
                $performance = '50%';
            } else if ($status === 'rejected' || $status === 'inactive') {
                $performance = '0%';
            }
            
            $data[] = [
                'id' => $row['id'],
                'name' => $row['name'] ?? 'N/A',
                'status' => $row['status'] ?? 'unknown',
                'revenue' => $salary > 0 ? '$' . number_format($salary, 2) : '$0.00',
                'performance' => $performance,
                'joinDate' => date('Y-m-d', strtotime($row['joinDate'] ?? 'now'))
            ];
        }
        
        error_log("Returning " . count($data) . " workers");
        if (count($data) > 0) {
            error_log("First worker: " . json_encode($data[0]));
        }
        return $data;
    } catch (Exception $e) {
        error_log("Error getting workers table data: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return [];
    }
}

function getCasesTableData($filters) {
    global $conn;
    
    error_log("=== getCasesTableData DEBUG START ===");
    error_log("Filters received: " . json_encode($filters));
    
    try {
        // Check if cases table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'cases'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            error_log("Cases table does not exist");
            return [];
        }
        
        // Get column names
        $columnsResult = $conn->query("SHOW COLUMNS FROM cases");
        $availableColumns = [];
        if ($columnsResult) {
            while ($col = $columnsResult->fetch_assoc()) {
                $availableColumns[] = $col['Field'];
            }
            error_log("Available columns in cases: " . implode(", ", $availableColumns));
        }
        
        // Build revenue expression based on available columns
        $revenueExpression = "0";
        if (in_array('total_amount', $availableColumns)) {
            $revenueExpression = "COALESCE(total_amount, 0)";
        } else if (in_array('amount', $availableColumns)) {
            $revenueExpression = "COALESCE(amount, 0)";
        }
        
        $whereConditions = buildWhereClause($filters, 'cases');
        $orderBy = getOrderBy($filters, 'cases');
        
        // Build query with proper WHERE clause
        $query = "SELECT 
                    id,
                    COALESCE(case_number, CONCAT('CASE-', id)) as name,
                    COALESCE(status, 'unknown') as status,
                    {$revenueExpression} as revenue,
                    COALESCE(created_at, NOW()) as joinDate
                  FROM cases";
        
        if (!empty($whereConditions)) {
            $query .= " WHERE " . $whereConditions;
        }
        
        $query .= " " . $orderBy . " LIMIT 50";
        
        error_log("Cases query: " . $query);
        
        $result = $conn->query($query);
        $data = [];
        
        if (!$result) {
            error_log("Cases query failed: " . $conn->error);
            error_log("MySQL error code: " . $conn->errno);
            return [];
        }
        
        error_log("Cases query returned " . $result->num_rows . " rows");
        
        if ($result->num_rows === 0) {
            error_log("No cases found in database");
            return [];
        }
        
        while ($row = $result->fetch_assoc()) {
            // Calculate progress based on status
            $status = strtolower($row['status'] ?? 'unknown');
            $progress = '0%';
            if ($status === 'closed' || $status === 'completed') {
                $progress = '100%';
            } else if ($status === 'in_progress' || $status === 'open') {
                $progress = '50%';
            } else {
                $progress = '0%';
            }
            
            $data[] = [
                'id' => $row['id'],
                'name' => $row['name'] ?? 'N/A',
                'status' => $row['status'] ?? 'unknown',
                'revenue' => '$' . number_format((float)($row['revenue'] ?? 0), 2),
                'performance' => $progress,
                'joinDate' => date('Y-m-d', strtotime($row['joinDate'] ?? 'now'))
            ];
        }
        
        error_log("Returning " . count($data) . " cases");
        if (count($data) > 0) {
            error_log("First case: " . json_encode($data[0]));
        }
        return $data;
    } catch (Exception $e) {
        error_log("Error getting cases table data: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return [];
    }
}

function getHRTableData($filters) {
    global $conn;
    
    error_log("=== getHRTableData DEBUG START ===");
    error_log("Filters received: " . json_encode($filters));
    
    try {
        // Check for both possible table names - employees is the actual table used by HR
        $tableName = null;
        $check1 = $conn->query("SHOW TABLES LIKE 'employees'");
        if ($check1 && $check1->num_rows > 0) {
            $tableName = 'employees';
            error_log("Using 'employees' table for HR");
        } else {
            $check2 = $conn->query("SHOW TABLES LIKE 'hr_employees'");
            if ($check2 && $check2->num_rows > 0) {
                $tableName = 'hr_employees';
                error_log("Using 'hr_employees' table for HR");
            } else {
                error_log("Neither employees nor hr_employees table exists");
                return [];
            }
        }
        
        // Get column names
        $columnsResult = $conn->query("SHOW COLUMNS FROM {$tableName}");
        $availableColumns = [];
        if ($columnsResult) {
            while ($col = $columnsResult->fetch_assoc()) {
                $availableColumns[] = $col['Field'];
            }
            error_log("Available columns in {$tableName}: " . implode(", ", $availableColumns));
        }
        
        // Detect ID column (might be 'id' or 'employee_id')
        $idColumn = in_array('id', $availableColumns) ? 'id' : 
                   (in_array('employee_id', $availableColumns) ? 'employee_id' : 'id');
        
        // Detect name column
        $nameColumn = null;
        $nameColumnsToTry = ['name', 'employee_name', 'full_name'];
        foreach ($nameColumnsToTry as $col) {
            if (in_array($col, $availableColumns)) {
                $nameColumn = $col;
                break;
            }
        }
        $nameExpression = $nameColumn ?: "CONCAT('Employee ', {$idColumn})";
        
        // Detect date column
        $dateColumn = in_array('join_date', $availableColumns) ? 'join_date' :
                     (in_array('hire_date', $availableColumns) ? 'hire_date' : 
                     (in_array('created_at', $availableColumns) ? 'created_at' : 'NOW()'));
        
        // Detect salary column
        $salaryColumn = in_array('basic_salary', $availableColumns) ? 'basic_salary' : 
                       (in_array('salary', $availableColumns) ? 'salary' : null);
        $salaryExpression = $salaryColumn ? "COALESCE({$salaryColumn}, 0)" : "0";
        
        $whereConditions = buildWhereClause($filters, 'hr_employees');
        
        // Build ORDER BY clause directly using detected columns
        $sortBy = $filters['sortBy'] ?? 'date';
        $orderByClause = '';
        if ($sortBy === 'name') {
            $orderByClause = "ORDER BY {$nameColumn} DESC";
        } else {
            // Use actual column name, not expression
            if ($dateColumn !== 'NOW()') {
                $orderByClause = "ORDER BY {$dateColumn} DESC";
            } else {
                $orderByClause = "ORDER BY {$idColumn} DESC";
            }
        }
        
        // Build query with proper WHERE clause - include salary
        $query = "SELECT 
                    {$idColumn} as id,
                    {$nameExpression} as name,
                    COALESCE(status, 'unknown') as status,
                    {$salaryExpression} as salary,
                    COALESCE({$dateColumn}, NOW()) as joinDate
                  FROM {$tableName}";
        
        if (!empty($whereConditions)) {
            $query .= " WHERE " . $whereConditions;
        }
        
        $query .= " " . $orderByClause . " LIMIT 50";
        
        error_log("HR query: " . $query);
        
        // First, check if table has ANY data at all
        $countQuery = "SELECT COUNT(*) as total FROM {$tableName}";
        $countResult = $conn->query($countQuery);
        if ($countResult) {
            $countRow = $countResult->fetch_assoc();
            $total = (int)($countRow['total'] ?? 0);
            error_log("Total rows in {$tableName}: " . $total);
            if ($total === 0) {
                error_log("Table {$tableName} is empty - returning empty array");
                return [];
            }
        }
        
        // Try absolute simplest query first
        $testQuery = "SELECT * FROM {$tableName} LIMIT 1";
        error_log("Testing simplest query: " . $testQuery);
        $testResult = $conn->query($testQuery);
        if (!$testResult) {
            error_log("Even simplest query failed: " . $conn->error);
            return [];
        }
        if ($testResult->num_rows > 0) {
            $testRow = $testResult->fetch_assoc();
            error_log("Test query succeeded - sample row keys: " . implode(", ", array_keys($testRow)));
        }
        
        $result = $conn->query($query);
        $data = [];
        
        if (!$result) {
            error_log("HR query failed: " . $conn->error);
            error_log("MySQL error code: " . $conn->errno);
            
            // Try simpler query without ORDER BY
            $simpleQuery = "SELECT {$idColumn} as id, {$nameExpression} as name, COALESCE(status, 'unknown') as status, {$salaryExpression} as salary, COALESCE({$dateColumn}, NOW()) as joinDate FROM {$tableName} LIMIT 50";
            error_log("Trying simple HR query: " . $simpleQuery);
            $simpleResult = $conn->query($simpleQuery);
            if ($simpleResult && $simpleResult->num_rows > 0) {
                error_log("Simple HR query worked - returned " . $simpleResult->num_rows . " rows");
                $result = $simpleResult;
            } else {
                error_log("Simple HR query also failed or returned 0 rows");
                if ($simpleResult) {
                    error_log("Simple query error: " . $conn->error);
                }
                return [];
            }
        }
        
        error_log("HR query returned " . $result->num_rows . " rows");
        
        if ($result->num_rows === 0) {
            error_log("No HR employees found in database after query");
            return [];
        }
        
        while ($row = $result->fetch_assoc()) {
            $salary = (float)($row['salary'] ?? 0);
            $status = strtolower($row['status'] ?? 'unknown');
            
            // Calculate performance based on status
            $performance = '0%';
            if ($status === 'active') {
                $performance = '100%';
            } else if ($status === 'inactive') {
                $performance = '0%';
            } else {
                $performance = '50%';
            }
            
            $data[] = [
                'id' => $row['id'],
                'name' => $row['name'] ?? 'N/A',
                'status' => $row['status'] ?? 'unknown',
                'revenue' => $salary > 0 ? '$' . number_format($salary, 2) : '$0.00',
                'performance' => $performance,
                'joinDate' => date('Y-m-d', strtotime($row['joinDate'] ?? 'now'))
            ];
        }
        
        error_log("Returning " . count($data) . " HR employees");
        if (count($data) > 0) {
            error_log("First HR employee: " . json_encode($data[0]));
        }
        return $data;
    } catch (Exception $e) {
        error_log("Error getting HR table data: " . $e->getMessage());
        return [];
    }
}

function getFinancialTableData($filters) {
    global $conn;
    
    error_log("=== getFinancialTableData DEBUG START ===");
    error_log("Filters received: " . json_encode($filters));
    
    try {
        // Check if financial_transactions table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            error_log("Financial_transactions table does not exist");
            return [];
        }
        
        // Get column names
        $columnsResult = $conn->query("SHOW COLUMNS FROM financial_transactions");
        $availableColumns = [];
        if ($columnsResult) {
            while ($col = $columnsResult->fetch_assoc()) {
                $availableColumns[] = $col['Field'];
            }
            error_log("Available columns in financial_transactions: " . implode(", ", $availableColumns));
        }
        
        // Build revenue expression based on available columns
        $revenueExpression = "0";
        if (in_array('total_amount', $availableColumns)) {
            $revenueExpression = "COALESCE(total_amount, 0)";
        } else if (in_array('amount', $availableColumns)) {
            $revenueExpression = "COALESCE(amount, 0)";
        }
        
        $whereConditions = buildWhereClause($filters, 'financial_transactions');
        
        // Check if description column exists
        $hasDescription = in_array('description', $availableColumns);
        $hasCreatedAt = in_array('created_at', $availableColumns);
        
        // Build ORDER BY clause directly
        $sortBy = $filters['sortBy'] ?? 'date';
        $orderByClause = '';
        if ($sortBy === 'name' && $hasDescription) {
            $orderByClause = "ORDER BY description DESC";
        } else if ($sortBy === 'amount') {
            $orderByClause = "ORDER BY {$revenueExpression} DESC";
        } else if ($hasCreatedAt) {
            $orderByClause = "ORDER BY created_at DESC";
        } else {
            $orderByClause = "ORDER BY id DESC";
        }
        
        // Build name expression - check if entity_type exists
        $hasEntityType = in_array('entity_type', $availableColumns);
        error_log("Financial - hasDescription: " . ($hasDescription ? 'YES' : 'NO') . ", hasEntityType: " . ($hasEntityType ? 'YES' : 'NO'));
        
        if ($hasDescription) {
            if ($hasEntityType) {
                $nameExpression = "COALESCE(description, CONCAT(COALESCE(transaction_type, 'Transaction'), ' - ', COALESCE(entity_type, 'N/A')))";
            } else {
                // entity_type doesn't exist, use simpler expression
                $nameExpression = "COALESCE(description, COALESCE(transaction_type, 'Transaction'))";
            }
        } else {
            if ($hasEntityType) {
                $nameExpression = "CONCAT(COALESCE(transaction_type, 'Transaction'), ' - ', COALESCE(entity_type, 'N/A'))";
            } else {
                $nameExpression = "COALESCE(transaction_type, 'Transaction')";
            }
        }
        
        error_log("Financial - nameExpression: " . $nameExpression);
        
        // Build date expression
        $dateExpression = $hasCreatedAt ? "COALESCE(created_at, NOW())" : "NOW()";
        
        // Build query with proper WHERE clause
        $query = "SELECT 
                    id,
                    {$nameExpression} as name,
                    COALESCE(transaction_type, 'unknown') as status,
                    {$revenueExpression} as revenue,
                    {$dateExpression} as joinDate
                  FROM financial_transactions";
        
        if (!empty($whereConditions)) {
            $query .= " WHERE " . $whereConditions;
        }
        
        $query .= " " . $orderByClause . " LIMIT 50";
        
        error_log("Financial query: " . $query);
        
        // First, check if table has ANY data at all
        $countQuery = "SELECT COUNT(*) as total FROM financial_transactions";
        $countResult = $conn->query($countQuery);
        if ($countResult) {
            $countRow = $countResult->fetch_assoc();
            $total = (int)($countRow['total'] ?? 0);
            error_log("Total rows in financial_transactions: " . $total);
            if ($total === 0) {
                error_log("Table financial_transactions is empty - returning empty array");
                return [];
            }
        }
        
        // Try absolute simplest query first
        $testQuery = "SELECT * FROM financial_transactions LIMIT 1";
        error_log("Testing simplest query: " . $testQuery);
        $testResult = $conn->query($testQuery);
        if (!$testResult) {
            error_log("Even simplest query failed: " . $conn->error);
            return [];
        }
        if ($testResult->num_rows > 0) {
            $testRow = $testResult->fetch_assoc();
            error_log("Test query succeeded - sample row keys: " . implode(", ", array_keys($testRow)));
        }
        
        $result = $conn->query($query);
        $data = [];
        
        if (!$result) {
            error_log("Financial query failed: " . $conn->error);
            error_log("MySQL error code: " . $conn->errno);
            
            // Try simpler query without ORDER BY
            $simpleQuery = "SELECT id, {$nameExpression} as name, COALESCE(transaction_type, 'unknown') as status, {$revenueExpression} as revenue, {$dateExpression} as joinDate FROM financial_transactions LIMIT 50";
            error_log("Trying simple Financial query: " . $simpleQuery);
            $simpleResult = $conn->query($simpleQuery);
            if ($simpleResult && $simpleResult->num_rows > 0) {
                error_log("Simple Financial query worked - returned " . $simpleResult->num_rows . " rows");
                $result = $simpleResult;
            } else {
                error_log("Simple Financial query also failed or returned 0 rows");
                if ($simpleResult) {
                    error_log("Simple query error: " . $conn->error);
                }
                return [];
            }
        }
        
        error_log("Financial query returned " . $result->num_rows . " rows");
        
        if ($result->num_rows === 0) {
            error_log("No financial transactions found in database after query");
            return [];
        }
        
        while ($row = $result->fetch_assoc()) {
            $amount = (float)($row['revenue'] ?? 0);
            $status = strtolower($row['status'] ?? 'unknown');
            
            // Calculate balance (for income transactions, balance is positive; for expenses, negative)
            $balance = $amount;
            if (in_array($status, ['expense', 'expenses', 'payment'])) {
                $balance = -$amount;
            }
            
            // Calculate performance based on transaction type
            $performance = '0%';
            if (in_array($status, ['income', 'revenue', 'receipt'])) {
                $performance = '100%';
            } else if (in_array($status, ['expense', 'payment'])) {
                $performance = '50%';
            } else {
                $performance = '0%';
            }
            
            $data[] = [
                'id' => $row['id'],
                'name' => $row['name'] ?? 'N/A',
                'status' => $row['status'] ?? 'unknown',
                'revenue' => '$' . number_format($amount, 2),
                'performance' => $balance != 0 ? '$' . number_format($balance, 2) : '$0.00',
                'joinDate' => date('Y-m-d', strtotime($row['joinDate'] ?? 'now'))
            ];
        }
        
        error_log("Returning " . count($data) . " financial transactions");
        if (count($data) > 0) {
            error_log("First transaction: " . json_encode($data[0]));
        }
        return $data;
    } catch (Exception $e) {
        error_log("Error getting financial table data: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return [];
    }
}

function buildWhereClause($filters, $table) {
    global $conn;
    $conditions = [];
    
    if (!empty($filters['status']) && $filters['status'] !== 'all' && $table !== 'financial_transactions') {
        $status = $conn->real_escape_string($filters['status']);
        $conditions[] = "status = '$status'";
    }
    
    if (!empty($filters['dateRange']['start']) && !empty($filters['dateRange']['end'])) {
        $dateField = 'created_at';
        if ($table === 'workers') $dateField = 'hire_date';
        elseif ($table === 'hr_employees' || $table === 'employees') $dateField = 'join_date';
        elseif ($table === 'financial_transactions') $dateField = getFinancialDateColumn($conn);
        $start = $conn->real_escape_string($filters['dateRange']['start']);
        $end = $conn->real_escape_string($filters['dateRange']['end']);
        $conditions[] = "$dateField BETWEEN '$start' AND '$end'";
    }
    
    return empty($conditions) ? '' : implode(' AND ', $conditions);
}

function getOrderBy($filters, $table, $nameColumn = null) {
    $sortBy = $filters['sortBy'] ?? 'date';
    $orderField = '';
    
    error_log("getOrderBy called - sortBy: " . $sortBy . ", table: " . $table . ", nameColumn: " . ($nameColumn ?: "null"));
    
    switch ($sortBy) {
        case 'name':
            // Use the actual column name, not the alias
            if ($table === 'agents' && $nameColumn) {
                $orderField = $nameColumn; // Use detected name column (e.g., agent_name, name, formatted_id)
            } else {
                // For other tables, try to use name column if provided
                $orderField = $nameColumn ?: 'name'; // Fallback for other tables
            }
            break;
        case 'amount':
            $orderField = ($table === 'cases' || $table === 'financial_transactions') ? 
                         'COALESCE(total_amount, amount, 0)' : 'revenue';
            break;
        case 'date':
        default:
            $orderField = ($table === 'workers' || $table === 'hr_employees') ? 'hire_date' : 'created_at';
            break;
    }
    
    if (empty($orderField)) {
        $orderField = ($table === 'workers' || $table === 'hr_employees') ? 'hire_date' : 'created_at';
    }
    
    $orderByClause = "ORDER BY $orderField DESC";
    error_log("getOrderBy returning: " . $orderByClause);
    
    return $orderByClause;
}

function debugTable() {
    global $conn;
    
    $category = $_GET['category'] ?? 'hr';
    $response = [
        'success' => true,
        'category' => $category,
        'debug' => []
    ];
    
    if ($category === 'hr') {
        // Check HR tables
        $tables = ['hr_employees', 'employees'];
        foreach ($tables as $table) {
            $check = $conn->query("SHOW TABLES LIKE '{$table}'");
            if ($check && $check->num_rows > 0) {
                $response['debug']['table_found'] = $table;
                
                // Get count
                $count = $conn->query("SELECT COUNT(*) as total FROM {$table}");
                if ($count) {
                    $row = $count->fetch_assoc();
                    $response['debug']['total_rows'] = (int)($row['total'] ?? 0);
                }
                
                // Get columns
                $cols = $conn->query("SHOW COLUMNS FROM {$table}");
                $columns = [];
                if ($cols) {
                    while ($col = $cols->fetch_assoc()) {
                        $columns[] = $col['Field'];
                    }
                }
                $response['debug']['columns'] = $columns;
                
                // Try simple query
                $simple = $conn->query("SELECT * FROM {$table} LIMIT 1");
                if ($simple && $simple->num_rows > 0) {
                    $sample = $simple->fetch_assoc();
                    $response['debug']['sample_row'] = array_keys($sample);
                }
                break;
            }
        }
    } else if ($category === 'financial') {
        $check = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
        if ($check && $check->num_rows > 0) {
            $response['debug']['table_found'] = 'financial_transactions';
            
            // Get count
            $count = $conn->query("SELECT COUNT(*) as total FROM financial_transactions");
            if ($count) {
                $row = $count->fetch_assoc();
                $response['debug']['total_rows'] = (int)($row['total'] ?? 0);
            }
            
            // Get columns
            $cols = $conn->query("SHOW COLUMNS FROM financial_transactions");
            $columns = [];
            if ($cols) {
                while ($col = $cols->fetch_assoc()) {
                    $columns[] = $col['Field'];
                }
            }
            $response['debug']['columns'] = $columns;
            
            // Try simple query
            $simple = $conn->query("SELECT * FROM financial_transactions LIMIT 1");
            if ($simple && $simple->num_rows > 0) {
                $sample = $simple->fetch_assoc();
                $response['debug']['sample_row'] = array_keys($sample);
            }
        }
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
}

function exportCSV($data, $category) {
    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No data to export']);
        return;
    }

    $filename = $category . '_report_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }
    
    // Write data
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

function getLogStats() {
    global $conn;
    
    if (!$conn) {
        error_log("getLogStats: Database connection not available");
        echo json_encode(['success' => false, 'message' => 'Database connection not available']);
        return;
    }
    
    $stats = [
        'activity_logs' => ['total' => 0, 'today' => 0, 'month' => 0],
        'system_events' => ['total' => 0, 'today' => 0, 'month' => 0],
        'case_activities' => ['total' => 0, 'today' => 0, 'month' => 0],
        'global_history' => ['total' => 0, 'today' => 0, 'month' => 0]
    ];
    
    // Activity logs
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'activity_logs'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $result = $conn->query("SELECT COUNT(*) as count FROM activity_logs");
            if ($result) {
                $row = $result->fetch_assoc();
                $stats['activity_logs']['total'] = (int)($row['count'] ?? 0);
            } else {
                error_log("Activity logs total query failed: " . $conn->error);
            }
            
            // Check if created_at column exists before querying by date
            $colCheck = $conn->query("SHOW COLUMNS FROM activity_logs LIKE 'created_at'");
            if ($colCheck && $colCheck->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['activity_logs']['today'] = (int)($row['count'] ?? 0);
                } else {
                    error_log("Activity logs today query failed: " . $conn->error);
                }
                
                $result = $conn->query("SELECT COUNT(*) as count FROM activity_logs WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['activity_logs']['month'] = (int)($row['count'] ?? 0);
                } else {
                    error_log("Activity logs month query failed: " . $conn->error);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Activity logs error: " . $e->getMessage());
    }
    
    // System events (central observability)
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'system_events'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $result = $conn->query("SELECT COUNT(*) as count FROM system_events WHERE event_type LIKE 'CONTROL_%' OR event_type LIKE 'QUERY_%' OR event_type = 'ADMIN_AUDIT'");
            if ($result) {
                $row = $result->fetch_assoc();
                $stats['system_events']['total'] = (int)($row['count'] ?? 0);
            } else {
                error_log("System events total query failed: " . $conn->error);
            }
            
            $colCheck = $conn->query("SHOW COLUMNS FROM system_events LIKE 'created_at'");
            if ($colCheck && $colCheck->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as count FROM system_events WHERE (event_type LIKE 'CONTROL_%' OR event_type LIKE 'QUERY_%' OR event_type = 'ADMIN_AUDIT') AND DATE(created_at) = CURDATE()");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['system_events']['today'] = (int)($row['count'] ?? 0);
                } else {
                    error_log("System events today query failed: " . $conn->error);
                }
                
                $result = $conn->query("SELECT COUNT(*) as count FROM system_events WHERE (event_type LIKE 'CONTROL_%' OR event_type LIKE 'QUERY_%' OR event_type = 'ADMIN_AUDIT') AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['system_events']['month'] = (int)($row['count'] ?? 0);
                } else {
                    error_log("System events month query failed: " . $conn->error);
                }
            }
        }
    } catch (Exception $e) {
        error_log("System events error: " . $e->getMessage());
    }
    
    // Case activities
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'case_activities'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $result = $conn->query("SELECT COUNT(*) as count FROM case_activities");
            if ($result) {
                $row = $result->fetch_assoc();
                $stats['case_activities']['total'] = (int)($row['count'] ?? 0);
            } else {
                error_log("Case activities total query failed: " . $conn->error);
            }
            
            $colCheck = $conn->query("SHOW COLUMNS FROM case_activities LIKE 'created_at'");
            if ($colCheck && $colCheck->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as count FROM case_activities WHERE DATE(created_at) = CURDATE()");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['case_activities']['today'] = (int)($row['count'] ?? 0);
                } else {
                    error_log("Case activities today query failed: " . $conn->error);
                }
                
                $result = $conn->query("SELECT COUNT(*) as count FROM case_activities WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['case_activities']['month'] = (int)($row['count'] ?? 0);
                } else {
                    error_log("Case activities month query failed: " . $conn->error);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Case activities error: " . $e->getMessage());
    }
    
    // Global history
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'global_history'");
        if ($tableCheck && $tableCheck->num_rows > 0) {
            $result = $conn->query("SELECT COUNT(*) as count FROM global_history");
            if ($result) {
                $row = $result->fetch_assoc();
                $stats['global_history']['total'] = (int)($row['count'] ?? 0);
            } else {
                error_log("Global history total query failed: " . $conn->error);
            }
            
            $colCheck = $conn->query("SHOW COLUMNS FROM global_history LIKE 'created_at'");
            if ($colCheck && $colCheck->num_rows > 0) {
                $result = $conn->query("SELECT COUNT(*) as count FROM global_history WHERE DATE(created_at) = CURDATE()");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['global_history']['today'] = (int)($row['count'] ?? 0);
                } else {
                    error_log("Global history today query failed: " . $conn->error);
                }
                
                $result = $conn->query("SELECT COUNT(*) as count FROM global_history WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
                if ($result) {
                    $row = $result->fetch_assoc();
                    $stats['global_history']['month'] = (int)($row['count'] ?? 0);
                } else {
                    error_log("Global history month query failed: " . $conn->error);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Global history error: " . $e->getMessage());
    }
    
    echo json_encode(['success' => true, 'data' => $stats]);
}

/**
 * Debug global_history: breakdown by module/action and by date.
 * ?action=debug_global_history
 */
function debugGlobalHistory() {
    global $conn;
    $chk = $conn->query("SHOW TABLES LIKE 'global_history'");
    if (!$chk || $chk->num_rows === 0) {
        echo json_encode(['success' => true, 'exists' => false, 'message' => 'global_history table does not exist']);
        return;
    }
    $byModule = [];
    $r = $conn->query("SELECT module, action, COUNT(*) as cnt FROM global_history GROUP BY module, action ORDER BY module, action");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $byModule[] = $row;
        }
    }
    $byDate = [];
    $r = $conn->query("SELECT DATE(created_at) as d, COUNT(*) as cnt FROM global_history GROUP BY DATE(created_at) ORDER BY d DESC LIMIT 30");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $byDate[] = $row;
        }
    }
    $total = 0;
    $r = $conn->query("SELECT COUNT(*) as c FROM global_history");
    if ($r) $total = (int)$r->fetch_assoc()['c'];
    echo json_encode([
        'success' => true,
        'exists' => true,
        'total' => $total,
        'by_module_action' => $byModule,
        'by_date' => $byDate
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Delete ALL records from report/history tables across the entire system.
 * ?action=clear_reports_history&confirm=1 to execute. &dry_run=1 to preview.
 */
function clearReportsHistory() {
    $conn = isset($GLOBALS['conn']) ? $GLOBALS['conn'] : null;
    if (!$conn) {
        echo json_encode(['success' => false, 'message' => 'Database connection not available']);
        return;
    }
    $confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';
    $dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';

    if (!$confirm && !$dryRun) {
        echo json_encode(['success' => false, 'message' => 'Add confirm=1 to execute. Add dry_run=1 to preview.']);
        return;
    }

    $results = [];
    $run = function($table) use ($conn, $dryRun, &$results) {
        $chk = @$conn->query("SHOW TABLES LIKE '{$table}'");
        if (!$chk || $chk->num_rows === 0) {
            $results[$table] = ['exists' => false, 'deleted' => 0];
            return;
        }
        $cnt = $conn->query("SELECT COUNT(*) as c FROM `{$table}`");
        $n = $cnt ? (int)$cnt->fetch_assoc()['c'] : 0;
        if (!$dryRun && $n > 0) {
            $conn->query("DELETE FROM `{$table}`");
        }
        $results[$table] = ['deleted' => $n];
    };

    $run('global_history');
    $run('activity_logs');
    $run('system_events');
    $run('case_activities');
    $run('financial_transactions');
    $run('entity_transactions');
    $run('transaction_lines');
    $run('entry_approval');
    $run('entity_totals');
    $run('salaries');
    $run('employees');
    $run('hr_employees');

    $jel = @$conn->query("SHOW TABLES LIKE 'journal_entry_lines'");
    if ($jel && $jel->num_rows > 0) {
        $c = $conn->query("SELECT COUNT(*) as cnt FROM journal_entry_lines");
        $n = $c ? (int)$c->fetch_assoc()['cnt'] : 0;
        if (!$dryRun && $n > 0) $conn->query("DELETE FROM journal_entry_lines");
        $results['journal_entry_lines'] = ['deleted' => $n];
    }
    $run('journal_entries');

    foreach (['receipt_payment_vouchers', 'payment_receipts', 'payment_payments', 'accounts_receivable', 'accounts_payable', 'accounting_bank_transactions', 'accounting_messages', 'accounting_followups'] as $t) {
        $run($t);
    }

    $total = 0;
    foreach ($results as $r) {
        $total += $r['deleted'] ?? 0;
    }
    echo json_encode(['success' => true, 'dry_run' => $dryRun, 'results' => $results, 'total_affected' => $total]);
}

/**
 * Clear all calculation/report data before cutoff 01/02/2026.
 * ?action=clear_calculations&confirm=1 to execute. &dry_run=1 to preview.
 */
function clearCalculations() {
    global $conn;
    $cutoff = '2026-02-01';
    $cutoffMonth = '2026-02';
    $confirm = isset($_GET['confirm']) && $_GET['confirm'] === '1';
    $dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';

    if (!$confirm && !$dryRun) {
        echo json_encode(['success' => false, 'message' => 'Add confirm=1 to execute. Add dry_run=1 to preview.', 'cutoff' => $cutoff]);
        return;
    }

    $c = $conn->real_escape_string($cutoff);
    $results = [];

    $run = function($table, $where) use ($conn, $dryRun, &$results) {
        $chk = $conn->query("SHOW TABLES LIKE '{$table}'");
        if (!$chk || $chk->num_rows === 0) { $results[$table] = ['exists' => false]; return; }
        $cnt = $conn->query("SELECT COUNT(*) as c FROM {$table} WHERE {$where}");
        $n = $cnt ? (int)$cnt->fetch_assoc()['c'] : 0;
        if (!$dryRun && $n > 0) { $conn->query("DELETE FROM {$table} WHERE {$where}"); }
        $results[$table] = ['deleted' => $n];
    };

    $amtCol = getFinancialAmountColumn($conn);
    $dtCol = $amtCol ? getFinancialDateColumn($conn) : 'created_at';
    if ($amtCol) $run('financial_transactions', "{$dtCol} < '{$c}'");

    $run('salaries', "salary_month < '{$cutoffMonth}'");
    $run('activity_logs', "created_at < '{$c}'");
    $run('system_events', "created_at < '{$c}'");
    $run('case_activities', "created_at < '{$c}'");
    $run('global_history', "created_at < '{$c}'");

    $jcols = @$conn->query("SHOW COLUMNS FROM journal_entries");
    if ($jcols) {
        $jf = []; while ($r = $jcols->fetch_assoc()) $jf[] = $r['Field'];
        $jd = in_array('entry_date', $jf) ? 'entry_date' : (in_array('created_at', $jf) ? 'created_at' : null);
        if ($jd) {
            $jel = $conn->query("SHOW TABLES LIKE 'journal_entry_lines'");
            if ($jel && $jel->num_rows > 0 && !$dryRun) {
                $conn->query("DELETE jel FROM journal_entry_lines jel INNER JOIN journal_entries je ON jel.journal_entry_id = je.id WHERE je.{$jd} < '{$c}'");
            }
            $run('journal_entries', "{$jd} < '{$c}'");
        }
    }

    foreach (['receipt_payment_vouchers', 'payment_receipts'] as $t) {
        $tc = @$conn->query("SHOW TABLES LIKE '{$t}'");
        if ($tc && $tc->num_rows > 0) {
            $tcols = $conn->query("SHOW COLUMNS FROM {$t}");
            $tf = []; if ($tcols) { while ($r = $tcols->fetch_assoc()) $tf[] = $r['Field']; }
            $td = in_array('payment_date', $tf) ? 'payment_date' : (in_array('created_at', $tf) ? 'created_at' : null);
            if ($td) $run($t, "{$td} < '{$c}'");
        }
    }

    $total = array_sum(array_column($results, 'deleted'));
    echo json_encode(['success' => true, 'dry_run' => $dryRun, 'cutoff' => $cutoff, 'results' => $results, 'total_affected' => $total]);
}
?>
