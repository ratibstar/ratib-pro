/**
 * Async mail DNS panel — never hang the Admin UI.
 * Auto-load with hard client timeout; fall back to manual retry.
 */
(function () {
    'use strict';

    function bindCopyButtons(root) {
        (root || document).querySelectorAll('[data-copy-target]').forEach(function (btn) {
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

    function loadDnsPanel(host) {
        var url = host.getAttribute('data-mail-dns-url');
        if (!url || host.getAttribute('data-mail-dns-loading') === '1') {
            return;
        }
        host.setAttribute('data-mail-dns-loading', '1');
        var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timer = setTimeout(function () {
            if (ctrl) {
                try { ctrl.abort(); } catch (e) { /* ignore */ }
            }
        }, 4000);

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
                host.removeAttribute('data-mail-dns-loading');
            });
    }

    function bindRetry(host) {
        var btn = host.querySelector('[data-mail-dns-retry]');
        if (!btn) {
            return;
        }
        btn.addEventListener('click', function () {
            host.innerHTML = '<p class="text-muted small mb-0">Checking DNS…</p>';
            loadDnsPanel(host);
        });
    }

    function boot() {
        // Defer DNS until idle so settings page paints and stays clickable first.
        var run = function () {
            document.querySelectorAll('[data-mail-dns-async]').forEach(loadDnsPanel);
            bindCopyButtons(document);
        };
        if (window.requestIdleCallback) {
            window.requestIdleCallback(run, { timeout: 2500 });
        } else {
            setTimeout(run, 800);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    document.addEventListener('rateb:nav:afterEnter', function () {
        setTimeout(boot, 300);
    });
})();
