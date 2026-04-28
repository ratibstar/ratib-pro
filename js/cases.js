/**
 * EN: Implements frontend interaction behavior in `js/cases.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/cases.js`.
 */
/**
 * Cases Management System - Unified JavaScript
 * Modern, clean, and efficient case management functionality
 */

// Helper function to get API base URL
function getApiBase() {
    return (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '') || '../api';
}

class CasesManager {
    constructor() {
        this.apiUrl = getApiBase() + '/cases/index.php';
        this.currentPage = 1;
        this.itemsPerPage = 5;
        this.currentFilters = {
            search: '',
            status: '',
            category: '',
            priority: ''
        };
        this.currentUser = 1; // You can get this from session or localStorage
        this.hasUnsavedChanges = false;
        this.isPopulatingForm = false;
        this.cases = [];
        this.stats = {};
        this.workers = [];
        this.agents = [];
        this.subagents = [];
        this.users = [];
        
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.loadInitialData();
        this.setActiveNavItem();
        
        // Global error handler
        window.addEventListener('error', (e) => {
            console.error('Global error:', e.error);
        });
        
        window.addEventListener('unhandledrejection', (e) => {
            console.error('Unhandled promise rejection:', e.reason);
        });

        // Initialize modern alert system
        this.initModernAlerts();

        // Before page unload, check for unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges && this.checkForUnsavedChanges()) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    }
    
    bindEvents() {
        // Search and filter events
        document.getElementById('searchInput').addEventListener('input', this.debounce(() => {
            this.currentFilters.search = document.getElementById('searchInput').value;
            this.currentPage = 1;
            this.loadCases();
        }, 300));
        
        document.getElementById('statusFilter').addEventListener('change', (e) => {
            this.currentFilters.status = e.target.value;
            this.currentPage = 1;
            this.loadCases();
        });
        
        document.getElementById('typeFilter').addEventListener('change', (e) => {
            this.currentFilters.category = e.target.value;
            this.currentPage = 1;
            this.loadCases();
        });
        
        document.getElementById('priorityFilter').addEventListener('change', (e) => {
            this.currentFilters.priority = e.target.value;
            this.currentPage = 1;
            this.loadCases();
        });
        
        // Pagination events - Bottom pagination
        document.getElementById('prevBtn').addEventListener('click', () => {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.loadCases();
            }
        });
        
        document.getElementById('nextBtn').addEventListener('click', () => {
            this.currentPage++;
            this.loadCases();
        });
        
        // Pagination events - Top pagination
        document.getElementById('prevBtnTop').addEventListener('click', () => {
            if (this.currentPage > 1) {
                this.currentPage--;
                this.loadCases();
            }
        });
        
        document.getElementById('nextBtnTop').addEventListener('click', () => {
            this.currentPage++;
            this.loadCases();
        });
        
        // Page size selectors
        const pageSizeEl = document.getElementById('pageSize');
        const pageSizeTopEl = document.getElementById('pageSizeTop');
        
        if (pageSizeEl) {
            pageSizeEl.value = String(this.itemsPerPage);
            pageSizeEl.addEventListener('change', (e) => {
                const value = parseInt(e.target.value, 10);
                if (!Number.isNaN(value) && value > 0) {
                    this.itemsPerPage = value;
                    this.currentPage = 1;
                    this.loadCases();
                }
            });
        }
        
        if (pageSizeTopEl) {
            pageSizeTopEl.value = String(this.itemsPerPage);
            pageSizeTopEl.addEventListener('change', (e) => {
                const value = parseInt(e.target.value, 10);
                if (!Number.isNaN(value) && value > 0) {
                    this.itemsPerPage = value;
                    this.currentPage = 1;
                    this.loadCases();
                }
            });
        }
        
        // Modal events - createCaseBtn removed from header
        
        document.getElementById('closeModal').addEventListener('click', async () => {
            await this.closeModal();
        });
        
        document.getElementById('closeViewModal').addEventListener('click', () => {
            this.closeViewModal();
        });
        
        document.getElementById('cancelBtn').addEventListener('click', async () => {
            await this.closeModal();
        });
        
        document.getElementById('newCaseBtn').addEventListener('click', async () => {
            await this.openCreateModal();
        });


        // Bulk modal close buttons
        document.getElementById('closeBulkEditModal').addEventListener('click', () => {
            this.closeBulkEditModal();
        });

        document.getElementById('closeBulkStatusModal').addEventListener('click', () => {
            this.closeBulkStatusModal();
        });

        document.getElementById('closeBulkPriorityModal').addEventListener('click', async () => {
            const hasChanges = document.getElementById('prioritySelect').value !== '';
            if (hasChanges) {
                const confirmed = await this.showModernConfirm(
                    'You have unsaved changes. Are you sure you want to close?',
                    'Close Without Saving',
                    'warning'
                );
                if (confirmed) {
                    this.closeBulkPriorityModal();
                }
            } else {
                this.closeBulkPriorityModal();
            }
        });

        document.getElementById('closeBulkAssignedModal').addEventListener('click', async () => {
            const hasChanges = document.getElementById('assignedSelect').value !== '';
            if (hasChanges) {
                const confirmed = await this.showModernConfirm(
                    'You have unsaved changes. Are you sure you want to close?',
                    'Close Without Saving',
                    'warning'
                );
                if (confirmed) {
                    this.closeBulkAssignedModal();
                }
            } else {
                this.closeBulkAssignedModal();
            }
        });

        document.getElementById('closeBulkDueDateModal').addEventListener('click', async () => {
            const hasChanges = document.getElementById('dueDateInput').value !== '';
            if (hasChanges) {
                const confirmed = await this.showModernConfirm(
                    'You have unsaved changes. Are you sure you want to close?',
                    'Close Without Saving',
                    'warning'
                );
                if (confirmed) {
                    this.closeBulkDueDateModal();
                }
            } else {
                this.closeBulkDueDateModal();
            }
        });

        // Bulk modal cancel buttons
        document.getElementById('cancelBulkPriority').addEventListener('click', async () => {
            const hasChanges = document.getElementById('prioritySelect').value !== '';
            if (hasChanges) {
                const confirmed = await this.showModernConfirm(
                    'You have unsaved changes. Are you sure you want to cancel?',
                    'Cancel Changes',
                    'warning'
                );
                if (confirmed) {
                    this.closeBulkPriorityModal();
                }
            } else {
                this.closeBulkPriorityModal();
            }
        });

        document.getElementById('cancelBulkAssigned').addEventListener('click', async () => {
            const hasChanges = document.getElementById('assignedSelect').value !== '';
            if (hasChanges) {
                const confirmed = await this.showModernConfirm(
                    'You have unsaved changes. Are you sure you want to cancel?',
                    'Cancel Changes',
                    'warning'
                );
                if (confirmed) {
                    this.closeBulkAssignedModal();
                }
            } else {
                this.closeBulkAssignedModal();
            }
        });

        document.getElementById('cancelBulkDueDate').addEventListener('click', async () => {
            const hasChanges = document.getElementById('dueDateInput').value !== '';
            if (hasChanges) {
                const confirmed = await this.showModernConfirm(
                    'You have unsaved changes. Are you sure you want to cancel?',
                    'Cancel Changes',
                    'warning'
                );
                if (confirmed) {
                    this.closeBulkDueDateModal();
                }
            } else {
                this.closeBulkDueDateModal();
            }
        });

        // Bulk modal confirm buttons
        document.getElementById('confirmBulkPriority').addEventListener('click', () => {
            this.handleBulkPriorityConfirm();
        });

        document.getElementById('confirmBulkAssigned').addEventListener('click', () => {
            this.handleBulkAssignedConfirm();
        });

        document.getElementById('confirmBulkDueDate').addEventListener('click', () => {
            this.handleBulkDueDateConfirm();
        });
        
        // Form submission
        document.getElementById('caseForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveCase();
        });
        
        // Save button click
        const saveBtn = document.getElementById('saveBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.saveCase();
            });
        } else {
            console.error('Save button not found!');
        }
        
        // Track form changes for unsaved changes detection
        document.getElementById('caseForm').addEventListener('input', () => {
            this.hasUnsavedChanges = true;
        });
        
        document.getElementById('caseForm').addEventListener('change', () => {
            this.hasUnsavedChanges = true;
        });
        
        // Worker dropdown change - auto-populate agents and subagents
        document.getElementById('workerId').addEventListener('change', (e) => {
            // Skip auto-population if we're in the middle of populating a form
            if (this.isPopulatingForm) {
                return;
            }
            
            if (e.target.value) {
                this.autoPopulateAgentsAndSubagents(e.target.value);
            } else {
                // Clear agents and subagents if no worker selected
                document.getElementById('agentId').innerHTML = '<option value="">Select Agent</option>';
                document.getElementById('subagentId').innerHTML = '<option value="">Select Subagent</option>';
            }
        });
        
        // Description dropdown change
        document.getElementById('description').addEventListener('change', (e) => this.handleDescriptionChange(e));
        
        // Resolution dropdown change
        document.getElementById('resolution').addEventListener('change', (e) => this.handleResolutionChange(e));
        
        // Agent dropdown change - auto-populate subagents
        document.getElementById('agentId').addEventListener('change', (e) => {
            if (e.target.value) {
                this.autoPopulateSubagents(e.target.value);
            } else {
                // Clear subagents if no agent selected
                document.getElementById('subagentId').innerHTML = '<option value="">Select Subagent</option>';
            }
        });
        
        // Status change for resolution field
        document.getElementById('status').addEventListener('change', (e) => {
            const resolutionGroup = document.getElementById('resolutionGroup');
            if (resolutionGroup) {
                if (e.target.value === 'resolved' || e.target.value === 'closed') {
                    resolutionGroup.style.display = 'block';
                } else {
                    resolutionGroup.style.display = 'none';
                }
            }
        });
        
        // Character counters
        document.getElementById('customDescription').addEventListener('input', (e) => {
            this.updateCharacterCount('customDescriptionCurrent', e.target.value.length, 500);
        });
        
        document.getElementById('customResolution').addEventListener('input', (e) => {
            this.updateCharacterCount('customResolutionCurrent', e.target.value.length, 1000);
        });
        
        // Close modals on outside click
        document.getElementById('caseModal').addEventListener('click', async (e) => {
            if (e.target.id === 'caseModal') {
                await this.closeModal();
            }
        });
        
        document.getElementById('viewModal').addEventListener('click', (e) => {
            if (e.target.id === 'viewModal') {
                this.closeViewModal();
            }
        });
        
        // Action button event delegation
        document.addEventListener('click', (e) => {
            try {
                if (e.target.closest('.action-btn')) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const button = e.target.closest('.action-btn');
                    const caseId = button.getAttribute('data-case-id');
                    const action = button.getAttribute('data-action');
                    
                    
                    if (caseId && action) {
                        switch (action) {
                            case 'view':
                                this.viewCase(caseId);
                                break;
                            case 'edit':
                                this.editCase(caseId);
                                break;
                            case 'delete':
                                this.deleteCase(caseId);
                                break;
                        }
                    }
                }
                
                // Bulk action buttons
                if (e.target.id === 'bulkEditBtn' || e.target.closest('#bulkEditBtn')) {
                    e.preventDefault();
                    this.handleBulkEdit();
                } else if (e.target.id === 'bulkDeleteBtn' || e.target.closest('#bulkDeleteBtn')) {
                    e.preventDefault();
                    this.handleBulkDelete();
                } else if (e.target.id === 'bulkStatusBtn' || e.target.closest('#bulkStatusBtn')) {
                    e.preventDefault();
                    this.handleBulkStatusChange();
                }
                
                // Select all checkbox
                if (e.target.id === 'selectAllCheckbox') {
                    this.handleSelectAll(e.target.checked);
                }
                
        // Individual case checkboxes
        if (e.target.classList.contains('case-checkbox')) {
            this.handleIndividualCheckbox();
        }
        
        // Bulk modal option buttons
        if (e.target.closest('.bulk-option-btn')) {
            const action = e.target.closest('.bulk-option-btn').getAttribute('data-action');
            this.handleBulkEditOption(action);
        }
        
        // Status modal option buttons
        if (e.target.closest('.status-option-btn')) {
            const status = e.target.closest('.status-option-btn').getAttribute('data-status');
            const activeStatus = e.target.closest('.status-option-btn').getAttribute('data-active-status');
            
            if (status) {
                this.handleBulkStatusOption(status);
            } else if (activeStatus) {
                this.handleBulkActiveStatusOption(activeStatus);
            }
        }
        
        
        // Modal close buttons
        if (e.target.id === 'closeBulkEditModal' || e.target.closest('#closeBulkEditModal')) {
            this.closeBulkEditModal();
        }
        if (e.target.id === 'closeBulkDeleteModal' || e.target.closest('#closeBulkDeleteModal')) {
            this.closeBulkDeleteModal();
        }
        if (e.target.id === 'closeBulkStatusModal' || e.target.closest('#closeBulkStatusModal')) {
            this.closeBulkStatusModal();
        }
        if (e.target.id === 'closeBulkPriorityModal' || e.target.closest('#closeBulkPriorityModal')) {
            this.closeBulkPriorityModal();
        }
        if (e.target.id === 'closeBulkAssignedModal' || e.target.closest('#closeBulkAssignedModal')) {
            this.closeBulkAssignedModal();
        }
        if (e.target.id === 'closeBulkDueDateModal' || e.target.closest('#closeBulkDueDateModal')) {
            this.closeBulkDueDateModal();
        }
        if (e.target.id === 'cancelBulkDelete') {
            this.closeBulkDeleteModal();
        }
        if (e.target.id === 'cancelBulkPriority') {
            this.closeBulkPriorityModal();
        }
        if (e.target.id === 'cancelBulkAssigned') {
            this.closeBulkAssignedModal();
        }
        if (e.target.id === 'cancelBulkDueDate') {
            this.closeBulkDueDateModal();
        }
        
            } catch (error) {
                console.error('Error in action button handler:', error);
            }
        });
    }
    
    async loadInitialData() {
        this.showLoading();
        try {
            await Promise.all([
                this.loadStats(),
                this.loadCases(),
                this.loadWorkers(),
                this.loadAgents(),
                this.loadSubagents(),
                this.loadUsers()
            ]);
        } catch (error) {
            this.showToast('Error loading initial data', 'error');
            console.error('Error loading initial data:', error);
        } finally {
            this.hideLoading();
        }
    }
    
    async loadStats() {
        try {
            const response = await this.apiCall('GET', 'stats');
            if (response.success) {
                this.stats = response.data.stats;
                this.updateStatsDisplay();
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }
    
    async loadCases() {
        try {
            const params = {
                page: this.currentPage,
                limit: this.itemsPerPage,
                ...this.currentFilters
            };
            
            const response = await this.apiCall('GET', 'list', null, params);
            if (response && response.success) {
                this.cases = response.data.cases || [];
                this.updateCasesTable();
                if (response.data.pagination) {
                    this.updatePagination(response.data.pagination);
                }
            } else {
                console.warn('Failed to load cases:', response?.message || 'Unknown error');
                this.cases = [];
            }
        } catch (error) {
            this.showToast('Error loading cases', 'error');
            console.error('Error loading cases:', error);
            this.cases = [];
        }
    }
    
    async loadWorkers() {
        try {
            const response = await this.apiCall('GET', 'workers');
            if (response.success) {
                this.workers = response.data.workers || response.data;
                this.updateSelectOptions('workerId', this.workers);
            } else {
                console.error('Workers API failed:', response.message);
            }
        } catch (error) {
            console.error('Error loading workers:', error);
        }
    }
    
    async loadAgents() {
        try {
            const response = await this.apiCall('GET', 'agents');
            if (response && response.success && response.data && response.data.agents) {
                this.agents = response.data.agents;
                this.updateSelectOptions('agentId', this.agents);
            } else {
                console.warn('Failed to load agents:', response?.message || 'Unknown error');
                this.agents = [];
            }
        } catch (error) {
            console.error('Error loading agents:', error);
            this.agents = [];
        }
    }
    
    async loadSubagents() {
        try {
            const response = await this.apiCall('GET', 'subagents');
            if (response && response.success && response.data && response.data.subagents) {
                this.subagents = response.data.subagents;
                this.updateSelectOptions('subagentId', this.subagents);
            } else {
                console.warn('Failed to load subagents:', response?.message || 'Unknown error');
                this.subagents = [];
            }
        } catch (error) {
            console.error('Error loading subagents:', error);
            this.subagents = [];
        }
    }
    
    async loadUsers() {
        try {
            const response = await this.apiCall('GET', 'users');
            if (response && response.success && response.data && response.data.users) {
                this.users = response.data.users;
                this.updateSelectOptions('assignedTo', this.users);
                const bulkSel = document.getElementById('assignedSelect');
                if (bulkSel) {
                    this.updateSelectOptions('assignedSelect', this.users);
                }
            } else {
                console.warn('Failed to load users:', response?.message || 'Unknown error');
                this.users = [];
            }
        } catch (error) {
            console.error('Error loading users:', error);
            this.users = [];
        }
    }
    
    updateStatsDisplay() {
        const totalCasesEl = document.getElementById('totalCases');
        const lowCasesEl = document.getElementById('lowCases');
        const mediumCasesEl = document.getElementById('mediumCases');
        const highCasesEl = document.getElementById('highCases');
        const urgentCasesEl = document.getElementById('urgentCases');
        const inProgressCasesEl = document.getElementById('inProgressCases');
        const resolvedCasesEl = document.getElementById('resolvedCases');
        
        if (totalCasesEl) totalCasesEl.textContent = this.stats.total || 0;
        if (lowCasesEl) lowCasesEl.textContent = this.stats.low || 0;
        if (mediumCasesEl) mediumCasesEl.textContent = this.stats.medium || 0;
        if (highCasesEl) highCasesEl.textContent = this.stats.high || 0;
        if (urgentCasesEl) urgentCasesEl.textContent = this.stats.urgent || 0;
        if (inProgressCasesEl) inProgressCasesEl.textContent = this.stats.in_progress || 0;
        if (resolvedCasesEl) resolvedCasesEl.textContent = this.stats.resolved || 0;
    }
    
    updateCasesTable() {
        const tbody = document.getElementById('casesTableBody');
        if (!tbody) {
            console.error('casesTableBody element not found!');
            return;
        }
        
        tbody.innerHTML = '';
        
        if (this.cases.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="14" class="text-center text-muted">
                        <i class="fas fa-inbox empty-state-icon"></i>
                        No cases found
                    </td>
                </tr>
            `;
            return;
        }
        
        this.cases.forEach(case_ => {
            const row = document.createElement('tr');
            // Escape text content and attribute values to prevent XSS
            // Note: priority, status, activeStatus are validated enums - safe for class names
            const caseNumber = this.escapeHtml(case_.case_number || 'N/A');
            const workerName = this.escapeHtml(case_.worker_name || 'Unassigned');
            const agentName = this.escapeHtml(case_.agent_name || 'Unassigned');
            const subagentName = this.escapeHtml(case_.subagent_name || 'Unassigned');
            const assignedToName = this.escapeHtml(case_.assigned_to_name || 'Unassigned');
            const description = this.escapeHtml(case_.raw_data?.case_description || case_.description || 'No description');
            const category = this.escapeHtml(case_.category || '');
            const priority = case_.priority || ''; // Validated enum, safe for class name
            const priorityText = this.escapeHtml(priority); // Escape for text content
            const status = case_.status || ''; // Validated enum, safe for class name
            const statusText = this.escapeHtml(status.replace('_', ' ')); // Escape for text content
            const activeStatus = case_.active_status || 'active'; // Validated enum, safe for class name
            const activeStatusText = this.escapeHtml(activeStatus.charAt(0).toUpperCase() + activeStatus.slice(1)); // Escape for text content
            const caseId = String(case_.case_id || '').replace(/[^0-9]/g, ''); // Sanitize ID to numbers only
            
            row.innerHTML = `
                <td>
                    <span class="font-weight-600">${caseNumber}</span>
                </td>
                <td title="${workerName}">
                    <div class="truncated-text">${workerName}</div>
                </td>
                <td title="${agentName}">
                    <div class="truncated-text">${agentName}</div>
                </td>
                <td title="${subagentName}">
                    <div class="truncated-text">${subagentName}</div>
                </td>
                <td title="${description}">
                    <div class="truncated-text font-weight-600">${description}</div>
                </td>
                <td>
                    <span class="text-capitalize">${category}</span>
                </td>
                <td>
                    <span class="priority-badge ${priority}">${priorityText}</span>
                </td>
                <td>
                    <span class="status-badge ${status}">${statusText}</span>
                </td>
                <td>
                    <span class="active-status-badge ${activeStatus}">${activeStatusText}</span>
                </td>
                <td title="${assignedToName}">
                    <div class="truncated-text">${assignedToName}</div>
                </td>
                <td>
                    <div>
                        <div>${this.formatDate(case_.created_at)}</div>
                        <div class="text-muted time-text">${this.formatTime(case_.created_at)}</div>
                    </div>
                </td>
                <td>
                    ${case_.due_date ? this.formatDate(case_.due_date) : '<span class="text-muted">Not set</span>'}
                </td>
                <td>
                    <input type="checkbox" class="case-checkbox" data-case-id="${caseId}">
                </td>
                <td>
                    <div class="action-buttons">
                        <button type="button" class="action-btn view" data-case-id="${caseId}" data-action="view" title="View Details" data-permission="view_cases">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" class="action-btn edit" data-case-id="${caseId}" data-action="edit" title="Edit Case" data-permission="edit_case">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="action-btn delete" data-case-id="${caseId}" data-action="delete" title="Delete Case" data-permission="delete_case">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });
        
        // Update bulk action buttons after table is populated
        this.updateBulkActionButtons();
        
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
    
    updatePagination(pagination) {
        // Update both top and bottom pagination controls
        this.updatePaginationControls(pagination, 'prevBtn', 'nextBtn', 'paginationInfo', 'paginationPages');
        this.updatePaginationControls(pagination, 'prevBtnTop', 'nextBtnTop', 'paginationInfoTop', 'paginationPagesTop');
    }
    
    updatePaginationControls(pagination, prevBtnId, nextBtnId, paginationInfoId, paginationPagesId) {
        const prevBtn = document.getElementById(prevBtnId);
        const nextBtn = document.getElementById(nextBtnId);
        const paginationInfo = document.getElementById(paginationInfoId);
        const paginationPages = document.getElementById(paginationPagesId);
        
        if (prevBtn) prevBtn.disabled = this.currentPage <= 1;
        if (nextBtn) nextBtn.disabled = this.currentPage >= pagination.total_pages;
        
        const start = ((pagination.current_page - 1) * pagination.per_page) + 1;
        const end = Math.min(pagination.current_page * pagination.per_page, pagination.total);
        if (paginationInfo) {
            paginationInfo.textContent = `Showing ${start} to ${end} of ${pagination.total} entries`;
        }
        
        // Generate page numbers
        if (paginationPages) {
            this.generatePageNumbers(pagination, paginationPages);
        }
    }
    
    generatePageNumbers(pagination, container) {
        const totalPages = pagination.total_pages;
        const currentPage = pagination.current_page;
        let pageNumbers = '';
        
        // Always show first page
        pageNumbers += `<span class="page-number ${currentPage === 1 ? 'active' : ''}" data-page="1">1</span>`;
        
        if (totalPages > 1) {
            // Calculate range of pages to show
            let startPage = Math.max(2, currentPage - 2);
            let endPage = Math.min(totalPages - 1, currentPage + 2);
            
            // Add ellipsis after first page if needed
            if (startPage > 2) {
                pageNumbers += '<span class="page-number dots">...</span>';
            }
            
            // Add middle pages
            for (let i = startPage; i <= endPage; i++) {
                if (i !== 1 && i !== totalPages) {
                    pageNumbers += `<span class="page-number ${currentPage === i ? 'active' : ''}" data-page="${i}">${i}</span>`;
                }
            }
            
            // Add ellipsis before last page if needed
            if (endPage < totalPages - 1) {
                pageNumbers += '<span class="page-number dots">...</span>';
            }
            
            // Always show last page if more than 1 page
            if (totalPages > 1) {
                pageNumbers += `<span class="page-number ${currentPage === totalPages ? 'active' : ''}" data-page="${totalPages}">${totalPages}</span>`;
            }
        }
        
        container.innerHTML = pageNumbers;
        
        // Add click event listeners to page numbers
        container.querySelectorAll('.page-number:not(.dots)').forEach(pageBtn => {
            pageBtn.addEventListener('click', (e) => {
                const page = parseInt(e.target.getAttribute('data-page'), 10);
                if (page !== this.currentPage) {
                    this.currentPage = page;
                    this.loadCases();
                }
            });
        });
    }
    
    updateSelectOptions(selectId, options) {
        const select = document.getElementById(selectId);
        if (!select) {
            console.error(`Select element with ID ${selectId} not found`);
            return;
        }
        
        // Clear existing options except the first one
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }
        
        if (!options || !Array.isArray(options)) {
            return;
        }
        
        options.forEach(option => {
            const optionElement = document.createElement('option');
            let fieldName = selectId.replace('Id', '_id');
            
            if (selectId === 'assignedTo' || selectId === 'assignedSelect') {
                fieldName = 'user_id';
            }
            
            optionElement.value = option[fieldName] ?? option.user_id ?? option.id ?? '';
            
            // Handle different naming conventions
            if (option.name) {
                optionElement.textContent = option.name;
            } else if (option.first_name) {
                // Use first_name even if last_name is empty
                optionElement.textContent = option.first_name;
            } else if (option.username) {
                optionElement.textContent = option.username;
            } else {
                optionElement.textContent = option[fieldName] || 'Unknown';
            }
            
            select.appendChild(optionElement);
        });
    }
    
    async openCreateModal() {
        document.getElementById('modalTitle').textContent = 'Create New Case';
        
        // Reset form to clean state
        this.resetForm();
        
        document.getElementById('caseModal').classList.add('show');
        
        // Ensure English date pickers are initialized (Flatpickr replaces native Arabic picker)
        if (typeof window.initializeEnglishDatePickers === 'function') {
            const modal = document.getElementById('caseModal');
            if (modal) window.initializeEnglishDatePickers(modal);
        }
        
        // Refresh all dropdown data to get any newly added records
        await Promise.all([
            this.loadWorkers(),
            this.loadAgents(),
            this.loadSubagents(),
            this.loadUsers()
        ]);
    }
    
    async editCase(caseId) {
        try {
            this.showLoading();
            const response = await this.apiCall('GET', 'get', null, { id: caseId });
            
            if (response.success) {
                const case_ = response.data.case;
                await this.populateForm(case_);
                document.getElementById('modalTitle').textContent = 'Edit Case';
                const caseModal = document.getElementById('caseModal');
                if (!caseModal) {
                    console.error('caseModal element not found!');
                    return;
                }
                caseModal.classList.add('show');
                if (typeof window.initializeEnglishDatePickers === 'function') {
                    window.initializeEnglishDatePickers(caseModal);
                }
            } else {
                this.showToast(response.message || 'Failed to load case details', 'error');
            }
        } catch (error) {
            this.showToast('Error loading case details', 'error');
            console.error('Error loading case:', error);
        } finally {
            this.hideLoading();
        }
    }
    
    async viewCase(caseId) {
        try {
            this.showLoading();
            const response = await this.apiCall('GET', 'get', null, { id: caseId });
            
            if (response.success) {
                const case_ = response.data.case;
                this.showCaseDetails(case_);
            } else {
                this.showToast(response.message || 'Failed to load case details', 'error');
            }
        } catch (error) {
            this.showToast('Error loading case details', 'error');
            console.error('Error loading case:', error);
        } finally {
            this.hideLoading();
        }
    }
    
    async populateForm(case_) {
        // Set flag to prevent worker change event from interfering
        this.isPopulatingForm = true;
        
        document.getElementById('caseId').value = case_.case_id;
        
        // Handle description dropdown and custom input
        const descriptionSelect = document.getElementById('description');
        const customDescription = document.getElementById('customDescription');
        
        if (case_.description || case_.case_description) {
            const description = case_.description || case_.case_description;
            // Check if description is in predefined options
            const options = Array.from(descriptionSelect.options);
            const matchingOption = options.find(option => option.value === description);
            
            if (matchingOption) {
                descriptionSelect.value = description;
                customDescription.style.display = 'none';
            } else {
                // It's a custom description
                descriptionSelect.value = 'custom';
                customDescription.value = description;
                customDescription.style.display = 'block';
            }
        }
        
        // Load dropdowns first, then set values
        if (case_.worker_id) {
            await this.autoPopulateAgentsAndSubagents(case_.worker_id);
        } else {
            // If no worker, just populate agents and subagents normally
            await this.loadAgents();
        }
        
        // Load users
        await this.loadUsers();
        
        // Now set all form values after dropdowns are loaded
        
        document.getElementById('caseType').value = case_.category || '';
        document.getElementById('priority').value = case_.priority || 'low';
        document.getElementById('status').value = case_.status || 'open';
        document.getElementById('activeStatus').value = case_.active_status || 'active';
        
        // Set worker value
        document.getElementById('workerId').value = case_.worker_id || '';
        
        // Set agent and subagent values after loading
        if (case_.agent_id) {
            document.getElementById('agentId').value = case_.agent_id;
        }
        if (case_.subagent_id) {
            document.getElementById('subagentId').value = case_.subagent_id;
        }
        
        // Set assigned to value
        document.getElementById('assignedTo').value = case_.assigned_to || '';
        
        // Handle date formatting for Date Created field (use setDateInputValue for Flatpickr/English)
        const dateCreatedField = document.getElementById('dateCreated');
        const dueDateField = document.getElementById('dueDate');
        this.setDateInputValue(dateCreatedField, this.formatDateForInput(case_.created_at));
        this.setDateInputValue(dueDateField, this.formatDateForInput(case_.due_date));
        
        // Handle resolution dropdown and custom input
        const resolutionSelect = document.getElementById('resolution');
        const customResolution = document.getElementById('customResolution');
        const customResolutionCount = document.getElementById('customResolutionCount');
        
        if (case_.resolution) {
            // Check if resolution is in predefined options
            const options = Array.from(resolutionSelect.options);
            const matchingOption = options.find(option => option.value === case_.resolution);
            
            if (matchingOption) {
                resolutionSelect.value = case_.resolution;
                customResolution.style.display = 'none';
                customResolutionCount.style.display = 'none';
            } else {
                // It's a custom resolution
                resolutionSelect.value = 'custom';
                customResolution.value = case_.resolution;
                customResolution.style.display = 'block';
                customResolutionCount.style.display = 'block';
                this.updateCharacterCount('customResolutionCurrent', case_.resolution.length, 1000);
            }
        } else {
            resolutionSelect.value = '';
            customResolution.style.display = 'none';
            customResolutionCount.style.display = 'none';
        }
        
        
        // Clear flag to allow normal worker change events
        this.isPopulatingForm = false;
    }

    // Modern Alert System
    initModernAlerts() {
        // Alert close button
        document.getElementById('alertClose').addEventListener('click', () => {
            this.hideModernAlert();
        });

        // Confirm dialog buttons
        document.getElementById('confirmCancel').addEventListener('click', () => {
            this.hideModernConfirm(false);
        });

        document.getElementById('confirmOk').addEventListener('click', () => {
            this.hideModernConfirm(true);
        });

        // Close confirm dialog when clicking overlay
        document.querySelector('.confirm-overlay').addEventListener('click', () => {
            this.hideModernConfirm(false);
        });
    }

    showModernAlert(message, type = 'info', title = 'Alert', duration = 4000) {
        const alert = document.getElementById('modernAlert');
        if (!alert) {
            console.error('modernAlert element not found');
            return;
        }
        
        const alertIcon = alert.querySelector('.alert-icon i');
        const alertTitle = alert.querySelector('.alert-title');
        const alertText = alert.querySelector('.alert-text');
        const alertProgress = alert.querySelector('.alert-progress');
        
        if (!alertIcon || !alertTitle || !alertText || !alertProgress) {
            console.error('Required alert elements not found');
            return;
        }

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

        // Show alert
        alert.classList.add('show');

        // Auto hide after duration
        if (duration > 0) {
            setTimeout(() => {
                this.hideModernAlert();
            }, duration);
        }

        // Reset progress bar
        alertProgress.style.transform = 'scaleX(0)';
        setTimeout(() => {
            alertProgress.style.transform = 'scaleX(1)';
        }, 100);
    }

    hideModernAlert() {
        const alert = document.getElementById('modernAlert');
        if (alert) {
            alert.classList.remove('show');
        }
    }

    showModernConfirm(message, title = 'Confirm Action', type = 'default') {
        return new Promise((resolve) => {
            const confirm = document.getElementById('modernConfirm');
            const confirmTitle = confirm.querySelector('.confirm-title');
            const confirmMessage = confirm.querySelector('.confirm-message');
            const confirmIcon = confirm.querySelector('.confirm-icon i');

            confirmTitle.textContent = title;
            confirmMessage.textContent = message;

            // Set icon based on type
            const icons = {
                delete: 'fas fa-trash',
                warning: 'fas fa-exclamation-triangle',
                info: 'fas fa-info-circle',
                default: 'fas fa-question-circle'
            };
            confirmIcon.className = icons[type] || icons.default;

            // Set type class for styling
            confirm.className = `modern-confirm ${type}`;

            // Store resolve function for later use
            this.confirmResolve = resolve;

            // Show confirm dialog
            confirm.classList.add('show');
        });
    }

    hideModernConfirm(result) {
        const confirm = document.getElementById('modernConfirm');
        confirm.classList.remove('show');

        // Resolve the promise with the result
        if (this.confirmResolve) {
            this.confirmResolve(result);
            this.confirmResolve = null;
        }
    }

    // Enhanced showToast method using modern alerts
    showToast(message, type = 'info', title = 'Notification') {
        this.showModernAlert(message, type, title, 3000);
    }

    // Check for unsaved changes
    hasUnsavedChanges() {
        // Check if any form fields have been modified
        const form = document.getElementById('caseForm');
        if (!form) return false;

        const inputs = form.querySelectorAll('input, select, textarea');
        for (let input of inputs) {
            if (input.type === 'checkbox' || input.type === 'radio') {
                if (input.checked !== input.defaultChecked) {
                    return true;
                }
            } else if (input.value !== input.defaultValue) {
                return true;
            }
        }
        return false;
    }

    // Show unsaved changes warning
    async showUnsavedChangesWarning() {
        if (!this.hasUnsavedChanges()) {
            return true; // No unsaved changes, proceed
        }

        const result = await this.showModernConfirm(
            'You have unsaved changes. Are you sure you want to leave without saving?',
            'Unsaved Changes'
        );
        return result;
    }

    
    showCaseDetails(case_) {
        const modalBody = document.getElementById('viewModalBody');
        if (!modalBody) {
            console.error('viewModalBody element not found!');
            return;
        }
        modalBody.innerHTML = `
            <div class="case-details">
                <div class="detail-section">
                    <h3>Case Information</h3>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Case Number:</label>
                            <span class="font-weight-600">${case_.case_number || case_.raw_data?.case_number || 'N/A'}</span>
                        </div>
                        <div class="detail-item">
                            <label>Description:</label>
                            <span>${this.escapeHtml(case_.description || case_.case_description || case_.raw_data?.case_title || 'No description')}</span>
                        </div>
                        <div class="detail-item">
                            <label>Type:</label>
                            <span class="text-capitalize">${case_.category}</span>
                        </div>
                        <div class="detail-item">
                            <label>Priority:</label>
                            <span class="priority-badge ${case_.priority}">${case_.priority}</span>
                        </div>
                        <div class="detail-item">
                            <label>Status:</label>
                            <span class="status-badge ${case_.status}">${case_.status.replace('_', ' ')}</span>
                        </div>
                        <div class="detail-item">
                            <label>Created:</label>
                            <span>${this.formatDate(case_.created_at)} at ${this.formatTime(case_.created_at)}</span>
                        </div>
                        <div class="detail-item">
                            <label>Last Updated:</label>
                            <span>${case_.updated_at ? `${this.formatDate(case_.updated_at)} at ${this.formatTime(case_.updated_at)}` : '—'}</span>
                        </div>
                        ${case_.due_date ? `
                        <div class="detail-item">
                            <label>Due Date:</label>
                            <span>${this.formatDate(case_.due_date)}</span>
                        </div>
                        ` : ''}
                    </div>
                </div>
                
                ${case_.description ? `
                <div class="detail-section">
                    <h3>Description</h3>
                    <div class="description-content">
                        ${this.escapeHtml(case_.description).replace(/\n/g, '<br>')}
                    </div>
                </div>
                ` : ''}
                
                ${case_.resolution ? `
                <div class="detail-section">
                    <h3>Resolution</h3>
                    <div class="resolution-content">
                        ${this.escapeHtml(case_.resolution).replace(/\n/g, '<br>')}
                    </div>
                </div>
                ` : ''}
                
                <div class="detail-section">
                    <h3>Assignments</h3>
                    <div class="detail-grid">
                        ${case_.worker_name ? `
                        <div class="detail-item">
                            <label>Worker:</label>
                            <span>${case_.worker_name}</span>
                        </div>
                        ` : ''}
                        ${case_.agent_name ? `
                        <div class="detail-item">
                            <label>Agent:</label>
                            <span>${case_.agent_name}</span>
                        </div>
                        ` : ''}
                        ${case_.subagent_name ? `
                        <div class="detail-item">
                            <label>Subagent:</label>
                            <span>${case_.subagent_name}</span>
                        </div>
                        ` : ''}
                        ${case_.assigned_to_name ? `
                        <div class="detail-item">
                            <label>Assigned To:</label>
                            <span>${case_.assigned_to_name}</span>
                        </div>
                        ` : ''}
                        <div class="detail-item">
                            <label>Created By:</label>
                            <span>${case_.created_by_name || 'System'}</span>
                        </div>
                    </div>
                </div>
                
                ${case_.activities && case_.activities.length > 0 ? `
                <div class="detail-section">
                    <h3>Activity History</h3>
                    <div class="activity-timeline">
                        ${case_.activities.map(activity => `
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <i class="fas fa-${this.getActivityIcon(activity.activity_type)}"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-header">
                                        <span class="activity-type">${this.formatActivityType(activity.activity_type)}</span>
                                        <span class="activity-time">${this.formatDate(activity.created_at)} at ${this.formatTime(activity.created_at)}</span>
                                    </div>
                                    <div class="activity-description">${this.escapeHtml(activity.description)}</div>
                                    <div class="activity-user">by ${activity.user_name}</div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
            </div>
        `;
        
        const viewModal = document.getElementById('viewModal');
        if (!viewModal) {
            console.error('viewModal element not found!');
            return;
        }
        viewModal.classList.add('show');
    }
    
    async saveCase() {
        try {
            this.showLoading();
            
            const formData = new FormData(document.getElementById('caseForm'));
            const data = Object.fromEntries(formData.entries());
        
        // Handle custom description
        if (data.description === 'custom' && data.custom_description) {
            data.description = data.custom_description;
        }
        delete data.custom_description; // Remove the custom_description field
        
        // Handle custom resolution
        if (data.resolution === 'custom' && data.custom_resolution) {
            data.resolution = data.custom_resolution;
        }
        delete data.custom_resolution; // Remove the custom_resolution field
        
        // Field validation
        if (!data.worker_id) {
            this.showToast('Worker is required', 'error');
            this.hideLoading();
            return;
        }
        
        if (!data.case_type || data.case_type === '') {
            this.showToast('Category is required', 'error');
            this.hideLoading();
            return;
        }
        
        if (!data.description || data.description.trim() === '') {
            this.showToast('Description is required', 'error');
            this.hideLoading();
            return;
        }
        
        // Validate field lengths
        if (data.description && data.description.length > 500) {
            this.showToast('Description must be 500 characters or less', 'error');
            this.hideLoading();
            return;
        }
        
        if (data.resolution && data.resolution.length > 1000) {
            this.showToast('Resolution must be 1000 characters or less', 'error');
            this.hideLoading();
            return;
        }
        
        // Validate due date is not in the past
        if (data.due_date) {
            const dueDate = new Date(data.due_date);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (dueDate < today) {
                this.showToast('Due date cannot be in the past', 'error');
                this.hideLoading();
                return;
            }
        }
            
        // Remove readonly fields that shouldn't be sent (date_created is auto-generated by database)
        delete data.date_created;
        
        // Don't send created_by - let the API get it from session to avoid foreign key constraint issues
        // The API will handle getting the current user from session and validating it exists
        delete data.created_by;
        
        // Convert empty strings to null for optional fields
        Object.keys(data).forEach(key => {
            if (data[key] === '') {
                data[key] = null;
            }
        });
            
        const action = data.case_id ? 'update' : 'create';
        const response = await this.apiCall(action === 'create' ? 'POST' : 'PUT', action, data);
            
            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                // Show modern success alert with cancel option
                const shouldClose = await this.showModernAlertWithConfirm(
                    'Success!',
                    response.message || 'Case saved successfully!',
                    'success',
                    'OK',
                    'Stay'
                );
                
                if (shouldClose) {
                    // Reset form before closing modal
                    this.resetForm();
                    
                    // Close modal
                    document.getElementById('caseModal').classList.remove('show');
                    this.hasUnsavedChanges = false;
                    await this.refreshData();
                }
                // If user chooses "Stay", do nothing - keep the form open
            } else {
                this.showToast(response.message, 'error');
            }
        } catch (error) {
            console.error('Error in saveCase function:', error);
            this.showToast('Error saving case', 'error');
        } finally {
            this.hideLoading();
        }
    }
    
    async deleteCase(caseId) {
        const confirmed = await this.showModernConfirm(
            'Are you sure you want to delete this case? This action cannot be undone.',
            'Delete Case',
            'delete'
        );
        
        if (!confirmed) {
            return;
        }
        
        try {
            this.showLoading();
            const response = await this.apiCall('DELETE', 'delete', null, { id: caseId });
            
            if (response.success) {
                this.showToast('Case deleted successfully', 'success');
                await this.refreshData();
            } else {
                this.showToast(response.message || 'Failed to delete case', 'error');
            }
        } catch (error) {
            this.showToast('Error deleting case', 'error');
            console.error('Error deleting case:', error);
        } finally {
            this.hideLoading();
        }
    }
    
    async refreshData() {
        await Promise.all([
            this.loadStats(),
            this.loadCases()
        ]);
    }
    
    setActiveNavItem() {
        // Remove active class from all nav items
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Add active class to cases nav item
        const casesNavItem = document.querySelector('a[href*="cases/cases-table.php"]') || 
                            document.querySelector('a[href*="cases/index.php"]');
        if (casesNavItem) {
            casesNavItem.classList.add('active');
        }
    }
    
    
    // Auto-populate agents and subagents based on worker
    async autoPopulateAgentsAndSubagents(workerId) {
        try {
            const response = await this.apiCall('GET', 'worker-details', null, { worker_id: workerId });
            
            if (response.success && response.data) {
                const { agents, subagents, worker_agent_id, worker_subagent_id } = response.data;
                
                // Populate agents dropdown with all agents
                const agentSelect = document.getElementById('agentId');
                if (agentSelect && agents) {
                    agentSelect.innerHTML = '<option value="">Select Agent</option>';
                    
                    agents.forEach(agent => {
                        const selected = (worker_agent_id && agent.agent_id == worker_agent_id) ? 'selected' : '';
                        agentSelect.innerHTML += `<option value="${agent.agent_id}" ${selected}>${agent.first_name}</option>`;
                    });
                    
                    // If worker has an assigned agent, auto-populate subagents for that agent
                    if (worker_agent_id) {
                        this.autoPopulateSubagents(worker_agent_id, worker_subagent_id);
                    } else {
                        // If worker has no agent, populate with all subagents from the response
                        this.populateSubagentsDropdown(subagents, worker_subagent_id);
                    }
                } else {
                    // If no agents data, populate with subagents from response
                    this.populateSubagentsDropdown(subagents, worker_subagent_id);
                }
            } else {
                console.error('Failed to get worker details:', response.message);
            }
        } catch (error) {
            console.error('Error loading worker details:', error);
        }
    }
    
    // Helper method to populate subagents dropdown
    populateSubagentsDropdown(subagents, preselectedSubagentId = null) {
        const subagentSelect = document.getElementById('subagentId');
        if (subagentSelect && subagents) {
            subagentSelect.innerHTML = '<option value="">Select Subagent</option>';
            
            subagents.forEach(subagent => {
                const selected = (preselectedSubagentId && subagent.subagent_id == preselectedSubagentId) ? 'selected' : '';
                subagentSelect.innerHTML += `<option value="${subagent.subagent_id}" ${selected}>${subagent.first_name}</option>`;
            });
        }
    }
    
    async autoPopulateSubagents(agentId, preselectedSubagentId = null) {
        try {
            const response = await this.apiCall('GET', 'subagents-by-agent', null, { agent_id: agentId });
            const result = response;
            
            if (result.success && result.data) {
                const { subagents } = result.data;
                const subagentSelect = document.getElementById('subagentId');
                subagentSelect.innerHTML = '<option value="">Select Subagent</option>';
                
                if (subagents && subagents.length === 1) {
                    // Auto-select if only one subagent
                    subagentSelect.innerHTML = `<option value="${subagents[0].subagent_id}" selected>${subagents[0].first_name}</option>`;
                } else if (subagents && subagents.length > 1) {
                    // Show multiple options
                    subagents.forEach(subagent => {
                        const selected = (preselectedSubagentId && subagent.subagent_id == preselectedSubagentId) ? 'selected' : '';
                        subagentSelect.innerHTML += `<option value="${subagent.subagent_id}" ${selected}>${subagent.first_name}</option>`;
                    });
                }
            }
        } catch (error) {
            console.error('Error loading subagents:', error);
        }
    }
    
    // Handle description dropdown change
    handleDescriptionChange(e) {
        const customDescription = document.getElementById('customDescription');
        const customDescriptionCount = document.getElementById('customDescriptionCount');
        if (e.target.value === 'custom') {
            customDescription.style.display = 'block';
            customDescriptionCount.style.display = 'block';
            customDescription.required = true;
        } else {
            customDescription.style.display = 'none';
            customDescriptionCount.style.display = 'none';
            customDescription.required = false;
            customDescription.value = '';
            this.updateCharacterCount('customDescriptionCurrent', 0, 500);
        }
    }

    // Handle resolution dropdown change
    handleResolutionChange(e) {
        const customResolution = document.getElementById('customResolution');
        const customResolutionCount = document.getElementById('customResolutionCount');
        if (e.target.value === 'custom') {
            customResolution.style.display = 'block';
            customResolutionCount.style.display = 'block';
            customResolution.required = true;
        } else {
            customResolution.style.display = 'none';
            customResolutionCount.style.display = 'none';
            customResolution.required = false;
            customResolution.value = '';
            this.updateCharacterCount('customResolutionCurrent', 0, 1000);
        }
    }
    
    updateCharacterCount(elementId, current, max) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = current;
            if (current > max * 0.9) {
                element.style.color = '#ff6b6b';
            } else if (current > max * 0.8) {
                element.style.color = '#ffa726';
            } else {
                element.style.color = '#666';
            }
        }
    }
    
    truncateText(text, maxLength) {
        if (text.length <= maxLength) {
            return text;
        }
        return text.substring(0, maxLength) + '...';
    }
    
    // Helper to set date on input (uses Flatpickr.setDate when available for English display)
    setDateInputValue(input, dateValue) {
        if (!input) return;
        const val = dateValue || '';
        if (input._flatpickr) {
            input._flatpickr.setDate(val || null);
        } else {
            input.value = val;
        }
    }
    
    // Helper function to format date for HTML date input
    formatDateForInput(dateString) {
        if (!dateString) return '';
        
        
        try {
            // Create a new Date object
            const date = new Date(dateString);
            
            // Check if the date is valid
            if (isNaN(date.getTime())) {
                return '';
            }
            
            // Format as YYYY-MM-DD for HTML date input
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            
            const formattedDate = `${year}-${month}-${day}`;
            
            return formattedDate;
        } catch (error) {
            console.error('Error formatting date:', error);
            return '';
        }
    }
    
    // Reset form to initial state
    resetForm() {
        const form = document.getElementById('caseForm');
        if (form) {
            form.reset();
        }
        
        // Clear specific fields
        document.getElementById('caseId').value = '';
        
        // Reset resolution field
        const customResolution = document.getElementById('customResolution');
        const customResolutionCount = document.getElementById('customResolutionCount');
        if (customResolution) {
            customResolution.style.display = 'none';
            customResolution.required = false;
            customResolution.value = '';
        }
        if (customResolutionCount) {
            customResolutionCount.style.display = 'none';
            this.updateCharacterCount('customResolutionCurrent', 0, 1000);
        }
        
        // Reset dropdowns to default state
        const agentSelect = document.getElementById('agentId');
        const subagentSelect = document.getElementById('subagentId');
        
        if (agentSelect) {
            agentSelect.innerHTML = '<option value="">Select Agent</option>';
        }
        
        if (subagentSelect) {
            subagentSelect.innerHTML = '<option value="">Select Subagent</option>';
        }
        
        // Hide custom description field
        const customDescription = document.getElementById('customDescription');
        if (customDescription) {
            customDescription.style.display = 'none';
        }
        
        // Reset custom description count
        const customDescriptionCount = document.getElementById('customDescriptionCount');
        if (customDescriptionCount) {
            this.updateCharacterCount('customDescriptionCurrent', 0, 500);
        }
        
        // Set current date for new cases, clear due date (explicit for Flatpickr sync)
        const today = new Date().toISOString().split('T')[0];
        this.setDateInputValue(document.getElementById('dateCreated'), today);
        this.setDateInputValue(document.getElementById('dueDate'), '');
        
        // Reset priority to default
        document.getElementById('priority').value = 'medium';
        
        // Reset status to default
        document.getElementById('status').value = 'open';
        document.getElementById('activeStatus').value = 'active';
        
        // Reset unsaved changes flag
        this.hasUnsavedChanges = false;
    }
    
    async closeModal() {
        const confirmed = await this.confirmClose();
        if (!confirmed) return;
        
        document.getElementById('caseModal').classList.remove('show');
        
        // Reset the form to clear all fields
        const form = document.getElementById('caseForm');
        if (form) {
            form.reset();
        }
        
        // Clear specific fields that might not be reset by form.reset()
        document.getElementById('caseId').value = '';
        
        // Hide resolution group if it exists (it's shown/hidden based on status)
        const resolutionGroup = document.getElementById('resolutionGroup');
        if (resolutionGroup) {
            resolutionGroup.style.display = 'none';
        }
        
        // Clear dropdowns to default state
        const agentSelect = document.getElementById('agentId');
        const subagentSelect = document.getElementById('subagentId');
        
        if (agentSelect) {
            agentSelect.innerHTML = '<option value="">Select Agent</option>';
        }
        
        if (subagentSelect) {
            subagentSelect.innerHTML = '<option value="">Select Subagent</option>';
        }
        
        // Hide custom description field if it's visible
        const customDescription = document.getElementById('customDescription');
        if (customDescription) {
            customDescription.style.display = 'none';
        }
        
        // Reset unsaved changes flag
        this.hasUnsavedChanges = false;
    }
    
    // Modern Alert Modal System with confirmation (returns Promise)
    // This method is used when you need a Promise-based confirmation dialog
    showModernAlertWithConfirm(title, message, type = 'warning', confirmText = 'Yes', cancelText = 'Cancel') {
        return new Promise((resolve) => {
            // Create alert overlay
            const overlay = document.createElement('div');
            overlay.className = 'modern-alert-overlay';
            overlay.innerHTML = `
                <div class="modern-alert-modal">
                    <div class="modern-alert-header">
                        <div class="modern-alert-icon ${type}">
                            <i class="fas ${type === 'warning' ? 'fa-exclamation-triangle' : type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
                        </div>
                        <h3 class="modern-alert-title">${this.escapeHtml(title)}</h3>
                    </div>
                    <div class="modern-alert-body">
                        <p class="modern-alert-message">${this.escapeHtml(message)}</p>
                    </div>
                    <div class="modern-alert-footer">
                        ${cancelText !== confirmText ? `<button class="modern-alert-btn modern-alert-cancel" data-action="cancel">${this.escapeHtml(cancelText)}</button>` : ''}
                        <button class="modern-alert-btn modern-alert-confirm ${type}" data-action="confirm">${this.escapeHtml(confirmText)}</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(overlay);
            
            // Handle button clicks
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay || e.target.closest('.modern-alert-cancel')) {
                    if (overlay.parentNode) {
                        overlay.parentNode.removeChild(overlay);
                    }
                    resolve(false);
                } else if (e.target.closest('.modern-alert-confirm') || e.target.dataset.action === 'confirm') {
                    if (overlay.parentNode) {
                        overlay.parentNode.removeChild(overlay);
                    }
                    resolve(true);
                }
            });
        });
    }
    
    // Check if form has unsaved changes
    checkForUnsavedChanges() {
        const form = document.getElementById('caseForm');
        if (!form) return false;
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Check if any required fields are filled
        return !!(data.worker_id || data.case_type || data.description || data.assigned_to);
    }
    
    // Show close confirmation
    async confirmClose() {
        if (!this.hasUnsavedChanges && !this.checkForUnsavedChanges()) return true;
        
        return await this.showModernConfirm(
            'You have unsaved changes. Are you sure you want to close without saving?',
            'Unsaved Changes',
            'warning'
        );
    }
    
    closeViewModal() {
        const viewModal = document.getElementById('viewModal');
        if (viewModal) {
            viewModal.classList.remove('show');
        }
    }
    
    async apiCall(method, action, data = null, params = null) {
        const base = getApiBase().replace(/\/$/, '');
        let url = `${base}/cases/cases.php?action=${action}`;
        
        // Handle GET parameters for specific actions
        if (params) {
            const paramString = new URLSearchParams(params).toString();
            url += `&${paramString}`;
        }

        const appCfg = typeof document !== 'undefined' ? document.getElementById('app-config') : null;
        if (appCfg && (appCfg.getAttribute('data-control') === '1' || appCfg.getAttribute('data-control-pro-bridge') === '1')) {
            url += '&control=1';
        }
        
        const options = {
            method: method,
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
            }
        };
        
        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }
        
        try {
            const response = await fetch(url, options);
            const text = await response.text();
            
            // Check if response is empty or whitespace
            if (!text || !text.trim()) {
                console.error('API Call Error: Empty response from', url);
                return {
                    success: false,
                    message: 'Empty response from server',
                    data: []
                };
            }
            
            // Try to parse JSON
            try {
                return JSON.parse(text);
            } catch (parseError) {
                console.error('API Call Error: Invalid JSON response from', url, 'Response:', text);
                return {
                    success: false,
                    message: 'Invalid JSON response from server',
                    data: [],
                    error: parseError.message
                };
            }
        } catch (error) {
            console.error('API Call Error:', error);
            return {
                success: false,
                message: 'Network error or failed request',
                data: [],
                error: error.message
            };
        }
    }
    
    showLoading() {
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.classList.add('show');
        }
    }
    
    hideLoading() {
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) {
            loadingOverlay.classList.remove('show');
        }
    }
    
    // Utility methods
    getToastIcon(type) {
        const icons = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-circle',
            warning: 'fas fa-exclamation-triangle',
            info: 'fas fa-info-circle'
        };
        return icons[type] || icons.info;
    }
    
    getActivityIcon(type) {
        const icons = {
            created: 'plus',
            updated: 'edit',
            assigned: 'user-tag',
            status_changed: 'exchange-alt',
            commented: 'comment',
            resolved: 'check'
        };
        return icons[type] || 'circle';
    }
    
    formatActivityType(type) {
        return type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    }
    
    formatDate(dateString) {
        if (!dateString) return 'Not set';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Invalid Date';
        return date.toLocaleDateString('en-GB', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }
    
    formatTime(dateString) {
        if (!dateString) return 'Not set';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Invalid Time';
        return date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }
    
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    debounce(func, wait = 300) {
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
    
    // Bulk action methods
    handleSelectAll(checked) {
        const checkboxes = document.querySelectorAll('.case-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = checked;
        });
        this.updateBulkActionButtons();
    }
    
    handleIndividualCheckbox() {
        this.updateBulkActionButtons();
    }
    
    updateBulkActionButtons() {
        const checkboxes = document.querySelectorAll('.case-checkbox');
        const checkedBoxes = document.querySelectorAll('.case-checkbox:checked');
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        
        // Update select all checkbox state
        if (selectAllCheckbox) {
            if (checkedBoxes.length === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (checkedBoxes.length === checkboxes.length) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
                selectAllCheckbox.checked = false;
            }
        }
        
        // Update bulk action buttons
        const hasSelection = checkedBoxes.length > 0;
        
        const bulkEditBtn = document.getElementById('bulkEditBtn');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        const bulkStatusBtn = document.getElementById('bulkStatusBtn');
        
        if (bulkEditBtn) bulkEditBtn.disabled = !hasSelection;
        if (bulkDeleteBtn) bulkDeleteBtn.disabled = !hasSelection;
        if (bulkStatusBtn) bulkStatusBtn.disabled = !hasSelection;
    }
    
    getSelectedCaseIds() {
        const checkedBoxes = document.querySelectorAll('.case-checkbox:checked');
        return Array.from(checkedBoxes).map(checkbox => checkbox.getAttribute('data-case-id'));
    }
    
    handleBulkEdit() {
        const selectedIds = this.getSelectedCaseIds();
        if (selectedIds.length === 0) {
            this.showToast('Please select cases to edit', 'warning');
            return;
        }
        
        // Update count and show modal
        const bulkEditCount = document.getElementById('bulkEditCount');
        const bulkEditModal = document.getElementById('bulkEditModal');
        if (bulkEditCount) bulkEditCount.textContent = selectedIds.length;
        if (bulkEditModal) bulkEditModal.classList.add('show');
    }
    
    async handleBulkDelete() {
        const selectedIds = this.getSelectedCaseIds();
        if (selectedIds.length === 0) {
            this.showToast('Please select cases to delete', 'warning');
            return;
        }
        
        const confirmed = await this.showModernConfirm(
            `You are about to delete ${selectedIds.length} case(s). This action cannot be undone!`,
            'Bulk Delete Cases',
            'delete'
        );
        
        if (confirmed) {
            await this.performBulkDelete(selectedIds);
        }
    }

    async performBulkDelete(caseIds) {
        try {
            this.showLoading();
            const response = await this.apiCall('POST', 'bulk-delete', {
                case_ids: caseIds
            });
            this.hideLoading();
            
            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                this.showToast(`${response.data.deleted_count || caseIds.length} case(s) deleted successfully!`, 'success');
                await this.loadCases();
            } else {
                this.showToast(response.message || 'Failed to delete cases', 'error');
            }
        } catch (error) {
            this.hideLoading();
            this.showToast('Error deleting cases', 'error');
            console.error('Error deleting cases:', error);
        }
    }
    
    handleBulkStatusChange() {
        const selectedIds = this.getSelectedCaseIds();
        if (selectedIds.length === 0) {
            this.showToast('Please select cases to change status', 'warning');
            return;
        }
        
        // Update count and show modal
        document.getElementById('bulkStatusCount').textContent = selectedIds.length;
        document.getElementById('bulkStatusModal').classList.add('show');
    }
    
    
    // Modal handling methods
    handleBulkEditOption(action) {
        const selectedIds = this.getSelectedCaseIds();
        this.closeBulkEditModal();
        
        switch (action) {
            case 'priority':
                this.showBulkPriorityModal(selectedIds);
                break;
            case 'status':
                this.showBulkStatusModal(selectedIds);
                break;
            case 'assigned':
                this.showBulkAssignedModal(selectedIds);
                break;
            case 'due-date':
                this.showBulkDueDateModal(selectedIds);
                break;
        }
    }
    
    async handleBulkStatusOption(status) {
        const selectedIds = this.getSelectedCaseIds();
        const statusText = status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ');
        const confirmed = await this.showModernConfirm(
            `Are you sure you want to change status to "${statusText}" for ${selectedIds.length} case(s)?`,
            'Confirm Status Change',
            'info'
        );
        
        if (confirmed) {
            this.closeBulkStatusModal();
            await this.performBulkStatusUpdate(selectedIds, status);
        }
    }
    
    async handleBulkActiveStatusOption(activeStatus) {
        const selectedIds = this.getSelectedCaseIds();
        const statusText = activeStatus.charAt(0).toUpperCase() + activeStatus.slice(1);
        const confirmed = await this.showModernConfirm(
            `Are you sure you want to change active status to "${statusText}" for ${selectedIds.length} case(s)?`,
            'Confirm Active Status Change',
            'info'
        );
        
        if (confirmed) {
            this.closeBulkStatusModal();
            await this.performBulkActiveStatusUpdate(selectedIds, activeStatus);
        }
    }
    
    
    // Bulk modal close methods are defined later (lines 2221-2239) to avoid duplicates
    
    // API call methods
    async performBulkStatusUpdate(caseIds, status) {
        try {
            this.showLoading();
            const response = await this.apiCall('POST', 'bulk-status', {
                case_ids: caseIds,
                status: status
            });
            this.hideLoading();
            
            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                this.showToast(`${response.data.updated_count} cases status updated to "${status.replace('_', ' ')}" successfully!`, 'success');
                this.loadCases();
            } else {
                this.showToast(response.message || 'Failed to update case status', 'error');
            }
        } catch (error) {
            this.hideLoading();
            this.showToast('Error updating case status', 'error');
            console.error('Error updating case status:', error);
        }
    }
    
    async performBulkActiveStatusUpdate(caseIds, activeStatus) {
        try {
            this.showLoading();
            const response = await this.apiCall('POST', 'bulk-active-status', {
                case_ids: caseIds,
                active_status: activeStatus
            });
            this.hideLoading();
            
            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                this.showToast(`${response.data.updated_count} cases active status updated to "${activeStatus}" successfully!`, 'success');
                this.loadCases();
            } else {
                this.showToast(response.message || 'Failed to update case active status', 'error');
            }
        } catch (error) {
            this.hideLoading();
            this.showToast('Error updating case active status', 'error');
            console.error('Error updating case active status:', error);
        }
    }
    
    async performBulkUpdate(caseIds, updates) {
        try {
            this.showLoading();
            const response = await this.apiCall('POST', 'bulk-update', {
                case_ids: caseIds,
                updates: updates
            });
            this.hideLoading();
            
            if (response.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                this.showToast(`${response.data.updated_count} cases updated successfully!`, 'success');
                this.loadCases();
            } else {
                this.showToast(response.message || 'Failed to update cases', 'error');
            }
        } catch (error) {
            this.hideLoading();
            this.showToast('Error updating cases', 'error');
            console.error('Error updating cases:', error);
        }
    }

    async performBulkPriorityUpdate(caseIds, priority) {
        await this.performBulkUpdate(caseIds, { priority: priority });
    }

    async performBulkAssignedUpdate(caseIds, assignedTo) {
        await this.performBulkUpdate(caseIds, { assigned_to: assignedTo });
    }

    async performBulkDueDateUpdate(caseIds, dueDate) {
        await this.performBulkUpdate(caseIds, { due_date: dueDate });
    }
    
    // Proper modal methods with dropdowns
    showBulkPriorityModal(caseIds) {
        const bulkPriorityCount = document.getElementById('bulkPriorityCount');
        const bulkPriorityModal = document.getElementById('bulkPriorityModal');
        const prioritySelect = document.getElementById('prioritySelect');
        if (bulkPriorityCount) bulkPriorityCount.textContent = caseIds.length;
        if (bulkPriorityModal) bulkPriorityModal.classList.add('show');
        if (prioritySelect) prioritySelect.value = '';
    }
    
    showBulkAssignedModal(caseIds) {
        const bulkAssignedCount = document.getElementById('bulkAssignedCount');
        const bulkAssignedModal = document.getElementById('bulkAssignedModal');
        const assignedSelect = document.getElementById('assignedSelect');
        if (bulkAssignedCount) bulkAssignedCount.textContent = caseIds.length;
        if (bulkAssignedModal) bulkAssignedModal.classList.add('show');
        this.loadUsersForAssigned();
        if (assignedSelect) assignedSelect.value = '';
    }
    
    showBulkDueDateModal(caseIds) {
        const bulkDueDateCount = document.getElementById('bulkDueDateCount');
        const bulkDueDateModal = document.getElementById('bulkDueDateModal');
        const dueDateInput = document.getElementById('dueDateInput');
        if (bulkDueDateCount) bulkDueDateCount.textContent = caseIds.length;
        if (bulkDueDateModal) bulkDueDateModal.classList.add('show');
        if (typeof window.initializeEnglishDatePickers === 'function' && bulkDueDateModal) {
            window.initializeEnglishDatePickers(bulkDueDateModal);
        }
        if (dueDateInput) this.setDateInputValue(dueDateInput, '');
    }
    
    // Modal close methods
    closeBulkEditModal() {
        const modal = document.getElementById('bulkEditModal');
        if (modal) modal.classList.remove('show');
    }
    
    closeBulkStatusModal() {
        const modal = document.getElementById('bulkStatusModal');
        if (modal) modal.classList.remove('show');
    }
    
    closeBulkPriorityModal() {
        const modal = document.getElementById('bulkPriorityModal');
        if (modal) modal.classList.remove('show');
    }
    
    closeBulkAssignedModal() {
        const modal = document.getElementById('bulkAssignedModal');
        if (modal) modal.classList.remove('show');
    }
    
    closeBulkDueDateModal() {
        const modal = document.getElementById('bulkDueDateModal');
        const dueDateInput = document.getElementById('dueDateInput');
        if (dueDateInput) this.setDateInputValue(dueDateInput, '');
        if (modal) modal.classList.remove('show');
    }

    // Bulk confirm handlers
    async handleBulkPriorityConfirm() {
        const priority = document.getElementById('prioritySelect').value;
        if (!priority) {
            this.showToast('Please select a priority', 'warning');
            return;
        }
        
        const selectedIds = this.getSelectedCaseIds();
        const confirmed = await this.showModernConfirm(
            `Are you sure you want to change priority to "${priority}" for ${selectedIds.length} case(s)?`,
            'Confirm Priority Change',
            'info'
        );
        
        if (confirmed) {
            this.closeBulkPriorityModal();
            await this.performBulkPriorityUpdate(selectedIds, priority);
        }
    }

    async handleBulkAssignedConfirm() {
        const assignedTo = document.getElementById('assignedSelect').value;
        if (!assignedTo) {
            this.showToast('Please select a user', 'warning');
            return;
        }
        
        const selectedIds = this.getSelectedCaseIds();
        const userText = document.getElementById('assignedSelect').selectedOptions[0].text;
        const confirmed = await this.showModernConfirm(
            `Are you sure you want to assign ${selectedIds.length} case(s) to "${userText}"?`,
            'Confirm Assignment',
            'info'
        );
        
        if (confirmed) {
            this.closeBulkAssignedModal();
            await this.performBulkAssignedUpdate(selectedIds, assignedTo);
        }
    }

    async handleBulkDueDateConfirm() {
        const dueDate = document.getElementById('dueDateInput').value;
        if (!dueDate) {
            this.showToast('Please select a due date', 'warning');
            return;
        }
        
        const selectedIds = this.getSelectedCaseIds();
        const confirmed = await this.showModernConfirm(
            `Are you sure you want to set due date to "${dueDate}" for ${selectedIds.length} case(s)?`,
            'Confirm Due Date Change',
            'info'
        );
        
        if (confirmed) {
            this.closeBulkDueDateModal();
            await this.performBulkDueDateUpdate(selectedIds, dueDate);
        }
    }
    
    async loadUsersForAssigned() {
        await this.loadUsers();
    }
    
    showBulkStatusModal(caseIds) {
        // Use the existing status modal
        document.getElementById('bulkStatusCount').textContent = caseIds.length;
        document.getElementById('bulkStatusModal').classList.add('show');
    }
}

// Initialize the Cases Manager when the page loads
let casesManager;
document.addEventListener('DOMContentLoaded', () => {
    casesManager = new CasesManager();
    // Make it globally available
    window.casesManager = casesManager;
    
    // Check for edit/view parameters and open modals automatically
    const urlParams = new URLSearchParams(window.location.search);
    const editId = urlParams.get('edit');
    const viewId = urlParams.get('view');
    
    // Hide table container when edit/view is present to show form directly
    if (editId || viewId) {
        const casesContainer = document.querySelector('.cases-container');
        const pageHeader = document.querySelector('.page-header');
        const statsGrid = document.querySelector('.stats-grid');
        const casesTableWrapper = document.querySelector('.cases-table-wrapper');
        if (casesContainer) {
            if (pageHeader) pageHeader.style.display = 'none';
            if (statsGrid) statsGrid.style.display = 'none';
            if (casesTableWrapper) casesTableWrapper.style.display = 'none';
        }
    }
    
    if (editId) {
        // Wait for casesManager to be ready, then open edit modal
        setTimeout(() => {
            if (window.casesManager && window.casesManager.editCase) {
                window.casesManager.editCase(parseInt(editId));
            }
        }, 1000);
    } else if (viewId) {
        // Wait for casesManager to be ready, then open view modal
        setTimeout(() => {
            if (window.casesManager && window.casesManager.viewCase) {
                window.casesManager.viewCase(parseInt(viewId));
            }
        }, 1000);
    }
});
