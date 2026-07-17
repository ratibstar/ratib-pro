/**
 * Phase PX — Offline V2 vs Online ERP architecture audit (read-only).
 * Does not modify production assets. Captures paints, long tasks, marks,
 * resources, storage probes, and comparative usability timing.
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const V2_URL = process.env.RATEB_V2_URL || 'https://rateb.sa/rateb-erp/public/v2/index.html';
const V2_ROUTE_URL = process.env.RATEB_V2_ROUTE_URL || V2_URL + '#/inventory';
const ONLINE_URL = process.env.RATEB_ONLINE_URL || 'https://rateb.sa/admin';
const ONLINE_LOGIN_URL = process.env.RATEB_ONLINE_LOGIN_URL || 'https://rateb.sa/login';
const OUT = process.env.RATEB_PX_OUT
  || path.join(__dirname, 'reports', `phase-px-architecture-${Date.now()}.json`);

function round(n) {
  return Number.isFinite(Number(n)) ? Math.round(Number(n) * 10) / 10 : null;
}

async function attachObservers(page) {
  await page.addInitScript(() => {
    window.__px = {
      longTasks: [],
      events: [],
      promiseSpans: [],
      scriptEval: [],
    };
    try {
      new PerformanceObserver((list) => {
        list.getEntries().forEach((e) => {
          window.__px.longTasks.push({
            start_ms: Math.round(e.startTime * 10) / 10,
            duration_ms: Math.round(e.duration * 10) / 10,
            name: e.name || 'longtask',
          });
        });
      }).observe({ type: 'longtask', buffered: true });
    } catch (_) { /* optional */ }

    const origDispatch = EventTarget.prototype.dispatchEvent;
    EventTarget.prototype.dispatchEvent = function (ev) {
      try {
        if (ev && typeof ev.type === 'string' && (
          ev.type.indexOf('rateb') !== -1 ||
          ev.type.indexOf('router:') !== -1 ||
          ev.type === 'DOMContentLoaded'
        )) {
          window.__px.events.push({
            type: ev.type,
            at_ms: Math.round(performance.now() * 10) / 10,
          });
        }
      } catch (_) { /* ignore */ }
      return origDispatch.call(this, ev);
    };
  });
}

async function collectPageMetrics(page, label, wallStart) {
  return page.evaluate(async ({ runLabel, started }) => {
    const nav = performance.getEntriesByType('navigation')[0] || {};
    const paints = performance.getEntriesByType('paint') || [];
    const paint = (name) => {
      const hit = paints.find((p) => p.name === name);
      return hit ? Math.round(hit.startTime * 10) / 10 : null;
    };
    const marks = (performance.getEntriesByType('mark') || [])
      .filter((m) => /^rateb-v2-/.test(m.name) || /^rateb-/.test(m.name))
      .map((m) => ({ name: m.name, start_ms: Math.round(m.startTime * 10) / 10 }));
    const mark = (name) => {
      const hit = marks.find((m) => m.name === name);
      return hit ? hit.start_ms : null;
    };
    const resources = (performance.getEntriesByType('resource') || []).map((r) => ({
      name: r.name.replace(/^https?:\/\/[^/]+/, ''),
      type: r.initiatorType,
      start_ms: Math.round(r.startTime * 10) / 10,
      duration_ms: Math.round(r.duration * 10) / 10,
      transfer_bytes: r.transferSize || 0,
      decoded_bytes: r.decodedBodySize || 0,
    })).sort((a, b) => b.duration_ms - a.duration_ms);

    let idbEstimate = null;
    try {
      if (navigator.storage && navigator.storage.estimate) {
        const est = await navigator.storage.estimate();
        idbEstimate = {
          usage: est.usage || 0,
          quota: est.quota || 0,
        };
      }
    } catch (_) { /* ignore */ }

    let opfsProbe = null;
    try {
      if (navigator.storage && navigator.storage.getDirectory) {
        const t0 = performance.now();
        const root = await navigator.storage.getDirectory();
        let entries = 0;
        // eslint-disable-next-line no-restricted-syntax
        for await (const _ of root.values()) {
          entries += 1;
          if (entries > 40) break;
        }
        opfsProbe = {
          ok: true,
          sample_entries: entries,
          duration_ms: Math.round((performance.now() - t0) * 10) / 10,
        };
      }
    } catch (e) {
      opfsProbe = { ok: false, error: String(e && e.message || e) };
    }

    const scripts = Array.from(document.scripts).map((s) => s.src || '(inline)');
    const shellReady = document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1';
    const routeReady = document.documentElement.getAttribute('data-rateb-v2-route-ready') === '1';
    const interactive = document.documentElement.getAttribute('data-rateb-v2-interactive') === '1';
    const outlet = document.getElementById('rateb-v2-shell-outlet');
    const bodyText = (document.body && document.body.innerText || '').slice(0, 240);

    // Approximate JS main-thread cost from long tasks + script download/eval windows.
    const longTasks = (window.__px && window.__px.longTasks) || [];
    const longTaskTotal = longTasks.reduce((sum, t) => sum + (t.duration_ms || 0), 0);

    const layer = {
      hci_ready_ms: mark('rateb-v2-hci-ready'),
      interactive_ms: mark('rateb-v2-interactive-ready'),
      shell_rendered_ms: mark('rateb-v2-shell-rendered'),
      runtime_ready_ms: mark('rateb-v2-runtime-ready'),
      router_ready_ms: mark('rateb-v2-router-ready'),
      shell_ready_ms: mark('rateb-v2-shell-ready'),
      route_ready_ms: mark('rateb-v2-route-ready'),
      pm_ready_ms: mark('rateb-v2-pm-ready'),
      db_ready_ms: mark('rateb-v2-db-ready'),
      background_platform_ready_ms: mark('rateb-v2-background-platform-ready'),
      background_platform_done_ms: mark('rateb-v2-background-platform-done'),
      active_module_ready_ms: mark('rateb-v2-active-module-ready'),
      background_ready_ms: mark('rateb-v2-background-ready'),
      sw_ready_ms: mark('rateb-v2-sw-ready'),
    };

    return {
      label: runLabel,
      wall_ms: Date.now() - started,
      url: location.href,
      title: document.title,
      body_preview: bodyText,
      navigation: {
        type: nav.type || null,
        ttfb_ms: nav.responseStart ? Math.round(nav.responseStart * 10) / 10 : null,
        response_end_ms: nav.responseEnd ? Math.round(nav.responseEnd * 10) / 10 : null,
        dom_interactive_ms: nav.domInteractive ? Math.round(nav.domInteractive * 10) / 10 : null,
        dom_content_loaded_ms: nav.domContentLoadedEventEnd
          ? Math.round(nav.domContentLoadedEventEnd * 10) / 10 : null,
        load_ms: nav.loadEventEnd ? Math.round(nav.loadEventEnd * 10) / 10 : null,
        transfer_bytes: nav.transferSize || 0,
        decoded_bytes: nav.decodedBodySize || 0,
      },
      first_paint_ms: paint('first-paint'),
      first_contentful_paint_ms: paint('first-contentful-paint'),
      shell_ready: shellReady,
      route_ready: routeReady,
      interactive,
      active_route: outlet ? outlet.getAttribute('data-route') : null,
      layer,
      marks,
      top_resources: resources.slice(0, 25),
      resource_count: resources.length,
      script_count: scripts.length,
      scripts: scripts.slice(0, 40),
      long_tasks: longTasks,
      long_task_total_ms: Math.round(longTaskTotal * 10) / 10,
      events: (window.__px && window.__px.events) || [],
      storage_estimate: idbEstimate,
      opfs_probe: opfsProbe,
      globals: {
        hasHCI: !!window.RatebOfflineV2HCI,
        hasPM: !!window.RatebOfflineV2PM,
        hasDB: !!window.RatebOfflineV2DB,
        hasRuntime: !!window.RatebOfflineV2Runtime,
        hasRouter: !!window.RatebOfflineV2Router,
        hasShell: !!window.RatebOfflineV2Shell,
        hasSync: !!window.RatebOfflineV2Sync,
        hasSDK: !!window.RatebOfflineV2Modules,
        hasBusiness: !!window.RatebOfflineV2Business,
        hasIdentity: !!window.RatebOfflineV2Identity,
        hasInventory: !!window.RatebOfflineV2Inventory,
        dbOpen: !!(window.RatebOfflineV2DB && window.RatebOfflineV2DB.isOpen && window.RatebOfflineV2DB.isOpen()),
        dbMode: window.RatebOfflineV2DB && window.RatebOfflineV2DB.getMode
          ? window.RatebOfflineV2DB.getMode() : null,
        runtimeState: window.RatebOfflineV2Runtime && window.RatebOfflineV2Runtime.getState
          ? window.RatebOfflineV2Runtime.getState() : null,
      },
    };
  }, { runLabel: label, started: wallStart });
}

async function profileUrl(context, url, label, waitFn) {
  const page = await context.newPage();
  await attachObservers(page);
  const t0 = Date.now();
  let navError = null;
  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  } catch (e) {
    navError = String(e && e.message || e);
  }
  if (typeof waitFn === 'function') {
    try {
      await waitFn(page);
    } catch (e) {
      navError = (navError ? navError + ' | ' : '') + String(e && e.message || e);
    }
  } else {
    await page.waitForTimeout(1500);
  }
  const metrics = await collectPageMetrics(page, label, t0);
  metrics.nav_error = navError;
  await page.close();
  return metrics;
}

async function waitV2Shell(page) {
  await page.waitForFunction(() => {
    return document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1';
  }, { timeout: 20000 });
  await page.waitForFunction(() => {
    return performance.getEntriesByName('rateb-v2-background-ready').length > 0
      || document.documentElement.getAttribute('data-rateb-v2-route-ready') === '1';
  }, { timeout: 20000 }).catch(() => null);
  await page.waitForTimeout(800);
}

async function waitOnlineUsable(page) {
  // Online ERP: usable when main content or login form is present.
  await page.waitForFunction(() => {
    const text = (document.body && document.body.innerText) || '';
    const hasLogin = !!document.querySelector('input[type="password"], form[action*="login"]');
    const hasAdmin = !!document.querySelector('#app, .erp-shell, [data-rateb-connection-status], nav, .sidebar');
    const hasContent = text.length > 80;
    return hasLogin || hasAdmin || hasContent;
  }, { timeout: 20000 });
  await page.waitForTimeout(800);
}

(async () => {
  const userDataDir = path.join(__dirname, '.chrome-user-data', `phase-px-${Date.now()}`);
  fs.mkdirSync(userDataDir, { recursive: true });
  const context = await chromium.launchPersistentContext(userDataDir, {
    headless: true,
    args: ['--disable-blink-features=AutomationControlled'],
  });

  // Warm Online once so SW/cookie path settles, then measure cold-ish and warm.
  const onlineLoginCold = await profileUrl(context, ONLINE_LOGIN_URL, 'online_login_cold', waitOnlineUsable);
  const onlineLoginWarm = await profileUrl(context, ONLINE_LOGIN_URL, 'online_login_warm', waitOnlineUsable);
  const onlineAdminCold = await profileUrl(context, ONLINE_URL, 'online_admin_cold', waitOnlineUsable);
  const onlineAdminWarm = await profileUrl(context, ONLINE_URL, 'online_admin_warm', waitOnlineUsable);

  // Fresh context for Offline V2 isolation of cache vs shared Online SW.
  await context.close();
  const v2Dir = path.join(__dirname, '.chrome-user-data', `phase-px-v2-${Date.now()}`);
  fs.mkdirSync(v2Dir, { recursive: true });
  const v2Context = await chromium.launchPersistentContext(v2Dir, {
    headless: true,
    args: ['--disable-blink-features=AutomationControlled'],
  });

  const v2HomeCold = await profileUrl(v2Context, V2_URL, 'v2_home_cold', waitV2Shell);
  let warmHome;
  {
    const page = await v2Context.newPage();
    await attachObservers(page);
    await page.goto(V2_URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await waitV2Shell(page);
    const t1 = Date.now();
    await page.reload({ waitUntil: 'domcontentloaded', timeout: 30000 });
    await waitV2Shell(page);
    warmHome = await collectPageMetrics(page, 'v2_home_warm', t1);
    await page.close();
  }

  const v2InvCold = await profileUrl(v2Context, V2_ROUTE_URL, 'v2_inventory_cold', waitV2Shell);
  let warmInv;
  {
    const page = await v2Context.newPage();
    await attachObservers(page);
    await page.goto(V2_ROUTE_URL, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await waitV2Shell(page);
    const t1 = Date.now();
    await page.reload({ waitUntil: 'domcontentloaded', timeout: 30000 });
    await waitV2Shell(page);
    warmInv = await collectPageMetrics(page, 'v2_inventory_warm', t1);
    await page.close();
  }

  await v2Context.setOffline(true);
  const v2HomeOffline = await profileUrl(v2Context, V2_URL, 'v2_home_offline', waitV2Shell);
  const v2InvOffline = await profileUrl(v2Context, V2_ROUTE_URL, 'v2_inventory_offline', waitV2Shell);
  await v2Context.setOffline(false);

  const report = {
    phase: 'PX',
    title: 'Offline V2 Performance Architecture Audit',
    generated_at: new Date().toISOString(),
    urls: {
      v2: V2_URL,
      v2_route: V2_ROUTE_URL,
      online_login: ONLINE_LOGIN_URL,
      online_admin: ONLINE_URL,
    },
    online: {
      login_cold: onlineLoginCold,
      login_warm: onlineLoginWarm,
      admin_cold: onlineAdminCold,
      admin_warm: onlineAdminWarm,
    },
    offline_v2: {
      home_cold: v2HomeCold,
      home_warm: warmHome,
      inventory_cold: v2InvCold,
      inventory_warm: warmInv,
      home_offline: v2HomeOffline,
      inventory_offline: v2InvOffline,
    },
  };

  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({
    out: OUT,
    online_login_warm_fcp: onlineLoginWarm.first_contentful_paint_ms,
    online_admin_warm_fcp: onlineAdminWarm.first_contentful_paint_ms,
    v2_home_warm_shell: warmHome.layer && warmHome.layer.shell_ready_ms,
    v2_inv_warm_route: warmInv.layer && warmInv.layer.route_ready_ms,
    v2_home_offline_shell: v2HomeOffline.layer && v2HomeOffline.layer.shell_ready_ms,
  }, null, 2));
  await v2Context.close();
  process.exit(0);
})().catch((err) => {
  console.error(err);
  process.exit(2);
});
