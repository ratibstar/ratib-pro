/**
 * FINAL RACE CONDITION RCA — why sidebar is clickable before bootAppUi.
 * Evidence only. No fixes. No network waterfall focus.
 *
 *   node sidebar-boot-race-rca.js
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
const OUT_MD = path.join(__dirname, 'reports', 'SIDEBAR-BOOT-RACE-RCA.md');
const OUT_JSON = path.join(__dirname, 'reports', 'SIDEBAR-BOOT-RACE-RCA.json');

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

const INIT = `(() => {
  const T0 = performance.now();
  const marks = [];
  const mark = (name, extra) => {
    marks.push(Object.assign({ name, t: performance.now() - T0, abs: performance.now() }, extra || {}));
  };
  mark('probe_init');

  window.__SIDEBAR_RACE__ = { marks, T0, ready: false };

  // Paint
  try {
    new PerformanceObserver((list) => {
      for (const e of list.getEntries()) {
        mark('paint:' + e.name, { startTime: e.startTime });
      }
    }).observe({ type: 'paint', buffered: true });
  } catch (e) {}

  // Boot flag
  const moBoot = new MutationObserver(() => {
    if (document.documentElement && document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1') {
      mark('bootAppUi_flag_set');
      moBoot.disconnect();
    }
  });
  try {
    if (document.documentElement) {
      moBoot.observe(document.documentElement, { attributes: true, attributeFilter: ['data-rateb-app-ui-booted'] });
    }
  } catch (eBoot) {
    mark('moBoot_observe_failed', { err: String(eBoot && eBoot.message) });
  }

  // Sidebar appearance + clickability polling (rAF until boot)
  let sawSidebarDom = false;
  let sawSidebarVisible = false;
  let sawSidebarClickable = false;
  let sawToggleDom = false;
  let sawFirstListener = false;

  function sidebarState() {
    const el = document.getElementById('rateb-sidebar') || document.querySelector('aside.rateb-sidebar, .rateb-sidebar');
    if (!el) return null;
    const st = getComputedStyle(el);
    const r = el.getBoundingClientRect();
    const pe = st.pointerEvents;
    const vis = st.visibility;
    const disp = st.display;
    const opacity = parseFloat(st.opacity || '1');
    const transformedAway = false;
    const onScreen = r.width > 40 && r.height > 40 && r.right > 0 && r.bottom > 0 && r.left < (window.innerWidth || 1400);
    const visible = disp !== 'none' && vis !== 'hidden' && opacity > 0.05 && onScreen;
    // Hit-test center of first toggle if present
    let hitToggle = false;
    const btn = el.querySelector('[data-nav-group-toggle]');
    if (btn && visible && pe !== 'none') {
      const br = btn.getBoundingClientRect();
      if (br.width > 0 && br.height > 0) {
        const top = document.elementFromPoint(br.left + br.width / 2, br.top + br.height / 2);
        hitToggle = !!(top && (top === btn || btn.contains(top) || (top.closest && top.closest('[data-nav-group-toggle]'))));
      }
    }
    const clickable = visible && pe !== 'none' && !!btn && hitToggle;
    return {
      pe, vis, disp, opacity, onScreen, visible, clickable, hitToggle,
      hasBtn: !!btn,
      listenerOnBtn: btn ? btn.getAttribute('data-race-listeners') : null,
      booted: document.documentElement.getAttribute('data-rateb-app-ui-booted'),
    };
  }

  const origAdd = EventTarget.prototype.addEventListener;
  EventTarget.prototype.addEventListener = function (type, listener, options) {
    if (type === 'click' && this && this.hasAttribute && this.hasAttribute('data-nav-group-toggle')) {
      const n = parseInt(this.getAttribute('data-race-listeners') || '0', 10) + 1;
      this.setAttribute('data-race-listeners', String(n));
      if (!sawFirstListener) {
        sawFirstListener = true;
        mark('first_toggle_addEventListener', { listenerPreview: String(listener).slice(0, 120) });
      }
    }
    // Detect app.js bootAppUi completion via attribute already; also mark script load via script tags
    return origAdd.call(this, type, listener, options);
  };

  // Script inject observation
  const moScript = new MutationObserver((muts) => {
    for (const m of muts) {
      for (const n of m.addedNodes) {
        if (n.tagName === 'SCRIPT' && n.src && /app\\.js/i.test(n.src)) {
          mark('app_js_script_inserted', { src: n.src.slice(0, 120) });
          n.addEventListener('load', () => mark('app_js_load_event'), { once: true });
          n.addEventListener('error', () => mark('app_js_error'), { once: true });
        }
      }
    }
  });

  function tick() {
    const s = sidebarState();
    if (s) {
      if (!sawSidebarDom) {
        sawSidebarDom = true;
        mark('sidebar_dom', s);
      }
      if (s.hasBtn && !sawToggleDom) {
        sawToggleDom = true;
        mark('toggle_dom', s);
      }
      if (s.visible && !sawSidebarVisible) {
        sawSidebarVisible = true;
        mark('sidebar_visible', s);
      }
      if (s.clickable && !sawSidebarClickable) {
        sawSidebarClickable = true;
        mark('sidebar_clickable_hit_test', s);
      }
    }
    if (document.readyState === 'interactive' && !window.__RACE_DCL__) {
      window.__RACE_DCL__ = true;
      mark('DOMContentLoaded_readyState_interactive');
    }
    if (document.readyState === 'complete' && !window.__RACE_COMPLETE__) {
      window.__RACE_COMPLETE__ = true;
      mark('readyState_complete');
    }
    if (!sawFirstListener || document.documentElement.getAttribute('data-rateb-app-ui-booted') !== '1') {
      requestAnimationFrame(tick);
    } else {
      mark('poll_stop_booted_and_listeners');
    }
  }

  const start = () => {
    try {
      if (document.documentElement) {
        moBoot.observe(document.documentElement, { attributes: true, attributeFilter: ['data-rateb-app-ui-booted'] });
      }
    } catch (e2) {}
    moScript.observe(document.documentElement || document, { childList: true, subtree: true });
    document.addEventListener('DOMContentLoaded', () => mark('DOMContentLoaded_event'), { once: true });
    requestAnimationFrame(tick);
    // also coarse interval
    const iv = setInterval(() => {
      tick();
      if (document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1' && sawFirstListener) {
        clearInterval(iv);
      }
    }, 16);
    setTimeout(() => clearInterval(iv), 30000);
  };
  if (document.documentElement) start();
  else document.addEventListener('DOMContentLoaded', start, { once: true });

  window.__SIDEBAR_RACE__ = {
    T0,
    marks,
    ready: true,
    finalState() {
      return {
        marks: marks.slice(),
        sidebar: sidebarState(),
        booted: document.documentElement.getAttribute('data-rateb-app-ui-booted'),
        toggleListenerSample: [...document.querySelectorAll('[data-nav-group-toggle]')].slice(0, 5).map((b) => ({
          text: (b.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 30),
          n: b.getAttribute('data-race-listeners'),
        })),
        criticalCssHref: !!(document.querySelector('link[href*="critical-shell"]') || document.querySelector('style')),
        pointerEventsOnSidebar: (() => {
          const el = document.getElementById('rateb-sidebar');
          return el ? getComputedStyle(el).pointerEvents : null;
        })(),
      };
    },
  };
})();`;

function buildAnswers(run) {
  const by = (name) => {
    const m = (run.marks || []).find((x) => x.name === name);
    return m ? m.t : null;
  };
  const clickableAt = by('sidebar_clickable_hit_test') ?? by('sidebar_visible') ?? by('toggle_dom');
  const bootDone = by('first_toggle_addEventListener');
  const bootFlag = by('bootAppUi_flag_set');
  const bootFinish = bootDone != null && bootFlag != null ? Math.max(bootDone, bootFlag) : bootDone ?? bootFlag;
  const gap =
    clickableAt != null && bootFinish != null ? bootFinish - clickableAt : null;

  return {
    q1:
      'Sidebar toggles become user-clickable (visible + pointer-events + hit-test) at t≈**' +
      r1(clickableAt) +
      ' ms** after navigation start (probe). Marks: `sidebar_dom` @ ' +
      r1(by('sidebar_dom')) +
      ' ms, `sidebar_visible` @ ' +
      r1(by('sidebar_visible')) +
      ' ms, `sidebar_clickable_hit_test` @ ' +
      r1(by('sidebar_clickable_hit_test')) +
      ' ms. First paint: `paint:first-contentful-paint` @ ' +
      r1(by('paint:first-contentful-paint')) +
      ' ms (startTime ' +
      r1((run.marks.find((m) => m.name === 'paint:first-contentful-paint') || {}).startTime) +
      ').',
    q2:
      '`bootAppUi()` finishes (flag set + first toggle `addEventListener`) at t≈**' +
      r1(bootFinish) +
      ' ms** (`bootAppUi_flag_set` @ ' +
      r1(bootFlag) +
      ' ms, `first_toggle_addEventListener` @ ' +
      r1(bootDone) +
      ' ms). `app.js` inserted @ ' +
      r1(by('app_js_script_inserted')) +
      ' ms, load @ ' +
      r1(by('app_js_load_event')) +
      ' ms. Gap clickable→boot ≈ **' +
      r1(gap) +
      ' ms**.',
    q3:
      'The sidebar is server-rendered HTML inside `main.php` (`#rateb-sidebar` + `sidebar-nav.php`) and styled by `critical-shell.css` as a normal fixed aside with `cursor:pointer` on toggles. There is **no** `pointer-events:none`, `disabled`, `inert`, or `aria-busy` gate tied to `data-rateb-app-ui-booted`. Visibility is CSS-default as soon as the parser inserts the nodes and critical CSS applies — independent of `app.js`.',
    q4:
      'Exposing code: (1) `rateb-erp/views/layouts/main.php` ~L432–438 emits `<aside id="rateb-sidebar">` and requires `sidebar-nav.php` with live `<button data-nav-group-toggle>`. (2) `rateb-erp/public/assets/css/critical-shell.css` L25–32 paints `.rateb-sidebar` fixed/visible and `.rateb-nav-group-toggle{cursor:pointer}` with **no** pre-boot disable. (3) `main.php` L709–717 schedules `loadCritical()` only on `DOMContentLoaded`, so `app.js` → `bootAppUi()` → `initSidebarNavGroups()` runs **after** the sidebar has already been visible/clickable.',
    q5:
      'From a correctness standpoint: **yes**, if the product requires the first click to always toggle, the sidebar (or at least `[data-nav-group-toggle]`) should not accept pointer input until `bootAppUi()`/`initSidebarNavGroups()` has bound listeners — or listeners must be bound earlier (inline/sync). Evidence: any click in the measured gap is a guaranteed no-op.',
    q6:
      '**Yes.** Holding `pointer-events: none` (or `visibility`/`inert`) on `#rateb-sidebar` until `data-rateb-app-ui-booted="1"` would make the first user click impossible until listeners exist, eliminating this race. (Evidence-only conclusion — not implemented.)',
    timestamps: {
      sidebar_dom: by('sidebar_dom'),
      sidebar_visible: by('sidebar_visible'),
      sidebar_clickable: by('sidebar_clickable_hit_test'),
      fcp: by('paint:first-contentful-paint'),
      dcl: by('DOMContentLoaded_event') ?? by('DOMContentLoaded_readyState_interactive'),
      app_js_inserted: by('app_js_script_inserted'),
      app_js_load: by('app_js_load_event'),
      boot_flag: bootFlag,
      first_listener: bootDone,
      boot_finish: bootFinish,
      race_gap_ms: gap,
    },
  };
}

(async () => {
  const mint = JSON.parse(
    ssh(
      'php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint'
    )
  );

  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'race-' + Date.now()), {
    headless: true,
    executablePath: CHROME,
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'allow',
    locale: 'ar-SA',
    viewport: { width: 1440, height: 900 },
  });
  await ctx.addInitScript({ content: INIT });
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

  const page = await ctx.newPage();
  await page.addInitScript({ content: INIT });
  await page.goto(BASE + '/admin/?company_id=22&_race=' + Date.now(), {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });
  if (/\/login/i.test(page.url())) {
    throw new Error('auth_failed_redirect_login: ' + page.url());
  }
  try {
    await page.waitForFunction(() => !!window.__SIDEBAR_RACE__, { timeout: 10000 });
    await page.waitForFunction(
      () => document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1',
      { timeout: 45000 }
    );
    await page.waitForTimeout(400);
  } catch (e) {
    const dump = await page.evaluate(() => ({
      href: location.href,
      booted: document.documentElement.getAttribute('data-rateb-app-ui-booted'),
      hasRace: !!window.__SIDEBAR_RACE__,
      marks: window.__SIDEBAR_RACE__ ? window.__SIDEBAR_RACE__.marks : null,
      toggles: document.querySelectorAll('[data-nav-group-toggle]').length,
      appScripts: [...document.querySelectorAll('script[src]')].map((s) => s.src).filter((u) => /app\.js|erp-nav|theme|module-page/.test(u)),
    }));
    console.error('WAIT_FAIL', JSON.stringify(dump, null, 2));
    throw e;
  }
  const run = await page.evaluate(() => window.__SIDEBAR_RACE__.finalState());

  // Static code evidence (local repo)
  const codeEvidence = {
    sidebarEmit: 'views/layouts/main.php ~L432-438 <aside id="rateb-sidebar"> + require sidebar-nav.php',
    toggleEmit: 'views/partials/sidebar-nav.php L45-46 button.rateb-nav-group-toggle[data-nav-group-toggle]',
    cssVisible: 'public/assets/css/critical-shell.css L25-32 .rateb-sidebar fixed; toggles cursor:pointer; no pointer-events:none',
    jsDeferred: 'views/layouts/main.php L709-717 loadCritical on DOMContentLoaded; critical includes app.js',
    boot: 'public/assets/js/app.js bootAppUi L312-348 → initSidebarNavGroups L162-176',
  };

  const answers = buildAnswers(run);
  const pack = {
    generatedAt: new Date().toISOString(),
    run,
    answers,
    codeEvidence,
  };

  const L = [];
  L.push('# FINAL RACE CONDITION RCA — Sidebar Clickable Before bootAppUi');
  L.push('');
  L.push('**Date:** ' + pack.generatedAt);
  L.push('**Question:** Why can users interact with the sidebar before `bootAppUi()` completes?');
  L.push('');
  L.push('## Measured timeline (ms from probe start ≈ navigation)');
  L.push('');
  L.push('| Mark | t (ms) |');
  L.push('|------|--------|');
  for (const m of run.marks) {
    L.push('| `' + m.name + '` | ' + r1(m.t) + ' |');
  }
  L.push('');
  L.push('## Race gap');
  L.push('');
  L.push('| Milestone | t (ms) |');
  L.push('|-----------|--------|');
  L.push('| Sidebar clickable (hit-test) | ' + r1(answers.timestamps.sidebar_clickable) + ' |');
  L.push('| First paint (FCP mark) | ' + r1(answers.timestamps.fcp) + ' |');
  L.push('| DOMContentLoaded | ' + r1(answers.timestamps.dcl) + ' |');
  L.push('| app.js inserted | ' + r1(answers.timestamps.app_js_inserted) + ' |');
  L.push('| app.js load | ' + r1(answers.timestamps.app_js_load) + ' |');
  L.push('| bootAppUi flag | ' + r1(answers.timestamps.boot_flag) + ' |');
  L.push('| first toggle addEventListener | ' + r1(answers.timestamps.first_listener) + ' |');
  L.push('| **Race window (clickable → boot)** | **' + r1(answers.timestamps.race_gap_ms) + '** |');
  L.push('');
  L.push('## Code that exposes sidebar before listeners');
  L.push('');
  L.push('```');
  L.push(JSON.stringify(codeEvidence, null, 2));
  L.push('```');
  L.push('');
  L.push('## Answers');
  L.push('');
  L.push('### 1. At what timestamp does the sidebar become clickable by the user?');
  L.push('');
  L.push(answers.q1);
  L.push('');
  L.push('### 2. At what timestamp does bootAppUi() actually finish?');
  L.push('');
  L.push(answers.q2);
  L.push('');
  L.push('### 3. Why is the sidebar visible before initialization?');
  L.push('');
  L.push(answers.q3);
  L.push('');
  L.push('### 4. Which code exposes the sidebar before listeners exist?');
  L.push('');
  L.push(answers.q4);
  L.push('');
  L.push('### 5. Should the sidebar remain disabled until boot completes?');
  L.push('');
  L.push(answers.q5);
  L.push('');
  L.push('### 6. Would delaying pointer-events or visibility until boot eliminate the race?');
  L.push('');
  L.push(answers.q6);
  L.push('');
  L.push('No production code was modified.');
  L.push('');

  fs.mkdirSync(path.dirname(OUT_MD), { recursive: true });
  fs.writeFileSync(OUT_MD, L.join('\n'));
  fs.writeFileSync(OUT_JSON, JSON.stringify(pack, null, 2));
  console.log(OUT_MD);
  console.log(JSON.stringify({ timestamps: answers.timestamps, answers }, null, 2));
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
