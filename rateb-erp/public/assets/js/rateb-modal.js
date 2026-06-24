(function () {
    'use strict';

    var instances = typeof WeakMap !== 'undefined' ? new WeakMap() : null;
    var fallbackBag = null;

    function bagGet(el) {
        if (instances) {
            return instances.get(el) || null;
        }
        return fallbackBag && el.id ? fallbackBag[el.id] || null : null;
    }

    function bagSet(el, inst) {
        if (instances) {
            instances.set(el, inst);
            return;
        }
        if (!fallbackBag) {
            fallbackBag = {};
        }
        if (el.id) {
            fallbackBag[el.id] = inst;
        }
    }

    function isValidModal(el) {
        return !!(
            el
            && el.nodeType === 1
            && el.classList.contains('modal')
            && el.querySelector('.modal-dialog')
        );
    }

    function clearAriaHidden(el) {
        if (!el) {
            return;
        }
        el.removeAttribute('aria-hidden');
        el.setAttribute('aria-modal', 'true');
    }

    function bindA11y(el) {
        if (!el || el.dataset.ratebModalA11y) {
            return;
        }
        el.dataset.ratebModalA11y = '1';
        el.addEventListener('show.bs.modal', function () {
            clearAriaHidden(el);
        }, true);
        el.addEventListener('shown.bs.modal', function () {
            clearAriaHidden(el);
        });
        el.addEventListener('hide.bs.modal', function () {
            clearAriaHidden(el);
        }, true);
    }

    function prepareElement(el) {
        if (!isValidModal(el)) {
            return null;
        }
        if (el.parentElement && el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
        bindA11y(el);
        return el;
    }

    function getInstance(el) {
        if (typeof bootstrap === 'undefined') {
            return null;
        }
        el = prepareElement(el);
        if (!el) {
            return null;
        }
        var existing = bagGet(el) || bootstrap.Modal.getInstance(el);
        if (existing) {
            bagSet(el, existing);
            return existing;
        }
        var created = new bootstrap.Modal(el, {
            backdrop: true,
            keyboard: true,
            focus: false
        });
        bagSet(el, created);
        return created;
    }

    window.ratebModalPrepare = function (el) {
        return getInstance(el);
    };

    window.ratebModalShow = function (el) {
        if (!el) {
            return null;
        }
        clearAriaHidden(el);
        var inst = getInstance(el);
        if (inst) {
            inst.show();
            window.requestAnimationFrame(function () {
                clearAriaHidden(el);
            });
        }
        return inst;
    };

    window.ratebModalHide = function (el) {
        var inst = bagGet(el) || (typeof bootstrap !== 'undefined' ? bootstrap.Modal.getInstance(el) : null);
        if (inst) {
            inst.hide();
        }
    };

    document.addEventListener('click', function (e) {
        var closeBtn = e.target.closest('[data-rateb-modal-close]');
        if (!closeBtn) {
            return;
        }
        var modal = closeBtn.closest('.modal');
        if (!modal) {
            return;
        }
        e.preventDefault();
        window.ratebModalHide(modal);
    });
})();
