import {
    completeSale,
    initiateCharge,
    recordPayment,
} from './api.js';
import { patchState, setCart, state, subscribe } from './state.js';
import { escapeHtml, formatMoney, showAlert, t, toggle } from './ui.js';
import { renderCart } from './cart.js';
import { loadCatalog } from './catalog.js';

/** @type {import('bootstrap').Modal|null} */
let checkoutModal = null;
/** @type {import('bootstrap').Modal|null} */
let receiptModal = null;

function els() {
    const r = document.querySelector('[data-pos-register]');
    return {
        checkoutOpen: r?.querySelector('[data-pos-checkout-open]'),
        checkoutModal: document.querySelector('[data-pos-checkout-modal]'),
        receiptModal: document.querySelector('[data-pos-receipt-modal]'),
        payMethod: /** @type {HTMLSelectElement|null} */ (r?.querySelector('[data-pos-pay-method]')),
        payAmount: /** @type {HTMLInputElement|null} */ (r?.querySelector('[data-pos-pay-amount]')),
        payReference: /** @type {HTMLInputElement|null} */ (r?.querySelector('[data-pos-pay-reference]')),
        payForm: r?.querySelector('[data-pos-payment-form]'),
        completeBtn: r?.querySelector('[data-pos-complete-sale]'),
        checkoutLoading: r?.querySelector('[data-pos-checkout-loading]'),
        payPaid: r?.querySelector('[data-pos-pay-paid]'),
        payPaidLabel: r?.querySelector('[data-pos-pay-paid-label]'),
        payBalance: r?.querySelector('[data-pos-pay-balance]'),
        payBalanceLabel: r?.querySelector('[data-pos-pay-balance-label]'),
        payChange: r?.querySelector('[data-pos-pay-change]'),
        payChangeLabel: r?.querySelector('[data-pos-pay-change-label]'),
        paymentLines: r?.querySelector('[data-pos-payment-lines]'),
        paymentLinesWrap: r?.querySelector('[data-pos-payment-lines-wrap]'),
        receiptOrder: document.querySelector('[data-pos-receipt-order]'),
        receiptBody: document.querySelector('[data-pos-receipt-body]'),
    };
}

function uuid() {
    if (crypto?.randomUUID) {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

function renderPaymentSummary(cart) {
    const {
        checkoutOpen,
        payPaid,
        payPaidLabel,
        payBalance,
        payBalanceLabel,
        payChange,
        payChangeLabel,
        paymentLines,
        paymentLinesWrap,
        completeBtn,
        payAmount,
    } = els();

    const payments = cart?.payments;
    const hasLines = (cart?.item_count ?? 0) > 0;

    if (checkoutOpen) {
        checkoutOpen.disabled = !hasLines;
    }

    if (!payments) {
        toggle(payPaidLabel, false);
        toggle(payPaid, false);
        toggle(payBalanceLabel, false);
        toggle(payBalance, false);
        toggle(payChangeLabel, false);
        toggle(payChange, false);
        toggle(paymentLinesWrap, false);
        if (completeBtn) {
            completeBtn.disabled = true;
        }
        return;
    }

    const showPay = parseFloat(payments.paid?.amount || '0') > 0;
    toggle(payPaidLabel, showPay);
    toggle(payPaid, showPay);
    if (payPaid) {
        payPaid.textContent = formatMoney(payments.paid);
    }

    const balance = parseFloat(payments.remaining?.amount || '0');
    toggle(payBalanceLabel, hasLines);
    toggle(payBalance, hasLines);
    if (payBalance) {
        payBalance.textContent = formatMoney(payments.remaining);
    }

    const change = parseFloat(payments.change_due?.amount || '0');
    toggle(payChangeLabel, change > 0);
    toggle(payChange, change > 0);
    if (payChange) {
        payChange.textContent = formatMoney(payments.change_due);
    }

    if (completeBtn) {
        completeBtn.disabled = !(hasLines && balance <= 0.001);
    }

    if (payAmount && balance > 0 && !payAmount.value) {
        payAmount.value = payments.remaining?.amount || '';
    }

    if (paymentLines && paymentLinesWrap) {
        const lines = payments.payments || [];
        toggle(paymentLinesWrap, lines.length > 0);
        paymentLines.innerHTML = lines.map((line) => {
            const label = `${escapeHtml(line.method)} — ${escapeHtml(formatMoney(line.amount))}`;
            return `<li class="list-group-item px-0 py-1 d-flex justify-content-between gap-2 bg-transparent">
                <span>${label}</span>
            </li>`;
        }).join('');
    }
}

async function openCheckout() {
    const { checkoutModal: modalEl } = els();
    if (!modalEl || !window.bootstrap) {
        return;
    }
    try {
        await initiateCharge(state.register?.session_id || data.register?.session_id || 0);
    } catch (err) {
        showAlert(err instanceof Error ? err.message : t('checkout_error', 'Could not open checkout.'));
        return;
    }
    if (!checkoutModal) {
        checkoutModal = new window.bootstrap.Modal(modalEl);
    }
    checkoutModal.show();
}

async function onAddPayment(e) {
    e.preventDefault();
    const { payMethod, payAmount, payReference, checkoutLoading } = els();
    const method = payMethod?.value || 'cash';
    const amount = payAmount?.value?.trim();
    if (!amount) {
        showAlert(t('payment_amount_required', 'Enter a payment amount.'));
        return;
    }

    toggle(checkoutLoading, true);
    try {
        const balance = await recordPayment({
            method,
            amount: { amount, currency: state.cart?.totals?.total?.currency || 'SAR' },
            reference: payReference?.value?.trim() || undefined,
        });
        if (state.cart) {
            const merged = {
                ...state.cart,
                payments: {
                    payments: balance.payments,
                    paid: balance.paid,
                    remaining: balance.balance_due,
                    change_due: balance.change_due,
                    total_due: state.cart.totals?.total,
                },
            };
            setCart(merged);
            renderPaymentSummary(merged);
        }
        if (payAmount) {
            payAmount.value = '';
        }
        if (payReference) {
            payReference.value = '';
        }
    } catch (err) {
        showAlert(err instanceof Error ? err.message : t('payment_error', 'Could not record payment.'));
    } finally {
        toggle(checkoutLoading, false);
    }
}

async function onCompleteSale() {
    const { completeBtn, checkoutLoading } = els();
    if (state.cartBusy) {
        return;
    }

    const payments = (state.cart?.payments?.payments || []).map((line) => ({
        method: line.method,
        amount: line.amount,
        reference: line.reference || undefined,
    }));

    patchState({ cartBusy: true });
    toggle(checkoutLoading, true);
    if (completeBtn) {
        completeBtn.disabled = true;
    }

    try {
        const cfg = getConfig();
        const result = await completeSale(
            {
                session_id: state.register?.session_id || 0,
                payments: payments.length ? payments : undefined,
            },
            uuid(),
        );

        const data = result.data;
        showAlert(
            result.idempotent
                ? t('sale_already_complete', 'Sale was already completed.')
                : `${t('sale_complete', 'Sale completed')}: ${data.order_no || data.order_id}`,
            result.idempotent ? 'info' : 'success',
        );

        if (checkoutModal) {
            checkoutModal.hide();
        }

        showReceipt(data);
        setCart({ lines: [], totals: state.cart?.totals, item_count: 0, payments: null });
        renderCart(state.cart);
        renderPaymentSummary(null);
        await loadCatalog({ reset: true });
    } catch (err) {
        showAlert(err instanceof Error ? err.message : t('checkout_failed', 'Checkout failed.'));
    } finally {
        patchState({ cartBusy: false });
        toggle(checkoutLoading, false);
        if (completeBtn) {
            completeBtn.disabled = false;
        }
    }
}

/**
 * @param {object} data
 */
function showReceipt(data) {
    const { receiptModal: modalEl, receiptOrder, receiptBody } = els();
    if (!modalEl || !window.bootstrap) {
        return;
    }
    if (receiptOrder) {
        receiptOrder.textContent = `${t('order_no', 'Order')}: ${data.order_no || data.order_id}`;
    }
    if (receiptBody) {
        const payload = data.receipt?.payload || data.receipt || {};
        receiptBody.textContent = JSON.stringify(payload, null, 2);
    }
    if (!receiptModal) {
        receiptModal = new window.bootstrap.Modal(modalEl);
    }
    receiptModal.show();
}

export function initPayment() {
    const { checkoutOpen, payForm, completeBtn } = els();
    checkoutOpen?.addEventListener('click', openCheckout);
    payForm?.addEventListener('submit', onAddPayment);
    completeBtn?.addEventListener('click', onCompleteSale);

    subscribe('cart', (cart) => {
        renderPaymentSummary(/** @type {import('./api.js').Cart} */ (cart));
    });
}
