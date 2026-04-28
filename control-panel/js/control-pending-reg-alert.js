/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control-pending-reg-alert.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control-pending-reg-alert.js`.
 */
/**
 * Control Panel: Pending registration popup alert
 * Shows modal when there are pending requests; repeats every 10 min, max 3 times.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'pendingRegPopupCount';
  var TEN_MIN = 10 * 60 * 1000;
  var MAX_SHOWS = 3;
  var POLL_INTERVAL = 60 * 1000;
  var VISIBLE_CLASS = 'pending-reg-alert-visible';

  var nextShowTimer = null;
  var overlay = document.getElementById('pendingRegAlertOverlay');
  var msgNum = document.getElementById('pendingRegAlertNum');
  var okBtn = document.getElementById('pendingRegAlertOk');

  if (!overlay || !okBtn) return;

  function getApiBase() {
    var path = window.location.pathname || '';
    var base = path.replace(/\/pages\/[^?]*$/, '') || '';
    return window.location.origin + base + '/api/control';
  }

  function getCount() {
    var apiBase = getApiBase();
    var url = apiBase + '/registration-requests.php?status=pending&limit=1&control=1';
    return fetch(url, { method: 'GET', credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res && res.success && res.pagination) return (res.pagination.total || 0) | 0;
        return 0;
      })
      .catch(function () { return 0; });
  }

  function getStoredCount() {
    try { return parseInt(sessionStorage.getItem(STORAGE_KEY) || '0', 10) || 0; } catch (e) { return 0; }
  }

  function setStoredCount(n) {
    try { sessionStorage.setItem(STORAGE_KEY, String(n)); } catch (e) {}
  }

  function showModal(pendingCount) {
    if (msgNum) msgNum.textContent = pendingCount;
    overlay.classList.add(VISIBLE_CLASS);
  }

  function hideModal() {
    overlay.classList.remove(VISIBLE_CLASS);
  }

  function scheduleNext() {
    if (nextShowTimer) clearTimeout(nextShowTimer);
    var count = getStoredCount();
    if (count >= MAX_SHOWS) return;
    nextShowTimer = setTimeout(function () {
      nextShowTimer = null;
      getCount().then(function (total) {
        if (total > 0) showModal(total);
      });
    }, TEN_MIN);
  }

  okBtn.addEventListener('click', function () {
    hideModal();
    var count = getStoredCount() + 1;
    setStoredCount(count);
    if (count < MAX_SHOWS) scheduleNext();
  });

  function check() {
    getCount().then(function (total) {
      if (total === 0) {
        setStoredCount(0);
        if (nextShowTimer) { clearTimeout(nextShowTimer); nextShowTimer = null; }
        return;
      }
      var count = getStoredCount();
      if (count >= MAX_SHOWS) return;
      if (overlay.classList.contains(VISIBLE_CLASS)) return;
      if (count === 0) showModal(total);
    });
  }

  setInterval(check, POLL_INTERVAL);
  setTimeout(check, 2000);
})();
