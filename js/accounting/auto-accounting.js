/**
 * EN: Implements frontend interaction behavior in `js/accounting/auto-accounting.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/auto-accounting.js`.
 */
/**
 * Automatic Accounting Integration Module
 * Automatically records transactions when financial events occur in entities
 * Supports: agents, subagents, workers, hr
 */

class AutoAccounting {
    constructor() {
        this.apiBase = ((window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '')) + '/accounting';
        this.init();
    }

    init() {
        // Listen for financial events across all entity pages
        this.setupGlobalListeners();
    }

    /**
     * Automatically record a transaction for an entity
     * @param {Object} params - Transaction parameters
     * @param {string} params.entityType - 'agent', 'subagent', 'worker', 'hr'
     * @param {number} params.entityId - Entity ID
     * @param {string} params.transactionType - 'Income' or 'Expense'
     * @param {number} params.amount - Transaction amount
     * @param {string} params.description - Transaction description
     * @param {string} params.category - Transaction category (optional)
     * @param {string} params.referenceNumber - Reference number (optional)
     * @param {string} params.transactionDate - Transaction date (optional, defaults to today)
     * @param {string} params.status - Transaction status (optional, defaults to 'Posted')
     * @returns {Promise<Object>} - API response
     */
    async recordTransaction(params) {
        try {
            const {
                entityType,
                entityId,
                transactionType,
                amount,
                description,
                category = 'other',
                referenceNumber = null,
                transactionDate = new Date().toISOString().split('T')[0],
                status = 'Posted'
            } = params;

            // Validate required parameters
            if (!entityType || !entityId || !transactionType || !amount || !description) {
                throw new Error('Missing required parameters: entityType, entityId, transactionType, amount, description');
            }

            // Validate transaction type
            if (!['Income', 'Expense'].includes(transactionType)) {
                throw new Error('Transaction type must be "Income" or "Expense"');
            }

            // Validate amount
            const amountNum = parseFloat(amount);
            if (isNaN(amountNum) || amountNum <= 0) {
                throw new Error('Amount must be a positive number');
            }

            const response = await fetch(`${this.apiBase}/auto-record-transaction.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    entity_type: entityType,
                    entity_id: entityId,
                    transaction_type: transactionType,
                    amount: amountNum,
                    description: description,
                    category: category,
                    reference_number: referenceNumber,
                    transaction_date: transactionDate,
                    status: status
                })
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Failed to record transaction');
            }

            // Show success notification
            this.showNotification('Transaction recorded successfully', 'success');

            return data;
        } catch (error) {
            this.showNotification(`Failed to record transaction: ${error.message}`, 'error');
            throw error;
        }
    }

    /**
     * Record a commission payment (Income for agent/subagent)
     */
    async recordCommission(entityType, entityId, amount, description, referenceNumber = null) {
        return this.recordTransaction({
            entityType,
            entityId,
            transactionType: 'Income',
            amount,
            description: description || `Commission Payment - ${entityType}`,
            category: 'commission',
            referenceNumber
        });
    }

    /**
     * Record a salary payment (Expense for HR/Worker)
     */
    async recordSalary(entityType, entityId, amount, description, referenceNumber = null) {
        return this.recordTransaction({
            entityType,
            entityId,
            transactionType: 'Expense',
            amount,
            description: description || `Salary Payment - ${entityType}`,
            category: 'salary',
            referenceNumber
        });
    }

    /**
     * Record a bonus payment
     */
    async recordBonus(entityType, entityId, amount, description, referenceNumber = null) {
        return this.recordTransaction({
            entityType,
            entityId,
            transactionType: entityType === 'hr' || entityType === 'worker' ? 'Expense' : 'Income',
            amount,
            description: description || `Bonus Payment - ${entityType}`,
            category: 'bonus',
            referenceNumber
        });
    }

    /**
     * Record a payment/refund
     */
    async recordPayment(entityType, entityId, amount, description, isRefund = false, referenceNumber = null) {
        return this.recordTransaction({
            entityType,
            entityId,
            transactionType: isRefund ? 'Expense' : 'Income',
            amount,
            description: description || `${isRefund ? 'Refund' : 'Payment'} - ${entityType}`,
            category: isRefund ? 'refund' : 'payment',
            referenceNumber
        });
    }

    /**
     * Record a general expense
     */
    async recordExpense(entityType, entityId, amount, description, category = 'expense', referenceNumber = null) {
        return this.recordTransaction({
            entityType,
            entityId,
            transactionType: 'Expense',
            amount,
            description,
            category,
            referenceNumber
        });
    }

    /**
     * Setup global event listeners for automatic transaction recording
     */
    setupGlobalListeners() {
        // Listen for custom events that entities can dispatch
        document.addEventListener('accounting:record-commission', async (e) => {
            const { entityType, entityId, amount, description, referenceNumber } = e.detail;
            await this.recordCommission(entityType, entityId, amount, description, referenceNumber);
        });

        document.addEventListener('accounting:record-salary', async (e) => {
            const { entityType, entityId, amount, description, referenceNumber } = e.detail;
            await this.recordSalary(entityType, entityId, amount, description, referenceNumber);
        });

        document.addEventListener('accounting:record-bonus', async (e) => {
            const { entityType, entityId, amount, description, referenceNumber } = e.detail;
            await this.recordBonus(entityType, entityId, amount, description, referenceNumber);
        });

        document.addEventListener('accounting:record-payment', async (e) => {
            const { entityType, entityId, amount, description, isRefund, referenceNumber } = e.detail;
            await this.recordPayment(entityType, entityId, amount, description, isRefund, referenceNumber);
        });

        document.addEventListener('accounting:record-expense', async (e) => {
            const { entityType, entityId, amount, description, category, referenceNumber } = e.detail;
            await this.recordExpense(entityType, entityId, amount, description, category, referenceNumber);
        });

        document.addEventListener('accounting:record-transaction', async (e) => {
            await this.recordTransaction(e.detail);
        });
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        // Use existing toast system if available
        if (window.accountingSystem && typeof window.accountingSystem.showToast === 'function') {
            window.accountingSystem.showToast(message, type);
        } else if (window.accountingModal && typeof window.accountingModal.showNotification === 'function') {
            window.accountingModal.showNotification(message, type);
        } else {
            // Fallback to console
        }
    }

    /**
     * Get entity name for display
     */
    async getEntityName(entityType, entityId) {
        try {
            const entityTableMap = {
                'agent': 'agents',
                'subagent': 'subagents',
                'worker': 'workers',
                'hr': 'hr_employees'
            };

            const tableName = entityTableMap[entityType];
            if (!tableName) return `${entityType} #${entityId}`;

            // This would need to be implemented via an API endpoint
            // For now, return a placeholder
            return `${entityType} #${entityId}`;
        } catch (error) {
            return `${entityType} #${entityId}`;
        }
    }
}

// Initialize global auto-accounting instance
window.autoAccounting = new AutoAccounting();

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AutoAccounting;
}

