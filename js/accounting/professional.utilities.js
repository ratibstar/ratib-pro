/**
 * EN: Implements frontend interaction behavior in `js/accounting/professional.utilities.js`.
 * AR: ينفذ سلوك تفاعلات الواجهة الأمامية في `js/accounting/professional.utilities.js`.
 */
/**
 * Professional Accounting - Utilities
 * Load AFTER professional.js
 */
(function(){
    if (typeof ProfessionalAccounting === 'undefined') return;
    const P = ProfessionalAccounting.prototype;

    P.formatDate = function(dateString) {
        if (!dateString) return '-';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return dateString;
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${month}/${day}/${year}`;
        } catch (e) {
            return dateString;
        }
    };

    P.formatDateForInput = function(dateString) {
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
    };

    P.formatDateForAPI = function(dateString) {
        if (!dateString) return '';
        try {
            if (dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
                return dateString;
            }
            if (dateString.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/)) {
                const parts = dateString.split('/');
                const month = parts[0].padStart(2, '0');
                const day = parts[1].padStart(2, '0');
                const year = parts[2];
                return `${year}-${month}-${day}`;
            }
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return '';
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        } catch (e) {
            return '';
        }
    };

    P.formatCurrency = function(amount, currency = null) {
        let validCurrency = currency || this.getDefaultCurrencySync();
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
            return new Intl.NumberFormat('en-SA', {
                style: 'currency',
                currency: 'SAR',
                minimumFractionDigits: 2
            }).format(amount || 0);
        }
    };

    P.escapeHtml = function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    P.isValidDate = function(dateString) {
        const regex = /^\d{4}-\d{2}-\d{2}$/;
        if (!regex.test(dateString)) return false;
        const date = new Date(dateString);
        return date instanceof Date && !isNaN(date);
    };

    P.showDateError = function(input, message) {
        let errorMsg = input.parentElement.querySelector('.date-validation-message');
        if (!errorMsg) {
            errorMsg = document.createElement('div');
            errorMsg.className = 'date-validation-message';
            input.parentElement.appendChild(errorMsg);
        }
        errorMsg.textContent = message;
        errorMsg.classList.add('show');
    };

    P.hideDateError = function(input) {
        const errorMsg = input.parentElement.querySelector('.date-validation-message');
        if (errorMsg) {
            errorMsg.classList.remove('show');
        }
    };

    P.getQuickDatePresets = function() {
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
    };

    P.applyQuickDatePreset = function(preset) {
        const startDateInput = document.getElementById('reportStartDate');
        const endDateInput = document.getElementById('reportEndDate');
        if (startDateInput && preset.start) {
            startDateInput.value = preset.start;
        }
        if (endDateInput && preset.end) {
            endDateInput.value = preset.end;
        }
        if (startDateInput) startDateInput.dispatchEvent(new Event('change'));
        if (endDateInput) endDateInput.dispatchEvent(new Event('change'));
        this.showToast(`Applied ${preset.label} preset`, 'success');
    };

    P.hasFormChanges = function(form) {
        if (!form) return false;
        if (form.hasAttribute('data-unsaved')) {
            return true;
        }
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
    };

    P.markFormAsChanged = function(form) {
        if (form) {
            form.setAttribute('data-unsaved', 'true');
        }
    };

    P.markFormAsSaved = function(form) {
        if (form) {
            form.removeAttribute('data-unsaved');
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.defaultChecked = input.checked;
                } else {
                    input.defaultValue = input.value;
                }
            });
        }
    };

    P.getDefaultCurrencySync = function() {
        const storedCurrency = localStorage.getItem('accounting_default_currency');
        return storedCurrency && storedCurrency.length === 3 ? storedCurrency : 'SAR';
    };

    P.createStatCard = function(type, icon, value, label) {
        const typeClass = `stat-card-${type}`;
        const iconClass = `stat-icon-${type}`;
        return `<div class="stat-card ${typeClass}">
            <i class="fas ${icon} stat-icon ${iconClass}"></i>
            <div class="stat-info">
                <span class="stat-value">${value}</span>
                <span class="stat-label">${label}</span>
            </div>
        </div>`;
    };

    P.isElementMeasurable = function(element) {
        if (!element || !element.getBoundingClientRect) {
            return false;
        }
        const style = window.getComputedStyle(element);
        if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
            return false;
        }
        let parent = element.parentElement;
        while (parent && parent !== document.body) {
            const parentStyle = window.getComputedStyle(parent);
            if (parentStyle.display === 'none') {
                return false;
            }
            parent = parent.parentElement;
        }
        const rect = element.getBoundingClientRect();
        return rect.width > 0 || rect.height > 0;
    };

    P.toggleDeleteButton = function(modal, checkboxSelector, buttonSelector) {
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
    };

    P.showToast = function(message, type = 'info', duration = 5000) {
        const existingToasts = document.querySelectorAll('.accounting-toast');
        existingToasts.forEach(toast => {
            toast.classList.add('accounting-toast-removing');
            setTimeout(() => toast.remove(), 300);
        });
        const toast = document.createElement('div');
        toast.className = `accounting-toast accounting-toast-${type}`;
        toast.innerHTML = `<div>${this.escapeHtml(message)}</div>`;
        toast.setAttribute('data-no-permissions', 'true');
        const oldNotifications = document.querySelectorAll('.accounting-notification');
        oldNotifications.forEach(n => {
            n.classList.add('notification-hidden');
            n.remove();
        });
        document.body.appendChild(toast);
        toast.classList.add('accounting-toast-visible');
        const protectToast = () => {
            if (!document.body.contains(toast)) return;
            const computed = window.getComputedStyle(toast);
            const needsFix = computed.display === 'none' || computed.visibility === 'hidden' || computed.opacity === '0' || computed.zIndex < 99999999;
            if (needsFix) {
                toast.classList.add('accounting-toast-protected');
                toast.classList.add('accounting-toast-visible');
            }
        };
        const styleObserver = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && (mutation.attributeName === 'style' || mutation.attributeName === 'class')) {
                    protectToast();
                }
            });
        });
        styleObserver.observe(toast, { attributes: true, attributeFilter: ['style', 'class'] });
        const protectionInterval = setInterval(() => {
            if (!document.body.contains(toast)) {
                clearInterval(protectionInterval);
                styleObserver.disconnect();
                return;
            }
            protectToast();
        }, 50);
        setTimeout(() => {
            clearInterval(protectionInterval);
            styleObserver.disconnect();
        }, duration + 1000);
        const originalApplyPermissions = window.UserPermissions?.applyPermissions;
        if (originalApplyPermissions) {
            window.UserPermissions.applyPermissions = function() {
                const result = originalApplyPermissions.apply(this, arguments);
                setTimeout(() => {
                    if (document.body.contains(toast)) protectToast();
                }, 10);
                return result;
            };
        }
        requestAnimationFrame(() => {
            toast.classList.add('accounting-toast-visible');
            toast.offsetHeight;
            protectToast();
        });
        setTimeout(() => {
            if (!toast.classList.contains('accounting-toast-visible')) toast.classList.add('accounting-toast-visible');
            toast.offsetHeight;
        }, 50);
        const closeBtn = toast.querySelector('.toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                toast.classList.add('accounting-toast-removing');
                setTimeout(() => { if (toast.parentElement) toast.remove(); }, 300);
            });
        }
        setTimeout(() => {
            if (toast.parentElement) {
                toast.classList.add('accounting-toast-removing');
                setTimeout(() => { if (toast.parentElement) toast.remove(); }, 300);
            }
        }, duration);
    };

    P.showConfirmDialog = function(title, message, confirmText = 'Confirm', cancelText = 'Cancel', type = 'warning') {
        return new Promise((resolve) => {
            const existingDialogs = document.querySelectorAll('.accounting-confirm-dialog');
            existingDialogs.forEach(dialog => dialog.remove());
            const overlay = document.createElement('div');
            overlay.className = 'accounting-confirm-overlay';
            const dialog = document.createElement('div');
            dialog.className = `accounting-confirm-dialog accounting-confirm-${type}`;
            const icons = { 'warning': 'fa-exclamation-triangle', 'danger': 'fa-exclamation-circle', 'info': 'fa-info-circle', 'success': 'fa-check-circle' };
            dialog.innerHTML = `<div class="confirm-icon"><i class="fas ${icons[type] || icons.warning}"></i></div><div class="confirm-content"><h3 class="confirm-title">${this.escapeHtml(title)}</h3><p class="confirm-message">${this.escapeHtml(message)}</p></div><div class="confirm-actions"><button class="btn-confirm-cancel" data-action="confirm-cancel">${this.escapeHtml(cancelText)}</button><button class="btn-confirm-ok" data-action="confirm-ok">${this.escapeHtml(confirmText)}</button></div>`;
            overlay.appendChild(dialog);
            document.body.appendChild(overlay);
            document.body.classList.add('body-no-scroll');
            requestAnimationFrame(() => {
                overlay.classList.add('confirm-overlay-active');
                dialog.classList.add('confirm-dialog-active');
                requestAnimationFrame(() => {
                    overlay.classList.add('confirm-overlay-visible');
                    dialog.classList.add('confirm-dialog-visible');
                    overlay.offsetHeight;
                    dialog.offsetHeight;
                });
            });
            const handleConfirm = () => {
                overlay.classList.remove('confirm-overlay-visible');
                dialog.classList.remove('confirm-dialog-visible');
                setTimeout(() => { overlay.remove(); document.body.classList.remove('body-no-scroll'); }, 300);
                resolve(true);
            };
            const handleCancel = () => {
                overlay.classList.remove('confirm-overlay-visible');
                dialog.classList.remove('confirm-dialog-visible');
                setTimeout(() => { overlay.remove(); document.body.classList.remove('body-no-scroll'); }, 300);
                resolve(false);
            };
            dialog.querySelector('[data-action="confirm-ok"]').addEventListener('click', handleConfirm);
            dialog.querySelector('[data-action="confirm-cancel"]').addEventListener('click', handleCancel);
            overlay.addEventListener('click', (e) => { if (e.target === overlay) handleCancel(); });
            const escHandler = (e) => {
                if (e.key === 'Escape') { handleCancel(); document.removeEventListener('keydown', escHandler); }
            };
            document.addEventListener('keydown', escHandler);
        });
    };

    P.showPrompt = function(title, message, defaultValue = '', placeholder = '', inputType = 'text') {
        return new Promise((resolve) => {
            const existingDialogs = document.querySelectorAll('.accounting-prompt-dialog');
            existingDialogs.forEach(dialog => dialog.remove());
            const overlay = document.createElement('div');
            overlay.className = 'accounting-confirm-overlay';
            const dialog = document.createElement('div');
            dialog.className = 'accounting-confirm-dialog accounting-prompt-dialog accounting-confirm-info';
            dialog.innerHTML = `<div class="confirm-icon"><i class="fas fa-question-circle"></i></div><div class="confirm-content"><h3 class="confirm-title">${this.escapeHtml(title)}</h3><p class="confirm-message">${this.escapeHtml(message)}</p><div class="prompt-input-container"><input type="${inputType}" id="promptInput" class="form-control prompt-input" value="${this.escapeHtml(defaultValue)}" placeholder="${this.escapeHtml(placeholder)}" autofocus required><div class="prompt-error" id="promptError"></div></div></div><div class="confirm-actions"><button class="btn-confirm-cancel" data-action="prompt-cancel">Cancel</button><button class="btn-confirm-ok" id="promptOkBtn" data-action="prompt-ok" disabled>OK</button></div>`;
            overlay.appendChild(dialog);
            document.body.appendChild(overlay);
            document.body.classList.add('body-no-scroll');
            requestAnimationFrame(() => {
                overlay.classList.add('confirm-overlay-active');
                dialog.classList.add('confirm-dialog-active');
                requestAnimationFrame(() => {
                    overlay.classList.add('confirm-overlay-visible');
                    dialog.classList.add('confirm-dialog-visible');
                    overlay.offsetHeight;
                    dialog.offsetHeight;
                    const input = dialog.querySelector('#promptInput');
                    if (input) { input.focus(); input.select(); }
                });
            });
            const handleConfirm = () => {
                const input = dialog.querySelector('#promptInput');
                const value = input ? input.value.trim() : '';
                const errorDiv = dialog.querySelector('#promptError');
                if (!value) {
                    if (errorDiv) { errorDiv.textContent = 'This field is required'; errorDiv.classList.add('error-visible'); errorDiv.classList.remove('error-hidden'); }
                    return;
                }
                overlay.classList.remove('confirm-overlay-visible');
                dialog.classList.remove('confirm-dialog-visible');
                setTimeout(() => { overlay.remove(); document.body.classList.remove('body-no-scroll'); }, 300);
                resolve(value);
            };
            const handleCancel = () => {
                overlay.classList.remove('confirm-overlay-visible');
                dialog.classList.remove('confirm-dialog-visible');
                setTimeout(() => { overlay.remove(); document.body.classList.remove('body-no-scroll'); }, 300);
                resolve(null);
            };
            const okBtn = dialog.querySelector('[data-action="prompt-ok"]');
            const cancelBtn = dialog.querySelector('[data-action="prompt-cancel"]');
            const input = dialog.querySelector('#promptInput');
            const errorDiv = dialog.querySelector('#promptError');
            okBtn.addEventListener('click', handleConfirm);
            cancelBtn.addEventListener('click', handleCancel);
            if (input) {
                const updateOkButton = () => {
                    const value = input.value.trim();
                    if (okBtn) okBtn.disabled = !value;
                    if (errorDiv) { errorDiv.classList.add('error-hidden'); errorDiv.classList.remove('error-visible'); }
                };
                input.addEventListener('input', updateOkButton);
                input.addEventListener('keyup', updateOkButton);
                updateOkButton();
                input.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && input.value.trim()) { e.preventDefault(); handleConfirm(); }
                    else if (e.key === 'Enter') { e.preventDefault(); if (errorDiv) { errorDiv.textContent = 'Please enter a value'; errorDiv.classList.add('error-visible'); errorDiv.classList.remove('error-hidden'); } }
                });
            }
            overlay.addEventListener('click', (e) => { if (e.target === overlay) handleCancel(); });
            const escHandler = (e) => {
                if (e.key === 'Escape') { handleCancel(); document.removeEventListener('keydown', escHandler); }
            };
            document.addEventListener('keydown', escHandler);
        });
    };

    P.getDefaultCurrency = async function(forceRefresh = false) {
        try {
            if (window.currencyUtils && typeof window.currencyUtils.fetchCurrencies === 'function') {
                const currencies = await window.currencyUtils.fetchCurrencies(forceRefresh);
                if (currencies && currencies.length > 0) {
                    const defaultCurrency = currencies[0].code;
                    localStorage.setItem('accounting_default_currency', defaultCurrency);
                    return defaultCurrency;
                }
            }
        } catch (error) {
            console.error('Error fetching default currency:', error);
        }
        const storedCurrency = localStorage.getItem('accounting_default_currency');
        return (storedCurrency && storedCurrency.length === 3) ? storedCurrency : 'SAR';
    };

    P.initDefaultCurrency = async function() {
        try {
            if (window.currencyUtils && typeof window.currencyUtils.clearCache === 'function') {
                window.currencyUtils.clearCache();
            }
            const defaultCurrency = await this.getDefaultCurrency(true);
            localStorage.setItem('accounting_default_currency', defaultCurrency);
            if (this.currentTab === 'dashboard' && typeof this.refreshDashboardCards === 'function') {
                this.refreshDashboardCards();
            }
        } catch (error) {
            console.error('Error initializing default currency:', error);
        }
    };

    P.getChartStyles = function() {
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
    };
})();
