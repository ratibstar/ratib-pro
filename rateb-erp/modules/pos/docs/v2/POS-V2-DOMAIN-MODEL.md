# RATEB POS V2 — Domain Model

**Version:** 1.0.0  
**Pattern:** DDD tactical design with Repository + Policy + Domain Events

---

## 1. Register Domain

### Responsibilities
- Shift gate enforcement before register access
- Session binding to terminal, branch, warehouse
- Register UI state orchestration (idle, active sale, overlays)
- Charge transition (cart → payment)

### Entities
| Entity | Key attributes |
|--------|----------------|
| `PosShift` | id, terminal_id, user_id, opened_at, closed_at, status |
| `PosSession` | id, shift_id, terminal_id, cart_state, customer_id |
| `PosTerminal` | id, branch_id, warehouse_id, device_meta |

### Value Objects
- `RegisterContext` — terminal, shift, session, warehouse, profile
- `Money` — amount, currency (SAR default)
- `RegisterState` — enum: idle, active_sale, payment, overlay

### Aggregates
- **ShiftAggregate** (root: PosShift) — open/close, cash counts
- **SessionAggregate** (root: PosSession) — cart lifecycle

### Repositories
- `ShiftRepositoryInterface`
- `SessionRepositoryInterface`
- `TerminalRepositoryInterface`

### Services
- `RegisterGateService` — validates shift open
- `RegisterOrchestrator` — state machine (application-facing)

### Policies
- `ShiftPolicy` — open, close, view
- `RegisterPolicy` — access register screen

### Events
- `ShiftOpened`, `ShiftClosed`, `SessionStarted`, `ChargeInitiated`

### Dependencies
- Payment (via ChargeInitiated)
- Approval (shift variance)
- Inventory Bridge (warehouse scope)

---

## 2. Cart Domain

### Responsibilities
- Line items, qty, modifiers, notes
- Price display (delegates calculation to Pricing)
- Reservations via inventory bridge

### Entities
| Entity | Attributes |
|--------|------------|
| `CartLine` | product_id, qty, unit_price, discount, modifiers, note |
| `CartSnapshot` | session_id, payload_json, version |

### Value Objects
- `LineItemId`, `Quantity`, `ModifierSelection[]`
- `CartTotals` — subtotal, tax, discount, total

### Aggregates
- **CartAggregate** (root: session cart) — add/update/remove lines

### Repositories
- `CartRepositoryInterface` (wraps PosRegisterCartService)

### Services
- `CartMutationService`
- `CartSnapshotService` (session recovery)

### Policies
- `CartPolicy` — modify, clear, override price

### Events
- `LineAdded`, `LineUpdated`, `LineRemoved`, `CartCleared`, `CartSnapshotSaved`

### Dependencies
- Catalog (product resolution)
- Inventory Bridge (reservations)
- Discount (applied totals)
- Approval (price override)

---

## 3. Orders Domain

### Responsibilities
- Order creation from cart at checkout
- Order status lifecycle
- Link to payments, receipts, returns

### Entities
| Entity | Attributes |
|--------|------------|
| `PosOrder` | order_no, status, totals, customer_id, idempotency_key |
| `PosOrderLine` | product_id, qty, price, tax |

### Value Objects
- `OrderNumber`, `OrderStatus`, `IdempotencyKey`

### Aggregates
- **OrderAggregate** (root: PosOrder)

### Repositories
- `OrderRepositoryInterface`

### Services
- `OrderFactoryService` — cart → order
- `OrderQueryService` — search for returns

### Policies
- `OrderPolicy` — view, void (manager)

### Events
- `OrderCreated`, `OrderCompleted`, `OrderVoided`

### Dependencies
- Payment, Receipt, Inventory Bridge, Accounting Bridge

---

## 4. Payments Domain

### Responsibilities
- Tender selection and amount entry
- Split payments, change calculation
- Card terminal orchestration (Phase 3)
- Tips (restaurant profile)

### Entities
| Entity | Attributes |
|--------|------------|
| `PosPayment` | order_id, method, amount, reference |
| `PosPaymentLine` | split payment row |

### Value Objects
- `PaymentMethod` — cash, card, wallet, store_credit, etc.
- `TenderAmount`, `ChangeDue`, `PaymentBalance`

### Aggregates
- **PaymentAggregate** (root: payment collection for order)

### Repositories
- `PaymentRepositoryInterface`

### Services
- `PaymentCollectionService`
- `SplitPaymentService`
- `TerminalPaymentService` (hardware)

### Policies
- `PaymentPolicy` — complete sale, refund tender

### Events
- `PaymentRecorded`, `PaymentCompleted`, `TerminalPaymentStarted`, `TerminalPaymentFailed`

### Dependencies
- Orders, Hardware (terminal), Receipt

---

## 5. Discounts Domain

### Responsibilities
- Line and cart discounts (pre-Charge only)
- Coupon/promo validation
- Manager approval thresholds

### Entities
| Entity | Attributes |
|--------|------------|
| `PosDiscountApplication` | scope, type, value, reason |

### Value Objects
- `DiscountType` — percent, fixed, coupon
- `DiscountScope` — line, cart

### Aggregates
- **DiscountAggregate** (applied discounts on cart)

### Repositories
- `DiscountRepositoryInterface` (reads promos from ERP)

### Services
- `DiscountApplicationService` (wraps PosPricingService)

### Policies
- `DiscountPolicy` — apply, max percent by role

### Events
- `DiscountApplied`, `DiscountRemoved`, `DiscountApprovalRequired`

### Dependencies
- Cart, Approval, Pricing (V1)

---

## 6. Returns Domain

### Responsibilities
- Return/exchange against completed orders
- Refund tender selection
- Stock return via bridge

### Entities
| Entity | Attributes |
|--------|------------|
| `PosReturn` | order_id, lines, refund_method |
| `PosRefund` | amount, payment_id |

### Value Objects
- `ReturnReason`, `ReturnLineSelection`

### Aggregates
- **ReturnAggregate** (root: PosReturn)

### Repositories
- `ReturnRepositoryInterface`

### Services
- `ReturnProcessingService` (wraps PosReturnService)
- `ExchangeService`

### Policies
- `ReturnPolicy` — process, exchange

### Events
- `ReturnInitiated`, `ReturnCompleted`, `RefundIssued`

### Dependencies
- Orders, Payment, Inventory Bridge, Approval

---

## 7. Approvals Domain

### Responsibilities
- Manager approval for restricted actions
- Token issuance and consumption
- Audit trail linkage

### Entities
| Entity | Attributes |
|--------|------------|
| `ApprovalRequest` | action, payload, status, approver_id |
| `ApprovalToken` | token_hash, expires_at, consumed |

### Value Objects
- `ApprovalAction` — discount, price_override, return, shift_variance, void
- `ApprovalStatus` — pending, approved, denied, expired

### Aggregates
- **ApprovalAggregate** (root: ApprovalRequest)

### Repositories
- `ApprovalRepositoryInterface`

### Services
- `ApprovalWorkflowService`
- `ApprovalTokenService`

### Policies
- `ApprovalPolicy` — approve, deny (manager/supervisor)

### Events
- `ApprovalRequested`, `ApprovalGranted`, `ApprovalDenied`, `ApprovalTokenConsumed`

### Dependencies
- All domains requiring elevation

---

## 8. Hardware Domain

### Responsibilities
- Device registry, driver lifecycle
- Print, drawer, scale, scanner, terminal, NFC
- Health and diagnostics

### Entities
| Entity | Attributes |
|--------|------------|
| `PosDevice` | type, driver, config, status |
| `PrintJob` | payload, status, retries |

### Value Objects
- `DeviceType`, `DeviceStatus`, `DriverId`

### Aggregates
- **DeviceAggregate**, **PrintJobAggregate**

### Repositories
- `DeviceRepositoryInterface`, `PrintJobRepositoryInterface`

### Services
- `PosHardwareManager` (existing, extended)
- `PrintQueueService`
- `DeviceHealthService`

### Policies
- `HardwarePolicy` — configure, test print

### Events
- `DeviceDiscovered`, `PrintJobQueued`, `PrintJobCompleted`, `PrintJobFailed`, `DrawerOpened`

### Dependencies
- Payment (terminal), Receipt (print)

---

## 9. Sessions Domain

### Responsibilities
- Browser/session persistence
- Recovery after crash
- Cart snapshot restore

### Entities
- `PosSession`, `CartSnapshot`

### Value Objects
- `SessionToken`, `RecoveryState`

### Aggregates
- **SessionRecoveryAggregate**

### Repositories
- `SessionRepositoryInterface`, `CartSnapshotRepositoryInterface`

### Services
- `SessionRecoveryService`

### Policies
- `SessionPolicy`

### Events
- `SessionRecovered`, `SessionExpired`

### Dependencies
- Cart, Register

---

## 10. Devices Domain (Terminal Identity)

### Responsibilities
- Terminal registration, licensing binding
- device_meta JSON (hardware config)
- Multi-terminal branch scope

### Entities
- `PosTerminal`

### Value Objects
- `TerminalCode`, `DeviceMeta`, `LicenseBinding`

### Repositories
- `TerminalRepositoryInterface`

### Services
- `TerminalRegistrationService`
- `LicenseValidationService`

### Events
- `TerminalRegistered`, `LicenseValidated`, `LicenseExpired`

### Dependencies
- Licensing config, Hardware

---

## 11. Restaurant Domain

### Responsibilities
- Table map, tabs, split/merge, kitchen tickets, tips

### Entities
| Entity | Attributes |
|--------|------------|
| `DiningTable` | number, zone, status |
| `TableSession` | table_id, order_ids |
| `KitchenTicket` | order_id, station, status |
| `OrderSplit` | parent_order_id, child_order_id |

### Value Objects
- `TableStatus`, `KitchenStation`, `SplitMode`

### Aggregates
- **TableSessionAggregate**, **KitchenTicketAggregate**

### Repositories
- `DiningTableRepositoryInterface`, `KitchenTicketRepositoryInterface`

### Services
- `TableMapService`, `SplitBillService`, `MergeTableService`, `KitchenRoutingService`

### Policies
- `RestaurantPolicy` — merge tables, void course

### Events
- `TableOpened`, `TableMerged`, `BillSplit`, `KitchenTicketSent`, `TipAdded`

### Dependencies
- Orders, Payment, Queue (kitchen)

---

## 12. Pharmacy Domain

### Responsibilities
- Prescription validation gate
- Controlled substance approval
- Batch/expiry selection (plain language UI)

### Entities
| Entity | Attributes |
|--------|------------|
| `PrescriptionLink` | rx_number, patient_ref, validated_at |
| `ControlledDrugLog` | product_id, approver_id |

### Value Objects
- `RxNumber`, `ValidationToken`, `BatchSelection`

### Aggregates
- **PrescriptionAggregate**

### Repositories
- `PrescriptionRepositoryInterface`

### Services
- `PrescriptionValidationService`
- `ControlledDrugService`
- `BatchPickerService`

### Policies
- `PharmacyPolicy` — dispense controlled, override batch

### Events
- `PrescriptionValidated`, `ControlledDrugDispensed`

### Dependencies
- Cart, Approval, Inventory Bridge

---

## 13. Inventory Bridge Domain (Anti-Corruption)

### Responsibilities
- Product catalog read for POS scope
- Stock validation and reservation
- Post-sale stock movement
- Batch/serial when required

### Entities
- None owned; maps ERP inventory entities

### Services
- `PosInventoryBridgeService` (existing V1 adapter)

### Events (integration)
- `StockReserved`, `StockReleased`, `StockPosted`, `StockInsufficient`

### Dependencies
- ERP Inventory module only via bridge

---

## 14. Accounting Bridge Domain

### Responsibilities
- GL posting on sale completion
- Refund reversal entries
- Shift cash variance posting

### Services
- Existing checkout/accounting bridge adapters

### Events
- `AccountingEntryPosted`, `AccountingEntryFailed`

### Dependencies
- Orders, Payment, Returns

---

## 15. CRM Bridge Domain

### Responsibilities
- Customer search and attach
- Loyalty points (future)
- Receipt delivery preferences

### Services
- `PosCustomerBridgeService` (existing)

### Events
- `CustomerAttached`, `CustomerDetached`

### Dependencies
- Cart, Orders

---

## 16. Cross-Domain Context Map

```
Register ──► Cart ──► Orders ──► Payment
   │           │         │          │
   │           ▼         ▼          ▼
   │      Inventory   Accounting  Hardware
   │         Bridge      Bridge      │
   ▼                                   ▼
Shift                              PrintJob
   │
   └──► Approval ◄── Discount, Return, Override
```

---

*End of POS-V2-DOMAIN-MODEL.md*
