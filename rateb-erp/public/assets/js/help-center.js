(function () {
  'use strict';

  if (window.__RATEB_HELP_CENTER_LIVE__) {
    return;
  }
  window.__RATEB_HELP_CENTER_LIVE__ = 1;

  var state = {
    seq: 0,
    timer: 0,
    abort: null,
    hits: [],
    active: -1,
    index: null
  };

  function $(id) {
    return document.getElementById(id);
  }

  function normalize(s) {
    var v = String(s || '').toLowerCase();
    try {
      v = v.replace(/[^\p{L}\p{N}\s\-]/gu, ' ');
    } catch (eNorm) {
      v = v.replace(/[^\w\u0600-\u06FF\s\-]/g, ' ');
    }
    return v.replace(/\s+/g, ' ').trim();
  }

  function loadIndex() {
    if (state.index) {
      return state.index;
    }
    var node = $('hc-search-index');
    if (!node) {
      return [];
    }
    try {
      state.index = JSON.parse(node.textContent || '[]') || [];
    } catch (e) {
      state.index = [];
    }
    return state.index;
  }

  function homeUrl() {
    var root = $('rateb-help-center');
    return (root && root.getAttribute('data-hc-home')) || '/admin/help';
  }

  function searchUrl() {
    var root = $('rateb-help-center');
    return (root && root.getAttribute('data-hc-search-url')) || '';
  }

  function itemUrl(item) {
    if (item && item.help_url) {
      return String(item.help_url);
    }
    var home = homeUrl().replace(/\/?$/, '/');
    if (item.type === 'module') {
      return home + 'module/' + encodeURIComponent(item.slug);
    }
    return home + 'article/' + encodeURIComponent(item.slug);
  }

  function searchLocal(q) {
    var n = normalize(q);
    if (!n) return [];
    var tokens = n.split(' ').filter(Boolean);
    var index = loadIndex();
    var scored = [];
    for (var i = 0; i < index.length; i++) {
      var item = index[i];
      var title = normalize(item.title);
      var hay = normalize(
        [item.title, item.summary, item.module_title, item.module]
          .concat(item.keywords || [])
          .join(' ')
      );
      var score = 0;
      for (var t = 0; t < tokens.length; t++) {
        var tok = tokens[t];
        if (!tok) continue;
        if (title.indexOf(tok) !== -1) score += 12;
        if (hay.indexOf(tok) !== -1) score += 6;
      }
      if (item.type === 'article') score += 1;
      if (score > 0) scored.push({ item: item, score: score });
    }
    scored.sort(function (a, b) { return b.score - a.score; });
    return scored.slice(0, 12).map(function (row) { return row.item; });
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
    var flags = [];
    for (var c = 0; c < cards.length; c++) {
      var hay = normalize(cards[c].getAttribute('data-hc-hay') || cards[c].textContent || '');
      flags[c] = hay.indexOf(n) !== -1;
      if (flags[c]) any = true;
    }
    for (var d = 0; d < cards.length; d++) {
      cards[d].hidden = any ? !flags[d] : false;
      cards[d].classList.toggle('is-hc-muted', !flags[d]);
    }
  }

  function renderHits(hits) {
    var box = $('hc-search-results');
    var empty = $('hc-search-empty');
    var input = $('hc-search-input');
    if (!box) return;
    state.hits = hits;
    state.active = hits.length ? 0 : -1;
    box.innerHTML = '';
    if (!hits.length) {
      box.hidden = true;
      if (input) input.setAttribute('aria-expanded', 'false');
      if (empty) empty.hidden = false;
      return;
    }
    if (empty) empty.hidden = true;
    box.hidden = false;
    if (input) input.setAttribute('aria-expanded', 'true');
    for (var i = 0; i < hits.length; i++) {
      var item = hits[i];
      var a = document.createElement('a');
      a.className = 'hc-search__hit' + (i === 0 ? ' is-active' : '');
      a.setAttribute('role', 'option');
      a.setAttribute('data-hc-nav', '1');
      a.href = itemUrl(item);
      a.innerHTML =
        '<span class="hc-search__hit-icon"><i class="fas ' +
        (item.icon || 'fa-circle-question') +
        '" aria-hidden="true"></i></span>' +
        '<span><span class="hc-search__hit-title"></span><span class="hc-search__hit-meta"></span></span>';
      a.querySelector('.hc-search__hit-title').textContent = item.title || '';
      a.querySelector('.hc-search__hit-meta').textContent =
        (item.type === 'module' ? '' : (item.module_title || '')) +
        (item.minutes ? ' · ' + item.minutes + 'm' : '');
      box.appendChild(a);
    }
  }

  function setActive(next) {
    var nodes = document.querySelectorAll('#hc-search-results .hc-search__hit');
    if (!nodes.length) return;
    if (state.active >= 0 && nodes[state.active]) nodes[state.active].classList.remove('is-active');
    state.active = (next + nodes.length) % nodes.length;
    nodes[state.active].classList.add('is-active');
    nodes[state.active].scrollIntoView({ block: 'nearest' });
  }

  function fetchRemote(q, token) {
    var url = searchUrl();
    if (!url) return;
    if (state.abort && typeof state.abort.abort === 'function') {
      try { state.abort.abort(); } catch (eAb) { /* ignore */ }
    }
    state.abort = typeof AbortController === 'function' ? new AbortController() : null;
    var full = url + (url.indexOf('?') === -1 ? '?' : '&') + 'q=' + encodeURIComponent(q) + '&limit=12';
    var opts = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (state.abort) opts.signal = state.abort.signal;
    fetch(full, opts)
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) {
        if (token !== state.seq) return;
        var remote = data && Array.isArray(data.results) ? data.results : [];
        if (remote.length) {
          filterCards(q);
          renderHits(remote);
        } else if (!searchLocal(q).length) {
          renderHits([]);
        }
      })
      .catch(function () { /* keep local */ });
  }

  function runSearch(input) {
    if (!input || !$('rateb-help-center')) return;
    var q = input.value || '';
    var clearBtn = $('hc-search-clear');
    if (clearBtn) clearBtn.hidden = q.trim() === '';
    if (!q.trim()) {
      state.seq += 1;
      if (state.abort && typeof state.abort.abort === 'function') {
        try { state.abort.abort(); } catch (eClr) { /* ignore */ }
      }
      var box = $('hc-search-results');
      var empty = $('hc-search-empty');
      if (box) {
        box.hidden = true;
        box.innerHTML = '';
      }
      if (empty) empty.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      filterCards('');
      return;
    }
    var localHits = searchLocal(q);
    filterCards(q);
    if (localHits.length) {
      renderHits(localHits);
    }
    state.seq += 1;
    var token = state.seq;
    if (state.timer) clearTimeout(state.timer);
    state.timer = setTimeout(function () {
      fetchRemote(q, token);
    }, 80);
  }

  function goActive() {
    if (state.active >= 0 && state.hits[state.active]) {
      window.location.href = itemUrl(state.hits[state.active]);
      return true;
    }
    var first = document.querySelector('#hc-search-results .hc-search__hit');
    if (first && first.href) {
      window.location.href = first.href;
      return true;
    }
    return false;
  }

  document.addEventListener('input', function (ev) {
    if (ev.target && ev.target.id === 'hc-search-input') {
      state.index = null;
      runSearch(ev.target);
    }
  }, true);

  document.addEventListener('keyup', function (ev) {
    if (!ev.target || ev.target.id !== 'hc-search-input') return;
    if (ev.key === 'Escape' || ev.key === 'Enter' || ev.key === 'ArrowDown' || ev.key === 'ArrowUp') return;
    runSearch(ev.target);
  }, true);

  document.addEventListener('keydown', function (ev) {
    if (!ev.target || ev.target.id !== 'hc-search-input') return;
    if (ev.key === 'Enter') {
      ev.preventDefault();
      ev.stopPropagation();
      runSearch(ev.target);
      goActive();
      return;
    }
    if (ev.key === 'ArrowDown') {
      ev.preventDefault();
      setActive(state.active + 1);
    } else if (ev.key === 'ArrowUp') {
      ev.preventDefault();
      setActive(state.active - 1);
    } else if (ev.key === 'Escape') {
      var box = $('hc-search-results');
      if (box) box.hidden = true;
      ev.target.setAttribute('aria-expanded', 'false');
    }
  }, true);

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    if (!form) return;
    if (form.id === 'hc-search-form' || (form.querySelector && form.querySelector('#hc-search-input'))) {
      ev.preventDefault();
      ev.stopPropagation();
      var input = $('hc-search-input');
      if (input) runSearch(input);
      goActive();
    }
  }, true);

  document.addEventListener('click', function (ev) {
    var clearBtn = ev.target && ev.target.closest ? ev.target.closest('#hc-search-clear') : null;
    if (clearBtn) {
      ev.preventDefault();
      var input = $('hc-search-input');
      if (input) {
        input.value = '';
        runSearch(input);
        input.focus();
      }
      return;
    }
    var box = $('hc-search-results');
    var root = $('rateb-help-center');
    if (box && root && ev.target && !root.contains(ev.target)) {
      box.hidden = true;
    }
  }, true);

  function bootFromDom() {
    state.index = null;
    var input = $('hc-search-input');
    if (!input) return;
    if (input.value && String(input.value).trim() !== '') {
      runSearch(input);
    }
  }

  document.addEventListener('DOMContentLoaded', bootFromDom);
  document.addEventListener('rateb:nav:afterEnter', bootFromDom);
  document.addEventListener('rateb:soft-nav:afterEnter', bootFromDom);
  if (document.readyState !== 'loading') bootFromDom();
})();
