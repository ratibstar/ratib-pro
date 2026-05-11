(function () {
  'use strict';

  var root = document.getElementById('infra-client-services');
  if (!root) return;

  function renderFromHealth(data) {
    root.textContent = JSON.stringify({
      module: data.module || 'infrastructure-marketplace',
      enabled: data.enabled,
      queue_driver: data.queue_driver || 'unknown',
      mode: 'health-fallback'
    }, null, 2);
  }

  fetch('/api/infrastructure-marketplace/dashboard.php', { credentials: 'same-origin' })
    .then(function (r) {
      if (!r.ok) {
        throw new Error('dashboard_http_' + r.status);
      }
      return r.json();
    })
    .then(function (data) {
      root.textContent = JSON.stringify(data.jobs || {}, null, 2);
    })
    .catch(function () {
      fetch('/api/infrastructure-marketplace/health.php', { credentials: 'same-origin' })
        .then(function (r) {
          if (!r.ok) throw new Error('health_http_' + r.status);
          return r.json();
        })
        .then(function (health) {
          renderFromHealth(health || {});
        })
        .catch(function (err) {
          root.textContent = 'Unable to load services. (' + String((err && err.message) || 'unknown') + ')';
        });
    });
})();

