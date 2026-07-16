# P14-00 — Phase 14 Accounting Audit (Enterprise Report)

**Status:** COMPLETE (evidence only)  
**Architecture Freeze:** AF 2.1 + AF 2.1.1 ACTIVE  
**Implementation:** NONE — STOP after audit

Interactive summary: open the Phase 14 Accounting Audit canvas beside chat (`phase14-accounting-audit.canvas.tsx`).

---

## Executive verdict

Online Accounting is a **mature GL-centric module** centered on `AccountingService` (COA provisioning, posting engine, fiscal periods, cash vouchers, AR/AP, TB/BS/P&L/VAT).

Offline V2 Accounting BusinessModule must:

- Own **accounting documents only** (COA, journals, fiscal, vouchers, reports).
- Depend on **identity** + **inventory**.
- **Never** own or mutate inventory balances/valuation.
- Consume Inventory via `module.inventory.*` (reads) and domain events from Sales/Procurement — never direct SQL to those stores.

---

## 1. General Ledger & Chart of Accounts

- Tables: `rateb_chart_of_accounts`, `rateb_journal_entries`, `rateb_journal_lines`.
- Default COA tree auto-provisioned (`DEFAULT_ACCOUNTS` in `AccountingService`).
- Types: `asset|liability|equity|revenue|expense`.
- Company-scoped COA with optional platform template (`company_id IS NULL`).

**Key posting codes:** 1100 Cash · 1200 AR · 1210 VAT input · 1300 Inventory · 2100 AP · 2200 VAT payable · 4100 Revenue · 4900 Sales returns · 5100 Procurement expense · 5200 COGS.

---

## 2–4. Journal entries & posting engine

**Writer:** `createPostedEntry()` — checks balance, open fiscal period, ledger mutable; inserts posted entry + lines; emits gateway event.

**Idempotency:** `source_type` + `source_id` via `journalExistsForSource`.

**Manual path:** `createManualDraft` / `updateManualDraft` → submit for approval → `postDraftEntry` / void.

**Statuses:** `draft|posted|void` (+ submission stamps for approval).

---

## 5–6. Fiscal periods & cost centers

- `rateb_fiscal_periods`: `open|closed`; closed period blocks post.
- `rateb_cost_centers` + optional `cost_center_id` on journal lines.
- Enterprise: profit centers, recurring journals (migration 182).

---

## 7–10. Integrations

| Source | Behavior |
|--------|----------|
| Platform invoice | Dr 1200 / Cr 4100 / Cr 2200 |
| Payment | Dr 1100 / Cr 1200 |
| PO confirmed | Dr 5100 (+VAT) / Cr 2100 |
| PO received | Dr 1300 (+VAT) / Cr 2100 |
| Purchase invoice landed | Dr 1300 / Cr 2100 |
| Stock movement in/out | Dr/Cr 1300 ↔ AP/COGS (qty×unit_cost from inventory row) |
| POS | `PosAccountingBridgeService` → `createPostedEntry` (revenue + COGS) |
| Cash vouchers | Receipt/payment ↔ counter account |

**Customers/Suppliers:** AR from `rateb_invoices` (platform); AP from purchase orders / supplier invoices — not a full subledger per party in GL.

---

## 11–12. Tax & currency

- VAT report aggregates 1210/2200 activity.
- ZATCA settings UI + `ZatcaService` for billing profiles/QR.
- Enterprise: `rateb_accounting_tax_codes`, currencies, exchange rates.
- **Gap:** posting paths largely assume SAR; FX/tax codes not fully driving every auto-post.

---

## 13–16. Financial reports

Implemented in `AccountingService` / dashboard controllers:

- Trial Balance · Balance Sheet · P&L · Cost of Sales · VAT · AR · AP · Budget vs Actual · COA tree · Journal register · Account statement · Partner subsidiary ledger · CFO dashboard · Bank reconciliation UI.

Routes under `routes/modules/ops.php` (`accounting/*`).

---

## 17–21. Permissions, APIs, schema, services, workflow

**Permissions:** `accounting.view|create|update|manage|approve|post|reverse|close_period` (+ role bundles).

**Services:** `AccountingService` (core), Domain/Enterprise/Reports/Dashboard/BranchScope/Support, POS accounting bridge.

**Workflow:** draft → submit → approve/post; fiscal close; oversight undo for journals/vouchers.

**Sync:** Online `accounting/sync` re-posts from sources (`syncFromSources`). Offline V1 has accounting feature flags — **frozen**, do not lift. Offline V2 Sync must carry **journal/COA/fiscal events only**, never credentials or calculated balances as SoT.

---

## 22. Sync boundaries (V2 implication)

| May sync | Must NOT sync |
|----------|----------------|
| Journal posted/void events | Inventory balances |
| COA / fiscal period changes | Passwords / tokens / cookies |
| Voucher lifecycle events | Ownership / derived TB balances as source of truth |

---

## 23–26. Reusable

- Double-entry + balanced-lines contract  
- Source idempotency keys  
- COA semantic vocabulary (codes as concepts)  
- Journal / fiscal / voucher status machines  
- `createPostedEntry` as PostingPort concept  
- Cost center dimension  
- Report shapes (TB/BS/P&L/VAT)  
- Permission matrix  
- Currency/tax_code master concepts  

---

## 27. Non-reusable

- PHP monolith `AccountingService` + controllers/views  
- Hardcoded account codes in `post*` methods  
- Direct SQL to inventory / PO / invoice tables  
- Platform invoices as ops AR  
- Offline V1 accounting adapters  
- POS bridge as Sales substitute  
- Dual inventory GL writers without clear ownership  

---

## 28. Risks

| ID | Severity | Risk |
|----|----------|------|
| R1 | Critical | Double inventory GL (PO received + stock_movement + landed) |
| R2 | Critical | Accounting SQL-reads inventory `unit_cost` (AF 2.1.1 spirit) |
| R3 | High | Hardcoded COA codes |
| R4 | High | AR ≠ POS/ops sales |
| R5 | High | God-class service |
| R6 | High | Silent null return on period/ledger lock |
| R7 | Medium | Incomplete TaxPolicy / FX on post |
| R8 | Medium | FX tables underused |
| R9 | Medium | Offline V1 accounting must stay frozen |

---

## 29. Missing abstractions

1. **PostingPort** — sole GL writer  
2. **AccountMap / CoaPolicy** — semantic role → account  
3. **InventoryValuationReadPort** — `module.inventory.valuation` only  
4. **Domain events** — `journal.posted`, `period.closed`  
5. **Source document contracts** — Sales/Proc/Inv emit; Accounting posts  
6. **TaxPolicy / CurrencyPolicy**  
7. **Clear rule:** who posts inventory GL (one owner)  
8. **Ops AR vs platform billing** separation  
9. **Offline sync DTOs** — events only  

---

## Recommended Accounting BusinessModule plan

1. Charter BM — docs only; deps `identity` + `inventory`; AF 2.1 / 2.1.1.  
2. Local `acct.*` entity storage — never `inv.*` / `sales.*` / `proc.*` / `identity.*`.  
3. Implement PostingPort (balance + period + idempotency).  
4. AccountMap settings — no hardcoded codes in post paths.  
5. Integrate via Inventory APIs + Sales/Procurement events only.  
6. Local TB / BS / P&L from posted journals.  
7. RBAC via `module.identity.*`.  
8. Evidence + STOP before next module.

---

## Phase boundary

**Phase 14 Audit: COMPLETE**  
**Do NOT implement Accounting BusinessModule in this phase.**  
**STOP.**
