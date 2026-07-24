/**
 * Async mail DNS panel — never hang the Admin UI.
 * Soft-nav safe: boot on afterEnter + ratebMailDnsBoot from erp-nav-instant.
 */
(function (root) {
    'use strict';

    if (root.__RATEB_MAIL_DNS_BOUND__) {
        if (typeof root.ratebMailDnsBoot === 'function') {
            root.ratebMailDnsBoot({ immediate: true });
        }
        return;
    }
    root.__RATEB_MAIL_DNS_BOUND__ = true;

    function bindCopyButtons(scope) {
        (scope || document).querySelectorAll('[data-copy-target]').forEach(function (btn) {
            if (btn.getAttribute('data-copy-bound') === '1') {
                return;
            }
            btn.setAttribute('data-copy-bound', '1');
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-copy-target');
                var el = id ? document.getElementById(id) : null;
                if (!el) {
                    return;
                }
                var text = typeof el.value === 'string' ? el.value : (el.textContent || '');
                if (el.select) {
                    el.select();
                    if (el.setSelectionRange) {
                        el.setSelectionRange(0, 99999);
                    }
                }
                try {
                    navigator.clipboard.writeText(text);
                } catch (e) {
                    document.execCommand('copy');
                }
            });
        });
    }

    function failHtml(host, msg) {
        var fail = host.getAttribute('data-mail-dns-fail') || 'DNS check failed';
        var url = host.getAttribute('data-mail-dns-url') || '';
        return '<p class="text-warning small mb-2">' + (msg || fail) + '</p>'
            + (url
                ? '<button type="button" class="btn btn-sm btn-outline-secondary" data-mail-dns-retry="1">'
                    + 'Retry DNS check</button>'
                : '');
    }

    function loadDnsPanel(host, force) {
        var url = host.getAttribute('data-mail-dns-url');
        if (!url) {
            return;
        }
        if (!force && host.getAttribute('data-mail-dns-loading') === '1') {
            return;
        }
        host.setAttribute('data-mail-dns-loading', '1');
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = setTimeout(function () {
            if (ctrl) {
                try { ctrl.abort(); } catch (e) { /* ignore */ }
            }
        }, 8000);

        fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            signal: ctrl ? ctrl.signal : undefined
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.ok || !data.html) {
                    host.innerHTML = failHtml(host);
                    bindRetry(host);
                    return;
                }
                host.outerHTML = data.html;
                bindCopyButtons(document);
            })
            .catch(function () {
                host.innerHTML = failHtml(host);
                bindRetry(host);
            })
            .finally(function () {
                clearTimeout(timer);
                try {
                    host.removeAttribute('data-mail-dns-loading');
                } catch (eFin) { /* ignore */ }
            });
    }

    function bindRetry(host) {
        var btn = host.querySelector('[data-mail-dns-retry]');
        if (!btn || btn.getAttribute('data-retry-bound') === '1') {
            return;
        }
        btn.setAttribute('data-retry-bound', '1');
        btn.addEventListener('click', function () {
            host.innerHTML = '<p class="text-muted small mb-0">Checking DNS…</p>';
            host.removeAttribute('data-mail-dns-loading');
            loadDnsPanel(host, true);
        });
    }

    function boot(opts) {
        var immediate = !!(opts && opts.immediate);
        var run = function () {
            document.querySelectorAll('[data-mail-dns-async]').forEach(function (host) {
                if (immediate) {
                    host.removeAttribute('data-mail-dns-loading');
                    loadDnsPanel(host, true);
                } else {
                    loadDnsPanel(host, false);
                }
            });
            bindCopyButtons(document);
        };
        if (immediate) {
            run();
            return;
        }
        if (root.requestIdleCallback) {
            root.requestIdleCallback(run, { timeout: 2500 });
        } else {
            setTimeout(run, 400);
        }
    }

    root.ratebMailDnsBoot = boot;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { boot(); });
    } else {
        boot();
    }
    document.addEventListener('rateb:nav:afterEnter', function () {
        boot({ immediate: true });
    });
})(typeof window !== 'undefined' ? window : this);
