(function (global) {
    'use strict';

    var reduced = false;
    try {
        reduced = global.matchMedia && global.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (e) { /* ignore */ }

    function ripple(el, clientX, clientY) {
        if (reduced || !el) {
            return;
        }
        var rect = el.getBoundingClientRect();
        var container = el.querySelector('.rateb-pos-product-card__ripple');
        if (!container) {
            container = document.createElement('span');
            container.className = 'rateb-pos-product-card__ripple';
            container.setAttribute('aria-hidden', 'true');
            el.insertBefore(container, el.firstChild);
        }
        var circle = document.createElement('span');
        circle.className = 'rateb-pos-ripple-circle';
        circle.style.left = (clientX - rect.left) + 'px';
        circle.style.top = (clientY - rect.top) + 'px';
        container.appendChild(circle);
        circle.addEventListener('animationend', function () {
            if (circle.parentNode) {
                circle.parentNode.removeChild(circle);
            }
        });
    }

    function flyToCart(fromEl) {
        if (reduced || !fromEl) {
            return;
        }
        var layer = document.querySelector('[data-pos-fly-layer]');
        var cart = document.querySelector('.rateb-pos-float-cart__badge') ||
            document.querySelector('.rateb-pos-float-cart');
        if (!layer || !cart) {
            return;
        }
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
        dot.style.setProperty('--fly-x', '0px');
        dot.style.setProperty('--fly-y', '0px');
        dot.style.setProperty('--fly-tx', (tx - sx) + 'px');
        dot.style.setProperty('--fly-ty', (ty - sy) + 'px');
        dot.style.animation = 'pos-fly-to-cart 0.55s cubic-bezier(0.16, 1, 0.3, 1) forwards';
        layer.appendChild(dot);
        dot.addEventListener('animationend', function () {
            if (dot.parentNode) {
                dot.parentNode.removeChild(dot);
            }
            cart.classList.add('is-bump');
            setTimeout(function () { cart.classList.remove('is-bump'); }, 300);
        });
    }

    function pulseTotal() {
        if (reduced) {
            return;
        }
        document.querySelectorAll('.rateb-pos-summary-card--grand, [data-pos-pay-amount]').forEach(function (el) {
            var card = el.closest('.rateb-pos-summary-card') || el;
            card.classList.remove('is-pulse');
            void card.offsetWidth;
            card.classList.add('is-pulse');
            setTimeout(function () { card.classList.remove('is-pulse'); }, 500);
        });
    }

    function updateCategoryIndicator(track, chip) {
        if (!track || !chip) {
            return;
        }
        var indicator = document.querySelector('[data-pos-category-indicator]');
        if (!indicator) {
            return;
        }
        var trackRect = track.getBoundingClientRect();
        var chipRect = chip.getBoundingClientRect();
        var left = chipRect.left - trackRect.left + track.scrollLeft;
        indicator.style.width = chipRect.width + 'px';
        indicator.style.transform = 'translate3d(' + left + 'px, 0, 0)';
        var accent = chip.style.getPropertyValue('--pos-cat-accent') || 'var(--pos-accent)';
        indicator.style.background = 'linear-gradient(90deg, ' + accent + ', var(--pos-info))';
    }

    function scrollCategoryIntoView(track, chip) {
        if (!track || !chip) {
            return;
        }
        var behavior = reduced ? 'auto' : 'smooth';
        chip.scrollIntoView({ behavior: behavior, inline: 'center', block: 'nearest' });
        setTimeout(function () {
            updateCategoryIndicator(track, chip);
        }, reduced ? 0 : 320);
    }

    function bindOverflow() {
        var toggle = document.querySelector('[data-pos-overflow-toggle]');
        var menu = document.querySelector('[data-pos-overflow]');
        if (!toggle || !menu) {
            return;
        }
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = menu.hidden;
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('[data-pos-overflow]') && !e.target.closest('[data-pos-overflow-toggle]')) {
                menu.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function bindMoreMenu() {
        var toggle = document.querySelector('[data-pos-more-actions]');
        var menu = document.querySelector('[data-pos-more-menu]');
        if (!toggle || !menu) {
            return;
        }
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = menu.hidden;
            menu.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function () {
            menu.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        });
    }

    function bindSearchClear() {
        var input = document.querySelector('[data-pos-product-search]');
        var clearBtn = document.querySelector('[data-pos-search-clear]');
        if (!input || !clearBtn) {
            return;
        }
        function sync() {
            clearBtn.hidden = input.value.trim() === '';
        }
        input.addEventListener('input', sync);
        clearBtn.addEventListener('click', function () {
            input.value = '';
            sync();
            input.focus();
        });
        sync();
    }

    bindOverflow();
    bindMoreMenu();
    bindSearchClear();

    global.RatebPosMotion = {
        ripple: ripple,
        flyToCart: flyToCart,
        pulseTotal: pulseTotal,
        updateCategoryIndicator: updateCategoryIndicator,
        scrollCategoryIntoView: scrollCategoryIntoView,
        prefersReducedMotion: reduced
    };
})(window);
