(function () {
    'use strict';

    var cfg = document.getElementById('erpAgencyUpdatesConfig');
    if (!cfg) return;

    var apiUrl = cfg.getAttribute('data-api-url') || '';
    var linkUrl = cfg.getAttribute('data-link-url') || '';
    var syncUrl = cfg.getAttribute('data-sync-url') || '';
    var restoreAdminUrl = cfg.getAttribute('data-restore-admin-url') || '';
    var resetDataUrl = cfg.getAttribute('data-reset-data-url') || '';
    var csrfToken = cfg.getAttribute('data-csrf') || '';
    var table = document.getElementById('erpAgencyUpdatesTable');
    var filterCompany = document.getElementById('erpAgencyFilterCompany');
    var selectionBadge = document.getElementById('erpSelectionBadge');
    var btnSyncSelected = document.getElementById('erpSyncRunSelected');
    var btnPushSelected = document.getElementById('erpUpdateRunSelected');
    var btnFullSelected = document.getElementById('erpFullDeploySelected');
    var btnRestoreSelected = document.getElementById('erpRestoreAdminSelected');
    var btnResetSelected = document.getElementById('erpResetDataSelected');
    var btnResetAll = document.getElementById('erpResetDataAllReady');
    var btnSyncAll = document.getElementById('erpSyncRunAllReady');
    var btnPushAll = document.getElementById('erpUpdateRunAllReady');
    var btnFullAll = document.getElementById('erpFullDeployAllReady');
    var btnPushSub = document.getElementById('erpUpdateRunSubscribed');
    var syncConfirmInput = document.getElementById('erpSyncConfirmInput');
    var resetConfirmInput = document.getElementById('erpResetConfirmInput');
    var includePlatform = document.getElementById('erpUpdateIncludePlatform');
    var progress = document.getElementById('erpUpdateProgress');
    var resultsBox = document.getElementById('erpUpdateResults');
    var logEl = document.getElementById('erpUpdateLog');
    var bulkLabel = selectionBadge ? selectionBadge.textContent.replace(/^\d+\s*/, '') : '';

    function visibleRows() {
        if (!table) return [];
        return Array.prototype.slice.call(table.querySelectorAll('tbody tr.erp-agency-row')).filter(function (tr) {
            return tr.style.display !== 'none';
        });
    }

    function boxes() {
        return visibleRows().map(function (tr) {
            return tr.querySelector('[data-rateb-row-check]');
        }).filter(Boolean);
    }

    function selectedIds() {
        return boxes().filter(function (cb) { return cb.checked; }).map(function (cb) {
            return parseInt(cb.value, 10);
        }).filter(function (n) { return n > 0; });
    }

    function syncConfirmValue() {
        return syncConfirmInput ? String(syncConfirmInput.value || '').trim().toUpperCase() : '';
    }

    function resetConfirmValue() {
        return resetConfirmInput ? String(resetConfirmInput.value || '').trim().toUpperCase() : '';
    }

    function resetOk() {
        return resetConfirmValue() === 'RESET-DATA' || syncConfirmValue() === 'RESET-DATA';
    }

    function syncOk() {
        return syncConfirmValue() === 'SYNC';
    }

    function applyCompanyFilter() {
        if (!table || !filterCompany) return;
        var val = filterCompany.value;
        table.querySelectorAll('tbody tr.erp-agency-row').forEach(function (tr) {
            var coId = String(tr.getAttribute('data-erp-company-id') || '0');
            var show = true;
            if (val === '0') {
                show = coId === '0' || coId === '';
            } else if (val !== '') {
                show = coId === val;
            }
            tr.style.display = show ? '' : 'none';
        });
        syncUi();
    }

    function setRowChecks(predicate) {
        if (!table) return;
        table.querySelectorAll('tbody tr.erp-agency-row').forEach(function (tr) {
            if (tr.style.display === 'none') return;
            var cb = tr.querySelector('[data-rateb-row-check]');
            if (cb) cb.checked = !!predicate(tr);
        });
        syncUi();
    }

    function syncUi() {
        var ids = selectedIds();
        var count = ids.length;
        if (selectionBadge) {
            selectionBadge.textContent = count + ' ' + bulkLabel.trim();
        }
        visibleRows().forEach(function (tr) {
            var cb = tr.querySelector('[data-rateb-row-check]');
            tr.classList.toggle('table-active', !!(cb && cb.checked));
        });
        var selectAll = table ? table.querySelector('[data-rateb-select-all]') : null;
        var vis = boxes();
        if (selectAll) {
            selectAll.indeterminate = count > 0 && count < vis.length;
            selectAll.checked = vis.length > 0 && count === vis.length;
        }
        var hasSel = count > 0;
        var syncReady = syncOk();
        [btnSyncSelected, btnFullSelected].forEach(function (b) {
            if (b) b.disabled = !hasSel || !syncReady;
        });
        if (btnPushSelected) {
            btnPushSelected.disabled = !hasSel && !(includePlatform && includePlatform.checked);
        }
        if (btnRestoreSelected) {
            btnRestoreSelected.disabled = !hasSel;
        }
        if (btnResetSelected) {
            btnResetSelected.disabled = !hasSel;
        }
        [btnSyncAll, btnFullAll].forEach(function (b) {
            if (b) b.disabled = !syncReady;
        });
    }

    function setBusy(busy) {
        [
            btnSyncSelected, btnPushSelected, btnFullSelected, btnRestoreSelected, btnResetSelected,
            btnSyncAll, btnPushAll, btnFullAll, btnPushSub, btnResetAll
        ].forEach(function (b) {
            if (b) b.disabled = !!busy;
        });
        if (!busy) syncUi();
    }

    function showProgress(msg) {
        if (!progress) return;
        progress.textContent = msg;
        progress.classList.remove('d-none');
    }

    function appendLog(text) {
        if (!logEl) return;
        logEl.textContent = logEl.textContent ? logEl.textContent + '\n' + text : text;
    }

    function formatResults(data, reset) {
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
        var block = lines.join('\n');
        if (reset) {
            return block;
        }
        return block;
    }

    function postJson(url, payload) {
        if (!url) {
            return Promise.reject(new Error('API URL missing — refresh the page or redeploy rateb-erp.'));
        }
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.text().then(function (text) {
                var body = {};
                if (text) {
                    try {
                        body = JSON.parse(text);
                    } catch (parseErr) {
                        var snippet = text.replace(/\s+/g, ' ').trim().slice(0, 120);
                        throw new Error('HTTP ' + res.status + (snippet ? ': ' + snippet : ''));
                    }
                }
                return { ok: res.ok, status: res.status, body: body };
            });
        });
    }

    function runPush(payload, confirmMsg, resetLog, manageBusy) {
        if (confirmMsg && !window.confirm(confirmMsg)) {
            return Promise.resolve(null);
        }
        if (manageBusy !== false) setBusy(true);
        showProgress(cfg.getAttribute('data-running') || 'Running…');
        if (resetLog && resultsBox) {
            resultsBox.classList.remove('d-none');
            if (logEl) logEl.textContent = '';
        }
        return postJson(apiUrl, payload)
            .then(function (pack) {
                var data = pack.body || {};
                var block = formatResults(data, true);
                if (resetLog && logEl) {
                    logEl.textContent = block;
                } else if (logEl) {
                    appendLog('\n--- DB migrations ---\n' + block);
                }
                var msg = data.success
                    ? (cfg.getAttribute('data-done-ok') || 'Done.')
                    : (cfg.getAttribute('data-done-errors') || 'Done with errors.');
                if (!pack.ok && data.message) msg = data.message;
                showProgress(msg);
                return data;
            })
            .catch(function (err) {
                showProgress((cfg.getAttribute('data-request-failed') || 'Failed') + ': ' + (err && err.message ? err.message : 'unknown'));
                return null;
            })
            .finally(function () {
                if (manageBusy !== false) setBusy(false);
            });
    }

    function runSync(payload, confirmMsg, resetLog, manageBusy) {
        if (!syncOk()) {
            showProgress(cfg.getAttribute('data-sync-confirm-required') || 'Type SYNC first.');
            return Promise.resolve(null);
        }
        if (confirmMsg && !window.confirm(confirmMsg)) {
            return Promise.resolve(null);
        }
        payload.confirm = 'SYNC';
        if (manageBusy !== false) setBusy(true);
        showProgress(cfg.getAttribute('data-sync-running') || 'Syncing…');
        if (resetLog && resultsBox) {
            resultsBox.classList.remove('d-none');
            if (logEl) logEl.textContent = '';
        }
        return postJson(syncUrl, payload)
            .then(function (pack) {
                var data = pack.body || {};
                var block = formatResults(data, true);
                if (resetLog && logEl) {
                    logEl.textContent = block;
                } else if (logEl) {
                    appendLog('\n--- File sync ---\n' + block);
                }
                var msg = data.success
                    ? (cfg.getAttribute('data-done-ok') || 'Done.')
                    : (cfg.getAttribute('data-done-errors') || 'Done with errors.');
                if (!pack.ok && data.message) msg = data.message;
                showProgress(msg);
                return data;
            })
            .catch(function (err) {
                showProgress((cfg.getAttribute('data-request-failed') || 'Failed') + ': ' + (err && err.message ? err.message : 'unknown'));
                return null;
            })
            .finally(function () {
                if (manageBusy !== false) setBusy(false);
            });
    }

    function pushPayloadFromSelection() {
        var ids = selectedIds();
        return {
            agency_ids: ids,
            include_platform: !!(includePlatform && includePlatform.checked)
        };
    }

    function runFullDeploy(scope, confirmMsg) {
        if (!syncOk()) {
            showProgress(cfg.getAttribute('data-sync-confirm-required') || 'Type SYNC first.');
            return;
        }
        var syncPayload = scope ? { scope: scope, confirm: 'SYNC' } : { agency_ids: selectedIds(), confirm: 'SYNC' };
        var pushPayload = scope
            ? { scope: scope, include_platform: !!(includePlatform && includePlatform.checked) }
            : pushPayloadFromSelection();
        if (!scope && selectedIds().length === 0 && !pushPayload.include_platform) {
            showProgress(cfg.getAttribute('data-select-first') || 'Select agencies first.');
            return;
        }
        if (confirmMsg && !window.confirm(confirmMsg)) return;

        setBusy(true);
        showProgress(cfg.getAttribute('data-full-running') || 'Full deploy…');
        if (resultsBox) resultsBox.classList.remove('d-none');
        if (logEl) logEl.textContent = '';

        runSync(syncPayload, null, true, false)
            .then(function (syncData) {
                if (!syncData || !syncData.success) {
                    return null;
                }
                return runPush(pushPayload, null, false, false);
            })
            .finally(function () {
                setBusy(false);
            });
    }

    if (table) {
        table.addEventListener('change', function (e) {
            var t = e.target;
            if (!t) return;
            if (t.getAttribute('data-rateb-select-all') !== null) {
                var on = !!t.checked;
                boxes().forEach(function (cb) { cb.checked = on; });
            } else if (t.getAttribute('data-rateb-row-check') === null) {
                return;
            }
            syncUi();
        });

        table.addEventListener('click', function (e) {
            if (e.target.closest('a, button, .dropdown-menu')) {
                return;
            }
            var tr = e.target.closest('tr.erp-agency-row');
            if (!tr || e.target.closest('[data-rateb-row-check]')) return;
            var cb = tr.querySelector('[data-rateb-row-check]');
            if (cb) {
                cb.checked = !cb.checked;
                syncUi();
            }
        });
    }

    if (filterCompany) {
        filterCompany.addEventListener('change', applyCompanyFilter);
    }
    if (syncConfirmInput) {
        syncConfirmInput.addEventListener('input', syncUi);
        syncConfirmInput.addEventListener('change', syncUi);
    }
    if (resetConfirmInput) {
        resetConfirmInput.addEventListener('input', syncUi);
        resetConfirmInput.addEventListener('change', syncUi);
    }
    if (includePlatform) {
        includePlatform.addEventListener('change', syncUi);
    }

    var btnSelectAll = document.getElementById('erpSelectAllRows');
    var btnSelectReady = document.getElementById('erpSelectReadyRows');
    var btnSelectSub = document.getElementById('erpSelectSubscribedRows');
    var btnSelectNone = document.getElementById('erpSelectNoneRows');

    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function () {
            setRowChecks(function () { return true; });
        });
    }
    if (btnSelectReady) {
        btnSelectReady.addEventListener('click', function () {
            setRowChecks(function (tr) { return tr.getAttribute('data-ready') === '1'; });
        });
    }
    if (btnSelectSub) {
        btnSelectSub.addEventListener('click', function () {
            setRowChecks(function (tr) { return tr.getAttribute('data-subscribed') === '1'; });
        });
    }
    if (btnSelectNone) {
        btnSelectNone.addEventListener('click', function () {
            setRowChecks(function () { return false; });
        });
    }

    if (btnSyncSelected) {
        btnSyncSelected.addEventListener('click', function () {
            var ids = selectedIds();
            if (!ids.length) {
                showProgress(cfg.getAttribute('data-select-first') || 'Select agencies first.');
                return;
            }
            runSync({ agency_ids: ids }, cfg.getAttribute('data-confirm-sync-selected'), true);
        });
    }
    if (btnPushSelected) {
        btnPushSelected.addEventListener('click', function () {
            var payload = pushPayloadFromSelection();
            if (!payload.agency_ids.length && !payload.include_platform) {
                showProgress(cfg.getAttribute('data-select-first') || 'Select agencies first.');
                return;
            }
            runPush(payload, cfg.getAttribute('data-confirm-selected'), true);
        });
    }
    if (btnFullSelected) {
        btnFullSelected.addEventListener('click', function () {
            runFullDeploy(null, cfg.getAttribute('data-confirm-full-selected'));
        });
    }
    if (btnRestoreSelected) {
        btnRestoreSelected.addEventListener('click', function () {
            var ids = selectedIds();
            if (!ids.length) {
                showProgress(cfg.getAttribute('data-select-first') || 'Select agencies first.');
                return;
            }
            if (!window.confirm(cfg.getAttribute('data-confirm-restore-selected') || 'Restore admin@rateb.sa?')) {
                return;
            }
            setBusy(true);
            showProgress(cfg.getAttribute('data-restore-running') || 'Restoring…');
            if (resultsBox) {
                resultsBox.classList.remove('d-none');
                if (logEl) logEl.textContent = '';
            }
            postJson(restoreAdminUrl, { agency_ids: ids })
                .then(function (pack) {
                    var data = pack.body || {};
                    var lines = ['Restore admin@rateb.sa / password'];
                    lines.push('Total: ' + (data.total || 0) + ' | Failed: ' + (data.failed_count || 0));
                    (data.results || []).forEach(function (r) {
                        lines.push('');
                        lines.push('=== Agency #' + (r.agency_id || '?') + ' ===');
                        if (!r.ok) {
                            lines.push('ERROR: ' + (r.error || 'failed'));
                            return;
                        }
                        var rep = r.report || {};
                        (rep.actions || []).forEach(function (a) { lines.push(a); });
                        lines.push('restored_users: ' + (rep.restored_users || 0));
                        lines.push('password_hashes_reset: ' + (rep.password_hashes_reset || 0));
                    });
                    if (logEl) logEl.textContent = lines.join('\n');
                    showProgress(data.success
                        ? (cfg.getAttribute('data-done-ok') || 'Done.')
                        : (cfg.getAttribute('data-done-errors') || 'Done with errors.'));
                })
                .catch(function (err) {
                    showProgress((cfg.getAttribute('data-request-failed') || 'Failed') + ': ' + (err && err.message ? err.message : 'unknown'));
                })
                .then(function () { setBusy(false); });
        });
    }

    function formatResetResults(data) {
        var lines = [];
        lines.push('Total: ' + (data.total || 0) + ' | OK: ' + (data.success_count || 0) + ' | Failed: ' + (data.failed_count || 0));
        (data.results || []).forEach(function (r) {
            lines.push('');
            lines.push('=== Agency #' + (r.agency_id || '?') + ' ' + (r.agency_name || '') + ' (' + (r.erp_db_name || '') + ') ===');
            if (!r.ok) {
                lines.push('ERROR: ' + (r.error || 'failed'));
                return;
            }
            var rep = r.report || {};
            var tables = rep.tables || {};
            var tableCount = Object.keys(tables).length;
            lines.push('tables_truncated: ' + tableCount);
            if (rep.errors && rep.errors.length) {
                rep.errors.forEach(function (err) { lines.push('WARN: ' + err); });
            }
            var seed = rep.seed || {};
            if (seed.company_id) {
                lines.push('seeded company_id: ' + seed.company_id + ' | login: ' + (seed.admin_username || 'admin') + ' / ' + (seed.admin_password || '123456'));
            }
        });
        return lines.join('\n');
    }

    function runReset(payload, confirmMsg) {
        if (!resetOk()) {
            showProgress(cfg.getAttribute('data-reset-confirm-required') || 'Type RESET-DATA first.');
            return Promise.resolve(null);
        }
        if (confirmMsg && !window.confirm(confirmMsg)) {
            return Promise.resolve(null);
        }
        payload.confirm = 'RESET-DATA';
        setBusy(true);
        showProgress(cfg.getAttribute('data-reset-running') || 'Resetting…');
        if (resultsBox) {
            resultsBox.classList.remove('d-none');
            if (logEl) logEl.textContent = '';
        }
        return postJson(resetDataUrl, payload)
            .then(function (pack) {
                var data = pack.body || {};
                if (logEl) {
                    logEl.textContent = formatResetResults(data);
                }
                var msg = data.success
                    ? (cfg.getAttribute('data-done-ok') || 'Done.')
                    : (cfg.getAttribute('data-done-errors') || 'Done with errors.');
                if (!pack.ok && data.message) msg = data.message;
                showProgress(msg);
                return data;
            })
            .catch(function (err) {
                showProgress((cfg.getAttribute('data-request-failed') || 'Failed') + ': ' + (err && err.message ? err.message : 'unknown'));
                return null;
            })
            .finally(function () {
                setBusy(false);
            });
    }

    function triggerResetSelected() {
        var ids = selectedIds();
        if (!ids.length) {
            showProgress(cfg.getAttribute('data-select-first') || 'Select agencies first.');
            return;
        }
        runReset({ agency_ids: ids }, cfg.getAttribute('data-confirm-reset-selected'));
    }

    function triggerResetAllReady() {
        runReset({ scope: 'all_ready' }, cfg.getAttribute('data-confirm-reset-all'));
    }

    if (btnResetSelected) {
        btnResetSelected.addEventListener('click', triggerResetSelected);
    }
    if (btnResetAll) {
        btnResetAll.addEventListener('click', triggerResetAllReady);
    }
    window.__erpAgencyResetSelected = triggerResetSelected;
    window.__erpAgencyResetAllReady = triggerResetAllReady;
    if (btnSyncAll) {
        btnSyncAll.addEventListener('click', function () {
            runSync({ scope: 'all_ready' }, cfg.getAttribute('data-confirm-sync-all'), true);
        });
    }
    if (btnPushAll) {
        btnPushAll.addEventListener('click', function () {
            runPush({
                scope: 'all_ready',
                include_platform: !!(includePlatform && includePlatform.checked)
            }, cfg.getAttribute('data-confirm-all'), true);
        });
    }
    if (btnFullAll) {
        btnFullAll.addEventListener('click', function () {
            runFullDeploy('all_ready', cfg.getAttribute('data-confirm-full-all'));
        });
    }
    if (btnPushSub) {
        btnPushSub.addEventListener('click', function () {
            runPush({
                scope: 'all_subscribed',
                include_platform: !!(includePlatform && includePlatform.checked)
            }, cfg.getAttribute('data-confirm-subscribed'), true);
        });
    }

    function linkAgency(agencyId, companyId) {
        if (!linkUrl || agencyId < 1 || companyId < 1) return;
        if (!window.confirm(cfg.getAttribute('data-confirm-link') || 'Link?')) return;
        setBusy(true);
        postJson(linkUrl, { agency_id: agencyId, company_id: companyId })
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
                setBusy(false);
            });
    }

    document.querySelectorAll('.erp-link-pick-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            linkAgency(
                parseInt(btn.getAttribute('data-agency-id') || '0', 10),
                parseInt(btn.getAttribute('data-company-id') || '0', 10)
            );
        });
    });

    function boot() {
        applyCompanyFilter();
        syncUi();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
