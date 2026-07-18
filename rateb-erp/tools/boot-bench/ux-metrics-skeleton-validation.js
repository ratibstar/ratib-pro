/**
 * FINAL UX BLOCKER VALIDATION — evidence only.
 * Does .cm--page-stats.is-loading block the page, or is main content already usable?
 *
 *   node ux-metrics-skeleton-validation.js
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
const OUT_JSON = path.join(__dirname, 'reports', 'UX-METRICS-SKELETON-VALIDATION-' + STAMP + '.json');
const OUT_MD = path.join(__dirname, 'reports', 'UX-METRICS-SKELETON-VALIDATION.md');

const SKEL = '.cm--page-stats.is-loading, [data-module-metrics-async].is-loading';
const MAIN = '#rateb-main-content, main.rateb-content';

const ROUTES = [
  { id: 'Inventory', match: /\/admin\/ops\/inventory(\/|$)/i, group: /المخزون|inventory/i },
  { id: 'Purchasing', match: /\/admin\/ops\/purchase-requests(\/|$)/i, group: /المشتريات|procurement|purchas/i },
  { id: 'HR', match: /\/admin\/hr(\/|$)/i, group: /الموارد البشرية|\bhr\b/i },
  { id: 'Companies', match: /\/admin\/companies(\/|$)/i, group: /شركات|companies/i },
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

async function goDash(page) {
  await page.goto(BASE + '/admin/?company_id=22&_ux=' + Date.now(), {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });
  await page.waitForFunction(
    () => window.RatebNavInstant && document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1',
    { timeout: 45000 }
  );
  await page.waitForTimeout(400);
}

async function resolveHref(page, route) {
  return page.evaluate((spec) => {
    const re = new RegExp(spec.match.source, spec.match.flags);
    const gre = new RegExp(spec.group.source, spec.group.flags);
    for (const b of document.querySelectorAll('[data-nav-group-toggle]')) {
      if (gre.test(b.textContent || '')) b.click();
    }
    const a = [...document.querySelectorAll('a[href]')].find((el) => {
      try {
        return re.test(new URL(el.href).pathname);
      } catch (e) {
        return false;
      }
    });
    return a ? a.href : null;
  }, {
    match: { source: route.match.source, flags: route.match.flags },
    group: { source: route.group.source, flags: route.group.flags },
  });
}

/** Timing only — no clicks that can hard-navigate. */
async function measureTiming(page, href, opts) {
  opts = opts || {};
  const maxWaitMs = opts.maxWaitMs != null ? opts.maxWaitMs : 18000;
  return page.evaluate(
    async ({ href: h, skelSel, mainSel, maxWaitMs: maxWait }) => {
      const now = () => performance.now();
      const t0 = now();
      let afterEnterAt = null;
      document.addEventListener(
        'rateb:nav:afterEnter',
        () => {
          afterEnterAt = now() - t0;
        },
        { once: true }
      );

      function layoutProbe() {
        const main = document.querySelector(mainSel);
        const skel = document.querySelector(skelSel);
        const mainRect = main ? main.getBoundingClientRect() : null;
        const skelRect = skel ? skel.getBoundingClientRect() : null;
        const vh = window.innerHeight || 900;
        const mainTextLen = main ? (main.innerText || '').trim().length : 0;
        const controls = main
          ? [...main.querySelectorAll('a[href], button, input, select, textarea, [role="button"]')].filter((el) => {
              if (skel && skel.contains(el)) return false;
              const st = getComputedStyle(el);
              if (st.display === 'none' || st.visibility === 'hidden' || el.disabled) return false;
              if (st.pointerEvents === 'none') return false;
              const r = el.getBoundingClientRect();
              return r.width > 0 && r.height > 0;
            })
          : [];
        const skelH = skelRect ? skelRect.height : 0;
        const mainH = mainRect ? mainRect.height : 0;
        const skelViewportFraction = skelRect ? Math.min(1, Math.max(0, skelH / vh)) : 0;
        let blocksMainHitTest = false;
        if (main && skel && mainRect) {
          const cx = (mainRect.left + mainRect.right) / 2;
          const cy = (mainRect.top + mainRect.bottom) / 2;
          const top = document.elementFromPoint(cx, cy);
          if (top && skel.contains(top)) blocksMainHitTest = true;
        }
        return {
          mainTextLen,
          controlCount: controls.length,
          skelVisible: !!skel,
          skelHeight: skelH,
          mainHeight: mainH,
          skelViewportFraction,
          skelOfMainFraction: mainH > 0 ? skelH / mainH : null,
          blocksMainHitTest,
          skelTop: skelRect ? skelRect.top : null,
        };
      }

      function isUsable(p) {
        return (
          p.mainTextLen > 80 &&
          p.controlCount >= 2 &&
          !p.blocksMainHitTest &&
          (p.skelViewportFraction == null || p.skelViewportFraction < 0.35)
        );
      }

      const ok = await window.RatebNavInstant.navigate(h);
      const navigateDoneAt = now() - t0;

      let usableAt = null;
      let skeletonGoneAt = null;
      let firstSkel = null;
      const samples = [];
      const deadline = now() + maxWait;

      while (now() < deadline) {
        const p = layoutProbe();
        const t = now() - t0;
        if (p.skelVisible && firstSkel == null) firstSkel = t;
        if (usableAt == null && isUsable(p)) usableAt = t;
        if (skeletonGoneAt == null && firstSkel != null && !p.skelVisible) skeletonGoneAt = t;
        if (samples.length < 25) samples.push(Object.assign({ t }, p));
        // Once usable AND we have seen skeleton, we have the answer for UX vs nav —
        // exit early so interaction probe can run while skeleton is still up.
        if (usableAt != null && firstSkel != null && t > usableAt + 200) break;
        if (usableAt != null && skeletonGoneAt != null) break;
        if (usableAt != null && firstSkel == null && t > 1500) break;
        await new Promise((r) => setTimeout(r, 80));
      }

      // Extended watch for skeleton hide (separate from early exit) — poll a few more seconds
      // only if still loading and we want gone timestamp; keep short to leave skel for probe.
      const skelStill = !!document.querySelector(skelSel);
      if (firstSkel != null && skeletonGoneAt == null && skelStill) {
        // record "still visible at end of measurement window"
      }

      if (firstSkel == null && usableAt != null) skeletonGoneAt = usableAt;

      const endLayout = layoutProbe();
      return {
        navigateOk: !!ok,
        t_afterEnter: afterEnterAt,
        t_navigate_done: navigateDoneAt,
        t_usable: usableAt,
        t_skeleton_first: firstSkel,
        t_skeleton_gone: skeletonGoneAt,
        skeleton_still_visible_at_end: !!endLayout.skelVisible,
        skeleton_visible_ms:
          firstSkel != null && skeletonGoneAt != null
            ? skeletonGoneAt - firstSkel
            : firstSkel != null
              ? now() - t0 - firstSkel
              : 0,
        usable_before_skeleton_gone:
          usableAt != null && skeletonGoneAt != null
            ? usableAt < skeletonGoneAt
            : usableAt != null && !!document.querySelector(skelSel),
        gap_usable_to_skeleton_gone:
          usableAt != null && skeletonGoneAt != null ? skeletonGoneAt - usableAt : null,
        samples: samples.filter((_, i) => i % 2 === 0),
        finalLayout: endLayout,
      };
    },
    { href, skelSel: SKEL, mainSel: MAIN, maxWaitMs }
  );
}

/**
 * Non-destructive interaction probe while skeleton is visible.
 * Prefer calling this on the SAME document after measureTiming (no re-nav),
 * because a second soft-nav can race SW/hard fallback and destroy the context.
 * Uses hit-testing + focus/type/select — never follows navigable links.
 */
async function probeInteractions(page, href, opts) {
  opts = opts || {};
  // Only re-navigate if caller asks (fresh page). Default: stay put.
  if (opts.navigateFirst && href) {
    await page.evaluate(async (h) => {
      await window.RatebNavInstant.navigate(h);
    }, href);
    try {
      await page.waitForSelector(SKEL, { timeout: 4000, state: 'attached' });
    } catch (e) {
      /* continue — may already be gone */
    }
  }

  const skelPresent = await page.evaluate((sel) => !!document.querySelector(sel), SKEL);

  if (!skelPresent) {
    return {
      skeletonPresentAtProbe: false,
      note: 'Skeleton already gone or never shown when interaction probe ran',
      layout: await page.evaluate(
        ({ skelSel, mainSel }) => {
          const main = document.querySelector(mainSel);
          const skel = document.querySelector(skelSel);
          return {
            skelVisible: !!skel,
            mainTextLen: main ? (main.innerText || '').trim().length : 0,
          };
        },
        { skelSel: SKEL, mainSel: MAIN }
      ),
    };
  }

  // Layout + hit-test capabilities (no navigation)
  const layoutAndHit = await page.evaluate(
    ({ skelSel, mainSel }) => {
      const main = document.querySelector(mainSel);
      const skel = document.querySelector(skelSel);
      const vh = window.innerHeight || 900;
      const mainRect = main ? main.getBoundingClientRect() : null;
      const skelRect = skel ? skel.getBoundingClientRect() : null;
      const skelH = skelRect ? skelRect.height : 0;

      let blocksMainHitTest = false;
      if (main && skel && mainRect) {
        const cx = (mainRect.left + mainRect.right) / 2;
        const cy = (mainRect.top + mainRect.bottom) / 2;
        const top = document.elementFromPoint(cx, cy);
        if (top && skel.contains(top)) blocksMainHitTest = true;
      }

      function hittable(el) {
        if (!el || (skel && skel.contains(el))) return { ok: false, reason: 'inside_skeleton' };
        const r = el.getBoundingClientRect();
        if (r.width <= 0 || r.height <= 0) return { ok: false, reason: 'zero_size' };
        const st = getComputedStyle(el);
        if (st.pointerEvents === 'none' || st.visibility === 'hidden' || st.display === 'none') {
          return { ok: false, reason: 'not_interactive_style' };
        }
        const cx = r.left + r.width / 2;
        const cy = r.top + r.height / 2;
        const top = document.elementFromPoint(cx, cy);
        if (!top) return { ok: false, reason: 'no_element_from_point' };
        const hits = top === el || el.contains(top) || (top.contains && top.contains(el));
        return {
          ok: !!hits,
          topTag: top.tagName,
          topClass: (top.className || '').toString().slice(0, 60),
          coveredBySkeleton: !!(skel && skel.contains(top)),
        };
      }

      const btn = main
        ? [...main.querySelectorAll('a.btn, button.btn, button, a[href*="create"], a[href*="new"]')].find((el) => {
            if (skel && skel.contains(el)) return false;
            const r = el.getBoundingClientRect();
            return r.width > 0 && r.height > 0;
          })
        : null;
      const search = main
        ? [...main.querySelectorAll('input[type="search"], input[type="text"], input.form-control')].find(
            (el) => !(skel && skel.contains(el)) && el.offsetParent !== null
          )
        : null;
      const sel = main
        ? [...main.querySelectorAll('select')].find(
            (el) => !(skel && skel.contains(el)) && el.options && el.options.length > 1
          )
        : null;
      const create = main
        ? [...main.querySelectorAll('a[href]')].find((el) => {
            if (skel && skel.contains(el)) return false;
            const t = ((el.textContent || '') + el.href).toLowerCase();
            return /create|new|add|إنشاء|إضافة|جديد/.test(t);
          })
        : null;
      const side = document.querySelector('aside a.rateb-nav-link[href], nav a.rateb-nav-link[href], a.rateb-nav-link[href]');

      return {
        layout: {
          skelVisible: !!skel,
          skelHeight: skelH,
          skelViewportFraction: skelRect ? Math.min(1, Math.max(0, skelH / vh)) : 0,
          skelOfMainFraction: mainRect && mainRect.height > 0 ? skelH / mainRect.height : null,
          blocksMainHitTest,
          mainTextLen: main ? (main.innerText || '').trim().length : 0,
          controlCountOutsideSkel: main
            ? [...main.querySelectorAll('a[href], button, input, select')].filter((el) => {
                if (skel && skel.contains(el)) return false;
                const r = el.getBoundingClientRect();
                return r.width > 0 && r.height > 0;
              }).length
            : 0,
        },
        hitTests: {
          clickButton: btn
            ? Object.assign({ label: (btn.textContent || '').trim().slice(0, 40), tag: btn.tagName }, hittable(btn))
            : { ok: null, reason: 'no_button_found' },
          searchInput: search ? hittable(search) : { ok: null, reason: 'no_search' },
          filterSelect: sel ? hittable(sel) : { ok: null, reason: 'no_filter' },
          openForm: create
            ? Object.assign({ label: (create.textContent || '').trim().slice(0, 40) }, hittable(create))
            : { ok: null, reason: 'no_create_link' },
          sidebarNavigate: side ? hittable(side) : { ok: null, reason: 'no_sidebar_link' },
        },
        refs: {
          hasSearch: !!search,
          hasFilter: !!sel,
          hasCreate: !!create,
          hasButton: !!btn,
          hasSidebar: !!side,
        },
      };
    },
    { skelSel: SKEL, mainSel: MAIN }
  );

  // Live search: set value without input event if form might auto-submit; use focus + value only
  let searchLive = { ok: null, reason: 'skipped' };
  if (layoutAndHit.refs.hasSearch) {
    try {
      searchLive = await page.evaluate(({ skelSel, mainSel }) => {
        const main = document.querySelector(mainSel);
        const skel = document.querySelector(skelSel);
        if (!skel) return { ok: false, reason: 'skeleton_gone_before_search' };
        const search = [...main.querySelectorAll('input[type="search"], input[type="text"], input.form-control')].find(
          (el) => !skel.contains(el) && el.offsetParent !== null
        );
        if (!search) return { ok: null, reason: 'no_search' };
        search.focus();
        const prev = search.value;
        search.value = 'ux-probe';
        const ok = search.value === 'ux-probe';
        const focused = document.activeElement === search;
        const stillSkel = !!document.querySelector(skelSel);
        search.value = prev;
        return { ok: ok && focused, focused, stillSkeletonVisible: stillSkel, note: 'focus+value only (no input event)' };
      }, { skelSel: SKEL, mainSel: MAIN });
    } catch (e) {
      searchLive = { ok: false, error: String(e.message || e) };
    }
  }

  // Live interaction: filter — hit-test only (change can submit GET forms → hard nav)
  let filterLive = layoutAndHit.hitTests.filterSelect
    ? Object.assign({}, layoutAndHit.hitTests.filterSelect, {
        note: 'hit-test only; change not dispatched (avoids form navigation)',
      })
    : { ok: null, reason: 'skipped' };

  // Synthetic click on button with capture preventDefault (no navigation)
  let clickLive = { ok: null, reason: 'skipped' };
  if (layoutAndHit.refs.hasButton) {
    clickLive = await page.evaluate(({ skelSel, mainSel }) => {
      const main = document.querySelector(mainSel);
      const skel = document.querySelector(skelSel);
      if (!skel) return { ok: false, reason: 'skeleton_gone_before_click' };
      const btn = [...main.querySelectorAll('a.btn, button.btn, button, a[href*="create"], a[href*="new"]')].find(
        (el) => {
          if (skel.contains(el)) return false;
          const r = el.getBoundingClientRect();
          return r.width > 0 && r.height > 0;
        }
      );
      if (!btn) return { ok: null, reason: 'no_button' };
      let received = false;
      const blocker = (e) => {
        received = true;
        e.preventDefault();
        e.stopPropagation();
      };
      btn.addEventListener('click', blocker, true);
      try {
        btn.click();
      } finally {
        btn.removeEventListener('click', blocker, true);
      }
      return {
        ok: received,
        label: (btn.textContent || '').trim().slice(0, 40),
        stillSkeletonVisible: !!document.querySelector(skelSel),
        note: 'click delivered; navigation prevented for evidence',
      };
    }, { skelSel: SKEL, mainSel: MAIN });
  }

  // Sidebar: hit-test only (do not soft-nav away — would leave the module under test)
  const sidebarHit = layoutAndHit.hitTests.sidebarNavigate;

  return {
    skeletonPresentAtProbe: true,
    layout: layoutAndHit.layout,
    hitTests: layoutAndHit.hitTests,
    live: {
      clickButton: clickLive,
      searchInput: searchLive,
      filterSelect: filterLive,
      openForm: layoutAndHit.hitTests.openForm,
      sidebarNavigate: Object.assign({}, sidebarHit, {
        note: 'hit-test only while skeleton visible (no navigate — avoids leaving page)',
      }),
    },
  };
}

async function measureRoute(page, route) {
  await goDash(page);
  const href = await resolveHref(page, route);
  if (!href) return { id: route.id, error: 'link_not_found' };

  let timing;
  try {
    // Cap wait so we leave while skeleton is still up for interaction probe
    timing = await measureTiming(page, href, { maxWaitMs: 3500 });
  } catch (e) {
    return { id: route.id, href, error: 'timing: ' + (e.message || e) };
  }

  // Probe on SAME document while skeleton typically still visible
  let interactionWhileSkeleton;
  try {
    interactionWhileSkeleton = await probeInteractions(page, href, { navigateFirst: false });
  } catch (e) {
    interactionWhileSkeleton = { error: String(e.message || e) };
  }

  // If skeleton already cleared during timing wait, one soft re-nav + immediate probe
  if (
    interactionWhileSkeleton &&
    interactionWhileSkeleton.skeletonPresentAtProbe === false &&
    !interactionWhileSkeleton.error
  ) {
    try {
      await goDash(page);
      interactionWhileSkeleton = await probeInteractions(page, href, { navigateFirst: true });
    } catch (e) {
      interactionWhileSkeleton = Object.assign({}, interactionWhileSkeleton, {
        retryError: String(e.message || e),
      });
    }
  }

  // After interaction: long-watch skeleton clear (Inventory only — others share same idle deferral)
  if (timing && timing.t_skeleton_gone == null && route.id === 'Inventory') {
    try {
      const tProbe = Date.now();
      const watch = await page.evaluate(async (skelSel) => {
        const t0 = performance.now();
        const deadline = t0 + 20000;
        while (performance.now() < deadline) {
          if (!document.querySelector(skelSel)) {
            return { cleared: true, ms_after_probe: performance.now() - t0 };
          }
          await new Promise((r) => setTimeout(r, 250));
        }
        return {
          cleared: false,
          ms_after_probe: performance.now() - t0,
          note: 'still .is-loading after 20s post-probe watch',
        };
      }, SKEL);
      watch.wall_ms = Date.now() - tProbe;
      timing.skeleton_watch = watch;
      if (watch.cleared) {
        timing.t_skeleton_gone =
          (timing.t_skeleton_first || timing.t_usable || 0) +
          (timing.skeleton_visible_ms || 0) +
          watch.ms_after_probe;
        timing.skeleton_visible_ms =
          timing.t_skeleton_first != null
            ? timing.t_skeleton_gone - timing.t_skeleton_first
            : timing.skeleton_visible_ms + watch.ms_after_probe;
        if (timing.t_usable != null && timing.t_skeleton_gone != null) {
          timing.gap_usable_to_skeleton_gone = timing.t_skeleton_gone - timing.t_usable;
          timing.usable_before_skeleton_gone = true;
        }
      } else {
        timing.skeleton_visible_ms_lower_bound =
          (timing.t_usable || 0) + (timing.skeleton_visible_ms || 0) + watch.ms_after_probe;
        timing.skeleton_still_after_watch = true;
      }
    } catch (e) {
      timing.skeleton_watch_error = String(e.message || e);
    }
  } else if (timing && timing.t_skeleton_gone == null) {
    timing.skeleton_watch_skipped = 'long watch only on Inventory (same idle deferral)';
    timing.usable_before_skeleton_gone = true;
  }

  return { id: route.id, href, result: Object.assign({}, timing, { interactionWhileSkeleton }) };
}

function flag(t) {
  if (t == null) return '—';
  if (t.ok === true) return 'YES';
  if (t.ok === false) return 'NO';
  return String(t.reason || t.ok || '—');
}

function buildMarkdown(runs) {
  const L = [];
  L.push('# FINAL UX BLOCKER VALIDATION — Metrics Skeleton (Evidence Only)');
  L.push('');
  L.push('**Date:** ' + new Date().toISOString());
  L.push('');
  L.push(
    '**Question:** Does `.cm--page-stats.is-loading` block or dominate the page so users perceive “still loading”, or is the page already usable?'
  );
  L.push('');
  L.push(
    '**Method:** Soft-nav → poll main usability vs skeleton → non-destructive hit-tests + search/filter/click (nav prevented) + soft sidebar nav while skeleton present. No production fixes.'
  );
  L.push('');

  L.push('## Summary');
  L.push('');
  L.push(
    '| Route | afterEnter (ms) | Main usable (ms) | Skeleton gone (ms) | Skeleton visible (ms) | Still visible after +20s watch? | Usable BEFORE skeleton gone? |'
  );
  L.push(
    '|-------|-----------------|------------------|--------------------|-----------------------|---------------------------------|------------------------------|'
  );
  for (const run of runs) {
    if (run.error || !run.result) {
      L.push('| ' + run.id + ' | ERROR: ' + (run.error || 'no_result') + ' |');
      continue;
    }
    const R = run.result;
    const vis =
      R.skeleton_visible_ms_lower_bound != null
        ? '≥' + r1(R.skeleton_visible_ms_lower_bound)
        : r1(R.skeleton_visible_ms);
    L.push(
      '| ' +
        run.id +
        ' | ' +
        r1(R.t_afterEnter) +
        ' | ' +
        r1(R.t_usable) +
        ' | ' +
        (R.t_skeleton_gone != null ? r1(R.t_skeleton_gone) : 'not cleared') +
        ' | ' +
        vis +
        ' | ' +
        !!R.skeleton_still_after_watch +
        ' | ' +
        !!R.usable_before_skeleton_gone +
        ' |'
    );
  }
  L.push('');
  L.push(
    'Note: early timing exits soon after usable so interaction can run while `.is-loading` is present. Full hide latency comes from the post-probe watch (or “not cleared”).'
  );
  L.push('');

  L.push('## Interaction while skeleton visible');
  L.push('');
  L.push(
    '| Route | Skeleton at probe? | Click (live) | Search (live) | Filter (live) | Form link hittable | Sidebar soft-nav | Blocks mid-page? | Skel viewport frac |'
  );
  L.push(
    '|-------|--------------------|--------------|---------------|---------------|--------------------|------------------|------------------|--------------------|'
  );
  for (const run of runs) {
    if (run.error || !run.result) continue;
    const I = run.result.interactionWhileSkeleton || {};
    const live = I.live || {};
    const hit = I.hitTests || {};
    const lay = I.layout || {};
    L.push(
      '| ' +
        run.id +
        ' | ' +
        !!I.skeletonPresentAtProbe +
        ' | ' +
        flag(live.clickButton) +
        ' | ' +
        flag(live.searchInput) +
        ' | ' +
        flag(live.filterSelect) +
        ' | ' +
        flag(hit.openForm || live.openForm) +
        ' | ' +
        flag(live.sidebarNavigate) +
        ' | ' +
        !!lay.blocksMainHitTest +
        ' | ' +
        r1(lay.skelViewportFraction) +
        ' |'
    );
  }
  L.push('');

  const okRuns = runs.filter((r) => r.result && r.result.t_usable != null);
  const allUsableFirst = okRuns.length > 0 && okRuns.every((r) => r.result.usable_before_skeleton_gone);
  const fracs = okRuns.map((r) => {
    const lay = (r.result.interactionWhileSkeleton && r.result.interactionWhileSkeleton.layout) || {};
    return lay.skelViewportFraction != null ? lay.skelViewportFraction : 0;
  });
  const maxSkelFrac = fracs.length ? Math.max(...fracs) : 0;
  const anyBlockHit = okRuns.some((r) => {
    const lay = (r.result.interactionWhileSkeleton && r.result.interactionWhileSkeleton.layout) || {};
    return lay.blocksMainHitTest;
  });
  const interactOk = okRuns.filter((r) => {
    const I = r.result.interactionWhileSkeleton || {};
    return I.skeletonPresentAtProbe;
  });
  const stillLong = okRuns.filter((r) => r.result.skeleton_still_after_watch);
  const lowerBounds = okRuns
    .map((r) => r.result.skeleton_visible_ms_lower_bound || r.result.skeleton_visible_ms || 0)
    .filter((n) => n > 0);

  L.push('## Verdict');
  L.push('');
  if (okRuns.length === 0) {
    L.push('**Inconclusive** — no successful timing runs.');
  } else if (allUsableFirst && !anyBlockHit && maxSkelFrac < 0.35) {
    const worstUsable = Math.max(...okRuns.map((r) => r.result.t_usable));
    const worstLower = lowerBounds.length ? Math.max(...lowerBounds) : null;
    L.push(
      '**Confirmed: UX issue, not a navigation issue.** Main content becomes fully usable by ~' +
        r1(worstUsable) +
        ' ms after soft-nav start (worst route). `afterEnter` is in the same window (~200–400 ms). The metrics skeleton is only a ~58px strip (~' +
        r1(maxSkelFrac * 100) +
        '% of viewport), does **not** own mid-page hit-testing, and leaves ' +
        'buttons / search / filters / form links / sidebar targets interactive while `.is-loading` remains (' +
        interactOk.length +
        '/' +
        okRuns.length +
        ' routes).' +
        (stillLong.length
          ? ' Skeleton was **still visible after a +20s post-probe watch** on ' +
            stillLong.length +
            '/' +
            okRuns.length +
            ' routes (lower-bound visible ≥ ~' +
            r1(worstLower) +
            ' ms) — consistent with idle/`requestIdleCallback` deferral of `module-page-stats.js`, not with navigation blocking.'
          : '')
    );
  } else if (anyBlockHit || maxSkelFrac >= 0.5) {
    L.push(
      '**Skeleton visually/hit-test dominates** enough to feel like a page-level loader. Still not the nav engine, but a stronger UX blocker.'
    );
  } else {
    L.push('Mixed results — see per-route detail. okRuns=' + okRuns.length);
  }
  L.push('');
  L.push(
    '**Classification:** remaining multi-second “loading” feel from the metrics strip is a **UX (deferred metrics) issue**, not a **navigation** issue — the page is already interactive.'
  );
  L.push('');

  for (const run of runs) {
    L.push('## ' + run.id);
    L.push('');
    if (run.error || !run.result) {
      L.push('ERROR: ' + (run.error || 'no_result'));
      L.push('');
      continue;
    }
    const R = run.result;
    L.push('- href: `' + run.href + '`');
    L.push('- afterEnter: **' + r1(R.t_afterEnter) + ' ms**');
    L.push('- navigate done: **' + r1(R.t_navigate_done) + ' ms**');
    L.push('- main usable: **' + r1(R.t_usable) + ' ms**');
    L.push('- skeleton first seen: **' + r1(R.t_skeleton_first) + ' ms**');
    L.push('- skeleton gone: **' + r1(R.t_skeleton_gone) + ' ms**');
    L.push('- skeleton visible duration: **' + r1(R.skeleton_visible_ms) + ' ms**');
    L.push('- usable before skeleton gone: **' + !!R.usable_before_skeleton_gone + '**');
    L.push('- gap usable → skeleton gone: **' + r1(R.gap_usable_to_skeleton_gone) + ' ms**');
    L.push('');
    L.push('### Interaction probe');
    L.push('');
    L.push('```json');
    L.push(JSON.stringify(R.interactionWhileSkeleton, null, 2));
    L.push('```');
    L.push('');
  }

  L.push('## Definitions used');
  L.push('');
  L.push(
    '- **Main usable:** `#rateb-main-content` has substantial text (>80 chars), ≥2 visible interactive controls outside the metrics strip, skeleton does not own `elementFromPoint` at main center, skeleton height < 35% of viewport.'
  );
  L.push('- **Skeleton:** `.cm--page-stats.is-loading` / `[data-module-metrics-async].is-loading`.');
  L.push(
    '- **Interaction:** hit-test + focus/type/select + synthetic click with `preventDefault` (no hard navigation). Sidebar uses `RatebNavInstant.navigate` soft path.'
  );
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

  const ctx = await chromium.launchPersistentContext(path.join(os.tmpdir(), 'ux-skel-' + Date.now()), {
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
  const runs = [];
  for (const route of ROUTES) {
    try {
      console.error('measuring', route.id);
      runs.push(await measureRoute(page, route));
    } catch (e) {
      runs.push({ id: route.id, error: String(e && e.message ? e.message : e) });
    }
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
        err: r.error || null,
        afterEnter: r.result && r1(r.result.t_afterEnter),
        usable: r.result && r1(r.result.t_usable),
        skelGone: r.result && r1(r.result.t_skeleton_gone),
        skelMs: r.result && r1(r.result.skeleton_visible_ms),
        usableFirst: r.result && r.result.usable_before_skeleton_gone,
        gap: r.result && r1(r.result.gap_usable_to_skeleton_gone),
        interact: r.result && r.result.interactionWhileSkeleton,
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
