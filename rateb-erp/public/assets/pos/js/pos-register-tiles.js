(function () {
    'use strict';

    var root = document.querySelector('[data-pos-register]');
    if (!root || !root.classList.contains('rateb-pos')) {
        return;
    }

    var configEl = document.getElementById('rateb-pos-register-config');
    var bootstrapEl = document.getElementById('rateb-pos-bootstrap');
    var config = {};
    var bootstrap = { categories: [], productIndex: {}, productImages: {}, catalogSeed: [], shiftTerminals: [], defaultTerminalId: 0 };
    try { config = JSON.parse((configEl && configEl.textContent) || '{}'); } catch (e) { config = {}; }
    try { bootstrap = JSON.parse((bootstrapEl && bootstrapEl.textContent) || '{}'); } catch (e2) { bootstrap = {}; }

    var motion = window.RatebPosMotion || {};
    var api = config.api || {};
    var i18n = config.i18n || {};
    var grid = root.querySelector('[data-pos-product-grid]');
    var gridWrap = root.querySelector('.rateb-pos__grid-wrap');
    var catalogEmpty = root.querySelector('[data-pos-catalog-empty]');
    var virtualWindow = root.querySelector('[data-pos-virtual-window]');
    var virtualSpacer = root.querySelector('[data-pos-virtual-spacer]');
    var categoriesEl = root.querySelector('[data-pos-categories]');
    var clockEl = document.querySelector('[data-pos-clock]');
    var connectionEl = document.querySelector('[data-pos-connection-status]');
    var modesToggle = root.querySelector('[data-pos-modes-toggle]');
    var modesMenu = root.querySelector('[data-pos-modes-menu]');

    var TILE_MIN = 158;
    var TILE_GAP = 10;
    var TILE_PAD = 12;
    var TILE_H = 148;
    var ROW_BUFFER = 2;

    var CAT_PALETTE = [
        '#7c3aed', '#0d9488', '#ea580c', '#2563eb', '#db2777',
        '#ca8a04', '#059669', '#dc2626', '#0891b2', '#9333ea'
    ];

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

    function deferIdle(fn, timeoutMs) {
        if (typeof window.requestIdleCallback === 'function') {
            window.requestIdleCallback(fn, { timeout: timeoutMs || 3000 });
            return;
        }
        setTimeout(fn, timeoutMs || 600);
    }

    function cacheCatalogOffline(seed) {
        if (!seed || !seed.length) {
            return;
        }
        function put() {
            if (!window.RatebPosOffline || !window.RatebPosOffline.catalogPutMany) {
                return false;
            }
            window.RatebPosOffline.catalogPutMany(seed).catch(function () { /* optional */ });
            if (window.RatebPosOffline.catalogMetaPut) {
                window.RatebPosOffline.catalogMetaPut({
                    productIndex: bootstrap.productIndex || {},
                    productImages: bootstrap.productImages || {},
                    categories: bootstrap.categories || [],
                    savedAt: Date.now()
                });
            }
            return true;
        }
        if (put()) {
            return;
        }
        var tries = 0;
        var timer = setInterval(function () {
            tries += 1;
            if (put() || tries > 50) {
                clearInterval(timer);
            }
        }, 100);
    }

    function whenOfflineReady(timeoutMs) {
        if (window.RatebPosOffline && window.RatebPosOffline.catalogGetAll) {
            return Promise.resolve(window.RatebPosOffline);
        }
        return new Promise(function (resolve) {
            var start = Date.now();
            var max = timeoutMs || 4000;
            var timer = setInterval(function () {
                if (window.RatebPosOffline && window.RatebPosOffline.catalogGetAll) {
                    clearInterval(timer);
                    resolve(window.RatebPosOffline);
                    return;
                }
                if (Date.now() - start >= max) {
                    clearInterval(timer);
                    resolve(null);
                }
            }, 50);
        });
    }

    function applyOfflineMeta(meta) {
        if (!meta) {
            return;
        }
        if (meta.productIndex && typeof meta.productIndex === 'object') {
            bootstrap.productIndex = Object.assign({}, bootstrap.productIndex || {}, meta.productIndex);
        }
        if (meta.productImages && typeof meta.productImages === 'object') {
            bootstrap.productImages = Object.assign({}, bootstrap.productImages || {}, meta.productImages);
            window.RatebPosProductImages = bootstrap.productImages;
        }
        if (Array.isArray(meta.categories) && meta.categories.length && !(bootstrap.categories || []).length) {
            bootstrap.categories = meta.categories;
            renderCategories();
        }
    }

    function loadCatalogFromIndexedDb() {
        return whenOfflineReady(4000).then(function (off) {
            if (!off || !off.catalogGetAll) {
                return [];
            }
            var metaPromise = off.catalogMetaGet ? off.catalogMetaGet() : Promise.resolve(null);
            return Promise.all([off.catalogGetAll(), metaPromise]).then(function (pair) {
                var items = pair[0] || [];
                applyOfflineMeta(pair[1]);
                items.forEach(function (p) {
                    if (p && p.id) {
                        productCache[p.id] = mergeProduct(productCache[p.id], p);
                    }
                });
                return items;
            });
        }).catch(function () {
            return [];
        });
    }

    function productImageUrl(p) {
        var id = String(p.id || '');
        return p.image_url || p.thumbnail_url || p.image || bootstrap.productImages[id] || '';
    }

    function categoryTileColor(product) {
        var catId = Number(bootstrap.productIndex[String(product.id)] || product.category_id || 0);
        var seed = catId > 0 ? catId : Number(product.id || 0);
        return CAT_PALETTE[Math.abs(seed) % CAT_PALETTE.length];
    }

    function categoryChipColor(catId) {
        if (catId === 'all') {
            return CAT_PALETTE[0];
        }
        return CAT_PALETTE[Math.abs(Number(catId) || 0) % CAT_PALETTE.length];
    }

    function mergeProduct(prev, next) {
        var merged = Object.assign({}, prev || {}, next || {});
        merged.image_url = (next && (next.image_url || next.thumbnail_url || next.image))
            || (prev && (prev.image_url || prev.thumbnail_url || prev.image))
            || bootstrap.productImages[String(merged.id || '')]
            || '';
        return merged;
    }

    function fetchProducts(query) {
        if (!api.products) { return Promise.resolve([]); }
        var q = (query || '').trim();
        if (!q) { q = 'a'; }

        function cacheItems(items) {
            items.forEach(function (p) {
                productCache[p.id] = mergeProduct(productCache[p.id], p);
            });
            if (window.RatebPosOffline && window.RatebPosOffline.catalogPutMany) {
                window.RatebPosOffline.catalogPutMany(items);
            }
            return items;
        }

        if (!isTilesOnline()
            && window.RatebPosOffline && window.RatebPosOffline.catalogSearch) {
            return window.RatebPosOffline.catalogSearch(q, 80).then(cacheItems);
        }

        return fetch(api.products + '?q=' + encodeURIComponent(q), {
            credentials: 'same-origin', headers: { Accept: 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (d) {
            return cacheItems(d.items || []);
        }).catch(function () {
            if (window.RatebPosOffline && window.RatebPosOffline.catalogSearch) {
                return window.RatebPosOffline.catalogSearch(q, 80).then(cacheItems);
            }
            return [];
        });
    }

    function seedCatalogFromBootstrap() {
        var seed = bootstrap.catalogSeed || [];
        if (!seed.length) { return []; }
        seed.forEach(function (p) {
            if (p && p.id) {
                productCache[p.id] = mergeProduct(productCache[p.id], p);
            }
        });
        cacheCatalogOffline(seed);
        return seed.slice();
    }

    var catalogFetchPromise = null;

    function applyCatalogPayload(data) {
        bootstrap.productIndex = data.productIndex || bootstrap.productIndex || {};
        bootstrap.productImages = data.productImages || bootstrap.productImages || {};
        window.RatebPosProductImages = bootstrap.productImages || {};
        var seed = data.catalogSeed || [];
        seed.forEach(function (p) {
            if (p && p.id) {
                productCache[p.id] = mergeProduct(productCache[p.id], p);
            }
        });
        cacheCatalogOffline(seed);
        return seed.slice();
    }

    function isTilesOnline() {
        if (navigator.onLine === false) {
            return false;
        }
        if (window.RatebPosNet && typeof window.RatebPosNet.isOnline === 'function') {
            return window.RatebPosNet.isOnline();
        }
        if (window.RatebPosConnectivity && typeof window.RatebPosConnectivity.isOnline === 'function') {
            return window.RatebPosConnectivity.isOnline();
        }
        return true;
    }

    function fetchCatalogFromApi() {
        if (!api.bootstrap) {
            return Promise.resolve([]);
        }
        if (!isTilesOnline()) {
            return Promise.resolve([]);
        }
        if (catalogFetchPromise) {
            return catalogFetchPromise;
        }
        catalogFetchPromise = fetch(api.bootstrap, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (d) {
            if (!d || !d.ok) {
                return [];
            }
            return applyCatalogPayload(d);
        }).catch(function () {
            if (window.RatebPosNet && window.RatebPosNet.markOffline) {
                window.RatebPosNet.markOffline();
            }
            return [];
        }).finally(function () {
            // Allow retry after reconnect.
            if (!isTilesOnline()) {
                catalogFetchPromise = null;
            }
        });
        return catalogFetchPromise;
    }

    function refreshCatalogInBackground() {
        if ((bootstrap.catalogSeed || []).length > 0) {
            return Promise.resolve();
        }
        var seeds = ['a', 'e', 'i', 'o', 'u', '1', '2', '3', 'b', 'c', 'd', 'f', 'g', 'h', 'm', 's'];
        return Promise.all(seeds.map(fetchProducts)).then(function (groups) {
            groups.forEach(function (items) {
                items.forEach(function (p) {
                    if (p && p.id) {
                        productCache[p.id] = mergeProduct(productCache[p.id], p);
                    }
                });
            });
            if (!products.length) { return; }
            var filtered = filterByCategory(Object.values(productCache), activeCategoryId);
            if (filtered.length !== products.length) {
                setProducts(filtered);
            } else {
                products = filtered;
                scheduleRender();
            }
        }).catch(function () { /* keep bootstrap catalog */ });
    }

    function fetchCatalog() {
        if (catalogLoaded && Object.keys(productCache).length) {
            return Promise.resolve(Object.values(productCache));
        }
        var bootItems = seedCatalogFromBootstrap();
        if (bootItems.length) {
            catalogLoaded = true;
            return Promise.resolve(bootItems);
        }

        function finishFromIdb() {
            return loadCatalogFromIndexedDb().then(function (items) {
                catalogLoaded = true;
                if (!items.length) {
                    notify(t('pos_catalog_empty', 'No products'));
                }
                return items;
            });
        }

        if (!isTilesOnline()) {
            return finishFromIdb();
        }

        if (api.bootstrap) {
            return fetchCatalogFromApi().then(function (items) {
                if (items && items.length) {
                    catalogLoaded = true;
                    return items;
                }
                return finishFromIdb();
            }).catch(function () {
                return finishFromIdb();
            });
        }
        var seeds = ['a', 'e', 'i', 'o', 'u', '1', '2', '3', 'b', 'c', 'd', 'f', 'g', 'h', 'm', 's'];
        return Promise.all(seeds.map(fetchProducts)).then(function (groups) {
            var map = {};
            groups.forEach(function (items) {
                items.forEach(function (p) { map[p.id] = p; });
            });
            catalogLoaded = true;
            var items = Object.values(map);
            if (!items.length) {
                return finishFromIdb();
            }
            cacheCatalogOffline(items);
            return items;
        }).catch(function () {
            return finishFromIdb();
        });
    }

    function filterByCategory(items, catId) {
        if (catId === 'all' || catId === null) {
            return items;
        }
        var cid = Number(catId);
        return items.filter(function (p) {
            var idx = bootstrap.productIndex[String(p.id)];
            if (idx != null && idx !== '') {
                return Number(idx) === cid;
            }
            return Number(p.category_id || 0) === cid;
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
        var online = window.RatebPosConnectivity
            ? window.RatebPosConnectivity.isOnline()
            : navigator.onLine;
        if (!online && /^https?:\/\//i.test(src)) {
            img.remove();
            var surface = img.closest('.rateb-pos__tile-surface') || img.closest('.rateb-pos__tile-media');
            if (surface) { surface.classList.remove('has-photo'); }
            return;
        }
        img.dataset.loaded = '1';
        var loader = new Image();
        loader.onload = function () {
            img.src = src;
            img.classList.add('is-loaded');
            var surfaceLoaded = img.closest('.rateb-pos__tile-surface');
            if (surfaceLoaded) { surfaceLoaded.classList.add('has-photo'); }
        };
        loader.onerror = function () {
            var surfaceErr = img.closest('.rateb-pos__tile-surface') || img.closest('.rateb-pos__tile-media');
            img.remove();
            if (surfaceErr) { surfaceErr.classList.remove('has-photo'); }
        };
        loader.src = src;
    }

    function bindTileEvents(tile, product) {
        function resolveProduct() {
            var pid = tile.getAttribute('data-product-id');
            if (pid != null && productCache[pid]) {
                return productCache[pid];
            }
            if (pid != null && productCache[Number(pid)]) {
                return productCache[Number(pid)];
            }
            return product;
        }

        function addOne(e) {
            if (tile.classList.contains('is-disabled') && (window.RatebPosConnectivity ? window.RatebPosConnectivity.isOnline() : navigator.onLine)) {
                return;
            }
            var item = resolveProduct();
            if (!item || item.id == null) {
                notify(t('pos_product_not_found', 'Product not found'));
                return;
            }
            try {
                if (motion.ripple && e) { motion.ripple(tile, e.clientX || 0, e.clientY || 0); }
            } catch (errRipple) { /* ignore motion errors */ }

            var added = false;
            try {
                if (window.RatebPosRegister && typeof window.RatebPosRegister.addProduct === 'function') {
                    window.RatebPosRegister.addProduct(item, 1);
                    added = true;
                } else if (window.RatebPosRegister && typeof window.RatebPosRegister.addProductLocal === 'function') {
                    window.RatebPosRegister.addProductLocal(item, 1);
                    added = true;
                }
            } catch (errAdd) {
                added = false;
            }
            if (!added) {
                try {
                    document.dispatchEvent(new CustomEvent('rateb-pos-add-product', { detail: { product: item, qty: 1 } }));
                } catch (errEvt) {
                    notify(t('pos_product_not_found', 'Product not found'));
                    return;
                }
            }
            tile.classList.add('is-added');
            setTimeout(function () { tile.classList.remove('is-added'); }, 280);
            try {
                if (motion.flyToCart) { motion.flyToCart(tile); }
            } catch (errFly) { /* ignore */ }
        }

        tile.onclick = addOne;
        tile.onkeydown = function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); addOne(e); }
        };
    }

    function fillTile(tile, product) {
        var avail = product.availability || {};
        var online = navigator.onLine !== false;
        if (online && window.RatebPosConnectivity && typeof window.RatebPosConnectivity.isOnline === 'function') {
            online = window.RatebPosConnectivity.isOnline();
        }
        var canAdd = online ? (avail.can_add !== false) : true;
        var imgUrl = productImageUrl(product);
        var modHint = !!(product.has_modifiers || product.requires_modifiers);
        var tileColor = categoryTileColor(product);
        var sku = product.item_code || product.sku || '';
        var stockQty = avail.available != null ? avail.available : (product.stock_on_hand != null ? product.stock_on_hand : product.qty_on_hand);
        var stockLabel = stockQty != null
            ? (t('pos_stock_available', 'Available') + ': ' + stockQty)
            : '';

        tile.className = 'rateb-pos__tile rateb-pos__tile-body-card';
        tile.style.setProperty('--tile-bg', tileColor);
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

        var photoHtml = imgUrl
            ? '<img data-src="' + escapeHtml(imgUrl) + '" alt="" decoding="async" />'
            : '';

        var modDot = modHint
            ? '<span class="rateb-pos__tile-mod" aria-hidden="true"></span>'
            : '';

        tile.innerHTML =
            overlay +
            '<span class="rateb-pos__tile-ripple" aria-hidden="true"></span>' +
            '<div class="rateb-pos__tile-surface' + (imgUrl ? ' has-photo' : '') + '">' +
            photoHtml +
            '<div class="rateb-pos__tile-label">' +
            '<span class="rateb-pos__tile-name">' + escapeHtml(product.item_name || '') + '</span>' +
            (sku ? '<span class="rateb-pos__tile-sku">' + escapeHtml(sku) + '</span>' : '') +
            '<span class="rateb-pos__tile-price">' + money(product.unit_price || 0) + '</span>' +
            (stockLabel ? '<span class="rateb-pos__tile-stock">' + escapeHtml(stockLabel) + '</span>' : '') +
            '</div>' + modDot + '</div>';

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
        hideEmpty();
        if (!grid || !virtualWindow) { return; }
        virtualWindow.innerHTML = '';
        if (virtualSpacer) { virtualSpacer.style.height = '480px'; }
        measureLayout();
        var count = layout.cols * 2;
        for (var i = 0; i < count; i++) {
            var sk = document.createElement('article');
            sk.className = 'rateb-pos__tile rateb-pos__tile--skel';
            sk.setAttribute('aria-hidden', 'true');
            sk.innerHTML = '<div class="rateb-pos__tile-surface"><div class="rateb-pos__tile-label"><div class="rateb-pos__tile-name">&nbsp;</div></div></div>';
            positionTile(sk, i);
            virtualWindow.appendChild(sk);
        }
    }

    function showEmpty() {
        if (virtualWindow) {
            virtualWindow.innerHTML = '';
        }
        if (virtualSpacer) {
            virtualSpacer.style.height = '0';
        }
        if (gridWrap) {
            gridWrap.classList.add('is-empty');
        }
        if (catalogEmpty) {
            catalogEmpty.hidden = false;
        }
    }

    function hideEmpty() {
        if (gridWrap) {
            gridWrap.classList.remove('is-empty');
        }
        if (catalogEmpty) {
            catalogEmpty.hidden = true;
        }
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
        hideEmpty();
        if (grid) { grid.scrollTop = 0; }
        renderVisible();
    }

    function setActiveCategory(btn, cat) {
        if (!categoriesEl || !btn) { return; }
        categoriesEl.querySelectorAll('.rateb-pos__cat-btn').forEach(function (c) {
            c.classList.remove('is-active');
            c.setAttribute('aria-selected', 'false');
        });
        btn.classList.add('is-active');
        btn.setAttribute('aria-selected', 'true');
        activeCategoryId = cat.id;
        if (motion.updateCategoryIndicator) {
            motion.updateCategoryIndicator(categoriesEl.closest('.rateb-pos__cat-bar') || categoriesEl, btn);
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
            notify(t('pos_catalog_empty', 'No products'));
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
            btn.className = 'rateb-pos__cat-btn' + (idx === 0 ? ' is-active' : '');
            btn.setAttribute('role', 'tab');
            btn.setAttribute('aria-selected', idx === 0 ? 'true' : 'false');
            btn.setAttribute('data-cat-id', cat.id);
            btn.innerHTML = '<span class="rateb-pos__cat-label">' + escapeHtml(cat.name) + '</span>';
            btn.addEventListener('click', function () {
                setActiveCategory(btn, cat);
                loadCategory(cat);
            });
            categoriesEl.appendChild(btn);
        });

        var catBar = categoriesEl.closest('.rateb-pos__cat-bar');
        requestAnimationFrame(function () {
            var first = categoriesEl.querySelector('.rateb-pos__cat-btn.is-active');
            if (first && motion.updateCategoryIndicator) {
                motion.updateCategoryIndicator(catBar || categoriesEl, first);
            }
        });

        if (catBar) {
            catBar.addEventListener('scroll', function () {
                var active = categoriesEl.querySelector('.rateb-pos__cat-btn.is-active');
                if (active && motion.updateCategoryIndicator) {
                    motion.updateCategoryIndicator(catBar, active);
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

        var form = root.querySelector('[data-pos-shift-form]');
        if (!form) {
            return;
        }
        form.addEventListener('submit', function (e) {
            var offline = window.RatebPosConnectivity
                ? !window.RatebPosConnectivity.isOnline()
                : !navigator.onLine;
            if (!offline || !window.RatebPosOffline || !window.RatebPosOffline.push) {
                return;
            }
            e.preventDefault();
            var terminalEl = form.querySelector('[data-pos-shift-terminal]');
            var floatEl = form.querySelector('[data-pos-shift-float]');
            var terminalId = terminalEl ? Number(terminalEl.value || 0) : 0;
            if (terminalId < 1) {
                if (window.RatebPosNotify) {
                    window.RatebPosNotify(t('select', 'Select'), true);
                }
                return;
            }
            var scope = window.RatebPosOffline.buildScope
                ? window.RatebPosOffline.buildScope({ apiBase: (api && api.sync) || undefined })
                : {};
            window.RatebPosOffline.push({
                client_id: window.RatebPosOffline.newClientId
                    ? window.RatebPosOffline.newClientId('shift_open')
                    : ('shift-open-' + Date.now()),
                action: 'shift_open',
                payload: {
                    terminal_id: terminalId,
                    opening_float: floatEl ? Number(floatEl.value || 0) : 0,
                    scope: Object.assign({}, scope, { terminal_id: terminalId, user_id: config.userId || 0 })
                },
                version: 1
            }, { apiBase: (api && api.sync) || undefined }).then(function () {
                if (window.RatebPosNotify) {
                    window.RatebPosNotify(t('pos_offline_queued', 'Shift open queued — reconnect to continue'));
                }
            }).catch(function (err) {
                if (window.RatebPosNotify) {
                    window.RatebPosNotify(err.message || t('invalid_request', 'Failed'), true);
                }
            });
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
        function hydrateIfNeeded() {
            if (products.length || Object.keys(productCache).length) {
                if (!products.length && Object.keys(productCache).length) {
                    setProducts(filterByCategory(Object.values(productCache), activeCategoryId || 'all'));
                }
                return Promise.resolve();
            }
            return loadCatalogFromIndexedDb().then(function (items) {
                if (!items.length) {
                    return;
                }
                catalogLoaded = true;
                setProducts(filterByCategory(Object.values(productCache), activeCategoryId || 'all'));
            });
        }
        function applyOnlineState(online) {
            connectionEl.classList.toggle('is-offline', !online);
            var label = connectionEl.querySelector('.rateb-pos-connection__label');
            if (label) { label.textContent = online ? t('pos_online', 'Online') : t('pos_offline', 'Offline'); }
            root.classList.toggle('rateb-pos--offline', !online);
            if (!online) {
                hydrateIfNeeded();
                return;
            }
            if (window.RatebPosOffline && window.RatebPosOffline.sync) {
                window.RatebPosOffline.sync({ apiBase: (api && api.sync) || undefined }).catch(function () {});
            }
            if (!products.length) {
                catalogLoaded = false;
                catalogFetchPromise = null;
                loadCategory({ id: activeCategoryId || 'all', name: '' });
            } else if (Object.keys(productCache).length) {
                cacheCatalogOffline(Object.values(productCache));
            }
        }
        if (window.RatebPosConnectivity && window.RatebPosConnectivity.subscribe) {
            window.RatebPosConnectivity.subscribe(applyOnlineState);
            window.RatebPosConnectivity.probe();
            return;
        }
        function sync() {
            applyOnlineState(navigator.onLine);
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

    // Auto-refresh catalog when inventory transfer pushes new stock to POS warehouse from another tab/page.
    window.addEventListener('storage', function (e) {
        if (e.key !== 'rateb_pos_catalog_refresh' || !e.newValue) {
            return;
        }
        catalogLoaded = false;
        catalogFetchPromise = null;
        productCache = {};
        loadCategory({ id: activeCategoryId || 'all', name: '' });
    });
    if (window.BroadcastChannel) {
        try {
            var refreshCh = new BroadcastChannel('rateb_pos_catalog_channel');
            refreshCh.onmessage = function (evt) {
                if (!evt || !evt.data || evt.data.type !== 'refresh') {
                    return;
                }
                catalogLoaded = false;
                catalogFetchPromise = null;
                productCache = {};
                loadCategory({ id: activeCategoryId || 'all', name: '' });
            };
        } catch (err) {
            // ignore channel errors and rely on storage event
        }
    }
    // Safety net for browsers/tabs that miss storage/channel events.
    setInterval(function () {
        try {
            var marker = localStorage.getItem('rateb_pos_catalog_refresh') || '';
            if (!marker) { return; }
            if (window.__ratebPosCatalogMarker === marker) { return; }
            window.__ratebPosCatalogMarker = marker;
            if (!isTilesOnline()) {
                return;
            }
            catalogLoaded = false;
            catalogFetchPromise = null;
            productCache = {};
            loadCategory({ id: activeCategoryId || 'all', name: '' });
        } catch (err) {
            // no-op
        }
    }, 7000);

    window.addEventListener('resize', function () {
        var catBar = categoriesEl && categoriesEl.closest('.rateb-pos__cat-bar');
        var active = categoriesEl && categoriesEl.querySelector('.rateb-pos__cat-btn.is-active');
        if (active && motion.updateCategoryIndicator) {
            motion.updateCategoryIndicator(catBar || categoriesEl, active);
        }
        scheduleRender();
    }, { passive: true });

    window.RatebPosProductImages = bootstrap.productImages || {};
})();
