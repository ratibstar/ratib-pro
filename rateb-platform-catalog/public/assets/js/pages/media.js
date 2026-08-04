(function (document, api, ui) {
  'use strict';

  var productUuid = '';

  async function loadMedia() {
    if (!productUuid) {
      return;
    }
    try {
      var images = await api.get('/catalog/products/' + encodeURIComponent(productUuid) + '/images');
      var files = await api.get('/catalog/products/' + encodeURIComponent(productUuid) + '/files');
      var videos = await api.get('/catalog/products/' + encodeURIComponent(productUuid) + '/videos');
      var assets = await api.get('/catalog/asset-types');

      ui.renderTable(document.getElementById('mediaImages'), [
        { key: 'uuid', label: ui.t('field_uuid', 'UUID'), render: function (r) { return ui.codeCell(r.uuid); } },
        { key: 'actions', label: ui.t('actions', 'Actions'), render: function (r) {
          return '<button type="button" class="btn btn-sm btn-outline-danger" data-del-image="' + ui.escapeHtml(r.uuid) + '">' + ui.escapeHtml(ui.t('delete', 'Delete')) + '</button>';
        } }
      ], Array.isArray(images.data) ? images.data : []);

      ui.renderTable(document.getElementById('mediaFiles'), [
        { key: 'uuid', label: ui.t('field_uuid', 'UUID'), render: function (r) { return ui.codeCell(r.uuid); } },
        { key: 'actions', label: ui.t('actions', 'Actions'), render: function (r) {
          return '<button type="button" class="btn btn-sm btn-outline-danger" data-del-file="' + ui.escapeHtml(r.uuid) + '">' + ui.escapeHtml(ui.t('delete', 'Delete')) + '</button>';
        } }
      ], Array.isArray(files.data) ? files.data : []);

      var videoItems = Array.isArray(videos.data) ? videos.data : [];
      if (videoItems.length) {
        ui.renderTable(document.getElementById('mediaVideos'), [
          { key: 'url', label: ui.t('field_url', 'URL'), render: function (r) { return ui.escapeHtml(r.url || r.video_url || '—'); } },
          { key: 'uuid', label: ui.t('field_uuid', 'UUID'), render: function (r) { return ui.codeCell(r.uuid); } }
        ], videoItems);
        document.getElementById('mediaVideos').innerHTML += ui.rawJsonDetails(videos.data);
      } else {
        document.getElementById('mediaVideos').innerHTML = ui.entityDetail(videos.data || {});
      }

      var assetItems = Array.isArray(assets.data) ? assets.data : [];
      if (assetItems.length) {
        ui.renderTable(document.getElementById('assetTypes'), [
          { key: 'code', label: ui.t('field_code', 'Code'), render: function (r) { return ui.escapeHtml(r.code || '—'); } },
          { key: 'name', label: ui.t('field_name', 'Name'), render: function (r) { return ui.escapeHtml(r.name || '—'); } }
        ], assetItems);
        document.getElementById('assetTypes').innerHTML += ui.rawJsonDetails(assets.data);
      } else {
        document.getElementById('assetTypes').innerHTML = ui.entityDetail(assets.data || {});
      }

      document.querySelectorAll('[data-del-image]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
          try {
            await api.del('/catalog/products/' + encodeURIComponent(productUuid) + '/images/' + encodeURIComponent(btn.getAttribute('data-del-image')));
            ui.flash(ui.t('success', 'Success'), 'success');
            loadMedia();
          } catch (error) {
            ui.handleError(error);
          }
        });
      });
      document.querySelectorAll('[data-del-file]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
          try {
            await api.del('/catalog/products/' + encodeURIComponent(productUuid) + '/files/' + encodeURIComponent(btn.getAttribute('data-del-file')));
            ui.flash(ui.t('success', 'Success'), 'success');
            loadMedia();
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
    ui.bindForm(document.getElementById('mediaLoadForm'), async function (data) {
      productUuid = data.product_uuid;
      await loadMedia();
    });

    document.getElementById('mediaImageForm').addEventListener('submit', async function (event) {
      event.preventDefault();
      if (!productUuid) {
        return;
      }
      try {
        var fd = new FormData(event.target);
        await api.post('/catalog/products/' + encodeURIComponent(productUuid) + '/images', fd);
        ui.flash(ui.t('success', 'Success'), 'success');
        loadMedia();
      } catch (error) {
        ui.handleError(error);
      }
    });

    document.getElementById('mediaFileForm').addEventListener('submit', async function (event) {
      event.preventDefault();
      if (!productUuid) {
        return;
      }
      try {
        var fd = new FormData(event.target);
        await api.post('/catalog/products/' + encodeURIComponent(productUuid) + '/files', fd);
        ui.flash(ui.t('success', 'Success'), 'success');
        loadMedia();
      } catch (error) {
        ui.handleError(error);
      }
    });

    ui.bindForm(document.getElementById('mediaVideoForm'), async function (data) {
      if (!productUuid) {
        return;
      }
      try {
        await api.post('/catalog/products/' + encodeURIComponent(productUuid) + '/videos', data);
        ui.flash(ui.t('success', 'Success'), 'success');
        loadMedia();
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
