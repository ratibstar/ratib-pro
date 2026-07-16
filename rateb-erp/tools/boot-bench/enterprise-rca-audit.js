/**
 * RATIB ERP Enterprise Performance Root Cause Audit (READ-ONLY / EVIDENCE).
 * Times REAL sidebar clicks from mousedown → usable. Does NOT call navigate() API as the primary metric.
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
const OUT_DIR = path.join(__dirname, 'reports');

const MODULES = [
  { id: 'dashboard', prefer: '/admin/', match: /\/admin\/?(\?|$)/ },
  { id: 'hr', prefer: '/admin/hr', match: /\/admin\/hr(\/|\?|$)/ },
  { id: 'crm', prefer: '/admin/crm', match: /\/admin\/crm/ },
  { id: 'inventory', prefer: '/admin/ops/inventory', match: /\/admin\/ops\/inventory/ },
  { id: 'accounting', prefer: '/admin/ops/accounting', match: /\/admin\/ops\/accounting/ },
  { id: 'procurement', prefer: '/admin/ops/purchase-requests', match: /purchase-requests/ },
  { id: 'warehouse', prefer: '/admin/ops/warehouses', match: /warehouses/ },
  { id: 'payroll', prefer: '/admin/hr/payroll', match: /payroll/ },
  { id: 'finance', prefer: '/admin/ops/accounting/platform', match: /accounting\/platform/ },
  { id: 'projects', prefer: '/admin/ops/projects', match: /\/projects/ },
  { id: 'assets', prefer: '/admin/ops/assets', match: /\/assets(\/|\?|$)/ },
  { id: 'support', prefer: '/admin/oversight/approvals', match: /oversight\/approvals/ },
  { id: 'website', prefer: '/admin/ops/website', match: /\/website/ },
  { id: 'pos', prefer: '/admin/ops/pos', match: /\/pos(\/|\?|$)/ },
];

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 120000,
  });
}

(async () => {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  const tReport = Date.now();

  // ---- PART 2: Server-Timing probe (curl) + code cause ----
  let mint;
  try {
    mint = JSON.parse(ssh('php /tmp/remote-auth-pa.php mintpos'));
  } catch (e) {
    mint = JSON.parse(ssh('php /tmp/remote-auth.php mint'));
  }
  const cookie = `${mint.session_name || 'rateb_erp'}=${mint.session_id}`;

  const serverHeaderProbe = JSON.parse(
    ssh(
      `php -r '$ch=curl_init("https://rateb.sa/rateb-erp/public/admin/hr/attendance?company_id=22");curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIE=>${JSON.stringify(cookie)},CURLOPT_TIMEOUT=>90,CURLOPT_HTTPHEADER=>["Accept: text/html"]]);$r=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);$ttfb=curl_getinfo($ch,CURLINFO_STARTTRANSFER_TIME)*1000;$total=curl_getinfo($ch,CURLINFO_TOTAL_TIME)*1000;curl_close($ch);$h=substr($r,0,strpos($r,"\\r\\n\\r\\n")?:0);echo json_encode(["http"=>$code,"ttfb_ms"=>round($ttfb,1),"total_ms"=>round($total,1),"has_server_timing"=>stripos($h,"server-timing")!==false,"has_x_rateb_st"=>stripos($h,"x-rateb-server-timing")!==false,"header_snip"=>substr(preg_replace("/\\r\\n/"," | ",$h),0,900)],JSON_UNESCAPED_SLASHES);'`
    )
  );

  // Cold then warm curl TTFB for first-vs-second (origin PHP)
  const phpFirstSecond = JSON.parse(
    ssh(
      `php -r '$c=${JSON.stringify(cookie)};$paths=["/rateb-erp/public/admin/","/rateb-erp/public/admin/hr/attendance?company_id=22","/rateb-erp/public/admin/ops/inventory?company_id=22","/rateb-erp/public/admin/ops/accounting?company_id=22"];$out=[];foreach($paths as $p){for($pass=1;$pass<=2;$pass++){$ch=curl_init("https://rateb.sa".$p);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIE=>$c,CURLOPT_TIMEOUT=>90]);$r=curl_exec($ch);$ttfb=curl_getinfo($ch,CURLINFO_STARTTRANSFER_TIME)*1000;$total=curl_getinfo($ch,CURLINFO_TOTAL_TIME)*1000;$sz=curl_getinfo($ch,CURLINFO_SIZE_DOWNLOAD);curl_close($ch);$out[]=["path"=>$p,"pass"=>$pass,"ttfb_ms"=>round($ttfb,1),"total_ms"=>round($total,1),"bytes"=>(int)$sz,"server_timing"=>stripos($r,"Server-Timing:")!==false];}}echo json_encode($out,JSON_UNESCAPED_SLASHES);'`
    )
  );

  // OPcache / realpath status (read-only)
  let opcacheStatus = null;
  try {
    opcacheStatus = JSON.parse(
      ssh(
        `php -r 'echo json_encode(["opcache_enabled"=>function_exists("opcache_get_status")?((opcache_get_status(false)["opcache_enabled"]??null)):null,"realpath_cache_size"=>ini_get("realpath_cache_size"),"realpath_cache_ttl"=>ini_get("realpath_cache_ttl"),"opcache_validate"=>ini_get("opcache.validate_timestamps"),"opcache_revalidate"=>ini_get("opcache.revalidate_freq")],JSON_UNESCAPED_SLASHES);'`
      )
    );
  } catch (e) {
    opcacheStatus = { error: String(e.message || e) };
  }

  // Existing CLI profiler if present (evidence, not production edit)
  let cliPhpProfile = null;
  try {
    const cliOut = ssh(
      'test -f /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/profile-admin-get.php && php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/profile-admin-get.php 2>/dev/null | tail -c 120000 || echo null'
    );
    if (cliOut && cliOut.trim() !== 'null') {
      try {
        cliPhpProfile = JSON.parse(cliOut);
      } catch (e2) {
        cliPhpProfile = { parse_error: true, snip: cliOut.slice(0, 500) };
      }
    }
  } catch (e3) {
    cliPhpProfile = { error: String(e3.message || e3) };
  }

  const serverTimingWhy = {
    file: 'rateb-erp/app/Core/ServerTiming.php',
    function: 'flush',
    line: 55,
    evidence:
      'flush() returns early when headers_sent() is true. HTML layout echoes body before request shutdown, so shutdown_function runs after headers already sent → Server-Timing never emitted.',
    arm_site: { file: 'rateb-erp/public/index.php', lines: '75–79' },
    view_mark_site: { file: 'rateb-erp/views/layouts/main.php', lines: '2–4' },
    marks_defined: ['bootstrap', 'controller', 'view', 'total'],
    marks_NOT_instrumented: [
      'RBAC',
      'Menu Builder',
      'Sidebar Builder',
      'Company lookup',
      'Router alone',
      'Response send',
    ],
    curl_probe: serverHeaderProbe,
  };

  // ---- Browser real-click instrumentation ----
  const context = await chromium.launchPersistentContext(
    path.join(os.tmpdir(), 'rateb-rca-' + tReport),
    {
      headless: true,
      executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
      args: ['--disable-dev-shm-usage'],
      serviceWorkers: 'allow',
      locale: 'ar-SA',
      viewport: { width: 1400, height: 900 },
    }
  );
  await context.clearCookies();
  await context.addCookies([
    {
      name: mint.session_name || 'rateb_erp',
      value: mint.session_id,
      domain: 'rateb.sa',
      path: '/',
      httpOnly: true,
      secure: true,
    },
  ]);
  const page = context.pages()[0] || (await context.newPage());

  await page.addInitScript(() => {
    window.__RCA = {
      reinitCalls: [],
      prefetchRequests: [],
      stages: [],
      click: null,
      fetchOrig: null,
    };

    // Patch fetch to tag prefetch / nav-swap
    const origFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
      const url = typeof input === 'string' ? input : (input && input.url) || '';
      const headers = (init && init.headers) || {};
      const getH = (k) => {
        if (!headers) return '';
        if (typeof headers.get === 'function') return headers.get(k) || '';
        return headers[k] || headers[k.toLowerCase()] || '';
      };
      const isPrefetch = String(getH('X-Rateb-Prefetch') || '') === '1';
      const isSwap = String(getH('X-Rateb-Nav-Swap') || '') === '1';
      const t0 = performance.now();
      const stack = new Error().stack || '';
      return origFetch(input, init).then(
        (res) => {
          const row = {
            url: String(url).slice(0, 180),
            isPrefetch,
            isSwap,
            status: res.status,
            ms: Math.round(performance.now() - t0),
            stack: String(stack)
              .split('\n')
              .slice(0, 8)
              .map((s) => s.trim()),
            t: performance.now(),
          };
          if (isPrefetch || /idlePrefetch|prefetchUrl|prefetch/i.test(stack)) {
            window.__RCA.prefetchRequests.push(row);
          }
          if (window.__RCA.click) {
            window.__RCA.click.fetchDuring = window.__RCA.click.fetchDuring || [];
            window.__RCA.click.fetchDuring.push(row);
          }
          return res;
        },
        (err) => {
          throw err;
        }
      );
    };

    // Wrap RatebApp.reinit when available
    const wrapReinit = () => {
      if (!window.RatebApp || typeof window.RatebApp.reinit !== 'function') return;
      if (window.RatebApp.__rcaWrapped) return;
      const orig = window.RatebApp.reinit.bind(window.RatebApp);
      window.RatebApp.reinit = function () {
        const t0 = performance.now();
        const stack = new Error().stack || '';
        const ret = orig();
        const ms = Math.round(performance.now() - t0);
        const call = {
          ms,
          t: performance.now(),
          stack: String(stack)
            .split('\n')
            .slice(0, 12)
            .map((s) => s.trim()),
        };
        window.__RCA.reinitCalls.push(call);
        if (window.__RCA.click) {
          window.__RCA.click.reinitCalls = window.__RCA.click.reinitCalls || [];
          window.__RCA.click.reinitCalls.push(call);
        }
        return ret;
      };
      window.RatebApp.__rcaWrapped = true;
    };
    setInterval(wrapReinit, 50);

    document.addEventListener(
      'mousedown',
      (ev) => {
        const a = ev.target && ev.target.closest && ev.target.closest('a.rateb-nav-link, #rateb-sidebar a[href]');
        if (!a) return;
        window.__RCA.click = {
          mousedown_t: performance.now(),
          href: a.href,
          text: (a.innerText || '').trim().slice(0, 40),
          stages: [{ name: 'mousedown', t: 0 }],
          fetchDuring: [],
          reinitCalls: [],
          beforeLeave_t: null,
          afterEnter_t: null,
          afterEnter_detail: null,
          pushState_t: null,
          usable_t: null,
        };
      },
      true
    );

    document.addEventListener(
      'click',
      (ev) => {
        if (!window.__RCA.click) return;
        const t = performance.now() - window.__RCA.click.mousedown_t;
        window.__RCA.click.stages.push({ name: 'click', t: Math.round(t * 10) / 10 });
      },
      true
    );

    document.addEventListener('rateb:nav:beforeLeave', () => {
      if (!window.__RCA.click) return;
      const t = performance.now() - window.__RCA.click.mousedown_t;
      window.__RCA.click.beforeLeave_t = Math.round(t * 10) / 10;
      window.__RCA.click.stages.push({ name: 'beforeLeave', t: window.__RCA.click.beforeLeave_t });
    });

    const origPush = history.pushState.bind(history);
    history.pushState = function (...args) {
      if (window.__RCA.click) {
        const t = Math.round((performance.now() - window.__RCA.click.mousedown_t) * 10) / 10;
        window.__RCA.click.pushState_t = t;
        window.__RCA.click.stages.push({ name: 'history.pushState', t });
      }
      return origPush(...args);
    };

    document.addEventListener('rateb:nav:afterEnter', (ev) => {
      if (!window.__RCA.click) return;
      const t = Math.round((performance.now() - window.__RCA.click.mousedown_t) * 10) / 10;
      window.__RCA.click.afterEnter_t = t;
      window.__RCA.click.afterEnter_detail = ev && ev.detail ? ev.detail : null;
      window.__RCA.click.stages.push({ name: 'afterEnter', t, detail: ev && ev.detail });
      // Resource Timing for swap/prefetch overlapping this click
      try {
        const entries = performance.getEntriesByType('resource') || [];
        const t0 = window.__RCA.click.mousedown_t;
        const navRes = entries
          .filter((e) => e.responseEnd >= t0 - 50)
          .filter((e) => /admin/i.test(e.name) && (e.initiatorType === 'fetch' || e.initiatorType === 'xmlhttprequest'))
          .map((e) => ({
            name: String(e.name).slice(0, 160),
            initiatorType: e.initiatorType,
            duration: Math.round(e.duration * 10) / 10,
            ttfb: Math.round((e.responseStart - e.requestStart) * 10) / 10,
            transferSize: e.transferSize,
            encodedBodySize: e.encodedBodySize,
            startRel: Math.round((e.startTime - t0) * 10) / 10,
          }))
          .sort((a, b) => b.duration - a.duration)
          .slice(0, 25);
        window.__RCA.click.resource_during = navRes;
        window.__RCA.click.stages.push({
          name: 'resource_timing_top',
          t,
          top: navRes.slice(0, 8),
        });
      } catch (eRT) { /* ignore */ }
      // usable heuristic: main has content + no spinner
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          if (!window.__RCA.click) return;
          const main = document.querySelector('#rateb-main-content, main.rateb-content');
          const spins = document.querySelectorAll('.spinner-border:not([hidden])');
          const usable =
            main && (main.innerText || '').trim().length > 20 && spins.length === 0;
          if (usable && window.__RCA.click.usable_t == null) {
            window.__RCA.click.usable_t =
              Math.round((performance.now() - window.__RCA.click.mousedown_t) * 10) / 10;
            window.__RCA.click.stages.push({ name: 'usable_rAF', t: window.__RCA.click.usable_t });
          }
        });
      });
    });
  });

  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 180000 });
  await page.waitForTimeout(3000);

  // Warm SW once (production path users also warm)
  await page.evaluate(async () => {
    const reg = await navigator.serviceWorker.getRegistration();
    if (reg && reg.active) {
      await new Promise((resolve) => {
        const ch = new MessageChannel();
        const t = setTimeout(resolve, 60000);
        ch.port1.onmessage = () => {
          clearTimeout(t);
          resolve();
        };
        reg.active.postMessage({ type: 'WARM_ERP_OFFLINE_SHELL', force: true }, [ch.port2]);
      });
    }
  });
  await page.waitForTimeout(2000);

  // Expand nav groups
  await page.evaluate(() => {
    document.querySelectorAll('[data-nav-group-toggle], .rateb-nav-group-toggle').forEach((b) => {
      try {
        b.click();
      } catch (e) { /* ignore */ }
    });
    document.querySelectorAll('.rateb-nav-group-body').forEach((el) => {
      el.style.display = 'block';
      el.hidden = false;
    });
  });

  async function realClickModule(mod, pass) {
    // Ensure on a different page first for "first click" semantics when pass===1 from dashboard
    if (pass === 1 && mod.id !== 'dashboard') {
      const onDash = /\/admin\/?(\?|$)/.test(new URL(page.url()).pathname);
      if (!onDash) {
        await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
        await page.waitForTimeout(800);
        await page.evaluate(() => {
          document.querySelectorAll('[data-nav-group-toggle]').forEach((b) => {
            try {
              b.click();
            } catch (e) { /* ignore */ }
          });
          document.querySelectorAll('.rateb-nav-group-body').forEach((el) => {
            el.style.display = 'block';
          });
        });
      }
    }

    await page.evaluate(() => {
      window.__RCA.click = null;
      window.__RCA.prefetchRequests = [];
    });

    const found = await page.evaluate(({ prefer, matchSource }) => {
      document.querySelectorAll('[data-rca]').forEach((el) => el.removeAttribute('data-rca'));
      const re = new RegExp(matchSource);
      const links = [...document.querySelectorAll('#rateb-sidebar a[href], a.rateb-nav-link[href]')];
      let hit =
        links.find((a) => {
          try {
            return new URL(a.href).pathname.indexOf(prefer.replace(/\?.*$/, '')) !== -1
              || (a.getAttribute('href') || '').indexOf(prefer) !== -1;
          } catch (e) {
            return false;
          }
        }) || links.find((a) => re.test(a.href));
      // Prefer exact pathname end when multiple match (e.g. assets vs oversight)
      if (prefer.indexOf('/assets') !== -1) {
        hit =
          links.find((a) => {
            try {
              return /\/admin\/ops\/assets(\/|\?|$)/.test(new URL(a.href).pathname + (new URL(a.href).search || ''));
            } catch (e2) {
              return false;
            }
          }) || hit;
      }
      if (!hit) return { ok: false, n: links.length };
      hit.setAttribute('data-rca', '1');
      return { ok: true, href: hit.href, text: (hit.innerText || '').trim().slice(0, 40) };
    }, { prefer: mod.prefer, matchSource: mod.match.source });

    if (!found.ok) {
      return { id: mod.id, pass, error: 'link_not_found', found };
    }

    const wall0 = Date.now();
    // Real pointer sequence — first() avoids strict-mode if duplicates remain
    const loc = page.locator('a[data-rca="1"]').first();
    await loc.dispatchEvent('mousedown');
    await loc.click({ force: true });
    // Wait until afterEnter or timeout
    await page
      .waitForFunction(
        () => window.__RCA && window.__RCA.click && window.__RCA.click.afterEnter_t != null,
        null,
        { timeout: 20000 }
      )
      .catch(() => null);
    // Allow reinit + late fetches to settle briefly
    await page.waitForTimeout(400);
    const wall = Date.now() - wall0;

    const snap = await page.evaluate(() => {
      const c = window.__RCA.click;
      const prefs = window.__RCA.prefetchRequests.slice();
      const reinits = window.__RCA.reinitCalls.slice();
      const main = document.querySelector('#rateb-main-content');
      return {
        click: c,
        prefetchDuringSession: prefs,
        reinitAll: reinits.slice(-6),
        href: location.href,
        mainLen: main ? (main.innerText || '').length : 0,
        hasNav: !!window.RatebNavInstant,
        classification:
          c && c.afterEnter_t != null
            ? 'content_swap'
            : c && c.pushState_t != null
              ? 'partial'
              : 'unknown_or_full_reload',
      };
    });

    await page.evaluate(() => {
      document.querySelectorAll('[data-rca]').forEach((el) => el.removeAttribute('data-rca'));
    });

    return {
      id: mod.id,
      pass,
      wall_ms: wall,
      link: found,
      ...snap,
    };
  }

  // PART 1 + 3: first vs second for core modules
  const firstSecond = {};
  for (const mod of [
    MODULES.find((m) => m.id === 'hr'),
    MODULES.find((m) => m.id === 'inventory'),
    MODULES.find((m) => m.id === 'accounting'),
    MODULES.find((m) => m.id === 'crm'),
  ]) {
    console.error('[first]', mod.id);
    const first = await realClickModule(mod, 1);
    console.error('[second]', mod.id);
    const second = await realClickModule(mod, 2);
    firstSecond[mod.id] = {
      first,
      second,
      delta_wall_ms:
        first.wall_ms != null && second.wall_ms != null ? first.wall_ms - second.wall_ms : null,
      first_afterEnter_ms: first.click && first.click.afterEnter_t,
      second_afterEnter_ms: second.click && second.click.afterEnter_t,
      first_usable_ms: first.click && first.click.usable_t,
      second_usable_ms: second.click && second.click.usable_t,
      first_reinit_n: (first.click && first.click.reinitCalls && first.click.reinitCalls.length) || 0,
      second_reinit_n:
        (second.click && second.click.reinitCalls && second.click.reinitCalls.length) || 0,
      first_prefetch_parallel: (first.click && first.click.fetchDuring) || [],
      second_prefetch_parallel: (second.click && second.click.fetchDuring) || [],
    };
  }

  // PART 4: collect prefetch waterfall after idle on dashboard
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.evaluate(() => {
    window.__RCA.prefetchRequests = [];
  });
  await page.evaluate(() => {
    document.querySelectorAll('[data-nav-group-toggle]').forEach((b) => {
      try {
        b.click();
      } catch (e) { /* ignore */ }
    });
    document.querySelectorAll('.rateb-nav-group-body').forEach((el) => {
      el.style.display = 'block';
    });
  });
  // Trigger hover on several links
  const hoverPrefetch = await page.evaluate(async () => {
    const links = [...document.querySelectorAll('a.rateb-nav-link[href]')].slice(0, 15);
    for (const a of links) {
      a.dispatchEvent(new PointerEvent('pointerenter', { bubbles: true }));
      a.dispatchEvent(new FocusEvent('focus', { bubbles: true }));
    }
    await new Promise((r) => setTimeout(r, 5000));
    return window.__RCA.prefetchRequests.slice();
  });

  // PART 7: per-module first click from dashboard (online)
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(1000);
  const perModule = {};
  for (const mod of MODULES) {
    if (mod.id === 'dashboard') continue;
    console.error('[module]', mod.id);
    try {
      await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 90000 }).catch(() => {});
      await page.waitForTimeout(500);
      await page.evaluate(() => {
        document.querySelectorAll('[data-nav-group-toggle]').forEach((b) => {
          try {
            b.click();
          } catch (e) { /* ignore */ }
        });
        document.querySelectorAll('.rateb-nav-group-body').forEach((el) => {
          el.style.display = 'block';
        });
      });
      perModule[mod.id] = await realClickModule(mod, 1);
    } catch (eMod) {
      perModule[mod.id] = { id: mod.id, pass: 1, error: String(eMod && eMod.message ? eMod.message : eMod) };
    }
  }

  // PART 6: offline first+second HR
  await page.goto(BASE + '/admin/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(1500);
  // ensure HR cached via one online click first
  await page.evaluate(() => {
    document.querySelectorAll('[data-nav-group-toggle]').forEach((b) => {
      try {
        b.click();
      } catch (e) { /* ignore */ }
    });
    document.querySelectorAll('.rateb-nav-group-body').forEach((el) => {
      el.style.display = 'block';
    });
  });
  await realClickModule(MODULES.find((m) => m.id === 'hr'), 1);
  await context.setOffline(true);
  const offlineHr1 = await realClickModule(MODULES.find((m) => m.id === 'hr'), 1);
  const offlineHr2 = await realClickModule(MODULES.find((m) => m.id === 'hr'), 2);
  const offlineInv = await realClickModule(MODULES.find((m) => m.id === 'inventory'), 1);
  await context.setOffline(false);

  // Reinit call tree sample from accumulated
  const reinitTree = await page.evaluate(() => {
    return (window.__RCA.reinitCalls || []).slice(-20);
  });

  // Build top bottlenecks from measured numbers only
  const bottlenecks = [];

  function addBn(b) {
    bottlenecks.push(b);
  }

  // Prefetch storm from first HR click
  const hrFirstFetches = (firstSecond.hr && firstSecond.hr.first_prefetch_parallel) || [];
  hrFirstFetches
    .filter((f) => f.ms > 100)
    .forEach((f) => {
      addBn({
        name: 'Parallel fetch during first HR click: ' + f.url,
        ms: f.ms,
        file: 'public/assets/js/erp-nav-instant.js',
        function: f.isPrefetch ? 'prefetchUrl' : f.isSwap ? 'fetchHtml' : 'fetch',
        line: f.isPrefetch ? 'prefetchUrl' : 'fetchHtml/idlePrefetchVisible',
        stack: f.stack,
        pct_of_click:
          firstSecond.hr.first.wall_ms > 0
            ? Math.round((f.ms / firstSecond.hr.first.wall_ms) * 1000) / 10
            : null,
      });
    });

  Object.entries(firstSecond).forEach(([id, pair]) => {
    if (pair.first_afterEnter_ms != null) {
      addBn({
        name: `First click afterEnter (${id})`,
        ms: pair.first_afterEnter_ms,
        file: 'erp-nav-instant.js',
        function: 'swapTo→afterEnter',
        line: 'afterEnter lifecycle',
        stack: ['sidebar mousedown→click→swapTo'],
        pct_of_click:
          pair.first.wall_ms > 0
            ? Math.round((pair.first_afterEnter_ms / pair.first.wall_ms) * 1000) / 10
            : null,
      });
    }
    if (pair.second_afterEnter_ms != null) {
      addBn({
        name: `Second click afterEnter (${id})`,
        ms: pair.second_afterEnter_ms,
        file: 'erp-nav-instant.js',
        function: 'swapTo→afterEnter',
        line: 'afterEnter lifecycle',
        stack: ['sidebar click warm'],
      });
    }
    (pair.first.click && pair.first.click.reinitCalls ? pair.first.click.reinitCalls : []).forEach(
      (r, i) => {
        addBn({
          name: `RatebApp.reinit #${i + 1} on first ${id}`,
          ms: r.ms,
          file: 'public/assets/js/app.js + erp-nav-instant.js',
          function: 'RatebApp.reinit',
          line: 'app.js ~314–322; erp-nav-instant reinitModuleUi ~251–254',
          stack: r.stack,
        });
      }
    );
  });

  phpFirstSecond.forEach((row) => {
    addBn({
      name: `Origin PHP TTFB ${row.path} pass${row.pass}`,
      ms: row.ttfb_ms,
      file: 'public/index.php → controller → views/layouts/main.php',
      function: 'full document PHP',
      line: 'opaque (Server-Timing not emitted)',
      stack: ['curl origin'],
    });
  });

  if (offlineHr1.click && offlineHr1.click.afterEnter_t != null) {
    addBn({
      name: 'Offline HR afterEnter',
      ms: offlineHr1.click.afterEnter_t,
      file: 'erp-nav-instant.js',
      function: 'swapTo fromCache',
      line: 'fetchHtml→matchCachedHtml',
      stack: ['offline sidebar click'],
    });
  }

  hoverPrefetch
    .filter((p) => p.ms > 200)
    .forEach((p) => {
      addBn({
        name: 'Hover/idle prefetch ' + p.url,
        ms: p.ms,
        file: 'erp-nav-instant.js',
        function: 'prefetchUrl',
        line: '~100–120',
        stack: p.stack,
      });
    });

  bottlenecks.sort((a, b) => (b.ms || 0) - (a.ms || 0));
  const top20 = bottlenecks.slice(0, 20);

  // Score: crude from evidence (not opinion): based on first-click afterEnter vs target 200
  const firstAe = Object.values(firstSecond)
    .map((p) => p.first_afterEnter_ms)
    .filter((x) => typeof x === 'number');
  const avgFirstAe =
    firstAe.length > 0 ? Math.round(firstAe.reduce((a, b) => a + b, 0) / firstAe.length) : null;
  const score =
    avgFirstAe == null
      ? null
      : Math.max(0, Math.min(100, Math.round(100 - (avgFirstAe / 500) * 100)));

  const report = {
    phase: 'ENTERPRISE_ROOT_CAUSE_AUDIT',
    mode: 'READ_ONLY_EVIDENCE_NO_FIXES',
    at: new Date().toISOString(),
    note: 'Primary metric = real sidebar mousedown→afterEnter/usable. API navigate() is NOT used as success metric.',
    server_timing: serverTimingWhy,
    php_first_second_curl: phpFirstSecond,
    opcache: opcacheStatus,
    cli_php_profile_present: !!cliPhpProfile && !cliPhpProfile.error,
    cli_php_profile_spans:
      cliPhpProfile && cliPhpProfile.spans
        ? Object.keys(cliPhpProfile.spans).slice(0, 40)
        : cliPhpProfile && cliPhpProfile.summary
          ? cliPhpProfile.summary
          : null,
    first_vs_second: firstSecond,
    hover_idle_prefetch_waterfall: hoverPrefetch,
    per_module_first_click: Object.fromEntries(
      Object.entries(perModule).map(([id, row]) => [
        id,
        {
          wall_ms: row.wall_ms,
          afterEnter_ms: row.click && row.click.afterEnter_t,
          usable_ms: row.click && row.click.usable_t,
          fromCache: row.click && row.click.afterEnter_detail && row.click.afterEnter_detail.fromCache,
          classification: row.classification,
          reinit_n: (row.click && row.click.reinitCalls && row.click.reinitCalls.length) || 0,
          reinit_ms_sum: ((row.click && row.click.reinitCalls) || []).reduce((a, c) => a + (c.ms || 0), 0),
          parallel_fetch_n: ((row.click && row.click.fetchDuring) || []).length,
          parallel_fetch_max_ms: Math.max(
            0,
            ...((row.click && row.click.fetchDuring) || []).map((f) => f.ms || 0)
          ),
          error: row.error || null,
        },
      ])
    ),
    offline: {
      hr_first: {
        wall_ms: offlineHr1.wall_ms,
        afterEnter_ms: offlineHr1.click && offlineHr1.click.afterEnter_t,
        fromCache: offlineHr1.click && offlineHr1.click.afterEnter_detail && offlineHr1.click.afterEnter_detail.fromCache,
        stages: offlineHr1.click && offlineHr1.click.stages,
        reinit: offlineHr1.click && offlineHr1.click.reinitCalls,
      },
      hr_second: {
        wall_ms: offlineHr2.wall_ms,
        afterEnter_ms: offlineHr2.click && offlineHr2.click.afterEnter_t,
        fromCache: offlineHr2.click && offlineHr2.click.afterEnter_detail && offlineHr2.click.afterEnter_detail.fromCache,
      },
      inventory_first: {
        wall_ms: offlineInv.wall_ms,
        afterEnter_ms: offlineInv.click && offlineInv.click.afterEnter_t,
        fromCache: offlineInv.click && offlineInv.click.afterEnter_detail && offlineInv.click.afterEnter_detail.fromCache,
        classification: offlineInv.classification,
      },
    },
    reinit_call_tree_sample: reinitTree,
    top20_bottlenecks: top20,
    overall_performance_score: score,
    root_cause_summary: {
      online_first_slow:
        'First sidebar click content-swap afterEnter is tens–hundreds of ms when HTML not cached; concurrent prefetchUrl/idlePrefetchVisible issues full-document network fetches (hundreds–2000+ ms) that saturate bandwidth during the click. Second click afterEnter drops when Cache API hit (fromCache).',
      offline_slow:
        'When fromCache=true, afterEnter is low tens of ms; perceived slowness includes duplicate RatebApp.reinit (2×) and any failed cache miss falling back. Measure offline.afterEnter_ms vs wall_ms in this report.',
      server_timing_missing:
        'ServerTiming::flush() aborted by headers_sent() after HTML body started — see Part 2.',
      benchmark_rejected:
        'Prior 16ms measured navigate() API post-prefetch, not mousedown→usable on sidebar <a>.',
    },
    elapsed_ms: Date.now() - tReport,
  };

  // Attach CLI spans if small enough
  if (cliPhpProfile && cliPhpProfile.spans && typeof cliPhpProfile.spans === 'object') {
    const spans = [];
    Object.values(cliPhpProfile.spans).forEach((s) => {
      if (s && typeof s.dur_ms === 'number') {
        spans.push({
          id: s.id,
          label: s.label,
          dur_ms: s.dur_ms,
          sql_count: s.sql_count,
        });
      }
    });
    spans.sort((a, b) => b.dur_ms - a.dur_ms);
    report.cli_php_top_spans = spans.slice(0, 25);
  }

  const out = path.join(OUT_DIR, `enterprise-rca-${tReport}.json`);
  fs.writeFileSync(out, JSON.stringify(report, null, 2));
  fs.writeFileSync(path.join(OUT_DIR, 'enterprise-rca-latest.json'), JSON.stringify(report, null, 2));

  console.log(
    JSON.stringify(
      {
        out,
        score: report.overall_performance_score,
        server_timing: {
          has_header: serverHeaderProbe.has_server_timing,
          why_line: 55,
          ttfb_ms: serverHeaderProbe.ttfb_ms,
        },
        first_vs_second: Object.fromEntries(
          Object.entries(firstSecond).map(([k, v]) => [
            k,
            {
              first_ae: v.first_afterEnter_ms,
              second_ae: v.second_afterEnter_ms,
              first_wall: v.first.wall_ms,
              second_wall: v.second.wall_ms,
              first_reinit_n: v.first_reinit_n,
              prefetch_max: Math.max(0, ...v.first_prefetch_parallel.map((f) => f.ms || 0)),
            },
          ])
        ),
        offline: report.offline,
        top10: top20.slice(0, 10),
        per_module: report.per_module_first_click,
      },
      null,
      2
    )
  );

  await context.close();
  process.exit(0);
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
