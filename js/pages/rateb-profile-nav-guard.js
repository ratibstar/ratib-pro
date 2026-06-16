/**
 * Profile links → /profile/#company-profile (plain href only, same tab).
 */
(function ratebProfileNavGuardJs() {
    'use strict';
    if (window.__ratebProfileNavGuard) {
        return;
    }
    window.__ratebProfileNavGuard = 1;

    function profileUrl() {
        var o = window.location.origin || '';
        return o ? o + '/profile/#company-profile' : '/profile/#company-profile';
    }

    function fixHrefs() {
        var PROFILE = profileUrl();
        document
            .querySelectorAll(
                '.rateb-nav__brand-profile, .rateb-nav__link--about, .rateb-nav__go-profile, [data-rateb-profile-nav], [data-rateb-go-profile], .rateb-footer-link--about'
            )
            .forEach(function (a) {
                a.setAttribute('href', PROFILE);
                a.removeAttribute('target');
                a.removeAttribute('rel');
                a.removeAttribute('onclick');
            });
        document.querySelectorAll('a.rateb-mega-nav__card').forEach(function (card) {
            var t = card.querySelector('.rateb-mega-nav__card-title');
            if (t && /company profile/i.test(t.textContent || '')) {
                card.setAttribute('href', PROFILE);
                card.removeAttribute('target');
                card.removeAttribute('rel');
                card.removeAttribute('onclick');
            }
        });
    }

    fixHrefs();
    document.addEventListener('DOMContentLoaded', fixHrefs);
    setTimeout(fixHrefs, 0);
    setTimeout(fixHrefs, 500);
})();
