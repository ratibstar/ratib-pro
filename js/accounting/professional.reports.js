/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.reports.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.reports.js`.
 */
/**
 * Professional Accounting - Reports
 * Load AFTER professional.js
 */
(function(){
    if (typeof ProfessionalAccounting === 'undefined') return;
    const methods = {
        showReportLoading(container, reportName) {
            // Don't show loading if report is already showing
            if (container.classList.contains('show') && container.innerHTML.trim().length > 100) {
                return;
            }
            
            // Hide reports grid
            const reportsGrid = document.getElementById('modalReportsGrid');
            if (reportsGrid) {
                reportsGrid.classList.add('reports-grid-hidden');
            }
            
            // Clear container and insert loading spinner
            container.innerHTML = `
                <div class="accounting-report-loading">
                    <i class="fas fa-spinner fa-spin accounting-report-loading-icon"></i>
                    <h3>Generating ${reportName} Report</h3>
                    <p>Please wait while we prepare your report...</p>
                </div>
            `;
            
            // Use CSS class to override CSS
            container.classList.add('report-container-visible');
            container.classList.remove('show'); // Remove show class when showing loading
        },

        saveReportAsFavorite(reportType, reportName, filters = {}) {
            const favorites = this.getReportFavorites();
            const favoriteId = `${reportType}_${Date.now()}`;
            
            favorites.push({
                id: favoriteId,
                type: reportType,
                name: reportName,
                filters: filters,
                created: new Date().toISOString()
            });
            
            localStorage.setItem('reportFavorites', JSON.stringify(favorites));
            this.showToast('Report saved as favorite', 'success');
            return favoriteId;
        },

        getReportFavorites() {
            try {
                const stored = localStorage.getItem('reportFavorites');
                return stored ? JSON.parse(stored) : [];
            } catch (e) {
                return [];
            }
        },

        removeReportFavorite(favoriteId) {
            const favorites = this.getReportFavorites();
            const filtered = favorites.filter(f => f.id !== favoriteId);
            localStorage.setItem('reportFavorites', JSON.stringify(filtered));
            this.showToast('Favorite removed', 'info');
        },

        loadReportFavorite(favorite) {
            // Generate report with saved filters
            const params = new URLSearchParams({
                type: favorite.type,
                ...(favorite.filters.start_date && { start_date: favorite.filters.start_date }),
                ...(favorite.filters.end_date && { end_date: favorite.filters.end_date }),
                ...(favorite.filters.as_of && { as_of: favorite.filters.as_of }),
                ...(favorite.filters.account_id && { account_id: favorite.filters.account_id })
            });
            
            this.generateReport(favorite.type, params);
        },

        displayReportInPopupSmooth(reportType, reportName, reportData, existingModal = null) {
            // Store report data for pagination
            const isNewReport = this.currentReportType !== reportType;
            this.currentReportType = reportType;
            this.currentReportData = reportData;
            
            // Only reset pagination if it's a new report, otherwise preserve current settings
            if (isNewReport) {
                this.reportCurrentPage = 1;
                this.reportPerPage = 5;
                this.reportSearchTerm = '';
                this.reportTotalCount = 0; // Reset total count for new report
            }
            
            // Format report data into HTML table based on report type
            const reportDate = reportData?.period || reportData?.as_of || new Date().toISOString().split('T')[0];
            const formattedDate = this.formatDate(reportDate);
            const todayFormatted = this.formatDate(new Date().toISOString().split('T')[0]);
            const reportPeriod = reportData?.period ? `Period: ${formattedDate}` : reportData?.as_of ? `As of: ${formattedDate}` : `Generated: ${todayFormatted}`;
            
            let reportHTML = `
                <div class="accounting-module-modal-content">
                    <div class="module-content">
                <div class="accounting-report-header professional-report-header">
                    <div class="accounting-report-header-top">
                        <div class="report-header-left">
                            <h3 class="accounting-report-header-title">
                                <i class="fas fa-file-alt"></i> ${reportName}
                            </h3>
                            <p class="accounting-report-header-meta">
                                <i class="fas fa-calendar"></i> ${reportPeriod}
                            </p>
                        </div>
                    </div>
                    <div class="accounting-report-header-buttons">
                        <button class="btn btn-primary" data-action="print-report" title="Print Report">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button class="btn btn-secondary" data-action="export-report" title="Export Report">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
                ${this.getReportStatusCards(reportType, reportData)}
                <div class="filters-and-pagination-container report-controls-container">
                    <div class="filters-bar filters-bar-compact">
                        ${this.getReportDateFiltersHTML(reportType)}
                        ${this.getReportAccountFilterHTML(reportType)}
                        <div class="filter-group filter-group-compact">
                            <label><i class="fas fa-search"></i> Search:</label>
                            <input type="text" id="reportSearchInput" class="filter-input filter-input-compact" 
                                   placeholder="Search accounts, descriptions..." 
                                   value="${this.reportSearchTerm || ''}">
                        </div>
                        <div class="filter-group filter-group-compact">
                            <label>Show entries:</label>
                            <select id="reportPerPage" class="filter-select filter-select-compact">
                                <option value="5" ${this.reportPerPage === 5 ? 'selected' : ''}>5</option>
                                <option value="10" ${this.reportPerPage === 10 ? 'selected' : ''}>10</option>
                                <option value="25" ${this.reportPerPage === 25 ? 'selected' : ''}>25</option>
                                <option value="50" ${this.reportPerPage === 50 ? 'selected' : ''}>50</option>
                                <option value="100" ${this.reportPerPage === 100 ? 'selected' : ''}>100</option>
                                <option value="999999" ${this.reportPerPage >= 999999 ? 'selected' : ''}>All</option>
                            </select>
                        </div>
                        <div class="filter-group filter-group-compact">
                            <button class="btn btn-primary btn-sm" id="applyReportFilters" data-action="apply-report-filters">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                        <div class="filter-group filter-group-compact">
                            <button class="btn btn-secondary btn-sm" id="clearReportFilters" data-action="clear-report-filters">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </div>
                    <div class="pagination-container">
                        <div id="reportPaginationInfo" class="pagination-info"></div>
                        <div id="reportPaginationControls" class="pagination-controls"></div>
                    </div>
                </div>
                <div class="accounting-report-content professional-report-content">
            `;
            
            // If modal exists, update it smoothly
            if (existingModal) {
                const modalBody = existingModal.querySelector('.accounting-modal-body');
                const modalHeader = existingModal.querySelector('.accounting-modal-header h3');
                
                if (modalBody && modalHeader) {
                    // Update header
                    modalHeader.innerHTML = `<i class="fas fa-file-alt"></i> ${reportName}`;
                    
                    // Fade out, update, fade in
                    modalBody.classList.add('opacity-disabled');
                    
                        requestAnimationFrame(() => {
                        // Complete the HTML
                        switch (reportType) {
                            case 'trial-balance':
                                reportHTML += this.formatTrialBalance(reportData || {});
                                break;
                            case 'income-statement':
                            case 'profit-loss':
                                reportHTML += this.formatIncomeStatement(reportData || {});
                                break;
                            case 'balance-sheet':
                                reportHTML += this.formatBalanceSheet(reportData || {});
                                break;
                            case 'cash-flow':
                                reportHTML += this.formatCashFlow(reportData || {});
                                break;
                            case 'aged-receivables':
                            case 'ages-debt-receivable':
                                reportHTML += this.formatAgedReceivables(reportData || {});
                                break;
                            case 'ages-credit-receivable':
                                reportHTML += this.formatAgedReceivables(reportData || {});
                                break;
                            case 'aged-payables':
                                reportHTML += this.formatAgedPayables(reportData || {});
                                break;
                            case 'cash-book':
                                reportHTML += this.formatCashBook(reportData || {});
                                break;
                            case 'bank-book':
                                reportHTML += this.formatBankBook(reportData || {});
                                break;
                            case 'general-ledger':
                            case 'general-ledger-report':
                                reportHTML += this.formatGeneralLedgerReport(reportData || {});
                                break;
                            case 'account-statement':
                                reportHTML += this.formatAccountStatement(reportData || {});
                                break;
                            case 'expense-statement':
                                reportHTML += this.formatExpenseStatement(reportData || {});
                                break;
                            case 'chart-of-accounts-report':
                                reportHTML += this.formatChartOfAccounts(reportData || {});
                                break;
                            case 'value-added':
                                reportHTML += this.formatValueAdded(reportData || {});
                                break;
                            case 'fixed-assets':
                                reportHTML += this.formatFixedAssets(reportData || {});
                                break;
                            case 'entries-by-year':
                                reportHTML += this.formatEntriesByYear(reportData || {});
                                break;
                            case 'customer-debits':
                                reportHTML += this.formatCustomerDebits(reportData || {});
                                break;
                            case 'statistical-position':
                                reportHTML += this.formatStatisticalPosition(reportData || {});
                                break;
                            case 'changes-equity':
                                reportHTML += this.formatChangesInEquity(reportData || {});
                                break;
                            case 'financial-performance':
                                reportHTML += this.formatFinancialPerformance(reportData || {});
                                break;
                            case 'comparative-report':
                                reportHTML += this.formatComparativeReport(reportData || {});
                                break;
                            default:
                                reportHTML += this.formatGenericReport(reportData || {}, reportName);
                        }
                        
                        reportHTML += `
                            </div>
                        </div>
                    </div>
                `;
                        
                        modalBody.innerHTML = reportHTML;
                        
                        // Setup handlers
                        setTimeout(() => {
                            this.setupReportHandlers();
                            this.setupReportPagination();
                        }, 50);
                        
                        // Fade back in
                        requestAnimationFrame(() => {
                            modalBody.classList.remove('opacity-disabled', 'opacity-loading');
                            modalBody.classList.add('opacity-full');
                        });
                    });
                    
                    return;
                }
            }
            
            // Fallback to original method if no existing modal
            this.displayReportInPopup(reportType, reportName, reportData);
        },

        displayReportInPopup(reportType, reportName, reportData) {
            // Store report data for pagination
            const isNewReport = this.currentReportType !== reportType;
            this.currentReportType = reportType;
            this.currentReportData = reportData;
            
            // Only reset pagination if it's a new report, otherwise preserve current settings
            if (isNewReport) {
                this.reportCurrentPage = 1;
                this.reportPerPage = 5;
                this.reportSearchTerm = '';
                this.reportTotalCount = 0; // Reset total count for new report
            }
            // Format report data into HTML table based on report type
            const reportDate = reportData?.period || reportData?.as_of || new Date().toISOString().split('T')[0];
            const formattedDate = this.formatDate(reportDate);
            const todayFormatted = this.formatDate(new Date().toISOString().split('T')[0]);
            const reportPeriod = reportData?.period ? `Period: ${formattedDate}` : reportData?.as_of ? `As of: ${formattedDate}` : `Generated: ${todayFormatted}`;
            
            let reportHTML = `
                <div class="accounting-module-modal-content">
                    <div class="module-content">
                <div class="accounting-report-header professional-report-header">
                    <div class="accounting-report-header-top">
                        <div class="report-header-left">
                            <h3 class="accounting-report-header-title">
                                <i class="fas fa-file-alt"></i> ${reportName}
                            </h3>
                            <p class="accounting-report-header-meta">
                                <i class="fas fa-calendar"></i> ${reportPeriod}
                            </p>
                        </div>
                    </div>
                    <div class="accounting-report-header-buttons">
                        <button class="btn btn-primary" data-action="print-report" title="Print Report">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button class="btn btn-secondary" data-action="export-report" title="Export Report">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
                ${this.getReportStatusCards(reportType, reportData)}
                <div class="filters-and-pagination-container report-controls-container">
                    <div class="filters-bar filters-bar-compact">
                        ${this.getReportDateFiltersHTML(reportType)}
                        ${this.getReportAccountFilterHTML(reportType)}
                        <div class="filter-group filter-group-compact">
                            <label><i class="fas fa-search"></i> Search:</label>
                            <input type="text" id="reportSearchInput" class="filter-input filter-input-compact" 
                                   placeholder="Search..." 
                                   value="${this.reportSearchTerm || ''}">
                        </div>
                        <div class="filter-group filter-group-compact">
                            <label>Show entries:</label>
                            <select id="reportPerPage" class="filter-select filter-select-compact">
                                <option value="5" ${this.reportPerPage === 5 ? 'selected' : ''}>5</option>
                                <option value="10" ${this.reportPerPage === 10 ? 'selected' : ''}>10</option>
                                <option value="25" ${this.reportPerPage === 25 ? 'selected' : ''}>25</option>
                                <option value="50" ${this.reportPerPage === 50 ? 'selected' : ''}>50</option>
                                <option value="100" ${this.reportPerPage === 100 ? 'selected' : ''}>100</option>
                                <option value="999999" ${this.reportPerPage >= 999999 ? 'selected' : ''}>All</option>
                            </select>
                        </div>
                        <div class="filter-group filter-group-compact">
                            <button class="btn btn-primary btn-sm" id="applyReportFilters" data-action="apply-report-filters">
                                <i class="fas fa-filter"></i> Apply Filters
                            </button>
                        </div>
                        <div class="filter-group filter-group-compact">
                            <button class="btn btn-secondary btn-sm" id="clearReportFilters" data-action="clear-report-filters">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                    </div>
                    <div class="pagination-container">
                        <div id="reportPaginationInfo" class="pagination-info"></div>
                        <div id="reportPaginationControls" class="pagination-controls"></div>
                    </div>
                </div>
                <div class="accounting-report-content professional-report-content">
            `;
            // Format based on report type
            switch (reportType) {
                case 'trial-balance':
                    reportHTML += this.formatTrialBalance(reportData || {});
                    break;
                case 'income-statement':
                case 'profit-loss':
                    reportHTML += this.formatIncomeStatement(reportData || {});
                    break;
                case 'balance-sheet':
                    reportHTML += this.formatBalanceSheet(reportData || {});
                    break;
                case 'cash-flow':
                    reportHTML += this.formatCashFlow(reportData || {});
                    break;
                    case 'aged-receivables':
                    case 'ages-debt-receivable':
                        reportHTML += this.formatAgedReceivables(reportData || {});
                        break;
                    case 'ages-credit-receivable':
                        reportHTML += this.formatAgedReceivables(reportData || {});
                        break;
                case 'aged-payables':
                    reportHTML += this.formatAgedPayables(reportData || {});
                    break;
                case 'cash-book':
                    reportHTML += this.formatCashBook(reportData || {});
                    break;
                case 'bank-book':
                    reportHTML += this.formatBankBook(reportData || {});
                    break;
                case 'general-ledger':
                case 'general-ledger-report':
                    reportHTML += this.formatGeneralLedgerReport(reportData || {});
                    break;
                case 'account-statement':
                    reportHTML += this.formatAccountStatement(reportData || {});
                    break;
                case 'expense-statement':
                    reportHTML += this.formatExpenseStatement(reportData || {});
                    break;
                case 'chart-of-accounts-report':
                    reportHTML += this.formatChartOfAccounts(reportData || {});
                    break;
                case 'value-added':
                    reportHTML += this.formatValueAdded(reportData || {});
                    break;
                case 'fixed-assets':
                    reportHTML += this.formatFixedAssets(reportData || {});
                    break;
                case 'entries-by-year':
                    reportHTML += this.formatEntriesByYear(reportData || {});
                    break;
                case 'customer-debits':
                    reportHTML += this.formatCustomerDebits(reportData || {});
                    break;
                case 'statistical-position':
                    reportHTML += this.formatStatisticalPosition(reportData || {});
                    break;
                case 'changes-equity':
                    reportHTML += this.formatChangesInEquity(reportData || {});
                    break;
                case 'financial-performance':
                    reportHTML += this.formatFinancialPerformance(reportData || {});
                    break;
                case 'comparative-report':
                    reportHTML += this.formatComparativeReport(reportData || {});
                    break;
                default:
                    reportHTML += this.formatGenericReport(reportData || {}, reportName);
            }
            reportHTML += `
                        </div>
                    </div>
                </div>
            `;
            
            // Close the loading modal and open the report popup
            this.closeModal();
            
            // Open report in new popup modal
            setTimeout(() => {
                this.showModal(reportName, reportHTML, 'large');
                
                // Setup handlers
                setTimeout(() => {
                    this.setupReportHandlers();
                    this.setupReportPagination();
                }, 100);
            }, 100);
        },

        setupReportHandlers() {
            const printBtn = document.querySelector('[data-action="print-report"]');
            const exportBtn = document.querySelector('[data-action="export-report"]');
            const refreshBtn = document.getElementById('refreshReportBtn');
            const applyFiltersBtn = document.getElementById('applyReportFilters');
            
            // Setup refresh button
            if (refreshBtn) {
                const newRefreshBtn = refreshBtn.cloneNode(true);
                refreshBtn.parentNode.replaceChild(newRefreshBtn, refreshBtn);
                newRefreshBtn.addEventListener('click', () => {
                    this.refreshCurrentReport();
                });
            }
            
            // Setup date validation
            this.setupDateValidation();
            
            // Setup column sorting
            this.setupColumnSorting();
            
            if (printBtn) {
                // Remove existing listeners by cloning
                const newPrintBtn = printBtn.cloneNode(true);
                printBtn.parentNode.replaceChild(newPrintBtn, printBtn);
                newPrintBtn.addEventListener('click', () => {
                    window.print();
                });
            }
            
            if (exportBtn) {
                // Remove existing listeners by cloning
                const newExportBtn = exportBtn.cloneNode(true);
                exportBtn.parentNode.replaceChild(newExportBtn, exportBtn);
                
                // Create dropdown menu for export formats
                newExportBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.showExportMenu(newExportBtn);
                });
            }
            
            if (applyFiltersBtn) {
                // Remove existing listeners by cloning
                const newApplyBtn = applyFiltersBtn.cloneNode(true);
                applyFiltersBtn.parentNode.replaceChild(newApplyBtn, applyFiltersBtn);
                newApplyBtn.addEventListener('click', () => {
                    this.applyReportFilters();
                });
            }
            
            // Setup clear filters button
            const clearFiltersBtn = document.getElementById('clearReportFilters');
            if (clearFiltersBtn) {
                const newClearBtn = clearFiltersBtn.cloneNode(true);
                clearFiltersBtn.parentNode.replaceChild(newClearBtn, clearFiltersBtn);
                newClearBtn.addEventListener('click', () => {
                    this.clearReportFilters();
                });
            }
            
            // Setup search input (search on Enter key or after delay)
            const searchInput = document.getElementById('reportSearchInput');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.reportSearchTerm = e.target.value || '';
                        this.reportCurrentPage = 1;
                        this.refreshReportDisplay();
                    }, 500); // Debounce search
                });
                
                searchInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        clearTimeout(searchTimeout);
                        this.reportSearchTerm = e.target.value || '';
                        this.reportCurrentPage = 1;
                        this.refreshReportDisplay();
                    }
                });
            }
            
            // Load accounts if account select exists
            const accountSelect = document.getElementById('reportAccountSelect');
            if (accountSelect) {
                this.loadAccountsForReport();
            }
            
            // Setup date validation
            this.setupDateValidation();
            
            // Setup column sorting
            this.setupColumnSorting();
            
            // Setup keyboard shortcuts
            this.setupKeyboardShortcuts();
            
            // Setup report comparison
            this.setupReportComparison();
        },

        setupKeyboardShortcuts() {
            // Only setup shortcuts when report modal is open
            const reportModal = document.querySelector('.accounting-modal:has(.accounting-report-content)');
            if (!reportModal) return;
            
            document.addEventListener('keydown', (e) => {
                // Only handle shortcuts when report is focused
                if (!reportModal.classList.contains('accounting-modal-visible')) return;
                
                // Ctrl/Cmd + P: Print
                if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                    e.preventDefault();
                    const printBtn = document.querySelector('[data-action="print-report"]');
                    if (printBtn) printBtn.click();
                }
                
                // Ctrl/Cmd + E: Export
                if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                    e.preventDefault();
                    const exportBtn = document.querySelector('[data-action="export-report"]');
                    if (exportBtn) exportBtn.click();
                }
                
                // Ctrl/Cmd + F: Focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'f' && !e.shiftKey) {
                    e.preventDefault();
                    const searchInput = document.getElementById('reportSearchInput');
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                }
                
                // Escape: Close modal
                if (e.key === 'Escape') {
                    const closeBtn = document.querySelector('.accounting-modal-close, [data-action="close-modal"]');
                    if (closeBtn) closeBtn.click();
                }
                
                // Arrow keys for pagination (when not in input field)
                if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) {
                    if (e.key === 'ArrowLeft' && e.ctrlKey) {
                        e.preventDefault();
                        const prevBtn = document.querySelector('.btn-pagination:not(.btn-pagination-next):not(.btn-pagination-prev)');
                        if (prevBtn && !prevBtn.classList.contains('active')) {
                            const activePage = parseInt(document.querySelector('.btn-pagination.active')?.textContent || '1');
                            if (activePage > 1) {
                                this.reportCurrentPage = activePage - 1;
                                this.refreshReportDisplay();
                            }
                        }
                    }
                    if (e.key === 'ArrowRight' && e.ctrlKey) {
                        e.preventDefault();
                        const nextBtn = document.querySelector('.btn-pagination-next');
                        if (nextBtn && !nextBtn.disabled) {
                            nextBtn.click();
                        }
                    }
                }
            });
        },

        setupReportComparison() {
            // Add compare button and favorites button to report header
            const reportHeader = document.querySelector('.accounting-report-header');
            if (reportHeader) {
                const headerButtons = reportHeader.querySelector('.accounting-report-header-buttons');
                if (headerButtons) {
                    // Refresh button handler
                    const refreshBtn = document.getElementById('refreshReportBtn');
                    if (refreshBtn && !refreshBtn.hasAttribute('data-listener-added')) {
                        refreshBtn.setAttribute('data-listener-added', 'true');
                        refreshBtn.addEventListener('click', () => {
                            this.refreshCurrentReport();
                        });
                    }
                    
                    const headerRight = headerButtons;
                    if (headerRight) {
                        // Compare button
                        if (!document.getElementById('compareReportBtn')) {
                            const compareBtn = document.createElement('button');
                            compareBtn.id = 'compareReportBtn';
                            compareBtn.className = 'btn btn-info btn-sm';
                            compareBtn.innerHTML = '<i class="fas fa-balance-scale"></i> Compare';
                            compareBtn.title = 'Compare with another period (Ctrl+Shift+C)';
                            compareBtn.addEventListener('click', () => {
                                this.openReportComparison();
                            });
                            headerRight.insertBefore(compareBtn, headerRight.firstChild);
                        }
                        
                        // Favorites button
                        if (!document.getElementById('saveFavoriteBtn')) {
                            const favoriteBtn = document.createElement('button');
                            favoriteBtn.id = 'saveFavoriteBtn';
                            favoriteBtn.className = 'btn btn-secondary btn-sm';
                            favoriteBtn.innerHTML = '<i class="far fa-star"></i> Save Favorite';
                            favoriteBtn.title = 'Save this report as favorite (Ctrl+S)';
                            favoriteBtn.addEventListener('click', () => {
                                this.saveCurrentReportAsFavorite();
                            });
                            headerRight.insertBefore(favoriteBtn, headerRight.firstChild);
                        }
                    }
                }
            }
            
            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'C') {
                    e.preventDefault();
                    const compareBtn = document.getElementById('compareReportBtn');
                    if (compareBtn) compareBtn.click();
                }
                if ((e.ctrlKey || e.metaKey) && e.key === 's' && !e.shiftKey) {
                    e.preventDefault();
                    const favoriteBtn = document.getElementById('saveFavoriteBtn');
                    if (favoriteBtn) favoriteBtn.click();
                }
                // F5: Refresh report
                if (e.key === 'F5') {
                    e.preventDefault();
                    const refreshBtn = document.getElementById('refreshReportBtn');
                    if (refreshBtn) refreshBtn.click();
                }
            });
        },

        refreshCurrentReport() {
            if (!this.currentReportType) {
                this.showToast('No report to refresh', 'warning');
                return;
            }
            
            // Show loading state
            const reportContent = document.querySelector('.accounting-report-content');
            if (reportContent) {
                reportContent.classList.add('opacity-loading');
            }
            
            // Regenerate report with current filters
            this.generateReport(this.currentReportType);
        },

        saveCurrentReportAsFavorite() {
            if (!this.currentReportType || !this.currentReportData) {
                this.showToast('No report to save', 'warning');
                return;
            }
            
            const startDateInput = document.getElementById('reportStartDate');
            const endDateInput = document.getElementById('reportEndDate');
            const asOfDateInput = document.getElementById('reportAsOfDate');
            const accountSelect = document.getElementById('reportAccountSelect');
            
            const filters = {
                ...(startDateInput?.value && { start_date: startDateInput.value }),
                ...(endDateInput?.value && { end_date: endDateInput.value }),
                ...(asOfDateInput?.value && { as_of: asOfDateInput.value }),
                ...(accountSelect?.value && { account_id: accountSelect.value })
            };
            
            const reportName = this.getReportName(this.currentReportType);
            this.saveReportAsFavorite(this.currentReportType, reportName, filters);
            
            // Update button icon
            const favoriteBtn = document.getElementById('saveFavoriteBtn');
            if (favoriteBtn) {
                favoriteBtn.innerHTML = '<i class="fas fa-star"></i> Saved';
                favoriteBtn.classList.add('favorited');
                setTimeout(() => {
                    favoriteBtn.innerHTML = '<i class="far fa-star"></i> Save Favorite';
                    favoriteBtn.classList.remove('favorited');
                }, 2000);
            }
        },

        openReportComparison() {
            // Show modal to select comparison period
            const currentType = this.currentReportType;
            const currentData = this.currentReportData;
            
            if (!currentType || !currentData) {
                this.showToast('No report to compare', 'warning');
                return;
            }
            
            // Create comparison modal
            const comparisonHTML = `
                <div class="accounting-modal accounting-modal-visible" id="reportComparisonModal">
                    <div class="accounting-modal-overlay"></div>
                    <div class="accounting-modal-content accounting-modal-medium">
                        <div class="accounting-modal-header">
                            <h3><i class="fas fa-balance-scale"></i> Compare Report</h3>
                            <button class="accounting-modal-close" data-action="close-modal">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="accounting-modal-body">
                            <p>Select a different date range to compare with the current report:</p>
                            <div class="accounting-modal-form-group">
                                <label>Comparison Start Date:</label>
                                <input type="text" id="compareStartDate" class="accounting-modal-input date-input" placeholder="MM/DD/YYYY">
                            </div>
                            <div class="accounting-modal-form-group">
                                <label>Comparison End Date:</label>
                                <input type="text" id="compareEndDate" class="accounting-modal-input date-input" placeholder="MM/DD/YYYY">
                            </div>
                            <div class="accounting-modal-form-group">
                                <label>Or As Of Date:</label>
                                <input type="text" id="compareAsOfDate" class="accounting-modal-input date-input" placeholder="MM/DD/YYYY">
                            </div>
                            <div class="accounting-modal-footer">
                                <button class="btn btn-primary" id="generateComparisonBtn">
                                    <i class="fas fa-balance-scale"></i> Generate Comparison
                                </button>
                                <button class="btn btn-secondary" data-action="close-modal">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', comparisonHTML);
            
            // Setup comparison button
            document.getElementById('generateComparisonBtn').addEventListener('click', async () => {
                const startDate = document.getElementById('compareStartDate').value;
                const endDate = document.getElementById('compareEndDate').value;
                const asOfDate = document.getElementById('compareAsOfDate').value;
                
                if (!startDate && !endDate && !asOfDate) {
                    this.showToast('Please select at least one date', 'warning');
                    return;
                }
                
                // Generate comparison report
                try {
                    const params = new URLSearchParams({
                        type: currentType,
                        ...(startDate && { start_date: startDate }),
                        ...(endDate && { end_date: endDate }),
                        ...(asOfDate && { as_of: asOfDate })
                    });
                    
                    const response = await fetch(`${this.apiBase}/reports.php?${params}`);
                    const data = await response.json();
                    
                    if (data.success && data.report) {
                        this.displayReportComparison(currentData, data.report, currentType);
                        this.closeModal();
                    } else {
                        this.showToast('Failed to generate comparison report', 'error');
                    }
                } catch (error) {
                    this.showToast('Error generating comparison: ' + error.message, 'error');
                }
            });
        },

        displayReportComparison(currentReport, comparisonReport, reportType) {
            // Create side-by-side comparison view
            const reportName = this.getReportName(reportType);
            const comparisonHTML = `
                <div class="accounting-module-modal-content">
                    <div class="module-content">
                        <div class="accounting-report-header professional-report-header">
                            <div class="accounting-report-header-top">
                                <div class="report-header-left">
                                    <h3 class="accounting-report-header-title">
                                        <i class="fas fa-balance-scale"></i> ${reportName} - Comparison
                                    </h3>
                                </div>
                                <div class="report-header-right">
                                    <button class="btn btn-primary" data-action="print-report">
                                        <i class="fas fa-print"></i> Print
                                    </button>
                                    <button class="btn btn-secondary" data-action="export-report">
                                        <i class="fas fa-download"></i> Export
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="accounting-report-content professional-report-content">
                            <div class="report-comparison-container">
                                <div class="report-comparison-column">
                                    <h4>Current Period</h4>
                                    ${this.getReportHTMLForComparison(currentReport, reportType)}
                                </div>
                                <div class="report-comparison-column">
                                    <h4>Comparison Period</h4>
                                    ${this.getReportHTMLForComparison(comparisonReport, reportType)}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            this.showModal(`${reportName} - Comparison`, comparisonHTML, 'large');
            setTimeout(() => {
                this.setupReportHandlers();
            }, 100);
        },

        getReportHTMLForComparison(reportData, reportType) {
            switch (reportType) {
                case 'trial-balance':
                    return this.formatTrialBalance(reportData);
                case 'income-statement':
                case 'profit-loss':
                    return this.formatIncomeStatement(reportData);
                case 'balance-sheet':
                    return this.formatBalanceSheet(reportData);
                case 'cash-flow':
                    return this.formatCashFlow(reportData);
                default:
                    return this.formatGenericReport(reportData, this.getReportName(reportType));
            }
        },

        setupDateValidation() {
            const startDateInput = document.getElementById('reportStartDate');
            const endDateInput = document.getElementById('reportEndDate');
            const asOfDateInput = document.getElementById('reportAsOfDate');
            
            const validateDate = (input, fieldName) => {
                if (!input) return;
                
                input.addEventListener('blur', () => {
                    const value = input.value.trim();
                    if (value && !this.isValidDate(value)) {
                        input.classList.add('invalid-date');
                        this.showDateError(input, `Invalid ${fieldName} format. Please use YYYY-MM-DD`);
                    } else {
                        input.classList.remove('invalid-date');
                        this.hideDateError(input);
                    }
                });
                
                input.addEventListener('input', () => {
                    input.classList.remove('invalid-date');
                    this.hideDateError(input);
                });
            };
            
            if (startDateInput) validateDate(startDateInput, 'start date');
            if (endDateInput) validateDate(endDateInput, 'end date');
            if (asOfDateInput) validateDate(asOfDateInput, 'as of date');
            
            // Validate date range
            if (startDateInput && endDateInput) {
                const validateRange = () => {
                    const start = startDateInput.value;
                    const end = endDateInput.value;
                    if (start && end && this.isValidDate(start) && this.isValidDate(end)) {
                        if (new Date(start) > new Date(end)) {
                            endDateInput.classList.add('invalid-date');
                            this.showDateError(endDateInput, 'End date must be after start date');
                        } else {
                            endDateInput.classList.remove('invalid-date');
                            this.hideDateError(endDateInput);
                        }
                    }
                };
                
                startDateInput.addEventListener('change', validateRange);
                endDateInput.addEventListener('change', validateRange);
            }
        },

        isValidDate(dateString) {
            const regex = /^\d{4}-\d{2}-\d{2}$/;
            if (!regex.test(dateString)) return false;
            const date = new Date(dateString);
            return date instanceof Date && !isNaN(date);
        },

        showDateError(input, message) {
            let errorMsg = input.parentElement.querySelector('.date-validation-message');
            if (!errorMsg) {
                errorMsg = document.createElement('div');
                errorMsg.className = 'date-validation-message';
                input.parentElement.appendChild(errorMsg);
            }
            errorMsg.textContent = message;
            errorMsg.classList.add('show');
        },

        hideDateError(input) {
            const errorMsg = input.parentElement.querySelector('.date-validation-message');
            if (errorMsg) {
                errorMsg.classList.remove('show');
            }
        },

        getQuickDatePresets() {
            const today = new Date();
            const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            const startOfYear = new Date(today.getFullYear(), 0, 1);
            const endOfYear = new Date(today.getFullYear(), 11, 31);
            const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
            const lastYearStart = new Date(today.getFullYear() - 1, 0, 1);
            const lastYearEnd = new Date(today.getFullYear() - 1, 11, 31);
            
            const formatDate = (date) => date.toISOString().split('T')[0];
            
            return [
                { label: 'Today', start: formatDate(today), end: formatDate(today) },
                { label: 'This Month', start: formatDate(startOfMonth), end: formatDate(endOfMonth) },
                { label: 'Last Month', start: formatDate(lastMonthStart), end: formatDate(lastMonthEnd) },
                { label: 'This Year', start: formatDate(startOfYear), end: formatDate(endOfYear) },
                { label: 'Last Year', start: formatDate(lastYearStart), end: formatDate(lastYearEnd) },
                { label: 'Last 7 Days', start: formatDate(new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000)), end: formatDate(today) },
                { label: 'Last 30 Days', start: formatDate(new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000)), end: formatDate(today) },
                { label: 'Last 90 Days', start: formatDate(new Date(today.getTime() - 90 * 24 * 60 * 60 * 1000)), end: formatDate(today) }
            ];
        },

        applyQuickDatePreset(preset) {
            const startDateInput = document.getElementById('reportStartDate');
            const endDateInput = document.getElementById('reportEndDate');
            
            if (startDateInput && preset.start) {
                startDateInput.value = preset.start;
            }
            if (endDateInput && preset.end) {
                endDateInput.value = preset.end;
            }
            
            // Trigger change events
            if (startDateInput) startDateInput.dispatchEvent(new Event('change'));
            if (endDateInput) endDateInput.dispatchEvent(new Event('change'));
            
            this.showToast(`Applied ${preset.label} preset`, 'success');
        },

        setupColumnSorting() {
            // Add sorting to table headers after a short delay to ensure table is rendered
            setTimeout(() => {
                const tables = document.querySelectorAll('.professional-report-table');
                tables.forEach(table => {
                    const headers = table.querySelectorAll('thead th');
                    headers.forEach((header, index) => {
                        // Skip sorting on action columns or columns that shouldn't be sorted
                        if (header.classList.contains('report-col-type') || 
                            header.textContent.trim() === '' ||
                            header.querySelector('i')) {
                            return;
                        }
                        
                        header.classList.add('sortable');
                        header.addEventListener('click', () => {
                            this.sortTableColumn(table, index, header);
                        });
                    });
                });
            }, 300);
        },

        sortTableColumn(table, columnIndex, header) {
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            
            const rows = Array.from(tbody.querySelectorAll('tr'));
            if (rows.length === 0) return;
            
            // Determine current sort direction
            const isAsc = header.classList.contains('sort-asc');
            const isDesc = header.classList.contains('sort-desc');
            
            // Remove sort classes from all headers
            table.querySelectorAll('th').forEach(th => {
                th.classList.remove('sort-asc', 'sort-desc');
            });
            
            // Set new sort direction
            const newDirection = isAsc ? 'desc' : 'asc';
            header.classList.add(`sort-${newDirection}`);
            
            // Sort rows
            rows.sort((a, b) => {
                const aCell = a.cells[columnIndex];
                const bCell = b.cells[columnIndex];
                
                if (!aCell || !bCell) return 0;
                
                let aValue = aCell.textContent.trim();
                let bValue = bCell.textContent.trim();
                
                // Try to parse as number
                const aNum = parseFloat(aValue.replace(/[^0-9.-]/g, ''));
                const bNum = parseFloat(bValue.replace(/[^0-9.-]/g, ''));
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return newDirection === 'asc' ? aNum - bNum : bNum - aNum;
                }
                
                // String comparison
                return newDirection === 'asc' 
                    ? aValue.localeCompare(bValue)
                    : bValue.localeCompare(aValue);
            });
            
            // Re-append sorted rows
            rows.forEach(row => tbody.appendChild(row));
        },

        setupReportPagination() {
            const perPageSelect = document.getElementById('reportPerPage');
            if (perPageSelect) {
                // Remove any existing listeners to prevent duplicates
                const newSelect = perPageSelect.cloneNode(true);
                perPageSelect.parentNode.replaceChild(newSelect, perPageSelect);
                
                // Set the value
                newSelect.value = this.reportPerPage.toString();
                
                // Add event listener
                newSelect.addEventListener('change', (e) => {
                    const newPerPage = parseInt(e.target.value) || 5;
                    if (newPerPage !== this.reportPerPage) {
                        this.reportPerPage = newPerPage;
                        this.reportCurrentPage = 1;
                        this.refreshReportDisplay();
                    }
                });
            }
            
            this.updateReportPagination();
        },

        refreshReportDisplay() {
            if (this.currentReportType && this.currentReportData) {
                // Update content smoothly without closing/reopening modal
                this.updateReportContent();
            }
        },

        updateReportContent() {
            // Find the report content container
            const reportContent = document.querySelector('.accounting-report-content.professional-report-content');
            if (!reportContent) {
                // Fallback to full refresh if container not found
                this.displayReportInPopup(this.currentReportType, this.getReportName(this.currentReportType), this.currentReportData);
                return;
            }
            
            // Add fade transition
            reportContent.classList.add('opacity-disabled');
            
            // Use requestAnimationFrame for smooth update
            requestAnimationFrame(() => {
                // Get formatted report HTML
                let reportHTML = '';
                switch (this.currentReportType) {
                    case 'trial-balance':
                        reportHTML = this.formatTrialBalance(this.currentReportData || {});
                        break;
                    case 'income-statement':
                    case 'profit-loss':
                        reportHTML = this.formatIncomeStatement(this.currentReportData || {});
                        break;
                    case 'balance-sheet':
                        reportHTML = this.formatBalanceSheet(this.currentReportData || {});
                        break;
                    case 'cash-flow':
                        reportHTML = this.formatCashFlow(this.currentReportData || {});
                        break;
                    case 'aged-receivables':
                    case 'ages-debt-receivable':
                        reportHTML = this.formatAgedReceivables(this.currentReportData || {});
                        break;
                    case 'ages-credit-receivable':
                        reportHTML = this.formatAgedReceivables(this.currentReportData || {});
                        break;
                    case 'aged-payables':
                        reportHTML = this.formatAgedPayables(this.currentReportData || {});
                        break;
                    case 'cash-book':
                        reportHTML = this.formatCashBook(this.currentReportData || {});
                        break;
                    case 'bank-book':
                        reportHTML = this.formatBankBook(this.currentReportData || {});
                        break;
                    case 'general-ledger':
                    case 'general-ledger-report':
                        reportHTML = this.formatGeneralLedgerReport(this.currentReportData || {});
                        break;
                    case 'account-statement':
                        reportHTML = this.formatAccountStatement(this.currentReportData || {});
                        break;
                    case 'expense-statement':
                        reportHTML = this.formatExpenseStatement(this.currentReportData || {});
                        break;
                    case 'chart-of-accounts-report':
                        reportHTML = this.formatChartOfAccounts(this.currentReportData || {});
                        break;
                    case 'value-added':
                        reportHTML = this.formatValueAdded(this.currentReportData || {});
                        break;
                    case 'fixed-assets':
                        reportHTML = this.formatFixedAssets(this.currentReportData || {});
                        break;
                    case 'entries-by-year':
                        reportHTML = this.formatEntriesByYear(this.currentReportData || {});
                        break;
                    case 'customer-debits':
                        reportHTML = this.formatCustomerDebits(this.currentReportData || {});
                        break;
                    case 'statistical-position':
                        reportHTML = this.formatStatisticalPosition(this.currentReportData || {});
                        break;
                    case 'changes-equity':
                        reportHTML = this.formatChangesInEquity(this.currentReportData || {});
                        break;
                    case 'financial-performance':
                        reportHTML = this.formatFinancialPerformance(this.currentReportData || {});
                        break;
                    case 'comparative-report':
                        reportHTML = this.formatComparativeReport(this.currentReportData || {});
                        break;
                    default:
                        reportHTML = this.formatGenericReport(this.currentReportData || {}, this.getReportName(this.currentReportType));
                }
                
                // Update content
            reportContent.innerHTML = reportHTML;
            
                // Update pagination and table wrappers
                this.updateReportPagination();
                
                // Re-setup column sorting after content update
                setTimeout(() => {
                    this.setupColumnSorting();
                }, 100);
                
                // Fade back in
                requestAnimationFrame(() => {
                    reportContent.classList.remove('opacity-disabled', 'opacity-loading');
                    reportContent.classList.add('opacity-full');
                });
            });
        },

        getReportName(reportType) {
            const names = {
                'trial-balance': 'Trial Balance',
                'income-statement': 'Income Statement',
                'balance-sheet': 'Balance Sheet',
                'cash-flow': 'Cash Flow Report',
                'aged-receivables': 'Ages of Debt Receivable',
                'ages-debt-receivable': 'Ages of Debt Receivable',
                'aged-payables': 'Aged Payables',
                'cash-book': 'Cash Book',
                'bank-book': 'Bank Book',
                'general-ledger': 'General Ledger',
                'general-ledger-report': 'General Ledger',
                'account-statement': 'Account Statement',
                'expense-statement': 'Expense Statement',
                'chart-of-accounts-report': 'Chart of Accounts',
                'value-added': 'Value Added',
                'fixed-assets': 'Fixed Assets Report',
                'entries-by-year': 'Entries by Year Report',
                'customer-debits': 'Customer Debits Report',
                'ages-credit-receivable': 'Ages of Credit Receivable',
                'statistical-position': 'Statistical Position Report',
                'changes-equity': 'Changes in Equity',
                'financial-performance': 'Financial Performance',
                'comparative-report': 'Comparative Report'
            };
            return names[reportType] || 'Report';
        },

        getReportDateFiltersHTML(reportType) {
            const needsDateRange = ['income-statement', 'profit-loss', 'cash-flow', 'cash-book', 'bank-book', 
                'general-ledger', 'general-ledger-report', 'account-statement', 'expense-statement', 
                'value-added', 'entries-by-year', 'changes-equity', 'financial-performance', 'comparative-report'].includes(reportType);
            const needsAsOfDate = ['trial-balance', 'balance-sheet', 'aged-receivables', 'ages-debt-receivable', 
                'ages-credit-receivable', 'aged-payables', 'chart-of-accounts-report', 'fixed-assets', 
                'customer-debits', 'statistical-position'].includes(reportType);
            
            let html = '';
            
            if (needsDateRange) {
                const defaultStartDate = new Date();
                defaultStartDate.setMonth(defaultStartDate.getMonth() - 1);
                const defaultEndDate = new Date();
                
                html += `
                    <div class="filter-group filter-group-compact">
                        <label>Start Date:</label>
                        <input type="text" id="reportStartDate" class="filter-input filter-input-compact date-input" placeholder="MM/DD/YYYY" 
                               value="${this.formatDateForInput(defaultStartDate.toISOString())}">
                    </div>
                    <div class="filter-group filter-group-compact">
                        <label>End Date:</label>
                        <input type="text" id="reportEndDate" class="filter-input filter-input-compact date-input" placeholder="MM/DD/YYYY" 
                               value="${this.formatDateForInput(defaultEndDate.toISOString())}">
                    </div>
                `;
            } else if (needsAsOfDate) {
                const defaultAsOfDate = new Date();
                
                html += `
                    <div class="filter-group filter-group-compact">
                        <label>As of Date:</label>
                        <input type="text" id="reportAsOfDate" class="filter-input filter-input-compact date-input" placeholder="MM/DD/YYYY" 
                               value="${this.formatDateForInput(defaultAsOfDate.toISOString())}">
                    </div>
                `;
            }
            
            return html;
        },

        getReportAccountFilterHTML(reportType) {
            const needsAccountId = ['general-ledger', 'general-ledger-report', 'account-statement'].includes(reportType);
            
            if (!needsAccountId) {
                return '';
            }
            
            return `
                <div class="filter-group filter-group-compact">
                    <label>Account:</label>
                    <select id="reportAccountSelect" class="filter-select filter-select-compact">
                        <option value="">All Accounts</option>
                    </select>
                </div>
            `;
        },

        async loadAccountsForReport() {
            const accountSelect = document.getElementById('reportAccountSelect');
            if (!accountSelect) return;
            try {
                const response = await fetch(`${this.apiBase}/accounts.php?action=list&is_active=1`, { credentials: 'include' });
                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.accounts) {
                        accountSelect.innerHTML = '<option value="">All Accounts</option>';
                        data.accounts.forEach(account => {
                            const option = document.createElement('option');
                            option.value = account.id;
                            option.textContent = `${account.account_code || ''} - ${account.account_name || ''}`.trim();
                            accountSelect.appendChild(option);
                        });
                    }
                }
            } catch (error) {
                console.error('Error loading accounts for report:', error);
            }
        },

        applyReportFilters() {
            if (this.currentReportType) {
                // Update search term from input
                const searchInput = document.getElementById('reportSearchInput');
                if (searchInput) {
                    this.reportSearchTerm = searchInput.value || '';
                }
                // Reset to page 1 when applying filters
                this.reportCurrentPage = 1;
                // Regenerate report with new filters
                this.generateReport(this.currentReportType);
            }
        },

        clearReportFilters() {
            // Clear search
            const searchInput = document.getElementById('reportSearchInput');
            if (searchInput) {
                searchInput.value = '';
                this.reportSearchTerm = '';
            }
            
            // Reset dates to defaults
            const startDateInput = document.getElementById('reportStartDate');
            const endDateInput = document.getElementById('reportEndDate');
            const asOfDateInput = document.getElementById('reportAsOfDate');
            
            if (startDateInput && endDateInput) {
                const defaultStartDate = new Date();
                defaultStartDate.setMonth(defaultStartDate.getMonth() - 1);
                const defaultEndDate = new Date();
                startDateInput.value = this.formatDateForInput(defaultStartDate.toISOString());
                endDateInput.value = this.formatDateForInput(defaultEndDate.toISOString());
            }
            
            if (asOfDateInput) {
                asOfDateInput.value = this.formatDateForInput(new Date().toISOString());
            }
            
            // Clear account filter
            const accountSelect = document.getElementById('reportAccountSelect');
            if (accountSelect) {
                accountSelect.value = '';
            }
            
            // Reset pagination
            this.reportCurrentPage = 1;
            
            // Regenerate report
            if (this.currentReportType) {
                this.generateReport(this.currentReportType);
            }
        },

        updateReportPagination() {
            const totalCount = this.getReportTotalCount();
            this.reportTotalPages = totalCount > 0 ? Math.max(1, Math.ceil(totalCount / this.reportPerPage)) : 1;
            
            const paginationInfo = document.getElementById('reportPaginationInfo');
            const paginationControls = document.getElementById('reportPaginationControls');
            
            if (paginationInfo) {
                const start = totalCount === 0 ? 0 : (this.reportCurrentPage - 1) * this.reportPerPage + 1;
                const end = totalCount === 0 ? 0 : Math.min(totalCount, this.reportCurrentPage * this.reportPerPage);
                paginationInfo.textContent = `Showing ${start} to ${end} of ${totalCount} entries`;
            }
            
            if (paginationControls) {
                paginationControls.innerHTML = '';
                
                // Previous button
                const prevBtn = document.createElement('button');
                prevBtn.className = 'btn-pagination btn-pagination-prev';
                prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i> <span>Previous</span>';
                prevBtn.disabled = this.reportCurrentPage === 1;
                prevBtn.title = 'Previous Page';
                prevBtn.addEventListener('click', () => {
                    if (this.reportCurrentPage > 1) {
                        this.reportCurrentPage--;
                        this.refreshReportDisplay();
                    }
                });
                paginationControls.appendChild(prevBtn);
                
                // Page numbers (show max 10 pages, with ellipsis if needed)
                const maxVisiblePages = 10;
                let startPage = Math.max(1, this.reportCurrentPage - Math.floor(maxVisiblePages / 2));
                let endPage = Math.min(this.reportTotalPages, startPage + maxVisiblePages - 1);
                
                if (endPage - startPage < maxVisiblePages - 1) {
                    startPage = Math.max(1, endPage - maxVisiblePages + 1);
                }
                
                if (startPage > 1) {
                    const firstBtn = document.createElement('button');
                    firstBtn.className = 'btn-pagination';
                    firstBtn.textContent = '1';
                    firstBtn.addEventListener('click', () => {
                        this.reportCurrentPage = 1;
                        this.refreshReportDisplay();
                    });
                    paginationControls.appendChild(firstBtn);
                    
                    if (startPage > 2) {
                        const ellipsis = document.createElement('span');
                        ellipsis.className = 'pagination-ellipsis';
                        ellipsis.textContent = '...';
                        paginationControls.appendChild(ellipsis);
                    }
                }
                
                for (let i = startPage; i <= endPage; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = `btn-pagination ${i === this.reportCurrentPage ? 'active' : ''}`;
                    pageBtn.textContent = i;
                    pageBtn.addEventListener('click', () => {
                        this.reportCurrentPage = i;
                        this.refreshReportDisplay();
                    });
                    paginationControls.appendChild(pageBtn);
                }
                
                if (endPage < this.reportTotalPages) {
                    if (endPage < this.reportTotalPages - 1) {
                        const ellipsis = document.createElement('span');
                        ellipsis.className = 'pagination-ellipsis';
                        ellipsis.textContent = '...';
                        paginationControls.appendChild(ellipsis);
                    }
                    
                    const lastBtn = document.createElement('button');
                    lastBtn.className = 'btn-pagination';
                    lastBtn.textContent = this.reportTotalPages;
                    lastBtn.addEventListener('click', () => {
                        this.reportCurrentPage = this.reportTotalPages;
                        this.refreshReportDisplay();
                    });
                    paginationControls.appendChild(lastBtn);
                }
                
                // Next button
                const nextBtn = document.createElement('button');
                nextBtn.className = 'btn-pagination btn-pagination-next';
                nextBtn.innerHTML = '<span>Next</span> <i class="fas fa-chevron-right"></i>';
                nextBtn.disabled = this.reportCurrentPage === this.reportTotalPages;
                nextBtn.title = 'Next Page';
                nextBtn.addEventListener('click', () => {
                    if (this.reportCurrentPage < this.reportTotalPages) {
                        this.reportCurrentPage++;
                        this.refreshReportDisplay();
                    }
                });
                paginationControls.appendChild(nextBtn);
            }
            
            // Update table wrapper scrolling
            const tableWrappers = document.querySelectorAll('.professional-report-table-wrapper');
            tableWrappers.forEach(wrapper => {
                wrapper.setAttribute('data-per-page', this.reportPerPage.toString());
                if (this.reportPerPage > 5) {
                    wrapper.classList.add('report-table-scroll');
                } else {
                    wrapper.classList.remove('report-table-scroll');
                }
            });
        },

        getReportTotalCount() {
            // If reportTotalCount is already set (from format function with search applied), use it
            if (this.reportTotalCount !== undefined && this.reportTotalCount !== null) {
                return this.reportTotalCount;
            }
            
            if (!this.currentReportData) return 0;
            
            switch (this.currentReportType) {
                case 'trial-balance':
                    return this.currentReportData.accounts?.length || 0;
                case 'income-statement':
                case 'profit-loss':
                    // Count revenue and expenses
                    const revenueCount = this.currentReportData.revenue?.length || 0;
                    const expensesCount = this.currentReportData.expenses?.length || 0;
                    return revenueCount + expensesCount;
                case 'balance-sheet':
                    // Count assets, liabilities, and equity
                    const assetsCount = this.currentReportData.assets?.length || 0;
                    const liabilitiesCount = this.currentReportData.liabilities?.length || 0;
                    const equityCount = this.currentReportData.equity?.length || 0;
                    return assetsCount + liabilitiesCount + equityCount;
                case 'cash-flow':
                    return this.currentReportData.operating?.length || 0;
                case 'aged-receivables':
                case 'ages-debt-receivable':
                case 'ages-credit-receivable':
                    return this.currentReportData.receivables?.length || 0;
                case 'aged-payables':
                    return this.currentReportData.payables?.length || 0;
                case 'cash-book':
                    return this.currentReportData.transactions?.length || 0;
                case 'bank-book':
                    return this.currentReportData.transactions?.length || 0;
                case 'general-ledger':
                case 'general-ledger-report':
                    return this.currentReportData.accounts?.length || 0;
                case 'account-statement':
                    return this.currentReportData.accounts?.length || 0;
                case 'expense-statement':
                    return this.currentReportData.expenses?.length || 0;
                case 'chart-of-accounts-report':
                    return this.currentReportData.accounts?.length || 0;
                case 'value-added':
                    return this.currentReportData.data?.length || 0;
                case 'fixed-assets':
                    return this.currentReportData.assets?.length || 0;
                case 'entries-by-year':
                    return this.currentReportData.data?.length || 0;
                case 'customer-debits':
                    return this.currentReportData.customers?.length || 0;
                case 'statistical-position':
                    return this.currentReportData.data?.length || 0;
                case 'changes-equity':
                    // Count all equity changes entries (flattened from equity_changes)
                    if (this.currentReportData.data && this.currentReportData.data.length > 0) {
                        return this.currentReportData.data.length;
                    }
                    if (this.currentReportData.equity_changes) {
                        return this.currentReportData.equity_changes.reduce((sum, equity) => {
                            return sum + (equity.monthly_changes?.length || 1);
                        }, 0);
                    }
                    return 0;
                case 'financial-performance':
                    return this.currentReportData.performance_data?.length || 0;
                case 'comparative-report':
                    return this.currentReportData.data?.length || 0;
                default:
                    return 0;
            }
        },

        displayReportPlaceholderInPopup(reportType, reportName) {
            // Show report table with empty data instead of placeholder message
            this.displayReportInPopup(reportType, reportName, {});
            this.showToast(`${reportName} report displayed (no data available)`, 'info');
        },

        displayReport(reportType, reportName, reportData) {
            this.displayReportInPopup(reportType, reportName, reportData);
        },

        formatTrialBalance(reportData) {
            // Get all accounts and apply search filter
            let allAccounts = reportData.accounts || [];
            
            // Apply search filter if search term exists
            if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
                const searchTerm = this.reportSearchTerm.toLowerCase().trim();
                allAccounts = allAccounts.filter(account => {
                    const accountCode = (account.account_code || '').toLowerCase();
                    const accountName = (account.account_name || '').toLowerCase();
                    return accountCode.includes(searchTerm) || accountName.includes(searchTerm);
                });
            }
            
            // Update total count for pagination
            this.reportTotalCount = allAccounts.length;
            
            // Get paginated accounts (if perPage is 999999, show all)
            let paginatedAccounts = allAccounts;
            if (this.reportPerPage < 999999) {
                const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
                const endIndex = startIndex + this.reportPerPage;
                paginatedAccounts = allAccounts.slice(startIndex, endIndex);
            }
            
            let html = '<div class="professional-report-table-wrapper">';
            html += '<table class="professional-report-table">';
            html += '<thead>';
            html += '<tr>';
            html += '<th class="report-col-code">Account Code</th>';
            html += '<th class="report-col-name">Account Name</th>';
            html += '<th class="report-col-debit text-right">Debit</th>';
            html += '<th class="report-col-credit text-right">Credit</th>';
            html += '<th class="report-col-balance text-right">Balance</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';
            
            if (paginatedAccounts.length > 0) {
                paginatedAccounts.forEach((account, index) => {
                    const balance = parseFloat(account.balance || 0);
                    const balanceClass = balance >= 0 ? 'balance-positive' : 'balance-negative';
                    html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                    html += `<td class="report-col-code"><code>${this.escapeHtml(account.account_code || '')}</code></td>`;
                    html += `<td class="report-col-name">${this.escapeHtml(account.account_name || '')}</td>`;
                    html += `<td class="report-col-debit text-right debit-cell">${parseFloat(account.debit || 0) > 0 ? this.formatCurrency(parseFloat(account.debit || 0)) : '-'}</td>`;
                    html += `<td class="report-col-credit text-right credit-cell">${parseFloat(account.credit || 0) > 0 ? this.formatCurrency(parseFloat(account.credit || 0)) : '-'}</td>`;
                    html += `<td class="report-col-balance text-right ${balanceClass}">${this.formatCurrency(balance)}</td>`;
                    html += '</tr>';
                });
            } else {
                html += '<tr><td colspan="5" class="text-center report-empty-state">No accounts found</td></tr>';
            }
            
            html += '</tbody>';
            
            if (reportData.totals) {
                html += '<tfoot class="report-totals-footer">';
                html += '<tr class="report-totals-row">';
                html += '<td colspan="2" class="report-totals-label"><strong>TOTALS</strong></td>';
                html += `<td class="report-col-debit text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_debit || 0))}</strong></td>`;
                html += `<td class="report-col-credit text-right credit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_credit || 0))}</strong></td>`;
                html += `<td class="report-col-balance text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.difference || 0))}</strong></td>`;
                html += '</tr>';
                if (Math.abs(parseFloat(reportData.totals.difference || 0)) > 0.01) {
                    html += '<tr class="report-balance-warning">';
                    html += '<td colspan="5" class="text-center"><i class="fas fa-exclamation-triangle"></i> Trial Balance is not balanced!</td>';
                    html += '</tr>';
                }
                html += '</tfoot>';
            }
            
            html += '</table></div>';
            return html;
        },

        formatIncomeStatement(reportData) {
            let html = '<div class="professional-report-sections">';
            
            // Get revenue and expenses, apply search filter
            let revenue = reportData.revenue || [];
            let expenses = reportData.expenses || [];
            
            // Apply search filter if search term exists
            if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
                const searchTerm = this.reportSearchTerm.toLowerCase().trim();
                revenue = revenue.filter(item => {
                    const month = (item.month || '').toLowerCase();
                    return month.includes(searchTerm);
                });
                expenses = expenses.filter(item => {
                    const month = (item.month || '').toLowerCase();
                    return month.includes(searchTerm);
                });
            }
            
            // Update total count for pagination (combined revenue and expenses)
            this.reportTotalCount = revenue.length + expenses.length;
            
            // Revenue Section
            html += '<div class="report-section revenue-section">';
            html += '<div class="report-section-header">';
            html += '<h4><i class="fas fa-arrow-up"></i> Revenue</h4>';
            html += '</div>';
            html += '<div class="professional-report-table-wrapper">';
            html += '<table class="professional-report-table">';
            html += '<thead><tr><th class="report-col-period">Period</th><th class="report-col-amount text-right">Total Revenue</th></tr></thead>';
            html += '<tbody>';
            
            if (revenue.length > 0) {
                revenue.forEach((item, index) => {
                    html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                    html += `<td class="report-col-period">${this.escapeHtml(item.month || '')}</td>`;
                    html += `<td class="report-col-amount text-right credit-cell">${this.formatCurrency(parseFloat(item.total_revenue || 0))}</td>`;
                    html += '</tr>';
                });
            } else {
                html += '<tr><td colspan="2" class="text-center report-empty-state">No revenue data</td></tr>';
            }
            
            html += '</tbody></table></div></div>';
            
            // Expenses Section
            html += '<div class="report-section expense-section">';
            html += '<div class="report-section-header">';
            html += '<h4><i class="fas fa-arrow-down"></i> Expenses</h4>';
            html += '</div>';
            html += '<div class="professional-report-table-wrapper">';
            html += '<table class="professional-report-table">';
            html += '<thead><tr><th class="report-col-period">Period</th><th class="report-col-amount text-right">Total Expenses</th></tr></thead>';
            html += '<tbody>';
            
            if (expenses.length > 0) {
                expenses.forEach((item, index) => {
                    html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                    html += `<td class="report-col-period">${this.escapeHtml(item.month || '')}</td>`;
                    html += `<td class="report-col-amount text-right debit-cell">${this.formatCurrency(parseFloat(item.total_expenses || 0))}</td>`;
                    html += '</tr>';
                });
            } else {
                html += '<tr><td colspan="2" class="text-center report-empty-state">No expense data</td></tr>';
            }
            
            html += '</tbody></table></div></div>';
            
            // Summary Section
            if (reportData.totals) {
                html += '<div class="report-summary-section">';
                html += '<div class="professional-report-table-wrapper">';
                html += '<table class="professional-report-table report-summary-table">';
                html += '<tbody>';
                html += '<tr class="report-summary-row">';
                html += '<td class="report-summary-label"><strong>Total Revenue:</strong></td>';
                html += `<td class="report-summary-value text-right credit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_revenue || 0))}</strong></td>`;
                html += '</tr>';
                html += '<tr class="report-summary-row">';
                html += '<td class="report-summary-label"><strong>Total Expenses:</strong></td>';
                html += `<td class="report-summary-value text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_expenses || 0))}</strong></td>`;
                html += '</tr>';
                const netIncome = parseFloat(reportData.totals.net_income || 0);
                html += '<tr class="report-summary-row report-net-income-row">';
                html += '<td class="report-summary-label"><strong>Net Income:</strong></td>';
                html += `<td class="report-summary-value text-right ${netIncome >= 0 ? 'credit-cell' : 'debit-cell'}"><strong>${this.formatCurrency(netIncome)}</strong></td>`;
                html += '</tr>';
                html += '</tbody></table></div></div>';
            }
            
            html += '</div>';
            return html;
        },

        formatBalanceSheet(reportData) {
            let html = '<div class="professional-report-sections">';
            
            // Get assets, liabilities, and equity, apply search filter
            let assets = reportData.assets || [];
            let liabilities = reportData.liabilities || [];
            let equity = reportData.equity || [];
            
            // Apply search filter if search term exists
            if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
                const searchTerm = this.reportSearchTerm.toLowerCase().trim();
                assets = assets.filter(item => {
                    const code = (item.account_code || '').toLowerCase();
                    const name = (item.account_name || '').toLowerCase();
                    return code.includes(searchTerm) || name.includes(searchTerm);
                });
                liabilities = liabilities.filter(item => {
                    const code = (item.account_code || '').toLowerCase();
                    const name = (item.account_name || '').toLowerCase();
                    return code.includes(searchTerm) || name.includes(searchTerm);
                });
                equity = equity.filter(item => {
                    const code = (item.account_code || '').toLowerCase();
                    const name = (item.account_name || '').toLowerCase();
                    return code.includes(searchTerm) || name.includes(searchTerm);
                });
            }
            
            // Update total count for pagination (combined all sections)
            this.reportTotalCount = assets.length + liabilities.length + equity.length;
            
            // Assets Section
            html += '<div class="report-section assets-section">';
            html += '<div class="report-section-header">';
            html += '<h4><i class="fas fa-wallet"></i> Assets</h4>';
            html += '</div>';
            html += '<div class="professional-report-table-wrapper">';
            html += '<table class="professional-report-table">';
            html += '<thead><tr><th class="report-col-code">Account Code</th><th class="report-col-name">Account Name</th><th class="report-col-balance text-right">Balance</th></tr></thead>';
            html += '<tbody>';
            
            if (assets.length > 0) {
                assets.forEach((asset, index) => {
                    html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                    html += `<td class="report-col-code"><code>${this.escapeHtml(asset.account_code || '')}</code></td>`;
                    html += `<td class="report-col-name">${this.escapeHtml(asset.account_name || '')}</td>`;
                    html += `<td class="report-col-balance text-right">${this.formatCurrency(parseFloat(asset.balance || 0))}</td>`;
                    html += '</tr>';
                });
            } else {
                html += '<tr><td colspan="3" class="text-center report-empty-state">No assets found</td></tr>';
            }
            
            html += '</tbody></table></div></div>';
            
            // Liabilities Section
            html += '<div class="report-section liabilities-section">';
            html += '<div class="report-section-header">';
            html += '<h4><i class="fas fa-file-invoice-dollar"></i> Liabilities</h4>';
            html += '</div>';
            html += '<div class="professional-report-table-wrapper">';
            html += '<table class="professional-report-table">';
            html += '<thead><tr><th class="report-col-code">Account Code</th><th class="report-col-name">Account Name</th><th class="report-col-balance text-right">Balance</th></tr></thead>';
            html += '<tbody>';
            
            if (liabilities.length > 0) {
                liabilities.forEach((liability, index) => {
                    html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                    html += `<td class="report-col-code"><code>${this.escapeHtml(liability.account_code || '')}</code></td>`;
                    html += `<td class="report-col-name">${this.escapeHtml(liability.account_name || '')}</td>`;
                    html += `<td class="report-col-balance text-right debit-cell">${this.formatCurrency(parseFloat(liability.balance || 0))}</td>`;
                    html += '</tr>';
                });
            } else {
                html += '<tr><td colspan="3" class="text-center report-empty-state">No liabilities found</td></tr>';
            }
            
            html += '</tbody></table></div></div>';
            
            // Equity Section
            html += '<div class="report-section equity-section">';
            html += '<div class="report-section-header">';
            html += '<h4><i class="fas fa-chart-line"></i> Equity</h4>';
            html += '</div>';
            html += '<div class="professional-report-table-wrapper">';
            html += '<table class="professional-report-table">';
            html += '<thead><tr><th class="report-col-code">Account Code</th><th class="report-col-name">Account Name</th><th class="report-col-balance text-right">Balance</th></tr></thead>';
            html += '<tbody>';
            
            if (equity.length > 0) {
                equity.forEach((equityItem, index) => {
                    html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                    html += `<td class="report-col-code"><code>${this.escapeHtml(equityItem.account_code || '')}</code></td>`;
                    html += `<td class="report-col-name">${this.escapeHtml(equityItem.account_name || '')}</td>`;
                    html += `<td class="report-col-balance text-right">${this.formatCurrency(parseFloat(equityItem.balance || 0))}</td>`;
                    html += '</tr>';
                });
            } else {
                html += '<tr><td colspan="3" class="text-center report-empty-state">No equity found</td></tr>';
            }
            
            html += '</tbody></table></div></div>';
            
            // Summary Section
            if (reportData.totals) {
                html += '<div class="report-summary-section">';
                html += '<div class="professional-report-table-wrapper">';
                html += '<table class="professional-report-table report-summary-table">';
                html += '<tbody>';
                html += '<tr class="report-summary-row">';
                html += '<td class="report-summary-label"><strong>Total Assets:</strong></td>';
                html += `<td class="report-summary-value text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_assets || 0))}</strong></td>`;
                html += '</tr>';
                html += '<tr class="report-summary-row">';
                html += '<td class="report-summary-label"><strong>Total Liabilities:</strong></td>';
                html += `<td class="report-summary-value text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_liabilities || 0))}</strong></td>`;
                html += '</tr>';
                html += '<tr class="report-summary-row">';
                html += '<td class="report-summary-label"><strong>Total Equity:</strong></td>';
                html += `<td class="report-summary-value text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_equity || 0))}</strong></td>`;
                html += '</tr>';
                html += '<tr class="report-summary-row report-net-income-row">';
                html += '<td class="report-summary-label"><strong>Total Liabilities + Equity:</strong></td>';
                html += `<td class="report-summary-value text-right"><strong>${this.formatCurrency(parseFloat(reportData.totals.total_liabilities_equity || 0))}</strong></td>`;
                html += '</tr>';
                const balanceCheck = Math.abs(parseFloat(reportData.totals.total_assets || 0) - parseFloat(reportData.totals.total_liabilities_equity || 0));
                if (balanceCheck > 0.01) {
                    html += '<tr class="report-balance-warning">';
                    html += '<td colspan="2" class="text-center"><i class="fas fa-exclamation-triangle"></i> Balance Sheet is not balanced! Difference: ' + this.formatCurrency(balanceCheck) + '</td>';
                    html += '</tr>';
                }
                html += '</tbody></table></div></div>';
            }
            
            html += '</div>';
            return html;
        },

        formatCashFlow(reportData) {
            // Get all operating activities and apply search filter
            let allOperating = reportData?.operating || [];
            
            // Apply search filter if search term exists
            if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
                const searchTerm = this.reportSearchTerm.toLowerCase().trim();
                allOperating = allOperating.filter(item => {
                    const period = (item.period || '').toLowerCase();
                    const description = (item.description || '').toLowerCase();
                    return period.includes(searchTerm) || description.includes(searchTerm);
                });
            }
            
            // Update total count for pagination
            this.reportTotalCount = allOperating.length;
            
            // Get paginated operating activities (if perPage is 999999, show all)
            let paginatedOperating = allOperating;
            if (this.reportPerPage < 999999) {
                const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
                const endIndex = startIndex + this.reportPerPage;
                paginatedOperating = allOperating.slice(startIndex, endIndex);
            }
            
            let html = '<div class="professional-report-sections">';
            
            html += '<div class="report-section operating-section">';
            html += '<div class="report-section-header">';
            html += '<h4><i class="fas fa-money-bill-wave"></i> Operating Activities</h4>';
            html += '</div>';
            html += '<div class="professional-report-table-wrapper">';
            html += '<table class="professional-report-table">';
            html += '<thead>';
            html += '<tr>';
            html += '<th class="report-col-period">Period</th>';
            html += '<th class="report-col-amount text-right">Cash In</th>';
            html += '<th class="report-col-amount text-right">Cash Out</th>';
            html += '<th class="report-col-amount text-right">Net Flow</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';
            
            if (paginatedOperating.length > 0) {
                paginatedOperating.forEach((item, index) => {
                    const netFlow = parseFloat(item.cash_in || 0) - parseFloat(item.cash_out || 0);
                    html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'}">`;
                    html += `<td class="report-col-period">${this.escapeHtml(item.month || '')}</td>`;
                    html += `<td class="report-col-amount text-right credit-cell">${this.formatCurrency(parseFloat(item.cash_in || 0))}</td>`;
                    html += `<td class="report-col-amount text-right debit-cell">${this.formatCurrency(parseFloat(item.cash_out || 0))}</td>`;
                    html += `<td class="report-col-amount text-right ${netFlow >= 0 ? 'credit-cell' : 'debit-cell'}">${this.formatCurrency(netFlow)}</td>`;
                    html += '</tr>';
                });
            } else {
                html += '<tr><td colspan="4" class="text-center report-empty-state">No cash flow data</td></tr>';
            }
            
            html += '</tbody></table></div></div>';
            
            // Summary Section
            if (reportData.totals) {
                html += '<div class="report-summary-section">';
                html += '<div class="professional-report-table-wrapper">';
                html += '<table class="professional-report-table report-summary-table">';
                html += '<tbody>';
                const operatingFlow = parseFloat(reportData.totals.operating_cash_flow || 0);
                html += '<tr class="report-summary-row">';
                html += '<td class="report-summary-label"><strong>Operating Cash Flow:</strong></td>';
                html += `<td class="report-summary-value text-right ${operatingFlow >= 0 ? 'credit-cell' : 'debit-cell'}"><strong>${this.formatCurrency(operatingFlow)}</strong></td>`;
                html += '</tr>';
                const netFlow = parseFloat(reportData.totals.net_cash_flow || 0);
                html += '<tr class="report-summary-row report-net-income-row">';
                html += '<td class="report-summary-label"><strong>Net Cash Flow:</strong></td>';
                html += `<td class="report-summary-value text-right ${netFlow >= 0 ? 'credit-cell' : 'debit-cell'}"><strong>${this.formatCurrency(netFlow)}</strong></td>`;
                html += '</tr>';
                html += '</tbody></table></div></div>';
            }
            
            html += '</div>';
            return html;
        },

        formatAgedReceivables(reportData) {
            // Get all receivables and apply search filter
            let allReceivables = reportData?.receivables || [];
            
            // Apply search filter if search term exists
            if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
                const searchTerm = this.reportSearchTerm.toLowerCase().trim();
                allReceivables = allReceivables.filter(item => {
                    const invoiceNumber = (item.invoice_number || '').toLowerCase();
                    const customerName = (item.customer_name || '').toLowerCase();
                    return invoiceNumber.includes(searchTerm) || customerName.includes(searchTerm);
                });
            }
            
            // Update total count for pagination
            this.reportTotalCount = allReceivables.length;
            
            // Get paginated receivables (if perPage is 999999, show all)
            let paginatedReceivables = allReceivables;
            if (this.reportPerPage < 999999) {
                const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
                const endIndex = startIndex + this.reportPerPage;
                paginatedReceivables = allReceivables.slice(startIndex, endIndex);
            }
            
            let html = '<div class="professional-report-table-wrapper">';
            html += '<table class="professional-report-table">';
            html += '<thead>';
            html += '<tr>';
            html += '<th class="report-col-invoice">Invoice #</th>';
            html += '<th class="report-col-customer">Customer</th>';
            html += '<th class="report-col-date">Invoice Date</th>';
            html += '<th class="report-col-date">Due Date</th>';
            html += '<th class="report-col-amount text-right">Total Amount</th>';
            html += '<th class="report-col-amount text-right">Paid</th>';
            html += '<th class="report-col-amount text-right">Balance</th>';
            html += '<th class="report-col-days text-right">Days Overdue</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';
            
            if (Array.isArray(paginatedReceivables) && paginatedReceivables.length > 0) {
                paginatedReceivables.forEach((item, index) => {
                    const daysOverdue = parseInt(item.days_overdue || 0);
                    const overdueClass = daysOverdue > 0 ? 'overdue' : '';
                    html += `<tr class="report-data-row ${index % 2 === 0 ? 'even' : 'odd'} ${overdueClass}">`;
                    const invoiceNumber = this.escapeHtml(item.invoice_number || '');
                    const customerName = this.escapeHtml(item.customer_name || '');
                    html += `<td class="report-col-invoice" title="${invoiceNumber}"><code>${invoiceNumber}</code></td>`;
                    html += `<td class="report-col-customer" title="${customerName}">${customerName}</td>`;
                    html += `<td class="report-col-date" title="${this.formatDate(item.invoice_date || '')}">${this.formatDate(item.invoice_date || '')}</td>`;
                    html += `<td class="report-col-date" title="${this.formatDate(item.due_date || '')}">${this.formatDate(item.due_date || '')}</td>`;
                    html += `<td class="report-col-amount text-right">${this.formatCurrency(parseFloat(item.total_amount || 0))}</td>`;
                    html += `<td class="report-col-amount text-right credit-cell">${this.formatCurrency(parseFloat(item.paid_amount || 0))}</td>`;
                    html += `<td class="report-col-amount text-right debit-cell">${this.formatCurrency(parseFloat(item.balance || 0))}</td>`;
                    html += `<td class="report-col-days text-right ${daysOverdue > 0 ? 'overdue-badge' : ''}">${daysOverdue > 0 ? daysOverdue : '-'}</td>`;
                    html += '</tr>';
                });
            } else {
                html += '<tr><td colspan="8" class="text-center report-empty-state">No receivables found</td></tr>';
            }
            
            html += '</tbody>';
            
            if (reportData?.total_outstanding !== undefined) {
                html += '<tfoot class="report-totals-footer">';
                html += '<tr class="report-totals-row">';
                html += '<td colspan="6" class="report-totals-label"><strong>Total Outstanding:</strong></td>';
                html += `<td class="report-col-amount text-right debit-cell"><strong>${this.formatCurrency(parseFloat(reportData.total_outstanding || 0))}</strong></td>`;
                html += '<td></td>';
                html += '</tr>';
                html += '</tfoot>';
            }
            
            html += '</table></div>';
            return html;
        },

        formatAgedPayables(reportData) {
            // Get all payables and apply search filter
            let allPayables = reportData?.payables || [];
            
            // Apply search filter if search term exists
            if (this.reportSearchTerm && this.reportSearchTerm.trim() !== '') {
                const searchTerm = this.reportSearchTerm.toLowerCase().trim();
                allPayables = allPayables.filter(item => {
                    const billNumber = (item.bill_number || '').toLowerCase();
                    const vendorName = (item.vendor_name || '').toLowerCase();
                    return billNumber.includes(searchTerm) || vendorName.includes(searchTerm);
                });
            }
            
            // Update total count for pagination
            this.reportTotalCount = allPayables.length;
            
            // Get paginated payables (if perPage is 999999, show all)
            let paginatedPayables = allPayables;
            if (this.reportPerPage < 999999) {
                const startIndex = (this.reportCurrentPage - 1) * this.reportPerPage;
                const endIndex = startIndex + this.reportPerPage;
                paginatedPayables = allPayables.slice(startIndex, endIndex);
            }
            
            let html = '<div class="professional-report-table-wrapper">';
            html += '<table class="professional-report-table">';
            html += '<thead>';
            html += '<tr>';
            html += '<th class="report-col-invoice">Bill #</th>';
            html += '<th class="report-col-customer">Vendor</th>';
            html += '<th class="report-col-date">Bill Date</th>';
            html += '<th class="report-col-date">Due Date</th>';
            html += '<th class="report-col-amount text-right">Total Amount</th>';
            html += '<th class="report-col-amount text-right">Paid</th>';
            html += '<th class="report-col-amount text-right">Balance</th>';
            html += '<th class="report-col-days text-right">Days Overdue</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';
            
            if (Array.isArray(paginatedPayables) && paginatedPayables.length > 0) {
                paginatedPayables.forEach((item, index) => {
                    const daysOverdue = parseInt(item.days_overdue || 0);
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
        },

        formatCashBook(reportData) {
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
        },

        formatBankBook(reportData) {
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
        },

        getReportStatusCards(reportType, reportData) {
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
        },

        getGeneralLedgerStatusCards(reportData) {
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
        },

        getTrialBalanceStatusCards(reportData) {
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
        },

        getIncomeStatementStatusCards(reportData) {
            const revenue = parseFloat(reportData?.totals?.total_revenue || 0);
            const expenses = parseFloat(reportData?.totals?.total_expenses || 0);
            const netIncome = revenue - expenses;
            
            let html = '<div class="report-status-cards">';
            html += this.createStatCard('success', 'fa-arrow-up', this.formatCurrency(revenue), 'Total Revenue');
            html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(expenses), 'Total Expenses');
            html += this.createStatCard(netIncome >= 0 ? 'success' : 'debit', 'fa-chart-line', this.formatCurrency(netIncome), 'Net Income');
            html += '</div>';
            return html;
        },

        getBalanceSheetStatusCards(reportData) {
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
        },

        getCashFlowStatusCards(reportData) {
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
        },

        getAgedReceivablesStatusCards(reportData) {
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
        },

        getAgedPayablesStatusCards(reportData) {
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
        },

        getCashBookStatusCards(reportData) {
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
        },

        getBankBookStatusCards(reportData) {
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
        },

        getAccountStatementStatusCards(reportData) {
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
        },

        getExpenseStatementStatusCards(reportData) {
            const expenses = reportData?.expenses || [];
            const totalExpenses = parseFloat(reportData?.totals?.total_expenses || 0);
            const categories = new Set(expenses.map(e => e.category || 'Uncategorized')).size;
            
            let html = '<div class="report-status-cards">';
            html += this.createStatCard('primary', 'fa-list', expenses.length, 'Expenses');
            html += this.createStatCard('info', 'fa-tags', categories, 'Categories');
            html += this.createStatCard('debit', 'fa-dollar-sign', this.formatCurrency(totalExpenses), 'Total Expenses');
            html += '</div>';
            return html;
        },

        getChartOfAccountsStatusCards(reportData) {
            const accounts = reportData?.accounts || [];
            const active = accounts.filter(a => a.is_active !== false).length;
            const inactive = accounts.length - active;
            
            let html = '<div class="report-status-cards">';
            html += this.createStatCard('primary', 'fa-book', accounts.length, 'Total Accounts');
            html += this.createStatCard('success', 'fa-check-circle', active, 'Active');
            html += this.createStatCard('warning', 'fa-times-circle', inactive, 'Inactive');
            html += '</div>';
            return html;
        },

        getValueAddedStatusCards(reportData) {
            const revenue = parseFloat(reportData?.revenue || 0);
            const cogs = parseFloat(reportData?.cogs || 0);
            const valueAdded = revenue - cogs;
            
            let html = '<div class="report-status-cards">';
            html += this.createStatCard('success', 'fa-arrow-up', this.formatCurrency(revenue), 'Revenue');
            html += this.createStatCard('debit', 'fa-arrow-down', this.formatCurrency(cogs), 'COGS');
            html += this.createStatCard('info', 'fa-plus-circle', this.formatCurrency(valueAdded), 'Value Added');
            html += '</div>';
            return html;
        },

        getFixedAssetsStatusCards(reportData) {
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
        },

        getEntriesByYearStatusCards(reportData) {
            const entries = reportData?.entries || [];
            const years = new Set(entries.map(e => e.year || 'Unknown')).size;
            const totalEntries = entries.reduce((sum, e) => sum + (e.count || 0), 0);
            
            let html = '<div class="report-status-cards">';
            html += this.createStatCard('primary', 'fa-calendar-alt', years, 'Years');
            html += this.createStatCard('info', 'fa-list', totalEntries, 'Total Entries');
            html += '</div>';
            return html;
        },

        getCustomerDebitsStatusCards(reportData) {
            const debits = reportData?.debits || [];
            const totalDebit = parseFloat(reportData?.totals?.total_debit || 0);
            const customers = new Set(debits.map(d => d.customer_id || d.customer_name)).size;
            
            let html = '<div class="report-status-cards">';
            html += this.createStatCard('primary', 'fa-users', customers, 'Customers');
            html += this.createStatCard('info', 'fa-list', debits.length, 'Debits');
            html += this.createStatCard('debit', 'fa-dollar-sign', this.formatCurrency(totalDebit), 'Total Debit');
            html += '</div>';
            return html;
        },

        getStatisticalPositionStatusCards(reportData) {
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
        },

        getChangesInEquityStatusCards(reportData) {
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
        },

        getFinancialPerformanceStatusCards(reportData) {
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
        },

        getComparativeReportStatusCards(reportData) {
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
        },

        createStatCard(type, icon, value, label) {
            const typeClass = `stat-card-${type}`;
            const iconClass = `stat-icon-${type}`;
            return `<div class="stat-card ${typeClass}">
                <i class="fas ${icon} stat-icon ${iconClass}"></i>
                <div class="stat-info">
                    <span class="stat-value">${value}</span>
                    <span class="stat-label">${label}</span>
                </div>
            </div>`;
        },

        formatGeneralLedgerReport(reportData) {
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
        },

        formatExpenseStatement(reportData) {
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
        },

        formatAccountStatement(reportData) {
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
        },

        formatValueAdded(reportData) {
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
        },

        formatFixedAssets(reportData) {
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
        },

        formatEntriesByYear(reportData) {
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
        },

        formatCustomerDebits(reportData) {
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
        },

        formatStatisticalPosition(reportData) {
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
        },

        formatChangesInEquity(reportData) {
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
        },

        formatFinancialPerformance(reportData) {
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
        },

        formatComparativeReport(reportData) {
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
        },

        formatGenericReport(reportData, reportName = 'Report') {
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
        },

        formatChartOfAccounts(reportData) {
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
        },

        displayReportPlaceholder(reportType, reportName) {
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
        },

        restoreReportsGrid() {
            const reportsGrid = document.getElementById('modalReportsGrid');
            const reportContent = document.getElementById('modalReportContent');
            
            if (reportsGrid) {
                reportsGrid.classList.remove('reports-grid-hidden');
                reportsGrid.classList.add('reports-grid-visible');
            }
            
            if (reportContent) {
                reportContent.classList.remove('report-content-visible');
                reportContent.classList.add('report-content-hidden');
                reportContent.classList.remove('show');
                reportContent.innerHTML = '';
            }
            
            this.attachReportCardListeners();
        },

        exportCurrentReport(format = 'csv') {
            if (!this.currentReportType || !this.currentReportData) {
                this.showToast('No report to export', 'warning');
                return;
            }
            try {
                const reportName = this.getReportName(this.currentReportType);
                const dateStr = new Date().toISOString().split('T')[0];
                
                if (format === 'csv') {
                    this.exportReportToCSV(reportName, dateStr);
                } else if (format === 'excel') {
                    this.exportReportToExcel(reportName, dateStr);
                } else if (format === 'json') {
                    const reportData = {
                        type: this.currentReportType,
                        data: this.currentReportData,
                        generated: new Date().toISOString()
                    };
                    const jsonData = JSON.stringify(reportData, null, 2);
                    const blob = new Blob([jsonData], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `report-${this.currentReportType}-${dateStr}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                }
                
                this.showToast(`Report exported as ${format.toUpperCase()} successfully`, 'success');
            } catch (error) {
                this.showToast('Error exporting report: ' + error.message, 'error');
            }
        },

        exportReportToCSV(reportName, dateStr) {
            let csvContent = '';
            
            // Add report header
            csvContent += `${reportName} Report\n`;
            csvContent += `Generated: ${dateStr}\n\n`;
            
            // Get data based on report type
            const reportData = this.currentReportData;
            
            if (this.currentReportType === 'trial-balance' || this.currentReportType === 'general-ledger') {
                const accounts = reportData?.accounts || [];
                if (accounts.length > 0) {
                    // CSV Headers
                    if (this.currentReportType === 'trial-balance') {
                        csvContent += 'Account Code,Account Name,Debit,Credit,Balance\n';
                        accounts.forEach(account => {
                            csvContent += `"${account.account_code || ''}","${account.account_name || ''}",${account.debit || 0},${account.credit || 0},${account.balance || 0}\n`;
                        });
                    } else {
                        // General Ledger - more complex structure
                        csvContent += 'Account Code,Account Name,Date,Description,Reference,Type,Debit,Credit,Balance\n';
                        accounts.forEach(account => {
                            const transactions = account.transactions || [];
                            if (transactions.length > 0) {
                                transactions.forEach(txn => {
                                    csvContent += `"${account.account_code || ''}","${account.account_name || ''}","${txn.transaction_date || ''}","${(txn.description || '').replace(/"/g, '""')}","${txn.reference_number || ''}","${txn.transaction_type || ''}",${txn.debit_amount || 0},${txn.credit_amount || 0},${(txn.debit_amount || 0) - (txn.credit_amount || 0)}\n`;
                                });
                            } else {
                                csvContent += `"${account.account_code || ''}","${account.account_name || ''}","","No transactions","","",0,0,0\n`;
                            }
                        });
                    }
                }
            } else if (this.currentReportType === 'income-statement') {
                csvContent += 'Period,Revenue,Expenses,Net Income\n';
                const revenue = reportData?.revenue || [];
                const expenses = reportData?.expenses || [];
                const periods = new Set([...revenue.map(r => r.month), ...expenses.map(e => e.month)]);
                periods.forEach(period => {
                    const rev = revenue.find(r => r.month === period)?.total_revenue || 0;
                    const exp = expenses.find(e => e.month === period)?.total_expenses || 0;
                    csvContent += `"${period}",${rev},${exp},${rev - exp}\n`;
                });
            } else if (this.currentReportType === 'balance-sheet') {
                csvContent += 'Account Code,Account Name,Type,Balance\n';
                const assets = reportData?.assets || [];
                const liabilities = reportData?.liabilities || [];
                const equity = reportData?.equity || [];
                [...assets, ...liabilities, ...equity].forEach(item => {
                    const type = assets.includes(item) ? 'Asset' : (liabilities.includes(item) ? 'Liability' : 'Equity');
                    csvContent += `"${item.account_code || ''}","${item.account_name || ''}","${type}",${item.balance || 0}\n`;
                });
            } else if (this.currentReportType === 'cash-flow') {
                csvContent += 'Period,Cash In,Cash Out,Net Flow\n';
                const operating = reportData?.operating || [];
                operating.forEach(item => {
                    csvContent += `"${item.month || item.period || ''}",${item.cash_in || 0},${item.cash_out || 0},${(item.cash_in || 0) - (item.cash_out || 0)}\n`;
                });
            } else if (this.currentReportType === 'aged-receivables' || this.currentReportType === 'ages-debt-receivable' || this.currentReportType === 'ages-credit-receivable') {
                csvContent += 'Invoice Number,Customer,Invoice Date,Due Date,Total Amount,Paid,Balance,Days Overdue\n';
                const receivables = reportData?.receivables || [];
                receivables.forEach(item => {
                    csvContent += `"${item.invoice_number || ''}","${(item.customer_name || '').replace(/"/g, '""')}","${item.invoice_date || ''}","${item.due_date || ''}",${item.total_amount || 0},${item.paid_amount || 0},${item.balance || 0},${item.days_overdue || 0}\n`;
                });
            } else if (this.currentReportType === 'aged-payables') {
                csvContent += 'Bill Number,Vendor,Bill Date,Due Date,Total Amount,Paid,Balance,Days Overdue\n';
                const payables = reportData?.payables || [];
                payables.forEach(item => {
                    csvContent += `"${item.bill_number || ''}","${(item.vendor_name || '').replace(/"/g, '""')}","${item.bill_date || ''}","${item.due_date || ''}",${item.total_amount || 0},${item.paid_amount || 0},${item.balance || 0},${item.days_overdue || 0}\n`;
                });
            } else if (this.currentReportType === 'cash-book' || this.currentReportType === 'bank-book') {
                csvContent += 'Date,Description,Reference,Type,Debit,Credit,Balance\n';
                const transactions = reportData?.transactions || [];
                transactions.forEach(item => {
                    csvContent += `"${item.transaction_date || ''}","${(item.description || '').replace(/"/g, '""')}","${item.reference_number || ''}","${item.transaction_type || ''}",${item.debit_amount || 0},${item.credit_amount || 0},${item.balance || 0}\n`;
                });
            } else if (this.currentReportType === 'expense-statement') {
                csvContent += 'Date,Category,Description,Amount\n';
                const expenses = reportData?.expenses || [];
                expenses.forEach(item => {
                    csvContent += `"${item.date || ''}","${item.category || ''}","${(item.description || '').replace(/"/g, '""')}",${item.amount || 0}\n`;
                });
            } else if (this.currentReportType === 'chart-of-accounts-report') {
                csvContent += 'Account Code,Account Name,Category,Balance\n';
                const grouped = reportData?.grouped || {};
                Object.keys(grouped).forEach(category => {
                    const accounts = grouped[category] || [];
                    accounts.forEach(account => {
                        csvContent += `"${account.account_code || ''}","${(account.account_name || '').replace(/"/g, '""')}","${category}",${account.balance || 0}\n`;
                    });
                });
            } else if (this.currentReportType === 'fixed-assets') {
                csvContent += 'Account Code,Account Name,Purchase Date,Cost,Depreciation,Current Value\n';
                const assets = reportData?.assets || [];
                assets.forEach(item => {
                    csvContent += `"${item.account_code || ''}","${(item.account_name || '').replace(/"/g, '""')}","${item.purchase_date || ''}",${item.cost || 0},${item.depreciation || 0},${item.current_value || item.balance || 0}\n`;
                });
            } else if (this.currentReportType === 'customer-debits') {
                csvContent += 'Customer,Total Invoiced,Total Paid,Balance\n';
                const customers = reportData?.customers || reportData?.debits || [];
                customers.forEach(item => {
                    csvContent += `"${(item.customer_name || item.name || '').replace(/"/g, '""')}",${item.total_invoiced || item.total_debit || 0},${item.total_paid || 0},${item.balance || 0}\n`;
                });
            } else if (this.currentReportType === 'value-added' || this.currentReportType === 'entries-by-year' || this.currentReportType === 'statistical-position' || this.currentReportType === 'financial-performance' || this.currentReportType === 'comparative-report' || this.currentReportType === 'changes-equity') {
                // Generic export for reports with data array
                const allData = reportData?.data || reportData?.equity_changes || reportData?.performance_data || [];
                if (allData.length > 0) {
                    // Use first row keys as headers
                    const headers = Object.keys(allData[0]);
                    csvContent += headers.map(h => h.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())).join(',') + '\n';
                    allData.forEach(row => {
                        csvContent += headers.map(h => {
                            const val = row[h];
                            if (typeof val === 'string') {
                                return `"${val.replace(/"/g, '""')}"`;
                            }
                            return val !== null && val !== undefined ? val : '';
                        }).join(',') + '\n';
                    });
                }
            } else {
                // Fallback: export any available data structure
                if (reportData?.data && Array.isArray(reportData.data) && reportData.data.length > 0) {
                    const headers = Object.keys(reportData.data[0]);
                    csvContent += headers.map(h => h.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())).join(',') + '\n';
                    reportData.data.forEach(row => {
                        csvContent += headers.map(h => {
                            const val = row[h];
                            if (typeof val === 'string') {
                                return `"${val.replace(/"/g, '""')}"`;
                            }
                            return val !== null && val !== undefined ? val : '';
                        }).join(',') + '\n';
                    });
                } else {
                    // Export as key-value pairs if no structured data
                    csvContent += 'Field,Value\n';
                    Object.keys(reportData).forEach(key => {
                        if (key !== 'data' && typeof reportData[key] !== 'object') {
                            csvContent += `"${key}","${String(reportData[key]).replace(/"/g, '""')}"\n`;
                        }
                    });
                }
            }
            
            // Add totals if available
            if (reportData?.totals) {
                csvContent += '\n';
                csvContent += 'Totals\n';
                Object.keys(reportData.totals).forEach(key => {
                    csvContent += `${key},${reportData.totals[key]}\n`;
                });
            }
            
            // Create and download CSV
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `report-${this.currentReportType}-${dateStr}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        },

        exportReportToExcel(reportName, dateStr) {
            // For Excel, we'll create a CSV with Excel-compatible format
            // Most browsers can open CSV files in Excel
            this.exportReportToCSV(reportName, dateStr);
            
            // Alternatively, we could use a library like SheetJS, but CSV works for most cases
            // If you want true Excel format, you'd need to add a library like xlsx
        },

        showExportMenu(button) {
            // Remove existing menu if any
            const existingMenu = document.querySelector('.export-format-menu');
            if (existingMenu) {
                existingMenu.remove();
                return;
            }
            
            // Create dropdown menu
            const menu = document.createElement('div');
            menu.className = 'export-format-menu';
            menu.innerHTML = `
                <div class="export-menu-item" data-format="csv">
                    <i class="fas fa-file-csv"></i> Export as CSV
                </div>
                <div class="export-menu-item" data-format="excel">
                    <i class="fas fa-file-excel"></i> Export as Excel
                </div>
                <div class="export-menu-item" data-format="json">
                    <i class="fas fa-file-code"></i> Export as JSON
                </div>
            `;
            
            // Position menu near button
            const rect = button.getBoundingClientRect();
            menu.style.position = 'fixed';
            menu.style.top = (rect.bottom + 5) + 'px';
            menu.style.left = rect.left + 'px';
            menu.style.zIndex = '10000';
            menu.style.backgroundColor = '#fff';
            menu.style.border = '1px solid #ddd';
            menu.style.borderRadius = '4px';
            menu.style.boxShadow = '0 2px 8px rgba(0,0,0,0.15)';
            menu.style.padding = '4px 0';
            menu.style.minWidth = '180px';
            
            // Style menu items
            const style = document.createElement('style');
            style.textContent = `
                .export-format-menu .export-menu-item {
                    padding: 8px 16px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    color: #333;
                }
                .export-format-menu .export-menu-item:hover {
                    background-color: #f5f5f5;
                }
                .export-format-menu .export-menu-item i {
                    width: 16px;
                }
            `;
            if (!document.getElementById('export-menu-styles')) {
                style.id = 'export-menu-styles';
                document.head.appendChild(style);
            }
            
            document.body.appendChild(menu);
            
            // Handle menu item clicks
            menu.querySelectorAll('.export-menu-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const format = item.getAttribute('data-format');
                    this.exportCurrentReport(format);
                    menu.remove();
                });
            });
            
            // Close menu when clicking outside
            const closeMenu = (e) => {
                if (!menu.contains(e.target) && e.target !== button) {
                    menu.remove();
                    document.removeEventListener('click', closeMenu);
                }
            };
            setTimeout(() => document.addEventListener('click', closeMenu), 100);
        },

        async exportAllReports() {
            try {
                const response = await fetch(`${this.apiBase}/reports.php?action=export_all`);
                if (response.ok) {
                    const blob = await response.blob();
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `all-reports-${new Date().toISOString().split('T')[0]}.zip`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    this.showToast('All reports exported successfully', 'success');
                } else {
                    this.showToast('Failed to export reports', 'error');
                }
            } catch (error) {
                this.showToast('Error exporting reports: ' + error.message, 'error');
            }
        }
    };
    Object.assign(ProfessionalAccounting.prototype, methods);
})();
