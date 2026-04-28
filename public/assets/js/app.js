/**
 * EN: Implements public web entry/assets behavior in `public/assets/js/app.js`.
 * AR: ينفذ سلوك المدخل العام للويب وملفات الواجهة في `public/assets/js/app.js`.
 */
/**
 * Accounting SaaS - Main Application Scripts
 * Modular pattern | public/assets/js/app.js
 */

(function () {
  'use strict';

  const RatibApp = {};

  /* ========== Currency Formatter ========== */
  RatibApp.formatCurrency = function (value, currencyCode, decimals) {
    const num = parseFloat(value);
    if (Number.isNaN(num)) return '';
    const code = currencyCode || 'SAR';
    const dec = typeof decimals === 'number' ? decimals : 2;
    return new Intl.NumberFormat(undefined, {
      minimumFractionDigits: dec,
      maximumFractionDigits: dec,
    }).format(num) + ' ' + code;
  };

  /* ========== AJAX Helper ========== */
  RatibApp.ajax = function (url, options) {
    const config = Object.assign(
      {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      },
      options
    );

    const init = {
      method: config.method,
      headers: new Headers(config.headers),
    };

    if (config.body && config.method !== 'GET') {
      init.body = typeof config.body === 'string' ? config.body : JSON.stringify(config.body);
    }

    return fetch(url, init)
      .then(function (response) {
        const contentType = response.headers.get('content-type');
        const isJson = contentType && contentType.indexOf('application/json') !== -1;
        const data = isJson ? response.json() : response.text();

        if (!response.ok) {
          return data.then(function (payload) {
            const err = new Error(response.statusText);
            err.status = response.status;
            err.payload = payload;
            throw err;
          });
        }
        return data;
      });
  };

  /* ========== Modal System ========== */
  const Modal = (function () {
    let overlay = null;

    function createOverlay() {
      if (overlay) return overlay;
      overlay = document.createElement('div');
      overlay.className = 'modal__backdrop';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');
      overlay.setAttribute('aria-hidden', 'true');
      overlay.innerHTML = '<div class="modal__content"><div class="modal__body"></div><button type="button" class="modal__close" aria-label="Close">&times;</button></div>';
      document.body.appendChild(overlay);
      return overlay;
    }

    function bindEvents(el) {
      el.querySelector('.modal__close').addEventListener('click', Modal.close);
      el.addEventListener('click', function (e) {
        if (e.target === el) Modal.close();
      });
    }

    function escapeHandler(e) {
      if (e.key === 'Escape' && overlay && overlay.classList.contains('modal--open')) {
        Modal.close();
      }
    }

    Modal.open = function (content) {
      const el = createOverlay();
      el.querySelector('.modal__body').innerHTML = typeof content === 'string' ? content : '';
      el.classList.add('modal--open');
      el.setAttribute('aria-hidden', 'false');
      bindEvents(el);
      document.addEventListener('keydown', escapeHandler);
    };

    Modal.close = function () {
      if (!overlay) return;
      overlay.classList.remove('modal--open');
      overlay.setAttribute('aria-hidden', 'true');
      document.removeEventListener('keydown', escapeHandler);
    };

    return Modal;
  })();

  /* ========== Form Validation ========== */
  const Validator = (function () {
    const rules = {
      required: function (value) {
        return (value || '').toString().trim().length > 0;
      },
      email: function (value) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test((value || '').toString().trim());
      },
      min: function (value, param) {
        const len = (value || '').toString().length;
        return len >= parseInt(param, 10);
      },
      max: function (value, param) {
        const len = (value || '').toString().length;
        return len <= parseInt(param, 10);
      },
      numeric: function (value) {
        return !Number.isNaN(parseFloat(value)) && isFinite(value);
      },
      decimal: function (value, param) {
        const parts = (param || '15,2').split(',');
        const maxInt = parseInt(parts[0], 10) || 15;
        const maxDec = parseInt(parts[1], 10) || 2;
        const num = parseFloat(value);
        if (Number.isNaN(num)) return false;
        const str = value.toString();
        const [intPart, decPart] = str.split('.');
        const intLen = (intPart || '').replace(/^-/, '').length;
        const decLen = (decPart || '').length;
        return intLen <= maxInt && decLen <= maxDec;
      },
    };

    function getErrorEl(input) {
      const group = input.closest('.form__group');
      if (!group) return null;
      let err = group.querySelector('.form__error');
      if (!err) {
        err = document.createElement('span');
        err.className = 'form__error';
        input.parentNode.appendChild(err);
      }
      return err;
    }

    function validateField(input) {
      const val = input.value;
      const ruleStr = input.getAttribute('data-validate');
      if (!ruleStr) return true;

      const parts = ruleStr.split('|');
      let valid = true;

      for (let i = 0; i < parts.length; i++) {
        const [name, param] = parts[i].split(':');
        const rule = rules[name];
        if (!rule) continue;
        if (!rule(val, param)) {
          valid = false;
          break;
        }
      }

      const errEl = getErrorEl(input);
      if (errEl) {
        errEl.textContent = valid ? '' : (input.getAttribute('data-validate-message') || 'Invalid value');
      }
      input.classList.toggle('form__input--error', !valid);
      return valid;
    }

    function validateForm(form) {
      const inputs = form.querySelectorAll('[data-validate]');
      let valid = true;
      inputs.forEach(function (input) {
        if (!validateField(input)) valid = false;
      });
      return valid;
    }

    function attach(form) {
      form.addEventListener('submit', function (e) {
        if (!validateForm(form)) {
          e.preventDefault();
        }
      });
      form.querySelectorAll('[data-validate]').forEach(function (input) {
        input.addEventListener('blur', function () {
          validateField(input);
        });
      });
    }

    return { validateForm: validateForm, validateField: validateField, attach: attach };
  })();

  /* ========== DOMContentLoaded ========== */
  function init() {
    document.querySelectorAll('.form[data-validate-form]').forEach(Validator.attach);

    document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const targetId = btn.getAttribute('data-modal-open');
        const content = targetId
          ? (document.getElementById(targetId) && document.getElementById(targetId).innerHTML) || ''
          : btn.getAttribute('data-modal-content') || '';
        Modal.open(content);
      });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
      btn.addEventListener('click', Modal.close);
    });

    document.querySelectorAll('[data-format-currency]').forEach(function (el) {
      const val = el.getAttribute('data-format-currency');
      const currency = el.getAttribute('data-currency') || 'SAR';
      const decimals = parseInt(el.getAttribute('data-decimals'), 10);
      if (val) el.textContent = RatibApp.formatCurrency(val, currency, isNaN(decimals) ? undefined : decimals);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  RatibApp.modal = Modal;

  window.RatibApp = RatibApp;
})();
