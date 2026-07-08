(function (document, api, ui) {
  'use strict';

  async function loadList() {
    var list = document.getElementById('entityList');
    ui.setLoading(list, true);
    try {
      var res = await api.get('/catalog/change-requests', {
        status: document.getElementById('crStatus').value,
        limit: 100,
        offset: 0
      });
      var items = Array.isArray(res.data) ? res.data : [];
      ui.renderTable(list, [
        { key: 'uuid', label: 'UUID', render: function (r) { return ui.codeCell(r.uuid); } },
        { key: 'status', label: 'Status', render: function (r) { return ui.statusBadge(r.status); } },
        { key: 'request_type', label: 'Type', render: function (r) { return ui.escapeHtml(r.request_type || '—'); } }
      ], items, { onRowClick: openItem });
    } catch (error) {
      ui.handleError(error);
      list.innerHTML = '<div class="admin-muted p-3">' + ui.escapeHtml(error.message) + '</div>';
    }
  }

  async function openItem(row) {
    document.getElementById('entityDetailPanel').hidden = false;
    try {
      var res = await api.get('/catalog/change-requests/' + encodeURIComponent(row.uuid));
      document.getElementById('entityDetail').innerHTML = ui.jsonBlock(res.data);
      var actions = document.getElementById('crActions');
      actions.innerHTML =
        '<button type="button" class="btn btn-sm btn-success" data-cr="approve">Approve</button>' +
        '<button type="button" class="btn btn-sm btn-outline-danger" data-cr="reject">Reject</button>' +
        '<button type="button" class="btn btn-sm btn-primary" data-cr="apply">Apply</button>';
      actions.querySelectorAll('[data-cr]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
          try {
            await api.post('/catalog/change-requests/' + encodeURIComponent(row.uuid) + '/' + btn.getAttribute('data-cr'), {});
            ui.flash(ui.t('success', 'Success'), 'success');
            openItem(row);
            loadList();
          } catch (error) {
            ui.handleError(error);
          }
        });
      });
    } catch (error) {
      ui.handleError(error);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadList();
    document.getElementById('crStatus').addEventListener('change', loadList);
    document.addEventListener('admin:page-refresh', loadList);
    document.getElementById('crCreateToggle').addEventListener('click', function () {
      document.getElementById('crCreatePanel').hidden = false;
    });
    ui.bindForm(document.getElementById('crCreateForm'), async function (data) {
      try {
        var proposed = JSON.parse(data.proposed_changes_json || '{}');
        await api.post('/catalog/change-requests', {
          product_uuid: data.product_uuid,
          request_type: data.request_type || 'update',
          proposed_changes: proposed
        });
        ui.flash(ui.t('success', 'Success'), 'success');
        document.getElementById('crCreatePanel').hidden = true;
        loadList();
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
