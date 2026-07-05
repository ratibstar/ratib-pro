(function () {
    'use strict';

    var root = document.querySelector('[data-pos-register]');
    if (!root || !root.classList.contains('rateb-pos-v2')) {
        return;
    }

    var configEl = document.getElementById('rateb-pos-register-config');
    var labelsEl = document.getElementById('rateb-pos-ui-labels');
    var config = {};
    var uiLabels = {};
    try { config = JSON.parse((configEl && configEl.textContent) || '{}'); } catch (e) { config = {}; }
    try { uiLabels = JSON.parse((labelsEl && labelsEl.textContent) || '{}'); } catch (e2) { uiLabels = {}; }

    var motion = window.RatebPosMotion || {};
    var api = config.api || {};
    var i18n = config.i18n || {};
    var grid = root.querySelector('[data-pos-product-grid]');
    var categoriesEl = root.querySelector('[data-pos-categories]');
    var clockEl = document.querySelector('[data-pos-clock]');
    var connectionEl = document.querySelector('[data-pos-connection-status]');

    var CATEGORIES = [
        { id: 'all', icon: 'fa-border-all', labelKey: 'pos_cat_all', accent: '#38bdf8', query: 'aa' },
        { id: 'coffee', icon: 'fa-mug-hot', labelKey: 'pos_cat_coffee', accent: '#0891b2', query: 'coffee' },
        { id: 'food', icon: 'fa-burger', labelKey: 'pos_cat_food', accent: '#ea580c', query: 'food' },
        { id: 'desserts', icon: 'fa-cookie', labelKey: 'pos_cat_desserts', accent: '#c026d3', query: 'cake' },
        { id: 'drinks', icon: 'fa-glass-water', labelKey: 'pos_cat_drinks', accent: '#0ea5e9', query: 'drink' },
        { id: 'bakery', icon: 'fa-bread-slice', labelKey: 'pos_cat_bakery', accent: '#d97706', query: 'bread' },
        { id: 'pizza', icon: 'fa-pizza-slice', labelKey: 'pos_cat_pizza', accent: '#dc2626', query: 'pizza' },
        { id: 'burger', icon: 'fa-burger', labelKey: 'pos_cat_burger', accent: '#f97316', query: 'burger' },
        { id: 'offers', icon: 'fa-tags', labelKey: 'pos_cat_offers', accent: '#ec4899', query: 'offer' },
        { id: 'favorites', icon: 'fa-heart', labelKey: 'pos_cat_favorites', accent: '#f43f5e', query: '' },
        { id: 'recent', icon: 'fa-clock-rotate-left', labelKey: 'pos_cat_recent', accent: '#6366f1', query: '' },
        { id: 'popular', icon: 'fa-fire', labelKey: 'pos_cat_popular', accent: '#ef4444', query: 'a' }
    ];

    var ICONS = {
        coffee: 'fa-mug-hot', food: 'fa-burger', dessert: 'fa-cookie', drink: 'fa-glass-water',
        bakery: 'fa-bread-slice', pizza: 'fa-pizza-slice', default: 'fa-box-open'
    };

    function t(key, fb) { return uiLabels[key] || i18n[key] || fb || key; }
    function money(n) { var v = Number(n); return (isFinite(v) ? v : 0).toFixed(2); }
    function escapeHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function favKey() { return 'rateb_pos_fav_' + (config.companyId || 0); }
    function recentKey() { return 'rateb_pos_recent_' + (config.companyId || 0); }

    function getFavorites() {
        try { return JSON.parse(localStorage.getItem(favKey()) || '[]'); } catch (e) { return []; }
    }
    function isFavorite(id) { return getFavorites().indexOf(id) >= 0; }
    function toggleFavorite(id) {
        var list = getFavorites();
        var i = list.indexOf(id);
        if (i >= 0) { list.splice(i, 1); } else { list.unshift(id); }
        try { localStorage.setItem(favKey(), JSON.stringify(list.slice(0, 48))); } catch (e) { /* ignore */ }
        return list.indexOf(id) >= 0;
    }
    function getRecent() {
        try { return JSON.parse(localStorage.getItem(recentKey()) || '[]'); } catch (e) { return []; }
    }
    function pushRecent(p) {
        if (!p || !p.id) { return; }
        var list = getRecent().filter(function (id) { return id !== p.id; });
        list.unshift(p.id);
        try { localStorage.setItem(recentKey(), JSON.stringify(list.slice(0, 24))); } catch (e) { /* ignore */ }
    }
    function productIcon(p) {
        var n = ((p.item_name || '') + ' ' + (p.item_code || '')).toLowerCase();
        if (n.indexOf('coffee') >= 0 || n.indexOf('latte') >= 0) { return ICONS.coffee; }
        if (n.indexOf('pizza') >= 0) { return ICONS.pizza; }
        if (n.indexOf('burger') >= 0) { return ICONS.food; }
        if (n.indexOf('cake') >= 0 || n.indexOf('dessert') >= 0) { return ICONS.dessert; }
        if (n.indexOf('drink') >= 0 || n.indexOf('juice') >= 0) { return ICONS.drink; }
        if (n.indexOf('bread') >= 0) { return ICONS.bakery; }
        return ICONS.default;
    }

    function fetchProducts(query) {
        if (!api.products) { return Promise.resolve([]); }
        var q = (query || 'aa').trim() || 'aa';
        return fetch(api.products + '?q=' + encodeURIComponent(q), {
            credentials: 'same-origin', headers: { Accept: 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (d) { return d.items || []; }).catch(function () { return []; });
    }

    function loadCategory(cat) {
        if (!grid) { return; }
        grid.classList.add('is-loading');
        grid.innerHTML = '';
        var promise;
        if (cat.id === 'favorites') {
            var favIds = getFavorites();
            promise = fetchProducts('aa').then(function (items) {
                return items.filter(function (p) { return favIds.indexOf(p.id) >= 0; });
            });
        } else if (cat.id === 'recent') {
            var recentIds = getRecent();
            promise = fetchProducts('aa').then(function (items) {
                var map = {}; items.forEach(function (p) { map[p.id] = p; });
                return recentIds.map(function (id) { return map[id]; }).filter(Boolean);
            });
        } else {
            promise = fetchProducts(cat.query || 'aa');
        }
        promise.then(renderProducts).finally(function () { grid.classList.remove('is-loading'); });
    }

    function renderProducts(items) {
        if (!grid) { return; }
        grid.innerHTML = '';
        if (!items.length) {
            var empty = document.createElement('p');
            empty.className = 'rateb-pos-v2__grid-empty';
            empty.textContent = t('pos_search_no_results', 'No products found');
            grid.appendChild(empty);
            return;
        }
        items.forEach(function (product, idx) {
            var tile = buildTile(product);
            tile.style.setProperty('--pos-stagger', String(idx % 24));
            grid.appendChild(tile);
        });
    }

    function buildTile(product) {
        var avail = product.availability || {};
        var canAdd = avail.can_add !== false;
        var available = Number(avail.available != null ? avail.available : 0);
        var fav = isFavorite(product.id);
        var tile = document.createElement('div');
        tile.className = 'rateb-pos-v2__tile';
        tile.setAttribute('data-product-id', String(product.id));
        tile.setAttribute('role', 'gridcell');
        tile.tabIndex = 0;
        tile.setAttribute('aria-label', (product.item_name || '') + ' ' + money(product.unit_price || 0));

        if (!canAdd) { tile.classList.add('is-disabled'); tile.setAttribute('aria-disabled', 'true'); }
        if (canAdd && available > 0 && available <= 5) { tile.classList.add('is-low-stock'); }
        if (fav) { tile.classList.add('is-favorite'); }

        var badges = '';
        if (fav) { badges += '<span class="rateb-pos-v2__badge rateb-pos-v2__badge--fav">' + escapeHtml(t('pos_favorite', 'Fav')) + '</span>'; }
        if (canAdd && available > 0 && available <= 5) {
            badges += '<span class="rateb-pos-v2__badge rateb-pos-v2__badge--low">' + escapeHtml(t('pos_low_stock', 'Low')) + '</span>';
        } else if (canAdd) {
            badges += '<span class="rateb-pos-v2__badge rateb-pos-v2__badge--stock">' + escapeHtml(String(available)) + '</span>';
        }
        if (product.requires_serial) { badges += '<span class="rateb-pos-v2__badge rateb-pos-v2__badge--serial">SN</span>'; }
        if (product.has_batches) { badges += '<span class="rateb-pos-v2__badge rateb-pos-v2__badge--batch">FEFO</span>'; }

        var overlay = !canAdd
            ? '<div class="rateb-pos-v2__tile-overlay">' + escapeHtml(t('pos_out_of_stock', 'Out of stock')) + '</div>'
            : '';

        tile.innerHTML =
            overlay +
            '<span class="rateb-pos-v2__tile-ripple" aria-hidden="true"></span>' +
            '<button type="button" class="rateb-pos-v2__tile-fav' + (fav ? ' is-favorite' : '') + '" data-pos-fav="' + product.id + '" aria-label="' + escapeHtml(t('pos_favorite', 'Favorite')) + '" aria-pressed="' + (fav ? 'true' : 'false') + '">' +
            '<i class="fa-' + (fav ? 'solid' : 'regular') + ' fa-heart"></i></button>' +
            '<div class="rateb-pos-v2__tile-media"><i class="fa-solid ' + productIcon(product) + ' rateb-pos-v2__tile-icon"></i></div>' +
            '<div class="rateb-pos-v2__tile-body">' +
            '<div class="rateb-pos-v2__tile-name">' + escapeHtml(product.item_name || '') + '</div>' +
            '<div class="rateb-pos-v2__tile-price">' + money(product.unit_price || 0) + '</div>' +
            '<div class="rateb-pos-v2__tile-badges">' + badges + '</div></div>';

        var favBtn = tile.querySelector('[data-pos-fav]');
        if (favBtn) {
            favBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var now = toggleFavorite(product.id);
                favBtn.classList.toggle('is-favorite', now);
                favBtn.setAttribute('aria-pressed', now ? 'true' : 'false');
                favBtn.querySelector('i').className = 'fa-' + (now ? 'solid' : 'regular') + ' fa-heart';
                tile.classList.toggle('is-favorite', now);
            });
        }

        function addOne(e) {
            if (e && e.target && e.target.closest('[data-pos-fav]')) { return; }
            if (tile.classList.contains('is-disabled')) { return; }
            if (motion.ripple && e) { motion.ripple(tile, e.clientX || 0, e.clientY || 0); }
            if (window.RatebPosRegister && window.RatebPosRegister.addProduct) {
                window.RatebPosRegister.addProduct(product, 1);
                pushRecent(product);
                tile.classList.add('is-added');
                setTimeout(function () { tile.classList.remove('is-added'); }, 400);
                if (motion.flyToCart) { motion.flyToCart(tile); }
            }
        }

        tile.addEventListener('click', addOne);
        tile.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); addOne(e); }
        });

        return tile;
    }

    function renderCategories() {
        if (!categoriesEl) { return; }
        categoriesEl.innerHTML = '';
        categoriesEl.setAttribute('role', 'tablist');
        CATEGORIES.forEach(function (cat, idx) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rateb-pos-v2__cat' + (idx === 0 ? ' is-active' : '');
            btn.setAttribute('role', 'tab');
            btn.setAttribute('aria-selected', idx === 0 ? 'true' : 'false');
            btn.style.setProperty('--pos-cat-accent', cat.accent);
            btn.innerHTML = '<i class="fa-solid ' + cat.icon + '" aria-hidden="true"></i><span class="rateb-pos-v2__cat-label">' + escapeHtml(t(cat.labelKey, cat.id)) + '</span>';
            btn.addEventListener('click', function () {
                categoriesEl.querySelectorAll('.rateb-pos-v2__cat').forEach(function (c) {
                    c.classList.remove('is-active');
                    c.setAttribute('aria-selected', 'false');
                });
                btn.classList.add('is-active');
                btn.setAttribute('aria-selected', 'true');
                loadCategory(cat);
            });
            categoriesEl.appendChild(btn);
        });
    }

    function tickClock() {
        if (!clockEl) { return; }
        var now = new Date();
        clockEl.textContent = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        clockEl.setAttribute('datetime', now.toISOString());
    }

    function bindConnection() {
        if (!connectionEl) { return; }
        function sync() {
            var online = navigator.onLine;
            connectionEl.classList.toggle('is-offline', !online);
            var label = connectionEl.querySelector('.rateb-pos-connection__label');
            if (label) { label.textContent = online ? t('pos_online', 'Online') : t('pos_offline', 'Offline'); }
        }
        window.addEventListener('online', sync);
        window.addEventListener('offline', sync);
        sync();
    }

    renderCategories();
    loadCategory(CATEGORIES[0]);
    tickClock();
    setInterval(tickClock, 30000);
    bindConnection();
})();
