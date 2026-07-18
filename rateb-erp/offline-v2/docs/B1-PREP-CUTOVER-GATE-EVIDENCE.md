# B1 Preparation — Cutover Gate Elimination (Evidence Pack)

**Status:** Prep infrastructure only. Track B Admin cutover is **NOT** started.  
**Authority:** ADR-B0 + ADR-AL-1/AL-2 + AF-2.1 + OFAT.

## Scope delivered

| Gate | Deliverable path |
|------|------------------|
| G1 Admin Cutover PX | `tools/boot-bench/phase-b1-prep-cutover-gates.js` + `platform/cutover/prep-bootstrap.js` + harness |
| G2 CompatGate | `platform/cutover/compat-gate.js` |
| G3 EmergencyRollback | `platform/cutover/emergency-rollback.js` + `feature-flags.js` + `public/platform-cutover-flags.php` |
| G4 Queue Strategy | `platform/cutover/queue-strategy.js` (drain-first **or** idempotent mapper) |
| G5 Identity Bridge | `platform/cutover/identity-bridge.js` |
| G6 Multi-tab lease | `platform/cutover/multi-tab-lease.js` |
| G7 POS isolation | preserved (harness loads no POS assets; PX asserts) |
| G8 Capability probe | `platform/cutover/capability-probe.js` |
| G9 Rollback drill | `tools/boot-bench/phase-b1-prep-rollback-drill.js` + harness G9 |

## Explicit non-goals (still NO-GO for Track B start until authorization)

- Admin page migration to Platform Runtime
- Dual Runtime/Queue/Identity/SQLite in production Admin boot
- POS / Offline V1 production path changes
- Credential transfer / AF-2.1 violation

## PX profile coverage (G1)

Boot → FLAGS → CAPABILITY → LEASE → COMPATGATE → VALIDATION → PLATFORM_BOOTSTRAP (prep skip) → ROLLBACK_PATH_READY → FAILURE_RECOVERY_READY; EmergencyRollback + Recovery when kill armed.

## Kill switch (no deploy)

1. Write `rateb-erp/storage/platform-cutover-flags.json` (see `platform-cutover-flags.example.json`) **or** set `RATEB_PLATFORM_CUTOVER_FLAGS`
2. Endpoint: `/rateb-erp/public/platform-cutover-flags.php`
3. Client sticky localStorage key `rateb_platform_emergency_rollback_sticky`

## Evidence commands

```bash
cd rateb-erp/tools/boot-bench
node phase-b1-prep-cutover-gates.js
node phase-b1-prep-rollback-drill.js
```

Reports land in `tools/boot-bench/reports/`.
