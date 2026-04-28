<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/account/cost-centers.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/account/cost-centers.php`.
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

$pageTitle = "Cost Centers";
$pageCss = [
    asset('css/accounting/professional.css') . "?v=" . time()
];
$pageJs = [
    asset('js/accounting/cost-centers.js') . "?v=" . time()
];

include '../../includes/header.php';
?>

<div class="accounting-container">
    <!-- Header -->
    <div class="accounting-header">
        <div class="header-left">
            <h1><i class="fas fa-sitemap"></i> Cost Centers</h1>
        </div>
        <div class="header-right">
            <button class="btn btn-primary" id="btn-new-cost-center" data-permission="create_cost_centers">
                <i class="fas fa-plus"></i> New Cost Center
            </button>
            <button class="btn btn-secondary" data-action="refresh" data-permission="view_chart_accounts">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="accounting-filters">
        <div class="filter-group">
            <label for="statusFilter">Status:</label>
            <select id="statusFilter" class="form-control">
                <option value="">All</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="filter-group">
            <button class="btn btn-primary" id="btn-apply-filters">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
        </div>
    </div>

    <!-- Cost Centers Table -->
    <div class="accounting-table-container">
        <table class="report-table" id="cost-centers-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Status</th>
                    <th class="amount">Total Expenses</th>
                    <th class="amount">Total Revenue</th>
                    <th class="amount">Net</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="cost-centers-tbody">
                <tr>
                    <td colspan="7" class="empty-state">
                        <div class="icon"><i class="fas fa-sitemap"></i></div>
                        <div class="message">No cost centers found</div>
                        <div class="action">
                            <button class="btn btn-primary" id="btn-add-first-cost-center">
                                <i class="fas fa-plus"></i> Add First Cost Center
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Cost Center Form Modal -->
<div class="accounting-modal accounting-modal-hidden" id="cost-center-modal">
    <div class="accounting-modal-content">
        <div class="accounting-modal-header">
            <h2 id="modal-title">New Cost Center</h2>
            <button class="accounting-modal-close" id="btn-close-modal">&times;</button>
        </div>
        <div class="accounting-modal-body">
            <form id="cost-center-form">
                <input type="hidden" id="cost-center-id" name="id">
                
                <!-- Cost Center Information Section -->
                <div class="voucher-info-section">
                    <div class="section-header">
                        <h3>COST CENTER INFORMATION</h3>
                    </div>
                    
                    <div class="form-group">
                        <label for="cost-center-code">Cost Center Code <span class="required">*</span></label>
                        <input type="text" id="cost-center-code" name="code" class="form-control" required>
                        <small class="form-text">Required, Unique</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="cost-center-name">Cost Center Name <span class="required">*</span></label>
                        <input type="text" id="cost-center-name" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cost-center-description">Description</label>
                        <textarea id="cost-center-description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Status:</label>
                        <div>
                            <label class="radio-label">
                                <input type="radio" name="status" value="active" checked> Active
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="status" value="inactive"> Inactive
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Cost Center Summary Section (from General Ledger) -->
                <div class="ledger-section ledger-debit" id="cost-center-summary">
                    <div class="section-header">
                        <h3>COST CENTER SUMMARY (from General Ledger)</h3>
                    </div>
                    
                    <div class="form-group">
                        <label>Total Expenses:</label>
                        <div class="amount-display" id="total-expenses">0.00</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Total Revenue:</label>
                        <div class="amount-display" id="total-revenue">0.00</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Net:</label>
                        <div class="amount-display" id="net-amount">0.00</div>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" class="btn btn-secondary" id="btn-view-detailed-report">
                            <i class="fas fa-chart-bar"></i> View Detailed Report
                        </button>
                    </div>
                </div>
                
                <div class="accounting-modal-footer">
                    <button type="button" class="btn btn-secondary" id="btn-cancel">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="btn-save">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
