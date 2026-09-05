/**
 * Company permissions edit — select / clear optional modules.
 * Works with soft-nav and without relying on a single inline IIFE.
 */
(function () {
    'use strict';

    function boxes() {
        return Array.prototype.slice.call(
            document.querySelectorAll('#rateb-company-permissions-form .rateb-cp-module:not([disabled])')
        );
    }

    function selectAll() {
        boxes().forEach(function (el) {
            el.checked = true;
        });
    }

    function clearOptional() {
        boxes().forEach(function (el) {
            el.checked = false;
        });
    }

    function bind() {
        var selectBtn = document.getElementById('rateb-cp-select-all');
        var clearBtn = document.getElementById('rateb-cp-clear-all');
        if (selectBtn && !selectBtn.dataset.ratebCpBound) {
            selectBtn.dataset.ratebCpBound = '1';
            selectBtn.addEventListener('click', function (ev) {
                ev.preventDefault();
                selectAll();
            });
        }
        if (clearBtn && !clearBtn.dataset.ratebCpBound) {
            clearBtn.dataset.ratebCpBound = '1';
            clearBtn.addEventListener('click', function (ev) {
                ev.preventDefault();
                clearOptional();
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
    document.addEventListener('rateb:soft-nav-ready', bind);
    document.addEventListener('rateb:page-ready', bind);
    window.RatebCompanyPermissions = { selectAll: selectAll, clearOptional: clearOptional, bind: bind };
})();
