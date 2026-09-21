/**
 * RATEB AI Control Tower — tabs + refresh (soft-nav safe).
 */
(function (root, doc) {
  'use strict';

  function qs(sel, el) {
    return (el || doc).querySelector(sel);
  }
  function qsa(sel, el) {
    return Array.prototype.slice.call((el || doc).querySelectorAll(sel));
  }

  function activate(tower, name) {
    if (!tower) return;
    name = name || 'overview';
    qsa('.rateb-ct-tab', tower).forEach(function (btn) {
      var on = btn.getAttribute('data-ct-tab') === name;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    qsa('.rateb-ct-panel', tower).forEach(function (panel) {
      var on = panel.getAttribute('data-ct-panel') === name;
      panel.classList.toggle('is-active', on);
      panel.hidden = !on;
    });
  }

  function refreshTower(tower, btn) {
    var endpoint = tower.getAttribute('data-tower-endpoint') || '';
    if (btn) btn.disabled = true;
    if (!endpoint) {
      root.location.reload();
      return;
    }
    fetch(endpoint, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then(function () { root.location.reload(); })
      .catch(function () { root.location.reload(); })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  function bind(tower) {
    if (!tower) return;
    if (tower.getAttribute('data-ct-bound') === '1') return;
    tower.setAttribute('data-ct-bound', '1');
    qsa('.rateb-ct-tab', tower).forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        activate(tower, btn.getAttribute('data-ct-tab') || 'overview');
      });
    });
    var refresh = qs('[data-ct-refresh]', tower);
    if (refresh) {
      refresh.addEventListener('click', function (e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        refreshTower(tower, refresh);
      });
    }
    activate(tower, 'overview');
  }

  function boot() {
    qsa('.rateb-ct').forEach(bind);
  }

  if (!root.__ratebCtClickV1) {
    root.__ratebCtClickV1 = true;
    doc.addEventListener('click', function (e) {
      var tab = e.target && e.target.closest ? e.target.closest('.rateb-ct-tab') : null;
      var refresh = e.target && e.target.closest ? e.target.closest('[data-ct-refresh]') : null;
      if (!tab && !refresh) return;
      var tower = (tab || refresh).closest('.rateb-ct');
      if (!tower) return;
      e.preventDefault();
      try { e.stopPropagation(); } catch (eStop) {}
      if (tab) {
        activate(tower, tab.getAttribute('data-ct-tab') || 'overview');
        return;
      }
      refreshTower(tower, refresh);
    }, true);
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  doc.addEventListener('rateb:nav:afterEnter', boot);
  doc.addEventListener('rateb:soft-nav:afterEnter', function () {
    qsa('.rateb-ct').forEach(function (el) {
      el.removeAttribute('data-ct-bound');
    });
    boot();
  });
})(window, document);
