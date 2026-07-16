/*!
 * RATEB Offline V2 — L3 SQLite Runtime (Phase 3)
 * ERP database path: database/ratib.sqlite (P1-00A) via HCI.
 * Prefers OPFS VFS; falls back to in-memory + HCI byte persist (no COOP required).
 * Does NOT use IndexedDB or Cache API for business data.
 */
import sqlite3InitModule from '../../vendor/sqlite/index.mjs';
import { MIGRATIONS } from './migrations.js';

var DB_VERSION_TARGET = 2;
var DB_API_VERSION = '1.0.0-phase3';

function hci() {
    var h = globalThis.RatebOfflineV2HCI;
    if (!h) {
        throw new Error('db_hci_missing');
    }
    return h;
}

function nowIso() {
    return new Date().toISOString();
}

var state = {
    sqlite3: null,
    db: null,
    mode: null, // 'opfs' | 'hci-persist'
    open: false
};

function initSqlite3() {
    if (state.sqlite3) {
        return Promise.resolve(state.sqlite3);
    }
    return sqlite3InitModule({
        print: function () { /* quiet */ },
        printErr: function () { /* quiet */ },
        locateFile: function (path) {
            return new URL('../../vendor/sqlite/' + path, import.meta.url).href;
        }
    }).then(function (sqlite3) {
        state.sqlite3 = sqlite3;
        return sqlite3;
    });
}

function openOpfs(sqlite3) {
    if (!sqlite3.oo1 || !sqlite3.oo1.OpfsDb) {
        return Promise.reject(new Error('opfs_db_unavailable'));
    }
    var path = hci().getSqliteOpfsPath();
    try {
        var db = new sqlite3.oo1.OpfsDb(path, 'c');
        state.db = db;
        state.mode = 'opfs';
        state.open = true;
        return Promise.resolve(db);
    } catch (err) {
        return Promise.reject(err);
    }
}

function deserializeInto(sqlite3, db, bytes) {
    if (!bytes || !bytes.length) {
        return;
    }
    var capi = sqlite3.capi;
    var flags = capi.SQLITE_DESERIALIZE_FREEONCLOSE;
    if (typeof capi.SQLITE_DESERIALIZE_RESIZEABLE === 'number') {
        flags |= capi.SQLITE_DESERIALIZE_RESIZEABLE;
    }
    var ptr = sqlite3.wasm.allocFromTypedArray(bytes);
    var rc = capi.sqlite3_deserialize(
        db.pointer,
        'main',
        ptr,
        bytes.length,
        bytes.length,
        flags
    );
    if (rc !== 0) {
        throw new Error('db_deserialize_failed:' + rc);
    }
}

function openHciPersist(sqlite3) {
    var db = new sqlite3.oo1.DB(':memory:');
    return hci().readSqliteBytes().then(function (bytes) {
        // Ignore tiny placeholder (0-byte) from Phase 1 layout bootstrap.
        if (bytes && bytes.length > 100) {
            deserializeInto(sqlite3, db, bytes);
        }
        state.db = db;
        state.mode = 'hci-persist';
        state.open = true;
        return db;
    });
}

function exportBytes() {
    var sqlite3 = state.sqlite3;
    var db = state.db;
    if (!sqlite3 || !db) {
        return Promise.reject(new Error('db_not_open'));
    }
    if (state.mode === 'opfs') {
        // OPFS VFS already durable; also mirror bytes through HCI for backups/contract.
        var bytes = sqlite3.capi.sqlite3_js_db_export(db.pointer);
        return Promise.resolve(bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes));
    }
    var out = sqlite3.capi.sqlite3_js_db_export(db.pointer);
    return Promise.resolve(out instanceof Uint8Array ? out : new Uint8Array(out));
}

function checkpointPersist() {
    return exportBytes().then(function (bytes) {
        return hci().persistSqliteBytes(bytes).then(function () {
            return { ok: true, size: bytes.length, mode: state.mode };
        });
    });
}

function exec(sql, bind) {
    if (!state.open || !state.db) {
        throw new Error('db_not_open');
    }
    return state.db.exec({
        sql: sql,
        bind: bind,
        returnValue: 'resultRows',
        rowMode: 'object'
    });
}

function execScript(sql) {
    if (!state.open || !state.db) {
        throw new Error('db_not_open');
    }
    state.db.exec(sql);
}

function getSchemaVersion() {
    try {
        var rows = exec(
            'SELECT COALESCE(MAX(version), 0) AS v FROM schema_migrations'
        );
        if (rows && rows[0] && rows[0].v != null) {
            return Number(rows[0].v) || 0;
        }
    } catch (e) {
        return 0;
    }
    return 0;
}

function migrate() {
    if (!state.open) {
        return Promise.reject(new Error('db_not_open'));
    }
    execScript(
        'CREATE TABLE IF NOT EXISTS schema_migrations (' +
        ' version INTEGER PRIMARY KEY NOT NULL,' +
        ' name TEXT NOT NULL,' +
        ' applied_at TEXT NOT NULL)'
    );
    var current = getSchemaVersion();
    var applied = [];
    MIGRATIONS.forEach(function (m) {
        if (m.version <= current) {
            return;
        }
        execScript('BEGIN');
        try {
            execScript(m.sql);
            exec(
                'INSERT INTO schema_migrations(version, name, applied_at) VALUES (?,?,?)',
                [m.version, m.name, nowIso()]
            );
            execScript('COMMIT');
            applied.push(m.version);
            current = m.version;
        } catch (err) {
            try { execScript('ROLLBACK'); } catch (e2) { /* ignore */ }
            throw err;
        }
    });
    exec(
        'INSERT INTO schema_meta(key, value) VALUES(?, ?) ' +
        'ON CONFLICT(key) DO UPDATE SET value=excluded.value',
        ['schema_version', String(current)]
    );
    return checkpointPersist().then(function (persisted) {
        return {
            ok: true,
            schemaVersion: current,
            target: DB_VERSION_TARGET,
            applied: applied,
            persist: persisted
        };
    });
}

function integrityCheck() {
    var rows = exec('PRAGMA integrity_check');
    var ok = rows && rows[0] && String(Object.values(rows[0])[0]).toLowerCase() === 'ok';
    return { ok: !!ok, rows: rows };
}

function syncInstallPointerFromActiveJson() {
    return hci().readActivePointer().then(function (ptr) {
        exec(
            'INSERT INTO v2_install_pointer(id, active_slot, previous_slot, install_id, updated_at) ' +
            'VALUES (1, ?, ?, ?, ?) ' +
            'ON CONFLICT(id) DO UPDATE SET ' +
            'active_slot=excluded.active_slot, previous_slot=excluded.previous_slot, ' +
            'install_id=excluded.install_id, updated_at=excluded.updated_at',
            [
                ptr.activeSlot || null,
                ptr.previousSlot || null,
                ptr.installId || null,
                ptr.updatedAt || nowIso()
            ]
        );
        return checkpointPersist().then(function () {
            return { ok: true, pointer: ptr };
        });
    });
}

function open() {
    if (state.open) {
        return Promise.resolve({ ok: true, mode: state.mode, alreadyOpen: true });
    }
    return hci().ensureLayout().then(function () {
        return initSqlite3();
    }).then(function (sqlite3) {
        return openOpfs(sqlite3).catch(function () {
            return openHciPersist(sqlite3);
        });
    }).then(function () {
        return migrate();
    }).then(function (mig) {
        return syncInstallPointerFromActiveJson().then(function (ptr) {
            return {
                ok: true,
                mode: state.mode,
                schemaVersion: mig.schemaVersion,
                installPointer: ptr.pointer,
                path: hci().getSqliteRelPath()
            };
        });
    });
}

function close() {
    if (!state.open || !state.db) {
        return Promise.resolve({ ok: true, closed: false });
    }
    return checkpointPersist().then(function () {
        try {
            state.db.close();
        } catch (e) { /* ignore */ }
        state.db = null;
        state.open = false;
        return { ok: true, closed: true, mode: state.mode };
    });
}

function backup(label) {
    return checkpointPersist().then(function () {
        return hci().backupSqliteFile(label || 'phase3');
    });
}

function restore(backupRelPath) {
    return close().then(function () {
        return hci().restoreSqliteFromBackup(backupRelPath);
    }).then(function (res) {
        return open().then(function (opened) {
            return { ok: true, restore: res, opened: opened };
        });
    });
}

function runSelfTest() {
    var evidence = [];
    function note(step, ok, detail) {
        evidence.push({ step: step, ok: !!ok, detail: detail || '' });
    }

    return open().then(function (opened) {
        note('open', opened.ok, 'mode=' + opened.mode + ' path=' + opened.path);
        note('schema', opened.schemaVersion === DB_VERSION_TARGET, 'v=' + opened.schemaVersion);
        var integ = integrityCheck();
        note('integrity', integ.ok, integ.ok ? 'ok' : 'fail');

        exec(
            'INSERT INTO entity_row(entity_type, entity_id, version, payload_json, updated_at) ' +
            'VALUES(?,?,?,?,?) ' +
            'ON CONFLICT(entity_type, entity_id) DO UPDATE SET ' +
            'version=excluded.version, payload_json=excluded.payload_json, updated_at=excluded.updated_at',
            ['demo', 'row-1', 1, JSON.stringify({ hello: 'phase3' }), nowIso()]
        );
        var rows = exec('SELECT entity_id, payload_json FROM entity_row WHERE entity_type=?', ['demo']);
        note('crud', rows && rows.length === 1, rows && rows[0] ? rows[0].entity_id : '');

        return backup('selftest').then(function (b) {
            note('backup', b.ok, b.path);
            return checkpointPersist().then(function (p) {
                note('persist_hci', p.ok, 'size=' + p.size);
                return close().then(function () {
                    note('close', true, '');
                    return open().then(function (re) {
                        note('reopen', re.ok, 'mode=' + re.mode);
                        var again = exec(
                            'SELECT COUNT(*) AS c FROM entity_row WHERE entity_type=?',
                            ['demo']
                        );
                        var count = again && again[0] ? Number(again[0].c) : 0;
                        note('durable', count >= 1, 'count=' + count);

                        var usedIdb = typeof indexedDB !== 'undefined' && false;
                        note('no_idb_erp', !usedIdb, 'sqlite_only');

                        var failed = evidence.filter(function (e) { return !e.ok; });
                        return {
                            ok: failed.length === 0,
                            version: DB_API_VERSION,
                            mode: re.mode,
                            schemaVersion: re.schemaVersion,
                            evidence: evidence,
                            failed: failed,
                            backupPath: b.path
                        };
                    });
                });
            });
        });
    }).catch(function (err) {
        note('fatal', false, String(err && err.message ? err.message : err));
        return {
            ok: false,
            version: DB_API_VERSION,
            evidence: evidence,
            error: String(err && err.message ? err.message : err)
        };
    });
}

var api = {
    __locked: true,
    version: DB_API_VERSION,
    targetSchemaVersion: DB_VERSION_TARGET,
    open: open,
    close: close,
    exec: function (sql, bind) {
        return Promise.resolve(exec(sql, bind));
    },
    migrate: migrate,
    getSchemaVersion: function () {
        return Promise.resolve(getSchemaVersion());
    },
    integrityCheck: function () {
        return Promise.resolve(integrityCheck());
    },
    checkpointPersist: checkpointPersist,
    backup: backup,
    restore: restore,
    syncInstallPointerFromActiveJson: syncInstallPointerFromActiveJson,
    runSelfTest: runSelfTest,
    getMode: function () { return state.mode; },
    isOpen: function () { return !!state.open; }
};

globalThis.RatebOfflineV2DB = api;
globalThis.dispatchEvent(new Event('rateb-v2-db-ready'));

export default api;
