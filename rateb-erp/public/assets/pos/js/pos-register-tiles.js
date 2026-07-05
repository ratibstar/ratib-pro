(function () {
    'use strict';

    var root = document.querySelector('[data-pos-register--premium]');
    if (!root) {
        return;
    }

    var configEl = document.getElementById('rateb-pos-register-config');
    var labelsEl = document.getElementById('rateb-pos-ui-labels');
    var config = {};
    var uiLabels = {};
    try {
        config = JSON.parse((configEl && configEl.textContent) || '{}');
    } catch (e) {
        config = {};
    }
    try {
        uiLabels = JSON.parse((labelsEl && labelsEl.textContent) || '{}');
    } catch (e2) {
        uiLabels = {};
    }

    var api = config.api || {};
    var i18n = config.i18n || {};
    var grid = root.querySelector('[data-pos-product-grid]');
    var categoriesEl = root.querySelector('[data-pos-categories]');
    var clockEl = document.querySelector('[data-pos-clock]');
    var connectionEl = document.querySelector('[data-pos-connection-status]');

    var selectedProductId = null;
    var activeCategory = 'all';
    var productCache = {};

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

    var PRODUCT_ICONS = {
        coffee: 'fa-mug-hot',
        food: 'fa-burger',
        dessert: 'fa-cookie',
        drink: 'fa-glass-water',
        bakery: 'fa-bread-slice',
        pizza: 'fa-pizza-slice',
        default: 'fa-box-open'
    };

    function t(key, fb) {
        return uiLabels[key] || i18n[key] || fb || key;
    }

    function money(n) {
        var v = Number(n);
        if (!isFinite(v)) {
            v = 0;
        }
        return v.toFixed(2);
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function favKey() {
        return 'rateb_pos_fav_' + (config.companyId || 0);
    }

    function recentKey() {
        return 'rateb_pos_recent_' + (config.companyId || 0);
    }

    function getFavorites() {
        try {
            var raw = localStorage.getItem(favKey());
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function getRecent() {
        try {
            var raw = localStorage.getItem(recentKey());
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    }

    function pushRecent(product) {
        if (!product || !product.id) {
            return;
        }
        var list = getRecent().filter(function (id) { return id !== product.id; });
        list.unshift(product.id);
        list = list.slice(0, 24);
        try {
            localStorage.setItem(recentKey(), JSON.stringify(list));
        } catch (e) { /* ignore */ }
    }

    function productIcon(product) {
        var name = ((product.item_name || '') + ' ' + (product.item_code || '')).toLowerCase();
        if (name.indexOf('coffee') >= 0 || name.indexOf('latte') >= 0) {
            return PRODUCT_ICONS.coffee;
        }
        if (name.indexOf('pizza') >= 0) {
            return PRODUCT_ICONS.pizza;
        }
        if (name.indexOf('burger') >= 0) {
            return PRODUCT_ICONS.food;
        }
        if (name.indexOf('cake') >= 0 || name.indexOf('dessert') >= 0) {
            return PRODUCT_ICONS.dessert;
        }
        if (name.indexOf('drink') >= 0 || name.indexOf('juice') >= 0) {
            return PRODUCT_ICONS.drink;
        }
        if (name.indexOf('bread') >= 0) {
            return PRODUCT_ICONS.bakery;
        }
        return PRODUCT_ICONS.default;
    }

    function fetchProducts(query) {
        if (!api.products) {
            return Promise.resolve([]);
        }
        var q = (query || 'aa').trim();
        if (q.length < 1) {
            q = 'aa';
        }
        return fetch(api.products + '?q=' + encodeURIComponent(q), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' }
        }).then(function (res) {
            return res.json();
        }).then(function (data) {
            return data.items || [];
        }).catch(function () {
            return [];
        });
    }

    function loadCategory(cat) {
        if (!grid) {
            return;
        }
        activeCategory = cat.id;
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
                var map = {};
                items.forEach(function (p) { map[p.id] = p; });
                return recentIds.map(function (id) { return map[id]; }).filter(Boolean);
            });
        } else {
            promise = fetchProducts(cat.query || 'aa');
        }

        promise.then(function (items) {
            renderProducts(items);
        }).finally(function () {
            grid.classList.remove('is-loading');
        });
    }

    function renderProducts(items) {
        if (!grid) {
            return;
        }
        grid.innerHTML = '';
        productCache = {};
        if (!items.length) {
            var empty = document.createElement('p');
            empty.className = 'rateb-pos-product-grid-empty';
            empty.textContent = t('pos_search_no_results', 'No products found');
            grid.appendChild(empty);
            return;
        }
        items.forEach(function (product) {
            productCache[product.id] = product;
            grid.appendChild(buildProductCard(product));
        });
    }

    function buildProductCard(product) {
        var avail = product.availability || {};
        var canAdd = avail.can_add !== false;
        var available = Number(avail.available != null ? avail.available : 0);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rateb-pos-product-card';
        btn.setAttribute('data-product-id', String(product.id));
        btn.setAttribute('role', 'gridcell');
        btn.setAttribute('aria-label', (product.item_name || '') + ' ' + money(product.unit_price || 0));
        if (!canAdd) {
            btn.classList.add('is-disabled');
            btn.disabled = true;
        }

        var badges = '';
        if (canAdd && available > 0 && available <= 5) {
            badges += '<span class="rateb-pos-product-badge rateb-pos-product-badge--low">' + escapeHtml(t('pos_low_stock', 'Low')) + '</span>';
        } else if (canAdd) {
            badges += '<span class="rateb-pos-product-badge rateb-pos-product-badge--stock">' + escapeHtml(String(available)) + '</span>';
        }
        if (product.requires_serial) {
            badges += '<span class="rateb-pos-product-badge rateb-pos-product-badge--serial">SN</span>';
        }
        if (product.has_batches) {
            badges += '<span class="rateb-pos-product-badge rateb-pos-product-badge--batch">FEFO</span>';
        }

        var overlay = !canAdd
            ? '<div class="rateb-pos-product-card__overlay">' + escapeHtml(t('pos_out_of_stock', 'Out of stock')) + '</div>'
            : '';

        btn.innerHTML =
            overlay +
            '<div class="rateb-pos-product-card__media">' +
            '<i class="fa-solid ' + productIcon(product) + ' rateb-pos-product-card__icon" aria-hidden="true"></i>' +
            '</div>' +
            '<div class="rateb-pos-product-card__body">' +
            '<div class="rateb-pos-product-card__name">' + escapeHtml(product.item_name || '') + '</div>' +
            '<div class="rateb-pos-product-card__price">' + money(product.unit_price || 0) + '</div>' +
            '<div class="rateb-pos-product-card__badges">' + badges + '</div>' +
            '</div>';

        btn.addEventListener('click', function () {
            onProductTap(product, btn);
        });

        return btn;
    }

    function onProductTap(product, btn) {
        if (selectedProductId === product.id) {
            addProduct(product);
            selectedProductId = null;
            grid.querySelectorAll('.rateb-pos-product-card.is-selected').forEach(function (el) {
                el.classList.remove('is-selected');
            });
            return;
        }
        selectedProductId = product.id;
        grid.querySelectorAll('.rateb-pos-product-card.is-selected').forEach(function (el) {
            el.classList.remove('is-selected');
        });
        btn.classList.add('is-selected');
    }

    function addProduct(product) {
        if (window.RatebPosRegister && typeof window.RatebPosRegister.addProduct === 'function') {
            window.RatebPosRegister.addProduct(product, 1);
            pushRecent(product);
            var card = grid.querySelector('[data-product-id="' + product.id + '"]');
            if (card) {
                card.classList.add('is-added');
                setTimeout(function () { card.classList.remove('is-added'); }, 350);
            }
        }
    }

    function renderCategories() {
        if (!categoriesEl) {
            return;
        }
        categoriesEl.innerHTML = '';
        categoriesEl.setAttribute('role', 'tablist');
        CATEGORIES.forEach(function (cat, idx) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'rateb-pos-category-chip' + (idx === 0 ? ' is-active' : '');
            chip.setAttribute('role', 'tab');
            chip.setAttribute('aria-selected', idx === 0 ? 'true' : 'false');
            chip.style.setProperty('--pos-cat-accent', cat.accent);
            chip.innerHTML =
                '<i class="fa-solid ' + cat.icon + '" aria-hidden="true"></i>' +
                '<span class="rateb-pos-category-chip__label">' + escapeHtml(t(cat.labelKey, cat.id)) + '</span>';
            chip.addEventListener('click', function () {
                categoriesEl.querySelectorAll('.rateb-pos-category-chip').forEach(function (c) {
                    c.classList.remove('is-active');
                    c.setAttribute('aria-selected', 'false');
                });
                chip.classList.add('is-active');
                chip.setAttribute('aria-selected', 'true');
                loadCategory(cat);
            });
            categoriesEl.appendChild(chip);
        });
    }

    function tickClock() {
        if (!clockEl) {
            return;
        }
        var now = new Date();
        var hh = String(now.getHours()).padStart(2, '0');
        var mm = String(now.getMinutes()).padStart(2, '0');
        clockEl.textContent = hh + ':' + mm;
        clockEl.setAttribute('datetime', now.toISOString());
    }

    function bindConnection() {
        if (!connectionEl) {
            return;
        }
        function sync() {
            var online = navigator.onLine;
            connectionEl.classList.toggle('is-offline', !online);
            var label = connectionEl.querySelector('.rateb-pos-connection__label');
            if (label) {
                label.textContent = online ? t('pos_online', 'Online') : t('pos_offline', 'Offline');
            }
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
