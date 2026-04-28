<?php
/**
 * EN: Handles API endpoint/business logic in `api/reports/reports-real.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/reports/reports-real.php`.
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once(__DIR__ . '/../../config/database.php');

class ReportsAPI {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function handleRequest() {
        try {
            $action = $_GET['action'] ?? '';
            $category = $_GET['category'] ?? '';
            
            switch ($action) {
                case 'get_category_data':
                    $this->getCategoryData($category);
                    break;
                default:
                    throw new Exception('Invalid action');
            }
        } catch (Exception $e) {
            $this->sendError($e->getMessage());
        }
    }
    
    private function getCategoryData($category) {
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        $status = $_GET['status'] ?? 'all';
        $sortBy = $_GET['sort_by'] ?? 'date';
        
        switch ($category) {
            case 'agents':
                $data = $this->getAgentsData($startDate, $endDate, $status, $sortBy);
                break;
            case 'subagents':
                $data = $this->getSubAgentsData($startDate, $endDate, $status, $sortBy);
                break;
            case 'workers':
                $data = $this->getWorkersData($startDate, $endDate, $status, $sortBy);
                break;
            case 'cases':
                $data = $this->getCasesData($startDate, $endDate, $status, $sortBy);
                break;
            case 'hr':
                $data = $this->getHRData($startDate, $endDate, $status, $sortBy);
                break;
            case 'financial':
                $data = $this->getFinancialData($startDate, $endDate, $status, $sortBy);
                break;
            default:
                throw new Exception('Invalid category');
        }
        
        $this->sendSuccess($data);
    }
    
    private function getAgentsData($startDate, $endDate, $status, $sortBy) {
        try {
            // Get total agents
            $totalQuery = "SELECT COUNT(*) as total FROM agents";
            $totalStmt = $this->db->prepare($totalQuery);
            $totalStmt->execute();
            $totalAgents = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get active agents
            $activeQuery = "SELECT COUNT(*) as active FROM agents WHERE status = 'active'";
            $activeStmt = $this->db->prepare($activeQuery);
            $activeStmt->execute();
            $activeAgents = $activeStmt->fetch(PDO::FETCH_ASSOC)['active'] ?? 0;
            
            // Get new agents this month
            $newThisMonthQuery = "SELECT COUNT(*) as new_this_month FROM agents WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
            $newThisMonthStmt = $this->db->prepare($newThisMonthQuery);
            $newThisMonthStmt->execute();
            $newThisMonth = $newThisMonthStmt->fetch(PDO::FETCH_ASSOC)['new_this_month'] ?? 0;
            
            // Get total revenue - try different possible table structures
            $totalRevenue = 0;
            try {
                $revenueQuery = "SELECT COALESCE(SUM(amount), 0) as total_revenue FROM transactions WHERE type = 'agent_commission' AND status = 'completed'";
                if ($startDate && $endDate) {
                    $revenueQuery .= " AND DATE(created_at) BETWEEN :start_date AND :end_date";
                }
                $revenueStmt = $this->db->prepare($revenueQuery);
                if ($startDate && $endDate) {
                    $revenueStmt->bindParam(':start_date', $startDate);
                    $revenueStmt->bindParam(':end_date', $endDate);
                }
                $revenueStmt->execute();
                $totalRevenue = $revenueStmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
            } catch (Exception $e) {
                // If transactions table doesn't exist, try agents table
                try {
                    $revenueQuery = "SELECT COALESCE(SUM(COALESCE(revenue, 0)), 0) as total_revenue FROM agents";
                    $revenueStmt = $this->db->prepare($revenueQuery);
                    $revenueStmt->execute();
                    $totalRevenue = $revenueStmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
                } catch (Exception $e2) {
                    $totalRevenue = 0;
                }
            }
            
            // Get chart data
            $chartData = $this->getPerformanceChartData('agents', 6);
            
            // Get table data
            $tableData = $this->getAgentsTableData($status, $sortBy);
            
            // Get additional metrics
            $inactiveAgents = $totalAgents - $activeAgents;
            $avgRevenue = $activeAgents > 0 ? $totalRevenue / $activeAgents : 0;
            $completionRate = $totalAgents > 0 ? ($activeAgents / $totalAgents) * 100 : 0;
            
            return [
                'stats' => [
                    [
                        'label' => 'Total Agents',
                        'value' => $totalAgents,
                        'icon' => 'fas fa-user-tie'
                    ],
                    [
                        'label' => 'Active Agents',
                        'value' => $activeAgents,
                        'icon' => 'fas fa-user-check'
                    ],
                    [
                        'label' => 'Inactive Agents',
                        'value' => $inactiveAgents,
                        'icon' => 'fas fa-user-times'
                    ],
                    [
                        'label' => 'New This Month',
                        'value' => $newThisMonth,
                        'icon' => 'fas fa-user-plus'
                    ],
                    [
                        'label' => 'Total Revenue',
                        'value' => '$' . number_format($totalRevenue, 2),
                        'icon' => 'fas fa-dollar-sign'
                    ],
                    [
                        'label' => 'Avg Revenue/Agent',
                        'value' => '$' . number_format($avgRevenue, 2),
                        'icon' => 'fas fa-chart-line'
                    ],
                    [
                        'label' => 'Completion Rate',
                        'value' => number_format($completionRate, 1) . '%',
                        'icon' => 'fas fa-percentage'
                    ],
                    [
                        'label' => 'Growth Rate',
                        'value' => $this->calculateGrowthRate('agents'),
                        'icon' => 'fas fa-trending-up'
                    ]
                ],
                'charts' => [
                    'performance' => [
                        'type' => 'line',
                        'data' => $chartData['performance']
                    ],
                    'revenue' => [
                        'type' => 'bar',
                        'data' => $chartData['revenue']
                    ]
                ],
                'tableData' => $tableData
            ];
        } catch (Exception $e) {
            // Return default data if there's an error
            return $this->getDefaultData('agents');
        }
    }
    
    private function getSubAgentsData($startDate, $endDate, $status, $sortBy) {
        try {
            // Get total subagents
            $totalQuery = "SELECT COUNT(*) as total FROM subagents";
            $totalStmt = $this->db->prepare($totalQuery);
            $totalStmt->execute();
            $totalSubAgents = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get active subagents
            $activeQuery = "SELECT COUNT(*) as active FROM subagents WHERE status = 'active'";
            $activeStmt = $this->db->prepare($activeQuery);
            $activeStmt->execute();
            $activeSubAgents = $activeStmt->fetch(PDO::FETCH_ASSOC)['active'] ?? 0;
            
            // Get new subagents this month
            $newThisMonthQuery = "SELECT COUNT(*) as new_this_month FROM subagents WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
            $newThisMonthStmt = $this->db->prepare($newThisMonthQuery);
            $newThisMonthStmt->execute();
            $newThisMonth = $newThisMonthStmt->fetch(PDO::FETCH_ASSOC)['new_this_month'] ?? 0;
            
            // Get total commission
            $totalCommission = 0;
            try {
                $commissionQuery = "SELECT COALESCE(SUM(amount), 0) as total_commission FROM transactions WHERE type = 'subagent_commission' AND status = 'completed'";
                if ($startDate && $endDate) {
                    $commissionQuery .= " AND DATE(created_at) BETWEEN :start_date AND :end_date";
                }
                $commissionStmt = $this->db->prepare($commissionQuery);
                if ($startDate && $endDate) {
                    $commissionStmt->bindParam(':start_date', $startDate);
                    $commissionStmt->bindParam(':end_date', $endDate);
                }
                $commissionStmt->execute();
                $totalCommission = $commissionStmt->fetch(PDO::FETCH_ASSOC)['total_commission'] ?? 0;
            } catch (Exception $e) {
                try {
                    $commissionQuery = "SELECT COALESCE(SUM(COALESCE(commission, 0)), 0) as total_commission FROM subagents";
                    $commissionStmt = $this->db->prepare($commissionQuery);
                    $commissionStmt->execute();
                    $totalCommission = $commissionStmt->fetch(PDO::FETCH_ASSOC)['total_commission'] ?? 0;
                } catch (Exception $e2) {
                    $totalCommission = 0;
                }
            }
            
            $chartData = $this->getPerformanceChartData('subagents', 6);
            $tableData = $this->getSubAgentsTableData($status, $sortBy);
            
            // Get additional metrics
            $inactiveSubAgents = $totalSubAgents - $activeSubAgents;
            $avgCommission = $activeSubAgents > 0 ? $totalCommission / $activeSubAgents : 0;
            $completionRate = $totalSubAgents > 0 ? ($activeSubAgents / $totalSubAgents) * 100 : 0;
            
            return [
                'stats' => [
                    [
                        'label' => 'Total SubAgents',
                        'value' => $totalSubAgents,
                        'icon' => 'fas fa-users',
                    ],
                    [
                        'label' => 'Active SubAgents',
                        'value' => $activeSubAgents,
                        'icon' => 'fas fa-user-check',
                    ],
                    [
                        'label' => 'Inactive SubAgents',
                        'value' => $inactiveSubAgents,
                        'icon' => 'fas fa-user-times',
                    ],
                    [
                        'label' => 'New This Month',
                        'value' => $newThisMonth,
                        'icon' => 'fas fa-user-plus',
                    ],
                    [
                        'label' => 'Total Commission',
                        'value' => '$' . number_format($totalCommission, 2),
                        'icon' => 'fas fa-dollar-sign',
                    ],
                    [
                        'label' => 'Avg Commission',
                        'value' => '$' . number_format($avgCommission, 2),
                        'icon' => 'fas fa-chart-line',
                    ],
                    [
                        'label' => 'Completion Rate',
                        'value' => number_format($completionRate, 1) . '%',
                        'icon' => 'fas fa-percentage',
                    ],
                    [
                        'label' => 'Growth Rate',
                        'value' => $this->calculateGrowthRate('subagents'),
                        'icon' => 'fas fa-trending-up',
                    ]
                ],
                'charts' => [
                    'performance' => [
                        'type' => 'line',
                        'data' => $chartData['performance']
                    ],
                    'revenue' => [
                        'type' => 'bar',
                        'data' => $chartData['revenue']
                    ]
                ],
                'tableData' => $tableData
            ];
        } catch (Exception $e) {
            return $this->getDefaultData('subagents');
        }
    }
    
    private function getWorkersData($startDate, $endDate, $status, $sortBy) {
        try {
            // Get total workers
            $totalQuery = "SELECT COUNT(*) as total FROM workers";
            $totalStmt = $this->db->prepare($totalQuery);
            $totalStmt->execute();
            $totalWorkers = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get active workers
            $activeQuery = "SELECT COUNT(*) as active FROM workers WHERE status = 'active'";
            $activeStmt = $this->db->prepare($activeQuery);
            $activeStmt->execute();
            $activeWorkers = $activeStmt->fetch(PDO::FETCH_ASSOC)['active'] ?? 0;
            
            // Get new workers this month
            $newThisMonthQuery = "SELECT COUNT(*) as new_this_month FROM workers WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
            $newThisMonthStmt = $this->db->prepare($newThisMonthQuery);
            $newThisMonthStmt->execute();
            $newThisMonth = $newThisMonthStmt->fetch(PDO::FETCH_ASSOC)['new_this_month'] ?? 0;
            
            // Get total payroll
            $totalPayroll = 0;
            try {
                $payrollQuery = "SELECT COALESCE(SUM(COALESCE(salary, 0)), 0) as total_payroll FROM workers WHERE status = 'active'";
                $payrollStmt = $this->db->prepare($payrollQuery);
                $payrollStmt->execute();
                $totalPayroll = $payrollStmt->fetch(PDO::FETCH_ASSOC)['total_payroll'] ?? 0;
            } catch (Exception $e) {
                $totalPayroll = 0;
            }
            
            $chartData = $this->getPerformanceChartData('workers', 6);
            $tableData = $this->getWorkersTableData($status, $sortBy);

            // Get additional metrics
            $inactiveWorkers = $totalWorkers - $activeWorkers;
            $avgSalary = $activeWorkers > 0 ? $totalPayroll / $activeWorkers : 0;
            $completionRate = $totalWorkers > 0 ? ($activeWorkers / $totalWorkers) * 100 : 0;

            return [
                'stats' => [
                    [
                        'label' => 'Total Workers',
                        'value' => $totalWorkers,
                        'icon' => 'fas fa-hard-hat',
                    ],
                    [
                        'label' => 'Active Workers',
                        'value' => $activeWorkers,
                        'icon' => 'fas fa-user-check',
                    ],
                    [
                        'label' => 'Inactive Workers',
                        'value' => $inactiveWorkers,
                        'icon' => 'fas fa-user-times',
                    ],
                    [
                        'label' => 'New This Month',
                        'value' => $newThisMonth,
                        'icon' => 'fas fa-user-plus',
                    ],
                    [
                        'label' => 'Total Payroll',
                        'value' => '$' . number_format($totalPayroll, 2),
                        'icon' => 'fas fa-dollar-sign',
                    ],
                    [
                        'label' => 'Avg Salary',
                        'value' => '$' . number_format($avgSalary, 2),
                        'icon' => 'fas fa-chart-line',
                    ],
                    [
                        'label' => 'Completion Rate',
                        'value' => number_format($completionRate, 1) . '%',
                        'icon' => 'fas fa-percentage',
                    ],
                    [
                        'label' => 'Growth Rate',
                        'value' => $this->calculateGrowthRate('workers'),
                        'icon' => 'fas fa-trending-up',
                    ]
                ],
                'charts' => [
                    'performance' => [
                        'type' => 'line',
                        'data' => $chartData['performance']
                    ],
                    'revenue' => [
                        'type' => 'bar',
                        'data' => $chartData['revenue']
                    ]
                ],
                'tableData' => $tableData
            ];
        } catch (Exception $e) {
            return $this->getDefaultData('workers');
        }
    }
    
    private function getCasesData($startDate, $endDate, $status, $sortBy) {
        try {
            // Get total cases
            $totalQuery = "SELECT COUNT(*) as total FROM cases";
            $totalStmt = $this->db->prepare($totalQuery);
            $totalStmt->execute();
            $totalCases = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get active cases
            $activeQuery = "SELECT COUNT(*) as active FROM cases WHERE status = 'active'";
            $activeStmt = $this->db->prepare($activeQuery);
            $activeStmt->execute();
            $activeCases = $activeStmt->fetch(PDO::FETCH_ASSOC)['active'] ?? 0;
            
            // Get new cases this month
            $newThisMonthQuery = "SELECT COUNT(*) as new_this_month FROM cases WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
            $newThisMonthStmt = $this->db->prepare($newThisMonthQuery);
            $newThisMonthStmt->execute();
            $newThisMonth = $newThisMonthStmt->fetch(PDO::FETCH_ASSOC)['new_this_month'] ?? 0;
            
            // Get total revenue from cases
            $totalRevenue = 0;
            try {
                $revenueQuery = "SELECT COALESCE(SUM(amount), 0) as total_revenue FROM transactions WHERE type = 'case_fee' AND status = 'completed'";
                if ($startDate && $endDate) {
                    $revenueQuery .= " AND DATE(created_at) BETWEEN :start_date AND :end_date";
                }
                $revenueStmt = $this->db->prepare($revenueQuery);
                if ($startDate && $endDate) {
                    $revenueStmt->bindParam(':start_date', $startDate);
                    $revenueStmt->bindParam(':end_date', $endDate);
                }
                $revenueStmt->execute();
                $totalRevenue = $revenueStmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
            } catch (Exception $e) {
                try {
                    $revenueQuery = "SELECT COALESCE(SUM(COALESCE(amount, 0)), 0) as total_revenue FROM cases";
                    $revenueStmt = $this->db->prepare($revenueQuery);
                    $revenueStmt->execute();
                    $totalRevenue = $revenueStmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
                } catch (Exception $e2) {
                    $totalRevenue = 0;
                }
            }
            
            $chartData = $this->getPerformanceChartData('cases', 6);
            $tableData = $this->getCasesTableData($status, $sortBy);

            // Get additional metrics
            $completedCases = $totalCases - $activeCases;
            $avgRevenue = $completedCases > 0 ? $totalRevenue / $completedCases : 0;
            $completionRate = $totalCases > 0 ? ($completedCases / $totalCases) * 100 : 0;

            return [
                'stats' => [
                    [
                        'label' => 'Total Cases',
                        'value' => $totalCases,
                        'icon' => 'fas fa-briefcase',
                    ],
                    [
                        'label' => 'Active Cases',
                        'value' => $activeCases,
                        'icon' => 'fas fa-briefcase',
                    ],
                    [
                        'label' => 'Completed Cases',
                        'value' => $completedCases,
                        'icon' => 'fas fa-check-circle',
                    ],
                    [
                        'label' => 'New This Month',
                        'value' => $newThisMonth,
                        'icon' => 'fas fa-plus',
                    ],
                    [
                        'label' => 'Total Revenue',
                        'value' => '$' . number_format($totalRevenue, 2),
                        'icon' => 'fas fa-dollar-sign',
                    ],
                    [
                        'label' => 'Avg Revenue/Case',
                        'value' => '$' . number_format($avgRevenue, 2),
                        'icon' => 'fas fa-chart-line',
                    ],
                    [
                        'label' => 'Completion Rate',
                        'value' => number_format($completionRate, 1) . '%',
                        'icon' => 'fas fa-percentage',
                    ],
                    [
                        'label' => 'Growth Rate',
                        'value' => $this->calculateGrowthRate('cases'),
                        'icon' => 'fas fa-trending-up',
                    ]
                ],
                'charts' => [
                    'performance' => [
                        'type' => 'line',
                        'data' => $chartData['performance']
                    ],
                    'revenue' => [
                        'type' => 'bar',
                        'data' => $chartData['revenue']
                    ]
                ],
                'tableData' => $tableData
            ];
        } catch (Exception $e) {
            return $this->getDefaultData('cases');
        }
    }
    
    private function getHRData($startDate, $endDate, $status, $sortBy) {
        try {
            // Get total employees
            $totalQuery = "SELECT COUNT(*) as total FROM hr_employees";
            $totalStmt = $this->db->prepare($totalQuery);
            $totalStmt->execute();
            $totalEmployees = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get active employees
            $activeQuery = "SELECT COUNT(*) as active FROM hr_employees WHERE status = 'active'";
            $activeStmt = $this->db->prepare($activeQuery);
            $activeStmt->execute();
            $activeEmployees = $activeStmt->fetch(PDO::FETCH_ASSOC)['active'] ?? 0;
            
            // Get new employees this month
            $newThisMonthQuery = "SELECT COUNT(*) as new_this_month FROM hr_employees WHERE MONTH(hire_date) = MONTH(CURRENT_DATE()) AND YEAR(hire_date) = YEAR(CURRENT_DATE())";
            $newThisMonthStmt = $this->db->prepare($newThisMonthQuery);
            $newThisMonthStmt->execute();
            $newThisMonth = $newThisMonthStmt->fetch(PDO::FETCH_ASSOC)['new_this_month'] ?? 0;
            
            // Get total payroll
            $totalPayroll = 0;
            try {
                $payrollQuery = "SELECT COALESCE(SUM(COALESCE(salary, 0)), 0) as total_payroll FROM hr_employees WHERE status = 'active'";
                $payrollStmt = $this->db->prepare($payrollQuery);
                $payrollStmt->execute();
                $totalPayroll = $payrollStmt->fetch(PDO::FETCH_ASSOC)['total_payroll'] ?? 0;
            } catch (Exception $e) {
                $totalPayroll = 0;
            }
            
            $chartData = $this->getPerformanceChartData('hr', 6);
            $tableData = $this->getHRTableData($status, $sortBy);
            
            // Get additional metrics
            $inactiveEmployees = $totalEmployees - $activeEmployees;
            $avgSalary = $activeEmployees > 0 ? $totalPayroll / $activeEmployees : 0;
            $completionRate = $totalEmployees > 0 ? ($activeEmployees / $totalEmployees) * 100 : 0;
            
            return [
                'stats' => [
                    [
                        'label' => 'Total Employees',
                        'value' => $totalEmployees,
                        'icon' => 'fas fa-users-cog',
                    ],
                    [
                        'label' => 'Active Employees',
                        'value' => $activeEmployees,
                        'icon' => 'fas fa-user-check',
                    ],
                    [
                        'label' => 'Inactive Employees',
                        'value' => $inactiveEmployees,
                        'icon' => 'fas fa-user-times',
                    ],
                    [
                        'label' => 'New This Month',
                        'value' => $newThisMonth,
                        'icon' => 'fas fa-user-plus',
                    ],
                    [
                        'label' => 'Total Payroll',
                        'value' => '$' . number_format($totalPayroll, 2),
                        'icon' => 'fas fa-dollar-sign',
                    ],
                    [
                        'label' => 'Avg Salary',
                        'value' => '$' . number_format($avgSalary, 2),
                        'icon' => 'fas fa-chart-line',
                    ],
                    [
                        'label' => 'Completion Rate',
                        'value' => number_format($completionRate, 1) . '%',
                        'icon' => 'fas fa-percentage',
                    ],
                    [
                        'label' => 'Growth Rate',
                        'value' => $this->calculateGrowthRate('hr'),
                        'icon' => 'fas fa-trending-up',
                    ]
                ],
                'charts' => [
                    'performance' => [
                        'type' => 'line',
                        'data' => $chartData['performance']
                    ],
                    'revenue' => [
                        'type' => 'bar',
                        'data' => $chartData['revenue']
                    ]
                ],
                'tableData' => $tableData
            ];
        } catch (Exception $e) {
            return $this->getDefaultData('hr');
        }
    }
    
    private function getFinancialData($startDate, $endDate, $status, $sortBy) {
        try {
            // Get total transactions
            $totalQuery = "SELECT COUNT(*) as total FROM transactions";
            if ($startDate && $endDate) {
                $totalQuery .= " WHERE DATE(created_at) BETWEEN :start_date AND :end_date";
            }
            $totalStmt = $this->db->prepare($totalQuery);
            if ($startDate && $endDate) {
                $totalStmt->bindParam(':start_date', $startDate);
                $totalStmt->bindParam(':end_date', $endDate);
            }
            $totalStmt->execute();
            $totalTransactions = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get total revenue
            $totalRevenue = 0;
            try {
                $revenueQuery = "SELECT COALESCE(SUM(amount), 0) as total_revenue FROM transactions WHERE type IN ('revenue', 'income', 'agent_commission', 'case_fee') AND status = 'completed'";
                if ($startDate && $endDate) {
                    $revenueQuery .= " AND DATE(created_at) BETWEEN :start_date AND :end_date";
                }
                $revenueStmt = $this->db->prepare($revenueQuery);
                if ($startDate && $endDate) {
                    $revenueStmt->bindParam(':start_date', $startDate);
                    $revenueStmt->bindParam(':end_date', $endDate);
                }
                $revenueStmt->execute();
                $totalRevenue = $revenueStmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;
            } catch (Exception $e) {
                $totalRevenue = 0;
            }
            
            // Get total expenses
            $totalExpenses = 0;
            try {
                $expensesQuery = "SELECT COALESCE(SUM(amount), 0) as total_expenses FROM transactions WHERE type IN ('expense', 'payroll', 'subagent_commission') AND status = 'completed'";
                if ($startDate && $endDate) {
                    $expensesQuery .= " AND DATE(created_at) BETWEEN :start_date AND :end_date";
                }
                $expensesStmt = $this->db->prepare($expensesQuery);
                if ($startDate && $endDate) {
                    $expensesStmt->bindParam(':start_date', $startDate);
                    $expensesStmt->bindParam(':end_date', $endDate);
                }
                $expensesStmt->execute();
                $totalExpenses = $expensesStmt->fetch(PDO::FETCH_ASSOC)['total_expenses'] ?? 0;
            } catch (Exception $e) {
                $totalExpenses = 0;
            }
            
            $netProfit = $totalRevenue - $totalExpenses;
            $chartData = $this->getPerformanceChartData('financial', 6);
            $tableData = $this->getFinancialTableData($status, $sortBy);
            
            // Get additional metrics
            $avgTransaction = $totalTransactions > 0 ? ($totalRevenue + $totalExpenses) / $totalTransactions : 0;
            $profitMargin = $totalRevenue > 0 ? ($netProfit / $totalRevenue) * 100 : 0;
            $expenseRatio = $totalRevenue > 0 ? ($totalExpenses / $totalRevenue) * 100 : 0;
            
            return [
                'stats' => [
                    [
                        'label' => 'Total Transactions',
                        'value' => $totalTransactions,
                        'icon' => 'fas fa-exchange-alt',
                    ],
                    [
                        'label' => 'Total Revenue',
                        'value' => '$' . number_format($totalRevenue, 2),
                        'icon' => 'fas fa-arrow-up',
                    ],
                    [
                        'label' => 'Total Expenses',
                        'value' => '$' . number_format($totalExpenses, 2),
                        'icon' => 'fas fa-arrow-down',
                    ],
                    [
                        'label' => 'Net Profit',
                        'value' => '$' . number_format($netProfit, 2),
                        'icon' => 'fas fa-dollar-sign',
                        'color' => $netProfit >= 0 ? '#4CAF50' : '#f44336'
                    ],
                    [
                        'label' => 'Avg Transaction',
                        'value' => '$' . number_format($avgTransaction, 2),
                        'icon' => 'fas fa-chart-line',
                    ],
                    [
                        'label' => 'Profit Margin',
                        'value' => number_format($profitMargin, 1) . '%',
                        'icon' => 'fas fa-percentage',
                    ],
                    [
                        'label' => 'Expense Ratio',
                        'value' => number_format($expenseRatio, 1) . '%',
                        'icon' => 'fas fa-chart-pie',
                    ],
                    [
                        'label' => 'Growth Rate',
                        'value' => $this->calculateGrowthRate('financial'),
                        'icon' => 'fas fa-trending-up',
                    ]
                ],
                'charts' => [
                    'performance' => [
                        'type' => 'line',
                        'data' => $chartData['performance']
                    ],
                    'revenue' => [
                        'type' => 'bar',
                        'data' => $chartData['revenue']
                    ]
                ],
                'tableData' => $tableData
            ];
        } catch (Exception $e) {
            return $this->getDefaultData('financial');
        }
    }
    
    private function getPerformanceChartData($category, $months) {
        $labels = [];
        $performanceData = [];
        $revenueData = [];
        
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = date('Y-m-01', strtotime("-$i months"));
            $labels[] = date('M', strtotime($date));
            
            $performanceValue = $this->getPerformanceValue($category, $date);
            $performanceData[] = $performanceValue;
            
            $revenueValue = $this->getRevenueValue($category, $date);
            $revenueData[] = $revenueValue;
        }
        
        return [
            'performance' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => ucfirst($category) . ' Performance',
                        'data' => $performanceData,
                        'borderColor' => '#667eea',
                        'backgroundColor' => 'rgba(102, 126, 234, 0.1)',
                        'tension' => 0.4
                    ]
                ]
            ],
            'revenue' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Revenue',
                        'data' => $revenueData,
                        'backgroundColor' => 'rgba(33, 150, 243, 0.8)',
                        'borderColor' => '#2196F3'
                    ]
                ]
            ]
        ];
    }
    
    private function getPerformanceValue($category, $date) {
        $month = date('m', strtotime($date));
        $year = date('Y', strtotime($date));
        
        try {
            $tableMap = [
                'agents' => 'agents',
                'subagents' => 'subagents',
                'workers' => 'workers',
                'cases' => 'cases',
                'hr' => 'hr_employees',
                'financial' => 'transactions'
            ];
            
            $table = $tableMap[$category] ?? 'agents';
            $dateField = ($category === 'hr') ? 'hire_date' : 'created_at';
            
            $query = "SELECT COUNT(*) as count FROM $table WHERE MONTH($dateField) = :month AND YEAR($dateField) = :year";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':month', $month);
            $stmt->bindParam(':year', $year);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['count'] : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getRevenueValue($category, $date) {
        $month = date('m', strtotime($date));
        $year = date('Y', strtotime($date));
        
        try {
            if ($category === 'financial') {
                $query = "SELECT COALESCE(SUM(amount), 0) as revenue FROM transactions WHERE type IN ('revenue', 'income') AND MONTH(created_at) = :month AND YEAR(created_at) = :year";
            } else {
                $tableMap = [
                    'agents' => 'agents',
                    'subagents' => 'subagents',
                    'workers' => 'workers',
                    'cases' => 'cases',
                    'hr' => 'hr_employees'
                ];
                
                $table = $tableMap[$category] ?? 'agents';
                $amountField = ($category === 'hr') ? 'salary' : 'revenue';
                $dateField = ($category === 'hr') ? 'hire_date' : 'created_at';
                
                $query = "SELECT COALESCE(SUM(COALESCE($amountField, 0)), 0) as revenue FROM $table WHERE MONTH($dateField) = :month AND YEAR($dateField) = :year";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':month', $month);
            $stmt->bindParam(':year', $year);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['revenue'] : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getAgentsTableData($status, $sortBy) {
        try {
            $query = "SELECT id, name, status, COALESCE(revenue, 0) as revenue, COALESCE(performance, 0) as performance, created_at as join_date FROM agents";
            
            if ($status !== 'all') {
                $query .= " WHERE status = :status";
            }
            
            switch ($sortBy) {
                case 'name':
                    $query .= " ORDER BY name";
                    break;
                case 'amount':
                    $query .= " ORDER BY revenue DESC";
                    break;
                default:
                    $query .= " ORDER BY created_at DESC";
            }
            
            $stmt = $this->db->prepare($query);
            if ($status !== 'all') {
                $stmt->bindParam(':status', $status);
            }
            $stmt->execute();
            
            $data = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'status' => ucfirst($row['status']),
                    'revenue' => '$' . number_format($row['revenue'], 2),
                    'performance' => $row['performance'] . '%',
                    'joinDate' => date('Y-m-d', strtotime($row['join_date']))
                ];
            }
            
            return $data;
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getSubAgentsTableData($status, $sortBy) {
        try {
            $query = "SELECT id, name, status, COALESCE(commission, 0) as commission, COALESCE(performance, 0) as performance, created_at as join_date FROM subagents";
            
            if ($status !== 'all') {
                $query .= " WHERE status = :status";
            }
            
            switch ($sortBy) {
                case 'name':
                    $query .= " ORDER BY name";
                    break;
                case 'amount':
                    $query .= " ORDER BY commission DESC";
                    break;
                default:
                    $query .= " ORDER BY created_at DESC";
            }
            
            $stmt = $this->db->prepare($query);
            if ($status !== 'all') {
                $stmt->bindParam(':status', $status);
            }
            $stmt->execute();
            
            $data = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'status' => ucfirst($row['status']),
                    'commission' => '$' . number_format($row['commission'], 2),
                    'performance' => $row['performance'] . '%',
                    'joinDate' => date('Y-m-d', strtotime($row['join_date']))
                ];
            }
            
            return $data;
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getWorkersTableData($status, $sortBy) {
        try {
            $query = "SELECT id, name, status, COALESCE(salary, 0) as salary, COALESCE(department, 'N/A') as department, created_at as join_date FROM workers";
            
            if ($status !== 'all') {
                $query .= " WHERE status = :status";
            }
            
            switch ($sortBy) {
                case 'name':
                    $query .= " ORDER BY name";
                    break;
                case 'amount':
                    $query .= " ORDER BY salary DESC";
                    break;
                default:
                    $query .= " ORDER BY created_at DESC";
            }
            
            $stmt = $this->db->prepare($query);
            if ($status !== 'all') {
                $stmt->bindParam(':status', $status);
            }
            $stmt->execute();
            
            $data = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'status' => ucfirst($row['status']),
                    'salary' => '$' . number_format($row['salary'], 2),
                    'department' => $row['department'],
                    'joinDate' => date('Y-m-d', strtotime($row['join_date']))
                ];
            }
            
            return $data;
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getCasesTableData($status, $sortBy) {
        try {
            $query = "SELECT id, case_number, status, COALESCE(client_name, 'N/A') as client, COALESCE(agent_name, 'N/A') as agent, created_at FROM cases";
            
            if ($status !== 'all') {
                $query .= " WHERE status = :status";
            }
            
            switch ($sortBy) {
                case 'name':
                    $query .= " ORDER BY case_number";
                    break;
                case 'amount':
                    $query .= " ORDER BY amount DESC";
                    break;
                default:
                    $query .= " ORDER BY created_at DESC";
            }
            
            $stmt = $this->db->prepare($query);
            if ($status !== 'all') {
                $stmt->bindParam(':status', $status);
            }
            $stmt->execute();
            
            $data = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data[] = [
                    'id' => $row['id'],
                    'caseNumber' => $row['case_number'],
                    'status' => ucfirst($row['status']),
                    'client' => $row['client'],
                    'agent' => $row['agent'],
                    'createdDate' => date('Y-m-d', strtotime($row['created_at']))
                ];
            }
            
            return $data;
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getHRTableData($status, $sortBy) {
        try {
            $query = "SELECT id, name, COALESCE(department, 'N/A') as department, COALESCE(position, 'N/A') as position, status, hire_date FROM hr_employees";
            
            if ($status !== 'all') {
                $query .= " WHERE status = :status";
            }
            
            switch ($sortBy) {
                case 'name':
                    $query .= " ORDER BY name";
                    break;
                case 'amount':
                    $query .= " ORDER BY salary DESC";
                    break;
                default:
                    $query .= " ORDER BY hire_date DESC";
            }
            
            $stmt = $this->db->prepare($query);
            if ($status !== 'all') {
                $stmt->bindParam(':status', $status);
            }
            $stmt->execute();
            
            $data = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data[] = [
                    'id' => $row['id'],
                    'employee' => $row['name'],
                    'department' => $row['department'],
                    'position' => $row['position'],
                    'status' => ucfirst($row['status']),
                    'hireDate' => date('Y-m-d', strtotime($row['hire_date']))
                ];
            }
            
            return $data;
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function getFinancialTableData($status, $sortBy) {
        try {
            $query = "SELECT id, description as transaction, amount, type, status, created_at as date FROM transactions";
            
            if ($status !== 'all') {
                $query .= " WHERE status = :status";
            }
            
            switch ($sortBy) {
                case 'name':
                    $query .= " ORDER BY description";
                    break;
                case 'amount':
                    $query .= " ORDER BY amount DESC";
                    break;
                default:
                    $query .= " ORDER BY created_at DESC";
            }
            
            $stmt = $this->db->prepare($query);
            if ($status !== 'all') {
                $stmt->bindParam(':status', $status);
            }
            $stmt->execute();
            
            $data = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data[] = [
                    'id' => $row['id'],
                    'transaction' => $row['transaction'],
                    'amount' => '$' . number_format($row['amount'], 2),
                    'type' => ucfirst($row['type']),
                    'date' => date('Y-m-d', strtotime($row['date'])),
                    'status' => ucfirst($row['status'])
                ];
            }
            
            return $data;
        } catch (Exception $e) {
            return [];
        }
    }
    
    private function calculateGrowthRate($category) {
        try {
            $currentMonth = date('Y-m-01');
            $lastMonth = date('Y-m-01', strtotime('-1 month'));
            
            $tableMap = [
                'agents' => 'agents',
                'subagents' => 'subagents',
                'workers' => 'workers',
                'cases' => 'cases',
                'hr' => 'hr_employees',
                'financial' => 'transactions'
            ];
            
            $table = $tableMap[$category] ?? 'agents';
            $dateField = ($category === 'hr') ? 'hire_date' : 'created_at';
            
            // Current month count
            $currentQuery = "SELECT COUNT(*) as count FROM $table WHERE DATE($dateField) >= :current_month";
            $currentStmt = $this->db->prepare($currentQuery);
            $currentStmt->bindParam(':current_month', $currentMonth);
            $currentStmt->execute();
            $currentCount = $currentStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            // Last month count
            $lastQuery = "SELECT COUNT(*) as count FROM $table WHERE DATE($dateField) >= :last_month AND DATE($dateField) < :current_month";
            $lastStmt = $this->db->prepare($lastQuery);
            $lastStmt->bindParam(':last_month', $lastMonth);
            $lastStmt->bindParam(':current_month', $currentMonth);
            $lastStmt->execute();
            $lastCount = $lastStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            if ($lastCount == 0) {
                return $currentCount > 0 ? '+100%' : '0%';
            }
            
            $growthRate = (($currentCount - $lastCount) / $lastCount) * 100;
            $sign = $growthRate >= 0 ? '+' : '';
            return $sign . number_format($growthRate, 1) . '%';
            
        } catch (Exception $e) {
            return '0%';
        }
    }
    
    private function getDefaultData($category) {
        $defaultStats = [
            'agents' => [
                ['label' => 'Total Agents', 'value' => 0, 'icon' => 'fas fa-user-tie', 'color' => '#667eea'],
                ['label' => 'Active Agents', 'value' => 0, 'icon' => 'fas fa-user-check', 'color' => '#4CAF50'],
                ['label' => 'Inactive Agents', 'value' => 0, 'icon' => 'fas fa-user-times', 'color' => '#f44336'],
                ['label' => 'New This Month', 'value' => 0, 'icon' => 'fas fa-user-plus', 'color' => '#FF9800'],
                ['label' => 'Total Revenue', 'value' => '$0.00', 'icon' => 'fas fa-dollar-sign', 'color' => '#2196F3'],
                ['label' => 'Avg Revenue/Agent', 'value' => '$0.00', 'icon' => 'fas fa-chart-line', 'color' => '#9C27B0'],
                ['label' => 'Completion Rate', 'value' => '0.0%', 'icon' => 'fas fa-percentage', 'color' => '#00BCD4'],
                ['label' => 'Growth Rate', 'value' => '0%', 'icon' => 'fas fa-trending-up', 'color' => '#4CAF50']
            ],
            'subagents' => [
                ['label' => 'Total SubAgents', 'value' => 0, 'icon' => 'fas fa-users', 'color' => '#667eea'],
                ['label' => 'Active SubAgents', 'value' => 0, 'icon' => 'fas fa-user-check', 'color' => '#4CAF50'],
                ['label' => 'Inactive SubAgents', 'value' => 0, 'icon' => 'fas fa-user-times', 'color' => '#f44336'],
                ['label' => 'New This Month', 'value' => 0, 'icon' => 'fas fa-user-plus', 'color' => '#FF9800'],
                ['label' => 'Total Commission', 'value' => '$0.00', 'icon' => 'fas fa-dollar-sign', 'color' => '#2196F3'],
                ['label' => 'Avg Commission', 'value' => '$0.00', 'icon' => 'fas fa-chart-line', 'color' => '#9C27B0'],
                ['label' => 'Completion Rate', 'value' => '0.0%', 'icon' => 'fas fa-percentage', 'color' => '#00BCD4'],
                ['label' => 'Growth Rate', 'value' => '0%', 'icon' => 'fas fa-trending-up', 'color' => '#4CAF50']
            ],
            'workers' => [
                ['label' => 'Total Workers', 'value' => 0, 'icon' => 'fas fa-hard-hat', 'color' => '#667eea'],
                ['label' => 'Active Workers', 'value' => 0, 'icon' => 'fas fa-user-check', 'color' => '#4CAF50'],
                ['label' => 'Inactive Workers', 'value' => 0, 'icon' => 'fas fa-user-times', 'color' => '#f44336'],
                ['label' => 'New This Month', 'value' => 0, 'icon' => 'fas fa-user-plus', 'color' => '#FF9800'],
                ['label' => 'Total Payroll', 'value' => '$0.00', 'icon' => 'fas fa-dollar-sign', 'color' => '#2196F3'],
                ['label' => 'Avg Salary', 'value' => '$0.00', 'icon' => 'fas fa-chart-line', 'color' => '#9C27B0'],
                ['label' => 'Completion Rate', 'value' => '0.0%', 'icon' => 'fas fa-percentage', 'color' => '#00BCD4'],
                ['label' => 'Growth Rate', 'value' => '0%', 'icon' => 'fas fa-trending-up', 'color' => '#4CAF50']
            ],
            'cases' => [
                ['label' => 'Total Cases', 'value' => 0, 'icon' => 'fas fa-briefcase', 'color' => '#667eea'],
                ['label' => 'Active Cases', 'value' => 0, 'icon' => 'fas fa-briefcase', 'color' => '#4CAF50'],
                ['label' => 'Completed Cases', 'value' => 0, 'icon' => 'fas fa-check-circle', 'color' => '#00BCD4'],
                ['label' => 'New This Month', 'value' => 0, 'icon' => 'fas fa-plus', 'color' => '#FF9800'],
                ['label' => 'Total Revenue', 'value' => '$0.00', 'icon' => 'fas fa-dollar-sign', 'color' => '#2196F3'],
                ['label' => 'Avg Revenue/Case', 'value' => '$0.00', 'icon' => 'fas fa-chart-line', 'color' => '#9C27B0'],
                ['label' => 'Completion Rate', 'value' => '0.0%', 'icon' => 'fas fa-percentage', 'color' => '#00BCD4'],
                ['label' => 'Growth Rate', 'value' => '0%', 'icon' => 'fas fa-trending-up', 'color' => '#4CAF50']
            ],
            'hr' => [
                ['label' => 'Total Employees', 'value' => 0, 'icon' => 'fas fa-users-cog', 'color' => '#667eea'],
                ['label' => 'Active Employees', 'value' => 0, 'icon' => 'fas fa-user-check', 'color' => '#4CAF50'],
                ['label' => 'Inactive Employees', 'value' => 0, 'icon' => 'fas fa-user-times', 'color' => '#f44336'],
                ['label' => 'New This Month', 'value' => 0, 'icon' => 'fas fa-user-plus', 'color' => '#FF9800'],
                ['label' => 'Total Payroll', 'value' => '$0.00', 'icon' => 'fas fa-dollar-sign', 'color' => '#2196F3'],
                ['label' => 'Avg Salary', 'value' => '$0.00', 'icon' => 'fas fa-chart-line', 'color' => '#9C27B0'],
                ['label' => 'Completion Rate', 'value' => '0.0%', 'icon' => 'fas fa-percentage', 'color' => '#00BCD4'],
                ['label' => 'Growth Rate', 'value' => '0%', 'icon' => 'fas fa-trending-up', 'color' => '#4CAF50']
            ],
            'financial' => [
                ['label' => 'Total Transactions', 'value' => 0, 'icon' => 'fas fa-exchange-alt', 'color' => '#667eea'],
                ['label' => 'Total Revenue', 'value' => '$0.00', 'icon' => 'fas fa-arrow-up', 'color' => '#4CAF50'],
                ['label' => 'Total Expenses', 'value' => '$0.00', 'icon' => 'fas fa-arrow-down', 'color' => '#f44336'],
                ['label' => 'Net Profit', 'value' => '$0.00', 'icon' => 'fas fa-dollar-sign', 'color' => '#4CAF50'],
                ['label' => 'Avg Transaction', 'value' => '$0.00', 'icon' => 'fas fa-chart-line', 'color' => '#9C27B0'],
                ['label' => 'Profit Margin', 'value' => '0.0%', 'icon' => 'fas fa-percentage', 'color' => '#00BCD4'],
                ['label' => 'Expense Ratio', 'value' => '0.0%', 'icon' => 'fas fa-chart-pie', 'color' => '#FF9800'],
                ['label' => 'Growth Rate', 'value' => '0%', 'icon' => 'fas fa-trending-up', 'color' => '#4CAF50']
            ]
        ];
        
        return [
            'stats' => $defaultStats[$category] ?? $defaultStats['agents'],
            'charts' => [
                'performance' => [
                    'type' => 'line',
                    'data' => [
                        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        'datasets' => [
                            [
                                'label' => ucfirst($category) . ' Performance',
                                'data' => [0, 0, 0, 0, 0, 0],
                                'borderColor' => '#667eea',
                                'backgroundColor' => 'rgba(102, 126, 234, 0.1)',
                                'tension' => 0.4
                            ]
                        ]
                    ]
                ],
                'revenue' => [
                    'type' => 'bar',
                    'data' => [
                        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                        'datasets' => [
                            [
                                'label' => 'Revenue',
                                'data' => [0, 0, 0, 0, 0, 0],
                                'backgroundColor' => 'rgba(33, 150, 243, 0.8)',
                                'borderColor' => '#2196F3'
                            ]
                        ]
                    ]
                ]
            ],
            'tableData' => []
        ];
    }
    
    private function sendSuccess($data) {
        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    }
    
    private function sendError($message) {
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
    }
}

// Initialize and handle the request
$api = new ReportsAPI();
$api->handleRequest();
?>