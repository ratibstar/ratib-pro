(function () {
  'use strict';

  var healthEl = document.getElementById('infra-provider-health');
  var capEl = document.getElementById('infra-provider-capability');
  if (!healthEl || !capEl) return;

  fetch('/api/infrastructure-marketplace/providers.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      healthEl.textContent = JSON.stringify(data.health || [], null, 2);
      capEl.textContent = JSON.stringify(data.capabilities || {}, null, 2);
    })
    .catch(function () {
      healthEl.textContent = 'Unable to load provider health.';
      capEl.textContent = 'Unable to load provider capabilities.';
    });
})();

