(function () {
  'use strict';

  var form = document.getElementById('infra-domain-search-form');
  var input = document.getElementById('infra-domain-q');
  var results = document.getElementById('infra-domain-results');
  var hint = document.getElementById('infra-domain-search-hint');
  if (!form || !input || !results) return;

  var apiRoot = typeof window.RATIB_INFRA_API_ROOT === 'string' ? window.RATIB_INFRA_API_ROOT : '';

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function render(items) {
    results.innerHTML = '';
    if (!items || !items.length) {
      results.innerHTML = '<p class="infra-domain-empty">No results. Try another keyword or check provider activation.</p>';
      return;
    }
    var grid = document.createElement('div');
    grid.className = 'infra-domain-results-grid';
    items.forEach(function (it) {
      var fqdn = it.fqdn || it.domain || it.name || '';
      var avail = it.available;
      var provider = it.provider || '';
      var row = document.createElement('article');
      row.className = 'infra-domain-result-card';
      var statusClass = avail === true ? 'infra-domain-status--yes' : avail === false ? 'infra-domain-status--no' : 'infra-domain-status--unk';
      var statusLabel = avail === true ? 'Available' : avail === false ? 'Taken' : 'Unknown';
      row.innerHTML =
        '<div class="infra-domain-result-main">' +
        '<strong class="infra-domain-fqdn">' + esc(fqdn) + '</strong>' +
        '<span class="infra-domain-status ' + statusClass + '">' + esc(statusLabel) + '</span>' +
        '</div>' +
        (provider ? '<span class="infra-domain-provider">' + esc(provider) + '</span>' : '');
      grid.appendChild(row);
    });
    results.appendChild(grid);
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var q = String(input.value || '').trim();
    if (!q) return;
    results.innerHTML = '<p class="infra-domain-loading">Searching…</p>';
    if (hint) hint.textContent = '';

    var url = apiRoot + '/api/infrastructure-marketplace/domain-search.php?q=' + encodeURIComponent(q);
    fetch(url, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.ok && Array.isArray(data.items)) {
          render(data.items);
          if (hint) hint.textContent = '';
        } else {
          results.innerHTML = '';
          if (hint) {
            hint.textContent = (data && data.message) ? String(data.message) : 'Search unavailable. Configure registrar providers and database migrations in Control Panel.';
          } else {
            results.innerHTML = '<p class="infra-domain-empty">Search unavailable.</p>';
          }
        }
      })
      .catch(function () {
        results.innerHTML = '<p class="infra-domain-empty">Network error. Try again.</p>';
      });
  });

  function scrollIfFocused() {
    try {
      var p = new URLSearchParams(window.location.search);
      if (p.get('focus') === 'domains') {
        document.getElementById('infra-domain-search').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    } catch (e) { /* ignore */
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scrollIfFocused);
  } else {
    scrollIfFocused();
  }
})();
