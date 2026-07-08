(function (document, api, ui) {
  'use strict';

  async function loadList() {
    var list = document.getElementById('entityList');
    ui.setLoading(list, true);
    try {
      var res = await api.get('/catalog/suppliers', { limit: 100, offset: 0 });
      var items = Array.isArray(res.data) ? res.data : [];
      ui.renderTable(list, [
        { key: 'name', label: 'Name', render: function (r) { return ui.escapeHtml(r.name || '—'); } },
        { key: 'uuid', label: 'UUID', render: function (r) { return '<code>' + ui.escapeHtml(r.uuid) + '</code>'; } }
      ], items, {
        onRowClick: async function (row) {
          document.getElementById('entityDetailPanel').hidden = false;
          try {
            var detail = await api.get('/catalog/suppliers/' + encodeURIComponent(row.uuid));
            document.getElementById('entityDetail').innerHTML = ui.jsonBlock(detail.data);
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
