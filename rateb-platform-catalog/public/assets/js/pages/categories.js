(function (document, api, ui) {
  'use strict';

  async function loadList() {
    var list = document.getElementById('entityList');
    ui.setLoading(list, true);
    try {
      var res = await api.get('/catalog/categories', { limit: 100, offset: 0 });
      var items = Array.isArray(res.data) ? res.data : [];
      ui.renderTable(list, [
        { key: 'name', label: 'Name', render: function (r) { return ui.escapeHtml(r.name || r.code || '—'); } },
        { key: 'uuid', label: 'UUID', render: function (r) { return ui.codeCell(r.uuid); } },
        { key: 'status', label: 'Status', render: function (r) { return ui.statusBadge(r.status || '—'); } }
      ], items, { onRowClick: openCategory });
    } catch (error) {
      ui.handleError(error);
      list.innerHTML = '<div class="admin-muted p-3">' + ui.escapeHtml(error.message) + '</div>';
    }
  }

  async function openCategory(row) {
    var panel = document.getElementById('entityDetailPanel');
    var detail = document.getElementById('entityDetail');
    var form = document.getElementById('schemaForm');
    panel.hidden = false;
    detail.innerHTML = ui.jsonBlock(row);
    try {
      var schema = await api.get('/catalog/categories/' + encodeURIComponent(row.uuid) + '/attribute-schema');
      document.getElementById('schemaUuid').value = row.uuid;
      document.getElementById('schemaJson').value = JSON.stringify(schema.data || {}, null, 2);
      form.hidden = false;
      detail.innerHTML += '<h3 class="h6 mt-3">Schema</h3>' + ui.jsonBlock(schema.data);
    } catch (error) {
      form.hidden = true;
      detail.innerHTML += '<div class="admin-muted mt-2">' + ui.escapeHtml(error.message) + '</div>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadList();
    document.addEventListener('admin:page-refresh', loadList);
    ui.bindForm(document.getElementById('schemaForm'), async function (data) {
      try {
        var schema = JSON.parse(data.schema_json || '{}');
        await api.put('/catalog/categories/' + encodeURIComponent(data.uuid) + '/attribute-schema', schema);
        ui.flash(ui.t('success', 'Success'), 'success');
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
