/**
 * Agency ERP push/sync control — soft-nav safe (re-boot on rateb:nav:afterEnter).
 */
(function (root) {
    'use strict';

    var state = {};

    function visibleRows() {
        if (!state.table) return [];
        return Array.prototype.slice.call(state.table.querySelectorAll('tbody tr.erp-agency-row')).filter(function (tr) {
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
        return state.syncConfirmInput ? String(state.syncConfirmInput.value || '').trim().toUpperCase() : '';
    }

    function resetConfirmValue() {
        return state.resetConfirmInput ? String(state.resetConfirmInput.value || '').trim().toUpperCase() : '';
    }

    function resetOk() {
        return resetConfirmValue() === 'RESET-DATA' || syncConfirmValue() === 'RESET-DATA';
    }

    function syncOk() {
        return syncConfirmValue() === 'SYNC';
    }

    function applyCompanyFilter() {
        if (!state.table || !state.filterCompany) return;
        var val = state.filterCompany.value;
        var total = 0;
        var visible = 0;
        state.table.querySelectorAll('tbody tr.erp-agency-row').forEach(function (tr) {
            total++;
            var coId = String(tr.getAttribute('data-erp-company-id') || '0');
            var show = true;
            if (val === '0') {
                show = coId === '0' || coId === '';
            } else if (val !== '') {
                show = coId === val;
            }
            tr.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        var emptyEl = document.getElementById('erpAgencyFilterEmpty');
        if (emptyEl) {
            emptyEl.classList.toggle('d-none', visible > 0 || total === 0);
        }
        syncUi();
    }

    function setRowChecks(predicate) {
        if (!state.table) return;
        state.table.querySelectorAll('tbody tr.erp-agency-row').forEach(function (tr) {
            if (tr.style.display === 'none') return;
            var cb = tr.querySelector('[data-rateb-row-check]');
            if (cb) cb.checked = !!predicate(tr);
        });
        syncUi();
    }

    function syncUi() {
        var ids = selectedIds();
        var count = ids.length;
        if (state.selectionBadge) {
            state.selectionBadge.textContent = count + ' ' + (state.bulkLabel || '').trim();
        }
        visibleRows().forEach(function (tr) {
            var cb = tr.querySelector('[data-rateb-row-check]');
            tr.classList.toggle('table-active', !!(cb && cb.checked));
        });
        var selectAll = state.table ? state.table.querySelector('[data-rateb-select-all]') : null;
        var vis = boxes();
        if (selectAll) {
            selectAll.indeterminate = count > 0 && count < vis.length;
            selectAll.checked = vis.length > 0 && count === vis.length;
        }
        var hasSel = count > 0;
        var syncReady = syncOk();
        [state.btnSyncSelected, state.btnFullSelected].forEach(function (b) {
            if (b) b.disabled = !hasSel || !syncReady;
        });
        if (state.btnPushSelected) {
            state.btnPushSelected.disabled = !hasSel && !(state.includePlatform && state.includePlatform.checked);
        }
        if (state.btnRestoreSelected) {
            state.btnRestoreSelected.disabled = !hasSel;
        }
        if (state.btnResetSelected) {
            state.btnResetSelected.disabled = !hasSel;
        }
        [state.btnSyncAll, state.btnFullAll].forEach(function (b) {
            if (b) b.disabled = !syncReady;
        });
    }

    function setBusy(busy) {
        [
            state.btnSyncSelected, state.btnPushSelected, state.btnFullSelected, state.btnRestoreSelected, state.btnResetSelected,
            state.btnSyncAll, state.btnPushAll, state.btnFullAll, state.btnPushSub, state.btnResetAll
        ].forEach(function (b) {
            if (b) b.disabled = !!busy;
        });
        if (!busy) syncUi();
    }

    function showProgress(msg) {
        if (!state.progress) return;
        state.progress.textContent = msg;
        state.progress.classList.remove('d-none');
    }

    function appendLog(text) {
        if (!state.logEl) return;
        state.logEl.textContent = state.logEl.textContent ? state.logEl.textContent + '\n' + text : text;
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
        return lines.join('\n');
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
                'X-CSRF-Token': state.csrfToken
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
        if (confirmMsg && !root.confirm(confirmMsg)) {
            return Promise.resolve(null);
        }
        if (manageBusy !== false) setBusy(true);
        showProgress(state.cfg.getAttribute('data-running') || 'Running…');
        if (resetLog && state.resultsBox) {
            state.resultsBox.classList.remove('d-none');
            if (state.logEl) state.logEl.textContent = '';
        }
        return postJson(state.apiUrl, payload)
            .then(function (pack) {
                var data = pack.body || {};
                var block = formatResults(data, true);
                if (resetLog && state.logEl) {
                    state.logEl.textContent = block;
                } else if (state.logEl) {
                    appendLog('\n--- DB migrations ---\n' + block);
                }
                var msg = data.success
                    ? (state.cfg.getAttribute('data-done-ok') || 'Done.')
                    : (state.cfg.getAttribute('data-done-errors') || 'Done with errors.');
                if (!pack.ok && data.message) msg = data.message;
                showProgress(msg);
                return data;
            })
            .catch(function (err) {
                showProgress((state.cfg.getAttribute('data-request-failed') || 'Failed') + ': ' + (err && err.message ? err.message : 'unknown'));
                return null;
            })
            .finally(function () {
                if (manageBusy !== false) setBusy(false);
            });
    }

    function runSync(payload, confirmMsg, resetLog, manageBusy) {
        if (!syncOk()) {
            showProgress(state.cfg.getAttribute('data-sync-confirm-required') || 'Type SYNC first.');
            return Promise.resolve(null);
        }
        if (confirmMsg && !root.confirm(confirmMsg)) {
            return Promise.resolve(null);
        }
        payload.confirm = 'SYNC';
        if (manageBusy !== false) setBusy(true);
        showProgress(state.cfg.getAttribute('data-sync-running') || 'Syncing…');
        if (resetLog && state.resultsBox) {
            state.resultsBox.classList.remove('d-none');
            if (state.logEl) state.logEl.textContent = '';
        }
        return postJson(state.syncUrl, payload)
            .then(function (pack) {
                var data = pack.body || {};
                var block = formatResults(data, true);
                if (resetLog && state.logEl) {
                    state.logEl.textContent = block;
                } else if (state.logEl) {
                    appendLog('\n--- File sync ---\n' + block);
                }
                var msg = data.success
                    ? (state.cfg.getAttribute('data-done-ok') || 'Done.')
                    : (state.cfg.getAttribute('data-done-errors') || 'Done with errors.');
                if (!pack.ok && data.message) msg = data.message;
                showProgress(msg);
                return data;
            })
            .catch(function (err) {
                showProgress((state.cfg.getAttribute('data-request-failed') || 'Failed') + ': ' + (err && err.message ? err.message : 'unknown'));
                return null;
            })
            .finally(function () {
                if (manageBusy !== false) setBusy(false);
            });
    }

    function pushPayloadFromSelection() {
        return {
            agency_ids: selectedIds(),
            include_platform: !!(state.includePlatform && state.includePlatform.checked)
        };
    }

    function runFullDeploy(scope, confirmMsg) {
        if (!syncOk()) {
            showProgress(state.cfg.getAttribute('data-sync-confirm-required') || 'Type SYNC first.');
            return;
        }
        var syncPayload = scope ? { scope: scope, confirm: 'SYNC' } : { agency_ids: selectedIds(), confirm: 'SYNC' };
        var pushPayload = scope
            ? { scope: scope, include_platform: !!(state.includePlatform && state.includePlatform.checked) }
            : pushPayloadFromSelection();
        if (!scope && selectedIds().length === 0 && !pushPayload.include_platform) {
            showProgress(state.cfg.getAttribute('data-select-first') || 'Select agencies first.');
            return;
        }
        if (confirmMsg && !root.confirm(confirmMsg)) return;

        setBusy(true);
        showProgress(state.cfg.getAttribute('data-full-running') || 'Full deploy…');
        if (state.resultsBox) state.resultsBox.classList.remove('d-none');
        if (state.logEl) state.logEl.textContent = '';

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
            var shell = rep.shell || rep.seed || {};
            if (shell.company_id) {
                lines.push('company_id: ' + shell.company_id);
            }
            if (shell.users_preserved) {
                lines.push('users_preserved: ' + shell.users_preserved + ' (passwords unchanged)');
            } else if (shell.credentials_unchanged) {
                lines.push('credentials: unchanged');
            }
            var platformWipes = rep.platform_wipes || [];
            if (platformWipes.length) {
                platformWipes.forEach(function (pw) {
                    lines.push('platform_db wiped: ' + (pw.database || rep.platform_db || '') + ' company_id=' + (pw.company_id || '?'));
                });
            } else if (rep.platform_pr_before !== undefined) {
                lines.push('platform_pr: ' + rep.platform_pr_before + ' -> ' + (rep.platform_pr_after || 0));
            }
            if (rep.platform_company_linked) {
                lines.push('platform_company_linked: #' + rep.platform_company_linked);
            }
            var platformWipe = rep.platform_wipe || {};
            if (platformWipe && platformWipe.ok) {
                lines.push('platform_db wiped: ' + (rep.platform_db || platformWipe.database || '') + ' company_id=' + (platformWipe.company_id || '?'));
            } else if (platformWipe && platformWipe.error) {
                lines.push('platform_wipe ERROR: ' + platformWipe.error);
            }
            if (rep.platform_errors && rep.platform_errors.length) {
                rep.platform_errors.forEach(function (err) { lines.push('platform ERROR: ' + err); });
            }
            if (rep.platform_warnings && rep.platform_warnings.length) {
                rep.platform_warnings.forEach(function (err) { lines.push('platform WARN: ' + err); });
            }
            if (rep.post_reset_counts) {
                lines.push('post_reset_counts: ' + JSON.stringify(rep.post_reset_counts));
            }
            var orphan = (shell && shell.orphan_cleanup) ? shell.orphan_cleanup : null;
            if (orphan && orphan.orphan_rows_deleted && Object.keys(orphan.orphan_rows_deleted).length) {
                lines.push('orphan_rows_deleted: ' + JSON.stringify(orphan.orphan_rows_deleted));
            }
            lines.push('');
            var verifySite = rep.site_url || '';
            if (!verifySite && rep.site_host) {
                verifySite = 'https://' + rep.site_host;
            }
            if (verifySite) {
                verifySite = verifySite.replace(/\/$/, '') + '/rateb-erp/public/admin';
                lines.push((state.cfg.getAttribute('data-reset-verify-site') || 'Verify on agency site: %s').replace('%s', verifySite));
            }
            lines.push(state.cfg.getAttribute('data-reset-logout-hint') || 'Log out and log in again on the agency site, then verify lists are empty.');
            lines.push(state.cfg.getAttribute('data-reset-shell-note') || '');
        });
        return lines.join('\n');
    }

    function runReset(payload, confirmMsg) {
        if (!resetOk()) {
            showProgress(state.cfg.getAttribute('data-reset-confirm-required') || 'Type RESET-DATA first.');
            return Promise.resolve(null);
        }
        if (confirmMsg && !root.confirm(confirmMsg)) {
            return Promise.resolve(null);
        }
        payload.confirm = 'RESET-DATA';
        setBusy(true);
        showProgress(state.cfg.getAttribute('data-reset-running') || 'Resetting…');
        if (state.resultsBox) {
            state.resultsBox.classList.remove('d-none');
            if (state.logEl) state.logEl.textContent = '';
        }
        return postJson(state.resetDataUrl, payload)
            .then(function (pack) {
                var data = pack.body || {};
                if (state.logEl) {
                    state.logEl.textContent = formatResetResults(data);
                }
                var msg = data.success
                    ? (state.cfg.getAttribute('data-done-ok') || 'Done.')
                    : (state.cfg.getAttribute('data-done-errors') || 'Done with errors.');
                if (!pack.ok && data.message) msg = data.message;
                showProgress(msg);
                return data;
            })
            .catch(function (err) {
                showProgress((state.cfg.getAttribute('data-request-failed') || 'Failed') + ': ' + (err && err.message ? err.message : 'unknown'));
                return null;
            })
            .finally(function () {
                setBusy(false);
            });
    }

    function resetPlatformCompanyId() {
        var sel = document.getElementById('erpAgencyFilterCompany');
        if (!sel) {
            return 0;
        }
        var value = parseInt(String(sel.value || ''), 10);
        return value > 0 ? value : 0;
    }

    function triggerResetSelected() {
        var ids = selectedIds();
        if (!ids.length) {
            showProgress(state.cfg.getAttribute('data-select-first') || 'Select agencies first.');
            return;
        }
        var payload = { agency_ids: ids };
        var platformCompanyId = resetPlatformCompanyId();
        if (platformCompanyId > 0 && ids.length === 1) {
            var row = document.querySelector('.erp-agency-row[data-agency-id="' + ids[0] + '"]');
            var linked = row ? parseInt(row.getAttribute('data-erp-company-id') || '0', 10) : 0;
            if (linked < 1) {
                payload.platform_company_id = platformCompanyId;
            }
        }
        runReset(payload, state.cfg.getAttribute('data-confirm-reset-selected'));
    }

    function triggerResetRow(agencyId, agencyName) {
        if (agencyId < 1) {
            return;
        }
        var msg = (state.cfg.getAttribute('data-confirm-reset-row') || 'Reset ERP data for %s?')
            .replace('%s', agencyName || ('#' + agencyId));
        var payload = { agency_ids: [agencyId] };
        var row = document.querySelector('.erp-agency-row[data-agency-id="' + agencyId + '"]');
        var linked = row ? parseInt(row.getAttribute('data-erp-company-id') || '0', 10) : 0;
        if (linked < 1) {
            var platformCompanyId = resetPlatformCompanyId();
            if (platformCompanyId > 0) {
                payload.platform_company_id = platformCompanyId;
            }
        }
        runReset(payload, msg);
    }

    function triggerResetAllReady() {
        var payload = { scope: 'all_ready' };
        var platformCompanyId = resetPlatformCompanyId();
        if (platformCompanyId > 0) {
            payload.platform_company_id = platformCompanyId;
        }
        runReset(payload, state.cfg.getAttribute('data-confirm-reset-all'));
    }

    function linkAgency(agencyId, companyId) {
        if (!state.linkUrl || agencyId < 1 || companyId < 1) return;
        if (!root.confirm(state.cfg.getAttribute('data-confirm-link') || 'Link?')) return;
        setBusy(true);
        postJson(state.linkUrl, { agency_id: agencyId, company_id: companyId })
            .then(function (pack) {
                if (pack.ok && pack.body && pack.body.success) {
                    root.location.reload();
                    return;
                }
                showProgress((pack.body && pack.body.message) ? pack.body.message : (state.cfg.getAttribute('data-request-failed') || 'Failed'));
            })
            .catch(function (err) {
                showProgress((state.cfg.getAttribute('data-request-failed') || 'Failed') + ': ' + (err && err.message ? err.message : 'unknown'));
            })
            .finally(function () {
                setBusy(false);
            });
    }

    function bindControls() {
        if (state.table) {
            state.table.addEventListener('change', onTableChange);
            state.table.addEventListener('click', onTableClick);
        }
        if (state.filterCompany) {
            state.filterCompany.addEventListener('change', applyCompanyFilter);
        }
        if (state.syncConfirmInput) {
            state.syncConfirmInput.addEventListener('input', syncUi);
            state.syncConfirmInput.addEventListener('change', syncUi);
        }
        if (state.resetConfirmInput) {
            state.resetConfirmInput.addEventListener('input', syncUi);
            state.resetConfirmInput.addEventListener('change', syncUi);
        }
        if (state.includePlatform) {
            state.includePlatform.addEventListener('change', syncUi);
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

        if (state.btnSyncSelected) {
            state.btnSyncSelected.addEventListener('click', function () {
                var ids = selectedIds();
                if (!ids.length) {
                    showProgress(state.cfg.getAttribute('data-select-first') || 'Select agencies first.');
                    return;
                }
                runSync({ agency_ids: ids }, state.cfg.getAttribute('data-confirm-sync-selected'), true);
            });
        }
        if (state.btnPushSelected) {
            state.btnPushSelected.addEventListener('click', function () {
                var payload = pushPayloadFromSelection();
                if (!payload.agency_ids.length && !payload.include_platform) {
                    showProgress(state.cfg.getAttribute('data-select-first') || 'Select agencies first.');
                    return;
                }
                runPush(payload, state.cfg.getAttribute('data-confirm-selected'), true);
            });
        }
        if (state.btnFullSelected) {
            state.btnFullSelected.addEventListener('click', function () {
                runFullDeploy(null, state.cfg.getAttribute('data-confirm-full-selected'));
            });
        }
        if (state.btnRestoreSelected) {
            state.btnRestoreSelected.addEventListener('click', function () {
                var ids = selectedIds();
                if (!ids.length) {
                    showProgress(state.cfg.getAttribute('data-select-first') || 'Select agencies first.');
                    return;
                }
                if (!root.confirm(state.cfg.getAttribute('data-confirm-restore-selected') || 'Restore admin@rateb.sa?')) {
                    return;
                }
                setBusy(true);
                showProgress(state.cfg.getAttribute('data-restore-running') || 'Restoring…');
                if (state.resultsBox) {
                    state.resultsBox.classList.remove('d-none');
                    if (state.logEl) state.logEl.textContent = '';
                }
                postJson(state.restoreAdminUrl, { agency_ids: ids })
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
                        if (state.logEl) state.logEl.textContent = lines.join('\n');
                        showProgress(data.success
                            ? (state.cfg.getAttribute('data-done-ok') || 'Done.')
                            : (state.cfg.getAttribute('data-done-errors') || 'Done with errors.'));
                    })
                    .catch(function (err) {
                        showProgress((state.cfg.getAttribute('data-request-failed') || 'Failed') + ': ' + (err && err.message ? err.message : 'unknown'));
                    })
                    .then(function () { setBusy(false); });
            });
        }
        if (state.btnResetSelected) {
            state.btnResetSelected.addEventListener('click', triggerResetSelected);
        }
        if (state.btnResetAll) {
            state.btnResetAll.addEventListener('click', triggerResetAllReady);
        }
        document.querySelectorAll('.erp-reset-row-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                triggerResetRow(
                    parseInt(btn.getAttribute('data-agency-id') || '0', 10),
                    btn.getAttribute('data-agency-name') || ''
                );
            });
        });
        if (state.btnSyncAll) {
            state.btnSyncAll.addEventListener('click', function () {
                runSync({ scope: 'all_ready' }, state.cfg.getAttribute('data-confirm-sync-all'), true);
            });
        }
        if (state.btnPushAll) {
            state.btnPushAll.addEventListener('click', function () {
                runPush({
                    scope: 'all_ready',
                    include_platform: !!(state.includePlatform && state.includePlatform.checked)
                }, state.cfg.getAttribute('data-confirm-all'), true);
            });
        }
        if (state.btnFullAll) {
            state.btnFullAll.addEventListener('click', function () {
                runFullDeploy('all_ready', state.cfg.getAttribute('data-confirm-full-all'));
            });
        }
        if (state.btnPushSub) {
            state.btnPushSub.addEventListener('click', function () {
                runPush({
                    scope: 'all_subscribed',
                    include_platform: !!(state.includePlatform && state.includePlatform.checked)
                }, state.cfg.getAttribute('data-confirm-subscribed'), true);
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
    }

    function onTableChange(e) {
        var t = e.target;
        if (!t) return;
        if (t.hasAttribute('data-rateb-select-all')) {
            var on = !!t.checked;
            boxes().forEach(function (cb) { cb.checked = on; });
        } else if (!t.hasAttribute('data-rateb-row-check')) {
            return;
        }
        syncUi();
    }

    function onTableClick(e) {
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
    }

    /**
     * Soft-nav replaces #rateb-main-content — re-read DOM and bind once per config node.
     */
    function boot() {
        var cfg = document.getElementById('erpAgencyUpdatesConfig');
        if (!cfg) {
            return;
        }
        if (cfg.getAttribute('data-rateb-agency-updates-bound') === '1') {
            syncUi();
            return;
        }
        cfg.setAttribute('data-rateb-agency-updates-bound', '1');

        state.cfg = cfg;
        state.apiUrl = cfg.getAttribute('data-api-url') || '';
        state.linkUrl = cfg.getAttribute('data-link-url') || '';
        state.syncUrl = cfg.getAttribute('data-sync-url') || '';
        state.restoreAdminUrl = cfg.getAttribute('data-restore-admin-url') || '';
        state.resetDataUrl = cfg.getAttribute('data-reset-data-url') || '';
        state.csrfToken = cfg.getAttribute('data-csrf') || '';
        state.table = document.getElementById('erpAgencyUpdatesTable');
        state.filterCompany = document.getElementById('erpAgencyFilterCompany');
        state.selectionBadge = document.getElementById('erpSelectionBadge');
        state.btnSyncSelected = document.getElementById('erpSyncRunSelected');
        state.btnPushSelected = document.getElementById('erpUpdateRunSelected');
        state.btnFullSelected = document.getElementById('erpFullDeploySelected');
        state.btnRestoreSelected = document.getElementById('erpRestoreAdminSelected');
        state.btnResetSelected = document.getElementById('erpResetDataSelected');
        state.btnResetAll = document.getElementById('erpResetDataAllReady');
        state.btnSyncAll = document.getElementById('erpSyncRunAllReady');
        state.btnPushAll = document.getElementById('erpUpdateRunAllReady');
        state.btnFullAll = document.getElementById('erpFullDeployAllReady');
        state.btnPushSub = document.getElementById('erpUpdateRunSubscribed');
        state.syncConfirmInput = document.getElementById('erpSyncConfirmInput');
        state.resetConfirmInput = document.getElementById('erpResetConfirmInput');
        state.includePlatform = document.getElementById('erpUpdateIncludePlatform');
        state.progress = document.getElementById('erpUpdateProgress');
        state.resultsBox = document.getElementById('erpUpdateResults');
        state.logEl = document.getElementById('erpUpdateLog');
        state.bulkLabel = state.selectionBadge ? state.selectionBadge.textContent.replace(/^\d+\s*/, '') : '';

        bindControls();
        applyCompanyFilter();
    }

    root.ratebAgencyUpdatesBoot = boot;
    root.__erpAgencyResetSelected = function () {
        if (state.cfg) {
            triggerResetSelected();
        }
    };
    root.__erpAgencyResetAllReady = function () {
        if (state.cfg) {
            triggerResetAllReady();
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    document.addEventListener('rateb:nav:afterEnter', boot);
})(typeof window !== 'undefined' ? window : this);
