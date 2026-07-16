# P15-00 — Phase 15 CRM Charter

**Status:** ACTIVE (implementation)  
**Architecture Freeze:** AF 2.1 + AF 2.1.1 ACTIVE  
**Module:** `crm` (`RatebOfflineV2Crm` `1.0.0-phase15`)

## Mandate

Implement Offline V2 **CRM BusinessModule** only.

## Dependencies

- **Mandatory:** `identity >= 1.0.0`
- **Optional:** `sales`, `accounting` (published APIs only; never ownership)

## Owns

Leads · Accounts · Contacts · Opportunities · Pipeline/Stages · Activities · Tasks · Meetings · Campaigns · Timeline · Notes · Assignments · CRM settings/diagnostics

## Must NOT

- Modify Platform / existing BusinessModules / Offline V1
- Own inventory, accounting GL, procurement, or sales documents
- Direct SQLite to other modules
- Store credentials
- Copy PHP / V1 / CMS / Contact Center

## Phase boundary

STOP after CRM Enterprise Complete.
