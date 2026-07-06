import { getConfig } from './api.js';
import { state } from './state.js';

/** @type {Record<string, string>} */
let strings = {};

/**
 * @param {Record<string, string>} [map]
 */
export function initI18n(map) {
    strings = map || getConfig().i18n || {};
}

/**
 * @param {string} key
 * @param {string} [fallback]
 */
export function t(key, fallback = '') {
    return strings[key] || fallback || key;
}

/**
 * @param {import('./api.js').Money|null|undefined} money
 */
export function formatMoney(money) {
    if (!money?.amount) {
        return '—';
    }
    const amount = Number(money.amount);
    const currency = money.currency || 'SAR';
    if (Number.isNaN(amount)) {
        return `${money.amount} ${currency}`;
    }
    try {
        const locale = state.locale === 'ar' ? 'ar-SA' : 'en-SA';
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency,
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(amount);
    } catch {
        return `${amount.toFixed(2)} ${currency}`;
    }
}

/**
 * @param {string} message
 * @param {'danger'|'success'|'warning'|'info'} [variant]
 */
export function showAlert(message, variant = 'danger') {
    const zone = document.querySelector('[data-pos-alerts]');
    if (!zone) {
        return;
    }
    const el = document.createElement('div');
    el.className = `alert alert-${variant} alert-dismissible fade show py-2 small mb-0`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `${escapeHtml(message)}<button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>`;
    zone.appendChild(el);
    window.setTimeout(() => {
        el.classList.remove('show');
        window.setTimeout(() => el.remove(), 300);
    }, 6000);
}

/**
 * @param {string} text
 */
export function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * @param {HTMLElement|null} el
 * @param {boolean} visible
 */
export function toggle(el, visible) {
    if (!el) {
        return;
    }
    el.classList.toggle('d-none', !visible);
    if (el.hasAttribute('hidden')) {
        if (visible) {
            el.removeAttribute('hidden');
        } else {
            el.setAttribute('hidden', '');
        }
    }
}

/**
 * @param {() => void} fn
 * @param {number} ms
 */
export function debounce(fn, ms) {
    let timer = 0;
    return (...args) => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => fn(...args), ms);
    };
}

export function initLocaleSwitcher() {
    const cfg = getConfig();
    document.querySelectorAll('[data-pos-locale]').forEach((btn) => {
        const loc = btn.getAttribute('data-pos-locale');
        const active = loc === cfg.locale;
        btn.classList.toggle('active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        btn.addEventListener('click', () => {
            const urls = cfg.localeUrls || {};
            const target = urls[loc || ''];
            if (target) {
                window.location.href = target;
            }
        });
    });
}

/**
 * @param {string|null|undefined} name
 */
export function renderRegisterName(name) {
    const el = document.querySelector('[data-pos-register-name]');
    if (el && name) {
        el.textContent = name;
    }
}
