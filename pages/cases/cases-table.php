<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/cases/cases-table.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/cases/cases-table.php`.
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// Check if user has permission to view cases
if (!hasPermission('view_cases')) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// Get current user info
$currentUserId = $_SESSION['user_id'] ?? 1;
$currentUser = 'Admin User'; // You can fetch from database if needed

$pageTitle = "Cases Management";
$pageCss = [
    asset('css/nav.css'),
    asset('css/cases.css') . '?v=' . time(), // Add cache busting for mobile fixes
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
    'https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css'
];

// Include the header
include '../../includes/header.php';
?>

<div class="cases-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1><i class="fas fa-folder-open"></i> Cases Management</h1>
                    <p>Manage and track all cases efficiently</p>
                </div>
                <div class="header-right">
                    <!-- Header New Case button removed as requested -->
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-content">
                    <h3 id="totalCases">0</h3>
                    <p>Total Cases</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon low">
                    <i class="fas fa-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 id="lowCases">0</h3>
                    <p>Low Priority</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon medium">
                    <i class="fas fa-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 id="mediumCases">0</h3>
                    <p>Medium Priority</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon high">
                    <i class="fas fa-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 id="highCases">0</h3>
                    <p>High Priority</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon urgent">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <h3 id="urgentCases">0</h3>
                    <p>Urgent</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon progress">
                    <i class="fas fa-tasks"></i>
                </div>
                <div class="stat-content">
                    <h3 id="inProgressCases">0</h3>
                    <p>In Progress</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon resolved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3 id="resolvedCases">0</h3>
                    <p>Resolved</p>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filters-section">
            <div class="filters-left">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search cases...">
                </div>
                <select id="statusFilter" class="filter-select">
                    <option value="">All Status</option>
                    <option value="open">Open</option>
                    <option value="in_progress">In Progress</option>
                    <option value="pending">Pending</option>
                    <option value="resolved">Resolved</option>
                    <option value="closed">Closed</option>
                </select>
                <select id="typeFilter" class="filter-select">
                    <option value="">All Categories</option>
                    <option value="worker">Worker</option>
                    <option value="agent">Agent</option>
                    <option value="legal">Legal</option>
                    <option value="financial">Financial</option>
                    <option value="other">Other</option>
                </select>
                <select id="priorityFilter" class="filter-select">
                    <option value="">All Priority</option>
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                </select>
            </div>
            <div class="filters-right">
                <div class="bulk-actions">
                    <button class="btn btn-secondary" id="bulkEditBtn" disabled data-permission="edit_case">
                        <i class="fas fa-edit"></i> Bulk Edit
                    </button>
                    <button class="btn btn-warning" id="bulkDeleteBtn" disabled data-permission="delete_case">
                        <i class="fas fa-trash"></i> Bulk Delete
                    </button>
                    <button class="btn btn-info" id="bulkStatusBtn" disabled data-permission="edit_case">
                        <i class="fas fa-tasks"></i> Change Status
                    </button>
                </div>
                <button class="btn btn-primary" id="newCaseBtn" data-permission="add_case">
                    <i class="fas fa-plus"></i> New Case
                </button>
            </div>
        </div>

        <!-- Top Pagination -->
        <div class="pagination-container top-pagination">
            <div class="pagination-info">
                <label for="pageSizeTop">Show</label>
                <select id="pageSizeTop" class="form-control">
                    <option value="5" selected>5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span>entries</span>
                <span id="paginationInfoTop">Showing 0 to 0 of 0 entries</span>
            </div>
            <div class="pagination-controls">
                <button class="btn btn-sm pagination-btn" id="prevBtnTop" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <div class="pagination-pages" id="paginationPagesTop">
                    <!-- Page numbers will be generated here -->
                </div>
                <button class="btn btn-sm pagination-btn" id="nextBtnTop" disabled>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <!-- Cases Table -->
        <div class="table-container">
            <table class="cases-table">
                <thead>
                    <tr>
                        <th>Case #</th>
                        <th>Worker</th>
                        <th>Agent</th>
                        <th>Subagent</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Active Status</th>
                        <th>Assigned To</th>
                        <th>Created</th>
                        <th>Due Date</th>
                        <th>
                            <input type="checkbox" id="selectAllCheckbox" title="Select All">
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="casesTableBody">
                    <!-- Cases will be loaded here -->
                </tbody>
            </table>
        </div>

        <!-- Bottom Pagination -->
        <div class="pagination-container bottom-pagination">
            <div class="pagination-info">
                <label for="pageSize">Show</label>
                <select id="pageSize" class="form-control">
                    <option value="5" selected>5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span>entries</span>
                <span id="paginationInfo">Showing 0 to 0 of 0 entries</span>
            </div>
            <div class="pagination-controls">
                <button class="btn btn-sm pagination-btn" id="prevBtn" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <div class="pagination-pages" id="paginationPages">
                    <!-- Page numbers will be generated here -->
                </div>
                <button class="btn btn-sm pagination-btn" id="nextBtn" disabled>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Create/Edit Case Modal -->
    <div id="caseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Create New Case</h2>
                <button class="modal-close" id="closeModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="caseForm">
                <div class="modal-body">
                    <input type="hidden" id="caseId" name="case_id">
                    
                    <!-- Worker, Agent, Subagent Row - Moved to Top -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="workerId">Worker *</label>
                            <select id="workerId" name="worker_id" required>
                                <option value="">Select Worker</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="agentId">Agent</label>
                            <select id="agentId" name="agent_id">
                                <option value="">Select Agent</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="subagentId">Subagent</label>
                            <select id="subagentId" name="subagent_id">
                                <option value="">Select Subagent</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="assignedTo">Assigned To</label>
                            <select id="assignedTo" name="assigned_to">
                                <option value="">Select User</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Category and Priority Row -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="caseType">Category *</label>
                            <select id="caseType" name="case_type" required>
                                <option value="">Select Category</option>
                                <option value="worker">Worker</option>
                                <option value="agent">Agent</option>
                                <option value="legal">Legal</option>
                                <option value="financial">Financial</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="priority">Priority</label>
                            <select id="priority" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Date Created, Due Date and Status Row (dir=ltr, lang=en for English numerals) -->
                    <div class="form-row" dir="ltr" lang="en">
                        <div class="form-group">
                            <label for="dateCreated">Date Created</label>
                            <input type="date" id="dateCreated" name="date_created" readonly dir="ltr" lang="en">
                        </div>
                        <div class="form-group">
                            <label for="dueDate">Due Date</label>
                            <input type="date" id="dueDate" name="due_date" dir="ltr" lang="en">
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="open" selected>Open</option>
                                <option value="in_progress">In Progress</option>
                                <option value="pending">Pending</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Active/Inactive Status Row -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="activeStatus">Active Status</label>
                            <select id="activeStatus" name="active_status">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Description Dropdown with Custom Input -->
                    <div class="form-group">
                        <label for="description">Description *</label>
                        <select id="description" name="description" required>
                            <option value="">Select Description</option>
                            <option value="Passport documentation needed">Passport documentation needed</option>
                            <option value="Visa application assistance">Visa application assistance</option>
                            <option value="Medical certificate required">Medical certificate required</option>
                            <option value="Legal advice needed">Legal advice needed</option>
                            <option value="Contract review required">Contract review required</option>
                            <option value="Travel arrangements">Travel arrangements</option>
                            <option value="Insurance claim processing">Insurance claim processing</option>
                            <option value="Health check scheduling">Health check scheduling</option>
                            <option value="Flight booking assistance">Flight booking assistance</option>
                            <option value="Accommodation arrangements">Accommodation arrangements</option>
                            <option value="General inquiry">General inquiry</option>
                            <option value="Complaint resolution">Complaint resolution</option>
                            <option value="Follow-up required">Follow-up required</option>
                            <option value="Document translation">Document translation</option>
                            <option value="Appointment scheduling">Appointment scheduling</option>
                            <option value="Payment processing">Payment processing</option>
                            <option value="Status update needed">Status update needed</option>
                            <option value="Emergency assistance">Emergency assistance</option>
                            <option value="custom">Other - Custom Description</option>
                        </select>
                        <input type="text" id="customDescription" name="custom_description" placeholder="Enter custom description..." maxlength="500" class="custom-description-input">
                        <div id="customDescriptionCount" class="custom-description-count">
                            <span id="customDescriptionCurrent">0</span>/500 characters
                        </div>
                    </div>
                    
                    <!-- Resolution Dropdown -->
                    <div class="form-group">
                        <label for="resolution">Resolution</label>
                        <select id="resolution" name="resolution">
                            <option value="">Select Resolution</option>
                            <option value="completed">Completed</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="pending_approval">Pending Approval</option>
                            <option value="requires_follow_up">Requires Follow-up</option>
                            <option value="escalated">Escalated</option>
                            <option value="on_hold">On Hold</option>
                            <option value="in_progress">In Progress</option>
                            <option value="partially_resolved">Partially Resolved</option>
                            <option value="custom">Other - Custom Resolution</option>
                        </select>
                        <input type="text" id="customResolution" name="custom_resolution" placeholder="Enter custom resolution..." maxlength="1000" class="custom-resolution-input d-none">
                        <div id="customResolutionCount" class="custom-resolution-count d-none">
                            <span id="customResolutionCurrent">0</span>/1000 characters
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveBtn">
                        <i class="fas fa-save"></i> Save Case
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Case Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h2 id="viewModalTitle">Case Details</h2>
                <button class="modal-close" id="closeViewModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Case details will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Loading...</p>
        </div>
    </div>

    <!-- Modern Alert System -->
    <div id="modernAlert" class="modern-alert">
        <div class="alert-content">
            <div class="alert-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <div class="alert-message">
                <h4 class="alert-title">Alert</h4>
                <p class="alert-text">This is a modern alert message.</p>
            </div>
            <button class="alert-close" id="alertClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="alert-progress"></div>
    </div>

    <!-- Modern Confirm Dialog -->
    <div id="modernConfirm" class="modern-confirm">
        <div class="confirm-overlay"></div>
        <div class="confirm-dialog">
            <div class="confirm-header">
                <div class="confirm-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <h3 class="confirm-title">Confirm Action</h3>
            </div>
            <div class="confirm-body">
                <p class="confirm-message">Are you sure you want to perform this action?</p>
            </div>
            <div class="confirm-footer">
                <button class="btn btn-secondary" id="confirmCancel">Cancel</button>
                <button class="btn btn-primary" id="confirmOk">OK</button>
            </div>
        </div>
    </div>





    <!-- Bulk Edit Modal -->
    <div id="bulkEditModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Bulk Edit Cases</h2>
                <button class="modal-close" id="closeBulkEditModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>You have selected <span id="bulkEditCount">0</span> case(s). Choose what you want to edit:</p>
                <div class="bulk-options">
                    <button class="bulk-option-btn" data-action="priority">
                        <i class="fas fa-flag"></i>
                        Change Priority
                    </button>
                    <button class="bulk-option-btn" data-action="status">
                        <i class="fas fa-tasks"></i>
                        Change Status
                    </button>
                    <button class="bulk-option-btn" data-action="assigned">
                        <i class="fas fa-user"></i>
                        Change Assigned To
                    </button>
                    <button class="bulk-option-btn" data-action="due-date">
                        <i class="fas fa-calendar"></i>
                        Change Due Date
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Status Modal -->
    <div id="bulkStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-tasks"></i> Change Status</h2>
                <button class="modal-close" id="closeBulkStatusModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>You have selected <span id="bulkStatusCount">0</span> case(s). Choose the new status:</p>
                <div class="status-options">
                    <button class="status-option-btn" data-status="open">
                        <i class="fas fa-circle status-icon-open"></i>
                        Open
                    </button>
                    <button class="status-option-btn" data-status="in_progress">
                        <i class="fas fa-circle status-icon-in-progress"></i>
                        In Progress
                    </button>
                    <button class="status-option-btn" data-status="pending">
                        <i class="fas fa-circle status-icon-pending"></i>
                        Pending
                    </button>
                    <button class="status-option-btn" data-status="resolved">
                        <i class="fas fa-circle status-icon-resolved"></i>
                        Resolved
                    </button>
                    <button class="status-option-btn" data-status="closed">
                        <i class="fas fa-circle status-icon-closed"></i>
                        Closed
                    </button>
                </div>
                
                <div class="status-section">
                    <h4>Active Status</h4>
                    <div class="status-options">
                        <button class="status-option-btn" data-active-status="active">
                            <i class="fas fa-check-circle status-icon-active"></i>
                            Active
                        </button>
                        <button class="status-option-btn" data-active-status="inactive">
                            <i class="fas fa-times-circle status-icon-inactive"></i>
                            Inactive
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Priority Modal -->
    <div id="bulkPriorityModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-flag"></i> Change Priority</h2>
                <button class="modal-close" id="closeBulkPriorityModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>You have selected <span id="bulkPriorityCount">0</span> case(s). Choose the new priority:</p>
                <div class="form-group">
                    <label for="prioritySelect">Priority:</label>
                    <select id="prioritySelect" class="form-control">
                        <option value="">Select Priority</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" id="cancelBulkPriority">Cancel</button>
                    <button class="btn btn-primary" id="confirmBulkPriority">Update Priority</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Assigned Modal -->
    <div id="bulkAssignedModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user"></i> Change Assigned To</h2>
                <button class="modal-close" id="closeBulkAssignedModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>You have selected <span id="bulkAssignedCount">0</span> case(s). Choose the new assigned user:</p>
                <div class="form-group">
                    <label for="assignedSelect">Assigned To:</label>
                    <select id="assignedSelect" class="form-control">
                        <option value="">Select User</option>
                        <!-- Users will be loaded here -->
                    </select>
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" id="cancelBulkAssigned">Cancel</button>
                    <button class="btn btn-primary" id="confirmBulkAssigned">Update Assigned To</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Due Date Modal -->
    <div id="bulkDueDateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-calendar"></i> Change Due Date</h2>
                <button class="modal-close" id="closeBulkDueDateModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" dir="ltr" lang="en">
                <p>You have selected <span id="bulkDueDateCount">0</span> case(s). Choose the new due date:</p>
                <div class="form-group">
                    <label for="dueDateInput">Due Date:</label>
                    <input type="date" id="dueDateInput" class="form-control" dir="ltr" lang="en">
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" id="cancelBulkDueDate">Cancel</button>
                    <button class="btn btn-primary" id="confirmBulkDueDate">Update Due Date</button>
                </div>
            </div>
        </div>
    </div>

        <!-- Toast Notifications -->
        <div id="toastContainer" class="toast-container"></div>
    </div>

    <!-- Flatpickr + English date picker (for English-only date display) -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js" 
            data-fallback="https://unpkg.com/flatpickr/dist/flatpickr.min.js"></script>
    <script src="<?php echo asset('js/utils/english-date-picker.js'); ?>"></script>
    <script src="../../js/cases.js"></script>

<?php include_once '../../includes/footer.php'; ?>
