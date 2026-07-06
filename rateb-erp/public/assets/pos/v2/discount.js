import { applyCartDiscount, applyLineDiscount } from './api.js';
import { patchState, setCart, state } from './state.js';
import { escapeHtml, showAlert, t, toggle } from './ui.js';

/**
 * @param {import('./api.js').CartLine[]} lines
 */
export function refreshDiscountLineSelect(lines) {
    const select = document.querySelector('[data-pos-discount-line-select]');
    if (!select) {
        return;
    }
    const current = select.value;
    const placeholder = `<option value="">${escapeHtml(t('select_line', 'Select a line…'))}</option>`;
    const options = (lines || []).map((line) => {
        const label = `${line.name} (${line.qty})`;
        return `<option value="${escapeHtml(line.line_id)}">${escapeHtml(label)}</option>`;
    }).join('');
    select.innerHTML = placeholder + options;
    if (current && lines.some((l) => l.line_id === current)) {
        select.value = current;
    }
}

function els() {
    const r = document.querySelector('[data-pos-register]');
    return {
        cartForm: r?.querySelector('[data-pos-discount-cart-form]'),
        lineForm: r?.querySelector('[data-pos-discount-line-form]'),
        loading: r?.querySelector('[data-pos-discount-loading]'),
    };
}

/**
 * @param {HTMLFormElement} form
 */
function readDiscountPayload(form) {
    const type = /** @type {HTMLSelectElement|null} */ (form.querySelector('[name="type"], [data-pos-discount-type]'))?.value;
    const value = /** @type {HTMLInputElement|null} */ (form.querySelector('[name="value"], [data-pos-discount-value]'))?.value?.trim();
    const reason = /** @type {HTMLInputElement|null} */ (form.querySelector('[name="reason"]'))?.value?.trim();
    return { type: type || 'percent', value: value || '', reason: reason || undefined };
}

async function onCartDiscount(e) {
    e.preventDefault();
    const { cartForm, loading } = els();
    if (!cartForm) {
        return;
    }
    const body = readDiscountPayload(cartForm);
    if (!body.value) {
        showAlert(t('discount_value_required', 'Enter a discount value.'));
        return;
    }

    patchState({ discountBusy: true });
    toggle(loading, true);
    try {
        const data = await applyCartDiscount(body);
        setCart(data);
        showAlert(t('discount_applied', 'Discount applied.'), 'success');
    } catch (err) {
        showAlert(err instanceof Error ? err.message : t('discount_error', 'Could not apply discount.'));
    } finally {
        patchState({ discountBusy: false });
        toggle(loading, false);
    }
}

async function onLineDiscount(e) {
    e.preventDefault();
    const { lineForm, loading } = els();
    if (!lineForm) {
        return;
    }
    const lineId = /** @type {HTMLSelectElement|null} */ (lineForm.querySelector('[data-pos-discount-line-select]'))?.value;
    if (!lineId) {
        showAlert(t('select_line_required', 'Select a cart line.'));
        return;
    }
    const body = readDiscountPayload(lineForm);
    if (!body.value) {
        showAlert(t('discount_value_required', 'Enter a discount value.'));
        return;
    }

    patchState({ discountBusy: true });
    toggle(loading, true);
    try {
        const data = await applyLineDiscount({ ...body, line_id: lineId });
        setCart(data);
        showAlert(t('discount_applied', 'Discount applied.'), 'success');
    } catch (err) {
        showAlert(err instanceof Error ? err.message : t('discount_error', 'Could not apply discount.'));
    } finally {
        patchState({ discountBusy: false });
        toggle(loading, false);
    }
}

export function initDiscount() {
    const { cartForm, lineForm } = els();
    cartForm?.addEventListener('submit', onCartDiscount);
    lineForm?.addEventListener('submit', onLineDiscount);
    refreshDiscountLineSelect(state.cart?.lines || []);
}
