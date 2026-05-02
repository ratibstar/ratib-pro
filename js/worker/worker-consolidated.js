/**
 * EN: Implements frontend interaction behavior in `js/worker/worker-consolidated.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/worker/worker-consolidated.js`.
 */
// WORKER CONSOLIDATED - Single file for all worker functionality

// Convert Arabic/Persian numerals to Western; remove Arabic letters entirely
window.toEnglishString = window.toEnglishString || function(val) {
    if (val == null || val === '') return val;
    let s = String(val);
    const numMap = { '٠':'0','١':'1','٢':'2','٣':'3','٤':'4','٥':'5','٦':'6','٧':'7','٨':'8','٩':'9','۰':'0','۱':'1','۲':'2','۳':'3','۴':'4','۵':'5','۶':'6','۷':'7','۸':'8','۹':'9' };
    s = s.replace(/[٠-٩۰-۹]/g, d => numMap[d] || d);
    s = s.replace(/[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF\uFB50-\uFDFF\uFE70-\uFEFF]/g, '');
    return s.replace(/\s+/g, ' ').trim();
};
window.toWesternNumerals = window.toWesternNumerals || window.toEnglishString;

// Debug Configuration - Set to false for production (shared across all worker files)
window.DEBUG_MODE = window.DEBUG_MODE !== undefined ? window.DEBUG_MODE : false;
const debug = {
    log: (...args) => window.DEBUG_MODE && console.log('[Worker-Consolidated]', ...args),
    error: (...args) => window.DEBUG_MODE && console.error('[Worker-Consolidated]', ...args),
    warn: (...args) => window.DEBUG_MODE && console.warn('[Worker-Consolidated]', ...args),
    info: (...args) => window.DEBUG_MODE && console.info('[Worker-Consolidated]', ...args)
};

// Simple Notification System for success/error messages
function showNotification(message, type = 'success') {
    // Remove any existing notifications first
    const existingNotifications = document.querySelectorAll('.worker-notification');
    existingNotifications.forEach(notif => notif.remove());
    
    const notification = document.createElement('div');
    notification.className = `worker-notification worker-notification-${type}`;
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    const colors = {
        success: '#43e97b',
        error: '#f5576c',
        warning: '#fee140',
        info: '#4facfe'
    };
    
    notification.innerHTML = `
        <div class="worker-notification-content">
            <i class="fas ${icons[type] || icons.info}"></i>
            <span>${message}</span>
        </div>
    `;
    
    // Styles are now in worker-table-styles.css - no need to create inline styles
    
    document.body.appendChild(notification);
    
    // Show notification
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Auto-hide after duration
    const duration = type === 'error' ? 6000 : 4000;
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, duration);
}

// Simple Alert System - Only 2 buttons: Cancel and Save
class SimpleAlert {
    static show(title, message, type = 'info', options = {}) {
        return new Promise((resolve) => {
            // Remove any existing alerts first
            const existingAlerts = document.querySelectorAll('.simple-alert-overlay');
            existingAlerts.forEach(alert => alert.remove());
            
            // Check if this is a notification (non-interactive)
            // If notification is explicitly set in options, use that; otherwise default based on type
            const isNotification = options.hasOwnProperty('notification') 
                ? options.notification 
                : (type === 'success' || type === 'danger' || type === 'warning');
            const showCancel = options.showCancel !== false && !isNotification;
            const showSave = options.showSave !== false && !isNotification;
            const cancelText = options.cancelText || 'Cancel';
            const saveText = options.saveText || (isNotification ? 'OK' : 'Save');
            
            const overlay = document.createElement('div');
            overlay.className = `simple-alert-overlay ${type}`;
            
            let actionsHTML = '';
            if (isNotification) {
                // For notifications, just show OK button
                actionsHTML = `
                    <div class="simple-alert-actions">
                        <button class="simple-alert-btn ok" data-action="ok">${saveText}</button>
                    </div>
                `;
            } else {
                // For confirmations, show Cancel and Save buttons
                actionsHTML = `
                    <div class="simple-alert-actions">
                        ${showCancel ? `<button class="simple-alert-btn cancel" data-action="cancel">${cancelText}</button>` : ''}
                        ${showSave ? `<button class="simple-alert-btn save" data-action="save">${saveText}</button>` : ''}
                    </div>
                `;
            }
            
            overlay.innerHTML = `
                <div class="simple-alert-container">
                    <div class="simple-alert-header">
                        <h3 class="simple-alert-title">${title}</h3>
                    </div>
                    <div class="simple-alert-body">
                        <p class="simple-alert-message">${message}</p>
                        ${actionsHTML}
                    </div>
                </div>
            `;
            
            document.body.appendChild(overlay);
            
            setTimeout(() => {
                overlay.classList.add('show');
            }, 10);
            
            // Only add event listener once
            const handleClick = (e) => {
                if (e.target.classList.contains('simple-alert-btn')) {
                    const action = e.target.getAttribute('data-action');
                    this.close(overlay);
                    // For notifications, always resolve true
                    // For confirmations, resolve true only if save/ok clicked
                    resolve(isNotification || action === 'save' || action === 'ok');
                    // Remove event listener after use
                    overlay.removeEventListener('click', handleClick);
                }
            };
            
            overlay.addEventListener('click', handleClick);
            
            // Auto-close notifications after 3 seconds
            if (isNotification && options.autoClose !== false) {
                setTimeout(() => {
                    if (overlay.parentNode) {
                        this.close(overlay);
                        resolve(true);
                    }
                }, options.autoCloseDelay || 3000);
            }
        });
    }
    
    static close(overlay) {
        overlay.classList.remove('show');
        setTimeout(() => {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }, 300);
    }
}

// Alert Functions
window.showSaveAlert = function() {
    return SimpleAlert.show('Save Worker', 'Are you sure you want to save this worker?', 'info');
};

window.showCloseAlert = function() {
    const form = document.getElementById('workerForm');
    
    // Check 1: Form must exist
    if (!form) {
        debug.log('[showCloseAlert] No form found, closing without alert');
        return Promise.resolve(true);
    }
    
    // Check 2: Must have originalValues (user interacted and we stored baseline)
    if (!form.dataset.originalValues) {
        debug.log('[showCloseAlert] No originalValues stored, closing without alert');
        return Promise.resolve(true);
    }
    
    // Check 3: Verify there are actual changes (not just form population)
    try {
        const originalValues = JSON.parse(form.dataset.originalValues);
        let hasRealChanges = false;
        const inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], select, textarea');
        for (const input of inputs) {
            const key = input.name || input.id;
            if (!key || key === 'csrf_token') continue; // Skip csrf_token
            const currentValue = input.value || '';
            const originalValue = originalValues[key] || '';
            // Only count as change if current value is different from original
            if (currentValue !== originalValue) {
                hasRealChanges = true;
                break;
            }
        }
        
        if (!hasRealChanges) {
            debug.log('[showCloseAlert] No real changes detected, closing without alert');
            return Promise.resolve(true);
        }
    } catch (e) {
        debug.log('[showCloseAlert] Error checking changes, closing without alert');
        return Promise.resolve(true);
    }
    
    // ALL checks passed - user has interacted AND made changes - show alert
    debug.log('[showCloseAlert] All checks passed, showing alert');
    return SimpleAlert.show('Close Form', 'Are you sure you want to close the form?', 'warning');
};

// Initialize base paths from PHP config
(function() {
    const configEl = document.getElementById('app-config');
    if (configEl) {
        const basePath = configEl.getAttribute('data-base-path') || '';
        window.BASE_PATH = basePath;
        window.API_BASE = basePath + '/api';
        window.WORKERS_API = basePath + '/api/workers';
    } else {
        // Fallback if config element not found - use APP_CONFIG if available
        const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '';
        window.BASE_PATH = baseUrl;
        window.API_BASE = baseUrl + '/api';
        window.WORKERS_API = baseUrl + '/api/workers';
    }
})();

// Worker Table Class
class WorkerTable {
    constructor() {
        this.baseUrl = (window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers');
        this.state = {
            currentPage: 1,
            itemsPerPage: 10,
            searchTerm: '',
            statusFilter: '',
            selectedWorkers: new Set()
        };
        
        // Store pagination data for validation
        this.paginationData = {
            total: 0,
            page: 1,
            limit: 10,
            total_pages: 0
        };
        
        // Track if user has actually interacted with the form
        this.userHasInteracted = false;
        
        // Add a delay before allowing alert checks (give form time to fully load)
        this.formReadyForAlertCheck = false;
        setTimeout(() => {
            this.formReadyForAlertCheck = true;
        }, 2000); // Wait 2 seconds after initialization before allowing alert checks
        
        this.init();
    }

    init() {
        this.purgeIndonesiaOnlyDomIfNeeded();
        // Ensure default is 10 before initialization
        if (this.state.itemsPerPage !== 10) {
            this.state.itemsPerPage = 10;
        }
        if (this.paginationData.limit !== 10) {
            this.paginationData.limit = 10;
        }
        
        // Initialize page size selectors
        this.initializePageSizeSelectors();
        
        // Initialize status filter
        this.initializeStatusFilter();
        
        // Add event listeners
        this.addEventListeners();
        
        // Add pagination event listeners (only once)
        this.addPaginationEventListeners();
        
        // Initialize status indicators
        this.initStatusIndicators();
        
        // Apply initial table scrolling
        this.applyTableScrolling();
        
        // Load initial data
        this.loadWorkers();
        this.loadStats();
    }
    
    initStatusIndicators() {
        // Add click handler for status indicators - use event delegation
        document.addEventListener('click', (e) => {
            // Check if clicking on status-wrapper or its children
            const statusWrapper = e.target.closest('.status-wrapper');
            if (statusWrapper) {
                // Check if it's disabled (view mode)
                if (statusWrapper.classList.contains('view-mode-disabled') || 
                    statusWrapper.closest('#workerFormContainer.view-mode')) {
                    return; // Don't handle clicks in view mode
                }
                
                e.preventDefault();
                e.stopPropagation();
                const docType = statusWrapper.getAttribute('data-doc-type');
                if (docType) {
                    debug.log('Status wrapper clicked:', docType);
                    this.cycleStatus(statusWrapper, docType);
                }
            }
        }, true); // Use capture phase to ensure it fires
    }
    
    getStatusText(status) {
        const statusMap = {
            'pending': 'pending',
            'ok': 'ok',
            'not_ok': 'not ok'
        };
        return statusMap[status] || 'pending';
    }
    
    cycleStatus(wrapper, docType) {
        const indicator = wrapper.querySelector('.status-indicator');
        const text = wrapper.querySelector('.status-text');
        
        if (!indicator || !text) return;
        
        // Get current status
        const currentStatus = indicator.classList.contains('status-pending') ? 'pending' :
                             indicator.classList.contains('status-ok') ? 'ok' :
                             indicator.classList.contains('status-not_ok') ? 'not_ok' : 'pending';
        
        // Cycle: pending -> ok -> not_ok -> pending
        let nextStatus;
        if (currentStatus === 'pending') {
            nextStatus = 'ok';
        } else if (currentStatus === 'ok') {
            nextStatus = 'not_ok';
        } else {
            nextStatus = 'pending';
        }
        
        // Update display
        this.updateStatusDisplay(indicator, text, nextStatus);
        
        // Store status in form field (for form submission)
        const statusInput = document.querySelector(`input[name="${docType}_status"]`);
        if (statusInput) {
            statusInput.value = nextStatus;
        } else {
            // Create hidden input if it doesn't exist
            const form = document.getElementById('workerForm');
            if (form) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = `${docType}_status`;
                hiddenInput.value = nextStatus;
                form.appendChild(hiddenInput);
            }
        }
        
        // Update table immediately if worker is visible
        const workerIdInput = document.querySelector('input[name="id"]');
        if (workerIdInput && workerIdInput.value) {
            const workerId = workerIdInput.value;
            // Only update table for police, medical, visa, ticket (not identity, passport)
            if (['police', 'medical', 'visa', 'ticket', 'training_certificate'].includes(docType)) {
                this.updateDocumentStatusInTable(workerId, docType, nextStatus);
            }
        }
    }
    
    updateStatusDisplay(indicator, text, status) {
        // Remove all status classes
        indicator.classList.remove('status-pending', 'status-ok', 'status-not_ok');
        text.classList.remove('status-pending', 'status-ok', 'status-not_ok');
        
        // Add new status class
        indicator.classList.add(`status-${status}`);
        text.classList.add(`status-${status}`);
        
        // Update text content
        text.textContent = this.getStatusText(status);
    }

    initializePageSizeSelectors() {
        const topSelector = document.getElementById('topPageSize');
        const bottomSelector = document.getElementById('bottomPageSize');
        
        if (topSelector) topSelector.value = this.state.itemsPerPage;
        if (bottomSelector) bottomSelector.value = this.state.itemsPerPage;
        
        // SYNC BOTH DROPDOWNS - When one changes, update the other
        if (topSelector && bottomSelector) {
            topSelector.addEventListener('change', (e) => {
                bottomSelector.value = e.target.value; // Sync bottom
                this.handleItemsPerPage(e);
            });
            
            bottomSelector.addEventListener('change', (e) => {
                topSelector.value = e.target.value; // Sync top
                this.handleItemsPerPage(e);
            });
        }
    }

    initializeStatusFilter() {
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            // Set initial value to empty (All Status)
            statusFilter.value = this.state.statusFilter || '';
            debug.log('Status filter initialized with value:', statusFilter.value);
        }
    }

    addEventListeners() {
        // Bulk action buttons
        const bulkActivateBtn = document.getElementById('bulkActivateBtn');
        const bulkDeactivateBtn = document.getElementById('bulkDeactivateBtn');
        const bulkPendingBtn = document.getElementById('bulkPendingBtn');
        const bulkSuspendedBtn = document.getElementById('bulkSuspendedBtn');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        
        if (bulkActivateBtn) {
            bulkActivateBtn.addEventListener('click', () => this.handleBulkAction('activate'));
        }
        if (bulkDeactivateBtn) {
            bulkDeactivateBtn.addEventListener('click', () => this.handleBulkAction('deactivate'));
        }
        if (bulkPendingBtn) {
            bulkPendingBtn.addEventListener('click', () => this.handleBulkAction('pending'));
        }
        if (bulkSuspendedBtn) {
            bulkSuspendedBtn.addEventListener('click', () => this.handleBulkAction('suspended'));
        }
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', () => this.handleBulkAction('delete'));
        }

        // Select all checkbox
        const selectAllCheckbox = document.getElementById('selectAll');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                const checkboxes = document.querySelectorAll('.worker-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = e.target.checked;
                    if (e.target.checked) {
                        this.state.selectedWorkers.add(checkbox.value);
                    } else {
                        this.state.selectedWorkers.delete(checkbox.value);
                    }
                });
                this.updateBulkActionButtons();
            });
        }

        // Individual checkboxes - use event delegation for dynamic content
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('worker-checkbox')) {
                if (e.target.checked) {
                    this.state.selectedWorkers.add(e.target.value);
                } else {
                    this.state.selectedWorkers.delete(e.target.value);
                }
                this.updateBulkActionButtons();
            }
        });

        // Add New Worker button
        const addNewWorkerBtn = document.getElementById('addNewBtn');
        if (addNewWorkerBtn) {
            addNewWorkerBtn.addEventListener('click', () => {
                if (window.openAddWorkerForm) {
                    window.openAddWorkerForm();
                } else {
                    this.showAddWorkerForm();
                }
            });
        }

        const aiWorkflowBtn = document.getElementById('aiWorkflowBtn');
        if (aiWorkflowBtn) {
            aiWorkflowBtn.addEventListener('click', () => {
                const form = document.getElementById('workerForm');
                const prefill = {
                    identityNumber: (form?.querySelector('[name="identity_number"]')?.value || '').trim(),
                    passportNumber: (form?.querySelector('[name="passport_number"]')?.value || '').trim(),
                    notifyTo: (form?.querySelector('[name="email"]')?.value || '').trim()
                };
                if (window.GlobalAIAction && typeof window.GlobalAIAction.open === 'function') {
                    window.GlobalAIAction.open(prefill);
                } else {
                    showNotification('Global AI module is not available.', 'error');
                }
            });
        }

        // Page size selectors
        const topPageSize = document.getElementById('topPageSize');
        const bottomPageSize = document.getElementById('bottomPageSize');
        
        if (topPageSize) {
            topPageSize.addEventListener('change', (e) => this.handleItemsPerPage(e));
        }
        if (bottomPageSize) {
            bottomPageSize.addEventListener('change', (e) => this.handleItemsPerPage(e));
        }

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.handleSearch(e.target.value));
        }

        // Status filter
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => this.handleStatusFilter(e.target.value));
        }

        // Document status click handlers - use event delegation
        document.addEventListener('click', (e) => {
            // Skip if clicking on modal close buttons to prevent interference
            if (e.target.closest('.close-btn') || e.target.closest('#closeDocumentsModal')) {
                return;
            }
            
            if (e.target.closest('.document-status-icon')) {
                const icon = e.target.closest('.document-status-icon');
                const row = icon.closest('tr');
                if (!row) return;
                const workerId = row.getAttribute('data-id');
                const documentType = this.getDocumentTypeFromColumn(icon);
                this.showDocumentStatusModal(workerId, documentType, icon.getAttribute('data-status'), icon);
            }
        });

        // Form close button
        const closeBtn = document.querySelector('#workerFormContainer .close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.hideAddWorkerForm());
        }

        // Form overlay click to close
        const formOverlay = document.querySelector('#workerFormContainer .form-overlay');
        if (formOverlay) {
            formOverlay.addEventListener('click', () => this.hideAddWorkerForm());
        }
        
        // Track user interactions on the form - ONLY for actual user input (not programmatic)
        const workerForm = document.getElementById('workerForm');
        if (workerForm) {
            workerForm.addEventListener('input', (e) => {
                // Only count as interaction if it's a real user event (not programmatic)
                if (e.isTrusted === true) {
                    this.userHasInteracted = true;
                    // Store original values on first interaction
                    if (!workerForm.dataset.originalValues) {
                        const originalValues = {};
                        const inputs = workerForm.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], select, textarea');
                        inputs.forEach(input => {
                            const key = input.name || input.id;
                            if (key) {
                                originalValues[key] = input.value || '';
                            }
                        });
                        workerForm.dataset.originalValues = JSON.stringify(originalValues);
                    }
                }
            }, true); // Use capture phase
            
            workerForm.addEventListener('change', (e) => {
                // Only count as interaction if it's a real user event (not programmatic)
                if (e.isTrusted === true) {
                    this.userHasInteracted = true;
                    // Store original values on first interaction
                    if (!workerForm.dataset.originalValues) {
                        const originalValues = {};
                        const inputs = workerForm.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], select, textarea');
                        inputs.forEach(input => {
                            const key = input.name || input.id;
                            if (key) {
                                originalValues[key] = input.value || '';
                            }
                        });
                        workerForm.dataset.originalValues = JSON.stringify(originalValues);
                    }
                }
            }, true); // Use capture phase
            
            // Also listen for keydown to catch typing
            workerForm.addEventListener('keydown', (e) => {
                if (e.isTrusted === true && !e.ctrlKey && !e.metaKey && !e.altKey) {
                    // User is typing (not shortcuts)
                    if (e.key.length === 1 || ['Backspace', 'Delete', 'Enter'].includes(e.key)) {
                        this.userHasInteracted = true;
                    }
                }
            }, true);
        }
    }

    getDocumentTypeFromColumn(icon) {
        const cell = icon.closest('td');
        const columnIndex = Array.from(cell.parentElement.children).indexOf(cell);
        const hasTrainingColumn = this.isIndonesiaProgramContext();
        if (!hasTrainingColumn) {
            switch (columnIndex) {
                case 4: return 'police';
                case 5: return 'medical';
                case 6: return 'visa';
                case 7: return 'ticket';
                default: return 'police';
            }
        }

        switch (columnIndex) {
            case 4: return 'training_certificate';
            case 5: return 'police';
            case 6: return 'medical';
            case 7: return 'visa';
            case 8: return 'ticket';
            default: return 'police';
        }
    }

    showDocumentStatusModal(workerId, documentType, currentStatus, iconElement) {
        const statuses = ['ok', 'not_ok', 'pending'];
        const statusLabels = {
            'ok': 'OK',
            'not_ok': 'NOT OK',
            'pending': 'PENDING'
        };
        
        let statusOptions = '';
        statuses.forEach(status => {
            const selected = status === currentStatus ? 'selected' : '';
            statusOptions += `<option value="${status}" ${selected}>${statusLabels[status]}</option>`;
        });
        
        const modal = `
            <div class="document-status-modal">
                <h3>Update ${documentType.toUpperCase()} Status</h3>
                <select id="newStatus">
                    ${statusOptions}
                </select>
                <div class="modal-actions">
                    <button class="btn-cancel" data-action="close">Cancel</button>
                    <button class="btn-update" data-action="update" data-worker-id="${workerId}" data-document-type="${documentType}">Update</button>
                </div>
            </div>
        `;
        
        // Remove any existing modal
        const existingModal = document.querySelector('.document-status-modal');
        if (existingModal) existingModal.remove();
        
        document.body.insertAdjacentHTML('beforeend', modal);
        
        // Position modal under the icon
        const modalElement = document.querySelector('.document-status-modal');
        if (iconElement) {
            const iconRect = iconElement.getBoundingClientRect();
            const modalRect = modalElement.getBoundingClientRect();
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
            
            // Position below the icon, centered horizontally
            const top = iconRect.bottom + scrollTop + 8; // 8px gap below icon
            const left = iconRect.left + scrollLeft + (iconRect.width / 2) - (modalRect.width / 2);
            
            // Ensure modal doesn't go off screen
            const maxLeft = window.innerWidth - modalRect.width - 10;
            const finalLeft = Math.max(10, Math.min(left, maxLeft));
            
            modalElement.style.setProperty('--modal-top', `${top}px`);
            modalElement.style.setProperty('--modal-left', `${finalLeft}px`);
        }
        
        // Add event handlers for modal buttons
        const cancelBtn = modalElement.querySelector('.btn-cancel');
        const updateBtn = modalElement.querySelector('.btn-update');
        
        cancelBtn.addEventListener('click', () => {
            modalElement.remove();
        });
        
        updateBtn.addEventListener('click', () => {
            const workerId = updateBtn.getAttribute('data-worker-id');
            const documentType = updateBtn.getAttribute('data-document-type');
            this.updateDocumentStatusFromModal(workerId, documentType);
        });
        
        // Close modal when clicking outside
        setTimeout(() => {
            const closeOnOutsideClick = (e) => {
                if (!modalElement.contains(e.target) && !iconElement.contains(e.target)) {
                    modalElement.remove();
                    document.removeEventListener('click', closeOnOutsideClick);
                }
            };
            document.addEventListener('click', closeOnOutsideClick);
        }, 100);
    }

    async updateDocumentStatusFromModal(workerId, documentType) {
        const modal = document.querySelector('.document-status-modal');
        const statusSelect = modal.querySelector('#newStatus');
        const newStatus = statusSelect.value;
        
        await this.updateDocumentStatus(workerId, documentType, newStatus);
        modal.remove();
    }

    async loadWorkers() {
        try {
            this.setLoading(true);
            
            // Check dropdown value FIRST - it's the source of truth
            const topSelector = document.getElementById('topPageSize');
            let limit = this.state.itemsPerPage || 10;
            
            if (topSelector) {
                const dropdownValue = parseInt(topSelector.value);
                if (dropdownValue && dropdownValue !== limit) {
                    limit = dropdownValue;
                    this.state.itemsPerPage = dropdownValue;
                }
            }
            
            // Ensure minimum of 10 if dropdown says 10
            if (topSelector && topSelector.value === '10' && limit !== 10) {
                limit = 10;
                this.state.itemsPerPage = 10;
            }
            
            const params = new URLSearchParams({
                page: this.state.currentPage,
                limit: limit,
                search: this.state.searchTerm,
                status: this.state.statusFilter,
                _t: Date.now()
            });

            const apiUrl = `${this.baseUrl}/core/get.php?${params}`;
            debug.log('Loading workers with params:', {
                page: this.state.currentPage,
                limit: this.state.itemsPerPage,
                search: this.state.searchTerm,
                status: this.state.statusFilter,
                apiUrl: apiUrl
            });
            
            const response = await fetch(apiUrl);
            const responseText = await response.text();
            
            if (!responseText.trim()) {
                throw new Error('Empty response from API');
            }
            
            const data = JSON.parse(responseText);
            debug.log('API Response:', {
                success: data.success,
                total: data.data?.pagination?.total || 0,
                workers: data.data?.workers?.length || 0,
                statusFilter: this.state.statusFilter
            });

            if (data.success) {
                // Verify we got the expected number of workers
                const workers = data.data?.workers || [];
                const expectedLimit = limit;
                const actualCount = workers.length;
                
                // CRITICAL FIX: If we requested 10 but got fewer, retry with explicit limit=10
                if (expectedLimit === 10 && actualCount < 10 && data.data?.pagination?.total >= 10) {
                    const retryParams = new URLSearchParams({
                        page: this.state.currentPage,
                        limit: '10',
                        search: this.state.searchTerm,
                        status: this.state.statusFilter,
                        _t: Date.now() + 1
                    });
                    const retryUrl = `${this.baseUrl}/core/get.php?${retryParams}`;
                    try {
                        const retryResponse = await fetch(retryUrl);
                        const retryText = await retryResponse.text();
                        const retryData = JSON.parse(retryText);
                        if (retryData.success && retryData.data?.workers) {
                            const retryWorkers = retryData.data.workers;
                            if (retryWorkers.length >= 10 || retryWorkers.length > actualCount) {
                                this.paginationData = retryData.data.pagination;
                                this.paginationData.limit = 10;
                                this.state.itemsPerPage = 10;
                                this.renderTable(retryWorkers);
                                this.renderPagination(this.paginationData);
                                this.loadStats();
                                return;
                            }
                        }
                    } catch (e) {
                        // Continue with original data
                    }
                }
                
                // Store pagination data
                this.paginationData = data.data.pagination;
                
                // Force limit to 10 if we requested 10
                if (limit === 10) {
                    this.paginationData.limit = 10;
                    this.state.itemsPerPage = 10;
                }
                
                // Render ALL workers
                this.renderTable(workers);
                this.renderPagination(this.paginationData);
                this.loadStats();
            } else {
                throw new Error(data.message || 'API returned success=false');
            }
        } catch (error) {
('Failed to load workers:', error);
            // Show user-friendly error message
            const tbody = document.getElementById('workerTableBody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="${this.isIndonesiaProgramContext() ? 14 : 13}" class="text-center error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            Failed to load workers. Please try again later.
                            <br><small>Error: ${error.message}</small>
                        </td>
                    </tr>
                `;
            }
        } finally {
            this.setLoading(false);
        }
    }

    // Helper method to get clean contact number
    getCleanContactNumber(worker) {
        const contactNumber = worker.contact_number || worker.phone || worker.identity_number || worker.phone_number;
        
        
        // If it's a valid number (not agent name or random text), return it
        if (contactNumber && /^[\d\-\+\(\)\s]+$/.test(contactNumber)) {
            return contactNumber;
        }
        
        // If it looks like agent name or random text, return dash
        return '-';
    }

    // Helper method to get clean passport number
    getCleanPassportNumber(worker) {
        const passportNumber = worker.passport_number;
        
        // If it's a valid passport number (alphanumeric), return it
        if (passportNumber && /^[A-Za-z0-9\-\s]+$/.test(passportNumber)) {
            return passportNumber;
        }
        
        // Return dash for missing passport number instead of fake placeholder
        return '-';
    }

    isIndonesiaProgramContext() {
        if (typeof window.RATIB_COUNTRY_PROFILE === 'string' && window.RATIB_COUNTRY_PROFILE.trim()) {
            return window.RATIB_COUNTRY_PROFILE.trim().toLowerCase() === 'indonesia';
        }
        if (typeof window.RATIB_IS_INDONESIA_PROGRAM === 'boolean') {
            return window.RATIB_IS_INDONESIA_PROGRAM;
        }
        const config = document.getElementById('app-config');
        const text = [
            config?.getAttribute('data-country-name'),
            config?.getAttribute('data-country-code')
        ].map(value => String(value || '').toLowerCase()).join(' ');
        return text.includes('indonesia') || text.includes('indonesian') || /\bidn?\b/.test(text);
    }

    purgeIndonesiaOnlyDomIfNeeded() {
        if (this.isIndonesiaProgramContext()) {
            return;
        }
        document.body.classList.remove('indonesia-table-visible', 'indonesia-compliance-visible');
        document.querySelectorAll(
            '.indonesia-compliance-card, ' +
            '#workerFormContainer .indonesia-compliance-field, ' +
            '#documentsModal .indonesia-compliance-field, ' +
            '.mobile-worker-cards .indonesia-compliance-field, ' +
            'option[value="contract_signed"], ' +
            'option[value="insurance"], ' +
            'option[value="exit_permit"], ' +
            'option[value="training_certificate"]'
        ).forEach(el => el.remove());
        document.querySelectorAll('.worker-table th.indonesia-only-column, .worker-table td.indonesia-only-column')
            .forEach(el => el.remove());
    }

    // Normalize status values for consistent display
    normalizeStatus(status) {
        if (!status) return 'pending';
        
        // Map different status values to standard ones
        switch (status.toLowerCase()) {
            case 'ok':
            case 'approved':
            case 'valid':
            case 'passed':
            case 'completed':
            case 'issued':
            case 'signed':
                return 'ok';
            case 'not_ok':
            case 'rejected':
            case 'invalid':
            case 'failed':
                return 'not_ok';
            case 'pending':
            case 'waiting':
            case 'processing':
            default:
                return 'pending';
        }
    }

    // Get human-readable status text
    getStatusText(status) {
        const normalized = this.normalizeStatus(status);
        switch (normalized) {
            case 'ok':
                return 'OK';
            case 'not_ok':
                return 'NOT OK';
            case 'pending':
            default:
                return 'PENDING';
        }
    }

    // Add event delegation for action buttons
    addActionButtonListeners() {
        const tbody = document.getElementById('workerTableBody');
        if (!tbody) return;

        tbody.addEventListener('click', (e) => {
            const button = e.target.closest('button[data-action]');
            if (!button) return;

            const action = button.getAttribute('data-action');
            const workerId = button.getAttribute('data-worker-id');

            switch (action) {
                case 'edit':
                    if (window.openEditWorkerForm) {
                        window.openEditWorkerForm(workerId);
                    } else {
                        window.editWorker(workerId);
                    }
                    break;
                case 'view':
                    if (window.openViewWorkerForm) {
                        window.openViewWorkerForm(workerId);
                    } else if (window.openEditWorkerForm) {
                        window.openEditWorkerForm(workerId, true);
                    } else {
                        window.editWorker(workerId);
                    }
                    break;
                case 'delete':
                    window.deleteWorker(workerId);
                    break;
                case 'musaned':
                    window.showMusaned(workerId);
                    break;
                case 'documents':
                    window.showDocuments(workerId);
                    break;
                case 'empty-cv':
                    window.showEmptyCv(workerId);
                    break;
                case 'deploy':
                    if (typeof window.openDeploymentModal === 'function') {
                        window.openDeploymentModal(workerId);
                    }
                    break;
            }
        });
    }

    // Helper method to get the correct CSS class for status badges
    getStatusBadgeClass(status) {
        if (!status || status === '' || status === null || status === undefined) {
            return 'pending';
        }
        
        const statusLower = String(status).toLowerCase().trim();
        debug.log('getStatusBadgeClass called with status:', status, 'normalized:', statusLower);
        
        switch (statusLower) {
            case 'active':
            case 'approved':
                debug.log('Status mapped to active');
                return 'active';
            case 'inactive':
            case 'rejected':
                debug.log('Status mapped to inactive');
                return 'inactive';
            case 'pending':
                debug.log('Status mapped to pending');
                return 'pending';
            case 'suspended':
                debug.log('Status mapped to suspended');
                return 'suspended';
            default:
                debug.log('Status mapped to pending (default) for status:', status);
                return 'pending';
        }
    }

    // Helper method to get the correct display text for status badges
    getStatusDisplayText(status) {
        if (!status || status === '' || status === null || status === undefined) {
            return 'Pend';
        }
        
        const statusLower = String(status).toLowerCase().trim();
        debug.log('getStatusDisplayText called with status:', status, 'normalized:', statusLower);
        
        switch (statusLower) {
            case 'active':
            case 'approved':
                debug.log('Status text mapped to Act');
                return 'Act';
            case 'inactive':
            case 'rejected':
                debug.log('Status text mapped to Inact');
                return 'Inact';
            case 'pending':
                debug.log('Status text mapped to Pend');
                return 'Pend';
            case 'suspended':
                debug.log('Status text mapped to Susp');
                return 'Susp';
            default:
                debug.log('Status text mapped to Pend (default) for status:', status);
                return 'Pend';
        }
    }

    renderTable(workers) {
        const tbody = document.getElementById('workerTableBody');
        
        if (!tbody) {
('Table body element not found!');
            return;
        }
        
        
        if (!workers || workers.length === 0) {
            document.body.classList.remove('indonesia-table-visible');
            this.purgeIndonesiaOnlyDomIfNeeded();
            tbody.innerHTML = `<tr><td colspan="${this.isIndonesiaProgramContext() ? 14 : 13}" class="text-center">No workers found</td></tr>`;
            this.renderMobileCards([]);
            return;
        }
        
        document.body.classList.toggle('indonesia-table-visible', this.isIndonesiaProgramContext());
        this.purgeIndonesiaOnlyDomIfNeeded();

        // Render mobile cards as well
        this.renderMobileCards(workers);

        // Workers loaded successfully
        
        const tableHTML = workers.map(worker => {
            
            // Create clean table row with correct data mapping (convert Arabic numerals/letters to English)
            const toEn = window.toEnglishString || window.toWesternNumerals || (v => v);
            const workerName = toEn((worker.worker_name || worker.full_name || '')).substring(0, 20) + (toEn(worker.worker_name || worker.full_name || '').length > 20 ? '...' : '');
            const identityNumber = toEn(this.getCleanContactNumber(worker));
            const passportNumber = toEn(this.getCleanPassportNumber(worker));
            
            // Debug logging removed for performance
            
            const trainingCell = this.isIndonesiaProgramContext() ? `
                <td class="document-icon indonesia-only-column">
                    <span class="document-status-icon" data-status="${this.normalizeStatus(worker.training_certificate_status)}" title="Training Certificate: ${this.getStatusText(worker.training_certificate_status)}">
                        <i class="fas fa-certificate"></i>
                    </span>
                </td>` : '';

            return `
            <tr data-id="${worker.id}">
                <td class="worker-id">${toEn(worker.formatted_id || worker.id)}</td>
                <td class="worker-name">${workerName}</td>
                <td class="worker-identity">${identityNumber}</td>
                <td class="worker-passport">${passportNumber}</td>
                ${trainingCell}
                <td class="document-icon">
                    <span class="document-status-icon" data-status="${this.normalizeStatus(worker.police_status)}" title="Police Clearance: ${this.getStatusText(worker.police_status)}">
                        <i class="fas fa-shield-alt"></i>
                    </span>
                </td>
                <td class="document-icon">
                    <span class="document-status-icon" data-status="${this.normalizeStatus(worker.medical_status)}" title="Medical Report: ${this.getStatusText(worker.medical_status)}">
                        <i class="fas fa-heartbeat"></i>
                    </span>
                </td>
                <td class="document-icon">
                    <span class="document-status-icon" data-status="${this.normalizeStatus(worker.visa_status)}" title="Visa: ${this.getStatusText(worker.visa_status)}">
                        <i class="fas fa-passport"></i>
                    </span>
                </td>
                <td class="document-icon">
                    <span class="document-status-icon" data-status="${this.normalizeStatus(worker.ticket_status)}" title="Ticket: ${this.getStatusText(worker.ticket_status)}">
                        <i class="fas fa-plane"></i>
                    </span>
                </td>
                <td class="agent-name">${toEn(worker.agent_name || '-').substring(0, 18)}${toEn(worker.agent_name || '').length > 18 ? '...' : ''}</td>
                <td class="subagent-name">${toEn(worker.subagent_name || '-').substring(0, 18)}${toEn(worker.subagent_name || '').length > 18 ? '...' : ''}</td>
                <td class="status-cell">
                    ${(() => {
                        const statusValue = worker.status || 'pending';
                        const badgeClass = this.getStatusBadgeClass(statusValue);
                        const displayText = this.getStatusDisplayText(statusValue);
                        debug.log('Rendering status:', { workerId: worker.id, statusValue, badgeClass, displayText });
                        return `<span class="status-badge ${badgeClass}" data-worker-status="${String(statusValue).toLowerCase().trim()}">${displayText}</span>`;
                    })()}
                </td>
                <td class="checkbox-cell">
                    <input type="checkbox" class="worker-checkbox" value="${worker.id}" title="Select worker">
                </td>
                <td class="actions-cell">
                    <div class="action-buttons-compact">
                        <button class="btn-icon-small edit-btn edit-worker" data-action="edit" data-worker-id="${worker.id}" title="Edit" data-permission="edit_worker">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon-small view-btn" data-action="view" data-worker-id="${worker.id}" title="View" data-permission="view_worker">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn-icon-small delete-btn" data-action="delete" data-worker-id="${worker.id}" title="Delete" data-permission="delete_worker">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button class="btn-icon-small musaned-btn" data-action="musaned" data-worker-id="${worker.id}" title="Musaned" data-permission="manage_musaned">
                            <i class="fas fa-user-check"></i>
                            <span>Musaned</span>
                        </button>
                        <button class="btn-icon-small docs-btn" data-action="documents" data-worker-id="${worker.id}" title="Documents" data-permission="view_worker_documents">
                            <i class="fas fa-file-alt"></i>
                        </button>
                        ${this.isIndonesiaProgramContext() ? `
                        <button class="btn-icon-small docs-btn" data-action="empty-cv" data-worker-id="${worker.id}" title="Empty CV" data-permission="view_worker_documents">
                            <i class="fas fa-file-lines"></i>
                        </button>` : ''}
                        <button class="btn-icon-small deploy-btn" data-action="deploy" data-worker-id="${worker.id}" title="Send Abroad" data-permission="edit_worker">
                            <i class="fas fa-plane-departure"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        }).join('');
        
        // Verify we're rendering all workers
        const renderedCount = workers.length;
        
        // Table HTML generated
        
        tbody.innerHTML = tableHTML;
        
        // Verify all rows are in DOM
        const actualRows = tbody.querySelectorAll('tr').length;
        if (renderedCount !== actualRows) {
            // Something went wrong with rendering
        }

        // Add event delegation for action buttons
        this.addActionButtonListeners();

        this.updateBulkActionButtons();
        
        // Apply table scrolling after rendering - use setTimeout to ensure DOM is ready
        setTimeout(() => {
            this.applyTableScrolling();
        }, 150);
        
        // Re-apply permissions after table update - wait a bit to ensure permissions are loaded
        setTimeout(() => {
            if (window.UserPermissions) {
                if (window.UserPermissions.loaded) {
                    window.UserPermissions.applyPermissions();
                } else {
                    // If permissions not loaded yet, wait for them to load
                    window.UserPermissions.load().then(() => {
                        window.UserPermissions.applyPermissions();
                    });
                }
            }
        }, 100);
    }

    updateBulkActionButtons() {
        const hasSelected = this.state.selectedWorkers.size > 0;
        const buttonIds = ['bulkActivateBtn', 'bulkDeactivateBtn', 'bulkPendingBtn', 'bulkSuspendedBtn', 'bulkDeleteBtn'];
        
        buttonIds.forEach(id => {
            const btn = document.getElementById(id);
            if (btn) {
                btn.disabled = !hasSelected;
            }
        });
        
        // Update selected count display
        const selectedCount = document.getElementById('selectedCount');
        if (selectedCount) {
            selectedCount.textContent = this.state.selectedWorkers.size;
        }
    }

    async handleBulkAction(action) {
        if (this.state.selectedWorkers.size === 0) {
            return;
        }

        const workerIds = Array.from(this.state.selectedWorkers);
        const confirmMessage = `Are you sure you want to ${action} ${workerIds.length} worker(s)?`;
        
        const confirmed = await SimpleAlert.show(
            `${action.charAt(0).toUpperCase() + action.slice(1)} Workers`, 
            confirmMessage, 
            action === 'delete' ? 'danger' : 'warning',
            action === 'delete' ? {
                notification: false,
                showCancel: true,
                cancelText: 'Cancel',
                saveText: 'Delete'
            } : {
                notification: false,
                showCancel: true,
                cancelText: 'Cancel',
                saveText: 'Confirm'
            }
        );
        
        if (!confirmed) {
            return;
        }

        try {
            debug.log(`Sending bulk ${action} request for workers:`, workerIds);
            
            // Call the actual API for bulk actions with cache busting
            const timestamp = Date.now();
            const response = await fetch(`${this.baseUrl}/bulk-${action}.php?t=${timestamp}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ 
                    worker_ids: workerIds,
                    action: action 
                })
            });
            
            debug.log(`Bulk ${action} response status:`, response.status);

            if (response.ok) {
                const responseText = await response.text();
                try {
                    const result = JSON.parse(responseText);
                    debug.log(`Bulk ${action} API response:`, result);
                    
                    if (result.success) {
                        // Refresh history if UnifiedHistory modal is open
                        if (window.unifiedHistory) {
                            await window.unifiedHistory.refreshIfOpen();
                        }
                        
                        debug.log(`Successfully ${action}d ${result.data?.affected_rows || result.data?.deleted_count || workerIds.length} worker(s)`);
                        if (result.data?.verification) {
                            debug.log(`Verification data:`, result.data.verification);
                        }
                        
                        // Update visual state immediately for better UX
                        if (action === 'activate' || action === 'deactivate' || action === 'pending' || action === 'suspended') {
                            let newStatus, newStatusClass, newStatusText;
                            
                            switch (action) {
                                case 'activate':
                                    newStatus = 'active';
                                    newStatusClass = 'active';
                                    newStatusText = 'Act';
                                    break;
                                case 'deactivate':
                                    newStatus = 'inactive';
                                    newStatusClass = 'inactive';
                                    newStatusText = 'Inact';
                                    break;
                                case 'pending':
                                    newStatus = 'pending';
                                    newStatusClass = 'pending';
                                    newStatusText = 'Pend';
                                    break;
                                case 'suspended':
                                    newStatus = 'suspended';
                                    newStatusClass = 'suspended';
                                    newStatusText = 'Susp';
                                    break;
                            }
                            
                            workerIds.forEach(workerId => {
                                // Update table row
                                const row = document.querySelector(`tr[data-id="${workerId}"]`);
                                if (row) {
                                    const statusCell = row.querySelector('.status-badge');
                                    if (statusCell) {
                                        statusCell.className = `status-badge ${newStatusClass}`;
                                        statusCell.textContent = newStatusText;
                                    }
                                }
                                
                                // Update mobile card
                                const card = document.querySelector(`.worker-card[data-id="${workerId}"]`);
                                if (card) {
                                    const statusElement = card.querySelector('.worker-card-status');
                                    if (statusElement) {
                                        statusElement.className = `worker-card-status status-${newStatusClass}`;
                                        statusElement.textContent = newStatusText.toUpperCase();
                                    }
                                }
                            });
                        } else if (action === 'delete') {
                            workerIds.forEach(workerId => {
                                // Remove table row
                                const row = document.querySelector(`tr[data-id="${workerId}"]`);
                                if (row) {
                                    row.remove();
                                }
                                
                                // Remove mobile card
                                const card = document.querySelector(`.worker-card[data-id="${workerId}"]`);
                                if (card) {
                                    card.remove();
                                }
                            });
                        }
                    } else {
                        debug.error(`Failed to ${action} workers:`, result.message);
                        SimpleAlert.show('Error', `Failed to ${action} workers: ${result.message}`, 'danger');
                    }
                } catch (parseError) {
                    debug.error('Invalid JSON response:', responseText);
                    debug.error(`Bulk ${action} API returned invalid JSON. Processing locally...`);
                    // Fall through to local processing
                }
            } else {
                debug.warn(`Bulk ${action} API returned status ${response.status}. Processing locally...`);
                
                // Process locally if API fails
                if (action === 'activate' || action === 'deactivate') {
                    const newStatus = action === 'activate' ? 'approved' : 'pending';
                    debug.log(`Processing ${action} locally for ${workerIds.length} worker(s)`);
                    
                    // Update the table rows to show the new status
                    workerIds.forEach(workerId => {
                        // Update table row
                        const row = document.querySelector(`tr[data-id="${workerId}"]`);
                        if (row) {
                            const statusCell = row.querySelector('.status-badge');
                            if (statusCell) {
                                statusCell.className = `status-badge ${newStatus === 'approved' ? 'active' : 'inactive'}`;
                                statusCell.textContent = newStatus === 'approved' ? 'Act' : 'Inact';
                            }
                        }
                        
                        // Update mobile card
                        const card = document.querySelector(`.worker-card[data-id="${workerId}"]`);
                        if (card) {
                            const statusElement = card.querySelector('.worker-card-status');
                            if (statusElement) {
                            statusElement.className = `worker-card-status status-${newStatus === 'approved' ? 'active' : 'inactive'}`;
                            statusElement.textContent = newStatus === 'approved' ? 'ACT' : 'INACT';
                            }
                        }
                    });
                } else if (action === 'delete') {
                    debug.log(`Processing ${action} locally for ${workerIds.length} worker(s)`);
                    
                    // Remove the rows from the table and mobile cards
                    workerIds.forEach(workerId => {
                        // Remove table row
                        const row = document.querySelector(`tr[data-id="${workerId}"]`);
                        if (row) {
                            row.remove();
                        }
                        
                        // Remove mobile card
                        const card = document.querySelector(`.worker-card[data-id="${workerId}"]`);
                        if (card) {
                            card.remove();
                        }
                    });
                }
                debug.log(`Bulk ${action} completed for ${workerIds.length} worker(s)`);
            }
            
            // Clear selections and refresh
            this.state.selectedWorkers.clear();
            this.updateBulkActionButtons();
            
            // Immediate refresh for better responsiveness
            this.loadWorkers(); // Refresh table
            this.loadStats(); // Refresh stats cards
            
        } catch (error) {
            debug.error(`Error in bulk ${action}:`, error);
            SimpleAlert.show('Error', `Error performing bulk ${action}: ${error.message}`, 'danger');
            
            // Still clear selections even if there was an error
            this.state.selectedWorkers.clear();
            this.updateBulkActionButtons();
            this.loadWorkers();
            this.loadStats();
        }

    }

    handleSearch(searchTerm) {
        this.state.searchTerm = searchTerm;
        this.state.currentPage = 1;
        this.loadWorkers();
    }

    handleStatusFilter(status) {
        debug.log('Status filter changed to:', status);
        this.state.statusFilter = status;
        this.state.currentPage = 1;
        this.loadWorkers();
    }

    handleItemsPerPage(e) {
        const newItemsPerPage = parseInt(e.target.value);
        this.state.itemsPerPage = newItemsPerPage;
        
        // Reset to page 1 when changing page size
        this.state.currentPage = 1;
        
        // Apply scrolling immediately before loading
        this.applyTableScrolling();
        
        // Load workers with new page size (scrolling will be applied again after render)
        this.loadWorkers();
    }
    
    goToPage(page) {
        // Use stored pagination data for validation
        const maxPage = this.paginationData.total_pages || Math.ceil(this.paginationData.total / this.state.itemsPerPage);
        
debug.log(`Attempting to go to page ${page}. Valid range: 1-${maxPage}`);
('Current pagination data:', this.paginationData);
        
        // Validate page number
        if (page >= 1 && page <= maxPage) {
            this.state.currentPage = page;
debug.log(`✅ Valid page number. Loading page ${page}...`);
            this.loadWorkers();
        } else {
debug.log(`❌ Invalid page number: ${page}. Valid range: 1-${maxPage}`);
        }
    }
    
    applyTableScrolling() {
        const tableWrapper = document.querySelector('.table-wrapper');
        
        if (!tableWrapper) {
            setTimeout(() => this.applyTableScrolling(), 100);
            return;
        }
        
        // Get items per page as number
        const itemsPerPage = parseInt(this.state.itemsPerPage) || 10;
        
        // Remove all classes first
        tableWrapper.classList.remove('scrollable', 'rows-5', 'rows-10', 'rows-25', 'rows-50', 'rows-100');
        
        if (itemsPerPage === 5 || itemsPerPage === 10) {
            // 5 or 10 entries: show all, no scrolling
            tableWrapper.classList.add(`rows-${itemsPerPage}`);
        } else {
            // 25, 50, 100 entries: scroll inside fixed 10-row height (450px)
            tableWrapper.classList.add('scrollable', `rows-${itemsPerPage}`);
        }
    }

    async loadStats() {
        try {
            const response = await fetch(`${this.baseUrl}/stats.php`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();

            if (data.success) {
                this.updateStatsDisplay(data.data);
            } else {
                throw new Error(data.message || 'API returned success=false');
            }
        } catch (error) {
            debug.error('Failed to load stats:', error);
            // Show error but don't use fake data - let user see the issue
            this.updateStatsDisplay({
                total: 0,
                active: 0,
                inactive: 0,
                documents: {
                    police: 0,
                    medical: 0,
                    visa: 0,
                    ticket: 0
                },
                indonesia_compliance: {
                    ready_to_deploy: 0,
                    waiting_medical: 0,
                    waiting_approval: 0,
                    blocked_workers: 0
                }
            });
        }
    }

    updateStatsDisplay(stats) {
        
        const elements = {
            totalWorkers: document.getElementById('totalWorkers'),
            activeWorkers: document.getElementById('activeWorkers'),
            inactiveWorkers: document.getElementById('inactiveWorkers'),
            pendingWorkers: document.getElementById('pendingWorkers'),
            suspendedWorkers: document.getElementById('suspendedWorkers'),
            policeCount: document.getElementById('policeCount'),
            medicalCount: document.getElementById('medicalCount'),
            visaCount: document.getElementById('visaCount'),
            ticketCount: document.getElementById('ticketCount'),
            indonesiaReadyToDeploy: document.getElementById('indonesiaReadyToDeploy'),
            indonesiaWaitingMedical: document.getElementById('indonesiaWaitingMedical'),
            indonesiaWaitingApproval: document.getElementById('indonesiaWaitingApproval'),
            indonesiaBlockedWorkers: document.getElementById('indonesiaBlockedWorkers')
        };

        if (elements.totalWorkers) {
            elements.totalWorkers.textContent = stats.total || 0;
        }
        if (elements.activeWorkers) {
            elements.activeWorkers.textContent = stats.active || 0;
        }
        if (elements.inactiveWorkers) {
            elements.inactiveWorkers.textContent = stats.inactive || 0;
        }
        if (elements.pendingWorkers) {
            elements.pendingWorkers.textContent = stats.pending || 0;
        }
        if (elements.suspendedWorkers) {
            elements.suspendedWorkers.textContent = stats.suspended || 0;
        }
        if (elements.policeCount) {
            elements.policeCount.textContent = stats.documents ? stats.documents.police || 0 : 0;
        }
        if (elements.medicalCount) {
            elements.medicalCount.textContent = stats.documents ? stats.documents.medical || 0 : 0;
        }
        if (elements.visaCount) {
            elements.visaCount.textContent = stats.documents ? stats.documents.visa || 0 : 0;
        }
        if (elements.ticketCount) {
            elements.ticketCount.textContent = stats.documents ? stats.documents.ticket || 0 : 0;
        }
        const indonesia = stats.indonesia_compliance || {};
        if (elements.indonesiaReadyToDeploy) {
            elements.indonesiaReadyToDeploy.textContent = indonesia.ready_to_deploy || 0;
        }
        if (elements.indonesiaWaitingMedical) {
            elements.indonesiaWaitingMedical.textContent = indonesia.waiting_medical || 0;
        }
        if (elements.indonesiaWaitingApproval) {
            elements.indonesiaWaitingApproval.textContent = indonesia.waiting_approval || 0;
        }
        if (elements.indonesiaBlockedWorkers) {
            elements.indonesiaBlockedWorkers.textContent = indonesia.blocked_workers || 0;
        }
        const indonesiaCard = document.querySelector('.indonesia-compliance-card');
        if (indonesiaCard) {
            const hasIndonesiaCounts = ['ready_to_deploy', 'waiting_medical', 'waiting_approval', 'blocked_workers']
                .some(key => Number(indonesia[key] || 0) > 0);
            indonesiaCard.classList.toggle('indonesia-compliance-field', !hasIndonesiaCounts);
            indonesiaCard.style.display = hasIndonesiaCounts ? '' : 'none';
        }
    }

    renderPagination(pagination) {
        const startEntry = (pagination.page - 1) * pagination.limit + 1;
        const endEntry = Math.min(pagination.page * pagination.limit, pagination.total);
        const infoText = `Showing ${startEntry} to ${endEntry} of ${pagination.total} entries`;
        
        const topPaginationInfo = document.getElementById('topPaginationInfo');
        const bottomPaginationInfo = document.getElementById('bottomPaginationInfo');
        
        if (topPaginationInfo) {
            topPaginationInfo.textContent = infoText;
        }
        if (bottomPaginationInfo) {
            bottomPaginationInfo.textContent = infoText;
        }
        
        // SYNC PAGE SIZE SELECTORS with current limit
        const topSelector = document.getElementById('topPageSize');
        const bottomSelector = document.getElementById('bottomPageSize');
        
        // Use state itemsPerPage if it's set, otherwise use pagination limit
        const currentLimit = this.state.itemsPerPage || pagination.limit;
        
        if (topSelector) topSelector.value = currentLimit;
        if (bottomSelector) bottomSelector.value = currentLimit;
        
        // Update state to match pagination, but preserve itemsPerPage if it was explicitly set
        if (this.state.itemsPerPage && this.state.itemsPerPage !== pagination.limit) {
            // State has a different value, keep it and update pagination data
            this.paginationData.limit = this.state.itemsPerPage;
        } else {
        this.state.itemsPerPage = pagination.limit;
        }
        this.state.currentPage = pagination.page;
        
        // Render page numbers
        this.renderPageNumbers(pagination);
    }
    
    renderPageNumbers(pagination) {
        const topPageNumbers = document.getElementById('topPageNumbers');
        const bottomPageNumbers = document.getElementById('bottomPageNumbers');
        
        if (!topPageNumbers && !bottomPageNumbers) return;
        
        const totalPages = Math.ceil(pagination.total / pagination.limit);
        const currentPage = pagination.page;
        
        // Ensure current page doesn't exceed total pages
        const validCurrentPage = Math.min(currentPage, totalPages);
        
        let pageNumbersHTML = '';
        
        // Previous button - only show if there are multiple pages and not on first page
        if (totalPages > 1) {
            pageNumbersHTML += `<button class="page-btn prev-btn" ${validCurrentPage <= 1 ? 'disabled' : ''} data-page="${validCurrentPage - 1}">‹</button>`;
        }
        
        // Page numbers - only show if there are multiple pages
        if (totalPages > 1) {
            const startPage = Math.max(1, validCurrentPage - 2);
            const endPage = Math.min(totalPages, validCurrentPage + 2);
        
        for (let i = startPage; i <= endPage; i++) {
                pageNumbersHTML += `<button class="page-btn ${i === validCurrentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
            }
        }
        
        // Next button - only show if there are multiple pages and not on last page
        if (totalPages > 1) {
            pageNumbersHTML += `<button class="page-btn next-btn" ${validCurrentPage >= totalPages ? 'disabled' : ''} data-page="${validCurrentPage + 1}">›</button>`;
        }
        
        if (topPageNumbers) {
            topPageNumbers.innerHTML = pageNumbersHTML;
        }
        if (bottomPageNumbers) {
            bottomPageNumbers.innerHTML = pageNumbersHTML;
        }
    }

    addPaginationEventListeners() {
        // Use event delegation for pagination buttons
        document.addEventListener('click', (e) => {
            // Skip if clicking on modal close buttons to prevent interference
            if (e.target.closest('.close-btn') || e.target.closest('#closeDocumentsModal')) {
                return;
            }
            
            if (e.target.classList.contains('page-btn') && !e.target.disabled) {
                const page = parseInt(e.target.getAttribute('data-page'));
                if (page && page > 0) {
debug.log(`Pagination button clicked: page ${page}`);
                    this.goToPage(page);
                }
            }
        });
    }

    showAddWorkerForm() {
        debug.log('Showing add worker form...');
        
        // Reset user interaction flag and alert check readiness
        this.userHasInteracted = false;
        this.formReadyForAlertCheck = false;
        
        // Record when form was opened
        const form = document.getElementById('workerForm');
        if (form) {
            form.dataset.openedTime = Date.now().toString();
        }
        
        // Wait 2 seconds before allowing alert checks (give form time to fully load)
        setTimeout(() => {
            this.formReadyForAlertCheck = true;
        }, 2000);
        
        // Clean up any existing listeners first
        this.cleanupStatusListeners();
        
        // Use the external form function if available
        if (window.openAddWorkerForm) {
            window.openAddWorkerForm();
        } else {
            // Fallback to direct form display
            const form = document.getElementById('workerForm');
            if (form) {
                delete form.dataset.originalValues; // Clear any previous values
            }
            const formContainer = document.getElementById('workerFormContainer');
            if (formContainer) {
                formContainer.classList.remove('force-hidden');
                formContainer.classList.add('show');
                
                // Mobile: Ensure scrolling works - use setTimeout to ensure DOM is ready
                setTimeout(() => {
                    if (window.innerWidth <= 768) {
                        const formWrapper = formContainer.querySelector('.form-wrapper');
                        const formContent = formContainer.querySelector('.form-content');
                        if (formWrapper && formContent) {
                            // Critical: Set min-height: 0 for flex scrolling to work
                            formWrapper.style.setProperty('min-height', '0', 'important');
                            formWrapper.style.setProperty('display', 'flex', 'important');
                            formWrapper.style.setProperty('flex-direction', 'column', 'important');
                            // Ensure form-wrapper is scrollable
                            formWrapper.style.setProperty('overflow-y', 'scroll', 'important');
                            formWrapper.style.setProperty('overflow-x', 'hidden', 'important');
                            formWrapper.style.setProperty('height', '100vh', 'important');
                            formWrapper.style.setProperty('max-height', '100vh', 'important');
                            formWrapper.style.setProperty('-webkit-overflow-scrolling', 'touch', 'important');
                            // Ensure form-content can grow and scroll
                            formContent.style.setProperty('flex', '1 1 auto', 'important');
                            formContent.style.setProperty('min-height', '0', 'important');
                            formContent.style.setProperty('min-height', 'calc(100vh + 500px)', 'important');
                            formContent.style.setProperty('padding-bottom', '400px', 'important');
                            formContent.style.setProperty('overflow-y', 'visible', 'important');
                            formContent.style.setProperty('overflow-x', 'hidden', 'important');
                            // Ensure container doesn't prevent scrolling
                            formContainer.style.setProperty('overflow-y', 'visible', 'important');
                            formContainer.style.setProperty('overflow-x', 'hidden', 'important');
                        }
                    }
                }, 100);
                
                if (typeof window.initializeEnglishDatePickers === 'function') {
                    setTimeout(function() { window.initializeEnglishDatePickers(formContainer); }, 50);
                }
            }
        }
            
        // Load agents and subagents for dropdowns
        this.loadAgentsAndSubagents();
        
    }

    async hideAddWorkerForm() {
        debug.log('Closing add worker form');
        
        // Check if form is closing after successful save
        if (window.workerFormClosingAfterSave) {
            debug.log('Form closing after save, skipping confirmation');
            window.workerFormClosingAfterSave = false; // Reset flag
        } else {
            // ULTRA AGGRESSIVE: Check time since form opened
            const form = document.getElementById('workerForm');
            const formOpenedTime = form ? (form.dataset.openedTime || 0) : 0;
            const timeSinceOpen = Date.now() - parseInt(formOpenedTime);
            const MIN_TIME_BEFORE_ALERT = 5000; // 5 seconds minimum
            
            if (timeSinceOpen < MIN_TIME_BEFORE_ALERT) {
                debug.log(`Form opened ${timeSinceOpen}ms ago (less than ${MIN_TIME_BEFORE_ALERT}ms), closing without alert`);
                // Form just opened, don't show alert
            } else if (!this.userHasInteracted) {
                debug.log('User has not interacted with form, closing without alert');
                // Don't show alert, just close
            } else {
                const form = document.getElementById('workerForm');
                if (form && this.hasUnsavedChanges(form)) {
                    // Only show alert if there are actual unsaved changes
                    const confirmed = await showCloseAlert();
                    if (!confirmed) {
                        debug.log('User cancelled close - keeping form open');
                        return; // Keep form open - DON'T CLOSE
                    }
                } else {
                    debug.log('No unsaved changes, closing without alert');
                }
            }
        }
        
        // Only close if user confirmed
        debug.log('Closing form');
        const formContainer = document.getElementById('workerFormContainer');
        if (formContainer) {
            formContainer.classList.remove('show');
        }
        
        // Clean up status indicator listeners
        this.cleanupStatusListeners();
    }
    
    // Clean up status indicator listeners
    cleanupStatusListeners() {
        if (this.statusClickHandler) {
            document.removeEventListener('click', this.statusClickHandler);
            this.statusClickHandler = null;
            debug.log('Status click listeners cleaned up');
        }
    }

    showEditWorkerForm(workerId) {
        debug.log('Showing edit worker form for ID:', workerId);
        
        // Reset user interaction flag and alert check readiness
        this.userHasInteracted = false;
        this.formReadyForAlertCheck = false;
        
        // Record when form was opened
        const form = document.getElementById('workerForm');
        if (form) {
            form.dataset.openedTime = Date.now().toString();
        }
        
        // Wait 2 seconds before allowing alert checks (give form time to fully load and populate)
        setTimeout(() => {
            this.formReadyForAlertCheck = true;
        }, 2000);
        
        // Use the external form function if available
        if (window.openEditWorkerForm) {
            window.openEditWorkerForm(workerId);
        } else {
            // Fallback to direct form display
        const formContainer = document.getElementById('workerFormContainer');
        const form = document.getElementById('workerForm');
        
        if (formContainer && form) {
            formContainer.classList.remove('force-hidden');
            formContainer.classList.add('show');
            
            // Mobile: Ensure scrolling works - use shared function if available
            setTimeout(() => {
                if (typeof window.ensureMobileScrolling === 'function') {
                    window.ensureMobileScrolling(formContainer);
                } else {
                    // Fallback: apply scrolling styles directly
                    if (window.innerWidth <= 768) {
                        const formWrapper = formContainer.querySelector('.form-wrapper');
                        const formContent = formContainer.querySelector('.form-content');
                        if (formWrapper && formContent) {
                            // Critical: Set min-height: 0 for flex scrolling to work
                            formWrapper.style.setProperty('min-height', '0', 'important');
                            formWrapper.style.setProperty('display', 'flex', 'important');
                            formWrapper.style.setProperty('flex-direction', 'column', 'important');
                            // Ensure form-wrapper is scrollable
                            formWrapper.style.setProperty('overflow-y', 'scroll', 'important');
                            formWrapper.style.setProperty('overflow-x', 'hidden', 'important');
                            formWrapper.style.setProperty('height', '100vh', 'important');
                            formWrapper.style.setProperty('max-height', '100vh', 'important');
                            formWrapper.style.setProperty('-webkit-overflow-scrolling', 'touch', 'important');
                            // Ensure form-content can grow and scroll
                            formContent.style.setProperty('flex', '1 1 auto', 'important');
                            formContent.style.setProperty('min-height', '0', 'important');
                            formContent.style.setProperty('min-height', 'calc(100vh + 500px)', 'important');
                            formContent.style.setProperty('padding-bottom', '400px', 'important');
                            formContent.style.setProperty('overflow-y', 'visible', 'important');
                            formContent.style.setProperty('overflow-x', 'hidden', 'important');
                            // Ensure container doesn't prevent scrolling
                            formContainer.style.setProperty('overflow-y', 'visible', 'important');
                            formContainer.style.setProperty('overflow-x', 'hidden', 'important');
                        }
                    }
                }
            }, 100);
            
            this.populateEditForm(workerId);
        } else {
                debug.log('Form container or form not found for edit');
            }
        }
    }

    async hideEditWorkerForm() {
        debug.log('Closing edit worker form');
        
        // Check if form is closing after successful save
        if (window.workerFormClosingAfterSave) {
            debug.log('Form closing after save, skipping confirmation');
            window.workerFormClosingAfterSave = false; // Reset flag
        } else {
            // ONLY show alert if user has actually interacted with the form
            if (!this.userHasInteracted) {
                debug.log('User has not interacted with form, closing without alert');
            } else {
                const form = document.getElementById('workerForm');
                if (form && this.hasUnsavedChanges(form)) {
                    // Only show alert if there are actual unsaved changes
                    const confirmed = await showCloseAlert();
                    if (!confirmed) {
                        debug.log('User cancelled close - keeping form open');
                        return; // Keep form open - DON'T CLOSE
                    }
                }
            }
        }
        
        // Only close if user confirmed
        debug.log('Closing form');
        const formContainer = document.getElementById('workerFormContainer');
        if (formContainer) {
            formContainer.classList.remove('show');
            const form = document.getElementById('workerForm');
            if (form) {
                form.reset();
                // Clear hidden ID field
                const idField = form.querySelector('input[name="id"]');
                if (idField) {
                    idField.value = '';
                }
            }
        }
    }

    resetForm() {
        const form = document.getElementById('workerForm');
        if (form) {
            form.reset();
            const formTitle = form.querySelector('h2');
            if (formTitle) {
                formTitle.textContent = 'Add New Worker';
            }
            
            // Clear hidden ID field
            const idField = form.querySelector('input[name="id"]');
            if (idField) {
                idField.value = '';
            }
            
            // Clear original values so form doesn't think there are changes
            delete form.dataset.originalValues;
        }
    }
    
    // Check if form has unsaved changes
    hasUnsavedChanges(form) {
        if (!form) return false;
        
        // CRITICAL: If user hasn't interacted, NO changes
        if (!this.userHasInteracted) {
            return false;
        }
        
        // If original values haven't been stored yet, don't consider it as having changes
        // (This means async operations like loading dropdowns are still in progress)
        if (!form.dataset.originalValues) {
            return false; // No changes detected yet - form still initializing
        }
        
        // Compare current values with original
        try {
            const originalValues = JSON.parse(form.dataset.originalValues);
            const inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="date"], input.date-input, select, textarea');
            for (const input of inputs) {
                const key = input.name || input.id;
                if (!key) continue; // Skip if no name or id
                const currentValue = input.value || '';
                const originalValue = originalValues[key] || '';
                if (currentValue !== originalValue) {
                    return true; // Found a change
                }
            }
        } catch (e) {
            // If parsing fails, no changes
            return false;
        }
        
        return false;
    }

    async loadAgentsAndSubagents() {
        try {
            
            // Load agents
            const apiBase = window.API_BASE || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api';
            const agentResponse = await fetch(apiBase + '/agents/get.php?limit=1000');
            if (!agentResponse.ok) {
                throw new Error('Failed to load agents');
            }
            const agentData = await agentResponse.json();
            
            
            if (agentData.success && agentData.data?.list) {
                const agentSelect = document.getElementById('agent_id');
                if (agentSelect) {
                    agentSelect.innerHTML = '<option value="">Select Agent</option>';
                    agentData.data.list.forEach(agent => {
                        const option = document.createElement('option');
                        option.value = agent.agent_id;
                        option.textContent = (window.toEnglishString || (v=>v))(`${agent.formatted_id || agent.agent_id} - ${agent.full_name}`);
                        agentSelect.appendChild(option);
                    });
                }
            }

            // Don't load all subagents - they are loaded per-agent by loadSubagentsForAgent when user selects an agent
            const subagentSelect = document.getElementById('subagent_id');
            if (subagentSelect) {
                subagentSelect.innerHTML = '<option value="">Select Subagent</option>';
            }
        } catch (error) {
            debug.error('Error loading agents and subagents:', error);
            // Don't modify form values on error - just log it
        }
    }

    async populateEditForm(workerId) {
        try {
            const response = await fetch(`${this.baseUrl}/core/get.php?id=${workerId}`);
            const data = await response.json();
            
            if (data.success && data.data.workers && data.data.workers.length > 0) {
                const worker = data.data.workers[0];
                const form = document.getElementById('workerForm');
                
                if (form) {
                    // Populate form fields
                    Object.keys(worker).forEach(key => {
                        const field = form.querySelector(`[name="${key}"]`);
                        if (field) {
                            field.value = worker[key] || '';
                        }
                    });

                    // Change form title to "Edit Workers"
                    const formTitle = form.querySelector('h2');
                    if (formTitle) {
                        formTitle.textContent = 'Edit Workers';
                    }
                    
                    // Set hidden ID field
                    const idField = form.querySelector('input[name="id"]');
                    if (idField) {
                        idField.value = worker.id;
                    }
                    
                    // Load agents and subagents after populating form
                    await this.loadAgentsAndSubagents();
                    
                    // Store original values after populating (with delay to ensure all fields and dropdowns are set)
                    setTimeout(() => {
                        delete form.dataset.originalValues; // Clear any previous values
                    }, 500);
                    
                    debug.log('✅ Edit form populated with worker data:', worker);
                }
            } else {
('❌ No worker data found for ID:', workerId);
                // Worker data not found - no alert needed
            }
        } catch (error) {
('Error populating edit form:', error);
            // Error loading worker data - no alert needed
        }
    }


    async updateDocumentStatus(workerId, documentType, status) {
        try {
            const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
            const response = await fetch(`${workersApi}/bulk-update-documents.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    worker_ids: [workerId],
                    document_type: documentType,
                    status: status
                })
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    // Update the table display
                    this.updateDocumentStatusInTable(workerId, documentType, status);
                    // Status updated - no alert needed
                } else {
                    debug.error(`Failed to update ${documentType} status:`, result.message);
                    showNotification(result.message || `Failed to update ${documentType} status`, 'error');
                }
            } else {
                const errorText = await response.text();
                debug.error(`Failed to update ${documentType} status:`, response.status, errorText);
                showNotification(`Failed to update ${documentType} status`, 'error');
            }
        } catch (error) {
            debug.error('Error updating document status:', error);
            showNotification(`Error updating ${documentType} status`, 'error');
        }
    }

    updateDocumentStatusInTable(workerId, documentType, status) {
        const row = document.querySelector(`tr[data-id="${workerId}"]`);
        if (!row) {
            debug.log(`Row not found for worker ${workerId}`);
            return;
        }
        
        // Find the correct column based on document type
        let statusCell;
        const cells = row.querySelectorAll('td.document-icon .document-status-icon');
        const offset = this.isIndonesiaProgramContext() ? 1 : 0;

        switch (documentType) {
            case 'training_certificate':
                // Training Certificate is the first document icon column
                statusCell = cells[0];
                break;
            case 'police':
                statusCell = cells[offset + 0];
                break;
            case 'medical':
                statusCell = cells[offset + 1];
                break;
            case 'visa':
                statusCell = cells[offset + 2];
                break;
            case 'ticket':
                statusCell = cells[offset + 3];
                break;
        }
        
        if (statusCell) {
            const normalizedStatus = this.normalizeStatus(status);
            statusCell.setAttribute('data-status', normalizedStatus);
            statusCell.className = `document-status-icon ${normalizedStatus}`;
            statusCell.setAttribute('title', `${documentType.charAt(0).toUpperCase() + documentType.slice(1)}: ${this.getStatusText(normalizedStatus)}`);
            debug.log(`Updated ${documentType} status in table to: ${normalizedStatus}`);
        } else {
            debug.warn(`Status cell not found for ${documentType} in worker ${workerId}`);
        }
    }

    setLoading(isLoading) {
        const overlay = document.querySelector('.table-overlay');
        const tbody = document.getElementById('workerTableBody');
        if (isLoading) {
            if (overlay) overlay.classList.add('loading');
            if (tbody) tbody.classList.add('loading');
        } else {
            if (overlay) overlay.classList.remove('loading');
            if (tbody) tbody.classList.remove('loading');
        }
    }
    
    renderMobileCards(workers) {
        let mobileContainer = document.querySelector('.mobile-worker-cards');
        
        // Create mobile container if it doesn't exist
        if (!mobileContainer) {
            mobileContainer = document.querySelector('.mobile-worker-cards');
            if (!mobileContainer) {
                mobileContainer = document.createElement('div');
                mobileContainer.className = 'mobile-worker-cards';
                
                // Insert after the table wrapper, before bottom pagination
                const tableWrapper = document.querySelector('.table-wrapper');
                if (tableWrapper && tableWrapper.parentNode) {
                    tableWrapper.parentNode.insertBefore(mobileContainer, tableWrapper.nextSibling);
                } else {
                    // Fallback: insert in table container
                    const tableContainer = document.querySelector('.table-container');
                    if (tableContainer) {
                        tableContainer.appendChild(mobileContainer);
                    }
                }
            }
        }
        
        if (!workers || workers.length === 0) {
            mobileContainer.innerHTML = '<div class="text-center">No workers found</div>';
            return;
        }
        
        const toEn = window.toEnglishString || window.toWesternNumerals || (v => v);
        const cardsHTML = workers.map(worker => {
            const workerName = toEn(worker.worker_name || worker.full_name || '');
            const identityNumber = toEn(this.getCleanContactNumber(worker));
            const passportNumber = toEn(this.getCleanPassportNumber(worker));
            const agentName = toEn(worker.agent_name || 'N/A');
            const subagentName = toEn(worker.subagent_name || 'N/A');
            
            const trainingDoc = this.isIndonesiaProgramContext() ? `
                        <div class="worker-card-doc indonesia-compliance-field">
                            <div class="worker-card-doc-label">Training</div>
                            <span class="document-status-icon" data-status="${this.normalizeStatus(worker.training_certificate_status)}" title="Training Certificate: ${this.getStatusText(worker.training_certificate_status)}">
                                <i class="fas fa-certificate"></i>
                            </span>
                        </div>` : '';

            return `
                <div class="worker-card" data-id="${worker.id}">
                    <div class="worker-card-header">
                        <div class="worker-card-checkbox">
                            <input type="checkbox" class="worker-checkbox" value="${worker.id}" title="Select worker">
                        </div>
                        <div class="worker-card-id">${toEn(worker.formatted_id || worker.id)}</div>
                        <div class="worker-card-status status-${worker.status || 'inactive'}">${worker.status === 'approved' || worker.status === 'active' ? 'ACT' : 'INACT'}</div>
                    </div>
                    
                    <div class="worker-card-body">
                        <div class="worker-card-field">
                            <div class="worker-card-label">Name</div>
                            <div class="worker-card-value">${workerName}</div>
                        </div>
                        <div class="worker-card-field">
                            <div class="worker-card-label">Identity</div>
                            <div class="worker-card-value">${identityNumber}</div>
                        </div>
                        <div class="worker-card-field">
                            <div class="worker-card-label">Passport</div>
                            <div class="worker-card-value">${passportNumber}</div>
                        </div>
                        <div class="worker-card-field">
                            <div class="worker-card-label">Agent</div>
                            <div class="worker-card-value">${agentName}</div>
                        </div>
                        <div class="worker-card-field">
                            <div class="worker-card-label">Subagent</div>
                            <div class="worker-card-value">${subagentName}</div>
                        </div>
                    </div>
                    
                    <div class="worker-card-documents">
                        ${trainingDoc}
                        <div class="worker-card-doc">
                            <div class="worker-card-doc-label">Police</div>
                            <span class="document-status-icon" data-status="${this.normalizeStatus(worker.police_status)}" title="Police Clearance: ${this.getStatusText(worker.police_status)}">
                                <i class="fas fa-shield-alt"></i>
                            </span>
                        </div>
                        <div class="worker-card-doc">
                            <div class="worker-card-doc-label">Medical</div>
                            <span class="document-status-icon" data-status="${this.normalizeStatus(worker.medical_status)}" title="Medical Report: ${this.getStatusText(worker.medical_status)}">
                                <i class="fas fa-heartbeat"></i>
                            </span>
                        </div>
                        <div class="worker-card-doc">
                            <div class="worker-card-doc-label">Visa</div>
                            <span class="document-status-icon" data-status="${this.normalizeStatus(worker.visa_status)}" title="Visa: ${this.getStatusText(worker.visa_status)}">
                                <i class="fas fa-passport"></i>
                            </span>
                        </div>
                        <div class="worker-card-doc">
                            <div class="worker-card-doc-label">Ticket</div>
                            <span class="document-status-icon" data-status="${this.normalizeStatus(worker.ticket_status)}" title="Ticket: ${this.getStatusText(worker.ticket_status)}">
                                <i class="fas fa-plane"></i>
                            </span>
                        </div>
                    </div>
                    
                    <div class="worker-card-actions">
                        <div class="worker-card-checkbox">
                            <input type="checkbox" class="form-checkbox" value="${worker.id}">
                        </div>
                        <div class="worker-card-buttons">
                            <button class="btn btn-primary btn-icon-small" data-action="edit-worker" data-worker-id="${worker.id}" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-icon-small" data-action="delete-worker" data-worker-id="${worker.id}" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button class="btn btn-success btn-icon-small" data-action="view-worker" data-worker-id="${worker.id}" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-info btn-icon-small" data-action="view-documents" data-worker-id="${worker.id}" title="Documents">
                                <i class="fas fa-file-alt"></i>
                            </button>
                            ${this.isIndonesiaProgramContext() ? `
                            <button class="btn btn-secondary btn-icon-small" data-action="empty-cv" data-worker-id="${worker.id}" title="Empty CV">
                                <i class="fas fa-file-lines"></i>
                            </button>` : ''}
                            <button class="btn btn-warning btn-icon-small" data-action="deploy-worker" data-worker-id="${worker.id}" title="Send Abroad">
                                <i class="fas fa-plane-departure"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        mobileContainer.innerHTML = cardsHTML;
        
        // Add event listeners for worker card buttons (replaces inline onclick handlers)
        mobileContainer.querySelectorAll('[data-action="edit-worker"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const workerId = parseInt(this.getAttribute('data-worker-id'));
                if (window.workerTable && typeof window.workerTable.editWorker === 'function') {
                    window.workerTable.editWorker(workerId);
                }
            });
        });
        
        mobileContainer.querySelectorAll('[data-action="delete-worker"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const workerId = parseInt(this.getAttribute('data-worker-id'));
                if (window.workerTable && typeof window.workerTable.deleteWorker === 'function') {
                    window.workerTable.deleteWorker(workerId);
                }
            });
        });
        
        mobileContainer.querySelectorAll('[data-action="view-worker"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const workerId = parseInt(this.getAttribute('data-worker-id'));
                if (window.openViewWorkerForm) {
                    window.openViewWorkerForm(workerId);
                } else if (window.openEditWorkerForm) {
                    window.openEditWorkerForm(workerId, true);
                } else if (typeof window.editWorker === 'function') {
                    window.editWorker(workerId);
                }
            });
        });
        
        mobileContainer.querySelectorAll('[data-action="view-documents"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const workerId = parseInt(this.getAttribute('data-worker-id'));
                if (window.workerTable && typeof window.workerTable.viewDocuments === 'function') {
                    window.workerTable.viewDocuments(workerId);
                }
            });
        });

        mobileContainer.querySelectorAll('[data-action="empty-cv"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const workerId = parseInt(this.getAttribute('data-worker-id'));
                if (typeof window.showEmptyCv === 'function') {
                    window.showEmptyCv(workerId);
                }
            });
        });

        mobileContainer.querySelectorAll('[data-action="deploy-worker"]').forEach(btn => {
            btn.addEventListener('click', function() {
                const workerId = parseInt(this.getAttribute('data-worker-id'));
                if (typeof window.openDeploymentModal === 'function') {
                    window.openDeploymentModal(workerId);
                }
            });
        });
    }
}

// Global functions - Removed duplicate populateEditForm function


window.showDocuments = async function(workerId) {
    debug.log('Show documents called with ID:', workerId);
    
    try {
        // Load worker documents
        const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
        const response = await fetch(`${workersApi}/core/get.php?id=${workerId}`);
        const data = await response.json();
        
        if (data.success && data.data.workers && data.data.workers.length > 0) {
            const worker = data.data.workers[0];
            
            // Populate worker info in modal
            document.getElementById('documentsWorkerName').textContent = (window.toEnglishString || window.toWesternNumerals || (v=>v))(worker.worker_name || worker.full_name || 'Unknown Worker');
            document.getElementById('documentsWorkerStatus').textContent = worker.status === 'approved' || worker.status === 'active' ? 'Act' : 'Inact';
            
            // Set worker ID in form
            const workerIdField = document.getElementById('documentsWorkerIdField');
            if (workerIdField) {
                workerIdField.value = worker.id;
                debug.log('Worker ID set to:', worker.id);
            } else {
                debug.error('Worker ID field not found!');
            }
            
            // Populate document fields
            populateDocumentFields(worker);
            
        // Show the modal
        const modal = document.getElementById('documentsModal');
        if (modal) {
            const isIndonesia = window.workerTable && typeof window.workerTable.isIndonesiaProgramContext === 'function'
                ? window.workerTable.isIndonesiaProgramContext()
                : false;
            modal.classList.toggle('indonesia-compliance-visible', isIndonesia);
            modal.classList.add('modal-visible');
            // Trigger smooth animation
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }
        } else {
            debug.error('Failed to load worker documents');
        }
    } catch (error) {
        debug.error('Error loading documents:', error);
        // Error loading documents - no alert needed
    }
};

// Function to populate document fields with worker data
function populateDocumentFields(worker) {
    debug.log('Populating document fields with worker data:', worker);
    debug.log('Document dates from worker:', {
        identity_date: worker.identity_date,
        passport_date: worker.passport_date,
        police_date: worker.police_date,
        medical_date: worker.medical_date,
        visa_date: worker.visa_date,
        ticket_date: worker.ticket_date,
        training_certificate_date: worker.training_certificate_date
    });
    
    // Identity Document
    const identityNumber = document.getElementById('identityNumber');
    const identityDate = document.getElementById('identityDate');
    const identityStatus = document.getElementById('identityStatus');
    
    const toEn = window.toEnglishString || window.toWesternNumerals || (v => v);
    if (identityNumber) identityNumber.value = toEn(worker.identity_number || '');
    if (identityDate) {
        const dateValue = worker.identity_date && worker.identity_date !== '0000-00-00' ? worker.identity_date : '';
        if (identityDate._flatpickr) identityDate._flatpickr.setDate(dateValue || null);
        else identityDate.value = dateValue;
        debug.log('Setting identity_date to:', dateValue, 'element found:', !!identityDate);
    } else {
        debug.log('identityDate element NOT found');
    }
    if (identityStatus) {
        identityStatus.value = worker.identity_status || 'pending';
        debug.log('Setting identity_status to:', worker.identity_status);
    }
    // Always show real uploaded files, ignore database file references
    updateCurrentFile('identityCurrentFile', null);
    
    // Passport Document
    const passportNumber = document.getElementById('passportNumber');
    const passportDate = document.getElementById('passportDate');
    const passportStatus = document.getElementById('passportStatus');
    
    if (passportNumber) passportNumber.value = toEn(worker.passport_number || '');
    if (passportDate) {
        const dateValue = worker.passport_date && worker.passport_date !== '0000-00-00' ? worker.passport_date : '';
        if (passportDate._flatpickr) passportDate._flatpickr.setDate(dateValue || null);
        else passportDate.value = dateValue;
        debug.log('Setting passport_date to:', dateValue);
    }
    if (passportStatus) {
        passportStatus.value = worker.passport_status || 'pending';
        debug.log('Setting passport_status to:', worker.passport_status);
    }
    updateCurrentFile('passportCurrentFile', null);
    
    // Police Clearance
    const policeNumber = document.getElementById('policeNumber');
    const policeDate = document.getElementById('policeDate');
    const policeStatus = document.getElementById('policeStatus');
    
    if (policeNumber) policeNumber.value = toEn(worker.police_number || '');
    if (policeDate) {
        const dateValue = worker.police_date && worker.police_date !== '0000-00-00' ? worker.police_date : '';
        if (policeDate._flatpickr) policeDate._flatpickr.setDate(dateValue || null);
        else policeDate.value = dateValue;
        debug.log('Setting police_date to:', dateValue);
    }
    if (policeStatus) {
        policeStatus.value = worker.police_status || 'pending';
        debug.log('Setting police_status to:', worker.police_status);
    }
    updateCurrentFile('policeCurrentFile', null);
    
    // Medical Report
    const medicalNumber = document.getElementById('medicalNumber');
    const medicalDate = document.getElementById('medicalDate');
    const medicalStatus = document.getElementById('medicalStatus');
    
    if (medicalNumber) medicalNumber.value = toEn(worker.medical_number || '');
    if (medicalDate) {
        const dateValue = worker.medical_date && worker.medical_date !== '0000-00-00' ? worker.medical_date : '';
        if (medicalDate._flatpickr) medicalDate._flatpickr.setDate(dateValue || null);
        else medicalDate.value = dateValue;
        debug.log('Setting medical_date to:', dateValue);
    }
    if (medicalStatus) {
        medicalStatus.value = worker.medical_status || 'pending';
        debug.log('Setting medical_status to:', worker.medical_status);
    }
    updateCurrentFile('medicalCurrentFile', null);
    
    // Visa Document
    const visaNumber = document.getElementById('visaNumber');
    const visaDate = document.getElementById('visaDate');
    const visaStatus = document.getElementById('visaStatus');
    
    if (visaNumber) visaNumber.value = toEn(worker.visa_number || '');
    if (visaDate) {
        const dateValue = worker.visa_date && worker.visa_date !== '0000-00-00' ? worker.visa_date : '';
        if (visaDate._flatpickr) visaDate._flatpickr.setDate(dateValue || null);
        else visaDate.value = dateValue;
        debug.log('Setting visa_date to:', dateValue);
    }
    if (visaStatus) {
        visaStatus.value = worker.visa_status || 'pending';
        debug.log('Setting visa_status to:', worker.visa_status);
    }
    updateCurrentFile('visaCurrentFile', null);
    
    // Ticket Document
    const ticketNumber = document.getElementById('ticketNumber');
    const ticketDate = document.getElementById('ticketDate');
    const ticketStatus = document.getElementById('ticketStatus');
    
    if (ticketNumber) ticketNumber.value = toEn(worker.ticket_number || '');
    if (ticketDate) {
        const dateValue = worker.ticket_date && worker.ticket_date !== '0000-00-00' ? worker.ticket_date : '';
        if (ticketDate._flatpickr) ticketDate._flatpickr.setDate(dateValue || null);
        else ticketDate.value = dateValue;
        debug.log('Setting ticket_date to:', dateValue);
    }
    if (ticketStatus) {
        ticketStatus.value = worker.ticket_status || 'pending';
        debug.log('Setting ticket_status to:', worker.ticket_status);
    }
    updateCurrentFile('ticketCurrentFile', null);

    // Training Certificate Document
    const trainingCertificateNumber = document.getElementById('trainingCertificateNumber');
    const trainingCertificateDate = document.getElementById('trainingCertificateDate');
    const trainingCertificateStatus = document.getElementById('trainingCertificateStatus');

    if (trainingCertificateNumber) trainingCertificateNumber.value = toEn(worker.training_certificate_number || '');
    if (trainingCertificateDate) {
        const dateValue = worker.training_certificate_date && worker.training_certificate_date !== '0000-00-00' ? worker.training_certificate_date : '';
        if (trainingCertificateDate._flatpickr) trainingCertificateDate._flatpickr.setDate(dateValue || null);
        else trainingCertificateDate.value = dateValue;
        debug.log('Setting training_certificate_date to:', dateValue);
    }
    if (trainingCertificateStatus) {
        trainingCertificateStatus.value = worker.training_certificate_status || 'pending';
        debug.log('Setting training_certificate_status to:', worker.training_certificate_status);
    }
    updateCurrentFile('trainingCertificateCurrentFile', null);
    
    // Update progress bar
    updateDocumentProgress();
}

// Function to update current file display
function updateCurrentFile(elementId, fileName) {
    const element = document.getElementById(elementId);
    debug.log(`Updating ${elementId} with fileName:`, fileName);
    
    if (!element) {
        debug.error(`Element with ID ${elementId} not found!`);
        return;
    }
    
    // Always show a simple "View Files" button that shows all uploaded files
    // This button should NEVER disappear, even after uploading
    element.innerHTML = `
        <button type="button" class="view-files-btn" data-action="view-files" data-element-id="${elementId}">
            <i class="fas fa-eye"></i> View Files
        </button>
    `;
    // Add event listener after innerHTML is set
    const viewBtn = element.querySelector('[data-action="view-files"]');
    if (viewBtn) {
        viewBtn.addEventListener('click', function() {
            const elementId = this.getAttribute('data-element-id');
            if (typeof showAllFilesForType === 'function') {
                showAllFilesForType(elementId);
            }
        });
    }
    
    debug.log(`View Files button created for ${elementId}`);
}

function checkFileExists(fileName, elementId, element) {
    // Check main directory first
    fetch(`../uploads/documents/${fileName}`, { method: 'HEAD' })
        .then(response => {
            if (response.ok) {
                debug.log(`File ${fileName} found in main directory`);
                showFileWithViewButton(element, fileName);
            } else {
                debug.log(`File ${fileName} not found in main directory, checking subdirectories...`);
                checkFileInSubdirectories(fileName, elementId, element);
            }
        })
        .catch(error => {
            debug.log(`Error checking main directory for ${fileName}:`, error);
            checkFileInSubdirectories(fileName, elementId, element);
        });
}

function checkFileInSubdirectories(fileName, elementId, element) {
    const subdirs = ['identity', 'passport', 'police', 'medical', 'visa', 'ticket', 'training_certificate'];
    let found = false;
    let checkedCount = 0;
    
    subdirs.forEach(subdir => {
        fetch(`../uploads/documents/${subdir}/${fileName}`, { method: 'HEAD' })
            .then(response => {
                checkedCount++;
                if (response.ok && !found) {
                    debug.log(`File ${fileName} found in ${subdir} directory`);
                    found = true;
                    showFileWithViewButton(element, fileName);
                } else if (checkedCount === subdirs.length && !found) {
                    debug.log(`File ${fileName} not found anywhere, showing available files`);
                    showAvailableFilesForType(elementId, element);
                }
            })
            .catch(error => {
                checkedCount++;
                debug.log(`File not found in ${subdir}:`, error);
                if (checkedCount === subdirs.length && !found) {
                    debug.log(`File ${fileName} not found anywhere, showing available files`);
                    showAvailableFilesForType(elementId, element);
                }
            });
    });
}

function showFileWithViewButton(element, fileName) {
    const safeFileName = fileName.replace(/'/g, "&#39;").replace(/"/g, "&quot;");
    element.innerHTML = `
        <span class="file-name">${safeFileName}</span>
        <button type="button" class="view-btn" data-action="view-document" data-file-name="${safeFileName}">
            <i class="fas fa-eye"></i> View
        </button>
    `;
    // Add event listener after innerHTML is set
    const viewBtn = element.querySelector('[data-action="view-document"]');
    if (viewBtn) {
        viewBtn.addEventListener('click', function() {
            const fileName = this.getAttribute('data-file-name');
            if (typeof viewDocument === 'function') {
                viewDocument(fileName);
            }
        });
    }
}

function showAvailableFilesForType(elementId, element) {
    const documentType = getDocumentTypeFromElementId(elementId);
    const availableFiles = getAvailableFilesForType(documentType);
    
    if (availableFiles && Array.isArray(availableFiles) && availableFiles.length > 0) {
        element.innerHTML = `
            <div class="available-files-section">
                <div class="available-files-header">Available Files:</div>
                <div class="available-files-list">
                    ${availableFiles.map(file => {
                        if (!file || !file.name) return '';
                        const safeFileName = (file.name || '').replace(/'/g, "&#39;").replace(/"/g, "&quot;");
                        return `
                        <div class="available-file-item" data-action="view-available-file" data-file-name="${safeFileName}">
                            <i class="fas fa-file"></i>
                            <span class="file-name">${safeFileName}</span>
                            <button type="button" class="view-btn-small">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    `;
                    }).filter(item => item !== '').join('')}
                </div>
            </div>
        `;
    } else {
        element.innerHTML = '<span class="no-file">No file</span>';
    }
}

// Function to show all files for a document type
async function showAllFilesForType(elementId) {
    debug.log('showAllFilesForType called with elementId:', elementId);
    const documentType = getDocumentTypeFromElementId(elementId);
    debug.log('Document type determined:', documentType);
    
    try {
        // Fetch files dynamically from server
        const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
        const apiUrl = `${workersApi}/get-files.php?type=${documentType}`;
        debug.log('Fetching files from API:', apiUrl);
        
        const response = await fetch(apiUrl);
        const data = await response.json();
        debug.log('API response:', data);
        
        if (data.success && data.files.length > 0) {
            debug.log('Files found:', data.files.length);
            // Create a modern modal to show all files
            const modal = document.createElement('div');
            modal.className = 'files-modal modern-files-modal';
            modal.innerHTML = `
                <div class="files-modal-content modern-modal-content">
                    <div class="files-modal-header modern-modal-header">
                        <div class="header-icon">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <div class="header-text">
                            <h3>Uploaded Files</h3>
                            <p>${documentType.toUpperCase()} Documents</p>
                        </div>
                        <div class="header-actions">
                            <button type="button" class="btn-select-all" data-action="select-all-files" data-document-type="${documentType}" title="Select All">
                                <i class="fas fa-square"></i>
                            </button>
                            <button type="button" class="btn-delete-all" data-action="delete-selected-files" data-document-type="${documentType}" title="Delete Selected">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button class="close-files-modal modern-close-btn" data-action="close-files-modal">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="files-modal-body modern-modal-body">
                        <div class="files-grid">
                            ${data.files.map(file => `
                                <div class="file-card modern-file-card" data-file-name="${file.name}">
                                    <div class="file-checkbox">
                                        <input type="checkbox" class="file-select-checkbox" value="${file.name}" data-action="update-select-all">
                                    </div>
                                    <div class="file-icon">
                                        <i class="fas fa-file-image"></i>
                                    </div>
                                    <div class="file-info">
                                        <div class="file-name">${file.name}</div>
                                        <div class="file-type">${file.name.split('.').pop().toUpperCase()}</div>
                                    </div>
                                    <div class="file-actions modern-actions">
                                        <button type="button" class="action-btn view-btn" data-action="view-file" data-file-name="${file.name.replace(/'/g, "&#39;").replace(/"/g, "&quot;")}" title="View File">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="action-btn print-btn" data-action="print-file" data-file-name="${file.name.replace(/'/g, "&#39;").replace(/"/g, "&quot;")}" title="Print File">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button type="button" class="action-btn delete-btn" data-action="delete-file" data-file-name="${file.name.replace(/'/g, "&#39;").replace(/"/g, "&quot;")}" data-document-type="${documentType}" title="Delete File">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;
            
            // Add click outside to close
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeFilesModal();
                }
            });
            
            // Append modal to document and show it
            document.body.appendChild(modal);
            modal.classList.add('modal-visible');
            
            // Add event listeners for modal buttons (replaces inline onclick handlers)
            modal.querySelectorAll('[data-action="select-all-files"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const docType = this.getAttribute('data-document-type');
                    if (typeof toggleSelectAll === 'function') {
                        toggleSelectAll(docType);
                    }
                });
            });
            
            modal.querySelectorAll('[data-action="delete-selected-files"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const docType = this.getAttribute('data-document-type');
                    if (typeof deleteSelectedFiles === 'function') {
                        deleteSelectedFiles(docType);
                    }
                });
            });
            
            modal.querySelectorAll('[data-action="close-files-modal"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (typeof closeFilesModal === 'function') {
                        closeFilesModal();
                    }
                });
            });
            
            modal.querySelectorAll('[data-action="view-file"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const fileName = this.getAttribute('data-file-name');
                    this.classList.add('pressed');
                    if (typeof viewDocument === 'function') {
                        viewDocument(fileName);
                    }
                    setTimeout(() => this.classList.remove('pressed'), 1000);
                });
            });
            
            modal.querySelectorAll('[data-action="print-file"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const fileName = this.getAttribute('data-file-name');
                    this.classList.add('pressed');
                    if (typeof printDocument === 'function') {
                        printDocument(fileName);
                    }
                    setTimeout(() => this.classList.remove('pressed'), 1000);
                });
            });
            
            modal.querySelectorAll('[data-action="delete-file"]').forEach(btn => {
                btn.addEventListener('click', function() {
                    const fileName = this.getAttribute('data-file-name');
                    const docType = this.getAttribute('data-document-type');
                    this.classList.add('pressed');
                    if (typeof deleteDocument === 'function') {
                        deleteDocument(fileName, docType, this);
                    }
                    setTimeout(() => this.classList.remove('pressed'), 1000);
                });
            });
            
            modal.querySelectorAll('[data-action="update-select-all"]').forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (typeof updateSelectAllButton === 'function') {
                        updateSelectAllButton();
                    }
                });
            });
            
            // Trigger smooth animation
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        } else {
            debug.log('No files found for document type:', documentType);
            // Show a message that no files are available
            SimpleAlert.show('No Files', `No files found for ${documentType.toUpperCase()} documents.`, 'info');
        }
    } catch (error) {
        debug.error('Error fetching files:', error);
        // Error loading files - no alert needed
    }
}

// Function to close files modal
function closeFilesModal() {
    debug.log('Closing files modal');
    const modal = document.querySelector('.files-modal');
    if (modal) {
        // Smooth close animation
        modal.classList.remove('show');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

// Function to upload file immediately
async function uploadFile(file, documentType) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('documentType', documentType);
    
    try {
        const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
        const response = await fetch(`${workersApi}/upload-file.php`, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        if (result.success) {
            debug.log('File uploaded successfully:', result.fileName);
            // Show success message
            // File uploaded successfully - no alert needed
        } else {
            debug.error('Upload failed:', result.message);
        }
    } catch (error) {
        debug.error('Upload error:', error);
        // Upload error - no alert needed
    }
}

// Function to find file path
function findFilePath(fileName) {
    // Return the correct path for the file
    // This will be used by the print function
    return `../uploads/documents/${fileName}`;
}

// Function to print document
async function printDocument(fileName) {
    debug.log('Print document:', fileName);
    
    // Find the correct file path
    const filePath = await getFileForPrinting(fileName);
    
    if (filePath) {
        debug.log('Found file at:', filePath);
        // Open file in new window for printing
        const printWindow = window.open(filePath, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
        if (printWindow) {
            printWindow.onload = function() {
                debug.log('File loaded in print window');
                // Wait a bit for the file to load, then print
                setTimeout(() => {
                    printWindow.print();
                }, 1500);
            };
        } else {
            debug.warn('Could not open print window. Please check your popup blocker settings.');
        }
    } else {
        debug.warn('File not found: ' + fileName);
    }
}

// Function to find the correct file path
function findCorrectFilePath(fileName) {
    // Check if file exists in main directory first
    return `../uploads/documents/${fileName}`;
}

// Function to check file and get correct path
async function getFileForPrinting(fileName) {
    try {
        // Check main directory first
        const mainPath = `../uploads/documents/${fileName}`;
        const response = await fetch(mainPath, { method: 'HEAD' });
        
        if (response.ok) {
            return mainPath;
        } else {
            // Check subdirectories
            const subdirs = ['identity', 'passport', 'police', 'medical', 'visa', 'ticket', 'training_certificate'];
            for (const subdir of subdirs) {
                const subPath = `../uploads/documents/${subdir}/${fileName}`;
                const subResponse = await fetch(subPath, { method: 'HEAD' });
                if (subResponse.ok) {
                    return subPath;
                }
            }
        }
        return null;
    } catch (error) {
        debug.error('Error finding file:', error);
        return null;
    }
}

// Function to delete document
async function deleteDocument(fileName, documentType, buttonElement) {
    const confirmed = await SimpleAlert.show('Delete Document', `Are you sure you want to delete "${fileName}"?`, 'warning');
    if (confirmed) {
        debug.log('Delete document:', fileName, 'from', documentType);
        
        // Send delete request to server with just the fileName
        // Let the server find the file
        const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
        fetch(`${workersApi}/delete-document.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                fileName: fileName,
                documentType: documentType
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove the file card from the modal
                const fileCard = buttonElement.closest('.file-card');
                if (fileCard) {
                    fileCard.remove();
                    debug.log('File card removed from modal');
                    
                    // Check if no files left
                    const remainingFiles = document.querySelectorAll('.file-card');
                    if (remainingFiles.length === 0) {
                        debug.log('No files left, closing modal');
                        closeFilesModal();
                    }
                } else {
                    debug.error('Could not find file card to remove');
                }
                // File deleted successfully - no alert needed
            } else {
                debug.error('Error deleting file:', data.message);
                SimpleAlert.show('Delete Failed', 'Failed to delete file: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            debug.error('Error deleting file:', error);
            // Error deleting file - no alert needed
        });
    }
}

function getDocumentTypeFromElementId(elementId) {
    if (elementId.includes('identity')) return 'identity';
    if (elementId.includes('passport')) return 'passport';
    if (elementId.includes('police')) return 'police';
    if (elementId.includes('medical')) return 'medical';
    if (elementId.includes('visa')) return 'visa';
    if (elementId.includes('ticket')) return 'ticket';
    return 'main';
}

function getAvailableFilesForType(documentType) {
    // Return empty array - we'll fetch files dynamically from server
    return [];
}

window.showMusaned = async function(workerId) {
    debug.log('Show Musaned called with ID:', workerId);
    
    try {
        // Load worker data for Musaned
        const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
        const response = await fetch(`${workersApi}/core/get.php?id=${workerId}`);
        const data = await response.json();
        
        if (data.success && data.data.workers && data.data.workers.length > 0) {
            const worker = data.data.workers[0];
            
            // Show Musaned form/modal
            const musanedInfo = `
Worker ID: ${worker.formatted_id || worker.id}
Name: ${worker.worker_name || worker.full_name || 'Not provided'}
Nationality: ${worker.nationality || 'Not provided'}
Passport: ${worker.passport_number || 'Not provided'}
Identity: ${worker.identity_number || 'Not provided'}

Musaned Status: ${worker.musaned_status || 'Not processed'}
            `;
            
            // Use MusanedStatus class from musaned.js
            MusanedStatus.showForm(worker);
        } else {
            debug.error('Failed to load worker data for Musaned');
        }
    } catch (error) {
        debug.error('Error loading Musaned data:', error);
        // Error loading Musaned data - no alert needed
    }
};

function ensureEmptyCvModal() {
    let modal = document.getElementById('emptyCvModal');
    if (modal) {
        const legacyToggleBtn = modal.querySelector('[data-action="toggle-empty-cv-missing"]');
        if (legacyToggleBtn) legacyToggleBtn.remove();
        return modal;
    }
    modal = document.createElement('div');
    modal.id = 'emptyCvModal';
    modal.className = 'empty-cv-modal';
    modal.innerHTML = `
        <div class="empty-cv-modal-overlay" data-action="close-empty-cv"></div>
        <div class="empty-cv-modal-dialog" role="dialog" aria-modal="true" aria-label="Empty CV">
            <div class="empty-cv-toolbar">
                <button type="button" class="empty-cv-btn" data-action="print-empty-cv">Print</button>
                <button type="button" class="empty-cv-btn" data-action="reset-empty-cv">Reset</button>
                <button type="button" class="empty-cv-btn" data-action="upload-empty-cv-photo">Upload Photo</button>
                <button type="button" class="empty-cv-btn" data-action="save-empty-cv">Save To System</button>
                <span class="empty-cv-hint">Click text to edit before printing.</span>
                <button type="button" class="empty-cv-btn close" data-action="close-empty-cv">Close</button>
            </div>
            <div class="empty-cv-body">
                <div id="emptyCvSheet"></div>
            </div>
            <input type="file" id="emptyCvPhotoInput" accept="image/*" style="display:none">
        </div>
    `;
    document.body.appendChild(modal);

    if (!document.getElementById('emptyCvModalStyles')) {
        const style = document.createElement('style');
        style.id = 'emptyCvModalStyles';
        style.textContent = `
            .empty-cv-modal{position:fixed;inset:0;display:none;z-index:10000}
            .empty-cv-modal.show{display:block}
            .empty-cv-modal-overlay{position:absolute;inset:0;background:rgba(10,20,34,.75)}
            .empty-cv-modal-dialog{position:relative;width:min(1100px,95vw);height:min(92vh,920px);margin:4vh auto;background:linear-gradient(165deg,#0d1f33 0%,#152a44 100%);border-radius:12px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 60px rgba(5,15,30,.45)}
            .empty-cv-toolbar{display:flex;gap:10px;align-items:center;padding:12px 16px;background:rgba(0,0,0,.25);border-bottom:1px solid rgba(255,255,255,.08);color:#fff;flex-wrap:wrap}
            .empty-cv-btn{background:#fff;color:#0c2d4a;border:0;border-radius:6px;padding:8px 14px;font-weight:700;font-size:13px;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.12);transition:transform .12s ease,box-shadow .12s ease}
            .empty-cv-btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.18)}
            .empty-cv-btn.close{margin-left:auto;background:rgba(255,255,255,.92)}
            .empty-cv-hint{font-size:12px;opacity:.88;flex:1;min-width:180px;line-height:1.35}
            .empty-cv-body{flex:1;overflow:auto;background:linear-gradient(180deg,#dfeaf6 0%,#eef3f8 45%,#f5f8fb 100%);padding:20px}
            #emptyCvSheet .page.cv-page{max-width:900px;margin:0 auto 22px auto;background:#fff;box-shadow:0 12px 40px rgba(15,41,64,.16),0 0 0 1px rgba(13,90,138,.08);border-radius:10px;overflow:hidden;font-family:'Segoe UI',system-ui,-apple-system,sans-serif;color:#152838}
            #emptyCvSheet .cv-accent-top{height:5px;background:linear-gradient(90deg,#1a5f8a,#2e8fc9,#1a5f8a)}
            #emptyCvSheet .header{background:linear-gradient(180deg,#d4ebfb 0%,#b9dcf3 100%);color:#062438;padding:18px 22px 16px;text-align:center;border-bottom:1px solid #8fc0e0}
            #emptyCvSheet .header h1{margin:0;font-size:22px;letter-spacing:.08em;font-weight:800;text-transform:uppercase;line-height:1.12;font-family:Georgia,'Times New Roman',serif}
            #emptyCvSheet .header h2{margin:10px 0 0;font-size:13px;font-weight:600;text-transform:none;color:#134060;max-width:46em;margin-left:auto;margin-right:auto;line-height:1.45}
            #emptyCvSheet .grid{display:grid;grid-template-columns:minmax(0,300px) minmax(0,1fr);gap:0;align-items:stretch}
            #emptyCvSheet .cv-panel{padding:18px 20px}
            #emptyCvSheet .left{background:linear-gradient(180deg,#fbfdff 0%,#f3f8fc 100%);border-right:1px solid #cfe3f0}
            #emptyCvSheet .right{background:linear-gradient(180deg,#f5fafd 0%,#eef6fb 100%)}
            #emptyCvSheet .photo-wrap{margin-bottom:4px}
            #emptyCvSheet .photo{aspect-ratio:3/4;max-height:260px;width:100%;border-radius:8px;border:1px solid #9dc4e0;background:linear-gradient(145deg,#e8f4fc,#dceefa);display:flex;align-items:center;justify-content:center;color:#4a7aa3;font-weight:800;font-size:13px;letter-spacing:.12em;overflow:hidden}
            #emptyCvSheet .photo img{width:100%;height:100%;object-fit:cover;display:block}
            #emptyCvSheet .side-title{background:linear-gradient(90deg,#1e6ead,#2b8cc9);color:#fff;font-size:12px;font-weight:800;padding:8px 12px;line-height:1.25;margin:18px 0 10px;border-radius:4px;letter-spacing:.06em;text-transform:uppercase;box-shadow:0 2px 6px rgba(30,110,173,.25)}
            #emptyCvSheet .side-title:first-of-type{margin-top:6px}
            #emptyCvSheet .main-title{color:#0d4a73;font-size:13px;font-weight:800;line-height:1.25;margin:20px 0 10px;padding:8px 0 6px;border-bottom:2px solid #8ec5e8;letter-spacing:.04em;text-transform:uppercase}
            #emptyCvSheet .main-title:first-child{margin-top:0}
            #emptyCvSheet .cv-section{margin-bottom:4px}
            #emptyCvSheet .line.cv-field-row{padding:8px 2px;font-size:13px;line-height:1.4;border-bottom:1px solid #e2ecf5}
            #emptyCvSheet .line.cv-field-row:last-child{border-bottom:0}
            #emptyCvSheet .pair{display:grid;grid-template-columns:minmax(0,40%) minmax(0,60%);gap:10px 14px;align-items:start}
            #emptyCvSheet .pair .k{font-weight:700;color:#2a5270;font-size:12px;text-transform:none;padding-top:3px}
            #emptyCvSheet .left .line .k{color:#244a63}
            #emptyCvSheet .left, #emptyCvSheet .left .line, #emptyCvSheet .left .line span{color:#1a2f42 !important}
            #emptyCvSheet .cv-value[contenteditable="true"]{outline:none;display:block;min-height:1.25em;word-break:break-word;border-radius:3px;padding:2px 4px;margin:-2px -4px}
            #emptyCvSheet .cv-value[contenteditable="true"]:hover{background:rgba(46,143,201,.08)}
            #emptyCvSheet .cv-duties{display:flex;flex-direction:column;gap:0;border:1px solid #cfe3f2;border-radius:8px;overflow:hidden;background:#fafdff;margin-top:6px}
            #emptyCvSheet .cv-duty-row.cv-field-row{padding:10px 12px;border-bottom:1px solid #e2ecf5;margin:0}
            #emptyCvSheet .cv-duty-row.cv-field-row:last-child{border-bottom:0}
            #emptyCvSheet .cv-duty-row .k{font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:#356688}
            #emptyCvSheet .note{color:#6a7d8f;font-size:11px;margin-top:18px;text-align:right;letter-spacing:.02em}
            #emptyCvSheet .editable:focus{outline:2px solid rgba(46,143,201,.55);outline-offset:2px;border-radius:4px}
            #emptyCvSheet .missing-value{color:#c62828;font-weight:700}
            #emptyCvSheet .hidden-by-missing-filter{display:none !important}
            @media (max-width:780px){
                #emptyCvSheet .grid{grid-template-columns:1fr}
                #emptyCvSheet .left{border-right:0;border-bottom:1px solid #cfe3f0}
            }
        `;
        document.head.appendChild(style);
    }

    modal.addEventListener('click', function (event) {
        const actionEl = event.target.closest('[data-action]');
        if (!actionEl) return;
        const action = actionEl.getAttribute('data-action');
        if (action === 'close-empty-cv') window.closeEmptyCvModal();
        if (action === 'reset-empty-cv') window.resetEmptyCvModal();
        if (action === 'print-empty-cv') window.printEmptyCvModal();
        if (action === 'save-empty-cv') window.saveEmptyCvModal();
        if (action === 'upload-empty-cv-photo') {
            const fileInput = document.getElementById('emptyCvPhotoInput');
            if (fileInput) fileInput.click();
        }
    });
    const fileInput = modal.querySelector('#emptyCvPhotoInput');
    if (fileInput) {
        fileInput.addEventListener('change', async function () {
            const file = this.files && this.files[0] ? this.files[0] : null;
            if (!file) return;
            await window.uploadEmptyCvPhoto(file);
            this.value = '';
        });
    }

    return modal;
}

function buildEmptyCvHtml(worker) {
    const esc = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    const pick = (...keys) => {
        for (const key of keys) {
            const val = worker && Object.prototype.hasOwnProperty.call(worker, key) ? worker[key] : undefined;
            if (val !== undefined && val !== null && String(val).trim() !== '') {
                return String(val).trim();
            }
        }
        return '';
    };
    const line = (value, fallback = 'Not provided') => {
        const clean = String(value ?? '').trim();
        return clean ? esc(clean) : fallback;
    };
    const show = (value) => line(value, '-');
    const markMissing = (value) => {
        const raw = String(value || '').trim();
        if (!raw || raw === '-' || raw.toLowerCase() === 'not provided') {
            return '<span class="missing-value">Missing</span>';
        }
        return esc(raw);
    };

    const fullName = line(pick('worker_name', 'full_name'));
    const nationality = line(pick('nationality', 'country'), 'INDONESIAN');
    const identity = line(pick('identity_number'));
    const passport = line(pick('passport_number'));
    const job = line(pick('job_title', 'occupation', 'specialization'), 'DOMESTIC WORKER');
    const dob = line(pick('date_of_birth', 'birth_date'));
    const placeOfBirth = line(pick('place_of_birth'));
    const phone = line(pick('phone', 'contact_number', 'contact', 'mobile'));
    const email = line(pick('email'));
    const address = line(pick('address', 'city', 'country'));
    const maritalStatus = line(pick('marital_status'));
    const language = line(pick('language', 'language_level'));
    const languageLevel = line(pick('language_level'));
    const educationLevel = line(pick('education_level', 'qualification'));
    const workExperience = line(pick('work_experience', 'local_experience', 'abroad_experience'));
    const skills = line(pick('skills'));
    const localExperience = line(pick('local_experience'));
    const abroadExperience = line(pick('abroad_experience'));
    const qualification = line(pick('qualification'));
    const trainingNotes = line(pick('training_notes'));
    const contractDuration = line(pick('contract_duration'));
    const workingHours = line(pick('working_hours'));
    const salary = line(pick('salary'));
    const gender = show(pick('gender'));
    const age = show(pick('age'));
    const city = show(pick('city'));
    const country = show(pick('country'));
    const workerStatus = show(pick('status'));
    const passportExpiry = show(pick('passport_expiry', 'passport_expiry_date'));
    const medicalNumber = show(pick('medical_number'));

    const photoUrl = pick('personal_photo_url');

    return `
        <div class="page cv-page">
            <div class="cv-accent-top"></div>
            <div class="header">
                <h1 class="editable" contenteditable="true" data-field="full_name">${fullName}</h1>
                <h2 class="editable" contenteditable="true" data-field="job_title">${job}</h2>
            </div>
            <div class="grid">
                <aside class="left cv-panel">
                    <div class="photo-wrap">
                        <div class="photo"><img id="emptyCvPhotoPreview" src="${photoUrl ? esc(photoUrl) : ''}" alt="" style="${photoUrl ? 'width:100%;height:100%;object-fit:cover;display:block' : 'display:none'}"><span id="emptyCvPhotoPlaceholder" style="${photoUrl ? 'display:none' : 'display:block'}">PHOTO</span></div>
                    </div>
                    <div class="side-title">Contact info</div>
                    <div class="line cv-field-row pair"><span class="k">Phone</span><span class="cv-value editable" contenteditable="true" data-field="phone">${phone}</span></div>
                    <div class="line cv-field-row pair"><span class="k">Email</span><span class="cv-value editable" contenteditable="true" data-field="email">${email}</span></div>
                    <div class="line cv-field-row pair"><span class="k">Address</span><span class="cv-value editable" contenteditable="true" data-field="address">${address}</span></div>
                    <div class="side-title">Personal details</div>
                    <div class="line cv-field-row pair"><span class="k">Date of Birth</span><span class="cv-value editable" contenteditable="true" data-field="date_of_birth">${dob}</span></div>
                    <div class="line cv-field-row pair"><span class="k">Place of Birth</span><span class="cv-value editable" contenteditable="true" data-field="place_of_birth">${placeOfBirth}</span></div>
                    <div class="line cv-field-row pair"><span class="k">Nationality</span><span class="cv-value editable" contenteditable="true" data-field="nationality">${nationality}</span></div>
                    <div class="line cv-field-row pair"><span class="k">Gender</span><span>${markMissing(gender)}</span></div>
                    <div class="line cv-field-row pair"><span class="k">Age</span><span>${markMissing(age)}</span></div>
                    <div class="line cv-field-row pair"><span class="k">Marital Status</span><span class="cv-value editable" contenteditable="true" data-field="marital_status">${maritalStatus}</span></div>
                    <div class="line cv-field-row pair"><span class="k">Passport</span><span class="cv-value editable" contenteditable="true" data-field="passport_number">${passport}</span></div>
                    <div class="line cv-field-row pair"><span class="k">Identity</span><span class="cv-value editable" contenteditable="true" data-field="identity_number">${identity}</span></div>
                    <div class="line cv-field-row pair"><span class="k">Language</span><span class="cv-value editable" contenteditable="true" data-field="language">${language}</span></div>
                    <div class="line cv-field-row pair"><span class="k">Lang. level</span><span class="cv-value editable" contenteditable="true" data-field="language_level">${languageLevel}</span></div>
                    <div class="line cv-field-row pair"><span class="k">City</span><span>${markMissing(city)}</span></div>
                    <div class="line cv-field-row pair"><span class="k">Country</span><span>${markMissing(country)}</span></div>
                    <div class="side-title">Education</div>
                    <div class="line cv-field-row"><span class="cv-value editable" contenteditable="true" data-field="education_level">${educationLevel}</span></div>
                </aside>
                <main class="right cv-panel">
                    <div class="cv-section">
                        <div class="main-title">Summary</div>
                        <div class="line cv-field-row pair"><span class="k">Qualification</span><span class="cv-value editable" contenteditable="true" data-field="qualification">${qualification}</span></div>
                        <div class="line cv-field-row pair"><span class="k">Skills</span><span class="cv-value editable" contenteditable="true" data-field="skills">${skills}</span></div>
                    </div>
                    <div class="cv-section">
                        <div class="main-title">Work experience</div>
                        <div class="line cv-field-row pair"><span class="k">Total (years)</span><span class="cv-value editable" contenteditable="true" data-field="work_experience">${workExperience}</span></div>
                        <div class="line cv-field-row pair"><span class="k">Local</span><span class="cv-value editable" contenteditable="true" data-field="local_experience">${localExperience}</span></div>
                        <div class="line cv-field-row pair"><span class="k">Abroad</span><span class="cv-value editable" contenteditable="true" data-field="abroad_experience">${abroadExperience}</span></div>
                    </div>
                    <div class="cv-section">
                        <div class="main-title">Duties & responsibilities</div>
                        <div class="cv-duties">
                            <div class="cv-duty-row cv-field-row pair"><span class="k">Training & duties</span><span class="cv-value editable" contenteditable="true" data-field="training_notes">${trainingNotes}</span></div>
                            <div class="cv-duty-row cv-field-row pair"><span class="k">Contract duration</span><span class="cv-value editable" contenteditable="true" data-field="contract_duration">${contractDuration}</span></div>
                            <div class="cv-duty-row cv-field-row pair"><span class="k">Working hours</span><span class="cv-value editable" contenteditable="true" data-field="working_hours">${workingHours}</span></div>
                            <div class="cv-duty-row cv-field-row pair"><span class="k">Salary</span><span class="cv-value editable" contenteditable="true" data-field="salary">${salary}</span></div>
                        </div>
                    </div>
                    <div class="cv-section">
                        <div class="main-title">Record snapshot</div>
                        <div class="line cv-field-row pair"><span class="k">Status</span><span>${markMissing(workerStatus)}</span></div>
                        <div class="line cv-field-row pair"><span class="k">Passport expiry</span><span>${markMissing(passportExpiry)}</span></div>
                        <div class="line cv-field-row pair"><span class="k">Medical no.</span><span>${markMissing(medicalNumber)}</span></div>
                    </div>
                    <div class="note">Ratib Pro Indonesia · CV preview — click fields to edit, then Save To System</div>
                </main>
            </div>
        </div>
    `;
}

window.closeEmptyCvModal = function() {
    const modal = document.getElementById('emptyCvModal');
    if (!modal) return;
    modal.classList.remove('show');
    document.body.style.overflow = '';
};

window.resetEmptyCvModal = function() {
    const modal = document.getElementById('emptyCvModal');
    const sheet = document.getElementById('emptyCvSheet');
    if (!modal || !sheet) return;
    sheet.innerHTML = modal.getAttribute('data-initial-html') || '';
    window.toggleEmptyCvMissingOnly(false);
};

window.printEmptyCvModal = function() {
    const sheet = document.getElementById('emptyCvSheet');
    if (!sheet) return;
    const printWindow = window.open('', '_blank', 'width=900,height=1200');
    if (!printWindow) return;
    printWindow.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Worker CV</title>
        <style>
            body{margin:0;background:#fff;font-family:'Segoe UI',system-ui,Georgia,serif;color:#152838}
            .page.cv-page{max-width:900px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden}
            .cv-accent-top{height:5px;background:linear-gradient(90deg,#1a5f8a,#2e8fc9,#1a5f8a)}
            .header{background:linear-gradient(180deg,#d4ebfb 0%,#b9dcf3 100%);color:#062438;padding:18px 22px 16px;text-align:center;border-bottom:1px solid #8fc0e0}
            .header h1{margin:0;font-size:22px;letter-spacing:.08em;font-weight:800;text-transform:uppercase;line-height:1.12;font-family:Georgia,'Times New Roman',serif}
            .header h2{margin:10px 0 0;font-size:13px;font-weight:600;color:#134060;line-height:1.45}
            .grid{display:grid;grid-template-columns:minmax(0,300px) minmax(0,1fr)}
            .cv-panel{padding:18px 20px}
            .left{background:linear-gradient(180deg,#fbfdff 0%,#f3f8fc 100%);border-right:1px solid #cfe3f0}
            .right{background:linear-gradient(180deg,#f5fafd 0%,#eef6fb 100%)}
            .photo{aspect-ratio:3/4;max-height:260px;width:100%;border-radius:8px;border:1px solid #9dc4e0;background:#e8f4fc;display:flex;align-items:center;justify-content:center;color:#4a7aa3;font-weight:800;font-size:13px;letter-spacing:.12em;overflow:hidden}
            .photo img{width:100%;height:100%;object-fit:cover;display:block}
            .side-title{background:linear-gradient(90deg,#1e6ead,#2b8cc9);color:#fff;font-size:12px;font-weight:800;padding:8px 12px;margin:18px 0 10px;border-radius:4px;letter-spacing:.06em;text-transform:uppercase}
            .main-title{color:#0d4a73;font-size:13px;font-weight:800;margin:20px 0 10px;padding:8px 0 6px;border-bottom:2px solid #8ec5e8;letter-spacing:.04em;text-transform:uppercase}
            .line.cv-field-row{padding:8px 2px;font-size:13px;line-height:1.4;border-bottom:1px solid #e2ecf5}
            .pair{display:grid;grid-template-columns:minmax(0,40%) minmax(0,60%);gap:10px 14px;align-items:start}
            .pair .k{font-weight:700;color:#2a5270;font-size:12px;padding-top:3px}
            .cv-duties{border:1px solid #cfe3f2;border-radius:8px;overflow:hidden;background:#fafdff;margin-top:6px}
            .cv-duty-row.cv-field-row{padding:10px 12px;border-bottom:1px solid #e2ecf5}
            .cv-duty-row.cv-field-row:last-child{border-bottom:0}
            .note{color:#6a7d8f;font-size:11px;margin-top:18px;text-align:right}
            @media print{.page{box-shadow:none}}
        </style>
    </head><body>${sheet.innerHTML}</body></html>`);
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
};

window.saveEmptyCvModal = async function() {
    const modal = document.getElementById('emptyCvModal');
    const sheet = document.getElementById('emptyCvSheet');
    if (!modal || !sheet) return;
    const workerId = modal.getAttribute('data-worker-id');
    if (!workerId) return;

    const payload = {};
    const allowedCvFields = new Set([
        'full_name',
        'job_title',
        'phone',
        'email',
        'nationality',
        'passport_number',
        'identity_number',
        'date_of_birth',
        'birth_date',
        'marital_status',
        'address',
        'place_of_birth',
        'language',
        'language_level',
        'education_level',
        'work_experience',
        'local_experience',
        'abroad_experience',
        'qualification',
        'skills',
        'training_notes',
        'personal_photo_url',
        'contract_duration',
        'working_hours',
        'salary'
    ]);
    const isIsoDate = (value) => /^\d{4}-\d{2}-\d{2}$/.test(value);
    const normalizeValue = (field, rawValue) => {
        const value = String(rawValue || '').trim();
        if (!value || /^_+$/.test(value)) return null;
        if (!allowedCvFields.has(field)) return null;
        if ((field === 'date_of_birth' || field === 'birth_date') && !isIsoDate(value)) return null;
        if (field === 'email' && value !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return null;
        return value;
    };
    const editableFields = sheet.querySelectorAll('[data-field]');
    editableFields.forEach((el) => {
        const field = String(el.getAttribute('data-field') || '').trim();
        if (!field) return;
        const value = normalizeValue(field, el.textContent || '');
        if (value === null) return;
        payload[field] = value;
    });
    if (payload.date_of_birth && !payload.birth_date) {
        payload.birth_date = payload.date_of_birth;
    }
    const photoUrl = modal.getAttribute('data-photo-url');
    if (photoUrl && String(photoUrl).trim() !== '') {
        payload.personal_photo_url = String(photoUrl).trim();
    }
    if (!Object.keys(payload).length) {
        SimpleAlert.show('No Changes', 'Nothing to save from CV.', 'info', { notification: true, autoClose: true });
        return;
    }

    try {
        const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
        const response = await fetch(`${workersApi}/core/update.php?id=${encodeURIComponent(workerId)}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok || !result?.success) {
            throw new Error(result?.message || 'Failed to save CV changes');
        }

        SimpleAlert.show('Saved', 'CV edits were saved to worker data.', 'success', { notification: true, autoClose: true });
        window.toggleEmptyCvMissingOnly(false);
        modal.setAttribute('data-initial-html', sheet.innerHTML);
        if (window.workerTable) {
            window.workerTable.loadWorkers();
            window.workerTable.loadStats();
        }
    } catch (error) {
        debug.error('Save CV error:', error);
        SimpleAlert.show('Save Failed', String(error?.message || error), 'danger', { notification: true });
    }
};

window.uploadEmptyCvPhoto = async function(file) {
    const modal = document.getElementById('emptyCvModal');
    if (!modal || !file) return;
    try {
        const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
        const workerId = modal.getAttribute('data-worker-id');
        if (!workerId) {
            throw new Error('Worker ID is missing');
        }
        const formData = new FormData();
        formData.append('file', file);
        formData.append('id', workerId);
        const response = await fetch(`${workersApi}/upload-profile-photo.php`, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        const uploadedPath = result?.data?.path || '';
        if (!response.ok || !result?.success || !uploadedPath) {
            throw new Error(result?.message || 'Photo upload failed');
        }
        const photoUrl = uploadedPath;
        modal.setAttribute('data-photo-url', photoUrl);
        const preview = document.getElementById('emptyCvPhotoPreview');
        const placeholder = document.getElementById('emptyCvPhotoPlaceholder');
        if (preview) {
            preview.src = photoUrl;
            preview.style.display = 'block';
        }
        if (placeholder) placeholder.style.display = 'none';
        SimpleAlert.show('Photo Uploaded', 'Photo is ready. Click Save To System.', 'success', { notification: true, autoClose: true });
    } catch (error) {
        debug.error('CV photo upload error:', error);
        SimpleAlert.show('Upload Failed', String(error?.message || error), 'danger', { notification: true });
    }
};

window.toggleEmptyCvMissingOnly = function(enabled) {
    const modal = document.getElementById('emptyCvModal');
    const sheet = document.getElementById('emptyCvSheet');
    if (!modal || !sheet) return;
    modal.setAttribute('data-missing-only', enabled ? '1' : '0');
    const rows = sheet.querySelectorAll('.cv-field-row');
    rows.forEach((row) => {
        const hasMissing = !!row.querySelector('.missing-value');
        row.classList.toggle('hidden-by-missing-filter', enabled && !hasMissing);
    });
};

window.showEmptyCv = async function(workerId) {
    debug.log('Show Empty CV called with ID:', workerId);
    try {
        const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
        const response = await fetch(`${workersApi}/core/get.php?id=${workerId}`);
        const data = await response.json();
        const worker = data?.success && data?.data?.workers?.length ? data.data.workers[0] : null;
        if (!worker) return;

        const modal = ensureEmptyCvModal();
        const sheet = document.getElementById('emptyCvSheet');
        if (!sheet) return;

        const html = buildEmptyCvHtml(worker);
        modal.setAttribute('data-initial-html', html);
        modal.setAttribute('data-worker-id', String(worker.id || workerId));
        modal.setAttribute('data-photo-url', String(worker.personal_photo_url || ''));
        modal.setAttribute('data-missing-only', '0');
        sheet.innerHTML = html;
        window.toggleEmptyCvMissingOnly(false);
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    } catch (error) {
        debug.error('Error loading empty CV:', error);
    }
};

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    debug.log('🚀 DOM Content Loaded - Initializing Worker Management System');
    
    if (document.getElementById('workerTableBody')) {
        window.workerTable = new WorkerTable();
        debug.log('✅ WorkerTable initialized successfully');
        
        // Load agents and subagents for forms
        window.workerTable.loadAgentsAndSubagents();
        debug.log('✅ Agents and subagents loading initiated');
        
        // Initialize documents modal functionality
        initializeDocumentsModal();
        debug.log('✅ Documents modal functionality initialized');
        
        // Initialize worker form submission
        initializeWorkerFormSubmission();
        debug.log('✅ Worker form submission initialized');
    } else {
        debug.log('❌ Worker table body not found!');
    }

});

// Initialize documents modal functionality
function initializeDocumentsModal() {
    // Close modal handlers
    const closeBtn = document.getElementById('closeDocumentsModal');
    const modal = document.getElementById('documentsModal');
    const closeBtnX = modal?.querySelector('.close-btn');
    
    if (closeBtn) {
        closeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            debug.log('Cancel button clicked');
            
            // Close modal without confirmation
            debug.log('Closing modal without confirmation');
            closeDocumentsModal();
        });
    }
    if (closeBtnX) {
        closeBtnX.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            debug.log('X button clicked');
            
            // Close modal without confirmation
            debug.log('Closing modal without confirmation');
            closeDocumentsModal();
        });
    }
    
    // Overlay click to close
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                debug.log('Overlay clicked');
                
                // Close modal without confirmation
                debug.log('Closing modal without confirmation');
                closeDocumentsModal();
            }
        });
    }
    
    // File upload handlers
    initializeFileUploads();
    
    // Form submission handler
    const documentsForm = document.getElementById('documentsForm');
    if (documentsForm) {
        documentsForm.addEventListener('submit', handleDocumentsSubmit);
        
        // Add event listeners for progress tracking
        const documentTypes = ['identity', 'passport', 'police', 'medical', 'visa', 'ticket', 'trainingCertificate'];
        documentTypes.forEach(type => {
            const numberField = document.getElementById(type + 'Number');
            const dateField = document.getElementById(type + 'Date');
            const statusField = document.getElementById(type + 'Status');
            
            if (numberField) numberField.addEventListener('input', updateDocumentProgress);
            if (dateField) dateField.addEventListener('change', updateDocumentProgress);
            if (statusField) statusField.addEventListener('change', updateDocumentProgress);
        });
    }
}

// Initialize worker form submission
function initializeWorkerFormSubmission() {
    const workerForm = document.getElementById('workerForm');
    if (workerForm) {
        workerForm.addEventListener('submit', (e) => {
            e.preventDefault();
            debug.log('Worker form submitted');
            
            // Call the save worker function
            if (window.saveWorker) {
                window.saveWorker();
            } else {
                debug.error('saveWorker function not found!');
            }
        });
        
        debug.log('✅ Worker form submit event listener added');
    } else {
        debug.error('❌ Worker form not found!');
    }
}

// Initialize file upload functionality
function initializeFileUploads() {
    const fileBtns = document.querySelectorAll('.file-btn');
    
    fileBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = btn.getAttribute('data-target');
            const fileInput = document.getElementById(targetId);
            if (fileInput) {
                fileInput.click();
            }
        });
    });
    
    // Handle file selection
    const fileInputs = document.querySelectorAll('.file-input');
    fileInputs.forEach(input => {
        input.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const fileName = file.name;
                const currentFileElement = document.getElementById(e.target.id.replace('File', 'CurrentFile'));
                if (currentFileElement) {
                    // Upload the file immediately
                    uploadFile(file, e.target.id.replace('File', ''));
                }
            }
        });
    });
}

// Close documents modal
function closeDocumentsModal() {
    debug.log('Closing documents modal');
    
    // Skip unsaved changes confirmation - close directly
    
    debug.log('Closing modal');
    const modal = document.getElementById('documentsModal');
    if (modal) {
        modal.classList.remove('modal-visible', 'show');
        
        // Reset form
        const form = document.getElementById('documentsForm');
        if (form) {
            form.reset();
        }
    }
}

// Check if form has unsaved changes
function hasUnsavedChanges() {
    const form = document.getElementById('documentsForm');
    if (!form) return false;
    
    // Check if any input has been modified
    const inputs = form.querySelectorAll('input[type="text"], input[type="date"], select');
    for (let input of inputs) {
        if (input.value && input.value.trim() !== '') {
            return true;
        }
    }
    
    // Check if any file has been selected
    const fileInputs = form.querySelectorAll('input[type="file"]');
    for (let fileInput of fileInputs) {
        if (fileInput.files && fileInput.files.length > 0) {
            return true;
        }
    }
    
    return false;
}

// Handle documents form submission
async function handleDocumentsSubmit(event) {
    event.preventDefault();
    debug.log('Saving documents...');
    
    const form = document.getElementById('documentsForm');
    if (!form) return;
    
    const formData = new FormData(form);
    const workerId = formData.get('worker_id');
    
    debug.log('Form data worker_id:', workerId);
    debug.log('All form data:', Object.fromEntries(formData));
    
    if (!workerId) {
        debug.error('Worker ID is required');
        return;
    }
    
    // Save without confirmation dialog
    
    // Show loading state
    const saveBtn = form.querySelector('.btn-save');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;
    
    try {
        const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
        const response = await fetch(`${workersApi}/update-documents.php`, {
            method: 'POST',
            body: formData
        });
        
        if (response.ok) {
            const result = await response.json();
            if (result.success) {
                debug.log('✅ Documents saved successfully');
                // Show success alert
                showNotification('Documents saved successfully!', 'success');
                
                // Close modal and refresh table
                closeDocumentsModal();
                if (window.workerTable) {
                    await window.workerTable.loadWorkers();
                }
            } else {
                debug.log('❌ Failed to save documents:', result.message);
                debug.error('Failed to save documents:', result.message);
                // Show error alert
                showNotification(result.message || 'Failed to save documents. Please try again.', 'error');
            }
        } else {
            const errorText = await response.text();
            throw new Error(`HTTP ${response.status}: ${response.statusText} - ${errorText}`);
        }
    } catch (error) {
        debug.error('Error saving documents:', error);
        // Show error alert
        showNotification('Error saving documents: ' + error.message, 'error');
    } finally {
        // Restore button state
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    }
}

// View document function
window.viewDocument = function(fileName) {
    debug.log('View document called with fileName:', fileName);
    
    if (fileName && fileName !== '') {
        // Files are stored in subdirectories by document type
        // Try to determine document type from filename or search all subdirectories
        const subdirs = ['identity', 'passport', 'police', 'medical', 'visa', 'ticket', 'training_certificate'];
        let found = false;
        let checkedCount = 0;
        
        // Try each subdirectory
        subdirs.forEach(subdir => {
            const subPath = `../uploads/documents/${subdir}/${fileName}`;
            debug.log('Trying file path:', subPath);
            
            fetch(subPath, { method: 'HEAD' })
                .then(response => {
                    checkedCount++;
                    if (response.ok && !found) {
                        found = true;
                        debug.log('File found in subdirectory:', subPath);
                        showDocumentInModal(subPath);
                    }
                })
                .catch(error => {
                    checkedCount++;
                    debug.log('Error checking subdirectory:', subdir, error);
                });
        });
        
        // If not found in subdirectories after all checks, show error
        setTimeout(() => {
            if (!found && checkedCount >= subdirs.length) {
                debug.warn('File not found in any subdirectory:', fileName);
                SimpleAlert.show('File Not Found', 'File not found: ' + fileName, 'warning');
            }
        }, 2000);
        
    } else {
        debug.warn('No document available to view');
        SimpleAlert.show('No File', 'No file specified to view', 'info');
    }
};

function checkSubdirectories(fileName) {
    const subdirs = ['identity', 'passport', 'police', 'medical', 'visa', 'ticket', 'training_certificate'];
    let found = false;
    let checkedCount = 0;
    
    subdirs.forEach(subdir => {
        fetch(`../uploads/documents/${subdir}/${fileName}`, { method: 'HEAD' })
            .then(response => {
                checkedCount++;
                if (response.ok && !found) {
                    debug.log(`File found in ${subdir} directory`);
                    found = true;
                    showDocumentInModal(`../uploads/documents/${subdir}/${fileName}`);
                } else if (checkedCount === subdirs.length && !found) {
                    showFileNotFoundError(fileName);
                }
            })
            .catch(error => {
                checkedCount++;
                debug.log(`File not found in ${subdir}:`, error);
                if (checkedCount === subdirs.length && !found) {
                    showFileNotFoundError(fileName);
                }
            });
    });
}

function showFileNotFoundError(fileName) {
    // Don't show error, just close the modal
    const modal = document.getElementById('documentViewerModal');
    if (modal) {
        modal.classList.remove('modal-visible', 'show');
    }
                        debug.warn('File not found: ' + fileName);
}

function showDocumentInModal(fileUrl) {
    debug.log('Opening document:', fileUrl);
    
    // First check if file exists before showing modal
    fetch(fileUrl, { method: 'HEAD' })
        .then(response => {
            if (response.ok) {
                debug.log('File found, opening:', fileUrl);
                openFileInModal(fileUrl);
            } else {
                debug.log('File not found, checking subdirectories');
                // Try to find file in subdirectories
                const fileName = fileUrl.split('/').pop();
                const subdirs = ['identity', 'passport', 'police', 'medical', 'visa', 'ticket', 'training_certificate'];
                
                let found = false;
                subdirs.forEach(subdir => {
                    if (!found) {
                        const subPath = `../uploads/documents/${subdir}/${fileName}`;
                        fetch(subPath, { method: 'HEAD' })
                            .then(response => {
                                if (response.ok && !found) {
                                    found = true;
                                    debug.log('File found in subdirectory:', subPath);
                                    openFileInModal(subPath);
                                }
                            })
                            .catch(error => {
                                debug.log('Error checking subdirectory:', subdir);
                            });
                    }
                });
                
                // If not found in subdirectories, show error
                setTimeout(() => {
                    if (!found) {
                        debug.warn('File not found: ' + fileName);
                    }
                }, 1000);
            }
        })
        .catch(error => {
            debug.log('Error checking file:', error);
            // Error loading file - no alert needed
        });
}

function openFileInModal(fileUrl) {
    debug.log('Opening file in modal:', fileUrl);
    
    // Show the document viewer modal
    const modal = document.getElementById('documentViewerModal');
    if (modal) {
        // Allow multiple calls - just update the iframe content
        debug.log('Opening file in modal (allowing multiple calls)');
        
        // Show modal immediately
        modal.classList.add('modal-visible');
        
        // Clear any existing iframe
        const existingIframe = document.getElementById('documentFrame');
        if (existingIframe) {
            existingIframe.remove();
        }
        
        // Create new iframe
        const iframe = document.createElement('iframe');
        iframe.id = 'documentFrame';
        iframe.className = 'document-viewer-iframe';
        iframe.src = fileUrl;
        
        // Add iframe to modal
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.appendChild(iframe);
        }
        
        // Wait for iframe to load
        iframe.onload = function() {
            debug.log('Iframe loaded successfully');
            modal.classList.add('show');
        };
        
        // Fallback: show modal after 1 second
        setTimeout(() => {
            if (!modal.classList.contains('show')) {
                debug.log('Fallback: showing modal');
                modal.classList.add('show');
            }
        }, 1000);
    }
}

// Helper function to get document type from file path
function getDocumentTypeFromFileName(fileUrl) {
    if (fileUrl.includes('/identity/')) return 'identity';
    if (fileUrl.includes('/passport/')) return 'passport';
    if (fileUrl.includes('/police/')) return 'police';
    if (fileUrl.includes('/medical/')) return 'medical';
    if (fileUrl.includes('/visa/')) return 'visa';
    if (fileUrl.includes('/ticket/')) return 'ticket';
    return 'identity'; // default
}

// Check if any files exist for this document type
async function checkIfAnyFilesExist(documentType, iframe) {
    try {
        debug.log('Checking if any files exist for document type:', documentType);
        
        // Fetch files from the API
        const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
        const response = await fetch(`${workersApi}/get-files.php?type=${documentType}`);
        const data = await response.json();
        
        if (data.success && data.files && data.files.length > 0) {
            debug.log('Files exist for this document type, showing first available file');
            // Show the first available file
            const firstFile = data.files[0];
            const filePath = `../uploads/documents/${documentType}/${firstFile.name}`;
            
            // Hide iframe completely before loading
            iframe.classList.add('iframe-loading');
            iframe.classList.remove('iframe-loaded');
            iframe.onload = () => {
                iframe.classList.remove('iframe-loading');
                iframe.classList.add('iframe-loaded');
            };
            iframe.src = filePath;
        } else {
            debug.log('No files exist for this document type, showing custom error page');
            // Show custom error page only when NO files exist
            const errorContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        body { 
                            background: #1a1a1a; 
                            color: #fff; 
                            font-family: Arial, sans-serif; 
                            display: flex; 
                            align-items: center; 
                            justify-content: center; 
                            height: 100vh; 
                            margin: 0;
                            text-align: center;
                        }
                        .error-container {
                            padding: 40px;
                            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                            border-radius: 15px;
                            border: 1px solid rgba(255, 255, 255, 0.1);
                            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
                        }
                        .error-icon {
                            font-size: 48px;
                            color: #f56565;
                            margin-bottom: 20px;
                        }
                        .error-title {
                            font-size: 24px;
                            font-weight: bold;
                            margin-bottom: 10px;
                            color: #ffffff;
                        }
                        .error-message {
                            font-size: 16px;
                            color: #a0aec0;
                            margin-bottom: 20px;
                        }
                        .file-info {
                            background: rgba(255, 255, 255, 0.05);
                            padding: 15px;
                            border-radius: 8px;
                            border: 1px solid rgba(255, 255, 255, 0.1);
                            font-family: monospace;
                            font-size: 14px;
                            color: #e2e8f0;
                        }
                    </style>
                </head>
                <body>
                    <div class="error-container">
                        <div class="error-icon">📁</div>
                        <div class="error-title">No Files Uploaded</div>
                        <div class="error-message">No ${documentType.toUpperCase()} documents have been uploaded yet.</div>
                        <div class="file-info">Document Type: ${documentType.toUpperCase()}</div>
                    </div>
                </body>
                </html>
            `;
            
            const blob = new Blob([errorContent], { type: 'text/html' });
            const blobUrl = URL.createObjectURL(blob);
            
            // Hide iframe completely before loading error page
            iframe.classList.add('iframe-loading');
            iframe.classList.remove('iframe-loaded');
            
            iframe.src = blobUrl;
            
            // Show iframe after loading
            setTimeout(() => {
                iframe.classList.remove('iframe-loading');
                iframe.classList.add('iframe-loaded');
            }, 200);
        }
    } catch (error) {
        debug.log('Error checking files, showing custom error page');
        const errorContent = `
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { 
                        background: #1a1a1a; 
                        color: #fff; 
                        font-family: Arial, sans-serif; 
                        display: flex; 
                        align-items: center; 
                        justify-content: center; 
                        height: 100vh; 
                        margin: 0;
                        text-align: center;
                    }
                    .error-container {
                        padding: 40px;
                        background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                        border-radius: 15px;
                        border: 1px solid rgba(255, 255, 255, 0.1);
                        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
                    }
                    .error-icon {
                        font-size: 48px;
                        color: #f56565;
                        margin-bottom: 20px;
                    }
                    .error-title {
                        font-size: 24px;
                        font-weight: bold;
                        margin-bottom: 10px;
                        color: #ffffff;
                    }
                    .error-message {
                        font-size: 16px;
                        color: #a0aec0;
                        margin-bottom: 20px;
                    }
                </style>
            </head>
            <body>
                <div class="error-container">
                    <div class="error-icon">📁</div>
                    <div class="error-title">No Files Uploaded</div>
                    <div class="error-message">No ${documentType.toUpperCase()} documents have been uploaded yet.</div>
                </div>
            </body>
            </html>
        `;
        
        const blob = new Blob([errorContent], { type: 'text/html' });
        const blobUrl = URL.createObjectURL(blob);
        
        // Hide iframe completely before loading error page
        iframe.classList.add('iframe-loading');
        iframe.classList.remove('iframe-loaded');
        
        iframe.src = blobUrl;
        
        // Show iframe after loading
        setTimeout(() => {
            iframe.classList.remove('iframe-loading');
            iframe.classList.add('iframe-loaded');
        }, 200);
    }
    
    // Add click outside to close document viewer modal
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            SimpleAlert.show('Close Viewer', 'Are you sure you want to close the document viewer?', 'warning')
                .then(confirmed => {
                    if (confirmed) {
                        debug.log('User confirmed, closing modal');
                        // Smooth close animation
                        modal.classList.remove('show', 'modal-visible');
                        setTimeout(() => {
                            // Clear the iframe source
                            const iframe = document.getElementById('documentFrame');
                            if (iframe) {
                                iframe.src = 'about:blank';
                            }
                        }, 200);
                    }
                });
        }
    });
}

// Close document viewer modal
document.addEventListener('DOMContentLoaded', function() {
    const closeBtn = document.getElementById('closeDocumentViewer');
    const modal = document.getElementById('documentViewerModal');
    
    if (closeBtn && modal) {
        closeBtn.addEventListener('click', function() {
            debug.log('Closing document viewer modal');
            
            // Show confirmation alert
            SimpleAlert.show('Close Viewer', 'Are you sure you want to close the document viewer?', 'warning')
                .then(confirmed => {
                    if (confirmed) {
                debug.log('User confirmed, closing modal');
                // Smooth close animation
                modal.classList.remove('show', 'modal-visible');
                setTimeout(() => {
                    const iframe = document.getElementById('documentFrame');
                    if (iframe) {
                        iframe.src = '';
                    }
                }, 300);
                    } else {
                        debug.log('User cancelled, keeping modal open');
                    }
                });
        });
        
        // Close on overlay click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                debug.log('Document viewer overlay clicked');
                
                // Close modal without confirmation
                debug.log('Closing modal without confirmation');
                // Smooth close animation
                modal.classList.remove('show', 'modal-visible');
                setTimeout(() => {
                    const iframe = document.getElementById('documentFrame');
                    if (iframe) {
                        iframe.src = '';
                    }
                }, 300);
            }
        });
    }
});

// View worker function
window.viewWorker = async function(workerId) {
    debug.log('View worker called with ID:', workerId);
    
    // Remove any existing view modal first
    const existingModal = document.getElementById('viewWorkerModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    try {
        // Load worker data
        const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
        const response = await fetch(`${workersApi}/core/get.php?id=${workerId}`);
        const data = await response.json();
        
        if (data.success && data.data.workers && data.data.workers.length > 0) {
            const worker = data.data.workers[0];
            
            // Create a simple view modal
            const modal = document.createElement('div');
            modal.id = 'viewWorkerModal';
            modal.className = 'modal modal-visible';
            modal.innerHTML = `
                <div class="modal-content modal-500">
                    <div class="modal-header">
                        <h2>Worker Details</h2>
                        <button type="button" class="close-btn" id="closeViewWorker">&times;</button>
                    </div>
                    <div class="modal-body">
                        ${Array.isArray(worker.government_alerts) && worker.government_alerts.length ? `
                        <div class="gov-worker-profile-alert" role="alert" style="background:#3b0d0d;color:#fecaca;padding:10px 12px;border-radius:8px;margin-bottom:12px;border:1px solid #7f1d1d;font-size:0.9rem;">
                            <strong>Government notice</strong>
                            <ul style="margin:6px 0 0 18px;padding:0;">
                                ${worker.government_alerts.map(function(a) {
                                    var m = String((a && a.message) ? a.message : '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                                    return '<li>' + m + '</li>';
                                }).join('')}
                            </ul>
                            ${worker.government_deploy_blocked ? '<p style="margin:8px 0 0 0;"><strong>Deployment blocked</strong> by labor monitoring rules.</p>' : ''}
                        </div>` : ''}
                        <div class="worker-details">
                            <p><strong>Name:</strong> ${(window.toEnglishString || window.toWesternNumerals || (v=>v))(worker.worker_name || worker.full_name || 'N/A')}</p>
                            <p><strong>ID:</strong> ${(window.toEnglishString || window.toWesternNumerals || (v=>v))(worker.formatted_id || worker.id)}</p>
                            <p><strong>Status:</strong> ${worker.status}</p>
                            <p><strong>Phone:</strong> ${(window.toEnglishString || window.toWesternNumerals || (v=>v))(worker.phone || 'N/A')}</p>
                            <p><strong>Email:</strong> ${(window.toEnglishString || window.toWesternNumerals || (v=>v))(worker.email || 'N/A')}</p>
                            <p><strong>Nationality:</strong> ${(window.toEnglishString || window.toWesternNumerals || (v=>v))(worker.nationality || 'N/A')}</p>
                            <p><strong>Created:</strong> ${(window.toEnglishString || window.toWesternNumerals || (v=>v))(worker.created_at || 'N/A')}</p>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Add close event listeners
            const closeBtn = modal.querySelector('#closeViewWorker');
            if (closeBtn) {
                closeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    debug.log('Closing view modal');
                    modal.remove();
                });
            }
            
            // Add overlay click to close
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    e.preventDefault();
                    e.stopPropagation();
                    debug.log('Closing view modal via overlay click');
                    modal.remove();
                }
            });
            
            // Add ESC key handler to close modal
            const escHandler = (e) => {
                if (e.key === 'Escape' && document.getElementById('viewWorkerModal')) {
                    e.preventDefault();
                    e.stopPropagation();
                    debug.log('Closing view modal via ESC key');
                    modal.remove();
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);
        } else {
            debug.error('Failed to load worker data');
        }
    } catch (error) {
        debug.error('Error loading worker:', error);
        // Error loading worker data - no alert needed
    }
};

// View documents function (alias for showDocuments)
window.viewDocuments = function(workerId) {
    debug.log('View documents called with ID:', workerId);
    window.showDocuments(workerId);
};

// Function to update document progress
function updateDocumentProgress() {
    const documentTypes = ['identity', 'passport', 'police', 'medical', 'visa', 'ticket', 'trainingCertificate'];
    let completedCount = 0;
    
    documentTypes.forEach(type => {
        const numberField = document.getElementById(type + 'Number');
        const dateField = document.getElementById(type + 'Date');
        const statusField = document.getElementById(type + 'Status');
        
        if (numberField && dateField && statusField) {
            const hasNumber = numberField.value.trim() !== '';
            const hasDate = dateField.value.trim() !== '';
            const hasStatus = statusField.value !== 'pending';
            
            if (hasNumber && hasDate && hasStatus) {
                completedCount++;
            }
        }
    });
    
    const progressFill = document.getElementById('documentProgressFill');
    const progressCount = document.getElementById('documentProgressCount');
    const progressPercentage = document.getElementById('documentProgressPercentage');
    
    if (progressFill && progressCount && progressPercentage) {
        const percentage = (completedCount / documentTypes.length) * 100;
        progressFill.style.setProperty('--progress-width', percentage + '%');
        progressCount.textContent = completedCount;
        progressPercentage.textContent = Math.round(percentage) + '%';
    }
}

// Global refresh function
window.refreshWorkerTable = async function() {
    if (window.workerTable && typeof window.workerTable.loadWorkers === 'function') {
        await window.workerTable.loadWorkers();
        return true;
    }
    return false;
};

// Form functions
window.closeWorkerForm = function() {
    if (window.workerTable) {
        window.workerTable.hideEditWorkerForm();
    }
};

// Edit worker function
window.editWorker = async function(workerId) {
    debug.log('Edit worker called with ID:', workerId);
    
    // Use the external form function to show edit form
    if (window.openEditWorkerForm) {
        window.openEditWorkerForm(workerId);
        
        // Add status indicator listeners after external form is shown
        setTimeout(() => {
        }, 300);
    } else {
        // Fallback to direct form handling
        try {
            // Load worker data
            const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
            const response = await fetch(`${workersApi}/core/get.php?id=${workerId}`);
            const data = await response.json();
            
            if (data.success && data.data.workers && data.data.workers.length > 0) {
                const worker = data.data.workers[0];
                debug.log('Worker data loaded:', worker);
                
                // Populate the form
                populateEditForm(worker);
                
                // Show the form
    if (window.workerTable) {
        window.workerTable.showEditWorkerForm(workerId);
                }
                
                // Add status indicator listeners after form is populated
                setTimeout(() => {
                }, 200);
            } else {
                debug.error('Failed to load worker data');
            }
        } catch (error) {
            debug.error('Error loading worker data:', error);
            // Error loading worker data - no alert needed
        }
    }
};

// Delete worker function
window.deleteWorker = async function(workerId) {
    // Show modern delete confirmation alert with Cancel and Delete buttons
    const confirmed = await SimpleAlert.show('Delete Worker', 'Are you sure you want to delete this worker? This action cannot be undone.', 'danger', {
        notification: false,
        showCancel: true,
        cancelText: 'Cancel',
        saveText: 'Delete'
    });
    if (confirmed) {
        
        try {
            const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
            const response = await fetch(`${workersApi}/delete.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ ids: [parseInt(workerId)] })
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    // Refresh history if UnifiedHistory modal is open
                    if (window.unifiedHistory) {
                        await window.unifiedHistory.refreshIfOpen();
                    }
                    
                    if (result.data && result.data.deleted_count > 0) {
                        // Worker deleted successfully - refresh table and stats
                        if (window.workerTable) {
                            window.workerTable.loadWorkers();
                            window.workerTable.loadStats(); // Refresh stats cards
                        }
                    } else {
                        // Still refresh the table and stats in case it was deleted elsewhere
                        if (window.workerTable) {
                            window.workerTable.loadWorkers();
                            window.workerTable.loadStats(); // Refresh stats cards
                        }
                    }
                } else {
                    debug.error('Failed to delete worker:', result.message);
                    SimpleAlert.show('Error', result.message || 'Failed to delete worker', 'danger');
                    // Still refresh to ensure UI is in sync
                    if (window.workerTable) {
                        window.workerTable.loadWorkers();
                        window.workerTable.loadStats();
                    }
                }
            } else {
                const errorText = await response.text();
                debug.error('HTTP Error:', response.status, response.statusText, errorText);
                SimpleAlert.show('Error', `Failed to delete worker: ${response.status} ${response.statusText}`, 'danger');
                // Still refresh to ensure UI is in sync
                if (window.workerTable) {
                    window.workerTable.loadWorkers();
                    window.workerTable.loadStats();
                }
            }
        } catch (error) {
            debug.error('Error deleting worker:', error);
            SimpleAlert.show('Error', `Error deleting worker: ${error.message}`, 'danger');
            // Still refresh to ensure UI is in sync
            if (window.workerTable) {
                window.workerTable.loadWorkers();
                window.workerTable.loadStats();
            }
        }
    }
};


// Modal functions
window.closeDeleteModal = function() {
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) {
        modal.classList.remove('modal-visible');
    }
};

window.confirmDelete = function() {
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) {
        modal.classList.remove('modal-visible');
    }
    // The actual delete will be handled by the deleteWorker function
};


window.saveWorker = async function(event) {
    try {
        if (event) {
            event.preventDefault();
        }
        
        // Prevent double execution
        if (window.isSavingWorker) {
            return;
        }
        
        // Show modern save confirmation alert
        try {
            let confirmed = false;
            if (typeof window.ModernFormAlert !== 'undefined') {
                confirmed = await window.ModernFormAlert.show(
                    'Save Worker',
                    'Are you sure you want to save this worker?',
                    'info',
                    { confirmText: 'Save', cancelText: 'Cancel' }
                );
            } else if (typeof showSaveAlert === 'function') {
                confirmed = await showSaveAlert();
            }
            if (!confirmed) {
                return;
            }
        } catch (error) {
            // Continue with save if alert fails
        }
        
        window.isSavingWorker = true;
    
    const form = document.getElementById('workerForm');
    if (!form) {
        window.isSavingWorker = false;
        return;
    }
    
    // Form validation
    const requiredFields = ['full_name', 'agent_id'];
    const missingFields = [];
    const isIndonesiaContext = window.workerTable && typeof window.workerTable.isIndonesiaProgramContext === 'function'
        ? window.workerTable.isIndonesiaProgramContext()
        : (typeof window.RATIB_IS_INDONESIA_PROGRAM === 'boolean' ? window.RATIB_IS_INDONESIA_PROGRAM : false);
    
    requiredFields.forEach(fieldName => {
        const field = form.querySelector(`[name="${fieldName}"]`);
        if (!field || !field.value.trim()) {
            missingFields.push(fieldName);
        }
    });
    
    if (missingFields.length > 0) {
        const fieldLabels = {
            'full_name': 'Full Name',
            'agent_id': 'Agent'
        };
        const missingLabels = missingFields.map(field => fieldLabels[field] || field);
        SimpleAlert.show('Validation Error', `Please fill in required fields: ${missingLabels.join(', ')}`, 'warning', { notification: true });
        window.isSavingWorker = false;
        return;
    }

    // Lifecycle required checks removed to restore previous Ratib Pro flow.
    
    const formData = new FormData(form);
    
    // Handle file uploads in main worker form
    const fileInputs = form.querySelectorAll('input[type="file"]');
    for (let input of fileInputs) {
        if (input.files && input.files.length > 0) {
            const file = input.files[0];
            const documentType = input.id.replace('File', '').replace('_file', ''); // e.g., 'identityFile' -> 'identity'
            debug.log(`Uploading file for ${documentType}:`, file.name);
            
            // Upload file immediately
            try {
                const uploadFormData = new FormData();
                uploadFormData.append('file', file);
                uploadFormData.append('documentType', documentType);
                
                const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
                const uploadResponse = await fetch(`${workersApi}/upload-file.php`, {
                    method: 'POST',
                    body: uploadFormData
                });
                
                const uploadResult = await uploadResponse.json();
                if (uploadResult.success) {
                    debug.log(`File uploaded successfully for ${documentType}:`, uploadResult.fileName);
                    // Store the filename in the form data for database storage
                    formData.append(`${documentType}_file`, uploadResult.fileName);
                } else {
                    debug.error(`Upload failed for ${documentType}:`, uploadResult.message);
                }
            } catch (error) {
                debug.error(`Upload error for ${documentType}:`, error);
            }
        }
    }
    
    // Properly handle FormData - collect all values for array fields
    const workerData = {};
    const jobTitleValues = [];
    
    // Iterate through FormData entries
    for (const [key, value] of formData.entries()) {
        if (key === 'job_title[]') {
            // Collect all job_title[] values into an array
            jobTitleValues.push(value);
        } else {
            // For other fields, use the last value (standard behavior)
            workerData[key] = value;
        }
    }
    
    // Handle multi-select job_title field - convert array to comma-separated string
    if (jobTitleValues.length > 0) {
        workerData.job_title = jobTitleValues.filter(s => s && s.trim()).join(',');
    } else if (workerData.job_title && Array.isArray(workerData.job_title)) {
        workerData.job_title = workerData.job_title.filter(s => s && s.trim()).join(',');
    } else if (workerData['job_title[]'] && Array.isArray(workerData['job_title[]'])) {
        workerData.job_title = workerData['job_title[]'].filter(s => s && s.trim()).join(',');
        delete workerData['job_title[]'];
    }

    // Remove job_title[] from workerData if it exists as a single value
    if (workerData['job_title[]'] && !Array.isArray(workerData['job_title[]'])) {
        delete workerData['job_title[]'];
    }
    
    // Also save to skills field for backward compatibility
    if (workerData.job_title && !workerData.skills) {
        workerData.skills = workerData.job_title;
    }
    
    // Handle multi-select skills field - convert array to comma-separated string
    if (workerData.skills && Array.isArray(workerData.skills)) {
        workerData.skills = workerData.skills.filter(s => s).join(',');
    } else if (workerData['skills[]'] && Array.isArray(workerData['skills[]'])) {
        workerData.skills = workerData['skills[]'].filter(s => s).join(',');
        delete workerData['skills[]'];
    }
    
    
    // CRITICAL: Ensure status field is included and mapped correctly
    const statusField = form.querySelector('[name="status"]');
    
    if (statusField) {
        // Get the form status value - try multiple methods to ensure we get it
        let formStatus = statusField.value;
        
        // If value is empty, try getting from selected option
        if (!formStatus || formStatus === '') {
            if (statusField.selectedIndex >= 0 && statusField.options[statusField.selectedIndex]) {
                formStatus = statusField.options[statusField.selectedIndex].value;
            }
        }
        
        // If still empty, try selectedOptions
        if (!formStatus || formStatus === '') {
            if (statusField.selectedOptions && statusField.selectedOptions.length > 0) {
                formStatus = statusField.selectedOptions[0].value;
            }
        }
        
        // Normalize the form status
        formStatus = String(formStatus || '').toLowerCase().trim();
        
        // Map form status to database status
        const statusMap = {
            'active': 'approved',  // Form 'active' -> DB 'approved'
            'inactive': 'inactive',
            'pending': 'pending',
            'suspended': 'suspended'
        };
        
        // Always override with the mapped status from the form field
        let mappedStatus = statusMap[formStatus];
        if (!mappedStatus && formStatus) {
            // If not in map but has a value, use it as-is
            mappedStatus = formStatus;
        } else if (!mappedStatus) {
            // If no value at all, default to pending
            mappedStatus = 'pending';
        }
        
        // Force set the status in workerData
        workerData.status = mappedStatus;
        
        debug.log('Status mapped:', { formStatus, mappedStatus, finalStatus: workerData.status });
    } else {
        debug.error('Status field not found in form!');
        // If status field not found, keep existing or default to pending
        if (!workerData.status || workerData.status === '') {
            workerData.status = 'pending';
            debug.warn('Status field not found, defaulting to pending');
        }
    }
    
    // Final verification - ensure status is set correctly
    debug.log('Final status check:', {
        status: workerData.status,
        statusType: typeof workerData.status
    });
    
    // Double-check status is in the data
    if (!workerData.status || workerData.status === '') {
        debug.error('CRITICAL: Status is missing or empty in workerData!');
        workerData.status = 'pending';
    }
    
    // Validate agent selection
    if (workerData.agent_id === '') {
        SimpleAlert.show('Validation Error', 'Please select a valid Agent', 'warning', { notification: true });
        window.isSavingWorker = false;
        return;
    }

    // Workflow-stage document fields are optional on save; completion can be tracked per stage in the UI.

    try {
        const isEdit = workerData.id && workerData.id.trim() !== '';
        
        const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
        debug.log('Sending to API:', {
            url: isEdit ? `${workersApi}/core/update.php?id=${workerData.id}` : `${workersApi}/core/create.php`,
            status: workerData.status,
            workerId: workerData.id,
            isEdit: isEdit
        });
        const apiUrl = isEdit ? 
            `${workersApi}/core/update.php?id=${workerData.id}` : 
            `${workersApi}/core/create.php`;
        
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(workerData)
        });
        
        if (response.ok) {
            const result = await response.json();
            
            if (result.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                const message = isEdit ? 'Worker updated successfully!' : 'Worker added successfully!';
                
                // Show modern success notification (non-interactive, auto-closes)
                SimpleAlert.show('Success', message, 'success', { notification: true, autoClose: true, autoCloseDelay: 1500 });
                
                // FORCE CLOSE the form completely without triggering alerts
                const formContainer = document.getElementById('workerFormContainer');
                
                if (formContainer) {
                    formContainer.classList.remove('show');
                    formContainer.classList.add('force-hidden');
                }
                
                // Reset form
                if (form) {
                    form.reset();
                }
                
                // Refresh worker table in background (don't block - alert shows immediately)
                if (window.workerTable) {
                    const timestamp = new Date().getTime();
                    const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
                    fetch(`${workersApi}/core/get.php?page=1&limit=10&t=${timestamp}`).then(() => {
                        window.workerTable.loadWorkers();
                        window.workerTable.loadStats();
                    }).catch(() => {});
                }
            } else {
                const errorMsg = `Failed to ${isEdit ? 'update' : 'add'} worker: ${result.message || 'Unknown error'}`;
                SimpleAlert.show('Error', errorMsg, 'danger', { notification: true });
            }
        } else {
            const errorText = await response.text();
            let backendMessage = '';
            try {
                const parsed = JSON.parse(errorText);
                backendMessage = parsed && parsed.message ? String(parsed.message) : '';
            } catch (parseError) {
                backendMessage = String(errorText || '').trim();
            }
            const details = backendMessage ? ` - ${backendMessage}` : '';
            throw new Error(`HTTP ${response.status}: ${response.statusText}${details}`);
        }
    } catch (error) {
        const rawMessage = String((error && error.message) || '');
        const missingPrefix = 'Missing required fields';
        if (rawMessage.includes(missingPrefix)) {
            const cleaned = rawMessage.includes(' - ')
                ? rawMessage.split(' - ').pop()
                : rawMessage;
            SimpleAlert.show('Missing Required Fields', cleaned, 'warning', { notification: true });
        } else {
            SimpleAlert.show('Error', 'Error saving worker: ' + rawMessage, 'danger', { notification: true });
        }
    } finally {
        // Reset the flag
        window.isSavingWorker = false;
    }
    } catch (outerError) {
        window.isSavingWorker = false;
    }
};

// Make functions globally available
window.showAllFilesForType = showAllFilesForType;
window.closeFilesModal = closeFilesModal;
window.printDocument = printDocument;
window.deleteDocument = deleteDocument;
window.uploadFile = uploadFile;

// Toggle select all files
window.toggleSelectAll = function(documentType) {
    const checkboxes = document.querySelectorAll('.file-select-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    debug.log('Toggle Select All - allChecked:', allChecked);
    debug.log('Total checkboxes:', checkboxes.length);
    
    // If all are checked, uncheck all. If any are unchecked, check all.
    checkboxes.forEach(checkbox => {
        checkbox.checked = !allChecked;
    });
    
    const selectAllBtn = document.querySelector('.btn-select-all');
    if (selectAllBtn) {
        // Update button icon and title based on NEW state
        const newState = !allChecked; // After toggling
        selectAllBtn.innerHTML = newState ? '<i class="fas fa-check-square"></i>' : '<i class="fas fa-square"></i>';
        selectAllBtn.title = newState ? 'Deselect All' : 'Select All';
    }
    
    debug.log('After toggle - all files checked:', !allChecked);
};

// Update Select All button state based on individual checkboxes
window.updateSelectAllButton = function() {
    const checkboxes = document.querySelectorAll('.file-select-checkbox');
    const checkedCount = document.querySelectorAll('.file-select-checkbox:checked').length;
    const totalCount = checkboxes.length;
    
    const selectAllBtn = document.querySelector('.btn-select-all');
    if (selectAllBtn) {
        if (checkedCount === 0) {
            // No files selected - show "Select All"
            selectAllBtn.innerHTML = '<i class="fas fa-square"></i>';
            selectAllBtn.title = 'Select All';
        } else if (checkedCount === totalCount) {
            // All files selected - show "Deselect All"
            selectAllBtn.innerHTML = '<i class="fas fa-check-square"></i>';
            selectAllBtn.title = 'Deselect All';
        } else {
            // Some files selected - show "Select All" (to select remaining)
            selectAllBtn.innerHTML = '<i class="fas fa-square"></i>';
            selectAllBtn.title = 'Select All';
        }
    }
};

// Delete selected files
window.deleteSelectedFiles = async function(documentType) {
    const selectedCheckboxes = document.querySelectorAll('.file-select-checkbox:checked');
    
    if (selectedCheckboxes.length === 0) {
        SimpleAlert.show('No Selection', 'Please select files to delete', 'info');
        return;
    }
    
    const confirmed = await SimpleAlert.show('Delete Files', `Are you sure you want to delete ${selectedCheckboxes.length} file(s)?`, 'warning');
    if (!confirmed) {
        return;
    }
    
    debug.log(`Deleting ${selectedCheckboxes.length} selected files`);
    
    // Delete files one by one
    for (let checkbox of selectedCheckboxes) {
        const fileName = checkbox.value;
        const fileCard = checkbox.closest('.file-card');
        
        try {
            const workersApi = window.WORKERS_API || ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || '') + '/api/workers';
            const response = await fetch(`${workersApi}/delete-document.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    fileName: fileName,
                    documentType: documentType
                })
            });
            
            const data = await response.json();
            if (data.success) {
                debug.log('File deleted:', fileName);
                if (fileCard) {
                    fileCard.remove();
                }
            } else {
                debug.error('Error deleting file:', fileName, data.message);
                SimpleAlert.show('Delete Failed', `Failed to delete ${fileName}: ${data.message}`, 'danger');
            }
        } catch (error) {
            debug.error('Error deleting file:', fileName, error);
            SimpleAlert.show('Delete Error', `Error deleting ${fileName}: ${error.message}`, 'danger');
        }
    }
    
    // Check if no files left
    const remainingFiles = document.querySelectorAll('.file-card');
    if (remainingFiles.length === 0) {
        debug.log('No files left, closing modal');
        closeFilesModal();
    }
};


