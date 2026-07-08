(function (document, api, ui) {
  'use strict';

  async function loadDashboard() {
    var stats = document.getElementById('dashboardStats');
    var healthEl = document.getElementById('dashboardHealth');
    var queueEl = document.getElementById('dashboardQueue');
    var productsEl = document.getElementById('dashboardProducts');
    ui.setLoading(healthEl, true);
    ui.setLoading(queueEl, true);
    ui.setLoading(productsEl, true);

    try {
      var health = await api.get('/health');
      var ready;
      try {
        ready = await api.get('/ready');
      } catch (e) {
        ready = { data: { status: 'not_ready', error: e.message } };
      }

      healthEl.innerHTML = ui.jsonBlock({ health: health.data, ready: ready.data });

      var queueData = null;
      try {
        var queue = await api.get('/catalog/admin/queue/status');
        queueData = queue.data;
        queueEl.innerHTML = ui.jsonBlock(queue.data);
      } catch (e) {
        queueEl.innerHTML = '<div class="admin-muted">' + ui.escapeHtml(e.message) + '</div>';
      }

      var products = await api.get('/catalog/products', { limit: 8, offset: 0 });
      var items = Array.isArray(products.data) ? products.data : [];
      if (stats) {
        stats.innerHTML =
          '<div class="admin-stat"><div class="admin-stat-label">Products (sample)</div><div class="admin-stat-value">' + ui.escapeHtml(String(items.length)) + '</div></div>' +
          '<div class="admin-stat"><div class="admin-stat-label">Health</div><div class="admin-stat-value">' + ui.escapeHtml((health.data && health.data.status) || 'ok') + '</div></div>' +
          '<div class="admin-stat"><div class="admin-stat-label">Ready</div><div class="admin-stat-value">' + ui.escapeHtml((ready.data && ready.data.status) || '—') + '</div></div>' +
          '<div class="admin-stat"><div class="admin-stat-label">Queue</div><div class="admin-stat-value">' + ui.escapeHtml(queueData && queueData.pending != null ? String(queueData.pending) : '—') + '</div></div>';
      }

      ui.renderTable(productsEl, [
        { key: 'sku', label: 'SKU', render: function (r) { return ui.escapeHtml(r.sku); } },
        { key: 'name', label: 'Name', render: function (r) { return ui.escapeHtml(r.name); } },
        { key: 'status', label: 'Status', render: function (r) { return ui.statusBadge(r.status); } }
      ], items);
    } catch (error) {
      ui.handleError(error);
      if (healthEl) {
        healthEl.innerHTML = '<div class="admin-muted">' + ui.escapeHtml(error.message) + '</div>';
      }
    }
  }

  document.addEventListener('DOMContentLoaded', loadDashboard);
  document.addEventListener('admin:dashboard-refresh', loadDashboard);
})(document, window.RatebAdminApi, window.RatebAdminUi);
