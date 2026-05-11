(function () {
  'use strict';

  var root = document.getElementById('infra-client-services');
  if (!root) return;

  fetch('/api/infrastructure-marketplace/dashboard.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      root.textContent = JSON.stringify(data.jobs || {}, null, 2);
    })
    .catch(function () {
      root.textContent = 'Unable to load services.';
    });
})();

