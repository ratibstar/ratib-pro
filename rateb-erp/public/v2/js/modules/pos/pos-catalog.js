/*!
 * RATEB Offline V2 — POS local catalog read layer (Phase 2)
 *
 * Stores categories/products in existing entity_row via createDocStore (pos.*).
 * No network, no schema migration, no sync, no inventory writes.
 */
(function (root) {
    'use strict';

    var Business = root.RatebOfflineV2Business;
    if (!Business || typeof Business.createDocStore !== 'function') {
        return;
    }

    var ET = {
        category: 'pos.category',
        product: 'pos.product',
        meta: 'pos.catalog_meta'
    };

    function nowIso() {
        return new Date().toISOString();
    }

    function normalizeText(value) {
        return String(value || '').trim().toLowerCase();
    }

    function createPosCatalog(module) {
        var state = {
            store: null,
            seedPromise: null
        };

        function ensureStore() {
            if (state.store) {
                return Promise.resolve(state.store);
            }
            var db = module.ctx && module.ctx.db;
            if (!db) {
                return Promise.reject(new Error('pos_db_missing'));
            }
            /* Open only when catalog data is requested — not on register/activate. */
            return db.open().then(function () {
                state.store = Business.createDocStore(db, {
                    ownedPrefix: 'pos.',
                    errorCode: 'pos_forbidden_storage'
                });
                return state.store;
            });
        }

        function localSeedRows(companyId) {
            var categories = [
                {
                    id: 'cat-beverages',
                    company_id: companyId,
                    name: 'Beverages',
                    sort_order: 1,
                    source: 'local_seed'
                },
                {
                    id: 'cat-snacks',
                    company_id: companyId,
                    name: 'Snacks',
                    sort_order: 2,
                    source: 'local_seed'
                },
                {
                    id: 'cat-grocery',
                    company_id: companyId,
                    name: 'Grocery',
                    sort_order: 3,
                    source: 'local_seed'
                }
            ];
            var products = [
                {
                    id: 'prod-water-500',
                    company_id: companyId,
                    sku: 'WTR-500',
                    barcode: '6281000000011',
                    name: 'Mineral Water 500ml',
                    category_id: 'cat-beverages',
                    price: 1.5,
                    currency: 'SAR',
                    unit: 'each',
                    active: true,
                    source: 'local_seed'
                },
                {
                    id: 'prod-cola-330',
                    company_id: companyId,
                    sku: 'COLA-330',
                    barcode: '6281000000028',
                    name: 'Cola Can 330ml',
                    category_id: 'cat-beverages',
                    price: 2.5,
                    currency: 'SAR',
                    unit: 'each',
                    active: true,
                    source: 'local_seed'
                },
                {
                    id: 'prod-chips-40',
                    company_id: companyId,
                    sku: 'CHIPS-40',
                    barcode: '6281000000035',
                    name: 'Potato Chips 40g',
                    category_id: 'cat-snacks',
                    price: 3,
                    currency: 'SAR',
                    unit: 'each',
                    active: true,
                    source: 'local_seed'
                },
                {
                    id: 'prod-dates-1kg',
                    company_id: companyId,
                    sku: 'DATES-1KG',
                    barcode: '6281000000042',
                    name: 'Dates 1kg',
                    category_id: 'cat-grocery',
                    price: 28,
                    currency: 'SAR',
                    unit: 'kg',
                    active: true,
                    source: 'local_seed'
                },
                {
                    id: 'prod-milk-1l',
                    company_id: companyId,
                    sku: 'MILK-1L',
                    barcode: '6281000000059',
                    name: 'Fresh Milk 1L',
                    category_id: 'cat-grocery',
                    price: 6.5,
                    currency: 'SAR',
                    unit: 'each',
                    active: true,
                    source: 'local_seed'
                }
            ];
            return { categories: categories, products: products };
        }

        function ensureLocalSeed(companyId) {
            if (state.seedPromise) {
                return state.seedPromise;
            }
            state.seedPromise = ensureStore().then(function (store) {
                return store.get(ET.meta, 'seed', companyId).then(function (meta) {
                    if (meta && meta.payload && meta.payload.seeded) {
                        return { ok: true, seeded: false, already: true };
                    }
                    return store.list(ET.product, companyId).then(function (existing) {
                        if (existing && existing.length) {
                            return store.put(ET.meta, 'seed', {
                                company_id: companyId,
                                seeded: true,
                                source: 'local_seed',
                                skipped: true,
                                updated_at: nowIso()
                            }, 1).then(function () {
                                return { ok: true, seeded: false, already: true };
                            });
                        }
                        var seed = localSeedRows(companyId);
                        var chain = Promise.resolve();
                        seed.categories.forEach(function (row) {
                            chain = chain.then(function () {
                                return store.put(ET.category, row.id, row, 1);
                            });
                        });
                        seed.products.forEach(function (row) {
                            chain = chain.then(function () {
                                return store.put(ET.product, row.id, row, 1);
                            });
                        });
                        return chain.then(function () {
                            return store.put(ET.meta, 'seed', {
                                company_id: companyId,
                                seeded: true,
                                source: 'local_seed',
                                product_count: seed.products.length,
                                category_count: seed.categories.length,
                                updated_at: nowIso()
                            }, 1);
                        }).then(function () {
                            return {
                                ok: true,
                                seeded: true,
                                product_count: seed.products.length,
                                category_count: seed.categories.length
                            };
                        });
                    });
                });
            }).then(function (res) {
                state.seedPromise = null;
                return res;
            }, function (err) {
                state.seedPromise = null;
                throw err;
            });
            return state.seedPromise;
        }

        function listCategories(companyId) {
            return ensureLocalSeed(companyId).then(function () {
                return ensureStore().then(function (store) {
                    return store.list(ET.category, companyId).then(function (rows) {
                        return (rows || []).map(function (r) {
                            return r.payload;
                        }).sort(function (a, b) {
                            return Number(a.sort_order || 0) - Number(b.sort_order || 0);
                        });
                    });
                });
            });
        }

        function listProducts(companyId, filters) {
            filters = filters || {};
            var q = normalizeText(filters.q || filters.query || '');
            var categoryId = filters.category_id ? String(filters.category_id) : '';
            return ensureLocalSeed(companyId).then(function () {
                return ensureStore().then(function (store) {
                    return store.list(ET.product, companyId).then(function (rows) {
                        return (rows || []).map(function (r) {
                            return r.payload;
                        }).filter(function (p) {
                            if (p && p.active === false) {
                                return false;
                            }
                            if (categoryId && String(p.category_id || '') !== categoryId) {
                                return false;
                            }
                            if (!q) {
                                return true;
                            }
                            var hay = [
                                p.name, p.sku, p.barcode, p.category_id
                            ].map(normalizeText).join(' ');
                            return hay.indexOf(q) !== -1;
                        }).sort(function (a, b) {
                            return String(a.name || '').localeCompare(String(b.name || ''));
                        });
                    });
                });
            });
        }

        function searchProducts(companyId, query, filters) {
            filters = filters || {};
            filters.q = query;
            return listProducts(companyId, filters);
        }

        function getProduct(companyId, productId) {
            if (!productId) {
                return Promise.reject(new Error('pos_product_id_required'));
            }
            return ensureLocalSeed(companyId).then(function () {
                return ensureStore().then(function (store) {
                    return store.get(ET.product, String(productId), companyId).then(function (row) {
                        if (!row || !row.payload) {
                            return null;
                        }
                        return row.payload;
                    });
                });
            });
        }

        function getCatalogStatus(companyId) {
            return ensureStore().then(function (store) {
                return Promise.all([
                    store.list(ET.product, companyId),
                    store.list(ET.category, companyId),
                    store.get(ET.meta, 'seed', companyId)
                ]).then(function (parts) {
                    return {
                        ok: true,
                        product_count: (parts[0] || []).length,
                        category_count: (parts[1] || []).length,
                        seed: parts[2] && parts[2].payload ? parts[2].payload : null,
                        network: false,
                        source: 'local_sqlite'
                    };
                });
            });
        }

        return {
            ET: ET,
            ensureStore: ensureStore,
            ensureLocalSeed: ensureLocalSeed,
            listCategories: listCategories,
            listProducts: listProducts,
            searchProducts: searchProducts,
            getProduct: getProduct,
            getCatalogStatus: getCatalogStatus,
            isStoreOpen: function () { return !!state.store; }
        };
    }

    root.RatebOfflineV2PosCatalog = {
        __locked: true,
        create: createPosCatalog,
        entityTypes: ET
    };
})(typeof window !== 'undefined' ? window : this);
