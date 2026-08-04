(function (document, api, ui, config) {
  'use strict';

  var locale = (config && config.locale) || 'ar';
  var categoryMap = {};
  var flatCategories = [];

  function flattenCategories(nodes, depth, out) {
    (nodes || []).forEach(function (node) {
      if (!node) {
        return;
      }
      var item = Object.assign({}, node, { depth: depth });
      delete item.children;
      out.push(item);
      categoryMap[node.uuid] = node.name || node.slug || node.uuid;
      if (Array.isArray(node.children) && node.children.length) {
        flattenCategories(node.children, depth + 1, out);
      }
    });
  }

  function fillCategorySelects() {
    var filter = document.getElementById('productCategory');
    var create = document.getElementById('productCreateCategory');
    [filter, create].forEach(function (select) {
      if (!select) {
        return;
      }
      var current = select.value;
      var first = select.options[0] ? select.options[0].outerHTML : '';
      select.innerHTML = first;
      flatCategories.forEach(function (cat) {
        var opt = document.createElement('option');
        opt.value = cat.uuid;
        opt.textContent = (cat.depth ? Array(cat.depth + 1).join('— ') : '') + (cat.name || cat.slug || cat.uuid);
        select.appendChild(opt);
      });
      if (current) {
        select.value = current;
      }
    });
  }

  async function loadCategories() {
    try {
      var res = await api.get('/catalog/categories', { limit: 500, offset: 0 });
      var data = res.data;
      categoryMap = {};
      flatCategories = [];
      if (Array.isArray(data)) {
        var looksNested = data.some(function (n) { return n && Array.isArray(n.children) && n.children.length; });
        if (looksNested) {
          flattenCategories(data, 0, flatCategories);
        } else {
          data.forEach(function (cat) {
            flatCategories.push(Object.assign({}, cat, { depth: cat.depth || 0 }));
            categoryMap[cat.uuid] = cat.name || cat.slug || cat.uuid;
          });
          flatCategories.sort(function (a, b) {
            return String(a.path || a.name || '').localeCompare(String(b.path || b.name || ''));
          });
        }
      }
      fillCategorySelects();
    } catch (error) {
      ui.handleError(error);
    }
  }

  async function loadProducts() {
    var list = document.getElementById('productList');
    ui.setLoading(list, true);
    try {
      var params = {
        limit: 50,
        offset: 0,
        sku: document.getElementById('productSku').value || undefined,
        status: document.getElementById('productStatus').value || undefined,
        category_uuid: document.getElementById('productCategory').value || undefined
      };
      var res = await api.get('/catalog/products', params);
      var items = Array.isArray(res.data) ? res.data : [];
      ui.renderTable(list, [
        {
          key: 'sku',
          label: ui.t('field_sku', 'SKU'),
          render: function (r) { return ui.escapeHtml(r.sku); }
        },
        {
          key: 'name',
          label: ui.t('field_name', 'Name'),
          render: function (r) { return ui.escapeHtml(r.name); }
        },
        {
          key: 'category',
          label: ui.t('field_category', 'Category'),
          render: function (r) {
            return ui.escapeHtml(categoryMap[r.category_uuid] || r.category_name || r.category_uuid || '—');
          }
        },
        {
          key: 'status',
          label: ui.t('field_status', 'Status'),
          render: function (r) { return ui.statusBadge(r.status); }
        }
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
      document.getElementById('productDetailMeta').innerHTML = ui.entityDetail(item, [
        { key: 'uuid', label: ui.t('field_uuid', 'UUID') },
        { key: 'category_uuid', label: ui.t('field_category', 'Category'), render: function (v) {
          return ui.escapeHtml(categoryMap[v] || v || '—');
        }},
        { key: 'brand_uuid', label: ui.t('field_brand', 'Brand') },
        { key: 'family_uuid', label: ui.t('field_family', 'Family') },
        { key: 'unit_uuid', label: ui.t('field_unit', 'Unit') },
        { key: 'lock_version', label: ui.t('field_lock_version', 'Lock version') }
      ], { auto: false });
    } catch (error) {
      ui.handleError(error);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadCategories().then(loadProducts);
    document.getElementById('productFilterBtn').addEventListener('click', loadProducts);
    document.getElementById('productCategory').addEventListener('change', loadProducts);
    document.getElementById('productStatus').addEventListener('change', loadProducts);
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
