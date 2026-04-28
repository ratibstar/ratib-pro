<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/account/bank-guarantees.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/account/bank-guarantees.php`.
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

$pageTitle = "Letters of Bank Guarantee";
$pageCss = [
    asset('css/accounting/professional.css') . "?v=" . time()
];
$pageJs = [
    asset('js/accounting/bank-guarantees.js') . "?v=" . time()
];

include '../../includes/header.php';
?>

<div class="accounting-container">
    <!-- Header -->
    <div class="accounting-header">
        <div class="header-left">
            <h1><i class="fas fa-file-contract"></i> Letters of Bank Guarantee</h1>
        </div>
        <div class="header-right">
            <button class="btn btn-primary" id="btn-new-guarantee" data-permission="create_bank_guarantees">
                <i class="fas fa-plus"></i> New Guarantee
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
                <option value="expired">Expired</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="bankFilter">Bank:</label>
            <select id="bankFilter" class="form-control">
                <option value="">All Banks</option>
            </select>
        </div>
        <div class="filter-group">
            <label for="dateFrom">From:</label>
            <input type="date" id="dateFrom" class="form-control">
        </div>
        <div class="filter-group">
            <label for="dateTo">To:</label>
            <input type="date" id="dateTo" class="form-control">
        </div>
        <div class="filter-group">
            <button class="btn btn-primary" id="btn-apply-filters">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
        </div>
    </div>

    <!-- Guarantees Table -->
    <div class="accounting-table-container">
        <table class="report-table" id="guarantees-table">
            <thead>
                <tr>
                    <th>Guarantee #</th>
                    <th>Bank</th>
                    <th class="amount">Amount</th>
                    <th>Issue Date</th>
                    <th>Expiry Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="guarantees-tbody">
                <tr>
                    <td colspan="7" class="empty-state">
                        <div class="icon"><i class="fas fa-file-contract"></i></div>
                        <div class="message">No bank guarantees found</div>
                        <div class="action">
                            <button class="btn btn-primary" id="btn-add-first-guarantee">
                                <i class="fas fa-plus"></i> Add First Guarantee
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Guarantee Form Modal -->
<div class="accounting-modal accounting-modal-hidden" id="guarantee-modal">
    <div class="accounting-modal-content">
        <div class="accounting-modal-header">
            <h2 id="modal-title">New Bank Guarantee</h2>
            <button class="accounting-modal-close" id="btn-close-modal">&times;</button>
        </div>
        <div class="accounting-modal-body">
            <form id="guarantee-form">
                <input type="hidden" id="guarantee-id" name="id">
                
                <div class="voucher-info-section">
                    <div class="section-header">
                        <h3>GUARANTEE INFORMATION</h3>
                    </div>
                    
                    <div class="form-group">
                        <label for="guarantee-number">Guarantee Number <span class="required">*</span></label>
                        <input type="text" id="guarantee-number" name="guarantee_number" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="bank-id">Bank <span class="required">*</span></label>
                        <select id="bank-id" name="bank_id" class="form-control" required>
                            <option value="">Select Bank</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Amount <span class="required">*</span></label>
                        <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="issue-date">Issue Date <span class="required">*</span></label>
                        <input type="date" id="issue-date" name="issue_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="expiry-date">Expiry Date <span class="required">*</span></label>
                        <input type="date" id="expiry-date" name="expiry_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Status:</label>
                        <div>
                            <label class="radio-label">
                                <input type="radio" name="status" value="active" checked> Active
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="status" value="expired"> Expired
                            </label>
                        </div>
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
