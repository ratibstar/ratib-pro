/**
 * Profile links → /profile/#company-profile (plain href only, same tab).
 */
(function ratibProfileNavGuardJs() {
    'use strict';
    if (window.__ratibProfileNavGuard) {
        return;
    }
    window.__ratibProfileNavGuard = 1;

    function profileUrl() {
        var o = window.location.origin || '';
        return o ? o + '/profile/#company-profile' : '/profile/#company-profile';
    }

    function fixHrefs() {
        var PROFILE = profileUrl();
        document
            .querySelectorAll(
                '.ratib-nav__brand-profile, .ratib-nav__link--about, .ratib-nav__go-profile, [data-ratib-profile-nav], [data-ratib-go-profile], .ratib-footer-link--about'
            )
            .forEach(function (a) {
                a.setAttribute('href', PROFILE);
                a.removeAttribute('target');
                a.removeAttribute('rel');
                a.removeAttribute('onclick');
            });
        document.querySelectorAll('a.ratib-mega-nav__card').forEach(function (card) {
            var t = card.querySelector('.ratib-mega-nav__card-title');
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
