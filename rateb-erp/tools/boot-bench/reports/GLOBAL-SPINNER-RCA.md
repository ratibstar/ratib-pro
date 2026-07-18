# GLOBAL SPINNER RCA (Evidence Only)

**Date:** 2026-07-18  
**Status:** MEASURE ONLY — no production fixes  
**Harness:** `rateb-erp/tools/boot-bench/global-spinner-rca.js`  
**JSON:** `rateb-erp/tools/boot-bench/reports/GLOBAL-SPINNER-RCA-1784386785447.json`  
**Focused traces:** Inventory metrics poll + warm banner (session measurements below)

**Premise:** Navigation engine is proven not to contain the multi-second delay. This RCA finds the **loader users actually see**.

---

## Verdict

### The spinner users see for several seconds

**`.cm--page-stats.is-loading`** — the **module metrics skeleton strip** at the top of module pages (Inventory, Purchasing, HR, Companies, …).

```
Click → content already swapped (nav done)
    ↓
Spinner shown: .cm--page-stats.is-loading  (in swapped HTML)
    ↓
★ BLOCKED: module-page-stats.js not yet loaded
   (deferred in main.php idleQueue via afterInteraction)
    ↓
★ BLOCKED again: requestIdleCallback(..., { timeout: 2500 })
   before fetch even starts
    ↓
fetch(admin/api/module-metrics?route=...)
    ↓
Spinner hidden: classList.remove('is-loading') in renderStrip
```

**Exact Promise / gate that keeps it visible for seconds:**

1. **Primary (multi-second):** deferred load of `module-page-stats.js` — `views/layouts/main.php` `$ratebIdleScripts` + `afterInteraction()` (lines **655–656**, **720–750**). Until that script loads and registers `rateb:nav:afterEnter`, **nothing hides `.is-loading`**.
2. **Secondary:** `requestIdleCallback(loadMetrics, { timeout: 2500 })` in `module-page-stats.js` **79–82** — delays the metrics AJAX after boot.
3. **Tertiary:** `fetch(data-module-metrics-url)` — AJAX duration after (1)+(2).

Runtime proof (Inventory soft-nav):

| Event | t after swapTo start |
|-------|----------------------|
| HTML fetch done | ~157 ms |
| `afterEnter` | ~167 ms |
| `.is-loading` present | **167 ms → still true at 12+ s** |
| `data-module-metrics-url` | `admin/api/module-metrics?route=admin/ops/inventory` |
| `module-metrics` fetch | **never started in 12 s window** |
| Other fetches while skeleton up | connectivity-probe; later CSS/warm URLs — **not** what clears the skeleton |

→ Skeleton remains **several seconds** with **no controlling metrics Promise yet** — the controlling “Promise” is effectively **“idle script chain has not loaded `module-page-stats.js`”**.

---

## Static inventory (Admin ERP)

| ID | File | Who shows | Who hides | Controlling Promise | Waits AJAX? | Waits module bootstrap? |
|----|------|-----------|-----------|---------------------|-------------|-------------------------|
| **module_metrics_skeleton** | `views/components/module-page-stats.php:14` + `public/assets/js/module-page-stats.js` | PHP adds `is-loading` when `$async` | `renderStrip()` L43 `classList.remove('is-loading')` (or `container.remove()` on fail) | **Idle load of `module-page-stats.js`** then `requestIdleCallback` then `fetch(metricsUrl)` | Yes (after script loads) | Yes |
| offline_warm_progress | `public/assets/offline/erp-offline-full-warm.js` | `ensureProgressUi` L460 / `startFullWarm` L883 | `box2.remove()` after warm done + **8000 ms** L972–974 | `startFullWarm` → `runQueue` asset+page fetches | Yes (many) | No |
| accounting_control | `control-center-phase7.js` `showLoading` L17 | `showLoading(true)` | `showLoading(false)` | section AJAX | Yes | Yes (accounting-control only) |
| section_shell_spinner | `_section-shell.php:9` | PHP markup | phase7 `showLoading(false)` | section load | Yes | Yes |
| entity_documents_modal | `entity-documents-modal.js:132` | inject spinner-border | replace modal HTML | documents fetch | Yes | No |
| approvals row | `approvals/index.php:206` + `approvals-oversight.js` | fa-spinner | `setRowBusy(false)` | action fetch | Yes | No |
| login barcode | `login.php` / `erp-login-barcode.js` | fa-spin | pair complete | pair poll | Yes | Login only |
| browser tab spinner | Chromium chrome | full document navigation | `load` | document nav | N/A | Soft-nav does not drive this |
| nprogress / pace / loadingManager / busyCounter / showLoader | — | **NOT FOUND** | — | — | — | — |

---

## Call path for the multi-second skeleton

```
main.php
  $ratebIdleScripts[] = module-page-stats.js     [L656]
  afterInteraction(fn)                           [L720]
    waits: pointerdown|keydown|touchstart|scroll
         OR requestIdleCallback(timeout: 4000) after load
    → chain(idleQueue) sequential script inject  [L740]

module-page-stats.js
  boot() on DOMContentLoaded + rateb:nav:afterEnter  [L71–92]
    for each [data-module-metrics-async]:
      mark data-rateb-metrics-loaded=1
      requestIdleCallback(loadMetrics, { timeout: 2500 })  [L79]
        → fetch(metricsUrl)                                  [L57]
        → renderStrip → remove is-loading                    [L43]

module-page-stats.php
  <div class="cm cm--page-stats … is-loading" data-module-metrics-async>
    skeleton strip…
```

---

## Runtime click matrix (Dashboard → Companies / Inventory / Purchasing / HR)

Automation matrix initially under-counted sustained visibility (observer noise). Focused Inventory poll is authoritative:

| Route | DOM spinner that appears | Sustained multi-second? | Notes |
|-------|--------------------------|-------------------------|-------|
| Dashboard | none in short window | — | |
| Companies / Inventory / Purchasing / HR | **`.cm--page-stats.is-loading`** | **YES** | Present from DOM swap until metrics script+fetch complete |
| Tab spinner (CDP) | may fire on pushState | N/A | Not the in-page loader |

### Inventory focused timeline

```
Spinner shown @ ~167 ms   (.cm--page-stats.is-loading after afterEnter)
  pending: (no module-metrics fetch yet)
  ↓
Every outstanding fetch while visible (examples):
  - connectivity-probe.json (~826–923 ms)
  - later: CSS asset warms /admin HTML (~6–12 s) — do NOT clear skeleton
  ↓
module-metrics fetch: NOT OBSERVED for ≥12 s
  ↓
Spinner still visible @ 12 s+
```

**Which exact Promise keeps it visible?**  
Not a single in-flight metrics Promise — the skeleton is stuck because **`module-page-stats.js` has not run `boot()` yet** (idle deferred). The hide path never starts.

---

## Secondary multi-second UI: offline warm banner

| Item | Evidence |
|------|----------|
| Selector | `#rateb-offline-warm-progress` |
| Show | `ensureProgressUi` / `startFullWarm` |
| Hide | 8 s after warm completion (`setTimeout(..., 8000)` L972) |
| Promise | Full warm `runQueue` over dozens of URLs |
| Measured | Banner can remain ~7–8 s **after** text already says «أوفلاين جاهز» |

This is a **fixed corner banner**, not the page-top metrics skeleton. It can overlap navigation but is owned by offline warm, not `swapTo`.

---

## What this is NOT

| Ruled out | Why |
|-----------|-----|
| `swapTo` / `fetchHtml` / DOM replace | Completes in ~150–200 ms; `afterEnter` at ~167 ms while skeleton still shows for seconds |
| Global NProgress/Pace overlay | Not in codebase |
| Browser tab spinner as the in-page loader | Soft content-swap; skeleton is DOM |

---

## Answer to the question

> Which exact Promise or request causes the spinner to remain visible for several seconds?

**The user-visible multi-second spinner is `.cm--page-stats.is-loading`.**

It stays up because:

1. **`module-page-stats.js` is on the PERF-P3 idle script queue** (`main.php` `afterInteraction` → `idleQueue`), so the hide/fetch logic often has not registered when the swapped HTML already shows the skeleton; and/or  
2. After load, **`requestIdleCallback(..., 2500)`** further delays `fetch(admin/api/module-metrics?...)`**; then**  
3. That **metrics fetch** finally clears `is-loading`.

In the measured Inventory session, (1) dominated: skeleton visible **12+ seconds** with **zero** `module-metrics` requests — so the blocking “Promise” is the **idle script load gate**, not navigation and not an in-flight metrics AJAX.

No production code was modified.
