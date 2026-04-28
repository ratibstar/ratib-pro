/**
 * EN: Implements frontend interaction behavior in `js/contact.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/contact.js`.
 */
/**
 * Contact Management JavaScript
 * Handles all contact-related functionality
 */

// Use app config API base so paths work from any base URL (subdomain, subfolder, etc.)
function getApiBase() {
    return (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '') || '../api';
}

function getContactApiUrl(endpoint) {
    return getApiBase().replace(/\/$/, '') + '/contacts/' + endpoint.replace(/^\//, '');
}

let currentPage = 1;
let currentFilters = {
    search: '',
    type: '',
    status: ''
};

// Initialize the page
// EN: Main UI bootstrap: wire events and load first-screen data.
// AR: تهيئة الواجهة الرئيسية: ربط الأحداث وتحميل بيانات الشاشة الأولى.
$(document).ready(function() {
    loadContacts();
    loadCommunications();
    loadCompanySuggestions();
    loadContactsForCommunication();
    loadNotificationBadge(); // Load notification badge
    
    // Handle contact type selection
    $('#contactTypeSelect').on('change', function() {
        handleContactTypeSelection();
    });
    
    // Handle contact name selection
    $('#contactNameSelect').on('change', function() {
        // Skip if this is a programmatic change during initial edit load
        if ($(this).data('skip-auto-populate')) {
            $(this).removeData('skip-auto-populate');
            return;
        }
        // Clear the flag immediately in case it was set by a previous programmatic change
        $(this).removeData('skip-auto-populate');
        handleContactNameSelection();
    });
    
    // Handle communication contact type selection
    $('#commContactType').on('change', function() {
        handleCommContactTypeSelection();
    });
    
    // Handle communication contact name selection
    $('#commContact').on('change', function() {
        handleCommContactNameSelection();
    });
    
    // Handle modal message template
    $('#modalMessageTemplate').on('change', function() {
        const currentContent = $('#modalMessageContent').val() || '';
        const trimmedContent = currentContent.trim();
        if (trimmedContent !== '') {
            const confirmed = window.confirm('Replace the current message with the selected template?');
            if (!confirmed) {
                return;
            }
        }
        loadModalMessageTemplate();
    });
    
    // Handle contacts status filter
    $('#contactsStatusFilter').on('change', function() {
        filterContacts();
    });
    
    // Handle contacts type filter
    $('#contactsTypeFilter').on('change', function() {
        filterContacts();
    });
    
    // Handle contacts per page
    $('#contactsPerPage, #contactsPerPageBottom').on('change', function() {
        changeContactsPerPage();
    });
    
    // Handle select all contacts checkbox
    $('#selectAllContacts').on('change', function() {
        toggleSelectAllContacts();
    });
    
    // EN: Use delegated handlers so actions still work for dynamically-rendered rows.
    // AR: استخدام تفويض الأحداث لضمان عمل الأزرار حتى مع الصفوف المولدة ديناميكياً.
    // Button event handlers using event delegation
    $(document).on('click', '[data-action="open-contact-modal"]', function() {
        openContactModal();
    });
    
    $(document).on('click', '[data-action="open-communication-modal"]', function() {
        openCommunicationModal();
    });
    
    $(document).on('click', '[data-action="open-notifications"]', function() {
        openNotificationsPage();
    });
    
    $(document).on('click', '[data-action="load-contacts"]', function() {
        loadContacts();
    });
    
    $(document).on('click', '[data-action="export-contacts"]', function() {
        exportContacts();
    });
    
    $(document).on('click', '[data-action="bulk-edit-contacts"]', function() {
        bulkEditContacts();
    });
    
    $(document).on('click', '[data-action="bulk-delete-contacts"]', function() {
        bulkDeleteContacts();
    });
    
    $(document).on('click', '[data-action="search-contacts"]', function() {
        searchContacts();
    });
    
    $(document).on('click', '[data-action="clear-search"]', function() {
        clearContactsSearch();
    });
    
    $(document).on('click', '[data-action="go-first-page"]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
            return false;
        }
        goToFirstPage();
        return false;
    });
    
    $(document).on('click', '[data-action="go-previous-page"]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
            return false;
        }
        goToPreviousPage();
        return false;
    });
    
    $(document).on('click', '[data-action="go-next-page"]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
            return false;
        }
        goToNextPage();
        return false;
    });
    
    $(document).on('click', '[data-action="go-last-page"]', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if ($(this).prop('disabled') || $(this).hasClass('disabled')) {
            return false;
        }
        goToLastPage();
        return false;
    });
    
    $(document).on('click', '[data-action="load-communications"]', function() {
        loadCommunications();
    });
    
    $(document).on('click', '[data-action="bulk-mark-read"]', function() {
        bulkMarkCommunicationsRead();
    });
    
    $(document).on('click', '[data-action="bulk-delete-communications"]', function() {
        bulkDeleteCommunications();
    });
    
    $(document).on('click', '[data-action="save-contact"]', function() {
        saveContact();
    });
    
    $(document).on('click', '[data-action="save-communication"]', function() {
        saveCommunication();
    });
    
    // EN: Row-level actions (view/edit/delete) from AJAX-generated tables.
    // AR: إجراءات مستوى الصف (عرض/تعديل/حذف) للجداول الناتجة من AJAX.
    // Dynamic action handlers for dynamically generated content
    $(document).on('click', '[data-action="edit-contact"]', function() {
        const contactId = $(this).data('contact-id');
        editContact(contactId);
    });
    
    $(document).on('click', '[data-action="view-contact"]', function() {
        const contactId = $(this).data('contact-id');
        viewContact(contactId);
    });
    
    $(document).on('click', '[data-action="add-communication"]', function() {
        const contactId = $(this).data('contact-id');
        addCommunication(contactId);
    });
    
    $(document).on('click', '[data-action="delete-contact"]', function() {
        const contactId = $(this).data('contact-id');
        deleteContact(contactId);
    });
    
    $(document).on('click', '[data-action="load-page"]', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page) {
            loadContacts(page);
        }
    });
    
    $(document).on('click', '[data-action="edit-from-view"]', function() {
        const contactId = $(this).data('contact-id');
        $('#viewContactModal').modal('hide');
        editContact(contactId);
    });
    
    $(document).on('click', '[data-action="view-communication"]', function() {
        const commId = $(this).data('comm-id');
        viewCommunication(commId);
    });
    
    $(document).on('click', '[data-action="edit-communication"]', function() {
        const commId = $(this).data('comm-id');
        editCommunication(commId);
    });
    
    $(document).on('click', '[data-action="delete-communication"]', function() {
        const commId = $(this).data('comm-id');
        deleteCommunication(commId);
    });
    
    // Auto-complete functionality
    $('#company').on('input', function() {
        const query = $(this).val();
        if (query.length > 2) {
            searchCompanies(query);
        }
    });
    
    // Email validation
    $('#email').on('blur', function() {
        validateEmail(this);
    });
    
    // EN: Periodic draft persistence to reduce accidental data loss.
    // AR: حفظ المسودة دورياً لتقليل فقدان البيانات غير المقصود.
    // Auto-save draft
    setInterval(saveDraft, 30000); // Save draft every 30 seconds
});

// Load contacts with pagination and filters
function loadContacts(page = 1) {
    currentContactsPage = page;
    
    const params = new URLSearchParams({
        action: 'get_contacts',
        page: page,
        limit: contactsPerPage,
        ...currentContactsFilters
    });
    
    const apiUrl = getContactApiUrl('contacts.php');
    $.ajax({
        url: apiUrl,
        method: 'GET',
        data: params.toString(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Handle both possible response formats
                const contacts = response.data.contacts || response.data || [];
                const pagination = response.data.pagination || response.pagination || null;
                
                displayContacts(contacts);
                
                // Update pagination if available
                if (pagination) {
                    totalContactsPages = pagination.total_pages || 1;
                    totalContactsRecords = pagination.total_records || contacts.length;
                    updateContactsPageInfo();
                } else {
                    totalContactsPages = 1;
                    totalContactsRecords = contacts.length;
                    updateContactsPageInfo();
                }
            } else {
                showNotification('Error loading contacts: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            let errorMessage = 'Error loading contacts: ' + error;
            if (xhr.status === 404) {
                errorMessage = 'API endpoint not found. Check if the file exists at: ' + apiUrl;
            } else if (xhr.responseText && xhr.responseText.includes('<!DOCTYPE')) {
                errorMessage = 'Server returned HTML instead of JSON. Check for PHP errors or 404.';
            } else if (xhr.responseText) {
                // Try to parse as JSON to get a better error message
                try {
                    const jsonResponse = JSON.parse(xhr.responseText);
                    errorMessage = jsonResponse.message || 'Error loading contacts: ' + error;
                } catch(e) {
                    // Not JSON, use default message
                }
            }
            showNotification(errorMessage, 'error');
        }
    });
}

// Display contacts in the table
function displayContacts(contacts) {
    const tbody = $('#contactsTableBody');
    tbody.empty();
    
    if (contacts.length === 0) {
        tbody.append(`
            <tr>
                <td colspan="11" class="text-center text-muted">No contacts found</td>
            </tr>
        `);
        return;
    }
    
    contacts.forEach(function(contact, index) {
        const statusBadge = getStatusBadge(contact.status || 'active');
        const typeBadge = getTypeBadge(contact.contact_type || contact.source_type);
        
        // Display contact_id or id as the name identifier (like C0147)
        const contactIdentifier = contact.contact_id || ('C' + String(contact.id || '').padStart(4, '0')) || '-';
        
        tbody.append(`
            <tr class="animated-row">
                <td class="small">${contactIdentifier}</td>
                <td class="small">${contact.name || '-'}</td>
                <td class="small">${contact.email || '-'}</td>
                <td class="small">${contact.phone || '-'}</td>
                <td class="small">${contact.country || '-'}</td>
                <td class="small">${contact.city || '-'}</td>
                <td>${typeBadge}</td>
                <td>${statusBadge}</td>
                <td class="small">${contact.last_contact_date ? formatDateTime(contact.last_contact_date) : 'No contact'}</td>
                <td>
                    <input type="checkbox" class="contact-checkbox" value="${contact.id}">
                </td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary btn-sm" data-action="edit-contact" data-contact-id="${contact.id}" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-info btn-sm" data-action="view-contact" data-contact-id="${contact.id}" title="View">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-success btn-sm" data-action="add-communication" data-contact-id="${contact.id}" title="Add Communication">
                            <i class="fas fa-comment"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-sm" data-action="delete-contact" data-contact-id="${contact.id}" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `);
    });
}


// Display pagination
function displayPagination(pagination) {
    const paginationContainer = $('#pagination');
    paginationContainer.empty();
    
    if (pagination.total_pages <= 1) return;
    
    // Previous button
    const prevDisabled = pagination.current_page <= 1 ? 'disabled' : '';
    paginationContainer.append(`
        <li class="page-item ${prevDisabled}">
            <a class="page-link" href="#" data-action="load-page" data-page="${pagination.current_page - 1}">Previous</a>
        </li>
    `);
    
    // Page numbers
    const startPage = Math.max(1, pagination.current_page - 2);
    const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        const active = i === pagination.current_page ? 'active' : '';
        paginationContainer.append(`
            <li class="page-item ${active}">
                <a class="page-link" href="#" data-action="load-page" data-page="${i}">${i}</a>
            </li>
        `);
    }
    
    // Next button
    const nextDisabled = pagination.current_page >= pagination.total_pages ? 'disabled' : '';
    paginationContainer.append(`
        <li class="page-item ${nextDisabled}">
            <a class="page-link" href="#" data-action="load-page" data-page="${pagination.current_page + 1}">Next</a>
        </li>
    `);
}


// Open contact modal for adding new contact
function openContactModal() {
    $('#contactModalLabel').text('Add/Edit Contact');
    $('#contactForm')[0].reset();
    $('#contactId').val('');
    
    // Clear all form fields explicitly
    $('#name, #email, #phone').val('');
    $('#contactTypeSelect').val('');
    $('#contactNameSelect').val('');
    $('#country').val(''); // No default - keep empty unless data exists in System Settings
    $('#city').val(''); // Clear city as well
    $('#modalMessageTemplate').val('');
    $('#modalMessageContent').val('');
    
    // Make all fields editable by default
    $('#name, #email, #phone, #city, #country').prop('readonly', false);
    
    // Don't set default country - let it be empty unless data exists in System Settings
    // Cities will populate when a country is selected
    
    $('#contactModal').modal('show');
}

// Load contacts for dropdown selection
function loadContactsForDropdown() {
    $.ajax({
        url: getContactApiUrl('simple_contacts.php'),
        method: 'GET',
        data: { action: 'get_all_contacts' }, // Get all contacts from all sources
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const select = $('#contactSelect');
                select.empty();
                select.append('<option value="">Select Existing Contact or Add New</option>');
                select.append('<option value="new">+ Add New Contact</option>');
                
                // Group contacts by source type
                const contactsBySource = {};
                response.data.forEach(function(contact) {
                    if (!contactsBySource[contact.source_type]) {
                        contactsBySource[contact.source_type] = [];
                    }
                    contactsBySource[contact.source_type].push(contact);
                });
                
                // Add contacts grouped by source
                Object.keys(contactsBySource).sort().forEach(function(sourceType) {
                    if (contactsBySource[sourceType].length > 0) {
                        select.append(`<optgroup label="--- ${sourceType}s ---">`);
                        contactsBySource[sourceType].forEach(function(contact) {
                            select.append(`<option value="${contact.id}" data-contact='${JSON.stringify(contact)}'>${contact.name} (${contact.company || sourceType})</option>`);
                        });
                        select.append('</optgroup>');
                    }
                });
            }
        },
        error: function(xhr, status, error) {
            // Error loading contacts for dropdown - silently fail
        }
    });
}

// Handle contact type selection
function handleContactTypeSelection() {
    const selectedType = $('#contactTypeSelect').val();
    const nameSelect = $('#contactNameSelect');
    
    // For "Regular Contact", we want empty text fields, not a dropdown
    if (selectedType === 'contact' || selectedType === 'regular contact') {
        // Clear the select dropdown and replace with an empty state
    nameSelect.empty();
        nameSelect.append('<option value="">New Contact - Enter Details Below</option>');
    
        // Clear all fields
        $('#contactId').val('');
        $('#name').val('');
        $('#email').val('');
        $('#phone').val('');
        $('#city').val('');
        // Don't set default country - let it be empty unless data exists in System Settings
        // Cities will populate when a country is selected
        
        // Make fields editable
        $('#name, #email, #phone, #city, #country').prop('readonly', false);
        
    } else if (selectedType && selectedType !== '') {
        // Clear name dropdown
        nameSelect.empty();
        // Load contacts of the selected type
        loadContactsByType(selectedType);
    } else {
        nameSelect.empty();
        nameSelect.append('<option value="">Select Contact Type First</option>');
    }
}

// Handle contact name selection
function handleContactNameSelection() {
    const selectedName = $('#contactNameSelect').val();
    
    if (!selectedName || selectedName === '') {
        return;
    }
    
    const selectedOption = $('#contactNameSelect option:selected');
    let contactDataAttr = selectedOption.data('contact');
    
    // Try to get from attribute if data() doesn't work or returns undefined
    if (!contactDataAttr) {
        const attrData = selectedOption.attr('data-contact');
        if (attrData) {
            contactDataAttr = attrData;
        } else {
            return;
        }
    }
    
    try {
        // Handle HTML entity encoding if present
        let contactDataStr = typeof contactDataAttr === 'string' ? contactDataAttr : JSON.stringify(contactDataAttr);
        contactDataStr = contactDataStr.replace(/&apos;/g, "'").replace(/&quot;/g, '"').replace(/&amp;/g, '&');
        
        // Parse the JSON string to get the contact data object
        const contactData = typeof contactDataAttr === 'object' && !Array.isArray(contactDataAttr) ? contactDataAttr : JSON.parse(contactDataStr);
        const selectedContactId = contactData.id || contactData.source_id || selectedName;
        
        // Always auto-populate fields when a contact is selected
        // The skip-auto-populate flag in the change handler prevents this during initial edit load
        $('#name').val(contactData.name || '');
        $('#email').val(contactData.email || '');
        $('#phone').val(contactData.phone || '');
        
        // Handle country and city - try to find country from city if not provided
        let countryValue = contactData.country || '';
        const cityValue = contactData.city || '';
        
        // If no country but we have a city, try to find the country from the city
        if (!countryValue && cityValue) {
            // Load countries/cities from API and find country
            loadCountriesCitiesFromAPI().then(citiesMap => {
                for (const [country, cities] of Object.entries(citiesMap)) {
                    if (Array.isArray(cities) && cities.includes(cityValue)) {
                        countryValue = country;
                        $('#country').val(countryValue);
                        // Populate city dropdown after finding country
                        autoPopulateCity('#country', '#city');
                        break;
                    }
                }
            });
        }
        
        // No default country - keep empty unless data exists in System Settings
        $('#country').val(countryValue || '');
        
        // Populate city dropdown based on country
        autoPopulateCity('#country', '#city');
        
        // Set the city value after populating the dropdown
        if (cityValue && cityValue.trim() !== '') {
            // Use setTimeout to ensure the dropdown is populated first
            setTimeout(() => {
                $('#city').val(cityValue);
            }, 100);
        } else if (countryValue) {
            // Load cities and select first one
            loadCountriesCitiesFromAPI().then(citiesMap => {
                if (citiesMap[countryValue] && Array.isArray(citiesMap[countryValue]) && citiesMap[countryValue].length > 0) {
                    setTimeout(() => {
                        $('#city').val(citiesMap[countryValue][0]);
                    }, 200);
                }
            });
        }
        
        // Set the contact ID for saving
        $('#contactId').val(selectedContactId);
        
        // Make fields editable
        $('#name, #email, #phone, #city, #country').prop('readonly', false);
        
    } catch (error) {
        // Error parsing contact data - silently fail
    }
}

// Load sent messages for template dropdown
function loadSentMessages() {
    $.ajax({
        url: getContactApiUrl('simple_contacts.php'),
        method: 'GET',
        data: { action: 'get_sent_messages' },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data.length > 0) {
                // Update both dropdowns
                const templateSelects = ['#messageTemplate', '#existingMessageTemplate'];
                
                templateSelects.forEach(function(selector) {
                    const templateSelect = $(selector);
                    if (templateSelect.length > 0) {
                        // Store original options first
                        if (!templateSelect.data('original-options')) {
                            const originalOptions = templateSelect.html();
                            templateSelect.data('original-options', originalOptions);
                        }
                        
                        // Restore original options
                        templateSelect.html(templateSelect.data('original-options'));
                        
                        // Add sent messages as options
                        response.data.forEach(function(message, index) {
                            const subject = message.subject || 'No Subject';
                            const date = new Date(message.communication_date).toLocaleDateString();
                            const truncatedMessage = message.message.length > 50 ? 
                                message.message.substring(0, 50) + '...' : 
                                message.message;
                            
                            templateSelect.append(`
                                <option value="sent_${index}" data-message="${message.message.replace(/"/g, '&quot;')}">
                                    Sent: ${subject} (${date}) - ${truncatedMessage}
                                </option>
                            `);
                        });
                    }
                });
            } else {
                // If no sent messages, ensure original options are restored
                const templateSelects = ['#messageTemplate', '#existingMessageTemplate'];
                templateSelects.forEach(function(selector) {
                    const templateSelect = $(selector);
                    if (templateSelect.length > 0 && templateSelect.data('original-options')) {
                        templateSelect.html(templateSelect.data('original-options'));
                    }
                });
            }
        },
        error: function() {
            // Restore original options on error
            const templateSelects = ['#messageTemplate', '#existingMessageTemplate'];
            templateSelects.forEach(function(selector) {
                const templateSelect = $(selector);
                if (templateSelect.length > 0 && templateSelect.data('original-options')) {
                    templateSelect.html(templateSelect.data('original-options'));
                }
            });
        }
    });
}

// Load message template
function loadMessageTemplate() {
    const template = $('#messageTemplate').val();
    let messageContent = '';
    
    // Check if it's a sent message
    if (template && template.startsWith('sent_')) {
        const selectedOption = $('#messageTemplate option:selected');
        const message = selectedOption.data('message');
        if (message) {
            messageContent = message;
        }
    } else {
        // Handle regular templates
        switch(template) {
        case 'welcome':
            messageContent = `Dear ${$('#name').val() || 'Valued Contact'},

Welcome to our services! We are excited to work with you and look forward to building a strong partnership.

If you have any questions or need assistance, please don't hesitate to contact us.

Best regards,
Your Team`;
            break;
            
        case 'follow_up':
            messageContent = `Hello ${$('#name').val() || 'Valued Contact'},

I hope this message finds you well. I wanted to follow up on our previous conversation and check if you have any questions or need any additional information.

Please let me know if there's anything I can help you with.

Best regards,
Your Team`;
            break;
            
        case 'meeting_request':
            messageContent = `Dear ${$('#name').val() || 'Valued Contact'},

I hope you're doing well. I would like to schedule a meeting to discuss our services and how we can best assist you.

Please let me know your availability for the following times:
- [Insert preferred dates/times]

Looking forward to hearing from you.

Best regards,
Your Team`;
            break;
            
        case 'contract_discussion':
            messageContent = `Hello ${$('#name').val() || 'Valued Contact'},

I hope this message finds you well. I wanted to discuss the contract details and ensure we address all your requirements.

Please review the attached contract and let me know if you have any questions or need any modifications.

Best regards,
Your Team`;
            break;
            
        case 'status_update':
            messageContent = `Dear ${$('#name').val() || 'Valued Contact'},

I wanted to provide you with an update on your current status and next steps.

[Insert specific status information]

If you have any questions, please don't hesitate to contact me.

Best regards,
Your Team`;
            break;
            
        case 'payment_reminder':
            messageContent = `Dear ${$('#name').val() || 'Valued Contact'},

I hope you're doing well. This is a friendly reminder that your payment is due.

Payment Details:
- Amount: [Insert amount]
- Due Date: [Insert due date]
- Payment Method: [Insert payment method]

Please let me know if you have any questions or need assistance with the payment process.

Best regards,
Your Team`;
            break;
            
        case 'thank_you':
            messageContent = `Dear ${$('#name').val() || 'Valued Contact'},

Thank you for choosing our services! We truly appreciate your business and look forward to working with you.

Your satisfaction is our priority, and we're committed to providing you with the best service possible.

Best regards,
Your Team`;
            break;
            
        case 'custom':
            messageContent = '';
            break;
            
        default:
            messageContent = '';
        }
    }
    
    $('#messageContent').val(messageContent);
}

// Send message
function sendMessage() {
    const contactId = $('#contactId').val();
    const contactName = $('#name').val() || $('#existingName').val();
    const contactEmail = $('#email').val() || $('#existingEmail').val();
    const messageContent = $('#messageContent').val();
    
    if (!contactId) {
        showNotification('Please select or create a contact first', 'error');
        return;
    }
    
    if (!messageContent.trim()) {
        showNotification('Please enter a message to send', 'error');
        return;
    }
    
    // Send message via API
    $.ajax({
        url: getContactApiUrl('contacts.php'),
        method: 'POST',
        data: {
            action: 'send_message',
            contact_id: contactId,
            contact_name: contactName,
            contact_email: contactEmail,
            message_content: messageContent,
            message_type: 'email',
            subject: 'Message from Contact Management System'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('Message sent successfully!', 'success');
                $('#messageContent').val('');
                $('#messageTemplate').val('');
            } else {
                showNotification('Error sending message: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error sending message: ' + error, 'error');
        }
    });
}

// Clear message
function clearMessage() {
    $('#messageContent').val('');
    $('#messageTemplate').val('');
}

// Add event handler for message template dropdown
$(document).on('change', '#messageTemplate', function() {
    loadMessageTemplate();
});

// Add event handler for existing message template dropdown
$(document).on('change', '#existingMessageTemplate', function() {
    loadExistingMessageTemplate();
});

// Country code mapping
const countryCodes = {
    'Saudi Arabia': '+966',
    'United Arab Emirates': '+971',
    'Kuwait': '+965',
    'Qatar': '+974',
    'Bahrain': '+973',
    'Oman': '+968',
    'Jordan': '+962',
    'Lebanon': '+961',
    'Egypt': '+20',
    'Turkey': '+90',
    'India': '+91',
    'Pakistan': '+92',
    'Bangladesh': '+880',
    'Philippines': '+63',
    'Indonesia': '+62',
    'Malaysia': '+60',
    'Thailand': '+66',
    'Vietnam': '+84',
    'China': '+86',
    'Japan': '+81',
    'South Korea': '+82',
    'United States': '+1',
    'United Kingdom': '+44',
    'Germany': '+49',
    'France': '+33',
    'Italy': '+39',
    'Spain': '+34',
    'Canada': '+1',
    'Australia': '+61',
    'Brazil': '+55',
    'Argentina': '+54',
    'Mexico': '+52',
    'Russia': '+7',
    'South Africa': '+27',
    'Nigeria': '+234',
    'Kenya': '+254',
    'Morocco': '+212',
    'Tunisia': '+216',
    'Algeria': '+213',
    'Libya': '+218',
    'Sudan': '+249',
    'Ethiopia': '+251',
    'Ghana': '+233',
    'Uganda': '+256',
    'Tanzania': '+255',
    'Zimbabwe': '+263',
    'Botswana': '+267',
    'Namibia': '+264',
    'Zambia': '+260',
    'Malawi': '+265',
    'Mozambique': '+258',
    'Madagascar': '+261',
    'Mauritius': '+230',
    'Seychelles': '+248',
    'Comoros': '+269',
    'Djibouti': '+253',
    'Somalia': '+252',
    'Eritrea': '+291',
    'Chad': '+235',
    'Niger': '+227',
    'Mali': '+223',
    'Burkina Faso': '+226',
    'Senegal': '+221',
    'Guinea': '+224',
    'Sierra Leone': '+232',
    'Liberia': '+231',
    'Ivory Coast': '+225',
    'Ghana': '+233',
    'Togo': '+228',
    'Benin': '+229',
    'Cameroon': '+237',
    'Central African Republic': '+236',
    'Congo': '+242',
    'Democratic Republic of Congo': '+243',
    'Gabon': '+241',
    'Equatorial Guinea': '+240',
    'Sao Tome and Principe': '+239',
    'Angola': '+244',
    'Zambia': '+260',
    'Zimbabwe': '+263',
    'Botswana': '+267',
    'Namibia': '+264',
    'South Africa': '+27',
    'Lesotho': '+266',
    'Swaziland': '+268',
    'Malawi': '+265',
    'Mozambique': '+258',
    'Madagascar': '+261',
    'Mauritius': '+230',
    'Seychelles': '+248',
    'Comoros': '+269',
    'Djibouti': '+253',
    'Somalia': '+252',
    'Eritrea': '+291',
    'Ethiopia': '+251',
    'Kenya': '+254',
    'Uganda': '+256',
    'Tanzania': '+255',
    'Rwanda': '+250',
    'Burundi': '+257',
    'Congo': '+242',
    'Central African Republic': '+236',
    'Chad': '+235',
    'Sudan': '+249',
    'South Sudan': '+211',
    'Libya': '+218',
    'Tunisia': '+216',
    'Algeria': '+213',
    'Morocco': '+212',
    'Western Sahara': '+212',
    'Mauritania': '+222',
    'Mali': '+223',
    'Burkina Faso': '+226',
    'Niger': '+227',
    'Nigeria': '+234',
    'Benin': '+229',
    'Togo': '+228',
    'Ghana': '+233',
    'Ivory Coast': '+225',
    'Liberia': '+231',
    'Sierra Leone': '+232',
    'Guinea': '+224',
    'Guinea-Bissau': '+245',
    'Senegal': '+221',
    'Gambia': '+220',
    'Cape Verde': '+238',
    'Sao Tome and Principe': '+239',
    'Equatorial Guinea': '+240',
    'Gabon': '+241',
    'Cameroon': '+237',
    'Central African Republic': '+236',
    'Chad': '+235',
    'Sudan': '+249',
    'South Sudan': '+211',
    'Ethiopia': '+251',
    'Eritrea': '+291',
    'Djibouti': '+253',
    'Somalia': '+252',
    'Kenya': '+254',
    'Uganda': '+256',
    'Tanzania': '+255',
    'Rwanda': '+250',
    'Burundi': '+257',
    'Congo': '+242',
    'Democratic Republic of Congo': '+243',
    'Angola': '+244',
    'Zambia': '+260',
    'Zimbabwe': '+263',
    'Botswana': '+267',
    'Namibia': '+264',
    'South Africa': '+27',
    'Lesotho': '+266',
    'Swaziland': '+268',
    'Malawi': '+265',
    'Mozambique': '+258',
    'Madagascar': '+261',
    'Mauritius': '+230',
    'Seychelles': '+248',
    'Comoros': '+269',
    'Mayotte': '+262',
    'Reunion': '+262',
    'Saint Helena': '+290',
    'Ascension Island': '+247',
    'Tristan da Cunha': '+290'
};

// Function to update phone field with country code
function updatePhoneWithCountryCode() {
    const country = $('#country').val();
    const phoneField = $('#phone');
    const currentPhone = phoneField.val();
    
    if (country && countryCodes[country]) {
        const countryCode = countryCodes[country];
        
        // If phone doesn't start with country code, add it
        if (currentPhone && !currentPhone.startsWith(countryCode)) {
            // Remove any existing country code
            let cleanPhone = currentPhone.replace(/^\+\d{1,4}\s?/, '');
            phoneField.val(countryCode + ' ' + cleanPhone);
        } else if (!currentPhone) {
            // If no phone number, just show the country code
            phoneField.attr('placeholder', countryCode + ' XXX XXX XXX');
        }
    }
}

// Load countries/cities from API
let countryCitiesMap = {};
let countriesCitiesCache = null;

// Function to load countries/cities from API
async function loadCountriesCitiesFromAPI() {
    if (countriesCitiesCache) {
        return countriesCitiesCache;
    }
    
    try {
        const baseUrl = document.documentElement.getAttribute('data-base-url') || '';
        const url = `${baseUrl}/api/admin/get_countries_cities.php?action=all`;
        
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && data.countriesCities) {
            countriesCitiesCache = data.countriesCities;
            countryCitiesMap = data.countriesCities;
            return data.countriesCities;
        }
    } catch (error) {
        console.error('Failed to load countries/cities:', error);
    }
    
    return {};
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadCountriesCitiesFromAPI();
});

// Function to auto-populate city based on country using API
async function autoPopulateCity(countryFieldId, cityFieldId) {
    const selectedCountry = $(countryFieldId).val();
    const cityField = $(cityFieldId);
    
    // Clear existing options
    cityField.empty();
    cityField.append('<option value="">Select City</option>');
    
    if (!selectedCountry) {
        return false;
    }
    
    try {
        // Load cities for the selected country from API
        const baseUrl = document.documentElement.getAttribute('data-base-url') || '';
        const url = `${baseUrl}/api/admin/get_countries_cities.php?action=cities&country=${encodeURIComponent(selectedCountry)}`;
        
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && Array.isArray(data.cities) && data.cities.length > 0) {
            // Populate dropdown with cities for the selected country
            data.cities.forEach(function(city) {
                cityField.append(`<option value="${city}">${city}</option>`);
            });
            return true;
        }
    } catch (error) {
        console.error('Failed to load cities:', error);
    }
    
    return false;
}

// Add event handler for country dropdown change - auto-populate city
$(document).on('change', '#country', function() {
    autoPopulateCity('#country', '#city');
    updatePhoneWithCountryCode();
});

// Add event handler for existing country dropdown change - auto-populate city
$(document).on('change', '#existingCountry', function() {
    autoPopulateCity('#existingCountry', '#existingCity');
    
    const country = $(this).val();
    const phoneField = $('#existingPhone');
    const currentPhone = phoneField.val();
    
    if (country && countryCodes[country]) {
        const countryCode = countryCodes[country];
        
        // If phone doesn't start with country code, add it
        if (currentPhone && !currentPhone.startsWith(countryCode)) {
            // Remove any existing country code
            let cleanPhone = currentPhone.replace(/^\+\d{1,4}\s?/, '');
            phoneField.val(countryCode + ' ' + cleanPhone);
        } else if (!currentPhone) {
            // If no phone number, just show the country code
            phoneField.attr('placeholder', countryCode + ' XXX XXX XXX');
        }
    }
});

// Phone number input handling with country code
$(document).on('focus', '#phone', function() {
    const phoneValue = $(this).val();
    const country = $('#country').val();
    
    if (country && countryCodes[country]) {
        const countryCode = countryCodes[country];
        
        // If phone doesn't start with country code, add it
        if (phoneValue && !phoneValue.startsWith(countryCode)) {
            // Remove any existing country code
            let cleanPhone = phoneValue.replace(/^\+\d{1,4}\s?/, '');
            $(this).val(countryCode + ' ' + cleanPhone);
        } else if (!phoneValue) {
            // If no phone number, set country code
            $(this).val(countryCode + ' ');
            // Move cursor after country code
            this.setSelectionRange(countryCode.length + 1, countryCode.length + 1);
        }
    }
});

// Fix phone number input to prevent backspace issues
$(document).on('input', '#phone', function() {
    const phone = $(this).val();
    const country = $('#country').val();
    
    if (country && countryCodes[country]) {
        const countryCode = countryCodes[country];
        const cursorPos = this.selectionStart;
        
        // Remove all characters except digits
        const digitsOnly = phone.replace(/\D/g, '');
        
        // Extract country code digits (remove + and spaces)
        const countryCodeDigits = countryCode.replace(/\D/g, '');
        
        // Get phone number without country code
        let phoneDigits = digitsOnly;
        if (digitsOnly.startsWith(countryCodeDigits)) {
            phoneDigits = digitsOnly.slice(countryCodeDigits.length);
        }
        
        // Build formatted phone number
        let formatted = countryCode + ' ';
        
        // Add spaces every 3 digits
        if (phoneDigits.length > 0) {
            formatted += phoneDigits.match(/.{1,3}/g).join(' ');
        }
        
        // Only update if different to avoid cursor issues
        if (phone !== formatted) {
            $(this).val(formatted);
            
            // Try to maintain cursor position accounting for spaces added
            const spacesAdded = (formatted.match(/ /g) || []).length - (phone.match(/ /g) || []).length;
            const newPos = Math.min(cursorPos + spacesAdded, formatted.length);
            this.setSelectionRange(newPos, newPos);
        }
    }
});

// Load message template for existing contacts
function loadExistingMessageTemplate() {
    const template = $('#existingMessageTemplate').val();
    let messageContent = '';
    
    // Check if it's a sent message
    if (template && template.startsWith('sent_')) {
        const selectedOption = $('#existingMessageTemplate option:selected');
        const message = selectedOption.data('message');
        if (message) {
            messageContent = message;
        }
    } else {
        // Handle regular templates
        switch(template) {
        case 'welcome':
            messageContent = `Dear ${$('#existingName').val() || 'Valued Contact'},

Welcome to our services! We are excited to work with you and look forward to building a strong partnership.

If you have any questions or need assistance, please don't hesitate to contact us.

Best regards,
Your Team`;
            break;
            
        case 'follow_up':
            messageContent = `Hello ${$('#existingName').val() || 'Valued Contact'},

I hope this message finds you well. I wanted to follow up on our previous conversation and check if you have any questions or need any additional information.

Please let me know if there's anything I can help you with.

Best regards,
Your Team`;
            break;
            
        case 'meeting_request':
            messageContent = `Dear ${$('#existingName').val() || 'Valued Contact'},

I hope you're doing well. I would like to schedule a meeting to discuss our services and how we can best assist you.

Please let me know your availability for the following times:
- [Insert preferred dates/times]

Looking forward to hearing from you.

Best regards,
Your Team`;
            break;
            
        case 'contract_discussion':
            messageContent = `Hello ${$('#existingName').val() || 'Valued Contact'},

I hope this message finds you well. I wanted to discuss the contract details and ensure we address all your requirements.

Please review the attached contract and let me know if you have any questions or need any modifications.

Best regards,
Your Team`;
            break;
            
        case 'status_update':
            messageContent = `Dear ${$('#existingName').val() || 'Valued Contact'},

I wanted to provide you with an update on your current status and next steps.

[Insert specific status information]

If you have any questions, please don't hesitate to contact me.

Best regards,
Your Team`;
            break;
            
        case 'payment_reminder':
            messageContent = `Dear ${$('#existingName').val() || 'Valued Contact'},

I hope you're doing well. This is a friendly reminder that your payment is due.

Payment Details:
- Amount: [Insert amount]
- Due Date: [Insert due date]
- Payment Method: [Insert payment method]

Please let me know if you have any questions or need assistance with the payment process.

Best regards,
Your Team`;
            break;
            
        case 'thank_you':
            messageContent = `Dear ${$('#existingName').val() || 'Valued Contact'},

Thank you for choosing our services! We truly appreciate your business and look forward to working with you.

Your satisfaction is our priority, and we're committed to providing you with the best service possible.

Best regards,
Your Team`;
            break;
            
        case 'custom':
            messageContent = '';
            break;
            
        default:
            messageContent = '';
        }
    }
    
    $('#existingMessageContent').val(messageContent);
}

// Send message for existing contacts
function sendExistingMessage() {
    const contactId = $('#contactId').val();
    const contactName = $('#existingName').val();
    const contactEmail = $('#existingEmail').val();
    const messageContent = $('#existingMessageContent').val();
    
    if (!contactId) {
        showNotification('Please select a contact first', 'error');
        return;
    }
    
    if (!messageContent.trim()) {
        showNotification('Please enter a message to send', 'error');
        return;
    }
    
    // Send message via API
    $.ajax({
        url: getContactApiUrl('contacts.php'),
        method: 'POST',
        data: {
            action: 'send_message',
            contact_id: contactId,
            contact_name: contactName,
            contact_email: contactEmail,
            message_content: messageContent,
            message_type: 'email',
            subject: 'Message from Contact Management System'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('Message sent successfully!', 'success');
                $('#existingMessageContent').val('');
                $('#existingMessageTemplate').val('');
            } else {
                showNotification('Error sending message: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error sending message: ' + error, 'error');
        }
    });
}

// Clear message for existing contacts
function clearExistingMessage() {
    $('#existingMessageContent').val('');
    $('#existingMessageTemplate').val('');
}

// Load message template for modal
function loadModalMessageTemplate() {
    const template = $('#modalMessageTemplate').val();
    let messageContent = '';
    
    // Get contact name for template
    const contactName = $('#name').val() || $('#existingName').val() || 'Valued Contact';
    
    switch(template) {
        case 'welcome':
            messageContent = `Dear ${contactName},

Welcome to our services! We are excited to work with you and look forward to building a strong partnership.

If you have any questions or need assistance, please don't hesitate to contact us.

Best regards,
Your Team`;
            break;
            
        case 'follow_up':
            messageContent = `Hello ${contactName},

I hope this message finds you well. I wanted to follow up on our previous conversation and check if you have any questions or need any additional information.

Please let me know if there's anything I can help you with.

Best regards,
Your Team`;
            break;
            
        case 'meeting_request':
            messageContent = `Dear ${contactName},

I hope you're doing well. I would like to schedule a meeting to discuss our services and how we can best assist you.

Please let me know your availability for the following times:
- [Insert preferred dates/times]

Looking forward to hearing from you.

Best regards,
Your Team`;
            break;
            
        case 'contract_discussion':
            messageContent = `Hello ${contactName},

I hope this message finds you well. I wanted to discuss the contract details and ensure we address all your requirements.

Please review the attached contract and let me know if you have any questions or need any modifications.

Best regards,
Your Team`;
            break;
            
        case 'status_update':
            messageContent = `Dear ${contactName},

I wanted to provide you with an update on your current status and next steps.

[Insert specific status information]

If you have any questions, please don't hesitate to contact me.

Best regards,
Your Team`;
            break;
            
        case 'payment_reminder':
            messageContent = `Dear ${contactName},

I hope you're doing well. This is a friendly reminder that your payment is due.

Payment Details:
- Amount: [Insert amount]
- Due Date: [Insert due date]
- Payment Method: [Insert payment method]

Please let me know if you have any questions or need assistance with the payment process.

Best regards,
Your Team`;
            break;
            
        case 'thank_you':
            messageContent = `Dear ${contactName},

Thank you for choosing our services! We truly appreciate your business and look forward to working with you.

Your satisfaction is our priority, and we're committed to providing you with the best service possible.

Best regards,
Your Team`;
            break;
            
        case 'custom':
            messageContent = '';
            break;
            
        default:
            messageContent = '';
    }
    
    $('#modalMessageContent').val(messageContent);
}

function ensureTemplateContentHydrated() {
    const templateValue = $('#modalMessageTemplate').val();
    const contentValue = $('#modalMessageContent').val();
    
    if (templateValue && templateValue.trim() !== '' && (!contentValue || contentValue.trim() === '')) {
        loadModalMessageTemplate();
    }
}

// Get template subject for notifications
function getTemplateSubject(templateValue) {
    const subjectMap = {
        'welcome': 'Welcome to Our Contact Management System',
        'follow_up': 'Follow Up Message',
        'meeting_request': 'Meeting Request',
        'contract_discussion': 'Contract Discussion',
        'status_update': 'Status Update',
        'payment_reminder': 'Payment Reminder',
        'thank_you': 'Thank You',
        'custom': 'Message from Contact Management System'
    };
    return subjectMap[templateValue] || 'Message from Contact Management System';
}

// Send message for modal
function sendModalMessage() {
    const contactId = $('#contactId').val();
    const contactName = $('#name').val() || $('#existingName').val() || 'Valued Contact';
    const contactEmail = $('#email').val() || $('#existingEmail').val();
    const messageContent = $('#modalMessageContent').val();
    
    if (!messageContent.trim()) {
        showNotification('Please enter a message to send', 'error');
        return;
    }
    
    // Send message via API
    $.ajax({
        url: getContactApiUrl('contacts.php'),
        method: 'POST',
        data: {
            action: 'send_message',
            contact_id: contactId || 'new',
            contact_name: contactName,
            contact_email: contactEmail,
            message_content: messageContent,
            message_type: 'email',
            subject: 'Message from Contact Management System'
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification('Message sent successfully!', 'success');
                $('#modalMessageContent').val('');
                $('#modalMessageTemplate').val('');
            } else {
                showNotification('Error sending message: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error sending message: ' + error, 'error');
        }
    });
}

// Clear message for modal
function clearModalMessage() {
    $('#modalMessageContent').val('');
    $('#modalMessageTemplate').val('');
}

// Add new contact button handler
// Load contacts by type
function loadContactsByType(type) {
    return new Promise((resolve, reject) => {
        const nameSelect = $('#contactNameSelect');
        
        // Show loading
        nameSelect.empty();
        nameSelect.append('<option value="">Loading...</option>');
        
        $.ajax({
            url: getContactApiUrl('simple_contacts.php'),
            method: 'GET',
            data: { action: 'get_all_contacts' },
            dataType: 'json',
            success: function(response) {
                nameSelect.empty();
                
                if (response.success && response.data) {
                    // Normalize the type parameter to match API source_type values
                    const typeMap = {
                        'agent': 'Agent',
                        'subagent': 'SubAgent',
                        'worker': 'Worker',
                        'hr': 'HR',
                        'hr employee': 'HR',
                        'contact': 'Contact',
                        'regular contact': 'Contact'
                    };
                    
                    const normalizedType = typeMap[type.toLowerCase()] || type;
                    
                    // Filter contacts by type - check both source_type and contact_type
                    const filteredContacts = response.data.filter(function(contact) {
                        const sourceType = contact.source_type || '';
                        const contactType = contact.contact_type || '';
                        
                        // Match by source_type (case-insensitive) or contact_type
                        return sourceType.toLowerCase() === normalizedType.toLowerCase() || 
                               contactType.toLowerCase() === type.toLowerCase() ||
                               sourceType.toLowerCase() === type.toLowerCase();
                    });
                    
                    if (filteredContacts.length > 0) {
                        nameSelect.append('<option value="">Select ' + type.charAt(0).toUpperCase() + type.slice(1) + '</option>');
                        
                        filteredContacts.forEach(function(contact) {
                            const contactId = contact.id || contact.source_id || '';
                            const contactName = contact.name || '';
                            const displayText = contactName + (contact.company ? ' (' + contact.company + ')' : ' (' + (contact.source_type || contact.contact_type || 'Contact') + ')');
                            // Use HTML5 data attribute with proper JSON encoding
                            const contactJson = JSON.stringify(contact).replace(/"/g, '&quot;');
                            nameSelect.append('<option value="' + contactId + '" data-contact="' + contactJson + '">' + displayText + '</option>');
                        });
                        
                    } else {
                        nameSelect.append('<option value="">No ' + type + 's found</option>');
                    }
                    resolve(filteredContacts);
                } else {
                    nameSelect.append('<option value="">Error loading contacts</option>');
                    reject('Error loading contacts');
                }
            },
            error: function(xhr, status, error) {
                nameSelect.empty();
                nameSelect.append('<option value="">Error loading contacts</option>');
                reject(error);
            }
        });
    });
}

// Handle communication contact type selection
function handleCommContactTypeSelection() {
    const selectedType = $('#commContactType').val();
    const nameSelect = $('#commContact');
    
    // Clear name dropdown
    nameSelect.empty();
    
    if (selectedType && selectedType !== '') {
        // Load contacts of the selected type for communications
        loadCommContactsByType(selectedType);
    } else {
        nameSelect.append('<option value="">Select Contact Type First</option>');
    }
}

// Handle communication contact name selection
function handleCommContactNameSelection() {
    const selectedName = $('#commContact').val();
    
    if (selectedName && selectedName !== '') {
        const selectedOption = $('#commContact option:selected');
        let contactDataAttr = selectedOption.data('contact');
        
        // Try to get from attribute if data() doesn't work
        if (!contactDataAttr) {
            const attrData = selectedOption.attr('data-contact');
            if (attrData) {
                contactDataAttr = attrData;
            }
        }
        
        if (contactDataAttr) {
            try {
                // Handle HTML entity encoding
                let contactDataStr = typeof contactDataAttr === 'string' ? contactDataAttr : JSON.stringify(contactDataAttr);
                // Replace HTML entities
                contactDataStr = contactDataStr.replace(/&apos;/g, "'").replace(/&quot;/g, '"').replace(/&amp;/g, '&');
                
                const contactData = typeof contactDataAttr === 'object' ? contactDataAttr : JSON.parse(contactDataStr);
                
                // Check if this contact exists in the main contacts table
                const contactId = contactData.id || contactData.source_id || selectedName;
                if (contactId && (contactId.toString().startsWith('agent_') || contactId.toString().startsWith('subagent_') || contactId.toString().startsWith('worker_') || contactId.toString().startsWith('hr_'))) {
                    // This is a contact from another source, we need to create it in the main contacts table first
                    createContactFromOtherSource(contactData);
                } else {
                    // This is already a main contact, use its ID directly
                    $('#commContactId').val(contactId || selectedName);
                }
            } catch (error) {
                // Fallback to using the selected value directly
                $('#commContactId').val(selectedName);
            }
        } else {
            // Fallback to using the selected value directly
            $('#commContactId').val(selectedName);
        }
    } else {
        // Clear contact ID if no contact is selected
        $('#commContactId').val('');
    }
}

// Create a contact record in the main contacts table from other sources
function createContactFromOtherSource(contactData) {
    const formData = {
        name: contactData.name,
        email: contactData.email || '',
        phone: contactData.phone || '',
        company: contactData.company || contactData.source_type,
        contact_type: contactData.source_type.toLowerCase(),
        status: 'active'
    };
    
    $.ajax({
        url: getContactApiUrl('contacts.php') + '?action=create_contact',
        method: 'POST',
        data: JSON.stringify(formData),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // ApiResponse::success wraps data in a 'data' property
                // The structure is: {success: true, data: {id: ..., contact_id: ..., message: ...}}
                const responseData = response.data || response;
                const newContactId = responseData.id || responseData.contact_id || (responseData.data && responseData.data.id);
                
                
                if (newContactId) {
                    $('#commContactId').val(newContactId);
                    showNotification('Contact created successfully', 'success');
                } else {
                    showNotification('Contact created but ID not found', 'error');
                }
            } else {
                const errorMsg = response.message || response.error || 'Unknown error';
                showNotification('Error creating contact: ' + errorMsg, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error creating contact: ' + error, 'error');
        }
    });
}

// Load contacts by type for communications
function loadCommContactsByType(type) {
    const nameSelect = $('#commContact');
    
    // Show loading
    nameSelect.empty();
    nameSelect.append('<option value="">Loading...</option>');
    
    $.ajax({
        url: getContactApiUrl('simple_contacts.php'),
        method: 'GET',
        data: { action: 'get_all_contacts' },
        dataType: 'json',
        success: function(response) {
            nameSelect.empty();
            
            if (response.success && response.data && Array.isArray(response.data)) {
                // Normalize the type parameter to match API source_type values
                const typeMap = {
                    'agent': 'Agent',
                    'subagent': 'SubAgent',
                    'worker': 'Worker',
                    'hr': 'HR',
                    'hr employee': 'HR',
                    'contact': 'Contact',
                    'regular contact': 'Contact'
                };
                
                const normalizedType = typeMap[type.toLowerCase()] || type;
                
                // Filter contacts by type
                const filteredContacts = response.data.filter(function(contact) {
                    const sourceType = contact.source_type || '';
                    const contactType = contact.contact_type || '';
                    
                    // Match by source_type (case-insensitive) or contact_type
                    return sourceType.toLowerCase() === normalizedType.toLowerCase() || 
                           contactType.toLowerCase() === type.toLowerCase();
                });
                
                if (filteredContacts.length > 0) {
                    nameSelect.append('<option value="">Select ' + type.charAt(0).toUpperCase() + type.slice(1) + '</option>');
                    
                    filteredContacts.forEach(function(contact) {
                        const contactId = contact.id || contact.source_id || '';
                        const contactName = contact.name || '';
                        const displayText = contactName + (contact.company ? ' (' + contact.company + ')' : ' (' + contact.source_type + ')');
                        // Use HTML5 data attribute with proper JSON encoding
                        const contactJson = JSON.stringify(contact).replace(/"/g, '&quot;');
                        nameSelect.append('<option value="' + contactId + '" data-contact="' + contactJson + '">' + displayText + '</option>');
                    });
                    
                } else {
                    nameSelect.append('<option value="">No ' + type + 's found</option>');
                }
            } else {
                // Invalid response format - silently fail
                nameSelect.append('<option value="">Error loading contacts - Invalid response</option>');
            }
        },
        error: function(xhr, status, error) {
            // Error loading communication contacts - silently fail
            nameSelect.empty();
            nameSelect.append('<option value="">Error loading contacts - ' + error + '</option>');
        }
    });
}

// Populate form fields with contact data
async function populateContactForm(contact) {
    
    $('#contactId').val(contact.id);
    $('#name').val(contact.name);
    $('#email').val(contact.email || '');
    $('#phone').val(contact.phone || '');
    
    // Handle country and city - try to find country from city if not provided
    let countryValue = contact.country || '';
    const cityValue = contact.city || '';
    
    // If no country but we have a city, try to find the country from the city
    if (!countryValue && cityValue) {
        // Load countries/cities from API and find country
        try {
            const citiesMap = await loadCountriesCitiesFromAPI();
            for (const [country, cities] of Object.entries(citiesMap)) {
                if (Array.isArray(cities) && cities.includes(cityValue)) {
                    countryValue = country;
                    break;
                }
            }
        } catch (error) {
            console.error('Failed to load countries/cities for city lookup:', error);
        }
    }
    
    // Set country value
    $('#country').val(countryValue);
    
    // Populate city dropdown based on country
    await autoPopulateCity('#country', '#city');
    
    // Set the city value after populating the dropdown (with delay to ensure dropdown is populated)
    if (cityValue && cityValue.trim() !== '') {
        setTimeout(() => {
            $('#city').val(cityValue);
        }, 100);
    } else if (countryValue) {
        // Auto-select first city if contact has no city
        loadCountriesCitiesFromAPI().then(citiesMap => {
            if (citiesMap[countryValue] && Array.isArray(citiesMap[countryValue]) && citiesMap[countryValue].length > 0) {
                setTimeout(() => {
                    $('#city').val(citiesMap[countryValue][0]);
                }, 200);
            }
        });
    }
    
    // Populate message template and content if they exist
    if (contact.modal_message_template) {
        $('#modalMessageTemplate').val(contact.modal_message_template);
    } else {
        $('#modalMessageTemplate').val('');
    }
    
    if (contact.modal_message_content) {
        $('#modalMessageContent').val(contact.modal_message_content);
    } else {
        $('#modalMessageContent').val('');
    }
    
    ensureTemplateContentHydrated();
    
    // Status is always 'active' for new/edited contacts
    $('#status').val('active');
    
}

// Populate form fields with contact data from other sources (agents, workers, etc.)
async function populateContactFormFromOtherSource(contact) {
    $('#contactId').val(''); // Clear contact ID since this is a new contact entry
    $('#name').val(contact.name);
    $('#email').val(contact.email || '');
    $('#phone').val(contact.phone || '');
    const countryValue = contact.country || ''; // No default - only use if provided
    $('#country').val(countryValue);
    // Auto-populate city when country is set (only if country exists)
    if (countryValue) {
        await autoPopulateCity('#country', '#city');
    }
    
    
    // Status is always 'active' for new contacts
    // Note: Contact type is set via contactTypeSelect, not as a separate field
}

// Edit contact
function editContact(contactId) {
    // Get the specific contact data
    $.ajax({
        url: getContactApiUrl('simple_contacts.php'),
        method: 'GET',
        data: { action: 'get_contact', id: contactId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const contact = response.data;
                
                // Set the modal title first so the guard in handleContactNameSelection works
                $('#contactModalLabel').text('Edit Contact');
                
                // Populate the form with contact data
                populateContactForm(contact).catch(error => {
                    console.error('Error populating contact form:', error);
                });
                
    // Make all fields editable
                $('#name, #email, #phone, #city, #country').prop('readonly', false);
                
                
                // Set contact type and name dropdowns based on contact data
                // Use contact_type from database (e.g., 'agent', 'customer', 'worker') if available
                // Otherwise use lowercase version of source_type
                const contactTypeValue = contact.contact_type || (contact.source_type ? contact.source_type.toLowerCase() : 'contact');
                
                // Set contact type dropdown to lowercase value
                $('#contactTypeSelect').val(contactTypeValue);
                
                // Load contacts for the selected type and then set the name
                loadContactsByType(contactTypeValue).then((filteredContacts) => {
                    // Wait a moment for the DOM to update with the new options
                    setTimeout(() => {
                        // Find the matching contact in the filtered list
                        const contactIdToMatch = contact.id || contact.source_id || contact.contact_id || '';
                        
                        // Try to match by ID first (check multiple ID formats)
                        let matchingContact = filteredContacts.find(c => {
                            const cId = c.id || c.source_id || c.contact_id || '';
                            return (cId === contactIdToMatch) || 
                                   (cId && cId.toString() === contactIdToMatch.toString()) ||
                                   (c.id && c.id.toString() === contactIdToMatch.toString());
                        });
                        
                        // If no match by ID, try to match by name
                        if (!matchingContact && contact.name) {
                            matchingContact = filteredContacts.find(c => 
                                c.name && c.name.toLowerCase() === contact.name.toLowerCase()
                            );
                        }
                        
                        // Set the contact name select value
                        let valueToSet = '';
                        if (matchingContact) {
                            valueToSet = matchingContact.id || matchingContact.source_id || matchingContact.contact_id || '';
                        } else if (contactIdToMatch) {
                            // Fallback: try the original contact ID
                            valueToSet = contactIdToMatch;
                        }
                        
                        if (valueToSet) {
                            // Set a flag to prevent auto-population during programmatic setting
                            $('#contactNameSelect').data('skip-auto-populate', true);
                            $('#contactNameSelect').val(valueToSet);
                            
                            // Force update the select in case the value didn't match
                            if ($('#contactNameSelect').val() !== valueToSet) {
                                // Value didn't match - try to find the option by value attribute
                                const option = $('#contactNameSelect option').filter(function() {
                                    return $(this).val() === valueToSet.toString();
                                });
                                if (option.length > 0) {
                                    $('#contactNameSelect').data('skip-auto-populate', true);
                                    $('#contactNameSelect').val(valueToSet);
                                } else {
                                    // Still no match - try by matching the first part of the ID (for prefixed IDs)
                                    const prefixMatch = $('#contactNameSelect option').filter(function() {
                                        const optVal = $(this).val().toString();
                                        return optVal.includes(valueToSet.toString()) || valueToSet.toString().includes(optVal);
                                    });
                                    if (prefixMatch.length > 0) {
                                        $('#contactNameSelect').data('skip-auto-populate', true);
                                        $('#contactNameSelect').val(prefixMatch.first().val());
                                    }
                                }
                            }
                            
                            // Clear the flag after a short delay so user changes will work normally
                            setTimeout(() => {
                                $('#contactNameSelect').removeData('skip-auto-populate');
                            }, 300);
                        }
                    }, 100);
                }).catch(error => {
                    // Error loading contacts for edit - silently fail
                });
                
                // Load sent messages for template dropdown
                loadSentMessages();
                
                // Show the modal first
                $('#contactModal').modal('show');
                
                // Load last sent message for this contact to populate template and content
                // Wait a moment for modal to render, then load the message data
                setTimeout(function() {
                    loadLastMessageForContact(contactId);
                }, 200);
            } else {
                showNotification('Error loading contact: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error loading contact: ' + error, 'error');
        }
    });
}

// View contact details
function viewContact(contactId) {
    $.ajax({
        url: getContactApiUrl('simple_contacts.php'),
        method: 'GET',
        data: { action: 'get_contact', id: contactId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const contact = response.data;
                showContactDetails(contact);
            } else {
                showNotification('Error loading contact: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error loading contact: ' + error, 'error');
        }
    });
}

// Show contact details in a modal
function showContactDetails(contact) {
    const modalHtml = `
        <div class="modal fade" id="viewContactModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content bg-dark text-white">
                    <div class="modal-header">
                        <h5 class="modal-title">Contact Details - ${contact.name}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Basic Information</h6>
                                <p><strong>Name:</strong> ${contact.name}</p>
                                <p><strong>Email:</strong> ${contact.email || 'Not provided'}</p>
                                <p><strong>Phone:</strong> ${contact.phone || 'Not provided'}</p>
                                <p><strong>Position:</strong> ${contact.position || 'Not provided'}</p>
                            </div>
                            <div class="col-md-6">
                                <h6>Location & Type</h6>
                                <p><strong>City:</strong> ${contact.city || 'Not provided'}</p>
                                <p><strong>Country:</strong> ${contact.country || 'Not provided'}</p>
                                <p><strong>Type:</strong> ${getTypeBadge(contact.contact_type)}</p>
                                <p><strong>Status:</strong> ${getStatusBadge(contact.status)}</p>
                            </div>
                        </div>
                        ${contact.address ? `<div class="mt-3"><h6>Address</h6><p>${contact.address}</p></div>` : ''}
                        ${contact.notes ? `<div class="mt-3"><h6>Notes</h6><p>${contact.notes}</p></div>` : ''}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" data-action="edit-from-view" data-contact-id="${contact.id}">Edit Contact</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    $('#viewContactModal').remove();
    
    // Add new modal to body
    $('body').append(modalHtml);
    
    // Show modal
    $('#viewContactModal').modal('show');
}

// Save contact
function saveContact() {
    const contactId = $('#contactId').val();
    const selectedType = $('#contactTypeSelect').val();
    const selectedName = $('#contactNameSelect').val();
    
    let formData;
    
    // Check if we're editing an existing contact (contactId exists and is not a composite ID)
    if (contactId && contactId !== '' && !contactId.includes('_')) {
        // For editing existing contacts, use the main form fields
        formData = {
            name: $('#name').val(),
            email: $('#email').val(),
            phone: $('#phone').val(),
            city: $('#city').val() || '',
            country: $('#country').val() || '',
            contact_type: selectedType || 'customer',
            status: 'active'
        };
    } else if (!selectedType || selectedType === '') {
        // New contact without selecting a type
        formData = {
            name: $('#name').val(),
            email: $('#email').val(),
            phone: $('#phone').val(),
            city: $('#city').val() || '',
            country: $('#country').val() || '',
            contact_type: 'customer',
            status: 'active'
        };
    } else {
        // For existing contacts from other sources (agent, worker, etc.)
        formData = {
            name: $('#name').val(),
            email: $('#email').val(),
            phone: $('#phone').val(),
            city: $('#city').val() || '',
            country: $('#country').val() || '',
            contact_type: selectedType,
            status: 'active'
        };
    }
    
    // Check if this is an edit operation (contactId exists and is not a composite ID)
    const isEdit = contactId !== '' && contactId !== null && contactId !== undefined && !contactId.includes('_');
    
    if (!formData.name.trim()) {
        showNotification('Name is required', 'error');
        return;
    }
    
    $.ajax({
        url: getContactApiUrl('simple_contacts.php') + `?action=${isEdit ? 'update_contact' : 'create_contact'}${isEdit ? '&id=' + contactId : ''}`,
        method: 'POST',
        data: JSON.stringify(formData),
        contentType: 'application/json',
        dataType: 'json',
        success: async function(response) {
            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                showNotification(isEdit ? 'Contact updated successfully' : 'Contact created successfully', 'success');
                
                // Save message template and content to localStorage for this specific contact
                const draftContactId = response.data?.id || contactId;
                if (draftContactId) {
                    const messageData = {
                        modal_message_template: $('#modalMessageTemplate').val(),
                        modal_message_content: $('#modalMessageContent').val()
                    };
                    localStorage.setItem(`contactDraft_${draftContactId}`, JSON.stringify(messageData));
                }
                
                const selectedTemplateValue = $('#modalMessageTemplate').val() || '';
                const messageContentValue = ($('#modalMessageContent').val() || '').trim();
                const numericContactId = response.data?.id || (contactId && !contactId.includes('_') ? contactId : null);
                const shouldSendNotification = !isEdit || Boolean(selectedTemplateValue || messageContentValue);
                
                if (shouldSendNotification && numericContactId) {
                    const notificationPayload = {
                        ...formData,
                        name: $('#name').val(),
                        email: $('#email').val(),
                        phone: $('#phone').val(),
                        contact_type: formData.contact_type || selectedType || 'customer',
                        company: formData.company || ($('#company').length ? $('#company').val() : '') || '',
                        message_template: selectedTemplateValue,
                        message_content: messageContentValue,
                        subject: selectedTemplateValue ? getTemplateSubject(selectedTemplateValue) : ''
                    };
                    sendContactNotification(notificationPayload, numericContactId);
                }
                
                $('#contactModal').modal('hide');
                clearDraft(); // Clear draft after successful save
                
                // Reset form for next use
                $('#contactForm')[0].reset();
                $('#contactId').val('');
                $('#contactTypeSelect').val('');
                $('#contactNameSelect').val('');
                
                // Force reload the contacts table, communications, and notification badge
                // Reset current page to 1 to show the newly added contact at the top
                currentPage = 1;
                currentContactsPage = 1;
                // Clear any filters to show all contacts
                $('#contactsSearchInput').val('');
                $('#contactsStatusFilter').val('');
                $('#contactsTypeFilter').val('');
                currentContactsFilters = { search: '', type: '', status: '' };
                setTimeout(() => {
                    loadContacts(1); // Always reload page 1 to show newest contacts first
                    loadCommunications(); // Reload communications to update Last Contact dates
                    loadNotificationBadge(); // Update notification badge
                }, 500); // Increased delay to ensure the database is updated
            } else {
                showNotification('Error saving contact: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error saving contact: ' + error, 'error');
        }
    });
}

// Send notification when contact is added
function sendContactNotification(contactData, contactId) {
    const fallbackTemplate = $('#modalMessageTemplate').val() || '';
    const fallbackMessage = ($('#modalMessageContent').val() || '').trim();
    
    // Ensure contactId is properly resolved - use the passed numeric ID
    const numericContactId = contactId || contactData?.id || contactData?.contact_id || null;
    const contactName = contactData?.name || $('#name').val() || 'Unknown Contact';
    
    // Ensure we have a valid numeric contact ID, fallback to name only if numeric ID is unavailable
    // The API will resolve name to ID if needed
    const contactIdForNotification = numericContactId || contactName;
    
    const notificationData = {
        contact_id: contactIdForNotification,
        contact_name: contactName,
        contact_email: contactData?.email || $('#email').val() || '',
        contact_phone: contactData?.phone || $('#phone').val() || '',
        contact_type: contactData?.contact_type || contactData?.type || 'Contact',
        company: contactData?.company || $('#company').val() || '',
        message_template: contactData?.message_template !== undefined ? contactData.message_template : fallbackTemplate,
        message_content: (contactData?.message_content !== undefined ? contactData.message_content : fallbackMessage).trim(),
        subject: contactData?.subject || ''
    };
    
    $.ajax({
        url: getApiBase() + '/notifications/notifications-api.php?action=send_contact_notification',
        method: 'POST',
        data: JSON.stringify(notificationData),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Silently succeed - notification created in database
            } else {
                // Failed to send notification - silently fail
            }
        },
        error: function(xhr, status, error) {
            // Error sending notification - silently fail
        }
    });
}

// Send notification when communication is added
function sendCommunicationNotification(communicationData) {
    $.ajax({
        url: getApiBase() + '/notifications/notifications-api.php?action=send_communication_notification',
        method: 'POST',
        data: JSON.stringify(communicationData),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (!response.success) {
                // Silently fail - notification error logged server-side
            }
        },
        error: function() {
            // Silently fail - notification error logged server-side
        }
    });
}

// Open notifications page
function openNotificationsPage() {
    // Store the current page in sessionStorage so we can navigate back
    sessionStorage.setItem('previousPage', 'contact');
    const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
    window.location.href = baseUrl + '/pages/notifications.php';
}

// New control functions for communications
function bulkMarkCommunicationsRead() {
    const selected = [];
    $('.comm-checkbox:checked').each(function() {
        const commId = $(this).closest('tr').data('comm-id');
        if (commId) {
            selected.push(commId);
        }
    });
    
    if (selected.length === 0) {
        showNotification('Please select communications to mark as read', 'warning');
        return;
    }
    
    if (!confirm(`Are you sure you want to mark ${selected.length} communication(s) as read?`)) {
        return;
    }
    
    $.ajax({
        url: getContactApiUrl('simple_contacts.php'),
        method: 'POST',
        data: JSON.stringify({ 
            action: 'bulk_change_status_communications', 
            ids: selected,
            status: 'read'
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification(`${selected.length} communication(s) marked as read successfully`, 'success');
                $('.comm-checkbox:checked').prop('checked', false);
                $('#selectAllComms').prop('checked', false);
                loadCommunications();
            } else {
                showNotification('Error marking communications as read: ' + (response.message || 'Unknown error'), 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error marking communications as read: ' + error, 'error');
        }
    });
}

function bulkDeleteCommunications() {
    const selected = [];
    $('.comm-checkbox:checked').each(function() {
        const commId = $(this).closest('tr').data('comm-id');
        if (commId) {
            selected.push(commId);
        }
    });
    
    if (selected.length === 0) {
        showNotification('Please select communications to delete', 'warning');
        return;
    }
    
    if (!confirm(`⚠️ Are you sure you want to delete ${selected.length} communication(s)? This action cannot be undone.`)) {
        return;
    }
    
    $.ajax({
        url: getContactApiUrl('simple_contacts.php'),
        method: 'POST',
        data: JSON.stringify({ 
            action: 'bulk_delete_communications', 
            ids: selected 
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification(`${selected.length} communication(s) deleted successfully`, 'success');
                $('.comm-checkbox:checked').prop('checked', false);
                $('#selectAllComms').prop('checked', false);
                loadCommunications();
                loadContacts(currentPage || 1);
            } else {
                showNotification('Error deleting communications: ' + (response.message || 'Unknown error'), 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error deleting communications: ' + error, 'error');
        }
    });
}

function editCommunication(commId) {
    // Get the communication data from the API - use simple_contacts.php which has edit_communication endpoint
    $.ajax({
        url: getContactApiUrl('simple_contacts.php'),
        method: 'GET',
        data: { action: 'get_communication', id: commId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                const comm = response.data;
                
                // Populate the communication modal with existing data
                $('#commContactId').val(comm.contact_id);
                $('#commType').val(comm.communication_type);
                $('#commDirection').val(comm.direction);
                $('#commPriority').val(comm.priority);
                $('#commSubject').val(comm.subject || '');
                $('#commMessage').val(comm.message || '');
                $('#commOutcome').val(comm.outcome || '');
                $('#commNextAction').val(comm.next_action || '');
                $('#commFollowUpDate').val(comm.follow_up_date || '');
                if ($('#commChannel').length) {
                    $('#commChannel').val(comm.channel || '');
                }
                if ($('#commDuration').length) {
                    $('#commDuration').val(comm.duration || '');
                }
                
                // Load contact type selection for this communication
                if (comm.contact_name || comm.contact_id) {
                    const contactType = comm.contact_type || 'contact';
                    if (typeof loadCommContactsByType === 'function') {
                        loadCommContactsByType(contactType);
                        
                        // Select the contact in dropdown after a short delay
                        setTimeout(function() {
                            $('#commContact').val(comm.contact_id);
                            if (typeof handleCommContactNameSelection === 'function') {
                                handleCommContactNameSelection();
                            }
                        }, 300);
                    }
                }
                
                // Set the modal title and show it
                $('#communicationModalLabel').text('Edit Communication');
                $('#communicationModal').modal('show');
                
                // Store the communication ID for updating
                $('#communicationModal').data('edit-id', commId);
            } else {
                showNotification('Error loading communication: ' + (response.message || 'Unknown error'), 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error loading communication: ' + error, 'error');
        }
    });
}

function deleteCommunication(commId) {
    if (confirm('⚠️ Are you sure you want to delete this communication? This action cannot be undone.')) {
        $.ajax({
            url: getContactApiUrl('simple_contacts.php'),
            method: 'POST',
            data: JSON.stringify({ action: 'delete_communication', id: commId }),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification('Communication deleted successfully', 'success');
                    loadCommunications();
                    loadContacts(currentPage || 1);
                } else {
                    showNotification('Error deleting communication: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error deleting communication: ' + error, 'error');
            }
        });
    }
}

// New control functions for contacts
function bulkEditContacts() {
    showNotification('Bulk edit contacts functionality will be implemented', 'info');
}

function bulkDeleteContacts() {
    // Collect selected contact IDs from checkboxes
    const selectedCheckboxes = document.querySelectorAll('.contact-checkbox:checked');
    const selectedIds = Array.from(selectedCheckboxes).map(cb => parseInt(cb.value)).filter(id => !isNaN(id));

    // Check if any contacts are selected
    if (selectedIds.length === 0) {
        showNotification('Please select at least one contact to delete', 'warning');
        return;
    }

    // Confirm action
    if (!confirm(`Are you sure you want to delete ${selectedIds.length} contact(s)? This action cannot be undone.`)) {
        return;
    }

    // Send bulk delete request to API
    $.ajax({
        url: getContactApiUrl('contacts.php') + '?action=bulk_delete_contacts',
        method: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({ contact_ids: selectedIds }),
        success: async function(response) {
            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                showNotification(response.data?.message || `${selectedIds.length} contact(s) deleted successfully`, 'success');
                // Uncheck all checkboxes
                $('.contact-checkbox').prop('checked', false);
                $('#selectAllContacts').prop('checked', false);
                // Reload contacts to reflect changes
                loadContacts(currentContactsPage || 1);
                loadCommunications();
                loadNotificationBadge();
            } else {
                showNotification('Error deleting contacts: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error deleting contacts: ' + error, 'error');
        }
    });
}

function toggleSelectAllContacts() {
    const isChecked = $('#selectAllContacts').is(':checked');
    $('.contact-checkbox').prop('checked', isChecked);
}

// Pagination functions for contacts
let currentContactsPage = 1;
let totalContactsPages = 1;
let totalContactsRecords = 0;
let contactsPerPage = 5;
let currentContactsFilters = {};

function changeContactsPerPage() {
    contactsPerPage = parseInt($('#contactsPerPage').val());
    $('#contactsPerPageBottom').val(contactsPerPage);
    loadContacts(1);
}

function goToFirstPage() {
    if (currentContactsPage > 1) {
        loadContacts(1);
    }
}

function goToPreviousPage() {
    if (currentContactsPage > 1) {
        loadContacts(currentContactsPage - 1);
    }
}

function goToNextPage() {
    if (currentContactsPage < totalContactsPages) {
        loadContacts(currentContactsPage + 1);
    }
}

function goToLastPage() {
    if (currentContactsPage < totalContactsPages) {
        loadContacts(totalContactsPages);
    }
}

function updateContactsPageInfo() {
    const pageInfo = `Page ${currentContactsPage} of ${totalContactsPages}`;
    const recordInfo = `Showing ${((currentContactsPage - 1) * contactsPerPage) + 1}-${Math.min(currentContactsPage * contactsPerPage, totalContactsRecords)} of ${totalContactsRecords} contacts`;
    
    $('#contactsRecordInfo').text(recordInfo);
    $('#contactsRecordInfoBottom').text(recordInfo);
    
    // Generate page numbers
    generateContactsPageNumbers();
}

function generateContactsPageNumbers() {
    const maxVisiblePages = 5;
    let startPage = Math.max(1, currentContactsPage - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalContactsPages, startPage + maxVisiblePages - 1);
    
    // Adjust start page if we're near the end
    if (endPage - startPage + 1 < maxVisiblePages) {
        startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    
    let pageNumbersHtml = '';
    
    // Add page numbers
    for (let i = startPage; i <= endPage; i++) {
        const isActive = i === currentContactsPage;
        pageNumbersHtml += `
            <button class="btn btn-sm ${isActive ? 'btn-primary' : 'btn-outline-secondary'}" 
                    data-action="load-page" 
                    data-page="${i}"
                    ${isActive ? 'disabled' : ''}>
                ${i}
            </button>
        `;
    }
    
    // Add ellipsis if needed
    if (startPage > 1) {
        pageNumbersHtml = `<button class="btn btn-sm btn-outline-secondary" data-action="load-page" data-page="1">1</button>` +
                         `<span class="btn btn-sm btn-outline-secondary disabled">...</span>` + pageNumbersHtml;
    }
    
    if (endPage < totalContactsPages) {
        pageNumbersHtml += `<span class="btn btn-sm btn-outline-secondary disabled">...</span>` +
                          `<button class="btn btn-sm btn-outline-secondary" data-action="load-page" data-page="${totalContactsPages}">${totalContactsPages}</button>`;
    }
    
    $('#contactsPageNumbers').html(pageNumbersHtml);
    $('#contactsPageNumbersBottom').html(pageNumbersHtml);
}

// Search and filter functions for contacts
function searchContacts() {
    const searchTerm = $('#contactsSearchInput').val().trim();
    currentContactsFilters.search = searchTerm;
    loadContacts(1);
}

function clearContactsSearch() {
    $('#contactsSearchInput').val('');
    currentContactsFilters.search = '';
    loadContacts(1);
}

function filterContacts() {
    const status = $('#contactsStatusFilter').val();
    const type = $('#contactsTypeFilter').val();
    
    currentContactsFilters.status = status;
    currentContactsFilters.type = type;
    
    loadContacts(1);
}

// Add event listeners for real-time search
$(document).ready(function() {
    // Real-time search on input
    $('#contactsSearchInput').on('input', function() {
        const searchTerm = $(this).val().trim();
        if (searchTerm.length >= 2 || searchTerm.length === 0) {
            currentContactsFilters.search = searchTerm;
            loadContacts(1);
        }
    });
    
    // Enter key search
    $('#contactsSearchInput').on('keypress', function(e) {
        if (e.which === 13) {
            searchContacts();
        }
    });
});

// Load notification count badge
function loadNotificationBadge() {
    $.ajax({
            url: ((window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '')) + '/notifications/notifications-api.php?action=get_notifications&status=pending',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            const count = (response.success && response.data) ? response.data.length : 0;
            
            // Add/update badge to notification button in header
            const notificationBtn = $('button[data-action="open-notifications"]');
            if (notificationBtn.length > 0) {
                notificationBtn.find('.notification-badge, .badge').remove();
                if (count > 0) {
                    notificationBtn.append(`<span class="notification-badge badge bg-danger ms-2">${count}</span>`);
                }
            }
            
            // Also add/update to sidebar
            const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
            const sidebarNotification = $('a[href="' + baseUrl + '/pages/notifications.php"], a[href*="notifications.php"]');
            if (sidebarNotification.length > 0) {
                sidebarNotification.find('.notification-badge, .badge').remove();
                if (count > 0) {
                    sidebarNotification.append(`<span class="notification-badge badge bg-danger ms-1">${count}</span>`);
                }
            }
            
            // Also check for notification link in nav
            $('.nav-item').each(function() {
                if ($(this).text().includes('Notification')) {
                    $(this).find('.notification-badge, .badge').remove();
                    if (count > 0) {
                        $(this).append(`<span class="notification-badge badge bg-danger ms-1">${count}</span>`);
                    }
                }
            });
        },
        error: function() {
            // Silently fail for badge loading
        }
    });
}

// Delete contact
function deleteContact(contactId) {
    if (!confirm('Are you sure you want to delete this contact?')) {
        return;
    }
    
    $.ajax({
        url: getContactApiUrl('contacts.php') + `?action=delete_contact&id=${contactId}`,
        method: 'POST',
        dataType: 'json',
        success: async function(response) {
            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                showNotification('Contact deleted successfully', 'success');
                loadContacts(currentContactsPage || 1);
                loadCommunications(); // Reload communications
                loadNotificationBadge(); // Update notification badge
            } else {
                showNotification('Error deleting contact: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error deleting contact: ' + error, 'error');
        }
    });
}

// Add communication
function addCommunication(contactId) {
    $('#commContactId').val(contactId);
    $('#communicationForm')[0].reset();
    $('#communicationModal').modal('show');
}

// Save communication
function saveCommunication() {
    // Get contact ID from hidden field, or fallback to dropdown value
    let contactId = $('#commContactId').val();
    
    if (!contactId) {
        contactId = $('#commContact').val();
        if (!contactId) {
            showNotification('Please select a contact', 'error');
            return;
        }
        // Set it for next time
        $('#commContactId').val(contactId);
    }
    
    const formData = {
        contact_id: contactId,
        communication_type: $('#commType').val() || '',
        direction: $('#commDirection').val() || 'outbound',
        priority: $('#commPriority').val() || 'medium',
        channel: $('#commChannel').val() || '',
        duration: $('#commDuration').val() || '',
        subject: $('#commSubject').val() || '',
        message: $('#commMessage').val() || '',
        outcome: $('#commOutcome').val() || '',
        next_action: $('#commNextAction').val() || '',
        follow_up_date: $('#commFollowUpDate').val() || '',
        status: 'sent'
    };
    
    // Validation - API requires these fields
    if (!formData.contact_id) {
        showNotification('Please select a contact', 'error');
        return;
    }
    
    if (!formData.communication_type || !formData.communication_type.trim()) {
        showNotification('Please select a Communication Type', 'error');
        $('#commType').focus();
        $('#commType').addClass('is-invalid');
        return;
    } else {
        $('#commType').removeClass('is-invalid');
    }
    
    // Subject or message must be provided
    const hasSubject = formData.subject && formData.subject.trim();
    const hasMessage = formData.message && formData.message.trim();
    if (!hasSubject && !hasMessage) {
        showNotification('Subject or message is required', 'error');
        if (!hasSubject) {
            $('#commSubject').focus();
            $('#commSubject').addClass('is-invalid');
        }
        if (!hasMessage) {
            $('#commMessage').addClass('is-invalid');
        }
        return;
    } else {
        $('#commSubject, #commMessage').removeClass('is-invalid');
    }
    
    // Check if we're editing an existing communication
    const editId = $('#communicationModal').data('edit-id');
    const isEdit = editId !== undefined && editId !== null;
    
    if (isEdit) {
        // Editing existing communication - use simple_contacts.php which has edit_communication endpoint
        formData.id = editId;
        
        $.ajax({
            url: getContactApiUrl('simple_contacts.php') + '?action=edit_communication',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification('Communication updated successfully', 'success');
                    
                    // Send communication notification if needed
                    const selectedContact = $('#commContact option:selected');
                    const contactData = selectedContact.data('contact');
                    if (contactData || formData.contact_id) {
                        const notificationData = {
                            contact_id: formData.contact_id,
                            contact_name: contactData?.name || 'Unknown Contact',
                            contact_email: contactData?.email || '',
                            contact_phone: contactData?.phone || '',
                            communication_type: formData.communication_type,
                            subject: formData.subject || 'Communication Updated: ' + (formData.communication_type || 'communication'),
                            message: formData.message || '',
                            outcome: formData.outcome || '',
                            next_action: formData.next_action || ''
                        };
                        sendCommunicationNotification(notificationData);
                    }
                    
                    $('#communicationModal').modal('hide');
                    // Reset communication form
                    $('#communicationForm')[0].reset();
                    $('#commContactId').val('');
                    // Clear edit mode
                    $('#communicationModal').removeData('edit-id');
                    $('#communicationModalLabel').text('Add Communication');
                    // Reload both communications and contacts to update Last Contact dates
                    loadCommunications();
                    loadContacts(currentPage || currentContactsPage || 1);
                    loadNotificationBadge(); // Update notification badge
                } else {
                    showNotification('Error updating communication: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error updating communication: ' + error, 'error');
            }
        });
    } else {
        // Creating new communication
        $.ajax({
            url: getContactApiUrl('simple_contacts.php') + '?action=add_communication',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification('Communication added successfully', 'success');
                    
                    // Send communication notification
                    const selectedContact = $('#commContact option:selected');
                    const contactData = selectedContact.data('contact');
                    if (contactData || formData.contact_id) {
                        const notificationData = {
                            contact_id: formData.contact_id,
                            contact_name: contactData?.name || 'Unknown Contact',
                            contact_email: contactData?.email || '',
                            contact_phone: contactData?.phone || '',
                            communication_type: formData.communication_type,
                            subject: formData.subject || 'Communication Added: ' + (formData.communication_type || 'communication'),
                            message: formData.message || '',
                            outcome: formData.outcome || '',
                            next_action: formData.next_action || ''
                        };
                        sendCommunicationNotification(notificationData);
                    }
                    
                    $('#communicationModal').modal('hide');
                    // Reset communication form
                    $('#communicationForm')[0].reset();
                    $('#commContactId').val('');
                    // Reload both communications and contacts to update Last Contact dates
                    loadCommunications();
                    loadContacts(currentPage || currentContactsPage || 1);
                    loadNotificationBadge(); // Update notification badge
                } else {
                    showNotification('Error adding communication: ' + (response.message || 'Unknown error'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error adding communication: ' + error, 'error');
            }
        });
    }
}

// Utility functions
function getStatusBadge(status) {
    const badges = {
        'active': '<span class="badge bg-success">Active</span>',
        'inactive': '<span class="badge bg-warning">Inactive</span>',
        'archived': '<span class="badge bg-secondary">Archived</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
}

function getTypeBadge(type) {
    const badges = {
        'customer': '<span class="badge bg-primary">Customer</span>',
        'vendor': '<span class="badge bg-info">Vendor</span>',
        'agent': '<span class="badge bg-success">Agent</span>',
        'subagent': '<span class="badge bg-warning">SubAgent</span>',
        'worker': '<span class="badge bg-secondary">Worker</span>',
        'other': '<span class="badge bg-dark">Other</span>'
    };
    return badges[type] || '<span class="badge bg-dark">Other</span>';
}

// Professional auto-selection functions
function loadCompanySuggestions() {
    $.ajax({
        url: getContactApiUrl('contacts.php'),
        method: 'GET',
        data: { action: 'get_companies' },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const companyList = $('#companyList');
                companyList.empty();
                response.data.forEach(company => {
                    companyList.append(`<option value="${company}">`);
                });
            }
        }
    });
}

async function loadCitySuggestions() {
    // Load cities based on selected country
    const country = $('#country').val();
    await updateCityList(country);
}

// Updated to use API instead of hardcoded data
async function updateCityList(country) {
    const citySelect = $('#city');
    
    // Clear existing options except the first one
    citySelect.find('option:not(:first)').remove();
    
    if (!country || country.trim() === '') {
        return;
    }
    
    try {
        const baseUrl = document.documentElement.getAttribute('data-base-url') || '';
        const url = `${baseUrl}/api/admin/get_countries_cities.php?action=cities&country=${encodeURIComponent(country)}`;
        
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.success && Array.isArray(data.cities) && data.cities.length > 0) {
            data.cities.forEach(city => {
                citySelect.append(`<option value="${city}">${city}</option>`);
            });
        } else {
            citySelect.append(`<option value="">No cities available for this country</option>`);
        }
    } catch (error) {
        console.error('Failed to load cities:', error);
        citySelect.append(`<option value="">Error loading cities</option>`);
    }
}

function searchCompanies(query) {
    $.ajax({
        url: getContactApiUrl('contacts.php'),
        method: 'GET',
        data: { action: 'search_companies', q: query },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const companyList = $('#companyList');
                companyList.empty();
                response.data.forEach(company => {
                    companyList.append(`<option value="${company}">`);
                });
            }
        }
    });
}

function updateTypeSuggestions() {
    const contactType = $('#contact_type').val();
    const positionSelect = $('#position');
    
    // Clear existing options except the first one
    positionSelect.find('option:not(:first)').remove();
    
    // Add type-specific position suggestions
    const positionSuggestions = {
        'customer': ['CEO', 'Manager', 'Director', 'Owner', 'President', 'Vice President', 'General Manager', 'Operations Manager', 'Business Owner', 'Entrepreneur'],
        'vendor': ['Sales Manager', 'Account Manager', 'Business Development', 'Sales Representative', 'Marketing Manager', 'Sales Director', 'Vendor Manager', 'Supplier Relations'],
        'agent': ['Agent', 'Recruitment Agent', 'HR Agent', 'Talent Acquisition', 'Recruiter', 'Senior Agent', 'Lead Agent', 'Supervisor'],
        'subagent': ['Sub-Agent', 'Assistant Agent', 'Junior Agent', 'Field Agent', 'Local Agent', 'Associate Agent', 'Support Agent'],
        'worker': ['Worker', 'Employee', 'Staff', 'Laborer', 'Technician', 'Specialist', 'Senior Worker', 'Skilled Worker'],
        'prospect': ['CEO', 'Manager', 'Director', 'Decision Maker', 'Influencer', 'Gatekeeper', 'Budget Holder'],
        'partner': ['Partner', 'Business Partner', 'Strategic Partner', 'Alliance Manager', 'Partnership Director'],
        'competitor': ['CEO', 'Manager', 'Director', 'Competitor', 'Market Analyst', 'Business Intelligence'],
        'other': ['Manager', 'Director', 'Consultant', 'Advisor', 'Coordinator', 'Specialist', 'Executive', 'Professional']
    };
    
    if (positionSuggestions[contactType]) {
        positionSuggestions[contactType].forEach(position => {
            positionSelect.append(`<option value="${position}">${position}</option>`);
        });
    }
}

async function updateCountrySuggestions() {
    const country = $('#country').val();
    await updateCityList(country);
}

function updateCitySuggestions() {
    // This function is called when city dropdown changes
    // Can be used for additional city-specific logic if needed
}

function formatPhoneNumber(input) {
    let value = input.value.replace(/\D/g, '');
    
    // Format based on country
    const country = $('#country').val();
    if (country === 'Saudi Arabia') {
        if (value.length >= 9) {
            value = value.replace(/(\d{3})(\d{3})(\d{3})/, '+966 $1 $2 $3');
        }
    } else if (country === 'United Arab Emirates') {
        if (value.length >= 9) {
            value = value.replace(/(\d{3})(\d{3})(\d{3})/, '+971 $1 $2 $3');
        }
    } else {
        if (value.length >= 10) {
            value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
        }
    }
    
    input.value = value;
}

function validateEmail(input) {
    const email = input.value;
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (email && !emailRegex.test(email)) {
        $(input).addClass('is-invalid');
        if (!$(input).next('.invalid-feedback').length) {
            $(input).after('<div class="invalid-feedback">Please enter a valid email address</div>');
        }
    } else {
        $(input).removeClass('is-invalid');
        $(input).next('.invalid-feedback').remove();
    }
}

function saveDraft() {
    const formData = {
        name: $('#name').val(),
        email: $('#email').val(),
        secondary_email: $('#secondary_email').val(),
        website: $('#website').val(),
        phone: $('#phone').val(),
        secondary_phone: $('#secondary_phone').val(),
        company: $('#company').val(),
        industry: $('#industry').val(),
        position: $('#position').val(),
        department: $('#department').val(),
        company_size: $('#company_size').val(),
        address: $('#address').val(),
        city: $('#city').val(),
        country: $('#country').val(),
        postal_code: $('#postal_code').val(),
        timezone: $('#timezone').val(),
        contact_type: $('#contact_type').val(),
        lead_source: $('#lead_source').val(),
        priority: $('#priority').val(),
        birth_date: $('#birth_date').val(),
        notes: $('#notes').val(),
        modal_message_template: $('#modalMessageTemplate').val(),
        modal_message_content: $('#modalMessageContent').val()
    };
    
    // Only save if there's actual content
    if (formData.name || formData.email || formData.phone || formData.company) {
        localStorage.setItem('contactDraft', JSON.stringify(formData));
    }
}

function loadDraft() {
    const draft = localStorage.getItem('contactDraft');
    if (draft) {
        const formData = JSON.parse(draft);
        $('#name').val(formData.name || '');
        $('#email').val(formData.email || '');
        $('#secondary_email').val(formData.secondary_email || '');
        $('#website').val(formData.website || '');
        $('#phone').val(formData.phone || '');
        $('#secondary_phone').val(formData.secondary_phone || '');
        $('#company').val(formData.company || '');
        $('#industry').val(formData.industry || '');
        $('#position').val(formData.position || '');
        $('#department').val(formData.department || '');
        $('#company_size').val(formData.company_size || '');
        $('#address').val(formData.address || '');
        $('#city').val(formData.city || '');
        $('#country').val(formData.country || ''); // No default - keep empty unless data exists
        $('#postal_code').val(formData.postal_code || '');
        $('#timezone').val(formData.timezone || 'Asia/Riyadh');
        $('#contact_type').val(formData.contact_type || 'customer');
        $('#lead_source').val(formData.lead_source || '');
        $('#priority').val(formData.priority || 'medium');
        $('#birth_date').val(formData.birth_date || '');
        $('#notes').val(formData.notes || '');
        
        // Load message template and content from draft if available
        if (formData.modal_message_template) {
            $('#modalMessageTemplate').val(formData.modal_message_template);
        }
        if (formData.modal_message_content) {
            $('#modalMessageContent').val(formData.modal_message_content);
        }
    }
}

// Load last sent message for a contact to populate template and content fields
function loadLastMessageForContact(contactId) {
    
    // Also check localStorage for a draft specific to this contact
    const draftKey = `contactDraft_${contactId}`;
    const savedDraft = localStorage.getItem(draftKey);
    if (savedDraft) {
        try {
            const draftData = JSON.parse(savedDraft);
            if (draftData.modal_message_template) {
                $('#modalMessageTemplate').val(draftData.modal_message_template);
            }
            if (draftData.modal_message_content) {
                $('#modalMessageContent').val(draftData.modal_message_content);
            }
        } catch (e) {
            // Error loading draft - silently fail
        }
    }
    
    $.ajax({
        url: getContactApiUrl('simple_contacts.php'),
        method: 'GET',
        data: {
            action: 'get_recent_communications',
            contact_id: contactId,
            limit: 1
        },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data && response.data.length > 0) {
                const lastMessage = response.data[0];
                
                // Populate message content with last sent message (only if not already set from draft)
                if (lastMessage.message && !$('#modalMessageContent').val()) {
                    $('#modalMessageContent').val(lastMessage.message);
                }
                
                // Try to match the message to a template type based on subject or content (only if not already set)
                if (!$('#modalMessageTemplate').val() && lastMessage.message) {
                    const subject = (lastMessage.subject || '').toLowerCase();
                    const message = (lastMessage.message || '').toLowerCase();
                    
                    let templateValue = '';
                    if (subject.includes('welcome') || message.includes('welcome')) {
                        templateValue = 'welcome';
                    } else if (subject.includes('follow') || message.includes('follow up')) {
                        templateValue = 'follow_up';
                    } else if (subject.includes('meeting') || message.includes('meeting')) {
                        templateValue = 'meeting_request';
                    } else if (subject.includes('contract') || message.includes('contract')) {
                        templateValue = 'contract_discussion';
                    } else if (subject.includes('status') || message.includes('status')) {
                        templateValue = 'status_update';
                    } else if (subject.includes('payment') || message.includes('payment')) {
                        templateValue = 'payment_reminder';
                    } else if (subject.includes('thank') || message.includes('thank')) {
                        templateValue = 'thank_you';
                    } else {
                        templateValue = 'custom';
                    }
                    
                    $('#modalMessageTemplate').val(templateValue);
                }
            } else {
            }
            
            ensureTemplateContentHydrated();
        },
        error: function(xhr, status, error) {
            // Error loading last message - silently fail
            ensureTemplateContentHydrated();
        }
    });
}

function clearDraft() {
    localStorage.removeItem('contactDraft');
}

function clearSearch() {
    $('#searchInput').val('');
    currentFilters.search = '';
    loadContacts();
}

function exportContacts() {
    const params = new URLSearchParams({
        action: 'export_contacts',
        ...currentFilters
    });
    
    window.open(getContactApiUrl('contacts.php') + '?' + params.toString(), '_blank');
}

function importContacts() {
    // Create file input for CSV import
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = '.csv';
    input.onchange = function(e) {
        const file = e.target.files[0];
        if (file) {
            importContactsFromFile(file);
        }
    };
    input.click();
}

function importContactsFromFile(file) {
    const formData = new FormData();
    formData.append('file', file);
    
    $.ajax({
        url: getContactApiUrl('contacts.php'),
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showNotification(`Successfully imported ${response.imported} contacts`, 'success');
                loadContacts();
            } else {
                showNotification('Import failed: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Import failed: ' + error, 'error');
        }
    });
}

// Communication functions for employer-customer interactions
function loadCommunications() {
    const apiUrl = getContactApiUrl('contacts.php') + '?action=get_recent_communications';
    $.ajax({
        url: apiUrl,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                displayCommunications(response.data);
            } else {
                // API Error - show notification instead
                showNotification('Error loading communications: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            // AJAX Error loading communications - show notification instead
            
            let errorMessage = 'Error loading communications: ' + error;
            if (xhr.status === 404) {
                errorMessage = 'API endpoint not found: ' + apiUrl;
            }
            showNotification(errorMessage, 'error');
            
            // Show empty state if API fails
            const timeline = $('#communicationsTimeline');
            timeline.html(`
                <div class="text-center text-muted py-4">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i>
                    <p>Unable to load communications</p>
                    <button class="btn btn-outline-primary btn-sm" data-action="load-communications">
                        <i class="fas fa-retry"></i> Retry
                    </button>
                </div>
            `);
        }
    });
}

function displayCommunications(communications) {
    const timeline = $('#communicationsTimeline');
    timeline.empty();
    
    if (communications.length === 0) {
        timeline.append(`
            <div class="text-center text-muted py-4">
                <i class="fas fa-comments fa-3x mb-3"></i>
                <p>No recent communications found</p>
            </div>
        `);
        return;
    }
    
    communications.forEach(comm => {
        const directionIcon = comm.direction === 'outbound' ? 'fa-arrow-right' : 'fa-arrow-left';
        const directionColor = comm.direction === 'outbound' ? 'text-primary' : 'text-success';
        const priorityBadge = getPriorityBadge(comm.priority);
        const outcomeBadge = getOutcomeBadge(comm.outcome);
        
        timeline.append(`
            <div class="communication-item mb-2 p-2 border rounded communication-item-dark">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center mb-1">
                            <i class="fas ${directionIcon} ${directionColor} me-1"></i>
                            <strong class="me-1 small">${comm.contact_name}</strong>
                            <span class="badge bg-secondary me-1 small">${comm.communication_type}</span>
                            ${priorityBadge}
                            ${outcomeBadge}
                        </div>
                        <h6 class="mb-1 small">${comm.subject}</h6>
                        <p class="mb-1 text-muted small">${comm.message}</p>
                        <div class="small text-muted">
                            ${comm.next_action ? `<span class="me-2">Next: ${comm.next_action}</span>` : ''}
                            ${comm.follow_up_date ? `<span>Follow: ${comm.follow_up_date}</span>` : ''}
                        </div>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">${formatDateTime(comm.communication_date)}</small>
                        <div class="btn-group btn-group-sm mt-1">
                            <button class="btn btn-outline-info btn-sm" data-action="view-communication" data-comm-id="${comm.id}" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-warning btn-sm" data-action="edit-communication" data-comm-id="${comm.id}" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-sm" data-action="delete-communication" data-comm-id="${comm.id}" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `);
    });
}

function loadContactsForCommunication() {
    const apiUrl = getContactApiUrl('contacts.php') + '?action=get_contacts_for_communication';
    $.ajax({
        url: apiUrl,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const select = $('#commContact');
                select.empty();
                select.append('<option value="">Select Contact</option>');
                response.data.forEach(contact => {
                    select.append(`<option value="${contact.id}">${contact.name} - ${contact.contact_type}</option>`);
                });
            } else {
                // API Error loading contacts - show notification instead
                showNotification('Error loading contacts: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            // AJAX Error loading contacts for communication - show notification instead
            
            let errorMessage = 'Error loading contacts: ' + error;
            if (xhr.status === 404) {
                errorMessage = 'API endpoint not found: ' + apiUrl;
            }
            showNotification(errorMessage, 'error');
        }
    });
}

function openCommunicationModal() {
    // Set title for Add mode
    $('#communicationModalLabel').text('Add Communication');
    // Clear edit mode
    $('#communicationModal').removeData('edit-id');
    
    $('#communicationForm')[0].reset();
    $('#commContactId').val('');
    $('#commContactType').val('');
    $('#commContact').val('');
    $('#commType').val('email'); // Set default to 'email'
    $('#commDirection').val('outbound');
    $('#commPriority').val('medium');
    // Remove any validation error classes
    $('#commType, #commSubject, #commMessage').removeClass('is-invalid');
    $('#communicationModal').modal('show');
}

function loadContactDetails() {
    const contactId = $('#commContact').val();
    if (contactId) {
        $('#commContactId').val(contactId);
    }
}

function viewCommunication(communicationId) {
    $.ajax({
        url: getContactApiUrl('contacts.php') + `?action=get_communication&id=${communicationId}`,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showCommunicationDetails(response.data);
            } else {
                showNotification('Error loading communication: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error loading communication: ' + error, 'error');
        }
    });
}

function showCommunicationDetails(comm) {
    const modalHtml = `
        <div class="modal fade" id="viewCommModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content bg-dark text-white">
                    <div class="modal-header">
                        <h5 class="modal-title">Communication Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Contact Information</h6>
                                <p><strong>Name:</strong> ${comm.contact_name}</p>
                                <p><strong>Company:</strong> ${comm.contact_company || 'Not specified'}</p>
                                <p><strong>Type:</strong> ${comm.contact_type}</p>
                            </div>
                            <div class="col-md-6">
                                <h6>Communication Details</h6>
                                <p><strong>Type:</strong> ${comm.communication_type}</p>
                                <p><strong>Direction:</strong> ${comm.direction}</p>
                                <p><strong>Priority:</strong> ${getPriorityBadge(comm.priority)}</p>
                                <p><strong>Outcome:</strong> ${getOutcomeBadge(comm.outcome)}</p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h6>Subject</h6>
                            <p>${comm.subject}</p>
                        </div>
                        <div class="mt-3">
                            <h6>Message/Notes</h6>
                            <p>${comm.message}</p>
                        </div>
                        ${comm.next_action ? `
                        <div class="mt-3">
                            <h6>Next Action</h6>
                            <p>${comm.next_action}</p>
                        </div>
                        ` : ''}
                        ${comm.follow_up_date ? `
                        <div class="mt-3">
                            <h6>Follow-up Date</h6>
                            <p>${comm.follow_up_date}</p>
                        </div>
                        ` : ''}
                        <div class="mt-3">
                            <h6>Date & Time</h6>
                            <p>${formatDateTime(comm.communication_date)}</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    $('#viewCommModal').remove();
    $('body').append(modalHtml);
    $('#viewCommModal').modal('show');
}

function getPriorityBadge(priority) {
    const badges = {
        'low': '<span class="badge bg-secondary">Low</span>',
        'medium': '<span class="badge bg-info">Medium</span>',
        'high': '<span class="badge bg-warning">High</span>',
        'urgent': '<span class="badge bg-danger">Urgent</span>'
    };
    return badges[priority] || '<span class="badge bg-secondary">Unknown</span>';
}

function getOutcomeBadge(outcome) {
    if (!outcome) return '';
    const badges = {
        'positive': '<span class="badge bg-success">Positive</span>',
        'neutral': '<span class="badge bg-secondary">Neutral</span>',
        'negative': '<span class="badge bg-danger">Negative</span>',
        'follow_up_required': '<span class="badge bg-warning">Follow-up Required</span>',
        'contract_signed': '<span class="badge bg-success">Contract Signed</span>',
        'deal_closed': '<span class="badge bg-success">Deal Closed</span>',
        'no_response': '<span class="badge bg-danger">No Response</span>'
    };
    return badges[outcome] || '';
}

function formatDateTime(dateString) {
    if (!dateString) return '—';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '—';
    return date.toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function showNotification(message, type = 'info') {
    const notification = $(`
        <div class="notification ${type}">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            ${message}
        </div>
    `);
    
    $('body').append(notification);
    
    setTimeout(() => {
        notification.addClass('fade-out');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}
