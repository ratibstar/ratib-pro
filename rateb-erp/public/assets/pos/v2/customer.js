import { attachCustomer, removeCustomer } from './api.js';
import { patchState, setCart, state, subscribe } from './state.js';
import { escapeHtml, showAlert, t, toggle } from './ui.js';

function els() {
    const r = document.querySelector('[data-pos-register]');
    return {
        card: r?.querySelector('[data-pos-customer-card]'),
        name: r?.querySelector('[data-pos-customer-name]'),
        meta: r?.querySelector('[data-pos-customer-meta]'),
        form: r?.querySelector('[data-pos-customer-form]'),
        idInput: /** @type {HTMLInputElement|null} */ (r?.querySelector('[data-pos-customer-id]')),
        remove: r?.querySelector('[data-pos-customer-remove]'),
        loading: r?.querySelector('[data-pos-customer-loading]'),
    };
}

/**
 * @param {import('./api.js').CustomerSummary|null|undefined} customer
 */
export function renderCustomer(customer) {
    const { card, name, meta } = els();
    const c = customer ?? state.cart?.customer ?? null;

    toggle(card, Boolean(c));
    if (!c) {
        return;
    }
    if (name) {
        name.textContent = c.name || `#${c.id}`;
    }
    if (meta) {
        const parts = [];
        if (c.phone) {
            parts.push(c.phone);
        }
        if (c.email) {
            parts.push(c.email);
        }
        parts.push(`ID ${c.id}`);
        meta.textContent = parts.join(' · ');
    }
}

async function onAttachSubmit(e) {
    e.preventDefault();
    const { idInput, loading } = els();
    const raw = idInput?.value?.trim();
    const customerId = raw ? parseInt(raw, 10) : 0;
    if (!customerId || customerId < 1) {
        showAlert(t('customer_id_invalid', 'Enter a valid customer ID.'));
        return;
    }

    patchState({ customerBusy: true });
    toggle(loading, true);
    try {
        const data = await attachCustomer(customerId);
        setCart(data);
        renderCustomer(state.cart?.customer);
        showAlert(t('customer_attached', 'Customer attached.'), 'success');
        if (idInput) {
            idInput.value = '';
        }
    } catch (err) {
        showAlert(err instanceof Error ? err.message : t('customer_error', 'Could not attach customer.'));
    } finally {
        patchState({ customerBusy: false });
        toggle(loading, false);
    }
}

async function onRemove() {
    const { loading } = els();
    patchState({ customerBusy: true });
    toggle(loading, true);
    try {
        const data = await removeCustomer();
        setCart(data);
        renderCustomer(null);
        showAlert(t('customer_removed', 'Customer removed.'), 'info');
    } catch (err) {
        showAlert(err instanceof Error ? err.message : t('customer_error', 'Could not remove customer.'));
    } finally {
        patchState({ customerBusy: false });
        toggle(loading, false);
    }
}

export function initCustomer() {
    const { form, remove } = els();
    form?.addEventListener('submit', onAttachSubmit);
    remove?.addEventListener('click', onRemove);
    subscribe('cart', () => renderCustomer(state.cart?.customer));
    renderCustomer(state.cart?.customer);
}
