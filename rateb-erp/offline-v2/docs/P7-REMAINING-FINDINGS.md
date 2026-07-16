# Phase 7 — Remaining Findings

1. **Operator Chromium gate:** Confirm Sync Engine Self-test PASS on `/rateb-erp/public/v2/` in a secure context.

2. **Transport:** Default `local-loop` transport exercises the full pipeline without cloud. Production HTTP/cloud transport is intentionally out of Phase 7 scope (pluggable `setTransport`).

3. **Schema bump:** Target schema version is **2**. Existing OPFS DBs migrate on next `open()`. Operators may need one host reload after SW cache `rateb-offline-v2-host-p7`.

4. **Online override:** Self-tests use `setOnlineOverride` to force offline/online without toggling the OS network. Production uses HCI reachability + browser events.

No Category B architecture violations.
