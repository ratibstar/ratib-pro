# Full Frontend Performance Audit — Admin ERP

**Measured:** 2026-07-18T06:35:40Z  
**URL:** `https://rateb.sa/rateb-erp/public/admin/?company_id=22`  
**Tools:** Playwright (Chromium) + Performance API / Long Tasks / Layout Shift + Lighthouse 13.4  
**Mode:** Measure only — no ERP architecture changes  
**Evidence JSON:** `frontend-perf-remeasure-1784356540233.json`

## Snapshot

| Metric | Cold (cache disabled) | Warm |
|--------|----------------------|------|
| NavTiming TTFB (client-observed) | 342 ms | 144 ms |
| DCL | 1049 ms | 214 ms |
| FCP | 1120 ms | 220 ms |
| Long tasks sum | 75 ms (1 task) | — |
| CLS | 0.002 | — |
| Lighthouse score | 0.98 | — |
| Lighthouse TBT | 0 ms | — |
| Lighthouse LCP | 0.8 s | — |
| IndexedDB opens | 0 | — |
| Deferred `<script>` tags | 13 | — |
| Icon nodes (FA) | 198 | — |
| `addEventListener` during boot | 557 | — |

**Sidebar click → usable**

| Module | Cold | Warm |
|--------|------|------|
| HR | 499 ms (HTML fetch 373 ms, uncached) | 59 ms |
| Inventory | 274 ms (HTML fetch 143 ms) | 127 ms |
| Accounting | 376 ms (HTML fetch 189 ms) | 149 ms |

**Network context (not patch targets):** auditor→origin TCP+TLS ≈ 340 ms; client-observed document TTFB ≈ 342 ms. Origin TTFB was already verified at 13–28 ms — **no backend recommendations**.

---

## Top 20 bottlenecks (≥ 5 ms, ranked by measured ms)

| Rank | ms | Save~ | P | Area | Bottleneck | File | Function |
|------|-----|-------|---|------|------------|------|----------|
| 1 | 903 | 700 | P0 | 11 | Post-FP fetch sum (5 requests after FCP) | `public/assets/js/erp-nav-instant.js` + `public/assets/offline/erp-offline-full-warm.js` | `idlePrefetchVisible` / `prefetchUrl` / `runPrefetchQueue` + full-warm queue |
| 2 | 686 | 200 | P0 | 15 | Deferred script waterfall span (13 `defer` tags) | `views/layouts/main.php` | bottom `<script defer>` block |
| 3 | 577 | 400 | P0 | 12 | `document.fonts.ready` | `views/layouts/main.php` + `public/assets/vendor/fonts/tajawal/` | `rateb_tajawal_font_css()` |
| 4 | 546 | 150 | P1 | 19 | LH main-thread “Other” | layout + CSSOM + idle accounting | paint/idle bucket (LH) |
| 5 | 499 | 400 | P0 | 9 | Sidebar cold nav → HR usable | `public/assets/js/erp-nav-instant.js` | `navigate` / `afterEnter` (HTML swap fetch) |
| 6 | 426 | 120 | P1 | 19 | LH Rendering (paint/composite) | `views/layouts/main.php` + CSS | first paint / layer work |
| 7 | 417 | 250 | P0 | 1 | FCP − responseStart (frontend render) | `views/layouts/main.php` | blocking CSS + HTML parse path |
| 8 | 374 | 300 | P0 | 11 | Post-FP fetch `/admin` (prefetch) | `public/assets/js/erp-nav-instant.js` | `prefetchUrl` @ ~4.2 s |
| 9 | 376 | 300 | P0 | 9 | Sidebar cold → accounting usable | `public/assets/js/erp-nav-instant.js` | `navigate` |
| 10 | 319 | 80 | P1 | 2/3 | `bootstrap.bundle.min.js` wall (80 KB decoded) | `public/assets/vendor/bootstrap/5.3.3/bootstrap.bundle.min.js` | parse/eval (defer) |
| 11 | 313 | 220 | P1 | 13 | Font Awesome `all.min.css` | `views/layouts/main.php` | `rateb_fontawesome_css()` |
| 12 | 307 | 150 | P1 | 7 | HTML parse → `domInteractive` | `views/layouts/main.php` | sidebar markup loop (~32 KB sidebar HTML, 802 DOM nodes) |
| 13 | 274 | 200 | P1 | 9 | Sidebar cold → inventory usable | `public/assets/js/erp-nav-instant.js` | `navigate` |
| 14 | 220 | 180 | P1 | 12/13 | `fa-solid-900.woff2` | `public/assets/vendor/fontawesome/6.5.2/webfonts/fa-solid-900.woff2` | icon font download |
| 15 | 207 | 150 | P1 | 4 | Render-blocking CSS chain | `bootstrap.rtl.min.css` (+ variables/main/components/dark/rtl) | render-blocking `<link>` in `main.php` |
| 16 | 197 | 150 | P1 | 4 | Blocking CSS: `bootstrap.rtl.min.css` | `public/assets/vendor/bootstrap/5.3.3/bootstrap.rtl.min.css` | render-blocking stylesheet |
| 17 | 164 | 120 | P1 | 11 | Post-FP fetch `ops/profile` | `erp-nav-instant.js` idlePrefetch (visible nav link) | `prefetchUrl` |
| 18 | 159 | 100 | P2 | 18 | Offline bootstrap: `erp-offline-full-warm.js` | `public/assets/offline/erp-offline-full-warm.js` | full warm scheduler (CONCURRENCY=1, GAP_MS=1200) |
| 19 | 154 | 60 | P2 | 19 | LH Style & Layout | CSS + DOM | style/layout recalculation |
| 20 | 104 | 80 | P2 | 11 | Post-FP `connectivity-probe.json` | `public/assets/js/connectivity-indicator.js` | `probe()` |

### Just below top 20 (still ≥ 5 ms)

| ms | Item | File / fn |
|----|------|-----------|
| 143 | Post-FP fetch `ops/notifications` | `erp-nav-instant.js` → `prefetchUrl` |
| 118 | Post-FP fetch `rateb-platform-catalog/admin` | idlePrefetch / warm |
| 106 | `erp-offline-nav-guard.js` | `public/assets/offline/erp-offline-nav-guard.js` |
| 100 | PWA icon `erp-icon-192.png` | `public/assets/pwa/erp-icon-192.png` |
| 75 | Long task @ 931 ms | main-thread (likely deferred script eval burst) |
| 64 | LH Script Evaluation | deferred JS |
| 24 | Icon-node cost proxy (198 FA icons) | `main.php` sidebar `<i class="fas">` |
| 22 | Listener boot cost proxy (557 adds) | `erp-nav-instant.js` `bindPrefetch` + `app.js` `RatebApp.init` |

---

## Area checklist (1–20)

| # | Area | Verdict |
|---|------|---------|
| 1 | Navigation timing | Cold DCL ~1.05 s; warm ~214 ms. Frontend gap after responseStart ≈ 417 ms to FCP. |
| 2 | Resource waterfall | ~13 defer scripts start ~714 ms; wall ~310–320 ms each (H2 multiplex + queue). Largest decoded: chart.umd 206 KB, bootstrap.bundle 81 KB. |
| 3 | JS execution | LH scriptEvaluation 64 ms; bootup scripting ~11–34 ms; **one long task 75 ms**. |
| 4 | CSS blocking | 7 blocking stylesheets; chain end ~910 ms; bootstrap.rtl dominates (197 ms). FA/dashboard/ar-typography already non-blocking (`media=print`). |
| 5 | Layout shifts | CLS 0.002 — ignore (< material). |
| 6 | Long tasks >50 ms | 1× 75 ms @ 931 ms. |
| 7 | DOM render delays | 802 nodes; sidebar HTML ~32 KB; htmlProcess 307 ms. |
| 8 | Event listeners | 557 `addEventListener` during boot (`bindPrefetch` + app init). |
| 9 | Sidebar rendering | Visible with HTML; cold module swap 274–499 ms. |
| 10 | Dashboard rendering | Warm FCP 220 ms; LH LCP 0.8 s. |
| 11 | API after first paint | 5 fetches: probe, `/admin`, catalog, notifications, profile (sum 903 ms). |
| 12 | Font loading | `fonts.ready` 577 ms; Tajawal CSS blocking 101 ms. |
| 13 | Icon loading | FA CSS 313 ms + `fa-solid-900.woff2` 220 ms; 198 icon nodes. |
| 14 | Images | Single meaningful: PWA icon ~100 ms. |
| 15 | Deferred scripts | 13 defer tags; waterfall span 686 ms. |
| 16 | IndexedDB init | **0 opens** on Admin cold path. |
| 17 | Service Worker | `pos-sw.js` — `ready` 816 ms; register in `main.php` `__ratebErpRegisterSwOnce`. Not on critical path for TBT (0). |
| 18 | Offline bootstrap | `erp-offline-full-warm.js`, `erp-pwa-install.js`, nav-guard, tenant-context load + warm fetches. |
| 19 | Main thread blocking | TBT **0**; LH main-thread work ~1.2 s mostly Rendering/Other (not long-task blocking). |
| 20 | Memory | Heap ~2.3 MB cold — negligible. |

---

## Patch priority (frontend only)

**P0 — highest ROI**
1. Further throttle / prioritize `idlePrefetchVisible` + `erp-offline-full-warm` so they never contend with first module navigation (already serialized; still ~900 ms post-FP HTML).
2. Reduce cold sidebar HTML swap cost (Cache API hit makes warm 59–149 ms — ensure critical nav links warm earlier without stampede).
3. Fonts: subset Tajawal + FA; `font-display: optional/swap`; delay non-critical FA weights.

**P1**
4. Critical CSS: keep only variables + minimal shell blocking; async the rest of bootstrap.rtl if safe.
5. Cut defer script count on dashboard (bootstrap/app/nav stay; charts/offline-warm later).
6. Shrink sidebar DOM (198 icons / large nav tree).

**P2**
7. SW ready is parallel — only optimize if offline-first UX requires earlier controller.
8. Listener/`bindPrefetch` batching micro-savings.
9. Connectivity probe defer slightly later if it races prefetch (already known interaction).

**Do not patch (measured)**
- Backend/OPcache/FPM (origin TTFB already 13–28 ms).
- CLS, IndexedDB, memory, TBT (already clean).
