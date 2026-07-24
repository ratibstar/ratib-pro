/*!
 * RATEB Offline V2 — POS local audit trail (Phase 10)
 *
 * Entity: pos.audit_event on existing entity_row.
 * Local only — no sync.start(), API, or Inventory writes.
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || typeof Business.createDocStore !== 'function') {
        return;
    }

    var ET = {
        audit: 'pos.audit_event'
    };

    var EVENT = {
        SALE_CREATED: 'SALE_CREATED',
        SALE_COMPLETED: 'SALE_COMPLETED',
        SALE_CANCELLED: 'SALE_CANCELLED',
        RESERVATION_CREATED: 'RESERVATION_CREATED',
        RESERVATION_RELEASED: 'RESERVATION_RELEASED',
        SYNC_PREPARED: 'SYNC_PREPARED',
        CONFLICT_CREATED: 'CONFLICT_CREATED',
        CONFLICT_RESOLVED: 'CONFLICT_RESOLVED',
        SYNC_VALIDATION_STARTED: 'SYNC_VALIDATION_STARTED',
        SYNC_VALIDATION_SUCCESS: 'SYNC_VALIDATION_SUCCESS',
        SYNC_VALIDATION_FAILED: 'SYNC_VALIDATION_FAILED'
    };

    function nowIso() {
        return new Date().toISOString();
    }

    function uid(prefix) {
        return (prefix || 'aud') + '-' + Date.now().toString(36) + '-' +
            Math.random().toString(36).slice(2, 8);
    }

    function createPosAudit(module) {
        var state = {
            store: null
        };

        function ensureStore() {
            if (state.store) {
                return Promise.resolve(state.store);
            }
            var db = module.ctx && module.ctx.db;
            if (!db) {
                return Promise.reject(new Error('pos_db_missing'));
            }
            return db.open().then(function () {
                state.store = Business.createDocStore(db, {
                    ownedPrefix: 'pos.',
                    errorCode: 'pos_forbidden_storage'
                });
                return state.store;
            });
        }

        function record(idCtx, spec) {
            spec = spec || {};
            if (!spec.event_type) {
                return Promise.reject(new Error('pos_audit_event_type_required'));
            }
            var ts = nowIso();
            var eventId = spec.event_id || uid('aud');
            var row = {
                id: eventId,
                event_id: eventId,
                company_id: idCtx.company_id,
                event_type: String(spec.event_type),
                entity_type: spec.entity_type || null,
                entity_id: spec.entity_id != null ? String(spec.entity_id) : null,
                timestamp: spec.timestamp || ts,
                device_id: spec.device_id || null,
                metadata: spec.metadata || null,
                created_at: ts,
                version: 1
            };
            return ensureStore().then(function (store) {
                return store.put(ET.audit, eventId, row, 1).then(function () {
                    return { ok: true, event: row, sync_started: false, network: false };
                });
            });
        }

        function listEvents(idCtx, filters) {
            filters = filters || {};
            return ensureStore().then(function (store) {
                return store.list(ET.audit, idCtx.company_id).then(function (rows) {
                    var list = (rows || []).map(function (r) { return r.payload; }).filter(Boolean);
                    return list.filter(function (e) {
                        if (filters.event_type && e.event_type !== filters.event_type) {
                            return false;
                        }
                        if (filters.entity_id && String(e.entity_id) !== String(filters.entity_id)) {
                            return false;
                        }
                        if (filters.entity_type && e.entity_type !== filters.entity_type) {
                            return false;
                        }
                        return true;
                    }).sort(function (a, b) {
                        return String(b.timestamp || b.created_at || '')
                            .localeCompare(String(a.timestamp || a.created_at || ''));
                    });
                });
            });
        }

        return {
            ET: ET,
            EVENT: EVENT,
            ensureStore: ensureStore,
            record: record,
            listEvents: listEvents,
            isStoreOpen: function () { return !!state.store; }
        };
    }

    root.RatebOfflineV2PosAudit = {
        __locked: true,
        create: createPosAudit,
        entityTypes: ET,
        EVENT: EVENT
    };
})(typeof window !== 'undefined' ? window : this);
