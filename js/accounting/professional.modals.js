/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.modals.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.modals.js`.
 */
/**
 * Professional Accounting - Modals
 * Load AFTER professional.js
 */
(function(){
    if (typeof ProfessionalAccounting === 'undefined') return;
    const methods = {
        clearLedgerFilters(expandRange = false) {
            const dateFromEl = document.getElementById('modalLedgerDateFrom');
            const dateToEl = document.getElementById('modalLedgerDateTo');
            const accountEl = document.getElementById('modalLedgerAccount');
            const searchEl = document.getElementById('modalLedgerSearch');
            
            if (dateFromEl) {
                if (expandRange) {
                    const firstDay = new Date();
                    firstDay.setDate(1);
                    dateFromEl.value = this.formatDateForInput(firstDay.toISOString());
                } else {
                    dateFromEl.value = '';
                }
            }
            if (dateToEl) {
                if (expandRange) {
                    const today = new Date();
                    dateToEl.value = this.formatDateForInput(today.toISOString());
                } else {
                    dateToEl.value = '';
                }
            }
            if (accountEl) accountEl.value = '';
            if (searchEl) searchEl.value = '';
            
            this.modalLedgerDateFrom = dateFromEl?.value || '';
            this.modalLedgerDateTo = dateToEl?.value || '';
            this.modalLedgerAccountId = '';
            this.modalLedgerSearch = '';
            this.modalLedgerCurrentPage = 1;
            this.loadModalJournalEntries();
        },

        async loadModalInvoices() {
            const currentPage = this.modalArCurrentPage || 1;
            const perPage = this.modalArPerPage || 5;
            
            // Get filter values
            const dateFromEl = document.getElementById('modalArDateFrom');
            const dateToEl = document.getElementById('modalArDateTo');
            const statusFilterEl = document.getElementById('modalArStatusFilter');
            const searchEl = document.getElementById('modalArSearch');
            
            let dateFrom = dateFromEl?.value || this.modalArDateFrom || '';
            let dateTo = dateToEl?.value || this.modalArDateTo || '';
            const statusFilter = statusFilterEl?.value || '';
            const search = searchEl?.value || this.modalArSearch || '';
            
            // Set default dates if empty
            if (!dateTo) {
                const today = this.formatDateForInput(new Date().toISOString().split('T')[0]);
                dateTo = today;
                if (dateToEl) dateToEl.value = today;
            }
            if (!dateFrom) {
                const ninetyDaysAgo = new Date();
                ninetyDaysAgo.setDate(ninetyDaysAgo.getDate() - 90);
                dateFrom = this.formatDateForInput(ninetyDaysAgo.toISOString().split('T')[0]);
                if (dateFromEl) dateFromEl.value = dateFrom;
            }
            
            const tbody = document.getElementById('modalInvoicesBody');
            if (!tbody) {
                return;
            }
            tbody.innerHTML = '<tr><td colspan="10" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
            try {
                const response = await fetch(`${this.apiBase}/invoices.php`);
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadModalInvoices:', jsonError);
                    data = { success: false, invoices: [] };
                }
                if (data.success && data.invoices && Array.isArray(data.invoices)) {
                    // Filter invoices
                    let filteredInvoices = data.invoices;
                    
                    // Filter by date
                    if (dateFrom) {
                        filteredInvoices = filteredInvoices.filter(inv => {
                            const invDate = new Date(inv.invoice_date);
                            return invDate >= new Date(dateFrom);
                        });
                    }
                    if (dateTo) {
                        filteredInvoices = filteredInvoices.filter(inv => {
                            const invDate = new Date(inv.invoice_date);
                            return invDate <= new Date(dateTo + 'T23:59:59');
                        });
                    }
                    
                    // Filter by status
                    if (statusFilter) {
                        filteredInvoices = filteredInvoices.filter(inv => {
                            const invStatus = (inv.status || '').toLowerCase();
                            if (statusFilter === 'Posted') return invStatus === 'posted';
                            if (statusFilter === 'Draft') return invStatus === 'draft';
                            if (statusFilter === 'Paid') return parseFloat(inv.balance_amount || 0) <= 0;
                            if (statusFilter === 'Unpaid') return parseFloat(inv.balance_amount || 0) > 0;
                            if (statusFilter === 'Overdue') {
                                const dueDate = new Date(inv.due_date);
                                return dueDate < new Date() && parseFloat(inv.balance_amount || 0) > 0;
                            }
                            return true;
                        });
                    }
                    
                    // Filter by search
                    if (search) {
                        const searchLower = search.toLowerCase();
                        filteredInvoices = filteredInvoices.filter(inv => 
                            (inv.invoice_number && inv.invoice_number.toLowerCase().includes(searchLower)) ||
                            (inv.customer_name && inv.customer_name.toLowerCase().includes(searchLower)) ||
                            (inv.status && inv.status.toLowerCase().includes(searchLower))
                        );
                    }
                    // Calculate summary statistics
                    const totalInvoices = filteredInvoices.length;
                    const totalOutstanding = filteredInvoices.reduce((sum, inv) => sum + (parseFloat(inv.balance_amount) || 0), 0);
                    const overdue = filteredInvoices.filter(inv => {
                        const dueDate = new Date(inv.due_date);
                        return dueDate < new Date() && parseFloat(inv.balance_amount || 0) > 0;
                    }).reduce((sum, inv) => sum + (parseFloat(inv.balance_amount) || 0), 0);
                    
                    const thisMonthStart = new Date();
                    thisMonthStart.setDate(1);
                    const thisMonth = filteredInvoices.filter(inv => {
                        const invDate = new Date(inv.invoice_date);
                        return invDate >= thisMonthStart;
                    }).reduce((sum, inv) => sum + (parseFloat(inv.credit_amount) || 0), 0);
                    
                    const posted = filteredInvoices.filter(inv => (inv.status || '').toLowerCase() === 'posted');
                    const draft = filteredInvoices.filter(inv => (inv.status || '').toLowerCase() === 'draft');
                    const paid = filteredInvoices.filter(inv => parseFloat(inv.balance_amount || 0) <= 0);
                    const unpaid = filteredInvoices.filter(inv => parseFloat(inv.balance_amount || 0) > 0);
                    
                    const postedAmount = posted.reduce((sum, inv) => sum + (parseFloat(inv.credit_amount) || 0), 0);
                    const draftAmount = draft.reduce((sum, inv) => sum + (parseFloat(inv.credit_amount) || 0), 0);
                    const paidAmount = paid.reduce((sum, inv) => sum + (parseFloat(inv.paid_amount) || 0), 0);
                    const unpaidAmount = unpaid.reduce((sum, inv) => sum + (parseFloat(inv.balance_amount) || 0), 0);
                    // Update summary cards
                    const totalInvoicesEl = document.getElementById('modalArTotalInvoices');
                    const totalEl = document.getElementById('modalArTotalOutstanding');
                    const overdueEl = document.getElementById('modalArOverdue');
                    const monthEl = document.getElementById('modalArThisMonth');
                    const postedCountEl = document.getElementById('modalArPostedCount');
                    const postedAmountEl = document.getElementById('modalArPostedAmount');
                    const draftCountEl = document.getElementById('modalArDraftCount');
                    const draftAmountEl = document.getElementById('modalArDraftAmount');
                    const paidCountEl = document.getElementById('modalArPaidCount');
                    const paidAmountEl = document.getElementById('modalArPaidAmount');
                    const unpaidCountEl = document.getElementById('modalArUnpaidCount');
                    const unpaidAmountEl = document.getElementById('modalArUnpaidAmount');
                    
                    if (totalInvoicesEl) totalInvoicesEl.textContent = totalInvoices;
                    const defaultCurrency = this.getDefaultCurrencySync();
                    if (totalEl) totalEl.textContent = this.formatCurrency(totalOutstanding, defaultCurrency);
                    if (overdueEl) overdueEl.textContent = this.formatCurrency(overdue, defaultCurrency);
                    if (monthEl) monthEl.textContent = this.formatCurrency(thisMonth, defaultCurrency);
                    if (postedCountEl) postedCountEl.textContent = posted.length;
                    if (postedAmountEl) postedAmountEl.textContent = this.formatCurrency(postedAmount, defaultCurrency);
                    if (draftCountEl) draftCountEl.textContent = draft.length;
                    if (draftAmountEl) draftAmountEl.textContent = this.formatCurrency(draftAmount, defaultCurrency);
                    if (paidCountEl) paidCountEl.textContent = paid.length;
                    if (paidAmountEl) paidAmountEl.textContent = this.formatCurrency(paidAmount, defaultCurrency);
                    if (unpaidCountEl) unpaidCountEl.textContent = unpaid.length;
                    if (unpaidAmountEl) unpaidAmountEl.textContent = this.formatCurrency(unpaidAmount, defaultCurrency);
                    // Calculate pagination
                    const totalEntries = filteredInvoices.length;
                    this.modalArTotalPages = Math.ceil(totalEntries / perPage);
                    const startIndex = (currentPage - 1) * perPage;
                    const endIndex = startIndex + perPage;
                    const paginatedInvoices = filteredInvoices.slice(startIndex, endIndex);
                    // Update pagination controls
                    this.updateModalArPagination(totalEntries, currentPage, perPage);
                    
                    // Update table wrapper scrolling
                    const tableWrapper = document.getElementById('modalArTableWrapper');
                    if (tableWrapper) {
                        tableWrapper.setAttribute('data-per-page', perPage.toString());
                        if (perPage > 5) {
                            tableWrapper.classList.remove('modal-table-wrapper-no-scroll');
                            tableWrapper.classList.add('modal-table-wrapper-scroll');
                        } else {
                            tableWrapper.classList.remove('modal-table-wrapper-scroll');
                            tableWrapper.classList.add('modal-table-wrapper-no-scroll');
                        }
                    }
                    if (paginatedInvoices.length > 0) {
                        tbody.innerHTML = paginatedInvoices.map((inv, idx) => {
                            // Use debit/credit from API: Invoices are receivables (credit = invoice amount, debit = payment received)
                            const debitAmount = parseFloat(inv.debit_amount || 0);
                            const creditAmount = parseFloat(inv.credit_amount || 0);
                            const rowNo = 'EX' + String(startIndex + idx + 1).padStart(6, '0');
                            const paymentVoucherText = (inv.payment_voucher && inv.payment_voucher.trim() !== '') ? this.escapeHtml(inv.payment_voucher) : 'Auto-generated';
                            const taxText = (inv.vat_report === 'tax_included') ? 'Tax included' : 'Tax not included';
                            const expenseText = this.escapeHtml(inv.description || inv.customer_name || 'N/A');
                            
                            return `
                            <tr>
                                <td class="index-column">
                                    <div class="row-index-with-checkbox">
                                        <input type="checkbox" class="row-checkbox" data-id="${inv.id}" data-action="bulk-checkbox-ar">
                                        <span class="row-index">${rowNo}</span>
                                    </div>
                                </td>
                                <td class="date-column">${inv.invoice_date ? this.formatDate(inv.invoice_date) : '-'}</td>
                                <td class="journal-number-column">${this.escapeHtml(inv.invoice_number || '')}</td>
                                <td class="expense-column">${expenseText}</td>
                                <td class="amount-column">${creditAmount > 0 ? this.formatCurrency(creditAmount) : '-'}</td>
                                <td class="voucher-column">${paymentVoucherText}</td>
                                <td class="vat-column">${taxText}</td>
                                <td class="status-column"><span class="status-badge ${String(inv.status || '').toLowerCase().replace(' ', '-')}">${this.escapeHtml(inv.status || '')}</span></td>
                                <td class="actions-column">
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
                        // Add checkbox change handlers
                        setTimeout(() => {
                            document.querySelectorAll('#modalInvoicesTable tbody input[type="checkbox"]').forEach(cb => {
                                cb.addEventListener('change', () => {
                                    this.updateBulkActions('ar');
                                    const selectAll = document.getElementById('bulkSelectAllAr');
                                    if (selectAll) {
                                        const allChecked = Array.from(document.querySelectorAll('#modalInvoicesTable tbody input[type="checkbox"]')).every(c => c.checked);
                                        const someChecked = Array.from(document.querySelectorAll('#modalInvoicesTable tbody input[type="checkbox"]')).some(c => c.checked);
                                        selectAll.checked = allChecked;
                                        selectAll.indeterminate = someChecked && !allChecked;
                                    }
                                });
                            });
                        }, 100);
                    } else {
                        tbody.innerHTML = '<tr><td colspan="9" class="text-center ledger-empty-state"><i class="fas fa-file-invoice ledger-empty-icon"></i><p class="ledger-empty-message">No invoices found</p></td></tr>';
                        this.updateModalArPagination(0, 1, perPage);
                        
                        // Reset summary cards
                        const defaultCurrency = this.getDefaultCurrencySync();
                        if (totalInvoicesEl) totalInvoicesEl.textContent = '0';
                        if (totalEl) totalEl.textContent = this.formatCurrency(0, defaultCurrency);
                        if (overdueEl) overdueEl.textContent = this.formatCurrency(0, defaultCurrency);
                        if (monthEl) monthEl.textContent = this.formatCurrency(0, defaultCurrency);
                        if (postedCountEl) postedCountEl.textContent = '0';
                        if (postedAmountEl) postedAmountEl.textContent = this.formatCurrency(0, defaultCurrency);
                        if (draftCountEl) draftCountEl.textContent = '0';
                        if (draftAmountEl) draftAmountEl.textContent = this.formatCurrency(0, defaultCurrency);
                        if (paidCountEl) paidCountEl.textContent = '0';
                        if (paidAmountEl) paidAmountEl.textContent = this.formatCurrency(0, defaultCurrency);
                        if (unpaidCountEl) unpaidCountEl.textContent = '0';
                        if (unpaidAmountEl) unpaidAmountEl.textContent = this.formatCurrency(0, defaultCurrency);
                    }
                    this.updateBulkActions('ar');
                } else {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center ledger-empty-state"><i class="fas fa-file-invoice ledger-empty-icon"></i><p class="ledger-empty-message">No invoices found</p></td></tr>';
                    this.updateModalArPagination(0, 1, perPage);
                    this.updateBulkActions('ar');
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error loading invoices: ${error.message}</td></tr>`;
                this.showToast('Failed to load invoices. Please try again.', 'error');
            }
        },

        async loadMainInvoices() {
            // Load invoices for the main accounting page (not modal)
            const tbody = document.getElementById('invoicesBody');
            if (!tbody) {
                return;
            }
            tbody.innerHTML = '<tr><td colspan="10" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
            try {
                const response = await fetch(`${this.apiBase}/invoices.php`);
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadMainInvoices:', jsonError);
                    data = { success: false, invoices: [], summary: {} };
                }
                if (data.success && data.invoices && Array.isArray(data.invoices)) {
                    const totalOutstanding = data.summary?.total_outstanding || 0;
                    const overdue = data.summary?.overdue || 0;
                    const thisMonth = data.summary?.this_month || 0;
                    // Update summary cards on main page
                    const totalEl = document.getElementById('arTotalOutstanding');
                    if (totalEl) totalEl.textContent = this.formatCurrency(totalOutstanding);
                    
                    const overdueEl = document.getElementById('arOverdue');
                    if (overdueEl) overdueEl.textContent = this.formatCurrency(overdue);
                    
                    const monthEl = document.getElementById('arThisMonth');
                    if (monthEl) monthEl.textContent = this.formatCurrency(thisMonth);
                    if (data.invoices.length > 0) {
                        tbody.innerHTML = data.invoices.map(inv => {
                            const debitAmount = parseFloat(inv.debit_amount || 0);
                            const creditAmount = parseFloat(inv.credit_amount || 0);
                            
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
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center"><div class="accounting-empty-state"><i class="fas fa-file-invoice accounting-empty-state-icon"></i><p class="accounting-empty-state-text">No invoices found</p></div></td></tr>';
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center text-danger">Error loading invoices: ${error.message}</td></tr>`;
            }
        },

        async loadModalBills() {
            const search = this.modalApSearch || '';
            const currentPage = this.modalApCurrentPage || 1;
            const perPage = this.modalApPerPage || 5;
            
            const tbody = document.getElementById('modalBillsBody');
            if (!tbody) {
                return;
            }
            tbody.innerHTML = '<tr><td colspan="10" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
            try {
                const response = await fetch(`${this.apiBase}/bills.php`);
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadModalBills:', jsonError);
                    data = { success: false, bills: [], summary: {} };
                }
                if (data.success && data.bills && Array.isArray(data.bills)) {
                    const totalOutstanding = data.summary?.total_outstanding || 0;
                    const overdue = data.summary?.overdue || 0;
                    const thisMonth = data.summary?.this_month || 0;
                    const totalEl = document.getElementById('modalApTotalOutstanding');
                    if (totalEl) totalEl.textContent = this.formatCurrency(totalOutstanding);
                    
                    const overdueEl = document.getElementById('modalApOverdue');
                    if (overdueEl) overdueEl.textContent = this.formatCurrency(overdue);
                    
                    const monthEl = document.getElementById('modalApThisMonth');
                    if (monthEl) monthEl.textContent = this.formatCurrency(thisMonth);
                    // Filter by search term
                    let filteredBills = data.bills;
                    if (search) {
                        const searchLower = search.toLowerCase();
                        filteredBills = filteredBills.filter(bill => 
                            (bill.bill_number && bill.bill_number.toLowerCase().includes(searchLower)) ||
                            (bill.vendor_name && bill.vendor_name.toLowerCase().includes(searchLower)) ||
                            (bill.status && bill.status.toLowerCase().includes(searchLower))
                        );
                    }
                    // Calculate pagination
                    const totalEntries = filteredBills.length;
                    this.modalApTotalPages = Math.ceil(totalEntries / perPage);
                    const startIndex = (currentPage - 1) * perPage;
                    const endIndex = startIndex + perPage;
                    const paginatedBills = filteredBills.slice(startIndex, endIndex);
                    // Update pagination controls
                    this.updateModalApPagination(totalEntries, currentPage, perPage);
                    // Update table wrapper scrolling based on perPage
                    const tableWrapper = document.querySelector('#modalBillsTable')?.closest('.modal-table-wrapper');
                    if (tableWrapper) {
                        tableWrapper.setAttribute('data-per-page', perPage.toString());
                        if (perPage > 5) {
                            tableWrapper.classList.remove('modal-table-wrapper-no-scroll');
                            tableWrapper.classList.add('modal-table-wrapper-scroll');
                        } else {
                            tableWrapper.classList.remove('modal-table-wrapper-scroll');
                            tableWrapper.classList.add('modal-table-wrapper-no-scroll');
                        }
                    }
                    if (paginatedBills.length > 0) {
                        tbody.innerHTML = paginatedBills.map(bill => {
                            // Use debit/credit from API: Bills are payables (debit = bill amount, credit = payment made)
                            const debitAmount = parseFloat(bill.debit_amount || 0);
                            const creditAmount = parseFloat(bill.credit_amount || 0);
                            
                            return `
                            <tr>
                                <td>${this.escapeHtml(bill.bill_number)}</td>
                                <td>${bill.bill_date}</td>
                                <td>${this.escapeHtml(bill.vendor_name || 'N/A')}</td>
                                <td>${bill.due_date}</td>
                                <td class="debit-cell">${debitAmount > 0 ? this.formatCurrency(debitAmount) : '-'}</td>
                                <td class="credit-cell">${creditAmount > 0 ? this.formatCurrency(creditAmount) : '-'}</td>
                                <td>${this.formatCurrency(bill.paid_amount)}</td>
                                <td>${this.formatCurrency(bill.balance_amount)}</td>
                                <td><span class="status-badge ${bill.status.toLowerCase().replace(' ', '-')}">${bill.status}</span></td>
                                <td><input type="checkbox" class="row-checkbox" data-id="${bill.id}" data-action="bulk-checkbox-ap"></td>
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
                        `;
                        }).join('');
                        setTimeout(() => {
                            document.querySelectorAll('#modalBillsTable tbody input[type="checkbox"]').forEach(cb => {
                                cb.addEventListener('change', () => {
                                    this.updateBulkActions('ap');
                                    const selectAll = document.getElementById('bulkSelectAllAp');
                                    if (selectAll) {
                                        const allChecked = Array.from(document.querySelectorAll('#modalBillsTable tbody input[type="checkbox"]')).every(c => c.checked);
                                        const someChecked = Array.from(document.querySelectorAll('#modalBillsTable tbody input[type="checkbox"]')).some(c => c.checked);
                                        selectAll.checked = allChecked;
                                        selectAll.indeterminate = someChecked && !allChecked;
                                    }
                                });
                            });
                        }, 100);
                    } else {
                        tbody.innerHTML = '<tr><td colspan="10" class="text-center"><div class="accounting-empty-state"><i class="fas fa-file-invoice accounting-empty-state-icon"></i><p class="accounting-empty-state-text">No bills found</p></div></td></tr>';
                    }
                    this.updateBulkActions('ap');
                } else {
                    tbody.innerHTML = '<tr><td colspan="10" class="text-center"><div class="accounting-empty-state"><i class="fas fa-file-invoice accounting-empty-state-icon"></i><p class="accounting-empty-state-text">No bills found</p></div></td></tr>';
                    this.updateModalApPagination(0, 1, perPage);
                    this.updateBulkActions('ap');
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="17" class="text-center text-danger">Error loading bills: ${error.message}</td></tr>`;
                this.showToast('Failed to load bills. Please try again.', 'error');
            }
        },

        async loadModalBankAccounts() {
            const search = this.modalBankSearch || '';
            const currentPage = this.modalBankCurrentPage || 1;
            const perPage = this.modalBankPerPage || 5;
            
            const tbody = document.getElementById('modalBankAccountsBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
            try {
                const response = await fetch(`${this.apiBase}/banks.php`);
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadModalBankAccounts:', jsonError);
                    data = { success: false, banks: [] };
                }
                if (data.success && data.banks && Array.isArray(data.banks)) {
                    // Filter by search term
                    let filteredBanks = data.banks;
                    if (search) {
                        const searchLower = search.toLowerCase();
                        filteredBanks = filteredBanks.filter(bank => 
                            (bank.bank_name && bank.bank_name.toLowerCase().includes(searchLower)) ||
                            (bank.account_name && bank.account_name.toLowerCase().includes(searchLower)) ||
                            (bank.account_number && bank.account_number.toLowerCase().includes(searchLower))
                        );
                    }
                    // Calculate pagination
                    const totalEntries = filteredBanks.length;
                    this.modalBankTotalPages = Math.ceil(totalEntries / perPage);
                    const startIndex = (currentPage - 1) * perPage;
                    const endIndex = startIndex + perPage;
                    const paginatedBanks = filteredBanks.slice(startIndex, endIndex);
                    // Update pagination controls
                    this.updateModalBankPagination(totalEntries, currentPage, perPage);
                    // Update table wrapper scrolling based on perPage
                    const tableWrapper = document.getElementById('modalBankTableWrapper');
                    if (tableWrapper) {
                        tableWrapper.setAttribute('data-per-page', perPage.toString());
                        if (perPage > 5) {
                            tableWrapper.classList.remove('modal-table-wrapper-no-scroll');
                            tableWrapper.classList.add('modal-table-wrapper-scroll');
                        } else {
                            tableWrapper.classList.remove('modal-table-wrapper-scroll');
                            tableWrapper.classList.add('modal-table-wrapper-no-scroll');
                        }
                    }
                    if (paginatedBanks.length > 0) {
                        tbody.innerHTML = paginatedBanks.map(bank => `
                            <tr>
                                <td>${this.escapeHtml(bank.bank_name || 'N/A')}</td>
                                <td>${this.escapeHtml(bank.account_name || 'N/A')}</td>
                                <td>${this.escapeHtml(bank.account_number || 'N/A')}</td>
                                <td>${this.escapeHtml(bank.account_type || 'N/A')}</td>
                                <td>${this.formatCurrency(bank.opening_balance || 0)}</td>
                                <td>${this.formatCurrency(bank.current_balance || 0)}</td>
                                <td><span class="status-badge ${bank.is_active ? 'active' : 'inactive'}">${bank.is_active ? 'Active' : 'Inactive'}</span></td>
                                <td><input type="checkbox" class="row-checkbox" data-id="${bank.id}" data-action="bulk-checkbox-bank"></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn view" data-action="view-bank" data-id="${bank.id}">View</button>
                                        <button class="action-btn edit" data-action="edit-bank" data-id="${bank.id}">Edit</button>
                                    </div>
                                </td>
                            </tr>
                        `).join('');
                        setTimeout(() => {
                            document.querySelectorAll('#modalBankAccountsTable tbody input[type="checkbox"]').forEach(cb => {
                                cb.addEventListener('change', () => {
                                    this.updateBulkActions('bank');
                                    const selectAll = document.getElementById('bulkSelectAllBank');
                                    if (selectAll) {
                                        const allChecked = Array.from(document.querySelectorAll('#modalBankAccountsTable tbody input[type="checkbox"]')).every(c => c.checked);
                                        const someChecked = Array.from(document.querySelectorAll('#modalBankAccountsTable tbody input[type="checkbox"]')).some(c => c.checked);
                                        selectAll.checked = allChecked;
                                        selectAll.indeterminate = someChecked && !allChecked;
                                    }
                                });
                            });
                        }, 100);
                    } else {
                        tbody.innerHTML = '<tr><td colspan="9" class="text-center"><div class="accounting-empty-state"><i class="fas fa-university accounting-empty-state-icon"></i><p class="accounting-empty-state-text">No bank accounts found</p></div></td></tr>';
                    }
                    this.updateBulkActions('bank');
                } else {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center"><div class="accounting-empty-state"><i class="fas fa-university accounting-empty-state-icon"></i><p class="accounting-empty-state-text">No bank accounts found</p></div></td></tr>';
                    this.updateModalBankPagination(0, 1, perPage);
                    this.updateBulkActions('bank');
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">Error loading bank accounts: ${error.message}</td></tr>`;
                this.showToast('Failed to load bank accounts. Please try again.', 'error');
            }
        },

        async loadAccountsForModalSelect(selector) {
            const accountSelect = document.querySelector(selector);
            if (!accountSelect) {
                return;
            }
            
            // Always start with "All Accounts" option - make sure it's properly set
            accountSelect.innerHTML = '<option value="" selected>All Accounts</option>';
            
            try {
                const response = await fetch(`${this.apiBase}/accounts.php?is_active=1`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadAccountsForModalSelect:', jsonError);
                    data = { success: false, accounts: [] };
                }
                
                if (data.success) {
                    if (data.accounts && data.accounts.length > 0) {
                        // "All Accounts" is already there, just add the account options
                    data.accounts.forEach(account => {
                        const option = document.createElement('option');
                        option.value = account.id;
                            const displayText = `${account.account_code || ''} ${account.account_name || 'N/A'}`.trim();
                            option.textContent = displayText;
                        accountSelect.appendChild(option);
                    });
                    }
                }
            } catch (error) {
                // Keep "All Accounts" option even if loading fails
            }
            
            // Ensure "All Accounts" is selected by default if no account was previously selected
            if (!accountSelect.value && accountSelect.options.length > 0) {
                accountSelect.value = '';
            }
            
            // Force a re-render by triggering a change event
            accountSelect.dispatchEvent(new Event('change', { bubbles: true }));
        },

        async loadAccountsForSelect(selectId = null, selectElement = null) {
            // Get the select element - try multiple methods
            let accountSelect = selectElement;
            if (!accountSelect && selectId) {
                accountSelect = document.getElementById(selectId);
            }
            if (!accountSelect) {
                accountSelect = document.querySelector('#journalEntryForm select[name="account_id"]');
            }
            
            if (!accountSelect) {
                console.warn('loadAccountsForSelect: Account select not found', selectId);
                return;
            }
            
            // Show loading state
            if (!accountSelect.innerHTML.includes('Loading')) {
                const loadingOption = document.createElement('option');
                loadingOption.value = '';
                loadingOption.textContent = 'Loading accounts...';
                loadingOption.disabled = true;
                accountSelect.innerHTML = '';
                accountSelect.appendChild(loadingOption);
            }
            
            try {
                const response = await fetch(`${this.apiBase}/accounts.php?is_active=1`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                // Re-fetch the select element in case DOM changed
                if (selectId) {
                    const refreshedSelect = document.getElementById(selectId);
                    if (refreshedSelect) {
                        accountSelect = refreshedSelect;
                    } else {
                        console.error('Select element lost after fetch!', selectId);
                        // Try one more time after a short delay
                        await new Promise(resolve => setTimeout(resolve, 100));
                        const retrySelect = document.getElementById(selectId);
                        if (retrySelect) {
                            accountSelect = retrySelect;
                        } else {
                            console.error('Select element still not found after retry!', selectId);
                            return;
                        }
                    }
                }
                
                if (data.success && data.accounts && data.accounts.length > 0) {
                    // Clear existing options completely - use multiple methods to ensure it works
                    while (accountSelect.firstChild) {
                        accountSelect.removeChild(accountSelect.firstChild);
                    }
                    accountSelect.innerHTML = '';
                    
                    // Use innerHTML directly for most reliable update
                    const optionsHTML = '<option value="">Select Account</option>' + 
                        data.accounts.map(account => {
                            const displayText = `${account.account_code || ''} ${account.account_name || 'N/A'}`.trim();
                            const escapedText = displayText.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
                            return `<option value="${account.id}">${escapedText}</option>`;
                        }).join('');
                    
                    accountSelect.innerHTML = optionsHTML;
                    
                    // If still not working, retry with fresh element reference
                    const finalOptionCount = accountSelect.options.length;
                    if (finalOptionCount <= 1 && selectId) {
                        await new Promise(resolve => setTimeout(resolve, 100));
                        const freshSelect = document.getElementById(selectId);
                        if (freshSelect) {
                            freshSelect.innerHTML = optionsHTML;
                        }
                    }
                } else {
                    // If no accounts found, use fallback options
                    accountSelect.innerHTML = '';
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = 'Select Account';
                    accountSelect.appendChild(defaultOption);
                    
                    const fallbackAccounts = [
                        { id: 1, name: 'Cash' },
                        { id: 2, name: 'Bank' },
                        { id: 3, name: 'Revenue' },
                        { id: 4, name: 'Expenses' }
                    ];
                    fallbackAccounts.forEach(acc => {
                        const option = document.createElement('option');
                        option.value = acc.id;
                        option.textContent = acc.name;
                        accountSelect.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading accounts:', error);
                // Re-fetch the select element in case DOM changed
                if (selectId) {
                    accountSelect = document.getElementById(selectId);
                }
                if (accountSelect) {
                    // Use fallback options on error
                    accountSelect.innerHTML = '';
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = 'Select Account';
                    accountSelect.appendChild(defaultOption);
                    
                    const fallbackAccounts = [
                        { id: 1, name: 'Cash' },
                        { id: 2, name: 'Bank' },
                        { id: 3, name: 'Revenue' },
                        { id: 4, name: 'Expenses' }
                    ];
                    fallbackAccounts.forEach(acc => {
                        const option = document.createElement('option');
                        option.value = acc.id;
                        option.textContent = acc.name;
                        accountSelect.appendChild(option);
                    });
                }
            }
        },

        async loadVendorsForSelect(selector, billId = null) {
            const vendorSelect = document.getElementById(selector);
            if (!vendorSelect) return;
            vendorSelect.innerHTML = '<option value="">Loading vendors...</option>';
            try {
                const response = await fetch(`${this.apiBase}/vendors.php?is_active=1`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                let data;
                try { data = await response.json(); } catch (e) { data = { success: false, vendors: [] }; }
                if (data.success && data.vendors) {
                    vendorSelect.innerHTML = '<option value="">Select Vendor</option>';
                    data.vendors.forEach(v => {
                        const opt = document.createElement('option');
                        opt.value = v.id;
                        opt.textContent = v.vendor_name || 'N/A';
                        vendorSelect.appendChild(opt);
                    });
                    if (billId) {
                        try {
                            const r = await fetch(`${this.apiBase}/bills.php?id=${billId}`);
                            const d = await r.json();
                            if (d.success && d.bill && d.bill.vendor_id) vendorSelect.value = d.bill.vendor_id;
                        } catch (e) {}
                    }
                } else {
                    vendorSelect.innerHTML = '<option value="">No vendors found</option>';
                }
            } catch (error) {
                vendorSelect.innerHTML = '<option value="">Error loading vendors</option>';
            }
        },

        async loadCustomersForSelect(selector, invoiceId = null) {
            const customerSelect = document.getElementById(selector);
            if (!customerSelect) {
                return;
            }
            
            customerSelect.innerHTML = '<option value="">Loading customers...</option>';
            
            try {
                const response = await fetch(`${this.apiBase}/customers.php?is_active=1`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadCustomersForSelect:', jsonError);
                    data = { success: false, customers: [] };
                }
                
                if (data.success && data.customers) {
                    customerSelect.innerHTML = '<option value="">Select Customer</option>';
                    data.customers.forEach(customer => {
                        const option = document.createElement('option');
                        option.value = customer.id;
                        option.textContent = customer.customer_name || 'N/A';
                        customerSelect.appendChild(option);
                    });
                    
                    // If editing, load invoice and populate form fields
                    if (invoiceId) {
                        try {
                            const invResponse = await fetch(`${this.apiBase}/invoices.php?id=${invoiceId}`);
                            const invData = await invResponse.json();
                            if (invData.success && invData.invoice) {
                                // Populate form fields
                                const form = document.getElementById('invoiceForm');
                                if (form && invData.invoice) {
                                    const inv = invData.invoice;
                                    if (form.querySelector('[name="invoice_number"]')) {
                                        form.querySelector('[name="invoice_number"]').value = inv.invoice_number || '';
                                    }
                                    if (form.querySelector('[name="invoice_date"]')) {
                                        form.querySelector('[name="invoice_date"]').value = inv.invoice_date ? this.formatDateForInput(inv.invoice_date) : '';
                                    }
                                    if (form.querySelector('[name="due_date"]')) {
                                        form.querySelector('[name="due_date"]').value = inv.due_date ? this.formatDateForInput(inv.due_date) : '';
                                    }
                                    if (form.querySelector('[name="total_amount"]')) {
                                        form.querySelector('[name="total_amount"]').value = inv.total_amount || 0;
                                    }
                                    if (form.querySelector('[name="currency"]')) {
                                        form.querySelector('[name="currency"]').value = inv.currency || this.getDefaultCurrencySync();
                                    }
                                    if (form.querySelector('[name="description"]')) {
                                        form.querySelector('[name="description"]').value = inv.description || '';
                                    }
                                    // Set customers if available
                                    const customersContainer = document.getElementById('invoiceCustomersContainer');
                                    if (customersContainer && inv.customers) {
                                        // Parse customers (could be comma-separated or newline-separated)
                                        const customers = inv.customers.split(/[,\n]/).map(c => c.trim()).filter(c => c);
                                        customersContainer.innerHTML = '';
                                        customers.forEach((customer, index) => {
                                            const row = document.createElement('div');
                                            row.className = 'customer-input-row';
                                            row.setAttribute('data-customer-index', index);
                                            const isLast = index === customers.length - 1;
                                            row.innerHTML = `
                                                <input type="text" name="customers[]" class="customer-name-input" placeholder="Enter customer name" value="${this.escapeHtml(customer)}">
                                                ${isLast ? '<button type="button" class="btn-add-customer" data-action="add-customer" title="Add Customer"><i class="fas fa-plus"></i></button>' : ''}
                                                ${customers.length > 1 ? '<button type="button" class="btn-remove-customer" data-action="remove-customer" title="Remove Customer"><i class="fas fa-minus"></i></button>' : ''}
                                            `;
                                            customersContainer.appendChild(row);
                                        });
                                        // Re-setup event listeners
                                        this.setupCustomerFields('invoiceCustomersContainer', invoiceId);
                                    } else if (customersContainer && inv.customer_name) {
                                        const firstInput = customersContainer.querySelector('.customer-name-input');
                                        if (firstInput) firstInput.value = inv.customer_name;
                                    }
                                    // Set debit and credit account IDs if available
                                    if (inv.debit_account_id && form.querySelector('[name="debit_account_id"]')) {
                                        setTimeout(() => {
                                            const debitSelect = form.querySelector('[name="debit_account_id"]');
                                            if (debitSelect) debitSelect.value = inv.debit_account_id;
                                        }, 300);
                                    }
                                    if (inv.credit_account_id && form.querySelector('[name="credit_account_id"]')) {
                                        setTimeout(() => {
                                            const creditSelect = form.querySelector('[name="credit_account_id"]');
                                            if (creditSelect) creditSelect.value = inv.credit_account_id;
                                        }, 300);
                                    }
                                    // Payment Voucher (readonly): show saved value or "Auto-generated"
                                    const pvInput = form.querySelector('#invoicePaymentVoucher');
                                    if (pvInput) {
                                        pvInput.value = (inv.payment_voucher && inv.payment_voucher.trim() !== '') ? inv.payment_voucher : '';
                                        pvInput.placeholder = 'Auto-generated';
                                    }
                                    // Tax checkbox: checked when vat_report === 'tax_included'
                                    const taxCb = form.querySelector('#invoiceTaxCheckbox');
                                    const taxLabel = form.querySelector('#invoiceTaxLabel');
                                    if (taxCb) {
                                        taxCb.checked = inv.vat_report === 'tax_included';
                                        if (taxLabel) taxLabel.textContent = taxCb.checked ? 'Tax included' : 'Tax not included';
                                    }
                                }
                            }
                        } catch (err) {
                        }
                    }
                } else {
                    customerSelect.innerHTML = '<option value="">No customers found</option>';
                }
            } catch (error) {
                customerSelect.innerHTML = '<option value="">Error loading customers</option>';
            }
        },

        async loadModalJournalEntries() {
            const currentPage = this.modalLedgerCurrentPage || 1;
            const perPage = this.modalLedgerPerPage || 5;
            
            // Get filter values from inputs or saved state
            const dateFromEl = document.getElementById('modalLedgerDateFrom');
            const dateToEl = document.getElementById('modalLedgerDateTo');
            const accountEl = document.getElementById('modalLedgerAccount');
            const searchEl = document.getElementById('modalLedgerSearch');
            
            let dateFrom = dateFromEl?.value || this.modalLedgerDateFrom || '';
            let dateTo = dateToEl?.value || this.modalLedgerDateTo || '';
            const accountId = accountEl?.value || this.modalLedgerAccountId || '';
            const search = searchEl?.value || this.modalLedgerSearch || '';
            // If Date To is empty, set it to today
            if (!dateTo) {
                const today = this.formatDateForInput(new Date().toISOString().split('T')[0]);
                dateTo = today;
                if (dateToEl) {
                    dateToEl.value = today;
                }
                this.modalLedgerDateTo = today;
            }
            
            // Only set default Date From if it's truly empty (don't override user selection)
            // Note: This should only happen on initial load, not when user is selecting dates
            if (!dateFrom && !dateFromEl?.value) {
                const ninetyDaysAgo = new Date();
                ninetyDaysAgo.setDate(ninetyDaysAgo.getDate() - 90);
                dateFrom = this.formatDateForInput(ninetyDaysAgo.toISOString().split('T')[0]);
                if (dateFromEl) {
                    dateFromEl.value = dateFrom;
                }
                this.modalLedgerDateFrom = dateFrom;
            } else if (dateFromEl?.value) {
                // Preserve user's selection from the input field
                dateFrom = dateFromEl.value;
                this.modalLedgerDateFrom = dateFrom;
            }
            // Don't auto-expand date range if user selected same dates intentionally
            // Only validate that Date From is not after Date To
            if (dateFrom && dateTo && dateFrom > dateTo) {
                // If Date From is after Date To, swap them
                const temp = dateFrom;
                dateFrom = dateTo;
                dateTo = temp;
                if (dateFromEl) dateFromEl.value = dateFrom;
                if (dateToEl) dateToEl.value = dateTo;
                this.modalLedgerDateFrom = dateFrom;
                this.modalLedgerDateTo = dateTo;
            }
            
            // If date range is less than 7 days and no data found, expand to 90 days
            const dateFromObj = new Date(dateFrom);
            const dateToObj = new Date(dateTo);
            const daysDiff = Math.ceil((dateToObj - dateFromObj) / (1000 * 60 * 60 * 24));
            const tbody = document.getElementById('modalJournalEntriesBody');
            if (!tbody) {
                return;
            }
            tbody.innerHTML = '<tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';
            
            const params = new URLSearchParams();
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            
            // Always send account_id if selected (even if empty string for "All Accounts")
            if (accountId !== null && accountId !== undefined && accountId !== '') {
                    params.append('account_id', accountId);
            }
            // Include Draft so newly-created journal entries show up immediately in the General Ledger modal
            params.append('include_draft', '1');
            
            // Add cache-busting parameter
            params.append('_t', Date.now());
            try {
                const url = `${this.apiBase}/journal-entries.php?${params.toString()}`;
                const response = await fetch(url, {
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
                // Don't auto-expand dates - respect user's date selections
                // If no entries found, just show empty state (handled below)
                if (data.success && data.entries && data.entries.length > 0) {
                    // Filter by search term
                    let filteredEntries = data.entries;
                    if (search) {
                        const searchLower = search.toLowerCase();
                        filteredEntries = filteredEntries.filter(entry => 
                            (entry.entry_number && entry.entry_number.toLowerCase().includes(searchLower)) ||
                            (entry.description && entry.description.toLowerCase().includes(searchLower)) ||
                            (entry.entry_type && entry.entry_type.toLowerCase().includes(searchLower)) ||
                            (entry.entity_name && entry.entity_name.toLowerCase().includes(searchLower))
                        );
                    }
                    
                    // Show helpful message if account filter is active but no results
                    if (accountId && accountId !== '' && accountId !== '0' && filteredEntries.length === 0) {
                        const accountName = accountEl?.options[accountEl?.selectedIndex]?.text || 'selected account';
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="8" class="text-center ledger-empty-state">
                                    <i class="fas fa-info-circle ledger-empty-icon"></i>
                                    <p class="ledger-empty-message">
                                        No transactions found for account: <strong>${accountName}</strong>
                                    </p>
                                    <p class="ledger-empty-hint">
                                        Transactions need to be linked to accounts first.
                                    </p>
                                    <a href="${(window.APP_CONFIG && window.APP_CONFIG.baseUrl) || ''}/pages/link-transactions.php" 
                                       class="btn btn-primary ledger-empty-link">
                                        <i class="fas fa-link"></i> Link Transactions to Accounts
                                    </a>
                                </td>
                            </tr>
                        `;
                        this.updateModalLedgerPagination(0, 1, perPage);
                        return;
                    }
                    // Calculate pagination
                    const totalEntries = filteredEntries.length;
                    this.modalLedgerTotalPages = Math.ceil(totalEntries / perPage);
                    const startIndex = (currentPage - 1) * perPage;
                    const endIndex = startIndex + perPage;
                    const paginatedEntries = filteredEntries.slice(startIndex, endIndex);
                    // Update pagination controls
                    this.updateModalLedgerPagination(totalEntries, currentPage, perPage);
                    // Update status cards
                    const totalDebit = filteredEntries.reduce((sum, entry) => sum + (parseFloat(entry.total_debit) || 0), 0);
                    const totalCredit = filteredEntries.reduce((sum, entry) => sum + (parseFloat(entry.total_credit) || 0), 0);
                    const postedCount = filteredEntries.filter(entry => (entry.status || '').toLowerCase() === 'posted').length;
                    const draftCount = filteredEntries.filter(entry => (entry.status || '').toLowerCase() === 'draft').length;
                    
                    // Calculate entity statistics
                    const agentsEntries = filteredEntries.filter(entry => entry.entity_type === 'agent' && entry.entity_id);
                    const subagentsEntries = filteredEntries.filter(entry => entry.entity_type === 'subagent' && entry.entity_id);
                    const workersEntries = filteredEntries.filter(entry => entry.entity_type === 'worker' && entry.entity_id);
                    const hrEntries = filteredEntries.filter(entry => entry.entity_type === 'hr' && entry.entity_id);
                    
                    const agentsCount = agentsEntries.length > 0 ? new Set(agentsEntries.map(e => e.entity_id).filter(id => id != null)).size : 0;
                    const subagentsCount = subagentsEntries.length > 0 ? new Set(subagentsEntries.map(e => e.entity_id).filter(id => id != null)).size : 0;
                    const workersCount = workersEntries.length > 0 ? new Set(workersEntries.map(e => e.entity_id).filter(id => id != null)).size : 0;
                    const hrCount = hrEntries.length > 0 ? new Set(hrEntries.map(e => e.entity_id).filter(id => id != null)).size : 0;
                    
                    const agentsAmount = agentsEntries.reduce((sum, entry) => sum + (parseFloat(entry.total_debit) || 0) + (parseFloat(entry.total_credit) || 0), 0);
                    const subagentsAmount = subagentsEntries.reduce((sum, entry) => sum + (parseFloat(entry.total_debit) || 0) + (parseFloat(entry.total_credit) || 0), 0);
                    const workersAmount = workersEntries.reduce((sum, entry) => sum + (parseFloat(entry.total_debit) || 0) + (parseFloat(entry.total_credit) || 0), 0);
                    const hrAmount = hrEntries.reduce((sum, entry) => sum + (parseFloat(entry.total_debit) || 0) + (parseFloat(entry.total_credit) || 0), 0);
                    
                    // Get currency from system settings or use first entry's currency
                    const defaultCurrency = this.getDefaultCurrencySync();
                    const entryCurrency = filteredEntries.length > 0 && filteredEntries[0].currency ? filteredEntries[0].currency : defaultCurrency;
                    const totalEntriesEl = document.getElementById('modalLedgerTotalEntries');
                    const totalDebitEl = document.getElementById('modalLedgerTotalDebit');
                    const totalCreditEl = document.getElementById('modalLedgerTotalCredit');
                    const balanceEl = document.getElementById('modalLedgerBalance');
                    const postedEl = document.getElementById('modalLedgerPosted');
                    const draftEl = document.getElementById('modalLedgerDraft');
                    
                    const agentsCountEl = document.getElementById('modalLedgerAgentsCount');
                    const agentsAmountEl = document.getElementById('modalLedgerAgentsAmount');
                    const subagentsCountEl = document.getElementById('modalLedgerSubagentsCount');
                    const subagentsAmountEl = document.getElementById('modalLedgerSubagentsAmount');
                    const workersCountEl = document.getElementById('modalLedgerWorkersCount');
                    const workersAmountEl = document.getElementById('modalLedgerWorkersAmount');
                    const hrCountEl = document.getElementById('modalLedgerHrCount');
                    const hrAmountEl = document.getElementById('modalLedgerHrAmount');
                    
                    if (totalEntriesEl) {
                        totalEntriesEl.textContent = totalEntries;
                    }
                    if (totalDebitEl) {
                        totalDebitEl.textContent = this.formatCurrency(totalDebit, entryCurrency);
                    }
                    if (totalCreditEl) {
                        totalCreditEl.textContent = this.formatCurrency(totalCredit, entryCurrency);
                    }
                    if (balanceEl) {
                        balanceEl.textContent = this.formatCurrency(totalCredit - totalDebit, entryCurrency);
                    }
                    if (postedEl) {
                        postedEl.textContent = postedCount;
                    }
                    if (draftEl) {
                        draftEl.textContent = draftCount;
                    }
                    
                    if (agentsCountEl) agentsCountEl.textContent = agentsCount;
                    if (agentsAmountEl) agentsAmountEl.textContent = this.formatCurrency(agentsAmount, entryCurrency);
                    if (subagentsCountEl) subagentsCountEl.textContent = subagentsCount;
                    if (subagentsAmountEl) subagentsAmountEl.textContent = this.formatCurrency(subagentsAmount, entryCurrency);
                    if (workersCountEl) workersCountEl.textContent = workersCount;
                    if (workersAmountEl) workersAmountEl.textContent = this.formatCurrency(workersAmount, entryCurrency);
                    if (hrCountEl) hrCountEl.textContent = hrCount;
                    if (hrAmountEl) hrAmountEl.textContent = this.formatCurrency(hrAmount, entryCurrency);
                    // Update table wrapper - no horizontal scrolling, show all columns
                    const tableWrapper = document.getElementById('modalLedgerTableWrapper');
                    if (tableWrapper) {
                        tableWrapper.setAttribute('data-per-page', perPage.toString());
                        // Disable horizontal scrolling - show all columns
                            tableWrapper.classList.remove('modal-table-wrapper-scroll');
                            tableWrapper.classList.add('modal-table-wrapper-no-scroll');
                    }
                    if (paginatedEntries.length > 0) {
                        const currency = filteredEntries.length > 0 && filteredEntries[0].currency 
                            ? filteredEntries[0].currency 
                            : this.getDefaultCurrencySync();
                        
                        const rows = paginatedEntries.map((entry, index) => {
                            try {
                                let description = this.escapeHtml(entry.description || '');
                            if (entry.entity_type && entry.entity_id) {
                                const entityType = entry.entity_type.charAt(0).toUpperCase() + entry.entity_type.slice(1);
                                description += ` <span class="badge badge-info badge-small">${entityType} #${entry.entity_id}</span>`;
                            }
                            
                            // Add reference number if available
                            if (entry.reference_number) {
                                description += ` <span class="text-muted reference-number">Ref: ${this.escapeHtml(entry.reference_number)}</span>`;
                            }
                            
                            const formattedDate = entry.entry_date ? this.formatDate(entry.entry_date) : '-';
                            const debitAmount = parseFloat(entry.total_debit) || 0;
                            const creditAmount = parseFloat(entry.total_credit) || 0;
                            const entryCurrency = entry.currency || currency;
                            
                            // Format entry number with better styling
                            const entryNumber = entry.entry_number || 'N/A';
                            const entryType = entry.entry_type || 'Manual';
                            // Get status - check both status and entry.status
                            const status = entry.status || (entry.entry && entry.entry.status) || 'Draft';
                            const statusClass = status.toLowerCase();
                            const statusDisplayText = statusClass === 'draft' ? 'Waiting for approval' : status;
                            
                            const entityTypeDisplay = entry.entity_type ? entry.entity_type.charAt(0).toUpperCase() + entry.entity_type.slice(1) : '-';
                            
                            // Get account name - check multiple possible fields
                            // For list entries, account_name might not be included, so try to fetch it if account_id is present
                            let accountDisplay = '-';
                            if (entry.account_name) {
                                accountDisplay = entry.account_name;
                            } else if (entry.account_code && entry.account_name) {
                                accountDisplay = `${entry.account_code} - ${entry.account_name}`;
                            } else if (entry.account) {
                                accountDisplay = entry.account;
                            } else if (entry.account_id) {
                                // Account name not provided, but we have account_id - show account_id as fallback
                                accountDisplay = `Account #${entry.account_id}`;
                            }
                            
                            // Get debit and credit side account names
                            // For now, use account_name if available, or try to get from entry lines
                            let debitSideAccount = '-';
                            let creditSideAccount = '-';
                            
                            // If entry has account_name and debit_amount > 0, use it for debit side
                            if (accountDisplay !== '-' && debitAmount > 0) {
                                debitSideAccount = accountDisplay;
                            }
                            // If entry has account_name and credit_amount > 0, use it for credit side
                            if (accountDisplay !== '-' && creditAmount > 0) {
                                creditSideAccount = accountDisplay;
                            }
                            
                            // If we have entry lines data, use that for more accurate debit/credit sides
                            if (entry.debit_account_name) {
                                debitSideAccount = entry.debit_account_name;
                            }
                            if (entry.credit_account_name) {
                                creditSideAccount = entry.credit_account_name;
                            }
                            
                            // Clean description - remove entity badges and reference numbers for main description
                            let cleanDescription = this.escapeHtml(entry.description || '');
                            
                            // Format status with badge (Draft = waiting for approval)
                            const statusBadgeVariant =
                                statusClass === 'posted'
                                    ? 'success'
                                    : (statusClass === 'draft' ? 'warning' : 'secondary');
                            const statusBadge = status ? `<span class="badge badge-${statusBadgeVariant} badge-small">${this.escapeHtml(statusDisplayText)}</span>` : '<span class="text-muted">-</span>';
                            
                            // Format entity display
                            let entityDisplay = '-';
                            if (entry.entity_name) {
                                entityDisplay = `<span class="entity-name-display">${this.escapeHtml(entry.entity_name)}</span>`;
                                if (entry.entity_type) {
                                    const entityTypeLabel = entry.entity_type.charAt(0).toUpperCase() + entry.entity_type.slice(1);
                                    entityDisplay += ` <span class="badge badge-info badge-small">${entityTypeLabel}</span>`;
                                }
                            } else if (entry.entity_type && entry.entity_id) {
                                entityDisplay = `<span class="badge badge-info badge-small">${entityTypeDisplay} #${entry.entity_id}</span>`;
                            }
                            
                            // Calculate running balance (cumulative per account)
                            // Note: This is a simplified calculation - full implementation would require account-level tracking
                            const runningBalance = debitAmount - creditAmount; // Simplified - would need account-level cumulative
                            
                            // Get posting date (use entry_date if posting_date not available)
                            const postingDate = entry.posting_date || entry.entry_date || formattedDate;
                            const postingDateFormatted = entry.posting_date ? this.formatDate(entry.posting_date) : formattedDate;
                            
                            // Get source module (from entry_type or source_module field)
                            const sourceModule = entry.source_module || entry.entry_type || 'Manual';
                            
                            // Get cost center name (if available)
                            const costCenterName = entry.cost_center_name || entry.cost_center || '-';
                            
                            // Get created by (from entry data)
                            const createdByName = entry.created_by_name || entry.created_by || '-';
                            
                            // Get approved by (from entry data or approval table)
                            const approvedByName = entry.approved_by_name || entry.approved_by || '-';
                            
                            // Make journal reference clickable
                            const journalRefLink = entry.id ? 
                                `<a href="#" class="journal-ref-link" data-action="view-entry" data-id="${entry.id}" title="View Journal Entry">${this.escapeHtml(entryNumber)}</a>` :
                                `<span class="voucher-number-display">${this.escapeHtml(entryNumber)}</span>`;
                            
                            return `
                            <tr class="ledger-entry-row professional-ledger-row">
                                <td class="voucher-number-cell">
                                    <div class="voucher-number-stack">
                                        ${journalRefLink}
                                        <div class="ledger-status-inline">${statusBadge}</div>
                                    </div>
                                </td>
                                <td class="date-cell">
                                    <span class="date-display">${formattedDate}</span>
                                </td>
                                <td class="debit-cell amount-cell ${debitAmount > 0 ? 'has-amount' : ''}">
                                    ${debitAmount > 0 ? this.formatCurrency(debitAmount, entryCurrency) : '<span class="text-muted">-</span>'}
                                </td>
                                <td class="credit-cell amount-cell ${creditAmount > 0 ? 'has-amount' : ''}">
                                    ${creditAmount > 0 ? this.formatCurrency(creditAmount, entryCurrency) : '<span class="text-muted">-</span>'}
                                </td>
                                <td class="account-cell debit-side-cell">
                                    <span class="debit-side-display">${this.escapeHtml(debitSideAccount)}</span>
                                </td>
                                <td class="account-cell credit-side-cell">
                                    <span class="credit-side-display">${this.escapeHtml(creditSideAccount)}</span>
                                </td>
                                <td class="description-cell">
                                    <div class="description-content">${cleanDescription}</div>
                                    ${entry.reference_number ? `<div class="reference-number-inline"><span class="text-muted reference-number">Ref: ${this.escapeHtml(entry.reference_number)}</span></div>` : ''}
                                </td>
                                <td class="actions-cell">
                                    ${statusClass === 'posted' ? `
                                    <div class="action-buttons">
                                        <button class="action-btn view" data-action="view-entry" data-id="${entry.id || entry.entry_number || ''}" data-source="${entry.source || 'journal'}" title="View Entry" data-permission="view_journal_entries">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        ${entry.source === 'transaction' ? `<button class="action-btn edit" data-action="edit-entity-transaction" data-id="${entry.id || entry.entry_number || ''}" data-permission="edit_journal_entry" title="Edit Entry"><i class="fas fa-edit"></i></button>` : `<button class="action-btn edit" data-action="edit-entry" data-id="${entry.id || entry.entry_number || ''}" data-permission="edit_journal_entry" title="Edit Entry"><i class="fas fa-edit"></i></button>`}
                                        <button class="action-btn print" data-action="print-entry" data-id="${entry.id || entry.entry_number || ''}" data-source="${entry.source || 'journal'}" title="Print Entry">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button class="action-btn delete" data-action="delete-entry" data-id="${entry.id || entry.entry_number || ''}" data-source="${entry.source || 'journal'}" data-permission="delete_journal_entry" title="Delete Entry">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    ` : '<span class="text-muted">-</span>'}
                                </td>
                            </tr>
                        `;
                        } catch (error) {
                            return `<tr><td colspan="8" class="text-danger">Error rendering entry: ${this.escapeHtml(error.message)}</td></tr>`;
                        }
                        }).filter(row => row !== null && row !== undefined);
                        
                        tbody.innerHTML = rows.join('');
                        // Add checkbox change handlers
                        setTimeout(() => {
                            document.querySelectorAll('#modalJournalEntriesTable tbody input[type="checkbox"]').forEach(cb => {
                                cb.addEventListener('change', () => {
                                    this.updateBulkActions('ledger');
                                    const selectAll = document.getElementById('bulkSelectAllLedger');
                                    if (selectAll) {
                                        const allChecked = Array.from(document.querySelectorAll('#modalJournalEntriesTable tbody input[type="checkbox"]')).every(c => c.checked);
                                        const someChecked = Array.from(document.querySelectorAll('#modalJournalEntriesTable tbody input[type="checkbox"]')).some(c => c.checked);
                                        selectAll.checked = allChecked;
                                        selectAll.indeterminate = someChecked && !allChecked;
                                    }
                                });
                            });
                        }, 100);
                    } else {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="8" class="text-center ledger-empty-state">
                                    <i class="fas fa-book ledger-empty-icon"></i>
                                    <p class="ledger-empty-message">No journal entries found</p>
                                    <p class="ledger-empty-hint">
                                        ${dateFrom && dateTo ? `Date range: ${dateFrom} to ${dateTo}` : ''}
                                        ${accountId ? `Account filter: ${accountEl?.options[accountEl?.selectedIndex]?.text || 'Selected account'}` : ''}
                                        ${search ? `Search: "${search}"` : ''}
                                    </p>
                                    <button class="btn btn-secondary btn-sm" data-action="clear-ledger-filters">
                                        <i class="fas fa-redo"></i> Clear Filters
                                    </button>
                                </td>
                            </tr>
                        `;
                    }
                    this.updateBulkActions('ledger');
                } else {
                    // Check if account filter is active
                    if (accountId && accountId !== '' && accountId !== '0') {
                        const accountName = accountEl?.options[accountEl?.selectedIndex]?.text || 'selected account';
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="8" class="text-center ledger-empty-state">
                                    <i class="fas fa-info-circle ledger-empty-icon"></i>
                                    <p class="ledger-empty-message">
                                        No transactions found for account: <strong>${this.escapeHtml(accountName)}</strong>
                                    </p>
                                    <p class="ledger-empty-hint">
                                        The transactions may not be linked to this account yet. Try selecting "All Accounts" to see all transactions.
                                    </p>
                                </td>
                            </tr>
                        `;
                    } else {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="8" class="text-center ledger-empty-state">
                                    <i class="fas fa-book ledger-empty-icon"></i>
                                    <p class="ledger-empty-message">No journal entries found</p>
                                    <p class="ledger-empty-hint">
                                        ${dateFrom && dateTo ? `Date range: ${dateFrom} to ${dateTo}` : ''}
                                        ${accountId ? `Account filter: ${accountEl?.options[accountEl?.selectedIndex]?.text || 'Selected account'}` : ''}
                                        ${search ? `Search: "${search}"` : ''}
                                    </p>
                                    <button class="btn btn-secondary btn-sm" data-action="clear-ledger-filters-expand">
                                        <i class="fas fa-redo"></i> Clear Filters & Expand Range
                                    </button>
                                </td>
                            </tr>
                        `;
                    }
                    this.updateModalLedgerPagination(0, 1, perPage);
                    this.updateBulkActions('ledger');
                    
                    // Reset status cards
                    const defaultCurrency = this.getDefaultCurrencySync();
                    const totalEntriesEl = document.getElementById('modalLedgerTotalEntries');
                    const totalDebitEl = document.getElementById('modalLedgerTotalDebit');
                    const totalCreditEl = document.getElementById('modalLedgerTotalCredit');
                    const balanceEl = document.getElementById('modalLedgerBalance');
                    const postedEl = document.getElementById('modalLedgerPosted');
                    const draftEl = document.getElementById('modalLedgerDraft');
                    
                    const agentsCountEl = document.getElementById('modalLedgerAgentsCount');
                    const agentsAmountEl = document.getElementById('modalLedgerAgentsAmount');
                    const subagentsCountEl = document.getElementById('modalLedgerSubagentsCount');
                    const subagentsAmountEl = document.getElementById('modalLedgerSubagentsAmount');
                    const workersCountEl = document.getElementById('modalLedgerWorkersCount');
                    const workersAmountEl = document.getElementById('modalLedgerWorkersAmount');
                    const hrCountEl = document.getElementById('modalLedgerHrCount');
                    const hrAmountEl = document.getElementById('modalLedgerHrAmount');
                    if (totalEntriesEl) totalEntriesEl.textContent = '0';
                    if (totalDebitEl) totalDebitEl.textContent = this.formatCurrency(0, defaultCurrency);
                    if (totalCreditEl) totalCreditEl.textContent = this.formatCurrency(0, defaultCurrency);
                    if (balanceEl) balanceEl.textContent = this.formatCurrency(0, defaultCurrency);
                    if (postedEl) postedEl.textContent = '0';
                    if (draftEl) draftEl.textContent = '0';
                    
                    if (agentsCountEl) agentsCountEl.textContent = '0';
                    if (agentsAmountEl) agentsAmountEl.textContent = this.formatCurrency(0, defaultCurrency);
                    if (subagentsCountEl) subagentsCountEl.textContent = '0';
                    if (subagentsAmountEl) subagentsAmountEl.textContent = this.formatCurrency(0, defaultCurrency);
                    if (workersCountEl) workersCountEl.textContent = '0';
                    if (workersAmountEl) workersAmountEl.textContent = this.formatCurrency(0, defaultCurrency);
                    if (hrCountEl) hrCountEl.textContent = '0';
                    if (hrAmountEl) hrAmountEl.textContent = this.formatCurrency(0, defaultCurrency);
                }
            } catch (error) {
                tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error loading journal entries: ${error.message}</td></tr>`;
                this.showToast('Failed to load journal entries. Please try again.', 'error');
            }
        },

        async loadEntityTransactionsData() {
            try {
                const tbody = document.getElementById('entityTransactionsBody');
                if (!tbody) return;
                // Show loading
                tbody.innerHTML = `
                    <tr>
                        <td colspan="17" class="loading-row">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                                <span>Loading transactions...</span>
                            </div>
                        </td>
                    </tr>
                `;
                // Get filters
                const filterType = document.getElementById('entityFilterType');
                const filterStatus = document.getElementById('entityFilterStatus');
                const searchInput = document.getElementById('entitySearchInput');
                
                const entityType = filterType ? filterType.value : '';
                const status = filterStatus ? filterStatus.value : '';
                const search = searchInput ? searchInput.value.trim() : '';
                // Build URL - we'll need to fetch all and filter client-side
                // since the API requires entity_type and entity_id
                // Add cache busting to ensure fresh data
                let url = `${this.apiBase}/transactions.php?limit=1000&_t=${Date.now()}`;
                const response = await fetch(url, {
                    cache: 'no-store',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });
                const data = await response.json();
                if (!data.success || !data.transactions) {
                    throw new Error(data.message || 'Failed to load transactions');
                }
                // Get all entity transactions by fetching from entities
                // Add cache busting to ensure fresh data
                const entitiesResponse = await fetch(`${this.apiBase}/entities.php?_t=${Date.now()}`, {
                    cache: 'no-store',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });
                const entitiesData = await entitiesResponse.json();
                
                let allTransactions = [];
                
                // If entity type filter is set, fetch transactions for that type
                if (entityType) {
                    const entities = entitiesData.success && entitiesData.entities 
                        ? entitiesData.entities.filter(e => e.entity_type === entityType)
                        : [];
                    
                    for (const entity of entities) {
                        try {
                            // Add cache busting to ensure fresh data
                            const etResponse = await fetch(
                                `${this.apiBase}/entity-transactions.php?entity_type=${entity.entity_type}&entity_id=${entity.id}&_t=${Date.now()}`,
                                {
                                    cache: 'no-store',
                                    headers: {
                                        'Cache-Control': 'no-cache',
                                        'Pragma': 'no-cache'
                                    }
                                }
                            );
                            const etData = await etResponse.json();
                            if (etData.success && etData.transactions) {
                                etData.transactions.forEach(t => {
                                    t.entity_name = entity.name || entity.display_name || `${entity.entity_type} #${entity.id}`;
                                });
                                allTransactions.push(...etData.transactions);
                            }
                        } catch (err) {
                        }
                    }
                } else {
                    // Load all entity types
                    const entityTypes = ['agent', 'subagent', 'worker', 'hr'];
                    const entities = entitiesData.success && entitiesData.entities ? entitiesData.entities : [];
                    
                    for (const entity of entities) {
                        try {
                            // Add cache busting to ensure fresh data
                            const etResponse = await fetch(
                                `${this.apiBase}/entity-transactions.php?entity_type=${entity.entity_type}&entity_id=${entity.id}&_t=${Date.now()}`,
                                {
                                    cache: 'no-store',
                                    headers: {
                                        'Cache-Control': 'no-cache',
                                        'Pragma': 'no-cache'
                                    }
                                }
                            );
                            const etData = await etResponse.json();
                            if (etData.success && etData.transactions) {
                                etData.transactions.forEach(t => {
                                    t.entity_name = entity.name || entity.display_name || `${entity.entity_type} #${entity.id}`;
                                });
                                allTransactions.push(...etData.transactions);
                            }
                        } catch (err) {
                        }
                    }
                }
                // Apply filters
                let filtered = allTransactions;
                
                if (status) {
                    filtered = filtered.filter(t => t.status === status);
                }
                
                if (search) {
                    const searchLower = search.toLowerCase();
                    filtered = filtered.filter(t => 
                        (t.description && t.description.toLowerCase().includes(searchLower)) ||
                        (t.reference_number && t.reference_number.toLowerCase().includes(searchLower)) ||
                        (t.entity_name && t.entity_name.toLowerCase().includes(searchLower)) ||
                        (t.id && t.id.toString().includes(search)) ||
                        (t.transaction_id && t.transaction_id.toString().includes(search))
                    );
                }
                // Sort by date (newest first), then by ID (newest first)
                filtered.sort((a, b) => {
                    const dateA = new Date(a.transaction_date || a.created_at);
                    const dateB = new Date(b.transaction_date || b.created_at);
                    if (dateB - dateA !== 0) {
                        return dateB - dateA;
                    }
                    // If same date, sort by ID descending (newest first)
                    return (parseInt(b.id) || 0) - (parseInt(a.id) || 0);
                });
                this.entityTransactionsData = allTransactions;
                this.entityTransactionsFiltered = filtered;
                this.entityTransactionsTotalCount = filtered.length;
                this.entityTransactionsTotalPages = Math.ceil(filtered.length / this.entityTransactionsPerPage);
                // Calculate pagination
                const startIndex = (this.entityTransactionsCurrentPage - 1) * this.entityTransactionsPerPage;
                const endIndex = startIndex + this.entityTransactionsPerPage;
                const paginatedData = filtered.slice(startIndex, endIndex);
                // Update status cards
                this.updateEntityStatusCards(allTransactions);
                // Render table
                this.renderEntityTransactionsTable(paginatedData);
                // Update pagination
                this.updateEntityTransactionsPagination();
            } catch (error) {
                const tbody = document.getElementById('entityTransactionsBody');
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="17" class="error-row">
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <span>Error loading transactions: ${error.message}</span>
                                </div>
                            </td>
                        </tr>
                    `;
                }
            }
        },

        async loadModalTransactions() {
            const tbody = document.getElementById('modalEntityTransactionsTable')?.querySelector('tbody');
            if (!tbody) return;
            try {
                // Show loading state
                tbody.innerHTML = `
                    <tr>
                        <td colspan="17" class="loading-row">
                            <div class="loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                                <span>Loading transactions...</span>
                            </div>
                        </td>
                    </tr>
                `;
                // Get filters from modal
                const entityTypeFilter = document.getElementById('modalEntityTypeFilter');
                const entityFilter = document.getElementById('modalEntityFilter');
                const searchInput = document.getElementById('modalLedgerSearch');
                const entriesPerPage = document.getElementById('entriesPerPage');
                
                const entityType = entityTypeFilter ? entityTypeFilter.value : '';
                const entityId = entityFilter ? entityFilter.value : '';
                const search = searchInput ? searchInput.value.trim() : '';
                const perPage = entriesPerPage ? parseInt(entriesPerPage.value) : this.transactionsPerPage;
                // Build API URL
                let url = `${this.apiBase}/transactions.php?limit=${perPage}&page=${this.transactionsCurrentPage}&_t=${Date.now()}`;
                if (entityType) url += `&entity_type=${encodeURIComponent(entityType)}`;
                if (entityId) url += `&entity_id=${encodeURIComponent(entityId)}`;
                if (search) url += `&search=${encodeURIComponent(search)}`;
                const response = await fetch(url, {
                    cache: 'no-store',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Pragma': 'no-cache'
                    }
                });
                
                const data = await response.json();
                if (!data.success || !data.transactions) {
                    throw new Error(data.message || 'Failed to load transactions');
                }
                // Update pagination
                this.transactionsTotalCount = data.total || data.transactions.length;
                this.transactionsTotalPages = Math.ceil(this.transactionsTotalCount / perPage);
                this.transactionsPerPage = perPage;
                // Render transactions
                if (data.transactions.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="17" class="empty-row">
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No transactions found</p>
                                </div>
                            </td>
                        </tr>
                    `;
                } else {
                    tbody.innerHTML = data.transactions.map(trans => {
                        const entityTypeName = trans.entity_type ? trans.entity_type.charAt(0).toUpperCase() + trans.entity_type.slice(1) : 'N/A';
                        const statusBadge = trans.status === 'posted' ? 'badge-success' : 'badge-warning';
                        return `
                            <tr>
                                <td>${trans.id || ''}</td>
                                <td>${trans.transaction_date || ''}</td>
                                <td>${this.escapeHtml(trans.entity_name || 'N/A')}</td>
                                <td><span class="badge badge-info">${entityTypeName}</span></td>
                                <td>${this.escapeHtml(trans.reference_number || '')}</td>
                                <td>${this.escapeHtml(trans.description || '')}</td>
                                <td class="text-right">${this.formatCurrency(trans.debit || 0, trans.currency || this.getDefaultCurrencySync())}</td>
                                <td class="text-right">${this.formatCurrency(trans.credit || 0, trans.currency || this.getDefaultCurrencySync())}</td>
                                <td><span class="badge ${statusBadge}">${trans.status || 'draft'}</span></td>
                                <td class="checkbox-col">
                                    <input type="checkbox" class="checkbox-modern" data-id="${trans.id}">
                                </td>
                                <td class="actions-col">
                                    <button class="btn-icon" data-action="view-transaction" data-id="${trans.id}" title="View">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon" data-action="edit-transaction" data-id="${trans.id}" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    }).join('');
                }
                // Update pagination controls
                this.updateModalTransactionsPagination();
            } catch (error) {
                const tbody = document.getElementById('modalEntityTransactionsTable')?.querySelector('tbody');
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="17" class="error-row">
                                <div class="error-state">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <p>Error loading transactions: ${this.escapeHtml(error.message)}</p>
                                </div>
                            </td>
                        </tr>
                    `;
                }
                console.error('Error loading modal transactions:', error);
            }
        },

        async loadEntitiesForSelect(entityType, selectElement, agentId = null, subagentId = null) {
            if (!selectElement) {
                return;
            }
            
            if (!entityType || entityType === '') {
                selectElement.innerHTML = '<option value="">Select Entity</option>';
                return;
            }
            
            try {
                // Show loading state
                selectElement.innerHTML = '<option value="">Loading...</option>';
                selectElement.disabled = true;
                
                let url = `${this.apiBase}/entities.php?entity_type=${encodeURIComponent(entityType)}&_t=${Date.now()}`;
                if (agentId !== null && agentId !== '') {
                    url += `&agent_id=${encodeURIComponent(agentId)}`;
                }
                if (subagentId !== null && subagentId !== '') {
                    url += `&subagent_id=${encodeURIComponent(subagentId)}`;
                }
                const response = await fetch(url, {
                    cache: 'no-store',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
                }
                
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load entities');
                }
                
                if (data.entities && Array.isArray(data.entities)) {
                    // Deduplicate entities using Map for unique keys
                    // Primary deduplication: by entity_type:ID combination
                    const uniqueEntities = new Map();
                    const seenIds = new Set(); // Track by ID only for this entity type
                    
                    data.entities.forEach(entity => {
                        if (!entity || !entity.id) return;
                        
                        const entityId = String(entity.id);
                        const key = `${(entity.entity_type || entityType).toLowerCase()}:${entityId}`;
                        
                        // Skip if we've already seen this exact ID (primary check)
                        if (seenIds.has(entityId)) {
                            return;
                        }
                        
                        // Only add if not already in map by key
                        if (!uniqueEntities.has(key)) {
                            uniqueEntities.set(key, entity);
                            seenIds.add(entityId);
                        }
                    });
                    
                    // Convert to array and sort alphabetically
                    const sortedEntities = Array.from(uniqueEntities.values()).sort((a, b) => {
                        const nameA = (a.display_name || a.name || '').toLowerCase();
                        const nameB = (b.display_name || b.name || '').toLowerCase();
                        return nameA.localeCompare(nameB);
                    });
                    
                    // Clear and rebuild dropdown - clear completely first
                    selectElement.innerHTML = '';
                    
                    // Add default option
                    const defaultOption = document.createElement('option');
                    defaultOption.value = '';
                    defaultOption.textContent = 'Select Entity';
                    selectElement.appendChild(defaultOption);
                    
                    let finalUnique = null;
                    
                    if (sortedEntities.length === 0) {
                        const noDataOption = document.createElement('option');
                        noDataOption.value = '';
                        noDataOption.textContent = 'No entities found';
                        noDataOption.disabled = true;
                        selectElement.appendChild(noDataOption);
                    } else {
                        // Final deduplication check before adding to DOM - only by ID value
                        finalUnique = new Map();
                        const finalSeenIds = new Set();
                        
                        sortedEntities.forEach(entity => {
                            const entityId = String(entity.id);
                            
                            // Skip if we've already added this exact ID (not by text, as names can repeat)
                            if (finalSeenIds.has(entityId)) {
                                return;
                            }
                            
                            // Add to final unique set
                            finalUnique.set(entityId, entity);
                            finalSeenIds.add(entityId);
                        });
                        
                        // Now add only truly unique entities by ID
                        // Also check DOM to prevent duplicates
                        const existingValues = new Set();
                        Array.from(finalUnique.values()).forEach(entity => {
                            const entityId = String(entity.id);
                            const optionText = entity.display_name || entity.name || `Entity #${entity.id}`;
                            
                            if (existingValues.has(entityId)) {
                                return;
                            }
                            
                            // Also check if option with this value already exists
                            const existingOption = Array.from(selectElement.options).find(opt => opt.value === entityId);
                            if (existingOption) {
                                return;
                            }
                            
                            const option = document.createElement('option');
                            // For modal filter, use format: entity_type:entity_id
                            // For other selects, use just entity_id
                            const isModalFilter = selectElement.id === 'modalEntityFilter';
                            option.value = isModalFilter ? `${entityType}:${entityId}` : entityId;
                            option.textContent = optionText;
                            selectElement.appendChild(option);
                            existingValues.add(entityId);
                        });
                    }
                    
                    selectElement.disabled = false;
                } else {
                    selectElement.innerHTML = '<option value="">No entities available</option>';
                    selectElement.disabled = false;
                }
            } catch (error) {
                selectElement.innerHTML = '<option value="">Error loading entities</option>';
                selectElement.disabled = false;
                this.showToast('Failed to load entities: ' + error.message, 'error');
            }
        },

        async populateCostCenterSelect(selectElement) {
            if (!selectElement) return;
            
            try {
                const response = await fetch(`${this.apiBase}/cost-centers.php?status=active`, {
                    credentials: 'include'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.success && data.cost_centers) {
                    // Preserve existing value if any
                    const currentValue = selectElement.value;
                    selectElement.innerHTML = '<option value="">- Main Center</option>';
                    data.cost_centers.forEach(cc => {
                        const option = document.createElement('option');
                        option.value = cc.id;
                        option.textContent = `${cc.code || ''} - ${cc.name}`.trim();
                        selectElement.appendChild(option);
                    });
                    // Restore value if it still exists
                    if (currentValue) {
                        const optionExists = Array.from(selectElement.options).some(opt => opt.value === currentValue);
                        if (optionExists) {
                            selectElement.value = currentValue;
                        }
                    }
                }
            } catch (error) {
                console.error('Error populating cost center select:', error);
                // Keep default option
            }
        },

        updateModalArPagination(totalEntries, currentPage, perPage) {
            const totalPages = Math.ceil(totalEntries / perPage);
            const startIndex = totalEntries > 0 ? (currentPage - 1) * perPage + 1 : 0;
            const endIndex = Math.min(currentPage * perPage, totalEntries);
            // Only update top pagination (matching General Ledger style)
            const infoEl = document.getElementById('modalArPaginationInfoTop');
            const firstBtn = document.getElementById('modalArFirstTop');
            const prevBtn = document.getElementById('modalArPrevTop');
            const nextBtn = document.getElementById('modalArNextTop');
            const lastBtn = document.getElementById('modalArLastTop');
            const pageNumbersEl = document.getElementById('modalArPageNumbersTop');
                if (infoEl) {
                    infoEl.textContent = totalEntries > 0 
                    ? `Showing ${startIndex} to ${endIndex} of ${totalEntries} invoices`
                    : 'No invoices found';
            }
            // Update navigation buttons
            const isFirstPage = currentPage <= 1;
            const isLastPage = currentPage >= totalPages;
            if (firstBtn) {
                firstBtn.disabled = isFirstPage;
                firstBtn.classList.toggle('disabled', isFirstPage);
                firstBtn.setAttribute('data-page', '1');
                }
                if (prevBtn) {
                prevBtn.disabled = isFirstPage;
                prevBtn.classList.toggle('disabled', isFirstPage);
                }
                if (nextBtn) {
                nextBtn.disabled = isLastPage;
                nextBtn.classList.toggle('disabled', isLastPage);
            }
            if (lastBtn) {
                lastBtn.disabled = isLastPage;
                lastBtn.classList.toggle('disabled', isLastPage);
                lastBtn.setAttribute('data-page', totalPages.toString());
                }
                if (pageNumbersEl) {
                    let pageNumbersHTML = '';
                
                if (totalPages <= 1) {
                    pageNumbersHTML = `<span class="page-number page-number-current">1</span>`;
                } else if (totalPages <= 7) {
                    // Show all pages if 7 or fewer
                    for (let i = 1; i <= totalPages; i++) {
                        const isActive = i === currentPage ? 'page-number-current' : '';
                        pageNumbersHTML += `<button class="page-number ${isActive}" data-action="modal-ar-page" data-page="${i}">${i}</button>`;
                    }
                } else {
                    // Smart pagination for many pages
                    let startPage, endPage;
                    
                    if (currentPage <= 3) {
                        startPage = 1;
                        endPage = 5;
                    } else if (currentPage >= totalPages - 2) {
                        startPage = totalPages - 4;
                        endPage = totalPages;
                    } else {
                        startPage = currentPage - 2;
                        endPage = currentPage + 2;
                    }
                    
                    if (startPage > 1) {
                        pageNumbersHTML += `<button class="page-number" data-action="modal-ar-page" data-page="1">1</button>`;
                        if (startPage > 2) {
                            pageNumbersHTML += `<span class="page-ellipsis">...</span>`;
                        }
                    }
                    for (let i = startPage; i <= endPage; i++) {
                        const isActive = i === currentPage ? 'page-number-current' : '';
                        pageNumbersHTML += `<button class="page-number ${isActive}" data-action="modal-ar-page" data-page="${i}">${i}</button>`;
                    }
                    
                    if (endPage < totalPages) {
                        if (endPage < totalPages - 1) {
                            pageNumbersHTML += `<span class="page-ellipsis">...</span>`;
                        }
                        pageNumbersHTML += `<button class="page-number" data-action="modal-ar-page" data-page="${totalPages}">${totalPages}</button>`;
                    }
                }
                
                    pageNumbersEl.innerHTML = pageNumbersHTML;
                }
        },

        updateModalApPagination(totalEntries, currentPage, perPage) {
            const totalPages = Math.ceil(totalEntries / perPage);
            const startIndex = totalEntries > 0 ? (currentPage - 1) * perPage + 1 : 0;
            const endIndex = Math.min(currentPage * perPage, totalEntries);
            ['Top', 'Bottom'].forEach(position => {
                const infoEl = document.getElementById(`modalApPaginationInfo${position}`);
                const prevBtn = document.getElementById(`modalApPrev${position}`);
                const nextBtn = document.getElementById(`modalApNext${position}`);
                const pageNumbersEl = document.getElementById(`modalApPageNumbers${position}`);
                if (infoEl) {
                    infoEl.textContent = totalEntries > 0 
                        ? `Showing ${startIndex} to ${endIndex} of ${totalEntries} entries`
                        : 'No entries found';
                }
                if (prevBtn) {
                    prevBtn.disabled = currentPage <= 1;
                    prevBtn.classList.toggle('disabled', currentPage <= 1);
                }
                if (nextBtn) {
                    nextBtn.disabled = currentPage >= totalPages;
                    nextBtn.classList.toggle('disabled', currentPage >= totalPages);
                }
                if (pageNumbersEl) {
                    let pageNumbersHTML = '';
                    const maxVisible = 5;
                    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
                    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
                    
                    if (endPage - startPage < maxVisible - 1) {
                        startPage = Math.max(1, endPage - maxVisible + 1);
                    }
                    for (let i = startPage; i <= endPage; i++) {
                        const isActive = i === currentPage ? 'active' : '';
                        pageNumbersHTML += `<button class="page-btn ${isActive}" data-action="modal-ap-page" data-page="${i}">${i}</button>`;
                    }
                    pageNumbersEl.innerHTML = pageNumbersHTML;
                }
            });
        },

        updateModalBankPagination(totalEntries, currentPage, perPage) {
            const totalPages = Math.ceil(totalEntries / perPage);
            const startIndex = totalEntries > 0 ? (currentPage - 1) * perPage + 1 : 0;
            const endIndex = Math.min(currentPage * perPage, totalEntries);
            // Only update top pagination (matching General Ledger style)
            const infoEl = document.getElementById('modalBankPaginationInfoTop');
            const firstBtn = document.getElementById('modalBankFirstTop');
            const prevBtn = document.getElementById('modalBankPrevTop');
            const nextBtn = document.getElementById('modalBankNextTop');
            const lastBtn = document.getElementById('modalBankLastTop');
            const pageNumbersEl = document.getElementById('modalBankPageNumbersTop');
                if (infoEl) {
                    infoEl.textContent = totalEntries > 0 
                    ? `Showing ${startIndex} to ${endIndex} of ${totalEntries} transactions`
                    : 'No transactions found';
            }
            // Update navigation buttons
            const isFirstPage = currentPage <= 1;
            const isLastPage = currentPage >= totalPages;
            if (firstBtn) {
                firstBtn.disabled = isFirstPage;
                firstBtn.classList.toggle('disabled', isFirstPage);
                firstBtn.setAttribute('data-page', '1');
                }
                if (prevBtn) {
                prevBtn.disabled = isFirstPage;
                prevBtn.classList.toggle('disabled', isFirstPage);
                }
                if (nextBtn) {
                nextBtn.disabled = isLastPage;
                nextBtn.classList.toggle('disabled', isLastPage);
            }
            if (lastBtn) {
                lastBtn.disabled = isLastPage;
                lastBtn.classList.toggle('disabled', isLastPage);
                lastBtn.setAttribute('data-page', totalPages.toString());
                }
                if (pageNumbersEl) {
                    let pageNumbersHTML = '';
                
                if (totalPages <= 1) {
                    pageNumbersHTML = `<span class="page-number page-number-current">1</span>`;
                } else if (totalPages <= 7) {
                    // Show all pages if 7 or fewer
                    for (let i = 1; i <= totalPages; i++) {
                        const isActive = i === currentPage ? 'page-number-current' : '';
                        pageNumbersHTML += `<button class="page-number ${isActive}" data-action="modal-bank-page" data-page="${i}">${i}</button>`;
                    }
                } else {
                    // Smart pagination for many pages
                    let startPage, endPage;
                    
                    if (currentPage <= 3) {
                        startPage = 1;
                        endPage = 5;
                    } else if (currentPage >= totalPages - 2) {
                        startPage = totalPages - 4;
                        endPage = totalPages;
                    } else {
                        startPage = currentPage - 2;
                        endPage = currentPage + 2;
                    }
                    
                    if (startPage > 1) {
                        pageNumbersHTML += `<button class="page-number" data-action="modal-bank-page" data-page="1">1</button>`;
                        if (startPage > 2) {
                            pageNumbersHTML += `<span class="page-ellipsis">...</span>`;
                        }
                    }
                    for (let i = startPage; i <= endPage; i++) {
                        const isActive = i === currentPage ? 'page-number-current' : '';
                        pageNumbersHTML += `<button class="page-number ${isActive}" data-action="modal-bank-page" data-page="${i}">${i}</button>`;
                    }
                    
                    if (endPage < totalPages) {
                        if (endPage < totalPages - 1) {
                            pageNumbersHTML += `<span class="page-ellipsis">...</span>`;
                        }
                        pageNumbersHTML += `<button class="page-number" data-action="modal-bank-page" data-page="${totalPages}">${totalPages}</button>`;
                    }
                }
                
                    pageNumbersEl.innerHTML = pageNumbersHTML;
                }
        },

        updateModalEntityPagination(totalEntries, currentPage, perPage) {
            const totalPages = Math.ceil(totalEntries / perPage);
            const startIndex = totalEntries > 0 ? (currentPage - 1) * perPage + 1 : 0;
            const endIndex = Math.min(currentPage * perPage, totalEntries);
            ['Top', 'Bottom'].forEach(position => {
                const infoEl = document.getElementById(`modalEntityPaginationInfo${position}`);
                const prevBtn = document.getElementById(`modalEntityPrev${position}`);
                const nextBtn = document.getElementById(`modalEntityNext${position}`);
                const pageNumbersEl = document.getElementById(`modalEntityPageNumbers${position}`);
                if (infoEl) {
                    infoEl.textContent = totalEntries > 0 
                        ? `Showing ${startIndex} to ${endIndex} of ${totalEntries} entries`
                        : 'No entries found';
                }
                if (prevBtn) {
                    prevBtn.disabled = currentPage <= 1;
                    prevBtn.classList.toggle('disabled', currentPage <= 1);
                }
                if (nextBtn) {
                    nextBtn.disabled = currentPage >= totalPages;
                    nextBtn.classList.toggle('disabled', currentPage >= totalPages);
                }
                if (pageNumbersEl) {
                    let pageNumbersHTML = '';
                    const maxVisible = 5;
                    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
                    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
                    
                    if (endPage - startPage < maxVisible - 1) {
                        startPage = Math.max(1, endPage - maxVisible + 1);
                    }
                    for (let i = startPage; i <= endPage; i++) {
                        const isActive = i === currentPage ? 'active' : '';
                        pageNumbersHTML += `<button class="page-btn ${isActive}" data-action="modal-entity-page" data-page="${i}">${i}</button>`;
                    }
                    pageNumbersEl.innerHTML = pageNumbersHTML;
                }
            });
        },

        updateModalLedgerPagination(totalEntries, currentPage, perPage) {
            const totalPages = Math.ceil(totalEntries / perPage);
            const startIndex = totalEntries > 0 ? (currentPage - 1) * perPage + 1 : 0;
            const endIndex = Math.min(currentPage * perPage, totalEntries);
            // Only update top pagination
            const infoEl = document.getElementById('modalLedgerPaginationInfoTop');
            const firstBtn = document.getElementById('modalLedgerFirstTop');
            const prevBtn = document.getElementById('modalLedgerPrevTop');
            const nextBtn = document.getElementById('modalLedgerNextTop');
            const lastBtn = document.getElementById('modalLedgerLastTop');
            const pageNumbersEl = document.getElementById('modalLedgerPageNumbersTop');
                if (infoEl) {
                    infoEl.textContent = totalEntries > 0 
                        ? `Showing ${startIndex} to ${endIndex} of ${totalEntries} entries`
                        : 'No entries found';
                }
            // Update navigation buttons
            const isFirstPage = currentPage <= 1;
            const isLastPage = currentPage >= totalPages;
            if (firstBtn) {
                firstBtn.disabled = isFirstPage;
                firstBtn.classList.toggle('disabled', isFirstPage);
                firstBtn.setAttribute('data-page', '1');
            }
                if (prevBtn) {
                prevBtn.disabled = isFirstPage;
                prevBtn.classList.toggle('disabled', isFirstPage);
                }
                if (nextBtn) {
                nextBtn.disabled = isLastPage;
                nextBtn.classList.toggle('disabled', isLastPage);
            }
            if (lastBtn) {
                lastBtn.disabled = isLastPage;
                lastBtn.classList.toggle('disabled', isLastPage);
                lastBtn.setAttribute('data-page', totalPages.toString());
                }
                if (pageNumbersEl) {
                    let pageNumbersHTML = '';
                
                if (totalPages <= 1) {
                    pageNumbersHTML = `<span class="page-number page-number-current">1</span>`;
                } else if (totalPages <= 7) {
                    // Show all pages if 7 or fewer
                    for (let i = 1; i <= totalPages; i++) {
                        const isActive = i === currentPage ? 'page-number-current' : '';
                        pageNumbersHTML += `<button class="page-number ${isActive}" data-action="modal-ledger-page" data-page="${i}">${i}</button>`;
                    }
                } else {
                    // Smart pagination for many pages
                    let startPage, endPage;
                    
                    if (currentPage <= 3) {
                        // Near the beginning
                        startPage = 1;
                        endPage = 5;
                    } else if (currentPage >= totalPages - 2) {
                        // Near the end
                        startPage = totalPages - 4;
                        endPage = totalPages;
                    } else {
                        // In the middle
                        startPage = currentPage - 2;
                        endPage = currentPage + 2;
                    }
                    
                    // Always show first page
                    if (startPage > 1) {
                        pageNumbersHTML += `<button class="page-number" data-action="modal-ledger-page" data-page="1">1</button>`;
                        if (startPage > 2) {
                            pageNumbersHTML += `<span class="page-ellipsis">...</span>`;
                        }
                    }
                    
                    // Show page range
                    for (let i = startPage; i <= endPage; i++) {
                        const isActive = i === currentPage ? 'page-number-current' : '';
                        pageNumbersHTML += `<button class="page-number ${isActive}" data-action="modal-ledger-page" data-page="${i}">${i}</button>`;
                    }
                    
                    // Always show last page
                    if (endPage < totalPages) {
                        if (endPage < totalPages - 1) {
                            pageNumbersHTML += `<span class="page-ellipsis">...</span>`;
                        }
                        pageNumbersHTML += `<button class="page-number" data-action="modal-ledger-page" data-page="${totalPages}">${totalPages}</button>`;
                    }
                }
                
                    pageNumbersEl.innerHTML = pageNumbersHTML;
                }
        },

        updateBulkActions(type) {
            let checkboxes, bulkBar, countEl, tableId;
            
            switch(type) {
                case 'ar':
                    tableId = 'modalInvoicesTable';
                    bulkBar = document.getElementById('bulkActionsAr');
                    countEl = document.getElementById('bulkSelectedCountAr');
                    break;
                case 'ap':
                    tableId = 'modalBillsTable';
                    bulkBar = document.getElementById('bulkActionsAp');
                    countEl = document.getElementById('bulkSelectedCountAp');
                    break;
                case 'bank':
                    tableId = 'modalBankAccountsTable';
                    bulkBar = document.getElementById('bulkActionsBank');
                    countEl = document.getElementById('bulkSelectedCountBank');
                    break;
                case 'entity':
                    tableId = 'modalEntityTransactionsTable';
                    bulkBar = document.getElementById('bulkActionsEntity');
                    countEl = document.getElementById('bulkSelectedCountEntity');
                    break;
                case 'ledger':
                    tableId = 'modalJournalEntriesTable';
                    bulkBar = document.getElementById('bulkActionsLedger');
                    countEl = document.getElementById('bulkSelectedCountLedger');
                    break;
                default:
                    return;
            }
            
            checkboxes = document.querySelectorAll(`#${tableId} tbody input[type="checkbox"]:checked`);
            const count = checkboxes.length;
            
            if (countEl) {
                countEl.textContent = `${count} selected`;
            }
            
            if (bulkBar) {
                bulkBar.classList.toggle('show', count > 0);
            }
        },

        getSelectedIds(type) {
            let tableId;
            switch(type) {
                case 'ar':
                    tableId = 'modalInvoicesTable';
                    break;
                case 'ap':
                    tableId = 'modalBillsTable';
                    break;
                case 'bank':
                    tableId = 'modalBankAccountsTable';
                    break;
                case 'entity':
                    tableId = 'modalEntityTransactionsTable';
                    break;
                case 'ledger':
                    tableId = 'modalJournalEntriesTable';
                    break;
                default:
                    return [];
            }
            
            const checkboxes = document.querySelectorAll(`#${tableId} tbody input[type="checkbox"]:checked`);
            return Array.from(checkboxes).map(cb => parseInt(cb.getAttribute('data-id'))).filter(id => !isNaN(id));
        },

        openAccountModal(accountId = null) {
            const isEdit = !!accountId;
            const content = `
                <form id="accountForm" data-account-id="${accountId || 'null'}">
                    <div class="accounting-modal-form-group">
                        <label>Account Code *</label>
                        <input type="text" name="account_code" id="accountCodeInput" required ${isEdit ? '' : 'readonly'} placeholder="Auto-generated" title="${isEdit ? 'Account code' : 'Account code is auto-generated'}">
                        ${isEdit ? '' : '<small class="form-help-text">Code will be auto-generated based on account type</small>'}
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Account Name *</label>
                        <input type="text" name="account_name" required placeholder="e.g., Cash">
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Account Type *</label>
                        <select name="account_type" id="accountTypeSelect" required>
                            <option value="ASSET">Asset</option>
                            <option value="LIABILITY">Liability</option>
                            <option value="EQUITY">Equity</option>
                            <option value="REVENUE">Revenue</option>
                            <option value="EXPENSE">Expense</option>
                        </select>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Normal Balance *</label>
                        <select name="normal_balance" required>
                            <option value="DEBIT">Debit</option>
                            <option value="CREDIT">Credit</option>
                        </select>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Opening Balance</label>
                        <input type="number" name="opening_balance" step="0.01" value="0.00" placeholder="0.00">
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Account description"></textarea>
                    </div>
                    <div class="accounting-modal-actions">
                        <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Account</button>
                    </div>
                </form>
            `;
            this.showModal(isEdit ? 'Edit Account' : 'Create Account', content);
            // Load account data if editing
            if (accountId) {
                setTimeout(async () => {
                    try {
                        const response = await fetch(`${this.apiBase}/accounts.php?id=${accountId}`);
                        let data;
                        try {
                            data = await response.json();
                        } catch (jsonError) {
                            console.error('Error parsing response when loading account data:', jsonError);
                            return; // Silently fail if JSON parsing fails
                        }
                        if (data.success && data.account) {
                            const modal = document.getElementById('accountFormModal');
                            const form = modal ? modal.querySelector('#accountForm') : document.getElementById('accountForm');
                            if (form) {
                                form.querySelector('[name="account_code"]').value = data.account.account_code || '';
                                form.querySelector('[name="account_name"]').value = data.account.account_name || '';
                                // Normalize to uppercase for ENUM values
                                const accountType = (data.account.account_type || 'ASSET').toUpperCase();
                                const normalBalance = (data.account.normal_balance || 'DEBIT').toUpperCase();
                                form.querySelector('[name="account_type"]').value = accountType;
                                form.querySelector('[name="normal_balance"]').value = normalBalance;
                                form.querySelector('[name="opening_balance"]').value = data.account.opening_balance || 0;
                                form.querySelector('[name="description"]').value = data.account.description || '';
                            }
                        }
                    } catch (error) {
                    }
                }, 100);
            }
            // Add form submit handler and auto-generate account code
            setTimeout(async () => {
                const modal = document.getElementById('accountFormModal');
                const form = modal ? modal.querySelector('#accountForm') : document.getElementById('accountForm');
                if (!form) {
                    console.error('Account form not found. Modal ID:', modal?.id, 'Form ID:', document.getElementById('accountForm')?.id);
                    this.showToast('Form not found. Please try again.', 'error');
                    return;
                }
                
                // Remove any existing listeners by cloning
                const newForm = form.cloneNode(true);
                form.parentNode.replaceChild(newForm, form);
                
                // Auto-generate account code for new accounts
                if (!accountId) {
                    const accountTypeSelect = newForm.querySelector('#accountTypeSelect');
                    const accountCodeInput = newForm.querySelector('#accountCodeInput');
                    
                    // Generate code when account type changes
                    if (accountTypeSelect && accountCodeInput) {
                        accountTypeSelect.addEventListener('change', async () => {
                            await this.generateAccountCode(accountTypeSelect.value, accountCodeInput);
                        });
                        
                        // Generate initial code
                        await this.generateAccountCode(accountTypeSelect.value, accountCodeInput);
                    }
                }
                
                newForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    const submitBtn = newForm.querySelector('button[type="submit"]');
                    const originalBtnText = submitBtn ? submitBtn.textContent : '';
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = accountId ? 'Updating...' : 'Creating...';
                    }
                    let saveSucceeded = false;
                    try {
                        const result = await this.saveAccount(accountId);
                        if (result === true) {
                            saveSucceeded = true;
                        }
                    } catch (error) {
                        console.error('Error saving account:', error);
                    } finally {
                        // Only re-enable button if save failed (modal will close on success)
                        if (!saveSucceeded && submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalBtnText || (accountId ? 'Update Account' : 'Create Account');
                        }
                    }
                });
                
                // Also handle close modal button
                const closeBtn = modal ? modal.querySelector('[data-action="close-modal"]') : null;
                if (closeBtn) {
                    closeBtn.addEventListener('click', async () => {
                        await this.closeModalWithConfirmation();
                    });
                }
                
                // Track form changes
                const formInputs = newForm.querySelectorAll('input, textarea, select');
                formInputs.forEach(input => {
                    input.addEventListener('change', () => {
                        this.markFormAsChanged(newForm);
                    });
                    input.addEventListener('input', () => {
                        this.markFormAsChanged(newForm);
                    });
                });
            }, 150);
        },

        exportAccounts() {
            const url = `${this.apiBase}/accounts.php?is_active=1`;
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.accounts && data.accounts.length > 0) {
                        this.exportAccountsToCSV(data.accounts);
                    } else {
                        this.showToast('No accounts to export', 'error');
                    }
                })
                .catch(() => this.showToast('Error exporting accounts', 'error'));
        },

        bulkExportAccounts(accountIds) {
            if (!accountIds || accountIds.length === 0) return;
            
            // Get all accounts data and filter by selected IDs
            const url = `${this.apiBase}/accounts.php?is_active=1`;
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.accounts) {
                        const selectedAccounts = data.accounts.filter(acc => accountIds.includes(acc.id));
                        this.exportAccountsToCSV(selectedAccounts);
                    }
                })
                .catch(error => {
                    this.showToast('Error exporting accounts', 'error');
                });
        },

        exportAccountsToCSV(accounts) {
            if (!accounts || accounts.length === 0) {
                this.showToast('No accounts to export', 'error');
                return;
            }
            
            const headers = ['Code', 'Name', 'Type', 'Normal', 'Opening', 'Balance', 'Currency', 'Status', 'Description'];
            const rows = accounts.map(account => [
                account.account_code || '',
                account.account_name || '',
                account.account_type || '',
                (account.normal_balance || 'DEBIT').toUpperCase(),
                account.opening_balance || 0,
                account.current_balance || 0,
                account.currency || this.getDefaultCurrencySync(),
                account.is_active ? 'Active' : 'Inactive',
                account.description || ''
            ]);
            
            const csvContent = [
                headers.join(','),
                ...rows.map(row => row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(','))
            ].join('\n');
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `chart-of-accounts-${new Date().toISOString().split('T')[0]}.csv`);
            link.classList.add('coa-download-link-hidden');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            this.showToast(`${accounts.length} account(s) exported successfully`, 'success');
        },

        async saveJournalEntry(entryId = null) {
            const form = document.getElementById('journalEntryForm');
            if (!form) { this.showToast('Form not found', 'error'); return false; }
            const debitLines = []; const creditLines = [];
            form.querySelectorAll('#journalDebitLinesBody .ledger-line-row').forEach((row) => {
                const accountSelect = row.querySelector('.account-select');
                const amountInput = row.querySelector('.debit-amount');
                const accountId = accountSelect ? parseInt(accountSelect.value) : 0;
                const amount = amountInput ? parseFloat(amountInput.value || 0) : 0;
                if (accountId > 0 && amount > 0) {
                    debitLines.push({ account_id: accountId, cost_center_id: row.querySelector('.cost-center-select') ? (parseInt(row.querySelector('.cost-center-select').value) || null) : null, description: row.querySelector('.line-description')?.value?.trim() || '', vat_report: row.querySelector('.vat-checkbox')?.checked || false, amount });
                }
            });
            form.querySelectorAll('#journalCreditLinesBody .ledger-line-row').forEach((row) => {
                const accountSelect = row.querySelector('.account-select');
                const amountInput = row.querySelector('.credit-amount');
                const accountId = accountSelect ? parseInt(accountSelect.value) : 0;
                const amount = amountInput ? parseFloat(amountInput.value || 0) : 0;
                if (accountId > 0 && amount > 0) {
                    creditLines.push({ account_id: accountId, cost_center_id: row.querySelector('.cost-center-select') ? (parseInt(row.querySelector('.cost-center-select').value) || null) : null, description: row.querySelector('.line-description')?.value?.trim() || '', vat_report: row.querySelector('.vat-checkbox')?.checked || false, amount });
                }
            });
            const entryDate = form.querySelector('#journalEntryDate')?.value;
            const branchId = form.querySelector('#journalBranchSelect')?.value;
            const description = form.querySelector('textarea[name="description"]')?.value?.trim();
            if (!entryDate) { this.showToast('Please select a journal date', 'error'); return false; }
            if (!branchId) { this.showToast('Please select a branch', 'error'); return false; }
            if (!description) { this.showToast('Please enter a description', 'error'); return false; }
            if (debitLines.length === 0 && creditLines.length === 0) { this.showToast('Please add at least one debit or credit line', 'error'); return false; }
            const totalDebit = debitLines.reduce((s, l) => s + l.amount, 0); const totalCredit = creditLines.reduce((s, l) => s + l.amount, 0);
            if (Math.abs(totalDebit - totalCredit) > 0.01) { this.showToast('Entry is not balanced', 'error'); return false; }
            const firstDebit = debitLines[0] || null; const firstCredit = creditLines[0] || null; const primary = firstDebit || firstCredit;
            if (!primary) { this.showToast('Please add at least one line', 'error'); return false; }
            const data = { entry_date: entryDate, branch_id: parseInt(branchId) || branchId, description, account_id: primary.account_id, debit: firstDebit ? firstDebit.amount : 0, credit: firstCredit ? firstCredit.amount : 0, total_debit: totalDebit, total_credit: totalCredit, currency: 'SAR', debit_lines: debitLines, credit_lines: creditLines, cost_center_id: primary.cost_center_id || null };
            try {
                const url = entryId ? `${this.apiBase}/journal-entries.php?id=${entryId}` : `${this.apiBase}/journal-entries.php`;
                const controller = new AbortController(); setTimeout(() => controller.abort(), 20000);
                const response = await fetch(url, { method: entryId ? 'PUT' : 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data), signal: controller.signal });
                const responseData = JSON.parse(await response.text().catch(() => '{}'));
                if (response.ok && responseData?.success) {
                    form.setAttribute('data-saved', 'true');
                    this.showToast(`Journal entry ${entryId ? 'updated' : 'created'} successfully!`, 'success');
                    const modal = form.closest('.accounting-modal'); if (modal) { modal.classList.remove('accounting-modal-visible', 'show-modal'); modal.classList.add('accounting-modal-hidden'); if (this.activeModal === modal) this.activeModal = null; document.body.classList.remove('body-no-scroll'); }
                    setTimeout(async () => { if (!entryId) this.modalLedgerCurrentPage = 1; await this.loadModalJournalEntries(); await this.loadJournalEntries(); this.refreshAllModules(); }, 500);
                    return true;
                }
                this.showToast(responseData?.message || 'Failed to save', 'error'); return false;
            } catch (e) {
                this.showToast(e?.name === 'AbortError' ? 'Request timeout' : (e?.message || 'Error saving'), 'error'); return false;
            }
        },

        async saveInvoice(invoiceId = null) {
            const form = document.getElementById('invoiceForm'); if (!form) { this.showToast('Invoice form not found', 'error'); return; }
            const formData = new FormData(form); const data = Object.fromEntries(formData);
            const customerInputs = form.querySelectorAll('input[name="customers[]"]'); const customers = Array.from(customerInputs).map(i => i.value.trim()).filter(v => v); data.customers = customers.join(', '); delete data['customers[]'];
            if (data.agent_id) { data.entity_type = 'agent'; data.entity_id = data.agent_id; } else if (data.subagent_id) { data.entity_type = 'subagent'; data.entity_id = data.subagent_id; } else if (data.worker_id) { data.entity_type = 'worker'; data.entity_id = data.worker_id; } else if (data.hr_id) { data.entity_type = 'hr'; data.entity_id = data.hr_id; } else { data.entity_type = null; data.entity_id = null; } delete data.agent_id; delete data.subagent_id; delete data.worker_id; delete data.hr_id;
            data.customer_id = data.customer_id || null; data.total_amount = parseFloat(data.total_amount) || 0; data.tax_included = !!(form.querySelector('#invoiceTaxCheckbox')?.checked); delete data.vat_report; delete data.payment_voucher;
            try {
                const response = await fetch(invoiceId ? `${this.apiBase}/invoices.php?id=${invoiceId}` : `${this.apiBase}/invoices.php`, { method: invoiceId ? 'PUT' : 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                const responseData = await response.json().catch(() => ({}));
                if (response.ok && responseData?.success !== false) { this.showToast(`Invoice ${invoiceId ? 'updated' : 'created'} successfully!`, 'success'); await this.loadModalInvoices(); this.closeModal(); this.refreshAllModules(); if (typeof this.loadInvoices === 'function') this.loadInvoices(); } else { this.showToast(responseData?.message || 'Failed to save invoice', 'error'); }
            } catch (e) { this.showToast('Error saving invoice: ' + (e?.message || 'Unknown'), 'error'); }
        },

        async saveBill(billId = null) {
            const form = document.getElementById('billForm'); if (!form) { this.showToast('Bill form not found', 'error'); return; }
            const formData = new FormData(form); const data = Object.fromEntries(formData);
            if (!data.bill_date || !data.due_date || !data.total_amount || parseFloat(data.total_amount) <= 0) { this.showToast('Required fields missing or invalid', 'error'); return; }
            try {
                const response = await fetch(billId ? `${this.apiBase}/bills.php?id=${billId}` : `${this.apiBase}/bills.php`, { method: billId ? 'PUT' : 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                const responseData = await response.json().catch(() => ({}));
                if (response.ok && responseData?.success !== false) { this.showToast(`Bill ${billId ? 'updated' : 'created'} successfully!`, 'success'); await this.loadModalBills(); this.closeModal(); this.refreshAllModules(); if (typeof this.loadBills === 'function') this.loadBills(); } else { this.showToast(responseData?.message || 'Failed to save bill', 'error'); }
            } catch (e) { this.showToast('Error saving bill', 'error'); }
        },

        async saveBankAccount(bankId = null) {
            if (this._savingBankAccount) return; this._savingBankAccount = true;
            const form = document.getElementById('bankAccountForm'); if (!form || !form.isConnected) { this.showToast('Bank account form not found', 'error'); this._savingBankAccount = false; return; }
            const formData = new FormData(form); const data = Object.fromEntries(formData);
            if (data.initial_balance !== undefined) { data.opening_balance = data.initial_balance; delete data.initial_balance; }
            if (!data.bank_name || !data.account_name) { this.showToast('Bank name and account name are required', 'error'); this._savingBankAccount = false; return; }
            const isEdit = bankId !== null && bankId > 0; const url = isEdit ? `${this.apiBase}/banks.php?id=${bankId}` : `${this.apiBase}/banks.php`;
            try {
                const controller = new AbortController(); const timeoutId = setTimeout(() => controller.abort(), 30000);
                const response = await fetch(url, { method: isEdit ? 'PUT' : 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data), signal: controller.signal });
                clearTimeout(timeoutId);
                const responseData = await response.json().catch(() => ({}));
                if (response.ok && responseData?.success !== false) { this.showToast(`Bank account ${isEdit ? 'updated' : 'added'} successfully!`, 'success'); this.closeModal(); await this.loadBankAccounts(); await this.loadModalBankAccounts(); } else { this.showToast(responseData?.message || 'Failed to save', 'error'); }
            } catch (e) { this.showToast(e?.name === 'AbortError' ? 'Request timeout' : (e?.message || 'Error'), 'error'); } finally { this._savingBankAccount = false; }
        },

        async saveEntityTransaction(transactionId = null) {
            const form = document.getElementById('entityTransactionForm'); if (!form) { this.showToast('Form not found', 'error'); return; }
            if (!transactionId) transactionId = form.getAttribute('data-transaction-id') || null;
            const debitField = form.querySelector('[name="debit"]') || form.querySelector('#entityTransactionDebit'); const creditField = form.querySelector('[name="credit"]') || form.querySelector('#entityTransactionCredit');
            let debitValue = debitField && debitField.value ? parseFloat(debitField.value) : 0; let creditValue = creditField && creditField.value ? parseFloat(creditField.value) : 0; if (isNaN(debitValue)) debitValue = 0; if (isNaN(creditValue)) creditValue = 0;
            const formData = new FormData(form); const data = Object.fromEntries(formData); data.debit = debitValue; data.debit_amount = debitValue; data.credit = creditValue; data.credit_amount = creditValue;
            data.entry_type = form.querySelector('[name="entry_type"]')?.value || form.querySelector('#entityTransactionType')?.value || 'Manual';
            const entitySelect = form.querySelector('[name="entity_id"]') || form.querySelector('#entitySelect'); if (entitySelect?.value?.includes(':')) { const p = entitySelect.value.split(':'); data.entity_type = p[0]; data.entity_id = parseInt(p[1]); } else { data.entity_id = parseInt(data.entity_id); }
            data.total_amount = Math.max(debitValue, creditValue) || parseFloat(data.amount) || 0; if (transactionId) data.id = parseInt(transactionId);
            try {
                const response = await fetch(`${this.apiBase}/entity-transactions.php${transactionId ? `?id=${transactionId}` : ''}`, { method: transactionId ? 'PUT' : 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
                const responseData = await response.json().catch(() => ({}));
                if (response.ok && responseData?.success !== false) { this.showToast(`Transaction ${transactionId ? 'updated' : 'created'} successfully!`, 'success'); this.closeModal(); setTimeout(async () => { await this.loadJournalEntries(); const tbody = document.getElementById('entityTransactionsBody'); if (tbody) { this.entityTransactionsCurrentPage = 1; await this.loadEntityTransactionsData(); } this.refreshAllModules(); if (typeof this.loadModalTransactions === 'function') this.loadModalTransactions(); }, 800); } else { this.showToast(responseData?.message || 'Failed to save', 'error'); }
            } catch (e) { this.showToast('Error saving transaction', 'error'); }
        },

        showModal(title, content, size = 'normal', customModalId = null) {
            // Use different ID for specific modals to avoid conflicts with accounting-modal.js
            let modalId = customModalId || 'accountingModalProfessional';
            if (!customModalId) {
                if (title === 'General Ledger') {
                    modalId = 'generalLedgerModal';
                } else if (title === 'Chart of Accounts' || title === 'Chart of Accounts Management') {
                    modalId = 'chartOfAccountsModal';
                } else if (title === 'Create Account' || title === 'Edit Account') {
                    modalId = 'accountFormModal';
                } else if (title === 'Banking & Cash') {
                    modalId = 'bankingCashModal';
                } else if (title === 'Vouchers' || title === 'Expenses (Payment Vouchers)') {
                    modalId = 'vouchersModal';
                } else if (title === 'Create Payment Voucher' || title === 'Edit Payment Voucher') {
                    modalId = 'paymentVoucherModal';
                } else if (title === 'Receipt Vouchers') {
                    modalId = 'receiptVouchersListModal';
                } else if (title === 'Add Receipt Voucher' || title === 'Create Receipt Voucher' || title === 'Edit Receipt Voucher') {
                    modalId = 'receiptVoucherModal';
                } else if (title === 'Create Bank Transaction') {
                    modalId = 'bankTransactionModal';
                } else if (title === 'Add Bank Account') {
                    modalId = 'bankAccountFormModal';
                }
            }
            
            
            // Only check for existing modal with the same ID
            const existingModal = document.getElementById(modalId);
            if (existingModal) {
                // If it's our own modal (has overlay), remove it to recreate
                if (existingModal.querySelector('.accounting-modal-overlay')) {
                existingModal.remove();
                    this.activeModal = null;
                } else if (modalId === 'accountingModal' && !existingModal.querySelector('.accounting-modal-overlay')) {
                    // This is the accounting-modal.js modal (doesn't have overlay), use a different ID
                    modalId = 'accountingModalProfessional';
                }
            }
            // Create modal
            const modal = document.createElement('div');
            modal.id = modalId;
            modal.className = 'accounting-modal';
            if (size === 'large') {
                modal.classList.add('accounting-modal-large');
            }
            
            if (modalId === 'bankTransactionModal') {
                modal.classList.add('modal-z-10001');
            } else if (modalId === 'bankAccountFormModal') {
                modal.classList.add('modal-z-10002');
                const modalContent = modal.querySelector('.accounting-modal-content');
                if (modalContent) {
                    modalContent.classList.add('modal-content-z-10003');
                }
            } else if (modalId === 'paymentVoucherModal' || modalId === 'receiptVoucherModal') {
                modal.classList.add('modal-z-10003');
            } else if (modalId === 'receiptVouchersListModal') {
                modal.classList.add('modal-z-10000');
            } else if (modalId === 'viewVoucherModal') {
                modal.classList.add('modal-z-10003');
            }
            
            modal.innerHTML = `
                <div class="accounting-modal-overlay"></div>
                <div class="accounting-modal-content ${size === 'large' ? 'accounting-modal-content-large' : ''}">
                    <div class="accounting-modal-header">
                        <h3>${this.escapeHtml(title)}</h3>
                        <button class="accounting-modal-close" data-action="close-modal">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="accounting-modal-body">
                        ${content}
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Initialize English date pickers after modal is appended to DOM
            setTimeout(() => {
                this.initializeEnglishDatePickers(modal);
                // Also try global function as fallback
                if (typeof window.initializeEnglishDatePickers === 'function') {
                    window.initializeEnglishDatePickers(modal);
                }
            }, 200);
            
            // Mark creation time to prevent cleanup from removing it
            modal.setAttribute('data-created', Date.now().toString());
            // Add data attribute to mark as visible for CSS
            modal.setAttribute('data-modal-visible', 'true');
            
            // Explicitly mark modal as visible
            // Remove hidden class and add visible classes - CSS will handle all styling
            modal.classList.remove('accounting-modal-hidden');
            modal.setAttribute('data-modal-visible', 'true');
            
            this.activeModal = modal;
            const self = this;
            
            // Ensure body-no-scroll is set
            document.body.classList.add('body-no-scroll');
            // Force a reflow to ensure styles are applied
            void modal.offsetHeight;
            
            // Smooth opening animation
            requestAnimationFrame(() => {
                modal.classList.add('accounting-modal-visible');
                modal.classList.add('show-modal');
                
                // Animate modal content
                const modalContent = modal.querySelector('.accounting-modal-content');
                if (modalContent) {
                    modalContent.classList.add('modal-scale-in');
                    
                    requestAnimationFrame(() => {
                        modalContent.classList.add('active');
                    });
                }
            });
            
            // Close on overlay click with confirmation (but not when clicking modal content)
            const overlay = modal.querySelector('.accounting-modal-overlay');
            if (overlay) {
                overlay.addEventListener('click', async (e) => {
                    // Only close if clicking directly on overlay, not modal content
                    if (e.target === overlay) {
                        e.stopPropagation(); // Prevent event from bubbling to modal
                        await this.closeModalWithConfirmation(modal);
                    }
                });
            }
            
            // Also handle clicks on modal backdrop (but not on modal content)
            modal.addEventListener('click', async (e) => {
                // Only close if clicking directly on modal backdrop, not modal content
                if (e.target === modal && !e.target.closest('.accounting-modal-content')) {
                    e.stopPropagation();
                    await this.closeModalWithConfirmation(modal);
                }
            });
            
            // Close on ESC key with confirmation
            const escHandler = async (e) => {
                if (e.key === 'Escape') {
                    const visibleModal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)[data-modal-visible="true"]') ||
                                        document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
                    if (visibleModal) {
                        await this.closeModalWithConfirmation(visibleModal);
                    document.removeEventListener('keydown', escHandler);
                    }
                }
            };
            document.addEventListener('keydown', escHandler);
            
            // Setup close button (X) click handler
            const closeBtn = modal.querySelector('.accounting-modal-close[data-action="close-modal"]');
            if (closeBtn) {
                closeBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    await this.closeModalWithConfirmation(modal);
                });
            }
            
            
            // Setup Cancel button handlers - handle ALL buttons with data-action="close-modal" directly
            setTimeout(() => {
                const closeButtons = modal.querySelectorAll('button[data-action="close-modal"]');
                closeButtons.forEach(btn => {
                    if (!btn.hasAttribute('data-close-handler-attached')) {
                        btn.setAttribute('data-close-handler-attached', 'true');
                        btn.addEventListener('click', async (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            await this.closeModalWithConfirmation(modal);
                        });
                    }
                });
                
                // Also handle Cancel buttons that don't have data-action="close-modal"
                const cancelButtons = modal.querySelectorAll('button.btn-secondary:not([type="submit"]):not([data-action="close-modal"])');
                cancelButtons.forEach(btn => {
                    const btnText = btn.textContent.trim().toLowerCase();
                    // Only attach handler to buttons that say "Cancel" or "Close"
                    if ((btnText === 'cancel' || btnText === 'close') && !btn.hasAttribute('data-close-handler-attached')) {
                        btn.setAttribute('data-close-handler-attached', 'true');
                        btn.addEventListener('click', async (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            await this.closeModalWithConfirmation(modal);
                        });
                    }
                });
            }, 100);
            // Setup entity type change listener for entity transaction form
            setTimeout(() => {
                const entityTypeSelect = modal.querySelector('#entityTypeSelect');
                const entitySelect = modal.querySelector('#entitySelect');
                
                if (entityTypeSelect && entitySelect) {
                    // Remove existing listener if any by cloning
                    const newEntityTypeSelect = entityTypeSelect.cloneNode(true);
                    entityTypeSelect.parentNode.replaceChild(newEntityTypeSelect, entityTypeSelect);
                    
                    newEntityTypeSelect.addEventListener('change', async function() {
                        const entityType = this.value ? this.value.toLowerCase() : '';
                        const selectEl = modal.querySelector('#entitySelect');
                        if (selectEl && entityType) {
                            await self.loadEntitiesForSelect(entityType, selectEl);
                        } else if (selectEl) {
                            selectEl.innerHTML = '<option value="">Select Entity</option>';
                        }
                    });
                    
                    // If entity type is already set (edit mode), trigger change to load entities
                    if (entityTypeSelect.value) {
                        entityTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
                
                // Setup entity type change listener for journal entry form
                const journalEntityTypeSelect = modal.querySelector('#journalEntityTypeSelect');
                const journalEntitySelect = modal.querySelector('#journalEntitySelect');
                
                if (journalEntityTypeSelect && journalEntitySelect) {
                    // Remove existing listener if any by cloning
                    const newJournalEntityTypeSelect = journalEntityTypeSelect.cloneNode(true);
                    journalEntityTypeSelect.parentNode.replaceChild(newJournalEntityTypeSelect, journalEntityTypeSelect);
                    
                    newJournalEntityTypeSelect.addEventListener('change', async function() {
                        const entityType = this.value || 'all';
                        // Clear entity selection when type changes
                        if (journalEntitySelect) {
                            journalEntitySelect.value = '';
                        }
                        // Load entities filtered by selected type
                        if (window.accountingModal && typeof window.accountingModal.loadJournalEntities === 'function') {
                            await window.accountingModal.loadJournalEntities(entityType);
                        } else if (journalEntitySelect) {
                            await self.loadEntitiesForSelect(entityType, journalEntitySelect);
                        }
                    });
                }
            }, 50);
            // Setup form submit handlers and other dynamic handlers
            setTimeout(() => {
                // Setup journal entry form submit handler (only if form exists in this modal)
                const journalEntryForm = modal.querySelector('#journalEntryForm');
                if (journalEntryForm && !journalEntryForm.hasAttribute('data-handler-attached')) {
                    // Mark as having handler attached to prevent duplicates
                    journalEntryForm.setAttribute('data-handler-attached', 'true');
                    
                    // Setup real-time balance calculation for multiple lines
                    const updateBalance = () => {
                        // Calculate total debit from all debit lines
                        const debitInputs = journalEntryForm.querySelectorAll('.debit-amount');
                        let totalDebit = 0;
                        debitInputs.forEach(input => {
                            const value = parseFloat(input.value || 0);
                            if (!isNaN(value) && value > 0) {
                                totalDebit += value;
                            }
                        });
                        
                        // Calculate total credit from all credit lines
                        const creditInputs = journalEntryForm.querySelectorAll('.credit-amount');
                        let totalCredit = 0;
                        creditInputs.forEach(input => {
                            const value = parseFloat(input.value || 0);
                            if (!isNaN(value) && value > 0) {
                                totalCredit += value;
                            }
                        });
                        
                        const totalDebitEl = document.getElementById('journalTotalDebit');
                        const totalCreditEl = document.getElementById('journalTotalCredit');
                        const balanceAmountEl = document.getElementById('journalBalanceAmount');
                        const balanceIndicator = document.getElementById('journalBalanceIndicator');
                        const balanceDifference = document.getElementById('journalBalanceDifference');
                        const submitBtn = journalEntryForm.querySelector('#journalSubmitBtn');
                        const balanceFooter = document.getElementById('journalBalanceFooter');
                        
                        if (totalDebitEl) totalDebitEl.textContent = totalDebit.toFixed(2);
                        if (totalCreditEl) totalCreditEl.textContent = totalCredit.toFixed(2);
                        if (balanceAmountEl) balanceAmountEl.textContent = Math.abs(totalDebit - totalCredit).toFixed(2);
                        
                        const difference = Math.abs(totalDebit - totalCredit);
                        const isBalanced = difference < 0.01 && totalDebit > 0 && totalCredit > 0;
                        
                        if (balanceDifference) {
                            balanceDifference.textContent = difference.toFixed(2);
                        }
                        
                        if (balanceIndicator && balanceFooter) {
                            if (isBalanced) {
                                balanceIndicator.className = 'balance-indicator balanced';
                                balanceIndicator.innerHTML = '<span class="icon">✓</span><span class="balance-text">BALANCED</span>';
                                balanceFooter.className = 'balance-validation-footer sticky-footer balanced';
                                if (submitBtn) submitBtn.disabled = false;
                            } else {
                                balanceIndicator.className = 'balance-indicator unbalanced';
                                balanceIndicator.innerHTML = '<span class="icon">⚠</span><span class="balance-text">UNBALANCED: <span id="journalBalanceDifference">' + difference.toFixed(2) + '</span></span>';
                                balanceFooter.className = 'balance-validation-footer sticky-footer unbalanced';
                                if (submitBtn) submitBtn.disabled = true;
                            }
                        }
                    };
                    
                    // Add event listeners to all amount inputs (delegation for dynamic lines)
                    journalEntryForm.addEventListener('input', (e) => {
                        if (e.target.classList.contains('debit-amount') || e.target.classList.contains('credit-amount')) {
                            updateBalance();
                        }
                    });
                    
                    journalEntryForm.addEventListener('change', (e) => {
                        if (e.target.classList.contains('debit-amount') || e.target.classList.contains('credit-amount')) {
                            updateBalance();
                        }
                    });
                    
                    // Setup add line buttons - use event delegation for dynamically added buttons
                    journalEntryForm.addEventListener('click', async (e) => {
                        // Check if clicked on button or icon inside button
                        const addDebitBtn = e.target.closest('[data-action="add-debit-line"]') || 
                                           (e.target.closest('.btn-add-line') && e.target.closest('.btn-add-line').dataset.side === 'debit');
                        const addCreditBtn = e.target.closest('[data-action="add-credit-line"]') || 
                                            (e.target.closest('.btn-add-line') && e.target.closest('.btn-add-line').dataset.side === 'credit');
                        const removeBtn = e.target.closest('[data-action="remove-line"]') || 
                                         e.target.closest('.btn-remove-line');
                        
                        if (addDebitBtn) {
                            e.preventDefault();
                            e.stopPropagation();
                            await this.addJournalEntryLine('debit');
                            updateBalance();
                            return;
                        }
                        if (addCreditBtn) {
                            e.preventDefault();
                            e.stopPropagation();
                            await this.addJournalEntryLine('credit');
                            updateBalance();
                            return;
                        }
                        if (removeBtn) {
                            e.preventDefault();
                            e.stopPropagation();
                            const row = e.target.closest('.ledger-line-row');
                            if (row) {
                                // Don't remove if it's the only row
                                const tbody = row.closest('tbody');
                                const allRows = tbody ? tbody.querySelectorAll('.ledger-line-row') : [];
                                if (allRows.length > 1) {
                                    row.remove();
                                    // Update remove button visibility after removal
                                    const remainingRows = tbody ? tbody.querySelectorAll('.ledger-line-row') : [];
                                    remainingRows.forEach((r) => {
                                        const removeButton = r.querySelector('.btn-remove-line');
                                        if (removeButton) {
                                            removeButton.style.display = remainingRows.length > 1 ? 'inline-flex' : 'none';
                                        }
                                    });
                                    updateBalance();
                                } else {
                                    this.showToast('At least one line is required', 'warning');
                                }
                            }
                            return;
                        }
                    });
                    
                    // Initial balance calculation
                    setTimeout(updateBalance, 100);
                    
                    // Hide remove buttons on initial rows (only one row per section)
                    const debitRows = journalEntryForm.querySelectorAll('#journalDebitLinesBody .ledger-line-row');
                    const creditRows = journalEntryForm.querySelectorAll('#journalCreditLinesBody .ledger-line-row');
                    
                    if (debitRows.length <= 1) {
                        debitRows.forEach(row => {
                            const removeBtn = row.querySelector('.btn-remove-line');
                            if (removeBtn) removeBtn.style.display = 'none';
                        });
                    }
                    
                    if (creditRows.length <= 1) {
                        creditRows.forEach(row => {
                            const removeBtn = row.querySelector('.btn-remove-line');
                            if (removeBtn) removeBtn.style.display = 'none';
                        });
                    }
                    
                    // Setup Save Draft button handler
                    const saveDraftBtn = document.getElementById('journalSaveDraftBtn');
                    if (saveDraftBtn) {
                        saveDraftBtn.addEventListener('click', async (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            // Save as draft (status will be Draft, which is default)
                            const entryId = journalEntryForm.getAttribute('data-entry-id');
                            const id = entryId && entryId !== 'null' ? parseInt(entryId) : null;
                            
                            // Disable button to prevent double submission
                            saveDraftBtn.disabled = true;
                            saveDraftBtn.textContent = 'Saving...';
                            
                            try {
                                const result = await this.saveJournalEntry(id);
                                if (result === true) {
                                    this.showToast('Draft saved successfully!', 'success');
                                    // Close modal after save
                                    setTimeout(() => {
                                        const modal = journalEntryForm.closest('.accounting-modal');
                                        if (modal) {
                                            this.closeModal(modal.id, false);
                                        }
                                    }, 500);
                                }
                            } catch (error) {
                                console.error('Error saving draft:', error);
                            } finally {
                                if (saveDraftBtn && saveDraftBtn.isConnected) {
                                    saveDraftBtn.disabled = false;
                                    saveDraftBtn.textContent = 'Save Draft';
                                }
                            }
                        });
                    }
                    
                    journalEntryForm.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        const entryId = journalEntryForm.getAttribute('data-entry-id');
                        const id = entryId && entryId !== 'null' ? parseInt(entryId) : null;
                        
                        // Disable submit button to prevent double submission
                        const submitBtn = journalEntryForm.querySelector('button[type="submit"]');
                        const originalBtnText = submitBtn ? submitBtn.textContent : '';
                        if (submitBtn) {
                            submitBtn.disabled = true;
                            const isEdit = id !== null;
                            submitBtn.textContent = isEdit ? 'Updating...' : 'Creating...';
                        }
                        
                        let saveSucceeded = false;
                        try {
                            const result = await this.saveJournalEntry(id);
                            // If saveJournalEntry returns false, validation failed
                            if (result === true) {
                                // Save succeeded or is in progress (modal will close, so don't re-enable button)
                                saveSucceeded = true;
                            }
                        } catch (error) {
                            // Error is already handled in saveJournalEntry
                            console.error('Error in journal entry form submit:', error);
                        }
                        
                        // Only re-enable button if validation failed (saveJournalEntry returned false)
                        // If save succeeded, modal will close so button state doesn't matter
                        if (!saveSucceeded && submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalBtnText;
                        }
                    });
                }
                
                // Setup invoice form submit handler
                const invoiceForm = modal.querySelector('#invoiceForm');
                if (invoiceForm) {
                    // Remove existing handler if any by cloning
                    const newForm = invoiceForm.cloneNode(true);
                    invoiceForm.parentNode.replaceChild(newForm, invoiceForm);
                    newForm.setAttribute('data-handler-attached', 'true');
                    
                    // Also setup button click handler as backup
                    const submitButton = newForm.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.addEventListener('click', async (e) => {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            // Prevent double submission
                            if (submitButton.disabled) {
                                return;
                            }
                            
                            // Disable button to prevent double submission
                            submitButton.disabled = true;
                            const originalText = submitButton.textContent;
                            submitButton.textContent = 'Saving...';
                            
                            try {
                                // Validate required fields
                                const requiredFields = newForm.querySelectorAll('[required]');
                                let isValid = true;
                                const missingFields = [];
                                requiredFields.forEach(field => {
                                    const value = field.value ? field.value.trim() : '';
                                    if (!value || value === '') {
                                        isValid = false;
                                        field.style.borderColor = '#ef4444';
                                        missingFields.push(field.name || field.id || field.label || 'Unknown field');
                                    } else {
                                        field.style.borderColor = '';
                                    }
                                });
                                
                                if (!isValid) {
                                    this.showToast(`Please fill in all required fields. Missing: ${missingFields.join(', ')}`, 'error');
                                    return;
                                }
                                
                                const invoiceId = newForm.getAttribute('data-invoice-id');
                                const id = invoiceId && invoiceId !== 'null' ? parseInt(invoiceId) : null;
                                await this.saveInvoice(id);
                            } finally {
                                // Re-enable button
                                submitButton.disabled = false;
                                submitButton.textContent = originalText;
                            }
                        });
                    }
                    
                    // Also setup form submit handler
                    newForm.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        // Validate required fields
                        const requiredFields = newForm.querySelectorAll('[required]');
                        let isValid = true;
                        requiredFields.forEach(field => {
                            if (!field.value || field.value.trim() === '') {
                                isValid = false;
                                field.style.borderColor = '#ef4444';
                            } else {
                                field.style.borderColor = '';
                            }
                        });
                        
                        if (!isValid) {
                            this.showToast('Please fill in all required fields', 'error');
                            return;
                        }
                        
                        const invoiceId = newForm.getAttribute('data-invoice-id');
                        const id = invoiceId && invoiceId !== 'null' ? parseInt(invoiceId) : null;
                        await this.saveInvoice(id);
                    });
                }
                
                // Setup bill form submit handler (only if form exists in this modal)
                const billForm = modal.querySelector('#billForm');
                if (billForm) {
                    billForm.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const billId = billForm.getAttribute('data-bill-id');
                        const id = billId && billId !== 'null' ? parseInt(billId) : null;
                        await this.saveBill(id);
                    });
                }
                
                // Setup bank account form submit handler (only if form exists in this modal)
                // Skip if handler already attached (openBankAccountForm handles this)
                const bankAccountForm = modal.querySelector('#bankAccountForm');
                if (bankAccountForm && !bankAccountForm.hasAttribute('data-handler-attached')) {
                    // Remove existing listeners by cloning
                    const newForm = bankAccountForm.cloneNode(true);
                    bankAccountForm.parentNode.replaceChild(newForm, bankAccountForm);
                    newForm.setAttribute('data-handler-attached', 'true');
                    
                    newForm.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        
                        // Additional guard check - double protection
                        if (this._savingBankAccount) {
                            // Form submission blocked: save already in progress
                            return;
                        }
                        
                        // Disable submit button to prevent double submission
                        const submitBtn = newForm.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            submitBtn.disabled = true;
                            submitBtn.textContent = 'Saving...';
                        }
                        
                        const bankId = newForm.getAttribute('data-bank-id');
                        const id = bankId && bankId !== 'null' ? parseInt(bankId) : null;
                        
                        try {
                            await this.saveBankAccount(id);
                        } finally {
                            // Re-enable submit button only if form still exists
                            if (newForm && newForm.isConnected) {
                                const currentSubmitBtn = newForm.querySelector('button[type="submit"]');
                                if (currentSubmitBtn) {
                                    currentSubmitBtn.disabled = false;
                                    currentSubmitBtn.textContent = id ? 'Update Bank Account' : 'Add Bank Account';
                                }
                            }
                        }
                    });
                }
                
                // Setup entity transaction form submit handler (only if form exists in this modal)
                const entityTransactionForm = modal.querySelector('#entityTransactionForm');
                if (entityTransactionForm) {
                    // Remove existing listener if any by cloning
                    const newForm = entityTransactionForm.cloneNode(true);
                    entityTransactionForm.parentNode.replaceChild(newForm, entityTransactionForm);
                    
                    newForm.addEventListener('submit', async (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const transactionId = newForm.getAttribute('data-transaction-id');
                        const id = transactionId && transactionId !== 'null' ? parseInt(transactionId) : null;
                        await this.saveEntityTransaction(id);
                    });
                    
                    // Protect debit/credit fields from being cleared when Type field changes
                    const typeField = newForm.querySelector('#entityTransactionType');
                    if (typeField) {
                        typeField.addEventListener('change', function() {
                            // Preserve debit and credit values when type changes
                            const debitField = newForm.querySelector('#entityTransactionDebit');
                            const creditField = newForm.querySelector('#entityTransactionCredit');
                            
                            if (debitField && debitField.value) {
                                const debitValue = debitField.value;
                                // Use setTimeout to ensure value is preserved after any other handlers
                                setTimeout(() => {
                                    if (debitField.value !== debitValue) {
                                        debitField.value = debitValue;
                                    }
                                }, 0);
                            }
                            
                            if (creditField && creditField.value) {
                                const creditValue = creditField.value;
                                setTimeout(() => {
                                    if (creditField.value !== creditValue) {
                                        creditField.value = creditValue;
                                    }
                                }, 0);
                            }
                        });
                    }
                }
                
                const currencySelects = modal.querySelectorAll('select[name="currency"]');
                currencySelects.forEach(select => {
                    const defaultCurrency = this.getDefaultCurrencySync();
                    if (!select.value || select.value === '') {
                        select.value = defaultCurrency;
                    }
                    if (!select.hasAttribute('data-currency-listener')) {
                        select.setAttribute('data-currency-listener', 'true');
                        select.addEventListener('change', function() {
                            // Update both last currency (for form memory) and default currency (for system-wide use)
                            localStorage.setItem('accounting_last_currency', this.value);
                            localStorage.setItem('accounting_default_currency', this.value);
                        });
                    }
                });
            }, 200);
        },

        async closeModalWithConfirmation(modalElement = null) {
            if (this._closingModal) return;
            this._closingModal = true;
            try {
                let modalToClose = modalElement || this.activeModal;
                if (!modalToClose) {
                    modalToClose = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)[data-modal-visible="true"]') || document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
                }
                if (!modalToClose) { this._closingModal = false; return; }
                const forms = modalToClose.querySelectorAll('form');
                let hasUnsavedChanges = false;
                try { for (const form of forms) { if (this.hasFormChanges(form)) { hasUnsavedChanges = true; break; } } } catch (e) {}
                if (hasUnsavedChanges) {
                    try {
                        const confirmed = await this.showConfirmDialog('Unsaved Changes', 'You have unsaved changes. Are you sure you want to close without saving?', 'Discard Changes', 'Cancel', 'warning');
                        if (!confirmed) { this._closingModal = false; return; }
                    } catch (e) {}
                }
                const modalId = modalToClose.id || modalToClose.getAttribute('id') || null;
                try {
                    modalToClose.classList.remove('accounting-modal-visible', 'show-modal');
                    modalToClose.classList.add('accounting-modal-hidden');
                    modalToClose.removeAttribute('data-modal-visible');
                } catch (e) {}
                if (modalToClose.parentNode) { try { modalToClose.remove(); } catch (e) {} }
                if (this.activeModal === modalToClose) this.activeModal = null;
                document.body.classList.remove('body-no-scroll');
                const allOverlays = document.querySelectorAll('.accounting-modal-overlay');
                allOverlays.forEach(overlay => {
                    const parentModal = overlay.closest('.accounting-modal');
                    if (!parentModal || parentModal.classList.contains('accounting-modal-hidden')) overlay.remove();
                });
                [document.querySelector('.accounting-container'), document.querySelector('.accounting-main-content'), document.querySelector('.accounting-layout'), document.querySelector('#dashboardTab')].forEach(el => { if (el) { el.classList.add('modal-cleanup-reset'); el.classList.remove('hidden'); } });
                const dashboardTabBtn = document.querySelector('.tab-btn[data-tab="dashboard"]');
                const dashboardTabContent = document.querySelector('#dashboardTab');
                if (dashboardTabBtn && dashboardTabContent) {
                    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                    dashboardTabBtn.classList.add('active');
                    dashboardTabContent.classList.add('active');
                    dashboardTabContent.classList.add('modal-cleanup-reset');
                }
                try { this.closeModal(modalId, false); } catch (e) {}
                setTimeout(() => {
                    const activeTab = document.querySelector('.tab-btn.active');
                    if (activeTab && activeTab.dataset.tab === 'dashboard') {
                        try { this.loadDashboard(); this.loadFinancialOverview(); } catch (e) {}
                    }
                }, 200);
            } catch (e) {} finally { this._closingModal = false; }
        },

        closeModal(modalId = null, reloadDashboard = true) {
            
            let modalToClose = null;
            
            // If specific modal ID provided, close that one
            if (modalId) {
                modalToClose = document.getElementById(modalId);
            } else if (this.activeModal) {
                modalToClose = this.activeModal;
                } else {
                // Try to find any visible modal
                modalToClose = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)[data-modal-visible="true"]') ||
                              document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
            }
            
            if (modalToClose) {
                
                // Get child elements that also need to be hidden
                const modalOverlay = modalToClose.querySelector('.accounting-modal-overlay');
                const modalContent = modalToClose.querySelector('.accounting-modal-content');
                const modalBody = modalToClose.querySelector('.accounting-modal-body');
                
                // Remove visible classes and add hidden class - CSS will handle hiding
                modalToClose.classList.remove('accounting-modal-visible', 'show-modal');
                modalToClose.classList.add('accounting-modal-hidden');
                modalToClose.removeAttribute('data-modal-visible');
                
                // Remove the modal from DOM immediately
                if (modalToClose.parentNode) {
                    try {
                        modalToClose.remove();
                    } catch (e) {
                    }
                }
                
                // Clear activeModal if it was this modal
                if (this.activeModal === modalToClose) {
                    this.activeModal = null;
                }
            } else {
            }
            
            // Remove body scroll lock
            document.body.classList.remove('body-no-scroll');
            
            // Remove ALL leftover overlays that might be blocking content
            const allOverlays = document.querySelectorAll('.accounting-modal-overlay');
            allOverlays.forEach(overlay => {
                const parentModal = overlay.closest('.accounting-modal');
                if (!parentModal || parentModal.classList.contains('accounting-modal-hidden')) {
                    overlay.remove();
                }
            });
            
            // Remove any leftover modal elements
            const hiddenModals = document.querySelectorAll('.accounting-modal.accounting-modal-hidden');
            hiddenModals.forEach(modal => {
                setTimeout(() => {
                    if (modal.classList.contains('accounting-modal-hidden') && modal.parentNode) {
                        modal.remove();
                    }
                }, 100);
            });
            
            // Ensure main content containers are visible
            const mainContent = document.querySelector('.accounting-container');
            const mainContentArea = document.querySelector('.accounting-main-content');
            const layout = document.querySelector('.accounting-layout');
            const dashboardTab = document.querySelector('#dashboardTab');
            
            [mainContent, mainContentArea, layout, dashboardTab].forEach(el => {
                if (el) {
                    el.classList.add('modal-cleanup-reset');
                    el.classList.remove('hidden');
                }
            });
            
            // Force a reflow to ensure visibility
            if (mainContent) {
                void mainContent.offsetHeight;
            }
            
            // Ensure dashboard tab is active and visible
            const dashboardTabBtn = document.querySelector('.tab-btn[data-tab="dashboard"]');
            const dashboardTabContent = document.querySelector('#dashboardTab');
            
            if (dashboardTabBtn && dashboardTabContent) {
                // Make sure dashboard tab is active
                document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                
                dashboardTabBtn.classList.add('active');
                dashboardTabContent.classList.add('active');
                
                // Ensure it's visible
                dashboardTabContent.classList.add('modal-cleanup-reset');
            }
            
            // Reload dashboard content if we're on the dashboard tab (only if requested)
            if (reloadDashboard) {
                setTimeout(() => {
                    const activeTab = document.querySelector('.tab-btn.active');
                    if (activeTab && activeTab.dataset.tab === 'dashboard') {
                        try {
                            this.loadDashboard();
                            this.loadFinancialOverview();
                        } catch (e) {
                            console.error('Error reloading dashboard:', e);
                        }
                    }
                }, 200);
            }
        },

        getJournalEntryModalContent(entryId = null) {
            const isEdit = !!entryId;
            const today = this.formatDateForInput(new Date().toISOString().split('T')[0]);
            return `
                <!-- Disable native HTML validation so our JS handler always runs (prevents "button flashes" with no action) -->
                <form id="journalEntryForm" data-entry-id="${entryId || 'null'}" novalidate>
                    <!-- HEADER FIELDS -->
                    <div class="journal-entry-header-fields">
                        <div class="accounting-modal-form-row">
                            <div class="accounting-modal-form-group">
                                <label>Journal Date *</label>
                                <input type="text" name="entry_date" id="journalEntryDate" class="date-input" required value="${today}" placeholder="MM/DD/YYYY">
                            </div>
                            <div class="accounting-modal-form-group">
                                <label>Branch *</label>
                                <select name="branch_id" id="journalBranchSelect" required>
                                    <!-- Default branch must have a real value (required field) -->
                                    <option value="1" selected>Main Branch</option>
                                </select>
                            </div>
                        </div>
                        <div class="accounting-modal-form-group full-width">
                            <label>Customers</label>
                            <div id="journalCustomersContainer">
                                <div class="customer-input-row" data-customer-index="0">
                                    <input type="text" name="customers[]" class="customer-name-input" placeholder="Enter customer name">
                                    <button type="button" class="btn-add-customer" data-action="add-customer" title="Add Customer">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="accounting-modal-form-group full-width">
                            <label>Description *</label>
                            <textarea name="description" required placeholder="Description"></textarea>
                        </div>
                    </div>
                    
                    <!-- DEBIT SECTION -->
                    <div class="ledger-section ledger-debit">
                        <div class="section-header">
                            <h3>DEBIT</h3>
                        </div>
                        <div class="ledger-entries-table">
                            <table class="ledger-entries-table-inner">
                                <thead>
                                    <tr>
                                        <th class="account-col">Account Name</th>
                                        <th class="cost-center-col">Cost Center</th>
                                        <th class="description-col">Description</th>
                                        <th class="vat-col">VAT Report</th>
                                        <th class="amount-col">Amount</th>
                                        <th class="actions-col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="journalDebitLinesBody">
                                    <tr class="ledger-line-row" data-line-index="0">
                                        <td class="account-cell">
                                            <select name="debit_lines[0][account_id]" class="account-select" required>
                                                <option value="">Select</option>
                                            </select>
                                        </td>
                                        <td class="cost-center-cell">
                                            <select name="debit_lines[0][cost_center_id]" class="cost-center-select">
                                                <option value="">- Main Center</option>
                                            </select>
                                        </td>
                                        <td class="description-cell">
                                            <input type="text" name="debit_lines[0][description]" class="line-description" placeholder="Description">
                                        </td>
                                        <td class="vat-cell">
                                            <input type="checkbox" name="debit_lines[0][vat_report]" class="vat-checkbox">
                                        </td>
                                        <td class="amount-cell">
                                            <input type="number" name="debit_lines[0][amount]" class="line-amount debit-amount" step="0.01" min="0" placeholder="0.00">
                                        </td>
                                        <td class="actions-cell">
                                            <button type="button" class="btn-add-line" data-side="debit" data-action="add-debit-line" title="Add Line">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <button type="button" class="btn-remove-line" data-action="remove-line" title="Remove Line" style="display: none;">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="section-total">
                                <div class="total-label">Total Debit:</div>
                                <div class="total-value" id="journalTotalDebit">0.00</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- CREDIT SECTION -->
                    <div class="ledger-section ledger-credit">
                        <div class="section-header">
                            <h3>CREDIT</h3>
                        </div>
                        <div class="ledger-entries-table">
                            <table class="ledger-entries-table-inner">
                                <thead>
                                    <tr>
                                        <th class="account-col">Account Name</th>
                                        <th class="cost-center-col">Cost Center</th>
                                        <th class="description-col">Description</th>
                                        <th class="vat-col">VAT Report</th>
                                        <th class="amount-col">Amount</th>
                                        <th class="actions-col">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="journalCreditLinesBody">
                                    <tr class="ledger-line-row" data-line-index="0">
                                        <td class="account-cell">
                                            <select name="credit_lines[0][account_id]" class="account-select" required>
                                                <option value="">Select</option>
                                            </select>
                                        </td>
                                        <td class="cost-center-cell">
                                            <select name="credit_lines[0][cost_center_id]" class="cost-center-select">
                                                <option value="">- Main Center</option>
                                            </select>
                                        </td>
                                        <td class="description-cell">
                                            <input type="text" name="credit_lines[0][description]" class="line-description" placeholder="Description">
                                        </td>
                                        <td class="vat-cell">
                                            <input type="checkbox" name="credit_lines[0][vat_report]" class="vat-checkbox">
                                        </td>
                                        <td class="amount-cell">
                                            <input type="number" name="credit_lines[0][amount]" class="line-amount credit-amount" step="0.01" min="0" placeholder="0.00">
                                        </td>
                                        <td class="actions-cell">
                                            <button type="button" class="btn-add-line" data-side="credit" data-action="add-credit-line" title="Add Line">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                            <button type="button" class="btn-remove-line" data-action="remove-line" title="Remove Line" style="display: none;">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div class="section-total">
                                <div class="total-label">Total Credit:</div>
                                <div class="total-value" id="journalTotalCredit">0.00</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- BALANCE VALIDATION FOOTER -->
                    <div class="balance-validation-footer sticky-footer unbalanced" id="journalBalanceFooter">
                        <div class="balance-totals">
                            <div class="balance-item">
                                <span class="balance-label">Amount:</span>
                                <span class="balance-value" id="journalBalanceAmount">0.00</span>
                            </div>
                        </div>
                        <div class="balance-indicator unbalanced" id="journalBalanceIndicator">
                            <span class="icon">⚠</span>
                            <span class="balance-text">UNBALANCED: <span id="journalBalanceDifference">0.00</span></span>
                        </div>
                    </div>
                    
                    <div class="accounting-modal-actions">
                        <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                        <button type="button" class="btn btn-secondary" id="journalSaveDraftBtn" data-action="save-draft" style="display: ${isEdit ? 'none' : 'inline-block'};">Save Draft</button>
                        <button type="submit" class="btn btn-primary" id="journalSubmitBtn" disabled>${isEdit ? 'Update' : 'Create'} Entry</button>
                    </div>
                </form>
            `;
        },

        getInvoiceModalContent(invoiceId = null) {
            const isEdit = !!invoiceId;
            return `
                        <form id="invoiceForm" data-invoice-id="${invoiceId || 'null'}">
                    <div class="accounting-modal-form-row">
                        <div class="accounting-modal-form-group">
                            <label>Invoice Number</label>
                            <input type="text" name="invoice_number" placeholder="Auto-generated if empty">
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Invoice Date *</label>
                            <input type="text" name="invoice_date" class="date-input" required value="${this.formatDateForInput(new Date().toISOString())}" placeholder="MM/DD/YYYY">
                        </div>
                    </div>
                    <div class="accounting-modal-form-row">
                        <div class="accounting-modal-form-group full-width">
                            <label>Customers</label>
                            <div id="invoiceCustomersContainer">
                                <div class="customer-input-row" data-customer-index="0">
                                    <input type="text" name="customers[]" class="customer-name-input" placeholder="Enter customer name">
                                    <button type="button" class="btn-add-customer" data-action="add-customer" title="Add Customer">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accounting-modal-form-row">
                        <div class="accounting-modal-form-group">
                            <label>Due Date *</label>
                            <input type="text" name="due_date" class="date-input" required placeholder="MM/DD/YYYY">
                        </div>
                    </div>
                    <div class="accounting-modal-form-row">
                        <div class="accounting-modal-form-group">
                            <label>Currency *</label>
                            <select name="currency" id="invoiceCurrencySelect" required>
                                <option value="">Loading currencies...</option>
                            </select>
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Total Amount *</label>
                            <input type="number" name="total_amount" step="0.01" min="0" required placeholder="0.00">
                        </div>
                    </div>
                    <div class="accounting-modal-form-row">
                        <div class="accounting-modal-form-group">
                            <label>Debit Account *</label>
                            <select name="debit_account_id" id="invoiceDebitAccountSelect" required>
                                <option value="">Loading accounts...</option>
                            </select>
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Credit Account *</label>
                            <select name="credit_account_id" id="invoiceCreditAccountSelect" required>
                                <option value="">Loading accounts...</option>
                            </select>
                        </div>
                    </div>
                    <div class="accounting-modal-form-row">
                        <div class="accounting-modal-form-group">
                            <label>Payment Voucher</label>
                            <input type="text" name="payment_voucher" id="invoicePaymentVoucher" readonly placeholder="Auto-generated">
                        </div>
                        <div class="accounting-modal-form-group">
                            <label>Tax</label>
                            <div class="tax-checkbox-row">
                                <input type="checkbox" name="tax_included" id="invoiceTaxCheckbox" value="1">
                                <span id="invoiceTaxLabel" class="tax-label">Tax not included</span>
                            </div>
                        </div>
                    </div>
                    <div class="accounting-modal-form-group full-width">
                        <label>Description</label>
                        <textarea name="description" placeholder="Invoice description"></textarea>
                    </div>
                    <div class="accounting-modal-actions">
                        <button type="button" class="btn btn-secondary" id="invoiceCancelBtn" data-action="close-modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="invoiceSaveBtn">${isEdit ? 'Update' : 'Create'} Invoice</button>
                    </div>
                </form>
            `;
        },

        getBillModalContent(billId = null) {
            const isEdit = !!billId;
            return `
                <form id="billForm" data-bill-id="${billId || 'null'}">
                    <div class="accounting-modal-form-group">
                        <label>Bill Number</label>
                        <input type="text" name="bill_number" placeholder="Auto-generated if empty">
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Bill Date *</label>
                        <input type="text" name="bill_date" class="date-input" required value="${this.formatDateForInput(new Date().toISOString())}" placeholder="MM/DD/YYYY">
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Vendor *</label>
                        <select name="vendor_id" id="billVendorSelect" required>
                            <option value="">Loading vendors...</option>
                        </select>
                        <small class="manage-link-wrapper">
                            <a href="#" data-action="manage-vendors" class="manage-link">Manage Vendors</a>
                        </small>
                    </div>
                    <div class="accounting-modal-form-group full-width">
                        <label>Customers</label>
                        <div id="billCustomersContainer">
                            <div class="customer-input-row" data-customer-index="0">
                                <input type="text" name="customers[]" class="customer-name-input" placeholder="Enter customer name">
                                <button type="button" class="btn-add-customer" data-action="add-customer" title="Add Customer">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Due Date *</label>
                        <input type="text" name="due_date" class="date-input" required placeholder="MM/DD/YYYY">
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Currency *</label>
                        <select name="currency" required>
                            ${this.getCurrencyOptionsHTML()}
                        </select>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Total Amount *</label>
                        <input type="number" name="total_amount" step="0.01" min="0" required placeholder="0.00">
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Bill description"></textarea>
                    </div>
                    <div class="accounting-modal-actions">
                        <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Bill</button>
                    </div>
                </form>
            `;
        },

        getBankAccountModalContent(bankData = null, bankIdParam = null) {
            const isEdit = bankData !== null || bankIdParam !== null;
            // Use bankIdParam if provided, otherwise try to get from bankData
            const bankId = bankIdParam || (bankData ? bankData.id : null);
            return `
                <form id="bankAccountForm" data-bank-id="${bankId || 'null'}">
                    <div class="accounting-modal-form-group">
                        <label>Bank Name *</label>
                        <input type="text" name="bank_name" required value="${bankData ? this.escapeHtml(bankData.bank_name || '') : ''}" placeholder="Enter bank name">
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Account Name *</label>
                        <input type="text" name="account_name" required value="${bankData ? this.escapeHtml(bankData.account_name || '') : ''}" placeholder="Enter account name">
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Account Number *</label>
                        <input type="text" name="account_number" required value="${bankData ? this.escapeHtml(bankData.account_number || '') : ''}" placeholder="Enter account number">
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>Account Type</label>
                        <select name="account_type" class="form-control">
                            <option value="Checking" ${bankData && bankData.account_type === 'Checking' ? 'selected' : 'selected'}>Checking</option>
                            <option value="Savings" ${bankData && bankData.account_type === 'Savings' ? 'selected' : ''}>Savings</option>
                            <option value="Money Market" ${bankData && bankData.account_type === 'Money Market' ? 'selected' : ''}>Money Market</option>
                            <option value="Certificate of Deposit" ${bankData && bankData.account_type === 'Certificate of Deposit' ? 'selected' : ''}>Certificate of Deposit</option>
                        </select>
                    </div>
                    <div class="accounting-modal-form-group">
                        <label>${isEdit ? 'Opening Balance (Read-only)' : 'Initial Balance'}</label>
                        <input type="number" name="initial_balance" step="0.01" value="${bankData ? (bankData.opening_balance || 0) : '0.00'}" placeholder="0.00" ${isEdit ? 'readonly' : ''}>
                        ${isEdit ? '<small class="form-help-text">Opening balance cannot be changed after account creation</small>' : ''}
                    </div>
                    <div class="accounting-modal-actions">
                        <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Add'} Bank Account</button>
                    </div>
                </form>
            `;
        },

        openGeneralLedgerModal() {
            this.modalLedgerCurrentPage = this.modalLedgerCurrentPage || 1;
            this.modalLedgerPerPage = this.modalLedgerPerPage || 5;
            this.modalLedgerSearch = this.modalLedgerSearch || '';
            this.modalLedgerDateFrom = this.modalLedgerDateFrom || '';
            // Don't set default for Date To - let user select manually
            this.modalLedgerDateTo = this.modalLedgerDateTo || '';
            this.modalLedgerAccountId = this.modalLedgerAccountId || '';
            
            // Set default date for Date From only if not set (first day of current month)
            if (!this.modalLedgerDateFrom) {
                const firstDay = new Date();
                firstDay.setDate(1);
                this.modalLedgerDateFrom = this.formatDateForInput(firstDay.toISOString().split('T')[0]);
            }
            
            // Set default date range to last 90 days to show previous data
            if (!this.modalLedgerDateFrom) {
                const ninetyDaysAgo = new Date();
                ninetyDaysAgo.setDate(ninetyDaysAgo.getDate() - 90);
                this.modalLedgerDateFrom = this.formatDateForInput(ninetyDaysAgo.toISOString().split('T')[0]);
            }
            if (!this.modalLedgerDateTo) {
                const today = this.formatDateForInput(new Date().toISOString().split('T')[0]);
                this.modalLedgerDateTo = today;
            }
            
            const content = `
                <div class="accounting-module-modal-content">
                    <div class="module-content">
                        <div class="summary-cards-mini-header">
                            <div class="summary-cards-mini">
                                <div class="summary-mini-card">
                                    <h4>Total Entries</h4>
                                    <p id="modalLedgerTotalEntries">0</p>
                                </div>
                                <div class="summary-mini-card">
                                    <h4>Total Debit</h4>
                                    <p id="modalLedgerTotalDebit">SAR 0.00</p>
                                </div>
                                <div class="summary-mini-card">
                                    <h4>Total Credit</h4>
                                    <p id="modalLedgerTotalCredit">SAR 0.00</p>
                                </div>
                                <div class="summary-mini-card">
                                    <h4>Balance</h4>
                                    <p id="modalLedgerBalance">SAR 0.00</p>
                                </div>
                                <div class="summary-mini-card">
                                    <h4>Posted</h4>
                                    <p id="modalLedgerPosted">0</p>
                                </div>
                                <div class="summary-mini-card">
                                    <h4>Draft</h4>
                                    <p id="modalLedgerDraft">0</p>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>Agents</h4>
                                    <p id="modalLedgerAgentsCount">0</p>
                                    <span id="modalLedgerAgentsAmount" class="entity-amount">SAR 0.00</span>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>Subagents</h4>
                                    <p id="modalLedgerSubagentsCount">0</p>
                                    <span id="modalLedgerSubagentsAmount" class="entity-amount">SAR 0.00</span>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>Workers</h4>
                                    <p id="modalLedgerWorkersCount">0</p>
                                    <span id="modalLedgerWorkersAmount" class="entity-amount">SAR 0.00</span>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>HR</h4>
                                    <p id="modalLedgerHrCount">0</p>
                                    <span id="modalLedgerHrAmount" class="entity-amount">SAR 0.00</span>
                                </div>
                            </div>
                        </div>
                        <div class="filters-and-pagination-container">
                            <div class="filters-bar filters-bar-compact">
                                <div class="filter-group filter-group-compact">
                                    <label>Date From:</label>
                                    <input type="text" id="modalLedgerDateFrom" class="filter-input filter-input-compact date-input" value="${this.modalLedgerDateFrom}" placeholder="MM/DD/YYYY">
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Date To:</label>
                                    <input type="text" id="modalLedgerDateTo" class="filter-input filter-input-compact date-input" value="" placeholder="MM/DD/YYYY">
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Account:</label>
                                    <select id="modalLedgerAccount" class="filter-select filter-select-compact">
                                        <option value="">All Accounts</option>
                                    </select>
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Search:</label>
                                    <input type="text" id="modalLedgerSearch" class="filter-input filter-input-compact" placeholder="Search entries..." value="${this.modalLedgerSearch}">
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Show:</label>
                                    <select id="modalLedgerPerPage" class="filter-select filter-select-compact">
                                        <option value="5" ${this.modalLedgerPerPage === 5 ? 'selected' : ''}>5</option>
                                        <option value="10" ${this.modalLedgerPerPage === 10 ? 'selected' : ''}>10</option>
                                        <option value="25" ${this.modalLedgerPerPage === 25 ? 'selected' : ''}>25</option>
                                        <option value="50" ${this.modalLedgerPerPage === 50 ? 'selected' : ''}>50</option>
                                        <option value="100" ${this.modalLedgerPerPage === 100 ? 'selected' : ''}>100</option>
                                    </select>
                                </div>
                                <button class="btn btn-primary btn-sm" data-action="new-journal-entry" data-permission="add_journal_entry,view_journal_entries">
                                    <i class="fas fa-plus"></i> New Journal
                                </button>
                                <button class="btn btn-secondary btn-sm" data-action="print-ledger" title="Print">
                                    <i class="fas fa-print"></i> Print
                                </button>
                                <button class="btn btn-secondary btn-sm" data-action="export-ledger-csv" title="Export CSV">
                                    <i class="fas fa-file-csv"></i> CSV
                                </button>
                                <button class="btn btn-secondary btn-sm" data-action="copy-ledger" title="Copy">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                                <button class="btn btn-secondary btn-sm" data-action="export-ledger-excel" title="Export Excel">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                            <div class="table-pagination-top" id="modalLedgerPaginationTop">
                                <div class="pagination-info" id="modalLedgerPaginationInfoTop"></div>
                                <div class="pagination-controls">
                                    <button class="btn-pagination btn-pagination-nav" id="modalLedgerFirstTop" data-action="modal-ledger-page" data-page="1" title="First Page">
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>
                                    <button class="btn-pagination btn-pagination-nav" id="modalLedgerPrevTop" data-action="modal-ledger-prev" title="Previous Page">
                                        <i class="fas fa-angle-left"></i>
                                    </button>
                                    <span id="modalLedgerPageNumbersTop" class="pagination-numbers"></span>
                                    <button class="btn-pagination btn-pagination-nav" id="modalLedgerNextTop" data-action="modal-ledger-next" title="Next Page">
                                        <i class="fas fa-angle-right"></i>
                                    </button>
                                    <button class="btn-pagination btn-pagination-nav" id="modalLedgerLastTop" data-action="modal-ledger-page" data-page="1" title="Last Page">
                                        <i class="fas fa-angle-double-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Bulk Actions Bar -->
                        <div class="bulk-actions-bar bulk-actions-bar-hidden" id="bulkActionsLedger">
                            <span class="bulk-selected-count" id="bulkSelectedCountLedger">0 selected</span>
                            <div class="bulk-action-buttons">
                                <button class="btn btn-sm btn-danger" data-action="bulk-delete-ledger">
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                                <button class="btn btn-sm btn-secondary" data-action="bulk-export-ledger">
                                    <i class="fas fa-download"></i> Export Selected
                                </button>
                            </div>
                        </div>
                        <div class="data-table-container modal-table-wrapper modal-table-wrapper-no-scroll" id="modalLedgerTableWrapper">
                            <table class="data-table modal-table-fixed professional-ledger-table" id="modalJournalEntriesTable">
                                <thead>
                                    <tr>
                                        <th class="voucher-number-column">Entry Number</th>
                                        <th class="date-column">Journal Date</th>
                                        <th class="amount-column debit-header">Total Debit</th>
                                        <th class="amount-column credit-header">Total Credit</th>
                                        <th class="account-column">Debit Account</th>
                                        <th class="account-column">Credit Account</th>
                                        <th class="description-column">Description</th>
                                        <th class="actions-column">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="modalJournalEntriesBody">
                                    <tr><td colspan="8" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            this.showModal('General Ledger', content, 'large');
            setTimeout(async () => {
                // Initialize date inputs first - ensure they're properly set and interactive
                const dateFromInput = document.getElementById('modalLedgerDateFrom');
                const dateToInput = document.getElementById('modalLedgerDateTo');
                
                // Set date values explicitly after DOM is ready and ensure they're interactive
                // Default to last 90 days to show previous data
                let initialDateFrom = this.modalLedgerDateFrom;
                let initialDateTo = this.modalLedgerDateTo;
                
                if (dateFromInput) {
                    // If no saved date, default to 90 days ago
                    if (!initialDateFrom) {
                        const ninetyDaysAgo = new Date();
                        ninetyDaysAgo.setDate(ninetyDaysAgo.getDate() - 90);
                        initialDateFrom = this.formatDateForInput(ninetyDaysAgo.toISOString().split('T')[0]);
                        this.modalLedgerDateFrom = initialDateFrom;
                    }
                    dateFromInput.value = initialDateFrom;
                    dateFromInput.removeAttribute('disabled');
                    dateFromInput.removeAttribute('readonly');
                    dateFromInput.classList.add('date-input-enabled');
                    
                    // Add change handler for Date From - auto-reload when both dates are selected
                    dateFromInput.addEventListener('change', (e) => {
                        const newDateFrom = e.target.value;
                        this.modalLedgerDateFrom = newDateFrom;
                        // Auto-reload when both dates are selected
                        const dateToValue = dateToInput ? dateToInput.value : this.modalLedgerDateTo;
                        if (newDateFrom && dateToValue) {
                            this.modalLedgerCurrentPage = 1;
                            this.loadModalJournalEntries();
                        }
                    });
                }
                if (dateToInput) {
                    // Set Date To to today if not set
                    if (!initialDateTo) {
                        const today = this.formatDateForInput(new Date().toISOString().split('T')[0]);
                        initialDateTo = today;
                        this.modalLedgerDateTo = initialDateTo;
                    }
                    dateToInput.value = initialDateTo;
                    dateToInput.removeAttribute('disabled');
                    dateToInput.removeAttribute('readonly');
                    dateToInput.classList.add('date-input-enabled');
                    
                    // Add change handler for Date To - auto-reload when both dates are selected
                    dateToInput.addEventListener('change', (e) => {
                        const newDateTo = e.target.value || '';
                        this.modalLedgerDateTo = newDateTo;
                        // Auto-reload when both dates are selected
                        const dateFromValue = dateFromInput ? dateFromInput.value : this.modalLedgerDateFrom;
                        if (newDateTo && dateFromValue) {
                            this.modalLedgerCurrentPage = 1;
                            this.loadModalJournalEntries();
                        }
                    });
                }
                
                // Load accounts first, then set selected value
                await this.loadAccountsForModalSelect('#modalLedgerAccount');
                const accountSelect = document.getElementById('modalLedgerAccount');
                if (accountSelect) {
                    // Ensure "All Accounts" option exists
                    let allAccountsOption = accountSelect.querySelector('option[value=""]');
                    if (!allAccountsOption) {
                        allAccountsOption = document.createElement('option');
                        allAccountsOption.value = '';
                        allAccountsOption.textContent = 'All Accounts';
                        accountSelect.insertBefore(allAccountsOption, accountSelect.firstChild);
                    }
                    
                    // Set saved value or default to "All Accounts"
                    if (this.modalLedgerAccountId) {
                        accountSelect.value = this.modalLedgerAccountId;
                    } else {
                        accountSelect.value = '';
                        allAccountsOption.selected = true;
                    }
                    
                    // Add account change handler - auto-filter when account changes
                    accountSelect.addEventListener('change', (e) => {
                        const selectedAccountId = e.target.value || '';
                        this.modalLedgerAccountId = selectedAccountId;
                        // Auto-apply filter when account is selected
                        this.modalLedgerCurrentPage = 1;
                        // Force reload with new account filter
                this.loadModalJournalEntries();
                    });
                    
                    // Make sure dropdown is enabled and visible
                    accountSelect.disabled = false;
                }
                
                // Initialize search input
                const searchInput = document.getElementById('modalLedgerSearch');
                if (searchInput) {
                    // Ensure search value is set
                    if (this.modalLedgerSearch) {
                        searchInput.value = this.modalLedgerSearch;
                    }
                    
                    // Add search handler with debounce
                    let searchTimeout;
                    searchInput.addEventListener('input', (e) => {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => {
                            this.modalLedgerSearch = e.target.value || '';
                            this.modalLedgerCurrentPage = 1;
                            this.loadModalJournalEntries();
                        }, 300);
                    });
                    
                    // Also handle Enter key for immediate search
                    searchInput.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            clearTimeout(searchTimeout);
                            this.modalLedgerSearch = e.target.value || '';
                            this.modalLedgerCurrentPage = 1;
                            this.loadModalJournalEntries();
                        }
                    });
                }
                
                // Add per page handler
                const perPageSelect = document.getElementById('modalLedgerPerPage');
                if (perPageSelect) {
                    perPageSelect.value = this.modalLedgerPerPage.toString();
                    perPageSelect.addEventListener('change', (e) => {
                        this.modalLedgerPerPage = parseInt(e.target.value);
                        this.modalLedgerCurrentPage = 1;
                        this.loadModalJournalEntries();
                    });
                }
                
                // Load entries after all initialization
                this.loadModalJournalEntries();
            }, 100);
        },

        openReceivablesModal() {
            this.modalArCurrentPage = this.modalArCurrentPage || 1;
            this.modalArPerPage = this.modalArPerPage || 5;
            this.modalArSearch = this.modalArSearch || '';
            this.modalArDateFrom = this.modalArDateFrom || '';
            this.modalArDateTo = this.modalArDateTo || '';
            
            const content = `
                <div class="accounting-module-modal-content">
                    <div class="module-content">
                        <div class="summary-cards-mini-header">
                        <div class="summary-cards-mini">
                                <div class="summary-mini-card">
                                    <h4>Total Invoices</h4>
                                    <p id="modalArTotalInvoices">0</p>
                                </div>
                            <div class="summary-mini-card">
                                <h4>Total Outstanding</h4>
                                <p id="modalArTotalOutstanding">SAR 0.00</p>
                            </div>
                            <div class="summary-mini-card">
                                <h4>Overdue</h4>
                                <p id="modalArOverdue" class="text-danger">SAR 0.00</p>
                            </div>
                            <div class="summary-mini-card">
                                <h4>This Month</h4>
                                <p id="modalArThisMonth">SAR 0.00</p>
                            </div>
                                <div class="summary-entity-card">
                                    <h4>Posted</h4>
                                    <p id="modalArPostedCount">0</p>
                                    <span id="modalArPostedAmount" class="entity-amount">SAR 0.00</span>
                        </div>
                                <div class="summary-entity-card">
                                    <h4>Draft</h4>
                                    <p id="modalArDraftCount">0</p>
                                    <span id="modalArDraftAmount" class="entity-amount">SAR 0.00</span>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>Paid</h4>
                                    <p id="modalArPaidCount">0</p>
                                    <span id="modalArPaidAmount" class="entity-amount">SAR 0.00</span>
                                </div>
                                <div class="summary-entity-card">
                                    <h4>Unpaid</h4>
                                    <p id="modalArUnpaidCount">0</p>
                                    <span id="modalArUnpaidAmount" class="entity-amount">SAR 0.00</span>
                                </div>
                            </div>
                        </div>
                        <div class="filters-and-pagination-container">
                            <div class="filters-bar filters-bar-compact">
                                <div class="filter-group filter-group-compact">
                                    <label>Date From:</label>
                                    <input type="text" id="modalArDateFrom" class="filter-input filter-input-compact date-input" value="${this.modalArDateFrom}" placeholder="MM/DD/YYYY">
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Date To:</label>
                                    <input type="text" id="modalArDateTo" class="filter-input filter-input-compact date-input" value="${this.modalArDateTo}" placeholder="MM/DD/YYYY">
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Status:</label>
                                    <select id="modalArStatusFilter" class="filter-select filter-select-compact">
                                        <option value="">All Status</option>
                                        <option value="Posted">Posted</option>
                                        <option value="Draft">Draft</option>
                                        <option value="Paid">Paid</option>
                                        <option value="Unpaid">Unpaid</option>
                                        <option value="Overdue">Overdue</option>
                                    </select>
                                </div>
                                <div class="filter-group filter-group-compact">
                                <label>Search:</label>
                                    <input type="text" id="modalArSearch" class="filter-input filter-input-compact" placeholder="Search invoices..." value="${this.modalArSearch}">
                                </div>
                                <div class="filter-group filter-group-compact">
                                    <label>Show:</label>
                                    <select id="modalArPerPage" class="filter-select filter-select-compact">
                                        <option value="5" ${this.modalArPerPage === 5 ? 'selected' : ''}>5</option>
                                        <option value="10" ${this.modalArPerPage === 10 ? 'selected' : ''}>10</option>
                                        <option value="25" ${this.modalArPerPage === 25 ? 'selected' : ''}>25</option>
                                        <option value="50" ${this.modalArPerPage === 50 ? 'selected' : ''}>50</option>
                                        <option value="100" ${this.modalArPerPage === 100 ? 'selected' : ''}>100</option>
                                    </select>
                                </div>
                                <button class="btn btn-primary btn-sm" data-action="new-invoice">
                                    <i class="fas fa-plus"></i> New Invoice
                                </button>
                                <button class="btn btn-secondary btn-sm" data-action="receive-payment">
                                    <i class="fas fa-money-check-alt"></i> Receive Payment
                                </button>
                                <button class="btn btn-secondary btn-sm" data-action="export-receivables">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                            <div class="table-pagination-top" id="modalArPaginationTop">
                                <div class="pagination-info" id="modalArPaginationInfoTop"></div>
                                <div class="pagination-controls">
                                    <button class="btn-pagination btn-pagination-nav" id="modalArFirstTop" data-action="modal-ar-page" data-page="1" title="First Page">
                                        <i class="fas fa-angle-double-left"></i>
                                    </button>
                                    <button class="btn-pagination btn-pagination-nav" id="modalArPrevTop" data-action="modal-ar-prev" title="Previous Page">
                                        <i class="fas fa-angle-left"></i>
                                    </button>
                                    <span id="modalArPageNumbersTop" class="pagination-numbers"></span>
                                    <button class="btn-pagination btn-pagination-nav" id="modalArNextTop" data-action="modal-ar-next" title="Next Page">
                                        <i class="fas fa-angle-right"></i>
                                    </button>
                                    <button class="btn-pagination btn-pagination-nav" id="modalArLastTop" data-action="modal-ar-page" data-page="1" title="Last Page">
                                        <i class="fas fa-angle-double-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <!-- Bulk Actions Bar -->
                        <div class="bulk-actions-bar" id="bulkActionsAr">
                            <span class="bulk-selected-count" id="bulkSelectedCountAr">0 selected</span>
                            <div class="bulk-action-buttons">
                                <button class="btn btn-sm btn-danger" data-action="bulk-delete-ar">
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                                <button class="btn btn-sm btn-secondary" data-action="bulk-export-ar">
                                    <i class="fas fa-download"></i> Export Selected
                                </button>
                            </div>
                        </div>
                        <div class="data-table-container modal-table-wrapper modal-table-wrapper-no-scroll" id="modalArTableWrapper">
                            <table class="data-table modal-table-fixed" id="modalInvoicesTable">
                                <thead>
                                    <tr>
                                        <th class="index-column">
                                            <div class="ar-header-with-checkbox">
                                                <input type="checkbox" id="bulkSelectAllAr" data-action="bulk-select-all-ar" title="Select all">
                                                <span>#</span>
                                            </div>
                                        </th>
                                        <th class="date-column">Date</th>
                                        <th class="journal-number-column">Journal No</th>
                                        <th class="expense-column">Expense</th>
                                        <th class="amount-column">Amount</th>
                                        <th class="voucher-column">Payment Voucher</th>
                                        <th class="vat-column">Tax</th>
                                        <th class="status-column">Status</th>
                                        <th class="actions-column">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="modalInvoicesBody">
                                    <tr><td colspan="9" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
            this.showModal('Accounts Receivable', content, 'large');
            
            const self = this;
            const setupNewInvoiceHandler = function(modal) {
                if (!modal) {
                    console.error('Accounts Receivable modal not found');
                    return;
                }
                
                const newInvoiceBtn = modal.querySelector('[data-action="new-invoice"]');
                if (newInvoiceBtn) {
                    // Remove any existing handlers by cloning
                    const newBtn = newInvoiceBtn.cloneNode(true);
                    newInvoiceBtn.parentNode.replaceChild(newBtn, newInvoiceBtn);
                    
                    const clickHandler = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        e.cancelBubble = true;
                        e.returnValue = false;
                        // Try to call the real openInvoiceModal function directly
                        // First check if getInvoiceModalContent exists (means real function is available)
                        if (typeof self.getInvoiceModalContent === 'function') {
                            const content = self.getInvoiceModalContent(null);
                            self.showModal('Create Invoice', content, 'large');
                            
                            // Run initialization code after modal is shown
                            setTimeout(() => {
                                const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
                                if (modal && typeof self.initializeEnglishDatePickers === 'function') {
                                    self.initializeEnglishDatePickers(modal);
                                }
                            }, 100);
                            
                            setTimeout(async () => {
                                await new Promise(resolve => setTimeout(resolve, 50));
                                
                                const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
                                if (!modal) return;
                                
                                const debitSelect = document.getElementById('invoiceDebitAccountSelect');
                                const creditSelect = document.getElementById('invoiceCreditAccountSelect');
                                
                                if (debitSelect && typeof self.loadAccountsForSelect === 'function') {
                                    try {
                                        await self.loadAccountsForSelect('invoiceDebitAccountSelect');
                                    } catch (error) {
                                        console.error('Error loading debit accounts:', error);
                                    }
                                }
                                
                                if (creditSelect && typeof self.loadAccountsForSelect === 'function') {
                                    try {
                                        await self.loadAccountsForSelect('invoiceCreditAccountSelect');
                                    } catch (error) {
                                        console.error('Error loading credit accounts:', error);
                                    }
                                }
                                
                            const form = document.getElementById('invoiceForm');
                            if (form) {
                                // Populate currency dropdown - ensure it loads properly
                                const currencySelect = document.getElementById('invoiceCurrencySelect') || form.querySelector('select[name="currency"]');
                                if (currencySelect) {
                                    // Clear loading message first
                                    currencySelect.innerHTML = '<option value="">Loading currencies...</option>';
                                    
                                    if (window.currencyUtils && typeof window.currencyUtils.populateCurrencySelect === 'function') {
                                        try {
                                            const defaultCurrency = (typeof self.getDefaultCurrencySync === 'function') ? self.getDefaultCurrencySync() : 'SAR';
                                            await window.currencyUtils.populateCurrencySelect(currencySelect, defaultCurrency);
                                        } catch (error) {
                                            console.error('Error populating invoice currency dropdown:', error);
                                            // Fallback: try getCurrencyOptionsHTML
                                            if (typeof self.getCurrencyOptionsHTML === 'function') {
                                                try {
                                                    const defaultCurrency = (typeof self.getDefaultCurrencySync === 'function') ? self.getDefaultCurrencySync() : 'SAR';
                                                    const optionsHTML = await self.getCurrencyOptionsHTML(defaultCurrency);
                                                    if (optionsHTML) {
                                                        currencySelect.innerHTML = optionsHTML;
                                                    } else {
                                                        currencySelect.innerHTML = '<option value="">Error loading currencies</option>';
                                                    }
                                                } catch (err) {
                                                    console.error('Error using getCurrencyOptionsHTML fallback:', err);
                                                    currencySelect.innerHTML = '<option value="">Error loading currencies</option>';
                                                }
                                            } else {
                                                currencySelect.innerHTML = '<option value="">Error loading currencies</option>';
                                            }
                                        }
                                    } else if (typeof self.getCurrencyOptionsHTML === 'function') {
                                        // Fallback if currencyUtils not available
                                        try {
                                            const defaultCurrency = (typeof self.getDefaultCurrencySync === 'function') ? self.getDefaultCurrencySync() : 'SAR';
                                            const optionsHTML = await self.getCurrencyOptionsHTML(defaultCurrency);
                                            if (optionsHTML) {
                                                currencySelect.innerHTML = optionsHTML;
                                            } else {
                                                currencySelect.innerHTML = '<option value="">Error loading currencies</option>';
                                            }
                                        } catch (error) {
                                            console.error('Error populating currency with getCurrencyOptionsHTML:', error);
                                            currencySelect.innerHTML = '<option value="">Error loading currencies</option>';
                                        }
                                    } else {
                                        // Last resort fallback
                                        currencySelect.innerHTML = '<option value="SAR">SAR - Saudi Riyal</option><option value="USD">USD - US Dollar</option><option value="EUR">EUR - Euro</option>';
                                    }
                                }
                                    
                                    // Setup customer fields
                                    if (typeof self.setupCustomerFields === 'function') {
                                        try {
                                            self.setupCustomerFields('invoiceCustomersContainer', null);
                                        } catch (error) {
                                            console.error('Error setting up customer fields:', error);
                                        }
                                    }
                                    
                                    // Setup Tax checkbox
                                    const taxCb = form.querySelector('#invoiceTaxCheckbox');
                                    const taxLabel = form.querySelector('#invoiceTaxLabel');
                                    if (taxCb && taxLabel) {
                                        const updateTaxLabel = () => { taxLabel.textContent = taxCb.checked ? 'Tax included' : 'Tax not included'; };
                                        taxCb.addEventListener('change', updateTaxLabel);
                                        updateTaxLabel();
                                    }
                                    
                                    // Setup close handlers for Cancel/X/outside click
                                    const closeInvoiceModal = async () => {
                                        try {
                                            modal.classList.remove('accounting-modal-visible', 'show-modal');
                                            modal.classList.add('accounting-modal-hidden');
                                            modal.removeAttribute('data-modal-visible');
                                            if (modal.parentNode) {
                                                modal.remove();
                                            }
                                            if (self.activeModal === modal) {
                                                self.activeModal = null;
                                            }
                                            document.body.classList.remove('body-no-scroll');
                                        } catch (e) {
                                            console.error('Error closing invoice modal:', e);
                                        }
                                    };
                                    
                                    // Form-level click handler for cancel button
                                    form.addEventListener('click', async (e) => {
                                        const button = e.target.closest('button');
                                        if (button) {
                                            const btnId = button.id;
                                            const btnText = button.textContent.trim().toLowerCase();
                                            const hasCloseAction = button.hasAttribute('data-action') && button.getAttribute('data-action') === 'close-modal';
                                            const isCancelBtn = btnId === 'invoiceCancelBtn' || (btnText.includes('cancel') && button.classList.contains('btn-secondary'));
                                            
                                            if (hasCloseAction || isCancelBtn) {
                                                e.preventDefault();
                                                e.stopPropagation();
                                                e.stopImmediatePropagation();
                                                e.cancelBubble = true;
                                                e.returnValue = false;
                                                await closeInvoiceModal();
                                                return false;
                                            }
                                        }
                                    }, { capture: true });
                                    
                                    // Direct handlers for buttons
                                    setTimeout(() => {
                                        const cancelBtn = form.querySelector('#invoiceCancelBtn') || form.querySelector('button[data-action="close-modal"]');
                                        if (cancelBtn) {
                                            const newCancelBtn = cancelBtn.cloneNode(true);
                                            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
                                            
                                            const cancelHandler = async function(e) {
                                                e.preventDefault();
                                                e.stopPropagation();
                                                e.stopImmediatePropagation();
                                                e.cancelBubble = true;
                                                e.returnValue = false;
                                                await closeInvoiceModal();
                                                return false;
                                            };
                                            
                                            newCancelBtn.onclick = cancelHandler;
                                            newCancelBtn.addEventListener('click', cancelHandler, { capture: true, once: false });
                                            newCancelBtn.addEventListener('click', cancelHandler, { capture: false, once: false });
                                        }
                                        
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
                                                await closeInvoiceModal();
                                                return false;
                                            };
                                            
                                            newCloseBtn.onclick = closeHandler;
                                            newCloseBtn.addEventListener('click', closeHandler, true);
                                        }
                                        
                                        const overlay = modal.querySelector('.accounting-modal-overlay');
                                        if (overlay) {
                                            const overlayHandler = async function(e) {
                                                if (e.target === overlay) {
                                                    e.preventDefault();
                                                    e.stopPropagation();
                                                    e.stopImmediatePropagation();
                                                    e.cancelBubble = true;
                                                    e.returnValue = false;
                                                    await closeInvoiceModal();
                                                    return false;
                                                }
                                            };
                                            
                                            overlay.onclick = overlayHandler;
                                            overlay.addEventListener('click', overlayHandler, true);
                                        }
                                        
                                        const backdropHandler = async function(e) {
                                            if (e.target === modal && !e.target.closest('.accounting-modal-content')) {
                                                e.preventDefault();
                                                e.stopPropagation();
                                                e.stopImmediatePropagation();
                                                e.cancelBubble = true;
                                                e.returnValue = false;
                                                await closeInvoiceModal();
                                                return false;
                                            }
                                        };
                                        
                                        modal.onclick = backdropHandler;
                                        modal.addEventListener('click', backdropHandler, true);
                                    }, 200);
                                }
                            }, 150);
                        } else if (typeof self.openInvoiceModal === 'function') {
                            // Check if it's the real function or the alias
                            const funcStr = self.openInvoiceModal.toString();
                            if (funcStr.includes('getInvoiceModalContent') || funcStr.includes('Create Invoice') || funcStr.includes('Edit Invoice')) {
                                self.openInvoiceModal();
                            } else {
                                // Try to get the real function from prototype
                                if (typeof ProfessionalAccounting !== 'undefined') {
                                    const protoFunc = ProfessionalAccounting.prototype.openInvoiceModal;
                                    if (protoFunc && protoFunc.toString().includes('getInvoiceModalContent')) {
                                        protoFunc.call(self);
                                    } else {
                                        console.error('Real openInvoiceModal not found in prototype');
                                    }
                                }
                            }
                        } else {
                            console.error('openInvoiceModal function not found');
                        }
                        return false;
                    };
                    
                    newBtn.onclick = clickHandler;
                    newBtn.addEventListener('click', clickHandler, { capture: true });
                    newBtn.addEventListener('click', clickHandler, { capture: false });
                } else {
                    console.error('New Invoice button not found in modal');
                }
            };
            
            setTimeout(() => {
                // Setup New Invoice button handler - find within modal with multiple attempts
                let currentModal = document.getElementById('accountingModalProfessional') || 
                                 document.querySelector('.accounting-modal:not(.accounting-modal-hidden)') ||
                                 document.querySelector('.accounting-modal[data-modal-visible="true"]');
                
                if (currentModal) {
                    setupNewInvoiceHandler(currentModal);
                } else {
                    // Try again after a short delay
                    setTimeout(() => {
                        currentModal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
                        if (currentModal) {
                            setupNewInvoiceHandler(currentModal);
                        }
                    }, 100);
                }
                
                // Initialize date inputs
                const dateFromInput = document.getElementById('modalArDateFrom');
                const dateToInput = document.getElementById('modalArDateTo');
                
                if (dateFromInput) {
                    if (!this.modalArDateFrom) {
                        const ninetyDaysAgo = new Date();
                        ninetyDaysAgo.setDate(ninetyDaysAgo.getDate() - 90);
                        this.modalArDateFrom = this.formatDateForInput(ninetyDaysAgo.toISOString().split('T')[0]);
                    }
                    dateFromInput.value = this.modalArDateFrom;
                    dateFromInput.addEventListener('change', () => {
                        this.modalArDateFrom = dateFromInput.value;
                        this.modalArCurrentPage = 1;
                this.loadModalInvoices();
                    });
                }
                if (dateToInput) {
                    if (!this.modalArDateTo) {
                        const today = this.formatDateForInput(new Date().toISOString().split('T')[0]);
                        this.modalArDateTo = today;
                    }
                    dateToInput.value = this.modalArDateTo;
                    dateToInput.addEventListener('change', () => {
                        this.modalArDateTo = dateToInput.value;
                        this.modalArCurrentPage = 1;
                        this.loadModalInvoices();
                    });
                }
                
                // Status filter
                const statusFilter = document.getElementById('modalArStatusFilter');
                if (statusFilter) {
                    statusFilter.addEventListener('change', () => {
                        this.modalArCurrentPage = 1;
                        this.loadModalInvoices();
                    });
                }
                
                // Search input
                const searchInput = document.getElementById('modalArSearch');
                if (searchInput) {
                    if (this.modalArSearch) {
                        searchInput.value = this.modalArSearch;
                    }
                    let searchTimeout;
                    searchInput.addEventListener('input', (e) => {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => {
                            this.modalArSearch = e.target.value || '';
                            this.modalArCurrentPage = 1;
                            this.loadModalInvoices();
                        }, 300);
                    });
                    searchInput.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            clearTimeout(searchTimeout);
                            this.modalArSearch = e.target.value || '';
                            this.modalArCurrentPage = 1;
                            this.loadModalInvoices();
                        }
                    });
                }
                
                // Per page
                const perPageSelect = document.getElementById('modalArPerPage');
                if (perPageSelect) {
                    perPageSelect.addEventListener('change', (e) => {
                        this.modalArPerPage = parseInt(e.target.value);
                        this.modalArCurrentPage = 1;
                        this.loadModalInvoices();
                    });
                }
                
                // Setup Receive Payment button handler
                const receivePaymentBtn = currentModal ? currentModal.querySelector('[data-action="receive-payment"]') : null;
                if (receivePaymentBtn) {
                    const newReceiveBtn = receivePaymentBtn.cloneNode(true);
                    receivePaymentBtn.parentNode.replaceChild(newReceiveBtn, receivePaymentBtn);
                    
                    newReceiveBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        if (typeof this.openReceivePaymentModal === 'function') {
                            this.openReceivePaymentModal();
                        }
                    });
                }
                
                // Setup Export button handler
                const exportBtn = currentModal ? currentModal.querySelector('[data-action="export-receivables"]') : null;
                if (exportBtn) {
                    const newExportBtn = exportBtn.cloneNode(true);
                    exportBtn.parentNode.replaceChild(newExportBtn, exportBtn);
                    
                    newExportBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        // Add export functionality here if needed
                    });
                }
                
                this.loadModalInvoices();
            }, 100);
        },

        openPayablesModal() {
            this.modalApCurrentPage = this.modalApCurrentPage || 1;
            this.modalApPerPage = this.modalApPerPage || 5;
            this.modalApSearch = this.modalApSearch || '';
            
            const content = `
                <div class="accounting-module-modal-content">
                    <div class="module-header">
                        <h2><i class="fas fa-file-invoice"></i> Accounts Payable</h2>
                        <div class="header-actions">
                            <button class="btn btn-primary" data-action="new-bill">
                                <i class="fas fa-plus"></i> New Bill
                            </button>
                            <button class="btn btn-secondary" data-action="make-payment">
                                <i class="fas fa-money-bill-wave"></i> Make Payment
                            </button>
                        </div>
                    </div>
                    <div class="module-content">
                        <div class="summary-cards-mini">
                            <div class="summary-mini-card">
                                <h4>Total Outstanding</h4>
                                <p id="modalApTotalOutstanding">SAR 0.00</p>
                            </div>
                            <div class="summary-mini-card">
                                <h4>Overdue</h4>
                                <p id="modalApOverdue" class="text-danger">SAR 0.00</p>
                            </div>
                            <div class="summary-mini-card">
                                <h4>This Month</h4>
                                <p id="modalApThisMonth">SAR 0.00</p>
                            </div>
                        </div>
                        <div class="filters-bar">
                            <div class="filter-group">
                                <label>Search:</label>
                                <input type="text" id="modalApSearch" class="filter-input" placeholder="Search bills..." value="${this.modalApSearch}">
                            </div>
                        </div>
                        <!-- Bulk Actions Bar -->
                        <div class="bulk-actions-bar" id="bulkActionsAp">
                            <span class="bulk-selected-count" id="bulkSelectedCountAp">0 selected</span>
                            <div class="bulk-action-buttons">
                                <button class="btn btn-sm btn-danger" data-action="bulk-delete-ap">
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                                <button class="btn btn-sm btn-secondary" data-action="bulk-export-ap">
                                    <i class="fas fa-download"></i> Export Selected
                                </button>
                            </div>
                        </div>
                        <!-- Top Pagination -->
                        <div class="table-pagination-top" id="modalApPaginationTop">
                            <div class="pagination-info" id="modalApPaginationInfoTop"></div>
                            <div class="pagination-controls">
                                <button class="btn btn-sm btn-secondary" id="modalApPrevTop" data-action="modal-ap-prev">Previous</button>
                                <span id="modalApPageNumbersTop"></span>
                                <button class="btn btn-sm btn-secondary" id="modalApNextTop" data-action="modal-ap-next">Next</button>
                            </div>
                        </div>
                        <div class="data-table-container modal-table-wrapper modal-table-wrapper-scroll">
                            <table class="data-table modal-table-fixed" id="modalBillsTable">
                                <thead>
                                    <tr>
                                        <th class="invoice-column">Bill #</th>
                                        <th class="date-column">Date</th>
                                        <th class="customer-column">Vendor</th>
                                        <th class="date-column">Due Date</th>
                                        <th class="amount-column">Debit</th>
                                        <th class="amount-column">Credit</th>
                                        <th class="amount-column">Paid</th>
                                        <th class="amount-column">Balance</th>
                                        <th class="status-column">Status</th>
                                        <th class="checkbox-column"><input type="checkbox" id="bulkSelectAllAp" data-action="bulk-select-all-ap"></th>
                                        <th class="actions-column">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="modalBillsBody">
                                    <tr><td colspan="10" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Bottom Pagination -->
                        <div class="table-pagination-bottom" id="modalApPaginationBottom">
                            <div class="pagination-info" id="modalApPaginationInfoBottom"></div>
                            <div class="pagination-controls">
                                <button class="btn btn-sm btn-secondary" id="modalApPrevBottom" data-action="modal-ap-prev">Previous</button>
                                <span id="modalApPageNumbersBottom"></span>
                                <button class="btn btn-sm btn-secondary" id="modalApNextBottom" data-action="modal-ap-next">Next</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            this.showModal('Accounts Payable', content, 'large');
            setTimeout(() => {
                this.loadModalBills();
                // Add search handler
                const searchInput = document.getElementById('modalApSearch');
                if (searchInput) {
                    let searchTimeout;
                    searchInput.addEventListener('input', (e) => {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => {
                            this.modalApSearch = e.target.value;
                            this.modalApCurrentPage = 1;
                            this.loadModalBills();
                        }, 300);
                    });
                }
            }, 100);
        },

        openBankingModal() {
            this.modalBankCurrentPage = this.modalBankCurrentPage || 1;
            this.modalBankPerPage = this.modalBankPerPage || 5;
            this.modalBankSearch = this.modalBankSearch || '';
            
            const content = `
                <div class="accounting-module-modal-content">
                    <div class="module-header">
                        <h2><i class="fas fa-university"></i> Banking</h2>
                        <div class="header-actions">
                            <button class="btn btn-primary" data-action="new-bank-account">
                                <i class="fas fa-plus"></i> Add Bank Account
                            </button>
                            <button class="btn btn-secondary" data-action="reconcile-account">
                                <i class="fas fa-balance-scale"></i> Reconcile
                            </button>
                        </div>
                    </div>
                    <div class="module-content">
                        <div class="filters-bar">
                            <div class="filter-group">
                                <label>Search:</label>
                                <input type="text" id="modalBankSearch" class="filter-input" placeholder="Search bank accounts..." value="${this.modalBankSearch}">
                            </div>
                        </div>
                        <!-- Bulk Actions Bar -->
                        <div class="bulk-actions-bar" id="bulkActionsBank">
                            <span class="bulk-selected-count" id="bulkSelectedCountBank">0 selected</span>
                            <div class="bulk-action-buttons">
                                <button class="btn btn-sm btn-danger" data-action="bulk-delete-bank">
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                                <button class="btn btn-sm btn-secondary" data-action="bulk-export-bank">
                                    <i class="fas fa-download"></i> Export Selected
                                </button>
                            </div>
                        </div>
                        <!-- Top Pagination -->
                        <div class="table-pagination-top" id="modalBankPaginationTop">
                            <div class="pagination-info" id="modalBankPaginationInfoTop"></div>
                            <div class="pagination-controls">
                                <button class="btn btn-sm btn-secondary" id="modalBankPrevTop" data-action="modal-bank-prev">Previous</button>
                                <span id="modalBankPageNumbersTop"></span>
                                <button class="btn btn-sm btn-secondary" id="modalBankNextTop" data-action="modal-bank-next">Next</button>
                            </div>
                        </div>
                        <div class="data-table-container modal-table-wrapper modal-table-wrapper-scroll">
                            <table class="data-table modal-table-fixed" id="modalBankAccountsTable">
                                <thead>
                                    <tr>
                                        <th class="bank-column">Bank Name</th>
                                        <th class="bank-column">Account Name</th>
                                        <th class="bank-column">Account Number</th>
                                        <th class="account-type-column">Account Type</th>
                                        <th class="amount-column">Opening Balance</th>
                                        <th class="amount-column">Current Balance</th>
                                        <th class="status-column">Status</th>
                                        <th class="checkbox-column"><input type="checkbox" id="bulkSelectAllBank" data-action="bulk-select-all-bank"></th>
                                        <th class="actions-column">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="modalBankAccountsBody">
                                    <tr><td colspan="9" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Bottom Pagination -->
                        <div class="table-pagination-bottom" id="modalBankPaginationBottom">
                            <div class="pagination-info" id="modalBankPaginationInfoBottom"></div>
                            <div class="pagination-controls">
                                <button class="btn btn-sm btn-secondary" id="modalBankPrevBottom" data-action="modal-bank-prev">Previous</button>
                                <span id="modalBankPageNumbersBottom"></span>
                                <button class="btn btn-sm btn-secondary" id="modalBankNextBottom" data-action="modal-bank-next">Next</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            this.showModal('Banking', content, 'large');
            setTimeout(() => {
                this.loadModalBankAccounts();
                // Add search handler
                const searchInput = document.getElementById('modalBankSearch');
                if (searchInput) {
                    let searchTimeout;
                    searchInput.addEventListener('input', (e) => {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(() => {
                            this.modalBankSearch = e.target.value;
                            this.modalBankCurrentPage = 1;
                            this.loadModalBankAccounts();
                        }, 300);
                    });
                }
            }, 100);
        },

        openEntitiesModal() {
            this.modalEntityCurrentPage = this.modalEntityCurrentPage || 1;
            this.modalEntityPerPage = this.modalEntityPerPage || 10;
            this.modalEntitySearch = this.modalEntitySearch || '';
            
            const content = `
                <div class="accounting-module-modal-content">
                    <div class="module-header">
                        <h2><i class="fas fa-users"></i> Entity Financial Management</h2>
                        <div class="header-actions">
                            <button class="btn btn-primary" data-action="new-entity-transaction">
                                <i class="fas fa-plus"></i> New Transaction
                            </button>
                        </div>
                    </div>
                    <div class="module-content">
                        <!-- Status Cards -->
                        <div class="entity-status-cards">
                            <div class="status-card-mini">
                                <div class="status-icon"><i class="fas fa-list"></i></div>
                                <div class="status-content">
                                    <div class="status-label">Total Transactions</div>
                                    <div class="status-value" id="statusTotalTransactions">0</div>
                        </div>
                            </div>
                            <div class="status-card-mini">
                                <div class="status-icon success"><i class="fas fa-check-circle"></i></div>
                                <div class="status-content">
                                    <div class="status-label">Posted</div>
                                    <div class="status-value" id="statusPosted">0</div>
                                </div>
                            </div>
                            <div class="status-card-mini">
                                <div class="status-icon warning"><i class="fas fa-clock"></i></div>
                                <div class="status-content">
                                    <div class="status-label">Draft</div>
                                    <div class="status-value" id="statusDraft">0</div>
                                </div>
                            </div>
                            <div class="status-card-mini">
                                <div class="status-icon info"><i class="fas fa-dollar-sign"></i></div>
                                <div class="status-content">
                                    <div class="status-label">Total Amount</div>
                                    <div class="status-value" id="statusTotalAmount">SAR 0.00</div>
                                </div>
                            </div>
                        </div>
                        <!-- Filters and Search Bar -->
                        <div class="modern-filters-bar">
                            <div class="filters-left">
                                <div class="filter-group-modern">
                                    <label>Status</label>
                                    <select id="entityFilterStatus" class="filter-select-modern">
                                        <option value="">All Statuses</option>
                                        <option value="Posted">Posted</option>
                                        <option value="Draft">Draft</option>
                                        <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>
                                <div class="filter-group-modern search-group">
                                    <label>Search</label>
                                    <div class="search-input-wrapper">
                                        <i class="fas fa-search search-icon"></i>
                                        <input type="text" id="entitySearchInput" class="search-input-modern" placeholder="Search transactions...">
                                        <button class="search-clear hidden" id="entitySearchClear">
                                            <i class="fas fa-times"></i>
                                        </button>
                            </div>
                        </div>
                            </div>
                            <div class="filters-right">
                                <button class="btn-modern btn-secondary" data-action="reset-entity-filters">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                            </div>
                        </div>
                        <!-- Bulk Actions Bar -->
                        <div class="bulk-actions-modern hidden" id="entityBulkActions">
                            <div class="bulk-info">
                                <span id="entityBulkCount">0</span> selected
                            </div>
                            <div class="bulk-buttons">
                                <button class="btn-modern btn-danger-sm" data-action="bulk-delete-entities">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                <button class="btn-modern btn-info-sm" data-action="bulk-export-entities">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                        </div>
                        <!-- Table Container -->
                        <div class="modern-table-container">
                            <div class="table-header-modern">
                                <div class="table-info">
                                    <span>Show</span>
                                    <select id="entityPerPage" class="per-page-select">
                                        <option value="5">5</option>
                                        <option value="10" selected>10</option>
                                        <option value="25">25</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                    </select>
                                    <span>entries</span>
                            </div>
                                <div class="table-pagination-top-modern" id="entityPaginationTop"></div>
                        </div>
                            <div class="table-wrapper-modern">
                                <table class="table-modern" id="entityTransactionsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Entity</th>
                                        <th>Type</th>
                                        <th>Reference</th>
                                        <th>Description</th>
                                        <th>Debit</th>
                                        <th>Credit</th>
                                        <th>Status</th>
                                        <th class="checkbox-col">
                                            <input type="checkbox" id="selectAllEntities" class="checkbox-modern">
                                        </th>
                                        <th class="actions-col">Actions</th>
                                    </tr>
                                </thead>
                                    <tbody id="entityTransactionsBody">
                                        <tr>
                                            <td colspan="17" class="loading-row">
                                                <div class="loading-spinner">
                                                    <i class="fas fa-spinner fa-spin"></i>
                                                    <span>Loading transactions...</span>
                                                </div>
                                            </td>
                                        </tr>
                                </tbody>
                            </table>
                        </div>
                            <div class="table-footer-modern">
                                <div class="table-pagination-info" id="entityPaginationInfo">
                                    Showing 0 to 0 of 0 entries
                                </div>
                                <div class="table-pagination-bottom-modern" id="entityPaginationBottom"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            this.showModal('Entities', content, 'large');
            
            // Initialize entity transactions system
            this.entityTransactionsCurrentPage = 1;
            this.entityTransactionsPerPage = 10;
            this.entityTransactionsTotalPages = 1;
            this.entityTransactionsTotalCount = 0;
            this.entityTransactionsData = [];
            this.entityTransactionsFiltered = [];
            this.entityTransactionsSelected = new Set();
            
            // Load data immediately
            setTimeout(() => {
                this.loadEntityTransactionsData();
                this.attachEntityTransactionsHandlers();
            }, 100);
        }
    };
    Object.assign(ProfessionalAccounting.prototype, methods);
})();
