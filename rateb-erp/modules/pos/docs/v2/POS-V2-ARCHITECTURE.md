# RATEB POS V2 — Architecture

**Version:** 1.0.0  
**Status:** Pre-implementation architecture (additive to V1)  
**Owner:** RATEB ERP Platform Architecture  
**Compatibility:** V1 POS remains default until `POS_V2_ENABLED` per company.

---

## 1. Purpose

This document defines the **complete domain architecture** for RATEB POS V2 — an isolated, additive layer on top of the existing `rateb-erp/modules/pos` module. V1 controllers, routes, views, and APIs remain untouched and operational.

---

## 2. Architectural Principles

| Principle | Rule |
|-----------|------|
| Additive only | No modification to ERP core modules (`app/`, inventory GL, HR, etc.) |
| Bridge pattern | Cross-domain operations via existing `Pos*BridgeService` classes |
| Isolation | V2 code lives under `V2/` namespaces and `v2/` asset paths |
| Feature flags | Company/terminal/profile flags in `rateb_pos_settings.settings_json` |
| Single write path | Mutations flow: Controller → UseCase → Domain Service → Repository |
| No duplicated pricing | All totals via `PosPricingService` / `PosRegisterCartService` |
| Policy-first auth | Authorization via Policy classes, not controller conditionals |
| Event-after-commit | Domain events dispatched after successful transaction commit |

---

## 3. Bounded Contexts

```
┌─────────────────────────────────────────────────────────────────┐
│                     RATEB POS V2 (Bounded Context)               │
├──────────────┬──────────────┬──────────────┬─────────────────────┤
│   Register   │   Payment    │  Operations  │    Enterprise       │
│   Context    │   Context    │   Context    │    Context          │
├──────────────┼──────────────┼──────────────┼─────────────────────┤
│ Shift Gate   │ Payment Sheet│ Returns      │ Notifications       │
│ Catalog      │ Receipt      │ Exchange     │ Audit Timeline      │
│ Ticket/Cart  │ Terminal     │ Suspend/Quote│ Sync/Conflict       │
│ Customer     │ Tips (R)     │ Shift Close  │ Licensing           │
│ Discounts    │ Split (R)    │ Drawer Events│ Emergency Mode      │
│ Overrides    │              │ Approvals    │ Diagnostics         │
└──────────────┴──────────────┴──────────────┴─────────────────────┘
         │              │              │              │
         └──────────────┴──────────────┴──────────────┘
                              │
                    ┌─────────▼─────────┐
                    │  Integration BC   │
                    │  (Anti-Corruption)│
                    ├───────────────────┤
                    │ Inventory Bridge  │
                    │ Accounting Bridge │
                    │ CRM Bridge        │
                    │ ZATCA Bridge      │
                    │ Hardware SDK      │
                    └───────────────────┘
```

### Context boundaries

| Context | Owns | Must NOT own |
|---------|------|--------------|
| Register | Cart, session, catalog presentation, Charge UX | GL posting, stock movement |
| Payment | Tender collection, terminal state, receipt trigger | Inventory reservation logic |
| Operations | Returns, exchange, suspend, shift close | Customer master data CRUD |
| Enterprise | Audit, sync, licensing, emergency | Sale pricing rules |
| Integration | Bridge calls to ERP services | POS UI state |

---

## 4. Layer Diagram

```
┌──────────────────────────────────────────────────────────────┐
│ Presentation Layer (V2 only)                                  │
│  views/v2/  ·  public/assets/pos/v2/js|css  ·  Components    │
└────────────────────────────┬─────────────────────────────────┘
                             │ data-pos-* hooks · JSON API
┌────────────────────────────▼─────────────────────────────────┐
│ Application Layer                                             │
│  Controllers/V2/*  ·  UseCases/V2/*  ·  DTOs  ·  Policies   │
└────────────────────────────┬─────────────────────────────────┘
                             │
┌────────────────────────────▼─────────────────────────────────┐
│ Domain Layer                                                  │
│  Domain/V2/{Register,Payment,...}  ·  Events  ·  ValueObjects │
└────────────────────────────┬─────────────────────────────────┘
                             │
┌────────────────────────────▼─────────────────────────────────┐
│ Infrastructure Layer                                          │
│  Repositories/V2/*  ·  Hardware/Drivers  ·  Queue/Jobs         │
│  Bridge adapters (wrap existing Pos*BridgeService)             │
└────────────────────────────┬─────────────────────────────────┘
                             │
┌────────────────────────────▼─────────────────────────────────┐
│ Existing V1 + ERP (unchanged)                                 │
│  PosRegisterApiController · PosCheckoutService · Inventory...  │
└──────────────────────────────────────────────────────────────┘
```

### Dependency rules (strict)

1. **Presentation** → Application only (via API + bootstrap JSON).
2. **Application** → Domain + Infrastructure interfaces.
3. **Domain** → no dependencies on Presentation or Infrastructure.
4. **Infrastructure** → Domain interfaces; may call V1 services as adapters.
5. **V2 never imports V1 views**; V2 may delegate to V1 services via adapter.
6. **ERP modules** (`app/Models/Inventory`, etc.) accessed only through Bridge services.

---

## 5. Folder Architecture

```
rateb-erp/modules/pos/
├── app/
│   ├── Controllers/V2/           # HTTP entry points (additive)
│   ├── UseCases/V2/              # One class per use case
│   ├── Domain/V2/                # Entities, VOs, domain services
│   │   ├── Register/
│   │   ├── Payment/
│   │   ├── Approval/
│   │   ├── Restaurant/
│   │   ├── Pharmacy/
│   │   └── Enterprise/
│   ├── DTO/V2/                   # Request/Response DTOs
│   ├── Repositories/V2/          # Repository implementations
│   ├── Policies/V2/              # Authorization policies
│   ├── Events/V2/                # Domain & integration events
│   ├── Jobs/V2/                  # Queue jobs
│   ├── Hardware/                 # SDK (existing + V2 extensions)
│   │   ├── Contracts/
│   │   └── Drivers/
│   └── Extensions/               # Extension SDK registry
├── config/v2/                    # V2 config, permissions, settings schema
├── routes/pos-v2.php             # V2 routes (included when flag on)
├── views/v2/                     # V2 PHP views (shell only, no inline JS/CSS)
├── public/assets/pos/v2/
│   ├── css/                      # tokens, components, screens
│   └── js/                       # modules, components, state
└── docs/v2/                      # This documentation set
```

---

## 6. Module Relationships

| Module | Relationship to POS V2 |
|--------|------------------------|
| Inventory (`rateb_inventory`) | Read/validate/reserve via `PosInventoryBridgeService` |
| Accounting | Post via existing checkout bridge; V2 emits events only |
| CRM/Customers | Search/attach via `PosCustomerBridgeService` |
| Branches | Scope via `BranchContext` + `PosBranchBridgeService` |
| Warehouses | Terminal warehouse scope; no UI exposure |
| Authorization | Extend permissions additively in migration |
| Documents/ZATCA | Receipt via `PosReceiptService` + `PosZatcaBridgeService` |
| Notifications (ERP) | Enterprise notifications via bridge |

---

## 7. Lifecycle of a Sale

```
1. SHIFT_OPEN     → PosShiftService (V1) · V2 gate UI
2. SESSION_BIND   → PosSessionService · terminal + warehouse context
3. CATALOG_BROWSE → PosInventoryBridgeService (read)
4. CART_MUTATE    → PosRegisterCartService · reservations
5. CUSTOMER_ATTACH→ PosCustomerBridgeService (optional)
6. DISCOUNT_APPLY → PosPricingService (pre-Charge only)
7. CHARGE         → V2 state transition · open payment sheet
8. PAYMENT        → PosCheckoutService · payments recorded
9. STOCK_POST     → PosInventoryBridgeService (on commit)
10. RECEIPT       → PosReceiptService · print queue
11. NEW_SALE      → session cart clear · return to idle
```

Each step emits domain events (see POS-V2-EVENT-ARCHITECTURE.md). Failures roll back at transaction boundaries defined in use cases.

---

## 8. Register Flow

```
[Shift Gate] → [Register Idle] ⇄ [Active Sale]
                    │                │
                    │         [Customer Sheet]
                    │         [Discount Sheet]
                    │         [Modifier Sheet] (profile)
                    ↓
              [Charge] → [Payment Sheet] → [Receipt] → [Register Idle]
```

**State owner:** `PosV2RegisterOrchestrator` (application) + `PosSessionService` (V1 adapter).

---

## 9. Payment Flow

```
PaymentSheetOpened
  → SelectTender
  → EnterAmount (numpad)
  → [Card?] → TerminalWaitState (Screen 28)
  → [Split?] → AddPaymentLine → balance check
  → [Cash overpay?] → ChangeDue calculated
  → CompleteSaleUseCase
      → PosCheckoutService.checkout()
      → Payments persisted
      → ReceiptPreviewOpened
```

**Idempotency:** `idempotency_key` on Complete request; stored on order.

---

## 10. Return Flow

```
More → ReturnOverlay
  → SearchOrder (barcode/order #)
  → SelectReturnLines + qty
  → SelectRefundTender
  → [Approval?] → ManagerApprovalSheet
  → ProcessReturnUseCase
      → PosReturnService
      → Refund + stock return via bridge
  → Receipt/confirmation
```

---

## 11. Approval Flow

```
RestrictedActionRequested
  → CreateApprovalRequest (pending)
  → ManagerApprovalSheet (PIN/scan)
  → Approve → short-lived ApprovalToken issued
  → Original action retried with X-Rateb-Approval-Token header
  → Token consumed · audit logged
  → Deny → action blocked · cashier sees PermissionDenied pattern
```

**Token TTL:** 60 seconds default (configurable).

---

## 12. Hardware Flow

```
PosHardwareManager (registry)
  → Discover devices (terminal device_meta)
  → HealthCheck (scheduled + on-demand)
  → Action requested (print, drawer pulse, scale read, terminal charge)
  → Driver.execute()
  → HardwareEvent emitted
  → Failure → PrintFailureSheet / DeviceStatus update
```

See POS-V2-HARDWARE-SDK.md.

---

## 13. Offline Flow

```
Online ──(connectivity lost)──► DegradedBanner (Screen 16)
  → [Policy allows?] ──no──► read-only / block Complete
  → [Policy allows] ──yes──► EmergencyMode (Screen 50)
      → cash-only · limits enforced
      → actions queued in rateb_pos_sync_queue
      → on reconnect: SyncWorker processes queue
      → Conflict? → ConflictResolutionSheet (Screen 44)
```

**Default Phase 1:** banner only; no silent offline sales.

---

## 14. Restaurant Profile Architecture

**Flag:** `POS_V2_PROFILE=restaurant`

Additional bounded context slice:

```
Restaurant/
├── TableMapService      → rateb_pos_dining_tables
├── TabService           → rateb_pos_table_sessions
├── SplitBillService     → rateb_pos_order_splits
├── MergeTableService
├── KitchenTicketService → rateb_pos_kitchen_tickets
└── TipService           → extends payment aggregate
```

Register shell unchanged; **mode switch** to TableMap on entry. Tabs replace suspend semantics for dine-in. Split creates child orders linked via `linked_order_id`.

---

## 15. Pharmacy Profile Architecture

**Flag:** `POS_V2_PROFILE=pharmacy`

```
Pharmacy/
├── PrescriptionValidationService → rateb_pos_prescription_links
├── ControlledDrugApprovalService → chains Approval BC
└── BatchPickerService → extends inventory bridge (plain language UI)
```

Rx gate **blocks** `AddToCartUseCase` until validation token present.

---

## 16. Future Extension Architecture

Third-party modules register via **Extension SDK** (POS-V2-EXTENSION-SDK.md):

```
PosExtensionRegistry
  → ServiceProvider boot
  → Hook registration (toolbar, payment, receipt)
  → Permission declaration
  → Event subscription
  → No core file modification
```

Versioned extension manifest: `rateb-pos-extension.json`.

---

## 17. Deployment & Runtime

| Concern | Mechanism |
|---------|-----------|
| Route loading | `PosModule::init()` registers V2 routes if flag |
| Asset loading | V2 shell loads `v2/js/app.js` only |
| API versioning | `/pos/api/v2/*` + header `X-Rateb-Pos-Version: 2` |
| Rollback | Disable `POS_V2_ENABLED`; V1 default |
| Migrations | Additive only; nullable columns |

---

## 18. Related Documents

- POS-V2-DOMAIN-MODEL.md
- POS-V2-EVENT-ARCHITECTURE.md
- POS-V2-QUEUE-ARCHITECTURE.md
- POS-V2-HARDWARE-SDK.md
- POS-V2-EXTENSION-SDK.md
- POS-V2-OPENAPI.yaml
- POS-V2-DECISIONS.md

---

*End of POS-V2-ARCHITECTURE.md*
