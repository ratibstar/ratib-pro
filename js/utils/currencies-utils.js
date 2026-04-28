/**
 * EN: Implements frontend interaction behavior in `js/utils/currencies-utils.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/utils/currencies-utils.js`.
 */
/**
 * Currency Utilities - Fetch and manage currencies from System Settings
 * Used by: Agent, SubAgent, Workers, Accounting, HR modules
 */

class CurrencyUtils {
    constructor() {
        this.currenciesCache = null;
        this.cacheTimestamp = null;
        this.cacheDuration = 5 * 60 * 1000; // 5 minutes cache
        const el = typeof document !== 'undefined' ? document.getElementById('app-config') : null;
        const cpHr = (window.APP_CONFIG && window.APP_CONFIG.controlHrApiBase) || (el && el.getAttribute('data-control-hr-api-base'));
        const controlPath = (window.APP_CONFIG && window.APP_CONFIG.controlApiPath) || (el && el.getAttribute('data-control-api-path'));
        if (cpHr && controlPath) {
            this.apiUrl = String(controlPath).replace(/\/$/, '') + '/hr-currencies.php';
            this.isControlHrCurrencies = true;
        } else {
            this.isControlHrCurrencies = false;
            let apiRoot = ((window.APP_CONFIG && window.APP_CONFIG.apiBase) || (window.API_BASE || '')).replace(/\/$/, '');
            if (!apiRoot) {
                const baseUrl = ((window.APP_CONFIG && window.APP_CONFIG.baseUrl) || (window.BASE_PATH || '')).replace(/\/$/, '');
                apiRoot = baseUrl ? baseUrl + '/api' : '/api';
            }
            this.apiUrl = apiRoot + '/settings/currencies-api.php';
        }
    }

    /**
     * Fetch currencies from API
     * @param {boolean} forceRefresh - Force refresh cache
     * @returns {Promise<Array>} Array of currency objects {code, name, symbol, label}
     */
    async fetchCurrencies(forceRefresh = false) {
        // Return cached data if available and not expired
        if (!forceRefresh && this.currenciesCache && this.cacheTimestamp) {
            const now = Date.now();
            if (now - this.cacheTimestamp < this.cacheDuration) {
                return this.currenciesCache;
            }
        }

        try {
            // Add cache busting to ensure fresh data (always fetch latest active currencies)
            const timestamp = Date.now();
            let url = this.apiUrl + (this.apiUrl.includes('?') ? '&' : '?') + '_t=' + timestamp;
            if (!this.isControlHrCurrencies) {
                const el = document.getElementById('app-config');
                if (el && (el.getAttribute('data-control') === '1' || el.getAttribute('data-control-pro-bridge') === '1')) {
                    url += '&control=1';
                }
            }
            const response = await fetch(url, {
                cache: 'no-cache',
                credentials: 'include',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            });
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.currencies) {
                // API already filters for active currencies, so use them as-is
                // Ensure each currency has a label field
                const formattedCurrencies = data.currencies.map(c => ({
                    code: c.code,
                    name: c.name,
                    symbol: c.symbol || '',
                    label: c.label || `${c.code} - ${c.name}`
                }));
                this.currenciesCache = formattedCurrencies;
                this.cacheTimestamp = Date.now();
                if (formattedCurrencies.length === 0) {
                    console.warn('⚠️ No active currencies found! Please activate currencies in System Settings.');
                }
                return formattedCurrencies;
            } else {
                throw new Error(data.message || 'Failed to fetch currencies');
            }
        } catch (error) {
            console.error('❌ Error fetching currencies:', error);
            // Return default currencies if API fails
            return this.getDefaultCurrencies();
        }
    }

    /**
     * Get default currencies (fallback)
     * @returns {Array} Array of default currency objects
     */
    getDefaultCurrencies() {
        return [
            { code: 'SAR', name: 'Saudi Riyal', symbol: '﷼', label: 'SAR - Saudi Riyal' },
            { code: 'USD', name: 'US Dollar', symbol: '$', label: 'USD - US Dollar' },
            { code: 'EUR', name: 'Euro', symbol: '€', label: 'EUR - Euro' },
            { code: 'GBP', name: 'British Pound', symbol: '£', label: 'GBP - British Pound' },
            { code: 'CAD', name: 'Canadian Dollar', symbol: 'C$', label: 'CAD - Canadian Dollar' },
            { code: 'AUD', name: 'Australian Dollar', symbol: 'A$', label: 'AUD - Australian Dollar' },
            { code: 'AED', name: 'UAE Dirham', symbol: 'د.إ', label: 'AED - UAE Dirham' },
            { code: 'KWD', name: 'Kuwaiti Dinar', symbol: 'د.ك', label: 'KWD - Kuwaiti Dinar' },
            { code: 'QAR', name: 'Qatari Riyal', symbol: '﷼', label: 'QAR - Qatari Riyal' },
            { code: 'BHD', name: 'Bahraini Dinar', symbol: '.د.ب', label: 'BHD - Bahraini Dinar' },
            { code: 'OMR', name: 'Omani Rial', symbol: 'ر.ع.', label: 'OMR - Omani Rial' },
            { code: 'JOD', name: 'Jordanian Dinar', symbol: 'د.ا', label: 'JOD - Jordanian Dinar' },
            { code: 'EGP', name: 'Egyptian Pound', symbol: '£', label: 'EGP - Egyptian Pound' },
            { code: 'JPY', name: 'Japanese Yen', symbol: '¥', label: 'JPY - Japanese Yen' },
            { code: 'CNY', name: 'Chinese Yuan', symbol: '¥', label: 'CNY - Chinese Yuan' },
            { code: 'INR', name: 'Indian Rupee', symbol: '₹', label: 'INR - Indian Rupee' },
            { code: 'PKR', name: 'Pakistani Rupee', symbol: '₨', label: 'PKR - Pakistani Rupee' },
            { code: 'BDT', name: 'Bangladeshi Taka', symbol: '৳', label: 'BDT - Bangladeshi Taka' },
            { code: 'PHP', name: 'Philippine Peso', symbol: '₱', label: 'PHP - Philippine Peso' },
            { code: 'IDR', name: 'Indonesian Rupiah', symbol: 'Rp', label: 'IDR - Indonesian Rupiah' },
            { code: 'THB', name: 'Thai Baht', symbol: '฿', label: 'THB - Thai Baht' },
            { code: 'MYR', name: 'Malaysian Ringgit', symbol: 'RM', label: 'MYR - Malaysian Ringgit' },
            { code: 'SGD', name: 'Singapore Dollar', symbol: 'S$', label: 'SGD - Singapore Dollar' },
            { code: 'KRW', name: 'South Korean Won', symbol: '₩', label: 'KRW - South Korean Won' },
            { code: 'BRL', name: 'Brazilian Real', symbol: 'R$', label: 'BRL - Brazilian Real' },
            { code: 'MXN', name: 'Mexican Peso', symbol: '$', label: 'MXN - Mexican Peso' },
            { code: 'TRY', name: 'Turkish Lira', symbol: '₺', label: 'TRY - Turkish Lira' },
            { code: 'ZAR', name: 'South African Rand', symbol: 'R', label: 'ZAR - South African Rand' }
        ];
    }

    /**
     * Generate HTML options for currency select dropdown
     * @param {string} selectedCurrency - Currently selected currency code
     * @returns {Promise<string>} HTML string of option elements
     */
    async getCurrencyOptionsHTML(selectedCurrency = null) {
        const currencies = await this.fetchCurrencies();
        let normalizedCurrency = selectedCurrency;
        
        // Normalize selected currency
        if (typeof normalizedCurrency === 'string') {
            if (normalizedCurrency.includes(' - ')) {
                normalizedCurrency = normalizedCurrency.split(' - ')[0].trim();
            }
            normalizedCurrency = normalizedCurrency.toUpperCase().trim();
        }
        
        return currencies.map(currency => {
            const isSelected = normalizedCurrency === currency.code ? 'selected' : '';
            return `<option value="${currency.code}" ${isSelected}>${currency.label}</option>`;
        }).join('');
    }

    /**
     * Populate a currency select element
     * @param {string|HTMLElement} selectElement - ID or DOM element of select
     * @param {string} selectedCurrency - Currently selected currency code
     */
    async populateCurrencySelect(selectElement, selectedCurrency = null) {
        const select = typeof selectElement === 'string' 
            ? document.getElementById(selectElement) 
            : selectElement;
        
        if (!select) {
            console.error('❌ Currency select element not found:', selectElement);
            return;
        }

        try {
            // Fetch active currencies from API (only active currencies are returned)
            const currencies = await this.fetchCurrencies(true); // Force refresh to get latest active currencies
            
            // Build options HTML
            let normalizedCurrency = selectedCurrency;
            if (normalizedCurrency && typeof normalizedCurrency === 'string' && normalizedCurrency.includes(' - ')) {
                normalizedCurrency = normalizedCurrency.split(' - ')[0].trim();
            }
            normalizedCurrency = normalizedCurrency ? normalizedCurrency.toUpperCase().trim() : null;
            
            // Generate options
            let optionsHTML = currencies.map(c => {
                const isSelected = normalizedCurrency && normalizedCurrency === c.code ? 'selected' : '';
                return `<option value="${c.code}" ${isSelected}>${c.label}</option>`;
            }).join('');
            
            // Set innerHTML with default option + currency options
            select.innerHTML = '<option value="">Select Currency</option>' + optionsHTML;
            
            // Auto-select if only one currency exists (and no currency was explicitly selected)
            if (!normalizedCurrency && currencies.length === 1) {
                select.value = currencies[0].code;
            } else if (normalizedCurrency) {
                // Set selected value if provided
                select.value = normalizedCurrency;
            }
        } catch (error) {
            console.error('❌ Error populating currency select:', error);
            // Don't use fallback - show empty dropdown if API fails
            // This ensures users know they need to activate currencies
            select.innerHTML = '<option value="">No active currencies found. Please activate currencies in System Settings.</option>';
        }
    }

    /**
     * Clear cache (useful after currency updates)
     */
    clearCache() {
        this.currenciesCache = null;
        this.cacheTimestamp = null;
    }
}

// Create global instance
window.currencyUtils = new CurrencyUtils();

// Initialize currency utils on load
if (typeof window !== 'undefined') {
    window.addEventListener('DOMContentLoaded', async () => {
        if (window.currencyUtils) {
            try {
                await window.currencyUtils.fetchCurrencies(true); // Force refresh on load
            } catch (error) {
                console.error('❌ CurrencyUtils initialization failed:', error);
            }
        }
    });
}
