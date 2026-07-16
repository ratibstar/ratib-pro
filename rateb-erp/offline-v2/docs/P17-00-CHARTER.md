# P17-00 — Phase 17 Manufacturing Charter

**Status:** ACTIVE (implementation)  
**Architecture Freeze:** AF 2.1 + AF 2.1.1 ACTIVE  
**Module:** `mfg` (`RatebOfflineV2Mfg` `1.0.0-phase17`)

## Mandate

Implement Offline V2 **Manufacturing BusinessModule** only.

## Dependencies

- **Mandatory:** `identity >= 1.0.0`, `inventory >= 1.0.0`
- **Optional:** `procurement`, `sales`, `accounting` (published APIs only; never ownership)

## Owns

Product · BOM · Routing · Work centers · Production orders · Work orders · Material reservation/consumption **meta** · FG receipt **meta** · Capacity · Quality · Cost **meta** · Timeline

## Must NOT

- Modify Platform / existing BusinessModules / Offline V1
- Own authentication, inventory balances, accounting GL, procurement, sales, CRM, HR
- Direct SQLite to other modules / OPFS outside HCI
- Implement MRP explode/net engine (deferred; not present as Online SoT engine)
- Post stock except via `module.inventory.*`

## Phase boundary

STOP after Manufacturing Enterprise Complete.
