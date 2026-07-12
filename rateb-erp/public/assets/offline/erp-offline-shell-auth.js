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

    function publicBase() {
        try {
            var p = String(root.location.pathname || '');
            var m = p.match(/^(.*\/public\/)/i);
            if (m && m[1]) {
                return m[1];
            }
        } catch (e) { /* ignore */ }
        return '/rateb-erp/public/';
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

    function brandOfflineStatus() {
        var statusBox = $('offline-status');
        if (!statusBox || statusBox.querySelector('[data-rateb-brand]')) {
            return;
        }
        var brand = root.document.createElement('div');
        brand.setAttribute('data-rateb-brand', '1');
        brand.textContent = 'RATEB ERP';
        brand.style.cssText = 'font:700 1.5rem/1.2 system-ui,Segoe UI,sans-serif;letter-spacing:.04em;'
            + 'color:#8ab4ff;margin:0 0 .5rem;';
        statusBox.insertBefore(brand, statusBox.firstChild);
    }

    /** Snapshot HTML keeps <link> in <head>; restore must import those or the shell looks unstyled. */
    function injectStylesFromDoc(doc) {
        if (!doc || !root.document || !root.document.head) {
            return;
        }
        var links = doc.querySelectorAll('link[rel="stylesheet"], link[rel="preload"][as="style"]');
        Array.prototype.forEach.call(links, function (link) {
            var href = link.getAttribute('href') || '';
            if (!href || /^javascript:/i.test(href)) {
                return;
            }
            if (root.document.head.querySelector('link[data-rateb-offline-css="' + href.replace(/"/g, '') + '"]')) {
                return;
            }
            var el = root.document.createElement('link');
            el.rel = 'stylesheet';
            el.href = href;
            el.setAttribute('data-rateb-offline-css', href);
            root.document.head.appendChild(el);
        });
        // Fallback core ERP CSS if snapshot had none (old captures).
        if (!links.length) {
            var base = publicBase();
            [
                'assets/css/variables.css',
                'assets/css/main.css',
                'assets/css/components.css',
                'assets/css/dark.css',
                'assets/css/rtl.css'
            ].forEach(function (rel) {
                var href = base + rel;
                if (root.document.head.querySelector('link[data-rateb-offline-css="' + href + '"]')) {
                    return;
                }
                var el = root.document.createElement('link');
                el.rel = 'stylesheet';
                el.href = href;
                el.setAttribute('data-rateb-offline-css', href);
                root.document.head.appendChild(el);
            });
        }
    }

    function forceOfflineBadge() {
        try {
            var nodes = root.document.querySelectorAll(
                '.rateb-connection-indicator, [data-rateb-connection-status], #rateb-connection-indicator'
            );
            Array.prototype.forEach.call(nodes, function (el) {
                el.classList.remove('is-online');
                el.classList.add('is-offline');
                el.setAttribute('title', 'غير متصل');
                el.setAttribute('aria-label', 'غير متصل');
                var label = el.querySelector('.rateb-connection-indicator__label');
                if (label) {
                    label.textContent = 'غير متصل';
                }
            });
            root.document.querySelectorAll('.rateb-topbar *, header *, .navbar *').forEach(function (el) {
                if (el.children && el.children.length) {
                    return;
                }
                var t = (el.textContent || '').trim();
                if (t === 'متصل' || t === 'Online') {
                    el.textContent = 'غير متصل';
                }
            });
            root.document.querySelectorAll(
                '#rateb-modal, .rateb-modal, [data-rateb-confirm], .rateb-confirm, '
                + '#rateb-loading, .rateb-loading, [data-rateb-attachments]'
            ).forEach(function (el) {
                try { el.remove(); } catch (e) { /* ignore */ }
            });
        } catch (e2) { /* ignore */ }
    }

    function fillModuleHomeFromNav() {
        var host = root.document.getElementById('rateb-offline-module-links');
        if (!host) {
            return;
        }
        var links = root.document.querySelectorAll(
            '.rateb-offline-rbac-link, aside.rateb-sidebar a[href], aside.rateb-offline-shell-nav a[href], #rateb-sidebar a[href]'
        );
        var seen = {};
        var items = [];
        Array.prototype.forEach.call(links, function (a) {
            var href = (a.getAttribute('href') || '').trim();
            if (!href || href === '#' || /^javascript:/i.test(href)) {
                return;
            }
            if (seen[href]) {
                return;
            }
            seen[href] = true;
            items.push({
                href: href,
                label: (a.textContent || '').replace(/\s+/g, ' ').trim() || href
            });
        });
        if (!items.length) {
            host.innerHTML = '<p class="text-muted">لا توجد وحدات محفوظة للتصفح أوفلاين بعد. ادخل أونلاين مرة (مع قائمة النظام ظاهرة) ثم أعد المحاولة أوفلاين.</p>';
            return;
        }
        var html = '<div class="list-group">';
        items.forEach(function (it) {
            html += '<a class="list-group-item list-group-item-action" href="'
                + String(it.href).replace(/"/g, '&quot;') + '">'
                + String(it.label).replace(/</g, '&lt;') + '</a>';
        });
        html += '</div>';
        host.innerHTML = html;
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
        injectStylesFromDoc(doc);
        while (shellRoot.firstChild) shellRoot.removeChild(shellRoot.firstChild);
        Array.prototype.forEach.call(doc.body.childNodes, function (node) {
            if (node.nodeType === 1 || node.nodeType === 3) {
                shellRoot.appendChild(document.importNode(node, true));
            }
        });
        shellRoot.hidden = false;
        if (statusBox) statusBox.hidden = true;
        root.document.body.classList.add('rateb-offline-shell-active');
        forceOfflineBadge();
        ensureOfflineHomeHost();
        return shellRoot.childNodes.length > 0;
    }

    function ensureOfflineHomeHost() {
        var main = root.document.getElementById('rateb-offline-shell-main')
            || root.document.querySelector('.rateb-offline-shell-main, main.rateb-content, main');
        if (!main) {
            return;
        }
        if (!root.document.getElementById('rateb-offline-module-links')) {
            var wrap = root.document.createElement('div');
            wrap.className = 'container py-4 rateb-offline-home';
            wrap.innerHTML = '<h2 class="h4 mb-2">وضع عدم الاتصال</h2>'
                + '<p class="text-muted mb-3">القائمة والصفحات المحفوظة متاحة للتصفح. البيانات الحية والتعديل يحتاجان اتصالاً.</p>'
                + '<div id="rateb-offline-module-links" class="rateb-offline-module-links"></div>';
            // Prefer replacing empty placeholder content
            main.innerHTML = '';
            main.appendChild(wrap);
        }
        var aside = root.document.querySelector('aside.rateb-sidebar, aside.rateb-offline-shell-nav, aside');
        if (aside) {
            aside.classList.add('rateb-sidebar', 'rateb-offline-shell-nav');
            if (!aside.id) {
                aside.id = 'rateb-sidebar';
            }
        }
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
        return rbac.applyCachedNav({ requireDeviceActive: false }).then(function (res) {
            forceOfflineBadge();
            fillModuleHomeFromNav();
            return res;
        });
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
        brandOfflineStatus();
        // Paint unlock copy immediately — do not leave "loading identity" spinner stuck.
        showMsg('فتح أوفلاين', 'أدخل رمز PIN لفتح الهوية الدافئة والعمل بنفس واجهة النظام.');

        var scope = readScope();
        if (!scope.company_id || !scope.user_id) {
            showMsg(
                'الواجهة المحفوظة غير متاحة',
                'يلزم نطاق المستأجر (company_id + user_id). افتح الإدارة وأنت متصل مرة واحدة للتسجيل.'
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
                startConnectivity: true,
                startScheduler: false
            });
        }

        // If the device is already online on offline-shell.html, jump back to live Admin.
        try {
            if (root.navigator && root.navigator.onLine !== false) {
                var last = '';
                try { last = root.localStorage.getItem('rateb_erp_offline_last_url') || ''; } catch (eL) { /* ignore */ }
                var target = last;
                if (!target || !/\/rateb-erp\/public\//i.test(target)) {
                    target = publicBase() + 'admin/?company_id=' + encodeURIComponent(String(scope.company_id || ''));
                }
                root.setTimeout(function () {
                    if (root.navigator && root.navigator.onLine === false) {
                        return;
                    }
                    try {
                        var key = 'rateb_erp_live_reload_at';
                        var prev = parseInt(root.sessionStorage.getItem(key) || '0', 10) || 0;
                        if (prev > 0 && (Date.now() - prev) < 15000) {
                            return;
                        }
                        root.sessionStorage.setItem(key, String(Date.now()));
                    } catch (eS) { /* ignore */ }
                    root.location.href = target;
                }, 600);
            }
        } catch (eOnline) { /* ignore */ }

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
                if (typeof lock.landAfterUnlock === 'function') {
                    try { lock.landAfterUnlock(); } catch (eLand0) { /* ignore */ }
                }
                proceed();
                return;
            }
            showMsg('فتح أوفلاين', 'أدخل رمز PIN لفتح الهوية الدافئة والعمل بنفس واجهة النظام.');
            lock.requireUnlockIfNeeded().then(function (res) {
                if (res && res.ok) {
                    if (typeof lock.landAfterUnlock === 'function') {
                        try { lock.landAfterUnlock(); } catch (eLand1) { /* ignore */ }
                    }
                    proceed();
                    return;
                }
                root.addEventListener('rateb:offline-unlocked', function onUnlock() {
                    root.removeEventListener('rateb:offline-unlocked', onUnlock);
                    if (typeof lock.landAfterUnlock === 'function') {
                        try { lock.landAfterUnlock(); } catch (eLand2) { /* ignore */ }
                    }
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
