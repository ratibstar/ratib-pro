(function (document, api, ui) {
  'use strict';

  async function loadHealth() {
    var live = document.getElementById('healthLiveness');
    var ready = document.getElementById('healthReady');
    ui.setLoading(live, true);
    ui.setLoading(ready, true);
    try {
      var health = await api.get('/health');
      live.innerHTML = ui.renderHealthPanel(health.data);
    } catch (error) {
      live.innerHTML = '<div class="admin-muted">' + ui.escapeHtml(error.message) + '</div>';
    }
    try {
      var readiness = await api.get('/ready');
      ready.innerHTML = ui.renderReadyPanel(readiness.data);
    } catch (error) {
      ready.innerHTML = ui.renderReadyPanel({ status: 'not_ready', message: error.message });
    }
  }

  document.addEventListener('DOMContentLoaded', loadHealth);
  document.addEventListener('admin:page-refresh', loadHealth);
})(document, window.RatebAdminApi, window.RatebAdminUi);
