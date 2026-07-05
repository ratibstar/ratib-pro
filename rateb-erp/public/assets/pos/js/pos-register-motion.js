(function (global) {
    'use strict';

    var reduced = false;
    try {
        reduced = global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (e) { /* ignore */ }

    function ripple(el, clientX, clientY) {
        if (reduced || !el) { return; }
        var rect = el.getBoundingClientRect();
        var container = el.querySelector('.rateb-pos__tile-ripple, .rateb-pos-v3__card-ripple, .rateb-pos-v2__tile-ripple, .rateb-pos-product-card__ripple');
        if (!container) {
            container = document.createElement('span');
            if (el.classList.contains('rateb-pos__tile')) {
                container.className = 'rateb-pos__tile-ripple';
            } else if (el.classList.contains('rateb-pos-v3__card')) {
                container.className = 'rateb-pos-v3__card-ripple';
            } else {
                container.className = 'rateb-pos-v2__tile-ripple';
            }
            container.setAttribute('aria-hidden', 'true');
            el.insertBefore(container, el.firstChild);
        }
        var circle = document.createElement('span');
        circle.className = 'rateb-pos-ripple-circle';
        circle.style.left = (clientX - rect.left) + 'px';
        circle.style.top = (clientY - rect.top) + 'px';
        container.appendChild(circle);
        circle.addEventListener('animationend', function () {
            if (circle.parentNode) { circle.parentNode.removeChild(circle); }
        });
    }

    function flyToCart(fromEl) {
        if (reduced || !fromEl) { return; }
        var layer = document.querySelector('[data-pos-fly-layer]');
        var cart = document.querySelector('[data-pos-cart-count]');
        if (!layer || !cart) { return; }
        var from = fromEl.getBoundingClientRect();
        var to = cart.getBoundingClientRect();
        var dot = document.createElement('div');
        dot.className = 'rateb-pos-fly-dot';
        dot.innerHTML = '<i class="fa-solid fa-plus"></i>';
        var sx = from.left + from.width / 2;
        var sy = from.top + from.height / 2;
        var tx = to.left + to.width / 2;
        var ty = to.top + to.height / 2;
        dot.style.left = sx + 'px';
        dot.style.top = sy + 'px';
        dot.style.setProperty('--fly-tx', (tx - sx) + 'px');
        dot.style.setProperty('--fly-ty', (ty - sy) + 'px');
        dot.style.animation = 'pos-fly-to-cart 0.16s cubic-bezier(0.16, 1, 0.3, 1) forwards';
        layer.appendChild(dot);
        dot.addEventListener('animationend', function () {
            if (dot.parentNode) { dot.parentNode.removeChild(dot); }
            bumpCartCount();
        });
    }

    function bumpCartCount() {
        if (reduced) { return; }
        var cart = document.querySelector('[data-pos-cart-count]');
        if (!cart) { return; }
        cart.classList.remove('is-bump');
        void cart.offsetWidth;
        cart.classList.add('is-bump');
        setTimeout(function () { cart.classList.remove('is-bump'); }, 280);
    }

    function pulseTotal() {
        if (reduced) { return; }
        document.querySelectorAll(
            '.rateb-pos__totals-row--total dd, .rateb-pos__charge, [data-pos-pay-amount], ' +
            '.rateb-pos-v3__total-line--grand, .rateb-pos-v3__pay, .rateb-pos-v3__running-total-value, ' +
            '.rateb-pos-v2__total-row--grand, .rateb-pos-v2__pay, [data-pos-toolbar-total]'
        ).forEach(function (el) {
            el.classList.remove('is-pulse');
            void el.offsetWidth;
            el.classList.add('is-pulse');
            setTimeout(function () { el.classList.remove('is-pulse'); }, 350);
        });
    }

    function updateCategoryIndicator(scrollEl, btn) {
        if (!scrollEl || !btn) { return; }
        var indicator = document.querySelector('[data-pos-cat-indicator]');
        if (!indicator) { return; }
        var scrollRect = scrollEl.getBoundingClientRect();
        var btnRect = btn.getBoundingClientRect();
        var top = btnRect.top - scrollRect.top + scrollEl.scrollTop;
        indicator.style.height = btnRect.height + 'px';
        indicator.style.transform = 'translate3d(0,' + top + 'px,0)';
        indicator.style.opacity = '1';
    }

    function bindSettings() {
        var toggle = document.querySelector('[data-pos-settings-toggle]');
        var menu = document.querySelector('[data-pos-settings]');
        if (!toggle || !menu) { return; }
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = menu.hidden;
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('[data-pos-settings]') && !e.target.closest('[data-pos-settings-toggle]')) {
                menu.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function bindSearchClear() {
        var input = document.querySelector('[data-pos-product-search]');
        var clearBtn = document.querySelector('[data-pos-search-clear]');
        if (!input || !clearBtn) { return; }
        function sync() { clearBtn.hidden = input.value.trim() === ''; }
        input.addEventListener('input', sync);
        clearBtn.addEventListener('click', function () {
            input.value = '';
            sync();
            input.focus();
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
        sync();
    }

    bindSettings();
    bindSearchClear();

    global.RatebPosMotion = {
        ripple: ripple,
        flyToCart: flyToCart,
        bumpCartCount: bumpCartCount,
        pulseTotal: pulseTotal,
        updateCategoryIndicator: updateCategoryIndicator,
        prefersReducedMotion: reduced
    };
})(window);
