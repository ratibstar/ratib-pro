/**
 * NAVIGATION LATENCY RCA — EVIDENCE ONLY (no production changes).
 *
 *   node nav-latency-waterfall-rca.js
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
const OUT_JSON = path.join(__dirname, 'reports', 'NAV-LATENCY-WATERFALL-' + STAMP + '.json');
const OUT_MD = path.join(__dirname, 'reports', 'NAV-LATENCY-WATERFALL-RCA.md');

const ROUTES = [
  { id: 'Inventory', match: [/\/admin\/ops\/inventory(\/|$|\?)/i], group: /المخزون|inventory/i },
  { id: 'HR', match: [/\/admin\/hr(\/|$|\?)/i], group: /الموارد البشرية|human|\bhr\b/i },
  {
    id: 'Sales',
    // Prefer customers (accounting sales). POS/* is intentionally hard-nav (POS_PATH_RE excludes content-swap).
    match: [/\/admin\/ops\/customers(\/|$|\?)/i],
    group: /محاسبة|accounting|عملاء|customers|المبيعات|sales/i,
  },
  {
    id: 'Sales_POS_hardnav',
    match: [/\/admin\/ops\/pos\/dashboard(\/|$|\?)/i],
    group: /نقطة البيع|pos/i,
    expectHardNav: true,
  },
  {
    id: 'Purchasing',
    match: [/\/admin\/ops\/purchase-requests(\/|$|\?)/i, /\/admin\/ops\/purchase-orders(\/|$|\?)/i],
    group: /المشتريات|procurement|purchas/i,
  },
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

function stageDurations(marks) {
  const order = [
    'T0_click',
    'T1_onClick',
    'T2_preventDefault',
    'T3_swapTo',
    'T4_navigating_true',
    'T5_fetchHtml_called',
    'T5b_cacheApi_done',
    'T6_http_request_start',
    'T7_first_byte',
    'T8_response_complete',
    'T9_html_parsed',
    'T10_dom_swap_start',
    'T11_dom_swap_end',
    'T12_beforeEnter',
    'T12b_scripts_start',
    'T12c_scripts_end',
    'T13_afterEnter',
    'T14_page_interactive',
  ];
  const rows = [];
  for (let i = 0; i < order.length; i++) {
    const key = order[i];
    const t = marks[key];
    let delta = null;
    if (t != null && marks.T0_click != null) {
      let p = marks.T0_click;
      for (let j = i - 1; j >= 0; j--) {
        if (marks[order[j]] != null) {
          p = marks[order[j]];
          break;
        }
      }
      delta = r1(t - p);
    }
    rows.push({
      stage: key,
      t_ms: t == null ? null : r1(t - marks.T0_click),
      stage_ms: delta,
      observed: t != null,
    });
  }
  return rows;
}

/** Injected in-page — patches live nav pipeline. */
function installInstrumentation() {
  if (window.__NAV_RCA__ && window.__NAV_RCA__.__installed) {
    return true;
  }
  const N = (window.__NAV_RCA__ = {
    active: null,
    runs: [],
    longTasks: [],
    promises: [],
    hooks: [],
    raw: [],
    spinState: { visible: false, firstAt: null, lastAt: null },
    __installed: true,
  });
  const now = () => performance.now();
  const rRound = (d) => Math.round(d * 10) / 10;
  const push = (type, detail) => N.raw.push(Object.assign({ t: now(), type }, detail || {}));

  try {
    const po = new PerformanceObserver((list) => {
      for (const e of list.getEntries()) {
        if (e.duration >= 50) {
          N.longTasks.push({ t: now(), start: e.startTime, duration: e.duration, name: e.name || 'longtask' });
        }
      }
    });
    po.observe({ type: 'longtask', buffered: true });
  } catch (e) { /* ignore */ }

  const spinSel =
    '.spinner-border:not([hidden]), .fa-spinner.fa-spin, #rateb-offline-warm-progress, .rateb-loading, [data-rateb-nav-busy="1"]';
  const checkSpin = () => {
    const spins = [...document.querySelectorAll(spinSel)].filter((el) => {
      const st = getComputedStyle(el);
      return st.display !== 'none' && st.visibility !== 'hidden' && (el.offsetWidth > 0 || el.offsetHeight > 0);
    });
    const vis = spins.length > 0;
    if (vis && !N.spinState.visible) {
      N.spinState.visible = true;
      N.spinState.firstAt = now();
      if (N.active) {
        N.active.spinnerFirstAt = N.spinState.firstAt;
        N.active.spinnerShown = true;
      }
      push('spinner_show', { count: spins.length });
    } else if (!vis && N.spinState.visible) {
      N.spinState.visible = false;
      N.spinState.lastAt = now();
      if (N.active) N.active.spinnerLastAt = N.spinState.lastAt;
      push('spinner_hide', {});
    }
  };
  setInterval(checkSpin, 40);

  // ---- Cache API ----
  if (window.caches) {
    const oOpen = caches.open.bind(caches);
    const oKeys = caches.keys.bind(caches);
    const patchCache = (cache) => {
      if (!cache || cache.__rca) return cache;
      cache.__rca = true;
      const om = cache.match.bind(cache);
      cache.match = function (req, opts) {
        const t0 = now();
        return om(req, opts).then((hit) => {
          const d = now() - t0;
          if (N.active) {
            N.active.cacheApi = N.active.cacheApi || { matches: [], totalMs: 0, opens: [] };
            N.active.cacheApi.matches.push({
              key: String(req && req.url ? req.url : req).slice(0, 140),
              hit: !!hit,
              ms: rRound(d),
            });
            N.active.cacheApi.totalMs += d;
            if (hit) N.active.cacheHit = true;
          }
          if (d > 30) N.promises.push({ kind: 'cache.match', ms: rRound(d) });
          return hit;
        });
      };
      return cache;
    };
    caches.open = function (name) {
      const t0 = now();
      return oOpen(name).then((c) => {
        const d = now() - t0;
        if (N.active) {
          N.active.cacheApi = N.active.cacheApi || { matches: [], totalMs: 0, opens: [] };
          N.active.cacheApi.opens.push({ name, ms: rRound(d) });
          N.active.cacheApi.totalMs += d;
        }
        if (d > 30) N.promises.push({ kind: 'caches.open', ms: rRound(d), name });
        return patchCache(c);
      });
    };
    caches.keys = function () {
      const t0 = now();
      return oKeys().then((keys) => {
        const d = now() - t0;
        if (N.active) {
          N.active.cacheApi = N.active.cacheApi || { matches: [], totalMs: 0, opens: [] };
          N.active.cacheApi.keysMs = rRound(d);
          N.active.cacheApi.totalMs += d;
        }
        if (d > 30) N.promises.push({ kind: 'caches.keys', ms: rRound(d) });
        return keys;
      });
    };
  }

  // ---- fetch ----
  const origFetch = window.fetch.bind(window);
  window.fetch = function (input, init) {
    const url = typeof input === 'string' ? input : (input && input.url) || String(input);
    const headers = (init && init.headers) || {};
    const hdr = (k) => {
      try {
        if (headers.get) return headers.get(k);
        if (typeof headers === 'object') return headers[k] || headers[k.toLowerCase()];
      } catch (e) { /* ignore */ }
      return null;
    };
    const isNav = hdr('X-Rateb-Nav-Swap') === '1' || hdr('x-rateb-nav-swap') === '1';
    const isPrefetch = hdr('X-Rateb-Prefetch') === '1' || hdr('x-rateb-prefetch') === '1';
    const tStart = now();
    if (N.active && isNav && !isPrefetch) {
      // Cache API phase ends when network fetch starts
      if (N.active.marks.T5b_cacheApi_done == null) N.active.marks.T5b_cacheApi_done = tStart;
      if (N.active.marks.T6_http_request_start == null) N.active.marks.T6_http_request_start = tStart;
      N.active.network = N.active.network || {};
      N.active.network.fetchUrl = String(url).slice(0, 240);
      N.active.network.fetchStart = tStart;
      push('fetch_start', { url: String(url).slice(0, 180) });
    }
    const tracked = origFetch(input, init).then(
      (res) => {
        if (!(N.active && isNav && !isPrefetch)) return res;
        const tHeaders = now();
        N.active.network.status = res.status;
        N.active.network.ok = !!res.ok;
        if (N.active.marks.T7_first_byte == null) N.active.marks.T7_first_byte = tHeaders;
        push('fetch_headers', { status: res.status, ms: rRound(tHeaders - tStart) });
        // Do NOT Proxy Response — native getters throw Illegal invocation via Proxy receiver.
        const origText = res.text.bind(res);
        res.text = function () {
          const t0 = now();
          return origText().then((html) => {
            const t1 = now();
            if (N.active) {
              N.active.marks.T8_response_complete = t1;
              N.active.htmlSize = html ? html.length : 0;
              N.active.network.bodyMs = rRound(t1 - t0);
              N.active.network.totalFetchMs = rRound(t1 - tStart);
            }
            push('fetch_body', { bytes: html ? html.length : 0, ms: rRound(t1 - t0) });
            return html;
          });
        };
        return res;
      },
      (err) => {
        if (N.active && isNav) {
          N.active.network = N.active.network || {};
          N.active.network.fetchError = String(err && err.message ? err.message : err);
        }
        throw err;
      }
    );
    if (N.active && isNav) {
      const t0 = now();
      tracked.then(
        () => {
          const d = now() - t0;
          if (d > 30) N.promises.push({ kind: 'nav_fetch', ms: rRound(d), url: String(url).slice(0, 120) });
        },
        () => {}
      );
    }
    return tracked;
  };

  // ---- DOMParser ----
  const OrigDP = window.DOMParser;
  window.DOMParser = function () {
    const p = new OrigDP();
    const orig = p.parseFromString.bind(p);
    p.parseFromString = function (str, type) {
      const t0 = now();
      const doc = orig(str, type);
      if (N.active) {
        N.active.marks.T9_html_parsed = now();
        N.active.parseMs = rRound(now() - t0);
      }
      push('html_parsed', { ms: rRound(now() - t0), bytes: str ? str.length : 0 });
      return doc;
    };
    return p;
  };
  window.DOMParser.prototype = OrigDP.prototype;

  // ---- main.innerHTML ----
  const watchMain = () => {
    const main = document.querySelector('#rateb-main-content, main.rateb-content');
    if (!main || main.__rcaInner) return;
    main.__rcaInner = true;
    const desc = Object.getOwnPropertyDescriptor(Element.prototype, 'innerHTML');
    if (!desc || !desc.set) return;
    Object.defineProperty(main, 'innerHTML', {
      configurable: true,
      enumerable: true,
      get() {
        return desc.get.call(this);
      },
      set(v) {
        if (N.active) N.active.marks.T10_dom_swap_start = now();
        desc.set.call(this, v);
        if (N.active) N.active.marks.T11_dom_swap_end = now();
        push('dom_swap', {});
      },
    });
  };
  watchMain();
  try {
    new MutationObserver(() => watchMain()).observe(document.documentElement, { childList: true, subtree: true });
  } catch (e) { /* ignore */ }

  // ---- script append (loadNewScripts) ----
  const origAppend = Node.prototype.appendChild;
  Node.prototype.appendChild = function (child) {
    if (
      N.active &&
      child &&
      child.tagName === 'SCRIPT' &&
      child.src &&
      (this === document.body || this === document.documentElement || this === document.head)
    ) {
      if (N.active.marks.T12b_scripts_start == null) N.active.marks.T12b_scripts_start = now();
      N.active.scripts = N.active.scripts || [];
      const t0 = now();
      const src = child.src;
      child.addEventListener(
        'load',
        () => {
          const d = now() - t0;
          N.active.scripts.push({ src: String(src).slice(0, 160), ms: rRound(d) });
          N.active.marks.T12c_scripts_end = now();
          if (d > 30) N.promises.push({ kind: 'script_load', ms: rRound(d), src: String(src).slice(0, 120) });
        },
        { once: true }
      );
      child.addEventListener(
        'error',
        () => {
          N.active.scripts.push({ src: String(src).slice(0, 160), ms: rRound(now() - t0), error: true });
          N.active.marks.T12c_scripts_end = now();
        },
        { once: true }
      );
    }
    return origAppend.call(this, child);
  };

  document.addEventListener('rateb:nav:beforeLeave', (ev) => {
    if (N.active) {
      N.active.beforeLeaveAt = now();
      N.active.marks.T12_beforeEnter = now(); // maps to beforeLeave
      N.active.hooks.push({ name: 'beforeLeave', t: now(), detail: ev.detail || null });
    }
  });
  document.addEventListener('rateb:nav:afterEnter', (ev) => {
    if (N.active) {
      N.active.marks.T13_afterEnter = now();
      N.active.fromCache = !!(ev.detail && ev.detail.fromCache);
      N.active.hooks.push({ name: 'afterEnter', t: now(), detail: ev.detail || null });
    }
  });

  // Patch RatebModuleLifecycle + navigate
  const patchNav = () => {
    const L = window.RatebModuleLifecycle;
    if (L && !L.__rca) {
      L.__rca = true;
      ['beforeLeave', 'afterEnter'].forEach((name) => {
        const orig = L[name] && L[name].bind(L);
        if (!orig) return;
        L[name] = function (detail) {
          const t0 = now();
          const hooks = (L._hooks && L._hooks[name]) || [];
          if (N.active) {
            N.active.asyncHooks = N.active.asyncHooks || [];
            hooks.forEach((_, i) => N.active.asyncHooks.push({ hook: name, index: i }));
          }
          const ret = orig(detail);
          const d = now() - t0;
          if (d > 30) N.promises.push({ kind: 'lifecycle_' + name, ms: rRound(d) });
          return ret;
        };
      });
    }
    const api = window.RatebNavInstant;
    if (!api || api.__rcaNav) return !!api;
    api.__rcaNav = true;
    const orig = api.navigate.bind(api);
    api.navigate = function (href, opts) {
      const A = N.active;
      if (A) {
        A.marks.T3_swapTo = now();
        A.marks.T4_navigating_true = now();
        A.marks.T5_fetchHtml_called = now();
        push('swapTo', { href: String(href).slice(0, 200) });
      }
      const t0 = now();
      return Promise.resolve(orig(href, opts)).then((ok) => {
        const d = now() - t0;
        if (d > 30) N.promises.push({ kind: 'swapTo', ms: rRound(d), ok: !!ok });
        if (A) {
          A.swapOk = !!ok;
          A.swapTotalMs = rRound(d);
          try {
            const entries = performance.getEntriesByType('resource').filter((r) => {
              try {
                const u = new URL(A.href, location.href);
                return r.name.indexOf(u.pathname) !== -1 && r.initiatorType === 'fetch';
              } catch (e) {
                return false;
              }
            });
            const last = entries[entries.length - 1];
            if (last && A.network && A.network.fetchStart != null) {
              A.resourceTiming = {
                duration: last.duration,
                transferSize: last.transferSize,
                decodedBodySize: last.decodedBodySize,
                requestStart: last.requestStart,
                responseStart: last.responseStart,
                responseEnd: last.responseEnd,
                delivery:
                  (last.transferSize || 0) === 0 && (last.decodedBodySize || 0) > 0
                    ? 'cache_or_sw'
                    : (last.transferSize || 0) > 0
                      ? 'network'
                      : 'empty_or_unknown',
              };
              const netTtfb = last.responseStart - last.requestStart;
              A.network.resourceTtfbMs = netTtfb;
              A.network.resourceBodyMs = last.responseEnd - last.responseStart;
              A.network.transferSize = last.transferSize;
              A.network.decodedBodySize = last.decodedBodySize;
              A.marks.T7_first_byte = A.network.fetchStart + netTtfb;
            }
          } catch (eRT) { /* ignore */ }
        }
        return ok;
      });
    };
    return true;
  };
  setInterval(patchNav, 20);
  patchNav();

  // Click capture on window so it runs BEFORE document listeners (RatebNavInstant)
  window.addEventListener(
    'click',
    (ev) => {
      if (!N.pendingClick) return;
      const a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
      if (!a) return;
      const want = N.pendingClick;
      let match = false;
      try {
        const u = new URL(a.href, location.href);
        match = want.matchers.some((re) => re.test(u.pathname + u.search));
      } catch (e) { /* ignore */ }
      if (!match) return;
      N.active = {
        id: want.id,
        pass: want.pass,
        href: a.href,
        wallClickMs: Date.now(),
        marks: { T0_click: now(), T1_onClick: now() },
        hooks: [],
        asyncHooks: [],
        scripts: [],
        cacheHit: false,
        fromCache: null,
        htmlSize: null,
        network: {},
        cacheApi: { matches: [], totalMs: 0, opens: [] },
        hardNav: false,
        spinnerShown: false,
      };
      const pd = Event.prototype.preventDefault;
      let pdSeen = false;
      Event.prototype.preventDefault = function () {
        if (!pdSeen && N.active) {
          pdSeen = true;
          N.active.marks.T2_preventDefault = now();
        }
        return pd.call(this);
      };
      setTimeout(() => {
        Event.prototype.preventDefault = pd;
      }, 200);
      N.pendingClick = null;
      push('click', { href: a.href });
    },
    true
  );

  N.startRun = (meta) => {
    N.pendingClick = meta;
    N.promises = [];
    N.active = null;
  };

  N.finishRun = async () => {
    const A = N.active;
    if (!A) return { error: 'no_active' };
    const deadline = now() + 30000;
    // Wait briefly for afterEnter / swapOk; hard-nav pages destroy context
    while (now() < deadline) {
      if (A.marks.T13_afterEnter != null || A.swapOk === false) break;
      await new Promise((r) => setTimeout(r, 30));
    }
    // interactive: afterEnter is the content-swap completion signal (avoid .fa-spin false positives)
    if (A.marks.T13_afterEnter != null) {
      A.marks.T14_page_interactive = now();
    } else {
      let quiet = 0;
      while (now() < deadline) {
        checkSpin();
        const main = document.querySelector('#rateb-main-content, main.rateb-content');
        const text = main ? (main.innerText || '').trim().length : 0;
        if (text > 40 && !N.spinState.visible) {
          quiet += 30;
          if (quiet >= 120) {
            A.marks.T14_page_interactive = now();
            break;
          }
        } else quiet = 0;
        await new Promise((r) => setTimeout(r, 30));
      }
    }
    if (A.marks.T14_page_interactive == null) A.marks.T14_page_interactive = now();

    A.beforeEnterNote = 'T12 = beforeLeave (beforeEnter not implemented)';
    A.longTasksDuring = N.longTasks.filter(
      (lt) => A.marks.T0_click != null && lt.t >= A.marks.T0_click && lt.t <= A.marks.T14_page_interactive
    );
    A.waitBeforeFetchMs =
      A.marks.T6_http_request_start != null && A.marks.T5_fetchHtml_called != null
        ? A.marks.T6_http_request_start - A.marks.T5_fetchHtml_called
        : null;
    // If cache hit, no T6 — wait is cache API only
    A.cacheApiWaitMs =
      A.marks.T5b_cacheApi_done != null && A.marks.T5_fetchHtml_called != null
        ? A.marks.T5b_cacheApi_done - A.marks.T5_fetchHtml_called
        : A.cacheApi
          ? A.cacheApi.totalMs
          : null;
    A.waitAfterResponseMs =
      A.marks.T8_response_complete != null && A.marks.T14_page_interactive != null
        ? A.marks.T14_page_interactive - A.marks.T8_response_complete
        : A.marks.T11_dom_swap_end != null && A.fromCache
          ? A.marks.T14_page_interactive - A.marks.T11_dom_swap_end
          : null;
    A.networkTimeMs =
      A.marks.T8_response_complete != null && A.marks.T6_http_request_start != null
        ? A.marks.T8_response_complete - A.marks.T6_http_request_start
        : null;
    A.scriptsMs =
      A.marks.T12c_scripts_end != null && A.marks.T12b_scripts_start != null
        ? A.marks.T12c_scripts_end - A.marks.T12b_scripts_start
        : 0;
    A.totalMs = A.marks.T14_page_interactive - A.marks.T0_click;
    A.swController = !!(navigator.serviceWorker && navigator.serviceWorker.controller);
    A.hrefFinal = location.href;
    A.docSame = true; // soft path
    N.runs.push(A);
    const out = A;
    N.active = null;
    return out;
  };

  return true;
}

async function ensureInstrumented(page) {
  await page.waitForFunction(() => window.RatebNavInstant, { timeout: 45000 });
  // Re-install fresh each dashboard load
  await page.evaluate(installInstrumentation);
}

async function goDashboard(page) {
  await page.goto(BASE + '/admin/?company_id=22&_navrca=' + Date.now(), {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });
  await page.waitForFunction(
    () => window.RatebNavInstant && document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1',
    { timeout: 45000 }
  );
  await ensureInstrumented(page);
  await page.waitForTimeout(400);
}

async function ensureLink(page, route) {
  return page.evaluate((routeSpec) => {
    const matchers = routeSpec.match.map((s) => new RegExp(s.source, s.flags));
    const groupRe = new RegExp(routeSpec.group.source, routeSpec.group.flags);
    for (const btn of document.querySelectorAll('[data-nav-group-toggle]')) {
      if (!groupRe.test(btn.textContent || '')) continue;
      const group = btn.closest('[data-nav-group], .rateb-nav-group, li, details') || btn.parentElement;
      const open =
        group &&
        (group.classList.contains('is-open') || group.hasAttribute('open') || btn.getAttribute('aria-expanded') === 'true');
      if (!open) btn.click();
    }
    const links = [...document.querySelectorAll('a.rateb-nav-link[href], a[href]')];
    const isPosRegister = (pathname) => /\/admin\/ops\/pos$/i.test(pathname.replace(/\/+$/, ''));
    for (const re of matchers) {
      const hit = links.find((a) => {
        try {
          const u = new URL(a.href, location.href);
          if (isPosRegister(u.pathname)) return false;
          return re.test(u.pathname + u.search);
        } catch (e) {
          return false;
        }
      });
      if (hit) return { href: hit.href, text: (hit.textContent || '').trim().slice(0, 60) };
    }
    return null;
  }, {
    match: route.match.map((re) => ({ source: re.source, flags: re.flags })),
    group: { source: route.group.source, flags: route.group.flags },
  });
}

async function runOneNav(page, route, pass) {
  const onDash = await page.evaluate(() => /\/admin$/.test(location.pathname.replace(/\/+$/, '')));
  if (!onDash) await goDashboard(page);
  else await ensureInstrumented(page);

  const link = await ensureLink(page, route);
  if (!link) return { id: route.id, pass, error: 'link_not_found' };

  // Detect unexpected full document navigation (real hard nav)
  let hardNav = false;
  const onFrame = (frame) => {
    if (frame === page.mainFrame()) {
      // Same-document pushState also fires in Playwright — distinguish by document identity
    }
  };
  page.on('framenavigated', onFrame);

  const docToken = await page.evaluate(() => {
    const t = 'rca_' + Math.random().toString(36).slice(2);
    window.__RCA_DOC_TOKEN__ = t;
    return t;
  });

  await page.evaluate(
    ({ id, pass, match }) => {
      window.__NAV_RCA__.startRun({
        id,
        pass,
        matchers: match.map((m) => new RegExp(m.source, m.flags)),
      });
    },
    {
      id: route.id,
      pass,
      match: route.match.map((re) => ({ source: re.source, flags: re.flags })),
    }
  );

  const wall0 = Date.now();
  await page.evaluate((href) => {
    const a = [...document.querySelectorAll('a[href]')].find((el) => el.href === href);
    if (!a) throw new Error('anchor_missing');
    a.scrollIntoView({ block: 'center' });
    a.click();
  }, link.href);

  let result = null;
  let mode = 'soft';
  let error = null;
  try {
    result = await page.evaluate(async () => window.__NAV_RCA__.finishRun());
    const token = await page.evaluate(() => window.__RCA_DOC_TOKEN__ || null);
    if (token !== docToken) {
      hardNav = true;
      mode = 'hard_document';
    }
  } catch (e) {
    // Context destroyed => real hard navigation
    hardNav = true;
    mode = 'hard_destroyed';
    error = String(e && e.message ? e.message : e);
    try {
      await page.waitForLoadState('domcontentloaded', { timeout: 60000 });
      const nav = await page.evaluate(() => {
        const n = performance.getEntriesByType('navigation')[0];
        return n
          ? {
              duration: n.duration,
              ttfb: n.responseStart,
              responseEnd: n.responseEnd,
              loadEventEnd: n.loadEventEnd,
              transferSize: n.transferSize,
              decodedBodySize: n.decodedBodySize,
            }
          : null;
      });
      result = {
        id: route.id,
        pass,
        href: link.href,
        hardNav: true,
        marks: { T0_click: 0, T14_page_interactive: nav ? nav.loadEventEnd || nav.duration : Date.now() - wall0 },
        totalMs: nav ? nav.loadEventEnd || nav.duration : Date.now() - wall0,
        hardNavTiming: nav,
        htmlSize: nav && nav.decodedBodySize,
        fromCache: nav ? nav.transferSize === 0 && nav.decodedBodySize > 0 : null,
        network: { hardNav: true },
        hooks: [],
        asyncHooks: [],
        scripts: [],
        longTasksDuring: [],
        swController: false,
      };
    } catch (e2) {
      error = error + ' | ' + e2.message;
    }
  }

  page.off('framenavigated', onFrame);

  let extra = { promises: [], longTasks: [] };
  try {
    extra = await page.evaluate(() => ({
      promises: window.__NAV_RCA__ ? window.__NAV_RCA__.promises.slice() : [],
      longTasks: window.__NAV_RCA__ && window.__NAV_RCA__.runs.length
        ? window.__NAV_RCA__.runs[window.__NAV_RCA__.runs.length - 1].longTasksDuring || []
        : [],
      swController: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
      consoleNav: performance.getEntriesByType('mark').filter((m) => /rateb-nav/i.test(m.name)).slice(-3),
    }));
  } catch (e) { /* ignore */ }

  if (result && hardNav) result.hardNav = true;

  return {
    id: route.id,
    pass,
    link,
    result,
    mode,
    error,
    hardNav,
    wallMs: Date.now() - wall0,
    extra,
  };
}

function summarizeRun(run) {
  if (!run) return { error: 'empty' };
  if (run.error && !run.result) return { id: run.id, pass: run.pass, error: run.error };
  const A = run.result;
  if (!A || A.error) return { id: run.id, pass: run.pass, error: (A && A.error) || run.error || 'no_result', mode: run.mode };

  const waterfall = stageDurations(A.marks || {});
  const gaps = waterfall.filter((s) => s.stage_ms != null && s.observed && s.stage !== 'T0_click');
  const biggest = gaps.length ? gaps.reduce((a, b) => (a.stage_ms >= b.stage_ms ? a : b)) : null;

  return {
    id: run.id,
    pass: run.pass,
    mode: run.mode,
    href: A.href || (run.link && run.link.href),
    hrefFinal: A.hrefFinal,
    total_ms: r1(A.totalMs),
    wall_ms: run.wallMs,
    fromCache: A.fromCache,
    cacheHit_cacheApi: !!A.cacheHit,
    hardNav: !!A.hardNav || !!run.hardNav,
    swapOk: A.swapOk,
    htmlSize: A.htmlSize,
    networkTime_ms: r1(A.networkTimeMs),
    waitBeforeFetch_ms: r1(A.waitBeforeFetchMs),
    cacheApiWait_ms: r1(A.cacheApiWaitMs),
    waitAfterResponse_ms: r1(A.waitAfterResponseMs),
    scripts_ms: r1(A.scriptsMs),
    scripts: A.scripts || [],
    cacheApi_total_ms: r1(A.cacheApi && A.cacheApi.totalMs),
    cacheApi_detail: A.cacheApi,
    network: A.network,
    resourceTiming: A.resourceTiming,
    hardNavTiming: A.hardNavTiming,
    swController: A.swController != null ? A.swController : run.extra && run.extra.swController,
    spinner_shown: !!A.spinnerShown,
    spinner_ms:
      A.spinnerFirstAt != null
        ? r1((A.spinnerLastAt || A.marks.T14_page_interactive) - A.spinnerFirstAt)
        : 0,
    longTasks: A.longTasksDuring || (run.extra && run.extra.longTasks) || [],
    promises_gt_30ms: (run.extra && run.extra.promises) || [],
    asyncHooks: A.asyncHooks || [],
    hooks: A.hooks || [],
    beforeEnterNote: A.beforeEnterNote,
    waterfall,
    culprit_stage: biggest ? biggest.stage : null,
    culprit_ms: biggest ? biggest.stage_ms : null,
    marks: A.marks,
  };
}

function buildMarkdown(all) {
  const L = [];
  L.push('# Navigation Latency Waterfall RCA (Evidence Only)');
  L.push('');
  L.push('**Date:** ' + new Date().toISOString());
  L.push('');
  L.push('**Method:** Playwright in-page patches of live `erp-nav-instant.js` pipeline. No production code changes.');
  L.push('');
  L.push('**Note:** `beforeEnter` is not implemented in RatebNavInstant. **T12 = `beforeLeave`**.');
  L.push('');
  L.push('**Important:** Playwright `waitForNavigation`/`framenavigated` also fire on `history.pushState` (same-document). This run distinguishes soft vs hard by **document identity token**, not navigation events.');
  L.push('');

  L.push('## First vs second totals');
  L.push('');
  L.push('| Route | First ms | Second ms | First cache | Second cache | HTML bytes | Culprit (first) | Culprit ms | Net T6→T8 | Wait before fetch | Wait after | Scripts |');
  L.push('|-------|----------|-----------|-------------|--------------|------------|-----------------|------------|-----------|-------------------|------------|---------|');
  for (const route of ROUTES) {
    const f = all.find((r) => r.id === route.id && r.pass === 'first');
    const s = all.find((r) => r.id === route.id && r.pass === 'second');
    L.push(
      '| ' +
        route.id +
        ' | ' +
        (f && f.total_ms) +
        ' | ' +
        (s && s.total_ms) +
        ' | ' +
        (f && (f.fromCache ? 'HIT' : 'MISS')) +
        ' | ' +
        (s && (s.fromCache ? 'HIT' : 'MISS')) +
        ' | ' +
        (f && f.htmlSize) +
        ' | ' +
        (f && f.culprit_stage) +
        ' | ' +
        (f && f.culprit_ms) +
        ' | ' +
        (f && f.networkTime_ms) +
        ' | ' +
        (f && f.waitBeforeFetch_ms) +
        ' | ' +
        (f && f.waitAfterResponse_ms) +
        ' | ' +
        (f && f.scripts_ms) +
        ' |'
    );
  }
  L.push('');

  const firsts = all.filter((r) => r.pass === 'first' && r.total_ms != null);
  if (firsts.length) {
    const top = firsts.slice().sort((a, b) => (b.culprit_ms || 0) - (a.culprit_ms || 0))[0];
    L.push('## Single-stage verdict (first visits)');
    L.push('');
    L.push(
      'Largest stage gap on first visits: **' +
        top.culprit_stage +
        '** = **' +
        top.culprit_ms +
        ' ms** (' +
        top.id +
        ', total ' +
        top.total_ms +
        ' ms, fromCache=' +
        top.fromCache +
        ').'
    );
    L.push('');
    // Dominant across routes
    const byStage = {};
    for (const r of firsts) {
      const k = r.culprit_stage || 'unknown';
      byStage[k] = byStage[k] || { n: 0, max: 0 };
      byStage[k].n++;
      byStage[k].max = Math.max(byStage[k].max, r.culprit_ms || 0);
    }
    const dom = Object.entries(byStage).sort((a, b) => b[1].max - a[1].max)[0];
    L.push('Dominant culprit stage across modules: **' + dom[0] + '** (max ' + dom[1].max + ' ms, count ' + dom[1].n + ').');
    L.push('');
  }

  for (const run of all) {
    L.push('## ' + run.id + ' — ' + run.pass + ' visit');
    L.push('');
    if (run.error) {
      L.push('ERROR: ' + run.error);
      L.push('');
      continue;
    }
    L.push('- href: `' + (run.href || '') + '`');
    L.push('- total (T0→T14): **' + run.total_ms + ' ms**');
    L.push('- fromCache: **' + run.fromCache + '**');
    L.push('- Cache API match hit: ' + run.cacheHit_cacheApi);
    L.push('- SW controller: ' + run.swController);
    L.push('- HTML size: ' + run.htmlSize + ' bytes');
    L.push('- Network (T6→T8): ' + run.networkTime_ms + ' ms');
    L.push('- Wait BEFORE fetch (T5→T6 / Cache API): ' + run.waitBeforeFetch_ms + ' ms (cacheApiWait ' + run.cacheApiWait_ms + ')');
    L.push('- Wait AFTER response (→T14): ' + run.waitAfterResponse_ms + ' ms');
    L.push('- Module scripts chain: ' + run.scripts_ms + ' ms');
    L.push('- In-page spinner shown: ' + run.spinner_shown + ' (' + run.spinner_ms + ' ms)');
    L.push('- Hard document nav: ' + run.hardNav);
    L.push('- **Culprit stage: ' + run.culprit_stage + ' (' + run.culprit_ms + ' ms)**');
    L.push('');
    L.push('### Waterfall');
    L.push('');
    L.push('| Stage | t from click (ms) | Stage Δ (ms) | Observed |');
    L.push('|-------|-------------------|--------------|----------|');
    for (const row of run.waterfall || []) {
      const flag = run.culprit_stage === row.stage ? ' ← CULPRIT' : '';
      L.push('| ' + row.stage + ' | ' + row.t_ms + ' | ' + row.stage_ms + ' | ' + row.observed + flag + ' |');
    }
    L.push('');
    L.push('### Long tasks (>50ms)');
    L.push('');
    L.push(run.longTasks && run.longTasks.length ? '```json\n' + JSON.stringify(run.longTasks, null, 2) + '\n```' : 'None.');
    L.push('');
    L.push('### Promises >30ms');
    L.push('');
    L.push(
      run.promises_gt_30ms && run.promises_gt_30ms.length
        ? '```json\n' + JSON.stringify(run.promises_gt_30ms, null, 2) + '\n```'
        : 'None.'
    );
    L.push('');
    L.push('### Async hooks / scripts');
    L.push('');
    L.push('```json');
    L.push(JSON.stringify({ hooks: run.hooks, asyncHooks: run.asyncHooks, scripts: run.scripts }, null, 2));
    L.push('```');
    L.push('');
    L.push('### Network / Cache API detail');
    L.push('');
    L.push('```json');
    L.push(
      JSON.stringify(
        { network: run.network, resourceTiming: run.resourceTiming, cacheApi: run.cacheApi_detail },
        null,
        2
      )
    );
    L.push('```');
    L.push('');
  }
  return L.join('\n');
}

(async () => {
  const mint = JSON.parse(
    ssh(
      'php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint'
    )
  );

  // Fresh profile = cold Cache API / SW for first module; subsequent modules may warm within session
  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'nav-wf2-' + Date.now()), {
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

  const page = ctx.pages()[0] || (await ctx.newPage());
  const all = [];

  for (const route of ROUTES) {
    // Fresh context page load for each first visit from dashboard
    await goDashboard(page);
    all.push(summarizeRun(await runOneNav(page, route, 'first')));
    // Immediate second: back to dashboard then same module (Cache API should HIT)
    await goDashboard(page);
    all.push(summarizeRun(await runOneNav(page, route, 'second')));
  }

  fs.mkdirSync(path.dirname(OUT_JSON), { recursive: true });
  fs.writeFileSync(OUT_JSON, JSON.stringify({ generatedAt: new Date().toISOString(), runs: all }, null, 2));
  fs.writeFileSync(OUT_MD, buildMarkdown(all));
  console.log(OUT_JSON);
  console.log(OUT_MD);
  console.log(
    JSON.stringify(
      all.map((r) => ({
        id: r.id,
        pass: r.pass,
        total: r.total_ms,
        cache: r.fromCache,
        hardNav: r.hardNav,
        culprit: r.culprit_stage,
        culprit_ms: r.culprit_ms,
        net: r.networkTime_ms,
        waitBefore: r.waitBeforeFetch_ms,
        waitAfter: r.waitAfterResponse_ms,
        scripts: r.scripts_ms,
        spinner: r.spinner_ms,
        html: r.htmlSize,
      })),
      null,
      2
    )
  );
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
