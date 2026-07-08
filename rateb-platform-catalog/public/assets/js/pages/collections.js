(function (document, api, ui, config) {
  'use strict';

  var locale = (config && config.locale) || 'ar';

  async function loadList() {
    var list = document.getElementById('entityList');
    ui.setLoading(list, true);
    try {
      var res = await api.get('/catalog/collections', { limit: 100, offset: 0 });
      var items = Array.isArray(res.data) ? res.data : [];
      ui.renderTable(list, [
        { key: 'slug', label: 'Slug', render: function (r) { return ui.escapeHtml(r.slug || '—'); } },
        { key: 'name', label: 'Name', render: function (r) { return ui.escapeHtml(r.name || '—'); } },
        { key: 'uuid', label: 'UUID', render: function (r) { return '<code>' + ui.escapeHtml(r.uuid) + '</code>'; } }
      ], items, {
        onRowClick: async function (row) {
          document.getElementById('entityDetailPanel').hidden = false;
          try {
            var detail = await api.get('/catalog/collections/' + encodeURIComponent(row.uuid));
            document.getElementById('entityDetail').innerHTML = ui.jsonBlock(detail.data);
            var products = await api.get('/catalog/collections/' + encodeURIComponent(row.uuid) + '/products', { limit: 50 });
            ui.renderTable(document.getElementById('collectionProducts'), [
              { key: 'sku', label: 'SKU', render: function (r) { return ui.escapeHtml(r.sku || r.product_uuid || '—'); } },
              { key: 'name', label: 'Name', render: function (r) { return ui.escapeHtml(r.name || '—'); } }
            ], Array.isArray(products.data) ? products.data : []);
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
    document.getElementById('collectionCreateToggle').addEventListener('click', function () {
      document.getElementById('collectionCreatePanel').hidden = false;
    });
    ui.bindForm(document.getElementById('collectionCreateForm'), async function (data) {
      try {
        var payload = {
          slug: data.slug,
          collection_type: data.collection_type || 'manual',
          translations: {}
        };
        payload.translations[locale] = { name: data.name };
        await api.post('/catalog/collections', payload);
        ui.flash(ui.t('success', 'Success'), 'success');
        document.getElementById('collectionCreatePanel').hidden = true;
        loadList();
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi, window.RatebAdminConfig);
