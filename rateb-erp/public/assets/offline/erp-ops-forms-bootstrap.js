/**
 * RATEB Offline — Ops forms + master-data pickers bootstrap (Phase 14).
 * Loaded when read_cache is on and any Tier-1 write / master_data / ops_pages flag is set.
 */
(function (root) {
    'use strict';

    var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || root.__RATEB_ERP_MASTER_DATA__ || {};

    function flags() {
        if (root.RatebOffline && typeof root.RatebOffline.flags === 'function') {
            return root.RatebOffline.flags() || {};
        }
        return cfg.flags || {};
    }

    function isOffline() {
        var conn = root.RatebOfflineConnectivity;
        if (conn && typeof conn.isOnline === 'function') {
            return !conn.isOnline();
        }
        return typeof navigator !== 'undefined' && navigator.onLine === false;
    }

    function showMasterDataWarning(msg) {
        try {
            if (!root.document || !root.document.body) {
                return;
            }
            var id = 'rateb-offline-md-warning';
            var existing = root.document.getElementById(id);
            if (existing) {
                existing.textContent = msg;
                return;
            }
            var el = root.document.createElement('div');
            el.id = id;
            el.className = 'alert alert-warning m-2';
            el.setAttribute('role', 'status');
            el.textContent = msg;
            var main = root.document.querySelector('main') || root.document.body;
            main.insertBefore(el, main.firstChild);
        } catch (e) { /* ignore */ }
    }

    function fillSelect(select, options, keepSelected) {
        if (!select || !options || !options.length) {
            return;
        }
        var selected = keepSelected ? String(select.value || '') : '';
        var placeholder = select.querySelector('option[value=""]');
        var html = placeholder ? placeholder.outerHTML : '<option value=""></option>';
        options.forEach(function (opt) {
            var v = String(opt.value);
            var label = String(opt.label || v);
            html += '<option value="' + v.replace(/"/g, '&quot;') + '"'
                + (selected && selected === v ? ' selected' : '')
                + '>' + label.replace(/</g, '&lt;') + '</option>';
        });
        select.innerHTML = html;
        if (selected) {
            select.value = selected;
        }
    }

    function hydratePickers() {
        var md = root.RatebOfflineMasterData;
        if (!md || typeof md.pickerOptions !== 'function' || !md.isActive()) {
            return;
        }
        if (!isOffline()) {
            return;
        }
        var map = [
            { names: ['warehouse_id', 'source_warehouse_id', 'destination_warehouse_id'], entity: 'warehouse_directory' },
            { names: ['employee_id'], entity: 'employee_directory' },
            { names: ['supplier_id'], entity: 'supplier_directory' },
            { names: ['branch_id'], entity: 'branch_directory' },
            { names: ['customer_id'], entity: 'customer_directory' }
        ];
        map.forEach(function (entry) {
            entry.names.forEach(function (name) {
                var nodes = root.document.querySelectorAll('select[name="' + name + '"]');
                if (!nodes.length) {
                    return;
                }
                md.pickerOptions(entry.entity, { limit: 300 }).then(function (res) {
                    if (res && res.error === 'migration_required') {
                        showMasterDataWarning('Master data migration required — reconnect and sync directories.');
                        return;
                    }
                    if (!res || !res.options || !res.options.length) {
                        return;
                    }
                    nodes.forEach(function (sel) {
                        fillSelect(sel, res.options, true);
                    });
                }).catch(function () { /* ignore */ });
            });
        });
    }

    function surfaceSyncWarnings() {
        if (!root.RatebOfflineEvents) {
            return;
        }
        root.RatebOfflineEvents.on && root.RatebOfflineEvents.on('sdk:flags', function () { /* noop */ });
        // Listen via document custom events if master-data bootstrap emits warnings.
        root.document.addEventListener('rateb-offline-master-data', function (ev) {
            var d = ev && ev.detail ? ev.detail : null;
            if (!d) {
                return;
            }
            if (d.migration_required) {
                showMasterDataWarning('مطلوب ترحيل بيانات رئيسية — migration_required.');
            } else if (d.warning === 'page_limit_reached' || d.page_limit_reached) {
                showMasterDataWarning('حد صفحات المزامنة — page_limit_reached. أعد المزامنة لاحقاً.');
            }
        });
    }

    function boot() {
        var f = flags();
        if (!f['offline.enabled']) {
            return;
        }
        if (root.RatebOfflineOpsForms && typeof root.RatebOfflineOpsForms.bind === 'function') {
            root.RatebOfflineOpsForms.bind();
        }
        surfaceSyncWarnings();
        var runPickers = function () {
            hydratePickers();
        };
        if (root.document.readyState === 'complete') {
            setTimeout(runPickers, 400);
        } else {
            root.addEventListener('load', function () {
                setTimeout(runPickers, 400);
            }, { once: true });
        }
        root.addEventListener('offline', runPickers);
        if (root.RatebOfflineConnectivity && typeof root.RatebOfflineConnectivity.subscribe === 'function') {
            root.RatebOfflineConnectivity.subscribe(function (online) {
                if (!online) {
                    runPickers();
                }
            });
        }
    }

    if (root.document && root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})(typeof window !== 'undefined' ? window : globalThis);
