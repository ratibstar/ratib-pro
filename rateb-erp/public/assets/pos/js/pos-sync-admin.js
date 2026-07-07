(function () {
    'use strict';

    var root = document.querySelector('[data-pos-sync-admin]');
    if (!root) {
        return;
    }

    var cfg = {};
    try {
        cfg = JSON.parse(document.getElementById('rateb-pos-sync-config').textContent || '{}');
    } catch (e) {
        cfg = {};
    }

    var csrf = cfg.csrf || '';
    var api = cfg.api || {};
    var i18n = cfg.i18n || {};
    var statusBadge = root.querySelector('[data-pos-sync-connection]');
    var processBtn = root.querySelector('[data-pos-sync-process]');
    var alertBox = root.querySelector('[data-pos-sync-alert]');

    function t(key, fb) {
        return i18n[key] || fb || key;
    }

    function setOnlineBadge(online) {
        if (!statusBadge) {
            return;
        }
        statusBadge.textContent = online ? t('pos_online', 'Online') : t('pos_offline', 'Offline');
        statusBadge.classList.toggle('bg-success', online);
        statusBadge.classList.toggle('bg-secondary', !online);
    }

    function showAlert(message, type) {
        if (!alertBox) {
            window.alert(message);
            return;
        }
        alertBox.className = 'alert alert-' + (type || 'warning');
        alertBox.textContent = message;
        alertBox.hidden = false;
    }

    function fetchJson(url, options) {
        options = options || {};
        return fetch(url, {
            method: options.method || 'GET',
            credentials: 'same-origin',
            headers: Object.assign({
                'Accept': 'application/json',
                'X-CSRF-Token': csrf
            }, options.headers || {}),
            body: options.body || null
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok || data.ok === false) {
                    throw new Error((data && data.error) || t('invalid_request', 'Request failed'));
                }
                return data;
            });
        });
    }

    function requireOnline() {
        if (!navigator.onLine) {
            showAlert(t('pos_sync_offline_blocked', 'Cannot run sync actions while offline.'), 'warning');
            return false;
        }
        return true;
    }

    function processPending(evt) {
        if (evt) {
            evt.preventDefault();
        }
        if (!requireOnline() || !api.process) {
            return;
        }
        if (processBtn) {
            processBtn.disabled = true;
        }
        fetchJson(api.process, { method: 'POST' })
            .then(function (data) {
                var r = data.result || {};
                showAlert(
                    t('pos_sync_process_done', 'Done')
                        .replace(':synced', String(r.synced || 0))
                        .replace(':failed', String(r.failed || 0)),
                    'success'
                );
                window.setTimeout(function () { window.location.reload(); }, 800);
            })
            .catch(function (err) {
                showAlert(err.message || t('invalid_request', 'Request failed'), 'danger');
            })
            .finally(function () {
                if (processBtn) {
                    processBtn.disabled = false;
                }
            });
    }

    function resolveConflict(evt) {
        evt.preventDefault();
        if (!requireOnline() || !api.resolveConflict) {
            return;
        }
        var btn = evt.currentTarget;
        var conflictId = btn.getAttribute('data-conflict-id');
        var resolution = btn.getAttribute('data-resolution');
        if (!conflictId || !resolution) {
            return;
        }
        btn.disabled = true;
        fetchJson(api.resolveConflict.replace('{id}', conflictId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ resolution: resolution })
        })
            .then(function () {
                window.location.reload();
            })
            .catch(function (err) {
                showAlert(err.message || t('invalid_request', 'Request failed'), 'danger');
                btn.disabled = false;
            });
    }

    function syncConnection() {
        setOnlineBadge(navigator.onLine);
        if (processBtn) {
            processBtn.disabled = !navigator.onLine;
            processBtn.title = navigator.onLine ? '' : t('pos_sync_offline_blocked', '');
        }
    }

    if (processBtn) {
        processBtn.addEventListener('click', processPending);
    }

    root.querySelectorAll('[data-pos-sync-resolve]').forEach(function (btn) {
        btn.addEventListener('click', resolveConflict);
    });

    window.addEventListener('online', syncConnection);
    window.addEventListener('offline', syncConnection);
    syncConnection();
})();
