/**
 * Public nav IA v3: one mega-nav row (matches profile). Strips legacy pill row and
 * replaces cached Company/Sites/Grow mega markup with a fresh server fragment.
 */
(function ratibPublicNavIaFix() {
    'use strict';
    if (window.__ratibNavIaFixLoaded) {
        return;
    }
    window.__ratibNavIaFixLoaded = true;

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

    function stripNavBrandTagline() {
        var blk = document.querySelector('.ratib-nav__brand-block--animated');
        if (!blk) {
            return;
        }
        blk.querySelectorAll(
            '.ratib-brand-full__tagline, .ratib-brand-full__tagline-row, ' +
                '.ratib-brand-full__word--w1, .ratib-brand-full__word--w2, .ratib-brand-full__word--w3, ' +
                '.ratib-brand-full__word--w4, .ratib-brand-full__word--w5, .ratib-brand-full__word--amp'
        ).forEach(function (el) {
            el.remove();
        });
    }

    function megaNavTriggerLabels(root) {
        var labels = [];
        if (!root) {
            return labels;
        }
        root.querySelectorAll('.ratib-mega-nav__trigger-label').forEach(function (el) {
            labels.push((el.textContent || '').trim().toLowerCase());
        });
        return labels;
    }

    function isLegacyMegaNav(root) {
        if (document.querySelector('.ratib-nav__platform-links')) {
            return true;
        }
        if (!root) {
            return false;
        }
        if (
            root.querySelector(
                '[data-ratib-mega-id="company"],[data-ratib-mega-id="sites"],[data-ratib-mega-id="grow"],' +
                    '[data-ratib-mega-id="websites"],[data-ratib-mega-id="marketing"],[data-ratib-mega-id="security"]'
            )
        ) {
            return true;
        }
        var labels = megaNavTriggerLabels(root);
        if (labels.indexOf('sites') >= 0 || labels.indexOf('grow') >= 0) {
            return true;
        }
        if (labels.indexOf('company') >= 0 && labels.indexOf('solutions') < 0) {
            return true;
        }
        if (labels.length >= 4 && labels.indexOf('domains') < 0) {
            return true;
        }
        return false;
    }

    function fragmentUrls() {
        var qs = 'ratib_mega_nav_fragment=1';
        var rev = document.body && document.body.getAttribute('data-ratib-home-ui-rev');
        if (rev) {
            qs += '&ui=' + encodeURIComponent(rev);
        }
        return ['/home?' + qs, '/pages/home.php?' + qs];
    }

    function fetchMegaNavFragment() {
        var urls = fragmentUrls();
        var i = 0;
        function tryNext() {
            if (i >= urls.length) {
                return Promise.reject(new Error('nav fragment unavailable'));
            }
            var url = urls[i++];
            return fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
                if (!r.ok) {
                    return tryNext();
                }
                return r.text();
            });
        }
        return tryNext();
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
        stripNavBrandTagline();

        var root = document.getElementById('ratibMegaNavRoot');
        var onProfile =
            document.body &&
            (document.body.classList.contains('ratib-about-page') ||
                document.body.getAttribute('data-ratib-about') === '1');

        if (!onProfile && isLegacyMegaNav(root)) {
            nav.removeAttribute('data-ratib-nav-sync');
            fetchMegaNavFragment()
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
