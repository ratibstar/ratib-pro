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
    return '<pre class="admin-json">' + escapeHtml(typeof value === 'string' ? value : JSON.stringify(value, null, 2)) + '</pre>';
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
    handleError: handleError,
    t: function (key, fallback) {
      return i18n[key] || fallback || key;
    }
  };
})(window, document);
