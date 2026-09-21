/**
 * RATEB AI toolbar — last-line binder for send / mic / headset / language.
 * Loaded after rateb-ai-page.js; survives soft-nav via rateb:* events.
 * Also removes help-assistant FAB which sits on top of RTL left-side buttons.
 */
(function (root, doc) {
  'use strict';

  function status(msg) {
    var el = doc.getElementById('aiVoiceStatus');
    if (!el) return;
    el.textContent = msg || 'Ready';
    el.setAttribute('data-state', 'ready');
  }

  function killHelpFab() {
    try {
      if (doc.body) doc.body.setAttribute('data-rateb-hide-help-assistant', '1');
      var fabRoot = doc.getElementById('rateb-help-assistant-root');
      if (fabRoot && fabRoot.parentNode) fabRoot.parentNode.removeChild(fabRoot);
      var helpFab = doc.getElementById('rateb-help-fab');
      if (helpFab && helpFab.parentNode) helpFab.parentNode.removeChild(helpFab);
      var helpPanel = doc.getElementById('rateb-help-panel');
      if (helpPanel && helpPanel.parentNode) helpPanel.parentNode.removeChild(helpPanel);
    } catch (eKill) {}
  }

  function ensureApi() {
    if (!root.ratebAi) root.ratebAi = {};
    var api = root.ratebAi;
    if (typeof api.bindVoice === 'function') {
      try { api.bindVoice(); } catch (eB) {}
    }
    return api;
  }

  function bindToolbar() {
    killHelpFab();
    var rootEl = doc.getElementById('ratebAiRoot');
    if (!rootEl) return;
    var actions = doc.querySelector('.rateb-ai-input-actions');
    if (!actions) return;
    if (actions.getAttribute('data-toolbar-bound') === '10') return;
    actions.setAttribute('data-toolbar-bound', '10');

    var send = doc.getElementById('aiSendBtn');
    var mic = doc.getElementById('aiVoiceInputBtn');
    var mode = doc.getElementById('aiVoiceModeBtn');
    var stop = doc.getElementById('aiVoiceStopBtn');
    var lang = doc.getElementById('aiVoiceLanguage');

    function onSend(e) {
      if (e) { e.preventDefault(); e.stopPropagation(); }
      var api = ensureApi();
      status('إرسال…');
      if (typeof api.sendFromInput === 'function') {
        var ok = api.sendFromInput();
        if (ok === false) status('اكتب رسالة أو استخدم المايك أولاً');
      } else {
        status('ratebAi.sendFromInput غير جاهز — Ctrl+F5');
      }
      return false;
    }

    function onMic(e) {
      if (e) { e.preventDefault(); e.stopPropagation(); try { e.stopImmediatePropagation(); } catch (eSip) {} }
      var api = ensureApi();
      status('المايك…');
      if (typeof api.toggleVoiceInput === 'function') {
        api.toggleVoiceInput();
      } else if (typeof api.bindVoice === 'function') {
        api.bindVoice();
        if (api.toggleVoiceInput) api.toggleVoiceInput();
        else status('المايك غير مربوط — استخدم Chrome واسمح بالمايك');
      } else {
        status('المايك غير جاهز — Ctrl+F5 ثم اسمح بالمايك');
      }
      return false;
    }

    function onMode(e) {
      if (e) { e.preventDefault(); e.stopPropagation(); }
      var api = ensureApi();
      status('وضع الصوت…');
      if (typeof api.toggleVoiceMode === 'function') api.toggleVoiceMode();
      else if (typeof api.bindVoice === 'function') {
        api.bindVoice();
        if (api.toggleVoiceMode) api.toggleVoiceMode();
      }
      return false;
    }

    function onStop(e) {
      if (e) { e.preventDefault(); e.stopPropagation(); }
      var api = ensureApi();
      if (typeof api.stopVoice === 'function') api.stopVoice();
      status('Ready');
      return false;
    }

    if (send) {
      send.onclick = onSend;
      send.addEventListener('click', onSend, true);
    }
    if (mic) {
      mic.onclick = onMic;
      mic.addEventListener('click', onMic, true);
    }
    if (mode) {
      mode.onclick = onMode;
      mode.addEventListener('click', onMode, true);
    }
    if (stop) {
      stop.onclick = onStop;
      stop.addEventListener('click', onStop, true);
    }
    if (lang) {
      lang.addEventListener('change', function () {
        status('لغة الصوت: ' + (lang.value || 'AR'));
        var api = ensureApi();
        if (typeof api.bindVoice === 'function') api.bindVoice();
      });
    }

    // Raise above any leftover FAB / overlays on the physical left (RTL).
    actions.style.position = 'relative';
    actions.style.zIndex = '50';
    if (send) { send.style.pointerEvents = 'auto'; send.style.zIndex = '51'; }
    if (mic) { mic.style.pointerEvents = 'auto'; mic.style.zIndex = '51'; }
    if (mode) { mode.style.pointerEvents = 'auto'; mode.style.zIndex = '51'; }
  }

  function boot() {
    killHelpFab();
    bindToolbar();
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  doc.addEventListener('rateb:nav:afterEnter', function () {
    var a = doc.querySelector('.rateb-ai-input-actions');
    if (a) a.removeAttribute('data-toolbar-bound');
    boot();
  });
  doc.addEventListener('rateb:soft-nav:afterEnter', function () {
    var a = doc.querySelector('.rateb-ai-input-actions');
    if (a) a.removeAttribute('data-toolbar-bound');
    boot();
  });
})(window, document);
