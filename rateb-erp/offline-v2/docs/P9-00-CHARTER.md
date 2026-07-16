# Phase 9 — Business Module Framework Charter

**Status:** Binding  
**Depends on:** Phase 1–8 APPROVED · L5 Module SDK published APIs

## In scope

- Business Module base class
- Module metadata model (`rateb-offline-v2-business-module/1`)
- Registration / activation / discovery
- Dependency validation
- Health monitoring + diagnostics interface
- Permissions via Module SDK
- Dynamic loading through Package Manager (`modules` type)
- Service exposure + event subscriptions
- Navigation / workspace / settings contribution registration
- **One** ReferenceModule (architecture proof only)

## Forbidden

- Sales, HR, Inventory, Accounting, CRM, POS, Procurement, Projects, Manufacturing, or any ERP module
- ERP business logic inside ReferenceModule
- Modifying HCI / Runtime / PM / SQLite / Router / Shell / Sync / Module SDK architectures
- Offline V1 changes, PHP fetch, DOMParser, document reload, IndexedDB/Cache ERP storage
