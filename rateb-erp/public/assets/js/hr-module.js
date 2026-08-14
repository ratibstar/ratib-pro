(function () {
    'use strict';

    document.querySelectorAll('[data-hr-tree-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var group = btn.closest('[data-hr-tree-group]');
            if (!group) {
                return;
            }
            var open = group.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    /** Phase N — Command Center employee search → Employee 360 */
    function initCommandCenterSearch(root) {
        var input = root.querySelector('[data-hr-cc-search]');
        var results = root.querySelector('[data-hr-cc-results]');
        var lookupUrl = root.getAttribute('data-lookup-url') || '';
        if (!input || !results || !lookupUrl) {
            return;
        }

        var timer = null;
        var emptyLabel = input.getAttribute('data-empty-label') || '—';

        function hide() {
            results.classList.add('d-none');
            results.innerHTML = '';
        }

        function render(items) {
            results.innerHTML = '';
            if (!items || !items.length) {
                var empty = document.createElement('div');
                empty.className = 'p-2 small text-muted';
                empty.textContent = emptyLabel;
                results.appendChild(empty);
                results.classList.remove('d-none');
                return;
            }
            items.forEach(function (item) {
                var a = document.createElement('a');
                a.href = item.url || '#';
                a.setAttribute('role', 'option');
                var name = document.createElement('div');
                name.textContent = item.name || '';
                var code = document.createElement('div');
                code.className = 'small text-muted rateb-ltr-num';
                code.textContent = item.employee_code || '';
                a.appendChild(name);
                a.appendChild(code);
                results.appendChild(a);
            });
            results.classList.remove('d-none');
        }

        function search(q) {
            if (!q || q.length < 1) {
                hide();
                return;
            }
            var url = lookupUrl + (lookupUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q);
            fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (!data || !data.success) {
                        hide();
                        return;
                    }
                    render(data.items || []);
                })
                .catch(function () { hide(); });
        }

        input.addEventListener('input', function () {
            var q = (input.value || '').trim();
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(function () { search(q); }, 220);
        });

        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                hide();
            }
        });

        document.addEventListener('click', function (ev) {
            if (!root.contains(ev.target)) {
                hide();
            }
        });
    }

    document.querySelectorAll('[data-hr-cc]').forEach(initCommandCenterSearch);
})();
