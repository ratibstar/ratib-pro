/**
 * EN: Implements frontend interaction behavior in `js/subagent/form-table.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/subagent/form-table.js`.
 */
// Helper function to get API base URL
function getApiBase() {
    return (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '');
}

// Make formHandler global
window.formHandler = {
    // Form state management
    formState: {
        isEditing: false,
        currentId: null,
        isSubmitting: false,
        modal: null
    },

    // Add state management for filters
    state: {
        statusFilter: 'all',
        searchTerm: '',
        currentPage: 1,
        itemsPerPage: 5,
        totalRecords: 0
    },

    // Add new properties
    cachedData: null,
    isLoading: false,

    // Initialize form handlers
    init() {
        this.loadAgentOptions();
        this.updateStats();  // Initial stats load
        this.displaySubagents();
        this.initModalHandlers();
        this.initFilters();
        this.initPagination();
        this.initBulkActions();
        this.formState.modal = document.getElementById('editForm');
        // Form handler initialized
    },

    async loadAgentOptions() {
        try {
            const response = await fetch(getApiBase() + '/agents/get.php?limit=1000');
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Failed to load agents');
            }

            const agents = data.data?.list || [];
            const agentSelect = document.getElementById('agentSelect');
            
            if (!agentSelect) {
                throw new Error('Agent select element not found');
            }

            // Clear and add default option
                agentSelect.innerHTML = '<option value="">-- Select Agent --</option>';
            
            // Add all agents to dropdown
            agents.forEach(agent => {
                const option = document.createElement('option');
                    option.value = agent.agent_id;
                    option.textContent = `${agent.formatted_id} - ${agent.full_name}`;
                    agentSelect.appendChild(option);
                });

        } catch (error) {
            // 'Error loading agents:', error);
            // Don't show error to user, just log it
        }
    },

    handleSubmit(event) {
        event.preventDefault();
        
        const form = document.getElementById('subagentFormMain');
        if (!form) return;

        const formData = new FormData(form);
        const agentSelect = form.querySelector('#agentSelect');
        const statusSelect = form.querySelector('#subagentStatus');
        
        if (!agentSelect?.value) {
            this.showError('Please select an agent');
            return;
        }

        // Validate email format
        const email = formData.get('email');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            this.showError('Please enter a valid email address (e.g., user@example.com)');
            return;
        }

        // Create subagent data with correct field names
        const subagentData = {
            full_name: formData.get('name'),
            email: email,
            phone: formData.get('phone'),
            city: formData.get('city'),
            address: formData.get('address'),
            agent_id: parseInt(agentSelect.value), // Convert to integer
            status: statusSelect.value || 'active'
        };

        // Send to API
        this.saveSubagent(subagentData);
    },

    async saveSubagent(subagentData) {
        try {
            // Sending subagent data
            
            // Determine if this is an edit or create operation
            const isEditing = this.formState.isEditing;
            const endpoint = isEditing 
                ? `${getApiBase()}/subagents/update.php?id=${this.formState.currentId}`
                : getApiBase() + '/subagents/create.php';
            
            // Add ID to data if editing
            if (isEditing) {
                subagentData.id = this.formState.currentId;
            }

            const response = await fetch(endpoint, {
                method: isEditing ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(subagentData)
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();
            // API Response received

            if (result.success) {
                // Refresh history if UnifiedHistory modal is open
                if (window.unifiedHistory) {
                    await window.unifiedHistory.refreshIfOpen();
                }
                
                this.hideAllModals();
                await this.displaySubagents();
                await this.updateStats();
                this.showSuccess(result.message || `Subagent ${isEditing ? 'updated' : 'created'} successfully`);
        } else {
                throw new Error(result.message || 'Failed to save subagent');
            }
        } catch (error) {
            // 'Error saving subagent:', error);
            this.showError(error.message);
        }
    },

    async displaySubagents() {
        try {
            if (this.isLoading) return;
            this.isLoading = true;

            const tableBody = document.querySelector('.subagent-table tbody');
            if (!tableBody) return;

            // Build URL with all parameters
            const params = new URLSearchParams();
            params.append('page', this.state.currentPage);
            params.append('limit', this.state.itemsPerPage);
            
            if (this.state.statusFilter && this.state.statusFilter !== 'all') {
                params.append('status', this.state.statusFilter);
            }

            if (this.state.searchTerm) {
                params.append('search', this.state.searchTerm);
            }

            const url = `${getApiBase()}/subagents/get.php?${params.toString()}`;
            const response = await fetch(url);
            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Failed to load subagents');
            }

            const subagents = data.data?.subagents || data.subagents || [];
            this.state.totalRecords = data.data?.pagination?.total || data.pagination?.total || 0;
            
            // Update pagination info
            this.updatePaginationInfo();
            
            // Update table content
            this.updateTable(subagents);

        } catch (error) {
            // 'Error:', error);
        } finally {
            this.isLoading = false;
        }
    },

    updateTable(subagents) {
        const tableBody = document.querySelector('.subagent-table tbody');
        if (!tableBody) return;

        if (!subagents.length) {
            tableBody.innerHTML = '<tr><td colspan="10" class="text-center">No subagents found</td></tr>';
            return;
        }

        const html = subagents.map(subagent => `
            <tr data-id="${subagent.id}">
                <td>${subagent.formatted_id || 'SA' + String(subagent.id).padStart(4, '0')}</td>
                <td>${subagent.subagent_name || subagent.full_name || ''}</td>
                <td>${subagent.email || ''}</td>
                <td>${subagent.contact_number || subagent.phone || ''}</td>
                <td>${subagent.city || ''}</td>
                <td>${subagent.address || ''}</td>
                <td>${subagent.agent_name || ''}</td>
                <td>
                    <span class="status-badge ${subagent.status?.toLowerCase() || 'inactive'}">
                        ${subagent.status || 'Inactive'}
                    </span>
                </td>
                <td class="checkbox-cell">
                    <input type="checkbox" class="subagent-checkbox" value="${subagent.id}">
                </td>
                <td class="actions">
                    <button type="button" class="btn btn-info btn-sm" data-action="view-subagent-form" data-id="${subagent.id}">
                            <i class="fas fa-eye"></i>
                        </button>
                    <button type="button" class="btn btn-primary btn-sm" data-action="edit-subagent-form" data-id="${subagent.id}">
                            <i class="fas fa-edit"></i>
                        </button>
                    <button type="button" class="btn btn-danger btn-sm" data-action="delete-subagent-form" data-id="${subagent.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                </td>
            </tr>
        `).join('');

        tableBody.innerHTML = html;
        this.initBulkActions();
    },

    updatePaginationInfo() {
        const startRecord = (this.state.currentPage - 1) * this.state.itemsPerPage + 1;
        const endRecord = Math.min(startRecord + this.state.itemsPerPage - 1, this.state.totalRecords);
        const totalPages = Math.ceil(this.state.totalRecords / this.state.itemsPerPage);
        
        // Update pagination info
        ['Top', 'Bottom'].forEach(position => {
            document.getElementById(`startRecord${position}`).textContent = startRecord;
            document.getElementById(`endRecord${position}`).textContent = endRecord;
            document.getElementById(`totalRecords${position}`).textContent = this.state.totalRecords;
            
            // Add pagination controls
            const controls = document.getElementById(`pagination${position}`);
            if (controls) {
                controls.innerHTML = this.generatePaginationControls(totalPages);
            }
        });
    },

    generatePaginationControls(totalPages) {
        const currentPage = this.state.currentPage;
        let html = '';
            
            // Previous button
        html += `<button class="page-btn" ${currentPage === 1 ? 'disabled' : ''} 
            data-action="change-page" data-page="${currentPage - 1}">
                <i class="fas fa-chevron-left"></i>
            </button>`;

            // Page numbers
        html += '<div class="page-numbers">';
            for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += `<button class="page-btn active">${i}</button>`;
            } else {
                html += `<button class="page-btn" data-action="change-page" data-page="${i}">${i}</button>`;
            }
        }
        html += '</div>';

            // Next button
        html += `<button class="page-btn" ${currentPage === totalPages ? 'disabled' : ''} 
            data-action="change-page" data-page="${currentPage + 1}">
                <i class="fas fa-chevron-right"></i>
            </button>`;

        return html;
    },

    changePage(page) {
        if (page < 1) return;
        this.state.currentPage = page;
        this.displaySubagents();
    },

    changeItemsPerPage(newSize) {
        this.state.itemsPerPage = parseInt(newSize);
        this.state.currentPage = 1; // Reset to first page
        this.displaySubagents();
        
        // Update both page size selectors
        const pageSizeTop = document.getElementById('pageSizeTop');
        const pageSizeBottom = document.getElementById('pageSizeBottom');
        
        if (pageSizeTop) pageSizeTop.value = newSize;
        if (pageSizeBottom) pageSizeBottom.value = newSize;
    },

    getNextSubagentId() {
        const subagents = JSON.parse(localStorage.getItem('subagents')) || [];
        if (subagents.length === 0) return 'SA0001';
        
        // Find the highest ID number
        let maxNum = 0;
        subagents.forEach(subagent => {
            if (subagent.id && typeof subagent.id === 'string' && subagent.id.startsWith('SA')) {
                const num = parseInt(subagent.id.substring(2));
                if (!isNaN(num) && num > maxNum) {
                    maxNum = num;
                }
            }
        });
        
        // Generate next ID with padding
        return `SA${(maxNum + 1).toString().padStart(4, '0')}`;
    },

    // Form validation
    initFormValidation() {
        const form = document.getElementById('subagentForm');
        const inputs = form.querySelectorAll('input, select');

        inputs.forEach(input => {
            input.addEventListener('input', () => this.validateField(input));
            input.addEventListener('blur', () => this.validateField(input));
        });
    },

    validateField(field) {
        const errorElement = this.getOrCreateErrorElement(field);
        
        if (!field.checkValidity()) {
            field.classList.add('invalid');
            errorElement.textContent = field.validationMessage;
            errorElement.classList.add('error-visible');
            return false;
        } else {
            field.classList.remove('invalid');
            errorElement.classList.remove('error-visible');
            return true;
        }
    },

    getOrCreateErrorElement(field) {
        const errorId = `${field.id}-error`;
        let errorElement = document.getElementById(errorId);
        
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.id = errorId;
            errorElement.className = 'error-message';
            field.parentNode.appendChild(errorElement);
        }
        
        return errorElement;
    },

    // Input masks
    initInputMasks() {
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 0) {
                    value = value.match(new RegExp('.{1,4}', 'g')).join('-');
                }
                e.target.value = value;
            });
        }
    },

    // UI State Management
    toggleLoadingState(isLoading) {
        const submitBtn = document.querySelector('.modal-btn.save');
        if (submitBtn) {
            submitBtn.disabled = isLoading;
            submitBtn.innerHTML = isLoading 
                ? '<i class="fas fa-spinner fa-spin"></i> Saving...'
                : '<i class="fas fa-save"></i> Save';
        }
    },

    showNotification(message, type = 'success') {
        // Notification: ${type}: ${message}
        // Add your notification UI code here
    },

    // Form Reset
    resetForm(formId) {
        const form = document.getElementById(formId);
        if (form) {
            form.reset();
            form.querySelectorAll('.invalid').forEach(field => {
                field.classList.remove('invalid');
            });
            form.querySelectorAll('.error-message').forEach(error => {
                error.classList.remove('error-visible');
            });
        }
        this.formState.isEditing = false;
        this.formState.currentId = null;
    },

    // Modal Management
    showAddForm() {
        const form = document.getElementById('subagentFormMain');
        if (form) {
            form.reset();
            this.formState.isEditing = false;
            this.formState.currentId = null;
        
        const formTitle = document.getElementById('formTitle');
            if (formTitle) formTitle.textContent = 'Add New Subagent';

            this.showModal('editForm');
        }
    },

    async editSubagent(id) {
        try {
            // Editing subagent
            const response = await fetch(`${getApiBase()}/subagents/get.php?id=${id}`);
            const data = await response.json();

            if (!data.success || !data.data?.subagents?.[0]) {
                throw new Error('Failed to load subagent data');
            }

            const subagent = data.data.subagents[0];
            const form = document.getElementById('subagentFormMain');
            
            if (!form) {
                throw new Error('Form not found');
            }

            // First hide the view modal
            this.hideModal('viewSubagentModal');

            // Update form state
            this.formState.isEditing = true;
            this.formState.currentId = id;

            // Update form title
        const formTitle = document.getElementById('formTitle');
            if (formTitle) formTitle.textContent = 'Edit Subagent';

            // Fill form fields
            form.querySelector('[name="name"]').value = subagent.subagent_name || subagent.full_name || '';
            form.querySelector('[name="email"]').value = subagent.email || '';
            form.querySelector('[name="phone"]').value = subagent.contact_number || subagent.phone || '';
            form.querySelector('[name="city"]').value = subagent.city || '';
            form.querySelector('[name="address"]').value = subagent.address || '';
            
            // Load agents first if needed
            await this.loadAgentOptions();
            
            // Set the agent in dropdown
            const agentSelect = form.querySelector('#agentSelect');
            if (agentSelect) {
                // Make sure the agent option exists
                let agentOption = agentSelect.querySelector(`option[value="${subagent.agent_id}"]`);
                if (!agentOption) {
                    // If the agent option doesn't exist, create it
                    agentOption = document.createElement('option');
                    agentOption.value = subagent.agent_id;
                    agentOption.textContent = `${subagent.agent_formatted_id || ''} - ${subagent.agent_name || ''}`;
                    agentSelect.appendChild(agentOption);
                }
                agentSelect.value = subagent.agent_id;
            }

            const statusSelect = form.querySelector('#subagentStatus');
            if (statusSelect) statusSelect.value = subagent.status?.toLowerCase() || 'active';

            // Show edit form modal
            this.showModal('editForm');

        } catch (error) {
            // 'Error:', error);
            alert(error.message);
        }
    },

    hideForm() {
        const form = document.getElementById('subagentFormMain');
        if (form) {
            form.reset();
            this.formState.isEditing = false;
            this.formState.currentId = null;
        }
        this.hideModal('editForm');
    },

    // Event Handlers
    initClickOutside() {
        document.addEventListener('mousedown', (e) => {
            const modal = document.getElementById('editForm');
            const modalContent = modal?.querySelector('.modal-content');
            
            if (modal && modalContent && !modalContent.contains(e.target) && modal.classList.contains('subagent-modal-visible')) {
                this.hideForm();
            }
        });
    },

    initEscapeKey() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modal = document.getElementById('editForm');
                if (modal && modal.classList.contains('subagent-modal-visible')) {
                    this.hideForm();
                }
            }
        });
    },

    async viewSubagent(id) {
        try {
            // Viewing subagent
            const response = await fetch(`${getApiBase()}/subagents/get.php?id=${id}`);
            const data = await response.json();

            if (!data.success || !data.data?.subagents?.[0]) {
                throw new Error('Failed to load subagent details');
            }

            const subagent = data.data.subagents[0];
            const detailsContainer = document.getElementById('viewSubagentDetails');
            
            if (!detailsContainer) {
                throw new Error('View details container not found');
            }

            // Store ID for edit button
            window.currentSubagentId = id;

            // Generate details HTML
            detailsContainer.innerHTML = `
                <div class="details-row"><label>ID:</label><span>${subagent.formatted_id || ''}</span></div>
                <div class="details-row"><label>Name:</label><span>${subagent.full_name || ''}</span></div>
                <div class="details-row"><label>Email:</label><span>${subagent.email || ''}</span></div>
                <div class="details-row"><label>Phone:</label><span>${subagent.phone || ''}</span></div>
                <div class="details-row"><label>City:</label><span>${subagent.city || ''}</span></div>
                <div class="details-row"><label>Address:</label><span>${subagent.address || ''}</span></div>
                <div class="details-row"><label>Agent:</label><span>${subagent.agent_name || ''}</span></div>
                <div class="details-row">
                    <label>Status:</label>
                    <span class="status-badge ${subagent.status?.toLowerCase()}">${subagent.status || 'Inactive'}</span>
                </div>
            `;

            // Show modal
            this.showModal('viewSubagentModal');

        } catch (error) {
            // 'Error:', error);
            alert(error.message);
        }
    },

    confirmDelete(id) {
        if (confirm('Are you sure you want to delete this subagent?')) {
            this.deleteSubagent(id);
        }
    },

    async deleteSubagent(id) {
        try {
            const response = await fetch(getApiBase() + '/subagents/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: [id] })
            });

            const data = await response.json();
            if (data.success) {
                await this.displaySubagents();
                await this.updateStats();
                alert('Subagent deleted successfully');
            } else {
                throw new Error(data.message || 'Failed to delete subagent');
            }
        } catch (error) {
            // 'Error:', error);
            alert(error.message);
        }
    },

    // Modal click outside handler
    initModalHandlers() {
        // Close modal when clicking outside
        window.onclick = (event) => {
            if (event.target.classList.contains('modal')) {
                this.confirmClose();
            }
        };

        // Close modal when clicking close button
        document.querySelectorAll('.close-modal').forEach(button => {
            button.onclick = () => {
                this.confirmClose();
            };
        });

        // Prevent modal close when clicking modal content
        document.querySelectorAll('.modal-content').forEach(content => {
            content.onclick = (event) => {
                event.stopPropagation();
            };
        });
    },

    hideAllModals() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.classList.add('subagent-modal-hidden');
            modal.classList.remove('subagent-modal-visible');
        });
        
        // Reset form if it exists
        const form = document.getElementById('subagentFormMain');
        if (form) {
            form.reset();
            this.formState.isEditing = false;
            this.formState.currentId = null;
        }
        
        // Clear any error messages
        const errorContainer = document.getElementById('formErrorContainer');
        if (errorContainer) {
            errorContainer.classList.remove('error-visible');
        }
    },

    showModal(modalId) {
        const modal = document.getElementById(modalId);
                if (modal) {
            modal.classList.remove('subagent-modal-hidden');
            modal.classList.add('subagent-modal-visible');
            // Add click handler to modal backdrop
            modal.onclick = (event) => {
                if (event.target === modal) {
                    this.confirmClose();
                }
            };
        }
    },

    hideModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('subagent-modal-hidden');
            modal.classList.remove('subagent-modal-visible');
            modal.onclick = null; // Remove click handler
        }
    },

    // Add error handling methods
    showError(message) {
        const errorContainer = document.getElementById('formErrorContainer');
        if (errorContainer) {
            errorContainer.textContent = message;
            errorContainer.classList.add('error-visible');
        } else {
            alert(message); // Fallback to alert if container not found
        }
    },

    showSuccess(message) {
        // You could create a success container or use alert
        alert(message);
    },

    toggleSelectAll(source) {
        const checkboxes = document.querySelectorAll('.bulk-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = source.checked;
        });
    },

    activateSelected() {
        const selectedIds = this.getSelectedIds();
        if (selectedIds.length === 0) {
            this.showError('Please select at least one subagent to activate');
            return;
        }

        if (confirm(`Are you sure you want to activate ${selectedIds.length} subagent(s)?`)) {
            this.updateSubagentsStatus(selectedIds, 'active');
        }
    },

    deactivateSelected() {
        const selectedIds = this.getSelectedIds();
        if (selectedIds.length === 0) {
            this.showError('Please select at least one subagent to deactivate');
            return;
        }

        if (confirm(`Are you sure you want to deactivate ${selectedIds.length} subagent(s)?`)) {
            this.updateSubagentsStatus(selectedIds, 'inactive');
        }
    },

    getSelectedIds() {
        const checkboxes = document.querySelectorAll('.bulk-checkbox:checked');
        return Array.from(checkboxes).map(cb => cb.getAttribute('data-id'));
    },

    async updateSubagentsStatus(ids, status) {
        try {
            const response = await fetch(getApiBase() + '/subagents/update-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ ids, status })
            });

            const result = await response.json();

            if (result.success) {
                this.showSuccess(result.message || `Successfully ${status}d ${ids.length} subagent(s)`);
                await this.displaySubagents();
                await this.updateStats();  // Update stats after status change
                this.uncheckAllCheckboxes();
            } else {
                throw new Error(result.error);
            }
        } catch (error) {
            // 'Error updating status:', error);
            this.showError(error.message);
        }
    },

    async updateStats() {
        try {
            const response = await fetch(getApiBase() + '/subagents/stats.php');
            const data = await response.json();
            
            if (data.success) {
                document.getElementById('totalCount').textContent = data.data.total;
                document.getElementById('activeCount').textContent = data.data.active;
                document.getElementById('inactiveCount').textContent = data.data.inactive;
            }
        } catch (error) {
            // 'Error updating stats:', error);
        }
    },

    async refreshTable() {
        try {
            // Refreshing table
            await this.displaySubagents();
            await this.updateStats();
            // Table refresh completed
        } catch (error) {
            // 'Error refreshing table:', error);
        }
    },

    // Add filter initialization
    initFilters() {
        // Status filter
        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                this.state.statusFilter = e.target.value;
                this.state.currentPage = 1;  // Reset to first page
                this.displaySubagents();
            });
        }

        // Search filter with debounce
        const searchInput = document.getElementById('subagentSearch');
        if (searchInput) {
            let debounceTimer;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    const searchTerm = e.target.value.trim();
                    if (searchTerm !== this.state.searchTerm) {
                        this.state.searchTerm = searchTerm;
                        this.state.currentPage = 1;  // Reset to first page
                this.displaySubagents();
                    }
                }, 300);  // 300ms debounce delay
            });

            // Add search on Enter key
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    const searchTerm = e.target.value.trim();
                    this.state.searchTerm = searchTerm;
                    this.state.currentPage = 1;
                    this.displaySubagents();
                }
            });
        }
    },

    initPagination() {
        // Add event listeners to page size selectors
        const pageSizeTop = document.getElementById('pageSizeTop');
        const pageSizeBottom = document.getElementById('pageSizeBottom');
        
        [pageSizeTop, pageSizeBottom].forEach(select => {
            if (select) {
                select.addEventListener('change', (e) => {
                    const newSize = e.target.value;
                    this.changeItemsPerPage(newSize);
                    
                    // Sync the other selector
                    const otherSelector = e.target.id === 'pageSizeTop' ? pageSizeBottom : pageSizeTop;
                    if (otherSelector) otherSelector.value = newSize;
                });
            }
        });
    },

    // Add bulk action initialization
    initBulkActions() {
        // Add event listeners to checkboxes
        document.querySelectorAll('.subagent-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', () => {
                this.updateBulkButtons();
            });
        });

        // Add event listener to select all checkbox
        const selectAllCheckbox = document.querySelector('.bulk-checkbox-all');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                this.toggleAllCheckboxes(e.target);
            });
        }

        // Initial update of button states
        this.updateBulkButtons();
    },

    // Update bulk buttons state
    updateBulkButtons() {
        const checkedBoxes = document.querySelectorAll('.subagent-checkbox:checked');
        const bulkButtons = document.querySelectorAll('.bulk-btn');
        
        bulkButtons.forEach(button => {
            button.disabled = checkedBoxes.length === 0;
            button.classList.toggle('disabled', checkedBoxes.length === 0);
        });
    },

    // Toggle all checkboxes
    toggleAllCheckboxes(source) {
        const checkboxes = document.querySelectorAll('.subagent-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = source.checked;
        });
        this.updateBulkButtons();
    },

    // Add these bulk action methods to formHandler
    bulkActivate() {
        const selectedIds = this.getSelectedSubagentIds();
        if (selectedIds.length === 0) {
            alert('Please select at least one subagent to activate');
            return;
        }

        if (confirm(`Are you sure you want to activate ${selectedIds.length} subagent(s)?`)) {
            this.updateSubagentsStatus(selectedIds, 'active');
        }
    },

    bulkDeactivate() {
        const selectedIds = this.getSelectedSubagentIds();
        if (selectedIds.length === 0) {
            alert('Please select at least one subagent to deactivate');
            return;
        }

        if (confirm(`Are you sure you want to deactivate ${selectedIds.length} subagent(s)?`)) {
            this.updateSubagentsStatus(selectedIds, 'inactive');
        }
    },

    deleteSelected() {
        const selectedIds = this.getSelectedSubagentIds();
        if (selectedIds.length === 0) {
            alert('Please select at least one subagent');
            return;
        }

        if (confirm(`Are you sure you want to delete ${selectedIds.length} subagent(s)? This cannot be undone.`)) {
            this.deleteSubagents(selectedIds);
        }
    },

    async deleteSubagents(ids) {
        try {
            if (!ids || !ids.length) {
                throw new Error('No subagents selected');
            }

            const response = await fetch(getApiBase() + '/subagents/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ids: ids.map(id => parseInt(id)) })
            });

            const data = await response.json();
            if (data.success) {
                await this.displaySubagents();
                await this.updateStats();  // Update stats after delete
                this.uncheckAllCheckboxes();
                alert('Successfully deleted selected subagents');
            } else {
                throw new Error(data.message || 'Failed to delete subagents');
            }
        } catch (error) {
            // 'Error:', error);
            alert(error.message);
        }
    },

    uncheckAllCheckboxes() {
        const selectAllCheckbox = document.querySelector('.bulk-checkbox-all');
        if (selectAllCheckbox) selectAllCheckbox.checked = false;
        
        const checkboxes = document.querySelectorAll('.subagent-checkbox');
        checkboxes.forEach(cb => cb.checked = false);
        
        this.updateBulkButtons();
    },

    getSelectedSubagentIds() {
        const checkboxes = document.querySelectorAll('.subagent-checkbox:checked');
        return Array.from(checkboxes).map(cb => {
            // Try to get ID from data attribute first, then value
            return cb.dataset.id || cb.value;
        }).filter(id => id); // Filter out any undefined/null/empty values
    },

    // Update these methods in formHandler
    confirmClose() {
        let message = 'Are you sure you want to close?';
        const form = document.getElementById('subagentFormMain');
        
        // Check which modal is open
        const editForm = document.getElementById('editForm');
        const viewModal = document.getElementById('viewSubagentModal');
        const accountModal = document.getElementById('accountDetailsModal');
        const editFormVisible = editForm && editForm.classList.contains('subagent-modal-visible');
        const viewModalVisible = viewModal && viewModal.classList.contains('subagent-modal-visible');
        const accountModalVisible = accountModal && accountModal.classList.contains('subagent-modal-visible');
        
        if (editFormVisible && form) {
            // Check if form has been modified
            const hasChanges = this.checkFormChanges(form);
            if (hasChanges) {
                message = 'You have unsaved changes. Are you sure you want to close?';
        } else {
                message = 'Are you sure you want to close the form?';
            }
        } else if (viewModalVisible) {
            message = 'Are you sure you want to close the details view?';
        } else if (accountModalVisible) {
            message = 'Are you sure you want to close the account details?';
        }

        if (confirm(message)) {
            this.hideAllModals();
        }
    },

    checkFormChanges(form) {
        // Check if any field has been modified
        const formElements = form.elements;
        for (let element of formElements) {
            if (element.type === 'text' || element.type === 'email' || 
                element.type === 'tel' || element.type === 'textarea') {
                if (element.value.trim() !== '') return true;
            } else if (element.type === 'select-one') {
                if (element.value !== '' && 
                    element.value !== element.options[0].value) return true;
            }
        }
        return false;
    }
};

// Initialize when the document is ready
document.addEventListener('DOMContentLoaded', () => {
    formHandler.init();
}); 
