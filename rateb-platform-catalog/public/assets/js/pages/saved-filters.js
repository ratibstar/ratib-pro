(function (document, api, ui) {
  'use strict';

  async function loadList() {
    var list = document.getElementById('entityList');
    ui.setLoading(list, true);
    try {
      var res = await api.get('/catalog/saved-filters', {
        entity_type: document.getElementById('sfEntityType').value || undefined
      });
      ui.renderTable(list, [
        { key: 'name', label: ui.t('field_name', 'Name'), render: function (r) { return ui.escapeHtml(r.name); } },
        { key: 'entity_type', label: ui.t('field_entity', 'Entity'), render: function (r) { return ui.escapeHtml(r.entity_type); } },
        { key: 'actions', label: ui.t('actions', 'Actions'), render: function (r) {
          return '<button type="button" class="btn btn-sm btn-outline-danger" data-del="' + ui.escapeHtml(r.uuid) + '">' + ui.escapeHtml(ui.t('delete', 'Delete')) + '</button>';
        } }
      ], Array.isArray(res.data) ? res.data : [], {
        onRowClick: async function (row) {
          document.getElementById('entityDetailPanel').hidden = false;
          try {
            var detail = await api.get('/catalog/saved-filters/' + encodeURIComponent(row.uuid));
            document.getElementById('entityDetail').innerHTML = ui.entityDetail(detail.data || row);
          } catch (error) {
            ui.handleError(error);
          }
        }
      });

      document.querySelectorAll('[data-del]').forEach(function (btn) {
        btn.addEventListener('click', async function (e) {
          e.stopPropagation();
          if (!window.confirm(ui.t('confirm', 'Are you sure?'))) {
            return;
          }
          try {
            await api.del('/catalog/saved-filters/' + encodeURIComponent(btn.getAttribute('data-del')));
            ui.flash(ui.t('success', 'Success'), 'success');
            loadList();
          } catch (error) {
            ui.handleError(error);
          }
        });
      });
    } catch (error) {
      ui.handleError(error);
      list.innerHTML = '<div class="admin-muted p-3">' + ui.escapeHtml(error.message) + '</div>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadList();
    document.getElementById('sfEntityType').addEventListener('change', loadList);
    document.addEventListener('admin:page-refresh', loadList);
    document.getElementById('sfCreateToggle').addEventListener('click', function () {
      document.getElementById('sfCreatePanel').hidden = false;
    });
    ui.bindForm(document.getElementById('sfCreateForm'), async function (data) {
      try {
        var filter = JSON.parse(data.filter_json || '{}');
        await api.post('/catalog/saved-filters', {
          name: data.name,
          entity_type: data.entity_type,
          filter: filter
        });
        ui.flash(ui.t('success', 'Success'), 'success');
        document.getElementById('sfCreatePanel').hidden = true;
        loadList();
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
