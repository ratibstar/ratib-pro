<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/individual-reports.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/individual-reports.php`.
 */
require_once __DIR__ . '/../includes/config.php';
$pageTitle = "Individual Reports";
$pageCss = [
    asset('css/reports.css') . '?v=' . time(),
    asset('css/individual-reports.css') . '?v=' . time(),
    "https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css",
    "https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css",
    "https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css"
];
$pageJs = [
    "https://cdn.jsdelivr.net/npm/moment/moment.min.js",
    "https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js",
    "https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js",
    "https://cdn.jsdelivr.net/npm/chart.js",
    asset('js/individual-reports.js')
];

require_once(__DIR__ . '/../includes/header.php');
?>

<!-- Individual Reports Modal - force LTR and English, no Arabic -->
<div id="individualReportsModal" class="individual-reports-modal show" dir="ltr" lang="en">
    <div class="individual-reports-overlay"></div>
    <div class="individual-reports-container" dir="ltr" lang="en">
        <!-- Close Button -->
        <button class="individual-reports-close" id="closeIndividualReportsBtn" title="Close">
            <i class="fas fa-times"></i>
        </button>
    <!-- Individual Reports Header -->
    <div class="reports-header">
        <h2><i class="fas fa-user-chart"></i> Individual Reports</h2>
        <div class="report-actions">
            <button class="btn-back" data-action="back-to-reports" title="Back to Reports Dashboard">
                <i class="fas fa-arrow-left"></i> Back to Reports
            </button>
            <div class="connection-status" id="connectionStatus">
                <i class="fas fa-circle"></i>
                <span>Checking connection...</span>
            </div>
            <button class="btn-refresh" data-action="refresh-report">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
            <button class="btn-export" data-action="export-report">
                <i class="fas fa-file-export"></i> Export
            </button>
            <button class="btn-print" data-action="print-report">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Entity Selection -->
    <div class="entity-selection">
        <div class="selection-card">
            <h3><i class="fas fa-search"></i> Select Entity</h3>
            <div class="selection-filters">
                <div class="filter-group">
                    <label>Entity Type</label>
                    <select id="entityType" data-action="load-entity-options">
                        <option value="">Select Type</option>
                        <option value="agents">Agents</option>
                        <option value="subagents">SubAgents</option>
                        <option value="workers" selected>Workers</option>
                        <option value="cases">Cases</option>
                        <option value="hr">HR Employees</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Select Entity</label>
                    <select id="entitySelect" data-action="load-individual-report" disabled>
                        <option value="">Select an entity first</option>
                    </select>
                </div>
                <div class="filter-group date-fields-group" dir="ltr" lang="en">
                    <div class="date-field-wrapper">
                        <label>From</label>
                        <input type="text" id="dateFrom" class="date-input" placeholder="YYYY-MM-DD" autocomplete="off">
                    </div>
                    <div class="date-field-wrapper">
                        <label>To</label>
                        <input type="text" id="dateTo" class="date-input" placeholder="YYYY-MM-DD" autocomplete="off">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Individual Report Content -->
    <div class="individual-report-content d-none" id="individualReportContent">
        
        <!-- Entity Header -->
        <div class="entity-header">
            <div class="entity-info">
                <div class="entity-avatar" id="entityAvatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="entity-details">
                    <h3 id="entityName">Entity Name</h3>
                    <p id="entityTypeText">Entity Type</p>
                    <span class="status-badge" id="entityStatus">ACTIVE</span>
                </div>
            </div>
            <div class="entity-actions">
                <button class="btn-action btn-edit" data-action="edit-entity">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn-action btn-view" data-action="view-entity">
                    <i class="fas fa-eye"></i> View Details
                </button>
                <button class="btn-action btn-cancel" data-action="cancel-selection">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>

        <!-- Report Tabs -->
        <div class="report-tabs">
            <button class="tab-btn active" data-tab="overview">Overview</button>
            <button class="tab-btn" data-tab="performance">Performance</button>
            <button class="tab-btn" data-tab="financial">Financial</button>
            <button class="tab-btn" data-tab="activities">Activities</button>
            <button class="tab-btn" data-tab="documents">Documents</button>
        </div>

        <!-- Tab Content -->
        <div class="tab-content-container">
            <!-- Overview Tab -->
            <div class="tab-content active" id="overview">
                <div class="overview-grid">
                    <div class="overview-card">
                        <h4>Key Metrics</h4>
                        <div class="metrics-grid" id="overviewMetrics">
                            <!-- Dynamic metrics -->
                        </div>
                    </div>
                    <div class="overview-card">
                        <h4>Recent Activity</h4>
                        <div class="activity-list" id="recentActivity">
                            <!-- Dynamic activities -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Tab -->
            <div class="tab-content" id="performance">
                <div class="performance-section">
                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Financial Tab -->
            <div class="tab-content" id="financial">
                <div class="financial-section">
                    <div class="financial-summary" id="financialSummary">
                        <!-- Dynamic summary -->
                    </div>
                    <div class="transactions-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody id="transactionsBody">
                                <!-- Dynamic transactions -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Activities Tab -->
            <div class="tab-content" id="activities">
                <div class="activities-section">
                    <div class="activity-filters">
                        <div class="filter-group">
                            <label>Activity Type</label>
                            <select id="activityTypeFilter">
                                <option value="all">All Activities</option>
                                <option value="login">Logins</option>
                                <option value="transaction">Transactions</option>
                                <option value="update">Updates</option>
                                <option value="document">Documents</option>
                            </select>
                        </div>
                        <div class="filter-group" dir="ltr" lang="en">
                            <label>Date Range</label>
                            <input type="text" id="activityDateRange" class="date-range-picker" autocomplete="off">
                        </div>
                    </div>
                    <div class="activity-list" id="activityList">
                        <!-- Dynamic activities -->
                    </div>
                </div>
            </div>

            <!-- Documents Tab -->
            <div class="tab-content" id="documents">
                <div class="documents-section">
                    <div class="document-actions">
                        <button class="btn-primary" data-action="upload-document">
                            <i class="fas fa-upload"></i> Upload Document
                        </button>
                        <button class="btn-secondary" data-action="generate-document">
                            <i class="fas fa-file-pdf"></i> Generate Report
                        </button>
                    </div>
                    <div class="documents-grid" id="documentsGrid">
                        <!-- Dynamic documents -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading State -->
    <div class="loading-state d-none" id="loadingState">
        <div class="spinner"></div>
        <p>Loading report data...</p>
    </div>

    <!-- Error State -->
    <div class="error-state d-none" id="errorState">
        <i class="fas fa-exclamation-triangle"></i>
        <p id="errorMessage">An error occurred while loading the report.</p>
    </div>

    <!-- Empty State -->
    <div class="empty-state d-none" id="emptyState">
        <i class="fas fa-inbox"></i>
        <p>Select an Entity</p>
        <span>Choose an entity type and specific entity to view their individual report.</span>
    </div>
    </div>
</div>

<?php require_once(__DIR__ . '/../includes/footer.php'); ?>
