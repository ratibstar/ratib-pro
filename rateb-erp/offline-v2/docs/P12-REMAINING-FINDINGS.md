# P12 — Remaining findings

## Non-blocking (out of Phase 12 scope)

1. **Runtime service locator factory semantics** — registering a function registers it as a zero-arg factory. SDK/BM self-tests expect callable services (`ping`/`echo`). Locked under AF; Procurement invokes Inventory methods via active module instance after verifying `module.inventory.*` service keys are published.

2. **Router manifest relative URL** — `../routes/` from `js/router/router.js` resolves to `js/routes/`. Host ships a mirrored `js/routes/route-manifest.json`. Router source unmodified.

3. **Sync self-test `schema_v2`** — early assertion fails while later Sync steps pass. Pre-existing; Sync Engine locked.

4. **No 3-way match** — `invoiced_qty` unused (matches audited online lean scope). Documented; not implemented as false capability.

## Blocking

None for Procurement Enterprise Complete.
