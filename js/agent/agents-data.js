/**
 * EN: Implements frontend interaction behavior in `js/agent/agents-data.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/agent/agents-data.js`.
 */
// Utility Functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Modern Alert System
class ModernAlert {
    static isAlertOpen = false;
    
    static show(options) {
        const title = options.title || '';
        const message = options.message || '';
        const isOffline = !navigator.onLine;
        
        if (isOffline) {
            const titleMatches = title.includes('Unsaved Changes') || title.includes('unsaved changes') || 
                title.includes('Discard Changes') || title.includes('Close Form') ||
                title.includes('Cancel Changes') || title.includes('Close Accounting Modal');
            const messageMatches = message.includes('unsaved changes') || message.includes('Unsaved Changes') ||
                message.includes('close without saving') || message.includes('discard') ||
                message.includes('Any unsaved changes');
            const isUnsavedAlert = titleMatches || messageMatches;
            
            if (isUnsavedAlert) {
                return Promise.resolve({ action: 'confirm' });
            }
        }
        
        // Prevent multiple alerts
        if (this.isAlertOpen) {
            return Promise.resolve({ action: 'cancel' });
        }
        
        this.isAlertOpen = true;
        
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'modern-alert-overlay';
            
            const alert = document.createElement('div');
            alert.className = 'modern-alert';
            
            const iconMap = {
                success: '✓',
                warning: '⚠',
                danger: '✕',
                info: 'ℹ'
            };
            
            alert.innerHTML = `
                <div class="modern-alert-header">
                    <div class="modern-alert-icon ${options.type || 'info'}">${iconMap[options.type] || 'ℹ'}</div>
                    <h3 class="modern-alert-title">${options.title || 'Alert'}</h3>
                </div>
                <div class="modern-alert-body">
                    ${options.message || ''}
                    ${options.input ? `<input type="text" class="modern-alert-input" placeholder="${options.inputPlaceholder || ''}" value="${options.inputValue || ''}">` : ''}
                </div>
                <div class="modern-alert-footer">
                    ${options.showCancel !== false ? `<button class="modern-alert-btn modern-alert-btn-secondary" data-action="cancel">${options.cancelText || 'Cancel'}</button>` : ''}
                    <button class="modern-alert-btn ${options.confirmClass || 'modern-alert-btn-primary'}" data-action="confirm">${options.confirmText || 'OK'}</button>
                </div>
            `;
            
            overlay.appendChild(alert);
            document.body.appendChild(overlay);
            
            // Make overlay visible and interactive
            overlay.classList.add('visible');
            
            // Focus on input if present
            const input = alert.querySelector('.modern-alert-input');
            if (input) {
                setTimeout(() => input.focus(), 100);
            }
            
            // Handle button clicks - attach directly to buttons
            const buttons = alert.querySelectorAll('[data-action]');
            buttons.forEach(btn => {
                const action = btn.dataset.action;
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    const inputValue = input ? input.value : null;
                    this.close(overlay);
                    resolve({ action, inputValue });
                });
            });
            
            // Handle Enter key for input
            if (input) {
                input.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        this.close(overlay);
                        resolve({ action: 'confirm', inputValue: input.value });
                    }
                });
            }
        });
    }
    
    static close(overlay) {
        this.isAlertOpen = false;
        overlay.classList.add('fade-out');
        setTimeout(() => overlay.remove(), 300);
    }
    
    // Convenience methods
    static async confirm(message, title = 'Confirm') {
        const isOffline = !navigator.onLine;
        
        if (isOffline) {
            // Check if this is an unsaved changes alert
            const titleMatches = title.includes('Unsaved Changes') || title.includes('unsaved changes') || 
                title.includes('Discard Changes') || title.includes('Close Form') ||
                title.includes('Cancel Changes');
            const messageMatches = message && (message.includes('unsaved changes') || message.includes('Unsaved Changes') ||
                message.includes('close without saving') || message.includes('discard') ||
                message.includes('Any unsaved changes'));
            const isUnsavedAlert = titleMatches || messageMatches;
            
            if (isUnsavedAlert) {
                // Auto-confirm (allow close) when offline
                return { action: 'confirm' };
            }
        }
        
        return await this.show({
            type: 'warning',
            title,
            message,
            confirmText: 'Yes',
            cancelText: 'No',
            confirmClass: 'modern-alert-btn-danger'
        });
    }
    
    static async prompt(message, title = 'Input Required', placeholder = 'Enter value') {
        return await this.show({
            type: 'info',
            title,
            message,
            input: true,
            inputPlaceholder: placeholder,
            confirmText: 'Submit',
            cancelText: 'Cancel'
        });
    }
    
    static async success(message, title = 'Success') {
        return await this.show({
            type: 'success',
            title,
            message,
            showCancel: false,
            confirmText: 'OK'
        });
    }
    
    static async error(message, title = 'Error') {
        return await this.show({
            type: 'danger',
            title,
            message,
            showCancel: false,
            confirmText: 'OK'
        });
    }
    
    // Form validation helper
    static async validateForm(formData, rules) {
        for (const [field, rule] of Object.entries(rules)) {
            const value = formData[field];
            
            if (rule.required && (!value || value.trim() === '')) {
                await this.error(`${rule.label || field} is required.`, 'Validation Error');
                return false;
            }
            
            if (value && rule.pattern && !rule.pattern.test(value)) {
                await this.error(`${rule.label || field} format is invalid.`, 'Validation Error');
                return false;
            }
            
            if (value && rule.minLength && value.length < rule.minLength) {
                await this.error(`${rule.label || field} must be at least ${rule.minLength} characters.`, 'Validation Error');
                return false;
            }
        }
        return true;
    }
}

// Selected agents tracking
let selectedAgents = new Set();

// API Configuration
const API = {
    base: ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '')) + '/api/agents',
    endpoints: {
        get: '/get.php',
        create: '/create.php',
        update: '/update.php',
        delete: '/delete.php',
        bulk: '/bulk-update.php',
        stats: '/stats.php'
    }
};

// State Management
const state = {
    currentPage: 1,
    itemsPerPage: 5,
    filters: {
        page: 1,
        limit: 5,
        status: '',
        search: ''
    },
    stats: {
        total: 0,
        active: 0,
        inactive: 0
    },
    filteredData: [],
    pagination: {}
};

// API Handlers
const api = {
    async get(filters = {}) {
        try {
            // If requesting a single agent
            if (filters.id) {
                const response = await fetch(`${API.base}${API.endpoints.get}?id=${filters.id}`, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    // Try to get error message from response
                    let errorMessage = `HTTP error! status: ${response.status}`;
                    try {
                        const text = await response.text();
                        if (text && text.trim() !== '') {
                            try {
                                const errorData = JSON.parse(text);
                                if (errorData.message) {
                                    errorMessage = errorData.message;
                                }
                            } catch (e) {
                                // Not JSON, use text as error message
                                errorMessage = text.substring(0, 200); // Limit length
                            }
                        }
                    } catch (e) {
                        // Use default error message
                    }
                    throw new Error(errorMessage);
                }

                const text = await response.text();
                if (!text || text.trim() === '') {
                    throw new Error('Empty response from server');
                }
                
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response from server: ' + text.substring(0, 100));
                }
            }

            // For list view
            const params = new URLSearchParams({ 
                ...state.filters, 
                ...filters 
            }).toString();
            
            const response = await fetch(`${API.base}${API.endpoints.get}?${params}`, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            // Read response text first (can only read once)
            const text = await response.text();
            
            if (!response.ok) {
                // Try to get error message from response
                let errorMessage = `HTTP error! status: ${response.status}`;
                
                if (text && text.trim() !== '') {
                    try {
                        const errorData = JSON.parse(text);
                        if (errorData.message) {
                            errorMessage = errorData.message;
                        } else if (errorData.error) {
                            errorMessage = errorData.error;
                        } else {
                            errorMessage = JSON.stringify(errorData);
                        }
                    } catch (e) {
                        // Not JSON, use text as error message
                        errorMessage = text.substring(0, 500); // Limit length
                    }
                }
                
                throw new Error(errorMessage);
            }
            
            if (!text || text.trim() === '') {
                throw new Error('Empty response from server');
            }
            
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid JSON response from server: ' + text.substring(0, 100));
            }
        } catch (error) {
            throw error;
        }
    },

    async create(data) {
        try {
            const response = await fetch(`${API.base}${API.endpoints.create}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const text = await response.text();
            
            if (!response.ok) {
                let errorMessage = `HTTP error! status: ${response.status}`;
                if (text && text.trim() !== '') {
                    try {
                        const errorData = JSON.parse(text);
                        errorMessage = errorData.message || errorData.error || errorMessage;
                    } catch (e) {
                        errorMessage = text.substring(0, 200);
                    }
                }
                throw new Error(errorMessage);
            }

            if (!text || text.trim() === '') {
                throw new Error('Empty response from server');
            }

            const result = JSON.parse(text);
            if (!result.success) {
                throw new Error(result.message || 'Failed to create agent');
            }

            return result;
        } catch (error) {
            throw error;
        }
    },

    async update(id, data) {
        try {
            const response = await fetch(`${API.base}${API.endpoints.update}?id=${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const text = await response.text();
            
            if (!response.ok) {
                let errorMessage = `HTTP error! status: ${response.status}`;
                if (text && text.trim() !== '') {
                    try {
                        const errorData = JSON.parse(text);
                        errorMessage = errorData.message || errorData.error || errorMessage;
                    } catch (e) {
                        errorMessage = text.substring(0, 200);
                    }
                }
                throw new Error(errorMessage);
            }

            if (!text || text.trim() === '') {
                throw new Error('Empty response from server');
            }

            const result = JSON.parse(text);
            if (!result.success) {
                throw new Error(result.message || 'Failed to update agent');
            }

            // Immediately reload data after successful update
            await agentManager.load(state.filters);
            return result;
        } catch (error) {
            throw error;
        }
    },

    async delete(id) {
        try {
            const response = await fetch(`${API.base}${API.endpoints.delete}?id=${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json'
                }
            });

            const text = await response.text();
            
            if (!response.ok) {
                let errorMessage = `HTTP error! status: ${response.status}`;
                if (text && text.trim() !== '') {
                    try {
                        const errorData = JSON.parse(text);
                        errorMessage = errorData.message || errorData.error || errorMessage;
                    } catch (e) {
                        errorMessage = text.substring(0, 200);
                    }
                }
                throw new Error(errorMessage);
            }

            if (!text || text.trim() === '') {
                throw new Error('Empty response from server');
            }

            const data = JSON.parse(text);
            if (!data.success) {
                throw new Error(data.message || 'Failed to delete agent');
            }

            // Immediately reload data after successful deletion
            await agentManager.load(state.filters);
            return data;
        } catch (error) {
            throw error;
        }
    },

    async bulk(data) {
        try {
            const response = await fetch(`${API.base}${API.endpoints.bulk}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const text = await response.text();
            
            if (!response.ok) {
                let errorMessage = `HTTP error! status: ${response.status}`;
                if (text && text.trim() !== '') {
                    try {
                        const errorData = JSON.parse(text);
                        errorMessage = errorData.message || errorData.error || errorMessage;
                    } catch (e) {
                        errorMessage = text.substring(0, 200);
                    }
                }
                throw new Error(errorMessage);
            }

            if (!text || text.trim() === '') {
                throw new Error('Empty response from server');
            }

            const result = JSON.parse(text);
            if (!result.success) {
                throw new Error(result.message || 'Bulk action failed');
            }

            return result;
        } catch (error) {
            throw error;
        }
    },

    async getStats() {
        try {
            const response = await fetch(`${API.base}${API.endpoints.stats}`, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            const text = await response.text();
            
            if (!response.ok) {
                // Try to get error message from response
                let errorMessage = `HTTP error! status: ${response.status}`;
                
                if (text && text.trim() !== '') {
                    try {
                        const errorData = JSON.parse(text);
                        if (errorData.message) {
                            errorMessage = errorData.message;
                        } else if (errorData.error) {
                            errorMessage = errorData.error;
                        } else {
                            errorMessage = JSON.stringify(errorData);
                        }
                    } catch (e) {
                        // Not JSON, use text as error message
                        errorMessage = text.substring(0, 500); // Limit length
                    }
                }
                
                throw new Error(errorMessage);
            }
            
            if (!text || text.trim() === '') {
                throw new Error('Empty response from server');
            }
            
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid JSON response from server: ' + text.substring(0, 100));
            }
        } catch (error) {
            throw error;
        }
    }
};

// UI Handlers
const ui = {
    showLoading() {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) overlay.classList.add('show');
    },

    hideLoading() {
        const overlay = document.querySelector('.loading-overlay');
        if (overlay) overlay.classList.remove('show');
    },

    showModal(modal) {
        if (!modal) return;
        modal.classList.add('show', 'visible');
        modal.classList.remove('hidden');
        
        // Prevent body scroll and hide entire page
        document.body.classList.add('modal-open');
        document.documentElement.classList.add('modal-open');
        
        // Prevent modal content clicks from closing modal
        const modalContent = modal.querySelector('.modal-content');
        if (modalContent) {
            modalContent.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
    },

    async hideModal() {
        // Close modal without confirmation (used for cancel/close buttons)
        this.closeModal();
    },

    closeModal() {
        // Close modal without confirmation (used after successful save)
        document.querySelectorAll('.modal').forEach(modal => {
            modal.classList.remove('show', 'visible');
            modal.classList.add('hidden');
            
            // Reset form change tracking
            const form = modal.querySelector('form');
            if (form) {
                form.dataset.originalValues = '';
                form.dataset.hasChanges = 'false';
            }
        });
        
        // Restore body scroll
        document.body.classList.remove('modal-open');
        document.documentElement.classList.remove('modal-open');
    },

    showAddModal() {
        const modal = document.getElementById('editAgentModal');
        if (!modal) return;

        // Reset form
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
            form.querySelector('[name="id"]').value = '';
            
            // Store original (empty) values for change detection
            const formData = new FormData(form);
            const originalValues = {};
            for (const [key, value] of formData.entries()) {
                originalValues[key] = value;
            }
            form.dataset.originalValues = JSON.stringify(originalValues);
            form.dataset.hasChanges = 'false';
            form.dataset.openedTime = Date.now().toString();
        }

        // Update title
        modal.querySelector('#modalTitle').textContent = 'Add New Agent';
        
        // Show modal first, then initialize country dropdown
        this.showModal(modal);
        
        // Initialize country dropdown after modal is shown
        initializeCountryCityDropdowns().catch(err => {
            console.error('Failed to initialize country dropdown:', err);
        });
    },

    showEditModal(agent) {
        const modal = document.getElementById('editAgentModal');
        if (!modal) return;

        // Fill form
        const form = modal.querySelector('form');
        if (form && agent) {
            form.querySelector('[name="id"]').value = agent.agent_id;
            form.querySelector('[name="full_name"]').value = agent.full_name;
            form.querySelector('[name="email"]').value = agent.email;
            form.querySelector('[name="phone"]').value = agent.phone;
            form.querySelector('[name="address"]').value = agent.address || '';
            form.querySelector('[name="status"]').value = agent.status;
        }

        // Update title
        modal.querySelector('#modalTitle').textContent = 'Edit Agent';
        
        // Show modal first
        this.showModal(modal);
        
        // Initialize country dropdown FIRST (this populates the dropdown)
        initializeCountryCityDropdowns().then(() => {
            // Then set country and city values AFTER dropdown is populated
            if (form && agent) {
                const countryValue = agent.country || '';
                const cityValue = agent.city || '';
                const countrySelect = form.querySelector('[name="country"]');
                const citySelect = form.querySelector('[name="city"]');
                
                if (countrySelect) {
                    // If country exists directly, use it
                    if (countryValue) {
                        countrySelect.value = countryValue;
                        // Populate cities for the country
                        if (typeof loadCitiesByCountry === 'function') {
                            loadCitiesByCountry(countryValue, 'city');
                            // Set city value after cities are populated
                            setTimeout(() => {
                                if (citySelect && cityValue) {
                                    citySelect.value = cityValue;
                                }
                            }, 100);
                        }
                    } else if (cityValue && citySelect) {
                        // If no country but city exists, try to find country from city
                        let foundCountry = '';
                        if (typeof countriesCities !== 'undefined') {
                            for (const [country, cities] of Object.entries(countriesCities)) {
                                if (cities.includes(cityValue)) {
                                    foundCountry = country;
                                    break;
                                }
                            }
                        }
                        
                        // Set country if found
                        if (foundCountry) {
                            countrySelect.value = foundCountry;
                            // Populate cities for the country
                            if (typeof loadCitiesByCountry === 'function') {
                                loadCitiesByCountry(foundCountry, 'city');
                                // Set city value after cities are populated
                                setTimeout(() => {
                                    citySelect.value = cityValue;
                                }, 100);
                            } else {
                                citySelect.value = cityValue;
                            }
                        } else {
                            // City not found in any country, just set it
                            citySelect.value = cityValue;
                        }
                    }
                }
            }
        }).catch(err => {
            console.error('Failed to initialize country dropdown:', err);
        });

        // Store original values for change detection
        if (form && agent) {
            const formData = new FormData(form);
            const originalValues = {};
            for (const [key, value] of formData.entries()) {
                originalValues[key] = value;
            }
            form.dataset.originalValues = JSON.stringify(originalValues);
            form.dataset.hasChanges = 'false';
            form.dataset.openedTime = Date.now().toString();
        }

        this.showModal(modal);
    },

    showNotification(message, type = 'success') {
        // Remove any existing notifications
        document.querySelectorAll('.notification').forEach(note => note.remove());

        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <div class="flex-row-gap-8">
                <span class="fs-16">${type === 'success' ? '✓' : type === 'error' ? '✕' : type === 'warning' ? '⚠' : 'ℹ'}</span>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Remove after 4 seconds with fade out animation
        setTimeout(() => {
            notification.classList.add('fade-out');
            setTimeout(() => notification.remove(), 500);
        }, 4000);
    },

    showSuccess(message) {
        this.showNotification(message, 'success');
    },

    showError(message) {
        this.showNotification(message, 'error');
    },

    updateTable(agents) {
        const tbody = document.getElementById('agentTableBody');
        if (!tbody) return;

        // Clear old content FIRST to prevent flash
        tbody.innerHTML = '';
        
        // Ensure tbody is hidden during update
        tbody.classList.add('tbody-hidden');

        // Build new content directly - table is already hidden by CSS
        tbody.innerHTML = agents.map(agent => {
            // Format created date
            let createdDate = '-';
            if (agent.created_at) {
                try {
                    const date = new Date(agent.created_at);
                    if (!isNaN(date.getTime())) {
                        createdDate = date.toLocaleDateString('en-US', { 
                            year: 'numeric', 
                            month: 'short', 
                            day: 'numeric' 
                        });
                    }
                } catch (e) {
                    // If date parsing fails, use the raw value
                    createdDate = agent.created_at;
                }
            }
            
            // Limit address display - shorter on mobile, longer on desktop
            let displayAddress = agent.address || '-';
            if (displayAddress !== '-') {
                // Check if mobile viewport (approximate check)
                const isMobile = window.innerWidth <= 768;
                const maxLength = isMobile ? 30 : 40;
                if (displayAddress.length > maxLength) {
                    displayAddress = displayAddress.substring(0, maxLength) + '...';
                }
            }
            
            return `
            <tr>
                <td>${agent.formatted_id || `AG${String(agent.agent_id).padStart(4, '0')}`}</td>
                <td>${agent.full_name}</td>
                <td>${agent.email}</td>
                <td>${agent.phone}</td>
                <td>${agent.city || '-'}</td>
                <td title="${agent.address || '-'}">${displayAddress}</td>
                <td>
                    <span class="status-badge ${agent.status}">
                        ${agent.status.charAt(0).toUpperCase() + agent.status.slice(1)}
                    </span>
                </td>
                <td>${createdDate}</td>
                <td>
                    <input type="checkbox" class="agent-checkbox" 
                           value="${agent.agent_id}"
                           ${selectedAgents.has(agent.agent_id.toString()) ? 'checked' : ''}>
                </td>
                <td class="actions">
                    <button type="button" data-action="view-agent" data-id="${agent.agent_id}" 
                            class="btn-view" title="View" data-permission="view_agents">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button type="button" data-action="edit-agent" data-id="${agent.agent_id}" 
                            class="btn-edit" title="Edit" data-permission="edit_agent">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" data-action="delete-agent" data-id="${agent.agent_id}" 
                            class="btn-delete" title="Delete" data-permission="delete_agent">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        }).join('') || '<tr><td colspan="10" class="text-center">No agents found</td></tr>';
        
        // Ensure selectAll checkbox state is synced
        const selectAllCheckbox = document.querySelector('#selectAll');
        const allCheckboxes = document.querySelectorAll('.agent-checkbox');
        if (selectAllCheckbox && allCheckboxes.length > 0) {
            const allChecked = Array.from(allCheckboxes).every(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
        }

        this.updateBulkButtons();
        
        // Don't show tbody here - it will be shown in load() function after everything is ready
        
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
    },

    updateStats(stats) {
        // Update the stats in state
        Object.assign(state.stats, stats);
        
        // Update UI elements
        document.getElementById('totalAgents').textContent = stats.total || 0;
        document.getElementById('activeAgents').textContent = stats.active || 0;
        document.getElementById('inactiveAgents').textContent = stats.inactive || 0;
    },

    updateBulkButtons() {
        const hasSelected = selectedAgents.size > 0;
        const buttons = document.querySelectorAll('.bulk-activate, .bulk-deactivate, .bulk-delete');
        buttons.forEach(btn => {
            btn.disabled = !hasSelected;
            if (hasSelected) {
                btn.classList.remove('disabled');
                btn.classList.add('enabled');
            } else {
                btn.classList.remove('enabled');
                btn.classList.add('disabled');
            }
        });
    },

    showViewModal(agent) {
        const modal = document.getElementById('viewAgentModal');
        if (!modal) return;

        // Update modal content
        Object.entries({
            'viewId': agent.formatted_id,
            'viewName': agent.full_name,
            'viewEmail': agent.email,
            'viewPhone': agent.phone || '-',
            'viewCity': agent.city || '-',
            'viewAddress': agent.address || '-',
            'viewStatus': agent.status
        }).forEach(([id, value]) => {
            const element = modal.querySelector(`#${id}`);
            if (element) element.textContent = value;
        });

        this.showModal(modal);
    },

    updatePagination(pagination) {
        // Update state.pagination immediately
        state.pagination = { ...pagination };
        
        const containers = ['Top', 'Bottom'];
        containers.forEach(position => {
            // Update info text
            const start = ((pagination.page - 1) * pagination.limit) + 1;
            const end = Math.min(start + pagination.limit - 1, pagination.total);
            
            document.getElementById(`startRecord${position}`).textContent = start;
            document.getElementById(`endRecord${position}`).textContent = end;
            document.getElementById(`totalRecords${position}`).textContent = pagination.total;

            // Update page size select WITHOUT triggering change event
            const pageSizeSelect = document.getElementById(`pageSize${position}`);
            if (pageSizeSelect) {
                // Set a flag BEFORE updating value to block change event handler
                pageSizeSelect.dataset.updating = 'true';
                pageSizeSelect.value = pagination.limit;
                // Remove flag after event loop tick to ensure change event doesn't fire
                setTimeout(() => {
                    delete pageSizeSelect.dataset.updating;
                }, 0);
            }

            // Update pagination controls
            const controls = document.getElementById(`pagination${position}`);
            if (!controls) return;

            // This prevents automatic clicks when innerHTML is set
            controls.dataset.updating = 'true';

            let html = '';
            
            // First page
            const isFirstPage = pagination.page === 1;
            html += `<button class="page-btn first ${isFirstPage ? 'disabled' : ''}" 
                            ${isFirstPage ? 'disabled' : ''}>
                        <i class="fas fa-angle-double-left"></i>
                    </button>`;
            
            // Previous page
            html += `<button class="page-btn prev ${isFirstPage ? 'disabled' : ''}"
                            ${isFirstPage ? 'disabled' : ''}>
                        <i class="fas fa-angle-left"></i>
                    </button>`;

            // Page numbers
            for (let i = Math.max(1, pagination.page - 2); i <= Math.min(pagination.pages, pagination.page + 2); i++) {
                html += `<button class="page-btn ${pagination.page === i ? 'active' : ''}" 
                                data-page="${i}">${i}</button>`;
            }

            // Next page
            const isLastPage = pagination.page === pagination.pages;
            html += `<button class="page-btn next ${isLastPage ? 'disabled' : ''}"
                            ${isLastPage ? 'disabled' : ''}>
                        <i class="fas fa-angle-right"></i>
                    </button>`;
            
            // Last page
            html += `<button class="page-btn last ${isLastPage ? 'disabled' : ''}"
                            ${isLastPage ? 'disabled' : ''}>
                        <i class="fas fa-angle-double-right"></i>
                    </button>`;

            controls.innerHTML = html;
            
            // Remove flag after a short delay to allow events to settle
            setTimeout(() => {
                delete controls.dataset.updating;
            }, 100);
        });
    },

    generatePaginationButtons(pagination) {
        const current = pagination.page;
        const total = Math.ceil(pagination.total / pagination.limit);

        return `
            <button class="page-btn first" ${current === 1 ? 'disabled' : ''}>«</button>
            <button class="page-btn prev" ${current === 1 ? 'disabled' : ''}>‹</button>
            ${this.generatePageNumbers(current, total)}
            <button class="page-btn next" ${current === total ? 'disabled' : ''}>›</button>
            <button class="page-btn last" ${current === total ? 'disabled' : ''}>»</button>
        `;
    },

    generatePageNumbers(current, total) {
        const pages = [];
        const range = 2; // Show 2 pages before and after current

        for (let i = Math.max(1, current - range); i <= Math.min(total, current + range); i++) {
            pages.push(`
                <button class="page-btn number ${i === current ? 'active' : ''}" 
                        data-page="${i}">${i}</button>
            `);
        }

        return pages.join('');
    },

    hasUnsavedChanges() {
        return false;
    },

    async confirmClose() {
        return true;
    }
};

window.__agentInitialLoadDone = window.__agentInitialLoadDone || false;
window.__agentInitialLoadStarted = window.__agentInitialLoadStarted || false;
window.__agentInitialLoadTimestamp = window.__agentInitialLoadTimestamp || 0;

// Main Agent Manager
const agentManager = {
    isLoading: false, // Prevent double loading
    
    async load(filters = {}) {
        // Check if this is initial load (no filters)
        const isInitialLoad = Object.keys(filters).length === 0;
        
        if (isInitialLoad) {
            if (window.__agentInitialLoadDone) {
                return Promise.resolve();
            }
            if (window.__agentInitialLoadStarted) {
                return Promise.resolve();
            }
            window.__agentInitialLoadStarted = true;
            this.isLoading = true;
        } else {
            if (this.isLoading) {
                return Promise.resolve();
            }
            
            if (window.__agentInitialLoadDone) {
                const timestamp = window.__agentInitialLoadTimestamp || 0;
                const timeSinceInitialLoad = Date.now() - timestamp;
                
                if (timestamp > 0 && timeSinceInitialLoad < 1000) {
                    return Promise.resolve();
                }
                
                const hasDefaultFilters = (!filters.limit || filters.limit === 5) && 
                                         (!filters.page || filters.page === 1) && 
                                         !filters.search && 
                                         !filters.status;
                
                if (timestamp > 0 && timeSinceInitialLoad < 2000 && hasDefaultFilters) {
                    return Promise.resolve();
                }
            }
            
            this.isLoading = true;
        }
        
        // Now we know we're the only one running - proceed with async operations
        
        try {
            ui.showLoading();
            
            // Update filters
            state.filters = { ...state.filters, ...filters };
            
            const tableContainer = document.querySelector('.table-container');
            const tableScroll = document.querySelector('.table-scroll');
            const tbody = document.getElementById('agentTableBody');
            
            if (tableContainer && tableScroll) {
                tableContainer.classList.remove('table-ready');
                tableScroll.classList.remove('table-ready');
                tableScroll.classList.remove('scroll-ready');
                
                if (tbody) {
                    tbody.classList.add('tbody-hidden');
                }
            }
            
            // Load both data and stats
            const [response, statsResponse] = await Promise.all([
                api.get(state.filters),
                api.getStats()
            ]);
            
            if (response.success && response.data) {
                
                if (tableContainer && tableScroll) {
                    tableScroll.classList.remove('table-ready');
                    
                    tableContainer.classList.remove('show-10', 'show-25', 'show-50');
                    tableScroll.classList.remove('show-10', 'show-25', 'show-50');
                    
                    const limit = state.filters.limit || 5;
                    if (limit >= 10) {
                        tableContainer.classList.add('show-10');
                        tableScroll.classList.add('show-10');
                    }
                    if (limit >= 25) {
                        tableContainer.classList.add('show-25');
                        tableScroll.classList.add('show-25');
                    }
                    if (limit >= 50) {
                        tableContainer.classList.add('show-50');
                        tableScroll.classList.add('show-50');
                    }
                    
                    tableScroll.classList.add('scroll-ready');
                    
                    if (tbody) {
                        tbody.classList.add('tbody-hidden');
                    }
                    ui.updateTable(response.data.list);
                    
                    if (response.data.pagination) {
                        setTimeout(() => {
                            ui.updatePagination(response.data.pagination);
                        }, 100);
                    }
                    
                    if (tbody) {
                        tbody.classList.remove('tbody-hidden');
                    }
                    
                    if (statsResponse.success && statsResponse.data) {
                        ui.updateStats(statsResponse.data);
                    }
                    
                    if (isInitialLoad) {
                        window.__agentInitialLoadDone = true;
                        window.__agentInitialLoadStarted = false;
                        window.__agentInitialLoadTimestamp = Date.now();
                    }
                    
                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            tableContainer.classList.add('table-ready');
                            tableScroll.classList.add('table-ready');
                            tableScroll.classList.add('scroll-ready');
                            
                            if (isInitialLoad) {
                                agentManager.isLoading = false;
                            }
                        });
                    });
                } else {
                    // Fallback if elements not found
                    ui.updateTable(response.data.list);
                    if (response.data.pagination) {
                        ui.updatePagination(response.data.pagination);
                    }
                    // Update stats if available
                    if (statsResponse.success && statsResponse.data) {
                        ui.updateStats(statsResponse.data);
                    }
                    if (isInitialLoad) {
                        window.__agentInitialLoadDone = true;
                        window.__agentInitialLoadStarted = false;
                        window.__agentInitialLoadTimestamp = Date.now();
                        agentManager.isLoading = false;
                    }
                }
            } else {
                // If no response, update stats anyway if available
                if (statsResponse.success && statsResponse.data) {
                    ui.updateStats(statsResponse.data);
                }
                if (isInitialLoad) {
                    window.__agentInitialLoadDone = true;
                    window.__agentInitialLoadStarted = false;
                    window.__agentInitialLoadTimestamp = Date.now();
                    agentManager.isLoading = false;
                }
            }
        } catch (error) {
            const errorMessage = error.message || 'Failed to load agents';
            ui.showError(errorMessage);
            // Reset flags on error
            if (isInitialLoad) {
                window.__agentInitialLoadStarted = false;
            }
        } finally {
            // because it needs to stay true until requestAnimationFrame completes
            // to prevent race conditions. Only reset for non-initial loads.
            if (!isInitialLoad) {
                this.isLoading = false; // Reset loading flag only for non-initial loads
            }
            // For initial loads, isLoading will be reset inside requestAnimationFrame after table is shown
            ui.hideLoading();
        }
    },

    async view(id) {
        try {
            ui.showLoading();
            const response = await api.get({ id });
            
            if (response.success && response.data) {
                const agent = response.data;
                // Create and show view modal
                const viewModal = document.createElement('div');
                viewModal.className = 'modal';
                viewModal.id = 'viewAgentModal';
                
                // Format the status text safely
                const statusText = agent.status ? 
                    (agent.status.charAt(0).toUpperCase() + agent.status.slice(1)) : 
                    'Unknown';
                
                viewModal.innerHTML = `
            <div class="modal-content">
                        <span class="close">&times;</span>
                <div class="modal-header">
                    <h2>View Agent Details</h2>
                            <div class="modal-actions">
                                <button type="button" class="btn-edit" data-action="edit-agent-from-modal" data-id="${agent.agent_id}">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button type="button" class="btn-print" data-action="print-agent-details" data-id="${agent.agent_id}">
                                    <i class="fas fa-print"></i> Print
                                </button>
                </div>
                        </div>
                        <div class="agent-details">
                    <div class="detail-row">
                                <label>ID:</label>
                                <span>${agent.formatted_id || `AG${String(agent.agent_id).padStart(4, '0')}`}</span>
                    </div>
                    <div class="detail-row">
                                <label>Full Name:</label>
                                <span>${agent.full_name || '-'}</span>
                    </div>
                    <div class="detail-row">
                        <label>Email:</label>
                                <span>${agent.email || '-'}</span>
                    </div>
                    <div class="detail-row">
                        <label>Phone:</label>
                                <span>${agent.phone || '-'}</span>
                    </div>
                    <div class="detail-row">
                        <label>City:</label>
                                <span>${agent.city || '-'}</span>
                    </div>
                    <div class="detail-row">
                        <label>Address:</label>
                                <span>${agent.address || '-'}</span>
                    </div>
                    <div class="detail-row">
                        <label>Status:</label>
                                <span class="status-badge ${agent.status || 'unknown'}">
                                    ${statusText}
                                </span>
                    </div>
                            <div class="detail-row">
                                <label>Created:</label>
                                <span>${agent.created_at ? new Date(agent.created_at).toLocaleString() : '-'}</span>
                </div>
                            <div class="detail-row">
                                <label>Last Updated:</label>
                                <span>${agent.updated_at ? new Date(agent.updated_at).toLocaleString() : '-'}</span>
                </div>
            </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-close" data-action="close-modal">Close</button>
            </div>
        </div>
    `;

                // Remove any existing view modal
    const existingModal = document.getElementById('viewAgentModal');
    if (existingModal) {
        existingModal.remove();
    }

                // Add new modal to body
                document.body.appendChild(viewModal);
                
                // Show the modal
                ui.showModal(viewModal);
                
                // Add close button event listener
                viewModal.querySelector('.close').addEventListener('click', () => {
                    viewModal.remove();
                });
                
                // Add print button event listener
                const printBtn = viewModal.querySelector('.btn-print');
                if (printBtn) {
                    printBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        agentManager.printDetails(agent.agent_id);
                    });
                }
                
                // Add edit button event listener
                const editBtn = viewModal.querySelector('.btn-edit');
                if (editBtn) {
                    editBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        viewModal.remove();
                        agentManager.edit(agent.agent_id);
                    });
                }
                
                // Close on outside click
                viewModal.addEventListener('click', (e) => {
                    if (e.target === viewModal) {
                        viewModal.remove();
                    }
                });
            }
        } catch (error) {
            ui.showError('Failed to load agent details');
        } finally {
            ui.hideLoading();
        }
    },

    async edit(id) {
        try {
            ui.showLoading();
            const response = await api.get({ id });
            
            if (response.success && response.data) {
                const agent = response.data;
                ui.showEditModal(agent);
            }
        } catch (error) {
            ui.showError('Failed to load agent for editing');
        } finally {
            ui.hideLoading();
        }
    },


    async save(id, data) {
        // Show save confirmation
        const action = id ? 'update' : 'create';
        const agentName = data.full_name || data.agent_name || 'this agent';
        
        const result = await ModernAlert.confirm(
            `Are you sure you want to ${action} "${agentName}"? This will ${id ? 'update the existing agent information' : 'create a new agent record'}.`,
            `${action.charAt(0).toUpperCase() + action.slice(1)} Agent`
        );
        
        if (result.action !== 'confirm') return;
        
        try {
            ui.showLoading();
            const response = id ? 
                await api.update(id, data) : 
                await api.create(data);

            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                // Close the modal after successful save (no confirmation needed)
                ui.closeModal();
                // Reload with current filters - pass current state to avoid being treated as initial load
                await this.load({ ...state.filters });
                ui.showSuccess(`Agent ${id ? 'updated' : 'created'} successfully`);
            }
        } catch (error) {
            ui.showError(`Failed to ${id ? 'update' : 'create'} agent`);
        } finally {
            ui.hideLoading();
        }
    },

    async delete(id) {
        // Get agent name from current table data or use generic name
        let agentName = 'this agent';
        try {
            const response = await api.get({ id });
            if (response.success && response.data) {
                agentName = response.data.full_name || response.data.agent_name || 'this agent';
            }
        } catch (e) {
            // If we can't fetch, use generic name
        }
        
        const result = await ModernAlert.confirm(
            `Are you sure you want to permanently delete "${agentName}"? This action cannot be undone and will remove all associated data.`,
            'Delete Agent'
        );
        
        if (result.action !== 'confirm') return;
        
        try {
            ui.showLoading();
            const response = await api.delete(id);
            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                // Reload with current filters - pass current state to avoid being treated as initial load
                await this.load({ ...state.filters });
                ui.showSuccess('Agent deleted successfully');
            }
        } catch (error) {
            ui.showError('Failed to delete agent');
        } finally {
            ui.hideLoading();
        }
    },

    async bulkAction(action) {
        try {
            if (selectedAgents.size === 0) {
                ui.showError('Please select at least one agent');
                return;
            }

            ui.showLoading();
            // Convert Set to array and ensure IDs are strings/numbers
            const ids = Array.from(selectedAgents).map(id => String(id).trim()).filter(id => id);
            
            if (ids.length === 0) {
                ui.hideLoading();
                ui.showError('Please select at least one agent');
                return;
            }
            
            const response = await api.bulk({
                action,
                ids: ids
            });

            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                // Clear selections
                selectedAgents.clear();
                // Reload data
                await this.load(state.filters);
                // Clear checkboxes after table reload
                setTimeout(() => {
                    document.querySelectorAll('.agent-checkbox').forEach(cb => cb.checked = false);
                    const selectAllCheckbox = document.querySelector('#selectAll');
                    if (selectAllCheckbox) {
                        selectAllCheckbox.checked = false;
                    }
                }, 100);
                
                // Show success message with proper capitalization
                const actionText = action.charAt(0).toUpperCase() + action.slice(1);
                ui.showSuccess(`${actionText}d selected agents`);
            }
        } catch (error) {
            ui.showError(error.message || `Failed to ${action} agents`);
        } finally {
            ui.hideLoading();
        }
    },

    updatePagination(pagination) {
        state.pagination = pagination;
        ui.updatePagination(pagination);
    },

    async printDetails(agentId) {
        // Try to find agent in filtered data first
        let agent = state.filteredData?.find(a => a.agent_id === agentId);
        
        // If agent not found, try to get from the view modal
        if (!agent) {
            const viewModal = document.getElementById('viewAgentModal');
            if (viewModal) {
                const detailRows = viewModal.querySelectorAll('.detail-row');
                if (detailRows.length > 0) {
                    const statusBadge = detailRows[6]?.querySelector('.status-badge');
                    const statusClass = statusBadge?.classList.contains('active') ? 'active' : 'inactive';
                    
                    agent = {
                        agent_id: agentId,
                        formatted_id: detailRows[0]?.querySelector('span')?.textContent?.trim() || '',
                        full_name: detailRows[1]?.querySelector('span')?.textContent?.trim() || '',
                        email: detailRows[2]?.querySelector('span')?.textContent?.trim() || '',
                        phone: detailRows[3]?.querySelector('span')?.textContent?.trim() || '',
                        city: detailRows[4]?.querySelector('span')?.textContent?.trim() || '-',
                        address: detailRows[5]?.querySelector('span')?.textContent?.trim() || '-',
                        status: statusClass,
                        created_at: detailRows[7]?.querySelector('span')?.textContent?.trim() || '',
                        updated_at: detailRows[8]?.querySelector('span')?.textContent?.trim() || ''
                    };
                }
            }
        }
        
        // If still not found, try to fetch from API
        if (!agent) {
            try {
                const response = await api.get({ id: agentId });
                if (response.success && response.data) {
                    agent = response.data;
                }
            } catch (error) {
                // Error fetching agent for print
            }
        }
        
        if (!agent) {
            await ModernAlert.error('Agent details not found. Please try again.');
            return;
        }

        // Format status text safely
        const statusText = agent.status ? 
            (agent.status.charAt(0).toUpperCase() + agent.status.slice(1)) : 
            'Unknown';

        const printWindow = window.open('', '_blank', 'width=800,height=600');
        if (!printWindow) {
            await ModernAlert.error('Please allow pop-ups to print agent details.');
            return;
        }

        // Get the base path for CSS file
        const basePath = window.location.origin + '/ratibprogram';
        const agentCssPath = `${basePath}/css/agent/agent.css`;

        printWindow.document.write(`
            <!DOCTYPE html>
            <html class="print-window">
            <head>
                <title>Agent Details - ${agent.full_name || 'Agent'}</title>
                <meta charset="UTF-8">
                <link rel="stylesheet" href="${agentCssPath}">
            </head>
            <body>
                <div class="header">
                    <h1>Agent Details</h1>
                    <p>Generated on ${new Date().toLocaleString()}</p>
                </div>
                <div class="details-container">
                    <div class="detail-row">
                        <span class="label">ID:</span>
                        <span class="value">${agent.formatted_id || `AG${String(agent.agent_id).padStart(4, '0')}`}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Full Name:</span>
                        <span class="value">${agent.full_name || '-'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Email:</span>
                        <span class="value">${agent.email || '-'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Phone:</span>
                        <span class="value">${agent.phone || '-'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">City:</span>
                        <span class="value">${agent.city || '-'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Address:</span>
                        <span class="value">${agent.address || '-'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Status:</span>
                        <span class="status ${agent.status || 'inactive'}">
                            ${statusText}
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Created:</span>
                        <span class="value">${agent.created_at ? (typeof agent.created_at === 'string' ? agent.created_at : new Date(agent.created_at).toLocaleString()) : '-'}</span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Last Updated:</span>
                        <span class="value">${agent.updated_at ? (typeof agent.updated_at === 'string' ? agent.updated_at : new Date(agent.updated_at).toLocaleString()) : '-'}</span>
                    </div>
                </div>
                <div class="no-print">
                    <button id="printBtn">Print</button>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
        
        // Wait for content to load, then attach event listener and auto-trigger print dialog
        printWindow.onload = function() {
            const printBtn = printWindow.document.getElementById('printBtn');
            if (printBtn) {
                printBtn.addEventListener('click', () => {
                    printWindow.print();
                });
            }
            setTimeout(() => {
                printWindow.print();
            }, 250);
        };
    }
};

// Event Handlers
function setupEventListeners() {
    // Track form changes for unsaved changes detection - only if online
    document.addEventListener('input', (e) => {
        if (!navigator.onLine) {
            return; // Don't track changes when offline
        }
        const form = e.target.closest('#editAgentModal form');
        if (form && form.dataset.openedTime) {
            // Only track if form has been open for at least 1 second
            const openedTime = parseInt(form.dataset.openedTime || '0');
            const timeSinceOpen = Date.now() - openedTime;
            if (timeSinceOpen > 1000) {
                form.dataset.hasChanges = 'true';
            }
        }
    });

    // Track form changes for select elements - only if online
    document.addEventListener('change', (e) => {
        if (!navigator.onLine) {
            return; // Don't track changes when offline
        }
        const form = e.target.closest('#editAgentModal form');
        if (form && form.dataset.openedTime) {
            // Only track if form has been open for at least 1 second
            const openedTime = parseInt(form.dataset.openedTime || '0');
            const timeSinceOpen = Date.now() - openedTime;
            if (timeSinceOpen > 1000) {
                form.dataset.hasChanges = 'true';
            }
        }
    });

    // Listen for online/offline events to reset change tracking
    window.addEventListener('online', () => {
        // Network connection restored
    });

    window.addEventListener('offline', () => {
        const closingAlertModal = document.getElementById('closingAlertModal');
        if (closingAlertModal) {
            closingAlertModal.classList.add('d-none', 'offline-hidden');
        }
        
        // Also hide any ModernAlert overlays
        const modernAlertOverlays = document.querySelectorAll('.modern-alert-overlay');
        modernAlertOverlays.forEach(overlay => {
            overlay.remove();
        });
        
        // Reset all form change flags when going offline
        document.querySelectorAll('#editAgentModal form').forEach(form => {
            form.dataset.hasChanges = 'false';
        });
    });

    // Search handler
    document.querySelector('#searchInput')?.addEventListener('input', 
        debounce(e => agentManager.load({ search: e.target.value }), 300));
    
    // Status filter
    document.querySelector('.status-filter')?.addEventListener('change',
        e => agentManager.load({ status: e.target.value }));

    // Checkbox handlers
    document.addEventListener('change', e => {
        if (e.target.matches('.agent-checkbox')) {
            const agentId = String(e.target.value).trim();
            if (e.target.checked) {
                selectedAgents.add(agentId);
            } else {
                selectedAgents.delete(agentId);
                const selectAllCheckbox = document.querySelector('#selectAll');
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                }
            }
            ui.updateBulkButtons();
        }
        
        if (e.target.matches('#selectAll')) {
            const checkboxes = document.querySelectorAll('.agent-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = e.target.checked;
                const checkboxAgentId = String(checkbox.value).trim();
                if (e.target.checked) {
                    selectedAgents.add(checkboxAgentId);
                } else {
                    selectedAgents.delete(checkboxAgentId);
                }
            });
            ui.updateBulkButtons();
        }
    });

    // Form submission - use event delegation to handle dynamically created forms
    document.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!form || form.tagName !== 'FORM') {
            return;
        }
        
        // Check if this is the agent form
        const modal = form.closest('#editAgentModal');
        const isAgentForm = modal || form.classList.contains('agent-form');
        
        if (!isAgentForm) {
            return;
        }
        
        e.preventDefault();
        e.stopPropagation();
        
        try {
            // Validate form
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            // Get form data for confirmation
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            const id = data.id || null;
            const action = id ? 'update' : 'create';
            const agentName = data.full_name || 'this agent';
            
            // Show save confirmation
            const result = await ModernAlert.confirm(
                `Are you sure you want to ${action} "${agentName}"? This will ${id ? 'update the existing agent information' : 'create a new agent record'}.`,
                `${action.charAt(0).toUpperCase() + action.slice(1)} Agent`
            );
            
            if (result.action !== 'confirm') {
                return;
            }

            ui.showLoading();
            
            delete data.id;
            // Remove country field as it's not stored in database (only used for city selection)
            delete data.country;

            // Validate required fields
            const required = ['full_name', 'email', 'phone'];
            for (const field of required) {
                if (!data[field]?.trim()) {
                    throw new Error(`${field.replace('_', ' ')} is required`);
                }
            }

            // Validate email format
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
                throw new Error('Invalid email format');
            }

            // Add default status if not set
            if (!data.status) {
                data.status = 'active';
            }

            // Sending data to API
            const response = id ? 
                await api.update(id, data) : 
                await api.create(data);

            if (response.success) {
                // First reload the data
                await agentManager.load(state.filters);
                // Close the modal after successful save
                ui.closeModal();
                ui.showSuccess(`Agent ${id ? 'updated' : 'created'} successfully`);
            } else {
                throw new Error(response.message || `Failed to ${id ? 'update' : 'create'} agent`);
            }
        } catch (error) {
            ui.showError(error.message);
        } finally {
            ui.hideLoading();
        }
    });
    
    // Also handle save button clicks directly (in case form submit doesn't fire)
    document.addEventListener('click', async (e) => {
        // Skip if clicking on ModernAlert
        if (e.target.closest('.modern-alert-overlay, .modern-alert')) {
            return;
        }
        
        // Skip if clicking on cancel button (handled by separate handler)
        if (e.target.closest('.btn-cancel')) {
            return;
        }
        
        const saveBtn = e.target.closest('.btn-save');
        if (saveBtn) {
            e.preventDefault();
            e.stopPropagation();
            
            const form = saveBtn.closest('form');
            if (form) {
                // Check if this is the agent form
                const modal = form.closest('#editAgentModal');
                const isAgentForm = modal || form.classList.contains('agent-form');
                if (isAgentForm) {
                    // Trigger form submit which will be handled by the submit handler above
                    form.requestSubmit();
                }
            }
        }
    });

    // Action button handlers
    document.addEventListener('click', e => {
        // Handle pagination controls FIRST (before other action handlers)
        const pageBtn = e.target.closest('.page-btn');
        if (pageBtn) {
            const controls = pageBtn.closest('.pagination-controls');
            if (controls && controls.dataset.updating === 'true') {
                e.preventDefault();
                e.stopPropagation();
                return;
            }
            
            // Check if button is disabled (both attribute and class)
            if (pageBtn.disabled || pageBtn.classList.contains('disabled')) {
                return;
            }
            
            // These are likely automatic/programmatic and not user clicks
            if (window.__agentInitialLoadDone) {
                const timestamp = window.__agentInitialLoadTimestamp || 0;
                const timeSinceInitialLoad = Date.now() - timestamp;
                if (timestamp > 0 && timeSinceInitialLoad < 1000) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            const page = pageBtn.dataset.page;
            if (page) {
                agentManager.load({ page: parseInt(page) });
            } else if (pageBtn.classList.contains('first')) {
                agentManager.load({ page: 1 });
            } else if (pageBtn.classList.contains('last')) {
                agentManager.load({ page: state.pagination.pages || 1 });
            } else if (pageBtn.classList.contains('prev')) {
                const currentPage = state.pagination.page || 1;
                if (currentPage > 1) {
                    agentManager.load({ page: currentPage - 1 });
                }
            } else if (pageBtn.classList.contains('next')) {
                const currentPage = state.pagination.page || 1;
                const totalPages = state.pagination.pages || 1;
                if (currentPage < totalPages) {
                    agentManager.load({ page: currentPage + 1 });
                }
            }
            return;
        }
        
        const action = e.target.closest('[data-action]')?.getAttribute('data-action');
        
        if (action === 'view-agent') {
            const id = e.target.closest('[data-action]')?.getAttribute('data-id');
            if (id && agentManager) {
                agentManager.view(parseInt(id));
            }
            return;
        }
        
        if (action === 'edit-agent') {
            const id = e.target.closest('[data-action]')?.getAttribute('data-id');
            if (id && agentManager) {
                agentManager.edit(parseInt(id));
            }
            return;
        }
        
        if (action === 'delete-agent') {
            const id = e.target.closest('[data-action]')?.getAttribute('data-id');
            if (id && agentManager) {
                agentManager.delete(parseInt(id));
            }
            return;
        }
        
        if (action === 'edit-agent-from-modal') {
            const id = e.target.closest('[data-action]')?.getAttribute('data-id');
            const modal = e.target.closest('.modal');
            if (id && agentManager) {
                agentManager.edit(parseInt(id));
                if (modal) modal.remove();
            }
            return;
        }
        
        if (action === 'print-agent-details') {
            const id = e.target.closest('[data-action]')?.getAttribute('data-id');
            if (id && agentManager && agentManager.printDetails) {
                agentManager.printDetails(parseInt(id));
            }
            return;
        }
        
        if (action === 'close-modal') {
            if (ui && ui.hideModal) {
                ui.hideModal();
            }
            return;
        }
        
        if (action === 'print-page') {
            window.print();
            return;
        }
    });

    // Add New button
    const addButton = document.querySelector('.add-btn');
    if (addButton) {
        addButton.addEventListener('click', () => ui.showAddModal());
    }

    // Bulk action buttons - use event delegation (handle clicks on button or children)
    document.addEventListener('click', async (e) => {
        // Skip if clicking on ModernAlert (let it handle its own clicks)
        if (e.target.closest('.modern-alert-overlay, .modern-alert')) {
            return;
        }
        
        // Skip if clicking on cancel button (handled by separate handler)
        if (e.target.closest('.btn-cancel')) {
            return;
        }
        
        // Check if click is on bulk button or its children (icons, text)
        let btn = e.target;
        if (!btn.matches('.bulk-activate, .bulk-deactivate, .bulk-delete')) {
            btn = e.target.closest('.bulk-activate, .bulk-deactivate, .bulk-delete');
        }
        
        if (!btn) return;
        
        // Check if button is disabled
        if (btn.disabled) {
            return;
        }
        
        e.preventDefault();
        e.stopPropagation();
        
        const action = btn.classList.contains('bulk-activate') ? 'activate' :
                      btn.classList.contains('bulk-deactivate') ? 'deactivate' : 
                      'delete';
        
        // Add single confirmation dialog
        const actionMessages = {
            'activate': `Are you sure you want to activate ${selectedAgents.size} selected agent(s)? This will make them available for new assignments.`,
            'deactivate': `Are you sure you want to deactivate ${selectedAgents.size} selected agent(s)? This will prevent them from receiving new assignments.`,
            'delete': `Are you sure you want to permanently delete ${selectedAgents.size} selected agent(s)? This action cannot be undone and will remove all associated data.`
        };
        
        const result = await ModernAlert.confirm(
            actionMessages[action] || `Are you sure you want to ${action} the selected agents?`,
            `${action.charAt(0).toUpperCase() + action.slice(1)} ${selectedAgents.size} Agent(s)`
        );
        
        if (!result || result.action !== 'confirm') {
            return;
        }

        try {
            ui.showLoading();
            await agentManager.bulkAction(action);
        } catch (error) {
            ui.showError(error.message);
        } finally {
            ui.hideLoading();
        }
    });

    // Modal close buttons (X) - COMPLETELY DISABLED alerts
    document.querySelectorAll('.modal .close').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            // ALWAYS close without alert
                ui.closeModal();
        });
    });

    // Close modal on outside click - COMPLETELY DISABLED alerts
    // Prevent modal content clicks from closing
    document.addEventListener('click', async (e) => {
        // Skip if clicking on ModernAlert
        if (e.target.closest('.modern-alert-overlay, .modern-alert')) {
            return;
        }
        
        const modal = document.getElementById('editAgentModal');
        if (modal && modal.classList.contains('show') && e.target === modal && !e.target.closest('.modal-content')) {
            e.preventDefault();
            e.stopPropagation();
            
            // ALWAYS close without alert
                ui.closeModal();
        }
    });

    // Form cancel button - COMPLETELY DISABLED alerts (using event delegation)
    // Use capture phase to catch the event early, BEFORE other handlers
    document.addEventListener('click', async (e) => {
        // Check if clicked element or its parent is the cancel button FIRST, before other checks
        const cancelBtn = e.target.closest('.btn-cancel');
        
        if (cancelBtn) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            // Skip if clicking on ModernAlert (shouldn't happen, but just in case)
            if (e.target.closest('.modern-alert-overlay, .modern-alert')) {
                return;
            }
            
            // ALWAYS close without alert
                ui.closeModal();
            return;
        }
    }, true); // Use capture phase to catch the event before it bubbles

    // Page size handlers
    let isUpdatingPageSize = false; // Prevent double loading when updating both selects
    
    ['Top', 'Bottom'].forEach(position => {
        document.getElementById(`pageSize${position}`)?.addEventListener('change', e => {
            if (e.target.dataset.updating === 'true') {
                return;
            }
            
            // Prevent double loading when updating both selects
            if (isUpdatingPageSize) {
                return;
            }
            
            const newLimit = parseInt(e.target.value);
            
            // Check if the value actually changed
            const currentLimit = state.filters.limit || 5;
            if (newLimit === currentLimit) {
                return;
            }
            
            // Update both selects without triggering events
            isUpdatingPageSize = true;
            const topSelect = document.getElementById(`pageSizeTop`);
            const bottomSelect = document.getElementById(`pageSizeBottom`);
            if (topSelect) {
                topSelect.dataset.updating = 'true';
                topSelect.value = newLimit;
                setTimeout(() => delete topSelect.dataset.updating, 0);
            }
            if (bottomSelect) {
                bottomSelect.dataset.updating = 'true';
                bottomSelect.value = newLimit;
                setTimeout(() => delete bottomSelect.dataset.updating, 0);
            }
            isUpdatingPageSize = false;
            
            // Load with new limit
            agentManager.load({ limit: newLimit, page: 1 });
        });
    });
}

function getAdminApiBase() {
    const appApiBase = (window.APP_CONFIG && window.APP_CONFIG.apiBase) || '';
    if (appApiBase) {
        return appApiBase.replace(/\/+$/, '');
    }
    const basePath = (window.BASE_PATH || document.documentElement.getAttribute('data-base-url') || '').replace(/\/+$/, '');
    return basePath ? `${basePath}/api` : '/api';
}

// Country and City Functions - Using API
async function loadCitiesByCountry(country, cityFieldId) {
    const citySelect = document.getElementById(cityFieldId);
    if (!citySelect) return;
    
    // Clear existing options
    citySelect.innerHTML = '<option value="">Select Country First</option>';
    
    if (!country) return;
    
    try {
        const apiBase = getAdminApiBase();
        const url = `${apiBase}/admin/get_countries_cities.php?action=cities&country=${encodeURIComponent(country)}`;
        
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
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                citySelect.appendChild(option);
            });
        } else {
            citySelect.innerHTML = '<option value="">No cities available for this country</option>';
        }
    } catch (error) {
        console.error('Failed to load cities:', error);
        citySelect.innerHTML = '<option value="">Error loading cities</option>';
    }
}

// Populate country dropdown on page load from API
async function populateCountryDropdown() {
    const countrySelect = document.getElementById('country');
    if (!countrySelect) {
        console.warn('Country select element not found');
        return;
    }
    
    try {
        const apiBase = getAdminApiBase();
        // Add cache-busting parameter to ensure fresh data
        const timestamp = new Date().getTime();
        const url = `${apiBase}/admin/get_countries_cities.php?action=countries&_t=${timestamp}`;
        
        console.log('Loading countries from:', url);
        
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            cache: 'no-cache' // Prevent browser caching
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        console.log('Countries API response:', data);
        
        // Only populate if countries exist in System Settings
        if (data.success && Array.isArray(data.countries) && data.countries.length > 0) {
            // Clear existing options except the first one
            const firstOption = countrySelect.querySelector('option[value=""]');
            countrySelect.innerHTML = '';
            if (firstOption) {
                countrySelect.appendChild(firstOption);
            }
            
            // Add all countries from the API
            data.countries.sort().forEach(country => {
                const option = document.createElement('option');
                option.value = country;
                option.textContent = country;
                countrySelect.appendChild(option);
            });
            
            console.log(`Loaded ${data.countries.length} countries into dropdown`);
        } else {
            console.warn('No countries returned from API or API returned error');
            // No countries in System Settings - keep dropdown empty
            const firstOption = countrySelect.querySelector('option[value=""]');
            countrySelect.innerHTML = '';
            if (firstOption) {
                countrySelect.appendChild(firstOption);
            }
        }
    } catch (error) {
        console.error('Failed to load countries:', error);
        // Show error in dropdown
        const countrySelect = document.getElementById('country');
        if (countrySelect) {
            countrySelect.innerHTML = '<option value="">Error loading countries</option>';
        }
    }
}

// Initialize country dropdown and city loading on modal open
async function initializeCountryCityDropdowns() {
    // Wait a bit for modal to be fully rendered
    await new Promise(resolve => setTimeout(resolve, 100));
    
    // Wait for country select element to be available
    let countrySelect = document.getElementById('country');
    let retries = 0;
    while (!countrySelect && retries < 10) {
        await new Promise(resolve => setTimeout(resolve, 50));
        countrySelect = document.getElementById('country');
        retries++;
    }
    
    if (!countrySelect) {
        console.warn('Country select element not found after waiting');
        return;
    }
    
    // Populate countries
    await populateCountryDropdown();
    
    // Re-get the select element after population (it might have been replaced)
    countrySelect = document.getElementById('country');
    if (countrySelect) {
        // Remove existing listeners by cloning
        const newSelect = countrySelect.cloneNode(true);
        countrySelect.parentNode.replaceChild(newSelect, countrySelect);
        
        // Add new listener
        newSelect.addEventListener('change', function() {
            loadCitiesByCountry(this.value, 'city');
        });
    }
}

// Initialize Agents History
function initializeAgentHistory() {
    if (window.ModuleHistory) {
        window.agentHistory = new ModuleHistory('agents', 'Agents');
        
        // Load history stats
        window.agentHistory.loadStats().then(stats => {
            const countElement = document.getElementById('agentHistoryCount');
            if (countElement) {
                countElement.textContent = stats.total || 0;
            }
        });
    }
}

// Agent History Card Click Handler
document.addEventListener('click', (e) => {
    if (e.target.closest('#agentHistoryCard')) {
        if (window.agentHistory) {
            window.agentHistory.openModal();
        }
    }
});

// Check for edit/view parameters and open modals automatically
function checkUrlParameters() {
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit');
    const viewId = urlParams.get('view');
    
    // Hide table container when edit/view is present to show form directly
    if (editId || viewId) {
        const tableContainer = document.querySelector('.table-container');
        const contentWrapper = document.querySelector('.content-wrapper');
        if (tableContainer) {
            tableContainer.classList.add('agent-loading-hidden');
        }
        if (contentWrapper) {
            contentWrapper.classList.add('agent-loading-hidden');
        }
    }
    
    if (editId) {
        // Wait for agentManager to be ready, then open edit modal
        setTimeout(() => {
            if (window.agentManager && window.agentManager.edit) {
                window.agentManager.edit(parseInt(editId));
            }
        }, 1000);
    } else if (viewId) {
        // Wait for agentManager to be ready, then open view modal
        setTimeout(() => {
            if (window.agentManager && window.agentManager.view) {
                window.agentManager.view(parseInt(viewId));
            }
        }, 1000);
    }
}

// Verify accounting modal is loaded

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    if (!navigator.onLine) {
        const closingAlertModal = document.getElementById('closingAlertModal');
        if (closingAlertModal) {
            closingAlertModal.classList.add('d-none', 'offline-hidden');
        }
        
        // Also hide any ModernAlert overlays
        const modernAlertOverlays = document.querySelectorAll('.modern-alert-overlay');
        modernAlertOverlays.forEach(overlay => {
            overlay.remove();
        });
    }
    
    const tableContainer = document.querySelector('.table-container');
    const tableScroll = document.querySelector('.table-scroll');
    if (tableContainer && tableScroll) {
        tableContainer.classList.remove('table-ready');
        tableScroll.classList.remove('table-ready');
        tableScroll.classList.remove('scroll-ready');
        tableContainer.classList.remove('show-10', 'show-25', 'show-50');
        tableScroll.classList.remove('show-10', 'show-25', 'show-50');
    }
    
    setupEventListeners();
    agentManager.load();
    
    initializeAgentHistory();
    checkUrlParameters();
});

// Make available globally
window.agentManager = agentManager;
window.showAddModal = () => ui.showAddModal();
window.loadCitiesByCountry = loadCitiesByCountry;

