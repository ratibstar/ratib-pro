/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.core.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.core.js`.
 */
/**
 * Professional Accounting System - Main JavaScript
 * Complete frontend functionality for the accounting system
 */

class ProfessionalAccounting {
    constructor() {
        // Use dynamic base URL from config or fallback
        const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
        this.apiBase = baseUrl + '/api/accounting';
        this.currentTab = 'dashboard';
        this.revenueExpenseNetChart = null;
        this.cashBalanceChart = null;
        this.receivablePayableChart = null;
        this.expenseBreakdownChart = null;
        this.invoiceAgingChart = null;
        this.financialOverviewChart = null;
        this.reportsOriginalContent = null;
        this.activeModal = null;
        // Pagination state
        this.transactionsCurrentPage = 1;
        this.transactionsPerPage = 5;
        this.transactionsTotalPages = 1;
        this.transactionsTotalCount = 0;
        this.allTransactions = [];
        this.loadingEntities = false;
        // Modal pagination state
        this.modalArCurrentPage = 1;
        this.modalArPerPage = 5;
        this.modalArTotalPages = 1;
        this.modalArSearch = '';
        this.modalApCurrentPage = 1;
        this.modalApPerPage = 5;
        this.modalApTotalPages = 1;
        this.modalApSearch = '';
        this.modalBankCurrentPage = 1;
        this.modalBankPerPage = 5;
        this.modalBankTotalPages = 1;
        this.modalBankSearch = '';
        this.modalLedgerCurrentPage = 1;
        this.modalLedgerPerPage = 5;
        this.modalLedgerTotalPages = 1;
        this.modalLedgerSearch = '';
        // Chart of Accounts pagination state
        this.coaCurrentPage = 1;
        this.coaPerPage = 5;
        this.coaTotalPages = 1;
        this.coaTotalCount = 0;
        this.coaSearch = '';
        this.coaAccountTypeFilter = '';
        this.coaSortColumn = 'account_code';
        this.coaSortDirection = 'asc';
        this.coaSelectedAccounts = new Set();
        // Banking & Cash pagination and filter state
        this.bankingCurrentPage = 1;
        this.bankingPerPage = 5;
        this.bankingTotalPages = 1;
        this.bankingTotalCount = 0;
        this.bankingSearch = '';
        this.bankingTypeFilter = '';
        this.bankingStatusFilter = '';
        this.bankingSortColumn = 'id';
        this.bankingSortDirection = 'desc';
        this.bankingSelectedAccounts = new Set();
        this.bankingAllAccounts = [];
        this._savingBankAccount = false;
        // Report pagination state
        this.reportCurrentPage = 1;
        this.reportPerPage = 5;
        this.reportTotalPages = 1;
        this.reportTotalCount = 0;
        this.reportSearchTerm = '';
        
        // Entry Approval pagination
        this.entryApprovalCurrentPage = 1;
        this.entryApprovalPerPage = 5;
        this.entryApprovalTotalCount = 0;
        this.entryApprovalSearchTerm = '';
        this.entryApprovalData = [];
        
        // Cost Centers pagination
        this.costCentersCurrentPage = 1;
        this.costCentersPerPage = 5;
        this.costCentersTotalCount = 0;
        this.costCentersSearchTerm = '';
        this.costCentersData = [];
        
        // Bank Guarantee pagination
        this.bankGuaranteeCurrentPage = 1;
        this.bankGuaranteePerPage = 5;
        this.bankGuaranteeTotalCount = 0;
        this.bankGuaranteeSearchTerm = '';
        this.bankGuaranteeData = [];
        this.currentReportType = null;
        this.currentReportData = null;
        // Follow-ups pagination state
        this.followupCurrentPage = 1;
        this.followupPerPage = 5;
        this.followupTotalPages = 1;
        this.followupTotalCount = 0;
        this.allFollowups = [];
        // Messages pagination state
        this.messageCurrentPage = 1;
        this.messagePerPage = 5;
        this.messageTotalPages = 1;
        this.messageTotalCount = 0;
        this.allMessages = [];
        this.init();
    }

    init() {
        // Clean up any stray modal overlays on page load
        // Disabled cleanup to prevent modals from being removed
        // this.cleanupStrayOverlays();
        
        // Initialize default currency from system settings
        this.initDefaultCurrency();
        
        // Listen for storage changes (when currency is updated in another tab/window)
        window.addEventListener('storage', (e) => {
            if (e.key === 'accounting_default_currency' || e.key === null) {
                // Currency was changed, refresh dashboard cards
                if (this.currentTab === 'dashboard') {
                    this.refreshDashboardCards();
                }
            }
        });
        
        // Listen for page visibility change (user might have changed currency in another tab)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && this.currentTab === 'dashboard') {
                // Page became visible, refresh currency and dashboard
                this.initDefaultCurrency().then(() => {
                    if (typeof this.refreshDashboardCards === 'function') {
                        this.refreshDashboardCards();
                    }
                });
            }
        });
        
        // Check if tables exist and hide setup button if they do
        setTimeout(() => {
            this.checkTablesExist();
        }, 1000);
        
        // Ensure tab buttons are clickable after permissions are applied
        setTimeout(() => {
            this.ensureTabButtonsClickable();
        }, 1000);
        
        // Re-ensure after permissions are applied
        if (window.UserPermissions) {
            const originalApply = window.UserPermissions.applyPermissions;
            if (originalApply) {
                window.UserPermissions.applyPermissions = function() {
                    originalApply.call(this);
                    if (window.professionalAccounting) {
                        setTimeout(() => {
                            window.professionalAccounting.ensureTabButtonsClickable();
                        }, 100);
                    }
                };
            }
        }
        
        // Listen for transaction saved events to refresh tables
        window.addEventListener('accounting-transaction-saved', async (e) => {
            // Refresh main page tables if on accounting page
            if (window.location.pathname.includes('accounting.php')) {
                // Ensure modals exist in DOM - create them if missing
                this.ensureModalsExist();
                // Small delay to ensure backend mirroring is complete
                setTimeout(async () => {
                    if (typeof this.loadInvoices === 'function') {
                        await this.loadInvoices();
                    }
                    if (typeof this.loadBills === 'function') {
                        await this.loadBills();
                    }
                    if (typeof this.refreshAllModules === 'function') {
                        this.refreshAllModules();
                    }
                }, 1500);
            }
        });
        this.setupEventListeners();
        this.setupRecentTransactionsPaginationControls();
        this.loadDashboard();
        this.loadFinancialOverview();
        this.initializeDates();
        
        // Auto-generate alerts on page load (once per day)
        this.checkAndGenerateAlerts();
        
        // Show success message on initialization
        setTimeout(() => {
            this.showToast('Accounting system loaded successfully!', 'success', 5000);
        }, 1500);
    }

    saveReportsOriginalContent() {
        const reportsTab = document.getElementById('financialReportsTab') || document.getElementById('reportsTab');
        if (reportsTab) {
            const moduleContent = reportsTab.querySelector('.module-content');
            if (moduleContent && !this.reportsOriginalContent) {
                this.reportsOriginalContent = moduleContent.innerHTML;
            }
        }
    }

}