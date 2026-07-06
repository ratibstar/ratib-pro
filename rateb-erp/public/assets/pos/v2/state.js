/** @typedef {import('./api.js').PosV2Config} PosV2Config */

/** @typedef {'idle'|'loading'|'loadingMore'|'error'|'empty'|'ready'} CatalogStatus */

/**
 * @typedef {object} CatalogState
 * @property {import('./api.js').CatalogProduct[]} products
 * @property {import('./api.js').Category[]} categories
 * @property {number|null} selectedCategoryId
 * @property {string} searchQuery
 * @property {number} page
 * @property {number} lastPage
 * @property {number} total
 * @property {CatalogStatus} status
 * @property {string} errorMessage
 */

/**
 * @typedef {object} AppState
 * @property {boolean} bootstrapped
 * @property {boolean} bootstrapLoading
 * @property {string|null} bootstrapError
 * @property {import('./api.js').Cart|null} cart
 * @property {import('./api.js').RegisterMeta|null} register
 * @property {CatalogState} catalog
 * @property {boolean} cartBusy
 * @property {boolean} customerBusy
 * @property {boolean} discountBusy
 * @property {string} locale
 * @property {boolean} rtl
 */

/** @type {AppState} */
const initialCatalog = {
    products: [],
    categories: [],
    selectedCategoryId: null,
    searchQuery: '',
    page: 0,
    lastPage: 1,
    total: 0,
    status: 'idle',
    errorMessage: '',
};

/** @type {AppState} */
export const state = {
    bootstrapped: false,
    bootstrapLoading: false,
    bootstrapError: null,
    cart: null,
    register: null,
    catalog: { ...initialCatalog },
    cartBusy: false,
    customerBusy: false,
    discountBusy: false,
    locale: 'en',
    rtl: false,
};

/** @type {Map<string, Set<(value: unknown) => void>>} */
const listeners = new Map();

/**
 * @param {string} key
 * @param {(value: unknown) => void} fn
 * @returns {() => void}
 */
export function subscribe(key, fn) {
    if (!listeners.has(key)) {
        listeners.set(key, new Set());
    }
    listeners.get(key).add(fn);
    return () => listeners.get(key)?.delete(fn);
}

/**
 * @param {string} key
 * @param {unknown} [payload]
 */
export function emit(key, payload) {
    const set = listeners.get(key);
    if (!set) {
        return;
    }
    set.forEach((fn) => {
        try {
            fn(payload);
        } catch (err) {
            console.error('[pos-v2] listener error', key, err);
        }
    });
}

/**
 * @param {Partial<AppState>} patch
 */
export function patchState(patch) {
    Object.assign(state, patch);
    emit('state', state);
}

/**
 * @param {Partial<CatalogState>} patch
 */
export function patchCatalog(patch) {
    state.catalog = { ...state.catalog, ...patch };
    emit('catalog', state.catalog);
    emit('state', state);
}

export function resetCatalog() {
    state.catalog = {
        ...initialCatalog,
        categories: state.catalog.categories,
        selectedCategoryId: state.catalog.selectedCategoryId,
        searchQuery: state.catalog.searchQuery,
    };
    emit('catalog', state.catalog);
}

/**
 * @param {import('./api.js').Cart} cart
 */
export function setCart(cart) {
    state.cart = cart;
    emit('cart', cart);
    emit('state', state);
}
