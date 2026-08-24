(function () {
  'use strict';

  if (window.__RATEB_HELP_WIDGET__) return;
  window.__RATEB_HELP_WIDGET__ = 1;

  var cfg = window.__RATEB_HELP_WIDGET_CFG__ || null;
  if (!cfg) {
    var cfgNode = document.getElementById('rateb-help-widget-cfg');
    if (cfgNode) {
      try { cfg = JSON.parse(cfgNode.textContent || '{}'); } catch (eCfg) { cfg = null; }
    }
  }
  if (!cfg || !cfg.homeUrl) return;

  // Hide floating widget on Help Center pages (topbar button remains).
  var path = String(cfg.erpRoute || '');
  if (path === 'admin/help' || path.indexOf('admin/help/') === 0) return;

  var cssHref = cfg.cssUrl;
  if (cssHref && !document.querySelector('link[data-rateb-help-css="1"]')) {
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = cssHref;
    link.setAttribute('data-rateb-help-css', '1');
    document.head.appendChild(link);
  }

  var fab = document.createElement('button');
  fab.type = 'button';
  fab.className = 'rateb-help-fab';
  fab.id = 'rateb-help-fab';
  fab.setAttribute('aria-haspopup', 'dialog');
  fab.setAttribute('aria-expanded', 'false');
  fab.setAttribute('aria-controls', 'rateb-help-panel');
  fab.title = cfg.label || 'Help';
  fab.textContent = '?';

  var panel = document.createElement('div');
  panel.className = 'rateb-help-panel';
  panel.id = 'rateb-help-panel';
  panel.hidden = true;
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-label', cfg.label || 'Help');

  panel.innerHTML =
    '<h3 class="rateb-help-panel__title"></h3>' +
    '<input type="search" class="rateb-help-panel__search" id="rateb-help-panel-search" autocomplete="off">' +
    '<p class="small text-muted mb-2" id="rateb-help-panel-context"></p>' +
    '<ul class="rateb-help-panel__list" id="rateb-help-panel-suggestions"></ul>' +
    '<ul class="rateb-help-panel__list" id="rateb-help-panel-faqs"></ul>' +
    '<div class="rateb-help-panel__footer"><a class="btn btn-sm btn-primary" id="rateb-help-panel-open" href="#"></a></div>';

  document.body.appendChild(fab);
  document.body.appendChild(panel);

  panel.querySelector('.rateb-help-panel__title').textContent = cfg.label || 'Help';
  var searchInput = panel.querySelector('#rateb-help-panel-search');
  searchInput.placeholder = cfg.searchPlaceholder || 'Search help…';
  var openLink = panel.querySelector('#rateb-help-panel-open');
  openLink.href = cfg.homeUrl;
  openLink.textContent = cfg.openLabel || 'Open Help Center';

  function articleUrl(slug) {
    return cfg.homeUrl.replace(/\/?$/, '/') + 'article/' + encodeURIComponent(slug);
  }

  function renderList(node, items, mapFn) {
    node.innerHTML = '';
    (items || []).forEach(function (item) {
      var li = document.createElement('li');
      var a = document.createElement('a');
      var mapped = mapFn(item);
      a.href = mapped.href;
      a.textContent = mapped.label;
      li.appendChild(a);
      node.appendChild(li);
    });
  }

  function loadContext() {
    var url = cfg.contextUrl + (cfg.contextUrl.indexOf('?') >= 0 ? '&' : '?') + 'path=' + encodeURIComponent(cfg.erpRoute || '');
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) return;
        var ctx = panel.querySelector('#rateb-help-panel-context');
        if (data.module && data.module.title) {
          ctx.textContent = (cfg.contextLabel || 'Suggested for') + ': ' + data.module.title;
        } else {
          ctx.textContent = '';
        }
        renderList(panel.querySelector('#rateb-help-panel-suggestions'), data.suggestions || [], function (s) {
          return { href: articleUrl(s.slug), label: s.title };
        });
        renderList(panel.querySelector('#rateb-help-panel-faqs'), (data.faqs || []).slice(0, 4), function (f) {
          return { href: cfg.homeUrl, label: f.question };
        });
      })
      .catch(function () { /* ignore */ });
  }

  function toggle(force) {
    var open = typeof force === 'boolean' ? force : panel.hidden;
    panel.hidden = !open;
    fab.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      loadContext();
      searchInput.focus();
    }
  }

  fab.addEventListener('click', function () { toggle(); });

  searchInput.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter') return;
    ev.preventDefault();
    var q = (searchInput.value || '').trim();
    if (!q) {
      window.location.href = cfg.homeUrl;
      return;
    }
    window.location.href = cfg.homeUrl + (cfg.homeUrl.indexOf('?') >= 0 ? '&' : '?') + 'q=' + encodeURIComponent(q);
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && !panel.hidden) toggle(false);
  });

  document.addEventListener('click', function (ev) {
    if (panel.hidden) return;
    if (panel.contains(ev.target) || fab.contains(ev.target)) return;
    toggle(false);
  });
})();
