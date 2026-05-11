(function () {
  'use strict';

  var healthEl = document.getElementById('infra-provider-health');
  var capEl = document.getElementById('infra-provider-capability');
  var noticeEl = document.getElementById('infra-provider-notice');
  if (!healthEl || !capEl) return;

  function formatHealthSummary(health, data) {
    if (data && data.ok === false && data.message) {
      return 'snapshot unavailable\n\n' + String(data.message);
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
      lines.push('Add rows to ratib_infra_provider_activations or pass tenant_id / agency_id if you use scoped activations.');
      lines.push('');
    }
    lines.push(JSON.stringify(caps || {}, null, 2));
    return lines.join('\n');
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
          noticeEl.textContent = data.message;
        } else if (data && (data.degraded || data.message)) {
          noticeEl.hidden = false;
          noticeEl.textContent = data.message || 'Response is degraded; see panels below.';
        } else if (data && data.ok && allUnavailable && capsEmpty) {
          noticeEl.hidden = false;
          noticeEl.textContent =
            'Every provider type is unavailable and no capabilities were discovered. Run migration 005 if needed, insert ratib_infra_provider_activations rows, set RATIB_INFRA_PROVIDER_BINDINGS, and enable RATIB_INFRA_MARKETPLACE_ENABLED where appropriate.';
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
})();
