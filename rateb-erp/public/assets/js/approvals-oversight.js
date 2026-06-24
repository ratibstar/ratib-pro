(function () {
    'use strict';

    var root = document.getElementById('rateb-approvals-oversight');
    if (!root) {
        return;
    }

    var config = {};
    try {
        config = JSON.parse(root.getAttribute('data-config') || '{}');
    } catch (e) {
        config = {};
    }

    var labels = config.labels || {};
    var activeRowKey = null;

    function csrf() {
        return config.csrf || document.querySelector('meta[name="rateb-csrf"]')?.getAttribute('content') || '';
    }

    function rowKeyFromEl(el) {
        var row = el.closest('[data-approval-row]');
        return row ? row.getAttribute('data-approval-row') : null;
    }

    function dataRow(key) {
        return root.querySelector('[data-approval-row="' + key + '"]');
    }

    function detailRow(key) {
        return root.querySelector('[data-detail-for="' + key + '"]');
    }

    function closeAllDetails() {
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
            ops += '<a href="' + escapeHtml(detail.view_url) + '" class="rateb-approval-btn rateb-approval-btn-link" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i><span>' + escapeHtml(labels.open_in_ops || 'Open') + '</span></a>';
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

    function loadDetail(key) {
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
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.ok || !data.detail) {
                    throw new Error(data.message || 'Error');
                }
                body.innerHTML = renderDetailBody(data.detail);
                loading.classList.add('d-none');
                body.classList.remove('d-none');
            })
            .catch(function (err) {
                body.innerHTML = '<div class="alert alert-danger m-3">' + escapeHtml(err.message || 'Error') + '</div>';
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

    function postAction(action, key) {
        var row = dataRow(key);
        if (!row) {
            return;
        }
        var urlMap = {
            approve: config.approveUrl,
            reject: config.rejectUrl,
            undo: config.undoUrl
        };
        var url = urlMap[action];
        if (!url) {
            return;
        }
        if (action === 'reject' && !window.confirm(labels.confirm_reject || 'Confirm reject?')) {
            return;
        }
        if (action === 'undo' && !window.confirm(labels.confirm_undo || 'Confirm undo?')) {
            return;
        }

        var form = new FormData();
        form.append('_csrf', csrf());
        form.append('source_key', row.getAttribute('data-source-key') || '');
        form.append('record_id', row.getAttribute('data-record-id') || '');
        form.append('company_id', row.getAttribute('data-company-id') || '');
        if (config.typeFilter) {
            form.append('type_filter', config.typeFilter);
        }

        fetch(url, {
            method: 'POST',
            body: form,
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data.ok) {
                    throw new Error(data.message || 'Error');
                }
                if (action === 'approve' || action === 'reject') {
                    var detailTr = detailRow(key);
                    if (data.detail && data.detail.can_undo) {
                        if (!activeRowKey || activeRowKey !== key) {
                            openDetail(key);
                        }
                        var body = detailTr && detailTr.querySelector('.rateb-approval-detail-body');
                        if (body) {
                            body.innerHTML = renderDetailBody(data.detail);
                            body.classList.remove('d-none');
                            var loadingEl = detailTr.querySelector('.rateb-approval-detail-loading');
                            if (loadingEl) {
                                loadingEl.classList.add('d-none');
                            }
                        }
                        row.classList.add('rateb-approval-processed');
                    } else {
                        row.remove();
                        if (detailTr) {
                            detailTr.remove();
                        }
                        if (activeRowKey === key) {
                            activeRowKey = null;
                        }
                    }
                } else if (action === 'undo') {
                    if (data.detail && data.detail.can_approve) {
                        var pane = detailRow(key)?.querySelector('.rateb-approval-detail-body');
                        if (pane) {
                            pane.innerHTML = renderDetailBody(data.detail);
                        }
                        row.classList.remove('rateb-approval-processed');
                    } else {
                        row.remove();
                        var undoDetail = detailRow(key);
                        if (undoDetail) {
                            undoDetail.remove();
                        }
                        if (activeRowKey === key) {
                            activeRowKey = null;
                        }
                    }
                }
                if (data.message) {
                    flashToast(data.message);
                }
            })
            .catch(function (err) {
                window.alert(err.message || 'Error');
            });
    }

    function flashToast(message) {
        var el = document.createElement('div');
        el.className = 'alert alert-success rateb-approval-toast';
        el.textContent = message;
        root.prepend(el);
        window.setTimeout(function () {
            el.remove();
        }, 3200);
    }

    root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action]');
        if (!btn || !root.contains(btn)) {
            return;
        }
        var action = btn.getAttribute('data-action');
        var key = rowKeyFromEl(btn);
        if (!key && action !== 'close-detail') {
            return;
        }
        if (action === 'view') {
            e.preventDefault();
            openDetail(key);
            return;
        }
        if (action === 'close-detail') {
            e.preventDefault();
            closeAllDetails();
            return;
        }
        if (action === 'approve' || action === 'reject' || action === 'undo') {
            e.preventDefault();
            if (btn.closest('.rateb-approval-detail-body') || btn.closest('.rateb-approval-ops')) {
                postAction(action, key);
            }
        }
    });
})();
