(function () {
  'use strict';

  if (window.__RATEB_HELP_ASSISTANT__) return;
  window.__RATEB_HELP_ASSISTANT__ = 1;

  var cfgNode = document.getElementById('rateb-help-assistant-cfg');
  if (!cfgNode) return;
  var cfg;
  try {
    cfg = JSON.parse(cfgNode.textContent || '{}');
  } catch (e0) {
    return;
  }
  if (!cfg || !cfg.bootstrapUrl || !cfg.askUrl) return;

  // Avoid duplicate if already mounted
  if (document.getElementById('rateb-help-assistant-root')) return;

  // Do not load on POS shells
  if (document.body && (
    document.body.classList.contains('rateb-pos-shell')
    || document.body.getAttribute('data-rateb-hide-help-assistant') === '1'
  )) {
    return;
  }

  var STORAGE_KEY = 'rateb_help_assistant_v1';
  var locale = cfg.locale === 'en' ? 'en' : 'ar';
  var route = cfg.erpRoute || '';
  var csrf = cfg.csrf || ((document.querySelector('meta[name="rateb-csrf"]') || {}).content || '');
  var boot = null;
  var busy = false;

  function injectCss() {
    if (document.querySelector('link[data-rateb-ha-css="1"]')) return;
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = cfg.cssUrl;
    link.setAttribute('data-rateb-ha-css', '1');
    document.head.appendChild(link);
  }

  function t(key) {
    var map = (boot && boot.i18n) || cfg.i18n || {};
    return map[key] || key;
  }

  function nowLabel() {
    try {
      return new Date().toLocaleTimeString(locale === 'en' ? 'en-GB' : 'ar-SA', { hour: '2-digit', minute: '2-digit' });
    } catch (e1) {
      return '';
    }
  }

  function loadState() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return { messages: [], locale: locale };
      var data = JSON.parse(raw);
      if (!data || typeof data !== 'object') return { messages: [], locale: locale };
      return data;
    } catch (e2) {
      return { messages: [], locale: locale };
    }
  }

  function saveState(state) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({
        messages: (state.messages || []).slice(-40),
        locale: state.locale || locale
      }));
    } catch (e3) { /* ignore quota */ }
  }

  var state = loadState();
  if (state.locale === 'en' || state.locale === 'ar') {
    locale = state.locale;
  }

  injectCss();

  var root = document.createElement('div');
  root.className = 'rateb-ha-root';
  root.id = 'rateb-help-assistant-root';
  root.setAttribute('dir', locale === 'en' ? 'ltr' : 'rtl');

  root.innerHTML =
    '<button type="button" class="rateb-ha-fab" id="rateb-ha-fab" aria-haspopup="dialog" aria-expanded="false" aria-controls="rateb-ha-panel">' +
      '<i class="fas fa-robot" aria-hidden="true"></i>' +
    '</button>' +
    '<section class="rateb-ha-panel" id="rateb-ha-panel" role="dialog" aria-modal="false" hidden>' +
      '<header class="rateb-ha-header">' +
        '<span class="rateb-ha-header__icon" aria-hidden="true"><i class="fas fa-wand-magic-sparkles"></i></span>' +
        '<div class="rateb-ha-header__text">' +
          '<h2 class="rateb-ha-header__title" id="rateb-ha-title"></h2>' +
          '<p class="rateb-ha-header__sub" id="rateb-ha-sub"></p>' +
        '</div>' +
        '<div class="rateb-ha-header__actions">' +
          '<button type="button" class="rateb-ha-icon-btn" id="rateb-ha-lang-ar" title="العربية">AR</button>' +
          '<button type="button" class="rateb-ha-icon-btn" id="rateb-ha-lang-en" title="English">EN</button>' +
          '<button type="button" class="rateb-ha-icon-btn" id="rateb-ha-clear" title="Clear"><i class="fas fa-eraser" aria-hidden="true"></i></button>' +
          '<button type="button" class="rateb-ha-icon-btn" id="rateb-ha-min" title="Minimize"><i class="fas fa-minus" aria-hidden="true"></i></button>' +
          '<button type="button" class="rateb-ha-icon-btn" id="rateb-ha-close" title="Close"><i class="fas fa-xmark" aria-hidden="true"></i></button>' +
        '</div>' +
      '</header>' +
      '<div class="rateb-ha-body" id="rateb-ha-body" aria-live="polite"></div>' +
      '<form class="rateb-ha-footer" id="rateb-ha-form">' +
        '<input class="rateb-ha-input" id="rateb-ha-input" type="text" maxlength="400" autocomplete="off">' +
        '<button class="rateb-ha-send" type="submit" id="rateb-ha-send" aria-label="Send"><i class="fas fa-paper-plane" aria-hidden="true"></i></button>' +
      '</form>' +
    '</section>';

  document.body.appendChild(root);

  var fab = document.getElementById('rateb-ha-fab');
  var panel = document.getElementById('rateb-ha-panel');
  var body = document.getElementById('rateb-ha-body');
  var input = document.getElementById('rateb-ha-input');
  var form = document.getElementById('rateb-ha-form');
  var sendBtn = document.getElementById('rateb-ha-send');

  function setOpen(open) {
    panel.hidden = !open;
    fab.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      input.focus();
      ensureBoot().then(function () { renderAll(); });
    }
  }

  function applyChrome() {
    root.setAttribute('dir', locale === 'en' ? 'ltr' : 'rtl');
    document.getElementById('rateb-ha-title').textContent =
      (boot && boot.title) || (locale === 'en' ? 'RATIB AI Assistant' : 'مساعد رتب الذكي');
    document.getElementById('rateb-ha-sub').textContent =
      (boot && boot.subtitle) || (locale === 'en' ? 'How can I help you?' : 'كيف يمكنني مساعدتك؟');
    input.placeholder = locale === 'en' ? 'Ask about RATIB ERP…' : 'اسأل عن نظام رتب…';
    fab.title = (boot && boot.title) || (locale === 'en' ? 'RATIB AI Assistant' : 'مساعد رتب الذكي');
    fab.setAttribute('aria-label', fab.title);
    document.getElementById('rateb-ha-lang-ar').classList.toggle('is-active', locale === 'ar');
    document.getElementById('rateb-ha-lang-en').classList.toggle('is-active', locale === 'en');
  }

  function appendMsg(role, text, extraHtml) {
    var el = document.createElement('div');
    el.className = 'rateb-ha-msg rateb-ha-msg--' + (role === 'user' ? 'user' : 'bot');
    el.textContent = text || '';
    if (extraHtml) {
      var wrap = document.createElement('div');
      wrap.innerHTML = extraHtml;
      el.appendChild(wrap);
    }
    var meta = document.createElement('span');
    meta.className = 'rateb-ha-msg__meta';
    meta.textContent = nowLabel();
    el.appendChild(meta);
    body.appendChild(el);
    body.scrollTop = body.scrollHeight;
    return el;
  }

  function escapeHtml(s) {
    return String(s || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function renderQuick(quick) {
    var box = document.createElement('div');
    box.className = 'rateb-ha-quick';
    (quick || []).forEach(function (q) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'rateb-ha-chip';
      btn.textContent = q;
      btn.addEventListener('click', function () { ask(q, true); });
      box.appendChild(btn);
    });
    body.appendChild(box);
  }

  function renderAnswerExtras(data) {
    var html = '';
    if (data.article && data.article.url) {
      html += '<div class="rateb-ha-card">' +
        '<p class="rateb-ha-card__title">' + escapeHtml(data.article.title || '') + '</p>' +
        '<div class="rateb-ha-card__actions">' +
          '<a class="rateb-ha-btn" href="' + escapeHtml(data.article.url) + '" data-ha-open="' + escapeHtml(data.article.slug || '') + '">' +
            escapeHtml(data.open_label || (locale === 'en' ? 'Open full guide' : 'فتح الشرح الكامل')) +
          '</a>';
      if (data.support_url) {
        html += '<a class="rateb-ha-btn rateb-ha-btn--ghost" href="' + escapeHtml(data.support_url) + '">' +
          escapeHtml(locale === 'en' ? 'Contact Support' : 'تواصل مع الدعم') + '</a>';
      }
      html += '</div></div>';
    } else if (data.support_url || data.help_home_url) {
      html += '<div class="rateb-ha-card__actions" style="margin-top:.5rem">';
      if (data.help_home_url) {
        html += '<a class="rateb-ha-btn" href="' + escapeHtml(data.help_home_url) + '">' +
          escapeHtml(locale === 'en' ? 'Open Help Center' : 'فتح مركز المساعدة') + '</a>';
      }
      if (data.support_url) {
        html += '<a class="rateb-ha-btn rateb-ha-btn--ghost" href="' + escapeHtml(data.support_url) + '">' +
          escapeHtml(locale === 'en' ? 'Contact Support' : 'تواصل مع الدعم') + '</a>';
      }
      html += '</div>';
    }
    if (data.options && data.options.length) {
      html += '<div class="rateb-ha-quick" style="margin-top:.5rem">';
      data.options.forEach(function (opt) {
        html += '<button type="button" class="rateb-ha-chip" data-ha-opt="' + escapeHtml(opt.label || '') + '">' +
          escapeHtml(opt.label || '') + '</button>';
      });
      html += '</div>';
    }
    if (data.related && data.related.length) {
      html += '<ul class="rateb-ha-related">';
      data.related.forEach(function (r) {
        html += '<li><a href="' + escapeHtml(r.url || '#') + '">' + escapeHtml(r.title || '') + '</a></li>';
      });
      html += '</ul>';
    }
    return html;
  }

  function renderAll() {
    body.innerHTML = '';
    if (!state.messages || !state.messages.length) {
      appendMsg('bot', (boot && boot.welcome) || '');
      renderQuick((boot && boot.quick) || []);
      if (boot && boot.context && boot.context.module && boot.context.suggestions && boot.context.suggestions.length) {
        var ctxLabel = locale === 'en'
          ? ('Suggested for ' + (boot.context.module.title || '') + ':')
          : ('مقترح لـ ' + (boot.context.module.title || '') + ':');
        var tip = document.createElement('div');
        tip.className = 'rateb-ha-msg rateb-ha-msg--bot';
        tip.textContent = ctxLabel;
        var qbox = document.createElement('div');
        qbox.className = 'rateb-ha-quick';
        boot.context.suggestions.slice(0, 4).forEach(function (s) {
          var b = document.createElement('button');
          b.type = 'button';
          b.className = 'rateb-ha-chip';
          b.textContent = s.title || '';
          b.addEventListener('click', function () { ask(s.title || '', true); });
          qbox.appendChild(b);
        });
        tip.appendChild(qbox);
        body.appendChild(tip);
      }
      return;
    }
    state.messages.forEach(function (m) {
      if (m.role === 'user') {
        appendMsg('user', m.text || '');
      } else {
        appendMsg('bot', m.text || '', m.html || '');
      }
    });
  }

  function ensureBoot() {
    if (boot && boot.locale === locale) {
      return Promise.resolve(boot);
    }
    var url = cfg.bootstrapUrl + (cfg.bootstrapUrl.indexOf('?') >= 0 ? '&' : '?') +
      'locale=' + encodeURIComponent(locale) + '&route=' + encodeURIComponent(route);
    return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        boot = data || {};
        applyChrome();
        return boot;
      })
      .catch(function () {
        boot = { locale: locale, welcome: '', quick: [], title: '', subtitle: '' };
        applyChrome();
        return boot;
      });
  }

  function postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf
      },
      body: JSON.stringify(Object.assign({ _csrf: csrf }, payload))
    }).then(function (r) { return r.json(); });
  }

  // PHP Controllers read $_POST — send as form-urlencoded for CSRF compatibility
  function postForm(url, payload) {
    var body = new URLSearchParams();
    body.set('_csrf', csrf);
    Object.keys(payload || {}).forEach(function (k) {
      body.set(k, payload[k] == null ? '' : String(payload[k]));
    });
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': csrf
      },
      body: body.toString()
    }).then(function (r) { return r.json(); });
  }

  function ask(question, fromQuick) {
    question = String(question || '').trim();
    if (!question || busy) return;
    busy = true;
    sendBtn.disabled = true;
    state.messages = state.messages || [];
    state.messages.push({ role: 'user', text: question });
    appendMsg('user', question);
    input.value = '';

    var typing = document.createElement('div');
    typing.className = 'rateb-ha-typing';
    typing.id = 'rateb-ha-typing';
    typing.textContent = (boot && boot.typing) || (locale === 'en' ? 'Typing...' : 'يكتب الآن...');
    body.appendChild(typing);
    body.scrollTop = body.scrollHeight;

    postForm(cfg.askUrl, {
      question: question,
      locale: locale,
      route: route,
      source: fromQuick ? 'quick' : 'chat'
    }).then(function (data) {
      var tip = document.getElementById('rateb-ha-typing');
      if (tip) tip.remove();
      var answer = (data && data.answer) || (locale === 'en' ? 'No answer available.' : 'لا توجد إجابة.');
      var html = renderAnswerExtras(data || {});
      state.messages.push({ role: 'bot', text: answer, html: html });
      saveState({ messages: state.messages, locale: locale });
      appendMsg('bot', answer, html);
    }).catch(function () {
      var tip = document.getElementById('rateb-ha-typing');
      if (tip) tip.remove();
      var msg = locale === 'en' ? 'Something went wrong. Please try again.' : 'حدث خطأ. حاول مرة أخرى.';
      state.messages.push({ role: 'bot', text: msg });
      appendMsg('bot', msg);
      saveState({ messages: state.messages, locale: locale });
    }).finally(function () {
      busy = false;
      sendBtn.disabled = false;
      input.focus();
    });
  }

  fab.addEventListener('click', function () { setOpen(panel.hidden); });
  document.getElementById('rateb-ha-close').addEventListener('click', function () { setOpen(false); });
  document.getElementById('rateb-ha-min').addEventListener('click', function () { setOpen(false); });
  document.getElementById('rateb-ha-clear').addEventListener('click', function () {
    state.messages = [];
    saveState({ messages: [], locale: locale });
    if (cfg.trackUrl) {
      postForm(cfg.trackUrl, { event_type: 'clear', locale: locale, route: route }).catch(function () {});
    }
    renderAll();
  });
  document.getElementById('rateb-ha-lang-ar').addEventListener('click', function () {
    locale = 'ar';
    state.locale = 'ar';
    saveState(state);
    boot = null;
    ensureBoot().then(function () { renderAll(); });
  });
  document.getElementById('rateb-ha-lang-en').addEventListener('click', function () {
    locale = 'en';
    state.locale = 'en';
    saveState(state);
    boot = null;
    ensureBoot().then(function () { renderAll(); });
  });

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    ask(input.value, false);
  });

  body.addEventListener('click', function (ev) {
    var opt = ev.target && ev.target.closest ? ev.target.closest('[data-ha-opt]') : null;
    if (opt) {
      ask(opt.getAttribute('data-ha-opt') || '', true);
      return;
    }
    var open = ev.target && ev.target.closest ? ev.target.closest('[data-ha-open]') : null;
    if (open && cfg.trackUrl) {
      postForm(cfg.trackUrl, {
        event_type: 'open_article',
        locale: locale,
        route: route,
        article_slug: open.getAttribute('data-ha-open') || ''
      }).catch(function () {});
    }
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && !panel.hidden) setOpen(false);
  });

  applyChrome();
})();
