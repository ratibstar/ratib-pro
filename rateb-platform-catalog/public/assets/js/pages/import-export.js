(function (document, api, ui) {
  'use strict';

  function show(target, data) {
    document.getElementById(target).innerHTML = ui.jsonBlock(data);
  }

  document.addEventListener('DOMContentLoaded', function () {
    ui.bindForm(document.getElementById('importCreateForm'), async function (data) {
      try {
        var rows = JSON.parse(data.rows_json || '[]');
        var res = await api.post('/catalog/import/batches', {
          source_code: data.source_code,
          source_file_path: data.source_file_path || null,
          rows: rows
        });
        show('importResult', res.data);
        if (res.data && res.data.uuid) {
          document.getElementById('importBatchUuid').value = res.data.uuid;
        }
        ui.flash(ui.t('success', 'Success'), 'success');
      } catch (error) {
        ui.handleError(error);
      }
    });

    async function withBatch(fn) {
      var uuid = document.getElementById('importBatchUuid').value;
      if (!uuid) {
        return;
      }
      try {
        show('importResult', (await fn(uuid)).data);
        ui.flash(ui.t('success', 'Success'), 'success');
      } catch (error) {
        ui.handleError(error);
      }
    }

    document.getElementById('importValidateBtn').addEventListener('click', function () {
      withBatch(function (uuid) { return api.post('/catalog/import/batches/' + encodeURIComponent(uuid) + '/validate', {}); });
    });
    document.getElementById('importPreviewBtn').addEventListener('click', function () {
      withBatch(function (uuid) { return api.get('/catalog/import/batches/' + encodeURIComponent(uuid) + '/preview', { limit: 50 }); });
    });
    document.getElementById('importCommitBtn').addEventListener('click', function () {
      withBatch(function (uuid) { return api.post('/catalog/import/batches/' + encodeURIComponent(uuid) + '/commit', {}); });
    });
    document.getElementById('importRollbackBtn').addEventListener('click', function () {
      withBatch(function (uuid) { return api.post('/catalog/import/batches/' + encodeURIComponent(uuid) + '/rollback', {}); });
    });

    async function bulk(path, formId) {
      ui.bindForm(document.getElementById(formId), async function (data) {
        try {
          var payload = JSON.parse(data.payload || '{}');
          var res = await api.post(path, payload);
          show('bulkResult', res.data);
          ui.flash(ui.t('success', 'Success'), 'success');
        } catch (error) {
          ui.handleError(error);
        }
      });
    }

    bulk('/catalog/bulk/products/export', 'bulkExportForm');
    bulk('/catalog/bulk/products/publish', 'bulkPublishForm');
    bulk('/catalog/bulk/products/archive', 'bulkArchiveForm');
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
