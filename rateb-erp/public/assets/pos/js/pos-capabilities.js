(function () {
    'use strict';

    var configEl = document.getElementById('rateb-pos-register-config');
    if (!configEl) {
        return;
    }

    var config = {};
    try {
        config = JSON.parse(configEl.textContent || '{}');
    } catch (e) {
        config = {};
    }

    var caps = config.capabilities || {};
    var root = document.querySelector('[data-pos-register]');
    if (!root) {
        return;
    }

    function hide(sel) {
        root.querySelectorAll(sel).forEach(function (el) {
            el.hidden = true;
            el.setAttribute('aria-hidden', 'true');
        });
    }

    function disable(sel) {
        root.querySelectorAll(sel).forEach(function (el) {
            el.disabled = true;
            el.classList.add('is-disabled');
        });
    }

    if (!caps.discounts) {
        hide('[data-pos-cap-discount]');
        disable('[data-pos-line-discount-open]');
    }

    if (!caps.returns) {
        hide('[data-pos-cap-returns]');
        disable('[data-pos-return-open], [data-pos-exchange-open]');
    }

    if (!caps.paymentCard) {
        var cardBtn = root.querySelector('[data-pos-pay-quick="card"]');
        if (cardBtn) {
            cardBtn.hidden = true;
        }
    }

    if (!caps.inventoryAdjust) {
        root.querySelectorAll('[data-pos-nav-action="stock"], [data-pos-stock-open]').forEach(function (el) {
            el.hidden = true;
        });
    }

    if (!caps.shiftClose) {
        var shiftClose = root.querySelector('[data-pos-shift-close]');
        if (shiftClose) {
            shiftClose.hidden = true;
        }
    }

    root.querySelectorAll('[data-pos-nav-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = btn.getAttribute('data-pos-nav-action');
            if (action === 'customer') {
                var customerBtn = root.querySelector('[data-pos-focus-customer]');
                if (customerBtn) {
                    customerBtn.click();
                }
            } else if (action === 'catalog') {
                var search = root.querySelector('[data-pos-product-search]');
                if (search) {
                    search.focus();
                }
            } else if (action === 'stock') {
                var stockOpen = root.querySelector('[data-pos-stock-open]');
                if (stockOpen) {
                    stockOpen.click();
                } else if (window.RatebPosRequireApproval) {
                    var stockModal = root.querySelector('[data-pos-stock-modal]');
                    if (stockModal) {
                        stockModal.hidden = false;
                    }
                }
            }
        });
    });

    var barcodeFocus = document.querySelector('[data-pos-barcode-focus]');
    if (barcodeFocus) {
        barcodeFocus.addEventListener('click', function () {
            var inp = root.querySelector('[data-pos-barcode-input]');
            if (inp) {
                inp.focus();
            }
        });
    }

    var fullscreenBtn = document.querySelector('[data-pos-fullscreen]');
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', function () {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(function () {});
            } else {
                document.exitFullscreen().catch(function () {});
            }
        });
    }
})();
