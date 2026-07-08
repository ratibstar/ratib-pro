(function (document, api, ui) {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    ui.bindForm(document.getElementById('searchForm'), async function (data) {
      try {
        var res = await api.get('/catalog/search', { q: data.q, limit: 50 });
        document.getElementById('searchResults').innerHTML = ui.jsonBlock(res.data);
      } catch (error) {
        ui.handleError(error);
      }
    });

    ui.bindForm(document.getElementById('barcodeForm'), async function (data) {
      try {
        var res = await api.get('/catalog/search/barcode/' + encodeURIComponent(data.barcode));
        document.getElementById('searchResults').innerHTML = ui.jsonBlock(res.data);
      } catch (error) {
        ui.handleError(error);
      }
    });

    document.getElementById('searchReindexBtn').addEventListener('click', async function () {
      try {
        var res = await api.post('/catalog/admin/search/reindex', {});
        document.getElementById('searchResults').innerHTML = ui.jsonBlock(res.data);
        ui.flash(ui.t('success', 'Success'), 'success');
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
