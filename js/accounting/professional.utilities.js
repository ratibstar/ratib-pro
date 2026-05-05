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

    /** Remove all custom confirm layers + stray Bootstrap backdrops (footer loads Bootstrap globally). */
    function ratibAccountingConfirmSweep() {
        document.querySelectorAll('.accounting-confirm-overlay').forEach((el) => {
            try {
                el.style.cssText = 'display:none!important;pointer-events:none!important;opacity:0!important;visibility:hidden!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important';
                el.remove();
            } catch (e) {}
        });
        if (!document.querySelector('.modal.show')) {
            document.querySelectorAll('body > .modal-backdrop').forEach((el) => {
                try { el.remove(); } catch (e2) {}
            });
            document.body.classList.remove('modal-open');
        }
    }

    function ratibRestoreAccountingModalFocus() {
        const parentModal = document.querySelector('.accounting-modal[data-modal-visible="true"], .accounting-modal.accounting-modal-visible');
        if (!parentModal) return;
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                const target = parentModal.querySelector('.accounting-modal-close, [data-action="add-cost-center"], button.btn-primary, [data-action="close-modal"]');
                try {
                    if (target && typeof target.focus === 'function') {
                        target.focus({ preventScroll: true });
                    } else if (typeof parentModal.focus === 'function') {
                        if (!parentModal.hasAttribute('tabindex')) parentModal.setAttribute('tabindex', '-1');
                        parentModal.focus({ preventScroll: true });
                    }
                } catch (e) {}
            });
        });
    }

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
        // accounting.php sets this from tenant DB before any bundle runs — beats polluted localStorage / bad JS order.
        const serverCodeRaw = typeof window.__ACCOUNTING_SERVER_DEFAULT_CURRENCY__ === 'string'
            ? String(window.__ACCOUNTING_SERVER_DEFAULT_CURRENCY__).trim().toUpperCase()
            : '';
        if (/^[A-Z]{3}$/.test(serverCodeRaw)) {
            return serverCodeRaw;
        }
        const storedCurrency = localStorage.getItem('accounting_default_currency');
        const normalizedStored = storedCurrency ? String(storedCurrency).trim().toUpperCase() : '';
        const activeListRaw = localStorage.getItem('accounting_active_currencies');
        if (activeListRaw) {
            try {
                const activeList = JSON.parse(activeListRaw);
                if (Array.isArray(activeList)) {
                    const normalizedActive = activeList
                        .map((c) => String(c || '').trim().toUpperCase())
                        .filter((c) => /^[A-Z]{3}$/.test(c));
                    if (normalizedActive.length > 0) {
                        if (normalizedStored && normalizedActive.includes(normalizedStored)) {
                            return normalizedStored;
                        }
                        // Stored/default currency is not active anymore: follow active currency list.
                        return normalizedActive[0];
                    }
                    // Empty active list was persisted (e.g. API returned none): avoid trusting stale storage.
                }
            } catch (e) {
                // Ignore malformed cache and fallback to stored/default.
            }
        }
        if (normalizedStored && /^[A-Z]{3}$/.test(normalizedStored)) {
            return normalizedStored;
        }
        return 'SAR';
    };

    P.normalizeCurrencyCode = function(value) {
        let code = String(value || '').trim();
        if (code.includes(' - ')) {
            code = code.split(' - ')[0].trim();
        }
        code = code.toUpperCase();
        return /^[A-Z]{3}$/.test(code) ? code : '';
    };

    P.populateCurrencyFieldsInContainer = async function(container, preferredCurrency = null, forceRefresh = false) {
        const scope = container || document;
        const currencySelectors = [
            'select[name="currency"]',
            'select[name="journal_currency"]',
            '#transactionCurrency',
            '#journalCurrency',
            '#entityTransactionCurrency',
            '#entryApprovalCurrency',
            '#bgCurrency'
        ];
        const selectMap = new Map();
        currencySelectors.forEach((selector) => {
            scope.querySelectorAll(selector).forEach((el) => {
                if (el && el.tagName === 'SELECT' && el.id !== 'defaultCurrency') {
                    selectMap.set(el, true);
                }
            });
        });
        const selects = Array.from(selectMap.keys());
        if (selects.length === 0) return;

        const preferred = this.normalizeCurrencyCode(preferredCurrency) || this.getDefaultCurrencySync();

        if (window.currencyUtils && typeof window.currencyUtils.populateCurrencySelect === 'function') {
            for (const select of selects) {
                const current = this.normalizeCurrencyCode(select.value);
                const target = current || preferred;
                try {
                    await window.currencyUtils.populateCurrencySelect(select, target);
                } catch (e) {
                    // Keep graceful fallback below.
                    if (!select.value) {
                        select.value = target;
                    }
                }
                // If the select still points at a value that doesn't exist in options,
                // fall back to the preferred/default currency (prevents "phantom" BDT/USD labels).
                const normalizedValue = this.normalizeCurrencyCode(select.value);
                const hasValue = normalizedValue && Array.from(select.options || []).some((o) => this.normalizeCurrencyCode(o.value) === normalizedValue);
                if (!hasValue && preferred) {
                    select.value = preferred;
                }
            }
            return;
        }

        // Fallback when currencyUtils is unavailable: ensure at least default currency exists.
        for (const select of selects) {
            const current = this.normalizeCurrencyCode(select.value);
            const target = current || preferred;
            if (!target) continue;
            const hasOption = Array.from(select.options || []).some((o) => this.normalizeCurrencyCode(o.value) === target);
            if (!hasOption) {
                const opt = document.createElement('option');
                opt.value = target;
                opt.textContent = target;
                select.appendChild(opt);
            }
            select.value = target;
        }
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
            ratibAccountingConfirmSweep();
            document.body.classList.remove('body-no-scroll');

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
            let settled = false;
            const tearDown = () => {
                document.removeEventListener('keydown', escHandler);
            };
            const finish = (value) => {
                if (settled) return;
                settled = true;
                tearDown();
                try {
                    overlay.classList.remove('confirm-overlay-visible', 'confirm-overlay-active');
                    dialog.classList.remove('confirm-dialog-visible', 'confirm-dialog-active');
                } catch (e) {}
                try {
                    overlay.style.cssText = 'display:none!important;pointer-events:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important';
                    dialog.style.display = 'none';
                } catch (e2) {}
                ratibAccountingConfirmSweep();
                document.body.classList.remove('body-no-scroll');
                const parentModal = document.querySelector('.accounting-modal[data-modal-visible="true"], .accounting-modal.accounting-modal-visible');
                if (parentModal) document.body.classList.add('body-no-scroll');
                ratibRestoreAccountingModalFocus();
                resolve(value);
            };
            const handleConfirm = () => finish(true);
            const handleCancel = () => finish(false);
            dialog.querySelector('[data-action="confirm-ok"]').addEventListener('click', handleConfirm);
            dialog.querySelector('[data-action="confirm-cancel"]').addEventListener('click', handleCancel);
            overlay.addEventListener('click', (e) => { if (e.target === overlay) handleCancel(); });
            const escHandler = (e) => {
                if (e.key === 'Escape') handleCancel();
            };
            document.addEventListener('keydown', escHandler);
        });
    };

    P.showPrompt = function(title, message, defaultValue = '', placeholder = '', inputType = 'text') {
        return new Promise((resolve) => {
            ratibAccountingConfirmSweep();
            document.body.classList.remove('body-no-scroll');

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
            let settled = false;
            const tearDown = () => {
                document.removeEventListener('keydown', escHandler);
            };
            const finish = (value) => {
                if (settled) return;
                settled = true;
                tearDown();
                try {
                    overlay.classList.remove('confirm-overlay-visible', 'confirm-overlay-active');
                    dialog.classList.remove('confirm-dialog-visible', 'confirm-dialog-active');
                } catch (e) {}
                try {
                    overlay.style.cssText = 'display:none!important;pointer-events:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important';
                    dialog.style.display = 'none';
                } catch (e2) {}
                ratibAccountingConfirmSweep();
                document.body.classList.remove('body-no-scroll');
                const parentModal = document.querySelector('.accounting-modal[data-modal-visible="true"], .accounting-modal.accounting-modal-visible');
                if (parentModal) document.body.classList.add('body-no-scroll');
                ratibRestoreAccountingModalFocus();
                resolve(value);
            };
            const handleConfirm = () => {
                const inputEl = dialog.querySelector('#promptInput');
                const value = inputEl ? inputEl.value.trim() : '';
                const errDiv = dialog.querySelector('#promptError');
                if (!value) {
                    if (errDiv) { errDiv.textContent = 'This field is required'; errDiv.classList.add('error-visible'); errDiv.classList.remove('error-hidden'); }
                    return;
                }
                finish(value);
            };
            const handleCancel = () => finish(null);
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
                if (e.key === 'Escape') handleCancel();
            };
            document.addEventListener('keydown', escHandler);
        });
    };

    P.fetchActiveCurrencies = async function(forceRefresh = false) {
        const normalizeCode = (code) => String(code || '').trim().toUpperCase();
        const addUnique = (list, code) => {
            if (!/^[A-Z]{3}$/.test(code)) return;
            if (!list.includes(code)) list.push(code);
        };

        const activeCodes = [];

        try {
            let appApiBase = ((window.APP_CONFIG && window.APP_CONFIG.apiBase) || window.API_BASE || '').replace(/\/$/, '');
            if (!appApiBase && typeof this.apiBase === 'string') {
                const acct = this.apiBase.replace(/\/$/, '');
                const m = acct.match(/^(.*\/api)\/accounting$/i);
                if (m) {
                    appApiBase = m[1];
                }
            }
            const url = (appApiBase || '') + '/settings/currencies-api.php?_t=' + Date.now();
            const res = await fetch(url, {
                credentials: 'include',
                cache: 'no-cache',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            });
            const data = await res.json().catch(() => null);
            if (res.ok && data && data.success && Array.isArray(data.currencies)) {
                data.currencies.forEach((c) => addUnique(activeCodes, normalizeCode(c && c.code)));
            }
        } catch (e) {
            // Ignore direct API failure and fallback to currencyUtils.
        }

        if (activeCodes.length > 0) {
            return activeCodes;
        }

        try {
            if (window.currencyUtils && typeof window.currencyUtils.fetchCurrencies === 'function') {
                const currencies = await window.currencyUtils.fetchCurrencies(forceRefresh);
                if (Array.isArray(currencies)) {
                    currencies.forEach((c) => addUnique(activeCodes, normalizeCode(c && c.code)));
                }
            }
        } catch (error) {
            // Silent in production.
        }

        return activeCodes;
    };

    P.fetchAccountingDefaultCurrencySetting = async function() {
        try {
            const response = await fetch(`${this.apiBase}/settings.php?key=default_currency`, {
                credentials: 'include',
                cache: 'no-cache'
            });
            const data = await response.json().catch(() => null);
            if (response.ok && data && data.success && data.setting && data.setting.value) {
                const code = String(data.setting.value).trim().toUpperCase();
                return /^[A-Z]{3}$/.test(code) ? code : null;
            }
        } catch (error) {
            // Not fatal; we'll fallback to storage/active list.
        }
        return null;
    };

    P.getDefaultCurrency = async function(forceRefresh = false) {
        const serverHintRaw = typeof window.__ACCOUNTING_SERVER_DEFAULT_CURRENCY__ === 'string'
            ? String(window.__ACCOUNTING_SERVER_DEFAULT_CURRENCY__).trim().toUpperCase()
            : '';
        const serverHint = /^[A-Z]{3}$/.test(serverHintRaw) ? serverHintRaw : '';

        // accounting.php inline sets this before any bundle: must win over settings API / active list (fixes BDT drift).
        if (window.__ACCOUNTING_SERVER_BOOTSTRAPPED__ === true && serverHint) {
            const activeCurrencies = await this.fetchActiveCurrencies(forceRefresh);
            try {
                localStorage.setItem('accounting_active_currencies', JSON.stringify(activeCurrencies || []));
            } catch (e) {
                // Ignore storage quota/write errors.
            }
            const activeSet = new Set(activeCurrencies || []);
            const chosen = (activeSet.size > 0 && !activeSet.has(serverHint))
                ? activeCurrencies[0]
                : serverHint;
            try {
                localStorage.setItem('accounting_default_currency', chosen);
            } catch (e) {}
            return chosen;
        }

        const activeCurrencies = await this.fetchActiveCurrencies(forceRefresh);
        try {
            localStorage.setItem('accounting_active_currencies', JSON.stringify(activeCurrencies || []));
        } catch (e) {
            // Ignore storage quota/write errors.
        }
        const activeSet = new Set(activeCurrencies);
        const preferredFromSetting = await this.fetchAccountingDefaultCurrencySetting();
        // IMPORTANT: read raw storage here (do not call getDefaultCurrencySync),
        // because getDefaultCurrencySync may still reflect stale values before this resolves.
        const rawStored = localStorage.getItem('accounting_default_currency');
        const preferredFromStorage = rawStored ? String(rawStored).trim().toUpperCase() : '';
        const normalizedPreferredFromStorage = /^[A-Z]{3}$/.test(preferredFromStorage) ? preferredFromStorage : '';

        let resolvedCurrency = 'SAR';
        if (preferredFromSetting && /^[A-Z]{3}$/.test(preferredFromSetting) && activeSet.size > 0 && activeSet.has(preferredFromSetting)) {
            resolvedCurrency = preferredFromSetting;
        } else if (preferredFromSetting && /^[A-Z]{3}$/.test(preferredFromSetting) && activeSet.size === 0) {
            resolvedCurrency = preferredFromSetting;
        } else if (preferredFromSetting && /^[A-Z]{3}$/.test(preferredFromSetting) && activeSet.size > 0 && !activeSet.has(preferredFromSetting)) {
            resolvedCurrency = activeCurrencies[0];
        } else if (normalizedPreferredFromStorage && activeSet.has(normalizedPreferredFromStorage)) {
            resolvedCurrency = normalizedPreferredFromStorage;
        } else if (normalizedPreferredFromStorage && !activeSet.has(normalizedPreferredFromStorage) && activeSet.size > 0) {
            resolvedCurrency = activeCurrencies[0];
        } else if (normalizedPreferredFromStorage && !activeSet.has(normalizedPreferredFromStorage) && activeSet.size === 0) {
            resolvedCurrency = normalizedPreferredFromStorage;
        } else if (serverHint) {
            resolvedCurrency = serverHint;
        } else if (activeCurrencies.length > 0) {
            resolvedCurrency = activeCurrencies[0];
        }

        localStorage.setItem('accounting_default_currency', resolvedCurrency);
        return resolvedCurrency;
    };

    P.initDefaultCurrency = async function() {
        try {
            if (window.currencyUtils && typeof window.currencyUtils.clearCache === 'function') {
                window.currencyUtils.clearCache();
            }
            const defaultCurrency = await this.getDefaultCurrency(true);
            localStorage.setItem('accounting_default_currency', defaultCurrency);
            // Do not trigger refreshDashboardCards() here.
            // refreshDashboardCards() itself calls initDefaultCurrency(), so invoking it again
            // from this function causes a recursive refresh loop and repeated API requests.
        } catch (error) {
            // Silent in production.
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

    window.ratibAccountingConfirmSweepGlobal = ratibAccountingConfirmSweep;
    window.ratibRestoreAccountingModalFocusGlobal = ratibRestoreAccountingModalFocus;
})();
