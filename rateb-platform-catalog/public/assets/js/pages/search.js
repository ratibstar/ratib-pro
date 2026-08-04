(function (document, api, ui) {
  'use strict';

  function showResults(data) {
    var el = document.getElementById('searchResults');
    var items = Array.isArray(data) ? data : (data && Array.isArray(data.items) ? data.items : null);
    if (items) {
      ui.renderTable(el, [
        { key: 'sku', label: ui.t('field_sku', 'SKU'), render: function (r) { return ui.escapeHtml(r.sku || '—'); } },
        { key: 'name', label: ui.t('field_name', 'Name'), render: function (r) { return ui.escapeHtml(r.name || '—'); } },
        { key: 'status', label: ui.t('field_status', 'Status'), render: function (r) { return ui.statusBadge(r.status || '—'); } }
      ], items);
      el.innerHTML += ui.rawJsonDetails(data);
      return;
    }
    el.innerHTML = ui.entityDetail(data || {});
  }

  document.addEventListener('DOMContentLoaded', function () {
    ui.bindForm(document.getElementById('searchForm'), async function (data) {
      try {
        var res = await api.get('/catalog/search', { q: data.q, limit: 50 });
        showResults(res.data);
      } catch (error) {
        ui.handleError(error);
      }
    });

    ui.bindForm(document.getElementById('barcodeForm'), async function (data) {
      try {
        var res = await api.get('/catalog/search/barcode/' + encodeURIComponent(data.barcode));
        showResults(res.data);
      } catch (error) {
        ui.handleError(error);
      }
    });

    document.getElementById('searchReindexBtn').addEventListener('click', async function () {
      try {
        var res = await api.post('/catalog/admin/search/reindex', {});
        showResults(res.data);
        ui.flash(ui.t('success', 'Success'), 'success');
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
