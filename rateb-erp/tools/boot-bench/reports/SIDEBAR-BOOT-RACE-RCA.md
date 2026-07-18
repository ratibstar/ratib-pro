# FINAL RACE CONDITION RCA — Sidebar Clickable Before bootAppUi

**Date:** 2026-07-18T15:49:12.070Z
**Question:** Why can users interact with the sidebar before `bootAppUi()` completes?

## Measured timeline (ms from probe start ≈ navigation)

| Mark | t (ms) |
|------|--------|
| `probe_init` | 0 |
| `sidebar_dom` | 260.3 |
| `toggle_dom` | 260.3 |
| `sidebar_visible` | 260.3 |
| `sidebar_clickable_hit_test` | 260.3 |
| `app_js_script_inserted` | 325.4 |
| `first_toggle_addEventListener` | 488 |
| `bootAppUi_flag_set` | 488.2 |
| `app_js_load_event` | 488.8 |
| `poll_stop_booted_and_listeners` | 495.2 |
| `paint:first-paint` | 499.2 |
| `paint:first-contentful-paint` | 499.2 |
| `poll_stop_booted_and_listeners` | 561.1 |
| `poll_stop_booted_and_listeners` | 561.1 |
| `poll_stop_booted_and_listeners` | 561.1 |
| `poll_stop_booted_and_listeners` | 561.2 |
| `poll_stop_booted_and_listeners` | 561.3 |
| `poll_stop_booted_and_listeners` | 561.3 |
| `poll_stop_booted_and_listeners` | 561.4 |
| `poll_stop_booted_and_listeners` | 561.4 |
| `poll_stop_booted_and_listeners` | 561.4 |
| `poll_stop_booted_and_listeners` | 561.4 |
| `poll_stop_booted_and_listeners` | 561.5 |
| `poll_stop_booted_and_listeners` | 561.6 |
| `poll_stop_booted_and_listeners` | 561.6 |
| `poll_stop_booted_and_listeners` | 561.6 |

## Race gap

| Milestone | t (ms) |
|-----------|--------|
| Sidebar clickable (hit-test) | 260.3 |
| First paint (FCP mark) | 499.2 |
| DOMContentLoaded | null |
| app.js inserted | 325.4 |
| app.js load | 488.8 |
| bootAppUi flag | 488.2 |
| first toggle addEventListener | 488 |
| **Race window (clickable → boot)** | **227.9** |

## Code that exposes sidebar before listeners

```
{
  "sidebarEmit": "views/layouts/main.php ~L432-438 <aside id=\"rateb-sidebar\"> + require sidebar-nav.php",
  "toggleEmit": "views/partials/sidebar-nav.php L45-46 button.rateb-nav-group-toggle[data-nav-group-toggle]",
  "cssVisible": "public/assets/css/critical-shell.css L25-32 .rateb-sidebar fixed; toggles cursor:pointer; no pointer-events:none",
  "jsDeferred": "views/layouts/main.php L709-717 loadCritical on DOMContentLoaded; critical includes app.js",
  "boot": "public/assets/js/app.js bootAppUi L312-348 → initSidebarNavGroups L162-176"
}
```

## Answers

### 1. At what timestamp does the sidebar become clickable by the user?

Sidebar toggles become user-clickable (visible + pointer-events + hit-test) at t≈**260.3 ms** after navigation start (probe). Marks: `sidebar_dom` @ 260.3 ms, `sidebar_visible` @ 260.3 ms, `sidebar_clickable_hit_test` @ 260.3 ms. First paint: `paint:first-contentful-paint` @ 499.2 ms (startTime 1084).

### 2. At what timestamp does bootAppUi() actually finish?

`bootAppUi()` finishes (flag set + first toggle `addEventListener`) at t≈**488.2 ms** (`bootAppUi_flag_set` @ 488.2 ms, `first_toggle_addEventListener` @ 488 ms). `app.js` inserted @ 325.4 ms, load @ 488.8 ms. Gap clickable→boot ≈ **227.9 ms**.

### 3. Why is the sidebar visible before initialization?

The sidebar is server-rendered HTML inside `main.php` (`#rateb-sidebar` + `sidebar-nav.php`) and styled by `critical-shell.css` as a normal fixed aside with `cursor:pointer` on toggles. There is **no** `pointer-events:none`, `disabled`, `inert`, or `aria-busy` gate tied to `data-rateb-app-ui-booted`. Visibility is CSS-default as soon as the parser inserts the nodes and critical CSS applies — independent of `app.js`.

### 4. Which code exposes the sidebar before listeners exist?

Exposing code: (1) `rateb-erp/views/layouts/main.php` ~L432–438 emits `<aside id="rateb-sidebar">` and requires `sidebar-nav.php` with live `<button data-nav-group-toggle>`. (2) `rateb-erp/public/assets/css/critical-shell.css` L25–32 paints `.rateb-sidebar` fixed/visible and `.rateb-nav-group-toggle{cursor:pointer}` with **no** pre-boot disable. (3) `main.php` L709–717 schedules `loadCritical()` only on `DOMContentLoaded`, so `app.js` → `bootAppUi()` → `initSidebarNavGroups()` runs **after** the sidebar has already been visible/clickable.

### 5. Should the sidebar remain disabled until boot completes?

From a correctness standpoint: **yes**, if the product requires the first click to always toggle, the sidebar (or at least `[data-nav-group-toggle]`) should not accept pointer input until `bootAppUi()`/`initSidebarNavGroups()` has bound listeners — or listeners must be bound earlier (inline/sync). Evidence: any click in the measured gap is a guaranteed no-op.

### 6. Would delaying pointer-events or visibility until boot eliminate the race?

**Yes.** Holding `pointer-events: none` (or `visibility`/`inert`) on `#rateb-sidebar` until `data-rateb-app-ui-booted="1"` would make the first user click impossible until listeners exist, eliminating this race. (Evidence-only conclusion — not implemented.)

No production code was modified.
