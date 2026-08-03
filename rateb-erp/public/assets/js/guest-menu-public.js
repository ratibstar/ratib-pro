(function () {
    'use strict';

    var shell = document.querySelector('.gm-shell');
    if (!shell) return;

    var apiUrl = shell.getAttribute('data-gm-api') || '';
    var orderApiUrl = shell.getAttribute('data-gm-order-api') || '';
    var orderMode = shell.getAttribute('data-gm-order-mode') === '1';
    var grid = document.getElementById('gm-product-grid');
    var catButtons = shell.querySelectorAll('.gm-cat');
    if (!apiUrl || !grid) return;

    var cart = [];
    var cartFab = document.getElementById('gm-cart-fab');
    var cartPanel = document.getElementById('gm-cart');
    var cartList = document.getElementById('gm-cart-list');
    var cartTotal = document.getElementById('gm-cart-total');
    var cartCount = document.getElementById('gm-cart-count');
    var cartSubmit = document.getElementById('gm-cart-submit');
    var cartMsg = document.getElementById('gm-cart-msg');
    var tableInput = document.getElementById('gm-table-label');
    var guestInput = document.getElementById('gm-guest-name');

    function escapeHtml(text) {
        var d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function renderProducts(products) {
        var emptyMsg = document.getElementById('gm-empty-msg');
        if (!products.length) {
            grid.innerHTML = '';
            if (emptyMsg) emptyMsg.hidden = false;
            return;
        }
        if (emptyMsg) emptyMsg.hidden = true;
        grid.innerHTML = products.map(function (p) {
            var price = p.price || {};
            var inStock = !!p.in_stock;
            var img = p.image_url
                ? '<div class="gm-card__img" style="background-image:url(\'' + escapeHtml(p.image_url) + '\')"></div>'
                : '<div class="gm-card__img gm-card__img--placeholder"></div>';
            var badge = inStock ? '' : '<span class="gm-badge">' + escapeHtml(document.documentElement.lang === 'ar' ? 'غير متوفر' : 'Unavailable') + '</span>';
            var addBtn = (orderMode && inStock)
                ? '<button type="button" class="gm-add-btn" data-gm-add>' + escapeHtml(document.documentElement.lang === 'ar' ? 'أضف' : 'Add') + '</button>'
                : '';
            return '<article class="gm-card' + (inStock ? '' : ' is-unavailable') + '"'
                + ' data-product-id="' + escapeHtml(String(p.id || '')) + '"'
                + ' data-product-name="' + escapeHtml(p.name || '') + '"'
                + ' data-product-price="' + escapeHtml(String(price.amount || 0)) + '"'
                + ' data-product-currency="' + escapeHtml(price.currency || 'SAR') + '">'
                + img
                + '<div class="gm-card__body">'
                + '<h2 class="gm-card__name">' + escapeHtml(p.name || '') + '</h2>'
                + '<p class="gm-card__price">' + escapeHtml(Number(price.amount || 0).toFixed(2)) + ' ' + escapeHtml(price.currency || 'SAR') + '</p>'
                + badge + addBtn
                + '</div></article>';
        }).join('');
        bindAddButtons();
    }

    function bindAddButtons() {
        grid.querySelectorAll('[data-gm-add]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var card = btn.closest('.gm-card');
                if (!card) return;
                addToCart({
                    product_id: parseInt(card.getAttribute('data-product-id') || '0', 10),
                    name: card.getAttribute('data-product-name') || '',
                    unit_price: parseFloat(card.getAttribute('data-product-price') || '0'),
                    currency: card.getAttribute('data-product-currency') || 'SAR',
                });
            });
        });
    }

    function addToCart(item) {
        if (!item.product_id) return;
        var found = cart.find(function (r) { return r.product_id === item.product_id; });
        if (found) {
            found.qty += 1;
        } else {
            cart.push({
                product_id: item.product_id,
                name: item.name,
                unit_price: item.unit_price,
                currency: item.currency,
                qty: 1,
            });
        }
        renderCart();
    }

    function renderCart() {
        if (!orderMode || !cartList) return;
        var total = 0;
        var count = 0;
        cartList.innerHTML = cart.map(function (row, idx) {
            var line = row.unit_price * row.qty;
            total += line;
            count += row.qty;
            return '<li class="gm-cart__item">'
                + '<span>' + escapeHtml(row.name) + ' × ' + row.qty + '</span>'
                + '<button type="button" data-idx="' + idx + '" class="gm-cart__remove">&minus;</button>'
                + '</li>';
        }).join('');
        if (cartTotal) cartTotal.textContent = total.toFixed(2);
        if (cartCount) cartCount.textContent = String(count);
        if (cartFab) cartFab.hidden = count < 1;
        cartList.querySelectorAll('.gm-cart__remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var i = parseInt(btn.getAttribute('data-idx') || '-1', 10);
                if (i < 0 || !cart[i]) return;
                cart[i].qty -= 1;
                if (cart[i].qty < 1) cart.splice(i, 1);
                renderCart();
            });
        });
    }

    function loadCategory(categoryId) {
        var url = apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + 'page=1';
        if (categoryId) url += '&category_id=' + encodeURIComponent(categoryId);
        grid.classList.add('is-loading');
        fetch(url, { credentials: 'omit', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (payload) {
                if (payload && payload.ok && payload.data) {
                    renderProducts(payload.data.products || []);
                }
            })
            .catch(function () { /* keep grid */ })
            .finally(function () { grid.classList.remove('is-loading'); });
    }

    catButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            catButtons.forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            loadCategory(btn.getAttribute('data-category') || '');
        });
    });

    if (orderMode) {
        bindAddButtons();
        if (cartFab && cartPanel) {
            cartFab.addEventListener('click', function () {
                cartPanel.hidden = false;
            });
            var closeBtn = document.getElementById('gm-cart-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    cartPanel.hidden = true;
                });
            }
        }
        if (cartSubmit) {
            cartSubmit.addEventListener('click', function () {
                if (!orderApiUrl || cart.length === 0) return;
                cartSubmit.disabled = true;
                fetch(orderApiUrl, {
                    method: 'POST',
                    credentials: 'omit',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({
                        table_label: tableInput ? tableInput.value : '',
                        guest_name: guestInput ? guestInput.value : '',
                        items: cart,
                    }),
                })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.ok) {
                            cart = [];
                            renderCart();
                            if (cartMsg) {
                                cartMsg.hidden = false;
                                cartMsg.textContent = (document.documentElement.lang === 'ar'
                                    ? 'تم إرسال الطلب: '
                                    : 'Order sent: ') + (res.order_no || '');
                            }
                            if (cartPanel) cartPanel.hidden = true;
                        } else if (cartMsg) {
                            cartMsg.hidden = false;
                            cartMsg.textContent = document.documentElement.lang === 'ar'
                                ? 'تعذّر إرسال الطلب'
                                : 'Could not submit order';
                        }
                    })
                    .catch(function () {
                        if (cartMsg) {
                            cartMsg.hidden = false;
                            cartMsg.textContent = document.documentElement.lang === 'ar'
                                ? 'تعذّر إرسال الطلب'
                                : 'Could not submit order';
                        }
                    })
                    .finally(function () {
                        cartSubmit.disabled = false;
                    });
            });
        }
    }
})();
