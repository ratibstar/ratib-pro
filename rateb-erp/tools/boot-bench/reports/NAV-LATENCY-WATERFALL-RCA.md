# Navigation Latency Waterfall RCA (Evidence Only)

**Date:** 2026-07-18  
**Status:** MEASURE ONLY — no production code changes  
**Harness:** `rateb-erp/tools/boot-bench/nav-latency-waterfall-rca.js`  
**Raw JSON:** `rateb-erp/tools/boot-bench/reports/NAV-LATENCY-WATERFALL-1784383741885.json`  
**Target:** `https://rateb.sa/rateb-erp/public/admin/*`  
**Auditor:** Playwright Chromium → production (remote RTT)

---

## Executive verdict

For **content-swap modules** (Inventory / HR / Purchasing / Customers), the first visit is slower than the second because the first visit is a **Cache API MISS** and must:

1. Sequentially scan ops Cache API keys/caches (**wait BEFORE fetch**)
2. Then `fetch()` HTML over the network (**T6→T8**)
3. Then parse + DOM swap + `afterEnter`

The **largest single stage observed** on a first visit in this run was:

### **Cache API miss scan before HTTP starts — Purchasing first: 697 ms (T0 → T6)**

That is the wait *before* `fetch()` starts (`matchCachedHtml` / `openOpsCaches` + many sequential `cache.match` calls). Network HTML fetch itself was only ~155 ms on that same navigation.

Second visits to the same module are fast because `afterEnter.fromCache = true` (HTML served from Cache API; network used only for background SWR revalidate).

**Note on “several seconds” spinner:** From this auditor, soft-swap first visits were **0.2–0.9 s**, not multi-second. A multi-second browser tab spinner is consistent with **full document navigation** (hard nav), which **all `/admin/ops/pos/*` links force** via `POS_PATH_RE` in `erp-nav-instant.js` (content-swap intentionally bypassed). Soft-swap does **not** paint a dedicated nav overlay; the visible “loading” for soft path is mainly empty/main content until swap completes.

---

## Method notes

| Item | Detail |
|------|--------|
| Pipeline | Live `erp-nav-instant.js`: click → `onClick` → `preventDefault` → `swapTo` → `fetchHtml` → Cache API → `fetch` → parse → `main.innerHTML` → `loadNewScripts` (network path) → `afterEnter` |
| T12 | **`beforeLeave`** (there is **no** `beforeEnter` hook) |
| Soft vs hard | Distinguished by document identity token (Playwright `framenavigated` also fires on `pushState` — ignored) |
| Instrumentation pitfall found | Wrapping `Response` with `Proxy` causes `Illegal invocation` → false `hardNavigate`. Fixed in harness only (not production). |

---

## First vs second totals (soft content-swap)

| Route | First total | Second total | First cache | Second cache | First HTML | Dominant first-visit wait |
|-------|-------------|--------------|-------------|--------------|------------|---------------------------|
| Inventory | **202.5 ms** | **85.9 ms** | MISS | HIT | 102,180 B | Network TTFB 135 ms (after 23 ms Cache API) |
| HR | **241.2 ms** | **127.0 ms** | MISS | HIT | 86,627 B | Cache API 60 ms + TTFB 144 ms |
| Sales (Customers `/admin/customers`) | **~197 ms** (API) | **~43 ms** (API) | MISS→HIT | HIT | — | Soft-swap OK |
| Purchasing | **892.9 ms** | **314.9 ms** | MISS | HIT | 94,656 B | **Cache API 697 ms before fetch** |
| Sales POS dashboard | **273 ms wall** (hard nav) | **205 ms wall** (hard nav) | document nav | document nav | 87,990 B | Full document load (no content-swap) |

---

## Waterfall — Inventory FIRST (cache MISS)

| Stage | t from click (ms) | Stage Δ (ms) |
|-------|-------------------|--------------|
| T0_click | 0 | 0 |
| T1_onClick | 0 | 0 |
| T2_preventDefault | 0.7 | 0.7 |
| T5b_cacheApi_done | 22.6 | 21.9 |
| T6_http_request_start | 22.6 | 0 |
| T7_first_byte | 157.7 | **135.1** ← network TTFB |
| T8_response_complete | 159.1 | 1.4 |
| T9_html_parsed | 162.8 | 3.7 |
| T10_dom_swap_start | 165.8 | 3.0 |
| T11_dom_swap_end | 167.6 | 1.8 |
| T12_beforeLeave | 1.1 | (fires at start; early) |
| T13_afterEnter | 171.3 | — |
| T14_page_interactive | 202.5 | 31.2 |

- Cache: **MISS** (`fromCache=false`)
- Network T6→T8: **136.5 ms**
- Wait before fetch (Cache API): **~23 ms**
- Wait after response→interactive: **43.4 ms**
- SW controller: present
- In-page spinner overlay: **not observed** (soft path)
- Long tasks >50ms: none in soft completion window
- Async hooks: `beforeLeave`, `afterEnter` (+ `RatebApp.reinit` listener)

## Waterfall — Inventory SECOND (cache HIT)

| Stage | t from click (ms) | Stage Δ (ms) |
|-------|-------------------|--------------|
| T0_click | 0 | 0 |
| T2_preventDefault | 0.4 | 0.4 |
| T5b_cacheApi_done | 36.1 | 35.7 ← Cache API lookup |
| T9_html_parsed | 49.9 | 13.8 |
| T11_dom_swap_end | 52.9 | — |
| T13_afterEnter | 55.8 | — |
| T14_page_interactive | 85.9 | 30.1 |

- Cache: **HIT** (`fromCache=true`)
- HTML from Cache API; background SWR `fetch` may still start (does not block `afterEnter`)

---

## Waterfall — HR FIRST (cache MISS)

| Stage | t from click (ms) | Stage Δ (ms) |
|-------|-------------------|--------------|
| T5b_cacheApi_done | 59.9 | **59.6** |
| T6_http_request_start | 59.9 | 0 |
| T7_first_byte | 203.7 | **143.8** |
| T8_response_complete | 206.5 | 2.8 |
| T11_dom_swap_end | 215.9 | — |
| T13_afterEnter | 218.4 | — |
| T14_page_interactive | 241.2 | 22.8 |

- Network T6→T8: **146.6 ms** | Cache API total: **60.4 ms**

## Waterfall — HR SECOND (cache HIT)

| Stage | t from click (ms) | Stage Δ (ms) |
|-------|-------------------|--------------|
| T5b_cacheApi_done | 101.5 | **101.1** ← Cache API scan until HIT |
| T11_dom_swap_end | 110.7 | — |
| T13_afterEnter | 112.9 | — |
| T14_page_interactive | 127.0 | 14.1 |

---

## Waterfall — Purchasing FIRST (cache MISS) — largest delay

| Stage | t from click (ms) | Stage Δ (ms) |
|-------|-------------------|--------------|
| T2_preventDefault | 0.2 | 0.2 |
| **T5b_cacheApi_done** | **697.1** | **696.9** ← **CULPRIT** |
| T6_http_request_start | 697.1 | 0 |
| T7_first_byte | 850.7 | 153.6 |
| T8_response_complete | 852.4 | 1.7 |
| T11_dom_swap_end | 862.6 | — |
| T13_afterEnter | 864.0 | — |
| T14_page_interactive | 892.9 | 28.9 |

### Cache API detail (Purchasing first)

- `caches.open` / `caches.keys` / many sequential `cache.match` across `rateb-erp-ops-pages-v34` + `rateb-erp-coexist-v34`
- Example match costs before HIT: **53.7, 70.4, 190.3, 106.8, 228.4 ms** (misses)
- **Cache API total: 710.1 ms**
- Network only **155.3 ms** after that

## Waterfall — Purchasing SECOND (cache HIT)

| Stage | t from click (ms) | Stage Δ (ms) |
|-------|-------------------|--------------|
| T5b_cacheApi_done | 286.4 | **286.1** |
| T11_dom_swap_end | 294.6 | — |
| T13_afterEnter | 295.9 | — |
| T14_page_interactive | 314.9 | 19.0 |

Still spends ~286 ms in Cache API scanning before the HIT key is found — then swap is ~10 ms.

---

## Sales

### A) Customers (soft content-swap) — `/admin/customers`

| Pass | Result |
|------|--------|
| First | `navigate()` **196.9 ms**, `ok:true` |
| Second | `navigate()` **42.9 ms**, `ok:true` |

Same pattern: first slower (network/cache miss), second cache-fast.

### B) POS dashboard (HARD document nav) — `/admin/ops/pos/dashboard`

`erp-nav-instant.js` `POS_PATH_RE` treats POS URLs as **non-interceptable** → browser full navigation → **tab loading spinner** for the whole document.

| Pass | Wall click→load | Navigation Timing duration | TTFB | transferSize |
|------|-----------------|----------------------------|------|--------------|
| First | **273 ms** | 266.6 ms | 142.6 ms | 88,290 |
| Second | **205 ms** | 201.5 ms | 140.0 ms | 88,290 |

From this auditor, POS hard-nav is ~0.2–0.3 s, not multi-second — but it is a **different pipeline** (full reload vs content-swap) and will scale with RTT/CPU/SW much more visibly as a spinner.

---

## Derived metrics summary

| Metric | Inventory 1st | Inventory 2nd | HR 1st | Purchasing 1st | Purchasing 2nd |
|--------|---------------|---------------|--------|----------------|----------------|
| Cache miss/hit | MISS | HIT | MISS | MISS | HIT |
| Cache API delay | 45 ms | 35 ms | 60 ms | **710 ms** | 288 ms |
| Network time T6→T8 | 136 ms | (SWR only) | 147 ms | 155 ms | (SWR only) |
| Wait BEFORE fetch | ~23 ms | ~36 ms | ~60 ms | **697 ms** | ~286 ms |
| Wait AFTER response | 43 ms | 33 ms | 35 ms | 41 ms | 20 ms |
| HTML size | 102 KB | cache | 87 KB | 95 KB | cache |
| SW delay (extra) | not separable beyond Cache API / fetch | | | | |

---

## Single-stage highlight

| Question | Evidence answer |
|----------|-----------------|
| What makes the **first** visit slower? | Cache **MISS** + mandatory Cache API probe + network HTML fetch |
| What makes the **second** visit fast? | Cache **HIT** → parse/swap/`afterEnter` without waiting on network body |
| **Single worst stage** in this evidence pack | **`matchCachedHtml` / Cache API miss scan before `fetch` starts`** — **697 ms** on Purchasing first (T0→T6) |
| Secondary stage | Network TTFB (**T6→T7**) ~135–155 ms when fetch runs |
| If user sees **multi-second tab spinner** | Suspect **hard document navigation** (POS paths, soft-swap failure/`hardNavigate`, or click before `RatebNavInstant` ready) — not the soft content-swap critical path measured above |

---

## Async hooks during first navigation

Observed on soft path:

1. `RatebModuleLifecycle.beforeLeave` / event `rateb:nav:beforeLeave`
2. `RatebModuleLifecycle.afterEnter` / event `rateb:nav:afterEnter` (detail includes `fromCache`, `ms`)
3. `document` listener in `app.js` → `RatebApp.reinit()` on `afterEnter`
4. Background (non-blocking on cache HIT): SWR `fetch` + `putHtmlLocally` + SW `CACHE_ERP_OPS_PAGE` postMessage

No `beforeEnter` hook exists in production nav code.

---

## Files (evidence only)

- Harness: `rateb-erp/tools/boot-bench/nav-latency-waterfall-rca.js`
- Debug helper: `rateb-erp/tools/boot-bench/nav-latency-debug.js`
- This report: `rateb-erp/tools/boot-bench/reports/NAV-LATENCY-WATERFALL-RCA.md`
- JSON: `rateb-erp/tools/boot-bench/reports/NAV-LATENCY-WATERFALL-1784383741885.json`
