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
        var btn = li.querySelector('.ratib-mega-nav__trigger');
        if (!btn) {
            return;
        }

        btn.addEventListener('click', function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            toggleItem(li);
        });

        btn.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') {
                closeAll();
                btn.focus();
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
})();

/** Profile tab + brand link — works when only ratib-mega-nav.js is updated on the server (bundle v15). */
(function ratibProfileNavPatch() {
    function profileHref() {
        var ref =
            document.querySelector('.ratib-nav__platform-links a[href*="home.php"]') ||
            document.querySelector('a.ratib-nav__brand');
        var home = ref ? ref.getAttribute('href') || '' : '/pages/home.php';
        home = String(home).replace(/#.*$/, '');
        var prof = home.replace(/\/home\.php(\?.*)?$/i, '/company-profile.php');
        if (prof.indexOf('company-profile.php') < 0) {
            prof = (home.replace(/[#?].*$/, '') || '/pages/home.php').replace(
                /home\.php.*$/i,
                'company-profile.php'
            );
        }
        return prof;
    }

    function injectPill() {
        var wrap = document.querySelector('.ratib-nav__platform-links');
        if (!wrap) {
            return;
        }
        if (
            wrap.querySelector('.ratib-nav__link--about') ||
            wrap.querySelector('.ratib-nav__link--about-injected')
        ) {
            return;
        }
        var a = document.createElement('a');
        a.href = profileHref();
        a.className =
            'ratib-nav__link ratib-nav__link--about ratib-nav__link--about-injected';
        a.innerHTML =
            '<span class="ratib-nav__icon" aria-hidden="true"><svg class="ratib-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#ratib-ng-solutions"/></svg></span><span class="ratib-nav__label">Profile</span>';
        wrap.insertBefore(a, wrap.firstChild);
    }

    function injectBrand() {
        var shell = document.querySelector('.ratib-nav-shell__inner');
        if (!shell || shell.querySelector('.ratib-nav__brand-profile')) {
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
        prof.className = 'ratib-nav__brand-profile';
        prof.textContent = 'Profile';
        brand.parentNode.insertBefore(blk, brand);
        blk.appendChild(brand);
        var bt = brand.querySelector('.ratib-nav__brand-text');
        if (bt) {
            bt.textContent = 'Ratib Company';
        }
        blk.appendChild(prof);
    }

    function run() {
        injectPill();
        injectBrand();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
    setTimeout(run, 0);
    setTimeout(run, 200);
})();
