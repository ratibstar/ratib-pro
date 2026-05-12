(function () {
  'use strict';

  var healthEl = document.getElementById('infra-provider-health');
  var capEl = document.getElementById('infra-provider-capability');
  var actEl = document.getElementById('infra-provider-activations');
  var noticeEl = document.getElementById('infra-provider-notice');
  if (!healthEl || !capEl) return;

  function csrfToken() {
    var m = document.querySelector('meta[name="infra-control-csrf"]');
    return m && m.getAttribute('content') ? String(m.getAttribute('content')) : '';
  }

  function formatHealthSummary(health, data) {
    if (data && data.ok === false && data.message) {
      var extra = '';
      if (data.error_class || data.error_detail) {
        extra =
          '\n\n' +
          (data.error_class ? String(data.error_class) : '') +
          (data.error_detail ? '\n' + String(data.error_detail) : '');
      }
      return 'snapshot unavailable\n\n' + String(data.message) + extra;
    }
    if (!Array.isArray(health) || health.length === 0) {
      return 'No health rows returned.';
    }
    return health
      .map(function (r) {
        if (!r || typeof r !== 'object') return String(r);
        var t = r.provider_type != null ? String(r.provider_type) : '?';
        var s = r.status != null ? String(r.status) : '?';
        var n = r.active_count != null ? String(r.active_count) : '0';
        return t + ': ' + s + ' (active ' + n + ')';
      })
      .join('\n');
  }

  function capabilityTotal(caps) {
    if (!caps || typeof caps !== 'object') return 0;
    var n = 0;
    Object.keys(caps).forEach(function (k) {
      if (Array.isArray(caps[k])) n += caps[k].length;
    });
    return n;
  }

  function formatCapabilities(caps) {
    var total = capabilityTotal(caps);
    var lines = [];
    if (total === 0) {
      lines.push('No capability matrices yet (no enabled provider rows for this tenant scope).');
      lines.push('Use “Database activations” below or insert into ratib_infra_provider_activations.');
      lines.push('');
    }
    lines.push(JSON.stringify(caps || {}, null, 2));
    return lines.join('\n');
  }

  function formatActivations(rows) {
    if (!Array.isArray(rows) || rows.length === 0) {
      return 'No rows. Add a registrar row with the Namecheap adapter class.';
    }
    return rows
      .map(function (r) {
        return (
          'id=' +
          r.id +
          ' type=' +
          r.provider_type +
          ' code=' +
          r.provider_code +
          ' class=' +
          r.provider_class +
          ' prio=' +
          r.priority_weight +
          ' en=' +
          r.is_enabled
        );
      })
      .join('\n');
  }

  fetch('/api/infrastructure-marketplace/providers.php', { credentials: 'same-origin' })
    .then(function (r) {
      return r.json();
    })
    .then(function (data) {
      var allUnavailable =
        Array.isArray(data.health) &&
        data.health.length > 0 &&
        data.health.every(function (row) {
          return row && String(row.status || '').toLowerCase() === 'unavailable';
        });
      var capsEmpty = capabilityTotal(data.capabilities || {}) === 0;

      if (noticeEl) {
        if (data && data.ok === false && data.message) {
          noticeEl.hidden = false;
          noticeEl.textContent =
            data.message +
            (data.error_detail ? '\n\n' + String(data.error_class || '') + '\n' + String(data.error_detail) : '');
        } else if (data && (data.degraded || data.message)) {
          noticeEl.hidden = false;
          noticeEl.textContent = data.message || 'Response is degraded; see panels below.';
        } else if (data && data.ok && allUnavailable && capsEmpty) {
          noticeEl.hidden = false;
          noticeEl.textContent =
            'Every provider type is unavailable and no capabilities were discovered. Run migration 005, add activation rows, and configure runtime in Infrastructure Control.';
        } else {
          noticeEl.hidden = true;
        }
      }

      healthEl.textContent = formatHealthSummary(data.health || [], data);
      capEl.textContent = formatCapabilities(data.capabilities || {});
    })
    .catch(function () {
      if (noticeEl) {
        noticeEl.hidden = false;
        noticeEl.textContent = 'Request failed; check network or control session.';
      }
      healthEl.textContent = 'Unable to load provider health.';
      capEl.textContent = 'Unable to load provider capabilities.';
    });

  if (actEl) {
    fetch('/api/infrastructure-marketplace/provider-activation.php', { credentials: 'same-origin' })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (data && data.ok && Array.isArray(data.rows)) {
          actEl.textContent = formatActivations(data.rows);
        } else {
          actEl.textContent = (data && data.message) || 'Unable to load activations.';
        }
      })
      .catch(function () {
        actEl.textContent = 'Unable to load activations (session or network).';
      });
  }

  var upsertForm = document.getElementById('infra-provider-upsert-form');
  if (upsertForm) {
    upsertForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var tok = csrfToken();
      var fd = new FormData(upsertForm);
      var payload = {
        csrf_token: fd.get('csrf_token') || tok,
        action: 'upsert',
        provider_type: fd.get('provider_type'),
        provider_code: fd.get('provider_code'),
        provider_class: fd.get('provider_class'),
        priority_weight: parseInt(String(fd.get('priority_weight') || '100'), 10) || 100,
        is_enabled: fd.get('is_enabled') !== null,
      };
      var tid = String(fd.get('tenant_id') || '').trim();
      var aid = String(fd.get('agency_id') || '').trim();
      if (tid !== '') payload.tenant_id = parseInt(tid, 10);
      if (aid !== '') payload.agency_id = parseInt(aid, 10);

      fetch('/api/infrastructure-marketplace/provider-activation.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': payload.csrf_token || '',
        },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (j) {
          alert(j.ok ? 'Activation saved. Reload page to refresh lists.' : j.message || 'Save failed');
          if (j.ok && actEl) {
            actEl.textContent = 'Reload page to refresh.';
          }
        })
        .catch(function () {
          alert('Network error');
        });
    });
  }
})();
