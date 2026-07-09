(function () {
    'use strict';

    var configEl = document.getElementById('rateb-pos-register-config');
    var root = document.querySelector('[data-pos-register]');
    if (!configEl || !root) {
        return;
    }

    var config = {};
    try {
        config = JSON.parse(configEl.textContent || '{}');
    } catch (e) {
        config = {};
    }

    var i18n = config.i18n || {};
    var api = config.api || {};
    var state = {
        lines: Array.isArray(config.initialLines) ? config.initialLines.slice() : [],
        customer: (config.session && config.session.customer) ? config.session.customer : null,
        selectedLineId: null,
        totals: config.initialTotals || { subtotal: 0, tax: 0, total: 0, discount_total: 0 }
    };

    var els = {
        cartLines: root.querySelector('[data-pos-cart-lines]'),
        cartEmpty: root.querySelector('[data-pos-cart-empty]'),
        cartCount: root.querySelector('[data-pos-cart-count]'),
        subtotal: root.querySelector('[data-pos-subtotal]'),
        discountTotal: root.querySelector('[data-pos-discount-total]'),
        tax: root.querySelector('[data-pos-tax]'),
        total: root.querySelector('[data-pos-total]'),
        toolbarTotal: document.querySelector('[data-pos-toolbar-total]'),
        payAmount: root.querySelector('[data-pos-pay-amount]'),
        toolbarCustomer: document.querySelector('[data-pos-toolbar-customer]'),
        status: root.querySelector('[data-pos-status]'),
        customerInput: root.querySelector('[data-pos-customer-input]'),
        customerList: root.querySelector('[data-pos-customer-list]'),
        customerDisplay: root.querySelector('[data-pos-customer-display]'),
        customerClear: root.querySelector('[data-pos-customer-clear]'),
        productSearch: root.querySelector('[data-pos-product-search]'),
        productList: root.querySelector('[data-pos-product-list]'),
        barcodeInput: root.querySelector('[data-pos-barcode-input]'),
        checkoutOpen: root.querySelector('[data-pos-checkout-open]'),
        shortcutsList: root.querySelector('[data-pos-shortcuts-list]'),
        clearCart: root.querySelector('[data-pos-clear-cart]'),
        newSale: root.querySelector('[data-pos-new-sale]')
    };

    var saveTimer = null;
    var searchTimer = null;
    var statusTimer = null;
    var listActiveIndex = -1;
    var activeListType = null;
    var storageKey = 'rateb_pos_cart_' + (config.companyId || 0) + '_' + (config.userId || 0);
    var lastRenderedTotal = null;

    function t(key, fallback) {
        return i18n[key] || fallback || key;
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="rateb-csrf"]');
        return (config.csrf || (meta ? meta.getAttribute('content') : '') || '');
    }

    function money(n) {
        var v = Number(n);
        if (!isFinite(v)) {
            v = 0;
        }
        return v.toFixed(2);
    }

    function showStatus(msg, isError) {
        if (!els.status || !msg) {
            return;
        }
        els.status.textContent = msg;
        els.status.classList.add('is-visible');
        if (isError) {
            els.status.classList.add('is-error');
        } else {
            els.status.classList.remove('is-error');
        }
        clearTimeout(statusTimer);
        statusTimer = setTimeout(function () {
            els.status.classList.remove('is-visible');
            els.status.classList.remove('is-error');
        }, 2200);
    }

    window.RatebPosNotify = showStatus;

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

    function localSave() {
        try {
            localStorage.setItem(storageKey, JSON.stringify({
                lines: state.lines,
                customer: state.customer,
                updated_at: new Date().toISOString()
            }));
        } catch (e) { /* ignore quota */ }
    }

    function scheduleSave() {
        localSave();
        clearTimeout(saveTimer);
        saveTimer = setTimeout(persistSession, 450);
    }

    function persistSession() {
        if (!api.sessionSave) {
            return;
        }
        var body = new URLSearchParams();
        body.set('_csrf', csrfToken());
        body.set('lines', JSON.stringify(state.lines));
        body.set('customer', JSON.stringify(state.customer));
        fetchJson(api.sessionSave, { method: 'POST', body: body })
            .then(function (data) {
                if (data.lines) {
                    state.lines = data.lines;
                    renderCartWithoutSave();
                }
                if (data.totals) {
                    state.totals = data.totals;
                    renderTotals();
                }
                showStatus(t('pos_session_saved', 'Session saved'));
            })
            .catch(function (err) {
                showStatus(err.message || t('pos_insufficient_stock', 'Insufficient stock'));
            });
    }

    function refreshPricing() {
        if (!api.pricing) {
            computeTotalsLocal();
            return;
        }
        var body = new URLSearchParams();
        body.set('_csrf', csrfToken());
        body.set('lines', JSON.stringify(state.lines));
        fetchJson(api.pricing, { method: 'POST', body: body })
            .then(function (data) {
                if (data.totals) {
                    state.totals = data.totals;
                    renderTotals();
                }
            })
            .catch(computeTotalsLocal);
    }

    function computeTotalsLocal() {
        var subtotal = 0;
        state.lines.forEach(function (line) {
            subtotal += Number(line.line_total || 0);
        });
        var tax = Math.round(subtotal * 0.15 * 100) / 100;
        state.totals = { subtotal: subtotal, tax: tax, total: subtotal + tax, discount_total: 0 };
        renderTotals();
    }

    function renderTotals() {
        if (els.subtotal) {
            els.subtotal.textContent = money(state.totals.subtotal);
        }
        if (els.discountTotal) {
            els.discountTotal.textContent = money(state.totals.discount_total || 0);
        }
        if (els.tax) {
            els.tax.textContent = money(state.totals.tax);
        }
        if (els.total) {
            els.total.textContent = money(state.totals.total);
        }
        if (els.payAmount) {
            els.payAmount.textContent = money(state.totals.total);
        }
        if (els.toolbarTotal) {
            els.toolbarTotal.textContent = money(state.totals.total);
        }
        var discWrap = root.querySelector('[data-pos-totals-discount-wrap]');
        if (discWrap) {
            discWrap.hidden = !(Number(state.totals.discount_total || 0) > 0.009);
        }
        var totalNum = Number(state.totals.total || 0);
        if (lastRenderedTotal !== null && lastRenderedTotal !== totalNum &&
            window.RatebPosMotion && typeof window.RatebPosMotion.pulseTotal === 'function') {
            window.RatebPosMotion.pulseTotal();
        }
        lastRenderedTotal = totalNum;
    }

    function lineThumbHtml(line) {
        var images = window.RatebPosProductImages || {};
        var src = images[String(line.product_id || '')] || '';
        if (src) {
            return '<img src="' + escapeAttr(src) + '" alt="" class="rateb-pos__line-photo" loading="lazy" decoding="async" />';
        }
        return '<span class="rateb-pos__line-photo rateb-pos__line-photo--empty" aria-hidden="true"></span>';
    }

    function renderCartLine(line) {
        var row = document.createElement('tr');
        row.className = 'rateb-pos__line';
        row.setAttribute('data-line-id', line.id || '');
        row.tabIndex = 0;
        if (state.selectedLineId === line.id) {
            row.classList.add('is-selected');
        }

        var sku = line.item_code || line.sku || '';
        var unitPrice = line.unit_price != null ? line.unit_price : (Number(line.line_total || 0) / Math.max(1, Number(line.quantity || 1)));

        row.innerHTML =
            '<td>' + lineThumbHtml(line) + '</td>' +
            '<td><p class="rateb-pos__line-name">' + escapeHtml(line.item_name || '') + '</p></td>' +
            '<td><span class="rateb-pos__line-sku">' + escapeHtml(sku) + '</span></td>' +
            '<td><div class="rateb-pos__line-qty">' +
            '<button type="button" class="rateb-pos-qty-btn" data-pos-qty-down="' + escapeAttr(line.id) + '" aria-label="' + escapeAttr(t('pos_decrease_qty', 'Decrease quantity')) + '">−</button>' +
            '<span class="rateb-pos-qty-value" aria-live="polite">' + escapeHtml(String(line.quantity)) + '</span>' +
            '<button type="button" class="rateb-pos-qty-btn" data-pos-qty-up="' + escapeAttr(line.id) + '" aria-label="' + escapeAttr(t('pos_increase_qty', 'Increase quantity')) + '">+</button>' +
            '</div></td>' +
            '<td class="rateb-pos__line-unit">' + money(unitPrice) + '</td>' +
            '<td class="rateb-pos__line-price">' + money(line.line_total) + '</td>' +
            '<td><button type="button" class="rateb-pos__line-remove" data-pos-remove="' + escapeAttr(line.id) + '" aria-label="' + escapeAttr(t('pos_remove_line', 'Remove')) + '">' +
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button></td>';

        row.addEventListener('click', function (e) {
            if (e.target.closest('[data-pos-remove]') || e.target.closest('[data-pos-pick-serial]') ||
                e.target.closest('[data-pos-qty-up]') || e.target.closest('[data-pos-qty-down]')) {
                return;
            }
            selectLine(line.id);
        });
        row.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectLine(line.id);
            }
        });
        return row;
    }

    function bindCartLineControls(container) {
        if (!container) {
            return;
        }
        container.querySelectorAll('[data-pos-pick-serial]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var pid = Number(btn.getAttribute('data-pos-pick-serial'));
                var lid = btn.getAttribute('data-line-id');
                var line = findLine(lid);
                if (line) {
                    openSerialPicker({ id: pid, item_name: line.item_name }, Number(line.quantity || 1), lid);
                }
            });
        });
        container.querySelectorAll('[data-pos-remove]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                removeLine(btn.getAttribute('data-pos-remove'));
            });
        });
        container.querySelectorAll('[data-pos-qty-up]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                adjustLineQty(btn.getAttribute('data-pos-qty-up'), 1);
            });
        });
        container.querySelectorAll('[data-pos-qty-down]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                adjustLineQty(btn.getAttribute('data-pos-qty-down'), -1);
            });
        });
    }

    function renderCartLines() {
        if (!els.cartLines) {
            return;
        }
        els.cartLines.innerHTML = '';
        state.lines.forEach(function (line) {
            els.cartLines.appendChild(renderCartLine(line));
        });
        bindCartLineControls(els.cartLines);
    }

    function renderCustomer() {
        var label = t('pos_walk_in_customer', 'Walk-in customer');
        if (state.customer && state.customer.name) {
            label = state.customer.name;
            if (state.customer.phone) {
                label += ' · ' + state.customer.phone;
            }
        }
        if (els.customerDisplay) {
            els.customerDisplay.textContent = label;
        }
        if (els.toolbarCustomer) {
            els.toolbarCustomer.textContent = label;
        }
    }

    var pendingSerialProduct = null;
    var pendingSerialQty = 1;

    function stockBadge(product) {
        var avail = product && product.availability ? product.availability : {};
        var n = Number(avail.available != null ? avail.available : 0);
        var cls = n > 0 ? 'rateb-pos-stock-ok' : 'rateb-pos-stock-out';
        var label = n > 0 ? (t('pos_stock_available', 'Available') + ': ' + n) : t('pos_out_of_stock', 'Out of stock');
        return '<span class="rateb-pos-stock-badge ' + cls + '">' + escapeHtml(label) + '</span>';
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function escapeAttr(s) {
        return String(s).replace(/"/g, '&quot;');
    }

    function findLine(id) {
        for (var i = 0; i < state.lines.length; i++) {
            if (state.lines[i].id === id) {
                return state.lines[i];
            }
        }
        return null;
    }

    function selectLine(id) {
        state.selectedLineId = id;
        renderCart();
    }

    function clearSelection() {
        state.selectedLineId = null;
        renderCart();
    }

    function addProduct(product, qty, serialNo) {
        qty = qty || 1;
        var avail = product && product.availability ? product.availability : {};
        if (avail.can_add === false) {
            showStatus(t('pos_out_of_stock', 'Out of stock'));
            return;
        }
        if (product.requires_serial && !serialNo && !product.matched_serial) {
            openSerialPicker(product, qty);
            return;
        }
        if (!api.cartAdd) {
            return;
        }
        var body = new URLSearchParams();
        body.set('_csrf', csrfToken());
        body.set('product_id', String(product.id));
        body.set('quantity', String(qty));
        if (serialNo || product.matched_serial) {
            body.set('serial_no', serialNo || product.matched_serial);
        }
        fetchJson(api.cartAdd, { method: 'POST', body: body })
            .then(function (data) {
                state.lines = data.lines || [];
                if (data.totals) {
                    state.totals = data.totals;
                    renderTotals();
                }
                state.selectedLineId = null;
                renderCartWithoutSave();
                scheduleSave();
                showStatus(t('pos_add_to_cart', 'Added to cart'));
            })
            .catch(function (err) {
                showStatus(err.message || t('pos_insufficient_stock', 'Insufficient stock'));
            });
    }

    function renderCartWithoutSave() {
        renderCartLines();
        var count = state.lines.length;
        var prevCount = els.cartCount ? Number(els.cartCount.getAttribute('data-prev-count') || '0') : 0;
        if (els.cartCount) {
            els.cartCount.textContent = String(count);
            els.cartCount.setAttribute('data-prev-count', String(count));
            if (count > prevCount && window.RatebPosMotion && typeof window.RatebPosMotion.bumpCartCount === 'function') {
                window.RatebPosMotion.bumpCartCount();
            }
        }
        if (els.cartEmpty) {
            els.cartEmpty.classList.toggle('is-hidden', count > 0);
        }
        if (els.checkoutOpen) {
            els.checkoutOpen.disabled = count < 1;
        }
        if (state.lines.length > 0) {
            var hasLineTotals = state.lines.some(function (line) {
                return Number(line.line_total || 0) > 0;
            });
            if (hasLineTotals && (!Number(state.totals.total) || state.totals.total === 0)) {
                computeTotalsLocal();
            }
        }
        refreshPricing();
    }

    function renderCart() {
        renderCartWithoutSave();
        scheduleSave();
    }

    function randomId() {
        return 'l' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
    }

    function removeLine(id) {
        if (!api.cartUpdate) {
            state.lines = state.lines.filter(function (line) { return line.id !== id; });
            if (state.selectedLineId === id) {
                state.selectedLineId = null;
            }
            renderCart();
            return;
        }
        var body = new URLSearchParams();
        body.set('_csrf', csrfToken());
        body.set('line_id', id);
        body.set('quantity', '0');
        fetchJson(api.cartUpdate, { method: 'POST', body: body })
            .then(function (data) {
                state.lines = data.lines || [];
                if (state.selectedLineId === id) {
                    state.selectedLineId = null;
                }
                if (data.totals) {
                    state.totals = data.totals;
                    renderTotals();
                }
                renderCartWithoutSave();
                scheduleSave();
            })
            .catch(function (err) {
                showStatus(err.message);
            });
    }

    function adjustLineQty(lineId, delta) {
        if (!lineId || !api.cartUpdate) {
            return;
        }
        var line = findLine(lineId);
        if (!line) {
            return;
        }
        state.selectedLineId = lineId;
        var q = Math.max(0, Number(line.quantity || 0) + delta);
        if (q <= 0) {
            removeLine(lineId);
            return;
        }
        var body = new URLSearchParams();
        body.set('_csrf', csrfToken());
        body.set('line_id', lineId);
        body.set('quantity', String(q));
        fetchJson(api.cartUpdate, { method: 'POST', body: body })
            .then(function (data) {
                state.lines = data.lines || [];
                if (data.totals) {
                    state.totals = data.totals;
                    renderTotals();
                }
                renderCartWithoutSave();
                scheduleSave();
            })
            .catch(function (err) {
                showStatus(err.message || t('pos_insufficient_stock', 'Insufficient stock'));
            });
    }

    function adjustSelectedQty(delta) {
        if (!state.selectedLineId) {
            return;
        }
        adjustLineQty(state.selectedLineId, delta);
    }

    function clearCart(skipConfirm) {
        function doClear() {
            if (!skipConfirm && state.lines.length && !window.confirm(t('pos_confirm_clear_cart', 'Cancel current sale and clear the cart?'))) {
                return;
            }
            state.lines = [];
            state.selectedLineId = null;
            renderCart();
        }
        if (window.RatebPosRequireApproval && state.lines.length) {
            window.RatebPosRequireApproval('cancel_invoice', {}, doClear);
            return;
        }
        doClear();
    }

    function newSale() {
        if (state.lines.length && !window.confirm(t('pos_confirm_clear_cart', 'Cancel current sale and clear the cart?'))) {
            return;
        }
        state.lines = [];
        state.selectedLineId = null;
        state.customer = null;
        renderCustomer();
        renderCart();
        scheduleSave();
        if (els.productSearch) {
            els.productSearch.value = '';
        }
        if (els.barcodeInput) {
            els.barcodeInput.value = '';
        }
        showStatus(t('pos_new_sale', 'New sale'));
    }

    function closeList() {
        listActiveIndex = -1;
        activeListType = null;
        if (els.customerList) {
            els.customerList.hidden = true;
            if (els.customerInput) {
                els.customerInput.setAttribute('aria-expanded', 'false');
            }
        }
        if (els.productList) {
            els.productList.hidden = true;
            if (els.productSearch) {
                els.productSearch.setAttribute('aria-expanded', 'false');
            }
        }
    }

    function renderListItems(listEl, items, type) {
        if (!listEl) {
            return;
        }
        listEl.innerHTML = '';
        listActiveIndex = -1;
        activeListType = type;
        if (!items.length) {
            var empty = document.createElement('li');
            empty.className = 'rateb-pos-combobox-option';
            empty.textContent = t('pos_search_no_results', 'No results');
            empty.setAttribute('aria-disabled', 'true');
            listEl.appendChild(empty);
            listEl.hidden = false;
            return;
        }
        items.forEach(function (item, idx) {
            var li = document.createElement('li');
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rateb-pos-combobox-option';
            btn.setAttribute('role', 'option');
            btn.setAttribute('data-index', String(idx));
            if (type === 'customer') {
                btn.textContent = (item.name || '') + (item.phone ? ' · ' + item.phone : '');
                btn.addEventListener('click', function () {
                    state.customer = item;
                    renderCustomer();
                    closeList();
                    if (els.customerInput) {
                        els.customerInput.value = '';
                    }
                    scheduleSave();
                    if (window.RatebPosPushRecentCustomer) {
                        window.RatebPosPushRecentCustomer(item);
                    }
                    if (window.RatebPosRefreshCustomerLoyalty) {
                        window.RatebPosRefreshCustomerLoyalty();
                    }
                });
            } else {
                var avail = item.availability || {};
                var canAdd = avail.can_add !== false;
                btn.textContent = (item.item_code ? item.item_code + ' — ' : '') + (item.item_name || '');
                btn.innerHTML = escapeHtml(btn.textContent) + ' ' + stockBadge(item);
                btn.disabled = !canAdd;
                btn.addEventListener('click', function () {
                    if (!canAdd) {
                        showStatus(t('pos_out_of_stock', 'Out of stock'));
                        return;
                    }
                    addProduct(item, 1);
                    closeList();
                    if (els.productSearch) {
                        els.productSearch.value = '';
                    }
                });
            }
            li.appendChild(btn);
            listEl.appendChild(li);
        });
        listEl.hidden = false;
        if (type === 'customer' && els.customerInput) {
            els.customerInput.setAttribute('aria-expanded', 'true');
        }
        if (type === 'product' && els.productSearch) {
            els.productSearch.setAttribute('aria-expanded', 'true');
        }
    }

    function searchCustomers(q) {
        if (!api.customers || q.length < 2) {
            closeList();
            return;
        }
        fetchJson(api.customers + '?q=' + encodeURIComponent(q))
            .then(function (data) {
                renderListItems(els.customerList, data.items || [], 'customer');
            })
            .catch(function () { closeList(); });
    }

    function searchProducts(q) {
        if (!api.products || q.length < 2) {
            closeList();
            return;
        }
        if (!navigator.onLine && window.RatebPosOffline && window.RatebPosOffline.catalogSearch) {
            window.RatebPosOffline.catalogSearch(q, 40).then(function (items) {
                renderListItems(els.productList, items || [], 'product');
            }).catch(function () { closeList(); });
            return;
        }
        fetchJson(api.products + '?q=' + encodeURIComponent(q))
            .then(function (data) {
                var items = data.items || [];
                if (window.RatebPosOffline && window.RatebPosOffline.catalogPutMany) {
                    window.RatebPosOffline.catalogPutMany(items);
                }
                renderListItems(els.productList, items, 'product');
            })
            .catch(function () {
                if (window.RatebPosOffline && window.RatebPosOffline.catalogSearch) {
                    window.RatebPosOffline.catalogSearch(q, 40).then(function (items) {
                        renderListItems(els.productList, items || [], 'product');
                    }).catch(function () { closeList(); });
                    return;
                }
                closeList();
            });
    }

    function lookupBarcode(code) {
        if (!code) {
            return;
        }
        function onFound(item) {
            if (item) {
                addProduct(item, 1);
                showStatus(t('pos_add_to_cart', 'Added to cart'));
            } else {
                showStatus(t('pos_product_not_found', 'Product not found'));
            }
        }
        if (!navigator.onLine && window.RatebPosOffline && window.RatebPosOffline.catalogLookupBarcode) {
            window.RatebPosOffline.catalogLookupBarcode(code).then(onFound).finally(function () {
                if (els.barcodeInput) {
                    els.barcodeInput.value = '';
                    els.barcodeInput.focus();
                }
            });
            return;
        }
        if (!api.barcode) {
            return;
        }
        fetchJson(api.barcode + '?code=' + encodeURIComponent(code))
            .then(function (data) {
                if (data.item) {
                    if (window.RatebPosOffline && window.RatebPosOffline.catalogPutMany) {
                        window.RatebPosOffline.catalogPutMany([data.item]);
                    }
                    onFound(data.item);
                } else {
                    onFound(null);
                }
            })
            .catch(function (err) {
                if (window.RatebPosOffline && window.RatebPosOffline.catalogLookupBarcode) {
                    window.RatebPosOffline.catalogLookupBarcode(code).then(onFound).catch(function () {
                        showStatus(err.message || t('pos_product_not_found', 'Product not found'));
                    });
                    return;
                }
                showStatus(err.message || t('pos_product_not_found', 'Product not found'));
            })
            .finally(function () {
                if (els.barcodeInput) {
                    els.barcodeInput.value = '';
                    els.barcodeInput.focus();
                }
            });
    }

    function loadSession() {
        renderCustomer();
        renderCart();

        if (!api.session) {
            try {
                var raw = localStorage.getItem(storageKey);
                if (raw) {
                    var parsed = JSON.parse(raw);
                    if (Array.isArray(parsed.lines)) {
                        state.lines = parsed.lines;
                    }
                    if (parsed.customer) {
                        state.customer = parsed.customer;
                    }
                    renderCustomer();
                    renderCart();
                }
            } catch (e) { /* ignore */ }
            return;
        }

        if (!navigator.onLine) {
            return;
        }

        fetchJson(api.session)
            .then(function (data) {
                if (data.session) {
                    if (data.session.customer) {
                        state.customer = data.session.customer;
                    }
                    if (data.session.cart && Array.isArray(data.session.cart.lines)) {
                        state.lines = data.session.cart.lines;
                    }
                }
                if (data.totals) {
                    state.totals = data.totals;
                }
                renderCustomer();
                renderCart();
            })
            .catch(function () { /* keep server-rendered session */ });
    }

    function bindComboboxInput(input, type) {
        if (!input) {
            return;
        }
        input.addEventListener('input', function () {
            clearTimeout(searchTimer);
            var q = input.value.trim();
            searchTimer = setTimeout(function () {
                if (type === 'customer') {
                    searchCustomers(q);
                } else {
                    searchProducts(q);
                }
            }, 280);
        });
        input.addEventListener('keydown', function (e) {
            var list = type === 'customer' ? els.customerList : els.productList;
            if (!list || list.hidden) {
                if (e.key === 'ArrowDown' && input.value.trim().length >= 2) {
                    if (type === 'customer') {
                        searchCustomers(input.value.trim());
                    } else {
                        searchProducts(input.value.trim());
                    }
                }
                return;
            }
            var options = list.querySelectorAll('.rateb-pos-combobox-option[data-index]');
            if (!options.length) {
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                listActiveIndex = Math.min(options.length - 1, listActiveIndex + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                listActiveIndex = Math.max(0, listActiveIndex - 1);
            } else if (e.key === 'Enter' && listActiveIndex >= 0) {
                e.preventDefault();
                options[listActiveIndex].click();
                return;
            } else if (e.key === 'Escape') {
                closeList();
                return;
            } else {
                return;
            }
            options.forEach(function (opt, i) {
                opt.classList.toggle('is-active', i === listActiveIndex);
                if (i === listActiveIndex) {
                    opt.focus();
                }
            });
        });
    }

    function bindShortcutsHelp() {
        if (!els.shortcutsList) {
            return;
        }
        var items = [
            ['F2', t('pos_shortcut_search', 'Focus product search')],
            ['F3', t('pos_shortcut_barcode', 'Focus barcode')],
            ['F6', t('pos_shortcut_customer', 'Focus customer')],
            ['F9', t('pos_shortcut_clear', 'Clear cart')],
            ['+ / -', t('pos_shortcut_qty_up', 'Adjust selected qty')],
            ['Delete', t('pos_remove_line', 'Remove selected line')],
            ['Esc', t('pos_clear_selection', 'Clear selection')]
        ];
        els.shortcutsList.innerHTML = items.map(function (row) {
            return '<li><kbd>' + escapeHtml(row[0]) + '</kbd> — ' + escapeHtml(row[1]) + '</li>';
        }).join('');
    }

    var pendingLineId = null;

    function closeSerialModal() {
        var modal = root.querySelector('[data-pos-serial-modal]');
        if (modal) {
            modal.hidden = true;
        }
        pendingSerialProduct = null;
        pendingLineId = null;
    }

    function openSerialPicker(product, qty, lineId) {
        pendingSerialProduct = product;
        pendingSerialQty = qty || 1;
        pendingLineId = lineId || null;
        var modal = root.querySelector('[data-pos-serial-modal]');
        if (!modal || !api.productSerials) {
            return;
        }
        var bodyEl = modal.querySelector('[data-pos-serial-body]');
        if (bodyEl) {
            bodyEl.innerHTML = '';
        }
        fetchJson(api.productSerials + '?id=' + encodeURIComponent(product.id))
            .then(function (data) {
                var items = data.items || [];
                if (!bodyEl) {
                    return;
                }
                if (!items.length) {
                    var empty = document.createElement('p');
                    empty.className = 'rateb-pos-serial-empty';
                    empty.textContent = t('pos_search_no_results', 'No results');
                    bodyEl.appendChild(empty);
                }
                items.forEach(function (serial) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'rateb-pos-combobox-option rateb-pos-serial-option';
                    btn.setAttribute('role', 'option');
                    btn.textContent = serial.serial_no || '';
                    btn.addEventListener('click', function () {
                        var serialNo = serial.serial_no || '';
                        closeSerialModal();
                        if (pendingLineId && api.cartUpdate) {
                            var body = new URLSearchParams();
                            body.set('_csrf', csrfToken());
                            body.set('line_id', pendingLineId);
                            body.set('quantity', String(pendingSerialQty));
                            body.set('serial_no', serialNo);
                            fetchJson(api.cartUpdate, { method: 'POST', body: body })
                                .then(function (resp) {
                                    state.lines = resp.lines || [];
                                    renderCartWithoutSave();
                                    scheduleSave();
                                })
                                .catch(function (err) {
                                    showStatus(err.message);
                                });
                        } else if (pendingSerialProduct) {
                            addProduct(pendingSerialProduct, pendingSerialQty, serialNo);
                        }
                    });
                    bodyEl.appendChild(btn);
                });
                modal.hidden = false;
            })
            .catch(function (err) {
                showStatus(err.message);
            });
    }

    function bindEvents() {
        bindComboboxInput(els.customerInput, 'customer');
        bindComboboxInput(els.productSearch, 'product');

        if (els.customerClear) {
            els.customerClear.addEventListener('click', function () {
                state.customer = null;
                renderCustomer();
                scheduleSave();
                if (els.customerInput) {
                    els.customerInput.value = '';
                    els.customerInput.focus();
                }
            });
        }

        if (els.barcodeInput) {
            els.barcodeInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    lookupBarcode(els.barcodeInput.value.trim());
                }
            });
        }

        if (els.clearCart) {
            root.querySelectorAll('[data-pos-clear-cart]').forEach(function (btn) {
                btn.addEventListener('click', clearCart);
            });
        }
        if (els.newSale) {
            root.querySelectorAll('[data-pos-new-sale]').forEach(function (btn) {
                btn.addEventListener('click', newSale);
            });
        }

        document.querySelectorAll('[data-pos-serial-close]').forEach(function (btn) {
            btn.addEventListener('click', closeSerialModal);
        });

        document.addEventListener('rateb-pos-shortcut', function (e) {
            var action = e.detail && e.detail.action;
            if (!action) {
                return;
            }
            if (action === 'pos-focus-search' && els.productSearch) {
                els.productSearch.focus();
            } else if (action === 'pos-focus-barcode' && els.barcodeInput) {
                els.barcodeInput.focus();
            } else if (action === 'pos-focus-customer') {
                var customerSheetEl = root.querySelector('[data-pos-customer-sheet]');
                if (customerSheetEl) {
                    customerSheetEl.hidden = false;
                }
                if (els.customerInput) {
                    els.customerInput.focus();
                }
            } else if (action === 'pos-clear-cart') {
                clearCart();
            } else if (action === 'pos-qty-up') {
                adjustSelectedQty(1);
            } else if (action === 'pos-qty-down') {
                adjustSelectedQty(-1);
            } else if (action === 'pos-remove-line' && state.selectedLineId) {
                removeLine(state.selectedLineId);
            } else if (action === 'pos-clear-selection') {
                closeList();
                clearSelection();
            }
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('[data-pos-customer-combobox]') &&
                !e.target.closest('[data-pos-product-combobox]')) {
                closeList();
            }
        });

        document.querySelectorAll('[data-pos-focus-search]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (els.productSearch) {
                    els.productSearch.focus();
                }
            });
        });
        document.querySelectorAll('[data-pos-focus-barcode]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (els.barcodeInput) {
                    els.barcodeInput.focus();
                }
            });
        });
        var customerSheet = root.querySelector('[data-pos-customer-sheet]');
        var recentWrap = root.querySelector('[data-pos-customer-recent-wrap]');
        var recentList = root.querySelector('[data-pos-customer-recent]');
        var loyaltyHint = root.querySelector('[data-pos-customer-loyalty-hint]');
        var recentKey = 'rateb_pos_recent_customers_' + (config.companyId || 0);

        function readRecentCustomers() {
            try {
                return JSON.parse(localStorage.getItem(recentKey) || '[]');
            } catch (e) {
                return [];
            }
        }

        function pushRecentCustomer(c) {
            if (!c || !c.id) {
                return;
            }
            var list = readRecentCustomers().filter(function (x) { return x.id !== c.id; });
            list.unshift({ id: c.id, name: c.name || '', phone: c.phone || '' });
            try {
                localStorage.setItem(recentKey, JSON.stringify(list.slice(0, 8)));
            } catch (e2) { /* ignore */ }
            renderRecentCustomers();
        }

        function renderRecentCustomers() {
            if (!recentList || !recentWrap) {
                return;
            }
            var items = readRecentCustomers();
            recentList.innerHTML = '';
            if (!items.length) {
                recentWrap.hidden = true;
                return;
            }
            recentWrap.hidden = false;
            items.forEach(function (c) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'rateb-pos__customer-chip';
                btn.textContent = c.name || c.phone || ('#' + c.id);
                btn.addEventListener('click', function () {
                    state.customer = { id: c.id, name: c.name, phone: c.phone };
                    renderCustomer();
                    scheduleSave();
                    refreshCustomerLoyalty();
                });
                recentList.appendChild(btn);
            });
        }

        function refreshCustomerLoyalty() {
            if (!loyaltyHint || !api.loyaltyBalance) {
                return;
            }
            if (!state.customer || !state.customer.id) {
                loyaltyHint.hidden = true;
                return;
            }
            fetchJson(api.loyaltyBalance + '?customer_id=' + encodeURIComponent(state.customer.id))
                .then(function (data) {
                    var bal = data.balance != null ? data.balance : (data.points != null ? data.points : 0);
                    loyaltyHint.hidden = false;
                    loyaltyHint.textContent = t('pos_loyalty_balance', 'Loyalty balance') + ': ' + money(bal);
                })
                .catch(function () {
                    loyaltyHint.hidden = true;
                });
        }

        var walkInBtn = root.querySelector('[data-pos-customer-walkin]');
        if (walkInBtn) {
            walkInBtn.addEventListener('click', function () {
                state.customer = null;
                renderCustomer();
                scheduleSave();
                if (loyaltyHint) {
                    loyaltyHint.hidden = true;
                }
            });
        }

        renderRecentCustomers();

        document.querySelectorAll('[data-pos-focus-customer]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (customerSheet) {
                    customerSheet.hidden = false;
                }
                renderRecentCustomers();
                refreshCustomerLoyalty();
                if (els.customerInput) {
                    els.customerInput.focus();
                }
            });
        });

        if (customerSheet) {
            customerSheet.querySelectorAll('[data-pos-customer-sheet-close]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    customerSheet.hidden = true;
                    closeList();
                });
            });
        }

        window.RatebPosPushRecentCustomer = pushRecentCustomer;
        window.RatebPosRefreshCustomerLoyalty = refreshCustomerLoyalty;

        var quickAddBtn = root.querySelector('[data-pos-customer-quick-add]');
        if (quickAddBtn && api.customerCreate) {
            quickAddBtn.addEventListener('click', function () {
                var nameEl = root.querySelector('[data-pos-customer-quick-name]');
                var phoneEl = root.querySelector('[data-pos-customer-quick-phone]');
                var name = nameEl ? nameEl.value.trim() : '';
                if (!name) {
                    showStatus(t('pos_customer_name', 'Customer name'), true);
                    return;
                }
                var body = new URLSearchParams();
                body.set('_csrf', csrfToken());
                body.set('name', name);
                body.set('phone', phoneEl ? phoneEl.value.trim() : '');
                fetchJson(api.customerCreate, { method: 'POST', body: body })
                    .then(function (data) {
                        state.customer = data.customer || null;
                        renderCustomer();
                        scheduleSave();
                        pushRecentCustomer(state.customer);
                        refreshCustomerLoyalty();
                        if (nameEl) {
                            nameEl.value = '';
                        }
                        if (phoneEl) {
                            phoneEl.value = '';
                        }
                        if (customerSheet) {
                            customerSheet.hidden = true;
                        }
                        showStatus(t('pos_add_customer', 'Add customer'));
                    })
                    .catch(function (err) {
                        showStatus(err.message, true);
                    });
            });
        }

        var barcodeFocusBtn = document.querySelector('[data-pos-barcode-focus]');
        if (barcodeFocusBtn && els.barcodeInput) {
            barcodeFocusBtn.addEventListener('click', function () {
                els.barcodeInput.focus();
            });
        }
        var fullscreenBtn = document.querySelector('[data-pos-fullscreen]');
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', function () {
                var el = document.documentElement;
                if (!document.fullscreenElement && el.requestFullscreen) {
                    el.requestFullscreen().catch(function () {});
                } else if (document.exitFullscreen) {
                    document.exitFullscreen().catch(function () {});
                }
            });
        }
    }

    bindShortcutsHelp();
    bindEvents();
    loadSession();
    root.setAttribute('data-pos-register-ready', '1');

    window.RatebPosRegisterReset = function () {
        state.lines = [];
        state.customer = null;
        state.selectedLineId = null;
        renderCustomer();
        renderCartWithoutSave();
        scheduleSave();
    };
    window.RatebPosRegisterApplyLines = function (lines, totals) {
        state.lines = Array.isArray(lines) ? lines.slice() : [];
        if (totals) {
            state.totals = totals;
        }
        renderCartWithoutSave();
        scheduleSave();
    };
    window.RatebPosRegister = {
        addProduct: addProduct,
        adjustLineQty: adjustLineQty,
        getState: function () { return state; }
    };
    Object.defineProperty(window, 'RatebPosRegisterState', {
        configurable: true,
        get: function () {
            return state;
        }
    });
})();
