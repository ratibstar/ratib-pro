# Phase 8 — Remaining Findings

1. **Operator Chromium gate:** Confirm Module SDK Self-test PASS on `/rateb-erp/public/v2/` in a secure context.

2. **Router API publication:** Phase 8 publishes existing internal `registerRoute` plus `unregisterRoute` on the Router public API so L5 can register routes without redesigning Router architecture.

3. **Harness only:** `sdk.fixture` / `sdk.faulty` are SDK self-test fixtures, not business ERP modules (Phase 9).

4. **Signature:** Default verifier skips when `signature` is null; hooks accept custom verifiers and HCI SHA-256 when a value is present.

5. **SW cache:** Bumped to `rateb-offline-v2-host-p8` — one reload may be required for operators with an older SW.

No Category B architecture violations.
