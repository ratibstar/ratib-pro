/*!
 * RATEB Offline V2 — POS Offline BusinessModule (Phase 6 Reservation)
 *
 * Local catalog + cart + complete sale + outbox + stock snapshot + local reservations.
 * No payment/receipt/network push/inv.* writes. sync.start() never called.
 * register/activate do not open DB; stock/cart/checkout open on demand.
 * Does not load Inventory BusinessModule. Online ERP = Authentication Authority (AF 2.1).
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || !Business.BusinessModule) {
        return;
    }

    var BusinessModule = Business.BusinessModule;
    var POS_VERSION = '0.6.0-phase6-reservation';

    function posUid(prefix) {
        return (prefix || 'id') + '-' + Date.now().toString(36) + '-' +
            Math.random().toString(36).slice(2, 8);
    }

    function PosModule() {
        BusinessModule.call(this, {
            id: 'pos',
            version: POS_VERSION,
            name: 'POS',
            description: 'Offline V2 POS — local stock reservation (no Inventory module).',
            moduleKind: 'pos',
            dependencies: [
                { id: 'identity', version: '>=1.0.0' }
            ],
            permissions: ['ui.contribute', 'services.register', 'db.read', 'sync.enqueue'],
            capabilities: [
                'ui.nav', 'route.register', 'services', 'settings', 'workspace', 'diagnostics',
                'pos.shell', 'pos.catalog', 'pos.cart', 'pos.checkout', 'pos.stock', 'pos.reservation'
            ],
            compat: {
                sdk: '>=1.0.0',
                runtime: '>=1.0.0',
                router: '>=1.0.0',
                shell: '>=1.0.0',
                sync: '>=1.0.0',
                db: '>=1.0.0',
                pm: '>=1.0.0'
            },
            routes: [
                { id: 'pos.home', path: '/pos', title: 'POS Catalog' },
                { id: 'pos.product', path: '/pos/product', title: 'POS Product' },
                { id: 'pos.cart', path: '/pos/cart', title: 'POS Cart' },
                { id: 'pos.checkout', path: '/pos/checkout', title: 'POS Checkout' },
                { id: 'pos.stock', path: '/pos/stock', title: 'POS Stock' },
                { id: 'pos.sales', path: '/pos/sales', title: 'POS Sales' },
                { id: 'pos.settings', path: '/pos/settings', title: 'POS Settings' }
            ],
            config: {
                catalogReadOnly: true,
                cartLocalOnly: true,
                stockReadOnly: true,
                salesLogic: true,
                payment: false,
                openDbOnRegister: false,
                startSyncOnActivate: false,
                identityDependency: 'identity'
            }
        });
        this._catalog = null;
        this._cart = null;
        this._stock = null;
        this._selectedProductId = null;
        this._lastCompleted = null;
        this._catalogUi = {
            q: '',
            category_id: ''
        };
    }

    PosModule.prototype = Object.create(BusinessModule.prototype);
    PosModule.prototype.constructor = PosModule;

    PosModule.prototype._callIdentity = function (name, arg) {
        return this.callPublished('identity', name, arg);
    };

    PosModule.prototype.requireIdentity = function () {
        var self = this;
        return Promise.all([
            self._callIdentity('session'),
            self._callIdentity('claims'),
            self._callIdentity('rbac')
        ]).then(function (parts) {
            var session = parts[0] || {};
            var claims = parts[1];
            var rbac = parts[2];
            if (!claims || !claims.company_id) {
                throw new Error('pos_identity_not_enrolled');
            }
            var perms = (rbac && rbac.permissions) || [];
            var allowed = perms.indexOf('pos.manage') !== -1 ||
                perms.indexOf('pos.view') !== -1 ||
                perms.indexOf('pos.sell') !== -1 ||
                perms.indexOf('*') !== -1;
            return {
                session: session,
                claims: claims,
                rbac: rbac,
                company_id: claims.company_id,
                branch_id: claims.branch_id || 0,
                user_id: claims.user_id,
                unlocked: !!(session && session.unlocked),
                allowed: allowed,
                permissions: perms
            };
        });
    };

    PosModule.prototype._gate = function () {
        return this.requireIdentity().then(function (idCtx) {
            if (!idCtx.allowed) {
                throw new Error('pos_forbidden');
            }
            return idCtx;
        });
    };

    PosModule.prototype._getCatalog = function () {
        if (this._catalog) {
            return this._catalog;
        }
        var api = root.RatebOfflineV2PosCatalog;
        if (!api || typeof api.create !== 'function') {
            throw new Error('pos_catalog_missing');
        }
        this._catalog = api.create(this);
        return this._catalog;
    };

    PosModule.prototype._getCart = function () {
        if (this._cart) {
            return this._cart;
        }
        var api = root.RatebOfflineV2PosCart;
        if (!api || typeof api.create !== 'function') {
            throw new Error('pos_cart_missing');
        }
        this._cart = api.create(this);
        return this._cart;
    };

    PosModule.prototype._getStock = function () {
        if (this._stock) {
            return this._stock;
        }
        var api = root.RatebOfflineV2PosStock;
        if (!api || typeof api.create !== 'function') {
            throw new Error('pos_stock_missing');
        }
        this._stock = api.create(this);
        return this._stock;
    };

    PosModule.prototype.listCategories = function () {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._getCatalog().listCategories(idCtx.company_id);
        });
    };

    PosModule.prototype.listProducts = function (filters) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._getCatalog().listProducts(idCtx.company_id, filters || {});
        });
    };

    PosModule.prototype.searchProducts = function (query, filters) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._getCatalog().searchProducts(idCtx.company_id, query, filters || {});
        });
    };

    PosModule.prototype.getProduct = function (productId) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._getCatalog().getProduct(idCtx.company_id, productId);
        });
    };

    PosModule.prototype.getCatalogStatus = function () {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._getCatalog().getCatalogStatus(idCtx.company_id);
        });
    };

    PosModule.prototype.getCart = function () {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._getCart().getCart(idCtx);
        });
    };

    PosModule.prototype.addToCart = function (productId, qty) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._getCatalog().getProduct(idCtx.company_id, productId).then(function (product) {
                if (!product) {
                    throw new Error('pos_product_not_found');
                }
                return self._getCart().addProduct(idCtx, product, qty);
            });
        });
    };

    PosModule.prototype.removeCartLine = function (lineId) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._getCart().removeLine(idCtx, lineId);
        });
    };

    PosModule.prototype.updateCartQuantity = function (lineId, qty) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._getCart().updateQuantity(idCtx, lineId, qty);
        });
    };

    PosModule.prototype.listStock = function () {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._getStock().listStock(idCtx.company_id);
        });
    };

    PosModule.prototype.getStockAvailability = function (productId) {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._getStock().getAvailability(idCtx.company_id, productId);
        });
    };

    PosModule.prototype.checkCartStock = function () {
        var self = this;
        return this._gate().then(function (idCtx) {
            return self._getCart().getCart(idCtx).then(function (cart) {
                return self._getStock().checkLines(idCtx.company_id, cart.lines || []);
            });
        });
    };

    PosModule.prototype.completeSale = function () {
        var self = this;
        return this._gate().then(function (idCtx) {
            /*
             * Flow: OPEN cart → stock check → reservation → sale completed → outbox.
             * Soft stock warnings only; reservations are POS-local (inv.* untouched).
             */
            return self._getCart().getCart(idCtx).then(function (cart) {
                var saleId = posUid('sale');
                return self._getStock().checkLines(idCtx.company_id, cart.lines || [])
                    .catch(function () {
                        return {
                            ok: true,
                            blocked: false,
                            reserved: false,
                            warnings: [],
                            warning_count: 0,
                            check_failed: true
                        };
                    })
                    .then(function (stockCheck) {
                        return self._getStock().reserveForSale(idCtx, {
                            sale_id: saleId,
                            lines: cart.lines || []
                        }).then(function (reservation) {
                            var reservationIds = (reservation.reservations || []).map(function (r) {
                                return r.reservation_id;
                            });
                            return self._getCart().completeSale(idCtx, {
                                sale_id: saleId,
                                reservation_ids: reservationIds,
                                stock_reserved: true
                            }).then(function (result) {
                                result.stock_warnings = (stockCheck && stockCheck.warnings) || [];
                                result.stock_warning_count = result.stock_warnings.length;
                                result.stock_blocked = false;
                                result.stock_reserved = true;
                                result.reservations = reservation.reservations || [];
                                result.reservation_count = reservation.reserved_count || 0;
                                result.reservation_availability = reservation.availability || [];
                                result.inventory_deducted = false;
                                result.inventory_touched = false;
                                if (result.sale) {
                                    result.sale.stock_warnings = result.stock_warnings;
                                    result.sale.stock_check_ok = !(stockCheck && stockCheck.warnings &&
                                        stockCheck.warnings.length);
                                    result.sale.reservation_ids = reservationIds;
                                    result.sale.stock_reserved = true;
                                }
                                self._lastCompleted = result;
                                return result;
                            }, function (err) {
                                return self._getStock().getReservation()
                                    .releaseForSale(idCtx, saleId)
                                    .catch(function () { return null; })
                                    .then(function () { throw err; });
                            });
                        });
                    });
            });
        });
    };

    PosModule.prototype.completeDraftPlaceholder = function () {
        return this.completeSale();
    };

    PosModule.prototype.getLastCompletedSale = function () {
        var self = this;
        if (self._lastCompleted && self._lastCompleted.sale) {
            return Promise.resolve(self._lastCompleted);
        }
        return this._gate().then(function (idCtx) {
            return self._getCart().getLastCompletedSale(idCtx).then(function (sale) {
                if (!sale) {
                    return null;
                }
                return { ok: true, sale: sale, local_txn_no: sale.local_txn_no, sale_id: sale.id };
            });
        });
    };

    PosModule.prototype.onInitialize = function () {
        var self = this;
        /* No db.open() / sync.start() here. */
        self.exposeService('status', function () {
            return {
                ok: true,
                version: POS_VERSION,
                catalogReadOnly: true,
                cartLocalOnly: true,
                stockReadOnly: true,
                salesLogic: true,
                payment: false,
                inventoryDeduction: false,
                inventoryModuleRequired: false,
                localReservation: true,
                syncStart: false,
                catalogStoreOpen: !!(self._catalog && self._catalog.isStoreOpen()),
                cartStoreOpen: !!(self._cart && self._cart.isStoreOpen()),
                stockStoreOpen: !!(self._stock && self._stock.isStoreOpen()),
                lastSaleId: self._lastCompleted && self._lastCompleted.sale_id || null
            };
        });
        self.exposeService('gate', function () {
            return self._gate();
        });
        self.exposeService('listCategories', function () {
            return self.listCategories();
        });
        self.exposeService('listProducts', function (filters) {
            return self.listProducts(filters);
        });
        self.exposeService('searchProducts', function (query, filters) {
            return self.searchProducts(query, filters);
        });
        self.exposeService('getProduct', function (productId) {
            return self.getProduct(productId);
        });
        self.exposeService('getCatalogStatus', function () {
            return self.getCatalogStatus();
        });
        self.exposeService('getCart', function () {
            return self.getCart();
        });
        self.exposeService('addToCart', function (productId, qty) {
            return self.addToCart(productId, qty);
        });
        self.exposeService('removeCartLine', function (lineId) {
            return self.removeCartLine(lineId);
        });
        self.exposeService('updateCartQuantity', function (lineId, qty) {
            return self.updateCartQuantity(lineId, qty);
        });
        self.exposeService('completeSale', function () {
            return self.completeSale();
        });
        self.exposeService('completeDraftPlaceholder', function () {
            return self.completeDraftPlaceholder();
        });
        self.exposeService('getLastCompletedSale', function () {
            return self.getLastCompletedSale();
        });
        self.exposeService('listStock', function () {
            return self.listStock();
        });
        self.exposeService('getStockAvailability', function (productId) {
            return self.getStockAvailability(productId);
        });
        self.exposeService('checkCartStock', function () {
            return self.checkCartStock();
        });
        self.reportHealth('initialize', true, 'pos_stock_ready');
        return Promise.resolve();
    };

    PosModule.prototype.onMount = function () {
        this.contributeNav({ label: 'POS', path: '/pos', title: 'POS Catalog' });
        this.contributeNav({ label: 'POS Cart', path: '/pos/cart', title: 'POS Cart' });
        this.contributeNav({ label: 'POS Checkout', path: '/pos/checkout', title: 'POS Checkout' });
        this.contributeNav({ label: 'POS Stock', path: '/pos/stock', title: 'POS Stock' });
        this.contributeWorkspace({
            id: 'pos.workspace',
            title: 'POS Offline',
            description: 'Local sale + read-only stock snapshot — no Inventory module'
        });
        this.contributeSettings({
            id: 'pos.cart_local_only',
            label: 'Cart local only',
            value: true
        });
        this.contributeSettings({
            id: 'pos.stock_read_only',
            label: 'Stock read only',
            value: true
        });
        this.reportHealth('mount', true, 'contributions');
        return Promise.resolve();
    };

    PosModule.prototype.onActivate = function (ctx) {
        /* UI prep only — do not open catalog/cart/stock stores. */
        if (ctx && ctx.events) {
            ctx.events.emit('pos:ready', {
                version: POS_VERSION,
                depends_on: ['identity'],
                catalog_read_only: true,
                cart_local_only: true,
                stock_read_only: true,
                payment: false,
                sales_logic: true,
                sync_start: false,
                inventory_module: false
            });
        }
        this.reportHealth('activate', true, 'ready');
        return Promise.resolve();
    };

    PosModule.prototype._el = function (tag, text, attrs) {
        var node = root.document.createElement(tag);
        if (text != null) {
            node.textContent = String(text);
        }
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                node.setAttribute(k, String(attrs[k]));
            });
        }
        return node;
    };

    PosModule.prototype._navigate = function (path) {
        try {
            var router = root.RatebOfflineV2Runtime &&
                root.RatebOfflineV2Runtime.services &&
                root.RatebOfflineV2Runtime.services.get('router');
            if (router && typeof router.navigate === 'function') {
                return router.navigate(path);
            }
        } catch (eNav) { /* ignore */ }
        return Promise.resolve({ ok: false });
    };

    PosModule.prototype._renderCatalog = function (outlet, idCtx) {
        var self = this;
        var catalog = self._getCatalog();
        outlet.textContent = '';
        outlet.setAttribute('data-pos-shell', '/pos');
        outlet.setAttribute('data-pos-view', 'catalog');

        outlet.appendChild(self._el('h3', 'POS Catalog'));
        outlet.appendChild(self._el('p',
            'Local SQLite catalog · company=' + idCtx.company_id + ' · no network'));

        var toCart = self._el('button', 'Open cart', { type: 'button' });
        toCart.addEventListener('click', function () { self._navigate('/pos/cart'); });
        var toStock = self._el('button', 'Stock', { type: 'button' });
        toStock.addEventListener('click', function () { self._navigate('/pos/stock'); });
        outlet.appendChild(toCart);
        outlet.appendChild(toStock);

        var controls = self._el('div', null, { 'data-pos-catalog-controls': '1' });
        var search = self._el('input', null, {
            type: 'search',
            placeholder: 'Search name, SKU, barcode',
            value: self._catalogUi.q || '',
            'data-pos-catalog-q': '1'
        });
        var category = self._el('select', null, { 'data-pos-catalog-category': '1' });
        category.appendChild(self._el('option', 'All categories', { value: '' }));
        controls.appendChild(search);
        controls.appendChild(category);
        outlet.appendChild(controls);

        var listHost = self._el('div', null, { 'data-pos-catalog-list': '1' });
        outlet.appendChild(listHost);

        function paintList(products) {
            listHost.textContent = '';
            listHost.appendChild(self._el('p', 'Products: ' + products.length));
            if (!products.length) {
                listHost.appendChild(self._el('p', 'No products match.'));
                return;
            }
            var ul = self._el('ul');
            products.forEach(function (p) {
                var li = self._el('li');
                var btn = self._el('button', (p.name || p.id) + ' · ' + (p.price != null ? p.price : '') +
                    ' ' + (p.currency || ''), { type: 'button', 'data-product-id': p.id });
                btn.addEventListener('click', function () {
                    self._selectedProductId = p.id;
                    self._navigate('/pos/product');
                });
                var add = self._el('button', 'Add', {
                    type: 'button',
                    'data-pos-add': p.id
                });
                add.addEventListener('click', function () {
                    self.addToCart(p.id, 1).then(function () {
                        add.textContent = 'Added';
                    }).catch(function (err) {
                        add.textContent = String(err && err.message ? err.message : err);
                    });
                });
                li.appendChild(btn);
                li.appendChild(add);
                ul.appendChild(li);
            });
            listHost.appendChild(ul);
        }

        function refresh() {
            self._catalogUi.q = String(search.value || '');
            self._catalogUi.category_id = String(category.value || '');
            return catalog.listProducts(idCtx.company_id, {
                q: self._catalogUi.q,
                category_id: self._catalogUi.category_id
            }).then(paintList);
        }

        return catalog.listCategories(idCtx.company_id).then(function (cats) {
            (cats || []).forEach(function (c) {
                var opt = self._el('option', c.name || c.id, { value: c.id });
                if (String(c.id) === String(self._catalogUi.category_id || '')) {
                    opt.selected = true;
                }
                category.appendChild(opt);
            });
            search.addEventListener('input', function () { refresh(); });
            category.addEventListener('change', function () { refresh(); });
            return refresh();
        });
    };

    PosModule.prototype._renderProduct = function (outlet, idCtx) {
        var self = this;
        var productId = self._selectedProductId;
        outlet.textContent = '';
        outlet.setAttribute('data-pos-shell', '/pos/product');
        outlet.setAttribute('data-pos-view', 'product');
        outlet.appendChild(self._el('h3', 'POS Product'));

        var back = self._el('button', 'Back to catalog', { type: 'button' });
        back.addEventListener('click', function () { self._navigate('/pos'); });
        outlet.appendChild(back);

        if (!productId) {
            outlet.appendChild(self._el('p', 'No product selected.'));
            return Promise.resolve();
        }

        return self._getCatalog().getProduct(idCtx.company_id, productId).then(function (p) {
            if (!p) {
                outlet.appendChild(self._el('p', 'Product not found: ' + productId));
                return;
            }
            outlet.setAttribute('data-pos-product-id', p.id);
            outlet.appendChild(self._el('p', 'Name: ' + (p.name || '')));
            outlet.appendChild(self._el('p', 'SKU: ' + (p.sku || '')));
            outlet.appendChild(self._el('p', 'Barcode: ' + (p.barcode || '')));
            outlet.appendChild(self._el('p', 'Category: ' + (p.category_id || '')));
            outlet.appendChild(self._el('p', 'Price: ' + (p.price != null ? p.price : '') +
                ' ' + (p.currency || '')));
            outlet.appendChild(self._el('p', 'Unit: ' + (p.unit || '')));
            outlet.appendChild(self._el('p', 'Source: ' + (p.source || 'local')));

            var add = self._el('button', 'Add to cart', { type: 'button', 'data-pos-add-cart': '1' });
            var msg = self._el('p', '');
            add.addEventListener('click', function () {
                self.addToCart(p.id, 1).then(function (cart) {
                    msg.textContent = 'In cart · lines=' + cart.line_count +
                        ' · total=' + cart.total + ' ' + (cart.currency || '');
                }).catch(function (err) {
                    msg.textContent = String(err && err.message ? err.message : err);
                });
            });
            var goCart = self._el('button', 'View cart', { type: 'button' });
            goCart.addEventListener('click', function () { self._navigate('/pos/cart'); });
            outlet.appendChild(add);
            outlet.appendChild(goCart);
            outlet.appendChild(msg);
        });
    };

    PosModule.prototype._renderCart = function (outlet, idCtx) {
        var self = this;
        outlet.textContent = '';
        outlet.setAttribute('data-pos-shell', '/pos/cart');
        outlet.setAttribute('data-pos-view', 'cart');
        outlet.appendChild(self._el('h3', 'POS Cart'));
        outlet.appendChild(self._el('p',
            'Local draft sale · no payment / receipt / sync · company=' + idCtx.company_id));

        var nav = self._el('div');
        var toCat = self._el('button', 'Catalog', { type: 'button' });
        toCat.addEventListener('click', function () { self._navigate('/pos'); });
        nav.appendChild(toCat);
        outlet.appendChild(nav);

        var host = self._el('div', null, { 'data-pos-cart-host': '1' });
        outlet.appendChild(host);

        function paint(cart) {
            host.textContent = '';
            host.setAttribute('data-pos-cart-empty', cart.empty ? '1' : '0');
            host.setAttribute('data-pos-cart-lines', String(cart.line_count || 0));
            host.setAttribute('data-pos-cart-total', String(cart.total || 0));

            if (cart.empty) {
                host.appendChild(self._el('p', 'Cart is empty.'));
                return;
            }

            var ul = self._el('ul');
            (cart.lines || []).forEach(function (line) {
                var li = self._el('li', null, { 'data-line-id': line.id });
                li.appendChild(self._el('span',
                    (line.name || line.product_id) + ' · ' + line.qty + ' × ' +
                    line.unit_price + ' = ' + line.line_total + ' ' + (line.currency || '')));

                var dec = self._el('button', '−', { type: 'button' });
                var inc = self._el('button', '+', { type: 'button' });
                var rm = self._el('button', 'Remove', { type: 'button' });
                dec.addEventListener('click', function () {
                    self.updateCartQuantity(line.id, Number(line.qty) - 1).then(paint);
                });
                inc.addEventListener('click', function () {
                    self.updateCartQuantity(line.id, Number(line.qty) + 1).then(paint);
                });
                rm.addEventListener('click', function () {
                    self.removeCartLine(line.id).then(paint);
                });
                li.appendChild(dec);
                li.appendChild(inc);
                li.appendChild(rm);
                ul.appendChild(li);
            });
            host.appendChild(ul);
            host.appendChild(self._el('p',
                'Lines: ' + cart.line_count +
                ' · Subtotal: ' + cart.subtotal +
                ' · Total: ' + cart.total + ' ' + (cart.currency || '')));
            host.appendChild(self._el('p',
                'Draft: ' + (cart.draft && cart.draft.id) +
                ' · status=' + (cart.draft && cart.draft.status)));
            var toCheckout = self._el('button', 'Checkout', {
                type: 'button',
                'data-pos-checkout': '1'
            });
            toCheckout.addEventListener('click', function () {
                self._navigate('/pos/checkout');
            });
            host.appendChild(toCheckout);
            return self._getStock().checkLines(idCtx.company_id, cart.lines || []).then(function (check) {
                if (check && check.warnings && check.warnings.length) {
                    var warn = self._el('div', null, { 'data-pos-stock-warn': '1' });
                    warn.appendChild(self._el('p',
                        'Stock warnings (sale not blocked): ' + check.warning_count));
                    check.warnings.forEach(function (w) {
                        warn.appendChild(self._el('p', w.message));
                    });
                    host.appendChild(warn);
                }
            }).catch(function () { /* soft */ });
        }

        /* First cart view action opens DB lazily via getCart. */
        return self._getCart().getCart(idCtx).then(paint);
    };

    PosModule.prototype._renderStock = function (outlet, idCtx) {
        var self = this;
        outlet.textContent = '';
        outlet.setAttribute('data-pos-shell', '/pos/stock');
        outlet.setAttribute('data-pos-view', 'stock');
        outlet.appendChild(self._el('h3', 'POS Stock'));
        outlet.appendChild(self._el('p',
            'Local snapshot · POS reservations reduce available only · inv.* untouched · company=' +
            idCtx.company_id));

        var nav = self._el('div');
        var toCat = self._el('button', 'Catalog', { type: 'button' });
        toCat.addEventListener('click', function () { self._navigate('/pos'); });
        var toCart = self._el('button', 'Cart', { type: 'button' });
        toCart.addEventListener('click', function () { self._navigate('/pos/cart'); });
        nav.appendChild(toCat);
        nav.appendChild(toCart);
        outlet.appendChild(nav);

        var host = self._el('div', null, { 'data-pos-stock-host': '1' });
        outlet.appendChild(host);

        /* Stock screen opens DB lazily. */
        return self._getStock().listStock(idCtx.company_id).then(function (rows) {
            host.textContent = '';
            host.setAttribute('data-pos-stock-count', String(rows.length));
            if (!rows.length) {
                host.setAttribute('data-pos-stock-empty', '1');
                host.appendChild(self._el('p', 'No stock snapshot rows.'));
                return;
            }
            var ul = self._el('ul');
            rows.forEach(function (row) {
                var li = self._el('li', null, {
                    'data-product-id': row.product_id,
                    'data-available': row.available ? '1' : '0',
                    'data-qty': String(row.available_qty),
                    'data-reserved': String(row.reserved_qty || 0),
                    'data-on-hand': String(row.on_hand_qty || 0)
                });
                li.appendChild(self._el('span',
                    (row.name || row.product_id) +
                    ' · on_hand=' + (row.on_hand_qty != null ? row.on_hand_qty : row.available_qty) +
                    ' · reserved=' + (row.reserved_qty || 0) +
                    ' · available_after=' + row.available_qty +
                    (row.available ? '' : ' · UNAVAILABLE') +
                    (row.warehouse_id ? (' · wh=' + row.warehouse_id) : '')));
                ul.appendChild(li);
            });
            host.appendChild(ul);
            host.appendChild(self._el('p',
                'Rows: ' + rows.length +
                ' · available = on_hand − ACTIVE reservations · inventory_module=false'));
        });
    };

    PosModule.prototype._renderCheckout = function (outlet, idCtx) {
        var self = this;
        outlet.textContent = '';
        outlet.setAttribute('data-pos-shell', '/pos/checkout');
        outlet.setAttribute('data-pos-view', 'checkout');
        outlet.appendChild(self._el('h3', 'POS Checkout'));
        outlet.appendChild(self._el('p',
            'Complete local sale · outbox only · no payment / network / stock · company=' +
            idCtx.company_id));

        var nav = self._el('div');
        var toCart = self._el('button', 'Cart', { type: 'button' });
        toCart.addEventListener('click', function () { self._navigate('/pos/cart'); });
        var toCat = self._el('button', 'Catalog', { type: 'button' });
        toCat.addEventListener('click', function () { self._navigate('/pos'); });
        nav.appendChild(toCart);
        nav.appendChild(toCat);
        outlet.appendChild(nav);

        var host = self._el('div', null, { 'data-pos-checkout-host': '1' });
        outlet.appendChild(host);

        function paintSuccess(result) {
            host.textContent = '';
            host.setAttribute('data-pos-checkout-state', 'success');
            host.setAttribute('data-pos-sale-id', String(result.sale_id || ''));
            host.setAttribute('data-pos-local-txn', String(result.local_txn_no || ''));
            host.appendChild(self._el('h4', 'Sale completed locally'));
            host.appendChild(self._el('p',
                'Local transaction: ' + (result.local_txn_no || result.sale_id)));
            host.appendChild(self._el('p',
                'Sale id: ' + result.sale_id +
                ' · status=' + (result.sale && result.sale.status) +
                ' · completed_at=' + (result.completed_at || '')));
            host.appendChild(self._el('p',
                'Lines: ' + (result.sale && result.sale.line_count) +
                ' · Total: ' + (result.sale && result.sale.total) + ' ' +
                ((result.sale && result.sale.currency) || '')));
            var outbox = result.outbox || {};
            host.appendChild(self._el('p',
                'Outbox: ' + (outbox.operation || 'CREATE_POS_SALE') +
                ' · status=' + (outbox.status || 'pending') +
                ' · client_id=' + (outbox.client_id || '')));
            host.appendChild(self._el('p',
                'sync_started=false · inventory_deducted=false · network=false'));
            if (result.reservation_count) {
                host.appendChild(self._el('p',
                    'Reservations: ' + result.reservation_count + ' ACTIVE · stock_reserved=true'));
                (result.reservation_availability || []).forEach(function (a) {
                    host.appendChild(self._el('p',
                        a.product_id + ' · reserved=' + a.reserved_qty +
                        ' · available_after=' + a.available_after_reservation));
                });
            }
            if (result.stock_warnings && result.stock_warnings.length) {
                host.appendChild(self._el('p',
                    'Stock warnings at complete: ' + result.stock_warning_count +
                    ' · blocked=false'));
                result.stock_warnings.forEach(function (w) {
                    host.appendChild(self._el('p', w.message));
                });
            }
            var again = self._el('button', 'New sale', { type: 'button' });
            again.addEventListener('click', function () { self._navigate('/pos'); });
            host.appendChild(again);
        }

        function paintSummary(cart) {
            host.textContent = '';
            if (cart.empty) {
                host.setAttribute('data-pos-checkout-state', 'empty');
                host.appendChild(self._el('p', 'Cart is empty — add products before checkout.'));
                var back = self._el('button', 'Open cart', { type: 'button' });
                back.addEventListener('click', function () { self._navigate('/pos/cart'); });
                host.appendChild(back);
                return;
            }
            host.setAttribute('data-pos-checkout-state', 'summary');
            host.appendChild(self._el('h4', 'Sale summary'));
            var ul = self._el('ul');
            (cart.lines || []).forEach(function (line) {
                ul.appendChild(self._el('li',
                    (line.name || line.product_id) + ' · ' + line.qty + ' × ' +
                    line.unit_price + ' = ' + line.line_total + ' ' + (line.currency || '')));
            });
            host.appendChild(ul);
            host.appendChild(self._el('p',
                'Lines: ' + cart.line_count +
                ' · Subtotal: ' + cart.subtotal +
                ' · Total: ' + cart.total + ' ' + (cart.currency || '')));
            host.appendChild(self._el('p',
                'Draft: ' + (cart.draft && cart.draft.id) +
                ' · status=' + (cart.draft && cart.draft.status)));

            var warnHost = self._el('div', null, { 'data-pos-stock-warn': '1' });
            host.appendChild(warnHost);
            self._getStock().checkLines(idCtx.company_id, cart.lines || []).then(function (check) {
                warnHost.textContent = '';
                if (check && check.warnings && check.warnings.length) {
                    warnHost.appendChild(self._el('p',
                        'Stock warnings (sale will NOT be blocked): ' + check.warning_count));
                    check.warnings.forEach(function (w) {
                        warnHost.appendChild(self._el('p', w.message));
                    });
                }
            }).catch(function () { /* soft */ });

            var msg = self._el('p', '', { 'data-pos-checkout-msg': '1' });
            var completeBtn = self._el('button', 'Complete sale', {
                type: 'button',
                'data-pos-complete-sale': '1'
            });
            completeBtn.addEventListener('click', function () {
                msg.textContent = 'Completing…';
                completeBtn.disabled = true;
                self.completeSale().then(function (result) {
                    paintSuccess(result);
                }).catch(function (err) {
                    completeBtn.disabled = false;
                    msg.textContent = String(err && err.message ? err.message : err);
                });
            });
            host.appendChild(completeBtn);
            host.appendChild(msg);
        }

        /* Checkout action opens DB lazily via getCart / completeSale. */
        return self._getCart().getCart(idCtx).then(function (cart) {
            if (!cart.empty) {
                paintSummary(cart);
                return;
            }
            if (self._lastCompleted && self._lastCompleted.sale) {
                paintSuccess(self._lastCompleted);
                return;
            }
            return self._getCart().getLastCompletedSale(idCtx).then(function (sale) {
                if (sale) {
                    paintSuccess({
                        ok: true,
                        sale: sale,
                        sale_id: sale.id,
                        local_txn_no: sale.local_txn_no,
                        completed_at: sale.completed_at,
                        outbox: {
                            operation: 'CREATE_POS_SALE',
                            status: 'pending',
                            client_id: sale.outbox_client_id || ''
                        }
                    });
                    return;
                }
                paintSummary(cart);
            });
        });
    };

    PosModule.prototype._renderSalesPlaceholder = function (outlet, idCtx) {
        var self = this;
        outlet.textContent = '';
        outlet.setAttribute('data-pos-shell', '/pos/sales');
        outlet.appendChild(self._el('h3', 'POS Sales'));
        outlet.appendChild(self._el('p',
            'Local completed sales via checkout · no payment · company=' + idCtx.company_id));
        var btn = self._el('button', 'Open checkout', { type: 'button' });
        btn.addEventListener('click', function () { self._navigate('/pos/checkout'); });
        outlet.appendChild(btn);
    };

    PosModule.prototype._renderSettings = function (outlet, idCtx) {
        var self = this;
        outlet.textContent = '';
        outlet.setAttribute('data-pos-shell', '/pos/settings');
        outlet.appendChild(self._el('h3', 'POS Settings'));
        var statusHost = self._el('pre', 'Loading…');
        outlet.appendChild(statusHost);
        return self._getCatalog().getCatalogStatus(idCtx.company_id).then(function (st) {
            statusHost.textContent = JSON.stringify({
                catalog: st,
                cartStoreOpen: !!(self._cart && self._cart.isStoreOpen()),
                payment: false,
                sync: false
            }, null, 2);
        });
    };

    PosModule.prototype.createRouteHandler = function (route) {
        var self = this;
        var path = route && route.path ? route.path : '/pos';
        return {
            init: function () { return Promise.resolve(); },
            mount: function (outlet) {
                return self._gate().then(function (idCtx) {
                    if (path === '/pos/product') {
                        return self._renderProduct(outlet, idCtx);
                    }
                    if (path === '/pos/cart') {
                        return self._renderCart(outlet, idCtx);
                    }
                    if (path === '/pos/checkout') {
                        return self._renderCheckout(outlet, idCtx);
                    }
                    if (path === '/pos/stock') {
                        return self._renderStock(outlet, idCtx);
                    }
                    if (path === '/pos/sales') {
                        self._renderSalesPlaceholder(outlet, idCtx);
                        return null;
                    }
                    if (path === '/pos/settings') {
                        return self._renderSettings(outlet, idCtx);
                    }
                    return self._renderCatalog(outlet, idCtx);
                }).catch(function (err) {
                    outlet.textContent = 'POS: ' + String(err && err.message ? err.message : err);
                });
            },
            unmount: function () { return Promise.resolve(); },
            dispose: function () { return Promise.resolve(); }
        };
    };

    PosModule.prototype.getDiagnostics = function () {
        var base = BusinessModule.prototype.getDiagnostics.call(this);
        base.depends_on = ['identity'];
        base.catalog_read_only = true;
        base.cart_local_only = true;
        base.stock_read_only = true;
        base.sales_logic = true;
        base.payment = false;
        base.inventory_deduction = false;
        base.inventory_module_required = false;
        base.opens_db_on_register = false;
        base.starts_sync_on_activate = false;
        base.catalog_store_open = !!(this._catalog && this._catalog.isStoreOpen());
        base.cart_store_open = !!(this._cart && this._cart.isStoreOpen());
        base.stock_store_open = !!(this._stock && this._stock.isStoreOpen());
        base.never_stores_credentials = true;
        base.sqlite_tables_added = false;
        base.outbox_operation = 'CREATE_POS_SALE';
        base.local_reservation = true;
        base.entity_types = [
            'pos.category', 'pos.product', 'pos.catalog_meta',
            'pos.sale_draft', 'pos.sale_line', 'pos.cart_session', 'pos.sale',
            'pos.stock_snapshot', 'pos.stock_meta', 'pos.stock_reservation'
        ];
        base.storage = 'entity_row via pos.* prefix + optional inv.item SELECT + sync_outbox enqueue';
        return base;
    };

    function createPosModule() {
        return new PosModule();
    }

    root.RatebOfflineV2Pos = {
        __locked: true,
        version: POS_VERSION,
        PosModule: PosModule,
        create: createPosModule
    };

    if (Business) {
        Business.createPosModule = createPosModule;
        Business.PosModule = PosModule;
    }
})(typeof window !== 'undefined' ? window : this);
