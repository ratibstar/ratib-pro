<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/agent.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/agent.php`.
 */
require_once '../includes/config.php';
require_once '../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// Check if user has permission to view agents
if (!hasPermission('view_agents')) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
$pageTitle = "Agent Management";
$pageCss = [
    asset('css/agent/agent.css') . "?v=" . time() . "&force=1",
    asset('css/system-settings.css'),
    asset('css/accounting/input-states.css')
];
$pageJs = [
    asset('js/utils/offline-modal-handler.js'), // Load first to handle offline modal
    asset('js/agent/agents-data.js'),
    asset('js/module-history.js'),
    asset('js/utils/currencies-utils.js'),
    asset('js/countries-cities.js')
];

include '../includes/header.php';
?>

<div class="agent-page-content"> 
    <div class="page-header">
        <h5>Agents Management</h5>
    </div>

    <div class="table-container">
        <!-- Stats Cards -->
        <div class="stats-wrapper">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-info">
                    <div id="totalAgents" class="stat-value">0</div>
                    <div class="stat-label">Total Agents</div>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-check"></i>
                <div class="stat-info">
                    <div id="activeAgents" class="stat-value">0</div>
                    <div class="stat-label">Active Agents</div>
                </div>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-times"></i>
                <div class="stat-info">
                    <div id="inactiveAgents" class="stat-value">0</div>
                    <div class="stat-label">Inactive Agents</div>
                </div>
            </div>
            <div class="stat-card clickable-stat-card" id="agentHistoryCard">
                <i class="fas fa-history"></i>
                <div class="stat-info">
                    <div id="agentHistoryCount" class="stat-value">-</div>
                    <div class="stat-label">Activity History</div>
                </div>
            </div>
        </div>

        <!-- Controls Bar -->
        <div class="controls-bar">
            <!-- Single row: Search, Status, and Buttons -->
            <div class="controls-row-1">
                <div class="search-wrapper">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search by ID, name, email..." autocomplete="off">
                    <i class="fas fa-search"></i>
                </div>
                <select class="status-filter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <div class="controls-row-2">
                    <button class="btn add-btn" data-action="add-new" data-permission="add_agent">Add New</button>
                    <button class="btn btn-primary bulk-activate" disabled data-permission="edit_agent">Activate</button>
                    <button class="btn btn-warning bulk-deactivate" disabled data-permission="edit_agent">Deactivate</button>
                    <button class="btn btn-danger bulk-delete" disabled data-permission="delete_agent">Delete</button>
                </div>
            </div>
        </div>

        <!-- Pagination (Top) -->
        <div class="pagination-container top">
            <div class="pagination-info">
                Showing <span id="startRecordTop">0</span>-<span id="endRecordTop">0</span> of <span id="totalRecordsTop">0</span> entries
            </div>
            <div class="pagination-controls" id="paginationTop"></div>
            <div class="page-size">
                <select id="pageSizeTop" class="form-select">
                    <option value="5" selected>Show 5</option>
                    <option value="10">Show 10</option>
                    <option value="25">Show 25</option>
                    <option value="50">Show 50</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="table-scroll">
            <table id="agentsTable" class="agent-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="agentTableBody">
                    <!-- Table content will be loaded dynamically -->
                </tbody>
            </table>
        </div>

        <!-- Pagination (Bottom) -->
        <div class="pagination-container bottom">
            <div class="pagination-info">
                Showing <span id="startRecordBottom">0</span>-<span id="endRecordBottom">0</span> of <span id="totalRecordsBottom">0</span> entries
            </div>
            <div class="pagination-controls" id="paginationBottom"></div>
            <div class="page-size">
                <select id="pageSizeBottom" class="form-select">
                    <option value="5" selected>Show 5</option>
                    <option value="10">Show 10</option>
                    <option value="25">Show 25</option>
                    <option value="50">Show 50</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Add this right after your content-wrapper div -->
    <div class="loading-overlay">
        <div class="loading-spinner"></div>
    </div>
</div>

<!-- Modals -->
<!-- Add/Edit Agent Modal -->
<div class="modal" id="editAgentModal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2 id="modalTitle">Add New Agent</h2>
        <div class="modal-loading hidden">
            <div class="spinner"></div>
        </div>
        <form class="agent-form">
            <input type="hidden" name="id">
            <div class="form-group">
                <label for="full_name">Full Name *</label>
                <input type="text" name="full_name" required>
            </div>
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone *</label>
                <input type="tel" name="phone" required>
            </div>
            <div class="form-group">
                <label for="country">Country</label>
                <select name="country" id="country">
                    <option value="">Select Country</option>
                    <!-- Countries populated dynamically from System Settings via API -->
                </select>
            </div>
            <div class="form-group">
                <label for="city">City</label>
                <select name="city" id="city">
                    <option value="">Select Country First</option>
                </select>
            </div>
            <div class="form-group" data-full-width="true">
                <label for="address">Address</label>
                <textarea name="address"></textarea>
            </div>
            <div class="form-group" data-full-width="true">
                <label for="status">Status</label>
                <select name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="modal-footer form-buttons">
                <button type="button" class="btn-cancel">
                    <i class="fas fa-times"></i>
                    Cancel
                </button>
                <button type="submit" class="btn-save" data-permission="add_agent,edit_agent">
                    <i class="fas fa-save"></i>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
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


<?php include '../includes/footer.php';
?>
