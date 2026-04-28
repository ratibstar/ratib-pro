<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/account/support-payments.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/account/support-payments.php`.
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

$pageTitle = "Support Payments";
$pageCss = [
    asset('css/accounting/professional.css') . "?v=" . time()
];
$pageJs = [
    asset('js/accounting/support-payments.js') . "?v=" . time()
];

include '../../includes/header.php';
?>

<div class="accounting-container">
    <!-- Header -->
    <div class="accounting-header">
        <div class="header-left">
            <h1><i class="fas fa-hand-holding-usd"></i> Support Payments</h1>
        </div>
        <div class="header-right">
            <button class="btn btn-primary" id="btn-new-payment" data-permission="create_support_payments">
                <i class="fas fa-plus"></i> New Payment
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
                <option value="draft">Draft</option>
                <option value="posted">Posted</option>
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

    <!-- Payments Table -->
    <div class="accounting-table-container">
        <table class="report-table" id="payments-table">
            <thead>
                <tr>
                    <th>Payment #</th>
                    <th>Date</th>
                    <th>Recipient</th>
                    <th class="amount">Amount</th>
                    <th>Status</th>
                    <th>JE #</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="payments-tbody">
                <tr>
                    <td colspan="7" class="empty-state">
                        <div class="icon"><i class="fas fa-hand-holding-usd"></i></div>
                        <div class="message">No support payments found</div>
                        <div class="action">
                            <button class="btn btn-primary" id="btn-add-first-payment">
                                <i class="fas fa-plus"></i> Add First Payment
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Payment Form Modal (Ledger-Driven Design) -->
<div class="accounting-modal accounting-modal-hidden" id="payment-modal">
    <div class="accounting-modal-content">
        <div class="accounting-modal-header">
            <h2 id="modal-title">New Support Payment</h2>
            <button class="accounting-modal-close" id="btn-close-modal">&times;</button>
        </div>
        <div class="accounting-modal-body">
            <form id="payment-form">
                <input type="hidden" id="payment-id" name="id">
                
                <!-- Voucher Information Section -->
                <div class="voucher-info-section">
                    <div class="section-header">
                        <h3>VOUCHER INFORMATION</h3>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment-number">Payment Number <span class="required">*</span></label>
                        <input type="text" id="payment-number" name="payment_number" class="form-control" readonly>
                        <small class="form-text">Auto-generated</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment-date">Payment Date <span class="required">*</span></label>
                        <input type="date" id="payment-date" name="payment_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="recipient">Recipient <span class="required">*</span></label>
                        <input type="text" id="recipient" name="recipient" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reference">Reference</label>
                        <input type="text" id="reference" name="reference" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Status:</label>
                        <div>
                            <span class="status-badge status-draft" id="status-display">Draft</span>
                        </div>
                    </div>
                </div>
                
                <!-- Debit Entries Section -->
                <div class="ledger-section ledger-debit">
                    <div class="section-header">
                        <h3>DEBIT ENTRIES (Ledger Movements)</h3>
                        <span class="section-indicator">Debit</span>
                    </div>
                    
                    <div class="ledger-lines-container" id="debit-lines-container">
                        <!-- Debit lines will be added here -->
                    </div>
                    
                    <div class="section-total sticky-total">
                        <span class="total-label">TOTAL DEBIT:</span>
                        <span class="total-value" id="total-debit">0.00</span>
                    </div>
                    
                    <button type="button" class="btn btn-add-line" id="btn-add-debit-line">
                        <i class="fas fa-plus"></i> Add Debit Line
                    </button>
                </div>
                
                <!-- Credit Entries Section -->
                <div class="ledger-section ledger-credit">
                    <div class="section-header">
                        <h3>CREDIT ENTRIES (Ledger Movements)</h3>
                        <span class="section-indicator">Credit</span>
                    </div>
                    
                    <div class="ledger-lines-container" id="credit-lines-container">
                        <!-- Credit lines will be added here -->
                    </div>
                    
                    <div class="section-total sticky-total">
                        <span class="total-label">TOTAL CREDIT:</span>
                        <span class="total-value" id="total-credit">0.00</span>
                    </div>
                    
                    <button type="button" class="btn btn-add-line" id="btn-add-credit-line">
                        <i class="fas fa-plus"></i> Add Credit Line
                    </button>
                </div>
                
                <!-- Balance Validation Footer -->
                <div class="balance-validation-footer sticky-footer balanced" id="balance-footer">
                    <div class="balance-totals">
                        <div class="balance-item">
                            <span class="balance-label">Total Debit:</span>
                            <span class="balance-value" id="footer-total-debit">0.00</span>
                        </div>
                        <div class="balance-item">
                            <span class="balance-label">Total Credit:</span>
                            <span class="balance-value" id="footer-total-credit">0.00</span>
                        </div>
                    </div>
                    <div class="balance-indicator balanced" id="balance-indicator">
                        <span class="icon">✓</span>
                        <span class="balance-text">BALANCED</span>
                    </div>
                </div>
                
                <!-- Period Status Section -->
                <div class="period-status-section open" id="period-status">
                    <div class="period-lock-warning">
                        <span class="icon">✓</span>
                        <span>Period is open - Entry allowed</span>
                    </div>
                </div>
                
                <div class="accounting-modal-footer">
                    <button type="button" class="btn btn-secondary" id="btn-cancel">Cancel</button>
                    <button type="button" class="btn btn-secondary" id="btn-save-draft">Save Draft</button>
                    <button type="submit" class="btn btn-primary" id="btn-post" disabled>Post Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
