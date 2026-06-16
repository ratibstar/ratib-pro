/**
 * Public nav IA v3: one mega-nav row (matches profile). Strips legacy pill row and
 * replaces cached Company/Sites/Grow mega markup with a fresh server fragment.
 */
(function ratebPublicNavIaFix() {
    'use strict';
    if (window.__ratebNavIaFixLoaded) {
        return;
    }
    window.__ratebNavIaFixLoaded = true;

    var PROFILE =
        typeof window.ratebProfileNavUrl === 'string' && window.ratebProfileNavUrl
            ? window.ratebProfileNavUrl
            : (window.location.origin || '') + '/profile/#company-profile';

    function wireProfileLink(a) {
        if (!a) {
            return;
        }
        a.setAttribute('href', PROFILE);
        a.setAttribute('data-rateb-profile-nav', '1');
        a.setAttribute('data-rateb-go-profile', '1');
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
                '.rateb-nav__brand-profile,.rateb-nav__link--about,[data-rateb-profile-nav],[data-rateb-go-profile]'
            )
            .forEach(wireProfileLink);
        document.querySelectorAll('a.rateb-mega-nav__card').forEach(function (card) {
            var t = card.querySelector('.rateb-mega-nav__card-title');
            if (t && /company profile/i.test(t.textContent || '')) {
                wireProfileLink(card);
            }
        });
    }

    function removeLegacyPlatformRow() {
        var pills = document.querySelector('.rateb-nav__platform-links');
        if (pills) {
            pills.remove();
        }
    }

    function layoutNavBrandRow() {
        var blk = document.querySelector('.rateb-nav__brand-block--animated');
        if (!blk) {
            return;
        }
        blk.classList.add('rateb-nav__brand-block--row');
        blk.style.display = 'flex';
        blk.style.flexDirection = 'row';
        blk.style.alignItems = 'center';
        blk.style.flexWrap = 'nowrap';
        var prof = blk.querySelector('.rateb-nav__brand-profile');
        var brand = blk.querySelector('a.rateb-nav__brand');
        if (prof && brand && prof.previousElementSibling !== brand) {
            brand.insertAdjacentElement('afterend', prof);
        }
    }

    function stripNavBrandTagline() {
        var blk = document.querySelector('.rateb-nav__brand-block--animated');
        if (!blk) {
            return;
        }
        blk.querySelectorAll(
            '.rateb-brand-full__tagline, .rateb-brand-full__tagline-row, ' +
                '.rateb-brand-full__word--w1, .rateb-brand-full__word--w2, .rateb-brand-full__word--w3, ' +
                '.rateb-brand-full__word--w4, .rateb-brand-full__word--w5, .rateb-brand-full__word--amp'
        ).forEach(function (el) {
            el.remove();
        });
    }

    function megaNavTriggerLabels(root) {
        var labels = [];
        if (!root) {
            return labels;
        }
        root.querySelectorAll('.rateb-mega-nav__trigger-label').forEach(function (el) {
            labels.push((el.textContent || '').trim().toLowerCase());
        });
        return labels;
    }

    function isLegacyMegaNav(root) {
        if (document.querySelector('.rateb-nav__platform-links')) {
            return true;
        }
        if (!root) {
            return false;
        }
        if (
            root.querySelector(
                '[data-rateb-mega-id="company"],[data-rateb-mega-id="sites"],[data-rateb-mega-id="grow"],' +
                    '[data-rateb-mega-id="websites"],[data-rateb-mega-id="marketing"],[data-rateb-mega-id="security"]'
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
        var qs = 'rateb_mega_nav_fragment=1';
        var rev = document.body && document.body.getAttribute('data-rateb-home-ui-rev');
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
        var items = root.querySelectorAll('.rateb-mega-nav__li--mega');
        if (!items.length) {
            return;
        }
        function closeAll() {
            items.forEach(function (li) {
                li.classList.remove('is-open');
                var btn = li.querySelector('.rateb-mega-nav__trigger');
                var panel = li.querySelector('.rateb-mega-nav__panel');
                if (btn) {
                    btn.setAttribute('aria-expanded', 'false');
                }
                if (panel) {
                    panel.setAttribute('hidden', 'hidden');
                }
            });
        }
        items.forEach(function (li) {
            var btn = li.querySelector('button.rateb-mega-nav__trigger');
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
                    var panel = li.querySelector('.rateb-mega-nav__panel');
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
        var nav = document.getElementById('ratebNavMenu');
        if (!nav) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var fresh = wrap.querySelector('#ratebMegaNavRoot');
        var old = document.getElementById('ratebMegaNavRoot');
        if (fresh && old) {
            old.replaceWith(fresh);
            initMegaNavPanels(fresh);
        }
    }

    function finishNavSync(nav) {
        nav.setAttribute('data-rateb-nav-sync', '1');
        nav.style.visibility = 'visible';
        nav.style.opacity = '1';
        nav.style.pointerEvents = 'auto';
    }

    function run() {
        var nav = document.getElementById('ratebNavMenu');
        if (!nav) {
            return;
        }

        wireAllProfileLinks();
        removeLegacyPlatformRow();
        layoutNavBrandRow();
        stripNavBrandTagline();

        var root = document.getElementById('ratebMegaNavRoot');
        var onProfile =
            document.body &&
            (document.body.classList.contains('rateb-about-page') ||
                document.body.getAttribute('data-rateb-about') === '1');

        if (!onProfile && isLegacyMegaNav(root)) {
            nav.removeAttribute('data-rateb-nav-sync');
            fetchMegaNavFragment()
                .then(function (html) {
                    if (html && html.indexOf('ratebMegaNavRoot') !== -1) {
                        applyFreshMegaNav(html);
                        wireAllProfileLinks();
                        removeLegacyPlatformRow();
                        layoutNavBrandRow();
                        stripNavBrandTagline();
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
