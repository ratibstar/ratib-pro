<?php
/**
 * EN: Handles user-facing page rendering and page-level server flow in `pages/communications.php`.
 * AR: يدير عرض صفحات المستخدم وتدفق الخادم الخاص بالصفحة في `pages/communications.php`.
 */
require_once "../includes/config.php";
require_once "../includes/simple_warning.php";

if (!checkPermission("communication_view")) {
    showWarning("communication_view");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communications Management</title>
    <!-- Global Animations CSS - Loaded after contact.css to ensure overrides -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/nav.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset('css/contact.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo asset('css/help-center-notification.css'); ?>?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    
    <div id="app-config" 
         data-base-path="<?php echo htmlspecialchars(getBaseUrl(), ENT_QUOTES, 'UTF-8'); ?>"
         data-base-url="<?php echo htmlspecialchars(getBaseUrl(), ENT_QUOTES, 'UTF-8'); ?>"
         data-api-base="<?php echo htmlspecialchars(getBaseUrl() . '/api', ENT_QUOTES, 'UTF-8'); ?>"
         data-site-url="<?php echo htmlspecialchars(SITE_URL, ENT_QUOTES, 'UTF-8'); ?>"
         data-control-pro-bridge="<?php echo ratib_control_pro_bridge() ? '1' : '0'; ?>"
         class="hidden"></div>
    <script src="<?php echo asset('js/utils/header-config.js'); ?>"></script>
    <!-- Navigation JavaScript - MUST be loaded early -->
    <script src="<?php echo asset('js/navigation.js'); ?>?v=<?php echo time(); ?>"></script>
</head>

<body>
    <div class="nav-trigger-area"></div>

    <!-- Mobile Navigation Toggle -->
    <button class="nav-toggle" id="mobileNavToggle" aria-label="Toggle Navigation Menu">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Mobile Navigation Overlay -->
    <div class="nav-overlay" id="mobileNavOverlay"></div>

    <nav class="main-nav" id="mainNav">
        <div class="nav-brand">
            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='40'%3E%3Crect width='40' height='40' fill='%23ddd'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' dy='.3em' fill='%23666' font-size='12'%3ELogo%3C/text%3E%3C/svg%3E" 
                 alt="Logo" class="nav-logo">
        </div>
        <div class="nav-items">
            <a href="<?php echo htmlspecialchars(ratib_nav_url('dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link">
                <i class="nav-icon fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('agent.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link">
                <i class="nav-icon fas fa-users"></i>
                <span>Agent</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('subagent.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link">
                <i class="nav-icon fas fa-user-friends"></i>
                <span>SubAgent</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('Worker.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link">
                <i class="nav-icon fas fa-tools"></i>
                <span>Workers</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('cases/cases-table.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link">
                <i class="nav-icon fas fa-clipboard-list"></i>
                <span>Cases</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('hr.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link">
                <i class="nav-icon fas fa-user-tie"></i>
                <span>HR</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('reports.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link">
                <i class="nav-icon fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('contact.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link">
                <i class="nav-icon fas fa-phone"></i>
                <span>Contact</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('communications.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link active">
                <i class="nav-icon fas fa-comments"></i>
                <span>Communications</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_nav_url('notifications.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link">
                <i class="nav-icon fas fa-bell"></i>
                <span>Notifications</span>
            </a>
            <a href="<?php echo htmlspecialchars(ratib_logout_url(), ENT_QUOTES, 'UTF-8'); ?>" class="nav-item nav-link">
                <i class="nav-icon fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
            <?php if (false): ?>
            <a href="<?php echo pageUrl('system-settings.php'); ?>" class="nav-item nav-link">
                <i class="nav-icon fas fa-cog"></i>
                <span>System Settings</span>
            </a>
            <?php endif; ?>
        </div>
    </nav>
    
    <div class="content-wrapper">
        <div class="container-fluid">
            <!-- Header Section -->
            <div class="row mb-1">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-comments me-1"></i>Communications Management
                        </h5>
                        <div class="btn-group btn-group-sm">
                            <a href="<?php echo pageUrl('contact.php'); ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Back to Contacts
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Cards -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Total</h6>
                                    <h4 class="mb-0" id="statTotal">0</h4>
                                </div>
                                <i class="fas fa-comments fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Inbound</h6>
                                    <h4 class="mb-0" id="statInbound">0</h4>
                                </div>
                                <i class="fas fa-arrow-left fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Outbound</h6>
                                    <h4 class="mb-0" id="statOutbound">0</h4>
                                </div>
                                <i class="fas fa-arrow-right fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Urgent</h6>
                                    <h4 class="mb-0" id="statUrgent">0</h4>
                                </div>
                                <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="row mb-2">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body py-2">
                            <div class="d-flex align-items-center flex-wrap">
                                <div class="filter-search-width">
                                    <input type="text" class="form-control form-control-sm" id="commSearch" placeholder="Search by subject, contact name...">
                                </div>
                                <div class="filter-dropdown-width">
                                    <select class="form-select form-select-sm" id="commTypeFilter">
                                        <option value="">All Types</option>
                                        <option value="email">Email</option>
                                        <option value="phone">Phone</option>
                                        <option value="meeting">Meeting</option>
                                        <option value="whatsapp">WhatsApp</option>
                                        <option value="sms">SMS</option>
                                    </select>
                                </div>
                                <div class="filter-dropdown-width">
                                    <select class="form-select form-select-sm" id="commDirectionFilter">
                                        <option value="">All Directions</option>
                                        <option value="inbound">Inbound</option>
                                        <option value="outbound">Outbound</option>
                                    </select>
                                </div>
                                <div class="filter-dropdown-width">
                                    <select class="form-select form-select-sm" id="commPriorityFilter">
                                        <option value="">All Priorities</option>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                <div class="filter-clear-width">
                                    <button class="btn btn-outline-secondary btn-sm" data-action="clear-filters" title="Clear Filters">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="ms-auto d-flex align-items-center filter-actions comms-filter-actions">
                                    <button class="btn btn-primary btn-sm me-2" data-action="open-communication-modal">
                                        <i class="fas fa-plus"></i> New Communication
                                    </button>
                                    <button class="btn btn-info btn-sm me-2" data-action="export-communications">
                                        <i class="fas fa-download"></i> Export CSV
                                    </button>
                                    <button class="btn btn-secondary btn-sm me-2" id="bulkEditCommsBtn" disabled>
                                        <i class="fas fa-edit"></i> Bulk Edit
                                    </button>
                                    <button class="btn btn-warning btn-sm" id="bulkDeleteCommsBtn" disabled>
                                        <i class="fas fa-trash"></i> Bulk Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Communications Table -->
            <div class="row">
                <div class="col-12">
                    
                    <div class="card">
                        <div class="card-header py-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Communications List</h6>
                            </div>
                            
                            <!-- Top Pagination -->
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <div class="d-flex align-items-center">
                                    <label class="form-label me-2 mb-0">Show:</label>
                                    <select class="form-select form-select-sm" id="commContactsPerPageTop">
                                        <option value="5" selected>5</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                </div>
                                
                                <div class="btn-group btn-group-sm contact-page-controls">
                                    <button class="btn btn-outline-primary btn-sm contact-page-nav-btn" data-action="go-first-page" type="button">
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm contact-page-nav-btn" data-action="go-previous-page" type="button">
                                        <i class="fas fa-angle-left"></i>
                                    </button>
                                    <div class="btn-group btn-group-sm contact-page-num-group" id="commPageNumbers">
                                        <!-- Page numbers will be generated here -->
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm contact-page-nav-btn" data-action="go-next-page" type="button">
                                        <i class="fas fa-angle-right"></i>
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm contact-page-nav-btn" data-action="go-last-page" type="button">
                                        <i class="fas fa-angle-double-right"></i>
                                    </button>
                                </div>

                                <div class="text-muted small" id="commRecordInfoTop">Showing 0 of 0 communications</div>
                            </div>
                        </div>
                        
                        <div class="card-body p-0">
                            <div class="table-responsive communications-table-scroll">
                                <table class="table table-dark table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Contact</th>
                                            <th>Country</th>
                                            <th>City</th>
                                            <th>Type</th>
                                            <th>Direction</th>
                                            <th>Priority</th>
                                            <th>Subject</th>
                                            <th>Outcome</th>
                                            <th>Date/Time</th>
                                            <th class="checkbox-col-width">
                                                <input type="checkbox" id="selectAllComms">
                                            </th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="communicationsTableBody">
                                        <tr>
                                            <td colspan="12" class="text-center py-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Loading...</span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Bottom Pagination -->
                        <div class="card-footer py-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <label class="form-label me-2 mb-0">Show:</label>
                                    <select class="form-select form-select-sm" id="commContactsPerPageBottom">
                                        <option value="5" selected>5</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                </div>
                                
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary btn-sm" data-action="go-first-page">
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm" data-action="go-previous-page">
                                        <i class="fas fa-angle-left"></i>
                                    </button>
                                    <div class="btn-group btn-group-sm" id="commPageNumbersBottom">
                                        <!-- Page numbers will be generated here -->
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm" data-action="go-next-page">
                                        <i class="fas fa-angle-right"></i>
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm" data-action="go-last-page">
                                        <i class="fas fa-angle-double-right"></i>
                                    </button>
                                </div>
                                
                                <div class="text-muted small" id="commRecordInfoBottom">Showing 0 of 0 communications</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Communication Modal -->
    <div class="modal fade" id="communicationModal" tabindex="-1" aria-labelledby="communicationModalLabel" aria-hidden="true" dir="ltr" lang="en">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="communicationModalLabel">Add Communication</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body communication-modal-body">
                    <form id="communicationForm" class="communication-form">
                        <input type="hidden" id="commContactId" name="contact_id">
                        <div class="row">
                            <div class="col-md-6 communication-field-margin">
                                <label class="form-label communication-label">Contact Type</label>
                                <select class="form-select" id="commContactType" name="contact_type" required>
                                    <option value="">Select Contact Type *</option>
                                    <option value="agent">Agent</option>
                                    <option value="subagent">SubAgent</option>
                                    <option value="worker">Worker</option>
                                    <option value="hr">HR Employee</option>
                                    <option value="contact">Regular Contact</option>
                                </select>
                            </div>
                            <div class="col-md-6 communication-field-margin">
                                <label class="form-label communication-label">Contact Name</label>
                                <select class="form-select" id="commContact" name="contact_id" required>
                                    <option value="">Select Contact Type First</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 communication-field-margin">
                                <label class="form-label communication-label">Country</label>
                                <select class="form-select" id="commCountry" name="country">
                                    <option value="">Select Country</option>
                                    <!-- Options populated by JS -->
                                </select>
                            </div>
                            <div class="col-md-6 communication-field-margin">
                                <label class="form-label communication-label">City</label>
                                <select class="form-select" id="commCity" name="city">
                                    <option value="">Select Country First</option>
                                    <!-- Options populated by JS -->
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 communication-field-margin">
                                <label class="form-label communication-label">Communication Type</label>
                                <select class="form-select" id="commType" name="communication_type" required>
                                    <option value="">Select Type</option>
                                    <option value="email">Email</option>
                                    <option value="phone">Phone Call</option>
                                    <option value="meeting">Meeting</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="sms">SMS</option>
                                    <option value="letter">Letter</option>
                                </select>
                            </div>
                            <div class="col-md-4 communication-field-margin">
                                <label class="form-label communication-label">Direction</label>
                                <select class="form-select" id="commDirection" name="direction" required>
                                    <option value="">Select Direction</option>
                                    <option value="inbound">Inbound</option>
                                    <option value="outbound">Outbound</option>
                                </select>
                            </div>
                            <div class="col-md-4 communication-field-margin">
                                <label class="form-label communication-label">Priority</label>
                                <select class="form-select" id="commPriority" name="priority">
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 communication-field-margin">
                                <label class="form-label communication-label">Subject</label>
                                <select class="form-select" id="commSubject" name="subject" required>
                                    <option value="">Select Subject</option>
                                    <option value="Initial Contact">Initial Contact</option>
                                    <option value="Follow Up">Follow Up</option>
                                    <option value="Proposal Sent">Proposal Sent</option>
                                    <option value="Quote Requested">Quote Requested</option>
                                    <option value="Contract Discussion">Contract Discussion</option>
                                    <option value="Payment Discussion">Payment Discussion</option>
                                    <option value="Project Update">Project Update</option>
                                    <option value="Meeting Scheduled">Meeting Scheduled</option>
                                    <option value="Meeting Confirmation">Meeting Confirmation</option>
                                    <option value="Document Request">Document Request</option>
                                    <option value="Document Submitted">Document Submitted</option>
                                    <option value="Status Inquiry">Status Inquiry</option>
                                    <option value="Complaint">Complaint</option>
                                    <option value="Issue Resolution">Issue Resolution</option>
                                    <option value="Account Review">Account Review</option>
                                    <option value="Renewal Discussion">Renewal Discussion</option>
                                    <option value="Cancellation Request">Cancellation Request</option>
                                    <option value="Information Request">Information Request</option>
                                    <option value="Service Inquiry">Service Inquiry</option>
                                    <option value="Support Request">Support Request</option>
                                    <option value="Thank You">Thank You</option>
                                    <option value="Appointment Confirmation">Appointment Confirmation</option>
                                    <option value="Appointment Rescheduling">Appointment Rescheduling</option>
                                    <option value="Welcome Message">Welcome Message</option>
                                    <option value="Reminder">Reminder</option>
                                    <option value="Deadline Notice">Deadline Notice</option>
                                    <option value="General Inquiry">General Inquiry</option>
                                    <option value="Custom">Custom (Enter manually)</option>
                                </select>
                                <input type="text" class="form-control form-control-sm mt-1 d-none" id="commSubjectCustom" name="subject_custom" placeholder="Enter custom subject">
                            </div>
                        </div>
                        <div class="communication-field-margin">
                            <label class="form-label communication-label">Message</label>
                            <select class="form-select" id="commMessage" name="message" required>
                                <option value="">Select Message Template</option>
                                <option value="Thank you for your inquiry. We will get back to you soon.">Thank you for your inquiry. We will get back to you soon.</option>
                                <option value="We have received your request and are processing it.">We have received your request and are processing it.</option>
                                <option value="Your proposal has been sent. Please review and let us know if you have any questions.">Your proposal has been sent. Please review and let us know if you have any questions.</option>
                                <option value="Thank you for your interest. We look forward to working with you.">Thank you for your interest. We look forward to working with you.</option>
                                <option value="We would like to schedule a meeting to discuss this further. Please let us know your availability.">We would like to schedule a meeting to discuss this further. Please let us know your availability.</option>
                                <option value="Thank you for the meeting. We appreciate your time and will follow up as discussed.">Thank you for the meeting. We appreciate your time and will follow up as discussed.</option>
                                <option value="We have reviewed your documents and everything looks good. We will proceed with the next steps.">We have reviewed your documents and everything looks good. We will proceed with the next steps.</option>
                                <option value="We need the following documents to proceed: [Please specify]">We need the following documents to proceed: [Please specify]</option>
                                <option value="Your payment has been received. Thank you!">Your payment has been received. Thank you!</option>
                                <option value="We are working on resolving your issue and will update you shortly.">We are working on resolving your issue and will update you shortly.</option>
                                <option value="We apologize for any inconvenience. We are investigating the matter.">We apologize for any inconvenience. We are investigating the matter.</option>
                                <option value="Your account has been reviewed and everything is in order.">Your account has been reviewed and everything is in order.</option>
                                <option value="We would like to discuss renewing your service. Please let us know when would be a good time.">We would like to discuss renewing your service. Please let us know when would be a good time.</option>
                                <option value="We have received your cancellation request and will process it according to our policy.">We have received your cancellation request and will process it according to our policy.</option>
                                <option value="Welcome! We are excited to have you on board.">Welcome! We are excited to have you on board.</option>
                                <option value="This is a friendly reminder about your upcoming appointment/deadline.">This is a friendly reminder about your upcoming appointment/deadline.</option>
                                <option value="We confirm your appointment scheduled for [date/time].">We confirm your appointment scheduled for [date/time].</option>
                                <option value="We need to reschedule our appointment. Please suggest alternative dates.">We need to reschedule our appointment. Please suggest alternative dates.</option>
                                <option value="Please find attached the requested information.">Please find attached the requested information.</option>
                                <option value="We are available to answer any questions you may have.">We are available to answer any questions you may have.</option>
                                <option value="Your request has been forwarded to the appropriate department.">Your request has been forwarded to the appropriate department.</option>
                                <option value="We appreciate your patience and will update you as soon as possible.">We appreciate your patience and will update you as soon as possible.</option>
                                <option value="Custom">Custom (Enter manually)</option>
                            </select>
                            <textarea class="form-control form-control-sm mt-1 d-none" id="commMessageCustom" name="message_custom" rows="3" placeholder="Enter custom message..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 communication-field-margin">
                                <label class="form-label communication-label">Outcome</label>
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
                            <div class="col-md-6 communication-field-margin">
                                <label class="form-label communication-label">Next Action</label>
                                <select class="form-select" id="commNextAction" name="next_action">
                                    <option value="">Select Next Action</option>
                                    <option value="Follow up in 1 day">Follow up in 1 day</option>
                                    <option value="Follow up in 3 days">Follow up in 3 days</option>
                                    <option value="Follow up in 1 week">Follow up in 1 week</option>
                                    <option value="Follow up in 2 weeks">Follow up in 2 weeks</option>
                                    <option value="Follow up in 1 month">Follow up in 1 month</option>
                                    <option value="Send proposal">Send proposal</option>
                                    <option value="Send quote">Send quote</option>
                                    <option value="Schedule meeting">Schedule meeting</option>
                                    <option value="Request documents">Request documents</option>
                                    <option value="Review documents">Review documents</option>
                                    <option value="Process payment">Process payment</option>
                                    <option value="Update status">Update status</option>
                                    <option value="Escalate to manager">Escalate to manager</option>
                                    <option value="Close case">Close case</option>
                                    <option value="Archive communication">Archive communication</option>
                                    <option value="Add to calendar">Add to calendar</option>
                                    <option value="Send reminder">Send reminder</option>
                                    <option value="Request feedback">Request feedback</option>
                                    <option value="Forward to department">Forward to department</option>
                                    <option value="No action required">No action required</option>
                                    <option value="Wait for response">Wait for response</option>
                                    <option value="Update CRM">Update CRM</option>
                                    <option value="Custom">Custom (Enter manually)</option>
                                </select>
                                <input type="text" class="form-control form-control-sm mt-1 d-none" id="commNextActionCustom" name="next_action_custom" placeholder="Enter custom next action">
                            </div>
                        </div>
                        <div class="row" dir="ltr" lang="en">
                            <div class="col-md-6 communication-field-margin">
                                <label class="form-label communication-label">Communication Date & Time</label>
                                <input type="text" class="form-control comm-datetime-input" id="commDateTime" name="communication_date" value="" dir="ltr" lang="en" placeholder="YYYY-MM-DD HH:MM" autocomplete="off">
                            </div>
                            <div class="col-md-6 communication-field-margin">
                                <label class="form-label communication-label">Follow Up Date</label>
                                <input type="text" class="form-control date-input" id="commFollowUpDate" name="follow_up_date" dir="ltr" lang="en" placeholder="YYYY-MM-DD" autocomplete="off">
                                <small class="text-muted">Auto-filled based on Next Action</small>
                            </div>
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

    <!-- View Communication Modal -->
    <div class="modal fade" id="viewCommunicationModal" tabindex="-1" aria-labelledby="viewCommunicationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewCommunicationModalLabel">Communication Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="viewCommunicationContent">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-action="print-communication">
                        <i class="fas fa-print me-2"></i>Print
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" data-action="edit-viewed-communication">
                        <i class="fas fa-edit me-2"></i>Edit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Edit Modal -->
    <div class="modal fade" id="bulkEditModal" tabindex="-1" aria-labelledby="bulkEditModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkEditModalLabel">
                        <i class="fas fa-edit"></i> Bulk Edit Communications
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You have selected <span id="bulkEditCount">0</span> communication(s). Choose what you want to edit:</p>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <button class="btn btn-outline-primary w-100 bulk-option-btn" data-action="priority">
                                <i class="fas fa-flag"></i><br>
                                Change Priority
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-outline-primary w-100 bulk-option-btn" data-action="direction">
                                <i class="fas fa-exchange-alt"></i><br>
                                Change Direction
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-outline-primary w-100 bulk-option-btn" data-action="type">
                                <i class="fas fa-comments"></i><br>
                                Change Type
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-outline-primary w-100 bulk-option-btn" data-action="outcome">
                                <i class="fas fa-check-circle"></i><br>
                                Change Outcome
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Priority Modal -->
    <div class="modal fade" id="bulkPriorityModal" tabindex="-1" aria-labelledby="bulkPriorityModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkPriorityModalLabel">
                        <i class="fas fa-flag"></i> Change Priority
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You have selected <span id="bulkPriorityCount">0</span> communication(s). Choose the new priority:</p>
                    <div class="mb-3">
                        <label for="bulkPrioritySelect" class="form-label">Priority:</label>
                        <select id="bulkPrioritySelect" class="form-select">
                            <option value="">Select Priority</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updateBulkPriorityBtn">Update Priority</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Direction Modal -->
    <div class="modal fade" id="bulkDirectionModal" tabindex="-1" aria-labelledby="bulkDirectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkDirectionModalLabel">
                        <i class="fas fa-exchange-alt"></i> Change Direction
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You have selected <span id="bulkDirectionCount">0</span> communication(s). Choose the new direction:</p>
                    <div class="mb-3">
                        <label for="bulkDirectionSelect" class="form-label">Direction:</label>
                        <select id="bulkDirectionSelect" class="form-select">
                            <option value="">Select Direction</option>
                            <option value="inbound">Inbound</option>
                            <option value="outbound">Outbound</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updateBulkDirectionBtn">Update Direction</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Type Modal -->
    <div class="modal fade" id="bulkTypeModal" tabindex="-1" aria-labelledby="bulkTypeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkTypeModalLabel">
                        <i class="fas fa-comments"></i> Change Type
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You have selected <span id="bulkTypeCount">0</span> communication(s). Choose the new type:</p>
                    <div class="mb-3">
                        <label for="bulkTypeSelect" class="form-label">Type:</label>
                        <select id="bulkTypeSelect" class="form-select">
                            <option value="">Select Type</option>
                            <option value="email">Email</option>
                            <option value="phone">Phone Call</option>
                            <option value="meeting">Meeting</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="sms">SMS</option>
                            <option value="letter">Letter</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updateBulkTypeBtn">Update Type</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Outcome Modal -->
    <div class="modal fade" id="bulkOutcomeModal" tabindex="-1" aria-labelledby="bulkOutcomeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkOutcomeModalLabel">
                        <i class="fas fa-check-circle"></i> Change Outcome
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You have selected <span id="bulkOutcomeCount">0</span> communication(s). Choose the new outcome:</p>
                    <div class="mb-3">
                        <label for="bulkOutcomeSelect" class="form-label">Outcome:</label>
                        <select id="bulkOutcomeSelect" class="form-select">
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
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="updateBulkOutcomeBtn">Update Outcome</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Configuration - Passed via data attributes -->
    <div id="app-config" 
         data-base-path="<?php echo htmlspecialchars(getBaseUrl(), ENT_QUOTES, 'UTF-8'); ?>"
         data-base-url="<?php echo htmlspecialchars(getBaseUrl(), ENT_QUOTES, 'UTF-8'); ?>"
         data-api-base="<?php echo htmlspecialchars(getBaseUrl() . '/api', ENT_QUOTES, 'UTF-8'); ?>"
         data-site-url="<?php echo htmlspecialchars(SITE_URL, ENT_QUOTES, 'UTF-8'); ?>"
         class="hidden"></div>
    <script src="<?php echo asset('js/utils/header-config.js'); ?>"></script>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.js" data-fallback="https://unpkg.com/flatpickr/dist/flatpickr.min.js"></script>
    <script src="<?php echo asset('js/utils/english-date-picker.js'); ?>"></script>
    <script src="<?php echo asset('js/countries-cities.js'); ?>"></script>
    <!-- Help Center Notification - Inline script for consistency -->
    <script>
    (function() {
        'use strict';
        if (window.helpCenterNotificationLoaded) return;
        window.helpCenterNotificationLoaded = true;
        
        function showHelpCenterNotification() {
            try {
                if (!document.body) {
                    setTimeout(showHelpCenterNotification, 100);
                    return;
                }
                var existing = document.querySelector('.help-center-notification');
                if (existing) return;
                
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
            function tryShow() {
                if (document.body && document.body.parentNode) {
                    if (!document.querySelector('.help-center-notification')) {
                        setTimeout(showHelpCenterNotification, 800);
                    }
                } else {
                    setTimeout(tryShow, 100);
                }
            }
            tryShow();
        }
        
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
        
        window.addEventListener('load', function() {
            if (!document.querySelector('.help-center-notification')) {
                setTimeout(showHelpCenterNotification, 800);
            }
        }, { once: true });
    })();
    </script>
    <script src="<?php echo asset('js/communications.js'); ?>"></script>
    
    <!-- Navigation handled by global navigation.js (already loaded in header.php) -->
</body>
</html>

