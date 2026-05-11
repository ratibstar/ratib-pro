(function () {
  'use strict';

  var root = document.getElementById('infra-client-services');
  if (!root) return;

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function row(label, value) {
    return '<p><strong>' + esc(label) + ':</strong> ' + esc(value) + '</p>';
  }

  function readFirstWorker(workers) {
    if (!workers || typeof workers !== 'object') {
      return 'n/a';
    }
    var keys = Object.keys(workers);
    if (!keys.length) {
      return 'n/a';
    }
    return keys[0] + ' (' + String(workers[keys[0]]) + ')';
  }

  function renderCards(title, rows) {
    var html = '<div class="infra-client-services-block">';
    html += '<h4>' + esc(title) + '</h4>';
    rows.forEach(function (r) {
      html += row(r[0], r[1]);
    });
    html += '</div>';
    root.innerHTML = html;
  }

  function renderFromDashboard(data) {
    data = data || {};
    var queue = data.queue || {};
    var jobs = data.jobs || {};
    var failed = data.failed || {};
    var providers = data.providers || {};
    renderCards('Active Services', [
      ['Queue driver', queue.driver || queue.queue_driver || 'unknown'],
      ['Queue depth', queue.depth == null ? 'n/a' : queue.depth],
      ['Queued jobs', jobs.QUEUED == null ? 0 : jobs.QUEUED],
      ['Running jobs', jobs.RUNNING == null ? 0 : jobs.RUNNING],
      ['Failed jobs', failed.failed == null ? 0 : failed.failed],
      ['Dead-letter jobs', failed.dead_letter == null ? 0 : failed.dead_letter],
      ['Worker', readFirstWorker(data.workers)],
      ['Providers status', providers.status || 'ready']
    ]);
  }

  function renderFromHealth(data) {
    data = data || {};
    renderCards('Active Services', [
      ['Module', data.module || 'infrastructure-marketplace'],
      ['Enabled', data.enabled === true ? 'yes' : 'no'],
      ['Queue driver', data.queue_driver || 'unknown'],
      ['Mode', 'health-fallback']
    ]);
  }

  fetch('/api/infrastructure-marketplace/dashboard.php', { credentials: 'same-origin' })
    .then(function (r) {
      if (!r.ok) {
        throw new Error('dashboard_http_' + r.status);
      }
      return r.json();
    })
    .then(function (data) {
      renderFromDashboard(data);
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
          renderCards('Active Services', [
            ['Status', 'unavailable'],
            ['Reason', (err && err.message) || 'unknown']
          ]);
        });
    });
})();

