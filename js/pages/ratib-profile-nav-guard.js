/**
 * Both Profile buttons → company profile (/profile).
 * Works when nav has pointer-events:none (clicks pass through to hit-test).
 */
(function ratibProfileNavGuardJs() {
    'use strict';
    if (window.__ratibProfileNavGuard) {
        return;
    }
    window.__ratibProfileNavGuard = 1;

    function profileUrl() {
        var o = window.location.origin || '';
        return o ? o + '/profile' : '/profile';
    }

    function findProfileLink(ev) {
        var t = ev.target;
        if (t && t.closest) {
            var hit = t.closest(
                '.ratib-nav__brand-profile, .ratib-nav__link--about, [data-ratib-profile-nav]'
            );
            if (hit) {
                return hit;
            }
        }
        var x = ev.clientX;
        var y = ev.clientY;
        if (typeof x !== 'number' || typeof y !== 'number') {
            return null;
        }
        var links = document.querySelectorAll(
            '.ratib-nav__brand-profile, .ratib-nav__link--about, [data-ratib-profile-nav]'
        );
        for (var i = 0; i < links.length; i++) {
            var el = links[i];
            var r = el.getBoundingClientRect();
            if (x >= r.left && x <= r.right && y >= r.top && y <= r.bottom) {
                return el;
            }
        }
        return null;
    }

    var PROFILE = profileUrl();

    function fixHrefs() {
        document
            .querySelectorAll(
                '.ratib-nav__brand-profile, .ratib-nav__link--about, [data-ratib-profile-nav]'
            )
            .forEach(function (a) {
                a.setAttribute('href', PROFILE);
                a.setAttribute('data-ratib-profile-nav', '1');
            });
    }

    fixHrefs();
    document.addEventListener('DOMContentLoaded', fixHrefs);

    document.addEventListener(
        'click',
        function (ev) {
            var a = findProfileLink(ev);
            if (!a) {
                return;
            }
            ev.preventDefault();
            ev.stopImmediatePropagation();
            window.location.assign(PROFILE);
        },
        true
    );
})();
