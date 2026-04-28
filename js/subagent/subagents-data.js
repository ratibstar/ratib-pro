/**
 * EN: Implements frontend interaction behavior in `js/subagent/subagents-data.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/subagent/subagents-data.js`.
 */
/**
 * Subagents Data Management
 * Handles all subagent-related data operations, API calls, and UI updates
 */

// Prevent redeclaration
if (typeof window.subagentManager === 'undefined') {

// XSS protection: escape user content before rendering in innerHTML
function escapeHtml(str) {
    if (str == null || str === '') return '';
    const d = document.createElement('div');
    d.textContent = String(str);
    return d.innerHTML;
}

const subagentManager = {
    pageSizeSync: false,
    // State management
    state: {
        subagents: [],
        pagination: {
            page: 1,
            limit: 5,
            total: 0,
            total_pages: 0
        },
        filters: {
            search: '',
            status: '',
            agent_id: ''
        },
        selected: new Set(),
        loading: false,
        stats: {
            total: 0,
            active: 0,
            inactive: 0
        }
    },

    // API configuration
    api: {
        get baseUrl() {
            return ((window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '')) + '/subagents';
        },
        
        async get(filters = {}) {
            try {
                // Build params object, only including non-empty values
                const params = new URLSearchParams({
                    page: subagentManager.state.pagination.page,
                    limit: subagentManager.state.pagination.limit
                });
                
                // Add filters only if they have values
                if (filters.search && filters.search.trim()) {
                    params.append('search', filters.search.trim());
                }
                if (filters.status && filters.status.trim()) {
                    params.append('status', filters.status.trim());
                }
                if (filters.agent_id) {
                    params.append('agent_id', filters.agent_id);
                }
                if (filters.id) {
                    params.append('id', filters.id);
                }
                
                const url = `${subagentManager.api.baseUrl}/get.php?${params}`;
                const response = await fetch(url, { credentials: 'same-origin' });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                return data;
            } catch (error) {
                throw error;
            }
        },

        async getStats() {
            try {
                const response = await fetch(`${subagentManager.api.baseUrl}/stats.php`, { credentials: 'same-origin' });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                return data;
            } catch (error) {
                throw error;
            }
        },

        async create(subagentData) {
            try {
                const response = await fetch(`${subagentManager.api.baseUrl}/create.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(subagentData)
                });
                
                const data = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const message = (data && data.message) ? data.message : `HTTP error! status: ${response.status}`;
                    throw new Error(message);
                }
                return data;
            } catch (error) {
                throw error;
            }
        },

        async update(id, subagentData) {
            try {
                const response = await fetch(`${subagentManager.api.baseUrl}/update.php?id=${id}`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(subagentData)
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                return data;
            } catch (error) {
                throw error;
            }
        },

        async delete(id) {
            try {
                const numericId = parseInt(id, 10);
                if (!Number.isInteger(numericId)) {
                    throw new Error('Invalid subagent ID');
                }

                const response = await fetch(`${subagentManager.api.baseUrl}/delete.php`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ ids: [numericId] })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const text = await response.text();
                if (!text || text.trim() === '') {
                    throw new Error('Empty response from server');
                }

                return JSON.parse(text);
            } catch (error) {
                throw error;
            }
        },

        async bulkUpdate(ids, updates) {
            try {
                const response = await fetch(`${subagentManager.api.baseUrl}/bulk-update.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ ids, updates })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                return data;
            } catch (error) {
                throw error;
            }
        },

        async bulkDelete(ids) {
            try {
                const sanitizedIds = ids
                    .map(id => parseInt(id, 10))
                    .filter(id => Number.isInteger(id) && id > 0);

                if (!sanitizedIds.length) {
                    throw new Error('No valid IDs provided for deletion');
                }

                const response = await fetch(`${subagentManager.api.baseUrl}/delete.php`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ ids: sanitizedIds })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const text = await response.text();
                if (!text || text.trim() === '') {
                    throw new Error('Empty response from server');
                }

                return JSON.parse(text);
            } catch (error) {
                throw error;
            }
        }
    },

    // UI management
    ui: {
        showLoading() {
            const loadingEl = document.getElementById('loadingIndicator');
            if (loadingEl) loadingEl.classList.add('loading-visible');
            
            const tableEl = document.getElementById('subagentTable');
            if (tableEl) tableEl.classList.add('subagent-table-loading');
        },

        hideLoading() {
            const loadingEl = document.getElementById('loadingIndicator');
            if (loadingEl) loadingEl.classList.remove('loading-visible');
            
            const tableEl = document.getElementById('subagentTable');
            if (tableEl) tableEl.classList.remove('subagent-table-loading');
        },

        showError(message, title = 'Error') {
            this.showModernAlert(message, 'error', title, 5000);
        },

        showSuccess(message, title = 'Success') {
            this.showModernAlert(message, 'success', title, 4000);
        },

        showWarning(message, title = 'Warning') {
            this.showModernAlert(message, 'warning', title, 4500);
        },

        showInfo(message, title = 'Info') {
            this.showModernAlert(message, 'info', title, 3000);
        },

        showModernAlert(message, type = 'info', title = 'Alert', duration = 4000) {
            const alert = document.getElementById('modernAlert');
            if (!alert) return;

            const alertIcon = alert.querySelector('.alert-icon i');
            const alertTitle = alert.querySelector('.alert-title');
            const alertText = alert.querySelector('.alert-text');
            const alertProgress = alert.querySelector('.alert-progress');
            const alertClose = alert.querySelector('.alert-close');

            // Set content
            alertTitle.textContent = title;
            alertText.textContent = message;

            // Set icon based on type
            const icons = {
                success: 'fas fa-check-circle',
                warning: 'fas fa-exclamation-triangle',
                error: 'fas fa-times-circle',
                info: 'fas fa-info-circle'
            };
            alertIcon.className = icons[type] || icons.info;

            // Set type class
            alert.className = `modern-alert ${type}`;

            // Remove existing close handler and add new one
            const newCloseBtn = alertClose.cloneNode(true);
            alertClose.parentNode.replaceChild(newCloseBtn, alertClose);
            newCloseBtn.addEventListener('click', () => this.hideModernAlert());

            // Show alert
            alert.classList.add('show');

            // Reset progress bar - use CSS animation
            alertProgress.classList.remove('animating');
            alertProgress.style.removeProperty('--alert-duration');
            void alertProgress.offsetWidth; // reflow
            alertProgress.style.setProperty('--alert-duration', `${duration}ms`);
            alertProgress.classList.add('animating');

            // Auto hide after duration
            if (duration > 0) {
                setTimeout(() => {
                    this.hideModernAlert();
                }, duration);
            }
        },

        hideModernAlert() {
            const alert = document.getElementById('modernAlert');
            if (alert) {
                alert.classList.remove('show');
            }
        },

        updateTable(subagents) {
            const tbody = document.getElementById('subagentTableBody');
            if (!tbody) return;

            tbody.innerHTML = subagents.map(subagent => `
                <tr>
                    <td>${escapeHtml(subagent.formatted_id || `S${String(subagent.subagent_id).padStart(4, '0')}`)}</td>
                    <td>${escapeHtml(subagent.full_name) || '-'}</td>
                    <td>${escapeHtml(subagent.email) || '-'}</td>
                    <td>${escapeHtml(subagent.phone) || '-'}</td>
                    <td>${escapeHtml(subagent.city) || '-'}</td>
                    <td>${escapeHtml(subagent.address) || '-'}</td>
                    <td>${escapeHtml(subagent.agent_name) || '-'}</td>
                    <td>
                        <span class="status-badge ${subagent.status || 'inactive'}">
                            ${(subagent.status || 'inactive').charAt(0).toUpperCase() + (subagent.status || 'inactive').slice(1)}
                        </span>
                    </td>
                    <td>
                        <input type="checkbox" class="subagent-checkbox" 
                               value="${subagent.subagent_id}"
                               ${subagentManager.state.selected.has(subagent.subagent_id.toString()) ? 'checked' : ''}>
                    </td>
                    <td class="actions">
                        <button type="button" data-action="view" data-id="${subagent.subagent_id}" 
                                class="action-btn view-btn" title="View" data-permission="view_subagents">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" data-action="edit" data-id="${subagent.subagent_id}" 
                                class="action-btn edit-btn" title="Edit" data-permission="edit_subagent">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" data-action="delete" data-id="${subagent.subagent_id}" 
                                class="action-btn delete-btn" title="Delete" data-permission="delete_subagent">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `).join('') || '<tr><td colspan="10" class="text-center">No subagents found</td></tr>';

            // Keep table aligned to left on laptop/desktop so ID + Name stay visible.
            const tableContainer = document.querySelector('.subagent-container .table-container');
            if (tableContainer) {
                tableContainer.scrollLeft = 0;
            }

            this.updateBulkButtons();
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
            Object.assign(subagentManager.state.stats, stats);
            
            // Update UI elements
            const totalCount = document.getElementById('totalCount');
            const activeCount = document.getElementById('activeCount');
            const inactiveCount = document.getElementById('inactiveCount');
            
            if (totalCount) totalCount.textContent = stats.total || 0;
            if (activeCount) activeCount.textContent = stats.active || 0;
            if (inactiveCount) inactiveCount.textContent = stats.inactive || 0;
        },

        updateBulkButtons() {
            const hasSelected = subagentManager.state.selected.size > 0;

            const bulkButtons = document.querySelectorAll('[data-action="bulk-activate"], [data-action="bulk-deactivate"], [data-action="delete-selected"]');
            bulkButtons.forEach(btn => {
                btn.disabled = !hasSelected;
                btn.classList.toggle('bulk-btn-disabled', !hasSelected);
                btn.classList.toggle('disabled', !hasSelected);
            });

            const rowCheckboxes = Array.from(document.querySelectorAll('.subagent-checkbox'));
            const selectedCount = rowCheckboxes.filter(cb => subagentManager.state.selected.has(String(cb.value))).length;
            const allChecked = rowCheckboxes.length > 0 && selectedCount === rowCheckboxes.length;

            document.querySelectorAll('.bulk-checkbox-all').forEach(cb => {
                cb.checked = allChecked && hasSelected;
                cb.indeterminate = !allChecked && selectedCount > 0;
            });
        },

        showViewModal(subagent) {
            const modal = document.getElementById('viewSubagentModal');
            if (!modal) return;

            // Set current subagent ID for edit functionality
            window.currentSubagentId = subagent.subagent_id;
            // Store full subagent for Print (may not be in state.subagents when viewed via URL or filters)
            window.currentViewedSubagent = subagent;

            // Update modal content (escape user data for XSS protection)
            const detailsContainer = modal.querySelector('#viewSubagentDetails');
            if (detailsContainer) {
                detailsContainer.innerHTML = `
                    <div class="details-row">
                        <label>ID:</label>
                        <span>${escapeHtml(subagent.formatted_id || `S${String(subagent.subagent_id).padStart(4, '0')}`)}</span>
                    </div>
                    <div class="details-row">
                        <label>Name:</label>
                        <span>${escapeHtml(subagent.full_name) || '-'}</span>
                    </div>
                    <div class="details-row">
                        <label>Email:</label>
                        <span>${escapeHtml(subagent.email) || '-'}</span>
                    </div>
                    <div class="details-row">
                        <label>Phone:</label>
                        <span>${escapeHtml(subagent.phone) || '-'}</span>
                    </div>
                    <div class="details-row">
                        <label>City:</label>
                        <span>${escapeHtml(subagent.city) || '-'}</span>
                    </div>
                    <div class="details-row">
                        <label>Address:</label>
                        <span>${escapeHtml(subagent.address) || '-'}</span>
                    </div>
                    <div class="details-row">
                        <label>Agent:</label>
                        <span>${escapeHtml(subagent.agent_name) || '-'}</span>
                    </div>
                    <div class="details-row">
                        <label>Status:</label>
                        <span class="status-badge ${subagent.status || 'inactive'}">
                            ${(subagent.status || 'inactive').charAt(0).toUpperCase() + (subagent.status || 'inactive').slice(1)}
                        </span>
                    </div>
                `;
            }

            this.showModal(modal);
        },

        syncFilters() {
            // Sync status filter dropdown with current filter state
            const statusFilter = document.getElementById('statusFilter');
            if (statusFilter) {
                const currentStatus = subagentManager.state.filters.status || 'all';
                statusFilter.value = currentStatus === '' ? 'all' : currentStatus;
            }
            
            // Sync search input with current filter state
            const searchInput = document.getElementById('subagentSearch');
            if (searchInput) {
                searchInput.value = subagentManager.state.filters.search || '';
            }
        },

        updatePagination(pagination) {
            const containers = ['Top', 'Bottom'];
            containers.forEach(position => {
                // Update info text
                const start = ((pagination.page - 1) * pagination.limit) + 1;
                const end = Math.min(start + pagination.limit - 1, pagination.total);
                
                document.getElementById(`startRecord${position}`).textContent = start;
                document.getElementById(`endRecord${position}`).textContent = end;
                document.getElementById(`totalRecords${position}`).textContent = pagination.total;

                // Update page size select
                const pageSizeSelect = document.getElementById(`pageSize${position}`);
                if (pageSizeSelect) {
                    pageSizeSelect.value = pagination.limit;
                }

                // Update prev/next buttons
                const prevBtn = document.querySelector(`#pagination${position} .prev-page`);
                const nextBtn = document.querySelector(`#pagination${position} .next-page`);
                
                if (prevBtn) prevBtn.disabled = pagination.page <= 1;
                if (nextBtn) nextBtn.disabled = pagination.page >= pagination.total_pages;

                // Generate page numbers
                const pageNumbersContainer = document.querySelector(`#pagination${position} .page-numbers`);
                if (pageNumbersContainer) {
                    pageNumbersContainer.innerHTML = '';
                    
                    const totalPages = pagination.total_pages;
                    const currentPage = pagination.page;
                    
                    if (totalPages > 0) {
                        // Show max 7 page numbers
                        let startPage = Math.max(1, currentPage - 3);
                        let endPage = Math.min(totalPages, currentPage + 3);
                        
                        // Adjust if we're near the start or end
                        if (endPage - startPage < 6) {
                            if (startPage === 1) {
                                endPage = Math.min(totalPages, startPage + 6);
                            } else if (endPage === totalPages) {
                                startPage = Math.max(1, endPage - 6);
                            }
                        }
                        
                        // Add first page if not in range
                        if (startPage > 1) {
                            const firstBtn = document.createElement('button');
                            firstBtn.className = 'page-number';
                            firstBtn.textContent = '1';
                            firstBtn.addEventListener('click', () => {
                                subagentManager.state.pagination.page = 1;
                                subagentManager.load();
                            });
                            pageNumbersContainer.appendChild(firstBtn);
                            
                            if (startPage > 2) {
                                const ellipsis = document.createElement('span');
                                ellipsis.className = 'page-ellipsis';
                                ellipsis.textContent = '...';
                                pageNumbersContainer.appendChild(ellipsis);
                            }
                        }
                        
                        // Add page numbers
                        for (let i = startPage; i <= endPage; i++) {
                            const pageBtn = document.createElement('button');
                            pageBtn.className = 'page-number';
                            if (i === currentPage) {
                                pageBtn.classList.add('active');
                            }
                            pageBtn.textContent = i;
                            pageBtn.addEventListener('click', () => {
                                subagentManager.state.pagination.page = i;
                                subagentManager.load();
                            });
                            pageNumbersContainer.appendChild(pageBtn);
                        }
                        
                        // Add last page if not in range
                        if (endPage < totalPages) {
                            if (endPage < totalPages - 1) {
                                const ellipsis = document.createElement('span');
                                ellipsis.className = 'page-ellipsis';
                                ellipsis.textContent = '...';
                                pageNumbersContainer.appendChild(ellipsis);
                            }
                            
                            const lastBtn = document.createElement('button');
                            lastBtn.className = 'page-number';
                            lastBtn.textContent = totalPages;
                            lastBtn.addEventListener('click', () => {
                                subagentManager.state.pagination.page = totalPages;
                                subagentManager.load();
                            });
                            pageNumbersContainer.appendChild(lastBtn);
                        }
                    }
                }
            });
        },

        showModal(modal) {
            if (!modal) return;
            
            modal.classList.remove('subagent-modal-hidden', 'd-none');
            modal.classList.add('show', 'subagent-modal-visible');
            document.body.classList.add('modal-open');
            
            // Add outside click handler for edit form
            if (modal.id === 'editForm') {
                const handleOutsideClick = (e) => {
                    if (e.target === modal) {
                        subagentManager.handleFormClose();
                    }
                };
                modal.addEventListener('click', handleOutsideClick);
            }
            
            // Add outside click handler for view modal
            if (modal.id === 'viewSubagentModal') {
                const handleOutsideClick = (e) => {
                    if (e.target === modal) {
                        subagentManager.ui.hideModal(modal);
                    }
                };
                // Remove existing listener if any
                modal.removeEventListener('click', handleOutsideClick);
                modal.addEventListener('click', handleOutsideClick);
            }
        },

        hideModal(modal) {
            if (!modal) return;
            modal.classList.add('subagent-modal-hidden', 'd-none');
            modal.classList.remove('show', 'subagent-modal-visible');
            document.body.classList.remove('modal-open');
        },

        closeAllModals() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.classList.add('subagent-modal-hidden', 'd-none');
                modal.classList.remove('show', 'subagent-modal-visible');
            });
            document.body.classList.remove('modal-open');
        }
    },

    // Event handlers
    events: {
        init() {
            // Search functionality
            const searchInput = document.getElementById('subagentSearch');
            if (searchInput) {
                // Use debounce for search to avoid too many API calls
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        // Reset to first page when search changes
                        subagentManager.state.pagination.page = 1;
                        subagentManager.state.filters.search = e.target.value;
                        subagentManager.load();
                    }, 300);
                });
            }

            // Status filter
            const statusFilter = document.getElementById('statusFilter');
            if (statusFilter) {
                statusFilter.addEventListener('change', (e) => {
                    // Reset to first page when filter changes
                    subagentManager.state.pagination.page = 1;
                    subagentManager.state.filters.status = e.target.value === 'all' ? '' : e.target.value;
                    subagentManager.load();
                });
            }

            // Agent filter
            const agentFilter = document.getElementById('agentFilter');
            if (agentFilter) {
                agentFilter.addEventListener('change', (e) => {
                    subagentManager.state.filters.agent_id = e.target.value;
                    subagentManager.load();
                });
            }

            // Pagination controls
            this.setupPaginationControls();

            // Page size selectors
            document.querySelectorAll('[id^="pageSize"]').forEach(select => {
                select.addEventListener('change', (e) => {
                    if (subagentManager.pageSizeSync) {
                        return;
                    }

                    const newLimit = parseInt(e.target.value);
                    subagentManager.state.pagination.limit = newLimit;
                    subagentManager.state.pagination.page = 1;

                    subagentManager.pageSizeSync = true;
                    document.querySelectorAll('[id^="pageSize"]').forEach(otherSelect => {
                        if (otherSelect !== e.target) {
                            otherSelect.value = newLimit;
                        }
                    });
                    subagentManager.pageSizeSync = false;

                    subagentManager.load();
                });
            });

            // Delegated event handling for action buttons
            document.addEventListener('click', (e) => {
                const target = e.target.closest('[data-action]');
                if (!target) return;

                if (target.disabled || target.classList.contains('disabled')) {
                    return;
                }

                const action = target.dataset.action;
                this.handleAction(action, e);
            });

            // Checkbox selection
            document.addEventListener('change', (e) => {
                if (e.target.classList.contains('subagent-checkbox')) {
                    const id = String(e.target.value);
                    if (e.target.checked) {
                        subagentManager.state.selected.add(id);
                    } else {
                        subagentManager.state.selected.delete(id);
                    }
                    subagentManager.ui.updateBulkButtons();
                }

                // Select all checkbox
                if (e.target.classList.contains('bulk-checkbox-all')) {
                    const checkboxes = document.querySelectorAll('.subagent-checkbox');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = e.target.checked;
                        const id = String(checkbox.value);
                        if (e.target.checked) {
                            subagentManager.state.selected.add(id);
                        } else {
                            subagentManager.state.selected.delete(id);
                        }
                    });
                    subagentManager.ui.updateBulkButtons();
                }
            });

            // Form submission
            const subagentForm = document.getElementById('subagentFormMain');
            if (subagentForm) {
                subagentForm.addEventListener('submit', (e) => {
                    this.handleFormSubmit(e);
                });
            }
            
            // Account form submission
            const accountForm = document.getElementById('accountDetailsForm');
            if (accountForm) {
                accountForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    subagentManager.handleAccountSave();
                });
            }
        },

        setupPaginationControls() {
            // First page buttons
            document.querySelectorAll('.first-page').forEach(btn => {
                btn.addEventListener('click', () => {
                    subagentManager.state.pagination.page = 1;
                    subagentManager.load();
                });
            });

            // Previous page buttons
            document.querySelectorAll('.prev-page').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (subagentManager.state.pagination.page > 1) {
                        subagentManager.state.pagination.page--;
                        subagentManager.load();
                    }
                });
            });

            // Next page buttons
            document.querySelectorAll('.next-page').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (subagentManager.state.pagination.page < subagentManager.state.pagination.total_pages) {
                        subagentManager.state.pagination.page++;
                        subagentManager.load();
                    }
                });
            });

            // Last page buttons
            document.querySelectorAll('.last-page').forEach(btn => {
                btn.addEventListener('click', () => {
                    subagentManager.state.pagination.page = subagentManager.state.pagination.total_pages;
                    subagentManager.load();
                });
            });
        },

        handleAction(action, event) {
            const target = event.target.closest('[data-action]');
            const id = target ? target.dataset.id : null;
            
            switch(action) {
                case 'show-add-form':
                    subagentManager.showAddForm();
                    break;
                case 'refresh':
                    subagentManager.load();
                    break;
                case 'bulk-activate':
                    subagentManager.showBulkActivateConfirmation();
                    break;
                case 'bulk-deactivate':
                    subagentManager.showBulkDeactivateConfirmation();
                    break;
                case 'delete-selected':
                    subagentManager.showBulkDeleteConfirmation();
                    break;
                case 'close-form':
                    // Check if it's the view modal
                    const viewModal = document.getElementById('viewSubagentModal');
                    if (viewModal && viewModal.classList.contains('subagent-modal-visible')) {
                        subagentManager.ui.hideModal(viewModal);
                    } else {
                        subagentManager.handleFormClose();
                    }
                    break;
                case 'edit-current':
                    if (window.currentSubagentId) {
                        // Close view modal first
                        const viewModal = document.getElementById('viewSubagentModal');
                        if (viewModal) {
                            subagentManager.ui.hideModal(viewModal);
                        }
                        subagentManager.edit(window.currentSubagentId);
                    }
                    break;
                case 'print-subagent':
                    if (window.currentSubagentId) {
                        const subagent = window.currentViewedSubagent || subagentManager.state.subagents.find(s => s.subagent_id === window.currentSubagentId);
                        if (subagent) {
                            subagentManager.printSubagent(subagent);
                        }
                    }
                    break;
                case 'view':
                    if (id) subagentManager.view(parseInt(id));
                    break;
                case 'edit':
                    if (id) subagentManager.edit(parseInt(id));
                    break;
                case 'account':
                    if (id) subagentManager.showAccount(parseInt(id));
                    break;
                case 'delete':
                    if (id) subagentManager.delete(parseInt(id));
                    break;
                case 'toggle-password':
                    this.togglePasswordVisibility(event.target);
                    break;
                case 'toggle-all':
                    // Handled in change event
                    break;
            }
        },

        async handleFormSubmit(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            // Validate form fields
            if (!data.name || !data.name.trim()) {
                subagentManager.ui.showError('Please enter the subagent name');
                document.getElementById('name')?.focus();
                return;
            }
            
            if (!data.email || !data.email.trim()) {
                subagentManager.ui.showError('Please enter the email address');
                document.getElementById('email')?.focus();
                return;
            }
            
            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(data.email.trim())) {
                subagentManager.ui.showError('Please enter a valid email address (e.g., user@example.com)');
                document.getElementById('email')?.focus();
                return;
            }
            
            if (!data.phone || !data.phone.trim()) {
                subagentManager.ui.showError('Please enter the phone number');
                document.getElementById('phone')?.focus();
                return;
            }
            
            if (!data.city || !data.city.trim() || data.city === 'Select Country First') {
                subagentManager.ui.showError('Please select a city');
                document.getElementById('city')?.focus();
                return;
            }
            
            if (!data.address || !data.address.trim()) {
                subagentManager.ui.showError('Please enter the address');
                document.getElementById('address')?.focus();
                return;
            }
            
            if (!data.agent || !data.agent.trim()) {
                subagentManager.ui.showError('Please select an agent');
                document.getElementById('agentSelect')?.focus();
                return;
            }
            
            // Map form fields to API expected fields
            const agentId = data.agent ? parseInt(data.agent, 10) : null;
            const mappedData = {
                full_name: data.name.trim(),
                email: data.email.trim(),
                phone: data.phone.trim(),
                city: data.city.trim(),
                address: data.address.trim(),
                agent_id: (Number.isInteger(agentId) && agentId > 0) ? agentId : null,
                status: (data.status && data.status.trim()) ? data.status.trim() : 'active'
            };
            
            // Modern confirmation before save
            const isEdit = !!window.currentSubagentId;
            const confirmOptions = isEdit
                ? {
                    type: 'warning',
                    icon: 'fa-save',
                    title: 'Save Changes',
                    message: 'Are you sure you want to save changes to this subagent?',
                    confirmText: 'Save',
                    cancelText: 'Cancel'
                  }
                : {
                    type: 'success',
                    icon: 'fa-user-plus',
                    title: 'Add Subagent',
                    message: 'Are you sure you want to add this subagent?',
                    confirmText: 'Save',
                    cancelText: 'Cancel'
                  };
            const confirmed = await subagentManager.showConfirmation(confirmOptions);
            if (!confirmed) return;
            
            // Check if editing or creating
            if (window.currentSubagentId) {
                subagentManager.updateSubagent(window.currentSubagentId, mappedData);
            } else {
                subagentManager.createSubagent(mappedData);
            }
        },

        togglePasswordVisibility(button) {
            const input = button.closest('.password-input-group').querySelector('input');
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    },

    // Main operations
    async load(filters = {}) {
        if (subagentManager.state.loading) {
            return;
        }
        try {
            subagentManager.state.loading = true;
            subagentManager.ui.showLoading();
            
            // Update filters
            subagentManager.state.filters = { ...subagentManager.state.filters, ...filters };
            
            // Load both data and stats
            const [response, statsResponse] = await Promise.all([
                subagentManager.api.get(subagentManager.state.filters),
                subagentManager.api.getStats()
            ]);
            
            if (response.success && response.data) {
                // Update state
                subagentManager.state.subagents = response.data.subagents || response.data.list || [];
                subagentManager.state.pagination = {
                    ...subagentManager.state.pagination,
                    ...(response.data.pagination || {})
                };

                // Remove any selected IDs that are no longer present
                const currentIdsSet = new Set(subagentManager.state.subagents.map(sub => String(sub.subagent_id)));
                Array.from(subagentManager.state.selected).forEach(id => {
                    if (!currentIdsSet.has(String(id))) {
                        subagentManager.state.selected.delete(id);
                    }
                });
                
                // Update UI
                subagentManager.ui.updateTable(subagentManager.state.subagents);
                subagentManager.ui.updatePagination(subagentManager.state.pagination);
                subagentManager.ui.syncFilters();
            } else {
                // Show empty state
                subagentManager.state.subagents = [];
                subagentManager.ui.updateTable([]);
            }

            // Update stats if available
            if (statsResponse && statsResponse.success && statsResponse.data) {
                subagentManager.ui.updateStats(statsResponse.data);
            }
        } catch (error) {
            subagentManager.ui.showError('Failed to load subagents: ' + error.message);
            // Show empty state on error
            subagentManager.state.subagents = [];
            subagentManager.ui.updateTable([]);
        } finally {
            subagentManager.state.loading = false;
            subagentManager.ui.updateBulkButtons();
            subagentManager.ui.hideLoading();
        }
    },

    async view(id) {
        try {
            subagentManager.ui.showLoading();
            const response = await subagentManager.api.get({ id });
            
            if (response.success && response.data) {
                const subagent = response.data.subagents[0];
                subagentManager.ui.showViewModal(subagent);
            }
        } catch (error) {
            subagentManager.ui.showError('Failed to load subagent details');
        } finally {
            subagentManager.ui.hideLoading();
        }
    },

    async edit(id) {
        try {
            subagentManager.ui.showLoading();
            const response = await subagentManager.api.get({ id });
            
            if (response.success && response.data) {
                const subagent = response.data.subagents[0]; // Get first subagent from array
                await subagentManager.showEditForm(subagent);
            } else {
                subagentManager.ui.showError('Failed to load subagent for editing');
            }
        } catch (error) {
            subagentManager.ui.showError('Failed to load subagent for editing');
        } finally {
            subagentManager.ui.hideLoading();
        }
    },

    async delete(id) {
        // Get subagent name for confirmation message
        const subagent = subagentManager.state.subagents.find(s => s.subagent_id === id);
        const subagentName = subagent ? (subagent.full_name || subagent.subagent_name || '') : '';
        
        // Show modern delete confirmation
        const shouldDelete = await subagentManager.showDeleteConfirmation(subagentName);
        if (!shouldDelete) {
            return;
        }

        try {
            subagentManager.ui.showLoading();
            const response = await subagentManager.api.delete(id);
            
            if (response && response.success) {
                subagentManager.state.selected.delete(String(id));
                subagentManager.ui.updateBulkButtons();
                // Optimistically remove the subagent from the current table view
                subagentManager.state.subagents = subagentManager.state.subagents.filter(subagent => {
                    return String(subagent.subagent_id) !== String(id);
                });
                subagentManager.ui.updateTable(subagentManager.state.subagents);

                subagentManager.ui.showSuccess(response.message || 'Subagent deleted successfully');
                await subagentManager.load();
            } else {
                const errorMsg = response?.message || 'Failed to delete subagent';
                subagentManager.ui.showError(errorMsg);
            }
        } catch (error) {
            subagentManager.ui.showError('Failed to delete subagent: ' + (error.message || 'Unknown error'));
        } finally {
            subagentManager.ui.hideLoading();
        }
    },

    async bulkUpdate(updates) {
        if (subagentManager.state.selected.size === 0) {
            subagentManager.ui.showError('Please select subagents to update');
            return;
        }

        try {
            subagentManager.ui.showLoading();
            const ids = Array.from(subagentManager.state.selected);
            const response = await subagentManager.api.bulkUpdate(ids, updates);
            if (response.success) {
                const count = ids.length;
                const action = updates.status === 'active' ? 'activated' : updates.status === 'inactive' ? 'deactivated' : 'updated';
                subagentManager.state.selected.clear();
                document.querySelectorAll('.subagent-checkbox:checked').forEach(cb => cb.checked = false);
                subagentManager.ui.updateBulkButtons();
                subagentManager.ui.showSuccess(`Successfully ${action} ${count} subagent${count > 1 ? 's' : ''}`);
                await subagentManager.load();
            } else {
                subagentManager.ui.showError(response.message || 'Failed to update subagents');
            }
        } catch (error) {
            subagentManager.ui.showError('Failed to update subagents: ' + (error.message || 'Unknown error'));
        } finally {
            subagentManager.ui.hideLoading();
        }
    },

    async bulkDelete() {
        if (subagentManager.state.selected.size === 0) {
            subagentManager.ui.showError('Please select subagents to delete');
            return;
        }

        try {
            subagentManager.ui.showLoading();
            const ids = Array.from(subagentManager.state.selected);
            const response = await subagentManager.api.bulkDelete(ids);
            
            if (response && response.success) {
                subagentManager.state.selected.clear();
                document.querySelectorAll('.subagent-checkbox:checked').forEach(cb => cb.checked = false);
                subagentManager.ui.updateBulkButtons();
                // Optimistically remove deleted subagents from the current view
                const toRemove = new Set(ids.map(id => String(id)));
                subagentManager.state.subagents = subagentManager.state.subagents.filter(subagent => {
                    return !toRemove.has(String(subagent.subagent_id));
                });
                subagentManager.ui.updateTable(subagentManager.state.subagents);

                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                subagentManager.ui.showSuccess(response.message || `Successfully deleted ${ids.length} subagent(s)`);
                await subagentManager.load();
            } else {
                const errorMsg = response?.message || 'Failed to delete subagents';
                subagentManager.ui.showError(errorMsg);
            }
        } catch (error) {
            subagentManager.ui.showError('Failed to delete subagents: ' + (error.message || 'Unknown error'));
        } finally {
            subagentManager.ui.hideLoading();
        }
    },

    showAddForm() {
        const modal = document.getElementById('editForm');
        const formTitle = document.getElementById('formTitle');
        const form = document.getElementById('subagentFormMain');
        
        if (formTitle) formTitle.textContent = 'Add New Subagent';
        if (form) form.reset();
        
        // Clear editing ID if exists
        window.currentSubagentId = null;
        
        // Load agents into dropdown
        this.loadAgents();
        
        subagentManager.ui.showModal(modal);
    },

    async showEditForm(subagent) {
        const modal = document.getElementById('editForm');
        const formTitle = document.getElementById('formTitle');
        const form = document.getElementById('subagentFormMain');
        
        if (formTitle) formTitle.textContent = 'Edit Subagent';
        if (form) {
            // Populate form with subagent data
            const nameField = document.getElementById('name');
            const emailField = document.getElementById('email');
            const phoneField = document.getElementById('phone');
            const addressField = document.getElementById('address');
            const statusField = document.getElementById('subagentStatus');
            
            if (nameField) nameField.value = subagent.full_name || '';
            if (emailField) emailField.value = subagent.email || '';
            if (phoneField) phoneField.value = subagent.phone || '';
            if (addressField) addressField.value = subagent.address || '';
            if (statusField) statusField.value = subagent.status || 'active';
            
            // Store original values for change detection
            setTimeout(() => {
                if (nameField) nameField.setAttribute('data-original', nameField.value || '');
                if (emailField) emailField.setAttribute('data-original', emailField.value || '');
                if (phoneField) phoneField.setAttribute('data-original', phoneField.value || '');
                if (addressField) addressField.setAttribute('data-original', addressField.value || '');
                if (statusField) statusField.setAttribute('data-original', statusField.value || '');
            }, 200);
            
            // Handle city and country - need to set country first, then populate cities, then set city
            const cityValue = subagent.city || '';
            const countrySelect = document.getElementById('country');
            const citySelect = document.getElementById('city');
            
            if (cityValue && countrySelect && citySelect) {
                // Try to find the country for this city
                // Try to find the country for this city using API
                async function findCountryForCity() {
                    try {
                        const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || document.getElementById('app-config')?.getAttribute('data-base-url') || '';
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
                        
                        let foundCountry = '';
                        if (data.success && data.countriesCities) {
                            for (const [country, cities] of Object.entries(data.countriesCities)) {
                                if (Array.isArray(cities) && cities.includes(cityValue)) {
                                    foundCountry = country;
                                    break;
                                }
                            }
                        }
                        
                        // Set country if found
                        if (foundCountry) {
                            countrySelect.value = foundCountry;
                            // Store original value
                            setTimeout(() => {
                                countrySelect.setAttribute('data-original', foundCountry);
                            }, 100);
                            
                            // Populate cities for the country
                            if (typeof loadCitiesByCountry === 'function') {
                                await loadCitiesByCountry(foundCountry, 'city');
                                // Set city value after cities are populated
                                setTimeout(() => {
                                    citySelect.value = cityValue;
                                    citySelect.setAttribute('data-original', cityValue);
                                }, 200);
                            } else {
                                citySelect.value = cityValue;
                                citySelect.setAttribute('data-original', cityValue);
                            }
                        } else {
                            // City not found in any country, just set it
                            citySelect.value = cityValue;
                        }
                    } catch (error) {
                        // Fallback: just set the city value
                        citySelect.value = cityValue;
                    }
                }
                
                await findCountryForCity();
            }
        }
        
        // Store current subagent ID for update
        window.currentSubagentId = subagent.subagent_id;
        
        // Load agents into dropdown first, then set the agent value
        await this.loadAgents();
        
        // Set agent value after agents are loaded and store original
        const agentSelect = document.getElementById('agentSelect');
        if (agentSelect) {
            agentSelect.value = subagent.agent_id || '';
            setTimeout(() => {
                agentSelect.setAttribute('data-original', agentSelect.value || '');
            }, 100);
        }
        
        subagentManager.ui.showModal(modal);
    },

    async loadAgents() {
        try {
            const apiBase = (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '');
            const response = await fetch(`${apiBase}/agents/get.php?limit=100`, { credentials: 'same-origin' });
            const data = await response.json();
            
            const agentSelect = document.getElementById('agentSelect');
            if (agentSelect && data.success && data.data) {
                agentSelect.innerHTML = '<option value="">-- Select Agent --</option>';
                
                let agents = [];
                if (Array.isArray(data.data)) {
                    agents = data.data;
                } else if (data.data.list) {
                    agents = data.data.list;
                } else if (data.data.agents) {
                    agents = data.data.agents;
                }
                
                agents.forEach(agent => {
                    const option = document.createElement('option');
                    option.value = agent.agent_id || agent.id;
                    option.textContent = `${agent.formatted_id || agent.agent_id || agent.id} - ${agent.full_name || agent.agent_name}`;
                    agentSelect.appendChild(option);
                });
            }
        } catch (error) {
            // Silent fail: dropdown may stay empty
        }
    },

    async createSubagent(data) {
        try {
            subagentManager.ui.showLoading();
            const response = await subagentManager.api.create(data);
            
            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                subagentManager.ui.showSuccess('Subagent created successfully');
                subagentManager.ui.closeAllModals();
                subagentManager.load();
            } else {
                subagentManager.ui.showError(response.message || 'Failed to create subagent');
            }
        } catch (error) {
            const message = (error && error.message) ? error.message : 'Failed to create subagent';
            subagentManager.ui.showError(message);
        } finally {
            subagentManager.ui.hideLoading();
        }
    },

    async updateSubagent(id, data) {
        try {
            subagentManager.ui.showLoading();
            const response = await subagentManager.api.update(id, data);
            
            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                subagentManager.ui.showSuccess('Subagent updated successfully');
                subagentManager.ui.closeAllModals();
                subagentManager.load();
            } else {
                subagentManager.ui.showError(response.message || 'Failed to update subagent');
            }
        } catch (error) {
            subagentManager.ui.showError('Failed to update subagent');
        } finally {
            subagentManager.ui.hideLoading();
        }
    },

    showAccount(id) {
        // Set current subagent ID for account functionality
        window.currentSubagentId = id;
        
        // Show the account modal
        const modal = document.getElementById('accountDetailsModal');
        if (modal) {
            subagentManager.ui.showModal(modal);
            
            // Setup closing alerts for account form
            if (window.UniversalClosingAlerts) {
                window.UniversalClosingAlerts.setupForm('#accountDetailsForm', '#accountDetailsModal', () => {
                    subagentManager.ui.hideModal(modal);
                    const form = document.getElementById('accountDetailsForm');
                    if (form) form.reset();
                });
            }
            
            // Save button will trigger form submit event, which is handled by the form submit listener
        }
    },

    // Show modern delete confirmation (custom modal)
    async showDeleteConfirmation(subagentName = '') {
        return await subagentManager.showConfirmation({
            type: 'danger',
            title: 'Delete Subagent',
            message: subagentName
                ? `Are you sure you want to delete "${subagentName}"? This action cannot be undone.`
                : 'Are you sure you want to delete this subagent? This action cannot be undone.',
            icon: 'fa-exclamation-triangle',
            confirmText: 'Delete',
            cancelText: 'Cancel'
        });
    },

    // Print subagent details
    printSubagent(subagent) {
        // Create print styles if they don't exist
        if (!document.getElementById('print-styles')) {
            const printStyle = document.createElement('style');
            printStyle.id = 'print-styles';
            printStyle.textContent = `
                @media print {
                    @page {
                        margin: 1cm;
                    }
                    body * {
                        visibility: hidden;
                    }
                    .print-container, .print-container * {
                        visibility: visible;
                    }
                    .print-container {
                        position: absolute;
                        left: 0;
                        top: 0;
                        width: 100%;
                        background: #fff;
                        color: #000;
                    }
                }
                .print-container {
                    display: none;
                }
            `;
            document.head.appendChild(printStyle);
        }
        
        // Create print container
        const printContainer = document.createElement('div');
        printContainer.className = 'print-container';
        printContainer.innerHTML = `
            <div class="subagent-export-page">
                <div class="subagent-export-header">
                    <h1 class="subagent-export-title">Subagent Details</h1>
                    <p class="subagent-export-date">Generated on ${escapeHtml(new Date().toLocaleString())}</p>
                </div>
                <div class="subagent-export-body">
                    <div class="subagent-export-row">
                        <div class="subagent-export-label">ID:</div>
                        <div class="subagent-export-value">${escapeHtml(subagent.formatted_id || `S${String(subagent.subagent_id).padStart(4, '0')}`)}</div>
                    </div>
                    <div class="subagent-export-row">
                        <div class="subagent-export-label">Name:</div>
                        <div class="subagent-export-value">${escapeHtml(subagent.full_name) || '-'}</div>
                    </div>
                    <div class="subagent-export-row">
                        <div class="subagent-export-label">Email:</div>
                        <div class="subagent-export-value">${escapeHtml(subagent.email) || '-'}</div>
                    </div>
                    <div class="subagent-export-row">
                        <div class="subagent-export-label">Phone:</div>
                        <div class="subagent-export-value">${escapeHtml(subagent.phone) || '-'}</div>
                    </div>
                    <div class="subagent-export-row">
                        <div class="subagent-export-label">City:</div>
                        <div class="subagent-export-value">${escapeHtml(subagent.city) || '-'}</div>
                    </div>
                    <div class="subagent-export-row">
                        <div class="subagent-export-label">Address:</div>
                        <div class="subagent-export-value">${escapeHtml(subagent.address) || '-'}</div>
                    </div>
                    <div class="subagent-export-row">
                        <div class="subagent-export-label">Agent:</div>
                        <div class="subagent-export-value">${escapeHtml(subagent.agent_name) || '-'}</div>
                    </div>
                    <div class="subagent-export-row subagent-export-row-last">
                        <div class="subagent-export-label">Status:</div>
                        <div class="subagent-export-value">
                            <span class="status-badge-print ${subagent.status === 'active' ? 'status-badge-print-active' : 'status-badge-print-inactive'}">
                                ${(subagent.status || 'inactive').charAt(0).toUpperCase() + (subagent.status || 'inactive').slice(1)}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="subagent-export-footer">
                    <p>This document was generated from the Subagent Management System</p>
                </div>
            </div>
        `;
        
        document.body.appendChild(printContainer);
        
        // Trigger print
        window.print();
        
        // Remove print container after printing
        setTimeout(() => {
            if (printContainer.parentNode) {
                printContainer.parentNode.removeChild(printContainer);
            }
        }, 100);
    },

    // Handle account save
    async handleAccountSave() {
        try {
            const form = document.getElementById('accountDetailsForm');
            if (!form) {
                subagentManager.ui.showError('Account form not found');
                return;
            }
            
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            // Validate form fields
            if (!data.username || !data.username.trim()) {
                subagentManager.ui.showError('Please enter a username');
                form.querySelector('[name="username"]')?.focus();
                return;
            }
            
            if (!data.password || !data.password.trim()) {
                subagentManager.ui.showError('Please enter a password');
                form.querySelector('[name="password"]')?.focus();
                return;
            }
            
            if (!data.role || !data.role.trim()) {
                subagentManager.ui.showError('Please select a role');
                form.querySelector('[name="role"]')?.focus();
                return;
            }
            
            subagentManager.ui.showSuccess('Account saved successfully!');
            
            // Close the modal
            const modal = document.getElementById('accountDetailsModal');
            if (modal) {
                subagentManager.ui.hideModal(modal);
            }
            
            // Reset the form
            form.reset();
            
        } catch (error) {
            subagentManager.ui.showError('Failed to save account: ' + (error.message || 'Unknown error'));
        }
    },

    // Initialize the manager
    init() {
        document.querySelectorAll('[id^="pageSize"]').forEach(select => {
            select.value = subagentManager.state.pagination.limit;
        });
        subagentManager.ui.updateBulkButtons();
        subagentManager.events.init();
        subagentManager.load();
        subagentManager.loadAgents();
    },

    // Modern confirmation modal
    showConfirmation(options) {
        return new Promise((resolve) => {
            const modal = document.getElementById('confirmationModal');
            const icon = document.getElementById('confirmationIcon');
            const title = document.getElementById('confirmationTitle');
            const message = document.getElementById('confirmationMessage');
            const cancelBtn = document.getElementById('confirmationCancel');
            const confirmBtn = document.getElementById('confirmationConfirm');

            // Set modal content
            title.textContent = options.title || 'Confirm Action';
            message.textContent = options.message || 'Are you sure you want to perform this action?';
            
            // Set icon and modal class based on type
            modal.className = 'confirmation-modal';
            if (options.type) {
                modal.classList.add(options.type);
            }

            // Set icon
            const iconClass = options.icon || 'fa-question-circle';
            icon.className = `fas ${iconClass}`;

            // Set button text
            cancelBtn.innerHTML = `<i class="fas fa-times"></i> ${options.cancelText || 'Cancel'}`;
            confirmBtn.innerHTML = `<i class="fas fa-check"></i> ${options.confirmText || 'Confirm'}`;

            // Set confirm button color based on type
            confirmBtn.className = 'btn';
            if (options.type === 'danger') {
                confirmBtn.classList.add('btn-primary');
            } else if (options.type === 'warning') {
                confirmBtn.classList.add('btn-warning');
            } else {
                confirmBtn.classList.add('btn-primary');
            }

            // Show modal (remove d-none so it displays)
            modal.classList.remove('d-none', 'subagent-modal-hidden');
            modal.classList.add('subagent-modal-visible');

            // Event listeners
            const handleCancel = () => {
                modal.classList.remove('subagent-modal-visible');
                modal.classList.add('subagent-modal-hidden', 'd-none');
                resolve(false);
                cleanup();
            };

            const handleConfirm = () => {
                modal.classList.remove('subagent-modal-visible');
                modal.classList.add('subagent-modal-hidden', 'd-none');
                resolve(true);
                cleanup();
            };

            const cleanup = () => {
                cancelBtn.removeEventListener('click', handleCancel);
                confirmBtn.removeEventListener('click', handleConfirm);
                modal.removeEventListener('click', handleBackdrop);
            };

            const handleBackdrop = (e) => {
                if (e.target === modal) {
                    handleCancel();
                }
            };

            cancelBtn.addEventListener('click', handleCancel);
            confirmBtn.addEventListener('click', handleConfirm);
            modal.addEventListener('click', handleBackdrop);
        });
    },

    // Bulk action confirmations
    async showBulkActivateConfirmation() {
        const selectedCount = subagentManager.state.selected.size;
        const confirmed = await subagentManager.showConfirmation({
            type: 'success',
            icon: 'fa-check-circle',
            title: 'Activate Subagents',
            message: `Are you sure you want to activate ${selectedCount} selected subagent${selectedCount > 1 ? 's' : ''}?`,
            confirmText: 'Activate',
            cancelText: 'Cancel'
        });
        
        if (confirmed) {
            subagentManager.bulkUpdate({ status: 'active' });
        }
    },

    async showBulkDeactivateConfirmation() {
        const selectedCount = subagentManager.state.selected.size;
        const confirmed = await subagentManager.showConfirmation({
            type: 'warning',
            icon: 'fa-exclamation-triangle',
            title: 'Deactivate Subagents',
            message: `Are you sure you want to deactivate ${selectedCount} selected subagent${selectedCount > 1 ? 's' : ''}?`,
            confirmText: 'Deactivate',
            cancelText: 'Cancel'
        });
        
        if (confirmed) {
            subagentManager.bulkUpdate({ status: 'inactive' });
        }
    },

    async showBulkDeleteConfirmation() {
        const selectedCount = subagentManager.state.selected.size;
        const confirmed = await subagentManager.showConfirmation({
            type: 'danger',
            icon: 'fa-trash-alt',
            title: 'Delete Subagents',
            message: `Are you sure you want to delete ${selectedCount} selected subagent${selectedCount > 1 ? 's' : ''}? This action cannot be undone.`,
            confirmText: 'Delete',
            cancelText: 'Cancel'
        });
        
        if (confirmed) {
            subagentManager.bulkDelete();
        }
    },

    // Modern Closing Alert for Forms
    showClosingAlert() {
        return new Promise((resolve) => {
            const modal = document.getElementById('closingAlertModal');
            const cancelBtn = document.getElementById('closingAlertCancel');
            const discardBtn = document.getElementById('closingAlertDiscard');
            
            modal.classList.remove('d-none');
            
            const handleCancel = () => {
                modal.classList.add('d-none');
                resolve(false);
                cancelBtn.removeEventListener('click', handleCancel);
                discardBtn.removeEventListener('click', handleDiscard);
            };
            
            const handleDiscard = () => {
                modal.classList.add('d-none');
                resolve(true);
                cancelBtn.removeEventListener('click', handleCancel);
                discardBtn.removeEventListener('click', handleDiscard);
            };
            
            cancelBtn.addEventListener('click', handleCancel);
            discardBtn.addEventListener('click', handleDiscard);
        });
    },

    // Track form changes
    hasFormChanges() {
        const form = document.getElementById('subagentFormMain');
        if (!form) return false;
        
        // Check if we're in edit mode (have currentSubagentId)
        const isEditMode = !!window.currentSubagentId;
        
        const inputs = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
        for (let input of inputs) {
            // Skip checkboxes and radio buttons for change detection
            if (input.type === 'checkbox' || input.type === 'radio') {
                continue;
            }
            
            // For edit mode, compare with original values if stored
            if (isEditMode && input.hasAttribute('data-original')) {
                const original = input.getAttribute('data-original') || '';
                if (input.value.trim() !== original.trim()) {
                    return true;
                }
            } else {
                // For add mode or fields without original, check if field has value
                if (input.value.trim() !== '') {
                    return true;
                }
            }
        }
        return false;
    },

    // Enhanced form close handling
    async handleFormClose() {
        if (this.hasFormChanges()) {
            const shouldClose = await this.showClosingAlert();
            if (shouldClose) {
                this.closeForm();
            }
        } else {
            this.closeForm();
        }
    },

    closeForm() {
        const modal = document.getElementById('editForm');
        if (modal) {
            subagentManager.ui.hideModal(modal);
        }
        // Clear form and reset state
        const form = document.getElementById('subagentFormMain');
        if (form) {
            form.reset();
        }
        window.currentSubagentId = null;
    }
};

// Check for edit/view parameters and hide table to show form directly
function checkSubagentUrlParameters() {
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit');
    const viewId = urlParams.get('view');
    
    // Hide table container when edit/view is present to show form directly
    if (editId || viewId) {
        const subagentContainer = document.querySelector('.subagent-container');
        if (subagentContainer) {
            const statusCards = subagentContainer.querySelector('.status-cards');
            const subagentTable = subagentContainer.querySelector('.subagent-table');
            if (statusCards) statusCards.classList.add('url-param-hidden');
            if (subagentTable) subagentTable.classList.add('url-param-hidden');
        }
    }
}

// Auto-initialize when DOM is ready
// Initialize subagent manager
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        checkSubagentUrlParameters();
        subagentManager.init();
        setupSubagentEventListeners();
    });
} else {
    checkSubagentUrlParameters();
    subagentManager.init();
    setupSubagentEventListeners();
}

// Setup event listeners for subagent page
function setupSubagentEventListeners() {
    // Country select - load cities on change
    const countrySelect = document.getElementById('country');
    if (countrySelect && countrySelect.hasAttribute('data-load-cities')) {
        const cityFieldId = countrySelect.getAttribute('data-load-cities');
        countrySelect.addEventListener('change', function() {
            if (typeof window.loadCitiesByCountry === 'function') {
                window.loadCitiesByCountry(this.value, cityFieldId);
            }
        });
    }
}

// Make it globally available
window.subagentManager = subagentManager;
} // End of redeclaration check
