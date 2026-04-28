/**
 * EN: Implements frontend interaction behavior in `js/accounting/accounting-modal.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/accounting-modal.js`.
 */
/**
 * Accounting Modal System - Complete Rebuild
 * Modern, feature-rich modal for entity financial transactions
 * Supports: agents, workers, subagents, hr
 */

class AccountingModal {
    constructor() {
        this.currentEntity = null;
        this.currentEntityId = null;
        this.currentEntityName = null;
        this.currentTransaction = null;
        this.isEditMode = false;
        this.isSubmitting = false; // Flag to prevent duplicate submissions
        this.isClosing = false; // Flag to prevent reopening during close
        const baseUrl = (window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '');
        this.apiBase = baseUrl + '/api/accounting/entity-transactions.php';
        this.baseUrl = baseUrl; // Store for use in methods
        this._closeButtonHandler = null; // Store close button handler
        this._backdropHandler = null; // Store backdrop handler
        this.init();
    }
    
    // Helper method to get API URL
    getApiUrl(endpoint) {
        return this.baseUrl + '/api/accounting/' + endpoint;
    }
    
    // Helper method to get page URL
    getPageUrl(page) {
        return this.baseUrl + '/pages/' + page;
    }

    init() {
        this.createModalHTML();
        this.setupEventListeners();
        this.setupGlobalCloseHandler();
        
        // Ensure modal is hidden on initialization (only if not being opened)
        setTimeout(() => {
            const modal = document.getElementById('accountingModal');
            if (modal && !this.currentEntity) {
                modal.classList.add('accounting-modal-hidden');
            }
        }, 100);
    }
    
    // Global close handler - SIMPLE like the example
    setupGlobalCloseHandler() {
        // Only set up once
        if (this._globalCloseHandlerSetup) return;
        this._globalCloseHandlerSetup = true;
        
        // Simple close function - COMPLETELY DISABLED alerts
        const closeModal = async () => {
            // ALWAYS close without any alerts - especially when offline
            // DISABLED: All alert checks removed
            
            // Extra safety: If offline, NEVER show alerts
            if (!navigator.onLine) {
                const modal = document.getElementById('accountingModal');
                if (modal) {
                    modal.classList.remove('accounting-modal-visible');
                    modal.classList.add('accounting-modal-hidden');
                    document.body.classList.remove('body-no-scroll');
                    this.currentEntity = null;
                    this.currentEntityId = null;
                    this.currentEntityName = null;
                    this.resetForm();
                }
                return;
            }
            
            const modal = document.getElementById('accountingModal');
            if (modal) {
                modal.classList.remove('accounting-modal-visible');
                modal.classList.add('accounting-modal-hidden');
                
                // Only remove body-no-scroll if General Ledger modal is not open
                const generalLedgerModal = document.getElementById('generalLedgerModal');
                const isGeneralLedgerOpen = generalLedgerModal && 
                    !generalLedgerModal.classList.contains('accounting-modal-hidden');
                
                if (!isGeneralLedgerOpen) {
                    document.body.classList.remove('body-no-scroll');
                }
                
                this.currentEntity = null;
                this.currentEntityId = null;
                this.currentEntityName = null;
                this.resetForm();
            }
        };
        
        // Close button click - simple approach
        document.addEventListener('click', async (e) => {
            const modal = document.getElementById('accountingModal');
            if (!modal || modal.classList.contains('accounting-modal-hidden')) return;
            
            // Close button clicked
            if (e.target.closest('.accounting-close') || 
                e.target.classList.contains('accounting-close') ||
                e.target.getAttribute('data-action') === 'close-accounting-modal') {
                e.preventDefault();
                e.stopPropagation();
                await closeModal();
                return false;
            }
            
            // Click outside modal (backdrop)
            if (e.target === modal) {
                e.preventDefault();
                e.stopPropagation();
                await closeModal();
                return false;
            }
        });
        
        // Escape key
        document.addEventListener('keydown', async (e) => {
            if (e.key === 'Escape') {
                const modal = document.getElementById('accountingModal');
                if (modal && !modal.classList.contains('accounting-modal-hidden')) {
                    await closeModal();
                }
            }
        });
    }
    
    // Simple close function - like the example
    forceClose() {
        const modal = document.getElementById('accountingModal');
        if (modal) {
            modal.classList.add('accounting-modal-hidden');
            modal.classList.remove('accounting-modal-visible');
            
            // Only remove body-no-scroll if General Ledger modal is not open
            const generalLedgerModal = document.getElementById('generalLedgerModal');
            const isGeneralLedgerOpen = generalLedgerModal && 
                !generalLedgerModal.classList.contains('accounting-modal-hidden');
            
            if (!isGeneralLedgerOpen) {
                document.body.classList.remove('body-no-scroll');
            }
            
            this.currentEntity = null;
            this.currentEntityId = null;
            this.currentEntityName = null;
            this.resetForm();
        }
    }

    createModalHTML() {
        // Remove existing modal if it exists
        const existingModal = document.getElementById('accountingModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        const modalHTML = `
                <div id="accountingModal" class="accounting-modal accounting-modal-hidden">
                    <div class="accounting-modal-content">
                        <div class="accounting-modal-header">
                            <h2 id="accountingModalTitle">Accounting</h2>
                            <button type="button" class="accounting-close" data-action="close-accounting-modal" title="Close">&times;</button>
                        </div>
                        <div class="accounting-modal-body">
                            <div class="accounting-tabs">
                                <button class="tab-btn active" data-tab="transactions">
                                    <i class="fas fa-exchange-alt"></i> Transactions
                                </button>
                            </div>
                            
                            <!-- Transactions Tab -->
                            <div id="transactionsTab" class="tab-content active">
                                <div class="transactions-layout">
                                    <div class="form-section">
                                        <div class="form-content-wrapper">
                                            <form id="accountingTransactionForm">
                                                <input type="hidden" id="transactionEditId" name="editId" value="">
                                                <!-- Journal Entry Fields (shown only for journal mode) -->
                                                <div id="journalFields" class="journal-fields journal-fields-hidden">
                                                    <!-- Entry # (Auto-generated, hidden) -->
                                                    <input type="hidden" id="journalEntryNumber" name="journal_entry_number" value="">
                                                    
                                                    <!-- Row 1: Entity (full width) -->
                                                    <div class="form-row">
                                                    <div class="form-group">
                                                            <label for="journalEntity">Entity</label>
                                                            <!-- Entity Type Filter Tabs -->
                                                            <div class="entity-type-filter-tabs" id="journalEntityTypeTabs">
                                                                <button type="button" class="entity-type-tab active" data-entity-type="all" title="Show All Entities">
                                                                    <i class="fas fa-list"></i> All
                                                                </button>
                                                                <button type="button" class="entity-type-tab" data-entity-type="agent" title="Show Agents Only">
                                                                    <i class="fas fa-user-tie"></i> Agents
                                                                </button>
                                                                <button type="button" class="entity-type-tab" data-entity-type="subagent" title="Show Subagents Only">
                                                                    <i class="fas fa-user-friends"></i> Subagents
                                                                </button>
                                                                <button type="button" class="entity-type-tab" data-entity-type="worker" title="Show Workers Only">
                                                                    <i class="fas fa-hard-hat"></i> Workers
                                                                </button>
                                                                <button type="button" class="entity-type-tab" data-entity-type="hr" title="Show HR Only">
                                                                    <i class="fas fa-users"></i> HR
                                                                </button>
                                                                <button type="button" class="entity-type-tab entity-type-tab-manual" data-entity-type="manual" title="Manual Entry" id="manualEntryTab">
                                                                    <i class="fas fa-pen"></i> Manual Entry
                                                                </button>
                                                    </div>
                                                            <select class="form-control" id="journalEntity" name="journal_entity">
                                                                <option value="">Select Entity (Optional)</option>
                                                            </select>
                                                    </div>
                                                    </div>
                                                    
                                                    <!-- Row 2: Account, Date, Currency -->
                                                    <div class="form-row">
                                                    <div class="form-group">
                                                            <label for="journalAccount">Account <span class="required">*</span></label>
                                                            <select class="form-control" id="journalAccount" name="journal_account" required>
                                                                <option value="">Loading accounts...</option>
                                                            </select>
                                                    </div>
                                                    <div class="form-group">
                                                            <label for="journalDate">Date <span class="required">*</span></label>
                                                            <input type="text" class="form-control date-input" id="journalDate" name="journal_date" required placeholder="MM/DD/YYYY">
                                                    </div>
                                                    <div class="form-group">
                                                        <label for="journalCurrency">Currency <span class="required">*</span></label>
                                                        <select class="form-control" id="journalCurrency" name="journal_currency" required>
                                                            <option value="">Loading currencies...</option>
                                                        </select>
                                                    </div>
                                                    </div>
                                                    
                                                    <!-- Row 3: Debit, Credit, Type, Status (4 columns) -->
                                                    <div class="form-row form-row-4cols">
                                                    <div class="form-group">
                                                            <label for="journalDebitAmount">Debit</label>
                                                            <input type="number" class="form-control" id="journalDebitAmount" name="journal_debit" step="0.01" placeholder="0.00">
                                                    </div>
                                                    <div class="form-group">
                                                            <label for="journalCreditAmount">Credit</label>
                                                            <input type="number" class="form-control" id="journalCreditAmount" name="journal_credit" step="0.01" placeholder="0.00">
                                                    </div>
                                                                    <div class="form-group">
                                                            <label for="journalType">Type</label>
                                                            <select class="form-control" id="journalType" name="journal_type">
                                                                <option value="Manual" selected>Manual</option>
                                                                <option value="Automatic">Automatic</option>
                                                                <option value="Recurring">Recurring</option>
                                                                <option value="Adjustment">Adjustment</option>
                                                                <option value="Reversal">Reversal</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="form-group">
                                                            <!-- Status field hidden - managed automatically by system -->
                                                            <input type="hidden" id="journalStatus" name="journal_status" value="Draft">
                                                                    </div>
                                                                </div>
                                                    
                                                    <!-- Row 4: Description (full width) -->
                                                                <div class="form-row">
                                                                    <div class="form-group">
                                                            <label for="journalDescription">Description <span class="required">*</span></label>
                                                            <textarea class="form-control" id="journalDescription" name="journal_description" placeholder="Enter journal entry description" required rows="4"></textarea>
                                                                    </div>
                                                                    </div>
                                                    
                                                        <!-- Balance Status (full width) -->
                                                    <div id="journalBalanceStatus" class="journal-balance-status hidden full-width">
                                                            <span id="journalBalanceText"></span>
                                                    </div>
                                                </div>
                                                <!-- Entity Transaction Fields (shown for entity modes) -->
                                                <div id="entityFields" class="entity-fields">
                                                    <!-- Row 1: Account, Date, Currency, Transaction Type (4 columns) -->
                                                    <div class="form-row form-row-4cols">
                                                        <div class="form-group">
                                                            <label for="transactionAccount">Account <span class="required">*</span></label>
                                                            <select class="form-control" id="transactionAccount" name="account_id" required>
                                                                <option value="">Loading accounts...</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="transactionDate">Date <span class="required">*</span></label>
                                                            <input type="text" class="form-control date-input" id="transactionDate" name="date" required placeholder="MM/DD/YYYY">
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="transactionCurrency">Currency <span class="required">*</span></label>
                                                            <select class="form-control" id="transactionCurrency" name="currency" required>
                                                                <option value="">Loading currencies...</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="transactionType">Transaction Type <span class="required">*</span></label>
                                                            <select class="form-control" id="transactionType" name="type" required>
                                                                <option value="">Select Type</option>
                                                                <option value="Income">Income</option>
                                                                <option value="Expense">Expense</option>
                                                                <option value="Transfer">Transfer</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <!-- Row 2: Debit, Credit, Type, Status (4 columns) -->
                                                    <div class="form-row form-row-4cols">
                                                        <div class="form-group">
                                                            <label for="transactionDebit">Debit</label>
                                                            <input type="number" class="form-control" id="transactionDebit" name="debit" step="0.01" placeholder="0.00">
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="transactionCredit">Credit</label>
                                                            <input type="number" class="form-control" id="transactionCredit" name="credit" step="0.01" placeholder="0.00">
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="transactionEntryType">Type</label>
                                                            <select class="form-control" id="transactionEntryType" name="entry_type">
                                                                <option value="Manual" selected>Manual</option>
                                                                <option value="Automatic">Automatic</option>
                                                                <option value="Recurring">Recurring</option>
                                                                <option value="Adjustment">Adjustment</option>
                                                                <option value="Reversal">Reversal</option>
                                                            </select>
                                                        </div>
                                                        <!-- Status field hidden - managed automatically by system -->
                                                        <input type="hidden" id="transactionStatus" name="status" value="Posted">
                                                    </div>
                                                    <!-- Row 3: Category, Description (Description wider) -->
                                                    <div class="form-row form-row-2cols">
                                                        <div class="form-group">
                                                            <label for="transactionCategory">Category</label>
                                                            <select class="form-control" id="transactionCategory" name="category">
                                                                <option value="commission">Commission</option>
                                                                <option value="salary">Salary</option>
                                                                <option value="bonus">Bonus</option>
                                                                <option value="payment">Payment</option>
                                                                <option value="refund">Refund</option>
                                                                <option value="expense">Expense</option>
                                                                <option value="other">Other</option>
                                                            </select>
                                                        </div>
                                                        <div class="form-group form-group-wide">
                                                            <label for="transactionDescription">Description <span class="required">*</span></label>
                                                            <textarea class="form-control" id="transactionDescription" name="description" placeholder="Enter transaction description" required rows="4"></textarea>
                                                        </div>
                                                    </div>
                                                    <!-- Row 4: Reference Number (alone, full width) -->
                                                    <div class="form-row">
                                                        <div class="form-group">
                                                            <label for="transactionReference">Reference Number</label>
                                                            <input type="text" class="form-control" id="transactionReference" name="reference" placeholder="Auto-generated if empty">
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                        <div class="form-actions">
                                            <button type="button" class="btn btn-secondary" id="cancelTransactionBtn" data-action="cancel-transaction">Cancel</button>
                                            <button type="submit" class="btn btn-primary" id="saveTransactionBtn">
                                                <i class="fas fa-save"></i> Save Transaction
                                            </button>
                                        </div>
                                    </div>
                                
                                <div class="transactions-list">
                                    <div class="list-header">
                                        <div>
                                            <h4>Transaction History</h4>
                                            <p class="accounting-sync-info">
                                                <i class="fas fa-sync-alt"></i> Synced with Accounting System
                                            </p>
                                        </div>
                                        <div class="list-header-actions">
                                            <a href="${this.getPageUrl('accounting.php')}" class="btn btn-sm btn-primary" target="_blank" title="View in Accounting System">
                                                <i class="fas fa-external-link-alt"></i> View in Accounting
                                            </a>
                                            <button class="btn btn-sm btn-outline" data-action="refresh-transactions" title="Refresh">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div id="transactionsLoading" class="loading-state accounting-loading-hidden">
                                        <i class="fas fa-spinner fa-spin"></i> Loading transactions...
                                    </div>
                                    <div id="transactionsEmpty" class="empty-state accounting-empty-hidden">
                                        <i class="fas fa-inbox"></i>
                                        <p>No transactions found</p>
                                    </div>
                                    <div class="table-container">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Type</th>
                                                    <th>Description</th>
                                                    <th>Category</th>
                                                    <th>Debit</th>
                                                    <th>Credit</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="accountingTransactionsTable">
                                                <!-- Transactions will be loaded here -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Verify modal was created
            const createdModal = document.getElementById('accountingModal');
            if (createdModal) {
                // Only hide if not being opened immediately
                if (!this.currentEntity) {
                    createdModal.classList.add('accounting-modal-hidden');
                }
            }
    }

    setupEventListeners() {
        // Tab switching
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('tab-btn') || e.target.closest('.tab-btn')) {
                const btn = e.target.classList.contains('tab-btn') ? e.target : e.target.closest('.tab-btn');
                if (btn && btn.dataset.tab) {
                    this.switchTab(btn.dataset.tab);
                }
            }
        });
        
        // Action handlers - use event delegation that always works
        // This handler stays active and checks modal state on each click
        if (!this._documentClickHandler) {
            this._documentClickHandler = (e) => {
                const modal = document.getElementById('accountingModal');
                if (!modal || modal.classList.contains('accounting-modal-hidden')) {
                    return;
                }
                
                // Don't interfere with SELECT dropdown clicks
                if (e.target.tagName === 'SELECT' || e.target.closest('select')) {
                    return;
                }
                
                // Handle close button (X) - multiple ways to detect
                const closeBtn = e.target.closest('.accounting-close');
                if (closeBtn || 
                    e.target.classList.contains('accounting-close') ||
                    e.target.getAttribute('data-action') === 'close-accounting-modal') {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.cancelBubble = true;
                    e.returnValue = false;
                    // Prevent navigation
                    if (window.event) {
                        window.event.preventDefault();
                        window.event.stopPropagation();
                        window.event.returnValue = false;
                        window.event.cancelBubble = true;
                    }
                    // Stop any form submission
                    const form = e.target.closest('form');
                    if (form) {
                        form.removeEventListener('submit', () => {}, true);
                    }
                    if (window.accountingModal) {
                        window.accountingModal.handleClose();
                    }
                    return false;
                }
                
                // Handle backdrop click (outside modal) - only if clicking directly on backdrop
                if (e.target === modal) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.cancelBubble = true;
                    e.returnValue = false;
                    if (window.event) {
                        window.event.preventDefault();
                        window.event.stopPropagation();
                        window.event.returnValue = false;
                        window.event.cancelBubble = true;
                    }
                    if (window.accountingModal) {
                        window.accountingModal.handleClose();
                    }
                    return false;
                }
                
                const action = e.target.closest('[data-action]')?.getAttribute('data-action');
                if (action) {
                    switch(action) {
                        case 'close-accounting-modal':
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            e.cancelBubble = true;
                            e.returnValue = false;
                            if (window.accountingModal) {
                                window.accountingModal.handleClose();
                            }
                            return false;
                        case 'cancel-transaction':
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                            e.cancelBubble = true;
                            e.returnValue = false;
                            if (window.accountingModal) {
                                window.accountingModal.handleClose();
                            }
                            return false;
                        case 'refresh-transactions':
                            if (window.accountingModal) {
                                window.accountingModal.loadTransactions();
                            }
                            break;
                        case 'edit-transaction':
                            const editId = e.target.closest('[data-action]')?.getAttribute('data-transaction-id');
                            if (editId && window.accountingModal) {
                                window.accountingModal.editTransaction(parseInt(editId));
                            }
                            break;
                        case 'delete-transaction':
                            const delId = e.target.closest('[data-action]')?.getAttribute('data-transaction-id');
                            if (delId && window.accountingModal) {
                                window.accountingModal.deleteTransaction(parseInt(delId));
                            }
                            break;
                    }
                }
            };
            
            // Add once, keep forever - it checks modal state internally
            document.addEventListener('click', this._documentClickHandler, true);
        }

        // Form submission - use onsubmit in HTML, but also add listener as backup
        const form = document.getElementById('accountingTransactionForm');
        if (form) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleTransactionSubmit(e);
            });
        }
    }

    async open(entityType, entityId, entityName) {
        // Prevent opening if currently closing
        if (this.isClosing) {
            return;
        }
        
        // Check if already open for same entity
        const existingModal = document.getElementById('accountingModal');
        if (existingModal && !existingModal.classList.contains('accounting-modal-hidden')) {
            // If a journal entry is requested, always allow it (close current modal first)
            if (entityType?.toLowerCase() === 'journal') {
                this.close();
                await new Promise(resolve => setTimeout(resolve, 300));
            } else {
                // Check if it's the same entity
                if (this.currentEntity === entityType?.toLowerCase() && 
                    this.currentEntityId === parseInt(entityId)) {
                    return;
                }
                // If different entity, close first
                this.close();
                // Wait a bit before reopening
                await new Promise(resolve => setTimeout(resolve, 100));
            }
        }
        
        // Normalize entity type to lowercase for API consistency
        this.currentEntity = entityType ? entityType.toLowerCase() : null;
        this.currentEntityId = entityId !== null && entityId !== undefined ? parseInt(entityId) : null;
        this.currentEntityName = entityName;
        this.isEditMode = false;
        this.currentTransaction = null;
        this.isClosing = false;

        const titleElement = document.getElementById('accountingModalTitle');
        if (titleElement) {
            if (this.currentEntity === 'journal') {
                titleElement.textContent = 'New Journal Entry';
            } else {
                const entityLabel = entityType ? (entityType.charAt(0).toUpperCase() + entityType.slice(1)) : 'Entity';
                titleElement.textContent = `Accounting - ${entityName || 'Unknown'} (${entityLabel})`;
            }
        }

        // Show/hide fields based on mode
        const journalFields = document.getElementById('journalFields');
        const entityFields = document.getElementById('entityFields');
        
        // Hide Transaction History section for journal entries
        const transactionsList = document.querySelector('.transactions-list');
        
        if (this.currentEntity === 'journal') {
            // Journal mode: show only journal fields, hide entity fields and transaction history
            if (journalFields) {
                journalFields.classList.remove('journal-fields-hidden');
                journalFields.classList.add('journal-fields-visible');
            }
            if (entityFields) {
                entityFields.classList.remove('entity-fields-visible');
                entityFields.classList.add('entity-fields-hidden');
            }
            if (transactionsList) {
                transactionsList.classList.remove('transactions-list-visible');
                transactionsList.classList.add('transactions-list-hidden');
            }
            // Load accounts and entities for journal dropdown
            // Wait a bit for form elements to be fully ready
            setTimeout(async () => {
                await this.loadJournalAccounts();
                await this.loadJournalEntities('all');
            }, 100);
        } else {
            // Entity mode: show entity fields and transaction history, hide journal fields
            if (journalFields) {
                journalFields.classList.remove('journal-fields-visible');
                journalFields.classList.add('journal-fields-hidden');
            }
            if (entityFields) {
                entityFields.classList.remove('entity-fields-hidden');
                entityFields.classList.add('entity-fields-visible');
            }
            if (transactionsList) {
                transactionsList.classList.remove('transactions-list-hidden');
                transactionsList.classList.add('transactions-list-visible');
            }
            
            // CRITICAL: Remove any duplicate entityTransactionForm that might exist
            setTimeout(() => {
                const duplicateForm = document.getElementById('entityTransactionForm');
                if (duplicateForm) {
                    duplicateForm.remove();
                }
                
                // Ensure only entityFields is visible, not journalFields
                const journalFieldsCheck = document.getElementById('journalFields');
                if (journalFieldsCheck) {
                    journalFieldsCheck.classList.add('journal-fields-hidden');
                    journalFieldsCheck.classList.remove('journal-fields-visible');
                }
                
                const entityFieldsCheck = document.getElementById('entityFields');
                if (entityFieldsCheck) {
                    entityFieldsCheck.classList.add('entity-fields-visible');
                    entityFieldsCheck.classList.remove('entity-fields-hidden');
                }
            }, 50);
            
            // Load accounts and entities for entity transaction form
            setTimeout(async () => {
                await this.loadTransactionAccounts();
                // Load entities if entity select exists
                const entitySelect = document.getElementById('journalEntity');
                if (entitySelect) {
                    await this.loadJournalEntities('all');
                }
            }, 100);
        }

        this.resetForm();
        this.setTodayDate();
        
        // Populate currency dropdowns AFTER resetForm (for both journal and entity modes)
        // Wait for currencyUtils to be available (with retries)
        let retryCount = 0;
        const maxRetries = 15;
        const populateCurrencies = async () => {
            try {
                // Wait for currencyUtils to be available
                if (!window.currencyUtils || typeof window.currencyUtils.populateCurrencySelect !== 'function') {
                    if (retryCount < maxRetries) {
                        retryCount++;
                        setTimeout(populateCurrencies, 100);
                        return;
                    } else {
                        console.error('❌ currencyUtils not available after max retries');
                        // Fallback: Set default currency
                        if (this.currentEntity === 'journal') {
                            const journalCurrencyField = document.getElementById('journalCurrency');
                            if (journalCurrencyField) {
                                journalCurrencyField.innerHTML = '<option value="SAR">SAR - Saudi Riyal</option>';
                            }
                        } else {
                            const transactionCurrencyField = document.getElementById('transactionCurrency');
                            if (transactionCurrencyField) {
                                transactionCurrencyField.innerHTML = '<option value="SAR">SAR - Saudi Riyal</option>';
                            }
                        }
                        return;
                    }
                }
                
                // Get default currency from system settings
                const defaultCurrency = localStorage.getItem('accounting_default_currency') || 'SAR';
                
                // Populate journal currency (if in journal mode)
                if (this.currentEntity === 'journal') {
                    const journalCurrencyField = document.getElementById('journalCurrency');
                    if (journalCurrencyField) {
                        await window.currencyUtils.populateCurrencySelect(journalCurrencyField, defaultCurrency);
                    } else {
                        if (retryCount < maxRetries) {
                            retryCount++;
                            setTimeout(populateCurrencies, 150);
                            return;
                        }
                    }
                } else {
                    // Populate transaction currency (if in entity mode)
                    const transactionCurrencyField = document.getElementById('transactionCurrency');
                    if (transactionCurrencyField) {
                        await window.currencyUtils.populateCurrencySelect(transactionCurrencyField, defaultCurrency);
                    }
                }
            } catch (error) {
                console.error('❌ Error populating currency dropdowns:', error);
            }
        };
        
        // Increased timeout for journal mode to ensure fields are visible
        const currencyDelay = this.currentEntity === 'journal' ? 400 : 200;
        setTimeout(populateCurrencies, currencyDelay);
        
        // Set journal date and initialize read-only fields if in journal mode
        if (this.currentEntity === 'journal') {
            const journalDateField = document.getElementById('journalDate');
            if (journalDateField) {
                const today = new Date().toISOString().split('T')[0];
                journalDateField.value = this.formatDateForInput(today);
            }
            
            // Ensure Entity dropdown is visible and setup filter tabs
            setTimeout(() => {
                const entitySelect = document.getElementById('journalEntity');
                if (entitySelect) {
                    // Make sure it's visible
                    entitySelect.classList.remove('entity-fields-hidden');
                    entitySelect.classList.add('entity-fields-visible');
                }
                
                // Setup entity type filter tabs
                const entityTypeTabs = document.getElementById('journalEntityTypeTabs');
                if (entityTypeTabs) {
                    const tabs = entityTypeTabs.querySelectorAll('.entity-type-tab');
                    const self = this;
                    
                    tabs.forEach(tab => {
                        tab.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            // Remove active class from all tabs
                            tabs.forEach(t => t.classList.remove('active'));
                            
                            // Add active class to clicked tab
                            this.classList.add('active');
                            
                            // Get entity type filter
                            const entityType = this.getAttribute('data-entity-type') || 'all';
                            
                            // Handle manual entry - convert dropdown to text input
                            if (entityType === 'manual') {
                                const entitySelect = document.getElementById('journalEntity');
                                const entityGroup = entitySelect?.closest('.form-group');
                                if (entitySelect && entityGroup) {
                                    // Check if it's already a text input
                                    if (entitySelect.tagName === 'INPUT') {
                                        return; // Already converted
                                    }
                                    
                                    // Store the current value if any
                                    const currentValue = entitySelect.value || '';
                                    
                                    // Create text input to replace dropdown
                                    const textInput = document.createElement('input');
                                    textInput.type = 'text';
                                    textInput.className = 'form-control';
                                    textInput.id = 'journalEntity';
                                    textInput.name = 'journal_entity';
                                    textInput.placeholder = 'Enter manual entry (e.g., Cash, Bank Transfer, etc.)';
                                    textInput.value = currentValue;
                                    
                                    // Replace dropdown with text input
                                    entitySelect.parentNode.replaceChild(textInput, entitySelect);
                                }
                                return;
                            }
                            
                            // For other entity types, ensure it's a dropdown (not text input)
                            const entitySelect = document.getElementById('journalEntity');
                            if (entitySelect && entitySelect.tagName === 'INPUT') {
                                // Convert back to dropdown
                                const currentValue = entitySelect.value || '';
                                const newSelect = document.createElement('select');
                                newSelect.className = 'form-control';
                                newSelect.id = 'journalEntity';
                                newSelect.name = 'journal_entity';
                                newSelect.innerHTML = '<option value="">Select Entity (Optional)</option>';
                                entitySelect.parentNode.replaceChild(newSelect, entitySelect);
                                
                                // Reload entities with filter
                                self.loadJournalEntities(entityType);
                            } else {
                                // Reload entities with filter
                                self.loadJournalEntities(entityType);
                            }
                        });
                    });
                    
                }
            }, 150);
            // Set read-only fields
                // Entry # is now hidden and auto-generated by the backend
            const journalEntryNumber = document.getElementById('journalEntryNumber');
            if (journalEntryNumber && !this.isEditMode) {
                    // Keep it empty, backend will generate it
                    journalEntryNumber.value = '';
            }
            const journalType = document.getElementById('journalType');
            if (journalType && !this.isEditMode) {
                // Only set default value in add mode, allow user to change it
                if (!journalType.value) {
                journalType.value = 'Manual';
                }
            }
            const journalStatus = document.getElementById('journalStatus');
            if (journalStatus) {
                // Status is now automatically set to Draft by backend - no need to set it here
                journalStatus.value = 'Draft';
            }
        }

        // Load data after opening
        setTimeout(async () => {
            // Ensure transactions list is visible for entity modes
            if (this.currentEntity !== 'journal') {
                const transactionsList = document.querySelector('.transactions-list');
                if (transactionsList) {
                    transactionsList.classList.remove('transactions-list-hidden');
                    transactionsList.classList.add('transactions-list-visible');
                }
            }
            
            await this.loadTransactions();
        }, 100);

        // Ensure modal exists - create if it doesn't, or rebuild if structure is wrong
        let modal = document.getElementById('accountingModal');
        if (!modal) {
            this.createModalHTML();
        } else {
            // CRITICAL: Remove any duplicate modals created by showModal()
            const duplicateModals = document.querySelectorAll('.accounting-modal:not(#accountingModal)');
            duplicateModals.forEach(dupModal => {
                dupModal.remove();
            });
            
            // Remove any duplicate entityTransactionForm that might exist
            const duplicateForm = modal.querySelector('#entityTransactionForm');
            if (duplicateForm) {
                duplicateForm.remove();
            }
            
            // Check if modal has the correct structure
            const hasFormSection = modal.querySelector('.form-section');
            const hasTransactionsTab = document.getElementById('transactionsTab');
            if (!hasFormSection || !hasTransactionsTab) {
                modal.remove();
                this.createModalHTML();
            }
        }
        
        // Wait for DOM to update after creation/rebuild
        await new Promise(resolve => setTimeout(resolve, 50));
        modal = document.getElementById('accountingModal');
        
        if (!modal) {
            return;
        }
        
        // Wait for form elements to be in DOM using requestAnimationFrame for better timing
        await new Promise(resolve => requestAnimationFrame(resolve));
        await new Promise(resolve => requestAnimationFrame(resolve));
        
        let attempts = 0;
        let formSection = null;
        let transactionsTab = null;
        let form = null;
        let transactionsLayout = null;
        let formContentWrapper = null;
        
        while (attempts < 30) {
            // Try multiple ways to find elements
            formSection = modal?.querySelector('.form-section') || document.querySelector('#accountingModal .form-section');
            transactionsTab = document.getElementById('transactionsTab');
            form = document.getElementById('accountingTransactionForm');
            transactionsLayout = modal?.querySelector('.transactions-layout') || document.querySelector('#accountingModal .transactions-layout');
            formContentWrapper = modal?.querySelector('.form-content-wrapper') || document.querySelector('#accountingModal .form-content-wrapper');
            
            if (formSection && transactionsTab && form) {
                break;
            }
            
            await new Promise(resolve => requestAnimationFrame(resolve));
            attempts++;
        }
        
        // Final check and detailed logging - refresh element references
        if (modal) {
            formSection = modal.querySelector('.form-section') || document.querySelector('#accountingModal .form-section');
            transactionsTab = document.getElementById('transactionsTab');
            form = document.getElementById('accountingTransactionForm');
            transactionsLayout = modal.querySelector('.transactions-layout') || document.querySelector('#accountingModal .transactions-layout');
            formContentWrapper = modal.querySelector('.form-content-wrapper') || document.querySelector('#accountingModal .form-content-wrapper');
            
            // Form elements check - continue if found
        }
        
        if (modal) {
            // Simple show - like the example
            modal.classList.remove('accounting-modal-hidden');
            modal.classList.remove('accounting-modal-hidden');
            modal.classList.add('accounting-modal-visible');
            document.body.classList.add('body-no-scroll');
            
            // Initialize English date pickers
            setTimeout(() => {
                if (typeof window.initializeEnglishDatePickers === 'function') {
                    window.initializeEnglishDatePickers(modal);
                }
            }, 200);
            
            // Force form visibility immediately - use the elements we found
            // (formSection, transactionsTab, form, transactionsLayout, formContentWrapper are already defined above)
            
            // Ensure transactions tab is active and visible
            if (transactionsTab) {
                transactionsTab.classList.add('active', 'transactions-tab-visible');
            }
            
            // Ensure transactions layout is visible
            if (transactionsLayout) {
                transactionsLayout.classList.add('transactions-layout-visible');
            }
            
            if (formSection) {
                formSection.classList.add('form-section-visible');
            }
            if (formContentWrapper) {
                formContentWrapper.classList.add('form-content-wrapper-visible');
            }
            if (form) {
                form.classList.add('form-visible');
            }
            
            
            document.body.classList.add('body-no-scroll');
            
            // Always attach fresh handlers - ensure it's a button element
            let closeButton = modal.querySelector('.accounting-close');
            if (closeButton) {
                // If it's not a button, convert it to one
                if (closeButton.tagName !== 'BUTTON') {
                    const newBtn = document.createElement('button');
                    newBtn.type = 'button';
                    newBtn.className = closeButton.className;
                    newBtn.setAttribute('data-action', 'close-accounting-modal');
                    newBtn.setAttribute('title', closeButton.getAttribute('title') || 'Close');
                    newBtn.innerHTML = closeButton.innerHTML || '&times;';
                    newBtn.className = 'accounting-close';
                    closeButton.parentNode.replaceChild(newBtn, closeButton);
                    closeButton = newBtn;
                } else {
                    // Clone to remove all old handlers
                    const newCloseBtn = closeButton.cloneNode(true);
                    closeButton.parentNode.replaceChild(newCloseBtn, closeButton);
                    closeButton = newCloseBtn;
                }
                
                // Ensure button attributes to prevent navigation
                closeButton.setAttribute('type', 'button');
                closeButton.setAttribute('role', 'button');
                closeButton.setAttribute('data-action', 'close-accounting-modal');
                closeButton.removeAttribute('href');
                closeButton.removeAttribute('onclick');
                
                // Add multiple event types for maximum compatibility
                const self = this;
                const closeHandler = (e) => {
                    if (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        e.cancelBubble = true;
                        e.returnValue = false;
                    }
                    // Prevent any navigation
                    if (window.event) {
                        window.event.preventDefault();
                        window.event.stopPropagation();
                        window.event.returnValue = false;
                        window.event.cancelBubble = true;
                    }
                    self.handleClose();
                    return false;
                };
                
                // Use addEventListener - prevents navigation
                closeButton.addEventListener('click', closeHandler, true);
                closeButton.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    closeHandler(e);
                }, true);
                closeButton.addEventListener('mouseup', closeHandler, true);
                
            }
            
            // Always attach fresh backdrop handler - remove ALL old ones first
            if (this._backdropHandler) {
                modal.removeEventListener('click', this._backdropHandler, true);
                modal.removeEventListener('mousedown', this._backdropHandler, true);
                modal.removeEventListener('mouseup', this._backdropHandler, true);
            }
            
            // Create fresh handler every time
            const self = this;
            this._backdropHandler = (e) => {
                // Only close if clicking directly on backdrop (not on content)
                if (e.target === modal) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    e.cancelBubble = true;
                    e.returnValue = false;
                    // Double-check modal is still visible and not already closing
                    const checkModal = document.getElementById('accountingModal');
                    if (checkModal && !checkModal.classList.contains('accounting-modal-hidden') && !self.isClosing) {
                        self.handleClose();
                    }
                    return false;
                }
            };
            // Add to multiple event types for maximum reliability
            modal.addEventListener('click', this._backdropHandler, true);
            modal.addEventListener('mousedown', this._backdropHandler, true);
            modal.addEventListener('mouseup', this._backdropHandler, true);
            
            // Force visibility again after a brief delay to override any CSS that might be applied
            setTimeout(() => {
                if (modal) {
                    modal.classList.remove('accounting-modal-hidden');
                    modal.classList.add('accounting-modal-visible');
                    modal.classList.remove('accounting-modal-hidden');
                    
                    // Initialize English date pickers
                    setTimeout(() => {
                        if (typeof window.initializeEnglishDatePickers === 'function') {
                            window.initializeEnglishDatePickers(modal);
                        }
                    }, 200);
                    
                    // Re-apply field visibility based on mode
                    const journalFields = document.getElementById('journalFields');
                    const entityFields = document.getElementById('entityFields');
                    const transactionsList = modal.querySelector('.transactions-list');
                    if (this.currentEntity === 'journal') {
                        if (journalFields) {
                            journalFields.classList.remove('journal-fields-hidden');
                            journalFields.classList.add('journal-fields-visible');
                        }
                        if (entityFields) {
                            entityFields.classList.remove('entity-fields-visible');
                            entityFields.classList.add('entity-fields-hidden');
                        }
                        if (transactionsList) {
                            transactionsList.classList.remove('transactions-list-visible');
                            transactionsList.classList.add('transactions-list-hidden');
                        }
                    } else {
                        if (journalFields) {
                            journalFields.classList.remove('journal-fields-visible');
                            journalFields.classList.add('journal-fields-hidden');
                        }
                        if (entityFields) {
                            entityFields.classList.remove('entity-fields-hidden');
                            entityFields.classList.add('entity-fields-visible');
                        }
                        if (transactionsList) {
                            transactionsList.classList.remove('transactions-list-hidden');
                            transactionsList.classList.add('transactions-list-visible');
                        }
                    }
                    
                    // Force form section visibility
                    const formSection = modal.querySelector('.form-section');
                    const transactionsTab = document.getElementById('transactionsTab');
                    const transactionsLayout = modal.querySelector('.transactions-layout');
                    const formContentWrapper = modal.querySelector('.form-content-wrapper');
                    const form = document.getElementById('accountingTransactionForm');
                    
                    // Ensure transactions tab is active
                    if (transactionsTab) {
                        transactionsTab.classList.add('active', 'transactions-tab-visible');
                    }
                    
                    // Ensure transactions layout is visible
                    if (transactionsLayout) {
                        transactionsLayout.classList.add('transactions-layout-visible');
                    }
                    
                    if (formSection) {
                        formSection.classList.add('form-section-visible');
                    }
                    if (formContentWrapper) {
                        formContentWrapper.classList.add('form-content-wrapper-visible');
                    }
                    if (form) {
                        form.classList.add('form-visible');
                    }
                    
                    // Ensure tab button is active
                    const transactionsTabBtn = modal.querySelector('[data-tab="transactions"]');
                    if (transactionsTabBtn) {
                        transactionsTabBtn.classList.add('active');
                    }
                }
            }, 50);
            
            // Ensure all inputs are enabled and interactive - AGGRESSIVE FIX
            const forceEnableInputs = () => {
                const inputs = modal.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    // Remove all blocking attributes
                    input.disabled = false;
                    input.readOnly = false;
                    input.removeAttribute('readonly');
                    input.removeAttribute('disabled');
                    input.removeAttribute('contenteditable');
                    
                    // Special handling for number inputs (Debit/Credit) to allow clearing
                    if (input.type === 'number' && (input.id === 'journalDebitAmount' || input.id === 'journalCreditAmount')) {
                        // Remove min attribute to allow clearing the field
                        input.removeAttribute('min');
                        input.removeAttribute('max');
                        // Ensure field is fully editable
                        input.setAttribute('step', '0.01');
                        // Explicitly make editable
                        input.readOnly = false;
                        input.disabled = false;
                        input.removeAttribute('readonly');
                        input.removeAttribute('disabled');
                        // Force enable all interaction styles using CSS classes
                        input.classList.add('input-enabled');
                        input.classList.remove('input-disabled');
                    }
                    
                    // Force enable styles directly using CSS class
                    input.classList.add('input-enabled');
                    
                    // Ensure tabindex is set for keyboard navigation
                    if (!input.hasAttribute('tabindex')) {
                        input.setAttribute('tabindex', '0');
                    }
                });
            };
            
            // Run immediately and multiple times to catch any delayed disabling
            forceEnableInputs();
            setTimeout(forceEnableInputs, 10);
            setTimeout(forceEnableInputs, 50);
            setTimeout(forceEnableInputs, 100);
            setTimeout(forceEnableInputs, 200);
            setTimeout(forceEnableInputs, 500);
            
            // Additional fix specifically for Debit/Credit fields - ensure they're always editable
            const ensureDebitCreditEditable = () => {
                const debitField = document.getElementById('journalDebitAmount');
                const creditField = document.getElementById('journalCreditAmount');
                
                [debitField, creditField].forEach(field => {
                    if (field) {
                        field.removeAttribute('min');
                        field.removeAttribute('max');
                        field.removeAttribute('readonly');
                        field.removeAttribute('disabled');
                        field.readOnly = false;
                        field.disabled = false;
                        // Use CSS classes instead of inline styles
                        field.classList.add('field-enabled');
                        field.classList.remove('field-disabled');
                        field.classList.add('field-enabled');
                        field.classList.remove('field-disabled');
                    }
                });
            };
            
            // Run multiple times to ensure fields stay editable
            setTimeout(ensureDebitCreditEditable, 100);
            setTimeout(ensureDebitCreditEditable, 300);
            setTimeout(ensureDebitCreditEditable, 600);
            
            // Global keyboard handler that intercepts ALL keyboard events at document level
            // Store modal reference and last focused input
            const modalRef = modal;
            this.lastFocusedInput = null;
            
            // Track the last focused input in the modal (including SELECT)
            const trackFocus = (e) => {
                const target = e.target;
                if (modalRef && modalRef.contains(target) && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT')) {
                    this.lastFocusedInput = target;
                }
            };
            modalRef.addEventListener('focusin', trackFocus, true);
            this._focusTracker = trackFocus;
            
            const globalKeyboardHandler = (e) => {
                const activeElement = document.activeElement;
                const isInModal = activeElement && modalRef && modalRef.contains(activeElement);
                
                // AGGRESSIVE: If we have a last focused input and user is typing a printable character,
                // ALWAYS redirect to that input, regardless of what currently has focus
                // Only process keydown and keypress, NOT keyup (to avoid duplicate characters)
                // EXCEPT: Don't redirect if it's a SELECT element (let dropdowns work normally)
                if ((e.type === 'keydown' || e.type === 'keypress') && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey && this.lastFocusedInput && modalRef.contains(this.lastFocusedInput)) {
                    // Don't redirect if the last focused element is a SELECT (dropdown needs to work normally)
                    if (this.lastFocusedInput.tagName === 'SELECT') {
                        return; // Let browser handle select navigation
                    }
                    // If active element is NOT the input, redirect
                    if (activeElement !== this.lastFocusedInput) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        
                        // Focus the input
                        this.lastFocusedInput.focus();
                        
                        // Insert the character
                        const input = this.lastFocusedInput;
                        const start = input.selectionStart !== null ? input.selectionStart : input.value.length;
                        const end = input.selectionEnd !== null ? input.selectionEnd : input.value.length;
                        const currentValue = input.value || '';
                        
                        if (input.type === 'number') {
                            if (/[0-9.]/.test(e.key)) {
                                const newValue = currentValue.substring(0, start) + e.key + currentValue.substring(end);
                                if (!isNaN(parseFloat(newValue)) || newValue === '' || newValue === '.' || newValue === '-') {
                                    input.value = newValue;
                                    input.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                                    input.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                                    return false;
                                }
                            }
                        } else {
                            const newValue = currentValue.substring(0, start) + e.key + currentValue.substring(end);
                            input.value = newValue;
                            input.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                            input.dispatchEvent(new Event('change', { bubbles: true, cancelable: true }));
                            setTimeout(() => {
                                try {
                                    input.setSelectionRange(start + 1, start + 1);
                                } catch (err) {}
                            }, 0);
                            return false;
                        }
                    }
                }
                
                // Handle Backspace/Delete for redirected inputs too (only on keydown)
                // BUT: For number inputs, let browser handle it naturally
                if (e.type === 'keydown' && (e.key === 'Backspace' || e.key === 'Delete') && this.lastFocusedInput && modalRef.contains(this.lastFocusedInput) && activeElement !== this.lastFocusedInput) {
                    // If it's a number input, just focus it and let browser handle deletion
                    if (this.lastFocusedInput.type === 'number') {
                        this.lastFocusedInput.focus();
                        return true; // Allow default behavior
                    }
                    
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    
                    this.lastFocusedInput.focus();
                    const input = this.lastFocusedInput;
                    const start = input.selectionStart || 0;
                    const end = input.selectionEnd || 0;
                    const currentValue = input.value || '';
                    
                    if (e.key === 'Backspace' && (start > 0 || end > start)) {
                        const newValue = currentValue.substring(0, Math.max(0, start - 1)) + currentValue.substring(end);
                        input.value = newValue;
                        input.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                        setTimeout(() => {
                            try {
                                input.setSelectionRange(Math.max(0, start - 1), Math.max(0, start - 1));
                            } catch (err) {}
                        }, 0);
                    } else if (e.key === 'Delete' && (end < currentValue.length || end > start)) {
                        const newValue = currentValue.substring(0, start) + currentValue.substring(Math.min(currentValue.length, end + 1));
                        input.value = newValue;
                        input.dispatchEvent(new Event('input', { bubbles: true, cancelable: true }));
                        setTimeout(() => {
                            try {
                                input.setSelectionRange(start, start);
                            } catch (err) {}
                        }, 0);
                    }
                    return false;
                }
                
                // Only handle if an input in the modal is focused (only on keydown/keypress, not keyup)
                if ((e.type === 'keydown' || e.type === 'keypress') && isInModal && (activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA')) {
                    // Handle printable characters
                    if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        
                        const input = activeElement;
                        const start = input.selectionStart || input.value.length;
                        const end = input.selectionEnd || input.value.length;
                        const currentValue = input.value || '';
                        
                        // For number inputs, only allow digits and decimal
                        if (input.type === 'number') {
                            if (/[0-9.]/.test(e.key)) {
                                const newValue = currentValue.substring(0, start) + e.key + currentValue.substring(end);
                                if (!isNaN(parseFloat(newValue)) || newValue === '' || newValue === '.' || newValue === '-') {
                                    input.value = newValue;
                                    // Set cursor position
                                    setTimeout(() => {
                                        try {
                                            input.setSelectionRange(start + 1, start + 1);
                                        } catch (err) {
                                            // Ignore selection errors for number inputs
                                        }
                                    }, 0);
                                    input.dispatchEvent(new Event('input', { bubbles: true }));
                                    input.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                            }
                        } else {
                            // For text inputs, allow all characters
                            const newValue = currentValue.substring(0, start) + e.key + currentValue.substring(end);
                            input.value = newValue;
                            // Set cursor position
                            setTimeout(() => {
                                try {
                                    input.setSelectionRange(start + 1, start + 1);
                                } catch (err) {
                                    // Ignore selection errors
                                }
                            }, 0);
                            // Trigger input event
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        return false;
                    }
                    
                    // Handle special keys (only on keydown)
                    // For number inputs (Debit/Credit), let browser handle Backspace/Delete naturally
                    if (e.type === 'keydown' && e.key === 'Backspace' && activeElement.type !== 'number') {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        const input = activeElement;
                        const start = input.selectionStart || 0;
                        const end = input.selectionEnd || 0;
                        if (start > 0 || end > start) {
                            const currentValue = input.value || '';
                            const newValue = currentValue.substring(0, Math.max(0, start - 1)) + currentValue.substring(end);
                            input.value = newValue;
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                            setTimeout(() => {
                                try {
                                    input.setSelectionRange(Math.max(0, start - 1), Math.max(0, start - 1));
                                } catch (err) {}
                            }, 0);
                        }
                        return false;
                    }
                    
                    if (e.type === 'keydown' && e.key === 'Delete' && activeElement.type !== 'number') {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        const input = activeElement;
                        const start = input.selectionStart || 0;
                        const end = input.selectionEnd || 0;
                        const currentValue = input.value || '';
                        if (end < currentValue.length || end > start) {
                            const newValue = currentValue.substring(0, start) + currentValue.substring(Math.min(currentValue.length, end + 1));
                            input.value = newValue;
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                            setTimeout(() => {
                                try {
                                    input.setSelectionRange(start, start);
                                } catch (err) {}
                            }, 0);
                        }
                        return false;
                    }
                    
                    // For number inputs, don't intercept Backspace/Delete - let browser handle it
                    if (e.type === 'keydown' && (e.key === 'Backspace' || e.key === 'Delete') && activeElement.type === 'number') {
                        // Don't prevent default - let browser handle deletion naturally
                        return true; // Allow default behavior
                    }
                }
            };
            
            // Add at CAPTURE phase to intercept BEFORE anything else - use BOTH phases
            // Also add to window and document separately
            window.addEventListener('keydown', globalKeyboardHandler, true);
            window.addEventListener('keydown', globalKeyboardHandler, false);
            window.addEventListener('keypress', globalKeyboardHandler, true);
            window.addEventListener('keypress', globalKeyboardHandler, false);
            
            document.addEventListener('keydown', globalKeyboardHandler, true); // Capture
            document.addEventListener('keydown', globalKeyboardHandler, false); // Bubble
            document.addEventListener('keypress', globalKeyboardHandler, true); // Capture
            document.addEventListener('keypress', globalKeyboardHandler, false); // Bubble
            
            // Store for cleanup
            this._globalKeyboardHandlers = [
                { type: 'keydown', handler: globalKeyboardHandler, capture: true, target: window },
                { type: 'keydown', handler: globalKeyboardHandler, capture: false, target: window },
                { type: 'keypress', handler: globalKeyboardHandler, capture: true, target: window },
                { type: 'keypress', handler: globalKeyboardHandler, capture: false, target: window },
                { type: 'keydown', handler: globalKeyboardHandler, capture: true, target: document },
                { type: 'keydown', handler: globalKeyboardHandler, capture: false, target: document },
                { type: 'keypress', handler: globalKeyboardHandler, capture: true, target: document },
                { type: 'keypress', handler: globalKeyboardHandler, capture: false, target: document }
            ];
            
            // Ensure inputs are truly interactive - add event listeners directly
            setTimeout(() => {
                const form = modal.querySelector('#accountingTransactionForm');
                if (form) {
                    const inputs = form.querySelectorAll('input, textarea, select');
                    inputs.forEach(input => {
                        // Ensure it's enabled
                        input.disabled = false;
                        input.readOnly = false;
                        input.removeAttribute('disabled');
                        input.removeAttribute('readonly');
                        
                        // Force styles
                        // Use CSS classes instead of inline styles
                        input.classList.add('input-enabled');
                        input.classList.remove('input-disabled');
                        
                        // Add focus highlight and track as last focused
                        const self = this; // Store reference to modal instance
                        input.addEventListener('focus', function() {
                            this.classList.add('input-focused');
                            self.lastFocusedInput = this; // Update last focused
                        }, { once: false });
                        
                        input.addEventListener('input', function(e) {
                        }, { once: false });
                        
                        // For SELECT elements, don't add click handler that forces focus (let browser handle it)
                        if (input.tagName !== 'SELECT') {
                            input.addEventListener('click', function(e) {
                                // Only focus if not already focused and not prevented
                                if (document.activeElement !== this) {
                                    this.focus();
                                }
                            }, { once: false });
                        } else {
                            // For SELECT elements, just ensure they're enabled and track focus
                            input.addEventListener('focus', function() {
                                self.lastFocusedInput = this;
                            }, { once: false });
                            
                            // Don't prevent default click behavior on SELECT
                            input.addEventListener('mousedown', function(e) {
                                // Allow native select dropdown behavior
                            }, { once: false, passive: true });
                        }
                    });
                }
            }, 200);
            
            // Continuously monitor (but less aggressively)
            const monitorInterval = setInterval(() => {
                const inputs = modal.querySelectorAll('input, select, textarea');
                let needsFix = false;
                inputs.forEach(input => {
                    if (input.disabled || input.readOnly || input.classList.contains('input-disabled')) {
                        needsFix = true;
                    }
                });
                if (needsFix) {
                    forceEnableInputs();
                }
            }, 500);
            
            // Store interval to clear on close
            this._monitorInterval = monitorInterval;
        }
        
        // Generate reference number after modal is fully set up
        setTimeout(() => {
            this.generateReferenceNumber();
        }, 300);
        
        // Attach form event listeners
        this.attachFormEventListeners();
        
        this.loadEntityData();
    }

    attachFormEventListeners() {
        const form = document.getElementById('accountingTransactionForm');
        const cancelBtn = document.getElementById('cancelTransactionBtn') || document.querySelector('[data-action="cancel-transaction"]');
        const saveBtn = document.getElementById('saveTransactionBtn') || document.querySelector('#accountingTransactionForm button[type="submit"]');
        
        if (form) {
            // Remove old listeners first
            const newForm = form.cloneNode(true);
            form.parentNode.replaceChild(newForm, form);
            const freshForm = document.getElementById('accountingTransactionForm');
            
            if (freshForm) {
                freshForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.handleTransactionSubmit(e);
                    return false;
                }, { capture: true });
            }
        }
        
        // Attach submit handler directly to button as backup
        if (saveBtn) {
            // Remove old listeners
            const newSaveBtn = saveBtn.cloneNode(true);
            saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
            const freshSaveBtn = document.getElementById('saveTransactionBtn') || document.querySelector('#accountingTransactionForm button[type="submit"]');
            
            if (freshSaveBtn) {
                freshSaveBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    
                    try {
                        // Check if we're in edit mode
                        const editIdField = document.getElementById('transactionEditId');
                        const isEditMode = editIdField && editIdField.value;
                        
                        // Show confirmation alert for saving transactions
                        const result = await this.showAlert(
                            isEditMode ? 'Update Transaction' : 'Save Transaction',
                            isEditMode 
                                ? 'Are you sure you want to update this transaction? This will modify the transaction in the accounting system.'
                                : 'Are you sure you want to save this transaction? This will record the transaction in the accounting system.',
                            'info',
                            [
                                { text: 'Cancel', action: 'cancel', class: 'secondary', icon: 'fas fa-times' },
                                { text: isEditMode ? 'Update' : 'Save', action: 'save', class: 'primary', icon: 'fas fa-save' }
                            ]
                        );
                        
                        if (result !== 'save') {
                            return false;
                        }
                        
                        // Directly call handleTransactionSubmit instead of dispatching event
                        await this.handleTransactionSubmit(e);
                    } catch (error) {
                        this.showError('Failed to save: ' + error.message);
                    }
                    return false;
                }, { capture: true });
                
                // Add event listener for save button
                freshSaveBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    await this.handleTransactionSubmit(e);
                    return false;
                });
            }
        }
        
        if (cancelBtn) {
            // Remove old listeners
            const newCancelBtn = cancelBtn.cloneNode(true);
            cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
            const freshCancelBtn = document.getElementById('cancelTransactionBtn') || document.querySelector('[data-action="cancel-transaction"]');
            
            if (freshCancelBtn) {
                freshCancelBtn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    
                    // COMPLETELY DISABLED - Always allow cancel without alerts
                    // DISABLED: All alert checks removed
                            this.resetForm();
                            this.forceClose();
                    return false;
                }, { capture: true });
            }
        }
    }

    // Modern Alert System for Accounting Form
    showAlert(title, message, type = 'info', buttons = []) {
        const isOffline = !navigator.onLine;
        
        // CRITICAL: If offline, NEVER show unsaved changes alerts
        if (isOffline) {
            // Check if this is an unsaved changes alert
            const titleMatches = title && (title.includes('Unsaved Changes') || title.includes('unsaved changes') || 
                title.includes('Discard Changes') || title.includes('Close Form') ||
                title.includes('Close Accounting Modal') || title.includes('Cancel Changes'));
            const messageMatches = message && (message.includes('unsaved changes') || message.includes('Unsaved Changes') ||
                message.includes('close without saving') || message.includes('discard') ||
                message.includes('Any unsaved changes'));
            const isUnsavedAlert = titleMatches || messageMatches;
            
            if (isUnsavedAlert) {
                // Auto-confirm (allow close) when offline
                return Promise.resolve('close');
            }
        }
        
        return new Promise((resolve) => {
            // Remove any existing alerts first
            const existingAlerts = document.querySelectorAll('.accounting-alert-overlay');
            existingAlerts.forEach(alert => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            });
            
            const overlay = document.createElement('div');
            overlay.className = 'accounting-alert-overlay';
            
            // Determine icon and icon class based on type
            let iconClass = 'fas fa-info-circle';
            let iconTypeClass = '';
            if (type === 'error' || type === 'danger') {
                iconClass = 'fas fa-exclamation-circle';
                iconTypeClass = 'danger';
            } else if (type === 'warning') {
                iconClass = 'fas fa-exclamation-triangle';
                iconTypeClass = 'warning';
            } else if (type === 'success') {
                iconClass = 'fas fa-check-circle';
                iconTypeClass = 'success';
            }
            
            // Default buttons if none provided
            if (buttons.length === 0) {
                buttons = [
                    { text: 'OK', action: 'ok', class: 'primary', icon: 'fas fa-check' }
                ];
            }
            
            const buttonsHTML = buttons.map(btn => {
                const icon = btn.icon ? `<i class="${btn.icon}"></i>` : '';
                return `<button class="accounting-alert-btn ${btn.class || 'primary'}" data-action="${btn.action}">
                    ${icon}
                    <span>${btn.text}</span>
                </button>`;
            }).join('');
            
            overlay.innerHTML = `
                <div class="accounting-alert-container">
                    <div class="accounting-alert-header">
                        <div class="accounting-alert-icon ${iconTypeClass}">
                            <i class="${iconClass}"></i>
                        </div>
                        <h3 class="accounting-alert-title">${title}</h3>
                    </div>
                    <div class="accounting-alert-body">
                        <p class="accounting-alert-message">${message}</p>
                    </div>
                    <div class="accounting-alert-footer">
                        ${buttonsHTML}
                    </div>
                </div>
            `;
            
            document.body.appendChild(overlay);
            
            // Trigger animation
            setTimeout(() => {
                overlay.classList.add('show');
            }, 10);
            
            // Handle button clicks
            overlay.querySelectorAll('.accounting-alert-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const action = btn.getAttribute('data-action');
                    overlay.classList.remove('show');
                    setTimeout(() => {
                        overlay.remove();
                        resolve(action);
                    }, 300);
                });
            });
            
            // Close on overlay click (outside modal)
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('show');
                    setTimeout(() => {
                        overlay.remove();
                        resolve('cancel');
                    }, 300);
                }
            });
            
            // Close on Escape key
            const escapeHandler = (e) => {
                if (e.key === 'Escape') {
                    overlay.classList.remove('show');
                    setTimeout(() => {
                        overlay.remove();
                        document.removeEventListener('keydown', escapeHandler);
                        resolve('cancel');
                    }, 300);
                }
            };
            document.addEventListener('keydown', escapeHandler);
        });
    }

    // Check if form has unsaved changes
    hasUnsavedChanges() {
        // COMPLETELY DISABLED - Always return false to prevent alerts
        // CRITICAL: If offline, NEVER return true
        if (!navigator.onLine) {
            return false;
        }
        return false;
        
        /* DISABLED CODE
        const form = document.getElementById('accountingTransactionForm');
        if (!form) return false;
        
        const type = form.querySelector('#transactionType')?.value;
        const debitField = form.querySelector('#transactionDebit');
        const creditField = form.querySelector('#transactionCredit');
        const debit = debitField ? parseFloat(debitField.value) || 0 : 0;
        const credit = creditField ? parseFloat(creditField.value) || 0 : 0;
        const amount = Math.max(debit, credit);
        const description = form.querySelector('#transactionDescription')?.value?.trim();
        
        return !!(type || amount > 0 || description);
        */
    }

    // Show modern confirmation dialog
    async showConfirmDialog() {
        return await this.showAlert(
            'Close Form?',
            'You have unsaved changes. Are you sure you want to close? Any unsaved changes will be lost.',
            'warning',
            [
                { text: 'Cancel', action: 'cancel', class: 'secondary', icon: 'fas fa-times' },
                { text: 'Close', action: 'close', class: 'danger', icon: 'fas fa-times-circle' }
            ]
        ).then(result => result === 'close');
    }

    // Handle close with confirmation
    async handleClose() {
        if (this.isClosing) {
            return;
        }
        
        // Double-check modal is actually visible
        const modal = document.getElementById('accountingModal');
        if (!modal) {
            return;
        }
        
        if (modal.classList.contains('accounting-modal-hidden')) {
            return;
        }
        
        // COMPLETELY DISABLED - Always close without alerts
        // DISABLED: All alert checks removed
        // CRITICAL: If offline, NEVER show alerts
        if (!navigator.onLine) {
            this.close();
                return;
        }
        
        this.close();
    }

    close() {
        // Simple close - like the example
        this.forceClose();
    }

    switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        const activeBtn = document.querySelector(`[data-tab="${tabName}"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }

        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.remove('active');
        });
        const activeTab = document.getElementById(`${tabName}Tab`);
        if (activeTab) {
            activeTab.classList.add('active');
        }

        // Load tab-specific data
        if (tabName === 'transactions') {
            this.loadTransactions();
        }
    }

    async loadEntityData() {
        try {
            await this.loadTransactions();
        } catch (error) {
            this.showError('Failed to load financial data: ' + error.message);
        }
    }

    async loadTransactions() {
        // For journal mode, load journal entries instead
        if (this.currentEntity === 'journal') {
            await this.loadJournalEntries();
            return;
        }

        if (!this.currentEntity) {
            return;
        }
        
        // Allow entityId to be 0 for "all entities" but check if null/undefined
        if (this.currentEntityId === null || this.currentEntityId === undefined) {
            // Try to get entityId from URL or context
            const currentPath = window.location.pathname.toLowerCase();
            let detectedId = null;
            
            // Try to extract ID from URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const idParam = urlParams.get('id') || urlParams.get('entity_id');
            if (idParam) {
                detectedId = parseInt(idParam);
            }
            
            // If still no ID, try to get from page context
            if (!detectedId) {
                const pageIdMatch = currentPath.match(/(agent|subagent|worker|hr)[_-]?id[=:](\d+)/i);
                if (pageIdMatch) {
                    detectedId = parseInt(pageIdMatch[2]);
                }
            }
            
            if (detectedId) {
                this.currentEntityId = detectedId;
            } else {
                // Show empty state instead of hiding everything
                const emptyEl = document.getElementById('transactionsEmpty');
                const tableContainer = document.querySelector('.transactions-list .table-container');
                if (emptyEl) {
                    emptyEl.classList.remove('accounting-empty-hidden');
                    emptyEl.classList.add('accounting-empty-visible');
                }
                if (tableContainer) {
                    tableContainer.classList.add('accounting-loading-hidden');
                }
                return;
            }
        }

        const loadingEl = document.getElementById('transactionsLoading');
        const emptyEl = document.getElementById('transactionsEmpty');
        const tableEl = document.getElementById('accountingTransactionsTable');
        const tableContainer = tableEl?.closest('.table-container');
        
        // Show loading, hide empty and table
        if (loadingEl) {
            loadingEl.classList.remove('accounting-loading-hidden');
            loadingEl.classList.add('accounting-loading-visible');
        }
        if (emptyEl) {
            emptyEl.classList.remove('accounting-empty-visible');
            emptyEl.classList.add('accounting-empty-hidden');
        }
        if (tableEl) tableEl.innerHTML = '';
        if (tableContainer) {
            tableContainer.classList.add('accounting-loading-hidden');
            tableContainer.classList.remove('accounting-table-visible');
        }

        try {
            // Ensure entity_type is lowercase for API
            const normalizedEntityType = this.currentEntity ? this.currentEntity.toLowerCase() : null;
            const url = `${this.apiBase}?entity_type=${normalizedEntityType}&entity_id=${this.currentEntityId}`;
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();

            // Hide loading
            if (loadingEl) {
                loadingEl.classList.remove('accounting-loading-visible');
                loadingEl.classList.add('accounting-loading-hidden');
            }

            if (data.success && data.transactions && data.transactions.length > 0) {
                this.renderTransactionsTable(data.transactions);
                // Show table, hide empty and loading
                if (tableContainer) {
                    tableContainer.classList.remove('accounting-loading-hidden', 'accounting-empty-hidden');
                    tableContainer.classList.add('accounting-table-visible');
                }
                if (emptyEl) {
                    emptyEl.classList.remove('accounting-empty-visible');
                    emptyEl.classList.add('accounting-empty-hidden');
                }
                // Ensure transactions-list section is visible
                const transactionsList = tableEl?.closest('.transactions-list');
                if (transactionsList) {
                    transactionsList.classList.remove('transactions-list-hidden');
                    transactionsList.classList.add('transactions-list-visible');
                }
                // Ensure transactions-layout is visible
                const transactionsLayout = tableEl?.closest('.transactions-layout');
                if (transactionsLayout) {
                    transactionsLayout.classList.add('transactions-layout-visible');
                }
            } else {
                // Show empty state, but keep transactions-list visible
                if (tableContainer) {
                    tableContainer.classList.add('accounting-loading-hidden');
                    tableContainer.classList.remove('accounting-table-visible');
                }
                if (emptyEl) {
                    emptyEl.classList.remove('accounting-empty-hidden');
                    emptyEl.classList.add('accounting-empty-visible');
                }
                // Keep transactions-list visible even when empty
                const transactionsList = tableEl?.closest('.transactions-list');
                if (transactionsList) {
                    transactionsList.classList.remove('transactions-list-hidden');
                    transactionsList.classList.add('transactions-list-visible');
                }
            }
        } catch (error) {
            if (loadingEl) {
                loadingEl.classList.remove('accounting-loading-visible');
                loadingEl.classList.add('accounting-loading-hidden');
            }
            this.showError('Failed to load transactions: ' + error.message);
        }
    }

    renderTransactionsTable(transactions) {
        const tbody = document.getElementById('accountingTransactionsTable');
        if (!tbody) {
            return;
        }

        // Ensure table container is visible
        const tableContainer = tbody.closest('.table-container');
        const transactionsList = tbody.closest('.transactions-list');
        const transactionsLayout = tbody.closest('.transactions-layout');
        
        // CRITICAL: Always show transactions-list for entity modes
        if (transactionsList) {
            transactionsList.classList.remove('transactions-list-hidden');
            transactionsList.classList.add('transactions-list-visible');
        }
        
        if (transactionsLayout) {
            transactionsLayout.classList.add('transactions-layout-visible');
        }
        
        if (tableContainer) {
            tableContainer.classList.remove('accounting-loading-hidden', 'accounting-empty-hidden');
            tableContainer.classList.add('accounting-table-visible');
        }

        tbody.innerHTML = transactions.map(transaction => {
            // Get entity type and id from transaction or use current values
            const entityType = transaction.entity_type || this.currentEntity;
            const entityId = transaction.entity_id || this.currentEntityId;
            const transactionId = transaction.id || transaction.entity_transaction_id;
            
            const amount = parseFloat(transaction.total_amount || transaction.amount || 0);
            const isIncome = (transaction.transaction_type || transaction.type) === 'Income';
            const debitAmount = isIncome ? amount : 0;
            const creditAmount = isIncome ? 0 : amount;
            
            return `
            <tr>
                <td>${this.formatDate(transaction.transaction_date || transaction.date)}</td>
                <td>
                    <span class="type-badge ${(transaction.transaction_type || transaction.type || '').toLowerCase()}">
                        ${transaction.transaction_type || transaction.type || 'Unknown'}
                    </span>
                </td>
                <td>${this.escapeHtml(transaction.description || '')}</td>
                <td><span class="category-badge">${transaction.category || 'other'}</span></td>
                <td class="debit-cell">${debitAmount > 0 ? this.formatCurrency(debitAmount, transaction.currency || (localStorage.getItem('accounting_default_currency') || 'SAR')) : '<span class="text-muted">-</span>'}</td>
                <td class="credit-cell">${creditAmount > 0 ? this.formatCurrency(creditAmount, transaction.currency || (localStorage.getItem('accounting_default_currency') || 'SAR')) : '<span class="text-muted">-</span>'}</td>
                <td>
                    <span class="status-badge ${(transaction.status || 'Posted').toLowerCase()}">
                        ${transaction.status || 'Posted'}
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <a href="${this.getPageUrl('accounting.php')}?entity=${entityType}&entity_id=${entityId}" 
                           class="btn btn-sm btn-info" 
                           target="_blank" 
                           title="View in Accounting System">
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        <button class="btn btn-sm btn-primary" data-action="edit-transaction" data-transaction-id="${transactionId}" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" data-action="delete-transaction" data-transaction-id="${transactionId}" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
        }).join('');
        
    }


    async loadJournalAccounts() {
        // Wait for journalAccount element to be available with retry logic
        let accountSelect = document.getElementById('journalAccount');
        let attempts = 0;
        while (!accountSelect && attempts < 20) {
            await new Promise(resolve => setTimeout(resolve, 100));
            accountSelect = document.getElementById('journalAccount');
            attempts++;
        }
        
        if (!accountSelect) {
            return;
        }
        
        try {
            const response = await fetch(this.getApiUrl('accounts.php') + '?is_active=1');
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.accounts && data.accounts.length > 0) {
                accountSelect.innerHTML = '<option value="">Select Account</option>';
                data.accounts.forEach(account => {
                    const option = document.createElement('option');
                    option.value = account.id;
                    const displayText = account.account_code 
                        ? `${account.account_code} - ${account.account_name || 'N/A'}`.trim()
                        : account.account_name || 'N/A';
                    option.textContent = displayText;
                    accountSelect.appendChild(option);
                });
            } else {
                accountSelect.innerHTML = '<option value="">No accounts found</option>';
            }
        } catch (error) {
            if (accountSelect) {
                accountSelect.innerHTML = '<option value="">Failed to load accounts</option>';
            }
        }
    }

    async loadTransactionAccounts() {
        const accountSelect = document.getElementById('transactionAccount');
        if (!accountSelect) return;
        
        try {
            const response = await fetch(this.getApiUrl('accounts.php') + '?is_active=1');
            const data = await response.json();
            
            if (data.success && data.accounts && data.accounts.length > 0) {
                accountSelect.innerHTML = '<option value="">Select Account</option>';
                    data.accounts.forEach(account => {
                        const option = document.createElement('option');
                        option.value = account.id;
                        option.textContent = `${account.account_code || ''} ${account.account_name || 'N/A'}`.trim();
                    accountSelect.appendChild(option);
                    });
            } else {
                accountSelect.innerHTML = '<option value="">No accounts available</option>';
            }
        } catch (error) {
            accountSelect.innerHTML = '<option value="">Error loading accounts</option>';
        }
    }

    async loadJournalEntities(entityTypeFilter = 'all') {
        // Try journalEntitySelect first (for journal entry modal), then journalEntity, then entitySelect (for entity transaction modal)
        const entitySelect = document.getElementById('journalEntitySelect') ||
                             document.getElementById('journalEntity') || 
                             document.getElementById('entitySelect');
        if (!entitySelect) {
            // Silently return if neither select exists - this is normal in some contexts
            return;
        }
        
        // Store all entities for filtering
        if (!this.allJournalEntities) {
            // Show loading state
            entitySelect.innerHTML = '<option value="">Loading entities...</option>';
            entitySelect.disabled = true;
        
        try {
            // Load all entities (agents, subagents, workers, hr) without filtering by type
            const response = await fetch(this.getApiUrl('entities.php') + '?include_inactive=0');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
            const data = await response.json();
            
                
                if (data.success && data.entities && data.entities.length > 0) {
                    // Store all entities for filtering
                    this.allJournalEntities = data.entities;
                } else {
                    entitySelect.innerHTML = '<option value="">No entities available</option>';
                    entitySelect.disabled = false;
                    return;
                }
            } catch (error) {
                entitySelect.innerHTML = '<option value="">Error loading entities</option>';
                entitySelect.disabled = false;
                return;
                    }
        }
        
        // Filter entities based on selected type
        let filteredEntities = this.allJournalEntities;
        if (entityTypeFilter && entityTypeFilter !== 'all') {
            filteredEntities = this.allJournalEntities.filter(entity => 
                (entity.entity_type || '').toLowerCase() === entityTypeFilter.toLowerCase()
            );
                }
                
        // Populate dropdown with filtered entities
        entitySelect.innerHTML = '<option value="">Select Entity (Optional)</option>';
        
        if (filteredEntities.length > 0) {
            // Sort alphabetically by name
            filteredEntities.sort((a, b) => {
                const nameA = (a.name || a.display_name || '').toLowerCase();
                const nameB = (b.name || b.display_name || '').toLowerCase();
                return nameA.localeCompare(nameB);
            });
            
            // Add entities to dropdown
            filteredEntities.forEach(entity => {
                        const option = document.createElement('option');
                const type = (entity.entity_type || '').toLowerCase();
                option.value = `${type}:${entity.id}`;
                option.textContent = entity.name || entity.display_name || `${type.charAt(0).toUpperCase() + type.slice(1)} #${entity.id}`;
                entitySelect.appendChild(option);
                    });
            
            // Re-enable the select
            entitySelect.disabled = false;
            } else {
            entitySelect.innerHTML = `<option value="">No ${entityTypeFilter === 'all' ? 'entities' : entityTypeFilter + ' entities'} available</option>`;
            entitySelect.disabled = false;
        }
    }

    async loadJournalEntries() {
        const loadingEl = document.getElementById('transactionsLoading');
        const emptyEl = document.getElementById('transactionsEmpty');
        const tableEl = document.getElementById('accountingTransactionsTable');
        const tableContainer = tableEl?.closest('.table-container');
        
        if (loadingEl) {
            loadingEl.classList.remove('accounting-loading-hidden');
            loadingEl.classList.add('accounting-loading-visible');
        }
        if (emptyEl) {
            emptyEl.classList.remove('accounting-empty-visible');
            emptyEl.classList.add('accounting-empty-hidden');
        }
        if (tableEl) tableEl.innerHTML = '';
        if (tableContainer) {
            tableContainer.classList.add('accounting-loading-hidden');
            tableContainer.classList.remove('accounting-table-visible');
        }

        try {
            const response = await fetch(this.getApiUrl('journal-entries.php'));
            const data = await response.json();
            
            if (loadingEl) {
                loadingEl.classList.remove('accounting-loading-visible');
                loadingEl.classList.add('accounting-loading-hidden');
            }

            if (data.success && data.entries && data.entries.length > 0) {
                this.renderJournalEntriesTable(data.entries);
                if (tableContainer) {
                    tableContainer.classList.remove('accounting-loading-hidden');
                    tableContainer.classList.add('accounting-table-visible');
                }
            } else {
                if (emptyEl) {
                    emptyEl.classList.remove('accounting-empty-hidden');
                    emptyEl.classList.add('accounting-empty-visible');
                }
            }
        } catch (error) {
            if (loadingEl) {
                loadingEl.classList.remove('accounting-loading-visible');
                loadingEl.classList.add('accounting-loading-hidden');
            }
            if (emptyEl) {
                emptyEl.classList.remove('accounting-empty-hidden');
                emptyEl.classList.add('accounting-empty-visible');
            }
        }
    }

    renderJournalEntriesTable(entries) {
        const tbody = document.getElementById('accountingTransactionsTable');
        if (!tbody) return;
        
        tbody.innerHTML = entries.map(entry => {
            const debit = parseFloat(entry.total_debit || 0);
            const credit = parseFloat(entry.total_credit || 0);
            
            return `
                <tr>
                    <td>${this.formatDate(entry.entry_date)}</td>
                    <td>Journal Entry</td>
                    <td>${entry.description || 'N/A'}</td>
                    <td>-</td>
                    <td class="debit-cell">${debit > 0 ? this.formatCurrency(debit, entry.currency || (localStorage.getItem('accounting_default_currency') || 'SAR')) : '<span class="text-muted">-</span>'}</td>
                    <td class="credit-cell">${credit > 0 ? this.formatCurrency(credit, entry.currency || (localStorage.getItem('accounting_default_currency') || 'SAR')) : '<span class="text-muted">-</span>'}</td>
                    <td><span class="status-badge status-${(entry.status || 'Posted').toLowerCase()}">${entry.status || 'Posted'}</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-sm btn-primary" data-action="edit-transaction" data-transaction-id="${entry.id}" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" data-action="delete-transaction" data-transaction-id="${entry.id}" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    async handleTransactionSubmit(e) {
        let submitBtn = null;
        
        try {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
            }
            
            if (this.isSubmitting) {
                return;
            }
            
            this.isSubmitting = true;
            
            const form = document.getElementById('accountingTransactionForm');
            if (!form) {
                this.isSubmitting = false;
                this.showError('Form not found. Please refresh the page.');
                return;
            }

            submitBtn = document.getElementById('saveTransactionBtn') || form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                const originalHTML = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                submitBtn.dataset.originalHTML = originalHTML;
            }

            // Handle journal entry submission
            if (this.currentEntity === 'journal') {
            const journalAccount = document.getElementById('journalAccount');
            const journalDebit = document.getElementById('journalDebitAmount');
            const journalCredit = document.getElementById('journalCreditAmount');
            const descriptionField = document.getElementById('journalDescription');
            const dateField = document.getElementById('journalDate');
            const currencyField = document.getElementById('journalCurrency');
            const referenceField = document.getElementById('transactionReference');
            const entityField = document.getElementById('journalEntity');

            if (!journalAccount?.value) {
                this.showError('Please select an account');
                this.reEnableForm(submitBtn);
                return;
            }
            if (!descriptionField?.value.trim()) {
                this.showError('Please enter a description');
                this.reEnableForm(submitBtn);
                return;
            }
            if (!dateField?.value) {
                this.showError('Please select a date');
                this.reEnableForm(submitBtn);
                return;
            }
            if (!currencyField?.value) {
                this.showError('Please select a currency');
                this.reEnableForm(submitBtn);
                return;
            }

            const debitAmount = parseFloat(journalDebit?.value || 0);
            const creditAmount = parseFloat(journalCredit?.value || 0);
            
            if (debitAmount <= 0 && creditAmount <= 0) {
                this.showError('Please enter either a debit or credit amount');
                this.reEnableForm(submitBtn);
                return;
            }
            
            // API requirement: Cannot have both debit and credit amounts
            if (debitAmount > 0 && creditAmount > 0) {
                const errorMsg = 'Please enter either a debit OR a credit amount, not both. The API does not allow both amounts in a single entry.';
                this.showError(errorMsg);
                this.reEnableForm(submitBtn);
                return;
            }

            // Parse entity field if selected (format: "entity_type:entity_id")
            let entityType = null;
            let entityId = null;
            if (entityField?.value) {
                const entityParts = entityField.value.split(':');
                if (entityParts.length === 2) {
                    entityType = entityParts[0];
                    entityId = parseInt(entityParts[1]);
                }
            }

            // Check if we're in edit mode
            const editIdField = document.getElementById('transactionEditId');
            const isEditMode = editIdField && editIdField.value;
            
            // Get type field value
            const typeField = document.getElementById('journalType');
            const entryTypeValue = typeField?.value || 'Manual';
            
            const formData = {
                entry_date: dateField.value,
                description: descriptionField.value.trim(),
                account_id: parseInt(journalAccount.value),
                debit: debitAmount > 0 ? debitAmount : 0,
                credit: creditAmount > 0 ? creditAmount : 0,
                currency: currencyField.value,
                entry_type: entryTypeValue,
                reference: referenceField?.value.trim() || null
            };

            // Add entity information if available
            if (entityType && entityId) {
                formData.entity_type = entityType;
                formData.entity_id = entityId;
            }

            try {
                const method = isEditMode ? 'PUT' : 'POST';
                const url = this.getApiUrl('journal-entries.php') + (isEditMode ? `?id=${editIdField.value}` : '');
                
                const response = await fetch(url, {
                    method: method,
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                
                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`HTTP error! status: ${response.status} - ${errorText.substring(0, 200)}`);
                }

                const data = await response.json();
                
                if (data.success) {
                    // Refresh history if UnifiedHistory modal is open
                    if (window.unifiedHistory) {
                        await window.unifiedHistory.refreshIfOpen();
                    }
                    
                    this.showSuccess(`Journal entry ${isEditMode ? 'updated' : 'created'} successfully!`);
                    
                    // Check if General Ledger modal is open before closing accounting modal
                    const generalLedgerModal = document.getElementById('generalLedgerModal');
                    const isGeneralLedgerOpen = generalLedgerModal && 
                        !generalLedgerModal.classList.contains('accounting-modal-hidden');
                    
                    // Close modal immediately to avoid showing form reset
                    this.close();
                    
                    // Reset form after modal closes (so user doesn't see empty form)
                    setTimeout(() => {
                    this.resetForm();
                    }, 100);
                    
                    // Refresh modal's journal entries list
                    await this.loadJournalEntries();
                    
                    // Refresh General Ledger if it's open - wait a bit for database commit
                    await new Promise(resolve => setTimeout(resolve, 800));
                    
                    // First, refresh the General Ledger modal table if it's open
                    // Check for General Ledger modal by ID (it uses 'generalLedgerModal' ID)
                    const generalLedgerModalAfterClose = document.getElementById('generalLedgerModal');
                    const isGeneralLedgerStillOpen = generalLedgerModalAfterClose && 
                        !generalLedgerModalAfterClose.classList.contains('accounting-modal-hidden');
                    
                    if (window.accountingSystem && typeof window.accountingSystem.loadModalJournalEntries === 'function') {
                        if (isGeneralLedgerStillOpen) {
                            await window.accountingSystem.loadModalJournalEntries();
                            
                            // Ensure the General Ledger modal stays visible
                            if (generalLedgerModalAfterClose) {
                                generalLedgerModalAfterClose.classList.add('general-ledger-modal-visible');
                                generalLedgerModalAfterClose.classList.remove('accounting-modal-hidden');
                                // Ensure body-no-scroll is maintained
                                document.body.classList.add('body-no-scroll');
                            }
                        }
                    }
                    
                    // Also refresh the main table if it exists
                    if (window.accountingSystem && typeof window.accountingSystem.loadJournalEntries === 'function') {
                        await window.accountingSystem.loadJournalEntries();
                    }
                } else {
                    throw new Error(data.message || data.error || 'Failed to save journal entry');
                }
            } catch (error) {
                this.showError('Failed to save journal entry: ' + error.message);
            } finally {
                this.reEnableForm(submitBtn);
            }
            return;
            }

            // Handle entity transaction submission
            const typeField = document.getElementById('transactionType');
            const debitField = document.getElementById('transactionDebit');
            const creditField = document.getElementById('transactionCredit');
            const entryTypeField = document.getElementById('transactionEntryType');
            const currencyField = document.getElementById('transactionCurrency');
            const descriptionField = document.getElementById('transactionDescription');
            const dateField = document.getElementById('transactionDate');
            const categoryField = document.getElementById('transactionCategory');
            const referenceField = document.getElementById('transactionReference');
            const editIdField = document.getElementById('transactionEditId');

            // Get debit and credit amounts
            const debitAmount = debitField ? parseFloat(debitField.value) || 0 : 0;
            const creditAmount = creditField ? parseFloat(creditField.value) || 0 : 0;

            if (!typeField?.value) {
                this.showError('Please select a transaction type');
                this.reEnableForm(submitBtn);
                return;
            }

            if (!currencyField?.value) {
                this.showError('Please select a currency');
                this.reEnableForm(submitBtn);
                return;
            }

            // Validate that debit or credit is provided
            const hasDebitCredit = debitAmount > 0 || creditAmount > 0;
            if (!hasDebitCredit) {
                this.showError('Please enter either Debit or Credit amount');
                this.reEnableForm(submitBtn);
                return;
            }

            if (!descriptionField?.value.trim()) {
                this.showError('Please enter a description');
                this.reEnableForm(submitBtn);
                return;
            }

            if (!dateField?.value) {
                this.showError('Please select a date');
                this.reEnableForm(submitBtn);
                return;
            }

            if (!this.currentEntity || (this.currentEntityId === null || this.currentEntityId === undefined)) {
                this.showError('Entity information is missing');
                this.reEnableForm(submitBtn);
                return;
            }

            // Calculate amount from debit or credit (whichever is greater)
            const finalAmount = Math.max(debitAmount, creditAmount);

            const formData = {
                entity_type: this.currentEntity ? this.currentEntity.toLowerCase() : null,
                entity_id: this.currentEntityId,
                type: typeField.value,
                currency: currencyField.value,
                amount: finalAmount,
                debit: debitAmount,
                credit: creditAmount,
                entry_type: entryTypeField?.value || 'Manual',
                description: descriptionField.value.trim(),
                transaction_date: dateField.value,
                category: categoryField?.value || 'other',
                reference_number: referenceField?.value.trim() || null
            };

            if (this.isEditMode && editIdField?.value) {
                formData.id = parseInt(editIdField.value);
                formData.status = 'Posted';
            }

            const method = this.isEditMode ? 'PUT' : 'POST';
            const response = await fetch(this.apiBase, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            const contentType = response.headers.get('content-type');
            let responseText = '';
            
            // Try to get response text first for error reporting
            try {
                const clonedResponse = response.clone();
                responseText = await clonedResponse.text();
            } catch (e) {
                // If cloning fails, try to read the response directly
                try {
                    responseText = await response.text();
                    // Re-create response from text for JSON parsing
                    response = new Response(responseText, {
                        status: response.status,
                        statusText: response.statusText,
                        headers: response.headers
                    });
                } catch (e2) {
                    // Could not read response text
                }
            }
            
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Server returned an invalid response (status: ' + response.status + '): ' + responseText.substring(0, 500));
            }

            let data;
            try {
                // If we already read the text, parse it as JSON
                if (responseText) {
                    data = JSON.parse(responseText);
                } else {
                    data = await response.json();
                }
            } catch (jsonError) {
                throw new Error('Server returned invalid JSON (status: ' + response.status + '): ' + responseText.substring(0, 500));
            }

            if (data.success) {
                this.showSuccess('Transaction saved successfully!');
                this.resetForm();
                
                // Wait a bit for database to commit the transaction
                await new Promise(resolve => setTimeout(resolve, 500));
                
                // Refresh data immediately and keep modal open to show updated list
                await this.loadTransactions();
                
                // Refresh Accounts Receivable and Accounts Payable tables
                // Use setTimeout to ensure the transaction is fully saved before refreshing
                setTimeout(async () => {
                    const accountingSystem = window.accountingSystem;
                    if (accountingSystem) {
                        // Always refresh if modals are open
                        const arTable = document.getElementById('modalInvoicesTable');
                        if (arTable && arTable.offsetParent !== null && typeof accountingSystem.loadModalInvoices === 'function') {
                            await accountingSystem.loadModalInvoices();
                        }
                        
                        const apTable = document.getElementById('modalBillsTable');
                        if (apTable && apTable.offsetParent !== null && typeof accountingSystem.loadModalBills === 'function') {
                            await accountingSystem.loadModalBills();
                        }
                        
                        // Always refresh the main accounting page if on accounting page
                        if (window.location.pathname.includes('accounting.php')) {
                            if (typeof accountingSystem.refreshAllModules === 'function') {
                                accountingSystem.refreshAllModules();
                            }
                            // Also explicitly refresh invoices and bills tables if they exist (both modal and main page)
                            if (typeof accountingSystem.loadModalInvoices === 'function') {
                                await accountingSystem.loadModalInvoices();
                            }
                            if (typeof accountingSystem.loadModalBills === 'function') {
                                await accountingSystem.loadModalBills();
                            }
                            // Refresh main page tables if they exist
                            const mainInvoicesBody = document.getElementById('invoicesBody');
                            if (mainInvoicesBody) {
                                if (typeof accountingSystem.loadInvoices === 'function') {
                                    await accountingSystem.loadInvoices();
                                } else if (typeof accountingSystem.loadMainInvoices === 'function') {
                                    await accountingSystem.loadMainInvoices();
                                } else if (typeof accountingSystem.loadModalInvoices === 'function') {
                                    // Fallback: use modal function but target main table
                                    await accountingSystem.loadModalInvoices();
                                }
                            }
                            const mainBillsBody = document.getElementById('billsBody');
                            if (mainBillsBody) {
                                if (typeof accountingSystem.loadBills === 'function') {
                                    await accountingSystem.loadBills();
                                } else if (typeof accountingSystem.loadMainBills === 'function') {
                                    await accountingSystem.loadMainBills();
                                } else if (typeof accountingSystem.loadModalBills === 'function') {
                                    // Fallback: use modal function but target main table
                                    await accountingSystem.loadModalBills();
                                }
                            }
                        }
                        
                        // Refresh General Ledger - wait longer for backend to create journal entry
                        // Entity transactions automatically create journal entries via auto-journal-entry.php
                        // Need to wait for that process to complete before refreshing
                        await new Promise(resolve => setTimeout(resolve, 1500));
                        
                        // Check if General Ledger modal is open and refresh it
                        const generalLedgerModal = document.getElementById('generalLedgerModal');
                        const isGeneralLedgerOpen = generalLedgerModal && 
                            !generalLedgerModal.classList.contains('accounting-modal-hidden');
                        
                        if (window.accountingSystem) {
                            // Always refresh the main General Ledger table on accounting.php page FIRST
                            // This ensures the main table updates even if modal isn't open
                            if (window.accountingSystem && typeof window.accountingSystem.loadJournalEntries === 'function') {
                                await window.accountingSystem.loadJournalEntries();
                            }
                            
                            // Refresh modal's General Ledger if open
                            if (typeof window.accountingSystem.loadModalJournalEntries === 'function') {
                                if (isGeneralLedgerOpen) {
                                    await window.accountingSystem.loadModalJournalEntries();
                                    
                                    // Ensure the General Ledger modal stays visible
                                    if (generalLedgerModal) {
                                        generalLedgerModal.classList.add('general-ledger-modal-visible');
                                        generalLedgerModal.classList.remove('accounting-modal-hidden');
                                        document.body.classList.add('body-no-scroll');
                                    }
                                }
                            }
                            
                            // Also try refreshAllModules to ensure everything updates
                            if (typeof window.accountingSystem.refreshAllModules === 'function') {
                                await window.accountingSystem.refreshAllModules();
                            }
                        }
                        
                        // Dispatch a custom event to notify other parts of the system
                        window.dispatchEvent(new CustomEvent('accounting-transaction-saved', {
                            detail: {
                                entityType: this.currentEntity,
                                entityId: this.currentEntityId,
                                transactionType: formData.type
                            }
                        }));
                    }
                }, 1000); // Increased delay to 1000ms to ensure backend mirroring is complete
                // Don't auto-close - let user see the updated transaction list
            } else {
                throw new Error(data.message || 'Failed to save transaction');
            }
        } catch (error) {
            this.showError('Failed to save transaction: ' + error.message);
        } finally {
            this.reEnableForm(submitBtn);
        }
    }

    resetForm() {
        const form = document.getElementById('accountingTransactionForm');
        if (form) {
            form.reset();
            this.isEditMode = false;
            
            // Clear edit ID field if exists
            const editIdField = document.getElementById('transactionEditId');
            if (editIdField) {
                editIdField.value = '';
            }
            
            // Reset date to today
            this.setTodayDate();
            
            // Generate new reference number
            this.generateReferenceNumber();
            
            // Re-attach event listeners after reset
            setTimeout(() => {
                this.attachFormEventListeners();
            }, 100);
            
        }
    }

    reEnableForm(submitBtn) {
        this.isSubmitting = false;
        const btn = submitBtn || document.getElementById('saveTransactionBtn') || document.querySelector('#accountingTransactionForm button[type="submit"]');
        if (btn) {
            btn.disabled = false;
            // Restore original HTML if saved
            if (btn.dataset.originalHTML) {
                btn.innerHTML = btn.dataset.originalHTML;
                delete btn.dataset.originalHTML;
            } else {
                btn.innerHTML = '<i class="fas fa-save"></i> Save Transaction';
            }
        }
    }

    async editTransaction(transactionId) {
        try {
            const url = `${this.apiBase}?id=${transactionId}`;
            const response = await fetch(url);
            const data = await response.json();

            if (data.success && data.transaction) {
                const trans = data.transaction;
                this.isEditMode = true;
                this.currentTransaction = trans;

                document.getElementById('transactionEditId').value = transactionId;
                document.getElementById('transactionType').value = trans.transaction_type;
                // Set debit/credit based on transaction type
                const debitField = document.getElementById('transactionDebit');
                const creditField = document.getElementById('transactionCredit');
                if (debitField && creditField) {
                    if (trans.transaction_type === 'Expense') {
                        debitField.value = trans.total_amount || trans.debit_amount || 0;
                        creditField.value = 0;
                    } else if (trans.transaction_type === 'Income') {
                        debitField.value = 0;
                        creditField.value = trans.total_amount || trans.credit_amount || 0;
                    } else {
                        debitField.value = trans.debit_amount || 0;
                        creditField.value = trans.credit_amount || 0;
                    }
                }
                const currencySelect = document.getElementById('transactionCurrency');
                if (currencySelect) {
                    // Ensure currency dropdown is populated before setting value
                    const defaultCurrency = localStorage.getItem('accounting_default_currency') || 'SAR';
                    if (window.currencyUtils) {
                        await window.currencyUtils.populateCurrencySelect(currencySelect, trans.currency || defaultCurrency);
                    } else {
                        currencySelect.value = trans.currency || defaultCurrency;
                    }
                    // Save currency to localStorage when editing
                    const currency = trans.currency || localStorage.getItem('accounting_default_currency') || 'SAR';
                    localStorage.setItem('accounting_last_currency', currency);
                    localStorage.setItem('accounting_default_currency', currency);
                }
                document.getElementById('transactionDescription').value = trans.description;
                const transactionDateField = document.getElementById('transactionDate');
                if (transactionDateField && trans.transaction_date) {
                    transactionDateField.value = this.formatDateForInput(trans.transaction_date);
                }
                document.getElementById('transactionCategory').value = trans.category || 'other';
                document.getElementById('transactionReference').value = trans.reference_number || '';

                // Form title removed - no longer needed
                const submitBtn = document.getElementById('saveTransactionBtn') || document.querySelector('#accountingTransactionForm button[type="submit"]');
                if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Transaction';

                // Scroll to form
                document.getElementById('transactionsTab').scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                throw new Error(data.message || 'Transaction not found');
            }
        } catch (error) {
            this.showError('Failed to load transaction: ' + error.message);
        }
    }

    async editJournalEntry(entryId) {
        // Ensure currency dropdown is populated before editing
        if (window.currencyUtils) {
            const journalCurrencyField = document.getElementById('journalCurrency');
            if (journalCurrencyField) {
                await window.currencyUtils.populateCurrencySelect(journalCurrencyField);
            }
        }
        try {
            // Fetch the journal entry data FIRST, before opening modal
            const response = await fetch(this.getApiUrl('journal-entries.php') + `?id=${entryId}`);
            const data = await response.json();
            
            if (!data.success || !data.entry) {
                throw new Error(data.message || 'Journal entry not found');
            }
            
            const entry = data.entry;
            
            // Open the modal in journal mode (this will reset the form, which is fine)
            await this.open('journal', 0, 'General Ledger');
            
            // Wait longer for form to be fully ready and visible after reset
            await new Promise(resolve => setTimeout(resolve, 800));
            
            // Wait for journal fields to be visible
            let journalFields = document.getElementById('journalFields');
            let attempts = 0;
            while ((!journalFields || journalFields.classList.contains('journal-fields-hidden')) && attempts < 30) {
                await new Promise(resolve => setTimeout(resolve, 100));
                journalFields = document.getElementById('journalFields');
                attempts++;
            }
            
            if (!journalFields) {
                throw new Error('Journal fields not found in DOM');
            }
            
            // Ensure journal fields are visible
            journalFields.classList.add('journal-fields-visible');
            journalFields.classList.remove('journal-fields-hidden');
            
            // Hide Transaction History section for journal entries
            const transactionsList = document.querySelector('.transactions-list');
            if (transactionsList) {
                transactionsList.classList.add('transactions-list-hidden');
                transactionsList.classList.remove('transactions-list-visible');
            }
            
            // Wait a bit more to ensure all form elements are ready
            await new Promise(resolve => setTimeout(resolve, 200));
            
            // Set edit mode AFTER modal is open (so resetForm doesn't clear it)
            this.isEditMode = true;
            const editIdField = document.getElementById('transactionEditId');
            if (editIdField) {
                editIdField.value = entryId;
            }
            
            // Populate journal entry fields
            const dateField = document.getElementById('journalDate');
            const descriptionField = document.getElementById('journalDescription');
            const accountField = document.getElementById('journalAccount');
            const debitField = document.getElementById('journalDebitAmount');
            const creditField = document.getElementById('journalCreditAmount');
            const currencyField = document.getElementById('journalCurrency');
            const typeField = document.getElementById('journalType');
            const statusField = document.getElementById('journalStatus');
            const entityField = document.getElementById('journalEntity');
            const referenceField = document.getElementById('transactionReference');
            
            if (dateField && entry.entry_date) {
                // Format date as MM/DD/YYYY for display
                dateField.value = this.formatDateForInput(entry.entry_date);
            }
            
            if (descriptionField) {
                descriptionField.value = entry.description || '';
            }
            
            // Wait for accounts to load before setting account
            if (accountField) {
                await this.loadJournalAccounts();
                if (entry.account_id) {
                    accountField.value = entry.account_id;
                }
            }
            
            if (debitField) {
                debitField.value = entry.total_debit || '';
            }
            
            if (creditField) {
                creditField.value = entry.total_credit || '';
            }
            
            if (currencyField) {
                // Normalize currency value - extract code if format is "CODE - Name"
                let currencyValue = entry.currency || (localStorage.getItem('accounting_default_currency') || 'SAR');
                if (typeof currencyValue === 'string' && currencyValue.includes(' - ')) {
                    currencyValue = currencyValue.split(' - ')[0].trim();
                }
                currencyValue = currencyValue.toUpperCase().trim();
                
                // Repopulate dropdown if needed, then set value
                if (window.currencyUtils && currencyField.options.length <= 1) {
                    await window.currencyUtils.populateCurrencySelect(currencyField, currencyValue);
                } else {
                    // Check if the value exists in the dropdown options
                    const currencyOption = Array.from(currencyField.options).find(opt => opt.value.toUpperCase() === currencyValue);
                    if (currencyOption) {
                        currencyField.value = currencyOption.value;
                    } else {
                        // Value doesn't match any option, default to system currency
                        currencyField.value = localStorage.getItem('accounting_default_currency') || 'SAR';
                    }
                }
            }
            
            if (typeField) {
                // Normalize entry_type value
                let typeValue = entry.entry_type || 'Manual';
                if (typeof typeValue === 'string') {
                    typeValue = typeValue.trim();
                    // Capitalize first letter, rest lowercase
                    typeValue = typeValue.charAt(0).toUpperCase() + typeValue.slice(1).toLowerCase();
                }
                
                // Check if the value exists in the dropdown options
                const typeOption = Array.from(typeField.options).find(opt => opt.value.toLowerCase() === typeValue.toLowerCase());
                if (typeOption) {
                    typeField.value = typeOption.value;
                    // Make type readonly in edit mode
                    typeField.setAttribute('readonly', 'readonly');
                    typeField.classList.add('type-field-readonly');
                    typeField.classList.remove('type-field-editable');
                } else {
                    // Value doesn't match any option, default to Manual
                    typeField.value = 'Manual';
                    typeField.setAttribute('readonly', 'readonly');
                    typeField.classList.add('type-field-readonly');
                    typeField.classList.remove('type-field-editable');
                }
            }
            
            if (statusField) {
                statusField.value = entry.status || 'Posted';
            }
            
            // Load and set entity if available
            if (entityField) {
                await this.loadJournalEntities('all');
                // Wait a bit for dropdown to be fully populated
                await new Promise(resolve => setTimeout(resolve, 200));
                
                if (entry.entity_type && entry.entity_id) {
                    const entityValue = `${entry.entity_type}:${entry.entity_id}`;
                    entityField.value = entityValue;
                    
                    // Verify it was set, retry if needed
                    if (entityField.value !== entityValue) {
                        await new Promise(resolve => setTimeout(resolve, 100));
                        entityField.value = entityValue;
                    }
                } else {
                    entityField.value = '';
                }
            }
            
            if (referenceField) {
                referenceField.value = entry.reference_number || '';
            }
            
            // Update button text
            const submitBtn = document.getElementById('saveTransactionBtn');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Update Transaction';
            }
            
            // Update modal title
            const titleElement = document.getElementById('accountingModalTitle');
            if (titleElement) {
                titleElement.textContent = 'Edit Journal Entry';
            }
            
            // Hide Manual Entry button in edit mode (it's only for add mode)
            const manualEntryTab = document.getElementById('manualEntryTab');
            if (manualEntryTab) {
                manualEntryTab.classList.add('manual-entry-tab-hidden');
                manualEntryTab.classList.remove('manual-entry-tab-visible');
            }
            
            // Force a re-render to ensure values are visible
            await new Promise(resolve => setTimeout(resolve, 100));
        } catch (error) {
            this.showError('Failed to load journal entry: ' + error.message);
        }
    }

    async deleteTransaction(transactionId) {
        const result = await this.showAlert(
            'Delete Transaction',
            'Are you sure you want to delete this transaction? This action cannot be undone.',
            'warning',
            [
                { text: 'Cancel', action: 'cancel', class: 'secondary', icon: 'fas fa-times' },
                { text: 'Delete', action: 'confirm', class: 'danger', icon: 'fas fa-trash' }
            ]
        );

        if (result !== 'confirm') {
            return;
        }

        try {
            const url = `${this.apiBase}?id=${transactionId}&entity_type=${this.currentEntity}&entity_id=${this.currentEntityId}`;
            const response = await fetch(url, {
                method: 'DELETE'
            });

            const data = await response.json();

            if (data.success) {
                this.showSuccess('Transaction deleted successfully');
                await this.loadTransactions();
            } else {
                throw new Error(data.message || 'Failed to delete transaction');
            }
        } catch (error) {
            this.showError('Failed to delete transaction: ' + error.message);
        }
    }


    resetForm() {
        const editIdField = document.getElementById('transactionEditId');
        const typeField = document.getElementById('transactionType');
        const debitField = document.getElementById('transactionDebit');
        const creditField = document.getElementById('transactionCredit');
        const descriptionField = document.getElementById('transactionDescription');
        const categoryField = document.getElementById('transactionCategory');
        const referenceField = document.getElementById('transactionReference');
        const dateField = document.getElementById('transactionDate');
        
        // Journal entry fields
        const journalDateField = document.getElementById('journalDate');
        const journalDescriptionField = document.getElementById('journalDescription');
        const journalAccountField = document.getElementById('journalAccount');
        const journalDebitField = document.getElementById('journalDebitAmount');
        const journalCreditField = document.getElementById('journalCreditAmount');
        const journalCurrencyField = document.getElementById('journalCurrency');
        const journalTypeField = document.getElementById('journalType');
        const journalStatusField = document.getElementById('journalStatus');
        const journalEntityField = document.getElementById('journalEntity');
        
        if (editIdField) editIdField.value = '';
        if (typeField) typeField.value = '';
        if (debitField) debitField.value = '';
        if (creditField) creditField.value = '';
        if (descriptionField) descriptionField.value = '';
        if (categoryField) categoryField.value = 'commission';
        if (referenceField) referenceField.value = '';
        
        // Clear and set today's date
        if (dateField) {
            const today = new Date().toISOString().split('T')[0];
            dateField.value = this.formatDateForInput(today);
        }
        
        // Reset journal entry fields
        if (journalDateField) {
            const today = new Date().toISOString().split('T')[0];
            journalDateField.value = this.formatDateForInput(today);
        }
        if (journalDescriptionField) journalDescriptionField.value = '';
        if (journalAccountField) journalAccountField.value = '';
        if (journalDebitField) journalDebitField.value = '';
        if (journalCreditField) journalCreditField.value = '';
        // Don't set journalCurrencyField value here - let populateCurrencies handle it
        // if (journalCurrencyField) journalCurrencyField.value = 'SAR';
        if (journalTypeField) {
            journalTypeField.value = 'Manual';
            // Make type editable in add mode
            journalTypeField.removeAttribute('readonly');
            journalTypeField.classList.add('type-field-editable');
            journalTypeField.classList.remove('type-field-readonly');
        }
        if (journalStatusField) journalStatusField.value = 'Posted';
        if (journalEntityField) journalEntityField.value = '';
        
        // Show Manual Entry button in add mode
        const manualEntryTab = document.getElementById('manualEntryTab');
        if (manualEntryTab) {
            manualEntryTab.classList.add('manual-entry-tab-visible');
            manualEntryTab.classList.remove('manual-entry-tab-hidden');
        }
        
        // Reset form title and button
        // Form title removed - no longer needed
        
        const submitBtn = document.getElementById('saveTransactionBtn') || document.querySelector('#accountingTransactionForm button[type="submit"]');
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Transaction';
            submitBtn.disabled = false;
        }
        
        // Reset edit mode
        this.isEditMode = false;
        this.currentTransaction = null;
        
        // Load default currency from system settings
        const defaultCurrency = localStorage.getItem('accounting_default_currency') || localStorage.getItem('accounting_last_currency') || 'SAR';
        const currencySelect = document.getElementById('transactionCurrency');
        if (currencySelect) {
            currencySelect.value = defaultCurrency;
            
            // Save currency when changed (only add listener once)
            if (!currencySelect.hasAttribute('data-currency-listener')) {
                currencySelect.setAttribute('data-currency-listener', 'true');
                currencySelect.addEventListener('change', function() {
                    // Update both last currency (for form memory) and default currency (for system-wide use)
                    localStorage.setItem('accounting_last_currency', this.value);
                    localStorage.setItem('accounting_default_currency', this.value);
                });
            }
        }
        
        // Generate new reference number after reset
        setTimeout(() => {
            this.generateReferenceNumber();
        }, 100);
    }


    // Utility functions
    formatDate(dateString) {
        if (!dateString) return '';
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
    formatDateForInput(dateString) {
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

    formatCurrency(amount, currency = null) {
        // Get default currency from system settings if not provided
        if (!currency) {
            currency = localStorage.getItem('accounting_default_currency') || 'SAR';
        }
        
        // Use Intl.NumberFormat for proper currency formatting
        try {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency || 'SAR',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(parseFloat(amount || 0));
        } catch (e) {
            // Fallback if currency code is invalid
            const defaultCurrency = localStorage.getItem('accounting_default_currency') || 'SAR';
            return `${defaultCurrency} ${parseFloat(amount || 0).toLocaleString('en-SA', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            })}`;
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    setTodayDate() {
        const today = new Date().toISOString().split('T')[0];
        const dateInput = document.getElementById('transactionDate');
        if (dateInput && !dateInput.value) {
            dateInput.value = this.formatDateForInput(today);
        }
    }

    async generateReferenceNumber() {
        const referenceField = document.getElementById('transactionReference');
        if (!referenceField) {
            return;
        }
        
        // Don't overwrite if editing an existing transaction
        if (this.isEditMode && referenceField.value.trim() !== '') {
            return;
        }
        
        // Clear any existing value when creating a new transaction
        if (!this.isEditMode) {
            referenceField.value = '';
        }

        try {
            const apiUrl = this.getApiUrl('transactions.php') + '?action=get_next_ref';
            const response = await fetch(apiUrl);
            const data = await response.json();
            
            if (data.success && data.next_reference) {
                referenceField.value = data.next_reference;
            } else {
                // Fallback: generate a simple reference
                const timestamp = Date.now();
                referenceField.value = `REF-${timestamp.toString().slice(-8)}`;
            }
        } catch (error) {
            // Fallback: generate a simple reference
            const timestamp = Date.now();
            if (referenceField) {
                referenceField.value = `REF-${timestamp.toString().slice(-8)}`;
            }
        }
    }

    showSuccess(message, title = 'Success') {
        this.showModernAlert(message, 'success', title, 4000);
    }

    showError(message, title = 'Error') {
        this.showModernAlert(message, 'error', title, 5000);
    }

    showWarning(message, title = 'Warning') {
        this.showModernAlert(message, 'warning', title, 4500);
    }

    showInfo(message, title = 'Info') {
        this.showModernAlert(message, 'info', title, 3000);
    }

    showNotification(message, type) {
        // Use modern alert system
        const titles = {
            success: 'Success',
            error: 'Error',
            warning: 'Warning',
            info: 'Info'
        };
        const durations = {
            success: 4000,
            error: 5000,
            warning: 4500,
            info: 3000
        };
        this.showModernAlert(message, type, titles[type] || 'Alert', durations[type] || 4000);
    }

    showModernAlert(message, type = 'info', title = 'Alert', duration = 4000) {
        const alert = document.getElementById('modernAlert');
        if (!alert) {
            // Fallback to basic alert if modern alert not available
            console.warn('Modern alert element not found, using fallback');
            if (type === 'error' || type === 'warning') {
                // Try to use subagentManager's modern alert if available
                if (window.subagentManager && typeof window.subagentManager.showModernAlert === 'function') {
                    window.subagentManager.showModernAlert(message, type, title, duration);
                } else {
                    alert(message);
                }
            }
            return;
        }

        const alertIcon = alert.querySelector('.alert-icon i');
        const alertTitle = alert.querySelector('.alert-title');
        const alertText = alert.querySelector('.alert-text');
        const alertProgress = alert.querySelector('.alert-progress');
        const alertClose = alert.querySelector('.alert-close');

        // Set content
        alertTitle.textContent = title;
        alertText.textContent = message;

        // Set icon based on type
        const icons = {
            success: 'fas fa-check-circle',
            warning: 'fas fa-exclamation-triangle',
            error: 'fas fa-times-circle',
            info: 'fas fa-info-circle'
        };
        alertIcon.className = icons[type] || icons.info;

        // Set type class
        alert.className = `modern-alert ${type}`;

        // Remove existing close handler and add new one
        const newCloseBtn = alertClose.cloneNode(true);
        alertClose.parentNode.replaceChild(newCloseBtn, alertClose);
        newCloseBtn.addEventListener('click', () => this.hideModernAlert());

        // Show alert
        alert.classList.add('show');

        // Reset progress bar
        alertProgress.style.transition = 'none';
        alertProgress.style.transform = 'scaleX(0)';
        setTimeout(() => {
            alertProgress.style.transition = `transform ${duration}ms linear`;
            alertProgress.style.transform = 'scaleX(1)';
        }, 100);

        // Auto hide after duration
        if (duration > 0) {
            setTimeout(() => {
                this.hideModernAlert();
            }, duration);
        }
    }

    hideModernAlert() {
        const alert = document.getElementById('modernAlert');
        if (alert) {
            alert.classList.remove('show');
        }
    }
}

// Global functions
async function openAccountingModal(entityType, entityId, entityName) {
    // Check if modal is closing
    if (window.accountingModal && window.accountingModal.isClosing) {
        return;
    }
    
    // Check if modal is already visible
    const existingModal = document.getElementById('accountingModal');
    if (existingModal && !existingModal.classList.contains('accounting-modal-hidden')) {
        // If a journal entry is requested, close the current modal first
        if (entityType === 'journal') {
            if (window.accountingModal) {
                await window.accountingModal.close();
                // Wait for modal to close
                await new Promise(resolve => setTimeout(resolve, 300));
            }
        } else {
            // Check if it's the same entity type and ID
            const currentEntity = window.accountingModal?.currentEntity;
            const currentEntityId = window.accountingModal?.currentEntityId;
            if (currentEntity === entityType?.toLowerCase() && currentEntityId === parseInt(entityId)) {
                return;
            }
            // If different entity, close first
            if (window.accountingModal) {
                await window.accountingModal.close();
                await new Promise(resolve => setTimeout(resolve, 300));
            }
        }
    }
    
    // Allow entityId: 0 for journal entries, but reject null/undefined
    if (!entityType || (entityId === null || entityId === undefined)) {
        if (window.accountingModal) {
            window.accountingModal.showError('Missing entity information. Please refresh the page and try again.', 'Error');
        } else {
            if (window.accountingModal) {
                window.accountingModal.showError('Missing entity information. Please refresh the page and try again.', 'Error');
            } else {
                alert('Error: Missing entity information. Please refresh the page and try again.');
            }
        }
        return;
    }
    
    // For non-journal entries, entityId must be > 0
    if (entityType !== 'journal' && entityId <= 0) {
        if (window.accountingModal) {
            window.accountingModal.showError('Invalid entity ID. Please refresh the page and try again.', 'Error');
        } else {
            if (window.accountingModal) {
                window.accountingModal.showError('Invalid entity ID. Please refresh the page and try again.', 'Error');
            } else {
                alert('Error: Invalid entity ID. Please refresh the page and try again.');
            }
        }
        return;
    }
    
    if (!window.accountingModal) {
        window.accountingModal = new AccountingModal();
    }
    
    try {
        await window.accountingModal.open(entityType, entityId, entityName);
    } catch (error) {
        if (window.accountingModal) {
            window.accountingModal.showError('Error opening accounting modal: ' + error.message, 'Error');
        } else {
            alert('Error opening accounting modal: ' + error.message);
        }
    }
}

function closeAccountingModal() {
    if (window.accountingModal) {
        window.accountingModal.close();
    }
}

// Make functions globally available
window.openAccountingModal = openAccountingModal;
window.closeAccountingModal = closeAccountingModal;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    if (!window.accountingModal) {
        window.accountingModal = new AccountingModal();
    }
});

// Global offline handler - disable all alerts when going offline
window.addEventListener('offline', () => {
    // Force close any open modals without alerts
    const modal = document.getElementById('accountingModal');
    if (modal && !modal.classList.contains('accounting-modal-hidden')) {
        if (window.accountingModal) {
            window.accountingModal.forceClose();
        }
    }
});
