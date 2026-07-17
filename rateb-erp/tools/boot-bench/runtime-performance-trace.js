/**
 * Runtime Performance Trace — Problem A (online warm→cold) + Problem B (offline boot stop).
 * Measure-only. No production asset changes. No architecture/security audits.
 */
'use strict';

const fs = require('fs');
const path = require('path');
const os = require('os');
const { chromium } = require('playwright');

const BASE = process.env.RATEB_V2_URL
  || 'https://rateb.sa/rateb-erp/public/v2/index.html';
const OUT = process.env.RATEB_RPT_OUT
  || path.join(__dirname, 'reports', `runtime-performance-trace-${Date.now()}.json`);
const MODULE = { id: 'inventory', route: '/inventory' };

function round(n) {
  return Number.isFinite(Number(n)) ? Math.round(Number(n) * 10) / 10 : null;
}

async function installTraceHooks(page) {
  await page.addInitScript(() => {
    const T = window.__RPT__ = {
      events: [],
      reloads: 0,
      runtimeStarts: 0,
      sqliteOpens: 0,
      idbOpens: 0,
      swFetches: 0,
      network: [],
      errors: [],
      bootStop: null,
    };
    const now = () => Math.round(performance.now() * 10) / 10;
    const push = (type, detail) => {
      T.events.push(Object.assign({ t: now(), type }, detail || {}));
    };
    T.push = push;

    // Full page reload / navigation detection
    window.addEventListener('pagehide', () => {
      T.reloads += 1;
      push('pagehide', {});
    });
    window.addEventListener('beforeunload', () => {
      push('beforeunload', {});
    });
    window.addEventListener('popstate', () => {
      push('history_popstate', { hash: location.hash });
    });
    window.addEventListener('hashchange', () => {
      push('hashchange', { hash: location.hash });
    });

    // Patch IndexedDB open
    const idbOpen = indexedDB.open.bind(indexedDB);
    indexedDB.open = function (name, version) {
      T.idbOpens += 1;
      push('indexeddb_open', { name: String(name), version: version });
      return idbOpen(name, version);
    };

    // Wrap fetch for SW/network observation in page
    const origFetch = window.fetch.bind(window);
    window.fetch = function () {
      const url = String(arguments[0] && arguments[0].url ? arguments[0].url : arguments[0]);
      push('fetch', { url: url.replace(/^https?:\/\/[^/]+/, '').slice(0, 180) });
      return origFetch.apply(this, arguments);
    };

    // Hook constructors after scripts load via property watchers
    function wrapOnce(objName, methodPath, label, counterKey) {
      let tries = 0;
      const tick = () => {
        tries += 1;
        try {
          const parts = methodPath.split('.');
          let cur = window[objName];
          if (!cur) {
            if (tries < 200) setTimeout(tick, 25);
            return;
          }
          for (let i = 0; i < parts.length - 1; i++) {
            cur = cur[parts[i]];
            if (!cur) {
              if (tries < 200) setTimeout(tick, 25);
              return;
            }
          }
          const name = parts[parts.length - 1];
          const orig = cur[name];
          if (typeof orig !== 'function' || orig.__rptWrapped) {
            if (tries < 200 && typeof orig !== 'function') setTimeout(tick, 25);
            return;
          }
          cur[name] = function () {
            if (counterKey) T[counterKey] += 1;
            const start = performance.now();
            push(label + '_start', {});
            const ret = orig.apply(this, arguments);
            return Promise.resolve(ret).then((v) => {
              push(label + '_end', { ms: Math.round((performance.now() - start) * 10) / 10 });
              return v;
            }, (err) => {
              push(label + '_fail', {
                ms: Math.round((performance.now() - start) * 10) / 10,
                error: String(err && err.message ? err.message : err).slice(0, 240),
              });
              throw err;
            });
          };
          cur[name].__rptWrapped = true;
          push('hooked', { target: objName + '.' + methodPath });
        } catch (e) {
          T.errors.push(String(e && e.message ? e.message : e));
        }
      };
      tick();
    }

    wrapOnce('RatebOfflineV2Runtime', 'start', 'runtime_start', 'runtimeStarts');
    wrapOnce('RatebOfflineV2DB', 'open', 'sqlite_open', 'sqliteOpens');

    // Defer deeper hooks until globals appear
    const deeper = setInterval(() => {
      const fw = window.RatebOfflineV2Business;
      const shell = window.RatebOfflineV2AppShell;
      const router = shell && shell.getRouter && shell.getRouter();
      if (fw && typeof fw.create === 'function' && !fw.create.__rptWrapped) {
        const origCreate = fw.create.bind(fw);
        fw.create = function () {
          push('business_framework_create', {});
          const instance = origCreate.apply(this, arguments);
          if (instance && typeof instance.register === 'function' && !instance.register.__rptWrapped) {
            const origReg = instance.register.bind(instance);
            instance.register = function (mod) {
              const id = mod && mod.metadata && mod.metadata.id;
              push('business_module_register', { id: id });
              return origReg(mod);
            };
            instance.register.__rptWrapped = true;
          }
          if (instance && typeof instance.activate === 'function' && !instance.activate.__rptWrapped) {
            const origAct = instance.activate.bind(instance);
            instance.activate = function (id) {
              const start = performance.now();
              push('module_activate_start', { id: id });
              return Promise.resolve(origAct(id)).then((v) => {
                push('module_activate_end', {
                  id: id,
                  ms: Math.round((performance.now() - start) * 10) / 10,
                  ok: !!(v && v.ok !== false),
                });
                return v;
              }, (err) => {
                push('module_activate_fail', {
                  id: id,
                  ms: Math.round((performance.now() - start) * 10) / 10,
                  error: String(err && err.message ? err.message : err).slice(0, 240),
                });
                throw err;
              });
            };
            instance.activate.__rptWrapped = true;
          }
          return instance;
        };
        fw.create.__rptWrapped = true;
      }
      if (router && typeof router.navigate === 'function' && !router.navigate.__rptWrapped) {
        const origNav = router.navigate.bind(router);
        router.navigate = function (to, opts) {
          const start = performance.now();
          push('router_navigate_start', { to: to, opts: opts || null });
          return Promise.resolve(origNav(to, opts)).then((v) => {
            push('router_navigate_end', {
              to: to,
              ms: Math.round((performance.now() - start) * 10) / 10,
              ok: !!(v && v.ok),
              reason: v && v.reason ? v.reason : null,
            });
            return v;
          }, (err) => {
            push('router_navigate_fail', {
              to: to,
              ms: Math.round((performance.now() - start) * 10) / 10,
              error: String(err && err.message ? err.message : err).slice(0, 240),
            });
            throw err;
          });
        };
        router.navigate.__rptWrapped = true;
        push('hooked', { target: 'router.navigate' });
      }
      if (window.RatebOfflineV2DB && window.RatebOfflineV2Runtime) {
        // keep interval until both exist; clear after shell ready attr
      }
      if (document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1') {
        clearInterval(deeper);
      }
    }, 30);

    // Capture console errors that may mark boot stop
    window.addEventListener('error', (ev) => {
      push('window_error', { message: String(ev.message || '').slice(0, 240) });
    });
    window.addEventListener('unhandledrejection', (ev) => {
      push('unhandledrejection', {
        message: String(ev.reason && ev.reason.message ? ev.reason.message : ev.reason).slice(0, 240),
      });
    });
  });
}

async function collectSnapshot(page, label) {
  return page.evaluate((phase) => {
    const T = window.__RPT__ || { events: [] };
    const marks = (performance.getEntriesByType('mark') || []).map((m) => ({
      name: m.name,
      at_ms: Math.round(m.startTime * 10) / 10,
    }));
    const outlet = document.getElementById('rateb-v2-shell-outlet');
    const bootStatus = (document.getElementById('boot-status') || {}).textContent || null;
    return {
      phase,
      href: location.href,
      hash: location.hash,
      boot_status: bootStatus,
      shell_ready: document.documentElement.getAttribute('data-rateb-v2-shell-ready'),
      route_ready: document.documentElement.getAttribute('data-rateb-v2-route-ready'),
      active_module: document.documentElement.getAttribute('data-rateb-v2-active-module'),
      outlet_route: outlet ? outlet.getAttribute('data-route') : null,
      outlet_text: outlet ? String(outlet.textContent || '').trim().slice(0, 200) : null,
      counts: {
        runtimeStarts: T.runtimeStarts,
        sqliteOpens: T.sqliteOpens,
        idbOpens: T.idbOpens,
        swFetches: T.swFetches,
        reloads: T.reloads,
      },
      marks,
      events: T.events.slice(),
      errors: T.errors || [],
      globals: {
        runtime: !!window.RatebOfflineV2Runtime,
        db: !!window.RatebOfflineV2DB,
        dbOpen: !!(window.RatebOfflineV2DB && window.RatebOfflineV2DB.isOpen && window.RatebOfflineV2DB.isOpen()),
        identity: !!window.RatebOfflineV2Identity,
        inventory: !!window.RatebOfflineV2Inventory,
        activeBusiness: !!window.RatebOfflineV2ActiveBusiness,
        swController: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
        onLine: navigator.onLine,
      },
    };
  }, label);
}

function summarizeProblemA(coldDoc, softWarm, leaveReturnSoft, leaveReturnHard) {
  const softMs = leaveReturnSoft && leaveReturnSoft.nav_ms;
  const hardMs = leaveReturnHard && leaveReturnHard.nav_ms;
  const hardRuntime = leaveReturnHard && leaveReturnHard.snapshot
    && leaveReturnHard.snapshot.counts.runtimeStarts;
  const hardSqlite = leaveReturnHard && leaveReturnHard.snapshot
    && leaveReturnHard.snapshot.counts.sqliteOpens;
  const softRuntimeDelta = leaveReturnSoft && leaveReturnSoft.runtime_starts_delta;
  const softSqliteDelta = leaveReturnSoft && leaveReturnSoft.sqlite_opens_delta;

  return {
    cold_doc_ms: coldDoc && coldDoc.wall_ms,
    soft_warm_ms: softWarm && softWarm.nav_ms,
    leave_return_soft_ms: softMs,
    leave_return_hard_reload_ms: hardMs,
    soft_leave_runtime_starts_delta: softRuntimeDelta,
    soft_leave_sqlite_opens_delta: softSqliteDelta,
    hard_reload_runtime_starts: hardRuntime,
    hard_reload_sqlite_opens: hardSqlite,
    soft_stays_warm: softMs != null && softMs < 50 && softRuntimeDelta === 0 && softSqliteDelta === 0,
    hard_reloads_become_cold: hardMs != null && hardMs > 100 && hardRuntime >= 1 && hardSqlite >= 1,
  };
}

(async () => {
  const report = {
    phase: 'RUNTIME_PERFORMANCE_TRACE',
    generated_at: new Date().toISOString(),
    base: BASE,
    problem_a: null,
    problem_b: null,
  };

  const profileDir = path.join(os.tmpdir(), 'rateb-rpt-' + Date.now());
  fs.mkdirSync(profileDir, { recursive: true });

  const context = await chromium.launchPersistentContext(profileDir, {
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--disable-dev-shm-usage', '--disable-blink-features=AutomationControlled'],
    serviceWorkers: 'allow',
  });

  const networkLog = [];
  context.on('request', (req) => {
    networkLog.push({
      t: Date.now(),
      type: 'request',
      method: req.method(),
      resourceType: req.resourceType(),
      url: req.url().replace(/^https?:\/\/[^/]+/, '').slice(0, 200),
    });
  });
  context.on('response', (res) => {
    networkLog.push({
      t: Date.now(),
      type: 'response',
      status: res.status(),
      fromServiceWorker: res.fromServiceWorker(),
      url: res.url().replace(/^https?:\/\/[^/]+/, '').slice(0, 200),
    });
  });

  // Warm SW/host once
  const warm = context.pages()[0] || await context.newPage();
  await warm.goto(BASE, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await warm.waitForFunction(
    () => document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1',
    null,
    { timeout: 90000 }
  ).catch(() => null);
  await warm.evaluate(async () => {
    if (navigator.serviceWorker) await navigator.serviceWorker.ready;
  }).catch(() => null);
  await warm.waitForTimeout(500);
  await warm.close();

  // -------- Problem A --------
  const pageA = await context.newPage();
  const pageErrorsA = [];
  pageA.on('pageerror', (e) => pageErrorsA.push(String(e.message || e)));
  await installTraceHooks(pageA);

  // A1: cold document open of module
  const coldStart = Date.now();
  await pageA.goto(`${BASE}#${MODULE.route}`, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await pageA.waitForFunction(
    () => document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1',
    null,
    { timeout: 90000 }
  );
  await pageA.waitForFunction((route) => {
    const outlet = document.getElementById('rateb-v2-shell-outlet');
    const active = document.documentElement.getAttribute('data-rateb-v2-active-module');
    return (outlet && String(outlet.getAttribute('data-route') || '').indexOf(route.slice(1)) === 0)
      || active === 'inventory'
      || document.documentElement.getAttribute('data-rateb-v2-route-ready') === '1';
  }, MODULE.route, { timeout: 90000 }).catch(() => null);
  // Wait for background platform/module
  await pageA.waitForTimeout(2500);
  const coldSnap = await collectSnapshot(pageA, 'A1_cold_document');
  const coldDoc = { wall_ms: Date.now() - coldStart, snapshot: coldSnap };

  // A2: soft warm leave/return (Home → module) same document
  const beforeSoft = await pageA.evaluate(() => ({
    runtimeStarts: window.__RPT__.runtimeStarts,
    sqliteOpens: window.__RPT__.sqliteOpens,
  }));
  const softWarm = await pageA.evaluate(async (route) => {
    const shell = window.RatebOfflineV2AppShell;
    const router = shell && shell.getRouter && shell.getRouter();
    if (!router) return { ok: false, error: 'no_router' };
    await router.navigate('/', { replace: true });
    const start = performance.now();
    const res = await router.navigate(route, { replace: true });
    return {
      ok: !!(res && res.ok),
      nav_ms: Math.round((performance.now() - start) * 10) / 10,
      reason: res && res.reason ? res.reason : null,
    };
  }, MODULE.route);
  const afterSoft = await pageA.evaluate(() => ({
    runtimeStarts: window.__RPT__.runtimeStarts,
    sqliteOpens: window.__RPT__.sqliteOpens,
    events: window.__RPT__.events.filter((e) =>
      /runtime_start|sqlite_open|router_navigate|module_activate|business_module|pagehide|beforeunload|hashchange/.test(e.type)
    ).slice(-40),
  }));
  softWarm.runtime_starts_delta = afterSoft.runtimeStarts - beforeSoft.runtimeStarts;
  softWarm.sqlite_opens_delta = afterSoft.sqliteOpens - beforeSoft.sqliteOpens;
  softWarm.events = afterSoft.events;
  const softSnap = await collectSnapshot(pageA, 'A2_soft_leave_return');

  // A3: hard leave/return — full document reload to same module
  const hardStart = Date.now();
  await pageA.goto(`${BASE}#${MODULE.route}`, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await pageA.waitForFunction(
    () => document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1',
    null,
    { timeout: 90000 }
  );
  await pageA.waitForTimeout(2500);
  const hardSnap = await collectSnapshot(pageA, 'A3_hard_reload_return');
  const leaveReturnHard = {
    nav_ms: Date.now() - hardStart,
    snapshot: hardSnap,
    page_errors: pageErrorsA.slice(),
  };

  // Detect whether soft leave triggers dispose / Runtime restart in UI clicks
  // Re-open same page for click path
  await pageA.goto(`${BASE}#${MODULE.route}`, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await pageA.waitForFunction(
    () => document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1',
    null,
    { timeout: 90000 }
  );
  await pageA.waitForTimeout(2000);
  const beforeClick = await pageA.evaluate(() => {
    window.__RPT__.events = [];
    return {
      runtimeStarts: window.__RPT__.runtimeStarts,
      sqliteOpens: window.__RPT__.sqliteOpens,
    };
  });
  // Click Home then Inventory in shell nav if present
  const clickPath = await pageA.evaluate(async () => {
    const nav = document.querySelector('#rateb-v2-shell-nav, [data-rateb-v2-nav], nav');
    const buttons = Array.from(document.querySelectorAll('[data-route-id], a[href^="#/"]'));
    const home = buttons.find((b) =>
      (b.getAttribute('data-route-id') === 'home')
      || (b.getAttribute('href') === '#/')
      || /home|الرئيسية/i.test(b.textContent || '')
    );
    const inv = buttons.find((b) =>
      (b.getAttribute('data-route-id') === 'inventory')
      || (b.getAttribute('href') === '#/inventory')
      || /inventory|مخزون/i.test(b.textContent || '')
    );
    const result = { homeFound: !!home, invFound: !!inv, navHtml: nav ? nav.outerHTML.slice(0, 400) : null };
    if (home) home.click();
    await new Promise((r) => setTimeout(r, 200));
    const start = performance.now();
    if (inv) inv.click();
    else {
      const shell = window.RatebOfflineV2AppShell;
      const router = shell && shell.getRouter && shell.getRouter();
      if (router) await router.navigate('/inventory');
    }
    await new Promise((r) => setTimeout(r, 300));
    result.nav_ms = Math.round((performance.now() - start) * 10) / 10;
    result.events = (window.__RPT__.events || []).slice();
    result.runtime_starts_delta = window.__RPT__.runtimeStarts - 0;
    return result;
  });
  const afterClick = await pageA.evaluate(() => ({
    runtimeStarts: window.__RPT__.runtimeStarts,
    sqliteOpens: window.__RPT__.sqliteOpens,
  }));
  clickPath.runtime_starts_delta = afterClick.runtimeStarts - beforeClick.runtimeStarts;
  clickPath.sqlite_opens_delta = afterClick.sqliteOpens - beforeClick.sqliteOpens;

  // Evidence: marks showing Runtime/SQLite cost on hard reload vs soft
  function extractCosts(snap) {
    const marks = (snap && snap.marks) || [];
    const at = (name) => {
      const m = marks.find((x) => x.name === name);
      return m ? m.at_ms : null;
    };
    return {
      shell_ready_ms: at('rateb-v2-shell-ready'),
      db_ready_ms: at('rateb-v2-db-ready'),
      background_start_ms: at('rateb-v2-background-start'),
      platform_done_ms: at('rateb-v2-background-platform-done'),
      active_module_ms: at('rateb-v2-active-module-ready'),
      route_ready_ms: at('rateb-v2-route-ready'),
      runtime_start_events: ((snap && snap.events) || []).filter((e) => e.type.indexOf('runtime_start') === 0),
      sqlite_open_events: ((snap && snap.events) || []).filter((e) => e.type.indexOf('sqlite_open') === 0),
      module_activate_events: ((snap && snap.events) || []).filter((e) => e.type.indexOf('module_activate') === 0),
      router_events: ((snap && snap.events) || []).filter((e) => e.type.indexOf('router_navigate') === 0),
      pagehide_events: ((snap && snap.events) || []).filter((e) => e.type === 'pagehide' || e.type === 'beforeunload'),
      counts: snap && snap.counts,
    };
  }

  report.problem_a = {
    summary: summarizeProblemA(coldDoc, softWarm, softWarm, leaveReturnHard),
    cold_document: {
      wall_ms: coldDoc.wall_ms,
      costs: extractCosts(coldSnap),
      snapshot: {
        boot_status: coldSnap.boot_status,
        active_module: coldSnap.active_module,
        outlet_route: coldSnap.outlet_route,
        outlet_text: coldSnap.outlet_text,
        counts: coldSnap.counts,
        globals: coldSnap.globals,
      },
    },
    soft_leave_return: {
      ...softWarm,
      costs: extractCosts(softSnap),
      snapshot: {
        outlet_route: softSnap.outlet_route,
        counts: softSnap.counts,
      },
    },
    ui_click_leave_return: clickPath,
    hard_document_return: {
      wall_ms: leaveReturnHard.nav_ms,
      costs: extractCosts(hardSnap),
      snapshot: {
        boot_status: hardSnap.boot_status,
        active_module: hardSnap.active_module,
        outlet_route: hardSnap.outlet_route,
        counts: hardSnap.counts,
        globals: hardSnap.globals,
      },
      page_errors: leaveReturnHard.page_errors,
    },
  };

  // -------- Problem B: Offline ERP does not open --------
  const pageB = await context.newPage();
  const pageErrorsB = [];
  const consoleB = [];
  pageB.on('pageerror', (e) => pageErrorsB.push(String(e.message || e)));
  pageB.on('console', (msg) => {
    if (msg.type() === 'error' || msg.type() === 'warning') {
      consoleB.push({ type: msg.type(), text: String(msg.text()).slice(0, 300) });
    }
  });
  await installTraceHooks(pageB);

  // Ensure SW controlling while online first
  await pageB.goto(BASE, { waitUntil: 'domcontentloaded', timeout: 90000 });
  await pageB.waitForFunction(
    () => document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1',
    null,
    { timeout: 90000 }
  ).catch(() => null);
  await pageB.evaluate(async () => {
    if (navigator.serviceWorker) await navigator.serviceWorker.ready;
  }).catch(() => null);
  await pageB.waitForTimeout(800);

  // Go offline and hard-open Offline ERP
  await context.setOffline(true);
  const offlineStart = Date.now();
  let offlineGotoError = null;
  try {
    await pageB.goto(`${BASE}#/`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  } catch (e) {
    offlineGotoError = String(e && e.message ? e.message : e);
  }

  // Wait briefly for either shell ready or stuck boot
  let offlineReady = false;
  try {
    await pageB.waitForFunction(
      () => document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1'
        || /fail|error|Boot failed/i.test((document.getElementById('boot-status') || {}).textContent || ''),
      null,
      { timeout: 45000 }
    );
    offlineReady = await pageB.evaluate(
      () => document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1'
    );
  } catch (_) {
    offlineReady = false;
  }

  const offlineSnap = await collectSnapshot(pageB, 'B_offline_boot').catch(async () => {
    // page may not have executed init script if navigation failed hard
    return {
      phase: 'B_offline_boot',
      href: pageB.url(),
      boot_status: null,
      shell_ready: null,
      events: [],
      errors: [],
      globals: {},
      capture_error: 'snapshot_failed',
    };
  });

  // Determine first blocking failure from events / boot status / network
  const offlineNetwork = networkLog.filter((n) => n.t >= offlineStart - 1000).slice(-80);
  const failedResponses = offlineNetwork.filter((n) =>
    n.type === 'response' && (n.status >= 400 || n.status === 0)
  );
  const failedRequests = [];
  // Also probe SW registrations
  const swInfo = await pageB.evaluate(async () => {
    if (!navigator.serviceWorker) return { supported: false };
    const regs = await navigator.serviceWorker.getRegistrations();
    return {
      supported: true,
      controller: !!(navigator.serviceWorker.controller),
      controllerURL: navigator.serviceWorker.controller
        ? navigator.serviceWorker.controller.scriptURL
        : null,
      registrations: regs.map((r) => ({
        scope: r.scope,
        active: !!(r.active),
        installing: !!(r.installing),
        waiting: !!(r.waiting),
        scriptURL: r.active ? r.active.scriptURL : null,
      })),
    };
  }).catch((e) => ({ error: String(e.message || e) }));

  const bootStatusText = offlineSnap.boot_status || '';
  const failEvents = (offlineSnap.events || []).filter((e) =>
    /_fail$|window_error|unhandledrejection/.test(e.type)
  );
  const firstFail = failEvents[0] || null;

  // Stage ladder
  const stages = {
    service_worker: swInfo,
    document_loaded: !offlineGotoError,
    offline_goto_error: offlineGotoError,
    boot_status: bootStatusText,
    shell_ready: offlineSnap.shell_ready === '1',
    runtime: !!(offlineSnap.globals && offlineSnap.globals.runtime),
    db: !!(offlineSnap.globals && offlineSnap.globals.db),
    db_open: !!(offlineSnap.globals && offlineSnap.globals.dbOpen),
    identity: !!(offlineSnap.globals && offlineSnap.globals.identity),
    active_business: !!(offlineSnap.globals && offlineSnap.globals.activeBusiness),
    interactive: offlineSnap.shell_ready === '1' || offlineSnap.route_ready === '1',
  };

  let firstBlock = null;
  if (offlineGotoError && !offlineReady) {
    firstBlock = {
      stage: 'document_navigation_or_sw_fetch',
      evidence: offlineGotoError,
    };
  } else if (!swInfo.controller && !offlineReady) {
    firstBlock = {
      stage: 'service_worker',
      evidence: 'No SW controller while offline; document/assets cannot be served',
      swInfo,
    };
  } else if (/Boot failed|Platform script missing|fail/i.test(bootStatusText) && !offlineReady) {
    firstBlock = {
      stage: 'boot',
      evidence: bootStatusText,
    };
  } else if (firstFail) {
    firstBlock = {
      stage: firstFail.type,
      evidence: firstFail,
    };
  } else if (!offlineReady) {
    firstBlock = {
      stage: 'boot_hang',
      evidence: {
        boot_status: bootStatusText,
        marks: offlineSnap.marks,
        last_events: (offlineSnap.events || []).slice(-20),
      },
    };
  } else {
    // Shell opened offline — check deeper module path
    firstBlock = {
      stage: 'none_shell_opened',
      evidence: {
        boot_status: bootStatusText,
        shell_ready: true,
        note: 'Offline shell reached ready; checking module activation path',
      },
    };
  }

  // If shell opened, try module deep-link offline
  let offlineModule = null;
  if (offlineReady) {
    await pageB.goto(`${BASE}#${MODULE.route}`, { waitUntil: 'domcontentloaded', timeout: 60000 }).catch((e) => {
      offlineModule = { goto_error: String(e.message || e) };
    });
    await pageB.waitForTimeout(3000);
    const modSnap = await collectSnapshot(pageB, 'B_offline_module').catch(() => null);
    const modFails = ((modSnap && modSnap.events) || []).filter((e) => /_fail$|window_error|unhandledrejection/.test(e.type));
    offlineModule = {
      snapshot: modSnap && {
        boot_status: modSnap.boot_status,
        shell_ready: modSnap.shell_ready,
        active_module: modSnap.active_module,
        outlet_route: modSnap.outlet_route,
        outlet_text: modSnap.outlet_text,
        counts: modSnap.counts,
        globals: modSnap.globals,
        marks: modSnap.marks,
      },
      first_fail: modFails[0] || null,
      all_fails: modFails.slice(0, 10),
    };
    if (firstBlock.stage === 'none_shell_opened' && modFails[0]) {
      firstBlock = {
        stage: 'module_activation',
        evidence: modFails[0],
      };
    } else if (firstBlock.stage === 'none_shell_opened'
      && modSnap && modSnap.active_module == null
      && modSnap.outlet_route && modSnap.outlet_route !== 'inventory') {
      firstBlock = {
        stage: 'module_activation',
        evidence: {
          active_module: modSnap.active_module,
          outlet_route: modSnap.outlet_route,
          outlet_text: modSnap.outlet_text,
          note: 'Shell ready offline but target module did not become active',
        },
      };
    }
  }

  report.problem_b = {
    offline_ready: offlineReady,
    wall_ms: Date.now() - offlineStart,
    first_blocking_failure: firstBlock,
    stages,
    offline_goto_error: offlineGotoError,
    page_errors: pageErrorsB,
    console: consoleB.slice(0, 40),
    failed_responses: failedResponses.slice(0, 30),
    offline_module: offlineModule,
    snapshot: {
      boot_status: offlineSnap.boot_status,
      shell_ready: offlineSnap.shell_ready,
      marks: offlineSnap.marks,
      counts: offlineSnap.counts,
      globals: offlineSnap.globals,
      events_tail: (offlineSnap.events || []).slice(-40),
      errors: offlineSnap.errors,
    },
  };

  await context.close();
  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({
    out: OUT,
    problem_a_summary: report.problem_a.summary,
    problem_b_first_block: report.problem_b.first_blocking_failure,
    problem_b_offline_ready: report.problem_b.offline_ready,
    problem_b_boot_status: report.problem_b.snapshot.boot_status,
  }, null, 2));
})().catch((err) => {
  console.error(err);
  process.exit(2);
});
