(function (document, api, ui, config) {
  'use strict';

  var locale = (config && config.locale) || 'ar';

  async function loadProducts() {
    var list = document.getElementById('productList');
    ui.setLoading(list, true);
    try {
      var res = await api.get('/catalog/products', {
        limit: 50,
        offset: 0,
        sku: document.getElementById('productSku').value,
        status: document.getElementById('productStatus').value
      });
      var items = Array.isArray(res.data) ? res.data : [];
      ui.renderTable(list, [
        { key: 'sku', label: 'SKU', render: function (r) { return ui.escapeHtml(r.sku); } },
        { key: 'name', label: 'Name', render: function (r) { return ui.escapeHtml(r.name); } },
        { key: 'status', label: 'Status', render: function (r) { return ui.statusBadge(r.status); } },
        { key: 'uuid', label: 'UUID', render: function (r) { return '<code>' + ui.escapeHtml(r.uuid) + '</code>'; } }
      ], items, {
        onRowClick: function (row) { openProduct(row.uuid); }
      });
    } catch (error) {
      ui.handleError(error);
      list.innerHTML = '<div class="admin-muted p-3">' + ui.escapeHtml(error.message) + '</div>';
    }
  }

  async function openProduct(uuid) {
    try {
      var res = await api.get('/catalog/products/' + encodeURIComponent(uuid));
      var item = res.data || {};
      document.getElementById('productDetailPanel').hidden = false;
      document.getElementById('productUuid').value = item.uuid || uuid;
      document.getElementById('productLockVersion').value = item.lock_version || '';
      document.getElementById('productEditSku').value = item.sku || '';
      document.getElementById('productEditStatus').value = item.status || '';
      document.getElementById('productEditName').value = item.name || '';
      document.getElementById('productEditBarcode').value = item.primary_barcode || '';
      document.getElementById('productEditShort').value = item.short_description || '';
      document.getElementById('productEditDescription').value = item.description || '';
      document.getElementById('productDetailJson').innerHTML = ui.jsonBlock(item);
    } catch (error) {
      ui.handleError(error);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadProducts();
    document.getElementById('productFilterBtn').addEventListener('click', loadProducts);
    document.getElementById('productCreateToggle').addEventListener('click', function () {
      document.getElementById('productCreatePanel').hidden = false;
    });
    document.getElementById('productCreateCancel').addEventListener('click', function () {
      document.getElementById('productCreatePanel').hidden = true;
    });

    ui.bindForm(document.getElementById('productCreateForm'), async function (data) {
      try {
        var payload = {
          sku: data.sku,
          category_uuid: data.category_uuid,
          unit_uuid: data.unit_uuid,
          brand_uuid: data.brand_uuid || null,
          family_uuid: data.family_uuid || null,
          translations: [
            { language_code: locale, name: data.name }
          ]
        };
        await api.post('/catalog/products', payload);
        ui.flash(ui.t('success', 'Success'), 'success');
        document.getElementById('productCreatePanel').hidden = true;
        loadProducts();
      } catch (error) {
        ui.handleError(error);
      }
    });

    ui.bindForm(document.getElementById('productEditForm'), async function (data) {
      try {
        var payload = {
          sku: data.sku,
          primary_barcode: data.primary_barcode || null,
          lock_version: Number(data.lock_version),
          translations: [
            {
              language_code: locale,
              name: data.name,
              short_description: data.short_description,
              description: data.description
            }
          ]
        };
        await api.put('/catalog/products/' + encodeURIComponent(data.uuid), payload, {
          ifMatch: '"' + data.lock_version + '"'
        });
        ui.flash(ui.t('success', 'Success'), 'success');
        openProduct(data.uuid);
        loadProducts();
      } catch (error) {
        ui.handleError(error);
      }
    });

    document.getElementById('productDeleteBtn').addEventListener('click', async function () {
      var uuid = document.getElementById('productUuid').value;
      if (!uuid || !window.confirm(ui.t('confirm', 'Are you sure?'))) {
        return;
      }
      try {
        await api.del('/catalog/products/' + encodeURIComponent(uuid));
        ui.flash(ui.t('success', 'Success'), 'success');
        document.getElementById('productDetailPanel').hidden = true;
        loadProducts();
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi, window.RatebAdminConfig);
