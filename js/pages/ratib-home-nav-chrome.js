/**
 * Sticky public nav: scroll shadow + mobile toggle (subset of home-page.js).
 */
(function ratibHomeNavChrome() {
    var header = document.getElementById('ratib-main-header');
    var toggle = document.getElementById('ratibNavToggle');
    var menu = document.getElementById('ratibNavMenu');
    if (!header) {
        return;
    }
    function onScroll() {
        header.classList.toggle('is-scrolled', window.scrollY > 32);
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    if (toggle && menu) {
        toggle.addEventListener('click', function () {
            var open = menu.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        menu.addEventListener('click', function (ev) {
            if (!ev.target.closest('a')) {
                return;
            }
            menu.classList.remove('is-open');
            toggle.setAttribute('aria-expanded', 'false');
        });
    }
})();
