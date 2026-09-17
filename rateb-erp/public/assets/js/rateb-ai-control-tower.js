/**
 * RATEB AI Control Tower — tabs + optional JSON refresh (no writes).
 */
(function () {
  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }
  function qsa(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function activate(root, name) {
    qsa('.rateb-ct-tab', root).forEach(function (btn) {
      var on = btn.getAttribute('data-ct-tab') === name;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    qsa('.rateb-ct-panel', root).forEach(function (panel) {
      var on = panel.getAttribute('data-ct-panel') === name;
      panel.classList.toggle('is-active', on);
      panel.hidden = !on;
    });
  }

  function bind(root) {
    if (!root || root.getAttribute('data-ct-bound') === '1') return;
    root.setAttribute('data-ct-bound', '1');
    qsa('.rateb-ct-tab', root).forEach(function (btn) {
      btn.addEventListener('click', function () {
        activate(root, btn.getAttribute('data-ct-tab') || 'overview');
      });
    });
    var refresh = qs('[data-ct-refresh]', root);
    if (refresh) {
      refresh.addEventListener('click', function () {
        var endpoint = root.getAttribute('data-tower-endpoint') || '';
        if (!endpoint) {
          window.location.reload();
          return;
        }
        refresh.disabled = true;
        fetch(endpoint, {
          method: 'GET',
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        })
          .then(function (r) { return r.json(); })
          .then(function () {
            window.location.reload();
          })
          .catch(function () {
            window.location.reload();
          })
          .finally(function () {
            refresh.disabled = false;
          });
      });
    }
    activate(root, 'overview');
  }

  function boot() {
    qsa('.rateb-ct').forEach(bind);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  document.addEventListener('rateb:nav:afterEnter', boot);
  document.addEventListener('rateb:soft-nav:afterEnter', function () {
    qsa('.rateb-ct').forEach(function (el) {
      el.removeAttribute('data-ct-bound');
    });
    boot();
  });
})();
