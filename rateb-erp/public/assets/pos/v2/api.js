/**
 * POS V2 API client — session admin routes only.
 */

/** @typedef {object} Money
 *  @property {string} amount
 *  @property {string} currency
 */

/** @typedef {object} CatalogProduct
 *  @property {number} id
 *  @property {string} sku
 *  @property {string} name
 *  @property {Money} price
 *  @property {string|null} image_url
 *  @property {boolean} in_stock
 *  @property {boolean} requires_weight
 */

/** @typedef {object} Category
 *  @property {number} id
 *  @property {string} name
 *  @property {number} [product_count]
 */

/** @typedef {object} Pagination
 *  @property {number} page
 *  @property {number} per_page
 *  @property {number} total
 *  @property {number} last_page
 */

/** @typedef {object} CartLine
 *  @property {string} line_id
 *  @property {number} product_id
 *  @property {string} name
 *  @property {string} qty
 *  @property {Money} unit_price
 *  @property {Money} line_total
 *  @property {object|null} discount
 */

/** @typedef {object} CartTotals
 *  @property {Money} subtotal
 *  @property {Money} discount
 *  @property {Money} total
 */

/** @typedef {object} CustomerSummary
 *  @property {number} id
 *  @property {string} name
 *  @property {string} [phone]
 *  @property {string} [email]
 */

/** @typedef {object} Cart
 *  @property {CartLine[]} lines
 *  @property {CartTotals} totals
 *  @property {number} item_count
 *  @property {CustomerSummary|null} customer
 *  @property {object|null} discounts
 */

/** @typedef {object} RegisterMeta
 *  @property {string} [name]
 *  @property {number} [id]
 */

/** @typedef {object} PosV2Config
 *  @property {Record<string, string>} api
 *  @property {string} csrf
 *  @property {string} locale
 *  @property {boolean} rtl
 *  @property {Record<string, string>} i18n
 *  @property {Record<string, string>} [localeUrls]
 */

/** @type {PosV2Config|null} */
let config = null;

/**
 * @returns {PosV2Config}
 */
export function getConfig() {
    if (config) {
        return config;
    }
    const el = document.getElementById('pos-v2-config');
    if (!el?.textContent) {
        throw new Error('POS V2 config missing');
    }
    config = JSON.parse(el.textContent);
    return /** @type {PosV2Config} */ (config);
}

function csrfToken() {
    const meta = document.querySelector('meta[name="rateb-csrf"]');
    return meta?.getAttribute('content') || getConfig().csrf || '';
}

/**
 * @param {string} pathKey
 * @param {string} [suffix]
 */
function apiUrl(pathKey, suffix = '') {
    const base = getConfig().api[pathKey];
    if (!base) {
        throw new Error(`Unknown API key: ${pathKey}`);
    }
    return base + suffix;
}

/**
 * @param {string} url
 * @param {RequestInit} [init]
 */
async function request(url, init = {}) {
    const headers = new Headers(init.headers || {});
    headers.set('Accept', 'application/json');
    if (init.body && !(init.body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
    }
    headers.set('X-CSRF-Token', csrfToken());
    headers.set('X-Requested-With', 'XMLHttpRequest');

    const response = await fetch(url, {
        ...init,
        headers,
        credentials: 'same-origin',
    });

    let payload = null;
    const text = await response.text();
    if (text) {
        try {
            payload = JSON.parse(text);
        } catch {
            payload = { success: false, message: text };
        }
    }

    const apiMessage = payload?.message
        || payload?.error?.message
        || (typeof payload?.error === 'string' ? payload.error : null);

    if (!response.ok) {
        const message = apiMessage || `HTTP ${response.status}`;
        const err = new Error(message);
        err.status = response.status;
        err.payload = payload;
        throw err;
    }

    if (payload && payload.success === false) {
        const err = new Error(apiMessage || 'Request failed');
        err.status = response.status;
        err.payload = payload;
        throw err;
    }

    return payload;
}

export async function fetchBootstrap() {
    const res = await request(apiUrl('bootstrap'));
    return res?.data ?? res;
}

/**
 * @param {{ q?: string, category_id?: number|null, page?: number, per_page?: number }} params
 */
export async function searchCatalog(params = {}) {
    const url = new URL(apiUrl('catalogSearch'), window.location.origin);
    if (params.q) {
        url.searchParams.set('q', params.q);
    }
    if (params.category_id != null && params.category_id > 0) {
        url.searchParams.set('category_id', String(params.category_id));
    }
    url.searchParams.set('page', String(params.page ?? 1));
    url.searchParams.set('per_page', String(params.per_page ?? 24));
    const res = await request(url.toString());
    return res?.data ?? { products: [], pagination: { page: 1, per_page: 24, total: 0, last_page: 1 } };
}

/**
 * @param {number} productId
 */
export async function getProduct(productId) {
    const res = await request(apiUrl('catalogProduct', `/${productId}`));
    return res?.data ?? res;
}

/**
 * @param {number} productId
 * @param {string} qty
 */
export async function addCartLine(productId, qty = '1') {
    const res = await request(apiUrl('cartLines'), {
        method: 'POST',
        body: JSON.stringify({ product_id: productId, qty }),
    });
    return res?.data ?? res;
}

/**
 * @param {string} lineId
 * @param {string} qty
 */
export async function updateCartLine(lineId, qty) {
    const res = await request(apiUrl('cartLine', `/${lineId}`), {
        method: 'PATCH',
        body: JSON.stringify({ qty }),
    });
    return res?.data ?? res;
}

/**
 * @param {string} lineId
 */
export async function removeCartLine(lineId) {
    const res = await request(apiUrl('cartLine', `/${lineId}`), {
        method: 'DELETE',
    });
    return res?.data ?? res;
}

/**
 * @param {number} customerId
 */
export async function attachCustomer(customerId) {
    const res = await request(apiUrl('cartCustomer'), {
        method: 'POST',
        body: JSON.stringify({ customer_id: customerId }),
    });
    return res?.data ?? res;
}

export async function removeCustomer() {
    const res = await request(apiUrl('cartCustomer'), {
        method: 'DELETE',
    });
    return res?.data ?? res;
}

/**
 * @param {{ type: string, value: string, line_id?: string, reason?: string }} body
 */
export async function applyLineDiscount(body) {
    const res = await request(apiUrl('discountLine'), {
        method: 'POST',
        body: JSON.stringify(body),
    });
    return res?.data ?? res;
}

/**
 * @param {{ type: string, value: string, reason?: string }} body
 */
export async function applyCartDiscount(body) {
    const res = await request(apiUrl('discountCart'), {
        method: 'POST',
        body: JSON.stringify(body),
    });
    return res?.data ?? res;
}

export async function fetchPayments() {
    const res = await request(apiUrl('payments'));
    return res?.data ?? res;
}

export async function addCashPayment(amount) {
    const res = await request(apiUrl('paymentsCash'), {
        method: 'POST',
        body: JSON.stringify({ amount }),
    });
    return res?.data ?? res;
}

export async function removePayment(paymentId) {
    const res = await request(apiUrl('paymentRemove', `/${paymentId}`), {
        method: 'DELETE',
    });
    return res?.data ?? res;
}

export async function initiateCharge(sessionId = 0) {
    const res = await request(apiUrl('chargeInitiate'), {
        method: 'POST',
        body: JSON.stringify({ session_id: sessionId }),
    });
    return res?.data ?? res;
}

/**
 * @param {{ method: string, amount: string|{amount: string, currency?: string}, reference?: string }} body
 */
export async function recordPayment(body) {
    const res = await request(apiUrl('paymentRecord'), {
        method: 'POST',
        body: JSON.stringify(body),
    });
    return res?.data ?? res;
}

/**
 * @param {{ session_id?: number, payments?: unknown[], gift_receipt?: boolean }} body
 * @param {string} idempotencyKey
 */
export async function completeSale(body, idempotencyKey) {
    const url = apiUrl('paymentComplete');
    const headers = new Headers();
    headers.set('Accept', 'application/json');
    headers.set('Content-Type', 'application/json');
    headers.set('X-CSRF-Token', csrfToken());
    headers.set('X-Requested-With', 'XMLHttpRequest');
    headers.set('Idempotency-Key', idempotencyKey);

    const response = await fetch(url, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    const text = await response.text();
    let payload = null;
    if (text) {
        try {
            payload = JSON.parse(text);
        } catch {
            payload = { success: false, message: text };
        }
    }

    const apiMessage = payload?.message
        || payload?.error?.message
        || (typeof payload?.error === 'string' ? payload.error : null);

    if (response.status === 409 && payload?.data) {
        return { data: payload.data, status: 409, idempotent: true };
    }

    if (!response.ok || payload?.success === false) {
        throw new Error(apiMessage || `HTTP ${response.status}`);
    }

    return {
        data: payload?.data ?? payload,
        status: response.status,
        idempotent: Boolean(payload?.data?.idempotent),
    };
}
