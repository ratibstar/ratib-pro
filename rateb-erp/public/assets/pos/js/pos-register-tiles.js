(function () {
    'use strict';

    var root = document.querySelector('[data-pos-register]');
    if (!root || !root.classList.contains('rateb-pos-v3')) {
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
    var virtualWindow = root.querySelector('[data-pos-virtual-window]');
    var virtualSpacer = root.querySelector('[data-pos-virtual-spacer]');
    var categoriesEl = root.querySelector('[data-pos-categories]');
    var clockEl = document.querySelector('[data-pos-clock]');
    var connectionEl = document.querySelector('[data-pos-connection-status]');
    var moreToggle = root.querySelector('[data-pos-more-toggle]');
    var moreMenu = root.querySelector('[data-pos-more-menu]');

    var CATEGORIES = [
        { id: 'all', icon: 'fa-border-all', labelKey: 'pos_cat_all', query: 'aa' },
        { id: 'coffee', icon: 'fa-mug-hot', labelKey: 'pos_cat_coffee', query: 'coffee' },
        { id: 'food', icon: 'fa-burger', labelKey: 'pos_cat_food', query: 'food' },
        { id: 'desserts', icon: 'fa-cookie', labelKey: 'pos_cat_desserts', query: 'cake' },
        { id: 'drinks', icon: 'fa-glass-water', labelKey: 'pos_cat_drinks', query: 'drink' },
        { id: 'bakery', icon: 'fa-bread-slice', labelKey: 'pos_cat_bakery', query: 'bread' },
        { id: 'pizza', icon: 'fa-pizza-slice', labelKey: 'pos_cat_pizza', query: 'pizza' },
        { id: 'burger', icon: 'fa-burger', labelKey: 'pos_cat_burger', query: 'burger' },
        { id: 'offers', icon: 'fa-tags', labelKey: 'pos_cat_offers', query: 'offer' },
        { id: 'favorites', icon: 'fa-heart', labelKey: 'pos_cat_favorites', query: '' },
        { id: 'recent', icon: 'fa-clock-rotate-left', labelKey: 'pos_cat_recent', query: '' },
        { id: 'popular', icon: 'fa-fire', labelKey: 'pos_cat_popular', query: 'a' }
    ];

    var ICONS = {
        coffee: 'fa-mug-hot', food: 'fa-burger', dessert: 'fa-cookie', drink: 'fa-glass-water',
        bakery: 'fa-bread-slice', pizza: 'fa-pizza-slice', default: 'fa-box-open'
    };

    var TILE_MIN = 176;
    var TILE_GAP = 12;
    var TILE_PAD = 12;
    var TILE_H = 220;
    var ROW_BUFFER = 2;
    var COL_BUFFER = 1;

    var products = [];
    var activeCategoryId = 'all';
    var layout = { cols: 4, tileW: TILE_MIN, rowH: TILE_H + TILE_GAP };
    var scrollRaf = null;
    var resizeObs = null;
    var tilePool = [];
    var boundTiles = {};

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
    function productImageUrl(p) {
        return p.image_url || p.thumbnail_url || p.image || '';
    }
    function hasPromo(product) {
        return !!(product.has_promotion || product.promotion_label || product.is_promo ||
            (product.discount_percent && Number(product.discount_percent) > 0));
    }

    function fetchProducts(query) {
        if (!api.products) { return Promise.resolve([]); }
        var q = (query || 'aa').trim() || 'aa';
        return fetch(api.products + '?q=' + encodeURIComponent(q), {
            credentials: 'same-origin', headers: { Accept: 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (d) { return d.items || []; }).catch(function () { return []; });
    }

    function measureLayout() {
        if (!grid) { return; }
        var w = grid.clientWidth - TILE_PAD * 2;
        var cols = Math.max(1, Math.floor((w + TILE_GAP) / (TILE_MIN + TILE_GAP)));
        var tileW = Math.floor((w - TILE_GAP * (cols - 1)) / cols);
        layout.cols = cols;
        layout.tileW = tileW;
        layout.rowH = TILE_H + TILE_GAP;
    }

    function updateSpacer() {
        if (!virtualSpacer) { return; }
        var rows = Math.ceil(products.length / layout.cols) || 1;
        virtualSpacer.style.height = (rows * layout.rowH + TILE_PAD * 2) + 'px';
    }

    function getTileFromPool() {
        if (tilePool.length) { return tilePool.pop(); }
        var el = document.createElement('article');
        el.className = 'rateb-pos-v3__card';
        el.setAttribute('role', 'gridcell');
        el.tabIndex = 0;
        return el;
    }

    function releaseTile(el) {
        el.className = 'rateb-pos-v3__card';
        el.removeAttribute('data-product-id');
        el.removeAttribute('aria-label');
        el.removeAttribute('aria-disabled');
        el.style.transform = '';
        el.innerHTML = '';
        el.onclick = null;
        el.onkeydown = null;
        tilePool.push(el);
    }

    function lazyLoadImg(img) {
        if (!img || img.dataset.loaded) { return; }
        var src = img.dataset.src;
        if (!src) { return; }
        img.dataset.loaded = '1';
        var loader = new Image();
        loader.onload = function () {
            img.src = src;
            img.classList.add('is-loaded');
        };
        loader.src = src;
    }

    function bindTileEvents(tile, product) {
        var pid = String(product.id);
        if (boundTiles[pid] === tile) { return; }
        boundTiles[pid] = tile;

        var favBtn = tile.querySelector('[data-pos-fav]');
        if (favBtn) {
            favBtn.onclick = function (e) {
                e.stopPropagation();
                var now = toggleFavorite(product.id);
                favBtn.classList.toggle('is-favorite', now);
                favBtn.setAttribute('aria-pressed', now ? 'true' : 'false');
                favBtn.querySelector('i').className = 'fa-' + (now ? 'solid' : 'regular') + ' fa-heart';
                if (activeCategoryId === 'favorites') {
                    loadCategory(CATEGORIES.filter(function (c) { return c.id === 'favorites'; })[0]);
                }
            };
        }

        function addOne(e) {
            if (e && e.target && e.target.closest('[data-pos-fav]')) { return; }
            if (tile.classList.contains('is-disabled')) { return; }
            if (motion.ripple && e) { motion.ripple(tile, e.clientX || 0, e.clientY || 0); }
            if (window.RatebPosRegister && window.RatebPosRegister.addProduct) {
                window.RatebPosRegister.addProduct(product, 1);
                pushRecent(product);
                tile.classList.add('is-added');
                setTimeout(function () { tile.classList.remove('is-added'); }, 320);
                if (motion.flyToCart) { motion.flyToCart(tile); }
            }
        }

        tile.onclick = addOne;
        tile.onkeydown = function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); addOne(e); }
        };
    }

    function fillTile(tile, product) {
        var avail = product.availability || {};
        var canAdd = avail.can_add !== false;
        var available = Number(avail.available != null ? avail.available : 0);
        var fav = isFavorite(product.id);
        var promo = hasPromo(product);
        var imgUrl = productImageUrl(product);

        tile.className = 'rateb-pos-v3__card';
        tile.setAttribute('data-product-id', String(product.id));
        tile.setAttribute('aria-label', (product.item_name || '') + ' ' + money(product.unit_price || 0));
        if (!canAdd) { tile.classList.add('is-disabled'); tile.setAttribute('aria-disabled', 'true'); }
        else { tile.removeAttribute('aria-disabled'); }

        var badges = '';
        if (promo) {
            badges += '<span class="rateb-pos-v3__badge rateb-pos-v3__badge--promo">' +
                escapeHtml(product.promotion_label || t('pos_promo', 'Promo')) + '</span>';
        }
        if (canAdd && available > 0 && available <= 5) {
            badges += '<span class="rateb-pos-v3__badge rateb-pos-v3__badge--low">' + escapeHtml(t('pos_low_stock', 'Low')) + '</span>';
        } else if (canAdd) {
            badges += '<span class="rateb-pos-v3__badge rateb-pos-v3__badge--stock">' + escapeHtml(String(available)) + '</span>';
        }
        if (product.requires_serial) { badges += '<span class="rateb-pos-v3__badge rateb-pos-v3__badge--serial">SN</span>'; }
        if (product.has_batches) { badges += '<span class="rateb-pos-v3__badge rateb-pos-v3__badge--batch">FEFO</span>'; }

        var overlay = !canAdd
            ? '<div class="rateb-pos-v3__card-overlay">' + escapeHtml(t('pos_out_of_stock', 'Out of stock')) + '</div>'
            : '';

        var imgHtml = imgUrl
            ? '<img data-src="' + escapeHtml(imgUrl) + '" alt="" loading="lazy" decoding="async" />'
            : '';
        var iconHtml = imgUrl ? '' : '<i class="fa-solid ' + productIcon(product) + ' rateb-pos-v3__card-icon"></i>';

        tile.innerHTML =
            overlay +
            '<span class="rateb-pos-v3__card-ripple" aria-hidden="true"></span>' +
            '<button type="button" class="rateb-pos-v3__card-fav' + (fav ? ' is-favorite' : '') + '" data-pos-fav="' + product.id + '" aria-label="' + escapeHtml(t('pos_favorite', 'Favorite')) + '" aria-pressed="' + (fav ? 'true' : 'false') + '">' +
            '<i class="fa-' + (fav ? 'solid' : 'regular') + ' fa-heart"></i></button>' +
            '<div class="rateb-pos-v3__card-visual">' + imgHtml + iconHtml +
            (badges ? '<div class="rateb-pos-v3__card-badges-top">' + badges + '</div>' : '') +
            '</div>' +
            '<div class="rateb-pos-v3__card-body">' +
            '<div class="rateb-pos-v3__card-name">' + escapeHtml(product.item_name || '') + '</div>' +
            '<div class="rateb-pos-v3__card-price">' + money(product.unit_price || 0) + '</div></div>';

        var img = tile.querySelector('img[data-src]');
        if (img) { lazyLoadImg(img); }
        bindTileEvents(tile, product);
    }

    function positionTile(tile, index) {
        var col = index % layout.cols;
        var row = Math.floor(index / layout.cols);
        var x = TILE_PAD + col * (layout.tileW + TILE_GAP);
        var y = TILE_PAD + row * layout.rowH;
        tile.style.width = layout.tileW + 'px';
        tile.style.height = TILE_H + 'px';
        tile.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0)';
    }

    function renderVisible() {
        if (!grid || !virtualWindow || !products.length) { return; }

        measureLayout();
        updateSpacer();

        var scrollTop = grid.scrollTop;
        var viewH = grid.clientHeight;
        var startRow = Math.max(0, Math.floor((scrollTop - TILE_PAD) / layout.rowH) - ROW_BUFFER);
        var endRow = Math.ceil((scrollTop + viewH - TILE_PAD) / layout.rowH) + ROW_BUFFER;
        var startIdx = startRow * layout.cols;
        var endIdx = Math.min(products.length, (endRow + 1) * layout.cols);

        virtualWindow.style.height = (Math.ceil(products.length / layout.cols) * layout.rowH + TILE_PAD * 2) + 'px';

        var needed = {};
        var i;
        for (i = startIdx; i < endIdx; i++) { needed[i] = true; }

        virtualWindow.querySelectorAll('.rateb-pos-v3__card[data-vi]').forEach(function (tile) {
            var vi = Number(tile.getAttribute('data-vi'));
            if (!needed[vi]) {
                tile.remove();
                releaseTile(tile);
            }
        });

        for (i = startIdx; i < endIdx; i++) {
            var existing = virtualWindow.querySelector('.rateb-pos-v3__card[data-vi="' + i + '"]');
            var tile = existing || getTileFromPool();
            tile.setAttribute('data-vi', String(i));
            if (tile.getAttribute('data-product-id') !== String(products[i].id)) {
                fillTile(tile, products[i]);
            }
            positionTile(tile, i);
            if (!existing) { virtualWindow.appendChild(tile); }
        }
    }

    function scheduleRender() {
        if (scrollRaf) { return; }
        scrollRaf = requestAnimationFrame(function () {
            scrollRaf = null;
            renderVisible();
        });
    }

    function showSkeleton() {
        if (!grid || !virtualWindow) { return; }
        products = [];
        virtualWindow.innerHTML = '';
        if (virtualSpacer) { virtualSpacer.style.height = '600px'; }
        measureLayout();
        var count = layout.cols * 3;
        for (var i = 0; i < count; i++) {
            var sk = document.createElement('article');
            sk.className = 'rateb-pos-v3__card rateb-pos-v3__card--skel';
            sk.setAttribute('aria-hidden', 'true');
            sk.innerHTML = '<div class="rateb-pos-v3__card-visual"></div><div class="rateb-pos-v3__card-body"><div class="rateb-pos-v3__card-name">&nbsp;</div><div class="rateb-pos-v3__card-price">&nbsp;</div></div>';
            positionTile(sk, i);
            virtualWindow.appendChild(sk);
        }
    }

    function showEmpty() {
        if (!virtualWindow) { return; }
        virtualWindow.innerHTML =
            '<div class="rateb-pos-v3__empty" role="status">' +
            '<div class="rateb-pos-v3__empty-icon"><i class="fa-solid fa-box-open"></i></div>' +
            '<p class="rateb-pos-v3__empty-title">' + escapeHtml(t('pos_search_no_results', 'No products found')) + '</p>' +
            '<p class="rateb-pos-v3__empty-hint">' + escapeHtml(t('pos_search_placeholder', 'Try another category')) + '</p></div>';
        if (virtualSpacer) { virtualSpacer.style.height = '100%'; }
    }

    function setProducts(items) {
        products = items || [];
        boundTiles = {};
        if (virtualWindow) {
            virtualWindow.innerHTML = '';
            tilePool = [];
        }
        if (!products.length) {
            showEmpty();
            return;
        }
        if (grid) { grid.scrollTop = 0; }
        renderVisible();
    }

    function setActiveCategory(btn, cat) {
        if (!categoriesEl || !btn) { return; }
        categoriesEl.querySelectorAll('.rateb-pos-v3__dock-btn').forEach(function (c) {
            c.classList.remove('is-active');
            c.setAttribute('aria-selected', 'false');
        });
        btn.classList.add('is-active');
        btn.setAttribute('aria-selected', 'true');
        activeCategoryId = cat.id;
        if (motion.updateCategoryIndicator) {
            motion.updateCategoryIndicator(categoriesEl, btn);
        }
    }

    function loadCategory(cat) {
        if (!grid) { return; }
        showSkeleton();
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
        promise.then(setProducts);
    }

    function renderCategories() {
        if (!categoriesEl) { return; }
        categoriesEl.innerHTML = '';
        categoriesEl.setAttribute('role', 'tablist');
        CATEGORIES.forEach(function (cat, idx) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rateb-pos-v3__dock-btn' + (idx === 0 ? ' is-active' : '');
            btn.setAttribute('role', 'tab');
            btn.setAttribute('aria-selected', idx === 0 ? 'true' : 'false');
            btn.setAttribute('data-cat-id', cat.id);
            btn.setAttribute('aria-label', t(cat.labelKey, cat.id));
            btn.innerHTML =
                '<i class="fa-solid ' + cat.icon + '" aria-hidden="true"></i>' +
                '<span class="rateb-pos-v3__dock-tip">' + escapeHtml(t(cat.labelKey, cat.id)) + '</span>';
            btn.addEventListener('click', function () {
                setActiveCategory(btn, cat);
                loadCategory(cat);
            });
            categoriesEl.appendChild(btn);
        });

        requestAnimationFrame(function () {
            var first = categoriesEl.querySelector('.rateb-pos-v3__dock-btn.is-active');
            if (first && motion.updateCategoryIndicator) {
                motion.updateCategoryIndicator(categoriesEl, first);
            }
        });

        categoriesEl.addEventListener('scroll', function () {
            var active = categoriesEl.querySelector('.rateb-pos-v3__dock-btn.is-active');
            if (active && motion.updateCategoryIndicator) {
                motion.updateCategoryIndicator(categoriesEl, active);
            }
        }, { passive: true });
    }

    function bindGridScroll() {
        if (!grid) { return; }
        grid.addEventListener('scroll', scheduleRender, { passive: true });
    }

    function bindResize() {
        if (!window.ResizeObserver || !grid) {
            window.addEventListener('resize', scheduleRender, { passive: true });
            return;
        }
        resizeObs = new ResizeObserver(scheduleRender);
        resizeObs.observe(grid);
    }

    function bindMoreMenu() {
        if (!moreToggle || !moreMenu) { return; }
        moreToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = moreMenu.hidden;
            moreMenu.hidden = !open;
            moreToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('[data-pos-more-menu]') && !e.target.closest('[data-pos-more-toggle]')) {
                moreMenu.hidden = true;
                moreToggle.setAttribute('aria-expanded', 'false');
            }
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
    bindGridScroll();
    bindResize();
    bindMoreMenu();
    loadCategory(CATEGORIES[0]);
    tickClock();
    setInterval(tickClock, 30000);
    bindConnection();

    window.addEventListener('resize', function () {
        var active = categoriesEl && categoriesEl.querySelector('.rateb-pos-v3__dock-btn.is-active');
        if (active && motion.updateCategoryIndicator) {
            motion.updateCategoryIndicator(categoriesEl, active);
        }
        scheduleRender();
    }, { passive: true });
})();
