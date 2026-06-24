(function () {
    'use strict';

    var instances = typeof WeakMap !== 'undefined' ? new WeakMap() : null;
    var instanceBag = instances || null;

    function bagGet(el) {
        if (instances) {
            return instances.get(el) || null;
        }
        return instanceBag && instanceBag[el.id] ? instanceBag[el.id] : null;
    }

    function bagSet(el, inst) {
        if (instances) {
            instances.set(el, inst);
            return;
        }
        if (!instanceBag) {
            instanceBag = {};
        }
        if (el.id) {
            instanceBag[el.id] = inst;
        }
    }

    function isValidModal(el) {
        return !!(
            el
            && el.nodeType === 1
            && el.classList
            && el.classList.contains('modal')
            && el.querySelector('.modal-dialog')
        );
    }

    function prepareElement(el) {
        if (!isValidModal(el)) {
            return null;
        }
        if (el.parentElement && el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
        if (!el.classList.contains('show')) {
            el.setAttribute('aria-hidden', 'true');
        }
        if (!el.dataset.ratebModalA11y) {
            el.dataset.ratebModalA11y = '1';
            el.addEventListener('show.bs.modal', function () {
                el.removeAttribute('aria-hidden');
                el.setAttribute('aria-modal', 'true');
            });
            el.addEventListener('hidden.bs.modal', function () {
                el.setAttribute('aria-hidden', 'true');
                el.removeAttribute('aria-modal');
            });
        }
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
        var inst = getInstance(el);
        if (inst) {
            inst.show();
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
