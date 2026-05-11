# Infrastructure Marketplace Progress Report

## Scope Completed

This work delivered an isolated Infrastructure Marketplace module and integrated it into public pages and control-panel surfaces, then hardened runtime behavior for production safety and cache-related issues.

The implementation remains module-scoped under `modules/infrastructure-marketplace` and API endpoints under `api/infrastructure-marketplace`, with controlled integration into control-panel navigation.

---

## 1) Public Site Work Completed

### A. Public nav exposure on homepage
- Added runtime fallback to inject missing nav tabs when stale/cached HTML omits them:
  - `Marketplace`
  - `Infra Status`
- Implemented in:
  - `js/pages/home-page.js`

### B. Inline open behavior (same page, under nav)
- Changed behavior from full-page navigation to inline panel rendering under top nav.
- Added toggle/open/close logic.
- Added embedded panel/iframe UX for both tabs.
- Implemented in:
  - `js/pages/home-page.js`
  - `css/pages/home-public.css`

### C. Cache-busting + no-cache hardening for embedded views
- Added filemtime-based script query params in module views.
- Added no-cache headers for embedded module pages.
- Added runtime timestamp query (`_rt`) on iframe URLs.
- Implemented in:
  - `modules/infrastructure-marketplace/Views/client/services.php`
  - `modules/infrastructure-marketplace/Views/marketplace/index.php`
  - `js/pages/home-page.js`

### D. Client services UI improvements
- Replaced raw JSON output with structured operational cards.
- Removed duplicated nested `Active Services` rendering issue.
- Kept graceful fallback path:
  - `dashboard.php` -> `health.php` -> unavailable card.
- Implemented in:
  - `modules/infrastructure-marketplace/Assets/js/client-services.js`
  - `modules/infrastructure-marketplace/Views/client/services.php`
  - `modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace-exposure.css`

---

## 2) API Stability + Runtime Hardening Completed

### A. `dashboard.php` crash resistance
- Added fatal-shutdown JSON fallback so endpoint avoids fatal 500 breaks.
- Added safe diagnostics payload in failure modes.
- Implemented in:
  - `api/infrastructure-marketplace/dashboard.php`

### B. Partial-failure tolerant metrics response
- Refactored dashboard data collection to avoid all-or-nothing failures.
- Added defaults for queue/jobs/failed/providers fields.
- Isolated query blocks with individual `try/catch` to preserve partial output.
- Added `db_reachable` diagnostic state.
- Implemented in:
  - `api/infrastructure-marketplace/dashboard.php`

### C. `catalog.php` resilience
- Hardened to return safe response shape instead of propagating 500 for frontend breakage.
- Implemented in:
  - `api/infrastructure-marketplace/catalog.php`

---

## 3) Control Panel Integration Completed

### A. Sidebar integration (live sidebar patched)
- Added Infrastructure entries under Administration:
  - `Infrastructure Control`
  - `Infrastructure Dashboard`
  - `Infrastructure Providers`
- Implemented in:
  - `control-panel/includes/control/sidebar.php`

### B. Open in same control-panel layout (right content pane)
- Added control-panel wrapper page that preserves header/sidebar and loads infra pages in right pane.
- Added wrapper CSS.
- Sidebar links now route to wrapper with `view` switching.
- Implemented in:
  - `control-panel/pages/control/infrastructure.php` (new)
  - `control-panel/css/control/infrastructure-embed.css` (new)
  - `control-panel/includes/control/sidebar.php` (updated links)

### C. Infrastructure Control Center page
- Added dedicated module admin page with:
  - runtime controls visibility
  - rollout visibility
  - provider toggle snapshot
  - operator shortcuts
- Implemented in:
  - `modules/infrastructure-marketplace/Views/admin/control.php` (new)

---

## 4) Runtime Control System Added

### A. Live control update endpoint
- Added POST API for runtime control updates:
  - `/api/infrastructure-marketplace/control-update.php`
- Writes to:
  - `modules/infrastructure-marketplace/Config/runtime-overrides.json`
- Supports updates for:
  - `enabled`
  - `dry_run`
  - `execution_kill_switch`
  - `queue_driver`
  - `queue_max_attempts`
  - `queue_pressure_threshold`
  - `worker_max_loop_jobs`
  - `default_currency`
  - `tenant_allowlist`
- Implemented in:
  - `api/infrastructure-marketplace/control-update.php` (new)

### B. `ModuleConfig` runtime override support
- `ModuleConfig` now reads runtime override JSON first, then env fallback.
- Preserved backward compatibility with existing env-based flow.
- Implemented in:
  - `modules/infrastructure-marketplace/Config/ModuleConfig.php`

### C. Control Center form wiring
- Added runtime control form in control center page posting to control-update API.
- Implemented in:
  - `modules/infrastructure-marketplace/Views/admin/control.php`

---

## 5) Registry + Navigation Metadata Updates

- Added control center route and nav hint entries.
- Added control-update API route metadata.
- Updated sidebar integration snippet for manual merge scenarios.
- Implemented in:
  - `modules/infrastructure-marketplace/Config/ModuleRegistry.php`
  - `modules/infrastructure-marketplace/integrations/control-panel-sidebar-snippet.php`

---

## 6) Files Added

- `modules/infrastructure-marketplace/Views/admin/control.php`
- `api/infrastructure-marketplace/control-update.php`
- `control-panel/pages/control/infrastructure.php`
- `control-panel/css/control/infrastructure-embed.css`
- `docs/infrastructure-marketplace-progress.md`

---

## 7) Key Files Updated

- `api/infrastructure-marketplace/dashboard.php`
- `api/infrastructure-marketplace/catalog.php`
- `modules/infrastructure-marketplace/Assets/js/client-services.js`
- `modules/infrastructure-marketplace/Views/client/services.php`
- `modules/infrastructure-marketplace/Views/marketplace/index.php`
- `modules/infrastructure-marketplace/Assets/css/infrastructure-marketplace-exposure.css`
- `modules/infrastructure-marketplace/Config/ModuleConfig.php`
- `modules/infrastructure-marketplace/Config/ModuleRegistry.php`
- `modules/infrastructure-marketplace/integrations/control-panel-sidebar-snippet.php`
- `js/pages/home-page.js`
- `css/pages/home-public.css`
- `control-panel/includes/control/sidebar.php`

---

## 8) Current Operational State

- Public users can open Infrastructure tabs inline on homepage.
- Inline content is more resilient against stale cache.
- Infra status page renders structured cards and fallback data.
- Control panel includes Infrastructure entries and loads infra screens in right pane.
- Admins can update runtime controls using Control Center form and runtime overrides file.

---

## 9) Known Constraints

- Runtime control writes require filesystem write access for:
  - `modules/infrastructure-marketplace/Config/runtime-overrides.json`
- If web server cannot write there, `control-update.php` returns an error JSON.
- Some values may still show fallback (`configured` / `unavailable`) when provider/table-specific data is not present in production DB.
- Sidebar visibility still depends on permission filtering via `data-permission`.

---

## 10) Recommended Next Steps

- Add in-page success/error banner for runtime control save (no new tab).
- Add CSRF token + stricter authorization gate to `control-update.php`.
- Add audit logging for runtime control changes (actor, timestamp, diff).
- Add "last applied override" timestamp and source indicator in Control Center.
- Add per-field validation feedback in control UI.

