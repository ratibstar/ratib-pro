(function (window, document) {
  'use strict';

  var config = window.RatebAdminConfig || {};
  var i18n = config.i18n || {};

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function flash(message, type) {
    var el = document.getElementById('adminFlash');
    if (!el) {
      return;
    }
    el.hidden = !message;
    el.className = 'admin-flash' + (type ? ' is-' + type : '');
    el.textContent = message || '';
  }

  function setLoading(target, loading) {
    if (!target) {
      return;
    }
    if (loading) {
      target.innerHTML = '<div class="admin-empty p-3">' + escapeHtml(i18n.loading || 'Loading…') + '</div>';
    }
  }

  function statusBadge(status) {
    var raw = status == null || status === '' ? '' : String(status);
    var labelKey = raw ? 'status_' + raw.toLowerCase().replace(/[^a-z0-9_]+/g, '_') : '';
    var label = raw ? (i18n[labelKey] || raw) : '—';
    var cls = 'admin-badge-status';
    if (raw) {
      cls += ' is-' + raw.toLowerCase().replace(/[^a-z0-9_-]+/g, '-');
    }
    return '<span class="' + cls + '">' + escapeHtml(label) + '</span>';
  }

  function renderTable(container, columns, rows, options) {
    options = options || {};
    if (!container) {
      return;
    }
    if (!rows || !rows.length) {
      container.innerHTML = '<div class="admin-empty p-3">' + escapeHtml(options.empty || i18n.empty || 'No data') + '</div>';
      return;
    }

    var html = '<div class="admin-table-wrap"><table class="table admin-table"><thead><tr>';
    columns.forEach(function (col) {
      html += '<th>' + escapeHtml(col.label) + '</th>';
    });
    html += '</tr></thead><tbody>';

    rows.forEach(function (row, index) {
      html += '<tr data-index="' + index + '">';
      columns.forEach(function (col) {
        var value = typeof col.render === 'function' ? col.render(row, index) : row[col.key];
        html += '<td>' + (value == null ? '—' : value) + '</td>';
      });
      html += '</tr>';
    });

    html += '</tbody></table></div>';
    container.innerHTML = html;

    if (typeof options.onRowClick === 'function') {
      container.querySelectorAll('tbody tr').forEach(function (tr) {
        tr.style.cursor = 'pointer';
        tr.addEventListener('click', function () {
          var idx = Number(tr.getAttribute('data-index'));
          options.onRowClick(rows[idx], idx, tr);
        });
      });
    }
  }

  function bindForm(form, handler) {
    if (!form) {
      return;
    }
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var data = {};
      var fd = new FormData(form);
      fd.forEach(function (value, key) {
        if (Object.prototype.hasOwnProperty.call(data, key)) {
          if (!Array.isArray(data[key])) {
            data[key] = [data[key]];
          }
          data[key].push(value);
        } else {
          data[key] = value;
        }
      });
      handler(data, form);
    });
  }

  function can(permission) {
    var list = config.permissions || [];
    return list.indexOf(permission) !== -1;
  }

  function jsonBlock(value) {
    var text = typeof value === 'string' ? value : JSON.stringify(value, null, 2);
    return '<pre class="admin-json" dir="ltr">' + escapeHtml(text) + '</pre>';
  }

  function rawJsonDetails(value, options) {
    options = options || {};
    var open = options.open ? ' open' : '';
    return '<details class="admin-json-details mt-2"' + open + '><summary class="admin-muted small">' +
      escapeHtml(i18n.raw_json || 'Raw JSON') + '</summary>' + jsonBlock(value) + '</details>';
  }

  function codeCell(value) {
    var safe = escapeHtml(value == null ? '' : value);
    return '<code class="admin-code" title="' + safe + '" dir="ltr">' + safe + '</code>';
  }

  function kvGrid(items) {
    var html = '<div class="admin-kv-grid">';
    (items || []).forEach(function (item) {
      html += '<div class="admin-kv-item">';
      html += '<div class="admin-kv-label">' + escapeHtml(item.label) + '</div>';
      html += '<div class="' + (item.mono ? 'admin-kv-value admin-kv-value-mono' : 'admin-kv-value') + '">';
      html += item.html != null ? item.html : escapeHtml(item.value == null ? '—' : item.value);
      html += '</div></div>';
    });
    html += '</div>';
    return html;
  }

  function fieldLabel(key) {
    var map = {
      uuid: 'field_uuid',
      sku: 'field_sku',
      name: 'field_name',
      status: 'field_status',
      category_uuid: 'field_category',
      category: 'field_category',
      brand_uuid: 'field_brand',
      brand: 'field_brand',
      family_uuid: 'field_family',
      family: 'field_family',
      unit_uuid: 'field_unit',
      slug: 'field_slug',
      code: 'field_code',
      url: 'field_url',
      type: 'field_type',
      request_type: 'field_type',
      entity_type: 'field_entity',
      score: 'field_score',
      amount: 'field_amount',
      currency: 'field_currency',
      channel_uuid: 'field_channel',
      primary_barcode: 'field_barcode',
      short_description: 'field_short_description',
      description: 'field_description',
      parent_uuid: 'field_parent',
      depth: 'field_depth',
      path: 'field_path',
      sort_order: 'field_sort_order',
      image_path: 'field_image',
      lock_version: 'field_lock_version',
      version_number: 'field_version',
      version: 'field_version',
      created_at: 'field_created_at',
      updated_at: 'field_updated_at',
      published_at: 'field_published_at',
      is_active: 'field_active',
      is_bundle: 'field_bundle',
      meta_title: 'field_meta_title',
      meta_description: 'field_meta_description',
      canonical_url: 'field_canonical',
      events: 'field_events',
      weight: 'field_weight',
      priority: 'field_priority',
      match_field: 'field_match_field',
      match_type: 'field_match_type',
      collection_type: 'field_type',
      source_code: 'field_source',
      pending: 'queue_pending'
    };
    var i18nKey = map[key] || ('field_' + key);
    if (i18n[i18nKey]) {
      return i18n[i18nKey];
    }
    return String(key || '').replace(/_/g, ' ');
  }

  function formatScalar(key, value) {
    if (value == null || value === '') {
      return { value: '—' };
    }
    if (key === 'status' || /_status$/.test(key)) {
      return { html: statusBadge(value) };
    }
    if (typeof value === 'boolean' || value === 0 || value === 1 || value === '0' || value === '1') {
      if (key === 'is_active' || key === 'is_bundle' || typeof value === 'boolean') {
        var on = value === true || value === 1 || value === '1';
        return { value: on ? (i18n.yes || 'Yes') : (i18n.no || 'No') };
      }
    }
    if (Array.isArray(value)) {
      if (!value.length) {
        return { value: '—' };
      }
      if (value.every(function (v) { return typeof v !== 'object' || v === null; })) {
        return { value: value.map(String).join(', ') };
      }
      return null;
    }
    if (typeof value === 'object') {
      return null;
    }
    var mono = /uuid|_id$|_uuid$|sku|slug|code|barcode|path|url|version|hash|token/i.test(key);
    return { value: String(value), mono: mono };
  }

  function entityDetail(obj, fieldMap, options) {
    options = options || {};
    obj = obj && typeof obj === 'object' ? obj : {};
    var items = [];
    var used = {};

    if (Array.isArray(fieldMap) && fieldMap.length) {
      fieldMap.forEach(function (field) {
        if (!field || !field.key) {
          return;
        }
        used[field.key] = true;
        var raw = Object.prototype.hasOwnProperty.call(obj, field.key) ? obj[field.key] : undefined;
        if (typeof field.render === 'function') {
          items.push({
            label: field.label || fieldLabel(field.key),
            html: field.render(raw, obj),
            mono: !!field.mono
          });
          return;
        }
        if (field.html != null) {
          items.push({
            label: field.label || fieldLabel(field.key),
            html: field.html,
            mono: !!field.mono
          });
          return;
        }
        var formatted = formatScalar(field.key, raw != null ? raw : field.value);
        if (!formatted) {
          return;
        }
        items.push({
          label: field.label || fieldLabel(field.key),
          value: formatted.value,
          html: formatted.html,
          mono: field.mono != null ? !!field.mono : !!formatted.mono
        });
      });
    }

    if (options.auto !== false) {
      Object.keys(obj).forEach(function (key) {
        if (used[key] || key === 'children') {
          return;
        }
        var formatted = formatScalar(key, obj[key]);
        if (!formatted) {
          return;
        }
        items.push({
          label: fieldLabel(key),
          value: formatted.value,
          html: formatted.html,
          mono: !!formatted.mono
        });
      });
    }

    var html = items.length
      ? kvGrid(items)
      : '<div class="admin-muted">' + escapeHtml(i18n.empty || 'No data') + '</div>';

    if (options.includeRaw !== false) {
      html += rawJsonDetails(obj, { open: !!options.rawOpen });
    }
    return html;
  }

  function renderHealthPanel(health, options) {
    options = options || {};
    health = health || {};
    var html = kvGrid([
      { label: i18n.status || 'Status', html: statusBadge(health.status || '—') },
      { label: i18n.service || 'Service', value: health.service || '—' },
      { label: i18n.version || 'Version', value: health.version || health.architecture_version || '—', mono: true },
      { label: i18n.release || 'Release', value: health.release || '—', mono: true },
      { label: i18n.build || 'Build', value: health.build_timestamp || '—', mono: true }
    ]);
    if (options.includeRaw !== false) {
      html += rawJsonDetails(health);
    }
    return html;
  }

  function renderReadyPanel(ready, options) {
    options = options || {};
    ready = ready || {};
    var checks = ready.checks && typeof ready.checks === 'object' ? ready.checks : {};
    var keys = Object.keys(checks);
    var html = '<div class="mb-2">' + statusBadge(ready.status || '—') + '</div>';
    if (keys.length) {
      html += '<ul class="admin-check-list">';
      keys.forEach(function (key) {
        var ok = !!checks[key];
        html += '<li class="admin-check-item' + (ok ? ' is-ok' : ' is-fail') + '">';
        html += '<span>' + escapeHtml(key) + '</span>';
        html += '<span>' + (ok ? '✓' : '✗') + '</span></li>';
      });
      html += '</ul>';
    } else {
      html += '<div class="admin-muted">' + escapeHtml(i18n.empty || 'No data') + '</div>';
    }
    if (options.includeRaw !== false) {
      html += rawJsonDetails(ready);
    }
    return html;
  }

  function renderQueuePanel(data, options) {
    options = options || {};
    data = data || {};
    var queues = Array.isArray(data.queues) ? data.queues : [];
    var pending = data.pending != null ? data.pending : null;
    var html = kvGrid([
      { label: i18n.queue_pending || 'Pending', value: pending != null ? String(pending) : '0' },
      { label: i18n.queue_count || 'Queues', value: String(queues.length) }
    ]);
    if (!queues.length) {
      html += '<div class="admin-muted mt-3">' + escapeHtml(i18n.queue_empty || 'No queued jobs right now.') + '</div>';
    } else {
      html += '<div class="admin-table-wrap mt-3"><table class="table admin-table"><thead><tr>' +
        '<th>' + escapeHtml(i18n.queue_name || 'Queue') + '</th>' +
        '<th>' + escapeHtml(i18n.queue_pending || 'Pending') + '</th></tr></thead><tbody>';
      queues.forEach(function (row) {
        html += '<tr><td>' + escapeHtml(row.name || row.queue || '—') + '</td><td>' +
          escapeHtml(String(row.pending != null ? row.pending : (row.count != null ? row.count : '—'))) + '</td></tr>';
      });
      html += '</tbody></table></div>';
    }
    if (options.includeRaw) {
      html += rawJsonDetails(data);
    }
    return html;
  }

  function handleError(error) {
    var message = error && error.message ? error.message : (i18n.error || 'Error');
    if (error && (error.status === 401 || error.status === 403)) {
      message = i18n.unauthorized || message;
    }
    flash(message, 'error');
    return message;
  }

  window.RatebAdminUi = {
    escapeHtml: escapeHtml,
    flash: flash,
    setLoading: setLoading,
    statusBadge: statusBadge,
    renderTable: renderTable,
    bindForm: bindForm,
    can: can,
    jsonBlock: jsonBlock,
    rawJsonDetails: rawJsonDetails,
    codeCell: codeCell,
    kvGrid: kvGrid,
    fieldLabel: fieldLabel,
    entityDetail: entityDetail,
    renderHealthPanel: renderHealthPanel,
    renderReadyPanel: renderReadyPanel,
    renderQueuePanel: renderQueuePanel,
    handleError: handleError,
    t: function (key, fallback) {
      return i18n[key] || fallback || key;
    }
  };
})(window, document);
