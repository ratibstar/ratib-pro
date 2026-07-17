/*!
 * RATEB Offline V2 — Host Capability Interface (HCI)
 * Phase 1 L0 + Phase 2 L7 write policy.
 * Layout: P1-00A (immutable). All durable storage via this module only.
 */
(function (root) {
    'use strict';

    var HCI_VERSION = '1.2.0-phase3';
    var LAYOUT_ID = 'P1-00A';
    var OPFS_APP_ROOT = 'rateb-offline-v2';
    var SQLITE_REL_PATH = 'database/ratib.sqlite';
    var SLOTS = ['slot-a', 'slot-b', 'slot-c'];
    var PACKAGE_TYPES = ['runtime', 'modules', 'language', 'assets'];

    var TOP_LEVEL = [
        'runtime', 'packages', 'slots', 'database', 'vault',
        'logs', 'temp', 'updates', 'backups'
    ];

    var DIRS = [
        'runtime',
        'packages',
        'packages/runtime',
        'packages/modules',
        'packages/language',
        'packages/assets',
        'slots',
        'slots/slot-a',
        'slots/slot-b',
        'slots/slot-c',
        'database',
        'vault',
        'logs',
        'temp',
        'updates',
        'backups'
    ];

    var PLACEHOLDER_FILES = {
        'runtime/runtime.pkg': new Uint8Array(0),
        'runtime/runtime.manifest': null,
        'runtime/active.json': null,
        'database/ratib.sqlite': new Uint8Array(0),
        'vault/vault.bin': new Uint8Array(0)
    };

    function utf8Encode(str) {
        return new TextEncoder().encode(String(str));
    }

    function utf8Decode(bytes) {
        return new TextDecoder().decode(bytes);
    }

    function defaultManifestBytes() {
        return utf8Encode(JSON.stringify({
            id: 'rateb-offline-v2-runtime',
            version: '0.0.0-p1',
            phase: 1,
            layout: LAYOUT_ID,
            note: 'Placeholder — replaced only by L7 atomic activate'
        }, null, 2));
    }

    function defaultActiveBytes() {
        return utf8Encode(JSON.stringify({
            layout: LAYOUT_ID,
            phase: 2,
            activeSlot: null,
            previousSlot: null,
            status: 'host-ready',
            updatedAt: new Date().toISOString()
        }, null, 2));
    }

    function normalizeRelPath(rel) {
        var p = String(rel || '').replace(/\\/g, '/').replace(/^\/+/, '');
        if (!p || p.indexOf('..') !== -1) {
            throw new Error('hci_invalid_path');
        }
        return p;
    }

    function topSegment(rel) {
        return normalizeRelPath(rel).split('/')[0];
    }

    function assertKnownTop(rel) {
        var top = topSegment(rel);
        if (TOP_LEVEL.indexOf(top) === -1) {
            throw new Error('hci_unknown_top_level:' + top);
        }
    }

    function slotIdFromPath(p) {
        var m = /^slots\/(slot-[abc])(\/|$)/.exec(p);
        return m ? m[1] : null;
    }

    /**
     * Write policy (Phase 2):
     * - packages/** : packageIngest + createIfAbsent only (immutable after write)
     * - updates/** : staging
     * - slots/slot-* : only if slot !== activeSlot (never mutate active slot)
     * - runtime/** : activate / bootstrap
     * - backups/**, logs/**, temp/** : allowed
     * - database/**, vault/** : layoutBootstrap createIfAbsent only
     */
    function assertWritable(rel, opts) {
        opts = opts || {};
        var p = normalizeRelPath(rel);
        assertKnownTop(p);

        if (opts.layoutBootstrap) {
            return;
        }

        if (p === 'packages' || p.indexOf('packages/') === 0) {
            if (!opts.packageIngest) {
                throw new Error('hci_packages_immutable');
            }
            if (!opts.createIfAbsent) {
                throw new Error('hci_packages_no_overwrite');
            }
            var segs = p.split('/');
            if (segs.length < 3 || PACKAGE_TYPES.indexOf(segs[1]) === -1) {
                throw new Error('hci_packages_bad_path');
            }
            return;
        }

        if (p.indexOf('updates/') === 0 || p === 'updates') {
            return;
        }

        if (p.indexOf('backups/') === 0 || p === 'backups') {
            return;
        }

        if (p.indexOf('logs/') === 0 || p === 'logs' || p.indexOf('temp/') === 0 || p === 'temp') {
            return;
        }

        if (p.indexOf('runtime/') === 0) {
            return;
        }

        var slot = slotIdFromPath(p);
        if (slot) {
            if (opts.activeSlot && opts.activeSlot === slot) {
                throw new Error('hci_active_slot_immutable:' + slot);
            }
            if (SLOTS.indexOf(slot) === -1) {
                throw new Error('hci_bad_slot');
            }
            return;
        }

        if (p.indexOf('database/') === 0 || p.indexOf('vault/') === 0) {
            if (opts.createIfAbsent) {
                return;
            }
            if (opts.sqlitePersist && p === SQLITE_REL_PATH) {
                return;
            }
            if (opts.vaultPersist && p === 'vault/vault.bin') {
                return;
            }
            throw new Error('hci_write_denied:' + p);
        }

        throw new Error('hci_write_denied:' + p);
    }

    var appRootPromise = null;

    function getOpfsRoot() {
        if (!root.isSecureContext) {
            return Promise.reject(new Error('hci_insecure_context'));
        }
        if (!root.navigator || !root.navigator.storage || typeof root.navigator.storage.getDirectory !== 'function') {
            return Promise.reject(new Error('hci_opfs_unavailable'));
        }
        if (appRootPromise) {
            return appRootPromise;
        }
        appRootPromise = root.navigator.storage.getDirectory().then(function (dir) {
            return dir.getDirectoryHandle(OPFS_APP_ROOT, { create: true });
        }).catch(function (err) {
            appRootPromise = null;
            throw err;
        });
        return appRootPromise;
    }

    function getChildDirectory(parent, name, create) {
        return parent.getDirectoryHandle(name, { create: !!create });
    }

    function resolveDirectory(rel, create) {
        var p = normalizeRelPath(rel);
        assertKnownTop(p);
        var parts = p.split('/').filter(Boolean);
        return getOpfsRoot().then(function (handle) {
            var chain = Promise.resolve(handle);
            parts.forEach(function (part) {
                chain = chain.then(function (h) {
                    return getChildDirectory(h, part, create);
                });
            });
            return chain;
        });
    }

    function resolveParentAndName(rel, createParents) {
        var p = normalizeRelPath(rel);
        var parts = p.split('/').filter(Boolean);
        if (parts.length < 1) {
            throw new Error('hci_invalid_path');
        }
        var name = parts.pop();
        var parentRel = parts.join('/');
        var parentPromise = parentRel
            ? resolveDirectory(parentRel, !!createParents)
            : getOpfsRoot();
        return parentPromise.then(function (parent) {
            return { parent: parent, name: name, path: p };
        });
    }

    function fileExists(parent, name) {
        return parent.getFileHandle(name).then(function () {
            return true;
        }).catch(function () {
            return false;
        });
    }

    function writeBytes(rel, bytes, opts) {
        opts = opts || {};
        var p = normalizeRelPath(rel);
        assertWritable(p, opts);
        var data = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes || []);

        return resolveParentAndName(p, true).then(function (ctx) {
            return fileExists(ctx.parent, ctx.name).then(function (exists) {
                if (exists && opts.createIfAbsent) {
                    return { path: p, created: false, skipped: true };
                }
                if (exists && opts.packageIngest) {
                    throw new Error('hci_packages_no_overwrite');
                }
                return ctx.parent.getFileHandle(ctx.name, { create: true }).then(function (fh) {
                    return fh.createWritable().then(function (w) {
                        return w.write(data).then(function () {
                            return w.close();
                        });
                    }).then(function () {
                        return { path: p, created: !exists, skipped: false };
                    });
                });
            });
        });
    }

    function writeFileCreateIfAbsent(rel, bytes) {
        return writeBytes(rel, bytes, { layoutBootstrap: true, createIfAbsent: true });
    }

    function writeJson(rel, obj, opts) {
        return writeBytes(rel, utf8Encode(JSON.stringify(obj, null, 2)), opts || {});
    }

    function readFile(rel) {
        var p = normalizeRelPath(rel);
        assertKnownTop(p);
        return resolveParentAndName(p, false).then(function (ctx) {
            return ctx.parent.getFileHandle(ctx.name).then(function (fh) {
                return fh.getFile().then(function (file) {
                    return file.arrayBuffer().then(function (buf) {
                        return new Uint8Array(buf);
                    });
                });
            });
        });
    }

    function readJson(rel) {
        return readFile(rel).then(function (bytes) {
            return JSON.parse(utf8Decode(bytes));
        });
    }

    function readActivePointer() {
        return readJson('runtime/active.json').catch(function () {
            return { activeSlot: null, previousSlot: null, status: 'missing' };
        });
    }

    function sha256Hex(bytes) {
        var data = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes || []);
        return root.crypto.subtle.digest('SHA-256', data).then(function (buf) {
            var arr = new Uint8Array(buf);
            var hex = '';
            for (var i = 0; i < arr.length; i++) {
                hex += (arr[i] < 16 ? '0' : '') + arr[i].toString(16);
            }
            return hex;
        });
    }

    function removeFile(rel) {
        var p = normalizeRelPath(rel);
        assertKnownTop(p);
        if (p.indexOf('packages/') === 0) {
            throw new Error('hci_packages_immutable');
        }
        return resolveParentAndName(p, false).then(function (ctx) {
            return ctx.parent.removeEntry(ctx.name);
        });
    }

    function ensureLayout() {
        var created = [];
        return getOpfsRoot().then(function () {
            var dirChain = Promise.resolve();
            DIRS.forEach(function (d) {
                dirChain = dirChain.then(function () {
                    return resolveDirectory(d, true).then(function () {
                        created.push({ type: 'dir', path: d });
                    });
                });
            });
            return dirChain;
        }).then(function () {
            var payloads = {
                'runtime/runtime.pkg': PLACEHOLDER_FILES['runtime/runtime.pkg'],
                'runtime/runtime.manifest': defaultManifestBytes(),
                'runtime/active.json': defaultActiveBytes(),
                'database/ratib.sqlite': PLACEHOLDER_FILES['database/ratib.sqlite'],
                'vault/vault.bin': PLACEHOLDER_FILES['vault/vault.bin']
            };
            var fileChain = Promise.resolve();
            Object.keys(payloads).forEach(function (path) {
                fileChain = fileChain.then(function () {
                    return writeBytes(path, payloads[path], {
                        layoutBootstrap: true,
                        createIfAbsent: true
                    }).then(function (res) {
                        created.push({ type: 'file', path: res.path, created: res.created });
                    });
                });
            });
            return fileChain;
        }).then(function () {
            return {
                ok: true,
                layout: LAYOUT_ID,
                opfsRoot: OPFS_APP_ROOT,
                created: created
            };
        });
    }

    function verifyLayout() {
        var missing = [];
        var checkDirs = Promise.resolve();
        DIRS.forEach(function (d) {
            checkDirs = checkDirs.then(function () {
                return resolveDirectory(d, false).catch(function () {
                    missing.push('dir:' + d);
                });
            });
        });
        return checkDirs.then(function () {
            var files = Object.keys(PLACEHOLDER_FILES);
            var checkFiles = Promise.resolve();
            files.forEach(function (f) {
                checkFiles = checkFiles.then(function () {
                    return readFile(f).catch(function () {
                        missing.push('file:' + f);
                    });
                });
            });
            return checkFiles;
        }).then(function () {
            return {
                ok: missing.length === 0,
                layout: LAYOUT_ID,
                missing: missing
            };
        });
    }

    function appendLog(line) {
        var name = 'host-' + new Date().toISOString().slice(0, 10) + '.log';
        var path = 'logs/' + name;
        var stamp = new Date().toISOString() + ' ' + String(line) + '\n';
        return readFile(path).catch(function () {
            return new Uint8Array(0);
        }).then(function (prev) {
            var add = utf8Encode(stamp);
            var merged = new Uint8Array(prev.length + add.length);
            merged.set(prev, 0);
            merged.set(add, prev.length);
            return writeBytes(path, merged, {});
        }).then(function () {
            return { ok: true, path: path };
        });
    }

    function clearTemp() {
        return resolveDirectory('temp', true).then(function (dir) {
            if (!dir.keys) {
                return { ok: true, cleared: 0, note: 'iterator_unavailable' };
            }
            return (async function () {
                var n = 0;
                for await (var key of dir.keys()) {
                    try {
                        await dir.removeEntry(key, { recursive: true });
                        n += 1;
                    } catch (e0) { /* ignore */ }
                }
                return { ok: true, cleared: n };
            })();
        }).catch(function () {
            return { ok: true, cleared: 0 };
        });
    }

    function getQuota() {
        if (!root.navigator || !root.navigator.storage || !root.navigator.storage.estimate) {
            return Promise.resolve({ ok: false, error: 'estimate_unavailable' });
        }
        return root.navigator.storage.estimate().then(function (est) {
            return { ok: true, usage: est.usage || 0, quota: est.quota || 0 };
        });
    }

    function requestPersistence() {
        if (!root.navigator || !root.navigator.storage || !root.navigator.storage.persist) {
            return Promise.resolve({ ok: false, persisted: false, error: 'persist_unavailable' });
        }
        return root.navigator.storage.persist().then(function (persisted) {
            return { ok: true, persisted: !!persisted };
        });
    }

    function getReachability() {
        var online = typeof root.navigator === 'undefined' ? true : !!root.navigator.onLine;
        return {
            online: online,
            note: 'Signal only — must never gate Offline V2 host boot'
        };
    }

    function isInstalledDisplay() {
        try {
            return !!(root.matchMedia && (
                root.matchMedia('(display-mode: standalone)').matches ||
                root.matchMedia('(display-mode: minimal-ui)').matches ||
                root.matchMedia('(display-mode: window-controls-overlay)').matches ||
                root.navigator.standalone === true
            ));
        } catch (e) {
            return false;
        }
    }

    function getLayoutSpec() {
        return {
            id: LAYOUT_ID,
            opfsAppRoot: OPFS_APP_ROOT,
            topLevel: TOP_LEVEL.slice(),
            directories: DIRS.slice(),
            files: Object.keys(PLACEHOLDER_FILES),
            slots: SLOTS.slice(),
            packageTypes: PACKAGE_TYPES.slice(),
            sqliteRelPath: SQLITE_REL_PATH,
            sqliteOpfsPath: OPFS_APP_ROOT + '/' + SQLITE_REL_PATH
        };
    }

    function getSqliteRelPath() {
        return SQLITE_REL_PATH;
    }

    function getSqliteOpfsPath() {
        return OPFS_APP_ROOT + '/' + SQLITE_REL_PATH;
    }

    /** Persist full SQLite DB bytes to database/ratib.sqlite (HCI-only). */
    function persistSqliteBytes(bytes) {
        return writeBytes(SQLITE_REL_PATH, bytes, { sqlitePersist: true });
    }

    /** Read current SQLite file bytes (may be empty placeholder). */
    function readSqliteBytes() {
        return readFile(SQLITE_REL_PATH);
    }

    /** Copy current DB file into backups/ with label. */
    function backupSqliteFile(label) {
        var safe = String(label || 'manual').replace(/[^a-zA-Z0-9._-]/g, '_');
        var dest = 'backups/ratib-' + safe + '-' + Date.now() + '.sqlite';
        return readSqliteBytes().then(function (bytes) {
            if (!bytes || !bytes.length) {
                throw new Error('hci_sqlite_empty');
            }
            return writeBytes(dest, bytes, {}).then(function () {
                return { ok: true, path: dest, size: bytes.length };
            });
        });
    }

    /** Restore DB bytes from a backups/ path (recovery foundation). */
    function restoreSqliteFromBackup(backupRelPath) {
        var p = normalizeRelPath(backupRelPath);
        if (p.indexOf('backups/') !== 0) {
            return Promise.reject(new Error('hci_restore_not_backup'));
        }
        return readFile(p).then(function (bytes) {
            if (!bytes || !bytes.length) {
                throw new Error('hci_backup_empty');
            }
            return persistSqliteBytes(bytes).then(function () {
                return { ok: true, restoredFrom: p, size: bytes.length };
            });
        });
    }

    var HCI = {
        __locked: true,
        version: HCI_VERSION,
        phase: 3,
        layoutId: LAYOUT_ID,
        opfsAppRoot: OPFS_APP_ROOT,
        SLOTS: SLOTS.slice(),
        PACKAGE_TYPES: PACKAGE_TYPES.slice(),
        getOpfsRoot: getOpfsRoot,
        ensureLayout: ensureLayout,
        verifyLayout: verifyLayout,
        readFile: readFile,
        readJson: readJson,
        writeBytes: writeBytes,
        writeJson: writeJson,
        writeFileCreateIfAbsent: writeFileCreateIfAbsent,
        removeFile: removeFile,
        readActivePointer: readActivePointer,
        sha256Hex: sha256Hex,
        appendLog: appendLog,
        clearTemp: clearTemp,
        getQuota: getQuota,
        requestPersistence: requestPersistence,
        getReachability: getReachability,
        isSecureContext: function () { return !!root.isSecureContext; },
        isInstalledDisplay: isInstalledDisplay,
        getLayoutSpec: getLayoutSpec,
        getSqliteRelPath: getSqliteRelPath,
        getSqliteOpfsPath: getSqliteOpfsPath,
        persistSqliteBytes: persistSqliteBytes,
        readSqliteBytes: readSqliteBytes,
        backupSqliteFile: backupSqliteFile,
        restoreSqliteFromBackup: restoreSqliteFromBackup,
        assertWritable: assertWritable,
        resolveDirectory: resolveDirectory
    };

    root.RatebOfflineV2HCI = HCI;
})(typeof window !== 'undefined' ? window : self);
