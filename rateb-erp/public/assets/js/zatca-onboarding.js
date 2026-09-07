(function () {
  'use strict';

  function post(url, form, extra) {
    var body = new FormData();
    var csrf = form.querySelector('input[name="_csrf"]');
    body.append('_csrf', csrf ? csrf.value : '');
    Object.keys(extra || {}).forEach(function (key) {
      body.append(key, extra[key]);
    });
    return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (res) {
        return res.text().then(function (text) {
          try {
            return JSON.parse(text);
          } catch (e) {
            return { ok: false };
          }
        });
      })
      .catch(function () {
        return { ok: false };
      });
  }

  function toggleNotes(form, environment) {
    var sandbox = form.querySelector('[data-zatca-note="sandbox"]');
    var production = form.querySelector('[data-zatca-note="production"]');
    var isProduction = environment === 'production';
    if (sandbox) sandbox.classList.toggle('d-none', isProduction);
    if (production) production.classList.toggle('d-none', !isProduction);
  }

  function bind() {
    var form = document.getElementById('zatcaOnboardingForm');
    if (!form || form.getAttribute('data-zatca-bound') === '1') return;
    form.setAttribute('data-zatca-bound', '1');

    var select = form.querySelector('[data-zatca-environment]');
    if (select) {
      select.addEventListener('change', function () {
        toggleNotes(form, select.value);
        post(form.getAttribute('data-env-url') || '', form, { environment: select.value });
      });
    }

    var generate = form.querySelector('[data-zatca-generate-serial]');
    var serial = form.querySelector('#zatcaEgsSerial');
    if (generate && serial) {
      generate.addEventListener('click', function () {
        generate.disabled = true;
        post(form.getAttribute('data-serial-url') || '', form, {}).then(function (data) {
          generate.disabled = false;
          if (data && data.ok && data.egs_serial) {
            serial.value = data.egs_serial;
          }
        });
      });
    }

    form.addEventListener('submit', function (e) {
      var otp = form.querySelector('#zatcaOtp');
      if (otp && otp.value.replace(/\D+/g, '').length < 6) {
        e.preventDefault();
        otp.focus();
        otp.classList.add('is-invalid');
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
  document.addEventListener('rateb:nav:afterEnter', bind);
})();
