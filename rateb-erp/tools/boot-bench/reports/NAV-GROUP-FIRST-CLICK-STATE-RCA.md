# NAV GROUP FIRST-CLICK STATE RCA (Evidence Only)

**Date:** 2026-07-18T15:34:00Z  
**Scope:** sidebar group state / listeners only — no network, no `fetchHtml`, no metrics.  
**Harness:** `rateb-erp/tools/boot-bench/nav-group-first-click-state-rca.js`  
**Raw:** `reports/NAV-GROUP-FIRST-CLICK-STATE-RCA-*.json`

## Protocol

1. Hard load Admin dashboard  
2. First click collapsed group **A** (before `bootAppUi`)  
3. After `data-rateb-app-ui-booted=1`, click group **B**  
4. Click original group **A** again  
5. Control: load → wait for boot → first click (must succeed)

> Note: scenario 1 delayed `app.js` by 2.5s only to widen the existing DCL→boot gap for a clean trace. The gap itself is proven without that delay: at `env_early`, toggles exist with `booted=null` and `btnListenerHint=null`.

## State diff (A failed → B/A success)

| Field | First click A (fail) | Second click B (ok) | Third click A (ok) | Control first click (ok) |
|-------|----------------------|---------------------|--------------------|---------------------------|
| Click dispatched on toggle | yes (`pre_dispatch`) | yes | yes | yes |
| `data-rateb-app-ui-booted` | **null** | **`"1"`** | **`"1"`** | `"1"` |
| Toggle `click` listener count | **null** | **`"1"`** | **`"1"`** | `"1"` |
| Toggle handler executed | **no** | **yes** | **yes** | yes |
| Early return in handler | n/a (no handler) | no | no | no |
| `stopPropagation` | no | no | no | no |
| `preventDefault` on click | no | no | no | no |
| `.is-open` after | **false** | **true** | **true** | true |
| `aria-expanded` | `"false"` | `"true"` | `"true"` | `"true"` |
| Lazy `<template>` cloned | **no** | **yes** | yes | (open path ran) |
| `hydrateNavLazy` effect | no | yes (tpl removed + prefetch binds) | yes | — |

### First-click detail (failed)

```json
{
  "pre_dispatch": {
    "booted": null,
    "listenerCount": null,
    "isOpen": false
  },
  "toggle_handler_enter": false,
  "isOpenAfter": false,
  "hasTplAfter": true
}
```

### Second-click detail (group B — success)

```json
{
  "booted": "1",
  "listenerCount": "1",
  "toggle_handler": {
    "before": { "isOpen": false, "hasTpl": true },
    "after": { "isOpen": true, "hasTpl": false },
    "changedOpen": true,
    "tplCloned": true
  }
}
```

Listeners that **did** run on success: `initSidebarNavGroups` click handler → `hydrateNavLazy` → `classList.toggle('is-open')` → prefetch `addEventListener`s on newly cloned links.  
Listeners that **did not** run on first click: that same toggle handler (it was not registered yet).

---

## Answers

### 1. What changed between the failed first click and the successful second click?

Shared sidebar UI init completed. `document.documentElement` gained `data-rateb-app-ui-booted="1"`, and every `[data-nav-group-toggle]` received exactly one `click` listener (`data-rca-listener-count` null → `"1"`). Group B did not “unlock” group A; both started working because the **same** init finished. After that, open toggles `.is-open`, sets `aria-expanded`, and clones `template[data-rateb-nav-lazy]`.

### 2. Which variable / flag / object / listener changed?

- **Flag:** `data-rateb-app-ui-booted` on `<html>` (`null` → `"1"`).  
- **Listeners:** per-button `click` handlers from `initSidebarNavGroups()` (absent → present, count `1`).  
- **DOM state on success:** `.is-open`, `aria-expanded`, removal of `template[data-rateb-nav-lazy]` via hydrate.

### 3. Which exact line of code performs that change?

In `rateb-erp/public/assets/js/app.js`:

```312:348:rateb-erp/public/assets/js/app.js
    function bootAppUi() {
        if (document.documentElement.getAttribute('data-rateb-app-ui-booted') === '1') {
            return;
        }
        document.documentElement.setAttribute('data-rateb-app-ui-booted', '1');
        // ...
        initSidebarNavGroups();
```

```162:176:rateb-erp/public/assets/js/app.js
    function initSidebarNavGroups() {
        document.querySelectorAll('[data-nav-group-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var group = btn.closest('[data-nav-group]');
                if (!group) {
                    return;
                }
                var willOpen = !group.classList.contains('is-open');
                if (willOpen) {
                    hydrateNavLazy(group);
                }
                var open = group.classList.toggle('is-open');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        });
    }
```

Template clone: `hydrateNavLazy()` lines **132–154** (`body.appendChild(tpl.content.cloneNode(true)); tpl.remove();`).

### 4. Why is that initialization NOT happening before the first user interaction?

`initSidebarNavGroups()` runs only inside `bootAppUi()`, which runs only when `app.js` executes. PERF-P3 injects `app.js` **after** `DOMContentLoaded` via the critical chain in `views/layouts/main.php` (`loadCritical` → theme → **app.js** → erp-nav → …). Sidebar toggles are already in the HTML and look clickable before that script runs. A first click in that window has **zero** toggle listeners, so nothing sets `.is-open` / hydrates the lazy template. Later clicks (any group) succeed once `bootAppUi` has run — matching “second group works, then the original works too.”

**Control:** after waiting for `data-rateb-app-ui-booted=1`, the first group click opens immediately (`listenerCount=1`, handler runs). No separate per-group unlock flag exists.

No production code was modified.
