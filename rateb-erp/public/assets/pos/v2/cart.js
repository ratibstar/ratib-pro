import { addCartLine, removeCartLine, updateCartLine } from './api.js';
import { patchState, setCart, state, subscribe } from './state.js';
import { escapeHtml, formatMoney, showAlert, t, toggle } from './ui.js';
import { refreshDiscountLineSelect } from './discount.js';

function els() {
    const r = document.querySelector('[data-pos-register]');
    return {
        lines: r?.querySelector('[data-pos-cart-lines]'),
        empty: r?.querySelector('[data-pos-cart-empty]'),
        loading: r?.querySelector('[data-pos-cart-loading]'),
        count: r?.querySelector('[data-pos-cart-count]'),
        subtotal: r?.querySelector('[data-pos-total-subtotal]'),
        discount: r?.querySelector('[data-pos-total-discount]'),
        grand: r?.querySelector('[data-pos-total-grand]'),
    };
}

/**
 * @param {import('./api.js').Cart|null} cart
 */
export function renderCart(cart) {
    const { lines, empty, count, subtotal, discount, grand } = els();
    if (!lines) {
        return;
    }

    const c = cart || state.cart;
    const items = c?.lines || [];
    const itemCount = c?.item_count ?? items.length;

    if (count) {
        count.textContent = String(itemCount);
    }
    if (subtotal) {
        subtotal.textContent = formatMoney(c?.totals?.subtotal);
    }
    if (discount) {
        discount.textContent = formatMoney(c?.totals?.discount);
    }
    if (grand) {
        grand.textContent = formatMoney(c?.totals?.total);
    }

    toggle(empty, items.length === 0);
    lines.querySelectorAll('.pos-v2-cart-line').forEach((el) => el.remove());

    items.forEach((line) => {
        const row = document.createElement('div');
        row.className = 'pos-v2-cart-line';
        row.dataset.lineId = line.line_id;
        const disc = line.discount
            ? `<div class="small text-success">${escapeHtml(t('line_discounted', 'Discount applied'))}</div>`
            : '';
        row.innerHTML = `
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div class="min-w-0">
                    <div class="pos-v2-cart-line-name text-truncate">${escapeHtml(line.name)}</div>
                    <div class="small text-muted">${escapeHtml(formatMoney(line.unit_price))} × ${escapeHtml(line.qty)}</div>
                    ${disc}
                </div>
                <div class="fw-semibold flex-shrink-0">${escapeHtml(formatMoney(line.line_total))}</div>
            </div>
            <div class="d-flex align-items-center justify-content-between gap-2">
                <div class="input-group input-group-sm pos-v2-qty-group">
                    <button type="button" class="btn btn-outline-secondary" data-pos-qty-dec aria-label="${escapeHtml(t('decrease', 'Decrease'))}">−</button>
                    <input type="text" class="form-control text-center" value="${escapeHtml(line.qty)}" data-pos-qty-input aria-label="${escapeHtml(t('quantity', 'Quantity'))}">
                    <button type="button" class="btn btn-outline-secondary" data-pos-qty-inc aria-label="${escapeHtml(t('increase', 'Increase'))}">+</button>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" data-pos-line-remove>${escapeHtml(t('remove', 'Remove'))}</button>
            </div>`;
        lines.appendChild(row);

        const dec = row.querySelector('[data-pos-qty-dec]');
        const inc = row.querySelector('[data-pos-qty-inc]');
        const input = /** @type {HTMLInputElement|null} */ (row.querySelector('[data-pos-qty-input]'));
        const removeBtn = row.querySelector('[data-pos-line-remove]');

        dec?.addEventListener('click', () => changeQty(line.line_id, bumpQty(line.qty, -1)));
        inc?.addEventListener('click', () => changeQty(line.line_id, bumpQty(line.qty, 1)));
        input?.addEventListener('change', () => {
            const v = input.value.trim();
            if (v) {
                changeQty(line.line_id, v);
            }
        });
        removeBtn?.addEventListener('click', () => removeLine(line.line_id));
    });

    refreshDiscountLineSelect(items);
}

/**
 * @param {string} qty
 * @param {number} delta
 */
function bumpQty(qty, delta) {
    const n = parseFloat(qty) || 0;
    const next = Math.max(0, n + delta);
    if (next <= 0) {
        return '0';
    }
    return Number.isInteger(next) ? String(next) : next.toFixed(3).replace(/\.?0+$/, '');
}

/**
 * @param {string} lineId
 * @param {string} qty
 */
async function changeQty(lineId, qty) {
    if (parseFloat(qty) <= 0) {
        await removeLine(lineId);
        return;
    }
    patchState({ cartBusy: true });
    toggle(els().loading, true);
    try {
        const data = await updateCartLine(lineId, qty);
        setCart(data);
        renderCart(state.cart);
    } catch (err) {
        showAlert(err instanceof Error ? err.message : t('cart_error', 'Cart update failed.'));
    } finally {
        patchState({ cartBusy: false });
        toggle(els().loading, false);
    }
}

/**
 * @param {string} lineId
 */
async function removeLine(lineId) {
    patchState({ cartBusy: true });
    toggle(els().loading, true);
    try {
        const data = await removeCartLine(lineId);
        setCart(data);
        renderCart(state.cart);
    } catch (err) {
        showAlert(err instanceof Error ? err.message : t('cart_error', 'Cart update failed.'));
    } finally {
        patchState({ cartBusy: false });
        toggle(els().loading, false);
    }
}

/**
 * @param {number} productId
 * @param {HTMLElement|null} card
 */
export async function addProductToCart(productId, card) {
    if (state.cartBusy) {
        return;
    }
    card?.classList.add('is-adding');
    patchState({ cartBusy: true });
    toggle(els().loading, true);
    try {
        const data = await addCartLine(productId, '1');
        setCart(data);
        renderCart(state.cart);
        showAlert(t('added_to_cart', 'Added to cart.'), 'success');
    } catch (err) {
        showAlert(err instanceof Error ? err.message : t('cart_error', 'Could not add to cart.'));
    } finally {
        card?.classList.remove('is-adding');
        patchState({ cartBusy: false });
        toggle(els().loading, false);
    }
}

export function initCart() {
    subscribe('cart', (cart) => renderCart(/** @type {import('./api.js').Cart} */ (cart)));

    document.addEventListener('pos-v2:add-product', (e) => {
        const detail = /** @type {CustomEvent} */ (e).detail;
        addProductToCart(detail.productId, detail.card);
    });
}
