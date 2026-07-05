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

    var rewardsState = { couponCode: '', couponDiscount: 0, pointsRedeem: 0 };
    var checkoutIdempotencyKey = null;

    function t(key, fallback) {
        return i18n[key] || fallback || key;
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
                var bal = data.balance != null ? data.balance : (data.points != null ? data.points : 0);
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
            if (checkoutSummary) {
                checkoutSummary.innerHTML =
                    '<div><dt>' + t('pos_subtotal', 'Subtotal') + '</dt><dd>' + money(p.subtotal) + '</dd></div>' +
                    '<div><dt>' + t('pos_discount_total', 'Discount') + '</dt><dd>' + money(p.discount_total) + '</dd></div>' +
                    (rewardsState.couponDiscount > 0
                        ? '<div><dt>' + t('pos_coupon_code', 'Coupon') + '</dt><dd>-' + money(rewardsState.couponDiscount) + '</dd></div>'
                        : '') +
                    '<div><dt>' + t('pos_tax', 'Tax') + '</dt><dd>' + money(p.tax) + '</dd></div>' +
                    '<div><dt>' + t('pos_total', 'Total') + '</dt><dd><strong>' + money(p.total) + '</strong></dd></div>';
            }
            return p;
        });
    }

    function addPaymentRow(method, amount, ref) {
        if (!paymentList) {
            return;
        }
        var row = document.createElement('div');
        row.className = 'rateb-pos-payment-row';
        row.innerHTML =
            '<div><label class="rateb-pos-label">' + t('pos_payment_method', 'Method') + '</label>' +
            '<select class="form-control rateb-pos-input" data-pos-pay-method>' +
            '<option value="cash">' + t('pos_refund_cash', 'Cash') + '</option>' +
            '<option value="card">' + t('pos_refund_card', 'Card') + '</option>' +
            '<option value="bank">' + t('pos_refund_bank', 'Bank') + '</option>' +
            '<option value="wallet">' + t('pos_refund_wallet', 'Wallet') + '</option>' +
            '<option value="gift_card">' + t('pos_refund_gift_card', 'Gift card') + '</option></select></div>' +
            '<div><label class="rateb-pos-label">' + t('pos_payment_amount', 'Amount') + '</label>' +
            '<input type="number" min="0" step="0.01" class="form-control rateb-pos-input" data-pos-pay-amount value="' + money(amount || 0) + '" /></div>' +
            '<div><label class="rateb-pos-label">' + t('pos_payment_reference', 'Ref') + '</label>' +
            '<input type="text" class="form-control rateb-pos-input" data-pos-pay-ref value="' + (ref || '') + '" placeholder="' + t('pos_gift_card_code', 'Gift card code') + '" /></div>';
        var sel = row.querySelector('[data-pos-pay-method]');
        if (sel && method) {
            sel.value = method;
        }
        paymentList.appendChild(row);
    }

    function collectPayments() {
        var out = [];
        if (!paymentList) {
            return out;
        }
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
        return out;
    }

    function openCheckout() {
        var state = getState();
        if (!state.lines.length) {
            alert(t('pos_cart_empty', 'Cart is empty'));
            return;
        }
        if (panel) {
            panel.hidden = false;
        }
        if (paymentList) {
            paymentList.innerHTML = '';
        }
        rewardsState = { couponCode: '', couponDiscount: 0, pointsRedeem: 0 };
        checkoutIdempotencyKey = newIdempotencyKey();
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
            addPaymentRow('cash', p ? p.total : 0, '');
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
            return '<tr><td>' + (line.description || '') + '</td><td>' + line.quantity + '</td><td>' + money(line.line_total) + '</td></tr>';
        }).join('');
        body.innerHTML =
            '<p><strong>' + (receipt.order_no || '') + '</strong></p>' +
            '<table class="rateb-pos-receipt-lines"><thead><tr><th>Item</th><th>Qty</th><th>Total</th></tr></thead><tbody>' +
            linesHtml + '</tbody></table>' +
            '<p>' + t('pos_total', 'Total') + ': <strong>' + money(receipt.totals && receipt.totals.total) + '</strong></p>';
        if (typeof receiptModal.showModal === 'function') {
            receiptModal.showModal();
        }
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
                if (couponMsg) {
                    couponMsg.hidden = false;
                    couponMsg.textContent = t('pos_apply_coupon', 'Apply') + ': -' + money(rewardsState.couponDiscount);
                }
                refreshPricing();
            })
            .catch(function (err) {
                rewardsState.couponCode = '';
                rewardsState.couponDiscount = 0;
                if (couponMsg) {
                    couponMsg.hidden = false;
                    couponMsg.textContent = err.message || t('pos_coupon_invalid', 'Invalid coupon');
                }
            });
    }

    function completeCheckout() {
        var state = getState();
        if (!api.checkout) {
            return;
        }
        var body = new URLSearchParams();
        body.set('_csrf', csrfToken());
        body.set('lines', JSON.stringify(state.lines));
        body.set('customer', JSON.stringify(state.customer));
        body.set('payments', JSON.stringify(collectPayments()));
        body.set('invoice_discount', JSON.stringify({
            type: invoiceDiscType ? invoiceDiscType.value : 'amount',
            value: invoiceDiscValue ? Number(invoiceDiscValue.value || 0) : 0
        }));
        if (rewardsState.couponCode) {
            body.set('coupon_code', rewardsState.couponCode);
        }
        if (pointsInput && Number(pointsInput.value) > 0) {
            body.set('points_redeem', String(Number(pointsInput.value)));
        }
        body.set('idempotency_key', checkoutIdempotencyKey || newIdempotencyKey());
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
                if (data.receipt) {
                    showReceipt(data.receipt);
                }
                if (window.RatebPosRegisterReset) {
                    window.RatebPosRegisterReset();
                }
            })
            .catch(function (err) {
                alert(err.message || t('pos_checkout_failed', 'Checkout failed'));
            })
            .finally(function () {
                if (completeBtn) {
                    completeBtn.disabled = false;
                }
            });
    }

    if (openBtn) {
        openBtn.addEventListener('click', openCheckout);
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', closeCheckout);
    }
    if (addPaymentBtn) {
        addPaymentBtn.addEventListener('click', function () {
            addPaymentRow('cash', 0, '');
        });
    }
    if (completeBtn) {
        completeBtn.addEventListener('click', completeCheckout);
    }
    if (applyCouponBtn) {
        applyCouponBtn.addEventListener('click', applyCoupon);
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
            if (receiptModal && typeof receiptModal.close === 'function') {
                receiptModal.close();
            }
        });
    });
})();
