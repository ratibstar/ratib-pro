/**
 * EN: Implements public web entry/assets behavior in `public/assets/js/payment.js`.
 * AR: ينفذ سلوك المدخل العام للويب وملفات الواجهة في `public/assets/js/payment.js`.
 */
(function () {
  'use strict';

  function apiBaseUrl() {
    if (
      typeof window.RATIB_BASE_URL === 'string' &&
      window.RATIB_BASE_URL.trim() !== ''
    ) {
      return String(window.RATIB_BASE_URL).replace(/\/+$/, '');
    }
    var pathRoot =
      (window.location.pathname || '').replace(/\/pages\/.*$/, '') || '';
    return (window.location.origin + pathRoot).replace(/\/+$/, '');
  }

  function buildOrderPayload(form) {
    var fd = new FormData(form);
    var data = {};
    fd.forEach(function (v, k) {
      if (
        k !== 'website_url' &&
        k !== 'country' &&
        k !== 'country_other' &&
        k !== 'payment_method'
      ) {
        data[k] = v;
      }
    });
    data.payment_method = 'register';

    var countrySelect = document.getElementById('countrySelect');
    var countryVal = countrySelect ? countrySelect.value : '';
    if (countryVal === 'Other countries sending workers') {
      var otherEl = document.getElementById('countryOther');
      data.country_name =
        (otherEl && otherEl.value.trim()) || 'Other';
      data.country_id = 0;
    } else if (countryVal) {
      data.country_name = countryVal;
      data.country_id = 0;
    }
    return data;
  }

  var form = document.getElementById('regForm');
  if (!form) {
    return;
  }

  var submitBtn = form.querySelector('button[type="submit"]');
  if (!submitBtn) {
    return;
  }

  var defaultBtnHtml = submitBtn.innerHTML;
  var loadingHtml =
    '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
  var REQUEST_TIMEOUT_MS = 25000;
  // Browser lock disabled: rely on backend safeguards instead.
  var CHECKOUT_LOCK_TTL_MS = 1;
  var CHECKOUT_LOCK_KEY = 'ratib_checkout_pending_v1';

  function normalizeLockIdentity(payload) {
    var p = payload && typeof payload === 'object' ? payload : {};
    return {
      email: String(p.email || p.contact_email || '').trim().toLowerCase(),
      plan: String(p.plan || '').trim().toLowerCase(),
      years: Number(p.years || 1) || 1
    };
  }

  function readCheckoutLock() {
    try {
      var raw = window.localStorage ? window.localStorage.getItem(CHECKOUT_LOCK_KEY) : '';
      if (!raw) return null;
      var data = JSON.parse(raw);
      if (!data || typeof data !== 'object') return null;
      var createdAt = Number(data.created_at || 0);
      if (!createdAt || !isFinite(createdAt)) return null;
      if (Date.now() - createdAt > CHECKOUT_LOCK_TTL_MS) {
        clearCheckoutLock();
        return null;
      }
      return data;
    } catch (e) {
      return null;
    }
  }

  function setCheckoutLock(payload) {
    try {
      if (!window.localStorage) return;
      var p = payload && typeof payload === 'object' ? payload : {};
      p.created_at = Date.now();
      window.localStorage.setItem(CHECKOUT_LOCK_KEY, JSON.stringify(p));
    } catch (e) {
      /* ignore */
    }
  }

  function clearCheckoutLock() {
    try {
      if (window.localStorage) window.localStorage.removeItem(CHECKOUT_LOCK_KEY);
    } catch (e) {
      /* ignore */
    }
  }

  function clearLockAfterFailedReturn() {
    try {
      var params = new URLSearchParams(window.location.search || '');
      var paymentStatus = String(params.get('payment') || '').trim().toLowerCase();
      if (paymentStatus === 'failed' || paymentStatus === 'cancelled' || paymentStatus === 'canceled') {
        clearCheckoutLock();
      }
    } catch (e) {
      /* ignore URL parsing failures */
    }
  }

  clearLockAfterFailedReturn();

  function fetchWithTimeout(url, options, timeoutMs) {
    if (typeof AbortController !== 'function') {
      return fetch(url, options);
    }
    var controller = new AbortController();
    var timer = setTimeout(function () {
      controller.abort();
    }, timeoutMs);
    var finalOptions = Object.assign({}, options, { signal: controller.signal });
    return fetch(url, finalOptions).finally(function () {
      clearTimeout(timer);
    });
  }

  function logCheckoutDebug(response, parsed, rawText, requestUrl) {
    if (typeof console === 'undefined' || !console) {
      return;
    }
    var status = response && typeof response.status === 'number' ? response.status : 0;
    var cfg = parsed && parsed.payment_config ? parsed.payment_config : null;
    var hint = cfg && cfg.credential_hint ? cfg.credential_hint : null;
    var title = '[checkout-debug] create-order failed ' + status;
    if (typeof console.groupCollapsed === 'function') {
      console.groupCollapsed(title);
    } else if (typeof console.error === 'function') {
      console.error(title);
    }
    if (typeof console.log === 'function') {
      console.log('request_url:', requestUrl);
      console.log('http_status:', status);
      if (parsed && typeof parsed.message === 'string' && parsed.message) {
        console.log('server_message:', parsed.message);
      }
      if (parsed && typeof parsed.error === 'string' && parsed.error) {
        console.log('server_error:', parsed.error);
      }
      if (parsed && typeof parsed.hint === 'string' && parsed.hint) {
        console.log('server_hint:', parsed.hint);
      }
      if (parsed && typeof parsed.detail === 'string' && parsed.detail) {
        console.log('server_detail:', parsed.detail);
      }
      if (parsed && typeof parsed.backend_release === 'string' && parsed.backend_release) {
        console.log('backend_release:', parsed.backend_release);
      }
      if (cfg) {
        console.log('payment_config:', cfg);
      }
      if (hint) {
        console.log('credential_hint:', hint);
      }
      if (parsed && parsed.identity_error) {
        console.log('identity_error:', parsed.identity_error);
      }
      if (parsed) {
        console.log('response_json:', parsed);
      } else if (rawText) {
        console.log('response_text:', rawText);
      }
    }
    if (typeof console.groupEnd === 'function') {
      console.groupEnd();
    }
    if (typeof console.error === 'function') {
      var bits = ['[create-order]', 'HTTP', String(status)];
      if (parsed && parsed.backend_release) {
        bits.push('release=' + parsed.backend_release);
      }
      if (parsed && typeof parsed.message === 'string' && parsed.message) {
        bits.push('msg=' + parsed.message);
      }
      if (parsed && typeof parsed.hint === 'string' && parsed.hint) {
        bits.push('hint=' + parsed.hint);
      }
      if (parsed && typeof parsed.detail === 'string' && parsed.detail) {
        bits.push('detail=' + parsed.detail);
      }
      if (parsed && parsed.phase) {
        bits.push('phase=' + String(parsed.phase));
      }
      if (parsed && parsed.local_order_id != null) {
        bits.push('local_order_id=' + String(parsed.local_order_id));
      }
      if (parsed && typeof parsed.error === 'string' && parsed.error) {
        bits.push('err=' + parsed.error);
      }
      if (!parsed && rawText) {
        bits.push('body_snip=' + String(rawText).slice(0, 240));
      }
      if (bits.length <= 3) {
        bits.push('(expand Network → create-order.php → Response; details were in collapsed group above)');
      }
      console.error(bits.join(' | '));
    }
  }

  function postCreateOrder(payload) {
    var requestUrl = apiBaseUrl() + '/api/create-order.php';
    return fetchWithTimeout(requestUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload || {}),
      credentials: 'same-origin',
    }, REQUEST_TIMEOUT_MS).then(function (response) {
      return response.text().then(function (text) {
        var parsed = null;
        if (text) {
          try {
            parsed = JSON.parse(text);
          } catch (e) {
            parsed = null;
          }
        }
        return {
          ok: response.ok,
          status: response.status,
          parsed: parsed,
          text: text,
          response: response,
        };
      });
    });
  }

  function buildCheckoutErrorNote(res) {
    if (!res) {
      return '';
    }
    var p = res.parsed;
    if (p && typeof p === 'object') {
      var bits = [];
      if (typeof p.backend_release === 'string' && p.backend_release) {
        bits.push('release=' + p.backend_release);
      }
      if (typeof p.message === 'string' && p.message) {
        bits.push(p.message);
      }
      if (typeof p.detail === 'string' && p.detail) {
        bits.push(p.detail);
      }
      if (typeof p.error === 'string' && p.error) {
        bits.push(p.error);
      }
      if (typeof p.hint === 'string' && p.hint) {
        bits.push(p.hint);
      }
      if (bits.length) {
        return bits.join(' — ');
      }
    }
    var t = res.text && String(res.text).trim();
    if (t) {
      if (t.charAt(0) === '{') {
        try {
          var j = JSON.parse(t);
          if (j && typeof j === 'object') {
            return buildCheckoutErrorNote({ parsed: j, text: t });
          }
        } catch (e2) {
          /* fall through */
        }
      }
      return t.slice(0, 500);
    }
    return '';
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();

    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    var payload = buildOrderPayload(form);
    var identity = normalizeLockIdentity(payload);
    var existingLock = readCheckoutLock();
    if (existingLock) {
      var lockedEmail = String(existingLock.email || '').trim().toLowerCase();
      var lockedPlan = String(existingLock.plan || '').trim().toLowerCase();
      var lockedYears = Number(existingLock.years || 1) || 1;
      var sameIdentity =
        lockedEmail !== '' &&
        lockedEmail === identity.email &&
        lockedPlan === identity.plan &&
        lockedYears === identity.years;
      if (!sameIdentity) {
        clearCheckoutLock();
      } else {
      window.alert(
        'A checkout is already pending from this browser. Complete that payment first, or wait and try again later.'
      );
      return;
      }
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = loadingHtml;

    setCheckoutLock({
      email: identity.email.slice(0, 255),
      plan: identity.plan,
      years: identity.years
    });
    var checkoutUrl = apiBaseUrl() + '/api/create-order.php';

    postCreateOrder(payload).then(
      function (res) {
        if (res.ok && res.parsed) {
          var payUrl =
            typeof res.parsed.payment_url === 'string'
              ? res.parsed.payment_url.trim()
              : '';
          if (payUrl !== '') {
            if (typeof console !== 'undefined' && console.info && res.parsed.amount_meta) {
              console.info('[checkout] amount_meta', res.parsed.amount_meta);
            }
            window.location.assign(payUrl);
            return Promise.resolve();
          }
        }
        clearCheckoutLock();
        var clientErr = !res.ok && res.status >= 400 && res.status < 500;
        if (clientErr) {
          var cm = res.parsed && res.parsed.message;
          window.alert(
            (typeof cm === 'string' && cm) || 'Please check your details and try again.'
          );
          return Promise.resolve();
        }
        logCheckoutDebug(res.response, res.parsed, res.text, checkoutUrl);
        var tech = buildCheckoutErrorNote(res);
        var head = res.ok
          ? 'Payment did not return a checkout link. Nothing was saved to Registration Requests.'
          : 'Checkout could not start. Nothing was saved to Registration Requests.';
        window.alert(head + (tech ? '\n\n— Server —\n' + tech : ''));
        return Promise.resolve();
      },
      function () {
        clearCheckoutLock();
        window.alert(
          'Could not reach the server. Nothing was saved. Check your connection and try again.'
        );
        return Promise.resolve();
      }
    ).catch(function (err) {
      clearCheckoutLock();
      var message =
        err && err.message
          ? err.message
          : 'Registration failed. Please try again.';
      if (typeof console !== 'undefined' && console.warn) {
        console.warn('[registration]', message);
      }
      window.alert(message);
    }).finally(function () {
      window.setTimeout(function () {
        submitBtn.disabled = false;
        submitBtn.innerHTML = defaultBtnHtml;
      }, 0);
    });
  });
})();
