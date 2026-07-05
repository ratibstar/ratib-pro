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
        totals: { subtotal: 0, tax: 0, total: 0, discount_total: 0 }
    };

    var els = {
        cartBody: root.querySelector('[data-pos-cart-body]'),
        cartLines: root.querySelector('[data-pos-cart-lines]'),
        cartEmpty: root.querySelector('[data-pos-cart-empty]'),
        cartTable: root.querySelector('[data-pos-cart-table]'),
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
        selectedLine: root.querySelector('[data-pos-selected-line]'),
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

    function showStatus(msg) {
        if (!els.status || !msg) {
            return;
        }
        els.status.textContent = msg;
        els.status.classList.add('is-visible');
        clearTimeout(statusTimer);
        statusTimer = setTimeout(function () {
            els.status.classList.remove('is-visible');
        }, 2200);
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
        var totalNum = Number(state.totals.total || 0);
        if (lastRenderedTotal !== null && lastRenderedTotal !== totalNum &&
            window.RatebPosMotion && typeof window.RatebPosMotion.pulseTotal === 'function') {
            window.RatebPosMotion.pulseTotal();
        }
        lastRenderedTotal = totalNum;
    }

    function cartLineIcon(line) {
        var name = ((line.item_name || '') + ' ' + (line.item_code || '')).toLowerCase();
        if (name.indexOf('coffee') >= 0 || name.indexOf('latte') >= 0) {
            return 'fa-mug-hot';
        }
        if (name.indexOf('pizza') >= 0) {
            return 'fa-pizza-slice';
        }
        if (name.indexOf('burger') >= 0) {
            return 'fa-burger';
        }
        if (name.indexOf('cake') >= 0 || name.indexOf('dessert') >= 0) {
            return 'fa-cookie';
        }
        if (name.indexOf('drink') >= 0 || name.indexOf('juice') >= 0) {
            return 'fa-glass-water';
        }
        if (name.indexOf('bread') >= 0) {
            return 'fa-bread-slice';
        }
        return 'fa-box-open';
    }

    function renderPremiumCartLine(line) {
        var card = document.createElement('article');
        card.className = 'rateb-pos-v2__line';
        card.setAttribute('role', 'listitem');
        card.setAttribute('data-line-id', line.id || '');
        card.tabIndex = 0;
        if (state.selectedLineId === line.id) {
            card.classList.add('is-selected');
        }

        var discountBadge = '';
        var disc = Number(line.discount_amount || line.line_discount || 0);
        if (disc > 0) {
            discountBadge = '<span class="rateb-pos-v2__line-tag rateb-pos-v2__line-tag--discount">-' + money(disc) + '</span>';
        }
        var serialBadge = line.serial_no
            ? '<span class="rateb-pos-v2__line-tag">SN: ' + escapeHtml(line.serial_no) + '</span>'
            : (line.requires_serial && !line.serial_no
                ? '<button type="button" class="rateb-pos-v2__line-tag" data-pos-pick-serial="' + escapeAttr(line.product_id) + '" data-line-id="' + escapeAttr(line.id) + '">' + escapeHtml(t('pos_serial_select', 'Select serial')) + '</button>'
                : '');
        var batchBadge = (line.batch_preview && line.batch_preview.allocations && line.batch_preview.allocations.length)
            ? '<span class="rateb-pos-v2__line-tag">FEFO</span>' + batchPreviewHtml(line)
            : (line.has_batches ? '<span class="rateb-pos-v2__line-tag">FEFO</span>' : '');

        card.innerHTML =
            '<div class="rateb-pos-v2__line-thumb" aria-hidden="true">' +
            '<i class="fa-solid ' + cartLineIcon(line) + '"></i></div>' +
            '<div class="rateb-pos-v2__line-main">' +
            '<p class="rateb-pos-v2__line-name">' + escapeHtml(line.item_name || '') + '</p>' +
            '<p class="rateb-pos-v2__line-meta">' + escapeHtml(line.item_code || '') + '</p>' +
            (line.notes ? '<p class="rateb-pos-v2__line-note">' + escapeHtml(line.notes) + '</p>' : '') +
            '<div class="rateb-pos-v2__line-tags">' + serialBadge + batchBadge + discountBadge + '</div>' +
            '<p class="rateb-pos-v2__line-price">' + money(line.line_total) + '</p>' +
            '</div>' +
            '<div class="rateb-pos-v2__line-actions">' +
            '<div class="rateb-pos-v2__line-qty">' +
            '<button type="button" class="rateb-pos-qty-btn" data-pos-qty-down="' + escapeAttr(line.id) + '" aria-label="' + escapeAttr(t('pos_decrease_qty', 'Decrease quantity')) + '">−</button>' +
            '<span class="rateb-pos-qty-value" aria-live="polite">' + escapeHtml(String(line.quantity)) + '</span>' +
            '<button type="button" class="rateb-pos-qty-btn" data-pos-qty-up="' + escapeAttr(line.id) + '" aria-label="' + escapeAttr(t('pos_increase_qty', 'Increase quantity')) + '">+</button>' +
            '</div>' +
            '<div class="rateb-pos-v2__line-tools">' +
            '<button type="button" class="rateb-pos-icon-action" data-pos-line-select="' + escapeAttr(line.id) + '" aria-label="' + escapeAttr(t('notes', 'Notes')) + '">' +
            '<i class="fa-solid fa-note-sticky" aria-hidden="true"></i></button>' +
            '<button type="button" class="rateb-pos-icon-action rateb-pos-icon-action--danger" data-pos-remove="' + escapeAttr(line.id) + '" aria-label="' + escapeAttr(t('pos_remove_line', 'Remove')) + '">' +
            '<i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>' +
            '</div></div>';

        card.addEventListener('click', function (e) {
            if (e.target.closest('[data-pos-remove]') || e.target.closest('[data-pos-pick-serial]') ||
                e.target.closest('[data-pos-qty-up]') || e.target.closest('[data-pos-qty-down]')) {
                return;
            }
            selectLine(line.id);
        });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectLine(line.id);
            }
        });
        return card;
    }

    function bindPremiumCartControls(container) {
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
        container.querySelectorAll('[data-pos-line-select]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                selectLine(btn.getAttribute('data-pos-line-select'));
            });
        });
    }

    function renderPremiumCart() {
        if (!els.cartLines) {
            return;
        }
        els.cartLines.innerHTML = '';
        state.lines.forEach(function (line) {
            els.cartLines.appendChild(renderPremiumCartLine(line));
        });
        bindPremiumCartControls(els.cartLines);
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

    function batchPreviewHtml(line) {
        var preview = line.batch_preview;
        if (!preview || !preview.allocations || !preview.allocations.length) {
            return '';
        }
        var rows = preview.allocations.map(function (a) {
            return '<li><span class="rateb-pos-batch-no">' + escapeHtml(a.batch_no || '') + '</span> · ' +
                escapeHtml(String(a.quantity)) + ' · ' + escapeHtml(a.expiry_date || '—') + '</li>';
        }).join('');
        return '<details class="rateb-pos-batch-preview"><summary>' + escapeHtml(t('pos_fefo_preview', 'FEFO preview')) +
            '</summary><ul>' + rows + '</ul></details>';
    }

    function lineStockCell(line) {
        var avail = line.available_qty != null ? line.available_qty : '—';
        var serial = line.serial_no ? ('<br><small>' + escapeHtml(line.serial_no) + '</small>') : '';
        var serialBtn = line.requires_serial && !line.serial_no
            ? '<br><button type="button" class="btn btn-sm btn-outline-primary rateb-pos-serial-btn" data-pos-pick-serial="' + escapeAttr(line.product_id) + '" data-line-id="' + escapeAttr(line.id) + '">' + escapeHtml(t('pos_serial_select', 'Select serial')) + '</button>'
            : '';
        return escapeHtml(String(avail)) + serial + serialBtn;
    }

    function renderSelectedLineHint() {
        if (!els.selectedLine) {
            return;
        }
        if (!state.selectedLineId) {
            els.selectedLine.textContent = '';
            return;
        }
        var line = findLine(state.selectedLineId);
        if (!line) {
            els.selectedLine.textContent = '';
            return;
        }
        els.selectedLine.textContent = t('pos_selected_line', 'Selected') + ': ' + (line.item_name || '') +
            ' (' + t('pos_shortcut_qty_up', '+/- qty') + ')';
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
        if (els.cartLines) {
            renderPremiumCart();
        } else if (els.cartBody) {
            els.cartBody.innerHTML = '';
            state.lines.forEach(function (line) {
                var tr = document.createElement('tr');
                tr.setAttribute('data-line-id', line.id || '');
                tr.tabIndex = 0;
                tr.setAttribute('role', 'button');
                tr.setAttribute('aria-label', (line.item_name || '') + ', ' + t('pos_qty', 'Qty') + ' ' + line.quantity);
                if (state.selectedLineId === line.id) {
                    tr.classList.add('is-selected');
                }
                tr.innerHTML =
                    '<td><span class="rateb-pos-item-code">' + escapeHtml(line.item_code || '') + '</span><br>' +
                    '<strong>' + escapeHtml(line.item_name || '') + '</strong>' +
                    batchPreviewHtml(line) + '</td>' +
                    '<td class="rateb-pos-stock-cell">' + lineStockCell(line) + '</td>' +
                    '<td>' + escapeHtml(String(line.quantity)) + '</td>' +
                    '<td>' + money(line.unit_price) + '</td>' +
                    '<td>' + money(line.line_total) + '</td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-danger rateb-pos-line-btn" data-pos-remove="' + escapeAttr(line.id) + '" aria-label="' + escapeAttr(t('pos_remove_line', 'Remove')) + '">×</button></td>';
                tr.addEventListener('click', function (e) {
                    if (e.target.closest('[data-pos-remove]') || e.target.closest('[data-pos-pick-serial]')) {
                        return;
                    }
                    selectLine(line.id);
                });
                tr.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        selectLine(line.id);
                    }
                });
                els.cartBody.appendChild(tr);
            });
            els.cartBody.querySelectorAll('[data-pos-pick-serial]').forEach(function (btn) {
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
            els.cartBody.querySelectorAll('[data-pos-remove]').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    removeLine(btn.getAttribute('data-pos-remove'));
                });
            });
        }
        var count = state.lines.length;
        var prevCount = els.cartCount ? Number(els.cartCount.getAttribute('data-prev-count') || '0') : 0;
        if (els.cartCount) {
            els.cartCount.textContent = String(count);
            els.cartCount.setAttribute('data-prev-count', String(count));
            if (count > prevCount && window.RatebPosMotion && typeof window.RatebPosMotion.bumpCartCount === 'function') {
                window.RatebPosMotion.bumpCartCount();
            }
        }
        if (els.cartTable) {
            els.cartTable.classList.toggle('is-empty', count === 0);
        }
        if (els.cartEmpty) {
            els.cartEmpty.classList.toggle('is-hidden', count > 0);
        }
        renderSelectedLineHint();
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

    function clearCart() {
        state.lines = [];
        state.selectedLineId = null;
        renderCart();
    }

    function newSale() {
        clearCart();
        state.customer = null;
        renderCustomer();
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
        fetchJson(api.products + '?q=' + encodeURIComponent(q))
            .then(function (data) {
                renderListItems(els.productList, data.items || [], 'product');
            })
            .catch(function () { closeList(); });
    }

    function lookupBarcode(code) {
        if (!api.barcode || !code) {
            return;
        }
        fetchJson(api.barcode + '?code=' + encodeURIComponent(code))
            .then(function (data) {
                if (data.item) {
                    addProduct(data.item, 1);
                    showStatus(t('pos_add_to_cart', 'Added to cart'));
                }
            })
            .catch(function (err) {
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
                }
            } catch (e) { /* ignore */ }
            renderCustomer();
            renderCart();
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
            .catch(function () {
                renderCustomer();
                renderCart();
            });
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
        var modal = document.getElementById('rateb-pos-serial-modal');
        if (modal && typeof modal.close === 'function') {
            modal.close();
        }
        pendingSerialProduct = null;
        pendingLineId = null;
    }

    function openSerialPicker(product, qty, lineId) {
        pendingSerialProduct = product;
        pendingSerialQty = qty || 1;
        pendingLineId = lineId || null;
        var modal = document.getElementById('rateb-pos-serial-modal');
        if (!modal || !api.productSerials) {
            return;
        }
        var list = modal.querySelector('[data-pos-serial-list]');
        var productLabel = modal.querySelector('[data-pos-serial-product]');
        if (productLabel) {
            productLabel.textContent = product.item_name || '';
        }
        if (list) {
            list.innerHTML = '';
        }
        fetchJson(api.productSerials + '?id=' + encodeURIComponent(product.id))
            .then(function (data) {
                var items = data.items || [];
                if (!list) {
                    return;
                }
                if (!items.length) {
                    var empty = document.createElement('li');
                    empty.className = 'rateb-pos-serial-empty';
                    empty.textContent = t('pos_search_no_results', 'No results');
                    list.appendChild(empty);
                }
                items.forEach(function (serial) {
                    var li = document.createElement('li');
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
                    li.appendChild(btn);
                    list.appendChild(li);
                });
                if (typeof modal.showModal === 'function') {
                    modal.showModal();
                }
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
            els.clearCart.addEventListener('click', clearCart);
        }
        if (els.newSale) {
            els.newSale.addEventListener('click', newSale);
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
            } else if (action === 'pos-focus-customer' && els.customerInput) {
                els.customerInput.focus();
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
        document.querySelectorAll('[data-pos-focus-customer]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (els.customerInput) {
                    els.customerInput.focus();
                    els.customerInput.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        });
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
