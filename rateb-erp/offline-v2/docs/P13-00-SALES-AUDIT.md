# P13-00 — Phase 13 Sales Audit (Enterprise Report)

**Status:** COMPLETE (evidence only)  
**Architecture Freeze:** AF 2.1 + AF 2.1.1 ACTIVE  
**Implementation:** NONE — STOP after audit

Interactive summary: open the Phase 13 Sales Audit canvas beside chat in Cursor (`phase13-sales-audit.canvas.tsx`).

---

## Executive verdict

**There is no classic B2B Sales module in the online ERP.**

| Expectation | Online reality |
|-------------|----------------|
| Customer quotation → SO → Delivery → Invoice | **Absent** |
| `SalesService` / `SaleOrder` / `rateb_sales_*` | **Not found** |
| Operational sales | **POS** (`modules/pos`, `rateb_pos_*`) |
| Customer master | **Accounting** (`rateb_customers`) |
| “Invoices” in admin | **Platform SaaS billing** (`rateb_invoices`) |
| “Quotations” in ops nav | **Procurement supplier quotes** |

Offline V2 Sales BusinessModule will be largely **greenfield** for B2B documents, with **POS as a retail concept reference** — not a lift-and-shift of PHP inventory writers.

---

## 1. Sales architecture

### Primary engine: POS

Orchestration: `PosCheckoutService` → inventory bridge + accounting bridge + order persistence.

Supporting: Quote, Return, Exchange, Suspend, Pricing, SellPrice, DiscountGuard, SupervisorApproval, InventoryReservation (POS-owned).

V2 layer: UseCases + Ports + DTOs under `modules/pos/app/` (cleaner register API boundary).

### Adjacent (not Sales docs)

- `BillingService` / `InvoicesController` — subscription/platform invoices  
- `CustomersController` — customer CRUD under accounting permissions  
- `CrmWorkflowService` — lead pipeline  
- `QuotationsController` — **supplier** quotations (procurement)

---

## 2–6. Document audit

| Document | Finding |
|----------|---------|
| **Quotations** | POS only: `order_type=quote`, cart in suspended payload, convert voids quote then checkout. No B2B customer quotation entity. |
| **Sales orders** | None. Closest: `rateb_pos_orders` `order_type=sale`. |
| **Invoices** | Ops: POS receipt on order. Platform: `rateb_invoices` draft→sent→paid. Two disconnected worlds. |
| **Deliveries** | None. Stock issues **immediately** at POS checkout. |
| **Returns** | POS: `PosReturnService` / `PosExchangeService` → return/exchange orders + stock in. |

---

## 7–10. Pricing, discounts, taxes, customers

**Price hierarchy** (`PosSellPriceService`): manual → promotion → customer price group → branch → inventory `sell_price` / `unit_cost`.

**Discounts:** line %/amount + invoice discount; gated by `pos.discount.manage` / V2 `pos.discount.apply`.

**Tax:** default **15%** hardcoded in `PosPricingService` — not a shared tax engine with ZATCA company profiles.

**Customers:** `rateb_customers` (+ `price_group_id`); POS via `PosCustomerBridgeService`.

---

## 11–12. Approvals & permissions

- Supervisor: `rateb_pos_approval_requests` / `grants` via `PosSupervisorApprovalService`.
- Permission family: **`pos.*`** (register, discount, returns, supervisor, reports, …).
- Customers: `accounting.view|manage`.
- Platform invoices: `billing.manage`.
- **No `sales.*` permission family.**

---

## 13–16. APIs, schema, services, workflow

**APIs:** POS register/checkout/quote/return/exchange (+ `/api/v1/pos/...` and V2 payment/complete). Thin REST elsewhere does not define a Sales document API.

**Schema (core):** `rateb_pos_orders` (+ lines, payments, returns, refunds, reservations, gl_postings, approvals, price tables); `rateb_customers`; `rateb_invoices`.

**Order statuses:** `draft|processing|completed|void|suspended`.  
**Order types:** `sale|return|exchange|quote|suspended`.

**Services (cheat-sheet):** Checkout / Return / Exchange / Quote / Pricing / SellPrice / InventoryBridge / AccountingBridge / CompleteSaleUseCase.

---

## 17. Inventory integration (critical for AF 2.1.1)

| Concern | Online behavior | V2 implication |
|---------|-----------------|----------------|
| Soft reserve | POS table `rateb_pos_inventory_reservations` | Must use `module.inventory.reserve` |
| Issue on sale | `StockMovementService` `out` + `reference_type=pos_order` | `module.inventory.postMovement` only |
| Serials | **Direct SQL** `UPDATE rateb_inventory_serials` (`markSerialSold`) | Forbidden dual-write |
| Deprecated path | `postSaleForOrder()` non-transactional | Do not copy |

Evidence: `PosInventoryBridgeService.php` (~postSaleForOrderInTransaction, markSerialSold).

---

## 18. Accounting integration

- POS GL: revenue + COGS + payment accounts via `PosAccountingBridgeService` (`pos_sale_*` sources).
- Platform invoice GL: AR 1200 / revenue 4100 / VAT 2200 via `AccountingService::postInvoice`.
- **Gap:** POS sales do not create `rateb_invoices` or customer AR balances.

---

## 19. Identity integration

Online: `rateb_can` + module middleware + POS V2 permission contexts + tenant company scoping.

Offline V2 constraint: Sales BM must use **`module.identity.*` only** — never own auth.

---

## 20–23. Reusable

- Document vocabulary: quote / sale / return / exchange / receipt  
- Status machine shapes  
- Price resolution hierarchy (concept)  
- Discount model (line + header)  
- Tax as rate × base (concept — not 15% constant)  
- Customer master fields / price group  
- Supervisor approval grant lifecycle  
- POS V2 UseCase + Port + DTO pattern  
- GL source idempotency idea  
- Permission shape (register / discount / returns / approve)

---

## 24. Non-reusable

- PHP controllers / POS views / CrudController patterns  
- Direct `StockMovementService` + inventory serial SQL  
- POS-owned reservation table pattern  
- Platform `rateb_invoices` as ops sales invoices  
- Hardcoded 15% VAT  
- Deprecated dual stock post path  
- Offline V1 POS/sync code (frozen)  
- Assumption that B2B SO/DN/Invoice already exist  
- Ops “Quotations” = procurement (naming trap)

---

## 25. Risks

| ID | Severity | Risk |
|----|----------|------|
| R1 | Critical | POS mutates serials + owns soft reserves (AF 2.1.1 spirit) |
| R2 | Critical | No B2B Sales domain online → greenfield V2 |
| R3 | High | Two invoice worlds (platform AR ≠ POS) |
| R4 | High | Deprecated non-transactional stock post still present |
| R5 | High | Tax hardcoded 15% |
| R6 | High | No delivery / ship-then-invoice lifecycle |
| R7 | Medium | Quotations naming collision with Procurement |
| R8 | Medium | No `sales.*` RBAC family |
| R9 | Medium | No Sales domain events |

---

## 26. Missing abstractions

1. Sales aggregate roots: Quotation · SO · Delivery · SalesInvoice · SalesReturn  
2. Sales→Inventory Port (`reserve` / `release` / `postMovement` only)  
3. Sales→Identity Port (`module.identity.*`)  
4. PricingPolicy / TaxPolicy (configurable)  
5. Delivery vs invoice split (stock vs AR)  
6. Ops customer AR (if required) vs platform billing  
7. Domain events for sync (documents only)  
8. `sales.*` permission family  
9. Offline sync DTOs — never balances / credentials / ownership state  

---

## Recommended Sales BusinessModule implementation plan

1. **Charter** — own sales documents only; deps `identity` + `inventory`; AF 2.1 + 2.1.1.  
2. **Scope decision** — A: POS-parity retail docs · **B (recommended): greenfield B2B Quotation→SO→Delivery→Invoice→Return**.  
3. **DTOs / events / status machines** — reuse POS *concepts*; never copy PHP/inv SQL.  
4. **Local storage** — `sales.*` (or `sale.*`) entity rows only; never `inv.*` / `identity.*` / `proc.*`.  
5. **Inventory** — all stock via `module.inventory.*` (no POS reservation dual-write).  
6. **Pricing + tax** — settings-driven policies; RBAC via Identity.  
7. **Accounting** — defer ownership; emit events only if needed (future Accounting BM).  
8. **Evidence + STOP** — self-tests + compliance before next module.

---

## Phase boundary

**Phase 13 Audit: COMPLETE**  
**Do NOT implement Sales BusinessModule in this phase.**  
**STOP.**
