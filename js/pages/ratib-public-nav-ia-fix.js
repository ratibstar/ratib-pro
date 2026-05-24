/**
 * Public nav IA v3: one mega-nav row (matches profile). Strips legacy pill row and
 * replaces cached Company/Sites/Grow mega markup with a fresh server fragment.
 */
(function ratibPublicNavIaFix() {
    'use strict';

    var PROFILE =
        typeof window.ratibProfileNavUrl === 'string' && window.ratibProfileNavUrl
            ? window.ratibProfileNavUrl
            : (window.location.origin || '') + '/profile/#company-profile';

    function wireProfileLink(a) {
        if (!a) {
            return;
        }
        a.setAttribute('href', PROFILE);
        a.setAttribute('data-ratib-profile-nav', '1');
        a.setAttribute('data-ratib-go-profile', '1');
        a.removeAttribute('target');
        a.removeAttribute('rel');
        var oc = a.getAttribute('onclick');
        if (oc && /window\.open/i.test(oc)) {
            a.removeAttribute('onclick');
        }
    }

    function wireAllProfileLinks() {
        document
            .querySelectorAll(
                '.ratib-nav__brand-profile,.ratib-nav__link--about,[data-ratib-profile-nav],[data-ratib-go-profile]'
            )
            .forEach(wireProfileLink);
        document.querySelectorAll('a.ratib-mega-nav__card').forEach(function (card) {
            var t = card.querySelector('.ratib-mega-nav__card-title');
            if (t && /company profile/i.test(t.textContent || '')) {
                wireProfileLink(card);
            }
        });
    }

    function removeLegacyPlatformRow() {
        var pills = document.querySelector('.ratib-nav__platform-links');
        if (pills) {
            pills.remove();
        }
    }

    function isLegacyMegaNav(root) {
        if (document.querySelector('.ratib-nav__platform-links')) {
            return true;
        }
        if (!root) {
            return false;
        }
        return !!root.querySelector(
            '[data-ratib-mega-id="company"],[data-ratib-mega-id="sites"],[data-ratib-mega-id="grow"],' +
                '[data-ratib-mega-id="websites"],[data-ratib-mega-id="marketing"],[data-ratib-mega-id="security"]'
        );
    }

    function fragmentUrl() {
        var path = window.location.pathname || '/home';
        if (!/\/home(\.php)?$/i.test(path) && !/\/pages\/home\.php$/i.test(path)) {
            path = '/home';
        }
        var qs = 'ratib_mega_nav_fragment=1';
        var rev = document.body && document.body.getAttribute('data-ratib-home-ui-rev');
        if (rev) {
            qs += '&ui=' + encodeURIComponent(rev);
        }
        return path + (path.indexOf('?') >= 0 ? '&' : '?') + qs;
    }

    function initMegaNavPanels(root) {
        if (!root) {
            return;
        }
        var items = root.querySelectorAll('.ratib-mega-nav__li--mega');
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
        items.forEach(function (li) {
            var btn = li.querySelector('button.ratib-mega-nav__trigger');
            if (!btn) {
                return;
            }
            btn.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                if (li.classList.contains('is-open')) {
                    closeAll();
                } else {
                    closeAll();
                    li.classList.add('is-open');
                    btn.setAttribute('aria-expanded', 'true');
                    var panel = li.querySelector('.ratib-mega-nav__panel');
                    if (panel) {
                        panel.removeAttribute('hidden');
                    }
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
    }

    function applyFreshMegaNav(html) {
        var nav = document.getElementById('ratibNavMenu');
        if (!nav) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var fresh = wrap.querySelector('#ratibMegaNavRoot');
        var old = document.getElementById('ratibMegaNavRoot');
        if (fresh && old) {
            old.replaceWith(fresh);
            initMegaNavPanels(fresh);
        }
    }

    function finishNavSync(nav) {
        nav.setAttribute('data-ratib-nav-sync', '1');
        nav.style.visibility = 'visible';
        nav.style.opacity = '1';
        nav.style.pointerEvents = 'auto';
    }

    function run() {
        var nav = document.getElementById('ratibNavMenu');
        if (!nav) {
            return;
        }

        wireAllProfileLinks();
        removeLegacyPlatformRow();

        var root = document.getElementById('ratibMegaNavRoot');
        var onProfile =
            document.body &&
            (document.body.classList.contains('ratib-about-page') ||
                document.body.getAttribute('data-ratib-about') === '1');

        if (!onProfile && isLegacyMegaNav(root)) {
            fetch(fragmentUrl(), { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) {
                    return r.text();
                })
                .then(function (html) {
                    if (html && html.indexOf('ratibMegaNavRoot') !== -1) {
                        applyFreshMegaNav(html);
                        wireAllProfileLinks();
                        removeLegacyPlatformRow();
                    }
                    finishNavSync(nav);
                })
                .catch(function () {
                    finishNavSync(nav);
                });
            return;
        }

        finishNavSync(nav);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
    setTimeout(run, 0);
    setTimeout(run, 400);
})();
