/**
 * EN: Implements frontend interaction behavior in `js/communications.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/communications.js`.
 */
/**
 * Communications Management JavaScript
 * Handles standalone communications page
 */

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

let rootScope;
if (typeof globalThis === 'undefined') {
    rootScope = new Function('return this')();
} else {
    rootScope = globalThis;
}

let currentCommPage = 1;
let currentCommFilters = {
    search: '',
    type: '',
    direction: '',
    priority: ''
};

// Country-City mapping - Load from API
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

// Function to auto-populate city based on country
async function autoPopulateCity(countryFieldId, cityFieldId) {
    try {
        // Use native DOM methods to avoid jQuery toLowerCase issues
        const countryElement = typeof countryFieldId === 'string' 
            ? document.querySelector(countryFieldId) 
            : countryFieldId;
        const cityElement = typeof cityFieldId === 'string' 
            ? document.querySelector(cityFieldId) 
            : cityFieldId;
            
        if (!countryElement || !cityElement) return false;
        
        const selectedCountry = countryElement.value || '';
        if (!selectedCountry) return false;
        
        // Clear existing options using native DOM
        cityElement.innerHTML = '<option value="">Select City</option>';
        
        // Load cities from API
        try {
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
                for (const city of data.cities) {
                    const option = document.createElement('option');
                    option.value = city;
                    option.textContent = city;
                    cityElement.appendChild(option);
                }
                return true;
            }
        } catch (error) {
            console.error('Failed to load cities:', error);
        }
        
        return false;
    } catch (error) {
        // Error auto-populating city - silently fail
        return false;
    }
}

async function inferCountryFromCity(city) {
    if (!city) {
        return '';
    }
    
    const citiesMap = await loadCountriesCitiesFromAPI();
    for (const [countryName, cities] of Object.entries(citiesMap)) {
        if (Array.isArray(cities) && cities.includes(city)) {
            return countryName;
        }
    }
    return '';
}

async function setCountryAndCity(country, city) {
    // No default country - only use if provided
    const resolvedCountry = country || '';
    $('#commCountry').val(resolvedCountry);
    if (resolvedCountry) {
        await autoPopulateCity('#commCountry', '#commCity');
        if (city) {
            setTimeout(() => {
                $('#commCity').val(city);
            }, 100);
        }
    } else {
        // Clear city if no country
        $('#commCity').val('');
    }
}

async function handleContactOptionSelection(selectedOption) {
    if (!selectedOption?.length) {
        // No default - keep empty unless data exists in System Settings
        await setCountryAndCity('');
        return;
    }
    const contactDataStr = selectedOption.attr('data-contact');
    if (!contactDataStr) {
        // No default - keep empty unless data exists in System Settings
        await setCountryAndCity('');
        return;
    }
    try {
        const contactData = JSON.parse(contactDataStr.replaceAll('&quot;', '"'));
        const city = contactData.city || '';
        const country = contactData.country || await inferCountryFromCity(city);
        await setCountryAndCity(country, city);
    } catch (error) {
        // Error parsing contact data - keep empty, no default
        await setCountryAndCity('');
    }
}

function applyDropdownWithCustom(selectSelector, customSelector, value) {
    const selectElement = $(selectSelector);
    const customElement = $(customSelector);
    let matched = false;
    selectElement.find('option').each(function() {
        if (String($(this).val() || '') === String(value || '')) {
            $(this).prop('selected', true);
            matched = true;
            return false;
        }
    });
    if (!matched && value) {
        selectElement.val('Custom');
        customElement.val(value).removeClass('d-none');
    } else {
        customElement.addClass('d-none').val('');
    }
}

function populateDateTimeField(selector, dateValue) {
    const el = document.querySelector(selector);
    if (!el) return;
    const date = dateValue ? new Date(dateValue) : new Date();
    const d = Number.isNaN(date.getTime()) ? new Date() : date;
    if (el._flatpickr) {
        el._flatpickr.setDate(d, false);
    } else {
        const formatted = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + ' ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
        el.value = formatted;
    }
}

function loadContactTypeSelection(comm) {
    if (!comm.contact_type && !comm.contact_id) {
        return;
    }
    const typeMap = {
        'Agent': 'agent',
        'SubAgent': 'subagent',
        'Worker': 'worker',
        'HR': 'hr',
        'Contact': 'contact'
    };
    const contactTypeValue = typeMap[comm.contact_type] || (comm.contact_type ? String(comm.contact_type).toLowerCase() : '');
    if (!contactTypeValue) {
        return;
    }
    $('#commContactType').val(contactTypeValue);
    loadCommContactsByType(contactTypeValue);
    setTimeout(() => {
        const nameSelect = $('#commContact');
        if (comm.contact_name) {
            const matchingOption = nameSelect.find('option').filter(function() {
                const optionText = String($(this).text() || '');
                return optionText.includes(comm.contact_name);
            });
            if (matchingOption.length > 0) {
                matchingOption.first().prop('selected', true);
                $('#commContact').trigger('change');
                return;
            }
        }
        if (comm.contact_id) {
            nameSelect.val(comm.contact_id);
            $('#commContact').trigger('change');
        }
    }, 800);
}

async function applyCountryFromCommunication(comm) {
    let countryValue = comm.contact_country || '';
    const cityValue = comm.contact_city || '';
    if (!countryValue && !cityValue && comm.contact_id) {
        return; // Will populate when contact selection fires
    }
    if (!countryValue && cityValue) {
        countryValue = await inferCountryFromCity(cityValue);
    }
    await setCountryAndCity(countryValue, cityValue);
}

function populateCommunicationForm(comm) {
    $('#communicationModalLabel').text('Edit Communication');
    $('#communicationModal').data('edit-id', comm.id);
    $('#commContactId').val(comm.contact_id || '');
    $('#commType').val(comm.communication_type || 'email');
    $('#commDirection').val(comm.direction || 'outbound');
    $('#commPriority').val(comm.priority || 'medium');
    applyDropdownWithCustom('#commSubject', '#commSubjectCustom', comm.subject || '');
    applyDropdownWithCustom('#commMessage', '#commMessageCustom', comm.message || '');
    applyDropdownWithCustom('#commNextAction', '#commNextActionCustom', comm.next_action || '');
    $('#commOutcome').val(comm.outcome || '');
    populateDateTimeField('#commDateTime', comm.communication_date);
    $('#commFollowUpDate').val(comm.follow_up_date || '');
    loadContactTypeSelection(comm);
    applyCountryFromCommunication(comm).catch(err => console.error('Error applying country:', err));
}

// Initialize the page
$(document).ready(function() {
    // Global error handler to catch any toLowerCase errors
    if (rootScope && typeof rootScope.addEventListener === 'function') {
        rootScope.addEventListener('error', function(event) {
            if (event.message?.includes('toLowerCase')) {
                // toLowerCase error caught - silently handled
                // Prevent the error from breaking the page
                event.preventDefault();
                return true;
            }
        });
    }
    
    loadAllCommunications();
    
    // Fix aria-hidden accessibility warning: modal must not have aria-hidden when visible/focused
    const commModalEl = document.getElementById('communicationModal');
    if (commModalEl) {
        commModalEl.addEventListener('shown.bs.modal', function() {
            this.removeAttribute('aria-hidden');
            this.setAttribute('aria-modal', 'true');
        });
        commModalEl.addEventListener('hide.bs.modal', function() {
            if (document.activeElement && this.contains(document.activeElement)) {
                document.activeElement.blur();
            }
        });
        commModalEl.addEventListener('hidden.bs.modal', function() {
            this.setAttribute('aria-hidden', 'true');
        });
        const obs = new MutationObserver(function() {
            if (commModalEl.classList.contains('show') && commModalEl.getAttribute('aria-hidden') === 'true') {
                commModalEl.removeAttribute('aria-hidden');
            }
        });
        obs.observe(commModalEl, { attributes: true, attributeFilter: ['aria-hidden', 'class'] });
    }
    
    // Initialize Flatpickr for datetime field (English only - no Arabic)
    const commDateTimeEl = document.getElementById('commDateTime');
    if (commDateTimeEl && typeof flatpickr !== 'undefined') {
        var engLocale = { weekdays: { shorthand: ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'], longhand: ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] }, months: { shorthand: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], longhand: ['January','February','March','April','May','June','July','August','September','October','November','December'] }, firstDayOfWeek: 0, rangeSeparator: ' to ', weekAbbreviation: 'Wk', scrollTitle: 'Scroll to increment', toggleTitle: 'Click to toggle', amPM: ['AM','PM'], yearAriaLabel: 'Year', monthAriaLabel: 'Month', hourAriaLabel: 'Hour', minuteAriaLabel: 'Minute', time_24hr: false };
        if (!commDateTimeEl._flatpickr) {
            flatpickr(commDateTimeEl, { theme: 'dark', locale: engLocale, dateFormat: 'Y-m-d H:i', altInput: false, allowInput: true, enableTime: true, time_24hr: false, defaultDate: commDateTimeEl.value || new Date(), clickOpens: true, disableMobile: 'true' });
        }
    }
    
    // Handle search filter - use input event for better responsiveness
    const searchDebounced = debounce(function() {
        try {
            const inputElement = document.getElementById('commSearch');
            if (!inputElement) return;
            
            // Use native value property to avoid jQuery's toLowerCase issue
            const searchValue = inputElement.value || '';
            currentCommFilters.search = String(searchValue).trim();
            currentCommPage = 1; // Reset to first page when searching
            loadAllCommunications();
        } catch (error) {
            // Search input error - silently fail
        }
    }, 500);
    
    const commSearchElement = document.getElementById('commSearch');
    if (commSearchElement) {
        commSearchElement.addEventListener('input', searchDebounced);
    }
    
    // Also handle Enter key - reuse the same element reference
    if (commSearchElement) {
        commSearchElement.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                try {
                    const searchValue = this.value || '';
                    currentCommFilters.search = String(searchValue).trim();
                    currentCommPage = 1;
                    loadAllCommunications();
                } catch (error) {
                    // Search keypress error - silently fail
                }
            }
        });
    }
    
    // Filter handlers - use native DOM to avoid jQuery issues
    const typeFilter = document.getElementById('commTypeFilter');
    const directionFilter = document.getElementById('commDirectionFilter');
    const priorityFilter = document.getElementById('commPriorityFilter');
    
    if (typeFilter) {
        typeFilter.addEventListener('change', function() {
            try {
                currentCommFilters.type = this.value || '';
                currentCommPage = 1;
                loadAllCommunications();
            } catch (error) {
                // Type filter error - silently fail
            }
        });
    }
    
    if (directionFilter) {
        directionFilter.addEventListener('change', function() {
            try {
                currentCommFilters.direction = this.value || '';
                currentCommPage = 1;
                loadAllCommunications();
            } catch (error) {
                // Direction filter error - silently fail
            }
        });
    }
    
    if (priorityFilter) {
        priorityFilter.addEventListener('change', function() {
            try {
                currentCommFilters.priority = this.value || '';
                currentCommPage = 1;
                loadAllCommunications();
            } catch (error) {
                // Priority filter error - silently fail
            }
        });
    }
    
    // Page size handlers - use native DOM
    const pageSizeTop = document.getElementById('commContactsPerPageTop');
    const pageSizeBottom = document.getElementById('commContactsPerPageBottom');
    
    if (pageSizeTop) {
        pageSizeTop.addEventListener('change', function() {
            try {
                let selectedValue = Number.parseInt(this.value || '5', 10) || 5;
                // Ensure value is between 1 and 100
                selectedValue = Math.max(1, Math.min(100, selectedValue));
                const valueStr = String(selectedValue);
                if (pageSizeBottom) {
                    pageSizeBottom.value = valueStr;
                }
                this.value = valueStr; // Update to validated value
                currentCommPage = 1;
                loadAllCommunications();
            } catch (error) {
                // Page size top error - silently fail
            }
        });
    }
    
    if (pageSizeBottom) {
        pageSizeBottom.addEventListener('change', function() {
            try {
                let selectedValue = Number.parseInt(this.value || '5', 10) || 5;
                // Ensure value is between 1 and 100
                selectedValue = Math.max(1, Math.min(100, selectedValue));
                const valueStr = String(selectedValue);
                if (pageSizeTop) {
                    pageSizeTop.value = valueStr;
                }
                this.value = valueStr; // Update to validated value
                currentCommPage = 1;
                loadAllCommunications();
            } catch (error) {
                // Page size bottom error - silently fail
            }
        });
    }
    
    $('#selectAllComms').on('change', function() {
        $('.comm-checkbox').prop('checked', $(this).prop('checked'));
        updateBulkButtons();
    });
    
    // Handle Subject dropdown - show/hide custom input
    $('#commSubject').on('change', function() {
        if ($(this).val() === 'Custom') {
            $('#commSubjectCustom').removeClass('d-none').focus();
        } else {
            $('#commSubjectCustom').addClass('d-none').val('');
        }
    });
    
    // Handle Message dropdown - show/hide custom textarea
    $('#commMessage').on('change', function() {
        if ($(this).val() === 'Custom') {
            $('#commMessageCustom').removeClass('d-none').focus();
        } else {
            $('#commMessageCustom').addClass('d-none').val('');
        }
    });
    
    // Handle Next Action dropdown - show/hide custom input and auto-calculate follow-up date
    $('#commNextAction').on('change', function() {
        const nextAction = $(this).val();
        
        if (nextAction === 'Custom') {
            $('#commNextActionCustom').removeClass('d-none').focus();
        } else {
            $('#commNextActionCustom').addClass('d-none').val('');
            
            // Auto-calculate follow-up date based on next action
            if (nextAction && nextAction !== '') {
                const followUpDate = calculateFollowUpDate(nextAction);
                if (followUpDate) {
                    $('#commFollowUpDate').val(followUpDate);
                }
            }
        }
    });
    
    // Handle individual checkbox changes
    $(document).on('change', '.comm-checkbox', function() {
        updateBulkButtons();
    });
    
    // Button handlers
    $(document).on('click', '[data-action="open-communication-modal"]', function() {
        openCommunicationModal();
    });
    
    $(document).on('click', '[data-action="load-communications"]', function() {
        loadAllCommunications();
    });
    
    $(document).on('click', '[data-action="save-communication"]', function() {
        saveCommunication();
    });
    
    $(document).on('click', '[data-action="view-communication"]', function() {
        const commId = $(this).attr('data-comm-id') || $(this).data('comm-id');
        if (commId) {
            viewCommunication(commId);
        }
    });
    
    $(document).on('click', '[data-action="edit-communication"]', function() {
        const commId = $(this).attr('data-comm-id') || $(this).data('comm-id');
        if (commId) {
            editCommunication(commId);
        }
    });
    
    $(document).on('click', '[data-action="delete-communication"]', function() {
        const commId = $(this).attr('data-comm-id') || $(this).data('comm-id');
        if (commId) {
            deleteCommunication(commId);
        }
    });
    
    // Bulk action handlers
    $('#bulkEditCommsBtn').on('click', function() {
        bulkEditCommunications();
    });
    
    $('#bulkDeleteCommsBtn').on('click', function() {
        bulkDeleteCommunications();
    });
    
    // Bulk edit modal option buttons
    $(document).on('click', '.bulk-option-btn', function() {
        const action = $(this).data('action');
        $('#bulkEditModal').modal('hide');
        
        const selected = getSelectedCommunications();
        const count = selected.length;
        
        switch(action) {
            case 'priority':
                $('#bulkPriorityCount').text(count);
                $('#bulkPrioritySelect').val('');
                $('#bulkPriorityModal').modal('show');
                break;
            case 'direction':
                $('#bulkDirectionCount').text(count);
                $('#bulkDirectionSelect').val('');
                $('#bulkDirectionModal').modal('show');
                break;
            case 'type':
                $('#bulkTypeCount').text(count);
                $('#bulkTypeSelect').val('');
                $('#bulkTypeModal').modal('show');
                break;
            case 'outcome':
                $('#bulkOutcomeCount').text(count);
                $('#bulkOutcomeSelect').val('');
                $('#bulkOutcomeModal').modal('show');
                break;
        }
    });
    
    // Bulk update buttons
    $('#updateBulkPriorityBtn').on('click', function() {
        const priority = $('#bulkPrioritySelect').val();
        if (!priority) {
            showNotification('Please select a priority', 'warning');
            return;
        }
        bulkUpdateCommunications('priority', priority);
    });
    
    $('#updateBulkDirectionBtn').on('click', function() {
        const direction = $('#bulkDirectionSelect').val();
        if (!direction) {
            showNotification('Please select a direction', 'warning');
            return;
        }
        bulkUpdateCommunications('direction', direction);
    });
    
    $('#updateBulkTypeBtn').on('click', function() {
        const type = $('#bulkTypeSelect').val();
        if (!type) {
            showNotification('Please select a type', 'warning');
            return;
        }
        bulkUpdateCommunications('communication_type', type);
    });
    
    $('#updateBulkOutcomeBtn').on('click', function() {
        const outcome = $('#bulkOutcomeSelect').val();
        if (!outcome) {
            showNotification('Please select an outcome', 'warning');
            return;
        }
        bulkUpdateCommunications('outcome', outcome);
    });
    
    $(document).on('click', '[data-action="bulk-delete-communications"]', function() {
        bulkDeleteCommunications();
    });
    
    $(document).on('click', '[data-action="export-communications"]', function() {
        exportCommunications();
    });
    
    $(document).on('click', '[data-action="clear-filters"]', function() {
        $('#commSearch').val('');
        $('#commTypeFilter').val('');
        $('#commDirectionFilter').val('');
        $('#commPriorityFilter').val('');
        currentCommFilters = { search: '', type: '', direction: '', priority: '' };
        currentCommPage = 1;
        loadAllCommunications();
    });
    
    // Pagination handlers
    $(document).on('click', '.comm-page-btn', function() {
        const requestedPage = Number.parseInt($(this).data('page'), 10);
        currentCommPage = Number.isNaN(requestedPage) ? 1 : requestedPage;
        loadAllCommunications();
    });
    
    $(document).on('click', '[data-action="go-first-page"]', function() {
        currentCommPage = 1;
        loadAllCommunications();
    });
    
    $(document).on('click', '[data-action="go-previous-page"]', function() {
        if (currentCommPage > 1) {
            currentCommPage--;
            loadAllCommunications();
        }
    });
    
    $(document).on('click', '[data-action="go-next-page"]', function() {
        try {
            const pageSizeElement = document.getElementById('commContactsPerPageTop');
            let limitValue = pageSizeElement ? (pageSizeElement.value || '5') : '5';
            let limit = Number.parseInt(limitValue, 10) || 5;
            // Ensure limit is between 1 and 100
            limit = Math.max(1, Math.min(100, limit));
            // Get total from current data if available, or calculate from record info
            const recordInfoElement = document.getElementById('commRecordInfoTop');
            const recordText = recordInfoElement ? recordInfoElement.textContent || '' : '';
            const match = /of (\d+)/.exec(recordText);
            const total = match?.[1] ? Number.parseInt(match[1], 10) : 0;
            const totalPages = limit > 0 && total > 0 ? Math.ceil(total / limit) : 0;
            if (currentCommPage < totalPages) {
                currentCommPage++;
                loadAllCommunications();
            }
        } catch (error) {
            // Error in go-next-page - silently fail
        }
    });
    
    $(document).on('click', '[data-action="go-last-page"]', function() {
        try {
            const pageSizeElement = document.getElementById('commContactsPerPageTop');
            let limitValue = pageSizeElement ? (pageSizeElement.value || '5') : '5';
            let limit = Number.parseInt(limitValue, 10) || 5;
            // Ensure limit is between 1 and 100
            limit = Math.max(1, Math.min(100, limit));
            // Get total from current data if available, or calculate from record info
            const recordInfoElement = document.getElementById('commRecordInfoTop');
            const recordText = recordInfoElement ? recordInfoElement.textContent || '' : '';
            const match = /of (\d+)/.exec(recordText);
            const total = match?.[1] ? Number.parseInt(match[1], 10) : 0;
            const totalPages = limit > 0 && total > 0 ? Math.ceil(total / limit) : 0;
            currentCommPage = totalPages || 1;
            loadAllCommunications();
        } catch (error) {
            // Error in go-last-page - silently fail
        }
    });
    
    // Handle contact type selection in modal
    $('#commContactType').on('change', function() {
        handleCommContactTypeSelection();
    });
    
    // Handle contact name selection - auto-populate country and city
    $('#commContact').on('change', function() {
        const selectedName = $(this).val();
        if (!selectedName) {
            return;
        }
        $('#commContactId').val(selectedName);
        handleContactOptionSelection($(this).find('option:selected')).catch(err => console.error('Error handling contact selection:', err));
    });

    // Handle print for communication details
    $(document).on('click', '[data-action="print-communication"]', function() {
        const modalBody = document.getElementById('viewCommunicationContent');
        if (!modalBody) {
            showNotification('No communication details available to print', 'warning');
            return;
        }

        const printWindow = rootScope && typeof rootScope.open === 'function'
            ? rootScope.open('', '_blank', 'width=900,height=700')
            : null;
        if (!printWindow) {
            showNotification('Unable to open print window. Please allow pop-ups for this site.', 'error');
            return;
        }

        const printDocument = printWindow.document;
        const head = printDocument.head || printDocument.createElement('head');
        head.innerHTML = '<title>Communication Details</title>';
        for (const node of document.querySelectorAll('link[rel="stylesheet"], style')) {
            head.appendChild(node.cloneNode(true));
        }
        if (!printDocument.head) {
            printDocument.documentElement.insertBefore(head, printDocument.body);
        }
        const body = printDocument.body || printDocument.createElement('body');
        body.innerHTML = '';
        const wrapper = printDocument.createElement('div');
        wrapper.className = 'print-communication';
        wrapper.innerHTML = modalBody.innerHTML;
        body.appendChild(wrapper);
        if (!printDocument.body) {
            printDocument.documentElement.appendChild(body);
        }
        printDocument.close();
        printWindow.focus();
        printWindow.print();
    });
    
    // Handle country change - auto-populate city
    $('#commCountry').on('change', async function() {
        await autoPopulateCity('#commCountry', '#commCity');
    });
    
    // Initialize country dropdown on page load
    populateCountryDropdown().catch(err => console.error('Error populating country dropdown:', err));
});

// Load all communications with filters and pagination
function loadAllCommunications() {
    let limit = Number.parseInt($('#commContactsPerPageTop').val() || 5, 10) || 5;
    // Ensure limit is between 1 and 100
    limit = Math.max(1, Math.min(100, limit));
    
    const requestData = {
        action: 'get_recent_communications',
        page: currentCommPage,
        limit: limit
    };
    
    // Always include search parameter (empty string is fine)
    if (currentCommFilters.search !== undefined) {
        requestData.search = currentCommFilters.search;
    }
    if (currentCommFilters.type) {
        requestData.type = currentCommFilters.type;
    }
    if (currentCommFilters.direction) {
        requestData.direction = currentCommFilters.direction;
    }
    if (currentCommFilters.priority) {
        requestData.priority = currentCommFilters.priority;
    }
    
    $.ajax({
        url: getApiBase() + '/contacts/simple_contacts.php',
        method: 'GET',
        data: requestData,
        dataType: 'json',
        cache: false,
        success: function(response) {
            try {
                if (response?.success && response.data) {
                    displayAllCommunications(response.data);
                } else {
                    $('#communicationsTableBody').html('<tr><td colspan="12" class="text-center">No communications found</td></tr>');
                    updateRecordInfo({ total: 0, page: 1, limit: 5, total_pages: 0 });
                    updateStatusCards({ stats: {} });
                }
            } catch (error) {
                // Error processing communications response - show notification instead
                showNotification('Error processing communications: ' + error.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            // Error loading communications - show notification instead
            const errorMessage = xhr.status === 404 
                ? 'API endpoint not found. Please check the server configuration.' 
                : (xhr.responseText || error || 'Unknown error');
            showNotification('Error loading communications: ' + errorMessage, 'error');
            
            // Show empty state in table
            $('#communicationsTableBody').html('<tr><td colspan="12" class="text-center py-4 text-danger">Error loading communications. Please try refreshing the page.</td></tr>');
            updateRecordInfo({ total: 0, page: 1, limit: 5, total_pages: 0 });
            updateStatusCards({ stats: {} });
        }
    });
}

// Display all communications in table format
function displayAllCommunications(data) {
    if (!data) {
        // Data is null or undefined - silently return
        return;
    }
    
    const tbody = $('#communicationsTableBody');
    if (!tbody.length) {
        // Table body not found - silently return
        return;
    }
    
    tbody.empty();
    
    if (data.communications && Array.isArray(data.communications) && data.communications.length > 0) {
        // Get the limit from the data or from the dropdown
        let limit = data.limit ? Number.parseInt(data.limit, 10) : 5;
        if (!limit || limit < 1) {
            limit = Number.parseInt($('#commContactsPerPageTop').val() || 5, 10) || 5;
        }
        // Ensure limit is between 1 and 100
        limit = Math.max(1, Math.min(100, limit));
        
        // Safety check: Only display up to the limit, even if API returns more
        const communicationsToDisplay = data.communications.slice(0, limit);
        
        for (const comm of communicationsToDisplay) {
            appendCommunicationRow(tbody, comm);
        }
    } else {
        tbody.html('<tr><td colspan="12" class="text-center py-4">No communications found</td></tr>');
    }
    
    updateRecordInfo(data);
    updatePagination(data);
    updateStatusCards(data);
    updateBulkButtons(); // Update bulk action button states
}

function appendCommunicationRow(tbody, comm) {
    if (!comm) {
        return;
    }
    const priorityBadge = getPriorityBadge(comm.priority || 'medium');
    const direction = comm.direction || 'outbound';
    const directionBadge = direction === 'outbound' 
        ? '<span class="badge bg-primary">Outbound</span>'
        : '<span class="badge bg-success">Inbound</span>';
    const commType = comm.communication_type || 'email';
    const typeBadge = `<span class="badge bg-info">${commType}</span>`;
    
    const commIdNum = Number.parseInt(comm?.id, 10) || 0;
    const commIdStr = String(commIdNum || 0);
    const commId = 'CM' + commIdStr.padStart(4, '0');
    const safeContactName = (comm.contact_name || 'Unknown').toString();
    const safeCountry = (comm.contact_country || '-').toString();
    const safeCity = (comm.contact_city || '-').toString();
    const safeSubject = (comm.subject || '-').toString();
    const safeOutcome = comm.outcome ? capitalize(String(comm.outcome)) : '-';
    
    tbody.append(`
        <tr>
            <td title="${commId}">${commId}</td>
            <td title="${safeContactName}">${safeContactName}</td>
            <td title="${safeCountry}">${safeCountry}</td>
            <td title="${safeCity}">${safeCity}</td>
            <td>${typeBadge}</td>
            <td>${directionBadge}</td>
            <td>${priorityBadge}</td>
            <td title="${safeSubject}">${safeSubject}</td>
            <td title="${safeOutcome}">${safeOutcome}</td>
            <td title="${formatDateTime(comm.communication_date || '')}">${formatDateTime(comm.communication_date || '')}</td>
            <td>
                <input type="checkbox" class="comm-checkbox" value="${commIdStr}">
            </td>
            <td>
                <button class="btn btn-sm btn-outline-info" data-action="view-communication" data-comm-id="${commIdStr}" title="View">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline-warning" data-action="edit-communication" data-comm-id="${commIdStr}" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" data-action="delete-communication" data-comm-id="${commIdStr}" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `);
}

// Update status cards
function updateStatusCards(data) {
    if (!data) {
        data = { stats: {} };
    }
    const stats = data.stats || {};
    
    $('#statTotal').text(stats.total_all || 0);
    $('#statInbound').text(stats.inbound_all || 0);
    $('#statOutbound').text(stats.outbound_all || 0);
    $('#statUrgent').text(stats.urgent_all || 0);
}

// Update record info
function updateRecordInfo(data) {
    if (!data) {
        data = { total: 0 };
    }
    const total = Number.parseInt(data.total, 10) || 0;
    const page = Number.parseInt(currentCommPage, 10) || 1;
    let limit = Number.parseInt($('#commContactsPerPageTop').val() || 5, 10) || 5;
    // Ensure limit is between 1 and 100
    limit = Math.max(1, Math.min(100, limit));
    const start = total > 0 ? ((page - 1) * limit) + 1 : 0;
    const end = total > 0 ? Math.min(start + limit - 1, total) : 0;
    
    const recordInfo = total > 0 ? `Showing ${start}-${end} of ${total} communications` : 'Showing 0 of 0 communications';
    $('#commRecordInfoTop').text(recordInfo);
    $('#commRecordInfoBottom').text(recordInfo);
}

// Update pagination
function updatePagination(data) {
    if (!data) {
        data = { total: 0 };
    }
    const total = Number.parseInt(data.total, 10) || 0;
    let limit = Number.parseInt($('#commContactsPerPageTop').val() || 5, 10) || 5;
    // Ensure limit is between 1 and 100
    limit = Math.max(1, Math.min(100, limit));
    const totalPages = limit > 0 ? Math.ceil(total / limit) : 0;
    
    let pageNumbers = '';
    
    if (totalPages > 1) {
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentCommPage - 2 && i <= currentCommPage + 2)) {
                pageNumbers += `<button class="btn btn-sm btn-outline-primary comm-page-btn ${i === currentCommPage ? 'active' : ''}" 
                              data-page="${i}">${i}</button>`;
            } else if (i === currentCommPage - 3 || i === currentCommPage + 3) {
                pageNumbers += `<span class="btn btn-sm btn-outline-primary disabled">...</span>`;
            }
        }
    }
    
    $('#commPageNumbers').html(pageNumbers);
    $('#commPageNumbersBottom').html(pageNumbers);
}

// Utility functions
function getPriorityBadge(priority) {
    if (!priority) return '<span class="badge bg-secondary">Medium</span>';
    const badges = {
        'low': '<span class="badge bg-secondary">Low</span>',
        'medium': '<span class="badge bg-info">Medium</span>',
        'high': '<span class="badge bg-warning">High</span>',
        'urgent': '<span class="badge bg-danger">Urgent</span>'
    };
    const priorityLower = String(priority).toLowerCase();
    return badges[priorityLower] || '<span class="badge bg-secondary">Medium</span>';
}

function formatDateTime(dateTime) {
    if (!dateTime) return '-';
    const date = new Date(dateTime);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
}

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1).replaceAll('_', ' ');
}

// Calculate follow-up date based on next action text
function calculateFollowUpDate(nextAction) {
    if (!nextAction) return null;
    
    const today = new Date();
    let daysToAdd = 0;
    
    // Parse common follow-up timeframes
    const action = String(nextAction).toLowerCase();
    
    if (action.includes('1 day') || action.includes('1day')) {
        daysToAdd = 1;
    } else if (action.includes('3 days') || action.includes('3days')) {
        daysToAdd = 3;
    } else if (action.includes('1 week') || action.includes('1week') || action.includes('7 days')) {
        daysToAdd = 7;
    } else if (action.includes('2 weeks') || action.includes('2weeks') || action.includes('14 days')) {
        daysToAdd = 14;
    } else if (action.includes('1 month') || action.includes('1month') || action.includes('30 days')) {
        daysToAdd = 30;
    } else {
        // Default to null if no match - user can manually set date
        return null;
    }
    
    const followUpDate = new Date(today);
    followUpDate.setDate(today.getDate() + daysToAdd);
    
    // Format as YYYY-MM-DD for date input
    const year = followUpDate.getFullYear();
    const month = String(followUpDate.getMonth() + 1).padStart(2, '0');
    const day = String(followUpDate.getDate()).padStart(2, '0');
    
    return `${year}-${month}-${day}`;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function updateBulkButtons() {
    const selectedCount = $('.comm-checkbox:checked').length;
    const hasSelection = selectedCount > 0;
    
    $('#bulkEditCommsBtn').prop('disabled', !hasSelection);
    $('#bulkDeleteCommsBtn').prop('disabled', !hasSelection);
}

function getSelectedCommunications() {
    const selected = [];
    $('.comm-checkbox:checked').each(function() {
        const val = $(this).val();
        if (val !== undefined && val !== null && val !== '') {
            selected.push(String(val));
        }
    });
    return selected;
}

// Bulk delete
function bulkDeleteCommunications() {
    const selected = getSelectedCommunications();
    
    if (selected.length === 0) {
        showNotification('Please select communications to delete', 'warning');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete ${selected.length} communication(s)?`)) {
        return;
    }
    
    // Delete logic here
    $.ajax({
        url: getApiBase() + '/contacts/simple_contacts.php',
        method: 'POST',
        data: JSON.stringify({ 
            action: 'bulk_delete_communications', 
            ids: selected 
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory && typeof window.unifiedHistory.refreshIfOpen === 'function') {
                    window.unifiedHistory.refreshIfOpen().catch(() => {});
                }
                
                showNotification(`${selected.length} communication(s) deleted successfully`, 'success');
                $('.comm-checkbox:checked').prop('checked', false);
                $('#selectAllComms').prop('checked', false);
                updateBulkButtons();
                loadAllCommunications();
            } else {
                showNotification('Error deleting communications: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error deleting communications: ' + error, 'error');
        }
    });
}

// Bulk edit
function bulkEditCommunications() {
    const selected = getSelectedCommunications();
    
    if (selected.length === 0) {
        showNotification('Please select communications to edit', 'warning');
        return;
    }
    
    $('#bulkEditCount').text(selected.length);
    $('#bulkEditModal').modal('show');
}

// Export communications
function exportCommunications() {
    const search = $('#commSearch').val() || '';
    const type = $('#commTypeFilter').val() || '';
    const direction = $('#commDirectionFilter').val() || '';
    const priority = $('#commPriorityFilter').val() || '';
    
    const params = new URLSearchParams({
        action: 'export_communications'
    });
    
    if (search) params.append('search', search);
    if (type) params.append('communication_type', type);
    if (direction) params.append('direction', direction);
    if (priority) params.append('priority', priority);
    
    const apiBase = getApiBase();
    const exportUrl = apiBase + '/contacts/simple_contacts.php?' + params.toString();
    
    window.location.href = exportUrl;
}

// Bulk update communications
function bulkUpdateCommunications(field, value) {
    const selected = getSelectedCommunications();
    
    if (selected.length === 0) {
        showNotification('Please select communications to update', 'warning');
        return;
    }
    
    $.ajax({
        url: getApiBase() + '/contacts/simple_contacts.php',
        method: 'POST',
        data: JSON.stringify({ 
            action: 'bulk_update_communications', 
            ids: selected,
            field: field,
            value: value
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const fieldName = field.replaceAll('_', ' ');
                showNotification(`${selected.length} communication(s) ${fieldName} updated successfully`, 'success');
                
                // Close all modals
                $('#bulkPriorityModal, #bulkDirectionModal, #bulkTypeModal, #bulkOutcomeModal').modal('hide');
                
                // Clear selections
                $('.comm-checkbox:checked').prop('checked', false);
                $('#selectAllComms').prop('checked', false);
                updateBulkButtons();
                
                // Reload communications
                loadAllCommunications();
            } else {
                showNotification('Error updating communications: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error updating communications: ' + error, 'error');
        }
    });
}

// Show notification
function showNotification(message, type = 'info') {
    const iconMap = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    
    const notification = $(`
        <div class="notification ${type}">
            <i class="fas fa-${iconMap[type] || 'info-circle'}"></i>
            ${message}
        </div>
    `);
    
    $('body').append(notification);
    
    setTimeout(() => {
        notification.fadeOut(300, function() {
            $(this).remove();
        });
    }, 3000);
}

// Open communication modal
function openCommunicationModal() {
    $('#communicationModalLabel').text('Add Communication');
    $('#communicationModal').removeData('edit-id');
    
    $('#communicationForm')[0].reset();
    $('#commContactId').val('');
    $('#commContactType').val('');
    $('#commContact').val('');
    $('#commType').val('email');
    $('#commDirection').val('outbound');
    $('#commPriority').val('medium');
    
    // Reset Subject, Message, Next Action dropdowns and hide custom fields
    $('#commSubject').val('');
    $('#commSubjectCustom').addClass('d-none').val('');
    $('#commMessage').val('');
    $('#commMessageCustom').addClass('d-none').val('');
    $('#commNextAction').val('');
    $('#commNextActionCustom').addClass('d-none').val('');
    
    // Set default communication date/time to current date/time
    const dtEl = document.getElementById('commDateTime');
    if (dtEl) {
        if (dtEl._flatpickr) {
            dtEl._flatpickr.setDate(new Date(), false);
        } else {
            const now = new Date();
            dtEl.value = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0') + ' ' + String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        }
    }
    
    // Clear follow-up date
    const fuEl = document.getElementById('commFollowUpDate');
    if (fuEl) {
        if (fuEl._flatpickr) {
            fuEl._flatpickr.clear();
        } else {
            fuEl.value = '';
        }
    }
    
    // Don't set default country - keep empty unless data exists in System Settings
    $('#commCountry').val('');
    $('#commCity').val(''); // Clear city as well
    // Cities will populate when a country is selected
    
    $('#communicationModal').modal('show');
}

// Populate country dropdown
async function populateCountryDropdown() {
    const countrySelect = $('#commCountry');
    countrySelect.empty();
    countrySelect.append('<option value="">Select Country</option>');
    
    // Load countries from API - only populate if data exists in System Settings
    try {
        const citiesMap = await loadCountriesCitiesFromAPI();
        if (citiesMap && Object.keys(citiesMap).length > 0) {
            for (const country of Object.keys(citiesMap)) {
                countrySelect.append(`<option value="${country}">${country}</option>`);
            }
        }
        // If no countries exist, dropdown stays empty with just "Select Country" option
    } catch (err) {
        console.error('Error loading countries:', err);
        // On error, keep dropdown empty
    }
}

// Edit communication
function editCommunication(commId) {
    $.ajax({
        url: getApiBase() + '/contacts/simple_contacts.php',
        method: 'GET',
        data: { action: 'get_communication', id: commId },
        dataType: 'json',
        success: function(response) {
            if (response.success && response.data) {
                populateCommunicationForm(response.data);
                $('#communicationModal').modal('show');
                return;
            }
            const errorMsg = response?.message || 'Unknown error';
            showNotification('Error loading communication: ' + errorMsg, 'error');
        },
        error: function(xhr, status, error) {
            showNotification('Error loading communication: ' + error, 'error');
        }
    });
}

// View communication
function viewCommunication(commId) {
    $.ajax({
        url: getApiBase() + '/contacts/simple_contacts.php',
        method: 'GET',
        data: { action: 'get_communication', id: commId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                const comm = response.data;
                const priorityLabel = comm.priority ? capitalize(comm.priority) : 'N/A';
                const outcomeLabel = comm.outcome ? capitalize(comm.outcome) : 'No Status';
                const directionLabel = comm.direction === 'outbound' ? 'Outbound' : 'Inbound';
                const contactLocation = [comm.contact_city, comm.contact_country].filter(Boolean).join(', ') || 'Not specified';
                
                const chips = `
                    <div class="comm-detail__chips">
                        <span class="comm-chip comm-chip--${(comm.priority || 'default').toLowerCase()}">${priorityLabel}</span>
                        <span class="comm-chip comm-chip--outline">${directionLabel}</span>
                        ${comm.outcome ? `<span class="comm-chip comm-chip--accent">${outcomeLabel}</span>` : ''}
                    </div>
                `;
                
                const html = `
                    <div class="comm-detail">
                        <div class="comm-detail__header">
                            <div>
                                <p class="comm-detail__label">Contact</p>
                                <h5>${comm.contact_name || 'Unknown'}</h5>
                                <p class="comm-detail__meta">${comm.contact_company || 'No company'}</p>
                            </div>
                            ${chips}
                        </div>

        <div class="comm-detail__grid">
            <div class="comm-tile">
                <span>Type</span>
                <p>${comm.communication_type || 'N/A'}</p>
            </div>
            <div class="comm-tile">
                <span>Direction</span>
                <p>${directionLabel}</p>
            </div>
            <div class="comm-tile">
                <span>Priority</span>
                <p>${priorityLabel}</p>
            </div>
            <div class="comm-tile">
                <span>Outcome</span>
                <p>${outcomeLabel}</p>
            </div>
            <div class="comm-tile">
                <span>Follow-up Date</span>
                <p>${comm.follow_up_date || 'Not set'}</p>
            </div>
            <div class="comm-tile">
                <span>Date & Time</span>
                <p>${formatDateTime(comm.communication_date)}</p>
            </div>
        </div>

        <div class="comm-section">
            <h6>Subject</h6>
            <p>${comm.subject || 'No subject added'}</p>
        </div>
        <div class="comm-section">
            <h6>Message / Notes</h6>
            <p>${comm.message || 'No message provided.'}</p>
        </div>
        ${comm.next_action ? `
            <div class="comm-section">
                <h6>Next Action</h6>
                <p>${comm.next_action}</p>
            </div>` : ''}

        <div class="comm-info">
            <div>
                <span>Location</span>
                <p>${contactLocation}</p>
            </div>
            <div>
                <span>Reference ID</span>
                <p>CM${String(comm.id).padStart(4, '0')}</p>
            </div>
        </div>
    </div>
`;
                
                $('#viewCommunicationContent').html(html);
                $('#viewCommunicationModal').modal('show');
            } else {
                showNotification('Error loading communication: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification('Error loading communication: ' + error, 'error');
        }
    });
}

// Delete communication
function deleteCommunication(commId) {
    if (confirm('⚠️ Are you sure you want to delete this communication? This action cannot be undone.')) {
        $.ajax({
            url: getApiBase() + '/contacts/simple_contacts.php',
            method: 'POST',
            data: JSON.stringify({ action: 'delete_communication', id: commId }),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNotification('Communication deleted successfully', 'success');
                    loadAllCommunications();
                } else {
                    showNotification('Error deleting communication: ' + response.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotification('Error deleting communication: ' + error, 'error');
            }
        });
    }
}

// Save communication
function saveCommunication() {
    const contactId = $('#commContactId').val() || $('#commContact').val();
    
    // Get subject value (use custom if "Custom" was selected)
    let subjectValue = $('#commSubject').val();
    if (subjectValue === 'Custom') {
        subjectValue = $('#commSubjectCustom').val() || '';
    }
    
    // Get message value (use custom if "Custom" was selected)
    let messageValue = $('#commMessage').val();
    if (messageValue === 'Custom') {
        messageValue = $('#commMessageCustom').val() || '';
    }
    
    // Get next action value (use custom if "Custom" was selected)
    let nextActionValue = $('#commNextAction').val();
    if (nextActionValue === 'Custom') {
        nextActionValue = $('#commNextActionCustom').val() || '';
    }
    
    // Get communication date/time (if provided, otherwise use server default)
    const commDateTime = $('#commDateTime').val();
    
    const formData = {
        contact_id: contactId,
        communication_type: $('#commType').val(),
        direction: $('#commDirection').val(),
        priority: $('#commPriority').val(),
        subject: subjectValue,
        message: messageValue,
        outcome: $('#commOutcome').val(),
        next_action: nextActionValue,
        follow_up_date: $('#commFollowUpDate').val(),
        country: $('#commCountry').val(),
        city: $('#commCity').val()
    };
    
    // Add communication_date if provided
    if (commDateTime) {
        formData.communication_date = commDateTime;
    }
    
    if (!formData.contact_id) {
        showNotification('Please select a contact', 'error');
        return;
    }
    
    if (!formData.communication_type) {
        showNotification('Please select a communication type', 'error');
        return;
    }
    
    // Subject or message must be provided
    const hasSubject = formData.subject?.trim();
    const hasMessage = formData.message?.trim();
    if (!hasSubject && !hasMessage) {
        showNotification('Subject or message is required', 'error');
        return;
    }
    
    const editId = $('#communicationModal').data('edit-id');
    const isEdit = editId !== undefined;
    
    // Set action for API
    const action = isEdit ? 'edit_communication' : 'add_communication';
    
    if (isEdit) {
        formData.id = editId;
        // Ensure contact_id is included for edit (needed to update contact country/city)
        if (!formData.contact_id) {
            formData.contact_id = contactId;
        }
    }
    
    $.ajax({
        url: getApiBase() + '/contacts/simple_contacts.php?action=' + action,
        method: 'POST',
        data: JSON.stringify(formData),
        contentType: 'application/json',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory && typeof window.unifiedHistory.refreshIfOpen === 'function') {
                    window.unifiedHistory.refreshIfOpen().catch(() => {});
                }
                
                const action = isEdit ? 'updated' : 'added';
                const commId = response.data?.id ? ' (ID: CM' + String(response.data.id).padStart(4, '0') + ')' : '';
                showNotification(`Communication ${action} successfully${commId}`, 'success');
                $('#communicationModal').modal('hide');
                loadAllCommunications();
                
                // Show follow-up reminder if follow-up date is set
                const followUpDate = formData.follow_up_date;
                if (followUpDate) {
                    const followUpDateObj = new Date(followUpDate);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    followUpDateObj.setHours(0, 0, 0, 0);
                    const daysDiff = Math.ceil((followUpDateObj - today) / (1000 * 60 * 60 * 24));
                    
                    if (daysDiff >= 0 && daysDiff <= 7) {
                        setTimeout(() => {
                            const pluralSuffix = daysDiff === 1 ? '' : 's';
                            showNotification(`⚠️ Follow-up scheduled in ${daysDiff} day${pluralSuffix}`, 'warning');
                        }, 1500);
                    }
                }
            } else {
                showNotification((isEdit ? 'Error updating' : 'Error adding') + ' communication: ' + response.message, 'error');
            }
        },
        error: function(xhr, status, error) {
            showNotification((isEdit ? 'Error updating' : 'Error adding') + ' communication: ' + error, 'error');
        }
    });
}

// Handle communication contact type selection
function handleCommContactTypeSelection() {
    const selectedType = $('#commContactType').val();
    const nameSelect = $('#commContact');
    
    nameSelect.empty();
    
    // Clear country and city when contact type changes - keep empty unless data exists
    $('#commCountry').val('');
    $('#commCity').val('');
    // Cities will populate when a country is selected
    
    if (selectedType && selectedType !== '') {
        loadCommContactsByType(selectedType);
    } else {
        nameSelect.append('<option value="">Select Contact Type First</option>');
    }
}

// Load contacts by type for communications
function loadCommContactsByType(type) {
    const nameSelect = $('#commContact');
    nameSelect.empty().append('<option value="">Loading...</option>');
    
    $.ajax({
        url: getApiBase() + '/contacts/simple_contacts.php',
        method: 'GET',
        data: { action: 'get_all_contacts' },
        dataType: 'json',
        success: function(response) {
            nameSelect.empty();
            
            if (!response?.success) {
                nameSelect.append('<option value="">Error loading contacts</option>');
                return;
            }

            const contactPayload = response.data;
            let contactsArray = [];
            if (Array.isArray(contactPayload)) {
                contactsArray = contactPayload;
            } else if (Array.isArray(contactPayload?.contacts)) {
                contactsArray = contactPayload.contacts;
            }
            
            if (!type) {
                nameSelect.append('<option value="">Please select a contact type first</option>');
                return;
            }
            
            const typeMap = {
                'agent': 'agent',
                'subagent': 'subagent',
                'worker': 'worker',
                'hr': 'hr',
                'hr employee': 'hr',
                'regular contact': 'contact',
                'contact': 'contact',
                'customer': 'contact'
            };
            
            const selectedTypeLower = String(type).toLowerCase();
            const normalizedType = typeMap[selectedTypeLower] || selectedTypeLower;
            
            const filteredContacts = contactsArray.filter(function(contact) {
                const sourceType = String(contact.source_type || '').toLowerCase();
                const contactType = String(contact.contact_type || '').toLowerCase();
                return sourceType === normalizedType || contactType === normalizedType;
            });
            
            if (filteredContacts.length === 0) {
                nameSelect.append(`<option value="">No ${type}s found</option>`);
                return;
            }
            
            const typeDisplay = selectedTypeLower.charAt(0).toUpperCase() + selectedTypeLower.slice(1);
            nameSelect.append(`<option value="">Select ${typeDisplay}</option>`);
            
            for (const contact of filteredContacts) {
                const optionId = contact.id || contact.source_id || '';
                const contactName = contact.name || '';
                const company = contact.company || contact.source_type || '';
                const displayText = company
                    ? `${contactName} (${company})`
                    : contactName;
                const contactJson = JSON.stringify(contact).replaceAll('"', '&quot;');
                nameSelect.append(`<option value="${optionId}" data-contact="${contactJson}">${displayText}</option>`);
            }
        },
        error: function() {
            nameSelect.empty().append('<option value="">Error loading contacts</option>');
        }
    });
}

