(function () {
    'use strict';

    var cfg = document.getElementById('erpAgencyUpdatesConfig');
    if (!cfg) return;

    var apiUrl = cfg.getAttribute('data-api-url') || '';
    var linkUrl = cfg.getAttribute('data-link-url') || '';
    var syncUrl = cfg.getAttribute('data-sync-url') || '';
    var csrfToken = cfg.getAttribute('data-csrf') || '';
    var table = document.getElementById('erpAgencyUpdatesTable');
    var bulkBar = document.getElementById('erpAgencyBulkBar');
    var btnSelected = document.getElementById('erpUpdateRunSelected');
    var btnAll = document.getElementById('erpUpdateRunAllReady');
    var btnSub = document.getElementById('erpUpdateRunSubscribed');
    var btnSyncSelected = document.getElementById('erpSyncRunSelected');
    var btnSyncAll = document.getElementById('erpSyncRunAllReady');
    var syncConfirmInput = document.getElementById('erpSyncConfirmInput');
    var progress = document.getElementById('erpUpdateProgress');
    var resultsBox = document.getElementById('erpUpdateResults');
    var logEl = document.getElementById('erpUpdateLog');
    var includePlatform = document.getElementById('erpUpdateIncludePlatform');

    function boxes() {
        if (!table) {
            return [];
        }
        return Array.prototype.slice.call(table.querySelectorAll('[data-rateb-row-check]'));
    }

    function selectedIds() {
        return boxes().filter(function (cb) { return cb.checked; }).map(function (cb) {
            return parseInt(cb.value, 10);
        }).filter(function (n) { return n > 0; });
    }

    function syncBulkBar() {
        var ids = selectedIds();
        var count = ids.length;
        if (bulkBar) {
            if (count > 0) {
                bulkBar.classList.remove('d-none');
                bulkBar.classList.add('erp-agency-bulk-bar--active');
            } else {
                bulkBar.classList.add('d-none');
                bulkBar.classList.remove('erp-agency-bulk-bar--active');
            }
            var countEl = bulkBar.querySelector('[data-rateb-bulk-count]');
            if (countEl) {
                var label = countEl.getAttribute('data-label') || 'selected';
                countEl.textContent = count + ' ' + label;
            }
        }
        if (btnSelected) {
            btnSelected.disabled = count === 0;
            btnSelected.classList.toggle('erp-push-bulk-btn--lit', count > 0);
        }
        if (table) {
            table.querySelectorAll('tbody tr.erp-agency-row').forEach(function (tr) {
                var cb = tr.querySelector('[data-rateb-row-check]');
                tr.classList.toggle('table-active', !!(cb && cb.checked));
            });
        }
        var selectAll = table ? table.querySelector('[data-rateb-select-all]') : null;
        if (selectAll) {
            selectAll.indeterminate = count > 0 && count < boxes().length;
            selectAll.checked = boxes().length > 0 && count === boxes().length;
        }
    }

    function setBusy(busy) {
        [btnSelected, btnAll, btnSub, btnSyncSelected, btnSyncAll].forEach(function (b) {
            if (!b) return;
            if (b === btnSelected || b === btnSyncSelected) {
                b.disabled = busy || selectedIds().length === 0;
            } else {
                b.disabled = !!busy;
            }
        });
    }

    function syncConfirmValue() {
        return syncConfirmInput ? String(syncConfirmInput.value || '').trim().toUpperCase() : '';
    }

    function syncButtonsReady() {
        var hasSelection = selectedIds().length > 0;
        if (btnSyncSelected) {
            btnSyncSelected.disabled = !hasSelection || syncConfirmValue() !== 'SYNC';
        }
        if (btnSyncAll) {
            btnSyncAll.disabled = syncConfirmValue() !== 'SYNC';
        }
    }

    function showProgress(msg) {
        if (!progress) return;
        progress.textContent = msg;
        progress.classList.remove('d-none');
    }

    function formatResults(data) {
        var lines = [];
        lines.push('Total: ' + (data.total || 0) + ' | OK: ' + (data.success_count || 0) + ' | Failed: ' + (data.failed_count || 0));
        (data.results || []).forEach(function (r) {
            lines.push('');
            if (r.target === 'platform') {
                lines.push('=== ' + (r.label || 'Platform') + ' ===');
            } else if (r.target === 'files') {
                lines.push('=== FILES Agency #' + (r.agency_id || '?') + ' ' + (r.agency_name || '') + ' (' + (r.site_url || r.host || '') + ') ===');
                if (r.source) lines.push('Source: ' + r.source);
                if (r.target_path) lines.push('Target: ' + r.target_path);
            } else {
                lines.push('=== Agency #' + (r.agency_id || '?') + ' ' + (r.agency_name || '') + ' (' + (r.erp_db_name || '') + ') ===');
            }
            if (!r.ok) {
                lines.push('ERROR: ' + (r.error || 'failed'));
                return;
            }
            (r.log || []).forEach(function (ln) { lines.push(ln); });
        });
        return lines.join('\n');
    }

    function run(payload, confirmMsg, url, runningMsg) {
        if (confirmMsg && !window.confirm(confirmMsg)) return;
        setBusy(true);
        showProgress(runningMsg || cfg.getAttribute('data-running') || 'Running…');
        if (resultsBox) resultsBox.classList.add('d-none');

        fetch(url || apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        })
            .then(function (res) { return res.json().then(function (j) { return { ok: res.ok, body: j }; }); })
            .then(function (pack) {
                var data = pack.body || {};
                if (logEl) logEl.textContent = formatResults(data);
                if (resultsBox) resultsBox.classList.remove('d-none');
                var msg = data.success
                    ? (cfg.getAttribute('data-done-ok') || 'Done.')
                    : (cfg.getAttribute('data-done-errors') || 'Done with errors.');
                if (!pack.ok && data.message) {
                    msg = data.message;
                }
                showProgress(msg);
            })
            .catch(function (err) {
                var prefix = cfg.getAttribute('data-request-failed') || 'Request failed';
                showProgress(prefix + ': ' + (err && err.message ? err.message : 'unknown'));
            })
            .finally(function () {
                setBusy(false);
                syncBulkBar();
                syncButtonsReady();
            });
    }

    function runSync(payload, confirmMsg) {
        if (syncConfirmValue() !== 'SYNC') {
            showProgress(cfg.getAttribute('data-sync-confirm-required') || 'Type SYNC to confirm.');
            return;
        }
        payload.confirm = 'SYNC';
        run(payload, confirmMsg, syncUrl, cfg.getAttribute('data-sync-running'));
    }

    if (table) {
        table.addEventListener('change', function (e) {
            var t = e.target;
            if (!t || t.getAttribute('data-rateb-row-check') === null && t.getAttribute('data-rateb-select-all') === null) {
                return;
            }
            if (t.getAttribute('data-rateb-select-all') !== null) {
                var on = !!t.checked;
                boxes().forEach(function (cb) { cb.checked = on; });
            }
            syncBulkBar();
            syncButtonsReady();
        });

        table.addEventListener('click', function (e) {
            if (e.target.closest('a, button, .erp-link-row-btn')) {
                return;
            }
            var tr = e.target.closest('tr.erp-agency-row');
            if (!tr || e.target.closest('[data-rateb-row-check]')) {
                return;
            }
            var cb = tr.querySelector('[data-rateb-row-check]');
            if (cb) {
                cb.checked = !cb.checked;
                syncBulkBar();
                syncButtonsReady();
            }
        });
    }

    if (syncConfirmInput) {
        syncConfirmInput.addEventListener('input', syncButtonsReady);
    }

    if (btnSyncSelected) {
        btnSyncSelected.addEventListener('click', function () {
            var ids = selectedIds();
            if (!ids.length) return;
            runSync({ agency_ids: ids }, cfg.getAttribute('data-confirm-sync-selected'));
        });
    }
    if (btnSyncAll) {
        btnSyncAll.addEventListener('click', function () {
            runSync({ scope: 'all_ready' }, cfg.getAttribute('data-confirm-sync-all'));
        });
    }

    if (btnSelected) {
        btnSelected.addEventListener('click', function () {
            var ids = selectedIds();
            if (!ids.length && !(includePlatform && includePlatform.checked)) return;
            run({
                agency_ids: ids,
                include_platform: !!(includePlatform && includePlatform.checked)
            }, cfg.getAttribute('data-confirm-selected'));
        });
    }
    if (btnAll) {
        btnAll.addEventListener('click', function () {
            run({
                scope: 'all_ready',
                include_platform: !!(includePlatform && includePlatform.checked)
            }, cfg.getAttribute('data-confirm-all'));
        });
    }
    if (btnSub) {
        btnSub.addEventListener('click', function () {
            run({
                scope: 'all_subscribed',
                include_platform: !!(includePlatform && includePlatform.checked)
            }, cfg.getAttribute('data-confirm-subscribed'));
        });
    }

    function boot() {
        syncBulkBar();
        syncButtonsReady();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    function linkAgency(agencyId, companyId, btn) {
        if (!linkUrl || agencyId < 1 || companyId < 1) return;
        var confirmMsg = cfg.getAttribute('data-confirm-link') || 'Link agency to this company?';
        if (!window.confirm(confirmMsg)) return;
        if (btn) btn.disabled = true;
        fetch(linkUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({ agency_id: agencyId, company_id: companyId })
        })
            .then(function (res) { return res.json().then(function (j) { return { ok: res.ok, body: j }; }); })
            .then(function (pack) {
                if (pack.ok && pack.body && pack.body.success) {
                    window.location.reload();
                    return;
                }
                showProgress((pack.body && pack.body.message) ? pack.body.message : (cfg.getAttribute('data-request-failed') || 'Failed'));
            })
            .catch(function (err) {
                showProgress((cfg.getAttribute('data-request-failed') || 'Failed') + ': ' + (err && err.message ? err.message : 'unknown'));
            })
            .finally(function () {
                if (btn) btn.disabled = false;
            });
    }

    var linkTopBtn = document.getElementById('erpLinkCompanyBtn');
    if (linkTopBtn) {
        linkTopBtn.addEventListener('click', function () {
            linkAgency(
                parseInt(linkTopBtn.getAttribute('data-agency-id') || '0', 10),
                parseInt(linkTopBtn.getAttribute('data-company-id') || '0', 10),
                linkTopBtn
            );
        });
    }

    document.querySelectorAll('.erp-link-row-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            linkAgency(
                parseInt(btn.getAttribute('data-agency-id') || '0', 10),
                parseInt(btn.getAttribute('data-company-id') || '0', 10),
                btn
            );
        });
    });
})();
