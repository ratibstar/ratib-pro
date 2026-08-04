(function (document, api, ui) {
  'use strict';

  var currentUuid = '';

  document.addEventListener('DOMContentLoaded', function () {
    ui.bindForm(document.getElementById('pricingLoadForm'), async function (data) {
      currentUuid = data.product_uuid;
      try {
        var res = await api.get('/catalog/products/' + encodeURIComponent(currentUuid) + '/prices');
        var items = Array.isArray(res.data) ? res.data : [];
        ui.renderTable(document.getElementById('pricingList'), [
          { key: 'channel_uuid', label: ui.t('field_channel', 'Channel'), render: function (r) { return ui.escapeHtml(r.channel_uuid || r.currency || '—'); } },
          { key: 'amount', label: ui.t('field_amount', 'Amount'), render: function (r) { return ui.escapeHtml(String(r.amount != null ? r.amount : (r.price || '—'))); } },
          { key: 'currency', label: ui.t('field_currency', 'Currency'), render: function (r) { return ui.escapeHtml(r.currency || '—'); } }
        ], items);
        document.getElementById('pricingSaveForm').hidden = false;
        document.getElementById('pricingJson').value = JSON.stringify(items, null, 2);
      } catch (error) {
        ui.handleError(error);
      }
    });

    ui.bindForm(document.getElementById('pricingSaveForm'), async function (data) {
      try {
        var prices = JSON.parse(data.prices_json || '[]');
        await api.put('/catalog/products/' + encodeURIComponent(currentUuid) + '/prices', { prices: prices });
        ui.flash(ui.t('success', 'Success'), 'success');
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
