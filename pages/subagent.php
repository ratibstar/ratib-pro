<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/subagent.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/subagent.php`.
 */
require_once '../includes/config.php';
require_once '../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// Check if user has permission to view subagents
if (!hasPermission('view_subagents')) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$pageTitle = "Subagent Management";
$subagentCssPath = __DIR__ . '/../css/subagent/subagent.css';
$subagentCssVersion = is_file($subagentCssPath) ? filemtime($subagentCssPath) : time();
$pageCss = [
    asset('css/subagent/subagent.css') . '?v=' . $subagentCssVersion
];
$pageJs = [
    asset('js/subagent/subagents-data.js') . "?v=" . time(),
    asset('js/common/universal-closing-alerts.js') . "?v=" . time()
];

include '../includes/header.php';
?>

<div class="subagent-container">
    <!-- Status Cards -->
    <div class="status-cards">
        <div class="status-card total">
            <div class="card-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="card-info">
                <h3>Total Subagents</h3>
                <span class="count" id="totalCount">0</span>
            </div>
        </div>
        <div class="status-card active">
            <div class="card-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="card-info">
                <h3>Active</h3>
                <span class="count" id="activeCount">0</span>
            </div>
        </div>
        <div class="status-card inactive">
            <div class="card-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="card-info">
                <h3>Inactive</h3>
                <span class="count" id="inactiveCount">0</span>
            </div>
        </div>
    </div>

    <!-- Modern Alert System -->
    <div id="modernAlert" class="modern-alert">
        <div class="alert-content">
            <div class="alert-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="alert-message">
                <div class="alert-title">Alert</div>
                <div class="alert-text">Message</div>
            </div>
            <button class="alert-close" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="alert-progress"></div>
    </div>

    <!-- Modern Closing Alert Modal -->
    <div id="closingAlertModal" class="closing-alert-modal d-none">
        <div class="closing-alert-content">
            <div class="closing-alert-header">
                <div class="closing-alert-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h3>Unsaved Changes</h3>
            </div>
            <div class="closing-alert-body">
                <p>You have unsaved changes. Are you sure you want to close without saving?</p>
            </div>
            <div class="closing-alert-footer">
                <button id="closingAlertCancel" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button id="closingAlertDiscard" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Discard Changes
                </button>
            </div>
        </div>
    </div>

    <!-- Modern Confirmation Modal -->
    <div id="confirmationModal" class="confirmation-modal d-none">
        <div class="confirmation-content">
            <div class="confirmation-header">
                <div class="confirmation-icon">
                    <i id="confirmationIcon" class="fas fa-question-circle"></i>
                </div>
                <h3 id="confirmationTitle">Confirm Action</h3>
            </div>
            <div class="confirmation-body">
                <p id="confirmationMessage">Are you sure you want to perform this action?</p>
            </div>
            <div class="confirmation-footer">
                <button id="confirmationCancel" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button id="confirmationConfirm" class="btn btn-primary">
                    <i class="fas fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>

    <!-- Search and Actions Bar -->
    <div class="actions-wrapper">
        <div class="search-wrapper">
            <i class="fas fa-search search-icon"></i>
            <input type="text" 
                   class="search-box" 
                   placeholder="Search subagents..." 
                   id="subagentSearch">
        </div>
        
        <div class="status-filter">
            <select id="statusFilter" class="status-select">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        
        <div class="bulk-actions">
            <button data-action="bulk-activate" class="bulk-btn activate" disabled data-permission="edit_subagent">
                <i class="fas fa-check-circle"></i> Activate
            </button>
            <button data-action="bulk-deactivate" class="bulk-btn deactivate" disabled data-permission="edit_subagent">
                <i class="fas fa-times-circle"></i> Deactivate
            </button>
            <button data-action="delete-selected" class="bulk-btn delete" disabled data-permission="delete_subagent">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>

        <button class="add-new-btn" data-action="show-add-form" data-permission="add_subagent">
            <i class="fas fa-plus"></i>
            Add New Subagent
        </button>
        
        <button class="refresh-btn" data-action="refresh">
            <i class="fas fa-sync-alt"></i>
            Refresh
        </button>
    </div>

    <!-- Pagination (Top) -->
    <div class="pagination-container top">
        <div class="pagination-info">
            Showing <span id="startRecordTop">0</span>-<span id="endRecordTop">0</span> of <span id="totalRecordsTop">0</span> entries
        </div>
        <div class="pagination-controls" id="paginationTop">
            <button class="page-btn first-page">
                <i class="fas fa-angle-double-left"></i>
            </button>
            <button class="page-btn prev-page">
                <i class="fas fa-angle-left"></i>
            </button>
            <span class="page-numbers"></span>
            <button class="page-btn next-page">
                <i class="fas fa-angle-right"></i>
            </button>
            <button class="page-btn last-page">
                <i class="fas fa-angle-double-right"></i>
            </button>
        </div>
        <div class="page-size">
            <select id="pageSizeTop" class="form-select">
                <option value="5">Show 5</option>
                <option value="10">Show 10</option>
                <option value="25">Show 25</option>
                <option value="50">Show 50</option>
            </select>
        </div>
    </div>

    <!-- Table Container -->
    <div class="table-container">
        <table class="subagent-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>City</th>
                    <th>Address</th>
                    <th>Agent</th>
                    <th>Status</th>
                    <th>
                        <input type="checkbox" 
                               class="bulk-checkbox-all" 
                               data-action="toggle-all">
                    </th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="subagentTableBody">
                <tr><td colspan="10" class="loading">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Pagination (Bottom) -->
    <div class="pagination-container bottom">
        <div class="pagination-info">
            Showing <span id="startRecordBottom">0</span>-<span id="endRecordBottom">0</span> of <span id="totalRecordsBottom">0</span> entries
        </div>
        <div class="pagination-controls" id="paginationBottom">
            <button class="page-btn first-page">
                <i class="fas fa-angle-double-left"></i>
            </button>
            <button class="page-btn prev-page">
                <i class="fas fa-angle-left"></i>
            </button>
            <span class="page-numbers"></span>
            <button class="page-btn next-page">
                <i class="fas fa-angle-right"></i>
            </button>
            <button class="page-btn last-page">
                <i class="fas fa-angle-double-right"></i>
            </button>
        </div>
        <div class="page-size">
            <select id="pageSizeBottom" class="form-select">
                <option value="5">Show 5</option>
                <option value="10">Show 10</option>
                <option value="25">Show 25</option>
                <option value="50">Show 50</option>
            </select>
        </div>
    </div>

    <!-- Forms -->
    <div class="forms-container">
        <!-- Edit/Add Form Modal -->
        <div id="editForm" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="formTitle">Add New Subagent</h2>
                    <button type="button" class="close-modal" data-action="close-form">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="subagentFormMain">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="country">Country</label>
                            <select name="country" id="country" class="form-control" data-load-cities="city">
                                <option value="">Select Country</option>
                                <!-- Countries populated dynamically from System Settings via API -->
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="city">City</label>
                            <select name="city" id="city" class="form-control" required>
                                <option value="">Select Country First</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" required></textarea>
                        </div>
                        <div class="form-group">
                            <label for="agentSelect">Agent</label>
                            <select id="agentSelect" name="agent" class="form-control" required>
                                <option value="">-- Select Agent --</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subagentStatus">Status</label>
                            <select id="subagentStatus" name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-action="close-form">Cancel</button>
                    <button type="submit" class="btn btn-primary" form="subagentFormMain">Save</button>
                </div>
            </div>
        </div>

        <!-- View Modal -->
        <div id="viewSubagentModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>View Subagent</h2>
                    <button type="button" class="close-modal" data-action="close-form">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="viewSubagentDetails"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-action="close-form">
                        <i class="fas fa-times"></i> Close
                    </button>
                    <button type="button" class="btn btn-info" data-action="print-subagent">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button type="button" class="btn btn-primary" data-action="edit-current">
                        <i class="fas fa-edit"></i> Edit Subagent
                    </button>
                </div>
            </div>
        </div>

        <!-- Account Modal -->
        <div id="accountModal" class="modal">
            <div class="modal-content">
                <h2>Account Details</h2>
                <!-- Account content -->
                <div class="modal-actions">
                    <button class="btn cancel-btn" data-action="close-form">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Account Details Modal -->
    <div id="accountDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Account Details</h2>
                <button class="close-modal" data-action="close-form">&times;</button>
            </div>
            <div class="modal-body">
                <form id="accountDetailsForm">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-input" name="username" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="password-input-group">
                            <input type="password" class="form-input" name="password" required>
                            <button type="button" class="toggle-password" data-action="toggle-password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" required>
                            <option value="admin">Admin</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="modal-btn cancel" data-action="close-form">Cancel</button>
                <button class="modal-btn save" type="submit" form="accountDetailsForm">Save</button>
            </div>
        </div>
    </div>
</div>

<script src="../js/utils/currencies-utils.js?v=<?php echo time(); ?>"></script>
<!-- Load the countries and cities database -->
<script src="../js/countries-cities.js?v=<?php echo time(); ?>"></script>
<script src="../js/subagent/subagent-page-init.js?v=<?php echo time(); ?>"></script>

<?php include '../includes/footer.php'; ?>
<?php foreach($pageJs as $script): ?>
    <script src="<?= htmlspecialchars($script) ?>"></script>
<?php endforeach; ?>

