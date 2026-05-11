/**
 * Mega navigation: click/tap to toggle panels, Escape and outside-click to close.
 * No dependencies. Works with includes/ratib-mega-nav-render.php markup.
 */
(function ratibMegaNavInit() {
    var root =
        document.getElementById('ratibMegaNavRoot') ||
        document.querySelector('.ratib-mega-nav[data-ratib-mega-nav="1"]');
    if (!root) {
        return;
    }

    var items = Array.prototype.slice.call(root.querySelectorAll('.ratib-mega-nav__li--mega'));
    if (!items.length) {
        return;
    }

    function closeAll() {
        items.forEach(function (li) {
            li.classList.remove('is-open');
            var btn = li.querySelector('.ratib-mega-nav__trigger');
            var panel = li.querySelector('.ratib-mega-nav__panel');
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
        var btn = li.querySelector('.ratib-mega-nav__trigger');
        var panel = li.querySelector('.ratib-mega-nav__panel');
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
        var btn = li.querySelector('.ratib-mega-nav__trigger');
        if (!btn) {
            return;
        }

        btn.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            toggleItem(li);
        });

        btn.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                closeAll();
                btn.focus();
            }
        });
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
