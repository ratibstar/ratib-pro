/**
 * Phase P1 — Offline shell warm unlock host.
 * Loads after rateb-offline.js; shows PIN unlock, then chrome + RBAC.
 */
(function (root) {
    'use strict';

    var SNAPSHOT_PREFIX = 'erp_shell_chrome';
    var SCOPE_KEY = 'rateb_erp_offline_scope';
    var DB_NAME = 'rateb_erp_offline';

    function $(id) {
        return root.document.getElementById(id);
    }

    function readScope() {
        var cfg = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        var scope = {
            company_id: parseInt(cfg.company_id, 10) || 0,
            branch_id: parseInt(cfg.branch_id, 10) || 0,
            user_id: parseInt(cfg.user_id, 10) || 0,
            auth_unlock: !!(cfg.flags && cfg.flags['offline.auth.unlock'])
        };
        if (!scope.company_id || !scope.user_id) {
            try {
                var raw = root.localStorage.getItem(SCOPE_KEY);
                if (raw) {
                    var o = JSON.parse(raw);
                    scope.company_id = parseInt(o.company_id, 10) || 0;
                    scope.branch_id = parseInt(o.branch_id, 10) || 0;
                    scope.user_id = parseInt(o.user_id, 10) || 0;
                    scope.auth_unlock = !!(o.auth_unlock || (o.flags && o.flags['offline.auth.unlock']));
                    root.__RATEB_ERP_SHELL_OFFLINE__ = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
                    root.__RATEB_ERP_SHELL_OFFLINE__.company_id = scope.company_id;
                    root.__RATEB_ERP_SHELL_OFFLINE__.tenant_id = parseInt(o.tenant_id || o.company_id, 10) || scope.company_id;
                    root.__RATEB_ERP_SHELL_OFFLINE__.branch_id = scope.branch_id;
                    root.__RATEB_ERP_SHELL_OFFLINE__.user_id = scope.user_id;
                    root.__RATEB_ERP_SHELL_OFFLINE__.is_super_admin = !!o.is_super_admin;
                    root.__RATEB_ERP_SHELL_OFFLINE__.flags = root.__RATEB_ERP_SHELL_OFFLINE__.flags || o.flags || {
                        'offline.enabled': true,
                        'offline.read_cache': true,
                        'offline.auth.unlock': !!scope.auth_unlock,
                        'offline.rbac.cache': !!(o.flags && o.flags['offline.rbac.cache'])
                    };
                }
            } catch (e) { /* ignore */ }
        }
        return scope;
    }

    function showMsg(titleText, msgText) {
        var title = $('title');
        var msg = $('msg');
        var statusBox = $('offline-status');
        var shellRoot = $('shell-root');
        if (title) title.textContent = titleText;
        if (msg) msg.textContent = msgText;
        if (statusBox) statusBox.hidden = false;
        if (shellRoot) {
            shellRoot.hidden = true;
            while (shellRoot.firstChild) shellRoot.removeChild(shellRoot.firstChild);
        }
    }

    function renderSafeShell(html) {
        var shellRoot = $('shell-root');
        var statusBox = $('offline-status');
        if (!html || typeof DOMParser === 'undefined' || !shellRoot) return false;
        var parser = new DOMParser();
        var doc = parser.parseFromString(String(html), 'text/html');
        if (!doc || !doc.body) return false;
        var marker = doc.getElementById('rateb-offline-shell-main')
            || doc.querySelector('.rateb-offline-shell-main');
        if (!marker) return false;
        doc.querySelectorAll('script, iframe, object, embed, link[rel="import"]').forEach(function (el) {
            el.remove();
        });
        doc.querySelectorAll('*').forEach(function (el) {
            var attrs = el.attributes ? Array.prototype.slice.call(el.attributes) : [];
            attrs.forEach(function (attr) {
                var name = String(attr.name || '');
                var val = String(attr.value || '');
                if (/^on/i.test(name)) {
                    el.removeAttribute(name);
                } else if ((name === 'href' || name === 'xlink:href' || name === 'src') && /^\s*javascript:/i.test(val)) {
                    el.removeAttribute(name);
                } else if (/^data-rateb-/i.test(name) || /^(data-csrf|data-token|data-session)/i.test(name)) {
                    el.removeAttribute(name);
                }
            });
        });
        while (shellRoot.firstChild) shellRoot.removeChild(shellRoot.firstChild);
        Array.prototype.forEach.call(doc.body.childNodes, function (node) {
            if (node.nodeType === 1 || node.nodeType === 3) {
                shellRoot.appendChild(document.importNode(node, true));
            }
        });
        shellRoot.hidden = false;
        if (statusBox) statusBox.hidden = true;
        return shellRoot.childNodes.length > 0;
    }

    function loadSnapshot(scope) {
        var id = SNAPSHOT_PREFIX + ':' + scope.company_id + ':' + (scope.branch_id || 0) + ':' + scope.user_id;
        if (!root.indexedDB) {
            showMsg('Cached shell unavailable', 'IndexedDB unavailable.');
            return Promise.resolve(false);
        }
        return new Promise(function (resolve) {
            var req = root.indexedDB.open(DB_NAME);
            req.onerror = function () {
                showMsg('Cached shell unavailable', 'Unable to open offline database.');
                resolve(false);
            };
            req.onsuccess = function () {
                try {
                    var db = req.result;
                    if (!db.objectStoreNames.contains('snapshots')) {
                        showMsg('Cached shell unavailable', 'No snapshots store.');
                        resolve(false);
                        return;
                    }
                    var tx = db.transaction('snapshots', 'readonly');
                    var get = tx.objectStore('snapshots').get(id);
                    get.onerror = function () {
                        showMsg('Cached shell unavailable', 'Snapshot read failed.');
                        resolve(false);
                    };
                    get.onsuccess = function () {
                        var row = get.result;
                        if (!row || !row.html || !renderSafeShell(row.html)) {
                            showMsg(
                                'Cached shell unavailable',
                                'No cached chrome for this user. Reconnect, open Admin once, then retry offline.'
                            );
                            resolve(false);
                            return;
                        }
                        resolve(true);
                    };
                } catch (e) {
                    showMsg('Cached shell unavailable', 'Snapshot error.');
                    resolve(false);
                }
            };
        });
    }

    function applyRbac() {
        var rbac = root.RatebOfflineRbacCache;
        if (!rbac || typeof rbac.applyCachedNav !== 'function') {
            return Promise.resolve({ ok: false });
        }
        return rbac.applyCachedNav({ requireDeviceActive: true });
    }

    function waitForAuthLock(tries) {
        tries = tries || 0;
        if (root.RatebOfflineAuthLock) {
            return Promise.resolve(root.RatebOfflineAuthLock);
        }
        // Cap ~500ms (was 2s) — SDK already loaded before this host script.
        if (tries > 10) {
            return Promise.resolve(null);
        }
        return new Promise(function (resolve) {
            root.setTimeout(function () {
                resolve(waitForAuthLock(tries + 1));
            }, 50);
        });
    }

    function boot() {
        // Paint unlock copy immediately — do not leave "loading identity" spinner stuck.
        showMsg('Unlock required', 'Enter your offline PIN to open the warm ERP identity.');

        var scope = readScope();
        if (!scope.company_id || !scope.user_id) {
            showMsg(
                'Cached shell unavailable',
                'Tenant scope (company_id + user_id) required. Open Admin online once to enroll.'
            );
            return;
        }
        root.__RATEB_ERP_SHELL_OFFLINE__ = root.__RATEB_ERP_SHELL_OFFLINE__ || {};
        root.__RATEB_ERP_SHELL_OFFLINE__.flags = root.__RATEB_ERP_SHELL_OFFLINE__.flags || {
            'offline.enabled': true,
            'offline.read_cache': true,
            'offline.auth.unlock': !!scope.auth_unlock,
            'offline.rbac.cache': true
        };
        if (root.RatebOffline && typeof root.RatebOffline.init === 'function') {
            root.RatebOffline.init({
                flags: root.__RATEB_ERP_SHELL_OFFLINE__.flags,
                startConnectivity: false,
                startScheduler: false
            });
        }

        waitForAuthLock().then(function (lock) {
            var unlockNeeded = !!(root.__RATEB_ERP_SHELL_OFFLINE__.flags['offline.auth.unlock']);
            var proceed = function () {
                if (lock && lock.touchIdle) {
                    lock.touchIdle(scope);
                    if (root.document) {
                        ['mousemove', 'keydown', 'touchstart', 'click'].forEach(function (ev) {
                            root.document.addEventListener(ev, function () {
                                lock.touchIdle(scope);
                            }, { passive: true });
                        });
                    }
                }
                return loadSnapshot(scope).then(function (ok) {
                    if (ok) {
                        return applyRbac();
                    }
                    return { ok: false };
                });
            };
            if (!unlockNeeded || !lock) {
                proceed();
                return;
            }
            if (lock.isUnlocked && lock.isUnlocked()) {
                proceed();
                return;
            }
            showMsg('Unlock required', 'Enter your offline PIN to open the warm ERP identity.');
            lock.requireUnlockIfNeeded().then(function (res) {
                if (res && res.ok) {
                    proceed();
                    return;
                }
                root.addEventListener('rateb:offline-unlocked', function onUnlock() {
                    root.removeEventListener('rateb:offline-unlocked', onUnlock);
                    proceed();
                });
            });
        });
    }

    if (root.document && root.document.readyState === 'loading') {
        root.document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})(typeof window !== 'undefined' ? window : globalThis);
