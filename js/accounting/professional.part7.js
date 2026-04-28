/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.part7.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.part7.js`.
 */
/** Professional Accounting - Part 7 (lines 30199-30450) */
ProfessionalAccounting.prototype.updateCostCentersPagination = function() {
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
    }

ProfessionalAccounting.prototype.renderBankGuaranteeTable = function() {
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
    }

ProfessionalAccounting.prototype.updateBankGuaranteePagination = function() {
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
    }

ProfessionalAccounting.prototype.renderEntryApprovalTable = function() {
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
    }

ProfessionalAccounting.prototype.updateEntryApprovalPagination = function() {
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
    }
