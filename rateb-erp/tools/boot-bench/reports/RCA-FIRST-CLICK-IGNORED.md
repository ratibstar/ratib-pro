# RCA: First sidebar/navbar click ignored (evidence only)

**Status:** Investigation only — no fix applied.  
**Measured:** 2026-07-18 (Playwright RCA + source)  
**Evidence JSON:** `rateb-erp/tools/boot-bench/reports/rca-first-click-1784379893158.json`

---

## Symptom → Explained

| Observation | Mechanism |
|-------------|-----------|
| First click on a **module group** often does nothing | Group toggle JS never binds; `type="button"` has no default action |
| Multiple clicks needed | User retries dead toggles; or double-clicks during `navigating` lock |
| First successful navigation feels slow | Cold Cache API miss → network `fetchHtml` (up to 2500 ms) with UI swap only at end |
| Later navigations fast | Cache API hit (`fromCache: true`) → swap in tens of ms |

---

## Single root cause

**PERF-P3 loads `app.js` after `DOMContentLoaded`, but `app.js` only initializes sidebar toggles inside a `DOMContentLoaded` listener. That listener never fires, so `initSidebarNavGroups()` never runs.**

Collapsed module links stay inside `<template data-rateb-nav-lazy>` and are not in the live DOM. Toggle buttons do not toggle `.is-open` and do not hydrate templates. First clicks on module groups are no-ops.

### Exact location

**1. Script load after DCL (introduced PERF-P3)**  
File: `rateb-erp/views/layouts/main.php`  
Lines: **696–718**

```696:718:rateb-erp/views/layouts/main.php
  /* PERF-P3: critical JS AFTER DOMContentLoaded so DCL is not blocked by defer scripts. */
  var critical = ... // theme.js → app.js → erp-nav-instant.js
  function loadCritical() {
    chain(critical, 0, function () { ... });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadCritical, { once: true });
  } else {
    loadCritical();
  }
```

Critical chain order (lines **634–637**): `theme.js` → `app.js` → `erp-nav-instant.js` — all start **after** DCL.

**2. Init only on DOMContentLoaded (missed forever when late-loaded)**  
File: `rateb-erp/public/assets/js/app.js`  
Lines: **312–343**

```312:343:rateb-erp/public/assets/js/app.js
    document.addEventListener('DOMContentLoaded', function () {
        ...
        initSidebarNavGroups();  // NEVER reached when app.js loads post-DCL
        ...
    });
```

Contrast: `erp-nav-instant.js` **does** handle late load correctly:

```720:724:rateb-erp/public/assets/js/erp-nav-instant.js
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
```

`app.js` has **no** equivalent `else` / `readyState !== 'loading'` path for sidebar init.

**3. Lazy sidebar markup depends on that init**  
File: `rateb-erp/views/partials/sidebar-nav.php`  
Lines: **57–66** (closed groups) — body is only:

```html
<template data-rateb-nav-lazy><!-- module links --></template>
```

Hydration only in `hydrateNavLazy()` / toggle handler — `app.js` **132–177**.

---

## Playwright evidence (live production)

### A. Sidebar visible while nav JS still absent

At ~989 ms after navigation start (sidebar already queryable):

| Field | Value |
|-------|-------|
| `navInstant` | **false** |
| `criticalReadyFired` | **false** |
| `visibleLinks` in live DOM | **5** (dashboard, catalog, notifications, profile, executive only) |
| `lazyTpl` | **12** (entire module tree still in templates) |
| `toggles` | **12** |

### B. Toggle click is a no-op (direct probe)

```text
toggleProbe:
  beforeOpen: false
  afterOpen: false          ← click did not add .is-open
  lazyBefore: true
  lazyAfter: true           ← template not hydrated
  linksAfterClick: 0        ← still zero live module links in group
```

DCL marked at **969.6 ms**; `RatebApp.reinit` exists (script loaded) but sidebar init never ran.

### C. Concurrent-nav mutex (secondary amplifier)

File: `erp-nav-instant.js`  
- `navigating` lock: lines **19**, **554–557**, **616–620**  
- Click path always `preventDefault` then `swapTo`: lines **660–674**

Probe: `navigate(href)` twice in parallel → `firstOk: true`, **`secondOk: false`**.

So once the interceptor is active, a second click during an in-flight cold swap is **silently discarded** (default prevented, no navigation). That matches “needs multiple clicks” on **visible** top-level links during a slow first fetch.

Cold path: `fetchHtml` → Cache miss → network (lines **509–548**), timeout **2500 ms** (`fetchWithTimeout`).  
Warm path: `fromCache: true` → fast `afterEnter` — explains “later navigations are fast.”

---

## Full lifecycle (first module-group click today)

| # | Stage | Timestamp (typ.) | Duration | Caller | Blocking / outcome |
|---|--------|------------------|----------|--------|---------------------|
| 1 | HTML parse / DCL | ~0–970 ms | — | Browser | Critical JS **not** loaded yet |
| 2 | `loadCritical()` starts | @ DCL | — | `main.php` inline | Chains theme → app → nav |
| 3 | `app.js` executes | after DCL | — | inject | Registers `DOMContentLoaded` listener **too late** |
| 4 | `initSidebarNavGroups` | **never** | — | — | **Root failure** |
| 5 | User click group toggle | user | 0 ms | `<button type="button">` | No handler → **no-op** |
| 6 | Template hydrate | — | — | `hydrateNavLazy` | Never called |
| 7 | Module `<a>` not in DOM | — | — | — | Cannot navigate that module |
| 8 | (If visible link + interceptor active) click | user | — | `onClick` capture | `preventDefault` |
| 9 | `swapTo` | sync | — | `erp-nav-instant.js` | Sets `navigating=true` |
| 10 | `fetchHtml` cold | — | 100–2500 ms | `fetch` / Cache API | UI unchanged until done |
| 11 | Extra clicks while `navigating` | user | 0 | `swapTo` | **Silent drop** (`return false`) |
| 12 | Warm later click | user | ~15–60 ms | cache hit | Fast swap |

---

## Ruled out (for this symptom)

| Area | Finding |
|------|---------|
| Offline nav-guard `preventDefault` | Only when `navigator.onLine === false` |
| Overlay / pointer-events | Not required; toggle simply unbound |
| RBAC / tenant / identity | Not on click path for shell links |
| IndexedDB | Not opened on Admin click path |
| Service Worker | Affects cache speed, not first-click no-op on toggles |
| `stopPropagation` on logout/offline same-URL | Unrelated to module groups |

---

## Why later navigations are fast (once a page swap works)

1. HTML stored in Cache API (`putHtmlLocally` / SW `CACHE_ERP_OPS_PAGE`).  
2. Next `swapTo` → `fromCache: true` → skip blocking network.  
3. `navigating` held only for a short interval → fewer swallowed clicks.

This does **not** fix dead group toggles; it only explains speed after a successful intercepting navigation on a **live** `<a>`.

---

## Call stack (dead toggle)

```
User click
  → <button data-nav-group-toggle type="button">
  → (no listener — initSidebarNavGroups never ran)
  → default action: none
  → STOP
```

## Call stack (swallowed second click on live link)

```
User click #1
  → document capture (erp-nav-instant onClick) L660
  → preventDefault L673
  → swapTo L674
  → navigating=true L557
  → fetchHtml (cold network) L509+
User click #2 (while fetch in flight)
  → onClick → preventDefault
  → swapTo L554 if (navigating) return false   ← ignored
  → STOP (no URL change)
```

---

## Verdict

**One root cause:** post-DCL loading of `app.js` without a late-load init path leaves `initSidebarNavGroups()` unexecuted, so PERF-P3 lazy sidebar groups never open/hydrate and first module clicks do nothing.

**Secondary amplifier (visible links only):** `navigating` mutex + `preventDefault` swallows retries during the slow first cold `fetchHtml`.
