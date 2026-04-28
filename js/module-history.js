/**
 * EN: Implements frontend interaction behavior in `js/module-history.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/module-history.js`.
 */
/**
 * Module History - Reusable history viewer for individual modules
 * Usage: Initialize with module name (e.g., 'agents', 'workers', 'cases', 'hr')
 */

function getModuleHistoryApiBase() {
    if (window.APP_CONFIG && window.APP_CONFIG.apiBase) return window.APP_CONFIG.apiBase.replace(/\/$/, '');
    if (window.API_BASE) return String(window.API_BASE).replace(/\/$/, '');
    const path = (window.location.pathname || '').replace(/\/pages\/.*$/, '').replace(/\/control\/.*$/, '') || '/';
    const base = path.endsWith('/') ? path.slice(0, -1) : path;
    return (base || '') + '/api';
}

class ModuleHistory {
    constructor(moduleName, moduleTitle) {
        this.moduleName = moduleName;
        this.moduleTitle = moduleTitle || moduleName.charAt(0).toUpperCase() + moduleName.slice(1);
        this.initModal();
    }
    
    initModal() {
        // Check if modal already exists
        let modal = document.getElementById('moduleHistoryModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'moduleHistoryModal';
            modal.className = 'modern-modal modal-hidden';
            modal.innerHTML = `
                <div class="modern-modal-content history-modal-content">
                    <div class="modern-modal-header">
                        <h2 class="modern-modal-title" id="moduleHistoryTitle">Activity History</h2>
                        <button class="modal-close" data-action="close-module-history">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div id="moduleHistoryBody" class="history-modal-body">
                        <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading history...</p></div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Add event listeners
            modal.addEventListener('click', (e) => {
                if (e.target.hasAttribute('data-action') && e.target.getAttribute('data-action') === 'close-module-history') {
                    this.closeModal();
                } else if (e.target.closest('.modal-close')) {
                    this.closeModal();
                } else if (e.target === modal) {
                    this.closeModal();
                }
            });
            
            // Close on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !modal.classList.contains('modal-hidden')) {
                    this.closeModal();
                }
            });
        }
        
        this.modal = modal;
    }
    
    async openModal() {
        const title = document.getElementById('moduleHistoryTitle');
        const body = document.getElementById('moduleHistoryBody');
        
        if (!title || !body) return;
        
        title.textContent = `${this.moduleTitle} - Activity History`;
        body.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading history...</p></div>';
        
        this.modal.classList.remove('modal-hidden');
        this.modal.classList.add('show');
        
        try {
            const history = await this.loadHistory();
            this.renderHistory(history);
        } catch (error) {
            body.innerHTML = `<div class="error-state"><i class="fas fa-exclamation-triangle"></i><p>Failed to load history: ${error.message}</p></div>`;
        }
    }
    
    async loadHistory(limit = 100) {
        try {
            const base = getModuleHistoryApiBase();
            const url = `${base}/core/module-history-api.php?action=get_history&module=${encodeURIComponent(this.moduleName)}&limit=${limit}`;
            
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const text = await response.text();
            if (!text.trim()) {
                return [];
            }
            
            const data = JSON.parse(text);
            if (data.success) {
                return data.data || [];
            }
            throw new Error(data.message || 'Failed to load history');
        } catch (error) {
            console.error('History load error:', error);
            return [];
        }
    }
    
    async loadStats() {
        try {
            const base = getModuleHistoryApiBase();
            const url = `${base}/core/module-history-api.php?action=get_stats&module=${encodeURIComponent(this.moduleName)}`;
            
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
    
    renderHistory(history) {
        const body = document.getElementById('moduleHistoryBody');
        if (!body) return;
        
        if (!history || history.length === 0) {
            body.innerHTML = '<div class="empty-state"><i class="fas fa-history"></i><p>No history records found</p></div>';
            return;
        }
        
        let html = '<div class="history-list">';
        
        history.forEach((item, index) => {
            const actionIcon = item.action === 'create' ? 'plus-circle' : item.action === 'update' ? 'edit' : 'trash';
            const actionLabel = item.action.charAt(0).toUpperCase() + item.action.slice(1);
            const actionClass = item.action === 'create' ? 'history-create' : item.action === 'update' ? 'history-update' : 'history-delete';
            
            const date = new Date(item.created_at);
            const relative = this.formatRelativeTime(date);
            const absolute = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
            
            let oldData = null;
            let newData = null;
            let changedFields = null;
            
            try {
                if (item.old_data) oldData = typeof item.old_data === 'string' ? JSON.parse(item.old_data) : item.old_data;
                if (item.new_data) newData = typeof item.new_data === 'string' ? JSON.parse(item.new_data) : item.new_data;
                if (item.changed_fields) changedFields = typeof item.changed_fields === 'string' ? JSON.parse(item.changed_fields) : item.changed_fields;
            } catch (e) {
                console.warn('Failed to parse history JSON:', e);
            }
            
            const itemId = `module-history-item-${index}`;
            
            // Extract key fields
            const dataSource = newData || oldData;
            let keyInfo = '';
            if (dataSource) {
                const name = dataSource.name || dataSource.full_name || dataSource.agent_name || dataSource.worker_name || dataSource.case_name || dataSource.employee_name || null;
                const email = dataSource.email || null;
                const phone = dataSource.phone || dataSource.contact_number || null;
                
                if (name || email || phone) {
                    keyInfo = '<div class="history-key-info">';
                    if (name) keyInfo += `<div class="history-key-field"><i class="fas fa-user-circle"></i> <strong>Name:</strong> <span>${this.escapeHtml(String(name))}</span></div>`;
                    if (email) keyInfo += `<div class="history-key-field"><i class="fas fa-envelope"></i> <strong>Email:</strong> <span>${this.escapeHtml(String(email))}</span></div>`;
                    if (phone) keyInfo += `<div class="history-key-field"><i class="fas fa-phone"></i> <strong>Phone:</strong> <span>${this.escapeHtml(String(phone))}</span></div>`;
                    keyInfo += '</div>';
                }
            }
            
            html += `
                <div class="history-item">
                    <div class="history-icon ${actionClass}">
                        <i class="fas fa-${actionIcon}"></i>
                    </div>
                    <div class="history-content">
                        <div class="history-header">
                            <div class="history-header-left">
                                <span class="history-action ${actionClass}">${actionLabel}</span>
                            </div>
                            <div class="history-header-right">
                                <span class="history-record-id">#${item.record_id}</span>
                                <span class="history-date" title="${absolute}">${relative}</span>
                            </div>
                        </div>
                        ${keyInfo}
                        <div class="history-meta">
                            ${item.user_name ? `<span><i class="fas fa-user"></i> ${this.escapeHtml(item.user_name)}</span>` : '<span><i class="fas fa-user"></i> System</span>'}
                            ${item.user_id ? `<span><i class="fas fa-id-badge"></i> User ID: ${item.user_id}</span>` : ''}
                            ${item.ip_address ? `<span><i class="fas fa-network-wired"></i> ${item.ip_address}</span>` : ''}
                        </div>
                        ${item.action === 'update' && changedFields && Object.keys(changedFields).length > 0 ? `
                            <div class="history-changes-summary">
                                <strong>Changed Fields (${Object.keys(changedFields).length}):</strong> ${Object.keys(changedFields).slice(0, 3).join(', ')}${Object.keys(changedFields).length > 3 ? ` +${Object.keys(changedFields).length - 3} more` : ''}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        body.innerHTML = html;
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
    
    closeModal() {
        if (this.modal) {
            this.modal.classList.add('modal-hidden');
            this.modal.classList.remove('show');
        }
    }
}

// Make it available globally
window.ModuleHistory = ModuleHistory;

