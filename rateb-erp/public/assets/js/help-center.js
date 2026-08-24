(function () {
  'use strict';

  var root = document.getElementById('rateb-help-center');
  if (!root || root.getAttribute('data-hc-bound') === '1') return;
  root.setAttribute('data-hc-bound', '1');

  var input = document.getElementById('hc-search-input');
  var results = document.getElementById('hc-search-results');
  var empty = document.getElementById('hc-search-empty');
  var clearBtn = document.getElementById('hc-search-clear');
  var indexNode = document.getElementById('hc-search-index');
  if (!input || !results || !indexNode) return;

  var index = [];
  try {
    index = JSON.parse(indexNode.textContent || '[]') || [];
  } catch (e) {
    index = [];
  }

  var home = root.getAttribute('data-hc-home') || '/admin/help';
  var active = -1;
  var lastHits = [];

  function normalize(s) {
    return String(s || '')
      .toLowerCase()
      .replace(/[^\p{L}\p{N}\s\-]/gu, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function scoreItem(item, tokens) {
    var title = normalize(item.title);
    var hay = normalize(
      [item.title, item.summary, item.module_title, item.module]
        .concat(item.keywords || [])
        .join(' ')
    );
    var score = 0;
    for (var i = 0; i < tokens.length; i++) {
      var t = tokens[i];
      if (!t) continue;
      if (title.indexOf(t) !== -1) score += 12;
      if (hay.indexOf(t) !== -1) score += 6;
      var kws = item.keywords || [];
      for (var k = 0; k < kws.length; k++) {
        var kn = normalize(kws[k]);
        if (kn === t) score += 10;
        else if (kn.indexOf(t) !== -1) score += 4;
      }
    }
    if (item.type === 'article') score += 1;
    return score;
  }

  function search(q) {
    var n = normalize(q);
    if (!n) return [];
    var tokens = n.split(' ').filter(Boolean);
    var scored = [];
    for (var i = 0; i < index.length; i++) {
      var item = index[i];
      var sc = scoreItem(item, tokens);
      if (sc > 0) {
        scored.push({ item: item, score: sc });
      }
    }
    scored.sort(function (a, b) { return b.score - a.score; });
    return scored.slice(0, 12).map(function (row) { return row.item; });
  }

  function itemUrl(item) {
    if (item.type === 'module') {
      return home.replace(/\/?$/, '/') + 'module/' + encodeURIComponent(item.slug);
    }
    return home.replace(/\/?$/, '/') + 'article/' + encodeURIComponent(item.slug);
  }

  function render(hits) {
    lastHits = hits;
    active = hits.length ? 0 : -1;
    results.innerHTML = '';
    if (!hits.length) {
      results.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      if (empty) empty.hidden = false;
      return;
    }
    if (empty) empty.hidden = true;
    results.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    for (var i = 0; i < hits.length; i++) {
      var item = hits[i];
      var a = document.createElement('a');
      a.className = 'hc-search__hit' + (i === 0 ? ' is-active' : '');
      a.setAttribute('role', 'option');
      a.href = itemUrl(item);
      a.id = 'hc-hit-' + i;
      a.innerHTML =
        '<span class="hc-search__hit-icon"><i class="fas ' +
        (item.icon || 'fa-circle-question') +
        '" aria-hidden="true"></i></span>' +
        '<span><span class="hc-search__hit-title"></span><span class="hc-search__hit-meta"></span></span>';
      a.querySelector('.hc-search__hit-title').textContent = item.title || '';
      a.querySelector('.hc-search__hit-meta').textContent =
        (item.type === 'module' ? '' : (item.module_title || '')) +
        (item.minutes ? ' · ' + item.minutes + 'm' : '');
      results.appendChild(a);
    }
  }

  function setActive(next) {
    var nodes = results.querySelectorAll('.hc-search__hit');
    if (!nodes.length) return;
    if (active >= 0 && nodes[active]) nodes[active].classList.remove('is-active');
    active = (next + nodes.length) % nodes.length;
    nodes[active].classList.add('is-active');
    nodes[active].scrollIntoView({ block: 'nearest' });
  }

  function onInput() {
    var q = input.value || '';
    if (clearBtn) clearBtn.hidden = q.trim() === '';
    if (!q.trim()) {
      results.hidden = true;
      results.innerHTML = '';
      if (empty) empty.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      return;
    }
    render(search(q));
  }

  input.addEventListener('input', onInput);
  input.addEventListener('keydown', function (ev) {
    if (results.hidden) return;
    if (ev.key === 'ArrowDown') {
      ev.preventDefault();
      setActive(active + 1);
    } else if (ev.key === 'ArrowUp') {
      ev.preventDefault();
      setActive(active - 1);
    } else if (ev.key === 'Enter' && active >= 0 && lastHits[active]) {
      ev.preventDefault();
      window.location.href = itemUrl(lastHits[active]);
    } else if (ev.key === 'Escape') {
      results.hidden = true;
      input.setAttribute('aria-expanded', 'false');
    }
  });

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      input.value = '';
      clearBtn.hidden = true;
      results.hidden = true;
      results.innerHTML = '';
      if (empty) empty.hidden = true;
      input.focus();
    });
  }

  document.addEventListener('click', function (ev) {
    if (!root.contains(ev.target)) {
      results.hidden = true;
      input.setAttribute('aria-expanded', 'false');
    }
  });

  try {
    var params = new URLSearchParams(window.location.search || '');
    var q0 = params.get('q');
    if (q0) {
      input.value = q0;
      onInput();
      input.focus();
    }
  } catch (eQ) { /* ignore */ }
})();
