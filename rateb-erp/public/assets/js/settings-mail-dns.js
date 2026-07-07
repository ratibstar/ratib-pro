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

    function loadDnsPanel(host) {
        var url = host.getAttribute('data-mail-dns-url');
        if (!url) {
            return;
        }
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.ok || !data.html) {
                    host.innerHTML = '<p class="text-danger small mb-0">' + (host.getAttribute('data-mail-dns-fail') || 'DNS check failed') + '</p>';
                    return;
                }
                host.outerHTML = data.html;
                bindCopyButtons(document);
            })
            .catch(function () {
                host.innerHTML = '<p class="text-danger small mb-0">' + (host.getAttribute('data-mail-dns-fail') || 'DNS check failed') + '</p>';
            });
    }

    function boot() {
        document.querySelectorAll('[data-mail-dns-async]').forEach(loadDnsPanel);
        bindCopyButtons(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
