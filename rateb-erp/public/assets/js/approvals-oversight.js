(function () {
    'use strict';

    var config = {};
    var labels = {};
    var activeRowKey = null;
    var clickBound = false;

    function rootEl() {
        return document.getElementById('rateb-approvals-oversight');
    }

    function refreshConfig() {
        config = {};
        var configEl = document.getElementById('rateb-approvals-config-json');
        if (configEl) {
            try {
                config = JSON.parse(configEl.textContent || '{}');
            } catch (e) {
                config = {};
            }
        }
        labels = config.labels || {};
    }

    function csrf() {
        return config.csrf || (document.querySelector('meta[name="rateb-csrf"]') || {}).getAttribute('content') || '';
    }

    function rowKeyFromEl(el) {
        var dataTr = el.closest('tr.rateb-approval-data-row[data-approval-row]');
        if (dataTr) {
            return dataTr.getAttribute('data-approval-row');
        }
        var detailTr = el.closest('tr.rateb-approval-detail-row[data-detail-for]');
        if (detailTr) {
            return detailTr.getAttribute('data-detail-for');
        }
        return activeRowKey;
    }

    function dataRow(key) {
        var root = rootEl();
        return root ? root.querySelector('tr.rateb-approval-data-row[data-approval-row="' + key + '"]') : null;
    }

    function detailRow(key) {
        var root = rootEl();
        return root ? root.querySelector('tr[data-detail-for="' + key + '"]') : null;
    }

    function closeAllDetails() {
        var root = rootEl();
        if (!root) {
            return;
        }
        root.querySelectorAll('.rateb-approval-detail-row').forEach(function (tr) {
            tr.classList.add('d-none');
        });
        root.querySelectorAll('.rateb-approval-data-row').forEach(function (tr) {
            tr.classList.remove('is-expanded');
        });
        activeRowKey = null;
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    function parseJsonResponse(res) {
        return res.json().then(function (data) {
            if (!res.ok || data.ok === false) {
                var err = new Error(data.message || labels.error || 'حدث خطأ غير متوقع');
                if (data.sql_error && data.message.indexOf(data.sql_error) === -1) {
                    err.message += ' — ' + data.sql_error;
                }
                err.status = res.status;
                err.payload = data;
                throw err;
            }
            return data;
        });
    }

    function confirmAction(message, variant) {
        // Offline: native confirm — ratebConfirm modal often fails if confirm JS is stale/uncached.
        try {
            if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                return Promise.resolve(window.confirm(message));
            }
            var badge = document.querySelector('[data-rateb-connection-status], #rateb-connection-indicator');
            if (badge && badge.classList.contains('is-offline')) {
                return Promise.resolve(window.confirm(message));
            }
        } catch (eOff) { /* fall through */ }
        var confirmFn = window.ratebConfirm || window.confirm;
        if (confirmFn === window.confirm) {
            return Promise.resolve(confirmFn(message));
        }
        try {
            var p = confirmFn(message, { variant: variant || 'primary' });
            if (p && typeof p.then === 'function') {
                return p.catch(function () {
                    return window.confirm(message);
                });
            }
            return Promise.resolve(!!p);
        } catch (eC) {
            return Promise.resolve(window.confirm(message));
        }
    }

    function confirmMessageForAction(action) {
        if (action === 'approve') {
            return labels.confirm_approve || 'Approve this request?';
        }
        if (action === 'reject') {
            return labels.confirm_reject || 'Confirm reject?';
        }
        if (action === 'undo') {
            return labels.confirm_undo || 'Confirm undo?';
        }
        return null;
    }

    function variantForAction(action) {
        if (action === 'reject') {
            return 'danger';
        }
        if (action === 'approve') {
            return 'primary';
        }
        return 'primary';
    }

    function rowUrls(row) {
        return {
            view: row.getAttribute('data-view-url') || '',
            edit: row.getAttribute('data-edit-url') || ''
        };
    }

    function renderMainRowActions(detail, row) {
        var ops = row.querySelector('.rateb-approval-ops');
        if (!ops) {
            return;
        }
        var urls = rowUrls(row);
        var viewUrl = (detail && detail.view_url) || urls.view;
        var editUrl = (detail && detail.edit_url) || urls.edit;
        var html = '';

        html += '<button type="button" class="rateb-approval-btn rateb-approval-btn-view" data-action="view" title="' + escapeHtml(labels.view || 'View') + '">'
            + '<i class="fas fa-eye"></i><span>' + escapeHtml(labels.view || 'View') + '</span></button>';

        if (editUrl) {
            html += '<a href="' + escapeHtml(editUrl) + '" class="rateb-approval-btn rateb-approval-btn-edit" data-rateb-edit-link="1" title="' + escapeHtml(labels.edit || 'Edit') + '">'
                + '<i class="fas fa-edit"></i><span>' + escapeHtml(labels.edit || 'Edit') + '</span></a>';
        }

        if (!detail || detail.can_approve) {
            html += '<button type="button" class="rateb-approval-btn rateb-approval-btn-approve" data-action="approve" title="' + escapeHtml(labels.approve || 'Approve') + '">'
                + '<i class="fas fa-check"></i><span>' + escapeHtml(labels.approve || 'Approve') + '</span></button>';
        }

        var canReject = detail ? detail.can_reject : row.getAttribute('data-can-reject') === '1';
        if (canReject && (!detail || detail.can_reject)) {
            html += '<button type="button" class="rateb-approval-btn rateb-approval-btn-reject" data-action="reject" title="' + escapeHtml(labels.reject || 'Reject') + '">'
                + '<i class="fas fa-times"></i><span>' + escapeHtml(labels.reject || 'Reject') + '</span></button>';
        }

        var showUndo = (detail && detail.can_undo)
            || (row.getAttribute('data-processed') === '1' && row.getAttribute('data-can-undo') === '1');
        if (showUndo) {
            html += '<button type="button" class="rateb-approval-btn rateb-approval-btn-undo" data-action="undo" title="' + escapeHtml(labels.undo || 'Undo') + '">'
                + '<i class="fas fa-rotate-left"></i><span>' + escapeHtml(labels.undo || 'Undo') + '</span></button>';
        }

        if (viewUrl) {
            html += '<a href="' + escapeHtml(viewUrl) + '" class="rateb-approval-btn rateb-approval-btn-link" target="_blank" rel="noopener" title="' + escapeHtml(labels.open_in_ops || 'Open') + '">'
                + '<i class="fas fa-external-link-alt"></i><span>' + escapeHtml(labels.open_in_ops || 'Open') + '</span></a>';
        }

        ops.innerHTML = html;
    }

    function applyDetailToRow(key, detail) {
        var row = dataRow(key);
        if (!row || !detail) {
            return;
        }
        var pendingLike = detail.can_approve || detail.can_reject;
        if (pendingLike) {
            row.classList.remove('rateb-approval-processed');
            row.removeAttribute('data-processed');
        } else {
            row.classList.add('rateb-approval-processed');
            row.setAttribute('data-processed', '1');
            if (detail.can_undo && row.getAttribute('data-can-undo') !== '0') {
                row.setAttribute('data-can-undo', '1');
            }
        }
        renderMainRowActions(detail, row);

        var statusCell = row.querySelector('.rateb-approval-status-chip');
        if (statusCell) {
            statusCell.textContent = detail.status_label || '';
            statusCell.classList.toggle('d-none', !detail.status_label);
        }
    }

    function renderDetailBody(detail) {
        var fieldsHtml = '';
        (detail.fields || []).forEach(function (field) {
            fieldsHtml += '<div class="rateb-approval-detail-field">'
                + '<div class="rateb-approval-detail-label">' + escapeHtml(field.label) + '</div>'
                + '<div class="rateb-approval-detail-value">' + escapeHtml(field.value) + '</div>'
                + '</div>';
        });

        var meta = '<div class="rateb-approval-detail-meta">'
            + '<span class="rateb-approval-detail-chip">' + escapeHtml(detail.type_label || '') + '</span>'
            + '<span class="rateb-approval-detail-chip rateb-approval-detail-status">' + escapeHtml(detail.status_label || '') + '</span>'
            + '</div>';

        var ops = '<div class="rateb-approval-ops rateb-approval-ops-detail">';
        if (detail.edit_url) {
            ops += '<a href="' + escapeHtml(detail.edit_url) + '" class="rateb-approval-btn rateb-approval-btn-edit"><i class="fas fa-edit"></i><span>' + escapeHtml(labels.edit || 'Edit') + '</span></a>';
        }
        if (detail.can_approve) {
            ops += '<button type="button" class="rateb-approval-btn rateb-approval-btn-approve" data-action="approve"><i class="fas fa-check"></i><span>' + escapeHtml(labels.approve || 'Approve') + '</span></button>';
        }
        if (detail.can_reject) {
            ops += '<button type="button" class="rateb-approval-btn rateb-approval-btn-reject" data-action="reject"><i class="fas fa-times"></i><span>' + escapeHtml(labels.reject || 'Reject') + '</span></button>';
        }
        if (detail.can_undo) {
            ops += '<button type="button" class="rateb-approval-btn rateb-approval-btn-undo" data-action="undo"><i class="fas fa-rotate-left"></i><span>' + escapeHtml(labels.undo || 'Undo') + '</span></button>';
        }
        if (detail.view_url) {
            ops += '<a href="' + escapeHtml(detail.view_url) + '" class="rateb-approval-btn rateb-approval-btn-link" data-rateb-full-nav="1"><i class="fas fa-external-link-alt"></i><span>' + escapeHtml(labels.open_in_ops || 'Open') + '</span></a>';
        }
        ops += '<button type="button" class="rateb-approval-btn rateb-approval-btn-close" data-action="close-detail"><i class="fas fa-chevron-up"></i><span>' + escapeHtml(labels.close || 'Close') + '</span></button>';
        ops += '</div>';

        return '<div class="rateb-approval-detail-header">'
            + '<div><h6 class="mb-1">' + escapeHtml(labels.approval_detail || 'Details') + '</h6>'
            + '<div class="text-muted small rateb-ltr-num">' + escapeHtml(detail.reference || '') + ' · ' + escapeHtml(detail.company_name || '') + '</div></div>'
            + meta
            + '</div>'
            + '<div class="rateb-approval-detail-grid">' + fieldsHtml + '</div>'
            + ops;
    }

    function loadDetail(key, onLoaded) {
        var row = dataRow(key);
        var detailTr = detailRow(key);
        if (!row || !detailTr) {
            return;
        }
        var pane = detailTr.querySelector('.rateb-approval-detail-pane');
        var loading = pane.querySelector('.rateb-approval-detail-loading');
        var body = pane.querySelector('.rateb-approval-detail-body');
        loading.classList.remove('d-none');
        body.classList.add('d-none');
        body.innerHTML = '';

        var params = new URLSearchParams({
            source_key: row.getAttribute('data-source-key') || '',
            record_id: row.getAttribute('data-record-id') || '',
            company_id: row.getAttribute('data-company-id') || ''
        });

        fetch(config.detailUrl + '?' + params.toString(), {
            cache: 'no-store',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(parseJsonResponse)
            .then(function (data) {
                if (!data.detail) {
                    throw new Error(labels.error || 'حدث خطأ غير متوقع');
                }
                body.innerHTML = renderDetailBody(data.detail);
                applyDetailToRow(key, data.detail);
                loading.classList.add('d-none');
                body.classList.remove('d-none');
                if (typeof onLoaded === 'function') {
                    onLoaded(data.detail);
                }
            })
            .catch(function (err) {
                var msg = err && err.message ? String(err.message) : (labels.error || 'حدث خطأ غير متوقع');
                try {
                    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
                        msg = 'التفاصيل تحتاج اتصال أو زيارة الصفحة وأنت متصل مرة لحفظها أوفلاين. استخدم زر التعديل إن كان متاحاً.';
                    } else if (/failed to fetch|network|internet_disconnected|offline/i.test(msg)) {
                        msg = 'تعذر تحميل التفاصيل — تحقق من الاتصال.';
                    }
                } catch (eM) { /* ignore */ }
                body.innerHTML = '<div class="alert alert-warning m-3">' + escapeHtml(msg) + '</div>';
                loading.classList.add('d-none');
                body.classList.remove('d-none');
            });
    }

    function openDetail(key) {
        if (activeRowKey === key) {
            closeAllDetails();
            return;
        }
        closeAllDetails();
        var row = dataRow(key);
        var detailTr = detailRow(key);
        if (!row || !detailTr) {
            return;
        }
        activeRowKey = key;
        row.classList.add('is-expanded');
        detailTr.classList.remove('d-none');
        loadDetail(key);
        detailTr.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function setRowBusy(key, busy) {
        var row = dataRow(key);
        if (!row) {
            return;
        }
        row.querySelectorAll('.rateb-approval-btn[data-action]').forEach(function (btn) {
            btn.disabled = !!busy;
        });
        var detailTr = detailRow(key);
        if (detailTr) {
            detailTr.querySelectorAll('.rateb-approval-btn[data-action]').forEach(function (btn) {
                btn.disabled = !!busy;
            });
        }
    }

    function summaryCardKeyForSource(sourceKey) {
        if (sourceKey === 'company_registration') {
            return 'company_registration';
        }
        if (sourceKey === 'workflow_instance') {
            return 'workflow_instance';
        }
        if (sourceKey === 'journal_entry' || sourceKey === 'cash_voucher') {
            return 'journal_entry';
        }
        if (sourceKey === 'hr_leave' || sourceKey === 'hr_permission'
            || sourceKey === 'hr_request' || sourceKey === 'hr_payroll') {
            return 'hr_leave';
        }
        return 'supplier_evaluation';
    }

    function cardCountFromSummary(summary, cardKey) {
        summary = summary || {};
        if (cardKey === 'total') {
            return (intOrZero(summary.total));
        }
        if (cardKey === 'journal_entry') {
            return intOrZero(summary.journal_entry) + intOrZero(summary.cash_voucher);
        }
        if (cardKey === 'supplier_evaluation') {
            return intOrZero(summary.supplier_evaluation)
                + intOrZero(summary.contract_renewal)
                + intOrZero(summary.asset_maintenance)
                + intOrZero(summary.asset_assignment)
                + intOrZero(summary.device_maintenance)
                + intOrZero(summary.device_spare_part)
                + intOrZero(summary.inventory_audit);
        }
        if (cardKey === 'hr_leave') {
            return intOrZero(summary.hr_leave)
                + intOrZero(summary.hr_permission)
                + intOrZero(summary.hr_request)
                + intOrZero(summary.hr_payroll);
        }
        return intOrZero(summary[cardKey]);
    }

    function intOrZero(v) {
        var n = parseInt(v, 10);
        return isFinite(n) ? n : 0;
    }

    function applySummaryCards(summary) {
        var root = rootEl();
        if (!root || !summary) {
            return;
        }
        root.querySelectorAll('[data-summary-card]').forEach(function (card) {
            var key = card.getAttribute('data-summary-card') || '';
            var el = card.querySelector('[data-summary-count]');
            if (!el || !key) {
                return;
            }
            el.textContent = String(cardCountFromSummary(summary, key));
        });
    }

    function navHrefMatches(href, routeSuffix) {
        if (!href || !routeSuffix) {
            return false;
        }
        try {
            var path = new URL(href, window.location.href).pathname.replace(/\/+$/, '');
            var needle = String(routeSuffix).replace(/\/+$/, '');
            return path.endsWith(needle);
        } catch (eH) {
            return href.indexOf(routeSuffix) !== -1;
        }
    }

    function setNavBadge(routeSuffix, count) {
        var links = document.querySelectorAll('#rateb-sidebar a.rateb-nav-link, .rateb-sidebar a.rateb-nav-link');
        var n = Math.max(0, intOrZero(count));
        var matched = null;
        Array.prototype.forEach.call(links, function (a) {
            var href = a.getAttribute('data-rateb-href') || a.getAttribute('href') || '';
            if (!navHrefMatches(href, routeSuffix)) {
                return;
            }
            matched = a;
            var badge = a.querySelector('.rateb-nav-badge');
            if (n <= 0) {
                if (badge) {
                    badge.remove();
                }
                return;
            }
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'rateb-nav-badge rateb-nav-badge--pending';
                a.appendChild(badge);
            }
            badge.textContent = String(n);
        });
        return matched;
    }

    function setGroupBadgeNear(linkEl, count) {
        if (!linkEl) {
            return;
        }
        var n = Math.max(0, intOrZero(count));
        var group = linkEl.closest('.rateb-nav-group');
        if (!group) {
            return;
        }
        var toggle = group.querySelector(':scope > .rateb-nav-group-toggle, :scope > .rateb-nav-subgroup-toggle');
        if (!toggle) {
            return;
        }
        var badge = toggle.querySelector('.rateb-nav-badge');
        if (n <= 0) {
            if (badge) {
                badge.remove();
            }
            return;
        }
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'rateb-nav-badge rateb-nav-group-badge rateb-nav-badge--pending';
            var chevron = toggle.querySelector('.rateb-nav-group-chevron, .rateb-nav-subgroup-chevron');
            if (chevron) {
                toggle.insertBefore(badge, chevron);
            } else {
                toggle.appendChild(badge);
            }
        }
        badge.textContent = String(n);
    }

    function applyMenuCounts(menuCounts) {
        if (!menuCounts) {
            return;
        }
        var approvalsLink = setNavBadge('/admin/oversight/approvals', menuCounts.approvals);
        setNavBadge('/admin/oversight/companies-approvals', menuCounts.company_pending);
        setNavBadge('/admin/oversight/procurement', menuCounts.procurement);
        setNavBadge('/admin/oversight/rfq', menuCounts.rfq);
        setNavBadge('/admin/oversight/inventory', menuCounts.inventory);
        setNavBadge('/admin/oversight/supplier-evaluations', menuCounts.supplier_evaluations);

        // Section badge (مراقبة الإدارة) — only the group that owns approvals.
        setGroupBadgeNear(approvalsLink, menuCounts.total);
        // Subgroup badge (متابعة المنصة) sums child link badges; recompute from known keys.
        var subTotal = intOrZero(menuCounts.approvals)
            + intOrZero(menuCounts.procurement)
            + intOrZero(menuCounts.rfq)
            + intOrZero(menuCounts.inventory)
            + intOrZero(menuCounts.supplier_evaluations);
        if (approvalsLink) {
            var sub = approvalsLink.closest('.rateb-nav-subgroup');
            if (sub) {
                var subToggle = sub.querySelector(':scope > .rateb-nav-subgroup-toggle');
                if (subToggle) {
                    var subBadge = subToggle.querySelector('.rateb-nav-badge');
                    if (subTotal <= 0) {
                        if (subBadge) {
                            subBadge.remove();
                        }
                    } else {
                        if (!subBadge) {
                            subBadge = document.createElement('span');
                            subBadge.className = 'rateb-nav-badge rateb-nav-badge--pending';
                            var subChev = subToggle.querySelector('.rateb-nav-subgroup-chevron');
                            if (subChev) {
                                subToggle.insertBefore(subBadge, subChev);
                            } else {
                                subToggle.appendChild(subBadge);
                            }
                        }
                        subBadge.textContent = String(subTotal);
                    }
                }
            }
        }
    }

    function removeProcessedRow(key) {
        var row = dataRow(key);
        var detail = detailRow(key);
        if (activeRowKey === key) {
            activeRowKey = null;
        }
        if (detail) {
            detail.remove();
        }
        if (row) {
            row.remove();
        }
        var root = rootEl();
        if (!root) {
            return;
        }
        var tbody = root.querySelector('table.rateb-approvals-table tbody');
        if (!tbody) {
            return;
        }
        if (!tbody.querySelector('tr.rateb-approval-data-row')) {
            var colSpan = 5;
            var table = root.querySelector('table.rateb-approvals-table');
            if (table) {
                var thCount = table.querySelectorAll('thead th').length;
                if (thCount > 0) {
                    colSpan = thCount;
                }
            }
            tbody.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-center text-muted py-4">'
                + escapeHtml(labels.no_records || 'No records')
                + '</td></tr>';
            var bar = root.querySelector('[data-rateb-bulk-bar]');
            if (bar) {
                bar.classList.add('d-none');
            }
        }
    }

    function syncRowAfterAction(action, key, data) {
        var row = dataRow(key);
        var sourceKey = row ? (row.getAttribute('data-source-key') || '') : '';

        if (action === 'approve' || action === 'reject') {
            removeProcessedRow(key);
            if (data.summary) {
                applySummaryCards(data.summary);
            } else if (sourceKey) {
                // Optimistic local bump when server omitted summary.
                var root = rootEl();
                if (root) {
                    [['total', -1], [summaryCardKeyForSource(sourceKey), -1]].forEach(function (pair) {
                        var card = root.querySelector('[data-summary-card="' + pair[0] + '"] [data-summary-count]');
                        if (!card) {
                            return;
                        }
                        card.textContent = String(Math.max(0, intOrZero(card.textContent) + pair[1]));
                    });
                }
            }
            if (data.menu_counts) {
                applyMenuCounts(data.menu_counts);
            }
            return;
        }

        // Undo: keep row and refresh actions/detail from server payload.
        if (data.detail) {
            applyDetailToRow(key, data.detail);
            var detailTr = detailRow(key);
            if (!activeRowKey || activeRowKey !== key) {
                openDetail(key);
            } else if (detailTr) {
                var body = detailTr.querySelector('.rateb-approval-detail-body');
                if (body) {
                    body.innerHTML = renderDetailBody(data.detail);
                    body.classList.remove('d-none');
                    var loadingEl = detailTr.querySelector('.rateb-approval-detail-loading');
                    if (loadingEl) {
                        loadingEl.classList.add('d-none');
                    }
                }
            }
            if (data.summary) {
                applySummaryCards(data.summary);
            }
            if (data.menu_counts) {
                applyMenuCounts(data.menu_counts);
            }
            return;
        }
        loadDetail(key);
    }

    function postAction(action, key) {
        var row = dataRow(key);
        if (!row || !key) {
            flashToast(labels.error || 'حدث خطأ غير متوقع', 'danger');
            return;
        }
        if (row.getAttribute('data-processed') === '1' && (action === 'approve' || action === 'reject')) {
            flashToast(labels.already_processed || labels.error || 'حدث خطأ غير متوقع', 'warning');
            loadDetail(key);
            return;
        }
        var url = config.decideUrl;
        if (!url) {
            var urlMap = {
                approve: config.approveUrl,
                reject: config.rejectUrl,
                undo: config.undoUrl
            };
            url = urlMap[action];
        }
        if (!url) {
            flashToast(labels.error || 'حدث خطأ غير متوقع', 'danger');
            return;
        }

        var confirmMsg = confirmMessageForAction(action);

        function runPost() {
            var form = new FormData();
            form.append('_csrf', csrf());
            form.append('decision', action);
            form.append('source_key', row.getAttribute('data-source-key') || '');
            form.append('record_id', row.getAttribute('data-record-id') || '');
            form.append('company_id', row.getAttribute('data-company-id') || '');
            if (config.typeFilter) {
                form.append('type_filter', config.typeFilter);
            }

            setRowBusy(key, true);

            function queueOfflineDecision() {
                try {
                    var DEFERRED_KEY = 'rateb_deferred_http_forms_v2';
                    var raw = localStorage.getItem(DEFERRED_KEY);
                    var list = raw ? JSON.parse(raw) : [];
                    if (!Array.isArray(list)) {
                        list = [];
                    }
                    var fields = {};
                    form.forEach(function (v, k) {
                        if (k === '_csrf') {
                            return;
                        }
                        fields[k] = String(v);
                    });
                    var entry = {
                        id: 'ap-' + Date.now() + '-' + Math.floor(Math.random() * 1e6),
                        url: url,
                        path: (location && location.pathname) || '',
                        fields: fields,
                        created_at: Date.now(),
                        via: 'approvals-oversight'
                    };
                    list.push(entry);
                    localStorage.setItem(DEFERRED_KEY, JSON.stringify(list));
                    try {
                        if (window.RatebOfflineNavGuard && typeof window.RatebOfflineNavGuard.refreshBanner === 'function') {
                            window.RatebOfflineNavGuard.refreshBanner();
                        }
                    } catch (eBan) { /* ignore */ }
                    try {
                        var n = (window.RatebOfflineNavGuard && window.RatebOfflineNavGuard.deferredCount)
                            ? window.RatebOfflineNavGuard.deferredCount()
                            : list.length;
                        flashToast('تم حفظ الاعتماد أوفلاين في قائمة المزامنة (' + n + ').', 'success');
                    } catch (eN) {
                        flashToast('تم حفظ الاعتماد أوفلاين — يُزامَن عند الاتصال أو من «مزامنة الآن».', 'success');
                    }
                    if (action === 'approve' || action === 'reject') {
                        syncRowAfterAction(action, key, {});
                    }
                } catch (eQ) {
                    flashToast(labels.error || 'حدث خطأ غير متوقع', 'danger');
                } finally {
                    setRowBusy(key, false);
                }
            }

            var offlineNow = false;
            try {
                // Soft badge alone must not divert approve to local queue.
                offlineNow = typeof navigator !== 'undefined' && navigator.onLine === false;
            } catch (eOff) { /* ignore */ }
            if (offlineNow) {
                queueOfflineDecision();
                return;
            }

            fetch(url, {
                method: 'POST',
                body: form,
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf()
                },
                credentials: 'same-origin'
            })
                .then(parseJsonResponse)
                .then(function (data) {
                    if (data && data.queued) {
                        flashToast(data.message || 'تم حفظ الاعتماد أوفلاين للمزامنة.', 'success');
                        if (action === 'approve' || action === 'reject') {
                            syncRowAfterAction(action, key, data || {});
                        }
                        return;
                    }
                    if (data && data.offline && !data.queued) {
                        queueOfflineDecision();
                        return;
                    }
                    if (action === 'approve' || action === 'reject' || action === 'undo') {
                        syncRowAfterAction(action, key, data);
                    }
                    if (data.message) {
                        flashToast(data.message, 'success');
                    }
                })
                .catch(function (err) {
                    var msg = err && err.message ? String(err.message) : '';
                    if (/failed to fetch|networkerror|internet_disconnected|offline/i.test(msg)) {
                        queueOfflineDecision();
                        return;
                    }
                    flashToast(msg || (labels.error || 'حدث خطأ غير متوقع'), 'danger');
                    setRowBusy(key, false);
                })
                .finally(function () {
                    setRowBusy(key, false);
                });
        }

        if (confirmMsg) {
            confirmAction(confirmMsg, variantForAction(action)).then(function (ok) {
                if (ok) {
                    runPost();
                }
            });
            return;
        }

        runPost();
    }

    function selectedBulkItems() {
        var root = rootEl();
        var items = [];
        if (!root) {
            return items;
        }
        root.querySelectorAll('[data-rateb-row-check]:checked').forEach(function (cb) {
            var tr = cb.closest('tr.rateb-approval-data-row');
            if (!tr) {
                return;
            }
            items.push({
                source_key: tr.getAttribute('data-source-key') || '',
                record_id: tr.getAttribute('data-record-id') || '',
                company_id: tr.getAttribute('data-company-id') || '',
                row_key: tr.getAttribute('data-approval-row') || cb.value || ''
            });
        });
        return items;
    }

    function confirmBulkAction(action, count) {
        if (action === 'reject') {
            return confirmAction(labels.bulk_confirm_reject || labels.confirm_reject || 'Confirm reject?', 'danger');
        }
        var tpl = labels.bulk_confirm_approve_count || labels.confirm_approve || 'Approve :count selected?';
        return confirmAction(String(tpl).replace(':count', String(count)), 'primary');
    }

    function postBulkAction(action) {
        if (!config.canBulk || !config.bulkDecideUrl) {
            flashToast(labels.error || 'حدث خطأ غير متوقع', 'danger');
            return;
        }
        var items = selectedBulkItems();
        if (!items.length) {
            flashToast(labels.bulk_none_selected || labels.error || 'حدث خطأ غير متوقع', 'warning');
            return;
        }
        if (action === 'reject') {
            items = items.filter(function (item) {
                var row = dataRow(item.row_key);
                return row && row.getAttribute('data-can-reject') === '1';
            });
            if (!items.length) {
                flashToast(labels.bulk_none_selected || labels.error || 'حدث خطأ غير متوقع', 'warning');
                return;
            }
        }

        confirmBulkAction(action, items.length).then(function (ok) {
            if (!ok) {
                return;
            }
            var form = new FormData();
            form.append('_csrf', csrf());
            form.append('decision', action);
            form.append('items', JSON.stringify(items));
            if (config.typeFilter) {
                form.append('type_filter', config.typeFilter);
            }
            if (config.companyFilter) {
                form.append('company_id', String(config.companyFilter));
            }

            items.forEach(function (item) {
                setRowBusy(item.row_key, true);
            });

            fetch(config.bulkDecideUrl, {
                method: 'POST',
                body: form,
                cache: 'no-store',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf()
                },
                credentials: 'same-origin'
            })
                .then(parseJsonResponse)
                .then(function (data) {
                    (data.processed || []).forEach(function (key) {
                        removeProcessedRow(key);
                    });
                    if (data.summary) {
                        applySummaryCards(data.summary);
                    }
                    if (data.menu_counts) {
                        applyMenuCounts(data.menu_counts);
                    }
                    if (data.message) {
                        flashToast(data.message, data.failed && data.failed.length ? 'warning' : 'success');
                    }
                    var rootAfter = rootEl();
                    if (rootAfter) {
                        var bar = rootAfter.querySelector('[data-rateb-bulk-bar]');
                        if (bar) {
                            bar.classList.add('d-none');
                        }
                        rootAfter.querySelectorAll('[data-rateb-row-check]:checked').forEach(function (cb) {
                            cb.checked = false;
                        });
                    }
                })
                .catch(function (err) {
                    flashToast(err && err.message ? String(err.message) : (labels.error || 'حدث خطأ غير متوقع'), 'danger');
                })
                .finally(function () {
                    items.forEach(function (item) {
                        setRowBusy(item.row_key, false);
                    });
                });
        });
    }

    function flashToast(message, type) {
        var root = rootEl();
        if (!root) {
            try {
                window.alert(message);
            } catch (eA) { /* ignore */ }
            return;
        }
        var el = document.createElement('div');
        el.className = 'alert alert-' + (type || 'success') + ' rateb-approval-toast';
        el.textContent = message;
        root.prepend(el);
        window.setTimeout(function () {
            el.remove();
        }, 5200);
    }

    function onRootClick(e) {
        var root = rootEl();
        if (!root || !root.contains(e.target)) {
            return;
        }

        var bulkBtn = e.target.closest('[data-oversight-bulk]');
        if (bulkBtn && root.contains(bulkBtn)) {
            e.preventDefault();
            e.stopPropagation();
            postBulkAction(bulkBtn.getAttribute('data-oversight-bulk') || 'approve');
            return;
        }

        var navLink = e.target.closest('a.rateb-approval-btn-edit, a.rateb-approval-btn-link');
        if (navLink && root.contains(navLink)) {
            e.preventDefault();
            var href = navLink.getAttribute('href') || '';
            if (!href) {
                return;
            }
            var msg = navLink.classList.contains('rateb-approval-btn-edit')
                ? (labels.confirm_edit || 'Open edit page?')
                : (labels.confirm_open_ops || 'Open in operations?');
            confirmAction(msg, 'primary').then(function (ok) {
                if (!ok) {
                    return;
                }
                // Same-tab navigation — window.open fails / blanks while offline.
                try {
                    window.location.assign(href);
                } catch (eNav) {
                    window.location.href = href;
                }
            });
            return;
        }

        var btn = e.target.closest('[data-action]');
        if (!btn || !root.contains(btn)) {
            return;
        }
        var action = btn.getAttribute('data-action');
        if (action === 'close-detail') {
            e.preventDefault();
            closeAllDetails();
            return;
        }
        var key = rowKeyFromEl(btn);
        if (!key) {
            return;
        }
        if (action === 'view') {
            e.preventDefault();
            openDetail(key);
            return;
        }
        if (action === 'approve' || action === 'reject' || action === 'undo') {
            e.preventDefault();
            e.stopPropagation();
            postAction(action, key);
        }
    }

    /**
     * Soft-nav replaces #rateb-main-content — re-read config and keep one document listener.
     * Idle-only load + one-shot IIFE left approve/reject dead after sidebar navigation.
     */
    function boot() {
        var root = rootEl();
        if (!root) {
            activeRowKey = null;
            return;
        }
        refreshConfig();
        if (!clickBound) {
            document.addEventListener('click', onRootClick);
            clickBound = true;
        }
        if (window.RatebApp && typeof window.RatebApp.reinit === 'function') {
            window.RatebApp.reinit();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    document.addEventListener('rateb:nav:afterEnter', boot);
})();
