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

    function deferIdle(fn, timeoutMs) {
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(fn, { timeout: timeoutMs || 2500 });
            return;
        }
        setTimeout(fn, timeoutMs || 500);
    }

    function isOffline() {
        if (window.RatebPosNet && window.RatebPosNet.isOnline) {
            return !window.RatebPosNet.isOnline();
        }
        return window.RatebPosConnectivity ? !window.RatebPosConnectivity.isOnline() : !navigator.onLine;
    }

    function fetchJson(url, opts) {
        opts = opts || {};
        if (window.RatebPosNet && window.RatebPosNet.fetchJson) {
            opts.csrf = csrf();
            return window.RatebPosNet.fetchJson(url, opts, t);
        }
        if (isOffline() && !opts.allowOffline) {
            return Promise.reject(new Error(t('pos_offline', 'Offline')));
        }
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
        }).catch(function (err) {
            var msg = String((err && err.message) || err || '');
            if (/Failed to fetch|NetworkError|ERR_INTERNET|ERR_FAILED|offline/i.test(msg) || !navigator.onLine) {
                if (window.RatebPosNet && window.RatebPosNet.markOffline) {
                    window.RatebPosNet.markOffline();
                }
            }
            throw err;
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
        if (isOffline()) {
            notify(t('pos_offline', 'Offline'), true);
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

    function offlineScope() {
        if (window.RatebPosOffline && window.RatebPosOffline.buildScope) {
            return window.RatebPosOffline.buildScope({ apiBase: api.sync });
        }
        var cfgScope = config.registerScope || {};
        var cfgSess = config.session || {};
        var cfgCtx = config.context || {};
        return {
            terminal_id: cfgScope.terminal_id || (cfgCtx.terminal && cfgCtx.terminal.id) || cfgSess.terminal_id || 0,
            shift_id: cfgScope.shift_id || (cfgCtx.shift && cfgCtx.shift.id) || cfgSess.shift_id || config.shiftId || 0,
            branch_id: cfgScope.branch_id || (cfgCtx.branch && cfgCtx.branch.id) || cfgSess.branch_id || 0,
            warehouse_id: cfgScope.warehouse_id || cfgSess.warehouse_id || null,
            session_id: cfgSess.db_session_id || null,
            user_id: config.userId || 0
        };
    }

    function queueOffline(action, payload) {
        if (!window.RatebPosOffline || !window.RatebPosOffline.push) {
            return Promise.reject(new Error(t('pos_offline', 'Offline')));
        }
        var clientId = window.RatebPosOffline.newClientId
            ? window.RatebPosOffline.newClientId(action)
            : ('local-' + Date.now());
        return window.RatebPosOffline.push({
            client_id: clientId,
            action: action,
            payload: payload,
            version: 1
        }, { apiBase: api.sync }).then(function (result) {
            return Object.assign({ client_id: clientId }, result || {});
        });
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
        if (isOffline()) {
            queueOffline('process_return', {
                original_order_id: selectedOrder.id,
                return_lines: returnLines,
                refunds: [{ method: method, amount: 0 }],
                customer: state().customer,
                scope: offlineScope()
            }).then(function () {
                notify(t('pos_offline_queued', 'Queued for sync'));
                selectedOrder = null;
                renderReturnLines([]);
                var display = panelEl('[data-pos-selected-order]');
                if (display) {
                    display.textContent = '';
                }
            }).catch(function (err) {
                notify(err.message, true);
            });
            return;
        }
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
        var couponEl = panelEl('[data-pos-exchange-coupon]');
        var pointsEl = panelEl('[data-pos-exchange-points]');
        if (isOffline()) {
            queueOffline('process_exchange', {
                original_order_id: selectedOrder.id,
                return_lines: returnLines,
                sale_lines: st.lines,
                payments: payments,
                refunds: refunds,
                customer: st.customer,
                coupon_code: couponEl && couponEl.value.trim() ? couponEl.value.trim() : '',
                points_redeem: pointsEl && Number(pointsEl.value) > 0 ? Number(pointsEl.value) : 0,
                scope: offlineScope()
            }).then(function () {
                notify(t('pos_offline_queued', 'Queued for sync'));
                if (window.RatebPosRegisterReset) {
                    window.RatebPosRegisterReset();
                }
                selectedOrder = null;
                renderReturnLines([]);
                var panel = panelEl('[data-pos-return-panel]');
                if (panel) {
                    panel.hidden = true;
                }
            }).catch(function (err) {
                notify(err.message, true);
            });
            return;
        }
        var body = new URLSearchParams();
        body.set('_csrf', csrf());
        body.set('original_order_id', String(selectedOrder.id));
        body.set('return_lines', JSON.stringify(returnLines));
        body.set('sale_lines', JSON.stringify(st.lines));
        body.set('payments', JSON.stringify(payments));
        body.set('refunds', JSON.stringify(refunds));
        body.set('customer', JSON.stringify(st.customer));
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
        if (isOffline()) {
            var scope = offlineScope();
            var clientId = window.RatebPosOffline && window.RatebPosOffline.newClientId
                ? window.RatebPosOffline.newClientId('suspend')
                : ('suspend-' + Date.now());
            var linesCopy;
            try {
                linesCopy = JSON.parse(JSON.stringify(st.lines || []));
            } catch (e) {
                linesCopy = (st.lines || []).slice();
            }
            var customerCopy = null;
            try {
                customerCopy = st.customer ? JSON.parse(JSON.stringify(st.customer)) : null;
            } catch (e2) {
                customerCopy = st.customer || null;
            }
            var totalsCopy = st.totals
                ? {
                    subtotal: Number(st.totals.subtotal || 0),
                    discount_total: Number(st.totals.discount_total || 0),
                    tax: Number(st.totals.tax || 0),
                    total: Number(st.totals.total || 0)
                }
                : null;
            var localEntry = {
                client_id: clientId,
                id: clientId,
                order_no: 'OFF-' + String(clientId).slice(-6).toUpperCase(),
                total: (totalsCopy && totalsCopy.total) || 0,
                lines: linesCopy,
                customer: customerCopy,
                totals: totalsCopy,
                created_at: new Date().toISOString(),
                local: true
            };
            var putLocal = window.RatebPosOffline && window.RatebPosOffline.suspendedPut
                ? window.RatebPosOffline.suspendedPut(localEntry)
                : Promise.resolve(true);
            putLocal.then(function () {
                return window.RatebPosOffline.push({
                    client_id: clientId,
                    action: 'suspend',
                    payload: {
                        lines: linesCopy,
                        customer: customerCopy,
                        notes: '',
                        scope: scope,
                        local_client_id: clientId
                    },
                    version: 1
                }, { apiBase: api.sync });
            }).then(function () {
                if (window.RatebPosRegisterReset) {
                    window.RatebPosRegisterReset();
                }
                loadSuspended();
                switchSavedTab('suspended');
                notify(t('pos_offline_queued', 'Queued for sync'));
            }).catch(function (err) {
                notify(err.message, true);
            });
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
            switchSavedTab('suspended');
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
            notify(t('pos_cart_empty', 'Cart empty'), true);
            return;
        }
        var body = new URLSearchParams();
        body.set('_csrf', csrf());
        body.set('lines', JSON.stringify(st.lines));
        body.set('customer', JSON.stringify(st.customer));
        fetchJson(api.quoteSave, { method: 'POST', body: body }).then(function (data) {
            loadQuotes();
            switchSavedTab('quotes');
            notify(t('pos_quote_saved', 'Quote saved') + ': ' + (data.order_no || ''));
        }).catch(function (err) {
            notify(err.message, true);
        });
    }

    function renderSavedItem(item, kind) {
        var row = document.createElement('div');
        row.className = 'rateb-pos__saved-item';

        var meta = document.createElement('div');
        meta.className = 'rateb-pos__saved-meta';

        var title = document.createElement('strong');
        title.className = 'rateb-pos__saved-title';
        title.textContent = item.order_no || ('#' + item.id);

        var total = document.createElement('span');
        total.className = 'rateb-pos__saved-total';
        total.textContent = money(item.total || 0);

        meta.appendChild(title);
        meta.appendChild(total);

        if (kind === 'quote' && item.quote_expires_at) {
            var exp = document.createElement('span');
            exp.className = 'rateb-pos__saved-exp';
            exp.textContent = String(item.quote_expires_at).slice(0, 10);
            meta.appendChild(exp);
        }

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rateb-pos__saved-resume';
        btn.textContent = kind === 'quote'
            ? t('pos_load_quote', 'Load')
            : t('pos_resume_sale', 'Resume');
        btn.addEventListener('click', function () {
            if (kind === 'quote') {
                resumeQuote(item.id);
            } else {
                resumeSuspended(item.id);
            }
        });

        row.appendChild(meta);
        row.appendChild(btn);
        return row;
    }

    function switchSavedTab(tab) {
        var tabs = root.querySelectorAll('[data-pos-saved-tab]');
        var panels = root.querySelectorAll('[data-pos-saved-panel]');
        tabs.forEach(function (el) {
            var active = el.getAttribute('data-pos-saved-tab') === tab;
            el.classList.toggle('is-active', active);
            el.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(function (el) {
            var active = el.getAttribute('data-pos-saved-panel') === tab;
            el.classList.toggle('is-active', active);
            el.hidden = !active;
        });
    }

    function loadSuspended() {
        var list = root.querySelector('[data-pos-suspended-list]');
        var empty = root.querySelector('[data-pos-suspended-empty]');
        if (!list) {
            return;
        }
        function renderItems(items) {
            list.innerHTML = '';
            if (empty) {
                empty.hidden = items.length > 0;
            }
            items.forEach(function (item) {
                list.appendChild(renderSavedItem(item, 'suspended'));
            });
        }
        if (isOffline() && window.RatebPosOffline && window.RatebPosOffline.suspendedList) {
            window.RatebPosOffline.suspendedList().then(renderItems).catch(function () {
                if (empty) {
                    empty.hidden = false;
                }
            });
            return;
        }
        if (!api.suspendedList) {
            return;
        }
        fetchJson(api.suspendedList).then(function (data) {
            var items = data.items || [];
            if (window.RatebPosOffline && window.RatebPosOffline.suspendedList) {
                return window.RatebPosOffline.suspendedList().then(function (localItems) {
                    var merged = items.slice();
                    (localItems || []).forEach(function (local) {
                        if (!merged.some(function (x) { return String(x.id) === String(local.id); })) {
                            merged.push(local);
                        }
                    });
                    renderItems(merged);
                });
            }
            renderItems(items);
        }).catch(function () {
            if (window.RatebPosOffline && window.RatebPosOffline.suspendedList) {
                window.RatebPosOffline.suspendedList().then(renderItems);
                return;
            }
            if (empty) {
                empty.hidden = false;
            }
        });
    }

    function isLocalSuspendedId(id) {
        var s = String(id || '');
        return s.indexOf('suspend-') === 0
            || s.indexOf('local-') === 0
            || s.indexOf('OFF-') === 0;
    }

    function resumeLocalSuspended(id) {
        if (!window.RatebPosOffline || !(window.RatebPosOffline.suspendedGetForResume || window.RatebPosOffline.suspendedGet)) {
            notify(t('pos_offline', 'Offline'), true);
            return;
        }
        var key = String(id);
        var loader = window.RatebPosOffline.suspendedGetForResume || window.RatebPosOffline.suspendedGet;
        loader.call(window.RatebPosOffline, key).then(function (entry) {
            if (!entry && window.RatebPosOffline.suspendedList) {
                return window.RatebPosOffline.suspendedList().then(function (items) {
                    var found = null;
                    (items || []).forEach(function (item) {
                        if (!item) {
                            return;
                        }
                        if (String(item.client_id) === key
                            || String(item.id) === key
                            || String(item.order_no) === key) {
                            found = item;
                        }
                    });
                    if (found && window.RatebPosOffline.suspendedGetForResume) {
                        return window.RatebPosOffline.suspendedGetForResume(String(found.client_id || found.id));
                    }
                    return found;
                });
            }
            return entry;
        }).then(function (entry) {
            if (!entry) {
                notify(t('no_records', 'Not found'), true);
                return;
            }
            var lines = entry.lines || [];
            if (!lines.length) {
                notify(t('pos_cart_empty', 'Cart empty'), true);
                return;
            }
            var restore = window.RatebPosRegisterRestoreCart
                || (window.RatebPosRegister && window.RatebPosRegister.restoreCart);
            if (typeof restore === 'function') {
                restore({
                    lines: lines,
                    customer: entry.customer || null,
                    totals: entry.totals || null
                });
            } else if (window.RatebPosRegisterApplyLines) {
                window.RatebPosRegisterApplyLines(lines, entry.totals || null);
                if (window.RatebPosRegisterState) {
                    window.RatebPosRegisterState.customer = entry.customer || null;
                }
                if (window.RatebPosRenderCart) {
                    window.RatebPosRenderCart();
                }
            } else {
                notify(t('invalid_request', 'Failed'), true);
                return;
            }
            var removeKey = String(entry.client_id || entry.id || key);
            if (window.RatebPosOffline.suspendedRemove) {
                return window.RatebPosOffline.suspendedRemove(removeKey).then(function () {
                    notify(t('pos_resume_sale', 'Resume'));
                    loadSuspended();
                });
            }
            notify(t('pos_resume_sale', 'Resume'));
            loadSuspended();
        }).catch(function (err) {
            notify((err && err.message) || t('invalid_request', 'Failed'), true);
        });
    }

    function resumeSuspended(id) {
        if (isLocalSuspendedId(id)) {
            resumeLocalSuspended(id);
            return;
        }
        // Also try local store first (covers any offline draft id shape).
        if (isOffline() && window.RatebPosOffline && window.RatebPosOffline.suspendedGet) {
            window.RatebPosOffline.suspendedGet(String(id)).then(function (entry) {
                if (entry && entry.local) {
                    resumeLocalSuspended(id);
                    return;
                }
                if (!api.suspendedResume) {
                    notify(t('pos_offline', 'Offline'), true);
                    return;
                }
                queueOffline('resume_suspended', {
                    order_id: Number(id) || 0,
                    scope: offlineScope()
                }).then(function () {
                    notify(t('pos_offline_queued', 'Queued for sync'));
                }).catch(function (err) {
                    notify(err.message, true);
                });
            });
            return;
        }
        if (!api.suspendedResume) {
            return;
        }
        if (isOffline()) {
            queueOffline('resume_suspended', {
                order_id: Number(id),
                scope: offlineScope()
            }).then(function () {
                notify(t('pos_offline_queued', 'Queued for sync'));
            }).catch(function (err) {
                notify(err.message, true);
            });
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

    function loadQuotes() {
        var list = root.querySelector('[data-pos-quotes-list]');
        var empty = root.querySelector('[data-pos-quotes-empty]');
        if (!list || !api.quotesList) {
            return;
        }
        fetchJson(api.quotesList).then(function (data) {
            var items = data.items || [];
            list.innerHTML = '';
            if (empty) {
                empty.hidden = items.length > 0;
            }
            items.forEach(function (item) {
                list.appendChild(renderSavedItem(item, 'quote'));
            });
        }).catch(function () {
            if (empty) {
                empty.hidden = false;
            }
        });
    }

    function resumeQuote(id) {
        if (!api.quoteResume) {
            return;
        }
        var url = api.quoteResume.replace('{id}', String(id));
        var body = new URLSearchParams();
        body.set('_csrf', csrf());
        fetchJson(url, { method: 'POST', body: body }).then(function (data) {
            notify(t('pos_quote_loaded', 'Quote loaded') + ': ' + (data.order_no || ''));
            location.reload();
        }).catch(function (err) {
            notify(err.message, true);
        });
    }

    function bindSavedTabs() {
        root.querySelectorAll('[data-pos-saved-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = btn.getAttribute('data-pos-saved-tab') || 'suspended';
                switchSavedTab(tab);
                if (tab === 'quotes') {
                    loadQuotes();
                } else {
                    loadSuspended();
                }
            });
        });
        var refreshBtn = root.querySelector('[data-pos-saved-refresh]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                var active = root.querySelector('[data-pos-saved-tab].is-active');
                var tab = active ? active.getAttribute('data-pos-saved-tab') : 'suspended';
                if (tab === 'quotes') {
                    loadQuotes();
                } else {
                    loadSuspended();
                }
            });
        }
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

    root.querySelectorAll('[data-pos-suspend]').forEach(function (btn) {
        btn.addEventListener('click', suspendSale);
    });
    root.querySelectorAll('[data-pos-save-quote]').forEach(function (btn) {
        btn.addEventListener('click', saveQuote);
    });
    root.querySelectorAll('[data-pos-return-open]').forEach(function (btn) {
        btn.addEventListener('click', openReturnPanel);
    });
    root.querySelectorAll('[data-pos-exchange-open]').forEach(function (btn) {
        btn.addEventListener('click', openExchangePanel);
    });
    root.querySelector('[data-pos-return-submit]') && root.querySelector('[data-pos-return-submit]').addEventListener('click', processReturn);
    root.querySelector('[data-pos-exchange-submit]') && root.querySelector('[data-pos-exchange-submit]').addEventListener('click', processExchange);
    root.querySelector('[data-pos-exchange-add-payment]') && root.querySelector('[data-pos-exchange-add-payment]').addEventListener('click', function () {
        addExchangePaymentRow('cash', 0, '');
    });

    bindOrderSearch();
    bindRefundPicker();
    bindReturnBarcode();
    bindSavedTabs();
    deferIdle(function () {
        loadSuspended();
    }, 2000);

    window.RatebPosOpsUpdateNet = updateNetSummary;
})();
