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
      setText('infra-workers', formatObject(data.workers || {}));
      setText('infra-failed', formatObject(data.failed || {}));
      setText('infra-reconcile', formatObject(data.reconciliation || {}));
      setText('infra-diagnostics', formatObject(data.diagnostics || {}));
      setText('infra-traces', formatObject(data.traces || {}));
      setText('infra-audit', formatObject(data.audit || {}));
    })
    .catch(function (err) {
      var message = 'Unable to load dashboard data: ' + (err && err.message ? err.message : 'unknown');
      setText('infra-health', message);
      setText('infra-queue', message);
      setText('infra-providers', message);
      setText('infra-catalog', message);
      setText('infra-jobs', message);
      setText('infra-workers', message);
      setText('infra-failed', message);
      setText('infra-reconcile', message);
      setText('infra-diagnostics', message);
      setText('infra-traces', message);
      setText('infra-audit', message);
      setText('infra-launch-readiness', message);
      setText('infra-deployment', message);
      setText('infra-warnings', message);
      setText('infra-drills', message);
    });

  fetch('/api/infrastructure-marketplace/ops-queue.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data || !data.ok) return;
      setText('infra-queue', formatObject({
        depth: data.depth,
        queued: (data.status_counts || {}).QUEUED || 0,
        running: (data.status_counts || {}).RUNNING || 0,
        retrying: (data.status_counts || {}).RETRYING || 0
      }));
      if (Array.isArray(data.recent)) {
        setText('infra-traces', 'Recent jobs: ' + data.recent.length + '\n' + formatObject(data.recent[0] || {}));
      }
    })
    .catch(function () {});

  fetch('/api/infrastructure-marketplace/prelaunch-health.php', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data || !data.report) return;
      var report = data.report;
      setText('infra-launch-readiness', formatObject({
        status: report.status,
        score: report.score,
        pass: (report.matrix || {}).PASS || 0,
        warn: (report.matrix || {}).WARN || 0,
        fail: (report.matrix || {}).FAIL || 0
      }));
      setText('infra-deployment', formatObject(((report.sections || {}).deployment || {}).checks || {}));
      setText('infra-warnings', (report.recommendations || []).join('\n') || 'No warnings.');
      setText('infra-drills', 'Use /api/infrastructure-marketplace/recovery-drill.php in DRY-RUN mode.');
    })
    .catch(function () {});
})();

