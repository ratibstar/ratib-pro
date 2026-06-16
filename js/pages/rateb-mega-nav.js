/**
 * Mega navigation: click/tap to toggle panels, Escape and outside-click to close.
 * Profile links + legacy pill row: js/pages/rateb-public-nav-ia-fix.js (loaded from chrome).
 */
(function ratebMegaNavInit() {
    var root =
        document.getElementById('ratebMegaNavRoot') ||
        document.querySelector('.rateb-mega-nav[data-rateb-mega-nav="1"]');
    if (!root) {
        return;
    }

    var items = Array.prototype.slice.call(root.querySelectorAll('.rateb-mega-nav__li--mega'));
    if (!items.length) {
        return;
    }

    function closeAll() {
        items.forEach(function (li) {
            li.classList.remove('is-open');
            var btn = li.querySelector('.rateb-mega-nav__trigger');
            var panel = li.querySelector('.rateb-mega-nav__panel');
            if (btn) {
                btn.setAttribute('aria-expanded', 'false');
            }
            if (panel) {
                panel.setAttribute('hidden', 'hidden');
            }
        });
    }

    function openItem(li) {
        closeAll();
        li.classList.add('is-open');
        var btn = li.querySelector('.rateb-mega-nav__trigger');
        var panel = li.querySelector('.rateb-mega-nav__panel');
        if (btn) {
            btn.setAttribute('aria-expanded', 'true');
        }
        if (panel) {
            panel.removeAttribute('hidden');
        }
    }

    function toggleItem(li) {
        if (li.classList.contains('is-open')) {
            closeAll();
        } else {
            openItem(li);
        }
    }

    items.forEach(function (li) {
        var btn = li.querySelector('button.rateb-mega-nav__trigger');
        var triggerEl = btn && btn.getAttribute('id') ? btn : li.querySelector('.rateb-mega-nav__trigger-label');
        if (!triggerEl) {
            return;
        }

        function onActivate(ev) {
            if (ev.target && ev.target.closest && ev.target.closest('.rateb-mega-nav__panel')) {
                return;
            }
            ev.preventDefault();
            ev.stopPropagation();
            toggleItem(li);
        }

        triggerEl.addEventListener('click', onActivate);
        if (triggerEl !== btn && btn) {
            btn.addEventListener('click', onActivate);
        }

        if (btn && btn.addEventListener) {
            btn.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape') {
                    closeAll();
                    btn.focus();
                }
            });
        }
    });

    document.addEventListener('click', function (ev) {
        if (!root.contains(ev.target)) {
            closeAll();
        }
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') {
            closeAll();
        }
    });
})();
