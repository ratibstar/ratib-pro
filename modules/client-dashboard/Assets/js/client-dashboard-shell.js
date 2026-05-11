(function () {
    var shell = document.querySelector('[data-ratib-client-shell]');
    if (!shell) return;

    function isMobileLayout() {
        return window.matchMedia('(max-width: 1024px)').matches;
    }

    var openBtn = document.querySelector('[data-ratib-cp-open-sidebar]');
    var backdrop = document.querySelector('[data-ratib-cp-backdrop]');

    function setOpen(open) {
        document.body.classList.toggle('is-ratib-cp-mobile-open', open);
        if (openBtn) {
            openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            openBtn.setAttribute(
                'aria-label',
                open ? 'Close sidebar' : 'Open sidebar'
            );
        }
        if (!open && document.activeElement && shell.contains(document.activeElement)) {
            document.getElementById('ratib-cp-main').focus({ preventScroll: true });
        }
    }

    if (openBtn) {
        openBtn.addEventListener('click', function () {
            var willOpen = !document.body.classList.contains('is-ratib-cp-mobile-open');
            setOpen(willOpen);
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setOpen(false);
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            setOpen(false);
        }
        if (
            document.body.classList.contains('is-ratib-cp-mobile-open') &&
            e.key === 'Tab' &&
            isMobileLayout()
        ) {
            var sidebar = document.getElementById('ratib-cp-sidebar');
            if (!sidebar) return;
            var focusables = sidebar.querySelectorAll(
                'a[href], button:not([disabled])'
            );
            if (!focusables.length) return;
            var first = focusables[0];
            var last = focusables[focusables.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    window.addEventListener('resize', function () {
        if (!isMobileLayout()) {
            document.body.classList.remove('is-ratib-cp-mobile-open');
            if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
        }
    });

    shell.setAttribute(
        'data-ratib-cp-loaded',
        new Date().getTime().toString()
    );
})();
