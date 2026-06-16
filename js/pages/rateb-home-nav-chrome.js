/**
 * Sticky public nav: scroll shadow + mobile toggle (subset of home-page.js).
 */
(function ratebHomeNavChrome() {
    var header = document.getElementById('rateb-main-header');
    var toggle = document.getElementById('ratebNavToggle');
    var menu = document.getElementById('ratebNavMenu');
    var headerPin = document.getElementById('rateb-public-header-pin');

    function syncPublicHeaderHeight() {
        if (!headerPin) {
            return;
        }
        var pinHeight = headerPin.offsetHeight;
        document.documentElement.style.setProperty('--rateb-public-header-h', pinHeight + 'px');
        var profileBanner = document.querySelector('[data-rateb-profile-distinct="1"]');
        var bannerHeight = profileBanner ? profileBanner.offsetHeight : 0;
        document.documentElement.style.setProperty('--rateb-profile-banner-h', bannerHeight + 'px');
    }

    syncPublicHeaderHeight();
    window.addEventListener('resize', syncPublicHeaderHeight);
    window.addEventListener('load', syncPublicHeaderHeight);
    if (typeof ResizeObserver !== 'undefined' && headerPin) {
        var ro = new ResizeObserver(syncPublicHeaderHeight);
        ro.observe(headerPin);
        var profileBanner = document.querySelector('[data-rateb-profile-distinct="1"]');
        if (profileBanner) {
            ro.observe(profileBanner);
        }
    }

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
