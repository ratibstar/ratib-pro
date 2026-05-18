/**
 * Profile buttons → company profile (/profile/).
 * Brand tab, platform pill, mega-nav card, footer link.
 */
(function ratibProfileNavGuardJs() {
    'use strict';
    if (window.__ratibProfileNavGuard) {
        return;
    }
    window.__ratibProfileNavGuard = 1;

    function profileUrl() {
        var o = window.location.origin || '';
        return o ? o + '/profile/' : '/profile/';
    }

    function isProfileAnchor(a) {
        if (!a || !a.matches) {
            return false;
        }
        if (
            a.matches(
                '.ratib-nav__brand-profile, .ratib-nav__link--about, .ratib-nav__go-profile, [data-ratib-profile-nav], [data-ratib-go-profile], .ratib-footer-link--about'
            )
        ) {
            return true;
        }
        if (a.matches('a.ratib-mega-nav__card')) {
            var t = a.querySelector('.ratib-mega-nav__card-title');
            if (t && /company profile/i.test(t.textContent || '')) {
                return true;
            }
        }
        return false;
    }

    function findProfileLink(ev) {
        var t = ev.target;
        if (t && t.closest) {
            var hit = t.closest('a');
            if (hit && isProfileAnchor(hit)) {
                return hit;
            }
        }
        var x = ev.clientX;
        var y = ev.clientY;
        if (typeof x !== 'number' || typeof y !== 'number') {
            return null;
        }
        var links = document.querySelectorAll(
            '.ratib-nav__brand-profile, .ratib-nav__link--about, .ratib-nav__go-profile, [data-ratib-profile-nav], [data-ratib-go-profile], .ratib-footer-link--about, a.ratib-mega-nav__card'
        );
        for (var i = 0; i < links.length; i++) {
            var el = links[i];
            if (!isProfileAnchor(el)) {
                continue;
            }
            var r = el.getBoundingClientRect();
            if (x >= r.left - 4 && x <= r.right + 4 && y >= r.top - 4 && y <= r.bottom + 4) {
                return el;
            }
        }
        return null;
    }

    var PROFILE = profileUrl();

    function fixHrefs() {
        document
            .querySelectorAll(
                '.ratib-nav__brand-profile, .ratib-nav__link--about, .ratib-nav__go-profile, [data-ratib-profile-nav], [data-ratib-go-profile], .ratib-footer-link--about'
            )
            .forEach(function (a) {
                a.setAttribute('href', PROFILE);
                a.setAttribute('data-ratib-profile-nav', '1');
                a.setAttribute('data-ratib-go-profile', '1');
            });
        document.querySelectorAll('a.ratib-mega-nav__card').forEach(function (card) {
            var t = card.querySelector('.ratib-mega-nav__card-title');
            if (t && /company profile/i.test(t.textContent || '')) {
                card.setAttribute('href', PROFILE);
                card.setAttribute('data-ratib-go-profile', '1');
            }
        });
    }

    function goProfile(ev) {
        var a = findProfileLink(ev);
        if (!a) {
            return;
        }
        ev.preventDefault();
        ev.stopImmediatePropagation();
        window.location.assign(PROFILE);
    }

    function runFix() {
        fixHrefs();
        if (document.body && document.body.classList.contains('ratib-about-page')) {
            document
                .querySelectorAll('.ratib-nav__brand-profile, .ratib-nav__link--about')
                .forEach(function (a) {
                    a.classList.add('is-current');
                    a.setAttribute('aria-current', 'page');
                });
            document
                .querySelectorAll('.ratib-nav__platform-links .ratib-nav__link')
                .forEach(function (a) {
                    if (!a.classList.contains('ratib-nav__link--about')) {
                        a.classList.remove('is-current');
                        a.removeAttribute('aria-current');
                    }
                });
        }
    }

    runFix();
    document.addEventListener('DOMContentLoaded', runFix);
    setTimeout(runFix, 0);
    setTimeout(runFix, 250);
    setTimeout(runFix, 1000);

    document.addEventListener('mousedown', goProfile, true);
    document.addEventListener('click', goProfile, true);
})();
