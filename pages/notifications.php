<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/notifications.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/notifications.php`.
 */
require_once '../includes/config.php';
require_once '../includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// Check if user has permission to view notifications
if (!hasPermission('view_notifications')) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// Set page variables for header.php
$pageTitle = "Notifications Management";
$pageCss = [
    asset('css/contact.css'),
    asset('css/notifications.css')
];
// Load after Bootstrap in footer — avoids Role Broadcast / modals running before bootstrap.bundle exists
// (header $pageJs runs in <head> before footer scripts; BS5 Modal was undefined → freeze / broken UI).
$pageJs = [];
$pageJsFooter = [
    asset('js/contact.js'),
    asset('js/notifications.js') . '?v=' . time() . '&nocache=' . uniqid()
];

require_once '../includes/header.php';
// Sidebar file is empty, skip it to avoid potential issues
// require_once '../includes/sidebar.php';
?>


<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-2">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Notification Management</h4>
                    <div class="btn-group flex-wrap gap-1 notif-toolbar-bulk-group">
                        <button class="btn btn-secondary btn-sm" data-action="go-back-contacts">
                            <i class="fas fa-arrow-left me-1"></i>Back to Contacts
                        </button>
                        <button class="btn btn-warning btn-sm" data-action="open-role-broadcast">
                            <i class="fas fa-bullhorn me-1"></i>Role Broadcast
                        </button>
                        <button class="btn btn-success btn-sm" data-action="send-bulk-notifications">
                            <i class="fas fa-paper-plane me-1"></i>Send Bulk Notifications
                        </button>
                        <button class="btn btn-info btn-sm" data-action="export-notifications">
                            <i class="fas fa-download me-1"></i>Export CSV
                        </button>
                        <button class="btn btn-primary btn-sm" data-action="refresh-notifications">
                            <i class="fas fa-sync me-1"></i>Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compact Notification Statistics -->
        <div class="row mb-2">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body py-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="card-title">Total</small>
                                <h6 class="mb-0" id="totalNotifications">0</h6>
                            </div>
                            <i class="fas fa-bell"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body py-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="card-title">Sent</small>
                                <h6 class="mb-0" id="sentNotifications">0</h6>
                            </div>
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body py-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="card-title">Pending</small>
                                <h6 class="mb-0" id="pendingNotifications">0</h6>
                            </div>
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body py-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="card-title">Failed</small>
                                <h6 class="mb-0" id="failedNotifications">0</h6>
                            </div>
                            <i class="fas fa-times"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compact Filters and Bulk Actions -->
        <div class="row mb-2">
            <div class="col-md-12">
                <div class="card bg-dark border-secondary">
                    <div class="card-body py-1">
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <select class="form-select form-select-sm" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="sent">Sent</option>
                                    <option value="failed">Failed</option>
                                    <option value="read">Read</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select form-select-sm" id="typeFilter">
                                    <option value="">All Types</option>
                                    <option value="contact_added">Contact Added</option>
                                    <option value="communication_added">Communication Added</option>
                                    <option value="contact_updated">Contact Updated</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Search notifications...">
                            </div>
                            <div class="col-md-1">
                                <button class="btn btn-primary btn-sm" data-action="filter-notifications">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label text-white me-2">Show:</label>
                                <select class="form-select form-select-sm d-inline-block w-auto" id="showEntries">
                                    <option value="5" selected>5</option>
                                    <option value="10">10</option>
                                    <option value="20">20</option>
                                    <option value="50">50</option>
                                </select>
                            </div>
                            <div class="col-md-3 text-end">
                                <div class="btn-group btn-group-sm notif-toolbar-bulk-group">
                                <button class="btn btn-outline-warning btn-sm" data-action="bulk-mark-read">
                                    <i class="fas fa-check"></i> Mark Read
                                </button>
                                <button class="btn btn-outline-info btn-sm" data-action="bulk-mark-unread">
                                    <i class="fas fa-undo"></i> Mark Unread
                                </button>
                                    <button class="btn btn-outline-danger btn-sm" data-action="bulk-delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" data-action="select-all">
                                        <i class="fas fa-check-square"></i> Select All
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compact Notifications Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="card bg-dark border-secondary">
                    <div class="card-header py-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-white">Notifications List</h6>
                            <span class="text-muted" id="selectedCount">0 selected</span>
                        </div>
                        <!-- Top Pagination -->
                        <div class="d-flex justify-content-center mt-1 notif-pagination-outer">
                            <div class="btn-group btn-group-sm notif-pagination-group">
                                <button class="btn btn-outline-primary btn-sm notif-page-btn" data-action="go-first-page" type="button">
                                    <i class="fas fa-angle-double-left"></i>
                                </button>
                                <button class="btn btn-outline-primary btn-sm notif-page-btn" data-action="go-previous-page" type="button">
                                    <i class="fas fa-angle-left"></i>
                                </button>
                                <span class="btn btn-outline-secondary btn-sm" id="pageInfo">Page 1</span>
                                <button class="btn btn-outline-primary btn-sm notif-page-btn" data-action="go-next-page" type="button">
                                    <i class="fas fa-angle-right"></i>
                                </button>
                                <button class="btn btn-outline-primary btn-sm notif-page-btn" data-action="go-last-page" type="button">
                                    <i class="fas fa-angle-double-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive notifications-table-scroll">
                            <table class="table table-hover table-dark mb-0 table-sm" id="notificationsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="col-id">ID</th>
                                        <th>Contact</th>
                                        <th class="col-type">Type</th>
                                        <th>Title</th>
                                        <th class="col-status">Status</th>
                                        <th>Country</th>
                                        <th>City</th>
                                        <th class="col-sent-date">Sent Date</th>
                                        <th class="col-read-date">Read Date</th>
                                        <th class="col-checkbox">
                                            <input type="checkbox" id="selectAllCheckbox">
                                        </th>
                                        <th class="col-actions">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="notificationsTableBody">
                                    <!-- Notifications will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Bottom Pagination -->
                        <div class="card-footer py-1">
                            <div class="d-flex justify-content-center align-items-center notif-pagination-outer">
                                <div class="btn-group btn-group-sm notif-pagination-group">
                                    <button class="btn btn-outline-primary btn-sm notif-page-btn" data-action="go-first-page">
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm notif-page-btn" data-action="go-previous-page">
                                        <i class="fas fa-angle-left"></i>
                                    </button>
                                    <span class="btn btn-outline-secondary btn-sm" id="bottomPageInfo">Page 1</span>
                                    <button class="btn btn-outline-primary btn-sm notif-page-btn" data-action="go-next-page">
                                        <i class="fas fa-angle-right"></i>
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm notif-page-btn" data-action="go-last-page">
                                        <i class="fas fa-angle-double-right"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="text-center mt-1">
                                <small class="text-muted" id="recordInfo">Showing 0 of 0 notifications</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Role Broadcast Modal -->
<div class="modal fade role-broadcast-modal" id="roleBroadcastModal" tabindex="-1" aria-labelledby="roleBroadcastLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="roleBroadcastLabel">Send Broadcast to Selected Roles</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="roleBroadcastForm">
                <div class="modal-body">
                    <div class="alert alert-secondary py-2">
                        Choose the audience groups you want to reach. You can use placeholders in the subject/body:
                        <code>{name}</code>, <code>{type}</code>, <code>{company}</code>.
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-5">
                            <div class="mb-3">
                                <label class="form-label text-uppercase small text-muted">Message Template (optional)</label>
                                <select class="form-select" id="roleBroadcastTemplate">
                                    <option value="">Select a template...</option>
                                    <option value="welcome">Welcome Message</option>
                                    <option value="follow_up">Follow Up</option>
                                    <option value="meeting_request">Meeting Request</option>
                                    <option value="contract_discussion">Contract Discussion</option>
                                    <option value="status_update">Status Update</option>
                                    <option value="payment_reminder">Payment Reminder</option>
                                    <option value="thank_you">Thank You</option>
                                    <option value="custom">Custom Message</option>
                                </select>
                                <small class="form-text text-muted">Selecting a template fills the subject and message. You can edit them after.</small>
                            </div>
                            <div class="role-broadcast-groups border border-secondary rounded-3 p-3">
                                <span class="text-uppercase text-muted small d-block mb-2">Audience Groups</span>
                                <div class="role-broadcast-grid">
                                    <div class="form-check">
                                        <input class="form-check-input role-broadcast-checkbox" type="checkbox" value="all_contacts" id="roleBroadcastAllContacts">
                                        <label class="form-check-label" for="roleBroadcastAllContacts">All Contacts</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input role-broadcast-checkbox" type="checkbox" value="agents" id="roleBroadcastAgents">
                                        <label class="form-check-label" for="roleBroadcastAgents">Agents</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input role-broadcast-checkbox" type="checkbox" value="subagents" id="roleBroadcastSubagents">
                                        <label class="form-check-label" for="roleBroadcastSubagents">Subagents</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input role-broadcast-checkbox" type="checkbox" value="workers" id="roleBroadcastWorkers">
                                        <label class="form-check-label" for="roleBroadcastWorkers">Workers</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input role-broadcast-checkbox" type="checkbox" value="customers" id="roleBroadcastCustomers">
                                        <label class="form-check-label" for="roleBroadcastCustomers">Customers</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input role-broadcast-checkbox" type="checkbox" value="vendors" id="roleBroadcastVendors">
                                        <label class="form-check-label" for="roleBroadcastVendors">Vendors</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input role-broadcast-checkbox" type="checkbox" value="prospects" id="roleBroadcastProspects">
                                        <label class="form-check-label" for="roleBroadcastProspects">Prospects</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input role-broadcast-checkbox" type="checkbox" value="partners" id="roleBroadcastPartners">
                                        <label class="form-check-label" for="roleBroadcastPartners">Partners</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input role-broadcast-checkbox" type="checkbox" value="employees" id="roleBroadcastEmployees">
                                        <label class="form-check-label" for="roleBroadcastEmployees">HR Employees</label>
                                    </div>
                                </div>
                                <small class="text-muted d-block mt-2">Select multiple roles as needed. Choosing "All Contacts" will ignore other selections.</small>
                            </div>
                        </div>
                        <div class="col-lg-7 d-flex flex-column">
                            <div class="mb-3">
                                <label for="roleBroadcastSubject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="roleBroadcastSubject" placeholder="e.g., Important update for {type}" required>
                            </div>
                            <div class="flex-grow-1 d-flex flex-column">
                                <label for="roleBroadcastMessage" class="form-label">Message</label>
                                <textarea class="form-control role-broadcast-message flex-grow-1" id="roleBroadcastMessage" rows="10" placeholder="Write your message... Use {name}, {type}, {company} to personalize." required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="roleBroadcastSubmit" class="btn btn-warning">
                        <i class="fas fa-paper-plane me-1"></i>Send Broadcast
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Notification Details Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="notificationModalLabel">Notification Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="notificationDetails">
                    <!-- Notification details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="resendNotificationBtn">Resend</button>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
