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
        { key: 'uuid', label: 'UUID', render: function (r) { return '<code>' + ui.escapeHtml(r.uuid) + '</code>'; } },
        { key: 'actions', label: 'Actions', render: function (r) {
          return '<button type="button" class="btn btn-sm btn-outline-danger" data-del-image="' + ui.escapeHtml(r.uuid) + '">Delete</button>';
        } }
      ], Array.isArray(images.data) ? images.data : []);

      ui.renderTable(document.getElementById('mediaFiles'), [
        { key: 'uuid', label: 'UUID', render: function (r) { return '<code>' + ui.escapeHtml(r.uuid) + '</code>'; } },
        { key: 'actions', label: 'Actions', render: function (r) {
          return '<button type="button" class="btn btn-sm btn-outline-danger" data-del-file="' + ui.escapeHtml(r.uuid) + '">Delete</button>';
        } }
      ], Array.isArray(files.data) ? files.data : []);

      document.getElementById('mediaVideos').innerHTML = ui.jsonBlock(videos.data);
      document.getElementById('assetTypes').innerHTML = ui.jsonBlock(assets.data);

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
