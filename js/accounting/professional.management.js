/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.management.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.management.js`.
 */
/**
 * Professional Accounting - Management
 * Load AFTER professional.js
 */
(function(){
    if (typeof ProfessionalAccounting === 'undefined') return;
    const methods = {
        async checkAndGenerateAlerts() {
            try {
                const lastAlertCheck = localStorage.getItem('lastAlertCheck');
                const today = this.formatDateForInput(new Date().toISOString().split('T')[0]);
                if (lastAlertCheck === today) return;
                const response = await fetch(`${this.apiBase}/auto-generate-alerts.php`, { method: 'POST' });
                const data = await response.json();
                if (data.success) {
                    localStorage.setItem('lastAlertCheck', today);
                    if (data.alerts_generated && data.alerts_generated > 0) {
                        this.showToast(`${data.alerts_generated} new alert(s) generated`, 'info', 7000);
                    }
                }
            } catch (error) {}
        },

        setupCostCentersHandlers() {
            // Alias for setupCostCentersEventHandlers
            this.setupCostCentersEventHandlers();
        },

        setupBankGuaranteeHandlers() {
            // Alias for setupBankGuaranteesEventHandlers
            this.setupBankGuaranteesEventHandlers();
        },

        applyBankingFilters() {
            this.bankingCurrentPage = 1;
            this.loadBankAccounts();
        },

        async openBankTransactionModal() {
            this.bankTransCurrentPage = this.bankTransCurrentPage || 1;
            this.bankTransPerPage = this.bankTransPerPage || 10;
            this.bankTransSearch = this.bankTransSearch || '';
            this.bankTransData = this.bankTransData || [];
            const content = `
                <div class="accounting-module-modal-content">
                    <div class="module-header"><h2><i class="fas fa-exchange-alt"></i> Bank Transactions</h2>
                        <div class="header-actions"><button class="btn btn-primary" data-action="new-bank-transaction"><i class="fas fa-plus"></i> New Transaction</button></div>
                    </div>
                    <div class="module-content">
                        <div class="filters-bar">
                            <div class="filter-group"><label>Search:</label><input type="text" id="bankTransSearch" class="filter-input" placeholder="Search..." value="${this.bankTransSearch || ''}"></div>
                            <div class="filter-group"><label>Bank Account:</label><select id="bankTransAccountFilter" class="filter-select"><option value="">All</option></select></div>
                            <div class="filter-group"><label>Type:</label><select id="bankTransTypeFilter" class="filter-select"><option value="">All</option><option value="deposit">Deposit</option><option value="withdrawal">Withdrawal</option><option value="transfer">Transfer</option></select></div>
                            <button class="btn btn-secondary btn-sm" id="bankTransApplyFilters"><i class="fas fa-filter"></i> Apply</button>
                        </div>
                        <div class="data-table-container modal-table-wrapper modal-table-wrapper-scroll">
                            <table class="data-table modal-table-fixed"><thead><tr><th>Date</th><th>Bank</th><th>Type</th><th>Description</th><th class="amount-column">Amount</th><th>Reference</th><th class="actions-column">Actions</th></tr></thead>
                            <tbody id="bankTransTableBody"><tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr></tbody></table>
                        </div>
                        <div class="table-pagination-bottom"><div class="pagination-info" id="bankTransPaginationInfo"></div><div class="pagination-controls"><button class="btn btn-sm btn-secondary" id="bankTransPrevBtn">Previous</button><span id="bankTransPageNumbers"></span><button class="btn btn-sm btn-secondary" id="bankTransNextBtn">Next</button></div></div>
                    </div>
                </div>
            `;
            this.showModal('Bank Transactions', content, 'large');
            setTimeout(async () => { await this.loadBankTransactions(); this.setupBankTransactionHandlers(); }, 100);
        },

        async loadBankTransactions() {
            try {
                const response = await fetch(`${this.apiBase}/bank-transactions.php`);
                const data = await response.json();
                if (data.success && data.transactions) {
                    this.bankTransData = data.transactions;
                    this.renderBankTransactionsTable();
                    const accountFilter = document.getElementById('bankTransAccountFilter');
                    if (accountFilter) {
                        const accounts = [...new Set((this.bankTransData || []).map(t => t.bank_account_name || t.bank_name).filter(Boolean))];
                        accounts.forEach(acc => { const opt = document.createElement('option'); opt.value = acc; opt.textContent = acc; accountFilter.appendChild(opt); });
                    }
                } else {
                    this.bankTransData = []; this.renderBankTransactionsTable();
                }
            } catch (e) {
                this.bankTransData = []; this.renderBankTransactionsTable();
            }
        },

        async openBankReconciliationModal() {
            const content = `
                <div class="accounting-module-modal-content">
                    <div class="module-header"><h2><i class="fas fa-balance-scale"></i> Bank Reconciliation</h2>
                        <div class="header-actions"><button class="btn btn-primary" data-action="new-reconciliation"><i class="fas fa-plus"></i> New Reconciliation</button></div>
                    </div>
                    <div class="module-content">
                        <div class="filters-bar">
                            <div class="filter-group"><label>Bank Account:</label><select id="reconciliationAccountFilter" class="filter-select"><option value="">Select</option></select></div>
                            <div class="filter-group"><label>Status:</label><select id="reconciliationStatusFilter" class="filter-select"><option value="">All</option><option value="pending">Pending</option><option value="in_progress">In Progress</option><option value="completed">Completed</option></select></div>
                            <button class="btn btn-secondary btn-sm" id="reconciliationApplyFilters"><i class="fas fa-filter"></i> Apply</button>
                        </div>
                        <div class="data-table-container modal-table-wrapper modal-table-wrapper-scroll">
                            <table class="data-table modal-table-fixed"><thead><tr><th>Date</th><th>Bank Account</th><th>Statement</th><th>Book</th><th>Difference</th><th>Status</th><th class="actions-column">Actions</th></tr></thead>
                            <tbody id="reconciliationTableBody"><tr><td colspan="7" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr></tbody></table>
                        </div>
                    </div>
                </div>
            `;
            this.showModal('Bank Reconciliation', content, 'large');
            setTimeout(async () => { await this.loadBankReconciliations(); this.setupBankReconciliationHandlers(); }, 100);
        },

        async loadBankReconciliations() {
            try {
                const response = await fetch(`${this.apiBase}/bank-reconciliation.php`);
                const data = await response.json();
                if (data.success && data.reconciliations) {
                    this.reconciliationData = data.reconciliations;
                    this.renderBankReconciliationsTable();
                    const accountFilter = document.getElementById('reconciliationAccountFilter');
                    if (accountFilter) {
                        const accounts = [...new Set((this.reconciliationData || []).map(r => r.bank_account_name || r.account_name).filter(Boolean))];
                        accounts.forEach(acc => { const opt = document.createElement('option'); opt.value = acc; opt.textContent = acc; accountFilter.appendChild(opt); });
                    }
                } else {
                    this.reconciliationData = []; this.renderBankReconciliationsTable();
                }
            } catch (e) {
                this.reconciliationData = []; this.renderBankReconciliationsTable();
            }
        },

        async viewReconciliation(id) {
            try {
                const response = await fetch(`${this.apiBase}/bank-reconciliation.php?id=${id}`);
                const data = await response.json();
                if (data.success && data.reconciliation) {
                    const rec = data.reconciliation;
                    const content = `<div class="reconciliation-details"><h3>Reconciliation #${rec.id}</h3><div class="detail-row"><label>Date:</label><span>${rec.reconciliation_date || rec.date || 'N/A'}</span></div><div class="detail-row"><label>Bank:</label><span>${this.escapeHtml(rec.bank_account_name || rec.account_name || 'N/A')}</span></div><div class="detail-row"><label>Statement:</label><span>${this.formatCurrency(rec.statement_balance || 0)}</span></div><div class="detail-row"><label>Book:</label><span>${this.formatCurrency(rec.book_balance || 0)}</span></div></div>`;
                    this.showModal('View Reconciliation', content);
                } else {
                    this.showToast(data.message || 'Failed to load', 'error');
                }
            } catch (e) { this.showToast('Error loading reconciliation', 'error'); }
        },

        async completeReconciliation(id) {
            try {
                const response = await fetch(`${this.apiBase}/bank-reconciliation.php?id=${id}`, { method: 'PUT', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status: 'completed' }) });
                const data = await response.json();
                if (data.success) { this.showToast('Reconciliation completed', 'success'); await this.loadBankReconciliations(); } else { this.showToast(data.message || 'Failed', 'error'); }
            } catch (e) { this.showToast('Error completing reconciliation', 'error'); }
        },

        async openReconciliationForm(reconciliationId = null) {
            let bankAccounts = []; try { const r = await fetch(`${this.apiBase}/banks.php`); const d = await r.json(); if (d.success && d.accounts) bankAccounts = d.accounts; } catch (e) {}
            const formContent = `<form id="reconciliationForm"><div class="accounting-modal-form-group"><label>Bank Account *</label><select id="reconciliationAccount" name="bank_account_id" class="form-control" required><option value="">Select</option>${bankAccounts.map(a => `<option value="${a.id}">${this.escapeHtml(a.bank_name || '')} - ${this.escapeHtml(a.account_name || '')}</option>`).join('')}</select></div><div class="accounting-modal-form-group"><label>Date *</label><input type="text" id="reconciliationDate" name="reconciliation_date" class="form-control date-input" required placeholder="MM/DD/YYYY"></div><div class="accounting-modal-form-group"><label>Statement Balance *</label><input type="number" id="reconciliationStatementBalance" name="statement_balance" class="form-control" step="0.01" required placeholder="0.00"></div><div class="accounting-modal-form-group"><label>Book Balance *</label><input type="number" id="reconciliationBookBalance" name="book_balance" class="form-control" step="0.01" required placeholder="0.00"></div><div class="accounting-modal-form-group"><label>Notes</label><textarea id="reconciliationNotes" name="notes" class="form-control" rows="2"></textarea></div><div class="accounting-modal-actions"><button type="submit" class="btn btn-primary">Create</button><button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button></div></form>`;
            this.showModal('New Reconciliation', formContent, 'normal', 'reconciliationFormModal');
            setTimeout(() => { const form = document.getElementById('reconciliationForm'); if (form) form.addEventListener('submit', async (e) => { e.preventDefault(); await this.saveReconciliation(reconciliationId); }); this.initializeEnglishDatePickers(document.getElementById('reconciliationFormModal')); }, 100);
        },

        async saveReconciliation(reconciliationId = null) {
            try {
                const bankAccountId = document.getElementById('reconciliationAccount')?.value;
                const reconciliationDate = document.getElementById('reconciliationDate')?.value;
                const statementBalance = parseFloat(document.getElementById('reconciliationStatementBalance')?.value || 0);
                const bookBalance = parseFloat(document.getElementById('reconciliationBookBalance')?.value || 0);
                const notes = document.getElementById('reconciliationNotes')?.value?.trim();
                if (!bankAccountId || !reconciliationDate) { this.showToast('Bank account and date required', 'error'); return; }
                const response = await fetch(reconciliationId ? `${this.apiBase}/bank-reconciliation.php?id=${reconciliationId}` : `${this.apiBase}/bank-reconciliation.php`, { method: reconciliationId ? 'PUT' : 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ bank_account_id: bankAccountId, reconciliation_date: reconciliationDate, statement_balance: statementBalance, book_balance: bookBalance, notes }) });
                const data = await response.json();
                if (data.success) { this.showToast('Reconciliation saved', 'success'); this.closeModal(); await this.loadBankReconciliations(); } else { this.showToast(data.message || 'Failed', 'error'); }
            } catch (e) { this.showToast('Error saving', 'error'); }
        },

        async openBankTransactionForm(transactionId = null) {
            let transactionData = null;
            if (transactionId) {
                try {
                    const r = await fetch(`${this.apiBase}/bank-transactions.php?id=${transactionId}`);
                    const d = await r.json();
                    if (d.success && d.transaction) transactionData = d.transaction;
                    else { this.showToast(d.message || 'Failed to load', 'error'); return; }
                } catch (e) { this.showToast('Error loading', 'error'); return; }
            }
            let bankAccounts = []; try { const r = await fetch(`${this.apiBase}/banks.php`); const d = await r.json(); if (d.success && d.accounts) bankAccounts = d.accounts; } catch (e) {}
            const formContent = `<form id="bankTransactionForm"><div class="accounting-modal-form-group"><label>Bank Account *</label><select id="bankTransAccount" name="bank_account_id" class="form-control" required><option value="">Select</option>${bankAccounts.map(a => `<option value="${a.id}" ${transactionData && transactionData.bank_account_id == a.id ? 'selected' : ''}>${this.escapeHtml(a.bank_name || '')} - ${this.escapeHtml(a.account_name || '')}</option>`).join('')}</select></div><div class="accounting-modal-form-group"><label>Date *</label><input type="text" id="bankTransDate" name="transaction_date" class="form-control date-input" required value="${transactionData ? (transactionData.transaction_date || '') : ''}" placeholder="MM/DD/YYYY"></div><div class="accounting-modal-form-group"><label>Type *</label><select id="bankTransType" name="transaction_type" class="form-control" required><option value="">Select</option><option value="deposit" ${transactionData?.transaction_type === 'deposit' ? 'selected' : ''}>Deposit</option><option value="withdrawal" ${transactionData?.transaction_type === 'withdrawal' ? 'selected' : ''}>Withdrawal</option><option value="transfer" ${transactionData?.transaction_type === 'transfer' ? 'selected' : ''}>Transfer</option></select></div><div class="accounting-modal-form-group"><label>Amount *</label><input type="number" id="bankTransAmount" name="amount" class="form-control" step="0.01" min="0" required value="${transactionData ? (transactionData.amount || '') : ''}" placeholder="0.00"></div><div class="accounting-modal-form-group"><label>Description</label><textarea id="bankTransDescription" name="description" class="form-control" rows="2">${transactionData ? this.escapeHtml(transactionData.description || '') : ''}</textarea></div><div class="accounting-modal-form-group"><label>Reference</label><input type="text" id="bankTransReference" name="reference_number" class="form-control" value="${transactionData ? this.escapeHtml(transactionData.reference_number || '') : ''}"></div><div class="accounting-modal-actions"><button type="submit" class="btn btn-primary">${transactionId ? 'Update' : 'Create'}</button><button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button></div></form>`;
            this.showModal(transactionId ? 'Edit Bank Transaction' : 'New Bank Transaction', formContent, 'normal', 'bankTransactionFormModal');
            setTimeout(() => { const form = document.getElementById('bankTransactionForm'); if (form) form.addEventListener('submit', async (e) => { e.preventDefault(); await this.saveBankTransaction(transactionId); }); this.initializeEnglishDatePickers(document.getElementById('bankTransactionFormModal')); }, 100);
        },

        async saveBankTransaction(transactionId = null) {
            try {
                const bankAccountId = document.getElementById('bankTransAccount')?.value;
                const transactionDate = document.getElementById('bankTransDate')?.value;
                const transactionType = document.getElementById('bankTransType')?.value;
                const amount = parseFloat(document.getElementById('bankTransAmount')?.value || 0);
                const description = document.getElementById('bankTransDescription')?.value?.trim();
                const referenceNumber = document.getElementById('bankTransReference')?.value?.trim();
                if (!bankAccountId || !transactionDate || !transactionType || !amount || amount <= 0) { this.showToast('Required fields missing or invalid', 'error'); return; }
                const response = await fetch(transactionId ? `${this.apiBase}/bank-transactions.php?id=${transactionId}` : `${this.apiBase}/bank-transactions.php`, { method: transactionId ? 'PUT' : 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ bank_account_id: bankAccountId, transaction_date: transactionDate, transaction_type: transactionType, amount, description, reference_number: referenceNumber }) });
                const data = await response.json();
                if (data.success) { this.showToast('Transaction saved', 'success'); this.closeModal(); await this.loadBankTransactions(); await this.loadBankAccounts(); } else { this.showToast(data.message || 'Failed', 'error'); }
            } catch (e) { this.showToast('Error saving', 'error'); }
        },

        async viewBankTransaction(transactionId) {
            try {
                const response = await fetch(`${this.apiBase}/bank-transactions.php?id=${transactionId}`);
                const data = await response.json();
                if (data.success && data.transaction) {
                    const trans = data.transaction;
                    const content = `<div class="transaction-details"><h3>Bank Transaction #${trans.id}</h3><div class="detail-row"><label>Date:</label><span>${trans.transaction_date || 'N/A'}</span></div><div class="detail-row"><label>Amount:</label><span>${this.formatCurrency(trans.amount || 0, trans.currency || this.getDefaultCurrencySync())}</span></div><div class="detail-row"><label>Description:</label><span>${this.escapeHtml(trans.description || 'N/A')}</span></div></div>`;
                    this.showModal('View Bank Transaction', content);
                } else { this.showToast(data.message || 'Failed to load', 'error'); }
            } catch (e) { this.showToast('Error loading transaction', 'error'); }
        },

        async deleteBankTransaction(transactionId) {
            const confirmed = await this.showConfirmDialog('Delete Bank Transaction', 'Are you sure? This cannot be undone.', 'Delete', 'Cancel', 'danger');
            if (confirmed) {
                try {
                    const response = await fetch(`${this.apiBase}/bank-transactions.php?id=${transactionId}`, { method: 'DELETE' });
                    const data = await response.json();
                    if (data.success) { this.showToast('Transaction deleted', 'success'); await this.loadBankTransactions(); await this.loadBankAccounts(); } else { this.showToast(data.message || 'Failed', 'error'); }
                } catch (e) { this.showToast('Error deleting', 'error'); }
            }
        },

        updateFollowupPagination() {
            const infoEl = document.getElementById('followupPaginationInfo');
            if (infoEl && this.followupTotalCount !== undefined) {
                const start = this.followupTotalCount > 0 ? (this.followupCurrentPage - 1) * this.followupPerPage + 1 : 0;
                const end = Math.min(this.followupCurrentPage * this.followupPerPage, this.followupTotalCount);
                infoEl.textContent = this.followupTotalCount > 0 ? `Showing ${start} to ${end} of ${this.followupTotalCount}` : 'No follow-ups';
            }
        },

        updateMessagePagination() {
            const infoEl = document.getElementById('messagePaginationInfo');
            if (infoEl && this.messageTotalCount !== undefined) {
                const start = this.messageTotalCount > 0 ? (this.messageCurrentPage - 1) * this.messagePerPage + 1 : 0;
                const end = Math.min(this.messageCurrentPage * this.messagePerPage, this.messageTotalCount);
                infoEl.textContent = this.messageTotalCount > 0 ? `Showing ${start} to ${end} of ${this.messageTotalCount}` : 'No messages';
            }
        },

        setupEntryApprovalHandlers() {
            // Setup entry approval handlers including search and filter
            this.setupEntryApprovalEventHandlers();
            
            const modal = document.getElementById('entryApprovalModal');
            if (!modal) return;
            // Search input handler
            const searchInput = document.getElementById('entryApprovalSearch');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    this.entryApprovalSearchTerm = e.target.value.trim();
                    searchTimeout = setTimeout(() => {
                        this.entryApprovalCurrentPage = 1;
                        this.renderEntryApprovalTable();
                    }, 300);
                });
            }
            // Status filter handler
            const statusFilter = document.getElementById('entryApprovalStatusFilter');
            if (statusFilter) {
                statusFilter.addEventListener('change', (e) => {
                    this.entryApprovalCurrentPage = 1;
                    this.loadEntryApproval(e.target.value);
                });
            }
            // Page size handler
            const pageSize = document.getElementById('entryApprovalPageSize');
            if (pageSize) {
                pageSize.addEventListener('change', (e) => {
                    this.entryApprovalPerPage = parseInt(e.target.value);
                    this.entryApprovalCurrentPage = 1;
                    this.renderEntryApprovalTable();
                });
            }
            // Apply filters button
            const applyFiltersBtn = document.getElementById('entryApprovalApplyFilters');
            if (applyFiltersBtn) {
                applyFiltersBtn.addEventListener('click', () => {
                    this.entryApprovalCurrentPage = 1;
                    const statusFilter = document.getElementById('entryApprovalStatusFilter');
                    this.loadEntryApproval(statusFilter ? statusFilter.value : 'all');
                });
            }
            // Pagination handlers
            const prevBtn = document.getElementById('entryApprovalPrevBtn');
            const nextBtn = document.getElementById('entryApprovalNextBtn');
            
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    if (this.entryApprovalCurrentPage > 1) {
                        this.entryApprovalCurrentPage--;
                        this.renderEntryApprovalTable();
                    }
                });
            }
            
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    const totalPages = Math.ceil(this.entryApprovalTotalCount / this.entryApprovalPerPage);
                    if (this.entryApprovalCurrentPage < totalPages) {
                        this.entryApprovalCurrentPage++;
                        this.renderEntryApprovalTable();
                    }
                });
            }
        },

        renderCostCentersTable() {
            const tbody = document.getElementById('costCentersTableBody');
            if (!tbody) return;
            // Apply search filter
            let filtered = [...this.costCentersData];
            const searchTerm = (this.costCentersSearchTerm || '').toLowerCase();
            if (searchTerm) {
                filtered = filtered.filter(cc => 
                    (cc.code && cc.code.toLowerCase().includes(searchTerm)) ||
                    (cc.name && cc.name.toLowerCase().includes(searchTerm)) ||
                    (cc.description && cc.description.toLowerCase().includes(searchTerm))
                );
            }
            // Apply status filter
            const statusFilter = document.getElementById('costCentersStatusFilter')?.value;
            if (statusFilter) {
                filtered = filtered.filter(cc => cc.status === statusFilter);
            }
            // Update total count
            this.costCentersTotalCount = filtered.length;
            const totalPages = Math.ceil(this.costCentersTotalCount / this.costCentersPerPage);
            this.costCentersTotalPages = totalPages || 1;
            // Paginate
            const start = (this.costCentersCurrentPage - 1) * this.costCentersPerPage;
            const end = start + this.costCentersPerPage;
            const paginated = filtered.slice(start, end);
            // Render
            if (paginated.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No cost centers found</p>
                            </div>
                            </td>
                    </tr>
                `;
            } else {
                tbody.innerHTML = paginated.map(cc => `
                    <tr>
                        <td>${this.escapeHtml(cc.code || '')}</td>
                        <td>${this.escapeHtml(cc.name || '')}</td>
                        <td>${this.escapeHtml(cc.description || '')}</td>
                        <td>
                            <span class="badge ${cc.status === 'active' ? 'badge-success' : 'badge-secondary'}">
                                ${cc.status === 'active' ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td>
                            <input type="checkbox" class="cost-center-checkbox" value="${cc.id}">
                        </td>
                        <td>
                            <button class="btn-icon btn-edit" data-action="edit-cost-center" data-id="${cc.id}" title="Edit">
                                <i class="fas fa-edit"></i>
                                </button>
                            <button class="btn-icon btn-delete" data-action="delete-cost-center" data-id="${cc.id}" title="Delete">
                                <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('');
            }
            // Update pagination UI
            this.updateCostCentersPagination();
            
            // Re-attach event handlers
            this.setupCostCentersEventHandlers();
        },

        updateCostCentersPagination() {
            const showingFrom = document.getElementById('costCentersShowingFrom');
            const showingTo = document.getElementById('costCentersShowingTo');
            const totalCountDisplay = document.getElementById('costCentersTotalCountDisplay');
            const currentPageEl = document.getElementById('costCentersCurrentPage');
            const totalPagesEl = document.getElementById('costCentersTotalPages');
            const prevBtn = document.getElementById('costCentersPrevBtn');
            const nextBtn = document.getElementById('costCentersNextBtn');
            const start = (this.costCentersCurrentPage - 1) * this.costCentersPerPage + 1;
            const end = Math.min(this.costCentersCurrentPage * this.costCentersPerPage, this.costCentersTotalCount);
            if (showingFrom) showingFrom.textContent = this.costCentersTotalCount > 0 ? start : 0;
            if (showingTo) showingTo.textContent = end;
            if (totalCountDisplay) totalCountDisplay.textContent = this.costCentersTotalCount;
            if (currentPageEl) currentPageEl.textContent = this.costCentersCurrentPage;
            if (totalPagesEl) totalPagesEl.textContent = this.costCentersTotalPages;
            if (prevBtn) prevBtn.disabled = this.costCentersCurrentPage <= 1;
            if (nextBtn) nextBtn.disabled = this.costCentersCurrentPage >= this.costCentersTotalPages;
        },

        renderBankGuaranteeTable() {
            const tbody = document.getElementById('bankGuaranteeTableBody');
            if (!tbody) return;
            // Apply search filter
            let filtered = [...this.bankGuaranteeData];
            const searchTerm = (this.bankGuaranteeSearchTerm || '').toLowerCase();
            if (searchTerm) {
                filtered = filtered.filter(bg => 
                    (bg.reference_number && bg.reference_number.toLowerCase().includes(searchTerm)) ||
                    (bg.bank_name && bg.bank_name.toLowerCase().includes(searchTerm))
                );
            }
            // Apply status filter
            const statusFilter = document.getElementById('bankGuaranteeStatusFilter')?.value;
            if (statusFilter) {
                filtered = filtered.filter(bg => bg.status === statusFilter);
            }
            // Update total count
            this.bankGuaranteeTotalCount = filtered.length;
            const totalPages = Math.ceil(this.bankGuaranteeTotalCount / this.bankGuaranteePerPage);
            this.bankGuaranteeTotalPages = totalPages || 1;
            // Paginate
            const start = (this.bankGuaranteeCurrentPage - 1) * this.bankGuaranteePerPage;
            const end = start + this.bankGuaranteePerPage;
            const paginated = filtered.slice(start, end);
            // Render
            if (paginated.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No bank guarantees found</p>
                            </div>
                        </td>
                    </tr>
                `;
                } else {
                tbody.innerHTML = paginated.map(bg => `
                    <tr>
                        <td>${this.escapeHtml(bg.reference_number || '')}</td>
                        <td>${this.escapeHtml(bg.bank_name || '')}</td>
                        <td>${this.formatCurrency(bg.amount || 0, bg.currency || this.getDefaultCurrencySync())}</td>
                        <td>${bg.issue_date || ''}</td>
                        <td>${bg.expiry_date || ''}</td>
                        <td>
                            <span class="badge ${bg.status === 'active' ? 'badge-success' : bg.status === 'expired' ? 'badge-danger' : 'badge-warning'}">
                                ${bg.status || 'Pending'}
                            </span>
                        </td>
                        <td>
                            <input type="checkbox" class="bank-guarantee-checkbox" value="${bg.id}">
                        </td>
                        <td class="actions-cell">
                            <div class="bank-guarantee-actions">
                                <button class="action-btn action-btn-edit" data-action="edit-bank-guarantee" data-id="${bg.id}" title="Edit">
                                    <i class="fas fa-edit"></i>
                                    <span class="btn-label">Edit</span>
                                </button>
                                <button class="action-btn action-btn-delete" data-action="delete-bank-guarantee" data-id="${bg.id}" title="Delete">
                                    <i class="fas fa-trash"></i>
                                    <span class="btn-label">Delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            }
            // Update pagination UI
            this.updateBankGuaranteePagination();
            
            // Re-attach event handlers
            this.setupBankGuaranteesEventHandlers();
        },

        updateBankGuaranteePagination() {
            const showingFrom = document.getElementById('bankGuaranteeShowingFrom');
            const showingTo = document.getElementById('bankGuaranteeShowingTo');
            const totalCountDisplay = document.getElementById('bankGuaranteeTotalCountDisplay');
            const currentPageEl = document.getElementById('bankGuaranteeCurrentPage');
            const totalPagesEl = document.getElementById('bankGuaranteeTotalPages');
            const prevBtn = document.getElementById('bankGuaranteePrevBtn');
            const nextBtn = document.getElementById('bankGuaranteeNextBtn');
            const start = (this.bankGuaranteeCurrentPage - 1) * this.bankGuaranteePerPage + 1;
            const end = Math.min(this.bankGuaranteeCurrentPage * this.bankGuaranteePerPage, this.bankGuaranteeTotalCount);
            if (showingFrom) showingFrom.textContent = this.bankGuaranteeTotalCount > 0 ? start : 0;
            if (showingTo) showingTo.textContent = end;
            if (totalCountDisplay) totalCountDisplay.textContent = this.bankGuaranteeTotalCount;
            if (currentPageEl) currentPageEl.textContent = this.bankGuaranteeCurrentPage;
            if (totalPagesEl) totalPagesEl.textContent = this.bankGuaranteeTotalPages;
            if (prevBtn) prevBtn.disabled = this.bankGuaranteeCurrentPage <= 1;
            if (nextBtn) nextBtn.disabled = this.bankGuaranteeCurrentPage >= this.bankGuaranteeTotalPages;
        },

        renderEntryApprovalTable() {
            const tbody = document.getElementById('entryApprovalTableBody');
            if (!tbody) return;
            
            // Apply search filter
            let filtered = [...this.entryApprovalData];
            const searchTerm = (this.entryApprovalSearchTerm || '').toLowerCase();
            if (searchTerm) {
                filtered = filtered.filter(entry => 
                    (entry.entry_number && entry.entry_number.toLowerCase().includes(searchTerm)) ||
                    (entry.description && entry.description.toLowerCase().includes(searchTerm)) ||
                    (entry.debit_account_name && entry.debit_account_name.toLowerCase().includes(searchTerm)) ||
                    (entry.credit_account_name && entry.credit_account_name.toLowerCase().includes(searchTerm))
                );
            }
            // Update total count
            this.entryApprovalTotalCount = filtered.length;
            const totalPages = Math.ceil(this.entryApprovalTotalCount / this.entryApprovalPerPage);
            this.entryApprovalTotalPages = totalPages || 1;
            // Paginate
            const start = (this.entryApprovalCurrentPage - 1) * this.entryApprovalPerPage;
            const end = start + this.entryApprovalPerPage;
            const paginated = filtered.slice(start, end);
            // Render
            if (paginated.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>No entries found</p>
                            </div>
                        </td>
                    </tr>
                `;
                        } else {
                tbody.innerHTML = paginated.map(entry => {
                    const status = (entry.status || 'pending').toLowerCase();
                    const statusBadgeVariant = status === 'approved' ? 'success' : (status === 'rejected' ? 'danger' : 'warning');
                    const statusText = status === 'approved' ? 'Approved' : (status === 'rejected' ? 'Rejected' : 'Pending');
                    const isDisabled = status !== 'pending';
                    const currency = entry.currency || this.getDefaultCurrencySync();
                    const debitAmount = parseFloat(entry.total_debit ?? entry.debit_amount ?? entry.debit ?? 0) || 0;
                    const creditAmount = parseFloat(entry.total_credit ?? entry.credit_amount ?? entry.credit ?? 0) || 0;
                    const debitAccount = entry.debit_account_name ? this.escapeHtml(entry.debit_account_name) : '<span class="text-muted">-</span>';
                    const creditAccount = entry.credit_account_name ? this.escapeHtml(entry.credit_account_name) : '<span class="text-muted">-</span>';
                    const description = this.escapeHtml(entry.description || '');
                        
                        return `
                            <tr class="ledger-entry-row professional-ledger-row">
                                <td class="voucher-number-cell">
                                    <div class="voucher-number-stack">
                                        <div class="voucher-number-inline" style="display:flex; align-items:center; gap:8px;">
                                            <input type="checkbox" class="entry-checkbox" value="${entry.id}" ${isDisabled ? 'disabled' : ''} />
                                            <a href="#" class="journal-ref-link" data-action="view-entry" data-id="${entry.id}" title="View Entry">
                                                ${this.escapeHtml(entry.entry_number || '')}
                                            </a>
                                        </div>
                                        <div class="ledger-status-inline">
                                            <span class="badge badge-${statusBadgeVariant} badge-small">${this.escapeHtml(statusText)}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="date-cell">
                                    <span class="date-display">${entry.entry_date ? this.formatDate(entry.entry_date) : ''}</span>
                                </td>
                                <td class="debit-cell amount-cell ${debitAmount > 0 ? 'has-amount' : ''}">
                                    ${debitAmount > 0 ? this.formatCurrency(debitAmount, currency) : '<span class="text-muted">-</span>'}
                                </td>
                                <td class="credit-cell amount-cell ${creditAmount > 0 ? 'has-amount' : ''}">
                                    ${creditAmount > 0 ? this.formatCurrency(creditAmount, currency) : '<span class="text-muted">-</span>'}
                                </td>
                                <td class="account-cell debit-side-cell">${debitAccount}</td>
                                <td class="account-cell credit-side-cell">${creditAccount}</td>
                                <td class="description-cell">
                                    <div class="description-content">${description || '<span class="text-muted">-</span>'}</div>
                                </td>
                                <td class="actions-cell">
                                    <button class="btn-icon btn-view" data-action="view-entry" data-id="${entry.id}" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    ${!isDisabled ? `
                                        <button class="btn-icon btn-success" data-action="approve-entry" data-id="${entry.id}" title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn-icon btn-danger" data-action="reject-entry" data-id="${entry.id}" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    ` : ''}
                                    <button class="btn-icon btn-edit" data-action="edit-entry-approval" data-id="${entry.id}" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    }).join('');
            }
            // Update pagination UI
            this.updateEntryApprovalPagination();
            
            // Re-attach event handlers
            this.setupEntryApprovalEventHandlers();
        },

        updateEntryApprovalPagination() {
            const showingFrom = document.getElementById('entryApprovalShowingFrom');
            const showingTo = document.getElementById('entryApprovalShowingTo');
            const totalCountDisplay = document.getElementById('entryApprovalTotalCountDisplay');
            const currentPageEl = document.getElementById('entryApprovalCurrentPage');
            const totalPagesEl = document.getElementById('entryApprovalTotalPages');
            const prevBtn = document.getElementById('entryApprovalPrevBtn');
            const nextBtn = document.getElementById('entryApprovalNextBtn');
            const start = (this.entryApprovalCurrentPage - 1) * this.entryApprovalPerPage + 1;
            const end = Math.min(this.entryApprovalCurrentPage * this.entryApprovalPerPage, this.entryApprovalTotalCount);
            if (showingFrom) showingFrom.textContent = this.entryApprovalTotalCount > 0 ? start : 0;
            if (showingTo) showingTo.textContent = end;
            if (totalCountDisplay) totalCountDisplay.textContent = this.entryApprovalTotalCount;
            if (currentPageEl) currentPageEl.textContent = this.entryApprovalCurrentPage;
            if (totalPagesEl) totalPagesEl.textContent = this.entryApprovalTotalPages;
            if (prevBtn) prevBtn.disabled = this.entryApprovalCurrentPage <= 1;
            if (nextBtn) nextBtn.disabled = this.entryApprovalCurrentPage >= this.entryApprovalTotalPages;
        },

        async deleteCostCenter(id) {
            const confirmed = await this.showConfirmDialog(
                'Delete Cost Center',
                'Are you sure you want to delete this cost center?',
                'Delete',
                'Cancel',
                'danger'
            );
            if (!confirmed) return;
            try {
                const response = await fetch(`${this.apiBase}/cost-centers.php?id=${id}`, {
                    method: 'DELETE',
                    credentials: 'include'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showToast('Cost center deleted successfully', 'success');
                    this.loadCostCenters();
                } else {
                    this.showToast(data.message || 'Failed to delete cost center', 'error');
                }
            } catch (error) {
                this.showToast('Error deleting cost center', 'error');
            }
        },

        async deleteBankGuarantee(id) {
            const confirmed = await this.showConfirmDialog(
                'Delete Bank Guarantee',
                'Are you sure you want to delete this bank guarantee?',
                'Delete',
                'Cancel',
                'danger'
            );
            if (!confirmed) return;
            try {
                const response = await fetch(`${this.apiBase}/bank-guarantees.php?id=${id}`, {
                    method: 'DELETE',
                    credentials: 'include'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showToast('Bank guarantee deleted successfully', 'success');
                    this.loadBankGuarantees();
                } else {
                    this.showToast(data.message || 'Failed to delete bank guarantee', 'error');
                }
            } catch (error) {
                this.showToast('Error deleting bank guarantee', 'error');
            }
        },

        async deleteEntryApproval(id) {
            const confirmed = await this.showConfirmDialog(
                'Delete Entry',
                'Are you sure you want to delete this entry? This action cannot be undone.',
                'Delete',
                'Cancel',
                'danger'
            );
            
            if (!confirmed) return;
            
            try {
                const response = await fetch(`${this.apiBase}/entry-approval.php?id=${id}`, {
                    method: 'DELETE',
                    credentials: 'include'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.showToast('Entry deleted successfully', 'success');
                    await this.loadEntryApproval();
                } else {
                    this.showToast(data.message || 'Failed to delete entry', 'error');
                }
            } catch (error) {
                this.showToast('Error deleting entry: ' + error.message, 'error');
            }
        },

        setupFollowupMessages() {
            // Setup event listeners for followup/message forms
            // This is typically called during initialization
        },

        updateBulkActionsFollowups() {
            const checkboxes = document.querySelectorAll('#followupsList input[type="checkbox"]:checked');
            const count = checkboxes.length;
            const bulkBar = document.getElementById('followupsBulkActions');
            const countEl = document.getElementById('bulkSelectedCountFollowups');
            
            if (countEl) {
                countEl.textContent = `${count} selected`;
            }
            
            if (bulkBar) {
                bulkBar.classList.toggle('bulk-actions-bar-hidden', count === 0);
                bulkBar.classList.toggle('show', count > 0);
            }
        },

        updateBulkActionsMessages() {
            const checkboxes = document.querySelectorAll('#messagesList input[type="checkbox"]:checked');
            const count = checkboxes.length;
            const bulkBar = document.getElementById('messagesBulkActions');
            const countEl = document.getElementById('bulkSelectedCountMessages');
            
            if (countEl) {
                countEl.textContent = `${count} selected`;
            }
            
            if (bulkBar) {
                bulkBar.classList.toggle('bulk-actions-bar-hidden', count === 0);
                bulkBar.classList.toggle('show', count > 0);
            }
        },

        showNewFollowupForm() {
            const form = document.getElementById('newFollowupForm');
            if (form) {
                form.style.display = 'block';
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        },

        closeNewFollowupForm() {
            const form = document.getElementById('newFollowupForm');
            if (form) {
                form.style.display = 'none';
            }
        },

        showNewMessageForm() {
            const form = document.getElementById('newMessageForm');
            if (form) {
                form.style.display = 'block';
                form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        },

        closeNewMessageForm() {
            const form = document.getElementById('newMessageForm');
            if (form) {
                form.style.display = 'none';
            }
        },

        async loadFollowups() {
            try {
                const statusFilter = document.getElementById('followupStatusFilter')?.value || '';
                const priorityFilter = document.getElementById('followupPriorityFilter')?.value || '';
                const typeFilter = document.getElementById('followupTypeFilter')?.value || '';
                const entriesPerPage = parseInt(document.getElementById('followupEntriesPerPage')?.value || '5');
                
                // Update per page if changed
                if (entriesPerPage !== this.followupPerPage) {
                    this.followupPerPage = entriesPerPage;
                    this.followupCurrentPage = 1; // Reset to first page
                }
                
                let url = `${this.apiBase}/followups.php?`;
                if (statusFilter) url += `status=${encodeURIComponent(statusFilter)}&`;
                if (priorityFilter) url += `priority=${encodeURIComponent(priorityFilter)}&`;
                if (typeFilter) url += `related_type=${encodeURIComponent(typeFilter)}&`;
                
                const response = await fetch(url, {
                    cache: 'no-store',
                    headers: { 'Cache-Control': 'no-cache' }
                });
                
                if (!response.ok) {
                    if (response.status === 503) {
                        const errorData = await response.json().catch(() => ({}));
                        throw new Error(errorData.message || 'Database tables not found. Please click "Setup Follow-ups & Messages" in the navigation sidebar first.');
                    }
                    const errorText = await response.text().catch(() => 'Unknown error');
                    throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
                }
                
                const data = await response.json();
                
                const listContainer = document.getElementById('followupsList');
                if (!listContainer) {
                    this.showToast('Error: Follow-ups container not found', 'error');
                    return;
                }
                
                if (data.success && Array.isArray(data.followups)) {
                    // Store all followups
                    this.allFollowups = data.followups;
                    this.followupTotalCount = data.followups.length;
                    
                    // Calculate pagination
                    this.followupTotalPages = Math.max(1, Math.ceil(this.followupTotalCount / this.followupPerPage));
                    if (this.followupCurrentPage > this.followupTotalPages) {
                        this.followupCurrentPage = this.followupTotalPages;
                    }
                    
                    // Get paginated subset
                    const startIndex = (this.followupCurrentPage - 1) * this.followupPerPage;
                    const endIndex = startIndex + this.followupPerPage;
                    const paginatedFollowups = data.followups.slice(startIndex, endIndex);
                    
                    if (paginatedFollowups.length === 0 && this.followupTotalCount === 0) {
                        listContainer.innerHTML = `
                            <div class="empty-state-container">
                                <i class="fas fa-tasks empty-state-icon"></i>
                                <p class="empty-state-text large">No follow-ups found</p>
                                <p class="empty-state-hint">Click "New Follow-up" button above to create your first task</p>
                            </div>
                        `;
                        this.updateFollowupPagination();
                        return;
                    }
                    
                    let html = '<div class="followup-items-container">';
                    html += `
                        <div class="bulk-select-header">
                            <label class="bulk-select-all-label">
                                <input type="checkbox" id="bulkSelectAllFollowups" data-action="bulk-select-all-followups">
                                Select All
                            </label>
                        </div>
                    `;
                    paginatedFollowups.forEach(followup => {
                        const priorityColors = {
                            'urgent': '#ef4444',
                            'high': '#f59e0b',
                            'medium': '#3b82f6',
                            'low': '#6b7280'
                        };
                        const statusColors = {
                            'pending': '#f59e0b',
                            'in_progress': '#3b82f6',
                            'completed': '#10b981',
                            'cancelled': '#6b7280'
                        };
                        const priorityColor = priorityColors[followup.priority] || '#6b7280';
                        const statusColor = statusColors[followup.status] || '#6b7280';
                        const dueDate = followup.due_date ? new Date(followup.due_date) : null;
                        const isOverdue = dueDate && dueDate < new Date() && followup.status !== 'completed';
                        
                        const overdueClass = isOverdue ? ' overdue' : '';
                        html += `
                            <div class="followup-item${overdueClass}" data-id="${followup.id}" data-priority="${followup.priority}">
                                <div class="followup-item-checkbox">
                                    <input type="checkbox" class="followup-checkbox" data-id="${followup.id}" data-action="select-followup">
                                </div>
                                <div class="followup-item-header">
                                    <div class="followup-item-content">
                                        <h4 class="followup-item-title">
                                            ${this.escapeHtml(followup.title)}
                                        </h4>
                                        ${followup.description ? `
                                            <p class="followup-item-description">
                                                ${this.escapeHtml(followup.description.substring(0, 100))}${followup.description.length > 100 ? '...' : ''}
                                            </p>
                                        ` : ''}
                                    </div>
                                    <div class="followup-item-badges">
                                        <span class="priority-badge ${followup.priority}">
                                            ${followup.priority}
                                        </span>
                                        <span class="status-badge ${followup.status}">
                                            ${followup.status.replace('_', ' ')}
                                        </span>
                                    </div>
                                </div>
                                <div class="followup-item-meta">
                                    <div class="followup-item-meta-info">
                                        <span><i class="fas fa-tag"></i> ${followup.related_type}</span>
                                        ${dueDate ? `
                                            <span class="due-date${isOverdue ? ' overdue' : ''}">
                                                <i class="fas fa-calendar"></i> Due: ${this.formatDate(dueDate)}
                                                ${isOverdue ? ' (Overdue)' : ''}
                                            </span>
                                        ` : ''}
                                        ${followup.assigned_to_name ? `
                                            <span><i class="fas fa-user"></i> ${this.escapeHtml(followup.assigned_to_name)}</span>
                                        ` : ''}
                                    </div>
                                    <div class="followup-item-actions">
                                        ${followup.status !== 'completed' ? `
                                            <button class="btn btn-sm btn-success" data-action="complete-followup" data-id="${followup.id}" title="Complete">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        ` : ''}
                                        <button class="btn btn-sm btn-info" data-action="view-followup" data-id="${followup.id}" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" data-action="edit-followup" data-id="${followup.id}" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-secondary" data-action="print-followup" data-id="${followup.id}" title="Print">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning" data-action="duplicate-followup" data-id="${followup.id}" title="Duplicate">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <button class="btn btn-sm btn-secondary" data-action="export-followup" data-id="${followup.id}" title="Export">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" data-action="delete-followup" data-id="${followup.id}" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    listContainer.innerHTML = html;
                    
                    // Update pagination controls
                    this.updateFollowupPagination();
                    
                    // Setup bulk action handlers and event delegation for delete buttons
                    setTimeout(() => {
                        const selectAllCheckbox = document.getElementById('bulkSelectAllFollowups');
                        const followupCheckboxes = document.querySelectorAll('.followup-checkbox');
                        
                        if (selectAllCheckbox) {
                            selectAllCheckbox.removeEventListener('change', this._bulkSelectAllFollowupsHandler);
                            this._bulkSelectAllFollowupsHandler = (e) => {
                                const checked = e.target.checked;
                                followupCheckboxes.forEach(cb => {
                                    cb.checked = checked;
                                });
                                this.updateBulkActionsFollowups();
                            };
                            selectAllCheckbox.addEventListener('change', this._bulkSelectAllFollowupsHandler);
                        }
                        
                        followupCheckboxes.forEach(checkbox => {
                            checkbox.removeEventListener('change', this._followupCheckboxHandler);
                            this._followupCheckboxHandler = () => {
                                this.updateBulkActionsFollowups();
                            };
                            checkbox.addEventListener('change', this._followupCheckboxHandler);
                        });
                        
                        // Use event delegation on the container for delete buttons
                        listContainer.removeEventListener('click', this._followupsContainerClickHandler);
                        const self = this;
                        this._followupsContainerClickHandler = (e) => {
                            const deleteBtn = e.target.closest('button[data-action="delete-followup"]');
                            if (deleteBtn) {
                                e.preventDefault();
                                e.stopPropagation();
                                e.stopImmediatePropagation();
                                const followupId = deleteBtn.getAttribute('data-id');
                                if (followupId) {
                                    self.deleteFollowup(followupId);
                                }
                            }
                        };
                        listContainer.addEventListener('click', this._followupsContainerClickHandler, true);
                    }, 100);
                } else {
                    let errorMessage = data.message || 'Unknown error';
                    if (data.message && (data.message.includes('table not found') || data.message.includes('503'))) {
                        errorMessage = 'Database tables not found. Please click "Setup Follow-ups & Messages" in the navigation sidebar to create the required tables.';
                    }
                    listContainer.innerHTML = `
                        <div class="empty-state-container">
                            <i class="fas fa-exclamation-circle empty-state-icon error"></i>
                            <p class="empty-state-text">
                                Error loading follow-ups: ${errorMessage}
                            </p>
                            ${errorMessage.includes('table not found') || errorMessage.includes('503') ? `
                                <div class="setup-button-container">
                                    <button class="btn btn-primary" data-action="setup-followup-messages">
                                        <i class="fas fa-database"></i> Setup Database Tables
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    `;
                }
            } catch (error) {
                const listContainer = document.getElementById('followupsList');
                if (listContainer) {
                    let errorMessage = error.message;
                    if (error.message.includes('503') || error.message.includes('Service Unavailable') || error.message.includes('table not found')) {
                        errorMessage = 'Database tables not found. Please click "Setup Follow-ups & Messages" in the navigation sidebar to create the required tables.';
                    }
                    listContainer.innerHTML = `
                        <div class="empty-state-container">
                            <i class="fas fa-exclamation-circle empty-state-icon error"></i>
                            <p class="empty-state-text">
                                Error: ${errorMessage}
                            </p>
                            ${error.message.includes('503') || error.message.includes('Service Unavailable') || error.message.includes('table not found') ? `
                                <div class="setup-button-container">
                                    <button class="btn btn-primary" data-action="setup-followup-messages">
                                        <i class="fas fa-database"></i> Setup Database Tables
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    `;
                } else {
                    this.showToast('Error: Follow-ups container not found', 'error');
                }
            }
        },

        async loadMessages() {
            try {
                const typeFilter = document.getElementById('messageTypeFilter')?.value || '';
                const categoryFilter = document.getElementById('messageCategoryFilter')?.value || '';
                const unreadOnly = document.getElementById('unreadOnlyFilter')?.checked || false;
                const entriesPerPage = parseInt(document.getElementById('messageEntriesPerPage')?.value || '5');
                
                // Update per page if changed
                if (entriesPerPage !== this.messagePerPage) {
                    this.messagePerPage = entriesPerPage;
                    this.messageCurrentPage = 1; // Reset to first page
                }
                
                let url = `${this.apiBase}/messages.php?`;
                if (typeFilter) url += `type=${encodeURIComponent(typeFilter)}&`;
                if (categoryFilter) url += `category=${encodeURIComponent(categoryFilter)}&`;
                if (unreadOnly) url += `unread_only=true&`;
                
                const response = await fetch(url, {
                    cache: 'no-store',
                    headers: { 'Cache-Control': 'no-cache' }
                });
                
                if (!response.ok) {
                    if (response.status === 503) {
                        const errorData = await response.json().catch(() => ({}));
                        throw new Error(errorData.message || 'Database tables not found. Please click "Setup Follow-ups & Messages" in the navigation sidebar first.');
                    }
                    const errorText = await response.text().catch(() => 'Unknown error');
                    throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
                }
                
                const data = await response.json();
                const listContainer = document.getElementById('messagesList');
                const unreadBadge = document.getElementById('unreadMessagesBadge');
                
                if (!listContainer) return;
                
                // Update unread badge
                if (unreadBadge && data.unread_count !== undefined) {
                    if (data.unread_count > 0) {
                        unreadBadge.textContent = data.unread_count;
                        unreadBadge.classList.add('visible-inline-block');
                        unreadBadge.classList.remove('hidden');
                    } else {
                        unreadBadge.classList.add('hidden');
                        unreadBadge.classList.remove('visible-inline-block');
                    }
                }
                
                if (data.success && data.messages) {
                    // Store all messages
                    this.allMessages = data.messages;
                    this.messageTotalCount = data.messages.length;
                    
                    // Calculate pagination
                    this.messageTotalPages = Math.max(1, Math.ceil(this.messageTotalCount / this.messagePerPage));
                    if (this.messageCurrentPage > this.messageTotalPages) {
                        this.messageCurrentPage = this.messageTotalPages;
                    }
                    
                    // Get paginated subset
                    const startIndex = (this.messageCurrentPage - 1) * this.messagePerPage;
                    const endIndex = startIndex + this.messagePerPage;
                    const paginatedMessages = data.messages.slice(startIndex, endIndex);
                    
                    if (paginatedMessages.length === 0 && this.messageTotalCount === 0) {
                        listContainer.innerHTML = `
                            <div class="empty-state-container">
                                <i class="fas fa-envelope-open empty-state-icon"></i>
                                <p class="empty-state-text">No messages found</p>
                            </div>
                        `;
                        this.updateMessagePagination();
                        return;
                    }
                    
                    const typeColors = {
                        'info': '#3b82f6',
                        'warning': '#f59e0b',
                        'error': '#ef4444',
                        'success': '#10b981',
                        'alert': '#8b5cf6'
                    };
                    
                    let html = '<div class="message-items-container">';
                    html += `
                        <div class="bulk-select-header">
                            <label class="bulk-select-all-label">
                                <input type="checkbox" id="bulkSelectAllMessages" data-action="bulk-select-all-messages">
                                Select All
                            </label>
                        </div>
                    `;
                    paginatedMessages.forEach(msg => {
                        const isRead = msg.is_read_by_user === 1;
                        const createdAt = new Date(msg.created_at);
                        const readClass = isRead ? '' : ' unread';
                        
                        html += `
                            <div class="message-item${readClass}" data-id="${msg.id}" data-type="${msg.type}">
                                <div class="message-item-checkbox">
                                    <input type="checkbox" class="message-checkbox" data-id="${msg.id}" data-action="select-message">
                                </div>
                                <div class="message-item-header">
                                    <div class="message-item-content">
                                        <div class="message-item-title-wrapper">
                                            <h4 class="message-item-title">
                                                ${!isRead ? '<i class="fas fa-circle unread-indicator"></i> ' : ''}
                                                ${this.escapeHtml(msg.title)}
                                            </h4>
                                            ${msg.is_important ? `
                                                <span class="important-badge">
                                                    IMPORTANT
                                                </span>
                                            ` : ''}
                                        </div>
                                        <p class="message-item-body">
                                            ${this.escapeHtml(msg.message)}
                                        </p>
                                    </div>
                                    <div class="message-item-badges">
                                        <span class="type-badge type-${msg.type}">
                                            ${msg.type}
                                        </span>
                                    </div>
                                </div>
                                <div class="message-item-meta">
                                    <div class="message-item-meta-info">
                                        <span><i class="fas fa-tag"></i> ${msg.category.replace('_', ' ')}</span>
                                        <span><i class="fas fa-clock"></i> ${this.formatDate(createdAt)}</span>
                                        ${msg.related_type ? `
                                            <span><i class="fas fa-link"></i> ${msg.related_type}</span>
                                        ` : ''}
                                    </div>
                                    <div class="message-item-actions">
                                        ${!isRead ? `
                                            <button class="btn btn-sm btn-success" data-action="mark-message-read" data-id="${msg.id}" title="Mark Read">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        ` : ''}
                                        <button class="btn btn-sm btn-info" data-action="view-message" data-id="${msg.id}" title="View">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        ${msg.action_url ? `
                                            <a href="${msg.action_url}" class="btn btn-sm btn-success" title="Open Link">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        ` : ''}
                                        <button class="btn btn-sm btn-secondary" data-action="print-message" data-id="${msg.id}" title="Print">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning" data-action="duplicate-message" data-id="${msg.id}" title="Duplicate">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                        <button class="btn btn-sm btn-secondary" data-action="export-message" data-id="${msg.id}" title="Export">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" data-action="delete-message" data-id="${msg.id}" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    listContainer.innerHTML = html;
                    
                    // Update pagination controls
                    this.updateMessagePagination();
                    
                    // Setup bulk action handlers and direct delete handlers after rendering
                    setTimeout(() => {
                        const selectAllCheckbox = document.getElementById('bulkSelectAllMessages');
                        const messageCheckboxes = document.querySelectorAll('.message-checkbox');
                        
                        if (selectAllCheckbox) {
                            selectAllCheckbox.removeEventListener('change', this._bulkSelectAllMessagesHandler);
                            this._bulkSelectAllMessagesHandler = (e) => {
                                const checked = e.target.checked;
                                messageCheckboxes.forEach(cb => {
                                    cb.checked = checked;
                                });
                                this.updateBulkActionsMessages();
                            };
                            selectAllCheckbox.addEventListener('change', this._bulkSelectAllMessagesHandler);
                        }
                        
                        messageCheckboxes.forEach(checkbox => {
                            checkbox.removeEventListener('change', this._messageCheckboxHandler);
                            this._messageCheckboxHandler = () => {
                                this.updateBulkActionsMessages();
                            };
                            checkbox.addEventListener('change', this._messageCheckboxHandler);
                        });
                        
                        // Use event delegation on the container for delete buttons
                        listContainer.removeEventListener('click', this._messagesContainerClickHandler);
                        const self = this;
                        this._messagesContainerClickHandler = (e) => {
                            const deleteBtn = e.target.closest('button[data-action="delete-message"]');
                            if (deleteBtn) {
                                e.preventDefault();
                                e.stopPropagation();
                                e.stopImmediatePropagation();
                                const messageId = deleteBtn.getAttribute('data-id');
                                if (messageId) {
                                    self.deleteMessage(messageId);
                                }
                            }
                        };
                        listContainer.addEventListener('click', this._messagesContainerClickHandler, true);
                    }, 100);
                } else {
                    let errorMessage = data.message || 'Unknown error';
                    if (data.message && (data.message.includes('table not found') || data.message.includes('503'))) {
                        errorMessage = 'Database tables not found. Please click "Setup Follow-ups & Messages" in the navigation sidebar to create the required tables.';
                    }
                    listContainer.innerHTML = `
                        <div class="empty-state-container">
                            <i class="fas fa-exclamation-circle empty-state-icon error"></i>
                            <p class="empty-state-text">
                                Error loading messages: ${errorMessage}
                            </p>
                            ${errorMessage.includes('table not found') || errorMessage.includes('503') ? `
                                <div class="setup-button-container">
                                    <button class="btn btn-primary" data-action="setup-followup-messages">
                                        <i class="fas fa-database"></i> Setup Database Tables
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    `;
                }
            } catch (error) {
                const listContainer = document.getElementById('messagesList');
                if (listContainer) {
                    let errorMessage = error.message;
                    if (error.message.includes('503') || error.message.includes('Service Unavailable')) {
                        errorMessage = 'Database tables not found. Please click "Setup Follow-ups & Messages" in the navigation sidebar to create the required tables.';
                    }
                    listContainer.innerHTML = `
                        <div class="empty-state-container">
                            <i class="fas fa-exclamation-circle empty-state-icon error"></i>
                            <p class="empty-state-text">
                                Error: ${errorMessage}
                            </p>
                            ${error.message.includes('503') || error.message.includes('Service Unavailable') || error.message.includes('table not found') ? `
                                <div class="setup-button-container">
                                    <button class="btn btn-primary" data-action="setup-followup-messages">
                                        <i class="fas fa-database"></i> Setup Database Tables
                                    </button>
                                </div>
                            ` : ''}
                        </div>
                    `;
                }
            }
        },

        async completeFollowup(followupId) {
            try {
                const response = await fetch(`${this.apiBase}/followups.php`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: parseInt(followupId),
                        status: 'completed'
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    this.showToast('Follow-up marked as completed!', 'success');
                    this.loadFollowups(); // Refresh the list
                } else {
                    this.showToast('Error: ' + (data.message || 'Failed to complete follow-up'), 'error');
                }
            } catch (error) {
                this.showToast('Error completing follow-up: ' + error.message, 'error');
            }
        },

        async editFollowup(followupId) {
            try {
                // Fetch the follow-up data
                const response = await fetch(`${this.apiBase}/followups.php?id=${parseInt(followupId)}`);
                const data = await response.json();
                
                if (!data.success || !data.followup) {
                    this.showToast('Error: ' + (data.message || 'Failed to load follow-up'), 'error');
                    return;
                }
                
                const followup = data.followup;
                
                // Populate the form fields
                document.getElementById('editFollowupId').value = followup.id;
                document.getElementById('editFollowupTitle').value = followup.title || '';
                document.getElementById('editFollowupDescription').value = followup.description || '';
                document.getElementById('editFollowupRelatedType').value = followup.related_type || '';
                document.getElementById('editFollowupRelatedId').value = followup.related_id || 0;
                document.getElementById('editFollowupDueDate').value = followup.due_date ? followup.due_date.split(' ')[0] : '';
                document.getElementById('editFollowupPriority').value = followup.priority || 'medium';
                document.getElementById('editFollowupStatus').value = followup.status || 'pending';
                document.getElementById('editFollowupNotes').value = followup.notes || '';
                
                // Show the modal
                this.showEditFollowupForm();
            } catch (error) {
                this.showToast('Error loading follow-up: ' + error.message, 'error');
            }
        },

        async viewFollowup(followupId) {
            // Open edit modal in view mode
            await this.editFollowup(followupId);
            // You can add view-only mode logic here if needed
        },

        async printFollowup(followupId) {
            try {
                const response = await fetch(`${this.apiBase}/followups.php?id=${parseInt(followupId)}`);
                const data = await response.json();
                
                if (data.success && data.followup) {
                    const followup = data.followup;
                    const printWindow = window.open('', '_blank', 'width=800,height=600');
                    if (!printWindow) {
                        this.showToast('Please allow popups to print follow-up', 'warning');
                        return;
                    }
                    
                    const dueDate = followup.due_date ? this.formatDate(followup.due_date) : 'N/A';
                    const createdAt = followup.created_at ? this.formatDate(followup.created_at) : 'N/A';
                    
                    printWindow.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Follow-up Task</title>
                            <style>
                                body { font-family: Arial, sans-serif; padding: 20px; }
                                h2 { color: #333; border-bottom: 2px solid #3b82f6; padding-bottom: 10px; }
                                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                                td { padding: 8px; border-bottom: 1px solid #ddd; }
                                .label { font-weight: bold; width: 150px; }
                                .value { }
                            </style>
                        </head>
                        <body>
                            <h2>Follow-up Task</h2>
                            <table>
                                <tr><td class="label">Title:</td><td class="value">${this.escapeHtml(followup.title || 'N/A')}</td></tr>
                                <tr><td class="label">Description:</td><td class="value">${this.escapeHtml(followup.description || 'N/A')}</td></tr>
                                <tr><td class="label">Status:</td><td class="value">${followup.status || 'N/A'}</td></tr>
                                <tr><td class="label">Priority:</td><td class="value">${followup.priority || 'N/A'}</td></tr>
                                <tr><td class="label">Due Date:</td><td class="value">${dueDate}</td></tr>
                                <tr><td class="label">Related Type:</td><td class="value">${followup.related_type || 'N/A'}</td></tr>
                                <tr><td class="label">Related ID:</td><td class="value">${followup.related_id || 'N/A'}</td></tr>
                                <tr><td class="label">Created:</td><td class="value">${createdAt}</td></tr>
                                ${followup.notes ? `<tr><td class="label">Notes:</td><td class="value">${this.escapeHtml(followup.notes)}</td></tr>` : ''}
                            </table>
                            <p class="print-footer">Printed: ${new Date().toLocaleString()}</p>
                        </body>
                        </html>
                    `);
                    printWindow.document.close();
                    setTimeout(() => {
                        printWindow.print();
                    }, 250);
                } else {
                    this.showToast('Error: ' + (data.message || 'Failed to load follow-up'), 'error');
                }
            } catch (error) {
                this.showToast('Error printing follow-up: ' + error.message, 'error');
            }
        },

        async duplicateFollowup(followupId) {
            try {
                const response = await fetch(`${this.apiBase}/followups.php?id=${parseInt(followupId)}`);
                const data = await response.json();
                
                if (data.success && data.followup) {
                    const followup = data.followup;
                    // Open new followup form with duplicated data
                    this.showNewFollowupForm();
                    setTimeout(() => {
                        document.getElementById('followupTitle').value = followup.title + ' (Copy)';
                        document.getElementById('followupDescription').value = followup.description || '';
                        document.getElementById('followupPriority').value = followup.priority || 'medium';
                        document.getElementById('followupStatus').value = 'pending';
                        document.getElementById('followupType').value = followup.related_type || '';
                        if (followup.due_date) {
                            document.getElementById('followupDueDate').value = followup.due_date.split(' ')[0];
                        }
                    }, 100);
                    this.showToast('Follow-up data loaded for duplication', 'success');
                } else {
                    this.showToast('Error: ' + (data.message || 'Failed to load follow-up'), 'error');
                }
            } catch (error) {
                this.showToast('Error duplicating follow-up: ' + error.message, 'error');
            }
        },

        async exportFollowup(followupId) {
            try {
                const response = await fetch(`${this.apiBase}/followups.php?id=${parseInt(followupId)}`);
                const data = await response.json();
                
                if (data.success && data.followup) {
                    const followup = data.followup;
                    const jsonData = JSON.stringify(followup, null, 2);
                    const blob = new Blob([jsonData], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `followup-${followup.id}-${new Date().toISOString().split('T')[0]}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    this.showToast('Follow-up exported successfully', 'success');
                } else {
                    this.showToast('Error: ' + (data.message || 'Failed to load follow-up'), 'error');
                }
            } catch (error) {
                this.showToast('Error exporting follow-up: ' + error.message, 'error');
            }
        },

        async deleteFollowup(followupId) {
            try {
                const followupIdInt = parseInt(followupId);
                if (!followupIdInt || isNaN(followupIdInt)) {
                    this.showToast('Invalid follow-up ID', 'error');
                    return;
                }
                
                const response = await fetch(`${this.apiBase}/followups.php?id=${followupIdInt}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' }
                });
                
                if (!response.ok) {
                    const errorText = await response.text().catch(() => 'Unknown error');
                    throw new Error(`HTTP ${response.status}: ${errorText}`);
                }
                
                const data = await response.json();
                if (data.success) {
                    this.showToast('Follow-up deleted successfully!', 'success');
                    this.loadFollowups(); // Refresh the list
                } else {
                    this.showToast('Error: ' + (data.message || 'Failed to delete follow-up'), 'error');
                }
            } catch (error) {
                this.showToast('Error deleting follow-up: ' + error.message, 'error');
            }
        },

        async markMessageRead(messageId) {
            try {
                const response = await fetch(`${this.apiBase}/messages.php?id=${messageId}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ is_read: 1 })
                });
                const data = await response.json();
                if (data.success) {
                    await this.loadMessages();
                }
            } catch (error) {
                console.error('Error marking message as read:', error);
            }
        },

        async viewMessage(messageId) {
            try {
                const response = await fetch(`${this.apiBase}/messages.php?id=${messageId}`);
                const data = await response.json();
                if (data.success && data.message) {
                    const msg = data.message;
                    const content = `
                        <div class="message-details">
                            <h3>${this.escapeHtml(msg.subject || 'Message')}</h3>
                            <div class="detail-row">
                                <label>From:</label>
                                <span>${this.escapeHtml(msg.from_name || 'System')}</span>
                            </div>
                            <div class="detail-row">
                                <label>Date:</label>
                                <span>${msg.created_at || 'N/A'}</span>
                            </div>
                            <div class="detail-row">
                                <label>Message:</label>
                                <div>${this.escapeHtml(msg.message || 'N/A')}</div>
                            </div>
                        </div>
                    `;
                    this.showModal('View Message', content);
                    // Mark as read
                    await this.markMessageRead(messageId);
                } else {
                    this.showToast(data.message || 'Failed to load message', 'error');
                }
            } catch (error) {
                this.showToast('Error loading message: ' + error.message, 'error');
            }
        },

        async printMessage(messageId) {
            try {
                const response = await fetch(`${this.apiBase}/messages.php?id=${messageId}&format=print`);
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const printWindow = window.open(url, '_blank');
                    if (printWindow) {
                        printWindow.onload = () => printWindow.print();
                    }
                } else {
                    this.showToast('Failed to generate print view', 'error');
                }
            } catch (error) {
                this.showToast('Error printing message: ' + error.message, 'error');
            }
        },

        async duplicateMessage(messageId) {
            try {
                const response = await fetch(`${this.apiBase}/messages.php?id=${messageId}&action=duplicate`, {
                    method: 'POST'
                });
                const data = await response.json();
                if (data.success) {
                    this.showToast('Message duplicated successfully', 'success');
                    await this.loadMessages();
            } else {
                    this.showToast(data.message || 'Failed to duplicate message', 'error');
                }
            } catch (error) {
                this.showToast('Error duplicating message: ' + error.message, 'error');
            }
        },

        async exportMessage(messageId) {
            try {
                const response = await fetch(`${this.apiBase}/messages.php?id=${messageId}`);
                const data = await response.json();
                if (data.success && data.message) {
                    const jsonData = JSON.stringify(data.message, null, 2);
                    const blob = new Blob([jsonData], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `message-${messageId}-${new Date().toISOString().split('T')[0]}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                URL.revokeObjectURL(url);
                    this.showToast('Message exported successfully', 'success');
                } else {
                    this.showToast('Error: ' + (data.message || 'Failed to load message'), 'error');
                }
            } catch (error) {
                this.showToast('Error exporting message: ' + error.message, 'error');
            }
        },

        async deleteMessage(messageId) {
            const confirmed = await this.showConfirmDialog(
                'Delete Message',
                'Are you sure you want to delete this message? This action cannot be undone.',
                'Delete',
                'Cancel',
                'danger'
            );
            if (confirmed) {
                try {
                    const response = await fetch(`${this.apiBase}/messages.php?id=${messageId}`, {
                        method: 'DELETE'
                    });
                    const data = await response.json();
                    if (data.success) {
                        this.showToast('Message deleted successfully', 'success');
                        await this.loadMessages();
                    } else {
                        this.showToast(data.message || 'Failed to delete message', 'error');
                    }
                } catch (error) {
                    this.showToast('Error deleting message: ' + error.message, 'error');
                }
            }
        },

        setupBankingCashHandlers() {
            const modal = document.getElementById('bankingCashModal') || document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
            if (!modal) return;
            
            const self = this;
            
            // New Bank Account button
            const newBankAccountBtn = modal.querySelector('[data-action="new-bank-account"]');
            if (newBankAccountBtn) {
                const newBtn = newBankAccountBtn.cloneNode(true);
                newBankAccountBtn.parentNode.replaceChild(newBtn, newBankAccountBtn);
                newBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Check if function exists directly
                    if (typeof self.openBankAccountForm === 'function') {
                        self.openBankAccountForm();
                    } else if (typeof ProfessionalAccounting !== 'undefined' && typeof ProfessionalAccounting.prototype.openBankAccountForm === 'function') {
                        // Try prototype method
                        ProfessionalAccounting.prototype.openBankAccountForm.call(self);
                    } else if (typeof self.getBankAccountModalContent === 'function') {
                        // Fallback: use getBankAccountModalContent directly (similar to invoice modal fix)
                        try {
                            const content = self.getBankAccountModalContent(null);
                            self.showModal('Add Bank Account', content, 'normal', 'bankAccountFormModal');
                            
                            // Setup form handlers after modal is shown
                            setTimeout(() => {
                                const form = document.getElementById('bankAccountForm');
                                if (form && typeof self.saveBankAccount === 'function') {
                                    form.addEventListener('submit', async (e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        await self.saveBankAccount(null);
                                    });
                                }
                            }, 100);
                        } catch (error) {
                            console.error('Error opening bank account form:', error);
                            self.showToast('Error opening bank account form. Please refresh the page.', 'error');
                        }
                    } else if (typeof ProfessionalAccounting !== 'undefined' && typeof ProfessionalAccounting.prototype.getBankAccountModalContent === 'function') {
                        // Try prototype getBankAccountModalContent
                        try {
                            const content = ProfessionalAccounting.prototype.getBankAccountModalContent.call(self, null);
                            self.showModal('Add Bank Account', content, 'normal', 'bankAccountFormModal');
                            
                            // Setup form handlers after modal is shown
                            setTimeout(() => {
                                const form = document.getElementById('bankAccountForm');
                                if (form && typeof self.saveBankAccount === 'function') {
                                    form.addEventListener('submit', async (e) => {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        await self.saveBankAccount(null);
                                    });
                                }
                            }, 100);
                        } catch (error) {
                            console.error('Error opening bank account form:', error);
                            self.showToast('Error opening bank account form. Please refresh the page.', 'error');
                        }
                    } else {
                        console.error('openBankAccountForm function not found');
                        self.showToast('Bank account form function not available. Please refresh the page.', 'error');
                    }
                });
            }
            
            // Refresh button
            const refreshBtn = modal.querySelector('[data-action="refresh-banking"]');
            if (refreshBtn) {
                const newRefreshBtn = refreshBtn.cloneNode(true);
                refreshBtn.parentNode.replaceChild(newRefreshBtn, refreshBtn);
                newRefreshBtn.addEventListener('click', async () => {
                    await this.loadBankAccounts();
                    this.showToast('Bank accounts refreshed', 'success');
                });
            }
            
            // Apply filters button
            const applyFiltersBtn = modal.querySelector('[data-action="apply-banking-filters"]');
            if (applyFiltersBtn) {
                const newBtn = applyFiltersBtn.cloneNode(true);
                applyFiltersBtn.parentNode.replaceChild(newBtn, applyFiltersBtn);
                newBtn.addEventListener('click', () => {
                    this.applyBankingFilters();
                });
            }
            
            // Search input (debounced)
            const searchInput = modal.querySelector('#bankingSearchInput');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.bankingSearch = e.target.value;
                        this.bankingCurrentPage = 1;
                        this.loadBankAccounts();
                    }, 500);
                });
            }
            
            // Account Type filter (auto-apply)
            const typeFilter = modal.querySelector('#bankingTypeFilter');
            if (typeFilter) {
                typeFilter.addEventListener('change', (e) => {
                    this.bankingTypeFilter = e.target.value;
                    this.bankingCurrentPage = 1;
                    this.loadBankAccounts();
                });
            }
            
            // Status filter (auto-apply)
            const statusFilter = modal.querySelector('#bankingStatusFilter');
            if (statusFilter) {
                statusFilter.addEventListener('change', (e) => {
                    this.bankingStatusFilter = e.target.value;
                    this.bankingCurrentPage = 1;
                    this.loadBankAccounts();
                });
            }
            
            // Per page selector
            const perPageSelect = modal.querySelector('#bankingPerPageSelect');
            if (perPageSelect) {
                perPageSelect.addEventListener('change', (e) => {
                    this.bankingPerPage = parseInt(e.target.value);
                    this.bankingCurrentPage = 1;
                    this.loadBankAccounts();
                });
            }
            
            // Pagination buttons
            const prevBtn = modal.querySelector('#bankingPrevPage');
            if (prevBtn) {
                const newBtn = prevBtn.cloneNode(true);
                prevBtn.parentNode.replaceChild(newBtn, prevBtn);
                newBtn.addEventListener('click', () => {
                    if (this.bankingCurrentPage > 1) {
                        this.bankingCurrentPage--;
                        this.loadBankAccounts();
                    }
                });
            }
            
            const nextBtn = modal.querySelector('#bankingNextPage');
            if (nextBtn) {
                const newBtn = nextBtn.cloneNode(true);
                nextBtn.parentNode.replaceChild(newBtn, nextBtn);
                newBtn.addEventListener('click', () => {
                    if (this.bankingCurrentPage < this.bankingTotalPages) {
                        this.bankingCurrentPage++;
                        this.loadBankAccounts();
                    }
                });
            }
            
            // Page number buttons
            const pageNumbersContainer = modal.querySelector('#bankingPageNumbers');
            if (pageNumbersContainer) {
                pageNumbersContainer.addEventListener('click', (e) => {
                    const pageBtn = e.target.closest('.page-number');
                    if (pageBtn && !pageBtn.classList.contains('active')) { 
                        const page = parseInt(pageBtn.dataset.page);
                        if (page && page >= 1 && page <= this.bankingTotalPages) {
                            this.bankingCurrentPage = page;
                            this.loadBankAccounts();
                        }
                    }
                });
            }
            
            // Sortable column headers
            modal.querySelectorAll('.sortable').forEach(header => {
                const newHeader = header.cloneNode(true);
                header.parentNode.replaceChild(newHeader, header);
                newHeader.addEventListener('click', () => {
                    const column = newHeader.dataset.sort;
                    if (this.bankingSortColumn === column) {
                        this.bankingSortDirection = this.bankingSortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.bankingSortColumn = column;
                        this.bankingSortDirection = 'asc';
                    }
                    this.loadBankAccounts();
                });
            });
            
            // Select All checkbox
            const selectAllCheckbox = modal.querySelector('#bankingSelectAll');
            if (selectAllCheckbox) {
                const newCheckbox = selectAllCheckbox.cloneNode(true);
                selectAllCheckbox.parentNode.replaceChild(newCheckbox, selectAllCheckbox);
                newCheckbox.addEventListener('change', (e) => {
                    const isChecked = e.target.checked;
                    const tbody = modal.querySelector('#bankAccountsTableBody');
                    if (tbody) {
                        tbody.querySelectorAll('.bank-account-checkbox').forEach(checkbox => {
                            checkbox.checked = isChecked;
                            const bankId = parseInt(checkbox.dataset.bankId);
                            if (!isNaN(bankId)) {
                                if (isChecked) {
                                    this.bankingSelectedAccounts.add(bankId);
                                } else {
                                    this.bankingSelectedAccounts.delete(bankId);
                                }
                            }
                        });
                        // Update row selection classes
                        tbody.querySelectorAll('tr[data-bank-id]').forEach(row => {
                            const bankId = parseInt(row.dataset.bankId);
                            if (!isNaN(bankId)) {
                                if (isChecked) {
                                    row.classList.add('row-selected');
                                } else {
                                    row.classList.remove('row-selected');
                                }
                            }
                        });
                    }
                    this.updateBankingBulkActionsBar();
                });
            }
            
            // Bulk action buttons
            const exportSelectedBtn = modal.querySelector('[data-action="export-selected-banks"]');
            if (exportSelectedBtn) {
                const newBtn = exportSelectedBtn.cloneNode(true);
                exportSelectedBtn.parentNode.replaceChild(newBtn, exportSelectedBtn);
                newBtn.addEventListener('click', () => {
                    this.exportSelectedBankAccounts();
                });
            }
            
            const printSelectedBtn = modal.querySelector('[data-action="print-selected-banks"]');
            if (printSelectedBtn) {
                const newBtn = printSelectedBtn.cloneNode(true);
                printSelectedBtn.parentNode.replaceChild(newBtn, printSelectedBtn);
                newBtn.addEventListener('click', () => {
                    this.printSelectedBankAccounts();
                });
            }
            
            const deleteSelectedBtn = modal.querySelector('[data-action="delete-selected-banks"]');
            if (deleteSelectedBtn) {
                const newBtn = deleteSelectedBtn.cloneNode(true);
                deleteSelectedBtn.parentNode.replaceChild(newBtn, deleteSelectedBtn);
                newBtn.addEventListener('click', () => {
                    this.deleteSelectedBankAccounts();
                });
            }
            
            // Activate selected button
            const activateSelectedBtn = modal.querySelector('[data-action="activate-selected-banks"]');
            if (activateSelectedBtn) {
                const newBtn = activateSelectedBtn.cloneNode(true);
                activateSelectedBtn.parentNode.replaceChild(newBtn, activateSelectedBtn);
                newBtn.addEventListener('click', () => {
                    this.activateSelectedBankAccounts();
                });
            }
            
            // Inactivate selected button
            const inactivateSelectedBtn = modal.querySelector('[data-action="inactivate-selected-banks"]');
            if (inactivateSelectedBtn) {
                const newBtn = inactivateSelectedBtn.cloneNode(true);
                inactivateSelectedBtn.parentNode.replaceChild(newBtn, inactivateSelectedBtn);
                newBtn.addEventListener('click', () => {
                    this.inactivateSelectedBankAccounts();
                            });
                        }
                    },

        setupBankingBulkActions() {
            const modal = document.getElementById('bankingCashModal') || document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
            if (!modal) return;
            
            // Individual checkboxes
            modal.querySelectorAll('.bank-account-checkbox').forEach(checkbox => {
                const newCheckbox = checkbox.cloneNode(true);
                checkbox.parentNode.replaceChild(newCheckbox, checkbox);
                newCheckbox.addEventListener('change', (e) => {
                    const bankId = parseInt(e.target.dataset.bankId);
                    const row = e.target.closest('tr');
                    
                    if (e.target.checked) {
                        this.bankingSelectedAccounts.add(bankId);
                        if (row) row.classList.add('row-selected');
                        } else {
                        this.bankingSelectedAccounts.delete(bankId);
                        if (row) row.classList.remove('row-selected');
                    }
                    
                    this.updateBankingBulkActionsBar();
                    this.updateSelectAllCheckbox();
                });
            });
        },

        updateBankingBulkActionsBar() {
            const bulkActionsBar = document.getElementById('bankingBulkActions');
            const selectedCountEl = document.getElementById('bankingSelectedCount');
            
            if (bulkActionsBar && selectedCountEl) {
                const count = this.bankingSelectedAccounts.size;
                selectedCountEl.textContent = count;
                bulkActionsBar.style.display = count > 0 ? 'flex' : 'none';
            }
        },

        updateSelectAllCheckbox() {
            const modal = document.getElementById('bankingCashModal') || document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
            if (!modal) return;
            
            const selectAllCheckbox = modal.querySelector('#bankingSelectAll');
            if (!selectAllCheckbox) return;
            
            const allCheckboxes = modal.querySelectorAll('.bank-account-checkbox');
            const checkedCount = Array.from(allCheckboxes).filter(cb => cb.checked).length;
            
            selectAllCheckbox.checked = allCheckboxes.length > 0 && checkedCount === allCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < allCheckboxes.length;
        },

        clearBankingFilters() {
            this.bankingSearch = '';
            this.bankingTypeFilter = '';
            this.bankingStatusFilter = '';
            this.bankingCurrentPage = 1;
            
            const modal = document.getElementById('bankingCashModal') || document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
            if (modal) {
                const searchInput = modal.querySelector('#bankingSearchInput');
                const typeFilter = modal.querySelector('#bankingTypeFilter');
                const statusFilter = modal.querySelector('#bankingStatusFilter');
                
                if (searchInput) searchInput.value = '';
                if (typeFilter) typeFilter.value = '';
                if (statusFilter) statusFilter.value = '';
            }
            
            this.loadBankAccounts();
        },

        exportSelectedBankAccounts() {
            if (this.bankingSelectedAccounts.size === 0) {
                this.showToast('No accounts selected', 'warning');
                return;
            }
            
            const selectedBanks = this.bankingAllAccounts.filter(bank => 
                this.bankingSelectedAccounts.has(bank.id)
            );
            
            if (selectedBanks.length === 0) {
                this.showToast('No accounts to export', 'warning');
                return;
            }
            
            // Create CSV content
            const headers = ['ID', 'Bank Name', 'Account Name', 'Account Number', 'Account Type', 'Opening Balance', 'Current Balance', 'Status'];
            const rows = selectedBanks.map(bank => {
                const formattedId = `BA${String(bank.id).padStart(3, '0')}`;
                return [
                    formattedId,
                    bank.bank_name || '',
                    bank.account_name || '',
                    bank.account_number || '',
                    bank.account_type || 'Checking',
                    bank.opening_balance || 0,
                    bank.current_balance || 0,
                    bank.is_active ? 'Active' : 'Inactive'
                ];
            });
            
            const csvContent = [
                headers.join(','),
                ...rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','))
            ].join('\n');
            
            // Download CSV
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `bank_accounts_${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            this.showToast(`${selectedBanks.length} account(s) exported successfully`, 'success');
        },

        printSelectedBankAccounts() {
            if (this.bankingSelectedAccounts.size === 0) {
                this.showToast('No accounts selected', 'warning');
                return;
            }
            
            const selectedBanks = this.bankingAllAccounts.filter(bank => 
                this.bankingSelectedAccounts.has(bank.id)
            );
            
            if (selectedBanks.length === 0) {
                this.showToast('No accounts to print', 'warning');
                return;
            }
            
            // Create print window
            const printWindow = window.open('', '_blank');
            const formattedId = (id) => `BA${String(id).padStart(3, '0')}`;
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                    <head>
                    <title>Bank Accounts Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { color: #333; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #4CAF50; color: white; }
                        tr:nth-child(even) { background-color: #f2f2f2; }
                        .amount { text-align: right; }
                    </style>
                    </head>
                <body>
                    <h1>Bank Accounts Report</h1>
                        <p>Generated: ${new Date().toLocaleString()}</p>
                    <p>Total Accounts: ${selectedBanks.length}</p>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Bank Name</th>
                                <th>Account Name</th>
                                <th>Account Number</th>
                                <th>Account Type</th>
                                <th class="amount">Opening Balance</th>
                                <th class="amount">Current Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${selectedBanks.map(bank => `
                                <tr>
                                    <td>${formattedId(bank.id)}</td>
                                    <td>${this.escapeHtml(bank.bank_name || 'N/A')}</td>
                                    <td>${this.escapeHtml(bank.account_name || 'N/A')}</td>
                                    <td>${this.escapeHtml(bank.account_number || 'N/A')}</td>
                                    <td>${this.escapeHtml(bank.account_type || 'Checking')}</td>
                                    <td class="amount">${this.formatCurrency(bank.opening_balance || 0)}</td>
                                    <td class="amount">${this.formatCurrency(bank.current_balance || 0)}</td>
                                    <td>${bank.is_active ? 'Active' : 'Inactive'}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
            
            this.showToast(`${selectedBanks.length} account(s) sent to printer`, 'success');
        },

        filterAndSortBankAccounts(banks) {
            let filtered = [...banks];
            
            // Apply search filter
            if (this.bankingSearch && this.bankingSearch.trim()) {
                const searchTerm = this.bankingSearch.toLowerCase().trim();
                filtered = filtered.filter(bank => 
                    (bank.bank_name && bank.bank_name.toLowerCase().includes(searchTerm)) ||
                    (bank.account_name && bank.account_name.toLowerCase().includes(searchTerm)) ||
                    (bank.account_number && bank.account_number.toLowerCase().includes(searchTerm))
                );
            }
            
            // Apply type filter
            if (this.bankingTypeFilter) {
                filtered = filtered.filter(bank => bank.account_type === this.bankingTypeFilter);
            }
            
            // Apply status filter
            if (this.bankingStatusFilter) {
                if (this.bankingStatusFilter === 'active') {
                    filtered = filtered.filter(bank => bank.is_active);
                } else if (this.bankingStatusFilter === 'inactive') {
                    filtered = filtered.filter(bank => !bank.is_active);
                }
            }
            
            // Apply sorting
            if (this.bankingSortColumn) {
                filtered.sort((a, b) => {
                    let aVal = a[this.bankingSortColumn];
                    let bVal = b[this.bankingSortColumn];
                    
                    // Handle null/undefined values
                    if (aVal === null || aVal === undefined) aVal = '';
                    if (bVal === null || bVal === undefined) bVal = '';
                    
                    // Convert to comparable types
                    if (typeof aVal === 'string') aVal = aVal.toLowerCase();
                    if (typeof bVal === 'string') bVal = bVal.toLowerCase();
                    
                    if (this.bankingSortDirection === 'asc') {
                        return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
                    } else {
                        return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
                    }
                });
            }
            
            return filtered;
        },

        updateBankingPaginationControls() {
            const pageStart = this.bankingTotalCount === 0 ? 0 : (this.bankingCurrentPage - 1) * this.bankingPerPage + 1;
            const pageEnd = Math.min(this.bankingCurrentPage * this.bankingPerPage, this.bankingTotalCount);
            
            const pageStartEl = document.getElementById('bankingPageStart');
            const pageEndEl = document.getElementById('bankingPageEnd');
            const totalCountEl = document.getElementById('bankingTotalCount');
            const prevBtn = document.getElementById('bankingPrevPage');
            const nextBtn = document.getElementById('bankingNextPage');
            const pageNumbersContainer = document.getElementById('bankingPageNumbers');
            
            if (pageStartEl) pageStartEl.textContent = pageStart;
            if (pageEndEl) pageEndEl.textContent = pageEnd;
            if (totalCountEl) totalCountEl.textContent = this.bankingTotalCount;
            if (prevBtn) prevBtn.disabled = this.bankingCurrentPage <= 1;
            if (nextBtn) nextBtn.disabled = this.bankingCurrentPage >= this.bankingTotalPages;
            
            // Generate page number buttons
            if (pageNumbersContainer) {
                let pageNumbersHTML = '';
                const totalPages = this.bankingTotalPages;
                const currentPage = this.bankingCurrentPage;
                
                if (totalPages <= 1) {
                    pageNumbersHTML = '';
                } else {
                    const maxVisible = 5;
                    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
                    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
                    
                    if (endPage - startPage < maxVisible - 1) {
                        startPage = Math.max(1, endPage - maxVisible + 1);
                    }
                    
                    // First page
                    if (startPage > 1) {
                        pageNumbersHTML += `<button class="page-number" data-page="1">1</button>`;
                        if (startPage > 2) {
                            pageNumbersHTML += '<span class="page-ellipsis">...</span>';
                        }
                    }
                    
                    // Page numbers around current
                    for (let i = startPage; i <= endPage; i++) {
                        const isActive = i === currentPage ? 'active' : '';
                        pageNumbersHTML += `<button class="page-number ${isActive}" data-page="${i}">${i}</button>`;
                    }
                    
                    // Last page
                    if (endPage < totalPages) {
                        if (endPage < totalPages - 1) {
                            pageNumbersHTML += '<span class="page-ellipsis">...</span>';
                        }
                        pageNumbersHTML += `<button class="page-number" data-page="${totalPages}">${totalPages}</button>`;
                    }
                }
                
                pageNumbersContainer.innerHTML = pageNumbersHTML;
            }
        },

        updateBankingStatusCards(banks = null) {
            // Use provided banks or fall back to stored accounts
            const allBanks = banks !== null ? banks : (this.bankingAllAccounts || []);
            
            // Calculate statistics
            const totalAccounts = allBanks.length;
            const activeAccounts = allBanks.filter(bank => bank.is_active === 1 || bank.is_active === true).length;
            const inactiveAccounts = totalAccounts - activeAccounts;
            const totalBalance = allBanks.reduce((sum, bank) => {
                const balance = parseFloat(bank.current_balance || 0);
                return sum + (isNaN(balance) ? 0 : balance);
            }, 0);
            
            // Update DOM elements
            const totalAccountsEl = document.getElementById('bankingTotalAccounts');
            const activeAccountsEl = document.getElementById('bankingActiveAccounts');
            const inactiveAccountsEl = document.getElementById('bankingInactiveAccounts');
            const totalBalanceEl = document.getElementById('bankingTotalBalance');
            
            if (totalAccountsEl) totalAccountsEl.textContent = totalAccounts;
            if (activeAccountsEl) activeAccountsEl.textContent = activeAccounts;
            if (inactiveAccountsEl) inactiveAccountsEl.textContent = inactiveAccounts;
            if (totalBalanceEl) {
                totalBalanceEl.textContent = this.formatCurrency(totalBalance);
            }
        },

        setupBankAccountActions() {
            const modal = document.getElementById('bankingCashModal') || document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
            if (!modal) return;
            
            // Remove old event listeners by cloning buttons (prevents duplicates)
            const viewButtons = modal.querySelectorAll('[data-action="view-bank-account"]');
            const editButtons = modal.querySelectorAll('[data-action="edit-bank-account"]');
            const deleteButtons = modal.querySelectorAll('[data-action="delete-bank-account"]');
            
            // View bank account
            viewButtons.forEach(btn => {
                // Clone to remove old listeners
                const newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);
                
                newBtn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    this.viewBankAccount(id);
                });
            });
            
            // Edit bank account
            editButtons.forEach(btn => {
                // Clone to remove old listeners
                const newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);
                
                newBtn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    const self = this;
                    // Check if function exists directly
                    if (typeof self.openBankAccountForm === 'function') {
                        self.openBankAccountForm(id);
                    } else if (typeof ProfessionalAccounting !== 'undefined' && typeof ProfessionalAccounting.prototype.openBankAccountForm === 'function') {
                        // Try prototype method
                        ProfessionalAccounting.prototype.openBankAccountForm.call(self, id);
                    } else {
                        console.error('openBankAccountForm function not found');
                        self.showToast('Bank account form function not available. Please refresh the page.', 'error');
                    }
                });
            });
            
            // Delete bank account
            deleteButtons.forEach(btn => {
                // Clone to remove old listeners
                const newBtn = btn.cloneNode(true);
                btn.parentNode.replaceChild(newBtn, btn);
                
                newBtn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    this.deleteBankAccount(id);
                });
            });
        },

        async loadBankingCashModal() {
            // Reset selection when opening modal
            this.bankingSelectedAccounts.clear();
            
            // Open banking & cash modal - similar to other modals
            const modal = document.getElementById('bankingCashModal');
            if (modal) {
                modal.classList.remove('accounting-modal-hidden');
                modal.classList.add('accounting-modal-visible');
                this.activeModal = modal;
                
                // Ensure status cards exist - add them if missing
                const moduleContent = modal.querySelector('.accounting-module-modal-content');
                if (moduleContent) {
                    const statusCards = moduleContent.querySelector('#bankingStatusCards');
                    if (!statusCards) {
                        // Status cards don't exist, add them before filters
                        const filtersContainer = moduleContent.querySelector('.filters-and-pagination-container');
                        if (filtersContainer) {
                            const statusCardsHTML = `
                                <div class="report-status-cards" id="bankingStatusCards">
                                    <div class="stat-card stat-card-primary">
                                        <div class="stat-icon stat-icon-primary">
                                            <i class="fas fa-university"></i>
                                        </div>
                                        <div class="stat-info">
                                            <div class="stat-label">Total Accounts</div>
                                            <div class="stat-value" id="bankingTotalAccounts">0</div>
                                        </div>
                                    </div>
                                    <div class="stat-card stat-card-success">
                                        <div class="stat-icon stat-icon-success">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="stat-info">
                                            <div class="stat-label">Active Accounts</div>
                                            <div class="stat-value" id="bankingActiveAccounts">0</div>
                                        </div>
                                    </div>
                                    <div class="stat-card stat-card-warning">
                                        <div class="stat-icon stat-icon-warning">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <div class="stat-info">
                                            <div class="stat-label">Inactive Accounts</div>
                                            <div class="stat-value" id="bankingInactiveAccounts">0</div>
                                        </div>
                                    </div>
                                    <div class="stat-card stat-card-balance">
                                        <div class="stat-icon stat-icon-info">
                                            <i class="fas fa-wallet"></i>
                                        </div>
                                        <div class="stat-info">
                                            <div class="stat-label">Total Balance</div>
                                            <div class="stat-value" id="bankingTotalBalance">SAR 0.00</div>
                                        </div>
                                    </div>
                                </div>
                            `;
                            filtersContainer.insertAdjacentHTML('beforebegin', statusCardsHTML);
                        }
                    }
                }
                
                // Load bank accounts if not already loaded
                const bankAccountsList = document.getElementById('bankAccountsList');
                const bankAccountsTableBody = document.getElementById('bankAccountsTableBody');
                if ((bankAccountsList && (!bankAccountsList.innerHTML || bankAccountsList.innerHTML.includes('Loading'))) ||
                    (bankAccountsTableBody && (!bankAccountsTableBody.innerHTML || bankAccountsTableBody.innerHTML.includes('Loading')))) {
                    await this.loadBankAccounts();
                } else {
                    // Update status cards even if accounts are already loaded
                    this.updateBankingStatusCards();
                }
                
                // Setup handlers for existing modal
                this.setupBankingCashHandlers();
                
                // Set initial table container scrolling (no scroll for 5 entries)
                const tableContainer = modal.querySelector('#bankAccountsTableContainer');
                if (tableContainer) {
                    if (this.bankingPerPage <= 5) {
                        // No scrolling for 5 or fewer entries
                        tableContainer.classList.remove('modal-table-wrapper-scroll');
                        tableContainer.classList.add('modal-table-wrapper-no-scroll');
                        const table = tableContainer.querySelector('#bankAccountsTable');
                        if (table) {
                            table.classList.add('banking-table-no-scroll');
                        }
                        // Disable modal body scrolling
                        const modalBody = modal.querySelector('.accounting-modal-body');
                        if (modalBody) {
                            modalBody.classList.remove('banking-modal-scroll');
                            modalBody.classList.add('banking-modal-no-scroll');
                        }
                        const moduleContent = modal.querySelector('.accounting-module-modal-content');
                        if (moduleContent) {
                            moduleContent.classList.remove('banking-content-scroll');
                            moduleContent.classList.add('banking-content-no-scroll');
                        }
                    } else {
                        // Enable scrolling for more entries
                        tableContainer.classList.remove('modal-table-wrapper-no-scroll');
                        tableContainer.classList.add('modal-table-wrapper-scroll');
                        const table = tableContainer.querySelector('#bankAccountsTable');
                        if (table) {
                            table.classList.remove('banking-table-no-scroll');
                        }
                        // Keep modal body and content from scrolling (scrolling happens inside table only)
                        const modalBody = modal.querySelector('.accounting-modal-body');
                        if (modalBody) {
                            modalBody.classList.add('banking-modal-no-scroll');
                            modalBody.classList.remove('banking-modal-scroll');
                        }
                        const moduleContent = modal.querySelector('.accounting-module-modal-content');
                        if (moduleContent) {
                            moduleContent.classList.add('banking-content-no-scroll');
                            moduleContent.classList.remove('banking-content-scroll');
                        }
                    }
                }
            } else {
                // Create banking & cash modal if it doesn't exist
                const content = `
                    <div class="accounting-module-modal-content">
                        <div class="module-header">
                        </div>
                        <div class="module-content">
                            <!-- Status Cards -->
                            <div class="report-status-cards" id="bankingStatusCards">
                                <div class="stat-card stat-card-primary">
                                    <div class="stat-icon stat-icon-primary">
                                        <i class="fas fa-university"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-label">Total Accounts</div>
                                        <div class="stat-value" id="bankingTotalAccounts">0</div>
                                    </div>
                                </div>
                                <div class="stat-card stat-card-success">
                                    <div class="stat-icon stat-icon-success">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-label">Active Accounts</div>
                                        <div class="stat-value" id="bankingActiveAccounts">0</div>
                                    </div>
                                </div>
                                <div class="stat-card stat-card-warning">
                                    <div class="stat-icon stat-icon-warning">
                                        <i class="fas fa-times-circle"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-label">Inactive Accounts</div>
                                        <div class="stat-value" id="bankingInactiveAccounts">0</div>
                                    </div>
                                </div>
                                <div class="stat-card stat-card-balance">
                                    <div class="stat-icon stat-icon-info">
                                        <i class="fas fa-wallet"></i>
                                    </div>
                                    <div class="stat-info">
                                        <div class="stat-label">Total Balance</div>
                                        <div class="stat-value" id="bankingTotalBalance">SAR 0.00</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Filters and Search Bar -->
                            <div class="filters-and-pagination-container">
                                <div class="filters-bar">
                                    <div class="filter-group">
                                        <label><i class="fas fa-search"></i> Search:</label>
                                        <input type="text" id="bankingSearchInput" class="filter-input" placeholder="Search by bank name, account name, or number..." value="${this.bankingSearch || ''}">
                                    </div>
                                    <div class="filter-group">
                                        <label>Account Type:</label>
                                        <select id="bankingTypeFilter" class="filter-select">
                                            <option value="">All Types</option>
                                            <option value="Checking" ${this.bankingTypeFilter === 'Checking' ? 'selected' : ''}>Checking</option>
                                            <option value="Savings" ${this.bankingTypeFilter === 'Savings' ? 'selected' : ''}>Savings</option>
                                            <option value="Money Market" ${this.bankingTypeFilter === 'Money Market' ? 'selected' : ''}>Money Market</option>
                                            <option value="Certificate of Deposit" ${this.bankingTypeFilter === 'Certificate of Deposit' ? 'selected' : ''}>Certificate of Deposit</option>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label>Status:</label>
                                        <select id="bankingStatusFilter" class="filter-select">
                                            <option value="">All Status</option>
                                            <option value="active" ${this.bankingStatusFilter === 'active' ? 'selected' : ''}>Active</option>
                                            <option value="inactive" ${this.bankingStatusFilter === 'inactive' ? 'selected' : ''}>Inactive</option>
                                        </select>
                                    </div>
                                    <div class="filter-group">
                                        <label>Per Page:</label>
                                        <select id="bankingPerPageSelect" class="filter-select">
                                            <option value="5" ${this.bankingPerPage === 5 ? 'selected' : ''}>5</option>
                                            <option value="10" ${this.bankingPerPage === 10 ? 'selected' : ''}>10</option>
                                            <option value="25" ${this.bankingPerPage === 25 ? 'selected' : ''}>25</option>
                                            <option value="50" ${this.bankingPerPage === 50 ? 'selected' : ''}>50</option>
                                            <option value="100" ${this.bankingPerPage === 100 ? 'selected' : ''}>100</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary btn-sm" data-action="new-bank-account">
                                        <i class="fas fa-plus"></i> New Bank Account
                                    </button>
                                    <button class="btn btn-secondary btn-sm" data-action="refresh-banking">
                                        <i class="fas fa-sync"></i> Refresh
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Bulk Actions Bar -->
                            <div class="bulk-actions-bar" id="bankingBulkActions" style="display: none;">
                                <!-- Pagination Controls (moved to left of bulk buttons) -->
                                <div class="pagination-container" id="bankingPaginationContainer">
                                    <div class="pagination-info">
                                        Showing <span id="bankingPageStart">0</span> to <span id="bankingPageEnd">0</span> of <span id="bankingTotalCount">0</span> accounts
                                    </div>
                                    <div class="pagination-controls">
                                        <button class="btn btn-sm btn-secondary" id="bankingPrevPage" disabled>
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </button>
                                        <div class="page-numbers" id="bankingPageNumbers"></div>
                                        <button class="btn btn-sm btn-secondary" id="bankingNextPage" disabled>
                                            Next <i class="fas fa-chevron-right"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="bulk-actions-info">
                                    <span id="bankingSelectedCount">0</span> account(s) selected
                                </div>
                                <div class="bulk-actions-buttons">
                                    <button class="btn btn-sm btn-success" data-action="activate-selected-banks">
                                        <i class="fas fa-check-circle"></i> Activate
                                    </button>
                                    <button class="btn btn-sm btn-warning" data-action="inactivate-selected-banks">
                                        <i class="fas fa-times-circle"></i> Inactivate
                                    </button>
                                    <button class="btn btn-sm btn-info" data-action="export-selected-banks">
                                        <i class="fas fa-download"></i> Export
                                    </button>
                                    <button class="btn btn-sm btn-secondary" data-action="print-selected-banks">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                    <button class="btn btn-sm btn-danger" data-action="delete-selected-banks">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                            
                            <div class="data-table-container modal-table-wrapper modal-table-wrapper-no-scroll" id="bankAccountsTableContainer">
                                <table class="data-table modal-table-fixed banking-table-no-scroll" id="bankAccountsTable">
                                    <thead>
                                        <tr>
                                            <th class="sortable" data-sort="id">
                                                ID <i class="fas fa-sort"></i>
                                            </th>
                                            <th class="sortable" data-sort="bank_name">
                                                Bank Name <i class="fas fa-sort"></i>
                                            </th>
                                            <th class="sortable" data-sort="account_name">
                                                Account Name <i class="fas fa-sort"></i>
                                            </th>
                                            <th>Account Number</th>
                                            <th class="sortable" data-sort="account_type">
                                                Account Type <i class="fas fa-sort"></i>
                                            </th>
                                            <th class="amount-column sortable" data-sort="opening_balance">
                                                Opening Balance <i class="fas fa-sort"></i>
                                            </th>
                                            <th class="amount-column sortable" data-sort="current_balance">
                                                Current Balance <i class="fas fa-sort"></i>
                                            </th>
                                            <th class="sortable" data-sort="is_active">
                                                Status <i class="fas fa-sort"></i>
                                            </th>
                                            <th class="checkbox-column">
                                                <input type="checkbox" id="bankingSelectAll" title="Select All">
                                            </th>
                                            <th class="actions-column">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="bankAccountsTableBody">
                                        <tr>
                                            <td colspan="10" class="text-center">
                                                <i class="fas fa-spinner fa-spin"></i> Loading bank accounts...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                `;
                this.showModal('Banking & Cash', content, 'large', 'bankingCashModal');
                
                // Load bank accounts and setup handlers after modal is shown
                setTimeout(async () => {
                    await this.loadBankAccounts();
                    this.setupBankingCashHandlers();
                    
                    // Set initial table container scrolling (no scroll for 5 entries)
                    const modal = document.getElementById('bankingCashModal');
                    if (modal) {
                        const tableContainer = modal.querySelector('#bankAccountsTableContainer');
                        if (tableContainer) {
                            if (this.bankingPerPage <= 5) {
                                // No scrolling for 5 or fewer entries
                                tableContainer.classList.remove('modal-table-wrapper-scroll');
                                tableContainer.classList.add('modal-table-wrapper-no-scroll');
                                const table = tableContainer.querySelector('#bankAccountsTable');
                                if (table) {
                                    table.classList.add('banking-table-no-scroll');
                                }
                                // Disable modal body scrolling
                                const modalBody = modal.querySelector('.accounting-modal-body');
                                if (modalBody) {
                                    modalBody.classList.remove('banking-modal-scroll');
                                    modalBody.classList.add('banking-modal-no-scroll');
                                }
                                const moduleContent = modal.querySelector('.accounting-module-modal-content');
                                if (moduleContent) {
                                    moduleContent.classList.remove('banking-content-scroll');
                                    moduleContent.classList.add('banking-content-no-scroll');
                    }
                } else {
                                // Enable scrolling for more entries
                                tableContainer.classList.remove('modal-table-wrapper-no-scroll');
                                tableContainer.classList.add('modal-table-wrapper-scroll');
                                const table = tableContainer.querySelector('#bankAccountsTable');
                                if (table) {
                                    table.classList.remove('banking-table-no-scroll');
                                }
                                // Keep modal body and content from scrolling (scrolling happens inside table only)
                                const modalBody = modal.querySelector('.accounting-modal-body');
                                if (modalBody) {
                                    modalBody.classList.add('banking-modal-no-scroll');
                                    modalBody.classList.remove('banking-modal-scroll');
                                }
                                const moduleContent = modal.querySelector('.accounting-module-modal-content');
                                if (moduleContent) {
                                    moduleContent.classList.add('banking-content-no-scroll');
                                    moduleContent.classList.remove('banking-content-scroll');
                                }
                            }
                        }
                    }
                }, 100);
            }
        },

        renderBankTransactionsTable() {
            const tbody = document.getElementById('bankTransTableBody');
            if (!tbody) return;
            
            let filtered = [...this.bankTransData];
            
            // Apply search filter
            const searchTerm = (this.bankTransSearch || '').toLowerCase();
            if (searchTerm) {
                filtered = filtered.filter(t =>
                    (t.description && t.description.toLowerCase().includes(searchTerm)) ||
                    (t.reference_number && t.reference_number.toLowerCase().includes(searchTerm)) ||
                    (t.bank_account_name && t.bank_account_name.toLowerCase().includes(searchTerm))
                );
            }
            
            // Apply account filter
            const accountFilter = document.getElementById('bankTransAccountFilter')?.value;
            if (accountFilter) {
                filtered = filtered.filter(t => 
                    (t.bank_account_name || t.bank_name) === accountFilter
                );
            }
            
            // Apply type filter
            const typeFilter = document.getElementById('bankTransTypeFilter')?.value;
            if (typeFilter) {
                filtered = filtered.filter(t => t.transaction_type === typeFilter);
            }
            
            // Pagination
            const totalCount = filtered.length;
            const totalPages = Math.ceil(totalCount / this.bankTransPerPage);
            const start = (this.bankTransCurrentPage - 1) * this.bankTransPerPage;
            const end = start + this.bankTransPerPage;
            const paginated = filtered.slice(start, end);
            
            // Render table
            if (paginated.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No transactions found</td></tr>';
                } else {
                tbody.innerHTML = paginated.map(trans => `
                    <tr>
                        <td>${trans.transaction_date || 'N/A'}</td>
                        <td>${this.escapeHtml(trans.bank_account_name || trans.bank_name || 'N/A')}</td>
                        <td><span class="badge badge-${trans.transaction_type === 'deposit' ? 'success' : trans.transaction_type === 'withdrawal' ? 'danger' : 'info'}">${this.escapeHtml(trans.transaction_type || 'N/A')}</span></td>
                        <td>${this.escapeHtml(trans.description || 'N/A')}</td>
                        <td class="amount-column">${this.formatCurrency(trans.amount || 0, trans.currency || this.getDefaultCurrencySync())}</td>
                        <td>${this.escapeHtml(trans.reference_number || 'N/A')}</td>
                        <td class="actions-column">
                            <button class="btn btn-sm btn-info" data-action="view-bank-transaction" data-id="${trans.id}">
                                        <i class="fas fa-eye"></i>
                                    </button>
                            <button class="btn btn-sm btn-danger" data-action="delete-bank-transaction" data-id="${trans.id}">
                                        <i class="fas fa-trash"></i>
                                    </button>
                            </td>
                        </tr>
                `).join('');
            }
            
            // Update pagination
            const paginationInfo = document.getElementById('bankTransPaginationInfo');
            if (paginationInfo) {
                paginationInfo.textContent = `Showing ${start + 1}-${Math.min(end, totalCount)} of ${totalCount}`;
            }
            
            const pageNumbers = document.getElementById('bankTransPageNumbers');
            if (pageNumbers) {
                pageNumbers.textContent = `Page ${this.bankTransCurrentPage} of ${totalPages || 1}`;
            }
            
            const prevBtn = document.getElementById('bankTransPrevBtn');
            const nextBtn = document.getElementById('bankTransNextBtn');
            if (prevBtn) prevBtn.disabled = this.bankTransCurrentPage <= 1;
            if (nextBtn) nextBtn.disabled = this.bankTransCurrentPage >= totalPages;
        },

        setupBankTransactionHandlers() {
            const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
            if (!modal) return;
            
            // Search handler
            const searchInput = document.getElementById('bankTransSearch');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    this.bankTransSearch = e.target.value.trim();
                    searchTimeout = setTimeout(() => {
                        this.bankTransCurrentPage = 1;
                        this.renderBankTransactionsTable();
                    }, 300);
                });
            }
            
            // Filter handlers
            const accountFilter = document.getElementById('bankTransAccountFilter');
            const typeFilter = document.getElementById('bankTransTypeFilter');
            const applyFiltersBtn = document.getElementById('bankTransApplyFilters');
            
            if (accountFilter) {
                accountFilter.addEventListener('change', () => {
                    this.bankTransCurrentPage = 1;
                    this.renderBankTransactionsTable();
                });
            }
            
            if (typeFilter) {
                typeFilter.addEventListener('change', () => {
                    this.bankTransCurrentPage = 1;
                    this.renderBankTransactionsTable();
                });
            }
            
            if (applyFiltersBtn) {
                applyFiltersBtn.addEventListener('click', () => {
                    this.bankTransCurrentPage = 1;
                    this.renderBankTransactionsTable();
                });
            }
            
            // Pagination handlers
            const prevBtn = document.getElementById('bankTransPrevBtn');
            const nextBtn = document.getElementById('bankTransNextBtn');
            
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    if (this.bankTransCurrentPage > 1) {
                        this.bankTransCurrentPage--;
                        this.renderBankTransactionsTable();
                    }
                });
            }
            
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    const totalPages = Math.ceil((this.bankTransData?.length || 0) / this.bankTransPerPage);
                    if (this.bankTransCurrentPage < totalPages) {
                        this.bankTransCurrentPage++;
                        this.renderBankTransactionsTable();
                    }
                });
            }
            
            // Action handlers
            modal.querySelectorAll('[data-action="view-bank-transaction"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    this.viewBankTransaction(id);
                });
            });
            
            modal.querySelectorAll('[data-action="delete-bank-transaction"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    this.deleteBankTransaction(id);
                });
            });
            
            modal.querySelectorAll('[data-action="new-bank-transaction"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    this.openBankTransactionForm();
                });
            });
        },

        renderBankReconciliationsTable() {
            const tbody = document.getElementById('reconciliationTableBody');
            if (!tbody) return;
            
            let filtered = [...(this.reconciliationData || [])];
            
            // Apply account filter
            const accountFilter = document.getElementById('reconciliationAccountFilter')?.value;
            if (accountFilter) {
                filtered = filtered.filter(r => 
                    (r.bank_account_name || r.account_name) === accountFilter
                );
            }
            
            // Apply status filter
            const statusFilter = document.getElementById('reconciliationStatusFilter')?.value;
            if (statusFilter) {
                filtered = filtered.filter(r => r.status === statusFilter);
            }
            
            if (filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No reconciliations found</td></tr>';
                } else {
                tbody.innerHTML = filtered.map(rec => {
                    const difference = (rec.statement_balance || 0) - (rec.book_balance || 0);
                    const statusClass = rec.status === 'completed' ? 'success' : rec.status === 'in_progress' ? 'warning' : 'secondary';
                        return `
                        <tr>
                            <td>${rec.reconciliation_date || rec.date || 'N/A'}</td>
                            <td>${this.escapeHtml(rec.bank_account_name || rec.account_name || 'N/A')}</td>
                            <td class="amount-column">${this.formatCurrency(rec.statement_balance || 0, rec.currency || this.getDefaultCurrencySync())}</td>
                            <td class="amount-column">${this.formatCurrency(rec.book_balance || 0, rec.currency || this.getDefaultCurrencySync())}</td>
                            <td class="amount-column ${difference !== 0 ? 'text-danger' : ''}">${this.formatCurrency(difference, rec.currency || this.getDefaultCurrencySync())}</td>
                            <td><span class="badge badge-${statusClass}">${this.escapeHtml(rec.status || 'N/A')}</span></td>
                            <td class="actions-column">
                                <button class="btn btn-sm btn-info" data-action="view-reconciliation" data-id="${rec.id}">
                                    <i class="fas fa-eye"></i>
                                    </button>
                                ${rec.status !== 'completed' ? `
                                    <button class="btn btn-sm btn-success" data-action="complete-reconciliation" data-id="${rec.id}">
                                        <i class="fas fa-check"></i>
                                    </button>
                                ` : ''}
                                </td>
                            </tr>
                        `;
                    }).join('');
            }
        },

        setupBankReconciliationHandlers() {
            const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
            if (!modal) return;
            
            // Filter handlers
            const accountFilter = document.getElementById('reconciliationAccountFilter');
            const statusFilter = document.getElementById('reconciliationStatusFilter');
            const applyFiltersBtn = document.getElementById('reconciliationApplyFilters');
            
            if (accountFilter) {
                accountFilter.addEventListener('change', () => {
                    this.renderBankReconciliationsTable();
                });
            }
            
            if (statusFilter) {
                statusFilter.addEventListener('change', () => {
                    this.renderBankReconciliationsTable();
                });
            }
            
            if (applyFiltersBtn) {
                applyFiltersBtn.addEventListener('click', () => {
                    this.renderBankReconciliationsTable();
                });
            }
            
            // Action handlers
            modal.querySelectorAll('[data-action="view-reconciliation"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    this.viewReconciliation(id);
                });
            });
            
            modal.querySelectorAll('[data-action="complete-reconciliation"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    this.completeReconciliation(id);
                });
            });
            
            modal.querySelectorAll('[data-action="new-reconciliation"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    this.openReconciliationForm();
                });
            });
        },

        renderPeriodsTable() {
            const tbody = document.getElementById('periodsTableBody');
            if (!tbody) return;
            
            let filtered = [...(this.periodsData || [])];
            
            const searchTerm = (this.periodsSearch || '').toLowerCase();
            if (searchTerm) {
                filtered = filtered.filter(p =>
                    (p.period_name && p.period_name.toLowerCase().includes(searchTerm)) ||
                    (p.name && p.name.toLowerCase().includes(searchTerm))
                );
            }
            
            const statusFilter = document.getElementById('periodsStatusFilter')?.value;
            if (statusFilter) {
                filtered = filtered.filter(p => p.status === statusFilter);
            }
            
            const totalCount = filtered.length;
            const totalPages = Math.ceil(totalCount / this.periodsPerPage);
            const start = (this.periodsCurrentPage - 1) * this.periodsPerPage;
            const end = start + this.periodsPerPage;
            const paginated = filtered.slice(start, end);
            
            if (paginated.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No periods found</td></tr>';
                        } else {
                tbody.innerHTML = paginated.map(period => `
                    <tr>
                        <td>${this.escapeHtml(period.period_name || period.name || 'N/A')}</td>
                        <td>${period.start_date || 'N/A'}</td>
                        <td>${period.end_date || 'N/A'}</td>
                        <td><span class="badge badge-${period.status === 'closed' ? 'secondary' : 'success'}">${this.escapeHtml(period.status || 'open')}</span></td>
                        <td class="actions-column">
                            <button class="btn btn-sm btn-info" data-action="view-period" data-id="${period.id}">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${period.status !== 'closed' ? `
                                <button class="btn btn-sm btn-warning" data-action="close-period" data-id="${period.id}">
                                    <i class="fas fa-lock"></i>
                                </button>
                            ` : ''}
                        </td>
                    </tr>
                `).join('');
            }
            
            const paginationInfo = document.getElementById('periodsPaginationInfo');
            if (paginationInfo) {
                paginationInfo.textContent = `Showing ${start + 1}-${Math.min(end, totalCount)} of ${totalCount}`;
            }
            
            const pageNumbers = document.getElementById('periodsPageNumbers');
            if (pageNumbers) {
                pageNumbers.textContent = `Page ${this.periodsCurrentPage} of ${totalPages || 1}`;
            }
            
            const prevBtn = document.getElementById('periodsPrevBtn');
            const nextBtn = document.getElementById('periodsNextBtn');
            if (prevBtn) prevBtn.disabled = this.periodsCurrentPage <= 1;
            if (nextBtn) nextBtn.disabled = this.periodsCurrentPage >= totalPages;
        },

        setupPeriodsHandlers() {
            const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
            if (!modal) return;
            
            const searchInput = document.getElementById('periodsSearch');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    this.periodsSearch = e.target.value.trim();
                    searchTimeout = setTimeout(() => {
                        this.periodsCurrentPage = 1;
                        this.renderPeriodsTable();
                    }, 300);
                });
            }
            
            const statusFilter = document.getElementById('periodsStatusFilter');
            const applyFiltersBtn = document.getElementById('periodsApplyFilters');
            
            if (statusFilter) {
                statusFilter.addEventListener('change', () => {
                    this.periodsCurrentPage = 1;
                    this.renderPeriodsTable();
                });
            }
            
            if (applyFiltersBtn) {
                applyFiltersBtn.addEventListener('click', () => {
                    this.periodsCurrentPage = 1;
                    this.renderPeriodsTable();
                });
            }
            
            const prevBtn = document.getElementById('periodsPrevBtn');
            const nextBtn = document.getElementById('periodsNextBtn');
            
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    if (this.periodsCurrentPage > 1) {
                        this.periodsCurrentPage--;
                        this.renderPeriodsTable();
                    }
                });
            }
            
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    const totalPages = Math.ceil((this.periodsData?.length || 0) / this.periodsPerPage);
                    if (this.periodsCurrentPage < totalPages) {
                        this.periodsCurrentPage++;
                        this.renderPeriodsTable();
                        }
                    });
                }
            
            modal.querySelectorAll('[data-action="new-period"]').forEach(btn => {
                btn.addEventListener('click', () => this.openPeriodForm());
            });
        },

        renderTaxSettingsTable() {
            const tbody = document.getElementById('taxSettingsTableBody');
            if (!tbody) return;
            
            let filtered = [...(this.taxSettingsData || [])];
            
            const searchTerm = (this.taxSettingsSearch || '').toLowerCase();
            if (searchTerm) {
                filtered = filtered.filter(t =>
                    (t.tax_name && t.tax_name.toLowerCase().includes(searchTerm)) ||
                    (t.name && t.name.toLowerCase().includes(searchTerm))
                );
            }
            
            const typeFilter = document.getElementById('taxSettingsTypeFilter')?.value;
            if (typeFilter) {
                filtered = filtered.filter(t => t.tax_type === typeFilter);
            }
            
            const totalCount = filtered.length;
            const totalPages = Math.ceil(totalCount / this.taxSettingsPerPage);
            const start = (this.taxSettingsCurrentPage - 1) * this.taxSettingsPerPage;
            const end = start + this.taxSettingsPerPage;
            const paginated = filtered.slice(start, end);
            
            if (paginated.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No tax settings found</td></tr>';
                                    } else {
                tbody.innerHTML = paginated.map(tax => `
                    <tr>
                        <td>${this.escapeHtml(tax.tax_name || tax.name || 'N/A')}</td>
                        <td><span class="badge badge-info">${this.escapeHtml(tax.tax_type || tax.type || 'N/A')}</span></td>
                        <td>${tax.tax_rate || tax.rate || 0}%</td>
                        <td><span class="badge badge-${tax.status === 'active' ? 'success' : 'secondary'}">${this.escapeHtml(tax.status || 'inactive')}</span></td>
                        <td class="actions-column">
                            <button class="btn btn-sm btn-info" data-action="edit-tax-setting" data-id="${tax.id}">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
            }
            
            const paginationInfo = document.getElementById('taxSettingsPaginationInfo');
            if (paginationInfo) {
                paginationInfo.textContent = `Showing ${start + 1}-${Math.min(end, totalCount)} of ${totalCount}`;
            }
            
            const pageNumbers = document.getElementById('taxSettingsPageNumbers');
            if (pageNumbers) {
                pageNumbers.textContent = `Page ${this.taxSettingsCurrentPage} of ${totalPages || 1}`;
            }
            
            const prevBtn = document.getElementById('taxSettingsPrevBtn');
            const nextBtn = document.getElementById('taxSettingsNextBtn');
            if (prevBtn) prevBtn.disabled = this.taxSettingsCurrentPage <= 1;
            if (nextBtn) nextBtn.disabled = this.taxSettingsCurrentPage >= totalPages;
        },

        setupTaxSettingsHandlers() {
            const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
            if (!modal) return;
            
            const searchInput = document.getElementById('taxSettingsSearch');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    this.taxSettingsSearch = e.target.value.trim();
                    searchTimeout = setTimeout(() => {
                        this.taxSettingsCurrentPage = 1;
                        this.renderTaxSettingsTable();
                    }, 300);
                });
            }
            
            const typeFilter = document.getElementById('taxSettingsTypeFilter');
            const applyFiltersBtn = document.getElementById('taxSettingsApplyFilters');
            
            if (typeFilter) {
                typeFilter.addEventListener('change', () => {
                    this.taxSettingsCurrentPage = 1;
                    this.renderTaxSettingsTable();
                });
            }
            
            if (applyFiltersBtn) {
                applyFiltersBtn.addEventListener('click', () => {
                    this.taxSettingsCurrentPage = 1;
                    this.renderTaxSettingsTable();
                });
            }
            
            const prevBtn = document.getElementById('taxSettingsPrevBtn');
            const nextBtn = document.getElementById('taxSettingsNextBtn');
            
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    if (this.taxSettingsCurrentPage > 1) {
                        this.taxSettingsCurrentPage--;
                        this.renderTaxSettingsTable();
                            }
                        });
                    }
                    
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    const totalPages = Math.ceil((this.taxSettingsData?.length || 0) / this.taxSettingsPerPage);
                    if (this.taxSettingsCurrentPage < totalPages) {
                        this.taxSettingsCurrentPage++;
                        this.renderTaxSettingsTable();
                    }
                });
            }
            
            modal.querySelectorAll('[data-action="new-tax-setting"]').forEach(btn => {
                btn.addEventListener('click', () => this.openTaxSettingForm());
            });
            
            modal.querySelectorAll('[data-action="edit-tax-setting"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    this.openTaxSettingForm(id);
                });
            });
        },

        openPeriodForm(periodId = null) {
            const isEdit = periodId !== null;
            const formContent = `
                <form id="periodForm">
                <div class="accounting-modal-form-group">
                        <label for="periodName">Period Name <span class="required">*</span></label>
                        <input type="text" id="periodName" name="period_name" class="form-control" required placeholder="e.g., 2024 Fiscal Year">
                </div>
                <div class="accounting-modal-form-group">
                        <label for="periodStartDate">Start Date <span class="required">*</span></label>
                        <input type="text" id="periodStartDate" name="start_date" class="form-control date-input" required placeholder="MM/DD/YYYY">
                </div>
                <div class="accounting-modal-form-group">
                        <label for="periodEndDate">End Date <span class="required">*</span></label>
                        <input type="text" id="periodEndDate" name="end_date" class="form-control date-input" required placeholder="MM/DD/YYYY">
                    </div>
                    <div class="accounting-modal-form-group">
                        <label for="periodStatus">Status</label>
                        <select id="periodStatus" name="status" class="form-control">
                            <option value="open">Open</option>
                            <option value="closed">Closed</option>
                        </select>
                </div>
                <div class="accounting-modal-actions">
                        <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Period</button>
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                </div>
                </form>
            `;
            
            this.showModal(isEdit ? 'Edit Period' : 'New Period', formContent, 'normal', 'periodFormModal');
            
            setTimeout(() => {
                const form = document.getElementById('periodForm');
                if (form) {
                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        await this.savePeriod(periodId);
                    });
                }
            }, 100);
        },

        openTaxSettingForm(taxSettingId = null) {
            const isEdit = taxSettingId !== null;
            const formContent = `
                <form id="taxSettingForm">
                    <div class="accounting-modal-form-group">
                        <label for="taxName">Tax Name <span class="required">*</span></label>
                        <input type="text" id="taxName" name="tax_name" class="form-control" required placeholder="e.g., VAT, Sales Tax">
                                </div>
                    <div class="accounting-modal-form-group">
                        <label for="taxType">Tax Type <span class="required">*</span></label>
                        <select id="taxType" name="tax_type" class="form-control" required>
                            <option value="">Select Type</option>
                            <option value="sales">Sales Tax</option>
                            <option value="purchase">Purchase Tax</option>
                            <option value="vat">VAT</option>
                                    </select>
                                </div>
                    <div class="accounting-modal-form-group">
                        <label for="taxRate">Tax Rate (%) <span class="required">*</span></label>
                        <input type="number" id="taxRate" name="tax_rate" class="form-control" required step="0.01" min="0" max="100" placeholder="15.00">
                                </div>
                    <div class="accounting-modal-form-group">
                        <label for="taxStatus">Status</label>
                        <select id="taxStatus" name="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                    <div class="accounting-modal-actions">
                        <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Tax Setting</button>
                        <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                            </div>
                </form>
            `;
            
            this.showModal(isEdit ? 'Edit Tax Setting' : 'New Tax Setting', formContent, 'normal', 'taxSettingFormModal');
            
            setTimeout(() => {
                const form = document.getElementById('taxSettingForm');
                if (form) {
                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        await this.saveTaxSetting(taxSettingId);
                    });
                }
            }, 100);
        },

        async loadReceiptVoucherAccountOptions(modalOrForm = null) {
            const container = modalOrForm || document;
            const cashSelect = container.querySelector ? container.querySelector('#cash_account') : document.getElementById('cash_account');
            const collectedSelect = container.querySelector ? container.querySelector('#collected_from') : document.getElementById('collected_from');
            if (!cashSelect || !collectedSelect) return;
            cashSelect.innerHTML = '<option value="">Loading accounts...</option>';
            collectedSelect.innerHTML = '<option value="">Loading accounts...</option>';
            try {
                const [banksResponse, customersResponse, accountsResponse] = await Promise.all([
                    fetch(`${this.apiBase}/banks.php`).catch(() => ({ ok: false })),
                    fetch(`${this.apiBase}/customers.php`).catch(() => ({ ok: false })),
                    fetch(`${this.apiBase}/accounts.php?is_active=1&ensure_entity_accounts=1`).catch(() => ({ ok: false }))
                ]);
                const banksData = banksResponse.ok ? await banksResponse.json().catch(() => ({})) : {};
                const banks = (banksData.success && banksData.banks && Array.isArray(banksData.banks)) ? banksData.banks : [];
                const customersData = customersResponse.ok ? await customersResponse.json().catch(() => ({})) : {};
                const customers = (customersData.success && customersData.customers && Array.isArray(customersData.customers)) ? customersData.customers : [];
                let accountsData = accountsResponse.ok ? await accountsResponse.json().catch(() => ({})) : {};
                let accounts = (accountsData.success && accountsData.accounts && Array.isArray(accountsData.accounts)) ? accountsData.accounts : [];
                const byEntityFirst = (accs) => {
                    const o = { agent: [], subagent: [], worker: [], hr: [], other: [] };
                    (accs || []).forEach(a => { const et = (a.entity_type || '').toLowerCase(); if (et && o[et]) o[et].push(a); else o.other.push(a); });
                    return o;
                };
                if (accounts.length > 0 && (byEntityFirst(accounts).worker.length === 0 || byEntityFirst(accounts).hr.length === 0)) {
                    try {
                        const retryRes = await fetch(`${this.apiBase}/accounts.php?is_active=1&ensure_entity_accounts=1&_t=${Date.now()}`);
                        if (retryRes.ok) {
                            const retryData = await retryRes.json().catch(() => ({}));
                            if (retryData.success && Array.isArray(retryData.accounts) && retryData.accounts.length > 0) {
                                accounts = retryData.accounts;
                            }
                        }
                    } catch (_) {}
                }
                const populateSelect = (select, emptyVal, emptyText, includeCash) => {
                    select.innerHTML = '';
                    const addOpt = (val, text, disabled) => {
                        const o = document.createElement('option');
                        o.value = val !== undefined ? val : '';
                        o.textContent = text || '';
                        if (disabled) o.disabled = true;
                        select.appendChild(o);
                    };
                    addOpt(emptyVal, emptyText);
                    if (includeCash) addOpt('0', 'Cash');
                    if (banks.length > 0) {
                        addOpt('', '─── Bank Accounts ───', true);
                        banks.forEach(b => {
                            let t = b.account_name || b.bank_name || `Bank ${b.id}`;
                            if (b.account_number) t += ` (${b.account_number})`;
                            if (b.is_active === false || b.is_active === 0) t += ' [Inactive]';
                            addOpt(`bank_${b.id}`, t);
                        });
                    }
                    if (customers.length > 0) {
                        addOpt('', '─── Customers ───', true);
                        customers.forEach(c => {
                            let t = c.customer_name || `Customer ${c.id}`;
                            if (c.contact_person) t += ` - ${c.contact_person}`;
                            if (c.is_active === false || c.is_active === 0) t += ' [Inactive]';
                            addOpt(`customer_${c.id}`, t);
                        });
                    }
                    if (accounts.length > 0) {
                        const entityLabels = { agent: 'Agents', subagent: 'SubAgents', worker: 'Workers', hr: 'HR' };
                        const byEntity = { agent: [], subagent: [], worker: [], hr: [], other: [] };
                        accounts.forEach(a => {
                            const et = (a.entity_type || '').toLowerCase();
                            if (et && byEntity[et]) byEntity[et].push(a);
                            else byEntity.other.push(a);
                        });
                        ['agent', 'subagent', 'worker', 'hr'].forEach(et => {
                            addOpt('', `─── ${entityLabels[et]} ───`, true);
                            if (byEntity[et].length > 0) {
                                byEntity[et].forEach(a => {
                                    let t = a.account_code ? `${a.account_code} ${a.account_name || `Account ${a.id}`}` : (a.account_name || `Account ${a.id}`);
                                    if (a.account_type) t += ` (${a.account_type})`;
                                    if (a.is_active === false || a.is_active === 0) t += ' [Inactive]';
                                    addOpt(`gl_${a.id}`, t);
                                });
                            } else {
                                addOpt('', '— No accounts assigned yet —', true);
                            }
                        });
                        if (byEntity.other.length > 0) {
                            addOpt('', '─── General Ledger Accounts ───', true);
                            byEntity.other.forEach(a => {
                                let t = a.account_code ? `${a.account_code} ${a.account_name || `Account ${a.id}`}` : (a.account_name || `Account ${a.id}`);
                                if (a.account_type) t += ` (${a.account_type})`;
                                if (a.is_active === false || a.is_active === 0) t += ' [Inactive]';
                                addOpt(`gl_${a.id}`, t);
                            });
                        }
                    }
                };
                populateSelect(cashSelect, '', 'Select option', true);
                populateSelect(collectedSelect, '', 'Select option', true);
            } catch (error) {
                console.error('Error loading receipt voucher account options:', error);
                cashSelect.innerHTML = '<option value="">Error loading accounts</option>';
                collectedSelect.innerHTML = '<option value="">Error loading accounts</option>';
                const cashOpt = document.createElement('option');
                cashOpt.value = '0';
                cashOpt.textContent = 'Cash';
                cashSelect.appendChild(cashOpt);
            }
        },

        async loadCostCentersForReceiptVoucher(modalOrForm = null) {
            const container = modalOrForm || document;
            const select = container.querySelector ? container.querySelector('#receiptVoucherCostCenter') : document.getElementById('receiptVoucherCostCenter');
            if (!select) return;
            try {
                const response = await fetch(`${this.apiBase}/cost-centers.php`);
                const data = await response.json();
                if (data.success && data.cost_centers) {
                    select.innerHTML = '<option value="">Select option</option>';
                    data.cost_centers.forEach(cc => {
                        const option = document.createElement('option');
                        option.value = cc.id;
                        option.textContent = cc.code ? `${cc.code} - ${cc.name}` : cc.name;
                        select.appendChild(option);
                    });
                } else {
                    select.innerHTML = '<option value="">No cost centers found</option>';
                }
            } catch (error) {
                select.innerHTML = '<option value="">Error loading cost centers</option>';
            }
        },

        async loadReceiptVoucherData(receiptId, formEl = null) {
            const modal = document.getElementById('receiptVoucherModal');
            const form = formEl && formEl.closest && formEl.closest('#receiptVoucherModal') ? formEl : (modal ? modal.querySelector('#receiptVoucherForm') : null) || document.getElementById('receiptVoucherForm');
            if (!form) return;
            try {
                const response = await fetch(`${this.apiBase}/payment-receipts.php?id=${receiptId}`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const data = await response.json();
                if (data.success && data.receipt) {
                    const receipt = data.receipt;
                    this.applyReceiptDataToEditForm(receipt, modal);
                    const cashEl = form.querySelector('#cash_account');
                    const collectedEl = form.querySelector('#collected_from');
                    const bankVal = this._receiptBankSelectValue(receipt);
                    const customerVal = this._receiptCollectedSelectValue(receipt);
                    if (cashEl) this._setReceiptSelectValueAndTrigger(cashEl, bankVal, bankVal === '0' ? 'Cash' : (bankVal.startsWith('bank_') ? 'Bank' : 'GL'));
                    if (collectedEl) this._setReceiptSelectValueAndTrigger(collectedEl, customerVal, customerVal ? (customerVal.startsWith('customer_') ? 'Customer' : 'GL') : '');
                } else {
                    throw new Error(data.message || 'Failed to load receipt voucher');
                }
            } catch (error) {
                this.showToast(error.message || 'Failed to load receipt voucher', 'error');
            }
        },

        async saveReceiptVoucher(receiptId = null) {
            const form = document.getElementById('receiptVoucherForm');
            if (!form) return;
            try {
                const formData = new FormData(form);
                const data = Object.fromEntries(formData);
                data.amount = parseFloat(data.amount) || 0;
                if (data.amount <= 0 || isNaN(data.amount)) {
                    this.showToast('Amount must be greater than 0', 'error');
                    return;
                }
                const vatCheckbox = form.querySelector('#receiptVoucherVatCheckbox');
                data.vat_report = vatCheckbox && vatCheckbox.checked ? '1' : '0';
                if (!data.payment_method) data.payment_method = 'Cash';
                if (!data.customer_id || data.customer_id === '') {
                    data.customer_id = null;
                    data.collected_from_account_id = null;
                } else if (typeof data.customer_id === 'string' && data.customer_id.startsWith('customer_')) {
                    data.customer_id = parseInt(data.customer_id.replace('customer_', ''));
                    data.collected_from_account_id = null;
                } else if (typeof data.customer_id === 'string' && data.customer_id.startsWith('gl_')) {
                    data.collected_from_account_id = parseInt(data.customer_id.replace('gl_', ''));
                    data.customer_id = null;
                } else {
                    data.customer_id = parseInt(data.customer_id);
                    data.collected_from_account_id = null;
                }
                if (!data.bank_account_id || data.bank_account_id === '') {
                    data.bank_account_id = null;
                    data.account_id = null;
                } else if (data.bank_account_id === '0') {
                    data.bank_account_id = 0;
                    data.account_id = null;
                } else if (typeof data.bank_account_id === 'string' && data.bank_account_id.startsWith('bank_')) {
                    data.bank_account_id = parseInt(data.bank_account_id.replace('bank_', ''));
                    data.account_id = null;
                } else if (typeof data.bank_account_id === 'string' && data.bank_account_id.startsWith('gl_')) {
                    data.account_id = parseInt(data.bank_account_id.replace('gl_', ''));
                    data.bank_account_id = null;
                } else {
                    data.bank_account_id = parseInt(data.bank_account_id);
                    data.account_id = null;
                }
                if (!data.cost_center_id || data.cost_center_id === '') data.cost_center_id = null;
                if (!data.notes || data.notes.trim() === '') data.notes = null;
                if (data.notes && !data.description) data.description = data.notes;
                if (!data.receipt_number || data.receipt_number.trim() === '') delete data.receipt_number;
                const url = receiptId ? `${this.apiBase}/payment-receipts.php?id=${receiptId}` : `${this.apiBase}/payment-receipts.php`;
                const method = receiptId ? 'PUT' : 'POST';
                const response = await fetch(url, { method: method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                if (!response.ok) {
                    const errorText = await response.text();
                    let errorData;
                    try { errorData = JSON.parse(errorText); } catch (e) { errorData = { success: false, message: `HTTP ${response.status}` }; }
                    throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                if (result.success) {
                    this.showToast(receiptId ? 'Receipt voucher updated' : 'Receipt voucher saved', 'success');
                    this.closeModal(document.getElementById('receiptVoucherModal'));
                    this.loadReceiptVouchers();
                } else {
                    throw new Error(result.message || 'Failed to save receipt voucher');
                }
            } catch (error) {
                this.showToast(error.message || 'Failed to save receipt voucher', 'error');
            }
        },

        async loadReceiptVouchers() {
            const tbody = document.getElementById('receiptVouchersTableBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
            try {
                let url = `${this.apiBase}/payment-receipts.php`;
                const params = [];
                if (this.receiptVouchersYearFilter) {
                    if (this.receiptVouchersYearFilter === 'previous') {
                        const currentYear = new Date().getFullYear();
                        params.push(`date_from=1900-01-01&date_to=${currentYear - 1}-12-31`);
                    } else {
                        params.push(`year=${this.receiptVouchersYearFilter}`);
                    }
                }
                if (params.length > 0) {
                    url += '?' + params.join('&');
                }
                url += (url.includes('?') ? '&' : '?') + '_t=' + Date.now();
                const response = await fetch(url, { credentials: 'include' });
                if (!response.ok) {
                    let errorMessage = `HTTP error! status: ${response.status}`;
                    try {
                        const errorData = await response.text();
                        if (errorData) {
                            try {
                                const errorJson = JSON.parse(errorData);
                                if (errorJson.message) {
                                    errorMessage = errorJson.message;
                                }
                            } catch (e) {
                                // If not JSON, use the raw text (might be PHP error)
                                if (errorData.length < 500) {
                                    errorMessage = errorData;
                                }
                            }
                        }
                    } catch (e) {
                        // Keep default error message
                    }
                    throw new Error(errorMessage);
                }
                const data = await response.json();
                if (data.success && data.receipts && Array.isArray(data.receipts)) {
                    let receipts = data.receipts;
                    // Apply search filter
                    if (this.receiptVouchersSearch) {
                        const searchLower = this.receiptVouchersSearch.toLowerCase();
                        receipts = receipts.filter(r =>
                            ((r.receipt_number || r.voucher_number) && String(r.receipt_number || r.voucher_number).toLowerCase().includes(searchLower)) ||
                            (r.customer_name && r.customer_name.toLowerCase().includes(searchLower)) ||
                            (r.bank_account_name && r.bank_account_name.toLowerCase().includes(searchLower)) ||
                            (r.description && r.description.toLowerCase().includes(searchLower))
                        );
                    }
                    // Apply sorting
                    if (this.receiptVouchersSortField) {
                        receipts.sort((a, b) => {
                            let aVal = a[this.receiptVouchersSortField] || '';
                            let bVal = b[this.receiptVouchersSortField] || '';
                            
                            if (this.receiptVouchersSortField === 'amount') {
                                aVal = parseFloat(aVal) || 0;
                                bVal = parseFloat(bVal) || 0;
                            } else if (this.receiptVouchersSortField === 'payment_date') {
                                aVal = new Date(aVal);
                                bVal = new Date(bVal);
                            } else {
                                aVal = String(aVal).toLowerCase();
                                bVal = String(bVal).toLowerCase();
                            }
                            if (this.receiptVouchersSortDir === 'asc') {
                                return aVal > bVal ? 1 : aVal < bVal ? -1 : 0;
                            } else {
                                return aVal < bVal ? 1 : aVal > bVal ? -1 : 0;
                            }
                        });
                    }
                    // Pagination
                    const totalRecords = receipts.length;
                    const totalPages = Math.ceil(totalRecords / this.receiptVouchersPerPage);
                    const startIndex = (this.receiptVouchersCurrentPage - 1) * this.receiptVouchersPerPage;
                    const endIndex = startIndex + this.receiptVouchersPerPage;
                    const paginatedReceipts = receipts.slice(startIndex, endIndex);
                    // Render table
                    if (paginatedReceipts.length > 0) {
                        tbody.innerHTML = paginatedReceipts.map(receipt => {
                            const voucherNumber = this.escapeHtml(receipt.receipt_number || receipt.voucher_number || '');
                            const date = receipt.payment_date ? this.formatDate(receipt.payment_date) : '-';
                            const description = this.escapeHtml(receipt.description || receipt.notes || '');
                            const customerName = this.escapeHtml(receipt.customer_name || 'N/A');
                            const bankAccountName = this.escapeHtml(receipt.bank_account_name || 'Cash');
                            const amount = this.formatCurrency(parseFloat(receipt.amount || 0), receipt.currency || 'SAR');
                            const costCenter = this.escapeHtml(receipt.cost_center_name || '');
                            return `
                                <tr>
                                    <td>${voucherNumber}</td>
                                    <td>${date}</td>
                                    <td>${description}</td>
                                    <td>${customerName}</td>
                                    <td>${bankAccountName}</td>
                                    <td class="text-right">${amount}</td>
                                    <td>${costCenter}</td>
                                    <td>
                                        <div class="action-buttons">
                                            <input type="checkbox" class="row-checkbox" data-id="${receipt.id}">
                                            <button class="action-btn view" data-action="view-receipt-voucher" data-id="${receipt.id}" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="action-btn edit" data-action="edit-receipt-voucher" data-id="${receipt.id}" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }).join('');
                        // Update table wrapper scrolling
                        const tableWrapper = document.getElementById('receiptVouchersTableWrapper');
                        if (tableWrapper) {
                            tableWrapper.setAttribute('data-per-page', this.receiptVouchersPerPage.toString());
                            if (this.receiptVouchersPerPage > 5) {
                                tableWrapper.classList.remove('modal-table-wrapper-no-scroll');
                                tableWrapper.classList.add('modal-table-wrapper-scroll');
                            } else {
                                tableWrapper.classList.remove('modal-table-wrapper-scroll');
                                tableWrapper.classList.add('modal-table-wrapper-no-scroll');
                            }
                        }
                        // Update pagination
                        this.updateReceiptVouchersPagination(totalRecords, this.receiptVouchersCurrentPage, totalPages);
                    } else {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center ledger-empty-state"><i class="fas fa-receipt ledger-empty-icon"></i><p class="ledger-empty-message">No receipt vouchers found</p><p class="text-muted small">Filter: Year ' + (this.receiptVouchersYearFilter || 'All') + '. Try &quot;All Years&quot; or click Refresh.</p></td></tr>';
                        this.updateReceiptVouchersPagination(0, 1, 1);
                    }
                } else {
                    const errMsg = (data && data.message) ? data.message : 'API did not return receipts list';
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">' + this.escapeHtml(errMsg) + '</td></tr>';
                    this.updateReceiptVouchersPagination(0, 1, 1);
                }
            } catch (error) {
                console.error('Error loading receipt vouchers:', error);
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error loading receipt vouchers: ${error.message}</td></tr>`;
                this.showToast('Failed to load receipt vouchers', 'error');
            }
        },

        async loadEntryApprovalData(id) {
            try {
                const response = await fetch(`${this.apiBase}/entry-approval.php?id=${id}`, {
                    credentials: 'include'
                });
                
                const data = await response.json();
                
                if (!data.success || !data.entry) {
                    this.showToast('Entry not found', 'error');
                    return;
                }
                
                const entry = data.entry;
                
                // Get debit and credit amounts, prioritizing direct values
                const debitAmount = entry.debit_amount || entry.total_debit || 0;
                const creditAmount = entry.credit_amount || entry.total_credit || 0;
                const currencyValue = entry.currency || this.getDefaultCurrencySync();
                
                const formContent = `
                    <form id="entryApprovalForm">
                        <input type="hidden" id="entryApprovalId" value="${entry.id}">
                        <div class="accounting-modal-form-group">
                            <label>Entry Number <span class="required">*</span></label>
                            <input type="text" id="entryApprovalNumber" value="${this.escapeHtml(entry.entry_number)}" class="form-control" required>
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Entry Date <span class="required">*</span></label>
                            <input type="text" id="entryApprovalDate" value="${entry.entry_date}" class="form-control date-input" required placeholder="MM/DD/YYYY">
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Description</label>
                            <textarea id="entryApprovalDescription" class="form-control" rows="3">${this.escapeHtml(entry.description || '')}</textarea>
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Debit</label>
                            <input type="number" id="entryApprovalDebit" value="${debitAmount}" step="0.01" min="0" class="form-control">
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Credit</label>
                            <input type="number" id="entryApprovalCredit" value="${creditAmount}" step="0.01" min="0" class="form-control">
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Currency</label>
                            <select id="entryApprovalCurrency" class="form-control">
                                <option value="">Loading currencies...</option>
                            </select>
                        </div>
                        <div class="accounting-modal-actions">
                            <button type="submit" class="btn btn-primary">Update Entry</button>
                            <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                        </div>
                    </form>
                `;
                
                this.showModal('Edit Entry Approval', formContent, 'normal', 'entryApprovalFormModal');
                
                // Populate currency dropdown
                setTimeout(async () => {
                    const currencySelect = document.getElementById('entryApprovalCurrency');
                    if (currencySelect && window.currencyUtils) {
                        try {
                            let currency = currencyValue;
                            if (currency && currency.includes(' - ')) {
                                currency = currency.split(' - ')[0].trim();
                            }
                            await window.currencyUtils.populateCurrencySelect(currencySelect, currency);
                        } catch (error) {
                            // Error populating currency - continue with default
                        }
                    }
                }, 200);
                
                // Setup form submit handler - attach directly to form and button
                setTimeout(() => {
                    const modal = document.getElementById('entryApprovalFormModal');
                    const form = document.getElementById('entryApprovalForm');
                    
                    if (!form || !modal) {
                        console.error('Form or modal not found');
                        return;
                    }
                    
                    // Store entry ID on the form for reference
                    form.dataset.entryId = id;
                    
                    // Create submit handler
                    const formSubmitHandler = async (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const formId = form.dataset.entryId || id;
                        await this.saveEntryApproval(formId);
                        return false;
                    };
                    
                    // Remove any existing listeners by cloning the form
                    const newForm = form.cloneNode(true);
                    form.parentNode.replaceChild(newForm, form);
                    newForm.dataset.entryId = id;
                    
                    // Attach submit handler to form
                    newForm.addEventListener('submit', formSubmitHandler, { capture: true });
                    
                    // Attach click handler to submit button with high priority
                    const updateBtn = newForm.querySelector('button[type="submit"]');
                    if (updateBtn) {
                        updateBtn.addEventListener('click', async (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            const formId = newForm.dataset.entryId || id;
                            await this.saveEntryApproval(formId);
                            return false;
                        }, { capture: true });
                        
                        // Also add as onclick as last resort
                        updateBtn.onclick = async (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            const formId = newForm.dataset.entryId || id;
                            await this.saveEntryApproval(formId);
                            return false;
                        };
                    }
                }, 500);
            } catch (error) {
                this.showToast('Error loading entry data: ' + error.message, 'error');
            }
        },

        async loadBankGuaranteeData(id) {
            try {
                const response = await fetch(`${this.apiBase}/bank-guarantees.php?id=${id}`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success && data.bank_guarantee) {
                    const bg = data.bank_guarantee;
                    document.getElementById('bgReferenceNumber').value = bg.reference_number;
                    document.getElementById('bgBankName').value = bg.bank_name;
                    document.getElementById('bgAmount').value = bg.amount;
                    // Populate currency dropdown before setting value
                    const currencySelect = document.getElementById('bgCurrency');
                    if (currencySelect && window.currencyUtils) {
                        await window.currencyUtils.populateCurrencySelect(currencySelect, bg.currency || this.getDefaultCurrencySync());
                    } else if (currencySelect) {
                        currencySelect.value = bg.currency || this.getDefaultCurrencySync();
                    }
                    document.getElementById('bgIssueDate').value = bg.issue_date;
                    document.getElementById('bgExpiryDate').value = bg.expiry_date || '';
                    document.getElementById('bgStatus').value = bg.status;
                    document.getElementById('bgDescription').value = bg.description || '';
                }
            } catch (error) {
                this.showToast('Failed to load bank guarantee data', 'error');
            }
        },

        exportTransactions() {
            try {
                const tbody = document.getElementById('entityTransactionsBody');
                if (!tbody) {
                    this.showToast('No transactions to export', 'warning');
                    return;
                }
                const rows = tbody.querySelectorAll('tr[data-id]');
                if (rows.length === 0) {
                    this.showToast('No transactions to export', 'warning');
                    return;
                }
                const transactions = Array.from(rows).map(row => {
                    const id = row.getAttribute('data-id');
                    const cells = row.querySelectorAll('td');
                    return {
                        id: id,
                        date: cells[1]?.textContent?.trim() || '',
                        entity: cells[2]?.textContent?.trim() || '',
                        type: cells[3]?.textContent?.trim() || '',
                        reference: cells[4]?.textContent?.trim() || '',
                        description: cells[5]?.textContent?.trim() || '',
                        debit: cells[6]?.textContent?.trim() || '',
                        credit: cells[7]?.textContent?.trim() || '',
                        status: cells[8]?.textContent?.trim() || ''
                    };
                });
                const jsonData = JSON.stringify(transactions, null, 2);
                const blob = new Blob([jsonData], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `transactions-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                this.showToast(`${transactions.length} transaction(s) exported successfully`, 'success');
            } catch (error) {
                this.showToast('Error exporting transactions: ' + error.message, 'error');
            }
        },

        async openReceiptVouchersModal() {
            // Initialize pagination state
            if (typeof this.receiptVouchersCurrentPage === 'undefined') {
                this.receiptVouchersCurrentPage = 1;
            }
            if (typeof this.receiptVouchersPerPage === 'undefined') {
                this.receiptVouchersPerPage = 10;
            }
            // Default to "All Years" so list shows all receipts; user can filter by year after
            if (typeof this.receiptVouchersYearFilter === 'undefined') {
                this.receiptVouchersYearFilter = '';
            }
            if (typeof this.receiptVouchersSearch === 'undefined') {
                this.receiptVouchersSearch = '';
            }
            const currentYear = new Date().getFullYear();
            const years = [];
            for (let y = currentYear; y >= currentYear - 10; y--) {
                years.push(y);
            }
            const content = `
                <div class="accounting-modal-content">
                    <div class="modal-header-actions">
                        <button class="btn btn-primary" id="addReceiptVoucherBtn" data-action="add-receipt-voucher">
                            <i class="fas fa-plus"></i> Add Receipt Voucher
                        </button>
                    </div>
                    <div class="accounting-filters-row">
                        <div class="accounting-filter-group">
                            <label>Filter by Year:</label>
                            <select id="receiptVouchersYearFilter" class="form-control">
                                <option value="" ${this.receiptVouchersYearFilter === '' || this.receiptVouchersYearFilter === null ? 'selected' : ''}>All Years</option>
                                <option value="previous">Previous Years</option>
                                ${years.map(y => `<option value="${y}" ${y === this.receiptVouchersYearFilter ? 'selected' : ''}>${y}</option>`).join('')}
                            </select>
                        </div>
                        <div class="accounting-filter-group">
                            <label>Display:</label>
                            <select id="receiptVouchersPerPageSelect" class="form-control">
                                <option value="10" ${this.receiptVouchersPerPage === 10 ? 'selected' : ''}>10</option>
                                <option value="25" ${this.receiptVouchersPerPage === 25 ? 'selected' : ''}>25</option>
                                <option value="50" ${this.receiptVouchersPerPage === 50 ? 'selected' : ''}>50</option>
                                <option value="100" ${this.receiptVouchersPerPage === 100 ? 'selected' : ''}>100</option>
                            </select>
                            <span>Records</span>
                        </div>
                        <div class="accounting-filter-group">
                            <label>Search:</label>
                            <input type="text" id="receiptVouchersSearch" class="form-control" placeholder="Search..." value="${this.receiptVouchersSearch || ''}">
                        </div>
                        <div class="accounting-filter-group export-buttons-group">
                            <button class="btn btn-sm btn-export" data-action="print-receipt-vouchers" title="Print">
                                <i class="fas fa-print"></i>
                            </button>
                            <button class="btn btn-sm btn-export" data-action="pdf-receipt-vouchers" title="PDF">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                            <button class="btn btn-sm btn-export" data-action="csv-receipt-vouchers" title="CSV">
                                <i class="fas fa-file-csv"></i>
                            </button>
                            <button class="btn btn-sm btn-export" data-action="copy-receipt-vouchers" title="Copy">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button class="btn btn-sm btn-export active" data-action="excel-receipt-vouchers" title="Excel">
                                <i class="fas fa-file-excel"></i>
                            </button>
                        </div>
                    </div>
                    <div class="data-table-container modal-table-wrapper" id="receiptVouchersTableWrapper">
                        <table class="data-table modal-table-fixed" id="receiptVouchersTable">
                            <thead>
                                <tr>
                                    <th class="sortable" data-sort="receipt_number">
                                        Voucher Number <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="sortable" data-sort="payment_date">
                                        Date <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="sortable" data-sort="description">
                                        Description <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="sortable" data-sort="customer_name">
                                        Payee / Customer Name <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="sortable" data-sort="bank_account_name">
                                        Bank / Cash Account Name <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="sortable text-right" data-sort="amount">
                                        Amount <i class="fas fa-sort"></i>
                                    </th>
                                    <th class="sortable" data-sort="cost_center_name">
                                        Cost Center <i class="fas fa-sort"></i>
                                    </th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="receiptVouchersTableBody">
                                <tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-pagination" id="receiptVouchersPagination">
                        <!-- Pagination will be inserted here -->
                    </div>
                </div>
            `;
            this.showModal('Receipt Vouchers', content, 'large', 'receiptVouchersListModal');
            // Setup event listeners
            setTimeout(() => {
                const yearFilter = document.getElementById('receiptVouchersYearFilter');
                const perPageSelect = document.getElementById('receiptVouchersPerPageSelect');
                const searchInput = document.getElementById('receiptVouchersSearch');
                const addBtn = document.getElementById('addReceiptVoucherBtn');
                if (yearFilter) {
                    yearFilter.addEventListener('change', (e) => {
                        this.receiptVouchersYearFilter = e.target.value === 'previous' ? 'previous' : (e.target.value ? parseInt(e.target.value) : null);
                        this.receiptVouchersCurrentPage = 1;
                        this.loadReceiptVouchers();
                    });
                }
                if (perPageSelect) {
                    perPageSelect.addEventListener('change', (e) => {
                        this.receiptVouchersPerPage = parseInt(e.target.value);
                        this.receiptVouchersCurrentPage = 1;
                        this.loadReceiptVouchers();
                    });
                }
                if (searchInput) {
                    let searchTimeout;
                    searchInput.addEventListener('input', (e) => {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => {
                            this.receiptVouchersSearch = e.target.value;
                            this.receiptVouchersCurrentPage = 1;
                            this.loadReceiptVouchers();
                        }, 300);
                    });
                }
                if (addBtn) {
                    addBtn.addEventListener('click', () => {
                        this.openReceiptVoucherModal();
                    });
                }
                // View/Edit action buttons: delegate on table wrapper so it works after tbody re-renders
                const tableWrapper = document.getElementById('receiptVouchersTableWrapper');
                if (tableWrapper) {
                    tableWrapper.addEventListener('click', (e) => {
                        const btn = e.target.closest('[data-action="view-receipt-voucher"], [data-action="edit-receipt-voucher"]');
                        if (btn) {
                            e.preventDefault();
                            e.stopPropagation();
                            const id = btn.getAttribute('data-id');
                            if (id) this.openReceiptVoucherModal(parseInt(id, 10));
                        }
                    });
                }
                // Export buttons
                document.querySelectorAll('[data-action^="print-"], [data-action^="pdf-"], [data-action^="csv-"], [data-action^="copy-"], [data-action^="excel-"]').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const action = e.currentTarget.getAttribute('data-action');
                        this.exportReceiptVouchers(action.replace('receipt-vouchers', '').replace('-', ''));
                    });
                });
                // Sortable headers
                document.querySelectorAll('#receiptVouchersTable th.sortable').forEach(th => {
                    th.addEventListener('click', () => {
                        const sortField = th.getAttribute('data-sort');
                        // Toggle sort direction
                        this.receiptVouchersSortField = sortField;
                        this.receiptVouchersSortDir = (this.receiptVouchersSortField === sortField && this.receiptVouchersSortDir === 'asc') ? 'desc' : 'asc';
                        this.loadReceiptVouchers();
                    });
                });
                // Load data
                this.loadReceiptVouchers();
            }, 100);
        },

        async loadBankGuarantees() {
            const tbodyEl = document.getElementById('bankGuaranteeTableBody');
            if (!tbodyEl) {
                console.error('bankGuaranteeTableBody not found');
                return;
            }

            try {
                tbodyEl.innerHTML = '<tr><td colspan="8" class="text-center"><div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading bank guarantees...</p></div></td></tr>';
                
                // Build URL with date filters if available
                const params = new URLSearchParams();
                const dateFrom = document.getElementById('bankGuaranteeDateFrom')?.value;
                const dateTo = document.getElementById('bankGuaranteeDateTo')?.value;
                if (dateFrom) {
                    params.append('date_from', this.formatDateForAPI(dateFrom));
                }
                if (dateTo) {
                    params.append('date_to', this.formatDateForAPI(dateTo));
                }
                
                const url = params.toString() 
                    ? `${this.apiBase}/bank-guarantees.php?${params.toString()}`
                    : `${this.apiBase}/bank-guarantees.php`;
                
                const response = await fetch(url, {
                    credentials: 'include'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load bank guarantees');
                }

                // Store all data
                this.bankGuaranteeData = data.bank_guarantees || [];
                
                // Render table with pagination and filters
                this.renderBankGuaranteeTable();
            } catch (error) {
                console.error('Error loading bank guarantees:', error);
                tbodyEl.innerHTML = `<tr><td colspan="8" class="text-center"><div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading bank guarantees</p><p class="text-muted">${error.message}</p></div></td></tr>`;
                this.showToast(`Failed to load bank guarantees: ${error.message}`, 'error');
            }
        },

        async loadCostCenters() {
            const tbodyEl = document.getElementById('costCentersTableBody');
            if (!tbodyEl) {
                console.error('costCentersTableBody not found');
                return;
            }

            try {
                tbodyEl.innerHTML = '<tr><td colspan="6" class="text-center"><div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading cost centers...</p></div></td></tr>';
                
                const response = await fetch(`${this.apiBase}/cost-centers.php`, {
                    credentials: 'include'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load cost centers');
                }

                // Store all data
                this.costCentersData = data.cost_centers || [];
                
                // Render table with pagination and filters
                this.renderCostCentersTable();
            } catch (error) {
                console.error('Error loading cost centers:', error);
                tbodyEl.innerHTML = `<tr><td colspan="6" class="text-center"><div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading cost centers</p><p class="text-muted">${error.message}</p></div></td></tr>`;
                this.showToast(`Failed to load cost centers: ${error.message}`, 'error');
            }
        },

        getPaymentVoucherModalContent(voucherId = null) {
            const isEdit = !!voucherId;
            const today = this.formatDateForInput ? this.formatDateForInput(new Date().toISOString().split('T')[0]) : new Date().toISOString().split('T')[0];
            return `
            <form id="paymentVoucherForm" data-voucher-id="${voucherId || 'null'}">
                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Voucher Number</label>
                        <input type="text" name="voucher_number" id="paymentVoucherNumber" readonly placeholder="Auto-generated">
                        <small class="form-text">Auto-generated</small>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Voucher Date *</label>
                        <div class="date-input-wrapper">
                            <input type="text" name="voucher_date" id="paymentVoucherDate" class="date-input" required value="${today}" placeholder="MM/DD/YYYY">
                            <i class="fas fa-calendar-alt date-icon"></i>
                        </div>
                    </div>
                </div>
                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Cash / Bank Account *</label>
                        <select name="bank_account_id" id="paymentVoucherCashAccount" required>
                            <option value="">Loading bank accounts...</option>
                        </select>
                        <small class="form-text" id="paymentVoucherBankHelpText" style="display: none;"></small>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Payee / Expense Account *</label>
                        <select name="vendor_id" id="paymentVoucherPayee" required>
                            <option value="">Loading vendors & accounts...</option>
                        </select>
                        <small class="form-text" id="paymentVoucherPayeeHelpText" style="display: none;"></small>
                    </div>
                </div>
                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Payment Method *</label>
                        <select name="payment_method" required>
                            <option value="Cash" selected>Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Amount *</label>
                        <input type="number" name="amount" step="0.01" min="0" required placeholder="Amount">
                    </div>
                </div>
                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Cost Center</label>
                        <select name="cost_center_id" id="paymentVoucherCostCenter">
                            <option value="">Select option</option>
                        </select>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Currency</label>
                        <select name="currency" id="paymentVoucherCurrency">
                            <option value="">Loading currencies...</option>
                        </select>
                    </div>
                </div>
                <div class="accounting-modal-form-row">
                    <div class="accounting-modal-form-group">
                        <label>Status</label>
                        <select name="status" id="paymentVoucherStatus">
                            <option value="Draft" selected>Draft</option>
                            <option value="Posted">Posted</option>
                            <option value="Cancelled">Cancelled</option>
                            <option value="Reversed">Reversed</option>
                        </select>
                        <small class="form-text">Draft = not finalized; Posted = recorded/cleared</small>
                    </div>
                </div>
                <div class="accounting-modal-form-group full-width">
                    <label>Description</label>
                    <textarea name="notes" rows="3" placeholder="Description"></textarea>
                </div>
                <div class="accounting-modal-actions">
                    <button type="button" class="btn btn-secondary" id="paymentVoucherCancelBtn" data-action="close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="paymentVoucherSaveBtn">
                        <i class="fas fa-save"></i> ${isEdit ? 'Update' : 'Save'}
                    </button>
                </div>
            </form>
            `;
        },

        async loadPaymentVoucherAccountOptions(modalOrForm = null) {
            const container = modalOrForm || document;
            const cashSelect = container.querySelector('#paymentVoucherCashAccount');
            const payeeSelect = container.querySelector('#paymentVoucherPayee');
            if (!cashSelect || !payeeSelect) return;
            cashSelect.innerHTML = '<option value="">Loading...</option>';
            payeeSelect.innerHTML = '<option value="">Loading...</option>';
            try {
                const [banksRes, vendorsRes, accountsRes] = await Promise.all([
                    fetch(`${this.apiBase}/banks.php`, { credentials: 'include' }).catch(() => ({ ok: false })),
                    fetch(`${this.apiBase}/vendors.php`, { credentials: 'include' }).catch(() => ({ ok: false })),
                    fetch(`${this.apiBase}/accounts.php?is_active=1&ensure_entity_accounts=1`, { credentials: 'include' }).catch(() => ({ ok: false }))
                ]);
                const banksData = banksRes.ok ? await banksRes.json().catch(() => ({})) : {};
                const banks = (banksData.success && banksData.banks) ? banksData.banks : [];
                const vendorsData = vendorsRes.ok ? await vendorsRes.json().catch(() => ({})) : {};
                const vendors = (vendorsData.success && vendorsData.vendors) ? vendorsData.vendors : [];
                const accountsData = accountsRes.ok ? await accountsRes.json().catch(() => ({})) : {};
                const accounts = (accountsData.success && accountsData.accounts) ? accountsData.accounts : [];
                cashSelect.innerHTML = '<option value="">Select Cash/Bank</option><option value="0">Cash</option>';
                banks.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = `bank_${b.id}`;
                    opt.textContent = (b.account_name || b.bank_name || `Bank ${b.id}`) + (b.account_number ? ` (${b.account_number})` : '');
                    cashSelect.appendChild(opt);
                });
                if (accounts.length) {
                    const cashGrp = document.createElement('optgroup');
                    cashGrp.label = 'GL Accounts (Cash/Bank/Asset)';
                    accounts.filter(a => {
                        const t = (a.account_type || '').toLowerCase();
                        return t.includes('asset') || t.includes('cash') || t.includes('bank') || !t;
                    }).forEach(a => {
                        const opt = document.createElement('option');
                        opt.value = `gl_${a.id}`;
                        opt.textContent = (a.account_code ? `${a.account_code} ` : '') + (a.account_name || `Account ${a.id}`);
                        cashGrp.appendChild(opt);
                    });
                    if (cashGrp.children.length) cashSelect.appendChild(cashGrp);
                }
                payeeSelect.innerHTML = '<option value="">Select Payee / Expense Account</option>';
                if (vendors.length) {
                    const grp = document.createElement('optgroup');
                    grp.label = 'Vendors';
                    vendors.forEach(v => {
                        const opt = document.createElement('option');
                        opt.value = `vendor_${v.id}`;
                        opt.textContent = v.vendor_name || `Vendor ${v.id}`;
                        grp.appendChild(opt);
                    });
                    payeeSelect.appendChild(grp);
                }
                if (accounts.length) {
                    const grp = document.createElement('optgroup');
                    grp.label = 'All GL Accounts';
                    accounts.forEach(a => {
                        const opt = document.createElement('option');
                        opt.value = `gl_${a.id}`;
                        opt.textContent = (a.account_code ? `${a.account_code} ` : '') + (a.account_name || `Account ${a.id}`);
                        grp.appendChild(opt);
                    });
                    payeeSelect.appendChild(grp);
                }
            } catch (e) {
                console.error('Error loading payment voucher options:', e);
                cashSelect.innerHTML = '<option value="">Error loading</option>';
                payeeSelect.innerHTML = '<option value="">Error loading</option>';
            }
        },

        async loadCostCentersForPaymentVoucher(modalOrForm = null) {
            const container = modalOrForm || document;
            const select = container.querySelector('#paymentVoucherCostCenter');
            if (!select) return;
            try {
                const response = await fetch(`${this.apiBase}/cost-centers.php`, { credentials: 'include' });
                const data = await response.json();
                if (data.success && data.cost_centers) {
                    select.innerHTML = '<option value="">Select option</option>';
                    data.cost_centers.forEach(cc => {
                        const opt = document.createElement('option');
                        opt.value = cc.id;
                        opt.textContent = cc.code ? `${cc.code} - ${cc.name}` : cc.name;
                        select.appendChild(opt);
                    });
                }
            } catch (e) {
                select.innerHTML = '<option value="">No cost centers</option>';
            }
        },

        async loadVouchers() {
            const tbody = document.getElementById('vouchersTableBody');
            if (!tbody) return;
            const expensesOnly = this.vouchersModalMode === 'expenses';
            try {
                let allVouchers = [];
                if (expensesOnly) {
                    // Expenses view: use receipt-payment-vouchers (same API as save) so new vouchers appear
                    const resp = await fetch(`${this.apiBase}/receipt-payment-vouchers.php?type=payment`, { credentials: 'include' });
                    const data = await resp.json();
                    if (data.success && data.vouchers) {
                        data.vouchers.forEach(v => {
                            allVouchers.push({
                                id: v.id,
                                date: v.voucher_date || v.payment_date,
                                type: 'Payment',
                                reference: v.voucher_number || v.reference_number || 'N/A',
                                referenceNumber: v.reference_number || '',
                                amount: v.amount,
                                currency: v.currency || 'SAR',
                                status: v.status,
                                voucherType: 'payment'
                            });
                        });
                    }
                } else {
                    // Full vouchers: both payment and receipt from receipt-payment-vouchers API
                    const [paymentsResp, receiptsResp] = await Promise.all([
                        fetch(`${this.apiBase}/receipt-payment-vouchers.php?type=payment`, { credentials: 'include' }),
                        fetch(`${this.apiBase}/receipt-payment-vouchers.php?type=receipt`, { credentials: 'include' })
                    ]);
                    const paymentsData = await paymentsResp.json();
                    const receiptsData = await receiptsResp.json();
                    if (paymentsData.success && paymentsData.vouchers) {
                        paymentsData.vouchers.forEach(v => {
                            allVouchers.push({
                                id: v.id,
                                date: v.voucher_date || v.payment_date,
                                type: 'Payment',
                                reference: v.voucher_number || v.reference_number || 'N/A',
                                referenceNumber: v.reference_number || '',
                                amount: v.amount,
                                currency: v.currency || 'SAR',
                                status: v.status,
                                voucherType: 'payment'
                            });
                        });
                    }
                    if (receiptsData.success && receiptsData.vouchers) {
                        receiptsData.vouchers.forEach(v => {
                            allVouchers.push({
                                id: v.id,
                                date: v.voucher_date || v.payment_date,
                                type: 'Receipt',
                                reference: v.voucher_number || v.reference_number || 'N/A',
                                referenceNumber: v.reference_number || '',
                                amount: v.amount,
                                currency: v.currency || 'SAR',
                                status: v.status,
                                voucherType: 'receipt'
                            });
                        });
                    }
                }

                // One-time auto renumber: if any voucher still has old format (PV- / RV-), run renumber then reload
                const hasOldFormat = allVouchers.some(v => /^(PV|RV)-/.test(String(v.reference || '')));
                if (hasOldFormat && !sessionStorage.getItem('voucherRenumberDone')) {
                    try {
                        const renumResp = await fetch(`${this.apiBase}/receipt-payment-vouchers.php?action=renumber`, { credentials: 'include' });
                        const renumData = await renumResp.json().catch(() => ({}));
                        if (renumData.success) {
                            sessionStorage.setItem('voucherRenumberDone', '1');
                            await this.loadVouchers();
                            return;
                        }
                    } catch (e) { /* ignore; show current data */ }
                }

                // Sort by date (newest first)
                allVouchers.sort((a, b) => new Date(b.date) - new Date(a.date));
                
                // Render vouchers table
                if (allVouchers.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center">
                                <div class="empty-state">
                                    <i class="fas fa-file-invoice"></i>
                                    <p>No vouchers found</p>
                                </div>
                            </td>
                        </tr>
                    `;
                } else {
                    tbody.innerHTML = allVouchers.map(voucher => `
                        <tr>
                            <td>${this.escapeHtml(voucher.reference || 'N/A')}</td>
                            <td>${voucher.date || 'N/A'}</td>
                            <td>
                                <span class="badge badge-${voucher.type === 'Payment' ? 'danger' : 'success'}">
                                    ${this.escapeHtml(voucher.type)}
                                </span>
                            </td>
                            <td>${this.escapeHtml(voucher.referenceNumber || '-')}</td>
                            <td>${this.formatCurrency(voucher.amount || 0, voucher.currency)}</td>
                            <td>
                                <span class="badge badge-${voucher.status === 'Draft' ? 'secondary' : voucher.status === 'Cleared' || voucher.status === 'Deposited' ? 'success' : 'warning'}">
                                    ${this.escapeHtml(voucher.status || 'N/A')}
                                </span>
                            </td>
                            <td class="actions-column">
                                <button class="btn btn-sm btn-info" data-action="view-voucher" data-id="${voucher.id}" data-type="${voucher.voucherType}" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-warning" data-action="edit-voucher" data-id="${voucher.id}" data-type="${voucher.voucherType}" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-secondary" data-action="print-voucher" data-id="${voucher.id}" data-type="${voucher.voucherType}" title="Print">
                                    <i class="fas fa-print"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" data-action="delete-voucher" data-id="${voucher.id}" data-type="${voucher.voucherType}" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `).join('');
                }
            } catch (error) {
                console.error('Error loading vouchers:', error);
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-danger">
                            <i class="fas fa-exclamation-circle"></i> Error loading vouchers: ${this.escapeHtml(error.message)}
                        </td>
                    </tr>
                `;
                this.showToast(`Failed to load vouchers: ${error.message}`, 'error');
            }
        },

        async loadEntryApproval(statusFilter = null) {
            const tbodyEl = document.getElementById('entryApprovalTableBody');
            if (!tbodyEl) {
                console.error('entryApprovalTableBody not found');
                return;
            }

            // Get status filter from dropdown if not provided
            if (!statusFilter) {
                const filterSelect = document.getElementById('entryApprovalStatusFilter');
                statusFilter = filterSelect ? filterSelect.value : 'all';
            }

            try {
                tbodyEl.innerHTML = '<tr><td colspan="8" class="text-center"><div class="loading-state"><i class="fas fa-spinner fa-spin"></i><p>Loading entries...</p></div></td></tr>';
                
                // Build URL with filters
                const params = new URLSearchParams();
                if (statusFilter && statusFilter !== 'all') {
                    params.append('status', statusFilter);
                }
                
                // Add date filters if available
                const dateFrom = document.getElementById('entryApprovalDateFrom')?.value;
                const dateTo = document.getElementById('entryApprovalDateTo')?.value;
                if (dateFrom) {
                    params.append('date_from', this.formatDateForAPI(dateFrom));
                }
                if (dateTo) {
                    params.append('date_to', this.formatDateForAPI(dateTo));
                }
                
                const url = params.toString() 
                    ? `${this.apiBase}/entry-approval.php?${params.toString()}`
                    : `${this.apiBase}/entry-approval.php`;
                
                const response = await fetch(url, {
                    credentials: 'include'
                });
                
                if (!response.ok) {
                    const errorText = await response.text().catch(function() { return ''; });
                    throw new Error(errorText && errorText.length < 200 ? errorText : 'HTTP ' + response.status);
                }
                const data = await response.json().catch(function() { return { success: false, message: 'Invalid response' }; });
                if (!data.success) {
                    throw new Error((data && data.message) || (data && data.error) || 'Failed to load entries');
                }

                // Store all data
                this.entryApprovalData = data.entries || [];
                
                // Render table with pagination and filters
                this.renderEntryApprovalTable();
            } catch (error) {
                var errMsg = (error && typeof error.message === 'string') ? error.message : 'Unknown error';
                tbodyEl.innerHTML = '<tr><td colspan="8" class="text-center"><div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading entries</p><p class="text-muted">' + this.escapeHtml(errMsg) + '</p></div></td></tr>';
                this.showToast('Failed to load entries: ' + errMsg, 'error');
            }
        }
    };
    Object.assign(ProfessionalAccounting.prototype, methods);
})();
