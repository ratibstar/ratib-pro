import { searchCatalog } from './api.js';
import { patchCatalog, resetCatalog, state } from './state.js';
import { escapeHtml, formatMoney, showAlert, t, toggle, debounce } from './ui.js';

/** @type {boolean} */
let loadingMore = false;

/** @type {IntersectionObserver|null} */
let sentinelObserver = null;

const root = () => document.querySelector('[data-pos-register]');

function els() {
    const r = root();
    return {
        grid: r?.querySelector('[data-pos-product-grid]'),
        categories: r?.querySelector('[data-pos-categories]'),
        categoriesLoading: r?.querySelector('[data-pos-categories-loading]'),
        search: /** @type {HTMLInputElement|null} */ (r?.querySelector('[data-pos-search]')),
        searchClear: r?.querySelector('[data-pos-search-clear]'),
        scroll: r?.querySelector('[data-pos-catalog-scroll]'),
        empty: r?.querySelector('[data-pos-catalog-empty]'),
        error: r?.querySelector('[data-pos-catalog-error]'),
        errorMsg: r?.querySelector('[data-pos-catalog-error-msg]'),
        retry: r?.querySelector('[data-pos-catalog-retry]'),
        meta: r?.querySelector('[data-pos-catalog-meta]'),
        spinner: r?.querySelector('[data-pos-catalog-spinner]'),
        loadMore: r?.querySelector('[data-pos-catalog-load-more]'),
    };
}

/**
 * @param {import('./api.js').Category[]} categories
 */
export function renderCategories(categories) {
    const { categories: nav, categoriesLoading } = els();
    if (!nav) {
        return;
    }
    toggle(categoriesLoading, false);
    const selected = state.catalog.selectedCategoryId;
    const allBtn = `<button type="button" class="pos-v2-category-btn${selected == null ? ' is-active' : ''}" data-pos-category data-category-id="">${escapeHtml(t('all_categories', 'All categories'))}</button>`;
    const items = (categories || []).map((cat) => {
        const active = selected === cat.id ? ' is-active' : '';
        const count = cat.product_count != null ? ` <span class="text-muted">(${cat.product_count})</span>` : '';
        return `<button type="button" class="pos-v2-category-btn${active}" data-pos-category data-category-id="${cat.id}">${escapeHtml(cat.name)}${count}</button>`;
    }).join('');
    nav.innerHTML = allBtn + items;

    nav.querySelectorAll('[data-pos-category]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const raw = btn.getAttribute('data-category-id');
            const id = raw === '' || raw == null ? null : Number(raw);
            selectCategory(Number.isNaN(id) ? null : id);
        });
    });
}

/**
 * @param {number|null} categoryId
 */
export function selectCategory(categoryId) {
    patchCatalog({ selectedCategoryId: categoryId });
    renderCategories(state.catalog.categories);
    loadCatalog({ reset: true });
}

/**
 * @param {{ reset?: boolean }} [opts]
 */
export async function loadCatalog(opts = {}) {
    const { reset = false } = opts;
    if (reset) {
        resetCatalog();
        patchCatalog({ status: 'loading', page: 0, products: [] });
        clearGrid();
    } else if (loadingMore || state.catalog.status === 'loading') {
        return;
    }

    const nextPage = reset ? 1 : state.catalog.page + 1;
    if (!reset && nextPage > state.catalog.lastPage) {
        return;
    }

    const isMore = !reset && nextPage > 1;
    patchCatalog({ status: isMore ? 'loadingMore' : 'loading' });
    updateCatalogChrome();

    try {
        const data = await searchCatalog({
            q: state.catalog.searchQuery || undefined,
            category_id: state.catalog.selectedCategoryId,
            page: nextPage,
            per_page: 24,
        });
        const products = data.products || [];
        const pagination = data.pagination || { page: nextPage, last_page: nextPage, total: products.length };
        const merged = reset ? products : [...state.catalog.products, ...products];

        patchCatalog({
            products: merged,
            page: pagination.page ?? nextPage,
            lastPage: pagination.last_page ?? nextPage,
            total: pagination.total ?? merged.length,
            status: merged.length === 0 ? 'empty' : 'ready',
            errorMessage: '',
        });

        if (reset) {
            renderProductGrid(merged);
        } else {
            appendProducts(products);
        }
    } catch (err) {
        const message = err instanceof Error ? err.message : t('catalog_error', 'Failed to load products.');
        if (reset) {
            patchCatalog({ status: 'error', errorMessage: message, products: [] });
            clearGrid();
        } else {
            patchCatalog({ status: state.catalog.products.length ? 'ready' : 'error', errorMessage: message });
        }
        showAlert(message);
    }

    updateCatalogChrome();
    setupInfiniteScroll();
}

function clearGrid() {
    const { grid } = els();
    if (grid) {
        grid.innerHTML = '';
    }
}

function updateCatalogChrome() {
    const { empty, error, errorMsg, meta, spinner, loadMore } = els();
    const cat = state.catalog;

    toggle(spinner, cat.status === 'loading');
    toggle(loadMore, cat.status === 'loadingMore');
    toggle(empty, cat.status === 'empty');
    toggle(error, cat.status === 'error');
    if (errorMsg) {
        errorMsg.textContent = cat.errorMessage || t('catalog_error', 'Failed to load products.');
    }
    if (meta) {
        if (cat.status === 'ready' || cat.status === 'loadingMore') {
            meta.textContent = t('products_count', '{n} products').replace('{n}', String(cat.total));
        } else {
            meta.textContent = '';
        }
    }
}

/**
 * @param {import('./api.js').CatalogProduct[]} products
 */
function renderProductGrid(products) {
    const { grid } = els();
    if (!grid) {
        return;
    }
    grid.innerHTML = products.map(productCardHtml).join('');
    bindProductCards(grid);
}

/**
 * @param {import('./api.js').CatalogProduct[]} products
 */
function appendProducts(products) {
    const { grid } = els();
    if (!grid || !products.length) {
        return;
    }
    const frag = document.createElement('div');
    frag.innerHTML = products.map(productCardHtml).join('');
    Array.from(frag.children).forEach((child) => grid.appendChild(child));
    bindProductCards(grid);
}

/**
 * @param {import('./api.js').CatalogProduct} product
 */
function productCardHtml(product) {
    const disabled = !product.in_stock;
    const cls = disabled ? ' is-disabled' : '';
    const img = product.image_url
        ? `<img class="pos-v2-product-img" src="${escapeHtml(product.image_url)}" alt="" loading="lazy">`
        : `<div class="pos-v2-product-img-placeholder" aria-hidden="true">📦</div>`;
    const stock = product.in_stock
        ? `<span class="badge text-bg-success pos-v2-stock-badge">${escapeHtml(t('in_stock', 'In stock'))}</span>`
        : `<span class="badge text-bg-secondary pos-v2-stock-badge">${escapeHtml(t('out_of_stock', 'Out of stock'))}</span>`;
    const weight = product.requires_weight
        ? `<span class="badge text-bg-warning pos-v2-stock-badge ms-1">${escapeHtml(t('weighted', 'Weighted'))}</span>`
        : '';

    return `<div class="col">
        <article class="pos-v2-product-card${cls}" data-pos-product-card data-product-id="${product.id}" tabindex="0" role="button" aria-label="${escapeHtml(product.name)}">
            ${img}
            <div class="pos-v2-product-body">
                <div class="pos-v2-product-name">${escapeHtml(product.name)}</div>
                <div class="pos-v2-product-sku">${escapeHtml(product.sku || '')}</div>
                <div class="d-flex align-items-center justify-content-between mt-2 gap-1 flex-wrap">
                    <span class="pos-v2-product-price">${escapeHtml(formatMoney(product.price))}</span>
                    <span>${stock}${weight}</span>
                </div>
            </div>
        </article>
    </div>`;
}

/**
 * @param {ParentNode} container
 */
function bindProductCards(container) {
    container.querySelectorAll('[data-pos-product-card]:not([data-bound])').forEach((card) => {
        card.setAttribute('data-bound', '1');
        const id = Number(card.getAttribute('data-product-id'));
        const handler = () => {
            if (card.classList.contains('is-disabled')) {
                return;
            }
            document.dispatchEvent(new CustomEvent('pos-v2:add-product', { detail: { productId: id, card } }));
        };
        card.addEventListener('click', handler);
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                handler();
            }
        });
    });
}

function setupInfiniteScroll() {
    const { scroll } = els();
    if (!scroll) {
        return;
    }

    let sentinel = scroll.querySelector('[data-pos-scroll-sentinel]');
    if (!sentinel) {
        sentinel = document.createElement('div');
        sentinel.setAttribute('data-pos-scroll-sentinel', '');
        sentinel.style.height = '1px';
        scroll.appendChild(sentinel);
    }

    if (sentinelObserver) {
        sentinelObserver.disconnect();
    }

    sentinelObserver = new IntersectionObserver(
        (entries) => {
            const entry = entries[0];
            if (!entry?.isIntersecting) {
                return;
            }
            if (state.catalog.page >= state.catalog.lastPage) {
                return;
            }
            if (state.catalog.status === 'loading' || state.catalog.status === 'loadingMore') {
                return;
            }
            loadingMore = true;
            loadCatalog({ reset: false }).finally(() => {
                loadingMore = false;
            });
        },
        { root: scroll, rootMargin: '120px', threshold: 0 },
    );
    sentinelObserver.observe(sentinel);
}

export function initCatalog() {
    const { search, searchClear, retry } = els();

    if (search) {
        const onSearch = debounce(() => {
            const q = search.value.trim();
            patchCatalog({ searchQuery: q });
            toggle(searchClear, q.length > 0);
            loadCatalog({ reset: true });
        }, 350);
        search.addEventListener('input', onSearch);
    }

    if (searchClear) {
        searchClear.addEventListener('click', () => {
            if (search) {
                search.value = '';
            }
            patchCatalog({ searchQuery: '' });
            toggle(searchClear, false);
            loadCatalog({ reset: true });
        });
    }

    if (retry) {
        retry.addEventListener('click', () => loadCatalog({ reset: true }));
    }
}
