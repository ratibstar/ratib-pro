(function (document, api, ui) {
  'use strict';

  async function loadList() {
    var list = document.getElementById('entityList');
    ui.setLoading(list, true);
    try {
      var res = await api.get('/catalog/webhooks', { limit: 100, offset: 0 });
      ui.renderTable(list, [
        { key: 'url', label: ui.t('field_url', 'URL'), render: function (r) { return ui.escapeHtml(r.url || '—'); } },
        { key: 'is_active', label: ui.t('field_active', 'Active'), render: function (r) {
          var on = r.is_active === true || r.is_active === 1 || r.is_active === '1';
          return ui.escapeHtml(on ? ui.t('yes', 'Yes') : ui.t('no', 'No'));
        } }
      ], Array.isArray(res.data) ? res.data : [], {
        onRowClick: async function (row) {
          document.getElementById('entityDetailPanel').hidden = false;
          try {
            var detail = await api.get('/catalog/webhooks/' + encodeURIComponent(row.uuid));
            var item = detail.data || {};
            document.getElementById('entityDetail').innerHTML = ui.entityDetail(item);
            document.getElementById('webhookUpdateForm').hidden = false;
            document.getElementById('whUuid').value = item.uuid || row.uuid;
            document.getElementById('whUrl').value = item.url || '';
            document.getElementById('whEvents').value = Array.isArray(item.events) ? item.events.join(',') : '';
            document.getElementById('whActive').value = item.is_active ? '1' : '0';
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

  document.addEventListener('DOMContentLoaded', function () {
    loadList();
    document.addEventListener('admin:page-refresh', loadList);
    document.getElementById('webhookCreateToggle').addEventListener('click', function () {
      document.getElementById('webhookCreatePanel').hidden = false;
    });

    ui.bindForm(document.getElementById('webhookCreateForm'), async function (data) {
      try {
        var events = String(data.events || '').split(',').map(function (v) { return v.trim(); }).filter(Boolean);
        var payload = {
          url: data.url,
          events: events,
          secret: data.secret || undefined
        };
        if (data.erp_company_id) {
          payload.erp_company_id = Number(data.erp_company_id);
        }
        await api.post('/catalog/webhooks', payload);
        ui.flash(ui.t('success', 'Success'), 'success');
        document.getElementById('webhookCreatePanel').hidden = true;
        loadList();
      } catch (error) {
        ui.handleError(error);
      }
    });

    ui.bindForm(document.getElementById('webhookUpdateForm'), async function (data) {
      try {
        var events = String(data.events || '').split(',').map(function (v) { return v.trim(); }).filter(Boolean);
        await api.put('/catalog/webhooks/' + encodeURIComponent(data.uuid), {
          url: data.url,
          events: events,
          is_active: data.is_active === '1' || data.is_active === 1 || data.is_active === true
        });
        ui.flash(ui.t('success', 'Success'), 'success');
        loadList();
      } catch (error) {
        ui.handleError(error);
      }
    });

    document.getElementById('webhookDeleteBtn').addEventListener('click', async function () {
      var uuid = document.getElementById('whUuid').value;
      if (!uuid || !window.confirm(ui.t('confirm', 'Are you sure?'))) {
        return;
      }
      try {
        await api.del('/catalog/webhooks/' + encodeURIComponent(uuid));
        ui.flash(ui.t('success', 'Success'), 'success');
        document.getElementById('entityDetailPanel').hidden = true;
        loadList();
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
