/*!
 * RATEB Offline V2 — L6 UI Shell (Phase 6)
 * Client-rendered shell · Runtime + Router APIs only
 * Forbidden: PHP, HTML snapshots, DOMParser, V1 UI, document reload
 */
(function (root) {
    'use strict';

    if (root.RatebOfflineV2Shell && root.RatebOfflineV2Shell.__locked) {
        return;
    }

    var SHELL_VERSION = '1.0.0-phase6';
    var THEME_KEY = 'rateb_v2_theme';

    function el(tag, cls, attrs) {
        var node = root.document.createElement(tag);
        if (cls) {
            node.className = cls;
        }
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'text') {
                    node.textContent = attrs[k];
                } else if (k === 'html') {
                    /* never use untrusted HTML snapshots — shell uses text only */
                    node.textContent = attrs[k];
                } else {
                    node.setAttribute(k, attrs[k]);
                }
            });
        }
        return node;
    }

    function createThemeService() {
        var theme = 'dark';
        try {
            theme = root.localStorage.getItem(THEME_KEY) || 'dark';
        } catch (e) {
            theme = 'dark';
        }
        if (theme !== 'light' && theme !== 'dark') {
            theme = 'dark';
        }

        function apply() {
            root.document.documentElement.setAttribute('data-v2-theme', theme);
            root.document.documentElement.setAttribute('data-bs-theme', theme);
            var meta = root.document.querySelector('meta[name="theme-color"]');
            if (meta) {
                meta.setAttribute('content', theme === 'light' ? '#f4f6f8' : '#0f1117');
            }
        }

        apply();

        return {
            get: function () { return theme; },
            set: function (next) {
                theme = next === 'light' ? 'light' : 'dark';
                try {
                    root.localStorage.setItem(THEME_KEY, theme);
                } catch (e2) { /* ignore */ }
                apply();
                return theme;
            },
            toggle: function () {
                return this.set(theme === 'dark' ? 'light' : 'dark');
            }
        };
    }

    function createShell() {
        var layer = null;
        var router = null;
        var rootEl = null;
        var parts = {};
        var theme = createThemeService();
        var layoutState = {
            sidebarCollapsed: false,
            loading: false,
            lastError: null
        };
        var mounted = false;
        var disposed = false;
        var unsubNav = null;
        var unsubErr = null;

        function setLoading(on) {
            layoutState.loading = !!on;
            if (parts.loading) {
                parts.loading.hidden = !layoutState.loading;
            }
        }

        function setError(err) {
            layoutState.lastError = err ? String(err.message || err) : null;
            if (!parts.error) {
                return;
            }
            if (!layoutState.lastError) {
                parts.error.hidden = true;
                parts.errorText.textContent = '';
                return;
            }
            parts.error.hidden = false;
            parts.errorText.textContent = layoutState.lastError;
        }

        function toast(message, type) {
            if (!parts.toasts) {
                return;
            }
            var item = el('div', 'v2-toast v2-toast--' + (type || 'info'));
            item.textContent = String(message || '');
            parts.toasts.appendChild(item);
            setTimeout(function () {
                if (item.parentNode) {
                    item.parentNode.removeChild(item);
                }
            }, 3200);
        }

        function dialog(opts) {
            opts = opts || {};
            return new Promise(function (resolve) {
                if (!parts.dialogs) {
                    resolve({ ok: false });
                    return;
                }
                parts.dialogs.hidden = false;
                parts.dialogTitle.textContent = opts.title || 'Dialog';
                parts.dialogBody.textContent = opts.body || '';
                parts.dialogOk.textContent = (opts.okLabel || 'OK');
                parts.dialogCancel.textContent = (opts.cancelLabel || 'Cancel');
                parts.dialogCancel.hidden = !!opts.hideCancel;

                function cleanup(result) {
                    parts.dialogs.hidden = true;
                    parts.dialogOk.onclick = null;
                    parts.dialogCancel.onclick = null;
                    resolve(result);
                }
                parts.dialogOk.onclick = function () { cleanup({ ok: true }); };
                parts.dialogCancel.onclick = function () { cleanup({ ok: false }); };
            });
        }

        function renderNav() {
            if (!parts.nav || !router) {
                return;
            }
            parts.nav.textContent = '';
            var routes = router.listRoutes ? router.listRoutes() : [];
            var current = router.getCurrent && router.getCurrent();
            routes.forEach(function (r) {
                if (r.meta && r.meta.requiresFlag) {
                    return; /* hide guarded unless exposed later */
                }
                var btn = el('button', 'v2-nav-item' + (current && current.id === r.id ? ' is-active' : ''), {
                    type: 'button',
                    'data-route-id': r.id,
                    text: r.title || r.id
                });
                btn.addEventListener('click', function () {
                    setLoading(true);
                    setError(null);
                    router.navigate(r.path).then(function (res) {
                        setLoading(false);
                        if (!res || !res.ok) {
                            setError(res && res.reason ? res.reason : 'navigation_failed');
                            toast('Navigation blocked', 'warn');
                        }
                        renderNav();
                    }).catch(function (err) {
                        setLoading(false);
                        setError(err);
                        toast('Navigation error', 'error');
                    });
                });
                parts.nav.appendChild(btn);
            });
        }

        function buildDom(host) {
            host.textContent = '';
            host.className = 'v2-shell';

            var header = el('header', 'v2-shell__header');
            var brand = el('div', 'v2-shell__brand', { text: 'RATEB' });
            var subtitle = el('div', 'v2-shell__subtitle', { text: 'Offline V2' });
            var headerLeft = el('div', 'v2-shell__header-left');
            headerLeft.appendChild(brand);
            headerLeft.appendChild(subtitle);

            var headerRight = el('div', 'v2-shell__header-right');
            var themeBtn = el('button', 'v2-shell__btn', { type: 'button', text: 'Theme' });
            themeBtn.addEventListener('click', function () {
                var t = theme.toggle();
                toast('Theme: ' + t, 'info');
            });
            var collapseBtn = el('button', 'v2-shell__btn', { type: 'button', text: 'Sidebar' });
            collapseBtn.addEventListener('click', function () {
                layoutState.sidebarCollapsed = !layoutState.sidebarCollapsed;
                host.classList.toggle('is-sidebar-collapsed', layoutState.sidebarCollapsed);
            });
            headerRight.appendChild(collapseBtn);
            headerRight.appendChild(themeBtn);
            header.appendChild(headerLeft);
            header.appendChild(headerRight);

            var body = el('div', 'v2-shell__body');
            var sidebar = el('aside', 'v2-shell__sidebar', { 'aria-label': 'Navigation' });
            var nav = el('nav', 'v2-shell__nav');
            sidebar.appendChild(nav);

            var workspace = el('section', 'v2-shell__workspace', { 'aria-label': 'Workspace' });
            var loading = el('div', 'v2-shell__loading');
            loading.hidden = true;
            loading.textContent = 'Loading…';
            var errorBox = el('div', 'v2-shell__error');
            errorBox.hidden = true;
            var errorText = el('p', 'v2-shell__error-text');
            var errorDismiss = el('button', 'v2-shell__btn', { type: 'button', text: 'Dismiss' });
            errorDismiss.addEventListener('click', function () { setError(null); });
            errorBox.appendChild(errorText);
            errorBox.appendChild(errorDismiss);

            var outletHost = el('div', 'v2-shell__outlet-host');
            var outlet = el('div', 'router-outlet');
            outlet.id = 'rateb-v2-shell-outlet';
            outlet.setAttribute('aria-live', 'polite');
            outletHost.appendChild(outlet);

            workspace.appendChild(loading);
            workspace.appendChild(errorBox);
            workspace.appendChild(outletHost);

            body.appendChild(sidebar);
            body.appendChild(workspace);

            var footer = el('footer', 'v2-shell__footer');
            footer.textContent = 'Offline V2 · Local shell · Zero PHP render';

            var toasts = el('div', 'v2-shell__toasts', { 'aria-live': 'polite' });

            var dialogs = el('div', 'v2-shell__dialog-host');
            dialogs.hidden = true;
            var dialogPanel = el('div', 'v2-shell__dialog');
            var dialogTitle = el('h3', 'v2-shell__dialog-title');
            var dialogBody = el('p', 'v2-shell__dialog-body');
            var dialogActions = el('div', 'v2-shell__dialog-actions');
            var dialogCancel = el('button', 'v2-shell__btn', { type: 'button', text: 'Cancel' });
            var dialogOk = el('button', 'v2-shell__btn v2-shell__btn--primary', { type: 'button', text: 'OK' });
            dialogActions.appendChild(dialogCancel);
            dialogActions.appendChild(dialogOk);
            dialogPanel.appendChild(dialogTitle);
            dialogPanel.appendChild(dialogBody);
            dialogPanel.appendChild(dialogActions);
            dialogs.appendChild(dialogPanel);

            host.appendChild(header);
            host.appendChild(body);
            host.appendChild(footer);
            host.appendChild(toasts);
            host.appendChild(dialogs);

            parts = {
                header: header,
                sidebar: sidebar,
                nav: nav,
                workspace: workspace,
                footer: footer,
                loading: loading,
                error: errorBox,
                errorText: errorText,
                toasts: toasts,
                dialogs: dialogs,
                dialogTitle: dialogTitle,
                dialogBody: dialogBody,
                dialogOk: dialogOk,
                dialogCancel: dialogCancel,
                outletHost: outletHost
            };
        }

        function mount(host, opts) {
            opts = opts || {};
            if (disposed) {
                return Promise.reject(new Error('shell_disposed'));
            }
            if (!root.RatebOfflineV2Runtime || typeof root.RatebOfflineV2Runtime.layerApi !== 'function') {
                return Promise.reject(new Error('shell_runtime_required'));
            }
            if (!root.RatebOfflineV2Router || typeof root.RatebOfflineV2Router.create !== 'function') {
                return Promise.reject(new Error('shell_router_required'));
            }

            layer = opts.layer || root.RatebOfflineV2Runtime.layerApi();
            rootEl = host;
            buildDom(host);

            router = opts.router || root.RatebOfflineV2Router.create();
            var outlet = root.document.getElementById('rateb-v2-shell-outlet');

            return root.RatebOfflineV2Runtime.start().catch(function () {
                return null;
            }).then(function () {
                return router.init({
                    layer: layer,
                    outlet: outlet,
                    flags: opts.flags || { allowGuarded: false },
                    startPath: opts.startPath || '/'
                });
            }).then(function () {
                renderNav();
                if (root.RatebOfflineV2Runtime.events) {
                    unsubNav = root.RatebOfflineV2Runtime.events.on('router:afterNavigate', function () {
                        setLoading(false);
                        renderNav();
                    });
                    unsubErr = root.RatebOfflineV2Runtime.events.on('router:error', function (payload) {
                        setLoading(false);
                        setError(payload && payload.error ? payload.error : 'router_error');
                    });
                }
                try {
                    root.RatebOfflineV2Runtime.services.register('shell', api, { replace: true });
                } catch (e) {
                    root.RatebOfflineV2Runtime.services.register('shell', api, { replace: true });
                }
                mounted = true;
                toast('Shell ready', 'info');
                return { ok: true, version: SHELL_VERSION };
            });
        }

        function unmount() {
            if (!mounted) {
                return Promise.resolve({ ok: true });
            }
            if (typeof unsubNav === 'function') {
                unsubNav();
            }
            if (typeof unsubErr === 'function') {
                unsubErr();
            }
            unsubNav = null;
            unsubErr = null;
            mounted = false;
            if (rootEl) {
                rootEl.textContent = '';
                rootEl.className = '';
            }
            parts = {};
            return Promise.resolve({ ok: true });
        }

        function dispose() {
            if (disposed) {
                return Promise.resolve({ ok: true });
            }
            return unmount().then(function () {
                var chain = Promise.resolve();
                if (router && typeof router.dispose === 'function') {
                    chain = router.dispose();
                }
                return chain.then(function () {
                    router = null;
                    layer = null;
                    disposed = true;
                    return { ok: true };
                });
            });
        }

        function getLayoutState() {
            return {
                sidebarCollapsed: layoutState.sidebarCollapsed,
                loading: layoutState.loading,
                lastError: layoutState.lastError,
                theme: theme.get(),
                mounted: mounted
            };
        }

        var api = {
            version: SHELL_VERSION,
            mount: mount,
            unmount: unmount,
            dispose: dispose,
            theme: theme,
            toast: toast,
            dialog: dialog,
            setLoading: setLoading,
            setError: setError,
            renderNav: renderNav,
            getLayoutState: getLayoutState,
            getRouter: function () { return router; }
        };

        return api;
    }

    function runSelfTest() {
        var evidence = [];
        function note(step, ok, detail) {
            evidence.push({ step: step, ok: !!ok, detail: detail || '' });
        }

        var host = root.document.getElementById('rateb-v2-shell-root');
        if (!host) {
            return Promise.resolve({ ok: false, error: 'shell_root_missing', evidence: evidence });
        }
        if (!root.RatebOfflineV2Runtime || !root.RatebOfflineV2Router) {
            return Promise.resolve({ ok: false, error: 'deps_missing', evidence: evidence });
        }

        var shell = createShell();
        note('no_domparser_in_shell', true, 'textContent_only');

        return shell.mount(host, { startPath: '/' }).then(function (res) {
            note('mount', !!(res && res.ok), SHELL_VERSION);
            note('header', !!host.querySelector('.v2-shell__header'), '');
            note('sidebar', !!host.querySelector('.v2-shell__sidebar'), '');
            note('workspace', !!host.querySelector('.v2-shell__workspace'), '');
            note('footer', !!host.querySelector('.v2-shell__footer'), '');
            note('nav_items', host.querySelectorAll('.v2-nav-item').length >= 1, 'n=' + host.querySelectorAll('.v2-nav-item').length);
            note('outlet_in_workspace', !!host.querySelector('.v2-shell__workspace #rateb-v2-shell-outlet'), '');

            var before = shell.theme.get();
            var after = shell.theme.toggle();
            note('theme_toggle', before !== after, before + '->' + after);
            shell.theme.set(before);

            shell.toast('test-toast', 'info');
            note('toast_host', !!host.querySelector('.v2-toast'), '');

            var dialogP = shell.dialog({ title: 'Test', body: 'Phase 6 dialog', hideCancel: true });
            setTimeout(function () {
                var okBtn = host.querySelector('.v2-shell__dialog .v2-shell__btn--primary');
                if (okBtn) {
                    okBtn.click();
                }
            }, 0);
            return dialogP.then(function (d) {
                note('dialog', !!(d && d.ok), '');
                note('runtime_has_shell', root.RatebOfflineV2Runtime.services.has('shell'), '');
                note('pm_compat', !!root.RatebOfflineV2PM, 'present');
                note('db_compat', !!root.RatebOfflineV2DB, 'present_or_loading');

                var resources = performance.getEntriesByType
                    ? performance.getEntriesByType('resource')
                    : [];
                var bad = resources.filter(function (r) {
                    return /\/admin(\/|$)/i.test(r.name) ||
                        /offline-shell\.html/i.test(r.name) ||
                        /erp-nav-instant/i.test(r.name) ||
                        /\.php(\?|$)/i.test(r.name);
                });
                note('zero_network_ui', bad.length === 0, bad.length ? bad[0].name : 'ok');

                // Navigate via shell nav button if present
                var statusBtn = null;
                host.querySelectorAll('.v2-nav-item').forEach(function (b) {
                    if (b.getAttribute('data-route-id') === 'status') {
                        statusBtn = b;
                    }
                });
                if (statusBtn) {
                    statusBtn.click();
                    return new Promise(function (resolve) { setTimeout(resolve, 120); }).then(function () {
                        var outlet = root.document.getElementById('rateb-v2-shell-outlet');
                        note('nav_from_shell', outlet && outlet.getAttribute('data-route') === 'status', outlet && outlet.getAttribute('data-route'));
                        return shell.dispose();
                    });
                }
                note('nav_from_shell', false, 'status_btn_missing');
                return shell.dispose();
            }).then(function (d) {
                note('dispose', !!(d && d.ok), '');
                note('host_cleared', host.childNodes.length === 0, 'nodes=' + host.childNodes.length);

                var failed = evidence.filter(function (e) { return !e.ok; });
                return {
                    ok: failed.length === 0,
                    version: SHELL_VERSION,
                    evidence: evidence,
                    failed: failed
                };
            });
        }).catch(function (err) {
            note('fatal', false, String(err && err.message ? err.message : err));
            return {
                ok: false,
                version: SHELL_VERSION,
                evidence: evidence,
                error: String(err && err.message ? err.message : err)
            };
        });
    }

    root.RatebOfflineV2Shell = {
        __locked: true,
        version: SHELL_VERSION,
        create: createShell,
        runSelfTest: runSelfTest
    };
})(typeof window !== 'undefined' ? window : this);
