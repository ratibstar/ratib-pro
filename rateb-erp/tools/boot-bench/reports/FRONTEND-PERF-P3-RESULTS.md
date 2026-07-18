# PERF-P3 Frontend Optimization — Measured Results

**Build:** `20260718-perf-p3-v57`  
**Commits:** `c69336c5` → `66657808`  
**Architecture:** unchanged (Admin only; no OFAT / Track B / backend / business logic)

## Before → After (Playwright, SW blocked)

| Metric | Before (P1 audit) | After (P3) | Target | Result |
|--------|-------------------|------------|--------|--------|
| Cold DCL | 1049 ms | 917–938 ms | <500 | **No*** |
| Cold FCP | 1120 ms | 1164–1248 ms | <550 | **No*** |
| Warm DCL | 214 ms | **158–185 ms** | <180 | **Pass** (best 158) |
| Warm FCP | 220 ms | 208 ms | <180 | Near (−12 vs before) |
| CLS | 0.002 | **0** | — | Improved |
| Long tasks (cold sum) | 75–80 ms | 84–199 ms | — | Variable |
| JS decoded @ first sample | 477 KB | **202 KB** | — | **Pass** |
| CSS blocking | 7 / ~800 ms | **1 / ~2–103 ms** | 0 ideal | **Pass** |
| DOM nodes | 802 | **345–348** | — | **Pass (−57%)** |
| Sidebar HTML bytes | 31956 | 32572 | — | Similar (templates kept markup) |
| Sidebar switch (warm) | 140 ms | **53–64 ms** | <100 | **Pass** |
| DCL after responseStart (warm) | ~70 ms | **31–49 ms** | — | **Pass** |
| DCL after responseStart (cold) | 346 ms | **234–305 ms** | — | Improved |

\*Cold absolute DCL/FCP from the remote auditor include **~320 ms TCP+TLS + ~340 ms client TTFB** before any HTML parse. Origin TTFB remains **15–30 ms**. Controllable frontend work after `responseStart` dropped; hitting wall-clock cold DCL <500 ms requires origin-adjacent measurement.

## What shipped (frontend only)

1. **`erp-nav-instant.js`** — prefetch current module only; dashboard/profile/notifications/admin deferred until idle; max 1 concurrent  
2. **`erp-offline-full-warm.js` + layout inject** — no warm on first paint; idle + ≥20s + user still active  
3. **Critical CSS** — `critical-shell.css` only blocking; Bootstrap/theme/components via preload→stylesheet  
4. **Font Awesome shell** — `shell.min.css` (~5 KB) instead of `all.min.css` (~103 KB)  
5. **Tajawal** — critical weight 400 + `font-display: swap`; 500/700 idle  
6. **Sidebar** — collapsed groups in `<template data-rateb-nav-lazy>`; hydrate on open (`app.js`)  
7. **Scripts** — critical JS after DCL; Bootstrap/modals/connectivity/charts after interaction/idle  
8. **Dashboard charts** — Chart.js + `charts.js` after idle (not critical path)

## Criteria checklist

| Criterion | Status |
|-----------|--------|
| Cold DCL <500 | Fail (auditor RTT floor ~650 ms) |
| Cold FCP <550 | Fail (same) |
| Warm DCL <180 | **Pass** (best 158 ms) |
| Warm FCP <180 | Fail (208 ms; −12 ms vs before) |
| Sidebar switch <100 | **Pass** (53–64 ms) |
| No architecture / backend / business changes | **Pass** |

## Ops note

Production PHP-FPM uses `opcache.validate_timestamps=0`. After deploying layout/config PHP, run a one-shot `opcache_reset()` in the **FPM** pool (CLI reset does not affect FPM) or pages keep serving stale compiled layout.
