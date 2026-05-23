<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/contact.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/contact.php`.
 */
require_once "../includes/config.php";
require_once "../includes/permissions.php";

// Stay on Ratib Pro when ?control=1&agency_id= is present (sidebar SSO); do not bounce to control-panel control-hub.

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

// Check if user has permission to view contacts
if (!hasPermission('view_contacts')) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

$pageTitle = 'Contact Management';
$pageCss = [
    asset('css/contact.css'),
    asset('css/help-center-notification.css') . '?v=' . time(),
];
$pageJsFooter = [
    asset('js/countries-cities.js'),
    asset('js/contact.js'),
];

require_once '../includes/header.php';
?>

        <div class="container-fluid">
            <!-- Header Section -->
            <div class="row mb-1">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-handshake me-1"></i>Employer-Customer Communications
                        </h5>
                        <div class="btn-group btn-group-sm contact-btn-group-wrap">
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#contactModal" data-action="open-contact-modal">
                                <i class="fas fa-plus me-1"></i>Add Contact
                            </button>
                            <a href="<?php echo pageUrl('communications.php'); ?>" class="btn btn-success btn-sm">
                                <i class="fas fa-comments me-1"></i>Communications
                            </a>
                            <button class="btn btn-info btn-sm" data-action="open-notifications">
                                <i class="fas fa-bell me-1"></i>Notifications
                            </button>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Contacts Table -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header py-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Contacts List</h6>
                        <div class="btn-group btn-group-sm contact-btn-group-wrap">
                            <button class="btn btn-outline-primary btn-sm" data-action="load-contacts">
                                <i class="fas fa-sync"></i>
                            </button>
                            <button class="btn btn-outline-success btn-sm" data-action="open-contact-modal">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button class="btn btn-outline-info btn-sm" data-action="export-contacts">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="btn btn-outline-warning btn-sm" data-action="bulk-edit-contacts">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-sm" data-action="bulk-delete-contacts">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                            </div>
                            
                            <!-- Search and Filter -->
                            <div class="d-flex justify-content-between align-items-center mt-2 mb-1">
                                <div class="d-flex align-items-center">
                                    <div class="input-group input-group-sm me-3 contacts-search-group">
                                        <input type="text" class="form-control form-control-sm" id="contactsSearchInput" placeholder="Search contacts...">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-action="search-contacts">
                            <i class="fas fa-search"></i>
                        </button>
                                        <button class="btn btn-outline-danger btn-sm" type="button" data-action="clear-search" title="Clear Search">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                                    
                                    <select class="form-select form-select-sm me-3 contacts-filter-select" id="contactsStatusFilter">
                                        <option value="">All Status</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="archived">Archived</option>
                                    </select>
                                    
                                    <select class="form-select form-select-sm me-3 contacts-filter-select" id="contactsTypeFilter">
                        <option value="">All Types</option>
                        <option value="customer">Customer</option>
                        <option value="vendor">Vendor</option>
                        <option value="agent">Agent</option>
                        <option value="subagent">SubAgent</option>
                        <option value="worker">Worker</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                                
                                <div class="d-flex align-items-center">
                                    <label class="form-label me-2 mb-0">Show:</label>
                                    <select class="form-select form-select-sm contacts-per-page-select" id="contactsPerPage">
                                        <option value="5" selected>5</option>
                                        <option value="10">10</option>
                                        <option value="20">20</option>
                                        <option value="50">50</option>
                    </select>
                </div>
                            </div>
                            
                            <!-- Top Pagination -->
                            <div class="d-flex justify-content-between align-items-center mt-2 contact-pagination-row">
                                <div></div>
                                
                                <div class="btn-group btn-group-sm contact-page-controls">
                                    <button class="btn btn-outline-primary btn-sm contact-page-nav-btn" data-action="go-first-page" type="button">
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm contact-page-nav-btn" data-action="go-previous-page" type="button">
                                        <i class="fas fa-angle-left"></i>
                                    </button>
                                    <div class="btn-group btn-group-sm contact-page-num-group" id="contactsPageNumbers">
                                        <!-- Page numbers will be generated here -->
                </div>
                                    <button class="btn btn-outline-primary btn-sm contact-page-nav-btn" data-action="go-next-page" type="button">
                                        <i class="fas fa-angle-right"></i>
                        </button>
                                    <button class="btn btn-outline-primary btn-sm contact-page-nav-btn" data-action="go-last-page" type="button">
                                        <i class="fas fa-angle-double-right"></i>
                        </button>
            </div>

                                <div class="text-muted small" id="contactsRecordInfo">Showing 0 of 0 contacts</div>
                            </div>
                        </div>
                        <div class="card-body py-0">
                            <div class="table-responsive contacts-scroll">
                                <table class="table table-dark table-hover table-sm" id="contactsTable">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th class="col-id">ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Country</th>
                                            <th>City</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Last Contact</th>
                                            <th class="col-checkbox">
                                                <input type="checkbox" id="selectAllContacts">
                                            </th>
                                            <th class="col-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="contactsTableBody">
                                        <!-- Contacts will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Bottom Pagination -->
                            <div class="d-flex justify-content-between align-items-center mt-2 contact-pagination-row">
                                <div class="d-flex align-items-center contact-pagination-inner">
                                    <label class="form-label me-2 mb-0">Show:</label>
                                    <select class="form-select form-select-sm contacts-per-page-select" id="contactsPerPageBottom">
                                        <option value="5" selected>5</option>
                                        <option value="10">10</option>
                                        <option value="20">20</option>
                                        <option value="50">50</option>
                                    </select>
                                </div>
                                
                                <div class="btn-group btn-group-sm contact-page-controls">
                                    <button class="btn btn-outline-primary btn-sm contact-page-nav-btn" data-action="go-first-page" type="button">
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm contact-page-nav-btn" data-action="go-previous-page" type="button">
                                        <i class="fas fa-angle-left"></i>
                                    </button>
                                    <div class="btn-group btn-group-sm contact-page-num-group" id="contactsPageNumbersBottom">
                                        <!-- Page numbers will be generated here -->
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm contact-page-nav-btn" data-action="go-next-page" type="button">
                                        <i class="fas fa-angle-right"></i>
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm contact-page-nav-btn" data-action="go-last-page" type="button">
                                        <i class="fas fa-angle-double-right"></i>
                                    </button>
                                </div>
                                
                                <div class="text-muted small" id="contactsRecordInfoBottom">Showing 0 of 0 contacts</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Communications Timeline -->
            <div class="row mb-1">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header py-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i class="fas fa-comments me-2"></i>Recent Communications
                                </h6>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary btn-sm" data-action="load-communications">
                                        <i class="fas fa-sync"></i>
                                    </button>
                                    <a href="<?php echo pageUrl('communications.php'); ?>" class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-plus"></i>
                                    </a>
                                    <button class="btn btn-outline-warning btn-sm" data-action="bulk-mark-read">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" data-action="bulk-delete-communications">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body py-0">
                            <div id="communicationsTimeline" class="communications-scroll">
                                <!-- Communications will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- Contact Modal -->
    <div class="modal fade" id="contactModal" tabindex="-1" aria-labelledby="contactModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="contactModalLabel">Add/Edit Contact</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="contactForm">
                        <input type="hidden" id="contactId" name="id">
                        
                        <!-- Message Templates Section (Always Visible) -->
                        <div class="row mb-3">
                            <div class="col-12 mb-2">
                                <label class="form-label">Message Templates</label>
                                <select class="form-select" id="modalMessageTemplate" name="modal_message_template">
                                    <option value="">Select Message Template</option>
                                    <option value="welcome">Welcome Message</option>
                                    <option value="follow_up">Follow Up</option>
                                    <option value="meeting_request">Meeting Request</option>
                                    <option value="contract_discussion">Contract Discussion</option>
                                    <option value="status_update">Status Update</option>
                                    <option value="payment_reminder">Payment Reminder</option>
                                    <option value="thank_you">Thank You</option>
                                    <option value="custom">Custom Message</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Message Content Section -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label">Message Content</label>
                                <textarea class="form-control" id="modalMessageContent" name="modal_message_content" placeholder="Type your message here..." rows="3"></textarea>
                            </div>
                        </div>
                        
                        <hr class="my-3">
                        
                        <!-- Contact Details - 3 Column Layout -->
                        <div class="row mb-2">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Contact Type</label>
                                <select class="form-select" id="contactTypeSelect" name="contactTypeSelect">
                                    <option value="">Select Contact Type</option>
                                    <option value="agent">Agent</option>
                                    <option value="subagent">SubAgent</option>
                                    <option value="worker">Worker</option>
                                    <option value="hr">HR Employee</option>
                                    <option value="contact">Regular Contact</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Contact Name <span class="text-danger">*</span></label>
                                <select class="form-select" id="contactNameSelect" name="contactNameSelect">
                                    <option value="">Select Contact Type First</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                        </div>
                        
                        <div class="row mb-2">
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">Country</label>
                                <select class="form-select" id="country" name="country">
                                    <option value="">Select Country</option>
                                    <!-- Countries populated dynamically from System Settings via API -->
                                </select>
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label">City</label>
                                <select class="form-select" id="city" name="city">
                                    <option value="">Select City</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-action="save-contact">Save Contact</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Communication Modal -->
    <div class="modal fade" id="communicationModal" tabindex="-1" aria-labelledby="communicationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="communicationModalLabel">Employer-Customer Communication</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="communicationForm">
                        <input type="hidden" id="commContactId" name="contact_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Type</label>
                                <select class="form-select" id="commContactType" name="contact_type" required>
                                    <option value="">Select Contact Type *</option>
                                    <option value="agent">Agent</option>
                                    <option value="subagent">SubAgent</option>
                                    <option value="worker">Worker</option>
                                    <option value="hr">HR Employee</option>
                                    <option value="contact">Regular Contact</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Name</label>
                                <select class="form-select" id="commContact" name="contact_id" required>
                                    <option value="">Select Contact Type First</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Communication Type</label>
                                <select class="form-select" id="commType" name="communication_type" required>
                                    <option value="">Select Type *</option>
                                    <option value="email" selected>Email</option>
                                    <option value="phone">Phone Call</option>
                                    <option value="meeting">Meeting</option>
                                    <option value="video_call">Video Call</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="sms">SMS</option>
                                    <option value="letter">Letter</option>
                                    <option value="contract">Contract</option>
                                    <option value="follow_up">Follow Up</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Direction</label>
                                <select class="form-select" id="commDirection" name="direction" required>
                                    <option value="outbound">Outbound</option>
                                    <option value="inbound">Inbound</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select class="form-select" id="commPriority" name="priority">
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" id="commSubject" name="subject" required placeholder="Communication Subject">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea class="form-control" id="commMessage" name="message" rows="4" required placeholder="Detailed notes about the communication..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Outcome</label>
                                <select class="form-select" id="commOutcome" name="outcome">
                                    <option value="">Select Outcome</option>
                                    <option value="positive">Positive</option>
                                    <option value="neutral">Neutral</option>
                                    <option value="negative">Negative</option>
                                    <option value="follow_up_required">Follow Up Required</option>
                                    <option value="contract_signed">Contract Signed</option>
                                    <option value="deal_closed">Deal Closed</option>
                                    <option value="no_response">No Response</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Next Action</label>
                                <input type="text" class="form-control" id="commNextAction" name="next_action" placeholder="What should happen next?">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Follow Up Date</label>
                            <input type="date" class="form-control" id="commFollowUpDate" name="follow_up_date">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-action="save-communication">
                        <i class="fas fa-save me-2"></i>Save Communication
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Center Notification - Inline script for consistency -->
    <script>
    (function() {
        'use strict';
        if (window.helpCenterNotificationLoaded) return;
        window.helpCenterNotificationLoaded = true;
        var helpNotificationShown = false;
        
        function showHelpCenterNotification() {
            try {
                if (helpNotificationShown) return;
                if (!document.body) {
                    setTimeout(showHelpCenterNotification, 100);
                    return;
                }
                if (document.querySelector('.help-center-notification')) return;
                helpNotificationShown = true;
                
                var helpCenterUrl = '';
                try {
                    var appConfig = document.getElementById('app-config');
                    if (appConfig) {
                        var basePath = appConfig.getAttribute('data-base-path') || '';
                        helpCenterUrl = basePath + '/pages/help-center.php';
                    }
                } catch(e) {}
                
                if (!helpCenterUrl) {
                    var currentPath = window.location.pathname;
                    var basePath = currentPath.substring(0, currentPath.lastIndexOf('/'));
                    if (basePath.endsWith('/pages')) {
                        helpCenterUrl = basePath + '/help-center.php';
                    } else {
                        helpCenterUrl = basePath + '/pages/help-center.php';
                    }
                }
                
                var notification = document.createElement('div');
                notification.className = 'help-center-notification';
                notification.innerHTML = 
                    '<div class="help-center-notification-inner">' +
                        '<div class="help-center-notification-icon"><i class="fas fa-question-circle"></i></div>' +
                        '<div class="help-center-notification-content">' +
                            '<h4 class="help-center-notification-title">📚 Help & Learning Center Available!</h4>' +
                            '<p class="help-center-notification-text">Master the system with step-by-step guides, interactive tutorials, and expert tips.</p>' +
                        '</div>' +
                        '<div class="help-center-notification-actions">' +
                            '<button class="help-center-notification-btn" data-help-url="' + helpCenterUrl + '">Explore Now</button>' +
                            '<button class="help-center-notification-close" aria-label="Close"><i class="fas fa-times"></i></button>' +
                        '</div>' +
                    '</div>';
                
                document.body.appendChild(notification);
                void notification.offsetHeight;
                setTimeout(function() { notification.classList.add('show'); }, 100);
                
                var exploreBtn = notification.querySelector('.help-center-notification-btn');
                if (exploreBtn) {
                    exploreBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        var url = exploreBtn.getAttribute('data-help-url');
                        if (url) window.location.href = url;
                    });
                }
                
                var closeBtn = notification.querySelector('.help-center-notification-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        notification.classList.remove('show');
                        setTimeout(function() {
                            if (notification.parentNode) notification.parentNode.removeChild(notification);
                        }, 300);
                    });
                }
                
                setTimeout(function() {
                    if (notification.parentNode) {
                        notification.classList.remove('show');
                        setTimeout(function() {
                            if (notification.parentNode) notification.parentNode.removeChild(notification);
                        }, 300);
                    }
                }, 10000);
            } catch(e) {
                console.error('Error showing help center notification:', e);
            }
        }
        
        function init() {
            if (document.body && document.body.parentNode) {
                setTimeout(showHelpCenterNotification, 800);
            } else {
                setTimeout(init, 100);
            }
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
    </script>

<?php require_once '../includes/footer.php'; ?>
