(function (document, api, ui) {
  'use strict';

  async function loadHealth() {
    var live = document.getElementById('healthLiveness');
    var ready = document.getElementById('healthReady');
    ui.setLoading(live, true);
    ui.setLoading(ready, true);
    try {
      var health = await api.get('/health');
      live.innerHTML = ui.jsonBlock(health.data);
    } catch (error) {
      live.innerHTML = '<div class="admin-muted">' + ui.escapeHtml(error.message) + '</div>';
    }
    try {
      var readiness = await api.get('/ready');
      ready.innerHTML = ui.jsonBlock(readiness.data);
    } catch (error) {
      ready.innerHTML = ui.jsonBlock({ status: 'not_ready', message: error.message, payload: error.payload || null });
    }
  }

  document.addEventListener('DOMContentLoaded', loadHealth);
  document.addEventListener('admin:page-refresh', loadHealth);
})(document, window.RatebAdminApi, window.RatebAdminUi);
