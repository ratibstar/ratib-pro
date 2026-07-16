# Phase 6 — Remaining Findings

1. **Operator Chromium gate:** Production Enterprise sign-off still requires opening `/rateb-erp/public/v2/` in a secure context and confirming **UI Shell Self-test = PASS** (same pattern as Phases 1–5).

2. **Diagnostics co-exist with shell:** Host page keeps Phase 1–5 diagnostic panels below `#rateb-v2-shell-root`. Shell self-test mounts then disposes; production UX for a pure shell-first chrome is deferred (not required for L6 gate).

3. **SW cache bump:** Cache id moved to `rateb-offline-v2-host-p6` so precache picks up `shell.js` / `shell.css`. Operators with an old SW need one reload after update.

4. **Theme persistence:** Uses `localStorage` key `rateb_v2_theme` (host preference only — not ERP SoT).

No Category B architecture violations.
