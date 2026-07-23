/*!
 * RATEB Offline V2 — POS offline device identity (Phase 10)
 *
 * Local-only stable device UUID + installation id.
 * No server registration, no API calls, no sync.start().
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || typeof Business.createDocStore !== 'function') {
        return;
    }

    var ET = {
        device: 'pos.device_identity'
    };

    var ENTITY_ID = 'local';
    var LS_UUID = 'rateb.pos.device_uuid';
    var LS_INSTALL = 'rateb.pos.installation_id';

    function nowIso() {
        return new Date().toISOString();
    }

    function uuidV4() {
        var rnd = (root.crypto && typeof root.crypto.getRandomValues === 'function')
            ? root.crypto.getRandomValues(new Uint8Array(16))
            : null;
        if (!rnd) {
            var s = '';
            for (var i = 0; i < 32; i++) {
                s += Math.floor(Math.random() * 16).toString(16);
            }
            return s.slice(0, 8) + '-' + s.slice(8, 12) + '-4' + s.slice(13, 16) +
                '-a' + s.slice(17, 20) + '-' + s.slice(20, 32);
        }
        rnd[6] = (rnd[6] & 0x0f) | 0x40;
        rnd[8] = (rnd[8] & 0x3f) | 0x80;
        var hex = [];
        for (var j = 0; j < rnd.length; j++) {
            hex.push((rnd[j] < 16 ? '0' : '') + rnd[j].toString(16));
        }
        var h = hex.join('');
        return h.slice(0, 8) + '-' + h.slice(8, 12) + '-' + h.slice(12, 16) +
            '-' + h.slice(16, 20) + '-' + h.slice(20, 32);
    }

    function readLs(key) {
        try {
            return root.localStorage ? root.localStorage.getItem(key) : null;
        } catch (e) {
            return null;
        }
    }

    function writeLs(key, value) {
        try {
            if (root.localStorage) {
                root.localStorage.setItem(key, value);
            }
        } catch (e) { /* ignore quota / private mode */ }
    }

    function createPosDevice(module) {
        var state = {
            store: null,
            cached: null
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

        function seedFromLs() {
            return {
                device_uuid: readLs(LS_UUID) || null,
                installation_id: readLs(LS_INSTALL) || null
            };
        }

        function persistLs(identity) {
            if (identity && identity.device_uuid) {
                writeLs(LS_UUID, identity.device_uuid);
            }
            if (identity && identity.installation_id) {
                writeLs(LS_INSTALL, identity.installation_id);
            }
        }

        /**
         * Get-or-create stable local device identity (idempotent across reloads).
         */
        function ensureIdentity(idCtx) {
            if (state.cached && state.cached.company_id === idCtx.company_id) {
                var touch = Object.assign({}, state.cached, {
                    last_activity_at: nowIso()
                });
                state.cached = touch;
                return ensureStore().then(function (store) {
                    return store.put(ET.device, ENTITY_ID, touch, Number(touch.version || 1) + 1)
                        .then(function () {
                            persistLs(touch);
                            return touch;
                        });
                });
            }
            return ensureStore().then(function (store) {
                return store.get(ET.device, ENTITY_ID, idCtx.company_id).then(function (row) {
                    var ls = seedFromLs();
                    if (row && row.payload && row.payload.device_uuid) {
                        var existing = Object.assign({}, row.payload, {
                            last_activity_at: nowIso(),
                            company_id: idCtx.company_id,
                            /* Placeholders for future Online binding — never registered here. */
                            company_binding: row.payload.company_binding || null,
                            branch_binding: row.payload.branch_binding || null
                        });
                        if (!existing.installation_id && ls.installation_id) {
                            existing.installation_id = ls.installation_id;
                        }
                        return store.put(ET.device, ENTITY_ID, existing, Number(existing.version || 1) + 1)
                            .then(function () {
                                persistLs(existing);
                                state.cached = existing;
                                return existing;
                            });
                    }
                    var createdAt = nowIso();
                    var identity = {
                        id: ENTITY_ID,
                        company_id: idCtx.company_id,
                        device_uuid: ls.device_uuid || uuidV4(),
                        installation_id: ls.installation_id || uuidV4(),
                        created_at: createdAt,
                        last_activity_at: createdAt,
                        company_binding: null,
                        branch_binding: null,
                        branch_id_placeholder: idCtx.branch_id || 0,
                        company_id_placeholder: idCtx.company_id,
                        registered_server: false,
                        source: 'local_offline',
                        version: 1
                    };
                    return store.put(ET.device, ENTITY_ID, identity, 1).then(function () {
                        persistLs(identity);
                        state.cached = identity;
                        return identity;
                    });
                });
            });
        }

        function getIdentity(idCtx) {
            return ensureIdentity(idCtx);
        }

        function getDeviceUuid(idCtx) {
            return ensureIdentity(idCtx).then(function (id) {
                return id.device_uuid;
            });
        }

        /**
         * Idempotency / sync key: DEVICE_UUID + SALE_ID + CREATED_AT
         */
        function buildSyncKey(deviceUuid, saleId, createdAt) {
            return String(deviceUuid || '') + '+' + String(saleId || '') + '+' + String(createdAt || '');
        }

        return {
            ET: ET,
            ENTITY_ID: ENTITY_ID,
            ensureStore: ensureStore,
            ensureIdentity: ensureIdentity,
            getIdentity: getIdentity,
            getDeviceUuid: getDeviceUuid,
            buildSyncKey: buildSyncKey,
            isStoreOpen: function () { return !!state.store; }
        };
    }

    root.RatebOfflineV2PosDevice = {
        __locked: true,
        create: createPosDevice,
        entityTypes: ET
    };
})(typeof window !== 'undefined' ? window : this);
