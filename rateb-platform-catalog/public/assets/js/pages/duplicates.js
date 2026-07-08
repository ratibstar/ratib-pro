(function (document, api, ui) {
  'use strict';

  async function loadList() {
    var list = document.getElementById('entityList');
    ui.setLoading(list, true);
    try {
      var res = await api.get('/catalog/duplicates', {
        status: document.getElementById('dupStatus').value,
        limit: 100,
        offset: 0
      });
      ui.renderTable(list, [
        { key: 'uuid', label: 'UUID', render: function (r) { return '<code>' + ui.escapeHtml(r.uuid) + '</code>'; } },
        { key: 'status', label: 'Status', render: function (r) { return ui.statusBadge(r.status); } },
        { key: 'score', label: 'Score', render: function (r) { return ui.escapeHtml(String(r.score != null ? r.score : '—')); } }
      ], Array.isArray(res.data) ? res.data : [], {
        onRowClick: async function (row) {
          document.getElementById('entityDetailPanel').hidden = false;
          try {
            var detail = await api.get('/catalog/duplicates/' + encodeURIComponent(row.uuid));
            document.getElementById('entityDetail').innerHTML = ui.jsonBlock(detail.data);
            document.getElementById('dupResolveForm').hidden = false;
            document.getElementById('dupUuid').value = row.uuid;
          } catch (error) {
            ui.handleError(error);
          }
        }
      });

      var rules = await api.get('/catalog/duplicate-rules');
      document.getElementById('dupRules').innerHTML = ui.jsonBlock(rules.data);
    } catch (error) {
      ui.handleError(error);
      list.innerHTML = '<div class="admin-muted p-3">' + ui.escapeHtml(error.message) + '</div>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadList();
    document.getElementById('dupStatus').addEventListener('change', loadList);
    document.addEventListener('admin:page-refresh', loadList);
    ui.bindForm(document.getElementById('dupResolveForm'), async function (data) {
      try {
        await api.put('/catalog/duplicates/' + encodeURIComponent(data.uuid) + '/resolve', {
          resolution: data.resolution,
          keep_product_uuid: data.keep_product_uuid || null
        });
        ui.flash(ui.t('success', 'Success'), 'success');
        loadList();
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
