(function () {
    'use strict';

    var root = document.getElementById('rateb-approvals-oversight');
    if (!root) {
        return;
    }

    var config = {};
    var configEl = document.getElementById('rateb-approvals-config-json');
    if (configEl) {
        try {
            config = JSON.parse(configEl.textContent || '{}');
        } catch (e) {
            config = {};
        }
    }

    var labels = config.labels || {};
    var activeRowKey = null;

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
        return root.querySelector('tr.rateb-approval-data-row[data-approval-row="' + key + '"]');
    }

    function detailRow(key) {
        return root.querySelector('tr[data-detail-for="' + key + '"]');
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

    function parseJsonResponse(res) {
        return res.json().then(function (data) {
            if (!res.ok || data.ok === false) {
                var err = new Error(data.message || labels.error || 'Error');
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
            html += '<a href="' + escapeHtml(editUrl) + '" class="rateb-approval-btn rateb-approval-btn-edit" target="_blank" rel="noopener" title="' + escapeHtml(labels.edit || 'Edit') + '">'
                + '<i class="fas fa-edit"></i><span>' + escapeHtml(labels.edit || 'Edit') + '</span></a>';
        }

        if (!detail || detail.can_approve) {
            html += '<button type="button" class="rateb-approval-btn rateb-approval-btn-approve" data-action="approve" title="' + escapeHtml(labels.approve || 'Approve') + '">'
                + '<i class="fas fa-check"></i><span>' + escapeHtml(labels.approve || 'Approve') + '</span></button>';
        }

        var canReject = detail ? detail.can_reject : row.getAttribute('data-can-reject') === '1';
        if (canReject) {
            html += '<button type="button" class="rateb-approval-btn rateb-approval-btn-reject" data-action="reject" title="' + escapeHtml(labels.reject || 'Reject') + '">'
                + '<i class="fas fa-times"></i><span>' + escapeHtml(labels.reject || 'Reject') + '</span></button>';
        }

        if (detail && detail.can_undo) {
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
            ops += '<a href="' + escapeHtml(detail.edit_url) + '" class="rateb-approval-btn rateb-approval-btn-edit" target="_blank" rel="noopener"><i class="fas fa-edit"></i><span>' + escapeHtml(labels.edit || 'Edit') + '</span></a>';
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
                    throw new Error(labels.error || 'Error');
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

    function syncRowAfterAction(key, data) {
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
            return;
        }
        loadDetail(key);
    }

    function postAction(action, key) {
        var row = dataRow(key);
        if (!row || !key) {
            flashToast(labels.error || 'Error', 'danger');
            return;
        }
        if (row.getAttribute('data-processed') === '1' && (action === 'approve' || action === 'reject')) {
            flashToast(labels.already_processed || labels.error || 'Error', 'warning');
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
            flashToast(labels.error || 'Error', 'danger');
            return;
        }

        var confirmMsg = null;
        if (action === 'reject') {
            confirmMsg = labels.confirm_reject || 'Confirm reject?';
        } else if (action === 'undo') {
            confirmMsg = labels.confirm_undo || 'Confirm undo?';
        }

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
                    if (action === 'approve' || action === 'reject' || action === 'undo') {
                        syncRowAfterAction(key, data);
                    }
                    if (data.message) {
                        flashToast(data.message, 'success');
                    }
                })
                .catch(function (err) {
                    flashToast(err && err.message ? err.message : (labels.error || 'Error'), 'danger');
                })
                .finally(function () {
                    setRowBusy(key, false);
                });
        }

        if (confirmMsg) {
            var confirmFn = window.ratebConfirm || window.confirm;
            var promise = confirmFn === window.confirm
                ? Promise.resolve(confirmFn(confirmMsg))
                : confirmFn(confirmMsg, { variant: action === 'undo' ? 'primary' : 'danger' });
            promise.then(function (ok) {
                if (ok) {
                    runPost();
                }
            });
            return;
        }

        runPost();
    }

    function flashToast(message, type) {
        var el = document.createElement('div');
        el.className = 'alert alert-' + (type || 'success') + ' rateb-approval-toast';
        el.textContent = message;
        root.prepend(el);
        window.setTimeout(function () {
            el.remove();
        }, 5200);
    }

    root.addEventListener('click', function (e) {
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
    });
})();
