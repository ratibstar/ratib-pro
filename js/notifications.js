/**
 * EN: Implements frontend interaction behavior in `js/notifications.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/notifications.js`.
 */
// Wrap in IIFE to avoid variable conflicts with contact.js
(function() {
'use strict';

// Helper function to get API base URL
function getApiBase() {
    // Try multiple fallback methods to get API base
    if (window.APP_CONFIG && window.APP_CONFIG.apiBase) {
        return window.APP_CONFIG.apiBase;
    }
    if (window.API_BASE) {
        return window.API_BASE;
    }
    // Fallback: construct from base path or use relative path
    const basePath = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || window.BASE_PATH || '';
    if (basePath) {
        return basePath + '/api';
    }
    // Last resort: use relative path from pages/ directory
    return '../api';
}

// Helper function to get base URL
function getBaseUrl() {
    return (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
}

/** Bootstrap 5 modal (no jQuery .modal() — removed in BS5) */
function getBsModalInstance(element) {
    if (!element || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        return null;
    }
    const Modal = bootstrap.Modal;
    if (typeof Modal.getOrCreateInstance === 'function') {
        return Modal.getOrCreateInstance(element, { focus: false, backdrop: true, keyboard: true });
    }
    return Modal.getInstance(element) || new Modal(element, { focus: false, backdrop: true, keyboard: true });
}

/**
 * Role broadcast: must sit under document.body so backdrop/stacking works (modals inside
 * .content-wrapper + transformed nav can trap clicks or break focus). focus:false avoids
 * rare BS5 focus-enforcement loops with high z-index widgets (e.g. chat button).
 */
function showRoleBroadcastModal() {
    const el = document.getElementById('roleBroadcastModal');
    if (!el) return;

    const open = () => {
        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return;
        }
        if (el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
        const Modal = bootstrap.Modal;
        const existing = Modal.getInstance(el);
        if (existing) {
            existing.dispose();
        }
        const inst = new Modal(el, { focus: false, backdrop: true, keyboard: true });
        inst.show();
    };

    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        setTimeout(open, 0);
    } else {
        let tries = 0;
        const wait = () => {
            tries++;
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                open();
            } else if (tries < 80) {
                setTimeout(wait, 50);
            } else {
                console.error('[notifications] Bootstrap Modal not available; load bootstrap.bundle before this script or move scripts to footer order.');
            }
        };
        wait();
    }
}

function hideRoleBroadcastModal() {
    const el = document.getElementById('roleBroadcastModal');
    if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
    const inst = bootstrap.Modal.getInstance(el);
    if (inst) inst.hide();
}

// Helper function to escape HTML to prevent XSS
function escapeHtml(text) {
    if (text == null || text === undefined) {
        return '';
    }
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Declare variables - isolated scope prevents conflicts
let currentPage = 1;
let totalPages = 1;
let totalRecords = 0;
let currentFilters = {};
let selectedNotifications = [];
let currentLimit = 5; // Default to 5 entries

const roleBroadcastTemplates = {
    welcome: {
        subject: 'Welcome to Our Contact Management System',
        message: `Dear {name},

Welcome to our services! We are excited to work with you and look forward to building a strong partnership.

If you have any questions or need assistance, please don't hesitate to contact us.

Best regards,
Your Team`
    },
    follow_up: {
        subject: 'Following Up on Our Conversation',
        message: `Hello {name},

I hope this message finds you well. I wanted to follow up on our previous conversation and check if you have any questions or need any additional information.

Please let me know if there's anything I can help you with.

Best regards,
Your Team`
    },
    meeting_request: {
        subject: 'Meeting Request',
        message: `Dear {name},

I hope you're doing well. I would like to schedule a meeting to discuss our services and how we can best assist you.

Please let me know your availability for the following times:
- [Insert preferred dates/times]

Looking forward to hearing from you.

Best regards,
Your Team`
    },
    contract_discussion: {
        subject: 'Contract Discussion',
        message: `Hello {name},

I hope this message finds you well. I wanted to discuss the contract details and ensure we address all your requirements.

Please review the attached contract and let me know if you have any questions or need any modifications.

Best regards,
Your Team`
    },
    status_update: {
        subject: 'Current Status Update',
        message: `Dear {name},

I wanted to provide you with an update on your current status and next steps.

[Insert specific status information]

If you have any questions, please don't hesitate to contact me.

Best regards,
Your Team`
    },
    payment_reminder: {
        subject: 'Friendly Payment Reminder',
        message: `Dear {name},

I hope you're doing well. This is a friendly reminder that your payment is due.

Payment Details:
- Amount: [Insert amount]
- Due Date: [Insert due date]
- Payment Method: [Insert payment method]

Please let me know if you have any questions or need assistance with the payment process.

Best regards,
Your Team`
    },
    thank_you: {
        subject: 'Thank You',
        message: `Dear {name},

Thank you for choosing our services! We truly appreciate your business and look forward to working with you.

Your satisfaction is our priority, and we're committed to providing you with the best service possible.

Best regards,
Your Team`
    },
    custom: {
        subject: '',
        message: ''
    }
};

$(document).ready(function() {
    loadNotifications();
    loadStatistics();
    
    // Filter event handlers
    $('#statusFilter, #typeFilter').on('change', function() {
        filterNotifications();
    });
    
    // Handle show entries change
    $('#showEntries').on('change', function() {
        changeShowEntries();
    });
    
    // Handle select all checkbox
    $('#selectAllCheckbox').on('change', function() {
        toggleSelectAll();
    });
    
    // Fix search functionality - trigger on input change and Enter key
    $('#searchInput').on('input', function() {
        filterNotifications();
    });
    
    $('#searchInput').on('keypress', function(e) {
        if (e.which === 13) {
            filterNotifications();
        }
    });
    
    // Button event handlers using event delegation
    $(document).on('click', '[data-action="go-back-contacts"]', function() {
        goBackToContacts();
    });
    
    $(document).on('click', '[data-action="send-bulk-notifications"]', function() {
        sendBulkNotifications();
    });

    $(document).on('click', '[data-action="open-role-broadcast"]', function(e) {
        e.preventDefault();
        showRoleBroadcastModal();
    });

    $('#roleBroadcastForm').on('submit', function(e) {
        e.preventDefault();
        sendRoleBroadcast();
    });

    $(document).on('change', '.role-broadcast-checkbox', function() {
        if (this.value === 'all_contacts' && this.checked) {
            $('.role-broadcast-checkbox').not(this).prop('checked', false);
        } else if (this.value !== 'all_contacts' && this.checked) {
            $('#roleBroadcastAllContacts').prop('checked', false);
        }
    });
    
    $(document).on('click', '[data-action="refresh-notifications"]', function() {
        refreshNotifications();
    });
    
    $('#roleBroadcastTemplate').on('change', function() {
        const templateKey = $(this).val();
        if (!templateKey) {
            return;
        }
        applyRoleBroadcastTemplate(templateKey);
    });

    $(document).on('click', '[data-action="filter-notifications"]', function() {
        filterNotifications();
    });
    
    $(document).on('click', '[data-action="bulk-mark-read"]', function() {
        bulkMarkAsRead();
    });
    
    $(document).on('click', '[data-action="bulk-mark-unread"]', function() {
        bulkMarkAsUnread();
    });
    
    $(document).on('click', '[data-action="bulk-delete"]', function() {
        bulkDelete();
    });
    
    $(document).on('click', '[data-action="select-all"]', function() {
        selectAll();
    });
    
    $(document).on('click', '[data-action="go-first-page"]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
            return false;
        }
        loadNotifications(1);
        return false;
    });
    
    $(document).on('click', '[data-action="go-previous-page"]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
            return false;
        }
        previousPage();
        return false;
    });
    
    $(document).on('click', '[data-action="go-next-page"]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
            return false;
        }
        nextPage();
        return false;
    });
    
    $(document).on('click', '[data-action="go-last-page"]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
            return false;
        }
        loadNotifications(totalPages);
        return false;
    });
    
    $(document).on('click', '[data-action="resend-notification"]', function() {
        resendNotification();
    });
    
    // Dynamic action handlers for dynamically generated content
    $(document).on('change', '[data-action="update-selection"]', function() {
        updateSelection();
    });
    
    $(document).on('click', '[data-action="view-notification"]', function() {
        const notificationId = $(this).data('notification-id');
        viewNotification(notificationId);
    });
    
    $(document).on('click', '[data-action="resend-notification-id"]', function() {
        const notificationId = $(this).data('notification-id');
        resendNotification(notificationId);
    });
    
    // Handle resend button in modal
    $(document).on('click', '#resendNotificationBtn', function() {
        const modalElement = document.getElementById('notificationModal');
        if (modalElement && modalElement.dataset.notificationId) {
            const notificationId = parseInt(modalElement.dataset.notificationId);
            resendNotification(notificationId);
            
            // Close modal after resend
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        }
    });
    
    $(document).on('click', '[data-action="export-notifications"]', function() {
        exportNotifications();
    });
    
    $(document).on('click', '[data-action="mark-as-read"]', function() {
        const notificationId = $(this).data('notification-id');
        markAsRead(notificationId);
    });
    
    $(document).on('click', '[data-action="mark-as-unread"]', function() {
        const notificationId = $(this).data('notification-id');
        markAsUnread(notificationId);
    });
    
    $(document).on('click', '[data-action="export-notifications"]', function() {
        exportNotifications();
    });
    
    $(document).on('click', '[data-action="refresh-notifications"]', function() {
        loadNotifications(currentPage);
        loadStatistics();
    });
});

function loadNotifications(page = 1) {
    currentPage = page;
    
    const params = new URLSearchParams({
        limit: currentLimit,
        offset: (page - 1) * currentLimit,
        ...currentFilters
    });
    
    const notificationsUrl = getApiBase() + '/notifications/notifications-api.php?action=get_notifications';
    
    $.ajax({
        url: notificationsUrl,
        method: 'GET',
        data: params.toString(),
        dataType: 'json',
        cache: false,
        success: function(response) {
            if (response.success) {
                const notifications = response.data || [];
                displayNotifications(notifications);
                // Update pagination info if available
                if (response.pagination) {
                    totalPages = response.pagination.total_pages || 1;
                    totalRecords = response.pagination.total_records || notifications.length;
                } else {
                    totalPages = Math.ceil(notifications.length / currentLimit) || 1;
                    totalRecords = notifications.length || 0;
                }
                updatePageInfo();
            } else {
                showNotification('Error loading notifications: ' + (response.message || 'Unknown error'), 'error');
                displayNotifications([]); // Show empty state on error
            }
        },
        error: function(xhr, status, error) {
            // Notifications API error - show notification instead
            showNotification('Error loading notifications: ' + error, 'error');
        }
    });
}

function displayNotifications(notifications) {
    const tbody = $('#notificationsTableBody');
    tbody.empty();
    
    if (!notifications || !Array.isArray(notifications) || notifications.length === 0) {
        tbody.append(`
            <tr class="animated-row">
                <td colspan="11" class="text-center text-muted">No notifications found</td>
            </tr>
        `);
        return;
    }
    
    notifications.forEach(function(notification) {
        const statusBadge = getStatusBadge(notification.status);
        const typeBadge = getTypeBadge(notification.notification_type);
        const isSelected = selectedNotifications.includes(notification.id);
        
        // Format ID as N0001, N0002, etc.
        const formattedId = `N${String(notification.id).padStart(4, '0')}`;
        
        // Display contact name (prefer display_contact_name from API, fallback to contact_name)
        const contactInfo = escapeHtml(notification.display_contact_name || notification.contact_name || notification.contact_email || '-');
        
        // Clean title - remove "Communication Added" prefix if present
        let cleanTitle = notification.title || '-';
        if (cleanTitle !== '-') {
            cleanTitle = cleanTitle.replace(/^Communication\s+Added\s*/i, '');
            cleanTitle = cleanTitle.replace(/^nmunication\s+Add\s*/i, '');
            cleanTitle = cleanTitle.trim() || notification.title || '-';
        }
        cleanTitle = escapeHtml(cleanTitle);
        const titleAttr = escapeHtml(notification.title || '');
        
        const country = escapeHtml(notification.contact_country || notification.country || '-');
        const city = escapeHtml(notification.contact_city || notification.city || '-');
        
        const row = `
            <tr class="animated-row">
                <td class="small">${formattedId}</td>
                <td class="small">${contactInfo}</td>
                <td>${typeBadge}</td>
                <td class="small" title="${titleAttr}">${cleanTitle}</td>
                <td>${statusBadge}</td>
                <td class="small">${country}</td>
                <td class="small">${city}</td>
                <td class="small">${notification.sent_at ? formatDateTime(notification.sent_at) : '-'}</td>
                <td class="small">${notification.read_at ? formatDateTime(notification.read_at) : '-'}</td>
                <td>
                    <input type="checkbox" class="notification-checkbox" value="${notification.id}" ${isSelected ? 'checked' : ''} data-action="update-selection">
                </td>
                <td>
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-info" data-action="view-notification" data-notification-id="${notification.id}" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning" data-action="resend-notification-id" data-notification-id="${notification.id}" title="Resend">
                            <i class="fas fa-redo"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success" data-action="mark-as-read" data-notification-id="${notification.id}" title="Mark as Read">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning" data-action="mark-as-unread" data-notification-id="${notification.id}" title="Mark as Unread">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
    
    updatePageInfo();
}

function loadStatistics() {
    const statsUrl = getApiBase() + '/notifications/notifications-api.php?action=get_statistics';
    
    $.ajax({
        url: statsUrl,
        method: 'GET',
        dataType: 'json',
        cache: false, // Prevent caching to ensure fresh statistics
        success: function(response) {
            if (response.success) {
                const stats = response.data;
                
                // Update the statistics cards
                $('#totalNotifications').text(stats.total);
                $('#sentNotifications').text(stats.sent);
                $('#pendingNotifications').text(stats.pending);
                $('#failedNotifications').text(stats.failed);
            } else {
                // Error loading statistics - show notification instead
            }
        },
        error: function(xhr, status, error) {
            // Error loading statistics - show notification instead
        }
    });
}

function filterNotifications() {
    currentFilters = {
        status: $('#statusFilter').val(),
        notification_type: $('#typeFilter').val(), // Fixed parameter name for API
        search: $('#searchInput').val()
    };
    
    // Remove empty filters
    Object.keys(currentFilters).forEach(key => {
        if (!currentFilters[key]) {
            delete currentFilters[key];
        }
    });
    
    loadNotifications(1);
}

function viewNotification(notificationId) {
    // Fetch notification details from API
    $.ajax({
        url: getApiBase() + '/notifications/notifications-api.php?action=get_notifications',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const notification = response.data.find(n => n.id == notificationId);
                if (notification) {
                    showNotificationDetails(notification);
                } else {
                    showNotification('Notification not found', 'error');
                }
            } else {
                showNotification('Error loading notification details: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error loading notification details: ' + error, 'error');
        }
    });
}

function showNotificationDetails(notification) {
    // Escape user content to prevent XSS
    const name = escapeHtml(notification.display_contact_name || notification.contact_name || 'Not provided');
    const email = escapeHtml(notification.contact_email || 'Not provided');
    const phone = escapeHtml(notification.contact_phone || 'Not provided');
    const country = escapeHtml(notification.contact_country || notification.country || 'Not provided');
    const city = escapeHtml(notification.contact_city || notification.city || 'Not provided');
    const title = escapeHtml(notification.title || 'No title');
    // Message content can contain HTML from email templates, so we preserve it but remove script tags for security
    // The message is already sanitized by the API, but we strip script tags just in case
    const message = notification.message ? (notification.message.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '').replace(/javascript:/gi, '')) : 'No message content';
    
    const details = `
        <div class="row">
            <div class="col-md-6">
                <h6>Contact Information</h6>
                <p><strong>Name:</strong> ${name}</p>
                <p><strong>Email:</strong> ${email}</p>
                <p><strong>Phone:</strong> ${phone}</p>
                <p><strong>Country:</strong> ${country}</p>
                <p><strong>City:</strong> ${city}</p>
            </div>
            <div class="col-md-6">
                <h6>Notification Details</h6>
                <p><strong>Type:</strong> ${getTypeBadge(notification.notification_type)}</p>
                <p><strong>Status:</strong> ${getStatusBadge(notification.status)}</p>
                <p><strong>Created:</strong> ${formatDateTime(notification.created_at)}</p>
                <p><strong>Sent:</strong> ${notification.sent_at ? formatDateTime(notification.sent_at) : 'Not sent'}</p>
                <p><strong>Read:</strong> ${notification.read_at ? formatDateTime(notification.read_at) : 'Not read'}</p>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-md-12">
                <h6>Message</h6>
                <div class="border p-3 rounded">
                    <h6>${title}</h6>
                    <div>${message}</div>
                </div>
            </div>
        </div>
    `;
    
    $('#notificationDetails').html(details);
    
    // Use Bootstrap 5 Modal API
    const modalElement = document.getElementById('notificationModal');
    if (modalElement) {
        // Get existing instance or create new one
        let modal = bootstrap.Modal.getInstance(modalElement);
        if (!modal) {
            modal = new bootstrap.Modal(modalElement);
        }
        modal.show();
        
        // Store notification data for resend functionality
        modalElement.dataset.notificationId = notification.id;
        modalElement.dataset.notificationData = JSON.stringify(notification);
        } else {
            // Notification modal element not found - silently fail
        }
}

function resendNotification(notificationId) {
    if (!confirm('Are you sure you want to resend this notification?')) {
        return;
    }
    
    // Get notification data from stored data or fetch from API
    let notificationData = null;
    const modalElement = document.getElementById('notificationModal');
    if (modalElement && modalElement.dataset.notificationData) {
        try {
            notificationData = JSON.parse(modalElement.dataset.notificationData);
        } catch (e) {
            // Error parsing notification data - silently fail
        }
    }
    
    // If not in modal, fetch from API
    if (!notificationData) {
        $.ajax({
            url: getApiBase() + '/notifications/notifications-api.php?action=get_notifications',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const notification = response.data.find(n => n.id == notificationId);
                    if (notification) {
                        sendResendRequest(notification);
                    } else {
                        showNotification('Notification not found', 'error');
                    }
                } else {
                    showNotification('Error loading notification: ' + response.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error loading notification: ' + error, 'error');
            }
        });
    } else {
        sendResendRequest(notificationData);
    }
}

// RESEND = EDIT - 100% IDENTICAL CODE PATH
// Convert HTML to plain text using textContent (same as textarea)
function sendResendRequest(notification, callback) {
    let messageContent = notification.message || '';
    
    // Convert HTML to plain text - EXACTLY what textarea shows
    if (messageContent) {
        const div = document.createElement('div');
        div.innerHTML = messageContent;
        messageContent = (div.textContent || div.innerText || '').trim();
    }
    
    const resendSubject = `[Resend ${new Date().toLocaleString()}] ${notification.title || 'Notification'}`;

    // Send EXACT same payload as edit (contact.js line 2049-2058)
    const payload = {
        contact_id: notification.contact_id,
        contact_name: notification.display_contact_name || notification.contact_name,
        contact_email: notification.contact_email || '',
        contact_phone: notification.contact_phone || '',
        contact_type: notification.contact_type || 'Contact',
        company: notification.company || '',
        message_template: '',
        message_content: messageContent,
        subject: resendSubject,
        is_resend: true
    };
    
    // Call API directly (same as sendContactNotification line 2060)
    $.ajax({
        url: getApiBase() + '/notifications/notifications-api.php?action=send_contact_notification',
        method: 'POST',
        data: JSON.stringify(payload),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                if (callback) callback(true);
                else {
                    showNotification('Notification resent successfully', 'success');
                    loadNotifications(currentPage);
                }
            } else {
                if (callback) callback(false);
                else showNotification('Error: ' + (response.message || 'Unknown error'), 'error');
            }
        },
        error: function(xhr, status, error) {
            if (callback) callback(false);
            else showNotification('Error: ' + error, 'error');
        }
    });
}

function markAsRead(notificationId) {
    // Update the status in the table immediately
    const notificationRow = $(`.notification-checkbox[value="${notificationId}"]`).closest('tr');
    if (notificationRow.length > 0) {
        const statusCell = notificationRow.find('td:nth-child(5)');
        statusCell.html('<span class="badge bg-info">Read</span>');
        
        const readDateCell = notificationRow.find('td:nth-child(7)');
        readDateCell.text(new Date().toLocaleString());
    }
    
    // Send to API
    $.ajax({
        url: getApiBase() + '/notifications/notifications-api.php?action=mark_notification_read',
        method: 'POST',
        data: JSON.stringify({ notification_id: notificationId }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Don't show success message to avoid duplicate messages
                loadStatistics();
            } else {
                showNotification('Error marking notification as read: ' + response.message, 'error');
                // Revert the UI change if API failed
                loadNotifications(currentPage);
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error marking notification as read: ' + error, 'error');
            // Revert the UI change if API failed
            loadNotifications(currentPage);
        }
    });
}

function markAsUnread(notificationId) {
    // Update the status in the table immediately
    const notificationRow = $(`.notification-checkbox[value="${notificationId}"]`).closest('tr');
    if (notificationRow.length > 0) {
        const statusCell = notificationRow.find('td:nth-child(5)');
        statusCell.html('<span class="badge bg-warning">Pending</span>');
        
        const readDateCell = notificationRow.find('td:nth-child(7)');
        readDateCell.text('-');
    }
    
    // Send to API
    $.ajax({
        url: getApiBase() + '/notifications/notifications-api.php?action=mark_notification_unread',
        method: 'POST',
        data: JSON.stringify({ notification_id: notificationId }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Refresh the notifications to ensure persistence
                loadNotifications(currentPage);
                loadStatistics();
            } else {
                showNotification('Error marking notification as unread: ' + response.message, 'error');
                // Revert the UI change if API failed
                loadNotifications(currentPage);
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error marking notification as unread: ' + error, 'error');
            // Revert the UI change if API failed
            loadNotifications(currentPage);
        }
    });
}

function sendBulkNotifications() {
    if (!confirm('This will send notifications to all contacts. Are you sure?')) {
        return;
    }
    
    // Get selected notifications
    const selectedIds = selectedNotifications.map(id => parseInt(id));
    
    if (selectedIds.length === 0) {
        showNotification('Please select at least one notification to resend', 'warning');
        return;
    }
    
    if (!confirm(`Are you sure you want to resend ${selectedIds.length} notification(s)?`)) {
        return;
    }
    
    // Resend each selected notification
    let successCount = 0;
    let failCount = 0;
    let processed = 0;
    
    selectedIds.forEach(function(notificationId) {
        // Fetch notification data and resend
        $.ajax({
            url: getApiBase() + '/notifications/notifications-api.php?action=get_notifications',
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const notification = response.data.find(n => n.id == notificationId);
                    if (notification) {
                        sendResendRequest(notification, function(success) {
                            processed++;
                            if (success) {
                                successCount++;
                            } else {
                                failCount++;
                            }
                            
                            // Check if all are processed
                            if (processed === selectedIds.length) {
                                if (failCount === 0) {
                                    showNotification(`Successfully resent ${successCount} notification(s)`, 'success');
                                } else {
                                    showNotification(`Resent ${successCount} notification(s), ${failCount} failed`, 'warning');
                                }
                                loadNotifications(currentPage);
                                selectedNotifications = [];
                                updateSelection();
                            }
                        });
                    } else {
                        processed++;
                        failCount++;
                        if (processed === selectedIds.length) {
                            showNotification(`Resent ${successCount} notification(s), ${failCount} failed`, 'warning');
                            loadNotifications(currentPage);
                        }
                    }
                } else {
                    processed++;
                    failCount++;
                    if (processed === selectedIds.length) {
                        showNotification(`Resent ${successCount} notification(s), ${failCount} failed`, 'warning');
                        loadNotifications(currentPage);
                    }
                }
            },
            error: function() {
                processed++;
                failCount++;
                if (processed === selectedIds.length) {
                    showNotification(`Resent ${successCount} notification(s), ${failCount} failed`, 'warning');
                    loadNotifications(currentPage);
                }
            }
        });
    });
}

// Prevent multiple simultaneous sends
let isBroadcastSending = false;

function sendRoleBroadcast() {
    // Prevent duplicate sends
    if (isBroadcastSending) {
        showNotification('Broadcast is already being sent. Please wait...', 'warning');
        return;
    }

    const selectedRoles = [];
    $('.role-broadcast-checkbox:checked').each(function() {
        selectedRoles.push($(this).val());
    });

    if (selectedRoles.length === 0) {
        showNotification('Select at least one audience group', 'warning');
        return;
    }

    const subject = ($('#roleBroadcastSubject').val() || '').trim();
    const message = ($('#roleBroadcastMessage').val() || '').trim();

    if (!subject || !message) {
        showNotification('Subject and message are required', 'warning');
        return;
    }

    // Set sending flag immediately
    isBroadcastSending = true;

    const submitBtn = $('#roleBroadcastSubmit');
    const originalLabel = submitBtn.html();
    submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Sending...');

    // Show progress message
    const progressMsg = $('<div class="alert alert-info mt-2 mb-0" id="broadcastProgress"><small>Sending emails... This may take a moment for multiple recipients.</small></div>');
    $('#roleBroadcastForm').append(progressMsg);

    $.ajax({
        url: getApiBase() + '/notifications/notifications-api.php?action=send_role_broadcast',
        method: 'POST',
        data: JSON.stringify({
            roles: selectedRoles,
            subject: subject,
            message: message
        }),
        contentType: 'application/json',
        dataType: 'json',
        timeout: 300000, // 5 minutes timeout for large broadcasts
        success: function(response) {
            progressMsg.remove();
            if (response.success) {
                const summary = response.data || {};
                const hasFailures = (summary.failed || 0) > 0;
                const toastType = hasFailures ? ((summary.sent || 0) > 0 ? 'warning' : 'error') : 'success';
                let messageText = response.message || 'Broadcast processed successfully.';
                
                // Show detailed summary
                const sentCount = summary.sent || 0;
                const skippedCount = summary.skipped || 0;
                const failedCount = summary.failed || 0;
                
                if (sentCount > 0) {
                    messageText = `✅ ${sentCount} email${sentCount > 1 ? 's' : ''} sent successfully`;
                    if (skippedCount > 0) {
                        messageText += ` • ${skippedCount} skipped (invalid emails)`;
                    }
                    if (failedCount > 0) {
                        messageText += ` • ${failedCount} failed`;
                    }
                } else if (skippedCount > 0) {
                    messageText = `⚠️ No emails sent: ${skippedCount} recipient${skippedCount > 1 ? 's' : ''} had invalid email addresses`;
                } else if (failedCount > 0) {
                    messageText = `❌ ${failedCount} email${failedCount > 1 ? 's' : ''} failed to send`;
                }
                showNotification(messageText, toastType);
                if (!hasFailures) {
                    hideRoleBroadcastModal();
                    $('#roleBroadcastForm')[0].reset();
                }
                loadNotifications(1);
                loadStatistics();
            } else {
                showNotification(response.message || 'Failed to send broadcast', 'error');
            }
        },
        error: function(xhr, status, error) {
            progressMsg.remove();
            isBroadcastSending = false; // Reset flag on error
            if (status === 'timeout') {
                showNotification('Broadcast is taking longer than expected. Please check notifications page for status.', 'warning');
            } else {
                showNotification('Error sending broadcast: ' + error, 'error');
            }
        },
        complete: function() {
            progressMsg.remove();
            isBroadcastSending = false; // Always reset flag when done
            submitBtn.prop('disabled', false).html(originalLabel);
        }
    });
}

function applyRoleBroadcastTemplate(templateKey) {
    const template = roleBroadcastTemplates[templateKey];
    if (!template) {
        return;
    }

    const subjectField = $('#roleBroadcastSubject');
    const messageField = $('#roleBroadcastMessage');
    const hasExisting =
        (subjectField.val() && subjectField.val().trim().length > 0) ||
        (messageField.val() && messageField.val().trim().length > 0);

    if (hasExisting) {
        const shouldReplace = confirm('Replace the current subject and message with the selected template?');
        if (!shouldReplace) {
            $('#roleBroadcastTemplate').val('');
            return;
        }
    }

    subjectField.val(template.subject || '');
    messageField.val(template.message || '');
}

function refreshNotifications() {
    loadNotifications(currentPage);
    loadStatistics();
}

// Bulk action functions
function updateSelection() {
    selectedNotifications = [];
    $('.notification-checkbox:checked').each(function() {
        selectedNotifications.push(parseInt($(this).val()));
    });
    $('#selectedCount').text(selectedNotifications.length + ' selected');
    
    // Update select all checkbox
    const totalCheckboxes = $('.notification-checkbox').length;
    const checkedCheckboxes = $('.notification-checkbox:checked').length;
    
    if (totalCheckboxes > 0) {
        $('#selectAllCheckbox').prop('checked', checkedCheckboxes === totalCheckboxes);
    } else {
        $('#selectAllCheckbox').prop('checked', false);
    }
}

function toggleSelectAll() {
    const isChecked = $('#selectAllCheckbox').is(':checked');
    $('.notification-checkbox').prop('checked', isChecked);
    updateSelection();
}

function selectAll() {
    $('#selectAllCheckbox').prop('checked', true);
    toggleSelectAll();
}

function clearAllSelections() {
    // Clear all checkboxes
    $('.notification-checkbox').prop('checked', false);
    $('#selectAllCheckbox').prop('checked', false);
    
    // Clear selection array
    selectedNotifications = [];
    
    // Update display
    $('#selectedCount').text('0 selected');
}

function bulkMarkAsRead() {
    if (selectedNotifications.length === 0) {
        showNotification('Please select notifications to mark as read', 'warning');
        return;
    }
    
    if (!confirm(`Mark ${selectedNotifications.length} notifications as read?`)) {
        return;
    }
    
    // Send bulk request to API
    $.ajax({
        url: getApiBase() + '/notifications/notifications-api.php?action=bulk_mark_read',
        method: 'POST',
        data: JSON.stringify({ notification_ids: selectedNotifications }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification(`Marked ${selectedNotifications.length} notifications as read`, 'success');
                clearAllSelections();
                loadNotifications(currentPage);
                loadStatistics();
            } else {
                showNotification('Error marking notifications as read: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error marking notifications as read: ' + error, 'error');
        }
    });
}

function bulkMarkAsUnread() {
    if (selectedNotifications.length === 0) {
        showNotification('Please select notifications to mark as unread', 'warning');
        return;
    }
    
    if (!confirm(`Mark ${selectedNotifications.length} notifications as unread?`)) {
        return;
    }
    
    // Send bulk request to API
    $.ajax({
        url: getApiBase() + '/notifications/notifications-api.php?action=bulk_mark_unread',
        method: 'POST',
        data: JSON.stringify({ notification_ids: selectedNotifications }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification(`Marked ${selectedNotifications.length} notifications as unread`, 'success');
                clearAllSelections();
                loadNotifications(currentPage);
                loadStatistics();
            } else {
                showNotification('Error marking notifications as unread: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error marking notifications as unread: ' + error, 'error');
        }
    });
}

function bulkDelete() {
    if (selectedNotifications.length === 0) {
        showNotification('Please select notifications to delete', 'warning');
        return;
    }
    
    if (!confirm(`Delete ${selectedNotifications.length} notifications? This action cannot be undone.`)) {
        return;
    }
    
    // Send bulk delete request to API
    $.ajax({
        url: getApiBase() + '/notifications/notifications-api.php?action=bulk_delete',
        method: 'POST',
        data: JSON.stringify({ notification_ids: selectedNotifications }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification(`Deleted ${selectedNotifications.length} notifications`, 'success');
                clearAllSelections();
                loadNotifications(currentPage);
                loadStatistics();
            } else {
                showNotification('Error deleting notifications: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error deleting notifications: ' + error, 'error');
        }
    });
}

// Pagination functions
function nextPage() {
    if (currentPage < totalPages) {
        loadNotifications(currentPage + 1);
    } else {
        showNotification('Already on the last page', 'info');
    }
}

function previousPage() {
    if (currentPage > 1) {
        loadNotifications(currentPage - 1);
    } else {
        showNotification('Already on the first page', 'info');
    }
}

function updatePageInfo() {
    $('#pageInfo').text(`Page ${currentPage}`);
    $('#bottomPageInfo').text(`Page ${currentPage}`);
    
    // Handle edge cases for pagination info
    if (totalRecords === 0) {
        $('#recordInfo').text('Showing 0 of 0 notifications');
    } else {
        const start = Math.max(1, (currentPage - 1) * currentLimit + 1);
        const end = Math.min(currentPage * currentLimit, totalRecords);
        $('#recordInfo').text(`Showing ${start}-${end} of ${totalRecords} notifications`);
    }
}

function changeShowEntries() {
    const newLimit = parseInt($('#showEntries').val(), 10);
    if (!isNaN(newLimit) && newLimit > 0) {
        currentLimit = newLimit;
        loadNotifications(1);
    } else {
        // Reset to default if invalid value
        $('#showEntries').val(currentLimit);
        showNotification('Invalid entries per page value. Reset to default.', 'warning');
    }
}

function getStatusBadge(status) {
    const badges = {
        'pending': '<span class="badge bg-warning">Pending</span>',
        'sent': '<span class="badge bg-success">Sent</span>',
        'failed': '<span class="badge bg-danger">Failed</span>',
        'read': '<span class="badge bg-info">Read</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
}

function getTypeBadge(type) {
    if (!type) {
        return '<span class="badge bg-secondary">Unknown</span>';
    }
    const typeLower = type.toLowerCase();
    const badges = {
        'contact_added': '<span class="badge bg-primary">Contact Added</span>',
        'communication_added': '<span class="badge bg-info">Communication Added</span>',
        'contact_updated': '<span class="badge bg-warning">Contact Updated</span>',
        'email': '<span class="badge bg-info">Email</span>',
        'whatsapp': '<span class="badge bg-success">WhatsApp</span>',
        'sms': '<span class="badge bg-success">SMS</span>',
        'phone': '<span class="badge bg-primary">Phone</span>',
        'meeting': '<span class="badge bg-warning">Meeting</span>',
        'system': '<span class="badge bg-secondary">System</span>',
        'alert': '<span class="badge bg-danger">Alert</span>',
        'reminder': '<span class="badge bg-warning">Reminder</span>'
    };
    return badges[typeLower] || `<span class="badge bg-secondary">${type}</span>`;
}

function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

function goBackToContacts() {
    // Always go to contact page
    const baseUrl = getBaseUrl();
    window.location.href = baseUrl + '/pages/contact.php';
}

function showNotification(message, type) {
    // Remove any existing notifications first
    $('.notification-alert').remove();
    
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    // Escape message to prevent XSS
    const escapedMessage = escapeHtml(message);
    
    const alert = $(`
        <div class="alert ${alertClass} alert-dismissible fade show notification-alert" role="alert">
            ${escapedMessage}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `);
    
    // Add to top of page
    $('.main-content').prepend(alert);
    
    // Auto remove after 5 seconds (longer for errors)
    const duration = type === 'error' ? 7000 : 5000;
    setTimeout(function() {
        alert.fadeOut(function() {
            $(this).remove();
        });
    }, duration);
}

// Close IIFE - all code above is in isolated scope
})();
