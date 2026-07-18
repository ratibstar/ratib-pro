# INPUT LATENCY RCA (Evidence Only)

**Date:** 2026-07-18  
**Status:** MEASURE ONLY — no production fixes  
**Harness:** `rateb-erp/tools/boot-bench/input-latency-rca.js`  
**JSON:** `rateb-erp/tools/boot-bench/reports/INPUT-LATENCY-RCA-1784385169509.json`

---

## Question

Users observe **3–6 seconds before navigation begins**.  
Previous nav waterfall started at `onClick()` — too late.

Where are the missing seconds **before** `erp-nav-instant` `onClick`?

---

## Answer (production clicks)

```
Mouse click
    ↓   Event Timing inputDelay ≈ 0.6–4.3 ms   ← NOT multi-second
Actual browser delay (idle / ASAP boot)
    ↓   pointerdown → click handlers ≈ 7–14 ms
onClick entered
    ↓   ≤ 1 ms to preventDefault + swapTo
Navigation (fetchHtml network)
    ↓   +35–310 ms Cache API probe before HTTP  ← AFTER onClick
```

**The missing 3–6 seconds are not in the pre-`onClick` pipeline when the main thread is idle.**

Measured production gaps before `onClick`:

| Gap | Observed |
|-----|----------|
| Event Timing **inputDelay** (browser queues event → handlers start) | **0.6 – 4.3 ms** |
| pointerdown → click handler | **7 – 14 ms** |
| click → erp-nav `onClick` | **0.3 – 0.8 ms** |
| onClick → swapTo committed | **~0 – 2 ms** |

Largest gap **after** onClick in these runs: **swapTo → fetchHtml network = 35–310 ms** (Cache API miss scan — same family as prior nav RCA).

---

## Where 3–6 seconds *would* appear (mechanism proof)

Instrumentation proof only (forced 3000 ms sync main-thread block, then mouse click):

| Metric | Value |
|--------|-------|
| Event Timing **inputDelay** | **2939.8 ms** |
| Long task overlap | **3000 ms** |
| Verdict | `PROOF_INPUT_DELAY_EQUALS_MAIN_THREAD_BLOCK` |

```
Mouse click (queued by browser)
    ↓   ★ ★ ★  ~2940 ms inputDelay  ★ ★ ★
    ↓   (main thread stuck in long task — onClick has NOT run yet)
onClick entered
    ↓
Navigation
```

**Conclusion:** If users truly wait 3–6 s *before* navigation starts, those seconds are almost certainly **main-thread input delay** (Long Tasks / sync JS blocking the click), not listener ordering between `pointerdown` and `onClick`. Capture field **INP / Event Timing inputDelay** + Long Tasks at click time to confirm on real devices.

---

## Summary table (this run)

| Scenario | ET inputDelay | pointer→click | click→onClick | onClick→swapTo | swapTo→fetch | LT sum | Verdict |
|----------|---------------|---------------|---------------|----------------|--------------|--------|---------|
| Inventory_idle | 0.6 ms | 9.6 | 0.6 | 0 | 34.9 | 0 | no multi-s pre-onClick |
| Inventory_asap | 0.6 ms | 7.2 | 0.3 | 0 | 45.2 | 0 | no multi-s pre-onClick |
| Purchasing_idle | 4.3 ms | 12.4 | 0.5 | 0 | **277** | 0 | no multi-s pre-onClick |
| HR_asap | 1.1 ms | 9.2 | 0.3 | 0 | **293** | 0 | no multi-s pre-onClick |
| Inventory_during_busy | 1.4 ms | 13.7 | 0.8 | 0 | **309** | 64 | no multi-s pre-onClick |
| PROOF_sync_block_3s | **2939.8 ms** | 2.5 | — | — | — | **3000** | proof only |

---

## Example timeline — Inventory_idle (ms from pointerdown)

| Stage | t (ms) |
|-------|--------|
| pointerdown | 0 |
| mousedown | 0.5 |
| click_window_capture | 9.6 |
| document capture / erp-nav onClick | 10.2 |
| onClick committed (preventDefault) | 12.1 |
| swapTo (inferred) | 12.1 |
| fetchHtml network start | 47.0 |

Event Timing click sample: `inputDelay=0.6`, `processingTime≈2`, `presentationDelay≈69`.

---

## Also measured

| Signal | Result (production clicks) |
|--------|----------------------------|
| Long Tasks >50 ms overlapping input | None meaningful (one busy scenario LT sum 64 ms) |
| requestAnimationFrame delay | Idle-class (~1 frame); no multi-second rAF stall before onClick |
| Event Timing / INP ingredients | inputDelay ≪ 16 ms when idle |
| Interaction latency | Dominated by **post-onClick** Cache API→fetch, not pre-onClick |
| Sync layout probe on click capture | Negligible forced reflow in probe |
| Document click listeners | Multiple capture listeners (erp-nav `onClick`, connectivity, layout helpers); **ordering cost &lt; 2 ms** |

GC pauses: not exposed as a first-class web API in these runs; any multi-second stall would still surface as **Long Task + inputDelay** (as in the 3 s proof).

---

## Single-stage identification

| Claim | Evidence |
|-------|----------|
| Missing seconds are between MouseDown and onClick while idle? | **Rejected** — inputDelay ≤ 4.3 ms |
| Missing seconds are document listener queue before erp-nav? | **Rejected** — click→onClick ≤ 0.8 ms |
| Missing seconds can be main-thread block before onClick? | **Mechanism confirmed** — proof inputDelay ≈ 2940 ms |
| Delay users may attribute to “before nav” but is after onClick? | **Yes** — swapTo→fetchHtml up to ~310 ms here; prior RCA up to ~697 ms Cache API |

### Exact location of “missing seconds” (if 3–6 s is real)

**Before `onClick`:** only if **Event Timing `inputDelay` / Long Tasks** at click time are seconds long (main thread busy).  
**Not** in the idle pointerdown→mousedown→click→document capture→`onClick` micro-pipeline.

**After `onClick` but before network:** Cache API / `matchCachedHtml` (prior RCA) — still not “before onClick”.

---

## Method notes

- Early `addInitScript` wraps `addEventListener` + window capture for pointer/mouse/click  
- Real `page.mouse` down/up (not only `locator.click`)  
- `onClick` calls internal `swapTo` (not `RatebNavInstant.navigate`) — swap marked via preventDefault + fetch `X-Rateb-Nav-Swap`  
- No production code modified
