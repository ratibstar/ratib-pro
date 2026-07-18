# FINAL BLOCKING POINT RCA — DOM Replacement (Evidence Only)

**Date:** 2026-07-18  
**Status:** MEASURE ONLY — no production fixes  
**Source of truth:** `rateb-erp/public/assets/js/erp-nav-instant.js`  
**Harness:** `rateb-erp/tools/boot-bench/blocking-point-dom-replace-rca.js`  
**JSON:** `rateb-erp/tools/boot-bench/reports/BLOCKING-POINT-DOM-REPLACE-1784386448024.json`

---

## Question

What **exactly** prevents DOM replacement between `swapTo()` and the content swap?

---

## Answer

**DOM replacement is intentionally delayed until `fetchHtml(href)` completes.**

There is **no** `replaceMainContent()` function. The equivalent is inline in `swapTo`:

```571:571:rateb-erp/public/assets/js/erp-nav-instant.js
            curMain.innerHTML = nextMain.innerHTML;
```

That statement runs **only inside** the `.then` of:

```561:561:rateb-erp/public/assets/js/erp-nav-instant.js
        return fetchHtml(href).then(function (pack) {
```

Until that Promise resolves, `pack.html` does not exist → parse cannot run → **`innerHTML` cannot run → old page stays**.

### Highlighted blocking await

| Field | Value |
|-------|-------|
| **Await** | `fetchHtml(href)` |
| **File** | `rateb-erp/public/assets/js/erp-nav-instant.js` |
| **Function** | `swapTo` |
| **Line** | **561** |
| **Role** | Sole gate before DOM replace |

---

## Call graph

```
swapTo(href)                                          [erp-nav-instant.js:552]
  │
  ├─ runLifecycle('beforeLeave')                      [sync]
  │
  └─ ★ await fetchHtml(href)                          [line 561]  ← BLOCKS DOM REPLACE
        │
        ├─ await matchCachedHtml(href)                [line 455]
        │     ├─ await openOpsCaches()                [line 433]
        │     │     ├─ await caches.keys()            [line 437]
        │     │     └─ await caches.open(name)×N      [line 446]
        │     └─ await cache.match(key)…              [line 470–474]  (sequential chain)
        │
        ├─ HIT path:
        │     └─ await cached.text()                  [line 529]
        │         (background SWR fetch does NOT block DOM)
        │
        └─ MISS path:
              └─ await fetchWithTimeout → fetch       [line 178 / 536]
                    └─ await res.text()               [line 543]
  │
  ▼  fetchHtml resolved  →  pack.html ready
  │
  DOM REPLACE: curMain.innerHTML = nextMain.innerHTML [line 571]
  │            (= replaceMainContent equivalent; SYNC)
  │
  ├─ HIT:  afterEnter() immediately                   [line 600–602]
  │        loadNewScripts scheduled idle              [line 603–607]
  │
  └─ MISS: await loadNewScripts(doc)                  [line 610]
              └─ afterEnter()                         [line 583]
```

---

## Which await finishes immediately before the old page disappears?

Runtime measurement (Inventory first, cache **MISS**):

| Order | Step | Line | Duration | t from swapTo |
|-------|------|------|----------|---------------|
| 1 | `swapTo()` entered | 552 | 0 | 0 |
| 2 | **await `fetchHtml(href)`** ★ | **561** | **198.8 ms** | 0 → 198.8 |
| 3 | └ await `caches.keys` | 437 | 22.3 | 0.8 |
| 4 | └ await `caches.open` | 446 | 6.4 | 23.4 |
| 5 | └ await `cache.match` (misses) | 471 | ~1–2 each | 30–34 |
| 6 | └ **await `fetch` (network)** | 178 | **149.7 ms** | 38.8 |
| 7 | └ **await `Response.text` (body)** | **543** | **1.9 ms** | 188.6 |
| 8 | **DOM_REPLACE `innerHTML`** | **571** | sync | **198.8** |
| 9 | `afterEnter` | 583 | sync | 207.7 |

**Await that finishes immediately before the old page disappears:**

> **`await Response.text()` (network body)** — `fetchHtml` line **543** — ended at **190.5 ms**, then sync parse + **`innerHTML` at 198.8 ms**.

On cache **HIT** (Inventory second):

| Step | Line | Duration | t from swapTo |
|------|------|----------|---------------|
| await `fetchHtml` ★ | 561 | **15.6 ms** | 0 → 15.6 |
| await `cached.text` / `Response.text` | 529 | 6.6 | 4.1 |
| **DOM_REPLACE** | 571 | sync | **15.6** |
| afterEnter | 583 | sync | 18.6 |

Immediate predecessor of DOM replace on HIT: **`await cached.text()`** (line 529).

---

## Runtime summary

| Run | DOM replace @ | Blocking `fetchHtml` | Immediate pre-DOM await | Dominant leaf inside |
|-----|---------------|----------------------|-------------------------|----------------------|
| Inventory first (MISS) | **198.8 ms** | 198.8 ms | `Response.text` L543 (1.9 ms) | **`fetch` 149.7 ms** |
| Inventory second (HIT) | **15.6 ms** | 15.6 ms | `Response.text` L529 (6.6 ms) | Cache text read |
| Purchasing first | **196.0 ms** | 196.0 ms | `Response.text` L543 (1.0 ms) | **`fetch` 143.1 ms** |
| Purchasing second | **15.8 ms** | 15.8 ms | `Response.text` L529 (6.7 ms) | Cache text read |

---

## Proof excerpt (intentional delay)

```552:571:rateb-erp/public/assets/js/erp-nav-instant.js
    function swapTo(href, opts) {
        opts = opts || {};
        if (navigating) {
            return Promise.resolve(false);
        }
        navigating = true;
        var t0 = performance.now();
        runLifecycle('beforeLeave', { href: root.location.href, next: href });

        return fetchHtml(href).then(function (pack) {
            var doc = new DOMParser().parseFromString(pack.html, 'text/html');
            if (!sameShell(doc)) {
                throw new Error('shell_mismatch');
            }
            var nextMain = doc.querySelector('#rateb-main-content, main.rateb-content');
            var curMain = document.querySelector('#rateb-main-content, main.rateb-content');
            if (!nextMain || !curMain) {
                throw new Error('missing_main');
            }
            curMain.innerHTML = nextMain.innerHTML;
```

**No alternate code path replaces `#rateb-main-content` before `fetchHtml` settles.**

---

## Note on `afterEnter`

- **Cache HIT:** `afterEnter` runs right after DOM replace (scripts deferred).  
- **Cache MISS:** `afterEnter` waits on **`await loadNewScripts(doc)`** (line 610) — that await is **after** DOM replace, so it does **not** keep the old page visible; it only delays `afterEnter` hooks.

---

## Bottom line

| Question | Evidence |
|----------|----------|
| What prevents DOM replacement? | **`await fetchHtml(href)` at `swapTo` line 561** |
| Is delay intentional? | **Yes** — `innerHTML` is in `fetchHtml(...).then(...)` |
| Last await before old page gone? | **`Response.text()`** (L543 miss / L529 hit) |
| Dominant time on first visit? | Leaf **`await fetch`** (~140–150 ms) inside that gate |

No production code was modified.
