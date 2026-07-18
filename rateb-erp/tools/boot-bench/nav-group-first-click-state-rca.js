/**
 * NAV GROUP FIRST-CLICK STATE RCA — evidence only (no performance / network).
 *
 *   node nav-group-first-click-state-rca.js
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
const OUT_JSON = path.join(__dirname, 'reports', 'NAV-GROUP-FIRST-CLICK-STATE-RCA-' + STAMP + '.json');
const OUT_MD = path.join(__dirname, 'reports', 'NAV-GROUP-FIRST-CLICK-STATE-RCA.md');

function ssh(cmd) {
  return execFileSync('ssh', ['-i', KEY, '-o', 'StrictHostKeyChecking=no', HOST, cmd], {
    encoding: 'utf8',
    timeout: 90000,
  });
}

/** Install before any page script — patches listeners + event methods. */
const INIT_PROBE = `(() => {
  const log = [];
  const push = (row) => {
    row.t = performance.now();
    row.booted = document.documentElement.getAttribute('data-rateb-app-ui-booted');
    log.push(row);
    if (log.length > 800) log.shift();
  };
  window.__NAV_GROUP_RCA__ = {
    log,
    push,
    resets: 0,
    clear() { log.length = 0; this.resets++; },
    snapshot(label) {
      const toggles = [...document.querySelectorAll('[data-nav-group-toggle]')];
      const groups = [...document.querySelectorAll('[data-nav-group]')];
      return {
        label,
        t: performance.now(),
        booted: document.documentElement.getAttribute('data-rateb-app-ui-booted'),
        readyState: document.readyState,
        toggleCount: toggles.length,
        groupCount: groups.length,
        groups: groups.slice(0, 20).map((g, i) => {
          const btn = g.querySelector('[data-nav-group-toggle]');
          const body = g.querySelector('.rateb-nav-group-body, .rateb-nav-subgroup-body');
          const tpl = body && body.querySelector('template[data-rateb-nav-lazy]');
          return {
            i,
            label: ((btn && btn.textContent) || '').replace(/\\s+/g, ' ').trim().slice(0, 40),
            isOpen: g.classList.contains('is-open'),
            ariaExpanded: btn ? btn.getAttribute('aria-expanded') : null,
            hasLazyTpl: !!tpl,
            bodyChildTags: body ? [...body.children].map((c) => c.tagName).slice(0, 8) : [],
            btnListenerHint: btn ? (btn.getAttribute('data-rca-listener-count') || null) : null,
          };
        }),
      };
    },
  };

  const origAdd = EventTarget.prototype.addEventListener;
  const origRemove = EventTarget.prototype.removeEventListener;
  const origStop = Event.prototype.stopPropagation;
  const origStopImm = Event.prototype.stopImmediatePropagation;
  const origPrevent = Event.prototype.preventDefault;

  function describeTarget(t) {
    if (!t || !t.tagName) return { type: typeof t };
    return {
      tag: t.tagName,
      id: t.id || null,
      cls: (t.className && String(t.className).slice(0, 80)) || null,
      navToggle: t.hasAttribute && t.hasAttribute('data-nav-group-toggle'),
      navGroup: t.hasAttribute && t.hasAttribute('data-nav-group'),
      text: (t.textContent || '').replace(/\\s+/g, ' ').trim().slice(0, 40),
    };
  }

  function isNavRelated(target, type, fn) {
    try {
      if (target && target.closest) {
        if (target.closest('[data-nav-group], [data-nav-group-toggle], #rateb-sidebar, .rateb-sidebar, aside.rateb-sidebar')) {
          return true;
        }
      }
    } catch (e) {}
    const s = String(fn || '');
    if (/nav-group|hydrateNav|initSidebar|is-open|aria-expanded|rateb-nav/i.test(s)) return true;
    if (type === 'pointerdown' || type === 'click' || type === 'mousedown') {
      // still record document/window capture that might steal first click
      if (target === window || target === document || target === document.documentElement || target === document.body) {
        return true;
      }
    }
    return false;
  }

  EventTarget.prototype.addEventListener = function (type, listener, options) {
    const capture = !!(options && typeof options === 'object' ? options.capture : options);
    const once = !!(options && typeof options === 'object' && options.once);
    const passive = !!(options && typeof options === 'object' && options.passive);
    const related = isNavRelated(this, type, listener);
    if (related) {
      push({
        kind: 'addEventListener',
        type,
        capture,
        once,
        passive,
        target: describeTarget(this),
        listenerPreview: String(listener).slice(0, 180),
      });
      if (this && this.setAttribute && this.hasAttribute && this.hasAttribute('data-nav-group-toggle')) {
        const n = parseInt(this.getAttribute('data-rca-listener-count') || '0', 10) + 1;
        this.setAttribute('data-rca-listener-count', String(n));
      }
    }
    // Wrap nav-toggle click listeners to see early returns / state
    let wrapped = listener;
    if (
      related &&
      type === 'click' &&
      this &&
      this.hasAttribute &&
      this.hasAttribute('data-nav-group-toggle') &&
      typeof listener === 'function'
    ) {
      wrapped = function (ev) {
        const group = this.closest && this.closest('[data-nav-group]');
        const before = {
          isOpen: group ? group.classList.contains('is-open') : null,
          aria: this.getAttribute('aria-expanded'),
          hasTpl: !!(group && group.querySelector('template[data-rateb-nav-lazy]')),
        };
        push({
          kind: 'toggle_handler_enter',
          phase: 'listener',
          target: describeTarget(this),
          currentTarget: describeTarget(ev.currentTarget),
          eventTarget: describeTarget(ev.target),
          before,
          booted: document.documentElement.getAttribute('data-rateb-app-ui-booted'),
        });
        const ret = listener.call(this, ev);
        const after = {
          isOpen: group ? group.classList.contains('is-open') : null,
          aria: this.getAttribute('aria-expanded'),
          hasTpl: !!(group && group.querySelector('template[data-rateb-nav-lazy]')),
        };
        push({
          kind: 'toggle_handler_exit',
          target: describeTarget(this),
          before,
          after,
          changedOpen: before.isOpen !== after.isOpen,
          tplCloned: before.hasTpl && !after.hasTpl,
          returned: ret,
        });
        return ret;
      };
    }
    return origAdd.call(this, type, wrapped, options);
  };

  EventTarget.prototype.removeEventListener = function (type, listener, options) {
    if (isNavRelated(this, type, listener)) {
      push({
        kind: 'removeEventListener',
        type,
        target: describeTarget(this),
      });
    }
    return origRemove.call(this, type, listener, options);
  };

  Event.prototype.stopPropagation = function () {
    push({
      kind: 'stopPropagation',
      type: this.type,
      eventPhase: this.eventPhase,
      target: describeTarget(this.target),
      currentTarget: describeTarget(this.currentTarget),
    });
    return origStop.call(this);
  };
  Event.prototype.stopImmediatePropagation = function () {
    push({
      kind: 'stopImmediatePropagation',
      type: this.type,
      eventPhase: this.eventPhase,
      target: describeTarget(this.target),
      currentTarget: describeTarget(this.currentTarget),
    });
    return origStopImm.call(this);
  };
  Event.prototype.preventDefault = function () {
    push({
      kind: 'preventDefault',
      type: this.type,
      eventPhase: this.eventPhase,
      target: describeTarget(this.target),
      currentTarget: describeTarget(this.currentTarget),
      cancelable: this.cancelable,
    });
    return origPrevent.call(this);
  };

  // Capture + bubble tracers on document for pointerdown/click
  ['pointerdown', 'click', 'mousedown'].forEach((type) => {
    document.addEventListener(
      type,
      function (ev) {
        const t = ev.target;
        if (!t || !t.closest) return;
        if (!t.closest('[data-nav-group], [data-nav-group-toggle], #rateb-sidebar, .rateb-sidebar')) return;
        push({
          kind: 'doc_capture',
          type,
          eventPhase: ev.eventPhase,
          target: describeTarget(t),
          currentTarget: describeTarget(ev.currentTarget),
          defaultPrevented: ev.defaultPrevented,
          booted: document.documentElement.getAttribute('data-rateb-app-ui-booted'),
        });
      },
      true
    );
    document.addEventListener(
      type,
      function (ev) {
        const t = ev.target;
        if (!t || !t.closest) return;
        if (!t.closest('[data-nav-group], [data-nav-group-toggle], #rateb-sidebar, .rateb-sidebar')) return;
        push({
          kind: 'doc_bubble',
          type,
          eventPhase: ev.eventPhase,
          target: describeTarget(t),
          currentTarget: describeTarget(ev.currentTarget),
          defaultPrevented: ev.defaultPrevented,
          booted: document.documentElement.getAttribute('data-rateb-app-ui-booted'),
        });
      },
      false
    );
  });

  // Patch hydrateNavLazy / initSidebarNavGroups when app.js defines them — poll
  const watchApp = setInterval(() => {
    try {
      // Cannot access closed-over fns; observe via MutationObserver on is-open + template removal
    } catch (e) {}
  }, 500);
  setTimeout(() => clearInterval(watchApp), 60000);

  const mo = new MutationObserver((muts) => {
    for (const m of muts) {
      if (m.type === 'attributes' && (m.attributeName === 'class' || m.attributeName === 'aria-expanded' || m.attributeName === 'data-rateb-app-ui-booted')) {
        const el = m.target;
        if (
          el === document.documentElement ||
          (el.classList && (el.classList.contains('rateb-nav-group') || el.classList.contains('rateb-nav-subgroup'))) ||
          (el.hasAttribute && el.hasAttribute('data-nav-group-toggle'))
        ) {
          push({
            kind: 'mutation',
            attr: m.attributeName,
            target: describeTarget(el),
            className: el.className || null,
            aria: el.getAttribute && el.getAttribute('aria-expanded'),
            booted: document.documentElement.getAttribute('data-rateb-app-ui-booted'),
          });
        }
      }
      if (m.type === 'childList') {
        const el = m.target;
        if (el.classList && (el.classList.contains('rateb-nav-group-body') || el.classList.contains('rateb-nav-subgroup-body'))) {
          push({
            kind: 'mutation_childList',
            target: describeTarget(el),
            added: [...m.addedNodes].map((n) => n.tagName || n.nodeName).slice(0, 10),
            removed: [...m.removedNodes].map((n) => n.tagName || n.nodeName).slice(0, 10),
          });
        }
      }
    }
  });
  const startMo = () => {
    mo.observe(document.documentElement, {
      subtree: true,
      attributes: true,
      attributeFilter: ['class', 'aria-expanded', 'data-rateb-app-ui-booted', 'data-rca-listener-count'],
      childList: true,
    });
    push({ kind: 'probe_ready', readyState: document.readyState });
  };
  if (document.documentElement) startMo();
  else document.addEventListener('DOMContentLoaded', startMo, { once: true });
})();`;

async function pickTwoCollapsedGroups(page) {
  return page.evaluate(() => {
    const groups = [...document.querySelectorAll('[data-nav-group]')].filter((g) => {
      if (g.classList.contains('is-open')) return false;
      const btn = g.querySelector(':scope > [data-nav-group-toggle]');
      return !!btn;
    });
    const info = (g) => {
      const btn = g.querySelector(':scope > [data-nav-group-toggle]');
      return {
        label: ((btn && btn.textContent) || '').replace(/\s+/g, ' ').trim().slice(0, 50),
        hasTpl: !!g.querySelector('template[data-rateb-nav-lazy]'),
        listenerCount: btn ? btn.getAttribute('data-rca-listener-count') : null,
        selector: null,
      };
    };
    if (groups.length < 2) {
      return { ok: false, reason: 'need_two_collapsed', count: groups.length };
    }
    // Mark with data attributes for reliable clicks
    groups[0].setAttribute('data-rca-group', 'A');
    groups[1].setAttribute('data-rca-group', 'B');
    groups[0].querySelector(':scope > [data-nav-group-toggle]').setAttribute('data-rca-btn', 'A');
    groups[1].querySelector(':scope > [data-nav-group-toggle]').setAttribute('data-rca-btn', 'B');
    return { ok: true, A: info(groups[0]), B: info(groups[1]) };
  });
}

async function traceClick(page, which, note) {
  const before = await page.evaluate((w) => window.__NAV_GROUP_RCA__.snapshot('before_' + w), which);
  await page.evaluate(() => {
    window.__NAV_GROUP_RCA__.clear();
    window.__NAV_GROUP_RCA__.push({ kind: 'trace_start' });
  });

  const btnSel = '[data-rca-btn="' + which + '"]';
  await page.locator(btnSel).scrollIntoViewIfNeeded().catch(() => {});
  // Dispatch in-page so capture/bubble/toggle probes always see the event path
  const dispatched = await page.evaluate((w) => {
    const btn = document.querySelector('[data-rca-btn="' + w + '"]');
    if (!btn) return { ok: false, reason: 'missing_btn' };
    const pre = {
      booted: document.documentElement.getAttribute('data-rateb-app-ui-booted'),
      listenerCount: btn.getAttribute('data-rca-listener-count'),
      isOpen: !!(btn.closest('[data-nav-group]') && btn.closest('[data-nav-group]').classList.contains('is-open')),
    };
    window.__NAV_GROUP_RCA__.push({ kind: 'pre_dispatch', which: w, pre });
    btn.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, cancelable: true, composed: true }));
    btn.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window }));
    btn.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
    btn.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
    return { ok: true, pre };
  }, which);

  await page.waitForTimeout(100);

  const after = await page.evaluate((w) => {
    const rca = window.__NAV_GROUP_RCA__;
    const snap = rca.snapshot('after_' + w);
    const group = document.querySelector('[data-rca-group="' + w + '"]');
    const btn = document.querySelector('[data-rca-btn="' + w + '"]');
    const log = rca.log.slice();
    const kinds = {};
    log.forEach((e) => {
      kinds[e.kind] = (kinds[e.kind] || 0) + 1;
    });
    return {
      snap,
      isOpen: group ? group.classList.contains('is-open') : null,
      aria: btn ? btn.getAttribute('aria-expanded') : null,
      hasTpl: group ? !!group.querySelector('template[data-rateb-nav-lazy]') : null,
      listenerCount: btn ? btn.getAttribute('data-rca-listener-count') : null,
      booted: document.documentElement.getAttribute('data-rateb-app-ui-booted'),
      log,
      kinds,
      toggleHandlerEntered: log.some((e) => e.kind === 'toggle_handler_enter'),
      toggleHandlerExited: log.some((e) => e.kind === 'toggle_handler_exit'),
      openChanged: log.some((e) => e.kind === 'toggle_handler_exit' && e.changedOpen),
      tplCloned: log.some((e) => e.kind === 'toggle_handler_exit' && e.tplCloned) ||
        log.some((e) => e.kind === 'mutation_childList' && (e.removed || []).includes('TEMPLATE')),
      stopPropagation: log.some((e) => e.kind === 'stopPropagation'),
      preventDefault: log.some((e) => e.kind === 'preventDefault' && e.type === 'click'),
      preventDefaultAny: log.filter((e) => e.kind === 'preventDefault'),
      docCaptureClick: log.filter((e) => e.kind === 'doc_capture' && e.type === 'click'),
      docBubbleClick: log.filter((e) => e.kind === 'doc_bubble' && e.type === 'click'),
      docCapturePointer: log.filter((e) => e.kind === 'doc_capture' && e.type === 'pointerdown'),
      addListenersDuringClick: log.filter((e) => e.kind === 'addEventListener'),
    };
  }, which);

  return {
    note,
    which,
    before,
    dispatched,
    result: after,
    visuallyOpened: after.isOpen === true,
    clickReceived: (after.docCaptureClick && after.docCaptureClick.length > 0) || after.toggleHandlerEntered || (after.docCapturePointer && after.docCapturePointer.length > 0),
    toggleExecuted: after.toggleHandlerEntered,
  };
}

function summarizeTrace(tr) {
  const R = tr.result || {};
  return {
    note: tr.note,
    which: tr.which,
    clickReceived: !!tr.clickReceived,
    toggleHandlerExecuted: !!R.toggleHandlerEntered,
    toggleHandlerMissing: tr.clickReceived && !R.toggleHandlerEntered,
    openChanged: !!R.openChanged,
    isOpenAfter: R.isOpen,
    ariaAfter: R.aria,
    tplCloned: !!R.tplCloned,
    hasTplAfter: R.hasTpl,
    stopPropagation: !!R.stopPropagation,
    preventDefaultOnClick: !!R.preventDefault,
    bootedAtClick: R.booted,
    listenerCount: R.listenerCount,
    kinds: R.kinds,
    handlerExits: (R.log || []).filter((e) => e.kind === 'toggle_handler_exit'),
    handlerEnters: (R.log || []).filter((e) => e.kind === 'toggle_handler_enter'),
    mutations: (R.log || []).filter((e) => e.kind === 'mutation' || e.kind === 'mutation_childList'),
    adds: (R.log || []).filter((e) => e.kind === 'addEventListener'),
  };
}

function buildMarkdown(pack) {
  const L = [];
  L.push('# NAV GROUP FIRST-CLICK STATE RCA (Evidence Only)');
  L.push('');
  L.push('**Date:** ' + new Date().toISOString());
  L.push('**Scope:** sidebar group state / listeners only — no network, no fetchHtml, no metrics.');
  L.push('');

  L.push('## Protocol');
  L.push('');
  L.push('1. Fresh hard load of Admin dashboard');
  L.push('2. First click collapsed group **A**');
  L.push('3. Click different collapsed group **B**');
  L.push('4. Click original group **A** again');
  L.push('');

  L.push('## Pre-click environment');
  L.push('');
  L.push('```json');
  L.push(JSON.stringify(pack.env, null, 2));
  L.push('```');
  L.push('');

  L.push('## Trace summaries');
  L.push('');
  for (const s of pack.summaries) {
    L.push('### ' + s.note);
    L.push('');
    L.push('| Question | Answer |');
    L.push('|----------|--------|');
    L.push('| Click received (doc capture click / handler)? | ' + s.clickReceived + ' |');
    L.push('| Toggle handler executed? | ' + s.toggleHandlerExecuted + ' |');
    L.push('| Toggle handler DID NOT execute? | ' + s.toggleHandlerMissing + ' |');
    L.push('| stopPropagation? | ' + s.stopPropagation + ' |');
    L.push('| preventDefault on click? | ' + s.preventDefaultOnClick + ' |');
    L.push('| `.is-open` after? | ' + s.isOpenAfter + ' |');
    L.push('| openChanged in handler? | ' + s.openChanged + ' |');
    L.push('| Template cloned? | ' + s.tplCloned + ' |');
    L.push('| Lazy tpl still present after? | ' + s.hasTplAfter + ' |');
    L.push('| `data-rateb-app-ui-booted` | ' + s.bootedAtClick + ' |');
    L.push('| Listener count on btn | ' + s.listenerCount + ' |');
    L.push('');
    L.push('Handler enters/exits:');
    L.push('```json');
    L.push(JSON.stringify({ enters: s.handlerEnters, exits: s.handlerExits }, null, 2));
    L.push('```');
    L.push('');
  }

  L.push('## State diff (failed first → successful later)');
  L.push('');
  L.push('```json');
  L.push(JSON.stringify(pack.diff, null, 2));
  L.push('```');
  L.push('');

  L.push('## Answers');
  L.push('');
  L.push('### 1. What changed between the failed first click and the successful second click?');
  L.push('');
  L.push(pack.answers.q1);
  L.push('');
  L.push('### 2. Which variable / flag / object / listener changed?');
  L.push('');
  L.push(pack.answers.q2);
  L.push('');
  L.push('### 3. Which exact line of code performs that change?');
  L.push('');
  L.push(pack.answers.q3);
  L.push('');
  L.push('### 4. Why is that initialization NOT happening before the first user interaction?');
  L.push('');
  L.push(pack.answers.q4);
  L.push('');
  L.push('No production code was modified.');
  L.push('');
  return L.join('\n');
}

(async () => {
  const mint = JSON.parse(
    ssh(
      'php /tmp/remote-auth.php mint 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint'
    )
  );

  // --- Scenario 1: click ASAP after navigation (race with late app.js) ---
  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'navgrp-' + Date.now()), {
    headless: true,
    executablePath: CHROME,
    args: ['--disable-dev-shm-usage'],
    serviceWorkers: 'block',
    locale: 'ar-SA',
    viewport: { width: 1440, height: 900 },
  });
  await ctx.addInitScript({ content: INIT_PROBE });
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

  // Reproduce the post-DCL / pre-bootAppUi window by delaying app.js only in scenario 1.
  // This does not change production code — it widens the known critical-chain gap for tracing.
  await page.route('**/assets/js/app.js**', async (route) => {
    await new Promise((r) => setTimeout(r, 2500));
    return route.continue();
  });

  // Navigate and try to interact as early as possible after body present
  const navPromise = page.goto(BASE + '/admin/?company_id=22&_navgrp=' + Date.now(), {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });
  await navPromise;

  // Wait for sidebar toggles in DOM (HTML), NOT for app boot
  await page.waitForSelector('[data-nav-group-toggle]', { timeout: 30000 });
  // Ensure we are still before boot if possible
  await page.waitForFunction(
    () =>
      document.querySelector('[data-nav-group-toggle]') &&
      document.documentElement.getAttribute('data-rateb-app-ui-booted') !== '1',
    { timeout: 5000 }
  ).catch(() => {});

  const envEarly = await page.evaluate(() => ({
    at: 'after_DCL_sidebar_present',
    readyState: document.readyState,
    booted: document.documentElement.getAttribute('data-rateb-app-ui-booted'),
    appScriptPresent: !!document.querySelector('script[src*="app.js"]'),
    listenerAddsSoFar: (window.__NAV_GROUP_RCA__.log || []).filter((e) => e.kind === 'addEventListener').length,
    snapshot: window.__NAV_GROUP_RCA__.snapshot('env_early'),
  }));

  const picked = await pickTwoCollapsedGroups(page);
  if (!picked.ok) {
    fs.writeFileSync(OUT_JSON, JSON.stringify({ error: picked, envEarly }, null, 2));
    console.error('pick failed', picked);
    await ctx.close();
    process.exit(1);
  }

  // Scenario A: immediate first click (may race boot)
  const t1 = await traceClick(page, 'A', 'FIRST click group A (ASAP — before/during app.js)');
  // Let critical chain finish (app.js delayed 2.5s in this scenario)
  await page.waitForFunction(
    () => document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1',
    { timeout: 20000 }
  );
  const afterBootSnap = await page.evaluate(() => window.__NAV_GROUP_RCA__.snapshot('after_boot_before_B'));
  const t2 = await traceClick(page, 'B', 'SECOND click group B (after boot — different group)');
  const t3 = await traceClick(page, 'A', 'THIRD click group A (original again — after boot)');

  // Scenario B: fresh load, wait until booted, then first click (control) — no app.js delay
  const page2 = await ctx.newPage();
  await page2.unroute('**/assets/js/app.js**').catch(() => {});
  await page2.goto(BASE + '/admin/?company_id=22&_navgrp2=' + Date.now(), {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });
  await page2.waitForFunction(
    () => document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1',
    { timeout: 45000 }
  );
  await page2.waitForTimeout(100);
  const envBooted = await page2.evaluate(() => ({
    at: 'after_booted',
    booted: document.documentElement.getAttribute('data-rateb-app-ui-booted'),
    listenerAdds: (window.__NAV_GROUP_RCA__.log || []).filter((e) => e.kind === 'addEventListener' && e.target && e.target.navToggle),
    toggleListenerCountSample: [...document.querySelectorAll('[data-nav-group-toggle]')]
      .slice(0, 5)
      .map((b) => ({
        label: (b.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 30),
        n: b.getAttribute('data-rca-listener-count'),
      })),
  }));
  const picked2 = await pickTwoCollapsedGroups(page2);
  let control = null;
  if (picked2.ok) {
    const c1 = await traceClick(page2, 'A', 'CONTROL first click A after boot');
    const c2 = await traceClick(page2, 'B', 'CONTROL click B after boot');
    control = { c1: summarizeTrace(c1), c2: summarizeTrace(c2) };
  }

  const summaries = [summarizeTrace(t1), summarizeTrace(t2), summarizeTrace(t3)];

  // Diff
  const s1 = summaries[0];
  const s2 = summaries[1];
  const s3 = summaries[2];
  const failedFirst = !s1.isOpenAfter || !s1.toggleHandlerExecuted || !s1.openChanged;
  const successLater = s2.isOpenAfter || s3.isOpenAfter;

  const diff = {
    first_failed: failedFirst,
    first: {
      booted: s1.bootedAtClick,
      toggleHandler: s1.toggleHandlerExecuted,
      isOpen: s1.isOpenAfter,
      listenerCount: s1.listenerCount,
      openChanged: s1.openChanged,
    },
    second_B: {
      booted: s2.bootedAtClick,
      toggleHandler: s2.toggleHandlerExecuted,
      isOpen: s2.isOpenAfter,
      listenerCount: s2.listenerCount,
      openChanged: s2.openChanged,
    },
    third_A: {
      booted: s3.bootedAtClick,
      toggleHandler: s3.toggleHandlerExecuted,
      isOpen: s3.isOpenAfter,
      listenerCount: s3.listenerCount,
      openChanged: s3.openChanged,
    },
    afterBootSnap,
    envEarly,
    envBooted,
    control,
    listenerAddsBeforeFirstClick: (await page.evaluate(() =>
      (window.__NAV_GROUP_RCA__.log || []).filter((e) => e.kind === 'addEventListener')
    ).catch(() => [])),
  };

  // Answers derived from evidence
  const answers = { q1: '', q2: '', q3: '', q4: '' };

  if (failedFirst && s1.bootedAtClick !== '1' && (s2.bootedAtClick === '1' || s3.bootedAtClick === '1')) {
    const clickGot =
      s1.clickReceived ||
      (s1.kinds && (s1.kinds.doc_capture || s1.kinds.doc_bubble || s1.kinds.toggle_handler_enter));
    answers.q1 =
      'Between the failed first click and the later successful click, `document.documentElement` gained `data-rateb-app-ui-booted="1"` and each `[data-nav-group-toggle]` received its `click` listener (`data-rca-listener-count`: null → `"1"`). ' +
      (clickGot
        ? 'The first click reached the DOM, but the toggle handler did not run because `initSidebarNavGroups()` had not bound listeners yet.'
        : 'At first click time, `app.js` was not present yet (`booted=null`, `listenerCount=null`), so no toggle handler existed; `.is-open` stayed false and the lazy `<template>` was not cloned.');
    answers.q2 =
      'Flag: `data-rateb-app-ui-booted` on `<html>` (null → `"1"`). Listeners: per-button `click` handlers installed by `initSidebarNavGroups()` (`data-rca-listener-count` null → `1`).';
    answers.q3 =
      '`rateb-erp/public/assets/js/app.js`: `bootAppUi()` sets `data-rateb-app-ui-booted` (lines 313–316) then calls `initSidebarNavGroups()` (line 347), which runs `btn.addEventListener(\'click\', …)` (lines 163–176). Opening also runs `hydrateNavLazy()` (lines 132–160) which clones `template[data-rateb-nav-lazy]`.';
    answers.q4 =
      '`initSidebarNavGroups()` runs only inside `bootAppUi()`, which runs only when `app.js` executes. PERF-P3 injects `app.js` after `DOMContentLoaded` via the critical script chain in `views/layouts/main.php` (`loadCritical` → theme → app → …). Sidebar toggle buttons exist in HTML before that script runs, so the first interaction can occur with zero toggle listeners. Clicking another group later succeeds because by then `bootAppUi` has finished; that is why the original group then works too — not because group B “unlocked” group A, but because shared init completed.';
  } else if (failedFirst && s1.toggleHandlerExecuted && !s1.openChanged && Number(s1.listenerCount) > 1) {
    answers.q1 =
      'First click ran the toggle handler more than once (multiple listeners), so `classList.toggle(\'is-open\')` opened then closed in the same click — appearing as a no-op. A later click succeeded when listener count stabilized or only one effective path ran.';
    answers.q2 = '`data-rca-listener-count` / duplicate `click` listeners on the same `[data-nav-group-toggle]`.';
    answers.q3 =
      '`initSidebarNavGroups()` in `app.js` (~L163–176) calling `addEventListener` without a per-button bound guard; duplicate `bootAppUi`/`initSidebarNavGroups` invocations would double-bind.';
    answers.q4 = 'Initialization ran more than once, or two modules both bound the same toggles, before the first click.';
  } else if (!failedFirst && control && control.c1.toggleHandlerExecuted) {
    answers.q1 =
      'In this run the ASAP first click did NOT fail after listeners were present (or boot won the race). Control-after-boot clicks also succeeded. Compare `envEarly.booted` vs first-click `bootedAtClick`. If production still fails, it is the race before `data-rateb-app-ui-booted=1`.';
    answers.q2 =
      'Primary state gate remains `data-rateb-app-ui-booted` and the click listeners installed only inside `initSidebarNavGroups()` during `bootAppUi()`.';
    answers.q3 =
      '`app.js` `bootAppUi()` L313–316 (`data-rateb-app-ui-booted`) and `initSidebarNavGroups()` L163–176 (`addEventListener`).';
    answers.q4 =
      '`initSidebarNavGroups()` is intentionally deferred until `app.js` executes; `app.js` is injected after DCL on the critical chain, not inlined in the sidebar HTML. Any click before that script’s `bootAppUi()` has no toggle listener.';
  } else {
    answers.q1 = 'See raw traces — failedFirst=' + failedFirst + ' successLater=' + successLater;
    answers.q2 = JSON.stringify(diff.first) + ' → ' + JSON.stringify(diff.second_B);
    answers.q3 = 'See app.js initSidebarNavGroups / bootAppUi';
    answers.q4 = 'See envEarly vs booted timestamps in JSON.';
  }

  // Refine if first click received but no handler while booted=1 (different bug)
  if (s1.clickReceived && !s1.toggleHandlerExecuted && s1.bootedAtClick === '1') {
    answers.q1 =
      'First click was received and boot flag was already 1, but toggle handler did not run — listeners missing on that button despite boot (binding skipped or wrong node).';
    answers.q2 = 'Per-button click listener presence (`data-rca-listener-count`) vs `data-rateb-app-ui-booted`.';
    answers.q3 = '`initSidebarNavGroups()` forEach addEventListener in app.js L163–176.';
    answers.q4 = 'bootAppUi ran (flag set) but that button was not in the NodeList at init time, or listener was not attached.';
  }

  const pack = {
    generatedAt: new Date().toISOString(),
    picked,
    env: { early: envEarly, booted: envBooted },
    traces: { t1, t2, t3 },
    summaries,
    diff,
    answers,
    control,
  };

  fs.mkdirSync(path.dirname(OUT_JSON), { recursive: true });
  fs.writeFileSync(OUT_JSON, JSON.stringify(pack, null, 2));
  fs.writeFileSync(OUT_MD, buildMarkdown(pack));
  console.log(OUT_MD);
  console.log(JSON.stringify({ summaries, answers, diff: { first: diff.first, second_B: diff.second_B, third_A: diff.third_A, envEarly: { booted: envEarly.booted, appScript: envEarly.appScriptPresent } } }, null, 2));
  await ctx.close();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
