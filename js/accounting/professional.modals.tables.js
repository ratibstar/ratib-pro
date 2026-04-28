/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.modals.tables.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.modals.tables.js`.
 */
/**
 * Professional Accounting - Modals (Tables & Handlers)
 * Load AFTER professional.js
 */
(function(){
    if (typeof ProfessionalAccounting === 'undefined') return;
    var origOpenPaymentVoucherModal = ProfessionalAccounting.prototype.openPaymentVoucherModal;
    const methods = {
        attachEntityTransactionsHandlers() {
            // Search input with debounce
            const searchInput = document.getElementById('entitySearchInput');
            const searchClear = document.getElementById('entitySearchClear');
                if (searchInput) {
                    let searchTimeout;
                    searchInput.addEventListener('input', (e) => {
                        clearTimeout(searchTimeout);
                    const value = e.target.value.trim();
                    if (value) {
                        if (searchClear) searchClear.classList.remove('hidden');
                    } else {
                        if (searchClear) searchClear.classList.add('hidden');
                    }
                        searchTimeout = setTimeout(() => {
                        this.entityTransactionsCurrentPage = 1;
                        this.loadEntityTransactionsData();
                        }, 300);
                    });
                }
            if (searchClear) {
                searchClear.addEventListener('click', () => {
                    if (searchInput) {
                        searchInput.value = '';
                        searchClear.classList.add('hidden');
                        this.entityTransactionsCurrentPage = 1;
                        this.loadEntityTransactionsData();
                    }
                });
            }
            // Filters
            const filterType = document.getElementById('entityFilterType');
            const filterStatus = document.getElementById('entityFilterStatus');
            if (filterType) {
                filterType.addEventListener('change', () => {
                    this.entityTransactionsCurrentPage = 1;
                    this.loadEntityTransactionsData();
                });
            }
            if (filterStatus) {
                filterStatus.addEventListener('change', () => {
                    this.entityTransactionsCurrentPage = 1;
                    this.loadEntityTransactionsData();
                });
            }
            // Per page
            const perPage = document.getElementById('entityPerPage');
            if (perPage) {
                perPage.addEventListener('change', (e) => {
                    this.entityTransactionsPerPage = parseInt(e.target.value);
                    this.entityTransactionsCurrentPage = 1;
                    this.loadEntityTransactionsData();
                });
            }
            // Select all checkbox
            const selectAll = document.getElementById('selectAllEntities');
            if (selectAll) {
                selectAll.addEventListener('change', (e) => {
                    const checked = e.target.checked;
                    const checkboxes = document.querySelectorAll('#entityTransactionsBody input[type="checkbox"][data-id]');
                    checkboxes.forEach(cb => {
                        cb.checked = checked;
                        const id = cb.getAttribute('data-id');
                        if (checked) {
                            this.entityTransactionsSelected.add(id);
                        } else {
                            this.entityTransactionsSelected.delete(id);
                        }
                    });
                    this.updateBulkActionsBar();
                });
            }
            // Reset filters
            document.querySelector('[data-action="reset-entity-filters"]')?.addEventListener('click', () => {
                if (filterType) filterType.value = '';
                if (filterStatus) filterStatus.value = '';
                if (searchInput) {
                    searchInput.value = '';
                    if (searchClear) searchClear.classList.add('hidden');
                }
                this.entityTransactionsCurrentPage = 1;
                this.loadEntityTransactionsData();
            });
        },

        updateEntityStatusCards(transactions) {
            const total = transactions.length;
            const posted = transactions.filter(t => t.status === 'Posted').length;
            const draft = transactions.filter(t => t.status === 'Draft').length;
            const totalAmount = transactions.reduce((sum, t) => sum + parseFloat(t.total_amount || 0), 0);
            const totalEl = document.getElementById('statusTotalTransactions');
            const postedEl = document.getElementById('statusPosted');
            const draftEl = document.getElementById('statusDraft');
            const amountEl = document.getElementById('statusTotalAmount');
            if (totalEl) totalEl.textContent = total;
            if (postedEl) postedEl.textContent = posted;
            if (draftEl) draftEl.textContent = draft;
            if (amountEl) amountEl.textContent = this.formatCurrency(totalAmount);
        },

        renderEntityTransactionsTable(transactions) {
            const tbody = document.getElementById('entityTransactionsBody');
            if (!tbody) return;
            if (transactions.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="17" class="empty-row">
                            <div class="empty-message">
                                <i class="fas fa-inbox"></i>
                                <span>No transactions found</span>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }
            tbody.innerHTML = transactions.map(t => {
                const isSelected = this.entityTransactionsSelected.has(t.id.toString());
                const statusClass = t.status === 'Posted' ? 'status-posted' : t.status === 'Draft' ? 'status-draft' : 'status-cancelled';
                // Format ID as 3 letters + 5 digits
                const idStr = String(t.id).padStart(5, '0');
                const formattedId = `ETX${idStr}`;
                
                // Get debit/credit from entity_transactions table (debit_amount, credit_amount)
                // If not available or both are 0, calculate from total_amount and transaction_type
                let debitAmount = parseFloat(t.debit_amount || 0);
                let creditAmount = parseFloat(t.credit_amount || 0);
                
                // Fallback: If both debit and credit are 0 or missing, calculate from total_amount and transaction_type
                // This handles cases where the entity_transactions table has NULL or 0 values
                if (debitAmount === 0 && creditAmount === 0 && t.total_amount) {
                    const totalAmount = parseFloat(t.total_amount || 0);
                    if (totalAmount > 0) {
                        if (t.transaction_type === 'Expense') {
                            debitAmount = totalAmount;
                            creditAmount = 0;
                        } else if (t.transaction_type === 'Income') {
                            debitAmount = 0;
                            creditAmount = totalAmount;
                        }
                    }
                }
                
                // Ensure we have valid numbers
                debitAmount = isNaN(debitAmount) ? 0 : debitAmount;
                creditAmount = isNaN(creditAmount) ? 0 : creditAmount;
                return `
                    <tr>
                        <td>${formattedId}</td>
                        <td>${t.transaction_date ? this.formatDate(t.transaction_date) : '-'}</td>
                        <td>
                            <div class="entity-cell">
                                <span class="entity-name">${this.escapeHtml(t.entity_name || `${t.entity_type} #${t.entity_id}`)}</span>
                                <span class="entity-type-badge">${t.entity_type || '-'}</span>
                            </div>
                        </td>
                        <td>${t.transaction_type || '-'}</td>
                        <td>${t.reference_number || '-'}</td>
                        <td class="description-cell">${this.escapeHtml(t.description || '-')}</td>
                        <td class="amount-cell debit">${debitAmount > 0 ? this.formatCurrency(debitAmount) : '-'}</td>
                        <td class="amount-cell credit">${creditAmount > 0 ? this.formatCurrency(creditAmount) : '-'}</td>
                        <td><span class="status-badge ${statusClass}">${t.status || '-'}</span></td>
                        <td class="checkbox-col">
                            <input type="checkbox" class="checkbox-modern" data-id="${t.id}" ${isSelected ? 'checked' : ''}>
                        </td>
                        <td class="actions-col">
                            <div class="action-buttons">
                                <button class="btn-icon btn-view" data-action="view-entity-transaction" data-id="${t.id}" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-icon btn-edit" data-action="edit-entity-transaction" data-id="${t.id}" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon btn-delete" data-action="delete-entity-transaction" data-id="${t.id}" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
            // Attach checkbox handlers
            tbody.querySelectorAll('input[type="checkbox"][data-id]').forEach(cb => {
                cb.addEventListener('change', (e) => {
                    const id = e.target.getAttribute('data-id');
                    if (e.target.checked) {
                        this.entityTransactionsSelected.add(id);
                    } else {
                        this.entityTransactionsSelected.delete(id);
                        const selectAll = document.getElementById('selectAllEntities');
                        if (selectAll) selectAll.checked = false;
                    }
                    this.updateBulkActionsBar();
                });
            });
            // Attach action handlers
            tbody.querySelectorAll('[data-action="view-entity-transaction"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = e.target.closest('[data-id]').getAttribute('data-id');
                    this.openEntityTransactionModal(id);
                });
            });
            tbody.querySelectorAll('[data-action="edit-entity-transaction"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = e.target.closest('[data-id]').getAttribute('data-id');
                    this.openEntityTransactionModal(id);
                });
            });
            tbody.querySelectorAll('[data-action="delete-entity-transaction"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = e.target.closest('[data-id]').getAttribute('data-id');
                    this.deleteEntityTransaction(id);
                });
            });
        },

        updateEntityTransactionsPagination() {
            const paginationTop = document.getElementById('entityPaginationTop');
            const paginationBottom = document.getElementById('entityPaginationBottom');
            const paginationInfo = document.getElementById('entityPaginationInfo');
            const current = this.entityTransactionsCurrentPage;
            const total = this.entityTransactionsTotalPages;
            const start = total === 0 ? 0 : (current - 1) * this.entityTransactionsPerPage + 1;
            const end = Math.min(current * this.entityTransactionsPerPage, this.entityTransactionsTotalCount);
            // Update info
            if (paginationInfo) {
                paginationInfo.textContent = `Showing ${start} to ${end} of ${this.entityTransactionsTotalCount} entries`;
            }
            // Create pagination HTML
            const paginationHTML = this.createPaginationHTML(current, total, 'entity-transactions');
            if (paginationTop) paginationTop.innerHTML = paginationHTML;
            if (paginationBottom) paginationBottom.innerHTML = paginationHTML;
            // Attach pagination handlers
            document.querySelectorAll('[data-action="entity-transactions-page"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const page = parseInt(e.target.getAttribute('data-page'));
                    if (!isNaN(page)) {
                        this.entityTransactionsCurrentPage = page;
                        this.loadEntityTransactionsData();
                    }
                });
            });
            const prevBtn = document.querySelector('[data-action="entity-transactions-prev"]');
            const nextBtn = document.querySelector('[data-action="entity-transactions-next"]');
            
            if (prevBtn) {
                prevBtn.disabled = current <= 1;
                prevBtn.addEventListener('click', () => {
                    if (current > 1) {
                        this.entityTransactionsCurrentPage--;
                        this.loadEntityTransactionsData();
                    }
                });
            }
            if (nextBtn) {
                nextBtn.disabled = current >= total;
                nextBtn.addEventListener('click', () => {
                    if (current < total) {
                        this.entityTransactionsCurrentPage++;
                        this.loadEntityTransactionsData();
                    }
                });
            }
        },

        updateBulkActionsBar() {
            const count = this.entityTransactionsSelected.size;
            const bulkBar = document.getElementById('entityBulkActions');
            const bulkCount = document.getElementById('entityBulkCount');
            
            if (bulkBar) {
                if (count > 0) {
                    bulkBar.classList.remove('entity-bulk-actions-hidden');
                    bulkBar.classList.add('entity-bulk-actions-visible');
                } else {
                    bulkBar.classList.remove('entity-bulk-actions-visible');
                    bulkBar.classList.add('entity-bulk-actions-hidden');
                }
            }
            if (bulkCount) {
                bulkCount.textContent = count;
            }
        },

        createPaginationHTML(current, total, prefix) {
            if (total <= 1) return '';
            let html = '<div class="pagination-controls-modern">';
            html += `<button class="btn-pagination" data-action="${prefix}-prev" ${current <= 1 ? 'disabled' : ''}><i class="fas fa-chevron-left"></i> Previous</button>`;
            html += '<div class="page-numbers-modern">';
            const maxPages = 5;
            let startPage = Math.max(1, current - Math.floor(maxPages / 2));
            let endPage = Math.min(total, startPage + maxPages - 1);
            if (endPage - startPage < maxPages - 1) startPage = Math.max(1, endPage - maxPages + 1);
            if (startPage > 1) {
                html += `<button class="btn-page" data-action="${prefix}-page" data-page="1">1</button>`;
                if (startPage > 2) html += '<span class="page-ellipsis">...</span>';
            }
            for (let i = startPage; i <= endPage; i++) {
                html += `<button class="btn-page ${i === current ? 'active' : ''}" data-action="${prefix}-page" data-page="${i}">${i}</button>`;
            }
            if (endPage < total) {
                if (endPage < total - 1) html += '<span class="page-ellipsis">...</span>';
                html += `<button class="btn-page" data-action="${prefix}-page" data-page="${total}">${total}</button>`;
            }
            html += '</div>';
            html += `<button class="btn-pagination" data-action="${prefix}-next" ${current >= total ? 'disabled' : ''}>Next <i class="fas fa-chevron-right"></i></button>`;
            html += '</div>';
            return html;
        },

        async deleteEntityTransaction(id) {
            const confirmed = await this.showConfirmDialog('Delete Transaction', 'Are you sure you want to delete this transaction?', 'Delete', 'Cancel', 'danger');
            if (!confirmed) return;
            try {
                const response = await fetch(`${this.apiBase}/entity-transactions.php?id=${parseInt(id)}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include'
                });
                const data = await response.json();
                if (data.success) {
                    this.showToast('Transaction deleted successfully', 'success');
                    this.entityTransactionsSelected.delete(String(id));
                    this.loadEntityTransactionsData();
                } else {
                    this.showToast(data.message || 'Failed to delete transaction', 'error');
                }
            } catch (error) {
                this.showToast('Error deleting transaction', 'error');
            }
        },

        async bulkDeleteEntityTransactions(ids) {
            try {
                const promises = ids.map(id =>
                    fetch(`${this.apiBase}/entity-transactions.php?id=${id}`, {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'include'
                    }).then(r => r.json())
                );
                const results = await Promise.all(promises);
                const successCount = results.filter(r => r.success).length;
                const failedCount = results.length - successCount;
                if (successCount > 0) {
                    this.showToast(`Successfully deleted ${successCount} transaction(s)`, 'success');
                    this.entityTransactionsSelected.clear();
                    this.loadEntityTransactionsData();
                }
                if (failedCount > 0) this.showToast(`Failed to delete ${failedCount} transaction(s)`, 'error');
            } catch (error) {
                this.showToast('Error deleting transactions', 'error');
            }
        },

        async bulkExportEntityTransactions(ids) {
            try {
                const filtered = this.entityTransactionsFiltered || [];
                const selectedTransactions = filtered.filter(t => ids.includes(parseInt(t.id)));
                const headers = ['ID', 'Date', 'Entity', 'Type', 'Reference', 'Description', 'Debit', 'Credit', 'Status'];
                const rows = selectedTransactions.map(t => [
                    t.id,
                    t.transaction_date || '',
                    t.entity_name || `${t.entity_type} #${t.entity_id}`,
                    t.transaction_type || '',
                    t.reference_number || '',
                    t.description || '',
                    t.debit_amount || 0,
                    t.credit_amount || 0,
                    t.status || ''
                ]);
                const csv = [headers, ...rows].map(row =>
                    row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')
                ).join('\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `entity-transactions-${new Date().toISOString().split('T')[0]}.csv`;
                link.classList.add('download-link-hidden');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
                this.showToast(`Exported ${ids.length} transaction(s)`, 'success');
            } catch (error) {
                this.showToast('Error exporting transactions', 'error');
            }
        },

        openReportsModal() {
            const content = `
                <div class="accounting-module-modal-content">
                    <div class="module-content">
                        <div class="summary-cards-mini-header">
                            <div class="summary-cards-mini">
                                <div class="summary-mini-card">
                                    <h4>Total Reports</h4>
                                    <p id="modalReportsTotal">16</p>
                                </div>
                                <div class="summary-mini-card">
                                    <h4>Financial</h4>
                                    <p id="modalReportsFinancial">9</p>
                                </div>
                                <div class="summary-mini-card">
                                    <h4>Operational</h4>
                                    <p id="modalReportsOperational">7</p>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>Balance Reports</h4>
                                    <p id="modalReportsBalanceCount">3</p>
                                    <span class="entity-amount">Trial Balance, Balance Sheet, Cash Flow Report</span>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>Transaction Reports</h4>
                                    <p id="modalReportsTransactionCount">5</p>
                                    <span class="entity-amount">Cash Book, Bank Book, Ledger, Account Statement, Chart</span>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>Aging Reports</h4>
                                    <p id="modalReportsAgingCount">2</p>
                                    <span class="entity-amount">Debt Receivable, Credit Receivable</span>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>Analysis Reports</h4>
                                    <p id="modalReportsAnalysisCount">6</p>
                                    <span class="entity-amount">Income, Expense, Performance, Equity, Comparative</span>
                                </div>
                            </div>
                        </div>
                        <div class="filters-and-pagination-container">
                            <div class="filters-bar filters-bar-compact">
                                <div class="filter-group filter-group-compact">
                                    <label>Category:</label>
                                    <select id="modalReportsCategoryFilter" class="filter-select filter-select-compact">
                                        <option value="">All Categories</option>
                                        <option value="financial">Financial</option>
                                        <option value="operational">Operational</option>
                                        <option value="balance">Balance Reports</option>
                                        <option value="transaction">Transaction Reports</option>
                                        <option value="aging">Aging Reports</option>
                                        <option value="analysis">Analysis Reports</option>
                                    </select>
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Search:</label>
                                    <input type="text" id="modalReportsSearch" class="filter-input filter-input-compact" placeholder="Search reports...">
                                </div>
                                <button class="btn btn-secondary btn-sm" data-action="export-all-reports">
                                    <i class="fas fa-download"></i> Export All
                                </button>
                            </div>
                        </div>
                        <div class="reports-grid" id="modalReportsGrid">
                            <div class="report-card" data-action="generate-report" data-report="trial-balance" data-category="balance">
                                <i class="fas fa-balance-scale"></i>
                                <h4>Trial Balance</h4>
                                <p>Summary of all account balances</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="income-statement" data-category="financial">
                                <i class="fas fa-chart-line"></i>
                                <h4>Income Statement</h4>
                                <p>Revenue and expenses report</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="balance-sheet" data-category="balance">
                                <i class="fas fa-file-alt"></i>
                                <h4>Balance Sheet</h4>
                                <p>Assets, liabilities, and equity</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="cash-flow" data-category="balance">
                                <i class="fas fa-exchange-alt"></i>
                                <h4>Cash Flow Report</h4>
                                <p>Cash inflows and outflows</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="general-ledger" data-category="transaction">
                                <i class="fas fa-book"></i>
                                <h4>General Ledger</h4>
                                <p>Complete ledger report</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="account-statement" data-category="transaction">
                                <i class="fas fa-file-alt"></i>
                                <h4>Account Statement</h4>
                                <p>Individual account statement</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="ages-debt-receivable" data-category="aging">
                                <i class="fas fa-clock"></i>
                                <h4>Ages of Debt Receivable</h4>
                                <p>Outstanding invoices by age</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="ages-credit-receivable" data-category="aging">
                                <i class="fas fa-clock"></i>
                                <h4>Ages of Credit Receivable</h4>
                                <p>Outstanding credit by age</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="cash-book" data-category="transaction">
                                <i class="fas fa-book-open"></i>
                                <h4>Cash Book</h4>
                                <p>All cash transactions</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="bank-book" data-category="transaction">
                                <i class="fas fa-university"></i>
                                <h4>Bank Book</h4>
                                <p>All bank transactions</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="chart-of-accounts-report" data-category="transaction">
                                <i class="fas fa-sitemap"></i>
                                <h4>Chart of Accounts</h4>
                                <p>Account structure overview</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="value-added" data-category="analysis">
                                <i class="fas fa-plus-circle"></i>
                                <h4>Value Added</h4>
                                <p>Value added analysis report</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="fixed-assets" data-category="financial">
                                <i class="fas fa-building"></i>
                                <h4>Fixed Assets Report</h4>
                                <p>Fixed assets overview</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="entries-by-year" data-category="financial">
                                <i class="fas fa-calendar-alt"></i>
                                <h4>Entries by Year Report</h4>
                                <p>Annual entries summary</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="customer-debits" data-category="financial">
                                <i class="fas fa-user-minus"></i>
                                <h4>Customer Debits Report</h4>
                                <p>Customer debits analysis</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="statistical-position" data-category="analysis">
                                <i class="fas fa-chart-pie"></i>
                                <h4>Statistical Position Report</h4>
                                <p>Statistical financial position</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="changes-equity" data-category="analysis">
                                <i class="fas fa-chart-line"></i>
                                <h4>Changes in Equity</h4>
                                <p>Equity changes over time</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="financial-performance" data-category="analysis">
                                <i class="fas fa-tachometer-alt"></i>
                                <h4>Financial Performance</h4>
                                <p>Financial performance metrics</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="comparative-report" data-category="analysis">
                                <i class="fas fa-columns"></i>
                                <h4>Comparative Report</h4>
                                <p>Period comparison analysis</p>
                            </div>
                            <div class="report-card" data-action="generate-report" data-report="expense-statement" data-category="analysis">
                                <i class="fas fa-file-invoice"></i>
                                <h4>Expense Statement</h4>
                                <p>Detailed expense breakdown</p>
                            </div>
                        </div>
                        <div id="modalReportContent" class="modal-report-content">
                            <!-- Generated report will appear here -->
                        </div>
                    </div>
                </div>
            `;
            this.showModal('Financial Reports', content, 'large');
            setTimeout(() => {
                this.attachReportCardListeners();
                this.setupReportsFilters();
                // Update counts on initial load
                this.filterReports();
            }, 100);
        },

        setupReportsFilters() {
            const categoryFilter = document.getElementById('modalReportsCategoryFilter');
            const searchInput = document.getElementById('modalReportsSearch');
            
            if (categoryFilter) {
                categoryFilter.addEventListener('change', () => {
                    this.filterReports();
                });
            }
            
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.filterReports();
                    }, 300);
                });
            }
        },

        filterReports() {
            const categoryFilter = document.getElementById('modalReportsCategoryFilter');
            const searchInput = document.getElementById('modalReportsSearch');
            const reportCards = document.querySelectorAll('#modalReportsGrid .report-card');
            
            const category = categoryFilter?.value || '';
            const search = (searchInput?.value || '').toLowerCase();
            
            let visibleCount = 0;
            let financialCount = 0;
            let operationalCount = 0;
            let balanceCount = 0;
            let transactionCount = 0;
            let agingCount = 0;
            let analysisCount = 0;
            
            reportCards.forEach(card => {
                const cardCategory = card.getAttribute('data-category') || '';
                const cardTitle = card.querySelector('h4')?.textContent || '';
                const cardDesc = card.querySelector('p')?.textContent || '';
                const cardText = (cardTitle + ' ' + cardDesc).toLowerCase();
                
                const matchesCategory = !category || cardCategory === category;
                const matchesSearch = !search || cardText.includes(search);
                
                if (matchesCategory && matchesSearch) {
                    card.classList.remove('report-card-hidden');
                    card.classList.add('report-card-visible');
                    visibleCount++;
                    
                    // Count by specific category
                    if (cardCategory === 'balance') balanceCount++;
                    else if (cardCategory === 'transaction') transactionCount++;
                    else if (cardCategory === 'aging') agingCount++;
                    else if (cardCategory === 'analysis') analysisCount++;
                    
                    // Count by financial vs operational
                    if (['balance', 'financial', 'analysis'].includes(cardCategory)) {
                        financialCount++;
                    } else if (['transaction', 'aging'].includes(cardCategory)) {
                        operationalCount++;
                    }
                } else {
                    card.classList.remove('report-card-visible');
                    card.classList.add('report-card-hidden');
                }
            });
            
            // Update summary cards
            const totalEl = document.getElementById('modalReportsTotal');
            const financialEl = document.getElementById('modalReportsFinancial');
            const operationalEl = document.getElementById('modalReportsOperational');
            const balanceCountEl = document.getElementById('modalReportsBalanceCount');
            const transactionCountEl = document.getElementById('modalReportsTransactionCount');
            const agingCountEl = document.getElementById('modalReportsAgingCount');
            const analysisCountEl = document.getElementById('modalReportsAnalysisCount');
            
            if (totalEl) totalEl.textContent = visibleCount;
            if (financialEl) financialEl.textContent = financialCount;
            if (operationalEl) operationalEl.textContent = operationalCount;
            if (balanceCountEl) balanceCountEl.textContent = balanceCount;
            if (transactionCountEl) transactionCountEl.textContent = transactionCount;
            if (agingCountEl) agingCountEl.textContent = agingCount;
            if (analysisCountEl) analysisCountEl.textContent = analysisCount;
        },

        setupSettingsFilters() {
            const searchInput = document.getElementById('modalSettingsSearch');
            
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.filterSettings(e.target.value);
                    }, 300);
                });
            }
        },

        filterSettings(searchTerm) {
            const search = (searchTerm || '').toLowerCase();
            const container = document.getElementById('modalSettingsSectionsContainer');
            const settingItems = container.querySelectorAll('.setting-item');
            let visibleCount = 0;
            
            settingItems.forEach(item => {
                const label = item.querySelector('label span')?.textContent || '';
                const labelText = label.toLowerCase();
                
                const matches = !search || labelText.includes(search);
                
                if (matches) {
                    item.classList.remove('settings-section-hidden');
                    item.classList.add('settings-section-visible');
                    item.classList.remove('hidden');
                    visibleCount++;
                } else {
                    item.classList.remove('settings-section-visible');
                    item.classList.add('settings-section-hidden');
                    item.classList.add('hidden');
                }
            });
            
            // Show/hide no results message
            let noResultsMsg = container.querySelector('.settings-no-results');
            if (visibleCount === 0 && search) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.className = 'settings-no-results';
                    noResultsMsg.innerHTML = `
                        <div class="accounting-empty-state">
                            <i class="fas fa-search accounting-empty-state-icon"></i>
                            <p class="accounting-empty-state-text">No settings found matching "${this.escapeHtml(searchTerm)}"</p>
                        </div>
                    `;
                    container.appendChild(noResultsMsg);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        },

        resetSettings() {
            (async () => {
                // Reset directly without confirmation
                
                // Reload settings from API (will use defaults if not set)
                await this.loadSettings();
                
                // Remove changed indicators
                const settingInputs = document.querySelectorAll('#accountingSettingsModal [data-setting-key]');
                settingInputs.forEach(input => input.classList.remove('setting-changed'));
                
                this.showToast('Settings reset to defaults', 'success');
            })();
        },

        exportSettings() {
            const settingInputs = document.querySelectorAll('#accountingSettingsModal [data-setting-key]');
            const settings = {};
            
            settingInputs.forEach(input => {
                const key = input.getAttribute('data-setting-key');
                let value = input.value;
                
                if (input.type === 'checkbox') {
                    value = input.checked;
                }
                
                settings[key] = value;
            });
            
            const json = JSON.stringify(settings, null, 2);
            const blob = new Blob([json], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', `accounting_settings_${new Date().toISOString().split('T')[0]}.json`);
            link.classList.add('export-link-hidden');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            
            this.showToast('Settings exported successfully', 'success');
        },

        setupSettingsHandlers() {
            const modal = document.getElementById('accountingSettingsModal');
            if (!modal) return;
            
            // Save button
            const saveBtn = modal.querySelector('[data-action="save-settings"]');
            if (saveBtn && !saveBtn.dataset.handlerAttached) {
                saveBtn.dataset.handlerAttached = 'true';
                saveBtn.addEventListener('click', () => this.saveSettings());
            }
            
            // Reset button
            const resetBtn = modal.querySelector('[data-action="reset-settings"]');
            if (resetBtn && !resetBtn.dataset.handlerAttached) {
                resetBtn.dataset.handlerAttached = 'true';
                resetBtn.addEventListener('click', () => this.resetSettings());
            }
            
            // Export button
            const exportBtn = modal.querySelector('[data-action="export-settings"]');
            if (exportBtn && !exportBtn.dataset.handlerAttached) {
                exportBtn.dataset.handlerAttached = 'true';
                exportBtn.addEventListener('click', () => this.exportSettings());
            }
        },

        async loadSettings() {
            try {
                const response = await fetch(`${this.apiBase}/settings.php`, { credentials: 'include' });
                const data = await response.json();
                if (data.success && data.settings) {
                    const settingsMap = {};
                    data.settings.forEach(setting => { settingsMap[setting.key] = setting.value; });
                    const settingInputs = document.querySelectorAll('#accountingSettingsModal [data-setting-key]');
                    settingInputs.forEach(input => {
                        const key = input.getAttribute('data-setting-key');
                        const value = settingsMap[key];
                        if (value !== undefined && value !== null) {
                            if (input.type === 'checkbox') {
                                input.checked = value === true || value === '1' || value === 1;
                            } else if (input.tagName === 'SELECT') {
                                const option = Array.from(input.options).find(opt => opt.value === value || opt.value === String(value));
                                if (option) input.value = option.value;
                                else if (input.id === 'defaultCurrency' && value) {
                                    const code = String(value).toUpperCase();
                                    const currencyOption = Array.from(input.options).find(opt =>
                                        (opt.value.includes(' - ') ? opt.value.split(' - ')[0].trim().toUpperCase() : opt.value.toUpperCase()) === code
                                    );
                                    if (currencyOption) input.value = currencyOption.value;
                                }
                            } else {
                                input.value = value;
                            }
                        }
                    });
                    this.updateSettingsSummary();
                }
            } catch (error) {
                console.error('Error loading settings:', error);
                this.showToast('Failed to load settings. Using defaults.', 'warning');
            }
        },

        async saveSettings() {
            const settingInputs = document.querySelectorAll('#accountingSettingsModal [data-setting-key]');
            const settingsToSave = [];
            settingInputs.forEach(input => {
                const key = input.getAttribute('data-setting-key');
                const type = input.getAttribute('data-setting-type') || 'text';
                let value = input.value;
                if (input.type === 'checkbox') value = input.checked;
                else if (type === 'number') value = parseFloat(value) || 0;
                else if (type === 'boolean') value = value === '1' || value === 1 || value === true;
                else if (input.tagName === 'SELECT' && input.id === 'defaultCurrency' && value && value.includes(' - '))
                    value = value.split(' - ')[0].trim();
                settingsToSave.push({ key, value, type });
            });
            try {
                const results = await Promise.all(settingsToSave.map(s =>
                    fetch(`${this.apiBase}/settings.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(s),
                        credentials: 'include'
                    })
                ));
                const allSuccess = results.every(r => r.ok);
                if (allSuccess) {
                    this.showToast('Settings saved successfully!', 'success');
                    settingInputs.forEach(input => input.classList.remove('setting-changed'));
                } else {
                    this.showToast('Some settings failed to save. Please try again.', 'error');
                }
            } catch (error) {
                this.showToast('Error saving settings: ' + (error?.message || ''), 'error');
            }
        },

        updateSettingsSummary() {
            const taxRateInput = document.getElementById('defaultTaxRate');
            const taxMethodSelect = document.getElementById('taxMethod');
            const currencySelect = document.getElementById('defaultCurrency');
            const fiscalYearStartInput = document.getElementById('fiscalYearStart');
            
            const taxRate = taxRateInput?.value || '15';
            const taxMethod = taxMethodSelect?.value === 'inclusive' ? 'Inclusive' : 'Exclusive';
            // Extract currency code from dropdown value (may be "CODE - Name" format)
            let currency = currencySelect?.value || this.getDefaultCurrencySync();
            if (currency && currency.includes(' - ')) {
                currency = currency.split(' - ')[0].trim();
            }
            if (!currency || currency === '0' || currency.length !== 3) {
                currency = this.getDefaultCurrencySync();
            }
            const fiscalYear = fiscalYearStartInput?.value ? new Date(fiscalYearStartInput.value).getFullYear() : new Date().getFullYear();
            
            const taxRateEl = document.getElementById('modalSettingsTaxRate');
            const taxMethodEl = document.getElementById('modalSettingsTaxMethod');
            const currencyEl = document.getElementById('modalSettingsCurrency');
            const fiscalYearEl = document.getElementById('modalSettingsFiscalYear');
            
            if (taxRateEl) taxRateEl.textContent = `${taxRate}%`;
            if (taxMethodEl) taxMethodEl.textContent = taxMethod;
            if (currencyEl) currencyEl.textContent = currency;
            if (fiscalYearEl) fiscalYearEl.textContent = fiscalYear;
        },

        openChartOfAccountsModal() {
            // Reset to defaults
            this.coaCurrentPage = 1;
            this.coaPerPage = 5;
            this.coaSelectedAccounts.clear();
            
            this.showModal('Chart of Accounts', this.getChartOfAccountsModalContent(), 'large');
            setTimeout(() => {
                // Wait for modal to be fully rendered
                const modal = document.getElementById('chartOfAccountsModal');
                const modalBody = modal ? modal.querySelector('.accounting-modal-body') : null;
                const scrollableContent = modal ? modal.querySelector('.coa-scrollable-content') : null;
                
                // Add CSS classes for proper layout (no inline styles)
                if (modalBody) {
                    modalBody.classList.add('coa-modal-body');
                }
                
                if (scrollableContent) {
                    scrollableContent.classList.add('coa-scrollable-content-active');
                }
                
                const tableWrapper = modal ? modal.querySelector('.coa-table-wrapper') : null;
                if (tableWrapper) {
                    tableWrapper.classList.add('coa-table-wrapper-active');
                    tableWrapper.setAttribute('data-per-page', '5'); // Set initial value
                }
                
                const tbody = document.getElementById('chartOfAccountsBody');
                const perPageSelect = document.getElementById('coaPerPage');
                
                // Force set per page to 5
                if (perPageSelect) {
                    perPageSelect.value = '5';
                }
                
                if (!tbody) {
                    setTimeout(() => {
                        this.loadChartOfAccounts();
                        this.setupChartOfAccountsFilters();
                    }, 200);
                    return;
                }
                this.loadChartOfAccounts();
                this.setupChartOfAccountsFilters();
            }, 150);
        },

        setupChartOfAccountsFilters() {
            const modal = document.getElementById('chartOfAccountsModal');
            if (!modal) {
                return;
            }
            
            // Account Type Filter
            const accountTypeFilter = modal.querySelector('#coaAccountTypeFilter');
            if (accountTypeFilter) {
                // Remove any existing listeners by cloning
                const newFilter = accountTypeFilter.cloneNode(true);
                accountTypeFilter.parentNode.replaceChild(newFilter, accountTypeFilter);
                
                newFilter.addEventListener('change', (e) => {
                    this.coaCurrentPage = 1;
                    this.loadChartOfAccounts();
                });
            }
            
            // Search
            const coaSearch = modal.querySelector('#coaSearch');
            if (coaSearch) {
                // Remove any existing listeners by cloning
                const newSearch = coaSearch.cloneNode(true);
                coaSearch.parentNode.replaceChild(newSearch, coaSearch);
                
                let searchTimeout;
                newSearch.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.coaCurrentPage = 1;
                        this.loadChartOfAccounts();
                    }, 300);
                });
                // Also trigger on Enter key
                newSearch.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        clearTimeout(searchTimeout);
                        this.coaCurrentPage = 1;
                        this.loadChartOfAccounts();
                    }
                });
            }
            
            // Per Page
            const coaPerPage = modal.querySelector('#coaPerPage');
            if (coaPerPage && !coaPerPage.dataset.listenerAdded) {
                // Force set default value to 5
                this.coaPerPage = 5;
                coaPerPage.value = '5';
                
                coaPerPage.addEventListener('change', (e) => {
                    this.coaPerPage = parseInt(e.target.value) || 5;
                    this.coaCurrentPage = 1;
                    
                    // Update data-per-page attribute for CSS
                    const modal = document.getElementById('chartOfAccountsModal');
                    const tableWrapper = modal ? modal.querySelector('.coa-table-wrapper') : null;
                    if (tableWrapper) {
                        tableWrapper.setAttribute('data-per-page', this.coaPerPage.toString());
                    }
                    
                    this.loadChartOfAccounts().then(() => {
                        // Scroll after content loads
                        setTimeout(() => this.scrollToCoaTable(), 300);
                    }).catch(() => {
                        setTimeout(() => this.scrollToCoaTable(), 300);
                    });
                });
                coaPerPage.dataset.listenerAdded = 'true';
            }
            
            // Sorting
            document.querySelectorAll('#chartOfAccountsTable th[data-sort]').forEach(th => {
                if (!th.dataset.listenerAdded) {
                    th.classList.add('coa-sortable-header');
                    th.addEventListener('click', () => {
                        const sortColumn = th.dataset.sort;
                        if (this.coaSortColumn === sortColumn) {
                            this.coaSortDirection = this.coaSortDirection === 'asc' ? 'desc' : 'asc';
                        } else {
                            this.coaSortColumn = sortColumn;
                            this.coaSortDirection = 'asc';
                        }
                        this.updateCoaSortIndicators();
                        this.loadChartOfAccounts();
                    });
                    th.dataset.listenerAdded = 'true';
                }
            });
            
            // Bulk selection
            const selectAll = document.getElementById('coaSelectAll');
            if (selectAll && !selectAll.dataset.listenerAdded) {
                selectAll.addEventListener('change', (e) => {
                    const isChecked = e.target.checked;
                    document.querySelectorAll('.coa-row-checkbox').forEach(cb => {
                        cb.checked = isChecked;
                        const accountId = parseInt(cb.dataset.accountId);
                        if (isChecked) {
                            this.coaSelectedAccounts.add(accountId);
                        } else {
                            this.coaSelectedAccounts.delete(accountId);
                        }
                    });
                    this.updateCoaBulkActions();
                });
                selectAll.dataset.listenerAdded = 'true';
            }
            
            // Individual row checkboxes
            document.addEventListener('change', (e) => {
                if (e.target.classList.contains('coa-row-checkbox')) {
                    const accountId = parseInt(e.target.dataset.accountId);
                    if (e.target.checked) {
                        this.coaSelectedAccounts.add(accountId);
                    } else {
                        this.coaSelectedAccounts.delete(accountId);
                    }
                    this.updateCoaSelectAll();
                    this.updateCoaBulkActions();
                }
            });
            
            // Update sort indicators
            this.updateCoaSortIndicators();
        },

        updateCoaSortIndicators() {
            document.querySelectorAll('#chartOfAccountsTable th[data-sort]').forEach(th => {
                const icon = th.querySelector('i');
                if (th.dataset.sort === this.coaSortColumn) {
                    if (icon) {
                        icon.className = this.coaSortDirection === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
                    }
                    th.classList.add('sort-active');
                } else {
                    if (icon) {
                        icon.className = 'fas fa-sort';
                    }
                    th.classList.remove('sort-active');
                }
            });
        },

        updateCoaSelectAll() {
            const selectAll = document.getElementById('coaSelectAll');
            if (selectAll) {
                const checkboxes = document.querySelectorAll('.coa-row-checkbox');
                const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
                selectAll.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
            }
        },

        updateCoaPagination() {
            const updatePaginationSet = (position) => {
                const isTop = position === 'top';
                const prefix = isTop ? 'Top' : 'Bottom';
                const infoEl = document.getElementById(`coaPaginationInfo${prefix}`);
                const prevBtn = document.getElementById(`coaPrev${prefix}`);
                const nextBtn = document.getElementById(`coaNext${prefix}`);
                const pageNumbersEl = document.getElementById(`coaPageNumbers${prefix}`);
                
                if (infoEl) {
                    const totalUnfiltered = this.coaTotalAccountsUnfiltered || this.coaTotalCount;
                    if (this.coaPerPage > 0 && this.coaPerPage < this.coaTotalCount) {
                        const startIndex = this.coaTotalCount > 0 ? (this.coaCurrentPage - 1) * this.coaPerPage + 1 : 0;
                        const endIndex = Math.min(this.coaCurrentPage * this.coaPerPage, this.coaTotalCount);
                        if (this.coaTotalCount < totalUnfiltered) {
                            infoEl.textContent = `Showing ${startIndex} to ${endIndex} of ${this.coaTotalCount} filtered accounts (${totalUnfiltered} total)`;
                        } else {
                            infoEl.textContent = `Showing ${startIndex} to ${endIndex} of ${this.coaTotalCount} accounts`;
                        }
                    } else {
                        if (this.coaTotalCount < totalUnfiltered) {
                            infoEl.textContent = `Showing all ${this.coaTotalCount} filtered accounts (${totalUnfiltered} total)`;
                        } else {
                            infoEl.textContent = `Showing all ${this.coaTotalCount} accounts`;
                        }
                    }
                }
                
                if (prevBtn) {
                    prevBtn.disabled = this.coaCurrentPage <= 1;
                    prevBtn.classList.toggle('disabled', this.coaCurrentPage <= 1);
                }
                
                if (nextBtn) {
                    nextBtn.disabled = this.coaCurrentPage >= this.coaTotalPages;
                    nextBtn.classList.toggle('disabled', this.coaCurrentPage >= this.coaTotalPages);
                }
                
                if (pageNumbersEl) {
                    let pageNumbersHTML = '';
                    const maxPages = 5;
                    let startPage = Math.max(1, this.coaCurrentPage - Math.floor(maxPages / 2));
                    let endPage = Math.min(this.coaTotalPages, startPage + maxPages - 1);
                    
                    if (endPage - startPage < maxPages - 1) {
                        startPage = Math.max(1, endPage - maxPages + 1);
                    }
                    
                    if (startPage > 1) {
                        pageNumbersHTML += `<button class="btn btn-sm btn-secondary" data-action="coa-page" data-page="1">1</button>`;
                        if (startPage > 2) {
                            pageNumbersHTML += `<span>...</span>`;
                        }
                    }
                    
                    for (let i = startPage; i <= endPage; i++) {
                        pageNumbersHTML += `<button class="btn btn-sm ${i === this.coaCurrentPage ? 'btn-primary' : 'btn-secondary'}" data-action="coa-page" data-page="${i}">${i}</button>`;
                    }
                    
                    if (endPage < this.coaTotalPages) {
                        if (endPage < this.coaTotalPages - 1) {
                            pageNumbersHTML += `<span>...</span>`;
                        }
                        pageNumbersHTML += `<button class="btn btn-sm btn-secondary" data-action="coa-page" data-page="${this.coaTotalPages}">${this.coaTotalPages}</button>`;
                    }
                    
                    pageNumbersEl.innerHTML = pageNumbersHTML;
                }
            };
            
            updatePaginationSet('top');
            updatePaginationSet('bottom');
        },

        updateCoaBulkActions() {
            const bulkBar = document.getElementById('bulkActionsCoa');
            const countEl = document.getElementById('bulkSelectedCountCoa');
            
            if (this.coaSelectedAccounts.size > 0) {
                if (bulkBar) {
                    bulkBar.classList.remove('coa-bulk-actions-hidden');
                    bulkBar.classList.add('coa-bulk-actions-visible');
                }
                if (countEl) countEl.textContent = `${this.coaSelectedAccounts.size} selected`;
            } else {
                if (bulkBar) {
                    bulkBar.classList.remove('coa-bulk-actions-visible');
                    bulkBar.classList.add('coa-bulk-actions-hidden');
                }
                if (countEl) countEl.textContent = '0 selected';
            }
        },

        scrollToCoaTable() {
            const modal = document.getElementById('chartOfAccountsModal');
            if (!modal) return;
            
            // Find scroll target - scroll to table container
            const tableContainer = modal.querySelector('.coa-table-wrapper');
            const table = modal.querySelector('#chartOfAccountsTable');
            const scrollTarget = tableContainer || table;
            
            if (!scrollTarget) return;
            
            // Get the scrollable content container
            const scrollableContent = modal.querySelector('.coa-scrollable-content');
            
            if (scrollableContent) {
                // Wait a moment for styles to apply, then scroll
                setTimeout(() => {
                    const scrollHeight = scrollableContent.scrollHeight;
                    const clientHeight = scrollableContent.clientHeight;
                    
                    if (scrollHeight > clientHeight) {
                        // Calculate offset from scrollable content
                        let offset = 0;
                        let el = scrollTarget;
                        while (el && el !== scrollableContent && el !== document.body && el !== document.documentElement) {
                            offset += el.offsetTop;
                            el = el.offsetParent;
                        }
                        
                        // Scroll to show the table (accounting for sticky headers)
                        scrollableContent.scrollTo({
                            top: Math.max(0, offset - 10),
                            behavior: 'smooth'
                        });
                    } else {
                        // Not scrollable, use scrollIntoView
                        scrollTarget.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start',
                            inline: 'nearest'
                        });
                    }
                }, 50);
            } else {
                // Fallback: use scrollIntoView
                scrollTarget.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start',
                    inline: 'nearest'
                });
            }
        },

        getChartOfAccountsModalContent() {
            return `
                <div class="accounting-module-modal-content">
                    <div class="module-content">
                        <div class="summary-cards-mini-header">
                            <div class="summary-cards-mini">
                                <div class="summary-mini-card">
                                    <h4>Total Accounts</h4>
                                    <p id="modalCoaTotalAccounts">0</p>
                        </div>
                                <div class="summary-mini-card">
                                    <h4>Active</h4>
                                    <p id="modalCoaActive">0</p>
                    </div>
                                <div class="summary-mini-card">
                                    <h4>Inactive</h4>
                                    <p id="modalCoaInactive">0</p>
                                </div>
                                <div class="summary-mini-card">
                                    <h4>Total Balance</h4>
                                    <p id="modalCoaTotalBalance">SAR 0.00</p>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>Assets</h4>
                                    <p id="modalCoaAssetsCount">0</p>
                                    <span id="modalCoaAssetsBalance" class="entity-amount">SAR 0.00</span>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>Liabilities</h4>
                                    <p id="modalCoaLiabilitiesCount">0</p>
                                    <span id="modalCoaLiabilitiesBalance" class="entity-amount">SAR 0.00</span>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>Equity</h4>
                                    <p id="modalCoaEquityCount">0</p>
                                    <span id="modalCoaEquityBalance" class="entity-amount">SAR 0.00</span>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>Income</h4>
                                    <p id="modalCoaIncomeCount">0</p>
                                    <span id="modalCoaIncomeBalance" class="entity-amount">SAR 0.00</span>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>Expenses</h4>
                                    <p id="modalCoaExpensesCount">0</p>
                                    <span id="modalCoaExpensesBalance" class="entity-amount">SAR 0.00</span>
                                </div>
                            </div>
                        </div>
                        <div class="filters-and-pagination-container">
                            <div class="filters-bar filters-bar-compact">
                                <div class="filter-group filter-group-compact">
                                <label>Account Type:</label>
                                    <select id="coaAccountTypeFilter" class="filter-select filter-select-compact">
                                    <option value="">All Types</option>
                                    <option value="Asset">Asset</option>
                                    <option value="Liability">Liability</option>
                                    <option value="Equity">Equity</option>
                                    <option value="Income">Income</option>
                                    <option value="Expense">Expense</option>
                                </select>
                            </div>
                                <div class="filter-group filter-group-compact">
                                <label>Search:</label>
                                    <input type="text" id="coaSearch" class="filter-input filter-input-compact" placeholder="Search by code or name...">
                            </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Show:</label>
                                    <select id="coaPerPage" class="filter-select filter-select-compact">
                                        <option value="5" selected>5</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                        </div>
                                <button class="btn btn-primary btn-sm" data-action="new-account" data-permission="add_account,view_chart_accounts">
                                    <i class="fas fa-plus"></i> New Account
                                </button>
                                <button class="btn btn-secondary btn-sm" data-action="export-accounts" data-permission="view_chart_accounts">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                            <div class="table-pagination-top" id="coaPaginationTop">
                                <div class="pagination-info" id="coaPaginationInfoTop"></div>
                                <div class="pagination-controls">
                                    <button class="btn-pagination btn-pagination-nav" id="coaFirstTop" data-action="coa-page" data-page="1" title="First Page">
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>
                                    <button class="btn-pagination btn-pagination-nav" id="coaPrevTop" data-action="coa-prev" title="Previous Page">
                                        <i class="fas fa-angle-left"></i>
                                    </button>
                                    <span id="coaPageNumbersTop" class="pagination-numbers"></span>
                                    <button class="btn-pagination btn-pagination-nav" id="coaNextTop" data-action="coa-next" title="Next Page">
                                        <i class="fas fa-angle-right"></i>
                                    </button>
                                    <button class="btn-pagination btn-pagination-nav" id="coaLastTop" data-action="coa-page" data-page="1" title="Last Page">
                                        <i class="fas fa-angle-double-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Bulk Actions Bar -->
                        <div class="bulk-actions-bar" id="bulkActionsCoa">
                            <span class="bulk-selected-count" id="bulkSelectedCountCoa">0 selected</span>
                            <div class="bulk-action-buttons">
                                <button class="btn btn-sm btn-danger" data-action="bulk-delete-coa" data-permission="delete_account">
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                                <button class="btn btn-sm btn-secondary" data-action="bulk-export-coa">
                                    <i class="fas fa-download"></i> Export Selected
                                </button>
                                <button class="btn btn-sm btn-secondary" data-action="bulk-activate-coa" data-permission="edit_account">
                                    <i class="fas fa-check"></i> Activate
                                </button>
                                <button class="btn btn-sm btn-secondary" data-action="bulk-deactivate-coa" data-permission="edit_account">
                                    <i class="fas fa-times"></i> Deactivate
                                </button>
                            </div>
                        </div>
                        <div class="data-table-container modal-table-wrapper modal-table-wrapper-no-scroll" id="modalCoaTableWrapper">
                            <table class="data-table modal-table-fixed" id="chartOfAccountsTable">
                                <thead>
                                    <tr>
                                        <th class="invoice-column" data-sort="account_code">
                                            Code <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="entity-name-column" data-sort="account_name">
                                            Name <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="entity-type-column" data-sort="account_type">
                                            Type <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="date-column" data-sort="normal_balance">
                                            Normal <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="amount-column debit-cell" data-sort="opening_balance">
                                            Opening <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="amount-column debit-cell" data-sort="current_balance">
                                            Balance <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="status-column" data-sort="is_active">
                                            Status <i class="fas fa-sort"></i>
                                        </th>
                                        <th class="checkbox-column">
                                            <input type="checkbox" id="coaSelectAll" data-action="coa-select-all" title="Select All">
                                        </th>
                                        <th class="actions-column">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="chartOfAccountsBody">
                                    <tr><td colspan="9" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        },

        openCostCentersModal() {
            const tableContent = `
                <div class="accounting-module-modal-content">
                    <div class="module-content">
                        <div class="module-header">
                            <div class="header-actions">
                                <button class="btn btn-primary" data-action="add-cost-center">
                                    <i class="fas fa-plus"></i> Add Cost Center
                                </button>
                                <button class="btn btn-secondary btn-hidden" data-action="delete-selected-cost-centers">
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                            </div>
                        </div>
                        <div id="costCentersStatusCards" class="report-status-cards">
                            <div class="stat-card stat-card-primary">
                                <i class="fas fa-building stat-icon stat-icon-primary"></i>
                                <div class="stat-info">
                                    <span class="stat-value" id="costCentersTotalCount">0</span>
                                    <span class="stat-label">Total Cost Centers</span>
                                </div>
                            </div>
                            <div class="stat-card stat-card-success">
                                <i class="fas fa-check-circle stat-icon stat-icon-success"></i>
                                <div class="stat-info">
                                    <span class="stat-value" id="costCentersActiveCount">0</span>
                                    <span class="stat-label">Active</span>
                                </div>
                            </div>
                            <div class="stat-card stat-card-warning">
                                <i class="fas fa-times-circle stat-icon stat-icon-warning"></i>
                                <div class="stat-info">
                                    <span class="stat-value" id="costCentersInactiveCount">0</span>
                                    <span class="stat-label">Inactive</span>
                                </div>
                            </div>
                        </div>
                        <div class="filters-and-pagination-container report-controls-container">
                            <div class="filters-bar filters-bar-compact">
                                <div class="filter-group filter-group-compact">
                                    <label><i class="fas fa-search"></i> Search:</label>
                                    <input type="text" id="costCentersSearch" class="filter-input filter-input-compact" placeholder="Search cost centers...">
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Status:</label>
                                    <select id="costCentersStatusFilter" class="filter-select filter-select-compact">
                                        <option value="">All Status</option>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Show:</label>
                                    <select id="costCentersPageSize" class="filter-select filter-select-compact">
                                        <option value="5" selected>5</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <button class="btn btn-primary btn-sm" id="costCentersApplyFilters">
                                        <i class="fas fa-filter"></i> Apply
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="table-container">
                            <table class="accounting-table" id="costCentersTable">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th><input type="checkbox" id="selectAllCostCenters"></th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="costCentersTableBody">
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <div class="loading-state">
                                                <i class="fas fa-spinner fa-spin"></i>
                                                <p>Loading cost centers...</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination-container">
                            <div class="pagination-info" id="costCentersPaginationInfo">
                                Showing <span id="costCentersShowingFrom">0</span> - <span id="costCentersShowingTo">0</span> of <span id="costCentersTotalCountDisplay">0</span> cost centers
                            </div>
                            <div class="pagination-controls" id="costCentersPaginationControls">
                                <button class="btn-pagination btn-pagination-prev" id="costCentersPrevBtn" disabled>
                                    <i class="fas fa-chevron-left"></i> <span>Previous</span>
                                </button>
                                <span class="pagination-page-info">
                                    Page <span id="costCentersCurrentPage">1</span> of <span id="costCentersTotalPages">1</span>
                                </span>
                                <button class="btn-pagination btn-pagination-next" id="costCentersNextBtn" disabled>
                                    <span>Next</span> <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            this.showModal('Cost Centers', tableContent, 'large', 'costCentersModal');
            // Initialize pagination
            this.costCentersCurrentPage = 1;
            this.costCentersPerPage = 5;
            this.costCentersSearchTerm = '';
            // Wait for modal to be fully rendered before loading data
            setTimeout(() => {
                this.loadCostCenters();
                this.setupCostCentersEventHandlers();
            }, 100);
        },

        openBankGuaranteeModal() {
            const today = this.formatDateForInput(new Date().toISOString().split('T')[0]);
            const firstDay = new Date();
            firstDay.setDate(1);
            const firstDayStr = this.formatDateForInput(firstDay.toISOString().split('T')[0]);
            
            const tableContent = `
                <div class="accounting-module-modal-content">
                    <div class="module-content">
                        <div class="module-header">
                            <div class="header-actions">
                                <button class="btn btn-primary" data-action="add-bank-guarantee">
                                    <i class="fas fa-plus"></i> Add Bank Guarantee
                                </button>
                                <button class="btn btn-secondary btn-hidden" data-action="delete-selected-bank-guarantees">
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                            </div>
                        </div>
                        <div id="bankGuaranteeStatusCards" class="report-status-cards">
                            <div class="stat-card stat-card-primary">
                                <i class="fas fa-shield-alt stat-icon stat-icon-primary"></i>
                                <div class="stat-info">
                                    <span class="stat-value" id="bankGuaranteeTotalCount">0</span>
                                    <span class="stat-label">Total Guarantees</span>
                                </div>
                            </div>
                            <div class="stat-card stat-card-success">
                                <i class="fas fa-check-circle stat-icon stat-icon-success"></i>
                                <div class="stat-info">
                                    <span class="stat-value" id="bankGuaranteeActiveCount">0</span>
                                    <span class="stat-label">Active</span>
                                </div>
                            </div>
                            <div class="stat-card stat-card-warning">
                                <i class="fas fa-exclamation-triangle stat-icon stat-icon-warning"></i>
                                <div class="stat-info">
                                    <span class="stat-value" id="bankGuaranteeExpiredCount">0</span>
                                    <span class="stat-label">Expired</span>
                                </div>
                            </div>
                            <div class="stat-card stat-card-info">
                                <i class="fas fa-dollar-sign stat-icon stat-icon-info"></i>
                                <div class="stat-info">
                                    <span class="stat-value" id="bankGuaranteeTotalAmount">SAR 0.00</span>
                                    <span class="stat-label">Total Amount</span>
                                </div>
                            </div>
                        </div>
                        <div class="filters-and-pagination-container report-controls-container">
                            <div class="filters-bar filters-bar-compact">
                                <div class="filter-group filter-group-compact">
                                    <label>Date From:</label>
                                    <input type="text" id="bankGuaranteeDateFrom" class="filter-input filter-input-compact date-input" value="${firstDayStr}" placeholder="MM/DD/YYYY">
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Date To:</label>
                                    <input type="text" id="bankGuaranteeDateTo" class="filter-input filter-input-compact date-input" value="${today}" placeholder="MM/DD/YYYY">
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label><i class="fas fa-search"></i> Search:</label>
                                    <input type="text" id="bankGuaranteeSearch" class="filter-input filter-input-compact" placeholder="Search guarantees...">
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Status:</label>
                                    <select id="bankGuaranteeStatusFilter" class="filter-select filter-select-compact">
                                        <option value="">All Status</option>
                                        <option value="active">Active</option>
                                        <option value="expired">Expired</option>
                                        <option value="pending">Pending</option>
                                    </select>
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Show:</label>
                                    <select id="bankGuaranteePageSize" class="filter-select filter-select-compact">
                                        <option value="5" selected>5</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="table-container">
                            <table class="accounting-table" id="bankGuaranteeTable">
                                <thead>
                                    <tr>
                                        <th>Reference Number</th>
                                        <th>Bank Name</th>
                                        <th>Amount</th>
                                        <th>Issue Date</th>
                                        <th>Expiry Date</th>
                                        <th>Status</th>
                                        <th><input type="checkbox" id="selectAllBankGuarantees"></th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="bankGuaranteeTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center">
                                            <div class="loading-state">
                                                <i class="fas fa-spinner fa-spin"></i>
                                                <p>Loading bank guarantees...</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination-container">
                            <div class="pagination-info" id="bankGuaranteePaginationInfo">
                                Showing <span id="bankGuaranteeShowingFrom">0</span> - <span id="bankGuaranteeShowingTo">0</span> of <span id="bankGuaranteeTotalCountDisplay">0</span> guarantees
                            </div>
                            <div class="pagination-controls" id="bankGuaranteePaginationControls">
                                <button class="btn-pagination btn-pagination-prev" id="bankGuaranteePrevBtn" disabled>
                                    <i class="fas fa-chevron-left"></i> <span>Previous</span>
                                </button>
                                <span class="pagination-page-info">
                                    Page <span id="bankGuaranteeCurrentPage">1</span> of <span id="bankGuaranteeTotalPages">1</span>
                                </span>
                                <button class="btn-pagination btn-pagination-next" id="bankGuaranteeNextBtn" disabled>
                                    <span>Next</span> <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            this.showModal('Letters of Bank Guarantee', tableContent, 'large', 'bankGuaranteeModal');
            // Initialize pagination
            this.bankGuaranteeCurrentPage = 1;
            this.bankGuaranteePerPage = 5;
            this.bankGuaranteeSearchTerm = '';
            // Wait for modal to be fully rendered before loading data
            setTimeout(() => {
                this.loadBankGuarantees();
                this.setupBankGuaranteesEventHandlers();
            }, 100);
        },

        openEntryApprovalModal() {
            const today = this.formatDateForInput(new Date().toISOString().split('T')[0]);
            const firstDay = new Date();
            firstDay.setDate(1);
            const firstDayStr = this.formatDateForInput(firstDay.toISOString().split('T')[0]);
            
            const tableContent = `
                <div class="accounting-module-modal-content">
                    <div class="module-content">
                        <div class="module-header">
                            <div class="header-actions">
                                <button class="btn btn-success" data-action="approve-selected">
                                    <i class="fas fa-check"></i> Approve Selected
                                </button>
                                <button class="btn btn-danger" data-action="reject-selected">
                                    <i class="fas fa-times"></i> Reject Selected
                                </button>
                            </div>
                        </div>
                        <div id="entryApprovalStatusCards" class="report-status-cards">
                            <div class="stat-card stat-card-primary">
                                <i class="fas fa-list stat-icon stat-icon-primary"></i>
                                <div class="stat-info">
                                    <span class="stat-value" id="entryApprovalTotalCount">0</span>
                                    <span class="stat-label">Total Entries</span>
                                </div>
                            </div>
                            <div class="stat-card stat-card-warning">
                                <i class="fas fa-clock stat-icon stat-icon-warning"></i>
                                <div class="stat-info">
                                    <span class="stat-value" id="entryApprovalPendingCount">0</span>
                                    <span class="stat-label">Pending</span>
                                </div>
                            </div>
                            <div class="stat-card stat-card-danger">
                                <i class="fas fa-times-circle stat-icon stat-icon-danger"></i>
                                <div class="stat-info">
                                    <span class="stat-value" id="entryApprovalRejectedCount">0</span>
                                    <span class="stat-label">Rejected</span>
                                </div>
                            </div>
                            <div class="stat-card stat-card-success">
                                <i class="fas fa-check-circle stat-icon stat-icon-success"></i>
                                <div class="stat-info">
                                    <span class="stat-value" id="entryApprovalApprovedCount">0</span>
                                    <span class="stat-label">Approved</span>
                                </div>
                            </div>
                        </div>
                        <div class="filters-and-pagination-container report-controls-container">
                            <div class="filters-bar filters-bar-compact">
                                <div class="filter-group filter-group-compact">
                                    <label>Date From:</label>
                                    <input type="text" id="entryApprovalDateFrom" class="filter-input filter-input-compact date-input" value="${firstDayStr}" placeholder="MM/DD/YYYY">
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Date To:</label>
                                    <input type="text" id="entryApprovalDateTo" class="filter-input filter-input-compact date-input" value="${today}" placeholder="MM/DD/YYYY">
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label><i class="fas fa-search"></i> Search:</label>
                                    <input type="text" id="entryApprovalSearch" class="filter-input filter-input-compact" placeholder="Search entries...">
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Status:</label>
                                    <select id="entryApprovalStatusFilter" class="filter-select filter-select-compact">
                                        <option value="all" selected>All Entries</option>
                                        <option value="pending">Pending</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Show:</label>
                                    <select id="entryApprovalPageSize" class="filter-select filter-select-compact">
                                        <option value="5" selected>5</option>
                                        <option value="10">10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <button class="btn btn-primary btn-sm" id="entryApprovalApplyFilters">
                                        <i class="fas fa-filter"></i> Apply
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="data-table-container modal-table-wrapper modal-table-wrapper-no-scroll" id="entryApprovalTableWrapper">
                            <table class="data-table modal-table-fixed professional-ledger-table" id="entryApprovalTable">
                                <thead>
                                    <tr>
                                        <th class="voucher-number-column">
                                            <div class="entry-approval-header-with-checkbox">
                                                <input type="checkbox" id="selectAllEntries" title="Select all">
                                                <span>Entry Number</span>
                                            </div>
                                        </th>
                                        <th class="date-column">Journal Date</th>
                                        <th class="amount-column debit-header">Total Debit</th>
                                        <th class="amount-column credit-header">Total Credit</th>
                                        <th class="account-column">Debit Account</th>
                                        <th class="account-column">Credit Account</th>
                                        <th class="description-column">Description</th>
                                        <th class="actions-column">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="entryApprovalTableBody">
                                    <tr>
                                        <td colspan="8" class="text-center">
                                            <div class="loading-state">
                                                <i class="fas fa-spinner fa-spin"></i>
                                                <p>Loading entries...</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="pagination-container">
                            <div class="pagination-info" id="entryApprovalPaginationInfo">
                                Showing <span id="entryApprovalShowingFrom">0</span> - <span id="entryApprovalShowingTo">0</span> of <span id="entryApprovalTotalCountDisplay">0</span> entries
                            </div>
                            <div class="pagination-controls" id="entryApprovalPaginationControls">
                                <button class="btn-pagination btn-pagination-prev" id="entryApprovalPrevBtn" disabled>
                                    <i class="fas fa-chevron-left"></i> <span>Previous</span>
                                </button>
                                <span class="pagination-page-info">
                                    Page <span id="entryApprovalCurrentPage">1</span> of <span id="entryApprovalTotalPages">1</span>
                                </span>
                                <button class="btn-pagination btn-pagination-next" id="entryApprovalNextBtn" disabled>
                                    <span>Next</span> <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            this.showModal('Entry Approval', tableContent, 'large', 'entryApprovalModal');
            // Initialize pagination
            this.entryApprovalCurrentPage = 1;
            this.entryApprovalPerPage = 5;
            this.entryApprovalSearchTerm = '';
            // Wait for modal to be fully rendered before loading data
            setTimeout(() => {
                this.loadEntryApproval();
                this.setupEntryApprovalHandlers();
            }, 100);
        },

        createJournalEntryLineRow(index, side) {
            const sideClass = side === 'debit' ? 'debit' : 'credit';
            const amountClass = side === 'debit' ? 'debit-amount' : 'credit-amount';
            const actionDataSide = side === 'debit' ? 'add-debit-line' : 'add-credit-line';
            
            return `
                <tr class="ledger-line-row" data-line-index="${index}">
                    <td class="account-cell">
                        <select name="${side}_lines[${index}][account_id]" class="account-select" required>
                            <option value="">Select</option>
                        </select>
                    </td>
                    <td class="cost-center-cell">
                        <select name="${side}_lines[${index}][cost_center_id]" class="cost-center-select">
                            <option value="">- Main Center</option>
                        </select>
                    </td>
                    <td class="description-cell">
                        <input type="text" name="${side}_lines[${index}][description]" class="line-description" placeholder="Description">
                    </td>
                    <td class="vat-cell">
                        <input type="checkbox" name="${side}_lines[${index}][vat_report]" class="vat-checkbox">
                    </td>
                    <td class="amount-cell">
                        <input type="number" name="${side}_lines[${index}][amount]" class="line-amount ${amountClass}" step="0.01" min="0" placeholder="0.00">
                    </td>
                    <td class="actions-cell">
                        <button type="button" class="btn-add-line" data-side="${side}" data-action="${actionDataSide}" title="Add Line">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" class="btn-remove-line" data-action="remove-line" title="Remove Line">
                            <i class="fas fa-minus"></i>
                        </button>
                    </td>
                </tr>
            `;
        },

        async populateJournalEntryEditForm(entry, lines = []) {
            const form = document.getElementById('journalEntryForm');
            if (!form) return;
            const entryDateInput = form.querySelector('#journalEntryDate') || form.querySelector('input[name="entry_date"]');
            const descriptionInput = form.querySelector('textarea[name="description"]');
            const branchSelect = form.querySelector('#journalBranchSelect');
            if (entryDateInput && entry?.entry_date) entryDateInput.value = entry.entry_date;
            if (descriptionInput) descriptionInput.value = entry?.description || '';
            if (branchSelect) {
                const branchId = entry?.branch_id ? String(entry.branch_id) : (branchSelect.value || '');
                if (branchId) {
                    const exists = Array.from(branchSelect.options).some(o => o.value === branchId);
                    if (!exists) {
                        const opt = document.createElement('option');
                        opt.value = branchId;
                        opt.textContent = branchId === '1' ? 'Main Branch' : `Branch #${branchId}`;
                        branchSelect.appendChild(opt);
                    }
                    branchSelect.value = branchId;
                }
            }
            const debitLines = [];
            const creditLines = [];
            (Array.isArray(lines) ? lines : []).forEach((ln) => {
                if (!ln || typeof ln !== 'object') return;
                const accountId = parseInt(ln.account_id || 0);
                const costCenterId = ln.cost_center_id ? parseInt(ln.cost_center_id) : 0;
                const desc = (ln.description || '').toString();
                const vat = !!ln.vat_report;
                const debitAmt = parseFloat(ln.debit_amount || 0);
                const creditAmt = parseFloat(ln.credit_amount || 0);
                if (accountId > 0 && debitAmt > 0) debitLines.push({ account_id: accountId, cost_center_id: costCenterId, description: desc, vat_report: vat, amount: debitAmt });
                if (accountId > 0 && creditAmt > 0) creditLines.push({ account_id: accountId, cost_center_id: costCenterId, description: desc, vat_report: vat, amount: creditAmt });
            });
            if (debitLines.length === 0) debitLines.push({ account_id: 0, cost_center_id: 0, description: '', vat_report: false, amount: 0 });
            if (creditLines.length === 0) creditLines.push({ account_id: 0, cost_center_id: 0, description: '', vat_report: false, amount: 0 });
            const debitTbody = document.getElementById('journalDebitLinesBody');
            const creditTbody = document.getElementById('journalCreditLinesBody');
            if (!debitTbody || !creditTbody) return;
            debitTbody.innerHTML = '';
            creditTbody.innerHTML = '';
            debitLines.forEach((_, idx) => debitTbody.insertAdjacentHTML('beforeend', this.createJournalEntryLineRow(idx, 'debit')));
            creditLines.forEach((_, idx) => creditTbody.insertAdjacentHTML('beforeend', this.createJournalEntryLineRow(idx, 'credit')));
            const fillRow = async (rowEl, ln, side) => {
                if (!rowEl || !ln) return;
                const accountSelect = rowEl.querySelector('.account-select');
                const costCenterSelect = rowEl.querySelector('.cost-center-select');
                const descInput = rowEl.querySelector('.line-description');
                const vatCb = rowEl.querySelector('.vat-checkbox');
                const amtInput = rowEl.querySelector(side === 'debit' ? '.debit-amount' : '.credit-amount');
                if (accountSelect) {
                    await this.loadAccountsForSelect(null, accountSelect);
                    accountSelect.value = ln.account_id ? String(ln.account_id) : '';
                }
                if (costCenterSelect) {
                    await this.populateCostCenterSelect(costCenterSelect);
                    costCenterSelect.value = ln.cost_center_id ? String(ln.cost_center_id) : '';
                }
                if (descInput) descInput.value = ln.description || '';
                if (vatCb) vatCb.checked = !!ln.vat_report;
                if (amtInput) amtInput.value = ln.amount && ln.amount > 0 ? Number(ln.amount).toFixed(2) : '';
            };
            const debitRows = Array.from(debitTbody.querySelectorAll('.ledger-line-row'));
            for (let i = 0; i < debitRows.length; i++) await fillRow(debitRows[i], debitLines[i], 'debit');
            const creditRows = Array.from(creditTbody.querySelectorAll('.ledger-line-row'));
            for (let i = 0; i < creditRows.length; i++) await fillRow(creditRows[i], creditLines[i], 'credit');
            const updateRemoveButtons = (tbody) => {
                const rows = tbody.querySelectorAll('.ledger-line-row');
                rows.forEach((r) => {
                    const removeBtn = r.querySelector('.btn-remove-line');
                    if (removeBtn) removeBtn.style.display = rows.length > 1 ? 'inline-flex' : 'none';
                });
            };
            updateRemoveButtons(debitTbody);
            updateRemoveButtons(creditTbody);
            setTimeout(() => {
                form.querySelectorAll('.debit-amount, .credit-amount').forEach((inp) => inp.dispatchEvent(new Event('input', { bubbles: true })));
            }, 50);
        },

        setupCostCentersEventHandlers() {
            const modal = document.getElementById('costCentersModal');
            if (!modal) return;
            // Select all checkbox
            const selectAll = modal.querySelector('#selectAllCostCenters');
            if (selectAll) {
                selectAll.addEventListener('change', (e) => {
                    const checkboxes = modal.querySelectorAll('.cost-center-checkbox');
                    checkboxes.forEach(cb => cb.checked = e.target.checked);
                    this.toggleDeleteButton(modal, '.cost-center-checkbox', '[data-action="delete-selected-cost-centers"]');
                });
            }
            // Individual checkboxes
            modal.querySelectorAll('.cost-center-checkbox').forEach(cb => {
                cb.addEventListener('change', () => {
                    this.toggleDeleteButton(modal, '.cost-center-checkbox', '[data-action="delete-selected-cost-centers"]');
                    const allChecked = Array.from(modal.querySelectorAll('.cost-center-checkbox')).every(c => c.checked);
                    if (selectAll) selectAll.checked = allChecked;
                });
            });
            // Add button
            const addBtn = modal.querySelector('[data-action="add-cost-center"]');
            if (addBtn) {
                addBtn.addEventListener('click', () => this.openCostCenterForm());
            }
            // Edit buttons
            modal.querySelectorAll('[data-action="edit-cost-center"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    this.openCostCenterForm(id);
                });
            });
            // Delete buttons
            modal.querySelectorAll('[data-action="delete-cost-center"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    this.deleteCostCenter(id);
                });
            });
            // Delete selected
            const deleteSelectedBtn = modal.querySelector('[data-action="delete-selected-cost-centers"]');
            if (deleteSelectedBtn) {
                deleteSelectedBtn.addEventListener('click', () => this.deleteSelectedCostCenters());
            }
            // Search input handler
            const searchInput = document.getElementById('costCentersSearch');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    this.costCentersSearchTerm = e.target.value.trim();
                    searchTimeout = setTimeout(() => {
                        this.costCentersCurrentPage = 1;
                        this.renderCostCentersTable();
                    }, 300);
                });
            }
            // Status filter handler
            const statusFilter = document.getElementById('costCentersStatusFilter');
            if (statusFilter) {
                statusFilter.addEventListener('change', () => {
                    this.costCentersCurrentPage = 1;
                    this.renderCostCentersTable();
                });
            }
            // Page size handler
            const pageSize = document.getElementById('costCentersPageSize');
            if (pageSize) {
                pageSize.addEventListener('change', (e) => {
                    this.costCentersPerPage = parseInt(e.target.value);
                    this.costCentersCurrentPage = 1;
                    this.renderCostCentersTable();
                });
            }
            // Apply filters button
            const applyFiltersBtn = document.getElementById('costCentersApplyFilters');
            if (applyFiltersBtn) {
                applyFiltersBtn.addEventListener('click', () => {
                    this.costCentersCurrentPage = 1;
                    this.renderCostCentersTable();
                });
            }
            // Pagination handlers
            const prevBtn = document.getElementById('costCentersPrevBtn');
            const nextBtn = document.getElementById('costCentersNextBtn');
            
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    if (this.costCentersCurrentPage > 1) {
                        this.costCentersCurrentPage--;
                        this.renderCostCentersTable();
                    }
                });
            }
            
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    if (this.costCentersCurrentPage < this.costCentersTotalPages) {
                        this.costCentersCurrentPage++;
                        this.renderCostCentersTable();
                    }
                });
            }
        },

        setupBankGuaranteesEventHandlers() {
            const modal = document.getElementById('bankGuaranteeModal');
            if (!modal) return;
            // Select all checkbox
            const selectAll = modal.querySelector('#selectAllBankGuarantees');
            if (selectAll) {
                selectAll.addEventListener('change', (e) => {
                    const checkboxes = modal.querySelectorAll('.bank-guarantee-checkbox');
                    checkboxes.forEach(cb => cb.checked = e.target.checked);
                    this.toggleDeleteButton(modal, '.bank-guarantee-checkbox', '[data-action="delete-selected-bank-guarantees"]');
                });
            }
            // Individual checkboxes
            modal.querySelectorAll('.bank-guarantee-checkbox').forEach(cb => {
                cb.addEventListener('change', () => {
                    this.toggleDeleteButton(modal, '.bank-guarantee-checkbox', '[data-action="delete-selected-bank-guarantees"]');
                    const allChecked = Array.from(modal.querySelectorAll('.bank-guarantee-checkbox')).every(c => c.checked);
                    if (selectAll) selectAll.checked = allChecked;
                });
            });
            // Add button
            const addBtn = modal.querySelector('[data-action="add-bank-guarantee"]');
            if (addBtn) {
                addBtn.addEventListener('click', () => this.openBankGuaranteeForm());
            }
            // Edit buttons
            modal.querySelectorAll('[data-action="edit-bank-guarantee"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    this.openBankGuaranteeForm(id);
                });
            });
            // Delete buttons
            modal.querySelectorAll('[data-action="delete-bank-guarantee"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    this.deleteBankGuarantee(id);
                });
            });
            // Delete selected
            const deleteSelectedBtn = modal.querySelector('[data-action="delete-selected-bank-guarantees"]');
            if (deleteSelectedBtn) {
                deleteSelectedBtn.addEventListener('click', () => this.deleteSelectedBankGuarantees());
            }
            // Search input handler
            const searchInput = document.getElementById('bankGuaranteeSearch');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    this.bankGuaranteeSearchTerm = e.target.value.trim();
                    searchTimeout = setTimeout(() => {
                        this.bankGuaranteeCurrentPage = 1;
                        this.renderBankGuaranteeTable();
                    }, 300);
                });
            }
            // Status filter handler
            const statusFilter = document.getElementById('bankGuaranteeStatusFilter');
            if (statusFilter) {
                statusFilter.addEventListener('change', () => {
                    this.bankGuaranteeCurrentPage = 1;
                    this.renderBankGuaranteeTable();
                });
            }
            // Date filter handlers
            const dateFrom = document.getElementById('bankGuaranteeDateFrom');
            const dateTo = document.getElementById('bankGuaranteeDateTo');
            if (dateFrom) {
                dateFrom.addEventListener('change', () => {
                    this.bankGuaranteeCurrentPage = 1;
                    this.loadBankGuarantees();
                });
            }
            if (dateTo) {
                dateTo.addEventListener('change', () => {
                    this.bankGuaranteeCurrentPage = 1;
                    this.loadBankGuarantees();
                });
            }
            // Page size handler
            const pageSize = document.getElementById('bankGuaranteePageSize');
            if (pageSize) {
                pageSize.addEventListener('change', (e) => {
                    this.bankGuaranteePerPage = parseInt(e.target.value);
                    this.bankGuaranteeCurrentPage = 1;
                    this.renderBankGuaranteeTable();
                });
            }
            // Filters now auto-apply on change - Apply button removed
            // Pagination handlers
            const prevBtn = document.getElementById('bankGuaranteePrevBtn');
            const nextBtn = document.getElementById('bankGuaranteeNextBtn');
            
            if (prevBtn) {
                prevBtn.addEventListener('click', () => {
                    if (this.bankGuaranteeCurrentPage > 1) {
                        this.bankGuaranteeCurrentPage--;
                        this.renderBankGuaranteeTable();
                    }
                });
            }
            
            if (nextBtn) {
                nextBtn.addEventListener('click', () => {
                    if (this.bankGuaranteeCurrentPage < this.bankGuaranteeTotalPages) {
                        this.bankGuaranteeCurrentPage++;
                        this.renderBankGuaranteeTable();
                    }
                });
            }
        },

        setupEntryApprovalEventHandlers() {
            const modal = document.getElementById('entryApprovalModal');
            if (!modal) return;
            // Select all checkbox
            const selectAll = modal.querySelector('#selectAllEntries');
            if (selectAll) {
                selectAll.addEventListener('change', (e) => {
                    const checkboxes = modal.querySelectorAll('.entry-checkbox:not(:disabled)');
                    checkboxes.forEach(cb => cb.checked = e.target.checked);
                });
            }
            // Individual checkboxes
            modal.querySelectorAll('.entry-checkbox').forEach(cb => {
                cb.addEventListener('change', () => {
                    const allChecked = Array.from(modal.querySelectorAll('.entry-checkbox:not(:disabled)')).every(c => c.checked);
                    if (selectAll) selectAll.checked = allChecked;
                });
            });
            // Approve selected
            const approveBtn = modal.querySelector('[data-action="approve-selected"]');
            if (approveBtn) {
                approveBtn.addEventListener('click', () => this.approveSelectedEntries());
            }
            // Reject selected
            const rejectBtn = modal.querySelector('[data-action="reject-selected"]');
            if (rejectBtn) {
                rejectBtn.addEventListener('click', () => this.rejectSelectedEntries());
            }
            // Status filter dropdown
            // Status filter handler is now in setupEntryApprovalHandlers() to avoid duplicates
            // Individual approve/reject buttons
            modal.querySelectorAll('[data-action="approve-entry"]').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const id = parseInt(e.target.closest('button').dataset.id);
                    const confirmed = await this.showConfirmDialog(
                        'Approve Entry',
                        'Are you sure you want to approve this entry?',
                        'Approve',
                        'Cancel',
                        'success'
                    );
                    if (confirmed) {
                        await this.approveEntries([id]);
                    }
                });
            });
            modal.querySelectorAll('[data-action="reject-entry"]').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const id = parseInt(e.target.closest('button').dataset.id);
                    const confirmed = await this.showConfirmDialog(
                        'Reject Entry',
                        'Are you sure you want to reject this entry?',
                        'Continue',
                        'Cancel',
                        'warning'
                    );
                    if (confirmed) {
                        const reason = await this.showPrompt(
                            'Rejection Reason',
                            'Please enter the reason for rejecting this entry:',
                            '',
                            'Enter rejection reason...',
                            'text'
                        );
                        if (reason && reason.trim()) {
                            await this.rejectEntries([id], reason.trim());
                        } else if (reason !== null) {
                            this.showToast('Rejection reason is required', 'error');
                        }
                    }
                });
            });
            // View buttons
            modal.querySelectorAll('[data-action="view-entry"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    const target = e.target.closest('[data-action="view-entry"]');
                    const id = target ? parseInt(target.dataset.id) : NaN;
                    if (!id || Number.isNaN(id)) return;
                    this.openEntryDetailsModal(id);
                });
            });
            // Edit buttons
            modal.querySelectorAll('[data-action="edit-entry-approval"]').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const id = parseInt(e.target.closest('button').dataset.id);
                    const confirmed = await this.showConfirmDialog(
                        'Edit Entry',
                        'Are you sure you want to edit this entry?',
                        'Edit',
                        'Cancel',
                        'info'
                    );
                    if (confirmed) {
                        this.openEntryApprovalForm(id);
                    }
                });
            });
            // Delete buttons
            modal.querySelectorAll('[data-action="delete-entry-approval"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const id = parseInt(e.target.closest('button').dataset.id);
                    this.deleteEntryApproval(id);
                });
            });
        },

        openEntryApprovalForm(id) {
            this.loadEntryApprovalData(id);
        },

        toggleDeleteButton(modal, checkboxSelector, buttonSelector) {
            const checkboxes = modal.querySelectorAll(checkboxSelector);
            const deleteBtn = modal.querySelector(buttonSelector);
            if (deleteBtn) {
                const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
                if (anyChecked) {
                    deleteBtn.classList.add('btn-visible');
                    deleteBtn.classList.remove('btn-hidden');
                } else {
                    deleteBtn.classList.add('btn-hidden');
                    deleteBtn.classList.remove('btn-visible');
                }
            }
        },

        openCostCenterForm(id = null) {
            const isEdit = id !== null;
            const formContent = `
                <form id="costCenterForm">
                    <div class="accounting-modal-form-group">
                        <label for="costCenterCode">Code <span class="required">*</span></label>
                        <input type="text" id="costCenterCode" name="code" class="form-control" required ${isEdit ? '' : 'readonly'} placeholder="Auto-generated" title="${isEdit ? 'Cost center code' : 'Cost center code is auto-generated'}">
                        ${isEdit ? '' : '<small class="form-help-text">Code will be auto-generated</small>'}
                    </div>
                    <div class="accounting-modal-form-group">
                        <label for="costCenterName">Name <span class="required">*</span></label>
                        <input type="text" id="costCenterName" name="name" class="form-control" required>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label for="costCenterDescription">Description</label>
                        <textarea id="costCenterDescription" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <!-- Status field hidden - managed automatically by system -->
                    <input type="hidden" id="costCenterStatus" name="status" value="active">
                    <div class="accounting-modal-actions">
                        <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Cost Center</button>
                        <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                    </div>
                </form>
            `;
            this.showModal(isEdit ? 'Edit Cost Center' : 'Add Cost Center', formContent, 'normal', 'costCenterFormModal');
            
            if (isEdit) {
                this.loadCostCenterData(id);
            } else {
                // Generate code for new cost center
                setTimeout(async () => {
                    const codeInput = document.getElementById('costCenterCode');
                    if (codeInput) {
                        await this.generateCostCenterCode(codeInput);
                    }
                }, 150);
            }
            
            // Setup form submit
            setTimeout(() => {
                const form = document.getElementById('costCenterForm');
                if (form) {
                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        await this.saveCostCenter(id);
                    });
                }
            }, 100);
        },

        openBankGuaranteeForm(id = null) {
            const isEdit = id !== null;
            const formContent = `
                <form id="bankGuaranteeForm">
                    <div class="accounting-modal-form-group">
                        <label for="bgReferenceNumber">Reference Number <span class="required">*</span></label>
                        <input type="text" id="bgReferenceNumber" name="reference_number" class="form-control" required>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label for="bgBankName">Bank Name <span class="required">*</span></label>
                        <input type="text" id="bgBankName" name="bank_name" class="form-control" required>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label for="bgAmount">Amount <span class="required">*</span></label>
                        <input type="number" id="bgAmount" name="amount" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label for="bgCurrency">Currency</label>
                        <select id="bgCurrency" name="currency" class="form-control">
                            <option value="">Loading currencies...</option>
                        </select>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label for="bgIssueDate">Issue Date <span class="required">*</span></label>
                        <input type="text" id="bgIssueDate" name="issue_date" class="form-control date-input" required placeholder="MM/DD/YYYY">
                    </div>
                    <div class="accounting-modal-form-group">
                        <label for="bgExpiryDate">Expiry Date</label>
                        <input type="text" id="bgExpiryDate" name="expiry_date" class="form-control date-input" placeholder="MM/DD/YYYY">
                    </div>
                    <!-- Status field hidden - managed automatically by system -->
                    <input type="hidden" id="bgStatus" name="status" value="active">
                    <div class="accounting-modal-form-group">
                        <label for="bgDescription">Description</label>
                        <textarea id="bgDescription" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="accounting-modal-actions">
                        <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Bank Guarantee</button>
                        <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                    </div>
                </form>
            `;
            this.showModal(isEdit ? 'Edit Bank Guarantee' : 'Add Bank Guarantee', formContent, 'normal', 'bankGuaranteeFormModal');
            
            // Populate currency dropdown
            setTimeout(async () => {
                const currencySelect = document.getElementById('bgCurrency');
                if (currencySelect && window.currencyUtils) {
                    try {
                        const defaultCurrency = this.getDefaultCurrencySync();
                        await window.currencyUtils.populateCurrencySelect(currencySelect, isEdit ? null : defaultCurrency);
                    } catch (error) {
                        console.error('Error populating bank guarantee currency:', error);
                    }
                }
            }, 150);
            
            if (isEdit) {
                this.loadBankGuaranteeData(id);
            } else {
                const bgIssueDateEl = document.getElementById('bgIssueDate');
                if (bgIssueDateEl) bgIssueDateEl.value = this.formatDateForInput(new Date().toISOString());
            }
            
            setTimeout(() => {
                const form = document.getElementById('bankGuaranteeForm');
                if (form) {
                    form.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        await this.saveBankGuarantee(id);
                    });
                }
            }, 100);
        },

        setupCustomerFields(containerId, editId = null) {
            const container = document.getElementById(containerId);
            if (!container) return;
            // If editing, load existing customers
            if (editId) {
                // This will be handled when form data is loaded
                // For now, we'll keep the single field
            }
            // Handle add customer button clicks
            container.addEventListener('click', (e) => {
                if (e.target.closest('.btn-add-customer')) {
                    e.preventDefault();
                    const currentRow = e.target.closest('.customer-input-row');
                    const index = parseInt(currentRow.getAttribute('data-customer-index')) + 1;
                    
                    // Hide the add button on current row
                    const addBtn = currentRow.querySelector('.btn-add-customer');
                    if (addBtn) addBtn.style.display = 'none';
                    
                    // Create new row
                    const newRow = document.createElement('div');
                    newRow.className = 'customer-input-row';
                    newRow.setAttribute('data-customer-index', index);
                    newRow.innerHTML = `
                        <input type="text" name="customers[]" class="customer-name-input" placeholder="Enter customer name">
                        <button type="button" class="btn-add-customer" data-action="add-customer" title="Add Customer">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button type="button" class="btn-remove-customer" data-action="remove-customer" title="Remove Customer">
                            <i class="fas fa-minus"></i>
                        </button>
                    `;
                    
                    container.appendChild(newRow);
                }
                
                // Handle remove customer button clicks
                if (e.target.closest('.btn-remove-customer')) {
                    e.preventDefault();
                    const rowToRemove = e.target.closest('.customer-input-row');
                    const allRows = container.querySelectorAll('.customer-input-row');
                    
                    if (allRows.length > 1) {
                        rowToRemove.remove();
                        
                        // Show add button on last remaining row if it was hidden
                        const remainingRows = container.querySelectorAll('.customer-input-row');
                        if (remainingRows.length > 0) {
                            const lastRow = remainingRows[remainingRows.length - 1];
                            const addBtn = lastRow.querySelector('.btn-add-customer');
                            if (addBtn) addBtn.style.display = 'inline-block';
                        }
                    }
                }
            });
        },

        setupEntityCascadingDropdowns(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            const agentSelect = form.querySelector('[name="agent_id"]');
            const subagentSelect = form.querySelector('[name="subagent_id"]');
            const workerSelect = form.querySelector('[name="worker_id"]');
            // When agent changes, reload subagents and clear workers
            if (agentSelect && subagentSelect) {
                // Remove existing listeners by cloning
                const newAgentSelect = agentSelect.cloneNode(true);
                agentSelect.parentNode.replaceChild(newAgentSelect, agentSelect);
                
                newAgentSelect.addEventListener('change', async (e) => {
                    const agentId = e.target.value;
                    if (subagentSelect) {
                        subagentSelect.innerHTML = '<option value="">Select Subagent</option>';
                    }
                    if (workerSelect) {
                        workerSelect.innerHTML = '<option value="">Select Worker</option>';
                    }
                    
                    if (agentId && subagentSelect) {
                        await this.loadEntitiesForSelect('subagent', subagentSelect, agentId, null);
                    } else if (subagentSelect) {
                        await this.loadEntitiesForSelect('subagent', subagentSelect, null, null);
                    }
                });
            }
            // When subagent changes, reload workers
            if (subagentSelect && workerSelect) {
                // Remove existing listeners by cloning
                const newSubagentSelect = subagentSelect.cloneNode(true);
                subagentSelect.parentNode.replaceChild(newSubagentSelect, subagentSelect);
                
                newSubagentSelect.addEventListener('change', async (e) => {
                    const subagentId = e.target.value;
                    if (workerSelect) {
                        workerSelect.innerHTML = '<option value="">Select Worker</option>';
                    }
                    
                    if (subagentId && workerSelect) {
                        await this.loadEntitiesForSelect('worker', workerSelect, null, subagentId);
                    } else if (workerSelect) {
                        await this.loadEntitiesForSelect('worker', workerSelect, null, null);
                    }
                });
            }
        },

        normalizeEntityTypeForModal(entityType) {
            if (!entityType) return '';
            const normalized = entityType.toLowerCase();
            const entityTypeMap = {
                'agents': 'agent',
                'subagents': 'subagent',
                'workers': 'worker',
                'hr': 'hr'
            };
            return entityTypeMap[normalized] || normalized;
        },

        openTransactionsModal() {
            const modal = document.getElementById('transactionsModal');
            if (modal) {
                modal.classList.remove('accounting-modal-hidden');
                modal.classList.add('accounting-modal-visible');
                this.transactionsCurrentPage = 1;
                
                // Reset filters to defaults
                const entriesPerPage = document.getElementById('entriesPerPage');
                const entityTypeFilter = document.getElementById('modalEntityTypeFilter');
                const entityFilter = document.getElementById('modalEntityFilter');
                
                if (entriesPerPage) entriesPerPage.value = '5';
                if (entityTypeFilter) entityTypeFilter.value = '';
                
                // Clear entity filter dropdown completely
                if (entityFilter) {
                    entityFilter.innerHTML = '<option value="">All</option>';
                    entityFilter.value = '';
                }
                
                this.transactionsPerPage = 5;
                this.loadModalTransactions();
                
                // Load entities after a short delay to ensure modal is fully rendered
                setTimeout(() => {
                    this.loadEntitiesForModal();
                }, 100);
            }
        },

        closeTransactionsModal() {
            const modal = document.getElementById('transactionsModal');
            if (modal) {
                modal.classList.add('accounting-modal-hidden');
                modal.classList.remove('accounting-modal-visible');
            }
        },

        ensureModalsExist() {
            let followupModal = document.getElementById('followupModal');
            if (!followupModal) {
                followupModal = this.createFollowupModal();
                document.body.appendChild(followupModal);
            }
            let messagesModal = document.getElementById('messagesModal');
            if (!messagesModal) {
                messagesModal = this.createMessagesModal();
                document.body.appendChild(messagesModal);
            }
        },

        createFollowupModal() {
            const modal = document.createElement('div');
            modal.id = 'followupModal';
            modal.className = 'accounting-modal accounting-modal-overlay accounting-modal-hidden';
            modal.innerHTML = `
                <div class="accounting-modal-content followup-modal">
                    <div class="modal-header">
                        <h2><i class="fas fa-tasks"></i> Follow-up Tasks</h2>
                        <button class="modal-close" data-action="close-followup-modal">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="filters-bar filters-bar-compact">
                            <div class="filter-group filter-group-compact">
                                <label>Status:</label>
                                <select id="followupStatusFilter" class="filter-select filter-select-compact">
                                    <option value="">All</option>
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="filter-group filter-group-compact">
                                <label>Priority:</label>
                                <select id="followupPriorityFilter" class="filter-select filter-select-compact">
                                    <option value="">All</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="high">High</option>
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                            <div class="filter-group filter-group-compact">
                                <label>Type:</label>
                                <select id="followupTypeFilter" class="filter-select filter-select-compact">
                                    <option value="">All Types</option>
                                    <option value="transaction">Transaction</option>
                                    <option value="invoice">Invoice</option>
                                    <option value="bill">Bill</option>
                                    <option value="journal_entry">Journal Entry</option>
                                    <option value="account">Account</option>
                                </select>
                            </div>
                            <button class="btn btn-primary btn-sm" data-action="new-followup">
                                <i class="fas fa-plus"></i> New Follow-up
                            </button>
                            <button class="btn btn-secondary btn-sm" data-action="refresh-followups">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                        </div>
                        <div class="bulk-actions-bar bulk-actions-bar-hidden" id="followupsBulkActions">
                            <span class="bulk-selected-count" id="bulkSelectedCountFollowups">0 selected</span>
                            <div class="bulk-action-buttons">
                                <button class="btn btn-sm btn-danger" data-action="bulk-delete-followups">
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                                <button class="btn btn-sm btn-secondary" data-action="bulk-export-followups">
                                    <i class="fas fa-download"></i> Export Selected
                                </button>
                                <button class="btn btn-sm btn-primary" data-action="bulk-print-followups">
                                    <i class="fas fa-print"></i> Print Selected
                                </button>
                            </div>
                        </div>
                        <div class="followups-list" id="followupsList">
                            <div class="loading-state">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Loading follow-ups...</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            return modal;
        },

        createMessagesModal() {
            const modal = document.createElement('div');
            modal.id = 'messagesModal';
            modal.className = 'accounting-modal accounting-modal-overlay accounting-modal-hidden';
            modal.innerHTML = `
                <div class="accounting-modal-content messages-modal">
                    <div class="modal-header">
                        <h2>
                            <i class="fas fa-envelope"></i> Messages & Notifications
                            <span id="unreadMessagesBadge" class="badge badge-danger unread-badge">0</span>
                        </h2>
                        <div class="header-actions">
                            <button class="btn btn-primary btn-sm" data-action="new-message">
                                <i class="fas fa-plus"></i> New Message
                            </button>
                            <button class="btn btn-secondary btn-sm" data-action="mark-all-read">
                                <i class="fas fa-check-double"></i> Mark All Read
                            </button>
                            <button class="modal-close" data-action="close-messages-modal">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="modal-body">
                        <div class="filters-bar filters-bar-compact">
                            <div class="filter-group filter-group-compact">
                                <label>Type:</label>
                                <select id="messageTypeFilter" class="filter-select filter-select-compact">
                                    <option value="">All</option>
                                    <option value="info">Info</option>
                                    <option value="warning">Warning</option>
                                    <option value="error">Error</option>
                                    <option value="success">Success</option>
                                    <option value="alert">Alert</option>
                                </select>
                            </div>
                            <div class="filter-group filter-group-compact">
                                <label>Category:</label>
                                <select id="messageCategoryFilter" class="filter-select filter-select-compact">
                                    <option value="">All Categories</option>
                                    <option value="overdue_invoice">Overdue Invoices</option>
                                    <option value="low_balance">Low Balance</option>
                                    <option value="transaction_alert">Transaction Alerts</option>
                                    <option value="system_notification">System Notifications</option>
                                </select>
                            </div>
                            <div class="filter-group filter-group-compact">
                                <label>
                                    <input type="checkbox" id="unreadOnlyFilter" class="filter-checkbox">
                                    Unread Only
                                </label>
                            </div>
                            <button class="btn btn-secondary btn-sm" data-action="refresh-messages">
                                <i class="fas fa-sync"></i> Refresh
                            </button>
                        </div>
                        <div class="bulk-actions-bar bulk-actions-bar-hidden" id="messagesBulkActions">
                            <span class="bulk-selected-count" id="bulkSelectedCountMessages">0 selected</span>
                            <div class="bulk-action-buttons">
                                <button class="btn btn-sm btn-danger" data-action="bulk-delete-messages">
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                                <button class="btn btn-sm btn-secondary" data-action="bulk-export-messages">
                                    <i class="fas fa-download"></i> Export Selected
                                </button>
                                <button class="btn btn-sm btn-primary" data-action="bulk-print-messages">
                                    <i class="fas fa-print"></i> Print Selected
                                </button>
                            </div>
                        </div>
                        <div class="messages-list" id="messagesList">
                            <div class="loading-state">
                                <i class="fas fa-spinner fa-spin"></i>
                                <p>Loading messages...</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            return modal;
        },

        openFollowupModal() {
            // Ensure modals exist before trying to open
            this.ensureModalsExist();
            
            const modal = document.getElementById('followupModal');
            if (!modal) {
                // Wait a bit and try again
                setTimeout(() => {
                    const retryModal = document.getElementById('followupModal');
                    if (retryModal) {
                        this.openFollowupModal();
                    }
                }, 500);
                return;
            }
            
            if (modal) {
                // Remove existing handlers if any
                if (modal._handleClickOutside) {
                    modal.removeEventListener('click', modal._handleClickOutside);
                }
                if (modal._handleEscape) {
                    document.removeEventListener('keydown', modal._handleEscape);
                }
                
                modal.classList.remove('accounting-modal-hidden');
                modal.classList.add('accounting-modal-visible', 'show-modal');
                modal.setAttribute('data-modal-visible', 'true');
                this.activeModal = modal;
                
                // Add click-outside-to-close handler
                modal._handleClickOutside = async (e) => {
                    // Only close if clicking directly on modal backdrop, not modal content
                    if (e.target === modal && !e.target.closest('.accounting-modal-content')) {
                        e.stopPropagation();
                        await this.closeModalWithConfirmation(modal);
                    }
                };
                modal.addEventListener('click', modal._handleClickOutside);
                
                // Add Escape key handler
                modal._handleEscape = async (e) => {
                    if (e.key === 'Escape' && modal.classList.contains('accounting-modal-visible')) {
                        e.preventDefault();
                        await this.closeModalWithConfirmation(modal);
                    }
                };
                document.addEventListener('keydown', modal._handleEscape);
                
                // Small delay to ensure modal is visible before loading
                setTimeout(() => {
                    this.loadFollowups();
                }, 100);
                // Setup filter listeners
                setTimeout(() => {
                    const statusFilter = document.getElementById('followupStatusFilter');
                    const priorityFilter = document.getElementById('followupPriorityFilter');
                    const typeFilter = document.getElementById('followupTypeFilter');
                    if (statusFilter) {
                        statusFilter.removeEventListener('change', this.followupFilterHandler);
                        this.followupFilterHandler = () => this.loadFollowups();
                        statusFilter.addEventListener('change', this.followupFilterHandler);
                    }
                    if (priorityFilter) {
                        priorityFilter.removeEventListener('change', this.followupFilterHandler);
                        priorityFilter.addEventListener('change', this.followupFilterHandler);
                    }
                    if (typeFilter) {
                        typeFilter.removeEventListener('change', this.followupFilterHandler);
                        typeFilter.addEventListener('change', this.followupFilterHandler);
                    }
                }, 100);
            }
        },

        closeFollowupModal() {
            const modal = document.getElementById('followupModal');
            if (modal) {
                // Remove event listeners
                if (modal._handleClickOutside) {
                    modal.removeEventListener('click', modal._handleClickOutside);
                    delete modal._handleClickOutside;
                }
                if (modal._handleEscape) {
                    document.removeEventListener('keydown', modal._handleEscape);
                    delete modal._handleEscape;
                }
                
                modal.classList.add('accounting-modal-hidden');
                modal.classList.remove('accounting-modal-visible', 'show-modal');
                modal.removeAttribute('data-modal-visible');
                if (this.activeModal === modal) {
                    this.activeModal = null;
                }
            }
        },

        openMessagesModal() {
            // Ensure modals exist before trying to open
            this.ensureModalsExist();
            
            const modal = document.getElementById('messagesModal');
            if (modal) {
                // Remove existing handlers if any
                if (modal._handleClickOutside) {
                    modal.removeEventListener('click', modal._handleClickOutside);
                }
                if (modal._handleEscape) {
                    document.removeEventListener('keydown', modal._handleEscape);
                }
                
                modal.classList.remove('accounting-modal-hidden');
                modal.classList.add('accounting-modal-visible', 'show-modal');
                modal.setAttribute('data-modal-visible', 'true');
                this.activeModal = modal;
                
                // Add click-outside-to-close handler
                modal._handleClickOutside = async (e) => {
                    // Don't handle if clicking on action buttons - let them bubble up
                    if (e.target.closest('[data-action]')) {
                        return;
                    }
                    // Only close if clicking directly on modal backdrop, not modal content
                    if (e.target === modal && !e.target.closest('.accounting-modal-content')) {
                        e.stopPropagation();
                        await this.closeModalWithConfirmation(modal);
                    }
                };
                modal.addEventListener('click', modal._handleClickOutside);
                
                // Add Escape key handler
                modal._handleEscape = async (e) => {
                    if (e.key === 'Escape' && modal.classList.contains('accounting-modal-visible')) {
                        e.preventDefault();
                        await this.closeModalWithConfirmation(modal);
                    }
                };
                document.addEventListener('keydown', modal._handleEscape);
                
                this.loadMessages();
                // Setup filter listeners
                setTimeout(() => {
                    const typeFilter = document.getElementById('messageTypeFilter');
                    const categoryFilter = document.getElementById('messageCategoryFilter');
                    const unreadOnlyFilter = document.getElementById('unreadOnlyFilter');
                    if (typeFilter) {
                        typeFilter.removeEventListener('change', this.messageFilterHandler);
                        this.messageFilterHandler = () => {
                            this.messageCurrentPage = 1;
                            this.loadMessages();
                        };
                        typeFilter.addEventListener('change', this.messageFilterHandler);
                    }
                    if (categoryFilter) {
                        categoryFilter.removeEventListener('change', this.messageFilterHandler);
                        categoryFilter.addEventListener('change', this.messageFilterHandler);
                    }
                    if (unreadOnlyFilter) {
                        unreadOnlyFilter.removeEventListener('change', this.messageFilterHandler);
                        unreadOnlyFilter.addEventListener('change', this.messageFilterHandler);
                    }
                    const entriesPerPage = document.getElementById('messageEntriesPerPage');
                    if (entriesPerPage) {
                        entriesPerPage.removeEventListener('change', this.messageFilterHandler);
                        entriesPerPage.addEventListener('change', this.messageFilterHandler);
                    }
                }, 100);
            }
        },

        closeMessagesModal() {
            const modal = document.getElementById('messagesModal');
            if (modal) {
                // Remove event listeners
                if (modal._handleClickOutside) {
                    modal.removeEventListener('click', modal._handleClickOutside);
                    delete modal._handleClickOutside;
                }
                if (modal._handleEscape) {
                    document.removeEventListener('keydown', modal._handleEscape);
                    delete modal._handleEscape;
                }
                
                modal.classList.add('accounting-modal-hidden');
                modal.classList.remove('accounting-modal-visible', 'show-modal');
                modal.removeAttribute('data-modal-visible');
                if (this.activeModal === modal) {
                    this.activeModal = null;
                }
            }
        },

        showEditFollowupForm() {
            const modal = document.getElementById('editFollowupModal');
            if (modal) {
                modal.classList.remove('accounting-modal-hidden');
                modal.classList.add('accounting-modal-visible');
            }
        },

        closeEditFollowupForm() {
            const modal = document.getElementById('editFollowupModal');
            if (modal) {
                modal.classList.add('accounting-modal-hidden');
                modal.classList.remove('accounting-modal-visible');
                // Reset form
                document.getElementById('editFollowupForm')?.reset();
            }
        },

        updateModalTransactionsPagination() {
            const paginationTop = document.getElementById('modalLedgerPaginationTop');
            const paginationInfo = document.getElementById('modalLedgerPaginationInfoTop');
            
            if (paginationInfo) {
                const start = (this.transactionsCurrentPage - 1) * this.transactionsPerPage + 1;
                const end = Math.min(this.transactionsCurrentPage * this.transactionsPerPage, this.transactionsTotalCount);
                paginationInfo.textContent = `Showing ${start} to ${end} of ${this.transactionsTotalCount} entries`;
            }
            if (paginationTop) {
                let html = '<div class="pagination-controls">';
                
                // Previous button
                html += `<button class="btn btn-sm ${this.transactionsCurrentPage <= 1 ? 'disabled' : ''}" 
                         data-action="modal-transactions-prev" ${this.transactionsCurrentPage <= 1 ? 'disabled' : ''}>
                         <i class="fas fa-chevron-left"></i> Previous</button>`;
                
                // Page numbers
                const maxPages = Math.min(this.transactionsTotalPages, 10);
                const startPage = Math.max(1, this.transactionsCurrentPage - 5);
                const endPage = Math.min(this.transactionsTotalPages, startPage + 9);
                
                for (let i = startPage; i <= endPage; i++) {
                    html += `<button class="btn btn-sm ${i === this.transactionsCurrentPage ? 'btn-primary' : ''}" 
                             data-action="modal-transactions-page" data-page="${i}">${i}</button>`;
                }
                
                // Next button
                html += `<button class="btn btn-sm ${this.transactionsCurrentPage >= this.transactionsTotalPages ? 'disabled' : ''}" 
                         data-action="modal-transactions-next" ${this.transactionsCurrentPage >= this.transactionsTotalPages ? 'disabled' : ''}>
                         Next <i class="fas fa-chevron-right"></i></button>`;
                
                html += '</div>';
                paginationTop.innerHTML = html;
            }
        },

        openVouchersModal(mode) {
            // mode: 'expenses' = Payment vouchers only (money out). undefined/'all' = both Payment & Receipt
            this.vouchersModalMode = (mode === 'expenses') ? 'expenses' : 'all';
            const isExpensesOnly = this.vouchersModalMode === 'expenses';
            const modalTitle = isExpensesOnly ? 'Expenses (Payment Vouchers)' : 'Vouchers';
            const modal = document.getElementById('vouchersModal');
            if (modal) {
                modal.classList.remove('accounting-modal-hidden');
                modal.classList.add('accounting-modal-visible');
                this.activeModal = modal;
                const titleEl = modal.querySelector('.accounting-modal-header h3');
                if (titleEl) titleEl.textContent = modalTitle;
                const receiptBtn = modal.querySelector('[data-action="new-receipt-voucher"]');
                if (receiptBtn) receiptBtn.style.display = isExpensesOnly ? 'none' : '';
                setTimeout(() => {
                    this.loadVouchers();
                    this.setupVouchersHandlers();
                }, 100);
            } else {
                const receiptButtonHtml = isExpensesOnly ? '' : `
                                <button class="btn btn-primary btn-sm" data-action="new-receipt-voucher">
                                    <i class="fas fa-plus"></i> New Receipt Voucher
                                </button>`;
                const content = `
                    <div class="accounting-module-modal-content">
                        <div class="module-content">
                            <div class="filters-bar filters-bar-compact">
                                <button class="btn btn-primary btn-sm" data-action="new-payment-voucher">
                                    <i class="fas fa-plus"></i> New Payment Voucher
                                </button>
                                ${receiptButtonHtml}
                                <button class="btn btn-secondary btn-sm" data-action="refresh-vouchers">
                                    <i class="fas fa-sync"></i> Refresh
                                </button>
                                </div>
                            <div class="table-wrapper-modern">
                                <table class="table-modern" id="vouchersTable">
                                    <thead>
                                        <tr>
                                            <th>VOUCHER #</th>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Reference</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="vouchersTableBody">
                                        <tr>
                                            <td colspan="7" class="loading-row">
                                                <div class="loading-spinner">
                                                    <i class="fas fa-spinner fa-spin"></i>
                                                    <span>Loading vouchers...</span>
                            </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            </div>
                        </div>
                    `;
                this.showModal(modalTitle, content, 'large', 'vouchersModal');
                setTimeout(() => {
                    this.loadVouchers();
                    this.setupVouchersHandlers();
                }, 100);
            }
        },

        setupVouchersHandlers() {
            const modal = document.getElementById('vouchersModal') || document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
            if (!modal) return;
            
            // Refresh button
            const refreshBtn = modal.querySelector('[data-action="refresh-vouchers"]');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', () => {
                    this.loadVouchers();
                });
            }
            
            // New payment voucher button
            const newPaymentBtn = modal.querySelector('[data-action="new-payment-voucher"]');
            if (newPaymentBtn) {
                newPaymentBtn.addEventListener('click', () => {
                    this.openPaymentVoucherModal();
                });
            }
            
            // New receipt voucher button
            const newReceiptBtn = modal.querySelector('[data-action="new-receipt-voucher"]');
            if (newReceiptBtn) {
                newReceiptBtn.addEventListener('click', () => {
                    this.openReceiptVoucherModal();
                });
            }
            
            // View voucher buttons
            modal.querySelectorAll('[data-action="view-voucher"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    const type = e.target.closest('button').dataset.type;
                    this.viewVoucher(id, type);
                });
            });
            
            // Edit voucher buttons
            modal.querySelectorAll('[data-action="edit-voucher"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    const type = e.target.closest('button').dataset.type;
                    if (type === 'payment') this.openPaymentVoucherModal(id);
                    else if (type === 'receipt') this.openReceiptVoucherModal(id);
                });
            });
            
            // Print voucher buttons
            modal.querySelectorAll('[data-action="print-voucher"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    const type = e.target.closest('button').dataset.type;
                    this.printVoucher(id, type);
                });
            });
            
            // Delete voucher buttons
            modal.querySelectorAll('[data-action="delete-voucher"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const id = parseInt(e.target.closest('button').dataset.id);
                    const type = e.target.closest('button').dataset.type;
                    this.deleteVoucher(id, type);
                });
            });
        },

        async viewVoucher(voucherId, voucherType) {
            try {
                const response = await fetch(`${this.apiBase}/receipt-payment-vouchers.php?id=${voucherId}&type=${voucherType}`, { credentials: 'include' });
                const data = await response.json();
                if (data.success && data.voucher) {
                    const v = data.voucher;
                    const typeLabel = voucherType === 'payment' ? 'Payment' : 'Receipt';
                    const content = `
                        <div class="voucher-view-details accounting-modal-form-group">
                            <div class="accounting-modal-form-row">
                                <div class="accounting-modal-form-group">
                                    <label>Voucher Number</label>
                                    <p class="voucher-view-value">${this.escapeHtml(v.voucher_number || v.reference_number || voucherId)}</p>
                                </div>
                                <div class="accounting-modal-form-group">
                                    <label>Date</label>
                                    <p class="voucher-view-value">${v.voucher_date || v.payment_date || 'N/A'}</p>
                                </div>
                            </div>
                            <div class="accounting-modal-form-row">
                                <div class="accounting-modal-form-group">
                                    <label>Cash / Bank Account</label>
                                    <p class="voucher-view-value">${this.escapeHtml(v.bank_account_name || (v.bank_account_id === 0 ? 'Cash' : (v.bank_account_id ? `Bank #${v.bank_account_id}` : 'N/A')))}</p>
                                </div>
                                <div class="accounting-modal-form-group">
                                    <label>Payee / Expense Account</label>
                                    <p class="voucher-view-value">${this.escapeHtml(v.vendor_name || v.account_name || (v.vendor_id ? `Vendor #${v.vendor_id}` : v.account_id ? `Account #${v.account_id}` : 'N/A'))}</p>
                                </div>
                            </div>
                            <div class="accounting-modal-form-row">
                                <div class="accounting-modal-form-group">
                                    <label>Payment Method</label>
                                    <p class="voucher-view-value">${this.escapeHtml(v.payment_method || 'N/A')}</p>
                                </div>
                                <div class="accounting-modal-form-group">
                                    <label>Amount</label>
                                    <p class="voucher-view-value">${this.formatCurrency(v.amount || 0, v.currency || this.getDefaultCurrencySync?.() || 'SAR')}</p>
                                </div>
                            </div>
                            <div class="accounting-modal-form-row">
                                <div class="accounting-modal-form-group">
                                    <label>Cost Center</label>
                                    <p class="voucher-view-value">${this.escapeHtml(v.cost_center_name || (v.cost_center_id ? `#${v.cost_center_id}` : 'N/A'))}</p>
                                </div>
                                <div class="accounting-modal-form-group">
                                    <label>Currency</label>
                                    <p class="voucher-view-value">${this.escapeHtml(v.currency || 'N/A')}</p>
                                </div>
                            </div>
                            <div class="accounting-modal-form-row">
                                <div class="accounting-modal-form-group">
                                    <label>Status</label>
                                    <p class="voucher-view-value"><span class="badge badge-${v.status === 'Draft' ? 'secondary' : v.status === 'Posted' || v.status === 'Cleared' ? 'success' : 'warning'}">${this.escapeHtml(v.status || 'N/A')}</span></p>
                                </div>
                            </div>
                            <div class="accounting-modal-form-group full-width">
                                <label>Description</label>
                                <p class="voucher-view-value">${this.escapeHtml(v.notes || v.description || 'N/A')}</p>
                            </div>
                            <div class="accounting-modal-actions">
                                <button type="button" class="btn btn-secondary" data-action="close-modal">Close</button>
                                <button type="button" class="btn btn-primary btn-edit-from-view" data-voucher-id="${voucherId}" data-voucher-type="${voucherType}">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </div>
                        </div>
                    `;
                    this.showModal(`View ${typeLabel} Voucher`, content, 'large', 'viewVoucherModal');
                    const modal = document.getElementById('viewVoucherModal');
                    if (modal) {
                        const editBtn = modal.querySelector('.btn-edit-from-view');
                        if (editBtn) {
                            editBtn.addEventListener('click', () => {
                                this.closeModal();
                                if (voucherType === 'payment') this.openPaymentVoucherModal(parseInt(voucherId));
                                else if (voucherType === 'receipt') this.openReceiptVoucherModal(parseInt(voucherId));
                            });
                        }
                    }
                } else {
                    this.showToast(data.message || 'Failed to load voucher', 'error');
                }
            } catch (error) {
                this.showToast('Error loading voucher: ' + error.message, 'error');
            }
        },

        async printInvoice(invoiceId) {
            try {
                const response = await fetch(`${this.apiBase}/invoices.php?id=${invoiceId}&format=print`);
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const printWindow = window.open(url, '_blank');
                    if (printWindow) printWindow.onload = () => printWindow.print();
                } else {
                    this.showToast('Failed to generate print view', 'error');
                }
            } catch (error) {
                this.showToast('Error printing invoice: ' + (error?.message || ''), 'error');
            }
        },

        async printBill(billId) {
            try {
                const response = await fetch(`${this.apiBase}/bills.php?id=${billId}&format=print`);
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const printWindow = window.open(url, '_blank');
                    if (printWindow) printWindow.onload = () => printWindow.print();
                } else {
                    this.showToast('Failed to generate print view', 'error');
                }
            } catch (error) {
                this.showToast('Error printing bill: ' + (error?.message || ''), 'error');
            }
        },

        async printVoucher(voucherId, voucherType) {
            try {
                const response = await fetch(`${this.apiBase}/vouchers.php?id=${voucherId}&type=${voucherType}&format=print`);
                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const printWindow = window.open(url, '_blank');
                    if (printWindow) printWindow.onload = () => printWindow.print();
                } else {
                    this.showToast('Failed to generate print view', 'error');
                }
            } catch (error) {
                this.showToast('Error printing voucher: ' + error.message, 'error');
            }
        },

        async duplicateVoucher(voucherId, voucherType) {
            try {
                const response = await fetch(`${this.apiBase}/vouchers.php?id=${voucherId}&type=${voucherType}&action=duplicate`, { method: 'POST' });
                const data = await response.json();
                if (data.success) {
                    this.showToast(`${voucherType === 'payment' ? 'Payment' : 'Receipt'} voucher duplicated successfully`, 'success');
                    if (document.getElementById('vouchersModal')?.classList.contains('accounting-modal-visible')) {
                        this.loadVouchers();
                    }
                } else {
                    this.showToast(data.message || 'Failed to duplicate voucher', 'error');
                }
            } catch (error) {
                this.showToast('Error duplicating voucher: ' + error.message, 'error');
            }
        },

        async exportSingleVoucher(voucherId, voucherType) {
            try {
                const response = await fetch(`${this.apiBase}/vouchers.php?id=${voucherId}&type=${voucherType}`);
                const data = await response.json();
                if (data.success && data.voucher) {
                    const jsonData = JSON.stringify(data.voucher, null, 2);
                    const blob = new Blob([jsonData], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    const ref = (data.voucher.voucher_number || data.voucher.receipt_number || voucherId);
                    a.download = `${voucherType}-voucher-${ref}-${new Date().toISOString().split('T')[0]}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    this.showToast('Voucher exported successfully', 'success');
                } else {
                    this.showToast(data.message || 'Failed to load voucher', 'error');
                }
            } catch (error) {
                this.showToast('Error exporting voucher: ' + error.message, 'error');
            }
        },

        async deleteVoucher(voucherId, voucherType) {
            try {
                const response = await fetch(`${this.apiBase}/vouchers.php?id=${voucherId}&type=${voucherType}`, { method: 'DELETE' });
                const data = await response.json();
                if (data.success) {
                    this.showToast(`${voucherType === 'payment' ? 'Payment' : 'Receipt'} voucher deleted successfully`, 'success');
                    if (document.getElementById('vouchersModal')?.classList.contains('accounting-modal-visible')) {
                        this.loadVouchers();
                    }
                } else {
                    this.showToast(data.message || 'Failed to delete voucher', 'error');
                }
            } catch (error) {
                this.showToast('Error deleting voucher: ' + error.message, 'error');
            }
        },

        async addJournalEntryLine(side) {
            const form = document.getElementById('journalEntryForm');
            if (!form) return;
            const tbodyId = side === 'debit' ? 'journalDebitLinesBody' : 'journalCreditLinesBody';
            const tbody = document.getElementById(tbodyId);
            if (!tbody) return;
            const existingRows = tbody.querySelectorAll('.ledger-line-row');
            let maxIndex = -1;
            existingRows.forEach(row => {
                const index = parseInt(row.getAttribute('data-line-index') || '0');
                if (index > maxIndex) maxIndex = index;
            });
            const newIndex = maxIndex + 1;
            const newRowHTML = this.createJournalEntryLineRow(newIndex, side);
            const lastRow = tbody.querySelector('.ledger-line-row:last-child');
            if (lastRow) lastRow.insertAdjacentHTML('beforebegin', newRowHTML);
            else tbody.insertAdjacentHTML('beforeend', newRowHTML);
            const rowElement = tbody.querySelector(`.ledger-line-row[data-line-index="${newIndex}"]`);
            if (!rowElement) return;
            const allRows = tbody.querySelectorAll('.ledger-line-row');
            allRows.forEach((row) => {
                const removeBtn = row.querySelector('.btn-remove-line');
                if (removeBtn) removeBtn.style.display = allRows.length > 1 ? 'inline-flex' : 'none';
            });
            const accountSelect = rowElement.querySelector('.account-select');
            const costCenterSelect = rowElement.querySelector('.cost-center-select');
            if (accountSelect) await this.loadAccountsForSelect(null, accountSelect);
            if (costCenterSelect) await this.populateCostCenterSelect(costCenterSelect);
            const amountInput = rowElement.querySelector('.line-amount');
            if (amountInput && form) amountInput.dispatchEvent(new Event('input', { bubbles: true }));
        },

        async openPaymentVoucherModal(voucherId = null) {
            if (typeof origOpenPaymentVoucherModal === 'function') {
                await origOpenPaymentVoucherModal.call(this, voucherId);
                return;
            }
            const title = voucherId ? 'Edit Payment Voucher' : 'Add Payment Voucher';
            const content = typeof this.getPaymentVoucherModalContent === 'function'
                ? this.getPaymentVoucherModalContent(voucherId)
                : '<p class="text-muted">Payment voucher form not loaded. Ensure professional.part5.js is loaded.</p>';
            this.showModal(title, content, 'large', 'paymentVoucherModal');
            const modal = document.getElementById('paymentVoucherModal');
            if (!modal) return;
            
            // Create a simple close function for this modal
            const closePaymentModal = async () => {
                try {
                    modal.classList.remove('accounting-modal-visible', 'show-modal');
                    modal.classList.add('accounting-modal-hidden');
                    modal.removeAttribute('data-modal-visible');
                    if (modal.parentNode) {
                        modal.remove();
                    }
                    if (this.activeModal === modal) {
                        this.activeModal = null;
                    }
                    document.body.classList.remove('body-no-scroll');
                } catch (e) {
                    console.error('Error closing payment modal:', e);
                }
            };
            
            modal.setAttribute('lang', 'en');
            const form = modal.querySelector('#paymentVoucherForm');
            if (form) {
                form.setAttribute('lang', 'en');
                
                // Prevent form submission from cancel/close buttons - MUST be first
                form.addEventListener('click', async (e) => {
                    const button = e.target.closest('button');
                    if (button) {
                        const btnId = button.id;
                        const btnText = button.textContent.trim().toLowerCase();
                        const hasCloseAction = button.hasAttribute('data-action') && button.getAttribute('data-action') === 'close-modal';
                        const isCancelBtn = btnId === 'paymentVoucherCancelBtn' || (btnText.includes('cancel') && button.classList.contains('btn-secondary'));
                        
                        if (hasCloseAction || isCancelBtn) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            e.cancelBubble = true;
                            e.returnValue = false;
                            // Close the modal directly from form handler
                            await closePaymentModal();
                            return false;
                        }
                    }
                }, { capture: true });
                
                // Also add a direct handler on the cancel button itself as backup
                setTimeout(() => {
                    const cancelBtn = form.querySelector('#paymentVoucherCancelBtn') || form.querySelector('button[data-action="close-modal"]');
                    if (cancelBtn) {
                        const directCancelHandler = async function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            e.cancelBubble = true;
                            e.returnValue = false;
                            await closePaymentModal();
                            return false;
                        };
                        cancelBtn.addEventListener('click', directCancelHandler, { capture: true });
                        cancelBtn.addEventListener('click', directCancelHandler, { capture: false });
                        cancelBtn.onclick = directCancelHandler;
                    }
                }, 300);
                form.querySelectorAll('select[required]').forEach(function(sel) {
                    sel.setCustomValidity('Please select an item from the list.');
                    sel.addEventListener('change', function() { sel.setCustomValidity(sel.value ? '' : 'Please select an item from the list.'); });
                });
                form.querySelectorAll('input[required]').forEach(function(inp) {
                    inp.setCustomValidity(inp.validity.valueMissing ? 'Please fill in this field.' : '');
                    inp.addEventListener('input', function() { inp.setCustomValidity(inp.validity.valueMissing ? 'Please fill in this field.' : ''); });
                });
                form.removeEventListener('submit', form._paymentVoucherSubmit);
                form._paymentVoucherSubmit = async (e) => {
                    e.preventDefault();
                    if (typeof this.savePaymentVoucher === 'function') await this.savePaymentVoucher(voucherId);
                };
                form.addEventListener('submit', form._paymentVoucherSubmit);
                const dateInput = form.querySelector('[name="voucher_date"]');
                if (dateInput && !dateInput.value) {
                    dateInput.value = this.formatDateForInput(new Date().toISOString());
                }
                var voucherDataForApply = null;
                if (voucherId) {
                    try {
                        const res = await fetch(`${this.apiBase}/receipt-payment-vouchers.php?id=${voucherId}&type=payment`, { credentials: 'include' });
                        if (res.ok) {
                            const data = await res.json();
                            if (data.success && data.voucher) {
                                voucherDataForApply = data.voucher;
                            } else if (!data.success) {
                                this.showToast(data.message || 'Failed to load voucher', 'error');
                            }
                        } else {
                            this.showToast('Failed to load payment voucher', 'error');
                        }
                    } catch (e) {
                        console.error('Failed to fetch payment voucher:', e);
                        this.showToast('Failed to load payment voucher', 'error');
                    }
                }
                var loadOpts = ProfessionalAccounting.prototype.loadPaymentVoucherAccountOptions || this.loadPaymentVoucherAccountOptions;
                if (typeof loadOpts === 'function') await loadOpts.call(this, modal);
                if (typeof this.loadCostCentersForPaymentVoucher === 'function') await this.loadCostCentersForPaymentVoucher(modal);
                if (window.currencyUtils && window.currencyUtils.populateCurrencySelect) {
                    const currencySelect = form.querySelector('[name="currency"]');
                    if (currencySelect) {
                        window.currencyUtils.populateCurrencySelect(currencySelect.id || 'paymentVoucherCurrency');
                    }
                }
                if (voucherDataForApply && typeof this.applyPaymentVoucherDataToForm === 'function') {
                    const voucherData = voucherDataForApply;
                    const formEl = form;
                    var self = this;
                    var arabicToWestern = function(s) {
                        if (!s) return '';
                        var map = { '\u0660': '0', '\u0661': '1', '\u0662': '2', '\u0663': '3', '\u0664': '4', '\u0665': '5', '\u0666': '6', '\u0667': '7', '\u0668': '8', '\u0669': '9', '\u06F0': '0', '\u06F1': '1', '\u06F2': '2', '\u06F3': '3', '\u06F4': '4', '\u06F5': '5', '\u06F6': '6', '\u06F7': '7', '\u06F8': '8', '\u06F9': '9' };
                        return String(s).replace(/[\u0660-\u0669\u06F0-\u06F9]/g, function(c) { return map[c] || c; });
                    };
                    function applyNow() {
                        self.applyPaymentVoucherDataToForm(voucherData, formEl);
                        var amtInput = formEl.querySelector('[name="amount"]');
                        if (amtInput && amtInput.value) {
                            amtInput.value = arabicToWestern(amtInput.value);
                        }
                        var cashEl = formEl.querySelector('#paymentVoucherCashAccount');
                        if (cashEl && voucherData) {
                            var rawBank = voucherData.bank_account_id !== undefined ? voucherData.bank_account_id : (voucherData.bankAccountId !== undefined ? voucherData.bankAccountId : (voucherData.bank_account && voucherData.bank_account.id));
                            var bankId = rawBank !== undefined && rawBank !== null && rawBank !== '' ? parseInt(rawBank, 10) : NaN;
                            var val = '';
                            if (!isNaN(bankId) && bankId > 0) {
                                val = 'bank_' + bankId;
                                if (!Array.from(cashEl.options).some(function(o) { return o.value === val; })) {
                                    var opt = document.createElement('option');
                                    opt.value = val;
                                    opt.textContent = 'Bank #' + bankId;
                                    cashEl.appendChild(opt);
                                }
                            } else if (bankId === 0 || rawBank === '0' || rawBank === 0) {
                                val = '0';
                            }
                            if (val) {
                                cashEl.value = val;
                                var idx = Array.from(cashEl.options).findIndex(function(o) { return o.value === val; });
                                if (idx >= 0) cashEl.selectedIndex = idx;
                                var hint = formEl.querySelector('#paymentVoucherCashHint');
                                if (hint) hint.remove();
                            } else {
                                var hintEl = formEl.querySelector('#paymentVoucherCashHint');
                                if (!hintEl) {
                                    hintEl = document.createElement('small');
                                    hintEl.id = 'paymentVoucherCashHint';
                                    hintEl.className = 'form-text text-muted';
                                    hintEl.textContent = 'No cash/bank saved. Select one and click Update.';
                                    hintEl.style.marginTop = '4px';
                                    if (cashEl.nextElementSibling) cashEl.parentNode.insertBefore(hintEl, cashEl.nextElementSibling);
                                    else cashEl.parentNode.appendChild(hintEl);
                                }
                            }
                        }
                    }
                    applyNow();
                    setTimeout(applyNow, 100);
                    setTimeout(applyNow, 350);
                    var amtInput = formEl.querySelector('[name="amount"]');
                    if (amtInput) { amtInput.setAttribute('dir', 'ltr'); amtInput.setAttribute('lang', 'en'); amtInput.style.direction = 'ltr'; }
                }
                var amountInput = form.querySelector('[name="amount"]');
                if (amountInput) {
                    var toWestern = function(str) {
                        if (!str) return '';
                        str = String(str);
                        var m = { '\u0660': '0', '\u0661': '1', '\u0662': '2', '\u0663': '3', '\u0664': '4', '\u0665': '5', '\u0666': '6', '\u0667': '7', '\u0668': '8', '\u0669': '9', '\u06F0': '0', '\u06F1': '1', '\u06F2': '2', '\u06F3': '3', '\u06F4': '4', '\u06F5': '5', '\u06F6': '6', '\u06F7': '7', '\u06F8': '8', '\u06F9': '9' };
                        return str.replace(/[\u0660-\u0669\u06F0-\u06F9]/g, function(c) { return m[c] || c; });
                    };
                    amountInput.addEventListener('input', function() {
                        var v = this.value;
                        var w = toWestern(v);
                        if (w !== v) this.value = w;
                    });
                    amountInput.addEventListener('change', function() {
                        var v = this.value;
                        var w = toWestern(v);
                        if (w !== v) this.value = w;
                    });
                }
                
                // Force attach handlers directly to buttons after a short delay
                setTimeout(() => {
                    // Cancel button - remove all handlers and add new one
                    const cancelBtn = modal.querySelector('#paymentVoucherCancelBtn') || modal.querySelector('button[data-action="close-modal"]');
                    if (cancelBtn) {
                        // Clone to remove all event listeners
                        const newCancelBtn = cancelBtn.cloneNode(true);
                        cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
                        
                        const cancelHandler = async function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            e.cancelBubble = true;
                            e.returnValue = false;
                            
                            // Prevent form submission
                            const form = this.closest('form');
                            if (form) {
                                form.preventDefault && form.preventDefault();
                                form.stopPropagation && form.stopPropagation();
                            }
                            
                            await closePaymentModal();
                            return false;
                        };
                        
                        // Set onclick first (fires before addEventListener)
                        newCancelBtn.onclick = cancelHandler;
                        // Then addEventListener with capture (fires early)
                        newCancelBtn.addEventListener('click', cancelHandler, { capture: true, once: false });
                        // Also add without capture as backup
                        newCancelBtn.addEventListener('click', cancelHandler, { capture: false, once: false });
                    }
                    
                    // X button - remove all handlers and add new one
                    const closeBtn = modal.querySelector('.accounting-modal-close');
                    if (closeBtn) {
                        const newCloseBtn = closeBtn.cloneNode(true);
                        closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);
                        
                        const closeHandler = async function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            e.cancelBubble = true;
                            e.returnValue = false;
                            await closePaymentModal();
                            return false;
                        };
                        
                        newCloseBtn.onclick = closeHandler;
                        newCloseBtn.addEventListener('click', closeHandler, true);
                    }
                    
                    // Overlay click
                    const overlay = modal.querySelector('.accounting-modal-overlay');
                    if (overlay) {
                        const overlayHandler = async function(e) {
                            if (e.target === overlay) {
                                e.preventDefault();
                                e.stopPropagation();
                                e.stopImmediatePropagation();
                                e.cancelBubble = true;
                                e.returnValue = false;
                                await closePaymentModal();
                                return false;
                            }
                        };
                        
                        overlay.onclick = overlayHandler;
                        overlay.addEventListener('click', overlayHandler, true);
                    }
                    
                    // Modal backdrop click
                    const backdropHandler = async function(e) {
                        if (e.target === modal && !e.target.closest('.accounting-modal-content')) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            e.cancelBubble = true;
                            e.returnValue = false;
                            await closePaymentModal();
                            return false;
                        }
                    };
                    
                    modal.onclick = backdropHandler;
                    modal.addEventListener('click', backdropHandler, true);
                }, 200);
            }
        },

        async openReceiptVoucherModal(voucherId = null) {
            const title = voucherId ? 'Edit Receipt Voucher' : 'Add Receipt Voucher';
            const content = this.getReceiptVoucherModalContent(voucherId);
            this.showModal(title, content, 'large');
            const modal = document.getElementById('receiptVoucherModal');
            if (!modal) return;
            
            // Create a simple close function for this modal
            const closeReceiptModal = async () => {
                try {
                    modal.classList.remove('accounting-modal-visible', 'show-modal');
                    modal.classList.add('accounting-modal-hidden');
                    modal.removeAttribute('data-modal-visible');
                    if (modal.parentNode) {
                        modal.remove();
                    }
                    if (this.activeModal === modal) {
                        this.activeModal = null;
                    }
                    document.body.classList.remove('body-no-scroll');
                } catch (e) {
                    console.error('Error closing receipt modal:', e);
                }
            };
            
            const form = modal.querySelector('#receiptVoucherForm');
            if (!form) return;
            
            // Prevent form submission from cancel/close buttons - MUST be first
            form.addEventListener('click', async (e) => {
                const button = e.target.closest('button');
                if (button) {
                    const btnId = button.id;
                    const btnText = button.textContent.trim().toLowerCase();
                    const hasCloseAction = button.hasAttribute('data-action') && button.getAttribute('data-action') === 'close-modal';
                    const isCancelBtn = btnId === 'receiptVoucherCancelBtn' || (btnText.includes('cancel') && button.classList.contains('btn-secondary'));
                    
                    if (hasCloseAction || isCancelBtn) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        e.cancelBubble = true;
                        e.returnValue = false;
                        // Close the modal directly from form handler
                        await closeReceiptModal();
                        return false;
                    }
                }
            }, { capture: true });
            
            // Also add a direct handler on the cancel button itself as backup
            setTimeout(() => {
                const cancelBtn = form.querySelector('#receiptVoucherCancelBtn') || form.querySelector('button[data-action="close-modal"]');
                if (cancelBtn) {
                    const directCancelHandler = async function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        e.cancelBubble = true;
                        e.returnValue = false;
                        await closeReceiptModal();
                        return false;
                    };
                    cancelBtn.addEventListener('click', directCancelHandler, { capture: true });
                    cancelBtn.addEventListener('click', directCancelHandler, { capture: false });
                    cancelBtn.onclick = directCancelHandler;
                }
            }, 300);
            
            // Form submit handler
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.saveReceiptVoucher(voucherId);
            });
            const dateInput = form.querySelector('[name="payment_date"]');
            if (dateInput && !dateInput.value) {
                dateInput.value = this.formatDateForInput(new Date().toISOString());
            }
            await this.loadReceiptVoucherAccountOptions(modal);
            await this.loadCostCentersForReceiptVoucher(modal);
            if (window.currencyUtils && window.currencyUtils.populateCurrencySelect) {
                const currencySelect = form.querySelector('[name="currency"]');
                if (currencySelect) {
                    window.currencyUtils.populateCurrencySelect(currencySelect.id || 'receiptVoucherCurrency');
                }
            }
            if (voucherId) {
                let receipt = null;
                try {
                    const res = await fetch(`${this.apiBase}/payment-receipts.php?id=${voucherId}`);
                    if (res.ok) {
                        let text = await res.text();
                        if (text && text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
                        const data = JSON.parse(text);
                        if (data.success && data.receipt) receipt = data.receipt;
                    }
                } catch (e) {
                    console.error('Failed to fetch receipt for edit:', e);
                }
                if (receipt) {
                    this.applyReceiptDataToEditForm(receipt, modal);
                    const cashEl = modal.querySelector('#cash_account');
                    const collectedEl = modal.querySelector('#collected_from');
                    const bankVal = this._receiptBankSelectValue(receipt);
                    const customerVal = this._receiptCollectedSelectValue(receipt);
                    this._setReceiptSelectValueAndTrigger(cashEl, bankVal, bankVal === '0' ? 'Cash' : (bankVal.startsWith('bank_') ? 'Bank' : 'GL'));
                    this._setReceiptSelectValueAndTrigger(collectedEl, customerVal, customerVal ? (customerVal.startsWith('customer_') ? 'Customer' : 'GL') : '');
                } else {
                    await this.loadReceiptVoucherData(voucherId, form);
                }
            }
            const addBankBtn = modal.querySelector('#addBankAccountBtn');
            if (addBankBtn) {
                addBankBtn.addEventListener('click', async () => {
                    await this.loadReceiptVoucherAccountOptions(modal);
                });
            }
            const customerSelect = form.querySelector('[name="collected_from"]');
            if (customerSelect) {
                customerSelect.addEventListener('change', async () => {
                    const selected = customerSelect.value;
                    if (selected && selected.startsWith('customer_')) {
                        const customerId = parseInt(selected.replace('customer_', ''), 10);
                        const invoiceSelect = form.querySelector('[name="invoice_id"]');
                        if (invoiceSelect) {
                            await this.loadInvoicesForSelect(invoiceSelect.id, customerId);
                        }
                    }
                });
            }
            this.initializeEnglishDatePickers(modal);
            
            // Force attach handlers directly to buttons after a short delay
            setTimeout(() => {
                // Cancel button - remove all handlers and add new one
                const cancelBtn = modal.querySelector('#receiptVoucherCancelBtn') || modal.querySelector('button[data-action="close-modal"]');
                if (cancelBtn) {
                    // Clone to remove all event listeners
                    const newCancelBtn = cancelBtn.cloneNode(true);
                    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
                    
                    const cancelHandler = async function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        e.cancelBubble = true;
                        e.returnValue = false;
                        
                        // Prevent form submission
                        const form = this.closest('form');
                        if (form) {
                            form.preventDefault && form.preventDefault();
                            form.stopPropagation && form.stopPropagation();
                        }
                        
                        await closeReceiptModal();
                        return false;
                    };
                    
                    // Set onclick first (fires before addEventListener)
                    newCancelBtn.onclick = cancelHandler;
                    // Then addEventListener with capture (fires early)
                    newCancelBtn.addEventListener('click', cancelHandler, { capture: true, once: false });
                    // Also add without capture as backup
                    newCancelBtn.addEventListener('click', cancelHandler, { capture: false, once: false });
                }
                
                // X button - remove all handlers and add new one
                const closeBtn = modal.querySelector('.accounting-modal-close');
                if (closeBtn) {
                    const newCloseBtn = closeBtn.cloneNode(true);
                    closeBtn.parentNode.replaceChild(newCloseBtn, closeBtn);
                    
                    const closeHandler = async function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        e.cancelBubble = true;
                        e.returnValue = false;
                        await closeReceiptModal();
                        return false;
                    };
                    
                    newCloseBtn.onclick = closeHandler;
                    newCloseBtn.addEventListener('click', closeHandler, true);
                }
                
                // Overlay click
                const overlay = modal.querySelector('.accounting-modal-overlay');
                if (overlay) {
                    const overlayHandler = async function(e) {
                        if (e.target === overlay) {
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            e.cancelBubble = true;
                            e.returnValue = false;
                            await closeReceiptModal();
                            return false;
                        }
                    };
                    
                    overlay.onclick = overlayHandler;
                    overlay.addEventListener('click', overlayHandler, true);
                }
                
                // Modal backdrop click
                const backdropHandler = async function(e) {
                    if (e.target === modal && !e.target.closest('.accounting-modal-content')) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        e.cancelBubble = true;
                        e.returnValue = false;
                        await closeReceiptModal();
                        return false;
                    }
                };
                
                modal.onclick = backdropHandler;
                modal.addEventListener('click', backdropHandler, true);
            }, 200);
        },

        async loadInvoicesForSelect(selector, customerId = null) {
            const invoiceSelect = typeof selector === 'string' ? document.getElementById(selector) : selector;
            if (!invoiceSelect) return;
            invoiceSelect.innerHTML = '<option value="">Loading invoices...</option>';
            try {
                let url = `${this.apiBase}/invoices.php?status=unpaid`;
                if (customerId) url += `&customer_id=${customerId}`;
                const response = await fetch(url);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    data = { success: false, invoices: [] };
                }
                if (data.success && data.invoices) {
                    invoiceSelect.innerHTML = '<option value="">Select Invoice</option>';
                    data.invoices.forEach(invoice => {
                        const option = document.createElement('option');
                        option.value = invoice.id;
                        const balance = parseFloat(invoice.balance_amount || invoice.total_amount || 0);
                        option.textContent = `${invoice.invoice_number || 'N/A'} - ${this.formatCurrency(balance, invoice.currency || this.getDefaultCurrencySync())}`;
                        option.dataset.balance = balance;
                        invoiceSelect.appendChild(option);
                    });
                } else {
                    invoiceSelect.innerHTML = '<option value="">No unpaid invoices found</option>';
                }
            } catch (error) {
                invoiceSelect.innerHTML = '<option value="">Error loading invoices</option>';
            }
        },

        async loadBillsForSelect(selector, vendorId = null) {
            const billSelect = typeof selector === 'string' ? document.getElementById(selector) : selector;
            if (!billSelect) return;
            billSelect.innerHTML = '<option value="">Loading bills...</option>';
            try {
                let url = `${this.apiBase}/bills.php?status=unpaid`;
                if (vendorId) url += `&vendor_id=${vendorId}`;
                const response = await fetch(url);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    data = { success: false, bills: [] };
                }
                if (data.success && data.bills) {
                    billSelect.innerHTML = '<option value="">Select Bill</option>';
                    data.bills.forEach(bill => {
                        const option = document.createElement('option');
                        option.value = bill.id;
                        const balance = parseFloat(bill.balance_amount || bill.total_amount || 0);
                        option.textContent = `${bill.bill_number || 'N/A'} - ${this.formatCurrency(balance, bill.currency || this.getDefaultCurrencySync())}`;
                        option.dataset.balance = balance;
                        billSelect.appendChild(option);
                    });
                } else {
                    billSelect.innerHTML = '<option value="">No unpaid bills found</option>';
                }
            } catch (error) {
                billSelect.innerHTML = '<option value="">Error loading bills</option>';
            }
        },

        _receiptBankSelectValue(receipt) {
            if (receipt.cash_account_option_value !== undefined && receipt.cash_account_option_value !== null && receipt.cash_account_option_value !== '') {
                return String(receipt.cash_account_option_value);
            }
            // Check for Cash (0) first
            if (receipt.bank_account_id === 0 || receipt.bank_account_id === '0') return '0';
            const bankId = receipt.bank_account_id != null && receipt.bank_account_id !== '' ? Number(receipt.bank_account_id) : null;
            const accountId = receipt.account_id != null && receipt.account_id !== '' ? Number(receipt.account_id) : null;
            if (bankId) return `bank_${bankId}`;
            if (accountId) return `gl_${accountId}`;
            return '';
        },

        _receiptCollectedSelectValue(receipt) {
            if (receipt.collected_from_option_value !== undefined && receipt.collected_from_option_value !== null && receipt.collected_from_option_value !== '') {
                return String(receipt.collected_from_option_value);
            }
            if (receipt.customer_id === 0 || receipt.customer_id === '0') return '0';
            const customerId = receipt.customer_id != null && receipt.customer_id !== '' ? Number(receipt.customer_id) : null;
            const collectedFromId = receipt.collected_from_account_id != null && receipt.collected_from_account_id !== '' ? Number(receipt.collected_from_account_id) : null;
            if (customerId) return `customer_${customerId}`;
            if (collectedFromId) return `gl_${collectedFromId}`;
            return '';
        },

        _setReceiptSelectValueAndTrigger(select, value, fallbackLabelPrefix) {
            if (!select) return;
            const strVal = String(value);
            let idx = -1;
            if (strVal === '') {
                idx = 0;
            } else {
                idx = Array.from(select.options).findIndex(o => String(o.value) === strVal);
                if (idx < 0) {
                    const opt = document.createElement('option');
                    opt.value = strVal;
                    opt.textContent = fallbackLabelPrefix ? `${fallbackLabelPrefix} #${strVal.replace(/^(bank_|gl_|customer_)/, '')}` : strVal;
                    select.appendChild(opt);
                    idx = Array.from(select.options).findIndex(o => String(o.value) === strVal);
                }
            }
            if (idx >= 0) {
                select.selectedIndex = idx;
                for (let i = 0; i < select.options.length; i++) {
                    select.options[i].selected = (i === idx);
                }
            }
            select.dispatchEvent(new Event('change', { bubbles: true }));
            if (typeof window.$ !== 'undefined' && window.$.fn.select2 && window.$(select).data('select2')) {
                window.$(select).trigger('change.select2');
            }
        },

        getReceiptVoucherModalContent(receiptId = null) {
            const isEdit = !!receiptId;
            const today = this.formatDateForInput(new Date().toISOString().split('T')[0]);
            
            return `
                <form id="receiptVoucherForm" data-receipt-id="${receiptId || 'null'}">
                    <div class="accounting-modal-form-row">
                        <div class="accounting-modal-form-group">
                            <label>Voucher Number</label>
                            <input type="text" name="receipt_number" id="receiptVoucherNumber" readonly placeholder="Auto-generated">
                            <small class="form-text">Auto-generated</small>
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Voucher Date *</label>
                            <div class="date-input-wrapper">
                                <input type="text" name="payment_date" id="receiptVoucherDate" class="date-input" required value="${today}" placeholder="MM/DD/YYYY">
                                <i class="fas fa-calendar-alt date-icon"></i>
                            </div>
                        </div>
                    </div>
                    <div class="accounting-modal-form-row">
                        <div class="accounting-modal-form-group">
                            <label>Cash / Bank Account *</label>
                            <div style="display: flex; gap: 8px; align-items: flex-start;">
                                <select name="bank_account_id" id="cash_account" required style="flex: 1;">
                                    <option value="">Loading bank accounts...</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-outline" id="addBankAccountBtn" title="Add Bank Account" style="white-space: nowrap; padding: 8px 12px;">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <small class="form-text" id="bankAccountHelpText" style="display: none;"></small>
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Account Collected From</label>
                            <div style="display: flex; gap: 8px; align-items: flex-start;">
                                <select name="customer_id" id="collected_from" style="flex: 1;">
                                    <option value="">Loading accounts...</option>
                                </select>
                                <button type="button" class="btn btn-sm btn-outline" id="addCustomerBtn" title="Add Customer" style="white-space: nowrap; padding: 8px 12px;">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                            <small class="form-text" id="customerHelpText" style="display: none;"></small>
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
                    </div>
                    <div class="accounting-modal-form-row">
                        <div class="accounting-modal-form-group">
                            <label>Amount *</label>
                            <input type="number" name="amount" step="0.01" min="0" required placeholder="Amount">
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Cost Center</label>
                            <select name="cost_center_id" id="receiptVoucherCostCenter">
                                <option value="">Select option</option>
                            </select>
                        </div>
                    </div>
                    <div class="accounting-modal-form-row">
                        <div class="accounting-modal-form-group">
                            <label>Value Added Tax:</label>
                            <div class="tax-checkbox-row">
                                <input type="checkbox" name="vat_report" id="receiptVoucherVatCheckbox" value="1">
                                <span id="receiptVoucherVatLabel" class="tax-label">No Value Added Tax</span>
                            </div>
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Currency</label>
                            <select name="currency" id="receiptVoucherCurrency">
                                <option value="">Loading currencies...</option>
                            </select>
                        </div>
                    </div>
                    <div class="accounting-modal-form-group full-width">
                        <label>Statement / Description</label>
                        <textarea name="notes" rows="3" placeholder="Description"></textarea>
                    </div>
                <div class="accounting-modal-actions">
                    <button type="button" class="btn btn-secondary" id="receiptVoucherCancelBtn" data-action="close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="receiptVoucherSaveBtn">
                        <i class="fas fa-save"></i> ${isEdit ? 'Update' : 'Save'}
                    </button>
                </div>
            </form>
            `;
        },

        applyReceiptDataToEditForm(receipt, modalEl = null) {
            const modal = modalEl || document.getElementById('receiptVoucherModal');
            const form = modal ? modal.querySelector('#receiptVoucherForm') : document.getElementById('receiptVoucherForm');
            if (!form || !receipt) return;
            if (form.querySelector('[name="receipt_number"]')) form.querySelector('[name="receipt_number"]').value = receipt.receipt_number || receipt.voucher_number || '';
            if (form.querySelector('[name="payment_date"]')) form.querySelector('[name="payment_date"]').value = receipt.payment_date || '';
            if (form.querySelector('[name="amount"]')) form.querySelector('[name="amount"]').value = receipt.amount || '';
            if (form.querySelector('[name="cost_center_id"]')) form.querySelector('[name="cost_center_id"]').value = receipt.cost_center_id || '';
            if (form.querySelector('[name="payment_method"]')) form.querySelector('[name="payment_method"]').value = receipt.payment_method || 'Cash';
            if (form.querySelector('[name="currency"]')) form.querySelector('[name="currency"]').value = receipt.currency || 'SAR';
            if (form.querySelector('[name="notes"]')) form.querySelector('[name="notes"]').value = receipt.notes || receipt.description || '';
            const vatCheckbox = form.querySelector('#receiptVoucherVatCheckbox');
            if (vatCheckbox) vatCheckbox.checked = receipt.vat_report === '1' || receipt.vat_report === true || receipt.vat_report === 1;
            const vatLabel = form.querySelector('#receiptVoucherVatLabel');
            if (vatLabel) vatLabel.textContent = vatCheckbox && vatCheckbox.checked ? 'Value Added Tax' : 'No Value Added Tax';
        },

        updateReceiptVouchersPagination(totalRecords, currentPage, totalPages) {
            const paginationEl = document.getElementById('receiptVouchersPagination');
            if (!paginationEl) return;
            const startRecord = totalRecords > 0 ? (currentPage - 1) * this.receiptVouchersPerPage + 1 : 0;
            const endRecord = Math.min(currentPage * this.receiptVouchersPerPage, totalRecords);
            paginationEl.innerHTML = `
                <div class="pagination-info">
                    ${totalRecords > 0 ? `Showing ${startRecord} to ${endRecord} of ${totalRecords} records` : 'No records found'}
                </div>
                <div class="pagination-controls">
                    <button class="btn btn-sm" ${currentPage === 1 ? 'disabled' : ''} data-action="prev-receipt-vouchers-page">
                        <i class="fas fa-chevron-left"></i> Previous
                    </button>
                    ${Array.from({ length: Math.min(totalPages, 10) }, (_, i) => {
                        const page = i + 1;
                        return `<button class="btn btn-sm ${page === currentPage ? 'active' : ''}" data-action="goto-receipt-vouchers-page" data-page="${page}">${page}</button>`;
                    }).join('')}
                    <button class="btn btn-sm" ${currentPage === totalPages ? 'disabled' : ''} data-action="next-receipt-vouchers-page">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            `;
            // Add event listeners
            paginationEl.querySelectorAll('[data-action="prev-receipt-vouchers-page"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (this.receiptVouchersCurrentPage > 1) {
                        this.receiptVouchersCurrentPage--;
                        this.loadReceiptVouchers();
                    }
                });
            });
            paginationEl.querySelectorAll('[data-action="next-receipt-vouchers-page"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (this.receiptVouchersCurrentPage < totalPages) {
                        this.receiptVouchersCurrentPage++;
                        this.loadReceiptVouchers();
                    }
                });
            });
            paginationEl.querySelectorAll('[data-action="goto-receipt-vouchers-page"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const page = parseInt(e.currentTarget.getAttribute('data-page'));
                    this.receiptVouchersCurrentPage = page;
                    this.loadReceiptVouchers();
                });
            });
        },

        exportReceiptVouchers(format) {
            // Export functionality - to be implemented
            this.showToast(`Export to ${format.toUpperCase()} - Coming soon`, 'info');
        },

        openQuickEntry() {
            this.openQuickEntryModal();
        },

        openQuickEntryModal() {
            const content = this.getQuickEntryModalContent();
            this.showModal('Quick Entry', content);
            
            setTimeout(async () => {
                await this.loadAccountsForSelect('quickEntryAccountSelect');
                // Populate currency dropdown
                const currencySelect = document.getElementById('quickEntryCurrency');
                if (currencySelect && window.currencyUtils) {
                    try {
                        const defaultCurrency = this.getDefaultCurrencySync();
                        await window.currencyUtils.populateCurrencySelect(currencySelect, defaultCurrency);
                    } catch (error) {
                        console.error('Error populating quick entry currency:', error);
                    }
                }
            }, 100);
        },

        getQuickEntryModalContent() {
            return `
                <form id="quickEntryForm" class="accounting-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date *</label>
                            <input type="text" name="date" class="date-input" required value="${this.formatDateForInput(new Date().toISOString())}" placeholder="MM/DD/YYYY">
                        </div>
                        <div class="form-group">
                            <label>Account *</label>
                            <select name="account_id" id="quickEntryAccountSelect" required>
                                <option value="">Select Account...</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Description *</label>
                            <textarea name="description" required rows="3" placeholder="Transaction description"></textarea>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="debit-label">Debit Amount</label>
                            <input type="number" name="debit" class="debit-input" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label class="credit-label">Credit Amount</label>
                            <input type="number" name="credit" class="credit-input" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Currency</label>
                            <select name="currency" id="quickEntryCurrency">
                                <option value="">Loading currencies...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Reference</label>
                            <input type="text" name="reference" placeholder="Optional reference">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Entry</button>
                    </div>
                </form>
            `;
        },

        async openReceivePaymentModal(invoiceId = null) {
            const title = invoiceId ? 'Receive Payment for Invoice' : 'Receive Payment';
            const content = this.getReceivePaymentModalContent(invoiceId);
            this.showModal(title, content);
            setTimeout(() => {
                const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
                if (modal) this.initializeEnglishDatePickers(modal);
            }, 100);
            setTimeout(async () => {
                const currencySelect = document.getElementById('receivePaymentCurrency');
                if (currencySelect && window.currencyUtils) {
                    try {
                        await window.currencyUtils.populateCurrencySelect(currencySelect, this.getDefaultCurrencySync());
                    } catch (e) {}
                }
            }, 150);
            if (!invoiceId) {
                setTimeout(async () => { await this.loadInvoicesForSelect('receivePaymentInvoiceSelect'); }, 100);
            } else {
                setTimeout(async () => {
                    try {
                        const r = await fetch(`${this.apiBase}/invoices.php?id=${invoiceId}`);
                        const d = await r.json();
                        if (d.success && d.invoice) {
                            const form = document.getElementById('receivePaymentForm');
                            if (form) {
                                const amt = form.querySelector('input[name="amount"]');
                                const sel = form.querySelector('select[name="invoice_id"]');
                                if (amt) amt.value = parseFloat(d.invoice.balance_amount || d.invoice.total_amount || 0);
                                if (sel) sel.value = invoiceId;
                            }
                        }
                    } catch (e) {}
                }, 100);
            }
        },

        async openMakePaymentModal(billId = null) {
            const title = billId ? 'Make Payment for Bill' : 'Make Payment';
            const content = this.getMakePaymentModalContent(billId);
            this.showModal(title, content);
            setTimeout(async () => {
                const currencySelect = document.getElementById('makePaymentCurrency');
                if (currencySelect && window.currencyUtils) {
                    try {
                        await window.currencyUtils.populateCurrencySelect(currencySelect, this.getDefaultCurrencySync());
                    } catch (e) {}
                }
            }, 150);
            if (!billId) {
                setTimeout(async () => {
                    await this.loadVendorsForSelect('makePaymentVendorSelect');
                    await this.loadBillsForSelect('makePaymentBillSelect');
                }, 100);
            } else {
                setTimeout(async () => {
                    try {
                        const r = await fetch(`${this.apiBase}/bills.php?id=${billId}`);
                        const d = await r.json();
                        if (d.success && d.bill) {
                            const form = document.getElementById('makePaymentForm');
                            if (form) {
                                const amt = form.querySelector('input[name="amount"]');
                                const sel = form.querySelector('select[name="bill_id"]');
                                if (amt) amt.value = parseFloat(d.bill.balance_amount || d.bill.total_amount || 0);
                                if (sel) sel.value = billId;
                            }
                        }
                    } catch (e) {}
                }, 100);
            }
        },

        getReceivePaymentModalContent(invoiceId = null) {
            return `
                <form id="receivePaymentForm" class="accounting-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Payment Date *</label>
                            <input type="text" name="payment_date" class="date-input" required value="${this.formatDateForInput(new Date().toISOString())}" placeholder="MM/DD/YYYY">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label>Customers</label>
                            <div id="receivePaymentCustomersContainer">
                                <div class="customer-input-row" data-customer-index="0">
                                    <input type="text" name="customers[]" class="customer-name-input" placeholder="Enter customer name">
                                    <button type="button" class="btn-add-customer" data-action="add-customer" title="Add Customer">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Invoice</label>
                            <select name="invoice_id" id="receivePaymentInvoiceSelect">
                                <option value="">Select Invoice...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Amount *</label>
                            <input type="number" name="amount" step="0.01" min="0" required placeholder="0.00">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Payment Method *</label>
                            <select name="payment_method" required>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Currency</label>
                            <select name="currency" id="receivePaymentCurrency">
                                <option value="">Loading currencies...</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Reference Number</label>
                            <input type="text" name="reference_number" placeholder="Reference number">
                        </div>
                        <div class="form-group">
                            <label>Cheque Number</label>
                            <input type="text" name="cheque_number" placeholder="If paid by cheque">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label>Notes</label>
                            <textarea name="notes" rows="3" placeholder="Additional notes"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Record Payment</button>
                    </div>
                </form>
            `;
        },

        getMakePaymentModalContent(billId = null) {
            return `
                <form id="makePaymentForm" class="accounting-form">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Payment Date *</label>
                            <input type="text" name="payment_date" class="date-input" required value="${this.formatDateForInput(new Date().toISOString())}" placeholder="MM/DD/YYYY">
                        </div>
                        <div class="form-group">
                            <label>Vendor</label>
                            <select name="vendor_id" id="makePaymentVendorSelect">
                                <option value="">Select Vendor...</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Bill</label>
                            <select name="bill_id" id="makePaymentBillSelect">
                                <option value="">Select Bill...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Amount *</label>
                            <input type="number" name="amount" step="0.01" min="0" required placeholder="0.00">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Payment Method *</label>
                            <select name="payment_method" required>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Currency</label>
                            <select name="currency" id="quickEntryCurrency">
                                <option value="">Loading currencies...</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Reference Number</label>
                            <input type="text" name="reference_number" placeholder="Reference number">
                        </div>
                        <div class="form-group">
                            <label>Cheque Number</label>
                            <input type="text" name="cheque_number" placeholder="If paid by cheque">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label>Notes</label>
                            <textarea name="notes" rows="3" placeholder="Additional notes"></textarea>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Record Payment</button>
                    </div>
                </form>
            `;
        },

        getFinancialPeriodsModalContent() {
            return `
                <div class="accounting-modal-content-full">
                    <div class="module-header">
                        <h3><i class="fas fa-calendar-alt"></i> Financial Periods</h3>
                        <button class="btn btn-primary" data-action="new-period">
                            <i class="fas fa-plus"></i> New Period
                        </button>
                    </div>
                    <div class="data-table-container">
                        <table class="data-table" id="periodsTable">
                            <thead>
                                <tr>
                                    <th>Period Name</th>
                                    <th>Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="periodsBody">
                                <tr><td colspan="6" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        },

        getTaxSettingsModalContent() {
            return `
                <div class="accounting-modal-content-full">
                    <div class="module-header">
                        <h3><i class="fas fa-percentage"></i> Tax Settings</h3>
                        <button class="btn btn-primary" data-action="new-tax-setting">
                            <i class="fas fa-plus"></i> New Tax Setting
                        </button>
                    </div>
                    <div class="data-table-container">
                        <table class="data-table" id="taxSettingsTable">
                            <thead>
                                <tr>
                                    <th>Setting Key</th>
                                    <th>Value</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="taxSettingsBody">
                                <tr><td colspan="5" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        },

        async openJournalEntryModal(entryId = null) {
            const title = entryId ? 'Edit Journal Entry' : 'New Journal Entry';
            const content = this.getJournalEntryModalContent(entryId);
            // Use a dedicated modal ID so Journal Entry styling can be fully scoped
            // without affecting other modals that reuse `accountingModalProfessional`.
            this.showModal(title, content, 'large', 'journalEntryModal');
            
            // Load accounts dropdown and entities
            setTimeout(async () => {
                // Ensure Branch has a valid selected value (required field).
                // Some deployments may still render an empty-value placeholder option; this prevents
                // the browser from blocking submit with "Please select an item in the list."
                const branchSelect = document.getElementById('journalBranchSelect');
                if (branchSelect) {
                    const currentVal = (branchSelect.value || '').toString().trim();
                    if (!currentVal) {
                        const firstValid = Array.from(branchSelect.options || []).find(
                            (o) => o && !o.disabled && (o.value || '').toString().trim() !== ''
                        );
                        if (firstValid) {
                            branchSelect.value = firstValid.value;
                        } else {
                            // Fallback: inject a default "Main Branch" option
                            branchSelect.innerHTML = '<option value="1">Main Branch</option>';
                            branchSelect.value = '1';
                        }
                        branchSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                // Populate currency dropdown first
                const currencySelect = document.querySelector('#journalEntryForm select[name="currency"]') || document.getElementById('journalEntryCurrencySelect');
                            if (currencySelect && window.currencyUtils) {
                    try {
                        // Get default currency from system settings or use stored preference
                        const defaultCurrency = this.getDefaultCurrencySync();
                        await window.currencyUtils.populateCurrencySelect(currencySelect, defaultCurrency);
                    } catch (error) {
                        console.error('❌ Error populating journal entry currency dropdown:', error);
                    }
                }
                
                // Load accounts and cost centers for all debit and credit line selects
                const form = document.getElementById('journalEntryForm');
                if (form) {
                    const debitAccountSelects = form.querySelectorAll('#journalDebitLinesBody .account-select');
                    const creditAccountSelects = form.querySelectorAll('#journalCreditLinesBody .account-select');
                    
                    for (const select of debitAccountSelects) {
                        await this.loadAccountsForSelect(null, select);
                    }
                    for (const select of creditAccountSelects) {
                        await this.loadAccountsForSelect(null, select);
                    }
                    
                    // Load cost centers for all debit and credit line selects
                    const debitCostCenterSelects = form.querySelectorAll('#journalDebitLinesBody .cost-center-select');
                    const creditCostCenterSelects = form.querySelectorAll('#journalCreditLinesBody .cost-center-select');
                    
                    for (const select of debitCostCenterSelects) {
                        await this.populateCostCenterSelect(select);
                    }
                    for (const select of creditCostCenterSelects) {
                        await this.populateCostCenterSelect(select);
                    }
                }
                
                // Setup customer fields
                if (form) {
                    this.setupCustomerFields('journalCustomersContainer');
                }
                
                // Setup date sync logic
                // Reuse form variable already declared above
                if (form) {
                    const entryDateInput = form.querySelector('#journalEntryDate');
                    const documentDateInput = form.querySelector('#journalDocumentDate');
                    const postingDateInput = form.querySelector('#journalPostingDate');
                    
                    // Sync Document Date with Entry Date
                    if (entryDateInput && documentDateInput) {
                        entryDateInput.addEventListener('change', () => {
                            if (!documentDateInput.value || documentDateInput.value === '') {
                                documentDateInput.value = entryDateInput.value;
                                // Re-initialize Flatpickr if needed
                                if (typeof window.initializeEnglishDatePickers === 'function') {
                                    setTimeout(() => window.initializeEnglishDatePickers(documentDateInput.parentElement), 100);
                                }
                            }
                        });
                        // Set initial value
                        if (!documentDateInput.value || documentDateInput.value === '') {
                            documentDateInput.value = entryDateInput.value;
                        }
                    }
                    
                    // Sync Posting Date with Entry Date (only if not readonly)
                    if (entryDateInput && postingDateInput && !postingDateInput.readOnly) {
                        entryDateInput.addEventListener('change', () => {
                            if (!postingDateInput.value || postingDateInput.value === '') {
                                postingDateInput.value = entryDateInput.value;
                                // Re-initialize Flatpickr if needed
                                if (typeof window.initializeEnglishDatePickers === 'function') {
                                    setTimeout(() => window.initializeEnglishDatePickers(postingDateInput.parentElement), 100);
                                }
                            }
                        });
                        // Set initial value
                        if (!postingDateInput.value || postingDateInput.value === '') {
                            postingDateInput.value = entryDateInput.value;
                        }
                    }
                }
                
                // If editing, load entry data after accounts are loaded
                if (entryId) {
                    try {
                        // Request entry lines so we can populate Debit/Credit rows in the edit form
                        const response = await fetch(`${this.apiBase}/journal-entries.php?id=${entryId}&lines=true`);
                        const data = await response.json();
                        
                        if (data.success && data.entry) {
                            const entry = data.entry;
                            const form = document.getElementById('journalEntryForm');
                            if (!form) {
                                console.error('Journal entry form not found');
                                return;
                            }
                            
                            // Wait a bit more to ensure dropdowns are fully loaded
                            await new Promise(resolve => setTimeout(resolve, 150));
                            
                            // Populate currency dropdown with the entry's currency before setting value
                            const currencySelect = form.querySelector('select[name="currency"]');
                            if (currencySelect && entry.currency && window.currencyUtils) {
                                try {
                                    let currencyValue = entry.currency;
                                    if (currencyValue.includes(' - ')) {
                                        currencyValue = currencyValue.split(' - ')[0].trim();
                                    }
                                    await window.currencyUtils.populateCurrencySelect(currencySelect, currencyValue);
                                } catch (error) {
                                    console.error('❌ Error populating currency for edit:', error);
                                }
                            }
                            
                            // Populate form fields
                            const entryDateInput = form.querySelector('input[name="entry_date"]');
                            const descriptionInput = form.querySelector('textarea[name="description"]');
                            const accountSelect = form.querySelector('select[name="account_id"]');
                            const debitInput = form.querySelector('input[name="debit"]');
                            const creditInput = form.querySelector('input[name="credit"]');
                            const entryTypeSelect = form.querySelector('select[name="entry_type"]') || form.querySelector('#journalEntryType');
                            const statusSelect = form.querySelector('select[name="status"]') || form.querySelector('#journalEntryStatus');
                            
                            if (entryDateInput && entry.entry_date) {
                                entryDateInput.value = this.formatDateForInput(entry.entry_date);
                            }
                            if (descriptionInput && entry.description) {
                                descriptionInput.value = entry.description;
                            }
                            // NEW: Populate debit/credit lines for the multi-line Journal Entry form
                            // (the old single-line fields do not exist in this form layout)
                            if (Array.isArray(data.lines)) {
                                await this.populateJournalEntryEditForm(entry, data.lines);
                            }
                            if (accountSelect && entry.account_id) {
                                // Convert to string and ensure it's set
                                const accountIdStr = entry.account_id.toString();
                                // Check if the option exists, if not wait a bit more
                                const optionExists = Array.from(accountSelect.options).some(opt => opt.value === accountIdStr);
                                if (optionExists) {
                                    accountSelect.value = accountIdStr;
                                } else {
                                    // Wait a bit more for accounts to load
                                    setTimeout(() => {
                                        accountSelect.value = accountIdStr;
                                    }, 200);
                                }
                            }
                            if (debitInput) {
                                const debitVal = parseFloat(entry.total_debit) || 0;
                                debitInput.value = debitVal > 0 ? debitVal.toFixed(2) : '0.00';
                            }
                            if (creditInput) {
                                const creditVal = parseFloat(entry.total_credit) || 0;
                                creditInput.value = creditVal > 0 ? creditVal.toFixed(2) : '0.00';
                            }
                            // Currency is already set by populateCurrencySelect above
                            
                            // Populate new fields
                            const documentDateInput = form.querySelector('#journalDocumentDate');
                            const postingDateInput = form.querySelector('#journalPostingDate');
                            const entryNumberInput = form.querySelector('#journalEntryNumber');
                            const statusDisplay = document.getElementById('journalEntryStatusDisplay');
                            const sourceModuleSelect = form.querySelector('#journalSourceModule');
                            const sourceReferenceInput = form.querySelector('#journalSourceReferenceId');
                            const costCenterSelect = form.querySelector('#journalCostCenterSelect');
                            const lineNarrationInput = form.querySelector('#journalLineNarration');
                            
                            if (documentDateInput && entry.entry_date) {
                                documentDateInput.value = this.formatDateForInput(entry.entry_date);
                            }
                            if (postingDateInput && entry.entry_date) {
                                postingDateInput.value = this.formatDateForInput(entry.entry_date);
                            }
                            if (postingDateInput && entry.posting_date) {
                                postingDateInput.value = this.formatDateForInput(entry.posting_date);
                            }
                            if (entryNumberInput && entry.entry_number) {
                                entryNumberInput.value = entry.entry_number;
                            }
                            if (statusDisplay && entry.status) {
                                const status = entry.status;
                                const statusClass = status.toLowerCase() === 'posted' ? 'status-posted' : status.toLowerCase() === 'approved' ? 'status-approved' : 'status-draft';
                                statusDisplay.innerHTML = `<span class="status-badge ${statusClass}">${this.escapeHtml(status)}</span>`;
                            }
                            if (sourceModuleSelect && entry.entry_type) {
                                sourceModuleSelect.value = entry.entry_type || 'Manual';
                            }
                            if (sourceReferenceInput && entry.reference_number) {
                                sourceReferenceInput.value = entry.reference_number;
                            }
                            
                            // Populate cost center if available
                            if (costCenterSelect && entry.cost_center_id) {
                                const costCenterIdStr = entry.cost_center_id.toString();
                                // Wait for cost centers to load, then set value
                                setTimeout(() => {
                                    const optionExists = Array.from(costCenterSelect.options).some(opt => opt.value === costCenterIdStr);
                                    if (optionExists) {
                                        costCenterSelect.value = costCenterIdStr;
                                    } else {
                                        // Try again after a short delay
                                        setTimeout(() => {
                                            costCenterSelect.value = costCenterIdStr;
                                        }, 200);
                                    }
                                }, 100);
                            }
                            
                            // Populate line narration if available (from journal_entry_lines)
                            // Note: Backend doesn't support line_narration yet, but we can try to get it
                            if (lineNarrationInput) {
                                // Line narration would come from journal_entry_lines if backend supported it
                                // For now, leave empty or use description as fallback
                                if (entry.line_narration) {
                                    lineNarrationInput.value = entry.line_narration;
                                }
                            }
                            
                            // Load approval data if entry is approved/posted
                            if (entry.status && (entry.status.toLowerCase() === 'approved' || entry.status.toLowerCase() === 'posted')) {
                                const approvalSection = document.getElementById('journalApprovalSection');
                                if (approvalSection) {
                                    approvalSection.style.display = 'block';
                                    // Fetch approval data from entry_approval table
                                    try {
                                        const approvalResponse = await fetch(`${this.apiBase}/entry-approval.php?journal_entry_id=${entry.id}`);
                                        const approvalData = await approvalResponse.json();
                                        if (approvalData.success && approvalData.approval) {
                                            const approvedByEl = document.getElementById('journalApprovedBy');
                                            const approvedDateEl = document.getElementById('journalApprovedDate');
                                            const approvalNotesEl = document.getElementById('journalApprovalNotes');
                                            if (approvedByEl && approvalData.approval.approved_by_name) {
                                                approvedByEl.value = approvalData.approval.approved_by_name;
                                            }
                                            if (approvedDateEl && approvalData.approval.approved_at) {
                                                approvedDateEl.value = this.formatDate(approvalData.approval.approved_at);
                                            }
                                            if (approvalNotesEl && approvalData.approval.rejection_reason) {
                                                approvalNotesEl.value = approvalData.approval.rejection_reason;
                                            }
                                        }
                                    } catch (err) {
                                        console.error('Error loading approval data:', err);
                                    }
                                }
                            }
                            
                            // Update balance display after setting values
                            setTimeout(() => {
                                const debitInputEl = form.querySelector('#journalDebitAmount');
                                const creditInputEl = form.querySelector('#journalCreditAmount');
                                if (debitInputEl && creditInputEl) {
                                    debitInputEl.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                            }, 200);
                            
                            // Set Entry Type
                            if (entryTypeSelect && entry.entry_type) {
                                const validTypes = ['Manual', 'Automatic', 'Recurring', 'Adjustment', 'Reversal'];
                                const entryType = entry.entry_type.trim();
                                // Normalize to match dropdown values
                                const normalizedType = validTypes.find(t => t.toLowerCase() === entryType.toLowerCase()) || 'Manual';
                                entryTypeSelect.value = normalizedType;
                            }
                            
                            // Set Status (hidden field - status is managed automatically)
                            if (statusSelect && entry.status) {
                                // Status field is hidden, but we can still set it if it exists
                                const statusValue = entry.status.trim();
                                const validStatuses = ['Draft', 'Posted'];
                                const normalizedStatus = validStatuses.find(s => s.toLowerCase() === statusValue.toLowerCase()) || 'Draft';
                                statusSelect.value = normalizedStatus;
                            }
                            
                        } else {
                            this.showToast('Failed to load entry data', 'error');
                        }
                    } catch (error) {
                        this.showToast('Error loading entry data', 'error');
                    }
                }
            }, 200);
        },

        async generateReport(reportType) {
            // Get report name using centralized function
            const reportName = this.getReportName(reportType);
            
            try {
                // Check if there's already a modal open - if so, update it smoothly
                const existingModal = document.querySelector('.accounting-modal.accounting-modal-visible, .accounting-modal.show-modal');
                let modalBody = null;
                
                if (existingModal) {
                    modalBody = existingModal.querySelector('.accounting-modal-body');
                    if (modalBody) {
                        // Show loading state smoothly
                        modalBody.classList.add('opacity-loading');
                        modalBody.innerHTML = `
                            <div class="accounting-module-modal-content">
                                <div class="module-content report-loading-container">
                                    <div class="report-loading-content">
                                        <i class="fas fa-spinner fa-spin report-loading-spinner"></i>
                                        <h3>Generating ${reportName}...</h3>
                                        <p>Please wait while we prepare your report</p>
                                    </div>
                                </div>
                            </div>
                        `;
                        // Update modal title
                        const modalHeader = existingModal.querySelector('.accounting-modal-header h3');
                        if (modalHeader) {
                            modalHeader.innerHTML = `<i class="fas fa-file-alt"></i> ${reportName}`;
                        }
                    }
                } else {
                    // Show loading in a new popup modal
                    const loadingContent = `
                        <div class="accounting-module-modal-content">
                            <div class="module-content report-loading-container">
                                <div class="report-loading-content">
                                    <i class="fas fa-spinner fa-spin report-loading-spinner"></i>
                                    <h3>Generating ${reportName}...</h3>
                                    <p>Please wait while we prepare your report</p>
                                </div>
                            </div>
                        </div>
                    `;
                    this.showModal(reportName, loadingContent, 'large');
                    modalBody = document.querySelector('.accounting-modal.accounting-modal-visible .accounting-modal-body, .accounting-modal.show-modal .accounting-modal-body');
                }
                // Build query parameters
                const params = new URLSearchParams();
                params.append('type', reportType);
                
                // Get date parameters from UI if available, or use defaults
                const startDateInput = document.getElementById('reportStartDate');
                const endDateInput = document.getElementById('reportEndDate');
                const asOfDateInput = document.getElementById('reportAsOfDate');
                const accountSelect = document.getElementById('reportAccountSelect');
                
                // Determine which date parameters to use based on report type
                const needsDateRange = ['income-statement', 'profit-loss', 'cash-flow', 'cash-book', 'bank-book', 
                    'general-ledger', 'general-ledger-report', 'account-statement', 'expense-statement', 
                    'value-added', 'entries-by-year', 'changes-equity', 'financial-performance', 'comparative-report'].includes(reportType);
                const needsAsOfDate = ['trial-balance', 'balance-sheet', 'aged-receivables', 'ages-debt-receivable', 
                    'ages-credit-receivable', 'aged-payables', 'chart-of-accounts-report', 'fixed-assets', 
                    'customer-debits', 'statistical-position'].includes(reportType);
                
                if (needsDateRange) {
                    if (startDateInput && startDateInput.value) {
                        params.append('start_date', startDateInput.value);
                    }
                    if (endDateInput && endDateInput.value) {
                        params.append('end_date', endDateInput.value);
                    }
                } else if (needsAsOfDate) {
                    if (asOfDateInput && asOfDateInput.value) {
                        params.append('as_of', asOfDateInput.value);
                    }
                }
                
                // Add account_id if available and needed
                if (accountSelect && accountSelect.value && accountSelect.value !== '' && ['general-ledger', 'general-ledger-report', 'account-statement'].includes(reportType)) {
                    const accountId = parseInt(accountSelect.value);
                    if (!isNaN(accountId) && accountId > 0) {
                        params.append('account_id', accountId);
                    }
                }
                
                // Add search_term if available (for backend filtering on large datasets)
                const searchInput = document.getElementById('reportSearchInput');
                if (searchInput && searchInput.value && searchInput.value.trim() !== '') {
                    params.append('search_term', searchInput.value.trim());
                }
                // Fetch report data
                const response = await fetch(`${this.apiBase}/reports.php?${params.toString()}`, {
                    credentials: 'include'
                });
                
                // Check if response is ok
                if (!response.ok) {
                    let errorMessage = `Report API error: ${response.status}`;
                    try {
                        const errorData = await response.json();
                        if (errorData.message) {
                            errorMessage = errorData.message;
                        }
                    } catch (e) {
                        // If JSON parsing fails, try to get text response
                        try {
                    const errorText = await response.text();
                            if (errorText) {
                                errorMessage = errorText.substring(0, 200);
                            }
                        } catch (textError) {
                            // Ignore text parsing errors
                        }
                    }
                    throw new Error(errorMessage);
                }
                
                // Try to parse as JSON
                let data;
                try {
                    data = await response.json();
                } catch (e) {
                    throw new Error('Invalid JSON response from report API');
                }
                // Always display report table, even if data is empty
                const reportData = (data.success && data.report) ? data.report : {};
                
                // Different report types use different data structures
                const reportsWithAccounts = ['trial-balance', 'general-ledger'];
                const hasAccounts = reportData.accounts && Array.isArray(reportData.accounts);
                
                if (reportsWithAccounts.includes(reportType)) {
                    if (!hasAccounts) {
                        console.warn(reportType + ' report missing accounts array');
                    } else if (reportData.accounts.length === 0) {
                        console.warn('Report returned 0 accounts.');
                    }
                } else {
                    // Other reports use different structures - check what they have
                }
                
                if (data.debug) {
                    // debug available but not logged to reduce console noise
                    if (data.debug.total_accounts_in_db !== undefined) {
                        // not logged to reduce console noise
                    }
                    if (data.debug.accounts_count !== undefined) {
                        // not logged to reduce console noise
                    }
                    if (data.debug.table_exists_check !== undefined) {
                        // not logged to reduce console noise
                    }
                }
                
                // Setup handlers after report is displayed
                setTimeout(() => {
                    this.displayReportInPopupSmooth(reportType, reportName, reportData, existingModal);
                    if (data.success && data.report) {
                        this.showToast(`${reportName} report generated successfully!`, 'success');
                    } else {
                        this.showToast(`${reportName} report displayed (no data available)`, 'info');
                    }
                }, 100);
            } catch (error) {
                console.error('❌ Error generating report:', error);
                // Show report table with empty data instead of placeholder
                const existingModal = document.querySelector('.accounting-modal.accounting-modal-visible, .accounting-modal.show-modal');
                this.displayReportInPopupSmooth(reportType, reportName, {}, existingModal);
                this.showToast(`${reportName} report displayed (no data available)`, 'info');
            }
        },

        async loadInvoices() {
            const tbody = document.getElementById('invoicesBody');
            if (!tbody) return;
            // Show loading state
            tbody.innerHTML = '<tr><td colspan="9" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
            try {
                const response = await fetch(`${this.apiBase}/invoices.php`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                if (data.success && data.invoices) {
                    // Update summary cards
                    const totalOutstanding = data.summary?.total_outstanding || 0;
                    const overdue = data.summary?.overdue || 0;
                    const thisMonth = data.summary?.this_month || 0;
                    const totalEl = document.getElementById('arTotalOutstanding');
                    if (totalEl) totalEl.textContent = this.formatCurrency(totalOutstanding);
                    
                    const overdueEl = document.getElementById('arOverdue');
                    if (overdueEl) overdueEl.textContent = this.formatCurrency(overdue);
                    
                    const monthEl = document.getElementById('arThisMonth');
                    if (monthEl) monthEl.textContent = this.formatCurrency(thisMonth);
                    // Update table
                    if (data.invoices.length > 0) {
                        tbody.innerHTML = data.invoices.map(inv => {
                            // Calculate debit/credit: Invoices are receivables (credit), payments are debits
                            const debitAmount = parseFloat(inv.debit_amount || inv.paid_amount || 0);
                            const creditAmount = parseFloat(inv.credit_amount || (inv.total_amount && inv.paid_amount === 0 ? inv.total_amount : 0));
                            
                            return `
                            <tr>
                                <td>${this.escapeHtml(inv.invoice_number)}</td>
                                <td>${inv.invoice_date}</td>
                                <td>${this.escapeHtml(inv.customer_name || 'N/A')}</td>
                                <td>${inv.due_date}</td>
                                <td class="debit-cell">${debitAmount > 0 ? this.formatCurrency(debitAmount) : '-'}</td>
                                <td class="credit-cell">${creditAmount > 0 ? this.formatCurrency(creditAmount) : '-'}</td>
                                <td>${this.formatCurrency(inv.paid_amount)}</td>
                                <td>${this.formatCurrency(inv.balance_amount)}</td>
                                <td><span class="status-badge ${inv.status.toLowerCase().replace(' ', '-')}">${inv.status}</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn view" data-action="view-invoice" data-id="${inv.id}" data-permission="view_receivables" title="View Invoice">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn edit" data-action="edit-invoice" data-id="${inv.id}" data-permission="edit_receivable" title="Edit Invoice">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn print" data-action="print-invoice" data-id="${inv.id}" title="Print Invoice">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button class="action-btn delete" data-action="delete-invoice" data-id="${inv.id}" data-permission="delete_receivable" title="Delete Invoice">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                        }).join('');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="10" class="text-center"><div class="accounting-empty-state"><i class="fas fa-file-invoice accounting-empty-state-icon"></i><p class="accounting-empty-state-text">No invoices found</p></div></td></tr>';
                    }
                } else {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center"><div class="accounting-empty-state"><i class="fas fa-file-invoice accounting-empty-state-icon"></i><p class="accounting-empty-state-text">No invoices found</p></div></td></tr>';
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error loading invoices: ${error.message}</td></tr>`;
                this.showToast('Failed to load invoices. Please try again.', 'error');
            } finally {
                // Apply permissions after table is rendered
                setTimeout(() => {
                    if (window.UserPermissions && window.UserPermissions.loaded) {
                        window.UserPermissions.applyPermissions();
                    }
                }, 50);
            }
        },

        async loadBills() {
            const tbody = document.getElementById('billsBody');
            if (!tbody) return;
            // Show loading state
            tbody.innerHTML = '<tr><td colspan="10" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
            try {
                const response = await fetch(`${this.apiBase}/bills.php`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadBills:', jsonError);
                    data = { success: false, bills: [] };
                }
                if (data.success && data.bills) {
                    // Update summary cards
                    const totalOutstanding = data.summary?.total_outstanding || 0;
                    const overdue = data.summary?.overdue || 0;
                    const thisMonth = data.summary?.this_month || 0;
                    const totalEl = document.getElementById('apTotalOutstanding');
                    if (totalEl) totalEl.textContent = this.formatCurrency(totalOutstanding);
                    
                    const overdueEl = document.getElementById('apOverdue');
                    if (overdueEl) overdueEl.textContent = this.formatCurrency(overdue);
                    
                    const monthEl = document.getElementById('apThisMonth');
                    if (monthEl) monthEl.textContent = this.formatCurrency(thisMonth);
                    // Update table
                    if (data.bills.length > 0) {
                        tbody.innerHTML = data.bills.map(bill => `
                            <tr>
                                <td>${this.escapeHtml(bill.bill_number || 'N/A')}</td>
                                <td>${bill.bill_date ? this.formatDate(bill.bill_date) : ''}</td>
                                <td>${this.escapeHtml(bill.vendor_name || 'N/A')}</td>
                                <td>${bill.due_date ? this.formatDate(bill.due_date) : ''}</td>
                                <td>${this.formatCurrency(bill.total_amount || 0)}</td>
                                <td>${this.formatCurrency(bill.paid_amount || 0)}</td>
                                <td>${this.formatCurrency(bill.balance_amount || 0)}</td>
                                <td><span class="status-badge ${(bill.status || 'Draft').toLowerCase().replace(' ', '-')}">${bill.status || 'Draft'}</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn view" data-action="view-bill" data-id="${bill.id}" data-permission="view_payables" title="View Bill">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="action-btn edit" data-action="edit-bill" data-id="${bill.id}" data-permission="edit_payable" title="Edit Bill">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn print" data-action="print-bill" data-id="${bill.id}" title="Print Bill">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button class="action-btn delete" data-action="delete-bill" data-id="${bill.id}" data-permission="delete_payable" title="Delete Bill">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `).join('');
                    } else {
                        tbody.innerHTML = '<tr><td colspan="9" class="text-center"><div class="accounting-empty-state"><i class="fas fa-file-invoice-dollar accounting-empty-state-icon"></i><p class="accounting-empty-state-text">No bills found</p></div></td></tr>';
                    }
                } else {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center"><div class="accounting-empty-state"><i class="fas fa-file-invoice-dollar accounting-empty-state-icon"></i><p class="accounting-empty-state-text">No bills found</p></div></td></tr>';
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error loading bills: ${error.message}</td></tr>`;
                this.showToast('Failed to load bills. Please try again.', 'error');
            } finally {
                // Apply permissions after table is rendered
                setTimeout(() => {
                    if (window.UserPermissions && window.UserPermissions.loaded) {
                        window.UserPermissions.applyPermissions();
                    }
                }, 50);
            }
        },

        async loadJournalEntries() {
            const dateFrom = document.getElementById('ledgerDateFrom')?.value;
            const dateTo = document.getElementById('ledgerDateTo')?.value;
            const accountId = document.getElementById('ledgerAccount')?.value;
            const tbody = document.getElementById('journalEntriesBody');
            if (!tbody) {
                return;
            }
            // Show loading state
            tbody.innerHTML = '<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
            const params = new URLSearchParams();
            if (dateFrom) params.append('date_from', this.formatDateForAPI(dateFrom));
            if (dateTo) params.append('date_to', this.formatDateForAPI(dateTo));
            if (accountId) params.append('account_id', accountId);
            // Include Draft so newly-created journal entries show up immediately in the table
            params.append('include_draft', '1');
            // Add cache-busting parameter
            params.append('_t', Date.now());
            const apiUrl = `${this.apiBase}/journal-entries.php?${params.toString()}`;
            try {
                const response = await fetch(apiUrl, {
                    cache: 'no-store',
                    headers: {
                        'Cache-Control': 'no-cache, no-store, must-revalidate',
                        'Pragma': 'no-cache'
                    }
                });
                
                if (!response.ok) {
                    const errorData = await response.json().catch(() => null);
                    throw new Error(`HTTP ${response.status}: ${errorData?.message || errorData?.error || 'Unknown error'}`);
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadFinancialOverview:', jsonError);
                    throw new Error('Invalid JSON response from server');
                }
                if (data.success && data.entries && data.entries.length > 0) {
                    tbody.innerHTML = data.entries.map(entry => {
                        // Format description to include entity info if available
                        let description = this.escapeHtml(entry.description || '');
                        if (entry.entity_type && entry.entity_id) {
                            const entityType = entry.entity_type.charAt(0).toUpperCase() + entry.entity_type.slice(1);
                            description += ` <span class="badge badge-info badge-small">${entityType} #${entry.entity_id}</span>`;
                        }
                        
                        // Format entity display
                        let entityDisplay = '-';
                        if (entry.entity_name) {
                            entityDisplay = this.escapeHtml(entry.entity_name);
                        } else if (entry.entity_type && entry.entity_id) {
                            const entityType = entry.entity_type.charAt(0).toUpperCase() + entry.entity_type.slice(1);
                            entityDisplay = `${entityType} #${entry.entity_id}`;
                        }
                        
                        // Format entity type display
                        let entityTypeDisplay = '-';
                        if (entry.entity_type) {
                            entityTypeDisplay = entry.entity_type.charAt(0).toUpperCase() + entry.entity_type.slice(1);
                        }
                        
                        // Format account display
                        let accountDisplay = '-';
                        if (entry.account_name) {
                            accountDisplay = this.escapeHtml(entry.account_name);
                        } else if (entry.account_id) {
                            accountDisplay = `Account #${entry.account_id}`;
                        }
                        
                        
                        return `
                        <tr>
                            <td>${this.escapeHtml(entry.entry_number || 'N/A')}</td>
                            <td>${entry.entry_date ? this.formatDate(entry.entry_date) : ''}</td>
                            <td>${this.escapeHtml(entityTypeDisplay)}</td>
                            <td>${entityDisplay}</td>
                            <td>${description}</td>
                            <td>${accountDisplay}</td>
                            <td>${this.escapeHtml(entry.entry_type || 'Manual')}</td>
                            <td class="debit-cell amount-cell ${(parseFloat(entry.total_debit) || 0) > 0 ? 'has-amount' : ''}">${(parseFloat(entry.total_debit) || 0) > 0 ? this.formatCurrency(entry.total_debit || 0) : '<span class="text-muted">-</span>'}</td>
                            <td class="credit-cell amount-cell ${(parseFloat(entry.total_credit) || 0) > 0 ? 'has-amount' : ''}">${(parseFloat(entry.total_credit) || 0) > 0 ? this.formatCurrency(entry.total_credit || 0) : '<span class="text-muted">-</span>'}</td>
                            <td><span class="status-badge ${(entry.status || 'Draft').toLowerCase()}">${((entry.status || 'Draft').toLowerCase() === 'draft') ? 'Waiting for approval' : (entry.status || 'Draft')}</span></td>
                            <td>
                                <input type="checkbox" class="entry-checkbox" data-entry-id="${entry.id}" data-action="select-entry">
                            </td>
                            <td>
                                ${((entry.status || 'Draft').toLowerCase() === 'posted') ? `
                                <div class="action-buttons">
                                    <button class="action-btn view" data-action="view-entry" data-id="${entry.id}" data-source="${entry.source || 'journal'}" data-permission="view_journal_entries" title="View Entry">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    ${entry.source === 'transaction' ? `<button class="action-btn edit" data-action="edit-entity-transaction" data-id="${entry.id}" data-permission="edit_journal_entry" title="Edit Entry"><i class="fas fa-edit"></i></button>` : `<button class="action-btn edit" data-action="edit-entry" data-id="${entry.id}" data-permission="edit_journal_entry" title="Edit Entry"><i class="fas fa-edit"></i></button>`}
                                    <button class="action-btn print" data-action="print-entry" data-id="${entry.id || entry.entry_number || ''}" data-source="${entry.source || 'journal'}" title="Print Entry">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="action-btn delete" data-action="delete-entry" data-id="${entry.id}" data-source="${entry.source || 'journal'}" data-permission="delete_journal_entry" title="Delete Entry">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                ` : '<span class="text-muted">-</span>'}
                            </td>
                        </tr>
                    `;
                    }).join('');
                } else {
                    tbody.innerHTML = '<tr><td colspan="12" class="text-center"><div class="accounting-empty-state"><i class="fas fa-book accounting-empty-state-icon"></i><p class="accounting-empty-state-text">No journal entries found</p></div></td></tr>';
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="12" class="text-center text-danger">Error loading journal entries: ${error.message}</td></tr>`;
                this.showToast('Failed to load journal entries. Please try again.', 'error');
            } finally {
                // Apply permissions after table is rendered
                setTimeout(() => {
                    if (window.UserPermissions && window.UserPermissions.loaded) {
                        window.UserPermissions.applyPermissions();
                    }
                }, 50);
            }
        },

        async loadBankAccounts() {
            // Prevent multiple simultaneous calls
            if (this._loadingBankAccounts) {
                return;
            }
            this._loadingBankAccounts = true;
            
            // Try both table body and list container for backward compatibility
            const tbody = document.getElementById('bankAccountsTableBody');
            const containerEl = document.getElementById('bankAccountsList');
            const targetEl = tbody || containerEl;
            
            if (!targetEl) {
                this._loadingBankAccounts = false;
                return;
            }
            // Show loading state
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading bank accounts...</td></tr>';
            } else if (containerEl) {
            containerEl.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
            }
            try {
                const response = await fetch(`${this.apiBase}/banks.php`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadBankAccounts:', jsonError);
                    data = { success: false, banks: [] };
                }
                if (data.success && data.banks && data.banks.length > 0) {
                    // Store all accounts for filtering/sorting
                    this.bankingAllAccounts = data.banks;
                    
                    // Update status cards
                    this.updateBankingStatusCards(data.banks);
                    
                    if (tbody) {
                        // Apply filters and search
                        let filteredBanks = this.filterAndSortBankAccounts(data.banks);
                        
                        // Store total count
                        this.bankingTotalCount = filteredBanks.length;
                        this.bankingTotalPages = Math.ceil(this.bankingTotalCount / this.bankingPerPage);
                        
                        // Apply pagination
                        const startIndex = (this.bankingCurrentPage - 1) * this.bankingPerPage;
                        const endIndex = startIndex + this.bankingPerPage;
                        const paginatedBanks = filteredBanks.slice(startIndex, endIndex);
                        
                        // Render as table with formatted ID (BA001, BA002, etc.) and newest first
                        tbody.innerHTML = paginatedBanks.map((bank, index) => {
                            // Format ID as BA001, BA002, etc.
                            const formattedId = `BA${String(bank.id || index + 1).padStart(3, '0')}`;
                            const isSelected = this.bankingSelectedAccounts.has(bank.id);
                            return `
                            <tr data-bank-id="${bank.id}" class="${isSelected ? 'row-selected' : ''}">
                                <td><strong>${formattedId}</strong></td>
                                <td>${this.escapeHtml(bank.bank_name || 'N/A')}</td>
                                <td>${this.escapeHtml(bank.account_name || 'N/A')}</td>
                                <td>${this.escapeHtml(bank.account_number || 'N/A')}</td>
                                <td>
                                    <span class="badge badge-info">${this.escapeHtml(bank.account_type || 'Checking')}</span>
                                </td>
                                <td class="amount-column">${this.formatCurrency(bank.opening_balance || 0, bank.currency || this.getDefaultCurrencySync())}</td>
                                <td class="amount-column">
                                    <strong>${this.formatCurrency(bank.current_balance || 0, bank.currency || this.getDefaultCurrencySync())}</strong>
                                </td>
                                <td>
                                    <span class="badge badge-${bank.is_active ? 'success' : 'danger'}" style="${!bank.is_active ? 'background-color: #dc3545; color: white;' : ''}">
                                        ${bank.is_active ? 'Active' : 'Inactive'}
                                    </span>
                                </td>
                                <td class="checkbox-column">
                                    <input type="checkbox" class="bank-account-checkbox" data-bank-id="${bank.id}" ${isSelected ? 'checked' : ''}>
                                </td>
                                <td class="actions-column">
                                    <button class="btn btn-sm btn-info" data-action="view-bank-account" data-id="${bank.id}" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning" data-action="edit-bank-account" data-id="${bank.id}" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" data-action="delete-bank-account" data-id="${bank.id}" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        }).join('');
                        
                        // Update pagination controls
                        this.updateBankingPaginationControls();
                        
                        // Adjust scrolling based on entries per page after rendering
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
                        
                        // Setup action handlers for table
                        this.setupBankAccountActions();
                        // Remove listener flag so it can be re-attached after re-render
                        if (tbody) {
                            tbody.removeAttribute('data-bulk-listener-attached');
                        }
                        this.setupBankingBulkActions();
                        this.updateBankingBulkActionsBar();
                        this.updateSelectAllCheckbox();
                    } else if (containerEl) {
                        // Fallback to card layout for backward compatibility
                    containerEl.innerHTML = data.banks.map(bank => `
                        <div class="dashboard-widget">
                            <h3>${this.escapeHtml(bank.bank_name || 'N/A')} - ${this.escapeHtml(bank.account_name || 'N/A')}</h3>
                            <p>Account: ${this.escapeHtml(bank.account_number || 'N/A')}</p>
                                <p><strong>Balance: ${this.formatCurrency(bank.current_balance || 0, bank.currency || this.getDefaultCurrencySync())}</strong></p>
                        </div>
                    `).join('');
                    }
                } else {
                    this.bankingAllAccounts = [];
                    this.bankingTotalCount = 0;
                    this.bankingTotalPages = 0;
                    if (tbody) {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="10" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-university empty-state-icon"></i>
                                        <p class="empty-state-text">No bank accounts found</p>
                                        <button class="btn btn-primary btn-sm mt-2" data-action="new-bank-account">
                                            <i class="fas fa-plus"></i> Create First Bank Account
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                        this.updateBankingPaginationControls();
                    } else if (containerEl) {
                    containerEl.innerHTML = '<div class="accounting-empty-state"><i class="fas fa-university accounting-empty-state-icon"></i><p class="accounting-empty-state-text">No bank accounts found</p></div>';
                    }
                    
                    // Update status cards with empty data
                    this.updateBankingStatusCards([]);
                }
            } catch (error) {
                console.error('Error loading bank accounts:', error);
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger"><i class="fas fa-exclamation-circle"></i> Error loading bank accounts: ${this.escapeHtml(error.message)}</td></tr>`;
                } else if (containerEl) {
                containerEl.innerHTML = `<div class="text-danger">Error loading bank accounts: ${error.message}</div>`;
                }
                this.showToast('Failed to load bank accounts. Please try again.', 'error');
            } finally {
                this._loadingBankAccounts = false;
            }
        }
    };
    Object.assign(ProfessionalAccounting.prototype, methods);
})();
