/**
 * INPUT LATENCY RCA — evidence only (no production changes).
 * Measures what happens BEFORE erp-nav-instant onClick / swapTo.
 *
 *   node input-latency-rca.js
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
const OUT_JSON = path.join(__dirname, 'reports', 'INPUT-LATENCY-RCA-' + STAMP + '.json');
const OUT_MD = path.join(__dirname, 'reports', 'INPUT-LATENCY-RCA.md');

const SCENARIOS = [
  {
    id: 'Inventory_idle',
    label: 'Inventory after idle boot',
    match: /\/admin\/ops\/inventory(\/|$|\?)/i,
    group: /المخزون|inventory/i,
    waitAfterBootMs: 2500,
  },
  {
    id: 'Inventory_asap',
    label: 'Inventory ASAP after nav scripts ready',
    match: /\/admin\/ops\/inventory(\/|$|\?)/i,
    group: /المخزون|inventory/i,
    waitAfterBootMs: 0,
  },
  {
    id: 'Purchasing_idle',
    label: 'Purchasing after idle boot',
    match: /\/admin\/ops\/purchase-requests(\/|$|\?)/i,
    group: /المشتريات|procurement|purchas/i,
    waitAfterBootMs: 2500,
  },
  {
    id: 'HR_asap',
    label: 'HR ASAP after nav scripts ready',
    match: /\/admin\/hr(\/|$|\?)/i,
    group: /الموارد البشرية|\bhr\b/i,
    waitAfterBootMs: 0,
  },
  {
    id: 'Inventory_during_busy',
    label: 'Inventory while main thread busy (natural deferred work window)',
    match: /\/admin\/ops\/inventory(\/|$|\?)/i,
    group: /المخزون|inventory/i,
    waitAfterBootMs: 100,
    clickDuringBusyProbe: true,
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

/**
 * Injected as early as possible (addInitScript) so we wrap listeners before app JS.
 */
function earlyProbe() {
  if (window.__INPUT_RCA__) return;
  const N = (window.__INPUT_RCA__ = {
    armed: false,
    targetHref: null,
    wallArm: null,
    marks: {},
    listenerHits: [],
    longTasks: [],
    eventTimings: [],
    layoutish: [],
    rafSamples: [],
    syncCosts: [],
    clickListeners: { capture: [], bubble: [] },
    raw: [],
  });
  const now = () => performance.now();
  const push = (type, detail) => N.raw.push(Object.assign({ t: now(), type }, detail || {}));

  // ---- Long tasks ----
  try {
    const po = new PerformanceObserver((list) => {
      for (const e of list.getEntries()) {
        if (e.duration >= 50) {
          N.longTasks.push({
            t: now(),
            start: e.startTime,
            duration: e.duration,
            name: e.name || 'longtask',
            attribution: (e.attribution && e.attribution[0] && e.attribution[0].name) || null,
          });
        }
      }
    });
    po.observe({ type: 'longtask', buffered: true });
  } catch (e) { /* ignore */ }

  // ---- Event Timing (INP building block) ----
  try {
    const poE = new PerformanceObserver((list) => {
      for (const e of list.getEntries()) {
        const inputDelay = e.processingStart - e.startTime;
        const proc = e.processingEnd - e.processingStart;
        N.eventTimings.push({
          t: now(),
          name: e.name,
          entryType: e.entryType,
          startTime: e.startTime,
          processingStart: e.processingStart,
          processingEnd: e.processingEnd,
          duration: e.duration,
          inputDelay,
          processingTime: proc,
          presentationDelay: e.duration - (e.processingEnd - e.startTime),
          interactionId: e.interactionId || null,
          target: e.target ? (e.target.tagName || '') + (e.target.id ? '#' + e.target.id : '') : null,
        });
      }
    });
    poE.observe({ type: 'event', buffered: true, durationThreshold: 16 });
  } catch (e2) { /* ignore */ }

  // ---- Wrap addEventListener to inventory click/pointer/mouse on window/document ----
  const origAEL = EventTarget.prototype.addEventListener;
  EventTarget.prototype.addEventListener = function (type, listener, options) {
    const capture =
      options === true || (options && typeof options === 'object' && !!options.capture);
    const isDoc = this === document || this === window;
    if (isDoc && (type === 'click' || type === 'pointerdown' || type === 'mousedown')) {
      const src = (listener && listener.name) || 'anonymous';
      const stack = new Error().stack || '';
      const stackLine = stack
        .split('\n')
        .slice(2, 6)
        .map((s) => s.trim())
        .join(' | ');
      if (type === 'click') {
        (capture ? N.clickListeners.capture : N.clickListeners.bubble).push({
          src,
          stackLine: stackLine.slice(0, 240),
          t: now(),
        });
      }
      const wrapped = function (ev) {
        if (N.armed) {
          const href =
            ev.target && ev.target.closest
              ? (ev.target.closest('a[href]') || {}).href
              : null;
          const relevant =
            !N.targetHref ||
            (href && href.indexOf(new URL(N.targetHref, location.href).pathname) !== -1) ||
            (N.marks.pointerdown != null && now() - N.marks.pointerdown < 10000);
          if (relevant || type !== 'click') {
            N.listenerHits.push({
              t: now(),
              type,
              phase: capture ? 'capture' : 'bubble',
              target: this === window ? 'window' : this === document ? 'document' : 'other',
              src,
              defaultPrevented: !!(ev && ev.defaultPrevented),
              eventTimeStamp: ev && ev.timeStamp,
            });
            push('listener', { type, phase: capture ? 'capture' : 'bubble', src });
          }
        }
        if (typeof listener === 'function') return listener.apply(this, arguments);
        if (listener && typeof listener.handleEvent === 'function') return listener.handleEvent(ev);
      };
      try {
        Object.defineProperty(wrapped, 'name', { value: 'rca$' + src });
      } catch (eN) { /* ignore */ }
      return origAEL.call(this, type, wrapped, options);
    }
    return origAEL.call(this, type, listener, options);
  };

  // ---- First-chance input marks on window capture ----
  function markInput(name, ev) {
    if (!N.armed) return;
    if (N.marks[name] == null) {
      N.marks[name] = now();
      N.marks[name + '_eventTimeStamp'] = ev && ev.timeStamp;
      N.marks[name + '_wall'] = Date.now();
      push(name, { eventTimeStamp: ev && ev.timeStamp });
    }
  }
  window.addEventListener(
    'pointerdown',
    (ev) => {
      markInput('pointerdown', ev);
      // schedule rAF to measure frame delay after pointerdown
      if (N.armed && N.rafSamples.length < 5) {
        const t0 = now();
        requestAnimationFrame(() => {
          const t1 = now();
          requestAnimationFrame(() => {
            N.rafSamples.push({ after: 'pointerdown', delay1: t1 - t0, delay2: now() - t1 });
          });
        });
      }
    },
    true
  );
  window.addEventListener('mousedown', (ev) => markInput('mousedown', ev), true);
  window.addEventListener(
    'click',
    (ev) => {
      markInput('click_window_capture', ev);
      // Measure sync layout/style forced by reading layout around click
      if (N.armed) {
        try {
          const t0 = now();
          const el = ev.target;
          if (el && el.getBoundingClientRect) {
            el.getBoundingClientRect();
            document.body && document.body.offsetHeight;
          }
          const forced = now() - t0;
          N.syncCosts.push({ kind: 'forced_reflow_on_click_capture', ms: forced });
        } catch (eR) { /* ignore */ }
      }
    },
    true
  );
  window.addEventListener(
    'click',
    (ev) => {
      markInput('click_window_bubble', ev);
    },
    false
  );

  N.arm = (href) => {
    N.armed = true;
    N.targetHref = href;
    N.wallArm = Date.now();
    N.marks = {};
    N.listenerHits = [];
    N.rafSamples = [];
    N.syncCosts = [];
    // keep longTasks/eventTimings accumulating but snapshot baseline index
    N.ltBaseline = N.longTasks.length;
    N.etBaseline = N.eventTimings.length;
    push('arm', { href });
  };

  N.notePlaywrightInput = (phase) => {
    N.marks['pw_' + phase] = now();
    N.marks['pw_' + phase + '_wall'] = Date.now();
    push('pw_' + phase, {});
  };

  N.patchNav = () => {
    const api = window.RatebNavInstant;
    if (!api || api.__inputRca) return !!api;
    api.__inputRca = true;
    const orig = api.navigate.bind(api);
    api.navigate = function (href, opts) {
      if (N.armed) {
        if (N.marks.swapTo == null) N.marks.swapTo = now();
        if (N.marks.onClick_nav_entry == null) N.marks.onClick_nav_entry = N.marks.swapTo;
        push('swapTo', { href: String(href).slice(0, 160) });
      }
      return orig(href, opts);
    };
    // Wrap fetch to mark fetchHtml network start
    if (!window.__inputRcaFetch) {
      window.__inputRcaFetch = true;
      const of = window.fetch.bind(window);
      window.fetch = function (input, init) {
        const headers = (init && init.headers) || {};
        const hv = (k) => {
          try {
            if (headers.get) return headers.get(k);
            return headers[k] || headers[k.toLowerCase()];
          } catch (e) {
            return null;
          }
        };
        if (N.armed && (hv('X-Rateb-Nav-Swap') === '1' || hv('x-rateb-nav-swap') === '1')) {
          if (N.marks.fetchHtml_network == null) N.marks.fetchHtml_network = now();
          push('fetchHtml_network', {});
        }
        return of(input, init);
      };
    }
    return true;
  };

  // Periodic attempt to patch nav once loaded
  const iv = setInterval(() => {
    if (N.patchNav()) {
      /* keep */
    }
  }, 20);

  N.collect = () => {
    const marks = Object.assign({}, N.marks);
    // Derive onClick entry as first document capture click listener hit after click_window_capture
    const clickHits = N.listenerHits.filter((h) => h.type === 'click');
    const docCap = clickHits.filter((h) => h.target === 'document' && h.phase === 'capture');
    if (docCap.length && marks.onClick_first_doc_capture == null) {
      marks.onClick_first_doc_capture = docCap[0].t;
    }
    // Heuristic: erp-nav-instant registers as anonymous capture on document
    const navHit = docCap.find((h) => /erp-nav|onClick|anonymous|rca\$/i.test(h.src)) || docCap[0];
    if (navHit && marks.erp_nav_onClick_entry == null) {
      marks.erp_nav_onClick_entry = navHit.t;
    }

    const et = N.eventTimings.slice(N.etBaseline || 0).filter((e) => /click|pointer|mouse/i.test(e.name));
    const lt = N.longTasks.slice(N.ltBaseline || 0);
    // Long tasks overlapping [pointerdown - 100, swapTo]
    const p0 = marks.pointerdown != null ? marks.pointerdown - 100 : null;
    const p1 = marks.swapTo != null ? marks.swapTo : marks.click_window_capture;
    const ltOverlap = lt.filter((t) => {
      if (p0 == null) return true;
      const end = t.start + t.duration;
      return end >= p0 && t.start <= (p1 || end);
    });

    // Best Event Timing click entry near our click
    let bestEt = null;
    if (marks.click_window_capture != null) {
      for (const e of et) {
        if (e.name !== 'click') continue;
        if (Math.abs(e.processingStart - marks.click_window_capture) < 500 || Math.abs(e.startTime - (marks.click_window_capture_eventTimeStamp || 0)) < 50) {
          if (!bestEt || e.inputDelay > bestEt.inputDelay) bestEt = e;
        }
      }
      if (!bestEt && et.length) {
        bestEt = et.filter((e) => e.name === 'click').sort((a, b) => b.inputDelay - a.inputDelay)[0] || et[0];
      }
    }

    // When onClick calls internal swapTo (not RatebNavInstant.navigate), infer:
    // - onClick_committed = first doc capture click where defaultPrevented flipped true
    // - swapTo ≈ moment before fetchHtml_network (cache probe may precede network)
    const prevDef = { v: false };
    for (const h of clickHits) {
      if (h.target === 'document' && h.phase === 'capture' && h.defaultPrevented && !prevDef.v) {
        if (marks.onClick_committed == null) marks.onClick_committed = h.t;
        prevDef.v = true;
      }
      if (h.defaultPrevented) prevDef.v = true;
    }
    if (marks.swapTo == null && marks.fetchHtml_network != null) {
      marks.swapTo_inferred = marks.onClick_committed || marks.erp_nav_onClick_entry || marks.click_window_capture;
    }
    if (marks.swapTo == null && marks.swapTo_inferred != null) {
      marks.swapTo = marks.swapTo_inferred;
    }

    const gaps = [];
    const order = [
      'pw_mouse_down',
      'pointerdown',
      'mousedown',
      'pw_mouse_up',
      'click_window_capture',
      'onClick_first_doc_capture',
      'erp_nav_onClick_entry',
      'onClick_committed',
      'swapTo',
      'fetchHtml_network',
    ];
    for (let i = 1; i < order.length; i++) {
      const a = order[i - 1];
      const b = order[i];
      if (marks[a] != null && marks[b] != null) {
        gaps.push({ from: a, to: b, ms: marks[b] - marks[a] });
      }
    }

    // Browser delay before handlers: Event Timing inputDelay
    const browserDelay = bestEt ? bestEt.inputDelay : null;

    return {
      marks,
      gaps,
      browserDelay_eventTiming_inputDelay: bestEt ? bestEt.inputDelay : null,
      eventTimingBest: bestEt,
      eventTimings: et,
      longTasksAll: lt,
      longTasksOverlapInput: ltOverlap,
      longTasksOverlapSumMs: ltOverlap.reduce((s, t) => s + t.duration, 0),
      rafSamples: N.rafSamples.slice(),
      syncCosts: N.syncCosts.slice(),
      listenerHits: N.listenerHits.slice(),
      clickListenerRegistry: {
        captureCount: N.clickListeners.capture.length,
        bubbleCount: N.clickListeners.bubble.length,
        capture: N.clickListeners.capture.slice(0, 20),
        bubble: N.clickListeners.bubble.slice(0, 20),
      },
      wallArm: N.wallArm,
      hrefFinal: location.href,
    };
  };
}

async function goDashboard(page) {
  await page.goto(BASE + '/admin/?company_id=22&_inrca=' + Date.now(), {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });
  await page.waitForFunction(
    () => window.RatebNavInstant && document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1',
    { timeout: 45000 }
  );
  await page.evaluate(() => window.__INPUT_RCA__ && window.__INPUT_RCA__.patchNav());
}

async function resolveLink(page, scenario) {
  return page.evaluate((spec) => {
    const re = new RegExp(spec.match.source, spec.match.flags);
    const gre = new RegExp(spec.group.source, spec.group.flags);
    for (const btn of document.querySelectorAll('[data-nav-group-toggle]')) {
      if (!gre.test(btn.textContent || '')) continue;
      const group = btn.closest('[data-nav-group], .rateb-nav-group, li, details') || btn.parentElement;
      const open =
        group &&
        (group.classList.contains('is-open') || group.hasAttribute('open') || btn.getAttribute('aria-expanded') === 'true');
      if (!open) btn.click();
    }
    const a = [...document.querySelectorAll('a[href]')].find((el) => {
      try {
        return re.test(new URL(el.href).pathname + new URL(el.href).search);
      } catch (e) {
        return false;
      }
    });
    if (!a) return null;
    const r = a.getBoundingClientRect();
    return {
      href: a.href,
      text: (a.textContent || '').trim().slice(0, 50),
      box: { x: r.x + r.width / 2, y: r.y + r.height / 2, w: r.width, h: r.height },
    };
  }, {
    match: { source: scenario.match.source, flags: scenario.match.flags },
    group: { source: scenario.group.source, flags: scenario.group.flags },
  });
}

async function measureBusyMs(page) {
  return page.evaluate(async () => {
    const samples = [];
    for (let i = 0; i < 10; i++) {
      const t0 = performance.now();
      await new Promise((r) => requestAnimationFrame(r));
      samples.push(performance.now() - t0);
    }
    const avg = samples.reduce((a, b) => a + b, 0) / samples.length;
    const max = Math.max(...samples);
    return { avgRaf: avg, maxRaf: max, samples };
  });
}

async function runScenario(page, client, scenario) {
  await goDashboard(page);
  if (scenario.waitAfterBootMs > 0) {
    await page.waitForTimeout(scenario.waitAfterBootMs);
  }

  const link = await resolveLink(page, scenario);
  if (!link) return { id: scenario.id, error: 'link_not_found' };

  const busyBefore = await measureBusyMs(page);

  // Optional: click in a window where long tasks are more likely (right after expanding groups + boot)
  if (scenario.clickDuringBusyProbe) {
    // Trigger work: re-expand all groups (hydrate templates) then click immediately
    await page.evaluate(() => {
      document.querySelectorAll('[data-nav-group-toggle]').forEach((b) => b.click());
    });
  }

  await page.evaluate((href) => {
    window.__INPUT_RCA__.arm(href);
    window.__INPUT_RCA__.patchNav();
  }, link.href);

  // CDP: enable queue for layout events around interaction (best-effort)
  let traceEvents = [];
  try {
    await client.send('Performance.enable');
  } catch (e) { /* ignore */ }

  const box = link.box;
  // Real mouse path (not locator.click auto-wait heuristics beyond our control)
  await page.mouse.move(box.x, box.y);
  await page.evaluate(() => window.__INPUT_RCA__.notePlaywrightInput('mouse_move'));
  await page.evaluate(() => window.__INPUT_RCA__.notePlaywrightInput('mouse_down'));
  const wallDown = Date.now();
  await page.mouse.down();
  await page.evaluate(() => window.__INPUT_RCA__.notePlaywrightInput('mouse_up'));
  await page.mouse.up();
  const wallUp = Date.now();

  // Wait until swapTo or timeout
  try {
    await page.waitForFunction(
      () => {
        const m = window.__INPUT_RCA__ && window.__INPUT_RCA__.marks;
        return m && (m.swapTo != null || m.fetchHtml_network != null || m.click_window_capture != null);
      },
      { timeout: 15000 }
    );
  } catch (e) { /* ignore */ }
  await page.waitForTimeout(400);

  // Flush event timing buffer
  await page.evaluate(async () => {
    await new Promise((r) => setTimeout(r, 100));
  });

  const collected = await page.evaluate(() => window.__INPUT_RCA__.collect());
  const busyAfter = await measureBusyMs(page);

  let perfMetrics = null;
  try {
    const { metrics } = await client.send('Performance.getMetrics');
    perfMetrics = {};
    for (const m of metrics || []) perfMetrics[m.name] = m.value;
  } catch (e2) { /* ignore */ }

  // Build timeline relative to pointerdown (or pw_mouse_down)
  const marks = collected.marks || {};
  const t0 = marks.pointerdown != null ? marks.pointerdown : marks.pw_mouse_down;
  const timeline = [];
  const keys = [
    'pw_mouse_down',
    'pointerdown',
    'mousedown',
    'pw_mouse_up',
    'click_window_capture',
    'onClick_first_doc_capture',
    'erp_nav_onClick_entry',
    'onClick_committed',
    'swapTo',
    'fetchHtml_network',
  ];
  for (const k of keys) {
    if (marks[k] != null && t0 != null) {
      timeline.push({ stage: k, t_ms: r1(marks[k] - t0), abs: marks[k] });
    }
  }

  // Identify missing seconds: largest gap before swapTo
  const preNavGaps = (collected.gaps || []).filter(
    (g) => g.to !== 'fetchHtml_network' || g.from === 'swapTo'
  );
  const biggest = preNavGaps.length
    ? preNavGaps.reduce((a, b) => (a.ms >= b.ms ? a : b))
    : null;

  const inputDelay = collected.browserDelay_eventTiming_inputDelay;
  const pointerToClick =
    marks.pointerdown != null && marks.click_window_capture != null
      ? marks.click_window_capture - marks.pointerdown
      : null;
  const clickToOnClick =
    marks.click_window_capture != null && marks.erp_nav_onClick_entry != null
      ? marks.erp_nav_onClick_entry - marks.click_window_capture
      : marks.click_window_capture != null && marks.onClick_first_doc_capture != null
        ? marks.onClick_first_doc_capture - marks.click_window_capture
        : null;
  const onClickToSwap =
    marks.onClick_committed != null && marks.swapTo != null
      ? marks.swapTo - marks.onClick_committed
      : marks.erp_nav_onClick_entry != null && marks.swapTo != null
        ? marks.swapTo - marks.erp_nav_onClick_entry
        : null;
  const swapToFetch =
    marks.swapTo != null && marks.fetchHtml_network != null
      ? marks.fetchHtml_network - marks.swapTo
      : marks.onClick_committed != null && marks.fetchHtml_network != null
        ? marks.fetchHtml_network - marks.onClick_committed
        : null;
  const onClickToFetch =
    marks.onClick_committed != null && marks.fetchHtml_network != null
      ? marks.fetchHtml_network - marks.onClick_committed
      : null;

  return {
    id: scenario.id,
    label: scenario.label,
    href: link.href,
    wallMouseDownUpMs: wallUp - wallDown,
    busyBefore,
    busyAfter,
    timeline,
    gaps: collected.gaps,
    biggestGap: biggest,
    chain: {
      eventTiming_inputDelay_ms: r1(inputDelay),
      pointerdown_to_click_handler_ms: r1(pointerToClick),
      click_to_erp_nav_onClick_ms: r1(clickToOnClick),
      onClick_to_swapTo_ms: r1(onClickToSwap),
      swapTo_to_fetchHtml_ms: r1(swapToFetch),
      onClick_committed_to_fetchHtml_ms: r1(onClickToFetch),
      pointerdown_to_swapTo_ms:
        marks.pointerdown != null && marks.swapTo != null ? r1(marks.swapTo - marks.pointerdown) : null,
      pointerdown_to_fetchHtml_ms:
        marks.pointerdown != null && marks.fetchHtml_network != null
          ? r1(marks.fetchHtml_network - marks.pointerdown)
          : null,
    },
    eventTimingBest: collected.eventTimingBest,
    eventTimings: collected.eventTimings,
    longTasksOverlapInput: collected.longTasksOverlapInput,
    longTasksOverlapSumMs: r1(collected.longTasksOverlapSumMs),
    longTasksAll: collected.longTasksAll,
    rafSamples: collected.rafSamples,
    syncCosts: collected.syncCosts,
    listenerHits: collected.listenerHits,
    clickListenerRegistry: collected.clickListenerRegistry,
    perfMetrics,
    marks,
    hrefFinal: collected.hrefFinal,
    verdictHint:
      inputDelay != null && inputDelay >= 1000
        ? 'MAIN_THREAD_INPUT_DELAY'
        : biggest && biggest.ms >= 1000
          ? 'GAP_' + biggest.from + '_to_' + biggest.to
          : inputDelay != null && inputDelay >= 100
            ? 'MODERATE_INPUT_DELAY'
            : 'NO_MULTI_SECOND_PRE_ONCLICK_IN_THIS_RUN',
  };
}

function buildMarkdown(runs) {
  const L = [];
  L.push('# INPUT LATENCY RCA (Evidence Only)');
  L.push('');
  L.push('**Date:** ' + new Date().toISOString());
  L.push('');
  L.push('**Question:** Users see 3–6 s before navigation starts. Prior waterfall began at `onClick()`. Where are the missing seconds **before** `onClick`?');
  L.push('');
  L.push('**Method:** Early `addInitScript` probe + real `page.mouse` down/up + Event Timing API + Long Tasks + rAF + listener inventory. No production code changes.');
  L.push('');
  L.push('## Chain under test');
  L.push('');
  L.push('```');
  L.push('MouseDown / PointerDown');
  L.push('  ↓  (browser input delay / main-thread block)');
  L.push('Click event dispatched');
  L.push('  ↓  window capture → document capture listeners');
  L.push('erp-nav-instant onClick (capture)');
  L.push('  ↓');
  L.push('swapTo() → fetchHtml()');
  L.push('```');
  L.push('');

  L.push('## Summary table');
  L.push('');
  L.push('| Scenario | ET inputDelay | pointer→click | click→onClick | onClick→swapTo | swapTo→fetch | LT overlap sum | Verdict hint |');
  L.push('|----------|---------------|---------------|---------------|----------------|--------------|----------------|--------------|');
  for (const r of runs) {
    if (r.error) {
      L.push('| ' + r.id + ' | ERROR: ' + r.error + ' |');
      continue;
    }
    const c = r.chain || {};
    L.push(
      '| ' +
        r.id +
        ' | ' +
        c.eventTiming_inputDelay_ms +
        ' | ' +
        c.pointerdown_to_click_handler_ms +
        ' | ' +
        c.click_to_erp_nav_onClick_ms +
        ' | ' +
        c.onClick_to_swapTo_ms +
        ' | ' +
        c.swapTo_to_fetchHtml_ms +
        ' | ' +
        r.longTasksOverlapSumMs +
        ' | ' +
        r.verdictHint +
        ' |'
    );
  }
  L.push('');

  // Global verdict
  const withDelay = runs.filter((r) => r.chain && r.chain.eventTiming_inputDelay_ms != null);
  const maxDelay = withDelay.sort(
    (a, b) => (b.chain.eventTiming_inputDelay_ms || 0) - (a.chain.eventTiming_inputDelay_ms || 0)
  )[0];
  const maxGap = runs
    .filter((r) => r.biggestGap)
    .sort((a, b) => b.biggestGap.ms - a.biggestGap.ms)[0];

  L.push('## Single-location verdict');
  L.push('');
  if (maxDelay && maxDelay.chain.eventTiming_inputDelay_ms >= 1000) {
    L.push(
      '**Missing seconds are BEFORE JS `onClick` handlers run:** Event Timing **inputDelay = ' +
        maxDelay.chain.eventTiming_inputDelay_ms +
        ' ms** (' +
        maxDelay.id +
        '). The main thread was blocked; the browser queued the click until JS was free.'
    );
  } else if (maxGap && maxGap.biggestGap.ms >= 1000) {
    L.push(
      '**Largest pre-navigation gap:** `' +
        maxGap.biggestGap.from +
        '` → `' +
        maxGap.biggestGap.to +
        '` = **' +
        r1(maxGap.biggestGap.ms) +
        ' ms** (' +
        maxGap.id +
        ').'
    );
  } else {
    L.push(
      'In these controlled runs, **no 3–6 s gap was observed before `onClick`**. Max Event Timing inputDelay = **' +
        (maxDelay ? maxDelay.chain.eventTiming_inputDelay_ms : 'n/a') +
        ' ms**. Max measured stage gap = **' +
        (maxGap ? r1(maxGap.biggestGap.ms) : 'n/a') +
        ' ms**.'
    );
    L.push('');
    L.push(
      'Interpretation: the user-visible 3–6 s is **unlikely to be a silent delay inside the click→onClick micro-pipeline when the main thread is idle**. It is more consistent with **(a)** main-thread long tasks at click time (inputDelay), **(b)** post-onClick navigation work (previous RCA: Cache API / network), or **(c)** hard document navigation / click before `RatebNavInstant` is bound. This RCA isolates (a).'
    );
  }
  L.push('');

  for (const r of runs) {
    L.push('## ' + r.id + ' — ' + (r.label || ''));
    L.push('');
    if (r.error) {
      L.push('ERROR: ' + r.error);
      L.push('');
      continue;
    }
    L.push('- href: `' + r.href + '`');
    L.push('- hrefFinal: `' + r.hrefFinal + '`');
    L.push('- verdict hint: **' + r.verdictHint + '**');
    L.push('');
    L.push('### Timeline (ms from pointerdown)');
    L.push('');
    L.push('| Stage | t (ms) |');
    L.push('|-------|--------|');
    for (const row of r.timeline || []) {
      L.push('| ' + row.stage + ' | ' + row.t_ms + ' |');
    }
    L.push('');
    L.push('### Gaps');
    L.push('');
    L.push('| From | To | ms |');
    L.push('|------|----|----|');
    for (const g of r.gaps || []) {
      L.push('| ' + g.from + ' | ' + g.to + ' | ' + r1(g.ms) + ' |');
    }
    L.push('');
    L.push('### Event Timing / INP ingredients');
    L.push('');
    L.push('```json');
    L.push(JSON.stringify({ best: r.eventTimingBest, all: r.eventTimings }, null, 2));
    L.push('```');
    L.push('');
    L.push('### Long Tasks overlapping input window');
    L.push('');
    L.push(
      r.longTasksOverlapInput && r.longTasksOverlapInput.length
        ? '```json\n' + JSON.stringify(r.longTasksOverlapInput, null, 2) + '\n```'
        : 'None ≥50 ms overlapping pointerdown→swapTo.'
    );
    L.push('');
    L.push('### rAF delay samples');
    L.push('');
    L.push('```json');
    L.push(JSON.stringify({ aroundClick: r.rafSamples, busyBefore: r.busyBefore, busyAfter: r.busyAfter }, null, 2));
    L.push('```');
    L.push('');
    L.push('### Sync layout/style probe');
    L.push('');
    L.push('```json');
    L.push(JSON.stringify(r.syncCosts, null, 2));
    L.push('```');
    L.push('');
    L.push('### Document click listeners registered (inventory)');
    L.push('');
    L.push('```json');
    L.push(JSON.stringify(r.clickListenerRegistry, null, 2));
    L.push('```');
    L.push('');
    L.push('### Listener hit order during armed click');
    L.push('');
    L.push('```json');
    L.push(JSON.stringify(r.listenerHits, null, 2));
    L.push('```');
    L.push('');
  }

  L.push('## How to read “missing seconds”');
  L.push('');
  L.push('| If you see… | Meaning |');
  L.push('|-------------|---------|');
  L.push('| Event Timing **inputDelay** of seconds | Main thread blocked; click queued; **`onClick` has not started yet** |');
  L.push('| Long Tasks sum ≈ inputDelay | Those tasks **are** the delay |');
  L.push('| inputDelay ~0 but swapTo→fetch large | Delay is **after** onClick (prior nav RCA) |');
  L.push('| pointerdown→click ~0, click→onClick large | Capture listeners before erp-nav are slow |');
  L.push('| No multi-second pre-onClick here | Reproduce while CPU is busy (boot/warm) or capture field INP |');
  L.push('');

  return L.join('\n');
}

(async () => {
  const mint = JSON.parse(
    ssh(
      'php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint'
    )
  );

  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'input-rca-' + Date.now()), {
    headless: true,
    executablePath: CHROME,
    args: ['--disable-dev-shm-usage', '--enable-blink-features=EventTiming'],
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
  await ctx.addInitScript(earlyProbe);

  const page = ctx.pages()[0] || (await ctx.newPage());
  const client = await ctx.newCDPSession(page);

  const runs = [];
  for (const scenario of SCENARIOS) {
    try {
      runs.push(await runScenario(page, client, scenario));
    } catch (e) {
      runs.push({ id: scenario.id, error: String(e && e.message ? e.message : e) });
    }
  }

  // Bonus: prove Event Timing inputDelay when main thread is sync-blocked 3s
  try {
    await goDashboard(page);
    const link = await resolveLink(page, SCENARIOS[0]);
    if (link) {
      await page.evaluate((href) => {
        window.__INPUT_RCA__.arm(href);
        // Start a 3000ms synchronous main-thread block on next timer (evaluate returns first)
        setTimeout(() => {
          const end = performance.now() + 3000;
          while (performance.now() < end) {
            /* intentional main-thread block for RCA proof only */
          }
        }, 30);
      }, link.href);
      await page.waitForTimeout(80); // block should be running
      const wallClick = Date.now();
      await page.mouse.click(link.box.x, link.box.y);
      const wallAfter = Date.now();
      await page.waitForTimeout(500);
      const proof = await page.evaluate(() => window.__INPUT_RCA__.collect());
      const et = proof.eventTimingBest || (proof.eventTimings || []).find((e) => e.name === 'click');
      runs.push({
        id: 'PROOF_sync_block_3s',
        label: 'PROOF: mouse click during 3000ms sync main-thread block (not production behavior)',
        href: link.href,
        wallClickBlockedMs: wallAfter - wallClick,
        chain: {
          eventTiming_inputDelay_ms: et ? Math.round(et.inputDelay * 10) / 10 : null,
          pointerdown_to_click_handler_ms:
            proof.marks.pointerdown != null && proof.marks.click_window_capture != null
              ? Math.round((proof.marks.click_window_capture - proof.marks.pointerdown) * 10) / 10
              : null,
          click_to_erp_nav_onClick_ms: null,
          onClick_to_swapTo_ms: null,
          swapTo_to_fetchHtml_ms: null,
          pointerdown_to_swapTo_ms: null,
        },
        eventTimingBest: et || proof.eventTimingBest,
        longTasksOverlapInput: proof.longTasksOverlapInput,
        longTasksOverlapSumMs: proof.longTasksOverlapSumMs,
        gaps: proof.gaps,
        biggestGap: (proof.gaps || []).reduce((a, b) => (!a || b.ms > a.ms ? b : a), null),
        timeline: [],
        verdictHint:
          et && et.inputDelay >= 2000
            ? 'PROOF_INPUT_DELAY_EQUALS_MAIN_THREAD_BLOCK'
            : 'PROOF_INCONCLUSIVE',
        marks: proof.marks,
        listenerHits: proof.listenerHits,
        eventTimings: proof.eventTimings,
        rafSamples: proof.rafSamples,
        syncCosts: proof.syncCosts,
        clickListenerRegistry: proof.clickListenerRegistry,
        hrefFinal: proof.hrefFinal,
      });
    }
  } catch (eProof) {
    runs.push({ id: 'PROOF_sync_block_3s', error: String(eProof.message || eProof) });
  }

  fs.mkdirSync(path.dirname(OUT_JSON), { recursive: true });
  fs.writeFileSync(OUT_JSON, JSON.stringify({ generatedAt: new Date().toISOString(), runs }, null, 2));
  fs.writeFileSync(OUT_MD, buildMarkdown(runs));
  console.log(OUT_JSON);
  console.log(OUT_MD);
  console.log(
    JSON.stringify(
      runs.map((r) => ({
        id: r.id,
        inputDelay: r.chain && r.chain.eventTiming_inputDelay_ms,
        ptrToClick: r.chain && r.chain.pointerdown_to_click_handler_ms,
        clickToOnClick: r.chain && r.chain.click_to_erp_nav_onClick_ms,
        onClickToSwap: r.chain && r.chain.onClick_to_swapTo_ms,
        swapToFetch: r.chain && r.chain.swapTo_to_fetchHtml_ms,
        ltSum: r.longTasksOverlapSumMs,
        biggest: r.biggestGap,
        verdict: r.verdictHint,
        err: r.error || null,
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
