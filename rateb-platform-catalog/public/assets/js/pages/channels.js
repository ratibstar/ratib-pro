(function (document, api, ui) {
  'use strict';

  async function loadChannels() {
    var list = document.getElementById('entityList');
    ui.setLoading(list, true);
    try {
      var res = await api.get('/catalog/channels', { limit: 100, offset: 0 });
      ui.renderTable(list, [
        { key: 'code', label: 'Code', render: function (r) { return ui.escapeHtml(r.code || r.slug || '—'); } },
        { key: 'name', label: 'Name', render: function (r) { return ui.escapeHtml(r.name || '—'); } },
        { key: 'uuid', label: 'UUID', render: function (r) { return '<code>' + ui.escapeHtml(r.uuid) + '</code>'; } }
      ], Array.isArray(res.data) ? res.data : []);
    } catch (error) {
      ui.handleError(error);
      list.innerHTML = '<div class="admin-muted p-3">' + ui.escapeHtml(error.message) + '</div>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadChannels();
    document.addEventListener('admin:page-refresh', loadChannels);

    document.getElementById('channelLoadBtn').addEventListener('click', async function () {
      var uuid = document.querySelector('#channelAssignForm [name="product_uuid"]').value;
      if (!uuid) {
        return;
      }
      try {
        var res = await api.get('/catalog/products/' + encodeURIComponent(uuid) + '/channels');
        document.getElementById('channelProductDetail').innerHTML = ui.jsonBlock(res.data);
        document.getElementById('channelReplaceForm').hidden = false;
        document.getElementById('channelProductUuid').value = uuid;
        document.getElementById('channelReplaceInput').value = JSON.stringify(res.data || [], null, 2);
      } catch (error) {
        ui.handleError(error);
      }
    });

    ui.bindForm(document.getElementById('channelReplaceForm'), async function (data) {
      try {
        var channels;
        try {
          channels = JSON.parse(data.channels);
        } catch (e) {
          channels = String(data.channels || '').split(',').map(function (v) { return v.trim(); }).filter(Boolean).map(function (uuid) {
            return { channel_uuid: uuid };
          });
        }
        await api.put('/catalog/products/' + encodeURIComponent(data.product_uuid) + '/channels', { channels: channels });
        ui.flash(ui.t('success', 'Success'), 'success');
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
