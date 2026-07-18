(function () {
    'use strict';

    var DATE_TYPES = ['date', 'datetime-local', 'time', 'month', 'week'];

    function formatHint(type) {
        var body = document.body;
        if (!body) {
            return '';
        }
        var key = 'data-rateb-date-hint-' + (type === 'datetime-local' ? 'datetime' : type);
        return body.getAttribute(key) || '';
    }

    function normalizeInput(input) {
        input.classList.add('rateb-ltr-date', 'rateb-date-input');
        if (input.classList.contains('form-control') && !input.classList.contains('rateb-form-control')) {
            input.classList.add('rateb-form-control');
        }
        input.setAttribute('dir', 'ltr');
        input.setAttribute('lang', 'en');
        if (!input.getAttribute('autocomplete')) {
            input.setAttribute('autocomplete', 'off');
        }
    }

    function syncEmpty(wrap, input) {
        wrap.setAttribute('data-empty', input.value ? '0' : '1');
    }

    function bindWrap(wrap, input) {
        if (wrap.getAttribute('data-rateb-date-bound') === '1') {
            syncEmpty(wrap, input);
            return;
        }
        wrap.setAttribute('data-rateb-date-bound', '1');
        var type = (input.getAttribute('type') || 'date').toLowerCase();
        if (!wrap.getAttribute('data-format-hint')) {
            var hint = formatHint(type);
            if (hint) {
                wrap.setAttribute('data-format-hint', hint);
            }
        }
        var icon = wrap.querySelector('.rateb-date-wrap-icon');
        if (icon && !icon.getAttribute('data-rateb-bound')) {
            icon.setAttribute('data-rateb-bound', '1');
            icon.addEventListener('click', function (e) {
                e.preventDefault();
                openPicker(input);
            });
        }
        syncEmpty(wrap, input);
        input.addEventListener('input', function () {
            syncEmpty(wrap, input);
        });
        input.addEventListener('change', function () {
            syncEmpty(wrap, input);
        });
    }

    function openPicker(input) {
        if (typeof input.showPicker === 'function') {
            try {
                input.showPicker();
                return;
            } catch (err) {
                /* fall through */
            }
        }
        input.focus();
    }

    function wrapDateInput(input) {
        if (!input || input.getAttribute('type') === 'hidden') {
            return;
        }
        if (input.getAttribute('data-acc-locale-managed') === '1') {
            return;
        }
        var type = (input.getAttribute('type') || '').toLowerCase();
        if (DATE_TYPES.indexOf(type) === -1) {
            return;
        }

        normalizeInput(input);

        var existing = input.closest('.rateb-date-wrap');
        if (existing) {
            bindWrap(existing, input);
            return;
        }

        var wrap = document.createElement('div');
        wrap.className = 'rateb-date-wrap';
        wrap.setAttribute('data-date-type', type);
        var hint = formatHint(type);
        if (hint) {
            wrap.setAttribute('data-format-hint', hint);
        }

        var parent = input.parentNode;
        if (!parent) {
            return;
        }
        parent.insertBefore(wrap, input);
        wrap.appendChild(input);

        var icon = document.createElement('button');
        icon.type = 'button';
        icon.className = 'rateb-date-wrap-icon';
        icon.setAttribute('tabindex', '-1');
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = '<i class="fas fa-calendar-alt"></i>';
        wrap.insertBefore(icon, input);

        bindWrap(wrap, input);
    }

    function initRatebDateInputs(root) {
        var scope = root || document;
        DATE_TYPES.forEach(function (type) {
            scope.querySelectorAll('input[type="' + type + '"]').forEach(wrapDateInput);
        });
    }

    window.ratebInitDateInputs = initRatebDateInputs;

    document.addEventListener('DOMContentLoaded', function () {
        var observeRoot = document.getElementById('rateb-main-content') || document.body;
        initRatebDateInputs(observeRoot || document);
        if (typeof MutationObserver === 'undefined' || !observeRoot) {
            return;
        }
        var timer = null;
        var observer = new MutationObserver(function () {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(function () {
                initRatebDateInputs(observeRoot);
            }, 80);
        });
        observer.observe(observeRoot, { childList: true, subtree: true });
        document.addEventListener('rateb:nav:afterEnter', function () {
            var main = document.getElementById('rateb-main-content') || document.body;
            if (main) {
                initRatebDateInputs(main);
            }
        });
    });
})();
