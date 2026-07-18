# PERF-P4 — Module Metrics UX Fix (Shipped)

**Commit:** `449f9312`  
**Build:** `20260718-perf-p4-metrics-ux-v59`  
**Deploy:** https://github.com/ratibstar/ratib-pro/actions/runs/29649772839 — **success**  
**Date:** 2026-07-18

## Problem

Navigation/`afterEnter` already finished in ~150–400 ms. The metrics strip (`.cm--page-stats.is-loading`) stayed up for seconds because `module-page-stats.js` sat on the **afterInteraction + idle** queue and then used `requestIdleCallback({ timeout: 2500 })` before fetch.

## Changes (exact scope)

| File | Change |
|------|--------|
| `rateb-erp/views/layouts/main.php` | Move `module-page-stats.js` from `$ratebIdleScripts` → `$ratebCriticalScripts` (always loaded with nav shell). |
| `rateb-erp/public/assets/js/module-page-stats.js` | Start fetch **immediately** on `afterEnter`/boot (no idle). Fail-soft **400 ms** → remove `.is-loading`, show `—` placeholders; upgrade when JSON arrives; silent retry on fail. |
| `rateb-erp/config/app.php` | Asset build → `20260718-perf-p4-metrics-ux-v59`. |

**Not touched:** `erp-nav-instant.js`, backend metrics API, architecture, warm pipeline.

## Diff summary

```
- idleQueue includes module-page-stats.js (afterInteraction)
+ critical chain includes module-page-stats.js (with erp-nav-instant)

- requestIdleCallback(run, { timeout: 2500 })
+ loadMetrics(el) immediately on afterEnter
+ FAILSOFT_MS = 400 → renderPlaceholder (—), fetch may still upgrade
+ silent retry after 8s on failure (no skeleton flash)
```

## Timing comparison (Playwright soft-nav)

### Before (UX blocker validation)

| Route | afterEnter | usable | skeleton still up |
|-------|------------|--------|-------------------|
| Inventory / Purchasing / HR / Companies | ~150–400 ms | ~170–400 ms | **≥18 s** (cold) / multi-second typical |

### After (PERF-P4 verify on production)

| Route | afterEnter | usable | skel gone | **skel − afterEnter** | metrics fetch start |
|-------|------------|--------|-----------|----------------------|---------------------|
| Inventory | 247 ms | 248 ms | 431 ms | **185 ms** | 247 ms (= afterEnter) |
| Purchasing | 210 ms | 211 ms | 463 ms | **253 ms** | 210 ms |
| HR | 302 ms | 303 ms | 534 ms | **231 ms** | 302 ms |
| Companies | 516 ms* | 517 ms* | 718 ms | **202 ms** | 516 ms |

\*Companies absolute times >400 ms are **nav/TTFB** to that href (afterEnter itself 516 ms), not metrics — skeleton still clears ~200 ms after afterEnter.

## Acceptance

| Criterion | Result |
|-----------|--------|
| Main interactive &lt;400 ms | **Pass** on Inventory / Purchasing / HR; Companies limited by nav `afterEnter` (~516 ms), metrics not on critical path |
| Skeleton removed &lt;500 ms after afterEnter | **Pass** (185–253 ms) |
| Metrics request async | **Pass** (fetch starts at afterEnter; does not gate swap) |
| No nav flow changes | **Pass** (`erp-nav-instant.js` untouched) |
| No architecture / backend logic changes | **Pass** |

## Confirmation

- Metrics fetch starts in the same tick as `afterEnter`.
- Skeleton no longer waits for afterInteraction / idle / 2500 ms ric.
- Page remains usable while strip loads; strip never owns perceived load.

Harness: `rateb-erp/tools/boot-bench/perf-p4-metrics-verify.js`  
Raw JSON: `rateb-erp/tools/boot-bench/reports/PERF-P4-METRICS-VERIFY.json`
