/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.part1.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.part1.js`.
 */
/** Professional Accounting - Part 1 (lines 199-5198) */
ProfessionalAccounting.prototype.cleanupStrayOverlays = function() {
        // Only run cleanup on page load, not continuously
        // Remove any overlay elements that are not inside visible modals
        const allOverlays = document.querySelectorAll('.accounting-modal-overlay');
        allOverlays.forEach(overlay => {
            const parentModal = overlay.closest('.accounting-modal');
            // Don't remove if modal is being shown (has inline styles or visible class)
            if (!parentModal || (!parentModal.classList.contains('accounting-modal-visible') && !parentModal.classList.contains('show-modal'))) {
                // Only remove if it's been more than 1 second since creation
                const createdTime = parentModal?.getAttribute('data-created');
                if (!createdTime || (Date.now() - parseInt(createdTime)) > 1000) {
                    overlay.remove();
                }
            }
        });
        
        // Remove any modals that don't have the visible class AND don't have inline display style
        const allModals = document.querySelectorAll('.accounting-modal');
        allModals.forEach(modal => {
            const hasVisibleClass = modal.classList.contains('accounting-modal-visible') || modal.classList.contains('show-modal');
            const hasDataAttribute = modal.getAttribute('data-modal-visible') === 'true';
            if (!hasVisibleClass && !hasDataAttribute) {
                // Only remove if it's been more than 1 second since creation
                const createdTime = modal.getAttribute('data-created');
                if (!createdTime || (Date.now() - parseInt(createdTime)) > 1000) {
                    modal.remove();
                }
            }
        });
    }

ProfessionalAccounting.prototype.saveReportsOriginalContent = function() {
        const reportsTab = document.getElementById('financialReportsTab') || document.getElementById('reportsTab');
        if (reportsTab) {
            const moduleContent = reportsTab.querySelector('.module-content');
            if (moduleContent && !this.reportsOriginalContent) {
                this.reportsOriginalContent = moduleContent.innerHTML;
            }
        }
    }

ProfessionalAccounting.prototype.setupEventListeners = function() {
        const self = this;
        
        // Use event delegation specifically for accounting sidebar and tabs
        // Use capture phase to catch events before other handlers
        const normalizeEntityType = (value) => {
            if (!value) return '';
            const lower = value.toLowerCase();
            const map = { agents: 'agent', subagents: 'subagent', workers: 'worker', hr: 'hr' };
            return map[lower] || lower;
        };

        const handleModalFilterChange = async (event) => {
            const target = event.target;
            if (!target) return;
            if (target.id === 'modalEntityTypeFilter') {
                const entitySelect = document.getElementById('modalEntityFilter');
                const entityType = target.value;
                const normalized = normalizeEntityType(entityType);
                
                // Reset entity filter to "All" first
                if (entitySelect) {
                    entitySelect.value = '';
                }
                
                // Immediately load transactions for the selected entity type
                this.transactionsCurrentPage = 1;
                await this.loadModalTransactions();
                
                if (normalized && entitySelect) {
                    entitySelect.innerHTML = '<option value="">All</option>';
                    await this.loadEntitiesForSelect(normalized, entitySelect);
                    if (entitySelect.options.length > 1 && entitySelect.options[0].value !== '') {
                        const allOption = document.createElement('option');
                        allOption.value = '';
                        allOption.textContent = 'All';
                        entitySelect.insertBefore(allOption, entitySelect.firstChild);
                    }
                    // Keep entity filter as "All" to show all entities of this type
                    entitySelect.value = '';
                } else if (entitySelect) {
                    entitySelect.innerHTML = '<option value="">All</option>';
                    entitySelect.value = '';
                }
            } else if (target.id === 'modalEntityFilter') {
                this.transactionsCurrentPage = 1;
                this.loadModalTransactions();
            }
        };
        document.addEventListener('change', handleModalFilterChange);
        document.addEventListener('click', function(e) {
            // Handle top navigation bar (new horizontal navigation)
            const accountingTopNav = e.target.closest('.accounting-top-nav');
            if (accountingTopNav) {
                // Find the top-nav-link: check if target is top-nav-link, inside top-nav-link, or inside top-nav-item
                let navLink = null;
                
                // Method 1: Check if target itself is a top-nav-link
                if (e.target.classList.contains('top-nav-link')) {
                    navLink = e.target;
                }
                // Method 2: Check if target is inside a top-nav-link (ancestor)
                else if (e.target.closest('.top-nav-link')) {
                    navLink = e.target.closest('.top-nav-link');
                }
                // Method 3: Check if target is inside a top-nav-item, find top-nav-link child
                else if (e.target.closest('.top-nav-item')) {
                    const navItem = e.target.closest('.top-nav-item');
                    navLink = navItem.querySelector('.top-nav-link');
                }
                
                if (navLink) {
                    // Allow normal navigation for links that have href but no data-tab/data-toggle
                    // This allows links like accounting-guide.php, migrate-debit-credit.php, etc. to work normally
                    if (navLink.href && !navLink.dataset.tab && !navLink.dataset.toggle) {
                        // Allow normal navigation - don't prevent default
                        return true;
                    }
                    
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (navLink.dataset.toggle) {
                        // Special handling for Reports dropdown - clicking opens the reports modal
                        if (navLink.dataset.toggle === 'reports-dropdown') {
                            // Don't toggle dropdown, just open the reports modal
                            self.openReportsModal();
                            self.switchTab('dashboard');
                            return false;
                        }
                        // For other dropdowns, toggle them
                        const navItem = navLink.closest('.top-nav-item');
                        if (navItem) {
                            navItem.classList.toggle('active');
                        }
                        return false;
                    }
                    
                    if (navLink.dataset.tab) {
                        const tabName = navLink.dataset.tab;
                        
                        // Open modals for these tabs, keep dashboard as regular tab
                        if (tabName === 'dashboard') {
                            self.switchTab('dashboard');
                        } else if (tabName === 'journal-entries' || tabName === 'general-ledger') {
                            self.openGeneralLedgerModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'chart-of-accounts') {
                            self.openChartOfAccountsModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'cost-centers') {
                            // Open cost centers modal with table
                            self.openCostCentersModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'bank-guarantee') {
                            // Open bank guarantee modal with table
                            self.openBankGuaranteeModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'support-payments') {
                            self.openSupportPaymentsModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'expenses') {
                            self.openVouchersModal('expenses');
                            self.switchTab('dashboard');
                        } else if (tabName === 'receipts') {
                            // Open Receipt Vouchers table modal
                            self.openReceiptVouchersModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'disbursement-vouchers' || tabName === 'vouchers' || tabName === 'payment-voucher' || tabName === 'receipt-voucher') {
                            self.openVouchersModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'accounts-payable') {
                            self.openPayablesModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'electronic-invoices' || tabName === 'invoices' || tabName === 'accounts-receivable') {
                            self.openReceivablesModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'entry-approval') {
                            // Open entry approval modal with table
                            self.openEntryApprovalModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'bank-reconciliation' || tabName === 'banking-cash' || tabName === 'banking') {
                            self.loadBankingCashModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'financial-reports' || tabName === 'reports') {
                            self.openReportsModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'settings') {
                            self.openSettingsModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'follow-up') {
                            self.openFollowupModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'messages') {
                            self.openMessagesModal();
                            self.switchTab('dashboard');
                        } else {
                            // Dashboard and other regular tabs
                            self.switchTab(tabName);
                        }
                        return false;
                    }
                }

                let dropdownLink = null;
                if (e.target.classList.contains('dropdown-link')) {
                    dropdownLink = e.target;
                } else if (e.target.closest('.dropdown-link')) {
                    dropdownLink = e.target.closest('.dropdown-link');
                }
                
                if (dropdownLink && dropdownLink.dataset.report) {
                    e.preventDefault();
                    e.stopPropagation();
                    const reportType = dropdownLink.dataset.report;
                    // Close dropdown after selection
                    const navItem = dropdownLink.closest('.top-nav-item');
                    if (navItem) {
                        navItem.classList.remove('active');
                    }
                    document.querySelectorAll('.dropdown-link').forEach(l => l.classList.remove('active'));
                    dropdownLink.classList.add('active');
                    // Generate report - open reports modal first, then generate after a short delay
                    self.openReportsModal();
                    // Wait for modal to open before generating report
                    setTimeout(() => {
                        if (typeof self.generateReport === 'function') {
                            self.generateReport(reportType);
                        }
                    }, 300);
                    return false;
                }
                
                // Close dropdowns when clicking outside (but not if clicking on dropdown links or the Reports link itself)
                if (!e.target.closest('.dropdown-link') && !e.target.closest('.top-nav-item.has-dropdown')) {
                    document.querySelectorAll('.top-nav-item.has-dropdown').forEach(item => {
                        item.classList.remove('active');
                    });
                }
            }

            // Handle sidebar navigation (old vertical navigation - kept for backward compatibility)
            const accountingSidebar = e.target.closest('.accounting-sidebar');
            if (accountingSidebar) {
                // Find the nav-link: check if target is nav-link, inside nav-link, or inside nav-item
                let navLink = null;
                
                // Method 1: Check if target itself is a nav-link
                if (e.target.classList.contains('nav-link')) {
                    navLink = e.target;
                }
                // Method 2: Check if target is inside a nav-link (ancestor)
                else if (e.target.closest('.nav-link')) {
                    navLink = e.target.closest('.nav-link');
                }
                // Method 3: Check if target is inside a nav-item, find nav-link child
                else if (e.target.closest('.nav-item')) {
                    const navItem = e.target.closest('.nav-item');
                    navLink = navItem.querySelector('.nav-link');
                }
                
                if (navLink) {
                    // Allow normal navigation for links that have href but no data-tab/data-toggle
                    // This allows links like accounting-guide.php, migrate-debit-credit.php, etc. to work normally
                    if (navLink.href && !navLink.dataset.tab && !navLink.dataset.toggle) {
                        // Allow normal navigation - don't prevent default
                        return true;
                    }
                    
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (navLink.dataset.toggle) {
                        const navItem = navLink.closest('.nav-item');
                        if (navItem) {
                            navItem.classList.toggle('active');
                        }
                        return false;
                    }
                    
                    if (navLink.dataset.tab) {
                        const tabName = navLink.dataset.tab;
                        
                        // Open modals for these tabs, keep dashboard as regular tab
                        if (tabName === 'dashboard') {
                            self.switchTab('dashboard');
                        } else if (tabName === 'journal-entries' || tabName === 'general-ledger') {
                            self.openGeneralLedgerModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'chart-of-accounts') {
                            self.openChartOfAccountsModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'cost-centers') {
                            // Open cost centers modal with table
                            self.openCostCentersModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'bank-guarantee') {
                            // Open bank guarantee modal with table
                            self.openBankGuaranteeModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'support-payments') {
                            self.openSupportPaymentsModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'expenses') {
                            self.openVouchersModal('expenses');
                            self.switchTab('dashboard');
                        } else if (tabName === 'receipts') {
                            // Open Receipt Vouchers table modal
                            self.openReceiptVouchersModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'disbursement-vouchers' || tabName === 'vouchers' || tabName === 'payment-voucher' || tabName === 'receipt-voucher') {
                            self.openVouchersModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'accounts-payable') {
                            self.openPayablesModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'electronic-invoices' || tabName === 'invoices' || tabName === 'accounts-receivable') {
                            self.openReceivablesModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'entry-approval') {
                            // Open entry approval modal with table
                            self.openEntryApprovalModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'bank-reconciliation' || tabName === 'banking-cash' || tabName === 'banking') {
                            self.loadBankingCashModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'financial-reports' || tabName === 'reports') {
                            self.openReportsModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'settings') {
                            self.openSettingsModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'follow-up') {
                            self.openFollowupModal();
                            self.switchTab('dashboard');
                        } else if (tabName === 'messages') {
                            self.openMessagesModal();
                            self.switchTab('dashboard');
                        } else {
                            // Dashboard and other regular tabs
                            self.switchTab(tabName);
                        }
                        return false;
                    }
                }

                let submenuLink = null;
                if (e.target.classList.contains('submenu-link')) {
                    submenuLink = e.target;
                } else if (e.target.closest('.submenu-link')) {
                    submenuLink = e.target.closest('.submenu-link');
                }
                
                if (submenuLink && submenuLink.dataset.report) {
                    e.preventDefault();
                    e.stopPropagation();
                    const reportType = submenuLink.dataset.report;
                    document.querySelectorAll('.submenu-link').forEach(l => l.classList.remove('active'));
                    submenuLink.classList.add('active');
                    // Generate report - open reports modal first, then generate after a short delay
                    self.openReportsModal();
                    // Wait for modal to open before generating report
                    setTimeout(() => {
                        if (typeof self.generateReport === 'function') {
                            self.generateReport(reportType);
                        }
                    }, 300);
                    return false;
                }
            }

            // Tab button clicks (horizontal tabs) - anywhere in accounting container
            const accountingContainer = e.target.closest('.accounting-container');
            if (accountingContainer) {
                const tabBtn = e.target.closest('.tab-btn');
                if (tabBtn && tabBtn.dataset.tab) {
                    // Check if button is disabled or hidden by permissions
                    if (tabBtn.disabled || tabBtn.classList.contains('hidden') || tabBtn.classList.contains('permission-denied')) {
                        return true; // Allow default behavior if disabled
                    }
                    
                    e.preventDefault();
                    e.stopPropagation();
                    const tabName = tabBtn.dataset.tab;
                    
                    // Open modals for these tabs, keep dashboard as regular tab
                    if (tabName === 'dashboard') {
                        self.switchTab('dashboard');
                    } else if (tabName === 'journal-entries' || tabName === 'general-ledger') {
                        self.openGeneralLedgerModal();
                        // Keep dashboard visible
                        self.switchTab('dashboard');
                    } else if (tabName === 'chart-of-accounts') {
                        // Open chart of accounts as modal
                        self.openChartOfAccountsModal();
                        self.switchTab('dashboard');
                    } else if (tabName === 'cost-centers') {
                        // Open cost centers modal
                        self.openCostCentersModal();
                        self.switchTab('dashboard');
                    } else if (tabName === 'bank-guarantee') {
                        // Open bank guarantee modal
                        self.openBankGuaranteeModal();
                        self.switchTab('dashboard');
                    } else if (tabName === 'support-payments') {
                        self.openSupportPaymentsModal();
                        self.switchTab('dashboard');
                    } else if (tabName === 'expenses') {
                        self.openVouchersModal('expenses');
                        self.switchTab('dashboard');
                    } else if (tabName === 'receipts') {
                        // Open Receipt Vouchers table modal
                        self.openReceiptVouchersModal();
                        self.switchTab('dashboard');
                    } else if (tabName === 'disbursement-vouchers' || tabName === 'vouchers') {
                        self.openVouchersModal();
                        self.switchTab('dashboard');
                    } else if (tabName === 'accounts-payable') {
                        self.openPayablesModal();
                        self.switchTab('dashboard');
                    } else if (tabName === 'electronic-invoices' || tabName === 'invoices' || tabName === 'accounts-receivable') {
                        self.openReceivablesModal();
                        self.switchTab('dashboard');
                    } else if (tabName === 'entry-approval') {
                        // Open entry approval modal
                        self.openEntryApprovalModal();
                        self.switchTab('dashboard');
                    } else if (tabName === 'bank-reconciliation' || tabName === 'banking-cash' || tabName === 'banking') {
                        // Open Banking & Cash as modal
                        self.loadBankingCashModal();
                        self.switchTab('dashboard');
                    } else if (tabName === 'financial-reports' || tabName === 'reports') {
                        self.openReportsModal();
                        self.switchTab('dashboard');
                    } else if (tabName === 'settings') {
                        self.openSettingsModal();
                        self.switchTab('dashboard');
                    } else {
                        // Dashboard stays as regular tab
                        self.switchTab(tabName);
                    }
                    return false;
                }
            }
            
            // Also handle tab button clicks directly (not just within accounting container)
            // This ensures clicks are caught even if event delegation fails
            const directTabBtn = e.target.closest('.tab-btn');
            if (directTabBtn && directTabBtn.dataset.tab) {
                // Check if button is disabled or hidden by permissions
                if (directTabBtn.disabled || directTabBtn.classList.contains('hidden') || directTabBtn.classList.contains('permission-denied')) {
                    return true;
                }
                
                e.preventDefault();
                e.stopPropagation();
                const tabName = directTabBtn.dataset.tab;
                
                // Open modals for these tabs, keep dashboard as regular tab
                if (tabName === 'dashboard') {
                    self.switchTab('dashboard');
                } else if (tabName === 'journal-entries' || tabName === 'general-ledger') {
                    self.openGeneralLedgerModal();
                    self.switchTab('dashboard');
                } else if (tabName === 'chart-of-accounts') {
                    self.openChartOfAccountsModal();
                    self.switchTab('dashboard');
                } else if (tabName === 'cost-centers') {
                    // Open cost centers modal
                    self.openCostCentersModal();
                    self.switchTab('dashboard');
                } else if (tabName === 'bank-guarantee') {
                    // Open bank guarantee modal
                    self.openBankGuaranteeModal();
                    self.switchTab('dashboard');
                } else if (tabName === 'support-payments') {
                    self.openSupportPaymentsModal();
                    self.switchTab('dashboard');
                } else if (tabName === 'expenses') {
                    self.openVouchersModal('expenses');
                    self.switchTab('dashboard');
                } else if (tabName === 'receipts') {
                    // Open Receipt Vouchers table modal
                    self.openReceiptVouchersModal();
                    self.switchTab('dashboard');
                } else if (tabName === 'disbursement-vouchers' || tabName === 'vouchers') {
                    self.openVouchersModal();
                    self.switchTab('dashboard');
} else if (tabName === 'accounts-payable') {
                        self.openPayablesModal();
                        self.switchTab('dashboard');
                    } else if (tabName === 'electronic-invoices' || tabName === 'invoices' || tabName === 'accounts-receivable') {
                        self.openReceivablesModal();
                        self.switchTab('dashboard');
                    } else if (tabName === 'entry-approval') {
                    // Open entry approval modal
                    self.openEntryApprovalModal();
                    self.switchTab('dashboard');
                } else if (tabName === 'bank-reconciliation' || tabName === 'banking-cash' || tabName === 'banking') {
                    self.loadBankingCashModal();
                    self.switchTab('dashboard');
                } else if (tabName === 'financial-reports' || tabName === 'reports') {
                    self.openReportsModal();
                    self.switchTab('dashboard');
                } else if (tabName === 'settings') {
                    self.openSettingsModal();
                    self.switchTab('dashboard');
                } else if (tabName === 'follow-up') {
                    self.openFollowupModal();
                    self.switchTab('dashboard');
                } else if (tabName === 'messages') {
                    self.openMessagesModal();
                    self.switchTab('dashboard');
                } else {
                    self.switchTab(tabName);
                }
                return false;
            }
        }, true); // Use capture phase

        // Action buttons - handle clicks anywhere in the accounting container
        document.addEventListener('click', (e) => {
            // Handle page number clicks first (before other actions)
            const pageBtn = e.target.closest('.page-btn[data-page]');
            if (pageBtn) {
                const pageNum = parseInt(pageBtn.getAttribute('data-page'));
                if (!isNaN(pageNum) && pageNum >= 1 && pageNum <= this.transactionsTotalPages) {
                    this.transactionsCurrentPage = pageNum;
                    this.loadModalTransactions();
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
            }
            
            // Handle actions within accounting container OR any accounting modal
            const accountingContainer = e.target.closest('.accounting-container');
            const transactionsModal = e.target.closest('#transactionsModal');
            const accountingModal = e.target.closest('#accountingModal');
            const generalLedgerModal = e.target.closest('#generalLedgerModal');
            const chartOfAccountsModal = e.target.closest('#chartOfAccountsModal');
            const bankingCashModal = e.target.closest('#bankingCashModal');
            const vouchersModal = e.target.closest('#vouchersModal');
            const supportPaymentsModal = e.target.closest('#supportPaymentsModal');
            const followupModal = e.target.closest('#followupModal');
            const editFollowupModal = e.target.closest('#editFollowupModal');
            const messagesModal = e.target.closest('#messagesModal');
            const anyAccountingModal = e.target.closest('.accounting-modal');
            
            // Only process if click is within accounting container or any accounting modal
            if (!accountingContainer && !transactionsModal && !accountingModal && !generalLedgerModal && !chartOfAccountsModal && !bankingCashModal && !vouchersModal && !supportPaymentsModal && !followupModal && !editFollowupModal && !messagesModal && !anyAccountingModal) return;

            const action = e.target.closest('[data-action]')?.getAttribute('data-action');
            if (!action) return;

            switch(action) {
                case 'refresh-dashboard':
                    this.refreshAllModules();
                    this.loadDashboard();
                    this.loadFinancialOverview();
                    break;
                case 'quick-entry':
                    this.openQuickEntry();
                    break;
                case 'open-receivables-modal':
                    this.openInvoiceModal();
                    break;
                case 'open-payment-voucher':
                    this.openPaymentVoucherModal();
                    break;
                case 'open-receipt-voucher':
                    this.openReceiptVoucherModal();
                    break;
                case 'open-reports-modal':
                    this.openReportsModal();
                    break;
                case 'open-settings-modal':
                    this.openSettingsModal();
                    break;
                case 'new-journal-entry':
                    // Use professional.js modal system for consistency
                    this.openJournalEntryModal(null);
                    break;
                case 'new-invoice':
                    this.openInvoiceModal();
                    break;
                case 'new-bill':
                    this.openBillModal();
                    break;
                case 'receive-payment':
                    this.openReceivePaymentModal();
                    break;
                case 'make-payment':
                    this.openMakePaymentModal();
                    break;
                case 'apply-ledger-filters':
                    this.loadJournalEntries();
                    break;
                case 'apply-modal-ledger-filters':
                    // Save filter values
                    const dateFromEl = document.getElementById('modalLedgerDateFrom');
                    const dateToEl = document.getElementById('modalLedgerDateTo');
                    const accountEl = document.getElementById('modalLedgerAccount');
                    const searchEl = document.getElementById('modalLedgerSearch');
                    
                    if (dateFromEl) this.modalLedgerDateFrom = dateFromEl.value || '';
                    if (dateToEl) this.modalLedgerDateTo = dateToEl.value || '';
                    if (accountEl) this.modalLedgerAccountId = accountEl.value || '';
                    if (searchEl) this.modalLedgerSearch = searchEl.value || '';
                    
                    this.modalLedgerCurrentPage = 1;
                    this.loadModalJournalEntries();
                    break;
                case 'clear-ledger-filters':
                case 'clear-ledger-filters-expand':
                    this.clearLedgerFilters(action === 'clear-ledger-filters-expand');
                    break;
                case 'remove-budget-line-item':
                    const lineItem = e.target.closest('.budget-line-item');
                    if (lineItem) lineItem.remove();
                    break;
                case 'modal-ledger-prev':
                    if (this.modalLedgerCurrentPage > 1) {
                        this.modalLedgerCurrentPage--;
                        this.loadModalJournalEntries();
                    }
                    break;
                case 'modal-ledger-next':
                    if (this.modalLedgerCurrentPage < this.modalLedgerTotalPages) {
                        this.modalLedgerCurrentPage++;
                        this.loadModalJournalEntries();
                    }
                    break;
                case 'modal-ledger-page':
                    const ledgerPageNum = parseInt(e.target.getAttribute('data-page'));
                    if (!isNaN(ledgerPageNum) && ledgerPageNum >= 1 && ledgerPageNum <= this.modalLedgerTotalPages) {
                        this.modalLedgerCurrentPage = ledgerPageNum;
                        this.loadModalJournalEntries();
                    }
                    break;
                case 'bulk-select-all-ledger':
                    const ledgerSelectAll = e.target.checked;
                    document.querySelectorAll('#modalJournalEntriesTable tbody input[type="checkbox"]').forEach(cb => {
                        cb.checked = ledgerSelectAll;
                    });
                    this.updateBulkActions('ledger');
                    break;
                case 'bulk-delete-ledger':
                    this.bulkDeleteJournalEntries();
                    break;
                case 'bulk-export-ledger':
                    this.bulkExportJournalEntries();
                    break;
                case 'modal-ar-prev':
                    if (this.modalArCurrentPage > 1) {
                        this.modalArCurrentPage--;
                        this.loadModalInvoices();
                    }
                    break;
                case 'modal-ar-next':
                    if (this.modalArCurrentPage < this.modalArTotalPages) {
                        this.modalArCurrentPage++;
                        this.loadModalInvoices();
                    }
                    break;
                case 'modal-ar-page':
                    const arPageNum = parseInt(e.target.getAttribute('data-page'));
                    if (!isNaN(arPageNum) && arPageNum >= 1 && arPageNum <= this.modalArTotalPages) {
                        this.modalArCurrentPage = arPageNum;
                        this.loadModalInvoices();
                    }
                    break;
                case 'modal-ap-prev':
                    if (this.modalApCurrentPage > 1) {
                        this.modalApCurrentPage--;
                        this.loadModalBills();
                    }
                    break;
                case 'modal-ap-next':
                    if (this.modalApCurrentPage < this.modalApTotalPages) {
                        this.modalApCurrentPage++;
                        this.loadModalBills();
                    }
                    break;
                case 'modal-ap-page':
                    const apPageNum = parseInt(e.target.getAttribute('data-page'));
                    if (!isNaN(apPageNum) && apPageNum >= 1 && apPageNum <= this.modalApTotalPages) {
                        this.modalApCurrentPage = apPageNum;
                        this.loadModalBills();
                    }
                    break;
                case 'modal-bank-prev':
                    if (this.modalBankCurrentPage > 1) {
                        this.modalBankCurrentPage--;
                        this.loadBankingCashModal();
                    }
                    break;
                case 'modal-bank-next':
                    if (this.modalBankCurrentPage < this.modalBankTotalPages) {
                        this.modalBankCurrentPage++;
                        this.loadBankingCashModal();
                    }
                    break;
                case 'modal-bank-page':
                    const bankPageNum = parseInt(e.target.getAttribute('data-page'));
                    if (!isNaN(bankPageNum) && bankPageNum >= 1 && bankPageNum <= this.modalBankTotalPages) {
                        this.modalBankCurrentPage = bankPageNum;
                        this.loadBankingCashModal();
                    }
                    break;
                case 'add-cost-center':
                    this.openCostCenterForm();
                    break;
                case 'edit-cost-center':
                    const costCenterId = parseInt(e.target.closest('[data-id]')?.getAttribute('data-id') || e.target.closest('button')?.getAttribute('data-id') || 0);
                    if (costCenterId) this.openCostCenterForm(costCenterId);
                    break;
                case 'delete-cost-center':
                    const deleteCostCenterId = parseInt(e.target.closest('button')?.getAttribute('data-id') || 0);
                    if (deleteCostCenterId) this.deleteCostCenter(deleteCostCenterId);
                    break;
                case 'delete-selected-cost-centers':
                    this.deleteSelectedCostCenters();
                    break;
                case 'add-bank-guarantee':
                    this.openBankGuaranteeForm();
                    break;
                case 'edit-bank-guarantee':
                    const bankGuaranteeId = parseInt(e.target.closest('[data-id]')?.getAttribute('data-id') || e.target.closest('button')?.getAttribute('data-id') || 0);
                    if (bankGuaranteeId) this.openBankGuaranteeForm(bankGuaranteeId);
                    break;
                case 'delete-bank-guarantee':
                    const deleteBankGuaranteeId = parseInt(e.target.closest('button')?.getAttribute('data-id') || 0);
                    if (deleteBankGuaranteeId) this.deleteBankGuarantee(deleteBankGuaranteeId);
                    break;
                case 'delete-selected-bank-guarantees':
                    this.deleteSelectedBankGuarantees();
                    break;
                case 'approve-selected':
                    this.approveSelectedEntries();
                    break;
                case 'reject-selected':
                    this.rejectSelectedEntries();
                    break;
                case 'approve-entry':
                    const approveEntryId = parseInt(e.target.closest('button')?.getAttribute('data-id') || 0);
                    if (approveEntryId) {
                        this.showConfirmDialog(
                            'Approve Entry',
                            'Are you sure you want to approve this entry?',
                            'Approve',
                            'Cancel',
                            'success'
                        ).then(confirmed => {
                            if (confirmed) {
                                this.approveEntries([approveEntryId]);
                            }
                        });
                    }
                    break;
                case 'reject-entry':
                    const rejectEntryId = parseInt(e.target.closest('button')?.getAttribute('data-id') || 0);
                    if (rejectEntryId) {
                        this.showConfirmDialog(
                            'Reject Entry',
                            'Are you sure you want to reject this entry?',
                            'Continue',
                            'Cancel',
                            'warning'
                        ).then(confirmed => {
                            if (confirmed) {
                                this.showPrompt(
                                    'Rejection Reason',
                                    'Please enter the reason for rejecting this entry:',
                                    '',
                                    'Enter rejection reason...',
                                    'text'
                                ).then(reason => {
                                    if (reason && reason.trim()) {
                                        this.rejectEntries([rejectEntryId], reason.trim());
                                    } else if (reason !== null) {
                                        this.showToast('Rejection reason is required', 'error');
                                    }
                                });
                            }
                        });
                    }
                    break;
                case 'modal-entity-prev':
                case 'bulk-select-all-ar':
                    const arSelectAll = e.target.checked;
                    document.querySelectorAll('#modalInvoicesTable tbody input[type="checkbox"]').forEach(cb => {
                        cb.checked = arSelectAll;
                    });
                    this.updateBulkActions('ar');
                    break;
                case 'bulk-select-all-ap':
                    const apSelectAll = e.target.checked;
                    document.querySelectorAll('#modalBillsTable tbody input[type="checkbox"]').forEach(cb => {
                        cb.checked = apSelectAll;
                    });
                    this.updateBulkActions('ap');
                    break;
                case 'bulk-select-all-bank':
                    const bankSelectAll = e.target.checked;
                    const bankTable = document.getElementById('modalBankingCashTable') || document.getElementById('modalBankAccountsTable');
                    if (bankTable) {
                        bankTable.querySelectorAll('tbody input[type="checkbox"]').forEach(cb => {
                        cb.checked = bankSelectAll;
                    });
                    }
                    this.updateBulkActions('bank');
                    break;
                case 'bulk-delete-ar':
                    this.bulkDeleteInvoices();
                    break;
                case 'bulk-delete-ap':
                    this.bulkDeleteBills();
                    break;
                case 'bulk-delete-bank':
                    this.bulkDeleteBankAccounts();
                    break;
                case 'bulk-delete-entity':
                case 'bulk-delete-entities':
                    const selectedIds = Array.from(this.entityTransactionsSelected).map(id => parseInt(id));
                    if (selectedIds.length === 0) {
                        this.showToast('Please select at least one transaction', 'warning');
                        break;
                    }
                    (async () => {
                        const deleteConfirmed = await this.showConfirmDialog(
                            'Delete Transactions',
                            `Are you sure you want to delete ${selectedIds.length} transaction(s)?`,
                            'Delete',
                            'Cancel',
                            'danger'
                        );
                        if (deleteConfirmed) {
                        this.bulkDeleteEntityTransactions(selectedIds);
                    }
                    })();
                    break;
                case 'bulk-export-entity':
                case 'bulk-export-entities':
                    const exportIds = Array.from(this.entityTransactionsSelected).map(id => parseInt(id));
                    if (exportIds.length === 0) {
                        this.showToast('Please select at least one transaction', 'warning');
                        break;
                    }
                    this.bulkExportEntityTransactions(exportIds);
                    break;
                case 'bulk-export-ar':
                    this.bulkExportInvoices();
                    break;
                case 'bulk-export-ap':
                    this.bulkExportBills();
                    break;
                case 'bulk-export-bank':
                    this.bulkExportBankAccounts();
                    break;
                case 'generate-report':
                    const reportType = e.target.closest('[data-report]')?.getAttribute('data-report');
                    if (reportType) {
                        // Switch to reports tab first if not already there
                        if (this.currentTab !== 'reports') {
                            this.switchTab('financial-reports');
                            setTimeout(() => {
                                this.generateReport(reportType);
                            }, 150);
                        } else {
                            this.generateReport(reportType);
                        }
                    }
                    break;
                case 'view-all-transactions':
                case 'open-transactions-modal':
                    this.openTransactionsModal();
                    break;
                case 'new-entity-transaction':
                    this.openEntityTransactionModal();
                    break;
                case 'apply-entity-filters':
                    // Filters removed - table deleted
                    break;
                case 'clear-entity-filters':
                    // Filters removed - table deleted
                    break;
                case 'manage-chart-of-accounts':
                    this.openChartOfAccountsModal();
                    break;
                case 'coa-prev':
                    if (this.coaCurrentPage > 1) {
                        this.coaCurrentPage--;
                        this.loadChartOfAccounts().then(() => {
                            setTimeout(() => this.scrollToCoaTable(), 300);
                        });
                    }
                    break;
                case 'coa-next':
                    if (this.coaCurrentPage < this.coaTotalPages) {
                        this.coaCurrentPage++;
                        this.loadChartOfAccounts().then(() => {
                            setTimeout(() => this.scrollToCoaTable(), 300);
                            });
                        }
                    break;
                case 'coa-page':
                    const page = parseInt(e.target.dataset.page);
                    if (page && page >= 1 && page <= this.coaTotalPages) {
                        this.coaCurrentPage = page;
                        this.loadChartOfAccounts().then(() => {
                            setTimeout(() => this.scrollToCoaTable(), 300);
                        });
                    }
                    break;
                case 'coa-select-all':
                    const selectAll = document.getElementById('coaSelectAll');
                    if (selectAll) {
                        const isChecked = selectAll.checked;
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
                    }
                    break;
                case 'bulk-delete-coa':
                    if (this.coaSelectedAccounts.size === 0) {
                        this.showToast('Please select accounts to delete', 'error');
                        return;
                    }
                    (async () => {
                        const deleteAccountsConfirmed = await this.showConfirmDialog(
                            'Delete Accounts',
                            `Are you sure you want to delete ${this.coaSelectedAccounts.size} account(s)?`,
                            'Delete',
                            'Cancel',
                            'danger'
                        );
                        if (deleteAccountsConfirmed) {
                            this.bulkDeleteAccounts(Array.from(this.coaSelectedAccounts));
                        }
                    })();
                    break;
                case 'bulk-export-coa':
                    if (this.coaSelectedAccounts.size === 0) {
                        this.showToast('Please select accounts to export', 'error');
                        return;
                    }
                    this.bulkExportAccounts(Array.from(this.coaSelectedAccounts));
                    break;
                case 'bulk-activate-coa':
                    if (this.coaSelectedAccounts.size === 0) {
                        this.showToast('Please select accounts to activate', 'error');
                        return;
                    }
                    this.bulkUpdateAccountsStatus(Array.from(this.coaSelectedAccounts), true);
                    break;
                case 'bulk-deactivate-coa':
                    if (this.coaSelectedAccounts.size === 0) {
                        this.showToast('Please select accounts to deactivate', 'error');
                        return;
                    }
                    this.bulkUpdateAccountsStatus(Array.from(this.coaSelectedAccounts), false);
                    break;
                case 'new-account':
                    this.openAccountModal();
                    break;
                case 'edit-account':
                    const accountId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (accountId) {
                        this.openAccountModal(accountId);
                    }
                    break;
                case 'delete-account':
                    const deleteAccountId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (deleteAccountId) {
                        (async () => {
                            const confirmed = await this.showConfirmDialog(
                                'Delete Account',
                                'Are you sure you want to delete this account?',
                                'Delete',
                                'Cancel',
                                'danger'
                            );
                            if (confirmed) {
                        this.deleteAccount(deleteAccountId);
                            }
                        })();
                    }
                    break;
                case 'export-accounts':
                    this.exportAccounts();
                    break;
                case 'new-payment-voucher':
                    this.openPaymentVoucherModal();
                    break;
                case 'new-receipt-voucher':
                    this.openReceiptVoucherModal();
                    break;
                case 'view-receipt-voucher':
                case 'edit-receipt-voucher': {
                    const receiptIdEl = e.target.closest('[data-action="view-receipt-voucher"], [data-action="edit-receipt-voucher"]');
                    const receiptId = receiptIdEl ? receiptIdEl.getAttribute('data-id') : null;
                    if (receiptId) {
                        this.openReceiptVoucherModal(parseInt(receiptId, 10));
                    }
                    break;
                }
                case 'view-voucher':
                    const voucherId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    const voucherType = e.target.closest('[data-type]')?.getAttribute('data-type');
                    if (voucherId && voucherType) {
                        this.viewVoucher(voucherId, voucherType);
                    }
                    break;
                case 'print-voucher':
                    const printVoucherId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    const printVoucherType = e.target.closest('[data-type]')?.getAttribute('data-type');
                    if (printVoucherId && printVoucherType) {
                        this.printVoucher(printVoucherId, printVoucherType);
                    }
                    break;
                case 'duplicate-voucher':
                    const duplicateVoucherId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    const duplicateVoucherType = e.target.closest('[data-type]')?.getAttribute('data-type');
                    if (duplicateVoucherId && duplicateVoucherType) {
                        this.duplicateVoucher(parseInt(duplicateVoucherId), duplicateVoucherType);
                    }
                    break;
                case 'export-voucher':
                    const exportVoucherId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    const exportVoucherType = e.target.closest('[data-type]')?.getAttribute('data-type');
                    if (exportVoucherId && exportVoucherType) {
                        this.exportSingleVoucher(parseInt(exportVoucherId), exportVoucherType);
                    }
                    break;
                case 'delete-voucher':
                    const deleteVoucherId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    const deleteVoucherType = e.target.closest('[data-type]')?.getAttribute('data-type');
                    if (deleteVoucherId && deleteVoucherType) {
                        (async () => {
                            const confirmed = await this.showConfirmDialog(
                                'Delete Voucher',
                                `Are you sure you want to delete this ${deleteVoucherType} voucher? This action cannot be undone.`,
                                'Delete',
                                'Cancel',
                                'danger'
                            );
                            if (confirmed) {
                                this.deleteVoucher(parseInt(deleteVoucherId), deleteVoucherType);
                            }
                        })();
                    }
                    break;
                case 'new-bank-transaction':
                    this.openBankTransactionModal();
                    break;
                case 'reconcile-bank':
                    this.openBankReconciliationModal();
                    break;
                case 'print-invoice':
                    const printInvoiceId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (printInvoiceId) {
                        this.printInvoice(printInvoiceId);
                    }
                    break;
                case 'print-bill':
                    const printBillId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (printBillId) {
                        this.printBill(printBillId);
                    }
                    break;
                case 'view-bank-transaction':
                    const viewBankTransId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (viewBankTransId) {
                        this.viewBankTransaction(viewBankTransId);
                    }
                    break;
                case 'delete-bank-transaction':
                    const deleteBankTransId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (deleteBankTransId) {
                        this.deleteBankTransaction(deleteBankTransId);
                    }
                    break;
                case 'manage-customers':
                    this.openCustomersModal();
                    break;
                case 'manage-vendors':
                    this.openVendorsModal();
                    break;
                case 'manage-periods':
                    this.openPeriodsModal();
                    break;
                case 'manage-tax':
                    this.openTaxSettingsModal();
                    break;
                case 'new-customer':
                    this.openCustomerForm();
                    break;
                case 'edit-customer':
                    const customerId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (customerId) this.openCustomerForm(customerId);
                    break;
                case 'delete-customer':
                    const delCustomerId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (delCustomerId) {
                        (async () => {
                            const confirmed = await this.showConfirmDialog(
                                'Delete Customer',
                                'Are you sure you want to delete this customer?',
                                'Delete',
                                'Cancel',
                                'danger'
                            );
                            if (confirmed) {
                        this.deleteCustomer(delCustomerId);
                            }
                        })();
                    }
                    break;
                case 'new-vendor':
                    this.openVendorForm();
                    break;
                case 'edit-vendor':
                    const vendorId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (vendorId) this.openVendorForm(vendorId);
                    break;
                case 'delete-vendor':
                    const delVendorId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (delVendorId) {
                        (async () => {
                            const confirmed = await this.showConfirmDialog(
                                'Delete Vendor',
                                'Are you sure you want to delete this vendor?',
                                'Delete',
                                'Cancel',
                                'danger'
                            );
                            if (confirmed) {
                        this.deleteVendor(delVendorId);
                            }
                        })();
                    }
                    break;
                case 'receive-payment':
                    this.openReceivePaymentModal();
                    break;
                case 'make-payment':
                    this.openMakePaymentModal();
                    break;
                case 'new-period':
                    this.openPeriodForm();
                    break;
                case 'new-tax-setting':
                    this.openTaxSettingForm();
                    break;
                case 'manage-budgets':
                case 'new-budget':
                    this.openBudgetForm();
                    break;
                case 'edit-budget':
                    const budgetId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (budgetId) this.openBudgetForm(budgetId);
                    break;
                case 'view-budget':
                    const viewBudgetId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (viewBudgetId) this.openBudgetForm(viewBudgetId);
                    break;
                case 'delete-budget':
                    const delBudgetId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (delBudgetId) this.deleteBudget(delBudgetId);
                    break;
                case 'manage-financial-closings':
                case 'new-financial-closing':
                    this.openFinancialClosingForm();
                    break;
                case 'view-financial-closing':
                    const viewClosingId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (viewClosingId) this.openFinancialClosingForm(viewClosingId);
                    break;
                case 'complete-financial-closing':
                    const completeClosingId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (completeClosingId) this.completeFinancialClosing(completeClosingId);
                    break;
                case 'delete-financial-closing':
                    const delClosingId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (delClosingId) this.deleteFinancialClosing(delClosingId);
                    break;
                case 'manage-payment-allocations':
                case 'new-payment-allocation':
                    this.openPaymentAllocationForm();
                    break;
                case 'delete-payment-allocation':
                    const delAllocationId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (delAllocationId) this.deletePaymentAllocation(delAllocationId);
                    break;
                case 'save-settings':
                    this.saveSettings();
                    break;
                case 'reset-settings':
                    this.resetSettings();
                    break;
                case 'export-settings':
                    this.exportSettings();
                    break;
                case 'new-bank-account':
                    // Check if function exists directly
                    if (typeof this.openBankAccountForm === 'function') {
                        this.openBankAccountForm();
                    } else if (typeof ProfessionalAccounting !== 'undefined' && typeof ProfessionalAccounting.prototype.openBankAccountForm === 'function') {
                        // Try prototype method
                        ProfessionalAccounting.prototype.openBankAccountForm.call(this);
                    } else {
                        console.error('openBankAccountForm function not found');
                        this.showToast('Bank account form function not available. Please refresh the page.', 'error');
                    }
                    break;
                case 'reconcile-account':
                    this.openBankReconciliationModal();
                    break;
                case 'export-ledger':
                    this.exportJournalEntries();
                    break;
                case 'export-accounts':
                    this.exportAccounts();
                    break;
                case 'export-invoices':
                    this.exportInvoices();
                    break;
                case 'export-bills':
                    this.exportBills();
                    break;
                case 'export-vouchers':
                    this.exportVouchers();
                    break;
                case 'export-bank-transactions':
                    this.exportBankTransactions();
                    break;
                case 'view-entry':
                    const viewEntryId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    const entrySource = e.target.closest('[data-source]')?.getAttribute('data-source') || 'journal';
                    // Check if this is from Entry Approval modal
                    const entryApprovalModal = e.target.closest('#entryApprovalModal');
                    if (entryApprovalModal && viewEntryId) {
                        this.openEntryDetailsModal(parseInt(viewEntryId));
                    } else if (viewEntryId) {
                        if (entrySource === 'transaction') {
                            this.viewEntityTransaction(viewEntryId);
                        } else {
                            this.viewJournalEntry(viewEntryId);
                        }
                    }
                    break;
                case 'print-entry': {
                    const printBtn = e.target.closest('[data-action="print-entry"]');
                    const printId = printBtn?.getAttribute('data-id') || e.target.closest('[data-id]')?.getAttribute('data-id');
                    const printSource = printBtn?.getAttribute('data-source') || e.target.closest('[data-source]')?.getAttribute('data-source') || 'journal';
                    if (printId) {
                        if (printSource === 'transaction') {
                            this.printTransaction(parseInt(printId));
                        } else {
                            this.printJournalEntry(parseInt(printId));
                        }
                    }
                    break;
                }
                case 'edit-entry-approval':
                    const editApprovalEntryId = parseInt(e.target.closest('button')?.getAttribute('data-id') || 0);
                    if (editApprovalEntryId) this.openEntryApprovalForm(editApprovalEntryId);
                    break;
                case 'delete-entry-approval':
                    const deleteApprovalEntryId = parseInt(e.target.closest('button')?.getAttribute('data-id') || 0);
                    if (deleteApprovalEntryId) this.deleteEntryApproval(deleteApprovalEntryId);
                    break;
                case 'edit-entry':
                    const editEntryId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    // Always use professional.js modal system for consistency
                    if (editEntryId) {
                    this.openJournalEntryModal(editEntryId);
                    }
                    break;
                case 'delete-entry':
                    const deleteBtn = e.target.closest('[data-action="delete-entry"]');
                    const deleteEntryId = deleteBtn?.getAttribute('data-id') || e.target.closest('[data-id]')?.getAttribute('data-id');
                    const deleteSource = deleteBtn?.getAttribute('data-source') || e.target.closest('[data-source]')?.getAttribute('data-source') || 'journal';
                    if (deleteEntryId) {
                        if (deleteSource === 'transaction') {
                            this.deleteEntityTransaction(deleteEntryId);
                        } else {
                            this.deleteJournalEntry(parseInt(deleteEntryId));
                        }
                    }
                    break;
                case 'view-invoice':
                    const invoiceId = e.target.closest('[data-id]')?.getAttribute('data-id');
                        this.openInvoiceModal(invoiceId);
                    break;
                case 'edit-invoice':
                    const editInvoiceId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    this.openInvoiceModal(editInvoiceId);
                    break;
                case 'delete-invoice':
                    const deleteInvoiceId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (deleteInvoiceId) {
                        this.deleteInvoice(parseInt(deleteInvoiceId));
                    }
                    break;
                case 'view-bill':
                    const billId = e.target.closest('[data-id]')?.getAttribute('data-id');
                        this.openBillModal(billId);
                    break;
                case 'edit-bill':
                    const editBillId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    this.openBillModal(editBillId);
                    break;
                case 'delete-bill':
                    const deleteBillId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (deleteBillId) {
                        this.deleteBill(parseInt(deleteBillId));
                    }
                    break;
                    this.openBillModal(editBillId);
                    break;
                case 'edit-entity-transaction':
                    const entityTransId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (entityTransId) {
                        this.openEntityTransactionModal(parseInt(entityTransId));
                    } else {
                        this.openEntityTransactionModal();
                    }
                    break;
                case 'open-transactions-modal':
                    this.openTransactionsModal();
                    break;
                case 'close-transactions-modal':
                    this.closeTransactionsModal();
                    break;
                case 'apply-modal-filters':
                    this.transactionsCurrentPage = 1;
                    this.loadModalTransactions();
                    break;
                case 'view-entity-transaction':
                    const viewTransId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (viewTransId) {
                        this.viewEntityTransaction(viewTransId);
                    }
                    break;
                case 'duplicate-entity-transaction':
                    const dupTransId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (dupTransId) {
                        this.duplicateEntityTransaction(dupTransId);
                    }
                    break;
                case 'void-entity-transaction':
                    const voidTransId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (voidTransId) {
                        this.voidEntityTransaction(voidTransId);
                    }
                    break;
                case 'print-transaction':
                    const printTransId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (printTransId) {
                        this.printTransaction(printTransId);
                    }
                    break;
                case 'first-page':
                    this.transactionsCurrentPage = 1;
                    this.loadModalTransactions();
                    break;
                case 'prev-page':
                    if (this.transactionsCurrentPage > 1) {
                        this.transactionsCurrentPage--;
                        this.loadModalTransactions();
                    }
                    break;
                case 'next-page':
                    if (this.transactionsCurrentPage < this.transactionsTotalPages && this.transactionsTotalPages > 0) {
                        this.transactionsCurrentPage++;
                        this.loadModalTransactions();
                    }
                    break;
                case 'last-page':
                    if (this.transactionsTotalPages > 0) {
                        this.transactionsCurrentPage = this.transactionsTotalPages;
                        this.loadModalTransactions();
                    }
                    break;
                case 'refresh-modal-transactions':
                    this.transactionsCurrentPage = 1;
                    this.loadModalTransactions();
                    break;
                case 'close-followup-modal':
                    this.closeFollowupModal();
                    break;
                case 'refresh-followups':
                    this.loadFollowups();
                    break;
                case 'new-followup':
                    this.showNewFollowupForm();
                    break;
                case 'close-new-followup-modal':
                    this.closeNewFollowupForm();
                    break;
                case 'new-message':
                    this.showNewMessageForm();
                    break;
                case 'close-new-message-modal':
                    this.closeNewMessageForm();
                    break;
                case 'close-edit-followup-modal':
                    this.closeEditFollowupForm();
                    break;
                case 'edit-voucher':
                    const editVoucherId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    const editVoucherType = e.target.closest('[data-type]')?.getAttribute('data-type');
                    if (editVoucherId && editVoucherType) {
                        if (editVoucherType === 'receipt') {
                            this.openReceiptVoucherModal(parseInt(editVoucherId));
                        } else if (editVoucherType === 'payment') {
                            this.openPaymentVoucherModal(parseInt(editVoucherId));
                        }
                    }
                    break;
                case 'complete-followup':
                    const completeFollowupId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (completeFollowupId) {
                        this.completeFollowup(completeFollowupId);
                    }
                    break;
                case 'edit-followup':
                    const editFollowupId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (editFollowupId) {
                        this.editFollowup(editFollowupId);
                    }
                    break;
                case 'view-followup':
                    const viewFollowupId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (viewFollowupId) {
                        this.viewFollowup(viewFollowupId);
                    }
                    break;
                case 'print-followup':
                    const printFollowupId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (printFollowupId) {
                        this.printFollowup(printFollowupId);
                    }
                    break;
                case 'duplicate-followup':
                    const duplicateFollowupId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (duplicateFollowupId) {
                        this.duplicateFollowup(duplicateFollowupId);
                    }
                    break;
                case 'export-followup':
                    const exportFollowupId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (exportFollowupId) {
                        this.exportFollowup(exportFollowupId);
                    }
                    break;
                case 'delete-followup':
                    const deleteFollowupBtn = e.target.closest('[data-action="delete-followup"]');
                    const deleteFollowupId = deleteFollowupBtn?.getAttribute('data-id');
                    if (deleteFollowupId) {
                        e.preventDefault();
                        e.stopPropagation();
                            this.deleteFollowup(deleteFollowupId);
                    }
                    break;
                case 'close-messages-modal':
                    this.closeMessagesModal();
                    break;
                case 'refresh-messages':
                    this.loadMessages();
                    break;
                case 'mark-message-read':
                    const markReadId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (markReadId) {
                        this.markMessageRead(markReadId);
                    }
                    break;
                case 'mark-all-read':
                    this.markAllMessagesRead();
                    break;
                case 'view-message':
                    const viewMessageId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (viewMessageId) {
                        this.viewMessage(viewMessageId);
                    }
                    break;
                case 'print-message':
                    const printMessageId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (printMessageId) {
                        this.printMessage(printMessageId);
                    }
                    break;
                case 'duplicate-message':
                    const duplicateMessageId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (duplicateMessageId) {
                        this.duplicateMessage(duplicateMessageId);
                    }
                    break;
                case 'export-message':
                    const exportMessageId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (exportMessageId) {
                        this.exportMessage(exportMessageId);
                    }
                    break;
                case 'delete-message':
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    const deleteMessageBtn = e.target.closest('[data-action="delete-message"]');
                    if (!deleteMessageBtn) {
                        // Try alternative approach - find by parent
                        const btn = e.target.closest('button[data-action="delete-message"]') || 
                                   e.target.closest('.btn-danger[data-action="delete-message"]');
                        if (btn) {
                            const id = btn.getAttribute('data-id');
                            if (id) {
                                this.deleteMessage(id);
                            }
                        }
                        return;
                    }
                    const deleteMessageId = deleteMessageBtn.getAttribute('data-id');
                    if (deleteMessageId) {
                            this.deleteMessage(deleteMessageId);
                    } else {
                        this.showToast('Error: Could not find message ID', 'error');
                    }
                    break;
                case 'setup-followup-messages':
                    this.setupFollowupMessages();
                    break;
                case 'bulk-select-all-followups':
                    const followupSelectAll = e.target.checked;
                    document.querySelectorAll('.followup-checkbox').forEach(cb => {
                        cb.checked = followupSelectAll;
                    });
                    this.updateBulkActionsFollowups();
                    break;
                case 'select-followup':
                    this.updateBulkActionsFollowups();
                    break;
                case 'bulk-delete-followups':
                    this.bulkDeleteFollowups();
                    break;
                case 'bulk-export-followups':
                    this.bulkExportFollowups();
                    break;
                case 'bulk-print-followups':
                    this.bulkPrintFollowups();
                    break;
                case 'bulk-select-all-messages':
                    const messageSelectAll = e.target.checked;
                    document.querySelectorAll('.message-checkbox').forEach(cb => {
                        cb.checked = messageSelectAll;
                    });
                    this.updateBulkActionsMessages();
                    break;
                case 'select-message':
                    this.updateBulkActionsMessages();
                    break;
                case 'bulk-delete-messages':
                    this.bulkDeleteMessages();
                    break;
                case 'bulk-export-messages':
                    this.bulkExportMessages();
                    break;
                case 'bulk-print-messages':
                    this.bulkPrintMessages();
                    break;
                case 'followup-first-page':
                    this.followupCurrentPage = 1;
                    this.loadFollowups();
                    break;
                case 'followup-prev-page':
                    if (this.followupCurrentPage > 1) {
                        this.followupCurrentPage--;
                        this.loadFollowups();
                    }
                    break;
                case 'followup-next-page':
                    if (this.followupCurrentPage < this.followupTotalPages) {
                        this.followupCurrentPage++;
                        this.loadFollowups();
                    }
                    break;
                case 'followup-last-page':
                    this.followupCurrentPage = this.followupTotalPages;
                    this.loadFollowups();
                    break;
                case 'message-first-page':
                    this.messageCurrentPage = 1;
                    this.loadMessages();
                    break;
                case 'message-prev-page':
                    if (this.messageCurrentPage > 1) {
                        this.messageCurrentPage--;
                        this.loadMessages();
                    }
                    break;
                case 'message-next-page':
                    if (this.messageCurrentPage < this.messageTotalPages) {
                        this.messageCurrentPage++;
                        this.loadMessages();
                    }
                    break;
                case 'message-last-page':
                    this.messageCurrentPage = this.messageTotalPages;
                    this.loadMessages();
                    break;
                case 'followup-page':
                    const followupPage = parseInt(e.target.getAttribute('data-page'));
                    if (followupPage && followupPage >= 1 && followupPage <= this.followupTotalPages) {
                        this.followupCurrentPage = followupPage;
                        this.loadFollowups();
                    }
                    break;
                case 'message-page':
                    const messagePage = parseInt(e.target.getAttribute('data-page'));
                    if (messagePage && messagePage >= 1 && messagePage <= this.messageTotalPages) {
                        this.messageCurrentPage = messagePage;
                        this.loadMessages();
                    }
                    break;
                    this.showToast('Transactions refreshed', 'success');
                    break;
                case 'export-transactions':
                    this.exportTransactions();
                    break;
                case 'print-transactions':
                    this.printTransactions();
                    break;
                case 'delete-entity-transaction':
                    const deleteTransId = e.target.closest('[data-id]')?.getAttribute('data-id');
                    if (deleteTransId) {
                        this.deleteEntityTransaction(deleteTransId);
                    }
                    break;
                case 'restore-reports-grid':
                    this.restoreReportsGrid();
                    break;
                case 'print-report':
                    window.print();
                    break;
                case 'export-report':
                    this.exportCurrentReport();
                    break;
                case 'export-all-reports':
                    this.exportAllReports();
                    break;
                case 'close-modal':
                    (async () => {
                        const btn = e.target.closest('button[data-action="close-modal"]');
                        const modal = btn ? btn.closest('.accounting-modal') : null;
                        await this.closeModalWithConfirmation(modal);
                    })();
                    break;
                case 'close-toast':
                    e.target.closest('.accounting-toast')?.remove();
                    break;
            }
        });

        // Filters removed - table deleted

        // Chart period changes
        const revenueExpenseNetPeriod = document.getElementById('revenueExpenseNetPeriod');
        if (revenueExpenseNetPeriod) {
            revenueExpenseNetPeriod.addEventListener('change', () => {
                this.loadRevenueExpenseNetChart();
            });
        }

        const cashBalancePeriod = document.getElementById('cashBalancePeriod');
        if (cashBalancePeriod) {
            cashBalancePeriod.addEventListener('change', () => {
                this.loadCashBalanceChart();
            });
        }
        
        // Modal entries per page change
        const entriesPerPage = document.getElementById('entriesPerPage');
        if (entriesPerPage) {
            entriesPerPage.addEventListener('change', () => {
                this.transactionsPerPage = parseInt(entriesPerPage.value);
                this.transactionsCurrentPage = 1;
                // Recalculate total pages based on current logic
                if (this.transactionsPerPage === 5) {
                    // Use pagination for 5
                    this.transactionsTotalPages = Math.ceil(this.transactionsTotalCount / this.transactionsPerPage);
                } else {
                    // Show all with scrolling for 10, 25, 50, 100 (rows only)
                    this.transactionsTotalPages = 1;
                }
                this.loadModalTransactions();
            });
        }
        
        // Filters removed - table deleted
        
        // Close modal on overlay click
        const transactionsModal = document.getElementById('transactionsModal');
        if (transactionsModal) {
            transactionsModal.addEventListener('click', (e) => {
                if (e.target === transactionsModal) {
                    this.closeTransactionsModal();
                }
            });
        }

        // Attach report card listeners on page load
        this.attachReportCardListeners();
    }

ProfessionalAccounting.prototype.attachReportCardListeners = function() {
        // This ensures report cards work even after content is restored
        document.querySelectorAll('.report-card[data-action="generate-report"]').forEach(card => {
            // Event delegation should handle this, but ensure it's accessible
            if (!card.hasAttribute('data-listener-attached')) {
                card.setAttribute('data-listener-attached', 'true');
            }
        });
    }

ProfessionalAccounting.prototype.ensureTabButtonsClickable = function() {
        // Ensure all tab buttons are clickable, even if permissions system interferes
        const tabButtons = document.querySelectorAll('.tab-btn[data-tab]');
        tabButtons.forEach(btn => {
            // Only enable if not explicitly hidden by permissions
            if (!btn.classList.contains('permission-denied') && !btn.classList.contains('hidden')) {
                // Force enable pointer events and cursor using CSS classes
                btn.classList.add('tab-btn-clickable');
                btn.disabled = false;
                btn.setAttribute('tabindex', '0');
                
                // Remove any blocking attributes
                btn.removeAttribute('aria-disabled');
            }
        });
        
        // Also ensure top-nav-link elements are clickable
        const topNavLinks = document.querySelectorAll('.top-nav-link[data-tab]');
        topNavLinks.forEach(link => {
            if (!link.classList.contains('permission-denied') && !link.classList.contains('hidden')) {
                link.style.pointerEvents = 'auto';
                link.style.cursor = 'pointer';
                link.style.zIndex = '200';
                link.style.position = 'relative';
                link.disabled = false;
                link.setAttribute('tabindex', '0');
                link.removeAttribute('aria-disabled');
            }
        });
        
        // Ensure top-nav container is clickable
        const topNav = document.querySelector('.accounting-top-nav');
        if (topNav) {
            topNav.style.pointerEvents = 'auto';
            topNav.style.zIndex = '200';
            topNav.style.position = 'relative';
        }
        
        // Ensure no hidden overlays are blocking clicks
        const hiddenOverlays = document.querySelectorAll('.accounting-modal-overlay:not(.accounting-modal-visible):not(.show-modal)');
        hiddenOverlays.forEach(overlay => {
            overlay.style.pointerEvents = 'none';
            overlay.style.zIndex = '-1';
            overlay.style.display = 'none';
        });
        
        // Ensure hidden modals don't block clicks
        const hiddenModals = document.querySelectorAll('.accounting-modal.accounting-modal-hidden, .accounting-modal.hidden');
        hiddenModals.forEach(modal => {
            modal.style.pointerEvents = 'none';
            modal.style.zIndex = '-1';
        });
    }

ProfessionalAccounting.prototype.handleNavClick = function(tabName) {
    if (!tabName) return;
    if (window.ACCOUNTING_DEBUG && console && console.log) console.log('[Accounting Tab Debug] handleNavClick called', tabName);
    try {
        if (tabName === 'dashboard') this.switchTab('dashboard');
        else if (tabName === 'chart-of-accounts') { this.openChartOfAccountsModal(); this.switchTab('dashboard'); }
        else if (tabName === 'cost-centers') { this.openCostCentersModal(); this.switchTab('dashboard'); }
        else if (tabName === 'bank-guarantee') { this.openBankGuaranteeModal(); this.switchTab('dashboard'); }
        else if (tabName === 'support-payments') { this.openSupportPaymentsModal(); this.switchTab('dashboard'); }
        else if (tabName === 'journal-entries' || tabName === 'general-ledger') { this.openGeneralLedgerModal(); this.switchTab('dashboard'); }
        else if (tabName === 'expenses') { this.openVouchersModal('expenses'); this.switchTab('dashboard'); }
        else if (tabName === 'receipts') { this.openReceiptVouchersModal(); this.switchTab('dashboard'); }
        else if (tabName === 'disbursement-vouchers' || tabName === 'vouchers') { this.openVouchersModal(); this.switchTab('dashboard'); }
        else if (tabName === 'accounts-payable') { this.openPayablesModal(); this.switchTab('dashboard'); }
        else if (['electronic-invoices','invoices','accounts-receivable'].includes(tabName)) { this.openReceivablesModal(); this.switchTab('dashboard'); }
        else if (tabName === 'entry-approval') { this.openEntryApprovalModal(); this.switchTab('dashboard'); }
        else if (['bank-reconciliation','banking-cash','banking'].includes(tabName)) { this.loadBankingCashModal(); this.switchTab('dashboard'); }
        else if (tabName === 'financial-reports' || tabName === 'reports') { this.openReportsModal(); this.switchTab('dashboard'); }
        else this.switchTab(tabName);
    } catch (e) { console.error('handleNavClick error:', e); }
};

ProfessionalAccounting.prototype.handleQuickAction = function(action) {
    if (!action) return;
    if (window.ACCOUNTING_DEBUG && console && console.log) console.log('[Accounting Tab Debug] handleQuickAction called', action);
    try {
        switch (action) {
            case 'quick-entry': this.openQuickEntry(); break;
            case 'open-receivables-modal': this.openInvoiceModal(); break;
            case 'open-payment-voucher': this.openPaymentVoucherModal(); break;
            case 'open-receipt-voucher': this.openReceiptVoucherModal(); break;
            case 'open-reports-modal': this.openReportsModal(); break;
            case 'open-settings-modal': this.openSettingsModal(); break;
            default: break;
        }
    } catch (e) { console.error('handleQuickAction error:', e); }
};

ProfessionalAccounting.prototype.switchTab = function(tabName) {
        // Map sidebar navigation items to actual tabs where needed
        const tabMapping = {
            'payment-voucher': 'vouchers',
            'receipt-voucher': 'vouchers',
            'expenses': 'invoices',
            'accounts-receivable': 'invoices',
            'general-ledger': 'journal-entries'
        };
        
        // Handle special tabs that open modals instead of tab content
        if (tabName === 'follow-up') {
            this.openFollowupModal();
            // Switch to dashboard to hide any inline content
            setTimeout(() => {
                this.switchTab('dashboard');
            }, 100);
            return;
        }
        
        if (tabName === 'messages') {
            this.openMessagesModal();
            // Switch to dashboard to hide any inline content
            setTimeout(() => {
                this.switchTab('dashboard');
            }, 100);
            return;
        }
        
        // If switching to dashboard, refresh currency and status cards
        if (tabName === 'dashboard') {
            this.initDefaultCurrency();
        }

        let actualTab = tabName;
        if (tabMapping[tabName]) {
            actualTab = tabMapping[tabName];
        }

        // Convert dash-case to camelCase for tab ID
        // Example: "general-ledger" -> "generalLedger" -> "generalLedgerTab"
        const convertToCamelCase = (str) => {
            return str.replace(/-([a-z])/g, (g) => g[1].toUpperCase());
        };
        
        const tabId = convertToCamelCase(actualTab) + 'Tab';
        
        const tabContent = document.getElementById(tabId);
        
        if (!tabContent && !tabMapping[tabName]) {
            // Tab doesn't exist and no mapping - show error
            this.showToast(`Tab "${tabName}" not found.`, 'error');
            return;
        }

        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.tab === actualTab) {
                btn.classList.add('active');
            }
        });

        // Update top nav links (both the clicked one and the mapped one)
        document.querySelectorAll('.top-nav-link[data-tab], .nav-link[data-tab]').forEach(link => {
            link.classList.remove('active');
            if (link.dataset.tab === tabName || link.dataset.tab === actualTab) {
                link.classList.add('active');
            }
        });

        // Update tab content - force hide all first using CSS classes
        // BUT preserve entities tab if we're switching to it
        document.querySelectorAll('.tab-content').forEach(content => {
            if (content.id === 'entitiesTab' && actualTab === 'entities') {
                // Don't hide entities tab if we're switching to it
            } else {
                content.classList.remove('active');
                content.classList.add('accounting-tab-hidden');
                content.classList.remove('accounting-tab-visible');
            }
        });

        if (tabContent) {
            tabContent.classList.add('active');
            tabContent.classList.remove('accounting-tab-hidden');
            tabContent.classList.add('accounting-tab-visible');
            void tabContent.offsetHeight;
            
            // Removed scrollIntoView to prevent page jump
        } else if (tabMapping[tabName]) {
            const mappedTabId = convertToCamelCase(actualTab) + 'Tab';
            const mappedTabContent = document.getElementById(mappedTabId);
            if (mappedTabContent) {
                mappedTabContent.classList.add('active');
                mappedTabContent.classList.remove('accounting-tab-hidden');
                mappedTabContent.classList.add('accounting-tab-visible');
                void mappedTabContent.offsetHeight;
                // Removed scrollIntoView to prevent page jump
            } else {
                this.showToast(`Content for "${tabName}" is not available yet.`, 'warning');
            }
        } else {
            this.showToast(`Tab "${tabName}" not found.`, 'error');
        }

        this.currentTab = actualTab;

        switch(actualTab) {
            case 'reports':
                this.saveReportsOriginalContent();
                // Restore original reports grid if user navigates back to reports tab
                // This allows users to select a different report
                const reportsTab = document.getElementById('financialReportsTab') || document.getElementById('reportsTab');
                if (reportsTab && this.reportsOriginalContent) {
                    const moduleContent = reportsTab.querySelector('.module-content');
                    // Only restore if currently showing a placeholder or generated report
                    if (moduleContent && (moduleContent.querySelector('.report-placeholder') || moduleContent.querySelector('.report-content') || moduleContent.querySelector('.report-loading'))) {
                        moduleContent.innerHTML = this.reportsOriginalContent;
                        // Re-attach event listeners for report cards
                        this.attachReportCardListeners();
                    }
                }
                break;
            case 'settings':
                // Settings always opens as modal
                this.openSettingsModal();
                // Switch back to dashboard to hide any inline content
                setTimeout(() => {
                    this.switchTab('dashboard');
                }, 100);
                break;
            case 'dashboard':
                // Always load dashboard data, even if modal is open
                // The loadRecentTransactions function will check for modals itself
                this.loadDashboard();
                this.loadFinancialOverview(); // Refresh overview when switching to dashboard
                break;
            case 'journal-entries':
            case 'general-ledger':
                // Journal Entries always opens as modal
                this.openTransactionsModal();
                // Switch back to dashboard to hide any inline content
                setTimeout(() => {
                    this.switchTab('dashboard');
                }, 100);
                break;
            case 'chart-of-accounts':
                // Chart of Accounts always opens as modal
                this.openChartOfAccountsModal();
                // Switch back to dashboard to hide any inline content
                setTimeout(() => {
                    this.switchTab('dashboard');
                }, 100);
                break;
            case 'banking-cash':
            case 'banking':
                // Banking & Cash opens as modal when clicking tab button
                // But when clicking sidebar nav, show the tab content if available
                break;
            case 'vouchers':
            case 'payment-voucher':
            case 'receipt-voucher':
                // Vouchers always opens as modal
                this.openVouchersModal();
                // Switch back to dashboard to hide any inline content
                setTimeout(() => {
                    this.switchTab('dashboard');
                }, 100);
                break;
            case 'invoices':
            case 'accounts-receivable':
            case 'expenses':
                // Expenses = Payment vouchers only (money out)
                this.openVouchersModal('expenses');
                setTimeout(() => {
                    this.switchTab('dashboard');
                }, 100);
                break;
            case 'accounts-payable':
                this.loadBills();
                break;
            case 'financial-reports':
            case 'reports':
                // Reports tab content
                break;
            case 'entities':
                this.loadEntities();
                break;
        }
        
        // Apply permissions after content is loaded - wait a bit to ensure permissions are loaded
        setTimeout(() => {
            if (window.UserPermissions) {
                if (window.UserPermissions.loaded) {
                    window.UserPermissions.applyPermissions();
                } else {
                    window.UserPermissions.load().then(() => {
                        window.UserPermissions.applyPermissions();
                    });
                }
            }
        }, 100);
    }

ProfessionalAccounting.prototype.initializeDates = function() {
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
    }

    // Initialize Flatpickr on date inputs with English locale
ProfessionalAccounting.prototype.initializeEnglishDatePickers = function(container = document) {
        // Always use the global function if available (it has the explicit locale config)
        if (typeof window.initializeEnglishDatePickers === 'function') {
            window.initializeEnglishDatePickers(container);
            return;
        }
        
        // Fallback if global function not available
        if (typeof flatpickr === 'undefined') {
            setTimeout(() => {
                this.initializeEnglishDatePickers(container);
            }, 100);
            return;
        }
        
        // Target both date inputs and date-input class
        const dateInputs = container.querySelectorAll('input[type="date"], input.date-input');
        dateInputs.forEach((input) => {
            // Skip if already initialized
            if (input._flatpickr) {
                return;
            }
            
            // Convert HTML5 date input to text input for Flatpickr
            const originalType = input.type;
            const originalValue = input.value;
            
            // Change to text input if it's currently a date input
            if (input.type === 'date') {
                input.type = 'text';
            }
            
            try {
                // Use explicit English locale
                const englishLocale = {
                    weekdays: {
                        shorthand: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                        longhand: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
                    },
                    months: {
                        shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                        longhand: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
                    },
                    firstDayOfWeek: 0,
                    rangeSeparator: ' to ',
                    weekAbbreviation: 'Wk',
                    scrollTitle: 'Scroll to increment',
                    toggleTitle: 'Click to toggle',
                    amPM: ['AM', 'PM'],
                    yearAriaLabel: 'Year',
                    monthAriaLabel: 'Month',
                    hourAriaLabel: 'Hour',
                    minuteAriaLabel: 'Minute',
                    time_24hr: false
                };
                
                // Convert YYYY-MM-DD to MM/DD/YYYY before initializing Flatpickr
                let dateValue = originalValue || null;
                if (dateValue && typeof dateValue === 'string') {
                    if (dateValue.match(/^\d{4}-\d{2}-\d{2}$/)) {
                        // Convert YYYY-MM-DD to MM/DD/YYYY
                        const parts = dateValue.split('-');
                        dateValue = `${parts[1]}/${parts[2]}/${parts[0]}`;
                        // Update input value immediately
                        input.value = dateValue;
                    } else if (!dateValue.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
                        // Try to parse and convert
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
                
                const fp = flatpickr(input, {
                    locale: englishLocale,
                    dateFormat: 'm/d/Y',
                    altInput: false,
                    allowInput: true,
                    enableTime: false,
                    time_24hr: false,
                    defaultDate: dateValue,
                    clickOpens: true
                });
            } catch (e) {
                console.warn('Failed to initialize Flatpickr on date input:', e);
                // Restore original type on error
                input.type = originalType;
            }
        });
    }

ProfessionalAccounting.prototype.loadFinancialOverview = async function() {
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
    }

ProfessionalAccounting.prototype.updateOverviewCards = function(data) {
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
    }

ProfessionalAccounting.prototype.refreshAllModules = async function() {
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
    }

ProfessionalAccounting.prototype.loadDashboard = async function() {
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
    }
    
ProfessionalAccounting.prototype.ensureQuickActionsVisible = function() {
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
    }

ProfessionalAccounting.prototype.loadRecentTransactions = async function() {
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
    }

ProfessionalAccounting.prototype.loadCashFlowSummary = async function() {
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
    }

ProfessionalAccounting.prototype.loadFinancialSummary = async function() {
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
    }

ProfessionalAccounting.prototype.updateRecentTransactionsPagination = function(totalCountOverride = null) {
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
    }

ProfessionalAccounting.prototype.setupRecentTransactionsPaginationControls = function() {
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
    }

ProfessionalAccounting.prototype.renderRecentTransactionsPageButtons = function() {
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
    }

ProfessionalAccounting.prototype.loadRevenueExpenseNetChart = async function() {
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
    }

ProfessionalAccounting.prototype.getChartStyles = function() {
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
    }

ProfessionalAccounting.prototype.renderRevenueExpenseNetChart = function(chartData) {
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
    }


ProfessionalAccounting.prototype.loadCashBalanceChart = async function() {
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
    }

ProfessionalAccounting.prototype.renderCashBalanceChart = function(chartData) {
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
    }

ProfessionalAccounting.prototype.loadReceivablePayableChart = async function() {
        try {
            const [receivablesRes, payablesRes] = await Promise.all([
                fetch(`${this.apiBase}/invoices.php`, { credentials: 'include' }),
                fetch(`${this.apiBase}/bills.php`, { credentials: 'include' })
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
    }

ProfessionalAccounting.prototype.renderReceivablePayableChart = function(receivablesTotal, receivablesCount, payablesTotal, payablesCount) {
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
    }

ProfessionalAccounting.prototype.loadExpenseBreakdownChart = async function() {
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
    }

ProfessionalAccounting.prototype.renderExpenseBreakdownChart = function(data) {
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
    }

ProfessionalAccounting.prototype.loadInvoiceAgingChart = async function() {
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
    }

ProfessionalAccounting.prototype.renderInvoiceAgingChart = function(data) {
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
    }

ProfessionalAccounting.prototype.loadFinancialOverviewChart = async function() {
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
    }

ProfessionalAccounting.prototype.renderFinancialOverviewChart = function(totalIncome, totalExpense) {
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
    }

ProfessionalAccounting.prototype.loadJournalEntries = async function() {
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
                credentials: 'include',
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
    }

ProfessionalAccounting.prototype.loadInvoices = async function() {
        const tbody = document.getElementById('invoicesBody');
        if (!tbody) return;

        // Show loading state
        tbody.innerHTML = '<tr><td colspan="9" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

        try {
            const response = await fetch(`${this.apiBase}/invoices.php`, { credentials: 'include' });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const msg = data.message || data.error || `HTTP error! status: ${response.status}`;
                throw new Error(msg);
            }

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
    }

ProfessionalAccounting.prototype.loadBills = async function() {
        const tbody = document.getElementById('billsBody');
        if (!tbody) return;

        // Show loading state
        tbody.innerHTML = '<tr><td colspan="10" class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

        try {
            const response = await fetch(`${this.apiBase}/bills.php`, { credentials: 'include' });
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
    }

ProfessionalAccounting.prototype.loadBankAccounts = async function() {
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

ProfessionalAccounting.prototype.filterAndSortBankAccounts = function(banks) {
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
    }

ProfessionalAccounting.prototype.updateBankingPaginationControls = function() {
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
    }

ProfessionalAccounting.prototype.updateBankingStatusCards = function(banks = null) {
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
    }

ProfessionalAccounting.prototype.setupBankAccountActions = function() {
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
    }

ProfessionalAccounting.prototype.viewBankAccount = async function(accountId) {
        try {
            const response = await fetch(`${this.apiBase}/banks.php?id=${accountId}`);
            const data = await response.json();
            
            if (data.success && data.bank) {
                const bank = data.bank;
                // Format ID as BA001, BA002, etc.
                const formattedId = `BA${String(bank.id || 0).padStart(3, '0')}`;
                const content = `
                    <div class="bank-account-details">
                        <h3>Bank Account Details</h3>
                        <div class="detail-row">
                            <label>Account ID:</label>
                            <span><strong>${formattedId}</strong></span>
                        </div>
                        <div class="detail-row">
                            <label>Bank Name:</label>
                            <span>${this.escapeHtml(bank.bank_name || 'N/A')}</span>
                        </div>
                        <div class="detail-row">
                            <label>Account Name:</label>
                            <span>${this.escapeHtml(bank.account_name || 'N/A')}</span>
                        </div>
                        <div class="detail-row">
                            <label>Account Number:</label>
                            <span>${this.escapeHtml(bank.account_number || 'N/A')}</span>
                        </div>
                        <div class="detail-row">
                            <label>Account Type:</label>
                            <span>${this.escapeHtml(bank.account_type || 'Checking')}</span>
                        </div>
                        <div class="detail-row">
                            <label>Opening Balance:</label>
                            <span>${this.formatCurrency(bank.opening_balance || 0, bank.currency || this.getDefaultCurrencySync())}</span>
                        </div>
                        <div class="detail-row">
                            <label>Current Balance:</label>
                            <span><strong>${this.formatCurrency(bank.current_balance || 0, bank.currency || this.getDefaultCurrencySync())}</strong></span>
                        </div>
                        <div class="detail-row">
                            <label>Status:</label>
                            <span><span class="badge badge-${bank.is_active ? 'success' : 'secondary'}">${bank.is_active ? 'Active' : 'Inactive'}</span></span>
                        </div>
                        ${bank.created_at ? `
                            <div class="detail-row">
                                <label>Created:</label>
                                <span>${this.formatDate(bank.created_at)}</span>
                            </div>
                        ` : ''}
                    </div>
                `;
                this.showModal('Bank Account Details', content);
            } else {
                this.showToast(data.message || 'Failed to load bank account', 'error');
            }
        } catch (error) {
            this.showToast('Error loading bank account: ' + error.message, 'error');
        }
    }

ProfessionalAccounting.prototype.deleteBankAccount = async function(accountId) {
        const confirmed = await this.showConfirmDialog(
            'Delete Bank Account',
            'Are you sure you want to permanently delete this bank account? This action cannot be undone and will permanently remove the account from the database.',
            'Delete',
            'Cancel',
            'danger'
        );
        
        if (!confirmed) return;
        
        try {
            const response = await fetch(`${this.apiBase}/banks.php?id=${accountId}`, {
                method: 'DELETE'
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                this.showToast(errorData.message || `Failed to delete bank account: HTTP ${response.status}`, 'error');
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.showToast('Bank account deleted successfully', 'success');
                // Remove from selected accounts if it was selected
                this.bankingSelectedAccounts.delete(accountId);
                await this.loadBankAccounts();
            } else {
                this.showToast(data.message || 'Failed to delete bank account', 'error');
            }
        } catch (error) {
            this.showToast('Error deleting bank account: ' + error.message, 'error');
            console.error('Error deleting bank account:', error);
        }
    }

    // Modal Loading Functions
ProfessionalAccounting.prototype.loadModalJournalEntries = async function() {
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
    }

ProfessionalAccounting.prototype.clearLedgerFilters = function(expandRange = false) {
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
    }

ProfessionalAccounting.prototype.formatDate = function(dateString) {
        if (!dateString) return '-';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            // Format as MM/DD/YYYY (English format)
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${month}/${day}/${year}`;
        } catch (e) {
            return dateString;
        }
    }

    // Format date for input fields (MM/DD/YYYY)
ProfessionalAccounting.prototype.formatDateForInput = function(dateString) {
        if (!dateString) return '';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return '';
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${month}/${day}/${year}`;
        } catch (e) {
            return '';
        }
    }
    
    // Format date for API requests (YYYY-MM-DD)