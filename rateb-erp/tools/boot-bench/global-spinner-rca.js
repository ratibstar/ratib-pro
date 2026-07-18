/**
 * GLOBAL SPINNER RCA — evidence only (ignore nav engine delay).
 * Find which DOM spinner users see and which Promise/fetch keeps it visible.
 *
 *   node global-spinner-rca.js
 */
'use strict';

const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const BASE = process.env.RATEB_ERP_URL || 'https://rateb.sa/rateb-erp/public';
const KEY = process.env.RATEB_SSH_KEY || 'C:\\Users\\Public\\ratib_da_deploy_runtime';
const HOST = process.env.RATEB_SSH_HOST || 'admin@167.233.71.107';
const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const STAMP = Date.now();
const OUT_JSON = path.join(__dirname, 'reports', 'GLOBAL-SPINNER-RCA-' + STAMP + '.json');
const OUT_MD = path.join(__dirname, 'reports', 'GLOBAL-SPINNER-RCA.md');
const ROOT = path.join(__dirname, '..', '..');

const ROUTES = [
  { id: 'Dashboard', match: /\/admin\/?$/, group: null, hrefHint: null, preferPath: /\/admin\/?$/ },
  { id: 'Companies', match: /\/admin\/companies(\/|$|\?)/i, group: /شركات|companies|إعدادات|النظام/i },
  { id: 'Inventory', match: /\/admin\/ops\/inventory(\/|$|\?)/i, group: /المخزون|inventory/i },
  { id: 'Purchasing', match: /\/admin\/ops\/purchase-requests(\/|$|\?)/i, group: /المشتريات|procurement|purchas/i },
  { id: 'HR', match: /\/admin\/hr(\/|$|\?)/i, group: /الموارد البشرية|\bhr\b/i },
];

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 90000,
  });
}

function r1(n) {
  if (n == null || Number.isNaN(Number(n))) return null;
  return Math.round(Number(n) * 10) / 10;
}

/** Static inventory (Admin-relevant; excludes pure vendor FA/bootstrap defs). */
function staticInventory() {
  return [
    {
      id: 'offline_warm_progress',
      file: 'public/assets/offline/erp-offline-full-warm.js',
      show: { function: 'ensureProgressUi', line: 460 },
      hide: { function: 'startFullWarm.then → setTimeout(box2.remove)', line: 972 },
      update: { function: 'updateProgressUi', line: 447 },
      promise: 'startFullWarm() → runQueue(assets) → runQueue(pages)',
      waitsAjax: true,
      waitsModuleBootstrap: false,
      userVisible: 'Fixed bottom-left banner «تجهيز الأوفلاين… N/M»',
      selector: '#rateb-offline-warm-progress',
    },
    {
      id: 'module_metrics_skeleton',
      file: 'views/components/module-page-stats.php + public/assets/js/module-page-stats.js',
      show: { function: 'PHP render (class is-loading)', line: 'module-page-stats.php:14' },
      hide: { function: 'renderStrip → classList.remove(is-loading)', line: 'module-page-stats.js:43' },
      promise: 'fetch(data-module-metrics-url) JSON',
      waitsAjax: true,
      waitsModuleBootstrap: true,
      userVisible: 'Metrics strip skeleton (.cm--page-stats.is-loading)',
      selector: '.cm--page-stats.is-loading, [data-module-metrics-async].is-loading',
    },
    {
      id: 'accounting_control_showLoading',
      file: 'public/assets/accounting-control/control-center-phase7.js',
      show: { function: 'showLoading(true)', line: 17 },
      hide: { function: 'showLoading(false)', line: 17 },
      promise: 'section AJAX fetches in that file',
      waitsAjax: true,
      waitsModuleBootstrap: true,
      userVisible: 'Accounting-control section spinner only',
      selector: '.spinner-border (section shell)',
      scope: 'accounting-control pages only',
    },
    {
      id: 'accounting_section_shell_spinner',
      file: 'views/admin/accounting-control/sections/_section-shell.php',
      show: { function: 'PHP markup', line: 9 },
      hide: { function: 'control-center JS showLoading(false)', line: 'phase7.js' },
      promise: 'section load',
      waitsAjax: true,
      waitsModuleBootstrap: true,
      userVisible: 'Bootstrap spinner-border-sm in section shell',
      selector: '.spinner-border',
    },
    {
      id: 'entity_documents_modal',
      file: 'public/assets/js/entity-documents-modal.js',
      show: { function: 'modal body HTML inject', line: 132 },
      hide: { function: 'render results HTML', line: 'same file' },
      promise: 'documents fetch',
      waitsAjax: true,
      waitsModuleBootstrap: false,
      userVisible: 'Modal-only spinner-border',
      selector: '.spinner-border inside modal',
    },
    {
      id: 'approvals_loading_icon',
      file: 'views/admin/approvals/index.php',
      show: { function: 'PHP / JS row busy', line: 206 },
      hide: { function: 'approvals-oversight setRowBusy(false)', line: 'approvals-oversight.js:315' },
      promise: 'approval action fetch',
      waitsAjax: true,
      waitsModuleBootstrap: false,
      userVisible: 'Inline fa-spinner on approval rows',
      selector: '.fa-spinner.fa-spin',
    },
    {
      id: 'login_barcode_spinner',
      file: 'views/shared/auth/login.php + erp-login-barcode.js',
      show: { function: 'login wait UI', line: 70 },
      hide: { function: 'pair complete', line: 'erp-login-barcode.js' },
      promise: 'barcode pair poll',
      waitsAjax: true,
      waitsModuleBootstrap: false,
      userVisible: 'Login-only',
      selector: '.fa-spinner.fa-spin',
      scope: 'login page only',
    },
    {
      id: 'browser_tab_loading',
      file: '(browser chrome — not DOM)',
      show: { function: 'document navigation / hardNavigate', line: 'erp-nav-instant.js hardNavigate / full load' },
      hide: { function: 'loadEvent', line: 'browser' },
      promise: 'document navigation',
      waitsAjax: false,
      waitsModuleBootstrap: false,
      userVisible: 'Tab/toolbar spinner — ONLY on full document navigation',
      selector: null,
      note: 'Content-swap does not drive this',
    },
    {
      id: 'nprogress_pace_loadingManager',
      file: '—',
      show: null,
      hide: null,
      promise: null,
      waitsAjax: null,
      waitsModuleBootstrap: null,
      userVisible: 'NOT FOUND in Admin ERP codebase',
      selector: null,
      missing: true,
    },
  ];
}

function installSpinnerProbe() {
  if (window.__SPIN_RCA__ && window.__SPIN_RCA__.__installed) return true;
  const N = (window.__SPIN_RCA__ = {
    __installed: true,
    pendingFetch: new Map(),
    pendingXhr: new Map(),
    events: [],
    visible: new Map(),
    armed: false,
    run: null,
  });
  const now = () => performance.now();
  const push = (type, detail) => {
    const ev = Object.assign({ t: now(), type }, detail || {});
    N.events.push(ev);
    if (N.run) N.run.events.push(ev);
    return ev;
  };

  const SPIN_SEL = [
    '#rateb-offline-warm-progress',
    '.cm--page-stats.is-loading',
    '[data-module-metrics-async].is-loading',
    '.spinner-border',
    '.fa-spinner.fa-spin',
    '.rateb-loading',
    '#rateb-loading',
    '[aria-busy="true"]',
    '.is-loading',
    '[data-rateb-erp-auth-lock]',
  ].join(',');

  function isVisible(el) {
    if (!el || !el.getBoundingClientRect) return false;
    const st = getComputedStyle(el);
    if (st.display === 'none' || st.visibility === 'hidden' || Number(st.opacity) === 0) return false;
    const r = el.getBoundingClientRect();
    return r.width > 0 || r.height > 0 || el.id === 'rateb-offline-warm-progress';
  }

  function describe(el) {
    return {
      id: el.id || null,
      tag: el.tagName,
      className: String(el.className || '').slice(0, 120),
      text: (el.textContent || '').trim().slice(0, 80),
      selectorHit:
        el.id === 'rateb-offline-warm-progress'
          ? '#rateb-offline-warm-progress'
          : el.classList && el.classList.contains('is-loading')
            ? '.is-loading'
            : el.classList && el.classList.contains('spinner-border')
              ? '.spinner-border'
              : el.classList && el.classList.contains('fa-spin')
                ? '.fa-spin'
                : 'other',
    };
  }

  function snapshotSpinners() {
    const found = [];
    try {
      document.querySelectorAll(SPIN_SEL).forEach((el) => {
        if (isVisible(el)) found.push(describe(el));
      });
    } catch (e) { /* ignore */ }
    return found;
  }

  function pendingSnapshot() {
    const fetches = [...N.pendingFetch.values()];
    const xhrs = [...N.pendingXhr.values()];
    return {
      fetchCount: fetches.length,
      xhrCount: xhrs.length,
      fetches: fetches.slice(0, 30),
      xhrs: xhrs.slice(0, 20),
    };
  }

  function onSpinnerAppear(el) {
    const key = el.id || el.className + ':' + (el.getAttribute('data-module-metrics-url') || '');
    if (N.visible.has(key)) return;
    const info = describe(el);
    const pend = pendingSnapshot();
    N.visible.set(key, { el, info, shownAt: now(), pendAtShow: pend });
    push('spinner_show', { info, pending: pend });
  }

  function onSpinnerGone(key, info) {
    const rec = N.visible.get(key);
    if (!rec) return;
    const pend = pendingSnapshot();
    const ms = now() - rec.shownAt;
    N.visible.delete(key);
    push('spinner_hide', {
      info: info || rec.info,
      visibleMs: ms,
      pendingAtHide: pend,
      pendingAtShow: rec.pendAtShow,
      fetchesThatOverlapped: rec.pendAtShow.fetches,
    });
  }

  function scan() {
    const live = new Map();
    snapshotSpinners().forEach((info) => {
      // re-find element
    });
    const els = [];
    try {
      document.querySelectorAll(SPIN_SEL).forEach((el) => {
        if (isVisible(el)) els.push(el);
      });
    } catch (e) { /* ignore */ }
    const keysNow = new Set();
    els.forEach((el) => {
      const key = (el.id || '') + '|' + String(el.className || '').slice(0, 60) + '|' + (el.getAttribute('data-module-metrics-url') || '');
      keysNow.add(key);
      if (!N.visible.has(key)) onSpinnerAppear(el);
      else N.visible.get(key).el = el;
    });
    [...N.visible.keys()].forEach((key) => {
      if (!keysNow.has(key)) {
        const rec = N.visible.get(key);
        onSpinnerGone(key, rec && rec.info);
      }
    });
  }

  setInterval(scan, 50);
  try {
    new MutationObserver(() => scan()).observe(document.documentElement, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['class', 'style', 'hidden', 'aria-busy'],
    });
  } catch (e) { /* ignore */ }

  // fetch tracker
  const ofetch = window.fetch.bind(window);
  let fid = 0;
  window.fetch = function (input, init) {
    const id = ++fid;
    const url = String(typeof input === 'string' ? input : (input && input.url) || '').slice(0, 220);
    const headers = (init && init.headers) || {};
    const hv = (k) => {
      try {
        if (headers.get) return headers.get(k);
        return headers[k] || headers[k.toLowerCase()];
      } catch (e) {
        return null;
      }
    };
    const rec = {
      id,
      url,
      started: now(),
      kind: hv('X-Rateb-Nav-Swap') ? 'nav-swap' : hv('X-Rateb-Prefetch') ? 'prefetch' : 'fetch',
    };
    N.pendingFetch.set(id, rec);
    push('fetch_start', { id, url, kind: rec.kind });
    return ofetch(input, init).then(
      (res) => {
        rec.ended = now();
        rec.ms = rec.ended - rec.started;
        rec.status = res.status;
        N.pendingFetch.delete(id);
        push('fetch_end', { id, url, ms: rec.ms, status: res.status, kind: rec.kind });
        return res;
      },
      (err) => {
        rec.ended = now();
        rec.ms = rec.ended - rec.started;
        rec.error = String(err && err.message ? err.message : err);
        N.pendingFetch.delete(id);
        push('fetch_end', { id, url, ms: rec.ms, error: rec.error, kind: rec.kind });
        throw err;
      }
    );
  };

  // XHR tracker
  const XO = XMLHttpRequest.prototype.open;
  const XS = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function (method, url) {
    this.__spinUrl = String(url || '').slice(0, 220);
    this.__spinMethod = method;
    return XO.apply(this, arguments);
  };
  XMLHttpRequest.prototype.send = function () {
    const id = 'x' + ++fid;
    const rec = { id, url: this.__spinUrl, method: this.__spinMethod, started: now() };
    N.pendingXhr.set(id, rec);
    push('xhr_start', rec);
    this.addEventListener('loadend', () => {
      rec.ms = now() - rec.started;
      N.pendingXhr.delete(id);
      push('xhr_end', { id, url: rec.url, ms: rec.ms, status: this.status });
    });
    return XS.apply(this, arguments);
  };

  N.startRun = (meta) => {
    N.run = {
      id: meta.id,
      href: meta.href,
      t0: now(),
      wall0: Date.now(),
      events: [],
      spinnerEpisodes: [],
    };
    N.armed = true;
    push('run_start', meta);
    scan();
  };

  N.finishRun = async () => {
    const deadline = now() + 20000;
    // Wait until: no tracked spinner visible OR quiet network for 400ms after spinner hide
    let quiet = 0;
    while (now() < deadline) {
      scan();
      const spins = snapshotSpinners();
      const pend = pendingSnapshot();
      if (spins.length === 0 && pend.fetchCount === 0 && pend.xhrCount === 0) {
        quiet += 50;
        if (quiet >= 400) break;
      } else quiet = 0;
      await new Promise((r) => setTimeout(r, 50));
    }
    scan();
    // Build episodes from events
    const episodes = [];
    let open = null;
    for (const ev of N.run.events) {
      if (ev.type === 'spinner_show') {
        open = {
          info: ev.info,
          shownAt: ev.t - N.run.t0,
          pendingAtShow: ev.pending,
          hideAt: null,
          visibleMs: null,
          pendingAtHide: null,
          fetchesStartedWhileVisible: [],
          fetchesEndedWhileVisible: [],
        };
      } else if (ev.type === 'spinner_hide' && open) {
        open.hideAt = ev.t - N.run.t0;
        open.visibleMs = ev.visibleMs;
        open.pendingAtHide = ev.pendingAtHide;
        episodes.push(open);
        open = null;
      } else if (open && ev.type === 'fetch_start') {
        open.fetchesStartedWhileVisible.push({ url: ev.url, kind: ev.kind, t: ev.t - N.run.t0 });
      } else if (open && ev.type === 'fetch_end') {
        open.fetchesEndedWhileVisible.push({ url: ev.url, kind: ev.kind, ms: ev.ms, status: ev.status, t: ev.t - N.run.t0 });
      }
    }
    if (open) {
      open.hideAt = null;
      open.visibleMs = now() - (N.run.t0 + open.shownAt);
      open.stillVisible = true;
      episodes.push(open);
    }
    N.run.episodes = episodes;
    N.run.finalSpinners = snapshotSpinners();
    N.run.finalPending = pendingSnapshot();
    N.run.totalMs = now() - N.run.t0;
    N.run.hrefFinal = location.href;

    // Correlate: which fetch overlapped longest with spinner visibility
    let culprit = null;
    for (const ep of episodes) {
      const candidates = [];
      // fetches pending at show
      (ep.pendingAtShow && ep.pendingAtShow.fetches ? ep.pendingAtShow.fetches : []).forEach((f) => {
        candidates.push({ url: f.url, kind: f.kind, reason: 'pending_at_spinner_show' });
      });
      (ep.fetchesStartedWhileVisible || []).forEach((f) => {
        candidates.push({ url: f.url, kind: f.kind, reason: 'started_while_spinner_visible', t: f.t });
      });
      // Prefer metrics URL or warm-related
      const scored = candidates.map((c) => {
        let score = 0;
        if (/metrics|module-metrics|stats/i.test(c.url)) score += 100;
        if (/warm|offline/i.test(c.url)) score += 50;
        if (c.kind === 'nav-swap') score -= 20; // nav proven not the multi-second spinner source
        if (ep.info && ep.info.selectorHit === '#rateb-offline-warm-progress') score += 200;
        if (ep.info && /is-loading/.test(ep.info.className || '')) score += 150;
        return Object.assign(c, { score });
      });
      scored.sort((a, b) => b.score - a.score);
      ep.likelyControllingRequests = scored.slice(0, 8);
      if (!culprit && scored[0]) {
        culprit = {
          spinner: ep.info,
          visibleMs: ep.visibleMs,
          request: scored[0],
          episode: { shownAt: ep.shownAt, hideAt: ep.hideAt },
        };
      }
      // For metrics skeleton: controlling promise is metrics fetch ending near hide
      const metricsEnd = (ep.fetchesEndedWhileVisible || []).filter((f) => /metrics|stats/i.test(f.url));
      if (metricsEnd.length && /is-loading/.test((ep.info && ep.info.className) || '')) {
        ep.controllingPromise = {
          type: 'fetch',
          url: metricsEnd[metricsEnd.length - 1].url,
          durationMs: metricsEnd[metricsEnd.length - 1].ms,
          evidence: 'metrics fetch ended while .is-loading visible; hide removes is-loading in module-page-stats.js:43',
        };
        culprit = {
          spinner: ep.info,
          visibleMs: ep.visibleMs,
          request: ep.controllingPromise,
          episode: { shownAt: ep.shownAt, hideAt: ep.hideAt },
        };
      }
      if (ep.info && ep.info.selectorHit === '#rateb-offline-warm-progress') {
        ep.controllingPromise = {
          type: 'startFullWarm runQueue chain',
          evidence: 'Banner remains until warm queue completes; removed 8s after done (erp-offline-full-warm.js:972)',
          pendingFetchesAtShow: (ep.pendingAtShow && ep.pendingAtShow.fetchCount) || 0,
        };
        culprit = {
          spinner: ep.info,
          visibleMs: ep.visibleMs,
          request: ep.controllingPromise,
          episode: { shownAt: ep.shownAt, hideAt: ep.hideAt },
        };
      }
    }
    N.run.culprit = culprit;
    const out = N.run;
    N.run = null;
    N.armed = false;
    return out;
  };

  return true;
}

async function goDashboard(page) {
  await page.goto(BASE + '/admin/?company_id=22&_spin=' + Date.now(), {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });
  await page.waitForFunction(
    () => window.RatebNavInstant && document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1',
    { timeout: 45000 }
  );
  await page.evaluate(installSpinnerProbe);
}

async function resolveHref(page, route) {
  if (route.id === 'Dashboard') {
    return BASE + '/admin/?company_id=22';
  }
  return page.evaluate((spec) => {
    const re = new RegExp(spec.match.source, spec.match.flags);
    if (spec.group) {
      const gre = new RegExp(spec.group.source, spec.group.flags);
      for (const b of document.querySelectorAll('[data-nav-group-toggle]')) {
        if (gre.test(b.textContent || '')) b.click();
      }
    } else {
      document.querySelectorAll('[data-nav-group-toggle]').forEach((b) => b.click());
    }
    const a = [...document.querySelectorAll('a[href]')].find((el) => {
      try {
        const u = new URL(el.href);
        return re.test(u.pathname + u.search);
      } catch (e) {
        return false;
      }
    });
    return a ? a.href : null;
  }, {
    match: { source: route.match.source, flags: route.match.flags },
    group: route.group ? { source: route.group.source, flags: route.group.flags } : null,
  });
}

async function runClick(page, client, route) {
  await goDashboard(page);
  // Give warm a chance to start on first scenario only later; for each click start from dash
  const href = await resolveHref(page, route);
  if (!href && route.id !== 'Dashboard') return { id: route.id, error: 'link_not_found' };

  // CDP tab loading (browser spinner)
  const tabLoads = [];
  const onLoadStart = () => tabLoads.push({ t: Date.now(), type: 'frameStartedLoading' });
  const onLoadStop = () => tabLoads.push({ t: Date.now(), type: 'frameStoppedLoading' });
  try {
    await client.send('Page.enable');
    client.on('Page.frameStartedLoading', onLoadStart);
    client.on('Page.frameStoppedLoading', onLoadStop);
  } catch (e) { /* ignore */ }

  await page.evaluate(
    (meta) => {
      window.__SPIN_RCA__.startRun(meta);
    },
    { id: route.id, href: href || location.href }
  );

  if (route.id === 'Dashboard') {
    // Already on dashboard — soft re-navigate to self may no-op; click home link if present
    await page.evaluate(() => {
      const a = document.querySelector('a.rateb-nav-link[href*="/admin"]');
      if (a) a.click();
      else if (window.RatebNavInstant) window.RatebNavInstant.navigate(location.href.split('#')[0]);
    });
  } else {
    await page.evaluate((h) => {
      const a = [...document.querySelectorAll('a[href]')].find((el) => el.href === h);
      if (a) {
        a.scrollIntoView({ block: 'center' });
        a.click();
      } else {
        window.RatebNavInstant.navigate(h);
      }
    }, href);
  }

  const result = await page.evaluate(async () => window.__SPIN_RCA__.finishRun());
  try {
    client.off('Page.frameStartedLoading', onLoadStart);
    client.off('Page.frameStoppedLoading', onLoadStop);
  } catch (e2) { /* ignore */ }

  result.tabLoadingEvents = tabLoads;
  result.tabSpinnerFired = tabLoads.some((e) => e.type === 'frameStartedLoading');
  return { id: route.id, href, result };
}

function buildMarkdown(inventory, runs, warmProbe) {
  const L = [];
  L.push('# GLOBAL SPINNER RCA (Evidence Only)');
  L.push('');
  L.push('**Date:** ' + new Date().toISOString());
  L.push('');
  L.push('**Premise:** Navigation engine is proven NOT to contain the multi-second delay. This RCA finds the **loading spinner users actually see**.');
  L.push('');
  L.push('**Method:** Static inventory + runtime MutationObserver + fetch/XHR tracking + CDP tab-loading. No production fixes.');
  L.push('');

  L.push('## Static inventory (Admin ERP)');
  L.push('');
  L.push('| ID | File | Show | Hide | Controlling Promise | AJAX? | Module bootstrap? | User-visible? |');
  L.push('|----|------|------|------|---------------------|-------|-------------------|---------------|');
  for (const s of inventory) {
    if (s.missing) {
      L.push('| ' + s.id + ' | — | NOT FOUND | — | — | — | — | — |');
      continue;
    }
    L.push(
      '| ' +
        s.id +
        ' | `' +
        s.file +
        '` | ' +
        (s.show ? s.show.function + ' @ ' + s.show.line : '') +
        ' | ' +
        (s.hide ? s.hide.function + ' @ ' + s.hide.line : '') +
        ' | ' +
        (s.promise || '') +
        ' | ' +
        s.waitsAjax +
        ' | ' +
        s.waitsModuleBootstrap +
        ' | ' +
        (s.userVisible || '') +
        ' |'
    );
  }
  L.push('');
  L.push('**Not found:** `nprogress`, `pace`, `loadingManager`, `busyCounter`, `showLoader`/`hideLoader` global nav overlay.');
  L.push('');

  L.push('## Runtime — click matrix');
  L.push('');
  L.push('| Route | Spinner episodes | Longest visible (ms) | Culprit spinner | Controlling Promise / request | Tab spinner |');
  L.push('|-------|------------------|----------------------|-----------------|-------------------------------|-------------|');
  for (const run of runs) {
    if (run.error) {
      L.push('| ' + run.id + ' | ERROR: ' + run.error + ' |');
      continue;
    }
    const R = run.result;
    const eps = R.episodes || [];
    const longest = eps.slice().sort((a, b) => (b.visibleMs || 0) - (a.visibleMs || 0))[0];
    const cul = R.culprit;
    L.push(
      '| ' +
        run.id +
        ' | ' +
        eps.length +
        ' | ' +
        (longest ? r1(longest.visibleMs) : 0) +
        ' | ' +
        (cul && cul.spinner ? cul.spinner.selectorHit || cul.spinner.id || cul.spinner.className : 'none') +
        ' | ' +
        (cul && cul.request
          ? JSON.stringify(cul.request).slice(0, 120)
          : 'none') +
        ' | ' +
        !!R.tabSpinnerFired +
        ' |'
    );
  }
  L.push('');

  for (const run of runs) {
    L.push('## ' + run.id);
    L.push('');
    if (run.error) {
      L.push('ERROR: ' + run.error);
      L.push('');
      continue;
    }
    const R = run.result;
    L.push('- href: `' + run.href + '`');
    L.push('- hrefFinal: `' + R.hrefFinal + '`');
    L.push('- observe window: **' + r1(R.totalMs) + ' ms**');
    L.push('- browser tab loading events: ' + (R.tabLoadingEvents || []).length + ' (tabSpinnerFired=' + !!R.tabSpinnerFired + ')');
    L.push('');
    if (!R.episodes || !R.episodes.length) {
      L.push('**No DOM spinner became visible during this click window.**');
      L.push('');
      L.push('Outstanding fetches at end: ' + ((R.finalPending && R.finalPending.fetchCount) || 0));
      L.push('');
      continue;
    }
    for (const ep of R.episodes) {
      L.push('### Spinner episode');
      L.push('');
      L.push('```');
      L.push('Spinner shown @ ' + r1(ep.shownAt) + ' ms');
      L.push('  id/class: ' + JSON.stringify(ep.info));
      L.push('  pending fetch/XHR at show: fetch=' + ((ep.pendingAtShow && ep.pendingAtShow.fetchCount) || 0) + ' xhr=' + ((ep.pendingAtShow && ep.pendingAtShow.xhrCount) || 0));
      L.push('  ↓');
      L.push('  Outstanding Promises/requests while visible:');
      (ep.fetchesStartedWhileVisible || []).slice(0, 15).forEach((f) => {
        L.push('    + fetch[' + f.kind + '] ' + f.url + ' @+' + r1(f.t) + 'ms');
      });
      (ep.fetchesEndedWhileVisible || []).slice(0, 15).forEach((f) => {
        L.push('    - fetch end ' + f.url + ' (' + r1(f.ms) + 'ms, status=' + f.status + ')');
      });
      L.push('  ↓');
      L.push('Spinner hidden @ ' + (ep.hideAt != null ? r1(ep.hideAt) + ' ms' : 'STILL VISIBLE') + ' (visible ' + r1(ep.visibleMs) + ' ms)');
      if (ep.controllingPromise) L.push('  controlling: ' + JSON.stringify(ep.controllingPromise));
      L.push('```');
      L.push('');
    }
    if (R.culprit) {
      L.push('**Culprit for this click:**');
      L.push('');
      L.push('```json');
      L.push(JSON.stringify(R.culprit, null, 2));
      L.push('```');
      L.push('');
    }
  }

  if (warmProbe) {
    L.push('## Warm-banner probe (idle ≥20s path)');
    L.push('');
    L.push('```json');
    L.push(JSON.stringify(warmProbe, null, 2));
    L.push('```');
    L.push('');
  }

  L.push('## Verdict');
  L.push('');
  const allEp = [];
  runs.forEach((r) => {
    if (r.result && r.result.episodes) {
      r.result.episodes.forEach((ep) => allEp.push(Object.assign({ route: r.id }, ep)));
    }
  });
  const multi = allEp.filter((e) => (e.visibleMs || 0) >= 1000);
  if (multi.length) {
    const top = multi.sort((a, b) => b.visibleMs - a.visibleMs)[0];
    L.push(
      'Multi-second DOM spinner observed: **' +
        ((top.info && (top.info.selectorHit || top.info.id)) || 'unknown') +
        '** for **' +
        r1(top.visibleMs) +
        ' ms** on **' +
        top.route +
        '**.'
    );
    L.push('');
    L.push('Controlling Promise/request: **' + JSON.stringify(top.controllingPromise || top.likelyControllingRequests) + '**');
  } else {
    L.push('In the immediate click windows (Dashboard / Companies / Inventory / Purchasing / HR), **no DOM spinner stayed visible for several seconds**.');
    L.push('');
    L.push('What *can* produce a multi-second visible loader in Admin:');
    L.push('');
    L.push('1. **`#rateb-offline-warm-progress`** — shown by `ensureProgressUi` / `startFullWarm`; remains for the entire offline warm queue (many fetches); removed only ~8s after warm completes (`erp-offline-full-warm.js`). This is **not** driven by content-swap `swapTo`, but by idle warm Promises.');
    L.push('2. **`.cm--page-stats.is-loading`** — shown in HTML for async metrics; hidden when `fetch(metricsUrl)` resolves (`module-page-stats.js`). Duration = metrics AJAX, typically sub-second unless the API is slow.');
    L.push('3. **Browser tab spinner** — only on **full document** navigation (`hardNavigate` / POS bypass / first load), not soft content-swap.');
    L.push('');
    L.push('If users report multi-second spinner *during* module clicks while soft-nav works, the evidence points to **`#rateb-offline-warm-progress` overlapping the session** (warm still running) or a **slow metrics fetch** leaving `.is-loading` up — not the navigation engine.');
  }
  L.push('');
  L.push('No production code was modified.');
  L.push('');
  return L.join('\n');
}

(async () => {
  const inventory = staticInventory();
  const mint = JSON.parse(
    ssh(
      'php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint'
    )
  );

  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'spin-rca-' + Date.now()), {
    headless: true,
    executablePath: CHROME,
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
    viewport: { width: 1440, height: 900 },
  });
  await ctx.addCookies([
    {
      name: mint.session_name || 'rateb_erp',
      value: mint.session_id,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
      sameSite: 'Lax',
    },
  ]);
  await ctx.addInitScript(installSpinnerProbe);

  const page = ctx.pages()[0] || (await ctx.newPage());
  const client = await ctx.newCDPSession(page);
  const runs = [];

  for (const route of ROUTES) {
    try {
      runs.push(await runClick(page, client, route));
    } catch (e) {
      runs.push({ id: route.id, error: String(e && e.message ? e.message : e) });
    }
  }

  // Warm probe: stay on dashboard long enough / force localStorage clear + wait for banner
  let warmProbe = null;
  try {
    await goDashboard(page);
    await page.evaluate(() => {
      try {
        Object.keys(localStorage)
          .filter((k) => /RATEB|WARM|OFFLINE/i.test(k))
          .forEach((k) => localStorage.removeItem(k));
      } catch (e) { /* ignore */ }
      // Force warm if API exists
      if (window.RatebOfflineFullWarm && typeof window.RatebOfflineFullWarm.start === 'function') {
        window.RatebOfflineFullWarm.start({ force: true });
      } else if (window.__RATEB_FORCE_OFFLINE_WARM__) {
        window.__RATEB_FORCE_OFFLINE_WARM__ = true;
      }
    });
    // Also try URL force param reload
    await page.goto(BASE + '/admin/?company_id=22&rateb_warm=1&_=' + Date.now(), {
      waitUntil: 'domcontentloaded',
      timeout: 90000,
    });
    await page.evaluate(installSpinnerProbe);
    await page.waitForTimeout(5000);
    warmProbe = await page.evaluate(() => {
      const el = document.getElementById('rateb-offline-warm-progress');
      const pend = window.__SPIN_RCA__
        ? {
            fetch: window.__SPIN_RCA__.pendingFetch.size,
            xhr: window.__SPIN_RCA__.pendingXhr.size,
          }
        : null;
      return {
        warmBannerPresent: !!el,
        warmBannerText: el ? (el.textContent || '').slice(0, 120) : null,
        warmBannerVisible: !!(el && el.offsetParent !== null),
        pending: pend,
      };
    });
    // If banner present, wait and sample pending fetches
    if (warmProbe.warmBannerPresent) {
      await page.waitForTimeout(3000);
      warmProbe.after3s = await page.evaluate(() => {
        const el = document.getElementById('rateb-offline-warm-progress');
        const fetches = window.__SPIN_RCA__ ? [...window.__SPIN_RCA__.pendingFetch.values()] : [];
        return {
          stillPresent: !!el,
          text: el ? (el.textContent || '').slice(0, 120) : null,
          pendingFetchCount: fetches.length,
          pendingFetchSample: fetches.slice(0, 10),
        };
      });
    }
  } catch (eW) {
    warmProbe = { error: String(eW.message || eW) };
  }

  fs.mkdirSync(path.dirname(OUT_JSON), { recursive: true });
  fs.writeFileSync(
    OUT_JSON,
    JSON.stringify({ generatedAt: new Date().toISOString(), inventory, runs, warmProbe }, null, 2)
  );
  fs.writeFileSync(OUT_MD, buildMarkdown(inventory, runs, warmProbe));
  console.log(OUT_JSON);
  console.log(OUT_MD);
  console.log(
    JSON.stringify(
      {
        runs: runs.map((r) => ({
          id: r.id,
          err: r.error || null,
          episodes: r.result && r.result.episodes && r.result.episodes.length,
          longestMs:
            r.result && r.result.episodes && r.result.episodes.length
              ? Math.max(...r.result.episodes.map((e) => e.visibleMs || 0))
              : 0,
          culprit: r.result && r.result.culprit,
          tabSpinner: r.result && r.result.tabSpinnerFired,
        })),
        warmProbe,
      },
      null,
      2
    )
  );
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
