/*!
 * RATEB Platform Cutover Prep — Capability Probe (B1-Prep G8)
 * OPFS / WASM / SQLite support. Unsupported → force Offline V1.
 */
(function (root) {
    'use strict';

    if (root.RatebPlatformCapabilityProbe && root.RatebPlatformCapabilityProbe.__locked) {
        return;
    }

    function hasWasm() {
        try {
            return typeof root.WebAssembly === 'object'
                && typeof root.WebAssembly.instantiate === 'function';
        } catch (e) {
            return false;
        }
    }

    function hasOpfs() {
        try {
            return !!(root.isSecureContext
                && root.navigator
                && root.navigator.storage
                && typeof root.navigator.storage.getDirectory === 'function');
        } catch (e2) {
            return false;
        }
    }

    function probeOpfsWritable() {
        if (!hasOpfs()) {
            return Promise.resolve({ ok: false, reason: 'opfs_api_missing' });
        }
        return root.navigator.storage.getDirectory().then(function (rootDir) {
            return rootDir.getDirectoryHandle('rateb-cutover-probe', { create: true }).then(function (dir) {
                return dir.getFileHandle('probe.txt', { create: true }).then(function (fh) {
                    return fh.createWritable().then(function (w) {
                        return w.write('ok').then(function () {
                            return w.close();
                        });
                    }).then(function () {
                        return { ok: true, reason: 'opfs_writable' };
                    });
                });
            });
        }).catch(function (err) {
            return {
                ok: false,
                reason: 'opfs_write_failed',
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    function probeSqliteVendor(vendorIndexUrl) {
        if (!vendorIndexUrl) {
            return Promise.resolve({ ok: hasWasm(), reason: hasWasm() ? 'wasm_only' : 'wasm_missing' });
        }
        function interpret(res) {
            if (!res || !res.ok) {
                return { ok: false, reason: 'vendor_http_' + (res ? res.status : 0) };
            }
            return { ok: hasWasm(), reason: hasWasm() ? 'vendor_reachable' : 'wasm_missing' };
        }
        /* Prefer HEAD; fall back to GET (some hosts reject HEAD on static .mjs). */
        return root.fetch(vendorIndexUrl, {
            method: 'HEAD',
            credentials: 'same-origin',
            cache: 'no-cache'
        }).then(function (res) {
            if (res && (res.ok || res.status === 405 || res.status === 501)) {
                if (res.ok) {
                    return interpret(res);
                }
                return root.fetch(vendorIndexUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-cache',
                    headers: { 'Range': 'bytes=0-0' }
                }).then(interpret);
            }
            return interpret(res);
        }).catch(function () {
            return root.fetch(vendorIndexUrl, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-cache',
                headers: { 'Range': 'bytes=0-0' }
            }).then(interpret).catch(function (err) {
                return {
                    ok: false,
                    reason: 'vendor_fetch_failed',
                    error: String(err && err.message ? err.message : err)
                };
            });
        });
    }

    function run(opts) {
        opts = opts || {};
        var wasm = hasWasm();
        var opfsApi = hasOpfs();
        return Promise.all([
            probeOpfsWritable(),
            probeSqliteVendor(opts.vendorIndexUrl || null)
        ]).then(function (parts) {
            var opfs = parts[0];
            var sqlite = parts[1];
            var supported = !!(wasm && opfs.ok && sqlite.ok);
            return {
                ok: supported,
                forceOfflineV1: !supported,
                checks: {
                    wasm: { ok: wasm },
                    opfs: opfs,
                    sqliteVendor: sqlite,
                    secureContext: { ok: !!root.isSecureContext }
                },
                at: new Date().toISOString()
            };
        });
    }

    root.RatebPlatformCapabilityProbe = {
        __locked: true,
        version: '1.0.0-b1-prep',
        hasWasm: hasWasm,
        hasOpfs: hasOpfs,
        run: run
    };
})(typeof window !== 'undefined' ? window : this);
