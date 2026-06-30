(function () {
    'use strict';

    var cfg = document.getElementById('erpAgencyUpdatesConfig');
    if (!cfg) return;

    var apiUrl = cfg.getAttribute('data-api-url') || '';
    var csrfToken = cfg.getAttribute('data-csrf') || '';
    var btnSelected = document.getElementById('erpUpdateRunSelected');
    var btnAll = document.getElementById('erpUpdateRunAllReady');
    var btnSub = document.getElementById('erpUpdateRunSubscribed');
    var selectAll = document.getElementById('erpUpdateSelectAll');
    var progress = document.getElementById('erpUpdateProgress');
    var resultsBox = document.getElementById('erpUpdateResults');
    var logEl = document.getElementById('erpUpdateLog');
    var includePlatform = document.getElementById('erpUpdateIncludePlatform');

    function boxes() {
        return Array.prototype.slice.call(document.querySelectorAll('.erp-update-agency-cb'));
    }

    function selectedIds() {
        return boxes().filter(function (cb) { return cb.checked; }).map(function (cb) { return parseInt(cb.value, 10); }).filter(function (n) { return n > 0; });
    }

    function setBusy(busy) {
        [btnSelected, btnAll, btnSub].forEach(function (b) {
            if (b) b.disabled = busy || (b === btnSelected && selectedIds().length === 0);
        });
    }

    function refreshSelectedBtn() {
        if (btnSelected) btnSelected.disabled = selectedIds().length === 0;
    }

    boxes().forEach(function (cb) {
        cb.addEventListener('change', refreshSelectedBtn);
    });
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            var on = !!selectAll.checked;
            boxes().forEach(function (cb) { cb.checked = on; });
            refreshSelectedBtn();
        });
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

    function run(payload, confirmMsg) {
        if (confirmMsg && !window.confirm(confirmMsg)) return;
        setBusy(true);
        showProgress(cfg.getAttribute('data-running') || 'Running…');
        if (resultsBox) resultsBox.classList.add('d-none');

        fetch(apiUrl, {
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
                refreshSelectedBtn();
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

    refreshSelectedBtn();
})();
