(function () {
  'use strict';

  function setText(id, value) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = value;
  }

  function formatObject(obj) {
    if (!obj || typeof obj !== 'object') return String(obj);
    var rows = [];
    Object.keys(obj).forEach(function (k) {
      rows.push(k + ': ' + String(obj[k]));
    });
    return rows.join('\n');
  }

  fetch('/api/infrastructure-marketplace/dashboard.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      setText('infra-health', formatObject(data.health || {}));
      setText('infra-queue', formatObject(data.queue || {}));
      setText('infra-providers', formatObject(data.providers || {}));
      setText('infra-catalog', formatObject(data.catalog || {}));
      setText('infra-jobs', formatObject(data.jobs || {}));
    })
    .catch(function (err) {
      var message = 'Unable to load dashboard data: ' + (err && err.message ? err.message : 'unknown');
      setText('infra-health', message);
      setText('infra-queue', message);
      setText('infra-providers', message);
      setText('infra-catalog', message);
      setText('infra-jobs', message);
    });
})();

