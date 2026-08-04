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
        { key: 'status', label: ui.t('field_status', 'Status'), render: function (r) { return ui.statusBadge(r.status); } },
        { key: 'score', label: ui.t('field_score', 'Score'), render: function (r) { return ui.escapeHtml(String(r.score != null ? r.score : '—')); } },
        { key: 'uuid', label: ui.t('field_uuid', 'UUID'), render: function (r) { return ui.codeCell(r.uuid); } }
      ], Array.isArray(res.data) ? res.data : [], {
        onRowClick: async function (row) {
          document.getElementById('entityDetailPanel').hidden = false;
          try {
            var detail = await api.get('/catalog/duplicates/' + encodeURIComponent(row.uuid));
            document.getElementById('entityDetail').innerHTML = ui.entityDetail(detail.data || row);
            document.getElementById('dupResolveForm').hidden = false;
            document.getElementById('dupUuid').value = row.uuid;
          } catch (error) {
            ui.handleError(error);
          }
        }
      });

      var rules = await api.get('/catalog/duplicate-rules');
      var ruleItems = Array.isArray(rules.data) ? rules.data : [];
      ui.renderTable(document.getElementById('dupRules'), [
        { key: 'code', label: ui.t('field_code', 'Code'), render: function (r) { return ui.codeCell(r.code); } },
        { key: 'match_field', label: ui.t('field_match_field', 'Field'), render: function (r) { return ui.escapeHtml(r.match_field); } },
        { key: 'match_type', label: ui.t('field_match_type', 'Type'), render: function (r) { return ui.escapeHtml(r.match_type); } },
        { key: 'priority', label: ui.t('field_priority', 'Priority'), render: function (r) { return ui.escapeHtml(String(r.priority != null ? r.priority : '—')); } },
        { key: 'is_active', label: ui.t('field_active', 'Active'), render: function (r) {
          var on = r.is_active === true || r.is_active === 1 || r.is_active === '1';
          return ui.escapeHtml(on ? ui.t('yes', 'Yes') : ui.t('no', 'No'));
        } }
      ], ruleItems);
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
