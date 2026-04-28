/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.part3.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.part3.js`.
 */
/** Professional Accounting - Part 3 (lines 10199-15198) */
                const overdueClass = daysOverdue > 0 ? 'overdue' : '';
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'} ${overdueClass}">`;
                const billNumber = this.escapeHtml(item.bill_number || '');
                const vendorName = this.escapeHtml(item.vendor_name || '');
                html += `<td class="report-col-invoice" title="${billNumber}"><code>${billNumber}</code></td>`;
                html += `<td class="report-col-customer" title="${vendorName}">${vendorName}</td>`;
                html += `<td class="report-col-date" title="${this.formatDate(item.bill_date || '')}">${this.formatDate(item.bill_date || '')}</td>`;
                html += `<td class="report-col-date" title="${this.formatDate(item.due_date || '')}">${this.formatDate(item.due_date || '')}</td>`;
                html += `<td class="report-col-amount text-right">${this.formatCurrency(parseFloat(item.total_amount || 0))}</td>`;
                html += `<td class="report-col-amount text-right debit-cell">${this.formatCurrency(parseFloat(item.paid_amount || 0))}</td>`;
                html += `<td class="report-col-amount text-right credit-cell">${this.formatCurrency(parseFloat(item.balance || 0))}</td>`;
                html += `<td class="report-col-days text-right ${daysOverdue > 0 ? 'overdue-badge' : ''}">${daysOverdue > 0 ? daysOverdue : '-'}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="8" class="text-center report-empty-state">No payables found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.total_outstanding !== undefined) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="6" class="report-totals-label"><strong>Total Outstanding:</strong></td>';
            html += `<td class="report-col-amount text-right credit-cell"><strong>${this.formatCurrency(parseFloat(reportData.total_outstanding || 0))}</strong></td>`;
            html += '<td></td>';
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }

ProfessionalAccounting.prototype.formatCashBook = function(reportData) {
        // Get all transactions and apply search filter
        let allTransactions = reportData?.transactions || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allTransactions = allTransactions.filter(txn => {
                const description = (txn.description || '').toLowerCase();
                const reference = (txn.reference_number || '').toLowerCase();
                const type = (txn.transaction_type || '').toLowerCase();
                return description.includes(searchTerm) || reference.includes(searchTerm) || type.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allTransactions.length;
        
        // Get paginated transactions (if perPage is 999999, show all)
        let paginatedTransactions = allTransactions;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedTransactions = allTransactions.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-date">Date</th>';
        html += '<th class="report-col-name">Description</th>';
        html += '<th class="report-col-name">Reference</th>';
        html += '<th class="report-col-type">Type</th>';
        html += '<th class="report-col-debit text-right">Debit</th>';
        html += '<th class="report-col-credit text-right">Credit</th>';
        html += '<th class="report-col-balance text-right">Balance</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (Array.isArray(paginatedTransactions) && paginatedTransactions.length > 0) {
            paginatedTransactions.forEach((txn, index) => {
                const balance = parseFloat(txn.balance || 0);
                const balanceClass = balance >= 0 ? 'balance-positive' : 'balance-negative';
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-date">${this.formatDate(txn.transaction_date || '')}</td>`;
                html += `<td class="report-col-name">${this.escapeHtml(txn.description || '')}</td>`;
                html += `<td class="report-col-name"><code>${this.escapeHtml(txn.reference_number || '')}</code></td>`;
                html += `<td class="report-col-type"><span class="type-badge type-badge-${(txn.transaction_type || '').toLowerCase()}">${this.escapeHtml(txn.transaction_type || '')}</span></td>`;
                html += `<td class="report-col-debit text-right debit-cell">${parseFloat(txn.debit_amount || 0) > 0 ? this.formatCurrency(parseFloat(txn.debit_amount || 0)) : '-'}</td>`;
                html += `<td class="report-col-credit text-right credit-cell">${parseFloat(txn.credit_amount || 0) > 0 ? this.formatCurrency(parseFloat(txn.credit_amount || 0)) : '-'}</td>`;
                html += `<td class="report-col-balance text-right ${balanceClass}">${this.formatCurrency(balance)}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="7" class="text-center report-empty-state">No cash transactions found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="4" class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-debit text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_debit || 0))}</strong></td>`;
            html += `<td class="report-col-credit text-right credit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_credit || 0))}</strong></td>`;
            html += `<td class="report-col-balance text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.closing_balance || 0))}</strong></td>`;
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }

ProfessionalAccounting.prototype.formatBankBook = function(reportData) {
        // Get all transactions and apply search filter
        let allTransactions = reportData?.transactions || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allTransactions = allTransactions.filter(txn => {
                const description = (txn.description || '').toLowerCase();
                const reference = (txn.reference_number || '').toLowerCase();
                const type = (txn.transaction_type || '').toLowerCase();
                const bankName = (txn.bank_account_name || '').toLowerCase();
                return description.includes(searchTerm) || reference.includes(searchTerm) || type.includes(searchTerm) || bankName.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allTransactions.length;
        
        // Get paginated transactions (if perPage is 999999, show all)
        let paginatedTransactions = allTransactions;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedTransactions = allTransactions.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-date">Date</th>';
        html += '<th class="report-col-name">Description</th>';
        html += '<th class="report-col-name">Reference</th>';
        html += '<th class="report-col-name">Bank Account</th>';
        html += '<th class="report-col-type">Type</th>';
        html += '<th class="report-col-debit text-right">Debit</th>';
        html += '<th class="report-col-credit text-right">Credit</th>';
        html += '<th class="report-col-balance text-right">Balance</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (Array.isArray(paginatedTransactions) && paginatedTransactions.length > 0) {
            paginatedTransactions.forEach((txn, index) => {
                const balance = parseFloat(txn.balance || 0);
                const balanceClass = balance >= 0 ? 'balance-positive' : 'balance-negative';
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-date">${this.formatDate(txn.transaction_date || '')}</td>`;
                html += `<td class="report-col-name">${this.escapeHtml(txn.description || '')}</td>`;
                html += `<td class="report-col-name"><code>${this.escapeHtml(txn.reference_number || '')}</code></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(txn.bank_account_name || 'N/A')}</td>`;
                html += `<td class="report-col-type"><span class="type-badge type-badge-${(txn.transaction_type || '').toLowerCase()}">${this.escapeHtml(txn.transaction_type || '')}</span></td>`;
                html += `<td class="report-col-debit text-right debit-cell">${parseFloat(txn.debit_amount || 0) > 0 ? this.formatCurrency(parseFloat(txn.debit_amount || 0)) : '-'}</td>`;
                html += `<td class="report-col-credit text-right credit-cell">${parseFloat(txn.credit_amount || 0) > 0 ? this.formatCurrency(parseFloat(txn.credit_amount || 0)) : '-'}</td>`;
                html += `<td class="report-col-balance text-right ${balanceClass}">${this.formatCurrency(balance)}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="8" class="text-center report-empty-state">No bank transactions found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="5" class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-debit text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_debit || 0))}</strong></td>`;
            html += `<td class="report-col-credit text-right credit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_credit || 0))}</strong></td>`;
            html += `<td class="report-col-balance text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.closing_balance || 0))}</strong></td>`;
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }

ProfessionalAccounting.prototype.getReportStatusCards = function(reportType, reportData) {
        switch (reportType) {
            case 'general-ledger':
            case 'general-ledger-report':
                return this.getGeneralLedgerStatusCards(reportData);
            case 'trial-balance':
                return this.getTrialBalanceStatusCards(reportData);
            case 'income-statement':
            case 'profit-loss':
                return this.getIncomeStatementStatusCards(reportData);
            case 'balance-sheet':
                return this.getBalanceSheetStatusCards(reportData);
            case 'cash-flow':
                return this.getCashFlowStatusCards(reportData);
            case 'aged-receivables':
            case 'ages-debt-receivable':
            case 'ages-credit-receivable':
                return this.getAgedReceivablesStatusCards(reportData);
            case 'aged-payables':
                return this.getAgedPayablesStatusCards(reportData);
            case 'cash-book':
                return this.getCashBookStatusCards(reportData);
            case 'bank-book':
                return this.getBankBookStatusCards(reportData);
            case 'account-statement':
                return this.getAccountStatementStatusCards(reportData);
            case 'expense-statement':
                return this.getExpenseStatementStatusCards(reportData);
            case 'chart-of-accounts-report':
                return this.getChartOfAccountsStatusCards(reportData);
            case 'value-added':
                return this.getValueAddedStatusCards(reportData);
            case 'fixed-assets':
                return this.getFixedAssetsStatusCards(reportData);
            case 'entries-by-year':
                return this.getEntriesByYearStatusCards(reportData);
            case 'customer-debits':
                return this.getCustomerDebitsStatusCards(reportData);
            case 'statistical-position':
                return this.getStatisticalPositionStatusCards(reportData);
            case 'changes-equity':
                return this.getChangesInEquityStatusCards(reportData);
            case 'financial-performance':
                return this.getFinancialPerformanceStatusCards(reportData);
            case 'comparative-report':
                return this.getComparativeReportStatusCards(reportData);
            default:
                return '';
        }
    }

ProfessionalAccounting.prototype.getGeneralLedgerStatusCards = function(reportData) {
        const allAccounts = reportData?.accounts || [];
        
        // Calculate statistics for status cards
        const totalAccounts = allAccounts.length;
        const accountsWithTransactions = allAccounts.filter(acc => (acc.transactions || []).length > 0).length;
        const accountsWithoutTransactions = totalAccounts - accountsWithTransactions;
        const totalTransactions = allAccounts.reduce((sum, acc) => sum + (acc.transactions || []).length, 0);
        const totalDebit = parseFloat(reportData?.totals?.total_debit || 0);
        const totalCredit = parseFloat(reportData?.totals?.total_credit || 0);
        const balance = totalDebit - totalCredit;
        const difference = parseFloat(reportData?.totals?.difference || 0);
        
        let html = '<div class="report-status-cards">';
        html += '<div class="stat-card stat-card-primary">';
        html += '<i class="fas fa-book stat-icon stat-icon-primary"></i>';
        html += '<div class="stat-info">';
        html += `<span class="stat-value">${totalAccounts}</span>`;
        html += '<span class="stat-label">Total Accounts</span>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="stat-card stat-card-success">';
        html += '<i class="fas fa-check-circle stat-icon stat-icon-success"></i>';
        html += '<div class="stat-info">';
        html += `<span class="stat-value">${accountsWithTransactions}</span>`;
        html += '<span class="stat-label">With Transactions</span>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="stat-card stat-card-warning">';
        html += '<i class="fas fa-exclamation-circle stat-icon stat-icon-warning"></i>';
        html += '<div class="stat-info">';
        html += `<span class="stat-value">${accountsWithoutTransactions}</span>`;
        html += '<span class="stat-label">No Transactions</span>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="stat-card stat-card-info">';
        html += '<i class="fas fa-exchange-alt stat-icon stat-icon-info"></i>';
        html += '<div class="stat-info">';
        html += `<span class="stat-value">${totalTransactions}</span>`;
        html += '<span class="stat-label">Total Transactions</span>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="stat-card stat-card-debit">';
        html += '<i class="fas fa-arrow-down stat-icon stat-icon-debit"></i>';
        html += '<div class="stat-info">';
        html += `<span class="stat-value">${this.formatCurrency(totalDebit)}</span>`;
        html += '<span class="stat-label">Total Debit</span>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="stat-card stat-card-credit">';
        html += '<i class="fas fa-arrow-up stat-icon stat-icon-credit"></i>';
        html += '<div class="stat-info">';
        html += `<span class="stat-value">${this.formatCurrency(totalCredit)}</span>`;
        html += '<span class="stat-label">Total Credit</span>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="stat-card stat-card-balance">';
        html += `<i class="fas fa-balance-scale stat-icon stat-icon-${balance >= 0 ? 'positive' : 'negative'}"></i>`;
        html += '<div class="stat-info">';
        html += `<span class="stat-value ${balance >= 0 ? 'balance-positive' : 'balance-negative'}">${this.formatCurrency(balance)}</span>`;
        html += '<span class="stat-label">Balance</span>';
        html += '</div>';
        html += '</div>';
        
        if (difference > 0) {
            html += '<div class="stat-card stat-card-warning">';
            html += '<i class="fas fa-exclamation-triangle stat-icon stat-icon-warning"></i>';
            html += '<div class="stat-info">';
            html += `<span class="stat-value">${this.formatCurrency(difference)}</span>`;
            html += '<span class="stat-label">Difference</span>';
            html += '</div>';
            html += '</div>';
        }
        
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getTrialBalanceStatusCards = function(reportData) {
        const accounts = reportData?.accounts || [];
        const totalDebit = parseFloat(reportData?.totals?.total_debit || 0);
        const totalCredit = parseFloat(reportData?.totals?.total_credit || 0);
        const difference = Math.abs(totalDebit - totalCredit);
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-balance-scale', accounts.length, 'Total Accounts');
        html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(totalDebit), 'Total Debit');
        html += this.createStatCard('credit', 'fa-arrow-up', this.formatCurrency(totalCredit), 'Total Credit');
        html += this.createStatCard('balance', 'fa-equals', this.formatCurrency(difference), 'Difference');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getIncomeStatementStatusCards = function(reportData) {
        const revenue = parseFloat(reportData?.totals?.total_revenue || 0);
        const expenses = parseFloat(reportData?.totals?.total_expenses || 0);
        const netIncome = revenue - expenses;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('success', 'fa-arrow-up', this.formatCurrency(revenue), 'Total Revenue');
        html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(expenses), 'Total Expenses');
        html += this.createStatCard(netIncome >= 0 ? 'success' : 'debit', 'fa-chart-line', this.formatCurrency(netIncome), 'Net Income');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getBalanceSheetStatusCards = function(reportData) {
        const assets = parseFloat(reportData?.totals?.total_assets || 0);
        const liabilities = parseFloat(reportData?.totals?.total_liabilities || 0);
        const equity = parseFloat(reportData?.totals?.total_equity || 0);
        const balance = Math.abs(assets - (liabilities + equity));
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('success', 'fa-building', this.formatCurrency(assets), 'Total Assets');
        html += this.createStatCard('debit', 'fa-file-invoice-dollar', this.formatCurrency(liabilities), 'Total Liabilities');
        html += this.createStatCard('info', 'fa-hand-holding-usd', this.formatCurrency(equity), 'Total Equity');
        html += this.createStatCard('balance', 'fa-balance-scale', this.formatCurrency(balance), 'Balance');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getCashFlowStatusCards = function(reportData) {
        // Check multiple possible keys for cash flow totals
        const operating = parseFloat(reportData?.totals?.operating_cash_flow || reportData?.totals?.operating_activities || 0);
        const investing = parseFloat(reportData?.totals?.investing_cash_flow || reportData?.totals?.investing_activities || 0);
        const financing = parseFloat(reportData?.totals?.financing_cash_flow || reportData?.totals?.financing_activities || 0);
        const netCash = parseFloat(reportData?.totals?.net_cash_flow || (operating + investing + financing));
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('info', 'fa-exchange-alt', this.formatCurrency(operating), 'Operating');
        html += this.createStatCard('primary', 'fa-chart-line', this.formatCurrency(investing), 'Investing');
        html += this.createStatCard('success', 'fa-money-bill-wave', this.formatCurrency(financing), 'Financing');
        html += this.createStatCard(netCash >= 0 ? 'success' : 'debit', 'fa-wallet', this.formatCurrency(netCash), 'Net Cash Flow');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getAgedReceivablesStatusCards = function(reportData) {
        const receivables = reportData?.receivables || [];
        const totalOutstanding = parseFloat(reportData?.total_outstanding || 0);
        const current = receivables.filter(r => parseInt(r.days_overdue || 0) <= 30).length;
        const overdue = receivables.filter(r => parseInt(r.days_overdue || 0) > 30).length;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-file-invoice-dollar', receivables.length, 'Total Receivables');
        html += this.createStatCard('success', 'fa-check-circle', current, 'Current');
        html += this.createStatCard('warning', 'fa-exclamation-triangle', overdue, 'Overdue');
        html += this.createStatCard('debit', 'fa-dollar-sign', this.formatCurrency(totalOutstanding), 'Outstanding');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getAgedPayablesStatusCards = function(reportData) {
        const payables = reportData?.payables || [];
        const totalOutstanding = parseFloat(reportData?.total_outstanding || 0);
        const current = payables.filter(p => parseInt(p.days_overdue || 0) <= 30).length;
        const overdue = payables.filter(p => parseInt(p.days_overdue || 0) > 30).length;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-file-invoice', payables.length, 'Total Payables');
        html += this.createStatCard('success', 'fa-check-circle', current, 'Current');
        html += this.createStatCard('warning', 'fa-exclamation-triangle', overdue, 'Overdue');
        html += this.createStatCard('credit', 'fa-dollar-sign', this.formatCurrency(totalOutstanding), 'Outstanding');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getCashBookStatusCards = function(reportData) {
        const transactions = reportData?.transactions || [];
        const totalDebit = parseFloat(reportData?.totals?.total_debit || 0);
        const totalCredit = parseFloat(reportData?.totals?.total_credit || 0);
        const closingBalance = parseFloat(reportData?.totals?.closing_balance || 0);
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('info', 'fa-exchange-alt', transactions.length, 'Transactions');
        html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(totalDebit), 'Total Debit');
        html += this.createStatCard('credit', 'fa-arrow-up', this.formatCurrency(totalCredit), 'Total Credit');
        html += this.createStatCard('balance', 'fa-wallet', this.formatCurrency(closingBalance), 'Closing Balance');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getBankBookStatusCards = function(reportData) {
        const transactions = reportData?.transactions || [];
        const totalDebit = parseFloat(reportData?.totals?.total_debit || 0);
        const totalCredit = parseFloat(reportData?.totals?.total_credit || 0);
        const closingBalance = parseFloat(reportData?.totals?.closing_balance || 0);
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('info', 'fa-university', transactions.length, 'Transactions');
        html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(totalDebit), 'Total Debit');
        html += this.createStatCard('credit', 'fa-arrow-up', this.formatCurrency(totalCredit), 'Total Credit');
        html += this.createStatCard('balance', 'fa-university', this.formatCurrency(closingBalance), 'Closing Balance');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getAccountStatementStatusCards = function(reportData) {
        const transactions = reportData?.accounts?.[0]?.transactions || [];
        const totalDebit = parseFloat(reportData?.totals?.total_debit || 0);
        const totalCredit = parseFloat(reportData?.totals?.total_credit || 0);
        const balance = parseFloat(reportData?.totals?.balance || 0);
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('info', 'fa-list', transactions.length, 'Transactions');
        html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(totalDebit), 'Total Debit');
        html += this.createStatCard('credit', 'fa-arrow-up', this.formatCurrency(totalCredit), 'Total Credit');
        html += this.createStatCard('balance', 'fa-balance-scale', this.formatCurrency(balance), 'Balance');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getExpenseStatementStatusCards = function(reportData) {
        const expenses = reportData?.expenses || [];
        const totalExpenses = parseFloat(reportData?.totals?.total_expenses || 0);
        const categories = new Set(expenses.map(e => e.category || 'Uncategorized')).size;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-list', expenses.length, 'Expenses');
        html += this.createStatCard('info', 'fa-tags', categories, 'Categories');
        html += this.createStatCard('debit', 'fa-dollar-sign', this.formatCurrency(totalExpenses), 'Total Expenses');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getChartOfAccountsStatusCards = function(reportData) {
        const accounts = reportData?.accounts || [];
        const active = accounts.filter(a => a.is_active !== false).length;
        const inactive = accounts.length - active;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-book', accounts.length, 'Total Accounts');
        html += this.createStatCard('success', 'fa-check-circle', active, 'Active');
        html += this.createStatCard('warning', 'fa-times-circle', inactive, 'Inactive');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getValueAddedStatusCards = function(reportData) {
        const revenue = parseFloat(reportData?.revenue || 0);
        const cogs = parseFloat(reportData?.cogs || 0);
        const valueAdded = revenue - cogs;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('success', 'fa-arrow-up', this.formatCurrency(revenue), 'Revenue');
        html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(cogs), 'COGS');
        html += this.createStatCard('info', 'fa-plus-circle', this.formatCurrency(valueAdded), 'Value Added');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getFixedAssetsStatusCards = function(reportData) {
        const assets = reportData?.assets || [];
        const totalValue = parseFloat(reportData?.totals?.total_value || 0);
        const totalDepreciation = parseFloat(reportData?.totals?.total_depreciation || 0);
        const netValue = totalValue - totalDepreciation;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-building', assets.length, 'Assets');
        html += this.createStatCard('success', 'fa-dollar-sign', this.formatCurrency(totalValue), 'Total Value');
        html += this.createStatCard('warning', 'fa-arrow-down', this.formatCurrency(totalDepreciation), 'Depreciation');
        html += this.createStatCard('info', 'fa-calculator', this.formatCurrency(netValue), 'Net Value');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getEntriesByYearStatusCards = function(reportData) {
        const entries = reportData?.entries || [];
        const years = new Set(entries.map(e => e.year || 'Unknown')).size;
        const totalEntries = entries.reduce((sum, e) => sum + (e.count || 0), 0);
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-calendar-alt', years, 'Years');
        html += this.createStatCard('info', 'fa-list', totalEntries, 'Total Entries');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getCustomerDebitsStatusCards = function(reportData) {
        const debits = reportData?.debits || [];
        const totalDebit = parseFloat(reportData?.totals?.total_debit || 0);
        const customers = new Set(debits.map(d => d.customer_id || d.customer_name)).size;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-users', customers, 'Customers');
        html += this.createStatCard('info', 'fa-list', debits.length, 'Debits');
        html += this.createStatCard('debit', 'fa-dollar-sign', this.formatCurrency(totalDebit), 'Total Debit');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getStatisticalPositionStatusCards = function(reportData) {
        const accounts = reportData?.statistics?.total_accounts || 0;
        const transactions = reportData?.statistics?.total_transactions || 0;
        const receivables = reportData?.statistics?.total_receivables || 0;
        const payables = reportData?.statistics?.total_payables || 0;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('primary', 'fa-book', accounts, 'Accounts');
        html += this.createStatCard('info', 'fa-exchange-alt', transactions, 'Transactions');
        html += this.createStatCard('success', 'fa-file-invoice-dollar', receivables, 'Receivables');
        html += this.createStatCard('warning', 'fa-file-invoice', payables, 'Payables');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getChangesInEquityStatusCards = function(reportData) {
        const changes = reportData?.equity_changes || [];
        const opening = parseFloat(reportData?.totals?.opening_equity || 0);
        const closing = parseFloat(reportData?.totals?.closing_equity || 0);
        const netChange = closing - opening;
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('info', 'fa-arrow-left', this.formatCurrency(opening), 'Opening');
        html += this.createStatCard('primary', 'fa-list', changes.length, 'Changes');
        html += this.createStatCard('success', 'fa-arrow-right', this.formatCurrency(closing), 'Closing');
        html += this.createStatCard(netChange >= 0 ? 'success' : 'debit', 'fa-chart-line', this.formatCurrency(netChange), 'Net Change');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getFinancialPerformanceStatusCards = function(reportData) {
        const revenue = parseFloat(reportData?.performance_data?.revenue || 0);
        const expenses = parseFloat(reportData?.performance_data?.expenses || 0);
        const netIncome = revenue - expenses;
        const profitMargin = revenue > 0 ? ((netIncome / revenue) * 100).toFixed(1) + '%' : '0%';
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('success', 'fa-arrow-up', this.formatCurrency(revenue), 'Revenue');
        html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(expenses), 'Expenses');
        html += this.createStatCard(netIncome >= 0 ? 'success' : 'debit', 'fa-chart-line', this.formatCurrency(netIncome), 'Net Income');
        html += this.createStatCard('info', 'fa-percentage', profitMargin, 'Profit Margin');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.getComparativeReportStatusCards = function(reportData) {
        const currentRevenue = parseFloat(reportData?.current_period?.revenue || 0);
        const previousRevenue = parseFloat(reportData?.previous_period?.revenue || 0);
        const revenueChange = currentRevenue - previousRevenue;
        const revenueChangePercent = previousRevenue > 0 ? ((revenueChange / previousRevenue) * 100).toFixed(1) + '%' : '0%';
        
        let html = '<div class="report-status-cards">';
        html += this.createStatCard('success', 'fa-calendar-check', this.formatCurrency(currentRevenue), 'Current Revenue');
        html += this.createStatCard('info', 'fa-calendar', this.formatCurrency(previousRevenue), 'Previous Revenue');
        html += this.createStatCard(revenueChange >= 0 ? 'success' : 'debit', 'fa-chart-line', this.formatCurrency(revenueChange), 'Change');
        html += this.createStatCard('primary', 'fa-percentage', revenueChangePercent, 'Change %');
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.createStatCard = function(type, icon, value, label) {
        const typeClass = `stat-card-${type}`;
        const iconClass = `stat-icon-${type}`;
        return `<div class="stat-card ${typeClass}">
            <i class="fas ${icon} stat-icon ${iconClass}"></i>
            <div class="stat-info">
                <span class="stat-value">${value}</span>
                <span class="stat-label">${label}</span>
            </div>
        </div>`;
    }

ProfessionalAccounting.prototype.formatGeneralLedgerReport = function(reportData) {
        let html = '<div class="professional-report-sections">';
        
        const allAccounts = reportData?.accounts || [];
        
        if (Array.isArray(allAccounts) && allAccounts.length > 0) {
            // Apply search filter if search term exists
            let filteredAccounts = allAccounts;
            if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
                const searchTerm = this.reportSearchTerm.toLowerCase().trim();
                filteredAccounts = allAccounts.filter(account => {
                    const accountCode = (account.account_code || '').toLowerCase();
                    const accountName = (account.account_name || '').toLowerCase();
                    const hasMatchingTransaction = (account.transactions || []).some(txn => {
                        const description = (txn.description || '').toLowerCase();
                        const reference = (txn.reference_number || '').toLowerCase();
                        return description.includes(searchTerm) || reference.includes(searchTerm);
                    });
                    return accountCode.includes(searchTerm) || 
                           accountName.includes(searchTerm) || 
                           hasMatchingTransaction;
                });
            }
            
            // Apply pagination to filtered accounts (if perPage is 999999, show all)
            let paginatedAccounts = filteredAccounts;
            if (this.reportPerPage < 999999) {
                const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
                const endIndex = startIndex + this.reportPerPage;
                paginatedAccounts = filteredAccounts.slice(startIndex, endIndex);
            }
            
            // Update total count for pagination (use filtered count)
            this.reportTotalCount = filteredAccounts.length;
            
            paginatedAccounts.forEach((account, accIndex) => {
                html += `<div class="report-section">`;
                html += `<div class="report-section-header">`;
                html += `<h4><code>${this.escapeHtml(account.account_code || '')}</code> ${this.escapeHtml(account.account_name || '')}</h4>`;
                html += `</div>`;
                html += '<div class="professional-report-table-wrapper">';
                html += '<table class="professional-report-table">';
                html += '<thead>';
                html += '<tr>';
                html += '<th class="report-col-date">Date</th>';
                html += '<th class="report-col-name">Description</th>';
                html += '<th class="report-col-name">Reference</th>';
                html += '<th class="report-col-type">Type</th>';
                html += '<th class="report-col-debit text-right">Debit</th>';
                html += '<th class="report-col-credit text-right">Credit</th>';
                html += '<th class="report-col-balance text-right">Balance</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';
                
                const transactions = account.transactions || [];
                if (transactions.length > 0) {
                    let runningBalance = 0;
                    transactions.forEach((txn, txnIndex) => {
                        const debit = parseFloat(txn.debit_amount || 0);
                        const credit = parseFloat(txn.credit_amount || 0);
                        runningBalance += (debit - credit);
                        const balanceClass = runningBalance >= 0 ? 'balance-positive' : 'balance-negative';
                        html += `<tr class="report-data-row ${txnIndex % 2 === 0 ? 'even' : 'odd'}">`;
                        html += `<td class="report-col-date">${this.formatDate(txn.transaction_date || '')}</td>`;
                        html += `<td class="report-col-name">${this.escapeHtml(txn.description || '')}</td>`;
                        html += `<td class="report-col-name"><code>${this.escapeHtml(txn.reference_number || '')}</code></td>`;
                        html += `<td class="report-col-type"><span class="type-badge type-badge-${(txn.transaction_type || '').toLowerCase()}">${this.escapeHtml(txn.transaction_type || '')}</span></td>`;
                        html += `<td class="report-col-debit text-right debit-cell">${debit > 0 ? this.formatCurrency(debit) : '-'}</td>`;
                        html += `<td class="report-col-credit text-right credit-cell">${credit > 0 ? this.formatCurrency(credit) : '-'}</td>`;
                        html += `<td class="report-col-balance text-right ${balanceClass}">${this.formatCurrency(runningBalance)}</td>`;
                        html += '</tr>';
                    });
                    
                    html += '<tr class="report-totals-row">';
                    html += '<td colspan="4" class="report-totals-label"><strong>Account Totals:</strong></td>';
                    html += `<td class="report-col-debit text-right debit-cell"><strong>${this.formatCurrency(parseFloat(account.total_debit || 0))}</strong></td>`;
                    html += `<td class="report-col-credit text-right credit-cell"><strong>${this.formatCurrency(parseFloat(account.total_credit || 0))}</strong></td>`;
                    html += `<td class="report-col-balance text-right"><strong>${this.formatCurrency(parseFloat(account.balance || 0))}</strong></td>`;
                    html += '</tr>';
                } else {
                    html += '<tr><td colspan="7" class="text-center report-empty-state">No transactions</td></tr>';
                }
                
                html += '</tbody></table></div></div>';
            });
            
            // Add overall totals footer if available
            if (reportData?.totals) {
                html += '<div class="report-totals-summary">';
                html += '<table class="professional-report-table">';
                html += '<tfoot class="report-totals-footer">';
                html += '<tr class="report-totals-row">';
                html += '<td colspan="4" class="report-totals-label"><strong>GRAND TOTALS:</strong></td>';
                html += `<td class="report-col-debit text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_debit || 0))}</strong></td>`;
                html += `<td class="report-col-credit text-right credit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_credit || 0))}</strong></td>`;
                html += `<td class="report-col-balance text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.difference || 0))}</strong></td>`;
                html += '</tr>';
                html += '</tfoot>';
                html += '</table>';
                html += '</div>';
            }
        } else {
            html += '<div class="report-empty-state">';
            html += '<i class="fas fa-info-circle report-empty-icon"></i>';
            html += '<h3>No Accounts Found</h3>';
            
            // Check if there's debug info
            if (reportData?.debug) {
                if (reportData.debug.message === 'financial_accounts table does not exist') {
                    html += '<p class="report-empty-text">The financial accounts table does not exist in the database. Please ensure the accounting system is properly set up.</p>';
                } else if (reportData.debug.message === 'Failed to query financial_accounts table') {
                    html += '<p class="report-empty-text">Unable to query the accounts table. Database error: ' + this.escapeHtml(reportData.debug.error || 'Unknown error') + '</p>';
                } else if (reportData.debug.message === 'Query executed successfully but returned 0 accounts') {
                    html += '<p class="report-empty-text">No accounts found in the system. Please create accounts first to generate the General Ledger report.</p>';
                } else {
                    html += '<p class="report-empty-text">No accounts found in the system. Please create accounts first to generate the General Ledger report.</p>';
                }
            } else {
                html += '<p class="report-empty-text">No accounts found in the system. Please create accounts first to generate the General Ledger report.</p>';
            }
            html += '</div>';
        }
        
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.formatExpenseStatement = function(reportData) {
        // Get all expenses and apply search filter
        let allExpenses = reportData?.expenses || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allExpenses = allExpenses.filter(expense => {
                const category = (expense.category || '').toLowerCase();
                const description = (expense.description || '').toLowerCase();
                const vendor = (expense.vendor_name || '').toLowerCase();
                return category.includes(searchTerm) || description.includes(searchTerm) || vendor.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allExpenses.length;
        
        // Get paginated expenses (if perPage is 999999, show all)
        let paginatedExpenses = allExpenses;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedExpenses = allExpenses.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-name">Category</th>';
        html += '<th class="report-col-name">Description</th>';
        html += '<th class="report-col-date">Date</th>';
        html += '<th class="report-col-amount text-right">Amount</th>';
        html += '<th class="report-col-days text-right">Count</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (Array.isArray(paginatedExpenses) && paginatedExpenses.length > 0) {
            paginatedExpenses.forEach((expense, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-name"><span class="badge badge-info">${this.escapeHtml(expense.category || 'Uncategorized')}</span></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(expense.description || '')}</td>`;
                html += `<td class="report-col-date">${this.formatDate(expense.transaction_date || '')}</td>`;
                html += `<td class="report-col-amount text-right debit-cell">${this.formatCurrency(parseFloat(expense.total_amount || 0))}</td>`;
                html += `<td class="report-col-days text-right">${expense.transaction_count || 1}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="5" class="text-center report-empty-state">No expenses found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="3" class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-amount text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_expenses || 0))}</strong></td>`;
            html += `<td class="report-col-days text-right"><strong>${reportData.totals.transaction_count || 0}</strong></td>`;
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }

ProfessionalAccounting.prototype.formatAccountStatement = function(reportData) {
        // Check if there's a message (e.g., account not selected)
        if (reportData?.message) {
            return `
                <div class="report-empty-state">
                    <i class="fas fa-info-circle report-empty-icon"></i>
                    <h3>${this.escapeHtml(reportData.message)}</h3>
                    <p class="report-empty-text">Please select an account from the filter above to generate the statement.</p>
                </div>
            `;
        }
        // Similar to General Ledger but for a specific account
        return this.formatGeneralLedgerReport(reportData);
    }

ProfessionalAccounting.prototype.formatValueAdded = function(reportData) {
        // Get all data and apply search filter
        let allData = reportData?.data || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allData = allData.filter(item => {
                const itemName = (item.item || item.name || '').toLowerCase();
                return itemName.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allData.length;
        
        // Get paginated data (if perPage is 999999, show all)
        let paginatedData = allData;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedData = allData.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-code">Account Code</th>';
        html += '<th class="report-col-name">Account Name</th>';
        html += '<th class="report-col-name">Type</th>';
        html += '<th class="report-col-amount text-right">Amount</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedData.length > 0) {
            paginatedData.forEach((item, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-code"><code>${this.escapeHtml(item.account_code || '')}</code></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(item.account_name || '')}</td>`;
                html += `<td class="report-col-name"><span class="badge badge-info">${this.escapeHtml(item.type || '')}</span></td>`;
                html += `<td class="report-col-amount text-right ${item.type === 'Revenue' ? 'credit-cell' : 'debit-cell'}">${this.formatCurrency(parseFloat(item.amount || 0))}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="4" class="text-center report-empty-state">No value added data available</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="3" class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-amount text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.value_added || 0))}</strong></td>`;
            html += '</tr>';
            if (reportData.totals.value_added_percentage !== undefined) {
                html += '<tr class="report-totals-row">';
                html += '<td colspan="3" class="report-totals-label"><strong>Value Added Percentage:</strong></td>';
                html += `<td class="report-col-amount text-right"><strong>${parseFloat(reportData.totals.value_added_percentage || 0).toFixed(2)}%</strong></td>`;
                html += '</tr>';
            }
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }

ProfessionalAccounting.prototype.formatFixedAssets = function(reportData) {
        // Get all assets and apply search filter
        let allAssets = reportData?.assets || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allAssets = allAssets.filter(asset => {
                const accountCode = (asset.account_code || '').toLowerCase();
                const accountName = (asset.account_name || '').toLowerCase();
                return accountCode.includes(searchTerm) || accountName.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allAssets.length;
        
        // Get paginated assets (if perPage is 999999, show all)
        let paginatedAssets = allAssets;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedAssets = allAssets.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-code">Account Code</th>';
        html += '<th class="report-col-name">Account Name</th>';
        html += '<th class="report-col-balance text-right">Balance</th>';
        html += '<th class="report-col-name">Description</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedAssets.length > 0) {
            paginatedAssets.forEach((asset, index) => {
                const balance = parseFloat(asset.balance || 0);
                const balanceClass = balance >= 0 ? 'balance-positive' : 'balance-negative';
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-code"><code>${this.escapeHtml(asset.account_code || '')}</code></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(asset.account_name || '')}</td>`;
                html += `<td class="report-col-balance text-right ${balanceClass}">${this.formatCurrency(balance)}</td>`;
                html += `<td class="report-col-name">${this.escapeHtml(asset.description || '')}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="4" class="text-center report-empty-state">No fixed assets found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="2" class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-balance text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_assets || 0))}</strong></td>`;
            html += `<td class="report-col-name"><strong>${reportData.totals.asset_count || 0} assets</strong></td>`;
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }

ProfessionalAccounting.prototype.formatEntriesByYear = function(reportData) {
        // Get all data and apply search filter
        let allData = reportData?.data || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allData = allData.filter(item => {
                const year = String(item.year || '').toLowerCase();
                return year.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allData.length;
        
        // Get paginated data (if perPage is 999999, show all)
        let paginatedData = allData;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedData = allData.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-period">Year</th>';
        html += '<th class="report-col-days text-right">Entry Count</th>';
        html += '<th class="report-col-amount text-right">Total Amount</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedData.length > 0) {
            paginatedData.forEach((item, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-period"><strong>${this.escapeHtml(String(item.year || ''))}</strong></td>`;
                html += `<td class="report-col-days text-right">${item.entry_count || 0}</td>`;
                html += `<td class="report-col-amount text-right">${this.formatCurrency(parseFloat(item.total_amount || 0))}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="3" class="text-center report-empty-state">No entries found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-days text-right"><strong>${reportData.totals.total_entries || 0}</strong></td>`;
            html += `<td class="report-col-amount text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_amount || 0))}</strong></td>`;
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }

ProfessionalAccounting.prototype.formatCustomerDebits = function(reportData) {
        // Get all customers and apply search filter
        let allCustomers = reportData?.customers || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allCustomers = allCustomers.filter(customer => {
                const customerName = (customer.customer_name || '').toLowerCase();
                const customerId = String(customer.customer_id || '').toLowerCase();
                return customerName.includes(searchTerm) || customerId.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allCustomers.length;
        
        // Get paginated customers (if perPage is 999999, show all)
        let paginatedCustomers = allCustomers;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedCustomers = allCustomers.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-customer">Customer</th>';
        html += '<th class="report-col-days text-right">Invoice Count</th>';
        html += '<th class="report-col-amount text-right">Total Invoiced</th>';
        html += '<th class="report-col-amount text-right">Total Paid</th>';
        html += '<th class="report-col-amount text-right">Total Debit</th>';
        html += '<th class="report-col-days text-right">Overdue Count</th>';
        html += '<th class="report-col-date">Latest Due Date</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedCustomers.length > 0) {
            paginatedCustomers.forEach((customer, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-customer">${this.escapeHtml(customer.customer_name || 'N/A')}</td>`;
                html += `<td class="report-col-days text-right">${customer.invoice_count || 0}</td>`;
                html += `<td class="report-col-amount text-right">${this.formatCurrency(parseFloat(customer.total_invoiced || 0))}</td>`;
                html += `<td class="report-col-amount text-right credit-cell">${this.formatCurrency(parseFloat(customer.total_paid || 0))}</td>`;
                html += `<td class="report-col-amount text-right debit-cell">${this.formatCurrency(parseFloat(customer.total_debit || 0))}</td>`;
                html += `<td class="report-col-days text-right ${customer.overdue_count > 0 ? 'overdue-badge' : ''}">${customer.overdue_count || 0}</td>`;
                html += `<td class="report-col-date">${customer.latest_due_date ? this.formatDate(customer.latest_due_date) : '-'}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="7" class="text-center report-empty-state">No customer debits found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td class="report-totals-label"><strong>TOTALS</strong></td>';
            html += `<td class="report-col-days text-right"><strong>${reportData.totals.total_customers || 0}</strong></td>`;
            html += `<td class="report-col-amount text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_invoiced || 0))}</strong></td>`;
            html += `<td class="report-col-amount text-right credit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_paid || 0))}</strong></td>`;
            html += `<td class="report-col-amount text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_debit || 0))}</strong></td>`;
            html += '<td colspan="2"></td>';
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }

ProfessionalAccounting.prototype.formatStatisticalPosition = function(reportData) {
        // Get all data and apply search filter
        let allData = reportData?.data || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allData = allData.filter(item => {
                const itemName = (item.item || item.name || item.category || '').toLowerCase();
                return itemName.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allData.length;
        
        // Get paginated data (if perPage is 999999, show all)
        let paginatedData = allData;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedData = allData.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-name">Category</th>';
        html += '<th class="report-col-name">Metric</th>';
        html += '<th class="report-col-amount text-right">Value</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedData.length > 0) {
            paginatedData.forEach((item, index) => {
                let displayValue = item.value;
                // Format numbers appropriately
                if (typeof item.value === 'number') {
                    if (item.value > 1000 || (item.value.toString().includes('.') && item.value < 1)) {
                        displayValue = this.formatCurrency(item.value);
                    } else {
                        displayValue = item.value.toLocaleString();
                    }
                } else {
                    displayValue = this.escapeHtml(String(item.value || ''));
                }
                
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-name"><strong>${this.escapeHtml(item.category || '')}</strong></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(item.metric || '')}</td>`;
                html += `<td class="report-col-amount text-right">${displayValue}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="3" class="text-center report-empty-state">No statistical data available</td></tr>';
        }
        
        html += '</tbody></table></div>';
        return html;
    }

ProfessionalAccounting.prototype.formatChangesInEquity = function(reportData) {
        // Get paginated data - prefer data array, fallback to equity_changes
        let allData = reportData?.data || [];
        if (allData.length === 0 && reportData?.equity_changes) {
            // Flatten equity_changes for display
            reportData.equity_changes.forEach(equity => {
                if (equity.monthly_changes && equity.monthly_changes.length > 0) {
                    equity.monthly_changes.forEach(change => {
                        allData.push({
                            account_code: equity.account_code,
                            account_name: equity.account_name,
                            period: change.period,
                            change_amount: change.change_amount,
                            current_balance: equity.current_balance
                        });
                    });
                } else {
                    // If no monthly changes, show current balance
                    allData.push({
                        account_code: equity.account_code,
                        account_name: equity.account_name,
                        period: 'Current',
                        change_amount: 0,
                        current_balance: equity.current_balance
                    });
                }
            });
        }
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allData = allData.filter(item => {
                const accountCode = (item.account_code || '').toLowerCase();
                const accountName = (item.account_name || '').toLowerCase();
                const period = (item.period || '').toLowerCase();
                return accountCode.includes(searchTerm) || accountName.includes(searchTerm) || period.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allData.length;
        
        // Get paginated data (if perPage is 999999, show all)
        let paginatedData = allData;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedData = allData.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-code">Account Code</th>';
        html += '<th class="report-col-name">Account Name</th>';
        html += '<th class="report-col-period">Period</th>';
        html += '<th class="report-col-amount text-right">Change Amount</th>';
        html += '<th class="report-col-amount text-right">Current Balance</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedData.length > 0) {
            paginatedData.forEach((item, index) => {
                const changeAmount = parseFloat(item.change_amount || 0);
                const balance = parseFloat(item.current_balance || 0);
                const changeClass = changeAmount >= 0 ? 'credit-cell' : 'debit-cell';
                const balanceClass = balance >= 0 ? 'balance-positive' : 'balance-negative';
                
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-code"><code>${this.escapeHtml(item.account_code || '')}</code></td>`;
                html += `<td class="report-col-name">${this.escapeHtml(item.account_name || '')}</td>`;
                html += `<td class="report-col-period">${this.escapeHtml(item.period || '')}</td>`;
                html += `<td class="report-col-amount text-right ${changeClass}">${this.formatCurrency(changeAmount)}</td>`;
                html += `<td class="report-col-amount text-right ${balanceClass}">${this.formatCurrency(balance)}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="5" class="text-center report-empty-state">No equity changes found</td></tr>';
        }
        
        html += '</tbody>';
        
        if (reportData?.totals) {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            html += '<td colspan="3" class="report-totals-label"><strong>TOTALS</strong></td>';
            html += '<td></td>';
            html += `<td class="report-col-amount text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_equity || 0))}</strong></td>`;
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div>';
        return html;
    }

ProfessionalAccounting.prototype.formatFinancialPerformance = function(reportData) {
        // Get all performance data and apply search filter
        let performanceData = reportData?.performance_data || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            performanceData = performanceData.filter(item => {
                const metric = (item.metric || item.name || '').toLowerCase();
                return metric.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = performanceData.length;
        
        const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
        const endIndex = startIndex + this.reportPerPage;
        const paginatedData = performanceData.slice(startIndex, endIndex);
        
        let html = '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-name">Metric</th>';
        html += '<th class="report-col-amount text-right">Value</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedData.length > 0) {
            paginatedData.forEach((item, index) => {
                let displayValue = '';
                if (item.type === 'currency') {
                    displayValue = this.formatCurrency(parseFloat(item.value || 0));
                } else if (item.type === 'percentage') {
                    displayValue = parseFloat(item.value || 0).toFixed(2) + '%';
                } else if (item.type === 'ratio') {
                    displayValue = parseFloat(item.value || 0).toFixed(2);
                } else {
                    displayValue = this.escapeHtml(String(item.value || ''));
                }
                
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-name"><strong>${this.escapeHtml(item.metric || '')}</strong></td>`;
                html += `<td class="report-col-amount text-right">${displayValue}</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="2" class="text-center report-empty-state">No performance data available</td></tr>';
        }
        
        html += '</tbody></table></div>';
        return html;
    }

ProfessionalAccounting.prototype.formatComparativeReport = function(reportData) {
        // Get all data and apply search filter
        let allData = reportData?.data || [];
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allData = allData.filter(item => {
                const itemName = (item.item || item.name || '').toLowerCase();
                return itemName.includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = allData.length;
        
        // Get paginated data (if perPage is 999999, show all)
        let paginatedData = allData;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedData = allData.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-sections">';
        
        // Period labels
        if (reportData?.periods && reportData.periods.length >= 2) {
            html += '<div class="report-section-header">';
            html += `<h4>Comparing: ${reportData.periods[0].label} vs ${reportData.periods[1].label}</h4>`;
            html += '</div>';
        }
        
        html += '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead>';
        html += '<tr>';
        html += '<th class="report-col-name">Item</th>';
        html += '<th class="report-col-amount text-right">Previous Period</th>';
        html += '<th class="report-col-amount text-right">Current Period</th>';
        html += '<th class="report-col-amount text-right">Change</th>';
        html += '<th class="report-col-amount text-right">Change %</th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';
        
        if (paginatedData.length > 0) {
            paginatedData.forEach((item, index) => {
                const change = parseFloat(item.change || 0);
                const changePercent = parseFloat(item.change_percentage || 0);
                const changeClass = change >= 0 ? 'credit-cell' : 'debit-cell';
                const percentClass = changePercent >= 0 ? 'credit-cell' : 'debit-cell';
                
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                html += `<td class="report-col-name"><strong>${this.escapeHtml(item.item || '')}</strong></td>`;
                html += `<td class="report-col-amount text-right">${this.formatCurrency(parseFloat(item.previous_period || 0))}</td>`;
                html += `<td class="report-col-amount text-right">${this.formatCurrency(parseFloat(item.current_period || 0))}</td>`;
                html += `<td class="report-col-amount text-right ${changeClass}">${this.formatCurrency(change)}</td>`;
                html += `<td class="report-col-amount text-right ${percentClass}">${changePercent.toFixed(2)}%</td>`;
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="5" class="text-center report-empty-state">No comparison data available</td></tr>';
        }
        
        html += '</tbody></table></div></div>';
        return html;
    }

ProfessionalAccounting.prototype.formatGenericReport = function(reportData, reportName = 'Report') {
        // Try multiple data sources
        let allData = reportData.data || reportData.assets || reportData.customers || 
                     reportData.receivables || reportData.payables || 
                     reportData.transactions || reportData.expenses ||
                     reportData.performance_data || reportData.comparisons || 
                     reportData.equity_changes || [];
        
        // If still empty, check if it's an array directly
        if (!Array.isArray(allData) && typeof reportData === 'object') {
            // Try to find any array property
            for (const key in reportData) {
                if (Array.isArray(reportData[key]) && reportData[key].length > 0) {
                    allData = reportData[key];
                    break;
                }
            }
        }
        
        // Apply search filter if search term exists
        if (Array.isArray(allData) && this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            allData = allData.filter(item => {
                if (typeof item === 'object' && item !== null) {
                    return Object.values(item).some(val => 
                        String(val || '').toLowerCase().includes(searchTerm)
                    );
                }
                return String(item || '').toLowerCase().includes(searchTerm);
            });
        }
        
        // Update total count for pagination
        this.reportTotalCount = Array.isArray(allData) ? allData.length : 0;
        
        const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
        const endIndex = startIndex + this.reportPerPage;
        // Get paginated data (if perPage is 999999, show all)
        let paginatedData = Array.isArray(allData) ? allData : [];
        if (this.reportPerPage < 999999 && Array.isArray(allData)) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedData = allData.slice(startIndex, endIndex);
        }
        
        let html = '<div class="professional-report-sections">';
        html += '<div class="report-section">';
        html += '<div class="professional-report-table-wrapper">';
        html += '<table class="professional-report-table">';
        html += '<thead><tr>';
        
        // Try to detect columns from data
        if (reportData.columns && Array.isArray(reportData.columns)) {
            reportData.columns.forEach(col => {
                html += `<th>${this.escapeHtml(String(col))}</th>`;
            });
        } else if (paginatedData.length > 0 && typeof paginatedData[0] === 'object') {
            // Use first row keys as columns
            Object.keys(paginatedData[0]).forEach(key => {
                const formattedKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                html += `<th>${this.escapeHtml(formattedKey)}</th>`;
            });
        } else if (Array.isArray(allData) && allData.length > 0 && typeof allData[0] === 'object') {
            // Fallback to allData if paginatedData is empty
            Object.keys(allData[0]).forEach(key => {
                const formattedKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                html += `<th>${this.escapeHtml(formattedKey)}</th>`;
            });
        } else {
            html += '<th>Data</th>';
        }
        
        html += '</tr></thead><tbody>';
        
        if (paginatedData.length > 0) {
            paginatedData.forEach((row, index) => {
                html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                if (typeof row === 'object') {
                    Object.values(row).forEach(val => {
                        // Format currency if it looks like a number
                        let displayVal = val !== null && val !== undefined ? String(val) : '';
                        if (typeof val === 'number' && (val.toString().includes('.') || Math.abs(val) > 100)) {
                            displayVal = this.formatCurrency(val);
                        } else if (typeof val === 'string' && /^\d+\.?\d*$/.test(val.trim()) && parseFloat(val) > 100) {
                            displayVal = this.formatCurrency(parseFloat(val));
                        } else {
                            displayVal = this.escapeHtml(displayVal);
                        }
                        html += `<td>${displayVal}</td>`;
                    });
                } else {
                    html += `<td>${this.escapeHtml(String(row))}</td>`;
                }
                html += '</tr>';
            });
        } else {
            html += '<tr><td colspan="100%" class="text-center report-empty-state">No data available</td></tr>';
        }
        
        html += '</tbody>';
        
        // Add totals footer if available
        if (reportData.totals && typeof reportData.totals === 'object') {
            html += '<tfoot class="report-totals-footer">';
            html += '<tr class="report-totals-row">';
            const colCount = reportData.columns?.length || (paginatedData.length > 0 && typeof paginatedData[0] === 'object' ? Object.keys(paginatedData[0]).length : (Array.isArray(allData) && allData.length > 0 && typeof allData[0] === 'object' ? Object.keys(allData[0]).length : 1));
            html += `<td colspan="${Math.max(1, colCount - 1)}" class="report-totals-label"><strong>TOTALS</strong></td>`;
            // Try to find a total amount field
            const totalField = Object.keys(reportData.totals).find(k => k.toLowerCase().includes('total'));
            if (totalField) {
                html += `<td class="report-col-amount text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals[totalField] || 0))}</strong></td>`;
            } else {
                html += '<td></td>';
            }
            html += '</tr>';
            html += '</tfoot>';
        }
        
        html += '</table></div></div></div>';
        return html;
    }

ProfessionalAccounting.prototype.formatChartOfAccounts = function(reportData) {
        let html = '<div class="professional-report-sections">';
        
        const grouped = reportData?.grouped || {};
        let categories = Object.keys(grouped).sort();
        
        // Apply search filter if search term exists
        if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
            const searchTerm = this.reportSearchTerm.toLowerCase().trim();
            categories = categories.filter(category => {
                const accounts = grouped[category] || [];
                return category.toLowerCase().includes(searchTerm) || 
                       accounts.some(acc => {
                           const code = (acc.account_code || '').toLowerCase();
                           const name = (acc.account_name || '').toLowerCase();
                           return code.includes(searchTerm) || name.includes(searchTerm);
                       });
            });
        }
        
        // Calculate total accounts for pagination
        let totalAccounts = 0;
        categories.forEach(category => {
            totalAccounts += (grouped[category] || []).length;
        });
        this.reportTotalCount = totalAccounts;
        
        // Apply pagination to categories (if perPage is 999999, show all)
        let paginatedCategories = categories;
        if (this.reportPerPage < 999999) {
            const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
            const endIndex = startIndex + this.reportPerPage;
            paginatedCategories = categories.slice(startIndex, endIndex);
        }
        
        if (paginatedCategories.length > 0) {
            paginatedCategories.forEach((category, catIndex) => {
                html += `<div class="report-section">`;
                html += `<div class="report-section-header">`;
                html += `<h4><i class="fas fa-folder"></i> ${this.escapeHtml(category)}</h4>`;
                html += `</div>`;
                html += '<div class="professional-report-table-wrapper">';
                html += '<table class="professional-report-table">';
                html += '<thead>';
                html += '<tr>';
                html += '<th class="report-col-code">Account Code</th>';
                html += '<th class="report-col-name">Account Name</th>';
                html += '<th class="report-col-balance text-right">Balance</th>';
                html += '</tr>';
                html += '</thead>';
                html += '<tbody>';
                
                let accounts = grouped[category] || [];
                
                // Apply search filter to accounts within category
                if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
                    const searchTerm = this.reportSearchTerm.toLowerCase().trim();
                    accounts = accounts.filter(acc => {
                        const code = (acc.account_code || '').toLowerCase();
                        const name = (acc.account_name || '').toLowerCase();
                        return code.includes(searchTerm) || name.includes(searchTerm);
                    });
                }
                accounts.forEach((account, accIndex) => {
                    const balance = parseFloat(account.balance || 0);
                    const balanceClass = balance >= 0 ? 'balance-positive' : 'balance-negative';
                    html += `<tr class="report-data-row ${accIndex % 2 === 0 ? 'even' : 'odd'}">`;
                    html += `<td class="report-col-code"><code>${this.escapeHtml(account.account_code || '')}</code></td>`;
                    html += `<td class="report-col-name">${this.escapeHtml(account.account_name || '')}</td>`;
                    html += `<td class="report-col-balance text-right ${balanceClass}">${this.formatCurrency(balance)}</td>`;
                    html += '</tr>';
                });
                
                html += '</tbody></table></div></div>';
            });
        } else {
            html += '<div class="report-empty-state">';
            html += '<i class="fas fa-info-circle report-empty-icon"></i>';
            html += '<h3>No Accounts Found</h3>';
            html += '<p class="report-empty-text">No accounts found in the system.</p>';
            html += '</div>';
        }
        
        if (reportData?.total_accounts !== undefined) {
            html += '<div class="report-summary-section">';
            html += '<div class="professional-report-table-wrapper">';
            html += '<table class="professional-report-table report-summary-table">';
            html += '<tbody>';
            html += '<tr class="report-summary-row">';
            html += '<td class="report-summary-label"><strong>Total Accounts:</strong></td>';
            html += `<td class="report-summary-value text-right"><strong>${reportData.total_accounts}</strong></td>`;
            html += '</tr>';
            html += '</tbody></table></div></div>';
        }
        
        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.displayReportPlaceholder = function(reportType, reportName) {
        const reportContent = document.getElementById('modalReportContent');
        const reportsGrid = document.getElementById('modalReportsGrid');
        const targetContainer = reportContent || document.querySelector('#financialReportsTab .module-content') || document.querySelector('#reportsTab .module-content');

        if (!targetContainer) {
            return;
        }

        if (reportsGrid) {
            reportsGrid.classList.remove('reports-grid-visible');
            reportsGrid.classList.add('reports-grid-hidden');
        }

        targetContainer.innerHTML = `
            <div class="accounting-report-placeholder">
                <div class="accounting-report-placeholder-header">
                    <h3 class="accounting-report-header-title">${reportName} Report</h3>
                    <button class="btn btn-secondary" data-action="restore-reports-grid">
                        <i class="fas fa-arrow-left"></i> Back to Reports
                    </button>
                </div>
                <i class="fas fa-chart-bar accounting-report-placeholder-icon"></i>
                <p class="accounting-report-placeholder-text">This report will display detailed financial information once the required data is available.</p>
                <div class="accounting-report-placeholder-features">
                    <h4>Report Features:</h4>
                    <ul>
                        <li>
                            <i class="fas fa-check-circle accounting-report-placeholder-features-icon"></i>
                            Comprehensive financial data
                        </li>
                        <li>
                            <i class="fas fa-check-circle accounting-report-placeholder-features-icon"></i>
                            Export to PDF/Excel
                        </li>
                        <li>
                            <i class="fas fa-check-circle accounting-report-placeholder-features-icon"></i>
                            Print-friendly format
                        </li>
                        <li>
                            <i class="fas fa-check-circle accounting-report-placeholder-features-icon"></i>
                            Date range filtering
                        </li>
                    </ul>
                </div>
            </div>
        `;

        targetContainer.classList.add('report-container-visible');
        targetContainer.classList.add('show');
    }

ProfessionalAccounting.prototype.showToast = function(message, type = 'info', duration = 5000) {
        // Remove existing toasts
        const existingToasts = document.querySelectorAll('.accounting-toast');
        existingToasts.forEach(toast => {
            toast.classList.add('accounting-toast-removing');
            setTimeout(() => toast.remove(), 300);
        });

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `accounting-toast accounting-toast-${type}`;
        
        toast.innerHTML = `<div>${this.escapeHtml(message)}</div>`;

        // Add data attribute to prevent permissions script from hiding it
        toast.setAttribute('data-no-permissions', 'true');
        
        // Remove any old notifications that might be showing
        const oldNotifications = document.querySelectorAll('.accounting-notification');
        oldNotifications.forEach(n => {
            n.classList.add('notification-hidden');
            n.remove();
        });
        
        // Ensure toast fits on screen
        const viewportWidth = window.innerWidth;
        const toastWidth = Math.min(400, viewportWidth - 40);

        document.body.appendChild(toast);

        // Force immediate visibility
        toast.classList.add('accounting-toast-visible');
        
        // Protection from permissions script interference
        const protectToast = () => {
            if (!document.body.contains(toast)) {
                return;
            }
            
            const computed = window.getComputedStyle(toast);
            const needsFix = computed.display === 'none' || 
                           computed.visibility === 'hidden' || 
                           computed.opacity === '0' ||
                           computed.zIndex < 99999999;
            
            if (needsFix) {
                // Force visibility using CSS class
                toast.classList.add('accounting-toast-protected');
                toast.classList.add('accounting-toast-visible');
            }
        };
        
        // Watch for style changes that might hide the toast
        const styleObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && (mutation.attributeName === 'style' || mutation.attributeName === 'class')) {
                    protectToast();
                }
            });
        });
        
        styleObserver.observe(toast, {
            attributes: true,
            attributeFilter: ['style', 'class']
        });
        
        // Check frequently to catch permissions script interference
        const protectionInterval = setInterval(() => {
            if (!document.body.contains(toast)) {
                clearInterval(protectionInterval);
                styleObserver.disconnect();
                return;
            }
            protectToast();
        }, 50);
        
        // Clean up interval when toast is removed
        setTimeout(() => {
            clearInterval(protectionInterval);
            styleObserver.disconnect();
        }, duration + 1000);
        
        // Also protect immediately after permissions script runs
        const originalApplyPermissions = window.UserPermissions?.applyPermissions;
        if (originalApplyPermissions) {
            window.UserPermissions.applyPermissions = function() {
                const result = originalApplyPermissions.apply(this, arguments);
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        protectToast();
                    }
                }, 10);
                return result;
            };
        }
        
        // Ensure visibility with multiple attempts
        requestAnimationFrame(() => {
            toast.classList.add('accounting-toast-visible');
            toast.offsetHeight; // Force reflow
            
            // Force check and fix immediately
            protectToast();
            
            const computed = window.getComputedStyle(toast);
            const rect = toast.getBoundingClientRect();
        });
        
        setTimeout(() => {
            if (!toast.classList.contains('accounting-toast-visible')) {
                toast.classList.add('accounting-toast-visible');
            }
            toast.offsetHeight; // Force reflow again
            const rect = toast.getBoundingClientRect();
            const viewportWidth = window.innerWidth;
            const isOnScreen = rect.right <= viewportWidth && rect.left >= 0;
            const isInDOM = document.body.contains(toast);
            
            // If toast is off-screen or removed from DOM, re-add and ensure visibility
            if (!isInDOM) {
                document.body.appendChild(toast);
                toast.classList.add('accounting-toast-visible');
            }
            
            if (!isOnScreen || rect.right > viewportWidth) {
                // Re-position using class
                toast.classList.add('accounting-toast-visible');
                toast.offsetHeight; // Force reflow
            }
        }, 50);

        // Add click handler for close button
        const closeBtn = toast.querySelector('.toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                toast.classList.add('accounting-toast-removing');
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, 300);
            });
        }

        // Auto-remove after duration
        setTimeout(() => {
            if (toast.parentElement) {
                toast.classList.add('accounting-toast-removing');
                setTimeout(() => {
                    if (toast.parentElement) {
                        toast.remove();
                    }
                }, 300);
            }
        }, duration);
    }

ProfessionalAccounting.prototype.showConfirmDialog = function(title, message, confirmText = 'Confirm', cancelText = 'Cancel', type = 'warning') {
        return new Promise((resolve) => {
            // Remove existing dialogs
            const existingDialogs = document.querySelectorAll('.accounting-confirm-dialog');
            existingDialogs.forEach(dialog => dialog.remove());

            // Create dialog overlay
            const overlay = document.createElement('div');
            overlay.className = 'accounting-confirm-overlay';
            
            // Create dialog
            const dialog = document.createElement('div');
            dialog.className = `accounting-confirm-dialog accounting-confirm-${type}`;
        
        const icons = {
            'warning': 'fa-exclamation-triangle',
                'danger': 'fa-exclamation-circle',
                'info': 'fa-info-circle',
                'success': 'fa-check-circle'
        };

            dialog.innerHTML = `
                <div class="confirm-icon">
                    <i class="fas ${icons[type] || icons.warning}"></i>
                </div>
                <div class="confirm-content">
                    <h3 class="confirm-title">${this.escapeHtml(title)}</h3>
                    <p class="confirm-message">${this.escapeHtml(message)}</p>
                </div>
                <div class="confirm-actions">
                    <button class="btn-confirm-cancel" data-action="confirm-cancel">
                        ${this.escapeHtml(cancelText)}
            </button>
                    <button class="btn-confirm-ok" data-action="confirm-ok">
                        ${this.escapeHtml(confirmText)}
                    </button>
                </div>
        `;

            overlay.appendChild(dialog);
            document.body.appendChild(overlay);
            document.body.classList.add('body-no-scroll');

            // Add visible classes immediately using requestAnimationFrame
            requestAnimationFrame(() => {
                overlay.classList.add('confirm-overlay-active');
                dialog.classList.add('confirm-dialog-active');
                
                // Animate in immediately
                requestAnimationFrame(() => {
                    overlay.classList.add('confirm-overlay-visible');
                    dialog.classList.add('confirm-dialog-visible');
                    // Force reflow
                    overlay.offsetHeight;
                    dialog.offsetHeight;
                });
            });

            // Handle button clicks
            const handleConfirm = () => {
                overlay.classList.remove('confirm-overlay-visible');
                dialog.classList.remove('confirm-dialog-visible');
        setTimeout(() => {
                    overlay.remove();
                    document.body.classList.remove('body-no-scroll');
                }, 300);
                resolve(true);
            };

            const handleCancel = () => {
                overlay.classList.remove('confirm-overlay-visible');
                dialog.classList.remove('confirm-dialog-visible');
                setTimeout(() => {
                    overlay.remove();
                    document.body.classList.remove('body-no-scroll');
                }, 300);
                resolve(false);
            };

            dialog.querySelector('[data-action="confirm-ok"]').addEventListener('click', handleConfirm);
            dialog.querySelector('[data-action="confirm-cancel"]').addEventListener('click', handleCancel);
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    handleCancel();
                }
            });

            // Handle ESC key
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    handleCancel();
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);
        });
    }

    // Modern prompt dialog (replaces browser prompt)
ProfessionalAccounting.prototype.showPrompt = function(title, message, defaultValue = '', placeholder = '', inputType = 'text') {
        return new Promise((resolve) => {
            // Remove existing dialogs
            const existingDialogs = document.querySelectorAll('.accounting-prompt-dialog');
            existingDialogs.forEach(dialog => dialog.remove());

            // Create dialog overlay
            const overlay = document.createElement('div');
            overlay.className = 'accounting-confirm-overlay';
            
            // Create dialog
            const dialog = document.createElement('div');
            dialog.className = 'accounting-confirm-dialog accounting-prompt-dialog accounting-confirm-info';
        
            dialog.innerHTML = `
                <div class="confirm-icon">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="confirm-content">
                    <h3 class="confirm-title">${this.escapeHtml(title)}</h3>
                    <p class="confirm-message">${this.escapeHtml(message)}</p>
                    <div class="prompt-input-container">
                        <input type="${inputType}" 
                               id="promptInput" 
                               class="form-control prompt-input" 
                               value="${this.escapeHtml(defaultValue)}" 
                               placeholder="${this.escapeHtml(placeholder)}"
                               autofocus
                               required>
                        <div class="prompt-error" id="promptError"></div>
                    </div>
                </div>
                <div class="confirm-actions">
                    <button class="btn-confirm-cancel" data-action="prompt-cancel">
                        Cancel
                    </button>
                    <button class="btn-confirm-ok" id="promptOkBtn" data-action="prompt-ok" disabled>
                        OK
                    </button>
                </div>
            `;

            overlay.appendChild(dialog);
            document.body.appendChild(overlay);
            document.body.classList.add('body-no-scroll');

            // Add visible classes immediately
            requestAnimationFrame(() => {
                overlay.classList.add('confirm-overlay-active');
                dialog.classList.add('confirm-dialog-active');
                
                requestAnimationFrame(() => {
                    overlay.classList.add('confirm-overlay-visible');
                    dialog.classList.add('confirm-dialog-visible');
                    overlay.offsetHeight;
                    dialog.offsetHeight;
                    
                    // Focus input
                    const input = dialog.querySelector('#promptInput');
                    if (input) {
                        input.focus();
                        input.select();
                    }
                });
            });

            // Handle button clicks
            const handleConfirm = () => {
                const input = dialog.querySelector('#promptInput');
                const value = input ? input.value.trim() : '';
                const errorDiv = dialog.querySelector('#promptError');
                
                if (!value) {
                    if (errorDiv) {
                        errorDiv.textContent = 'This field is required';
                        errorDiv.classList.add('error-visible');
                        errorDiv.classList.remove('error-hidden');
                    }
                    return;
                }
                
                overlay.classList.remove('confirm-overlay-visible');
                dialog.classList.remove('confirm-dialog-visible');
                setTimeout(() => {
                    overlay.remove();
                    document.body.classList.remove('body-no-scroll');
                }, 300);
                resolve(value);
            };

            const handleCancel = () => {
                overlay.classList.remove('confirm-overlay-visible');
                dialog.classList.remove('confirm-dialog-visible');
                setTimeout(() => {
                    overlay.remove();
                    document.body.classList.remove('body-no-scroll');
                }, 300);
                resolve(null);
            };

            const okBtn = dialog.querySelector('[data-action="prompt-ok"]');
            const cancelBtn = dialog.querySelector('[data-action="prompt-cancel"]');
            const input = dialog.querySelector('#promptInput');
            const errorDiv = dialog.querySelector('#promptError');
            
            okBtn.addEventListener('click', handleConfirm);
            cancelBtn.addEventListener('click', handleCancel);
            
            // Enable/disable OK button based on input
            if (input) {
                const updateOkButton = () => {
                    const value = input.value.trim();
                    if (okBtn) {
                        okBtn.disabled = !value;
                    }
                    if (errorDiv) {
                        errorDiv.classList.add('error-hidden');
                        errorDiv.classList.remove('error-visible');
                    }
                };
                
                input.addEventListener('input', updateOkButton);
                input.addEventListener('keyup', updateOkButton);
                
                // Initial check
                updateOkButton();
                
                // Handle Enter key
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && input.value.trim()) {
                        e.preventDefault();
                        handleConfirm();
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (errorDiv) {
                            errorDiv.textContent = 'Please enter a value';
                            errorDiv.classList.add('error-visible');
                            errorDiv.classList.remove('error-hidden');
                        }
                    }
                });
            }
            
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    handleCancel();
                }
            });

            // Handle ESC key
            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    handleCancel();
                    document.removeEventListener('keydown', escHandler);
                }
            };
            document.addEventListener('keydown', escHandler);
        });
    }

ProfessionalAccounting.prototype.hasFormChanges = function(form) {
        if (!form) return false;
        
        // Check if form has data-unsaved attribute
        if (form.hasAttribute('data-unsaved')) {
            return true;
        }

        // Check if any input has been modified
        const inputs = form.querySelectorAll('input, textarea, select');
        for (const input of inputs) {
            if (input.type === 'checkbox' || input.type === 'radio') {
                if (input.defaultChecked !== input.checked) {
                    return true;
                }
            } else {
                if (input.defaultValue !== input.value) {
                    return true;
                }
            }
        }
        return false;
    }

ProfessionalAccounting.prototype.markFormAsChanged = function(form) {
        if (form) {
            form.setAttribute('data-unsaved', 'true');
            }
    }

ProfessionalAccounting.prototype.markFormAsSaved = function(form) {
        if (form) {
            form.removeAttribute('data-unsaved');
            // Update default values
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.defaultChecked = input.checked;
                } else {
                    input.defaultValue = input.value;
                }
            });
        }
    }

ProfessionalAccounting.prototype.openQuickEntry = function() {
        this.openQuickEntryModal();
    }

ProfessionalAccounting.prototype.openJournalEntryModal = async function(entryId = null) {
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
    }

ProfessionalAccounting.prototype.loadAccountsForSelect = async function(selectId = null, selectElement = null) {
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
        
        console.log('loadAccountsForSelect: Found select element', selectId, accountSelect);
        
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
            const accountsUrl = `${this.apiBase}/accounts.php?is_active=1&ensure_entity_accounts=1`;
            console.log('Loading accounts for:', selectId || 'element', 'API:', accountsUrl);
            const response = await fetch(accountsUrl, { credentials: 'include' });
            const data = await response.json().catch(() => ({ success: false, accounts: [] }));
            if (!response.ok) {
                throw new Error(data.message || data.error || `HTTP error! status: ${response.status}`);
            }
            console.log('Accounts response:', data);
            
            // Re-fetch the select element in case DOM changed
            if (selectId) {
                const refreshedSelect = document.getElementById(selectId);
                if (refreshedSelect) {
                    accountSelect = refreshedSelect;
                    console.log('Re-fetched select element after API call:', selectId);
                } else {
                    console.error('Select element lost after fetch!', selectId);
                    // Try one more time after a short delay
                    await new Promise(resolve => setTimeout(resolve, 100));
                    const retrySelect = document.getElementById(selectId);
                    if (retrySelect) {
                        accountSelect = retrySelect;
                        console.log('Retry: Found select element', selectId);
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
                
                // Verify the update worked
                const finalOptionCount = accountSelect.options.length;
                console.log(`Successfully loaded ${data.accounts.length} accounts into ${selectId || 'element'}. Options count: ${finalOptionCount}`);
                
                if (finalOptionCount > 0) {
                    console.log('First option:', accountSelect.options[0].textContent);
                    if (finalOptionCount > 1) {
                        console.log('Second option:', accountSelect.options[1].textContent);
                    }
                }
                
                // If still not working, retry with fresh element reference
                if (finalOptionCount <= 1 && selectId) {
                    console.warn('Options count low, retrying with fresh element reference...');
                    await new Promise(resolve => setTimeout(resolve, 100));
                    const freshSelect = document.getElementById(selectId);
                    if (freshSelect) {
                        freshSelect.innerHTML = optionsHTML;
                        console.log('Retried with fresh element. New count:', freshSelect.options.length);
                    }
                }
            } else {
                // If no accounts found, use fallback options
                console.warn('No accounts found, using fallback options');
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
    }

ProfessionalAccounting.prototype.openInvoiceModal = async function(invoiceId = null) {
        // Use large modal for a wider 2-column form layout
        this.showModal(invoiceId ? 'Edit Invoice' : 'Create Invoice', this.getInvoiceModalContent(invoiceId), 'large');
        
        const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
        if (!modal) return;
        
        // Create a simple close function for this modal
        const closeInvoiceModal = async () => {
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
                console.error('Error closing invoice modal:', e);
            }
        };
        
        const form = modal.querySelector('#invoiceForm');
        
        // Prevent form submission from cancel/close buttons - MUST be first
        if (form) {
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
            
            // Also add a direct handler on the cancel button itself as backup
            setTimeout(() => {
                const cancelBtn = form.querySelector('#invoiceCancelBtn') || form.querySelector('button[data-action="close-modal"]');
                if (cancelBtn) {
                    const directCancelHandler = async function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        e.cancelBubble = true;
                        e.returnValue = false;
                        await closeInvoiceModal();
                        return false;
                    };
                    cancelBtn.addEventListener('click', directCancelHandler, { capture: true });
                    cancelBtn.addEventListener('click', directCancelHandler, { capture: false });
                    cancelBtn.onclick = directCancelHandler;
                }
            }, 300);
        }
        
        // Initialize English date pickers
        setTimeout(() => {
            if (modal) {
                this.initializeEnglishDatePickers(modal);
            }
        }, 100);
        // Load accounts, currency, and entities into dropdowns
        setTimeout(async () => {
            // Wait a bit longer to ensure DOM is ready
            await new Promise(resolve => setTimeout(resolve, 50));
            
            // Verify elements exist before loading
            const debitSelect = document.getElementById('invoiceDebitAccountSelect');
            const creditSelect = document.getElementById('invoiceCreditAccountSelect');
            
            console.log('Invoice modal opened - Debit select found:', !!debitSelect, 'Credit select found:', !!creditSelect);
            
            if (debitSelect) {
                await this.loadAccountsForSelect('invoiceDebitAccountSelect');
            } else {
                console.error('invoiceDebitAccountSelect not found in DOM');
            }
            
            if (creditSelect) {
                await this.loadAccountsForSelect('invoiceCreditAccountSelect');
            } else {
                console.error('invoiceCreditAccountSelect not found in DOM');
            }
            
            // Populate currency dropdown
            const form = document.getElementById('invoiceForm');
            if (form) {
                const currencySelect = document.getElementById('invoiceCurrencySelect') || form.querySelector('select[name="currency"]');
                if (currencySelect) {
                    // Clear loading message first
                    currencySelect.innerHTML = '<option value="">Loading currencies...</option>';
                    
                    if (window.currencyUtils && typeof window.currencyUtils.populateCurrencySelect === 'function') {
                        try {
                            const defaultCurrency = this.getDefaultCurrencySync();
                            await window.currencyUtils.populateCurrencySelect(currencySelect, defaultCurrency);
                        } catch (error) {
                            console.error('Error populating invoice currency dropdown:', error);
                            // Fallback: try getCurrencyOptionsHTML
                            if (typeof this.getCurrencyOptionsHTML === 'function') {
                                try {
                                    const defaultCurrency = this.getDefaultCurrencySync();
                                    const optionsHTML = await this.getCurrencyOptionsHTML(defaultCurrency);
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
                    } else if (typeof this.getCurrencyOptionsHTML === 'function') {
                        // Fallback if currencyUtils not available
                        try {
                            const defaultCurrency = this.getDefaultCurrencySync();
                            const optionsHTML = await this.getCurrencyOptionsHTML(defaultCurrency);
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
                this.setupCustomerFields('invoiceCustomersContainer', invoiceId);
                
                // Setup Tax checkbox - toggle "Tax included" / "Tax not included"
                const taxCb = form.querySelector('#invoiceTaxCheckbox');
                const taxLabel = form.querySelector('#invoiceTaxLabel');
                if (taxCb && taxLabel) {
                    const updateTaxLabel = () => { taxLabel.textContent = taxCb.checked ? 'Tax included' : 'Tax not included'; };
                    taxCb.addEventListener('change', updateTaxLabel);
                    updateTaxLabel();
                }
                
                // Payment Voucher is auto-generated; keep field readonly, placeholder "Auto-generated"
                const pvInput = form.querySelector('#invoicePaymentVoucher');
                if (pvInput) pvInput.placeholder = 'Auto-generated';
                
                // Setup form submit handler directly here as backup
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.hasAttribute('data-handler-attached')) {
                    submitBtn.setAttribute('data-handler-attached', 'true');
                    submitBtn.addEventListener('click', async (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Create Invoice button clicked (direct handler)');
                        
                        // Prevent double submission
                        if (submitBtn.disabled) {
                            console.log('Button already processing, ignoring click');
                            return;
                        }
                        
                        // Disable button to prevent double submission
                        submitBtn.disabled = true;
                        const originalText = submitBtn.textContent;
                        submitBtn.textContent = 'Saving...';
                        
                        try {
                            // Validate required fields
                            const requiredFields = form.querySelectorAll('[required]');
                            console.log('Found required fields:', requiredFields.length);
                            let isValid = true;
                            const missingFields = [];
                            requiredFields.forEach(field => {
                                const value = field.value ? field.value.trim() : '';
                                console.log(`Field ${field.name || field.id}: value="${value}"`);
                                if (!value || value === '') {
                                    isValid = false;
                                    field.style.borderColor = '#ef4444';
                                    missingFields.push(field.name || field.id || field.label || 'Unknown field');
                                } else {
                                    field.style.borderColor = '';
                                }
                            });
                            
                            if (!isValid) {
                                console.log('Validation failed. Missing fields:', missingFields);
                                this.showToast(`Please fill in all required fields. Missing: ${missingFields.join(', ')}`, 'error');
                                return;
                            }
                            
                            console.log('Validation passed, proceeding to save...');
                            
                            const invoiceIdAttr = form.getAttribute('data-invoice-id');
                            const id = invoiceIdAttr && invoiceIdAttr !== 'null' ? parseInt(invoiceIdAttr) : null;
                            console.log('Calling saveInvoice with id:', id);
                            await this.saveInvoice(id);
                        } finally {
                            // Re-enable button
                            submitBtn.disabled = false;
                            submitBtn.textContent = originalText;
                        }
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
            }
            
            // Force attach handlers directly to buttons after a short delay
            setTimeout(() => {
                // Cancel button - remove all handlers and add new one
                const cancelBtn = modal.querySelector('#invoiceCancelBtn') || modal.querySelector('button[data-action="close-modal"]');
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
                        
                        await closeInvoiceModal();
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
                        await closeInvoiceModal();
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
                            await closeInvoiceModal();
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
                        await closeInvoiceModal();
                        return false;
                    }
                };
                
                modal.onclick = backdropHandler;
                modal.addEventListener('click', backdropHandler, true);
            }, 200);
        }, 100);
    }

ProfessionalAccounting.prototype.openBillModal = async function(billId = null) {
        this.showModal(billId ? 'Edit Bill' : 'Create Bill', this.getBillModalContent(billId));
        // Initialize English date pickers
        setTimeout(() => {
            const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
            if (modal) {
                this.initializeEnglishDatePickers(modal);
            }
        }, 100);
        // Load vendors and entities into dropdowns
        setTimeout(async () => {
            await this.loadVendorsForSelect('billVendorSelect', billId);
            
            // Load entities
            const form = document.getElementById('billForm');
            if (form) {
                // Setup customer fields
                this.setupCustomerFields('billCustomersContainer');
            }
            
            // If editing, load bill data and populate form
            if (billId) {
                try {
                    const response = await fetch(`${this.apiBase}/bills.php?id=${billId}`);
                    const data = await response.json();
                    
                    if (data.success && data.bill) {
                        const bill = data.bill;
                        const form = document.getElementById('billForm');
                        if (!form) return;
                        
                        // Populate form fields
                        const billNumberInput = form.querySelector('input[name="bill_number"]');
                        const billDateInput = form.querySelector('input[name="bill_date"]');
                        const vendorSelect = form.querySelector('select[name="vendor_id"]');
                        const dueDateInput = form.querySelector('input[name="due_date"]');
                        const currencySelect = form.querySelector('select[name="currency"]');
                        const totalAmountInput = form.querySelector('input[name="total_amount"]');
                        const descriptionTextarea = form.querySelector('textarea[name="description"]');
                        
                        if (billNumberInput && bill.bill_number) billNumberInput.value = bill.bill_number;
                        if (billDateInput && bill.bill_date) billDateInput.value = this.formatDateForInput(bill.bill_date);
                        if (vendorSelect && bill.vendor_id) {
                            // Wait for vendors to load, then set value
                            setTimeout(() => {
                                vendorSelect.value = bill.vendor_id;
                            }, 200);
                        }
                        if (dueDateInput && bill.due_date) dueDateInput.value = this.formatDateForInput(bill.due_date);
                        if (currencySelect && bill.currency) currencySelect.value = bill.currency;
                        if (totalAmountInput && bill.total_amount) totalAmountInput.value = bill.total_amount;
                        if (descriptionTextarea && bill.description) descriptionTextarea.value = bill.description || '';
                    }
                } catch (error) {
                    this.showToast('Failed to load bill data', 'error');
                }
            }
        }, 100);
    }

ProfessionalAccounting.prototype.openReceivePaymentModal = async function(invoiceId = null) {
        const title = invoiceId ? 'Receive Payment for Invoice' : 'Receive Payment';
        const content = this.getReceivePaymentModalContent(invoiceId);
        this.showModal(title, content);
        // Initialize English date pickers
        setTimeout(() => {
            const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
            if (modal) {
                this.initializeEnglishDatePickers(modal);
            }
        }, 100);
        
        // Populate currency dropdown
        setTimeout(async () => {
            const currencySelect = document.getElementById('receivePaymentCurrency');
            if (currencySelect && window.currencyUtils) {
                try {
                    const defaultCurrency = this.getDefaultCurrencySync();
                    await window.currencyUtils.populateCurrencySelect(currencySelect, defaultCurrency);
                } catch (error) {
                    console.error('Error populating receive payment currency:', error);
                }
            }
        }, 150);
        
        // Load invoices and entities if not editing
        if (!invoiceId) {
            setTimeout(async () => {
                await this.loadInvoicesForSelect('receivePaymentInvoiceSelect');
                
            }, 100);
        } else {
            // Load invoice details
            setTimeout(async () => {
                try {
                    const response = await fetch(`${this.apiBase}/invoices.php?id=${invoiceId}`);
                    const data = await response.json();
                    if (data.success && data.invoice) {
                        const invoice = data.invoice;
                        const form = document.getElementById('receivePaymentForm');
                        if (form) {
                            const amountInput = form.querySelector('input[name="amount"]');
                            const invoiceSelect = form.querySelector('select[name="invoice_id"]');
                            if (amountInput) amountInput.value = parseFloat(invoice.balance_amount || invoice.total_amount || 0);
                            if (invoiceSelect) invoiceSelect.value = invoiceId;
                        }
                    }
                } catch (error) {
    }
            }, 100);
        }
    }

ProfessionalAccounting.prototype.openMakePaymentModal = async function(billId = null) {
        const title = billId ? 'Make Payment for Bill' : 'Make Payment';
        const content = this.getMakePaymentModalContent(billId);
        this.showModal(title, content);
        
        // Populate currency dropdown
        setTimeout(async () => {
            const currencySelect = document.getElementById('makePaymentCurrency');
            if (currencySelect && window.currencyUtils) {
                try {
                    const defaultCurrency = this.getDefaultCurrencySync();
                    await window.currencyUtils.populateCurrencySelect(currencySelect, defaultCurrency);
                } catch (error) {
                    console.error('Error populating make payment currency:', error);
                }
            }
        }, 150);
        
        // Load vendors and bills if not editing
        if (!billId) {
            setTimeout(async () => {
                await this.loadVendorsForSelect('makePaymentVendorSelect');
                await this.loadBillsForSelect('makePaymentBillSelect');
            }, 100);
        } else {
            // Load bill details
            setTimeout(async () => {
                try {
                    const response = await fetch(`${this.apiBase}/bills.php?id=${billId}`);
                    const data = await response.json();
                    if (data.success && data.bill) {
                        const bill = data.bill;
                        const form = document.getElementById('makePaymentForm');
                        if (form) {
                            const amountInput = form.querySelector('input[name="amount"]');
                            const billSelect = form.querySelector('select[name="bill_id"]');
                            if (amountInput) amountInput.value = parseFloat(bill.balance_amount || bill.total_amount || 0);
                            if (billSelect) billSelect.value = billId;
                        }
                    }
                } catch (error) {
                }
            }, 100);
        }
    }

    // Module Modal Openers
ProfessionalAccounting.prototype.openGeneralLedgerModal = function() {
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
    }

ProfessionalAccounting.prototype.openReceivablesModal = function() {
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
                    console.log('New Invoice button clicked from Accounts Receivable modal');
                    
                    // Try to call the real openInvoiceModal function directly
                    // First check if getInvoiceModalContent exists (means real function is available)
                    if (typeof self.getInvoiceModalContent === 'function') {
                        console.log('Found getInvoiceModalContent, opening invoice form');
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
                            console.log('Calling real openInvoiceModal function');
                            self.openInvoiceModal();
                        } else {
                            console.log('openInvoiceModal is alias, trying to find real function');
                            // Try to get the real function from prototype
                            if (typeof ProfessionalAccounting !== 'undefined') {
                                const protoFunc = ProfessionalAccounting.prototype.openInvoiceModal;
                                if (protoFunc && protoFunc.toString().includes('getInvoiceModalContent')) {
                                    console.log('Calling prototype openInvoiceModal');
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
                
                console.log('New Invoice button handler attached');
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
            
            this.loadModalInvoices();
        }, 100);
    }

ProfessionalAccounting.prototype.openPayablesModal = function() {
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
    }

ProfessionalAccounting.prototype.openBankingModal = function() {
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
    }

ProfessionalAccounting.prototype.openEntitiesModal = function() {
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

ProfessionalAccounting.prototype.attachEntityTransactionsHandlers = function() {
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
    }

ProfessionalAccounting.prototype.loadEntityTransactionsData = async function() {
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
    }

ProfessionalAccounting.prototype.updateEntityStatusCards = function(transactions) {
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
    }

ProfessionalAccounting.prototype.renderEntityTransactionsTable = function(transactions) {
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
    }

ProfessionalAccounting.prototype.updateEntityTransactionsPagination = function() {
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
    }

ProfessionalAccounting.prototype.createPaginationHTML = function(current, total, prefix) {
        if (total <= 1) return '';

        let html = '<div class="pagination-controls-modern">';
        
        // Previous button
        html += `<button class="btn-pagination" data-action="${prefix}-prev" ${current <= 1 ? 'disabled' : ''}>
            <i class="fas fa-chevron-left"></i> Previous
        </button>`;

        // Page numbers
        html += '<div class="page-numbers-modern">';
        
        const maxPages = 5;
        let startPage = Math.max(1, current - Math.floor(maxPages / 2));
        let endPage = Math.min(total, startPage + maxPages - 1);
        
        if (endPage - startPage < maxPages - 1) {
            startPage = Math.max(1, endPage - maxPages + 1);
        }

        if (startPage > 1) {
            html += `<button class="btn-page" data-action="${prefix}-page" data-page="1">1</button>`;
            if (startPage > 2) {
                html += '<span class="page-ellipsis">...</span>';
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<button class="btn-page ${i === current ? 'active' : ''}" data-action="${prefix}-page" data-page="${i}">${i}</button>`;
        }

        if (endPage < total) {
            if (endPage < total - 1) {
                html += '<span class="page-ellipsis">...</span>';
            }
            html += `<button class="btn-page" data-action="${prefix}-page" data-page="${total}">${total}</button>`;
        }

        html += '</div>';

        // Next button
        html += `<button class="btn-pagination" data-action="${prefix}-next" ${current >= total ? 'disabled' : ''}>
            Next <i class="fas fa-chevron-right"></i>
        </button>`;

        html += '</div>';
        return html;
    }

ProfessionalAccounting.prototype.updateBulkActionsBar = function() {
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
    }

ProfessionalAccounting.prototype.deleteEntityTransaction = async function(id) {
        const confirmed = await this.showConfirmDialog(
            'Delete Transaction',
            'Are you sure you want to delete this transaction?',
            'Delete',
            'Cancel',
            'danger'
        );
        if (!confirmed) {
            return;
        }

        try {
            // API expects id as GET parameter for DELETE method
            const response = await fetch(`${this.apiBase}/entity-transactions.php?id=${parseInt(id)}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();
            if (data.success) {
                this.showToast('Transaction deleted successfully', 'success');
                this.entityTransactionsSelected.delete(id.toString());
                this.loadEntityTransactionsData();
            } else {
                this.showToast(data.message || 'Failed to delete transaction', 'error');
            }
        } catch (error) {
            this.showToast('Error deleting transaction', 'error');
        }
    }

ProfessionalAccounting.prototype.bulkDeleteEntityTransactions = async function(ids) {
        try {
            const promises = ids.map(id => 
                fetch(`${this.apiBase}/entity-transactions.php?id=${id}`, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' }
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
            if (failedCount > 0) {
                this.showToast(`Failed to delete ${failedCount} transaction(s)`, 'error');
            }
        } catch (error) {
            this.showToast('Error deleting transactions', 'error');
        }
    }

ProfessionalAccounting.prototype.bulkExportEntityTransactions = async function(ids) {
        try {
            const selectedTransactions = this.entityTransactionsFiltered.filter(t => 
                ids.includes(parseInt(t.id))
            );

            // Create CSV
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
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `entity-transactions-${new Date().toISOString().split('T')[0]}.csv`);
            link.classList.add('download-link-hidden');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            this.showToast(`Exported ${ids.length} transaction(s)`, 'success');
        } catch (error) {
            this.showToast('Error exporting transactions', 'error');
        }
    }

ProfessionalAccounting.prototype.openReportsModal = function() {
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
    }
    
ProfessionalAccounting.prototype.setupReportsFilters = function() {
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
    }
    
ProfessionalAccounting.prototype.filterReports = function() {
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
    }

ProfessionalAccounting.prototype.openSettingsModal = async function() {
        const today = new Date();
        const fiscalYearStart = this.formatDateForInput(new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0]);
        const fiscalYearEnd = this.formatDateForInput(new Date(today.getFullYear(), 11, 31).toISOString().split('T')[0]);
        
        // Get currency options HTML (async function) - get current default currency first
        const currentDefaultCurrency = this.getDefaultCurrencySync();
        const currencyOptionsHTML = await this.getCurrencyOptionsHTML(currentDefaultCurrency);
        
        const content = `
            <div class="accounting-module-modal-content">
                <div class="module-header">
                    <div class="header-actions">
                        <button class="btn btn-sm btn-primary" data-action="save-settings" type="button">
                            <i class="fas fa-save"></i> Save All Settings
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="reset-settings" type="button">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                        <button class="btn btn-sm btn-secondary" data-action="export-settings" type="button">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
                <div class="module-content">
                    <div class="settings-summary-cards">
                        <div class="settings-summary-card">
                            <div class="summary-card-icon">
                                <i class="fas fa-percent"></i>
                            </div>
                            <div class="summary-card-content">
                                <h4 id="modalSettingsTaxRate">15%</h4>
                                <p>Tax Rate</p>
                            </div>
                            </div>
                        <div class="settings-summary-card">
                            <div class="summary-card-icon">
                                <i class="fas fa-calculator"></i>
                            </div>
                            <div class="summary-card-content">
                                <h4 id="modalSettingsTaxMethod">Inclusive</h4>
                                <p>Tax Method</p>
                            </div>
                            </div>
                        <div class="settings-summary-card">
                            <div class="summary-card-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="summary-card-content">
                                <h4 id="modalSettingsCurrency">SAR</h4>
                                <p>Default Currency</p>
                        </div>
                    </div>
                        <div class="settings-summary-card">
                            <div class="summary-card-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="summary-card-content">
                                <h4 id="modalSettingsFiscalYear">${today.getFullYear()}</h4>
                                <p>Fiscal Year</p>
                        </div>
                    </div>
                    </div>
                    
                    <div class="settings-search-bar">
                        <div class="search-input-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" id="modalSettingsSearch" class="settings-search-input" placeholder="Search settings...">
                        </div>
                    </div>
                    
                    <div class="settings-sections-container" id="modalSettingsSectionsContainer">
                            <div class="settings-grid">
                                <div class="setting-item">
                                        <label for="defaultTaxRate">
                                            <span>Default Tax Rate (%)</span>
                                            <span class="setting-help" title="The default tax rate applied to transactions">?</span>
                                        </label>
                                        <input type="number" id="defaultTaxRate" name="defaultTaxRate" step="0.01" min="0" max="100" value="15" data-setting-key="default_tax_rate" data-setting-type="number">
                                </div>
                                <div class="setting-item">
                                        <label for="taxMethod">
                                            <span>Tax Calculation Method</span>
                                            <span class="setting-help" title="Inclusive: Tax included in price. Exclusive: Tax added to price">?</span>
                                        </label>
                                        <select id="taxMethod" name="taxMethod" data-setting-key="tax_calculation_method" data-setting-type="text">
                                        <option value="inclusive">Tax Inclusive</option>
                                        <option value="exclusive">Tax Exclusive</option>
                                    </select>
                                </div>
                                    <div class="setting-item">
                                        <label for="fiscalYearStart">
                                            <span>Fiscal Year Start Date</span>
                                            <span class="setting-help" title="The start date of your fiscal year">?</span>
                                        </label>
                                        <input type="text" id="fiscalYearStart" name="fiscalYearStart" class="date-input" value="${fiscalYearStart}" data-setting-key="fiscal_year_start" data-setting-type="date" placeholder="MM/DD/YYYY">
                            </div>
                                    <div class="setting-item">
                                        <label for="fiscalYearEnd">
                                            <span>Fiscal Year End Date</span>
                                            <span class="setting-help" title="The end date of your fiscal year">?</span>
                                        </label>
                                        <input type="text" id="fiscalYearEnd" name="fiscalYearEnd" class="date-input" value="${fiscalYearEnd}" data-setting-key="fiscal_year_end" data-setting-type="date" placeholder="MM/DD/YYYY">
                        </div>
                                <div class="setting-item">
                                        <label for="defaultCurrency">
                                            <span>Default Currency</span>
                                            <span class="setting-help" title="The default currency used throughout the system">?</span>
                                        </label>
                                        <select id="defaultCurrency" name="defaultCurrency" data-setting-key="default_currency" data-setting-type="text">
                                            ${currencyOptionsHTML}
                                        </select>
                                </div>
                                <div class="setting-item">
                                        <label for="numberFormat">
                                            <span>Number Format</span>
                                            <span class="setting-help" title="How numbers are displayed">?</span>
                                        </label>
                                        <select id="numberFormat" name="numberFormat" data-setting-key="number_format" data-setting-type="text">
                                            <option value="standard">Standard (1,234.56)</option>
                                            <option value="european">European (1.234,56)</option>
                                            <option value="indian">Indian (12,34,567.89)</option>
                                        </select>
                                </div>
                                    <div class="setting-item">
                                        <label for="decimalPlaces">
                                            <span>Decimal Places</span>
                                            <span class="setting-help" title="Number of decimal places for amounts">?</span>
                                        </label>
                                        <select id="decimalPlaces" name="decimalPlaces" data-setting-key="decimal_places" data-setting-type="number">
                                            <option value="0">0</option>
                                            <option value="2" selected>2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                        </select>
                            </div>
                                    <div class="setting-item">
                                        <label for="thousandSeparator">
                                            <span>Thousand Separator</span>
                                            <span class="setting-help" title="Character used to separate thousands">?</span>
                                        </label>
                                        <select id="thousandSeparator" name="thousandSeparator" data-setting-key="thousand_separator" data-setting-type="text">
                                            <option value="comma" selected>Comma (,)</option>
                                            <option value="period">Period (.)</option>
                                            <option value="space">Space ( )</option>
                                        </select>
                        </div>
                                <div class="setting-item">
                                        <label for="accountingMethod">
                                            <span>Accounting Method</span>
                                            <span class="setting-help" title="Cash: Record when money is received/paid. Accrual: Record when transaction occurs">?</span>
                                        </label>
                                        <select id="accountingMethod" name="accountingMethod" data-setting-key="accounting_method" data-setting-type="text">
                                            <option value="accrual" selected>Accrual Basis</option>
                                            <option value="cash">Cash Basis</option>
                                    </select>
                                </div>
                                    <div class="setting-item">
                                        <label for="autoNumbering">
                                            <span>Auto Numbering</span>
                                            <span class="setting-help" title="Automatically generate numbers for vouchers, invoices, etc.">?</span>
                                        </label>
                                        <select id="autoNumbering" name="autoNumbering" data-setting-key="auto_numbering" data-setting-type="text">
                                            <option value="enabled" selected>Enabled</option>
                                            <option value="disabled">Disabled</option>
                                        </select>
                            </div>
                                    <div class="setting-item">
                                        <label for="requireApproval">
                                            <span>Require Approval</span>
                                            <span class="setting-help" title="Require approval for transactions above a certain amount">?</span>
                                        </label>
                                        <select id="requireApproval" name="requireApproval" data-setting-key="require_approval" data-setting-type="text">
                                            <option value="disabled" selected>Disabled</option>
                                            <option value="enabled">Enabled</option>
                                        </select>
                                    </div>
                                    <div class="setting-item">
                                        <label for="approvalThreshold">
                                            <span>Approval Threshold</span>
                                            <span class="setting-help" title="Amount above which approval is required">?</span>
                                        </label>
                                        <input type="number" id="approvalThreshold" name="approvalThreshold" step="0.01" min="0" value="0" data-setting-key="approval_threshold" data-setting-type="number">
                                    </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        this.showModal('Accounting Settings', content, 'large', 'accountingSettingsModal');
        
        setTimeout(async () => {
            await this.loadSettings();
            this.setupSettingsHandlers();
            this.setupSettingsFilters();
            this.updateSettingsSummary();
            
            // Add change listeners to update summary cards
            const inputs = document.querySelectorAll('#accountingSettingsModal input, #accountingSettingsModal select');
            inputs.forEach(input => {
                input.addEventListener('change', () => {
                    this.updateSettingsSummary();
                    input.classList.add('setting-changed');
                });
            });
        }, 100);
    }
    
ProfessionalAccounting.prototype.setupSettingsFilters = function() {
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
    }
    
ProfessionalAccounting.prototype.filterSettings = function(searchTerm) {
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
    }
    
ProfessionalAccounting.prototype.loadSettings = async function() {
        try {
            const response = await fetch(`${this.apiBase}/settings.php`);
            const data = await response.json();
            
            if (data.success && data.settings) {
                const settingsMap = {};
                data.settings.forEach(setting => {
                    settingsMap[setting.key] = setting.value;
                });
                
                // Load settings into form fields
                const settingInputs = document.querySelectorAll('#accountingSettingsModal [data-setting-key]');
                settingInputs.forEach(input => {
                    const key = input.getAttribute('data-setting-key');
                    const value = settingsMap[key];
                    
                    if (value !== undefined && value !== null) {
                        if (input.type === 'checkbox') {
                            input.checked = value === true || value === '1' || value === 1;
                        } else if (input.type === 'date') {
                            input.value = value;
                        } else if (input.tagName === 'SELECT') {
                            // For select dropdowns, try to match value
                            const option = Array.from(input.options).find(opt => opt.value === value || opt.value === String(value));
                            if (option) {
                                input.value = option.value;
                            } else if (input.id === 'defaultCurrency') {