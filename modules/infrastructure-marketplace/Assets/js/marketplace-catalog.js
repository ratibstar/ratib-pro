(function () {
  'use strict';

  var root = document.getElementById('infra-market-catalog');
  var notice = document.getElementById('infra-market-notice');
  if (!root) return;

  fetch('/api/infrastructure-marketplace/catalog.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data || !data.ok || !Array.isArray(data.items)) {
        root.textContent = 'Catalog unavailable.';
        return;
      }
      root.innerHTML = '';
      data.items.forEach(function (item) {
        var card = document.createElement('article');
        card.className = 'infra-market-card';
        card.innerHTML =
          '<h3>' + String(item.title || item.sku) + '</h3>' +
          '<p>' + String(item.description || '') + '</p>' +
          '<p><strong>' + String((item.pricing || {}).currency || '') + ' ' + String((item.pricing || {}).final_price || 0) + '</strong></p>' +
          '<div class="infra-market-actions">' +
          '<button class="infra-btn" data-sku="' + String(item.sku || '') + '">Queue Provisioning</button>' +
          '</div>';
        root.appendChild(card);
      });
    })
    .catch(function () {
      root.textContent = 'Catalog unavailable.';
    });

  root.addEventListener('click', function (ev) {
    var target = ev.target;
    if (!(target instanceof HTMLElement)) return;
    if (!target.matches('.infra-btn[data-sku]')) return;
    var sku = target.getAttribute('data-sku') || '';
    var body = {
      sku: sku,
      idempotency_key: 'mkp-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8),
      currency: 'USD',
      amount: 0,
      actor: 'marketplace_ui'
    };
    fetch('/api/infrastructure-marketplace/order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body)
    }).then(function (r) { return r.json(); })
      .then(function (res) {
        if (notice) {
          notice.textContent = res && res.ok
            ? 'Provisioning request queued successfully.'
            : 'Unable to queue provisioning request.';
        }
      })
      .catch(function () {
        if (notice) notice.textContent = 'Failed to queue provisioning request.';
      });
  });
})();

