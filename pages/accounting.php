<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/accounting.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/accounting.php`.
 */
require_once '../includes/config.php';
require_once '../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// Check if user has permission to view accounting
if (!hasPermission('view_chart_accounts')) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$pageTitle = "Professional Accounting System";
$pageCss = [
    asset('css/accounting/professional.css') . "?v=" . time()
];
$pageJs = [
    "https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"
];
$pageCss[] = "https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css";
$pageCss[] = "https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css";
$pageJs[] = "https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js";

include '../includes/header.php';
?>

<div class="accounting-container">
    <!-- Header -->
    <div class="accounting-header">
        <div class="header-left">
            <h1><i class="fas fa-calculator"></i> Professional Accounting System</h1>
        </div>
        <div class="header-right">
            <button class="btn btn-secondary" data-action="refresh-dashboard" data-permission="view_chart_accounts">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <div class="accounting-layout">
        <!-- Main Content Area -->
        <div class="accounting-main-content">
            <!-- Main Content Tabs (Control Panel only - other navigation moved to top nav) -->
            <div class="accounting-tabs">
                 <button class="tab-btn active" data-tab="dashboard" data-permission="view_chart_accounts">
                     <i class="fas fa-tachometer-alt"></i> Control Panel
                 </button>
            </div>

            <!-- Dashboard Tab Content -->
            <div id="dashboardTab" class="tab-content active dashboard-tab-content">
                <!-- Financial Overview Cards -->
                <div class="overview-grid" id="financialOverview">
                    <div class="overview-card revenue">
                        <div class="card-icon">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="card-content">
                            <h3 id="totalRevenue">SAR 0.00</h3>
                            <p>Total Revenue</p>
                            <span class="card-change positive" id="revenueChange">+0%</span>
                        </div>
                    </div>
                    <div class="overview-card expense">
                        <div class="card-icon">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="card-content">
                            <h3 id="totalExpense">SAR 0.00</h3>
                            <p>Total Expenses</p>
                            <span class="card-change negative" id="expenseChange">+0%</span>
                        </div>
                    </div>
                    <div class="overview-card profit">
                        <div class="card-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="card-content">
                            <h3 id="netProfit">SAR 0.00</h3>
                            <p>Net Profit</p>
                            <span class="card-change" id="profitChange">+0%</span>
                        </div>
                    </div>
                    <div class="overview-card balance">
                        <div class="card-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="card-content">
                            <h3 id="cashBalance">SAR 0.00</h3>
                            <p>Cash Balance</p>
                            <span class="card-change" id="balanceChange">+0%</span>
                        </div>
                    </div>
                    <div class="overview-card receivables">
                        <div class="card-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <div class="card-content">
                            <h3 id="totalReceivables">SAR 0.00</h3>
                            <p>Accounts Receivable</p>
                            <span class="card-badge" id="receivablesCount">0 invoices</span>
                        </div>
                    </div>
                    <div class="overview-card payables">
                        <div class="card-icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="card-content">
                            <h3 id="totalPayables">SAR 0.00</h3>
                            <p>Accounts Payable</p>
                            <span class="card-badge" id="payablesCount">0 bills</span>
                        </div>
                    </div>
                </div>

                <!-- Top Navigation Bar -->
                <nav class="accounting-top-nav">
                    <ul class="top-nav-menu">
                        <!-- Control Panel (Dashboard) -->
                        <li class="top-nav-item">
                            <a href="#" class="top-nav-link" data-tab="dashboard" data-permission="view_chart_accounts">
                                <i class="fas fa-tachometer-alt"></i>
                                <span>Control Panel</span>
                            </a>
                        </li>
                        <!-- Chart of Accounts -->
                        <li class="top-nav-item">
                            <a href="#" class="top-nav-link" data-tab="chart-of-accounts" data-permission="view_chart_accounts">
                                <i class="fas fa-sitemap"></i>
                                <span>Chart of Accounts</span>
                            </a>
                        </li>
                        <!-- Cost Centers -->
                        <li class="top-nav-item">
                            <a href="#" class="top-nav-link" data-tab="cost-centers" data-permission="view_chart_accounts">
                                <i class="fas fa-building"></i>
                                <span>Cost Centers</span>
                            </a>
                        </li>
                        <!-- Letters of Bank Guarantee -->
                        <li class="top-nav-item">
                            <a href="#" class="top-nav-link" data-tab="bank-guarantee" data-permission="view_bank_accounts">
                                <i class="fas fa-shield-alt"></i>
                                <span>Letters of Bank Guarantee</span>
                            </a>
                        </li>
                        <!-- Support Payments -->
                        <li class="top-nav-item">
                            <a href="#" class="top-nav-link" data-tab="support-payments" data-permission="view_payment_vouchers">
                                <i class="fas fa-hand-holding-usd"></i>
                                <span>Support Payments</span>
                            </a>
                        </li>
                        <!-- Journal Entries -->
                        <li class="top-nav-item">
                            <a href="#" class="top-nav-link" data-tab="journal-entries" data-permission="view_journal_entries">
                                <i class="fas fa-book"></i>
                                <span>Journal Entries</span>
                            </a>
                        </li>
                        <!-- Expenses -->
                        <li class="top-nav-item">
                            <a href="#" class="top-nav-link" data-tab="expenses" data-permission="view_journal_entries">
                                <i class="fas fa-arrow-down"></i>
                                <span>Expenses</span>
                            </a>
                        </li>
                        <!-- Receipts -->
                        <li class="top-nav-item">
                            <a href="#" class="top-nav-link" data-tab="receipts" data-permission="view_receipt_vouchers">
                                <i class="fas fa-receipt"></i>
                                <span>Receipts</span>
                            </a>
                        </li>
                        <!-- Disbursement Vouchers -->
                        <li class="top-nav-item">
                            <a href="#" class="top-nav-link" data-tab="disbursement-vouchers" data-permission="view_payment_vouchers">
                                <i class="fas fa-file-invoice"></i>
                                <span>Disbursement Vouchers</span>
                            </a>
                        </li>
                        <!-- Electronic Invoice List -->
                        <li class="top-nav-item">
                            <a href="#" class="top-nav-link" data-tab="electronic-invoices" data-permission="view_receivables,view_payables">
                                <i class="fas fa-file-invoice-dollar"></i>
                                <span>Electronic Invoice List</span>
                            </a>
                        </li>
                        <!-- Entry Approval -->
                        <li class="top-nav-item">
                            <a href="#" class="top-nav-link" data-tab="entry-approval" data-permission="view_journal_entries">
                                <i class="fas fa-check-circle"></i>
                                <span>Entry Approval</span>
                            </a>
                        </li>
                        <!-- Bank Reconciliation -->
                        <li class="top-nav-item">
                            <a href="#" class="top-nav-link" data-tab="bank-reconciliation" data-permission="view_bank_accounts">
                                <i class="fas fa-balance-scale"></i>
                                <span>Bank Reconciliation</span>
                            </a>
                        </li>
                        <!-- Financial Reports -->
                        <li class="top-nav-item">
                            <a href="#" class="top-nav-link" data-tab="financial-reports" data-permission="view_chart_accounts">
                                <i class="fas fa-chart-bar"></i>
                                <span>Financial Reports</span>
                            </a>
                        </li>
                    </ul>
                </nav>

                <!-- Dashboard Widgets Grid -->
                <div class="dashboard-grid">
                    <!-- Recent Transactions Widget -->
                    <div class="dashboard-widget transactions-widget">
                        <div class="widget-header">
                            <h3><i class="fas fa-history"></i> Recent Transactions</h3>
                        </div>
                        <div class="widget-content">
                            <div id="recentTransactionsLoading" class="loading-state">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Loading transactions...</p>
                            </div>
                            <div class="recent-transactions-container" id="recentTransactionsContainer">
                                <table class="dashboard-table" id="recentTransactionsTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th>Debit</th>
                                            <th>Credit</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentTransactions">
                                        <!-- Transactions will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Widget -->
                    <div class="dashboard-widget quick-actions-widget">
                        <div class="widget-header">
                            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                        </div>
                        <div class="widget-content">
                            <div class="quick-actions-grid">
                                <button class="quick-action-btn" data-action="quick-entry" data-permission="add_journal_entry,view_journal_entries">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>New Entry</span>
                                </button>
                                <button class="quick-action-btn" data-action="open-receivables-modal" data-permission="view_receivables">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                    <span>New Invoice</span>
                                </button>
                                <button class="quick-action-btn" data-action="open-payment-voucher" data-permission="view_payment_vouchers">
                                    <i class="fas fa-money-check-alt"></i>
                                    <span>Payment</span>
                                </button>
                                <button class="quick-action-btn" data-action="open-receipt-voucher" data-permission="view_receipt_vouchers">
                                    <i class="fas fa-receipt"></i>
                                    <span>Receipt</span>
                                </button>
                                <button class="quick-action-btn" data-action="open-reports-modal" data-permission="view_chart_accounts">
                                    <i class="fas fa-chart-bar"></i>
                                    <span>Reports</span>
                                </button>
                                <button class="quick-action-btn" data-action="open-settings-modal" data-permission="view_chart_accounts">
                                    <i class="fas fa-cog"></i>
                                    <span>Settings</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Cash Flow Summary Widget -->
                    <div class="dashboard-widget cashflow-widget">
                        <div class="widget-header">
                            <h3><i class="fas fa-exchange-alt"></i> Cash Flow Summary</h3>
                        </div>
                        <div class="widget-content">
                            <div class="cashflow-stats">
                                <div class="cashflow-item">
                                    <div class="cashflow-label">Cash In</div>
                                    <div class="cashflow-value positive" id="cashInAmount">SAR 0.00</div>
                                    <div class="cashflow-period">This Month</div>
                                </div>
                                <div class="cashflow-item">
                                    <div class="cashflow-label">Cash Out</div>
                                    <div class="cashflow-value negative" id="cashOutAmount">SAR 0.00</div>
                                    <div class="cashflow-period">This Month</div>
                                </div>
                                <div class="cashflow-item">
                                    <div class="cashflow-label">Net Flow</div>
                                    <div class="cashflow-value" id="netFlowAmount">SAR 0.00</div>
                                    <div class="cashflow-period">This Month</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Summary Widget -->
                    <div class="dashboard-widget summary-widget">
                        <div class="widget-header">
                            <h3><i class="fas fa-chart-pie"></i> Financial Summary</h3>
                        </div>
                        <div class="widget-content">
                            <div class="summary-stats">
                                <div class="summary-item">
                                    <div class="summary-label">Total Assets</div>
                                    <div class="summary-value" id="totalAssets">SAR 0.00</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Total Liabilities</div>
                                    <div class="summary-value" id="totalLiabilities">SAR 0.00</div>
                                </div>
                                <div class="summary-item">
                                    <div class="summary-label">Equity</div>
                                    <div class="summary-value" id="totalEquity">SAR 0.00</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chart 1: Revenue vs Expenses vs Net Profit -->
                    <div class="dashboard-widget chart-widget">
                        <div class="widget-header">
                            <h3><i class="fas fa-chart-line"></i> Revenue vs Expenses vs Net Profit</h3>
                            <select id="revenueExpenseNetPeriod" class="period-select">
                                <option value="7">Last 7 Days</option>
                                <option value="30" selected>Last 30 Days</option>
                                <option value="90">Last 90 Days</option>
                                <option value="365">Last Year</option>
                            </select>
                        </div>
                        <div class="widget-content">
                            <canvas id="revenueExpenseNetChart"></canvas>
                        </div>
                    </div>

                    <!-- Chart 2: Cash Balance Trend -->
                    <div class="dashboard-widget chart-widget">
                        <div class="widget-header">
                            <h3><i class="fas fa-chart-area"></i> Cash Balance Trend</h3>
                            <select id="cashBalancePeriod" class="period-select">
                                <option value="7">Last 7 Days</option>
                                <option value="30" selected>Last 30 Days</option>
                                <option value="90">Last 90 Days</option>
                                <option value="365">Last Year</option>
                            </select>
                        </div>
                        <div class="widget-content">
                            <canvas id="cashBalanceChart"></canvas>
                        </div>
                    </div>

                    <!-- Chart 3: Accounts Receivable vs Payable -->
                    <div class="dashboard-widget chart-widget">
                        <div class="widget-header">
                            <h3><i class="fas fa-chart-bar"></i> Accounts Receivable vs Payable</h3>
                        </div>
                        <div class="widget-content">
                            <canvas id="receivablePayableChart"></canvas>
                        </div>
                    </div>

                    <!-- Chart 4: Expense Breakdown -->
                    <div class="dashboard-widget chart-widget">
                        <div class="widget-header">
                            <h3><i class="fas fa-chart-bar"></i> Expense Breakdown</h3>
                        </div>
                        <div class="widget-content">
                            <canvas id="expenseBreakdownChart"></canvas>
                        </div>
                    </div>

                    <!-- Chart 5: Invoice Aging -->
                    <div class="dashboard-widget chart-widget">
                        <div class="widget-header">
                            <h3><i class="fas fa-chart-pie"></i> Invoice Aging</h3>
                        </div>
                        <div class="widget-content">
                            <canvas id="invoiceAgingChart"></canvas>
                        </div>
                    </div>

                    <!-- Chart 6: Financial Overview (Donut) -->
                    <div class="dashboard-widget chart-widget">
                        <div class="widget-header">
                            <h3><i class="fas fa-chart-pie"></i> Financial Overview</h3>
                        </div>
                        <div class="widget-content">
                            <canvas id="financialOverviewChart"></canvas>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Chart of Accounts Tab Content - Opens in modal only -->
            <div id="chartOfAccountsTab" class="tab-content accounting-tab-hidden">
            </div>

            <!-- Cost Centers Tab Content - Opens in modal only -->
            <div id="costCentersTab" class="tab-content accounting-tab-hidden">
            </div>

            <!-- Bank Guarantee Tab Content - Opens in modal only -->
            <div id="bankGuaranteeTab" class="tab-content accounting-tab-hidden">
            </div>

            <!-- Support Payments Tab Content - Opens in modal only -->
            <div id="supportPaymentsTab" class="tab-content accounting-tab-hidden">
            </div>

            <!-- Journal Entries Tab Content - Opens in modal only -->
            <div id="journalEntriesTab" class="tab-content accounting-tab-hidden">
            </div>

            <!-- Expenses Tab Content - Opens in modal only -->
            <div id="expensesTab" class="tab-content accounting-tab-hidden">
            </div>

            <!-- Receipts Tab Content - Opens in modal only -->
            <div id="receiptsTab" class="tab-content accounting-tab-hidden">
            </div>

            <!-- Disbursement Vouchers Tab Content - Opens in modal only -->
            <div id="disbursementVouchersTab" class="tab-content accounting-tab-hidden">
            </div>

            <!-- Electronic Invoices Tab Content - Opens in modal only -->
            <div id="electronicInvoicesTab" class="tab-content accounting-tab-hidden">
            </div>

            <!-- Entry Approval Tab Content - Opens in modal only -->
            <div id="entryApprovalTab" class="tab-content accounting-tab-hidden">
            </div>

            <!-- Bank Reconciliation Tab Content - Opens in modal only -->
            <div id="bankReconciliationTab" class="tab-content accounting-tab-hidden">
            </div>
        </div>
    </div>
</div>

<!-- Transactions Modal -->
<div id="transactionsModal" class="accounting-modal accounting-modal-overlay accounting-modal-hidden">
    <div class="accounting-modal-content transactions-modal">
        <div class="modal-header">
            <h2><i class="fas fa-table"></i> Transaction History</h2>
            <button class="modal-close" data-action="close-transactions-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <!-- Status Cards -->
            <div class="transactions-status-cards" id="transactionsStatusCards">
                <div class="status-card">
                    <div class="status-icon"><i class="fas fa-list"></i></div>
                    <div class="status-content">
                        <h4 id="totalEntriesCount">0</h4>
                        <p>Total Entries</p>
                    </div>
                </div>
                <div class="status-card">
                    <div class="status-icon"><i class="fas fa-arrow-up"></i></div>
                    <div class="status-content">
                        <h4 id="totalIncomeAmount">SAR 0.00</h4>
                        <p>Total Income</p>
                    </div>
                </div>
                <div class="status-card">
                    <div class="status-icon"><i class="fas fa-arrow-down"></i></div>
                    <div class="status-content">
                        <h4 id="totalExpenseAmount">SAR 0.00</h4>
                        <p>Total Expenses</p>
                    </div>
                </div>
                <div class="status-card">
                    <div class="status-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="status-content">
                        <h4 id="postedCount">0</h4>
                        <p>Posted</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="modal-filters">
                <div class="filter-group">
                    <label for="modalEntityTypeFilter">Entity Type</label>
                    <select id="modalEntityTypeFilter" class="filter-select">
                        <option value="">All Entities</option>
                        <option value="agents">Agents</option>
                        <option value="subagents">Subagents</option>
                        <option value="workers">Workers</option>
                        <option value="hr">HR</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="modalEntityFilter">Entity</label>
                    <select id="modalEntityFilter" class="filter-select">
                        <option value="">All</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="entriesPerPage">Show entries</label>
                    <select id="entriesPerPage" class="filter-select">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                </div>
                <div id="topPaginationInfo" class="pagination-info filter-pagination-info"></div>
                <div class="filter-actions">
                    <button class="btn btn-secondary" data-action="refresh-modal-transactions">Refresh</button>
                </div>
            </div>

            <!-- Top Pagination -->
            <div class="modal-pagination" id="topPagination">
                <div class="pagination-wrapper">
                    <button class="btn btn-secondary btn-xs pagination-btn" id="topFirstPage" data-action="first-page">First</button>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="topPrevPage" data-action="prev-page">Previous</button>
                    <div id="topPageNumbers" class="page-numbers"></div>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="topNextPage" data-action="next-page">Next</button>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="topLastPage" data-action="last-page">Last</button>
                </div>
            </div>

            <!-- Table -->
            <div class="modal-table-container">
                <div class="modal-table-wrapper">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    image.png                                    <th>Account</th>
                                    <th>Debit</th>
                                    <th>Credit</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="accountingTransactionsTable">
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <div class="accounting-empty-state">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            <p class="accounting-empty-state-text">Loading transactions...</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Pagination -->
            <div class="modal-pagination modal-pagination-center" id="centerPagination">
                <div class="pagination-wrapper">
                    <button class="btn btn-secondary btn-xs pagination-btn" id="centerFirstPage" data-action="first-page">First</button>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="centerPrevPage" data-action="prev-page">Previous</button>
                    <div id="centerPageNumbers" class="page-numbers"></div>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="centerNextPage" data-action="next-page">Next</button>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="centerLastPage" data-action="last-page">Last</button>
                </div>
            </div>

            <!-- Bottom Pagination -->
            <div class="modal-pagination" id="pagination">
                <div class="pagination-wrapper">
                    <button class="btn btn-secondary btn-xs pagination-btn" id="firstPage" data-action="first-page">First</button>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="prevPage" data-action="prev-page">Previous</button>
                    <div id="pageNumbers" class="page-numbers"></div>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="nextPage" data-action="next-page">Next</button>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="lastPage" data-action="last-page">Last</button>
                </div>
                <div id="paginationInfo" class="pagination-info"></div>
            </div>

        </div>
    </div>
</div>

<!-- Follow-up Modal -->
<div id="followupModal" class="accounting-modal accounting-modal-overlay accounting-modal-hidden">
    <div class="accounting-modal-content followup-modal">
        <div class="modal-header">
            <h2><i class="fas fa-tasks"></i> Follow-up Tasks</h2>
            <button class="modal-close" data-action="close-followup-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <!-- Filters and Actions -->
            <div class="filters-bar filters-bar-compact">
                <div class="filter-group filter-group-compact">
                    <label>Status:</label>
                    <select id="followupStatusFilter" class="filter-select filter-select-compact">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="filter-group filter-group-compact">
                    <label>Priority:</label>
                    <select id="followupPriorityFilter" class="filter-select filter-select-compact">
                        <option value="">All</option>
                        <option value="urgent">Urgent</option>
                        <option value="high">High</option>
                        <option value="medium">Medium</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="filter-group filter-group-compact">
                    <label>Type:</label>
                    <select id="followupTypeFilter" class="filter-select filter-select-compact">
                        <option value="">All Types</option>
                        <option value="transaction">Transaction</option>
                        <option value="invoice">Invoice</option>
                        <option value="bill">Bill</option>
                        <option value="journal_entry">Journal Entry</option>
                        <option value="account">Account</option>
                    </select>
                </div>
                <div class="filter-group filter-group-compact">
                    <label>Show entries:</label>
                    <select id="followupEntriesPerPage" class="filter-select filter-select-compact">
                        <option value="5" selected>5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <button class="btn btn-primary btn-sm" data-action="new-followup">
                    <i class="fas fa-plus"></i> New Follow-up
                </button>
                <button class="btn btn-secondary btn-sm" data-action="refresh-followups">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>

            <!-- Bulk Actions Bar -->
            <div class="bulk-actions-bar bulk-actions-bar-hidden" id="followupsBulkActions">
                <div class="bulk-actions-info">
                    <span id="followupsSelectedCount">0</span> selected
                </div>
                <div class="bulk-action-buttons">
                    <button class="btn btn-sm btn-danger" data-action="bulk-delete-followups">
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                    <button class="btn btn-sm btn-secondary" data-action="bulk-export-followups">
                        <i class="fas fa-download"></i> Export Selected
                    </button>
                    <button class="btn btn-sm btn-primary" data-action="bulk-print-followups">
                        <i class="fas fa-print"></i> Print Selected
                    </button>
                </div>
            </div>

            <!-- Pagination Info -->
            <div id="followupPaginationInfo" class="pagination-info"></div>

            <!-- Follow-ups List -->
            <div class="followups-list" id="followupsList">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading follow-ups...</p>
                </div>
            </div>

            <!-- Pagination -->
            <div class="modal-pagination" id="followupPagination">
                <div class="pagination-wrapper">
                    <button class="btn btn-secondary btn-xs pagination-btn" id="followupFirstPage" data-action="followup-first-page">First</button>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="followupPrevPage" data-action="followup-prev-page">Previous</button>
                    <div id="followupPageNumbers" class="page-numbers"></div>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="followupNextPage" data-action="followup-next-page">Next</button>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="followupLastPage" data-action="followup-last-page">Last</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Messages Modal -->
<div id="messagesModal" class="accounting-modal accounting-modal-overlay accounting-modal-hidden">
    <div class="accounting-modal-content messages-modal">
        <div class="modal-header">
            <h2>
                <i class="fas fa-envelope"></i> Messages & Notifications
                <span id="unreadMessagesBadge" class="badge badge-danger unread-badge">0</span>
            </h2>
            <div class="header-actions">
                <button class="btn btn-primary btn-sm" data-action="new-message">
                    <i class="fas fa-plus"></i> New Message
                </button>
                <button class="btn btn-secondary btn-sm" data-action="mark-all-read">
                    <i class="fas fa-check-double"></i> Mark All Read
                </button>
                <button class="modal-close" data-action="close-messages-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="modal-body">
            <!-- Filters -->
            <div class="filters-bar filters-bar-compact">
                <div class="filter-group filter-group-compact">
                    <label>Type:</label>
                    <select id="messageTypeFilter" class="filter-select filter-select-compact">
                        <option value="">All</option>
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                        <option value="success">Success</option>
                        <option value="alert">Alert</option>
                    </select>
                </div>
                <div class="filter-group filter-group-compact">
                    <label for="messageCategoryFilter">Category:</label>
                    <select id="messageCategoryFilter" class="filter-select filter-select-compact">
                        <option value="">All Categories</option>
                        <option value="overdue_invoice">Overdue Invoices</option>
                        <option value="low_balance">Low Balance</option>
                        <option value="transaction_alert">Transaction Alerts</option>
                        <option value="system_notification">System Notifications</option>
                    </select>
                </div>
                <div class="filter-group filter-group-compact">
                    <label for="messageEntriesPerPage">Show entries:</label>
                    <select id="messageEntriesPerPage" class="filter-select filter-select-compact">
                        <option value="5" selected>5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="filter-group filter-group-compact">
                    <label>
                        <input type="checkbox" id="unreadOnlyFilter" class="filter-checkbox">
                        Unread Only
                    </label>
                </div>
                <button class="btn btn-secondary btn-sm" data-action="refresh-messages">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>

            <!-- Bulk Actions Bar -->
            <div class="bulk-actions-bar bulk-actions-bar-hidden" id="messagesBulkActions">
                <div class="bulk-actions-info">
                    <span id="messagesSelectedCount">0</span> selected
                </div>
                <div class="bulk-action-buttons">
                    <button class="btn btn-sm btn-danger" data-action="bulk-delete-messages">
                        <i class="fas fa-trash"></i> Delete Selected
                    </button>
                    <button class="btn btn-sm btn-secondary" data-action="bulk-export-messages">
                        <i class="fas fa-download"></i> Export Selected
                    </button>
                    <button class="btn btn-sm btn-primary" data-action="bulk-print-messages">
                        <i class="fas fa-print"></i> Print Selected
                    </button>
                </div>
            </div>

            <!-- Pagination Info -->
            <div id="messagePaginationInfo" class="pagination-info"></div>

            <!-- Messages List -->
            <div class="messages-list" id="messagesList">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Loading messages...</p>
                </div>
            </div>

            <!-- Pagination -->
            <div class="modal-pagination" id="messagePagination">
                <div class="pagination-wrapper">
                    <button class="btn btn-secondary btn-xs pagination-btn" id="messageFirstPage" data-action="message-first-page">First</button>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="messagePrevPage" data-action="message-prev-page">Previous</button>
                    <div id="messagePageNumbers" class="page-numbers"></div>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="messageNextPage" data-action="message-next-page">Next</button>
                    <button class="btn btn-secondary btn-xs pagination-btn" id="messageLastPage" data-action="message-last-page">Last</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Follow-up Form Modal -->
<div id="newFollowupModal" class="accounting-modal accounting-modal-overlay accounting-modal-hidden">
    <div class="accounting-modal-content new-form-modal">
        <div class="modal-header">
            <h2><i class="fas fa-plus-circle"></i> New Follow-up</h2>
            <button class="modal-close" data-action="close-new-followup-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="newFollowupForm">
                <div class="form-group">
                    <label for="followupTitle">Title <span class="text-danger">*</span></label>
                    <input type="text" id="followupTitle" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="followupDescription">Description</label>
                    <textarea id="followupDescription" name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="followupRelatedType">Related Type <span class="text-danger">*</span></label>
                            <select id="followupRelatedType" name="related_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="transaction">Transaction</option>
                                <option value="invoice">Invoice</option>
                                <option value="bill">Bill</option>
                                <option value="journal_entry">Journal Entry</option>
                                <option value="account">Account</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="followupRelatedId">Related ID</label>
                            <input type="number" id="followupRelatedId" name="related_id" class="form-control" value="0">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="followupDueDate">Due Date</label>
                            <input type="text" id="followupDueDate" name="due_date" class="form-control date-input" placeholder="MM/DD/YYYY">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="followupPriority">Priority</label>
                            <select id="followupPriority" name="priority" class="form-control">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="followupNotes">Notes</label>
                    <textarea id="followupNotes" name="notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" data-action="close-new-followup-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Follow-up</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Follow-up Form Modal -->
<div id="editFollowupModal" class="accounting-modal accounting-modal-overlay accounting-modal-hidden">
    <div class="accounting-modal-content new-form-modal">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Edit Follow-up</h2>
            <button class="modal-close" data-action="close-edit-followup-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="editFollowupForm">
                <input type="hidden" id="editFollowupId" name="id">
                <div class="form-group">
                    <label for="editFollowupTitle">Title <span class="text-danger">*</span></label>
                    <input type="text" id="editFollowupTitle" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="editFollowupDescription">Description</label>
                    <textarea id="editFollowupDescription" name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="editFollowupRelatedType">Related Type <span class="text-danger">*</span></label>
                            <select id="editFollowupRelatedType" name="related_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="transaction">Transaction</option>
                                <option value="invoice">Invoice</option>
                                <option value="bill">Bill</option>
                                <option value="journal_entry">Journal Entry</option>
                                <option value="account">Account</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="editFollowupRelatedId">Related ID</label>
                            <input type="number" id="editFollowupRelatedId" name="related_id" class="form-control" value="0">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="editFollowupDueDate">Due Date</label>
                            <input type="text" id="editFollowupDueDate" name="due_date" class="form-control date-input" placeholder="MM/DD/YYYY">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="editFollowupPriority">Priority</label>
                            <select id="editFollowupPriority" name="priority" class="form-control">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="editFollowupStatus">Status</label>
                            <select id="editFollowupStatus" name="status" class="form-control">
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="editFollowupNotes">Notes</label>
                    <textarea id="editFollowupNotes" name="notes" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" data-action="close-edit-followup-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Follow-up</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- New Message Form Modal -->
<div id="newMessageModal" class="accounting-modal accounting-modal-overlay accounting-modal-hidden">
    <div class="accounting-modal-content new-form-modal">
        <div class="modal-header">
            <h2><i class="fas fa-envelope"></i> New Message</h2>
            <button class="modal-close" data-action="close-new-message-modal">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="newMessageForm">
                <div class="form-group">
                    <label for="messageTitle">Title <span class="text-danger">*</span></label>
                    <input type="text" id="messageTitle" name="title" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="messageBody">Message <span class="text-danger">*</span></label>
                    <textarea id="messageBody" name="message" class="form-control" rows="4" required></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="messageType">Type</label>
                            <select id="messageType" name="type" class="form-control">
                                <option value="info" selected>Info</option>
                                <option value="warning">Warning</option>
                                <option value="error">Error</option>
                                <option value="success">Success</option>
                                <option value="alert">Alert</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="messageCategory">Category</label>
                            <select id="messageCategory" name="category" class="form-control">
                                <option value="system_notification">System Notification</option>
                                <option value="overdue_invoice">Overdue Invoice</option>
                                <option value="low_balance">Low Balance</option>
                                <option value="transaction_alert">Transaction Alert</option>
                                <option value="financial">Financial</option>
                                <option value="operational">Operational</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="messageRelatedType">Related Type</label>
                            <select id="messageRelatedType" name="related_type" class="form-control">
                                <option value="">None</option>
                                <option value="transaction">Transaction</option>
                                <option value="invoice">Invoice</option>
                                <option value="bill">Bill</option>
                                <option value="account">Account</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="messageRelatedId">Related ID</label>
                            <input type="number" id="messageRelatedId" name="related_id" class="form-control" value="0">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="messageImportant" name="is_important" value="1">
                        Mark as Important
                    </label>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" data-action="close-new-message-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo asset('js/utils/currencies-utils.js'); ?>"></script>
<!-- Core: Class definition and constructor -->
<script src="<?php echo asset('js/accounting/professional.core.js'); ?>"></script>
<!-- Utilities: Formatting and utility methods -->
<script src="<?php echo asset('js/accounting/professional.utilities.js'); ?>"></script>
<!-- Part 1: setupEventListeners, ensureTabButtonsClickable, switchTab, handleNavClick, etc. -->
<script src="<?php echo asset('js/accounting/professional.part1.js'); ?>"></script>
<!-- Accounts: Account-related methods -->
<script src="<?php echo asset('js/accounting/professional.accounts.js'); ?>"></script>
<!-- Dashboard: Dashboard methods -->
<script src="<?php echo asset('js/accounting/professional.dashboard.js'); ?>"></script>
<!-- Management: Management methods (cost centers, bank guarantees, vouchers, etc.) - Must load before modals.tables -->
<script src="<?php echo asset('js/accounting/professional.management.js'); ?>"></script>
<!-- Part 5: Payment/Receipt voucher modals, getPaymentVoucherModalContent, savePaymentVoucher - Must load before modals.tables -->
<script src="<?php echo asset('js/accounting/professional.part5.js'); ?>"></script>
<!-- Modals: Modal-related methods -->
<script src="<?php echo asset('js/accounting/professional.modals.js'); ?>"></script>
<script src="<?php echo asset('js/accounting/professional.modals.tables.js'); ?>"></script>
<!-- Reports: Report methods -->
<script src="<?php echo asset('js/accounting/professional.reports.js'); ?>"></script>
<!-- Extensions: Additional features -->
<script src="<?php echo asset('js/accounting/professional-support-payments.js'); ?>"></script>
<!-- Patches: Must load last - patches existing methods -->
<script src="<?php echo asset('js/accounting/professional.init.js'); ?>"></script>
<script src="<?php echo asset('js/accounting/accounting-modal.js'); ?>"></script>

<?php include '../includes/footer.php'; ?>

