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
    var safe = escapeHtml(status || '—');
    var cls = 'admin-badge-status';
    if (status) {
      cls += ' is-' + String(status).toLowerCase().replace(/[^a-z0-9_-]+/g, '-');
    }
    return '<span class="' + cls + '">' + safe + '</span>';
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

  function codeCell(value) {
    var safe = escapeHtml(value == null ? '' : value);
    return '<code class="admin-code" title="' + safe + '" dir="ltr">' + safe + '</code>';
  }

  function kvGrid(items) {
    var html = '<div class="admin-kv-grid">';
    items.forEach(function (item) {
      html += '<div class="admin-kv-item">';
      html += '<div class="admin-kv-label">' + escapeHtml(item.label) + '</div>';
      html += '<div class="' + (item.mono ? 'admin-kv-value admin-kv-value-mono' : 'admin-kv-value') + '">';
      html += item.html != null ? item.html : escapeHtml(item.value == null ? '—' : item.value);
      html += '</div></div>';
    });
    html += '</div>';
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
      html += '<details class="admin-json-details"><summary class="admin-muted small">' +
        escapeHtml(i18n.raw_json || 'Raw JSON') + '</summary>' + jsonBlock(health) + '</details>';
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
      html += '<details class="admin-json-details mt-2"><summary class="admin-muted small">' +
        escapeHtml(i18n.raw_json || 'Raw JSON') + '</summary>' + jsonBlock(ready) + '</details>';
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
      html += '<details class="admin-json-details mt-2"><summary class="admin-muted small">' +
        escapeHtml(i18n.raw_json || 'Raw JSON') + '</summary>' + jsonBlock(data) + '</details>';
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
    codeCell: codeCell,
    kvGrid: kvGrid,
    renderHealthPanel: renderHealthPanel,
    renderReadyPanel: renderReadyPanel,
    renderQueuePanel: renderQueuePanel,
    handleError: handleError,
    t: function (key, fallback) {
      return i18n[key] || fallback || key;
    }
  };
})(window, document);
