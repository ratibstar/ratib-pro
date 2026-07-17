/**
 * Phase PX2 — Cold BusinessModule performance audit.
 *
 * Audit-only browser instrumentation. Production assets are not modified.
 * Each module receives an isolated installed profile, a cold deep-link load,
 * then a second in-page navigation after the module remains active.
 */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.RATEB_V2_URL
  || 'https://rateb.sa/rateb-erp/public/v2/index.html';
const OUT = process.env.RATEB_PX2_OUT
  || path.join(__dirname, 'reports', `phase-px2-cold-modules-${Date.now()}.json`);

const MODULES = [
  { id: 'identity', route: '/identity' },
  { id: 'inventory', route: '/inventory' },
  { id: 'procurement', route: '/procurement' },
  { id: 'sales', route: '/sales' },
  { id: 'accounting', route: '/accounting' },
  { id: 'crm', route: '/crm' },
  { id: 'hr', route: '/hr' },
  // Boot selector is "manufacturing", while the published module route is "/mfg".
  { id: 'manufacturing', trigger: '/manufacturing', route: '/mfg' },
];

function round(value) {
  return Number.isFinite(Number(value))
    ? Math.round(Number(value) * 10) / 10
    : null;
}

function summarizeCpu(profile) {
  if (!profile || !Array.isArray(profile.nodes)) {
    return { total_ms: 0, top_functions: [] };
  }
  const byId = new Map(profile.nodes.map((node) => [node.id, node]));
  const sums = new Map();
  const samples = profile.samples || [];
  const deltas = profile.timeDeltas || [];
  let totalUs = 0;
  let v2Us = 0;
  samples.forEach((nodeId, index) => {
    const us = Number(deltas[index] || 0);
    totalUs += us;
    const node = byId.get(nodeId);
    if (!node) return;
    const frame = node.callFrame || {};
    const url = String(frame.url || '');
    if (!url.includes('/v2/')) return;
    v2Us += us;
    const key = `${frame.functionName || '(anonymous)'}|${url.replace(/^https?:\/\/[^/]+/, '')}`;
    sums.set(key, (sums.get(key) || 0) + us);
  });
  const top = Array.from(sums.entries())
    .map(([key, us]) => {
      const split = key.indexOf('|');
      return {
        function: key.slice(0, split),
        url: key.slice(split + 1),
        self_ms: round(us / 1000),
      };
    })
    .sort((a, b) => b.self_ms - a.self_ms)
    .slice(0, 20);
  return {
    sample_window_ms: round(totalUs / 1000),
    v2_cpu_self_ms: round(v2Us / 1000),
    top_functions: top,
  };
}

function summarizeCoverage(entries) {
  return (entries || [])
    .filter((entry) => String(entry.url || '').includes('/v2/'))
    .map((entry) => {
      let total = 0;
      let executed = 0;
      (entry.functions || []).forEach((fn) => {
        (fn.ranges || []).forEach((range) => {
          const bytes = Math.max(0, range.endOffset - range.startOffset);
          total += bytes;
          if (range.count > 0) executed += bytes;
        });
      });
      return {
        url: String(entry.url).replace(/^https?:\/\/[^/]+/, ''),
        function_ranges: (entry.functions || []).length,
        sampled_bytes: total,
        executed_sampled_bytes: executed,
      };
    })
    .sort((a, b) => b.executed_sampled_bytes - a.executed_sampled_bytes)
    .slice(0, 20);
}

async function installInstrumentation(page, moduleId) {
  await page.addInitScript(({ targetModule }) => {
    const audit = window.__PX2__ = {
      targetModule,
      phase: 'cold',
      spans: [],
      sql: [],
      events: [],
      created: [],
      errors: [],
    };

    function now() {
      return Math.round(performance.now() * 10) / 10;
    }

    function recordSpan(layer, op, module, start, ok, error) {
      const end = performance.now();
      audit.spans.push({
        phase: audit.phase,
        layer,
        op,
        module: module || null,
        start_ms: Math.round(start * 10) / 10,
        end_ms: Math.round(end * 10) / 10,
        duration_ms: Math.round((end - start) * 10) / 10,
        ok: ok !== false,
        error: error ? String(error).slice(0, 180) : null,
      });
    }

    function wrapMethod(obj, name, layer, module) {
      if (!obj || typeof obj[name] !== 'function' || obj[name].__px2Wrapped) return;
      const original = obj[name];
      function wrapped() {
        const start = performance.now();
        let result;
        try {
          result = original.apply(this, arguments);
        } catch (error) {
          recordSpan(layer, name, module, start, false, error && error.message);
          throw error;
        }
        if (result && typeof result.then === 'function') {
          return result.then((value) => {
            recordSpan(layer, name, module, start, true);
            return value;
          }, (error) => {
            recordSpan(layer, name, module, start, false, error && error.message);
            throw error;
          });
        }
        recordSpan(layer, name, module, start, true);
        return result;
      }
      wrapped.__px2Wrapped = true;
      wrapped.__px2Original = original;
      obj[name] = wrapped;
    }

    function wrapSql(db) {
      if (!db || typeof db.exec !== 'function' || db.exec.__px2SqlWrapped) return;
      const original = db.exec;
      function exec(sql) {
        const start = performance.now();
        const fingerprint = String(sql || '')
          .replace(/\s+/g, ' ')
          .replace(/'[^']*'/g, '?')
          .replace(/\b\d+(?:\.\d+)?\b/g, '?')
          .trim()
          .slice(0, 220);
        let result;
        try {
          result = original.apply(this, arguments);
        } catch (error) {
          audit.sql.push({
            phase: audit.phase,
            at_ms: Math.round(start * 10) / 10,
            duration_ms: Math.round((performance.now() - start) * 10) / 10,
            sql: fingerprint,
            ok: false,
          });
          throw error;
        }
        return Promise.resolve(result).then((value) => {
          audit.sql.push({
            phase: audit.phase,
            at_ms: Math.round(start * 10) / 10,
            duration_ms: Math.round((performance.now() - start) * 10) / 10,
            sql: fingerprint,
            ok: true,
          });
          return value;
        }, (error) => {
          audit.sql.push({
            phase: audit.phase,
            at_ms: Math.round(start * 10) / 10,
            duration_ms: Math.round((performance.now() - start) * 10) / 10,
            sql: fingerprint,
            ok: false,
          });
          throw error;
        });
      }
      exec.__px2Wrapped = true;
      db.exec = exec;
    }

    function instrumentRouteHandler(handler, module) {
      ['init', 'mount', 'unmount', 'dispose'].forEach((name) => {
        wrapMethod(handler, name, 'render', module);
      });
      return handler;
    }

    function instrumentModule(instance, fallbackId) {
      if (!instance || instance.__px2Instrumented) return instance;
      try {
        Object.defineProperty(instance, '__px2Instrumented', { value: true });
      } catch (_) {
        instance.__px2Instrumented = true;
      }
      const module = (instance.metadata && instance.metadata.id) || fallbackId;
      audit.created.push({ type: 'module-instance', module, at_ms: now() });
      const lifecyclePattern = /^(on|_ensure|ensure|createRouteHandler|getHealth|getDiagnostics|recordTimeline|listTimeline|sync|register|render|mount|activate|initialize|dispose|start|stop)/i;
      let proto = instance;
      const names = new Set();
      for (let depth = 0; proto && depth < 4; depth += 1) {
        Object.getOwnPropertyNames(proto).forEach((name) => names.add(name));
        proto = Object.getPrototypeOf(proto);
      }
      names.forEach((name) => {
        if (name === 'constructor' || !lifecyclePattern.test(name)) return;
        if (name === 'createRouteHandler' && typeof instance[name] === 'function') {
          const original = instance[name];
          if (original.__px2Wrapped) return;
          function createRouteHandler() {
            const start = performance.now();
            try {
              const handler = original.apply(this, arguments);
              recordSpan('module', name, module, start, true);
              return instrumentRouteHandler(handler, module);
            } catch (error) {
              recordSpan('module', name, module, start, false, error && error.message);
              throw error;
            }
          }
          createRouteHandler.__px2Wrapped = true;
          instance[name] = createRouteHandler;
          return;
        }
        wrapMethod(instance, name, 'module', module);
      });
      return instance;
    }

    function instrumentRouter(router) {
      if (!router) return router;
      ['init', 'navigate', 'registerRoute', 'unregisterRoute', 'dispose']
        .forEach((name) => wrapMethod(router, name, 'router', targetModule));
      return router;
    }

    function instrumentShell(shell) {
      if (!shell) return shell;
      ['mount', 'renderNav', 'setLoading', 'setError', 'dispose']
        .forEach((name) => wrapMethod(shell, name, 'shell', targetModule));
      return shell;
    }

    function instrumentFramework(framework) {
      if (!framework) return framework;
      ['start', 'register', 'activate', 'deactivate', 'validateDependencies',
        'getContributions', 'getHealth', 'getDiagnostics']
        .forEach((name) => wrapMethod(framework, name, 'business-framework', targetModule));
      return framework;
    }

    function instrumentSdk(host) {
      if (!host) return host;
      ['start', 'install', 'initialize', 'mount', 'activate', 'load',
        'deactivate', 'unmount', 'disposeModule', 'registerService']
        .forEach((name) => wrapMethod(host, name, 'sdk', targetModule));
      return host;
    }

    function hookGlobal(name, onSet) {
      let value;
      try {
        Object.defineProperty(window, name, {
          configurable: true,
          enumerable: true,
          get() { return value; },
          set(next) {
            value = next;
            try { onSet(next); } catch (error) {
              audit.errors.push({ hook: name, error: String(error && error.message || error) });
            }
          },
        });
      } catch (_) { /* existing non-configurable global */ }
    }

    hookGlobal('RatebOfflineV2HCI', (api) => {
      ['ensureLayout', 'verifyLayout', 'getQuota', 'requestPersistence',
        'appendLog', 'readSqliteBytes', 'writeSqliteBytes']
        .forEach((name) => wrapMethod(api, name, 'hci', targetModule));
    });
    hookGlobal('RatebOfflineV2Runtime', (api) => {
      ['start', 'loadActivePackage', 'runHealthChecks']
        .forEach((name) => wrapMethod(api, name, 'runtime', targetModule));
      if (api && api.events) wrapMethod(api.events, 'emit', 'events', targetModule);
    });
    hookGlobal('RatebOfflineV2DB', (api) => {
      wrapSql(api);
      ['open', 'migrate', 'integrityCheck', 'syncInstallPointerFromActiveJson']
        .forEach((name) => wrapMethod(api, name, 'sqlite', targetModule));
    });
    hookGlobal('RatebOfflineV2PM', (api) => {
      ['getActive', 'verifySlot', 'stageInstall', 'activate', 'ingestArtifact']
        .forEach((name) => wrapMethod(api, name, 'pm', targetModule));
    });
    hookGlobal('RatebOfflineV2Router', (api) => {
      if (api && typeof api.create === 'function') {
        const original = api.create;
        api.create = function () { return instrumentRouter(original.apply(this, arguments)); };
      }
    });
    hookGlobal('RatebOfflineV2Shell', (api) => {
      if (api && typeof api.create === 'function') {
        const original = api.create;
        api.create = function () { return instrumentShell(original.apply(this, arguments)); };
      }
    });
    hookGlobal('RatebOfflineV2Sync', (api) => {
      ['create', 'start', 'verifyCompat', 'enqueue', 'pull', 'push']
        .forEach((name) => wrapMethod(api, name, 'sync', targetModule));
    });
    hookGlobal('RatebOfflineV2Modules', (api) => {
      if (api && typeof api.create === 'function') {
        const original = api.create;
        api.create = function () { return instrumentSdk(original.apply(this, arguments)); };
      }
    });
    hookGlobal('RatebOfflineV2Business', (api) => {
      if (api && typeof api.create === 'function') {
        const original = api.create;
        api.create = function () { return instrumentFramework(original.apply(this, arguments)); };
      }
    });

    const moduleGlobals = [
      'RatebOfflineV2Identity',
      'RatebOfflineV2Inventory',
      'RatebOfflineV2Procurement',
      'RatebOfflineV2Sales',
      'RatebOfflineV2Accounting',
      'RatebOfflineV2Crm',
      'RatebOfflineV2Hr',
      'RatebOfflineV2Mfg',
    ];
    moduleGlobals.forEach((globalName) => {
      hookGlobal(globalName, (api) => {
        if (!api || typeof api.create !== 'function') return;
        const original = api.create;
        api.create = function () {
          return instrumentModule(original.apply(this, arguments), targetModule);
        };
      });
    });

    const dispatch = EventTarget.prototype.dispatchEvent;
    EventTarget.prototype.dispatchEvent = function (event) {
      if (event && /^rateb-v2-/.test(String(event.type || ''))) {
        audit.events.push({ phase: audit.phase, type: event.type, at_ms: now() });
      }
      return dispatch.call(this, event);
    };
  }, { targetModule: moduleId });
}

async function waitForColdRoute(page, module) {
  if (module.trigger && module.trigger !== module.route) {
    await page.waitForFunction(() => {
      return !!window.RatebOfflineV2Mfg
        && performance.getEntriesByName('rateb-v2-background-platform-done').length > 0;
    }, null, { timeout: 30000 });
    const activated = await page.evaluate(() => {
      return performance.getEntriesByName('rateb-v2-active-module-ready').length > 0;
    });
    if (!activated) {
      await page.waitForTimeout(500);
      return { ready: false, reason: 'boot_id_manufacturing_vs_module_id_mfg' };
    }
    await page.evaluate(async (route) => {
      const shell = window.RatebOfflineV2AppShell;
      const router = shell && shell.getRouter ? shell.getRouter() : null;
      if (!router) throw new Error('px2_router_missing_after_module_activation');
      const result = await router.navigate(route, { replace: true });
      if (!result || !result.ok) throw new Error('px2_published_route_failed:' + route);
      performance.mark('px2-cold-route-ready');
    }, module.route);
  }
  await page.waitForFunction((route) => {
    const outlet = document.getElementById('rateb-v2-shell-outlet');
    return outlet && outlet.getAttribute('data-route')
      && String(outlet.getAttribute('data-route')).startsWith(route.slice(1));
  }, module.route, { timeout: 30000 });
  await page.waitForTimeout(250);
  return { ready: true };
}

async function navigateWarm(page, module) {
  return page.evaluate(async ({ route, id }) => {
    const audit = window.__PX2__;
    const shell = window.RatebOfflineV2AppShell;
    const router = shell && shell.getRouter ? shell.getRouter() : null;
    if (!router) throw new Error('px2_router_missing');
    await router.navigate('/', { replace: true });
    audit.phase = 'warm';
    performance.mark(`px2-${id}-warm-start`);
    const start = performance.now();
    const result = await router.navigate(route, { replace: true });
    const duration = performance.now() - start;
    performance.mark(`px2-${id}-warm-end`);
    return {
      ok: !!(result && result.ok),
      duration_ms: Math.round(duration * 10) / 10,
      active_route: (document.getElementById('rateb-v2-shell-outlet') || {})
        .getAttribute
        ? document.getElementById('rateb-v2-shell-outlet').getAttribute('data-route')
        : null,
    };
  }, { route: module.route, id: module.id });
}

async function collectPageEvidence(page, module, warmResult) {
  return page.evaluate(({ moduleSpec, warm }) => {
    const marks = (performance.getEntriesByType('mark') || [])
      .map((entry) => ({ name: entry.name, at_ms: Math.round(entry.startTime * 10) / 10 }));
    const mark = (name) => {
      const hit = marks.find((entry) => entry.name === name);
      return hit ? hit.at_ms : null;
    };
    const resources = (performance.getEntriesByType('resource') || [])
      .filter((entry) => String(entry.name).includes('/v2/'))
      .map((entry) => ({
        name: entry.name.replace(/^https?:\/\/[^/]+/, ''),
        type: entry.initiatorType,
        start_ms: Math.round(entry.startTime * 10) / 10,
        duration_ms: Math.round(entry.duration * 10) / 10,
        transfer_bytes: entry.transferSize || 0,
        decoded_bytes: entry.decodedBodySize || 0,
      }))
      .sort((a, b) => b.duration_ms - a.duration_ms);
    const audit = window.__PX2__ || {};
    const coldSql = (audit.sql || []).filter((row) => row.phase === 'cold');
    const warmSql = (audit.sql || []).filter((row) => row.phase === 'warm');
    const duplicates = {};
    coldSql.forEach((row) => { duplicates[row.sql] = (duplicates[row.sql] || 0) + 1; });
    const outlet = document.getElementById('rateb-v2-shell-outlet');
    const firstSql = coldSql.length ? coldSql[0] : null;
    const coldStart = mark('rateb-v2-shell-ready') || mark('rateb-v2-background-start') || 0;
    const coldEnd = mark('rateb-v2-route-ready') || mark('px2-cold-route-ready');
    return {
      module: moduleSpec.id,
      route: moduleSpec.route,
      cold: {
        navigation_to_route_ms: coldEnd,
        shell_to_route_ms: coldEnd !== null ? Math.round((coldEnd - coldStart) * 10) / 10 : null,
        shell_ready_ms: mark('rateb-v2-shell-ready'),
        background_start_ms: mark('rateb-v2-background-start'),
        pm_ready_ms: mark('rateb-v2-pm-ready'),
        sqlite_ready_ms: mark('rateb-v2-db-ready'),
        platform_ready_ms: mark('rateb-v2-background-platform-ready'),
        platform_done_ms: mark('rateb-v2-background-platform-done'),
        active_module_ready_ms: mark('rateb-v2-active-module-ready'),
        first_sql_at_ms: firstSql ? firstSql.at_ms : null,
        first_sql_from_shell_ms: firstSql
          ? Math.round((firstSql.at_ms - coldStart) * 10) / 10
          : null,
        sql_count: coldSql.length,
        sql_total_ms: Math.round(coldSql.reduce((sum, row) => sum + row.duration_ms, 0) * 10) / 10,
      },
      warm: Object.assign({}, warm, {
        sql_count: warmSql.length,
        sql_total_ms: Math.round(warmSql.reduce((sum, row) => sum + row.duration_ms, 0) * 10) / 10,
      }),
      sql: {
        cold: coldSql,
        warm: warmSql,
        duplicates: Object.entries(duplicates)
          .filter(([, count]) => count > 1)
          .map(([sql, count]) => ({ sql, count }))
          .sort((a, b) => b.count - a.count),
      },
      spans: audit.spans || [],
      events: audit.events || [],
      created: audit.created || [],
      instrumentation_errors: audit.errors || [],
      resources,
      active_route: outlet ? outlet.getAttribute('data-route') : null,
      workspace_text: outlet ? String(outlet.textContent || '').trim().slice(0, 240) : null,
      globals: {
        db_open: !!(window.RatebOfflineV2DB
          && window.RatebOfflineV2DB.isOpen
          && window.RatebOfflineV2DB.isOpen()),
        db_mode: window.RatebOfflineV2DB && window.RatebOfflineV2DB.getMode
          ? window.RatebOfflineV2DB.getMode()
          : null,
        identity_loaded: !!window.RatebOfflineV2Identity,
        target_loaded: !!({
          identity: window.RatebOfflineV2Identity,
          inventory: window.RatebOfflineV2Inventory,
          procurement: window.RatebOfflineV2Procurement,
          sales: window.RatebOfflineV2Sales,
          accounting: window.RatebOfflineV2Accounting,
          crm: window.RatebOfflineV2Crm,
          hr: window.RatebOfflineV2Hr,
          manufacturing: window.RatebOfflineV2Mfg,
        }[moduleSpec.id]),
      },
    };
  }, { moduleSpec: module, warm: warmResult });
}

async function profileModule(module, index) {
  const profileDir = path.join(
    __dirname,
    '.chrome-user-data',
    `phase-px2-${module.id}-${Date.now()}-${index}`
  );
  fs.mkdirSync(profileDir, { recursive: true });
  const context = await chromium.launchPersistentContext(profileDir, {
    headless: true,
    args: ['--disable-blink-features=AutomationControlled'],
  });

  // Install and warm the host/core cache without loading a BusinessModule.
  const warmup = context.pages()[0] || await context.newPage();
  await warmup.goto(BASE, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await warmup.waitForFunction(() => {
    return document.documentElement.getAttribute('data-rateb-v2-shell-ready') === '1';
  }, { timeout: 30000 });
  await warmup.evaluate(async () => {
    if (navigator.serviceWorker) await navigator.serviceWorker.ready;
  }).catch(() => null);
  await warmup.waitForTimeout(800);
  await warmup.close();

  const page = await context.newPage();
  await installInstrumentation(page, module.id);
  const cdp = await context.newCDPSession(page);
  await cdp.send('Profiler.enable');
  await cdp.send('Profiler.setSamplingInterval', { interval: 100 });
  await cdp.send('Profiler.startPreciseCoverage', {
    callCount: true,
    detailed: true,
    allowTriggeredUpdates: false,
  });
  await cdp.send('Profiler.start');

  const url = `${BASE}#${module.trigger || module.route}`;
  const started = Date.now();
  const pageErrors = [];
  page.on('pageerror', (error) => pageErrors.push(String(error && error.message || error)));
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  const coldRoute = await waitForColdRoute(page, module);
  const coldWallMs = Date.now() - started;

  const coldCoverage = await cdp.send('Profiler.takePreciseCoverage');
  const coldCpu = await cdp.send('Profiler.stop');
  await cdp.send('Profiler.stopPreciseCoverage');

  await cdp.send('Profiler.startPreciseCoverage', {
    callCount: true,
    detailed: true,
    allowTriggeredUpdates: false,
  });
  await cdp.send('Profiler.start');
  const warmResult = await navigateWarm(page, module);
  await page.waitForTimeout(100);
  const warmCoverage = await cdp.send('Profiler.takePreciseCoverage');
  const warmCpu = await cdp.send('Profiler.stop');
  await cdp.send('Profiler.stopPreciseCoverage');

  const evidence = await collectPageEvidence(page, module, warmResult);
  evidence.cold.wall_ms = coldWallMs;
  evidence.cold.route_result = coldRoute;
  evidence.cpu = {
    cold: summarizeCpu(coldCpu.profile),
    warm: summarizeCpu(warmCpu.profile),
  };
  evidence.js_coverage = {
    cold: summarizeCoverage(coldCoverage.result),
    warm: summarizeCoverage(warmCoverage.result),
  };
  evidence.page_errors = pageErrors;

  await cdp.detach().catch(() => null);
  await context.close();
  return evidence;
}

(async () => {
  const results = [];
  for (let i = 0; i < MODULES.length; i += 1) {
    const module = MODULES[i];
    process.stderr.write(`[PX2] ${i + 1}/${MODULES.length} ${module.id}\n`);
    results.push(await profileModule(module, i));
  }
  const report = {
    phase: 'PX2',
    title: 'Cold Module Performance Audit',
    audit_only: true,
    generated_at: new Date().toISOString(),
    url: BASE,
    identity_boundary: {
      online_erp_authentication_authority: true,
      credentials_collected: false,
      sql_bind_values_collected: false,
    },
    modules: results,
  };
  fs.mkdirSync(path.dirname(OUT), { recursive: true });
  fs.writeFileSync(OUT, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({
    out: OUT,
    modules: results.map((result) => ({
      module: result.module,
      cold_route_ms: result.cold.navigation_to_route_ms,
      cold_shell_to_route_ms: result.cold.shell_to_route_ms,
      warm_route_ms: result.warm.duration_ms,
      cold_sql: result.cold.sql_count,
      warm_sql: result.warm.sql_count,
      cold_cpu_ms: result.cpu.cold.v2_cpu_self_ms,
      warm_cpu_ms: result.cpu.warm.v2_cpu_self_ms,
      errors: result.page_errors.length + result.instrumentation_errors.length,
    })),
  }, null, 2));
})().catch((error) => {
  console.error(error);
  process.exit(2);
});
