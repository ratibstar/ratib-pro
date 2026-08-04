(function (document, api, ui) {
  'use strict';

  function flattenCategories(nodes, depth, out) {
    (nodes || []).forEach(function (node) {
      if (!node) {
        return;
      }
      var item = Object.assign({}, node, { depth: depth != null ? depth : (node.depth || 0) });
      delete item.children;
      out.push(item);
      if (Array.isArray(node.children) && node.children.length) {
        flattenCategories(node.children, item.depth + 1, out);
      }
    });
  }

  function depthPrefix(depth) {
    var n = Number(depth) || 0;
    if (n <= 0) {
      return '';
    }
    return Array(n + 1).join('— ') ;
  }

  async function loadList() {
    var list = document.getElementById('entityList');
    ui.setLoading(list, true);
    try {
      var res = await api.get('/catalog/categories', { limit: 500, offset: 0 });
      var data = Array.isArray(res.data) ? res.data : [];
      var items = [];
      var looksNested = data.some(function (n) { return n && Array.isArray(n.children) && n.children.length; });
      if (looksNested) {
        flattenCategories(data, 0, items);
      } else {
        items = data.slice().sort(function (a, b) {
          return String(a.path || a.name || '').localeCompare(String(b.path || b.name || ''));
        });
      }
      ui.renderTable(list, [
        {
          key: 'name',
          label: ui.t('field_name', 'Name'),
          render: function (r) {
            return ui.escapeHtml(depthPrefix(r.depth) + (r.name || r.slug || r.code || '—'));
          }
        },
        {
          key: 'slug',
          label: ui.t('field_slug', 'Slug'),
          render: function (r) { return ui.escapeHtml(r.slug || '—'); }
        },
        {
          key: 'status',
          label: ui.t('field_status', 'Status'),
          render: function (r) { return ui.statusBadge(r.status || '—'); }
        }
      ], items, { onRowClick: openCategory });
    } catch (error) {
      ui.handleError(error);
      list.innerHTML = '<div class="admin-muted p-3">' + ui.escapeHtml(error.message) + '</div>';
    }
  }

  async function openCategory(row) {
    var panel = document.getElementById('entityDetailPanel');
    var detail = document.getElementById('entityDetail');
    var form = document.getElementById('schemaForm');
    panel.hidden = false;
    detail.innerHTML = ui.entityDetail(row, [
      { key: 'name', label: ui.t('field_name', 'Name') },
      { key: 'slug', label: ui.t('field_slug', 'Slug') },
      { key: 'status', label: ui.t('field_status', 'Status') },
      { key: 'uuid', label: ui.t('field_uuid', 'UUID') },
      { key: 'parent_uuid', label: ui.t('field_parent', 'Parent') },
      { key: 'depth', label: ui.t('field_depth', 'Depth') },
      { key: 'path', label: ui.t('field_path', 'Path') },
      { key: 'sort_order', label: ui.t('field_sort_order', 'Sort order') },
      { key: 'description', label: ui.t('field_description', 'Description') }
    ], { auto: false });
    try {
      var schema = await api.get('/catalog/categories/' + encodeURIComponent(row.uuid) + '/attribute-schema');
      document.getElementById('schemaUuid').value = row.uuid;
      document.getElementById('schemaJson').value = JSON.stringify(schema.data || {}, null, 2);
      form.hidden = false;
      detail.innerHTML += '<details class="admin-json-details mt-3"><summary class="admin-muted small">' +
        ui.escapeHtml(ui.t('attribute_schema', 'Attribute schema')) + '</summary>' +
        ui.entityDetail(schema.data || {}, null, { includeRaw: true }) + '</details>';
    } catch (error) {
      form.hidden = true;
      detail.innerHTML += '<div class="admin-muted mt-2">' + ui.escapeHtml(error.message) + '</div>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadList();
    document.addEventListener('admin:page-refresh', loadList);
    ui.bindForm(document.getElementById('schemaForm'), async function (data) {
      try {
        var schema = JSON.parse(data.schema_json || '{}');
        await api.put('/catalog/categories/' + encodeURIComponent(data.uuid) + '/attribute-schema', schema);
        ui.flash(ui.t('success', 'Success'), 'success');
      } catch (error) {
        ui.handleError(error);
      }
    });
  });
})(document, window.RatebAdminApi, window.RatebAdminUi);
