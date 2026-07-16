# P8-10 — Phase 8 Enterprise Complete

**Layer:** L5 Module SDK  
**API:** `RatebOfflineV2Modules` `1.0.0-phase8`  
**Manifest schema:** `rateb-offline-v2-module/1`

## Delivered

| Capability | Evidence |
|------------|----------|
| Manifest format | `modules/module-manifest.example.json` |
| Lifecycle | install…dispose + `load`/`unload` |
| DI | `factory(ctx)` context |
| Services | `ctx.registerService` |
| Events | `module:*` + module emits |
| Routes | Router `registerRoute` / `unregisterRoute` |
| Contributions | nav/ui registries |
| Permissions / capabilities | manifest + `hasCapability` |
| Compat / signature hooks | `checkCompat` / `setSignatureVerifier` |
| Hot load/unload | self-test |
| Fault isolation | `sdk.faulty` fixture |
| Self-test | `runSelfTest()` |

## Operator gate

Open `/rateb-erp/public/v2/`. Confirm **Module SDK Self-test = PASS**.

## Phase boundary

Do **not** start Phase 9 (Business Modules) until Architecture Board approves.

**Phase 8 Enterprise Complete:** PASS (implementation).  
**Phase Gate:** GO — STOP (no Phase 9).
