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
    var canReturns = !!config.canReturns;

    var opsMode = 'return';
    var selectedOrder = null;
    var returnableLines = [];

    function t(key, fb) {
        return i18n[key] || fb || key;
    }

    function csrf() {
        return config.csrf || (document.querySelector('meta[name="rateb-csrf"]') || {}).content || '';
    }

    function state() {
        return window.RatebPosRegisterState || { lines: [], customer: null, totals: { total: 0 } };
    }

    function notify(msg, isError) {
        if (window.RatebPosNotify) {
            window.RatebPosNotify(msg, isError);
        }
    }

    function money(n) {
        var v = Number(n);
        if (!isFinite(v)) {
            v = 0;
        }
        return v.toFixed(2);
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function fetchJson(url, opts) {
        opts = opts || {};
        var headers = opts.headers || {};
        headers.Accept = 'application/json';
        if (opts.method === 'POST') {
            headers['X-CSRF-Token'] = csrf();
        }
        return fetch(url, {
            method: opts.method || 'GET',
            credentials: 'same-origin',
            headers: headers,
            body: opts.body || null
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) {
                    throw new Error((data && data.error) ? data.error : t('invalid_request', 'Failed'));
                }
                return data;
            });
        });
    }

    function panelEl(sel) {
        return root.querySelector(sel);
    }

    function setOpsMode(mode) {
        opsMode = mode === 'exchange' ? 'exchange' : 'return';
        var panel = panelEl('[data-pos-return-panel]');
        if (!panel) {
            return;
        }
        panel.hidden = false;
        var title = panelEl('[data-pos-ops-panel-title]');
        if (title) {
            title.textContent = opsMode === 'exchange' ? t('pos_exchange', 'Exchange') : t('pos_return', 'Return');
        }
        var exchangeNote = panelEl('[data-pos-exchange-cart-note]');
        var exchangeRewards = panelEl('[data-pos-exchange-rewards]');
        var exchangePayments = panelEl('[data-pos-exchange-payments]');
        var returnSubmit = panelEl('[data-pos-return-submit]');
        var exchangeSubmit = panelEl('[data-pos-exchange-submit]');
        var settlement = panelEl('[data-pos-return-settlement]');
        if (exchangeNote) {
            exchangeNote.hidden = opsMode !== 'exchange';
        }
        if (exchangeRewards) {
            exchangeRewards.hidden = opsMode !== 'exchange';
        }
        if (exchangePayments) {
            exchangePayments.hidden = opsMode !== 'exchange';
        }
        if (returnSubmit) {
            returnSubmit.hidden = opsMode === 'exchange';
        }
        if (exchangeSubmit) {
            exchangeSubmit.hidden = opsMode !== 'exchange';
        }
        if (settlement) {
            settlement.hidden = opsMode === 'exchange';
        }
        updateNetSummary();
    }

    function openReturnPanel() {
        setOpsMode('return');
    }

    function openExchangePanel() {
        setOpsMode('exchange');
        var st = state();
        if (!st.lines.length) {
            notify(t('pos_exchange_cart_hint', 'Add new items to the cart for the exchange sale leg.'), true);
        }
    }

    function collectReturnLines() {
        var body = panelEl('[data-pos-return-lines-body]');
        if (!body) {
            return [];
        }
        var out = [];
        body.querySelectorAll('[data-pos-return-line]').forEach(function (row) {
            var cb = row.querySelector('[data-pos-return-select]');
            var qtyInput = row.querySelector('[data-pos-return-qty]');
            if (!cb || !cb.checked || !qtyInput) {
                return;
            }
            var qty = Number(qtyInput.value || 0);
            var lineId = Number(row.getAttribute('data-pos-return-line') || 0);
            if (lineId > 0 && qty > 0) {
                out.push({ original_line_id: lineId, quantity: qty });
            }
        });
        return out;
    }

    function estimateReturnTotal() {
        var total = 0;
        var body = panelEl('[data-pos-return-lines-body]');
        if (!body) {
            return 0;
        }
        body.querySelectorAll('[data-pos-return-line]').forEach(function (row) {
            var cb = row.querySelector('[data-pos-return-select]');
            var qtyInput = row.querySelector('[data-pos-return-qty]');
            if (!cb || !cb.checked || !qtyInput) {
                return;
            }
            var qty = Number(qtyInput.value || 0);
            var unit = Number(row.getAttribute('data-pos-unit-price') || 0);
            total += qty * unit;
        });
        return total;
    }

    function updateNetSummary() {
        var summary = panelEl('[data-pos-ops-net-summary]');
        if (!summary || opsMode !== 'exchange') {
            if (summary) {
                summary.hidden = true;
            }
            return;
        }
        var cartTotal = Number((state().totals && state().totals.total) || 0);
        var returnEst = estimateReturnTotal();
        var net = cartTotal - returnEst;
        summary.hidden = false;
        if (net > 0.02) {
            summary.textContent = t('pos_net_due', 'Customer pays') + ': ' + money(net);
        } else if (net < -0.02) {
            summary.textContent = t('pos_net_refund', 'Refund due') + ': ' + money(Math.abs(net));
        } else {
            summary.textContent = t('pos_net_even', 'Even exchange');
        }
    }

    function renderReturnLines(lines) {
        returnableLines = lines || [];
        var wrap = panelEl('[data-pos-return-lines-wrap]');
        var body = panelEl('[data-pos-return-lines-body]');
        if (!wrap || !body) {
            return;
        }
        body.innerHTML = '';
        if (!lines.length) {
            wrap.hidden = true;
            return;
        }
        wrap.hidden = false;
        lines.forEach(function (line) {
            var card = document.createElement('article');
            card.className = 'rateb-pos__return-card';
            card.setAttribute('data-pos-return-line', String(line.id));
            card.setAttribute('data-pos-unit-price', String(line.unit_price || 0));
            card.setAttribute('role', 'listitem');
            card.innerHTML =
                '<label class="rateb-pos__return-card-main">' +
                '<input type="checkbox" class="rateb-pos__return-check" data-pos-return-select checked />' +
                '<span class="rateb-pos__return-card-name">' + escapeHtml(line.description || String(line.id)) + '</span>' +
                '<span class="rateb-pos__return-card-meta">' + escapeHtml(t('pos_returnable_qty', 'Returnable')) + ': ' + money(line.returnable_qty) + '</span>' +
                '</label>' +
                '<div class="rateb-pos__return-card-qty">' +
                '<button type="button" class="rateb-pos-qty-btn" data-pos-return-qty-down aria-label="-">−</button>' +
                '<input type="number" class="rateb-pos__return-qty-input" data-pos-return-qty min="0.001" max="' + String(line.returnable_qty) + '" step="0.001" value="' + String(line.returnable_qty) + '" inputmode="decimal" />' +
                '<button type="button" class="rateb-pos-qty-btn" data-pos-return-qty-up aria-label="+">+</button>' +
                '</div>';
            var qtyInput = card.querySelector('[data-pos-return-qty]');
            card.querySelector('[data-pos-return-qty-down]').addEventListener('click', function () {
                qtyInput.value = String(Math.max(0, Number(qtyInput.value || 0) - 1));
                updateNetSummary();
            });
            card.querySelector('[data-pos-return-qty-up]').addEventListener('click', function () {
                qtyInput.value = String(Math.min(Number(line.returnable_qty), Number(qtyInput.value || 0) + 1));
                updateNetSummary();
            });
            card.querySelector('[data-pos-return-select]').addEventListener('change', updateNetSummary);
            qtyInput.addEventListener('input', updateNetSummary);
            body.appendChild(card);
        });
        updateNetSummary();
    }

    function loadReturnableLines(orderId) {
        if (!api.returnableLines) {
            return Promise.resolve();
        }
        var url = api.returnableLines.replace('{id}', String(orderId));
        return fetchJson(url).then(function (data) {
            renderReturnLines(data.lines || []);
        });
    }

    function searchOrders(term) {
        if (!api.searchOrders) {
            return Promise.resolve([]);
        }
        var url = api.searchOrders + '?q=' + encodeURIComponent(term || '');
        return fetchJson(url).then(function (data) {
            return data.items || [];
        });
    }

    function bindOrderSearch() {
        var input = panelEl('[data-pos-order-search]');
        var list = panelEl('[data-pos-order-list]');
        var display = panelEl('[data-pos-selected-order]');
        if (!input || !list) {
            return;
        }
        var timer = null;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            var term = input.value.trim();
            if (term.length < 1) {
                list.hidden = true;
                list.innerHTML = '';
                return;
            }
            timer = setTimeout(function () {
                searchOrders(term).then(function (items) {
                    list.innerHTML = '';
                    if (!items.length) {
                        list.hidden = true;
                        return;
                    }
                    items.forEach(function (order) {
                        var li = document.createElement('li');
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'rateb-pos-combobox-option';
                        btn.textContent = (order.order_no || order.id) + ' — ' + money(order.total);
                        btn.addEventListener('click', function () {
                            selectedOrder = order;
                            input.value = order.order_no || String(order.id);
                            list.hidden = true;
                            if (display) {
                                display.textContent = (order.order_no || '') + ' · ' + money(order.total);
                            }
                            loadReturnableLines(Number(order.id)).catch(function (err) {
                                notify(err.message, true);
                            });
                        });
                        li.appendChild(btn);
                        list.appendChild(li);
                    });
                    list.hidden = false;
                }).catch(function () {
                    list.hidden = true;
                });
            }, 250);
        });
    }

    function addExchangePaymentRow(method, amount, ref) {
        var container = panelEl('[data-pos-exchange-payments]');
        if (!container) {
            return;
        }
        var row = document.createElement('div');
        row.className = 'rateb-pos__exchange-pay-row';
        row.innerHTML =
            '<div class="rateb-pos__field"><label class="rateb-pos__field-label">' + t('pos_payment_method', 'Method') + '</label>' +
            '<select class="rateb-pos__input rateb-pos__input--block" data-pos-ex-pay-method>' +
            '<option value="cash">' + t('pos_refund_cash', 'Cash') + '</option>' +
            '<option value="card">' + t('pos_refund_card', 'Card') + '</option>' +
            '<option value="bank">' + t('pos_refund_bank', 'Bank') + '</option>' +
            '<option value="wallet">' + t('pos_refund_wallet', 'Wallet') + '</option>' +
            '<option value="gift_card">' + t('pos_refund_gift_card', 'Gift card') + '</option></select></div>' +
            '<div class="rateb-pos__field"><label class="rateb-pos__field-label">' + t('pos_payment_amount', 'Amount') + '</label>' +
            '<input type="number" min="0" step="0.01" class="rateb-pos__input rateb-pos__input--block" data-pos-ex-pay-amount value="' + money(amount || 0) + '" /></div>' +
            '<div class="rateb-pos__field"><label class="rateb-pos__field-label">' + t('pos_payment_reference', 'Ref') + '</label>' +
            '<input type="text" class="rateb-pos__input rateb-pos__input--block" data-pos-ex-pay-ref value="' + (ref || '') + '" /></div>';
        var sel = row.querySelector('[data-pos-ex-pay-method]');
        if (sel && method) {
            sel.value = method;
        }
        container.appendChild(row);
    }

    function collectExchangePayments() {
        var container = panelEl('[data-pos-exchange-payments]');
        var out = [];
        if (!container) {
            return out;
        }
        container.querySelectorAll('.rateb-pos__exchange-pay-row').forEach(function (row) {
            var method = row.querySelector('[data-pos-ex-pay-method]');
            var amount = row.querySelector('[data-pos-ex-pay-amount]');
            var ref = row.querySelector('[data-pos-ex-pay-ref]');
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

    function processReturn() {
        if (!api.processReturn || !canReturns || !selectedOrder) {
            notify(t('pos_search_order', 'Search order'), true);
            return;
        }
        var returnLines = collectReturnLines();
        if (!returnLines.length) {
            notify(t('pos_return_lines', 'Return lines'), true);
            return;
        }
        var method = (panelEl('[data-pos-return-refund-method]') || {}).value || 'cash';
        var body = new URLSearchParams();
        body.set('_csrf', csrf());
        body.set('original_order_id', String(selectedOrder.id));
        body.set('return_lines', JSON.stringify(returnLines));
        body.set('refunds', JSON.stringify([{ method: method, amount: 0 }]));
        fetchJson(api.processReturn, { method: 'POST', body: body }).then(function (data) {
            notify(t('pos_return_complete', 'Return complete') + ': ' + (data.order_no || ''));
            selectedOrder = null;
            renderReturnLines([]);
            var display = panelEl('[data-pos-selected-order]');
            if (display) {
                display.textContent = '';
            }
        }).catch(function (err) {
            notify(err.message, true);
        });
    }

    function processExchange() {
        if (!api.processExchange || !canReturns || !selectedOrder) {
            return;
        }
        var st = state();
        if (!st.lines.length) {
            notify(t('pos_exchange_cart_hint', 'Add new items to the cart.'), true);
            return;
        }
        var returnLines = collectReturnLines();
        if (!returnLines.length) {
            notify(t('pos_return_lines', 'Select return lines'), true);
            return;
        }
        var cartTotal = Number((st.totals && st.totals.total) || 0);
        var returnEst = estimateReturnTotal();
        var net = cartTotal - returnEst;
        var payments = [];
        var refunds = [];
        if (net > 0.02) {
            payments = collectExchangePayments();
            if (!payments.length) {
                addExchangePaymentRow('cash', net, '');
                payments = collectExchangePayments();
            }
        } else if (net < -0.02) {
            refunds = [{ method: 'cash', amount: Math.abs(net) }];
        }
        var body = new URLSearchParams();
        body.set('_csrf', csrf());
        body.set('original_order_id', String(selectedOrder.id));
        body.set('return_lines', JSON.stringify(returnLines));
        body.set('sale_lines', JSON.stringify(st.lines));
        body.set('payments', JSON.stringify(payments));
        body.set('refunds', JSON.stringify(refunds));
        body.set('customer', JSON.stringify(st.customer));
        var couponEl = panelEl('[data-pos-exchange-coupon]');
        var pointsEl = panelEl('[data-pos-exchange-points]');
        if (couponEl && couponEl.value.trim()) {
            body.set('coupon_code', couponEl.value.trim());
        }
        if (pointsEl && Number(pointsEl.value) > 0) {
            body.set('points_redeem', String(Number(pointsEl.value)));
        }
        fetchJson(api.processExchange, { method: 'POST', body: body }).then(function (data) {
            notify(t('pos_exchange_complete', 'Exchange complete') + ': ' + (data.order_no || ''));
            if (window.RatebPosRegisterReset) {
                window.RatebPosRegisterReset();
            }
            selectedOrder = null;
            renderReturnLines([]);
            panelEl('[data-pos-return-panel]').hidden = true;
        }).catch(function (err) {
            notify(err.message, true);
        });
    }

    function suspendSale() {
        if (!api.suspend) {
            return;
        }
        var st = state();
        if (!st.lines.length) {
            notify(t('pos_cart_empty', 'Cart empty'), true);
            return;
        }
        var body = new URLSearchParams();
        body.set('_csrf', csrf());
        body.set('lines', JSON.stringify(st.lines));
        body.set('customer', JSON.stringify(st.customer));
        fetchJson(api.suspend, { method: 'POST', body: body }).then(function () {
            if (window.RatebPosRegisterReset) {
                window.RatebPosRegisterReset();
            }
            loadSuspended();
            notify(t('pos_suspend_saved', 'Sale suspended'));
        }).catch(function (err) {
            notify(err.message, true);
        });
    }

    function saveQuote() {
        if (!api.quoteSave) {
            return;
        }
        var st = state();
        if (!st.lines.length) {
            return;
        }
        var body = new URLSearchParams();
        body.set('_csrf', csrf());
        body.set('lines', JSON.stringify(st.lines));
        body.set('customer', JSON.stringify(st.customer));
        fetchJson(api.quoteSave, { method: 'POST', body: body }).then(function (data) {
            notify(t('pos_quote_saved', 'Quote saved') + ': ' + (data.order_no || ''));
        }).catch(function (err) {
            notify(err.message, true);
        });
    }

    function loadSuspended() {
        var list = root.querySelector('[data-pos-suspended-list]');
        if (!list || !api.suspendedList) {
            return;
        }
        fetchJson(api.suspendedList).then(function (data) {
            list.innerHTML = '';
            (data.items || []).forEach(function (item) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'rateb-pos__customer-chip';
                btn.textContent = (item.order_no || '') + ' — ' + Number(item.total || 0).toFixed(2);
                btn.addEventListener('click', function () {
                    resumeSuspended(item.id);
                });
                list.appendChild(btn);
            });
        });
    }

    function resumeSuspended(id) {
        if (!api.suspendedResume) {
            return;
        }
        var url = api.suspendedResume.replace('{id}', String(id));
        var body = new URLSearchParams();
        body.set('_csrf', csrf());
        fetchJson(url, { method: 'POST', body: body }).then(function () {
            location.reload();
        }).catch(function (err) {
            notify(err.message, true);
        });
    }

    function bindRefundPicker() {
        root.querySelectorAll('[data-pos-refund-pick]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var method = btn.getAttribute('data-pos-refund-pick') || 'cash';
                var sel = panelEl('[data-pos-return-refund-method]');
                if (sel) {
                    sel.value = method;
                }
                root.querySelectorAll('[data-pos-refund-pick]').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
            });
        });
    }

    function bindReturnBarcode() {
        var input = root.querySelector('[data-pos-return-barcode]');
        var orderSearch = panelEl('[data-pos-order-search]');
        if (!input || !orderSearch) {
            return;
        }
        input.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') {
                return;
            }
            e.preventDefault();
            var code = input.value.trim();
            if (!code) {
                return;
            }
            orderSearch.value = code;
            orderSearch.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    root.querySelector('[data-pos-suspend]') && root.querySelector('[data-pos-suspend]').addEventListener('click', suspendSale);
    root.querySelector('[data-pos-save-quote]') && root.querySelector('[data-pos-save-quote]').addEventListener('click', saveQuote);
    root.querySelector('[data-pos-return-open]') && root.querySelector('[data-pos-return-open]').addEventListener('click', openReturnPanel);
    root.querySelector('[data-pos-exchange-open]') && root.querySelector('[data-pos-exchange-open]').addEventListener('click', openExchangePanel);
    root.querySelector('[data-pos-return-submit]') && root.querySelector('[data-pos-return-submit]').addEventListener('click', processReturn);
    root.querySelector('[data-pos-exchange-submit]') && root.querySelector('[data-pos-exchange-submit]').addEventListener('click', processExchange);
    root.querySelector('[data-pos-exchange-add-payment]') && root.querySelector('[data-pos-exchange-add-payment]').addEventListener('click', function () {
        addExchangePaymentRow('cash', 0, '');
    });

    var giftCb = root.querySelector('[data-pos-gift-receipt]');
    if (giftCb) {
        giftCb.addEventListener('change', function () {
            window.RatebPosGiftReceipt = giftCb.checked;
        });
    }

    bindOrderSearch();
    bindRefundPicker();
    bindReturnBarcode();
    loadSuspended();

    window.RatebPosOpsUpdateNet = updateNetSummary;
})();
