(function () {
    'use strict';

    var root = document.querySelector('[data-pos-register]');
    if (!root || !root.classList.contains('rateb-pos')) {
        return;
    }

    var configEl = document.getElementById('rateb-pos-register-config');
    var bootstrapEl = document.getElementById('rateb-pos-bootstrap');
    var config = {};
    var bootstrap = { categories: [], productIndex: {}, productImages: {}, shiftTerminals: [], defaultTerminalId: 0 };
    try { config = JSON.parse((configEl && configEl.textContent) || '{}'); } catch (e) { config = {}; }
    try { bootstrap = JSON.parse((bootstrapEl && bootstrapEl.textContent) || '{}'); } catch (e2) { bootstrap = {}; }

    var motion = window.RatebPosMotion || {};
    var api = config.api || {};
    var i18n = config.i18n || {};
    var grid = root.querySelector('[data-pos-product-grid]');
    var virtualWindow = root.querySelector('[data-pos-virtual-window]');
    var virtualSpacer = root.querySelector('[data-pos-virtual-spacer]');
    var categoriesEl = root.querySelector('[data-pos-categories]');
    var clockEl = document.querySelector('[data-pos-clock]');
    var connectionEl = document.querySelector('[data-pos-connection-status]');
    var modesToggle = root.querySelector('[data-pos-modes-toggle]');
    var modesMenu = root.querySelector('[data-pos-modes-menu]');

    var TILE_MIN = 152;
    var TILE_GAP = 12;
    var TILE_PAD = 12;
    var TILE_H = 200;
    var ROW_BUFFER = 2;

    var products = [];
    var productCache = {};
    var activeCategoryId = 'all';
    var layout = { cols: 4, tileW: TILE_MIN, rowH: TILE_H + TILE_GAP };
    var scrollRaf = null;
    var resizeObs = null;
    var tilePool = [];
    var boundTiles = {};
    var catalogLoaded = false;

    var FALLBACK_ART =
        '<svg class="rateb-pos__tile-art" viewBox="0 0 120 120" aria-hidden="true" focusable="false">' +
        '<defs><linearGradient id="rg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="var(--pos-surface-3)"/><stop offset="100%" stop-color="var(--pos-surface-2)"/></linearGradient></defs>' +
        '<rect width="120" height="120" fill="url(#rg)"/>' +
        '<circle cx="60" cy="48" r="22" fill="none" stroke="currentColor" stroke-width="2" opacity="0.18"/>' +
        '<path d="M30 88c8-14 52-14 60 0" fill="none" stroke="currentColor" stroke-width="2" opacity="0.18"/>' +
        '</svg>';

    function t(key, fb) { return i18n[key] || fb || key; }
    function money(n) { var v = Number(n); return (isFinite(v) ? v : 0).toFixed(2); }
    function escapeHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function notify(msg) {
        if (window.RatebPosNotify) { window.RatebPosNotify(msg); }
    }

    function productImageUrl(p) {
        var id = String(p.id || '');
        return p.image_url || p.thumbnail_url || p.image || bootstrap.productImages[id] || '';
    }

    function fetchProducts(query) {
        if (!api.products) { return Promise.resolve([]); }
        var q = (query || '').trim();
        if (!q) { q = 'a'; }
        return fetch(api.products + '?q=' + encodeURIComponent(q), {
            credentials: 'same-origin', headers: { Accept: 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (d) {
            var items = d.items || [];
            items.forEach(function (p) { productCache[p.id] = p; });
            return items;
        }).catch(function () { return []; });
    }

    function fetchCatalog() {
        if (catalogLoaded) {
            return Promise.resolve(Object.values(productCache));
        }
        var seeds = ['a', 'e', 'i', 'o', 'u', '1', '2', '3', 'b', 'c', 'd', 'f', 'g', 'h', 'm', 's'];
        return Promise.all(seeds.map(fetchProducts)).then(function (groups) {
            var map = {};
            groups.forEach(function (items) {
                items.forEach(function (p) { map[p.id] = p; });
            });
            catalogLoaded = true;
            return Object.values(map);
        });
    }

    function filterByCategory(items, catId) {
        if (catId === 'all' || catId === null) {
            return items;
        }
        var cid = Number(catId);
        return items.filter(function (p) {
            var idx = bootstrap.productIndex[String(p.id)];
            return Number(idx) === cid;
        });
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
        el.className = 'rateb-pos__tile';
        el.setAttribute('role', 'listitem');
        el.tabIndex = 0;
        return el;
    }

    function releaseTile(el) {
        el.className = 'rateb-pos__tile';
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
            var media = img.closest('.rateb-pos__tile-media');
            if (media) { media.classList.add('has-photo'); }
        };
        loader.onerror = function () {
            img.classList.remove('is-loaded');
            var media = img.closest('.rateb-pos__tile-media');
            if (media) {
                media.classList.remove('has-photo');
            }
        };
        loader.src = src;
    }

    function bindTileEvents(tile, product) {
        var pid = String(product.id);
        if (boundTiles[pid] === tile) { return; }
        boundTiles[pid] = tile;

        function addOne(e) {
            if (tile.classList.contains('is-disabled')) { return; }
            if (motion.ripple && e) { motion.ripple(tile, e.clientX || 0, e.clientY || 0); }
            if (window.RatebPosRegister && window.RatebPosRegister.addProduct) {
                window.RatebPosRegister.addProduct(product, 1);
                tile.classList.add('is-added');
                setTimeout(function () { tile.classList.remove('is-added'); }, 280);
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
        var imgUrl = productImageUrl(product);
        var modHint = !!(product.has_modifiers || product.requires_modifiers);

        tile.className = 'rateb-pos__tile';
        tile.setAttribute('data-product-id', String(product.id));
        tile.setAttribute('aria-label', (product.item_name || '') + ' ' + money(product.unit_price || 0));
        if (!canAdd) {
            tile.classList.add('is-disabled');
            tile.setAttribute('aria-disabled', 'true');
        } else {
            tile.classList.remove('is-disabled');
            tile.removeAttribute('aria-disabled');
        }

        var overlay = !canAdd
            ? '<div class="rateb-pos__tile-overlay">' + escapeHtml(t('pos_out_of_stock', 'Out of stock')) + '</div>'
            : '';

        var mediaInner = imgUrl
            ? '<img data-src="' + escapeHtml(imgUrl) + '" alt="" loading="lazy" decoding="async" />'
            : FALLBACK_ART;

        var modDot = modHint
            ? '<span class="rateb-pos__tile-mod" aria-hidden="true"></span>'
            : '';

        tile.innerHTML =
            overlay +
            '<span class="rateb-pos__tile-ripple" aria-hidden="true"></span>' +
            '<div class="rateb-pos__tile-media' + (imgUrl ? ' has-photo' : '') + '">' + mediaInner + modDot + '</div>' +
            '<div class="rateb-pos__tile-body">' +
            '<div class="rateb-pos__tile-name">' + escapeHtml(product.item_name || '') + '</div>' +
            '<div class="rateb-pos__tile-price">' + money(product.unit_price || 0) + '</div></div>';

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

        virtualWindow.querySelectorAll('.rateb-pos__tile[data-vi]').forEach(function (tile) {
            var vi = Number(tile.getAttribute('data-vi'));
            if (!needed[vi]) {
                tile.remove();
                releaseTile(tile);
            }
        });

        for (i = startIdx; i < endIdx; i++) {
            var existing = virtualWindow.querySelector('.rateb-pos__tile[data-vi="' + i + '"]');
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
        virtualWindow.innerHTML = '';
        if (virtualSpacer) { virtualSpacer.style.height = '480px'; }
        measureLayout();
        var count = layout.cols * 2;
        for (var i = 0; i < count; i++) {
            var sk = document.createElement('article');
            sk.className = 'rateb-pos__tile rateb-pos__tile--skel';
            sk.setAttribute('aria-hidden', 'true');
            sk.innerHTML = '<div class="rateb-pos__tile-media"></div><div class="rateb-pos__tile-body"><div class="rateb-pos__tile-name">&nbsp;</div><div class="rateb-pos__tile-price">&nbsp;</div></div>';
            positionTile(sk, i);
            virtualWindow.appendChild(sk);
        }
    }

    function showEmpty() {
        if (!virtualWindow) { return; }
        virtualWindow.innerHTML =
            '<div class="rateb-pos__empty" role="status">' +
            '<p class="rateb-pos__empty-title">' + escapeHtml(t('pos_search_no_results', 'No products found')) + '</p>' +
            '<p class="rateb-pos__empty-hint">' + escapeHtml(t('pos_search_placeholder', 'Try another category')) + '</p></div>';
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
        categoriesEl.querySelectorAll('.rateb-pos__rail-btn').forEach(function (c) {
            c.classList.remove('is-active');
            c.setAttribute('aria-selected', 'false');
        });
        btn.classList.add('is-active');
        btn.setAttribute('aria-selected', 'true');
        activeCategoryId = cat.id;
        if (motion.updateCategoryIndicator) {
            motion.updateCategoryIndicator(categoriesEl.closest('.rateb-pos__rail-scroll') || categoriesEl, btn);
        }
    }

    function loadCategory(cat) {
        if (!grid) { return; }
        showSkeleton();
        fetchCatalog().then(function (all) {
            var filtered = filterByCategory(all, cat.id);
            if (cat.id !== 'all' && !filtered.length) {
                return fetchProducts(cat.name || '').then(function (items) {
                    return filterByCategory(items.length ? items : all, cat.id);
                });
            }
            return filtered;
        }).then(setProducts).catch(function () {
            showEmpty();
        });
    }

    function buildCategories() {
        var list = [{ id: 'all', name: t('pos_cat_all', 'All') }];
        (bootstrap.categories || []).forEach(function (c) {
            if (c && c.id) {
                list.push({ id: String(c.id), name: c.name || String(c.id) });
            }
        });
        return list;
    }

    function renderCategories() {
        if (!categoriesEl) { return; }
        var CATEGORIES = buildCategories();
        categoriesEl.innerHTML = '';
        CATEGORIES.forEach(function (cat, idx) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'rateb-pos__rail-btn' + (idx === 0 ? ' is-active' : '');
            btn.setAttribute('role', 'tab');
            btn.setAttribute('aria-selected', idx === 0 ? 'true' : 'false');
            btn.setAttribute('data-cat-id', cat.id);
            btn.innerHTML = '<span class="rateb-pos__rail-label">' + escapeHtml(cat.name) + '</span>';
            btn.addEventListener('click', function () {
                setActiveCategory(btn, cat);
                loadCategory(cat);
            });
            categoriesEl.appendChild(btn);
        });

        var railScroll = categoriesEl.closest('.rateb-pos__rail-scroll');
        requestAnimationFrame(function () {
            var first = categoriesEl.querySelector('.rateb-pos__rail-btn.is-active');
            if (first && motion.updateCategoryIndicator) {
                motion.updateCategoryIndicator(railScroll || categoriesEl, first);
            }
        });

        if (railScroll) {
            railScroll.addEventListener('scroll', function () {
                var active = categoriesEl.querySelector('.rateb-pos__rail-btn.is-active');
                if (active && motion.updateCategoryIndicator) {
                    motion.updateCategoryIndicator(railScroll, active);
                }
            }, { passive: true });
        }
    }

    function bindShiftGate() {
        var sel = root.querySelector('[data-pos-shift-terminal]');
        if (!sel || !bootstrap.shiftTerminals) { return; }
        bootstrap.shiftTerminals.forEach(function (opt) {
            var o = document.createElement('option');
            o.value = String(opt.value);
            o.textContent = opt.label || String(opt.value);
            if (Number(opt.value) === Number(bootstrap.defaultTerminalId)) {
                o.selected = true;
            }
            sel.appendChild(o);
        });
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

    function bindModesMenu() {
        if (!modesToggle || !modesMenu) { return; }
        modesToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = modesMenu.hidden;
            modesMenu.hidden = !open;
            modesToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('[data-pos-modes-menu]') && !e.target.closest('[data-pos-modes-toggle]')) {
                modesMenu.hidden = true;
                modesToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function bindReturnClose() {
        var closeBtn = root.querySelector('[data-pos-return-close]');
        var panel = root.querySelector('[data-pos-return-panel]');
        if (closeBtn && panel) {
            closeBtn.addEventListener('click', function () { panel.hidden = true; });
        }
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
    bindShiftGate();
    bindGridScroll();
    bindResize();
    bindModesMenu();
    bindReturnClose();
    loadCategory({ id: 'all' });
    tickClock();
    setInterval(tickClock, 30000);
    bindConnection();

    window.addEventListener('resize', function () {
        var railScroll = categoriesEl && categoriesEl.closest('.rateb-pos__rail-scroll');
        var active = categoriesEl && categoriesEl.querySelector('.rateb-pos__rail-btn.is-active');
        if (active && motion.updateCategoryIndicator) {
            motion.updateCategoryIndicator(railScroll || categoriesEl, active);
        }
        scheduleRender();
    }, { passive: true });

    window.RatebPosProductImages = bootstrap.productImages || {};
})();
