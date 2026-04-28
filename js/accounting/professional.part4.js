/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.part4.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.part4.js`.
 */
/** Professional Accounting - Part 4 (lines 15199-20198) */
                                // For currency, try to match by code (value may be "CODE - Name")
                                const currencyCode = String(value).toUpperCase();
                                const currencyOption = Array.from(input.options).find(opt => {
                                    const optValue = opt.value;
                                    if (optValue.includes(' - ')) {
                                        return optValue.split(' - ')[0].trim().toUpperCase() === currencyCode;
                                    }
                                    return optValue.toUpperCase() === currencyCode;
                                });
                                if (currencyOption) {
                                    input.value = currencyOption.value;
                                }
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
    }
    
ProfessionalAccounting.prototype.saveSettings = async function() {
        // Save directly without confirmation
        
        const settingInputs = document.querySelectorAll('#accountingSettingsModal [data-setting-key]');
        const settingsToSave = [];
        
        settingInputs.forEach(input => {
            const key = input.getAttribute('data-setting-key');
            const type = input.getAttribute('data-setting-type') || 'text';
            let value = input.value;
            
            if (input.type === 'checkbox') {
                value = input.checked;
            } else if (type === 'number') {
                value = parseFloat(value) || 0;
            } else if (type === 'boolean') {
                value = value === '1' || value === 1 || value === true;
            } else if (input.tagName === 'SELECT' && input.id === 'defaultCurrency') {
                // Extract currency code from dropdown value (may be "CODE - Name" format)
                if (value && value.includes(' - ')) {
                    value = value.split(' - ')[0].trim();
                }
            }
            
            settingsToSave.push({
                key: key,
                value: value,
                type: type
            });
        });
        
        try {
            const savePromises = settingsToSave.map(setting => 
                fetch(`${this.apiBase}/settings.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(setting)
                })
            );
            
            const results = await Promise.all(savePromises);
            const allSuccess = results.every(r => r.ok);
            
            if (allSuccess) {
                this.showToast('Settings saved successfully!', 'success');
                // Remove changed indicators
                settingInputs.forEach(input => input.classList.remove('setting-changed'));
            } else {
                this.showToast('Some settings failed to save. Please try again.', 'error');
            }
        } catch (error) {
            console.error('Error saving settings:', error);
            this.showToast('Error saving settings: ' + error.message, 'error');
        }
    }
    
ProfessionalAccounting.prototype.resetSettings = function() {
        (async () => {
            // Reset directly without confirmation
            
            // Reload settings from API (will use defaults if not set)
            await this.loadSettings();
            
            // Remove changed indicators
            const settingInputs = document.querySelectorAll('#accountingSettingsModal [data-setting-key]');
            settingInputs.forEach(input => input.classList.remove('setting-changed'));
            
            this.showToast('Settings reset to defaults', 'success');
        })();
    }
    
ProfessionalAccounting.prototype.exportSettings = function() {
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
    }
    
ProfessionalAccounting.prototype.setupSettingsHandlers = function() {
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
    }
    
ProfessionalAccounting.prototype.updateSettingsSummary = function() {
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
    }

    /**
     * Get default currency from system settings
     * Always fetches fresh from system settings to ensure it's up-to-date
     */
ProfessionalAccounting.prototype.getDefaultCurrency = async function(forceRefresh = false) {
        // Get first active currency from system settings (always fresh)
        try {
            if (window.currencyUtils && typeof window.currencyUtils.fetchCurrencies === 'function') {
                const currencies = await window.currencyUtils.fetchCurrencies(forceRefresh);
                if (currencies && currencies.length > 0) {
                    const defaultCurrency = currencies[0].code;
                    // Always update localStorage with the first active currency
                    localStorage.setItem('accounting_default_currency', defaultCurrency);
                    return defaultCurrency;
                }
            }
        } catch (error) {
            console.error('Error fetching default currency:', error);
        }

        // Fallback to stored value or SAR
        const storedCurrency = localStorage.getItem('accounting_default_currency');
        return (storedCurrency && storedCurrency.length === 3) ? storedCurrency : 'SAR';
    }

    /**
     * Get default currency synchronously (uses cached value or SAR)
     */
ProfessionalAccounting.prototype.getDefaultCurrencySync = function() {
        const storedCurrency = localStorage.getItem('accounting_default_currency');
        return storedCurrency && storedCurrency.length === 3 ? storedCurrency : 'SAR';
    }

    /**
     * Initialize default currency from system settings
     */
ProfessionalAccounting.prototype.initDefaultCurrency = async function() {
        try {
            // Clear currency cache to force fresh fetch
            if (window.currencyUtils && typeof window.currencyUtils.clearCache === 'function') {
                window.currencyUtils.clearCache();
            }
            
            // Force refresh to get the latest currency from system settings
            const defaultCurrency = await this.getDefaultCurrency(true);
            localStorage.setItem('accounting_default_currency', defaultCurrency);
            
            // Refresh status cards after currency is initialized
            if (this.currentTab === 'dashboard') {
                this.refreshDashboardCards();
            }
        } catch (error) {
            console.error('Error initializing default currency:', error);
        }
    }
    
    /**
     * Refresh dashboard cards with current default currency
     */
ProfessionalAccounting.prototype.refreshDashboardCards = async function() {
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

ProfessionalAccounting.prototype.formatCurrency = function(amount, currency = null) {
        // If currency not provided, use default from system settings
        let validCurrency = currency || this.getDefaultCurrencySync();
        
        // Validate currency code - must be a valid 3-letter code, not empty, not '0'
        if (!validCurrency || validCurrency === '0' || validCurrency === '' || validCurrency.length !== 3) {
            validCurrency = 'SAR';
        }
        
        try {
            return new Intl.NumberFormat('en-SA', {
                style: 'currency',
                currency: validCurrency,
                minimumFractionDigits: 2
            }).format(amount || 0);
        } catch (e) {
            // Fallback if currency code is still invalid
            return new Intl.NumberFormat('en-SA', {
                style: 'currency',
                currency: 'SAR',
                minimumFractionDigits: 2
            }).format(amount || 0);
        }
    }

ProfessionalAccounting.prototype.escapeHtml = function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }


ProfessionalAccounting.prototype.loadEntities = async function() {
        try {
            const entityTypeFilter = document.getElementById('entityTypeFilter');
            const entityType = entityTypeFilter ? entityTypeFilter.value : '';
            
            const url = `${this.apiBase}/entities.php?only_with_transactions=1${entityType ? `&entity_type=${entityType}` : ''}`;
            const response = await fetch(url);
            const data = await response.json();

            if (data.success && data.entities) {
                const entityFilter = document.getElementById('entityFilter');
                if (!entityFilter) return;

                entityFilter.innerHTML = '<option value="">All</option>' + 
                    data.entities.map(entity => 
                        `<option value="${entity.entity_type}:${entity.id}">${this.escapeHtml(entity.display_name)}</option>`
                    ).join('');
            }
        } catch (error) {
            this.showToast('Failed to load entities list. Please try again.', 'warning');
        }
    }

ProfessionalAccounting.prototype.loadTopEntities = async function(topEntities) {
        try {
            const container = document.getElementById('topEntitiesList');
            if (!container) return;

            if (!topEntities || topEntities.length === 0) {
                container.innerHTML = '<p class="text-muted">No entity data available</p>';
                return;
            }

            // Fetch ALL entity names (no filter to get all types)
            const entitiesResponse = await fetch(`${this.apiBase}/entities.php`);
            const entitiesData = await entitiesResponse.json();
            const entitiesMap = {};
            if (entitiesData.success && entitiesData.entities) {
                entitiesData.entities.forEach(entity => {
                    // Create both with and without entity_type prefix for flexibility
                    const key1 = `${entity.entity_type}:${entity.id}`;
                    const key2 = `${entity.id}`;
                    entitiesMap[key1] = entity;
                    entitiesMap[key2] = entity; // Also store by ID only for fallback
                });
            }

            container.innerHTML = topEntities.map((entity, index) => {
                // Try multiple key formats
                const entityKey1 = `${entity.entity_type}:${entity.entity_id}`;
                const entityKey2 = `${entity.entity_id}`;
                let entityInfo = entitiesMap[entityKey1] || entitiesMap[entityKey2];
                
                // Use entity name from API response if available, otherwise use entitiesMap
                let entityName = null;
                if (entity.entity_name) {
                    entityName = entity.entity_name;
                } else if (entityInfo) {
                    entityName = entityInfo.name || entityInfo.display_name;
                }
                
                // If still not found, construct display name
                if (!entityName) {
                    const entityTypeLabel = entity.entity_type ? entity.entity_type.charAt(0).toUpperCase() + entity.entity_type.slice(1) : 'Entity';
                    entityName = `${entityTypeLabel} #${entity.entity_id}`;
                }
                
                return `
                    <div class="accounting-top-entity-item">
                        <div class="accounting-top-entity-content">
                            <div>
                                <span class="badge badge-primary accounting-top-entity-badge">#${index + 1}</span>
                                <strong>${this.escapeHtml(entityName)}</strong>
                                <span class="badge badge-secondary">${entity.entity_type || 'unknown'}</span>
                            </div>
                            <div class="accounting-top-entity-right">
                                <div><strong>Revenue:</strong> ${this.formatCurrency(entity.total_revenue)}</div>
                                <div><strong>Net Profit:</strong> 
                                    <span class="${entity.net_profit >= 0 ? 'text-success' : 'text-danger'}">
                                        ${this.formatCurrency(entity.net_profit)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        } catch (error) {
        }
    }

    // Event delegation for edit/delete buttons

ProfessionalAccounting.prototype.openEntityTransactionModal = async function(transactionId = null) {
        // Use the unified accounting modal system - DO NOT create duplicate modals
        // Detect entity type from current page URL or context
        let entityType = 'agent'; // Default
        let entityId = null;
        let entityName = 'Entity';
        
        // Detect entity type from page URL
        const currentPath = window.location.pathname.toLowerCase();
        if (currentPath.includes('/agent.php') || currentPath.includes('/agents')) {
            entityType = 'agent';
        } else if (currentPath.includes('/subagent.php') || currentPath.includes('/subagents')) {
            entityType = 'subagent';
        } else if (currentPath.includes('/worker.php') || currentPath.includes('/workers')) {
            entityType = 'worker';
        } else if (currentPath.includes('/hr.php') || currentPath.includes('/hr')) {
            entityType = 'hr';
        }
        
        // If editing, get entity info from transaction data
        if (transactionId) {
            try {
                const response = await fetch(`${this.apiBase}/entity-transactions.php?id=${transactionId}`);
                const data = await response.json();
                if (data.success && data.transaction) {
                    const trans = data.transaction;
                    entityType = (trans.entity_type || entityType).toLowerCase();
                    entityId = trans.entity_id || entityId;
                    entityName = trans.entity_name || entityName || `${entityType.charAt(0).toUpperCase() + entityType.slice(1)} ${entityId || ''}`;
                }
            } catch (error) {
            }
        }
        
        // Use the unified openAccountingModal function - this ensures only ONE modal exists
        if (typeof window.openAccountingModal === 'function') {
            await window.openAccountingModal(entityType, entityId || 0, entityName);
            
            // Initialize English date pickers
            setTimeout(() => {
                const modal = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
                if (modal) {
                    this.initializeEnglishDatePickers(modal);
                }
            }, 200);
            
            // If editing, load the transaction data after modal opens
            if (transactionId) {
                setTimeout(async () => {
                    try {
                        const response = await fetch(`${this.apiBase}/entity-transactions.php?id=${transactionId}`);
                        const data = await response.json();
                        if (data.success && data.transaction) {
                            const form = document.getElementById('entityTransactionForm');
                            if (form) {
                                const trans = data.transaction;
                                const accountSelect = document.getElementById('entityTransactionAccount');
                                const dateField = document.getElementById('entityTransactionDate');
                                const currencySelect = document.getElementById('entityTransactionCurrency');
                                const debitField = document.getElementById('entityTransactionDebit');
                                const creditField = document.getElementById('entityTransactionCredit');
                                const typeSelect = document.getElementById('entityTransactionType');
                                const statusField = document.getElementById('entityTransactionStatus');
                                const descriptionField = document.getElementById('entityTransactionDescription');
                                const referenceField = document.getElementById('entityTransactionReference');
                                
                                if (dateField) dateField.value = trans.transaction_date || '';
                                if (currencySelect) currencySelect.value = trans.currency || this.getDefaultCurrencySync();
                                if (debitField) debitField.value = trans.debit_amount || trans.debit || '';
                                if (creditField) creditField.value = trans.credit_amount || trans.credit || '';
                                if (typeSelect) typeSelect.value = trans.entry_type || 'Manual';
                                if (statusField) statusField.value = trans.status || 'Posted';
                                if (descriptionField) descriptionField.value = trans.description || '';
                                if (referenceField) referenceField.value = trans.reference_number || '';
                                
                                form.setAttribute('data-transaction-id', transactionId);
                                
                                if (accountSelect && trans.account_id) {
                                    await this.loadAccountsForSelect('entityTransactionAccount');
                                    setTimeout(() => {
                                        if (accountSelect) accountSelect.value = trans.account_id;
                                    }, 100);
                                }
                            }
                        }
                    } catch (error) {
                    }
                }, 300);
            }
        } else {
            alert('Error: Accounting modal system not available. Please refresh the page.');
        }
        
        // Load accounts dropdown and entities for entity transaction form
            setTimeout(async () => {
            // Function to load accounts with retries
            const loadAccountsWithRetry = async (retries = 0, maxRetries = 5) => {
                const accountSelect = document.getElementById('entityTransactionAccount');
                if (accountSelect) {
                    try {
                        await this.loadAccountsForSelect('entityTransactionAccount');
                    } catch (error) {
                    }
                } else {
                    if (retries < maxRetries) {
                        await new Promise(resolve => setTimeout(resolve, 200));
                        return loadAccountsWithRetry(retries + 1, maxRetries);
                    } else {
                    }
                }
            };
            
            // Start loading accounts
            await loadAccountsWithRetry();
            
            // Get account select element for later use
            const accountSelect = document.getElementById('entityTransactionAccount') || 
                                  document.getElementById('transactionAccount');
            
            // If editing, populate form fields
            if (transactionId) {
                try {
                    const response = await fetch(`${this.apiBase}/entity-transactions.php?id=${transactionId}`);
                    const data = await response.json();
                    
                    if (data.success && data.transaction) {
                        const transaction = data.transaction;
                        
                        // First, ensure accounts are loaded
                        if (accountSelect) {
                            await loadAccountsWithRetry();
                        }
                        
                        // Set account - wait for accounts to load first
                        if (accountSelect && transaction.account_id) {
                            const setAccountValue = (retries = 0, maxRetries = 15) => {
                                if (accountSelect.options.length > 1) {
                                    // Accounts are loaded, set the value
                                    const accountId = String(transaction.account_id);
                                    const optionExists = Array.from(accountSelect.options).some(opt => opt.value === accountId);
                                if (optionExists) {
                                        accountSelect.value = accountId;
                                        // Trigger change event to update UI
                                        accountSelect.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                                } else if (retries < maxRetries) {
                                    // Accounts not loaded yet, retry
                                    setTimeout(() => setAccountValue(retries + 1, maxRetries), 200);
                                } else {
                                }
                            };
                            setTimeout(() => setAccountValue(), 300);
                        } else if (accountSelect) {
                        }
                        
                        // Set other form fields - wait a bit for form to be in DOM and ensure all dropdowns are populated
                        await new Promise(resolve => setTimeout(resolve, 500));
                        
                        const form = document.getElementById('entityTransactionForm');
                        if (form) {
                            
                            const dateField = form.querySelector('[name="transaction_date"]') || document.getElementById('entityTransactionDate');
                            if (dateField && transaction.transaction_date) {
                                dateField.value = transaction.transaction_date;
                            }
                            
                            // Set debit - check both by ID and name
                            const debitField = document.getElementById('entityTransactionDebit') || 
                                             form.querySelector('[name="debit"]') ||
                                             form.querySelector('#entityTransactionDebit');
                            if (debitField) {
                                // Use debit_amount or debit, or calculate from transaction_type and total_amount
                                let debitValue = parseFloat(transaction.debit_amount) || parseFloat(transaction.debit) || 0;
                                
                                // If debit is 0 or not set, try to calculate from transaction_type
                                if (debitValue === 0 && transaction.total_amount) {
                                    if (transaction.transaction_type === 'Expense' || transaction.entry_type === 'Expense') {
                                        debitValue = parseFloat(transaction.total_amount);
                                    } else if (transaction.transaction_type === 'Transfer' || transaction.entry_type === 'Transfer') {
                                        // For transfers, debit might equal total_amount
                                        debitValue = parseFloat(transaction.total_amount);
                                    }
                                }
                                
                                debitField.value = debitValue > 0 ? debitValue.toFixed(2) : '0.00';
                            }
                            
                            // Set credit - check both by ID and name
                            const creditField = document.getElementById('entityTransactionCredit') || 
                                              form.querySelector('[name="credit"]') ||
                                              form.querySelector('#entityTransactionCredit');
                            if (creditField) {
                                // Use credit_amount or credit, or calculate from transaction_type and total_amount
                                let creditValue = parseFloat(transaction.credit_amount) || parseFloat(transaction.credit) || 0;
                                
                                // If credit is 0 or not set, try to calculate from transaction_type
                                if (creditValue === 0 && transaction.total_amount) {
                                    if (transaction.transaction_type === 'Income' || transaction.entry_type === 'Income') {
                                        creditValue = parseFloat(transaction.total_amount);
                                    } else if (transaction.transaction_type === 'Transfer' || transaction.entry_type === 'Transfer') {
                                        // For transfers, credit might equal total_amount
                                        creditValue = parseFloat(transaction.total_amount);
                                    }
                                }
                                
                                creditField.value = creditValue > 0 ? creditValue.toFixed(2) : '0.00';
                            }
                            
                            // Set type (entry_type) - check current value first, only set if different
                            const typeField = document.getElementById('entityTransactionType') || 
                                            form.querySelector('[name="entry_type"]') ||
                                            form.querySelector('#entityTransactionType');
                            if (typeField) {
                                // Map transaction_type to entry_type if needed
                                let entryType = transaction.entry_type || transaction.transaction_type || 'Manual';
                                
                                // If transaction_type is Expense, Income, or Transfer, map to Manual (or keep as is if entry_type exists)
                                if (!transaction.entry_type && transaction.transaction_type) {
                                    // Check if transaction_type matches an entry_type option
                                    const typeOptions = Array.from(typeField.options).map(opt => opt.value);
                                    if (!typeOptions.includes(transaction.transaction_type)) {
                                        // transaction_type doesn't match entry_type options, use Manual
                                        entryType = 'Manual';
                                        } else {
                                        entryType = transaction.transaction_type;
                                    }
                                }
                                
                                // Check current value - if already correct, don't change it
                                if (typeField.value && typeField.value === entryType) {
                                    // Already set correctly
                                } else {
                                    // Check if the option exists in the dropdown
                                    const typeOption = Array.from(typeField.options).find(opt => opt.value === entryType);
                                    if (typeOption) {
                                        typeField.value = entryType;
                                    } else {
                                        // Check if form HTML already set a value
                                        if (!typeField.value || typeField.value === '') {
                                            // Option doesn't exist, default to Manual
                                            typeField.value = 'Manual';
                                        }
                                    }
                                }
                            }
                            
                            // Set currency - wait a bit for dropdown to be populated, then set with retry
                            const currencyField = form.querySelector('[name="currency"]') || document.getElementById('entityTransactionCurrency');
                            if (currencyField) {
                                // Ensure currency is valid (not empty, not '0', default to system currency)
                                let currencyValue = transaction.currency && transaction.currency !== '0' && transaction.currency !== '' 
                                    ? transaction.currency 
                                    : this.getDefaultCurrencySync();
                                
                                // Extract currency code if format is "CODE - Name"
                                if (typeof currencyValue === 'string' && currencyValue.includes(' - ')) {
                                    currencyValue = currencyValue.split(' - ')[0].trim();
                                }
                                currencyValue = currencyValue.toUpperCase().trim();
                                
                                // Retry logic to wait for dropdown to be populated
                                let currencyRetries = 0;
                                const maxCurrencyRetries = 10;
                                const setCurrencyValue = () => {
                                    // Check if the option exists in the dropdown
                                    const currencyOption = Array.from(currencyField.options).find(opt => 
                                        opt.value.toUpperCase() === currencyValue || opt.value === currencyValue
                                    );
                                    if (currencyOption) {
                                        currencyField.value = currencyOption.value;
                                    } else if (currencyRetries < maxCurrencyRetries) {
                                        currencyRetries++;
                                        setTimeout(setCurrencyValue, 200);
                                    } else {
                                        // Option doesn't exist, default to system currency
                                        currencyField.value = this.getDefaultCurrencySync();
                                    }
                                };
                                setCurrencyValue();
                            }
                            
                            const descriptionField = form.querySelector('[name="description"]') || document.getElementById('entityTransactionDescription');
                            if (descriptionField && transaction.description) {
                                descriptionField.value = transaction.description;
                            }
                            
                            const referenceField = form.querySelector('[name="reference_number"]') || form.querySelector('[name="reference"]') || document.getElementById('entityTransactionReference');
                            if (referenceField) {
                                const refValue = transaction.reference_number || transaction.reference || '';
                                referenceField.value = refValue;
                            }
                            
                            const statusField = form.querySelector('[name="status"]') || document.getElementById('entityTransactionStatus');
                            if (statusField && transaction.status) {
                                statusField.value = transaction.status;
                            }
                        }
                    }
                } catch (error) {
                    this.showToast('Failed to load transaction data', 'error');
                }
        }
        }, 100);
    }


    // Modal System
ProfessionalAccounting.prototype.showModal = function(title, content, size = 'normal', customModalId = null) {
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
            } else if (title === 'Vouchers') {
                modalId = 'vouchersModal';
            } else if (title === 'Support Payments') {
                modalId = 'supportPaymentsModal';
            } else if (title === 'Add Payment Voucher' || title === 'Create Payment Voucher' || title === 'Edit Payment Voucher') {
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
                        console.log('Create Invoice button clicked');
                        
                        // Prevent double submission
                        if (submitButton.disabled) {
                            console.log('Button already processing, ignoring click');
                            return;
                        }
                        
                        // Disable button to prevent double submission
                        submitButton.disabled = true;
                        const originalText = submitButton.textContent;
                        submitButton.textContent = 'Saving...';
                        
                        try {
                            // Validate required fields
                            const requiredFields = newForm.querySelectorAll('[required]');
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
                            
                            const invoiceId = newForm.getAttribute('data-invoice-id');
                            const id = invoiceId && invoiceId !== 'null' ? parseInt(invoiceId) : null;
                            console.log('Calling saveInvoice with id:', id);
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
                    console.log('Invoice form submitted');
                    
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
                    console.log('Calling saveInvoice with id:', id);
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
                        console.warn('Form submission blocked: save already in progress');
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
    }

    // Cost Centers Modal
ProfessionalAccounting.prototype.openCostCentersModal = function() {
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
    }

    // Bank Guarantee Modal
ProfessionalAccounting.prototype.openBankGuaranteeModal = function() {
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
    }

    // Entry Approval Modal
ProfessionalAccounting.prototype.openEntryApprovalModal = function() {
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
    }

    // Load Cost Centers
ProfessionalAccounting.prototype.loadCostCenters = async function() {
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
    }

ProfessionalAccounting.prototype.loadCostCentersForSelect = async function(selectId) {
        const select = document.getElementById(selectId);
        if (!select) return;
        
        try {
            const response = await fetch(`${this.apiBase}/cost-centers.php?status=active`, {
                credentials: 'include'
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.cost_centers) {
                select.innerHTML = '<option value="">All Cost Centers</option>';
                data.cost_centers.forEach(cc => {
                    const option = document.createElement('option');
                    option.value = cc.id;
                    option.textContent = `${cc.code || ''} - ${cc.name}`.trim();
                    select.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error loading cost centers for select:', error);
            // Keep default "All Cost Centers" option
        }
    }

ProfessionalAccounting.prototype.populateCostCenterSelect = async function(selectElement) {
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
    }

ProfessionalAccounting.prototype.addJournalEntryLine = async function(side) {
        const form = document.getElementById('journalEntryForm');
        if (!form) return;
        
        const tbodyId = side === 'debit' ? 'journalDebitLinesBody' : 'journalCreditLinesBody';
        const tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        
        // Get the highest line index
        const existingRows = tbody.querySelectorAll('.ledger-line-row');
        let maxIndex = -1;
        existingRows.forEach(row => {
            const index = parseInt(row.getAttribute('data-line-index') || '0');
            if (index > maxIndex) maxIndex = index;
        });
        const newIndex = maxIndex + 1;
        
        // Create new row HTML
        const newRowHTML = this.createJournalEntryLineRow(newIndex, side);
        
        // Use insertAdjacentHTML to properly insert the <tr> element
        const lastRow = tbody.querySelector('.ledger-line-row:last-child');
        if (lastRow) {
            lastRow.insertAdjacentHTML('beforebegin', newRowHTML);
        } else {
            tbody.insertAdjacentHTML('beforeend', newRowHTML);
        }
        
        // Get the newly inserted row element
        const rowElement = tbody.querySelector(`.ledger-line-row[data-line-index="${newIndex}"]`);
        if (!rowElement) {
            console.error('Failed to find newly inserted row');
            return;
        }
        
        // Show remove button on all rows (except if only one row exists)
        const allRows = tbody.querySelectorAll('.ledger-line-row');
        allRows.forEach((row, idx) => {
            const removeBtn = row.querySelector('.btn-remove-line');
            if (removeBtn) {
                // Show remove button if more than one row, hide if only one
                removeBtn.style.display = allRows.length > 1 ? 'inline-flex' : 'none';
            }
        });
        
        // Load accounts and cost centers for the new row
        const accountSelect = rowElement.querySelector('.account-select');
        const costCenterSelect = rowElement.querySelector('.cost-center-select');
        
        if (accountSelect) {
            await this.loadAccountsForSelect(null, accountSelect);
        }
        if (costCenterSelect) {
            await this.populateCostCenterSelect(costCenterSelect);
        }
        
        // Trigger balance update by dispatching an input event on the form
        // This will trigger the updateBalance function set up in openJournalEntryModal
        const amountInput = rowElement.querySelector('.line-amount');
        if (amountInput && form) {
            amountInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

ProfessionalAccounting.prototype.createJournalEntryLineRow = function(index, side) {
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
    }

ProfessionalAccounting.prototype.populateJournalEntryEditForm = async function(entry, lines = []) {
        const form = document.getElementById('journalEntryForm');
        if (!form) return;

        // Header fields
        const entryDateInput = form.querySelector('#journalEntryDate') || form.querySelector('input[name="entry_date"]');
        const descriptionInput = form.querySelector('textarea[name="description"]');
        const branchSelect = form.querySelector('#journalBranchSelect');

        if (entryDateInput && entry?.entry_date) entryDateInput.value = entry.entry_date;
        if (descriptionInput) descriptionInput.value = entry?.description || '';

        if (branchSelect) {
            const branchId = entry?.branch_id ? String(entry.branch_id) : (branchSelect.value || '');
            if (branchId) {
                // Ensure option exists
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

        // Build debit/credit line arrays from API lines
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

            if (accountId > 0 && debitAmt > 0) {
                debitLines.push({ account_id: accountId, cost_center_id: costCenterId, description: desc, vat_report: vat, amount: debitAmt });
            }
            if (accountId > 0 && creditAmt > 0) {
                creditLines.push({ account_id: accountId, cost_center_id: costCenterId, description: desc, vat_report: vat, amount: creditAmt });
            }
        });

        if (debitLines.length === 0) debitLines.push({ account_id: 0, cost_center_id: 0, description: '', vat_report: false, amount: 0 });
        if (creditLines.length === 0) creditLines.push({ account_id: 0, cost_center_id: 0, description: '', vat_report: false, amount: 0 });

        const debitTbody = document.getElementById('journalDebitLinesBody');
        const creditTbody = document.getElementById('journalCreditLinesBody');
        if (!debitTbody || !creditTbody) return;

        debitTbody.innerHTML = '';
        creditTbody.innerHTML = '';

        // Render rows
        debitLines.forEach((_, idx) => debitTbody.insertAdjacentHTML('beforeend', this.createJournalEntryLineRow(idx, 'debit')));
        creditLines.forEach((_, idx) => creditTbody.insertAdjacentHTML('beforeend', this.createJournalEntryLineRow(idx, 'credit')));

        // Populate row values + load select options
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
        for (let i = 0; i < debitRows.length; i++) {
            await fillRow(debitRows[i], debitLines[i], 'debit');
        }

        const creditRows = Array.from(creditTbody.querySelectorAll('.ledger-line-row'));
        for (let i = 0; i < creditRows.length; i++) {
            await fillRow(creditRows[i], creditLines[i], 'credit');
        }

        // Remove button visibility
        const updateRemoveButtons = (tbody) => {
            const rows = tbody.querySelectorAll('.ledger-line-row');
            rows.forEach((r) => {
                const removeBtn = r.querySelector('.btn-remove-line');
                if (removeBtn) removeBtn.style.display = rows.length > 1 ? 'inline-flex' : 'none';
            });
        };
        updateRemoveButtons(debitTbody);
        updateRemoveButtons(creditTbody);

        // Trigger balance recalculation (handled by listeners)
        setTimeout(() => {
            form.querySelectorAll('.debit-amount, .credit-amount').forEach((inp) => {
                inp.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }, 50);
    }

    // Load Bank Guarantees
ProfessionalAccounting.prototype.loadBankGuarantees = async function() {
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
    }

    // Load Entry Approval
ProfessionalAccounting.prototype.loadEntryApproval = async function(statusFilter = null) {
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
                const errorText = await response.text();
                console.error('❌ API Error Response:', response.status, errorText);
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                console.error('❌ API returned success:false:', data.message || data.error);
                throw new Error(data.message || data.error || 'Failed to load entries');
            }

            // Store all data
            this.entryApprovalData = data.entries || [];
            
            // Render table with pagination and filters
            this.renderEntryApprovalTable();
        } catch (error) {
            console.error('Error loading entries:', error);
                tbodyEl.innerHTML = `<tr><td colspan="8" class="text-center"><div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading entries</p><p class="text-muted">${error.message}</p></div></td></tr>`;
            this.showToast(`Failed to load entries: ${error.message}`, 'error');
        }
    }

    // Setup Cost Centers Event Handlers
ProfessionalAccounting.prototype.setupCostCentersEventHandlers = function() {
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
    }

    // Setup Bank Guarantees Event Handlers
ProfessionalAccounting.prototype.setupBankGuaranteesEventHandlers = function() {
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
    }

    // Setup Entry Approval Event Handlers
ProfessionalAccounting.prototype.setupEntryApprovalEventHandlers = function() {
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
    }

    // Approve Selected Entries
ProfessionalAccounting.prototype.approveSelectedEntries = async function() {
        const modal = document.getElementById('entryApprovalModal');
        if (!modal) return;
        
        const checked = Array.from(modal.querySelectorAll('.entry-checkbox:checked:not(:disabled)')).map(cb => parseInt(cb.value));
        if (checked.length === 0) {
            this.showToast('Please select entries to approve', 'warning');
            return;
        }
        
        const confirmed = await this.showConfirmDialog(
            'Approve Entries',
            `Are you sure you want to approve ${checked.length} entry(ies)?`,
            'Approve',
            'Cancel',
            'success'
        );
        if (!confirmed) return;
        
        await this.approveEntries(checked);
    }

    // Reject Selected Entries
ProfessionalAccounting.prototype.rejectSelectedEntries = async function() {
        const modal = document.getElementById('entryApprovalModal');
        if (!modal) return;
        
        const checked = Array.from(modal.querySelectorAll('.entry-checkbox:checked:not(:disabled)')).map(cb => parseInt(cb.value));
        if (checked.length === 0) {
            this.showToast('Please select entries to reject', 'warning');
            return;
        }
        
        const confirmed = await this.showConfirmDialog(
            'Reject Entries',
            `Are you sure you want to reject ${checked.length} entry(ies)?`,
            'Continue',
            'Cancel',
            'warning'
        );
        if (!confirmed) return;
        
        // Prompt for rejection reason
        const reason = await this.showPrompt(
            'Rejection Reason',
            `Please enter the reason for rejecting ${checked.length} entry(ies):`,
            '',
            'Enter rejection reason...',
            'text'
        );
        if (!reason || !reason.trim()) {
            if (reason !== null) {
                this.showToast('Rejection reason is required', 'error');
            }
            return;
        }
        
        await this.rejectEntries(checked, reason.trim());
    }

    // Approve Entries
ProfessionalAccounting.prototype.approveEntries = async function(ids) {
        if (!ids || ids.length === 0) return;
        
        // Show loading indicator
        this.showToast('Processing approval...', 'info');
        
        try {
            const response = await fetch(`${this.apiBase}/entry-approval.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'approve',
                    ids: ids
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showToast(data.message || `${ids.length} entry(ies) approved successfully`, 'success');
                // Get current filter and reload with same filter
                const filterSelect = document.getElementById('entryApprovalStatusFilter');
                const currentFilter = filterSelect ? filterSelect.value : 'all';
                await this.loadEntryApproval(currentFilter);
                // Refresh General Ledger if open (both modal and main table)
                if (typeof this.loadModalJournalEntries === 'function') {
                    setTimeout(() => this.loadModalJournalEntries(), 500);
                }
                // Also refresh main journal entries table
                if (typeof this.loadJournalEntries === 'function') {
                    setTimeout(() => this.loadJournalEntries(), 600);
                }
                // Refresh dashboard/recent transactions
                if (typeof this.loadDashboard === 'function') {
                    setTimeout(() => this.loadDashboard(), 700);
                }
            } else {
                this.showToast(data.message || 'Failed to approve entries', 'error');
            }
        } catch (error) {
            this.showToast('Error approving entries: ' + error.message, 'error');
        }
    }

    // Reject Entries
ProfessionalAccounting.prototype.rejectEntries = async function(ids, rejectionReason = null) {
        if (!ids || ids.length === 0) return;
        
        if (!rejectionReason) {
            rejectionReason = await this.showPrompt(
                'Rejection Reason',
                'Please enter the reason for rejecting this entry:',
                '',
                'Enter rejection reason...',
                'text'
            );
            if (!rejectionReason || !rejectionReason.trim()) {
                if (rejectionReason !== null) {
                    this.showToast('Rejection reason is required', 'error');
                }
                return;
            }
        }
        
        // Show loading indicator
        this.showToast('Processing rejection...', 'info');
        
        try {
            const response = await fetch(`${this.apiBase}/entry-approval.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    action: 'reject',
                    ids: ids,
                    rejection_reason: rejectionReason
                })
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ message: `HTTP error! status: ${response.status}` }));
                console.error('❌ Rejection error response:', errorData);
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.showToast(data.message || `${ids.length} entry(ies) rejected successfully`, 'success');
                // Small delay to ensure database update is complete
                await new Promise(resolve => setTimeout(resolve, 500));
                // Change filter to "all" or "rejected" to show rejected entries
                const filterSelect = document.getElementById('entryApprovalStatusFilter');
                if (filterSelect) {
                    // If currently showing "pending", switch to "all" to show the rejected entry
                    if (filterSelect.value === 'pending') {
                        filterSelect.value = 'all';
                    }
                    await this.loadEntryApproval(filterSelect.value);
                } else {
                    await this.loadEntryApproval('all');
                }
            } else {
                console.error('❌ Rejection failed:', data.message);
                this.showToast(data.message || 'Failed to reject entries', 'error');
            }
        } catch (error) {
            console.error('❌ Error rejecting entries:', error);
            this.showToast('Error rejecting entries: ' + error.message, 'error');
        }
    }

    // Open Entry Details Modal
ProfessionalAccounting.prototype.openEntryDetailsModal = async function(entryId) {
        try {
            const response = await fetch(`${this.apiBase}/entry-approval.php?id=${entryId}`, {
                credentials: 'include'
            });
            
            const data = await response.json();
            
            if (!data.success || !data.entry) {
                this.showToast('Entry not found', 'error');
                return;
            }
            
            const entry = data.entry;
            const modalContent = `
                <div class="entry-details-view">
                    <div class="detail-row">
                        <label>Entry Number:</label>
                        <span>${this.escapeHtml(entry.entry_number)}</span>
                    </div>
                    <div class="detail-row">
                        <label>Date:</label>
                        <span>${this.formatDate(entry.entry_date)}</span>
                    </div>
                    <div class="detail-row">
                        <label>Description:</label>
                        <span>${this.escapeHtml(entry.description || 'N/A')}</span>
                    </div>
                    <div class="detail-row">
                        <label>Amount:</label>
                        <span>${this.formatCurrency(entry.amount)} ${entry.currency}</span>
                    </div>
                    <div class="detail-row">
                        <label>Status:</label>
                        <span class="status-badge ${entry.status === 'approved' ? 'status-posted' : entry.status === 'rejected' ? 'status-rejected' : 'status-pending'}">${entry.status === 'approved' ? 'Approved' : entry.status === 'rejected' ? 'Rejected' : 'Pending'}</span>
                    </div>
                    ${entry.entity_name ? `
                    <div class="detail-row">
                        <label>Entity:</label>
                        <span>${this.escapeHtml(entry.entity_name)} (${entry.entity_type})</span>
                    </div>
                    ` : ''}
                    <div class="detail-row">
                        <label>Created By:</label>
                        <span>${this.escapeHtml(entry.created_by_name || 'N/A')}</span>
                    </div>
                    ${entry.approved_by_name ? `
                    <div class="detail-row">
                        <label>Approved By:</label>
                        <span>${this.escapeHtml(entry.approved_by_name)}</span>
                    </div>
                    ` : ''}
                    ${entry.rejection_reason ? `
                    <div class="detail-row">
                        <label>Rejection Reason:</label>
                        <span>${this.escapeHtml(entry.rejection_reason)}</span>
                    </div>
                    ` : ''}
                </div>
            `;
            
            this.showModal('Entry Details', modalContent, 'normal', 'entryDetailsModal');
        } catch (error) {
            this.showToast('Error loading entry details: ' + error.message, 'error');
        }
    }

    // Open Entry Approval Form (for editing)
ProfessionalAccounting.prototype.openEntryApprovalForm = function(id) {
        this.loadEntryApprovalData(id);
    }

    // Load Entry Approval Data for Editing
ProfessionalAccounting.prototype.loadEntryApprovalData = async function(id) {
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
    }

    // Save Entry Approval (Update)
ProfessionalAccounting.prototype.saveEntryApproval = async function(id) {
        try {
            const entryNumberEl = document.getElementById('entryApprovalNumber');
            const entryDateEl = document.getElementById('entryApprovalDate');
            const descriptionEl = document.getElementById('entryApprovalDescription');
            const debitAmountEl = document.getElementById('entryApprovalDebit');
            const creditAmountEl = document.getElementById('entryApprovalCredit');
            const currencyEl = document.getElementById('entryApprovalCurrency');
            
            if (!entryNumberEl || !entryDateEl) {
                this.showToast('Form fields not found', 'error');
                return;
            }
            
            const entryNumber = entryNumberEl.value.trim();
            const entryDate = entryDateEl.value;
            const description = descriptionEl ? descriptionEl.value.trim() : '';
            const debitAmount = debitAmountEl ? parseFloat(debitAmountEl.value || 0) : 0;
            const creditAmount = creditAmountEl ? parseFloat(creditAmountEl.value || 0) : 0;
            const currency = currencyEl ? currencyEl.value : 'SAR';
            
            if (!entryNumber || !entryDate) {
                this.showToast('Entry number and date are required', 'error');
                return;
            }
            
            if (debitAmount <= 0 && creditAmount <= 0) {
                this.showToast('Either Debit or Credit amount must be greater than 0', 'error');
                return;
            }
            
            if (!id || id <= 0) {
                this.showToast('Invalid entry ID', 'error');
                return;
            }
            
            this.showToast('Updating entry...', 'info');
            
            const response = await fetch(`${this.apiBase}/entry-approval.php?id=${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    entry_number: entryNumber,
                    entry_date: entryDate,
                    description: description,
                    debit_amount: debitAmount,
                    credit_amount: creditAmount,
                    currency: currency
                })
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.showToast('Entry updated successfully', 'success');
                const modal = document.getElementById('entryApprovalFormModal');
                if (modal) {
                    await this.closeModalWithConfirmation(modal);
                }
                await this.loadEntryApproval();
            } else {
                this.showToast(data.message || 'Failed to update entry', 'error');
            }
        } catch (error) {
            this.showToast('Error updating entry: ' + error.message, 'error');
        }
    }

    // Delete Entry Approval
ProfessionalAccounting.prototype.deleteEntryApproval = async function(id) {
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
    }

    // Helper function to toggle delete button visibility
ProfessionalAccounting.prototype.toggleDeleteButton = function(modal, checkboxSelector, buttonSelector) {
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
    }

    // Cost Center Form Modal
ProfessionalAccounting.prototype.openCostCenterForm = function(id = null) {
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
    }

ProfessionalAccounting.prototype.generateCostCenterCode = async function(codeInput) {
        try {
            // Get all cost centers to find the highest code
            const response = await fetch(`${this.apiBase}/cost-centers.php`, {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (data.success && data.cost_centers) {
                let maxNumber = 30000; // Start from CC30000
                
                // Find the highest numeric code (extract number from CC#### format)
                data.cost_centers.forEach(cc => {
                    // Extract number from codes like CC30000, CC30001, etc.
                    const match = cc.code.match(/^CC(\d+)$/i);
                    if (match) {
                        const num = parseInt(match[1]);
                        if (!isNaN(num) && num >= 30000 && num > maxNumber) {
                            maxNumber = num;
                        }
                    }
                });
                
                // Generate next code (increment by 1)
                const nextNumber = maxNumber + 1;
                codeInput.value = `CC${nextNumber.toString().padStart(5, '0')}`;
            } else {
                // Fallback: start from CC30000 if no cost centers exist
                codeInput.value = 'CC30000';
            }
        } catch (error) {
            console.error('Error generating cost center code:', error);
            // Fallback: start from CC30000 on error
            codeInput.value = 'CC30000';
        }
    }

ProfessionalAccounting.prototype.loadCostCenterData = async function(id) {
        try {
            const response = await fetch(`${this.apiBase}/cost-centers.php?id=${id}`, {
                credentials: 'include'
            });
            const data = await response.json();
            
            if (data.success && data.cost_center) {
                document.getElementById('costCenterCode').value = data.cost_center.code;
                document.getElementById('costCenterName').value = data.cost_center.name;
                document.getElementById('costCenterDescription').value = data.cost_center.description || '';
                document.getElementById('costCenterStatus').value = data.cost_center.status;
            }
        } catch (error) {
            this.showToast('Failed to load cost center data', 'error');
        }
    }

ProfessionalAccounting.prototype.saveCostCenter = async function(id = null) {
        const code = document.getElementById('costCenterCode').value.trim();
        const name = document.getElementById('costCenterName').value.trim();
        const description = document.getElementById('costCenterDescription').value.trim();
        const status = document.getElementById('costCenterStatus').value;

        if (!code || !name) {
            this.showToast('Code and name are required', 'error');
            return;
        }

        try {
            const url = id ? `${this.apiBase}/cost-centers.php?id=${id}` : `${this.apiBase}/cost-centers.php`;
            const method = id ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ code, name, description, status })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showToast(data.message || (id ? 'Cost center updated' : 'Cost center created'), 'success');
                await this.closeModalWithConfirmation(document.getElementById('costCenterFormModal'));
                this.loadCostCenters();
            } else {
                this.showToast(data.message || 'Failed to save cost center', 'error');
            }
        } catch (error) {
            this.showToast('Error saving cost center', 'error');
        }
    }

ProfessionalAccounting.prototype.deleteCostCenter = async function(id) {
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
    }

ProfessionalAccounting.prototype.deleteSelectedCostCenters = async function() {
        const modal = document.getElementById('costCentersModal');
        if (!modal) return;
        
        const checked = Array.from(modal.querySelectorAll('.cost-center-checkbox:checked')).map(cb => parseInt(cb.value));
        if (checked.length === 0) {
            this.showToast('Please select cost centers to delete', 'warning');
            return;
        }

        const confirmed = await this.showConfirmDialog(
            'Delete Cost Centers',
            `Are you sure you want to delete ${checked.length} cost center(s)?`,
            'Delete',
            'Cancel',
            'danger'
        );
        if (!confirmed) return;

        try {
            for (const id of checked) {
                await fetch(`${this.apiBase}/cost-centers.php?id=${id}`, {
                    method: 'DELETE',
                    credentials: 'include'
                });
            }
            this.showToast(`${checked.length} cost center(s) deleted successfully`, 'success');
            this.loadCostCenters();
        } catch (error) {
            this.showToast('Error deleting cost centers', 'error');
        }
    }

    // Bank Guarantee Form Modal
ProfessionalAccounting.prototype.openBankGuaranteeForm = function(id = null) {
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
    }

ProfessionalAccounting.prototype.loadBankGuaranteeData = async function(id) {
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
    }

ProfessionalAccounting.prototype.saveBankGuarantee = async function(id = null) {
        const referenceNumber = document.getElementById('bgReferenceNumber').value.trim();
        const bankName = document.getElementById('bgBankName').value.trim();
        const amount = parseFloat(document.getElementById('bgAmount').value);
        const currency = document.getElementById('bgCurrency').value;
        const issueDate = document.getElementById('bgIssueDate').value;
        const expiryDate = document.getElementById('bgExpiryDate').value || null;
        const status = document.getElementById('bgStatus').value;
        const description = document.getElementById('bgDescription').value.trim();

        if (!referenceNumber || !bankName || !issueDate) {
            this.showToast('Reference number, bank name, and issue date are required', 'error');
            return;
        }

        try {
            const url = id ? `${this.apiBase}/bank-guarantees.php?id=${id}` : `${this.apiBase}/bank-guarantees.php`;
            const method = id ? 'PUT' : 'POST';
            
            const response = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ reference_number: referenceNumber, bank_name: bankName, amount, currency, issue_date: issueDate, expiry_date: expiryDate, status, description })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showToast(data.message || (id ? 'Bank guarantee updated' : 'Bank guarantee created'), 'success');
                await this.closeModalWithConfirmation(document.getElementById('bankGuaranteeFormModal'));
                this.loadBankGuarantees();
            } else {
                this.showToast(data.message || 'Failed to save bank guarantee', 'error');
            }
        } catch (error) {
            this.showToast('Error saving bank guarantee', 'error');
        }
    }

ProfessionalAccounting.prototype.deleteBankGuarantee = async function(id) {
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
    }

ProfessionalAccounting.prototype.deleteSelectedBankGuarantees = async function() {
        const modal = document.getElementById('bankGuaranteeModal');
        if (!modal) return;
        
        const checked = Array.from(modal.querySelectorAll('.bank-guarantee-checkbox:checked')).map(cb => parseInt(cb.value));
        if (checked.length === 0) {
            this.showToast('Please select bank guarantees to delete', 'warning');
            return;
        }

        const confirmed = await this.showConfirmDialog(
            'Delete Bank Guarantees',
            `Are you sure you want to delete ${checked.length} bank guarantee(s)?`,
            'Delete',
            'Cancel',
            'danger'
        );
        if (!confirmed) return;

        try {
            for (const id of checked) {
                await fetch(`${this.apiBase}/bank-guarantees.php?id=${id}`, {
                    method: 'DELETE',
                    credentials: 'include'
                });
            }
            this.showToast(`${checked.length} bank guarantee(s) deleted successfully`, 'success');
            this.loadBankGuarantees();
        } catch (error) {
            this.showToast('Error deleting bank guarantees', 'error');
        }
    }

ProfessionalAccounting.prototype.closeModalWithConfirmation = async function(modalElement = null) {
        // Prevent multiple simultaneous calls
        if (this._closingModal) {
            return;
        }
        this._closingModal = true;
        
        try {
            // Use provided modal or activeModal
            let modalToClose = modalElement || this.activeModal;
        
        if (!modalToClose) {
            // Try to find any visible modal
            modalToClose = document.querySelector('.accounting-modal:not(.accounting-modal-hidden)[data-modal-visible="true"]') ||
                          document.querySelector('.accounting-modal:not(.accounting-modal-hidden)');
        }
        
        if (!modalToClose) {
            this._closingModal = false;
            return;
        }

        // Check for forms with unsaved changes
        const forms = modalToClose.querySelectorAll('form');
        let hasUnsavedChanges = false;

        try {
            for (const form of forms) {
                if (this.hasFormChanges(form)) {
                    hasUnsavedChanges = true;
                    break;
                }
            }
        } catch (e) {
        }

        if (hasUnsavedChanges) {
            try {
                const confirmed = await this.showConfirmDialog(
                    'Unsaved Changes',
                    'You have unsaved changes. Are you sure you want to close without saving?',
                    'Discard Changes',
                    'Cancel',
                    'warning'
                );
                
                if (!confirmed) {
                    this._closingModal = false;
                    return;
                }
            } catch (e) {
                // Continue with closing anyway
            }
        }

        const modalId = modalToClose.id || modalToClose.getAttribute('id') || null;
        
        // Close modal using CSS classes only - no inline styles
        // Remove visibility classes and add hidden class - CSS will handle hiding
        try {
            modalToClose.classList.remove('accounting-modal-visible', 'show-modal');
            modalToClose.classList.add('accounting-modal-hidden');
            modalToClose.removeAttribute('data-modal-visible');
        } catch (e) {
        }
        
        // Remove from DOM immediately
        if (modalToClose.parentNode) {
            try {
                modalToClose.remove();
            } catch (e) {
            }
        }
        
        // Clear activeModal
        if (this.activeModal === modalToClose) {
            this.activeModal = null;
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
            // Only remove if it's been hidden for a bit
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
        
        // Also call closeModal for cleanup (in case it does additional work)
        try {
            this.closeModal(modalId, false); // Pass false to prevent duplicate reload
        } catch (error) {
        }
        
        // Reload dashboard content if we're on the dashboard tab (only once)
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
        } catch (error) {
        } finally {
            // Reset the flag
            this._closingModal = false;
        }
    }

ProfessionalAccounting.prototype.closeModal = function(modalId = null, reloadDashboard = true) {
        
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
    }

    // Helper function to get currency options HTML
ProfessionalAccounting.prototype.getCurrencyOptionsHTML = async function(selectedCurrency = null) {
        // Use CurrencyUtils if available, otherwise fallback to default
        if (window.currencyUtils) {
            try {
                if (!selectedCurrency) {
                    selectedCurrency = this.getDefaultCurrencySync();
                }
                return await window.currencyUtils.getCurrencyOptionsHTML(selectedCurrency);
            } catch (error) {
                console.error('Error fetching currencies:', error);
                // Fallback to default currencies
            }
        }
        
        // Fallback to default currencies
        const currencies = [
            { code: 'SAR', name: 'Saudi Riyal' },
            { code: 'USD', name: 'US Dollar' },
            { code: 'EUR', name: 'Euro' },
            { code: 'GBP', name: 'British Pound' },
            { code: 'CAD', name: 'Canadian Dollar' },
            { code: 'AUD', name: 'Australian Dollar' },
            { code: 'AED', name: 'UAE Dirham' },
            { code: 'KWD', name: 'Kuwaiti Dinar' },
            { code: 'QAR', name: 'Qatari Riyal' },
            { code: 'BHD', name: 'Bahraini Dinar' },
            { code: 'OMR', name: 'Omani Rial' },
            { code: 'JOD', name: 'Jordanian Dinar' },
            { code: 'EGP', name: 'Egyptian Pound' },
            { code: 'JPY', name: 'Japanese Yen' },
            { code: 'CNY', name: 'Chinese Yuan' },
            { code: 'INR', name: 'Indian Rupee' },
            { code: 'PKR', name: 'Pakistani Rupee' }
        ];
        if (!selectedCurrency) {
            selectedCurrency = localStorage.getItem('accounting_last_currency') || 'SAR';
        }
        // Normalize selectedCurrency - extract code if format is "CODE - Name" or handle empty/0
        let normalizedCurrency = selectedCurrency;
        if (typeof normalizedCurrency === 'string') {
            if (normalizedCurrency.includes(' - ')) {
                normalizedCurrency = normalizedCurrency.split(' - ')[0].trim();
            }
            normalizedCurrency = normalizedCurrency.toUpperCase().trim();
            // Handle invalid currency codes
            if (!normalizedCurrency || normalizedCurrency === '0' || normalizedCurrency === '') {
                normalizedCurrency = 'SAR';
            }
        } else {
            normalizedCurrency = 'SAR';
        }
        return currencies.map(currency => `<option value="${currency.code}" ${normalizedCurrency === currency.code ? 'selected' : ''}>${currency.code} - ${currency.name}</option>`).join('');
    }

    // Modal Content Generators
ProfessionalAccounting.prototype.getJournalEntryModalContent = function(entryId = null) {
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
    }

ProfessionalAccounting.prototype.getInvoiceModalContent = function(invoiceId = null) {
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
                    <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">${isEdit ? 'Update' : 'Create'} Invoice</button>
                </div>
            </form>
        `;
    }

ProfessionalAccounting.prototype.getBillModalContent = function(billId = null) {
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
    }

ProfessionalAccounting.prototype.openBankAccountForm = async function(bankId = null) {
        // Prevent opening form if save is in progress
        if (this._savingBankAccount) {
            this.showToast('Please wait for the current operation to complete', 'warning');
            return;
        }
        
        const isEdit = bankId !== null;
        let bankData = null;
        
        if (isEdit) {
            try {
                const response = await fetch(`${this.apiBase}/banks.php?id=${bankId}`);
                const data = await response.json();
                if (data.success && data.bank) {
                    bankData = data.bank;
                } else {
                    this.showToast(data.message || 'Failed to load bank account', 'error');
                    return;
                }
            } catch (error) {
                this.showToast('Error loading bank account: ' + error.message, 'error');
                return;
            }
        }
        
        const formContent = this.getBankAccountModalContent(bankData);
        this.showModal(isEdit ? 'Edit Bank Account' : 'Add Bank Account', formContent, 'normal', 'bankAccountFormModal');
        
        // Setup form submit handler - use requestAnimationFrame for immediate attachment
        // Store frame ID to prevent multiple callbacks
        let frameId = null;
        frameId = requestAnimationFrame(() => {
            frameId = null; // Clear frame ID
            const form = document.getElementById('bankAccountForm');
            // Double check form still exists (modal might have been closed)
            if (form && form.isConnected && !form.hasAttribute('data-handler-attached')) {
                // Remove existing listeners by cloning
                const newForm = form.cloneNode(true);
                form.parentNode.replaceChild(newForm, form);
                
                // Ensure data-bank-id is set on the form
                if (bankId) {
                    newForm.setAttribute('data-bank-id', bankId);
                } else {
                    newForm.setAttribute('data-bank-id', 'null');
                }
                
                // Mark as handler attached to prevent duplicate handlers
                newForm.setAttribute('data-handler-attached', 'true');
                
                // Prevent form submission via Enter key or other means
                // Use capture phase to catch all submissions first
                newForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    
                    // Additional guard check - double protection
                    if (this._savingBankAccount) {
                        console.warn('Form submission blocked: save already in progress');
                        return;
                    }
                    
                    // Disable submit button to prevent double submission
                    const submitBtn = newForm.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Saving...';
                    }
                    
                    // Get bankId from form attribute (most reliable)
                    const formBankId = newForm.getAttribute('data-bank-id');
                    const id = formBankId && formBankId !== 'null' ? parseInt(formBankId) : null;
                    
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
        });
    }

ProfessionalAccounting.prototype.getBankAccountModalContent = function(bankData = null, bankIdParam = null) {
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
    }

ProfessionalAccounting.prototype.getEntityTransactionModalContent = async function(transactionId = null) {
        // Use same modal structure as journal entry form - with tabs
        let formHTML;
        try {
            formHTML = await this.getEntityTransactionFormHTML(transactionId);
        } catch (error) {
            // If there's an error, fallback to new transaction form
            formHTML = await this.getEntityTransactionFormHTML(null);
        }
        
        // If formHTML is empty (error occurred), use new transaction form
        if (!formHTML) {
            formHTML = await this.getEntityTransactionFormHTML(null);
        }
        
        const modalContent = `
            <div class="accounting-tabs">
                <button class="tab-btn active" data-tab="transactions">
                    <i class="fas fa-exchange-alt"></i> Transactions
                </button>
                <button class="tab-btn" data-tab="summary">
                    <i class="fas fa-chart-bar"></i> Summary
                </button>
                <button class="tab-btn" data-tab="reports">
                    <i class="fas fa-file-pdf"></i> Reports
                </button>
            </div>
            
            <!-- Transactions Tab -->
            <div id="transactionsTab" class="tab-content active">
                <div class="transactions-layout">
                    <div class="form-section">
                        <div class="form-content-wrapper">
                            ${formHTML}
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
                                <a href="${(window.APP_CONFIG && window.APP_CONFIG.baseUrl) || ''}/pages/accounting.php" class="btn btn-sm btn-primary" target="_blank" title="View in Accounting System">
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
                                        <th>Account</th>
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
            
            <!-- Summary Tab -->
            <div id="summaryTab" class="tab-content">
                <div id="summaryLoading" class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i> Loading summary...
                </div>
                <div id="summaryContent" class="accounting-summary-content-hidden">
                    <div class="summary-cards">
                        <div class="summary-card revenue">
                            <div class="card-icon"><i class="fas fa-arrow-up"></i></div>
                            <div class="card-content">
                                <h4>Total Revenue</h4>
                                <p id="totalRevenue">0.00</p>
                            </div>
                        </div>
                        <div class="summary-card expense">
                            <div class="card-icon"><i class="fas fa-arrow-down"></i></div>
                            <div class="card-content">
                                <h4>Total Expenses</h4>
                                <p id="totalExpenses">0.00</p>
                            </div>
                        </div>
                        <div class="summary-card profit">
                            <div class="card-icon"><i class="fas fa-chart-line"></i></div>
                            <div class="card-content">
                                <h4>Net Profit</h4>
                                <p id="netProfit">0.00</p>
                            </div>
                        </div>
                        <div class="summary-card month">
                            <div class="card-icon"><i class="fas fa-calendar"></i></div>
                            <div class="card-content">
                                <h4>This Month</h4>
                                <p id="thisMonth">0.00</p>
                            </div>
                        </div>
                    </div>
                    <div class="summary-stats">
                        <div class="stat-item">
                            <span class="stat-label">Total Transactions</span>
                            <span class="stat-value" id="transactionCount">0</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Reports Tab -->
            <div id="reportsTab" class="tab-content">
                <div class="reports-content">
                    <p>Reports functionality coming soon...</p>
                </div>
            </div>
        `;
        
        return modalContent;
    }
    
ProfessionalAccounting.prototype.getEntityTransactionFormHTML = async function(transactionId = null) {
        const isEdit = !!transactionId;
        let formHTML = '';
        
        if (isEdit) {
            // Load existing transaction data
            try {
                const response = await fetch(`${this.apiBase}/entity-transactions.php?id=${transactionId}`);
                
                const data = await response.json();
                
                if (!response.ok) {
                    const errorMsg = data.error || data.message || response.statusText;
                    throw new Error(`HTTP ${response.status}: ${errorMsg}`);
                }
                
                if (data.success && data.transaction) {
                    const trans = data.transaction;
                    // Normalize entity type to lowercase for comparison
                    const entityType = (trans.entity_type || '').toLowerCase();
                    formHTML = `
                        <form id="entityTransactionForm" data-transaction-id="${transactionId}">
                            <!-- Row 1: Account, Date, Currency -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="entityTransactionAccount">Account <span class="required">*</span></label>
                                    <select class="form-control" name="account_id" id="entityTransactionAccount" required>
                                        <option value="">Loading accounts...</option>
                                </select>
                            </div>
                                <div class="form-group">
                                    <label for="entityTransactionDate">Date <span class="required">*</span></label>
                                    <input type="text" class="form-control date-input" id="entityTransactionDate" name="transaction_date" required value="${trans.transaction_date ? this.formatDateForInput(trans.transaction_date) : this.formatDateForInput(new Date().toISOString())}" placeholder="MM/DD/YYYY">
                            </div>
                                <div class="form-group">
                                    <label for="entityTransactionCurrency">Currency <span class="required">*</span></label>
                                    <select class="form-control" id="entityTransactionCurrency" name="currency" required>
                                    ${(function() {
                                        // Normalize currency before passing to getCurrencyOptionsHTML
                                        let currencyValue = trans.currency || null;
                                        if (currencyValue && typeof currencyValue === 'string') {
                                            // Extract code if format is "CODE - Name"
                                            if (currencyValue.includes(' - ')) {
                                                currencyValue = currencyValue.split(' - ')[0].trim();
                                            }
                                            currencyValue = currencyValue.toUpperCase().trim();
                                            // Handle invalid currency codes
                                            if (!currencyValue || currencyValue === '0' || currencyValue === '') {
                                                currencyValue = this.getDefaultCurrencySync();
                                            }
                                        } else if (!currencyValue) {
                                            currencyValue = this.getDefaultCurrencySync();
                                        }
                                        // Use the instance method correctly
                                        const self = this;
                                        const optionsHTML = self.getCurrencyOptionsHTML(currencyValue);
                                        return optionsHTML;
                                    }).call(this)}
                                </select>
                            </div>
                            </div>
                            <!-- Row 3: Debit, Credit, Type, Status (4 columns) -->
                            <div class="form-row form-row-4cols">
                                <div class="form-group">
                                    <label for="entityTransactionDebit" class="debit-label">Debit</label>
                                    <input type="number" class="form-control debit-input" id="entityTransactionDebit" name="debit" step="0.01" placeholder="0.00" value="${trans.debit_amount || trans.debit || (trans.transaction_type === 'Expense' ? trans.total_amount : 0) || ''}">
                                </div>
                                <div class="form-group">
                                    <label for="entityTransactionCredit" class="credit-label">Credit</label>
                                    <input type="number" class="form-control credit-input" id="entityTransactionCredit" name="credit" step="0.01" placeholder="0.00" value="${trans.credit_amount || trans.credit || (trans.transaction_type === 'Income' ? trans.total_amount : 0) || ''}">
                                </div>
                                <div class="form-group">
                                    <label for="entityTransactionType">Type</label>
                                    <select class="form-control" id="entityTransactionType" name="entry_type">
                                        ${(() => {
                                            // Only use entry_type, not transaction_type (they're different)
                                            const entryType = trans.entry_type || 'Manual';
                                            const validTypes = ['Manual', 'Automatic', 'Recurring', 'Adjustment', 'Reversal'];
                                            const selectedType = validTypes.includes(entryType) ? entryType : 'Manual';
                                            const optionsHTML = validTypes.map(type => 
                                                `<option value="${type}" ${type === selectedType ? 'selected' : ''}>${type}</option>`
                                            ).join('');
                                            return optionsHTML;
                                        })()}
                                </select>
                            </div>
                                <div class="form-group">
                                    <label for="entityTransactionStatus">Status</label>
                                    <input type="text" class="form-control" id="entityTransactionStatus" name="status" value="${trans.status || 'Posted'}" readonly>
                            </div>
                            </div>
                            <!-- Row 4: Description (full width) -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="entityTransactionDescription">Description <span class="required">*</span></label>
                                    <textarea class="form-control" id="entityTransactionDescription" name="description" placeholder="Enter journal entry description" required rows="4">${this.escapeHtml(trans.description || '')}</textarea>
                            </div>
                            </div>
                            <!-- Row 5: Reference (full width) -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="entityTransactionReference">Reference Number</label>
                                    <input type="text" class="form-control" id="entityTransactionReference" name="reference_number" placeholder="Auto-generated if empty" value="${this.escapeHtml(trans.reference_number || trans.reference || '')}">
                                </div>
                            </div>
                            <div class="accounting-modal-actions">
                                <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Save Transaction</button>
                            </div>
                        </form>
                    `;
                } else {
                    throw new Error(`Transaction not found. API returned: ${JSON.stringify(data)}`);
                }
            } catch (error) {
                this.showToast('Failed to load transaction data: ' + error.message, 'error');
                // Return empty form HTML instead of throwing to prevent recursion
                formHTML = '';
            }
        } else {
            // New transaction form - same structure as journal entry form
            formHTML = `
                <form id="entityTransactionForm" data-transaction-id="null">
                    <!-- Row 1: Account, Date, Currency -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="entityTransactionAccount">Account <span class="required">*</span></label>
                            <select class="form-control" name="account_id" id="entityTransactionAccount" required>
                                <option value="">Loading accounts...</option>
                        </select>
                    </div>
                        <div class="form-group">
                            <label for="entityTransactionDate">Date <span class="required">*</span></label>
                            <input type="text" class="form-control date-input" id="entityTransactionDate" name="transaction_date" required value="${this.formatDateForInput(new Date().toISOString())}" placeholder="MM/DD/YYYY">
                        </div>
                        <div class="form-group">
                            <label for="entityTransactionCurrency">Currency <span class="required">*</span></label>
                            <select class="form-control" id="entityTransactionCurrency" name="currency" required>
                            ${this.getCurrencyOptionsHTML()}
                        </select>
                    </div>
                    </div>
                    <!-- Row 3: Debit, Credit, Type, Status (4 columns) -->
                    <div class="form-row form-row-4cols">
                        <div class="form-group">
                            <label for="entityTransactionDebit" class="debit-label">Debit</label>
                            <input type="number" class="form-control debit-input" id="entityTransactionDebit" name="debit" step="0.01" placeholder="0.00">
                    </div>
                        <div class="form-group">
                            <label for="entityTransactionCredit" class="credit-label">Credit</label>
                            <input type="number" class="form-control credit-input" id="entityTransactionCredit" name="credit" step="0.01" placeholder="0.00">
                    </div>
                        <div class="form-group">
                            <label for="entityTransactionType">Type</label>
                            <select class="form-control" id="entityTransactionType" name="entry_type">
                                <option value="Manual" selected>Manual</option>
                                <option value="Automatic">Automatic</option>
                                <option value="Recurring">Recurring</option>
                                <option value="Adjustment">Adjustment</option>
                                <option value="Reversal">Reversal</option>
                            </select>
                    </div>
                        <div class="form-group">
                            <label for="entityTransactionStatus">Status</label>
                            <input type="text" class="form-control" id="entityTransactionStatus" name="status" value="Posted" readonly>
                        </div>
                    </div>
                    <!-- Row 4: Description (full width) -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="entityTransactionDescription">Description <span class="required">*</span></label>
                            <textarea class="form-control" id="entityTransactionDescription" name="description" placeholder="Enter journal entry description" required rows="4"></textarea>
                        </div>
                    </div>
                    <!-- Row 5: Reference (full width) -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="entityTransactionReference">Reference Number</label>
                            <input type="text" class="form-control" id="entityTransactionReference" name="reference_number" placeholder="Auto-generated if empty">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" data-action="close-modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Transaction
                        </button>
                    </div>
                </form>
            `;
        }
        
        return formHTML;
    }

ProfessionalAccounting.prototype.openChartOfAccountsModal = function() {
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
    }

ProfessionalAccounting.prototype.setupChartOfAccountsFilters = function() {
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
    }
    
ProfessionalAccounting.prototype.updateCoaSortIndicators = function() {
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
    }
    
ProfessionalAccounting.prototype.updateCoaSelectAll = function() {
        const selectAll = document.getElementById('coaSelectAll');
        if (selectAll) {
            const checkboxes = document.querySelectorAll('.coa-row-checkbox');
            const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            selectAll.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
            selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
        }
    }
    
ProfessionalAccounting.prototype.updateCoaPagination = function() {
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
    }
    
ProfessionalAccounting.prototype.updateCoaBulkActions = function() {
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
    }
    
ProfessionalAccounting.prototype.scrollToCoaTable = function() {
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