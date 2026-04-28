/**
 * EN: Implements frontend interaction behavior in `js/unified-history.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/unified-history.js`.
 */
/**
 * Unified History Viewer - Select module and view its history
 */
class UnifiedHistory {
    constructor() {
        this.modules = [
            { value: 'all', label: 'All Modules', icon: 'layer-group', isAll: true },
            { value: 'agents', label: 'Agents', icon: 'users' },
            { value: 'subagents', label: 'SubAgents', icon: 'user-friends' },
            { value: 'workers', label: 'Workers', icon: 'hard-hat' },
            { value: 'cases', label: 'Cases', icon: 'folder' },
            { value: 'accounting', label: 'Accounting', icon: 'dollar-sign' },
            { value: 'hr', label: 'HR', icon: 'briefcase' },
            { value: 'contacts', label: 'Contacts', icon: 'address-book' },
            { value: 'communications', label: 'Communications', icon: 'comments' },
            { value: 'notifications', label: 'Notifications', icon: 'bell', isNotification: true },
            { value: 'settings', label: 'System Settings', icon: 'cog' }
        ];
        this.currentModule = 'all';
        this.currentPage = 1;
        this.perPage = 20;
        this.totalRecords = 0;
        this.searchQuery = '';
        this.actionFilter = 'all';
        this.filterUserId = null; // Filter by user ID (null = show all users)
        this.foreignKeyCache = new Map(); // Cache for resolved foreign keys
        this.initModal();
    }
    
    initModal() {
        let modal = document.getElementById('unifiedHistoryModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'unifiedHistoryModal';
            modal.className = 'modern-modal modal-hidden';
            modal.innerHTML = `
                <div class="modern-modal-content history-modal-content">
                    <div class="modern-modal-header">
                        <h2 class="modern-modal-title">Activity History</h2>
                        <button class="modal-close" data-action="close-unified-history">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="history-controls-bar">
                        <div class="history-controls-row">
                            <label class="history-module-label">
                                <i class="fas fa-filter"></i>
                                Select Module to View History:
                            </label>
                            <div id="moduleHistoryStats" class="history-stats-display">
                                <span class="history-stat-item">
                                    <i class="fas fa-database"></i> Total: <strong id="moduleTotalCount">-</strong>
                                </span>
                                <span class="history-stat-item">
                                    Showing: <strong id="showingCount">-</strong> of <strong id="totalRecordsCount">-</strong>
                                </span>
                            </div>
                        </div>
                        <div class="history-filters-row">
                            <div class="history-search-wrapper">
                                <i class="fas fa-search history-search-icon"></i>
                                <input type="text" id="historySearchInput" placeholder="Search history..." class="history-search-input">
                            </div>
                            <select id="historyActionFilter" class="history-filter-select">
                                <option value="all">All Actions</option>
                                <option value="create">Created</option>
                                <option value="update">Updated</option>
                                <option value="delete">Deleted</option>
                            </select>
                            <select id="historyPerPage" class="history-filter-select">
                                <option value="10">10 per page</option>
                                <option value="20" selected>20 per page</option>
                                <option value="50">50 per page</option>
                                <option value="100">100 per page</option>
                            </select>
                        </div>
                        <div id="historyPagination" class="history-pagination-container">
                            <!-- Pagination buttons will be added here -->
                        </div>
                        <div class="history-module-buttons">
                            ${this.modules.map(m => {
                                return `
                                    <div class="history-module-button-wrapper">
                                        <button class="history-module-btn ${m.isNotification ? 'history-module-btn-notification' : ''} ${m.isAll ? 'history-module-btn-all' : ''}" 
                                                data-module="${m.value}">
                                            <i class="fas fa-${m.icon}"></i>
                                            <span>${m.label}</span>
                                        </button>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                    <div id="unifiedHistoryBody" class="history-modal-body">
                        <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading history...</p></div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Module button clicks
            const moduleButtons = modal.querySelectorAll('.history-module-btn');
            moduleButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const module = e.currentTarget.getAttribute('data-module');
                    if (module) {
                        // Update active state
                        moduleButtons.forEach(b => {
                            b.classList.remove('history-module-btn-active');
                        });
                        e.currentTarget.classList.add('history-module-btn-active');
                        
                        this.currentModule = module;
                        this.currentPage = 1; // Reset to first page
                        this.loadAndRenderHistory();
                    }
                });
            });
            
            // Search input
            const searchInput = modal.querySelector('#historySearchInput');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.searchQuery = e.target.value.trim();
                        this.currentPage = 1; // Reset to first page on search
                        this.loadAndRenderHistory();
                    }, 300);
                });
            }
            
            // Action filter
            const actionFilter = modal.querySelector('#historyActionFilter');
            if (actionFilter) {
                actionFilter.addEventListener('change', (e) => {
                    this.actionFilter = e.target.value;
                    this.currentPage = 1; // Reset to first page on filter change
                    this.loadAndRenderHistory();
                });
            }
            
            // Per page selector
            const perPageSelect = modal.querySelector('#historyPerPage');
            if (perPageSelect) {
                perPageSelect.addEventListener('change', (e) => {
                    this.perPage = parseInt(e.target.value);
                    this.currentPage = 1; // Reset to first page
                    this.loadAndRenderHistory();
                });
            }
            
            // Close events
            modal.addEventListener('click', (e) => {
                if (e.target.hasAttribute('data-action') && e.target.getAttribute('data-action') === 'close-unified-history') {
                    this.closeModal();
                } else if (e.target.closest('.modal-close')) {
                    this.closeModal();
                    } else if (e.target.classList.contains('modern-modal') && !e.target.closest('.modern-modal-content')) {
                    this.closeModal();
                }
            });
            
            // Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.classList.contains('modal-hidden')) {
                    this.closeModal();
                }
            });
        }
        
        this.modal = modal;
    }
    
    async openModal(initialModule = 'all', userId = null) {
        // Check permission before opening modal
        if (window.UserPermissions && window.UserPermissions.loaded) {
            // Check for view_system_history or view_module_history permission
            const hasSystemHistoryPermission = window.UserPermissions.has('view_system_history');
            const hasModuleHistoryPermission = window.UserPermissions.has('view_module_history');
            
            if (!hasSystemHistoryPermission && !hasModuleHistoryPermission) {
                if (window.SystemSettingsAlert) {
                    await window.SystemSettingsAlert.error(
                        'You do not have permission to view system history.',
                        'Access Denied'
                    );
                } else {
                    alert('You do not have permission to view system history.');
                }
                return;
            }
        }
        
        // Set user filter if provided (for profile page)
        this.filterUserId = userId;
        
        // Set active module button
        this.currentModule = initialModule;
        const moduleButtons = this.modal.querySelectorAll('.history-module-btn');
        moduleButtons.forEach(btn => {
            const module = btn.getAttribute('data-module');
            if (module === initialModule) {
                btn.classList.add('history-module-btn-active');
            } else {
                btn.classList.remove('history-module-btn-active');
            }
        });
        
        const title = this.modal.querySelector('.modern-modal-title');
        if (title) {
            if (userId) {
                title.textContent = 'My Activity History - Select Module';
            } else {
            title.textContent = 'Activity History - Select Module';
            }
        }
        
        const body = document.getElementById('unifiedHistoryBody');
        if (body) {
            body.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading history...</p></div>';
        }
        
        this.modal.classList.remove('modal-hidden');
        this.modal.classList.add('show');
        document.body.classList.add('modal-open');
        
        await this.loadAndRenderHistory();
    }
    
    async loadAndRenderHistory() {
        try {
            const body = document.getElementById('unifiedHistoryBody');
            if (body) {
                body.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading history...</p></div>';
            }
            
            const [historyResult, stats] = await Promise.all([
                this.loadHistory(),
                this.loadStats()
            ]);
            
            // Get history and total count
            const history = historyResult.history || [];
            this.totalRecords = historyResult.total || 0;
            
            await this.renderHistory(history);
            this.renderStats(stats);
            this.renderPagination();
        } catch (error) {
            const body = document.getElementById('unifiedHistoryBody');
            if (body) {
                body.innerHTML = `<div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>Failed to load history: ${error.message}</p></div>`;
            }
        }
    }
    
    /**
     * API root (e.g. https://host/api). Prefer data-api-base / APP_CONFIG.apiBase — same as modern-forms.js.
     * Avoids broken URLs like .../pages/api/... when baseUrl incorrectly includes /pages.
     */
    _apiBase() {
        let api = (window.APP_CONFIG && window.APP_CONFIG.apiBase) || (typeof window.API_BASE === 'string' ? window.API_BASE : '');
        if (typeof api === 'string' && api.trim()) {
            api = api.trim().replace(/\/+$/, '');
            if (/\/pages\/api$/i.test(api)) {
                api = api.replace(/\/pages\/api$/i, '/api');
            }
            return api;
        }
        let base = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '') || '';
        base = String(base).replace(/\/+$/, '');
        if (/\/pages$/i.test(base)) {
            base = base.replace(/\/pages$/i, '');
        }
        return base ? `${base}/api` : '/api';
    }

    _controlSuffix() {
        const el = document.getElementById('app-config');
        if (el && (el.getAttribute('data-control') === '1' || el.getAttribute('data-control-pro-bridge') === '1')) {
            return '&control=1';
        }
        return '';
    }
    
    async loadHistory() {
        try {
            const apiBase = this._apiBase();
            let url;
            
            // If "all" is selected, use global-history-api.php to get all modules
            // Use a very high limit to get all records (pagination handled client-side)
            if (this.currentModule === 'all') {
                url = `${apiBase}/core/global-history-api.php?action=get_history&limit=10000&offset=0${this._controlSuffix()}`;
                if (this.filterUserId) {
                    url += `&user_id=${encodeURIComponent(this.filterUserId)}`;
                }
            } else {
                // Use module-specific API for individual modules
                url = `${apiBase}/core/module-history-api.php?action=get_history&module=${encodeURIComponent(this.currentModule)}&limit=10000&offset=0${this._controlSuffix()}`;
                if (this.filterUserId) {
                    url += `&user_id=${encodeURIComponent(this.filterUserId)}`;
                }
            }
            
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const text = await response.text();
            if (!text.trim()) {
                return { history: [], total: 0 };
            }
            
            const data = JSON.parse(text);
            if (data.success) {
                let history = data.data || [];
                
                // Apply action filter
                if (this.actionFilter !== 'all') {
                    history = history.filter(item => item.action === this.actionFilter);
                }
                
                // Apply search filter
                if (this.searchQuery) {
                    const query = this.searchQuery.toLowerCase();
                    history = history.filter(item => {
                        const searchableText = JSON.stringify(item).toLowerCase();
                        return searchableText.includes(query);
                    });
                }
                
                // Store total for pagination
                const total = history.length;
                
                // Apply pagination
                const startIdx = (this.currentPage - 1) * this.perPage;
                const endIdx = startIdx + this.perPage;
                const paginatedHistory = history.slice(startIdx, endIdx);
                
                return { history: paginatedHistory, total: total };
            }
            throw new Error(data.message || 'Failed to load history');
        } catch (error) {
            console.error('History load error:', error);
            return { history: [], total: 0 };
        }
    }
    
    async loadStats() {
        try {
            const apiBase = this._apiBase();
            let url;
            // If "all" is selected, use global-history-api.php for stats
            if (this.currentModule === 'all') {
                url = `${apiBase}/core/global-history-api.php?action=get_stats${this._controlSuffix()}`;
                if (this.filterUserId) {
                    url += `&user_id=${encodeURIComponent(this.filterUserId)}`;
                }
            } else {
                url = `${apiBase}/core/module-history-api.php?action=get_stats&module=${encodeURIComponent(this.currentModule)}${this._controlSuffix()}`;
                if (this.filterUserId) {
                    url += `&user_id=${encodeURIComponent(this.filterUserId)}`;
                }
            }
            
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (!response.ok) {
                return { total: 0, creates: 0, updates: 0, deletes: 0, today: 0 };
            }
            
            const text = await response.text();
            if (!text.trim()) {
                return { total: 0, creates: 0, updates: 0, deletes: 0, today: 0 };
            }
            
            const data = JSON.parse(text);
            if (data.success) {
                return data.data || { total: 0, creates: 0, updates: 0, deletes: 0, today: 0 };
            }
            return { total: 0, creates: 0, updates: 0, deletes: 0, today: 0 };
        } catch (error) {
            console.error('Stats load error:', error);
            return { total: 0, creates: 0, updates: 0, deletes: 0, today: 0 };
        }
    }
    
    renderStats(stats) {
        const totalElement = document.getElementById('moduleTotalCount');
        const showingElement = document.getElementById('showingCount');
        const totalRecordsElement = document.getElementById('totalRecordsCount');
        
        if (totalElement) {
            totalElement.textContent = stats.total || 0;
        }
        
        // Calculate showing range
        const start = (this.currentPage - 1) * this.perPage + 1;
        const end = Math.min(this.currentPage * this.perPage, this.totalRecords);
        const showing = this.totalRecords > 0 ? `${start}-${end}` : '0';
        
        if (showingElement) {
            showingElement.textContent = showing;
        }
        if (totalRecordsElement) {
            totalRecordsElement.textContent = this.totalRecords || 0;
        }
        
        // Update title
        const module = this.modules.find(m => m.value === this.currentModule);
        const title = this.modal.querySelector('.modern-modal-title');
        if (title && module) {
            if (module.value === 'all') {
                title.textContent = `All Program History (${stats.total || 0} total records)`;
            } else {
            title.textContent = `${module.label} History (${stats.total || 0} total records)`;
            }
        }
    }
    
    renderPagination() {
        const pagination = document.getElementById('historyPagination');
        if (!pagination) return;
        
        const totalPages = Math.ceil(this.totalRecords / this.perPage);
        
        if (totalPages <= 1) {
            pagination.innerHTML = '';
            return;
        }
        
        let html = '';
        
        // Previous button
        const prevDisabled = this.currentPage === 1 ? 'disabled' : '';
        html += `<button class="history-page-btn history-page-btn-prev" data-page="${this.currentPage - 1}" ${prevDisabled}><i class="fas fa-chevron-left"></i> Previous</button>`;
        
        // Page numbers
        const maxVisible = 5;
        let startPage = Math.max(1, this.currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);
        
        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }
        
        if (startPage > 1) {
            html += `<button class="history-page-btn history-page-number" data-page="1">1</button>`;
            if (startPage > 2) {
                html += `<span class="history-page-ellipsis">...</span>`;
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === this.currentPage ? 'history-page-btn-active' : '';
            html += `<button class="history-page-btn history-page-number ${isActive}" data-page="${i}">${i}</button>`;
        }
        
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<span class="history-page-ellipsis">...</span>`;
            }
            html += `<button class="history-page-btn history-page-number" data-page="${totalPages}">${totalPages}</button>`;
        }
        
        // Next button
        const nextDisabled = this.currentPage === totalPages ? 'disabled' : '';
        html += `<button class="history-page-btn history-page-btn-next" data-page="${this.currentPage + 1}" ${nextDisabled}>Next <i class="fas fa-chevron-right"></i></button>`;
        
        pagination.innerHTML = html;
        
        // Bind page button clicks
        pagination.querySelectorAll('.history-page-btn:not([disabled])').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const page = parseInt(e.currentTarget.getAttribute('data-page'));
                if (page && page !== this.currentPage) {
                    this.currentPage = page;
                    this.loadAndRenderHistory();
                    // Scroll to top of history list
                    const body = document.getElementById('unifiedHistoryBody');
                    if (body) {
                        body.scrollTop = 0;
                    }
                }
            });
        });
    }
    
    async renderHistory(history) {
        const body = document.getElementById('unifiedHistoryBody');
        if (!body) return;
        
        if (!history || history.length === 0) {
            body.innerHTML = '<div class="empty-state"><i class="fas fa-history"></i><p>No history records found for this module</p></div>';
            return;
        }
        
        // Process all items (with async foreign key resolution)
        const itemPromises = history.map(async (item, index) => {
            const actionIcon = item.action === 'create' ? 'plus-circle' : item.action === 'update' ? 'edit' : 'trash';
            const actionLabel = item.action.charAt(0).toUpperCase() + item.action.slice(1);
            const actionClass = item.action === 'create' ? 'history-create' : item.action === 'update' ? 'history-update' : 'history-delete';
            
            const date = new Date(item.created_at);
            
            // Get record information to build action description
            let dataSource = null;
            let oldDataSource = null;
            try {
                if (item.new_data) dataSource = typeof item.new_data === 'string' ? JSON.parse(item.new_data) : item.new_data;
                if (item.old_data) oldDataSource = typeof item.old_data === 'string' ? JSON.parse(item.old_data) : item.old_data;
                if (!dataSource && oldDataSource) dataSource = oldDataSource;
            } catch (e) {
                console.warn('Failed to parse history JSON:', e);
            }
            
            // Build action description - what the user did (simple, concise)
            let actionDescription = '';
            const moduleLabel = item.module ? item.module.charAt(0).toUpperCase() + item.module.slice(1) : 'Record';
            
            // Parse changed fields to show what was changed (briefly)
            let changedFields = null;
            let changedFieldsList = '';
            try {
                if (item.changed_fields) {
                    changedFields = typeof item.changed_fields === 'string' ? JSON.parse(item.changed_fields) : item.changed_fields;
                }
            } catch (e) {
                // Ignore parsing errors
            }
            
            // Filter out technical fields
            const excludedFieldsSet = new Set([
                'password', 'password_plain', 'updated_at', 'created_at', 
                'last_login', 'login_token', 'session_id', 'remember_token', 'api_token'
            ]);
            
            // Build detailed changes list for expandable section
            let detailedChangesHtml = '';
            let filteredFields = [];
            
            if (changedFields && Object.keys(changedFields).length > 0) {
                filteredFields = Object.keys(changedFields).filter(field => {
                    const fieldLower = field.toLowerCase();
                    if (excludedFieldsSet.has(fieldLower)) return false;
                    if (fieldLower.includes('_at') || fieldLower.includes('timestamp')) return false;
                    // Check for password hashes
                    const change = changedFields[field];
                    const oldVal = String(change.old || '');
                    const newVal = String(change.new || '');
                    if (oldVal.startsWith('$2y$') || oldVal.startsWith('$2a$') || 
                        newVal.startsWith('$2y$') || newVal.startsWith('$2a$')) return false;
                    return true;
                });
                
                if (filteredFields.length > 0) {
                    changedFieldsList = ` (changed: ${filteredFields.slice(0, 3).join(', ')}${filteredFields.length > 3 ? ` +${filteredFields.length - 3} more` : ''})`;
                    
                    // Build detailed changes HTML with professional styling
                    // Always start with Record ID and Table metadata for updates
                    detailedChangesHtml = '<div class="history-details-content">';
                    
                    // Add record metadata at the top (ALWAYS for updates)
                    if (item.action === 'update') {
                        // Try multiple sources for record ID
                        let recordId = item.record_id;
                        if (!recordId && dataSource) {
                            recordId = dataSource.id || dataSource.recruitment_country_id || dataSource.visa_type_id || 
                                      dataSource.job_category_id || dataSource.agent_id || dataSource.worker_id || 
                                      dataSource.case_id || dataSource.user_id || dataSource.contact_id || 
                                      dataSource.notification_id || dataSource.subagent_id || dataSource.employee_id ||
                                      dataSource.customer_id || dataSource.vendor_id || dataSource.account_id;
                        }
                        if (!recordId && oldDataSource) {
                            recordId = oldDataSource.id || oldDataSource.recruitment_country_id || oldDataSource.visa_type_id || 
                                      oldDataSource.job_category_id || oldDataSource.agent_id || oldDataSource.worker_id || 
                                      oldDataSource.case_id || oldDataSource.user_id || oldDataSource.contact_id || 
                                      oldDataSource.notification_id || oldDataSource.subagent_id || oldDataSource.employee_id ||
                                      oldDataSource.customer_id || oldDataSource.vendor_id || oldDataSource.account_id;
                        }
                        recordId = recordId || 'N/A';
                        
                        const tableName = item.table_name || 'N/A';
                        detailedChangesHtml += '<div class="history-grid">';
                        detailedChangesHtml += `<div class="history-section-header"><i class="fas fa-hashtag"></i> <strong>Record ID:</strong> #${recordId}</div>`;
                        detailedChangesHtml += `<div class="history-section-header"><i class="fas fa-database"></i> <strong>Table:</strong> ${tableName}</div>`;
                        detailedChangesHtml += '</div>';
                    }
                    
                    detailedChangesHtml += '<div class="history-section-title"><i class="fas fa-exchange-alt"></i> <strong>Changes Summary</strong></div>';
                    
                    // Process fields and resolve foreign keys
                    const fieldPromises = filteredFields.map(async (field) => {
                        const change = changedFields[field];
                        let oldVal = change.old !== null && change.old !== undefined ? String(change.old) : '<em>empty</em>';
                        let newVal = change.new !== null && change.new !== undefined ? String(change.new) : '<em>empty</em>';
                        
                        // Resolve foreign keys (with error handling)
                        try {
                            if (oldVal !== '<em>empty</em>' && field.endsWith('_id') && /^\d+$/.test(oldVal)) {
                                const resolved = await this.resolveForeignKey(field, oldVal, item);
                                if (resolved && resolved !== oldVal) {
                                    oldVal = `${resolved} (ID: ${oldVal})`;
                                }
                            }
                        } catch (e) {
                            console.warn(`Failed to resolve foreign key for ${field}:${oldVal}`, e);
                        }
                        
                        try {
                            if (newVal !== '<em>empty</em>' && field.endsWith('_id') && /^\d+$/.test(newVal)) {
                                const resolved = await this.resolveForeignKey(field, newVal, item);
                                if (resolved && resolved !== newVal) {
                                    newVal = `${resolved} (ID: ${newVal})`;
                                }
                            }
                        } catch (e) {
                            console.warn(`Failed to resolve foreign key for ${field}:${newVal}`, e);
                        }
                        
                        // Format field name (convert snake_case to Title Case)
                        const fieldLabel = field
                            .split('_')
                            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                            .join(' ');
                        
                        return `
                            <div class="history-change-item history-changed-field">
                                <div class="history-change-field">
                                    <i class="fas fa-circle history-change-indicator"></i>
                                    <strong>${this.escapeHtml(fieldLabel)}</strong>
                                    <span class="history-change-badge">MODIFIED</span>
                                </div>
                                <div class="history-change-values">
                                    <div class="history-change-value-col">
                                        <div class="history-change-label">Previous Value</div>
                                        <span class="history-change-old">${oldVal === '<em>empty</em>' ? oldVal : this.escapeHtml(oldVal)}</span>
                                    </div>
                                    <i class="fas fa-long-arrow-alt-right history-change-arrow"></i>
                                    <div class="history-change-value-col">
                                        <div class="history-change-label">New Value</div>
                                        <span class="history-change-new">${newVal === '<em>empty</em>' ? newVal : this.escapeHtml(newVal)}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    // Wait for all foreign key resolutions
                    const fieldHtmls = await Promise.all(fieldPromises);
                    detailedChangesHtml += fieldHtmls.join('');
                    detailedChangesHtml += '</div>';
                }
            }
            
            // For create/delete actions, show ALL information (not limited)
            if (item.action === 'create' && dataSource) {
                const keyFields = Object.keys(dataSource).filter(field => {
                    const fieldLower = field.toLowerCase();
                    return !excludedFieldsSet.has(fieldLower) && 
                           !fieldLower.includes('_at') && 
                           !fieldLower.includes('timestamp') &&
                           !fieldLower.includes('password');
                });
                
                if (keyFields.length > 0) {
                    detailedChangesHtml = '<div class="history-details-content">';
                    // Try multiple sources for record ID
                    let recordId = item.record_id;
                    if (!recordId && dataSource) {
                        recordId = dataSource.id || dataSource.recruitment_country_id || dataSource.visa_type_id || 
                                  dataSource.job_category_id || dataSource.agent_id || dataSource.worker_id || 
                                  dataSource.case_id || dataSource.user_id || dataSource.contact_id || 
                                  dataSource.notification_id || dataSource.subagent_id || dataSource.employee_id ||
                                  dataSource.customer_id || dataSource.vendor_id || dataSource.account_id;
                    }
                    recordId = recordId || 'N/A';
                    const tableName = item.table_name || 'N/A';
                    detailedChangesHtml += '<div class="history-grid">';
                    detailedChangesHtml += `<div class="history-section-header"><i class="fas fa-hashtag"></i> <strong>Record ID:</strong> #${recordId}</div>`;
                    detailedChangesHtml += `<div class="history-section-header"><i class="fas fa-database"></i> <strong>Table:</strong> ${tableName}</div>`;
                    detailedChangesHtml += '</div>';
                    detailedChangesHtml += '<div class="history-create-info"><i class="fas fa-plus-circle"></i> <strong>Created Record Data</strong></div>';
                    keyFields.forEach(field => {
                        const fieldLabel = field
                            .split('_')
                            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                            .join(' ');
                        const value = dataSource[field];
                        const displayValue = value !== null && value !== undefined ? String(value) : '<em>empty</em>';
                        detailedChangesHtml += `
                            <div class="history-change-item">
                                <div class="history-change-field"><strong>${this.escapeHtml(fieldLabel)}:</strong></div>
                                <div class="history-change-values">
                                    <span class="history-change-new">${displayValue === '<em>empty</em>' ? displayValue : this.escapeHtml(displayValue)}</span>
                                </div>
                            </div>
                        `;
                    });
                    detailedChangesHtml += '</div>';
                }
            }
            
            if (item.action === 'delete' && oldDataSource) {
                const keyFields = Object.keys(oldDataSource).filter(field => {
                    const fieldLower = field.toLowerCase();
                    return !excludedFieldsSet.has(fieldLower) && 
                           !fieldLower.includes('_at') && 
                           !fieldLower.includes('timestamp') &&
                           !fieldLower.includes('password');
                });
                
                if (keyFields.length > 0) {
                    detailedChangesHtml = '<div class="history-details-content">';
                    // Try multiple sources for record ID
                    let recordId = item.record_id;
                    if (!recordId && oldDataSource) {
                        recordId = oldDataSource.id || oldDataSource.recruitment_country_id || oldDataSource.visa_type_id || 
                                  oldDataSource.job_category_id || oldDataSource.agent_id || oldDataSource.worker_id || 
                                  oldDataSource.case_id || oldDataSource.user_id || oldDataSource.contact_id || 
                                  oldDataSource.notification_id || oldDataSource.subagent_id || oldDataSource.employee_id ||
                                  oldDataSource.customer_id || oldDataSource.vendor_id || oldDataSource.account_id;
                    }
                    recordId = recordId || 'N/A';
                    const tableName = item.table_name || 'N/A';
                    detailedChangesHtml += '<div class="history-grid">';
                    detailedChangesHtml += `<div class="history-section-header"><i class="fas fa-hashtag"></i> <strong>Record ID:</strong> #${recordId}</div>`;
                    detailedChangesHtml += `<div class="history-section-header"><i class="fas fa-database"></i> <strong>Table:</strong> ${tableName}</div>`;
                    detailedChangesHtml += '</div>';
                    detailedChangesHtml += '<div class="history-delete-info"><i class="fas fa-trash-alt"></i> <strong>Deleted Record Data</strong></div>';
                    keyFields.forEach(field => {
                        const fieldLabel = field
                            .split('_')
                            .map(word => word.charAt(0).toUpperCase() + word.slice(1))
                            .join(' ');
                        const value = oldDataSource[field];
                        const displayValue = value !== null && value !== undefined ? String(value) : '<em>empty</em>';
                        detailedChangesHtml += `
                            <div class="history-change-item">
                                <div class="history-change-field"><strong>${this.escapeHtml(fieldLabel)}:</strong></div>
                                <div class="history-change-values">
                                    <span class="history-change-old">${displayValue === '<em>empty</em>' ? displayValue : this.escapeHtml(displayValue)}</span>
                                </div>
                            </div>
                        `;
                    });
                    detailedChangesHtml += '</div>';
                }
            }
            
            // For updates, always add complete record information (even if no changed fields detected)
            if (item.action === 'update' && (oldDataSource || dataSource)) {
                // If no changed fields were detected but we have old/new data, build the details from scratch
                if (!detailedChangesHtml) {
                    detailedChangesHtml = '<div class="history-details-content">';
                    // Add record metadata at the top (professional grid layout)
                    // Try multiple sources for record ID
                    let recordId = item.record_id;
                    if (!recordId && dataSource) {
                        recordId = dataSource.id || dataSource.recruitment_country_id || dataSource.visa_type_id || 
                                  dataSource.job_category_id || dataSource.agent_id || dataSource.worker_id || 
                                  dataSource.case_id || dataSource.user_id || dataSource.contact_id || 
                                  dataSource.notification_id || dataSource.subagent_id || dataSource.employee_id ||
                                  dataSource.customer_id || dataSource.vendor_id || dataSource.account_id;
                    }
                    if (!recordId && oldDataSource) {
                        recordId = oldDataSource.id || oldDataSource.recruitment_country_id || oldDataSource.visa_type_id || 
                                  oldDataSource.job_category_id || oldDataSource.agent_id || oldDataSource.worker_id || 
                                  oldDataSource.case_id || oldDataSource.user_id || oldDataSource.contact_id || 
                                  oldDataSource.notification_id || oldDataSource.subagent_id || oldDataSource.employee_id ||
                                  oldDataSource.customer_id || oldDataSource.vendor_id || oldDataSource.account_id;
                    }
                    recordId = recordId || 'N/A';
                    
                    const tableName = item.table_name || 'N/A';
                    let metadataHtml = '<div class="history-grid">';
                    metadataHtml += `<div class="history-section-header"><i class="fas fa-hashtag"></i> <strong>Record ID:</strong> #${recordId}</div>`;
                    metadataHtml += `<div class="history-section-header"><i class="fas fa-database"></i> <strong>Table:</strong> ${tableName}</div>`;
                    metadataHtml += '</div>';
                    detailedChangesHtml += metadataHtml;
                    detailedChangesHtml += '</div>';
                }
            }
            
            if (dataSource) {
                const recordName = dataSource.name || dataSource.full_name || dataSource.agent_name || 
                                 dataSource.worker_name || dataSource.case_name || dataSource.employee_name || 
                                 dataSource.username || dataSource.notification_title || dataSource.title || 
                                 `#${item.record_id}`;
                
                if (item.action === 'create') {
                    actionDescription = `Created ${moduleLabel} "${this.escapeHtml(String(recordName))}"`;
                } else if (item.action === 'update') {
                    actionDescription = `Updated ${moduleLabel} "${this.escapeHtml(String(recordName))}"${changedFieldsList}`;
                } else if (item.action === 'delete') {
                    actionDescription = `Deleted ${moduleLabel} "${this.escapeHtml(String(recordName))}"`;
                }
            } else {
                if (item.action === 'create') {
                    actionDescription = `Created ${moduleLabel} #${item.record_id}`;
                } else if (item.action === 'update') {
                    actionDescription = `Updated ${moduleLabel} #${item.record_id}${changedFieldsList}`;
                } else if (item.action === 'delete') {
                    actionDescription = `Deleted ${moduleLabel} #${item.record_id}`;
                }
            }
            
            // Show "View Details" button if there are details to show
            const hasDetails = detailedChangesHtml !== '';
            const detailsToggleId = `history-details-${item.id || index}`;
            
            // Format date and time
            const dateFormatted = date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
            const timeFormatted = date.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true
            });
            
            return `
                <div class="history-item">
                    <div class="history-icon ${actionClass}">
                        <i class="fas fa-${actionIcon}"></i>
                    </div>
                    <div class="history-content">
                        <div class="history-header">
                            <div class="history-header-left">
                                <div class="history-action-badge ${actionClass}">
                                    <i class="fas fa-${actionIcon}"></i>
                                    <span>${actionLabel}</span>
                                </div>
                            </div>
                            <div class="history-header-right">
                                <div class="history-timestamp">
                                    <i class="fas fa-calendar"></i>
                                    <span class="history-date">${dateFormatted}</span>
                                    <i class="fas fa-clock history-time-icon"></i>
                                    <span class="history-time">${timeFormatted}</span>
                                </div>
                            </div>
                        </div>
                        <div class="history-description">
                            ${actionDescription}
                        </div>
                        ${hasDetails ? `
                            <div class="history-details-toggle">
                                <button class="history-details-btn expanded" data-action="toggle-history-details">
                                    <i class="fas fa-chevron-down"></i>
                                    <span>Hide Details</span>
                                </button>
                            </div>
                            <div class="history-details" id="${detailsToggleId}">
                                ${detailedChangesHtml}
                            </div>
                        ` : ''}
                        <div class="history-meta">
                            <span><i class="fas fa-user"></i> <strong>By:</strong> ${item.user_name ? this.escapeHtml(item.user_name) : 'System'}</span>
                            <span><i class="fas fa-calendar"></i> ${dateFormatted}</span>
                            <span><i class="fas fa-clock"></i> ${timeFormatted}</span>
                        </div>
                    </div>
                </div>
            `;
        });
        
        // Wait for all items to be processed (with foreign key resolution)
        const itemHtmls = await Promise.all(itemPromises);
        const html = '<div class="history-list">' + itemHtmls.join('') + '</div>';
        body.innerHTML = html;
        
        // Add event listeners for history detail toggles (replaces inline onclick handlers)
        body.querySelectorAll('[data-action="toggle-history-details"]').forEach(btn => {
            btn.addEventListener('click', function() {
                this.classList.toggle('expanded');
                const detailsDiv = this.nextElementSibling;
                if (detailsDiv) {
                    detailsDiv.classList.toggle('hidden');
                    // Update button text
                    const span = this.querySelector('span');
                    if (span) {
                        span.textContent = detailsDiv.classList.contains('hidden') ? 'Show Details' : 'Hide Details';
                    }
                }
            });
        });
    }
    
    formatJSONData(data) {
        if (!data) return '<em class="text-muted">No data</em>';
        if (typeof data === 'string') {
            try {
                data = JSON.parse(data);
            } catch (e) {
                return `<code>${this.escapeHtml(String(data))}</code>`;
            }
        }
        return '<pre class="history-json">' + this.escapeHtml(JSON.stringify(data, null, 2)) + '</pre>';
    }
    
    formatRelativeTime(date) {
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins}m ago`;
        if (diffHours < 24) return `${diffHours}h ago`;
        if (diffDays < 7) return `${diffDays}d ago`;
        return date.toLocaleDateString();
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    /**
     * Resolve foreign key ID to display name
     * @param {string} fieldName - The field name (e.g., 'country_id')
     * @param {string|number} value - The ID value
     * @param {object} item - The history item (for context)
     * @returns {Promise<string>} - Resolved name or original value
     */
    async resolveForeignKey(fieldName, value, item) {
        if (!value || value === '0' || value === 0 || value === '' || value === null) {
            return value;
        }
        
        // Check cache first
        const cacheKey = `${fieldName}_${value}`;
        if (this.foreignKeyCache.has(cacheKey)) {
            return this.foreignKeyCache.get(cacheKey);
        }
        
        // Foreign key mapping
        const fkMapping = {
            'country_id': { table: 'recruitment_countries', idField: 'id', nameField: 'country_name' },
            'recruitment_country_id': { table: 'recruitment_countries', idField: 'id', nameField: 'country_name' },
            'city_id': { table: 'cities', idField: 'id', nameField: 'city_name' },
            'agent_id': { table: 'agents', idField: 'agent_id', nameField: 'agent_name' },
            'subagent_id': { table: 'subagents', idField: 'subagent_id', nameField: 'subagent_name' },
            'worker_id': { table: 'workers', idField: 'id', nameField: 'full_name' },
            'user_id': { table: 'users', idField: 'user_id', nameField: 'username' },
            'case_id': { table: 'cases', idField: 'id', nameField: 'case_number' },
            'contact_id': { table: 'contacts', idField: 'id', nameField: 'name' },
            'visa_type_id': { table: 'visa_types', idField: 'id', nameField: 'visa_name' },
            'job_category_id': { table: 'job_categories', idField: 'id', nameField: 'category_name' },
            'customer_id': { table: 'accounting_customers', idField: 'id', nameField: 'customer_name' },
            'vendor_id': { table: 'accounting_vendors', idField: 'id', nameField: 'vendor_name' },
            'account_id': { table: 'financial_accounts', idField: 'id', nameField: 'account_name' }
        };
        
        const mapping = fkMapping[fieldName];
        if (!mapping) {
            return value; // Not a known foreign key
        }
        
        try {
            // Try to get from item data first (if already loaded)
            if (item && item.new_data) {
                const newData = typeof item.new_data === 'string' ? JSON.parse(item.new_data) : item.new_data;
                const relatedField = fieldName.replace('_id', '_name') || fieldName.replace('_id', '');
                if (newData[relatedField]) {
                    this.foreignKeyCache.set(cacheKey, newData[relatedField]);
                    return newData[relatedField];
                }
            }
            
            // Fetch from API (use control settings API when in control panel)
            const apiBase = this._apiBase();
            const el = document.getElementById('app-config');
            const isControl = el && el.getAttribute('data-control') === '1';
            const settingsPath = isControl ? 'control/settings-api.php' : 'settings/settings-api.php';
            const response = await fetch(`${apiBase}/${settingsPath}?action=get_one&table=${mapping.table}&id=${value}`);
            if (response.ok) {
                const result = await response.json();
                if (result.success && result.data) {
                    const name = result.data[mapping.nameField] || result.data.name || result.data[mapping.nameField.replace('_name', '')] || value;
                    this.foreignKeyCache.set(cacheKey, name);
                    return name;
                }
            }
        } catch (e) {
            console.warn(`Failed to resolve foreign key ${fieldName}:${value}`, e);
        }
        
        return value; // Return original value if resolution fails
    }
    
    closeModal() {
        if (this.modal) {
            this.modal.classList.add('modal-hidden');
            this.modal.classList.remove('show');
            document.body.classList.remove('modal-open');
        }
    }
    
    // Refresh history if modal is currently open
    async refreshIfOpen() {
        if (this.modal && !this.modal.classList.contains('modal-hidden') && this.modal.classList.contains('show')) {
            await this.loadAndRenderHistory();
        }
    }
}

// Make it available globally
window.UnifiedHistory = UnifiedHistory;

// Create global instance if it doesn't exist
if (!window.unifiedHistory) {
    window.unifiedHistory = new UnifiedHistory();
    window.unifiedHistory.initModal();
    
    // Listen for history update events from anywhere in the system
    document.addEventListener('history-updated', async () => {
        await window.unifiedHistory.refreshIfOpen();
    });
}

// Helper function to notify history update (can be called from anywhere)
window.refreshHistoryIfOpen = async function() {
    if (window.unifiedHistory) {
        await window.unifiedHistory.refreshIfOpen();
    }
};

