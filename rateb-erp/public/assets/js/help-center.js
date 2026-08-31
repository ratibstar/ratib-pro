(function () {
  'use strict';

  function normalize(s) {
    var v = String(s || '').toLowerCase();
    try {
      v = v.replace(/[^\p{L}\p{N}\s\-]/gu, ' ');
    } catch (eNorm) {
      v = v.replace(/[^\w\u0600-\u06FF\s\-]/g, ' ');
    }
    return v.replace(/\s+/g, ' ').trim();
  }

  function parseIndex(node) {
    if (!node) return [];
    try {
      return JSON.parse(node.textContent || '[]') || [];
    } catch (e) {
      return [];
    }
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

  function searchLocal(index, q) {
    var n = normalize(q);
    if (!n) return [];
    var tokens = n.split(' ').filter(Boolean);
    var scored = [];
    for (var i = 0; i < index.length; i++) {
      var item = index[i];
      var sc = scoreItem(item, tokens);
      if (sc > 0) scored.push({ item: item, score: sc });
    }
    scored.sort(function (a, b) { return b.score - a.score; });
    return scored.slice(0, 12).map(function (row) { return row.item; });
  }

  function bindHelpSearch(root) {
    if (!root || root.getAttribute('data-hc-bound') === '1') return;
    var input = root.querySelector('#hc-search-input') || document.getElementById('hc-search-input');
    var results = root.querySelector('#hc-search-results') || document.getElementById('hc-search-results');
    var empty = root.querySelector('#hc-search-empty') || document.getElementById('hc-search-empty');
    var clearBtn = root.querySelector('#hc-search-clear') || document.getElementById('hc-search-clear');
    var indexNode = document.getElementById('hc-search-index');
    if (!input || !results) return;

    root.setAttribute('data-hc-bound', '1');

    var index = parseIndex(indexNode);
    var home = root.getAttribute('data-hc-home') || '/admin/help';
    var searchUrl = root.getAttribute('data-hc-search-url') || '';
    var active = -1;
    var lastHits = [];
    var debounceTimer = 0;
    var abortCtl = null;
    var seq = 0;

    function itemUrl(item) {
      if (item.type === 'module') {
        return home.replace(/\/?$/, '/') + 'module/' + encodeURIComponent(item.slug);
      }
      return home.replace(/\/?$/, '/') + 'article/' + encodeURIComponent(item.slug);
    }

    function filterCards(q) {
      var cards = document.querySelectorAll('.hc-module-card');
      if (!cards.length) return;
      var n = normalize(q);
      if (!n) {
        for (var i = 0; i < cards.length; i++) {
          cards[i].hidden = false;
          cards[i].classList.remove('is-hc-muted');
        }
        return;
      }
      var any = false;
      var matchFlags = [];
      for (var c = 0; c < cards.length; c++) {
        var hay = normalize(cards[c].getAttribute('data-hc-hay') || cards[c].textContent || '');
        var ok = hay.indexOf(n) !== -1;
        matchFlags[c] = ok;
        if (ok) any = true;
      }
      for (var d = 0; d < cards.length; d++) {
        if (any) {
          cards[d].hidden = !matchFlags[d];
          cards[d].classList.toggle('is-hc-muted', !matchFlags[d]);
        } else {
          cards[d].hidden = false;
          cards[d].classList.add('is-hc-muted');
        }
      }
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

    function applyQuery(q, hits) {
      filterCards(q);
      render(hits);
    }

    function fetchRemote(q, token) {
      if (!searchUrl) return;
      if (abortCtl && typeof abortCtl.abort === 'function') {
        try { abortCtl.abort(); } catch (eAb) { /* ignore */ }
      }
      abortCtl = typeof AbortController === 'function' ? new AbortController() : null;
      var url = searchUrl + (searchUrl.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q) + '&limit=12';
      var opts = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
      if (abortCtl) opts.signal = abortCtl.signal;
      fetch(url, opts)
        .then(function (res) { return res.ok ? res.json() : null; })
        .then(function (data) {
          if (token !== seq) return;
          var remote = data && Array.isArray(data.results) ? data.results : [];
          if (remote.length) applyQuery(q, remote);
        })
        .catch(function () { /* keep local hits */ });
    }

    function onInput() {
      var q = input.value || '';
      if (clearBtn) clearBtn.hidden = q.trim() === '';
      if (!q.trim()) {
        seq += 1;
        if (abortCtl && typeof abortCtl.abort === 'function') {
          try { abortCtl.abort(); } catch (eClr) { /* ignore */ }
        }
        results.hidden = true;
        results.innerHTML = '';
        if (empty) empty.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        filterCards('');
        return;
      }
      var localHits = searchLocal(index, q);
      filterCards(q);
      if (localHits.length) {
        render(localHits);
      } else if (!searchUrl) {
        render([]);
      } else if (empty) {
        empty.hidden = true;
      }
      seq += 1;
      var token = seq;
      if (debounceTimer) clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        fetchRemote(q, token);
      }, 120);
    }

    var form = root.querySelector('#hc-search-form');
    if (form) {
      form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (active >= 0 && lastHits[active]) {
          window.location.href = itemUrl(lastHits[active]);
        } else {
          onInput();
        }
      });
    }

    input.addEventListener('input', onInput);
    input.addEventListener('search', onInput);
    input.addEventListener('keyup', function (ev) {
      if (ev.key === 'Escape' || ev.key === 'ArrowDown' || ev.key === 'ArrowUp' || ev.key === 'Enter') return;
      onInput();
    });
    input.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && (!results.hidden && active >= 0 && lastHits[active])) {
        ev.preventDefault();
        window.location.href = itemUrl(lastHits[active]);
        return;
      }
      if (results.hidden) return;
      if (ev.key === 'ArrowDown') {
        ev.preventDefault();
        setActive(active + 1);
      } else if (ev.key === 'ArrowUp') {
        ev.preventDefault();
        setActive(active - 1);
      } else if (ev.key === 'Escape') {
        results.hidden = true;
        input.setAttribute('aria-expanded', 'false');
      }
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        input.value = '';
        clearBtn.hidden = true;
        onInput();
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
  }

  function boot() {
    var root = document.getElementById('rateb-help-center');
    if (!root) return;
    bindHelpSearch(root);
  }

  if (!window.__RATEB_HELP_CENTER_WIRED__) {
    window.__RATEB_HELP_CENTER_WIRED__ = 1;
    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('rateb:nav:afterEnter', boot);
    document.addEventListener('rateb:soft-nav:afterEnter', boot);
  }
  if (document.readyState !== 'loading') boot();
})();
