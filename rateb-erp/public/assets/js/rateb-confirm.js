(function () {
    'use strict';

    var modalEl = null;
    var messageEl = null;
    var okBtn = null;
    var cancelBtn = null;
    var titleEl = null;
    var iconEl = null;
    var bsModal = null;
    var resolvePending = null;
    var alertMode = false;
    var confirmAccepted = false;

    function labels() {
        if (!modalEl) {
            return { yes: 'Yes', cancel: 'Cancel', ok: 'OK', title: 'Confirm' };
        }
        return {
            yes: modalEl.getAttribute('data-label-yes') || 'Yes',
            cancel: modalEl.getAttribute('data-label-cancel') || 'Cancel',
            ok: modalEl.getAttribute('data-label-ok') || 'OK',
            title: modalEl.getAttribute('data-label-title') || 'Confirm'
        };
    }

    function settle(result) {
        if (!resolvePending) {
            return;
        }
        var resolve = resolvePending;
        resolvePending = null;
        resolve(result);
    }

    function initModal() {
        modalEl = document.getElementById('ratebConfirmModal');
        if (!modalEl || typeof bootstrap === 'undefined') {
            return false;
        }
        messageEl = modalEl.querySelector('[data-rateb-confirm-message]');
        okBtn = modalEl.querySelector('[data-rateb-confirm-ok]');
        cancelBtn = modalEl.querySelector('[data-rateb-confirm-cancel]');
        titleEl = modalEl.querySelector('[data-rateb-confirm-title]');
        iconEl = modalEl.querySelector('[data-rateb-confirm-icon]');
        if (!modalEl.classList.contains('show')) {
            modalEl.removeAttribute('aria-hidden');
        }

        modalEl.addEventListener('hidden.bs.modal', function () {
            if (!resolvePending) {
                confirmAccepted = false;
                return;
            }
            if (confirmAccepted) {
                confirmAccepted = false;
                var resolveOk = resolvePending;
                resolvePending = null;
                resolveOk(alertMode ? undefined : true);
                return;
            }
            settle(alertMode ? undefined : false);
        });

        okBtn.addEventListener('click', function () {
            if (!resolvePending) {
                return;
            }
            confirmAccepted = true;
            var inst = getBsModal();
            if (inst) {
                inst.hide();
                return;
            }
            confirmAccepted = false;
            var resolve = resolvePending;
            resolvePending = null;
            resolve(alertMode ? undefined : true);
        });

        cancelBtn.addEventListener('click', function () {
            if (!resolvePending) {
                return;
            }
            confirmAccepted = false;
            var resolve = resolvePending;
            resolvePending = null;
            var inst = getBsModal();
            if (inst) {
                inst.hide();
            }
            resolve(false);
        });

        return true;
    }

    function applyOptions(options) {
        options = options || {};
        alertMode = !!options.alert;
        var L = labels();

        if (messageEl) {
            messageEl.textContent = options.message || '';
        }
        if (titleEl) {
            titleEl.textContent = options.title || L.title;
        }
        if (iconEl) {
            if (alertMode) {
                iconEl.className = 'fas fa-info-circle text-info me-2';
            } else if (options.variant === 'danger') {
                iconEl.className = 'fas fa-exclamation-triangle text-warning me-2';
            } else {
                iconEl.className = 'fas fa-question-circle text-primary me-2';
            }
        }
        if (alertMode) {
            cancelBtn.classList.add('d-none');
            okBtn.textContent = options.okText || L.ok;
            okBtn.className = 'btn btn-primary';
        } else {
            cancelBtn.classList.remove('d-none');
            okBtn.textContent = options.confirmText || L.yes;
            cancelBtn.textContent = options.cancelText || L.cancel;
            okBtn.className = 'btn btn-' + (options.variant === 'primary' ? 'primary' : 'danger');
        }
    }

    function getBsModal() {
        if (!modalEl && !initModal()) {
            return null;
        }
        if (!bsModal) {
            bsModal = window.ratebModalPrepare ? window.ratebModalPrepare(modalEl) : null;
        }
        return bsModal;
    }

    function show(message, options) {
        options = Object.assign({}, options || {}, { message: message || '' });
        if (!modalEl && !initModal()) {
            if (options.alert) {
                window.alert(message);
                return Promise.resolve();
            }
            return Promise.resolve(window.confirm(message));
        }
        applyOptions(options);
        return new Promise(function (resolve) {
            confirmAccepted = false;
            resolvePending = resolve;
            var inst = getBsModal();
            if (!inst) {
                if (options.alert) {
                    window.alert(message);
                    resolve(undefined);
                } else {
                    resolve(window.confirm(message));
                }
                resolvePending = null;
                return;
            }
            if (window.ratebModalShow) {
                window.ratebModalShow(modalEl);
            } else {
                inst.show();
            }
        });
    }

    window.ratebConfirm = function (message, options) {
        return show(message, options || {});
    };

    window.ratebAlert = function (message, options) {
        var opts = options || {};
        opts.alert = true;
        return show(message, opts);
    };

    function confirmMessageFromForm(form) {
        return form.getAttribute('data-confirm-delete')
            || form.getAttribute('data-rateb-confirm')
            || form.getAttribute('data-confirm')
            || extractInlineConfirm(form.getAttribute('onsubmit') || '');
    }

    function extractInlineConfirm(attr) {
        if (!attr) {
            return '';
        }
        var match = attr.match(/confirm\s*\(\s*(['"])([\s\S]*?)\1/);
        return match ? match[2] : '';
    }

    function shouldSkipForm(form) {
        return form.hasAttribute('data-rateb-bulk-form')
            || form.hasAttribute('data-entity-docs-delete')
            || form.hasAttribute('data-entity-docs-upload');
    }

    function variantForForm(form) {
        if (form.hasAttribute('data-confirm-delete')) {
            return 'danger';
        }
        return 'primary';
    }

    function resubmitForm(form, submitter) {
        form.removeAttribute('onsubmit');
        form.dataset.ratebConfirmed = '1';
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter || null);
        } else {
            form.submit();
        }
    }

    function initFormInterceptor() {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            if (shouldSkipForm(form)) {
                return;
            }
            if (form.dataset.ratebConfirmed === '1') {
                delete form.dataset.ratebConfirmed;
                return;
            }
            var msg = confirmMessageFromForm(form);
            if (!msg) {
                return;
            }

            e.preventDefault();
            e.stopImmediatePropagation();

            ratebConfirm(msg, { variant: variantForForm(form) }).then(function (ok) {
                if (ok) {
                    resubmitForm(form, e.submitter || null);
                }
            });
        }, true);
    }

    function initClickInterceptor() {
        document.addEventListener('click', function (e) {
            var el = e.target.closest('[onclick]');
            if (!el) {
                return;
            }
            var onclick = el.getAttribute('onclick') || '';
            if (!/confirm\s*\(/.test(onclick)) {
                return;
            }
            var msg = extractInlineConfirm(onclick);
            if (!msg) {
                return;
            }

            e.preventDefault();
            e.stopImmediatePropagation();

            var form = el.closest('form');
            var isSubmit = el.type === 'submit'
                || (el.tagName === 'BUTTON' && (!el.type || el.type === 'submit'))
                || (el.tagName === 'INPUT' && el.type === 'submit');

            ratebConfirm(msg, { variant: 'primary' }).then(function (ok) {
                if (!ok) {
                    return;
                }
                el.removeAttribute('onclick');
                if (form && isSubmit) {
                    resubmitForm(form, el);
                    return;
                }
                el.click();
            });
        }, true);
    }

    function initConfirmClickButtons() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-rateb-confirm-click]');
            if (!btn) {
                return;
            }
            var form = btn.closest('form');
            if (!form) {
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            var msg = btn.getAttribute('data-rateb-confirm-click') || '';
            ratebConfirm(msg, { variant: 'primary' }).then(function (ok) {
                if (!ok) {
                    return;
                }
                resubmitForm(form, btn);
            });
        }, true);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initModal();
        initFormInterceptor();
        initClickInterceptor();
        initConfirmClickButtons();
    });
})();
