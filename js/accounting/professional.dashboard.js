/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.dashboard.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.dashboard.js`.
 */
/**
 * Professional Accounting - Dashboard
 * Load AFTER professional.js
 */
(function(){
    if (typeof ProfessionalAccounting === 'undefined') return;
    const methods = {
        updateOverviewCards(data) {
            // Revenue
            const revenueEl = document.getElementById('totalRevenue');
            if (revenueEl) revenueEl.textContent = this.formatCurrency(data.total_revenue || 0);
            
            // Expenses
            const expenseEl = document.getElementById('totalExpense');
            if (expenseEl) expenseEl.textContent = this.formatCurrency(data.total_expenses || 0);
            
            // Net Profit
            const profitEl = document.getElementById('netProfit');
            if (profitEl) profitEl.textContent = this.formatCurrency(data.net_profit || 0);
            
            // Cash Balance
            const balanceEl = document.getElementById('cashBalance');
            if (balanceEl) balanceEl.textContent = this.formatCurrency(data.cash_balance || 0);
            
            // Receivables
            const receivablesEl = document.getElementById('totalReceivables');
            if (receivablesEl) receivablesEl.textContent = this.formatCurrency(data.total_receivables || 0);
            
            const receivablesCount = document.getElementById('receivablesCount');
            if (receivablesCount) {
                receivablesCount.textContent = `${data.receivables_count || 0} invoices`;
            }
            
            // Payables
            const payablesEl = document.getElementById('totalPayables');
            if (payablesEl) payablesEl.textContent = this.formatCurrency(data.total_payables || 0);
            
            const payablesCount = document.getElementById('payablesCount');
            if (payablesCount) {
                payablesCount.textContent = `${data.payables_count || 0} bills`;
            }
            // Changes (if available)
            const revenueChange = document.getElementById('revenueChange');
            if (revenueChange && data.revenue_change) {
                revenueChange.textContent = `${data.revenue_change > 0 ? '+' : ''}${data.revenue_change.toFixed(1)}%`;
                revenueChange.className = `card-change ${data.revenue_change >= 0 ? 'positive' : 'negative'}`;
            }
        },

        ensureQuickActionsVisible() {
            const quickActionsWidget = document.querySelector('.quick-actions-widget');
            if (quickActionsWidget) {
                quickActionsWidget.classList.remove('hidden');
            }
            
            const quickActionsGrid = document.querySelector('.quick-actions-grid');
            if (quickActionsGrid) {
                quickActionsGrid.classList.remove('hidden');
            }
            
            const quickActionButtons = document.querySelectorAll('.quick-action-btn');
            quickActionButtons.forEach(btn => {
                btn.classList.remove('hidden');
            });
        },

        async loadRecentTransactions() {
            const loadingEl = document.getElementById('recentTransactionsLoading');
            const containerEl = document.getElementById('recentTransactionsContainer');
            const tableEl = document.getElementById('recentTransactionsTable');
            const tbodyEl = document.getElementById('recentTransactions');
            
            // Skip if modal is open to prevent flashing
            // But only skip if modal is actually visible (not just in DOM)
            const anyModalOpen = document.querySelector('.accounting-modal.accounting-modal-visible, .accounting-modal.show-modal');
            if (anyModalOpen && !anyModalOpen.classList.contains('accounting-modal-hidden')) {
                return;
            }
            
            if (loadingEl) {
                loadingEl.classList.add('visible-flex');
                loadingEl.classList.remove('hidden');
            }
            // Use opacity instead of display to prevent layout shift
            if (containerEl) {
                containerEl.classList.add('recent-transactions-loading', 'table-container-loading');
                containerEl.classList.remove('recent-transactions-visible', 'recent-transactions-fade-in', 'hidden');
            }
            if (tbodyEl) {
                // Keep table structure visible even when empty
                if (!tbodyEl.innerHTML.trim()) {
                    tbodyEl.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading transactions...</p></td></tr>';
                }
            }
            try {
                const params = new URLSearchParams({
                    limit: 5,
                    page: 1
                });
                const response = await fetch(`${this.apiBase}/transactions.php?${params.toString()}`);
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadRecentTransactions:', jsonError);
                    data = { success: false, transactions: [] };
                }
                if (loadingEl) {
                    loadingEl.classList.add('hidden', 'loading-hidden');
                    loadingEl.classList.remove('visible-flex');
                }
                if (containerEl) {
                    // Remove loading class FIRST, then set opacity
                    containerEl.classList.remove('recent-transactions-loading', 'table-container-loading', 'hidden');
                    containerEl.classList.add('recent-transactions-fade-in', 'visible', 'recent-transactions-visible', 'table-container-visible');
                    
                    // Double-check after a frame
                    requestAnimationFrame(() => {
                        containerEl.classList.remove('recent-transactions-fade-in');
                        containerEl.classList.add('recent-transactions-visible', 'table-container-visible');
                    });
                }
                if (data.success && data.transactions && data.transactions.length > 0) {
                    if (tbodyEl) {
                        const tableHTML = data.transactions.map(t => {
                            const amount = parseFloat(t.total_amount || 0);
                            const isIncome = t.transaction_type === 'Income';
                            const statusClass = t.status === 'Posted' ? 'status-posted' : t.status === 'Draft' ? 'status-draft' : 'status-pending';
                            const debitAmount = isIncome ? amount : 0;
                            const creditAmount = isIncome ? 0 : amount;
                            
                            return `
                                <tr>
                                    <td>${this.formatDate(t.transaction_date)}</td>
                                    <td>${this.escapeHtml(t.description || 'N/A')}</td>
                                    <td class="debit-cell">${debitAmount > 0 ? this.formatCurrency(debitAmount, t.currency || this.getDefaultCurrencySync()) : '<span class="text-muted">-</span>'}</td>
                                    <td class="credit-cell">${creditAmount > 0 ? this.formatCurrency(creditAmount, t.currency || this.getDefaultCurrencySync()) : '<span class="text-muted">-</span>'}</td>
                                    <td><span class="status-badge ${statusClass}">${t.status || 'Pending'}</span></td>
                                </tr>
                            `;
                        }).join('');
                        
                        tbodyEl.innerHTML = tableHTML;
                        
                        // Force tbody and all rows to be visible with maximum priority
                        tbodyEl.classList.add('table-row-group-visible');
                        
                        Array.from(tbodyEl.children).forEach((tr, index) => {
                            tr.classList.add('table-row-visible');
                            // Also force all cells to be visible with text color
                            Array.from(tr.children).forEach(td => {
                                td.classList.add('table-cell-visible');
                            });
                        });
                        
                        // Force table and container z-index to ensure it's on top
                        if (tableEl) {
                            tableEl.classList.add('table-elevated');
                        }
                        if (containerEl) {
                            containerEl.classList.add('table-elevated');
                        }
                        
                        // Check if table is in viewport and scroll to it if needed
                        setTimeout(() => {
                            if (tableEl) {
                                const tableRect = tableEl.getBoundingClientRect();
                                // If table has dimensions but is not in viewport, scroll to it
                                if (tableRect.width > 0 && tableRect.height > 0 && 
                                    (tableRect.top < 0 || tableRect.left < 0 || 
                                     tableRect.bottom > window.innerHeight || tableRect.right > window.innerWidth)) {
                                    tableEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                }
                            }
                        }, 200);
                        
                        // Check if table is actually in viewport
                        const tableRect = tableEl.getBoundingClientRect();
                        
                        // Ensure container opacity is 1 after loading (not 0.5 from loading state)
                        if (containerEl) {
                            // Remove loading class FIRST - this removes the CSS rule with opacity: 0.5 !important
                            containerEl.classList.remove('recent-transactions-loading', 'table-container-loading');
                            // Also remove it from classList multiple times to ensure it's gone
                            while (containerEl.classList.contains('recent-transactions-loading')) {
                                containerEl.classList.remove('recent-transactions-loading');
                            }
                            
                            containerEl.classList.add('recent-transactions-visible', 'visible', 'table-container-visible');
                        }
                        
                        if (tableEl) {
                            tableEl.classList.add('table-visible');
                        }
                        
                        // Check widget visibility
                        const widget = containerEl?.closest('.transactions-widget');
                        if (widget) {
                            widget.classList.add('visible');
                        }
                    }
                } else {
                    if (tbodyEl) {
                        tbodyEl.innerHTML = '<tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i><p>No recent transactions</p></td></tr>';
                    }
                    if (containerEl) {
                        containerEl.classList.add('visible', 'table-container-visible');
                        containerEl.classList.remove('hidden');
                    }
                    }
            } catch (error) {
                if (loadingEl) {
                    loadingEl.classList.add('hidden', 'loading-hidden');
                    loadingEl.classList.remove('visible-flex');
                }
                if (containerEl) {
                    containerEl.classList.add('visible', 'table-container-visible');
                    containerEl.classList.remove('hidden', 'recent-transactions-loading', 'table-container-loading');
                }
                if (tbodyEl) {
                    tbodyEl.innerHTML = '<tr><td colspan="4" class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading transactions</p></td></tr>';
                }
            }
        },

        async loadCashFlowSummary() {
            try {
                const response = await fetch(`${this.apiBase}/unified-calculations.php?type=all`);
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadCashFlowSummary:', jsonError);
                    return; // Silently fail if JSON parsing fails
                }
                if (data.success && data.dashboard) {
                    const cashIn = parseFloat(data.dashboard.total_revenue || 0);
                    const cashOut = parseFloat(data.dashboard.total_expenses || 0);
                    const netFlow = cashIn - cashOut;
                    const cashInEl = document.getElementById('cashInAmount');
                    const cashOutEl = document.getElementById('cashOutAmount');
                    const netFlowEl = document.getElementById('netFlowAmount');
                    if (cashInEl) {
                        cashInEl.textContent = this.formatCurrency(cashIn);
                }
                    if (cashOutEl) {
                        cashOutEl.textContent = this.formatCurrency(cashOut);
                    }
                    if (netFlowEl) {
                        netFlowEl.textContent = this.formatCurrency(netFlow);
                        netFlowEl.className = 'cashflow-value ' + (netFlow >= 0 ? 'positive' : 'negative');
                }
                }
            } catch (error) {
            }
        },

        async loadFinancialSummary() {
            try {
                const response = await fetch(`${this.apiBase}/unified-calculations.php?type=all`);
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadFinancialSummary:', jsonError);
                    return; // Silently fail if JSON parsing fails
                }
                if (data.success && data.dashboard) {
                    // Calculate assets (cash balance + receivables)
                    const assets = parseFloat(data.dashboard.cash_balance || 0) + parseFloat(data.dashboard.total_receivables || 0);
                    // Liabilities (payables)
                    const liabilities = parseFloat(data.dashboard.total_payables || 0);
                    // Equity (assets - liabilities)
                    const equity = assets - liabilities;
                    const assetsEl = document.getElementById('totalAssets');
                    const liabilitiesEl = document.getElementById('totalLiabilities');
                    const equityEl = document.getElementById('totalEquity');
                    if (assetsEl) {
                        assetsEl.textContent = this.formatCurrency(assets);
                    }
                    if (liabilitiesEl) {
                        liabilitiesEl.textContent = this.formatCurrency(liabilities);
                    }
                    if (equityEl) {
                        equityEl.textContent = this.formatCurrency(equity);
                        equityEl.className = 'summary-value ' + (equity >= 0 ? 'positive' : 'negative');
                    }
                }
            } catch (error) {
            }
        },

        updateRecentTransactionsPagination(totalCountOverride = null) {
            if (typeof totalCountOverride === 'number') {
                this.transactionsTotalCount = totalCountOverride;
            }
            const indicatorEl = document.getElementById('recentPageIndicator');
            const infoEl = document.getElementById('recentPaginationInfo');
            const prevBtn = document.getElementById('recentTransactionsPrev');
            const nextBtn = document.getElementById('recentTransactionsNext');
            const pageSizeSelect = document.getElementById('recentTransactionsPageSize');
            if (pageSizeSelect) {
                pageSizeSelect.value = this.transactionsPerPage.toString();
            }
            const total = this.transactionsTotalCount || 0;
            const start = total === 0 ? 0 : (this.transactionsCurrentPage - 1) * this.transactionsPerPage + 1;
            const end = total === 0 ? 0 : Math.min(total, this.transactionsCurrentPage * this.transactionsPerPage);
            const hasMore = end < total;
            if (indicatorEl) {
                indicatorEl.textContent = `${this.transactionsCurrentPage}`;
            }
            if (infoEl) {
                infoEl.textContent = total > 0
                    ? `Showing ${start} to ${end} of ${total} entries`
                    : 'No entries to display';
            }
            if (prevBtn) {
                prevBtn.disabled = this.transactionsCurrentPage <= 1;
            }
            if (nextBtn) {
                nextBtn.disabled = total === 0 || !hasMore;
            }
            this.transactionsTotalPages = total > 0 ? Math.max(1, Math.ceil(total / this.transactionsPerPage)) : 1;
            this.renderRecentTransactionsPageButtons();
        },

        setupRecentTransactionsPaginationControls() {
            const prevBtn = document.getElementById('recentTransactionsPrev');
            const nextBtn = document.getElementById('recentTransactionsNext');
            const pageSizeSelect = document.getElementById('recentTransactionsPageSize');
            if (prevBtn) {
                prevBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (this.transactionsCurrentPage > 1) {
                        this.transactionsCurrentPage -= 1;
                        this.loadRecentTransactions();
                    }
                });
            }
            if (nextBtn) {
                nextBtn.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (this.transactionsCurrentPage < this.transactionsTotalPages) {
                        this.transactionsCurrentPage += 1;
                        this.loadRecentTransactions();
                    }
                });
            }
            if (pageSizeSelect) {
                pageSizeSelect.value = this.transactionsPerPage.toString();
                pageSizeSelect.addEventListener('change', (event) => {
                    const target = event.target;
                    const value = parseInt(target.value, 10);
                    this.transactionsPerPage = Number.isNaN(value) ? 5 : Math.max(1, value);
                    this.transactionsCurrentPage = 1;
                    this.loadRecentTransactions();
                });
            }
            const paginationNumbers = document.getElementById('recentPaginationNumbers');
            if (paginationNumbers) {
                paginationNumbers.addEventListener('click', (event) => {
                    const button = event.target.closest('.pagination-number');
                    if (!button) return;
                    const targetPage = parseInt(button.dataset.page, 10);
                    if (Number.isNaN(targetPage) || targetPage === this.transactionsCurrentPage) return;
                    this.transactionsCurrentPage = targetPage;
                    this.loadRecentTransactions();
                });
            }
        },

        initializeDates() {
            const today = this.formatDateForInput(new Date().toISOString().split('T')[0]);
            const dateFrom = document.getElementById('ledgerDateFrom');
            const dateTo = document.getElementById('ledgerDateTo');
            if (dateFrom) {
                const firstDay = new Date();
                firstDay.setDate(1);
                dateFrom.value = this.formatDateForInput(firstDay.toISOString());
            }
            if (dateTo) {
                dateTo.value = today;
            }
        },

        initializeEnglishDatePickers(container = document) {
            if (typeof window.initializeEnglishDatePickers === 'function') {
                window.initializeEnglishDatePickers(container);
                return;
            }
            if (typeof flatpickr === 'undefined') {
                setTimeout(() => {
                    this.initializeEnglishDatePickers(container);
                }, 100);
                return;
            }
            const dateInputs = container.querySelectorAll('input[type="date"], input.date-input');
            dateInputs.forEach((input) => {
                if (input._flatpickr) return;
                const originalType = input.type;
                const originalValue = input.value;
                if (input.type === 'date') input.type = 'text';
                try {
                    const englishLocale = {
                        weekdays: { shorthand: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], longhand: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] },
                        months: { shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], longhand: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] },
                        firstDayOfWeek: 0, rangeSeparator: ' to ', weekAbbreviation: 'Wk', scrollTitle: 'Scroll to increment', toggleTitle: 'Click to toggle', amPM: ['AM', 'PM'],
                        yearAriaLabel: 'Year', monthAriaLabel: 'Month', hourAriaLabel: 'Hour', minuteAriaLabel: 'Minute', time_24hr: false
                    };
                    let dateValue = originalValue || null;
                    if (dateValue && typeof dateValue === 'string') {
                        if (dateValue.match(/^\d{4}-\d{2}-\d{2}$/)) {
                            const parts = dateValue.split('-');
                            dateValue = `${parts[1]}/${parts[2]}/${parts[0]}`;
                            input.value = dateValue;
                        } else if (!dateValue.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
                            try {
                                const date = new Date(dateValue);
                                if (!isNaN(date.getTime())) {
                                    const year = date.getFullYear();
                                    const month = String(date.getMonth() + 1).padStart(2, '0');
                                    const day = String(date.getDate()).padStart(2, '0');
                                    dateValue = `${month}/${day}/${year}`;
                                    input.value = dateValue;
                                }
                            } catch (e) {}
                        }
                    }
                    flatpickr(input, { locale: englishLocale, dateFormat: 'm/d/Y', altInput: false, allowInput: true, enableTime: false, time_24hr: false, defaultDate: dateValue, clickOpens: true });
                } catch (e) {
                    console.warn('Failed to initialize Flatpickr on date input:', e);
                    input.type = originalType;
                }
            });
        },

        async checkTablesExist() {
            try {
                const response = await fetch(`${this.apiBase}/accounts.php?is_active=1`);
                const data = await response.json();
                const setupButton = document.querySelector('[data-action="setup-tables"]');
                if (setupButton) {
                    setupButton.style.display = (data.success && data.accounts && data.accounts.length > 0) ? 'none' : '';
                }
            } catch (error) {}
        },

        renderRecentTransactionsPageButtons() {
            const container = document.getElementById('recentPaginationNumbers');
            if (!container) return;
            const totalPages = this.transactionsTotalPages;
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }
            const maxButtons = 5;
            let startPage = Math.max(1, this.transactionsCurrentPage - 2);
            let endPage = Math.min(totalPages, startPage + maxButtons - 1);
            if (endPage - startPage + 1 < maxButtons) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }
            const buttons = [];
            for (let i = startPage; i <= endPage; i += 1) {
                const isActive = i === this.transactionsCurrentPage;
                buttons.push(`
                    <button type="button" class="pagination-number${isActive ? ' active' : ''}" data-page="${i}">
                        ${i}
                    </button>
                `);
            }
            container.innerHTML = buttons.join('');
        },

        getChartStyles() {
            const root = getComputedStyle(document.documentElement);
            return {
                revenueColor: root.getPropertyValue('--chart-revenue-color').trim(),
                revenueBg: root.getPropertyValue('--chart-revenue-bg').trim(),
                expenseColor: root.getPropertyValue('--chart-expense-color').trim(),
                expenseBg: root.getPropertyValue('--chart-expense-bg').trim(),
                netProfitColor: root.getPropertyValue('--chart-netprofit-color').trim(),
                netProfitBgStart: root.getPropertyValue('--chart-netprofit-bg-start').trim(),
                netProfitBgEnd: root.getPropertyValue('--chart-netprofit-bg-end').trim(),
                cashColor: root.getPropertyValue('--chart-cash-color').trim(),
                cashBgStart: root.getPropertyValue('--chart-cash-bg-start').trim(),
                cashBgEnd: root.getPropertyValue('--chart-cash-bg-end').trim(),
                receivableColor: root.getPropertyValue('--chart-receivable-color').trim(),
                receivableBg: root.getPropertyValue('--chart-receivable-bg').trim(),
                payableColor: root.getPropertyValue('--chart-payable-color').trim(),
                payableBg: root.getPropertyValue('--chart-payable-bg').trim(),
                legendColor: root.getPropertyValue('--chart-legend-color').trim(),
                tickColor: root.getPropertyValue('--chart-tick-color').trim(),
                gridColor: root.getPropertyValue('--chart-grid-color').trim(),
                tooltipBg: root.getPropertyValue('--chart-tooltip-bg').trim(),
                tooltipTitle: root.getPropertyValue('--chart-tooltip-title').trim(),
                tooltipBody: root.getPropertyValue('--chart-tooltip-body').trim(),
                tooltipBorder: root.getPropertyValue('--chart-tooltip-border').trim(),
                pointBorder: root.getPropertyValue('--chart-point-border').trim(),
                incomeGradientStart: root.getPropertyValue('--chart-income-gradient-start').trim(),
                incomeGradientEnd: root.getPropertyValue('--chart-income-gradient-end').trim(),
                expenseGradientStart: root.getPropertyValue('--chart-expense-gradient-start').trim(),
                expenseGradientEnd: root.getPropertyValue('--chart-expense-gradient-end').trim(),
                aging0_30: root.getPropertyValue('--chart-aging-0-30').trim(),
                aging31_60: root.getPropertyValue('--chart-aging-31-60').trim(),
                aging61_90: root.getPropertyValue('--chart-aging-61-90').trim(),
                aging90Plus: root.getPropertyValue('--chart-aging-90-plus').trim(),
                breakdown0_30: root.getPropertyValue('--chart-breakdown-0-30').trim(),
                breakdown31_60: root.getPropertyValue('--chart-breakdown-31-60').trim(),
                breakdown61Plus: root.getPropertyValue('--chart-breakdown-61-plus').trim(),
                fontFamily: root.getPropertyValue('--chart-font-family').trim(),
                fontSizeSmall: parseInt(root.getPropertyValue('--chart-font-size-small').trim()) || 11,
                fontSizeMedium: parseInt(root.getPropertyValue('--chart-font-size-medium').trim()) || 13,
                fontSizeLarge: parseInt(root.getPropertyValue('--chart-font-size-large').trim()) || 16,
                fontWeightMedium: root.getPropertyValue('--chart-font-weight-medium').trim(),
                fontWeightBold: root.getPropertyValue('--chart-font-weight-bold').trim(),
                lineWidth: parseInt(root.getPropertyValue('--chart-line-width').trim()) || 3,
                pointRadius: parseInt(root.getPropertyValue('--chart-point-radius').trim()) || 5,
                pointHoverRadius: parseInt(root.getPropertyValue('--chart-point-hover-radius').trim()) || 8,
                pointBorderWidth: parseInt(root.getPropertyValue('--chart-point-border-width').trim()) || 3,
                barBorderRadius: parseInt(root.getPropertyValue('--chart-bar-border-radius').trim()) || 6,
                barBorderWidth: parseInt(root.getPropertyValue('--chart-bar-border-width').trim()) || 2,
                borderRadius: parseInt(root.getPropertyValue('--chart-border-radius').trim()) || 8,
                paddingSm: parseInt(root.getPropertyValue('--chart-padding-sm').trim()) || 10,
                paddingMd: parseInt(root.getPropertyValue('--chart-padding-md').trim()) || 12,
                paddingLg: parseInt(root.getPropertyValue('--chart-padding-lg').trim()) || 20,
                tension: parseFloat(root.getPropertyValue('--chart-tension').trim()) || 0.4,
                duration: parseInt(root.getPropertyValue('--chart-duration').trim()) || 2000,
                donutBorderWidth: parseInt(root.getPropertyValue('--chart-donut-border-width').trim()) || 3,
                donutBorderColor: root.getPropertyValue('--chart-donut-border-color').trim()
            };
        },

        async loadRevenueExpenseNetChart() {
            const period = document.getElementById('revenueExpenseNetPeriod')?.value || 30;
            
            try {
                const response = await fetch(`${this.apiBase}/chart-data.php?period=${period}`);
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadRevenueExpenseNetChart:', jsonError);
                    return; // Silently fail if JSON parsing fails
                }
                if (data.success && data.chart_data) {
                    this.renderRevenueExpenseNetChart(data.chart_data);
                }
            } catch (error) {
                console.error('Error loading revenue expense net chart:', error);
            }
        },

        renderRevenueExpenseNetChart(chartData) {
            const canvas = document.getElementById('revenueExpenseNetChart');
            if (!canvas || typeof Chart === 'undefined') return;
            
            if (this.revenueExpenseNetChart) {
                this.revenueExpenseNetChart.destroy();
            }
            const styles = this.getChartStyles();
                const ctx = canvas.getContext('2d');
                const labels = chartData.map(d => {
                    if (d.date) {
                        const date = new Date(d.date);
                        if (!isNaN(date.getTime())) {
                            const month = date.getMonth();
                            const day = date.getDate();
                            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                            return `${monthNames[month]} ${day}`;
                        }
                        return d.date;
                } else if (d.period) {
                    const date = new Date(d.period);
                    if (!isNaN(date.getTime())) {
                        const month = date.getMonth();
                        const day = date.getDate();
                        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        return `${monthNames[month]} ${day}`;
                    }
                    return d.period;
                    } else if (d.month) {
                        return d.month;
                    }
                    return '';
                });
            const incomeData = chartData.map(d => parseFloat(d.income || 0));
            const expenseData = chartData.map(d => parseFloat(d.expenses || 0));
            const netProfitData = incomeData.map((income, i) => income - expenseData[i]);
            const netProfitGradient = ctx.createLinearGradient(0, 0, 0, 400);
            netProfitGradient.addColorStop(0, styles.netProfitBgStart);
            netProfitGradient.addColorStop(1, styles.netProfitBgEnd);
            this.revenueExpenseNetChart = new Chart(ctx, {
                type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                            label: 'Revenue',
                            data: incomeData,
                            borderColor: styles.revenueColor,
                            backgroundColor: styles.revenueBg,
                            borderWidth: styles.lineWidth,
                            fill: false,
                            tension: styles.tension,
                            pointRadius: styles.pointRadius,
                            pointHoverRadius: styles.pointHoverRadius,
                            pointBackgroundColor: styles.revenueColor,
                            pointBorderColor: styles.pointBorder,
                            pointBorderWidth: styles.pointBorderWidth,
                            pointStyle: 'circle'
                            },
                            {
                                label: 'Expenses',
                            data: expenseData,
                            borderColor: styles.expenseColor,
                            backgroundColor: styles.expenseBg,
                            borderWidth: styles.lineWidth,
                            fill: false,
                            tension: styles.tension,
                            pointRadius: styles.pointRadius,
                            pointHoverRadius: styles.pointHoverRadius,
                            pointBackgroundColor: styles.expenseColor,
                            pointBorderColor: styles.pointBorder,
                            pointBorderWidth: styles.pointBorderWidth,
                            pointStyle: 'circle'
                        },
                        {
                            label: 'Net Profit',
                            data: netProfitData,
                            borderColor: styles.netProfitColor,
                            backgroundColor: netProfitGradient,
                            borderWidth: styles.lineWidth,
                            fill: true,
                            tension: styles.tension,
                            pointRadius: styles.pointRadius,
                            pointHoverRadius: styles.pointHoverRadius,
                            pointBackgroundColor: styles.netProfitColor,
                            pointBorderColor: styles.pointBorder,
                            pointBorderWidth: styles.pointBorderWidth,
                            pointStyle: 'circle'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                    animation: {
                        duration: styles.duration,
                        easing: 'easeOutQuart'
                    },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                            align: 'center',
                                labels: {
                                color: styles.legendColor,
                                font: { size: styles.fontSizeMedium, weight: styles.fontWeightBold, family: styles.fontFamily },
                                padding: styles.paddingLg,
                                usePointStyle: true,
                                pointStyle: 'circle',
                                boxWidth: 12,
                                boxHeight: 12
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                            backgroundColor: styles.tooltipBg,
                            titleColor: styles.tooltipTitle,
                            bodyColor: styles.tooltipBody,
                            borderColor: styles.tooltipBorder,
                            borderWidth: 1,
                            padding: styles.paddingMd,
                            cornerRadius: styles.borderRadius,
                                callbacks: {
                                    label: function(context) {
                                    const value = context.parsed.y;
                                    const formatted = value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    const currency = window.professionalAccounting ? window.professionalAccounting.getDefaultCurrencySync() : 'SAR';
                                    return context.dataset.label + ': ' + currency + ' ' + formatted;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: {
                                color: styles.tickColor,
                                font: { size: styles.fontSizeSmall, weight: styles.fontWeightMedium, family: styles.fontFamily },
                                padding: styles.paddingSm
                            },
                            grid: {
                                color: styles.gridColor,
                                drawBorder: false,
                                lineWidth: 1
                            },
                            border: { display: false }
                        },
                        y: {
                            ticks: {
                                color: styles.tickColor,
                                font: { size: styles.fontSizeSmall, weight: styles.fontWeightMedium, family: styles.fontFamily },
                                padding: styles.paddingMd,
                                callback: function(value) {
                                    const currency = window.professionalAccounting ? window.professionalAccounting.getDefaultCurrencySync() : 'SAR';
                                    if (value >= 1000000) return currency + ' ' + (value / 1000000).toFixed(1) + 'M';
                                    if (value >= 1000) return currency + ' ' + (value / 1000).toFixed(1) + 'K';
                                    return currency + ' ' + value.toLocaleString();
                                    }
                                },
                                grid: {
                                color: styles.gridColor,
                                drawBorder: false,
                                lineWidth: 1
                            },
                            border: { display: false },
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        async loadCashBalanceChart() {
            const period = document.getElementById('cashBalancePeriod')?.value || 30;
            
            try {
                const response = await fetch(`${this.apiBase}/chart-data.php?period=${period}`);
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadCashBalanceChart:', jsonError);
                    return; // Silently fail if JSON parsing fails
                }
                if (data.success && data.chart_data) {
                    this.renderCashBalanceChart(data.chart_data);
                }
            } catch (error) {
                console.error('Error loading cash balance chart:', error);
            }
        },

        renderCashBalanceChart(chartData) {
            const canvas = document.getElementById('cashBalanceChart');
            if (!canvas || typeof Chart === 'undefined') return;
            
            if (this.cashBalanceChart) {
                this.cashBalanceChart.destroy();
            }
            const styles = this.getChartStyles();
            const ctx = canvas.getContext('2d');
            const labels = chartData.map(d => {
                if (d.date) {
                    const date = new Date(d.date);
                    if (!isNaN(date.getTime())) {
                        const month = date.getMonth();
                        const day = date.getDate();
                        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        return `${monthNames[month]} ${day}`;
                    }
                    return d.date;
                } else if (d.period) {
                    const date = new Date(d.period);
                    if (!isNaN(date.getTime())) {
                        const month = date.getMonth();
                        const day = date.getDate();
                        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        return `${monthNames[month]} ${day}`;
                    }
                    return d.period;
                } else if (d.month) {
                    return d.month;
                }
                return '';
            });
            // Calculate cash balance over time (simplified - in real app would fetch from API)
            const incomeData = chartData.map(d => parseFloat(d.income || 0));
            const expenseData = chartData.map(d => parseFloat(d.expenses || 0));
            let runningBalance = 0;
            const cashBalanceData = incomeData.map((income, i) => {
                runningBalance = runningBalance + income - expenseData[i];
                return runningBalance;
            });
            const cashGradient = ctx.createLinearGradient(0, 0, 0, 400);
            cashGradient.addColorStop(0, styles.cashBgStart);
            cashGradient.addColorStop(1, styles.cashBgEnd);
            this.cashBalanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Cash Balance',
                        data: cashBalanceData,
                        borderColor: styles.cashColor,
                        backgroundColor: cashGradient,
                        borderWidth: styles.lineWidth,
                        fill: true,
                        tension: styles.tension,
                        pointRadius: 0,
                        pointHoverRadius: styles.pointHoverRadius,
                        pointBackgroundColor: styles.cashColor,
                        pointBorderColor: styles.pointBorder,
                        pointBorderWidth: styles.pointBorderWidth
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: styles.duration,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                color: styles.legendColor,
                                font: { size: styles.fontSizeMedium, weight: styles.fontWeightBold, family: styles.fontFamily },
                                padding: styles.paddingLg,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: styles.tooltipBg,
                            titleColor: styles.tooltipTitle,
                            bodyColor: styles.tooltipBody,
                            borderColor: styles.tooltipBorder,
                            borderWidth: 1,
                            padding: styles.paddingMd,
                            cornerRadius: styles.borderRadius,
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed.y;
                                    const formatted = value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    const currency = window.professionalAccounting ? window.professionalAccounting.getDefaultCurrencySync() : 'SAR';
                                    return 'Cash Balance: ' + currency + ' ' + formatted;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                                ticks: {
                                color: styles.tickColor,
                                font: { size: styles.fontSizeSmall, weight: styles.fontWeightMedium, family: styles.fontFamily },
                                padding: styles.paddingSm
                            },
                            grid: {
                                color: styles.gridColor,
                                drawBorder: false,
                                lineWidth: 1
                            },
                            border: { display: false }
                        },
                        y: {
                            ticks: {
                                color: styles.tickColor,
                                font: { size: styles.fontSizeSmall, weight: styles.fontWeightMedium, family: styles.fontFamily },
                                padding: styles.paddingMd,
                                    callback: function(value) {
                                    const currency = window.professionalAccounting ? window.professionalAccounting.getDefaultCurrencySync() : 'SAR';
                                    if (value >= 1000000) return currency + ' ' + (value / 1000000).toFixed(1) + 'M';
                                    if (value >= 1000) return currency + ' ' + (value / 1000).toFixed(1) + 'K';
                                        return currency + ' ' + value.toLocaleString();
                                    }
                                },
                                grid: {
                                color: 'rgba(51, 65, 85, 0.3)',
                                drawBorder: false,
                                lineWidth: 1
                                },
                            border: { display: false },
                                beginAtZero: true
                            }
                        }
                    }
                });
        },

        async loadReceivablePayableChart() {
            try {
                const [receivablesRes, payablesRes] = await Promise.all([
                    fetch(`${this.apiBase}/invoices.php`),
                    fetch(`${this.apiBase}/bills.php`)
                ]);
                let receivablesData, payablesData;
                try {
                    receivablesData = await receivablesRes.json();
                } catch (jsonError) {
                    console.error('Error parsing receivables response:', jsonError);
                    receivablesData = { summary: { total_outstanding: 0 }, invoices: [] };
                }
                try {
                    payablesData = await payablesRes.json();
                } catch (jsonError) {
                    console.error('Error parsing payables response:', jsonError);
                    payablesData = { summary: { total_outstanding: 0 }, bills: [] };
                }
                const receivablesTotal = receivablesData.summary?.total_outstanding || 0;
                const receivablesCount = receivablesData.invoices?.length || 0;
                const payablesTotal = payablesData.summary?.total_outstanding || 0;
                const payablesCount = payablesData.bills?.length || 0;
                this.renderReceivablePayableChart(receivablesTotal, receivablesCount, payablesTotal, payablesCount);
            } catch (error) {
            }
        },

        renderReceivablePayableChart(receivablesTotal, receivablesCount, payablesTotal, payablesCount) {
            const canvas = document.getElementById('receivablePayableChart');
            if (!canvas || typeof Chart === 'undefined') return;
            
            if (this.receivablePayableChart) {
                this.receivablePayableChart.destroy();
            }
            const styles = this.getChartStyles();
            const ctx = canvas.getContext('2d');
            this.receivablePayableChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Accounts Receivable', 'Accounts Payable'],
                    datasets: [{
                        label: 'Amount',
                        data: [receivablesTotal, payablesTotal],
                        backgroundColor: [styles.receivableBg, styles.payableBg],
                        borderColor: [styles.receivableColor, styles.payableColor],
                        borderWidth: styles.barBorderWidth,
                        borderRadius: styles.barBorderRadius,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: styles.duration,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: styles.tooltipBg,
                            titleColor: styles.tooltipTitle,
                            bodyColor: styles.tooltipBody,
                            borderColor: styles.tooltipBorder,
                            borderWidth: 1,
                            padding: styles.paddingMd,
                            cornerRadius: styles.borderRadius,
                            callbacks: {
                                afterLabel: function(context) {
                                    if (context.dataIndex === 0) {
                                        return `${receivablesCount} Invoices Pending`;
            } else {
                                        return `${payablesCount} Bills Due`;
                                    }
                                },
                                label: function(context) {
                                    const value = context.parsed.y;
                                    const formatted = value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    const currency = window.professionalAccounting ? window.professionalAccounting.getDefaultCurrencySync() : 'SAR';
                                    return 'Amount: ' + currency + ' ' + formatted;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: styles.tickColor,
                                font: { size: styles.fontSizeSmall, weight: styles.fontWeightMedium, family: styles.fontFamily },
                                padding: styles.paddingSm
                            },
                            grid: {
                                display: false
                            },
                            border: { display: false }
                        },
                        y: {
                            ticks: {
                                color: styles.tickColor,
                                font: { size: styles.fontSizeSmall, weight: styles.fontWeightMedium, family: styles.fontFamily },
                                padding: styles.paddingMd,
                                callback: function(value) {
                                    if (value >= 1000000) return 'SAR ' + (value / 1000000).toFixed(1) + 'M';
                                    if (value >= 1000) return 'SAR ' + (value / 1000).toFixed(1) + 'K';
                                    return 'SAR ' + value.toLocaleString();
                                }
                            },
                            grid: {
                                color: 'rgba(51, 65, 85, 0.3)',
                                drawBorder: false,
                                lineWidth: 1
                            },
                            border: { display: false },
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        async loadExpenseBreakdownChart() {
            try {
                const response = await fetch(`${this.apiBase}/dashboard.php`);
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadExpenseBreakdownChart:', jsonError);
                    return; // Silently fail if JSON parsing fails
                }
                if (data.success) {
                    // Simplified expense breakdown - in real app would fetch from categories API
                    const expenseData = [
                        { label: '0-30 Days', value: 6 },
                        { label: '31-60 Days', value: 2 },
                        { label: '61+ Days', value: 1 }
                    ];
                    this.renderExpenseBreakdownChart(expenseData);
                }
            } catch (error) {
                console.error('Error loading expense breakdown chart:', error);
            }
        },

        renderExpenseBreakdownChart(data) {
            const canvas = document.getElementById('expenseBreakdownChart');
            if (!canvas || typeof Chart === 'undefined') return;
            
            if (this.expenseBreakdownChart) {
                this.expenseBreakdownChart.destroy();
            }
            const styles = this.getChartStyles();
                const ctx = canvas.getContext('2d');
            this.expenseBreakdownChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{
                        label: 'Count',
                        data: data.map(d => d.value),
                        backgroundColor: [styles.breakdown0_30, styles.breakdown31_60, styles.breakdown61Plus],
                        borderColor: [styles.revenueColor, styles.cashColor, styles.expenseColor],
                        borderWidth: styles.barBorderWidth,
                        borderRadius: styles.barBorderRadius
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: styles.duration,
                        easing: 'easeOutQuart'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: styles.tooltipBg,
                            titleColor: styles.tooltipTitle,
                            bodyColor: styles.tooltipBody,
                            borderColor: styles.tooltipBorder,
                            borderWidth: 1,
                            padding: styles.paddingMd,
                            cornerRadius: styles.borderRadius,
                            callbacks: {
                                label: function(context) {
                                    return 'Count: ' + context.parsed.x;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: styles.tickColor,
                                font: { size: styles.fontSizeSmall, weight: styles.fontWeightMedium, family: styles.fontFamily },
                                padding: styles.paddingSm
                            },
                            grid: {
                                color: styles.gridColor,
                                drawBorder: false,
                                lineWidth: 1
                            },
                            border: { display: false },
                            beginAtZero: true
                        },
                        y: {
                            ticks: {
                                color: styles.tickColor,
                                font: { size: styles.fontSizeSmall, weight: styles.fontWeightMedium, family: styles.fontFamily },
                                padding: styles.paddingSm
                            },
                            grid: {
                                display: false
                            },
                            border: { display: false }
                        }
                    }
                }
            });
        },

        async loadInvoiceAgingChart() {
            try {
                const response = await fetch(`${this.apiBase}/dashboard.php`);
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadInvoiceAgingChart:', jsonError);
                    return; // Silently fail if JSON parsing fails
                }
                if (data.success) {
                    // Simplified invoice aging - in real app would fetch from invoices API
                    const agingData = [
                        { label: '0-30 Days', value: 60 },
                        { label: '31-60 Days', value: 25 },
                        { label: '61-90 Days', value: 10 },
                        { label: '90+ Days', value: 5 }
                    ];
                    this.renderInvoiceAgingChart(agingData);
                }
            } catch (error) {
                console.error('Error loading invoice aging chart:', error);
            }
        },

        renderInvoiceAgingChart(data) {
            const canvas = document.getElementById('invoiceAgingChart');
            if (!canvas || typeof Chart === 'undefined') return;
            
            if (this.invoiceAgingChart) {
                this.invoiceAgingChart.destroy();
            }
            const styles = this.getChartStyles();
            const ctx = canvas.getContext('2d');
            const total = data.reduce((sum, d) => sum + d.value, 0);
            this.invoiceAgingChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{
                        data: data.map(d => d.value),
                        backgroundColor: [
                            styles.aging0_30,
                            styles.aging31_60,
                            styles.aging61_90,
                            styles.aging90Plus
                        ],
                        borderColor: [styles.revenueColor, styles.receivableColor, styles.cashColor, styles.expenseColor],
                        borderWidth: styles.donutBorderWidth
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    animation: {
                        animateRotate: true,
                        duration: styles.duration
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                color: styles.legendColor,
                                font: { size: styles.fontSizeSmall, weight: styles.fontWeightMedium, family: styles.fontFamily },
                                padding: styles.paddingMd,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: styles.tooltipBg,
                            titleColor: styles.tooltipTitle,
                            bodyColor: styles.tooltipBody,
                            borderColor: styles.tooltipBorder,
                            borderWidth: 1,
                            padding: styles.paddingMd,
                            cornerRadius: styles.borderRadius,
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed;
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return context.label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'centerText',
                    beforeDraw: function(chart) {
                        const ctx = chart.ctx;
                        const centerX = chart.chartArea.left + (chart.chartArea.right - chart.chartArea.left) / 2;
                        const centerY = chart.chartArea.top + (chart.chartArea.bottom - chart.chartArea.top) / 2;
                        
                        ctx.save();
                        ctx.font = `bold ${styles.fontSizeLarge}px ${styles.fontFamily}`;
                        ctx.fillStyle = styles.legendColor;
                ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText('Total', centerX, centerY - 10);
                        
                        ctx.font = `bold ${styles.fontSizeMedium}px ${styles.fontFamily}`;
                        ctx.fillStyle = styles.receivableColor;
                        ctx.fillText(total.toString(), centerX, centerY + 10);
                        ctx.restore();
            }
                }]
            });
        },

        async loadFinancialOverviewChart() {
            try {
                const response = await fetch(`${this.apiBase}/dashboard.php`);
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in loadFinancialOverviewChart:', jsonError);
                    return; // Silently fail if JSON parsing fails
                }
                if (data.success && data.stats) {
                    const totalIncome = data.stats.total_income || 0;
                    const totalExpense = data.stats.total_expense || 0;
                    this.renderFinancialOverviewChart(totalIncome, totalExpense);
                }
            } catch (error) {
            }
        },

        renderFinancialOverviewChart(totalIncome, totalExpense) {
            const canvas = document.getElementById('financialOverviewChart');
            if (!canvas || typeof Chart === 'undefined') return;
            
            if (this.financialOverviewChart) {
                this.financialOverviewChart.destroy();
            }
            const styles = this.getChartStyles();
            const ctx = canvas.getContext('2d');
            const grandTotal = totalIncome + totalExpense;
            const incomeGradient = ctx.createLinearGradient(0, 0, 0, 400);
            incomeGradient.addColorStop(0, styles.incomeGradientStart);
            incomeGradient.addColorStop(1, styles.incomeGradientEnd);
            const expenseGradient = ctx.createLinearGradient(0, 0, 0, 400);
            expenseGradient.addColorStop(0, styles.expenseGradientStart);
            expenseGradient.addColorStop(1, styles.expenseGradientEnd);
            this.financialOverviewChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Income', 'Expenses'],
                    datasets: [{
                        data: [totalIncome, totalExpense],
                        backgroundColor: [incomeGradient, expenseGradient],
                        borderColor: [styles.donutBorderColor, styles.donutBorderColor],
                        borderWidth: styles.donutBorderWidth
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    animation: {
                        animateRotate: true,
                        duration: styles.duration
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: {
                                color: styles.legendColor,
                                font: { size: styles.fontSizeMedium, weight: styles.fontWeightBold, family: styles.fontFamily },
                                padding: styles.paddingLg,
                                usePointStyle: true,
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    return data.labels.map((label, i) => {
                                        const value = data.datasets[0].data[i];
                                        const formatted = value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                        const percentage = grandTotal > 0 ? ((value / grandTotal) * 100).toFixed(1) : 0;
                                        return {
                                            text: (() => {
                                                const currency = window.professionalAccounting ? window.professionalAccounting.getDefaultCurrencySync() : 'SAR';
                                                return `${label}: ${currency} ${formatted} (${percentage}%)`;
                                            })(),
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            strokeStyle: data.datasets[0].borderColor[i],
                                            lineWidth: data.datasets[0].borderWidth,
                                            hidden: false,
                                            index: i
                                        };
            });
            }
        }
                        },
                        tooltip: {
                            backgroundColor: styles.tooltipBg,
                            titleColor: styles.tooltipTitle,
                            bodyColor: styles.tooltipBody,
                            borderColor: styles.tooltipBorder,
                            borderWidth: 1,
                            padding: styles.paddingMd,
                            cornerRadius: styles.borderRadius,
                            callbacks: {
                                label: function(context) {
                                    const value = context.parsed;
                                    const formatted = value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    const percentage = grandTotal > 0 ? ((value / grandTotal) * 100).toFixed(1) : 0;
                                    const currency = window.professionalAccounting ? window.professionalAccounting.getDefaultCurrencySync() : 'SAR';
                                    return `${context.label}: ${currency} ${formatted} (${percentage}%)`;
                                }
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'centerText',
                    beforeDraw: function(chart) {
                        const ctx = chart.ctx;
                        const centerX = chart.chartArea.left + (chart.chartArea.right - chart.chartArea.left) / 2;
                        const centerY = chart.chartArea.top + (chart.chartArea.bottom - chart.chartArea.top) / 2;
                        
                        ctx.save();
                        ctx.font = `bold ${styles.fontSizeLarge + 2}px ${styles.fontFamily}`;
                        ctx.fillStyle = styles.legendColor;
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        ctx.fillText('Total', centerX, centerY - 15);
                        
                        ctx.font = `bold ${styles.fontSizeLarge}px ${styles.fontFamily}`;
                        ctx.fillStyle = styles.receivableColor;
                        const currency = window.professionalAccounting ? window.professionalAccounting.getDefaultCurrencySync() : 'SAR';
                        ctx.fillText(currency + ' ' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }), centerX, centerY + 10);
                        ctx.restore();
                    }
                }]
            });
        },

        async loadFinancialOverview() {
            // Prevent multiple simultaneous calls
            if (this._loadingFinancialOverview) {
                return;
            }
            // Skip if modal is open (prevents flashing when opening modals)
            const anyModalOpen = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
            if (anyModalOpen) {
                return;
            }
            this._loadingFinancialOverview = true;
            
            try {
                const response = await fetch(`${this.apiBase}/unified-calculations.php?type=all`);
                
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
                if (data.success && data.dashboard) {
                    // Update dashboard cards with unified data
                    this.updateOverviewCards({
                        total_revenue: data.dashboard.total_revenue,
                        total_expenses: data.dashboard.total_expenses,
                        net_profit: data.dashboard.net_profit,
                        cash_balance: data.dashboard.cash_balance,
                        total_receivables: data.dashboard.total_receivables,
                        total_payables: data.dashboard.total_payables,
                        receivables_count: data.dashboard.receivables_count,
                        payables_count: data.dashboard.payables_count
                    });
                    this._loadingFinancialOverview = false;
                    return; // Success
                } else {
                    throw new Error(data.message || 'No dashboard data');
                }
            } catch (error) {
                this._loadingFinancialOverview = false;
                // Fallback to overview API
                try {
                    const overviewResponse = await fetch(`${this.apiBase}/overview.php`);
                    
                    if (!overviewResponse.ok) {
                        const errorData = await overviewResponse.json().catch(() => null);
                        throw new Error(`HTTP ${overviewResponse.status}: ${errorData?.message || errorData?.error || 'Unknown error'}`);
                    }
                    
                    let overviewData;
                    try {
                        overviewData = await overviewResponse.json();
                    } catch (jsonError) {
                        console.error('Error parsing overview response:', jsonError);
                        throw new Error('Invalid JSON response from overview API');
                    }
                    
                    if (overviewData.success && overviewData.data) {
                        this.updateOverviewCards(overviewData.data);
                    } else {
                        this.updateOverviewCards({
                            total_revenue: 0,
                            total_expenses: 0,
                            net_profit: 0,
                            cash_balance: 0,
                            total_receivables: 0,
                            total_payables: 0,
                            receivables_count: 0,
                            payables_count: 0
                        });
                    }
                    this._loadingFinancialOverview = false;
                } catch (fallbackError) {
                    this.updateOverviewCards({
                        total_revenue: 0,
                        total_expenses: 0,
                        net_profit: 0,
                        cash_balance: 0,
                        total_receivables: 0,
                        total_payables: 0,
                        receivables_count: 0,
                        payables_count: 0
                    });
                    this._loadingFinancialOverview = false;
                }
            }
        },

        async refreshAllModules() {
            // Refresh all accounting modules with unified calculations
            try {
                const response = await fetch(`${this.apiBase}/unified-calculations.php?type=all`);
                let data;
                try {
                    data = await response.json();
                } catch (jsonError) {
                    console.error('Error parsing response in refreshAllModules:', jsonError);
                    return; // Silently fail if JSON parsing fails
                }
                if (data.success) {
                    // Refresh Dashboard
                    if (data.dashboard) {
                        this.updateOverviewCards({
                            total_revenue: data.dashboard.total_revenue,
                            total_expenses: data.dashboard.total_expenses,
                            net_profit: data.dashboard.net_profit,
                            cash_balance: data.dashboard.cash_balance,
                            total_receivables: data.dashboard.total_receivables,
                            total_payables: data.dashboard.total_payables,
                            receivables_count: data.dashboard.receivables_count,
                            payables_count: data.dashboard.payables_count
                        });
                    }
                    // Refresh Receivables summary if on Receivables tab
                    if (data.receivables && document.getElementById('arTotalOutstanding')) {
                        const totalEl = document.getElementById('arTotalOutstanding');
                        if (totalEl) totalEl.textContent = this.formatCurrency(data.receivables.total_outstanding);
                        
                        const overdueEl = document.getElementById('arOverdue');
                        if (overdueEl) overdueEl.textContent = this.formatCurrency(data.receivables.overdue);
                        
                        const monthEl = document.getElementById('arThisMonth');
                        if (monthEl) monthEl.textContent = this.formatCurrency(data.receivables.this_month);
                    }
                    // Refresh Payables summary if on Payables tab
                    if (data.payables && document.getElementById('apTotalOutstanding')) {
                        const totalEl = document.getElementById('apTotalOutstanding');
                        if (totalEl) totalEl.textContent = this.formatCurrency(data.payables.total_outstanding);
                        
                        const overdueEl = document.getElementById('apOverdue');
                        if (overdueEl) overdueEl.textContent = this.formatCurrency(data.payables.overdue);
                        
                        const monthEl = document.getElementById('apThisMonth');
                        if (monthEl) monthEl.textContent = this.formatCurrency(data.payables.this_month);
                    }
                    // Refresh Banking summary if on Banking tab
                    if (data.banking && document.getElementById('bankTotalBalance')) {
                        const balanceEl = document.getElementById('bankTotalBalance');
                        if (balanceEl) balanceEl.textContent = this.formatCurrency(data.banking.total_balance);
                    }
                    // Refresh Entities summary if on Entities tab
                    if (data.entities) {
                        // Update entity summary cards if they exist
                        const entityRevenueEl = document.getElementById('entityTotalRevenue');
                        if (entityRevenueEl) entityRevenueEl.textContent = this.formatCurrency(data.entities.total_revenue);
                        
                        const entityExpenseEl = document.getElementById('entityTotalExpenses');
                        if (entityExpenseEl) entityExpenseEl.textContent = this.formatCurrency(data.entities.total_expenses);
                        
                        const entityProfitEl = document.getElementById('entityNetProfit');
                        if (entityProfitEl) entityProfitEl.textContent = this.formatCurrency(data.entities.net_profit);
                    }
                    // Refresh General Ledger summary if on Ledger tab
                    if (data.ledger && document.getElementById('ledgerTotalDebits')) {
                        const debitsEl = document.getElementById('ledgerTotalDebits');
                        if (debitsEl) debitsEl.textContent = this.formatCurrency(data.ledger.total_debits);
                        
                        const creditsEl = document.getElementById('ledgerTotalCredits');
                        if (creditsEl) creditsEl.textContent = this.formatCurrency(data.ledger.total_credits);
                        
                        const balanceEl = document.getElementById('ledgerBalance');
                        if (balanceEl) {
                            balanceEl.textContent = this.formatCurrency(data.ledger.balance);
                            balanceEl.className = `ledger-balance ${Math.abs(data.ledger.balance) < 0.01 ? 'balanced' : 'unbalanced'}`;
                        }
                    }
                    this.showToast('All modules refreshed successfully', 'success');
                }
            } catch (error) {
                this.showToast('Error refreshing modules. Please try again.', 'error');
            }
        },

        async loadDashboard() {
            // Prevent multiple simultaneous calls
            if (this._loadingDashboard) {
                return;
            }
            this._loadingDashboard = true;
            
            // Refresh currency from system settings when dashboard loads
            await this.initDefaultCurrency();
            
            // Force table container to be visible
            const containerEl = document.getElementById('recentTransactionsContainer');
            const tableEl = document.getElementById('recentTransactionsTable');
            if (containerEl) {
                containerEl.classList.add('table-container-visible');
            }
            if (tableEl) {
                tableEl.classList.add('table-visible');
            }
            
            try {
                // Ensure Quick Actions are visible
                this.ensureQuickActionsVisible();
                
            this.loadRecentTransactions();
            this.loadCashFlowSummary();
            this.loadFinancialSummary();
            this.loadRevenueExpenseNetChart();
            this.loadCashBalanceChart();
            this.loadReceivablePayableChart();
            this.loadExpenseBreakdownChart();
            this.loadInvoiceAgingChart();
            this.loadFinancialOverviewChart();
            } finally {
                // Reset flag after a short delay to allow async operations to complete
                setTimeout(() => {
                    this._loadingDashboard = false;
                }, 100);
            }
        },

        async refreshDashboardCards() {
            try {
                // Refresh overview cards
                const response = await fetch(`${this.apiBase}/unified-calculations.php?type=all`);
                const data = await response.json();
                if (data.success && data.dashboard) {
                    this.updateOverviewCards({
                        total_revenue: data.dashboard.total_revenue,
                        total_expenses: data.dashboard.total_expenses,
                        net_profit: data.dashboard.net_profit,
                        cash_balance: data.dashboard.cash_balance,
                        total_receivables: data.dashboard.total_receivables,
                        total_payables: data.dashboard.total_payables,
                        receivables_count: data.dashboard.receivables_count,
                        payables_count: data.dashboard.payables_count
                    });
                }
                
                // Refresh cash flow summary
                this.loadCashFlowSummary();
                
                // Refresh financial summary
                this.loadFinancialSummary();
            } catch (error) {
                console.error('Error refreshing dashboard cards:', error);
            }
        }
    };
    Object.assign(ProfessionalAccounting.prototype, methods);
})();
