<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/account/financial-reports.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/account/financial-reports.php`.
 */
require_once '../../includes/config.php';
require_once '../../includes/permissions.php';

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

$pageTitle = "Financial Reports";
$pageCss = [
    asset('css/accounting/professional.css') . "?v=" . time()
];
$pageJs = [
    asset('js/accounting/financial-reports.js') . "?v=" . time()
];

include '../../includes/header.php';
?>

<div class="accounting-container">
    <!-- Header -->
    <div class="accounting-header">
        <div class="header-left">
            <h1><i class="fas fa-chart-line"></i> Financial Reports</h1>
        </div>
        <div class="header-right">
            <button class="btn btn-secondary" data-action="refresh" data-permission="view_chart_accounts">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Report Filters -->
    <div class="accounting-filters">
        <div class="filter-group">
            <label for="period-select">Period:</label>
            <select id="period-select" class="form-control">
                <option value="current-month">Current Month</option>
                <option value="last-month">Last Month</option>
                <option value="current-quarter">Current Quarter</option>
                <option value="last-quarter">Last Quarter</option>
                <option value="current-year">Current Year</option>
                <option value="last-year">Last Year</option>
                <option value="custom">Custom Range</option>
            </select>
        </div>
        <div class="filter-group" id="custom-date-range">
            <label for="date-from">From:</label>
            <input type="date" id="date-from" class="form-control">
        </div>
        <div class="filter-group" id="custom-date-range-to">
            <label for="date-to">To:</label>
            <input type="date" id="date-to" class="form-control">
        </div>
        <div class="filter-group">
            <label for="cost-center-filter">Cost Center:</label>
            <select id="cost-center-filter" class="form-control">
                <option value="">All</option>
            </select>
        </div>
        <div class="filter-group">
            <button class="btn btn-primary" id="btn-generate-report">
                <i class="fas fa-file-alt"></i> Generate Report
            </button>
        </div>
    </div>

    <!-- Report List -->
    <div class="report-list-container">
        <table class="report-table" id="reports-list-table">
            <thead>
                <tr>
                    <th>Report Type</th>
                    <th>Period</th>
                    <th>Cost Center</th>
                    <th>Generated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <i class="fas fa-balance-scale"></i> Trial Balance
                    </td>
                    <td id="period-display">Current Month</td>
                    <td id="cost-center-display">All</td>
                    <td>-</td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-action="generate-report" data-report-type="trial-balance">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-report" data-report-type="trial-balance" data-format="pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-report" data-report-type="trial-balance" data-format="excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                    </td>
                </tr>
                <tr>
                    <td>
                        <i class="fas fa-chart-line"></i> Income Statement
                    </td>
                    <td id="period-display">Current Month</td>
                    <td id="cost-center-display">All</td>
                    <td>-</td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-action="generate-report" data-report-type="income-statement">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-report" data-report-type="income-statement" data-format="pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-report" data-report-type="income-statement" data-format="excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                    </td>
                </tr>
                <tr>
                    <td>
                        <i class="fas fa-building"></i> Balance Sheet
                    </td>
                    <td id="period-display">Current Month</td>
                    <td id="cost-center-display">All</td>
                    <td>-</td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-action="generate-report" data-report-type="balance-sheet">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-report" data-report-type="balance-sheet" data-format="pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-report" data-report-type="balance-sheet" data-format="excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                    </td>
                </tr>
                <tr>
                    <td>
                        <i class="fas fa-money-bill-wave"></i> Cash Flow Statement
                    </td>
                    <td id="period-display">Current Month</td>
                    <td id="cost-center-display">All</td>
                    <td>-</td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-action="generate-report" data-report-type="cash-flow">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-report" data-report-type="cash-flow" data-format="pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-report" data-report-type="cash-flow" data-format="excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                    </td>
                </tr>
                <tr>
                    <td>
                        <i class="fas fa-receipt"></i> VAT Report
                    </td>
                    <td id="period-display">Current Month</td>
                    <td id="cost-center-display">All</td>
                    <td>-</td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-action="generate-report" data-report-type="vat">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-report" data-report-type="vat" data-format="pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-report" data-report-type="vat" data-format="excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="btn btn-sm btn-warning" data-action="export-report" data-report-type="vat" data-format="tax-filing">
                            <i class="fas fa-file-export"></i> Tax Filing
                        </button>
                    </td>
                </tr>
                <tr>
                    <td>
                        <i class="fas fa-clock"></i> Aged Receivables
                    </td>
                    <td id="period-display">As of Date</td>
                    <td id="cost-center-display">All</td>
                    <td>-</td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-action="generate-report" data-report-type="aged-receivables">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-report" data-report-type="aged-receivables" data-format="pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-report" data-report-type="aged-receivables" data-format="excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                    </td>
                </tr>
                <tr>
                    <td>
                        <i class="fas fa-clock"></i> Aged Payables
                    </td>
                    <td id="period-display">As of Date</td>
                    <td id="cost-center-display">All</td>
                    <td>-</td>
                    <td>
                        <button class="btn btn-sm btn-primary" data-action="generate-report" data-report-type="aged-payables">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-report" data-report-type="aged-payables" data-format="pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-report" data-report-type="aged-payables" data-format="excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Report View Modal -->
<div class="accounting-modal accounting-modal-hidden" id="report-modal">
    <div class="accounting-modal-content">
        <div class="accounting-modal-header">
            <h2 id="report-modal-title">Report</h2>
            <button class="accounting-modal-close" id="btn-close-report-modal">&times;</button>
        </div>
        <div class="accounting-modal-body">
            <div id="report-content">
                <!-- Report content will be loaded here -->
            </div>
        </div>
        <div class="accounting-modal-footer">
            <button class="btn btn-secondary" id="btn-close-report">Close</button>
            <button class="btn btn-primary" id="btn-export-pdf">
                <i class="fas fa-file-pdf"></i> Export PDF
            </button>
            <button class="btn btn-primary" id="btn-export-excel">
                <i class="fas fa-file-excel"></i> Export Excel
            </button>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
