/**
 * Marketing pricing — show agency registration form in place of plan cards.
 */
(function () {
  'use strict';

  function qs(name) {
    try {
      return new URLSearchParams(window.location.search).get(name) || '';
    } catch (e) {
      return '';
    }
  }

  function erpToCheckout(plan) {
    var map = {
      starter: 'pro',
      professional: 'gold',
      enterprise: 'platinum',
      pro: 'pro',
      gold: 'gold',
      platinum: 'platinum',
    };
    return map[String(plan || '').toLowerCase()] || 'gold';
  }

  function pricingPackagesEl() {
    return document.getElementById('ratebMktPricingPackages');
  }

  function registerPanelEl() {
    return document.getElementById('ratebMktAgencyRegister');
  }

  function showAgencyRegister(erpPlan, years) {
    var packages = pricingPackagesEl();
    var panel = registerPanelEl();
    if (!panel) {
      return false;
    }
    var checkoutPlan = erpToCheckout(erpPlan);
    var y = parseInt(years, 10);
    if (!isFinite(y) || y < 0) {
      y = 1;
    }
    var inputPlan = document.getElementById('inputPlan');
    var inputYears = document.getElementById('inputYears');
    var inputAmount = document.getElementById('inputPlanAmount');
    if (inputPlan) {
      inputPlan.value = checkoutPlan;
    }
    if (inputYears) {
      inputYears.value = String(y);
    }
    if (inputAmount) {
      var amt = 0;
      if (checkoutPlan === 'gold') {
        amt = y === 0 ? 4.5 : 5;
      } else if (checkoutPlan === 'platinum') {
        amt = y === 0 ? 67 : 800;
      }
      inputAmount.value = amt > 0 ? String(amt) : '';
    }
    if (packages) {
      packages.classList.add('d-none');
      packages.setAttribute('hidden', 'hidden');
    }
    panel.classList.remove('d-none');
    panel.removeAttribute('hidden');
    var pricing = document.getElementById('pricing');
    if (pricing) {
      pricing.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
    return true;
  }

  function hideAgencyRegister() {
    var packages = pricingPackagesEl();
    var panel = registerPanelEl();
    if (packages) {
      packages.classList.remove('d-none');
      packages.removeAttribute('hidden');
    }
    if (panel) {
      panel.classList.add('d-none');
      panel.setAttribute('hidden', 'hidden');
    }
    try {
      var u = new URL(window.location.href);
      u.searchParams.delete('register');
      window.history.replaceState({}, '', u.pathname + u.search + u.hash);
    } catch (e) {
      /* ignore */
    }
  }

  function bindCountryOther() {
    var sel = document.getElementById('countrySelect');
    var wrap = document.getElementById('otherCountryWrap');
    if (!sel || !wrap) {
      return;
    }
    sel.addEventListener('change', function () {
      var show = sel.value === 'Other countries sending workers';
      wrap.classList.toggle('d-none', !show);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    bindCountryOther();
    var back = document.getElementById('ratebMktBackToPricing');
    if (back) {
      back.addEventListener('click', hideAgencyRegister);
    }
    if (qs('register') === '1') {
      showAgencyRegister(qs('plan') || 'professional', qs('years') || '1');
    }
  });

  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href*="register=1"]');
    if (!a) {
      return;
    }
    try {
      var url = new URL(a.href, window.location.origin);
      if (url.pathname.replace(/\/$/, '').endsWith('/site/pricing') || url.hash === '#pricing') {
        if (window.location.pathname.replace(/\/$/, '').endsWith('/site/pricing')) {
          e.preventDefault();
          showAgencyRegister(url.searchParams.get('plan') || 'professional', url.searchParams.get('years') || '1');
          window.history.replaceState({}, '', url.pathname + url.search + '#pricing');
        }
      }
    } catch (err) {
      /* allow navigation */
    }
  });
})();
