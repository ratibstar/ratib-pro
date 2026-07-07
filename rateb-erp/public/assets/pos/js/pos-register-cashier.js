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
    var urls = config.urls || {};
    var i18n = config.i18n || {};

    function t(key, fb) {
        return i18n[key] || fb || key;
    }

    function csrf() {
        return config.csrf || (document.querySelector('meta[name="rateb-csrf"]') || {}).content || '';
    }

    function notify(msg, isError) {
        if (window.RatebPosNotify) {
            window.RatebPosNotify(msg, isError);
        }
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

    function modal(modalEl, open) {
        if (!modalEl) {
            return;
        }
        modalEl.hidden = !open;
    }

    function bindModalClose(modalEl, selector) {
        if (!modalEl) {
            return;
        }
        root.querySelectorAll(selector).forEach(function (btn) {
            btn.addEventListener('click', function () {
                modal(modalEl, false);
            });
        });
    }

    function syncOfflineQueueBadge() {
        var badge = root.querySelector('[data-pos-offline-queue]');
        if (!badge) {
            return;
        }
        var depth = (window.RatebPosOffline && window.RatebPosOffline.queueDepth) || 0;
        badge.textContent = String(depth);
        badge.hidden = depth < 1;
    }

    function deferIdle(fn, timeoutMs) {
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(fn, { timeout: timeoutMs || 2500 });
            return;
        }
        setTimeout(fn, timeoutMs || 500);
    }

    function bindCashierTools() {
        var toolsModal = root.querySelector('[data-pos-cashier-tools-modal]');
        var openBtn = root.querySelector('[data-pos-cashier-tools-open]');
        if (openBtn) {
            openBtn.addEventListener('click', function () {
                modal(toolsModal, true);
            });
        }
        bindModalClose(toolsModal, '[data-pos-cashier-tools-close]');

        var shiftLink = root.querySelector('[data-pos-shift-close-link]');
        if (shiftLink && urls.shiftClose && config.canShiftClose) {
            shiftLink.href = urls.shiftClose;
            shiftLink.hidden = false;
        }

        var drawerOpenBtn = root.querySelector('[data-pos-drawer-open]');
        if (drawerOpenBtn && api.drawerOpen) {
            drawerOpenBtn.addEventListener('click', function () {
                var body = new URLSearchParams();
                body.set('_csrf', csrf());
                fetchJson(api.drawerOpen, { method: 'POST', body: body })
                    .then(function () {
                        notify(t('pos_open_drawer', 'Open drawer'));
                    })
                    .catch(function (err) {
                        notify(err.message, true);
                    });
            });
        }

        var drawerForm = root.querySelector('[data-pos-drawer-event-form]');
        if (drawerForm && api.drawerEvent) {
            drawerForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (!config.canDrawerManage) {
                    notify(t('invalid_request', 'Not allowed'), true);
                    return;
                }
                var typeEl = root.querySelector('[data-pos-drawer-event-type]');
                var amountEl = root.querySelector('[data-pos-drawer-event-amount]');
                var notesEl = root.querySelector('[data-pos-drawer-event-notes]');
                var body = new URLSearchParams();
                body.set('_csrf', csrf());
                body.set('event_type', typeEl ? typeEl.value : 'pay_in');
                body.set('amount', amountEl ? String(amountEl.value || 0) : '0');
                body.set('notes', notesEl ? String(notesEl.value || '') : '');
                fetchJson(api.drawerEvent, { method: 'POST', body: body })
                    .then(function () {
                        notify(t('saved', 'Saved'));
                        modal(toolsModal, false);
                    })
                    .catch(function (err) {
                        notify(err.message, true);
                    });
            });
        }
    }

    function bindXReport() {
        var xModal = root.querySelector('[data-pos-x-report-modal]');
        var bodyEl = root.querySelector('[data-pos-x-report-body]');
        var openBtn = root.querySelector('[data-pos-x-report-open]');
        if (!openBtn || !api.xReport) {
            return;
        }
        openBtn.addEventListener('click', function () {
            modal(xModal, true);
            if (bodyEl) {
                bodyEl.innerHTML = '<p class="rateb-pos__hint">' + t('pos_register_loading', 'Loading…') + '</p>';
            }
            fetchJson(api.xReport).then(function (data) {
                var r = data.report || {};
                var sales = r.sales || {};
                var payments = r.payments || {};
                bodyEl.innerHTML =
                    '<p><strong>' + (r.report_no || r.shift_no || '') + '</strong></p>' +
                    '<p>' + t('pos_gross_sales', 'Gross sales') + ': ' + Number(sales.gross_total || 0).toFixed(2) + '</p>' +
                    '<p>' + t('pos_net_sales', 'Net sales') + ': ' + Number(sales.net_total || 0).toFixed(2) + '</p>' +
                    '<p>' + t('pos_expected_cash', 'Expected cash') + ': ' + Number((r.drawer && r.drawer.expected_balance) || r.expected_cash || 0).toFixed(2) + '</p>' +
                    '<p>' + t('pos_refund_cash', 'Cash') + ': ' + Number(payments.cash || 0).toFixed(2) + '</p>';
            }).catch(function (err) {
                if (bodyEl) {
                    bodyEl.innerHTML = '<p class="rateb-pos__hint">' + err.message + '</p>';
                }
            });
        });
        bindModalClose(xModal, '[data-pos-x-report-close]');
    }

    function bindShortcutsModal() {
        var modalEl = root.querySelector('[data-pos-shortcuts-modal]');
        var visibleList = root.querySelector('[data-pos-shortcuts-visible]');
        var hiddenList = root.querySelector('[data-pos-shortcuts-list]');
        var openBtn = root.querySelector('[data-pos-shortcuts-open]');
        if (openBtn) {
            openBtn.addEventListener('click', function () {
                if (visibleList && hiddenList) {
                    visibleList.innerHTML = hiddenList.innerHTML;
                }
                modal(modalEl, true);
            });
        }
        bindModalClose(modalEl, '[data-pos-shortcuts-close]');
    }

    function bindLineDiscount() {
        var modalEl = root.querySelector('[data-pos-line-discount-modal]');
        var targetEl = root.querySelector('[data-pos-line-discount-target]');
        var typeEl = root.querySelector('[data-pos-line-discount-type]');
        var valueEl = root.querySelector('[data-pos-line-discount-value]');
        var applyBtn = root.querySelector('[data-pos-line-discount-apply]');
        var openBtn = root.querySelector('[data-pos-line-discount-open]');

        function openModal() {
            var st = window.RatebPosRegisterState || { lines: [], selectedLineId: null };
            var line = (st.lines || []).find(function (l) {
                return l.id === st.selectedLineId;
            });
            if (!line) {
                notify(t('pos_select_line', 'Select a line'), true);
                return;
            }
            if (!config.canDiscount) {
                notify(t('pos_discount_permission_denied', 'No discount permission'), true);
                return;
            }
            if (targetEl) {
                targetEl.textContent = line.item_name || '';
            }
            if (typeEl) {
                typeEl.value = Number(line.discount_percent || 0) > 0 ? 'percent' : 'amount';
            }
            if (valueEl) {
                valueEl.value = String(Number(line.discount_percent || line.discount_amount || 0) || 0);
            }
            modal(modalEl, true);
        }

        if (openBtn) {
            openBtn.addEventListener('click', openModal);
        }
        bindModalClose(modalEl, '[data-pos-line-discount-close]');

        if (applyBtn && api.cartUpdate) {
            applyBtn.addEventListener('click', function () {
                var st = window.RatebPosRegisterState || {};
                if (!st.selectedLineId) {
                    return;
                }
                var body = new URLSearchParams();
                body.set('_csrf', csrf());
                body.set('line_id', st.selectedLineId);
                body.set('discount_type', typeEl ? typeEl.value : 'amount');
                body.set('discount_value', valueEl ? String(valueEl.value || 0) : '0');
                fetchJson(api.cartUpdate, { method: 'POST', body: body })
                    .then(function (data) {
                        if (window.RatebPosRegisterApplyLines) {
                            window.RatebPosRegisterApplyLines(data.lines || [], data.totals || null);
                        }
                        modal(modalEl, false);
                        notify(t('pos_apply_line_discount', 'Discount applied'));
                    })
                    .catch(function (err) {
                        notify(err.message, true);
                    });
            });
        }
    }

    function bindReprintLast() {
        var btn = root.querySelector('[data-pos-reprint-last]');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            var cached = null;
            try {
                cached = JSON.parse(localStorage.getItem('rateb_pos_last_receipt') || 'null');
            } catch (e) {
                cached = null;
            }
            if (cached && window.RatebPosShowReceipt) {
                window.RatebPosShowReceipt(cached);
                return;
            }
            if (!api.lastReceipt) {
                notify(t('no_records', 'No records'), true);
                return;
            }
            fetchJson(api.lastReceipt).then(function (data) {
                if (data.receipt && window.RatebPosShowReceipt) {
                    window.RatebPosShowReceipt(data.receipt);
                }
            }).catch(function (err) {
                notify(err.message, true);
            });
        });
    }

    bindCashierTools();
    bindXReport();
    bindShortcutsModal();
    bindLineDiscount();
    bindReprintLast();

    deferIdle(syncOfflineQueueBadge, 2500);
    setInterval(syncOfflineQueueBadge, 15000);
    window.addEventListener('online', syncOfflineQueueBadge);
})();
