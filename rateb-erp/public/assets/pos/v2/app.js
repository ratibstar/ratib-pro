import { fetchBootstrap } from './api.js';
import { getConfig } from './api.js';
import { initCatalog, loadCatalog, renderCategories } from './catalog.js';
import { initCart, renderCart } from './cart.js';
import { initCustomer } from './customer.js';
import { initPayment } from './payment.js';
import { patchCatalog, patchState, setCart } from './state.js';
import { initI18n, initLocaleSwitcher, renderRegisterName, showAlert, t } from './ui.js';

async function bootstrap() {
    const cfg = getConfig();
    initI18n(cfg.i18n);
    patchState({
        locale: cfg.locale || 'en',
        rtl: Boolean(cfg.rtl),
        bootstrapLoading: true,
        bootstrapError: null,
    });

    initLocaleSwitcher();
    initCatalog();
    initCart();
    initCustomer();
    initDiscount();
    initPayment();

    try {
        const data = await fetchBootstrap();
        const catalog = data.catalog || {};
        const categories = catalog.categories || [];
        const cart = data.cart || null;
        const register = data.register || null;
        const branchName = data.branch?.name || data.terminal?.name || '';

        patchCatalog({ categories });
        renderCategories(categories);

        if (cart) {
            setCart(cart);
            renderCart(cart);
        }

        if (branchName) {
            renderRegisterName(branchName);
        }

        if (data.locale?.code) {
            patchState({ locale: data.locale.code, rtl: Boolean(data.locale.rtl ?? data.register?.rtl) });
        }

        patchState({
            bootstrapped: true,
            bootstrapLoading: false,
            register,
        });

        await loadCatalog({ reset: true });
    } catch (err) {
        const message = err instanceof Error ? err.message : t('bootstrap_error', 'Failed to load register.');
        patchState({ bootstrapLoading: false, bootstrapError: message, bootstrapped: false });
        showAlert(message);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    bootstrap().catch((err) => {
        console.error('[pos-v2]', err);
        showAlert(t('fatal_error', 'POS failed to start.'));
    });
});
