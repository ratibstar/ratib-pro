(function () {
    'use strict';

    var shell = document.querySelector('.gm-shell');
    if (!shell) return;

    var apiUrl = shell.getAttribute('data-gm-api') || '';
    var grid = document.getElementById('gm-product-grid');
    var catButtons = shell.querySelectorAll('.gm-cat');
    if (!apiUrl || !grid) return;

    function escapeHtml(text) {
        var d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    function renderProducts(products) {
        if (!products.length) {
            grid.innerHTML = '<p class="gm-empty">—</p>';
            return;
        }
        grid.innerHTML = products.map(function (p) {
            var price = p.price || {};
            var inStock = !!p.in_stock;
            var img = p.image_url
                ? '<div class="gm-card__img" style="background-image:url(\'' + escapeHtml(p.image_url) + '\')"></div>'
                : '<div class="gm-card__img gm-card__img--placeholder"></div>';
            var badge = inStock ? '' : '<span class="gm-badge">' + escapeHtml(document.documentElement.lang === 'ar' ? 'غير متوفر' : 'Unavailable') + '</span>';
            return '<article class="gm-card' + (inStock ? '' : ' is-unavailable') + '">' +
                img +
                '<div class="gm-card__body">' +
                '<h2 class="gm-card__name">' + escapeHtml(p.name || '') + '</h2>' +
                '<p class="gm-card__price">' + escapeHtml(Number(price.amount || 0).toFixed(2)) + ' ' + escapeHtml(price.currency || 'SAR') + '</p>' +
                badge +
                '</div></article>';
        }).join('');
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
            .catch(function () { /* keep current grid */ })
            .finally(function () { grid.classList.remove('is-loading'); });
    }

    catButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            catButtons.forEach(function (b) { b.classList.remove('is-active'); });
            btn.classList.add('is-active');
            loadCategory(btn.getAttribute('data-category') || '');
        });
    });
})();
