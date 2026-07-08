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

      healthEl.innerHTML =
        '<div class="mb-3">' + ui.renderHealthPanel(health.data, { includeRaw: false }) + '</div>' +
        ui.renderReadyPanel(ready.data, { includeRaw: false });

      var queueData = null;
      try {
        var queue = await api.get('/catalog/admin/queue/status');
        queueData = queue.data;
        queueEl.innerHTML = ui.renderQueuePanel(queue.data, { includeRaw: false });
      } catch (e) {
        queueEl.innerHTML = '<div class="admin-muted">' + ui.escapeHtml(e.message) + '</div>';
      }

      var products = null;
      try {
        products = await api.get('/catalog/products', { limit: 8, offset: 0 });
      } catch (e) {
        productsEl.innerHTML = '<div class="admin-muted">' + ui.escapeHtml(e.message) + '</div>';
        products = { data: [] };
      }

      var items = Array.isArray(products.data) ? products.data : [];
      if (stats) {
        stats.innerHTML =
          '<div class="admin-stat"><div class="admin-stat-label">' + ui.escapeHtml(ui.t('stat_products', 'Products')) + '</div><div class="admin-stat-value">' + ui.escapeHtml(String(items.length)) + '</div></div>' +
          '<div class="admin-stat"><div class="admin-stat-label">' + ui.escapeHtml(ui.t('stat_health', 'Health')) + '</div><div class="admin-stat-value">' + ui.escapeHtml((health.data && health.data.status) || 'ok') + '</div></div>' +
          '<div class="admin-stat"><div class="admin-stat-label">' + ui.escapeHtml(ui.t('stat_ready', 'Ready')) + '</div><div class="admin-stat-value">' + ui.escapeHtml((ready.data && ready.data.status) || '—') + '</div></div>' +
          '<div class="admin-stat"><div class="admin-stat-label">' + ui.escapeHtml(ui.t('stat_queue', 'Queue')) + '</div><div class="admin-stat-value">' + ui.escapeHtml(queueData && queueData.pending != null ? String(queueData.pending) : '0') + '</div></div>';
      }

      ui.renderTable(productsEl, [
        { key: 'sku', label: 'SKU', render: function (r) { return ui.codeCell(r.sku); } },
        { key: 'name', label: ui.t('name', 'Name'), render: function (r) { return ui.escapeHtml(r.name); } },
        { key: 'status', label: ui.t('status', 'Status'), render: function (r) { return ui.statusBadge(r.status); } }
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
