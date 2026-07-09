(function () {
    'use strict';

    var root = document.querySelector('[data-pos-register]');
    if (!root) {
        return;
    }

    var configEl = document.getElementById('rateb-pos-register-config');
    var config = {};
    try {
        config = JSON.parse((configEl && configEl.textContent) || '{}');
    } catch (e) {
        config = {};
    }

    var api = config.api || {};
    var i18n = config.i18n || {};
    var panel = root.querySelector('[data-pos-checkout-panel]');
    var receiptModal = document.getElementById('rateb-pos-receipt-modal');
    var paymentList = root.querySelector('[data-pos-payment-list]');
    var invoiceDiscType = root.querySelector('[data-pos-invoice-discount-type]');
    var invoiceDiscValue = root.querySelector('[data-pos-invoice-discount-value]');
    var checkoutSummary = root.querySelector('[data-pos-checkout-summary]');
    var openBtn = root.querySelector('[data-pos-checkout-open]');
    var closeBtn = root.querySelector('[data-pos-checkout-close]');
    var addPaymentBtn = root.querySelector('[data-pos-add-payment]');
    var completeBtn = root.querySelector('[data-pos-checkout-complete]');
    var couponInput = root.querySelector('[data-pos-coupon-code]');
    var applyCouponBtn = root.querySelector('[data-pos-apply-coupon]');
    var couponMsg = root.querySelector('[data-pos-coupon-msg]');
    var pointsInput = root.querySelector('[data-pos-points-redeem]');
    var loyaltyBalanceEl = root.querySelector('[data-pos-loyalty-balance]');
    var activeAmountEl = root.querySelector('[data-pos-active-pay-amount]');
    var payDueEl = root.querySelector('[data-pos-pay-due]');
    var payHeadTotal = root.querySelector('[data-pos-pay-sheet-total]');
    var changeWrap = root.querySelector('[data-pos-change-wrap]');
    var changeDueEl = root.querySelector('[data-pos-change-due]');
    var keypad = root.querySelector('[data-pos-keypad]');
    var giftCardPanel = root.querySelector('[data-pos-gift-card-panel]');
    var giftCardInput = root.querySelector('[data-pos-gift-card-code]');
    var giftCardValidateBtn = root.querySelector('[data-pos-gift-card-validate]');
    var giftCardBalanceEl = root.querySelector('[data-pos-gift-card-balance]');
    var giftReceiptCb = root.querySelector('[data-pos-gift-receipt]');

    var rewardsState = { couponCode: '', couponDiscount: 0, pointsRedeem: 0 };
    var giftCardRef = '';
    var checkoutIdempotencyKey = null;
    var activeMethod = 'cash';
    var pricingTotal = 0;
    var keypadBuffer = '';

    function t(key, fallback) {
        return i18n[key] || fallback || key;
    }

    function notify(msg, isError) {
        if (window.RatebPosNotify) {
            window.RatebPosNotify(msg, isError);
        }
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="rateb-csrf"]');
        return config.csrf || (meta ? meta.getAttribute('content') : '') || '';
    }

    function getState() {
        return window.RatebPosRegisterState || { lines: [], customer: null, totals: { total: 0 } };
    }

    function newIdempotencyKey() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'pos-' + Date.now() + '-' + Math.random().toString(16).slice(2);
    }

    function fetchJson(url, options) {
        options = options || {};
        var headers = options.headers || {};
        headers['Accept'] = 'application/json';
        if (options.method === 'POST') {
            headers['X-CSRF-Token'] = csrfToken();
        }
        return fetch(url, {
            method: options.method || 'GET',
            credentials: 'same-origin',
            headers: headers,
            body: options.body || null
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) {
                    throw new Error((data && data.error) ? data.error : t('invalid_request', 'Request failed'));
                }
                return data;
            });
        });
    }

    function money(n) {
        var v = Number(n);
        if (!isFinite(v)) {
            v = 0;
        }
        return v.toFixed(2);
    }

    function setActiveAmount(val) {
        keypadBuffer = money(val);
        if (activeAmountEl) {
            activeAmountEl.value = keypadBuffer;
        }
        updateChangeDue();
    }

    function updateChangeDue() {
        if (!changeWrap || !changeDueEl) {
            return;
        }
        var paid = splitPaidTotal() + Number(keypadBuffer || 0);
        var change = paid - pricingTotal;
        if (activeMethod === 'cash' && change > 0.009) {
            changeWrap.hidden = false;
            changeDueEl.textContent = money(change);
        } else {
            changeWrap.hidden = true;
            changeDueEl.textContent = '0.00';
        }
    }

    function splitPaidTotal() {
        var sum = 0;
        if (!paymentList) {
            return sum;
        }
        paymentList.querySelectorAll('[data-pos-pay-amount]').forEach(function (inp) {
            sum += Number(inp.value || 0);
        });
        return sum;
    }

    function refreshLoyaltyBalance() {
        if (!api.loyaltyBalance || !loyaltyBalanceEl) {
            return Promise.resolve();
        }
        var customer = getState().customer;
        if (!customer || !customer.id) {
            loyaltyBalanceEl.textContent = t('pos_loyalty_balance', 'Loyalty balance') + ': —';
            return Promise.resolve();
        }
        return fetchJson(api.loyaltyBalance + '?customer_id=' + encodeURIComponent(customer.id))
            .then(function (data) {
                var bal = data.balance != null ? data.balance : (data.points != null ? data.points : (data.points_balance != null ? data.points_balance : 0));
                loyaltyBalanceEl.textContent = t('pos_loyalty_balance', 'Loyalty balance') + ': ' + money(bal);
            })
            .catch(function () {
                loyaltyBalanceEl.textContent = t('pos_loyalty_balance', 'Loyalty balance') + ': —';
            });
    }

    function refreshPricing() {
        var state = getState();
        if (!api.pricing || !state.lines.length) {
            if (checkoutSummary) {
                checkoutSummary.innerHTML = '';
            }
            return Promise.resolve(null);
        }
        var body = new URLSearchParams();
        body.set('_csrf', csrfToken());
        body.set('lines', JSON.stringify(state.lines));
        body.set('invoice_discount', JSON.stringify({
            type: invoiceDiscType ? invoiceDiscType.value : 'amount',
            value: invoiceDiscValue ? Number(invoiceDiscValue.value || 0) : 0
        }));
        return fetchJson(api.pricing, { method: 'POST', body: body }).then(function (data) {
            var p = data.pricing || data.totals || {};
            pricingTotal = Number(p.total || 0);
            if (payDueEl) {
                payDueEl.textContent = money(pricingTotal);
            }
            if (payHeadTotal) {
                payHeadTotal.textContent = money(pricingTotal);
            }
            if (checkoutSummary) {
                var html = '<div class="rateb-pos__pay-summary-row"><dt>' + t('pos_subtotal', 'Subtotal') + '</dt><dd>' + money(p.subtotal) + '</dd></div>';
                if (Number(p.discount_total || 0) > 0) {
                    html += '<div class="rateb-pos__pay-summary-row"><dt>' + t('pos_discount_total', 'Discount') + '</dt><dd>-' + money(p.discount_total) + '</dd></div>';
                }
                html += '<div class="rateb-pos__pay-summary-row"><dt>' + t('pos_tax', 'Tax') + '</dt><dd>' + money(p.tax) + '</dd></div>';
                checkoutSummary.innerHTML = html;
            }
            updateChangeDue();
            return p;
        });
    }

    function addPaymentRow(method, amount, ref) {
        if (!paymentList) {
            return;
        }
        var row = document.createElement('div');
        row.className = 'rateb-pos__pay-row rateb-pos-payment-row';
        row.innerHTML =
            '<span class="rateb-pos__pay-row-method">' + escapeHtml(methodLabel(method)) + '</span>' +
            '<input type="text" inputmode="decimal" class="rateb-pos__pay-row-amt" data-pos-pay-amount value="' + money(amount || 0) + '" readonly />' +
            '<select class="visually-hidden" data-pos-pay-method tabindex="-1" aria-hidden="true">' +
            tenderOptions(method) + '</select>' +
            '<input type="hidden" data-pos-pay-ref value="' + escapeAttr(ref || '') + '" />' +
            '<button type="button" class="rateb-pos__pay-row-remove" data-pos-pay-row-remove aria-label="' + escapeAttr(t('pos_remove_line', 'Remove')) + '">×</button>';
        var sel = row.querySelector('[data-pos-pay-method]');
        if (sel && method) {
            sel.value = method;
        }
        row.querySelector('[data-pos-pay-row-remove]').addEventListener('click', function () {
            row.remove();
            updateChangeDue();
        });
        paymentList.appendChild(row);
        updateChangeDue();
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function escapeAttr(s) {
        return String(s).replace(/"/g, '&quot;');
    }

    function methodLabel(m) {
        var map = {
            cash: t('pos_refund_cash', 'Cash'),
            card: t('pos_refund_card', 'Card'),
            bank: t('pos_refund_bank', 'Bank'),
            wallet: t('pos_refund_wallet', 'Wallet'),
            gift_card: t('pos_refund_gift_card', 'Gift card')
        };
        return map[m] || m;
    }

    function tenderOptions(selected) {
        var methods = ['cash', 'card', 'bank', 'wallet', 'gift_card'];
        return methods.map(function (m) {
            return '<option value="' + m + '"' + (m === selected ? ' selected' : '') + '>' + escapeHtml(methodLabel(m)) + '</option>';
        }).join('');
    }

    function collectPayments() {
        var out = [];
        if (paymentList) {
            paymentList.querySelectorAll('.rateb-pos-payment-row').forEach(function (row) {
                var method = row.querySelector('[data-pos-pay-method]');
                var amount = row.querySelector('[data-pos-pay-amount]');
                var ref = row.querySelector('[data-pos-pay-ref]');
                var amt = Number(amount && amount.value ? amount.value : 0);
                if (amt > 0) {
                    out.push({
                        method: method ? method.value : 'cash',
                        amount: amt,
                        reference_no: ref ? ref.value : ''
                    });
                }
            });
        }
        var activeAmt = Number(keypadBuffer || 0);
        if (activeAmt > 0) {
            var ref = activeMethod === 'gift_card' ? giftCardRef : '';
            out.push({ method: activeMethod, amount: activeAmt, reference_no: ref });
        }
        return out;
    }

    function syncGiftCardPanel() {
        if (!giftCardPanel) {
            return;
        }
        giftCardPanel.hidden = activeMethod !== 'gift_card';
    }

    function validateGiftCard() {
        if (!api.validateGiftCard || !giftCardInput) {
            return;
        }
        var code = (giftCardInput.value || '').trim();
        if (!code) {
            return;
        }
        var body = new URLSearchParams();
        body.set('_csrf', csrfToken());
        body.set('gift_card_code', code);
        body.set('amount', String(pricingTotal || 0));
        fetchJson(api.validateGiftCard, { method: 'POST', body: body })
            .then(function (data) {
                giftCardRef = code;
                if (giftCardBalanceEl) {
                    giftCardBalanceEl.hidden = false;
                    giftCardBalanceEl.textContent = t('pos_gift_card_balance', 'Gift card balance') + ': ' + money(data.balance || 0);
                }
                notify(t('pos_gift_card_balance', 'Gift card balance') + ': ' + money(data.balance || 0));
            })
            .catch(function (err) {
                giftCardRef = '';
                if (giftCardBalanceEl) {
                    giftCardBalanceEl.hidden = true;
                }
                notify(err.message || t('pos_gift_card_invalid', 'Invalid gift card'), true);
            });
    }

    function openCheckout() {
        var state = getState();
        if (!state.lines.length) {
            notify(t('pos_cart_empty', 'Cart is empty'), true);
            return;
        }
        if (panel) {
            panel.hidden = false;
        }
        if (paymentList) {
            paymentList.innerHTML = '';
        }
        rewardsState = { couponCode: '', couponDiscount: 0, pointsRedeem: 0 };
        giftCardRef = '';
        checkoutIdempotencyKey = newIdempotencyKey();
        activeMethod = (window.RatebPosQuickPayMethod && window.RatebPosQuickPayMethod()) || 'cash';
        keypadBuffer = '';
        if (giftReceiptCb) {
            giftReceiptCb.checked = false;
            window.RatebPosGiftReceipt = false;
        }
        if (giftCardInput) {
            giftCardInput.value = '';
        }
        if (giftCardBalanceEl) {
            giftCardBalanceEl.hidden = true;
        }
        syncGiftCardPanel();
        root.querySelectorAll('[data-pos-tender-pick]').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.getAttribute('data-pos-tender-pick') === 'cash');
        });
        if (couponInput) {
            couponInput.value = '';
        }
        if (pointsInput) {
            pointsInput.value = '0';
        }
        if (couponMsg) {
            couponMsg.hidden = true;
            couponMsg.textContent = '';
        }
        refreshLoyaltyBalance();
        refreshPricing().then(function (p) {
            setActiveAmount(p ? p.total : (state.totals && state.totals.total) || 0);
        });
    }

    function closeCheckout() {
        if (panel) {
            panel.hidden = true;
        }
    }

    function showReceipt(receipt) {
        if (!receiptModal) {
            return;
        }
        var body = receiptModal.querySelector('[data-pos-receipt-body]');
        if (!body || !receipt) {
            return;
        }
        var linesHtml = (receipt.lines || []).map(function (line) {
            return '<div class="rateb-pos__receipt-line"><span>' + escapeHtml(line.description || '') + '</span><span>×' + line.quantity + '</span><span>' + money(line.line_total) + '</span></div>';
        }).join('');
        body.innerHTML =
            '<p class="rateb-pos__receipt-no"><strong>' + escapeHtml(receipt.order_no || '') + '</strong></p>' +
            '<div class="rateb-pos__receipt-lines">' + linesHtml + '</div>' +
            '<p class="rateb-pos__receipt-total">' + t('pos_total', 'Total') + ': <strong>' + money(receipt.totals && receipt.totals.total) + '</strong></p>';
        receiptModal.hidden = false;
        try {
            localStorage.setItem('rateb_pos_last_receipt', JSON.stringify(receipt));
        } catch (e) { /* ignore */ }
    }

    window.RatebPosShowReceipt = showReceipt;

    function printReceipt() {
        var area = receiptModal ? receiptModal.querySelector('[data-pos-receipt-body]') : null;
        if (!area) {
            return;
        }
        var w = window.open('', '_blank', 'width=400,height=600');
        if (!w) {
            notify(t('pos_print_receipt', 'Print'), true);
            return;
        }
        w.document.write('<html><head><title>' + t('pos_receipt', 'Receipt') + '</title><style>body{font-family:monospace;padding:12px} .rateb-pos__receipt-line{display:flex;justify-content:space-between;gap:8px;margin:4px 0}</style></head><body>' + area.innerHTML + '</body></html>');
        w.document.close();
        w.focus();
        w.print();
    }

    function applyCoupon() {
        if (!api.validateCoupon || !couponInput) {
            return;
        }
        var code = (couponInput.value || '').trim();
        if (!code) {
            return;
        }
        var body = new URLSearchParams();
        body.set('_csrf', csrfToken());
        body.set('coupon_code', code);
        body.set('subtotal', String((getState().totals && getState().totals.subtotal) || 0));
        fetchJson(api.validateCoupon, { method: 'POST', body: body })
            .then(function (data) {
                rewardsState.couponCode = code;
                rewardsState.couponDiscount = Number(data.discount || 0);
                notify(t('pos_apply_coupon', 'Coupon applied') + ': -' + money(rewardsState.couponDiscount));
                refreshPricing();
            })
            .catch(function (err) {
                rewardsState.couponCode = '';
                rewardsState.couponDiscount = 0;
                notify(err.message || t('pos_coupon_invalid', 'Invalid coupon'), true);
            });
    }

    function completeCheckout() {
        var state = getState();
        if (!api.checkout) {
            return;
        }
        var payments = collectPayments();
        if (!payments.length) {
            notify(t('pos_payment_invalid_amount', 'Invalid payment amount'), true);
            return;
        }
        var invoiceDiscount = {
            type: invoiceDiscType ? invoiceDiscType.value : 'amount',
            value: invoiceDiscValue ? Number(invoiceDiscValue.value || 0) : 0
        };
        if (!checkoutIdempotencyKey) {
            checkoutIdempotencyKey = newIdempotencyKey();
        }

        if (!navigator.onLine && window.RatebPosOffline) {
            var cfgScope = config.registerScope || {};
            var cfgSess = config.session || {};
            var cfgCtx = config.context || {};
            if (completeBtn) {
                completeBtn.disabled = true;
            }
            window.RatebPosOffline.push({
                client_id: checkoutIdempotencyKey,
                action: 'checkout',
                payload: {
                    lines: state.lines,
                    customer: state.customer,
                    payments: payments,
                    invoice_discount: invoiceDiscount,
                    coupon_code: rewardsState.couponCode || '',
                    points_redeem: pointsInput ? Number(pointsInput.value || 0) : 0,
                    gift_receipt: !!window.RatebPosGiftReceipt,
                    tax_rate: 0.15,
                    scope: {
                        terminal_id: cfgScope.terminal_id || (cfgCtx.terminal && cfgCtx.terminal.id) || cfgSess.terminal_id || 0,
                        shift_id: cfgScope.shift_id || (cfgCtx.shift && cfgCtx.shift.id) || cfgSess.shift_id || config.shiftId || 0,
                        branch_id: cfgScope.branch_id || (cfgCtx.branch && cfgCtx.branch.id) || cfgSess.branch_id || 0,
                        warehouse_id: cfgScope.warehouse_id || cfgSess.warehouse_id || null,
                        session_id: cfgSess.db_session_id || null,
                        user_id: config.userId || 0
                    }
                },
                version: 1
            }, { apiBase: api.sync }).then(function () {
                closeCheckout();
                checkoutIdempotencyKey = null;
                notify(t('pos_offline_queued', 'Sale queued for sync'));
                if (window.RatebPosRegisterReset) {
                    window.RatebPosRegisterReset();
                }
            }).catch(function (err) {
                notify(err.message || t('pos_checkout_failed', 'Checkout failed'), true);
            }).finally(function () {
                if (completeBtn) {
                    completeBtn.disabled = false;
                }
            });
            return;
        }

        var body = new URLSearchParams();
        body.set('_csrf', csrfToken());
        body.set('lines', JSON.stringify(state.lines));
        body.set('customer', JSON.stringify(state.customer));
        body.set('payments', JSON.stringify(payments));
        body.set('invoice_discount', JSON.stringify(invoiceDiscount));
        if (rewardsState.couponCode) {
            body.set('coupon_code', rewardsState.couponCode);
        }
        if (pointsInput && Number(pointsInput.value) > 0) {
            body.set('points_redeem', String(Number(pointsInput.value)));
        }
        body.set('idempotency_key', checkoutIdempotencyKey);
        if (window.RatebPosGiftReceipt) {
            body.set('gift_receipt', '1');
        }
        if (completeBtn) {
            completeBtn.disabled = true;
        }
        fetchJson(api.checkout, { method: 'POST', body: body })
            .then(function (data) {
                closeCheckout();
                checkoutIdempotencyKey = null;
                notify(t('pos_complete_sale', 'Sale complete'));
                if (data.receipt) {
                    showReceipt(data.receipt);
                }
                if (window.RatebPosRegisterReset) {
                    window.RatebPosRegisterReset();
                }
            })
            .catch(function (err) {
                notify(err.message || t('pos_checkout_failed', 'Checkout failed'), true);
            })
            .finally(function () {
                if (completeBtn) {
                    completeBtn.disabled = false;
                }
            });
    }

    function bindTenders() {
        root.querySelectorAll('[data-pos-tender-pick]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                activeMethod = btn.getAttribute('data-pos-tender-pick') || 'cash';
                root.querySelectorAll('[data-pos-tender-pick]').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                syncGiftCardPanel();
                updateChangeDue();
            });
        });
    }

    function bindCashShortcuts() {
        root.querySelectorAll('[data-pos-cash-amt]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                activeMethod = 'cash';
                setActiveAmount(Number(btn.getAttribute('data-pos-cash-amt') || 0));
            });
        });
        var exact = root.querySelector('[data-pos-cash-exact]');
        if (exact) {
            exact.addEventListener('click', function () {
                setActiveAmount(pricingTotal);
            });
        }
    }

    function bindKeypad() {
        if (!keypad) {
            return;
        }
        keypad.addEventListener('click', function (e) {
            var keyBtn = e.target.closest('[data-pos-key]');
            if (!keyBtn) {
                return;
            }
            var key = keyBtn.getAttribute('data-pos-key');
            if (key === 'back') {
                keypadBuffer = keypadBuffer.slice(0, -1);
                if (!keypadBuffer || keypadBuffer === '.') {
                    keypadBuffer = '0';
                }
            } else if (key === '.') {
                if (keypadBuffer.indexOf('.') < 0) {
                    keypadBuffer += '.';
                }
            } else {
                if (keypadBuffer === '0' || keypadBuffer === '0.00') {
                    keypadBuffer = key;
                } else {
                    keypadBuffer += key;
                }
            }
            if (activeAmountEl) {
                activeAmountEl.value = keypadBuffer;
            }
            updateChangeDue();
        });
    }

    function commitSplitPayment() {
        var amt = Number(keypadBuffer || 0);
        if (amt <= 0) {
            return;
        }
        addPaymentRow(activeMethod, amt, '');
        keypadBuffer = '0';
        if (activeAmountEl) {
            activeAmountEl.value = '0.00';
        }
        updateChangeDue();
    }

    if (openBtn) {
        openBtn.addEventListener('click', openCheckout);
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', closeCheckout);
    }
    if (addPaymentBtn) {
        addPaymentBtn.addEventListener('click', commitSplitPayment);
    }
    if (completeBtn) {
        completeBtn.addEventListener('click', completeCheckout);
    }
    if (applyCouponBtn) {
        applyCouponBtn.addEventListener('click', applyCoupon);
    }
    if (giftCardValidateBtn) {
        giftCardValidateBtn.addEventListener('click', validateGiftCard);
    }
    if (giftReceiptCb) {
        giftReceiptCb.addEventListener('change', function () {
            window.RatebPosGiftReceipt = giftReceiptCb.checked;
        });
    }
    var printBtn = root.querySelector('[data-pos-receipt-print]');
    if (printBtn) {
        printBtn.addEventListener('click', printReceipt);
    }
    if (invoiceDiscType) {
        invoiceDiscType.addEventListener('change', refreshPricing);
    }
    if (invoiceDiscValue) {
        invoiceDiscValue.addEventListener('input', function () {
            clearTimeout(invoiceDiscValue._timer);
            invoiceDiscValue._timer = setTimeout(refreshPricing, 300);
        });
    }

    document.querySelectorAll('[data-pos-receipt-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (receiptModal) {
                receiptModal.hidden = true;
            }
        });
    });

    bindTenders();
    bindCashShortcuts();
    bindKeypad();

    var quickPayMethod = 'cash';
    var payMethodsEl = root.querySelector('[data-pos-pay-methods]');
    if (payMethodsEl) {
        payMethodsEl.querySelectorAll('[data-pos-pay-quick]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                quickPayMethod = btn.getAttribute('data-pos-pay-quick') || 'cash';
                payMethodsEl.querySelectorAll('[data-pos-pay-quick]').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                activeMethod = quickPayMethod === 'other' ? 'wallet' : quickPayMethod;
            });
        });
    }

    if (openBtn) {
        var origOpenHandler = openBtn.onclick;
        openBtn.addEventListener('click', function (e) {
            if (quickPayMethod && !panel || (panel && panel.hidden)) {
                activeMethod = quickPayMethod === 'other' ? 'wallet' : quickPayMethod;
            }
        }, true);
    }

    window.RatebPosQuickPayMethod = function () {
        return quickPayMethod === 'other' ? 'wallet' : quickPayMethod;
    };
})();
