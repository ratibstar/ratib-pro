# P16-00 — Phase 16 HR Charter

**Status:** ACTIVE (implementation)  
**Architecture Freeze:** AF 2.1 + AF 2.1.1 ACTIVE  
**Module:** `hr` (`RatebOfflineV2Hr` `1.0.0-phase16`)

## Mandate

Implement Offline V2 **HR BusinessModule** only.

## Dependencies

- **Mandatory:** `identity >= 1.0.0`
- **Optional:** `accounting`, `crm` (published APIs only; never ownership)

## Owns

Employees · Departments · Positions · Org units/locations · Attendance · Leave · Overtime drafts · Contracts · Recruitment drafts · Onboarding · Performance · Training · Document meta · Timeline

## Must NOT

- Modify Platform / existing BusinessModules / Offline V1
- Own authentication, inventory, accounting GL, procurement, sales, CRM stores
- Direct SQLite to other modules
- Store credentials or binary documents
- Copy PHP / V1 / online Recruitment monolith

## Phase boundary

STOP after HR Enterprise Complete.
