/**
 * Mega navigation: click/tap to toggle panels, Escape and outside-click to close.
 * No dependencies. Works with includes/ratib-mega-nav-render.php markup.
 */
(function ratibMegaNavInit() {
    var root =
        document.getElementById('ratibMegaNavRoot') ||
        document.querySelector('.ratib-mega-nav[data-ratib-mega-nav="1"]');
    if (!root) {
        return;
    }

    var items = Array.prototype.slice.call(root.querySelectorAll('.ratib-mega-nav__li--mega'));
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

    function openItem(li) {
        closeAll();
        li.classList.add('is-open');
        var btn = li.querySelector('.ratib-mega-nav__trigger');
        var panel = li.querySelector('.ratib-mega-nav__panel');
        if (btn) {
            btn.setAttribute('aria-expanded', 'true');
        }
        if (panel) {
            panel.removeAttribute('hidden');
        }
    }

    function toggleItem(li) {
        if (li.classList.contains('is-open')) {
            closeAll();
        } else {
            openItem(li);
        }
    }

    items.forEach(function (li) {
        var btn = li.querySelector('button.ratib-mega-nav__trigger');
        var triggerEl = btn && btn.getAttribute('id') ? btn : li.querySelector('.ratib-mega-nav__trigger-label');
        if (!triggerEl) {
            return;
        }

        function onActivate(ev) {
            if (ev.target && ev.target.closest && ev.target.closest('.ratib-mega-nav__panel')) {
                return;
            }
            ev.preventDefault();
            ev.stopPropagation();
            toggleItem(li);
        }

        triggerEl.addEventListener('click', onActivate);
        if (triggerEl !== btn && btn) {
            btn.addEventListener('click', onActivate);
        }

        if (btn && btn.addEventListener) {
            btn.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape') {
                    closeAll();
                    btn.focus();
                }
            });
        }
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
})();

/** Profile tab + platform pill → /profile (single-file deploy: ratib-mega-nav.js). */
(function ratibProfileNavPatch() {
    'use strict';

    function profileHref() {
        var o = window.location.origin || '';
        return o ? o + '/profile/#company-profile' : '/profile/#company-profile';
    }

    function fixProfileHrefs() {
        var PROFILE = profileHref();
        document
            .querySelectorAll(
                '.ratib-nav__brand-profile, .ratib-nav__link--about, [data-ratib-profile-nav], .ratib-footer-link--about'
            )
            .forEach(function (a) {
                a.setAttribute('href', PROFILE);
                a.setAttribute('data-ratib-profile-nav', '1');
                a.removeAttribute('target');
                a.removeAttribute('rel');
            });
        document.querySelectorAll('a.ratib-mega-nav__card').forEach(function (card) {
            var t = card.querySelector('.ratib-mega-nav__card-title');
            if (t && /company profile/i.test(t.textContent || '')) {
                card.setAttribute('href', PROFILE);
                card.removeAttribute('target');
                card.removeAttribute('rel');
            }
        });
    }

    function removeLegacyPlatformRow() {
        var wrap = document.querySelector('.ratib-nav__platform-links');
        if (wrap) {
            wrap.remove();
        }
    }

    function injectBrand() {
        var shell = document.querySelector('.ratib-nav-shell__inner');
        if (!shell) {
            return;
        }
        if (shell.querySelector('.ratib-nav__brand-profile')) {
            fixProfileHrefs();
            return;
        }
        var brand = shell.querySelector('a.ratib-nav__brand');
        if (!brand) {
            return;
        }
        var blk = document.createElement('div');
        blk.className = 'ratib-nav__brand-block';
        var prof = document.createElement('a');
        prof.href = profileHref();
        prof.setAttribute('data-ratib-profile-nav', '1');
        prof.className = 'ratib-nav__brand-profile';
        prof.textContent = 'Profile';
        brand.parentNode.insertBefore(blk, brand);
        blk.classList.add('ratib-nav__brand-block--animated', 'ratib-nav__brand-block--row');
        blk.appendChild(brand);
        blk.appendChild(prof);
        if (brand.querySelector('.ratib-brand-full')) {
            brand.querySelectorAll('img, .ratib-nav__brand-logo, .ratib-nav__brand-text').forEach(function (el) {
                el.remove();
            });
        } else {
            var bt = brand.querySelector('.ratib-nav__brand-text');
            if (bt) {
                bt.textContent = 'RATEB';
            }
        }
    }

    function run() {
        fixProfileHrefs();
        removeLegacyPlatformRow();
        injectBrand();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
    setTimeout(run, 0);
    setTimeout(run, 200);
    setTimeout(run, 800);
})();
