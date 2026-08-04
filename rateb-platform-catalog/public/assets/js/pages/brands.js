(function (document, api, ui) {
  'use strict';

  async function loadList() {
    var list = document.getElementById('entityList');
    ui.setLoading(list, true);
    try {
      var res = await api.get('/catalog/brands', { limit: 100, offset: 0 });
      var items = Array.isArray(res.data) ? res.data : [];
      ui.renderTable(list, [
        { key: 'name', label: ui.t('field_name', 'Name'), render: function (r) { return ui.escapeHtml(r.name || '—'); } },
        { key: 'status', label: ui.t('field_status', 'Status'), render: function (r) { return ui.statusBadge(r.status || '—'); } }
      ], items, {
        onRowClick: async function (row) {
          document.getElementById('entityDetailPanel').hidden = false;
          try {
            var detail = await api.get('/catalog/brands/' + encodeURIComponent(row.uuid));
            document.getElementById('entityDetail').innerHTML = ui.entityDetail(detail.data || row);
          } catch (error) {
            ui.handleError(error);
          }
        }
      });
    } catch (error) {
      ui.handleError(error);
      list.innerHTML = '<div class="admin-muted p-3">' + ui.escapeHtml(error.message) + '</div>';
    }
  }

  document.addEventListener('DOMContentLoaded', loadList);
  document.addEventListener('admin:page-refresh', loadList);
})(document, window.RatebAdminApi, window.RatebAdminUi);
