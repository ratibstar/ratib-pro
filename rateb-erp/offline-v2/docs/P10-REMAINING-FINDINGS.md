# Phase 10 — Remaining Findings

1. **Operator Chromium gate** required for production sign-off of Identity self-test.
2. **Live online enroll** requires an existing Online ERP browser session (`credentials: include`); the module never logs in.
3. **PIN unlock** is local-only unlock metadata; UX polish for Shell unlock chrome is future work.
4. **SW cache** bumped to `rateb-offline-v2-host-p10`.

No Category B architecture violations.
