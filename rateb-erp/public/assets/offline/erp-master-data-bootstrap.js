/**
 * RATEB Offline — Master-data bootstrap (Phase 14).
 * Loaded only when offline.enabled + offline.master_data are ON.
 * Surfaces migration_required / page_limit_reached via CustomEvent.
 */
(function (root) {
    'use strict';

    var cfg = root.__RATEB_ERP_MASTER_DATA__ || root.__RATEB_ERP_SHELL_OFFLINE__ || {};

    function flagsOk() {
        var f = cfg.flags || {};
        return !!(f['offline.enabled'] && f['offline.master_data']);
    }

    function emitMasterDataEvent(detail) {
        try {
            if (!root.document || typeof root.CustomEvent !== 'function') {
                return;
            }
            root.document.dispatchEvent(new root.CustomEvent('rateb-offline-master-data', {
                detail: detail || {}
            }));
        } catch (e) { /* ignore */ }
    }

    function surfaceSyncResult(result) {
        if (!result || !result.results) {
            return;
        }
        var entities = Object.keys(result.results);
        var migration = false;
        var pageLimit = false;
        entities.forEach(function (k) {
            var r = result.results[k] || {};
            if (r.migration_required || r.error === 'migration_required' || r.error === 'updated_at_required') {
                migration = true;
            }
            if (r.warning === 'page_limit_reached' || r.incomplete) {
                pageLimit = true;
            }
        });
        if (migration) {
            emitMasterDataEvent({ migration_required: true });
        }
        if (pageLimit) {
            emitMasterDataEvent({ page_limit_reached: true, warning: 'page_limit_reached' });
        }
    }

    function boot() {
        if (!flagsOk()) {
            return;
        }
        if (!(parseInt(cfg.company_id, 10) > 0)) {
            return;
        }
        if (root.RatebOffline && typeof root.RatebOffline.init === 'function') {
            root.RatebOffline.init({
                apiBase: cfg.apiBase || '',
                probeUrl: cfg.probeUrl || null,
                flags: cfg.flags || {},
                startConnectivity: !root.RatebOffline.isBooted(),
                startScheduler: false
            });
        }
        var md = root.RatebOfflineMasterData;
        if (!md || !md.isActive()) {
            return;
        }
        if (root.navigator && root.navigator.onLine === false) {
            return;
        }
        md.syncAll({
            apiBase: cfg.apiBase || '',
            scope: {
                company_id: parseInt(cfg.company_id, 10) || 0,
                branch_id: parseInt(cfg.branch_id, 10) || 0,
                user_id: parseInt(cfg.user_id, 10) || 0
            }
        }).then(function (result) {
            surfaceSyncResult(result);
        }).catch(function () { /* fail soft */ });
    }

    if (root.document && root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})(typeof window !== 'undefined' ? window : globalThis);
